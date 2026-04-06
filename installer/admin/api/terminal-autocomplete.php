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
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 Jose Conti -- https://plugins.joseconti.com -- https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
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

// 5. Get commands metadata and filter by query prefix.
$query    = strtolower( trim( $_GET['q'] ?? '' ) );
$executor = $app->getTerminalExecutor();
$metadata = $executor->getCommandsMetadata();

if ( $query === '' ) {
    $suggestions = array_keys( $metadata );
} else {
    $suggestions = array_values( array_filter(
        array_keys( $metadata ),
        fn( $name ) => str_starts_with( $name, $query )
    ) );
}

// Return both suggestions (for autocomplete) and full metadata (for command panel).
Helpers::jsonResponse( [
    'suggestions' => $suggestions,
    'commands'    => $metadata,
] );
