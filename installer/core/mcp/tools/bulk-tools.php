<?php

/**
 * Klytos — MCP Bulk Action Tools
 * Bulk operations on pages via MCP.
 *
 * @package Klytos
 * @since   0.26.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 Jose Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

function registerBulkTools(
    \Klytos\Core\MCP\ToolRegistry $registry,
    \Klytos\Core\App $app
): void {

    $registry->register(
        'klytos_bulk_update_pages',
        'Apply a bulk action to multiple pages at once: publish, draft, trash, delete permanently, restore from trash, or set a custom status.',
        [
            'slugs' => [
                'type'        => 'array',
                'description' => 'Array of page slugs to update.',
                'items'       => ['type' => 'string'],
            ],
            'action' => [
                'type'        => 'string',
                'description' => 'Action to apply. Standard: publish, draft, trash, delete, restore. Use "set_status" to set a custom status (requires status_id parameter).',
                'enum'        => ['publish', 'draft', 'trash', 'delete', 'restore', 'set_status'],
            ],
            'status_id' => [
                'type'        => 'string',
                'description' => 'Custom status ID to set when action is "set_status". Must be a valid status for the pages\' post type.',
            ],
        ],
        function ( array $params, \Klytos\Core\App $app ): array {
            $slugs = $params['slugs'] ?? [];
            $action = $params['action'] ?? '';

            if ( empty( $slugs ) || !is_array( $slugs ) ) {
                throw new \InvalidArgumentException( 'slugs must be a non-empty array of page slugs.' );
            }
            if ( $action === '' ) {
                throw new \InvalidArgumentException( 'action is required.' );
            }

            $pageManager = $app->getPages();
            $processed   = 0;
            $errors      = [];

            klytos_do_action( 'admin.bulk_action.before', $action, $slugs );

            foreach ( $slugs as $slug ) {
                try {
                    switch ( $action ) {
                        case 'publish':
                            $pageManager->update( $slug, ['status' => 'published'] );
                            break;
                        case 'draft':
                            $pageManager->update( $slug, ['status' => 'draft'] );
                            break;
                        case 'trash':
                            $pageManager->delete( $slug );
                            break;
                        case 'delete':
                            $pageManager->permanentDelete( $slug );
                            break;
                        case 'restore':
                            $pageManager->restore( $slug );
                            break;
                        case 'set_status':
                            $statusId = $params['status_id'] ?? '';
                            if ( $statusId === '' ) {
                                throw new \InvalidArgumentException( 'status_id is required for set_status action.' );
                            }
                            $pageManager->update( $slug, ['status' => $statusId] );
                            break;
                    }
                    $processed++;
                } catch ( \Throwable $e ) {
                    $errors[] = $slug . ': ' . $e->getMessage();
                }
            }

            klytos_do_action( 'admin.bulk_action.after', $action, $processed, $errors );

            return [
                'success'   => empty( $errors ),
                'processed' => $processed,
                'errors'    => $errors,
            ];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
        ['slugs', 'action']
    );
}
