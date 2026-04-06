<?php

/**
 * Klytos Admin API — Integrity Endpoint
 * Handles integrity verification operations via AJAX.
 *
 * JSON actions (POST with JSON body + X-CSRF-Token header):
 *   { "action": "verify|verify_force|check_plugin" }
 *
 * GET actions:
 *   ?action=status   — Get last integrity report.
 *   ?action=report   — Get detailed integrity report.
 *
 * @package Klytos
 * @since   2.1.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 Jose Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

// Catch fatal errors that bypass try-catch.
ob_start();
register_shutdown_function( function () {
    $error = error_get_last();
    if ( $error !== null && ( $error['type'] & ( E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR ) ) ) {
        ob_end_clean();
        header( 'Content-Type: application/json; charset=utf-8' );
        http_response_code( 500 );
        echo json_encode( [
            'error'   => 'PHP fatal error',
            'message' => $error['message'],
            'file'    => basename( $error['file'] ),
            'line'    => $error['line'],
        ] );
    }
} );

require_once dirname( __DIR__ ) . '/bootstrap.php';

use Klytos\Core\Helpers;

header( 'Content-Type: application/json; charset=utf-8' );

// ─── Authentication ──────────────────────────────────────────
if ( !$app->getAuth()->isAuthenticated() ) {
    Helpers::jsonResponse( ['error' => 'Unauthorized'], 401 );
}

// ─── Permission check ────────────────────────────────────────
if ( !klytos_has_permission( 'site.configure' ) ) {
    Helpers::jsonResponse( ['error' => 'Forbidden'], 403 );
}

// ─── Rate limiting (10 operations per minute per session) ────
$now   = time();
$key   = 'klytos_integrity_api_rate';
$limit = 10;

if ( !isset( $_SESSION[$key] ) ) {
    $_SESSION[$key] = ['count' => 0, 'reset' => $now + 60];
}
if ( $_SESSION[$key]['reset'] < $now ) {
    $_SESSION[$key] = ['count' => 0, 'reset' => $now + 60];
}

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET actions ─────────────────────────────────────────────
if ( $method === 'GET' ) {
    $action = $_GET['action'] ?? '';

    if ( $action === 'status' || $action === 'report' ) {
        $checker = $app->getIntegrityChecker();
        $report  = $checker->getLastReport();

        if ( $report === null ) {
            Helpers::jsonResponse( [
                'status'  => 'no_data',
                'message' => 'No integrity check has been run yet.',
            ] );
        }

        Helpers::jsonResponse( $report );
    }

    Helpers::jsonResponse( ['error' => 'Invalid action'], 400 );
}

// ─── POST actions ────────────────────────────────────────────
if ( $method !== 'POST' ) {
    Helpers::jsonResponse( ['error' => 'Method not allowed'], 405 );
}

// CSRF verification.
if ( !klytos_verify_csrf() ) {
    Helpers::jsonResponse( ['error' => 'Invalid CSRF token'], 403 );
}

$_SESSION[$key]['count']++;
if ( $_SESSION[$key]['count'] > $limit ) {
    Helpers::jsonResponse( ['error' => 'Rate limit exceeded. Try again in a minute.'], 429 );
}

// Parse JSON body.
$input  = json_decode( file_get_contents( 'php://input' ), true ) ?? [];
$action = $input['action'] ?? $_POST['action'] ?? '';

// Integrity checks may take a while (downloading manifests + hashing files).
set_time_limit( 120 );

try {
    $checker = $app->getIntegrityChecker();

    switch ( $action ) {
        case 'verify':
        case 'verify_force':
            $force  = ( $action === 'verify_force' );
            $report = $checker->verify( $force );
            Helpers::jsonResponse( $report );
            break;

        case 'check_plugin':
            $pluginId = $input['plugin_id'] ?? '';
            if ( empty( $pluginId ) ) {
                Helpers::jsonResponse( ['error' => 'plugin_id is required'], 400 );
            }
            $forceRefresh = !empty( $input['force_refresh'] );
            $result = $checker->verifyOnePlugin( $pluginId, $forceRefresh );
            Helpers::jsonResponse( $result );
            break;

        default:
            Helpers::jsonResponse( ['error' => 'Invalid action'], 400 );
    }
} catch ( \Throwable $e ) {
    Helpers::jsonResponse( [
        'error'   => 'Integrity check failed',
        'message' => $e->getMessage(),
        'file'    => basename( $e->getFile() ),
        'line'    => $e->getLine(),
    ], 500 );
}
