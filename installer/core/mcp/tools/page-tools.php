<?php

/**
 * Klytos — MCP Page Tools
 * CRUD operations for site pages via MCP.
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

function registerPageTools(ToolRegistry $registry): void
{
    // ─── klytos_create_page ────────────────────────────────────
    $registry->register(
        'klytos_create_page',
        'Create a new HTML page or entry for a custom Post Type. Supports hierarchical URLs: "servicios" creates /servicios/, "servicios/marketing" creates /servicios/marketing/. Parent pages must exist first. Provide slug, title, and content_html at minimum. EDITOR MODES — The Post Type editor setting determines content_html format: (A) GUTENBERG editor (default for "page" post type): Use Gutenberg block markup. For text content: <!-- wp:paragraph -->, <!-- wp:heading -->, etc. For complex visual designs: use <!-- wp:html -->...<!-- /wp:html --> blocks containing ANY free HTML/CSS — hero sections, product grids, pricing tables, multi-column layouts, etc. You can MIX both in the same page. (B) TINYMCE/CLASSIC editor: Use plain HTML directly — NO block markup needed. Write standard HTML/CSS with total design freedom. Both modes support TOTAL design freedom. For complex pages, prefer putting section-specific CSS in the custom_css field. Read klytos_get_guide("design-patterns") for ready-to-use visual patterns. meta_description is required for SEO. IMPORTANT FOR CUSTOM POST TYPES: When creating an entry with a custom post_type (not "page"), you MUST first: (1) Call klytos_get_post_type to learn about the Post Type taxonomies. (2) Call klytos_list_custom_fields to discover all Custom Fields and which are required. (3) Inform the administrator which fields are REQUIRED (they cannot be skipped) and which are optional. (4) After creating the entry, use klytos_set_bulk_field_values to set all Custom Field values. List all available taxonomies and their terms so the administrator can classify the entry.',
        [
            'slug'             => ['type' => 'string', 'description' => 'URL slug with hierarchy support. E.g.: "about" → /about/, "servicios/marketing" → /servicios/marketing/. Use / for nested pages. CRITICAL: The homepage MUST use slug "index" — this is the ONLY slug that maps to /index.html at the site root. Do NOT use "inicio", "home", or any other slug for the homepage.'],
            'title'            => ['type' => 'string', 'description' => 'Page title for <title> and <h1>. Max 60 chars. Primary keyword first. Do NOT include the site name (added automatically).'],
            'content_html'     => ['type' => 'string', 'description' => 'Full HTML body content. Format depends on the Post Type editor setting: GUTENBERG (default): wrap content in block comments — <!-- wp:paragraph --> for text, <!-- wp:heading --> for headings, <!-- wp:html -->...<!-- /wp:html --> for free HTML sections with complex designs. TINYMCE: use plain HTML directly, no block markup needed. Check the Post Type editor with klytos_get_post_type before creating content. Read klytos_get_guide("gutenberg-blocks") for Gutenberg syntax and klytos_get_guide("design-patterns") for visual patterns.'],
            'meta_description' => ['type' => 'string', 'description' => 'SEO meta description. REQUIRED. 120-155 chars recommended. Include primary keyword and a call-to-action. Max 160 chars.'],
            'og_image'         => ['type' => 'string', 'description' => 'Open Graph image URL (1200x630px recommended). Used for Facebook, LinkedIn, Twitter previews. Strongly recommended.'],
            'template'         => ['type' => 'string', 'description' => 'Template: default, landing, blog-post, blank', 'enum' => ['default', 'landing', 'blog-post', 'blank']],
            'status'           => ['type' => 'string', 'description' => 'Page status. System statuses: draft, published, scheduled. Custom statuses may also be available depending on the post type — use klytos_list_post_statuses to see all valid statuses. Use "scheduled" with publish_at for future publishing.'],
            'publish_at'       => ['type' => 'string', 'description' => 'ISO 8601 UTC datetime for scheduled publishing (e.g. "2026-05-01T09:00:00Z"). Required when status is "scheduled".'],
            'is_sticky'        => ['type' => 'boolean', 'description' => 'Pin this page to the top of listings (default false).'],
            'password'         => ['type' => 'string', 'description' => 'Password to protect this page. Content is encrypted client-side with AES-256-GCM. Empty string removes protection.'],
            'lang'             => ['type' => 'string', 'description' => 'Language code (es, en, ca...) for hreflang'],
            'custom_css'       => ['type' => 'string', 'description' => 'Custom CSS for this specific page. Use this for section-specific styles instead of inline styles. Define classes here and reference them in content_html. Injected into <head> via {{custom_css}} placeholder. Supports any valid CSS including @media queries, :hover states, animations, gradients, etc.'],
            'custom_js'        => ['type' => 'string', 'description' => 'Custom JS for this page'],
            'hreflang_refs'    => ['type' => 'object', 'description' => 'Map of lang to slug for alternate versions. E.g.: {"en": "en/about", "es": "about"}', 'additionalProperties' => true],
            'content'          => ['type' => 'object', 'description' => 'v2.0 structured block content. Object keyed by block_id with slot data. E.g.: {"hero": {"heading": "Welcome", "cta_url": "/contact/"}, "testimonials": {"heading": "Reviews"}}. When provided with a template, the build engine assembles blocks instead of using content_html.', 'additionalProperties' => true],
            'order'            => ['type' => 'integer', 'description' => 'Sort order (lower = first)'],
            'post_type'        => ['type' => 'string', 'description' => 'Post type identifier. Default "page". Use the custom post type slug for custom content (e.g. "casas", "productos").'],
            'llm_optional'     => ['type' => 'boolean', 'description' => 'Move page to "Optional" section of llms.txt (default false). Use for legal/privacy pages.'],
            'llm_exclude'      => ['type' => 'boolean', 'description' => 'Exclude page from llms.txt, llms-full.txt, and .html.md generation (default false). Use for internal/staging pages.'],
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

            // Only warn about missing Gutenberg markup if the post type uses the Gutenberg editor.
            if ( ! empty( $params['content_html'] ) && strpos( $params['content_html'], '<!-- wp:' ) === false ) {
                $postType = $params['post_type'] ?? 'page';
                $editorType = 'gutenberg';
                try {
                    $ptData = $app->getPostTypeManager()->get($postType);
                    $editorType = $ptData['editor'] ?? 'gutenberg';
                } catch (\Throwable $e) {
                    // Post type not found, assume gutenberg.
                }
                if ($editorType === 'gutenberg') {
                    $warnings[] = 'content_html does not contain Gutenberg block markup (this Post Type uses the Gutenberg editor). Tip: wrap free HTML sections in <!-- wp:html -->...<!-- /wp:html --> for visual editor compatibility while keeping full design freedom. If this Post Type uses TinyMCE, this warning can be ignored.';
                }
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
            'status'           => ['type' => 'string', 'description' => 'Page status. System: draft, published, scheduled. Custom statuses accepted if defined on the post type.'],
            'publish_at'       => ['type' => 'string', 'description' => 'ISO 8601 UTC datetime for scheduled publishing. Required when status is "scheduled".'],
            'is_sticky'        => ['type' => 'boolean', 'description' => 'Pin this page to the top of listings.'],
            'password'         => ['type' => 'string', 'description' => 'Password to protect page content. Empty string removes protection.'],
            'custom_css'       => ['type' => 'string'],
            'custom_js'        => ['type' => 'string'],
            'og_image'         => ['type' => 'string'],
            'lang'             => ['type' => 'string'],
            'hreflang_refs'    => ['type' => 'object', 'additionalProperties' => true],
            'content'          => ['type' => 'object', 'description' => 'v2.0 structured block content keyed by block_id', 'additionalProperties' => true],
            'order'            => ['type' => 'integer'],
            'post_type'        => ['type' => 'string', 'description' => 'Change the post type'],
            'llm_optional'     => ['type' => 'boolean', 'description' => 'Move page to "Optional" section of llms.txt.'],
            'llm_exclude'      => ['type' => 'boolean', 'description' => 'Exclude page from all LLM discoverability files.'],
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
        'Move a page to trash (soft delete). The page can be restored later with klytos_restore_page. To permanently delete, use klytos_permanent_delete_page.',
        [
            'slug' => ['type' => 'string', 'description' => 'Slug of the page to trash'],
        ],
        function (array $params, App $app): array {
            $deleted = $app->getPages()->delete( $params['slug'] ?? '' );
            return ['success' => $deleted, 'slug' => $params['slug'] ?? '', 'info' => 'Page moved to trash. Use klytos_restore_page to undo or klytos_permanent_delete_page to remove permanently.'];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['slug']
    );

    // ─── klytos_restore_page ─────────────────────────────────
    $registry->register(
        'klytos_restore_page',
        'Restore a page from the trash to its previous status (draft or published).',
        [
            'slug' => ['type' => 'string', 'description' => 'Slug of the trashed page to restore'],
        ],
        function (array $params, App $app): array {
            $page = $app->getPages()->restore( $params['slug'] ?? '' );
            return ['success' => true, 'page' => $page];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
        ['slug']
    );

    // ─── klytos_permanent_delete_page ────────────────────────
    $registry->register(
        'klytos_permanent_delete_page',
        'Permanently delete a page from storage. This action cannot be undone. The page does NOT need to be in trash first.',
        [
            'slug' => ['type' => 'string', 'description' => 'Slug of the page to permanently delete'],
        ],
        function (array $params, App $app): array {
            $deleted = $app->getPages()->permanentDelete( $params['slug'] ?? '' );
            return ['success' => $deleted, 'slug' => $params['slug'] ?? ''];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['slug']
    );

    // ─── klytos_empty_trash ──────────────────────────────────
    $registry->register(
        'klytos_empty_trash',
        'Permanently delete ALL pages in the trash. This action cannot be undone.',
        [],
        function (array $params, App $app): array {
            $count = $app->getPages()->emptyTrash();
            return ['success' => true, 'pages_deleted' => $count];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true]
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
            'status'    => ['type' => 'string', 'description' => 'Filter by status. System: all (excludes trash), published, draft, scheduled, trashed. Custom status IDs also accepted (e.g. "review", "approved").'],
            'lang'      => ['type' => 'string', 'description' => 'Filter by language code (empty = all)'],
            'post_type' => ['type' => 'string', 'description' => 'Filter by post type slug (e.g. "page", "casas"). Empty = all types.'],
            'limit'     => ['type' => 'integer', 'description' => 'Max results (default 50)'],
            'offset'    => ['type' => 'integer', 'description' => 'Offset for pagination'],
        ],
        function (array $params, App $app): array {
            $pages = $app->getPages()->list(
                $params['status'] ?? 'all',
                $params['lang'] ?? '',
                (int) ($params['limit'] ?? 50),
                (int) ($params['offset'] ?? 0),
                $params['post_type'] ?? ''
            );
            return ['pages' => $pages, 'total' => count($pages)];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    // ─── Post Locking Tools ─────────────────────────────────────

    $registry->register(
        'klytos_lock_page',
        'Acquire an editing lock on a page to prevent concurrent edits. Lock expires after 5 minutes without renewal.',
        [
            'slug'    => ['type' => 'string', 'description' => 'Page slug to lock.'],
            'user_id' => ['type' => 'string', 'description' => 'User ID requesting the lock.'],
        ],
        function ( array $params, App $app ): array {
            $slug   = $params['slug'] ?? '';
            $userId = $params['user_id'] ?? '';
            if ( empty( $slug ) || empty( $userId ) ) {
                throw new \InvalidArgumentException( 'slug and user_id are required.' );
            }
            return $app->getPages()->acquireLock( $slug, $userId );
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
        ['slug', 'user_id']
    );

    $registry->register(
        'klytos_unlock_page',
        'Release an editing lock on a page.',
        [
            'slug'    => ['type' => 'string', 'description' => 'Page slug to unlock.'],
            'user_id' => ['type' => 'string', 'description' => 'User ID releasing the lock.'],
        ],
        function ( array $params, App $app ): array {
            $slug   = $params['slug'] ?? '';
            $userId = $params['user_id'] ?? '';
            if ( empty( $slug ) || empty( $userId ) ) {
                throw new \InvalidArgumentException( 'slug and user_id are required.' );
            }
            $released = $app->getPages()->releaseLock( $slug, $userId );
            return ['success' => $released];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
        ['slug', 'user_id']
    );

    $registry->register(
        'klytos_check_page_lock',
        'Check whether a page is currently locked for editing and by whom.',
        [
            'slug' => ['type' => 'string', 'description' => 'Page slug to check.'],
        ],
        function ( array $params, App $app ): array {
            $slug = $params['slug'] ?? '';
            if ( empty( $slug ) ) {
                throw new \InvalidArgumentException( 'slug is required.' );
            }
            $lock = $app->getPages()->checkLock( $slug );
            return ['locked' => $lock !== null, 'lock' => $lock];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['slug']
    );
}
