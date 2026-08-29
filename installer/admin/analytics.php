<?php

/**
 * Klytos Admin — Analytics (manifest entry 7, template `overview-stats`)
 *
 * H1 **Analytics**, entry point `analytics.php`. Gated centrally at
 * `analytics.view` by `klytos_enforce_admin_gate()` (admin-gate.php:88), so no
 * inline gate is added here — the map entry is the floor.
 *
 * THE SECOND CONSUMER of `template-overview-stats.md` §4's chart pattern, built
 * once on entry 18 (D-112). The pattern is reused verbatim — `role="img"` with
 * the headline in its `aria-label`, a real `<table>` with the same numbers
 * following it IN THE DOM inside a `<details>`, and the chart replaced by that
 * table below 900px. **§7 asks for a LINE where §18 asked for bars, and only
 * the mark differs**; a second pattern would be a defect, not a choice.
 *
 * TWO OF §7's FIVE STATS ARE BUILT AS NAMED, AND THREE HAVE NO SOURCE AT ALL —
 * the fourth slice running where the per-screen survey's disagreement is with
 * the PRODUCT rather than with the delivery (DR-015):
 *
 *   - *Avg. time* — a pageview records ONE instant (`timestamp`). There is no
 *     session, no exit event and no second instant. A duration is a distance
 *     between two points and the product holds one. Entry 18's *Settlement lag*
 *     exactly (D-112).
 *   - *Bounce* — a bounce is a SESSION with one pageview, and there is no
 *     session concept anywhere: `visitor_hash` groups by visitor-DAY, not by
 *     visit, and nothing records a visit boundary.
 *   - *Agent hits* — `recordPageView()` stores no user agent, deliberately and
 *     as a privacy guarantee this file's own header makes. The only agent
 *     record in the product is the x402 TRANSACTION log, which counts agents
 *     that PAID — that is entry 18's *Paid requests* under another name, and an
 *     unpaid agent hit is recorded nowhere.
 *
 * Built instead: *Views* and *Visitors* as §7 names them, plus *Avg. views/day*
 * and *Pages tracked* — both measured, both already shipped on this screen, and
 * dropping shipped measured product is not a fidelity decision (D-076, D-079).
 * Four cards is also what keeps the row inside §1's 3–5 floor, which two would
 * break (adaptation 97).
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\AnalyticsManager;

$pageTitle = __( 'analytics.title' );

$analytics = new AnalyticsManager( $app->getStorage() );

/*
 * §7's period chips are 7d · 30d · 90d. **24h is kept as a fourth**: it is
 * shipped behaviour on a released product and removing a filter a person may be
 * using is not a fidelity decision (D-076's rule, D-079's precedent). Logged as
 * adaptation 98.
 *
 * An unknown `?period=` resolves to the default rather than failing — the same
 * call entry 13 made, and the same reason: a bookmark from an older version is
 * not an error condition.
 */
$periods = [
    '24h' => 1,
    '7d'  => 7,
    '30d' => 30,
    '90d' => 90,
];

$period = klytos_sanitize_key( (string) ( $_GET['period'] ?? '' ) );
if ( ! isset( $periods[ $period ] ) ) {
    $period = '30d';
}

$days     = $periods[ $period ];
$dateTo   = klytos_gmdate( 'Y-m-d' );
$dateFrom = klytos_gmdate( 'Y-m-d', time() - ( $days - 1 ) * 86400 );

$summary = $analytics->getSummary( $dateFrom, $dateTo );

$totalViews     = (int) ( $summary['total_views'] ?? 0 );
$uniqueVisitors = (int) ( $summary['unique_visitors'] ?? 0 );
$topPages       = $summary['top_pages'] ?? [];
$topReferrers   = $summary['top_referrers'] ?? [];
$devices        = $summary['devices'] ?? [];

// The dense series the chart needs. `daily_views` carries only the days that
// HAVE entries, and a chart drawn from that misrepresents the shape of the
// traffic without anything looking wrong (`AnalyticsDenseSeriesTest`).
$series = AnalyticsManager::denseDailyViews( $summary['daily_views'] ?? [], $dateFrom, $dateTo );

$hasTraffic = $totalViews > 0;

// A mean over a real number of days is measured; over no traffic at all it is
// the ABSENCE of an answer, and `template-overview-stats.md` §2 keeps `—` and
// `0` apart on purpose. Entry 18's *Avg. price* is the same call (D-112).
$avgPerDay = $hasTraffic ? round( $totalViews / max( 1, $days ), 1 ) : null;

$adminPath = $adminPath ?? '';

$analyticsUrl = static function ( string $periodValue ) use ( $adminPath ): string {
    return $adminPath . 'analytics.php?period=' . rawurlencode( $periodValue );
};

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
klytos_do_action( 'admin.analytics.before' );

/*
 * Defined AFTER the shell: `$spriteUrl` and `klytos_admin_icon()` are created by
 * `templates/sidebar.php`, and a closure binds its `use` variables at DEFINITION
 * time — D-110's defect, which turned a whole screen into a 500.
 */

/** The peak day, for the chart's accessible headline. */
$peak = ['date' => '', 'views' => 0];
foreach ( $series as $point ) {
    if ( $point['views'] > $peak['views'] ) {
        $peak = $point;
    }
}
?>

<?php klytos_do_action( 'admin.analytics.before_stats' ); ?>
<div class="k-stat-row" data-testid="analytics.stats">
    <?php
    $statCards = [
        [
            'id'    => 'views',
            'glyph' => 'ks-visibility',
            'tone'  => 'info',
            'value' => number_format( $totalViews ),
            'label' => __( 'analytics.stat_views' ),
            'note'  => '',
        ],
        [
            'id'    => 'visitors',
            'glyph' => 'ks-group',
            'tone'  => 'info',
            'value' => number_format( $uniqueVisitors ),
            'label' => __( 'analytics.stat_visitors' ),
            /*
             * THE NOTE IS NOT DECORATION. The visitor hash is SHA-256 of the IP
             * plus a salt that ROTATES DAILY — by design, so a visitor cannot be
             * followed across days. The consequence for this card is that over a
             * multi-day range the figure counts distinct visitor-DAYS: one person
             * visiting on ten days counts ten times. §7 names the card
             * "Visitors", so the label stays as the delivery writes it and the
             * supporting line states what the number actually measures, rather
             * than the card making a claim the data does not support. Raised in
             * DR-015.
             */
            'note'  => $days > 1 ? __( 'analytics.visitors_note' ) : '',
        ],
        [
            'id'    => 'avg',
            'glyph' => 'ks-monitoring',
            'tone'  => 'info',
            'value' => $avgPerDay === null ? '—' : number_format( $avgPerDay, 1 ),
            'label' => __( 'analytics.stat_avg_per_day' ),
            'note'  => '',
        ],
        [
            'id'    => 'pages',
            'glyph' => 'ks-description',
            'tone'  => 'info',
            'value' => number_format( count( $topPages ) ),
            'label' => __( 'analytics.stat_pages_tracked' ),
            'note'  => '',
        ],
    ];

    foreach ( klytos_apply_filters( 'admin.analytics.stats', $statCards ) as $card ) :
        $valId = 'analytics-stat-' . $card['id'] . '-value';
        $labId = 'analytics-stat-' . $card['id'] . '-label';
        $noteId = 'analytics-stat-' . $card['id'] . '-note';
        $note   = (string) ( $card['note'] ?? '' );
        ?>
        <div class="k-stat"
             aria-labelledby="<?php echo klytos_esc_attr( trim( $valId . ' ' . $labId . ( $note !== '' ? ' ' . $noteId : '' ) ) ); ?>"
             data-testid="analytics.stat.<?php echo klytos_esc_attr( (string) $card['id'] ); ?>">
            <span class="k-stat-tile k-stat-tile--<?php echo klytos_esc_attr( (string) $card['tone'] ); ?>" aria-hidden="true">
                <?php klytos_admin_icon( $spriteUrl, (string) $card['glyph'], '' ); ?>
            </span>
            <p class="k-stat-value k-num" id="<?php echo klytos_esc_attr( $valId ); ?>"
               data-testid="analytics.stat_value.<?php echo klytos_esc_attr( (string) $card['id'] ); ?>">
                <?php echo klytos_esc_html( (string) $card['value'] ); ?>
            </p>
            <p class="k-stat-label" id="<?php echo klytos_esc_attr( $labId ); ?>">
                <?php echo klytos_esc_html( (string) $card['label'] ); ?>
            </p>
            <?php if ( $note !== '' ) : ?>
                <p class="k-stat-delta" id="<?php echo klytos_esc_attr( $noteId ); ?>">
                    <?php echo klytos_esc_html( $note ); ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php klytos_do_action( 'admin.analytics.after_stats' ); ?>

<?php // §7's period chips: links carrying aria-current, in a <nav aria-label>
      // — template-overview-stats.md §4, and never tabs and never buttons. ?>
<nav class="k-filters" aria-label="<?php echo klytos_esc_attr( __( 'analytics.period_label' ) ); ?>"
     data-testid="analytics.periods">
    <?php foreach ( $periods as $periodValue => $periodDays ) : ?>
        <a class="k-chip" href="<?php echo klytos_esc_url( $analyticsUrl( (string) $periodValue ) ); ?>"
           <?php echo $period === $periodValue ? 'aria-current="true"' : ''; ?>
           data-testid="analytics.chip.<?php echo klytos_esc_attr( (string) $periodValue ); ?>">
            <?php echo klytos_esc_html( __( 'analytics.period_' . $periodValue ) ); ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php
// ─── The primary panel: the chart, and its table ─────────────────

$headline = __( 'analytics.chart_headline', [
    'days'  => (string) $days,
    'total' => number_format( $totalViews ),
    'peak'  => number_format( $peak['views'] ),
    'date'  => $peak['date'],
] );
?>
<section class="k-card k-card--padded" aria-labelledby="analytics-chart-heading" data-testid="analytics.chart_card">
    <div class="k-card-body">
        <h2 class="k-card-heading" id="analytics-chart-heading">
            <?php echo klytos_esc_html( __( 'analytics.chart_title' ) ); ?>
        </h2>

        <?php if ( ! $hasTraffic ) : ?>
            <?php // §7's empty state, quoted: "No traffic yet. Analytics starts
                  // counting once the site is public. — Open Settings". ?>
            <p class="k-empty" data-testid="analytics.empty">
                <?php klytos_admin_icon( $spriteUrl, 'ks-monitoring', 'k-empty-icon' ); ?>
                <span class="k-empty-text"><?php echo klytos_esc_html( __( 'analytics.empty_sentence' ) ); ?></span>
                <a href="<?php echo klytos_esc_url( $adminPath . 'settings.php' ); ?>"
                   data-testid="analytics.empty_action">
                    <?php echo klytos_esc_html( __( 'analytics.open_settings' ) ); ?>
                </a>
            </p>
        <?php else : ?>
            <?php
            /*
             * THE CHART — `template-overview-stats.md` §4's pattern, and the
             * ONLY accessible chart pattern this admin has. Built once on entry
             * 18 (D-112) and consumed here: same `.k-chart` container, same
             * `role="img"` with the headline in its `aria-label`, same
             * `<details>` table following it in the DOM, same replacement below
             * 900px. **Only the MARK differs** — §7 says a line where §18 says
             * bars — so this adds one `<polyline>` class to the layer and
             * nothing else.
             *
             * The `<svg>` carries width and height ATTRIBUTES as well as its CSS
             * size: an `<svg>` with neither renders at the SVG default of
             * 300 × 150 (L-048, adaptation 93).
             */
            $chartW    = 720;
            $chartH    = 240;
            $padLeft   = 8;
            $padBottom = 18;
            $plotH     = $chartH - $padBottom;
            $count     = max( 1, count( $series ) );
            $stepX     = $count > 1 ? ( $chartW - $padLeft * 2 ) / ( $count - 1 ) : 0.0;
            $maxVal    = max( 1, $peak['views'] );

            $points = [];
            foreach ( $series as $i => $point ) {
                $x        = $padLeft + $i * $stepX;
                $y        = $plotH - ( $plotH * ( $point['views'] / $maxVal ) );
                $points[] = round( $x, 2 ) . ',' . round( $y, 2 );
            }
            ?>
            <div class="k-chart" data-testid="analytics.chart">
                <svg class="k-chart-svg"
                     width="<?php echo (int) $chartW; ?>" height="<?php echo (int) $chartH; ?>"
                     viewBox="0 0 <?php echo (int) $chartW; ?> <?php echo (int) $chartH; ?>"
                     preserveAspectRatio="none"
                     role="img"
                     aria-label="<?php echo klytos_esc_attr( $headline ); ?>">
                    <?php // Gridlines in --separador, per §1. Four, plus the baseline. ?>
                    <?php for ( $g = 1; $g <= 4; $g++ ) : ?>
                        <?php $gy = $plotH * ( $g / 5 ); ?>
                        <line class="k-chart-grid" x1="0" x2="<?php echo (int) $chartW; ?>"
                              y1="<?php echo round( $gy, 2 ); ?>" y2="<?php echo round( $gy, 2 ); ?>"></line>
                    <?php endfor; ?>

                    <polyline class="k-chart-line" points="<?php echo klytos_esc_attr( implode( ' ', $points ) ); ?>"></polyline>

                    <line class="k-chart-axis" x1="0" x2="<?php echo (int) $chartW; ?>"
                          y1="<?php echo (int) $plotH; ?>" y2="<?php echo (int) $plotH; ?>"></line>
                </svg>
            </div>

            <?php
            /*
             * OPEN at every width — adaptation 92, taken on entry 18 and binding
             * on every consumer of this template. §4 opens it below 900px and the
             * SERVER HAS NO VIEWPORT; a script that opened it would make the
             * accessible path depend on JavaScript, which is the one thing §4
             * exists to prevent.
             */
            ?>
            <details class="k-chart-details" open data-testid="analytics.chart_details">
                <summary><?php echo klytos_esc_html( __( 'analytics.view_as_table' ) ); ?></summary>

                <table class="k-table k-analytics-table" data-testid="analytics.chart_table">
                    <caption class="k-table-caption"><?php echo klytos_esc_html( $headline ); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo klytos_esc_html( __( 'analytics.col_date' ) ); ?></th>
                            <th scope="col" class="k-num"><?php echo klytos_esc_html( __( 'analytics.col_views' ) ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $series as $point ) : ?>
                            <tr>
                                <th scope="row"><?php echo klytos_esc_html( $point['date'] ); ?></th>
                                <td class="k-num"><?php echo (int) $point['views']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        <?php endif; ?>
    </div>
</section>

<?php
// ─── §7's two detail cards: Top pages · Referrers ────────────────

$detailCards = [
    [
        'id'    => 'pages',
        'title' => __( 'analytics.top_pages' ),
        'head'  => __( 'analytics.col_page' ),
        'empty' => __( 'analytics.no_pages' ),
        'rows'  => $topPages,
    ],
    [
        'id'    => 'referrers',
        'title' => __( 'analytics.referrers' ),
        'head'  => __( 'analytics.col_referrer' ),
        'empty' => __( 'analytics.no_referrers' ),
        'rows'  => $topReferrers,
    ],
];
?>
<div class="k-widget-grid" data-testid="analytics.detail_cards">
    <?php foreach ( klytos_apply_filters( 'admin.analytics.detail_cards', $detailCards ) as $card ) : ?>
        <section class="k-card k-card--padded"
                 aria-labelledby="analytics-<?php echo klytos_esc_attr( (string) $card['id'] ); ?>-heading"
                 data-testid="analytics.detail.<?php echo klytos_esc_attr( (string) $card['id'] ); ?>">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="analytics-<?php echo klytos_esc_attr( (string) $card['id'] ); ?>-heading">
                    <?php echo klytos_esc_html( (string) $card['title'] ); ?>
                </h2>

                <?php if ( empty( $card['rows'] ) ) : ?>
                    <p class="k-empty">
                        <span class="k-empty-text"><?php echo klytos_esc_html( (string) $card['empty'] ); ?></span>
                    </p>
                <?php else : ?>
                    <table class="k-table k-analytics-table">
                        <caption class="k-table-caption"><?php echo klytos_esc_html( (string) $card['title'] ); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo klytos_esc_html( (string) $card['head'] ); ?></th>
                                <th scope="col" class="k-num"><?php echo klytos_esc_html( __( 'analytics.col_views' ) ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( array_slice( (array) $card['rows'], 0, 10, true ) as $name => $views ) : ?>
                                <tr>
                                    <th scope="row"><?php echo klytos_esc_html( (string) $name ); ?></th>
                                    <td class="k-num"><?php echo (int) $views; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<?php
/*
 * The DEVICES card is shipped product that §7's card list does not name. Kept
 * and logged, never silently dropped — the same call as entry 18's provider card
 * (adaptation 94) and entry 13's *In progress* chip. Adaptation 99.
 *
 * The percentages are drawn as a real table rather than the three emoji the
 * pre-redesign screen used: an emoji is the label of a device class carried by a
 * picture alone, which `accessibility.md` forbids for state and is no better for
 * a category.
 */
?>
<?php if ( ! empty( $devices ) ) : ?>
    <section class="k-card k-card--padded" aria-labelledby="analytics-devices-heading" data-testid="analytics.devices">
        <div class="k-card-body">
            <h2 class="k-card-heading" id="analytics-devices-heading">
                <?php echo klytos_esc_html( __( 'analytics.devices' ) ); ?>
            </h2>

            <table class="k-table k-analytics-table">
                <caption class="k-table-caption"><?php echo klytos_esc_html( __( 'analytics.devices' ) ); ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?php echo klytos_esc_html( __( 'analytics.col_device' ) ); ?></th>
                        <th scope="col" class="k-num"><?php echo klytos_esc_html( __( 'analytics.col_share' ) ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $devices as $category => $info ) : ?>
                        <tr>
                            <th scope="row">
                                <?php
                                // The class names are a closed set the manager
                                // itself produces, so each has its own key rather
                                // than a concatenated label (i18n rule).
                                $deviceKey = 'analytics.device_' . klytos_sanitize_key( (string) $category );
                                echo klytos_esc_html( __( $deviceKey ) );
                                ?>
                            </th>
                            <td class="k-num">
                                <?php
                                echo klytos_esc_html( __( 'analytics.share_value', [
                                    'percent' => (string) ( $info['percentage'] ?? 0 ),
                                    'count'   => number_format( (int) ( $info['count'] ?? 0 ) ),
                                ] ) );
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<style nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
/*
 * `grid-template-columns` is PER SCREEN (template-list-table.md §1) and §7
 * records none for any of these tables — DR-006's gap, on the surface right
 * after the one D-113 found it on. Same derivation and the same promise: the
 * tracks are content-driven, they invent no measurement, and they are replaced
 * verbatim the moment DR-006 answers. Adaptation 100.
 *
 * Two tracks here, not three: every table on this screen is label + one number.
 */
.k-analytics-table tr:not(.k-table-row-full) {
    grid-template-columns: minmax(0, 1fr) max-content;
}
</style>

<?php klytos_do_action( 'admin.analytics.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
