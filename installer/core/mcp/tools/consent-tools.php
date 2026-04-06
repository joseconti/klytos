<?php

/**
 * Klytos — MCP Consent Manager Tools
 * Tools: klytos_get_consent_config, klytos_set_consent_config,
 *        klytos_list_consent_declarations, klytos_add_consent_declaration,
 *        klytos_delete_consent_declaration, klytos_get_consent_audit.
 *
 * @package Klytos
 * @since   0.17.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

function registerConsentTools(ToolRegistry $registry, App $app): void
{
    $registry->register(
        'klytos_get_consent_config',
        'Get the current Consent Manager configuration (enabled state, banner text, privacy URL, cookie duration, custom categories).',
        [],
        function (array $params, App $app): array {
            return $app->getConsentManager()->getConfig();
        },
        ['title' => 'Get Consent Config', 'readOnlyHint' => true]
    );

    $registry->register(
        'klytos_set_consent_config',
        'Update the Consent Manager configuration. Triggers a site rebuild so changes take effect on the static frontend.',
        [
            'enabled'     => ['type' => 'boolean', 'description' => 'Enable or disable the consent banner.'],
            'banner_text' => ['type' => 'string', 'description' => 'Text shown in the consent banner.'],
            'privacy_url' => ['type' => 'string', 'description' => 'URL to the privacy policy page.'],
            'cookie_days' => ['type' => 'integer', 'description' => 'Days to remember the visitor consent choice (default: 365).'],
            'categories'  => ['type' => 'object', 'description' => 'Custom categories beyond the 4 defaults (necessary, functional, analytics, marketing). Object of {categoryId: {name, description, required}}.'],
        ],
        function (array $params, App $app): array {
            $consentManager = $app->getConsentManager();

            // Merge with existing config so partial updates work.
            $current = $consentManager->getConfig();
            $merged  = array_merge($current, array_filter($params, fn($v) => $v !== null));

            $consentManager->saveConfig($merged);

            // Rebuild static site.
            $buildEngine = new \Klytos\Core\BuildEngine($app);
            $buildEngine->buildAll();

            return $consentManager->getConfig();
        },
        ['title' => 'Set Consent Config', 'readOnlyHint' => false]
    );

    $registry->register(
        'klytos_list_consent_declarations',
        'List all plugin consent declarations (cookies, scripts, categories). Used for audit and compliance.',
        [],
        function (array $params, App $app): array {
            return $app->getConsentManager()->getPluginDeclarations();
        },
        ['title' => 'List Consent Declarations', 'readOnlyHint' => true]
    );

    $registry->register(
        'klytos_add_consent_declaration',
        'Register a plugin\'s cookie and script declarations for consent management. The plugin_id, name, and category are required. Category must be one of: necessary, functional, analytics, marketing (or a custom category).',
        [
            'plugin_id'   => ['type' => 'string', 'description' => 'Unique plugin identifier.'],
            'name'        => ['type' => 'string', 'description' => 'Human-readable plugin name.'],
            'category'    => ['type' => 'string', 'description' => 'Consent category: necessary, functional, analytics, marketing, or a custom category.'],
            'description' => ['type' => 'string', 'description' => 'What this plugin does (shown to visitors and admins).'],
            'vendor'      => ['type' => 'string', 'description' => 'Vendor/company name.'],
            'privacy_url' => ['type' => 'string', 'description' => 'URL to the vendor privacy policy.'],
            'cookies'     => ['type' => 'array', 'description' => 'Array of cookie declarations. Each: {name, duration, description, type ("cookie"|"localStorage"|"sessionStorage")}.', 'items' => ['type' => 'object']],
            'scripts'     => ['type' => 'array', 'description' => 'Array of external script URLs this plugin needs to load.', 'items' => ['type' => 'string']],
        ],
        function (array $params, App $app): array {
            $app->getConsentManager()->savePluginDeclaration($params);
            return ['saved' => true, 'plugin_id' => $params['plugin_id']];
        },
        ['title' => 'Add Consent Declaration', 'readOnlyHint' => false],
        ['plugin_id', 'name', 'category']
    );

    $registry->register(
        'klytos_delete_consent_declaration',
        'Remove a plugin\'s consent declaration. This does not uninstall the plugin, only removes its cookie/script audit entry.',
        [
            'plugin_id' => ['type' => 'string', 'description' => 'Plugin ID to remove.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['plugin_id'])) {
                throw new \InvalidArgumentException('plugin_id is required.');
            }
            $app->getConsentManager()->deletePluginDeclaration($params['plugin_id']);
            return ['deleted' => true, 'plugin_id' => $params['plugin_id']];
        },
        ['title' => 'Delete Consent Declaration', 'readOnlyHint' => false, 'destructiveHint' => true],
        ['plugin_id']
    );

    $registry->register(
        'klytos_get_consent_audit',
        'Get a full consent audit report: all declarations grouped by category with summary statistics. Useful for legal compliance reviews.',
        [],
        function (array $params, App $app): array {
            return $app->getConsentManager()->getAuditReport();
        },
        ['title' => 'Get Consent Audit', 'readOnlyHint' => true]
    );
}
