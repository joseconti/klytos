# Public comments — the one anonymous write surface

> Reference for `installer/public/comment-submit.php`, the only endpoint in Klytos
> that accepts a write from a caller with no identity at all.
> Introduced in its current form by Sprint 1 slice 7 (audit **S-09**).

## Why this file lives at the web root

The handler used to be `installer/admin/api/comment-submit.php`. It could not work,
and it could not be *made* to work where it was.

Klytos renames its own directory at install time to a randomized `<hex>-admin`
(`install.php:811-824`), and `Helpers::getBasePath()` states plainly that this name
"must NEVER appear in public URLs" (`core/helpers.php:192-197`). The root `.htaccess`
carries the same rule in its header. A comment form on a generated page has to post
somewhere; posting to the old location would have printed the secret directory name
into every page of every site.

Exempting it from the admin auth guard — the remediation the audit originally
recorded — would also have handed every anonymous caller more than a comment box.
`admin/bootstrap.php` runs the cron manager and the action scheduler on every request
(`bootstrap.php:184-196`). An unauthenticated endpoint behind that bootstrap is a
scheduler trigger for anyone who finds it. `installer/index.php` does neither
(`index.php:62`), and neither does this file.

So the handler sits in `installer/public/` and the build engine copies it to the
site's **web root** (`BuildEngine::syncCommentEndpoint()`), which is the placement
x402 already uses for its own public gate (`core/x402-bootstrap.php:265-267`).

Public URL: **`/comment-submit.php`** — no admin segment, in any install.

When `comments_enabled` is off, the build **removes** the file from the web root
rather than leaving it behind. A disabled endpoint that still boots the application
on every anonymous POST is attack surface with no feature behind it.

## Request

```
POST /comment-submit.php
Content-Type: application/x-www-form-urlencoded
```

| Field | Required | Notes |
|---|---|---|
| `page_slug` | yes | Sanitized through `Helpers::sanitizeSlug()`; truncated to 200 chars first |
| `author_name` | yes | Truncated to 100 chars, then `Helpers::sanitizeText()` |
| `content` | yes | `strip_tags()`, truncated to 5000 chars |
| `author_email` | no | Truncated to 254 chars; **never stored raw** — only an md5 hash, for gravatar |
| `parent_id` | no | Must be 32 hex chars (this collection's own ID shape) or it is dropped |
| `_honeypot` | no | Must be empty. Anything in it means a bot |

There is **no CSRF token**, deliberately. The caller is anonymous by definition and
holds no session — the admin session cookie is scoped to `path=<base>/admin/` with
`SameSite=Strict` (`core/auth.php:52-62`), so a form on the static site cannot send
one and could not carry a token bound to one.

## Responses

| Status | When |
|---|---|
| `201` | Stored, `status: pending`. Body carries `success`, `message`, `id` |
| `201` | **Also** the honeypot response — deliberately indistinguishable from the row above: same status, same shape, a decoy `id`. Nothing is stored. A caller cannot tell the two apart, which is the point |
| `400` | A required field is missing or empty |
| `403` | `comments_enabled` is false |
| `405` | Not a POST (checked before the application is even located) |
| `429` | Rate limited. Carries `Retry-After: 60` |
| `500` | The installation could not be located or failed to boot |

A submitted comment is **always** `status: pending`. There is no field a caller can
send that changes this — `CommentManager::submit()` hardcodes it.

## Rate limiting — and why the old one was not a rate limit

The previous implementation read `$_SESSION['last_comment_at']` and compared it to
`time()`. That could never work from the generated site, for the cookie-scoping
reason above: every anonymous submission arrives with a **brand-new** session, so the
value was always absent. It was not a weak rate limit; it was none. Verified live —
each anonymous request received a fresh `klytos_session` cookie.

### Two stages, and the ordering is a security property

The limit runs in two parts, and which part runs where is deliberate:

1. **A flood ceiling — 10 per 60 seconds, before `App::boot()`.** `App::boot()` decrypts the config,
   builds ~25 managers and runs every active plugin's `init.php` (`app.php:530-537`). Every other
   check in the endpoint needs a booted App, so putting the limiter after boot would make an
   anonymous caller pay that cost on every request, at a **fixed URL present on every install**. The
   ceiling needs only a directory path (`rate-limiter.php:35-38`), so it costs one JSON read/write.
   It is **not filterable**: it runs before plugins are loaded, and a ceiling a plugin can raise is
   not a ceiling.
2. **The comment policy — 2 per 60 seconds, after boot, filterable** via `comment.rate_limit`. It
   derives its count from `getRemainingRequests()`, which only reads, so it records nothing extra.

The honeypot is checked **after** both, not before. When it ran first, a flood that simply set
`_honeypot` on every request took the cheap success branch and was never counted — the one control
meant to bound repeated abuse never engaged for the traffic most likely to trigger it.

It uses the product's one rate limiter, `Klytos\Core\MCP\RateLimiter`
(`core/mcp/rate-limiter.php`), already behind the MCP endpoint, the OAuth token
endpoint and the plugin route layer. The key is `comment:<client-ip>`; state lives in
`data/rate_limits.json` and therefore survives across sessions, processes and
restarts. The window is that class's fixed 60 seconds, so the policy is expressed as
a count within it — **2 per 60 seconds** by default — rather than as a bespoke
interval. Inventing a second limiter to express "one per 30 seconds" exactly would
have been the duplication this project treats as a defect.

### Stated rather than implied: what this rate limit does not cover

`RateLimiter::getClientIp()` (`rate-limiter.php:151-170`) trusts `X-Forwarded-For`
**only** when `REMOTE_ADDR` is loopback, and otherwise uses `REMOTE_ADDR` directly.
On a site behind a CDN or reverse proxy whose address is not loopback, every visitor
resolves to the **same** address, so one bucket is shared by the whole audience and
the limit becomes a site-wide throttle rather than a per-visitor one. That is
recorded as audit finding **NEW-17**; it is not fixed here, because the remedy is a
trusted-proxy configuration that changes `getClientIp()` for MCP and OAuth too.

Two further limits of the shared limiter, recorded rather than implied away: the set of tracked
identifiers grows monotonically between cleanup runs, and an attacker rotating source addresses gets
a bucket each (**NEW-19**); and `check()` is a read-decide-write with no lock spanning it, so
concurrent requests can exceed the window (**NEW-20** — carried as *plausible and unproven*, because
the concurrency test that would settle it has not been run).

So: this raises the cost of comment spam substantially and bounds anonymous writes. It does not make
a determined distributed spammer impossible, a proxied deployment needs the NEW-17 fix before the
limit means what it says, and under concurrency NEW-20 makes the window approximate.

## Extension points

| Name | Kind | Signature |
|---|---|---|
| `comment.rate_limit` | filter | `( int $max, string $clientIp )` — submissions allowed per 60s window |
| `comment.notification_recipient` | filter | `( string $email, array $comment )` — who is told about a new comment |
| `comment.honeypot_rejected` | action | `( array{page_slug: string, ip: string} )` |
| `comment.rate_limited` | action | `( string $clientIp )` |
| `comment.before_save` | action | `( array $comment )` — pre-existing, fired by `CommentManager` |
| `comment.after_save` | action | `( array $comment, string 'create' )` — pre-existing |

Both actions are actions and **not** filters, for the same reason `auth.access_denied`
and `http.safe.blocked` are: a listener able to turn a refusal into an acceptance
would put anti-spam policy in third-party hands.

```php
// Allow one comment per minute instead of two.
klytos_add_filter( 'comment.rate_limit', function ( int $max, string $ip ): int {
    return 1;
}, 10 );

// Log every bot the honeypot catches.
klytos_add_action( 'comment.honeypot_rejected', function ( array $context ): void {
    klytos_log_warning( 'Honeypot caught a bot', $context, 'comments' );
} );
```

## Enabling comments

```php
$app->getSiteConfig()->setValue( 'comments_enabled', true );
```

or the MCP tool `klytos_set_comment_settings`.

`SiteConfig::setValue()` **did not exist** before slice 7, although
`core/mcp/tools/comment-tools.php:136-148` had always called it four times. The tool
— the only supported way to switch comments on — therefore fataled with *Call to
undefined method* for its entire life. Its sibling `SiteConfig::set()` cannot be used
either: that method carries a hardcoded allow-list of top-level fields for the
settings form, and `comments_enabled` is not on it, so a value passed there is
dropped **silently**. Both halves are fixed; see D-043.

Note the consequence for the build: comments only appear on a generated site after a
rebuild, because `syncCommentEndpoint()` and the approved-comment injection
(`core/app.php:438-458`) both run at build time.

## What is deliberately NOT here

**There is still no comment FORM in the generated output.** No template, part or build
step emits one, and `CommentManager::renderCommentsHtml()` returns early when a page
has no approved comments (`comment-manager.php:257-259`), so a first commenter would
have no entry point even if a form existed. Slice 7 was scoped to the endpoint and
its route by explicit decision: form emission belongs to the theme-package sprint
(D-023), which is already replacing the frontend template layer that would carry it.

Until that lands, the endpoint is reachable and correct but nothing on a generated
page calls it. Saying otherwise would be the L-002 defect — a document asserting a
capability the code does not have.
