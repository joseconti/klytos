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
