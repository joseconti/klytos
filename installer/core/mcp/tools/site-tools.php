<?php

/**
 * Klytos — MCP Site Tools
 * Global site configuration via MCP.
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

function registerSiteTools(ToolRegistry $registry): void
{
    $registry->register(
        'klytos_set_site_config',
        'Update global site configuration: name, tagline, language, SEO, social links, analytics.',
        [
            'site_name'        => ['type' => 'string', 'description' => 'Site name'],
            'tagline'          => ['type' => 'string', 'description' => 'Site tagline / subtitle'],
            'default_language' => ['type' => 'string', 'description' => 'Default language code (es, en, etc.)'],
            'description'      => ['type' => 'string', 'description' => 'SEO meta description for the site'],
            'favicon_url'      => ['type' => 'string', 'description' => 'Path to favicon'],
            'logo_url'         => ['type' => 'string', 'description' => 'Path to site logo'],
            'social'           => [
                'type' => 'object',
                'description' => 'Social media links: {twitter, github, linkedin, instagram, youtube, mastodon}',
                'additionalProperties' => true,
            ],
            'analytics'        => [
                'type' => 'object',
                'description' => 'Analytics config: {google_analytics_id, custom_head_scripts, custom_body_scripts}',
                'additionalProperties' => true,
            ],
            'seo'              => [
                'type' => 'object',
                'description' => 'SEO config: {default_og_image, robots_txt_extra}',
                'additionalProperties' => true,
            ],
        ],
        function (array $params, App $app): array {
            $config = $app->getSiteConfig()->set($params);
            return ['success' => true, 'config' => $config];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_get_site_config',
        'Get the current global site configuration.',
        [],
        function (array $params, App $app): array {
            return $app->getSiteConfig()->get();
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );
}
