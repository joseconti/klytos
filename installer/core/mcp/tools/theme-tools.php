<?php

/**
 * Klytos — MCP Theme Tools
 * Visual theme management via MCP.
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

function registerThemeTools(ToolRegistry $registry): void
{
    // ─── klytos_set_theme ──────────────────────────────────────
    $registry->register(
        'klytos_set_theme',
        'Set the full theme configuration: colors, fonts, layout, and custom CSS.',
        [
            'colors' => [
                'type' => 'object',
                'description' => 'Color palette with keys: primary, secondary, accent, background, surface, text, text_muted, border, success, warning, error. Values are #hex.',
                'additionalProperties' => true,
            ],
            'fonts' => [
                'type' => 'object',
                'description' => 'Font settings with keys: heading, body, code, heading_weight, body_weight, base_size, scale_ratio, google_fonts_url.',
                'additionalProperties' => true,
            ],
            'layout' => [
                'type' => 'object',
                'description' => 'Layout settings with keys: max_width, header_style (fixed/static/sticky), footer_enabled, sidebar_enabled, sidebar_position (left/right), border_radius, spacing_unit.',
                'additionalProperties' => true,
            ],
            'custom_css' => ['type' => 'string', 'description' => 'Additional custom CSS to append.'],
        ],
        function (array $params, App $app): array {
            $theme = $app->getTheme()->set($params);
            return ['success' => true, 'theme' => $theme];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true]
    );

    // ─── klytos_get_theme ──────────────────────────────────────
    $registry->register(
        'klytos_get_theme',
        'Get the current theme configuration including colors, fonts, and layout.',
        [],
        function (array $params, App $app): array {
            return $app->getTheme()->get();
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    // ─── klytos_set_colors ─────────────────────────────────────
    $registry->register(
        'klytos_set_colors',
        'Update only the color palette. Other theme settings remain unchanged.',
        [
            'primary'    => ['type' => 'string', 'description' => 'Primary brand color (#hex)'],
            'secondary'  => ['type' => 'string', 'description' => 'Secondary color (#hex)'],
            'accent'     => ['type' => 'string', 'description' => 'Accent/CTA color (#hex)'],
            'background' => ['type' => 'string', 'description' => 'Page background (#hex)'],
            'surface'    => ['type' => 'string', 'description' => 'Card/surface background (#hex)'],
            'text'       => ['type' => 'string', 'description' => 'Main text color (#hex)'],
            'text_muted' => ['type' => 'string', 'description' => 'Secondary text color (#hex)'],
            'border'     => ['type' => 'string', 'description' => 'Border color (#hex)'],
            'success'    => ['type' => 'string', 'description' => 'Success color (#hex)'],
            'warning'    => ['type' => 'string', 'description' => 'Warning color (#hex)'],
            'error'      => ['type' => 'string', 'description' => 'Error color (#hex)'],
        ],
        function (array $params, App $app): array {
            $theme = $app->getTheme()->setColors($params);
            return ['success' => true, 'colors' => $theme['colors']];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true]
    );

    // ─── klytos_set_fonts ──────────────────────────────────────
    $registry->register(
        'klytos_set_fonts',
        'Update font configuration. Other theme settings remain unchanged.',
        [
            'heading'          => ['type' => 'string', 'description' => 'Font family for headings'],
            'body'             => ['type' => 'string', 'description' => 'Font family for body text'],
            'code'             => ['type' => 'string', 'description' => 'Font family for code blocks'],
            'heading_weight'   => ['type' => 'string', 'description' => 'Heading font weight (e.g. 700)'],
            'body_weight'      => ['type' => 'string', 'description' => 'Body font weight (e.g. 400)'],
            'base_size'        => ['type' => 'string', 'description' => 'Base font size (e.g. 16px)'],
            'scale_ratio'      => ['type' => 'string', 'description' => 'Type scale ratio (e.g. 1.25)'],
            'google_fonts_url' => ['type' => 'string', 'description' => 'Full Google Fonts CSS2 URL'],
        ],
        function (array $params, App $app): array {
            $theme = $app->getTheme()->setFonts($params);
            return ['success' => true, 'fonts' => $theme['fonts']];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true]
    );

    // ─── klytos_set_layout ─────────────────────────────────────
    $registry->register(
        'klytos_set_layout',
        'Update layout configuration. Other theme settings remain unchanged.',
        [
            'max_width'        => ['type' => 'string', 'description' => 'Max content width (e.g. 1200px)'],
            'header_style'     => ['type' => 'string', 'description' => 'Header style', 'enum' => ['fixed', 'static', 'sticky']],
            'footer_enabled'   => ['type' => 'boolean', 'description' => 'Show footer'],
            'sidebar_enabled'  => ['type' => 'boolean', 'description' => 'Show sidebar'],
            'sidebar_position' => ['type' => 'string', 'description' => 'Sidebar position', 'enum' => ['left', 'right']],
            'border_radius'    => ['type' => 'string', 'description' => 'Border radius (e.g. 8px)'],
            'spacing_unit'     => ['type' => 'string', 'description' => 'Base spacing unit (e.g. 1rem)'],
        ],
        function (array $params, App $app): array {
            $theme = $app->getTheme()->setLayout($params);
            return ['success' => true, 'layout' => $theme['layout']];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true]
    );
}
