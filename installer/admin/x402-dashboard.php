<?php

/**
 * Klytos Admin — Agent payments (manifest entry 18, template `overview-stats`)
 *
 * H1 **Agent payments** — one of the five nav labels `SPEC/navigation.md` allows
 * to differ from its `<h1>`. Entry point stays `x402-dashboard.php`: a filename
 * is a URL on a released product (adaptation 2).
 *
 * This is the FIRST screen in the build to need `template-overview-stats.md`
 * §4's chart pattern, and there is exactly one of it: the chart is `role="img"`
 * with an `aria-label` carrying the headline, a real `<table>` with the same
 * numbers follows it **in the DOM** inside a `<details>` whose summary is "View
 * as table", and below 900px the `<details>` is open and the chart is hidden.
 * Analytics (entry 7) consumes the same pattern; do not write a second one.
 *
 * Four of §18's five stats are built. **Settlement lag is not**, and the reason
 * is the product: a transaction records ONE timestamp, `created_at`, and
 * `facilitator_ok` is a bool with no time beside it — nothing anywhere records
 * when settlement happened, so a lag cannot be computed from a single point.
 * Deferred in `docs/roadmap.md` §0c and asked as **DR-014**.
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

$pageTitle = __( 'klytos-x402.title' );

$config   = klytos_x402_config();
$stats    = klytos_x402_stats();
$registry = klytos_x402_providers();

$summary      = $stats->getSummary();
$topPages     = $stats->getTopPages( 10 );
$dailyRevenue = $stats->getDailyRevenue( 30 );

/*
 * *Unique agents* is a COUNT of the distinct agents in the same 30-day window
 * the other figures use. `getTopBots()` already groups by agent and slices to
 * its limit, so a limit above any plausible population gives the full set and
 * the count is a measured fact rather than a second traversal of the log with
 * its own subtly different grouping rule (L-004's shape).
 */
$allBots      = $stats->getTopBots( 10000 );
$topBots      = array_slice( $allBots, 0, 10 );
$uniqueAgents = count( $allBots );

$month       = $summary['month'] ?? [];
$revenue30d  = (float) ( $month['total_usd'] ?? 0 );
$paidCount   = (int) ( $month['transaction_count'] ?? 0 );

// A mean of measured values is measured; a mean over zero requests is not a
// zero, it is the absence of an answer, and §2 is explicit that `—` and `0`
// are different claims.
$avgPrice    = $paidCount > 0 ? $revenue30d / $paidCount : null;

$activeProviderId = $config->get( 'provider_id', '' );
$activeProvider   = $registry->has( $activeProviderId ) ? $registry->get( $activeProviderId ) : null;

$hasPayments = $paidCount > 0;

$adminPath = $adminPath ?? '';

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
klytos_do_action( 'admin.x402_dashboard.before' );

/*
 * Defined AFTER the shell: `$spriteUrl` and `klytos_admin_icon()` are created
 * by `templates/sidebar.php`, and a closure binds its `use` variables at
 * DEFINITION time (D-110's defect).
 */

/** All money is `--type-numeric`, right-aligned, with the currency as TEXT (§18's delta). */
$money = static function ( float $amount ): string {
    return 'USD ' . number_format( $amount, 4, '.', '' );
};
?>

<?php if ( $registry->isEmpty() ) : ?>
    <?php // A degraded SOURCE, handled per template §Error: the affected region
          // states it and the page does not turn into an error. ?>
    <p class="k-banner k-banner--aviso" role="status" data-testid="x402.no_provider">
        <?php klytos_admin_icon( $spriteUrl, 'ks-warning', 'k-banner-icon' ); ?>
        <span><?php echo klytos_esc_html( __( 'klytos-x402.no_provider' ) ); ?></span>
        <a href="<?php echo klytos_esc_url( $adminPath . 'x402-settings.php' ); ?>"
           data-testid="x402.no_provider_action">
            <?php echo klytos_esc_html( __( 'klytos-x402.review_pricing' ) ); ?>
        </a>
    </p>
<?php endif; ?>

<?php klytos_do_action( 'admin.x402_dashboard.before_stats' ); ?>
<div class="k-stat-row" data-testid="x402.stats">
    <?php
    /*
     * FOUR cards, not §18's five. *Settlement lag* needs a settlement time and
     * the transaction record holds exactly one timestamp — `created_at` — with
     * `facilitator_ok` a bare bool beside it. A lag between one point and
     * nothing is not a number (DR-014, roadmap §0c). Four is inside
     * `template-overview-stats.md` §1's 3–5.
     */
    $statCards = [
        [
            'id'    => 'revenue',
            'glyph' => 'ks-toll',
            'tone'  => $hasPayments ? 'exito' : 'offline',
            'value' => $hasPayments ? $money( $revenue30d ) : '—',
            'label' => __( 'klytos-x402.stat_revenue_30d' ),
        ],
        [
            'id'    => 'requests',
            'glyph' => 'ks-receipt_long',
            'tone'  => 'info',
            'value' => (string) $paidCount,
            'label' => __( 'klytos-x402.stat_paid_requests' ),
        ],
        [
            'id'    => 'agents',
            'glyph' => 'ks-smart_toy',
            'tone'  => 'info',
            'value' => (string) $uniqueAgents,
            'label' => __( 'klytos-x402.stat_unique_agents' ),
        ],
        [
            'id'    => 'avg',
            'glyph' => 'ks-sell',
            'tone'  => 'info',
            // `—`, never `0`: a mean over no requests is the absence of an
            // answer, and §2 keeps the two apart on purpose.
            'value' => $avgPrice === null ? '—' : $money( $avgPrice ),
            'label' => __( 'klytos-x402.stat_avg_price' ),
        ],
    ];

    foreach ( klytos_apply_filters( 'admin.x402_dashboard.stats', $statCards ) as $card ) :
        $valId = 'x402-stat-' . $card['id'] . '-value';
        $labId = 'x402-stat-' . $card['id'] . '-label';
        ?>
        <div class="k-stat"
             aria-labelledby="<?php echo klytos_esc_attr( $valId . ' ' . $labId ); ?>"
             data-testid="x402.stat.<?php echo klytos_esc_attr( (string) $card['id'] ); ?>">
            <span class="k-stat-tile k-stat-tile--<?php echo klytos_esc_attr( (string) $card['tone'] ); ?>" aria-hidden="true">
                <?php klytos_admin_icon( $spriteUrl, (string) $card['glyph'], '' ); ?>
            </span>
            <p class="k-stat-value k-num" id="<?php echo klytos_esc_attr( $valId ); ?>"
               data-testid="x402.stat_value.<?php echo klytos_esc_attr( (string) $card['id'] ); ?>">
                <?php echo klytos_esc_html( (string) $card['value'] ); ?>
            </p>
            <p class="k-stat-label" id="<?php echo klytos_esc_attr( $labId ); ?>">
                <?php echo klytos_esc_html( (string) $card['label'] ); ?>
            </p>
        </div>
    <?php endforeach; ?>
</div>
<?php klytos_do_action( 'admin.x402_dashboard.after_stats' ); ?>

<?php
// ─── The primary panel: the chart, and its table ─────────────────

$chartTotal = 0.0;
$chartPeak  = ['date' => '', 'total' => 0.0];
foreach ( $dailyRevenue as $day ) {
    $dayTotal    = (float) ( $day['total_usd'] ?? 0 );
    $chartTotal += $dayTotal;
    if ( $dayTotal > $chartPeak['total'] ) {
        $chartPeak = ['date' => (string) ( $day['date'] ?? '' ), 'total' => $dayTotal];
    }
}
?>
<section class="k-card k-card--padded" aria-labelledby="x402-chart-heading" data-testid="x402.chart_card">
    <div class="k-card-body">
        <h2 class="k-card-heading" id="x402-chart-heading">
            <?php echo klytos_esc_html( __( 'klytos-x402.daily_revenue' ) ); ?>
        </h2>

        <?php if ( ! $hasPayments ) : ?>
            <?php
            /*
             * §18's empty state, quoted by the template itself:
             * "No agent payments yet. x402 is enabled and no agent has paid for
             * a page. — Review pricing". A good empty state reads like an
             * answer, and it carries the action.
             */
            ?>
            <p class="k-empty" data-testid="x402.empty">
                <?php klytos_admin_icon( $spriteUrl, 'ks-toll', 'k-empty-icon' ); ?>
                <span class="k-empty-text"><?php echo klytos_esc_html( __( 'klytos-x402.empty_sentence' ) ); ?></span>
                <a href="<?php echo klytos_esc_url( $adminPath . 'x402-settings.php' ); ?>"
                   data-testid="x402.empty_action">
                    <?php echo klytos_esc_html( __( 'klytos-x402.review_pricing' ) ); ?>
                </a>
            </p>
        <?php else : ?>
            <?php
            /*
             * THE CHART, and it is the only accessible chart pattern this admin
             * has (`template-overview-stats.md` §4). Three rules, all of them
             * load-bearing:
             *
             *  1. the chart is `role="img"` with an `aria-label` giving the
             *     HEADLINE — the total and the peak — so a screen reader gets
             *     the answer without walking 30 bars;
             *  2. a real `<table>` with the SAME numbers follows it in the DOM,
             *     inside a `<details>` whose summary is "View as table";
             *  3. below 900px the `<details>` is open and the chart is hidden —
             *     a 320px-wide chart is decoration, a table is information.
             *
             * The bars are plain `<rect>`s in a `viewBox`, sized by CSS on the
             * container. The `<svg>` carries width and height attributes as
             * well: an `<svg>` with neither renders at the SVG default of
             * 300 x 150 (L-048, and its ninth occurrence shipped one slice ago).
             */
            $chartW   = 720;
            $chartH   = 240;
            $padLeft  = 8;
            $padBottom = 18;
            $barCount = max( 1, count( $dailyRevenue ) );
            $slot     = ( $chartW - $padLeft * 2 ) / $barCount;
            $barW     = max( 2.0, $slot * 0.7 );
            $maxVal   = max( 0.0001, $chartPeak['total'] );

            $headline = __( 'klytos-x402.chart_headline', [
                'total' => $money( $chartTotal ),
                'peak'  => $money( $chartPeak['total'] ),
                'date'  => $chartPeak['date'],
            ] );
            ?>
            <div class="k-chart" data-testid="x402.chart">
                <svg class="k-chart-svg"
                     width="<?php echo (int) $chartW; ?>" height="<?php echo (int) $chartH; ?>"
                     viewBox="0 0 <?php echo (int) $chartW; ?> <?php echo (int) $chartH; ?>"
                     preserveAspectRatio="none"
                     role="img"
                     aria-label="<?php echo klytos_esc_attr( $headline ); ?>">
                    <?php // Gridlines in --separador, per §1. Four, plus the baseline. ?>
                    <?php for ( $g = 1; $g <= 4; $g++ ) : ?>
                        <?php $gy = ( $chartH - $padBottom ) * ( $g / 5 ); ?>
                        <line class="k-chart-grid" x1="0" x2="<?php echo (int) $chartW; ?>"
                              y1="<?php echo round( $gy, 2 ); ?>" y2="<?php echo round( $gy, 2 ); ?>"></line>
                    <?php endfor; ?>

                    <?php foreach ( $dailyRevenue as $i => $day ) : ?>
                        <?php
                        $v = (float) ( $day['total_usd'] ?? 0 );
                        $h = $v > 0 ? max( 1.0, ( $chartH - $padBottom ) * ( $v / $maxVal ) ) : 0.0;
                        $x = $padLeft + $i * $slot + ( $slot - $barW ) / 2;
                        $y = ( $chartH - $padBottom ) - $h;
                        ?>
                        <?php if ( $h > 0 ) : ?>
                            <rect class="k-chart-bar"
                                  x="<?php echo round( $x, 2 ); ?>" y="<?php echo round( $y, 2 ); ?>"
                                  width="<?php echo round( $barW, 2 ); ?>" height="<?php echo round( $h, 2 ); ?>"></rect>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <line class="k-chart-axis" x1="0" x2="<?php echo (int) $chartW; ?>"
                          y1="<?php echo (int) ( $chartH - $padBottom ); ?>"
                          y2="<?php echo (int) ( $chartH - $padBottom ); ?>"></line>
                </svg>
            </div>

            <?php // The chart's table equivalent — mandatory, and the same numbers. ?>
            <?php
            /*
             * OPEN at every width. §4 says the <details> is open below 900px,
             * and the SERVER HAS NO VIEWPORT — adaptation 12's reasoning, on a
             * second control. The alternatives are both worse: a script that
             * opens it makes the ACCESSIBLE path depend on JavaScript, which is
             * the one thing §4 exists to prevent, and a CSS override of the UA's
             * own closed-details rule is a behaviour no stylesheet should be
             * asked to guarantee. Open is also strictly the safer direction: the
             * numbers are present for everyone, and a reader who wants them out
             * of the way can close it.
             */
            ?>
            <details class="k-chart-details" open data-testid="x402.chart_details">
                <summary><?php echo klytos_esc_html( __( 'klytos-x402.view_as_table' ) ); ?></summary>

                <table class="k-table" data-testid="x402.chart_table">
                    <caption class="k-table-caption"><?php echo klytos_esc_html( $headline ); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo klytos_esc_html( __( 'klytos-x402.chart_date' ) ); ?></th>
                            <th scope="col" class="k-num"><?php echo klytos_esc_html( __( 'klytos-x402.chart_revenue' ) ); ?></th>
                            <th scope="col" class="k-num"><?php echo klytos_esc_html( __( 'klytos-x402.chart_requests' ) ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $dailyRevenue as $day ) : ?>
                            <tr>
                                <th scope="row">
                                    <time datetime="<?php echo klytos_esc_attr( (string) ( $day['date'] ?? '' ) ); ?>">
                                        <?php echo klytos_esc_html( (string) ( $day['date'] ?? '' ) ); ?>
                                    </time>
                                </th>
                                <td class="k-num"><?php echo klytos_esc_html( $money( (float) ( $day['total_usd'] ?? 0 ) ) ); ?></td>
                                <td class="k-num"><?php echo (int) ( $day['count'] ?? 0 ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        <?php endif; ?>
    </div>
</section>

<?php
// ─── Detail cards: Top paid pages · Agents by spend ──────────────

$detailCards = [
    [
        'id'    => 'pages',
        'title' => __( 'klytos-x402.top_pages' ),
        'head'  => __( 'klytos-x402.tx_page' ),
        'rows'  => array_map(
            static fn( array $r ): array => [
                'name'  => (string) ( $r['slug'] ?? '' ),
                'count' => (int) ( $r['count'] ?? 0 ),
                'total' => (float) ( $r['total_usd'] ?? 0 ),
            ],
            $topPages
        ),
    ],
    [
        'id'    => 'agents',
        'title' => __( 'klytos-x402.top_bots' ),
        'head'  => __( 'klytos-x402.tx_bot' ),
        'rows'  => array_map(
            static fn( array $r ): array => [
                'name'  => (string) ( $r['bot'] ?? '' ),
                'count' => (int) ( $r['count'] ?? 0 ),
                'total' => (float) ( $r['total_usd'] ?? 0 ),
            ],
            $topBots
        ),
    ],
];
?>
<div class="k-widget-grid" data-testid="x402.detail_cards">
    <?php foreach ( klytos_apply_filters( 'admin.x402_dashboard.detail_cards', $detailCards ) as $card ) : ?>
        <?php $cardId = 'x402-detail-' . preg_replace( '/[^a-z0-9_-]/i', '', (string) $card['id'] ); ?>
        <section class="k-card k-card--padded" aria-labelledby="<?php echo klytos_esc_attr( $cardId ); ?>"
                 data-testid="x402.detail.<?php echo klytos_esc_attr( (string) $card['id'] ); ?>">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="<?php echo klytos_esc_attr( $cardId ); ?>">
                    <?php echo klytos_esc_html( (string) $card['title'] ); ?>
                </h2>

                <?php if ( empty( $card['rows'] ) ) : ?>
                    <p class="k-empty">
                        <span class="k-empty-text"><?php echo klytos_esc_html( __( 'klytos-x402.no_transactions' ) ); ?></span>
                    </p>
                <?php else : ?>
                    <table class="k-table">
                        <caption class="k-table-caption"><?php echo klytos_esc_html( (string) $card['title'] ); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo klytos_esc_html( (string) $card['head'] ); ?></th>
                                <th scope="col" class="k-num"><?php echo klytos_esc_html( __( 'klytos-x402.tx_count' ) ); ?></th>
                                <th scope="col" class="k-num"><?php echo klytos_esc_html( __( 'klytos-x402.tx_amount' ) ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $card['rows'] as $row ) : ?>
                                <tr>
                                    <th scope="row"><?php echo klytos_esc_html( (string) $row['name'] ); ?></th>
                                    <td class="k-num"><?php echo (int) $row['count']; ?></td>
                                    <td class="k-num"><?php echo klytos_esc_html( $money( (float) $row['total'] ) ); ?></td>
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
 * The provider fact stays on the screen: it is shipped product with no other
 * surface here, and it answers "who is taking the money" beside the figures
 * that money produced. Logged as an adaptation rather than left as a silent
 * addition — §18's card list does not name it.
 */
?>
<?php if ( $activeProvider !== null ) : ?>
    <section class="k-card k-card--padded" aria-labelledby="x402-provider-heading" data-testid="x402.provider">
        <div class="k-card-body">
            <h2 class="k-card-heading" id="x402-provider-heading">
                <?php echo klytos_esc_html( __( 'klytos-x402.provider' ) ); ?>
            </h2>
            <table class="k-table">
                <caption class="k-table-caption"><?php echo klytos_esc_html( __( 'klytos-x402.provider' ) ); ?></caption>
                <tbody>
                    <tr>
                        <th scope="row"><?php echo klytos_esc_html( __( 'klytos-x402.provider' ) ); ?></th>
                        <td><?php echo klytos_esc_html( $activeProvider->getLabel() ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo klytos_esc_html( __( 'klytos-x402.network' ) ); ?></th>
                        <td class="k-control--mono"><?php echo klytos_esc_html( (string) $config->get( 'network', 'base' ) ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php klytos_do_action( 'admin.x402_dashboard.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
