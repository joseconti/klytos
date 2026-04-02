<?php

/**
 * Klytos Admin — Webhook Management
 * Create, list, test, and delete webhook subscriptions.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\Helpers;
use Klytos\Core\WebhookManager;

$pageTitle      = 'Webhooks';
$auth           = $app->getAuth();
$webhookManager = new WebhookManager($app->getStorage());
$success        = '';
$error          = '';
$createdSecret  = null;
$csrf           = $auth->getCsrfToken();

// ─── Handle POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        try {
            // Parse selected events from checkboxes.
            $selectedEvents = $_POST['events'] ?? [];
            if (!is_array($selectedEvents)) {
                $selectedEvents = [];
            }

            $webhook = $webhookManager->create([
                'url'         => $_POST['url'] ?? '',
                'events'      => $selectedEvents,
                'description' => $_POST['description'] ?? '',
            ]);

            $createdSecret = $webhook['secret'] ?? '';
            $success = 'Webhook created. Copy the signing secret — it will not be shown again.';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'delete') {
        try {
            $webhookManager->delete($_POST['webhook_id'] ?? '');
            $success = 'Webhook deleted.';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'test') {
        try {
            $webhookManager->dispatch('test.ping', [
                'message'   => 'Test event from Klytos.',
                'timestamp' => Helpers::now(),
            ]);
            $success = 'Test event dispatched to all active webhooks.';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }

    $csrf = $auth->getCsrfToken();
}

// ─── Load data ───────────────────────────────────────────────
$webhooks       = $webhookManager->list();
$availableEvents = $webhookManager->getAvailableEvents();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.webhooks.before' ); ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<?php if ($createdSecret): ?>
    <div class="alert alert-warning">
        <strong>Signing Secret (copy now — will not be shown again):</strong>
        <div class="token-display mt-1"><?php echo klytos_esc_html( $createdSecret ); ?></div>
        <p class="text-xs" style="margin-top:0.4rem">
            Use this secret to verify webhook signatures via the <code>X-Klytos-Signature</code> header.
            Signature format: <code>sha256=HMAC(body, secret)</code>
        </p>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Active Webhooks</div>
        <div class="stat-value"><?php echo count( array_filter($webhooks, fn($w) => ($w['status'] ?? '') === 'active')); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Available Events</div>
        <div class="stat-value"><?php echo count( $availableEvents); ?></div>
        <div class="stat-detail">Core + plugins</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Webhooks</div>
        <div class="stat-value"><?php echo count( $webhooks); ?></div>
    </div>
</div>

<!-- Action bar -->
<div class="action-bar">
    <div></div>
    <div class="flex flex-gap-sm">
        <form method="post" class="inline-form">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="test">
            <button type="submit" class="btn btn-outline">Send Test Event</button>
        </form>
        <button class="btn btn-primary" id="btnNewWebhook">
            + New Webhook
        </button>
    </div>
</div>

<!-- Webhooks list -->
<div class="card">
    <?php if (empty($webhooks)): ?>
        <div class="empty-state">
            <h3>No webhooks configured</h3>
            <p>Webhooks notify external services when events occur in Klytos.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Events</th>
                        <th>Status</th>
                        <th>Failures</th>
                        <th>Last Triggered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($webhooks as $wh): ?>
                    <tr>
                        <td>
                            <code class="text-xs"><?php echo klytos_esc_html( $wh['url'] ?? ''); ?></code>
                            <?php if (!empty($wh['description'])): ?>
                                <br><small class="text-muted"><?php echo klytos_esc_html( $wh['description'] ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php foreach (($wh['events'] ?? []) as $event): ?>
                                <span class="badge-status badge-medium" style="margin:1px 0;display:inline-block;"><?php echo klytos_esc_html( $event ); ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <span class="badge-status badge-<?php echo ($wh['status'] ?? '') === 'active' ? 'active' : 'inactive'; ?>">
                                <?php echo ucfirst( klytos_esc_html( $wh['status'] ?? 'unknown')); ?>
                            </span>
                        </td>
                        <td>
                            <?php $fails = $wh['failure_count'] ?? 0; ?>
                            <span style="color:<?php echo $fails > 0 ? 'var(--klytos-error)' : 'var(--klytos-text-muted)'; ?>">
                                <?php echo $fails; ?>
                            </span>
                        </td>
                        <td class="text-sm text-muted">
                            <?php echo $wh['last_triggered'] ? date( 'M j H:i', strtotime($wh['last_triggered'])) : '—'; ?>
                        </td>
                        <td>
                            <form method="post" class="inline-form">
                                <?php echo klytos_csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="webhook_id" value="<?php echo klytos_esc_attr( $wh['id'] ?? ''); ?>">
                                <button type="submit" class="btn btn-danger btn-sm btn-confirm-delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php klytos_do_action( 'admin.webhooks.before_form' ); ?>

<!-- Create Webhook Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <h3>Create New Webhook</h3>
        <form method="post">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label>Endpoint URL</label>
                <input type="url" name="url" class="form-control" required placeholder="https://example.com/webhook">
                <div class="form-help">HTTPS recommended. This URL will receive POST requests with JSON payloads.</div>
            </div>

            <div class="form-group">
                <label>Description (optional)</label>
                <input type="text" name="description" class="form-control" placeholder="e.g. Slack notification for builds">
            </div>

            <div class="form-group">
                <label>Events to subscribe</label>
                <div class="grid-2" style="gap:0.4rem;margin-top:0.3rem">
                    <?php foreach ($availableEvents as $event => $desc): ?>
                    <label class="flex flex-center text-sm" style="gap:0.4rem;font-weight:400;cursor:pointer">
                        <input type="checkbox" name="events[]" value="<?php echo klytos_esc_attr( $event ); ?>">
                        <span title="<?php echo klytos_esc_attr( $desc ); ?>"><?php echo klytos_esc_html( $event ); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex flex-gap-sm mt-2" style="justify-content:flex-end">
                <button type="button" class="btn btn-outline" id="btnCancelWebhook">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Webhook</button>
            </div>
        </form>
    </div>
</div>

<?php klytos_do_action( 'admin.webhooks.after_form' ); ?>

<script nonce="<?php echo $cspNonce; ?>">
(function() {
    var modal       = document.getElementById( 'createModal' );
    var btnOpen     = document.getElementById( 'btnNewWebhook' );
    var btnCancel   = document.getElementById( 'btnCancelWebhook' );

    if ( btnOpen ) {
        btnOpen.addEventListener( 'click', function() {
            modal.classList.add( 'active' );
        });
    }
    if ( btnCancel ) {
        btnCancel.addEventListener( 'click', function() {
            modal.classList.remove( 'active' );
        });
    }
    // Close on overlay click.
    modal.addEventListener( 'click', function( e ) {
        if ( e.target === modal ) {
            modal.classList.remove( 'active' );
        }
    });

    // Confirm before delete.
    document.querySelectorAll( '.btn-confirm-delete' ).forEach( function( btn ) {
        btn.addEventListener( 'click', function( e ) {
            if ( !confirm( 'Delete this webhook?' ) ) {
                e.preventDefault();
            }
        });
    });
})();
</script>

<?php klytos_do_action( 'admin.webhooks.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
