<?php

/**
 * Klytos Admin — x402 Dashboard
 * Revenue stats, transaction chart, top pages, top bots.
 *
 * @package Klytos
 * @since   2.0.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

$pageTitle = __( 'klytos-x402.sidebar_title' );
$auth      = $app->getAuth();

$config   = klytos_x402_config();
$stats    = klytos_x402_stats();
$registry = klytos_x402_providers();

$summary      = $stats->getSummary();
$topPages     = $stats->getTopPages( 10 );
$topBots      = $stats->getTopBots( 10 );
$dailyRevenue = $stats->getDailyRevenue( 30 );

$activeProviderId = $config->get( 'provider_id', '' );
$activeProvider   = $registry->has( $activeProviderId ) ? $registry->get( $activeProviderId ) : null;

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if ( $registry->isEmpty() ): ?>
    <div class="alert alert-warning">
        <?php echo klytos_esc_html( __( 'klytos-x402.no_provider' ) ); ?>
    </div>
<?php endif; ?>

<!-- Revenue Cards -->
<div class="stats-grid">
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
    <div class="stat-card">
        <div class="stat-card__label"><?php echo klytos_esc_html( $label ); ?></div>
        <div class="stat-card__value">$<?php echo klytos_esc_html( $rev['total_usd'] ); ?></div>
        <div class="stat-card__meta"><?php echo (int) $rev['transaction_count']; ?> txns</div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Active Provider Info -->
<?php if ( $activeProvider ): ?>
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h2><?php echo klytos_esc_html( __( 'klytos-x402.provider' ) ); ?></h2>
    </div>
    <div class="card-body">
        <p>
            <strong><?php echo klytos_esc_html( $activeProvider->getLabel() ); ?></strong><br>
            <?php echo klytos_esc_html( __( 'klytos-x402.network' ) ); ?>: <?php echo klytos_esc_html( $config->get( 'network', 'base' ) ); ?><br>
            <?php echo klytos_esc_html( __( 'klytos-x402.wallet_address' ) ); ?>: <code><?php echo klytos_esc_html( substr( $config->get( 'wallet_address', '' ), 0, 10 ) . '...' ); ?></code>
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Daily Revenue Chart -->
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h2><?php echo klytos_esc_html( __( 'klytos-x402.daily_revenue' ) ); ?></h2>
    </div>
    <div class="card-body">
        <div style="display: flex; align-items: flex-end; gap: 2px; height: 120px;">
            <?php
            $maxRevenue = 0.0001;
            foreach ( $dailyRevenue as $day ) {
                $val = (float) $day['total_usd'];
                if ( $val > $maxRevenue ) $maxRevenue = $val;
            }
            foreach ( $dailyRevenue as $day ):
                $val    = (float) $day['total_usd'];
                $height = $maxRevenue > 0 ? round( ( $val / $maxRevenue ) * 100 ) : 0;
                $height = max( $height, 2 );
            ?>
            <div
                title="<?php echo klytos_esc_attr( $day['date'] . ': $' . $day['total_usd'] . ' (' . $day['count'] . ' txns)' ); ?>"
                style="flex: 1; height: <?php echo $height; ?>%; background: var(--accent); border-radius: 2px 2px 0 0; min-width: 4px;"
            ></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Top Pages & Top Bots -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
    <div class="card">
        <div class="card-header">
            <h2><?php echo klytos_esc_html( __( 'klytos-x402.top_pages' ) ); ?></h2>
        </div>
        <div class="card-body">
            <?php if ( empty( $topPages ) ): ?>
                <p class="text-muted"><?php echo klytos_esc_html( __( 'klytos-x402.no_transactions' ) ); ?></p>
            <?php else: ?>
                <table class="table">
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

    <div class="card">
        <div class="card-header">
            <h2><?php echo klytos_esc_html( __( 'klytos-x402.top_bots' ) ); ?></h2>
        </div>
        <div class="card-body">
            <?php if ( empty( $topBots ) ): ?>
                <p class="text-muted"><?php echo klytos_esc_html( __( 'klytos-x402.no_transactions' ) ); ?></p>
            <?php else: ?>
                <table class="table">
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

<?php require_once __DIR__ . '/templates/footer.php'; ?>
