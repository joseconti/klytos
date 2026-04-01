<?php

/**
 * Klytos — Consent Manager
 * Manages cookie consent configuration and plugin declarations for GDPR/CCPA compliance.
 *
 * The Consent Manager provides a server-side registry where plugins declare their
 * cookies, scripts, and consent categories. The admin can configure the consent
 * banner (text, privacy URL, cookie duration) and review an audit of all
 * declarations. During static site builds, the build engine uses this config
 * to inject the client-side consent-manager.js with the correct settings.
 *
 * Storage:
 * - Config: Options API key 'consent_manager.config'
 * - Declarations: Collection 'consent_declarations' in StorageInterface
 *
 * @package Klytos
 * @since   0.17.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class ConsentManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string Collection for plugin consent declarations. */
    private const COLLECTION = 'consent_declarations';

    /** @var string Options key for consent configuration. */
    private const CONFIG_KEY = 'consent_manager.config';

    /** @var array Default categories (matched by the JS consent-manager.js). */
    private const DEFAULT_CATEGORIES = [
        'necessary' => [
            'id'          => 'necessary',
            'name'        => 'Obligatorias',
            'description' => 'Cookies esenciales para el funcionamiento del sitio. No se pueden desactivar.',
            'required'    => true,
        ],
        'functional' => [
            'id'          => 'functional',
            'name'        => 'Funcionales',
            'description' => 'Mejoran la experiencia de uso (preferencias, idioma, sesion).',
            'required'    => false,
        ],
        'analytics' => [
            'id'          => 'analytics',
            'name'        => 'Analiticas',
            'description' => 'Permiten medir el trafico y el comportamiento de los visitantes.',
            'required'    => false,
        ],
        'marketing' => [
            'id'          => 'marketing',
            'name'        => 'Marketing',
            'description' => 'Se usan para mostrar publicidad relevante y medir campanas.',
            'required'    => false,
        ],
    ];

    /** @var array Default configuration values. */
    private const DEFAULT_CONFIG = [
        'enabled'     => false,
        'banner_text' => 'Este sitio utiliza cookies propias y de terceros. Puedes aceptar todas, solo las obligatorias, o configurar tus preferencias.',
        'privacy_url' => '/politica-de-privacidad',
        'cookie_days' => 365,
        'categories'  => [],
    ];

    public function __construct( StorageInterface $storage )
    {
        $this->storage = $storage;
    }

    // ─── Configuration ──────────────────────────────────────────

    /**
     * Get the consent manager configuration.
     *
     * @return array Merged defaults + stored config, filtered via 'consent.config'.
     */
    public function getConfig(): array
    {
        $stored = klytos_get_option( self::CONFIG_KEY, [] );
        $config = array_merge( self::DEFAULT_CONFIG, $stored );

        return klytos_apply_filters( 'consent.config', $config );
    }

    /**
     * Save the consent manager configuration.
     *
     * @param array $config Configuration values to save.
     */
    public function saveConfig( array $config ): void
    {
        $sanitized = [
            'enabled'     => (bool) ( $config['enabled'] ?? self::DEFAULT_CONFIG['enabled'] ),
            'banner_text' => trim( (string) ( $config['banner_text'] ?? self::DEFAULT_CONFIG['banner_text'] ) ),
            'privacy_url' => trim( (string) ( $config['privacy_url'] ?? self::DEFAULT_CONFIG['privacy_url'] ) ),
            'cookie_days' => max( 1, (int) ( $config['cookie_days'] ?? self::DEFAULT_CONFIG['cookie_days'] ) ),
            'categories'  => $this->sanitizeCategories( $config['categories'] ?? [] ),
        ];

        // Prevent </script> injection in banner text.
        $sanitized['banner_text'] = str_replace( '</script', '&lt;/script', $sanitized['banner_text'] );

        klytos_do_action( 'consent.before_save', $sanitized );

        klytos_set_option( self::CONFIG_KEY, $sanitized );

        klytos_do_action( 'consent.after_save', $sanitized );
    }

    // ─── Plugin Declarations ────────────────────────────────────

    /**
     * Get all plugin consent declarations.
     *
     * @return array List of declarations, filtered via 'consent.declarations'.
     */
    public function getPluginDeclarations(): array
    {
        $ids = $this->storage->list( self::COLLECTION );
        $declarations = [];

        foreach ( $ids as $id ) {
            try {
                $declarations[] = $this->storage->read( self::COLLECTION, $id );
            } catch ( \Throwable ) {
                continue;
            }
        }

        return klytos_apply_filters( 'consent.declarations', $declarations );
    }

    /**
     * Save (create or update) a plugin consent declaration.
     *
     * @param array $declaration Declaration data with required 'plugin_id', 'name', 'category'.
     * @throws \InvalidArgumentException If required fields are missing or category is invalid.
     */
    public function savePluginDeclaration( array $declaration ): void
    {
        $this->validateDeclaration( $declaration );

        $data = [
            'plugin_id'   => $declaration['plugin_id'],
            'name'        => trim( (string) $declaration['name'] ),
            'category'    => $declaration['category'],
            'description' => trim( (string) ( $declaration['description'] ?? '' ) ),
            'vendor'      => trim( (string) ( $declaration['vendor'] ?? '' ) ),
            'privacy_url' => trim( (string) ( $declaration['privacy_url'] ?? '' ) ),
            'cookies'     => $this->sanitizeCookies( $declaration['cookies'] ?? [] ),
            'scripts'     => array_values( array_filter( array_map( 'trim', (array) ( $declaration['scripts'] ?? [] ) ) ) ),
            'updated_at'  => date( 'c' ),
        ];

        $this->storage->write( self::COLLECTION, $data['plugin_id'], $data );
    }

    /**
     * Delete a plugin consent declaration.
     *
     * @param string $pluginId The plugin ID to remove.
     */
    public function deletePluginDeclaration( string $pluginId ): void
    {
        if ( $this->storage->exists( self::COLLECTION, $pluginId ) ) {
            $this->storage->delete( self::COLLECTION, $pluginId );
        }
    }

    // ─── Audit ──────────────────────────────────────────────────

    /**
     * Get a full audit report of all consent declarations grouped by category.
     *
     * @return array Audit report with summary and declarations by category.
     */
    public function getAuditReport(): array
    {
        $config       = $this->getConfig();
        $declarations = $this->getPluginDeclarations();
        $allCategories = array_merge( self::DEFAULT_CATEGORIES, $config['categories'] );

        $grouped = [];
        foreach ( $allCategories as $catId => $catMeta ) {
            $grouped[$catId] = [
                'category' => $catMeta,
                'plugins'  => [],
            ];
        }

        $totalCookies = 0;
        $totalScripts = 0;

        foreach ( $declarations as $decl ) {
            $cat = $decl['category'] ?? 'necessary';
            if ( !isset( $grouped[$cat] ) ) {
                $grouped[$cat] = [
                    'category' => ['id' => $cat, 'name' => $cat, 'description' => '', 'required' => false],
                    'plugins'  => [],
                ];
            }

            $cookieCount   = count( $decl['cookies'] ?? [] );
            $scriptCount   = count( $decl['scripts'] ?? [] );
            $totalCookies += $cookieCount;
            $totalScripts += $scriptCount;

            $grouped[$cat]['plugins'][] = $decl;
        }

        return klytos_apply_filters( 'consent.audit_export', [
            'generated_at'   => date( 'c' ),
            'enabled'        => $config['enabled'],
            'total_plugins'  => count( $declarations ),
            'total_cookies'  => $totalCookies,
            'total_scripts'  => $totalScripts,
            'categories'     => $grouped,
        ] );
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Get all valid category IDs (defaults + custom).
     *
     * @return array<string>
     */
    public function getValidCategories(): array
    {
        $config = $this->getConfig();
        return array_keys( array_merge( self::DEFAULT_CATEGORIES, $config['categories'] ) );
    }

    /**
     * Validate a plugin declaration.
     *
     * @throws \InvalidArgumentException
     */
    private function validateDeclaration( array $declaration ): void
    {
        if ( empty( $declaration['plugin_id'] ) ) {
            throw new \InvalidArgumentException( 'plugin_id is required.' );
        }
        if ( empty( $declaration['name'] ) ) {
            throw new \InvalidArgumentException( 'name is required.' );
        }
        if ( empty( $declaration['category'] ) ) {
            throw new \InvalidArgumentException( 'category is required.' );
        }

        $validCategories = $this->getValidCategories();
        if ( !in_array( $declaration['category'], $validCategories, true ) ) {
            throw new \InvalidArgumentException(
                'Invalid category: ' . $declaration['category'] . '. Valid: ' . implode( ', ', $validCategories )
            );
        }
    }

    /**
     * Sanitize cookie declarations.
     *
     * @param  array $cookies Raw cookies array.
     * @return array Sanitized cookies.
     */
    private function sanitizeCookies( array $cookies ): array
    {
        $result = [];
        foreach ( $cookies as $cookie ) {
            if ( empty( $cookie['name'] ) ) {
                continue;
            }
            $result[] = [
                'name'        => trim( (string) $cookie['name'] ),
                'duration'    => trim( (string) ( $cookie['duration'] ?? 'Session' ) ),
                'description' => trim( (string) ( $cookie['description'] ?? '' ) ),
                'type'        => in_array( $cookie['type'] ?? 'cookie', ['cookie', 'localStorage', 'sessionStorage'], true )
                    ? $cookie['type']
                    : 'cookie',
                'paths'       => (array) ( $cookie['paths'] ?? ['/'] ),
            ];
        }
        return $result;
    }

    /**
     * Sanitize custom categories.
     *
     * @param  array $categories Raw categories.
     * @return array Sanitized categories.
     */
    private function sanitizeCategories( array $categories ): array
    {
        $result = [];
        foreach ( $categories as $catId => $catData ) {
            if ( !is_string( $catId ) || empty( $catId ) ) {
                continue;
            }
            $result[$catId] = [
                'id'          => $catId,
                'name'        => trim( (string) ( $catData['name'] ?? $catId ) ),
                'description' => trim( (string) ( $catData['description'] ?? '' ) ),
                'required'    => (bool) ( $catData['required'] ?? false ),
            ];
        }
        return $result;
    }
}
