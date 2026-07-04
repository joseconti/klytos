<?php

/**
 * Klytos — MCP Part Tools
 * Unified site-wide parts management via MCP.
 *
 * Parts are the single source of truth for shared site fragments:
 * header, footer, menu, top-bar, cookie-banner, head, scripts...
 * A part is edited ONCE and propagates to every page on the next build
 * via the {{klytos_part:NAME}} placeholder in templates.
 *
 * These tools supersede the old split between template parts (files) and
 * global blocks (storage). Parts created here live in storage and are
 * resolved with the hierarchy:
 * custom-templates/parts/ > plugin filter > storage > templates/parts/.
 *
 * @package Klytos
 * @since   0.32.0
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

function registerPartTools(ToolRegistry $registry): void
{
    $registry->register(
        'klytos_list_parts',
        'List all site parts (header, footer, menu, top-bar...) from every source, with the effective source of each one (custom file > plugin > storage > core). Parts are the SINGLE place where shared site elements live: edit a part once and it updates on ALL pages on the next build.',
        [],
        function (array $params, App $app): array {
            return ['parts' => array_values($app->getPartManager()->list())];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_get_part',
        'Get a site part: its resolved HTML (following the hierarchy custom file > plugin > storage > core), effective source, slot definitions, and current data. Use this before editing a part to see what exists.',
        [
            'id' => ['type' => 'string', 'description' => 'Part ID (e.g. "header", "footer", "top-bar")'],
        ],
        function (array $params, App $app): array {
            $id = $params['id'] ?? '';
            if (empty($id)) {
                throw new \RuntimeException('Part ID is required.');
            }

            $parts = $app->getPartManager();
            $html  = $parts->resolveHtml($id);

            if ($html === null) {
                throw new \RuntimeException("Part not found: {$id}");
            }

            $record = [];
            try {
                $record = $parts->get($id);
            } catch (\RuntimeException $e) {
                // No storage record — file/plugin-only part.
            }

            return [
                'id'     => $id,
                'source' => $parts->getSource($id),
                'html'   => $html,
                'slots'  => $record['slots'] ?? [],
                'data'   => $record['data'] ?? [],
                'css'    => $record['css'] ?? '',
                'js'     => $record['js'] ?? '',
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['id']
    );

    $registry->register(
        'klytos_set_part',
        'Create or update a site part (header, footer, menu, top-bar, announcement banner...) with COMPLETELY FREE HTML/CSS design. This is THE tool for shared site elements: the part is stored once and rendered on ALL pages that reference {{klytos_part:ID}} in their template — change it here and the whole site updates on the next build. The HTML can include <style> blocks, inline styles, SVG, CSS Grid, Flexbox, animations, media queries. Use {{variables}} for dynamic content: {{site_name}}, {{menu_html}}, {{base_path}}, {{site_url}}. Optionally define typed slots and editable data: slot placeholders like {{cta_text}} are replaced with the part data at build time, so content can be changed later with klytos_set_part_data without touching the HTML. NOTE: a file in custom-templates/parts/ with the same ID takes precedence over this part — check the source with klytos_get_part first.',
        [
            'id'          => ['type' => 'string', 'description' => 'Part ID (e.g. "header", "footer", "top-bar"). Standard parts referenced by core templates: head, header, footer, scripts.'],
            'html'        => ['type' => 'string', 'description' => 'Part HTML. Can contain {{site variables}} and {{slot}} placeholders.'],
            'name'        => ['type' => 'string', 'description' => 'Human-readable name'],
            'description' => ['type' => 'string', 'description' => 'What this part is for'],
            'slots'       => ['type' => 'array', 'description' => 'Optional slot definitions: [{name, type, label, required, default}]. Types: text, richtext, image, url, icon, color, number, select, boolean, array, html, date, email, phone.', 'items' => ['type' => 'object']],
            'data'        => ['type' => 'object', 'description' => 'Slot values applied at build time (e.g. {"cta_text": "Buy now"})', 'additionalProperties' => true],
            'css'         => ['type' => 'string', 'description' => 'Optional CSS, emitted as a <style> tag with the part'],
            'js'          => ['type' => 'string', 'description' => 'Optional JS, emitted as a <script> tag with the part'],
        ],
        function (array $params, App $app): array {
            $parts = $app->getPartManager();
            $part  = $parts->save($params);

            $source  = $parts->getSource($part['id']);
            $warning = null;
            if ($source === 'custom') {
                $warning = 'A file override exists in custom-templates/parts/' . $part['id']
                         . '.html and takes precedence over this part. Delete it with'
                         . ' klytos_delete_custom_template_part to use the stored part.';
            } elseif ($source === 'plugin') {
                $warning = 'A plugin provides this part via filter and takes precedence over the stored part.';
            }

            $app->getTemplateResolver()->clearCache();

            return array_filter([
                'success' => true,
                'part'    => $part['id'],
                'source'  => $source,
                'warning' => $warning,
            ], fn($v) => $v !== null);
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['id', 'html']
    );

    $registry->register(
        'klytos_set_part_data',
        'Update ONLY the data (slot values) of a site part, without touching its HTML. This is the cheap "edit once, propagate everywhere" path: e.g. change the announcement text of the top-bar or the CTA of the header. Run klytos_build_site afterwards to publish the change on all pages.',
        [
            'id'   => ['type' => 'string', 'description' => 'Part ID'],
            'data' => ['type' => 'object', 'description' => 'Slot values (key => value)', 'additionalProperties' => true],
        ],
        function (array $params, App $app): array {
            $id = $params['id'] ?? '';
            if (empty($id)) {
                throw new \RuntimeException('Part ID is required.');
            }

            $part = $app->getPartManager()->setData($id, $params['data'] ?? []);

            return ['success' => true, 'part' => $id, 'data' => $part['data']];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['id', 'data']
    );

    $registry->register(
        'klytos_delete_part',
        'Delete a site part from storage. Resolution falls back to the next level in the hierarchy (plugin or core part with the same ID, if any). File overrides in custom-templates/parts/ are NOT touched by this tool.',
        [
            'id' => ['type' => 'string', 'description' => 'Part ID to delete'],
        ],
        function (array $params, App $app): array {
            $id = $params['id'] ?? '';
            if (empty($id)) {
                throw new \RuntimeException('Part ID is required.');
            }

            if (!$app->getPartManager()->delete($id)) {
                throw new \RuntimeException("Part not found in storage: {$id}");
            }

            $app->getTemplateResolver()->clearCache();

            return ['success' => true, 'deleted' => $id];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
        ['id']
    );

    $registry->register(
        'klytos_migrate_global_blocks_to_parts',
        'Migrate all global-scope blocks (header, footer, top-bar...) to unified site parts. Idempotent: blocks that already have a part with the same ID are skipped. The original blocks are kept (cleanup happens in a later phase) so existing page templates keep working. After migrating, edit shared elements with klytos_set_part / klytos_set_part_data instead of klytos_set_global_block_data.',
        [],
        function (array $params, App $app): array {
            $result = $app->getPartManager()->migrateGlobalBlocks();

            return [
                'success'  => true,
                'migrated' => $result['migrated'],
                'skipped'  => $result['skipped'],
            ];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true]
    );
}
