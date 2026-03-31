<?php
/**
 * Klytos Admin API -- Terminal Endpoint
 * Receives commands from the web terminal and returns output.
 * Only accessible via POST with active admin session, 2FA enabled,
 * and terminal.access permission.
 *
 * POST /admin/api/terminal.php
 * Body: { "command": "build" }
 * Response: { "success": true, "output": "...", "timestamp": 1234567890 }
 *
 * @package Klytos
 * @since   0.12.0
 *
 * @license    Elastic License 2.0 (ELv2) -- https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 Jose Conti -- https://plugins.joseconti.com -- https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/bootstrap.php';

use Klytos\Core\Helpers;

header( 'Content-Type: application/json; charset=utf-8' );

// 1. Only POST.
if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
    Helpers::jsonResponse( [ 'error' => 'Method not allowed' ], 405 );
}

// 2. Require authentication.
if ( ! $app->getAuth()->isAuthenticated() ) {
    Helpers::jsonResponse( [ 'error' => 'Unauthorized' ], 401 );
}

// 3. Verify CSRF.
if ( ! klytos_verify_csrf() ) {
    Helpers::jsonResponse( [ 'error' => 'Invalid CSRF token' ], 403 );
}

// 4. Verify terminal.access permission.
if ( ! klytos_has_permission( 'terminal.access' ) ) {
    Helpers::jsonResponse( [ 'error' => 'Insufficient permissions' ], 403 );
}

// 5. Verify 2FA is active.
$currentUser = klytos_current_user();
if ( empty( $currentUser['two_factor']['enabled'] ) ) {
    Helpers::jsonResponse( [ 'error' => 'Terminal requiere autenticacion de dos factores activa.' ], 403 );
}

// 6. Read command from JSON body.
$body    = json_decode( file_get_contents( 'php://input' ), true );
$command = $body['command'] ?? '';

if ( ! is_string( $command ) || trim( $command ) === '' ) {
    Helpers::jsonResponse( [ 'success' => false, 'output' => '', 'timestamp' => time() ] );
}

// 7. Execute via TerminalExecutor.
$executor = $app->getTerminalExecutor();
$userId   = (string) ( $currentUser['id'] ?? $currentUser['_id'] ?? 'unknown' );
$result   = $executor->execute( $command, $userId );

// 8. Respond.
Helpers::jsonResponse( $result );
