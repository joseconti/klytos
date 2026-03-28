<?php
/**
 * Klytos Admin — Plugins Management
 * Lists installed plugins, allows activation/deactivation, and shows the marketplace link.
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

$pageTitle    = 'Plugins';
$auth         = $app->getAuth();
$pluginLoader = $app->getPluginLoader();
$success      = '';
$error        = '';

// ─── Handle POST actions (activate / deactivate) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf($_POST['csrf'] ?? '')) {
    $action   = $_POST['action'] ?? '';
    $pluginId = $_POST['plugin_id'] ?? '';

    // Sanitize plugin ID to prevent injection.
    $pluginId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $pluginId);

    if ($action === 'activate' && !empty($pluginId)) {
        $result = $pluginLoader->activate($pluginId);
        if ($result['success']) {
            $success = "Plugin '{$pluginId}' activated successfully.";
        } else {
            $error = "Error activating plugin: " . ($result['error'] ?? 'Unknown error');
        }
    } elseif ($action === 'deactivate' && !empty($pluginId)) {
        $result = $pluginLoader->deactivate($pluginId);
        if ($result['success']) {
            $success = "Plugin '{$pluginId}' deactivated.";
        } else {
            $error = "Error deactivating plugin: " . ($result['error'] ?? 'Unknown error');
        }
    }
}

// ─── Get list of all plugins ─────────────────────────────────
$plugins = $pluginLoader->listAll();
$csrf    = $auth->getCsrfToken();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars( $success ); ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars( $error ); ?></div>
<?php endif; ?>

<!-- Marketplace link -->
<div class="card" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <strong>Plugin Marketplace</strong>
        <p style="color: var(--admin-text-muted); font-size: 0.85rem; margin: 0;">
            Discover free and premium plugins to extend Klytos.
        </p>
    </div>
    <a href="https://klytos.io/plugins" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
        Browse Marketplace
    </a>
</div>

<!-- Installed plugins list -->
<div class="card">
    <div class="card-header">
        <h3>Installed Plugins</h3>
    </div>

    <?php if (empty($plugins)): ?>
        <p style="color: var(--admin-text-muted); padding: 1rem 0;">
            No plugins installed. Visit the <a href="https://klytos.io/plugins" target="_blank" rel="noopener">Marketplace</a> to find plugins.
        </p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Plugin</th>
                    <th>Version</th>
                    <th>Author</th>
                    <th>Type</th>
                    <th><?php echo __( 'common.status' ); ?></th>
                    <th><?php echo __( 'common.actions' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plugins as $plugin): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars( $plugin['name'] ); ?></strong>
                        <br>
                        <small style="color: var(--admin-text-muted);">
                            <?php echo htmlspecialchars( $plugin['description'] ); ?>
                        </small>
                        <?php if (!empty($plugin['error'])): ?>
                            <br>
                            <small style="color: var(--admin-error);">
                                Error: <?php echo htmlspecialchars( $plugin['error'] ); ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars( $plugin['version'] ); ?></td>
                    <td>
                        <?php if (!empty($plugin['author_url'])): ?>
                            <a href="<?php echo htmlspecialchars( $plugin['author_url'] ); ?>" target="_blank" rel="noopener">
                                <?php echo htmlspecialchars( $plugin['author'] ); ?>
                            </a>
                        <?php else: ?>
                            <?php echo htmlspecialchars( $plugin['author'] ); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($plugin['premium']): ?>
                            <span class="badge-status badge-premium">Premium</span>
                        <?php else: ?>
                            <span class="badge-status badge-active">Free</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($plugin['active']): ?>
                            <span class="badge-status badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge-status badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($plugin['active']): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="plugin_id" value="<?php echo htmlspecialchars( $plugin['id'] ); ?>">
                                <button type="submit" class="btn btn-outline btn-sm">Deactivate</button>
                            </form>
                        <?php else: ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="plugin_id" value="<?php echo htmlspecialchars( $plugin['id'] ); ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Activate</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
