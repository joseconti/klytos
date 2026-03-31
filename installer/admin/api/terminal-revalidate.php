<?php

/**
 * Klytos Admin API -- Terminal 2FA Revalidation Endpoint
 * Verifies a 2FA code to resume the terminal session after inactivity.
 *
 * POST /admin/api/terminal-revalidate.php
 * Body: { "code": "123456", "method": "totp" }
 * Response: { "success": true }
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

// 4. Verify 2FA is active.
$currentUser = klytos_current_user();
if ( empty( $currentUser['two_factor']['enabled'] ) ) {
    Helpers::jsonResponse( [ 'error' => 'Requires 2FA' ], 403 );
}

// 5. Read code and method from JSON body.
$body   = json_decode( file_get_contents( 'php://input' ), true );
$code   = $body['code'] ?? '';
$method = $body['method'] ?? 'totp';

$twoFactor = $app->getTwoFactor();
$userId    = (string) ( $currentUser['id'] ?? $currentUser['_id'] ?? '' );

$verified = match ( $method ) {
    'totp'     => $twoFactor->verifyTotp( $currentUser['two_factor']['totp_secret'] ?? '', $code ),
    'recovery' => $twoFactor->verifyRecoveryCode( $userId, $code ),
    default    => false,
};

if ( $verified ) {
    $_SESSION['klytos_terminal_last_command'] = time();
    Helpers::jsonResponse( [ 'success' => true ] );
} else {
    Helpers::jsonResponse( [ 'success' => false, 'error' => 'Codigo 2FA invalido' ], 401 );
}
