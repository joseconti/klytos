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
| **OAuth access token** | Refused with **HTTP 401** on the **next request** — the resolver reads `status`, so the token stops being accepted at authentication. It is **not revoked**: the record stays, and reactivating the account makes the same token work again. Audit **NEW-41**, closed in Sprint 6 slice 2 (**D-060**) |

**The owner cannot be suspended.** `UserManager::update()` refuses it, mirroring `delete()`'s
protection. Without that guard an owner could suspend themselves into an install that `owner:repair`
also refuses — it refuses whenever an owner record exists — leaving no supported way back in.

```php
klytos_app()->getUserManager()->update( $viewerId, [ 'status' => 'suspended' ] );  // fine
klytos_app()->getUserManager()->update( $ownerId,  [ 'status' => 'suspended' ] );  // RuntimeException
```

## CSRF on the anonymous forms

**All three** forms a person can reach without logging in verify a CSRF token (audit **NEW-47**,
**NEW-26** and **NEW-51**, closed by **D-061**):

| Form | Where | Finding |
|---|---|---|
| The admin login form | `admin/login.php` | NEW-47 |
| The password-reset form | `admin/reset-password.php` | NEW-26 |
| **The OAuth consent screen's own login form** | `core/mcp/oauth-authorize-view.php` | NEW-51 |

The third one is easy to miss and was: `Auth::login()` has exactly **two** call sites in the product,
and the consent screen is the other one — so closing NEW-47 on the admin form alone would have left
the same forced-login attack available through a different URL while the audit entry said CLOSED.
Both review passes of the slice found it independently.

The token is the ordinary one: `klytos_csrf_field()` renders it, `klytos_verify_csrf()` checks it,
and it lives in the session `admin/bootstrap.php` (or, for the consent screen, its own
`startSession()`) begins for every request, so the GET that shows a visitor the form is what mints
their token.

**Why the login form needs it at all**, since the usual objection is that there is no session to
protect: the attack runs the other way. An attacker holding **their own** account on the install can
make a victim's browser POST the attacker's credentials; the victim carries on working inside an
account the attacker controls, and everything they write or paste there is the attacker's to read.
`SameSite=Strict` does not help — the victim has no session cookie to withhold, because the request
that creates one *is* the attack.

A refusal answers **HTTP 403** and re-renders the form with `auth.session_expired`:

> Your session expired before the form was sent. Reload the page and try again.

The wording is deliberate. The realistic trigger for a legitimate user is a page left open too long;
telling them their password was wrong would send them to change a password that works.

**Ordering, because it is load-bearing:** the CSRF check runs **before** the IP ceiling, so a forged
or token-less request never touches the shared per-address counter and never pays a bcrypt verify. A
credential-stuffing bot is unaffected — it holds its own session and token, passes the check, and is
counted where the ceiling is meant to bite.

**The consent screen had never rendered at all (audit NEW-52), and that is worth knowing before
anyone reads the paragraph above as routine.** `Router::handleOAuthAuthorize()` called
`handleOAuthAuthorizeView()` unqualified from namespace `Klytos\Core`, while the function is declared
in `Klytos\Core\MCP`. PHP falls back to the **global** namespace, never to a sibling, so every request
to `/oauth/authorize` died with *"Call to undefined function
Klytos\Core\handleOAuthAuthorizeView()"* — the OAuth authorization-code flow could not be completed
by any client, ever. Found by requesting the URL while proving NEW-51's fix, and fixed in the same
slice; **NEW-38** (this screen cannot complete a 2FA login) was written from source and describes a
page that, at the time, could not render either.

**The empty-token trap (audit NEW-50), stated here because it is the kind of thing that gets
"simplified" back:** `hash_equals( '', '' )` returns **true**. Until D-061, `Auth::validateCsrf()`
compared a missing token against a session that held none and accepted it — so adding the check to
the login branch changed nothing at all until the primitive itself refused empty values. Both sides
are now required to be non-empty, in `validateCsrf()` rather than at any call site.

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
supported way out for an operator who has locked themselves out and does not want to wait. An empty
map is stored as `{}` rather than the file being removed, so nothing can `unlink` a file another
process holds a lock on; removing it by hand still works exactly as described.

### The counter is atomic, and a lock it cannot take refuses

Since D-059 the whole read-modify-write of that file runs inside one exclusive lock, through
[`Klytos\Core\FileLock`](file-lock.md). Before that, two concurrent failures against the same
account both read the same pre-increment count and one increment was lost — and every lost increment
is one free attempt for a parallelized brute force. The identical defect in `MCP\RateLimiter`
(audit NEW-20) was measured at **20 simultaneous calls recording 2–4 of themselves**.

If the lock cannot be taken within its deadline the failure is **not** recorded, and `Auth::login()`
answers `account_locked:` rather than `login_failed`. That is deliberate: an uncounted attempt is
exactly the amplification the lockout exists to prevent, so "we could not count it" is never read as
"let it through". A corrupt counter file is treated differently — it starts a fresh map and logs,
because refusing everyone over one damaged file would be a worse failure than the race.

The lock deliberately does **not** span `Auth::login()` as a whole: `UserManager::authenticate()`
runs a bcrypt verify on every branch (the NEW-39 equalization), so a lock held across it would
serialise every login attempt on the install behind that verify.

### The endpoint has an IP ceiling

The per-account lockout bounds attempts against **one** account. Nothing bounded the login endpoint
itself, so a burst of invented usernames was limited only by the pruning window — and each request
paid a bcrypt verify, making the form a CPU amplifier as well as a credential-stuffing surface
(audit NEW-40, second half).

`admin/login.php` now refuses with **HTTP 429** and `Retry-After: 60` once an address has produced
too many failures, reusing the shipped `MCP\RateLimiter` auth-failure tracking — the same policy
(10 failures per 60 s, IP-keyed) that `core/mcp/server.php` already enforces on the MCP endpoint.
No constant was changed, which is what makes this reuse rather than a second limiter. Only failed
attempts count, so a user logging in repeatedly never approaches the ceiling.

**Known limit, stated rather than implied:** `RateLimiter::getClientIp()` trusts `X-Forwarded-For`
only from loopback, so behind a non-loopback reverse proxy every visitor collapses into one bucket
and the ceiling becomes a site-wide throttle. That is audit **NEW-17**, pre-existing, and this
change makes it reachable on a second surface. The remedy is trusted-proxy configuration, which
changes MCP and OAuth too — and, for an operator who cannot wait for that, the filter below.

**Second known limit, MEASURED rather than reasoned about (audit NEW-46).** The ceiling reads the
counter, authenticates, and only then records the failure — and the authentication in the middle is
a ~218 ms bcrypt verify. Concurrent requests can therefore all observe "under the limit" before any
of them has recorded its own failure. Measured with the bucket pre-filled to 9 of 10, one process
per request, each doing the real `isAuthBlocked()` → 218 ms → `recordAuthFailure()` sequence:

| Requests fired | Served (expected 1) | Bucket after |
|---|---|---|
| 6, one at a time | **1** | 10 |
| 6, simultaneous | **6** | 15 |
| 12, simultaneous | **12** | 21 |

So the overshoot equals the server's request concurrency, once, and the ceiling then holds: every
failure **is** counted (the bucket lands on exactly 9 + N, with no lost updates — that is what
D-059's atomicity bought), so the following requests are refused. Stated plainly rather than
softened: **the ceiling bounds a sustained brute force to ~10 attempts per minute plus one burst the
width of the server's worker pool; it does not make the limit exact.** Closing the remainder means
counting the attempt before authenticating rather than the failure after, which would also count
successful logins and is a policy change with its own decision.

### Extension point

`auth.login_ip_blocked` filters the ceiling's decision for one request:

```php
klytos_add_filter( 'auth.login_ip_blocked', function ( bool $blocked, string $ip ): bool {
    // The office behind one NAT address is not the threat this ceiling exists for.
    return str_starts_with( $ip, '203.0.113.' ) ? false : $blocked;
}, 10 );
```

It applies **only** to the login form's IP ceiling. It is deliberately not inside `MCP\RateLimiter`:
that class's constants are shared with `core/mcp/server.php`, and D-056's implementation note 3 and
D-059 both turn on their not moving, so filtering there would weaken the MCP surface in order to
loosen the login form. It also cannot reach the **per-account** lockout, which is a separate control
with its own counter and no filter — a plugin can widen the address ceiling, never the 5-attempts
bound on an individual account.

Like every other weakenable control here (`admin.gate_map` D-032, `http.safe.*` D-041,
`security.hsts` D-044), a plugin can switch it off. Plugins already run as first-party code in this
product; that trade is recorded rather than implied.

`auth.login_throttled` fires when the ceiling refuses. **Nothing in core subscribes to it** — it is
an audit seam, not a sink (L-019).

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
`login.php`'s dispatcher: `totp`, `passkey`, `recovery`, `email`, `emergency_email`.

### Passkeys (WebAuthn)

A passkey is a **second factor**, not a password replacement: the password stage runs first, and the
assertion completes it.

| Action on `admin/api/webauthn-challenge.php` | Who may call it |
|---|---|
| `register_challenge`, `register_complete` | **Fully authenticated callers only** |
| `auth_challenge` | A fully authenticated caller **or** one whose 2FA is pending |

**That split is the whole security of this endpoint.** `is2faPending()` becomes true after a correct
**password alone**, and `completePasskeyRegistration()` enrols a credential and sets `enabled = true`
without checking any existing factor. Had registration stayed reachable in the pending state — as it
was before Sprint 5 — a stolen password alone would have let an attacker enrol their own
authenticator and hold the account permanently. Sprint 1 slice 4 shipped exactly that exemption and
reverted it the same day (**D-036**); it is safe now only because the restriction above landed first.
`PasskeyLoginTest::testRegistrationIsRefusedWhileTwoFactorIsMerelyPending` exists so that stays true.

The account holder is emailed whenever an authenticator is enrolled, and the
**`user.passkey_enrolled`** action fires with `( $userId, $credentialId, $label )`. Neither can fail
the enrolment — the credential is already stored by then. Nothing in core subscribes to that action;
it is a seam, and saying otherwise would be the L-019 defect.

```php
klytos_add_action( 'user.passkey_enrolled', function ( $userId, $credentialId, $label ) {
    error_log( "passkey '{$label}' enrolled for {$userId}" );
}, 10 );
```

The Relying Party ID comes from `Helpers::webauthnRpId()` — the host without its port — and both
registration and verification derive it from that one function. A credential is bound to the rpId it
was registered under, so a divergence of one character silently invalidates every stored passkey and
looks like a broken authenticator rather than a broken string.

#### What the assertion path checks

`TwoFactor::verifyPasskeyAssertion()` verifies, in this order: the stored challenge exists, the
ceremony type is `webauthn.get`, the challenge matches, **the origin is acceptable**, **the
authenticator data is long enough**, the rpId hash matches, the user-presence flag is set, the
credential is one of this user's, the ES256 signature verifies, and **the signature counter does not
indicate a clone**. Any failure returns `false` and the login is refused.

The three emphasised checks landed in Sprint 6 slice 3 (audit **NEW-42**, **D-063**). Before that the
assertion path was the weaker sibling of the registration path it is supposed to mirror — and the
asymmetry ran the wrong way, since registration happens once per authenticator and assertion happens
at every login.

**Origin.** Registration and assertion now apply the *same* rule, `originIsAcceptable()`, rather than
two copies of it: the origin must be `https://{rpId}`, `https://localhost`, or `https://localhost:`
plus a port. Stated plainly so nobody has to wonder whether this can lock out an existing user: it
cannot. Every stored credential was enrolled through the identical check, so an origin that passes
enrolment passes assertion by construction — and one that would fail assertion could never have
produced a credential in the first place.

**Length.** A 32-byte `authenticatorData` is refused before any offset is read. This already failed
closed, but it did so via `ord($authData[32])` reading past the end of the string, which raises a PHP
warning — and the input is trivially precomputable, because authenticator data begins with
`sha256(rpId)` and the rpId is public. The regression test therefore asserts the **absence of the
warning**, which is the part a user or an operator actually meets.

**Clone detection.** WebAuthn's signature counter exists to reveal that two authenticators are
answering for one credential. The stored counter is compared with the presented one, and the
assertion is refused when the presented value does not exceed the stored value — **but only when both
values are non-zero**.

That condition is the design, not a caveat:

> Synced platform passkeys — iCloud Keychain, Google Password Manager, and every authenticator that
> exists in more than one place by design — report `signCount = 0` permanently. The obvious spelling
> of this check, "the new count must be greater than the stored one", would refuse the **second login
> of most authenticators in use today**. That is a security fix that breaks authentication, and it is
> the failure mode this project recorded in D-044.

`PasskeyLoginTest::testASyncedPasskeyReportingZeroForeverStillCompletesLogin` is the test that holds
that line, and it fails against the naive rule while the clone tests still pass.

##### Where this deliberately differs from the WebAuthn specification

Klytos requires **both** counters to be non-zero. The specification requires **either**. Verbatim,
from §7.2 *Verifying an Authentication Assertion*:

> If `authData.signCount` is nonzero **or** `storedSignCount` is nonzero, then run the following
> sub-step […] **less than or equal to** `storedSignCount`: This is a signal that the authenticator
> may be cloned […]

The two rules agree in every case but one — **stored non-zero, presented zero** — and the
consequences run in opposite directions:

| Case | Spec (OR) | Klytos (AND) |
|---|---|---|
| stored 0, presented 0 (synced passkey) | no check | no check |
| stored 0, presented 5 | accepted, counter stored | accepted, counter stored |
| stored 5, presented 6 | accepted, counter stored | accepted, counter stored |
| stored 5, presented 4 | cloning signal | refused |
| **stored 5, presented 0** | **cloning signal** | **accepted** |

So, said plainly rather than left for a reader to work out: **an attacker holding a cloned credential
can skip this check by presenting a counter of zero.** The reason Klytos accepts that today is the
mirror-image cost of the spec's rule — an authenticator that legitimately *stops* incrementing (a
firmware reset, or a credential migrated from a hardware key into a synced store) would be refused
from then on, which is D-044's trap in a narrower population.

The specification leaves the **response** to the Relying Party — *"Whether the Relying Party updates
`storedSignCount` in this case, or not, or fails the authentication ceremony or not, is Relying
Party-specific"* — but it does **not** leave the **condition** to the Relying Party. This is therefore
a deliberate divergence, not conformance. **It was put to the project owner with the trade-off and
settled on 2026-07-27: the permissive rule stays** (D-063 note 2). The reasoning is worth keeping
because it is easy to reach the opposite conclusion from the table alone — **clone detection does not
stop a determined attacker under either rule**, because whoever holds the cloned credential also
chooses the counter it presents and can simply present *stored + 1*. OR therefore catches only a
*careless* clone, while its cost lands on real users whose authenticator legitimately resets. The
specification agrees with that framing: the counter is input to **risk scoring**, not a gate —
*"Relying Parties should incorporate this information into their risk scoring"*.

When a clone is detected the **`user.passkey_clone_detected`** action fires with
`( $userId, $credentialId, $storedCount, $presentedCount )`. **Nothing in core subscribes to it** —
it is a seam, not a sink, and saying otherwise would be the L-019 defect. The login is refused with
or without a listener; a deployment that wants to be told registers one:

```php
klytos_add_action( 'user.passkey_clone_detected', function ( $userId, $credentialId, $stored, $presented ) {
    error_log( "passkey clone suspected for {$userId}: stored {$stored}, presented {$presented}" );
}, 10 );
```

What clone detection does **not** do, said rather than implied: it does not disable the credential, it
does not notify the account holder, and it does not lock the account. It refuses that one assertion
and announces it. A counter regression is also not proof of cloning — a restored authenticator backup
produces the same signal — which is why the response is a refusal plus a hook rather than an automatic
revocation.

#### The setup-wizard skip-list

`admin/bootstrap.php` carries two lists of surfaces that bypass a redirect: the pre-auth list (which
skips the authentication guard) and the setup-wizard list (which skips the first-login redirect).
Both are keyed on `klytos_admin_gate_key()` — the path relative to `admin/`, resolved from
`SCRIPT_FILENAME`. The wizard list moved onto that key in slice 3; it had been matching a **basename**,
which cannot express an `api/` path at all, so `api/webauthn-challenge.php` could not be listed and an
install whose setup was still incomplete answered the passkey ceremony with a 302 into the wizard
where its caller parses JSON. Six filenames exist in both `admin/` and `admin/api/`, which is why a
basename key is wrong in principle and not only in this instance (**D-032**, **D-058**).

## Known limits

- **Passkey enrolment does not require an existing second factor.** A fully authenticated session can
  add an authenticator without re-entering a password or passing 2FA, so a hijacked session can enrol
  one. The account holder is notified by email, which is the compensating control rather than a
  substitute for step-up authentication (see **NEW-13**, out of scope per D-057).
- **Passkey login needs HTTPS in practice.** Browsers only expose WebAuthn on a secure context, and
  **both** ceremonies expect an `https://` origin, allowing `localhost` for development — one rule,
  `originIsAcceptable()`, applied by registration and assertion alike since D-063. The automated tests
  drive the protocol directly, so they prove the server's verification without a browser — they do not
  prove any particular browser's UI.
- **The origin allowance is `https://localhost[:port]`, not `http://`.** A browser on plain
  `http://localhost:8080` sends `http://localhost:8080` and is refused. This is pre-existing behaviour
  of the registration path that the assertion path now mirrors rather than something slice 3
  introduced, and it is symmetric — a credential that cannot be enrolled cannot be asserted — so no
  working flow is affected. Narrowing or widening the development allowance is a decision of its own.
- **A counter regression is not proof of a clone**, and the product does not treat it as one: a
  restored authenticator backup produces the same signal. The assertion is refused and
  `user.passkey_clone_detected` fires; the credential is not revoked and the account is not locked.
- **The OAuth consent screen cannot complete a 2FA login** (audit **NEW-38**).
  `core/mcp/oauth-authorize-view.php` has no second-factor branch at all: on the 2FA path `login()`
  returns `success => true`, so its only check (`! $result['success']`) is not taken, and the screen
  selector — which asks `isAuthenticated()`, false while pending — silently re-renders the login form
  with no error. A 2FA-enabled account loops. Pre-existing; recorded with a trigger, not fixed here.
- ~~**The password-reset form has no CSRF field or check**~~ (audit **NEW-26**) and ~~**the
  password-login POST has none either**~~ (audit **NEW-47**) — **both CLOSED by D-061 (Sprint 6,
  slice 4)** and described in "CSRF on the anonymous forms" above rather than here.
- **No step-up authentication before privileged actions**, except the encryption-level change in
  `admin/security.php`, which re-authenticates against this same authority. Recorded as **NEW-13**
  for the identity-key export; out of scope per D-057.
- **A suspended user's live session survives up to 60 seconds**, the throttle on the record re-read.
  Deliberate: the alternative is a storage read on every request.
- ~~**The lockout's read-modify-write is not atomic** (NEW-40)~~ and ~~**nothing throttles the login
  endpoint**~~ — **both CLOSED by D-059 (Sprint 6, slice 1)**, and described above rather than here.
  The counter's whole read-modify-write now runs inside one `LOCK_EX` and the endpoint has an IP
  ceiling. **The remedy this section used to recommend was wrong and is not restored:** it named
  `ActionScheduler::acquireLock()` as the fix shape, and that method takes `LOCK_EX | LOCK_NB` —
  *skip if busy* — so under the parallel burst the fix exists to stop, every contender would fail to
  acquire and skip its own increment. That is a **deterministic** lost update, strictly worse than
  the racy one. Recorded in D-059 so it is not re-derived a third time.
- **The ceiling is not exact under concurrency** (audit **NEW-46**) — measured, with the numbers, in
  "The endpoint has an IP ceiling" above. The residual is one burst the width of the server's worker
  pool; the sustained rate is bounded.

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
| A suspended user's OAuth token is refused with 401 on the next request, and works again once the account is reactivated | `tests/Integration/OAuthSuspensionHttpTest.php` |
| A login POST with no CSRF token is refused, and the browser it hands back is not logged in | `tests/Integration/LoginCsrfHttpTest.php` |
| A CSRF token minted for another session is refused | `LoginCsrfHttpTest` |
| A synced passkey reporting `signCount = 0` forever still completes login | `tests/Integration/PasskeyLoginTest.php` |
| A counter regression is refused when both counters are non-zero, and a repeated counter too | `PasskeyLoginTest` |
| `user.passkey_clone_detected` fires carrying both counters | `PasskeyLoginTest` |
| An assertion carrying another origin is refused, and the localhost allowance still holds | `PasskeyLoginTest` |
| A 32-byte `authenticatorData` is refused with no PHP warning | `PasskeyLoginTest` |
| The WebAuthn endpoint answers JSON during an incomplete setup instead of redirecting | `PasskeyLoginTest` |
| An empty token is refused even when the session holds none either (NEW-50) | `LoginCsrfHttpTest` |
| The form still logs in when submitted the way the shipped page submits it | `LoginCsrfHttpTest` |
| The password reset is refused without its token, and completes with it — the new password then logs in | `LoginCsrfHttpTest` |
| Recovery restores access through the real gate | `tests/Integration/OwnerRepairTest.php` |
| A real passkey completes a second-factor login end to end | `tests/Integration/PasskeyLoginTest.php` |
| Passkey **registration** is refused while 2FA is merely pending | `PasskeyLoginTest` (the D-036 takeover proof) |
| The `auth_challenge` action stays reachable while 2FA is pending | `PasskeyLoginTest` |
| A tampered assertion is refused | `PasskeyLoginTest` |

---
D-021 · D-055 · **D-056** · D-057 · **D-060** · **D-061** · NEW-09 · NEW-11 (closed) · NEW-13 ·
NEW-26 (closed) · NEW-37 (closed) · NEW-38 · NEW-41 (closed) · NEW-47 (closed) · NEW-50 (closed) ·
L-024 · L-026
