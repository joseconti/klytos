<?php

/**
 * Klytos Admin API — Image Edit
 * Server-side image manipulation (crop, rotate, flip, resize) using GD.
 *
 * @package Klytos
 * @since   0.26.0
 *
 * @license    Elastic License 2.0 (ELv2)
 * @copyright  Copyright (c) 2026 Jose Conti
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/bootstrap.php';

use Klytos\Core\Helpers;

header( 'Content-Type: application/json; charset=utf-8' );

if ( !$app->getAuth()->isAuthenticated() ) {
    Helpers::jsonResponse( ['error' => 'Unauthorized'], 401 );
}
if ( !klytos_has_permission( 'assets.manage' ) ) {
    Helpers::jsonResponse( ['error' => 'Forbidden'], 403 );
}
if ( !klytos_verify_csrf() ) {
    Helpers::jsonResponse( ['error' => 'Invalid CSRF token'], 403 );
}
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    Helpers::jsonResponse( ['error' => 'Method not allowed'], 405 );
}

$input = json_decode( file_get_contents( 'php://input' ), true );
if ( !is_array( $input ) ) {
    Helpers::jsonResponse( ['error' => 'Invalid JSON body'], 400 );
}

$path = $input['path'] ?? '';
if ( empty( $path ) ) {
    Helpers::jsonResponse( ['error' => 'path is required'], 400 );
}

$operations = [];
if ( isset( $input['crop'] ) && is_array( $input['crop'] ) )    $operations['crop']   = $input['crop'];
if ( isset( $input['rotate'] ) )                                  $operations['rotate'] = (int) $input['rotate'];
if ( isset( $input['flip'] ) )                                    $operations['flip']   = $input['flip'];
if ( isset( $input['resize'] ) && is_array( $input['resize'] ) ) $operations['resize'] = $input['resize'];

$saveAs = $input['save_as'] ?? '';

try {
    $result = $app->getAssets()->editImage( $path, $operations, $saveAs );
    Helpers::jsonResponse( ['success' => true, 'asset' => $result] );
} catch ( \Throwable $e ) {
    Helpers::jsonResponse( ['error' => $e->getMessage()], 400 );
}
