<?php

/**
 * Klytos — MCP Shortcode Tools
 * List registered shortcodes via MCP.
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

function registerShortcodeTools(
    \Klytos\Core\MCP\ToolRegistry $registry,
    \Klytos\Core\App $app
): void {

    $registry->register(
        'klytos_list_shortcodes',
        'List all registered shortcodes with their tags and descriptions. Built-in shortcodes include [year], [site_name], [page_count], and [current_date].',
        [],
        function ( array $params, \Klytos\Core\App $app ): array {
            $shortcodes = $app->getShortcodeManager()->listAll();
            return [
                'shortcodes' => $shortcodes,
                'count'      => count( $shortcodes ),
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );
}
