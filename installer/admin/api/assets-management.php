<?php

/**
 * Klytos Admin API — Assets Management
 *
 * REST-like endpoint for managing asset metadata, categories,
 * usage tracking, sync, and cleanup operations.
 *
 * @package Klytos
 * @since   0.18.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require dirname( __DIR__ ) . '/bootstrap.php';

use Klytos\Core\Helpers;

header( 'Content-Type: application/json; charset=utf-8' );

// ─── Authentication ─────────────────────────────────────────
if ( !$app->getAuth()->isAuthenticated() ) {
    Helpers::jsonResponse( ['error' => 'Unauthorized'], 401 );
}

if ( !klytos_has_permission( 'assets.manage' ) ) {
    Helpers::jsonResponse( ['error' => 'Forbidden'], 403 );
}

$method       = $_SERVER['REQUEST_METHOD'];
$assetManager = $app->getAssetManager();

// ─── GET actions ────────────────────────────────────────────
if ( $method === 'GET' ) {
    $action = $_GET['action'] ?? '';

    switch ( $action ) {
        case 'list':
            /*
             * The selection itself lives in `AssetManager::query()`. It used to
             * be written out here, which was fine while this endpoint was the
             * only consumer — the screen was rendered by JavaScript from it.
             * Entry 4 makes the screen server-rendered, and two copies of
             * "which assets does this person see" would be free to drift, one
             * deciding what a page shows and the other what an MCP client is
             * told (L-004, D-118).
             *
             * `type` now accepts `document` as well as a MIME prefix, and every
             * row carries `usage_count`. Both are additions; nothing this
             * endpoint answered before has changed shape.
             */
            $result = $assetManager->query( [
                'filter'   => $_GET['filter'] ?? 'all',
                'category' => $_GET['category'] ?? '',
                'type'     => $_GET['type'] ?? '',
                'search'   => $_GET['search'] ?? '',
                'page'     => (int) ( $_GET['page'] ?? 1 ),
                'per_page' => (int) ( $_GET['per_page'] ?? 20 ),
            ] );

            Helpers::jsonResponse( [
                'success' => true,
                'assets'  => $result['assets'],
                'total'   => $result['total'],
                'page'    => $result['page'],
                'pages'   => $result['pages'],
            ] );
            break;

        case 'list_categories':
            $categories = $assetManager->listCategories();

            // Attach asset count to each category.
            foreach ( $categories as &$cat ) {
                $cat['asset_count'] = count( $assetManager->getAssetsByCategory( $cat['id'] ) );
            }
            unset( $cat );

            Helpers::jsonResponse( ['success' => true, 'categories' => $categories] );
            break;

        default:
            Helpers::jsonResponse( ['error' => 'Unknown action: ' . $action], 400 );
    }

    exit;
}

// ─── POST actions ───────────────────────────────────────────
if ( $method !== 'POST' ) {
    Helpers::jsonResponse( ['error' => 'Method not allowed'], 405 );
}

if ( !klytos_verify_csrf() ) {
    Helpers::jsonResponse( ['error' => 'Invalid CSRF token'], 403 );
}

$input  = json_decode( file_get_contents( 'php://input' ), true );
if ( !is_array( $input ) ) {
    $input = [];
}

$action = $input['action'] ?? ( $_POST['action'] ?? '' );

switch ( $action ) {
    case 'update':
        $id = $input['id'] ?? '';
        if ( $id === '' ) {
            Helpers::jsonResponse( ['error' => 'Missing id'], 400 );
        }

        try {
            $record = $assetManager->getStorage()->read( 'assets', $id );
        } catch ( \Throwable $e ) {
            Helpers::jsonResponse( ['error' => 'Asset not found'], 404 );
        }

        // Update allowed fields.
        $updatable = ['title', 'alt_text', 'description'];
        foreach ( $updatable as $field ) {
            if ( array_key_exists( $field, $input ) ) {
                $record[$field] = klytos_sanitize_text( (string) $input[$field] );
            }
        }

        // Categories: array of slugs.
        if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
            $record['categories'] = array_map( 'strval', $input['categories'] );
        }

        $record['updated_at'] = Helpers::now();
        $assetManager->getStorage()->write( 'assets', $id, $record );

        Helpers::jsonResponse( ['success' => true, 'asset' => $record] );
        break;

    case 'delete':
        $id = $input['id'] ?? '';
        if ( $id === '' ) {
            Helpers::jsonResponse( ['error' => 'Missing id'], 400 );
        }

        try {
            $record = $assetManager->getStorage()->read( 'assets', $id );
        } catch ( \Throwable $e ) {
            Helpers::jsonResponse( ['error' => 'Asset not found'], 404 );
        }

        $deleted = $assetManager->delete( $record['path'] ?? '' );

        Helpers::jsonResponse( ['success' => $deleted] );
        break;

    case 'bulk_delete':
        $ids = $input['ids'] ?? [];
        if ( !is_array( $ids ) || empty( $ids ) ) {
            Helpers::jsonResponse( ['error' => 'Missing or empty ids array'], 400 );
        }

        $deleted = 0;
        foreach ( $ids as $id ) {
            try {
                $record = $assetManager->getStorage()->read( 'assets', (string) $id );
                if ( $assetManager->delete( $record['path'] ?? '' ) ) {
                    $deleted++;
                }
            } catch ( \Throwable $e ) {
                // Skip assets that don't exist.
            }
        }

        Helpers::jsonResponse( ['success' => true, 'deleted' => $deleted] );
        break;

    case 'sync':
        $synced = $assetManager->syncExistingAssets();
        Helpers::jsonResponse( ['success' => true, 'synced' => $synced] );
        break;

    case 'rebuild_usage':
        $stats = $assetManager->rebuildUsageIndex();
        Helpers::jsonResponse( ['success' => true, 'stats' => $stats] );
        break;

    case 'create_category':
        $name = klytos_sanitize_text( (string) ( $input['name'] ?? '' ) );
        if ( $name === '' ) {
            Helpers::jsonResponse( ['error' => 'Missing category name'], 400 );
        }

        try {
            $category = $assetManager->createCategory(
                $name,
                klytos_sanitize_text( (string) ( $input['description'] ?? '' ) ),
                isset( $input['parent'] ) ? (string) $input['parent'] : null
            );
            Helpers::jsonResponse( ['success' => true, 'category' => $category] );
        } catch ( \RuntimeException $e ) {
            Helpers::jsonResponse( ['error' => $e->getMessage()], 409 );
        }
        break;

    case 'update_category':
        $id = $input['id'] ?? '';
        if ( $id === '' ) {
            Helpers::jsonResponse( ['error' => 'Missing category id'], 400 );
        }

        try {
            $data = [];
            if ( isset( $input['name'] ) ) {
                $data['name'] = klytos_sanitize_text( (string) $input['name'] );
            }
            if ( isset( $input['description'] ) ) {
                $data['description'] = klytos_sanitize_text( (string) $input['description'] );
            }
            if ( array_key_exists( 'parent', $input ) ) {
                $data['parent'] = $input['parent'] !== null ? (string) $input['parent'] : null;
            }
            if ( isset( $input['order'] ) ) {
                $data['order'] = (int) $input['order'];
            }

            $category = $assetManager->updateCategory( (string) $id, $data );
            Helpers::jsonResponse( ['success' => true, 'category' => $category] );
        } catch ( \RuntimeException $e ) {
            Helpers::jsonResponse( ['error' => $e->getMessage()], 404 );
        }
        break;

    case 'delete_category':
        $id = $input['id'] ?? '';
        if ( $id === '' ) {
            Helpers::jsonResponse( ['error' => 'Missing category id'], 400 );
        }

        $deleted = $assetManager->deleteCategory( (string) $id );
        Helpers::jsonResponse( ['success' => $deleted] );
        break;

    default:
        Helpers::jsonResponse( ['error' => 'Unknown action: ' . $action], 400 );
}
