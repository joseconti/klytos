<?php
/**
 * Klytos — MCP Page Tools
 * CRUD operations for site pages via MCP.
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

function registerPageTools(ToolRegistry $registry): void
{
    // ─── klytos_create_page ────────────────────────────────────
    $registry->register(
        'klytos_create_page',
        'Create a new HTML page. Supports hierarchical URLs: "servicios" creates /servicios/, "servicios/marketing" creates /servicios/marketing/. Parent pages must exist first. Provide slug, title, and content_html at minimum. IMPORTANT: content_html MUST use Gutenberg block markup (<!-- wp:paragraph --> etc.) for visual editor compatibility. meta_description is required for SEO.',
        [
            'slug'             => ['type' => 'string', 'description' => 'URL slug with hierarchy support. E.g.: "about" → /about/, "servicios/marketing" → /servicios/marketing/. Use / for nested pages.'],
            'title'            => ['type' => 'string', 'description' => 'Page title for <title> and <h1>. Max 60 chars. Primary keyword first. Do NOT include the site name (added automatically).'],
            'content_html'     => ['type' => 'string', 'description' => 'Full HTML body content. MUST use Gutenberg block comments (<!-- wp:paragraph -->, <!-- wp:heading -->, etc.) so the visual editor can parse it. See the klytos-gutenberg-blocks skill for all block formats.'],
            'meta_description' => ['type' => 'string', 'description' => 'SEO meta description. REQUIRED. 120-155 chars recommended. Include primary keyword and a call-to-action. Max 160 chars.'],
            'og_image'         => ['type' => 'string', 'description' => 'Open Graph image URL (1200x630px recommended). Used for Facebook, LinkedIn, Twitter previews. Strongly recommended.'],
            'template'         => ['type' => 'string', 'description' => 'Template: default, landing, blog-post, blank', 'enum' => ['default', 'landing', 'blog-post', 'blank']],
            'status'           => ['type' => 'string', 'description' => 'Page status', 'enum' => ['draft', 'published']],
            'lang'             => ['type' => 'string', 'description' => 'Language code (es, en, ca...) for hreflang'],
            'custom_css'       => ['type' => 'string', 'description' => 'Custom CSS for this page'],
            'custom_js'        => ['type' => 'string', 'description' => 'Custom JS for this page'],
            'hreflang_refs'    => ['type' => 'object', 'description' => 'Map of lang to slug for alternate versions. E.g.: {"en": "en/about", "es": "about"}', 'additionalProperties' => true],
            'order'            => ['type' => 'integer', 'description' => 'Sort order (lower = first)'],
        ],
        function (array $params, App $app): array {
            // Validate SEO fields.
            $warnings = [];

            if ( empty( $params['meta_description'] ) ) {
                $warnings[] = 'meta_description is missing. Every page MUST have a meta description for SEO (120-155 chars recommended).';
            } elseif ( strlen( $params['meta_description'] ) < 50 ) {
                $warnings[] = 'meta_description is too short (' . strlen( $params['meta_description'] ) . ' chars). Recommended: 120-155 characters.';
            }

            if ( empty( $params['og_image'] ) ) {
                $warnings[] = 'og_image is missing. Without it, social media shares will show a generic preview. Recommended: 1200x630px image.';
            }

            if ( ! empty( $params['title'] ) && strlen( $params['title'] ) > 60 ) {
                $warnings[] = 'title exceeds 60 characters (' . strlen( $params['title'] ) . ' chars). Google will truncate it in search results.';
            }

            if ( ! empty( $params['content_html'] ) && strpos( $params['content_html'], '<!-- wp:' ) === false ) {
                $warnings[] = 'content_html does not contain Gutenberg block markup. The visual editor will not be able to parse this content into editable blocks. Use <!-- wp:paragraph -->, <!-- wp:heading -->, etc.';
            }

            $page = $app->getPages()->create( $params );

            $result = ['success' => true, 'page' => $page];
            if ( ! empty( $warnings ) ) {
                $result['seo_warnings'] = $warnings;
            }

            return $result;
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
        ['slug', 'title', 'content_html']
    );

    // ─── klytos_update_page ────────────────────────────────────
    $registry->register(
        'klytos_update_page',
        'Update an existing page. Only provided fields will be changed.',
        [
            'slug'             => ['type' => 'string', 'description' => 'Slug of page to update (required)'],
            'title'            => ['type' => 'string', 'description' => 'New title'],
            'content_html'     => ['type' => 'string', 'description' => 'New HTML content'],
            'meta_description' => ['type' => 'string', 'description' => 'New meta description'],
            'template'         => ['type' => 'string', 'enum' => ['default', 'landing', 'blog-post', 'blank']],
            'status'           => ['type' => 'string', 'enum' => ['draft', 'published']],
            'custom_css'       => ['type' => 'string'],
            'custom_js'        => ['type' => 'string'],
            'og_image'         => ['type' => 'string'],
            'lang'             => ['type' => 'string'],
            'hreflang_refs'    => ['type' => 'object', 'additionalProperties' => true],
            'order'            => ['type' => 'integer'],
        ],
        function (array $params, App $app): array {
            $slug = $params['slug'] ?? '';
            unset($params['slug']);
            $page = $app->getPages()->update($slug, $params);
            return ['success' => true, 'page' => $page];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['slug']
    );

    // ─── klytos_delete_page ────────────────────────────────────
    $registry->register(
        'klytos_delete_page',
        'Delete a page by slug.',
        [
            'slug' => ['type' => 'string', 'description' => 'Slug of the page to delete'],
        ],
        function (array $params, App $app): array {
            $deleted = $app->getPages()->delete($params['slug'] ?? '');
            return ['success' => $deleted, 'slug' => $params['slug'] ?? ''];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['slug']
    );

    // ─── klytos_get_page ───────────────────────────────────────
    $registry->register(
        'klytos_get_page',
        'Get a page by slug. Returns all page data including HTML content.',
        [
            'slug' => ['type' => 'string', 'description' => 'Slug of the page to retrieve'],
        ],
        function (array $params, App $app): array {
            return $app->getPages()->get($params['slug'] ?? '');
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['slug']
    );

    // ─── klytos_list_pages ─────────────────────────────────────
    $registry->register(
        'klytos_list_pages',
        'List all pages with optional filtering by status and language.',
        [
            'status' => ['type' => 'string', 'description' => 'Filter: all, published, draft', 'enum' => ['all', 'published', 'draft']],
            'lang'   => ['type' => 'string', 'description' => 'Filter by language code (empty = all)'],
            'limit'  => ['type' => 'integer', 'description' => 'Max results (default 50)'],
            'offset' => ['type' => 'integer', 'description' => 'Offset for pagination'],
        ],
        function (array $params, App $app): array {
            $pages = $app->getPages()->list(
                $params['status'] ?? 'all',
                $params['lang'] ?? '',
                (int) ($params['limit'] ?? 50),
                (int) ($params['offset'] ?? 0)
            );
            return ['pages' => $pages, 'total' => count($pages)];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );
}
