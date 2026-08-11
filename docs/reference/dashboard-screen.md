# The Dashboard (`installer/admin/index.php`)

Manifest entry **44**, template **overview-stats**. The admin's landing screen
and the target of the shell's brand link. Built 2026-08-11 (**D-110**), the
first consumer of the `overview-stats` template.

Its job, in `SPEC/manifest.md` §44's own sentence: *what state is this install
in, and what should I do next.* It is deliberately **not** a fifth set of
numbers — Analytics owns traffic, Tasks owns work, Health owns checks, x402 owns
money. Every figure here links to the screen that owns the detail.

## What it renders

| Region | Contents |
|---|---|
| Banner | The indexing warning, `role="status"`, only while indexing is blocked |
| Stat row | Five cards: Last build · Pages · MCP · Failing checks · Pending updates |
| Primary panel | *Set up the site* — three ordered steps — until all three are done |
| Detail cards | The widget grid, once setup is complete |

The panel and the grid are **mutually exclusive**: §44 replaces the grid with the
panel on a new site, and the panel disappears the moment step 3 is done.

## The three rules this screen exists to keep

1. **It never blocks on the network.** The *Pending updates* figure comes from
   `Updater::getCachedUpdateState()`, which reads the six-hour cache and never
   fetches. `checkForUpdate()` is the right call on `updates.php`, whose job is
   to check; here it would mean waiting on GitHub after every login.
2. **It fabricates no zero.** `0` is an answer and `—` is the absence of one.
   Last build with no build renders `—`; Pending updates renders `—` whenever no
   usable cache exists, because a zero there is a claim nobody made.
3. **It changes no site-wide setting in passing.** The indexing banner carries no
   toggle — DR-002, confirmed in D-072 answer 1: indexing is a Settings control.
   The single write on the screen is *Build now*.

## Data sources

| Figure | Source |
|---|---|
| Last build | `SiteConfig::get()['last_build']` |
| Pages | `PageManager::count( 'all' )` |
| MCP | `count( Auth::listBearerTokens() )` |
| Failing checks | `SiteHealthManager::runAll()['summary']` — `warning` + `critical` |
| Pending updates | `Updater::getCachedUpdateState()` |

The setup steps read `PageManager::count()`, the existence of the `theme` record
(`StorageInterface::exists( 'theme', 'theme' )` — the ThemeManager returns
defaults for a site that has never saved one, so its VALUES cannot answer the
question) and `last_build`.

## `Updater::getCachedUpdateState()`

```php
$state = klytos_app()->getUpdater()->getCachedUpdateState();

match ( $state['state'] ) {
    'pending' => printf( '1 update: %s', $state['update']['new_version'] ),
    'current' => print( '0 pending updates' ),
    'unknown' => print( '— not checked recently' ),
};
```

Returns `['state' => 'pending'|'current'|'unknown', 'update' => array|null]`.
Never performs I/O beyond reading the encrypted cache file. `update` is present
only when `state` is `pending`.

Three states rather than two, because `getCachedRelease()` answers `null` both
when the cache is empty and when the install is up to date — opposite facts on a
stat card.

## Extension points

| Hook | Kind | Purpose |
|---|---|---|
| `admin.dashboard.before` / `.after` | action | Bracket the whole screen |
| `admin.dashboard.before_stats` / `.after_stats` | action | Bracket the stat row |
| `admin.dashboard.stats` | filter | Add, remove or reorder stat cards |
| `admin.dashboard.init` | action | Register widgets before the grid renders |
| `admin.dashboard.before_widgets` / `.after_widgets` | action | Bracket the grid |
| `admin.dashboard.widgets` | filter | Add, remove or reorder widgets |
| `admin.dashboard.quick_actions` | filter | Change the Quick actions links |
| `admin.dashboard.before_build` / `.after_build` | action | Bracket *Build now* |
| `notice.condition.indexing_blocked` | filter | Decide whether the banner shows |

A stat card is `['id', 'glyph', 'tone', 'value'|'value_html', 'label', 'detail',
'href']`; an empty `href` renders a `<div>` rather than an anchor with no
destination.

## Accessibility

- One `<h1>`, emitted by the shell from `$pageTitle`. Every card is an `<h2>`.
- A linked stat card is ONE `<a>` wrapping the whole card, with
  `aria-labelledby` binding the value to the label so it reads "4 — failing
  checks". The icon tile is `aria-hidden="true"`.
- The **Failing checks** card is a `<div>`, not a link: entry 22 (Health) is
  deferred and `health.php` does not exist. An anchor with no `href` is not a
  link and is not focusable — worse than either alternative.
- The setup panel is an `<ol>`; each step names its state in **words** (`Done` /
  `Next` / `Later`) before its action, so colour never carries the state alone.
- A `Later` step's action is rendered `disabled` with the reason in its
  accessible name, never hidden.
- Nothing on the screen requires JavaScript.

## What is deferred, and why

| Item | Reason |
|---|---|
| The *Next steps* panel on a working site | §44 names it and no delivered file says what it contains — **DR-012**, drafted |
| The **Choose widgets** link | Its destination is entry 27's *Preferences* card, deferred to four of five (D-100). Nothing in the product can hide a widget today, so a link to a control that does not exist would be the gap, not the fix |
| The **Failing checks** destination | Entry 22 (Health) is deferred (D-072) |

## Evidence

`tests/Integration/DashboardHttpTest.php` — 10 tests over the server-rendered
contract. `tests/Unit/UpdaterCachedStateTest.php` — 7 tests over the three
states.

**The browser tier is OWED and is not claimed:** no axe run, no geometry, no
both-themes pass, no 320 px reflow assertion and no capture-and-look (L-048).
Until that exists this screen's §5.4 row cannot carry a `Driven` tick.
