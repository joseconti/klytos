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
