---
paths:
  - installer/core/**/*.php
  - installer/admin/**/*.php
  - installer/plugins/**/*.php
---

# Docs discipline — Klytos CMS

Source of truth: docs/03-technical-plan.md §3 Conventions. On any conflict, the plan wins — fix this file.

## Before you write any new function, method or class
- Grep `docs/api/INDEX.md` FIRST. Reuse what is already there, or generalize it to cover the new case.
- A near-duplicate of a documented surface is a defect, not a shortcut. If you are about to add a second way to do a documented thing, stop and reuse instead.

## In the same slice as the code — never "later"
- Every new public surface gets its doc in `docs/api/` or `docs/reference/` AND its row in `docs/api/INDEX.md`, with a runnable example.
- Update `docs/PROGRESS.md` and `docs/decisions.md` at the moment of the change, not at the end of the session.

## Extensibility — this project IS extensible
- User-facing strings pass through a filter.
- Every decision the code makes gets a before action and an after action.
- Queries and responses are filterable.

Anything undefined in the recorded docs → ask. Design gaps → Design Request (Keel Phase 4). Recorded decisions are never re-opened on your initiative.
