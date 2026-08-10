# Test Points — Klytos CMS

> One row per slice, filled at the moment its test point passes — never retrospectively.
> An empty cell is a missing check, not a formatting gap. The evidence cell carries the **exact
> commands run, a one-line result summary, and the commit hash**; a result without them is empty.
> An acceptance criterion without an automated test carries its one-line justification in the row.
> "n/a" is a valid value where a column genuinely does not apply — but it is written, never left blank.

## The two coverage columns (Keel v5.0.0 — in force from Sprint 7)

From the next sprint table on, every row carries two further columns, and they exist for one reason:
**without them the coverage rule is self-declared**, which is exactly the failure Keel v5.0.0 was
written to end.

| Column | Value |
|---|---|
| `Criterion` | The `AC-nn` ID from `docs/02-functional-spec.md` §4b, or `—` for a check that is not criterion-bound |
| `Coverage` | Exactly one of: **`driven`** (a test drove it — its command and evidence are in the row) or one of the eight delegation tags: `CREDENTIAL` · `HARDWARE` · `ASSISTIVE-TECH` · `JUDGMENT` · `EXTERNAL-APPROVAL` · `PLATFORM-IMPOSSIBLE` · `PRODUCTION-RISK` · `NO-EXECUTION` |

**Free text in `Coverage` is a defect.** The whole point of the enum is that a script can count the
rows that are neither `driven` nor a valid tag. A tagged row carries the exact steps for whoever runs
it; a tag with no steps, or `JUDGMENT` on a criterion with no driven test beside it, is the laziest
escape in the contract and is caught at the sprint close.

**The rule this serves, stated once so no later session softens it: the user is not the test runner.**
Anything a machine can drive, the assistant drives — it starts the environment, fills every field
with valid, empty and invalid values, clicks every branch including the failure paths, asserts what
the interface actually shows, and reads back the console, the failed requests and
`installer/data/logs-*/`. "Go to that screen and tell me what you see" is not a test; it is the
assistant outsourcing its own work, and it silently skips the empty, invalid and permission-denied
cases where the bugs live. The full protocol is `references/test-automation.md`; the project's driver
table is `docs/03-technical-plan.md` §4a.

**Sprints 1–6 are NOT retrofitted.** Their rows predate both the `AC-nn` IDs and this enum, and
back-filling a `Coverage` value nobody recorded would be inventing evidence — the precise defect this
column exists to prevent. Each area gets its IDs and its coverage value **in the slice that next
touches it**.

**Neither column is in any table yet, and that is stated rather than implied.** The legend above
defines `Criterion` and `Coverage` for the *next* sprint table; the Sprint 1 table below carries
twelve columns and neither of them, exactly as this section says it should. A reader who greps for
`Coverage` and finds only this legend has found the truth, not a gap.

## The `Red first` column (Keel v5.11.0 — in force from the next row written)

The card says `Test-first policy: pure-logic`. A policy nobody records is a policy nobody applied, so
every test-point row carries **`Red first`**, holding exactly one of five values — and nothing else,
for the same reason `Coverage` is closed: a script can only count what it can recognise.

| Value | Means |
|---|---|
| `observed` | The test was run **before** the code existed and failed on the absent behaviour. Its failure line is in this row's evidence cell, introduced by the literal phrase **`red observed:`** followed by the failure inside backticks |
| `n/a — policy` | The card's `Test-first policy:` does not cover this row (`none`, or an acceptance-level row on a `pure-logic` project) |
| `n/a — out of scope` | The row is in the not-applied table of `references/test-automation.md`: markup, framework glue, exploratory third-party integration, configuration |
| `n/a — predates` | The row existed before this project adopted the policy. **The policy is never retroactive**, and this value exists so a row that WOULD be in scope is never confused with one the policy does not cover |
| `n/a — delegated` | The row's `Coverage` is one of the eight delegation tags, so nobody here ran it. On `NO-EXECUTION` the test is still WRITTEN first and handed over, and that goes in the delegation steps |

`scripts/keel-verify` enforces this in three checks, and **the asymmetry between them is deliberate**:
it **FAILS** a cell that is empty or outside the five values; it **FAILS** a row claiming `observed`
with no `red observed:` failure line in its evidence cell — a claim without its evidence, which is the
one thing this project does not tolerate; and it **REPORTS, never fails**, every row that is neither
`observed` nor `n/a — delegated`. The third is blunt on purpose: the script cannot decide whether a
given piece of code is pure logic, so any judgment-bearing value has to be visible, or the mildest one
quietly becomes the default.

**Every existing row takes `n/a — predates`** — that value exists for exactly this migration. **Two do
not**: entry 41's prerequisite (D-084, red `TypeError: count()… false given` at `logger.php:264`) and
entry 41 itself (D-085, red `Call to undefined method … parseLine()`). Both were genuinely written
test-first, both reds are quoted in their decision entries, and neither is a backfill: they are the
first two rows this project wrote under the policy.

## Sprint 1 — authorization axis

| Slice | Red first | Acceptance criteria checked | Real run in playground | Security checks | Accessibility (automated + AT pass) | i18n (strings externalized) | Reuse checked (no new duplicate) | Docs updated (api/reference, example runs) | Extension points exposed | Evidence (commands + output summary + commit) | Result | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 0 — Playground + gate zero | n/a — predates | Playground boots from documented commands; 4 roles seeded; MCP reachable; `.htaccess` denies replicated under `php -S` | **yes** — seeded, booted, admin 302→login, login 200, 4 users + 3 pages created via the product's own managers | Deny checks: `config/.encryption_key`, `config.json.enc`, `admin-identity.priv.enc`, `.playground-access`, `data/**`, `core/*.php`, `backups/`, `/.git/config`, `plugins/**/*.php` → **all 403**. MCP unauthenticated → **401**. `git check-ignore` confirms every generated secret is ignored; `git status -uall` over config/data/public → **empty** | n/a — no UI written in this slice | n/a — no user-facing strings added | **yes** — seeder drives `UserManager::create()`, `PageManager::create()`, `Auth::createAppPassword()`, `Encryption::generateRsaKeyPair()` rather than hand-writing storage; no new duplicate helper | `docs/playground.md` created; no new public PHP surface introduced (both scripts are dev entry points, not API) | n/a — dev tooling, no extension points | See the evidence block below | **PASS with one gate-zero item open** | Found 2 production bugs: NEW-03 (by-ref hooks) and NEW-04 (build writes into the repo root). Neither is fixed here; both recorded with triggers |
| 1 — Test harness + dev manifest | n/a — predates | `composer install` clean; both tiers run; unit tier passes on a bare checkout; integration tier boots the App and resolves each seeded role from `$_SESSION`; `phpunit` green; `phpcs` green | **yes** — the integration tier IS a playground run: it boots the real App against the seed and asserts all 4 roles; playground re-seeded and re-booted from the documented commands this session (freshness row below) | Harness only — no product security boundary changed. Two security-relevant properties of the harness itself were verified: the integration tier **skips loudly** instead of passing when the playground is absent (proven by moving the two install files aside), and `actingAs()` sessions are accepted by the product's own `Auth::isAuthenticated()` while a guest session is rejected — so later refusal tests cannot pass against an anonymous session | n/a — no UI written in this slice | n/a — no user-facing strings added | **yes** — class loading uses Composer's `classmap` over `installer/core/` rather than re-implementing `App::registerAutoloader()`'s CamelCase→kebab-case mapping (private, bound to a booted instance), so no second copy of that rule exists. Temp-dir teardown is NOT shared with `seed-playground.php`'s purge: that one deliberately preserves tracked `.htaccess` guards, this one deletes everything — different contracts, not a duplicate | No new product API surface (test harness + dev manifest). Teaching surfaces updated in the same slice per L-004: `docs/03-technical-plan.md` §1 and §4, `docs/playground.md` ("Running the tests"), the `klytos-core-development` skill (new Testing section), and the `docs-discipline` rule in all 7 mirrored containers | n/a — dev tooling, no extension points | See the evidence block below | **PASS** | Composer aliases added (`composer test` / `test:unit` / `test:integration` / `lint`). `composer.lock` untracked→tracked (D-027). `phpcs.xml` now covers `tests/`; `PSR1.Files.SideEffects` excluded for `tests/bootstrap.php` only, with justification. `failOnWarning` deliberately left off until NEW-03 is fixed (D-026) — reason recorded in `phpunit.xml` |
| 2 — `vendor-ai/` manifest + CVE audit | n/a — predates | Manifest resolves to **exactly** the 16 vendored versions (0 deltas); `composer audit` runs and its full output is pasted below; CVE findings reported and triaged, **not** patched (D-022) | **yes** — session-start freshness boot from `docs/playground.md` verbatim: admin 302, login 200, MCP 401 unauthenticated, `config/.encryption_key` 403, 177 tools authenticated. The slice itself changes no runtime code, so there is no new flow to walk | **This slice IS the security check.** First `composer audit` the project has ever been able to run: **5 advisories / 2 packages** (guzzle 7.10.0, psr7 2.9.0), all medium — recorded as NEW-05. Reachability assessed rather than assumed: no `CookieJar` anywhere, no user-controllable URLs (5 hardcoded provider endpoints, `chat-engine.php:242-247`), `vendor-ai/` loaded lazily from one call site. Also found NEW-06 (PHP 8.3 floor vs declared 8.1+) and NEW-07 (2 BSD packages with no licence text). **Verified `vendor-ai/` was not mutated** by the resolution: `git status --porcelain installer/vendor-ai` empty | n/a — no UI written in this slice | n/a — no user-facing strings added | **yes** — no new PHP surface. The drift guard extends `PHPUnit\Framework\TestCase` directly rather than `UnitTestCase`, whose per-test encryption key and throwaway storage would be set-up cost with no consumer; version normalisation is one private helper used by all four readers, not repeated per call site | `docs/03-technical-plan.md` §1 (both the dependency rows and the risk block), `docs/04-adoption-audit.md` (H-04 closed + NEW-05/06/07), `installer/vendor-ai/LICENSE-THIRD-PARTY.md` (all 16 packages, attribution corrected, BSD text added), `docs/playground.md` (new "Auditing the vendored dependencies" section — commands run as written). No new public API surface, so no `docs/api/` row | n/a — dev tooling and packaging metadata, no extension points | See the evidence block below | **PASS — with 5 CVEs open for user triage** | The sprint's "widest unknown" is now bounded: the upgrade is constraint-compatible (`soukicz/llm` allows guzzle `^7.9`, fixes are 7.12.1 / 2.12.1), so it is a re-vendor, not a dependency rewrite. Still a scope change → Estimate v2, per D-022 |
| 3 — One matrix + fail-closed current user | n/a — predates | Viewer denied an owner-only permission; unknown permission denied for every non-owner role; owner shortcut intact (unknown keys included); **a session with no `klytos_user_id` is DENIED, not promoted**; v1.x migration idempotent; **upgrade tested from the REAL previous version (v0.30.1)** | **yes** — session-start freshness boot from `docs/playground.md` verbatim (admin 302, login 200, `config/.encryption_key` 403, MCP 401). Beyond the playground, a **real v0.30.1 install** was built by that release's own installer in a temp dir and upgraded to the working tree: 12/12 assertions pass, including the NEW-01 denial on a genuinely upgraded install | **This slice IS the security fix.** NEW-01 closed: the hardcoded `['role' => 'owner']` fallback is gone; both failure shapes (no `klytos_user_id`; id that does not resolve) deny and log. S-04 closed: one matrix, in `UserManager`. Independent `security-auditor` pass over the diff: **no blocking findings** — it traced every caller of `klytos_current_user()`, `Auth::getUserId()` and direct `$_SESSION['klytos_user_id']` reads and found no residual path to privilege, confirmed display-only callers fail closed on null, and confirmed the new log calls carry no secret, hash or personal data. It also confirmed the temp-dir scripts cannot delete outside their `mktemp` directory and embed only throwaway credentials | n/a — no UI written in this slice | n/a — no user-facing strings added. The two new log messages are operator-facing diagnostics, not UI copy | **yes** — the matrix was not re-implemented anywhere: `klytos_has_permission()` now delegates to the existing `UserManager::hasPermission()`. No new helper was created; the fix is a deletion plus a delegation. Test-side, `PlaygroundState` is a new trait rather than a copy of `UnitTestCase`'s temp-dir teardown, whose contract is different (that one preserves nothing; this one must preserve tracked `.htaccess` guards) | `docs/04-adoption-audit.md` (S-04 and NEW-01 marked CLOSED with the evidence; NEW-02 sharpened; NEW-08 added), `docs/playground.md` (new "Testing an upgrade from the real previous release" section, plus the isolation contract under "Running the tests"), `docs/decisions.md` (D-030, D-031), `docs/lessons-learned.md` (L-006). No new public PHP surface introduced — the change removes a surface and redirects a caller — so no `docs/api/` row | n/a for this slice — the `auth.capabilities` filter already existed and still applies, now at the single remaining call site (`user-manager.php:628`) rather than at two | See the evidence block below | **PASS** | S-04 resolved the **opposite way** to the audit's remediation note, deliberately and recorded: the "dead" copy was kept and the live one deleted, because `UserManager` is the lower layer and slice 4 / Sprint 2 both hold a user object rather than a session. One defect caught before commit and recorded as **L-006**: the first version of the crash fix logged through `$this->logger`, which is null at that point in boot and would have raised a `TypeError` — a crash introduced by a crash fix, on a path no test can reach. **The `code-reviewer` pass returned a BLOCKING finding and it was fixed, not argued with:** the Step 10b `try`/`catch` was never executed by any test, because every install the tests boot already has an owner, so `migrateFromV1Config()` was never reached *through* `App::boot()`. The upgrade script gained two phases (`break-migration`, `boot-must-survive`) that put an install into the exact state that used to fatal and boot it in a fresh process. Two further reviewer findings applied: `UserManager::hasPermission()` now denies a record with no usable role instead of defaulting it to `viewer` (with its own test), and a comment citing the wrong boot step was corrected |
| 4 — `klytos_require_permission()` + central gate | n/a — predates | Per-role integration tests against representative pages **and** endpoints asserting the 403/401 **SHAPE**, not only the status; all 66 admin files accounted for in the gate map; `keel-verify`'s gate check demonstrably FAILS on a removed gate | **yes** — session-start freshness boot from `docs/playground.md` verbatim (admin 302, login 200, `config/.encryption_key` 403, MCP 401 unauthenticated, 177 tools authenticated). Beyond that, a **full 65-surface × 4-role walk over real HTTP** (260 requests) confirming both halves: privileged surfaces refuse, and nothing a role legitimately needs became unreachable (sprint risk 1) | **This slice IS the security fix.** S-07 closed: coverage 15/66 → 65/66 mapped surfaces, default-deny for the 66th and for anything added later. Defects closed in-path (D-033): NEW-10 setup-wizard escalation, and NEW-12 — `api/download-identity.php`'s **three stacked defects**, each masking the next, all **verified live** rather than believed. **NEW-09's fix was implemented and then REVERTED (D-036)**: the `security-auditor` pass showed the auth-guard exemption opens a full account-takeover primitive (a correct password alone would enrol an attacker's authenticator), and it buys nothing because passkey login cannot complete regardless. The hand-rolled `in_array( $role, ['owner','admin'] )` in `security.php` is gone, so `UserManager::hasPermission()` is still the single decision point (S-04 preserved). Independent `security-auditor` and `code-reviewer` passes over the diff | n/a — no UI written in this slice. The 403 page is a self-contained document; it sets `lang`, `charset`, a viewport and `noindex`, and escapes every interpolated value | **yes** — 4 new keys (`common.forbidden`, `common.no_permission`, `common.authentication_required`, `plugins.page_declares_no_capability`) added to **all 20 catalogues** (D-006). The first two were already REFERENCED by `plugin-page.php` and existed in **no** catalogue, so that gate had been rendering `__()`'s generated fallback — the audit called it "i18n'd"; it was not | **yes** — the denial shape was **promoted** from `core/router.php:438-447`, not reinvented; `klytos_require_permission()` delegates to the existing `klytos_has_permission()` → `UserManager::hasPermission()` chain rather than re-deciding; the 4 legacy redirect gates were deleted rather than left beside the central one | `docs/reference/authorization.md` **created** (matrix, gate map semantics, all 6 functions with runnable examples, both extension points, the "adding a new admin page" checklist, and an explicit "what this does NOT cover"); 8 new rows in `docs/api/INDEX.md` with counts updated 930 → 938; `docs/04-adoption-audit.md` (S-07 CLOSED + NEW-09/10/11); `docs/playground.md`; `docs/decisions.md` (D-032…D-035); `docs/lessons-learned.md` (L-007, L-008) | **yes** — `admin.gate_map` filter (a plugin gates its own admin files) and `auth.access_denied` action (the audit hook, deliberately **unable** to reverse the decision). The pre-existing `auth.capabilities` filter still governs the matrix | See the evidence block below | **PASS** | Both review subagents returned **blocking** findings; both were fixed rather than argued with, and the slice's test set grew from 6 to 13 HTTP tests as a direct result. 5 capabilities added to the one matrix. `ai.use` deliberately excludes `editor` while NEW-02 is open (D-035). `plugin-page.php` now DENIES a plugin that declares no capability — a **breaking change for third-party plugins** (D-034), verified to break no shipped plugin. **NEW-11 found and NOT fixed:** `Auth::login()` never consults `UserManager`, so only `config['admin_user']` can log in — which is very likely *why* S-07 survived unnoticed |
| 5 — Named escalations, one test each | n/a — predates | One NAMED test per finding asserting the refusal (S-01, S-02, S-03, S-05, S-06, S-12), each with its POSITIVE counterpart (a role that SHOULD reach the surface gets through, per L-008); S-12's remaining half closed — state-changing GET and missing CSRF; full suite green | **yes** — session-start freshness boot from `docs/playground.md` verbatim: admin **302**, login **200**, `config/.encryption_key` **403**, MCP **401** unauthenticated, **177 tools** authenticated (counted via the documented `.playground-access` recipe, matching the slice-0 baseline exactly). The escalation tests are themselves real HTTP against a real `php -S` server on the seeded playground | **This slice IS the security proof.** Each of the six findings now fails a named test if it regresses. Two live defects closed: **S-06 residue** — `api/tasks.php` did not re-gate `update`/`complete` at `tasks.manage` while its page twin does (`tasks.php:38`), so an editor was refused via the UI and allowed via the API; and **S-12** — POST + CSRF now required on the RSA private-key export, with the caller retargeted because the old 302 redirect *was* what made it a GET. Three of the new tests were **proven to FAIL against the unfixed code** (200 where 405/403 required; 500 proving the editor's task action executed) before the fixes landed. Independent `code-reviewer` and `security-auditor` passes over the diff | n/a — no new UI. The one markup change is the identity-export form's `action` attribute; the button, label and surrounding structure are untouched | **yes** — no new user-facing strings. The 405/403 bodies on `download-identity.php` are operator-facing plain text on a machine endpoint, consistent with the file's existing 429/404 responses, not UI copy | **yes** — and this drove a real refactor: the HTTP harness was **extracted** from `AdminGateHttpTest` into `tests/AdminHttpTestCase.php` rather than copied for the second HTTP class. Duplicating it would have forked the three defects L-008 records (session cookie name, `proc_open` handle shape, teardown orphan check) so a later fix to one copy would silently miss the other. `klytos_require_permission()` was reused in `api/tasks.php`; **no second authorization decision point was added** (S-04 preserved) | `docs/reference/authorization.md` (API-twin re-gating rule; CSRF and step-up-authentication added to "what this does NOT cover"), `docs/04-adoption-audit.md` (**NEW-13** added; NEW-12's open half resolved), `docs/decisions.md` (D-038, D-039, D-040), `docs/lessons-learned.md` (**L-010**). No new public PHP surface — the changes are two guards, one re-gate and test infrastructure — so no `docs/api/INDEX.md` row | n/a for this slice — no new extension point. The existing `admin.gate_map`, `auth.access_denied` and `auth.capabilities` all still apply unchanged | See the evidence block below | **PASS** | **A harness defect was found and repaired mid-slice, and it is the most consequential thing here: `PlaygroundState::assertConfigNotMutated()` was INERT** — it ran after `restorePlayground()` and re-hashed the already-restored file, so it compared the snapshot against itself and had been checking nothing since slice 3 (D-039, **L-010**). Proven inert with a probe, repaired to compare decrypted content minus `scheduler_last_run`, and pinned by a permanent two-way regression test. It then paid for itself immediately: it fired on the S-12 tests against the unfixed code, independently confirming the GET really did write config, and stopped once the fix landed. Lint baseline **improved** to 199/488 (errors −2). NEW-13 recorded and deliberately NOT fixed (D-040) |
| 6 — `SafeHttp` + risky call sites | n/a — predates | Refusals for `127.0.0.1`, `[::1]`, `169.254.169.254`, a non-HTTP scheme, **and a public URL that 302-redirects to a private one**; full suite green | **yes** — session-start freshness boot (see the freshness row: the documented port was held by an unrelated container, which is itself the session's first finding, **L-011**). The redirect and endpoint tests are real HTTP against real `php -S` servers; the oEmbed tests drive the endpoint as a seeded owner exactly as an editor would | **This slice IS the security fix.** S-08 closed by `SafeHttp`, applied at 5 call sites. The finding was **wider than recorded**: the *discovered* oEmbed endpoint is attacker-controlled too and its response **is** echoed back, and every fetch followed redirects unvalidated. Proven against the unfixed code: **6 of the 8** endpoint tests failed (404 where 400 required — the 404 the endpoint returns *after* fetching), and the old transport was demonstrated following a 302 to `http://169.254.169.254/latest/meta-data/` with `CURLINFO_EFFECTIVE_URL`. Fixed in-path: `HttpClient::requestWithStream()` silently dropped `follow_redirects`. Both review subagents run over the diff | n/a — no UI written in this slice | n/a — no new user-facing strings. The refusal deliberately reuses the endpoint's existing generic `Invalid URL`, so no catalogue key was needed and no internal-network oracle was created | **yes, and it drove the shape** — the validation was **promoted** from `ImportValidator::validateUrl()`, not rewritten, and `ImportValidator` now delegates, so ONE implementation exists where there were about to be two. `SafeHttp` reuses `HttpClient` for transport rather than opening a third cURL call site. `AdminHttpTestCase` was **generalized** with a `routerScript()` hook rather than copied for the fixture server, keeping L-008's three defects in one place | `docs/reference/safe-http.md` **created** (the rule, return shape, all 5 reason codes, redirects, the oracle rule, all 4 extension points, known limits, where it is applied, tests); **6 new rows** in `docs/api/INDEX.md` with counts updated 938 → 944; `docs/04-adoption-audit.md` (S-08 CLOSED + **NEW-15**); `docs/playground.md` (bind check); `docs/decisions.md` (D-041, D-042); `docs/lessons-learned.md` (**L-011**, **L-012**) | **yes** — `http.safe.allowed_schemes` and `http.safe.max_redirects` (filters, both tested), `http.safe.redirect` and `http.safe.blocked` (actions, both tested). `http.safe.blocked` is deliberately an action, not a filter, so it cannot reverse a refusal | See the evidence block below | **PASS** | **A second harness defect found, in the L-010 shape: the integration tier never reset hooks** while the unit tier always had, so a filter registered by one test leaked into every later test in the process (D-042, **L-012**). Nothing was passing for the wrong reason *yet*; the next weakening filter would have been. Caught by asserting on the refusal REASON rather than just the refusal. Lint baselines **improved**: core+admin 199 → **193**, plugins 131 → **129**. **NEW-15 recorded and deliberately NOT fixed:** DNS rebinding survives, because the address is resolved to validate and resolved again to connect — stated plainly in the reference doc rather than implied away |
| 7 — Public comments, off the admin path | n/a — predates | Anonymous submission succeeds; honeypot rejects a bot; rate limit holds **ACROSS sessions**; **no admin-directory name in any frontend-reachable URL** | **yes** — session-start freshness boot (port 8080 held by an unrelated container again, so a verified-free port was used, per L-011); the four criteria were then walked for real with `curl` against the playground, and the accented submission round-tripped intact | **This slice IS the security fix.** S-09 closed by RELOCATION, not by the recorded remediation: the handler left the admin tree entirely rather than being exempted from its auth guard. The D-036 question was asked *before* acting and changed the design — `admin/bootstrap.php` runs cron and the action scheduler on every request (`bootstrap.php:184-196`), so the recorded fix would have handed every anonymous caller a scheduler trigger. Input bounds added to `CommentManager::submit()` because it is now anonymously reachable. Both review subagents run; the `security-auditor` returned a **blocking** finding that restructured the slice (rate limit ran AFTER `App::boot()`, and the honeypot ran BEFORE the rate limit, so a `_honeypot` flood was never counted) | n/a — no UI written in this slice | **yes** — a new `comments` domain with **11 keys** added to **all 20 catalogues**; three hardcoded English validation messages in `submit()` converted to `__()` after the code review flagged that they reach anonymous callers verbatim. The `405`/`500` paths keep literals by necessity — they fire before I18n exists (**NEW-18**) | **yes** — the persistent IP-keyed `MCP\RateLimiter` was **reused**, not forked, even though its fixed 60s window meant expressing the policy as a count rather than the old "1 per 30s" interval; `AdminHttpTestCase::post()` was **generalized** (nullable `$role`) rather than copied for anonymous POSTs; `SiteConfig::setValue()` was written as the counterpart to the existing `getValue()`. The install-root discovery loop IS duplicated with `x402-gate.php` and is recorded as deliberately not extracted, with a trigger | `docs/reference/public-comments.md` **created**; **5 rows** changed/added in `docs/api/INDEX.md` with counts 944 → 948; `docs/04-adoption-audit.md` (S-09 CLOSED + **NEW-16…NEW-20**); `docs/playground.md` (new try-it section); `docs/decisions.md` (**D-043**, amended after its review cycle); `docs/lessons-learned.md` (**L-014**); stale `api/comment-submit.php` row removed from `README.md` | **yes** — `comment.rate_limit` and `comment.notification_recipient` (filters), `comment.honeypot_rejected` and `comment.rate_limited` (actions). Both are actions, not filters, so a listener cannot turn a refusal into an acceptance | See the evidence block below | **PASS** | **The named finding was the shallowest of three.** Underneath it: `SiteConfig::setValue()` **did not exist** although the MCP tool calls it four times, so comments could never be switched on at all (**NEW-16**, fixed in path); and **no comment form exists in the generated output**, which is deliberately still true at the end of this slice (D-023 owns it) and is said plainly in the reference doc. Lint baselines held exactly at 193/488 and 113/109; `installer/public/` was found to be **outside the phpcs scan set entirely** and is now scanned, at 0/0. Recorded and NOT fixed: **NEW-17** (proxy collapses the rate limit into one bucket), **NEW-18** (no `__()` outside the admin bootstrap), **NEW-19**, **NEW-20** (limiter race — carried as *plausible and unproven*, because the concurrency test that would settle it was not run) |
| 8 — HSTS + CSP fail-open + hardening | n/a — predates | Headers asserted on a REAL playground response; admin renders with the tightened CSP, browser console clean | **yes** — headers read off real `php -S` responses including the 401 and 403 refusals; headless Chrome on `login.php` reported 0 CSP violations; a CSP-conformance sweep ran clean across 18 admin pages | **This slice IS the security fix.** S-11, the CSP fail-open and NEW-14 closed with ONE enforcement point in `admin/bootstrap.php` covering all 64 entry points. Five named probes proved every new test fails against the unfixed code. Both review subagents run | n/a — no new UI; the nonce attributes are non-visual | n/a — no new user-facing strings | **yes** — `Helpers::isHttps()` became the single TLS check, replacing four duplicated expressions | `docs/reference/security-headers.md` created; 3 rows in `docs/api/INDEX.md`; audit, decisions (**D-044**), lessons (**L-015**) | **yes** — `security.hsts` filter, with its rollback risk stated | See the slice-8 evidence block below | **PASS** | Row filled retrospectively in slice 9 — it was left `pending` when slice 8 closed, although its evidence block was written. The gap is recorded rather than quietly corrected: a table that says `pending` for a closed slice is the L-002 defect in the project's own test log |
| 9 — `keel-verify` + regenerable INDEX | n/a — predates | `scripts/keel-verify` runs and its **FULL OUTPUT** is pasted below; every new check demonstrated to FAIL on an injected violation and pass once reverted | **yes** — the release archive was extracted from a real `git archive` and served over HTTP to test whether shipped dev scripts execute; both guards then re-verified against the edited files, and `router.php` re-verified as still working as the `php -S` router (admin 302) | **This slice found and fixed a live exposure.** `scripts/` is not export-ignored, so it ships to the site root, and the root `.htaccess` serves any existing file (`:23-25`). Verified over HTTP against an extracted archive: `router.php` **executed**, returning an internal 404 page disclosing the admin path, the MCP endpoint and build internals; `upgrade-assert.php` **executed**, HTTP 200 / 1332 bytes. Both now carry SAPI guards (their sibling `seed-playground.php:35` already had one). Recorded as **NEW-28**; the packaging half is Phase 7's | n/a — no UI written in this slice | **yes, as a CHECK rather than as strings** — no new user-facing strings, but the slice adds the catalogue key-parity check across **120 files in 6 sets** (core's 20 + 5 plugins' 20 each), which is the real i18n invariant here (D-006). Proven by injecting both a missing key and an extra one | **yes** — no new product surface. The gate check and the two WARN checks reuse `git check-attr`, which is the same authority that builds the release archive, rather than a second hand-maintained list of what ships | `docs/keel-verify.md` **created** (deliberately at `docs/` root, not `docs/reference/` — it is project tooling like `docs/playground.md`, not a product surface, and INDEX's own scope is `installer/` only); `docs/flows/` **created** with 3 files; `docs/04-adoption-audit.md` (**NEW-27**, **NEW-28**); `docs/decisions.md` (**D-045**); `docs/lessons-learned.md` (**L-016**) | n/a — dev tooling and CI, no product extension points | See the slice-9 evidence block below (commit `e8e3938`) | **PASS** | **Two findings, both from defining "distributable" honestly rather than assuming it. NEW-27:** all 16 in-product guides under `installer/core/guides/` are stripped from every release archive by the blanket `*.md export-ignore` — verified by extracting the archive, where the directory survives EMPTY and only 2 `.md` files remain repo-wide. They back `klytos_list_guides` / `klytos_get_guide`, whose own tool descriptions declare several REQUIRED before creating content, so on a released install those tools return nothing. **NEW-28:** the dev-script exposure above. Both are H-02's rule reaching further than H-02 recorded; both are made **detectable now** by WARN checks, with the fix left to Phase 7 — the same treatment sprint-1.md scoped for H-01 |

### Slice 0 — evidence (commands and output, 2026-07-18)

Commit: `622d54c` — *Sprint 1 slice 0: verification playground, plus the Keel docs baseline*.

```
$ php scripts/dev/seed-playground.php --reset
Resetting playground state… / config written / user admin|editor|viewer created
(owner created by UserManager::migrateFromV1Config at boot) / MCP application
password created / page home|about|contact created / Playground ready.

$ php -r '<boot + list users/pages/config>'
  viewer role=viewer status=active     owner  role=owner  status=active
  admin  role=admin  status=active     editor role=editor status=active
  developer_mode: true        paginas: 3

$ php -S 127.0.0.1:8080 -t . scripts/dev/router.php     (backgrounded)
  admin  302 -> /installer/admin/login.php?redirect_to=%2Finstaller%2Fadmin%2F
  login  200
  mcp (no auth)  401
  config/.encryption_key  403
  tools/list (Basic owner:<app password>)  ->  177 tools

$ phpcs --standard=phpcs.xml scripts/dev/
  (no output — 0 errors, 0 warnings)

$ php installer/cli.php status|pages|logs
  Klytos v0.31.1-beta.1 / Paginas: 3 / 3 pages listed / "No hay archivos de log."
  (note: CLI output is unaccented Spanish — audit I-04 confirmed live)
```

**Gate zero — 2 of 3 met.** `03-technical-plan.md:108` defines it as *phpcs clean + the app boots +
one MCP `tools/list` round trip*. The app boots and MCP answers with 177 tools. **`phpcs` is not
clean and never was:**

```
$ phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
A TOTAL OF 204 ERRORS AND 488 WARNINGS WERE FOUND IN 114 FILES
PHPCBF CAN FIX 203 OF THESE SNIFF VIOLATIONS AUTOMATICALLY
```

The 204 errors are a **pre-existing baseline**, not introduced by this slice — both files added here
lint clean. **Resolved by D-025:** gate zero's lint condition is baseline-locked — zero violations in
the files a slice touches, and the 204/488 baseline may not grow. Re-measured at every sprint close.
With that amendment, **gate zero is met** and slice 0 closes.

### Slice 1 — evidence (commands and output, 2026-07-19)

Commit: `585ec68` — *Sprint 1 slice 1: two-tier test harness (T-01) + dev-only composer manifest (D-022)*.

```
$ composer install --no-interaction
  28 packages installed — phpunit/phpunit 11.5.56, squizlabs/php_codesniffer 3.13.5
  Generating autoload files          (no errors, no warnings)

$ XDEBUG_MODE=off vendor/bin/phpunit
  PHPUnit 11.5.56 / PHP 8.3.12 / Configuration: phpunit.xml
  .........                                                    9 / 9 (100%)
  OK (9 tests, 37 assertions)        [4 unit + 5 integration]

$ vendor/bin/phpcs --standard=phpcs.xml tests/
  ..... 5 / 5 (100%)                 (0 errors, 0 warnings)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
  A TOTAL OF 204 ERRORS AND 488 WARNINGS WERE FOUND IN 114 FILES
  → D-025 baseline UNCHANGED (was 204/488 in 114 files on 2026-07-18)
```

**The tier boundary was verified, not assumed.** The two files `App::isInstalled()` checks for were
temporarily moved aside and the suite re-run, to prove the integration tier cannot pass without its
fixture:

```
$ mv installer/config/.encryption_key{,.bak}; mv installer/config/config.json.enc{,.bak}
$ XDEBUG_MODE=off vendor/bin/phpunit
  ....SSSSS                                                    9 / 9 (100%)
  OK, but some tests were skipped!
  Tests: 9, Assertions: 9, Skipped: 5.
  → the 4 unit tests pass on a bare checkout; all 5 integration tests SKIP with the reseed command.
$ (files restored)  XDEBUG_MODE=off vendor/bin/phpunit
  OK (9 tests, 37 assertions)
```

This matters more than the green run: a harness whose authorization tier passes silently when it
never executed would make every refusal test in slices 3–5 worthless. It refuses.

**Independent review (`code-reviewer` subagent, before the commit).** Verdict: no blocking findings;
the session seam was verified line-by-line against `auth.php:129-136` and `:183-188` and found to
match exactly. Six findings, every one resolved in this slice:

| # | Finding | Resolution |
|---|---|---|
| 1 | `composer lint` runs the unscoped ruleset, which includes `installer/plugins` — a path D-025 never measured. A contributor could not tell pre-existing noise from a regression they introduced | **Measured and recorded**: plugins **131/109 in 25 files**, whole ruleset **335/597 in 139 files** (`03-technical-plan.md` §4). All three paths now locked. D-025's decision is unchanged — its measurement simply covered 2 of the 3 ruleset paths |
| 2 | `requirePlayground()` re-implemented `App::isInstalled()` by hand — a near-duplicate, the exact defect L-004 records | **Fixed**: calls `App::getInstance()->isInstalled()`. Verified safe pre-`boot()` — it reads only `$configPath`, set in the constructor (`app.php:680-684`) |
| 3 | No storage isolation between integration tests. Harmless now (tests are read-only) but slices 3–5 will write state through this seam | **Not fixed — deliberately deferred with a trigger** (before slice 3). Building a rollback primitive nothing yet uses would be speculative; the trap is recorded in `PROGRESS.md` and in the sprint's risks so slice 3 cannot walk into it |
| 4 | Dead `<exclude>` in `phpunit.xml` — `vendor-ai/` is a *sibling* of `core/`, never in the included set | **Fixed**: removed, with a comment recording the layout so it is not re-added |
| 5 | `phpcs.xml` missing from `export-ignore` while the rest of the toolchain was added here | **Fixed**: added |
| 6 | `phpcs.xml` comment said "warn at 120" while `lineLimit` is 150 | **Fixed**: comment corrected (the value was right) |

One lint violation WAS introduced and fixed rather than tolerated: `tests/bootstrap.php` tripped
`PSR1.Files.SideEffects.FoundWithSymbols` (a bootstrap loads the autoloader *and* defines constants,
by nature). Fixed with a path-scoped exclusion in `phpcs.xml` carrying its justification — the same
pattern the ruleset already uses for `chat-engine.php` and the helper files — not by loosening the
rule globally.

### Slice 2 — evidence (commands and output, 2026-07-19)

Commit: `d406e1f` — *Sprint 1 slice 2: vendor-ai manifest + first CVE audit (H-04, D-028)*.

**The tree, established from Composer's own record — not from counting directories.**
`installer/vendor-ai/composer/installed.json` and `installed.php` were already present, so the
manifest was *derived*, never guessed. The dependency graph has exactly **one** genuine root
requirement; the other 15 packages are transitive:

```
$ php -r '<read installed.json; invert the require graph>'
  packages: 16
  soukicz/llm            0.5.0      php:>=8.3     *** ROOT CANDIDATE ***
  guzzlehttp/guzzle      7.10.0     php:^7.2.5||^8.0   <- soukicz/llm
  guzzlehttp/psr7        2.9.0                          <- guzzlehttp/guzzle, soukicz/llm
  brick/math             0.14.8     php:^8.2            <- ramsey/uuid
  ramsey/collection      2.1.1      php:^8.1            <- ramsey/uuid
  … 11 more, every one reachable from soukicz/llm
```

Two counts in the original H-04 finding were wrong and are corrected on the record: **16 packages,
not 9**, and 482 is the **tracked** count (`git ls-files | wc -l`) while 484 exist on disk — the two
extras are gitignored package-internal files (`guzzle/package-lock.json`,
`swaggest/json-schema/composer.lock`).

**The manifest resolves, and resolves to exactly what ships.**

```
$ composer validate --no-check-publish -d installer
  (warnings only: "exact version constraints should be avoided" — intentional, see D-028)

$ composer update --no-install --no-interaction -d installer
  Lock file operations: 16 installs, 0 updates, 0 removals
  - Locking brick/math (0.14.8)          - Locking ramsey/collection (2.1.1)
  - Locking guzzlehttp/guzzle (7.10.0)   - Locking ramsey/uuid (4.9.2)
  - Locking guzzlehttp/promises (2.3.0)  - Locking soukicz/llm (0.5.0)
  - Locking guzzlehttp/psr7 (2.9.0)      - Locking swaggest/json-diff (v3.12.1)
  - Locking phplang/scope-exit (1.0.0)   - Locking swaggest/json-schema (v0.12.43)
  - Locking psr/http-client (1.0.3)      - Locking symfony/deprecation-contracts (v3.6.0)
  - Locking psr/http-factory (1.1.0)     - Locking symfony/polyfill-mbstring (v1.33.0)
  - Locking psr/http-message (2.0)       - Locking ralouphie/getallheaders (3.0.3)
  Writing lock file
  Found 5 security vulnerability advisories affecting 2 packages.

$ git status --porcelain installer/vendor-ai
  (empty — the resolution wrote a lock and touched nothing vendored)
```

**A first attempt failed, and the failure is part of the record.** With Composer 2.9's default
`audit.block-insecure`, the resolver refused outright — *"found guzzlehttp/guzzle[7.10.0] but these
were not loaded, because they are affected by security advisories"*. The manifest could not describe
reality precisely **because** reality is vulnerable. Resolved by setting `block-insecure: false` in
the manifest (D-028): recording what ships is the manifest's job; reporting on it is
`composer audit`'s.

**`composer audit` — full output, 5 advisories / 2 packages, all severity *medium*.**

```
$ composer audit --no-interaction -d installer          (exit code 1)
Found 5 security vulnerability advisories affecting 2 packages:

| Package | guzzlehttp/guzzle | Severity medium | PKSA-93qv-9n9h-6k6p | CVE-2026-55767
| Title   | Dot-only cookie domains match all hosts
| URL     | https://github.com/guzzle/guzzle/security/advisories/GHSA-cwxw-98qj-8qjx
| Affected versions <7.12.1                      | Reported 2026-06-18T14:12:49+00:00

| Package | guzzlehttp/guzzle | Severity medium | PKSA-k22t-f949-t9g6 | CVE-2026-55568
| Title   | Silent HTTPS proxy downgrade to cleartext
| URL     | https://github.com/guzzle/guzzle/security/advisories/GHSA-wpwq-4j6v-78m3
| Affected versions <7.12.1                      | Reported 2026-06-18T14:12:49+00:00

| Package | guzzlehttp/psr7   | Severity medium | PKSA-7qs6-zvnz-h66r | CVE-2026-55766
| Title   | CRLF injection in HTTP start-line serialization
| URL     | https://github.com/guzzle/psr7/security/advisories/GHSA-vm85-hxw5-5432
| Affected versions <2.12.1                      | Reported 2026-06-18T09:49:37+00:00

| Package | guzzlehttp/psr7   | Severity medium | PKSA-gm5x-j3mz-71n9 | CVE-2026-49214
| Title   | CRLF injection via URI host component
| URL     | https://github.com/guzzle/psr7/security/advisories/GHSA-hq7v-mx3g-29hw
| Affected versions <2.10.2                      | Reported 2026-05-25T22:58:15+00:00

| Package | guzzlehttp/psr7   | Severity medium | PKSA-jj5t-2zs1-dcfm | CVE-2026-48998
| Title   | Host confusion via authority reinterpretation
| URL     | https://github.com/guzzle/psr7/security/advisories/GHSA-34xg-wgjx-8xph
| Affected versions <2.10.2                      | Reported 2026-05-25T22:58:15+00:00
```

**Reachability was checked, not assumed** — this is what turns five CVE rows into a triage the user
can actually decide on (full analysis in `docs/04-adoption-audit.md` NEW-05):

```
$ grep -rn "CookieJar|'cookies'" installer/core/ai installer/vendor-ai/soukicz   → no matches
$ grep -rni "proxy"              installer/core/ai installer/vendor-ai/soukicz   → no matches
$ grep -rn "custom_url|custom_endpoint|base_url|api_url" installer/core/ai installer/core/mcp/tools
                                                                                → no matches
$ sed -n '240,248p' installer/core/ai/chat-engine.php
    'anthropic' => new AnthropicClient(...)   'openrouter' => new OpenAICompatibleClient($apiKey, 'https://openrouter.ai/api/v1')
    'openai'    => new OpenAIClient(...)      'ollama'     => new OpenAICompatibleClient($apiKey, 'https://ollama.com/v1')
    'gemini'    => new GeminiClient(...)      default => throw new \InvalidArgumentException(...)
```

Four of the five CVEs need either a cookie jar or an attacker-influenced URI; this code path offers
neither. The fifth (CVE-2026-55568) is the one with a plausible path, because Guzzle honours
`HTTP_PROXY`/`HTTPS_PROXY` from the environment whether the application asks or not.

**The drift guard was proven to fail before it was trusted** — the same discipline slice 1 applied to
the tier boundary. A guard that cannot fail is decoration:

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  ............                                                  12 / 12 (100%)
  OK (12 tests, 57 assertions)          [3 new: manifest ≡ vendored ≡ lock ≡ licence notice]

# inject drift: pin brick/math at 0.14.7, delete phplang/scope-exit from the licence notice
$ XDEBUG_MODE=off vendor/bin/phpunit --filter VendorAiManifest
  1) …::testManifestPinsExactlyWhatIsVendored
     installer/composer.json pins brick/math at a version other than the one vendored.
  2) …::testEveryVendoredPackageAppearsInTheLicenceNotice
     LICENSE-THIRD-PARTY.md must list every vendored package at its vendored version.
  FAILURES! Tests: 3, Assertions: 5, Failures: 2.

# both files restored
$ XDEBUG_MODE=off vendor/bin/phpunit --filter VendorAiManifest
  OK (3 tests, 20 assertions)
```

**Lint — D-025 baseline unchanged.**

```
$ vendor/bin/phpcs --standard=phpcs.xml tests/
  ...... 6 / 6 (100%)                (0 errors, 0 warnings)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
  A TOTAL OF 204 ERRORS AND 488 WARNINGS WERE FOUND IN 114 FILES
  → unchanged (204/488/114, as on 2026-07-18 and 2026-07-19 slice 1)
```

**Distribution impact verified, not assumed.** The new files must not ship:

```
$ git check-attr export-ignore -- installer/composer.json installer/composer.lock
  installer/composer.json: export-ignore: set
  installer/composer.lock: export-ignore: set     (via the existing unanchored rules, .gitattributes:18-19)
```

The same check found NEW-07's packaging half: `installer/vendor-ai/LICENSE-THIRD-PARTY.md` is
**stripped** from release archives by the blanket `*.md export-ignore`, while every per-package
`LICENSE` file survives. Not changed here — that is H-02's `.gitattributes` policy and a Phase 7 call.

### Slice 3 — evidence (commands and output, 2026-07-19)

Commit: `bbeeb09` — *Sprint 1 slice 3: one matrix + fail-closed current user (S-04, NEW-01)*.

```
$ XDEBUG_MODE=off vendor/bin/phpunit
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.3.12
................................                                  32 / 32 (100%)
OK (32 tests, 219 assertions)
```

**The tests were proven able to fail before they were trusted.** Reverting only the two product
files and re-running the suite:

```
$ git stash push installer/core/helpers-global.php installer/core/app.php
$ XDEBUG_MODE=off vendor/bin/phpunit
1) CurrentUserFailClosedTest::testSessionWithoutUserIdIsDeniedNotPromoted
2) CurrentUserFailClosedTest::testSessionWithoutUserIdHoldsNoPermission
3) CurrentUserFailClosedTest::testSessionNamingAMissingUserIsDenied
4) PermissionMatrixTest::testTheMatrixIsDefinedExactlyOnce
FAILURES!  Tests: 31, Assertions: 208, Failures: 4.
$ git stash pop
```

Recorded honestly: `V1MigrationTest` passes both with and without the revert, because
`migrateFromV1Config()` itself was already correct and already wired into boot. Those tests verify
existing behaviour the fail-closed change depends on — they do not verify a new fix.

**Upgrade from the REAL previous version** (`Installed base: yes`, so clean-install-only does not
satisfy this). v0.30.1 is exported from git into a temp directory outside the repo, installed by
**its own installer** over `php -S`, then upgraded to the working tree:

```
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
== Klytos upgrade test: v0.30.1 -> working tree
-- 1. Exporting v0.30.1 from git ............ VERSION on disk: 0.30.1
-- 2. Running the v0.30.1 installer ......... installer responded 200
-- 3. Verifying the v0.30.1 install ......... 5/5 PASS (pre-upgrade, VERSION 0.30.1)
-- 4. Upgrading in place .................... upgraded to VERSION 0.31.1-beta.1
-- 5. Booting the upgraded install
   PASS  the migration is idempotent on an upgraded install (found 1)
   PASS  the upgraded owner still resolves after the fallback removal
   PASS  the upgraded owner still holds owner-only permissions
   PASS  a session without klytos_user_id is DENIED, not promoted (NEW-01)
   PASS  that session holds no owner permission
   PASS  that session holds no permission at all
-- 6. Breaking the install so the boot migration must fail ... 4/4 PASS
-- 7. Booting the broken install (this used to fatal on every request)
Klytos: v1.x owner migration failed — this install has no owner record, so every
permission check will deny until one exists. Underlying error: Cannot migrate:
admin_email is missing or invalid in config.
   PASS  App::boot() completed without a fatal
   PASS  boot left the install with no owner, rather than half-creating one
   PASS  no session is promoted into the missing-owner gap
   PASS  the surviving app denies owner-only permissions
   PASS  the surviving app denies every permission
== UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)
```

Steps 6 and 7 exist **because the `code-reviewer` pass found the Step 10b `try`/`catch` had no test
that drove execution through it** — a structural fix with no test is an unverified claim by this
project's own rule. They were then proven to detect its absence, the same way the isolation
primitive was:

```
$ git stash push installer/core/app.php     # remove the try/catch
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
PHP Fatal error:  Uncaught RuntimeException: Cannot migrate: admin_email is missing
or invalid in config.  in .../core/user-manager.php:658
Stack trace:
#0 .../core/app.php(360): Klytos\Core\UserManager->migrateFromV1Config(Array)
#1 .../scripts/dev/upgrade-assert.php(76): Klytos\Core\App->boot()
$ git stash pop
```

**Storage isolation (D-030), proven before the slice relied on it.** The rollback primitive is what
makes `V1MigrationTest` safe, since it deletes the seeded owner:

```
$ XDEBUG_MODE=off vendor/bin/phpunit --testsuite integration   # with isolation DISABLED
1) PlaygroundIsolationTest::testBPlaygroundIsRestoredForTheNextTest
The deleted user is still missing — the playground was NOT rolled back, and every later
authorization test is running against a mutated fixture.
FAILURES!  Tests: 7, Assertions: 32, Failures: 1.
```

The proof run really did delete the seeded `editor`; the playground was reseeded immediately after
(`php scripts/dev/seed-playground.php --reset`), and the suite then ran green twice in a row with
`git status --porcelain installer/` empty.

**Lint (D-025).** Zero violations introduced. The three pre-existing auto-fixable errors in
`app.php` were taken under D-025's opportunistic rule, so the baseline **decreased**:

```
$ vendor/bin/phpcs --standard=phpcs.xml --report=summary \
    installer/core/helpers-global.php installer/core/app.php installer/core/user-manager.php tests/ scripts/dev/
A TOTAL OF 0 ERRORS AND 8 WARNINGS WERE FOUND IN 3 FILES

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
A TOTAL OF 201 ERRORS AND 488 WARNINGS WERE FOUND IN 114 FILES     (was 204 / 488)
```

Remaining warnings are line-length in `app.php` (pre-existing) and `PSR1.Files.SideEffects` on
`upgrade-assert.php` — the same class already exempted for `tests/bootstrap.php`: it is a dev entry
point that must both define a helper and execute.

**Independent review (Phase 5 §2 test point, item f).** Two subagents on the slice diff.
`security-auditor`: **no blocking findings** — traced every caller of `klytos_current_user()`,
`Auth::getUserId()` and direct `$_SESSION['klytos_user_id']` reads and found no residual path to
privilege; confirmed display-only callers degrade safely on `null`; confirmed the new log calls carry
no secret, hash or personal data; confirmed the temp-dir scripts cannot delete outside their `mktemp`
directory. It also sharpened **NEW-02** with a finding on adjacent code
(`ai/chat-engine.php:401-421` filters the AI tool list only for exactly `viewer`/`editor`, so any
unrecognized role falls through unfiltered — fail-open, and Sprint 2's enforcement point must
default-deny instead). `code-reviewer`: **one blocking finding** (the untested `try`/`catch`, fixed
above) plus three non-blocking, of which two were applied (the role fail-closed tightening and its
test; the wrong boot-step number in a comment) and one was noted as a nit (`klytos_has_permission()`
constructs `UserManager` twice per call via `klytos_current_user()`).

### Slice 4 — evidence (commands and output, 2026-07-19)

Commit: `6e293f3` — *Sprint 1 slice 4: klytos_require_permission() + central default-deny gate (S-07)*.

**The load-bearing assumption was verified before the design relied on it.** A central gate only
gates what passes through it:

```
$ for f in installer/admin/*.php installer/admin/api/*.php; do
    grep -qE "require(_once)?.*bootstrap\.php" "$f" || echo "NO BOOTSTRAP: $f"; done
  NO BOOTSTRAP: installer/admin/bootstrap.php      ← itself, correctly
  (no other output — all 65 remaining surfaces require it)
```

**Full suite green.**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.3.12
..................................................                50 / 50 (100%)
OK (50 tests, 298 assertions)          [was 32 / 219 at slice 3: +18 tests, +78 assertions]
```

**The tests were proven able to fail before they were trusted.** Reverting the ONE file that wires
the gate in — `admin/bootstrap.php` — and re-running:

```
$ git stash push installer/admin/bootstrap.php
$ XDEBUG_MODE=off vendor/bin/phpunit --filter AdminGateHttp
There were 5 failures:
1) testUnauthenticatedApiRequestGetsJson401
   An anonymous API call must be 401, not 302.  Failed asserting that 302 is identical to 401.
2) testApiEndpointRefusesWithJson403
   A viewer must not reach the plugin API.      Failed asserting that 405 is identical to 403.
3) testAdminPageRefusesWithHtml403
   A viewer must not reach user management.     Failed asserting that 200 is identical to 403.
4) testAnUnmappedAdminFileIsDeniedEvenToTheOwner
   Failed asserting that 200 is identical to 403.
5) testPerRoleAccessMatrixAcrossRepresentativeSurfaces
   installer/admin/users.php as admin:  expected 403, got 200
   installer/admin/users.php as editor: expected 403, got 200
   installer/admin/users.php as viewer: expected 403, got 200
   installer/admin/plugins.php as viewer:  expected 403, got 200
   installer/admin/updates.php as viewer:  expected 403, got 200
   installer/admin/terminal.php as viewer: expected 403, got 200
$ git stash pop
```

That failure output **is** S-07, made executable: a `viewer` reaching user management, plugin
management and the core updater with 200. The sixth test
(`testUnauthenticatedPageRequestRedirectsToLogin`) passes either way, correctly — that behaviour is
deliberately unchanged.

**`scripts/keel-verify` fails on a removed gate — required by the sprint's acceptance criterion 3,
and demonstrated rather than asserted:**

```
$ php scripts/keel-verify                      # 1. baseline
  PASS  authorization gate covers every admin surface (65 files)
  PASS  the central gate is invoked from admin/bootstrap.php
OK — 2 check(s) passed.                        exit=0

# 2. remove ONE entry ('users.php' => 'users.manage') from the gate map
$ php scripts/keel-verify
  FAIL  authorization gate covers every admin surface (65 files)
          - admin/users.php has no entry in klytos_admin_gate_map() — it is denied to
            everyone by default. Map it deliberately in installer/core/admin-gate.php.
  PASS  the central gate is invoked from admin/bootstrap.php
FAILED — 1 problem(s) across 2 check(s).       exit=1

$ php scripts/keel-verify                      # 3. entry restored
OK — 2 check(s) passed.                        exit=0
```

The second check exists because a complete map enforces nothing if nobody calls the enforcer — the
difference between a gate and a spreadsheet about gates.

**Full 65-surface × 4-role walk over real HTTP (260 requests).** Sprint risk 1 is "default-deny can
lock someone out", so the walk checks both directions. Representative rows:

```
SURFACE                           owner  admin editor viewer
users.php                          200   403   403   403
plugins.php                        200   403   403   403
updates.php                        200   403   403   403
terminal.php                       200   403   403   403
privacy.php                        200   403   403   403
setup-wizard.php                   302   403   403   403     ← NEW-10 closed
mcp.php                            200   200   403   403
settings.php                       200   200   403   403
theme.php                          200   200   403   403
ai-chat.php                        200   200   403   403     ← ai.use excludes editor (D-035)
logs.php                           200   200   403   403     ← was a 302 redirect, now a real 403
analytics.php                      200   200   200   403
page-editor.php                    200   200   200   403
index.php                          200   200   200   200     ← no lockout: dashboard for all
pages.php                          200   200   200   200
profile.php                        200   200   200   200     ← self-service tier
security.php                       200   200   200   200
api/notices.php                    200   200   200   200
api/download-identity.php          200   403   403   403     ← was a FATAL for everyone
api/plugins.php                    405   403   403   403
api/update-install.php             405   403   403   403
api/webauthn-challenge.php         400   400   400   400     ← reachable at last (NEW-09)
```

Every 403-for-`owner` elsewhere in the walk was traced and is the endpoint's **own** CSRF or 2FA
refusal on a bare GET (`api/image-edit`, `api/options-management`, `api/post-lock`,
`api/sidebar-order`, `api/translations*`, `api/terminal-autocomplete`), not the gate. No role lost a
surface its capabilities entitle it to.

**Upgrade from the REAL previous version** (`Installed base: yes`), unchanged and still green:

```
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
== Klytos upgrade test: v0.30.1 -> working tree
   … 17/17 assertions PASS, including the failing-migration boot containment
== UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)
```

**Lint (D-025) — baseline unchanged, zero violations introduced.** Measured against the same files
at `HEAD` rather than assumed:

```
$ vendor/bin/phpcs --standard=phpcs.xml --report=summary <the 11 touched admin files, at HEAD>
A TOTAL OF 37 ERRORS AND 43 WARNINGS WERE FOUND IN 11 FILES     ← before
$ vendor/bin/phpcs --standard=phpcs.xml --report=summary <the same 11 files, after>
A TOTAL OF 37 ERRORS AND 43 WARNINGS WERE FOUND IN 11 FILES     ← after: identical

$ vendor/bin/phpcs --standard=phpcs.xml installer/core/admin-gate.php scripts/keel-verify tests/
  (0 errors, 0 warnings — every file this slice CREATED is clean)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
A TOTAL OF 201 ERRORS AND 488 WARNINGS WERE FOUND IN 114 FILES  ← D-025 baseline UNCHANGED
```

**The i18n additions are purely additive** — the encoder was proven to reproduce all 20 catalogues
byte-for-byte BEFORE any key was inserted, so the diff is the new keys and nothing else:

```
$ php <add-gate-keys>
Encoder reproduces all 20 catalogues byte-for-byte.
Updated 20 catalogues.
$ git diff --stat installer/core/lang/
 20 files changed, 120 insertions(+), 40 deletions(-)   [4 keys x 20, plus the comma re-punctuation]
```

**Independent review (Phase 5 §2 test point, item f) — both subagents returned BLOCKING findings.**

`security-auditor` — **1 blocking.** The `api/webauthn-challenge.php` auth-guard exemption added
earlier in this slice opens a **full account-takeover primitive**. Verified against source before
being accepted, not taken on the reviewer's word: `is2faPending()` is true after a correct
**password alone** (`auth.php:112-118`); the endpoint gates all four of its actions on
`( isAuthenticated() || is2faPending() )`; and `TwoFactor::completePasskeyRegistration()`
(`two-factor.php:507-530`) enrols the credential and sets `enabled = true` **without checking any
existing factor**, silently. The exemption was **reverted** (D-036). It also bought nothing —
`TwoFactor::verifyPasskeyAssertion()` (`two-factor.php:586`) has **zero call sites** and
`login.php:54-99` has no `passkey` branch, so the legitimate flow could not complete either way:

```
$ grep -rn "verifyPasskeyAssertion" installer/core installer/admin
installer/core/two-factor.php:586:    public function verifyPasskeyAssertion(     ← definition only
```

Four non-blocking findings were recorded rather than actioned, each already owned by a later slice:
`SCRIPT_NAME` in the pre-auth list (defense-in-depth; the gate re-derives from `SCRIPT_FILENAME`
regardless), refusals shipping without security headers (slice 8, S-11), `download-identity.php`'s
docblock claiming protections that do not exist (slice 5, S-12), and `api/oembed.php`'s SSRF
(slice 6, S-08).

`code-reviewer` — **blocking: a second decision point survived, and six changes shipped untested.**
The leftover was `security.php`'s `in_array( $userRole, ['owner','admin'] )` guarding the
Encryption & Recovery section's **visibility** — the first pass converted the four POST branches and
missed the markup one, while this slice's own `docs/reference/authorization.md` claimed "slice 4
removed the last of those". Fixed, and now pinned by `testEncryptionSectionIsHiddenBelowItsTier`.
The untested-change finding was upheld in full: the HTTP test class grew from 6 tests to 13, adding
the three de-gated files and `security.php`/`tasks.php` to the per-role matrix, the privileged-POST
re-gates (with their positive case), the plugin-page denial driven through a real activated plugin,
and the identity export.

**The added tests were proven able to fail, and one of them found a further bug while being proven.**

```
$ git stash push installer/admin/{security,plugin-page,index,pages,tasks}.php \
      installer/admin/api/download-identity.php
$ XDEBUG_MODE=off vendor/bin/phpunit --filter AdminGateHttp
1) testPluginPageDeclaringNoCapabilityIsRefused
   A plugin page declaring no capability must be refused, even to the owner.
   Failed asserting that 200 is identical to 403.
2) testPrivilegedPostBranchesRefuseTheViewTierRole
   viewer must be refused the privileged POST branch of installer/admin/index.php.
   Failed asserting that 200 is identical to 403.
$ git stash pop
```

Recorded honestly: `testEncryptionSectionIsHiddenBelowItsTier` does **not** fail against the old
hand-rolled check, because `in_array( $role, ['owner','admin'] )` and
`klytos_has_permission( 'site.configure' )` resolve to the same set — that fix removed a second
decision point without changing behaviour, and the test guards the behaviour going forward rather
than proving the fix.

`testIdentityExportIsOwnerOnlyAndNoLongerFatals` initially could not fail either, and that mattered:
the fatal returns **HTTP 200** with the error in the body, so the first version's status-only
assertion passed against completely broken code. Re-asserted on the body — and it immediately
exposed a **second** non-existent method in the same file that the first fatal had been masking
(NEW-12, lesson **L-009**):

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter IdentityExport   # after fixing only isLoggedIn()
1) testIdentityExportIsOwnerOnlyAndNoLongerFatals
   Fatal error: Uncaught Error: Call to undefined method Klytos\Core\Logger::log()
   in .../api/download-identity.php:102

$ git stash push installer/admin/api/download-identity.php     # proof against the original
1) testIdentityExportIsOwnerOnlyAndNoLongerFatals
   Fatal error: Uncaught Error: Call to undefined method Klytos\Core\Auth::isLoggedIn()
   in .../api/download-identity.php:35
```

### Slice 5 — evidence (commands and output, 2026-07-19)

Commit: `815d3c8` — *Sprint 1 slice 5: named escalations, one test each (S-01, S-02, S-03, S-05, S-06, S-12)*.
Committed cleanly through the confidential-data pre-commit gate; **no `--no-verify`**.

**1. Session-start freshness — the playground, from `docs/playground.md` verbatim.**

```
$ php scripts/dev/seed-playground.php
$ php -S 127.0.0.1:8080 -t . scripts/dev/router.php &
admin/ (expect 302): 302
login.php (expect 200): 200
config/.encryption_key (expect 403): 403
MCP unauthenticated (expect 401): 401

$ APPPW=$(grep -A1 "application password" installer/config/.playground-access | tail -1 | tr -d ' ')
$ curl -s -u "owner:$APPPW" -X POST http://127.0.0.1:8080/installer/mcp -d '{...tools/list}'
authenticated tools/list count: 177
```

**2. The named tests, PROVEN TO FAIL against the unfixed code.** This is the step that makes them
evidence rather than decoration. Run before any product change in this slice:

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter NamedEscalationsTest
FAILURES!  Tests: 9, Assertions: 40, Failures: 7.

2) NamedEscalationsTest::testS06TaskApiRegatesManageActionsLikeItsPage
   S-06 REGRESSION: an editor performed 'complete' through the task API.
   Failed asserting that 500 is identical to 403.
3) NamedEscalationsTest::testS12IdentityExportRefusesAStateChangingGet
   Failed asserting that 200 is identical to 405.
5) NamedEscalationsTest::testS12IdentityExportRequiresCsrf
   Failed asserting that 200 is identical to 403.
```

Read carefully, each failure is a distinct proof:

- **500, not 403, on the task API** — the editor did not merely reach the endpoint, the handler
  *executed* and threw on a non-existent task id. A 403 expectation meeting a 500 is proof the
  authorization branch was never consulted.
- **200 where 405 was required** — the state-changing GET, confirmed live rather than inferred from
  the audit text.
- **200 where 403 was required** — no CSRF check anywhere in the file, confirmed the same way.

Failures 4, 6 and 7 in the same run were the **repaired config-mutation guard** firing on the three
S-12 tests, which is independent confirmation that the GET really did write config
(`identity_last_downloaded_at`, `identity_download_count`). They disappeared once the fix landed,
because a refused request writes nothing. Nobody had to assert that; the guard reported it.

One failure was the TEST's fault, not the product's, and is recorded rather than quietly corrected:
`sidebar-order.php` calls `klytos_verify_csrf()` unconditionally at `:39`, before anything else and
regardless of method, so a bare GET is refused **403 for a missing token** — indistinguishable from a
gate refusal by status alone. The first version of the test read that as an authorization defect that
did not exist. It now sends a valid token, so a surviving 403 could only come from the gate. This is
L-008's rule applied in the other direction: suspect the harness before the product.

**3. After the fixes — all nine green.**

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter NamedEscalationsTest
OK (9 tests, 41 assertions)
```

**4. The config-mutation guard: proven inert, then proven live.** A throwaway probe wrote a marker
key into core config and asserted nothing:

```
# against the ORIGINAL guard
$ XDEBUG_MODE=off vendor/bin/phpunit --filter ZzProbeTest
OK (1 test, 1 assertion)          ← the guard saw a real mutation and said nothing

# against the REPAIRED guard
$ XDEBUG_MODE=off vendor/bin/phpunit --filter ZzProbeTest
FAILURES!  Tests: 1, Assertions: 2, Failures: 1.
1) ZzProbeTest::testMutatingCoreConfigShouldTripTheGuard
   This test mutated installer/config/config.json.enc while App was already booted. […]
```

The probe was deleted and replaced by permanent two-way cover:

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter PlaygroundGuardTest
OK (2 tests, 5 assertions)
```

— `testARealConfigMutationTripsTheGuard` and `testTheSchedulerHeartbeatAloneDoesNotTripTheGuard`.
Both directions, because a guard that only fails is as useless as one that only passes: the first
repair (a byte hash of the *encrypted* file) failed **ten healthy tests**, since `ActionScheduler`
writes `scheduler_last_run` on every `App::boot()` and the HTTP tests boot a server per request.

**5. Full suite, lint, gate check, upgrade path.**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
OK (60 tests, 336 assertions)                      ← was 50 tests / 298 assertions at slice 4

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
A TOTAL OF 199 ERRORS AND 488 WARNINGS WERE FOUND IN 113 FILES
                                                   ← D-025 baseline 201/488: errors IMPROVED by 2,
                                                     warnings unchanged. Baseline did not grow.
$ vendor/bin/phpcs --standard=phpcs.xml --report=summary tests/
(clean — 0 errors, 0 warnings, 16 files)

$ php scripts/keel-verify
  PASS  authorization gate covers every admin surface (65 files)
  PASS  the central gate is invoked from admin/bootstrap.php
OK — 2 check(s) passed.

$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
== UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)
```

The two lint errors removed were a pre-existing `declare( strict_types=1 )` spacing violation in
`api/download-identity.php`, fixed under D-025's opportunistic rule because the slice was already
editing that file. The one warning the slice *added* (an over-long form line in `security.php`) was
removed by hoisting the URL to a variable, rather than left to grow the baseline.

### Slice 6 — evidence (commands and output, 2026-07-19)

**Baseline before the slice** — 60 tests / 336 assertions, exactly as slice 5 left it:

```
$ composer install --no-interaction
Nothing to install, update or remove

$ XDEBUG_MODE=off vendor/bin/phpunit
OK (60 tests, 336 assertions)
```

**The four pre-flight refusals S-08's test point names, plus the reason each is refused FOR.**
Asserting the reason and not only the refusal is deliberate, and it paid for itself twice in this
slice: it caught `http://[::1]/` being refused as *unresolvable* rather than as *loopback* (because
`parse_url()` leaves the brackets on, which `gethostbynamel()` and `filter_var()` both reject), and
it is what surfaced the hook leak recorded as L-012.

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter SafeHttpTest
OK (20 tests, 48 assertions)
```

Verified empirically before relying on it, rather than assumed from the flag names — `filter_var`
with `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` on PHP 8.3.12:

```
127.0.0.1          blocked=YES        8.8.8.8            blocked=no
::1                blocked=YES        93.184.216.34      blocked=no
169.254.169.254    blocked=YES        fd00::1            blocked=YES
10.0.0.1           blocked=YES        fe80::1            blocked=YES
192.168.1.1        blocked=YES        0.0.0.0            blocked=YES
172.16.0.1         blocked=YES
```

**THE CASE THE SLICE EXISTS FOR — a public URL that 302-redirects to a private one.** Driven against
a real `php -S` fixture server answering a real 302 with a real `Location` header:

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter SafeHttpRedirectTest
OK (7 tests, 14 assertions)

  refused http://169.254.169.254/latest/meta-data/  (private_or_reserved_address, requested: http://127.0.0.1:8102/redirect-to-metadata)
  refused http://[::1]/admin                        (private_or_reserved_address, requested: http://127.0.0.1:8102/redirect-to-ipv6-loopback)
  refused file:///etc/passwd                        (scheme_not_allowed,          requested: http://127.0.0.1:8102/redirect-to-file-scheme)
  refused http://127.0.0.1:8102/redirect-loop       (too_many_redirects)
```

The one thing narrowed for this test is stated openly rather than buried: `tests/Support/
LoopbackPermittingSafeHttp.php` treats **127.0.0.1 and nothing else** as public, because a test suite
cannot own a public host and the real classifier would otherwise refuse the fixture at hop zero, so
the redirect would never be reached. Every refusal above still comes from the real, unmodified
`SafeHttp::isReservedAddress()` — `::1` is deliberately NOT exempted, and one of the tests asserts a
redirect to it is refused. Not faked: the socket, the request, the 302, the `Location` header, the
absolutization of a relative `Location`, and the hop limit. The positive control
(`/redirect-relative` → `/final` → 200 `FINAL-BODY`) is what separates "refused the dangerous hop"
from "cannot follow redirects at all" (L-008).

**PROVEN TO FAIL AGAINST THE UNFIXED CODE — the endpoint tests.** `oembed.php` alone reverted to
HEAD, `SafeHttp` left in place, so only the wiring is missing:

```
$ git stash push installer/admin/api/oembed.php
$ XDEBUG_MODE=off vendor/bin/phpunit --filter OembedSsrfTest
FAILURES!  Tests: 8, Assertions: 10, Failures: 6

  The oEmbed proxy did not refuse http://127.0.0.1/ before fetching it.
  The oEmbed proxy did not refuse http://[::1]/ before fetching it.
  The oEmbed proxy did not refuse http://169.254.169.254/latest/meta-data/ before fetching it.
  The oEmbed proxy did not refuse http://192.168.1.1/ before fetching it.
  The oEmbed proxy did not refuse file:///etc/passwd before fetching it.
  The oEmbed proxy did not refuse gopher://127.0.0.1:6379/ before fetching it.
    Failed asserting that 404 is identical to 400.
$ git stash pop
```

All six failed with **404**, which is precisely the point: 404 is what the endpoint answers *after*
it has already made the request on the server's behalf and found no oEmbed link in the response. The
two tests that still passed are the positive control and the authentication check — neither was
broken, and a proof run in which everything fails would have proved only that the file was missing.

**PROVEN: the old transport followed the redirect.** The exact `curl_setopt_array` block from the old
`fetchUrl()`, verbatim, against the same fixture:

```
OLD transport followed the 302 to: http://169.254.169.254/latest/meta-data/
redirect count: 1
SafeHttp: blocked='private_or_reserved_address' at http://127.0.0.1:8103/redirect-to-metadata
```

(SafeHttp refuses at hop 0 here because the *real* classifier is in play and the fixture is on
loopback — the per-hop check itself is what `SafeHttpRedirectTest` proves, with loopback narrowed.)

**PROVEN IN BOTH DIRECTIONS — the new hook-leak guard (L-010's rule: one direction is half a test).**
A throwaway probe with two tests, one leaking a filter through the raw API and one using the helper:

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter ZzLeakProbeTest
F.                                                                  2 / 2 (100%)

1) ZzLeakProbeTest::testLeaksAFilterOnPurpose
This test left 1 extra listener(s) on the filter "http.safe.allowed_schemes", which would leak
into every later test in this process. Register throwaway hooks with addTemporaryFilter()/
addTemporaryAction() instead of klytos_add_filter()/klytos_add_action().

FAILURES!  Tests: 2, Assertions: 3, Failures: 1
```

The leaking test failed with the intended message; the helper-using test passed in the same run. The
probe was then deleted (`tests/Integration/ZzLeakProbeTest.php` no longer exists).

**Full gate:**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
OK (95 tests, 445 assertions)

$ php scripts/keel-verify
  PASS  authorization gate covers every admin surface (65 files)
  PASS  the central gate is invoked from admin/bootstrap.php
OK — 2 check(s) passed.

$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
== UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)

$ vendor/bin/phpcs --standard=phpcs.xml <every file this slice touched> tests/
A TOTAL OF 0 ERRORS AND 2 WARNINGS WERE FOUND IN 2 FILES      (both warnings pre-existing)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
A TOTAL OF 193 ERRORS AND 488 WARNINGS WERE FOUND IN 112 FILES   (D-025 baseline: 199 → 193)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/plugins
A TOTAL OF 129 ERRORS AND 109 WARNINGS WERE FOUND IN 24 FILES    (baseline: 131 → 129)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary tests
(no findings — 0/0, unchanged)
```

**THE REVIEW CYCLE, which changed the slice materially.** Both subagents ran on the diff before the
commit, and the most serious finding came from neither of them:

```
$ php -r '...blockReason() against seven alternative notations...'
http://0177.0.0.1/                 -> private_or_reserved_address
http://2130706433/                 -> private_or_reserved_address
http://0x7f000001/                 -> private_or_reserved_address
http://127.1/                      -> private_or_reserved_address
http://[::ffff:127.0.0.1]/         -> *** ALLOWED ***          <-- LIVE BYPASS
http://expected.com@127.0.0.1/     -> private_or_reserved_address
http://127.0.0.1./                 -> host_does_not_resolve

$ curl -s -o /dev/null -w "HTTP %{http_code} from %{remote_ip}\n" "http://[::ffff:127.0.0.1]:8104/final"
HTTP 200 from ::ffff:127.0.0.1
$ curl -s "http://[::ffff:127.0.0.1]:8104/final"
FINAL-BODY
```

`filter_var`'s reserved-range flags do not understand IPv4-mapped IPv6 — `::ffff:169.254.169.254`
and `::ffff:10.0.0.1` also passed as public. The `security-auditor` raised this exact case and
reasoned past it ("very likely already handled correctly"); running it took two minutes and found the
opposite. Recorded as **L-013**. After the fix, every spelling refuses and public IPv6 still passes:

```
http://[::ffff:127.0.0.1]/  -> private_or_reserved_address     http://192.0.2.10/            -> allowed
http://[::ffff:7f00:1]/     -> private_or_reserved_address     http://[2606:4700:4700::1111]/ -> allowed
http://[::127.0.0.1]/       -> private_or_reserved_address
```

Findings acted on from the two reviews, each verified against source first:

| From | Finding | Outcome |
|---|---|---|
| security-auditor | `integrity-checker.php::httpGet()` — the SAME file's second untrusted fetch (`Integrity URL` header, same parser as the one fixed) had raw cURL, `FOLLOWLOCATION`, no address check | **Fixed.** Confirmed at `plugin-loader.php:118-125` and `integrity-checker.php:358-373` |
| both | The importer's fetchers still followed redirects unvalidated; the audit's S-08 text names them and D-041 cited `PageFetcher` **by name** as the unsound pattern while shipping it | **Fixed** — `PageFetcher::fetch()`, `fetchRobotsTxt()`, `MediaDownloader::downloadFile()`, `SitemapParser::fetchXml()` |
| security-auditor | `docs/reference/safe-http.md` claimed the importer's fetch paths were covered when only the pre-flight check was | **Fixed** — table corrected, with the check-vs-fetch distinction spelled out |
| security-auditor | `WebhookManager::create()` gave distinct messages for blocked vs malformed — an oracle reachable via an MCP tool with no permission check (NEW-02) | **Fixed** — identical message |
| code-reviewer | Dual-stack: `gethostbynamel()` reads A only, transport is dual-stack | **Fixed** — AAAA resolved too |
| code-reviewer | `absolutize()` mishandled a query-only `Location`; dot segments unnormalized | **Fixed**, with two new fixture routes and tests |
| code-reviewer | `update()` had no URL check | **Fixed** |
| code-reviewer | `tearDown()` ran the config assertion before the hook check, so a throw would skip the hook check | **Fixed** — hook check first |
| code-reviewer | `http.safe.redirect` docblock claimed the hop was followed | **Fixed** |
| code-reviewer | `PROGRESS.md` still said "next is slice 6" | Already updated before the review landed |
| both (non-blocking) | `PageFetcher`'s `CURLOPT_RESOLVE, []` labelled "DNS resolution SSRF check" is a no-op | **Removed** with the method it sat in, rather than left to reassure a reader about a protection that never existed |

After the cycle: **107 tests / 472 assertions**, `keel-verify` 2/2, upgrade passing, 0 lint errors on
every touched file, plugins baseline **131 → 113**.

Commit: **478657a**.

Suite 60 → **95 tests**, 336 → **445 assertions** at first pass, **107 / 472** after review. The six lint errors removed from
`integrity-checker.php` and the two from `ImportValidator.php` were pre-existing PSR-12 violations,
fixed under D-025's opportunistic rule because the slice was already editing both files.

### Slice 7 — evidence (commands and output, 2026-07-20)

**The defect, reproduced live before anything was changed.** The audit records a 302 to login; slice
4 changed API surfaces to JSON, so the recorded symptom was stale:

```
$ curl -s -D - -X POST http://127.0.0.1:8104/installer/admin/api/comment-submit.php \
    -d "page_slug=about&author_name=Bot&content=hello"
HTTP/1.1 401 Unauthorized
Set-Cookie: klytos_session=c5srq9t0l5hmhfdnvbapn6o8gn; path=/installer/admin/; HttpOnly; SameSite=Strict
{"error":"Authentication required","code":"authentication_required"}
```

Note the `path=/installer/admin/` and `SameSite=Strict` on that cookie, and that a **new** one was
issued on every anonymous request. That is the whole reason the old `$_SESSION['last_comment_at']`
rate limit was not a weak rate limit but no rate limit at all.

**NEW-16 proven, not inferred** — the second defect, hidden behind the first:

```
$ php -r 'require "installer/core/app.php"; $a=App::getInstance(); $a->boot();
          $c=$a->getSiteConfig(); echo implode(", ", get_class_methods($c));
          $c->setValue("comments_enabled", true);'
methods: __construct, get, set, getValue, updateBuildTimestamp
has setValue: NO
FATAL: Error: Call to undefined method Klytos\Core\SiteConfig::setValue()
```

**Every new test proven to FAIL against the unfixed code** (probes applied, run, reverted):

```
PROBE 1 — endpoint file removed:
  "An anonymous visitor could not submit a comment (S-09). Body: 404 — not found."
  Failed asserting that 404 is identical to 201.

PROBE 2 — rate limit disabled (the pre-slice per-session behaviour):
  "Six submissions from one address, each with a DIFFERENT session cookie, were all accepted.
   The rate limit is keyed on something the caller controls (S-09)."

PROBE 3 — honeypot disabled:
  Failed asserting that 201 is identical to 200.   (the bot's comment was stored)

PROBE 4 — parent_id format check removed:
  "A caller-supplied parent_id that is not a comment ID was stored verbatim."

PROBE A — honeypot restored to its pre-review position, BEFORE the rate limit:
  "Four honeypot submissions in one window were all accepted. Tripping the honeypot buys an
   attacker an uncounted request, so the rate limit can never engage. Statuses: 200, 200, 200, 200"

PROBE B — flood ceiling removed from the pre-boot path:
  "Twelve anonymous requests were served past the flood ceiling while comments were disabled,
   so the ceiling is not being enforced ahead of the boot-dependent checks.
   Statuses: 403, 403, 403, 403, 403, 403, 403, 403, 403, 403, 403, 403"

PROBE C — honeypot answering 200 again (post-review camouflage):
  "The honeypot answers a different status from a real submission, so a bot can detect the trap
   by comparing status codes."  Failed asserting that 200 is identical to 201.
```

Every probe was reverted and green restored before proceeding.

**The four test-point criteria, walked for real in the playground** (post-review code):

```
1. anonymous submission
   {"success":true,"message":"Comment submitted for moderation.","id":"8e2e…0230"}  [201]

2. honeypot — indistinguishable from the above, by design
   {"success":true,"message":"Comment submitted for moderation.","id":"6bd4…95a3"}  [201]

3. rate limit across sessions (a DIFFERENT klytos_session cookie on every request)
   req 3..12 -> 429 429 429 429 429 429 429 429 429 429

4. no admin-directory name in the frontend URL
   posted to: /comment-submit.php          (no admin segment, in any install)
   old path:  /installer/admin/api/comment-submit.php -> 404

   stored afterwards: 1
     status=pending author=Visitante content=Comentario anónimo con acentos: ñ, á, é.
```

Only the legitimate comment was stored — the honeypot request and all ten rate-limited requests
wrote nothing — and the accented content round-tripped intact, which is what the
`JSON_UNESCAPED_UNICODE` alignment was for.

**Full gates:**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
OK (116 tests, 541 assertions)

$ php scripts/keel-verify
  PASS  authorization gate covers every admin surface (64 files)
  PASS  the central gate is invoked from admin/bootstrap.php
OK — 2 check(s) passed.

$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
== UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
A TOTAL OF 193 ERRORS AND 488 WARNINGS WERE FOUND IN 112 FILES     (unchanged — D-025 holds)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/plugins
A TOTAL OF 113 ERRORS AND 109 WARNINGS WERE FOUND IN 17 FILES      (unchanged)

$ vendor/bin/phpcs --standard=phpcs.xml installer/public tests
(no output — 0 errors, 0 warnings)
```

`keel-verify` reports **64** admin surfaces, down from 65: `api/comment-submit.php` was deleted, and
its gate-map entry removed in the same change. The check's "the map names a file that does not exist
on disk" arm would have failed the build otherwise — it was left to prove that, and did.

Suite 107 → **116 tests**, 472 → **541 assertions**. Lint baselines unchanged; `installer/public/`
added to the scanned set at 0/0 after its two pre-existing errors were auto-fixed.

Commit: **b4c4c80**.

### Slice 8 — evidence (commands and output, 2026-07-20)

Closes **S-11**, the **CSP fail-open** and **NEW-14** (D-044). Opens NEW-21, NEW-22, NEW-23.

**Re-validation against source, before any code — two of the finding's own numbers were wrong.**
NEW-14 records "all 24 files in `installer/admin/api/`": there are **23** (slice 7 deleted
`comment-submit.php`, D-043). It also records that "every admin PAGE gets them, because they all
include `templates/header.php`" — false for **five** pages, which do not include it. Two of those
called `sendSecurityHeaders()` themselves; **`login.php` and `logout.php` called nothing**, so the
login form was served with no CSP, no `nosniff` and no `X-Frame-Options`. The real gap was **25
surfaces including the login form**, not 24 JSON endpoints.

```
$ find installer/admin -name "*.php" | wc -l            -> 73
$ (files not referencing bootstrap.php)                 -> 9  (bootstrap + templates/partials/includes)
                                                           => 64 entry points, matching keel-verify
$ ls installer/admin/api/*.php | wc -l                  -> 23   (audit says 24)
$ grep -rn sendSecurityHeaders installer/               -> 6 call sites, 0 under admin/api/
$ grep -rn "Strict-Transport" --include=*.php .         -> (no output)  S-11 confirmed
```

**The placement constraint, probed rather than inferred (L-006).** `Auth` does not resolve before
`App::boot()`, because `registerAutoloader()` is **Step 1 of boot** (`app.php:268`):

```
$ php -r 'require "installer/core/app.php";
          echo class_exists("Klytos\Core\Auth", true) ? "YES" : "NO";'      -> NO
$ php -r '... App::getInstance(); echo class_exists(...) ? "YES":"NO";'       -> NO
$ grep -n registerAutoloader installer/core/app.php    -> 268 (inside boot()), 738 (definition)
```

**The catch that would have broken the public site.** Failing the CSP closed everywhere would have
disabled the GDPR consent banner on every generated page — `Router::dispatch()` `readfile()`s
pre-generated HTML (`router.php:303-326`) and `build-engine.php:881` writes
`<script>ConsentManager.init(...)</script>` into it inline. A build-time file cannot carry a
per-request nonce. `installer/index.php` therefore states an explicit policy keeping
`script-src 'unsafe-inline'` (NEW-23), written as a literal so the weakening appears in a diff.

**Every test proven to FAIL against the unfixed code — five probes, each reverted after:**

| Probe (defect reintroduced) | Result |
|---|---|
| Enforcement point removed from `admin/bootstrap.php` | **6 of 11 fail** in `SecurityHeadersHttpTest` |
| HSTS emission line removed | **2 fail** in `SecurityHeadersTest` |
| CSP fallback restored to `'self' 'unsafe-inline'` | **1 fails** (`testCspFailsClosedWhenNoNonceIsSupplied`) |
| `login.php` inline `<script>` un-nonced | **1 fails** (response-level *and* source-level) |
| `login.php` mints its own nonce, diverging from the sent header | **1 fails** |

**Two false passes were caught in this slice's own test infrastructure — L-010, a third time.**

1. The first unit tests drove `sendSecurityHeaders()` and read `headers_list()`. Under the **CLI
   SAPI `header()` is a no-op and `headers_list()` returns an empty array**, so all three "the
   header is ABSENT" assertions passed against an empty string and would have passed against code
   that set no headers at all. Caught only because the *presence* assertions in the same file failed
   loudly for the same underlying reason. Repaired by splitting `Auth::buildSecurityHeaders()` out
   as a pure function: the unit tier now asserts the **policy**, the integration tier asserts it
   **reaches the wire**.
2. An integration assertion searching the whole response body for the nonce **still passed** with
   the nonce stripped from the `<script>` tag, because `login.php`'s `<style>` block carries one
   too. Tightened to match the elements themselves, then re-probed and confirmed failing.

**Headers on REAL responses** (playground on verified-free port 8321; port 8080 was again held by
the same Docker container — `Server: Apache/2.4.54 (Debian)`, L-011's tell, caught by the step-2
bind check):

```
$ curl -s -D - -o /dev/null -b "klytos_session=$SID" .../admin/api/notices.php
HTTP/1.1 200 OK
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' fonts.googleapis.com;
  font-src 'self' fonts.gstatic.com; img-src 'self' data:; script-src 'self' 'nonce-YH0Tei...';
  frame-src 'self' blob:
Permissions-Policy: camera=(), microphone=(), geolocation=()

$ curl ... /admin/login.php          -> 200 + full header set   (sent NOTHING before slice 8)
$ curl ... /admin/api/plugins.php    -> 401 + nosniff + CSP     (ordering proof: the refusal emits)
$ curl ... /admin/users.php (viewer) -> 403 + nosniff + DENY    (ordering proof: klytos_deny() emits)
$ curl ... /admin/index.php | grep -ci strict-transport -> 0    (S-11: correctly absent over http)
```

**Browser console — the test point's own criterion.** Real headless Chrome on `login.php` (the page
that gained a CSP): **0 console errors, 0 CSP violations**. For authenticated pages Chrome cannot
easily carry the HttpOnly session cookie, so a CSP-conformance check was run instead against the
real response + real header — stated as the substitute it is, not as a browser run. It parses each
page's actual CSP, then verifies every inline `<script>` is permitted by it and that no inline event
handler exists:

```
  index.php      200  mode:nonce          inline:3  clean     pages.php        200  mode:nonce  inline:4  clean
  page-editor    200  mode:unsafe-inline  inline:7  clean     templates.php    200  mode:nonce  inline:3  clean
  mcp.php        200  mode:nonce          inline:4  clean     ai-chat.php      200  mode:nonce  inline:3  clean
  terminal.php   200  mode:nonce          inline:3  clean     translations.php 200  mode:nonce  inline:4  clean
  updates.php    200  mode:nonce          inline:4  clean     users.php        200  mode:nonce  inline:4  clean
  security.php   200  mode:nonce          inline:4  clean     plugins.php      200  mode:nonce  inline:3  clean
  blocks.php     200  mode:nonce          inline:3  clean     logs.php         200  mode:nonce  inline:4  clean
  profile.php    200  mode:nonce          inline:4  clean     settings.php     200  mode:nonce  inline:5  clean
  login.php      200  mode:nonce          inline:0  clean     reset-password   200  mode:nonce  inline:0  clean

RESULT: no admin page would log a CSP violation
```

`page-editor.php` shows `mode:unsafe-inline` because it sets its own `$customCsp` — its 7 inline
scripts are permitted by its own policy, so no violation is logged. That explicit opt-out is
recorded as **NEW-21** and deliberately not fixed (user decision).

**Gates:**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
OK (138 tests, 603 assertions)                      # was 116 / 541

$ php scripts/keel-verify
  PASS  authorization gate covers every admin surface (64 files)
  PASS  the central gate is invoked from admin/bootstrap.php
OK — 2 check(s) passed.                             # exit 0

$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
== UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
A TOTAL OF 193 ERRORS AND 488 WARNINGS WERE FOUND IN 112 FILES     # baseline HELD (193/488)
$ ... installer/plugins -> 113 ERRORS AND 109 WARNINGS             # baseline HELD (113/109)
$ ... tests             -> (no output)                            # 0/0
$ ... installer/public  -> (no output)                            # 0/0
```

**Review cycle — both subagents, findings verified against source before acting (L-013).**

| Finding | Verdict | Action |
|---|---|---|
| `code-reviewer` **BLOCKING**: `PROGRESS.md` not updated | **REFUTED — stale, not wrong.** The file had been updated minutes before the agent finished; the reviews were launched while the docs were still being written. The other reviewer, in the same window, read it after the edit and said nothing | none — verified with one `grep` rather than "fixed" |
| `security-auditor`: `installer/public/comment-submit.php` and `x402-gate.php` send **no** security headers | **CONFIRMED** by grep — the only `header()` calls in either file are `Content-Type`, `Allow`, `Retry-After`. NEW-14's own shape at file N+1, on anonymous fixed-URL endpoints | recorded as **NEW-24**; the reference doc now states the slice covers admin + front controller and **not** these two, rather than overclaiming |
| `code-reviewer`: `updates.php:379,554` echo the nonce unescaped | **CONFIRMED** — all 11 other nonce sites use `klytos_esc_attr()` | **fixed** (escape-at-print-time has no exceptions, the D-043 precedent) |
| `code-reviewer`: `isHttps()` duplicates survive, incl. `bootstrap.php:195` | **CONFIRMED** — 7 copies remain, one in the file the slice was editing | `bootstrap.php:195` **fixed**; the other 6 recorded as **NEW-25** per D-031's narrowing |
| `code-reviewer`: "12 `<style>` blocks" is wrong | **CONFIRMED, and it was my error in text written this slice.** There are **10** in `installer/admin/`; the audit's 12 counts two `srcdoc`-embedded occurrences that cannot carry a nonce | **corrected** in D-044, the reference doc and the audit |
| `code-reviewer`: no extension point on anything but HSTS | **CONFIRMED** as an undocumented gap | reference doc now carries the risk paragraph D-032/D-041 use, incl. that `max-age=0` **rolls back** a cached HSTS policy, and why nothing else is filterable |
| `security-auditor`: `reset-password.php` form has no CSRF | **CONFIRMED**, exploitability genuinely low (a forged POST still needs the valid `user_id`+`token`, the secret CSRF would substitute for) | recorded as **NEW-26**, bound to the authentication slice |
| Both: ordering, nonce integrity, fail-closed CSP, `X-Forwarded-Proto` refusal | **CONFIRMED SOUND** against source by both | none |

Recorded as **L-015**: a review is a snapshot of the moment it *read*, so a finding can be stale
rather than wrong — and a number copied from another document is not a measurement.

```
$ XDEBUG_MODE=off vendor/bin/phpunit        (after the review fixes)
OK (138 tests, 603 assertions)
```

Suite 116 → **138 tests**, 541 → **603 assertions**. All four lint baselines held exactly; the new
code adds zero violations.

Commit: **8c208b7**.

### Slice 9 — evidence (commands and output, 2026-07-20)

The slice's own test point is *"`scripts/keel-verify` runs; its **full output** pasted"*. It is
pasted verbatim below, unedited, including both warnings.

#### `php scripts/keel-verify` — FULL OUTPUT

```
keel-verify — Klytos CMS

  PASS  authorization gate covers every admin surface (64 files)
  PASS  the central gate is invoked from admin/bootstrap.php
  PASS  docs/api/INDEX.md summary counts match its rows
  PASS  docs/api/INDEX.md parity: every row has its doc, every doc its row
  PASS  locale catalogues agree on their key set (120 files across 6 sets)
  PASS  no placeholder copy in distributable surfaces (448 files)
  PASS  changelog order oldest → newest (1 entry — ordering not yet exercised)
  WARN  version touchpoints in sync (5 touchpoints)
          - installer/VERSION (canonical)    0.31.1-beta.1
          - README.md (Current version)      0.28.5
          - README.md (structure listing)    0.28.5
          - changelog.txt (newest entry)     0.4.0
          - newest git tag                   0.30.1
          - audit H-01 — recorded in docs/03-technical-plan.md §5; the fix belongs to Phase 7.
  WARN  runtime assets survive the release archive (16 guides)
          - 16 of 16 files under installer/core/guides/ are export-ignored, so they do NOT ship.
          - klytos_list_guides / klytos_get_guide read that directory at runtime, so on a released install those tools return nothing.
          - audit NEW-27 (H-02 family) — the blanket `*.md export-ignore` in .gitattributes; the fix belongs to Phase 7.

OK — 9 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase).
(exit status: 0)
```

Seven checks are new in this slice. Two are **WARN**: they report a property that is genuinely
broken while the fix belongs to a phase that already owns it, so they print full evidence on every
run and do not change the exit code. That is what `docs/sprints/sprint-1.md` means by *"slice 9
makes H-01 detectable now; fixing it is Phase 7's"*, applied consistently to NEW-27 as well.

Two §1a checks are **deliberately absent**, and their absence is a decision rather than an omission:
minified-asset sync (N/A per **D-038** — all 68 tracked `*.min.*` are third-party vendor
distributions, so no source↔minified drift can exist) and WordPress i18n sniffs (N/A per **D-006** —
this is not a WordPress project; the equivalent real invariant, catalogue key parity, IS checked).

#### Every new check proven to FAIL on an injected violation

A check that has never fired is indistinguishable from one that cannot — the failure mode **L-010**
records, where a broken check simply goes quiet and lends its credibility to everything downstream.
So each check was driven with a violation injected, observed failing, and the injection reverted.
Ten probes, all fired, tree left clean:

| Probe | Injection | Expected | Observed |
|---|---|---|---|
| P1 | `\| Filters \| 116 \|` → `115` in INDEX.md | FAIL | `FAIL docs/api/INDEX.md summary counts match its rows` |
| P2 | an INDEX row retargeted to `docs/reference/ghost.md` | FAIL | `FAIL docs/api/INDEX.md parity` |
| P3 | created `docs/reference/orphan-probe.md` with no row | FAIL | `orphan-probe.md exists but no INDEX row points at it` |
| P4 | deleted `common.cancel` from `de.json` | FAIL | `FAIL locale catalogues agree on their key set` |
| P5 | added `zz_probe_key` to `de.json` | FAIL | `de.json carries key 'zz_probe_key' that ca.json does not` |
| P6 | appended `// TODO: finish this before release` to a distributable file | FAIL | `FAIL no placeholder copy in distributable surfaces` |
| P7a | appended a changelog entry dated **earlier** than the one above it | FAIL | `FAIL changelog order oldest → newest` |
| P7b | appended a correctly-dated second entry | PASS **with 2 entries** | `PASS changelog order oldest → newest (2 entries)` |
| P8 | aligned VERSION + README + changelog to the tag value | WARN → PASS | `PASS version touchpoints in sync` |
| P9 | `installer/core/guides/*.md -export-ignore` in `.gitattributes` | WARN → PASS | `PASS runtime assets survive the release archive` |

P7b matters on its own: with a single changelog entry the ordering check cannot run at all, so it
would have passed vacuously forever. P7b proves it genuinely executes once there are two entries.
P8 and P9 are the **other** direction required by L-010 — one direction is half a test. Both
warnings already fire on the real tree, so what needed proving was that they go **quiet** when the
condition is actually fixed, rather than being unconditional print statements.

After every probe: `git status --porcelain` reported only `M scripts/keel-verify` (the slice's own
work), and the closing `keel-verify` run matched the opening baseline exactly.

#### The checks are pinned by a permanent test, and that test was proven too

`tests/Unit/KeelVerifyTest.php` (4 tests / 14 assertions) asserts that keel-verify exits 0, that
**every one of the 9 check names appears in its output**, that the reported count matches the
expected set, and that the two known warnings are still reported. A throwaway probe script proves
nothing tomorrow; this is what makes "9 checks passed" a measurement rather than a habit.

Proven in three directions before being trusted:

| Probe | Injection | Observed |
|---|---|---|
| A | a check RENAMED | `keel-verify no longer reports the check 'locale catalogues agree on their key set'` — 1 failure |
| B | a check DELETED | the name assertion AND `keel-verify reported 8 checks; 9 are expected` — 2 failures |
| C | a WARN forced quiet | `The version touchpoints stopped disagreeing.` — 1 failure |

Restored: `OK (4 tests, 14 assertions)`.

#### NEW-27 — the in-product guides do not ship

Found while defining what "distributable surface" means for the placeholder check, which is the
honest way to build that check and is why it surfaced at all.

```
$ git archive HEAD | tar -x -C /tmp/klytos-archive-test
$ find /tmp/klytos-archive-test -name '*.md' | wc -l
2                      # PRIVACY.md only — README.md and INSTALL.md are stripped too (H-02)
$ ls -la /tmp/klytos-archive-test/installer/core/guides/
total 0                # the directory ships EMPTY; all 16 guides are gone
```

`installer/core/mcp/tools/guide-tools.php` reads that directory at runtime for
`klytos_list_guides` and `klytos_get_guide`, and the tool description declares
`gutenberg-blocks`, `seo-content`, `post-types-and-fields`, `forms` and `design-patterns`
**REQUIRED** before creating content. Verified this reaches real installs rather than only
`git archive`: release `v0.30.1` carries **no attached assets**, so `Updater::resolveDownloadUrl()`
falls back to `$release['zipball_url']` (`updater.php:751-752`) — GitHub's auto-generated zipball,
which honours `export-ignore`.

Not fixed here. `.gitattributes` packaging is audit **H-02**, which `docs/sprints/sprint-1.md`
scopes to Phase 7 ("they close by construction in the next full Phase 7").

#### NEW-28 — shipped dev scripts execute over HTTP

`scripts/` is not export-ignored, and the root `.htaccess` serves any existing file directly
(`.htaccess:23-25` — `REQUEST_FILENAME -f` → `RewriteRule ^ - [L]`). Tested rather than reasoned
about, against the extracted archive:

```
$ php -S 127.0.0.1:8321 -t /tmp/klytos-archive-test
$ curl -D - http://127.0.0.1:8321/scripts/dev/router.php
HTTP/1.1 404 Not Found          <- its OWN 404 page, 468 bytes, disclosing the admin path,
                                   the MCP endpoint, BuildEngine internals and audit NEW-04
$ curl http://127.0.0.1:8321/scripts/dev/upgrade-assert.php
HTTP 200, 1332 bytes            <- executed
$ curl http://127.0.0.1:8321/scripts/dev/seed-playground.php
This script is CLI-only.        <- correctly refused; seed-playground.php:35 already had the guard
```

Fixed in path per **D-031**'s narrowing — this is the code the slice is already changing and
testing. Both files now refuse the wrong SAPI.

**The first version of the router guard was wrong, and the test that "confirmed" it was a false
pass.** `PHP_SAPI !== 'cli-server'` looks right, but `php -S` reports `cli-server` **both** when the
file is the router **and** when it is served as an ordinary file — so the guard did not fire in the
second case. The probe reported `HTTP 404` and looked green, because the *unguarded* file answers
404 for that path too and its first line is byte-identical to the guard's. Only measuring the body
size separated them: **468 bytes = the disclosure page, 19 bytes = the guard**. The discriminator
was then probed rather than reasoned about:

```
case=A (as router)      SAPI=cli-server  SCRIPT_NAME=/installer/admin/
case=B (served as file) SAPI=cli-server  SCRIPT_NAME=/scripts/dev/router.php
```

Final state, both directions:

```
served as plain files:   router.php -> HTTP 404, 19 bytes    (guard fired)
                 upgrade-assert.php -> HTTP 404, 19 bytes    (guard fired)
legitimate use:  php -S ... router.php -> /installer/admin/ -> HTTP 302   (still works)
                 php scripts/dev/upgrade-assert.php -> its own usage message (still works)
```

#### Full suite, lint and the upgrade path

```
$ XDEBUG_MODE=off vendor/bin/phpunit
OK (142 tests, 617 assertions)          # 138/603 before this slice; +4 tests from KeelVerifyTest

$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
== UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)
```

D-025 baselines, each measured in its own scope. **`scripts/` had never been linted at all** — the
same gap slice 7 found for `installer/public/` — and is now in the scanned set with a fresh
baseline. `scripts/keel-verify` is named explicitly in `phpcs.xml` because it carries no `.php`
extension, so a directory entry alone would have silently skipped the one file this slice writes.

| Scope | Before | After | Verdict |
|---|---|---|---|
| `installer/core` + `installer/admin` | 193 / 488 | **193 / 488** | held |
| `installer/plugins` | 113 / 109 | **113 / 109** | held |
| `tests` | 0 / 0 | **0 / 0** | held |
| `installer/public` | 0 / 0 | **0 / 0** | held |
| `scripts` | *never scanned* | **0 / 2** | new baseline recorded |

`keel-verify` itself lints 0/0. The 2 warnings are pre-existing in `upgrade-assert.php`
(`PSR1.Files.SideEffects` and one long line). One error WAS introduced by this slice's guard — a
multi-line control structure — and was fixed before the measurement above; new code enters clean.

**A measurement lied three times in this session and each was caught by re-measuring, which is the
slice's own subject turned on itself.** (1) A `${var:-default}` fallback in the lint loop printed
"0 ERRORS AND 0 WARNINGS" for core+admin whenever `grep` found nothing — a *false clean* on the
project's largest baseline. (2) The router-guard probe above. (3) An archive built from
`git write-tree` extracted the **unmodified** files, so the first guard test measured HEAD rather
than the working tree. None of the three would have been caught by reading the code.

#### The review cycle changed the slice, and two of its findings were the slice's own subject

Four passes ran on the FINISHED diff, docs included (L-015). Every finding was re-derived against
source before anything was changed.

| Pass | Result |
|---|---|
| `docs-verifier` | no blocking issues; INDEX parity clean. **Caveat recorded:** it declined to count the rows itself ("probably ~308 not 306 — I'll trust the verify script"), which is the L-015 shape. The count stands because it was measured independently with `awk` before any of this was written. |
| `code-reviewer` | no BLOCKING; 5 non-blocking, **4 confirmed and fixed** |
| `security-auditor` | **1 BLOCKING**, confirmed and fixed; 3 non-blocking, 2 fixed |
| playground-QA (fresh context, given ONLY `docs/playground.md`) | **5 instruction defects**, all fixed before the close |

**The BLOCKING finding was mine, and it is the sharpest thing in this slice.**
`.claude/settings.json` and `.cursor/cli.json` allowed `Bash(curl -D -:*)` and
`Bash(curl -s -o /dev/null:*)` **unscoped to any host**, three lines below sibling entries that pin
`php -S` and `nc` to `127.0.0.1`. A committed allow-list grants execution **without a confirmation
prompt**, in a **public repo with forks** — so that is an exfiltration and SSRF primitive, written
into the config of the project that spent slice 6 building an SSRF control. Both are now pinned to
`http://127.0.0.1`.

**A check that scanned nothing reported PASS** — in the slice built to stop exactly that. When
`git check-attr` returned nothing, `$distributable` stayed empty and the placeholder check printed
`PASS … (0 files)`, indistinguishable from "nothing to flag", while its sibling `keel_git()` already
SKIPped loudly for the same cause. Proven by forcing the call to fail:

```
  FAIL  no placeholder copy in distributable surfaces
          - git check-attr returned nothing for 632 candidate files, so this check scanned
            ZERO files. It has not passed — it did not run.
```

**The guard added earlier in this same slice was bypassable by changing one letter.** Both reviewers
found it independently and a probe confirmed it: on the case-insensitive filesystems where the
playground actually runs, `/scripts/dev/Router.php` served the file and MISSED the guard. Now
lowercased and percent-decoded, and re-measured unambiguously — **19 bytes = guard fired, 468 bytes =
the disclosure page**:

```
  /scripts/dev/router.php          HTTP/1.1 404 |  19 bytes
  /scripts/dev/Router.php          HTTP/1.1 404 |  19 bytes
  /scripts/dev/ROUTER.PHP          HTTP/1.1 404 |  19 bytes
  /scripts/dev/upgrade-assert.php  HTTP/1.1 404 |  19 bytes
  php -S ... router.php -> /installer/admin/ -> HTTP 302   (legitimate use intact)
```

Also fixed: the `proc_open` write-then-read pattern would have **hung** rather than failed once the
file list outgrew the pipe buffer (replaced with a temp file); **CI was about to publish a live MCP
application password** into a public, retained Actions log (`seed-playground.php:327` — redirected,
because "unreachable" is weaker than "not logged"); my own comments claimed the four allow-lists were
the "same command set" when two are genuinely narrower (**the L-002 defect in text written to prevent
it, for the second slice running**); and `KeelVerifyTest` demanded 9 checks while the script
documented a 7-check no-git path.

**A fifth false measurement occurred while verifying these fixes** — `file_get_contents` returns
`false` for any non-2xx, so a probe printed `(no response)` for all four paths, which cannot
distinguish "guard fired" from "server never started". Re-run with `ignore_errors` to get the table
above. L-016 was written earlier in this same session and still caught nobody in the act.

#### Post-review re-verification

```
$ XDEBUG_MODE=off vendor/bin/phpunit
OK (142 tests, 617 assertions)

$ php scripts/keel-verify
OK — 9 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase).

$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
== UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)
```

Lint after the review changes: core+admin **193/488**, plugins **113/109**, tests **0/0**,
`installer/public` **0/0**, `scripts` **0/2**. All held.

---

# SPRINT 2 — MCP tool authorization

### Slice 1 — MCP actor resolution — evidence (commands and output, 2026-07-22)

**What the slice does:** the MCP path has no session, so identity is built from the credential.
`TokenAuth::validate()` now resolves an actor `{user_id, role}` and `getActor()` surfaces it; bearer
tokens carry a stamped role (`createBearerToken()` optional role, default owner); app-password and
OAuth roles resolve from the user record; a boot migration (`Auth::migrateCredentialRoles()`, Step
10b-2) stamps role-less bearer tokens with owner. No gate yet — that is slice 2; slice 1 is the
prerequisite identity layer.

#### Full suite (baseline 142 → 156)

```
$ XDEBUG_MODE=off vendor/bin/phpunit
OK (156 tests, 643 assertions)
```

The 14 new tests: `tests/Unit/CredentialRoleTest.php` (7 — bearer role stamp/resolve, absent role →
null, migration stamps + idempotent + leaves an existing role alone) and
`tests/Integration/McpActorResolutionTest.php` (7 — app-pw → owner via the user record, viewer bearer
→ viewer, default bearer → owner, role-less bearer → null role, valid credential + deleted user → no
actor, **OAuth token → its subject's role**, **OAuth token for an unknown subject → no actor**). The
two OAuth tests were added after the `code-reviewer` flagged the OAuth branch (whose return type this
slice changed) as untested — the blocking review finding, now closed.

#### The fail-closed properties proven to FAIL against the wrong behaviour (L-010)

Two decisive properties were broken deliberately and observed red, then reverted:

```
# TEMP-BREAK 1: getBearerTokenActor defaults an absent role to 'owner' (the D-047 mistake)
# TEMP-BREAK 2: resolveUserActor defaults a missing user to owner
$ XDEBUG_MODE=off vendor/bin/phpunit --filter 'CredentialRoleTest|McpActorResolutionTest'
FAILURES!  Tests: 12, Assertions: 21, Failures: 3.
  1) CredentialRoleTest::testATokenRecordWithNoRoleResolvesToANullRoleNotOwner
     Failed asserting that 'owner' is null.
  2) McpActorResolutionTest::testAValidCredentialWhoseUserIsGoneResolvesToNoActor
     Failed asserting that Array (owner) ... is null.
  3) McpActorResolutionTest::testABearerTokenWithNoRoleResolvesToANullRole
     Failed asserting that 'owner' is null.
# both TEMP-BREAKs reverted → OK (12 tests, 21 assertions)
```

A third decisive property — the migration actually persisting the role — was proven when the FIRST
implementation had a `$x['k'] ?? [] as &$ref` footgun: `migrateCredentialRoles()` returned `1` while
persisting nothing, and `testMigrationStampsRolelessRecordsWithOwnerAndIsIdempotent` caught it on the
**persisted** role (not the return value). Recorded as **L-017**; the identical pre-existing footgun in
`validateAppPassword()` is **NEW-29**.

#### Upgrade from the REAL previous version — the installed-base proof (D-047)

```
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
   PASS  a bearer token minted by the previous version carries no role (D-047)   # pre-upgrade, v0.30.1 code
   PASS  the previous version's bearer token survived the upgrade                # post-upgrade
   PASS  the boot migration stamped the pre-existing bearer token with owner (D-047)
== UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)
```

A bearer token minted by the real v0.30.1 installer (role-less by nature) is stamped `owner` by the
boot migration on the real upgraded install — the migration proven on a real token, not a fixture.
This fails against unfixed code: without Step 10b-2 the post-upgrade "stamped with owner" assertion is
red (the token stays role-less).

#### Gate, lint, freshness

```
$ php scripts/keel-verify
OK — 9 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase).

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
A TOTAL OF 193 ERRORS AND 488 WARNINGS WERE FOUND IN 112 FILES
```

Lint baselines all held: core+admin **193/488**, `scripts` **0/2** (upgrade-assert.php touched), tests
**0/0** (two new test files), plugins **113/109**, `installer/public` **0/0**.

Docs at creation: `docs/reference/mcp-authorization.md` (new, slice-1 sections + the four new public
surfaces with runnable examples); `docs/api/INDEX.md` `TokenAuth` row repointed to it. Decision **D-047**
amended (app-pw/OAuth resolve from the user, bearer stamped; the `?? []` footgun); **NEW-29** and
**L-017** recorded.

### Slice 2 — the gate + capability map + tools/list filter + keel-verify check 10 — evidence (commands and output, 2026-07-22)

**What the slice does:** closes **NEW-02**. `installer/core/mcp/tool-capabilities.php` maps the **169
live core tools** to capabilities (absent = deny, `null` = audited exception, `mcp.tool_capabilities`
filter); a typed `PermissionDeniedException`; ONE `denialReason()` gate inside `ToolRegistry::call()`
**above** the `mcp.handle_tool` filter, default-deny; `setActor()` carries the credential's identity
onto the per-request registry (set by `server.php` after auth and by `chat-engine` from the session);
`listTools()` filtered by the same decision; `server.php` catches the exception → JSON-RPC error object
+ **HTTP 403**; a `mcp.access_denied` audit action; `keel-verify` check 10.

#### Full suite (baseline 156 → 169)

```
$ XDEBUG_MODE=off vendor/bin/phpunit
OK (169 tests, 671 assertions)
```

The 13 new tests: `tests/Integration/McpToolGateTest.php` (9 — no-actor denies, viewer denied a
destructive tool with the exception's tool/role, viewer allowed a read tool, owner allowed the
destructive tool, unknown role denied even a read, an unmapped tool denied even for owner, `tools/list`
filtered for viewer + owner, empty list with no actor) and `tests/Integration/McpGateHttpTest.php` (4,
real HTTP :8105 — viewer bearer → `klytos_delete_page` **403** + JSON-RPC error object with `-32000` and
the id kept; owner bearer allowed the same tool; unknown-role bearer denied; viewer `tools/list` omits
destructive tools and keeps reads).

#### Every denial proven to FAIL against the ungated code (L-016)

`denialReason()` was made to `return null` unconditionally (the ungated shape), the two new test
classes run, and exactly the **9 denial tests failed while the 4 positive controls stayed green** — the
HTTP ones failing on `200 is identical to 403`, i.e. the tool actually RAN and shipped 200, which is the
property only the gate produces. Reverted → green.

```
$ # denialReason() TEMP-BROKEN to `return null;`
$ XDEBUG_MODE=off vendor/bin/phpunit tests/Integration/McpToolGateTest.php tests/Integration/McpGateHttpTest.php
FAILURES!  Tests: 13, Assertions: 20, Failures: 9.
  ...
  9) McpGateHttpTest::testViewerBearerIsDeniedADestructiveToolWith403
     the refusal must ship as HTTP 403 on the wire
     Failed asserting that 200 is identical to 403.
$ # TEMP-BREAK reverted → OK (13 tests, 27 assertions)
```

#### keel-verify check 10 — proven to fail both directions, then reverted (L-010)

```
$ php scripts/keel-verify        # after the check was added
  PASS  every registered MCP tool has a capability-map entry (169 tools)
OK — 10 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase).

$ # inject a typo: rename one map key 'klytos_get_page' -> 'klytos_get_page_TYPO'
$ php scripts/keel-verify
  FAIL  every registered MCP tool has a capability-map entry (169 tools)
          - MCP tool 'klytos_get_page' is registered but has no entry ...
          - the capability map names MCP tool 'klytos_get_page_TYPO', which is not registered ...
FAILED — 2 problem(s) across 10 check(s).
$ # typo reverted → 10 checks pass
```

The registered set is parsed from the loader's own `$toolFiles` list, so `integrity-tools.php` (dead
until slice 3) is correctly excluded; the check refuses to PASS if it enumerates fewer than 100 tools
(L-016 — a scan of nothing must not read as green).

#### Real HTTP + a live playground `tools/call` (the sprint's end-to-end proof)

Playground booted on a verified-free port; MCP endpoint answers 401 unauthenticated (freshness); a
`role=viewer` bearer and an `owner` bearer minted through the real App:

```
1. unauth tools/list                                              -> HTTP 401
2. viewer bearer  tools/call klytos_delete_page                  -> HTTP 403
   {"jsonrpc":"2.0","error":{"code":-32000,
     "message":"Permission denied: not authorized to call the tool 'klytos_delete_page'."},"id":7}
3. owner  bearer  tools/call klytos_delete_page (nonexistent)    -> HTTP 200, result envelope (past the gate)
4. viewer bearer  tools/list  -> 19 tools; klytos_delete_page: no, klytos_get_page: YES
5. owner  bearer  tools/list  -> 169 tools; klytos_delete_page: YES
```

#### Upgrade, gate, lint

```
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
== UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)

$ php scripts/keel-verify
OK — 10 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase).

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary        # whole configured scope
A TOTAL OF 306 ERRORS AND 599 WARNINGS WERE FOUND IN 130 FILES
```

The whole-scope **306/599** is the exact sum of every recorded per-scope baseline (core+admin 193/488,
plugins 113/109, tests 0/0, `installer/public` 0/0, `scripts` 0/2), so every D-025 baseline held and the
change added zero net lint. The two new core files (`tool-capabilities.php`, `permission-denied-exception.php`)
lint clean.

Docs at creation: `docs/reference/mcp-authorization.md` (the gate, the map, the refusal shape, `tools/list`
filtering, +5 public surfaces); `docs/api/INDEX.md` (+4 rows, Summary 949→**953**, `ToolRegistry` row
repointed); `docs/keel-verify.md` (check 10 as row 8, WARNs renumbered 9/10); `docs/flows/mcp-tool-call.md`
(the authorization step now exists); **D-046** amended. Skills deferred to slice 4 per the sprint plan.

#### Review cycle — one blocking finding, fixed (L-015)

Both `code-reviewer` and `security-auditor` ran on the finished diff, docs included. The
`security-auditor` returned **no blocking findings** (traced every degenerate actor to a DENY against
source). The `code-reviewer` returned **one BLOCKING finding**: the map omitted the 8 `klytos_x402_*`
tools, which the **core** x402 module injects through `mcp.tools_list`/`mcp.handle_tool` — so
default-deny made them unusable by every role including owner (the reason owner's `tools/list` had
dropped 177→169). This was a gap in the plan, not just the implementation — the plan named only the two
optional plugins as filter-injected, missing that x402 is unconditional core. Fixed by declaring x402's
capabilities through `mcp.tool_capabilities` in `x402-mcp-tools.php` (reads → `x402.view`, writes →
`x402.manage`); the static core map stays at 169 so keel-verify check 10 is unaffected. Two x402 tests
added (`McpToolGateTest`), both proven to FAIL against the un-declared code:

```
$ # x402 mcp.tool_capabilities filter TEMP-BROKEN to `return $map;`
$ XDEBUG_MODE=off vendor/bin/phpunit tests/Integration/McpToolGateTest.php --filter X402
  1) testX402ToolsAreGatedByTheirDeclaredCapability
     PermissionDeniedException: MCP tool 'klytos_x402_get_config' denied: ... not in the capability map
  2) testX402IsDeniedToViewerAndAdvertisedToOwner
     owner must still see x402 tools / Failed asserting that an array contains 'klytos_x402_get_config'.
$ # reverted → OK
```

The `code-reviewer`'s non-blocking finding is **NEW-30** (filter-injected tools unreachable via the HTTP
`exists()` path — pre-existing, fails closed, slice-3 reconciliation). A minor docblock nit in
`KeelVerifyTest` (stale "9 checks"/"7 not 9" → 10/8) and a `server.php` `getActor() ?? []` clarity tweak
were also applied.

### Slice 3 — coverage completeness (loader fail-loud, integrity + plugins gated, chat-engine default-deny, NEW-30) — evidence (commands and output, 2026-07-23)

Five things landed: the tool loader **fails loudly** (D-049) via an extracted `registerToolFile()` + typed
`ToolRegistrationException`; `integrity-tools.php` is the 34th loader file and its 3 tools are mapped
`site.configure`; the two shipped MCP plugins declare their tools' capabilities (`klytos-forms` 16 →
`forms.manage`, `klytos-importer` 10 → `site.configure`) and are activated in the seed; `chat-engine`
`getAvailableTools()` default-denies an unknown role to an empty list; and **NEW-30 is resolved**
(user-confirmed, D-050) — `exists()` = registered OR mapped, so filter-injected tools are callable over HTTP
and still gated.

#### Full suite (baseline 171 → 184) and keel-verify check 10 (169 → 172)

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (185 tests, 805 assertions)          # +14: 4 loader, 3 gate, 3 chat-engine, 3 HTTP, +1 review pin
$ php scripts/keel-verify | grep 'capability-map entry'
  PASS  every registered MCP tool has a capability-map entry (172 tools)
$ php scripts/keel-verify ; echo exit=$?
  OK — 10 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase).
  exit=0
```

#### Every new behaviour proven to FAIL against the unfixed code (L-016)

Source + seed stashed to HEAD, playground reseeded to the unfixed state (plugins inactive), the new tests
run against it:

```
$ git stash push -- installer/core installer/plugins scripts/
$ php scripts/dev/seed-playground.php --reset          # plugins inactive (unfixed)
$ XDEBUG_MODE=off vendor/bin/phpunit --filter 'McpToolLoaderTest|ChatEngineToolListTest|McpToolGateTest|McpGateHttpTest'
  Tests: 28, Assertions: 123, Errors: 2, Failures: 9.
    - registerToolFile() undefined (loader not extracted)          # fail-loud gone
    - testTheRealLoaderWiresInIntegrityTools: false is true        # integrity dead
    - ChatEngineToolListTest: unknown role returns the full list   # advisory fail-open
    - map assertions null / owner list membership / NEW-30 HTTP    # 9 failures
$ git stash pop ; php scripts/dev/seed-playground.php --reset      # restored, plugins active
$ XDEBUG_MODE=off vendor/bin/phpunit ; # OK (185 tests, 805 assertions)
```

The tests that stayed green under the stash are the shared existing tests and the legitimate positive
controls (owner gets a non-empty list; a viewer gets read-only tools; the slice-2 x402 tests).

#### Upgrade, baselines, live walk

```
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
  == UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)        # boot survives, migration idempotent, fails closed
$ # D-025 baselines — all held exactly
  core+admin 193/488 · plugins 113/109 · tests 0/0 · scripts 0/2 · installer/public 0/0
$ # live playground, owner over HTTP
  owner tools/list = 206  (172 core + 8 x402 + 16 forms + 10 importer; integrity now visible)
  owner tools/call klytos_forms_list  → HTTP 200   # NEW-30: was "Unknown tool" before this slice
```

The new/touched files (`tool-registration-exception.php`, the seed, and all four test files) lint clean
under `phpcs --standard=phpcs.xml` (0/0). Skills updates and the full count-truth reconciliation
(177/206 served vs live vs dead) are **slice 4** per the plan.

#### Review cycle — both clean, one narrowing applied (L-015)

Both subagents ran on the finished diff, docs included. The **`security-auditor`** returned **no
blocking findings**: it traced the default-deny invariant end to end and confirmed the gate
(`denialReason`) runs before any dispatch for a newly-callable filter-injected tool, that the
`PermissionDeniedException` catch precedes the fallback catch (so a 403 never degrades to "Unknown
tool"), and that a null/unknown actor still denies; capability assignments all err toward the safe
tier; no secrets in the diff or the seed (only gitignored encrypted state is written). The
**`code-reviewer`** returned **no blocking findings** and one substantive note: the fallback
`catch (\RuntimeException)` in `handleToolsCall()` was broader than needed — a future handler's plain
`RuntimeException` would be masked as "Unknown tool". Fixed by a typed
`Klytos\Core\MCP\ToolNotFoundException` (thrown by `call()` only for the mapped-but-unhandled case),
caught exactly; a plain `RuntimeException` now propagates. Pinned by a new test:

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter testAMappedButUnhandledToolThrowsToolNotFound
  OK (1 test, 1 assertion)          # owner (authorised) + a mapped tool nothing handles → ToolNotFoundException
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (185 tests, 805 assertions)
$ php scripts/keel-verify   # 10 checks, INDEX parity green (Classes 100 / Total 955), check 10 = 172
```

Two stale `scripts/keel-verify` comments ("integrity-tools.php … dead until slice 3", "live count is
169") were corrected to the 34-file/172-tool reality. Inner-paren style left as-is (file precedent;
phpcs.xml clean).

### Slice 4 — reconciliation, count truth, refusal i18n, skills, and the `ai.use` widening — evidence (commands and output, 2026-07-24)

The sprint-closing slice. Five things landed: the **count truth** across every surface; the
`authorization.md` forward reference **closed** and "Adding a new MCP tool — the checklist" written;
the client-facing refusal **translated into all 20 locales**; **eight** skills brought level with
slices 2–3; and **`ai.use` widened to `editor`** (D-051, user-confirmed, superseding D-035 at its own
recorded trigger).

#### The counts, measured live rather than carried forward (L-015)

Every figure was re-derived on a booted playground — an owner `tools/list` grouped by prefix — because
the plan's own numbers (177 served / 169 live / 3 dead) were all stale after slice 3.

```
$ curl -s -X POST http://127.0.0.1:8083/installer/mcp -H "Authorization: Bearer $TOK_OWNER" \
    -H 'Content-Type: application/json' -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | python3 -c ...
  TOTAL served to owner: 206
    x402    : 8
    forms   : 16
    importer: 10
    core loader (rest): 172
```

So: **172** core + **8** x402 = **180 on a default install** (neither MCP plugin is active —
`$state['active'][$id] ?? false`), **206** with both plugins active (the playground, and the figure
`docs/api/INDEX.md` records for tools on disk), **0** dead. Corrected across `04-adoption-audit.md`,
`01-discovery.md`, `03-technical-plan.md`, `playground.md`, `reference/mcp-authorization.md` and the
skills; the historical 177/169 figures are annotated as superseded, not rewritten.

#### Per-role × per-tool matrix, on the wire (the table now in `docs/playground.md` §3a)

Four bearer tokens, one per role; HTTP status of `tools/call`:

```
tool                    owner  admin editor viewer
klytos_get_page           200    200    200    200
klytos_delete_page        200    200    403    403
klytos_x402_get_config    200    200    200    403
klytos_forms_list         200    200    403    403
klytos_integrity_status   200    200    403    403
tools/list size           206    197     56     19
```

The refusal body, live — from the catalogue, not a literal:

```
{"jsonrpc":"2.0","error":{"code":-32000,"message":"Permission denied: not authorized to call the
tool 'klytos_delete_page'. Ask the site owner to grant this connection the permission it requires."},"id":1}   403
```

An unmapped name is a **protocol** error, not a refusal — `-32602` with **HTTP 200** — which is why
the two are worth distinguishing in the document:

```
$ ... "params":{"name":"klytos_not_a_tool"}
  {"jsonrpc":"2.0","error":{"code":-32602,"message":"Invalid params: Unknown tool: klytos_not_a_tool"},"id":1}
  200
```

#### Full suite (185 → 190) and the gates

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (190 tests, 951 assertions)      # +5: 4 refusal-i18n unit, 1 HTTP catalogue assertion
$ php scripts/keel-verify ; echo exit=$?
  OK — 10 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase).
  exit=0
  PASS  locale catalogues agree on their key set (120 files across 6 sets)   # 20 × mcp.permission_denied
  PASS  every registered MCP tool has a capability-map entry (172 tools)
  PASS  docs/api/INDEX.md summary counts match its rows                      # Classes 100 / Total 955
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
  == UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)
```

Lint (D-025) held **exactly**, measured per scope with no default value (L-016): core+admin
**193/488**, plugins **113/109**, tests **0/0**, installer/public **0/0**, scripts **0/2**.

#### Proven to FAIL against the unfixed code, both changes (L-016)

The i18n change, with the 20 catalogue edits reverted (`git checkout -- installer/core/lang`):

```
FAILURES!  Tests: 4, Assertions: 5, Failures: 4
  - ca.json must carry mcp.permission_denied …
  - the ca refusal must name the tool the caller asked for  (I18n echoed the key back)
  - the es refusal is byte-identical to English — it was not translated
  - the ca refusal contains a capability identifier …
```

The widening, with `ai.use` reverted to `['owner','admin']`:

```
FAILURES!  Tests: 17, Assertions: 66, Failures: 2
  1) AdminGateHttpTest — installer/admin/ai-chat.php as editor: expected 200, got 403
  2) AdminGateMapTest  — Role editor must hold ai.use …
```

Both restored and re-run green. The HTTP failure is the one that matters: the widening is asserted
on the wire, not by re-reading the matrix.

#### One instrument caught, recorded as L-018

The first version of the non-disclosure test blocked the word *owner* and failed on the **English**
message, which deliberately says "ask the site **owner**" — the person to contact, not the caller's
role. A word blocklist could not tell a legitimate use from the internal reason, so the assertion was
narrowed to the capability-identifier shape (`[a-z]+\.[a-z_]+`). A false accusation against correct
content: L-016's failure mode from the opposite side.

#### The four independent passes at the sprint close (the part that changed the slice)

```
code-reviewer    : no blocking. One stale why-comment (admin-gate.php: "Owner+admin only
                   while NEW-02 is open") — fixed.
security-auditor : THREE blocking. Two real → NEW-31, fixed in path. One verified and
                   deliberately retained (chat-engine surfaces the denial reason to the
                   operator's own session; reasoning recorded in D-051).
docs-verifier    : clean. INDEX parity, every Sprint-2 symbol resolves, examples correct,
                   counts consistent. Out-of-scope catch: README "33 tool modules" → 34.
playground-QA    : "qualified yes". §3a FLAWLESS — every cell of the per-role matrix matched.
                   8 defects elsewhere in playground.md, all fixed before the close.
```

**NEW-31, proven both directions after the fix:**

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter "testAiChatPanels|testAiChatApiDoesNotDisclose"
  # against the UNFIXED code:
  FAILURES!  Tests: 2, Assertions: 3, Failures: 2
    - An editor holds ai.use but not users.manage, so ?panel=users must refuse
      Failed asserting that 200 is identical to 403.      ← editor reached USER MANAGEMENT
    - An editor must not enumerate provider keys
      Failed asserting that 200 is identical to 403.      ← editor read 6+4 chars of every API key
  # after the fix:
  OK (12 tests, 47 assertions)
```

**The playground-QA pass's own headline finding, reproduced and fixed:** its section-1 server ran on
**8099**, which is `upgrade-test.sh`'s default port, so the upgrade test talked to the router instead
of the release installer and failed with `the installer did not produce an admin directory` —
a fabricated failure with no mention of a port. Section 1 now runs on its own `$RPORT`, and the
corrected flow was walked end to end (`viewer → users.php` **403**, `viewer → api/plugins.php`
**403** on `$RPORT=8093`).

**And the finding that reached the code:** the debug-log section promised entries that nothing
writes. `mcp.access_denied` and `auth.access_denied` have **no core listener** (grep, whole tree
minus vendor), so a refusal writes nothing. Recorded as **NEW-32**; the "went to the audit log"
wording corrected in `installer/core/mcp/server.php` and `docs/reference/mcp-authorization.md`;
lesson **L-019**.

#### Final gate state at the close

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (192 tests, 961 assertions)          # sprint start: 142/617
$ php scripts/keel-verify ; echo exit=$?
  OK — 10 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase).
  exit=0
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
  == UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)
$ vendor/bin/phpcs --standard=phpcs.xml …   # per scope, no default value (L-016)
  core+admin 193/488 · plugins 113/109 · tests 0/0 · installer/public 0/0 · scripts 0/2
```

---

# SPRINT 3 — vendor-ai CVE remediation, and the AI stack fails safe

### Slice 1 — the re-vendor to zero advisories — evidence (commands and output, 2026-07-25)

**Acceptance criterion 1 — `composer audit -d installer` reports zero. Measured on BOTH sides.**

```
# BEFORE (working tree at ffc8d29)
$ composer audit -d installer
  Found 11 security vulnerability advisories affecting 2 packages:
    guzzlehttp/guzzle 7.10.0 — 7 advisories (fixed in 7.12.1 / 7.12.3 / 7.14.2 / 7.15.1)
    guzzlehttp/psr7   2.9.0  — 4 advisories (fixed in 2.10.2 / 2.12.1 / 2.12.3)

# THE BUMP
$ composer update guzzlehttp/guzzle guzzlehttp/psr7 guzzlehttp/promises -W -d installer
  Lock file operations: 1 install, 3 updates, 0 removals
    - Upgrading guzzlehttp/guzzle   (7.10.0 => 7.15.1)
    - Upgrading guzzlehttp/promises (2.3.0  => 2.5.1)
    - Upgrading guzzlehttp/psr7     (2.9.0  => 2.13.0)
    - Locking   symfony/polyfill-php80 (v1.37.0)
  No security vulnerability advisories found.

# AFTER
$ composer audit -d installer
  No security vulnerability advisories found.
```

**D-029's recorded floors would NOT have reached zero.** Measured from the advisory list itself:
7.12.1/2.12.1 clears only 2 guzzle + 3 psr7, leaving **6 of 11** open (three guzzle advisories are
fixed only in 7.15.1, one in 7.14.2, one in 7.12.3; psr7's newest in 2.12.3). Resolved by **D-052**
in favour of D-029's own "audit to zero" criterion (L-014). Recorded here as a measurement, not an
argument.

**No unintended package moved — verified by listing changed directories, not assumed from the pins.**

```
$ git status --porcelain installer/vendor-ai/ | ...   # changed top-level package dirs
  guzzlehttp/guzzle · guzzlehttp/promises · guzzlehttp/psr7 · symfony/polyfill-php80
  + composer/ (generated metadata) + LICENSE-THIRD-PARTY.md
```

**The diff is 95 files, not the 482 D-029 implied** (482 is the size of the tree, never the size of
the change — corrected in D-052 and in `03-technical-plan.md`):

```
$ git diff --cached --shortstat installer/
  95 files changed, 15660 insertions(+), 1004 deletions(-)
$ git diff --cached --name-status installer/vendor-ai | awk '{print $1}' | sort | uniq -c
  27 A     66 M     0 D
$ git ls-files installer/vendor-ai | wc -l
  482 before  →  509 after
  guzzle 43→52 · psr7 33→37 · promises 18→21 · symfony/polyfill-php80 0→11
```

**The fixes are physically present, checked rather than inferred from a version number:**
`guzzlehttp/guzzle/src/Handler/ProxyEnvironment.php` (the proxy advisories) and
`guzzlehttp/psr7/src/Rfc3986.php` (CVE-2026-59882, weak URI host validation) are both among the 27
added files.

**Autoloader init hash unchanged** — `ComposerAutoloaderInita67a306bc5846a467c9201d459055b69` before
and after, so `autoload.php` / `autoload_real.php` / `autoload_static.php` did not churn beyond the
new psr-4 entry.

**Acceptance criterion 2 — the drift guard, observed RED before it was made green (L-016).** No fault
had to be injected: the real half-updated state failed all three methods, naming both the version
drift and a leaked root package.

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter VendorAiManifestTest    # records half-updated
  FAILURES!  Tests: 3, Assertions: 4, Failures: 3
    - manifest ↔ installed.php : guzzle/promises/psr7 versions differ; symfony/polyfill-php80 absent
                                 from require; 'klytos/vendor-ai-manifest' present in installed.php
    - notice   ↔ installed.php : same three versions + the missing 17th package
    - lock     ↔ installed.php : 'klytos/vendor-ai-manifest' => '1.0.0' not in the lock's packages

$ XDEBUG_MODE=off vendor/bin/phpunit --filter VendorAiManifestTest    # all four records reconciled
  OK (3 tests, 21 assertions)
```

**The guard's root-package assumption was the real finding here (L-020).** `vendoredVersions()`
skipped the root by the literal `'__root__'` — only what Composer writes for a root it cannot *name*.
This manifest is named, so the first `composer update` this repository has ever run renamed the entry
and the root leaked into all three comparisons. The guard was built (D-028) against a tree vendored
elsewhere and never regenerated here — slice 2's own test point asserted "`vendor-ai/` unmutated" —
so the assumption rode along unexercised for three sprints. Fixed by reading
`$installed['root']['name']`; both set comparisons stay `assertSame` in both directions.

**`installer/composer.json` gains `"version": "1.0.0"`, and it is load-bearing.** Left alone Composer
resolved the root identity from git and wrote branch-and-commit-dependent values into the **tracked**
generated file:

```
# without the pin
'pretty_version' => 'dev-develop',  'reference' => 'ffc8d2916217a4e407cb113dc46b978f0ec4ec1d'
# with it
'pretty_version' => '1.0.0',        'reference' => null
```

**Acceptance criterion 3 — the new compatibility test, proven to fail in three directions.**
`tests/Integration/VendorAiCompatibilityTest.php` (3 tests / 24 assertions). Its symbol list is
**measured**, not guessed — every `GuzzleHttp\*` / `Psr\Http\*` import in
`installer/vendor-ai/soukicz/llm/src/` plus the two exception types first-party code catches at
`chat-engine.php:197` and `:216`.

```
# probe A — an unresolvable symbol must be reported
+ 'GuzzleHttp\ProbeSymbolThatDoesNotExist'
  FAIL: "The vendored AI stack no longer provides symbols its own consumers import.
         Missing: GuzzleHttp\ProbeSymbolThatDoesNotExist"

# probe B — a URL psr7 normalises must break the round-trip assertion
+ 'https://api.anthropic.com:443/v1/messages'
  FAIL: "psr7 no longer round-trips a provider endpoint" — expected ':443', actual without it

# probe C — the cookie assertion must be able to be false, not only pass
$ php -r 'require ".../autoload.php"; ...'
  default         : false                              # what the test asserts
  cookies => true : GuzzleHttp\Cookie\CookieJar        # so the assertion reads a real property

# both probes reverted
$ XDEBUG_MODE=off vendor/bin/phpunit --filter VendorAiCompatibilityTest
  OK (3 tests, 24 assertions)
```

**Full test point.**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (195 tests, 986 assertions)          # sprint start: 192/961  (+3 / +25)

$ php scripts/keel-verify ; echo exit=$?
  OK — 10 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase).
  exit=0
  # check 10 = 172 tools · locale parity 120 files / 6 sets · INDEX parity clean
  # "no placeholder copy in distributable surfaces" moved 448 → 462 files: the new vendored
  #  PHP ships (the added .md files do not — `*.md` is export-ignored). Expected, not drift.

$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
  == UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary <scope>   # per scope, no default (L-016)
  installer/core + installer/admin  193 errors / 488 warnings in 112 files   (baseline 193/488 — held)
  installer/plugins                 113 errors / 109 warnings in  17 files   (baseline 113/109 — held)
  tests                               0 /   0  — 33 files scanned, ran (Time: line), no total line
  installer/public                    0 /   0  —  2 files scanned, ran (Time: line), no total line
  scripts + scripts/keel-verify       0 /   2  in 1 file                     (baseline 0/2 — held)
```

Two of the five scopes print **no** "A TOTAL OF" line when clean, which is indistinguishable from
"did not run" — so the file-count progress line and the `Time:` line are recorded as the proof each
scan actually executed. A first attempt at a scripted loop reported "NO SUMMARY LINE — INVESTIGATE"
for scopes that were in fact fine; the numbers above come from running each scope directly rather
than from patching the loop, because a measurement that needs a workaround is not yet a measurement
(L-016). **`installer/vendor-ai/*` is excluded at `phpcs.xml:27`, so a re-vendor structurally cannot
move a baseline — a movement here would itself have been the finding.**

**Reachability was redone from scratch and is recorded in D-052 and audit NEW-05, not repeated here.**
Headline: **none of the 11 advisories has a demonstrated exploitation path in Klytos**, and the
specific worry this slice was told not to inherit — that psr7's host-confusion CVEs sit next to
`SafeHttp`/NEW-15 — is **refuted by measurement**: `installer/core/safe-http.php` and
`installer/core/http-client.php` contain **zero** `GuzzleHttp`/`Psr\Http` references, so the two HTTP
stacks never share a URL and the differential-parsing precondition does not exist.

#### Post-review re-verification (slice 1)

Both subagents ran on the finished diff, docs included (L-015). **Neither returned a blocking finding.**
Two non-blocking items, both mine and both the L-015 class, were fixed before the commit:

- **"95 files" and "27 added, 66 modified, 0 deleted" sum differently (93)** — because they are two
  different scopes: 95 is `git diff --shortstat installer/`, 93 is `git diff --name-status
  installer/vendor-ai`. The missing two are `installer/composer.json` and `installer/composer.lock`.
  Both numbers were right and their juxtaposition was not; the scope of each is now named in D-052 and
  in audit NEW-05, so re-deriving them does not produce an apparent contradiction.
- **A comment in the new test said "three of the fourteen" symbols are interfaces; four are**
  (`ResponseInterface` was uncounted). Corrected. Nothing was under-tested — the loop checks
  `class_exists() || interface_exists()` uniformly.

The `security-auditor` re-derived the reachability verdict independently and reached the same
conclusion, and **found the `model`-parameter defect is sharper than I had framed it, in two ways**:
a `#` in `$input['model']` pushes `key={apiKey}` into the URI **fragment**, so the request leaves with
**no API key** (not merely a wrong endpoint); and the population is **editor and above**, not
"authenticated admin", because `ai.use` was widened by D-051. Both re-verified in the main session by
running the encodings rather than accepting the report (L-013) — the host never moves in any spelling
and CRLF is percent-encoded. Recorded as an audit finding with a trigger; not fixed here.

**Both reviewers ran without a shell** and reported that limit themselves, so the file-count
bookkeeping and the before/after `Client.php` comparison rest on this session's measurements rather
than an independent second run. Recorded rather than glossed.

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (195 tests, 986 assertions)
$ php scripts/keel-verify
  OK — 10 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase).
$ composer audit -d installer
  No security vulnerability advisories found.
$ vendor/bin/phpcs --standard=phpcs.xml tests/Integration/VendorAiCompatibilityTest.php tests/Unit/VendorAiManifestTest.php
  clean (no violations)
```

### Slice 2 — NEW-06 fail-safe runtime guard + standing advisory detection — evidence (commands and output, 2026-07-25)

**The defect, verified against the freshly re-vendored file rather than the finding text.**
`installer/vendor-ai/composer/platform_check.php` (regenerated by slice 1) sends
`HTTP/1.1 500 Internal Server Error`, echoes `Composer detected issues in your platform: …` **into
the response body**, then throws a bare `\RuntimeException` when `PHP_VERSION_ID < 80300` — and
`autoload_real.php:25` requires it unconditionally. The old guard in `App::getChatEngine()` was
`file_exists()`: a presence check standing in for a version check.

**Acceptance criterion 4 — the policy refuses below the floor and allows at or above it, both
directions asserted (L-008).**

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter AiRuntimeGuard
  OK (10 tests, 17 assertions)
```

`App::aiRuntimeUnsupportedReason()` is a PURE static taking the version id as a parameter, because
**PHP cannot be downgraded inside the suite** — a guard reading `PHP_VERSION_ID` directly would own a
branch no test could ever reach, which is indistinguishable from a branch that cannot fire (L-010).
Same split as D-044's `Auth::buildSecurityHeaders()`.

**Prove-it-fails — three probes, each reverted (L-016):**

```
# PROBE 1 — drop AI_MIN_PHP_VERSION_ID to 80200 (a floor disagreeing with platform_check.php)
  FAIL testRuntimesBelowTheFloorAreRefused (80200) — "must be refused, so the feature degrades…"
  FAIL testRuntimesBelowTheFloorAreRefused (80299) — same
  FAIL testTheConstantMatchesTheGeneratedPlatformCheck
       — "App::AI_MIN_PHP_VERSION_ID has drifted from the floor Composer generated"
  → 3 failures

# PROBE 2 — off-by-one: `<` becomes `<=`, so the floor itself is refused
  FAIL testRuntimesAtOrAboveTheFloorAreAllowed (80300)
       — "an over-eager guard would disable AI chat on hosts that can run it perfectly well"
  → 1 failure

# PROBE 3 — relocate the guard BELOW `require_once $vendorAutoload`
  FAIL testTheGuardRunsBeforeTheVendoredAutoloaderIsRequired
       — "Below it the guard is unreachable: platform_check.php has already sent HTTP 500
          and thrown from inside vendored code."
  → 1 failure

# all three reverted
  OK (10 tests, 17 assertions)
```

Probe 3 is the one that matters most and the only one no runtime test on a supported host can reach:
**ordering is the load-bearing property.** It is pinned by reading the source, the technique D-046
used to establish that the MCP gate sits above the `mcp.handle_tool` filter.

**i18n — one key, all 20 catalogues, verified live rather than by counting files.**

```
$ php scripts/keel-verify | grep locale
  PASS  locale catalogues agree on their key set (120 files across 6 sets)

$ # resolved through the real I18n service, with placeholders substituted
  "AI features need PHP 8.3 or newer. This site runs PHP 8.3.12, so the AI engine is
   disabled. The rest of Klytos is unaffected — ask your hosting provider to upgrade PHP."
```

The key was inserted **textually** before the `"ai_chat": {` anchor (present exactly once in all 20
files, checked first) so existing formatting stays byte-identical; the script re-parsed every file,
asserted the new value round-trips, and asserted **no existing key changed** before writing. `__()`
resolves here as `Klytos\Core\__()` — probed, not assumed: the global `\__()` does **not** exist
outside `admin/` (NEW-18), and `registerI18nGlobal()` declares the function inside a namespaced
method body, so it lands in `Klytos\Core`.

**Standing advisory detection — the `vendor-advisories` CI job, both branches proven.**

```
$ composer audit -d installer --format=plain
  No security vulnerability advisories found.          → takes the CLEAN branch (correct today)

$ # the WARNING branch, fed the real pre-bump output string
  "Found 11 security vulnerability advisories aff…"    → takes the WARNING branch
```

`continue-on-error: true` is deliberate and is D-045's WARN-tier reasoning: an advisory lands when
upstream publishes it, not when someone pushes, so a hard failure reddens unrelated pull requests for
a cause their author cannot fix. It stays **out** of `scripts/keel-verify` so that script keeps
working offline — a network-dependent check would go quiet without a network, the failure mode L-010
and L-016 both record.

**Real run in the playground — the per-role walk, and the near-miss it produced.**

```
  ai-chat.php as owner   -> 200
  ai-chat.php as admin   -> 200
  ai-chat.php as editor  -> 200      (D-051's widening, still in force)
  ai-chat.php as viewer  -> 403
```

**The first run of this walk was served by a server this session did not start**, and it is recorded
rather than quietly re-run: `nc -z` had printed `PORT 8093 IS TAKEN`, our backgrounded `php -S`
failed to bind silently, and a `php -S` left over from the **previous session of this project**
answered instead. Every L-011 diagnostic agreed with it — same `X-Powered-By`, same security headers,
same session store, same router — because it *was* a Klytos server. It happened to be serving the
current tree, so the numbers were right; they were right **by luck**. Settled with
`lsof -nP -iTCP:8093 -sTCP:LISTEN` + `ps -o lstart,command`, the leftover was killed, and the walk
**reproduced identically on a server proven to have bound**. Recorded as **L-021**.

**Two defects in `docs/playground.md` found by that near-miss, both fixed and both verified verbatim:**

```
# 1. The bind check I first wrote could not fire — it grepped a log the documented
#    command never wrote to. The start command now redirects; then, proven both ways:
  Failed to listen on 127.0.0.1:8093 (reason: Address already in use)  → "DID NOT BIND"
  (holder killed)                                                      → "bound cleanly on 8093"

# 2. `pkill -f "php -S 127.0.0.1:$RPORT"` matches NOTHING for that server: its command
#    line carries `-d session.save_path=…` between `php` and `-S`. Both Stop recipes now
#    kill by port, and both were run verbatim:
  8093 stopped by the documented command
  8083 stopped by the documented command
```

The first of those is the sharper one: **a check that reads a file nobody writes reassures for free**,
which is L-016's shape, written into a document in the same slice that cites L-016.

**Full test point.**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (206 tests, 1007 assertions)         # slice 1: 195/986  (+11 / +21)

$ php scripts/keel-verify ; echo exit=$?
  OK — 10 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase).
  exit=0
  # INDEX parity green after +2 rows (classes 100→101, actions 307→308, total 955→957)

$ composer audit -d installer
  No security vulnerability advisories found.

$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
  == UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary <scope>   # per scope, no default (L-016)
  installer/core + installer/admin  193 / 488 in 112 files   (baseline 193/488 — held)
  installer/plugins                 113 / 109 in  17 files   (baseline 113/109 — held)
  tests                               0 /   0  — 35 files scanned, ran (Time: line)
  installer/public                    0 /   0  —  2 files scanned, ran (Time: line)
  scripts + scripts/keel-verify       0 /   2  in   1 file   (baseline 0/2 — held)
```

`installer/core/app.php` was at **0 errors / 5 warnings before this slice and is at 0/5 after** —
measured by stashing the change and re-running, so the guard introduced none. All five pre-existing
warnings (one PSR1 side-effects, four long lines at `:688-691`) predate it.

**PROBE 4 — the refusal branch WAS driven end to end, and it proved more than the source-order test
could.** An earlier draft of this block claimed the branch was unreachable on a supported host. That
was wrong and is corrected here: the runtime cannot be lowered, but **the floor can be raised**.
Temporarily setting `AI_MIN_PHP_VERSION_ID = 99999` makes this host unsupported and drives the real
path:

```
typed exception : Klytos\Core\Ai\UnsupportedRuntimeException
message         : "AI features need PHP 9.99 or newer. This site runs PHP 8.3.12, so the AI engine
                   is disabled. The rest of Klytos is unaffected — ask your hosting provider to
                   upgrade PHP."
required/running: 99999 / 80312
action fired    : [["php_version_too_low",99999,80312]]
Guzzle loaded?  : false        <-- the require_once was NEVER reached
```

Four things this establishes that nothing else did:
1. the exception is the **typed** one, not a bare `\RuntimeException`;
2. **`__()` resolves in the throwing path** — the message is the catalogue value with both
   placeholders substituted, not a raw key and not an "undefined function" fatal. This was the
   sharpest risk in the slice (NEW-18: the *global* `__()` exists only under `admin/`, and one of the
   three callers is an MCP tool that never loads `admin/bootstrap.php`);
3. the **action fires before the throw**, with the right payload, and cannot influence the outcome;
4. `GuzzleHttp\Client` is **not loaded** — a **runtime** proof of the ordering, independent of the
   source-order assertion in probe 3.

**And the message half is now pinned permanently, not only probed.**
`AiRuntimeGuardIntegrationTest::testTheRefusalMessageResolvesAndSubstitutesBothPlaceholders()`
asserts the key resolves and both placeholders are substituted. `I18n::get()` returns the **key
itself** when it cannot resolve one, so "the result is not the key" is precisely the property that
separates a real translation from a silent miss. Proven to fail by removing the `ai` domain from
`en.json`:

```
  FAIL "The catalogue key did not resolve — I18n returns the key verbatim when it cannot find it,
        so the refusal would show a raw key to the operator."
  (key restored → OK, 11 tests, 22 assertions)
```

**What remains genuinely unproven, stated rather than implied (L-014):** nobody has run this on a
real PHP 8.1 or 8.2 host. Every property of the refusal has now been observed — but observed with the
floor moved, not with the runtime moved. The two differ only in `PHP_VERSION_ID`, which is the single
value the policy reads, so the gap is small; it is not zero, and it is not claimed to be.

#### Post-review re-verification (slice 2)

Both subagents ran on the finished diff, docs included (L-015). **The `security-auditor` returned no
blocking findings; the `code-reviewer` returned one, it was correct, and it was this slice's own
subject turned against it.**

**BLOCKING — the two new integration tests would have turned CI's PHP 8.2 leg red**, for behaviour
that is *correct* on 8.2. Measured by simulating the leg rather than reasoning about it — raising
`AI_MIN_PHP_VERSION_ID` so this host counts as unsupported:

```
# BEFORE the fix, on a simulated sub-floor runtime
  Tests: 206, Assertions: 884, Errors: 7, Failures: 4
  broken: AiRuntimeGuardIntegrationTest (2)   <- this slice
          VendorAiCompatibilityTest    (3)   <- slice 1
          ChatEngineToolListTest       (3)   <- LATENT SINCE SPRINT 2 SLICE 3
  (3 of the 4 "failures" are simulation artifacts — the unit tests compare against the
   constant being moved; on a real 8.2 host the constant is 80300 and they pass.)

# AFTER the fix, same simulation
  Tests: 206, Assertions: 883, Failures: 3, Skipped: 8      <- the 8 now SKIP
  (the 3 remaining are the same simulation artifacts)

# group selection, both directions
$ vendor/bin/phpunit --group ai-runtime            OK (8 tests, 124 assertions)
$ vendor/bin/phpunit --exclude-group ai-runtime     OK (198 tests, 883 assertions)
```

**Why skipping alone was NOT the fix.** CI promotes any skip to a hard failure (D-045) so an
un-seeded playground cannot hide — a rule that must keep meaning exactly that. So the 8 carry
`#[Group('ai-runtime')]` and the 8.2 leg **excludes** them explicitly, while
`IntegrationTestCase::requireAiRuntime()` (built in the shape of the existing `requirePlayground()`,
not as a second mechanism) still skips them for a developer running the suite locally on 8.1/8.2.
The group is applied **per method** in `AiRuntimeGuardIntegrationTest` so the one test asserting only
that the refusal *message* resolves keeps running on 8.2 — the runtime where that message is what an
operator actually sees.

**Why none of this had ever been observed: CI has never run — not once.** The workflow was written in
Sprint 1 slice 9 and every commit since (29) is unpushed by standing instruction. Recorded as
**L-022**: a workflow that is never triggered checks nothing, which is **L-019 one level out** — a
seam with no subscriber and a workflow with no run fail identically.

**Two non-blocking items taken:**

```
# 1. The advisory job reported GREP's exit status, so "no network" would have been
#    announced as "advisories found". Now three distinguishable outcomes, each proven:
   clean       -> CLEAN — no advisories
   advisories  -> WARNING — advisories found
   infra       -> WARNING — audit did not complete, NOTHING verified
   (real run   -> CLEAN)

# 2. A stale line reference this diff itself created: inserting ~107 lines into app.php
#    moved getChatEngine(), leaving `app.php:1009` wrong in two docs. Both now name the
#    method rather than a line, so the next insertion cannot rot them again.
```

**One non-blocking item REFUTED, and the refutation is the more useful record.** The reviewer asked
for the new exception's `parent::__construct( $message )` to lose its inner-paren spaces to match its
directory neighbours. `docs/03-technical-plan.md` §3 says spaces inside parentheses are the project's
style and **"do not 'correct' it"**, and `.claude/rules/code-style.md` names that section as the source
of truth on conflict. The change was applied before checking, then reverted — **L-013 and L-015 in one
move**: a reviewer's suggestion is a hypothesis, and a recorded convention outranks a neighbouring
file's habit.

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (206 tests, 1007 assertions)
$ php scripts/keel-verify
  OK — 10 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase).
$ composer audit -d installer
  No security vulnerability advisories found.
$ vendor/bin/phpcs --standard=phpcs.xml tests installer/core/ai
  39 files, 0 errors / 1 warning — the warning is pre-existing at chat-engine.php:401, not this slice's
```

# Sprint 4 — the hook mutation contract, and owner recovery

### Slice 1 — actions are fire-and-forget, enforced; page data gets a real filter — evidence (commands and output, 2026-07-25)

**Kickoff step 3 — the playground boots from its documented commands.**

```
$ export KPORT=8083; nc -z 127.0.0.1 $KPORT || echo "free"
  free                              (8080 squatted again — seventh consecutive session)
$ php -S 127.0.0.1:$KPORT -t . scripts/dev/router.php > /tmp/klytos-kport.log 2>&1 &
$ grep -i 'failed to listen' /tmp/klytos-kport.log || echo "bound cleanly"
  bound cleanly on 8083
$ lsof -nP -iTCP:8083 -sTCP:LISTEN            # L-021: is it OUR process?
  php83  35717  joseconti  ... (LISTEN)       # started by this session
$ curl -s -o /dev/null -w "%{http_code}" .../installer/admin/     →  302   (documented)
$ curl -s -o /dev/null -w "%{http_code}" -X POST .../installer/mcp →  401   (documented)
$ curl -s -D - .../installer/admin/ | grep -i "^Server:"
  (none)                             # L-011 tell: PHP's built-in server, not a squatter
```

**PROVEN TO FAIL FIRST (L-016) — the RED baseline before any production code was written.**

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter HooksTest
  FFFF......F   11 / 11 — 5 failures:
   1) testAByReferenceActionListenerIsRefusedAtRegistration — exception not thrown
   2) testAByReferenceFilterListenerIsRefusedAtRegistration — exception not thrown
   3) testARefusalNamesTheHookTheParameterAndWhereTheCallbackWasDeclared — none thrown
   4) testTheByReferenceParameterIsCaughtInAnyPosition        — exception not thrown
   5) testCoreItselfRegistersNoByReferenceListener            — ['core/x402-bootstrap.php']
  The 6 positive controls PASSED against the unfixed tree — they are regression cover,
  not new behaviour, which is what makes them a control (L-008).

$ XDEBUG_MODE=off vendor/bin/phpunit --filter HookMutationTest
  FFF   3 / 3 — 3 failures:
   1) testANewPageInheritsItsPostTypeX402Default
      "PRECONDITION FAILED: the post type does not carry x402_default_enabled, so no page
       could ever inherit it. This is a defect in the post-type save path, not in the hook."
   2) testCreatingAPageEmitsNoPhpDiagnostic
      + 0 => 'Klytos\Core\App::{closure}(): Argument #1 ($data) must be passed by
              reference, value given'                        ← NEW-03 reproduced in a test
   3) testAListenerCanModifyPageDataAndTheChangeIsPersisted
      expected 'set-by-listener-on-create', got ''
```

The precondition guard in (1) is what **separated NEW-36 from NEW-03 before either was fixed** — it
fired empirically rather than being deduced from reading the allow-list.

**The by-reference variadic was refuted by running PHP, not by argument** (probe, no repo files
touched):

```
literal string / int / null / array literal   →  Error: could not be passed by reference
concatenation / ternary / class constant      →  Error: could not be passed by reference
?? expression                                 →  Error: could not be passed by reference
function-call result                          →  Notice: Only variables should be passed…
plain $var / $obj->prop / $arr['k']           →  OK
UNDEFINED array key / variable                →  OK — and silently CREATES it
```

36+ real call sites pass one of the fatal shapes, starting with `page-manager.php:86` (`'create'`).

**After the fix — full suite, with `failOnWarning="true"` newly enabled:**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (220 tests, 1026 assertions)            # sprint start: 206 / 1007
```

`failOnWarning` matters here beyond bookkeeping: it means the NEW-03 warning's absence is now
asserted across **all 220 tests**, not only in the one test that looks for it.

**Lint — all five D-025 baselines, measured per scope with NO default value (L-016):**

```
$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
  A TOTAL OF 192 ERRORS AND 488 WARNINGS WERE FOUND IN 112 FILES     (was 193/488 — IMPROVED by 1)
$ … installer/plugins   →  113 ERRORS AND 109 WARNINGS IN 17 FILES   (held exactly)
$ … tests               →  37 files, no TOTAL line = 0 / 0           (was 35 files; held)
$ … installer/public    →  2 files, no TOTAL line = 0 / 0            (held)
$ … scripts scripts/keel-verify → 0 ERRORS AND 2 WARNINGS IN 1 FILE  (held exactly)
```

A first attempt at this measurement piped through `grep … | head -1` and printed nothing for two
scopes. It was re-run directly rather than defaulted to zero — the L-016 rule applied to the
instrument, in the slice that ends a defect L-016 was written about.

**keel-verify and the upgrade path:**

```
$ php scripts/keel-verify
  OK — 10 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase)
  incl. PASS docs/api/INDEX.md summary counts match its rows      (102 / 308 / 119, total 960)
        PASS docs/api/INDEX.md parity: every row has its doc, every doc its row
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
  == UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)
```

**Real functional verification on the production request path** (not only the suite):

```
$ curl -u owner:<app-pw> -d '{…"klytos_create_page"…}' http://127.0.0.1:8083/installer/mcp
  {"result":{…"success": true…}}
$ curl … klytos_get_page … | keys
  … 'x402_enabled'                    ← PRESENT. Before the fix the key was absent entirely,
                                        because the listener's write was discarded.
$ grep -icE "warning|fatal|notice|deprecated" /tmp/klytos-kport.log
  0                                   ← every page create used to log the NEW-03 warning
```

**After the review cycle — both subagents on the finished diff, docs included (L-015):**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (221 tests, 1029 assertions)     # +1 test: the reserved-key guard the review produced

reserved-key guard, proven to fail without the hardening (TEMP-BREAK, then restored):
  1) testTheUpdatableFieldsFilterCannotOpenAReservedKey
     "A filter opened the reserved `builtin` key — that is mass assignment."

widened interlock scan, proven in both directions by planting an offender the OLD scan
would have missed entirely (installer/admin/, outside every catch and every test):
  planted  → + 0 => 'admin/zz-probe.php'   FAILURES!
  removed  → OK (1 test, 2 assertions)     tree left clean

scan pattern proven against 9 cases (helper + Hooks:: direct, function + arrow fn,
multi-line signature, second position; and MUST-NOT: by-value, use(&$x), unrelated &$):
  all directions correct
```

### Slice 2 — owner recovery from the CLI — evidence (commands and output, 2026-07-25)

**PROVEN TO FAIL FIRST (L-016), and two of the five failed for the WRONG reason until tightened.**

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter OwnerRepairTest      # first draft
  Tests: 5, Failures: 3      ← testItRefusesWhenAnOwnerAlreadyExists and
                               testItRefusesIncompleteArguments PASSED against a tree
                               where the command does not exist, because dispatch()
                               also reports success=false for an UNKNOWN command.
  → tightened to assert the refusal's REASON (L-012)
$ … after tightening
  Tests: 5, Failures: 5      ← all five now fail for the right reason
```

**THE DESIGN WAS THEN REFUTED IN REVIEW AND REBUILT — see L-024.** The first version took
`--username`/`--password` and called `UserManager::create()`. `Auth::login()` validates against
`config['admin_user']` and `config['admin_pass_hash']`, never the record, so it minted an owner
nobody could log in as — and `findOwner()` returning non-null then made the command refuse forever.
The test written to prevent that asserted `UserManager::authenticate()` (the manager) instead of
`Auth::login()` (the gate), and passed against a command that restored nothing.

Measured, which is what settled the redesign:

```
$ php -r '…dump config keys…'
  admin_user       owner
  admin_pass_hash  present (60 chars)
  admin_email      owner@playground.test
$ grep -n admin_email scripts/dev/upgrade-assert.php
  131:    unset( $config['admin_email'] );      ← ONLY the email is lost
```

So the missing piece is the email, not the identity. The command now supplies it and runs the
product's own `migrateFromV1Config()`.

**Two guards fired on the new tests and both were right:**

```
D-039 config guard:
  "This test mutated installer/config/config.json.enc while App was already booted…"
  → #[RunInSeparateProcess] did NOT silence it (the guard detects the mutation, not the staleness).
    Resolved by passing the email config ALREADY holds → the write is net-zero, the command still
    takes the same branch. Suppressing the guard for this file was rejected.

L-010 positive control, on the redaction test:
  "Failed asserting that '[]' contains 'owner:repair'"
  → history was EMPTY: execute() re-demands 2FA after 10 min of terminal inactivity and returns
    BEFORE the history/audit writes. Without the control, the test would have asserted "the secret
    is not in the history" about a history nothing ever wrote to.
```

**After the redesign — full suite, lint, keel-verify:**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (227 tests, 1059 assertions)                      # slice 1 closed at 221 / 1029
$ vendor/bin/phpcs … installer/core installer/admin
  A TOTAL OF 192 ERRORS AND 488 WARNINGS IN 112 FILES  # held
$ vendor/bin/phpcs … tests
  0 errors / 0 warnings   (grew to 1 after the redesign — a stray blank line — fixed, re-measured)
$ php scripts/keel-verify
  OK — 10 check(s) passed, 2 warning(s) (owned by another phase)
  incl. PASS locale catalogues agree on their key set (120 files across 6 sets)
        PASS docs/api/INDEX.md parity   (CLI commands 26 -> 27, total 962)
```

**Real functional verification over the REAL CLI:**

```
$ php installer/cli.php owner:repair --email=x@example.test   ; echo $?
  Error: This install already has an owner (owner). Nothing was changed.
  1
$ php installer/cli.php owner:repair --email=not-an-email     ; echo $?
  Error: Usage: owner:repair --email=<address>
         A valid email address is required. The existing password is unchanged.
  1
$ php installer/cli.php owner:repair                          ; echo $?
  1
$ php installer/cli.php help | grep owner:repair
      owner:repair            Restore the owner account on an install whose owner record is missing
```

The first attempt at that exit-code measurement piped through `head -2` and reported `EXIT: 0` —
`$?` after a pipeline is the exit of `head`. Re-measured without the pipe. L-016 on the instrument,
in the same slice that recorded L-024 about the same class of error.

**`__()` proven to resolve on this path** (NEW-18 made it the sharpest risk): the tests assert on
`--email` and on the substituted username, neither of which appears in the catalogue KEYS — a miss
returns the key verbatim and would have failed them.

# Sprint 5 — authentication

### Slice 1 — the gate consults the user record — evidence (commands and output, 2026-07-25)

**PROVEN TO FAIL FIRST (L-016), per test rather than in aggregate — and TWO of the ten passed
against the unfixed tree, which is recorded rather than glossed.** The source changes were stashed
(`git stash push -- installer/`) with the tests left in place:

```
$ git stash push -- installer/ && XDEBUG_MODE=off vendor/bin/phpunit \
    --filter 'AuthLoginTest|McpActorResolutionTest' --testdox
  ✘ Every seeded role logs in through the real gate          ← 'account_locked:15' vs 'login_failed'
  ✘ A rotated password reaches the gate and the old one stops working
  ✔ A suspended account is refused ... same message           ← PASSED FOR THE WRONG REASON, see below
  ✘ Suspending an account ends its live session
  ✘ Locking one account does not lock another
  ✘ The lockout state is stored inside the install
  ✘ The owner cannot be suspended
  ✔ A non owner can still be suspended                        ← the negative control; correct
  ✘ A valid credential whose user is gone resolves to no actor
  Tests: 15, Assertions: 36, Failures: 7
$ git stash pop
```

- **The suspended-account test passed against the unfixed tree for entirely the wrong reason:** there
  `editor` cannot log in whether suspended or not (NEW-11), so all three refusals were trivially
  identical and the assertion observed nothing. A **positive control** was added — the account must
  log in BEFORE it is suspended — which fails against the unfixed tree and makes the refusal
  afterwards evidence (L-010, L-016).
- **`A non owner can still be suspended` passes in both trees by design.** It is the negative control
  for the owner guard: without it, a guard that refused *every* status change would look identical to
  the correct one.
- The first failure line is itself the defect on trial: against the unfixed tree the four wrong-password
  attempts of test 1 all land in **one global bucket**, so the fourth account is locked out by the
  first three — `account_locked:15` where `login_failed` was expected.

**A DEFECT IN MY OWN TEST, caught by running it — the L-016 shape.**

```
✘ The lockout state is stored inside the install
  A lockout file was written into the shared temp directory:
  /var/folders/.../T/klytos_lockout_72122ce96bfec66e2396d2e25225d70a.json
```

The assertion demanded the temp directory contain **no** `klytos_lockout_*` file. It failed on a file
the **old** implementation had written minutes earlier during the prove-it-fails run above — it was
measuring *history*, not *behaviour*, and would have failed the same way on any real install that had
ever run a previous version. Rewritten to compare the glob **before** against **after** a failed
login, so it observes what this code does rather than what some code once did.

**Full suite, after — and again after the review cycle added three more tests.**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (240 tests, 1124 assertions)          ← sprint start: 227 tests / 1059 assertions
$ XDEBUG_MODE=off vendor/bin/phpunit      # after the review fixes
  OK (243 tests, 1130 assertions)
```

**THE REVIEW CYCLE — one BLOCKING finding, correct, and it was my own guard defeating itself.**

Both subagents ran on the finished diff, docs included (L-015). Both reported having **no shell**, so
every number below was measured in the main session.

```
BLOCKING (code-reviewer), re-derived against source before acting (L-013):
  UserManager::update() processes 'role' BEFORE 'status' in $updatable and assigns
  $user[$field] inside the same loop — so a guard reading $user['role'] saw 'admin'
  by the time it ran:
      update( $ownerId, [ 'role' => 'admin', 'status' => 'suspended' ] )
  demoted AND suspended the owner in one call, straight through the check written to
  prevent exactly that. Reachable owner-only, incl. over MCP via klytos_update_user.
  → fixed by comparing $oldRole (already captured for the role-change hook)
  → second guard added from the auditor's finding 5: the owner's role can no longer
    be REMOVED through update() either, so transferOwnership() is the one path and
    an install cannot be left with zero owners
```

The nuance neither reviewer checked: the combined-call bypass produced a **recoverable** install
(zero owners → `findOwner()` null → `owner:repair` proceeds), while the single-field suspend the
guard did block produces the **unrecoverable** one. The guard was covering the worse case by luck.

**NEW-39 — the timing oracle, MEASURED before and after rather than reasoned about.**

```
$ php <probe>   # median of 12 runs per case, seeded playground
BEFORE                                     AFTER
active + wrong password     218.98 ms      217.55 ms
suspended + right password    0.65 ms      219.13 ms
no such user                  0.64 ms      218.05 ms
```

340× before; indistinguishable after. `authenticate()` returned early for an unknown username **and**
for a non-active account, so only *"exists and is active"* reached `password_verify()`. Pre-existing
code, new exposure — until D-056 it was only reachable from re-auth behind a session. It also made
this slice's own `docs/reference/authentication.md` assert *"Nothing in the response distinguishes
them"*, the **L-002** defect. **The two reviewers described it differently and one was wrong**
(**L-023**): the `code-reviewer` said "no bcrypt when the username matches no record", omitting the
suspended case; the `security-auditor` had the real split. Settled by measuring.

The fix compares against another stored record's hash rather than a committed literal — the cost
matches exactly, no bcrypt string enters the repo, and there is no first-call-in-the-process outlier,
which under `php -S` (a fresh process per request) would have **inverted** the oracle instead of
closing it.

**The three review-driven tests, proven in both directions:**

```
$ git stash push -- installer/core/user-manager.php && phpunit --filter '<the three>' --testdox
  ✘ The owner cannot be suspended by demoting in the same call
  ✘ The owners role cannot be removed through update
  ✘ A failed login costs the same whoever the username belongs to
  Tests: 3, Failures: 3
$ git stash pop && phpunit --filter '<the three>' --testdox
  ✔ ✔ ✔   OK (3 tests, 6 assertions)
```

**Recorded and NOT fixed:** **NEW-40** (the lockout's read-modify-write is not atomic; nothing
throttles the login endpoint — the NEW-20 shape in a second subsystem, with
`ActionScheduler::acquireLock()` named as the fix shape) and **NEW-41** (a suspended user's OAuth
access token keeps working for up to an hour — the one credential type where suspension does not take
effect; now stated in `authentication.md`'s suspension table).

**Two things the EXISTING suite caught before any new test was written** — which is why it was run
first:

```
1) OwnerRepairTest::testTheRecoveredInstallCanActuallyBeLoggedIntoThroughAuthLogin
   ArgumentCountError: Too few arguments to Auth::recordFailedAttempt(), 0 passed
   → a call site missed when the lockout gained its per-account parameter. Fixed; every
     call site then re-verified by grep rather than by re-running alone.

2) McpActorResolutionTest::testAValidCredentialWhoseUserIsGoneResolvesToNoActor
   "The credential itself is still valid." Failed asserting that false is true.
   → a REAL behaviour change: an orphan application password now fails at
     authentication (401) instead of authenticating and being denied by a null
     actor (403). Both fail closed; the layer moved. Recorded as implementation
     note 1 on D-056 BEFORE the test was touched, then the test was corrected to
     assert the stricter property — never weakened.
```

**`keel-verify` — 10 checks, exit 0, the 2 WARNs owned by Phase 7.**

```
$ php scripts/keel-verify
  PASS  authorization gate covers every admin surface (64 files)
  PASS  the central gate is invoked from admin/bootstrap.php
  PASS  docs/api/INDEX.md summary counts match its rows
  PASS  docs/api/INDEX.md parity: every row has its doc, every doc its row
  PASS  locale catalogues agree on their key set (120 files across 6 sets)
  PASS  no placeholder copy in distributable surfaces (470 files)
  PASS  changelog order oldest → newest (1 entry — ordering not yet exercised)
  PASS  every registered MCP tool has a capability-map entry (172 tools)
  WARN  version touchpoints in sync (5 touchpoints)      ← H-01, Phase 7
  WARN  runtime assets survive the release archive (16 guides)  ← NEW-27, Phase 7
  OK — 10 check(s) passed, 2 warning(s) carrying 9 note(s) (owned by another phase)
```

**Upgrade from the REAL v0.30.1 — and this sprint gave that test a new assertion, because it is the
one property a real operator can lose.** Everything the script asserted before was about the record
and its permissions; nothing asserted **access**. `upgrade-assert.php` now logs in through
`Auth::login()` with the password the *previous version* was installed with (L-024: through the exact
function that grants access, wrong password first per L-010):

```
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
   PASS  the upgraded owner still holds owner-only permissions
   PASS  the upgraded install refuses a wrong password
   PASS  the upgraded owner can LOG IN with the previous version's password (D-056)
   PASS  login resolved the upgraded owner's own id
== UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)
```

Those three messages were **reworded before this row was written**: `check()` prints its message on
PASS, so the first drafts ("the upgraded install accepts a WRONG password", "the upgraded owner
CANNOT LOG IN") made a *passing* run assert things that are false. The L-002 defect, in the output of
a test written to prevent one.

**Lint — all five D-025 baselines held exactly, measured per scope with no default value (L-016).**

```
$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
  A TOTAL OF 192 ERRORS AND 488 WARNINGS WERE FOUND IN 112 FILES     ← baseline 192/488
$ … installer/plugins   → 113 ERRORS AND 109 WARNINGS IN 17 FILES    ← baseline 113/109
$ … tests              → 40 files, no violations                     ← baseline 0/0
$ … installer/public   → 2 files, no violations                      ← baseline 0/0
$ … scripts/keel-verify scripts/dev → 0 ERRORS AND 2 WARNINGS IN 1 FILE ← baseline 0/2
```

The first attempt at that table piped each scope through `grep -E 'A TOTAL OF|^Time'` and printed a
fallback string for two scopes; it also passed two multi-word scopes as one quoted argument, so
`phpcs` reported *"The file does not exist"* and the loop reported nothing at all. Re-measured by
reading each scope's real output. **A measurement with a fallback is the defect L-016 records**, and
it appeared again in the same project three sprints later.

### Slice 2 — passkey second-factor login completes — evidence (commands and output, 2026-07-25)

**PROVEN TO FAIL FIRST (L-016): all four tests red before the `$preAuthScripts` entry existed** —
which is the ordering proof, since the exemption is the last step by design.

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter PasskeyLoginTest --testdox   # before step 4
  ✘ Registration is refused while two factor is merely pending   (401 from the auth guard, not 403)
  ✘ The authentication challenge is reachable while two factor is pending
  ✘ A passkey completes a second factor login
  ✘ A tampered assertion is refused
  Tests: 4, Failures: 4
$ … after step 4
  OK (4 tests, 19 assertions)
```

**THE REVIEW CYCLE FOUND THAT THIS SLICE HAD NOT CLOSED NEW-09 AT ALL.**

The `security-auditor` reported that the shipped page's request could never pass CSRF validation.
**Reproduced by running, not by reading** — a request built with exactly `login.php`'s headers
(`Content-Type: application/json` only, token inside the JSON body):

```
$ XDEBUG_MODE=off vendor/bin/phpunit --filter testTheEndpointAcceptsTheTokenTheShippedPageActuallySends
  ✘ The endpoint refused the CSRF token the shipped page sends
    (body: {"error":"Invalid CSRF token"})
    Failed asserting that 403 is identical to 200.
```

`Helpers::verifyCsrf()` reads `$_POST`, `X-CSRF-Token` and `$_GET`; PHP does not populate `$_POST`
for a JSON body. So passkey registration and passkey login were **both unreachable in a browser** —
and the whole suite was green because `AdminHttpTestCase::postJson()` adds an `X-CSRF-Token` header
the shipped page never sends. **The harness was quietly repairing the product it was measuring**, with
the correct diagnosis written into its own docblock as a workaround. L-016 one turn further out.

The tell was in the endpoint the entire time: `$csrf = $input['csrf'] ?? '';` was read and never used.
Fixed on both sides (the endpoint honours the body token; all three `fetch()` calls now send the
header), and pinned by the hand-built test above — the only one here that reproduces the shipped page
byte for byte.

**Second BLOCKING finding (`code-reviewer`), also correct:** `klytos_do_action( 'user.passkey_enrolled' )`
sat inside the registration `try/catch`, so a plugin throwing `RuntimeException` would have answered
`{"success": false}` / 400 for a credential that was **already stored** — contradicting this slice's
own comment claiming neither notification could fail the enrolment. The success response is now sent
before the notification block, which has its own `catch ( \Throwable )`.

**Full verification, after both fixes.**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (248 tests, 1152 assertions)        ← slice 1 close: 243/1130; sprint start: 227/1059
$ php scripts/keel-verify
  OK — 10 check(s) passed, 2 warning(s) …   (locale parity 120 files: the 2 new keys ×20;
                                             INDEX 962 → 963 after the new action row)
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
  == UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)
     — load-bearing here because the $preAuthScripts change touches the auth guard
       of EVERY admin surface, not just this endpoint
$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
  A TOTAL OF 192 ERRORS AND 488 WARNINGS WERE FOUND IN 112 FILES   ← baseline held
```

The core+admin baseline **grew to 194 mid-slice** (a multi-line `if` in the new guard) and was brought
back to 192 by fixing the file rather than by rebaselining — D-025 says a touched file is left clean.

**Recorded and NOT fixed:** **NEW-42** — four rough edges in the now-reachable assertion path (no
clone detection from the sign counter, no `origin` check, no length guard before reading `authData`
offsets, and the setup-wizard skip-list not extended alongside `$preAuthScripts`).

# Sprint 6 — hardening

### Slice 1 — the counters are atomic, and the login endpoint has a ceiling — evidence (commands and output, 2026-07-26)

The code, the i18n, the docs and the primitive's own unit tests landed in `282a3cf` with three tests,
this row and the two review passes explicitly still owed. This is that debt paid, and **the three
owed tests were not bookkeeping — one of them found a live defect in the slice's own code.**

**PROVEN TO FAIL FIRST (L-016), one TEMP-BREAK per test rather than in aggregate.** Each break
reproduces the pre-slice shape of exactly one mechanism, and the observed failure message is quoted
so a later reader can tell the test fired for its own reason and not for a neighbour's:

```
1. The IP ceiling removed  (login.php: `if ( $loginLimiter->isAuthBlocked(...) )` → `if ( false )`)
   $ XDEBUG_MODE=off vendor/bin/phpunit tests/Integration/LoginCeilingHttpTest.php
     ✘ testTheIpCeilingRefusesABurstOfInventedUsernamesThroughTheShippedForm
       "The attempt past the ceiling was served normally: the login endpoint has no IP ceiling."
     Tests: 3, Assertions: 27, Failures: 1     ← the other two are the positive control and the
                                                 source-parity check, which correctly do not depend
                                                 on the ceiling

2. NEW-44 reintroduced  (admin-gate.php: the `'security'` $source restored on the unmapped refusal)
   $ XDEBUG_MODE=off vendor/bin/phpunit tests/Integration/AdminGateHttpTest.php
     ✘ testAGateRefusalReachesTheLogFile
       "The gate refused and wrote nothing to any log file under …/installer/data. That is audit
        NEW-44: the refusal is discarded by Logger::write() because the $source is not \"core\"."
     Tests: 13, Assertions: 49, Failures: 1

   …and the same fix reverted on the OTHER refusal — the one no HTTP request can reach:
   $ XDEBUG_MODE=off vendor/bin/phpunit --filter \
       testNeitherGateRefusalLogsUnderASourceTheLoggerWillDiscard \
       tests/Integration/AdminGateMapTest.php
     ✘ "This gate refusal passes a third argument to klytos_log_*(), which is the $source: …"
     Tests: 1, Assertions: 3, Failures: 1

3. D-059's fail-closed branch removed  (auth.php: `if ($this->recordFailedAttempt($u) === null)`
                                        → `if (false) { $this->recordFailedAttempt($u); …`)
   $ XDEBUG_MODE=off vendor/bin/phpunit --filter \
       testAFailureThatCannotBeCountedRefusesInsteadOfPassingUncounted \
       tests/Integration/AuthLoginTest.php
     ✘ "The failure could not be recorded and the attempt was served anyway — that is a free,
        uncounted try for anyone able to provoke contention on the counter file."
     Tests: 1, Assertions: 4, Failures: 1
```

Breaks 2 and 3 were run under `--filter` and their counts are the FILTERED run's, written as
executed rather than as a whole-class figure nobody measured — the first draft of this row quoted
`Tests: 16, Assertions: 46` for break 3 beside a whole-file command, which was a number carried
rather than derived. L-015 applies to this document as readily as to any other.

Every break was reverted from a byte-for-byte backup and `git diff --stat` confirmed the file
identical to `HEAD` before continuing — the tree was never left holding a deliberate break.

**A DEFECT IN THIS SLICE'S OWN CODE, found by the first owed test and fixed in path.** The IP ceiling
answered **HTTP 429 with the words "Incorrect username or password"**. `login.php`'s throttle branch
set `$error = __( 'auth.too_many_attempts' )`, and the refusal-wording block below it then ran
unconditionally, took its `else` (because `'throttled'` does not start with `account_locked:`) and
**overwrote it with `auth.login_failed`**. So the key this slice added to all **20** catalogues could
never render, and an operator being throttled was told their password was wrong. Fixed by giving the
page exactly **one** place that turns a refusal into words — `$result['error']` is mapped in the block
below, and the throttle branch now sets no message at all. `keel-verify`'s locale check could not
have caught it: catalogue **parity** was perfect, and the key was simply unreachable. This is L-019's
family — a string that exists and never arrives.

**AN INSTRUMENT FAILURE CAUGHT AND NOT BELIEVED (L-008, L-016).** `testAGateRefusalReachesTheLogFile`
passed alone and **failed in the full suite**, reporting that the gate had written nothing. The
product was fine; the measurement was wrong. It asked the test process's own `Logger` for the logs
directory — and `Logger` caches that path per instance while this tier boots the App **once per
process** (D-030), so after a playground restore the test read directory X while the server, booting
fresh per request, wrote to directory Y. Fixed by not predicting the directory OR the file name at
all: the measurement globs `data/logs-*/debug-*.log` and diffs sizes. That is also the honest form of
the property — the refusal must reach *a log an operator can read*, not one particular path — and the
TEMP-BREAK above was re-run against the widened scan to confirm it still fails on unfixed code.

**Full verification.**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (262 tests, 1237 assertions)     ← slice-1 code landing: 255/1177; sprint start: 248/1152

$ php scripts/keel-verify
  OK — 10 check(s) run: 8 passed, 2 warning(s) carrying 9 note(s) (owned by another phase)
  [the 2 WARNs are H-01's version touchpoints and NEW-27's export-ignored guides — both Phase 7]

$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
  == UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
  A TOTAL OF 192 ERRORS AND 488 WARNINGS WERE FOUND IN 112 FILES     ← baseline held exactly
$ … installer/plugins   A TOTAL OF 113 ERRORS AND 109 WARNINGS WERE FOUND IN 17 FILES   ← held
$ … tests              (no total line; 44/44 files scanned, exit 0)                     ← 0/0 held
$ … installer/public   (no total line;   2/2 files scanned, exit 0)                     ← 0/0 held
$ … scripts scripts/keel-verify
                        A TOTAL OF 0 ERRORS AND 2 WARNINGS WERE FOUND IN 1 FILE          ← held
```

**The lint measurement was itself checked before being believed, for the second session running.** A
shell loop over the five scopes collapsed the two multi-path scopes into a single argument
(`ERROR: The file "installer/core installer/admin" does not exist`), and `tail -3` then cut the
summary's `A TOTAL OF …` line out of the two that did run — printing nothing where the answer was
192/488. Each scope was re-run directly, and for the two scopes that legitimately print **no** total
line at 0/0 the run was confirmed to have happened by its progress output (`44 / 44 (100%)`,
`2 / 2 (100%)`) rather than by reading the emptiness as a zero, exactly as L-016 requires.

**Reuse, per the standing rule.** No new HTTP harness: `LoginCeilingHttpTest` extends the existing
`AdminHttpTestCase` on the reserved port **8108**. No new lock-holding fixture: `file-lock-worker.php`
gained a `hold` mode rather than a sibling file. No new probe-file boilerplate: `AdminGateHttpTest`'s
existing unmapped-file test and the new logging test now share one `requestUnmappedProbe()` helper —
the duplicate was refactored away rather than added to.

**THE REVIEW CYCLE EARNED ITS COST FOR THE SEVENTEENTH CONSECUTIVE SLICE — no blocking finding, and
between the two passes it produced a measured correction to D-059's own claim, a FALSE claim in the
approved sprint plan, and three new audit findings.** Full account in D-059's review amendment; the
two that changed code:

- **The `security-auditor` hypothesised that the IP ceiling has the same check-then-act shape it
  replaces, and MEASURING it confirmed the hypothesis** — so D-059's "the ceiling closes the ~218 ms
  window" is narrowed to "bounds", with numbers. Bucket pre-filled to 9 of 10, one process per
  request, real `isAuthBlocked()` → 218 ms → `recordAuthFailure()` sequence:

  ```
  6 requests, one at a time   ->  1 served (expected 1), bucket 10   ← the control
  6 requests, simultaneous    ->  6 served,              bucket 15
  12 requests, simultaneous   -> 12 served,              bucket 21
  ```

  No increment is lost (the bucket lands on exactly 9 + N — that is what the atomicity bought), so
  this is a check-then-act window rather than NEW-20's lost update. Recorded as **NEW-46**.
  **My own instrument was wrong twice before this number was trustworthy**: the probe's `require`
  path was broken and fataled loudly (last session's false 0/20, caught this time because it
  screamed), and then `grep -c` over newline-less result files reported `SERVED=1` while the bucket
  said 15 — two measurements of one event disagreeing, which is what exposed the counter as the
  broken half (L-023).
- **The `code-reviewer` found that the approved sprint plan's risk 1 claimed "the ceiling is
  filterable" and no such filter existed** — re-derived, zero `klytos_apply_filters` in either file.
  Added rather than softened, because a mitigation the plan promises for a recorded risk (NEW-17) is
  part of the plan. `auth.login_ip_blocked`, at the login call site only, **proven with a real
  plugin loaded by the product's own `PluginLoader` in the SERVER process** — a filter registered in
  the test process would prove nothing about the process serving the request (L-019) — and observed
  failing against the un-filtered code first.

Also fixed from the review: `docs/reference/authentication.md`'s "Known limits" still declared both
of this slice's fixes to be open **and recommended `ActionScheduler::acquireLock()`, the remedy
D-059 refuted on two grounds** — a document contradicting its own body 100 lines above. Recorded and
not fixed: **NEW-47** (the password-login POST has no CSRF check — pinned by this slice's own
source-parity test, so it is now a decision rather than an omission) and **NEW-48** (the 2FA
emergency-email branch overwrites its own error message — implementation note 1's defect one branch
away). **NEW-17 gained the bypass half it never covered.**

**Coverage of the sprint's acceptance criteria by this slice:** 1 (`FileLockTest::testConcurrentIncrementsAreNotLost`,
landed with the code), 2 (`LoginCeilingHttpTest`), 3 (`FileLockTest` for the primitive's two fail
directions, `AuthLoginTest::testAFailureThatCannotBeCountedRefusesInsteadOfPassingUncounted` for the
product's), 6 (`AdminGateHttpTest::testAGateRefusalReachesTheLogFile` plus
`AdminGateMapTest::testNeitherGateRefusalLogsUnderASourceTheLoggerWillDiscard` for the twin no
request can reach), 7 (above).

### Slice 2 — suspension takes effect on OAuth too — evidence (commands and output, 2026-07-26)

**PROVEN TO FAIL FIRST (L-016), against the unfixed tree, before a line of the fix was written.** The
new class was run whole, so the positive control and the three refusals were observed together:

```
$ XDEBUG_MODE=off vendor/bin/phpunit tests/Integration/OAuthSuspensionHttpTest.php
  .FFF                                                                4 / 4 (100%)

  1) testSuspendingTheUserRefusesTheSameTokenOnTheNextRequest
     "a suspended user's OAuth token must be refused at AUTHENTICATION (401), not at the gate
      (403) — D-060 puts it at the layer D-056 put application passwords"
     Failed asserting that 200 is identical to 401.        ← the defect, live: the token kept working

  2) testReactivatingTheUserRestoresTheSameToken
     "the suspension must take effect first"
     Failed asserting that 200 is identical to 401.

  3) testAnOAuthTokenForADeletedUserIsRefusedAtAuthenticationToo
     "an unattributable OAuth token denies at authentication"
     Failed asserting that 403 is identical to 401.        ← the gate WAS refusing it; the layer moved

  Tests: 4, Assertions: 18, Failures: 3
```

The fourth test — `testAnOAuthTokenForAnActiveUserIsAccepted` — **passed against the unfixed tree,
and that is recorded rather than glossed**: it is the positive control. Without it, all three
refusals above could be passing because the minting flow, the transport or the tool was broken, which
is precisely the "right answer for the wrong reason" L-016 was written about. After the fix:
`OK (4 tests, 25 assertions)`.

The token is minted through the product's **own** OAuth flow — `createClient` → `handleAuthorize` →
`handleTokenRequest` with a real PKCE S256 verifier — never by writing a token record into storage
(**L-005**), because a hand-written record would bypass exactly the field (`user` on the token) the
fix reads.

**One existing test went red and it is a SPEC CORRECTION, recorded before it was touched.**
`McpActorResolutionTest::testOAuthTokenForAnUnknownSubjectResolvesToNoActor` asserted
`validate() === true` alongside `getActor() === null` — the old layer pinned in its precondition,
exactly as D-056's implementation note 1 described for the application-password credential one sprint
ago. The property it exists for is unchanged and is now satisfied more strictly, so the assertion was
**tightened, never weakened**: `validate()` must be **false** AND the actor null. Recorded as D-060
implementation note 1 first, then changed. A second method was added in the same class pinning the
resolver itself for a **suspended** subject, so the contract survives even if the check were ever
moved up into `server.php`.

**The review round added a sixth test and a finding.** Both subagents ran in parallel on the finished
diff, docs included (L-015), and neither returned a blocking finding. Two items were taken:

- **NEW-49, recorded after re-verifying the mechanism against source.** A refused OAuth request is an
  *authentication failure*, so it now feeds `RateLimiter`'s **IP-keyed** auth-failure bucket
  (`server.php:101`, `rate-limiter.php:105-138`) where a suspended user's token previously answered
  200 and fed nothing — so a suspended integration with a retry loop can push a shared NAT address
  past 10 failures/60 s and answer **429** to every other MCP client behind it. Not a new attacker
  capability (a garbage bearer token already costs the same one request), so severity LOW; recorded
  with its trigger and stated in the reference doc beside the behaviour that causes it.
- **A documented claim that had no test now has one.** D-060 implementation note 2 says a rejected
  OAuth token does not suppress an independently valid Basic credential. That was argued, not proven
  — the L-014 shape. `McpActorResolutionTest::testARejectedOAuthTokenDoesNotBlockAValidBasicCredential`
  drives it with a suspended subject's token in the `Authorization` header and an active account's
  application password in `PHP_AUTH_USER`/`PHP_AUTH_PW`, and was **proven in both directions**:

```
TEMP-BREAK  (token-auth.php: the fall-through replaced by `if ($actor === null) { return false; }`)
  $ XDEBUG_MODE=off vendor/bin/phpunit tests/Integration/McpActorResolutionTest.php
    ✘ testARejectedOAuthTokenDoesNotBlockAValidBasicCredential
      "A rejected OAuth token must not suppress an independently valid Basic credential."
      Failed asserting that false is true.
    Tests: 11, Assertions: 32, Failures: 1     ← exactly this test, nothing else
  reverted from a byte-for-byte backup; `cmp` confirms the file is identical to the pre-break version
```

Also taken: the line citations in D-060's own Decision line (`token-auth.php:227`,
`validate():132-139`) were correct when written at the kickoff and stale the moment this slice
inserted lines into the same file — corrected to name the method, the D-053 treatment, here and in
`sprints/sprint-6.md`.

**Verification (final, after the review follow-up):**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (268 tests, 1272 assertions)          ← 262/1237 at slice 1's close; 267/1266 before the review's test

$ php scripts/keel-verify
  OK — 10 check(s) run: 8 passed, 2 warning(s) carrying 9 note(s) (owned by another phase)
  (the two WARNs are H-01's version drift and NEW-27's stripped guides — both Phase 7's)

$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
  == UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary <scope>      (each scope run separately)
  installer/core installer/admin  A TOTAL OF 192 ERRORS AND 488 WARNINGS WERE FOUND IN 112 FILES
  installer/plugins               A TOTAL OF 113 ERRORS AND 109 WARNINGS WERE FOUND IN 17 FILES
  tests                           45 / 45 files processed, no violations           (0 / 0)
                                  (re-run after the review follow-up; still 45 / 45, still 0 / 0)
  installer/public                 2 / 2  files processed, no violations           (0 / 0)
  scripts scripts/keel-verify     A TOTAL OF 0 ERRORS AND 2 WARNINGS WERE FOUND IN 1 FILE
```

All five D-025 baselines held **exactly**. The three 0/0 scopes are recorded with their processed-file
counts on purpose: **phpcs prints no TOTAL line at 0/0**, so "no total" is indistinguishable from "the
run never happened" — the L-016 trap this project fell into last session. Each was re-run directly
and its progress line (`45 / 45 (100%)`) read as the proof it executed. The same session reproduced
that trap once more: a shell loop reported `exit=3` and no timing line for the `scripts` scope, and
running the identical command directly printed the baseline `0 ERRORS AND 2 WARNINGS`. The loop was
the broken instrument, again.

**Coverage of the sprint's acceptance criteria by this slice:** 4
(`OAuthSuspensionHttpTest::testSuspendingTheUserRefusesTheSameTokenOnTheNextRequest` for the refusal
and `testAnOAuthTokenForAnActiveUserIsAccepted` for the active half), 7 (above).

### Slice 4 — the two anonymous forms verify CSRF — evidence (commands and output, 2026-07-27)

A fourth slice, added to this sprint by user decision (**D-061**) and run before slice 3. Same
session as slice 2, which spanned midnight — the playground freshness check below covers both.

**THE PREDICTED FAILURE HAPPENED FIRST, AND IT WAS A TEST DOING ITS JOB.** Slice 1 wrote
`LoginCeilingHttpTest::testTheRequestThisClassSendsIsTheRequestTheShippedFormSends` specifically so
that adding a CSRF field to the login form would fail it. Adding the field:

```
$ XDEBUG_MODE=off vendor/bin/phpunit tests/Integration/LoginCeilingHttpTest.php tests/Integration/AuthLoginHttpTest.php
  ✘ testTheRequestThisClassSendsIsTheRequestTheShippedFormSends
    "The password form now emits a CSRF token that these requests do not send."
  Tests: 7, Assertions: 60, Failures: 1
```

**AND THE OTHER SIX STILL PASSED, WHICH IS THE FINDING OF THE SLICE (audit NEW-50).** A token-less
POST should have been refused the moment `klytos_verify_csrf()` was added to the password branch. It
was not. `Helpers::verifyCsrf()` resolves a missing field to `''`, `Auth::validateCsrf()` compared it
against a session that also held `''`, and:

```
$ php -r 'echo var_export( hash_equals( "", "" ), true );'
  true
```

So the new check passed for exactly the anonymous state the login and password-reset forms live in —
a guard that guarded nothing, in the slice written to add it. Fixed in `validateCsrf()` (the one
place that decides token validity, not at the call site) and recorded as **NEW-50**.

**PROVEN TO FAIL FIRST — three TEMP-BREAK cycles, one per mechanism**, each reverted from a
byte-for-byte backup and confirmed with `cmp`:

```
1. The login CSRF check removed  (login.php: `if ( ! klytos_verify_csrf() )` → `if ( false )`)
   ✘ testALoginPostWithNoCsrfTokenIsRefused                 Failed asserting that 302 is identical to 403.
   ✘ testAnEmptyTokenIsRefusedEvenWhenTheSessionHoldsNoneEither   … 302 … 403.
   ✘ testATokenFromAnotherSessionIsRefused                  … 302 … 403.
   Tests: 6, Assertions: 27, Failures: 3
   ← 302 IS the attack succeeding: a forged POST carrying the attacker's valid credentials logged the
     browser in.

2. validateCsrf()'s empty guard removed  (back to the bare hash_equals)
   ✘ testAnEmptyTokenIsRefusedEvenWhenTheSessionHoldsNoneEither   … 302 … 403.
   ✘ testALoginPostWithNoCsrfTokenIsRefused                        … 302 … 403.
   ✘ testThePasswordResetFormIsRefusedWithoutItsCsrfToken          … 200 … 403.
   Tests: 6, Assertions: 23, Failures: 3
   ← this is what makes NEW-50 load-bearing rather than tidy: with the checks in place and this guard
     gone, BOTH forms accept a token-less POST.

3. The reset-password CSRF check removed
   ✘ testThePasswordResetFormIsRefusedWithoutItsCsrfToken          … 200 … 403.
   Tests: 6, Assertions: 27, Failures: 1
```

**One assertion of mine was wrong and was corrected rather than kept.** The first version of the
no-token test asserted the refused response carried no `Set-Cookie: klytos_session`. It always does —
`bootstrap.php` starts a session on every admin request — so the assertion measured something the
product never does. Replaced with the property that matters: take the session the refusal handed back,
request the dashboard with it, and require a **302** to the login form. That asks "is the browser now
logged in?", which is the actual attack.

**The positive controls are the point of this slice, not a formality.** A CSRF fix that broke the
login form would lock every operator out of their own site, and one that broke password reset would
strand exactly the users who cannot log in. Both flows are driven the way a browser drives them —
GET the page, keep its session, post back its token — and the reset control goes further: it resets
the password over HTTP and then **logs in with the new one through the real form** (L-024).

**Verification:**

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  OK (276 tests, 1331 assertions)          ← 268/1272 at slice 2's close

$ php scripts/keel-verify
  OK — 10 check(s) run: 8 passed, 2 warning(s) carrying 9 note(s) (owned by another phase)
  PASS  locale catalogues agree on their key set (120 files across 6 sets)   ← the new auth.session_expired key ×20

$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
  == UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary <scope>      (each scope run separately)
  installer/core installer/admin  A TOTAL OF 192 ERRORS AND 488 WARNINGS WERE FOUND IN 112 FILES
  installer/plugins               A TOTAL OF 113 ERRORS AND 109 WARNINGS WERE FOUND IN 17 FILES
  tests                           46 / 46 files processed, no violations           (0 / 0)
  installer/public                 2 / 2  files processed, no violations           (0 / 0)
  scripts scripts/keel-verify     A TOTAL OF 0 ERRORS AND 2 WARNINGS WERE FOUND IN 1 FILE
```

All five D-025 baselines held **exactly**, including the core+admin scope after edits to `login.php`,
`reset-password.php` and `auth.php`.

**The review round added a third form, and requesting it found a fatal.** Both subagents ran in
parallel on the finished diff; the `code-reviewer` returned one BLOCKING finding and the
`security-auditor` reached the same conclusion independently: `core/mcp/oauth-authorize-view.php`'s
own `action=login` POST — the OTHER of the product's two `Auth::login()` call sites — had no CSRF
check either (**NEW-51**), which made this slice's new documentation sentence ("Both forms…") false as
written. Fixed in path. Proving it over HTTP is what found **NEW-52**:

```
$ curl "http://127.0.0.1:8114/installer/oauth/authorize?client_id=…&code_challenge=…"
  status=200
  Fatal error: Uncaught Error: Call to undefined function
  Klytos\Core\handleOAuthAuthorizeView() in …/installer/core/router.php on line 195
```

The router (namespace `Klytos\Core`) called the view's function unqualified; it is declared in
`Klytos\Core\MCP`, and PHP falls back to the GLOBAL namespace, never to a sibling. **The OAuth
consent screen has never rendered for anybody** — the authorization-code flow could not be completed
by any MCP client, ever. Confirmed byte-identical at HEAD before concluding anything, so it is
pre-existing. Both fixed in path, each with its own reverted TEMP-BREAK:

```
BREAK A  (the consent-screen CSRF check → `if (false)`)
  ✘ testTheOauthConsentScreenLoginIsRefusedWithoutACsrfToken     the forced login reached consent
  Tests: 2, Assertions: 6, Failures: 1

BREAK B  (the router call back to the unqualified name)
  ✘ testTheOauthConsentScreenStillLogsInWithItsToken
    "GET /installer/oauth/authorize?… issued no klytos_session cookie, so no CSRF token can belong
     to it."        ← formSession()'s own loud failure, reading the fatal page
  ✘ testTheOauthConsentScreenLoginIsRefusedWithoutACsrfToken
  Tests: 2, Assertions: 2, Failures: 2
```

**One more instrument failure of mine, caught by the test passing alone and failing in its class.**
The refusal test asserted the body did not contain `Authorize` — which is also the page's heading
("Authorize Application"), so it measured the chrome rather than the screen. Settled by driving the
sequence by hand (where it worked), dumping the real bodies, and switching to `value="authorize"` /
`value="login"`, which appear only in the consent and login forms. **L-018's shape**, and the same
alone-versus-in-class tell as slice 1's log-directory measurement.

**Coverage of the sprint's acceptance criteria by this slice:** none of the seven — this slice was not
in the approved plan. Its own acceptance is D-061: both anonymous forms refuse a POST without the
token they emit, both still work when submitted the way the shipped page submits them, and a token
minted for another session is refused.

### Slice 3 — the passkey assertion path — evidence (commands and output, 2026-07-27)

Closes audit **NEW-42**'s four items under **D-063**. The sprint's last slice.

**L-027 FIRST, BEFORE ANY CONTROL WAS WRITTEN.** The rule that lesson bought is *request the surface
once, over HTTP, as a client reaches it* — so both surfaces this slice hardens were requested before
they were touched:

```
$ curl -s -o /dev/null -w "GET status=%{http_code}\n" http://127.0.0.1:8115/installer/admin/login.php
  GET status=200                       # renders, with a name="csrf" field
$ curl -s -X POST http://127.0.0.1:8115/installer/admin/api/webauthn-challenge.php \
    -H 'Content-Type: application/json' -d '{"action":"auth_challenge"}'
  {"error":"Unauthorized"}   [401]     # reachable, answers its JSON contract
```

Neither fatals, so unlike slice 4 there was nothing hiding underneath. Also measured up front, because
item 4 needs it: this seed carries `setup_completed = true`, so the wizard branch is not reachable
without writing config.

**THE HEADLINE IS A MEASUREMENT, NOT AN ARGUMENT.** The sprint plan says clone detection must fire
only when both counters are `> 0`, because synced passkeys report `0` forever. That claim was tested
by installing the naive rule and running the class:

```
# TEMP-BREAK 1 — the naive rule: if ( $newSignCount <= $storedSignCount )
$ XDEBUG_MODE=off vendor/bin/phpunit --filter PasskeyLoginTest
  1) testASyncedPasskeyReportingZeroForeverStillCompletesLogin
     Failed asserting that 200 is identical to 302.
  Tests: 13, Assertions: 71, Failures: 1
```

Exactly one failure, and it is the synced-passkey login — while both clone tests still passed. That
is the shape a correct rule must have, and it is what a "must increase" implementation would have
shipped: a security fix refusing the second login of most authenticators in use (the D-044 trap).

**Five TEMP-BREAK cycles, each reverted from a byte-for-byte backup confirmed with `cmp`:**

```
# 2 — clone detection removed entirely
  1) testCloneDetectionFiresItsActionWithBothCounters   Failed asserting that true is false.
  2) testARepeatedCounterIsRefusedToo                   Failed asserting that 302 is identical to 200.
  3) testACounterRegressionIsRefusedWhenBothCounters…   Failed asserting that 302 is identical to 200.
  Tests: 13, Failures: 3        # the synced-passkey control stayed green — correct

# 3 — the assertion path's origin check removed (registration keeps its own)
  1) testAnAssertionFromAnotherOriginIsRefused          Failed asserting that 302 is identical to 200.
  Tests: 13, Failures: 1

# 4 — the length guard removed
  1) installer/core/two-factor.php:674  Uninitialized string offset 32
     * testATruncatedAuthenticatorDataIsRefusedWithNoPhpWarning
  Tests: 13, Assertions: 73, Warnings: 1        EXIT CODE = 1

# 5 — the basename setup-wizard skip-list restored
  1) testTheWebauthnEndpointAnswersJsonDuringAnIncompleteSetup
     The endpoint answered 302 (Location: /installer/admin/api/setup-wizard.php) …
  Tests: 13, Failures: 1
$ cmp installer/core/two-factor.php  …/two-factor.php.fixed   # restored, byte-identical
$ cmp installer/admin/bootstrap.php  …/bootstrap.php.fixed    # restored, byte-identical
```

Two things in break 4 are worth keeping. The warning is **exactly** the one the audit predicted
(`Uninitialized string offset 32`), and PHPUnit's summary line reads *"OK, but there were issues!"* —
so the honest signal is the **exit code 1** that `failOnWarning="true"` produces, not the word "OK". A
reader skimming the summary would have called that run green.

**Break 5 sharpened the audit entry rather than merely confirming it.** The redirect goes to
`/installer/admin/api/setup-wizard.php` — a path that does not exist — because it is built from
`dirname( $_SERVER['SCRIPT_NAME'] )` and the script lives in `api/`. The caller was redirected to a
404, not to the wizard. Corrected in the audit entry (L-015).

**Full harness:**

```
$ XDEBUG_MODE=off vendor/bin/phpunit            OK (284 tests, 1385 assertions)      # was 276 / 1331
$ php scripts/keel-verify                       OK — 10 check(s) run: 8 passed, 2 warning(s)
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
                                                == UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)
```

**Lint — each scope run DIRECTLY and separately, never through a shell loop (L-016, which has now
produced a bogus result in three separate sessions):**

| Scope | Result | Baseline |
|---|---|---|
| `installer/core installer/admin` | **191 errors / 488 warnings** in 112 files | 192/488 — **improved by 1**, never rebaselined |
| `installer/plugins` | 113 errors / 109 warnings in 17 files | 113/109 — exact |
| `tests` | no total line; **46 / 46 files processed** | 0/0 — exact |
| `installer/public` | no total line; **2 / 2 files processed** | 0/0 — exact |
| `scripts scripts/keel-verify` | 0 errors / 2 warnings in 1 file | 0/2 — exact |

The `-1` was **attributed by measurement, not assumed**: linting the pre-slice copy of the two touched
files showed `bootstrap.php` at 1 error / 2 warnings and it is now 0 / 2, so the improvement is the
`$currentScript` removal and nothing drifted elsewhere. The two 0/0 scopes are read by their
**processed-file count**, because phpcs prints no TOTAL line when there is nothing to report and a
silent run is indistinguishable from a clean one.

**Docs:** `docs/reference/authentication.md` gains "What the assertion path checks" and "The
setup-wizard skip-list", and three "Known limits" bullets (the `http://` vs `https://localhost`
allowance, and that a counter regression is not proof of a clone); `docs/api/INDEX.md` gains the
`user.passkey_clone_detected` row, Actions 311 → **312**, total 968 → **969**;
`.claude/skills/klytos-hooks-reference/references/complete-hooks.md` gains that hook **and
`user.passkey_enrolled`**, which had been missing since Sprint 5 slice 2 — the same table, the same
subject, so both went in (L-004).

**Coverage of the sprint's acceptance criteria by this slice:** criterion 5 in full — a
`signCount = 0` authenticator still logs in, a counter regression with both counters non-zero is
refused (and a repeated counter too), a wrong `origin` is refused, and a 32-byte `authenticatorData`
fails closed **with no PHP warning**. Criterion 7 (suite, keel-verify, upgrade, five baselines) is
evidenced above.

## Session-start freshness

At the **first** test point of every working session, the playground is booted from the commands in
`docs/playground.md` exactly as written — this validates the environment and the document in one
move — and `docs/playground.md` is stamped `last verified: <date>`. Instructions that no longer start
the playground are a defect caught here, not by the user.

| Date | Booted from documented commands | Result | Doc stamped |
|---|---|---|---|
| 2026-07-18 | `php scripts/dev/seed-playground.php --reset` then `php -S 127.0.0.1:8080 -t . scripts/dev/router.php` | OK — admin 302→login, login 200, MCP 401 unauthenticated / 177 tools authenticated | yes |
| 2026-07-19 | same two commands, verbatim from `docs/playground.md` | OK — admin 302, login 200, MCP 401 unauthenticated, `config/.encryption_key` 403, 177 tools authenticated. Identical to the slice-0 baseline; the NEW-03 warning appeared on page creation exactly as the document says it would | yes |
| 2026-07-19 (slice 2 session) | same two commands, verbatim | OK — admin 302, login 200, MCP 401 unauthenticated, `config/.encryption_key` 403, 177 tools authenticated. Unchanged; NEW-03 warning present as documented | yes |
| 2026-07-19 (slice 3 session) | same two commands, verbatim | OK — admin 302, login 200, `config/.encryption_key` 403, MCP 401 unauthenticated. The playground was additionally re-seeded mid-session after the isolation proof run deliberately deleted a seeded user (see the slice-3 evidence block) | yes |
| 2026-07-19 (slice 4 session) | same two commands, verbatim | OK — admin 302, login 200, `config/.encryption_key` 403, MCP 401 unauthenticated, **177 tools** authenticated. Counted with `installed.json`'s own parser, not a `grep -c '"name"'`, which reports 215 because nested schema properties are also named `name` — the looser count was discarded rather than recorded | yes |
| 2026-07-19 (slice 5 session) | same two commands, verbatim | OK — admin **302**, login **200**, `config/.encryption_key` **403**, MCP **401** unauthenticated, **177 tools** authenticated via the documented `.playground-access` recipe (`docs/playground.md:153-157`, run as written). Identical to the slice-0 baseline on every check; no drift in five sessions | yes |
| 2026-07-19 (slice 6 session) | documented commands, **but port 8080 was held by an unrelated Docker container** | **The document's own defect, caught here rather than by a user.** `php -S` could not bind, and because it had been backgrounded the failure was invisible — every check then reached the squatter. It reported admin `302` and MCP `302` where `401` is documented, plus a 200-tool count: three "findings" that looked like a slice-4 gate regression and were an unrelated Apache. The tell was `Server: Apache/2.4.54 (Debian)` in `curl -D -`; PHP's built-in server never sends it. Re-run on a **verified-free port (8123)**: admin **302**, MCP **401** unauthenticated, **177 tools** authenticated — identical to the slice-0 baseline, no drift in six sessions. `docs/playground.md` now carries a bind check as step 2 and the diagnostic note, so the next session cannot lose the same time. Recorded as **L-011** | yes |
| 2026-07-20 (slice 8 session) | documented commands, **port 8080 held by the same unrelated container for the third session running** | Caught in seconds by the step-2 bind check, exactly as L-011 intended; `curl -D -` confirmed `Server: Apache/2.4.54 (Debian)` before anything was believed. Ports 8081, 8082 and 8090 were also taken (the container maps a range). Re-run on verified-free **8321**: the server identified itself as `X-Powered-By: PHP/8.3.12` with no `Server:` header — ours, not the squatter's — admin **302** to login carrying the new `nosniff` header, login **200**, viewer **403** on `users.php`, anonymous API **401**. Test class port **8104** verified free before use | yes |
| 2026-07-20 (slice 9 session) | documented commands, **port 8080 held by the same unrelated container for the FOURTH consecutive session** — 8081, 8082 and 8090 with it | Caught by the step-2 bind check in seconds, as it has every session since L-011 was written. Re-run on verified-free **8321**, identified as ours by `X-Powered-By: PHP/8.3.12` with no `Server:` header: admin **302**, login **200**, `config/.encryption_key` **403**, MCP **401** unauthenticated — identical to the slice-0 baseline, no drift in nine sessions. Additionally `/scripts/dev/router.php` now answers **404** rather than its internal disclosure page (NEW-28, fixed this slice) | yes |
| 2026-07-22 (Sprint 2 slice 1 session) | documented commands; port **8080 not checked** this session — went straight to a verified-free **8085** (`nc -z` clean) | admin **302**, MCP **401** unauthenticated, **no `Server:` header** (PHP built-in, the L-011 tell) — identical to the baseline. The full integration suite (91 tests) had already booted the real App on the seeded playground before this manual check, so the environment was doubly validated | yes |
| 2026-07-22 (Sprint 2 slice 2 session) | documented commands on a verified-free **8106** (`nc -z` clean; 8080 not touched) | MCP **401** unauthenticated (freshness); then a live slice-2 walk on the same boot — viewer bearer `tools/call klytos_delete_page` → **403** JSON-RPC error, owner bearer allowed (**200**), viewer `tools/list` = **19 tools** (no destructive), owner = **169**. The 95-test integration tier had already booted the real App on the seeded playground first | yes |
| 2026-07-20 (slice 7 session) | documented commands, **port 8080 again held by the same unrelated container** | Caught immediately this time — `docs/playground.md`'s step-2 bind check (added by L-011) reported the port taken, and `curl -D -` confirmed `Server: Apache/2.4.54 (Debian)` before anything was believed. Re-run on verified-free ports (8104 for the walk, 8103 for the new test class): admin **302**, `klytos_session` cookie present, the S-09 defect reproduced live as **401** `authentication_required` (not the 302 the audit recorded — slice 4 changed that). The bind check paid for itself in seconds, which is the whole point of L-011 | yes |
| 2026-07-23 (Sprint 2 slice 3 session) | port 8080/8090 skipped (known-held, L-011); booted on a `nc -z`-verified-free **8091** | MCP **401** unauthenticated, **no `Server:` header** (PHP built-in). Then a live slice-3 walk on a verified-free **8092**: owner `tools/list` = **206** (172 core + 8 x402 + 16 forms + 10 importer — integrity and both shipped plugins now live), owner `tools/call klytos_forms_list` → **HTTP 200** (NEW-30: was "Unknown tool" pre-slice). The 100+-test integration tier had booted the real App on the seeded playground first | yes |
| 2026-07-24 (Sprint 2 slice 4 session) | documented commands; 8080/8081/8082/8090 skipped (known-held, L-011), booted on an `nc -z`-verified-free **8083** | MCP **401** unauthenticated (freshness). Then the whole of `docs/playground.md` §3a run for real on the same boot: four per-role bearer tokens minted with the documented one-liner, the full 5-tool × 4-role `tools/call` matrix (206/197/56/19 on `tools/list`), the translated 403 body, and the unmapped-tool protocol error. **Two commands in the newly-written §3a were wrong when first drafted and were corrected against the real output before the document was saved**: `grep -c '"name"'` counts 1 (the response is one line — and tool schemas carry `name` too), and the unmapped-tool call answers **200** with `-32602`, not a non-200 | yes |
| 2026-07-25 (Sprint 3 slice 1 session) | documented commands on a verified-free **8083** (`nc -z` first; **8080, 8081, 8082 and 8090 all held by the same unrelated container for the SIXTH consecutive session**) | admin **302**, MCP **401** unauthenticated, and `curl -D -` checked for the L-011 tell — no `Server: Apache/...` header, so the responses were our own `php -S` and not the squatter's. The seeder declined to overwrite the existing install, as documented, so the session ran against the standing playground | yes |
| 2026-07-26 (Sprint 6 slice 1 close session) | documented Start section run verbatim on a `nc -z`-verified-free **8112** (8080 skipped — Docker for the ninth consecutive session, established at the kickoff earlier the same day) | seed (`--reset`) clean; `php -S` backgrounded with its log grepped for `Failed to listen` → **bound cleanly**; owning PID confirmed by `lsof` (php83, 63899) rather than assumed (L-021); admin **302**, MCP **401** unauthenticated; `curl -D -` carried **no `Server:` header**, the L-011 tell, checked before any response was believed. Server stopped by port before the suite ran — the playground is single-tenant (L-025) | yes |
| 2026-07-26 (Sprint 6 slice 2 session) | documented Start section run verbatim on a `nc -z`-verified-free **8113** | seed (`--reset`) clean; backgrounded `php -S` with its log grepped for `Failed to listen` → **bound cleanly on 8113**; owning PID confirmed by `lsof` (php83, 73855); admin **302**, MCP **401** unauthenticated; `curl -D -` carried **no `Server:` header**. Stopped by port (`kill $(lsof -tiTCP:8113 -sTCP:LISTEN)`) and the port re-checked free **before** the suite ran (L-025) | yes |
| 2026-07-27 (Sprint 6 slice 3 session) | documented Start section run verbatim on a `nc -z`-verified-free **8115** (8113/8114/8116 also checked free) | seed (`--reset`) clean; backgrounded `php -S` with its log grepped for `failed to listen` → **bound cleanly on 8115**; owning PID confirmed by `lsof` (php83, 17804); admin **302**; MCP **401** unauthenticated; `curl -D -` carried **no `Server:` header**. **Two instrument corrections, both mine, both settled by re-running rather than by reasoning:** (1) a bare `GET /installer/mcp` answered **200** `{"name":"klytos","status":"ok"}` where the document says 401 — the document's check is a **POST** with a JSON-RPC body, so I had run the wrong command; re-run as written it answered **401**, and the GET health response is now noted in `docs/playground.md` so the next reader does not file it as a regression. (2) The suite then failed **1 test** on unmodified code (`LoginCeilingHttpTest`, "attempt 10 of 10 refused before the ceiling") — because that documented 401 probe **is an authentication failure** and had written one entry into `installer/data/rate_limits.json` (`ip:127.0.0.1`, read from the file, not inferred). Reseeded and re-ran: **276 tests / 1331 assertions green**, no test and no product file touched. Recorded as **L-028** and written into `docs/playground.md` at both places a reader meets it. Server stopped and port re-checked free before the suite (L-025) | yes |

## Phase 4 Step 4 — the redesign build, stage by stage

Keel v5.1.0 (A17–A19) requires a driven check on a browser surface to carry **the
path to its trace** in its row; a row without one is incomplete. Stages 1 and 2
predate that rule and recorded their evidence in `docs/decisions.md` (D-070,
D-075, D-077) — that is stated rather than backfilled, because a row invented
after the fact is not a record. **Stage 3 is the first to comply.**

Traces, videos and screenshots are recorded for EVERY test, passing or failing,
under the gitignored `tests/E2E/artifacts/test-results/<test-slug>-chromium/`.
Open one with `npx playwright show-trace <path>`.

| Stage | Red first | What was driven | Evidence | Trace |
|---|---|---|---|---|
| **3 of 6 — the component layer** (2026-07-29, D-078) | n/a — predates | `tests/E2E/components.spec.js` — **64 tests**, all passing on `KPORT=8143` (bind confirmed, owning PID `php83 64123` checked per L-021, no `Server:` header per L-011, reseeded `--reset` per L-028). The automated accessibility pass runs **per STATE, not per page**: axe-core at `wcag2a/2aa/21a/21aa/22aa`, scoped to each of the 12 component sections, in **both themes** = 24 scoped runs. Plus the geometry read back from the browser (button 34/28/38, badge 20, chip 24, field 34 + `--borde-control`, checkbox 13 and radio 14 each in a real 24 × 24 hit area, switch 38 × 22, stat tile 32, progress 8), the table's grid-and-roles in one run, the states, and the four declared breakpoints | Four real defects found and fixed, each re-verified: a **WCAG 1.4.10** page scroll of 346px at 320 CSS px caused by an absolutely positioned `.k-sr`; a cascade loss that painted a record name at 4.11:1; a whole class of link with no colour rule at all (1.48:1 in dark); and stacked error-summary links under the 2.5.8 target-size threshold. Two false failures separated from the real ones: axe's `aria-required-children` on a legal `<caption>` (refuted by reading Chromium's actual tree) and a contrast reading taken mid-`transition` (refuted by baking the theme in). **Three contrast pairs the delivery itself specifies measure below AA → DR-005**, excluded by selector and NOT reported as passing. PHP suite unchanged at **304 / 1526**; `keel-verify` 13 pass / 4 warnings; lint baseline **188/484 — down, never up** | `tests/E2E/artifacts/test-results/components-Responsive-beha-c593e-scroll-horizontally-1-4-10--chromium/trace.zip` (the 1.4.10 assertion) · `components-Table-—-accessi-2e38f-he-grid-—-every-one-of-them-chromium/trace.zip` (the explicit role set) · `components-axe-WCAG-2-2-AA-—-specimen-field-dark--chromium/trace.zip` (a per-state axe run). **Proven, not assumed:** removing `position: relative` from `.k-table-scroll` brought the 346px scroll back, and removing one `role="rowgroup"` turned the markup test red |
| **4 of 6 — the list screens, BATCH A** (2026-07-29, D-079) | n/a — predates | `tests/E2E/pages.spec.js` — **31 tests**, all passing, **0 skipped**, on `KPORT=8151` (bind confirmed, owning PID 23472 checked per L-021, no `Server:` header per L-011, reseeded `--reset` per L-028). Manifest entry 1 (Pages) driven per STATE in both themes: the complete explicit ARIA role set counted rather than sampled (7 columnheaders, every `tr` role=row, every `td` role=cell), one `th[role=rowheader][scope=row]` per row, row checkboxes `aria-labelledby` the row header, the visible aria-live caption, the seven-track `grid-template-columns` read out of `getComputedStyle`, the full-width empty row still spanning every column, axe at WCAG 2.2 AA over default / filter row / filtered-empty / selected-row / stacked-card in **both themes**, select-all's `indeterminate` + `aria-checked=mixed`, sort links with `aria-sort`, chips as links with `aria-current`, the disabled home-delete with its reason in its accessible name, the three responsive tiers (1024 sticky + scroll, <900 stacked cards with the table out of the tree, 320px asserted by TRYING TO SCROLL), bulk unpublish AND publish both driven end to end, a CSRF-less POST changing nothing, and a viewer offered no write control. | **Five real defects found, four in code earlier stages had closed** — the link layer losing to `klytos-base.css` since stage 3 (4.31:1, L-033), the bulk bar visible on load, the content area missing its 48px, `.k-table-scroll` never rendered (349px reflow failure at 320px), and an invented `--opacidad-desactivado`. **Each fix proven by planting the defect back and watching its own test go red**, then restoring byte-identically. PHP suite 304/1526 with the server stopped (the first run's 297/1480 error was L-025 — suite and playground sharing state). | `tests/E2E/artifacts/test-results/pages-*/trace.zip` |
| **4 of 6 — entry 41 (Logs), the prerequisite** (2026-08-09, D-084) | observed | `tests/Unit/LoggerReadFailureTest.php` — **6 tests** over `Logger::readLogFile()` and the new `Logger::isLogFileReadable()`: the unreadable file, the empty file, the two told apart by the new method, the ordinary offset/limit path, and the traversal refusal that had to survive the change. Where the running user can bypass mode 0000 (root), the unreadable fixtures **SKIP with a stated reason** rather than pass vacuously. PHP tier 304 → **310 tests / 1541 assertions**, 0 skips | **red observed:** `TypeError: count(): Argument #1 ($value) must be of type Countable\|array, false given` at `logger.php:264` — the defect itself, not a broken import. `template-console-stream.md` §2 specifies a separate state and a separate sentence for an unreadable log, and that state was **unreachable by construction**: the reader answered it with a fatal (L-034). The test was written and run before the fix existed; full record in **D-084** | n/a — PHP unit tier, no browser surface. The Keel v5.1.0 trace rule binds a driven check on a browser surface; this row is not one, and saying so is not the same as omitting a trace that was owed |
| **4 of 6 — entry 41 (Logs)** (2026-08-09, D-085) | observed | `tests/E2E/logs.spec.js` — **30 tests**, all passing, on `KPORT=8153` (bind confirmed, owning PID 82993 checked per L-021, no `Server:` header per L-011, reseeded `--reset` per L-028). Manifest entry 41 driven per STATE in **both themes**, with the theme now ASSERTED to have taken. Four fixture files give the states a real shape: populated (every level, one line with context, one line this Logger did not write), empty, unreadable (mode 0000, and the test SKIPS rather than lies where the running user is root), and 5,200 lines for truncation. Driven: the stream as a labelled focusable `role="group"`, the **absence** of `aria-live` asserted as a property, per-line `aria-pressed` toggling and the detail panel's `<h2>` + context, level chips as LINKS that change the URL and really filter, the file `<select>` with a **visible** label plus a submit exercised **with JavaScript disabled**, the Follow switch and the scroll-up pause announced in the shell's status region, Download streaming the real bytes with a traversal name refused 404 and a viewer refused 403, truncation stated with 5,000 of 5,200 lines, the copy affordance read back **out of the clipboard**, axe at WCAG 2.2 AA over five states × two themes, 320px asserted by trying to scroll (the page must not, **the stream must** — §3's inversion), and the detail panel measured at 340 / 300 / in-flow across the three breakpoints | **red observed:** `Call to undefined method Klytos\Core\Logger::parseLine()` — `tests/Unit/LoggerParseLineTest.php` was written and run before the method existed, and it failed on the absent behaviour rather than on a broken import (D-085). **Six real defects, five of them mine and found only by driving:** the Follow announcement was silently dropped because the script resolved `#k-live-status` at start-up and the shell emits it later; `k-line--info` / `k-line--debug` were emitted for tints the design withholds; the line reset clobbered `.k-line--*` by source order so **tinted lines rendered with no tint** (build rule 1's sixth mechanism, in the section whose header says it cannot happen there), and the first two fixes for it each broke a different state, caught by measuring; `.k-btn`'s `display` beat the `hidden` attribute so a hidden control still painted (**D-079's bulk-bar defect on a new component**); and the spec's own theme cookie was the wrong NAME, so every "light" run had measured dark — a false green in the harness, fixed by asserting `data-theme` rather than by correcting the name. **Two findings are the delivery's, not the build's → DR-007** (a 19px line where §7 admits no target below 24px; the selected line at 3.61:1 / 3.83:1), excluded by selector with their measured values pinned as floors. **Every fix proven by planting the defect back and watching its own test go red**, then restoring byte-identically. PHP suite **320 / 1577** with the server stopped; `keel-verify` 13 pass / 4 warnings; lint **182/480 — down again**; browser tier **141 passing** | `tests/E2E/artifacts/test-results/logs-*/trace.zip` — in particular `logs-Logs-—-the-controls-Follow-is-a-switch*/trace.zip` (the pause announcement), `logs-Logs-—-accessibility--*-at-320px*/trace.zip` (the 1.4.10 inversion) and `logs-Logs-—-the-controls-with-JavaScript-disabled*/trace.zip` (the no-JS fallback driven with JS actually off) |
| **5 of 6 — the form screens, BATCH A: entry 3 (Design)** (2026-08-09, D-088) | observed | `tests/E2E/design.spec.js` — **31 tests**, all passing, on `KPORT=8157` (bind confirmed, owning PID `php83 75391` checked per L-021, no `Server:` header per L-011, reseeded `--reset` per L-028). The first `record-form` screen, so the run carries the TEMPLATE's contract as well as the screen's: the three manifest cards each with its own `<h2>` and exactly one `<h1>`, a **visible** `<label for>` on every control with zero placeholders, the toolbar Save asserted to be a real `<button>` inside `.k-toolbar` owned by the form it submits, the picker/hex mirror in both directions with the picker carrying **no name**, the four measured pairs with ratio and verdict WORD, the below-AA save **refused with nothing written** (checked by a fresh GET, never `page.reload()`, which re-posts), the refused values kept on screen, the error summary as `role="alert"` with `tabindex="-1"` **taking focus** and each row linking to its field, the override offered only when there is something to override and **read back out of encrypted storage** to prove it was recorded, field-level `aria-invalid` + `aria-describedby` ordering (hint first), the empty-field-keeps-the-stored-value branch, the 880px content column and the 1200px grid switch both read out of `getComputedStyle`, 320px asserted by trying to scroll, and axe at WCAG 2.2 AA over default / refused / field-error in **both themes** with the theme asserted to have taken. PHP tier 320 → **335 tests / 1628 assertions**, 0 skips | **red observed:** `Call to undefined method Klytos\Core\Helpers::contrastRatio()`, then `Call to undefined method Klytos\Core\ThemeManager::contrastPairs()` — both unit files written and run BEFORE their methods existed, each failing on the absent behaviour rather than a broken import. **Two real defects found by driving, neither visible in source:** the toolbar seam could not carry a button at all (`klytos_kses_post()` has no `<button>` tag, so Save rendered as text — L-030's shape, a seam proved to exist and never proved to carry), and the pair specimen rendered the failing colours **as text**, so the screen whose job is to show a below-AA pair failed axe's contrast rule at 2.32:1 in reporting one. Both fixes **proven by planting the defect back and watching its own test go red**, then restoring byte-identically (`diff -q` clean). One finding is the delivery's: **DR-005 gap 3 reached the population it predicted** — `--color-peligro` on `--fondo-elevado`, 4.32:1 dark, on the field-level error message — excluded by selector with its ratio pinned as a floor | `tests/E2E/artifacts/test-results/design-*/trace.zip` — in particular `design-the-primary-Save-is-in-the-toolbar*/trace.zip` (the seam), `design-an-explicit-override-saves-the-pair-AND-records-it*/trace.zip` (the record read back out of storage) and `design-axe-finds-nothing-on-the-refused-state*/trace.zip` |
| **5 of 6 — the form screens, BATCH B: entry 19 (Content model)** (2026-08-10, D-089) | n/a — out of scope | `tests/E2E/content-model.spec.js` — **16 tests**, all passing, on `KPORT=8161` (bind confirmed, owning PID `php83 14323` checked per L-021, no `Server:` header per L-011, reseeded `--reset` per L-028), plus **2 new tests in `shell.spec.js`**; whole tier re-run at **190 passing** (was 172). Driven per STATE in **both themes**, with the theme ASSERTED to have taken: the two backed cards each with its own `<h2>` and exactly one `<h1>`, **the absence of the deferred Statuses card asserted** so it cannot be built by accident from the manifest alone, the row's link to its own screen carrying BOTH halves of a taxonomy's identity, the built-in type's disabled delete with its reason as real described text (not a tooltip), creation checked by a **fresh GET** and never `page.reload()`, the server-side refusal reached by stripping `required` (a client-only refusal is not a refusal), `aria-describedby` asserted to be exactly hint-then-error, the empty collection keeping its card heading with its add action still reachable, the destructive two-step driven **with JavaScript actually disabled** as well as enabled, 320px asserted by trying to scroll, and axe at WCAG 2.2 AA over default and error states in both themes — **scanning the WHOLE PAGE, not `#main`**. PHP tier **335 / 1628**, 0 skips, unchanged | **`n/a — out of scope`, and it is the honest value rather than `observed` borrowed from a neighbour:** this slice added no pure function of its inputs. It is markup, a manager it only calls, and an aggregation of what that manager already returns — the not-applied table's own list in `references/test-automation.md`. **One real defect found by driving, and it is not on this screen — it is on EVERY screen:** the sidebar's current nav item measures **4.31:1 dark / 3.70:1 light**, reproduced on `theme.php`, `logs.php` and `pages.php`, so it has been true since stage 2. It survived four screens because `design.spec.js`, `logs.spec.js` and `pages.spec.js` all scope axe to `#main` — and the shell is the one component `#main` never contains (**DR-005 addendum 2**; L-031 arriving on the tooling). Registered, not fixed; both ratios **pinned as floors in `shell.spec.js` and proven to FAIL on a planted colour**, then restored byte-identically. **One test defect of my own:** `AxeBuilder.exclude()` reads an array as a FRAME PATH, so `exclude( KNOWN_DELIVERY_GAPS )` excluded nothing — the pass was then **proven to fail on a planted contrast defect**, after a first plant that touched a rule with no rendered text and proved nothing. **One invented token caught before it shipped:** `--type-caption-mono` does not exist (D-079's fifth defect again). `keel-verify` **20 checks: 15 pass, 5 warnings**; lint **182/468 — errors unchanged, warnings down 6**; `docs/api/INDEX.md` **988 → 993** | `tests/E2E/artifacts/test-results/content-model-*/trace.zip` — in particular `content-model-the-two-step-confirm-works-with-JavaScript-DISABLED-chromium/trace.zip` (the confirm driven with JS off) and `content-model-axe-WCAG-2-2-AA-on-the-default-state-—-light-chromium/trace.zip` (the whole-page scan that found the shell defect) · `shell-DR-005-addendum-2-*/trace.zip` (the pinned floors) |
| **5 of 6 — the form screens, BATCH B: entry 39 (Post type)** (2026-08-10, D-090) | n/a — out of scope | `tests/E2E/post-type.spec.js` — **28 tests**, all passing, on `KPORT=8171` (bind confirmed, owning PID `php83 21205` checked per L-021, no `Server:` header per L-011, reseeded `--reset` per L-028); whole tier re-run at **218 passing** (was 190). The first screen in the build to render the template's SECTION NAV, so the run carries that component's contract as well as the screen's: the nav asserted to be a labelled `<nav>` whose every `href` resolves to a card that exists, exactly one `aria-current="page"`, the 180px sticky column read out of `getComputedStyle` at 1440 and the chip row at 1024 read the same way. Plus: the five backed cards each with its own `<h2>` and exactly one `<h1>` carrying the post type's NAME, **the absence of the deferred Exposure card asserted**, the ID `readonly` and NOT `disabled` and carrying neither `name` nor `form` (so it can never post), the toolbar Save asserted to be associated by `form=` and **Enter in a text field** driven as the implicit submit, every save checked by a **fresh GET** and never `page.reload()`, the server-side refusal reached by stripping `required`, `aria-describedby` asserted to be exactly hint-then-error, the manifest's own empty sentence asserted verbatim with the card heading kept, both collections' two-step confirms driven **with JavaScript actually disabled**, a status EDITED IN PLACE and read back by fresh GET, the four system statuses locked with their reason as described text, a reserved status id refused as a sentence, the three static option rows filled **with JavaScript off** to prove a choice field can be built without it, each locale field `lang`-tagged and labelled with the locale NAME, 320px asserted by trying to scroll with both collections populated, and axe at WCAG 2.2 AA over default / populated / error in **both themes** — **scanning the WHOLE PAGE, not `#main`** (L-037). PHP tier **335 / 1628**, 0 skips, unchanged | **`n/a — out of scope`, the honest value rather than `observed` borrowed from a neighbour:** this slice added no pure function of its inputs — it is markup over `PostTypeManager` methods that already existed and already have their tests. **ONE REAL DEFECT, and it was in a layer that had never been rendered:** `.k-section-nav-item` painted `--texto-secundario` on `--fondo-ventana` — **4.46:1 in light**, under AA by 0.04, recomputed independently in Python from the standard and agreeing with axe's 4.45. Entries 3 and 19 both render `--no-nav`, so the whole `.k-section-nav` block had **never painted anything** since D-088 wrote it: L-030's shape for the fifth time in this build, and the first one this build introduced itself rather than inherited. **Fixed in the build, not registered as a Design Request** — no delivered file states this control's colour, so the token was the build's own choice (the same call D-078 made for three pairs `klytos-admin.css` already ruled on). Now `--texto-primario`: **14.79:1 light / 15.29:1 dark**, both **pinned as floors** and the pair **proven to FAIL on the planted original** — three light axe runs and both floor tests went red together, then the file was restored byte-identically (`diff` clean). The `readonly`-not-`disabled` assertion was proven the same way. `keel-verify` **20 checks: 15 pass, 5 warnings**; lint on the rewritten screen **0 errors / 0 warnings**; `docs/api/INDEX.md` **993 → 1000** (5 actions + 2 filters) | `tests/E2E/artifacts/test-results/post-type-*/trace.zip` — in particular `post-type-the-section-nav-actually-paints*/trace.zip` (the layer's first consumer), `post-type-the-resting-section-nav-item-stays-above-AA-—-light-chromium/trace.zip` (the pinned floor) and `post-type-the-two-step-status-confirm-works-with-JavaScript-DISABLED-chromium/trace.zip` |
| **5 of 6 — the form screens, BATCH B: entry 6 (Security)** (2026-08-10, D-091) | n/a — out of scope | `tests/E2E/security.spec.js` — **35 tests**, all passing, on `KPORT=8175` (bind confirmed, owning PID `php83 33912` checked per L-021, no `Server:` header per L-011, reseeded `--reset` per L-028); whole tier re-run at **253 passing** (was 218). The first screen in the build whose controls are SWITCHES and the first consumer of `.k-card--secret`, so the run carries both contracts as well as the screen's: each second factor asserted to be `role="switch"` with an `aria-checked` that states the truth and an accessible name taken from its VISIBLE label, **the absence of a toolbar Save asserted** (adaptation 27), the destructive card asserted to be the LAST card and to exist only once 2FA is on, the section nav's every `href` resolving to a card that exists with exactly one `aria-current`, and the recovery-codes card asserted to be the ONE bordered card in the admin — read out of `getComputedStyle`, not out of the stylesheet. Plus: **the absence of both deferred cards asserted** (CSP, Integrity score) so neither can be built from the manifest alone; the re-auth step driven in both directions with a WRONG password first and the factor checked by a **fresh GET** to prove nothing was applied; the cancel path; the re-auth step driven **with JavaScript actually disabled**; the TOTP switch driven into the ENROLMENT ceremony with the **absence** of a password step asserted; a seeded passkey listed and removed; the codes shown ONCE with the warning proven to precede them by DOM position and proven absent on the next GET; the count sentence asserted number-neutral (D-076); the site-wide cards driven as `editor` and asserted absent from both the stack and the nav; a wrong password refusing the encryption change with the level read back from a fresh GET; `aria-describedby` asserted to be exactly hint-then-error; the summary taking focus; 320px asserted by trying to scroll; and axe at WCAG 2.2 AA over default / everything-on-with-codes / re-auth-and-error / enrolment in **both themes** — **scanning the WHOLE PAGE, not `#main`** (L-037). PHP tier **335 / 1629**, 0 skips | **`n/a — out of scope`, the honest value rather than `observed` borrowed from a neighbour:** this slice added no pure function of its inputs — it is markup over `TwoFactor` and `UserManager` methods that already exist and already have their tests. **ONE REAL PRODUCT DEFECT, found by driving with JavaScript off:** `.k-field { display: flex }` beat the user agent's `[hidden] { display: none }` on ORIGIN, so the passkey enrolment form — revealed only where `navigator.credentials` exists — was PAINTED and operable in a browser with no WebAuthn at all. That is the FIFTH occurrence of one mechanism, and the stylesheet already carried a comment stating the rule, so the fix is a rule plus **`keel-verify` check 21**, which was **proven to FAIL on the planted defect** and then restored byte-identically. Its first run found a false positive of its own (`\bhidden\b` matches inside `aria-hidden`), fixed before it was trusted. **ONE FINDING IS THE DELIVERY'S:** DR-005 gap 3 reached the other half of the population it named — `.k-btn--destructive` at **4.32:1** dark, the identical pair to the error message. It is not new: the class has existed since stage 3 and entries 19 and 39 both render it, but only in the ARMED state of a two-step confirm, which no axe run had ever reached. Registered, not fixed; excluded by selector with **both themes pinned as floors and each proven to FAIL on a planted colour**. The `.k-card--secret` pair is pinned the same way at its MEASURED value rather than at 4.5, after a floor set at 4.5 was found to pass with the override removed — a test that could not fail for the reason it existed. **Three test defects of my own**, separated from the product one: an untrimmed text comparison (D-088's again), and two orderings — a fixture that enabled a second factor BEFORE logging in, so the login demanded it and hung, and a second `login()` on a page that already held a session. `keel-verify` **21 checks: 16 pass, 5 warnings**; lint on both new files **0 errors / 0 warnings**; `docs/api/INDEX.md` **1000 → 1005** (3 actions + 2 filters) | `tests/E2E/artifacts/test-results/security-*/trace.zip` — in particular `security-with-no-WebAuthn-*/trace.zip` (the `[hidden]` defect), `security-DR-005-gap-3-*/trace.zip` (the pinned floors) and `security-the-re-auth-step-works-with-JavaScript-DISABLED-*/trace.zip` |


## Cross-cutting verification (Phase 5 §4 — before Phase 6)

Filled once, at the end of development — not per sprint.

| Check | Command | Result | Date |
|---|---|---|---|
| Full acceptance-criteria pass | | | |
| All flows incl. failure paths, in the playground | | | |
| Full security profile checklist (web-app + MCP) | | | |
| Accessibility end to end (automated + keyboard + real AT) | | | |
| i18n — no hardcoded/concatenated user-facing strings; catalogues current | | | |
| Performance budgets measured (`03-technical-plan.md`) | | | |
| Upgrade path from the real previous version | | | |
| `scripts/keel-verify` (full output pasted) | | | |
