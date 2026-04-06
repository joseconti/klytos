<?php

/**
 * Klytos — MCP Analytics Tools
 * Tools: klytos_get_analytics, klytos_get_top_pages.
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
            $dateFrom = $params['date_from'] ?? klytos_gmdate( 'Y-m-d', strtotime('-7 days') );
            $dateTo   = $params['date_to'] ?? klytos_gmdate( 'Y-m-d' );

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
            $dateFrom = $params['date_from'] ?? klytos_gmdate( 'Y-m-d', strtotime('-30 days') );
            $dateTo   = $params['date_to'] ?? klytos_gmdate( 'Y-m-d' );
            $limit    = (int) ($params['limit'] ?? 20);

            $analytics = new \Klytos\Core\AnalyticsManager($app->getStorage());
            return $analytics->getTopPages($dateFrom, $dateTo, $limit);
        },
        ['title' => 'Get Top Pages', 'readOnlyHint' => true]
    );
}
