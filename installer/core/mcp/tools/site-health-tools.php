<?php

/**
 * Klytos — MCP Site Health Tools
 * Run system diagnostics and get health reports via MCP.
 *
 * @package Klytos
 * @since   0.18.0
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
