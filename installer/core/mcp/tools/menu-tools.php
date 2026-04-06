<?php

/**
 * Klytos — MCP Menu Tools
 * Navigation menu management via MCP.
 *
 * @package Klytos
 * @since   1.0.0
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

function registerMenuTools(ToolRegistry $registry): void
{
    $registry->register(
        'klytos_set_menu',
        'Define the complete navigation menu structure. Replaces the existing menu.',
        [
            'items' => [
                'type' => 'array',
                'description' => 'Array of menu items. Each item: {label, url, target (_self/_blank), icon, children (nested items), order}',
                'items' => ['type' => 'object', 'additionalProperties' => true],
            ],
        ],
        function (array $params, App $app): array {
            $menu = $app->getMenu()->set($params['items'] ?? []);
            return ['success' => true, 'menu' => $menu];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['items']
    );

    $registry->register(
        'klytos_get_menu',
        'Get the current navigation menu structure.',
        [],
        function (array $params, App $app): array {
            return $app->getMenu()->get();
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_add_menu_item',
        'Add a single item to the navigation menu.',
        [
            'label'    => ['type' => 'string', 'description' => 'Display text'],
            'url'      => ['type' => 'string', 'description' => 'Link URL (relative or absolute)'],
            'target'   => ['type' => 'string', 'description' => 'Link target', 'enum' => ['_self', '_blank']],
            'icon'     => ['type' => 'string', 'description' => 'Icon class or emoji'],
            'order'    => ['type' => 'integer', 'description' => 'Sort order'],
            'children' => ['type' => 'array', 'description' => 'Nested sub-items (same structure)', 'items' => ['type' => 'object', 'additionalProperties' => true]],
        ],
        function (array $params, App $app): array {
            $menu = $app->getMenu()->addItem($params);
            return ['success' => true, 'menu' => $menu];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
        ['label', 'url']
    );

    $registry->register(
        'klytos_remove_menu_item',
        'Remove a menu item by its ID.',
        [
            'id' => ['type' => 'string', 'description' => 'ID of the menu item to remove'],
        ],
        function (array $params, App $app): array {
            $menu = $app->getMenu()->removeItem($params['id'] ?? '');
            return ['success' => true, 'menu' => $menu];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['id']
    );
}
