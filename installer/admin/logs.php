<?php

/**
 * Klytos Admin — Log Viewer
 * Displays debug log files with filtering, search, and management.
 *
 * Logs are stored in a secret directory inside data/ and are only
 * generated when Developer Mode is active. Plugins must declare
 * "Logs: true" in their header and have logging enabled.
 *
 * @package Klytos
 * @since   0.16.0
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

$pageTitle = __( 'logs.title' );

// ─── Permission check ────────────────────────────────────────
if (!klytos_has_permission( 'site.configure' )) {
    header( 'Location: ' . Helpers::url( 'admin/' ) );
    exit;
}

$logger      = $app->getLogger();
$auth        = $app->getAuth();
$isDevMode   = $app->isDevMode();
$success     = '';

// ─── Handle POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf()) {
    $postAction = $_POST['log_action'] ?? '';

    if ($postAction === 'delete' && !empty( $_POST['file'] )) {
        $file = basename( $_POST['file'] );
        if ($logger->deleteLogFile( $file )) {
            $success = __( 'logs.file_deleted' );
        }
    }

    if ($postAction === 'delete_all') {
        $count   = $logger->deleteAllLogFiles();
        $success = __( 'logs.all_deleted' );
    }
}

// ─── Get data ────────────────────────────────────────────────
$logFiles     = $logger->listLogFiles();
$logFiles     = klytos_apply_filters( 'admin.logs_file_list', $logFiles );
$selectedFile = basename( $_GET['file'] ?? '' );
$filterLevel  = $_GET['level'] ?? '';
$searchQuery  = $_GET['search'] ?? '';
$logLines     = [];

if ($selectedFile !== '') {
    if ($filterLevel !== '' || $searchQuery !== '') {
        $logLines = $logger->searchLogs( $selectedFile, $searchQuery, $filterLevel ?: null );
    } else {
        $logLines = $logger->readLogFile( $selectedFile, 0, 0 );
    }
}

$levels = \Klytos\Core\Logger::LEVELS;

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.logs.before' ); ?>

<link rel="stylesheet" href="<?php echo klytos_esc_url( $adminPath . 'assets/css/klytos-logs.css' ); ?>" nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">

<?php if (!$isDevMode): ?>
    <div class="card" style="margin-bottom: 1.5rem; background: rgba(245, 158, 11, 0.1); border-color: #f59e0b;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-triangle-exclamation" style="color: #f59e0b; font-size: 1.2rem;"></i>
            <div>
                <strong><?php echo __( 'logs.dev_mode_off_title' ); ?></strong>
                <p style="color: var(--admin-text-muted); font-size: 0.85rem; margin: 0.25rem 0 0;">
                    <?php echo __( 'logs.dev_mode_off_desc' ); ?>
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="card" style="margin-bottom: 1rem; background: rgba(34, 197, 94, 0.1); border-color: #22c55e; padding: 0.75rem 1rem;">
        <i class="fa-solid fa-check-circle" style="color: #22c55e;"></i>
        <?php echo klytos_esc_html( $success ); ?>
    </div>
<?php endif; ?>

<div class="logs-layout">
    <!-- File list panel -->
    <div class="logs-file-list">
        <div class="logs-file-list-header">
            <h3><i class="fa-solid fa-scroll"></i> <?php echo __( 'logs.files' ); ?></h3>
            <?php if (!empty( $logFiles )): ?>
                <form method="post" style="margin: 0;">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="log_action" value="delete_all">
                    <button type="submit" class="btn btn-danger btn-sm" id="logs-delete-all">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div class="logs-file-list-body">
            <?php if (empty( $logFiles )): ?>
                <div class="logs-empty-list">
                    <i class="fa-solid fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                    <?php echo __( 'logs.no_files' ); ?>
                </div>
            <?php else: ?>
                <?php foreach ($logFiles as $file): ?>
                    <a href="?file=<?php echo klytos_esc_attr( urlencode( $file['name'] ) ); ?>"
                       class="logs-file-item <?php echo $selectedFile === $file['name'] ? 'active' : ''; ?>">
                        <span class="logs-file-name"><?php echo klytos_esc_html( $file['name'] ); ?></span>
                        <span class="logs-file-size"><?php echo klytos_esc_html( $file['size_formatted'] ); ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Log viewer panel -->
    <div class="logs-viewer">
        <?php if ($selectedFile !== ''): ?>
            <div class="logs-viewer-toolbar">
                <?php echo klytos_apply_filters( 'admin.logs_toolbar', '' ); ?>
                <select id="logs-level-filter" onchange="filterLogs()">
                    <option value=""><?php echo __( 'logs.all_levels' ); ?></option>
                    <?php foreach ($levels as $lvl): ?>
                        <option value="<?php echo klytos_esc_attr( $lvl ); ?>" <?php echo $filterLevel === $lvl ? 'selected' : ''; ?>>
                            <?php echo klytos_esc_html( strtoupper( $lvl ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="logs-search" placeholder="<?php echo klytos_esc_attr( __( 'logs.search' ) ); ?>" value="<?php echo klytos_esc_attr( $searchQuery ); ?>">
                <button type="button" class="btn btn-outline btn-sm" id="logs-apply-filter">
                    <i class="fa-solid fa-filter"></i> <?php echo __( 'logs.filter' ); ?>
                </button>
                <form method="post" style="margin: 0; margin-left: auto;">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="log_action" value="delete">
                    <input type="hidden" name="file" value="<?php echo klytos_esc_attr( $selectedFile ); ?>">
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-trash"></i> <?php echo __( 'logs.delete_file' ); ?>
                    </button>
                </form>
            </div>
            <div class="logs-viewer-content">
                <?php if (empty( $logLines )): ?>
                    <div class="logs-viewer-empty">
                        <?php echo __( 'logs.empty_file' ); ?>
                    </div>
                <?php else: ?>
                    <pre><?php
                    foreach ($logLines as $line) {
                        $levelClass = 'log-line';
                        // Detect level from the line format: [date] [LEVEL] [source] message
                        if (preg_match( '/\[(EMERGENCY|ALERT|CRITICAL|ERROR|WARNING|NOTICE|INFO|DEBUG)\]/', $line, $m )) {
                            $levelClass .= ' log-line-' . strtolower( $m[1] );
                        }
                        echo '<div class="' . klytos_esc_attr( $levelClass ) . '">' . klytos_esc_html( $line ) . '</div>';
                    }
                    ?></pre>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="logs-viewer-empty">
                <div style="text-align: center;">
                    <i class="fa-solid fa-scroll" style="font-size: 3rem; margin-bottom: 1rem; display: block; opacity: 0.3;"></i>
                    <?php echo __( 'logs.select_file' ); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function() {
    var applyBtn = document.getElementById('logs-apply-filter');
    if (applyBtn) {
        applyBtn.addEventListener('click', filterLogs);
    }

    var searchInput = document.getElementById('logs-search');
    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                filterLogs();
            }
        });
    }

    var deleteAll = document.getElementById('logs-delete-all');
    if (deleteAll) {
        deleteAll.closest('form').addEventListener('submit', function(e) {
            if (!confirm('<?php echo __( 'logs.delete_all_confirm' ); ?>')) {
                e.preventDefault();
            }
        });
    }
})();

function filterLogs() {
    var level  = document.getElementById('logs-level-filter').value;
    var search = document.getElementById('logs-search').value;
    var url    = new URL(window.location.href);

    if (level) {
        url.searchParams.set('level', level);
    } else {
        url.searchParams.delete('level');
    }
    if (search) {
        url.searchParams.set('search', search);
    } else {
        url.searchParams.delete('search');
    }

    window.location.href = url.toString();
}
</script>

<?php klytos_do_action( 'admin.logs.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
