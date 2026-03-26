<?php
/**
 * Klytos — MCP Build Tools
 * Static site generation via MCP.
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

function registerBuildTools(ToolRegistry $registry): void
{
    $registry->register(
        'klytos_build_site',
        'Regenerate the entire static site: all pages, CSS, sitemap, and robots.txt.',
        [],
        function (array $params, App $app): array {
            require_once $app->getCorePath() . '/build-engine.php';
            $engine = new \Klytos\Core\BuildEngine($app);
            return $engine->buildAll();
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_build_page',
        'Regenerate a single page without rebuilding the entire site.',
        [
            'slug' => ['type' => 'string', 'description' => 'Slug of the page to rebuild'],
        ],
        function (array $params, App $app): array {
            require_once $app->getCorePath() . '/build-engine.php';
            $engine = new \Klytos\Core\BuildEngine($app);
            return $engine->buildPage($params['slug'] ?? '');
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['slug']
    );

    $registry->register(
        'klytos_preview_page',
        'Preview a page: returns rendered HTML without saving to disk.',
        [
            'slug' => ['type' => 'string', 'description' => 'Slug of the page to preview'],
        ],
        function (array $params, App $app): array {
            require_once $app->getCorePath() . '/build-engine.php';
            $engine = new \Klytos\Core\BuildEngine($app);
            $html   = $engine->renderPage($params['slug'] ?? '');
            return ['html' => $html, 'slug' => $params['slug'] ?? ''];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['slug']
    );

    $registry->register(
        'klytos_get_build_status',
        'Get the status of the last site build.',
        [],
        function (array $params, App $app): array {
            $siteConfig = $app->getSiteConfig()->get();
            $pageCount  = $app->getPages()->count('published');

            return [
                'last_build'      => $siteConfig['last_build'] ?? null,
                'published_pages' => $pageCount,
                'version'         => $app->getVersion(),
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );
}
