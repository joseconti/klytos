<?php

/**
 * Klytos — MCP Post Status Tools
 * CRUD operations for custom post statuses per post type via MCP.
 *
 * @package Klytos
 * @since   0.27.0
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

function registerPostStatusTools( ToolRegistry $registry ): void
{
    // ─── klytos_add_post_status ───────────────────────────────
    $registry->register(
        'klytos_add_post_status',
        'Add a custom workflow status to a post type. System statuses (draft, published, scheduled, trashed) are always available and cannot be added. Custom statuses define additional workflow states like "In Review", "Approved", "Archived". Each status has a label, color (for badges), and can optionally be marked as public (pages with this status will be built to the live site like published pages).',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type ID to add the status to (e.g. "products", "page").'],
            'id'           => ['type' => 'string', 'description' => 'Unique machine name for the status (lowercase, max 20 chars). Cannot be: draft, published, scheduled, trashed.'],
            'label'        => ['type' => 'string', 'description' => 'Human-readable label for the status (e.g. "In Review", "Approved").'],
            'color'        => ['type' => 'string', 'description' => 'Hex color for badge display (e.g. "#f59e0b"). Defaults to "#6b7280" (grey).'],
            'icon'         => ['type' => 'string', 'description' => 'Optional icon identifier (e.g. "eye", "check", "star", "archive").'],
            'is_public'    => ['type' => 'boolean', 'description' => 'If true, pages with this status are built to the public site (like published). Default: false.'],
        ],
        function ( array $params, App $app ): array {
            $postType = $app->getPostTypeManager()->addStatus(
                $params['post_type_id'] ?? '',
                [
                    'id'        => $params['id'] ?? '',
                    'label'     => $params['label'] ?? '',
                    'color'     => $params['color'] ?? '#6b7280',
                    'icon'      => $params['icon'] ?? '',
                    'is_public' => $params['is_public'] ?? false,
                ]
            );
            return ['success' => true, 'post_type' => $postType];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
        ['post_type_id', 'id', 'label']
    );

    // ─── klytos_update_post_status ────────────────────────────
    $registry->register(
        'klytos_update_post_status',
        'Update a custom status definition on a post type. Only provided fields will be changed. System statuses cannot be modified.',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type ID that owns the status.'],
            'id'           => ['type' => 'string', 'description' => 'The status ID to update.'],
            'label'        => ['type' => 'string', 'description' => 'New human-readable label.'],
            'color'        => ['type' => 'string', 'description' => 'New hex color for badge display.'],
            'icon'         => ['type' => 'string', 'description' => 'New icon identifier.'],
            'is_public'    => ['type' => 'boolean', 'description' => 'Whether pages with this status should be built to the public site.'],
        ],
        function ( array $params, App $app ): array {
            $postTypeId = $params['post_type_id'] ?? '';
            $statusId   = $params['id'] ?? '';
            unset( $params['post_type_id'], $params['id'] );

            $postType = $app->getPostTypeManager()->updateStatus( $postTypeId, $statusId, $params );
            return ['success' => true, 'post_type' => $postType];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['post_type_id', 'id']
    );

    // ─── klytos_remove_post_status ────────────────────────────
    $registry->register(
        'klytos_remove_post_status',
        'Remove a custom status from a post type. Pages currently using this status will be automatically reassigned to "draft". System statuses (draft, published, scheduled, trashed) cannot be removed.',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type ID that owns the status.'],
            'id'           => ['type' => 'string', 'description' => 'The status ID to remove.'],
        ],
        function ( array $params, App $app ): array {
            $postType = $app->getPostTypeManager()->removeStatus(
                $params['post_type_id'] ?? '',
                $params['id'] ?? '',
                $app->getPages()
            );
            return ['success' => true, 'post_type' => $postType];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['post_type_id', 'id']
    );

    // ─── klytos_list_post_statuses ────────────────────────────
    $registry->register(
        'klytos_list_post_statuses',
        'List all available statuses (system + custom) for a post type. System statuses (draft, published, scheduled, trashed) are always included. Custom statuses are defined per post type and appear after system statuses.',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type ID to list statuses for (e.g. "products", "page"). Defaults to "page".'],
        ],
        function ( array $params, App $app ): array {
            $postTypeId = $params['post_type_id'] ?? 'page';
            $statuses   = $app->getPostTypeManager()->getStatusesForPostType( $postTypeId );
            return ['success' => true, 'statuses' => $statuses, 'post_type_id' => $postTypeId];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        []
    );
}
