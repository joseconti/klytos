<?php

/**
 * Klytos — MCP Option Tools
 * Manage plugin options via MCP: list, classify, delete by domain, migrate.
 *
 * @package Klytos
 * @since   0.18.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 Jose Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\MCP\Tools;

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

function registerOptionTools(ToolRegistry $registry): void
{
    $registry->register(
        'klytos_options_list_by_domain',
        'List all options for a specific text domain (plugin).',
        [
            'text_domain' => ['type' => 'string', 'description' => 'Text domain to filter (e.g. "my-gallery")'],
        ],
        function (array $params, App $app): array {
            $domain = $params['text_domain'] ?? '';
            if ($domain === '') {
                return ['error' => 'text_domain is required'];
            }
            $options = $app->getOptionsManager()->getByTextDomain($domain);
            return ['text_domain' => $domain, 'count' => count($options), 'options' => $options];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_options_classify',
        'Classify all stored options by plugin status: core, active, inactive, orphan, unknown.',
        [],
        function (array $params, App $app): array {
            $domains    = $app->getPluginLoader()->getTextDomainsByStatus();
            $classified = $app->getOptionsManager()->classifyOptions($domains['active'], $domains['inactive']);

            $summary = [];
            foreach ($classified as $category => $domainGroups) {
                $count = 0;
                foreach ($domainGroups as $records) {
                    $count += count($records);
                }
                $summary[$category] = $count;
            }

            return ['summary' => $summary, 'details' => $classified];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_options_delete_domain',
        'Delete all options belonging to a text domain. Requires confirm=true.',
        [
            'text_domain' => ['type' => 'string', 'description' => 'Text domain whose options to delete'],
            'confirm'     => ['type' => 'boolean', 'description' => 'Must be true to confirm deletion'],
        ],
        function (array $params, App $app): array {
            $domain = $params['text_domain'] ?? '';
            if ($domain === '') {
                return ['error' => 'text_domain is required'];
            }
            if (empty($params['confirm'])) {
                return ['error' => 'confirm must be true to delete options'];
            }
            $deleted = $app->getOptionsManager()->deleteByTextDomain($domain);
            return ['success' => true, 'deleted' => $deleted, 'text_domain' => $domain];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_options_migrate',
        'Migrate legacy options that have no text_domain field. Infers domain from the key prefix.',
        [],
        function (array $params, App $app): array {
            $migrated = $app->getOptionsManager()->migrateTextDomains();
            return ['success' => true, 'migrated' => $migrated];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true]
    );
}
