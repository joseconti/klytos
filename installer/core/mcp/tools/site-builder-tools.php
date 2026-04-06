<?php

/**
 * Klytos — MCP Site Builder Tools
 *
 * Entry point for the guided site creation process. Returns the complete
 * site-builder guide so the AI assistant knows exactly how to walk the
 * user through building their website from scratch.
 *
 * @package   Klytos
 * @since     1.0.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

namespace Klytos\Core\MCP\Tools;

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

function registerSiteBuilderTools( ToolRegistry $registry ): void
{
    $registry->register(
        'klytos_start_site_builder',
        'Start the guided process to build a complete website from scratch. Call this when the user wants to create, set up, or configure their website after installation. Returns a comprehensive step-by-step conversational guide covering 9 phases: discovery, design reference, global config, theme, content structure, templates, content creation, additional features, and launch. The guide tells you exactly what to ask, what tools to use, and in what order. CRITICAL: When a user provides a reference website, only replicate the VISUAL DESIGN (layout, colors, typography, spacing). NEVER copy text content, blog posts, articles, or images from reference sites — generate original placeholders or ask the user for their own content.',
        [
            'site_type' => [
                'type'        => 'string',
                'description' => 'Optional. Pre-selected site type to skip some Phase 1 questions. Values: blog, corporate, portfolio, catalog, landing, documentation.',
            ],
            'language' => [
                'type'        => 'string',
                'description' => 'Optional. Preferred language for the guide context (e.g., "es", "en"). Does not change the guide content but helps the assistant adapt its responses.',
            ],
        ],
        function ( array $params, App $app ): array {
            $guidesDir = $app->getRootPath() . '/core/guides';
            $mainGuide = $guidesDir . '/site-builder.md';

            if ( ! file_exists( $mainGuide ) ) {
                // List available guides to help diagnose.
                $available = [];
                if ( is_dir( $guidesDir ) ) {
                    foreach ( glob( $guidesDir . '/*.md' ) as $f ) {
                        $available[] = basename( $f, '.md' );
                    }
                }

                return [
                    'error'            => 'Site builder guide not found. Please verify the installation.',
                    'guides_dir'       => $guidesDir,
                    'available_guides' => $available,
                ];
            }

            $content = file_get_contents( $mainGuide );

            // Remove YAML frontmatter if present.
            $content = preg_replace( '/^---\s*\n.*?\n---\s*\n/s', '', $content );

            $siteType = $params['site_type'] ?? null;
            $language = $params['language'] ?? null;

            // Build response — plain array, ToolRegistry wraps it.
            $response = [
                'guide'           => trim( $content ),
                'hint'            => 'Follow the 9 phases in order. Start with Phase 1 (Discovery). Each phase ends with user confirmation before proceeding. Use klytos_get_guide() to load auxiliary guides referenced in each phase (site-builder-types, site-builder-palettes, site-builder-page-trees, site-builder-content, site-builder-checklist).',
                'auxiliary_guides' => [
                    'site-builder-types'      => 'Site types and recommended structures (CPTs, fields, taxonomies)',
                    'site-builder-palettes'   => 'Color palettes by sector and site type',
                    'site-builder-page-trees' => 'Page hierarchies and menu structures by site type',
                    'site-builder-content'    => 'Content generation templates, typography combos, image guidance',
                    'site-builder-checklist'  => 'Final verification checklist and launch sequence',
                ],
            ];

            if ( $siteType ) {
                $response['pre_selected_site_type'] = $siteType;
                $response['hint'] .= ' The user has pre-selected site type "' . $siteType . '". You can skip the site type question in Phase 1 but still ask about sector, language, audience, etc.';
            }

            if ( $language ) {
                $response['preferred_language'] = $language;
                $response['hint'] .= ' The user prefers communication in language "' . $language . '". Adapt your responses accordingly.';
            }

            return $response;
        },
        [ 'readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true ],
        []
    );
}
