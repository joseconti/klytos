<?php

/**
 * Klytos Admin — Plugin Page Router
 * Loads admin pages declared by plugins.
 *
 * URL: admin/plugin-page.php?plugin={id}&page={page}
 *
 * The plugin's PHP file is loaded from: plugins/{id}/admin/{page}.php
 * The plugin has access to $app, $currentUser, and all Klytos helpers.
 *
 * @package Klytos
 * @since   0.16.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

// ─── Validate parameters ─────────────────────────────────────

$pluginId = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $_GET['plugin'] ?? '' );
$pageName = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $_GET['page'] ?? '' );

if ( empty( $pluginId ) || empty( $pageName ) ) {
    http_response_code( 400 );
    $pageTitle = 'Error';
    require_once __DIR__ . '/templates/header.php';
    require_once __DIR__ . '/templates/sidebar.php';
    echo '<div class="alert alert-danger">Invalid parameters.</div>';
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

// ─── Verify plugin is active ──────────────────────────���──────

$pluginLoader  = $app->getPluginLoader();
$activePlugins = $pluginLoader->getActivePlugins();

if ( !isset( $activePlugins[$pluginId] ) ) {
    http_response_code( 404 );
    $pageTitle = __( 'common.not_found' );
    require_once __DIR__ . '/templates/header.php';
    require_once __DIR__ . '/templates/sidebar.php';
    echo '<div class="alert alert-danger">' . __( 'plugins.not_active' ) . ': '
       . klytos_esc_html( $pluginId ) . '</div>';
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

// ─── Verify page file exists ─────────────────────────────────

$pluginPagePath = klytos_plugin_path( $pluginId, 'admin/' . $pageName . '.php' );

if ( !file_exists( $pluginPagePath ) ) {
    http_response_code( 404 );
    $pageTitle = __( 'common.not_found' );
    require_once __DIR__ . '/templates/header.php';
    require_once __DIR__ . '/templates/sidebar.php';
    echo '<div class="alert alert-danger">' . __( 'plugins.page_not_found' ) . ': '
       . klytos_esc_html( $pageName ) . '</div>';
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

// ─── Capability check from manifest ──────────────────────────

$manifest   = $pluginLoader->getManifest( $pluginId );
$adminPages = $manifest['admin_pages'] ?? [];

$requiredCapability = null;
foreach ( $adminPages as $adminPage ) {
    if ( ( $adminPage['id'] ?? '' ) === $pageName ) {
        $requiredCapability = $adminPage['capability'] ?? null;
        break;
    }
    foreach ( ( $adminPage['children'] ?? [] ) as $child ) {
        if ( ( $child['id'] ?? '' ) === $pageName ) {
            $requiredCapability = $child['capability'] ?? ( $adminPage['capability'] ?? null );
            break 2;
        }
    }
}

// Allow plugins to override capability via filter.
$requiredCapability = klytos_apply_filters( 'admin.plugin_page_capability', $requiredCapability, $pluginId, $pageName );

if ( $requiredCapability !== null && !klytos_has_permission( $requiredCapability ) ) {
    http_response_code( 403 );
    $pageTitle = __( 'common.forbidden' );
    require_once __DIR__ . '/templates/header.php';
    require_once __DIR__ . '/templates/sidebar.php';
    echo '<div class="alert alert-danger">' . __( 'common.no_permission' ) . '</div>';
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

// ─── Set page context ────────────────────────────────────────

$pageTitle = $manifest['name'] ?? $pluginId;
$GLOBALS['klytos_admin_page'] = 'plugin.' . $pluginId . '.' . $pageName;

klytos_do_action( 'admin.plugin_page.before_render', $pluginId, $pageName );

// ─── Render with admin layout ────────────────────────────────

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';

// The plugin page runs with access to: $app, $auth, $pluginId, $pageName, $manifest
require_once $pluginPagePath;

require_once __DIR__ . '/templates/footer.php';

klytos_do_action( 'admin.plugin_page.after_render', $pluginId, $pageName );
