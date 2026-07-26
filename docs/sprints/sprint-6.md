# Sprint 6 — Hardening: the abuse-bounding controls actually bound abuse, and suspension means suspension

- **Planned:** 2026-07-26 (plan mode, approved by the user). Kickoff re-validation ran in the
  planning session; every claim below was re-derived from source in the session that wrote it, not
  carried from the audit (L-015).
- **Status:** **PLANNED — slice 1 next.**
- **Scope basis:** audit **NEW-40** (the login lockout's read-modify-write is not atomic and nothing
  throttles the endpoint) together with **NEW-20** (the same shape in `MCP\RateLimiter::check()`,
  carried since Sprint 1 slice 7 as *plausible and unproven*); audit **NEW-41** (a suspended user's
  OAuth token keeps working for up to an hour); audit **NEW-42** (four rough edges in the passkey
  assertion path Sprint 5 made reachable). Decisions: **D-059** (slice 1's shape and fail direction)
  and **D-060** (the layer NEW-41 refuses at).
- **Sprint start baseline, measured this session:** `OK (248 tests, 1152 assertions)`, 0 skips.

## Why this sprint exists

Sprint 5 gave four roles a working login and completed passkey second-factor login. Both of its
review passes then recorded, and deliberately did not fix, the three weaknesses that change had made
reachable. The user chose this sprint over the theme-package sprint (D-023/D-024) and the bilingual
in-product guides.

The through-line is one sentence: **this project ships several controls whose whole purpose is to
bound abuse, and none of them has ever been shown to hold under concurrency.** NEW-20 has been
carried for five sprints with its own entry saying the concurrency test that would settle it was
never run. This sprint runs it.

## Re-validation of assumptions (Phase 5 §0 kickoff, step 2)

Verified against source by reading the files, not against the recorded audit. **The audit's own
recorded fix shape for NEW-40 is wrong, and two defects surfaced that are in no audit entry.**

### 1. NEW-40's recorded remedy would not work, for two independent reasons

The entry says: *"the codebase already has the primitive — `ActionScheduler::acquireLock()` is a
flock-based critical section. Wrap read-through-write in it."*

| Verified | Consequence |
|---|---|
| `ActionScheduler::acquireLock()` (`action-scheduler.php:786`) is **private** and uses `LOCK_EX \| LOCK_NB` — *skip if busy*, returning `null` | Correct for a scheduler, wrong for a counter. Under the parallel brute force this fix exists to stop, every concurrent attempt would fail to acquire and skip its increment — a **deterministic** lost update, strictly worse than the racy one it replaces |
| The read and the write are **not adjacent**: `isLockedOut()` at `auth.php:101`, `recordFailedAttempt()` at `:175`, with `UserManager::authenticate()` between them | That call performs a bcrypt verify on **every** branch since the NEW-39 equalization, measured in D-056's own review at ~218 ms. A lock spanning read-to-write would serialise every login attempt on the install behind a 218 ms critical section — a denial-of-service lever built by a hardening slice |

**L-014 decides it**: the criterion (*a parallelized brute force must not get more than the nominal
attempts*) is the requirement; the recorded remedy is one person's guess at how. Each counter's
read-modify-write becomes atomic **within itself**, and the ~218 ms window is closed by the IP
ceiling rather than by a wider lock. The 218 ms figure is re-derived in slice 1 rather than quoted.

### 2. Two defects found by driving the mechanism, both in no audit entry — NEW-44 and NEW-45

- **NEW-44 — the central admin gate's refusals write nothing.** `admin-gate.php:282` and `:296`
  pass `'security'` as `$source` to `klytos_log_warning()`. `Logger::write():122` drops any source
  that is not `'core'` unless a **plugin** with that ID has logging enabled, and no plugin is called
  `security`. So every default-deny refusal of the S-07 gate is discarded, Developer Mode on or off.
  This is the exact mistake the Sprint 5 close found in `docs/playground.md`'s own remedy snippet —
  **fixed in the document, still live in the product**, because nobody asked whether the product made
  it too. L-019's shape a third time. **Fixed in path** (D-059): this sprint adds refusal logging and
  would otherwise reproduce it a fourth time.
- **NEW-45 — five swapped-argument log calls.** `chat-engine.php:213,221,229,331` and
  `chat-manager.php:304` call `klytos_log( $message, $level )`; the signature is
  `klytos_log( string $level, string $message, ... )`. The real error text lands in `$level`, fails
  `in_array( $level, self::LEVELS )`, is replaced by `'info'` and is **discarded** — every AI chat
  failure logs the literal word `error`. Recorded with a trigger, **not fixed**: the AI subsystem is
  not in this sprint's diff (D-031's narrowing).

### 3. NEW-42's four items all reproduce, and item 4's mechanism is confirmed

`api/webauthn-challenge.php` maps to `null` in the gate map (`admin-gate.php:197`), an audited
no-capability exception, so `klytos_enforce_admin_gate()` returns early — and then the setup-wizard
redirect at `bootstrap.php:375-379`, still matched by **basename** against four names, bounces the
endpoint to the wizard whenever `setup_completed === false`. `$preAuthScripts` moved to gate-map keys
in D-058; this second list did not move with it, which is why the two must now move together by
construction rather than by memory.

### 4. NEW-41's fix point is a single shared resolver

`TokenAuth::resolveUserActor()` (`token-auth.php:227`) reads `role` and never `status`, and it is
the DRY resolver D-047 built for **both** the application-password and the OAuth paths
(`validate():137` and `:156`). One change covers both — defence in depth for app passwords, which
D-056 already refuses earlier, and the actual fix for OAuth.

### The environment

Kickoff step 3 executed: `seed-playground.php --reset` clean, `KPORT=8112` bound cleanly with the log
checked for `failed to listen`, owning PID confirmed by `lsof` (php83, 30273), and the two documented
responses reproduced (admin `302`, MCP `401`) with no `Server:` header. **8080 is Docker for the
ninth consecutive session** (PID 45413, confirmed by `lsof`). The server was stopped before the
baseline suite ran — the playground is a single-tenant resource (L-025).

## Slices

| # | Slice | Closes | Status | Test point result | Notes |
|---|-------|--------|--------|-------------------|-------|
| 1 | The counters are atomic, and the login endpoint has a ceiling | **NEW-40**, **NEW-20**, **NEW-44** | planned | — | One promoted file-transaction primitive consumed by `Auth`'s lockout and `MCP\RateLimiter`; `recordFailedAttempt()` returns its post-increment count; the IP ceiling **reuses** the shipped `recordAuthFailure()`/`isAuthBlocked()` with no constant changed |
| 2 | Suspension takes effect on OAuth too | **NEW-41** | planned | — | `resolveUserActor()` reads `status`; the OAuth branch requires a non-null actor → **401**, where D-056 put application passwords |
| 3 | The passkey assertion path | **NEW-42** | planned | — | Clone detection (both counters non-zero only), the `origin` check, the length guard, and the setup-wizard skip-list moved onto gate-map keys |

### Slice 1 — the counters are atomic, and the login endpoint has a ceiling

**Reuse assessed and recorded before writing anything.** `MCP\RateLimiter` already ships IP-keyed
auth-failure tracking with exactly the semantics wanted — `recordAuthFailure()` / `isAuthBlocked()`,
`MAX_AUTH_FAILURES = 10` per 60 s — and `server.php:87,101` already consumes it for the MCP endpoint.
D-056's implementation note 3 rejected this class for the **per-account** lockout because expressing
5-attempts/15-minutes through it would have meant changing its constants and weakening the MCP
surface. **That reason does not apply to the IP ceiling**: no constant moves. This is reuse, not a
fork, and the distinction is recorded so the next reader does not read it as a contradiction.

1. `installer/core/file-lock.php` — one exclusive file transaction holding a single `LOCK_EX` across
   read → decide → write, with a bounded retry deadline and an explicit fail-closed return. It does
   **not** absorb `ActionScheduler::acquireLock()`: that method's `LOCK_NB` skip-if-busy semantics
   are correct for a scheduler and it is a third subsystem, so the difference is documented at both
   sites rather than unified by force (D-031's narrowing).
2. `Auth::recordFailedAttempt()` / `resetLoginAttempts()` run inside it; `recordFailedAttempt()`
   returns the post-increment count so the decision uses a fresh value rather than the pre-read one.
3. `MCP\RateLimiter::check()` and `recordAuthFailure()` run inside it — closing **NEW-20** with the
   same implementation, which is the audit entry's own argument for doing the two together.
4. `admin/login.php` gains the IP ceiling around the `$auth->login()` call at `:126`.
5. Fixed in path: **NEW-44** (the two `'security'` sources), and the stale `getLockoutBucket()`
   reference in `auth.php:99`'s comment — the method is `lockoutKey()`.

**Tests.** The one that matters is a **real concurrency test**: N parallel PHP processes hammering
one bucket, proving lost updates against the unfixed tree and none after. That settles NEW-20 by
execution rather than by argument, which is what its entry has been asking for since Sprint 1 slice
7 (L-016). Plus: the IP ceiling engages over real HTTP through the **shipped** login form, with the
request built to match what the page sends (L-026); a lock-timeout refuses rather than passing
uncounted; an undecodable file is treated as empty and logged rather than denying everyone. New HTTP
port **8108** (verified free; outside the 8099–8107 test block and outside `playground.md`'s
8080/8093/8110/8111/8123).

### Slice 2 — suspension takes effect on OAuth too (NEW-41)

1. `TokenAuth::resolveUserActor()` reads `status` alongside `role`; non-active yields null.
2. The OAuth branch of `validate()` requires a non-null actor to accept → **401**.
3. **Not built, and named rather than implied**: active revocation of a suspended user's tokens. The
   audit records the token lifecycle as its own decision with its own test point; deny-at-validation
   is what this slice delivers, and `docs/reference/mcp-authorization.md` will say which of the two
   it is.

**Tests** through the real MCP HTTP surface: an OAuth token for an active user works; suspend the
user and the same token answers 401 on the **next request**, not in an hour. Proven to fail first.
Port **8109**.

### Slice 3 — the passkey assertion path (NEW-42)

1. **Clone detection** — compare the unpacked sign count against the stored one, refusing only when
   **both are `> 0`** and the new one does not exceed the stored one (WebAuthn §7.2 step 21). Synced
   platform passkeys report `signCount = 0` always, so a naive "must increase" rule would refuse most
   real authenticators — a security fix breaking authentication, the trap D-044 recorded. Fires an
   action; nothing in core subscribes and the reference doc says so in those words (L-019).
2. **`origin` check**, mirroring `completePasskeyRegistration():503-509` including its
   `https://localhost[:port]` allowance. The asymmetry today favours the path that runs once per
   enrolment over the path that runs at every login.
3. **Length guard** before `ord($authData[32])` and `substr($authData, 33, 4)`, matching
   registration's `strlen < 37` check. It already fails closed; what it also does is emit a PHP
   warning, and `phpunit.xml` has carried `failOnWarning="true"` since D-054 — so the regression test
   asserts the warning's **absence**, which is the user-visible outcome.
4. **The setup-wizard skip-list** (`bootstrap.php:375-379`) moves off basename matching onto
   `klytos_admin_gate_key()`, exactly as `$preAuthScripts` did in D-058, and gains
   `api/webauthn-challenge.php`.

## Acceptance — this sprint is done when

1. A parallel burst against one account is counted **once per attempt**, proven by a concurrency test
   that fails against the unfixed tree — settling NEW-20's "plausible and unproven" by measurement.
2. A burst of invented usernames from one IP is refused by the ceiling, over real HTTP through the
   shipped login form.
3. A lock that cannot be acquired refuses the attempt; an undecodable counter file is treated as
   empty and logged rather than denying everyone.
4. A suspended user's OAuth token answers **401** on the next request; an active user's still works.
5. A passkey login still succeeds for a `signCount = 0` authenticator, is refused on a counter
   regression when both counters are non-zero, is refused on a wrong `origin`, and a 32-byte
   `authenticatorData` fails closed **with no PHP warning**.
6. The admin gate's refusals appear in the log with Developer Mode on — proven by reading the file,
   not by reading the call.
7. Full suite green (sprint start: **248 tests / 1152 assertions**), `keel-verify` `10 check(s) run`
   with its output pasted (the same 2 WARNs owned by Phase 7), upgrade tested from the **real**
   v0.30.1, and all five D-025 lint baselines held exactly (core+admin 192/488, plugins 113/109,
   tests 0/0, installer/public 0/0, scripts 0/2).
8. Both review subagents ran on the **finished** diff, docs included (L-015); `docs-verifier` and a
   fresh-context playground-QA pass at the close, **never concurrent with the suite** (L-025).
9. The user's own test verdict recorded.

## Explicitly out of scope (named, so it is not mistaken for oversight)

- **NEW-45** (the five swapped `klytos_log()` calls) — user decision at this kickoff, recorded with
  its own trigger.
- **NEW-43** (`klytos_delete_page` describes an action that did not occur) — its trigger is the page
  MCP tools or the NEW-35 slice; this sprint touches neither.
- **NEW-32** (the authorization audit hooks reach no sink). NEW-44 makes the gate's **own** logging
  work; wiring a default listener for the refusal actions stays its own slice with real volume and
  content decisions — D-057 answered that question once already.
- **Active OAuth token revocation on suspension** — see slice 2, item 3.
- **NEW-38, NEW-15, NEW-33, NEW-34, NEW-35, NEW-04**, the `.gitattributes` review
  (**NEW-27** / **NEW-28** / **H-02**, Phase 7), and raising the PHP floor (D-027's trigger).
- **The theme-package sprint** (D-023/D-024) and **the bilingual in-product guides** — the two the
  user weighed against this sprint at the kickoff. Both stay queued; the guides still need NEW-27
  fixed first or neither language ships.
- **Splitting the state files** — deferred with its trigger at the next sprint close, where the
  Sprint 5 close already said it is overdue.

## Risks carried into this sprint

1. **The IP ceiling can lock out a whole office behind one NAT address.** `getClientIp()` trusts
   `X-Forwarded-For` only from loopback, so behind a non-loopback proxy every visitor collapses into
   one bucket — that is **NEW-17**, pre-existing, and this slice makes it reachable on a second
   surface. The ceiling is filterable and the interaction is stated in the reference doc rather than
   discovered by an operator.
2. **A blocking lock is a new failure mode.** Bounded deadline, fail-closed, and a test that drives
   the timeout path so the branch is observed rather than reasoned about (L-010).
3. **Slice 3 touches the login path for passkey users.** The `origin` and clone checks can refuse a
   login that works today; both are pinned in **both** directions, and the synced-passkey
   `signCount = 0` case gets its own named test because it is the one that would break real users.
4. **D-039's config guard** fires on any test that mutates core config. Nothing here writes config.
5. **CI has never run** (L-022). Nothing here reaches `App::getChatEngine()`, so
   `#[Group('ai-runtime')]` does not apply — stated because the rule is live, not because it bites.
6. **The concurrency test is itself an instrument** and gets L-016's treatment: it must be observed
   producing lost updates against the unfixed tree before its silence on the fixed tree means
   anything.
