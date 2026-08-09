<?php

/**
 * Klytos — Theme Manager
 * Manages visual theme configuration: colors, fonts, layout.
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

namespace Klytos\Core;

class ThemeManager
{
    /** @var StorageInterface Storage backend (FileStorage or DatabaseStorage). */
    private StorageInterface $storage;
    private const COLLECTION = 'config';
    private const ID         = 'theme';

    /**
     * WCAG 2.2 AA for normal text. Named rather than repeated so the screen's
     * refusal and the pair's verdict cannot drift apart.
     *
     * @var float
     */
    public const CONTRAST_THRESHOLD = 4.5;

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Get the full theme configuration.
     *
     * @return array
     */
    public function get(): array
    {
        if (!$this->storage->exists(self::COLLECTION, self::ID)) {
            return klytos_apply_filters('theme.data', $this->getDefaults());
        }

        $themeData = array_merge($this->getDefaults(), $this->storage->read(self::COLLECTION, self::ID));

        return klytos_apply_filters('theme.data', $themeData);
    }

    /**
     * Set the full theme configuration.
     *
     * @param  array $data Theme data with colors, fonts, layout keys.
     * @return array The saved theme data.
     */
    public function set(array $data): array
    {
        klytos_do_action('theme.before_save', $data);

        $current = $this->get();
        $theme   = $this->mergeTheme($current, $data);

        $this->storage->write(self::COLLECTION, self::ID, $theme);

        klytos_do_action('theme.after_save', $theme);

        return $theme;
    }

    /**
     * Update only the color palette.
     *
     * @param  array $colors Associative array of color keys => hex values.
     * @return array Updated theme.
     */
    public function setColors(array $colors): array
    {
        klytos_do_action('theme.before_save', $colors);

        $theme = $this->get();

        foreach ($colors as $key => $value) {
            if (Helpers::isValidHexColor($value)) {
                $theme['colors'][$key] = $value;
            }
        }

        $this->storage->write(self::COLLECTION, self::ID, $theme);

        klytos_do_action('theme.after_save', $theme);

        return $theme;
    }

    /**
     * Update only the fonts configuration.
     *
     * @param  array $fonts
     * @return array Updated theme.
     */
    public function setFonts(array $fonts): array
    {
        klytos_do_action('theme.before_save', $fonts);

        $theme = $this->get();

        $allowed = [
            'heading', 'body', 'code', 'heading_weight',
            'body_weight', 'base_size', 'scale_ratio', 'google_fonts_url',
        ];

        foreach ($allowed as $key) {
            if (isset($fonts[$key])) {
                $theme['fonts'][$key] = $fonts[$key];
            }
        }

        $this->storage->write(self::COLLECTION, self::ID, $theme);

        klytos_do_action('theme.after_save', $theme);

        return $theme;
    }

    /**
     * Update only the layout configuration.
     *
     * @param  array $layout
     * @return array Updated theme.
     */
    public function setLayout(array $layout): array
    {
        klytos_do_action('theme.before_save', $layout);

        $theme = $this->get();

        $allowed = [
            'max_width', 'header_style', 'footer_enabled',
            'sidebar_enabled', 'sidebar_position', 'border_radius', 'spacing_unit',
        ];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $layout)) {
                $theme['layout'][$key] = $layout[$key];
            }
        }

        $this->storage->write(self::COLLECTION, self::ID, $theme);

        klytos_do_action('theme.after_save', $theme);

        return $theme;
    }

    /**
     * Generate CSS custom properties from theme data.
     *
     * @return string CSS :root block with variables.
     */
    public function generateCssVariables(): string
    {
        $theme = $this->get();
        $vars  = [];

        // Colors
        foreach ($theme['colors'] as $key => $value) {
            $cssKey  = str_replace('_', '-', $key);
            $vars[] = "  --klytos-{$cssKey}: {$value};";
        }

        // Fonts
        $fonts = $theme['fonts'];
        $vars[] = "  --klytos-font-heading: '{$fonts['heading']}', sans-serif;";
        $vars[] = "  --klytos-font-body: '{$fonts['body']}', sans-serif;";
        $vars[] = "  --klytos-font-code: '{$fonts['code']}', monospace;";

        // Layout
        $layout = $theme['layout'];
        $vars[] = "  --klytos-max-width: {$layout['max_width']};";
        $vars[] = "  --klytos-radius: {$layout['border_radius']};";
        $vars[] = "  --klytos-spacing: {$layout['spacing_unit']};";

        return ":root {\n" . implode("\n", $vars) . "\n}";
    }

    /**
     * Get the Google Fonts URL (if set).
     *
     * @return string
     */
    public function getGoogleFontsUrl(): string
    {
        $theme = $this->get();
        return $theme['fonts']['google_fonts_url'] ?? '';
    }

    /**
     * Default theme configuration.
     */
    /**
     * The text/background pairs the theme defines, each with its measured
     * WCAG contrast ratio and its verdict.
     *
     * `SPEC/accessibility.md` §10.7 requires the Design screen to show the
     * ratio next to every text/background pair the theme defines and to refuse
     * to save a pair below 4.5:1 without a recorded override. Both halves need
     * the same answer, so it is computed once here and the screen renders it
     * and gates on it — never two implementations that could disagree.
     *
     * **The pair set is fixed and deliberate.** The theme declares two text
     * colours (`text`, `text_muted`) and two surfaces text sits on
     * (`background`, `surface`); those four combinations are what "every
     * text/background pair it defines" means. `primary`, `accent` and the
     * status colours are used as link, button and badge colours — real text
     * over a background too, but pairings §10.7's wording does not fix, so
     * guessing them would invent a rule the delivery does not state
     * (`docs/BUILD-SPEC.md` §5.9, adaptation 13).
     *
     * Static because it is a pure function of the palette: the screen calls it
     * on POSTED values, before anything is written, which an instance method
     * reading stored state could not do.
     *
     * @param  array $colors The theme's `colors` array (or a posted candidate).
     * @return array<int, array{
     *     foreground: string, background: string,
     *     foreground_hex: ?string, background_hex: ?string,
     *     ratio: ?float, passes: bool, measurable: bool
     * }> One entry per pair, in a stable order. `ratio` is null and
     *    `measurable` false when either colour is missing or not a hex value —
     *    an unmeasurable pair never counts as passing, and never throws: a
     *    theme mid-edit must render, not 500 (L-034).
     *
     * @since 2.1.0
     *
     * Example:
     *     $pairs = \Klytos\Core\ThemeManager::contrastPairs( $theme['colors'] );
     *     $failing = array_filter( $pairs, fn( $p ) => ! $p['passes'] );
     */
    public static function contrastPairs(array $colors): array
    {
        $textKeys       = ['text', 'text_muted'];
        $backgroundKeys = ['background', 'surface'];

        $pairs = [];

        foreach ($textKeys as $textKey) {
            foreach ($backgroundKeys as $backgroundKey) {
                $foregroundHex = $colors[$textKey] ?? null;
                $backgroundHex = $colors[$backgroundKey] ?? null;

                $ratio = null;

                if (is_string($foregroundHex) && is_string($backgroundHex)) {
                    try {
                        $ratio = Helpers::contrastRatio($foregroundHex, $backgroundHex);
                    } catch (\InvalidArgumentException $e) {
                        // Not a colour: unmeasurable, which is a state the
                        // screen renders. Swallowed here and nowhere else —
                        // the helper still refuses invalid input to every
                        // other caller.
                        $ratio = null;
                    }
                }

                $pairs[] = [
                    'foreground'     => $textKey,
                    'background'     => $backgroundKey,
                    'foreground_hex' => is_string($foregroundHex) ? $foregroundHex : null,
                    'background_hex' => is_string($backgroundHex) ? $backgroundHex : null,
                    'ratio'          => $ratio,
                    // The WCAG threshold is inclusive: exactly 4.5:1 passes.
                    'passes'         => $ratio !== null && $ratio >= self::CONTRAST_THRESHOLD,
                    'measurable'     => $ratio !== null,
                ];
            }
        }

        return klytos_apply_filters('theme.contrast_pairs', $pairs, $colors);
    }

    private function getDefaults(): array
    {
        return [
            'colors' => [
                'primary'    => '#2563eb',
                'secondary'  => '#7c3aed',
                'accent'     => '#f59e0b',
                'background' => '#ffffff',
                'surface'    => '#f8fafc',
                'text'       => '#1e293b',
                'text_muted' => '#64748b',
                'border'     => '#e2e8f0',
                'success'    => '#22c55e',
                'warning'    => '#f59e0b',
                'error'      => '#ef4444',
            ],
            'fonts' => [
                'heading'          => 'Inter',
                'body'             => 'Inter',
                'code'             => 'JetBrains Mono',
                'heading_weight'   => '700',
                'body_weight'      => '400',
                'base_size'        => '16px',
                'scale_ratio'      => '1.25',
                'google_fonts_url' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400&display=swap',
            ],
            'layout' => [
                'max_width'        => '1200px',
                'header_style'     => 'sticky',
                'footer_enabled'   => true,
                'sidebar_enabled'  => false,
                'sidebar_position' => 'left',
                'border_radius'    => '8px',
                'spacing_unit'     => '1rem',
            ],
            'custom_css' => '',
        ];
    }

    /**
     * Deep merge theme data.
     */
    private function mergeTheme(array $current, array $new): array
    {
        if (isset($new['colors']) && is_array($new['colors'])) {
            $current['colors'] = array_merge($current['colors'], $new['colors']);
        }
        if (isset($new['fonts']) && is_array($new['fonts'])) {
            $current['fonts'] = array_merge($current['fonts'], $new['fonts']);
        }
        if (isset($new['layout']) && is_array($new['layout'])) {
            $current['layout'] = array_merge($current['layout'], $new['layout']);
        }
        if (isset($new['custom_css'])) {
            $current['custom_css'] = $new['custom_css'];
        }
        /*
         * Contrast overrides (accessibility.md §10.7). Replaced wholesale, not
         * merged: the caller passes the complete list it wants recorded, and
         * array_merge on a list would renumber and duplicate entries rather
         * than append them.
         */
        if (isset($new['contrast_overrides']) && is_array($new['contrast_overrides'])) {
            $current['contrast_overrides'] = $new['contrast_overrides'];
        }

        return $current;
    }
}
