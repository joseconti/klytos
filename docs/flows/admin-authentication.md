# Flow — Admin authentication

> Created in Sprint 1 slice 9. **Sprint 5 closed NEW-11 and NEW-37 on this flow** (D-056: the user
> record is the sole login authority). Still open here, each with its trigger: **NEW-09** (passkey
> second factor — Sprint 5 slice 2), **NEW-13** and **NEW-26** (both explicitly out of Sprint 5's
> scope, D-057), and **NEW-38** (the OAuth consent screen cannot complete a 2FA login).

## Actors
A human with a browser, reaching `/<admin-dir>/admin/login.php`. The admin directory name is
randomized at install and `Helpers::getBasePath()` states it must NEVER appear in a public URL.

## Happy path
1. GET `login.php` → **200**, form rendered with a CSP nonce on its inline 2FA switcher (slice 8).
2. POST credentials → `Auth::login()` validates.
3. If 2FA is enabled, `is2faPending()` becomes true and the second factor is requested.
4. Session established as `klytos_session`, cookie scoped `path=<base>/admin/`, `SameSite=Strict`.
5. Every subsequent admin request passes `admin/bootstrap.php`, which sends security headers and
   enforces the gate map (slices 4 and 8).

## Failure / recovery branches

| Branch | Behaviour |
|---|---|
| Wrong password | re-render with an error; no session |
| Unauthenticated request to any admin surface | **302** to login for pages, **401 JSON** for `api`/`mcp` (slice 4) |
| Authenticated but lacking the capability | **403** via `klytos_deny()`, in the shape the caller can parse |
| Surface not in the gate map | **denied** — default-deny is the point (D-032) |
| No owner record exists (failed v1.x migration) | boot survives and **fails closed** (D-031). Recovery is `php installer/cli.php owner:repair --email=<address>`, which supplies the `admin_email` the migration lacked and rebuilds the record from `config['admin_user']`/`config['admin_pass_hash']` — the existing password still applies (**NEW-08** closed, D-055; see `docs/reference/owner-recovery.md`) |
| Password reset | `reset-password.php` — **no CSRF field or check** (**NEW-26**) |

## The branch that WAS broken, and what replaced it
For the project's first four sprints, **only `config['admin_user']` could log in**: `Auth::login()`
validated against the config value and never called the fully-implemented
`UserManager::authenticate()`, so `admin`, `editor` and `viewer` could not enter the admin panel at
all (**NEW-11**, verified live against the seeded playground). It is very likely *why* S-07 survived
unnoticed: with one usable account, ungated surfaces never misbehave.

**Sprint 5 (D-056) made the user record the sole login authority.** All four roles log in; the
config credential survives only as the seed `migrateFromV1Config()` and `owner:repair` rebuild a
missing owner record from. The same change closed **NEW-37** — every password-change surface writes
the record, so before it a rotated password was refused and the old one kept working. Details and
the failure modes: `docs/reference/authentication.md`.

**Passkey second-factor login cannot complete either** (**NEW-09**):
`TwoFactor::verifyPasskeyAssertion()` has zero call sites and `login.php`'s 2FA dispatcher has no
`passkey` branch. **Its obvious one-line fix is FORBIDDEN — read D-036 first:** exempting
`api/webauthn-challenge.php` from the auth guard opens a full account-takeover primitive, because
`is2faPending()` is true after a correct password alone and
`TwoFactor::completePasskeyRegistration()` enrols a new authenticator without checking any existing
factor.

## Related
`docs/reference/authorization.md` · `docs/reference/security-headers.md` · D-031 · D-036 · D-040
