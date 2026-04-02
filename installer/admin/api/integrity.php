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
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 Jose Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

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
$checker = $app->getIntegrityChecker();

// ─── GET actions ─────────────────────────────────────────────
if ( $method === 'GET' ) {
    $action = $_GET['action'] ?? '';

    if ( $action === 'status' || $action === 'report' ) {
        $report = $checker->getLastReport();

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

switch ( $action ) {
    case 'verify':
        $report = $checker->verify( false );
        Helpers::jsonResponse( $report );
        break;

    case 'verify_force':
        $report = $checker->verify( true );
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
