# Authentication — who the caller is, and what proves it

> Created Sprint 5 slice 1 (2026-07-25), closing audit **NEW-11** and **NEW-37**.
> Decision: **D-056** (the user record is the sole login authority), **D-057** (this sprint's scope).
> Related: `docs/reference/authorization.md` (what a caller may DO once identified),
> `docs/reference/mcp-authorization.md` (identity on the MCP path),
> `docs/reference/owner-recovery.md` (getting back in when the owner record is gone).

Authentication answers *who is this caller*. Authorization — a separate system, separately
documented — answers *what may they do*. Klytos has three ways to prove identity: the **admin login
form** (a session), an **application password** (HTTP Basic, MCP), and a **bearer token** or **OAuth
token** (MCP). This document covers the first two; the token paths are in
`docs/reference/mcp-authorization.md`.

## The one authority

`Auth::login( $username, $password )` (`installer/core/auth.php`) is the gate. It delegates
credential verification to **`UserManager::authenticate()`**, which:

- looks the username up in the `users` collection (exact match, case-sensitive);
- refuses any account whose `status` is not `active`;
- verifies the password against the record's bcrypt `pass_hash` (cost 12);
- stamps `last_login` and fires the `user.login` action.

**`config['admin_user']` and `config['admin_pass_hash']` are NOT consulted.** They survive as the
**seed** that `UserManager::migrateFromV1Config()` (at `App::boot()` Step 10b) and the
`owner:repair` CLI command rebuild a missing owner record *from*. Nothing reads them to grant access.

```php
$result = klytos_auth()->login( 'editor', 'the-editors-password' );

// [ 'success' => true, 'error' => '', 'requires_2fa' => false, 'user_id' => 'a1b2…' ]
if ( $result['success'] && ! $result['requires_2fa'] ) {
    // The session is granted; klytos_current_user() now resolves.
}
```

Two callers exist repo-wide: `installer/admin/login.php` (the form) and
`installer/core/mcp/oauth-authorize-view.php` (the OAuth consent screen — see *Known limits*).

### Why one authority and not two

Until Sprint 5, `Auth::login()` compared against the config credential and never called
`UserManager::authenticate()`. Two things followed, both recorded as audit findings:

- **NEW-11** — `admin`, `editor` and `viewer` could not log in at all, whatever their records said.
  Four sprints of authorization work were built on a login only one account could pass.
- **NEW-37** — every password-change surface (the profile page, the admin reset link, the MCP
  `klytos_reset_user_password` tool) writes the **record**. So a rotated password was refused and the
  old one kept working — on the only account that could log in. `reset-password.php` printed *"Your
  password has been reset successfully. You can now log in."*, which was false.

A record-first-with-config-fallback design was rejected for exactly this reason (D-056): the two
authorities had **already diverged**, so a fallback would have preserved the defect rather than a
working path.

## Account status

`status` is `active` or `suspended`. Suspension takes effect on both surfaces:

| Surface | Effect of `status = suspended` |
|---|---|
| Login form | Refused, with the same message as a wrong password |
| A live admin session | Ended at the next `isAuthenticated()` check — throttled to once per 60 s |
| Application password | Refused (`validateAppPassword()` requires an active record) |
| **OAuth access token** | **NOT revoked** — it keeps working, with its role, until it expires (1 hour). Audit **NEW-41**, recorded rather than implied away |

**The owner cannot be suspended.** `UserManager::update()` refuses it, mirroring `delete()`'s
protection. Without that guard an owner could suspend themselves into an install that `owner:repair`
also refuses — it refuses whenever an owner record exists — leaving no supported way back in.

```php
klytos_app()->getUserManager()->update( $viewerId, [ 'status' => 'suspended' ] );  // fine
klytos_app()->getUserManager()->update( $ownerId,  [ 'status' => 'suspended' ] );  // RuntimeException
```

## No account oracle

Every failure answers identically. A wrong password, a suspended account and a username that does
not exist all return `error => 'login_failed'`, which the form renders as *"Incorrect username or
password"*. Nothing in the response distinguishes them — **including how long it takes**.

That last part is not free and was not free here. `authenticate()` originally returned early for an
unknown username and for a non-active account, so only *"the account exists and is active"* reached
`password_verify()`. Measured on the seeded playground: **218.98 ms against 0.65 ms**, a 340×
difference readable from a single request. It is now equalized — every outcome performs one bcrypt
verify, against another record's hash when the submitted username resolves to nothing. An install
with **no users at all** skips the equalization, which is stated rather than hidden: there is no
account to enumerate in that state.

That property extends to the **lockout**, which is where it is easiest to lose by accident. After
**5** failed attempts an account is locked for **15** minutes (`account_locked:15`). The bucket is
keyed by `hash( 'sha256', $submittedUsername )` — the string as typed, **not** the resolved account:

- Keying it per *install* (the old `md5( config['admin_user'] )`) meant one global bucket, so five
  failures against any username would lock out every account including the owner.
- Keying it per *resolved account*, with a shared bucket for names that do not resolve, would leak
  existence through the **bucket** even with identical messages: an attacker fills the shared bucket
  with garbage, then reads "locked" as *no such user* and "wrong password" as *this user exists*.

State lives in `installer/data/login_lockouts.json`, one file for the whole install, with expired
entries pruned on every write. It is inside the install rather than `sys_get_temp_dir()` — that path
is predictable and world-writable, so on shared hosting a neighbour could forge a permanent lockout
or delete a real one.

**Operational note:** deleting `installer/data/login_lockouts.json` clears every lockout. That is the
supported way out for an operator who has locked themselves out and does not want to wait.

## Application passwords

`Auth::validateAppPassword( $username, $password )` authenticates HTTP Basic credentials on the MCP
path. The username must resolve to an **active user record**; the raw password is verified against
the stored bcrypt hash.

```php
// Mint one for any account (admin/mcp.php does this at the mcp.manage capability):
$created = klytos_auth()->createAppPassword( 'My MCP client', 'editor' );
echo $created['password'];   // shown once
```

The credential's **role follows the user record**: `TokenAuth::resolveUserActor()` reads it there, so
an editor's application password reaches the MCP authorization gate as an editor and is refused
anything an editor may not do. Nothing extra was needed to make that work — D-047 built the resolver
that way deliberately.

> **Behaviour change (Sprint 5).** Application passwords used to be pinned to `config['admin_user']`,
> so a credential minted for any other account was refused. Two consequences on upgrade: credentials
> the product itself minted for a non-`admin_user` account **start working**, and a credential whose
> user record no longer exists **stops** authenticating (it is refused at authentication now, rather
> than authenticating and then being denied every tool by a null actor). Both directions fail closed;
> the layer moved. This belongs in the release notes.

## Two-factor authentication

When the account has 2FA enabled, `login()` returns `success => true` **and** `requires_2fa => true`,
and sets a pending challenge (5-minute expiry). The caller must complete the second factor —
`login.php` renders the challenge and calls `Auth::complete2fa()` on success. A pending challenge is
**not** an authenticated session: `isAuthenticated()` is false, `is2faPending()` is true.

Enabled methods live on the user record, so per-user 2FA works for every role. Supported branches in
`login.php`'s dispatcher: `totp`, `recovery`, `email`, `emergency_email`.

## Known limits

- **Passkey second-factor login does not complete** (audit **NEW-09**). `login.php`'s dispatcher has
  no `passkey` branch and `TwoFactor::verifyPasskeyAssertion()` has zero call sites, although the
  page's own front end already posts the assertion. **Its obvious one-line fix is FORBIDDEN — read
  D-036 first**: exempting `api/webauthn-challenge.php` from the auth guard opens a full
  account-takeover path, because `is2faPending()` is true after a correct password alone and that
  endpoint's registration actions would then be reachable. Slice 2 of this sprint fixes it in the
  order that makes the exemption safe, and the exemption lands **last**.
- **The OAuth consent screen cannot complete a 2FA login** (audit **NEW-38**).
  `core/mcp/oauth-authorize-view.php` has no second-factor branch at all: on the 2FA path `login()`
  returns `success => true`, so its only check (`! $result['success']`) is not taken, and the screen
  selector — which asks `isAuthenticated()`, false while pending — silently re-renders the login form
  with no error. A 2FA-enabled account loops. Pre-existing; recorded with a trigger, not fixed here.
- **The password-reset form has no CSRF field or check** (audit **NEW-26**, out of scope per D-057).
- **No step-up authentication before privileged actions**, except the encryption-level change in
  `admin/security.php`, which re-authenticates against this same authority. Recorded as **NEW-13**
  for the identity-key export; out of scope per D-057.
- **A suspended user's live session survives up to 60 seconds**, the throttle on the record re-read.
  Deliberate: the alternative is a storage read on every request.
- **The lockout's read-modify-write is not atomic** (audit **NEW-40**). `LOCK_EX` covers the write,
  not the read that preceded it, so concurrent failed attempts against one account can lose an
  increment and allow a few more tries than the nominal five before the lockout engages. A torn read
  fails **open** (no lockouts) rather than closed. It bounds abuse, it does not bypass
  authentication. Same shape as **NEW-20** in `MCP\RateLimiter`; the fix shape is recorded — the
  `flock`-based critical section `ActionScheduler::acquireLock()` already uses — so it need not be
  re-derived.
- **Nothing throttles the login endpoint by IP or globally.** The lockout is per account, so a burst
  of invented usernames is bounded only by the 15-minute pruning window. Part of NEW-40.

## Tests

| Property | Test |
|---|---|
| All four roles log in through `Auth::login()` | `tests/Integration/AuthLoginTest.php` |
| All four roles log in through the real form, over HTTP | `tests/Integration/AuthLoginHttpTest.php` |
| A rotated password reaches the gate; the old one stops working | `AuthLoginTest` |
| A suspended account is refused, and its live session ends | `AuthLoginTest` |
| Locking one account does not lock another | `AuthLoginTest` |
| The lockout state is written inside the install | `AuthLoginTest` |
| The owner cannot be suspended; a non-owner can | `AuthLoginTest` |
| A non-owner application password carries its own role | `tests/Integration/McpActorResolutionTest.php` |
| A suspended user's application password is refused | `McpActorResolutionTest` |
| Recovery restores access through the real gate | `tests/Integration/OwnerRepairTest.php` |

---
D-021 · D-055 · **D-056** · D-057 · NEW-09 · NEW-11 (closed) · NEW-13 · NEW-26 · NEW-37 (closed) ·
NEW-38 · L-024
