<?php

/**
 * Klytos — MCP Bulk Action Tools
 * Bulk operations on pages via MCP.
 *
 * @package Klytos
 * @since   0.26.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 Jose Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

function registerBulkTools(
    \Klytos\Core\MCP\ToolRegistry $registry,
    \Klytos\Core\App $app
): void {

    $registry->register(
        'klytos_bulk_update_pages',
        'Apply a bulk action to multiple pages at once: publish, draft, trash, delete permanently, or restore from trash.',
        [
            'slugs' => [
                'type'        => 'array',
                'description' => 'Array of page slugs to update.',
                'items'       => ['type' => 'string'],
            ],
            'action' => [
                'type'        => 'string',
                'description' => 'Action to apply to all listed pages.',
                'enum'        => ['publish', 'draft', 'trash', 'delete', 'restore'],
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
