<?php

/**
 * Klytos — MCP Shortcode Tools
 * List registered shortcodes via MCP.
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
