<?php

/**
 * Klytos Admin — x402 Transactions
 * Paginated list of payment transactions with filters.
 *
 * @package Klytos
 * @since   2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

$pageTitle = __( 'klytos-x402.transactions' );
$auth      = $app->getAuth();
$log       = klytos_x402_log();

// ─── Filters ───────────────────────────────────────────────────
$from     = $_GET['from'] ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) );
$to       = $_GET['to']   ?? gmdate( 'Y-m-d' );
$slug     = $_GET['slug'] ?? '';
$bot      = $_GET['bot']  ?? '';
$page     = max( 1, (int) ( $_GET['p'] ?? 1 ) );
$perPage  = 25;
$offset   = ( $page - 1 ) * $perPage;

$filters = ['from' => $from, 'to' => $to];
if ( !empty( $slug ) ) $filters['slug'] = $slug;
if ( !empty( $bot ) )  $filters['bot_user_agent'] = $bot;

$result       = $log->list( $filters, $perPage, $offset );
$transactions = $result['transactions'];
$total        = $result['total'];
$totalPages   = (int) ceil( $total / $perPage );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<!-- Filters -->
<div class="card">
    <div class="card-body">
        <form method="get" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">From</label>
                <input type="date" name="from" value="<?php echo klytos_esc_attr( $from ); ?>" class="form-control">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">To</label>
                <input type="date" name="to" value="<?php echo klytos_esc_attr( $to ); ?>" class="form-control">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label"><?php echo klytos_esc_html( __( 'klytos-x402.tx_page' ) ); ?></label>
                <input type="text" name="slug" value="<?php echo klytos_esc_attr( $slug ); ?>" class="form-control" placeholder="slug">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label"><?php echo klytos_esc_html( __( 'klytos-x402.tx_bot' ) ); ?></label>
                <input type="text" name="bot" value="<?php echo klytos_esc_attr( $bot ); ?>" class="form-control" placeholder="GPTBot">
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h2><?php echo klytos_esc_html( __( 'klytos-x402.transactions' ) ); ?> (<?php echo $total; ?>)</h2>
    </div>
    <div class="card-body">
        <?php if ( empty( $transactions ) ): ?>
            <p class="text-muted"><?php echo klytos_esc_html( __( 'klytos-x402.no_transactions' ) ); ?></p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th><?php echo klytos_esc_html( __( 'klytos-x402.tx_id' ) ); ?></th>
                        <th><?php echo klytos_esc_html( __( 'klytos-x402.tx_page' ) ); ?></th>
                        <th><?php echo klytos_esc_html( __( 'klytos-x402.tx_bot' ) ); ?></th>
                        <th><?php echo klytos_esc_html( __( 'klytos-x402.tx_amount' ) ); ?></th>
                        <th><?php echo klytos_esc_html( __( 'klytos-x402.tx_network' ) ); ?></th>
                        <th><?php echo klytos_esc_html( __( 'klytos-x402.tx_provider' ) ); ?></th>
                        <th><?php echo klytos_esc_html( __( 'klytos-x402.tx_date' ) ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $transactions as $tx ): ?>
                    <tr>
                        <td><code><?php echo klytos_esc_html( $tx['id'] ?? '' ); ?></code></td>
                        <td><?php echo klytos_esc_html( $tx['slug'] ?? '' ); ?></td>
                        <td><?php echo klytos_esc_html( $tx['bot_user_agent'] ?? '' ); ?></td>
                        <td>$<?php echo klytos_esc_html( $tx['amount_usd'] ?? '0.00' ); ?></td>
                        <td><?php echo klytos_esc_html( $tx['network'] ?? '' ); ?></td>
                        <td><?php echo klytos_esc_html( $tx['provider_id'] ?? '' ); ?></td>
                        <td><?php echo klytos_esc_html( $tx['created_at'] ?? '' ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ( $totalPages > 1 ): ?>
            <div style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: center;">
                <?php for ( $i = 1; $i <= $totalPages; $i++ ):
                    $qp = ['from' => $from, 'to' => $to, 'p' => $i];
                    if ( $slug ) $qp['slug'] = $slug;
                    if ( $bot ) $qp['bot'] = $bot;
                    $url = 'x402-transactions.php?' . http_build_query( $qp );
                ?>
                    <a href="<?php echo klytos_esc_url( $url ); ?>"
                       class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-sm'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
