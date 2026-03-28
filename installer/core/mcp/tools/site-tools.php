<?php
/**
 * Klytos — MCP Site Tools
 * Global site configuration via MCP.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
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
