<?php
/**
 * Klytos Admin — Scheduled Actions
 * Configure server cron and manage scheduled actions.
 *
 * @package Klytos
 * @since   2.0.0
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

$pageTitle = 'Scheduled Actions';
$auth      = $app->getAuth();
$scheduler = $app->getActionScheduler();
$success   = '';
$error     = '';
$csrf      = $auth->getCsrfToken();

// ─── Active tab ─────────────────────────────────────────────
$activeTab = $_GET['tab'] ?? 'settings';
if (!in_array($activeTab, ['settings', 'actions'], true)) {
    $activeTab = 'settings';
}

// ─── Handle POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'regenerate_token') {
        $scheduler->regenerateCronToken();
        $success = 'Cron token regenerated. Update the crontab command on your server.';
    } elseif ($action === 'toggle_fallback') {
        $enabled = !empty($_POST['fallback_enabled']);
        $scheduler->setFallbackEnabled($enabled);
        $success = 'Fallback mode ' . ($enabled ? 'enabled' : 'disabled') . '.';
    } elseif ($action === 'cancel_action') {
        $actionId = $_POST['action_id'] ?? '';
        if ($scheduler->cancel($actionId)) {
            $success = 'Action canceled.';
        } else {
            $error = 'Could not cancel action (already running or completed).';
        }
    } elseif ($action === 'retry_action') {
        $actionId = $_POST['action_id'] ?? '';
        if ($scheduler->retry($actionId)) {
            $success = 'Action queued for retry.';
        } else {
            $error = 'Could not retry action.';
        }
    } elseif ($action === 'delete_action') {
        $actionId = $_POST['action_id'] ?? '';
        if ($scheduler->deleteAction($actionId)) {
            $success = 'Action deleted.';
        } else {
            $error = 'Could not delete action.';
        }
    } elseif ($action === 'prune_completed') {
        $pruned = $scheduler->pruneCompleted(30);
        $success = "Pruned {$pruned} completed/failed actions older than 30 days.";
    }

    $csrf = $auth->getCsrfToken();
}

// ─── Load data ───────────────────────────────────────────────
$cronToken      = $scheduler->getCronToken();
$fallbackEnabled = $scheduler->isFallbackEnabled();
$lastRun        = $scheduler->getLastRunTimestamp();
$siteUrl        = Helpers::siteUrl();
$cliPath        = realpath(__DIR__ . '/../cli.php');

// Stats
$pendingCount   = $scheduler->countActions(['status' => 'pending']);
$runningCount   = $scheduler->countActions(['status' => 'running']);
$completeCount  = $scheduler->countActions(['status' => 'complete']);
$failedCount    = $scheduler->countActions(['status' => 'failed']);

// Actions list (for actions tab)
$statusFilter = $_GET['status'] ?? 'all';
$filters      = [];
if ($statusFilter !== 'all' && in_array($statusFilter, ['pending', 'running', 'complete', 'failed', 'canceled'], true)) {
    $filters['status'] = $statusFilter;
}
$page    = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;
$actions = $scheduler->listActions($filters, $perPage, $offset);

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html($success); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html($error); ?></div>
<?php endif; ?>

<!-- Tabs -->
<div class="tabs">
    <a href="?tab=settings" class="tab <?php echo $activeTab === 'settings' ? 'active' : ''; ?>">Settings</a>
    <a href="?tab=actions" class="tab <?php echo $activeTab === 'actions' ? 'active' : ''; ?>">Scheduled Actions</a>
</div>

<?php if ($activeTab === 'settings'): ?>

    <!-- Cron Command -->
    <div class="card">
        <div class="card-header"><h3>Server Cron Command</h3></div>
        <div style="padding:1rem">
            <p style="margin-bottom:1rem;color:var(--admin-text-muted);font-size:0.9rem">
                Add one of the following commands to your server's crontab to process scheduled actions every minute.
            </p>

            <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.3rem">HTTP (curl)</label>
            <div class="token-display" id="cronCurl" style="margin-bottom:1rem;word-break:break-all">* * * * * curl -s "<?php echo klytos_esc_attr($siteUrl); ?>?route=cron&token=<?php echo klytos_esc_attr($cronToken); ?>" > /dev/null 2>&1</div>

            <label style="font-weight:600;font-size:0.85rem;display:block;margin-bottom:0.3rem">CLI (PHP)</label>
            <div class="token-display" id="cronCli" style="margin-bottom:1rem;word-break:break-all">* * * * * php <?php echo klytos_esc_html($cliPath); ?> cron:run --token=<?php echo klytos_esc_attr($cronToken); ?></div>

            <div style="display:flex;gap:0.5rem;margin-top:0.5rem">
                <button class="btn btn-outline btn-sm" onclick="copyText('cronCurl')">Copy HTTP</button>
                <button class="btn btn-outline btn-sm" onclick="copyText('cronCli')">Copy CLI</button>
            </div>
        </div>
    </div>

    <!-- Cron Status -->
    <div class="card">
        <div class="card-header"><h3>Cron Status</h3></div>
        <div style="padding:1rem">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div>
                    <div style="font-size:0.85rem;color:var(--admin-text-muted)">Last Execution</div>
                    <div style="font-size:1.1rem;font-weight:600;margin-top:0.2rem">
                        <?php if ($lastRun): ?>
                            <?php echo klytos_esc_html(date('M j, Y H:i:s', $lastRun)); ?>
                            <?php $ago = time() - $lastRun; ?>
                            <small style="color:var(--admin-text-muted)">(<?php echo $ago < 60 ? $ago . 's ago' : (int)($ago / 60) . 'm ago'; ?>)</small>
                        <?php else: ?>
                            <span style="color:var(--admin-warning)">Never</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div style="font-size:0.85rem;color:var(--admin-text-muted)">Pending Actions</div>
                    <div style="font-size:1.1rem;font-weight:600;margin-top:0.2rem">
                        <?php echo $pendingCount; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Regenerate Token -->
    <div class="card">
        <div class="card-header"><h3>Security Token</h3></div>
        <div style="padding:1rem">
            <p style="margin-bottom:1rem;color:var(--admin-text-muted);font-size:0.9rem">
                The cron endpoint is protected by a secret token. Regenerating the token will invalidate the current crontab command — you'll need to update it on your server.
            </p>
            <form method="post">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="action" value="regenerate_token">
                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Regenerate cron token? You will need to update the crontab on your server.')">
                    Regenerate Token
                </button>
            </form>
        </div>
    </div>

    <!-- Fallback Mode -->
    <div class="card">
        <div class="card-header"><h3>Fallback Mode</h3></div>
        <div style="padding:1rem">
            <p style="margin-bottom:1rem;color:var(--admin-text-muted);font-size:0.9rem">
                When enabled, the action queue is also processed on admin page loads (pseudo-cron). This is a fallback for when the server cron is not configured. Disable once you have set up the server crontab.
            </p>
            <form method="post">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="action" value="toggle_fallback">
                <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer">
                    <input type="checkbox" name="fallback_enabled" value="1" <?php echo $fallbackEnabled ? 'checked' : ''; ?> onchange="this.form.submit()">
                    <span>Enable fallback pseudo-cron</span>
                </label>
            </form>
        </div>
    </div>

<?php else: ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?php echo $pendingCount; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Running</div>
            <div class="stat-value"><?php echo $runningCount; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Completed</div>
            <div class="stat-value"><?php echo $completeCount; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Failed</div>
            <div class="stat-value" style="color:<?php echo $failedCount > 0 ? 'var(--admin-error)' : 'inherit'; ?>"><?php echo $failedCount; ?></div>
        </div>
    </div>

    <!-- Action bar -->
    <div class="action-bar">
        <div style="display:flex;gap:0.3rem;flex-wrap:wrap">
            <?php
            $statuses = ['all' => 'All', 'pending' => 'Pending', 'running' => 'Running', 'complete' => 'Complete', 'failed' => 'Failed', 'canceled' => 'Canceled'];
            foreach ($statuses as $key => $label): ?>
                <a href="?tab=actions&status=<?php echo $key; ?>" class="btn btn-sm <?php echo $statusFilter === $key ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>
        <form method="post" style="display:inline">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="prune_completed">
            <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('Prune completed/failed/canceled actions older than 30 days?')">
                Prune Old Actions
            </button>
        </form>
    </div>

    <!-- Actions table -->
    <div class="card">
        <?php if (empty($actions)): ?>
            <div class="empty-state">
                <h3>No scheduled actions</h3>
                <p>Actions will appear here when scheduled by core or plugins.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Hook</th>
                            <th>Group</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Scheduled</th>
                            <th>Attempts</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($actions as $act): ?>
                        <tr>
                            <td>
                                <code style="font-size:0.8rem"><?php echo klytos_esc_html($act['hook'] ?? ''); ?></code>
                                <?php if (!empty($act['args'])): ?>
                                    <br><small style="color:var(--admin-text-muted)"><?php echo klytos_esc_html(json_encode($act['args'])); ?></small>
                                <?php endif; ?>
                                <?php if (!empty($act['last_error'])): ?>
                                    <br><small style="color:var(--admin-error)"><?php echo klytos_esc_html($act['last_error']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($act['group'])): ?>
                                    <span class="badge-status badge-medium"><?php echo klytos_esc_html($act['group']); ?></span>
                                <?php else: ?>
                                    <span style="color:var(--admin-text-muted)">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.85rem"><?php echo klytos_esc_html($act['type'] ?? 'single'); ?></td>
                            <td>
                                <?php
                                $status = $act['status'] ?? 'pending';
                                $badgeClass = match ($status) {
                                    'pending'  => 'badge-draft',
                                    'running'  => 'badge-medium',
                                    'complete' => 'badge-active',
                                    'failed'   => 'badge-inactive',
                                    'canceled' => 'badge-inactive',
                                    default    => 'badge-draft',
                                };
                                ?>
                                <span class="badge-status <?php echo $badgeClass; ?>"><?php echo klytos_esc_html(ucfirst($status)); ?></span>
                            </td>
                            <td style="font-size:0.85rem;color:var(--admin-text-muted)">
                                <?php echo !empty($act['scheduled_at']) ? klytos_esc_html(date('M j H:i', strtotime($act['scheduled_at']))) : '—'; ?>
                            </td>
                            <td style="text-align:center">
                                <?php echo (int) ($act['attempts'] ?? 0); ?>/<?php echo (int) ($act['max_attempts'] ?? 3); ?>
                            </td>
                            <td>
                                <?php if ($status === 'pending'): ?>
                                    <form method="post" style="display:inline">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="action" value="cancel_action">
                                        <input type="hidden" name="action_id" value="<?php echo klytos_esc_attr($act['id'] ?? ''); ?>">
                                        <button type="submit" class="btn btn-outline btn-sm">Cancel</button>
                                    </form>
                                <?php elseif ($status === 'failed'): ?>
                                    <form method="post" style="display:inline">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="action" value="retry_action">
                                        <input type="hidden" name="action_id" value="<?php echo klytos_esc_attr($act['id'] ?? ''); ?>">
                                        <button type="submit" class="btn btn-outline btn-sm">Retry</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (in_array($status, ['complete', 'failed', 'canceled'], true)): ?>
                                    <form method="post" style="display:inline">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_action">
                                        <input type="hidden" name="action_id" value="<?php echo klytos_esc_attr($act['id'] ?? ''); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if (count($actions) >= $perPage || $page > 1): ?>
                <div style="padding:1rem;display:flex;gap:0.5rem;justify-content:center">
                    <?php if ($page > 1): ?>
                        <a href="?tab=actions&status=<?php echo klytos_esc_attr($statusFilter); ?>&p=<?php echo $page - 1; ?>" class="btn btn-outline btn-sm">&larr; Previous</a>
                    <?php endif; ?>
                    <span style="padding:0.4rem 0.8rem;font-size:0.85rem;color:var(--admin-text-muted)">Page <?php echo $page; ?></span>
                    <?php if (count($actions) >= $perPage): ?>
                        <a href="?tab=actions&status=<?php echo klytos_esc_attr($statusFilter); ?>&p=<?php echo $page + 1; ?>" class="btn btn-outline btn-sm">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<script nonce="<?php echo klytos_esc_attr($cspNonce); ?>">
function copyText(id) {
    var el = document.getElementById(id);
    if (!el) return;
    var text = el.textContent || el.innerText;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text.trim());
    } else {
        var ta = document.createElement('textarea');
        ta.value = text.trim();
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    }
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
