<?php

/**
 * Klytos -- Template Resolver
 * Centralizes all template resolution logic with a 4-level hierarchy.
 *
 * Resolution order (first match wins):
 * 1. custom-templates/{name}.html  -- User customizations (never overwritten)
 * 2. Plugin-registered templates   -- Provided by active plugins
 * 3. templates.json.enc            -- Created from the admin UI
 * 4. templates/{name}.html         -- Core templates (overwritten on update)
 *
 * @package Klytos
 * @since   0.12.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 Jose Conti -- https://plugins.joseconti.com -- https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Core;

class TemplateResolver
{
    private App $app;

    /** @var array<string, array> Templates registered by plugins: name => config */
    private array $pluginTemplates = [];

    /** @var array<string, string> In-memory cache of resolved templates */
    private array $cache = [];

    public function __construct( App $app )
    {
        $this->app = $app;
    }

    /**
     * Register templates provided by a plugin.
     * Called from klytos_register_templates() in the plugin's init.php.
     *
     * @param string $pluginId  Plugin ID.
     * @param array  $templates Array of [name => config].
     *   Config keys:
     *   - 'file'        (string) Absolute path to the .html file.
     *   - 'name'        (string) Human-readable name.
     *   - 'description' (string) Template description.
     *   - 'dynamic'     (bool)   Whether this template is for dynamic routes.
     *   - 'post_type'   (string|null) Associated post type.
     */
    public function registerPluginTemplates( string $pluginId, array $templates ): void
    {
        foreach ( $templates as $name => $config ) {
            $this->pluginTemplates[$name] = [
                'plugin_id'   => $pluginId,
                'file'        => $config['file'] ?? '',
                'name'        => $config['name'] ?? $name,
                'description' => $config['description'] ?? '',
                'dynamic'     => $config['dynamic'] ?? false,
                'post_type'   => $config['post_type'] ?? null,
            ];
        }
    }

    /**
     * Resolve a template by name.
     * Traverses the 4-level hierarchy and returns the HTML content.
     *
     * @param  string $name Template name (without extension).
     * @return string HTML content of the template.
     */
    public function resolve( string $name ): string
    {
        if ( isset( $this->cache[$name] ) ) {
            return $this->cache[$name];
        }

        $html = $this->doResolve( $name );
        $this->cache[$name] = $html;

        return $html;
    }

    /**
     * Internal resolution logic.
     */
    private function doResolve( string $name ): string
    {
        $rootPath = $this->app->getRootPath();

        // 1. custom-templates/ (user customizations).
        $customFile = $rootPath . '/custom-templates/' . $name . '.html';
        if ( file_exists( $customFile ) ) {
            return file_get_contents( $customFile );
        }

        // 2. Plugin-registered templates.
        if ( isset( $this->pluginTemplates[$name] ) ) {
            $config = $this->pluginTemplates[$name];
            if ( !empty( $config['file'] ) && file_exists( $config['file'] ) ) {
                return file_get_contents( $config['file'] );
            }
        }

        // 3. Templates in storage (templates.json.enc).
        try {
            $data = $this->app->getStorage()->read( 'templates.json.enc' );
            if ( isset( $data['templates'][$name] ) ) {
                return $data['templates'][$name]['html'];
            }
        } catch ( \RuntimeException $e ) {
            // No templates in storage.
        }

        // 4. Core templates (templates/).
        $coreFile = $this->app->getTemplatesPath() . '/' . $name . '.html';
        if ( file_exists( $coreFile ) ) {
            return file_get_contents( $coreFile );
        }

        // Fallback to default.html.
        $defaultFile = $this->app->getTemplatesPath() . '/default.html';
        if ( file_exists( $defaultFile ) ) {
            return file_get_contents( $defaultFile );
        }

        // Ultimate fallback.
        return $this->getMinimalTemplate();
    }

    /**
     * Resolve a template part (shared fragment).
     * Same hierarchy but within parts/ subdirectories.
     *
     * @param  string $partName Part name (e.g. 'header', 'footer').
     * @return string|null HTML content, or null if not found.
     */
    public function resolvePart( string $partName ): ?string
    {
        $rootPath = $this->app->getRootPath();

        // 1. custom-templates/parts/.
        $customPart = $rootPath . '/custom-templates/parts/' . $partName . '.html';
        if ( file_exists( $customPart ) ) {
            return file_get_contents( $customPart );
        }

        // 2. Plugin parts (via filter).
        $pluginPart = klytos_apply_filters( 'template_part.' . $partName, null );
        if ( $pluginPart !== null ) {
            return $pluginPart;
        }

        // 3. Core parts (templates/parts/).
        $corePart = $this->app->getTemplatesPath() . '/parts/' . $partName . '.html';
        if ( file_exists( $corePart ) ) {
            return file_get_contents( $corePart );
        }

        return null;
    }

    /**
     * Get a list of all available templates from all sources.
     * Higher-priority sources overwrite lower ones.
     *
     * @return array<string, array> Templates indexed by name with source metadata.
     */
    public function listAll(): array
    {
        $templates = [];
        $rootPath  = $this->app->getRootPath();

        // Core templates (lowest priority).
        $coreDir = $this->app->getTemplatesPath();
        foreach ( glob( $coreDir . '/*.html' ) as $file ) {
            $name = basename( $file, '.html' );
            $templates[$name] = [
                'name'   => $name,
                'source' => 'core',
                'file'   => $file,
            ];
        }

        // DB templates.
        try {
            $data = $this->app->getStorage()->read( 'templates.json.enc' );
            foreach ( ( $data['templates'] ?? [] ) as $name => $tpl ) {
                $templates[$name] = [
                    'name'   => $name,
                    'source' => 'database',
                    'file'   => null,
                ];
            }
        } catch ( \RuntimeException $e ) {
            // No templates in storage.
        }

        // Plugin templates.
        foreach ( $this->pluginTemplates as $name => $config ) {
            $templates[$name] = [
                'name'      => $config['name'],
                'source'    => 'plugin',
                'plugin_id' => $config['plugin_id'],
                'file'      => $config['file'],
                'dynamic'   => $config['dynamic'],
            ];
        }

        // Custom templates (highest priority).
        $customDir = $rootPath . '/custom-templates';
        if ( is_dir( $customDir ) ) {
            foreach ( glob( $customDir . '/*.html' ) as $file ) {
                $name = basename( $file, '.html' );
                $templates[$name] = [
                    'name'   => $name,
                    'source' => 'custom',
                    'file'   => $file,
                ];
            }
        }

        return $templates;
    }

    /**
     * Clear the in-memory cache.
     * Call after a plugin is activated/deactivated or templates are edited.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Minimal fallback template when nothing else is available.
     */
    private function getMinimalTemplate(): string
    {
        return '<!DOCTYPE html><html lang="{{page_lang}}">'
             . '<head><meta charset="UTF-8"><title>{{page_title}}</title></head>'
             . '<body>{{page_content}}</body></html>';
    }
}
