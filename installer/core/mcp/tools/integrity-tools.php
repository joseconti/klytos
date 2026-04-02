<?php

/**
 * Klytos — MCP Integrity Verification Tools
 * Registers MCP tools for running and querying file integrity checks.
 *
 * Tools registered:
 * - klytos_integrity_check:        Run a full integrity verification.
 * - klytos_integrity_status:       Get the last integrity report.
 * - klytos_integrity_check_plugin: Run integrity check on a specific plugin.
 *
 * @package Klytos
 * @since   2.1.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 Jose Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

/**
 * Register integrity verification MCP tools.
 *
 * @param ToolRegistry $registry The MCP tool registry.
 * @param App          $app      The application instance.
 */
function registerIntegrityTools( ToolRegistry $registry, App $app ): void
{
    // ─── klytos_integrity_check ─────────────────────────────
    $registry->register(
        'klytos_integrity_check',
        'Run a full integrity verification on core files and all installed plugins. '
        . 'Compares local SHA-256 hashes against signed manifests from trusted sources. '
        . 'Returns a detailed report with modified, added, and missing files.',
        [
            'force_refresh' => [
                'type'        => 'boolean',
                'description' => 'Force re-download of manifests ignoring cache (default: false).',
            ],
        ],
        function ( array $params, App $app ): array {
            $forceRefresh = !empty( $params['force_refresh'] );
            $checker = $app->getIntegrityChecker();
            return $checker->verify( $forceRefresh );
        },
        [
            'title'           => 'Integrity Check',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'idempotentHint'  => true,
            'openWorldHint'   => true,
        ]
    );

    // ─── klytos_integrity_status ────────────────────────────
    $registry->register(
        'klytos_integrity_status',
        'Get the last integrity check report without running a new verification. '
        . 'Returns null if no check has been run yet.',
        [],
        function ( array $params, App $app ): array {
            $checker = $app->getIntegrityChecker();
            $report  = $checker->getLastReport();

            if ( $report === null ) {
                return [
                    'status'  => 'no_data',
                    'message' => 'No integrity check has been run yet. Use klytos_integrity_check to run one.',
                ];
            }

            return $report;
        },
        [
            'title'           => 'Integrity Status',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'idempotentHint'  => true,
            'openWorldHint'   => false,
        ]
    );

    // ─── klytos_integrity_check_plugin ──────────────────────
    $registry->register(
        'klytos_integrity_check_plugin',
        'Run an integrity check on a specific plugin by its ID. '
        . 'Returns the verification result for that plugin only.',
        [
            'plugin_id' => [
                'type'        => 'string',
                'description' => 'The plugin ID to verify (matches the directory name in plugins/).',
            ],
            'force_refresh' => [
                'type'        => 'boolean',
                'description' => 'Force re-download of the manifest ignoring cache (default: false).',
            ],
        ],
        function ( array $params, App $app ): array {
            $pluginId = $params['plugin_id'] ?? '';

            if ( empty( $pluginId ) ) {
                throw new \InvalidArgumentException( 'plugin_id is required.' );
            }

            $forceRefresh = !empty( $params['force_refresh'] );
            $checker = $app->getIntegrityChecker();
            return $checker->verifyOnePlugin( $pluginId, $forceRefresh );
        },
        [
            'title'           => 'Check Plugin Integrity',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'idempotentHint'  => true,
            'openWorldHint'   => true,
        ],
        ['plugin_id']
    );
}
