# Sprint 1 — Close the authorization axis, and make it provable

- **Planned:** 2026-07-18 (plan mode, approved by the user)
- **Status:** planned
- **Scope basis:** the audit's fix-now bucket (D-018) — S-01…S-09 + T-01 — plus T-02 (required *by*
  this sprint's verification), NEW-01 (found in re-validation; it defeats every gate below), H-04
  (pulled in by D-022), and the Phase 5 scaffold duties (`scripts/keel-verify`, D-04).

## Why this sprint exists

Authorization is the weak axis of a product whose premise is handing control to an autonomous agent.
An authorization fix cannot be demonstrated by reading a diff — Keel's test-point rule requires a
command and an output — so the test harness (T-01) and the playground (T-02) are in scope, not
deferred.

## Re-validation of assumptions (Phase 5 §0 kickoff, step 2) — done 2026-07-18

Verified against source this session, not against the audit text. Three audit claims were wrong; two
new findings outrank most of the bucket. Corrections are recorded in `docs/04-adoption-audit.md`;
the summary lives there, not duplicated here.

- **S-04 divergence: refuted.** The two matrices are byte-for-byte identical. Dead duplicate, drift
  hazard — not a live inconsistency.
- **S-12: partly refuted.** An owner-only gate exists (ad-hoc). The real defect is a state-changing
  **GET** with no method check and no CSRF, plus a docblock asserting re-auth/2FA/email that do not
  exist.
- **S-07: sharper than stated.** 15/66 ≈ 23% overall, but admin **pages** are 12% gated (5 real
  gates in 42 files) vs API 42%.
- **NEW-01 (CRITICAL):** `klytos_current_user()` promotes any session lacking `klytos_user_id` to
  `owner`. Prerequisite for this sprint, not an addition.
- **NEW-02 (CRITICAL):** zero permission checks across all 172 MCP tools. **Sprint 2** by D-020.

## Acceptance — this sprint is done when

1. Every finding in scope has a **named automated test asserting the refusal**, not just a code
   change. A structural fix without its own test is an unverified claim.
2. The playground boots from `docs/playground.md` as written, and a fresh-context checker who has
   only that document can walk every try-it flow.
3. `scripts/keel-verify` **fails the build** when any file under `admin/` or `admin/api/` has neither
   a gate-map entry nor an inline gate — verified by removing one gate, observing the failure, and
   restoring it.
4. The full suite is green (not only this sprint's tests), `keel-verify` output is pasted as
   evidence, and the upgrade path is tested **from the real previous version** — installed base is
   `yes`, so a clean-install-only test does not satisfy this.
5. The user's own test verdict is recorded.

## Slices

| # | Slice | Closes | Status | Test point result | Notes |
|---|-------|--------|--------|-------------------|-------|
| 0 | Playground + gate zero | T-02 | **closed 2026-07-18** | **PASS** — seeded, booted, admin 302→login, MCP 177 tools, all deny checks 403; evidence in `docs/05-test-points.md` | Found NEW-03 and NEW-04 (both deferred by D-026). Gate zero baseline-locked by D-025. `.gitignore` hardened: identity keys, plain `.json`, `logs-*`, `_cache` |
| 1 | Test harness + dev manifest | T-01, T-04 | **closed 2026-07-19** | **PASS** — `composer install` clean, `phpunit` 9 tests/37 assertions green, `phpcs` clean, D-025 baseline unchanged; evidence in `docs/05-test-points.md` | Two tiers built. Integration tier proven to SKIP (not pass) without the playground. PHPUnit pinned + `composer.lock` tracked (D-027). Testing rule propagated to all 7 assistant containers + the core skill |
| 2 | `vendor-ai/` manifest + CVE audit | H-04 | **closed 2026-07-19** | **PASS — 5 CVEs open for user triage** — manifest resolves to the 16 vendored versions with 0 deltas; `composer audit` full output pasted; suite 12 tests/57 assertions green; D-025 baseline unchanged; evidence in `docs/05-test-points.md` | Unknown now bounded: fixes are constraint-compatible (guzzle 7.12.1 / psr7 2.12.1), so a re-vendor, not a dependency rewrite — still a scope change per D-022. Manifest counts corrected: **16 packages, not 9**. New findings NEW-05 (CVEs), NEW-06 (PHP 8.3 floor vs declared 8.1+), NEW-07 (2 BSD packages with no licence text — notice fixed, packaging half left to H-02) |
| 3 | One matrix + fail-closed current user | S-04, NEW-01 | **closed 2026-07-19** | **PASS** — 32 tests/219 assertions green; the 4 refusal tests proven to FAIL against the unfixed code; upgrade from real v0.30.1 passes 17/17 including the failing-migration boot; D-025 baseline **improved** to 201/488; evidence in `docs/05-test-points.md` | Storage isolation resolved first (D-030). S-04 closed the **opposite way** to the audit's note — `UserManager` keeps the matrix, the helper delegates — because slice 4 and Sprint 2 hold a user object, not a session. The boot crash on a failing v1.x migration was fixed here rather than deferred (D-031, user decision). `code-reviewer` returned a BLOCKING finding — the `try`/`catch` had no test driving it — fixed with two new upgrade phases, then proven to detect its absence. New findings: **NEW-08** (no owner-recovery path anywhere in the product) and a sharpening of NEW-02 (AI tool filter fails open on unrecognized roles). Lesson **L-006** |
| 4 | `klytos_require_permission()` + central default-deny gate | S-07 | **closed 2026-07-19** | **PASS** — 45 tests/270 assertions green; 5 of 6 HTTP tests proven to FAIL against the unfixed code; `keel-verify` proven to fail on a removed gate and restored; full 65×4 role walk (260 requests); upgrade 17/17; D-025 baseline unchanged at 201/488; evidence in `docs/05-test-points.md` | Resolved by **inversion**, not by the audit's suggested shape: a gate map + one enforcement point in `admin/bootstrap.php` (verified: all 66 files require it), where an ABSENT entry is a REFUSAL. Coverage 15/66 → 65/66, the 66th being `bootstrap.php`, deliberately unmapped. The denial shape was promoted from `router.php:438-447`, not reinvented. 5 capabilities added to the one matrix; `ai.use` excludes `editor` while NEW-02 is open (D-035). API surfaces now get 401 JSON instead of a 302 to HTML. **Bugs closed in-path** (D-033): NEW-10 (setup-wizard escalation) and NEW-12 (`api/download-identity.php`'s **three stacked defects**, each masking the next). **NEW-09's fix was implemented and then REVERTED (D-036)** — the security audit showed the exemption opens a full account-takeover primitive, and buys nothing because passkey login cannot complete regardless; NEW-09 stays open with its fix shape recorded. `plugin-page.php` now denies a plugin declaring no capability — a breaking change for third parties (D-034). Decisions D-032…D-036; lessons **L-007**, **L-008**, **L-009**. Both review subagents returned **blocking** findings and both were fixed, not argued with. **NEW-11 found, NOT fixed:** `Auth::login()` never consults `UserManager`, so only `config['admin_user']` can log in — likely why S-07 survived unnoticed |
| 5 | Named escalations, one test each | S-01,02,03,05,06,12 | **closed 2026-07-19** | **PASS** — 60 tests/336 assertions green; 3 of the new tests proven to FAIL against the unfixed code; `keel-verify` 2/2; upgrade from real v0.30.1 passes; D-025 baseline **improved** to 199/488; evidence in `docs/05-test-points.md` | Each of the six findings now has a named test that fails if it regresses, and each asserts its POSITIVE counterpart too (L-008). Two live defects closed that the gate could not reach: an **S-06 residue** — `api/tasks.php` never re-gated `update`/`complete` at `tasks.manage` while its page twin does, so an editor was refused through the UI and allowed through the API it calls — and **S-12**, where the export now requires POST + CSRF and the caller was retargeted, because `security.php`'s 302 redirect *was* what made a state-writing private-key export answer GET. The false docblock (re-auth, 2FA, email notification) is corrected, not implemented; the missing protections are recorded as **NEW-13** and bound to the authentication slice (D-040). **The most consequential find was in the harness, not the product:** `assertConfigNotMutated()` had been **INERT since slice 3** — it ran after the restore and re-hashed the restored file, comparing the snapshot against itself. Proven inert with a probe, repaired to compare decrypted content minus `scheduler_last_run`, and pinned by permanent two-way cover (D-039, **L-010**). It then caught the S-12 GET writing config, unprompted. HTTP harness extracted to `tests/AdminHttpTestCase.php` rather than copied. Decisions **D-038…D-040**; lesson **L-010** |
| 6 | `SafeHttp` + apply at every risky call site | S-08 | **closed 2026-07-19** | **PASS** — **107 tests/472 assertions** green (95/445 at first pass, grown by the review cycle); **6 of the 8** endpoint tests proven to FAIL against the unfixed code; the old transport demonstrated following a 302 into `169.254.169.254`; `keel-verify` 2/2; upgrade from real v0.30.1 passing; D-025 baseline **improved** to 193/488 and plugins **131→113**; evidence in `docs/05-test-points.md` | Promoted, not rewritten: the validation came from `ImportValidator::validateUrl()` and that class now **delegates**, so one implementation exists rather than two — the same move slice 4 made with the denial shape. **The finding was wider than the audit recorded:** the *discovered* oEmbed endpoint is attacker-controlled too and, unlike the page fetch, its response **is** echoed back — a full read-SSRF primitive the recorded remediation would not have closed. And pre-flight validation alone was never sufficient: `CURLOPT_REDIR_PROTOCOLS` is set nowhere in the repo, the `stream_context_create` sites inherit PHP's default of **20** hops, and only `PageFetcher` re-validated after a redirect at all — and only the FINAL url, after the body was already fetched. `SafeHttp` walks the chain itself. Applied at 5 call sites; `klytos_http_get()` deliberately left alone (breaking change for plugins → its own decision, the D-034 precedent). Fixed in-path per D-031: `HttpClient::requestWithStream()` accepted `follow_redirects` and then **dropped it**, so on a host without ext-curl the control had a silent bypass. **A second harness defect, in the L-010 shape:** the integration tier had **never** reset hooks while the unit tier always did, so a filter registered by one test leaked into every later one — invisible for five slices because no integration test had ever registered a hook (D-042, **L-012**). Caught by asserting on the refusal REASON, not just the refusal. **NEW-15 recorded, NOT fixed:** DNS rebinding survives; the reference doc says so in those words rather than implying the axis is closed. **The review cycle changed the slice materially, and the worst finding came from neither reviewer:** testing the alternative address notations found `http://[::ffff:127.0.0.1]/` was **ALLOWED** and fetched loopback for real — `filter_var`'s reserved-range flags do not understand IPv4-mapped IPv6, so every private address had a spelling that bypassed the control. The `security-auditor` raised the case and reasoned past it ("very likely already handled correctly"); running it took two minutes (**L-013**). Both reviewers also caught that the importer's three fetchers were left following redirects unvalidated — which D-041 itself cited `PageFetcher` **by name** as the unsound pattern while shipping it — and that `integrity-checker.php` had a SECOND untrusted fetch from the same plugin header, unfixed. All converted. A new oracle introduced in `WebhookManager::create()` was removed. Decisions **D-041** (amended post-review), **D-042**; lessons **L-011** (a Docker container held the documented playground port and three "findings" were an unrelated Apache), **L-012**, **L-013** |
| 7 | Public comments work, off the admin path | S-09 | **closed 2026-07-20** | **PASS** — **116 tests/541 assertions** green; every new test **proven to FAIL** against the unfixed code by seven named probes; the four criteria walked for real in the playground; `keel-verify` 2/2 (64 surfaces); upgrade from real v0.30.1 passing; D-025 baselines unchanged at 193/488 and 113/109; evidence in `docs/05-test-points.md` | **Closed by RELOCATION, not by the recorded remediation.** The audit said "add it to `$preAuthScripts`"; that fails criterion 4 by construction, because the handler sat inside the randomized admin directory and any form posting to it publishes that name on every generated page (`helpers.php:192-197`). **The D-036 question was asked before acting and changed the design:** `admin/bootstrap.php` runs the cron manager and action scheduler on every request, so exempting an anonymous endpoint would have handed every passer-by a scheduler trigger. The handler is now `installer/public/comment-submit.php`, copied to the WEB ROOT by the build engine — the x402 placement precedent, though deliberately **not** its bootstrap, which requires a `bootstrap-minimal.php` that does not exist. **The per-session rate limit was not weak, it was inert:** the session cookie is scoped `path=<base>/admin/` `SameSite=Strict`, so a form on the static site can never send it and every submission arrived with a fresh session. Replaced with the existing persistent IP-keyed `MCP\RateLimiter` — reused, not forked. **The named finding was the shallowest of three:** `SiteConfig::setValue()` **did not exist** although `comment-tools.php` calls it four times, so comments could never be enabled at all (**NEW-16**, fixed in path, the L-009 shape again); and **no comment form exists in the generated output**, which remains true by decision (D-023 owns it) and is stated plainly rather than implied. **The `security-auditor` returned a blocking finding that restructured the slice:** the rate limit ran AFTER `App::boot()` (which loads every plugin), at a fixed URL on every install — and worse, the honeypot ran BEFORE the rate limit, so a `_honeypot` flood was never counted. Now a non-filterable flood ceiling runs pre-boot and the filterable policy runs after. The `code-reviewer` returned six non-blocking items, four taken — including that **`installer/public/` was outside the phpcs scan set entirely**, so no public entry point had ever been linted. Decisions **D-043** (amended after its review cycle); lesson **L-014**. Recorded and NOT fixed: **NEW-17**, **NEW-18**, **NEW-19**, **NEW-20** |
| 8 | HSTS + CSP fail-open + hardening | S-11, part of S-10, **NEW-14** | **closed 2026-07-20** | **PASS** — **138 tests/603 assertions** green; every new test **proven to FAIL** against the unfixed code by five named probes; headers asserted on **real** playground responses including the 401 and 403 refusals; headless Chrome on `login.php` clean (0 CSP violations) and a CSP-conformance sweep clean across 18 admin pages; `keel-verify` 2/2 (64 surfaces); upgrade from real v0.30.1 passing; all four D-025 baselines held; evidence in `docs/05-test-points.md` | Resolved with the **S-07 answer**: ONE enforcement point in `admin/bootstrap.php` rather than 25 remembered calls. **Two of the finding's own numbers were wrong and re-validation caught both:** `admin/api/` holds **23** files, not 24 (slice 7 deleted one), and "every admin PAGE gets them via `templates/header.php`" is **false for five pages** — of which **`login.php` and `logout.php` called nothing at all**, so the login form was served with no CSP, no `nosniff` and no `X-Frame-Options`. The real gap was **25 surfaces including the login form**. **Placement is the load-bearing part and is bounded on both sides:** it cannot go later (the auth guard's 401, `klytos_deny()`'s 403, and three redirects all emit below it — all now carry headers) and cannot go earlier (`registerAutoloader()` is **Step 1 of `App::boot()`**, probed not inferred, per L-006); the residue is recorded as **NEW-22**. **The CSP fail-open was load-bearing, not cosmetic:** `login.php` has an inline 2FA switcher, so giving it a CSP without nonce-ing that block would have broken two-factor login — a regression shipped *by* a security fix. **A catch that would have broken every generated site:** failing closed at `installer/index.php` would have disabled the GDPR consent banner, because the front controller `readfile()`s pre-generated HTML containing inline script that cannot carry a per-request nonce; that call site now states an explicit policy so the weakening appears in a diff (**NEW-23**). **Two false passes found in this slice's own tests (L-010, a third time):** `header()` is a no-op under the CLI SAPI so three unit assertions could never fail — repaired by splitting out the pure `buildSecurityHeaders()`; and an integration assertion passed with the nonce stripped because a `<style>` block also carried one. `style-src` keeps `'unsafe-inline'` deliberately (S-10) with a test asserting that weakness on purpose, because a nonce source there makes browsers **ignore** `unsafe-inline` and would break all 349 attributes at once. Decision **D-044** (amended after its own review cycle); lesson **L-015**; findings **NEW-21**, **NEW-22**, **NEW-23** recorded and not fixed. **The review cycle mattered in both directions.** The `code-reviewer`'s single BLOCKING finding was **REFUTED as stale** — it read a mid-slice snapshot in which `PROGRESS.md` had not yet been written, and the file was already correct by the time the report arrived; verifying cost one `grep` and it is why nothing was "fixed" that was not broken (**L-015**). The `security-auditor` then found **the slice's own subject one directory outside its scope**: `installer/public/comment-submit.php` and `x402-gate.php` send **no** security headers at all, on anonymous fixed-URL endpoints — NEW-14's shape at file N+1, recorded as **NEW-24**, with the reference doc rewritten to say the slice covers admin + front controller and *not* those two rather than overclaiming. Three real defects fixed: two unescaped nonce echoes in `updates.php`, a surviving `isHttps()` duplicate in the very file the slice was editing, and **a count I had written this slice** — "12 `<style>` blocks" is **10**, the audit's 12 having counted two `srcdoc`-embedded occurrences that cannot carry a nonce, copied across without being measured. **NEW-25** and **NEW-26** recorded and not fixed |
| 9 | `scripts/keel-verify` + regenerable INDEX | Phase 5 §1a, D-04 | planned | — | Carries slice 4's mechanical check |

Full slice detail — the exact files, the reuse targets, and the per-slice test point — is in the
approved plan; the authoritative per-slice test-point evidence lands in `docs/05-test-points.md` as
each slice closes.

### Slice-by-slice test points (the definition of done per slice)

- **0** — playground boots from documented commands; `phpcs --standard=phpcs.xml` clean; one MCP
  `tools/list` round trip returns the tool set. (This project's gate zero,
  `03-technical-plan.md:108`.)
- **1** — `composer install` clean; one trivial passing test; `phpunit` green; `phpcs` green.
- **2** — manifest resolves; `composer audit` output pasted. CVE findings are **reported and
  triaged, never silently patched**.
- **3** — viewer denied an owner-only permission; unknown permission denied; owner shortcut intact;
  **a session with no `klytos_user_id` is denied, not promoted**; v1.x migration idempotent;
  **upgrade tested from the real previous version**.
- **4** — per-role integration tests against representative pages and endpoints, asserting the
  403/401 **shape**, not only the status; all 66 files carry a map entry; the keel-verify gate check
  demonstrably fails on a removed gate.
- **5** — one named test per finding, each asserting the refusal; full suite green.
- **6** — refusals for `127.0.0.1`, `[::1]`, `169.254.169.254`, a non-HTTP scheme, and **a public URL
  that 302-redirects to a private one** (the case pre-flight validation misses).
- **7** — anonymous submission succeeds; honeypot rejects a bot; rate limit holds **across sessions**;
  no admin-directory name appears in any frontend-reachable URL.
- **8** — headers asserted on a real playground response; admin renders with the tightened CSP,
  browser console clean.
- **9** — `scripts/keel-verify` runs; its **full output** pasted into `docs/05-test-points.md`.

## Explicitly out of scope (named, so it is not mistaken for oversight)

- **NEW-02 — MCP tool authorization.** Sprint 2, per D-020. Stated plainly: when Sprint 1 closes, the
  admin is gated and the product's primary interface is not.
- **S-10 `style-src 'unsafe-inline'` removal.** 349 inline `style=` attributes across 40 files; an
  attribute cannot take a nonce, so all 349 must become CSS classes. Its own sprint. The 12 `<style>`
  blocks *are* nonced in slice 8.
- **A-01…A-07 accessibility.** A dedicated sprint after this one, per the audit's trigger register.
  A-05 (zero ARIA in generated output) is the highest-stakes item after the authorization axis.
- **H-01, H-02, H-03, H-07 release hygiene.** They close by construction in the next full Phase 7.
  Slice 9 makes H-01 *detectable* now; fixing it is Phase 7's.

## Risks carried into this sprint

1. ~~**Default-deny can lock someone out.**~~ **Materialised as designed, and mitigated — 2026-07-19,
   slice 4.** The full walk was run: **65 surfaces × 4 roles = 260 real HTTP requests**, and no role
   lost a surface its capabilities entitle it to. The dashboard, page list, profile, security and
   notices remain reachable by all four roles; every 403-for-owner in the walk was traced to the
   endpoint's own CSRF or 2FA check on a bare GET, not to the gate. Two lockout paths were real and
   are handled deliberately rather than by accident: a plugin-registered page whose manifest declares
   no capability is now **denied** (D-034, a breaking change for third parties, verified to break no
   shipped plugin), and any unenumerated admin file is denied until mapped — which is the point, and
   is caught before release by `scripts/keel-verify` rather than by a user.
2. ~~**Slice 2 is genuinely unbounded** until `composer audit` runs. A CVE requiring an upgrade across
   482 vendored files may exceed this sprint and spawn its own.~~ **Resolved 2026-07-19 — the risk
   materialised, but bounded.** The audit found 5 medium CVEs (NEW-05). They do require a re-vendor
   across 482 files, so they do **not** enter this sprint; the fixes are constraint-compatible, so
   the work is a re-vendor rather than a dependency-tree rewrite. Awaiting the user's triage
   decision, per D-022.
3. **The playground writes real local credentials.** Seeded `config/`/`data/` stay gitignored;
   `docs/playground.md` carries throwaway values only. The pre-commit gate is the net.
4. ~~**The integration tier has no storage isolation**~~ *(added 2026-07-19, slice 1 review)*.
   **Resolved 2026-07-19, before slice 3, by D-030** — snapshot/restore of `installer/config/` +
   `installer/data/` around every integration test (`tests/PlaygroundState.php`), ON by default. The
   "either/or" in the original note was settled by the requirement, not by preference: fixtures
   cannot express slice 3's migration test, which must delete a record belonging to the seed. Proven
   to fail with isolation off before being trusted. Residual risk carried forward, stated rather than
   closed: a file restore cannot refresh `App::$config`, `EncryptionLevelTrait::$cachedEncryptionLevel`,
   `OptionsManager::$cache` or `AiKeyManager::$cache` — the tier fails loudly on core-config mutation
   instead of covering it.
5. **Never run the web installer in-tree** — `install.php:750` renames the tracked `install.php` and
   `:811-824` renames the whole `installer/` directory and writes into the repo's parent. This is
   documented loudly in `docs/playground.md` as part of slice 0.

## Close-out

*Filled at close.* Required: full suite green · `keel-verify` output pasted · `docs-verifier` over
everything touched · playground-QA fresh-context pass · numbered try-it script handed to the user
with the debug log ON · the user's recorded verdict · `PROGRESS.md` / `lessons-learned.md` /
`token-ledger.md` updated · continuation prompt produced unprompted.
