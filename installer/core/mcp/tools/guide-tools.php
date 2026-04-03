<?php

/**
 * Klytos — MCP Guide Tools
 *
 * Provides AI assistants with documentation on how to use Klytos correctly.
 * These guides teach the AI about Gutenberg block markup, SEO best practices,
 * accessibility standards, plugin development, and more.
 *
 * AI assistants SHOULD call klytos_get_guide('gutenberg-blocks') before
 * creating any page content, and klytos_get_guide('seo-content') before
 * setting SEO fields.
 *
 * @copyright 2024-2026 José Conti. All rights reserved.
 * @license   Elastic License 2.0 (ELv2)
 */

declare(strict_types=1);

namespace Klytos\Core\MCP\Tools;

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

function registerGuideTools( ToolRegistry $registry ): void
{
    // ─── klytos_list_guides ──────────────────────────────────────
    $registry->register(
        'klytos_list_guides',
        'List all available guides for AI assistants. Call this first to discover what documentation is available. Guides teach you how to create pages with correct Gutenberg block markup, SEO best practices, accessibility standards, and more. You SHOULD read the relevant guides before creating or editing content.',
        [],
        function ( array $params, App $app ): array {
            $guidesDir = $app->getRootPath() . '/core/guides';
            $guides    = [];

            if ( is_dir( $guidesDir ) ) {
                $files = glob( $guidesDir . '/*.md' );
                foreach ( $files as $file ) {
                    $id   = basename( $file, '.md' );
                    $name = ucwords( str_replace( '-', ' ', $id ) );

                    // Read the first line after the YAML frontmatter for description.
                    $content = file_get_contents( $file );
                    $desc    = '';

                    // Get the description from frontmatter if present.
                    if ( preg_match( '/^---\s*\n.*?description:\s*"([^"]+)"/s', $content, $m ) ) {
                        $desc = $m[1];
                    } elseif ( preg_match( '/^#\s+(.+)$/m', $content, $m ) ) {
                        $desc = trim( $m[1] );
                    }

                    $guides[] = [
                        'id'          => $id,
                        'name'        => $name,
                        'description' => $desc,
                        'size_bytes'  => filesize( $file ),
                    ];
                }
            }

            return [
                'guides' => $guides,
                'hint'   => 'Call klytos_get_guide with a guide id to read the full documentation. You SHOULD read "gutenberg-blocks" before creating pages, "seo-content" before setting SEO fields, "design-patterns" when creating visually rich pages or replicating a design reference, and "forms" before creating any contact form or form.',
            ];
        },
        [ 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ],
        []
    );

    // ─── klytos_get_guide ────────────────────────────────────────
    $registry->register(
        'klytos_get_guide',
        'Get a complete guide for AI assistants. Available guides: "gutenberg-blocks" (REQUIRED before creating page content — teaches editor modes, Gutenberg block markup AND free HTML design via wp:html blocks), "design-patterns" (REQUIRED when replicating a website design or creating visually rich pages — ready-to-use patterns for hero sections, product grids, pricing tables, testimonials, footers, etc.), "seo-content" (REQUIRED before setting title/meta_description — teaches SEO best practices), "post-types-and-fields" (REQUIRED before creating Post Types, Taxonomies, or Custom Fields — teaches all 27 field types, taxonomies, and the complete workflow), "forms" (REQUIRED before creating contact forms or any form — teaches klytos-forms plugin activation, all 18 field types, conditional logic, multi-step forms, notifications, anti-spam, and embedding forms in pages via {{form:form-id}} shortcode), "accessibility" (WCAG 2.1 AA compliance), "plugin-development" (how to build Klytos plugins), "seo-and-indexing" (sitemap, llms.txt, robots.txt), "security-architecture" (encryption, auth, CSP), "core-development" (system architecture). Always read "gutenberg-blocks" and "seo-content" before creating pages. Read "design-patterns" when creating visually complex pages or replicating a design reference. Read "post-types-and-fields" before creating or configuring Post Types. Read "forms" before creating any contact form or form.',
        [
            'guide_id' => [
                'type'        => 'string',
                'description' => 'Guide ID. Use klytos_list_guides to see all available guides. Most important: "gutenberg-blocks" (block markup for visual editor), "seo-content" (SEO optimization).',
            ],
        ],
        function ( array $params, App $app ): array {
            $guideId   = $params['guide_id'] ?? '';
            $guidesDir = $app->getRootPath() . '/core/guides';

            // Sanitize: only alphanumeric and hyphens.
            $guideId  = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $guideId ) );
            $filePath = $guidesDir . '/' . $guideId . '.md';

            if ( ! file_exists( $filePath ) ) {
                // List available guides in the error message.
                $available = [];
                if ( is_dir( $guidesDir ) ) {
                    foreach ( glob( $guidesDir . '/*.md' ) as $f ) {
                        $available[] = basename( $f, '.md' );
                    }
                }

                return [
                    'error'            => 'Guide not found: ' . $guideId,
                    'available_guides' => $available,
                ];
            }

            $content = file_get_contents( $filePath );

            // Remove YAML frontmatter if present.
            $content = preg_replace( '/^---\s*\n.*?\n---\s*\n/s', '', $content );

            return [
                'guide_id' => $guideId,
                'content'  => trim( $content ),
            ];
        },
        [ 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ],
        [ 'guide_id' ]
    );
}
