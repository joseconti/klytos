<?php
/**
 * Klytos Admin — Bootstrap
 * Common initialization for all admin pages.
 * Include this at the top of every admin page.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

$rootPath = dirname( __DIR__ );

// ─── Define __() translation function ONCE ───────────────────
// This function reads from $GLOBALS['klytos_i18n'].
// Before boot(): klytos_i18n is null → returns fallback label.
// After boot(): klytos_i18n is the real I18n instance → returns translation.
if ( ! function_exists( '__' ) ) {
    function __( string $key, array $replacements = [] ): string {
        if ( isset( $GLOBALS['klytos_i18n'] ) && $GLOBALS['klytos_i18n'] !== null ) {
            return $GLOBALS['klytos_i18n']->get( $key, $replacements );
        }
        $parts = explode( '.', $key );
        $label = ucfirst( str_replace( '_', ' ', end( $parts ) ) );
        foreach ( $replacements as $k => $v ) {
            $label = str_replace( '{' . $k . '}', (string) $v, $label );
        }
        return $label;
    }
}

// ─── Determine install URL before loading anything ───────────
// This must work even if core files fail to load.
$installUrl = dirname( $_SERVER['SCRIPT_NAME'] ) . '/../install.php';
$installUrl = str_replace( '//', '/', $installUrl );

// ─── Check if Klytos is installed (lightweight check) ────────
// Before even loading the App class, check if the encryption key exists.
// If it doesn't, the system is not installed → redirect to installer.
$configPath = $rootPath . '/config';
if ( ! file_exists( $configPath . '/.encryption_key' ) || ! file_exists( $configPath . '/config.json.enc' ) ) {
    header( 'Location: ' . $installUrl );
    exit;
}

// ─── Load the application ────────────────────────────────────
try {
    require_once $rootPath . '/core/app.php';
} catch ( \Throwable $e ) {
    error_log( 'Klytos: failed to load core — ' . $e->getMessage() );
    header( 'Location: ' . $installUrl );
    exit;
}

use Klytos\Core\App;
use Klytos\Core\Helpers;

try {
    $app = App::getInstance();

    if ( ! $app->isInstalled() ) {
        header( 'Location: ' . $installUrl );
        exit;
    }

    $app->boot();
} catch ( \Throwable $e ) {
    error_log( 'Klytos boot error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
    error_log( 'Klytos boot trace: ' . $e->getTraceAsString() );

    http_response_code( 500 );
    echo '<!DOCTYPE html><html><head><title>Klytos Error</title></head><body>';
    echo '<div style="max-width:600px;margin:4rem auto;font-family:sans-serif;text-align:center">';
    echo '<h1 style="color:#dc2626">Klytos Boot Error</h1>';
    echo '<p>The application failed to start. Check the PHP error log for details.</p>';
    echo '<pre style="background:#f1f5f9;padding:1rem;border-radius:8px;text-align:left;font-size:0.85rem;overflow:auto">';
    echo htmlspecialchars( $e->getMessage() );
    echo '</pre>';
    echo '</div></body></html>';
    exit( 1 );
}

// ─── Start admin session ─────────────────────────────────────
$app->getAuth()->startSession();

// ─── Run pseudo-cron (non-blocking) ──────────────────────────
try {
    $app->getCronManager()->runDueTasks();
} catch ( \Throwable $e ) {
    error_log( 'Klytos cron error: ' . $e->getMessage() );
}

// ─── Auth guard ──────────────────────────────────────────────
// If not authenticated and not on login page, redirect to login.
$currentScript = basename( $_SERVER['SCRIPT_NAME'] );
if ( $currentScript !== 'login.php' && $currentScript !== 'logout.php' ) {
    if ( ! $app->getAuth()->isAuthenticated() ) {
        $loginUrl = dirname( $_SERVER['SCRIPT_NAME'] ) . '/login.php';
        header( 'Location: ' . $loginUrl );
        exit;
    }
}
