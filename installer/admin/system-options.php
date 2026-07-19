<?php

/**
 * Klytos Admin — Options Management
 * View and manage all stored options grouped by text domain.
 *
 * @package Klytos
 * @since   0.18.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 Jose Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\Helpers;

$pageTitle = __('options.title');

// ─── Permission check ────────────────────────────────────────
// Enforced centrally since Sprint 1 slice 4 — 'site.configure' in the gate
// map (core/admin-gate.php), refused by admin/bootstrap.php with a 403
// before this body runs, instead of a redirect to the dashboard.

$auth           = $app->getAuth();
$csrf           = $auth->getCsrfToken();
$optionsManager = $app->getOptionsManager();
$pluginLoader   = $app->getPluginLoader();

// Classify all options.
$domains    = $pluginLoader->getTextDomainsByStatus();
$classified = $optionsManager->classifyOptions($domains['active'], $domains['inactive']);

// Count per category.
$counts = [];
$totalOptions = 0;
foreach ($classified as $category => $domainGroups) {
    $count = 0;
    foreach ($domainGroups as $records) {
        $count += count($records);
    }
    $counts[$category] = $count;
    $totalOptions += $count;
}

// Active filter tab.
$activeFilter = $_GET['filter'] ?? 'all';
$validFilters = ['all', 'core', 'active', 'inactive', 'orphan', 'unknown'];
if (!in_array($activeFilter, $validFilters, true)) {
    $activeFilter = 'all';
}

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php klytos_do_action('admin.system_options.before'); ?>

<div class="content-header">
    <h1><?php echo klytos_esc_html($pageTitle); ?></h1>
    <p class="text-muted mt-1">
        <?php echo __('options.description'); ?>
    </p>
</div>

<!-- Filter tabs -->
<div class="tabs mb-3">
    <?php
    $tabs = [
        'all'      => ['label' => __('options.tab_all'),      'count' => $totalOptions],
        'core'     => ['label' => __('options.tab_core'),     'count' => $counts['core'] ?? 0],
        'active'   => ['label' => __('options.tab_active'),   'count' => $counts['active'] ?? 0],
        'inactive' => ['label' => __('options.tab_inactive'), 'count' => $counts['inactive'] ?? 0],
        'orphan'   => ['label' => __('options.tab_orphan'),   'count' => $counts['orphan'] ?? 0],
        'unknown'  => ['label' => __('options.tab_unknown'),  'count' => $counts['unknown'] ?? 0],
    ];
    foreach ($tabs as $tabKey => $tab): ?>
        <a href="?filter=<?php echo klytos_esc_attr($tabKey); ?>"
           class="tab <?php echo $activeFilter === $tabKey ? 'active' : ''; ?>">
            <?php echo klytos_esc_html($tab['label']); ?>
            <span class="badge"><?php echo (int) $tab['count']; ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Migration notice -->
<?php if (($counts['unknown'] ?? 0) > 0): ?>
<div class="alert alert-warning mb-3" id="migrate-notice">
    <strong><?php echo __('options.migrate_notice'); ?></strong>
    <button class="btn btn-sm btn-warning ml-2" id="btn-migrate">
        <?php echo __('options.migrate_button'); ?>
    </button>
</div>
<?php endif; ?>

<!-- Options table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Text Domain</th>
                    <th><?php echo __('options.col_key'); ?></th>
                    <th><?php echo __('options.col_value'); ?></th>
                    <th><?php echo __('options.col_created'); ?></th>
                    <th><?php echo __('options.col_updated'); ?></th>
                    <th class="text-right"><?php echo __('options.col_actions'); ?></th>
                </tr>
            </thead>
            <tbody id="options-tbody">
                <?php
                // Determine which groups to show.
                if ($activeFilter === 'all') {
                    $displayGroups = $classified;
                } else {
                    $displayGroups = [$activeFilter => $classified[$activeFilter] ?? []];
                }

                $hasRows = false;
                foreach ($displayGroups as $category => $domainGroups):
                    foreach ($domainGroups as $domain => $records):
                        foreach ($records as $record):
                            $hasRows = true;
                            $key   = $record['key'] ?? '';
                            $value = $record['value'] ?? null;

                            // Preview: truncate to 100 chars.
                            if (is_array($value)) {
                                $preview = '[array(' . count($value) . ')]';
                            } elseif (is_object($value)) {
                                $preview = '{object}';
                            } elseif (is_bool($value)) {
                                $preview = $value ? 'true' : 'false';
                            } elseif ($value === null) {
                                $preview = 'null';
                            } else {
                                $preview = (string) $value;
                                if (mb_strlen($preview) > 100) {
                                    $preview = mb_substr($preview, 0, 100) . '...';
                                }
                            }
                            ?>
                            <tr data-key="<?php echo klytos_esc_attr($key); ?>" data-domain="<?php echo klytos_esc_attr($domain); ?>">
                                <td>
                                    <span class="badge badge-<?php echo $category === 'orphan' ? 'danger' : ($category === 'unknown' ? 'warning' : 'default'); ?>">
                                        <?php echo klytos_esc_html($domain); ?>
                                    </span>
                                </td>
                                <td><code><?php echo klytos_esc_html($key); ?></code></td>
                                <td title="<?php echo klytos_esc_attr(is_scalar($value) ? (string) $value : json_encode($value)); ?>">
                                    <?php echo klytos_esc_html($preview); ?>
                                </td>
                                <td><?php echo klytos_esc_html($record['created_at'] ?? '-'); ?></td>
                                <td><?php echo klytos_esc_html($record['updated_at'] ?? '-'); ?></td>
                                <td class="text-right">
                                    <button class="btn btn-sm btn-danger js-delete-option" data-option-key="<?php echo klytos_esc_attr($key); ?>">
                                        <?php echo __('options.btn_delete'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach;
                    endforeach;
                endforeach;

                if (!$hasRows): ?>
                    <tr><td colspan="6" class="text-center text-muted p-3">
                        <?php echo __('options.no_options'); ?>
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php klytos_do_action('admin.system_options.after'); ?>

<script nonce="<?php echo klytos_esc_attr($cspNonce); ?>">
(function() {
    const csrfToken = '<?php echo klytos_esc_attr($csrf); ?>';
    const apiUrl    = '<?php echo klytos_esc_url($adminPath . 'api/options-management.php'); ?>';
    const confirmDeleteMsg  = '<?php echo klytos_esc_attr(__('options.confirm_delete')); ?>';
    const confirmDomainMsg  = '<?php echo klytos_esc_attr(__('options.confirm_delete_domain')); ?>';
    const migrateSuccessMsg = '<?php echo klytos_esc_attr(__('options.migrate_success')); ?>';

    // Delete individual option buttons
    document.querySelectorAll('.js-delete-option').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var key = this.getAttribute('data-option-key');
            if (!confirm(confirmDeleteMsg)) return;

            fetch(apiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                body: JSON.stringify({action: 'delete', key: key})
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var row = document.querySelector('tr[data-key="' + key + '"]');
                    if (row) row.remove();
                } else {
                    alert(data.error || 'Error');
                }
            });
        });
    });

    // Migrate button
    var migrateBtn = document.getElementById('btn-migrate');
    if (migrateBtn) {
        migrateBtn.addEventListener('click', function() {
            this.disabled = true;
            this.textContent = '...';

            fetch(apiUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                body: JSON.stringify({action: 'migrate'})
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    document.getElementById('migrate-notice').innerHTML =
                        '<strong>' + migrateSuccessMsg + ': ' + data.migrated + '</strong>';
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    alert(data.error || 'Error');
                    migrateBtn.disabled = false;
                }
            });
        });
    }
})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
