# Flow — Public comment submission

> Created in Sprint 1 slice 9, documenting what slice 7 built (**D-043**, closes audit **S-09**).

## Actors
An anonymous visitor on a generated static site, POSTing to `/comment-submit.php` at the **web
root** — deliberately outside the admin tree, so no admin-directory name is ever published.

## Happy path
1. Operator enables comments via `klytos_set_comment_settings` (which needed `SiteConfig::setValue()`
   — a method that **did not exist** until slice 7, **NEW-16**).
2. `BuildEngine::syncCommentEndpoint()` copies `installer/public/comment-submit.php` to the web root.
3. Visitor POSTs `author_name`, `author_email`, `content`, `page_slug`.
4. A **non-filterable flood ceiling** (10/60s) runs **before** `App::boot()`, needing only a
   directory path.
5. `App::boot()` runs; the **filterable** policy limit (2/60s) derives its count from
   `getRemainingRequests()`, so it costs no second write.
6. Honeypot checked **after** the limiter.
7. Input bounds applied, comment stored unapproved → **201**.

## Failure / recovery branches

| Branch | Response |
|---|---|
| Comments disabled | refusal; the feature cannot be reached |
| Honeypot field filled (a bot) | **201** with a syntactically valid **decoy id** and no stored record — deliberately indistinguishable from success |
| Over the policy limit | 429 with `Retry-After`, `comment.rate_limited` action fired |
| Over the flood ceiling | 429 **before** boot, so a flood cannot make anyone pay `PluginLoader::loadAll()` |
| Oversized field | truncated *before* `sanitizeSlug()` runs |
| `parent_id` not this collection's 32-hex ID shape | dropped, never stored verbatim |
| Wrong method | 405 with `Allow` — English literal by necessity, it fires before I18n exists (**NEW-18**) |

## The ordering is the design, not an implementation detail
The security review found both controls in the wrong order after the suite was already green: the
rate limit ran **after** `App::boot()` (so an anonymous caller paid the full boot cost per request at
a fixed, scannable URL), and the honeypot ran **before** the rate limit (so a flood setting
`_honeypot` on every request took the cheap success branch and **was never counted at all**).

## What this flow still does NOT do
**No comment form exists anywhere in the generated output, and none ever did.** No template, part or
build step emits one, and `renderCommentsHtml()` returns early on zero approved comments. Form
emission is bound to the theme-package sprint (**D-023**). Stated plainly rather than implied away —
closing a finding is not the same as making a feature work (**L-014**).

Also open: **NEW-17** (behind a non-loopback proxy every visitor shares one rate-limit bucket) and
**NEW-24** (this endpoint sends **no** security headers at all).

## Related
`docs/reference/public-comments.md` · D-043 · L-014
