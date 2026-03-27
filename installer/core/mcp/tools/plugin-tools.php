<?php
/**
 * Klytos — MCP Plugin Management Tools
 * Registers MCP tools for listing, activating, and deactivating plugins.
 *
 * Tools registered:
 * - klytos_list_plugins: List all discovered plugins with status.
 * - klytos_activate_plugin: Activate a plugin by ID.
 * - klytos_deactivate_plugin: Deactivate a plugin by ID.
 *
 * @package Klytos
 * @since   2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

/**
 * Register plugin management MCP tools.
 *
 * @param ToolRegistry $registry The MCP tool registry to register tools into.
 * @param App          $app      The application instance.
 */
function registerPluginTools(ToolRegistry $registry, App $app): void
{
    // ─── klytos_list_plugins ─────────────────────────────────
    $registry->register(
        'klytos_list_plugins',
        'List all installed plugins with their status, version, and metadata.',
        [], // No input parameters required.
        function (array $params, App $app): array {
            $pluginLoader = $app->getPluginLoader();
            $plugins      = $pluginLoader->listAll();

            return [
                'total'   => count($plugins),
                'plugins' => $plugins,
            ];
        },
        [
            'title'          => 'List Plugins',
            'readOnlyHint'   => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
            'openWorldHint'  => false,
        ]
    );

    // ─── klytos_activate_plugin ──────────────────────────────
    $registry->register(
        'klytos_activate_plugin',
        'Activate a plugin by its ID. Runs the plugin\'s install script on first activation.',
        [
            'plugin_id' => [
                'type'        => 'string',
                'description' => 'The plugin ID to activate (matches the directory name in plugins/).',
            ],
        ],
        function (array $params, App $app): array {
            $pluginId = $params['plugin_id'] ?? '';

            if (empty($pluginId)) {
                throw new \InvalidArgumentException('plugin_id is required.');
            }

            $pluginLoader = $app->getPluginLoader();
            $result       = $pluginLoader->activate($pluginId);

            return $result;
        },
        [
            'title'           => 'Activate Plugin',
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'idempotentHint'  => true,
            'openWorldHint'   => false,
        ],
        ['plugin_id']
    );

    // ─── klytos_deactivate_plugin ────────────────────────────
    $registry->register(
        'klytos_deactivate_plugin',
        'Deactivate a plugin by its ID. The plugin stops running but its data is preserved.',
        [
            'plugin_id' => [
                'type'        => 'string',
                'description' => 'The plugin ID to deactivate.',
            ],
        ],
        function (array $params, App $app): array {
            $pluginId = $params['plugin_id'] ?? '';

            if (empty($pluginId)) {
                throw new \InvalidArgumentException('plugin_id is required.');
            }

            $pluginLoader = $app->getPluginLoader();
            $result       = $pluginLoader->deactivate($pluginId);

            return $result;
        },
        [
            'title'           => 'Deactivate Plugin',
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'idempotentHint'  => true,
            'openWorldHint'   => false,
        ],
        ['plugin_id']
    );
}
