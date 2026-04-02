<?php

/**
 * Klytos Admin — Plugins Management
 * Lists installed plugins with AJAX-powered actions: activate, deactivate, delete, bulk operations.
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


$pageTitle    = 'Plugins';
$auth         = $app->getAuth();
$pluginLoader = $app->getPluginLoader();

// ─── Get list of all plugins ─────────────────────────────────
$plugins = $pluginLoader->listAll();

// ─── Table columns (filterable) ──────────────────────────────
$columns = [
    'cb'      => '',
    'plugin'  => 'Plugin',
    'version' => 'Version',
    'author'  => 'Author',
    'type'    => 'Type',
    'status'  => __( 'common.status' ),
    'actions' => __( 'common.actions' ),
];
$columns = klytos_apply_filters( 'admin.plugins_columns', $columns );

// ─── Bulk actions (filterable) ───────────────────────────────
$bulkActions = [
    ''           => '— Select action —',
    'activate'   => 'Activate',
    'deactivate' => 'Deactivate',
    'delete'     => 'Delete',
];
$bulkActions = klytos_apply_filters( 'admin.plugins_page_actions', $bulkActions );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.plugins.before' ); ?>

<link rel="stylesheet" href="<?php echo klytos_esc_url( $adminPath . 'assets/css/klytos-plugins.css' ); ?>" nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">

<!-- Toast container -->
<div id="plugin-toast-container" class="plugin-toast-container"></div>

<!-- Marketplace link -->
<div class="card flex flex-between flex-center mb-3">
    <div>
        <strong>Plugin Marketplace</strong>
        <p class="text-muted text-sm" style="margin: 0;">
            Discover free and premium plugins to extend Klytos.
        </p>
    </div>
    <a href="https://klytos.io/plugins" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
        Browse Marketplace
    </a>
</div>

<!-- Installed plugins list -->
<div class="card" id="plugins-container" data-csrf="<?php echo klytos_esc_attr( $auth->getCsrfToken() ); ?>" data-api-url="<?php echo klytos_esc_url( $adminPath . 'api/plugins.php' ); ?>">
    <div class="card-header flex flex-between flex-center">
        <h3 style="margin:0;">Installed Plugins</h3>
        <button type="button" class="btn btn-primary btn-sm" id="plugin-install-btn">
            <i class="fa-solid fa-upload"></i> Install Plugin
        </button>
    </div>

    <?php if (empty($plugins)): ?>
        <p class="text-muted p-2">
            No plugins installed. Visit the <a href="https://klytos.io/plugins" target="_blank" rel="noopener">Marketplace</a> to find plugins.
        </p>
    <?php else: ?>
        <!-- Bulk action bar -->
        <div class="plugin-bulk-bar">
            <label>
                <input type="checkbox" id="plugin-select-all" class="plugin-checkbox" aria-label="Select all plugins">
                Select all
            </label>
            <select id="plugin-bulk-action" disabled aria-label="Bulk action">
                <?php foreach ($bulkActions as $value => $label): ?>
                    <option value="<?php echo klytos_esc_attr( $value ); ?>"><?php echo klytos_esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-outline btn-sm" id="plugin-bulk-apply" disabled>Apply</button>
        </div>

        <?php klytos_do_action( 'admin.plugins_before_table' ); ?>

        <div class="table-wrap">
            <table class="admin-table" id="plugins-table">
                <thead>
                    <tr>
                        <?php foreach ($columns as $key => $label): ?>
                            <?php if ($key === 'cb'): ?>
                                <th class="plugin-checkbox-cell"></th>
                            <?php else: ?>
                                <th><?php echo klytos_esc_html( $label ); ?></th>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="plugins-tbody">
                    <?php foreach ($plugins as $plugin): ?>
                        <?php
                        // Allow plugins to modify row data.
                        $plugin = klytos_apply_filters( 'admin.plugins_row_data', $plugin );

                        // Build row actions.
                        $rowActions = [];
                        if ($plugin['active']) {
                            $rowActions['deactivate'] = [
                                'label' => 'Deactivate',
                                'class' => 'btn btn-outline btn-sm',
                            ];
                        } else {
                            $rowActions['activate'] = [
                                'label' => 'Activate',
                                'class' => 'btn btn-primary btn-sm',
                            ];
                        }
                        $rowActions['restore'] = [
                            'label' => 'Restore',
                            'class' => 'btn btn-outline btn-sm',
                        ];
                        $rowActions['delete'] = [
                            'label' => 'Delete',
                            'class' => 'btn btn-danger btn-sm',
                        ];
                        if (!empty($plugin['logs'])) {
                            if ($plugin['logs_enabled']) {
                                $rowActions['disable_logs'] = [
                                    'label' => __( 'plugins.disable_logs' ),
                                    'class' => 'btn btn-outline btn-sm',
                                ];
                            } else {
                                $rowActions['enable_logs'] = [
                                    'label' => __( 'plugins.enable_logs' ),
                                    'class' => 'btn btn-outline btn-sm',
                                ];
                            }
                        }
                        $rowActions = klytos_apply_filters( 'admin.plugins_row_actions', $rowActions, $plugin['id'], $plugin );
                        ?>
                        <tr class="plugin-row" data-plugin="<?php echo klytos_esc_attr( $plugin['id'] ); ?>" data-plugin-name="<?php echo klytos_esc_attr( $plugin['name'] ); ?>">
                            <?php foreach ($columns as $key => $colLabel): ?>
                                <?php if ($key === 'cb'): ?>
                                    <td class="plugin-checkbox-cell">
                                        <input type="checkbox" class="plugin-checkbox" data-plugin="<?php echo klytos_esc_attr( $plugin['id'] ); ?>" aria-label="Select <?php echo klytos_esc_attr( $plugin['name'] ); ?>">
                                    </td>

                                <?php elseif ($key === 'plugin'): ?>
                                    <td>
                                        <span class="plugin-name"><?php echo klytos_esc_html( $plugin['name'] ); ?></span>
                                        <?php if (!empty($plugin['description'])): ?>
                                            <div class="plugin-description"><?php echo klytos_esc_html( $plugin['description'] ); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($plugin['error'])): ?>
                                            <div class="plugin-error">
                                                <i class="fa-solid fa-triangle-exclamation"></i>
                                                <?php echo klytos_esc_html( $plugin['error'] ); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (($plugin['discovery_method'] ?? '') === 'json_legacy'): ?>
                                            <div style="margin-top: 0.3rem;">
                                                <span class="plugin-badge-legacy">
                                                    <i class="fa-solid fa-triangle-exclamation"></i>
                                                    Legacy format — migrate to PHP header
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                <?php elseif ($key === 'version'): ?>
                                    <td><?php echo klytos_esc_html( $plugin['version'] ); ?></td>

                                <?php elseif ($key === 'author'): ?>
                                    <td>
                                        <?php if (!empty($plugin['author_url'])): ?>
                                            <a href="<?php echo klytos_esc_url( $plugin['author_url'] ); ?>" target="_blank" rel="noopener">
                                                <?php echo klytos_esc_html( $plugin['author'] ); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo klytos_esc_html( $plugin['author'] ); ?>
                                        <?php endif; ?>
                                    </td>

                                <?php elseif ($key === 'type'): ?>
                                    <td>
                                        <?php if ($plugin['premium']): ?>
                                            <span class="badge-status badge-premium">Premium</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-active">Free</span>
                                        <?php endif; ?>
                                    </td>

                                <?php elseif ($key === 'status'): ?>
                                    <td>
                                        <?php if ($plugin['active']): ?>
                                            <span class="badge-status badge-active plugin-status-badge">Active</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-inactive plugin-status-badge">Inactive</span>
                                        <?php endif; ?>
                                    </td>

                                <?php elseif ($key === 'actions'): ?>
                                    <td>
                                        <div class="plugin-actions">
                                            <?php foreach ($rowActions as $actionKey => $actionDef): ?>
                                                <button type="button"
                                                    class="<?php echo klytos_esc_attr( $actionDef['class'] ); ?>"
                                                    data-action="<?php echo klytos_esc_attr( $actionKey ); ?>"
                                                    data-plugin="<?php echo klytos_esc_attr( $plugin['id'] ); ?>">
                                                    <?php echo klytos_esc_html( $actionDef['label'] ); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>

                                <?php else: ?>
                                    <?php // Custom column added by plugins. ?>
                                    <td><?php klytos_do_action( 'admin.plugins_column_' . $key, $plugin ); ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php klytos_do_action( 'admin.plugins_after_table' ); ?>

    <?php endif; ?>
</div>

<!-- Delete confirmation modal -->
<div id="plugin-delete-modal" class="plugin-delete-modal" role="dialog" aria-modal="true" aria-labelledby="plugin-modal-title">
    <div class="plugin-delete-modal-content">
        <div class="plugin-delete-modal-header">
            <h3 id="plugin-modal-title">Delete plugin</h3>
            <button type="button" class="plugin-delete-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="plugin-delete-modal-body">
            <p>Are you sure you want to delete <strong id="plugin-modal-name"></strong>?</p>
            <p class="text-muted text-sm">
                This action will remove all plugin files. Plugin data will be kept unless you check the option below.
            </p>
            <label>
                <input type="checkbox" id="plugin-delete-data">
                Also delete plugin data (cannot be undone)
            </label>
        </div>
        <div class="plugin-delete-modal-footer">
            <button type="button" class="btn btn-outline" id="plugin-modal-cancel">Cancel</button>
            <button type="button" class="btn btn-danger" id="plugin-modal-confirm">Delete</button>
        </div>
    </div>
</div>

<!-- Install plugin modal -->
<div id="plugin-install-modal" class="plugin-delete-modal" role="dialog" aria-modal="true" aria-labelledby="plugin-install-modal-title">
    <div class="plugin-delete-modal-content">
        <div class="plugin-delete-modal-header">
            <h3 id="plugin-install-modal-title">Install Plugin</h3>
            <button type="button" class="plugin-delete-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="plugin-delete-modal-body">
            <p>Upload a plugin ZIP file to install or update a plugin.</p>
            <p class="text-muted text-sm">
                If the plugin already exists, a backup of the current version will be created automatically before updating.
            </p>
            <div class="plugin-upload-area" id="plugin-upload-area">
                <i class="fa-solid fa-cloud-arrow-up text-muted mb-1" style="font-size:2rem;"></i>
                <p class="text-sm" style="margin:0;">Drop one or more ZIP files here, or click to browse</p>
                <input type="file" id="plugin-install-file" accept=".zip" multiple class="hidden">
            </div>
            <div id="plugin-install-file-list" class="plugin-install-file-list hidden"></div>
            <div id="plugin-install-progress" class="hidden mt-2"></div>
        </div>
        <div class="plugin-delete-modal-footer">
            <button type="button" class="btn btn-outline" id="plugin-install-cancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="plugin-install-confirm" disabled>Install</button>
        </div>
    </div>
</div>

<!-- Restore plugin modal -->
<div id="plugin-restore-modal" class="plugin-delete-modal" role="dialog" aria-modal="true" aria-labelledby="plugin-restore-modal-title">
    <div class="plugin-delete-modal-content">
        <div class="plugin-delete-modal-header">
            <h3 id="plugin-restore-modal-title">Restore Plugin</h3>
            <button type="button" class="plugin-delete-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="plugin-delete-modal-body">
            <p>Select a backup to restore for <strong id="plugin-restore-name"></strong>:</p>
            <div id="plugin-restore-list" style="max-height:300px;overflow-y:auto;">
                <p class="text-muted">Loading backups...</p>
            </div>
        </div>
        <div class="plugin-delete-modal-footer">
            <button type="button" class="btn btn-outline" id="plugin-restore-cancel">Cancel</button>
        </div>
    </div>
</div>

<script src="<?php echo klytos_esc_url( $adminPath . 'assets/js/klytos-plugins.js' ); ?>" nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"></script>
<?php klytos_do_action( 'admin.plugins_page_scripts', $cspNonce ); ?>

<?php klytos_do_action( 'admin.plugins.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
