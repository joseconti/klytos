<?php

/**
 * Klytos x402 — Admin Dashboard
 *
 * Revenue stats, transaction chart, top pages, top bots.
 *
 * @package KlytosX402
 * @since   1.0.0
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

$config   = klytos_x402_config();
$stats    = klytos_x402_stats();
$registry = klytos_x402_providers();

$summary      = $stats->getSummary();
$topPages     = $stats->getTopPages( 10 );
$topBots      = $stats->getTopBots( 10 );
$dailyRevenue = $stats->getDailyRevenue( 30 );

$activeProviderId = $config->get( 'provider_id', '' );
$activeProvider   = $registry->has( $activeProviderId ) ? $registry->get( $activeProviderId ) : null;

$cspNonce = $GLOBALS['cspNonce'] ?? '';

?>

<div class="klytos-page-header">
    <h1><?php echo klytos_esc_html( __( 'klytos-x402.sidebar_title' ) ); ?></h1>
</div>

<?php if ( !$activeProvider ): ?>
    <div class="klytos-notice klytos-notice--warning">
        <?php echo klytos_esc_html( __( 'klytos-x402.no_provider' ) ); ?>
    </div>
<?php endif; ?>

<!-- Revenue Cards -->
<div class="klytos-grid klytos-grid--4">
    <?php
    $periods = [
        'today' => __( 'klytos-x402.revenue_today' ),
        'week'  => __( 'klytos-x402.revenue_week' ),
        'month' => __( 'klytos-x402.revenue_month' ),
        'total' => __( 'klytos-x402.revenue_total' ),
    ];
    foreach ( $periods as $key => $label ):
        $rev = $summary[$key] ?? ['total_usd' => '0.0000', 'transaction_count' => 0];
    ?>
    <div class="klytos-card">
        <div class="klytos-card__header">
            <span class="klytos-card__label"><?php echo klytos_esc_html( $label ); ?></span>
        </div>
        <div class="klytos-card__body">
            <span class="klytos-card__value">$<?php echo klytos_esc_html( $rev['total_usd'] ); ?></span>
            <span class="klytos-card__meta"><?php echo (int) $rev['transaction_count']; ?> txns</span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Active Provider Info -->
<?php if ( $activeProvider ): ?>
<div class="klytos-card" style="margin-top: var(--klytos-space-4);">
    <div class="klytos-card__header">
        <span class="klytos-card__label"><?php echo klytos_esc_html( __( 'klytos-x402.provider' ) ); ?></span>
    </div>
    <div class="klytos-card__body">
        <strong><?php echo klytos_esc_html( $activeProvider->getLabel() ); ?></strong>
        <br>
        <?php echo klytos_esc_html( __( 'klytos-x402.network' ) ); ?>: <?php echo klytos_esc_html( $config->get( 'network', 'base' ) ); ?>
        <br>
        <?php echo klytos_esc_html( __( 'klytos-x402.wallet_address' ) ); ?>: <code><?php echo klytos_esc_html( substr( $config->get( 'wallet_address', '' ), 0, 10 ) . '...' ); ?></code>
    </div>
</div>
<?php endif; ?>

<!-- Daily Revenue Chart -->
<div class="klytos-card" style="margin-top: var(--klytos-space-4);">
    <div class="klytos-card__header">
        <span class="klytos-card__label"><?php echo klytos_esc_html( __( 'klytos-x402.daily_revenue' ) ); ?></span>
    </div>
    <div class="klytos-card__body">
        <div id="x402-chart" style="display: flex; align-items: flex-end; gap: 2px; height: 120px; padding: var(--klytos-space-2) 0;">
            <?php
            $maxRevenue = 0.0001; // Avoid division by zero.
            foreach ( $dailyRevenue as $day ) {
                $val = (float) $day['total_usd'];
                if ( $val > $maxRevenue ) {
                    $maxRevenue = $val;
                }
            }
            foreach ( $dailyRevenue as $day ):
                $val     = (float) $day['total_usd'];
                $height  = $maxRevenue > 0 ? round( ( $val / $maxRevenue ) * 100 ) : 0;
                $height  = max( $height, 2 ); // Minimum bar height.
            ?>
            <div
                title="<?php echo klytos_esc_attr( $day['date'] . ': $' . $day['total_usd'] . ' (' . $day['count'] . ' txns)' ); ?>"
                style="flex: 1; height: <?php echo $height; ?>%; background: var(--klytos-primary); border-radius: 2px 2px 0 0; min-width: 4px;"
            ></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Top Pages & Top Bots -->
<div class="klytos-grid klytos-grid--2" style="margin-top: var(--klytos-space-4);">
    <div class="klytos-card">
        <div class="klytos-card__header">
            <span class="klytos-card__label"><?php echo klytos_esc_html( __( 'klytos-x402.top_pages' ) ); ?></span>
        </div>
        <div class="klytos-card__body">
            <?php if ( empty( $topPages ) ): ?>
                <p class="klytos-text--muted"><?php echo klytos_esc_html( __( 'klytos-x402.no_transactions' ) ); ?></p>
            <?php else: ?>
                <table class="klytos-table klytos-table--compact">
                    <thead>
                        <tr>
                            <th><?php echo klytos_esc_html( __( 'klytos-x402.tx_page' ) ); ?></th>
                            <th>Txns</th>
                            <th><?php echo klytos_esc_html( __( 'klytos-x402.tx_amount' ) ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $topPages as $p ): ?>
                        <tr>
                            <td><code><?php echo klytos_esc_html( $p['slug'] ); ?></code></td>
                            <td><?php echo (int) $p['count']; ?></td>
                            <td>$<?php echo klytos_esc_html( $p['total_usd'] ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="klytos-card">
        <div class="klytos-card__header">
            <span class="klytos-card__label"><?php echo klytos_esc_html( __( 'klytos-x402.top_bots' ) ); ?></span>
        </div>
        <div class="klytos-card__body">
            <?php if ( empty( $topBots ) ): ?>
                <p class="klytos-text--muted"><?php echo klytos_esc_html( __( 'klytos-x402.no_transactions' ) ); ?></p>
            <?php else: ?>
                <table class="klytos-table klytos-table--compact">
                    <thead>
                        <tr>
                            <th><?php echo klytos_esc_html( __( 'klytos-x402.tx_bot' ) ); ?></th>
                            <th>Txns</th>
                            <th><?php echo klytos_esc_html( __( 'klytos-x402.tx_amount' ) ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $topBots as $b ): ?>
                        <tr>
                            <td><?php echo klytos_esc_html( $b['bot'] ); ?></td>
                            <td><?php echo (int) $b['count']; ?></td>
                            <td>$<?php echo klytos_esc_html( $b['total_usd'] ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
