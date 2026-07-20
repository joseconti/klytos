# Security headers

> Sprint 1 slice 8 (D-044). Closes audit **S-11**, the **CSP fail-open** and **NEW-14**.
> Related: [authorization.md](authorization.md) — the same one-enforcement-point shape.

Klytos decides its response security headers in exactly one place,
`Klytos\Core\Auth`, and sends them from exactly one place per entry point. This
page is the contract.

> **What this does NOT yet cover, said plainly rather than implied away.** Slice
> 8 covers the **admin** (all 64 entry points) and the **public front
> controller** (`installer/index.php`). It does **not** cover the two standalone
> public entry points under `installer/public/` — `comment-submit.php` and
> `x402-gate.php` — which send no security headers at all, not even `nosniff`.
> They are anonymous and live at fixed, scannable URLs on every install. That is
> NEW-14's own shape one directory over, found by this slice's security review,
> and it is recorded as **NEW-24**, not fixed here. Read "every response carries
> headers" as "every admin response, plus the front controller" until it closes.

## The one enforcement point

`installer/admin/bootstrap.php` generates the request's CSP nonce and calls
`Auth::sendSecurityHeaders()` **once**. Every admin page and every admin API
endpoint requires that bootstrap — 64 entry points, verified mechanically and
re-checked by `php scripts/keel-verify` — so no surface can forget, and a
surface added tomorrow is covered without anyone remembering anything.

This is the same answer authorization got in slice 4, for the same reason: the
alternative is a call at the top of every file, which has the identical failure
mode one file later.

### Placement, and why it cannot move

A header set after output has begun is not set at all. The call therefore sits
**immediately after the boot `try`/`catch`**, and it is pinned on both sides:

| Direction | Constraint |
|---|---|
| Cannot go **later** | Everything below it emits or exits — the pending-rename redirect, the auth guard's 401 JSON and its 302, `klytos_deny()`'s 403 document, the setup-wizard redirect. All four carry the headers today. |
| Cannot go **earlier** | `registerAutoloader()` is **Step 1 of `App::boot()`** (`app.php:268`), so `Auth` does not resolve before boot; and `klytos_apply_filters()` does not exist until `app.php:331` runs inside the same boot. |

The residue of that lower bound is real and is recorded rather than implied:
the **boot-failure page and the two pre-boot redirects send no headers**
(**NEW-22**).

## What is sent

| Header | Value | Notes |
|---|---|---|
| `X-Content-Type-Options` | `nosniff` | The one that matters most for the JSON endpoints. |
| `X-Frame-Options` | `DENY` | |
| `X-XSS-Protection` | `1; mode=block` | Legacy; harmless. |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` | |
| `Strict-Transport-Security` | `max-age=31536000` | **Only over HTTPS.** See below. |
| `Content-Security-Policy` | nonce-based | See below. |

### HSTS

Sent **only when the request arrived over TLS**, and deliberately **without
`includeSubDomains`**.

A browser honours HSTS for the full `max-age` *after the header stops being
sent*, which makes it close to irreversible. `includeSubDomains` would force
HTTPS onto every sibling subdomain of the install's host — infrastructure that
belongs to the operator, not to Klytos, and which may legitimately serve plain
HTTP. Opting somebody else into a year-long directive they cannot easily undo
is not a default this project takes.

Operators who want more widen it themselves:

```php
klytos_add_filter( 'security.hsts', function ( string $value ): string {
    return 'max-age=63072000; includeSubDomains; preload';
} );
```

Returning `''` from that filter suppresses the header entirely.

### Content-Security-Policy — it fails CLOSED

```
default-src 'self';
style-src   'self' 'unsafe-inline' fonts.googleapis.com;
font-src    'self' fonts.gstatic.com;
img-src     'self' data:;
script-src  'self' 'nonce-<per-request>';
frame-src   'self' blob:
```

**A missing nonce no longer relaxes the policy.** Before slice 8, calling
`sendSecurityHeaders()` without one produced `script-src 'self' 'unsafe-inline'`
— the weakest policy in the product, applied silently. It now produces
`script-src 'self'`. The failure mode became a visibly broken widget instead of
a quietly disabled defence.

**One nonce per request.** `admin/bootstrap.php` generates it into
`$GLOBALS['klytos_csp_nonce']`; `templates/header.php`, `login.php`,
`reset-password.php` and `setup-wizard.php` all **reuse** it. Minting a second
one would put a value in the markup that the sent header does not name, and
every inline script on the page would be refused —
`testTheCspNonceMatchesTheNonceInTheMarkup` exists to catch exactly that.

Writing an inline script in the admin:

```php
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
    document.getElementById( 'thing' ).addEventListener( 'click', handler );
</script>
```

Inline `onclick=` / `onchange=` attributes are refused by a nonce-based CSP.
Use `addEventListener` inside a nonced block — which is the project's standing
rule anyway.

### `style-src` still allows `'unsafe-inline'`, on purpose

Audit **S-10** is open and deliberately out of scope: there are **349 inline
`style=` attributes across 40 files**, and an HTML attribute cannot carry a
nonce, so closing it means converting all 349 to classes.

There is a trap here worth stating plainly, because the obvious tidy-up breaks
the admin everywhere at once:

> Per CSP Level 3, adding a **nonce source** to `style-src` makes browsers
> **ignore** `'unsafe-inline'`.

So nonce-ing styles the way scripts are nonced would refuse all 349 attributes.
The **10** `<style>` elements in `installer/admin/` now all carry nonce
**attributes** that have no effect yet and are ready for the slice that removes
`'unsafe-inline'`. (The audit's S-10 entry says 12; it counted two occurrences
embedded in `srcdoc` iframe attributes, which cannot carry a nonce.)
`testStyleSrcStillAllowsInlineWhileS10IsOpen` asserts the weakness on purpose,
so its removal is a deliberate act by the slice that also converts the
attributes.

## The public site is different, and it is stated explicitly

`installer/index.php` passes an **explicit** policy that keeps `script-src
'unsafe-inline'`. This is not an oversight:

`Router::dispatch()` `readfile()`s **pre-generated static HTML**
(`router.php:303-326`), and the build engine writes inline `<script>` into it —
the GDPR consent banner's `ConsentManager.init(...)` (`build-engine.php:881`)
and a page's `custom_js` (`build-engine.php:444`). **A file generated once at
build time cannot carry a per-request nonce.** Failing closed there would
silently disable the consent banner on every generated page.

It is written out as a literal at the call site so the weakening shows up in a
diff — which is exactly what the implicit fallback did not do. Tracked as
**NEW-23**, bound to the theme-package sprint (D-023).

## API

### `Auth::sendSecurityHeaders( ?string $nonce = null, ?string $customCsp = null ): void`

Emits the headers. Safe to call again later: `header()` replaces a same-name
header, so the last call wins — which is how a page upgrades the bootstrap's
baseline policy to its own.

### `Auth::buildSecurityHeaders( ?string $nonce = null, ?string $customCsp = null ): array`

Returns the same headers as `name => value` **without sending them**.

This split exists for a concrete reason: under the **CLI SAPI `header()` is a
no-op and `headers_list()` returns an empty array**, so a unit test driving the
emitting function could observe nothing at all — and its "the header is absent"
assertions would pass against any code whatsoever. That happened while writing
this slice, and it is the L-010 failure mode (a check that cannot fail). The
unit tier now asserts the **policy** this returns; the integration tier asserts
it **reaches the wire** on a real response.

```php
$headers = \Klytos\Core\Auth::buildSecurityHeaders( 'abc123' );
// $headers['Content-Security-Policy'] contains "script-src 'self' 'nonce-abc123'"
```

### `Helpers::isHttps(): bool`

The single answer to "is this request over TLS" in the product. Four copies of
the expression existed before slice 8 (two in `Helpers`, two in `Auth`'s cookie
`secure` flags); a fifth was about to be written for HSTS, so they were
collapsed into this one.

It deliberately **does not consult `X-Forwarded-Proto`**: that header is
caller-supplied unless a trusted proxy is configured, and trusting it would let
a client talk the application into believing a cleartext request was secure —
wrong in the dangerous direction for both HSTS and the session cookie's `secure`
flag. Trusted-proxy support is **NEW-17**'s subject and changes MCP and OAuth
too.

## Extension points

| Hook | Kind | Can it weaken a control? |
|---|---|---|
| `security.hsts` | filter | **Yes, and more than "weaken" suggests.** Returning `''` or a non-string suppresses the header; returning `max-age=0` tells browsers to **forget** a previously cached HSTS policy for the domain — a rollback, not merely a downgrade. Same plugin-trust boundary as `admin.gate_map` (D-032) and `http.safe.allowed_schemes` (D-041): plugins already run as first-party code here. It cannot open a hole by *omission*, since the default is the strict value. |

**Nothing else is filterable, and that is deliberate.** The CSP string,
`Permissions-Policy`, `X-Frame-Options`, `Referrer-Policy`,
`X-Content-Type-Options` and `X-XSS-Protection` pass through no filter, and
there is no action around header computation. A filterable CSP would let any
plugin disable the policy for the whole admin with one listener — and unlike
`admin.gate_map`, where removing an entry *denies*, a CSP filter fails in the
permissive direction by construction. The cost is real and is named here rather
than hidden: a plugin that needs its own admin screen to load an external image
or script currently has no supported way to widen the policy short of patching
core. If that need becomes concrete, the right shape is a **narrow additive**
hook (append a source to a named directive) rather than a filter over the whole
string.

## Verifying it yourself

```bash
# Every admin API endpoint (0 of 23 sent anything before slice 8)
curl -s -D - -o /dev/null -b "klytos_session=$SID" \
  http://127.0.0.1:8080/installer/admin/api/notices.php | grep -iE '^X-|^Content-Security|^Referrer'

# The refusals carry them too — this is the ordering proof
curl -s -D - -o /dev/null http://127.0.0.1:8080/installer/admin/api/plugins.php   # 401 + headers
curl -s -D - -o /dev/null -b "klytos_session=$VIEWER" \
  http://127.0.0.1:8080/installer/admin/users.php                                  # 403 + headers

# HSTS is correctly ABSENT over cleartext
curl -s -D - -o /dev/null http://127.0.0.1:8080/installer/admin/login.php | grep -ci strict-transport
```

Automated cover: `tests/Unit/SecurityHeadersTest.php` (the policy) and
`tests/Integration/SecurityHeadersHttpTest.php` (real responses).
