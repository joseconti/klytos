---
# The ONE backlog file (Keel references/project-state.md, "The sprint plan"): everything wanted and
# not in this version. Same item schema as a sprint slice plus target (proposed version, or null) and
# reason. Ids share one namespace with the sprint slices: promoting an item is a MOVE that keeps its
# id. Hours here are what the item would cost later and are excluded from every plan total.
schema: keel.deferred/1
items:
  - id: S-702
    title: "SafeHttpTest hex-loopback asserts a macOS-only refusal reason; on Linux it is host_does_not_resolve (still refused)"
    status: not-started
    original_hours: 0.5
    residual_hours: 0.5
    change_kind: added
    parent_slice: null
    owns: []
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: []
    waiting_on: null
    criteria: []
    reconciliation_status: unreconciled
    target: null
    reason: "Found running the suite on the Linux tenant (D-121). The address is refused on both platforms; only the asserted reason differs, so it is a test-portability defect, not a security one."
  - id: S-901
    title: "Phase 8 — the klytos.io website"
    status: not-started
    original_hours: 12.0
    residual_hours: 12.0
    change_kind: added
    parent_slice: null
    owns: []
    estimate_changes: []
    actual_hours: null
    actual_source: null
    depends_on: []
    waiting_on: null
    criteria: []
    reconciliation_status: unreconciled
    estimate_basis: coarse
    target: null
    reason: "Website intent is yes, but Phase 8 is deferred until the user requests it (D-012)."
---

# Deferred — Klytos CMS

The backlog of work wanted and not scheduled in a sprint. The data is the frontmatter above; this
body is for people.

- **S-702** — the SafeHttp test's hex-loopback case asserts the refusal *reason* macOS's resolver
  produces; on Linux the same address is refused as `host_does_not_resolve`. It is refused on both,
  so nothing is exposed; the test needs to accept either reason or assert the refusal alone.
  Recorded in `docs/lessons-learned.md`.
- **S-901** — Phase 8, the site (D-012). Coarse placeholder hours.
