---
# Plan data (Keel references/project-state.md, "Sprint files"). Created 2026-11-12 during the Keel
# v5.13.0 -> v7.6.0 reconciliation (D-122), from docs/PROGRESS.md "Current position" and "Open items".
# Done slices here were BACKFILLED: the work happened before any sprint file covered it, so no
# approved estimate existed. Their original_hours is a backfilled estimate made now, and their
# actual_hours (actual_source: estimated) equals it — no clock existed, so no deviation is claimed.
schema: keel.sprint/2
sprint: 7
goal: "Phase 4 stage 7 (the unblocked screens) to the end, and Keel brought to v7.6.0"
status: in-progress
slices:
  # ---- Keel and environment --------------------------------------------------
  - id: S-701
    title: "Move the browser tier (tests/E2E) onto the keel-site tenant"
    status: not-started
    original_hours: 3.5
    residual_hours: 3.5
    change_kind: added
    parent_slice: null
    owns: []
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-703]
    waiting_on: null
    criteria: []
    reconciliation_status: unreconciled
    notes: "The E2E fixtures run PHP locally against the host's installer/data and read logs there (D-121); they need an adapter that runs them through scripts/keel-site php and keel-site logs on the tenant."
    execution:
      write_paths: [tests/E2E/, playwright.config.js, scripts/keel-site, docs/playground.md]
      contracts:
        reads: []
        writes: []
      exclusive_resources: [klytos-tenant, host-playground]
      parallel: eligible
      serial_reason: null
  - id: S-703
    title: "Keel v7.6.0 reconciliation, part 1 — embedded copies, lock blocks, card lines, the Parallels tenant, docs/machines/ (D-121, D-122; 4a9b1ba)"
    status: done
    original_hours: 6.0
    residual_hours: 0
    change_kind: added
    parent_slice: null
    owns: [KEEL-RECON-7.6.0-PART1]
    estimate_changes: []
    actual_hours: 6.0
    actual_source: estimated
    depends_on: []
    waiting_on: null
    criteria: []
  - id: S-704
    title: "Keel v7.6.0 reconciliation — remaining rows"
    status: in-progress
    original_hours: 12.0
    residual_hours: 9.0
    change_kind: added
    parent_slice: null
    owns: []
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-703]
    waiting_on: null
    criteria: []
    reconciliation_status: unreconciled
    notes: "Clock, sessions ledger and plan (this file), then keel-verify / keel-stop-hook / keel-close / keel-continue / keel-chain-check regeneration, test selection (keel-affected-tests + pre-push), the conformance sweep. Rows of docs/keel-conformance.md are not yet mapped to this slice one by one, hence unreconciled."
    execution:
      write_paths: [scripts/, docs/keel-conformance.md, docs/sessions.md, docs/sprints/, docs/.keel/, .githooks/]
      contracts:
        reads: []
        writes: []
      exclusive_resources: [keel-bookkeeping]
      parallel: serial
      serial_reason: "Coordinator work on the Keel scripts and bookkeeping every other slice reads"
  - id: S-705
    title: "Keel standing check 1 — the agent-teams flag in .claude/settings.local.json"
    status: not-started
    original_hours: 0.25
    residual_hours: 0.25
    change_kind: added
    parent_slice: null
    owns: [KEEL-STANDING-CHECK-1]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: []
    waiting_on: "José to apply the agent-teams flag to .claude/settings.local.json — the auto-mode classifier refuses the edit (as at D-081)"
    criteria: []
    reconciliation_status: reconciled
  # ---- Phase 4 stage 7, slices 1-6 (done, backfilled) ------------------------
  - id: S-706
    title: "Stage 7 slice 1 — entry 44 (Dashboard), first overview-stats consumer (D-110, D-113)"
    status: done
    original_hours: 4.0
    residual_hours: 0
    change_kind: added
    parent_slice: null
    owns: [ENTRY-44]
    estimate_changes: []
    actual_hours: 4.0
    actual_source: estimated
    depends_on: []
    waiting_on: null
    criteria: [ENTRY-44]
  - id: S-707
    title: "Stage 7 slice 2 — entry 13 (Tasks) (D-111, D-113)"
    status: done
    original_hours: 3.0
    residual_hours: 0
    change_kind: added
    parent_slice: null
    owns: [ENTRY-13]
    estimate_changes: []
    actual_hours: 3.0
    actual_source: estimated
    depends_on: []
    waiting_on: null
    criteria: [ENTRY-13]
  - id: S-708
    title: "Stage 7 slice 3 — entry 18 (Agent payments) and the admin's chart pattern (D-112, D-113)"
    status: done
    original_hours: 3.5
    residual_hours: 0
    change_kind: added
    parent_slice: null
    owns: [ENTRY-18]
    estimate_changes: []
    actual_hours: 3.5
    actual_source: estimated
    depends_on: []
    waiting_on: null
    criteria: [ENTRY-18]
  - id: S-709
    title: "Stage 7 slice 4 — entry 7 (Analytics) (D-114)"
    status: done
    original_hours: 3.5
    residual_hours: 0
    change_kind: added
    parent_slice: null
    owns: [ENTRY-7]
    estimate_changes: []
    actual_hours: 3.5
    actual_source: estimated
    depends_on: [S-708]
    waiting_on: null
    criteria: [ENTRY-7]
  - id: S-710
    title: "Stage 7 slice 5 — entry 4 (Assets), first gallery-grid consumer, server and browser tiers (D-118, D-119)"
    status: done
    original_hours: 5.0
    residual_hours: 0
    change_kind: added
    parent_slice: null
    owns: [ENTRY-4]
    estimate_changes: []
    actual_hours: 5.0
    actual_source: estimated
    depends_on: []
    waiting_on: null
    criteria: [ENTRY-4]
  - id: S-711
    title: "Stage 7 slice 6 — entry 21 (Blocks), second gallery-grid consumer (D-120)"
    status: done
    original_hours: 3.0
    residual_hours: 0
    change_kind: added
    parent_slice: null
    owns: [ENTRY-21]
    estimate_changes: []
    actual_hours: 3.0
    actual_source: estimated
    depends_on: [S-710]
    waiting_on: null
    criteria: [ENTRY-21]
  # ---- Unplanned defect work done inside stage 7 (done, backfilled) ----------
  - id: S-712
    title: "The storage-identity defect class — listWithIds() (D-115, L-050)"
    status: done
    original_hours: 5.0
    residual_hours: 0
    change_kind: added
    parent_slice: null
    owns: [D-115]
    estimate_changes: []
    actual_hours: 5.0
    actual_source: estimated
    depends_on: []
    waiting_on: null
    criteria: []
  - id: S-713
    title: "Form data belongs to the Forms plugin (D-116)"
    status: done
    original_hours: 3.0
    residual_hours: 0
    change_kind: added
    parent_slice: null
    owns: [D-116]
    estimate_changes: []
    actual_hours: 3.0
    actual_source: estimated
    depends_on: [S-712]
    waiting_on: null
    criteria: []
  - id: S-714
    title: "The database backend gets a test tier; D-115's VERIFY discharged (D-117)"
    status: done
    original_hours: 2.5
    residual_hours: 0
    change_kind: added
    parent_slice: null
    owns: [D-117]
    estimate_changes: []
    actual_hours: 2.5
    actual_source: estimated
    depends_on: [S-712]
    waiting_on: null
    criteria: []
  # ---- Phase 4 stage 7, slices 7-14 (the eight unblocked entries left) -------
  - id: S-715
    title: "Stage 7 slice 7 — entry 31 (Templates), third gallery-grid consumer"
    status: not-started
    original_hours: 2.5
    residual_hours: 2.5
    change_kind: added
    parent_slice: null
    owns: [ENTRY-31]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-711]
    waiting_on: null
    criteria: [ENTRY-31]
    reconciliation_status: reconciled
    execution:
      write_paths: [installer/admin/templates.php, installer/admin/assets/css/, installer/lang/, tests/, docs/BUILD-SPEC.md]
      contracts:
        reads: []
        writes: []
      exclusive_resources: [lang-catalogues, admin-component-css]
      parallel: eligible
      serial_reason: null
  - id: S-716
    title: "Stage 7 slice 8 — entry 20 (Translations), first editor-split consumer"
    status: not-started
    original_hours: 3.5
    residual_hours: 3.5
    change_kind: added
    parent_slice: null
    owns: [ENTRY-20]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: []
    waiting_on: null
    criteria: [ENTRY-20]
    reconciliation_status: reconciled
    execution:
      write_paths: [installer/admin/translations.php, installer/admin/assets/css/, installer/lang/, tests/, docs/BUILD-SPEC.md]
      contracts:
        reads: []
        writes: []
      exclusive_resources: [lang-catalogues, admin-component-css]
      parallel: eligible
      serial_reason: null
  - id: S-717
    title: "Stage 7 slice 9 — entry 29 (AI images), editor-split"
    status: not-started
    original_hours: 2.5
    residual_hours: 2.5
    change_kind: added
    parent_slice: null
    owns: [ENTRY-29]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-716]
    waiting_on: null
    criteria: [ENTRY-29]
    reconciliation_status: reconciled
    execution:
      write_paths: [installer/admin/ai-images.php, installer/admin/assets/css/, installer/lang/, tests/, docs/BUILD-SPEC.md]
      contracts:
        reads: []
        writes: []
      exclusive_resources: [lang-catalogues, admin-component-css]
      parallel: eligible
      serial_reason: null
  - id: S-718
    title: "Stage 7 slice 10 — entry 40 (Block data), editor-split"
    status: not-started
    original_hours: 2.5
    residual_hours: 2.5
    change_kind: added
    parent_slice: null
    owns: [ENTRY-40]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-716]
    waiting_on: null
    criteria: [ENTRY-40]
    reconciliation_status: reconciled
    execution:
      write_paths: [installer/admin/block-data.php, installer/admin/assets/css/, installer/lang/, tests/, docs/BUILD-SPEC.md]
      contracts:
        reads: []
        writes: []
      exclusive_resources: [lang-catalogues, admin-component-css]
      parallel: eligible
      serial_reason: null
  - id: S-719
    title: "Stage 7 slice 11 — entry 10 (Log in), first auth-centered consumer"
    status: not-started
    original_hours: 3.5
    residual_hours: 3.5
    change_kind: added
    parent_slice: null
    owns: [ENTRY-10]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: []
    waiting_on: null
    criteria: [ENTRY-10]
    reconciliation_status: reconciled
    execution:
      write_paths: [installer/admin/login.php, installer/admin/assets/css/, installer/lang/, tests/, docs/BUILD-SPEC.md]
      contracts:
        reads: []
        writes: []
      exclusive_resources: [lang-catalogues, admin-component-css]
      parallel: eligible
      serial_reason: null
  - id: S-720
    title: "Stage 7 slice 12 — entry 43 (Reset password), auth-centered"
    status: not-started
    original_hours: 2.0
    residual_hours: 2.0
    change_kind: added
    parent_slice: null
    owns: [ENTRY-43]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-719]
    waiting_on: null
    criteria: [ENTRY-43]
    reconciliation_status: reconciled
    execution:
      write_paths: [installer/admin/reset-password.php, installer/admin/assets/css/, installer/lang/, tests/, docs/BUILD-SPEC.md]
      contracts:
        reads: []
        writes: []
      exclusive_resources: [lang-catalogues, admin-component-css]
      parallel: eligible
      serial_reason: null
  - id: S-721
    title: "Stage 7 slice 13 — entry 8 (MCP), record-form"
    status: not-started
    original_hours: 3.0
    residual_hours: 3.0
    change_kind: added
    parent_slice: null
    owns: [ENTRY-8]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: []
    waiting_on: null
    criteria: [ENTRY-8]
    reconciliation_status: reconciled
    execution:
      write_paths: [installer/admin/mcp.php, installer/admin/assets/css/, installer/lang/, tests/, docs/BUILD-SPEC.md]
      contracts:
        reads: []
        writes: []
      exclusive_resources: [lang-catalogues, admin-component-css]
      parallel: eligible
      serial_reason: null
  - id: S-722
    title: "Stage 7 slice 14 — entry 38 (Plugin page pattern), record-form"
    status: not-started
    original_hours: 2.5
    residual_hours: 2.5
    change_kind: added
    parent_slice: null
    owns: [ENTRY-38]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: [S-721]
    waiting_on: null
    criteria: [ENTRY-38]
    reconciliation_status: reconciled
    execution:
      write_paths: [installer/admin/plugin-page.php, installer/admin/assets/css/, installer/lang/, tests/, docs/BUILD-SPEC.md]
      contracts:
        reads: []
        writes: []
      exclusive_resources: [lang-catalogues, admin-component-css]
      parallel: eligible
      serial_reason: null
  # ---- Phase 4, blocked on Design --------------------------------------------
  - id: S-723
    title: "Stage 4 batch B — the seven unbuilt list-table surfaces (entries 5, 15, 30, 33, 34, 35, 36)"
    status: not-started
    original_hours: 17.5
    residual_hours: 17.5
    change_kind: added
    parent_slice: null
    owns: [ENTRY-5, ENTRY-15, ENTRY-30, ENTRY-33, ENTRY-34, ENTRY-35, ENTRY-36]
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: []
    waiting_on: "Design's answer to DR-006 (column widths for the list-table surfaces; sent 2026-07-29, unanswered)"
    criteria: [ENTRY-5, ENTRY-15, ENTRY-30, ENTRY-33, ENTRY-34, ENTRY-35, ENTRY-36]
    reconciliation_status: unreconciled
    notes: "Seven screens at about 2.5 h each. Unreconciled: whether the list halves of the already-built entries 24, 27, 28 and 32 (also named by DR-006) are still owed has not been mapped to this slice."
---

# Sprint 7 — Phase 4 stage 7 to the end, and Keel brought to v7.6.0

- **Created:** 2026-11-12, during the Keel v5.13.0 → v7.6.0 reconciliation (D-122). Since Keel v5.21.0
  every unit of work is a slice; this project had no plan file for the work that followed Sprint 6,
  so this sprint was **derived** from `docs/PROGRESS.md` ("Current position", "Open items") rather
  than planned at a kickoff. It has not been through a kickoff re-validation; the next session that
  works it runs the Phase 5 kickoff steps on the open slices.
- **Why one sprint holds both:** Phase 4 stage 7 is the work in flight (`docs/PROGRESS.md` — "NEXT
  ACTION — stage 7 slice 7"), and the Keel reconciliation is the work in progress now. Sprint 6's
  close (S-605, S-606) is still open in `sprint-6.md`.
- **Backfilled slices.** S-703 and S-706…S-714 are finished work recorded after the fact. Nothing
  timed them, so their `original_hours` and `actual_hours` are the same backfilled estimate
  (`actual_source: estimated`) and they contribute no deviation and no pace sample. Phase 4 stages
  1–6 predate any sprint file and are not backfilled here; the record of them is `docs/history.md`.
- **Ownership.** Manifest entries are written `ENTRY-<n>` and are owned exclusively. S-701 and S-704
  own no mapped requirement yet, and S-723's scope boundary is not mapped, so the sprint is
  **unreconciled** and its remaining hours are an unreconciled baseline.
- **Parallelism.** Every screen slice writes the 20 catalogues in `installer/lang/` and the admin
  component CSS, declared as exclusive resources, so screen slices serialise on them even under
  `Parallel development: auto`.
- **Deferred from this sprint:** S-702 (`docs/sprints/deferred.md`).
- Acceptance: Phase 4's definition of done for stage 7's eight remaining entries (BUILD-SPEC §5.4
  rows ticked, browser tier driven); `Keel baseline:` reads v7.6.0 with every conformance row and
  standing check passing.
- Close-out: [filled at close]
