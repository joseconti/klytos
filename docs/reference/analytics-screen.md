# Analytics screen (manifest entry 7)

`installer/admin/analytics.php` · template `overview-stats` · H1 **Analytics** ·
gated centrally at `analytics.view` (`installer/core/admin-gate.php:88`).

Built in Phase 4 stage 7, slice 4 (**D-114**). The **second consumer** of
`template-overview-stats.md` §4's chart pattern, which was built once on entry 18
(D-112): `role="img"` with the headline in its `aria-label`, a real `<table>`
with the same numbers following it **in the DOM** inside a `<details>`, and the
chart replaced by that table below 900px. §7 asks for a **line** where §18 asks
for bars — the mark is the only thing that differs.

## What the screen measures, and what it cannot

§7 names five stat cards. **Two are built as named and three have no source in
the product at all** (**DR-015**):

| §7 stat | State | Why |
|---|---|---|
| Views | built | `getSummary()['total_views']` |
| Visitors | built, with a caveat on the card | `unique_visitors`, and the salt rotates DAILY — see below |
| Avg. time | **no source** | a pageview records one instant (`timestamp`); there is no session, no exit event and no second instant |
| Bounce | **no source** | a bounce is a session with one pageview, and there is no session concept anywhere |
| Agent hits | **no source** | `recordPageView()` stores no user agent, deliberately; the only agent record is the x402 TRANSACTION log, which counts agents that PAID |

Built instead, so the row stays inside the template's 3–5 floor and no figure is
invented: **Avg. views/day** and **Pages tracked**, both measured and both
already shipped on this screen (adaptation 97).

### The *Visitors* caveat is not decoration

`hashVisitorIdentity()` is SHA-256 of the IP plus a salt that **rotates daily**,
by design, so a visitor cannot be followed across days. The consequence for a
multi-day range is that the figure counts distinct **visitor-days**: one person
visiting on ten days counts ten times. §7 names the card "Visitors", so the label
stays as the delivery writes it and a supporting line states what the number
actually measures. Over a one-day range the caveat does not apply and is not
shown.

## Public surfaces this screen adds

### `AnalyticsManager::denseDailyViews()`

```php
public static function denseDailyViews(
    array $dailyViews,   // sparse map, 'Y-m-d' => count
    string $dateFrom,    // first day of the range, inclusive
    string $dateTo       // last day of the range, inclusive
): array                 // list<array{date: string, views: int}>, ascending
```

`getSummary()`'s `daily_views` carries **only the days that have entries**. A
chart drawn from that is not a chart with a gap — it draws the 2nd next to the
4th at ordinary spacing and misrepresents the shape of the traffic without
anything looking wrong. This fills the range.

Static and free of storage on purpose: it is a pure function of its inputs, so it
is unit-tested directly (`tests/Unit/AnalyticsDenseSeriesTest.php`, written and
seen failing first under the card's `Test-first policy: pure-logic`) and it adds
no second full-collection read to a screen whose single read is already the
expensive part.

An **inverted range answers `[]`** rather than swapping the bounds: `?period=` is
user input here, and a silently corrected range draws a chart nobody asked for
where an empty one draws the template's empty state.

```php
use Klytos\Core\AnalyticsManager;

$series = AnalyticsManager::denseDailyViews(
    ['2026-08-02' => 7],
    '2026-08-01',
    '2026-08-03'
);
// [ ['date' => '2026-08-01', 'views' => 0],
//   ['date' => '2026-08-02', 'views' => 7],
//   ['date' => '2026-08-03', 'views' => 0] ]
```

### Hooks

| Hook | Type | Payload |
|---|---|---|
| `admin.analytics.before` | action | none — echo extra HTML at the top of the screen |
| `admin.analytics.before_stats` | action | none — echo extra HTML above the stat row |
| `admin.analytics.stats` | filter | the four stat-card definitions, each `id`, `glyph`, `tone`, `value`, `label`, `note` |
| `admin.analytics.after_stats` | action | none — echo extra HTML below the stat row |
| `admin.analytics.detail_cards` | filter | the two detail-card definitions, each `id`, `title`, `head`, `empty`, `rows` |
| `admin.analytics.after` | action | none — echo extra HTML at the tail of the screen |

```php
// Add a fifth stat card from a plugin, without forking the screen.
klytos_add_filter( 'admin.analytics.stats', function ( array $cards ): array {
    $cards[] = [
        'id'    => 'signups',
        'glyph' => 'ks-group',
        'tone'  => 'exito',
        'value' => (string) my_plugin_signup_count(),
        'label' => __( 'my-plugin.signups' ),
        'note'  => '',
    ];

    return $cards;
} );
```

## Period chips

7d · 30d · 90d as §7 names them, **plus 24h**, which is shipped behaviour on a
released product — removing a filter someone may be using is not a fidelity
decision (adaptation 98). They are links in a `<nav aria-label>` carrying
`aria-current`, never tabs and never buttons. An unknown `?period=` resolves to
the 30-day default rather than failing: a bookmark from an older version is not
an error condition.

## Deviations logged

| # | What | Where |
|---|---|---|
| 97 | Four stat cards, two of them not in §7's list | `docs/BUILD-SPEC.md` §5.9 |
| 98 | The 24h period chip is kept | same |
| 99 | The Devices card is kept, and drawn as a table rather than three emoji | same |
| 100 | `grid-template-columns` derived, not read (DR-006 is unanswered) | same |

## Verified by

- `tests/Unit/AnalyticsDenseSeriesTest.php` — 7 tests, red observed first.
- `tests/Integration/AnalyticsHttpTest.php` — 12 tests, 98 assertions, zero
  skips, over the deterministic population in
  `tests/E2E/fixtures/reset-analytics.php`.
- `tests/E2E/overview-stats.spec.js` — the screen joins the template's shared
  browser tier: stat-tile geometry, whole-page axe in both themes, the 320 px
  reflow, the line mark, the 30-vertex series, the table's real columns, the
  below-900 replacement, and four full-page captures.
