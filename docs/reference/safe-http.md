# SafeHttp — outbound requests to URLs someone else chose

> Written in Sprint 1 slice 6, which closed audit finding **S-08** (SSRF in the oEmbed resolver).
> Companion to `docs/reference/authorization.md`: that one governs who may act, this one governs
> where the server may be made to go.

## The rule

**Any outbound HTTP request whose URL was influenced by anyone other than the operator goes through
`klytos_safe_http()`. Never `klytos_http_get()`, never raw cURL, never `file_get_contents()`.**

"Influenced" is broad on purpose — a request parameter, stored page content, an MCP tool argument, a
plugin file header, a value read back out of the database. If the operator did not type it into a
config file themselves, it is influenced.

Fixed URLs the product itself owns (the update server, the licence API, a payment provider's API
base) do not need it, because there is nothing for an attacker to steer.

## Why it exists

Before this slice the product had exactly one working SSRF control — `ImportValidator::validateUrl()`
in the importer plugin — while six core call sites fetched user- or AI-influenced URLs behind nothing
but `filter_var( $url, FILTER_VALIDATE_URL )`, which accepts all of these without complaint:

```
http://127.0.0.1:6379/            → the host's own Redis
http://169.254.169.254/latest/    → cloud instance metadata, i.e. credentials
http://[::1]/                     → the same host, IPv6
http://192.168.1.1/               → whatever else is on the LAN
```

That is the S-07 shape: a rule every author has to remember at every new call site. It gets the S-07
answer — one implementation, applied centrally, with the unsafe path being the one that takes extra
typing.

## Usage

```php
$result = klytos_safe_http()->fetch( $url, [
    'timeout' => 10,
    'headers' => [ 'Accept' => 'application/json' ],
] );

if ( $result['blocked'] !== null ) {
    // Refused before any socket opened. $result['blocked'] is a REASON_* constant.
    // Do NOT echo it back to the caller — see "Never answer as an oracle" below.
    return false;
}

if ( $result['error'] !== null || $result['status'] >= 400 ) {
    return false;
}

return $result['body'];
```

To check without fetching:

```php
if ( ! klytos_safe_http()->isAllowed( $url ) ) {
    throw new \InvalidArgumentException( 'That URL is not allowed.' );
}
```

### Return shape

`fetch()` returns the `HttpClient` array plus two keys:

| Key | Type | Meaning |
|---|---|---|
| `status` | int | HTTP status, `0` when refused |
| `headers` | array | Response headers, lowercased keys |
| `body` | string | Response body, `''` when refused |
| `error` | ?string | Transport error, or the refusal message |
| `blocked` | ?string | `REASON_*` constant when refused, `null` otherwise |
| `final_url` | string | The URL actually fetched, or the one that was refused |

**Check `blocked` before `status`.** A refusal has `status === 0`, which is also what a connection
failure returns; only `blocked` distinguishes "we would not" from "we could not".

### Refusal reasons

| Constant | Value | Meaning |
|---|---|---|
| `REASON_MALFORMED` | `malformed_url` | Unparseable, or no host |
| `REASON_SCHEME` | `scheme_not_allowed` | Not `http`/`https` |
| `REASON_UNRESOLVABLE` | `host_does_not_resolve` | No address to check — fail closed |
| `REASON_BLOCKED_ADDRESS` | `private_or_reserved_address` | Loopback, private, link-local, reserved |
| `REASON_TOO_MANY_REDIRECTS` | `too_many_redirects` | Hop limit exceeded |

## Redirects are the part people miss

Validating the URL the caller supplied is **necessary and not sufficient**. A public host that passes
the check can answer `302 Location: http://169.254.169.254/`, and both cURL's
`CURLOPT_FOLLOWLOCATION` and PHP's http stream wrapper follow it without asking anyone. Every fetch
in the product did exactly that before this slice.

`SafeHttp` therefore turns the transport's own redirect handling **off** and walks the chain itself,
re-validating every hop. Method and body follow RFC 9110 §15.4 — 301/302/303 become GET with no body,
307/308 keep both — so a call site moving off `CURLOPT_FOLLOWLOCATION` keeps the behaviour it had.

## Never answer as an oracle

A refusal must be indistinguishable, to the caller, from any other failure. `oembed.php` returns the
same generic `400 Invalid URL` for a refused private address as for a malformed one, deliberately:
distinct replies would turn an authenticated editor into an internal-network scanner, one address per
request. The specific reason goes to the error log, not the response.

## Extension points

| Hook | Kind | Signature | Notes |
|---|---|---|---|
| `http.safe.allowed_schemes` | filter | `(string[] $schemes, string $url)` | **Can weaken a security control** |
| `http.safe.max_redirects` | filter | `(int $max, string $url)` | Hop limit |
| `http.safe.redirect` | action | `(string $from, string $to)` | Fires per hop, before validation |
| `http.safe.blocked` | action | `(string $blocked, string $reason, string $origin)` | Audit hook |

`http.safe.blocked` is an **action, not a filter**, and that is deliberate: a listener able to turn a
refusal into a grant would put SSRF policy back in third-party hands. Same reasoning as
`auth.access_denied` in D-032.

`http.safe.allowed_schemes` **can** weaken a shipped control, exactly as `admin.gate_map` can (D-032).
Both are plugin-trust boundaries, and plugins already run as first-party code in this product. What
neither can do is open a hole by *omission* — the default is an allow-list.

## Known limits, stated rather than implied

- **DNS rebinding is NOT closed.** The host is resolved to validate it and resolved again by the
  transport when it connects, so a hostile nameserver with a short TTL can answer public first and
  private second. Closing it means pinning the validated address for the connection
  (`CURLOPT_RESOLVE`). Recorded as audit finding **NEW-15** with its own test point. This class
  raises the cost of SSRF substantially; it does not make it impossible.
- **Both address families are checked.** Resolution reads A records (`gethostbynamel()`) *and* AAAA
  records (`dns_get_record()`), and every returned address must pass. This is not thoroughness for
  its own sake: the transport is dual-stack (no `CURLOPT_IPRESOLVE` is set anywhere), so a host
  publishing a public A and a private AAAA would otherwise pass a v4-only check and still be reached
  over IPv6 — no rebinding trick required, just two DNS records. A host that resolves to nothing at
  all is refused as unresolvable.
- **IPv4-mapped IPv6 is normalized before classification.** `filter_var`'s reserved-range flags do
  not understand `::ffff:127.0.0.1`, so addresses are reduced to their dotted-quad form first. This
  was a live bypass during development, caught by testing the encodings rather than trusting the
  flags — see L-013.
- **`http.before_request` fires after validation.** `SafeHttp` delegates transport to `HttpClient`,
  and `HttpClient::request()` applies the `http.before_request` filter to the method, URL and options
  *after* `SafeHttp` has already validated the URL (`installer/core/http-client.php:91`). A plugin
  listening there can therefore substitute a private address post-check. This is the same
  plugin-trust boundary as `admin.gate_map` (D-032) — plugins run as first-party code here — but it
  is worth naming explicitly, because unlike the scheme filter it is not a security surface anyone
  would *expect* to be one. Do not add a listener there that rewrites hosts.
- **It does not sanitize what comes back.** A response body is still untrusted input and still needs
  escaping or `klytos_kses` at the point of use.

## Where it is applied

| Call site | URL comes from |
|---|---|
| `installer/admin/api/oembed.php` | `$_GET['url']`, and the endpoint discovered inside the fetched page |
| `installer/core/webhook-manager.php` `create()` / `update()` | MCP tool `klytos_create_webhook` |
| `installer/core/webhook-manager.php` `sendHttpPost()` | The stored subscription, re-checked at delivery |
| `installer/core/integrity-checker.php` `registerDeveloperKey()` | A plugin file's `Integrity Key URL` header |
| `installer/core/integrity-checker.php` `httpGet()` | A plugin file's `Integrity URL` header (external plugin manifests) |
| `KlytosImporter\PageFetcher` `fetch()` / `fetchRobotsTxt()` | MCP tool `klytos_import_fetch_page`, and the crawl |
| `KlytosImporter\MediaDownloader::downloadFile()` | Image URLs found in imported content |
| `KlytosImporter\SitemapParser::fetchXml()` | A supplied sitemap, and nested `<sitemapindex>` entries |
| `KlytosImporter\ImportValidator::validateUrl()` | Delegates its pre-flight check here |

Note the distinction on that last row, because it caused a documentation error in this very slice:
`ImportValidator::validateUrl()` delegates only the **check** (`isAllowed()`). It does not perform
the fetch, so a call site that validates through it and then issues its own request with
`CURLOPT_FOLLOWLOCATION` is still exposed to the redirect case. That is exactly what the importer's
three fetchers did, and why they now call `fetch()` directly rather than validating and fetching
separately.

Deliberately **not** applied: `klytos_http_get()` / `klytos_http_post()`. Making the general client
refuse private addresses would break any plugin legitimately calling a LAN or localhost service, and
that is a breaking change with its own decision and release note — not something to slip into a
security slice. Plugin authors fetching untrusted URLs use `klytos_safe_http()`.

## Tests

| What | Where |
|---|---|
| Pre-flight refusals, reasons, positive case, filter wiring | `tests/Unit/SafeHttpTest.php` |
| Real 302 into a private address, per-hop validation, hop limit | `tests/Integration/SafeHttpRedirectTest.php` |
| The endpoint itself, over HTTP, as a real editor | `tests/Integration/OembedSsrfTest.php` |
