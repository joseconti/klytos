<?php

/**
 * Klytos Admin API — Notices Endpoint
 * AJAX endpoint for dismissing and listing admin notices.
 *
 * Methods:
 * - GET                → List renderable notices.
 * - POST action=dismiss → Dismiss a persistent notice for the current session.
 *
 * Authentication: Requires active admin session + CSRF token.
 *
 * @package Klytos
 * @since   2.1.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/bootstrap.php';

use Klytos\Core\Helpers;

header( 'Content-Type: application/json; charset=utf-8' );

// Require authentication for all API calls.
if ( ! $app->getAuth()->isAuthenticated() ) {
    Helpers::jsonResponse( ['error' => 'Unauthorized'], 401 );
}

$noticeManager = $app->getNoticeManager();
$method        = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ( $method === 'GET' ) {
        $currentPage = $_GET['page'] ?? '';
        $notices     = $noticeManager->getRenderable( $currentPage );

        Helpers::jsonResponse( ['success' => true, 'notices' => $notices] );

    } elseif ( $method === 'POST' ) {
        // Parse JSON body.
        $input = json_decode( file_get_contents( 'php://input' ), true ) ?? $_POST;

        // Validate CSRF.
        if ( ! klytos_verify_csrf() ) {
            Helpers::jsonResponse( ['error' => 'Invalid CSRF token'], 403 );
        }

        $action = $input['action'] ?? '';

        if ( $action === 'dismiss' ) {
            $id = trim( $input['id'] ?? '' );
            if ( empty( $id ) ) {
                Helpers::jsonResponse( ['error' => 'Missing notice id'], 400 );
            }

            $noticeManager->dismiss( $id );

            Helpers::jsonResponse( ['success' => true, 'dismissed' => $id] );

        } elseif ( $action === 'dismiss-all' ) {
            $allNotices = $noticeManager->list();
            $dismissed  = [];
            foreach ( $allNotices as $notice ) {
                if ( ! empty( $notice['dismissible'] ) ) {
                    $noticeManager->dismiss( $notice['id'] );
                    $dismissed[] = $notice['id'];
                }
            }

            Helpers::jsonResponse( ['success' => true, 'dismissed' => $dismissed] );

        } else {
            Helpers::jsonResponse( ['error' => 'Unknown action'], 400 );
        }

    } else {
        Helpers::jsonResponse( ['error' => 'Method not allowed'], 405 );
    }
} catch ( \Throwable $e ) {
    error_log( 'Klytos notices API error: ' . $e->getMessage() );
    Helpers::jsonResponse( ['error' => 'Internal error'], 500 );
}
