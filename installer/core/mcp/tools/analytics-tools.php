<?php
/**
 * Klytos — MCP Analytics Tools
 * Tools: klytos_get_analytics, klytos_get_top_pages.
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

function registerAnalyticsTools(ToolRegistry $registry, App $app): void
{
    $registry->register(
        'klytos_get_analytics',
        'Get analytics summary for a date range: total views, unique visitors, top pages, referrers, devices, and daily breakdown.',
        [
            'date_from' => ['type' => 'string', 'description' => 'Start date (YYYY-MM-DD). Default: 7 days ago.'],
            'date_to'   => ['type' => 'string', 'description' => 'End date (YYYY-MM-DD). Default: today.'],
        ],
        function (array $params, App $app): array {
            $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
            $dateTo   = $params['date_to'] ?? date('Y-m-d');

            $analytics = new \Klytos\Core\AnalyticsManager($app->getStorage());
            return $analytics->getSummary($dateFrom, $dateTo);
        },
        ['title' => 'Get Analytics Summary', 'readOnlyHint' => true]
    );

    $registry->register(
        'klytos_get_top_pages',
        'Get the most visited pages for a date range, ranked by view count.',
        [
            'date_from' => ['type' => 'string', 'description' => 'Start date (YYYY-MM-DD). Default: 30 days ago.'],
            'date_to'   => ['type' => 'string', 'description' => 'End date (YYYY-MM-DD). Default: today.'],
            'limit'     => ['type' => 'integer', 'description' => 'Max pages to return. Default: 20.'],
        ],
        function (array $params, App $app): array {
            $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo   = $params['date_to'] ?? date('Y-m-d');
            $limit    = (int) ($params['limit'] ?? 20);

            $analytics = new \Klytos\Core\AnalyticsManager($app->getStorage());
            return $analytics->getTopPages($dateFrom, $dateTo, $limit);
        },
        ['title' => 'Get Top Pages', 'readOnlyHint' => true]
    );
}
