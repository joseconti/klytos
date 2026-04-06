<?php

/**
 * Klytos x402 — Admin Transactions
 *
 * Paginated list of payment transactions with filters.
 *
 * @package KlytosX402
 * @since   1.0.0
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

$pageTitle = __( 'klytos-x402.transactions' );

$log = klytos_x402_log();

// ─── Filters ───────────────────────────────────────────────────
$from     = $_GET['from'] ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) );
$to       = $_GET['to']   ?? gmdate( 'Y-m-d' );
$slug     = $_GET['slug'] ?? '';
$bot      = $_GET['bot']  ?? '';
$page     = max( 1, (int) ( $_GET['p'] ?? 1 ) );
$perPage  = 25;
$offset   = ( $page - 1 ) * $perPage;

$filters = ['from' => $from, 'to' => $to];
if ( !empty( $slug ) ) {
    $filters['slug'] = $slug;
}
if ( !empty( $bot ) ) {
    $filters['bot_user_agent'] = $bot;
}

$result       = $log->list( $filters, $perPage, $offset );
$transactions = $result['transactions'];
$total        = $result['total'];
$totalPages   = (int) ceil( $total / $perPage );

require_once __DIR__ . '/templates/header.php';

$baseUrl = \Klytos\Core\Helpers::getBasePath() . 'admin/x402-transactions.php';

?>

<div class="klytos-page-header">
    <h1><?php echo klytos_esc_html( __( 'klytos-x402.transactions' ) ); ?></h1>
</div>

<!-- Filters -->
<div class="klytos-card" style="margin-bottom: var(--klytos-space-4);">
    <div class="klytos-card__body">
        <form method="get" class="klytos-form klytos-form--inline">
            <input type="hidden" name="plugin" value="klytos-x402" />
            <input type="hidden" name="page" value="transactions" />

            <div class="klytos-field">
                <label class="klytos-field__label">From</label>
                <input type="date" name="from" value="<?php echo klytos_esc_attr( $from ); ?>" class="klytos-field__input" />
            </div>
            <div class="klytos-field">
                <label class="klytos-field__label">To</label>
                <input type="date" name="to" value="<?php echo klytos_esc_attr( $to ); ?>" class="klytos-field__input" />
            </div>
            <div class="klytos-field">
                <label class="klytos-field__label"><?php echo klytos_esc_html( __( 'klytos-x402.tx_page' ) ); ?></label>
                <input type="text" name="slug" value="<?php echo klytos_esc_attr( $slug ); ?>" class="klytos-field__input" placeholder="slug" />
            </div>
            <div class="klytos-field">
                <label class="klytos-field__label"><?php echo klytos_esc_html( __( 'klytos-x402.tx_bot' ) ); ?></label>
                <input type="text" name="bot" value="<?php echo klytos_esc_attr( $bot ); ?>" class="klytos-field__input" placeholder="GPTBot" />
            </div>
            <div class="klytos-field" style="align-self: flex-end;">
                <button type="submit" class="klytos-btn klytos-btn--secondary">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="klytos-card">
    <div class="klytos-card__body">
        <?php if ( empty( $transactions ) ): ?>
            <p class="klytos-text--muted"><?php echo klytos_esc_html( __( 'klytos-x402.no_transactions' ) ); ?></p>
        <?php else: ?>
            <table class="klytos-table">
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
            <div class="klytos-pagination" style="margin-top: var(--klytos-space-4); display: flex; gap: var(--klytos-space-2); justify-content: center;">
                <?php for ( $i = 1; $i <= $totalPages; $i++ ): ?>
                    <?php
                    $queryParams = ['p' => $i, 'from' => $from, 'to' => $to];
                    if ( $slug ) $queryParams['slug'] = $slug;
                    if ( $bot ) $queryParams['bot'] = $bot;
                    $url = \Klytos\Core\Helpers::getBasePath() . 'admin/x402-transactions.php?' . http_build_query( $queryParams );
                    ?>
                    <a href="<?php echo klytos_esc_url( $url ); ?>"
                       class="klytos-btn <?php echo $i === $page ? 'klytos-btn--primary' : 'klytos-btn--secondary'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<div style="margin-top: var(--klytos-space-2);">
    <span class="klytos-text--muted"><?php echo $total; ?> total transactions</span>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
