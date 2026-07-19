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
| 3 | One matrix + fail-closed current user | S-04, NEW-01 | planned | — | Prerequisite for slices 4–5 |
| 4 | `klytos_require_permission()` + central default-deny gate | S-07 | planned | — | The systemic fix; 66 files mapped |
| 5 | Named escalations, one test each | S-01,02,03,05,06,12 | planned | — | Proves each finding individually |
| 6 | `SafeHttp` + apply at every risky call site | S-08 | planned | — | Promote, don't rewrite |
| 7 | Public comments work, off the admin path | S-09 | planned | — | Also fixes the per-session rate limit |
| 8 | HSTS + CSP fail-open + hardening | S-11, part of S-10 | planned | — | `unsafe-inline` removal deferred, with trigger |
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

1. **Default-deny can lock someone out.** The map is complete by construction, but a
   plugin-registered admin page or an unenumerated path would 403. Mitigation: the keel-verify check
   plus a full playground walk of all 42 pages per role before the sprint closes.
2. ~~**Slice 2 is genuinely unbounded** until `composer audit` runs. A CVE requiring an upgrade across
   482 vendored files may exceed this sprint and spawn its own.~~ **Resolved 2026-07-19 — the risk
   materialised, but bounded.** The audit found 5 medium CVEs (NEW-05). They do require a re-vendor
   across 482 files, so they do **not** enter this sprint; the fixes are constraint-compatible, so
   the work is a re-vendor rather than a dependency-tree rewrite. Awaiting the user's triage
   decision, per D-022.
3. **The playground writes real local credentials.** Seeded `config/`/`data/` stay gitignored;
   `docs/playground.md` carries throwaway values only. The pre-commit gate is the net.
4. **The integration tier has no storage isolation** *(added 2026-07-19, slice 1 review)*. It shares
   the App singleton and the real on-disk playground with no per-test rollback. Slices 3–5 write
   state through this seam, so their tests would become order-dependent and would mutate the
   playground permanently. **Resolve before slice 3 starts** — either a per-test reset primitive or
   fixtures each test creates and destroys. Tracked in `PROGRESS.md` deferred items.
5. **Never run the web installer in-tree** — `install.php:750` renames the tracked `install.php` and
   `:811-824` renames the whole `installer/` directory and writes into the repo's parent. This is
   documented loudly in `docs/playground.md` as part of slice 0.

## Close-out

*Filled at close.* Required: full suite green · `keel-verify` output pasted · `docs-verifier` over
everything touched · playground-QA fresh-context pass · numbered try-it script handed to the user
with the debug log ON · the user's recorded verdict · `PROGRESS.md` / `lessons-learned.md` /
`token-ledger.md` updated · continuation prompt produced unprompted.
