# The Agent payments screen (`installer/admin/x402-dashboard.php`)

Manifest entry **18**, template **overview-stats**. H1 **Agent payments** — one
of the five nav labels `SPEC/navigation.md` allows to differ from its heading.
Built 2026-08-11 (**D-112**).

**This screen owns the admin's only chart pattern.** Entry 7 (Analytics) consumes
the same one; a second pattern is a defect, not a choice.

## What it renders

| Region | Contents |
|---|---|
| Banner | An absent payment provider, `role="status"`, with **Review pricing** |
| Stat row | **Revenue (30 days) · Paid requests · Unique agents · Avg. price** |
| Primary panel | The 30-day revenue chart **and its data table** |
| Detail cards | Top paid pages · Agents by spend, both real captioned tables |
| Provider | The active provider and its network (adaptation 94) |

## The chart pattern — `template-overview-stats.md` §4

Three rules, and none of them is decoration:

1. The `<svg>` is **`role="img"`** with an `aria-label` carrying the **headline** —
   the total and the peak with its date — so a screen reader gets the answer
   without walking thirty bars.
2. A real `<table>` with the **same numbers** follows it **in the DOM**, inside a
   `<details>` whose summary is *View as table*.
3. Below 900 px the chart is **replaced** by that table, not shrunk: a 320 px-wide
   chart is decoration, a table is information.

Two build notes:

- **The `<details>` ships `open` at every width** (adaptation 92). §4 opens it
  below 900 px and the server has no viewport. A script that opened it would make
  the *accessible* path depend on JavaScript, which is the one thing §4 exists to
  prevent; overriding the user agent's own closed-`<details>` rule from a
  stylesheet is not a behaviour a page should be asked to guarantee.
- **The `<svg>` carries `width` and `height` attributes** as well as its CSS size.
  An `<svg>` with neither renders at the SVG default of 300 × 150 (L-048), and the
  attributes are what survive a stylesheet that does not load.

## What is NOT built, and why

**§18's fifth stat, *Settlement lag*.** `TransactionLog::log()` writes exactly one
instant per transaction — `created_at`. `facilitator_ok` is a **boolean** and
`tx_hash` a frequently-empty string; neither carries a time. A lag is the distance
between two instants and the product holds one. **DR-014**, drafted, and
`docs/roadmap.md` §0c.

## Data sources

| Figure | Source |
|---|---|
| Revenue (30 days) | `X402\Stats::getSummary()['month']['total_usd']` |
| Paid requests | `…['month']['transaction_count']` |
| Unique agents | `count( X402\Stats::getTopBots( … ) )` — the same grouping the *Agents by spend* card uses |
| Avg. price | Revenue ÷ Paid requests; **`—`, never `0`, over zero requests** |
| Chart and table | `X402\Stats::getDailyRevenue( 30 )` |
| Top paid pages | `X402\Stats::getTopPages( 10 )` |

All money is numeric, right-aligned, **with the currency as text** (§18's delta).

## Extension points

| Hook | Kind | Purpose |
|---|---|---|
| `admin.x402_dashboard.before` / `.after` | action | Bracket the whole screen |
| `admin.x402_dashboard.before_stats` / `.after_stats` | action | Bracket the stat row |
| `admin.x402_dashboard.stats` | filter | Add, remove or reorder the stat cards |
| `admin.x402_dashboard.detail_cards` | filter | Change the detail cards and their rows |

```php
klytos_add_filter( 'admin.x402_dashboard.detail_cards', function ( array $cards ): array {
    $cards[] = [
        'id'    => 'networks',
        'title' => __( 'my_plugin.by_network' ),
        'head'  => __( 'my_plugin.network' ),
        'rows'  => [ ['name' => 'base', 'count' => 12, 'total' => 0.12] ],
    ];

    return $cards;
} );
```

## Authorization

Mapped to `analytics.view` in `core/admin-gate.php`. The screen performs no write.

## Evidence

`tests/Integration/X402DashboardHttpTest.php` — 11 tests, 132 assertions,
**0 skips** — with `tests/E2E/fixtures/reset-x402.php` seeding a **deterministic**
30-day series, because a random one makes the headline unassertable and the
headline is the whole accessible answer.

**The browser tier is OWED and is not claimed:** no axe run, no geometry, no
both-themes pass, no 320 px reflow assertion, no capture-and-look (L-048).
