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
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
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
