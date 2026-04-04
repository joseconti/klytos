<?php

/**
 * Klytos — MCP Comment Tools
 * Manage page comments via MCP: list, moderate, bulk actions, settings.
 *
 * @package Klytos
 * @since   0.18.0
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

function registerCommentTools( ToolRegistry $registry ): void
{
    // ─── klytos_list_comments ────────────────────────────────
    $registry->register(
        'klytos_list_comments',
        'List comments with optional filters by status and page slug. Returns comment data including author, content, status, and threading info.',
        [
            'status'    => ['type' => 'string', 'description' => 'Filter: all, pending, approved, spam, trash', 'enum' => ['all', 'pending', 'approved', 'spam', 'trash']],
            'page_slug' => ['type' => 'string', 'description' => 'Filter by page slug (empty = all pages)'],
            'limit'     => ['type' => 'integer', 'description' => 'Max results (default 50)'],
            'offset'    => ['type' => 'integer', 'description' => 'Offset for pagination'],
        ],
        function ( array $params, App $app ): array {
            $comments = $app->getCommentManager()->list(
                $params['status'] ?? 'all',
                $params['page_slug'] ?? '',
                (int) ($params['limit'] ?? 50),
                (int) ($params['offset'] ?? 0)
            );
            return [
                'comments' => $comments,
                'total'    => count( $comments ),
                'pending_count' => $app->getCommentManager()->count( 'pending' ),
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    // ─── klytos_moderate_comment ─────────────────────────────
    $registry->register(
        'klytos_moderate_comment',
        'Moderate a single comment: approve, mark as spam, or move to trash.',
        [
            'id'     => ['type' => 'string', 'description' => 'Comment ID to moderate'],
            'status' => ['type' => 'string', 'description' => 'New status', 'enum' => ['approved', 'pending', 'spam', 'trash']],
        ],
        function ( array $params, App $app ): array {
            $comment = $app->getCommentManager()->moderate(
                $params['id'] ?? '',
                $params['status'] ?? 'approved'
            );
            return ['success' => true, 'comment' => $comment];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
        ['id', 'status']
    );

    // ─── klytos_bulk_moderate_comments ─────���─────────────────
    $registry->register(
        'klytos_bulk_moderate_comments',
        'Moderate multiple comments at once. Useful for batch-approving pending comments or cleaning up spam.',
        [
            'ids'    => ['type' => 'array', 'description' => 'Array of comment IDs to moderate', 'items' => ['type' => 'string']],
            'status' => ['type' => 'string', 'description' => 'New status for all comments', 'enum' => ['approved', 'pending', 'spam', 'trash']],
        ],
        function ( array $params, App $app ): array {
            $count = $app->getCommentManager()->bulkModerate(
                $params['ids'] ?? [],
                $params['status'] ?? 'approved'
            );
            return ['success' => true, 'moderated_count' => $count];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
        ['ids', 'status']
    );

    // ─── klytos_delete_comment ───────────────────────────────
    $registry->register(
        'klytos_delete_comment',
        'Permanently delete a comment. This cannot be undone.',
        [
            'id' => ['type' => 'string', 'description' => 'Comment ID to delete permanently'],
        ],
        function ( array $params, App $app ): array {
            $deleted = $app->getCommentManager()->delete( $params['id'] ?? '' );
            return ['success' => $deleted];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['id']
    );

    // ─── klytos_get_comment_settings ─────────────────────────
    $registry->register(
        'klytos_get_comment_settings',
        'Get the current comment system settings (enabled, moderation mode, etc.).',
        [],
        function ( array $params, App $app ): array {
            return [
                'comments_enabled'  => $app->getSiteConfig()->getValue( 'comments_enabled', false ),
                'require_moderation' => $app->getSiteConfig()->getValue( 'comments_require_moderation', true ),
                'max_thread_depth'  => $app->getSiteConfig()->getValue( 'comments_max_thread_depth', 3 ),
                'honeypot_enabled'  => $app->getSiteConfig()->getValue( 'comments_honeypot', true ),
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    // ─── klytos_set_comment_settings ─────────────────────────
    $registry->register(
        'klytos_set_comment_settings',
        'Update comment system settings. Enable/disable comments, configure moderation, threading depth, anti-spam.',
        [
            'comments_enabled'   => ['type' => 'boolean', 'description' => 'Enable or disable the comment system site-wide'],
            'require_moderation' => ['type' => 'boolean', 'description' => 'If true, comments must be approved before appearing on the site'],
            'max_thread_depth'   => ['type' => 'integer', 'description' => 'Maximum nesting depth for threaded comments (1-5, default 3)'],
            'honeypot_enabled'   => ['type' => 'boolean', 'description' => 'Enable honeypot anti-spam field in comment forms'],
        ],
        function ( array $params, App $app ): array {
            $config = $app->getSiteConfig();

            if ( isset( $params['comments_enabled'] ) ) {
                $config->setValue( 'comments_enabled', (bool) $params['comments_enabled'] );
            }
            if ( isset( $params['require_moderation'] ) ) {
                $config->setValue( 'comments_require_moderation', (bool) $params['require_moderation'] );
            }
            if ( isset( $params['max_thread_depth'] ) ) {
                $depth = max( 1, min( 5, (int) $params['max_thread_depth'] ) );
                $config->setValue( 'comments_max_thread_depth', $depth );
            }
            if ( isset( $params['honeypot_enabled'] ) ) {
                $config->setValue( 'comments_honeypot', (bool) $params['honeypot_enabled'] );
            }

            return ['success' => true, 'settings' => [
                'comments_enabled'   => $config->getValue( 'comments_enabled', false ),
                'require_moderation' => $config->getValue( 'comments_require_moderation', true ),
                'max_thread_depth'   => $config->getValue( 'comments_max_thread_depth', 3 ),
                'honeypot_enabled'   => $config->getValue( 'comments_honeypot', true ),
            ]];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true]
    );
}
