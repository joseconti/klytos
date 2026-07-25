# Sprint 5 — Authentication: the other three roles can log in, and a password change means something

- **Planned:** 2026-07-25 (plan mode, approved by the user). Kickoff re-validation ran across the
  planning session and the implementation session; every claim below was re-derived from source in the
  session that wrote it, not carried from the plan (L-015).
- **Status:** **IN PROGRESS** — **slice 1 CLOSED 2026-07-25** (audit **NEW-11**, **NEW-37** and
  **NEW-39** closed; **NEW-40** and **NEW-41** opened and recorded). Slice 2 (NEW-09) next.
- **Scope basis:** audit **NEW-11** (only `config['admin_user']` can log in), found 2026-07-19 in
  Sprint 1 slice 4 and deferred with the trigger *"the authentication slice"*; audit **NEW-09**
  (passkey second-factor login is broken and its obvious fix opens an account takeover), bound by
  **D-036** to *"the same slice that closes NEW-11"*; and audit **NEW-37**, found at this kickoff by
  driving the feature rather than the finding (L-014). Decisions: **D-056** (the record is the sole
  login authority) and **D-057** (this scope).

## Why this sprint exists

Four sprints have built an authorization system — a 64-surface default-deny admin gate, one capability
matrix, per-credential MCP actors, `ai.use` widened to `editor` — on top of a login that exactly one
account can pass. `Auth::login()` (`installer/core/auth.php:83`, the comparison at `:99-102`) validates
against `config['admin_user']` / `config['admin_pass_hash']` and never calls the fully-implemented
`UserManager::authenticate()` (`user-manager.php:384`), which verifies the per-user bcrypt `pass_hash`,
refuses suspended accounts and updates `last_login`.

The consequence has been stated in every sprint close since: **the multi-role system these gates enforce
has, in production, one role that can reach the admin panel interactively.** Sprint 2's own end-to-end
proof had to be a bearer token precisely because no non-owner account could exist behind a login.

## Re-validation of assumptions (Phase 5 §0 kickoff, step 2)

Verified against source, by reading the files and running the code — not against the recorded plan.
**Two things the plan says are wrong, and both are corrected in the record before any code is written.**

### 1. NEW-11 reproduces live

Driven against the real login form on this session's own server (bind verified, owning PID confirmed,
no `Server:` header — L-011 + L-021), with the seeded passwords:

| user | HTTP | result |
|---|---|---|
| owner | 302 | in |
| admin | 200 | "Incorrect username or password" |
| editor | 200 | refused |
| viewer | 200 | refused |

### 2. A second live defect, found by driving the FEATURE rather than the finding (L-014) — NEW-37

Proven with a **net-zero probe**: the password was changed and restored inside one run, so the
playground came back byte-identical and D-039's config guard had nothing to fire on.

```
1. UserManager::changePassword( owner, NEW )
2. UserManager::authenticate( owner, NEW )   -> ACCEPTED   the record updated
3. Auth::login( owner, NEW )                 -> REFUSED    the change never reached the gate
4. Auth::login( owner, OLD )                 -> ACCEPTED   the old password still works
5. restored
```

So **no supported path can rotate the password of the only account that can log in** — not the profile
page, not the admin-issued reset link, not the MCP `klytos_reset_user_password` tool. And
`reset-password.php:71` prints *"Your password has been reset successfully. You can now log in."*
immediately after `changePassword()` returns true, which is false for the owner: the **L-002 defect,
live in the product**. This is why D-056 refused the config fallback — the fallback preserves the bug,
not a working path.

### 3. The OAuth consent caller — the plan's description was wrong in mechanism (NEW-38)

`Auth::login()` has exactly **two** callers repo-wide: `admin/login.php:115` and
`core/mcp/oauth-authorize-view.php:91`. The plan said the consent view *"treats `requires_2fa` as a
failed login and re-renders the form"*. It does not: the only branch that inspects the result is
`if ( ! $result['success'] )` at `:93`, and on the 2FA path `success` is **true**, so nothing is shown
at all. Execution falls to the screen selector at `:131-138`, which asks only `isAuthenticated()` —
false while 2FA is pending — and re-renders the login form with **no error and no second-factor
prompt**. Outcome (an infinite loop) unchanged; mechanism corrected. `is2faPending`, `complete2fa` and
`requires_2fa` appear nowhere in that file. Recorded as **NEW-38**, out of scope (D-057), and the
plan's file path was wrong too: it is under `installer/core/mcp/`, not `installer/admin/`.

### 4. Four consequences the fix itself creates or makes reachable — in scope BY NECESSITY

Not opportunism: D-031's narrowing (the code path the slice is already changing and testing) and the
NEW-16 shape from slice 7 (the acceptance criterion is unreachable without it).

| # | Verified | Why the fix breaks or exposes it |
|---|---|---|
| a | `getLockoutFile()` (`auth.php:851-853`) returns `sys_get_temp_dir() . '/klytos_lockout_' . md5( config['admin_user'] ?? 'admin' ) . '.json'` | **ONE global bucket.** With four accounts able to log in, five failures against *any* username lock out *everyone*, owner included. And the path is predictable and world-writable: another tenant on shared hosting can forge a permanent lockout or delete one |
| b | `admin/security.php:144` — `password_verify( $confirmPass, $mainConfig['admin_pass_hash'] ?? '' )` | The encryption-level change re-verifies the operator against **config**. The moment rotation works, it demands the **old** password forever |
| c | `Auth::validateAppPassword()` (`auth.php:688`, the pin at `:695-698`) refuses every username except `config['admin_user']`, while `admin/mcp.php:48` mints under `$auth->getUsername()` and the gate map puts `mcp.php` at `mcp.manage` = `['owner','admin']` (`user-manager.php:629`) | The moment an admin can log in, they mint an application password that **can never authenticate**. `TokenAuth::resolveUserActor()` (`token-auth.php:225-247`) already derives the role from the user record — D-047 built this "NEW-11-ready" — so resolving the username against an **active** record lands a per-user app password in D-046's gate carrying its own role, with no further work. **Behaviour change on the MCP surface → release note** (the D-034 precedent) |
| d | `UserManager::update()` validates `status` at `:212-214` with **no owner guard**, while `delete()` refuses the owner at `:267` | After this slice an owner could suspend themselves into an install that `owner:repair` **also** refuses (it refuses when an owner exists) — permanently unrecoverable through the product. `update()` gains the owner-status guard, mirroring `delete()`. Separately, `isAuthenticated()` (`:285-301`) already re-reads the user record every 60 s for `force_logout_at`; the same read gains a `status` check, so "suspended" stops meaning "suspended in up to 30 minutes" |

**One more sits inside the method slice 1 rewrites:** `validateAppPassword()` iterates
`$data['passwords'] ?? [] as &$stored` (`auth.php:702`) — audit **NEW-29**, the L-017 footgun, so
`last_used` has never persisted. It was classified "adjacent" when it was found because no slice was
touching that method. This slice rewrites it, so D-031's narrowing now applies: it is fixed in path and
pinned, or the test point records why not.

### 5. Slice 2's ground truth

- `login.php`'s 2FA dispatcher (`:63-107`) branches `totp` / `recovery` / `email` / `emergency_email`
  only — **no `passkey` branch** — while the same file's own front end already posts the assertion as
  `2fa_method=passkey`: the hidden field at `:263` and the `navigator.credentials.get()` block at
  `:317-348` that fills it and submits.
- `TwoFactor::verifyPasskeyAssertion()` (`two-factor.php:586`) has **zero call sites** — re-checked
  repo-wide this session; every other match is a comment or a document.
- `webauthn-challenge.php:20` gates **all** its actions on `( ! isAuthenticated() && ! is2faPending() )`.
- `bootstrap.php:305` — `$preAuthScripts = [ 'login.php', 'logout.php', 'reset-password.php' ]`.
- The assertion path is testable **without a browser**: `coseKeyToPem()` (`two-factor.php:1193`) accepts
  ES256 at `:1207` (`kty === 2 && alg === -7`), and registration already offers `-7` / `-257`
  (`:399-400`).

### The environment

Kickoff step 3 (playground boots from its documented commands) was executed in the planning session:
`KPORT=8112`, bound cleanly, owning PID confirmed as that session's own. **8080 is Docker for the
eighth consecutive session.** A `php -S` from the previous session is still listening on **8083**
(PID 35717, started 17:29, confirmed ours by `lsof` + `ps -o lstart`) — it is killed before this
sprint's first test point, per L-025: the playground is a single-tenant resource.

## Slices

| # | Slice | Closes | Status | Test point result | Notes |
|---|-------|--------|--------|-------------------|-------|
| 1 | The gate consults the user record | **NEW-11**, **NEW-37**, **NEW-39** | **closed 2026-07-25** | **PASS** — suite **227 → 243 tests / 1059 → 1130 assertions**; keel-verify 10 checks exit 0 (the same 2 Phase-7 WARNs); upgrade from real v0.30.1 PASS **and now asserts LOGIN, not just boot**; all five D-025 baselines held exactly. Evidence in `docs/05-test-points.md` | `Auth::login()` delegates to `UserManager::authenticate()` (D-056); per-account lockout keyed by the **submitted** username, one pruned file in `installer/data/`; `security.php` re-pointed; `validateAppPassword()` resolved against an **active** record, so a non-owner's credential reaches D-046's gate with its own role; owner-status guard; `status` on the 60 s re-read; **NEW-29 fixed in path**; six teaching surfaces corrected (L-004). **The `code-reviewer` returned a BLOCKING finding that was correct and was my own guard defeating itself** — `role` is processed before `status` and mutates `$user` in place, so demoting in the same call sailed past the owner-suspend check. **NEW-39 found by the `security-auditor` and MEASURED at 340×** before being fixed; the two reviewers described it differently and only one was right (**L-023**) |
| 2 | Passkey second-factor login completes | **NEW-09** | **planned** | — | Order is the point: restrict the registration actions **first**, add the dispatcher branch, notify on enrolment, and only **then** the `$preAuthScripts` entry. D-036's one-liner stays forbidden until it is last |

### Slice 1 — the gate consults the user record

1. `installer/core/auth.php::login()` — delegate credential verification to
   `UserManager::authenticate()`. Keep `auth.before_login` / `auth.after_login`, the lockout, the
   `session_regenerate_id( true )` and the existing per-user 2FA branch (`userHasTwoFactor()` already
   reads the record, so per-user 2FA works the moment the user id comes from the record).
2. **Per-account lockout**, keyed by the resolved account, with **one shared bucket** for usernames
   that do not resolve — so no existence oracle appears: both cases answer with the same
   `account_locked:` / `login_failed` strings the form already renders. The file moves out of
   `sys_get_temp_dir()` into the install's own data directory, which also puts it inside D-030's
   snapshot/restore.
3. `installer/admin/security.php:144` — re-point the `confirm_password` check at the same authority.
4. `installer/core/auth.php::validateAppPassword()` — resolve the username against an **active** user
   record. NEW-29 fixed in path or explicitly recorded.
5. Lockout hazards this fix creates: the owner-status guard in `UserManager::update()`, and the
   `status` check on `isAuthenticated()`'s existing 60 s re-read.
6. **Every surface that teaches the old truth, in the same slice (L-004):**
   `tests/AdminHttpTestCase.php`'s docblock (it cites `auth.php:99-102` as the reason sessions are
   synthesized), `scripts/dev/seed-playground.php:347` (*"the ONLY account that can currently log in"*),
   `docs/playground.md`, and a new `docs/reference/authentication.md` with its `docs/api/INDEX.md` row.

**Tests — the gate is named before the test is written (L-024): the gate is `Auth::login()`,
not `UserManager::authenticate()`.** Every one proven to FAIL against the unfixed tree first (L-016),
with the positive control checked to actually run:

- all four seeded roles authenticate through `Auth::login()` (integration tier, real `session_start()`
  — the `OwnerRepairTest` precedent);
- the four-role walk through the **real login form** over HTTP (`AdminHttpTestCase`), asserting the
  refusal *reason* in the body and not only the status (L-009 / L-012) — today 302/200/200/200;
- rotation: change the password, then `Auth::login()` accepts the new one **and refuses the old** — the
  kickoff probe turned into a permanent regression test;
- a suspended account is refused at login, and a live session for a suspended account ends;
- locking account A does not lock account B (fails against the global bucket);
- a non-owner's application password authenticates over MCP and is gated at **its own** role; a
  suspended user's is refused.

### Slice 2 — passkey second-factor login completes (NEW-09)

1. Restrict `register_challenge` / `register_complete` to **fully authenticated** callers; leave only
   `auth_challenge` reachable while 2FA is pending.
2. Add the `passkey` branch to `login.php`'s dispatcher, wired to `verifyPasskeyAssertion()`.
3. Notify the account owner when an authenticator is enrolled (the mailer already exists — the
   magic-link flow uses it).
4. **Only then** add `installer/admin/api/webauthn-challenge.php` to `$preAuthScripts`
   (`bootstrap.php:305`), with the D-036 question answered **in writing**: with (1) in place, what the
   endpoint permits pre-auth is a challenge for a user who has already passed the password stage.

**Tests:** a fixture generates a P-256 key with `openssl_pkey_new`, hand-builds the COSE key and signs
`authData || SHA-256( clientDataJSON )`, driving a real passkey login end to end with no browser. Plus
the takeover proof D-036 found: `register_complete` is **REFUSED** in the 2FA-pending state — proven to
fail against the unrestricted endpoint first.

## Acceptance — this sprint is done when

1. All four seeded roles log in through the **real login form**, and `Auth::login()` is the function the
   test calls — proven to FAIL against the unfixed code first (L-016, L-024).
2. A password changed through any supported surface is accepted by `Auth::login()` **and the old one is
   refused** — the kickoff probe as a permanent test.
3. Five failed attempts against account A do not lock account B, and an unresolvable username is
   indistinguishable from a wrong password in the response.
4. A non-owner application password authenticates over MCP and is gated at its own role; a suspended
   account can neither log in nor keep a live session.
5. A passkey completes a second-factor login end to end, and `register_complete` is refused in the
   2FA-pending state.
6. Full suite green (sprint start: **227 tests / 1059 assertions**), `keel-verify` 10 checks exit 0 with
   its output pasted (the same 2 WARNs owned by Phase 7), upgrade tested from the **real** v0.30.1 — 
   load-bearing in a new way this sprint, since an upgraded install's ability to log in now depends on
   the owner **record** the boot migration creates — and all five D-025 lint baselines held exactly
   (core+admin 192/488, plugins 113/109, tests 0/0, installer/public 0/0, scripts 0/2).
7. Both review subagents ran on the **finished** diff, docs included (L-015); `docs-verifier` and a
   fresh-context playground-QA pass at the close, **never concurrent with the suite** (L-025).
8. The user's own test verdict recorded.

## Explicitly out of scope (named, so it is not mistaken for oversight)

- **NEW-26** (the password-reset form has no CSRF), **NEW-32** (the authorization audit hooks reach no
  sink), **NEW-13** (the identity export has no re-auth, 2FA or owner notification) — **user decision
  at this kickoff, D-057.** Each keeps its recorded trigger.
- **NEW-38** (the OAuth consent screen cannot complete a 2FA login) — found at this kickoff, recorded
  with a trigger. Pre-existing; this sprint widens its population, which is stated in the entry rather
  than left implicit.
- **NEW-15** (DNS rebinding), **NEW-33** (the terminal's Spanish strings), **NEW-34**, **NEW-35**,
  **NEW-04** (build writes into the repo root), the `.gitattributes` review (**NEW-27** / **NEW-28** /
  **H-02**, Phase 7), and raising the PHP floor (D-027's trigger).
- **The theme-package sprint** (D-023 / D-024) stays queued and un-estimated — the user chose this
  sprint over it at the kickoff.
- **NEW-09's one-line fix remains FORBIDDEN** (D-036) until it is the **last** step of slice 2.

## Risks carried into this sprint

1. **An install whose owner record is missing loses login entirely** (today it logs in and is then
   denied everywhere by the gate). Mitigated by boot's idempotent migration (D-021/D-031) and by
   `owner:repair` (D-055) — the two sprints compose, and the upgrade test from real v0.30.1 is what
   proves it. It is now a **login** test, not only a boot test.
2. **D-039's config guard** fires on any test that mutates core config. Nothing here should write
   config: `Auth::login()` writes none, and moving the lockout file into `data/` keeps it inside
   D-030's snapshot rather than outside every guard.
3. **`Auth::login()` calls `session_regenerate_id( true )`**, so its tests need a real
   `session_start()` — the `OwnerRepairTest` precedent, the one place this tier deviates from its own
   design, stated in the test rather than discovered.
4. **A leftover `php -S` on 8083** (PID 35717, this project's own, from the previous session) is killed
   before the first test point — L-021 for identification, L-025 for why it cannot be left running.
5. **The MCP app-password change is a behaviour change for third parties** — a credential minted for a
   non-`admin_user` username starts authenticating where it used to be refused. Release note, the D-034
   precedent.
6. **CI has never run** (L-022). Nothing in this sprint reaches `App::getChatEngine()`, so
   `#[Group('ai-runtime')]` does not apply — stated because the rule is live, not because it bites.

## Close-out — filled at the sprint close

| Requirement | Status |
|---|---|
| Full suite green (every test, not only this sprint's) | — |
| `keel-verify` output pasted | — |
| Upgrade tested from the REAL previous version | — |
| Lint baselines held (all five, per scope, no default value — L-016) | — |
| `code-reviewer` + `security-auditor` per slice, on a finished diff (L-015) | — |
| `docs-verifier` over everything the sprint touched | — |
| Playground-QA fresh-context pass (never concurrent with the suite — L-025) | — |
| Numbered try-it script handed to the user, debug log ON | — |
| **User's recorded verdict** | — |
| `PROGRESS.md` / `lessons-learned.md` / `token-ledger.md` updated | — |
| Continuation prompt produced unprompted | — |
| Finished docs archived to `docs/old/sprint-5/` (or "nothing qualified", stated) | — |
