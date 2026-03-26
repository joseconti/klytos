<?php
/**
 * Klytos Admin — Webhook Management
 * Create, list, test, and delete webhook subscriptions.
 *
 * @package Klytos
 * @since   2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf($_POST['csrf'] ?? '')) {
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

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars( $success ); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars( $error ); ?></div>
<?php endif; ?>

<?php if ($createdSecret): ?>
    <div class="alert alert-warning">
        <strong>Signing Secret (copy now — will not be shown again):</strong>
        <div class="token-display" style="margin-top:0.5rem"><?php echo htmlspecialchars( $createdSecret ); ?></div>
        <p style="font-size:0.8rem;margin-top:0.4rem">
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
    <div style="display:flex;gap:0.5rem">
        <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
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
                            <code style="font-size:0.8rem"><?php echo htmlspecialchars( $wh['url'] ?? ''); ?></code>
                            <?php if (!empty($wh['description'])): ?>
                                <br><small style="color:var(--admin-text-muted)"><?php echo htmlspecialchars( $wh['description'] ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php foreach (($wh['events'] ?? []) as $event): ?>
                                <span class="badge-status badge-medium" style="margin:1px 0;display:inline-block"><?php echo htmlspecialchars( $event ); ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <span class="badge-status badge-<?php echo ($wh['status'] ?? '') === 'active' ? 'active' : 'inactive'; ?>">
                                <?php echo ucfirst( htmlspecialchars( $wh['status'] ?? 'unknown')); ?>
                            </span>
                        </td>
                        <td>
                            <?php $fails = $wh['failure_count'] ?? 0; ?>
                            <span style="color:<?php echo $fails > 0 ? 'var(--admin-error)' : 'var(--admin-text-muted)'; ?>">
                                <?php echo $fails; ?>
                            </span>
                        </td>
                        <td style="font-size:0.85rem;color:var(--admin-text-muted)">
                            <?php echo $wh['last_triggered'] ? date( 'M j H:i', strtotime($wh['last_triggered'])) : '—'; ?>
                        </td>
                        <td>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="webhook_id" value="<?php echo htmlspecialchars( $wh['id'] ?? ''); ?>">
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

<!-- Create Webhook Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <h3>Create New Webhook</h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
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
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.4rem;margin-top:0.3rem">
                    <?php foreach ($availableEvents as $event => $desc): ?>
                    <label style="display:flex;align-items:center;gap:0.4rem;font-weight:400;font-size:0.85rem;cursor:pointer">
                        <input type="checkbox" name="events[]" value="<?php echo htmlspecialchars( $event ); ?>">
                        <span title="<?php echo htmlspecialchars( $desc ); ?>"><?php echo htmlspecialchars( $event ); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem">
                <button type="button" class="btn btn-outline" id="btnCancelWebhook">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Webhook</button>
            </div>
        </form>
    </div>
</div>

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

<?php require_once __DIR__ . '/templates/footer.php'; ?>
