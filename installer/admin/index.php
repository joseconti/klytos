<?php

/**
 * Klytos Admin — Dashboard
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pageTitle  = __( 'dashboard.title' );
$auth       = $app->getAuth();
$siteConfig = $app->getSiteConfig()->get();
$pageCount  = $app->getPages()->count('all');
$published  = $app->getPages()->count('published');
$drafts     = $app->getPages()->count('draft');
$tokens     = $app->getAuth()->listBearerTokens();
$lastBuild  = $siteConfig['last_build'] ?? null;

$indexingEnabled = $siteConfig['indexing_enabled'] ?? false;

// Handle indexing toggle action.
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $action = $_POST['action'] ?? '';
    if ( $action === 'disable_block' ) {
        $app->getSiteConfig()->set( ['indexing_enabled' => true] );
        $indexingEnabled = true;
        $siteConfig['indexing_enabled'] = true;
    } elseif ( $action === 'enable_block' ) {
        $app->getSiteConfig()->set( ['indexing_enabled' => false] );
        $indexingEnabled = false;
        $siteConfig['indexing_enabled'] = false;
    }
}

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
klytos_do_action( 'admin.dashboard.before' );

// Indexing warning banner is now rendered automatically by the Notice API.
// Only the toggle action form remains here.
if ( ! $indexingEnabled ) : ?>
<form method="post" class="mb-md flex-end">
    <?php echo klytos_csrf_field(); ?>
    <input type="hidden" name="action" value="disable_block">
    <button type="submit" class="btn btn-primary btn-sm">
        <?php echo __( 'indexing.disable_block' ); ?>
    </button>
</form>
<?php else : ?>
<form method="post" class="mb-md flex-end">
    <?php echo klytos_csrf_field(); ?>
    <input type="hidden" name="action" value="enable_block">
    <button type="submit" class="btn btn-outline btn-sm">
        <?php echo __( 'indexing.enable_block' ); ?>
    </button>
</form>
<?php endif; ?>

<?php klytos_do_action('admin.dashboard.before_stats'); ?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label"><?php echo __( 'dashboard.total_pages' ); ?></div>
        <div class="stat-value"><?php echo $pageCount; ?></div>
        <div class="stat-detail"><?php echo $published; ?> <?php echo __( 'pages.published' ); ?> / <?php echo $drafts; ?> <?php echo __( 'pages.draft' ); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-label"><?php echo __( 'dashboard.last_build' ); ?></div>
        <div class="stat-value text-base">
            <?php echo $lastBuild ? date( 'Y-m-d H:i', strtotime($lastBuild)) : '—'; ?>
        </div>
        <div class="stat-detail"><?php echo $lastBuild ? 'UTC' : __( 'pages.no_pages' ); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-label"><?php echo __( 'dashboard.mcp_status' ); ?></div>
        <div class="stat-value text-base">
            <span class="badge-status badge-<?php echo count( $tokens) > 0 ? 'active' : 'inactive'; ?>">
                <?php echo count( $tokens) > 0 ? __( 'common.status' ) . ': OK' : __( 'mcp.no_tokens' ); ?>
            </span>
        </div>
        <div class="stat-detail"><?php echo count( $tokens); ?> <?php echo __( 'mcp.tokens' ); ?></div>
    </div>

    <div class="stat-card">
        <div class="stat-label"><?php echo __( 'dashboard.klytos_version' ); ?></div>
        <div class="stat-value text-base">
            v<?php echo klytos_esc_html( $app->getVersion()); ?>
        </div>
        <div class="stat-detail">PHP <?php echo PHP_VERSION; ?></div>
    </div>
</div>
<?php klytos_do_action('admin.dashboard.after_stats'); ?>

<?php
// ─── Dashboard Widgets ────────────────────────────────────────
// Fire init action so plugins can register widgets before rendering.
klytos_do_action( 'admin.dashboard.init' );

// Register core widgets.
klytos_register_dashboard_widget(
    'quick_actions',
    __( 'dashboard.quick_actions' ),
    function () {
        ?>
        <div class="flex-col flex-gap-sm">
            <a href="pages.php" class="btn btn-primary"><?php echo __( 'pages.create_page' ); ?></a>
            <a href="theme.php" class="btn btn-outline"><?php echo __( 'theme.title' ); ?></a>
            <a href="mcp.php" class="btn btn-outline"><?php echo __( 'mcp.create_token' ); ?></a>
            <a href="ai-images.php" class="btn btn-outline"><?php echo __( 'ai_images.generate' ); ?></a>
        </div>
        <?php
    },
    10
);

klytos_register_dashboard_widget(
    'system_info',
    __( 'dashboard.system_info' ),
    function () use ( $app ) {
        ?>
        <table>
            <tr><td class="font-bold"><?php echo __( 'dashboard.klytos_version' ); ?></td><td><?php echo klytos_esc_html( $app->getVersion() ); ?></td></tr>
            <tr><td class="font-bold"><?php echo __( 'dashboard.php_version' ); ?></td><td><?php echo PHP_VERSION; ?></td></tr>
            <tr><td class="font-bold"><?php echo __( 'dashboard.server' ); ?></td><td><?php echo klytos_esc_html( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ); ?></td></tr>
        </table>
        <?php
    },
    20
);

// Get per-user widget visibility preferences.
$currentUser     = klytos_current_user();
$currentUserId   = $currentUser['id'] ?? '';
$widgetPrefs     = $currentUserId ? ( klytos_get_meta( 'user', $currentUserId, 'dashboard_widgets' ) ?: [] ) : [];

klytos_do_action( 'admin.dashboard.before_widgets' );
$allWidgets = klytos_get_dashboard_widgets();

if ( !empty( $allWidgets ) ) : ?>
<div class="grid-2">
    <?php foreach ( $allWidgets as $widget ) :
        // Skip if user has explicitly hidden this widget.
        if ( isset( $widgetPrefs[$widget['id']] ) && $widgetPrefs[$widget['id']] === false ) {
            continue;
        }
        // Skip if capability check fails.
        if ( $widget['capability'] !== null && !klytos_has_permission( $widget['capability'] ) ) {
            continue;
        }
    ?>
    <div class="card" data-widget-id="<?php echo klytos_esc_attr( $widget['id'] ); ?>">
        <div class="card-header">
            <h3><?php echo klytos_esc_html( $widget['title'] ); ?></h3>
        </div>
        <?php call_user_func( $widget['callback'] ); ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php klytos_do_action( 'admin.dashboard.after_widgets' ); ?>

<?php klytos_do_action( 'admin.dashboard.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
