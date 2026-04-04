<?php

/**
 * Klytos — MCP Site Health Tools
 * Run system diagnostics and get health reports via MCP.
 *
 * @package Klytos
 * @since   0.18.0
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
use Klytos\Core\MCP\ToolRegistry;

function registerSiteHealthTools( ToolRegistry $registry ): void
{
    // ─── klytos_run_site_health ──────────────────────────────
    $registry->register(
        'klytos_run_site_health',
        'Run a comprehensive site health check. Returns a score (0-100) and individual check results grouped by category: PHP environment, security, storage, build status, and performance. Each check has a status (good/warning/critical), value, and recommendation.',
        [],
        function ( array $params, App $app ): array {
            return $app->getSiteHealthManager()->runAll();
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );
}
