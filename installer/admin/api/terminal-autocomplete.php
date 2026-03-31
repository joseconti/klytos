<?php
/**
 * Klytos Admin API -- Terminal Autocomplete Endpoint
 * Returns matching command names for Tab-completion in the web terminal.
 *
 * GET /admin/api/terminal-autocomplete.php?q=bui
 * Response: { "suggestions": ["build", "build:page"] }
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

// 1. Only GET.
if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'GET' ) {
    Helpers::jsonResponse( [ 'error' => 'Method not allowed' ], 405 );
}

// 2. Require authentication.
if ( ! $app->getAuth()->isAuthenticated() ) {
    Helpers::jsonResponse( [ 'error' => 'Unauthorized' ], 401 );
}

// 3. Verify terminal.access permission.
if ( ! klytos_has_permission( 'terminal.access' ) ) {
    Helpers::jsonResponse( [ 'error' => 'Forbidden' ], 403 );
}

// 4. Verify 2FA is active.
$currentUser = klytos_current_user();
if ( empty( $currentUser['two_factor']['enabled'] ) ) {
    Helpers::jsonResponse( [ 'error' => 'Requires 2FA' ], 403 );
}

// 5. Filter commands by query prefix.
$query        = strtolower( trim( $_GET['q'] ?? '' ) );
$executor     = $app->getTerminalExecutor();
$commandNames = array_keys( $executor->getCommands() );

if ( $query === '' ) {
    $suggestions = $commandNames;
} else {
    $suggestions = array_values( array_filter(
        $commandNames,
        fn( $name ) => str_starts_with( $name, $query )
    ) );
}

Helpers::jsonResponse( [ 'suggestions' => $suggestions ] );
