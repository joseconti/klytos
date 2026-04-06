<?php

/**
 * Klytos — MCP Export Tools
 * Export site content in JSON, WXR (WordPress XML), or CSV format.
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

namespace Klytos\Core\MCP\Tools;

use Klytos\Core\App;
use Klytos\Core\ExportManager;
use Klytos\Core\MCP\ToolRegistry;

function registerExportTools( ToolRegistry $registry ): void
{
    // ─── klytos_export_site ──────────────────────────────────
    $registry->register(
        'klytos_export_site',
        'Export site content. Returns the export data directly. Formats: "json" (full site archive with pages, post types, blocks, menus, theme, config), "wxr" (WordPress-compatible XML for migration to WordPress), "csv" (tabular page list). For large sites, the response may be large.',
        [
            'format'      => ['type' => 'string', 'description' => 'Export format', 'enum' => ['json', 'wxr', 'csv']],
            'post_type'   => ['type' => 'string', 'description' => 'Filter by post type (for wxr/csv). Empty = all.'],
            'collections' => ['type' => 'array', 'description' => 'Collections to include in JSON export (empty = all). Options: pages, post_types, blocks, page_templates, menu, theme, site_config', 'items' => ['type' => 'string']],
        ],
        function ( array $params, App $app ): array {
            $exporter = new ExportManager( $app );
            $format   = $params['format'] ?? 'json';

            switch ( $format ) {
                case 'csv':
                    $result = $exporter->exportCsv( $params['post_type'] ?? '' );
                    break;
                case 'wxr':
                    $result = $exporter->exportWxr( $params['post_type'] ?? '' );
                    break;
                default:
                    $result = $exporter->exportJson( $params['collections'] ?? [] );
                    break;
            }

            return [
                'success'  => true,
                'format'   => $result['format'],
                'filename' => $result['filename'],
                'mime'     => $result['mime'],
                'data'     => $result['data'],
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['format']
    );
}
