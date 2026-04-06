<?php
/**
 * Klytos — Front Controller
 * All requests are routed through this file via .htaccess rewrite rules.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

// Ensure minimum PHP version.
if ( version_compare( PHP_VERSION, '8.0.0', '<' ) ) {
    http_response_code( 500 );
    echo 'Klytos requires PHP 8.0 or higher. Current version: ' . PHP_VERSION;
    exit( 1 );
}

require_once __DIR__ . '/core/app.php';

use Klytos\Core\App;
use Klytos\Core\Auth;
use Klytos\Core\Router;

$app = App::getInstance();

// ─── Installation check ──────────────────────────────────────
// If not installed, show installer or return JSON error for API routes.
if ( ! $app->isInstalled() ) {
    $route = $_GET['route'] ?? '';

    // API routes: return JSON error (don't redirect to HTML installer).
    if ( in_array( $route, ['mcp', 'oauth/token', '.well-known/oauth-authorization-server'], true ) ) {
        http_response_code( 503 );
        header( 'Content-Type: application/json; charset=utf-8' );
        echo json_encode( [
            'error'   => 'not_installed',
            'message' => 'Klytos is not installed yet.',
        ] );
        exit;
    }

    // Show installer if it exists.
    if ( file_exists( __DIR__ . '/install.php' ) ) {
        require_once __DIR__ . '/install.php';
    } else {
        http_response_code( 500 );
        echo 'Klytos is not installed and the installer is missing.';
    }
    exit;
}

// ─── Boot the application ────────────────────────────────────
try {
    $app->boot();
} catch ( \Throwable $e ) {
    error_log( 'Klytos boot error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );

    $route = $_GET['route'] ?? '';
    // API routes: return JSON error.
    if ( $route === 'mcp' ) {
        http_response_code( 500 );
        header( 'Content-Type: application/json; charset=utf-8' );
        echo json_encode( [
            'jsonrpc' => '2.0',
            'error'   => ['code' => -32000, 'message' => 'Server boot error.'],
            'id'      => null,
        ] );
        exit;
    }

    http_response_code( 500 );
    echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
    echo '<div style="max-width:600px;margin:4rem auto;font-family:sans-serif;text-align:center">';
    echo '<h1 style="color:#dc2626">Klytos Error</h1>';
    echo '<p>The application failed to start. Check the PHP error log.</p>';
    echo '<pre style="background:#f1f5f9;padding:1rem;border-radius:8px;text-align:left;font-size:0.85rem;overflow:auto">';
    echo klytos_esc_html( $e->getMessage() );
    echo '</pre></div></body></html>';
    exit( 1 );
}

// Set security headers.
Auth::sendSecurityHeaders();

// Route the request.
$router = new Router( $app );
$router->dispatch();
