---
# Plan data (Keel references/project-state.md, "Sprint files"). Created 2026-11-12 during the Keel
# v5.13.0 -> v7.6.0 reconciliation (D-122) from docs/roadmap.md (D-062) and docs/PROGRESS.md "Open
# items". COARSE: every estimate here is an order-of-magnitude placeholder (estimate_basis: coarse)
# made without a kickoff re-validation, and every slice is unreconciled. The sprint is re-sliced and
# re-estimated (append-only estimate_changes) at its kickoff.
schema: keel.sprint/2
sprint: 8
goal: "The roadmap to v1 after Phase 4 — coarse backlog, to be re-sliced at kickoff"
status: not-started
slices:
  - id: S-801
    title: "Roadmap 1 — the .gitattributes review (NEW-27, NEW-28, H-02)"
    status: not-started
    original_hours: 1.5
    residual_hours: 1.5
    change_kind: added
    parent_slice: null
    owns: [NEW-27, NEW-28, H-02]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-715, S-716, S-717, S-718, S-719, S-720, S-721, S-722, S-723]
    waiting_on: null
    criteria: [NEW-27, NEW-28, H-02]
    reconciliation_status: unreconciled
    estimate_basis: coarse
  - id: S-802
    title: "Roadmap 0b — entry 17 Setup wizard: install.php from three steps to seven, non-destructive in a checkout (NEW-04)"
    status: not-started
    original_hours: 6.0
    residual_hours: 6.0
    change_kind: added
    parent_slice: null
    owns: [ENTRY-17]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-715, S-716, S-717, S-718, S-719, S-720, S-721, S-722, S-723]
    waiting_on: null
    criteria: [ENTRY-17]
    reconciliation_status: unreconciled
    estimate_basis: coarse
  - id: S-803
    title: "Roadmap 0b — entry 11 Verify, paired with NEW-38 (OAuth consent screen 2FA)"
    status: not-started
    original_hours: 4.0
    residual_hours: 4.0
    change_kind: added
    parent_slice: null
    owns: [ENTRY-11, NEW-38]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-715, S-716, S-717, S-718, S-719, S-720, S-721, S-722, S-723]
    waiting_on: null
    criteria: [ENTRY-11, NEW-38]
    reconciliation_status: unreconciled
    estimate_basis: coarse
  - id: S-804
    title: "Roadmap 0b — entry 14 Comments: a new admin screen over comment-manager"
    status: not-started
    original_hours: 5.0
    residual_hours: 5.0
    change_kind: added
    parent_slice: null
    owns: [ENTRY-14]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-715, S-716, S-717, S-718, S-719, S-720, S-721, S-722, S-723]
    waiting_on: null
    criteria: [ENTRY-14]
    reconciliation_status: unreconciled
    estimate_basis: coarse
  - id: S-805
    title: "Roadmap 0b — entry 22 Health: the data source first, then the screen"
    status: not-started
    original_hours: 5.0
    residual_hours: 5.0
    change_kind: added
    parent_slice: null
    owns: [ENTRY-22]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-715, S-716, S-717, S-718, S-719, S-720, S-721, S-722, S-723]
    waiting_on: null
    criteria: [ENTRY-22]
    reconciliation_status: unreconciled
    estimate_basis: coarse
  - id: S-806
    title: "Roadmap 0b — entry 42 Template preview: preview endpoint, DOM check runner, default-template store (D-104)"
    status: not-started
    original_hours: 8.0
    residual_hours: 8.0
    change_kind: added
    parent_slice: null
    owns: [ENTRY-42]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-715, S-716, S-717, S-718, S-719, S-720, S-721, S-722, S-723]
    waiting_on: null
    criteria: [ENTRY-42]
    reconciliation_status: unreconciled
    estimate_basis: coarse
  - id: S-807
    title: "Roadmap 0c — the stage-5 cards Phase 4 does not build (D-088)"
    status: not-started
    original_hours: 20.0
    residual_hours: 20.0
    change_kind: added
    parent_slice: null
    owns: []
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-715, S-716, S-717, S-718, S-719, S-720, S-721, S-722, S-723]
    waiting_on: null
    criteria: []
    reconciliation_status: unreconciled
    estimate_basis: coarse
  - id: S-808
    title: "Roadmap 2 — finish the hardening: NEW-17, NEW-32, NEW-35, NEW-13, NEW-34 and the LOW tail"
    status: not-started
    original_hours: 12.0
    residual_hours: 12.0
    change_kind: added
    parent_slice: null
    owns: [NEW-17, NEW-32, NEW-35, NEW-13, NEW-34]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-801]
    waiting_on: null
    criteria: [NEW-17, NEW-32, NEW-35, NEW-13, NEW-34]
    reconciliation_status: unreconciled
    estimate_basis: coarse
  - id: S-809
    title: "Roadmap 3 — accessibility (A-01…A-07, D-007)"
    status: not-started
    original_hours: 20.0
    residual_hours: 20.0
    change_kind: added
    parent_slice: null
    owns: [A-01, A-02, A-03, A-04, A-05, A-06, A-07]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-808]
    waiting_on: null
    criteria: [A-01, A-02, A-03, A-04, A-05, A-06, A-07]
    reconciliation_status: unreconciled
    estimate_basis: coarse
  - id: S-810
    title: "Roadmap 4 — the theme package (D-023, D-024, F-01, D-06, NEW-04, NEW-23, the comment form)"
    status: not-started
    original_hours: 24.0
    residual_hours: 24.0
    change_kind: added
    parent_slice: null
    owns: [D-023, D-024, F-01, D-06, NEW-23]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-809]
    waiting_on: null
    criteria: [D-023, D-024, F-01, D-06, NEW-23]
    reconciliation_status: unreconciled
    estimate_basis: coarse
  - id: S-811
    title: "Roadmap 5 — Phase 6 documentation, incl. the bilingual in-product guides (D-01…D-05)"
    status: not-started
    original_hours: 16.0
    residual_hours: 16.0
    change_kind: added
    parent_slice: null
    owns: [D-01, D-02, D-03, D-04, D-05]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-810]
    waiting_on: null
    criteria: [D-01, D-02, D-03, D-04, D-05]
    reconciliation_status: unreconciled
    estimate_basis: coarse
  - id: S-812
    title: "Roadmap 6 — Phase 7 release gate (H-01, H-03, H-05, H-07, T-03, T-04, D-038, D-027)"
    status: not-started
    original_hours: 8.0
    residual_hours: 8.0
    change_kind: added
    parent_slice: null
    owns: [H-01, H-03, H-05, H-07, T-03, T-04]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-802, S-803, S-804, S-805, S-806, S-807, S-811]
    waiting_on: null
    criteria: [H-01, H-03, H-05, H-07, T-03, T-04]
    reconciliation_status: unreconciled
    estimate_basis: coarse
---

# Sprint 8 — the roadmap to v1 after Phase 4 (coarse backlog)

- **Created:** 2026-11-12, during the Keel v5.13.0 → v7.6.0 reconciliation (D-122), so that "what is
  left" counts the whole recorded route to v1 instead of only the work in flight. It orders nothing
  new: the order is `docs/roadmap.md` (D-062), and every slice points at an item already recorded
  there or in `docs/PROGRESS.md` "Open items".
- **Every figure here is coarse.** No kickoff re-validation has run on any of these items, and this
  project's own calibration notes (`docs/estimate.md` v4–v7) record that the re-validation has
  changed the plan at every kickoff so far. The hours exist so the total is not silently missing
  this work; they are an unreconciled baseline, not a commitment. At this sprint's kickoff each slice
  is split, its ownership mapped, and its residual re-estimated with an `estimate_changes` entry.
- **Why everything waits on sprint 7.** `docs/PROGRESS.md` records that Phase 5 does not resume until
  Phase 4's definition of done is met, so the first slices depend on stage 7's open slices and on the
  DR-006 batch (S-723), which itself waits on Design.
- **Not here:** Phase 8 (the site) is deferred by D-012 and lives in `docs/sprints/deferred.md` (S-901).
- Acceptance: [set at kickoff]
- Close-out: [filled at close]
