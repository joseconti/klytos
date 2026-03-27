<?php
/**
 * Klytos — Theme Manager
 * Manages visual theme configuration: colors, fonts, layout.
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

namespace Klytos\Core;

class ThemeManager
{
    /** @var StorageInterface Storage backend (FileStorage or DatabaseStorage). */
    private StorageInterface $storage;
    private const COLLECTION = 'config';
    private const ID         = 'theme';

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
            return $this->getDefaults();
        }

        return array_merge($this->getDefaults(), $this->storage->read(self::COLLECTION, self::ID));
    }

    /**
     * Set the full theme configuration.
     *
     * @param  array $data Theme data with colors, fonts, layout keys.
     * @return array The saved theme data.
     */
    public function set(array $data): array
    {
        $current = $this->get();
        $theme   = $this->mergeTheme($current, $data);

        $this->storage->write(self::COLLECTION, self::ID, $theme);

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
        $theme = $this->get();

        foreach ($colors as $key => $value) {
            if (Helpers::isValidHexColor($value)) {
                $theme['colors'][$key] = $value;
            }
        }

        $this->storage->write(self::COLLECTION, self::ID, $theme);

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

        return $current;
    }
}
