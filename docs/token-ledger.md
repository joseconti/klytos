# Token Ledger — Klytos CMS

> Actual token/AI usage, one row per working session. Measured where the environment exposes usage,
> honestly estimated where it does not. Appending the session's row is part of ending every session.
> Reconciled against `docs/estimate.md` at release (Phase 7).

Payment mode: **subscription** (flat fee) — marginal token cost ≈ 0. Rows are recorded anyway,
because they are what calibrates future estimates and because the payment mode can change.

| # | Date | Session / scope | Model(s) | Output tokens | Notes |
|---|------|-----------------|----------|---------------|-------|
| 1 | 2026-07-18 | Keel adoption: maintenance update (v1.11.0 → v3.3.0, both embed trees), portability lock refresh + `AGENTS.md`, read-only inventory (3 parallel subagents), state files, `01`/`02`/`03` as-built, `04-adoption-audit.md`, `api/INDEX.md`, `issues.md`, competitive scan | Opus 4.8 (orchestrator) + subagents | ~estimated, not measured | Environment did not expose a per-session usage figure; `/cost` not available in this run. Estimate to be refined at the next session that can measure. |

| 2 | 2026-07-18 | Phase 5 Sprint 1 planning: Keel maintenance check, state re-read, kickoff re-validation against source (3 parallel Explore subagents — authorization surface, SSRF/comments/headers, playground & test tooling), plan mode + approval, then `sprints/sprint-1.md`, `estimate.md` v1, `05-test-points.md`, D-020/021/022, audit corrections (S-04/S-07/S-12 + NEW-01/NEW-02), PROGRESS update | Opus 4.8 (orchestrator) + 3 Explore subagents | ~estimated, not measured | Environment exposed no per-session usage counter. Dominated by the three subagent sweeps, which read broadly across `installer/admin/`, `installer/core/` and the plugins. No code written this session. |

Running total: 2 sessions — both estimated, neither measured. Refine at the first session that can measure.

## Reconciliation (filled at release)

- Total tokens by model: —
- Cost at verified prices: —
- Deviation vs `docs/estimate.md`: —
