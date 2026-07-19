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

| 3 | 2026-07-18/19 | Theme model redesign (design only, no code): Keel maintenance check, state re-read, 2 parallel Explore subagents (theme code path; skills & docs surface) + direct surgical reads of `theme-manager.php`, `build-engine.php`, `part-manager.php`, `part-tools.php`. Produced `theme-package-model.md`, D-023, D-024, L-004, audit D-06 + new section F (F-01), PROGRESS scope-change line. Then two commits: the spec, and the previously-untracked Keel v3.3.0 embed + assistant config package (106 files) with its confidential-data gate | Opus 4.8 (orchestrator) + 2 Explore subagents | ~estimated, not measured | Environment exposed no per-session usage counter. Ran concurrently with another session on the same repo — cost two numbering collisions (D-020, L-003) and a cross-commit; see the warning in the continuation prompt. No product code written. |

| 4 | 2026-07-19 | Phase 5 Sprint 1 slice 1 — test harness (T-01/T-04) + dev manifest (D-022): Keel maintenance check, state re-read, playground freshness boot, surgical source reads of `app.php`/`auth.php`/`file-storage.php`/`hooks.php` to find the bootstrap seams, then built `composer.json`, `phpunit.xml`, `tests/` (2 base cases + 2 harness tests), verified the tier boundary by removing the fixture, propagated the new testing rule to 7 assistant containers + the core skill, wrote D-027 and the slice's docs/test-point evidence. One `code-reviewer` subagent on the slice diff | Opus 4.8 (orchestrator) + 1 code-reviewer subagent | ~estimated, not measured | Environment exposed no per-session usage counter. Cheaper than sessions 2 and 3: no broad Explore sweeps — the recorded state named the files, so reads were targeted. First session of this project to produce running code with a green suite. |

| 5 | 2026-07-19 | Phase 5 Sprint 1 slice 2 — `vendor-ai/` manifest + CVE audit (H-04, D-022): Keel maintenance check (throttle held, no remote lookup), state re-read, playground freshness boot, then derived the dependency graph from `vendor-ai/composer/installed.json` rather than guessing it, wrote `installer/composer.json`, resolved `installer/composer.lock`, ran the project's first `composer audit`, assessed CVE reachability with targeted greps over `installer/core/ai/`, wrote the drift-guard test and proved it fails on injected drift, corrected `LICENSE-THIRD-PARTY.md`, and recorded D-028 + audit H-04 closure + NEW-05/06/07 | Opus 4.8 (orchestrator) + 1 code-reviewer subagent | ~estimated, not measured | Environment exposed no per-session usage counter. Cheapest code-producing session so far: the vendored tree carried its own Composer metadata, so the "reconstruction" was derivation, not archaeology — no Explore sweeps at all. |

Running total: 5 sessions — all estimated, none measured. Refine at the first session that can measure.

## Reconciliation (filled at release)

- Total tokens by model: —
- Cost at verified prices: —
- Deviation vs `docs/estimate.md`: —
