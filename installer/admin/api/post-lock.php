<?php

/**
 * Klytos Admin API — Post Lock
 * Acquire, renew, release, check, and takeover editing locks.
 *
 * @package Klytos
 * @since   0.26.0
 *
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 Jose Conti
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/bootstrap.php';

use Klytos\Core\Helpers;

header( 'Content-Type: application/json; charset=utf-8' );

if ( !$app->getAuth()->isAuthenticated() ) {
    Helpers::jsonResponse( ['error' => 'Unauthorized'], 401 );
}
if ( !klytos_verify_csrf() ) {
    Helpers::jsonResponse( ['error' => 'Invalid CSRF token'], 403 );
}

$input  = json_decode( file_get_contents( 'php://input' ), true );
if ( !is_array( $input ) ) {
    $input = $_POST;
}

$action = $input['action'] ?? '';
$slug   = $input['slug'] ?? '';
$userId = $app->getAuth()->getUserId();

if ( empty( $slug ) ) {
    Helpers::jsonResponse( ['error' => 'slug is required'], 400 );
}

$pages = $app->getPages();

switch ( $action ) {
    case 'acquire':
        $result = $pages->acquireLock( $slug, $userId );
        Helpers::jsonResponse( $result );
        break;

    case 'heartbeat':
        $renewed = $pages->renewLock( $slug, $userId );
        Helpers::jsonResponse( ['renewed' => $renewed] );
        break;

    case 'release':
        $released = $pages->releaseLock( $slug, $userId );
        Helpers::jsonResponse( ['released' => $released] );
        break;

    case 'check':
        $lock = $pages->checkLock( $slug );
        Helpers::jsonResponse( ['lock' => $lock] );
        break;

    case 'takeover':
        // Force-acquire the lock (admin/owner only).
        if ( !klytos_has_permission( 'pages.edit' ) ) {
            Helpers::jsonResponse( ['error' => 'Forbidden'], 403 );
        }
        $existing = $pages->checkLock( $slug );
        if ( $existing ) {
            klytos_do_action( 'page.lock_takeover', $slug, $userId, $existing['user_id'] ?? '' );
            // Clear existing lock.
            klytos_set_meta( 'page', $slug, '_editing_lock', null );
        }
        $result = $pages->acquireLock( $slug, $userId );
        Helpers::jsonResponse( $result );
        break;

    default:
        Helpers::jsonResponse( ['error' => 'Invalid action'], 400 );
}
