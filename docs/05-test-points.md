# Test Points — Klytos CMS

> One row per slice, filled at the moment its test point passes — never retrospectively.
> An empty cell is a missing check, not a formatting gap. The evidence cell carries the **exact
> commands run, a one-line result summary, and the commit hash**; a result without them is empty.
> An acceptance criterion without an automated test carries its one-line justification in the row.
> "n/a" is a valid value where a column genuinely does not apply — but it is written, never left blank.

## Sprint 1 — authorization axis

| Slice | Acceptance criteria checked | Real run in playground | Security checks | Accessibility (automated + AT pass) | i18n (strings externalized) | Reuse checked (no new duplicate) | Docs updated (api/reference, example runs) | Extension points exposed | Evidence (commands + output summary + commit) | Result | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 0 — Playground + gate zero | Playground boots from documented commands; 4 roles seeded; MCP reachable; `.htaccess` denies replicated under `php -S` | **yes** — seeded, booted, admin 302→login, login 200, 4 users + 3 pages created via the product's own managers | Deny checks: `config/.encryption_key`, `config.json.enc`, `admin-identity.priv.enc`, `.playground-access`, `data/**`, `core/*.php`, `backups/`, `/.git/config`, `plugins/**/*.php` → **all 403**. MCP unauthenticated → **401**. `git check-ignore` confirms every generated secret is ignored; `git status -uall` over config/data/public → **empty** | n/a — no UI written in this slice | n/a — no user-facing strings added | **yes** — seeder drives `UserManager::create()`, `PageManager::create()`, `Auth::createAppPassword()`, `Encryption::generateRsaKeyPair()` rather than hand-writing storage; no new duplicate helper | `docs/playground.md` created; no new public PHP surface introduced (both scripts are dev entry points, not API) | n/a — dev tooling, no extension points | See the evidence block below | **PASS with one gate-zero item open** | Found 2 production bugs: NEW-03 (by-ref hooks) and NEW-04 (build writes into the repo root). Neither is fixed here; both recorded with triggers |
| 1 — Test harness + dev manifest | `composer install` clean; both tiers run; unit tier passes on a bare checkout; integration tier boots the App and resolves each seeded role from `$_SESSION`; `phpunit` green; `phpcs` green | **yes** — the integration tier IS a playground run: it boots the real App against the seed and asserts all 4 roles; playground re-seeded and re-booted from the documented commands this session (freshness row below) | Harness only — no product security boundary changed. Two security-relevant properties of the harness itself were verified: the integration tier **skips loudly** instead of passing when the playground is absent (proven by moving the two install files aside), and `actingAs()` sessions are accepted by the product's own `Auth::isAuthenticated()` while a guest session is rejected — so later refusal tests cannot pass against an anonymous session | n/a — no UI written in this slice | n/a — no user-facing strings added | **yes** — class loading uses Composer's `classmap` over `installer/core/` rather than re-implementing `App::registerAutoloader()`'s CamelCase→kebab-case mapping (private, bound to a booted instance), so no second copy of that rule exists. Temp-dir teardown is NOT shared with `seed-playground.php`'s purge: that one deliberately preserves tracked `.htaccess` guards, this one deletes everything — different contracts, not a duplicate | No new product API surface (test harness + dev manifest). Teaching surfaces updated in the same slice per L-004: `docs/03-technical-plan.md` §1 and §4, `docs/playground.md` ("Running the tests"), the `klytos-core-development` skill (new Testing section), and the `docs-discipline` rule in all 7 mirrored containers | n/a — dev tooling, no extension points | See the evidence block below | **PASS** | Composer aliases added (`composer test` / `test:unit` / `test:integration` / `lint`). `composer.lock` untracked→tracked (D-027). `phpcs.xml` now covers `tests/`; `PSR1.Files.SideEffects` excluded for `tests/bootstrap.php` only, with justification. `failOnWarning` deliberately left off until NEW-03 is fixed (D-026) — reason recorded in `phpunit.xml` |
| 2 — `vendor-ai/` manifest + CVE audit | Manifest resolves to **exactly** the 16 vendored versions (0 deltas); `composer audit` runs and its full output is pasted below; CVE findings reported and triaged, **not** patched (D-022) | **yes** — session-start freshness boot from `docs/playground.md` verbatim: admin 302, login 200, MCP 401 unauthenticated, `config/.encryption_key` 403, 177 tools authenticated. The slice itself changes no runtime code, so there is no new flow to walk | **This slice IS the security check.** First `composer audit` the project has ever been able to run: **5 advisories / 2 packages** (guzzle 7.10.0, psr7 2.9.0), all medium — recorded as NEW-05. Reachability assessed rather than assumed: no `CookieJar` anywhere, no user-controllable URLs (5 hardcoded provider endpoints, `chat-engine.php:242-247`), `vendor-ai/` loaded lazily from one call site. Also found NEW-06 (PHP 8.3 floor vs declared 8.1+) and NEW-07 (2 BSD packages with no licence text). **Verified `vendor-ai/` was not mutated** by the resolution: `git status --porcelain installer/vendor-ai` empty | n/a — no UI written in this slice | n/a — no user-facing strings added | **yes** — no new PHP surface. The drift guard extends `PHPUnit\Framework\TestCase` directly rather than `UnitTestCase`, whose per-test encryption key and throwaway storage would be set-up cost with no consumer; version normalisation is one private helper used by all four readers, not repeated per call site | `docs/03-technical-plan.md` §1 (both the dependency rows and the risk block), `docs/04-adoption-audit.md` (H-04 closed + NEW-05/06/07), `installer/vendor-ai/LICENSE-THIRD-PARTY.md` (all 16 packages, attribution corrected, BSD text added), `docs/playground.md` (new "Auditing the vendored dependencies" section — commands run as written). No new public API surface, so no `docs/api/` row | n/a — dev tooling and packaging metadata, no extension points | See the evidence block below | **PASS — with 5 CVEs open for user triage** | The sprint's "widest unknown" is now bounded: the upgrade is constraint-compatible (`soukicz/llm` allows guzzle `^7.9`, fixes are 7.12.1 / 2.12.1), so it is a re-vendor, not a dependency rewrite. Still a scope change → Estimate v2, per D-022 |
| 3 — One matrix + fail-closed current user | Viewer denied an owner-only permission; unknown permission denied for every non-owner role; owner shortcut intact (unknown keys included); **a session with no `klytos_user_id` is DENIED, not promoted**; v1.x migration idempotent; **upgrade tested from the REAL previous version (v0.30.1)** | **yes** — session-start freshness boot from `docs/playground.md` verbatim (admin 302, login 200, `config/.encryption_key` 403, MCP 401). Beyond the playground, a **real v0.30.1 install** was built by that release's own installer in a temp dir and upgraded to the working tree: 12/12 assertions pass, including the NEW-01 denial on a genuinely upgraded install | **This slice IS the security fix.** NEW-01 closed: the hardcoded `['role' => 'owner']` fallback is gone; both failure shapes (no `klytos_user_id`; id that does not resolve) deny and log. S-04 closed: one matrix, in `UserManager`. Independent `security-auditor` pass over the diff: **no blocking findings** — it traced every caller of `klytos_current_user()`, `Auth::getUserId()` and direct `$_SESSION['klytos_user_id']` reads and found no residual path to privilege, confirmed display-only callers fail closed on null, and confirmed the new log calls carry no secret, hash or personal data. It also confirmed the temp-dir scripts cannot delete outside their `mktemp` directory and embed only throwaway credentials | n/a — no UI written in this slice | n/a — no user-facing strings added. The two new log messages are operator-facing diagnostics, not UI copy | **yes** — the matrix was not re-implemented anywhere: `klytos_has_permission()` now delegates to the existing `UserManager::hasPermission()`. No new helper was created; the fix is a deletion plus a delegation. Test-side, `PlaygroundState` is a new trait rather than a copy of `UnitTestCase`'s temp-dir teardown, whose contract is different (that one preserves nothing; this one must preserve tracked `.htaccess` guards) | `docs/04-adoption-audit.md` (S-04 and NEW-01 marked CLOSED with the evidence; NEW-02 sharpened; NEW-08 added), `docs/playground.md` (new "Testing an upgrade from the real previous release" section, plus the isolation contract under "Running the tests"), `docs/decisions.md` (D-030, D-031), `docs/lessons-learned.md` (L-006). No new public PHP surface introduced — the change removes a surface and redirects a caller — so no `docs/api/` row | n/a for this slice — the `auth.capabilities` filter already existed and still applies, now at the single remaining call site (`user-manager.php:628`) rather than at two | See the evidence block below | **PASS** | S-04 resolved the **opposite way** to the audit's remediation note, deliberately and recorded: the "dead" copy was kept and the live one deleted, because `UserManager` is the lower layer and slice 4 / Sprint 2 both hold a user object rather than a session. One defect caught before commit and recorded as **L-006**: the first version of the crash fix logged through `$this->logger`, which is null at that point in boot and would have raised a `TypeError` — a crash introduced by a crash fix, on a path no test can reach. **The `code-reviewer` pass returned a BLOCKING finding and it was fixed, not argued with:** the Step 10b `try`/`catch` was never executed by any test, because every install the tests boot already has an owner, so `migrateFromV1Config()` was never reached *through* `App::boot()`. The upgrade script gained two phases (`break-migration`, `boot-must-survive`) that put an install into the exact state that used to fatal and boot it in a fresh process. Two further reviewer findings applied: `UserManager::hasPermission()` now denies a record with no usable role instead of defaulting it to `viewer` (with its own test), and a comment citing the wrong boot step was corrected |
| 4 — `klytos_require_permission()` + central gate | Per-role integration tests against representative pages **and** endpoints asserting the 403/401 **SHAPE**, not only the status; all 66 admin files accounted for in the gate map; `keel-verify`'s gate check demonstrably FAILS on a removed gate | **yes** — session-start freshness boot from `docs/playground.md` verbatim (admin 302, login 200, `config/.encryption_key` 403, MCP 401 unauthenticated, 177 tools authenticated). Beyond that, a **full 65-surface × 4-role walk over real HTTP** (260 requests) confirming both halves: privileged surfaces refuse, and nothing a role legitimately needs became unreachable (sprint risk 1) | **This slice IS the security fix.** S-07 closed: coverage 15/66 → 65/66 mapped surfaces, default-deny for the 66th and for anything added later. Defects closed in-path (D-033): NEW-10 setup-wizard escalation, and NEW-12 — `api/download-identity.php`'s **three stacked defects**, each masking the next, all **verified live** rather than believed. **NEW-09's fix was implemented and then REVERTED (D-036)**: the `security-auditor` pass showed the auth-guard exemption opens a full account-takeover primitive (a correct password alone would enrol an attacker's authenticator), and it buys nothing because passkey login cannot complete regardless. The hand-rolled `in_array( $role, ['owner','admin'] )` in `security.php` is gone, so `UserManager::hasPermission()` is still the single decision point (S-04 preserved). Independent `security-auditor` and `code-reviewer` passes over the diff | n/a — no UI written in this slice. The 403 page is a self-contained document; it sets `lang`, `charset`, a viewport and `noindex`, and escapes every interpolated value | **yes** — 4 new keys (`common.forbidden`, `common.no_permission`, `common.authentication_required`, `plugins.page_declares_no_capability`) added to **all 20 catalogues** (D-006). The first two were already REFERENCED by `plugin-page.php` and existed in **no** catalogue, so that gate had been rendering `__()`'s generated fallback — the audit called it "i18n'd"; it was not | **yes** — the denial shape was **promoted** from `core/router.php:438-447`, not reinvented; `klytos_require_permission()` delegates to the existing `klytos_has_permission()` → `UserManager::hasPermission()` chain rather than re-deciding; the 4 legacy redirect gates were deleted rather than left beside the central one | `docs/reference/authorization.md` **created** (matrix, gate map semantics, all 6 functions with runnable examples, both extension points, the "adding a new admin page" checklist, and an explicit "what this does NOT cover"); 8 new rows in `docs/api/INDEX.md` with counts updated 930 → 938; `docs/04-adoption-audit.md` (S-07 CLOSED + NEW-09/10/11); `docs/playground.md`; `docs/decisions.md` (D-032…D-035); `docs/lessons-learned.md` (L-007, L-008) | **yes** — `admin.gate_map` filter (a plugin gates its own admin files) and `auth.access_denied` action (the audit hook, deliberately **unable** to reverse the decision). The pre-existing `auth.capabilities` filter still governs the matrix | See the evidence block below | **PASS** | Both review subagents returned **blocking** findings; both were fixed rather than argued with, and the slice's test set grew from 6 to 13 HTTP tests as a direct result. 5 capabilities added to the one matrix. `ai.use` deliberately excludes `editor` while NEW-02 is open (D-035). `plugin-page.php` now DENIES a plugin that declares no capability — a **breaking change for third-party plugins** (D-034), verified to break no shipped plugin. **NEW-11 found and NOT fixed:** `Auth::login()` never consults `UserManager`, so only `config['admin_user']` can log in — which is very likely *why* S-07 survived unnoticed |
| 5 — Named escalations, one test each | One NAMED test per finding asserting the refusal (S-01, S-02, S-03, S-05, S-06, S-12), each with its POSITIVE counterpart (a role that SHOULD reach the surface gets through, per L-008); S-12's remaining half closed — state-changing GET and missing CSRF; full suite green | **yes** — session-start freshness boot from `docs/playground.md` verbatim: admin **302**, login **200**, `config/.encryption_key` **403**, MCP **401** unauthenticated, **177 tools** authenticated (counted via the documented `.playground-access` recipe, matching the slice-0 baseline exactly). The escalation tests are themselves real HTTP against a real `php -S` server on the seeded playground | **This slice IS the security proof.** Each of the six findings now fails a named test if it regresses. Two live defects closed: **S-06 residue** — `api/tasks.php` did not re-gate `update`/`complete` at `tasks.manage` while its page twin does (`tasks.php:38`), so an editor was refused via the UI and allowed via the API; and **S-12** — POST + CSRF now required on the RSA private-key export, with the caller retargeted because the old 302 redirect *was* what made it a GET. Three of the new tests were **proven to FAIL against the unfixed code** (200 where 405/403 required; 500 proving the editor's task action executed) before the fixes landed. Independent `code-reviewer` and `security-auditor` passes over the diff | n/a — no new UI. The one markup change is the identity-export form's `action` attribute; the button, label and surrounding structure are untouched | **yes** — no new user-facing strings. The 405/403 bodies on `download-identity.php` are operator-facing plain text on a machine endpoint, consistent with the file's existing 429/404 responses, not UI copy | **yes** — and this drove a real refactor: the HTTP harness was **extracted** from `AdminGateHttpTest` into `tests/AdminHttpTestCase.php` rather than copied for the second HTTP class. Duplicating it would have forked the three defects L-008 records (session cookie name, `proc_open` handle shape, teardown orphan check) so a later fix to one copy would silently miss the other. `klytos_require_permission()` was reused in `api/tasks.php`; **no second authorization decision point was added** (S-04 preserved) | `docs/reference/authorization.md` (API-twin re-gating rule; CSRF and step-up-authentication added to "what this does NOT cover"), `docs/04-adoption-audit.md` (**NEW-13** added; NEW-12's open half resolved), `docs/decisions.md` (D-038, D-039, D-040), `docs/lessons-learned.md` (**L-010**). No new public PHP surface — the changes are two guards, one re-gate and test infrastructure — so no `docs/api/INDEX.md` row | n/a for this slice — no new extension point. The existing `admin.gate_map`, `auth.access_denied` and `auth.capabilities` all still apply unchanged | See the evidence block below | **PASS** | **A harness defect was found and repaired mid-slice, and it is the most consequential thing here: `PlaygroundState::assertConfigNotMutated()` was INERT** — it ran after `restorePlayground()` and re-hashed the already-restored file, so it compared the snapshot against itself and had been checking nothing since slice 3 (D-039, **L-010**). Proven inert with a probe, repaired to compare decrypted content minus `scheduler_last_run`, and pinned by a permanent two-way regression test. It then paid for itself immediately: it fired on the S-12 tests against the unfixed code, independently confirming the GET really did write config, and stopped once the fix landed. Lint baseline **improved** to 199/488 (errors −2). NEW-13 recorded and deliberately NOT fixed (D-040) |
| 6 — `SafeHttp` + risky call sites | Refusals for `127.0.0.1`, `[::1]`, `169.254.169.254`, a non-HTTP scheme, **and a public URL that 302-redirects to a private one**; full suite green | **yes** — session-start freshness boot (see the freshness row: the documented port was held by an unrelated container, which is itself the session's first finding, **L-011**). The redirect and endpoint tests are real HTTP against real `php -S` servers; the oEmbed tests drive the endpoint as a seeded owner exactly as an editor would | **This slice IS the security fix.** S-08 closed by `SafeHttp`, applied at 5 call sites. The finding was **wider than recorded**: the *discovered* oEmbed endpoint is attacker-controlled too and its response **is** echoed back, and every fetch followed redirects unvalidated. Proven against the unfixed code: **6 of the 8** endpoint tests failed (404 where 400 required — the 404 the endpoint returns *after* fetching), and the old transport was demonstrated following a 302 to `http://169.254.169.254/latest/meta-data/` with `CURLINFO_EFFECTIVE_URL`. Fixed in-path: `HttpClient::requestWithStream()` silently dropped `follow_redirects`. Both review subagents run over the diff | n/a — no UI written in this slice | n/a — no new user-facing strings. The refusal deliberately reuses the endpoint's existing generic `Invalid URL`, so no catalogue key was needed and no internal-network oracle was created | **yes, and it drove the shape** — the validation was **promoted** from `ImportValidator::validateUrl()`, not rewritten, and `ImportValidator` now delegates, so ONE implementation exists where there were about to be two. `SafeHttp` reuses `HttpClient` for transport rather than opening a third cURL call site. `AdminHttpTestCase` was **generalized** with a `routerScript()` hook rather than copied for the fixture server, keeping L-008's three defects in one place | `docs/reference/safe-http.md` **created** (the rule, return shape, all 5 reason codes, redirects, the oracle rule, all 4 extension points, known limits, where it is applied, tests); **6 new rows** in `docs/api/INDEX.md` with counts updated 938 → 944; `docs/04-adoption-audit.md` (S-08 CLOSED + **NEW-15**); `docs/playground.md` (bind check); `docs/decisions.md` (D-041, D-042); `docs/lessons-learned.md` (**L-011**, **L-012**) | **yes** — `http.safe.allowed_schemes` and `http.safe.max_redirects` (filters, both tested), `http.safe.redirect` and `http.safe.blocked` (actions, both tested). `http.safe.blocked` is deliberately an action, not a filter, so it cannot reverse a refusal | See the evidence block below | **PASS** | **A second harness defect found, in the L-010 shape: the integration tier never reset hooks** while the unit tier always had, so a filter registered by one test leaked into every later test in the process (D-042, **L-012**). Nothing was passing for the wrong reason *yet*; the next weakening filter would have been. Caught by asserting on the refusal REASON rather than just the refusal. Lint baselines **improved**: core+admin 199 → **193**, plugins 131 → **129**. **NEW-15 recorded and deliberately NOT fixed:** DNS rebinding survives, because the address is resolved to validate and resolved again to connect — stated plainly in the reference doc rather than implied away |
| 7 — Public comments, off the admin path | Anonymous submission succeeds; honeypot rejects a bot; rate limit holds **ACROSS sessions**; **no admin-directory name in any frontend-reachable URL** | **yes** — session-start freshness boot (port 8080 held by an unrelated container again, so a verified-free port was used, per L-011); the four criteria were then walked for real with `curl` against the playground, and the accented submission round-tripped intact | **This slice IS the security fix.** S-09 closed by RELOCATION, not by the recorded remediation: the handler left the admin tree entirely rather than being exempted from its auth guard. The D-036 question was asked *before* acting and changed the design — `admin/bootstrap.php` runs cron and the action scheduler on every request (`bootstrap.php:184-196`), so the recorded fix would have handed every anonymous caller a scheduler trigger. Input bounds added to `CommentManager::submit()` because it is now anonymously reachable. Both review subagents run; the `security-auditor` returned a **blocking** finding that restructured the slice (rate limit ran AFTER `App::boot()`, and the honeypot ran BEFORE the rate limit, so a `_honeypot` flood was never counted) | n/a — no UI written in this slice | **yes** — a new `comments` domain with **11 keys** added to **all 20 catalogues**; three hardcoded English validation messages in `submit()` converted to `__()` after the code review flagged that they reach anonymous callers verbatim. The `405`/`500` paths keep literals by necessity — they fire before I18n exists (**NEW-18**) | **yes** — the persistent IP-keyed `MCP\RateLimiter` was **reused**, not forked, even though its fixed 60s window meant expressing the policy as a count rather than the old "1 per 30s" interval; `AdminHttpTestCase::post()` was **generalized** (nullable `$role`) rather than copied for anonymous POSTs; `SiteConfig::setValue()` was written as the counterpart to the existing `getValue()`. The install-root discovery loop IS duplicated with `x402-gate.php` and is recorded as deliberately not extracted, with a trigger | `docs/reference/public-comments.md` **created**; **5 rows** changed/added in `docs/api/INDEX.md` with counts 944 → 948; `docs/04-adoption-audit.md` (S-09 CLOSED + **NEW-16…NEW-20**); `docs/playground.md` (new try-it section); `docs/decisions.md` (**D-043**, amended after its review cycle); `docs/lessons-learned.md` (**L-014**); stale `api/comment-submit.php` row removed from `README.md` | **yes** — `comment.rate_limit` and `comment.notification_recipient` (filters), `comment.honeypot_rejected` and `comment.rate_limited` (actions). Both are actions, not filters, so a listener cannot turn a refusal into an acceptance | See the evidence block below | **PASS** | **The named finding was the shallowest of three.** Underneath it: `SiteConfig::setValue()` **did not exist** although the MCP tool calls it four times, so comments could never be switched on at all (**NEW-16**, fixed in path); and **no comment form exists in the generated output**, which is deliberately still true at the end of this slice (D-023 owns it) and is said plainly in the reference doc. Lint baselines held exactly at 193/488 and 113/109; `installer/public/` was found to be **outside the phpcs scan set entirely** and is now scanned, at 0/0. Recorded and NOT fixed: **NEW-17** (proxy collapses the rate limit into one bucket), **NEW-18** (no `__()` outside the admin bootstrap), **NEW-19**, **NEW-20** (limiter race — carried as *plausible and unproven*, because the concurrency test that would settle it was not run) |
| 8 — HSTS + CSP fail-open + hardening | Headers asserted on a REAL playground response; admin renders with the tightened CSP, browser console clean | **yes** — headers read off real `php -S` responses including the 401 and 403 refusals; headless Chrome on `login.php` reported 0 CSP violations; a CSP-conformance sweep ran clean across 18 admin pages | **This slice IS the security fix.** S-11, the CSP fail-open and NEW-14 closed with ONE enforcement point in `admin/bootstrap.php` covering all 64 entry points. Five named probes proved every new test fails against the unfixed code. Both review subagents run | n/a — no new UI; the nonce attributes are non-visual | n/a — no new user-facing strings | **yes** — `Helpers::isHttps()` became the single TLS check, replacing four duplicated expressions | `docs/reference/security-headers.md` created; 3 rows in `docs/api/INDEX.md`; audit, decisions (**D-044**), lessons (**L-015**) | **yes** — `security.hsts` filter, with its rollback risk stated | See the slice-8 evidence block below | **PASS** | Row filled retrospectively in slice 9 — it was left `pending` when slice 8 closed, although its evidence block was written. The gap is recorded rather than quietly corrected: a table that says `pending` for a closed slice is the L-002 defect in the project's own test log |
| 9 — `keel-verify` + regenerable INDEX | `scripts/keel-verify` runs and its **FULL OUTPUT** is pasted below; every new check demonstrated to FAIL on an injected violation and pass once reverted | **yes** — the release archive was extracted from a real `git archive` and served over HTTP to test whether shipped dev scripts execute; both guards then re-verified against the edited files, and `router.php` re-verified as still working as the `php -S` router (admin 302) | **This slice found and fixed a live exposure.** `scripts/` is not export-ignored, so it ships to the site root, and the root `.htaccess` serves any existing file (`:23-25`). Verified over HTTP against an extracted archive: `router.php` **executed**, returning an internal 404 page disclosing the admin path, the MCP endpoint and build internals; `upgrade-assert.php` **executed**, HTTP 200 / 1332 bytes. Both now carry SAPI guards (their sibling `seed-playground.php:35` already had one). Recorded as **NEW-28**; the packaging half is Phase 7's | n/a — no UI written in this slice | **yes, as a CHECK rather than as strings** — no new user-facing strings, but the slice adds the catalogue key-parity check across **120 files in 6 sets** (core's 20 + 5 plugins' 20 each), which is the real i18n invariant here (D-006). Proven by injecting both a missing key and an extra one | **yes** — no new product surface. The gate check and the two WARN checks reuse `git check-attr`, which is the same authority that builds the release archive, rather than a second hand-maintained list of what ships | `docs/keel-verify.md` **created** (deliberately at `docs/` root, not `docs/reference/` — it is project tooling like `docs/playground.md`, not a product surface, and INDEX's own scope is `installer/` only); `docs/flows/` **created** with 3 files; `docs/04-adoption-audit.md` (**NEW-27**, **NEW-28**); `docs/decisions.md` (**D-045**); `docs/lessons-learned.md` (**L-016**) | n/a — dev tooling and CI, no product extension points | See the slice-9 evidence block below | **PASS** | **Two findings, both from defining "distributable" honestly rather than assuming it. NEW-27:** all 16 in-product guides under `installer/core/guides/` are stripped from every release archive by the blanket `*.md export-ignore` — verified by extracting the archive, where the directory survives EMPTY and only 2 `.md` files remain repo-wide. They back `klytos_list_guides` / `klytos_get_guide`, whose own tool descriptions declare several REQUIRED before creating content, so on a released install those tools return nothing. **NEW-28:** the dev-script exposure above. Both are H-02's rule reaching further than H-02 recorded; both are made **detectable now** by WARN checks, with the fix left to Phase 7 — the same treatment sprint-1.md scoped for H-01 |

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
| 2026-07-20 (slice 7 session) | documented commands, **port 8080 again held by the same unrelated container** | Caught immediately this time — `docs/playground.md`'s step-2 bind check (added by L-011) reported the port taken, and `curl -D -` confirmed `Server: Apache/2.4.54 (Debian)` before anything was believed. Re-run on verified-free ports (8104 for the walk, 8103 for the new test class): admin **302**, `klytos_session` cookie present, the S-09 defect reproduced live as **401** `authentication_required` (not the 302 the audit recorded — slice 4 changed that). The bind check paid for itself in seconds, which is the whole point of L-011 | yes |

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
