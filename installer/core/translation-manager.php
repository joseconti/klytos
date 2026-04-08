<?php

/**
 * Klytos — Translation Manager
 * Manages translation discovery, comparison, and editing for core, plugins, and templates.
 *
 * @package Klytos
 * @since   0.19.0
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

class TranslationManager
{
    private App $app;

    /** @var array<string, array> Cached sources list for the current request. */
    private array $sourcesCache = [];

    /** @var array<string, string> Common locale metadata (name, flag). */
    private const LOCALE_MAP = [
        'en' => ['name' => 'English',    'flag' => 'GB'],
        'es' => ['name' => 'Español',    'flag' => 'ES'],
        'fr' => ['name' => 'Français',   'flag' => 'FR'],
        'de' => ['name' => 'Deutsch',    'flag' => 'DE'],
        'it' => ['name' => 'Italiano',   'flag' => 'IT'],
        'pt' => ['name' => 'Português',  'flag' => 'PT'],
        'nl' => ['name' => 'Nederlands', 'flag' => 'NL'],
        'ja' => ['name' => '日本語',      'flag' => 'JP'],
        'zh' => ['name' => '中文',        'flag' => 'CN'],
        'ko' => ['name' => '한국어',      'flag' => 'KR'],
        'ru' => ['name' => 'Русский',    'flag' => 'RU'],
        'ar' => ['name' => 'العربية',    'flag' => 'SA'],
        'hi' => ['name' => 'हिन्दी',      'flag' => 'IN'],
        'pl' => ['name' => 'Polski',     'flag' => 'PL'],
        'sv' => ['name' => 'Svenska',    'flag' => 'SE'],
        'da' => ['name' => 'Dansk',      'flag' => 'DK'],
        'fi' => ['name' => 'Suomi',      'flag' => 'FI'],
        'nb' => ['name' => 'Norsk',      'flag' => 'NO'],
        'tr' => ['name' => 'Türkçe',     'flag' => 'TR'],
        'ca' => ['name' => 'Català',     'flag' => 'ES'],
        'eu' => ['name' => 'Euskara',    'flag' => 'ES'],
        'gl' => ['name' => 'Galego',     'flag' => 'ES'],
        'el' => ['name' => 'Ελληνικά',  'flag' => 'GR'],
    ];

    public function __construct( App $app )
    {
        $this->app = $app;
    }

    /**
     * Discover all translation sources (core + active plugins + templates).
     *
     * @return array [['id' => 'core', 'type' => 'core', 'name' => 'Klytos Core', 'path' => '...'], ...]
     */
    public function getSources(): array
    {
        if ( !empty( $this->sourcesCache ) ) {
            return $this->sourcesCache;
        }

        $sources = [];

        // Core translations.
        $coreLangDir = $this->app->getCorePath() . '/lang';
        if ( is_dir( $coreLangDir ) && file_exists( $coreLangDir . '/en.json' ) ) {
            $sources[] = [
                'id'   => 'core',
                'type' => 'core',
                'name' => 'Klytos Core',
                'path' => $coreLangDir,
            ];
        }

        // Active plugins with lang/en.json.
        $plugins = $this->app->getPluginLoader()->getActivePlugins();
        foreach ( $plugins as $pluginId => $manifest ) {
            $safeId  = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $pluginId );
            $langDir = $this->app->getRootPath() . '/plugins/' . $safeId . '/lang';
            if ( is_dir( $langDir ) && file_exists( $langDir . '/en.json' ) ) {
                $sources[] = [
                    'id'   => $safeId,
                    'type' => 'plugin',
                    'name' => $manifest['name'] ?? $safeId,
                    'path' => $langDir,
                ];
            }
        }

        // Templates (future, same pattern).
        $templatesDir = $this->app->getRootPath() . '/templates';
        if ( is_dir( $templatesDir ) ) {
            $dirs = glob( $templatesDir . '/*/lang', GLOB_ONLYDIR ) ?: [];
            foreach ( $dirs as $langDir ) {
                if ( file_exists( $langDir . '/en.json' ) ) {
                    $templateId = basename( dirname( $langDir ) );
                    $safeId     = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $templateId );
                    $sources[]  = [
                        'id'   => $safeId,
                        'type' => 'template',
                        'name' => ucfirst( str_replace( '-', ' ', $safeId ) ),
                        'path' => $langDir,
                    ];
                }
            }
        }

        $this->sourcesCache = klytos_apply_filters( 'translations.sources', $sources );
        return $this->sourcesCache;
    }

    /**
     * Get all flattened reference keys from en.json for a source.
     *
     * @param  string $sourceId 'core', plugin-id, or template-id.
     * @return array  ['common.save' => 'Save', ...]
     */
    public function getReferenceKeys( string $sourceId ): array
    {
        $path = $this->resolveFilePath( $sourceId, 'en' );
        if ( $path === null || !file_exists( $path ) ) {
            return [];
        }

        $data = $this->loadJsonFile( $path );
        return I18n::flattenKeys( $data );
    }

    /**
     * Get existing translations for a source and locale.
     *
     * @param  string $sourceId
     * @param  string $locale
     * @return array  ['common.save' => 'Guardar', ...] (only keys with values)
     */
    public function getTranslations( string $sourceId, string $locale ): array
    {
        $locale = $this->sanitizeLocale( $locale );
        $path   = $this->resolveFilePath( $sourceId, $locale );
        if ( $path === null || !file_exists( $path ) ) {
            return [];
        }

        $data = $this->loadJsonFile( $path );
        $flat = I18n::flattenKeys( $data );

        // Return only non-empty values.
        return array_filter( $flat, function ( $value ) {
            return $value !== '' && $value !== null;
        } );
    }

    /**
     * Get keys missing translation for a source and locale.
     *
     * @param  string $sourceId
     * @param  string $locale
     * @return array  ['common.new_key' => 'New key value in English', ...]
     */
    public function getMissingKeys( string $sourceId, string $locale ): array
    {
        $reference    = $this->getReferenceKeys( $sourceId );
        $translations = $this->getTranslations( $sourceId, $locale );

        $missing = [];
        foreach ( $reference as $key => $englishValue ) {
            if ( !isset( $translations[$key] ) || $translations[$key] === '' ) {
                $missing[$key] = $englishValue;
            }
        }

        return $missing;
    }

    /**
     * Save a single translation.
     *
     * @param string $sourceId
     * @param string $locale
     * @param string $key      Dot-notation key.
     * @param string $value    Translated string.
     */
    public function saveTranslation( string $sourceId, string $locale, string $key, string $value ): void
    {
        $this->validateKey( $key );
        $locale = $this->sanitizeLocale( $locale );
        $this->validateLocale( $locale );
        $this->validateSource( $sourceId );

        klytos_do_action( 'translations.before_save', $sourceId, $locale, $key, $value );

        $path = $this->resolveFilePath( $sourceId, $locale );
        if ( $path === null ) {
            throw new \RuntimeException( 'Cannot resolve translation file path.' );
        }

        $data = file_exists( $path ) ? $this->loadJsonFile( $path ) : $this->createEmptyLocaleData( $locale );
        $data = $this->expandDotKey( $data, $key, $value );
        $this->writeJsonFile( $path, $data );

        klytos_do_action( 'translations.after_save', $sourceId, $locale, $key, $value );
    }

    /**
     * Save multiple translations at once.
     *
     * @param string $sourceId
     * @param string $locale
     * @param array  $translations ['key' => 'value', ...]
     */
    public function saveBulkTranslations( string $sourceId, string $locale, array $translations ): void
    {
        $locale = $this->sanitizeLocale( $locale );
        $this->validateLocale( $locale );
        $this->validateSource( $sourceId );

        $path = $this->resolveFilePath( $sourceId, $locale );
        if ( $path === null ) {
            throw new \RuntimeException( 'Cannot resolve translation file path.' );
        }

        klytos_do_action( 'translations.before_bulk_save', $sourceId, $locale, $translations );

        $data = file_exists( $path ) ? $this->loadJsonFile( $path ) : $this->createEmptyLocaleData( $locale );

        foreach ( $translations as $key => $value ) {
            $this->validateKey( $key );
            $data = $this->expandDotKey( $data, $key, (string) $value );
        }

        $this->writeJsonFile( $path, $data );

        klytos_do_action( 'translations.after_bulk_save', $sourceId, $locale, $translations );
    }

    /**
     * Get translation statistics per source and locale.
     *
     * @return array ['core' => ['es' => ['total' => 557, 'translated' => 540, 'missing' => 17], ...], ...]
     */
    public function getStats(): array
    {
        $sources   = $this->getSources();
        $languages = $this->getConfiguredLanguages();
        $stats     = [];

        foreach ( $sources as $source ) {
            $reference = $this->getReferenceKeys( $source['id'] );
            $total     = count( $reference );

            foreach ( $languages as $lang ) {
                if ( $lang['code'] === 'en' ) {
                    continue;
                }
                $translations = $this->getTranslations( $source['id'], $lang['code'] );
                $translated   = count( $translations );
                $missing      = $total - $translated;

                $stats[$source['id']][$lang['code']] = [
                    'total'      => $total,
                    'translated' => $translated,
                    'missing'    => max( 0, $missing ),
                ];
            }
        }

        return klytos_apply_filters( 'translations.stats', $stats );
    }

    /**
     * Get the configured languages from site settings.
     *
     * Falls back to the default_language if the languages array is empty,
     * and always ensures English is included as reference.
     *
     * @return array [['code' => 'es', 'name' => 'Español'], ['code' => 'en', 'name' => 'English'], ...]
     */
    public function getConfiguredLanguages(): array
    {
        $siteConfig = $this->app->getSiteConfig()->get();
        $languages  = $siteConfig['languages'] ?? [];

        // If languages array is empty, build from default_language.
        if ( empty( $languages ) ) {
            $defaultLang = $siteConfig['default_language'] ?? 'en';
            if ( $defaultLang !== 'en' ) {
                $name = self::LOCALE_MAP[$defaultLang]['name'] ?? $defaultLang;
                $languages[] = ['code' => $defaultLang, 'name' => $name];
            }
        }

        // Ensure English is always present (as reference language).
        $codes = array_column( $languages, 'code' );
        if ( !in_array( 'en', $codes, true ) ) {
            $languages[] = ['code' => 'en', 'name' => 'English'];
        }

        return $languages;
    }

    // ─── Private helpers ─────────────────────────────────────────

    /**
     * Resolve the absolute file path for a source and locale.
     * Returns null if the source is unknown.
     */
    private function resolveFilePath( string $sourceId, string $locale ): ?string
    {
        $locale = $this->sanitizeLocale( $locale );
        if ( $locale === '' ) {
            return null;
        }

        $sources = $this->getSources();
        foreach ( $sources as $source ) {
            if ( $source['id'] === $sourceId ) {
                $path = $source['path'] . '/' . $locale . '.json';
                // Ensure path stays within the expected lang/ directory.
                $realBase = realpath( $source['path'] );
                if ( $realBase === false ) {
                    return null;
                }
                // For new files, check the directory part.
                $dirPart = dirname( $path );
                $realDir = realpath( $dirPart );
                if ( $realDir === false || strpos( $realDir, $realBase ) !== 0 ) {
                    return null;
                }
                return $path;
            }
        }

        return null;
    }

    /**
     * Load and decode a JSON file.
     */
    private function loadJsonFile( string $path ): array
    {
        $content = file_get_contents( $path );
        if ( $content === false ) {
            return [];
        }
        $data = json_decode( $content, true );
        return is_array( $data ) ? $data : [];
    }

    /**
     * Write data to a JSON file with pretty formatting.
     */
    private function writeJsonFile( string $path, array $data ): void
    {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        file_put_contents( $path, $json . "\n", LOCK_EX );
    }

    /**
     * Expand a dot-notation key into nested array and merge with existing data.
     * Preserves the _meta block.
     */
    private function expandDotKey( array $data, string $key, string $value ): array
    {
        $parts   = explode( '.', $key );
        $current = &$data;

        foreach ( $parts as $i => $part ) {
            if ( $i === count( $parts ) - 1 ) {
                $current[$part] = $value;
            } else {
                if ( !isset( $current[$part] ) || !is_array( $current[$part] ) ) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }
        unset( $current );

        return $data;
    }

    /**
     * Create an empty locale data structure with _meta.
     */
    private function createEmptyLocaleData( string $locale ): array
    {
        $languages = $this->getConfiguredLanguages();
        $name      = $locale;
        $flag      = strtoupper( $locale );

        // Try to find name/flag from configured languages.
        foreach ( $languages as $lang ) {
            if ( $lang['code'] === $locale ) {
                $name = $lang['name'] ?? $locale;
                break;
            }
        }

        // Try fallback from built-in locale map.
        if ( isset( self::LOCALE_MAP[$locale] ) ) {
            $name = self::LOCALE_MAP[$locale]['name'];
            $flag = self::LOCALE_MAP[$locale]['flag'];
        }

        return [
            '_meta' => [
                'locale'  => $locale,
                'name'    => $name,
                'flag'    => $flag,
                'version' => '1.0.0',
            ],
        ];
    }

    /**
     * Sanitize a locale code.
     */
    private function sanitizeLocale( string $locale ): string
    {
        return preg_replace( '/[^a-z_]/', '', strtolower( $locale ) );
    }

    /**
     * Validate that a translation key contains only allowed characters.
     *
     * @throws \InvalidArgumentException
     */
    private function validateKey( string $key ): void
    {
        if ( !preg_match( '/^[a-z0-9_.]+$/', $key ) ) {
            throw new \InvalidArgumentException( 'Invalid translation key: ' . $key );
        }
    }

    /**
     * Validate that a locale is one of the configured languages.
     *
     * @throws \InvalidArgumentException
     */
    private function validateLocale( string $locale ): void
    {
        $languages = $this->getConfiguredLanguages();
        $codes     = array_column( $languages, 'code' );

        // Always allow 'en' as reference.
        if ( $locale === 'en' ) {
            return;
        }

        if ( !in_array( $locale, $codes, true ) ) {
            throw new \InvalidArgumentException( 'Locale not configured: ' . $locale );
        }
    }

    /**
     * Validate that a source ID corresponds to an active source.
     *
     * @throws \InvalidArgumentException
     */
    private function validateSource( string $sourceId ): void
    {
        if ( strpos( $sourceId, '..' ) !== false ) {
            throw new \InvalidArgumentException( 'Invalid source ID.' );
        }

        $sources = $this->getSources();
        $ids     = array_column( $sources, 'id' );

        if ( !in_array( $sourceId, $ids, true ) ) {
            throw new \InvalidArgumentException( 'Unknown translation source: ' . $sourceId );
        }
    }
}
