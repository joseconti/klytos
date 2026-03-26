<?php
/**
 * Klytos — Internationalization System
 * Loads and resolves translation strings from JSON files.
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

class I18n
{
    private array $strings  = [];
    private array $fallback = [];
    private string $locale;
    private string $fallbackLocale = 'en';
    private string $langDir;

    /**
     * @param string $locale  Active locale code (e.g. 'es', 'en').
     * @param string $langDir Absolute path to core/lang/ directory.
     */
    public function __construct(string $locale, string $langDir)
    {
        $this->locale  = $locale;
        $this->langDir = rtrim($langDir, '/');

        // Load active locale
        $this->strings = $this->loadLocale($locale);

        // Load fallback (English) if different
        if ($locale !== $this->fallbackLocale) {
            $this->fallback = $this->loadLocale($this->fallbackLocale);
        }
    }

    /**
     * Get a translated string by dot-notation key.
     *
     * Fallback chain:
     *   1. Active locale
     *   2. English fallback
     *   3. Return the key itself
     *
     * @param  string $key          Dot-notation key (e.g. 'dashboard.title').
     * @param  array  $replacements Placeholder replacements (e.g. ['version' => '1.1.0']).
     * @return string
     */
    public function get(string $key, array $replacements = []): string
    {
        // Try active locale
        $value = $this->resolve($this->strings, $key);

        // Try fallback
        if ($value === null && !empty($this->fallback)) {
            $value = $this->resolve($this->fallback, $key);
        }

        // Return key as last resort
        if ($value === null) {
            $value = $key;
        }

        // Apply replacements: {variable} → value
        foreach ($replacements as $placeholder => $replacement) {
            $value = str_replace('{' . $placeholder . '}', (string) $replacement, $value);
        }

        return $value;
    }

    /**
     * Get the active locale.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * List available locales with metadata.
     *
     * @return array Array of ['locale' => string, 'name' => string, 'flag' => string]
     */
    public function getAvailableLocales(): array
    {
        $files   = glob($this->langDir . '/*.json') ?: [];
        $locales = [];

        foreach ($files as $file) {
            $code = pathinfo($file, PATHINFO_FILENAME);
            $data = $this->loadLocale($code);
            $meta = $data['_meta'] ?? [];

            $locales[] = [
                'locale'  => $code,
                'name'    => $meta['name'] ?? $code,
                'flag'    => $meta['flag'] ?? '',
                'version' => $meta['version'] ?? '1.0.0',
            ];
        }

        return $locales;
    }

    /**
     * Load a locale file and return its contents.
     *
     * @param  string $locale
     * @return array
     */
    private function loadLocale(string $locale): array
    {
        // Sanitize locale to prevent directory traversal
        $locale = preg_replace('/[^a-z_]/', '', strtolower($locale));
        $file   = $this->langDir . '/' . $locale . '.json';

        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Resolve a dot-notation key in a nested array.
     *
     * @param  array  $data
     * @param  string $key
     * @return string|null
     */
    private function resolve(array $data, string $key): ?string
    {
        $parts = explode('.', $key);
        $value = $data;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }

        return is_string($value) ? $value : null;
    }
}
