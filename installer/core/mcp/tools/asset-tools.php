<?php

/**
 * Klytos — MCP Asset Tools
 * File upload and management via MCP.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\MCP\Tools;

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

function registerAssetTools(ToolRegistry $registry): void
{
    $registry->register(
        'klytos_upload_asset',
        'Upload a file (image, CSS, JS, font, etc.) encoded in base64.',
        [
            'filename'    => ['type' => 'string', 'description' => 'Filename with extension (e.g. "logo.png")'],
            'data_base64' => ['type' => 'string', 'description' => 'File content encoded in base64'],
            'directory'   => ['type' => 'string', 'description' => 'Subdirectory within assets/ (default: "images")'],
        ],
        function (array $params, App $app): array {
            $result = $app->getAssets()->upload(
                $params['filename'] ?? '',
                $params['data_base64'] ?? '',
                $params['directory'] ?? 'images'
            );
            return ['success' => true, 'asset' => $result];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['filename', 'data_base64']
    );

    $registry->register(
        'klytos_list_assets',
        'List all uploaded assets, optionally filtered by directory.',
        [
            'directory' => ['type' => 'string', 'description' => 'Filter by subdirectory (empty = all)'],
        ],
        function (array $params, App $app): array {
            $assets = $app->getAssets()->list($params['directory'] ?? '');
            return ['assets' => $assets, 'total' => count($assets)];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_delete_asset',
        'Delete an uploaded asset file.',
        [
            'path' => ['type' => 'string', 'description' => 'Relative path from public/ (e.g. "assets/images/logo.png")'],
        ],
        function (array $params, App $app): array {
            $deleted = $app->getAssets()->delete($params['path'] ?? '');
            return ['success' => $deleted, 'path' => $params['path'] ?? ''];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['path']
    );

    // ──────────────────────────────────────────────────────────
    //  Media Management Tools (v0.18.0)
    // ──────────────────────────────────────────────────────────

    $registry->register(
        'klytos_assets_list_filtered',
        'List registered assets with optional filters (usage status, category, MIME type, search). Returns paginated results from the asset metadata database.',
        [
            'filter'   => ['type' => 'string', 'description' => 'Filter by usage: "all" (default), "in_use", or "unused"'],
            'category' => ['type' => 'string', 'description' => 'Filter by category slug'],
            'type'     => ['type' => 'string', 'description' => 'Filter by MIME type prefix: "image", "video", "application", "font"'],
            'search'   => ['type' => 'string', 'description' => 'Search by filename or title'],
            'page'     => ['type' => 'integer', 'description' => 'Page number (default 1)'],
            'per_page' => ['type' => 'integer', 'description' => 'Results per page (default 20, max 100)'],
        ],
        function (array $params, App $app): array {
            $am     = $app->getAssetManager();
            $filter = $params['filter'] ?? 'all';

            if ($filter === 'unused') {
                $assets = $am->getUnusedAssets();
            } elseif ($filter === 'in_use') {
                $allAssets = $am->getStorage()->list( 'assets' );
                $allUsage  = $am->getStorage()->list( 'asset-usage' );
                $usedIds   = [];
                foreach ( $allUsage as $u ) {
                    $usedIds[$u['asset_id'] ?? ''] = true;
                }
                $assets = array_values( array_filter( $allAssets, fn( $a ) => isset( $usedIds[$a['id']] ) ) );
            } else {
                $assets = $am->getStorage()->list( 'assets' );
            }

            // Category filter.
            $cat = $params['category'] ?? '';
            if ( $cat !== '' ) {
                $assets = array_values( array_filter( $assets, fn( $a ) =>
                    isset( $a['categories'] ) && in_array( $cat, $a['categories'], true )
                ) );
            }

            // MIME type filter.
            $type = $params['type'] ?? '';
            if ( $type !== '' ) {
                $assets = array_values( array_filter( $assets, fn( $a ) =>
                    str_starts_with( $a['mime_type'] ?? '', $type . '/' )
                ) );
            }

            // Search filter.
            $search = $params['search'] ?? '';
            if ( $search !== '' ) {
                $needle = mb_strtolower( $search );
                $assets = array_values( array_filter( $assets, fn( $a ) =>
                    str_contains( mb_strtolower( $a['filename'] ?? '' ), $needle )
                    || str_contains( mb_strtolower( $a['title'] ?? '' ), $needle )
                ) );
            }

            // Sort newest first.
            usort( $assets, fn( $a, $b ) => strcmp( $b['uploaded_at'] ?? '', $a['uploaded_at'] ?? '' ) );

            // Paginate.
            $page    = max( 1, (int) ( $params['page'] ?? 1 ) );
            $perPage = min( 100, max( 1, (int) ( $params['per_page'] ?? 20 ) ) );
            $total   = count( $assets );
            $paged   = array_slice( $assets, ( $page - 1 ) * $perPage, $perPage );

            return [
                'assets' => $paged,
                'total'  => $total,
                'page'   => $page,
                'pages'  => (int) ceil( $total / $perPage ),
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_assets_get_usage',
        'Get all locations where a specific asset is used (pages, header, footer, theme, etc.).',
        [
            'asset_id' => ['type' => 'string', 'description' => 'The asset ID to look up usage for'],
        ],
        function (array $params, App $app): array {
            $id    = $params['asset_id'] ?? '';
            $usage = $app->getAssetManager()->getUsage( $id );
            return ['asset_id' => $id, 'usage' => $usage, 'total' => count( $usage )];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['asset_id']
    );

    $registry->register(
        'klytos_assets_get_unused',
        'Get all registered assets that are not currently used in any page, header, footer, or theme setting.',
        [],
        function (array $params, App $app): array {
            $unused = $app->getAssetManager()->getUnusedAssets();
            return ['assets' => $unused, 'total' => count( $unused )];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_assets_update_metadata',
        'Update metadata for a registered asset (title, alt text, description, categories).',
        [
            'asset_id'    => ['type' => 'string', 'description' => 'The asset ID to update'],
            'title'       => ['type' => 'string', 'description' => 'New display title'],
            'alt_text'    => ['type' => 'string', 'description' => 'New alt text for accessibility'],
            'description' => ['type' => 'string', 'description' => 'New description'],
            'categories'  => ['type' => 'array', 'description' => 'Array of category slugs to assign'],
        ],
        function (array $params, App $app): array {
            $id      = $params['asset_id'] ?? '';
            $storage = $app->getAssetManager()->getStorage();

            $record = $storage->read( 'assets', $id );

            $fields = ['title', 'alt_text', 'description'];
            foreach ( $fields as $f ) {
                if ( array_key_exists( $f, $params ) ) {
                    $record[$f] = (string) $params[$f];
                }
            }

            if ( isset( $params['categories'] ) && is_array( $params['categories'] ) ) {
                $record['categories'] = array_map( 'strval', $params['categories'] );
            }

            $record['updated_at'] = \Klytos\Core\Helpers::now();
            $storage->write( 'assets', $id, $record );

            return ['success' => true, 'asset' => $record];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
        ['asset_id']
    );

    $registry->register(
        'klytos_asset_categories_list',
        'List all asset categories with the number of assets in each one.',
        [],
        function (array $params, App $app): array {
            $am   = $app->getAssetManager();
            $cats = $am->listCategories();
            foreach ( $cats as &$cat ) {
                $cat['asset_count'] = count( $am->getAssetsByCategory( $cat['id'] ) );
            }
            unset( $cat );
            return ['categories' => $cats, 'total' => count( $cats )];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_asset_categories_create',
        'Create a new asset category for organizing media files.',
        [
            'name'        => ['type' => 'string', 'description' => 'Category display name'],
            'description' => ['type' => 'string', 'description' => 'Optional description of the category'],
        ],
        function (array $params, App $app): array {
            $category = $app->getAssetManager()->createCategory(
                $params['name'] ?? '',
                $params['description'] ?? ''
            );
            return ['success' => true, 'category' => $category];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
        ['name']
    );

    $registry->register(
        'klytos_assets_sync',
        'Scan the filesystem for asset files that are not yet registered in the database and create metadata records for them. Useful after migrations or manual file additions.',
        [],
        function (array $params, App $app): array {
            $synced = $app->getAssetManager()->syncExistingAssets();
            return ['success' => true, 'synced' => $synced];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_assets_rebuild_usage',
        'Rebuild the entire asset usage index by scanning all pages and theme configuration. Deletes existing usage records and recreates them from current content.',
        [],
        function (array $params, App $app): array {
            $stats = $app->getAssetManager()->rebuildUsageIndex();
            return ['success' => true, 'stats' => $stats];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_assets_cleanup_unused',
        'Delete ALL assets that are not used anywhere. This permanently removes physical files and their metadata. Requires confirm=true as a safety measure.',
        [
            'confirm' => ['type' => 'boolean', 'description' => 'Must be true to proceed with deletion'],
        ],
        function (array $params, App $app): array {
            if ( empty( $params['confirm'] ) ) {
                return ['success' => false, 'error' => 'You must pass confirm: true to delete unused assets.'];
            }

            $am      = $app->getAssetManager();
            $unused  = $am->getUnusedAssets();
            $deleted = 0;

            foreach ( $unused as $asset ) {
                if ( $am->delete( $asset['path'] ?? '' ) ) {
                    $deleted++;
                }
            }

            return ['success' => true, 'deleted' => $deleted, 'total_unused' => count( $unused )];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
        ['confirm']
    );

    // ─── Image Editing ──────────────────────────────────────────

    $registry->register(
        'klytos_edit_image',
        'Edit an image: crop, rotate, flip, or resize. Uses GD server-side. Overwrites original unless save_as is provided.',
        [
            'path'    => ['type' => 'string', 'description' => 'Asset path relative to web root (e.g. "assets/images/2026/04/photo.jpg").'],
            'crop'    => ['type' => 'object', 'description' => 'Crop region: {x: int, y: int, width: int, height: int}.', 'additionalProperties' => true],
            'rotate'  => ['type' => 'integer', 'description' => 'Rotation degrees clockwise: 90, 180, or 270.'],
            'flip'    => ['type' => 'string', 'description' => 'Flip direction.', 'enum' => ['horizontal', 'vertical']],
            'resize'  => ['type' => 'object', 'description' => 'Resize: {width: int, height?: int}. Height auto-computed if omitted.', 'additionalProperties' => true],
            'save_as' => ['type' => 'string', 'description' => 'New filename. If omitted, overwrites original.'],
        ],
        function ( array $params, App $app ): array {
            $path = $params['path'] ?? '';
            if ( empty( $path ) ) {
                throw new \InvalidArgumentException( 'path is required.' );
            }

            $operations = [];
            if ( isset( $params['crop'] ) )   $operations['crop']   = $params['crop'];
            if ( isset( $params['rotate'] ) ) $operations['rotate'] = $params['rotate'];
            if ( isset( $params['flip'] ) )   $operations['flip']   = $params['flip'];
            if ( isset( $params['resize'] ) ) $operations['resize'] = $params['resize'];

            $saveAs = $params['save_as'] ?? '';
            $result = $app->getAssets()->editImage( $path, $operations, $saveAs );

            return ['success' => true, 'asset' => $result];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
        ['path']
    );
}
