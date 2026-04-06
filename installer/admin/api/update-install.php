<?php

/**
 * Klytos Admin API — Update Install Endpoint
 *
 * Handles the update installation via AJAX so the frontend
 * can display a progress overlay instead of a blank page reload.
 *
 * Accepts POST with:
 *   - X-CSRF-Token header
 *   - JSON body: { "download_url": "https://..." }
 *
 * Returns JSON: { "success": true/false, "from_version": "...", "to_version": "...", "error": "..." }
 *
 * @package   Klytos
 * @since     0.13.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/bootstrap.php';

use Klytos\Core\Helpers;

header( 'Content-Type: application/json; charset=utf-8' );

if ( ! $app->getAuth()->isAuthenticated() ) {
    Helpers::jsonResponse( [ 'error' => 'Unauthorized' ], 401 );
}

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    Helpers::jsonResponse( [ 'error' => 'Method not allowed' ], 405 );
}

if ( ! klytos_verify_csrf() ) {
    Helpers::jsonResponse( [ 'error' => 'Invalid CSRF token' ], 403 );
}

$input = json_decode( file_get_contents( 'php://input' ), true );
if ( ! is_array( $input ) ) {
    Helpers::jsonResponse( [ 'error' => 'Invalid JSON body' ], 400 );
}

$downloadUrl = $input['download_url'] ?? '';
if ( empty( $downloadUrl ) ) {
    Helpers::jsonResponse( [ 'error' => 'download_url is required' ], 400 );
}

$updater = $app->getUpdater();
$result  = $updater->install( $downloadUrl );

Helpers::jsonResponse( $result );
