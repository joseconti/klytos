<?php

/**
 * Klytos — MCP Build Tools
 * Static site generation via MCP.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
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
        'Get the status of the last site build. Includes LLM discoverability stats (llms.txt, llms-full.txt, per-page .html.md files).',
        [],
        function (array $params, App $app): array {
            $siteConfig = $app->getSiteConfig()->get();
            $pageCount  = $app->getPages()->count( 'published' );
            $seo        = $siteConfig['seo'] ?? [];

            return [
                'last_build'              => $siteConfig['last_build'] ?? null,
                'published_pages'         => $pageCount,
                'version'                 => $app->getVersion(),
                'llms_txt_enabled'        => $seo['llms_txt_enabled'] ?? true,
                'llms_full_txt_enabled'   => $seo['llms_full_txt_enabled'] ?? true,
                'llms_md_pages_enabled'   => $seo['llms_md_pages_enabled'] ?? true,
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_rebuild_block',
        'Smart rebuild: re-render a single global block and patch it across all generated HTML files without a full site rebuild. Only works for blocks with scope=global.',
        [
            'block_id' => ['type' => 'string', 'description' => 'ID of the global block to rebuild (e.g. "header", "footer")'],
        ],
        function (array $params, App $app): array {
            require_once $app->getCorePath() . '/build-engine.php';
            $engine = new \Klytos\Core\BuildEngine($app);
            return $engine->smartRebuildBlock($params['block_id'] ?? '');
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['block_id']
    );

    $registry->register(
        'klytos_rebuild_css',
        'Regenerate CSS files only: theme style.css and block assets blocks.css. Does not rebuild HTML pages.',
        [],
        function (array $params, App $app): array {
            require_once $app->getCorePath() . '/build-engine.php';
            $engine = new \Klytos\Core\BuildEngine($app);
            $engine->generateCss();
            $engine->generateBlocksCss();
            return ['success' => true, 'files' => ['style.css', 'blocks.css']];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true]
    );
}
