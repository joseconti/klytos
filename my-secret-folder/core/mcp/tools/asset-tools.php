<?php
/**
 * Klytos — MCP Asset Tools
 * File upload and management via MCP.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
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
}
