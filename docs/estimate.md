# Estimate — Klytos CMS

> Internal working estimate. AI-time based: AI session hours + vibe coder (supervision) hours.
> **Never** based on traditional human development time.
> `Client budget: no` (D-013) — there is no client-facing `docs/budget.md`, by design, not by omission.
> Actuals accumulate in `docs/token-ledger.md`; reconciled at release (Phase 7).

---

## Estimate v1 — Phase 5, Sprint 1 scope — 2026-07-18

This is the first estimate for the project. It deliberately covers **Sprint 1 only**, not the whole
remaining roadmap: the project was adopted brownfield, so there is no v1 feature list to estimate
against — there is a triaged audit. Estimating beyond Sprint 1 today would be false precision, since
Sprint 2's shape (MCP tool authorization, ~172 tools) depends on what Sprint 1's enforcement helper
turns out to look like.

### Scope basis

10 slices in `docs/sprints/sprint-1.md`, derived from:

- `docs/04-adoption-audit.md` fix-now bucket (D-018): S-01…S-09 + T-01
- T-02 (playground) — required *by* this sprint's verification, so it lands with it
- NEW-01 (fail-closed current user) — found in re-validation; it defeats every gate in the sprint
- H-04 (`installer/vendor-ai/` manifest) — pulled in by D-022
- Phase 5 scaffold duties: `scripts/keel-verify` + a regenerable `docs/api/INDEX.md` (D-04)

Concrete counts the numbers are computed from: **66 files** to gate (42 admin pages + 24 API
endpoints), **6 named escalations** each needing its own refusal test, **5 outbound-fetch call
sites** for the SSRF fix, **482 vendored files** across 9 packages to manifest, **2 test tiers**
(unit + integration) because the auth code is not unit-testable.

### AI working hours (itemized)

| Segment (AI does) | Hours (low–high) | Basis |
|---|---|---|
| Slice 0 — playground, seeder, `php -S` router, `playground.md` | 2.5–4 | No Docker/wizard needed; router must replicate the `.htaccess` denies |
| Slice 1 — test harness, `composer.json`, two-tier `tests/` | 2.0–3.5 | Unit tier is straightforward; integration tier needs the `$_SESSION` seam |
| Slice 2 — `vendor-ai/` manifest + `composer audit` | 3.0–6.0 | **Widest band.** 482 files, 9 packages, no upstream pins to read |
| Slice 3 — one matrix, fail-closed current user, v1 migration | 2.5–4.0 | Small diff, but the migration must be idempotent and upgrade-tested |
| Slice 4 — `klytos_require_permission()` + central default-deny map | 5.0–8.0 | 66 files mapped and each exercised per role |
| Slice 5 — 6 named escalations, one refusal test each | 3.0–5.0 | Mostly closed by slice 4; this is the proving work |
| Slice 6 — `SafeHttp` + 5 call sites + redirect revalidation | 4.0–6.0 | Promote existing `ImportValidator`; IPv6 gap to fix |
| Slice 7 — public comment route, off the admin path | 3.0–5.0 | New public bootstrap + Router route + rate-limit fix |
| Slice 8 — HSTS, CSP fail-open, nonce the 12 `<style>` blocks | 1.0–2.0 | Cheapest slice; `unsafe-inline` removal explicitly out of scope |
| Slice 9 — `scripts/keel-verify` + INDEX extraction script | 3.0–5.0 | Carries slice 4's mechanical gate check |
| Sprint close-out — docs-verifier, playground-QA, state, continuation prompt | 1.5–2.5 | Phase 5 §5, mandatory |
| **Total AI** | **30.5–51.0 h** | |

This is above the 32–48 h band stated in the approved plan. The plan's figure was explicitly labelled
rough; itemizing per slice moved the top end up, driven by slices 2 and 4. Recording the honest
number rather than defending the earlier one.

### Vibe coder hours (itemized)

| Segment | What the developer does | Hours (low–high) |
|---|---|---|
| Sprint kickoff | Answer the question batches, approve the plan (done — 2026-07-18) | 0.5–1.0 |
| During the build | Open sessions, read summaries, approve gates, unblock questions (20–45 min/day) | 5.0–9.0 |
| Slice 2 decisions | Triage whatever `composer audit` returns — upgrade, accept, or defer | 0.5–1.5 |
| Test points | Walk the flows in the playground as a real user, across 4 roles | 2.0–3.5 |
| Sprint close | Follow the numbered try-it script, return the verdict either way | 1.0–1.5 |
| Commits / pushes | Repo operations the AI does not perform unattended | 0.5–1.0 |
| **Total developer** | | **9.5–17.5 h → plan for ~18 h with margin** |

### Contingency: +30% (user-chosen, 2026-07-18)

Justified by slice 2's unknown CVE surface and slice 4's 66-file map, which must be exercised for
real across four roles rather than reasoned about.

| | Base | With +30% |
|---|---|---|
| AI hours | 30.5–51.0 h | **40–66 h** |
| Developer hours | 9.5–17.5 h | **12.5–23 h** |

### Estimated calendar delivery

At the stated availability of **5–10 h/week** supervision, and with 12.5–23 h of developer time
required, Sprint 1 lands over roughly **3–5 calendar weeks**. Labelled an estimate, not a commitment —
the AI only works in sessions the developer opens, so calendar time tracks supervision availability,
not AI hours.

### AI cost

**Mode: subscription** (flat monthly fee) — the marginal token cost of this sprint is **≈ 0**. It is
recorded as the mode, not billed as a line item; the supervision hours above already account for the
developer's real time. Rows still accumulate in `docs/token-ledger.md`, because that is what
calibrates future estimates and because the payment mode can change.

### Assumptions & risks

- **Assumed:** the environment stays as verified on 2026-07-18 — PHP 8.3.12, Composer 2.9.5, phpcs
  3.13.5, PHPUnit 11.5.53, Docker available but **not required** by the chosen playground recipe.
- **Assumed:** no Docker/MySQL tier this sprint. Storage tests run against file storage; a
  `database-storage.php` tier would add hours and is not in scope.
- **Risk — slice 2 is genuinely unbounded.** A CVE requiring an upgrade across 482 vendored files
  with no upstream pinning could exceed this sprint and spawn its own. Findings are reported and
  triaged, never silently patched. If this happens, it is a scope change → Estimate v2.
- **Risk — default-deny may lock out an unenumerated path.** Mitigated by the keel-verify check plus
  a full per-role playground walk, both already costed into slices 4 and 9.
- **Risk — the auth code resists testing by construction.** `App::$instance` has no reset, paths are
  hardcoded, and `helpers-global.php` has zero `function_exists()` guards. The integration tier works
  around this via `$_SESSION`; if that seam proves insufficient, slice 1 grows and slices 3–5 slip.
- **Excluded, and costed nowhere here:** MCP tool authorization (Sprint 2, D-020); the **theme
  package redesign** (D-023, `docs/theme-package-model.md`) — designed but explicitly sequenced after
  Sprint 1, and it runs through Keel Phases 3–4, so it needs its own estimate version rather than a
  line here; the 349 inline `style=` attributes (S-10); the accessibility sprint (A-01…A-07); release
  hygiene (H-01/02/03/07 — Phase 7); Phase 6 documentation; Phase 8 website.
- **Not estimated on purpose:** anything past Sprint 1. Sprint 2 gets its own estimate version once
  Sprint 1's enforcement helper exists and its real shape is known.

---

## Estimate v2 — Phase 5, Sprint 2 scope (MCP tool authorization, NEW-02 / D-020) — 2026-07-22

Trigger fired: Sprint 2 is planned (`docs/sprints/sprint-2.md`, approved 2026-07-22). Sprint 1's
enforcement helper now exists (`klytos_require_permission()` + the ONE matrix), so the shape v1 said
could not be estimated is known. This version covers **Sprint 2 only**; the theme-package sprint
(D-023) and the vendor-ai CVE re-vendor (D-029) still get their own versions when planned.

### Scope basis

4 slices in `docs/sprints/sprint-2.md`, derived from audit **NEW-02** and the five source-verified
corrections to the recorded plan (D-046…D-049). Concrete counts the numbers are computed from:
**172 registered / 169 live** MCP tools to map to capabilities, **1** central map file mirroring
`admin-gate.php`, **1** enforcement point (`ToolRegistry::call()`) reached by **2** callers, **3**
credential types (app-password, bearer, OAuth) whose identity must be surfaced and migrated,
**2** shipped MCP plugins (16 + 10 tools) to bring under the map, **1** new keel-verify check (→10),
**20** i18n catalogues for the refusal keys, **4** skills to update, and a new HTTP test port (:8105).

### AI working hours (itemized)

| Segment (AI does) | Hours (low–high) | Basis |
|---|---|---|
| Planning (sessions 13 + 14) — kickoff re-validation, 4 decisions, artifacts | 3.0–5.0 | Two sessions; three Explore sweeps + self-verification (L-013) dominated |
| Slice 1 — actor resolution: `token-auth`/`auth`/`oauth-server`, role on records, idempotent boot migration, upgrade-tested | 3.5–6.0 | The novel work; migration must be idempotent and proven on real v0.30.1 |
| Slice 2 — gate + `tool-capabilities.php` map + `PermissionDeniedException` + `setActor()` + `listTools()` filter + both transport catches + keel-verify check 10 + HTTP :8105 tests | 5.0–8.0 | **Widest band.** The core; 172 tools mapped, refusal proven on the wire |
| Slice 3 — loader fail-loud, wire `integrity-tools`, 2 plugins declare caps, `chat-engine` default-deny | 3.0–5.0 | Mostly wiring, but each behaviour proven against unfixed code |
| Slice 4 — reference doc, count truth across 4 surfaces, refusal i18n × 20, playground, 4 skills, D-035 widen | 3.5–5.5 | Documentation-heavy; the count reconciliation touches INDEX/audit/skills/reference |
| Sprint close-out — docs-verifier, playground-QA, state, continuation prompt | 1.5–2.5 | Phase 5 §5, mandatory |
| **Total AI** | **19.5–32.0 h** | |

Below Sprint 1's 30.5–51.0 h band, and the reason is structural: Sprint 1 built the harness, the
playground, the matrix, the admin gate and `keel-verify` from nothing; Sprint 2 **reuses** all of
them (the matrix, `klytos_require_permission()`'s intent, `AdminHttpTestCase`, the gate-map pattern,
the keel-verify framework). The novelty is concentrated in slice 1 (identity from a credential) and
the enforcement point in slice 2.

### Vibe coder hours (itemized)

| Segment | What the developer does | Hours (low–high) |
|---|---|---|
| Sprint kickoff | Take the 4 decisions, approve the plan (done — 2026-07-21/22) | 0.5–1.0 |
| During the build | Open sessions, read summaries, approve gates, unblock (20–45 min/day) | 4.0–7.0 |
| Slice 4 decision | Confirm the D-035 `ai.use` widening to editor | 0.25–0.5 |
| Test points | Walk `tools/call` in the playground across roles, incl. a viewer bearer token | 1.5–3.0 |
| Sprint close | Follow the numbered try-it script, return the verdict either way | 1.0–1.5 |
| Commits | Repo operations the AI does not perform unattended | 0.5–1.0 |
| **Total developer** | | **7.75–14.0 h → plan for ~14 h with margin** |

### Contingency: +30% (same basis as v1, carried)

Justified by slice 1's migration reaching a released installed base, and slice 2's 172-tool map that
must be exercised for real over HTTP rather than reasoned about.

| | Base | With +30% |
|---|---|---|
| AI hours | 19.5–32.0 h | **25–42 h** |
| Developer hours | 7.75–14.0 h | **10–18 h** |

### Estimated calendar delivery

At the stated **5–10 h/week** supervision and 10–18 h of developer time, Sprint 2 lands over roughly
**2–4 calendar weeks**. Estimate, not commitment — calendar time tracks supervision availability, not
AI hours.

### AI cost

**Mode: subscription** — marginal token cost **≈ 0**, recorded as the mode, not billed. Rows keep
accumulating in `docs/token-ledger.md`.

### Assumptions & risks

- **Assumed:** the environment stays as verified — PHP 8.3.x, Composer 2.9.x, phpcs 3.13.x, PHPUnit
  11.5.x. No Docker/MySQL tier; storage tests run against file storage.
- **Risk — the MCP rate limit (60/min per identity) bites the test matrix.** A 4-role × N-tool HTTP
  walk will trip it; mitigated by batching or filtering the limit via `addTemporaryFilter`. Costed
  into slice 2.
- **Risk — the migration reaches a released installed base.** Existing app-password/bearer/OAuth
  records get a role on upgrade; an incorrect migration would change who can do what. Mitigated by the
  real-previous-version upgrade test (mandatory, `Installed base: yes`). Costed into slice 1.
- **Risk — latent capability (L-014).** Every credential that exists resolves to owner today, so only
  a `role=viewer` bearer token exercises a real denial. Not a schedule risk but an honesty one — the
  reference doc states it plainly; no extra hours, but no green-over-dead test point either.
- **Excluded, and costed nowhere here:** NEW-11 authentication (per-role app-password login); the
  theme-package redesign (D-023); the vendor-ai CVE re-vendor (D-029, its own Estimate version); the
  349 inline `style=` attributes (S-10); the accessibility sprint (A-01…A-07); Phase 6 docs; Phase 8.
- **Not estimated on purpose:** anything past Sprint 2.

## Estimate v3 — Phase 5, Sprint 3 scope (vendor-ai CVE remediation, NEW-05 / D-029 / D-052, + NEW-06) — 2026-07-25

Trigger fired: **D-029 requires its own estimate version**, and its trigger ("Sprint 1 close") fired
2026-07-20; Sprint 2 closed 2026-07-24 with this queued as the next slice. This version covers
**Sprint 3 only**. The theme-package sprint (D-023/D-024) still has no estimate and gets its own
version when it is planned.

### Scope basis

2 slices in `docs/sprints/sprint-3.md`. Concrete counts the numbers are computed from: **11**
advisories to drive to zero across **2** packages; **3** packages bumped plus **1** added (16 → **17**),
against a tree of **482** tracked files today; **4** hand-edited files in slice 1 and **4** records
that must agree afterwards (`composer.json` ↔ `composer.lock` ↔ `vendor-ai/composer/installed.php` ↔
`LICENSE-THIRD-PARTY.md`); **1** existing drift guard reused unchanged; **1** new compatibility test;
**1** guard point (`App::getChatEngine()`) with **3** callers that already catch; **20** i18n
catalogues for one new key; **1** new CI job.

### AI working hours (itemized)

| Segment (AI does) | Hours (low–high) | Basis |
|---|---|---|
| Planning — kickoff re-validation (2 Explore sweeps + self-verification), advisory re-measurement, resolution probing, 3 user decisions, artifacts (sprint-3, D-052, this estimate) | 2.0–3.5 | Done this session. Cheaper than Sprint 2's planning: no 172-surface enumeration, and the re-validation is dependency metadata rather than a code survey |
| Slice 1 — hand-pin, two `composer update` passes, resolve the 17th package, verify no unintended package moved, `LICENSE-THIRD-PARTY.md`, compatibility test, prove the drift guard fails on a stale record | 3.0–5.5 | The `composer` work itself is minutes; the hours are in **reviewing a ~500-file third-party diff** and proving the four records agree in both directions |
| Slice 2 — typed exception + pure testable policy split + guard, `__()` key × 20 catalogues, unit + integration tests proven both directions, CI job, reference doc + INDEX rows | 2.5–4.5 | Small code, ordinary cost; the 20 catalogues and the docs-at-creation rule dominate |
| Reviews on the finished diff (both subagents, per slice) | 1.5–2.5 | **Not optional overhead.** Nine consecutive slices have had their code changed by the review cycle |
| Sprint close-out — docs-verifier, playground-QA, state, try-it script, continuation prompt | 1.5–2.5 | Phase 5 §5, mandatory |
| **Total AI** | **10.5–18.5 h** | |

Roughly half Sprint 2's 19.5–32.0 h band, and the reason is structural rather than optimistic: this
sprint **writes very little code**. Slice 1 is a dependency operation whose cost is verification, and
slice 2 reuses the typed-exception pattern (`permission-denied-exception.php`), the pure-policy split
(D-044's `buildSecurityHeaders()`), the catalogue-insertion procedure from Sprint 2 slice 4 and the
existing CI workflow. Nothing here is novel except the compatibility test.

### Vibe coder hours (itemized)

| Segment | What the developer does | Hours (low–high) |
|---|---|---|
| Sprint kickoff | Take the 3 decisions, approve the plan (done — 2026-07-25) | 0.25–0.5 |
| During the build | Open sessions, read summaries, approve gates (shorter sprint, fewer gates) | 1.5–3.0 |
| Test points | Spot-check the vendored diff; confirm the playground still loads AI chat | 0.5–1.0 |
| **The one segment only the developer can do** | Run a real AI chat round-trip with their own provider API key — the suite structurally cannot prove this | 0.5–1.0 |
| Sprint close | Follow the numbered try-it script, return the verdict either way | 0.5–1.0 |
| Commits | Repo operations the AI does not perform unattended | 0.25–0.5 |
| **Total developer** | | **3.5–7.0 h → plan for ~7 h with margin** |

### Contingency: +30% (same basis as v1/v2, carried)

Justified less by the code than by the diff: ~500 third-party files enter the repository of a released
product, and the one property that matters most — "does AI chat still work" — has **no automated
proof**. Contingency here buys the round of re-verification that gap implies.

| | Base | With +30% |
|---|---|---|
| AI hours | 10.5–18.5 h | **14–24 h** |
| Developer hours | 3.5–7.0 h | **5–9 h** |

### Estimated calendar delivery

At the stated **5–10 h/week** supervision and 5–9 h of developer time, Sprint 3 lands over roughly
**1–2 calendar weeks**. Estimate, not commitment — calendar time tracks supervision availability, not
AI hours.

### AI cost

**Mode: subscription** — marginal token cost **≈ 0**, recorded as the mode, not billed. Rows keep
accumulating in `docs/token-ledger.md`.

### Assumptions & risks

- **Assumed:** the environment stays as verified — PHP 8.3.x, Composer 2.9.x, phpcs 3.13.x, PHPUnit
  11.5.x, and network access to Packagist (this sprint cannot be done offline).
- **Risk — "does AI chat still work" has no automated proof, and this is the sprint's real exposure.**
  No test in the project exercises Guzzle's HTTP path; the suite proves the vendored code *loads* and
  that its *API surface resolves*. A real provider round-trip needs a key and is handed to the
  developer. D-029 named this at triage; it is costed as a developer segment above, not hidden.
- **Risk — the advisory list can move again mid-sprint**, exactly as it moved 5 → 11 between the
  triage and this plan. Mitigated by re-measuring at the test point rather than trusting the planning
  number (L-015), and closed structurally by the CI job in slice 2.
- **Risk — the third-party diff must still pass the confidential-data scan.** ~500 files is real
  reviewing work, not a formality. Costed into slice 1.
- **Risk — an unintended package moves.** Every package is exact-pinned, so nothing *should* move
  except the four named — verified in the diff rather than assumed. If one does, the four-record
  agreement catches it, but the hours to understand it are not budgeted.
- **Excluded, and costed nowhere here:** raising the product's PHP floor to 8.3 (a support-matrix
  decision, D-027's trigger); the unvalidated `model` parameter finding; NEW-11 authentication;
  NEW-03; the theme-package redesign (D-023, still un-estimated); S-10; the accessibility sprint;
  Phase 6 docs; Phase 7's `.gitattributes` review; Phase 8.
- **Not estimated on purpose:** anything past Sprint 3.

---

## Estimate v4 — Phase 5, Sprint 4 scope (the hook mutation contract + owner recovery, NEW-03 / NEW-36 / NEW-08) — 2026-07-25

Written at the sprint close rather than at planning, because Sprint 4 was chosen from two queued
candidates on the day. Covers **Sprint 4 only**. The theme-package sprint (D-023/D-024) still has no
estimate and gets its own version when it is planned.

### Scope basis

2 slices in `docs/sprints/sprint-4.md`. Concrete counts, all measured rather than carried:
**308** distinct action names and **363** fire sites in the blast-radius assessment, against which the
actual defect was **1** listener; **23** shipped action registrations + **32** filter registrations
reflected once at boot; **4** new public surfaces (2 filters, 1 exception class, 1 CLI command);
**20** i18n catalogues × **5** new keys; **2** new test files carrying **11** tests; **7** documentation
surfaces to reconcile (INDEX, a new reference doc ×2, the in-product guide, 5 skills, README,
playground.md, a flow file).

### AI working hours (itemized)

| Segment (AI does) | Hours (low–high) | Basis |
|---|---|---|
| Planning — kickoff re-validation (2 Explore sweeps, self-verification, **PHP probes of by-reference-variadic semantics**), 3 user decisions, artifacts (sprint-4, this estimate) | 2.0–3.5 | The probes are the unusual item and they decided the design: ten minutes of `php -r` refuted the recorded fix |
| Slice 1 — reflection refusal in both registries, `page.save_data`, x402 conversion, `post_type.updatable_fields` + reserved-key guard, 2 test files proven RED first, 7 doc surfaces | 3.5–6.0 | ~40 lines of production PHP inside a large verification-and-documentation shell |
| Slice 2 — the command, 20 catalogues, 6 tests, reference doc — **plus a full redesign after review** | 3.0–5.5 | The redesign is the honest cost: the first implementation was refuted and rebuilt, with its tests, docs, catalogue strings and decision entry rewritten |
| Reviews on the finished diff (both subagents, per slice) | 2.0–3.0 | **Where the defects were, for the twelfth and thirteenth consecutive slice** — one blocking finding per slice, both correct, both mine |
| Sprint close-out — docs-verifier, playground-QA, state, try-it script, continuation prompt | 1.5–2.5 | Phase 5 §5, mandatory |
| **Total AI** | **12.0–20.5 h** | |

Comparable to Sprint 3's 10.5–18.5 h and for the same structural reason: little code, much
verification. What pushed it higher is the slice-2 redesign — a cost that was *avoidable* only by
having asked, at design time, which function actually grants the access the command claims to restore.
That question is now L-024.

### Vibe coder hours (itemized)

| Segment | What the developer does | Hours (low–high) |
|---|---|---|
| Sprint choice + kickoff | Choose between the NEW-03 slice and the theme-package sprint; take 3 design decisions (done — 2026-07-25) | 0.25–0.5 |
| Mid-sprint decisions | The NEW-33 boundary, and the slice-2 fix shape after the blocking review | 0.25–0.5 |
| During the build | Open sessions, read summaries, approve gates | 1.0–2.0 |
| Test points | Walk the try-it script; confirm a page create is clean and the recovery command behaves | 0.5–1.0 |
| **Total developer** | | **2.0–4.0 h** |

### What this estimate is NOT

It excludes the theme-package sprint (D-023/D-024), the NEW-11 authentication slice, the S-10
CSS-consolidation sprint, the accessibility sprint (A-01…A-07), NEW-33's i18n conversion (deliberately
left open this sprint by user decision), and NEW-34/NEW-35. Each gets its own version when planned.

### Calibration note for the next estimate

Two sprints running, the **review cycle** has produced a blocking finding that changed the shipped
design. Sprint 3's turned CI's 8.2 leg red in simulation; Sprint 4's refuted an entire command. Budget
review-and-rework as a first-class segment, not as a rounding error on "the code is done" — on this
project it has cost between 15% and 30% of the slice it lands on, every time, and has been worth it
every time.

---

## Estimate v5 — Phase 5, Sprint 5 scope (authentication: NEW-11 / NEW-37 / NEW-39 / NEW-09) — 2026-07-25

Written at the sprint close. Covers **Sprint 5 only**. The theme-package sprint (D-023/D-024) still
has no estimate, and the newly-scoped bilingual in-product guides (user instruction, 2026-07-25) get
their own version when planned.

### Scope basis

2 slices in `docs/sprints/sprint-5.md`. Counts measured rather than carried: **2** `Auth::login()`
callers repo-wide; **6** consequences of the delegation fixed in path (lockout keying and relocation,
`security.php` re-auth, `validateAppPassword`, the owner-status and owner-role guards, the 60 s
status re-read); **4** new public surfaces (`Helpers::webauthnRpId()`,
`TwoFactor::sendPasskeyEnrolledEmail()`, the `user.passkey_enrolled` action, and
`docs/reference/authentication.md` itself); **20** catalogues × **2** new keys; **3** new test files
carrying **16** tests; **10** documentation surfaces reconciled; **6** audit findings opened
(NEW-38…NEW-43) and **4** closed.

### AI working hours (itemized)

| Segment (AI does) | Hours (low–high) | Basis |
|---|---|---|
| Planning — kickoff re-validation driven by source and by a **net-zero live probe** of the rotation defect, 2 user decisions, artifacts (sprint-5, this estimate) | 2.0–3.5 | The probe is the item that paid: it turned "three roles cannot log in" into "the login credential and every other credential surface have already diverged", which is what made the fallback design refusable |
| Slice 1 — the delegation plus its six consequences, 13 tests proven RED first, 6 teaching surfaces, a new reference doc | 4.0–6.5 | Small production diff inside a large verification shell; the surfaces that teach the old truth cost as much as the code |
| Slice 2 — endpoint restriction, dispatcher branch, notification + 20 catalogues, the `$preAuthScripts` key change, and a **WebAuthn fixture with real ES256 signatures and hand-encoded CBOR/COSE** | 4.0–6.5 | The fixture is the unusual item: hand-encoding an attestation object is a day's work in the wrong hands and a couple of hours with the parser open beside it |
| Reviews on the finished diff (both subagents, per slice) | 2.5–4.0 | **A blocking finding per slice for the fifteenth and sixteenth consecutive slice.** Slice 2's proved the slice had not closed its finding at all |
| Sprint close-out — docs-verifier, playground-QA, **8 defect fixes**, state, try-it script, continuation prompt | 2.0–3.5 | Higher than previous closes because the QA pass found 6 defects and the docs pass 2, all fixed before closing |
| **Total AI** | **14.5–24.0 h** | |

The highest of the five estimates so far, and the reason is not the code — it is that **authentication
touches every credential surface at once.** Changing one comparison in `Auth::login()` obliged six
adjacent corrections, each with its own failure mode, because four sprints of authorization had been
built on top of a login only one account could pass.

### Vibe coder hours (itemized)

| Segment | What the developer does | Hours (low–high) |
|---|---|---|
| Sprint choice + kickoff | Choose NEW-11 over the theme-package sprint; take the record-only and scope decisions (done — 2026-07-25) | 0.25–0.5 |
| Mid-sprint decisions | Approve the plan; scope the bilingual-guides instruction raised mid-slice | 0.25–0.5 |
| During the build | Open sessions, read summaries, approve gates | 1.0–2.0 |
| Test points | Walk the try-it script: log in as all four roles, rotate a password, lock one account and confirm another still works | 0.5–1.0 |
| **Total developer** | | **2.0–4.0 h** |

### What this estimate is NOT

It excludes the theme-package sprint (D-023/D-024), the **bilingual in-product guides** (16 guides ×
2 languages plus a locale-selection mechanism that does not exist, and NEW-27 must be fixed first or
neither language ships), the S-10 CSS-consolidation sprint, the accessibility sprint (A-01…A-07),
NEW-33's i18n conversion, and NEW-34/NEW-35/NEW-40/NEW-41/NEW-42/NEW-43. Each gets its own version.

### Calibration note for the next estimate

**Three sprints running, the review cycle has produced a blocking finding that changed the shipped
design — and this one changed whether the sprint had achieved anything at all.** Sprint 3's turned
CI's 8.2 leg red in simulation; Sprint 4's refuted an entire command; Sprint 5's proved a feature
declared closed could not work in any browser, because the test harness was sending a header the
product never sends. Budget review-and-rework as a first-class segment: it has cost 15–30% of the
slice it lands on, every time.

The sharper calibration point for whoever estimates next: **every high-value finding in this sprint
came from EXECUTING something** — a net-zero password probe, a 340× timing measurement, a hand-built
HTTP request, a QA agent following the document literally. None came from reading. Budget probe time
explicitly; it is the cheapest hour in the estimate and it has decided the design four times now.

---

## Estimate v6 — Phase 5, Sprint 6 scope (hardening: NEW-40 / NEW-20 / NEW-41 / NEW-42, + NEW-44) — 2026-07-26

Written at the sprint kickoff, before any code. Covers **Sprint 6 only**. The theme-package sprint
(D-023/D-024) and the bilingual in-product guides still have no estimate; each gets its own version
when planned.

### Scope basis

3 slices in `docs/sprints/sprint-6.md`. Counts measured this session, not carried: **2** call sites
of the lockout read-modify-write and **2** of the rate limiter's, closed by **1** promoted primitive;
**1** new IP ceiling reusing **0** changed constants; **1** shared resolver (`resolveUserActor()`)
covering **2** credential types; **4** items in NEW-42; **2** new audit findings opened at the
kickoff (**NEW-44**, **NEW-45**) of which 1 is fixed in path. Sprint-start suite: **248 tests /
1152 assertions**.

### AI working hours (itemized)

| Segment (AI does) | Hours (low–high) | Basis |
|---|---|---|
| Planning — kickoff re-validation against source, which **refuted the audit's own recorded fix shape on two independent grounds** and found 2 defects in no audit entry; 4 user decisions; artifacts (sprint-6, D-059, D-060, 2 audit entries, this estimate) | 2.0–3.0 | The re-validation is the item that paid: implementing the recorded remedy would have serialised every login behind ~218 ms of bcrypt, and the defect it was meant to fix would have got *worse* under `LOCK_NB` |
| Slice 1 — the file-transaction primitive, 4 call sites converted, the IP ceiling, NEW-44 in path, and a **real N-process concurrency test proven to produce lost updates first** | 4.0–6.0 | The concurrency test is the unusual item and the reason this slice is the sprint's largest: it is the measurement NEW-20 has been carried without for five sprints, and it has to be shown failing before its silence means anything |
| Slice 2 — `status` on the shared resolver, the OAuth branch requiring an actor, MCP HTTP tests | 1.5–2.5 | Small diff on a well-understood seam; the cost is in proving 401-on-the-next-request over real HTTP rather than by reading |
| Slice 3 — 4 items in the passkey assertion path, each pinned in both directions | 2.5–4.0 | Item 1's real work is *not* refusing synced passkeys (`signCount = 0`), which is the case that would break most real users |
| Reviews on the finished diff (both subagents, per slice) | 3.0–4.5 | **A correct blocking finding has landed on every slice for sixteen consecutive slices.** Budgeted as a first-class segment, not as a tax |
| Sprint close-out — docs-verifier, playground-QA, defect fixes, state, try-it script, continuation prompt | 2.0–3.5 | The last three closes found 6, 11 and 8 document defects respectively; assume it finds some |
| **Total AI** | **15.0–23.5 h** | |

Comparable to v5 despite a much smaller production diff, and the reason is worth naming: **this
sprint's subject is concurrency, and concurrency cannot be verified by reading.** Every acceptance
criterion here needs a process spawned, a burst issued or a timeout driven.

### Vibe coder hours (itemized)

| Segment | What the developer does | Hours (low–high) |
|---|---|---|
| Sprint choice + kickoff | Choose the hardening slice over the theme-package sprint and the bilingual guides; take the 4 kickoff decisions (done — 2026-07-26) | 0.25–0.5 |
| Mid-sprint decisions | Approve the plan; answer anything the reviews surface | 0.25–0.5 |
| During the build | Open sessions, read summaries, approve gates | 1.0–2.0 |
| Test points | Walk the try-it script: fail a login five times and confirm the account locks while others do not, suspend an account holding an MCP credential and confirm it stops working immediately, log in with a passkey | 0.5–1.0 |
| **Total developer** | | **2.0–4.0 h** |

### What this estimate is NOT

It excludes the theme-package sprint (D-023/D-024), the **bilingual in-product guides** (16 guides ×
2 languages plus a locale-selection mechanism that does not exist, and NEW-27 must be fixed first or
neither language ships), the S-10 CSS-consolidation sprint, the accessibility sprint (A-01…A-07),
NEW-33's i18n conversion, **active OAuth token revocation on suspension** (named out of scope in
slice 2), the **NEW-32 logging slice** that should close NEW-45 with it, and NEW-34/NEW-35/NEW-43.

### Calibration note for the next estimate

**The kickoff re-validation has now changed the plan in four consecutive sprints, and this time it
invalidated a fix shape the project had already written down and approved.** NEW-40's recorded remedy
was not merely suboptimal — under `LOCK_NB` it would have converted a racy lost update into a
deterministic one, in the slice written to close it. Budget the re-validation as a full segment
(2–3 h here) and treat every recorded "fix shape" as a hypothesis with the same status as a reviewer's
finding (L-013, L-014, L-015). The estimate that skips it saves two hours and buys a rewrite.

---

## Estimate v7 — Sprint 6 scope change (NEW-47 + NEW-26 pulled forward; NEW-50 found inside) — 2026-07-27

Written **after** the slice, because the scope change was decided and executed in the same session
(the user's instruction at the slice-2 close). It amends **v6** rather than replacing it: v6's three
slices are unchanged, and this covers the fourth.

### Scope basis

**1** added slice, run before slice 3. Measured rather than estimated, since the work is done: **2**
product files gained a CSRF check (`admin/login.php`, `admin/reset-password.php`), **1** primitive was
fixed (`Auth::validateCsrf()` — audit NEW-50, found by execution), **1** locale key × **20**
catalogues, **1** new test class (**6** tests), **2** existing HTTP test classes updated to send what
the shipped page sends, **1** harness helper added (`formSession()`) and **2** generalized
(`post()`, `request()`). **3** TEMP-BREAK cycles. Suite **268 → 274 tests / 1272 → 1323 assertions**.

### AI working hours (itemized)

| Segment (AI does) | Hours (low–high) | Basis |
|---|---|---|
| Source re-validation before touching anything | 0.25–0.5 | Confirmed NEW-47 against source (the 2FA branch DOES verify CSRF; the password branch does not — a distinction the audit entry got right and that a careless reading would have inverted) |
| The two checks + the form fields + the wording map | 0.5–0.75 | Small, once the shape was decided: the ordering (CSRF before the ceiling) and the single wording map are the only judgement calls |
| **NEW-50 — diagnosis and fix** | 0.5–1.0 | Unplanned and unavoidable: the new check was a no-op until the primitive stopped accepting `hash_equals( '', '' )`. Found only because the token-less requests were still served |
| 20 catalogues | 0.25–0.5 | One key, 20 languages, each meeting the orthography contract |
| Tests: the new class, plus updating the two that drove the login form | 1.0–1.5 | The updates were predicted by slice 1's own source-parity test, which fired on the first run |
| 3 TEMP-BREAK cycles, reverted and `cmp`-verified | 0.25–0.5 | One per mechanism, as the record requires |
| Verification (suite, keel-verify, upgrade, 5 lint scopes) | 0.25–0.5 | |
| Review round + follow-up | 0.5–1.0 | Two subagents on the finished diff |
| Docs and state (D-061, audit ×3, reference, sprint, test points, PROGRESS) | 0.75–1.25 | |
| **Total AI** | **4.25–7.5 h** | |

### Developer (vibe-coder) hours

| Segment | What the developer does | Hours (low–high) |
|---|---|---|
| The scope decision | Answer the question put at the slice-2 close, choosing to pull NEW-47 forward | 0.1–0.25 |
| Verification | Log in, get it wrong once, let a page go stale and see the message | 0.25–0.5 |
| **Total developer** | | **0.35–0.75 h** |

### Calibration note

**The unplanned half of this slice was larger than the planned half, and it was found by running the
tests rather than by reading the diff.** The planned work — two `klytos_verify_csrf()` calls and two
form fields — is perhaps an hour. NEW-50 is what made it real: without it the change was inert, and
nothing in the code review would have said so, because the call *was* there. Budget "the fix does not
work until something underneath it is fixed too" as a real possibility on any slice that adds a check
to a path nobody was checking before; this project has now hit that shape in NEW-16 (slice 7),
NEW-36 (Sprint 4) and here.
