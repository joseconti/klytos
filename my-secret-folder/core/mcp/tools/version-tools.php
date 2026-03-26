<?php
/**
 * Klytos — MCP Version History Tools
 * Tools: klytos_list_versions, klytos_get_version, klytos_restore_version, klytos_diff_versions.
 *
 * @package Klytos
 * @since   2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

function registerVersionTools(ToolRegistry $registry, App $app): void
{
    $registry->register(
        'klytos_list_versions',
        'List version history for a page (newest first). Returns metadata only, not full page data.',
        [
            'page_slug' => ['type' => 'string', 'description' => 'Page slug to list versions for.'],
            'limit'     => ['type' => 'integer', 'description' => 'Max versions to return (default: all).'],
        ],
        function (array $params, App $app): array {
            if (empty($params['page_slug'])) {
                throw new \InvalidArgumentException('page_slug is required.');
            }
            $versionManager = new \Klytos\Core\VersionManager($app->getStorage());
            return $versionManager->listForPage($params['page_slug'], (int) ($params['limit'] ?? 0));
        },
        ['title' => 'List Page Versions', 'readOnlyHint' => true],
        ['page_slug']
    );

    $registry->register(
        'klytos_get_version',
        'Get a specific version of a page, including the full page data snapshot.',
        [
            'page_slug' => ['type' => 'string', 'description' => 'Page slug.'],
            'version'   => ['type' => 'integer', 'description' => 'Version number (1-based).'],
        ],
        function (array $params, App $app): array {
            if (empty($params['page_slug']) || empty($params['version'])) {
                throw new \InvalidArgumentException('page_slug and version are required.');
            }
            $versionManager = new \Klytos\Core\VersionManager($app->getStorage());
            return $versionManager->get($params['page_slug'], (int) $params['version']);
        },
        ['title' => 'Get Page Version', 'readOnlyHint' => true],
        ['page_slug', 'version']
    );

    $registry->register(
        'klytos_restore_version',
        'Restore a page to a previous version. The current page is overwritten with the version\'s data. A new version entry is created for the restoration.',
        [
            'page_slug' => ['type' => 'string', 'description' => 'Page slug.'],
            'version'   => ['type' => 'integer', 'description' => 'Version number to restore.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['page_slug']) || empty($params['version'])) {
                throw new \InvalidArgumentException('page_slug and version are required.');
            }
            $versionManager = new \Klytos\Core\VersionManager($app->getStorage());
            $restoredData   = $versionManager->restore(
                $params['page_slug'],
                (int) $params['version'],
                'mcp'
            );
            return [
                'success'          => true,
                'restored_version' => (int) $params['version'],
                'page_slug'        => $params['page_slug'],
                'page_data'        => $restoredData,
            ];
        },
        ['title' => 'Restore Page Version', 'readOnlyHint' => false, 'destructiveHint' => true],
        ['page_slug', 'version']
    );

    $registry->register(
        'klytos_diff_versions',
        'Compare two versions of a page. Shows changed, added, and removed fields.',
        [
            'page_slug' => ['type' => 'string', 'description' => 'Page slug.'],
            'version_a' => ['type' => 'integer', 'description' => 'First version number (older).'],
            'version_b' => ['type' => 'integer', 'description' => 'Second version number (newer).'],
        ],
        function (array $params, App $app): array {
            if (empty($params['page_slug']) || empty($params['version_a']) || empty($params['version_b'])) {
                throw new \InvalidArgumentException('page_slug, version_a, and version_b are required.');
            }
            $versionManager = new \Klytos\Core\VersionManager($app->getStorage());
            return $versionManager->diff(
                $params['page_slug'],
                (int) $params['version_a'],
                (int) $params['version_b']
            );
        },
        ['title' => 'Diff Page Versions', 'readOnlyHint' => true],
        ['page_slug', 'version_a', 'version_b']
    );
}
