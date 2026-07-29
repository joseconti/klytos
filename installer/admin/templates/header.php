<?php

/**
 * Klytos Admin — Header Template
 * Shared header for all admin pages.
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

use Klytos\Core\Auth;
use Klytos\Core\Helpers;

// Reuse the request's nonce rather than minting a second one: bootstrap.php
// already generated it and already sent the headers with it (NEW-14). Minting
// another here would put a nonce in the markup that the sent CSP does not
// name, and every inline script on the page would be refused. The fallback
// covers a caller that reaches this template without the bootstrap.
$cspNonce = $GLOBALS['klytos_csp_nonce'] ?? Auth::generateCspNonce();
$GLOBALS['klytos_csp_nonce'] = $cspNonce;

// Re-sent because a page may supply its own $customCsp; header() replaces a
// same-name header, so this call overrides the bootstrap's baseline policy.
Auth::sendSecurityHeaders($cspNonce, $customCsp ?? null);
$basePath    = Helpers::getBasePath();
$adminPath   = $basePath . 'admin/';
$pageTitle   = $pageTitle ?? __( 'dashboard.title' );
$pageTitle   = klytos_apply_filters('admin.page_title', $pageTitle);
$adminTheme  = $app->getSiteConfig()->getValue('admin_theme', 'dark');
$adminTheme  = klytos_apply_filters('admin.theme', $adminTheme);
$version   = $app->getVersion();
?>
<!DOCTYPE html>
<html lang="<?php echo $app->getI18n()->getLocale(); ?>" data-theme="<?php echo klytos_esc_attr($adminTheme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?php klytos_do_action('admin.head_meta', $cspNonce); ?>
    <title><?php echo klytos_esc_html( $pageTitle ); ?> — Klytos Admin</title>
    <link rel="stylesheet" href="<?php echo klytos_esc_url( $adminPath . 'assets/vendor/fontawesome/css/all.min.css' ); ?>">
    <link rel="stylesheet" href="<?php echo klytos_esc_url( $adminPath . 'assets/css/klytos-tokens.css' ); ?>?v=<?php echo klytos_esc_attr( $version ); ?>">
    <link rel="stylesheet" href="<?php echo klytos_esc_url( $adminPath . 'assets/css/klytos-base.css' ); ?>?v=<?php echo klytos_esc_attr( $version ); ?>">
    <?php
    /*
     * Design-handoff token layer (Phase 4 Step 4, stage 1 — docs/BUILD-SPEC.md §5.9).
     *
     * The order is NOT free: it is the load order SPEC/design-tokens.md §"Load order"
     * declares, and `klytos-admin.css` is LAST on purpose — it is the only Klytos-owned
     * file of the set and it carries real enforcement rules (:focus-visible, .k-hit-24,
     * reduced-motion, forced-colors), not just declarations. The delivered
     * `tokens/klytos-components.css` consumer follows immediately after, as the SPEC says.
     *
     * `tokens/platform.css` is delivered but deliberately NOT loaded: SPEC/design-tokens.md
     * lists "platform.css in full" under "Inherited and NOT used — do not implement" (native
     * densities and touch targets; the admin is a browser surface). It is kept on disk so the
     * set stays diffable against upstream.
     */
    $klytosDesignTokens = [
        'colors.css',
        'typography.css',
        'spacing.css',
        'effects.css',
        'motion.css',
        'glass.css',
        'fonts.css',
        'klytos-admin.css',
    ];
    $klytosDesignTokens = klytos_apply_filters( 'admin.design_tokens', $klytosDesignTokens );
    foreach ( $klytosDesignTokens as $tokenFile ) {
        echo '<link rel="stylesheet" href="'
            . klytos_esc_url( $adminPath . 'assets/css/tokens/' . $tokenFile )
            . '?v=' . klytos_esc_attr( $version ) . '">' . "\n    ";
    }
    ?>
<link rel="stylesheet" href="<?php echo klytos_esc_url( $adminPath . 'assets/css/klytos-components.css' ); ?>?v=<?php echo klytos_esc_attr( $version ); ?>">
    <link rel="stylesheet" href="<?php echo klytos_esc_url( $adminPath . 'assets/css/klytos-sidebar.css' ); ?>?v=<?php echo klytos_esc_attr( $version ); ?>">
    <link rel="stylesheet" href="<?php echo klytos_esc_url( $adminPath . 'assets/css/klytos-utilities.css' ); ?>?v=<?php echo klytos_esc_attr( $version ); ?>">
    <?php
    // Filter: plugins can add stylesheet URLs to this array.
    $adminStylesheets = klytos_apply_filters( 'admin.stylesheets', [] );
    foreach ( $adminStylesheets as $stylesheetUrl ) {
        echo '<link rel="stylesheet" href="' . klytos_esc_url( $stylesheetUrl ) . '">' . "\n    ";
    }
    ?>
<?php klytos_do_action('admin.head', $cspNonce); ?>
</head>
<body>
<div class="admin-layout">
