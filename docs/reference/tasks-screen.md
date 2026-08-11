# The Tasks screen (`installer/admin/tasks.php`)

Manifest entry **13**, template **overview-stats**. Built 2026-08-11 (**D-111**).

The work queue: what needs attention, grouped, with the action beside each item.

## What it renders

| Region | Contents |
|---|---|
| Stat row | **Open · In progress · Done (30 days)** — three cards, each a measured fact |
| Filters | Open · In progress · Done · All — **links** carrying `aria-current`, never tabs |
| Body | The task list, grouped by status, each task an `<li>` in a `.k-collection` |
| Empty | §13's good-news state: "Nothing needs your attention." |

## What is NOT built, and why

**Two of §13's four stat cards, because the product cannot supply them.** A Klytos
task carries **no due date**: `TaskManager::create()` writes `id`, `page_slug`,
`css_selector`, `description`, `priority`, `status`, `created_by`, `assigned_to`,
`created_at`, `updated_at` and `completed_at`, and `update()`'s allow-list adds
none. So *Due this week* and *Overdue* would be numbers nobody measured, and
§13's "Overdue is never red alone" delta protects a state that cannot occur.

**The source line** ("raised by System integrity") has no field behind it either:
`created_by` is a **user id**, and nothing records a subsystem as an origin.

Both are **DR-013**, drafted, and `docs/roadmap.md` §0c.

*In progress* is built in their place. It is a shipped status this screen has
always drawn, and building it keeps the row inside `template-overview-stats.md`
§1's floor of three columns (adaptation 87).

## Grouping

By **status**, not by priority. §13 says "grouped" and names no axis; the delta
itself says "task *state* is a word plus a glyph", and the filter chips already
select on status — so grouping on the same axis gives one heading per group
instead of a heading that repeats the chip the reader just clicked. Registered as
a reading in DR-013 rather than kept quietly.

## Extension points

| Hook | Kind | Purpose |
|---|---|---|
| `admin.tasks.before` / `.after` | action | Bracket the whole screen |
| `admin.tasks.before_stats` / `.after_stats` | action | Bracket the stat row |
| `admin.tasks.stats` | filter | Add, remove or reorder the stat cards |
| `admin.tasks.list` | filter | Change the task list after the status filter is applied; receives `$tasks` and `$statusFilter` |
| `admin.tasks.before_action` / `.after_action` | action | Bracket a complete / dismiss / delete; each receives `$action` and `$taskId` |

```php
klytos_add_filter( 'admin.tasks.stats', function ( array $cards ): array {
    $cards[] = [
        'id'    => 'mine',
        'glyph' => 'ks-account_circle',
        'tone'  => 'info',
        'value' => 3,
        'label' => __( 'my_plugin.assigned_to_me' ),
    ];

    return $cards;
} );
```

## Authorization

The page is mapped to `tasks.create` in `core/admin-gate.php`, so an editor sees
their own work queue. **Completing, dismissing and deleting act on any task**,
including other people's, so the POST handler calls
`klytos_require_permission( 'tasks.manage' )` — the tier the matrix already
separates. A refused CSRF token is reported, never swallowed.

## Accessibility

- One `<h1>` from the shell; each group is an `<h2>`.
- Every state is a **word plus a glyph** — colour never carries it alone.
- Each row action **names its row** (`aria-label`), because the shipped screen's
  `title="Complete"` on a "✓" is announced by no screen reader reliably and is
  unreachable by touch.
- Times are stored UTC and rendered in the reader's zone inside `<time datetime>`.

## Evidence

`tests/Integration/TasksHttpTest.php` — 11 tests, 60 assertions, **0 skips** —
with `tests/E2E/fixtures/reset-tasks.php` building its own population through the
real `TaskManager`.

**The browser tier is OWED and is not claimed:** no axe run, no geometry, no
both-themes pass, no 320 px reflow assertion, no capture-and-look (L-048).
