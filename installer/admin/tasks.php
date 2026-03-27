<?php
/**
 * Klytos Admin — Task Management
 * Review tasks and annotations: list, filter, complete, and manage.
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
use Klytos\Core\TaskManager;

$pageTitle   = 'Tasks';
$auth        = $app->getAuth();
$taskManager = new TaskManager($app->getStorage());
$success     = '';
$error       = '';
$csrf        = $auth->getCsrfToken();

// ─── Handle POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $taskId = $_POST['task_id'] ?? '';

    if ($action === 'complete' && !empty($taskId)) {
        try {
            $taskManager->complete($taskId);
            $success = 'Task marked as completed.';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'dismiss' && !empty($taskId)) {
        try {
            $taskManager->update($taskId, ['status' => 'dismissed']);
            $success = 'Task dismissed.';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'delete' && !empty($taskId)) {
        try {
            $taskManager->delete($taskId);
            $success = 'Task deleted.';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }

    $csrf = $auth->getCsrfToken();
}

// ─── Load data ───────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'open';
$tasks        = $taskManager->list($statusFilter);
$openCount    = $taskManager->count('open');
$progressCount = $taskManager->count('in_progress');
$completedCount = $taskManager->count('completed');
$totalCount   = $taskManager->count('all');

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars( $success ); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars( $error ); ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Open</div>
        <div class="stat-value" style="color:var(--admin-primary)"><?php echo $openCount; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">In Progress</div>
        <div class="stat-value" style="color:var(--admin-warning)"><?php echo $progressCount; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Completed</div>
        <div class="stat-value" style="color:var(--admin-success)"><?php echo $completedCount; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total</div>
        <div class="stat-value"><?php echo $totalCount; ?></div>
    </div>
</div>

<!-- Filter tabs -->
<div class="tabs">
    <a href="?status=open" class="tab <?php echo $statusFilter === 'open' ? 'active' : ''; ?>">Open (<?php echo $openCount; ?>)</a>
    <a href="?status=in_progress" class="tab <?php echo $statusFilter === 'in_progress' ? 'active' : ''; ?>">In Progress (<?php echo $progressCount; ?>)</a>
    <a href="?status=completed" class="tab <?php echo $statusFilter === 'completed' ? 'active' : ''; ?>">Completed (<?php echo $completedCount; ?>)</a>
    <a href="?status=all" class="tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All</a>
</div>

<!-- Tasks list -->
<?php if (empty($tasks)): ?>
    <div class="card">
        <div class="empty-state">
            <h3>No tasks found</h3>
            <p>Tasks are created via the front-end review widget or MCP tools.<br>
            Use <code>klytos_create_task</code> via MCP to create review tasks.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($tasks as $task): ?>
    <div class="card" style="border-left: 4px solid <?php
        echo match($task['priority'] ?? 'medium') {
            'urgent' => 'var(--admin-error)',
            'high'   => '#f97316',
            'medium' => 'var(--admin-primary)',
            'low'    => 'var(--admin-success)',
            default  => 'var(--admin-border)',
        };
    ?>;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem">
            <div style="flex:1">
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.4rem">
                    <span class="priority-dot <?php echo htmlspecialchars( $task['priority'] ?? 'medium'); ?>"></span>
                    <span class="badge-status badge-<?php echo htmlspecialchars( $task['priority'] ?? 'medium'); ?>">
                        <?php echo ucfirst( htmlspecialchars( $task['priority'] ?? 'medium')); ?>
                    </span>
                    <span class="badge-status badge-<?php echo htmlspecialchars( $task['status'] ?? 'open'); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars( $task['status'] ?? 'open'))); ?>
                    </span>
                </div>
                <p style="margin-bottom:0.4rem"><?php echo htmlspecialchars( $task['description'] ?? ''); ?></p>
                <div style="font-size:0.8rem;color:var(--admin-text-muted);display:flex;gap:1rem;flex-wrap:wrap">
                    <span>Page: <strong><?php echo htmlspecialchars( $task['page_slug'] ?? '—'); ?></strong></span>
                    <?php if (!empty($task['css_selector'])): ?>
                        <span>Element: <code style="font-size:0.75rem"><?php echo htmlspecialchars( $task['css_selector'] ); ?></code></span>
                    <?php endif; ?>
                    <span><?php echo $task['created_at'] ? date( 'M j, Y H:i', strtotime($task['created_at'])) : ''; ?></span>
                </div>
            </div>
            <div style="display:flex;gap:0.3rem;flex-shrink:0">
                <?php if (($task['status'] ?? '') !== 'completed' && ($task['status'] ?? '') !== 'dismissed'): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="task_id" value="<?php echo htmlspecialchars( $task['id'] ?? ''); ?>">
                        <button type="submit" class="btn btn-primary btn-sm" title="Complete">✓</button>
                    </form>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="dismiss">
                        <input type="hidden" name="task_id" value="<?php echo htmlspecialchars( $task['id'] ?? ''); ?>">
                        <button type="submit" class="btn btn-outline btn-sm" title="Dismiss">✕</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
