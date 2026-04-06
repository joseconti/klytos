<?php

/**
 * Klytos — MCP Maintenance Tools
 * Enable/disable maintenance mode via MCP.
 *
 * @package Klytos
 * @since   0.26.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 Jose Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

function registerMaintenanceTools(
    \Klytos\Core\MCP\ToolRegistry $registry,
    \Klytos\Core\App $app
): void {

    $registry->register(
        'klytos_set_maintenance_mode',
        'Enable or disable maintenance mode. When enabled, visitors see a 503 maintenance page while admin remains accessible.',
        [
            'enabled' => [
                'type'        => 'boolean',
                'description' => 'True to enable maintenance mode, false to disable.',
            ],
            'message' => [
                'type'        => 'string',
                'description' => 'Custom maintenance message shown to visitors. Optional.',
            ],
        ],
        function ( array $params, \Klytos\Core\App $app ): array {
            $enabled = (bool) ( $params['enabled'] ?? false );
            $message = trim( $params['message'] ?? '' );

            $update = ['maintenance_mode' => $enabled];
            if ( $message !== '' ) {
                $update['maintenance_message'] = $message;
            }

            $config = $app->getSiteConfig()->set( $update );

            if ( $enabled ) {
                klytos_do_action( 'maintenance.enabled' );
            } else {
                klytos_do_action( 'maintenance.disabled' );
            }

            return [
                'success'            => true,
                'maintenance_mode'   => $config['maintenance_mode'] ?? false,
                'maintenance_message' => $config['maintenance_message'] ?? '',
                'note'               => $enabled
                    ? 'Maintenance mode enabled. Run klytos_build_site to apply changes to the public site.'
                    : 'Maintenance mode disabled. Run klytos_build_site to remove the maintenance page.',
            ];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['enabled']
    );

    $registry->register(
        'klytos_get_maintenance_mode',
        'Check whether maintenance mode is currently enabled and get the maintenance message.',
        [],
        function ( array $params, \Klytos\Core\App $app ): array {
            $config = $app->getSiteConfig()->get();
            return [
                'maintenance_mode'    => $config['maintenance_mode'] ?? false,
                'maintenance_message' => $config['maintenance_message'] ?? '',
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );
}
