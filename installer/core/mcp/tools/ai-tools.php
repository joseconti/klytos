<?php

/**
 * Klytos — MCP AI Tools
 * Read-only tools for querying AI chat configuration and usage.
 *
 * @package Klytos
 * @since   0.9.0
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
use Klytos\Core\Ai\AiKeyManager;
use Klytos\Core\Ai\ChatManager;
use Klytos\Core\MCP\ToolRegistry;

function registerAiTools(ToolRegistry $registry): void
{
    // ─── klytos_ai_get_config ──────────────────────────────────
    $registry->register(
        'klytos_ai_get_config',
        'Get the active AI provider and model for the integrated chat. Does NOT expose API keys.',
        [],
        function (array $params, App $app): array {
            $keys   = new AiKeyManager($app->getStorage(), $app->getConfigPath());
            $active = $keys->getActive();

            return [
                'active_provider' => $active['provider'],
                'active_model'    => $active['model'],
                'has_key'         => $active['provider'] ? $keys->hasKey($active['provider']) : false,
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    // ─── klytos_ai_list_providers ──────────────────────────────
    $registry->register(
        'klytos_ai_list_providers',
        'List all available AI providers with their configuration status and available models. Does NOT expose API keys.',
        [],
        function (array $params, App $app): array {
            $keys = new AiKeyManager($app->getStorage(), $app->getConfigPath());

            return ['providers' => $keys->listProviders()];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    // ─── klytos_ai_get_usage ───────────────────────────────────
    $registry->register(
        'klytos_ai_get_usage',
        'Get token usage statistics for the AI chat. Supports period filter: "week", "month", or "all".',
        [
            'period' => [
                'type'        => 'string',
                'description' => 'Time period for stats: "week", "month", or "all". Default: "month".',
                'enum'        => ['week', 'month', 'all'],
            ],
        ],
        function (array $params, App $app): array {
            $chatManager = new ChatManager($app->getStorage());
            $period      = $params['period'] ?? 'month';

            // Get usage for the current authenticated user.
            $auth   = $app->getAuth();
            $userId = 0;

            if ($auth->isAuthenticated()) {
                $user   = $auth->getCurrentUser();
                $userId = (int) ($user['id'] ?? 0);
            }

            return $chatManager->getChatUsage($userId, $period);
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );
}
