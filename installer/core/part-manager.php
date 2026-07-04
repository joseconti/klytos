<?php

/**
 * Klytos — Part Manager
 * Single source of truth for site-wide reusable fragments ("parts").
 *
 * A part is a named fragment shared across the whole site: header, footer,
 * menu, top-bar, cookie-banner, head, scripts... Edited once, it propagates
 * everywhere on the next build via the {{klytos_part:NAME}} placeholder.
 *
 * This manager unifies the two previous mechanisms:
 * - Template parts (static .html files resolved by TemplateResolver).
 * - Global-scope blocks (BlockManager entities with global_data).
 *
 * HTML resolution hierarchy (first match wins):
 * 1. custom-templates/parts/{id}.html  -- User file overrides (never overwritten)
 * 2. Plugin filter 'template_part.{id}' -- Provided by active plugins
 * 3. Storage collection 'parts'         -- AI/admin managed parts
 * 4. templates/parts/{id}.html          -- Core defaults (overwritten on update)
 *
 * Data (slot values) always comes from the storage record, regardless of
 * which level provided the HTML. Unknown {{variables}} are left untouched
 * so the build engine can replace site/page variables afterwards.
 *
 * @package Klytos
 * @since   0.32.0
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

class PartManager
{
    /** @var App Application instance. */
    private App $app;

    /** @var string Storage collection name. */
    private const COLLECTION = 'parts';

    /** @var array<string, string|null> Per-request cache of rendered parts. */
    private array $renderCache = [];

    /**
     * @param App $app Application instance.
     */
    public function __construct( App $app )
    {
        $this->app = $app;
    }

    /**
     * Save (create or update) a part in storage.
     *
     * @param  array $data Part data: id (required), name, description, html,
     *                     slots, data, css, js.
     * @return array The saved part.
     * @throws \InvalidArgumentException On validation failure.
     */
    public function save( array $data ): array
    {
        $partId = trim( $data['id'] ?? '' );

        if ( empty( $partId ) ) {
            throw new \InvalidArgumentException( 'Part ID is required.' );
        }
        if ( !preg_match( '/^[a-zA-Z0-9_\-]+$/', $partId ) ) {
            throw new \InvalidArgumentException( 'Part ID must be alphanumeric with hyphens/underscores.' );
        }

        $isNew    = !$this->storage()->exists( self::COLLECTION, $partId );
        $existing = $isNew ? [] : $this->storage()->read( self::COLLECTION, $partId );

        $part = [
            'id'            => $partId,
            'name'          => trim( $data['name'] ?? ( $existing['name'] ?? $partId ) ),
            'description'   => $data['description'] ?? ( $existing['description'] ?? '' ),
            'html'          => $data['html'] ?? ( $existing['html'] ?? '' ),
            'slots'         => $this->validateSlots( $data['slots'] ?? ( $existing['slots'] ?? [] ) ),
            'data'          => $data['data'] ?? ( $existing['data'] ?? [] ),
            'css'           => $data['css'] ?? ( $existing['css'] ?? '' ),
            'js'            => $data['js'] ?? ( $existing['js'] ?? '' ),
            'migrated_from' => $data['migrated_from'] ?? ( $existing['migrated_from'] ?? null ),
            'created_at'    => $isNew ? Helpers::now() : ( $existing['created_at'] ?? Helpers::now() ),
            'updated_at'    => Helpers::now(),
        ];

        klytos_do_action( 'part.before_save', $part );

        $this->storage()->write( self::COLLECTION, $partId, $part );
        $this->clearCache();

        klytos_do_action( 'part.after_save', $part );

        return $part;
    }

    /**
     * Get a part record from storage.
     *
     * @param  string $partId Part ID.
     * @return array  Part data.
     * @throws \RuntimeException If not found in storage.
     */
    public function get( string $partId ): array
    {
        return $this->storage()->read( self::COLLECTION, $partId );
    }

    /**
     * Check whether a part exists in storage.
     *
     * @param  string $partId Part ID.
     * @return bool
     */
    public function exists( string $partId ): bool
    {
        return $this->storage()->exists( self::COLLECTION, $partId );
    }

    /**
     * List all known parts from every source, with effective-source metadata.
     *
     * @return array<string, array> Parts indexed by ID.
     */
    public function list(): array
    {
        $parts = [];

        // 4. Core parts (lowest priority).
        $coreDir = $this->app->getTemplatesPath() . '/parts';
        if ( is_dir( $coreDir ) ) {
            foreach ( glob( $coreDir . '/*.html' ) as $file ) {
                $id = basename( $file, '.html' );
                $parts[$id] = [ 'id' => $id, 'name' => $id, 'source' => 'core', 'has_data' => false ];
            }
        }

        // 3. Storage parts.
        foreach ( $this->storage()->list( self::COLLECTION ) as $record ) {
            $id = $record['id'] ?? '';
            if ( $id === '' ) {
                continue;
            }
            $parts[$id] = [
                'id'       => $id,
                'name'     => $record['name'] ?? $id,
                'source'   => 'storage',
                'has_data' => !empty( $record['data'] ),
            ];
        }

        // 1. Custom file overrides (highest priority).
        $customDir = $this->app->getRootPath() . '/custom-templates/parts';
        if ( is_dir( $customDir ) ) {
            foreach ( glob( $customDir . '/*.html' ) as $file ) {
                $id = basename( $file, '.html' );
                $existing  = $parts[$id] ?? [ 'id' => $id, 'name' => $id, 'has_data' => false ];
                $parts[$id] = array_merge( $existing, [ 'source' => 'custom' ] );
            }
        }

        ksort( $parts );

        return $parts;
    }

    /**
     * Delete a part from storage.
     * File overrides and core files are not touched.
     *
     * @param  string $partId Part ID.
     * @return bool   True if deleted.
     */
    public function delete( string $partId ): bool
    {
        $deleted = $this->storage()->delete( self::COLLECTION, $partId );
        $this->clearCache();

        if ( $deleted ) {
            klytos_do_action( 'part.deleted', $partId );
        }

        return $deleted;
    }

    /**
     * Set the slot data for a part (the "edit once, propagate everywhere" path).
     *
     * @param  string $partId Part ID (must exist in storage).
     * @param  array  $data   Slot values.
     * @return array  Updated part.
     */
    public function setData( string $partId, array $data ): array
    {
        $part = $this->get( $partId );

        $part['data']       = $data;
        $part['updated_at'] = Helpers::now();

        $this->storage()->write( self::COLLECTION, $partId, $part );
        $this->clearCache();

        klytos_do_action( 'part.data_changed', $partId, $data );

        return $part;
    }

    /**
     * Get the slot data for a part.
     *
     * @param  string $partId Part ID.
     * @return array  Slot data, or empty array.
     */
    public function getData( string $partId ): array
    {
        try {
            $part = $this->get( $partId );
        } catch ( \RuntimeException $e ) {
            return [];
        }

        return $part['data'] ?? [];
    }

    /**
     * Resolve the raw HTML of a part through the 4-level hierarchy.
     *
     * @param  string $partId Part ID.
     * @return string|null HTML content, or null if not found at any level.
     */
    public function resolveHtml( string $partId ): ?string
    {
        // 1. custom-templates/parts/ (user file overrides).
        $customFile = $this->app->getRootPath() . '/custom-templates/parts/' . $partId . '.html';
        if ( file_exists( $customFile ) ) {
            return file_get_contents( $customFile );
        }

        // 2. Plugin parts (via filter).
        $pluginPart = klytos_apply_filters( 'template_part.' . $partId, null );
        if ( $pluginPart !== null ) {
            return $pluginPart;
        }

        // 3. Storage parts.
        try {
            $record = $this->storage()->read( self::COLLECTION, $partId );
            if ( !empty( $record['html'] ) ) {
                return $record['html'];
            }
        } catch ( \RuntimeException $e ) {
            // Not in storage — fall through.
        }

        // 4. Core parts (templates/parts/).
        $coreFile = $this->app->getTemplatesPath() . '/parts/' . $partId . '.html';
        if ( file_exists( $coreFile ) ) {
            return file_get_contents( $coreFile );
        }

        return null;
    }

    /**
     * Get the effective source of a part ('custom', 'plugin', 'storage', 'core').
     *
     * @param  string $partId Part ID.
     * @return string|null Source name, or null if the part does not resolve.
     */
    public function getSource( string $partId ): ?string
    {
        if ( file_exists( $this->app->getRootPath() . '/custom-templates/parts/' . $partId . '.html' ) ) {
            return 'custom';
        }
        if ( klytos_apply_filters( 'template_part.' . $partId, null ) !== null ) {
            return 'plugin';
        }
        if ( $this->storage()->exists( self::COLLECTION, $partId ) ) {
            return 'storage';
        }
        if ( file_exists( $this->app->getTemplatesPath() . '/parts/' . $partId . '.html' ) ) {
            return 'core';
        }

        return null;
    }

    /**
     * Render a part: resolved HTML + slot data + CSS/JS, wrapped in markers.
     *
     * Slot values are escaped according to their slot type (html/richtext
     * slots are output raw). Placeholders without a data value are left
     * untouched so the build engine can replace site/page {{variables}}
     * afterwards ({{menu_html}}, {{site_name}}, {{base_path}}...).
     *
     * @param  string $partId Part ID.
     * @return string|null Rendered HTML, or null if the part does not resolve.
     */
    public function render( string $partId ): ?string
    {
        if ( array_key_exists( $partId, $this->renderCache ) ) {
            return $this->renderCache[$partId];
        }

        $html = $this->resolveHtml( $partId );

        if ( $html === null ) {
            $this->renderCache[$partId] = null;
            return null;
        }

        $record = [];
        try {
            $record = $this->get( $partId );
        } catch ( \RuntimeException $e ) {
            // No storage record — render raw HTML with no data.
        }

        $html = $this->applyData( $html, $record['data'] ?? [], $record['slots'] ?? [] );

        // Self-contained CSS/JS so a part works on any page without extra wiring.
        if ( !empty( $record['css'] ) ) {
            $html = '<style data-klytos-part="' . Helpers::escAttr( $partId ) . '">'
                  . $record['css'] . '</style>' . "\n" . $html;
        }
        if ( !empty( $record['js'] ) ) {
            $html .= "\n" . '<script data-klytos-part="' . Helpers::escAttr( $partId ) . '">'
                   . $record['js'] . '</script>';
        }

        // Wrap with comment markers (smart rebuild, debugging).
        $html = "<!--klytos:part:{$partId}-->\n{$html}\n<!--/klytos:part:{$partId}-->";

        // Allow plugins to modify the rendered part HTML.
        $html = klytos_apply_filters( 'part.rendered_html', $html, $partId, $record );

        $this->renderCache[$partId] = $html;

        return $html;
    }

    /**
     * Clear the per-request render cache.
     * Call after a part is edited or a plugin is (de)activated.
     */
    public function clearCache(): void
    {
        $this->renderCache = [];
    }

    /**
     * Migrate global-scope blocks to parts (idempotent).
     *
     * For every block with scope 'global' that has no part with the same ID
     * in storage, a part is created from the block definition (html, slots,
     * css, js) and its global_data. Blocks are NOT deleted (that cleanup
     * belongs to a later phase), so existing builds keep working.
     *
     * @return array Summary: ['migrated' => [...], 'skipped' => [...]].
     */
    public function migrateGlobalBlocks(): array
    {
        $blockManager = $this->app->getBlockManager();
        $migrated     = [];
        $skipped      = [];

        foreach ( $blockManager->list( 'all', 'all' ) as $block ) {
            if ( ( $block['scope'] ?? '' ) !== 'global' ) {
                continue;
            }

            $blockId = $block['id'] ?? '';
            if ( $blockId === '' ) {
                continue;
            }

            if ( $this->exists( $blockId ) ) {
                $skipped[] = $blockId;
                continue;
            }

            $this->save( [
                'id'            => $blockId,
                'name'          => $block['name'] ?? $blockId,
                'description'   => 'Migrated from global block.',
                'html'          => $block['html'] ?? '',
                'slots'         => $block['slots'] ?? [],
                'data'          => $block['global_data'] ?? ( $block['sample_data'] ?? [] ),
                'css'           => $block['css'] ?? '',
                'js'            => $block['js'] ?? '',
                'migrated_from' => 'block:' . $blockId,
            ] );

            $migrated[] = $blockId;
        }

        return [ 'migrated' => $migrated, 'skipped' => $skipped ];
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Replace slot placeholders with data values, escaping by slot type.
     * Unknown placeholders are preserved for the build replacement pass.
     *
     * @param  string $html  Part HTML.
     * @param  array  $data  Slot values (key => value).
     * @param  array  $slots Slot definitions (for type-aware escaping).
     * @return string HTML with data applied.
     */
    private function applyData( string $html, array $data, array $slots ): string
    {
        if ( empty( $data ) ) {
            return $html;
        }

        $slotTypes = [];
        foreach ( $slots as $slot ) {
            if ( !empty( $slot['name'] ) ) {
                $slotTypes[$slot['name']] = $slot['type'] ?? 'text';
            }
        }

        // Slot types whose values contain legitimate HTML — must NOT be escaped.
        $htmlSlotTypes = [ 'html', 'richtext' ];

        foreach ( $data as $key => $value ) {
            if ( is_string( $value ) ) {
                $slotType  = $slotTypes[$key] ?? 'text';
                $safeValue = in_array( $slotType, $htmlSlotTypes, true )
                    ? $value
                    : Helpers::escAttr( $value );
                $html = str_replace( '{{' . $key . '}}', $safeValue, $html );
            } elseif ( is_bool( $value ) ) {
                $html = str_replace( '{{' . $key . '}}', $value ? 'true' : 'false', $html );
            } elseif ( is_numeric( $value ) ) {
                $html = str_replace( '{{' . $key . '}}', (string) $value, $html );
            }
            // Arrays and complex types are handled by specific slot renderers.
        }

        return $html;
    }

    /**
     * Validate slot definitions (same rules as BlockManager).
     *
     * @param  array $slots Raw slot definitions.
     * @return array Validated slots.
     */
    private function validateSlots( array $slots ): array
    {
        $validTypes = $this->app->getBlockManager()->getSlotTypes();
        $validated  = [];

        foreach ( $slots as $slot ) {
            if ( empty( $slot['name'] ) || empty( $slot['type'] ) ) {
                continue;
            }
            if ( !in_array( $slot['type'], $validTypes, true ) ) {
                continue;
            }

            $validated[] = [
                'name'        => $slot['name'],
                'type'        => $slot['type'],
                'label'       => $slot['label'] ?? $slot['name'],
                'required'    => $slot['required'] ?? false,
                'default'     => $slot['default'] ?? null,
                'placeholder' => $slot['placeholder'] ?? '',
                'options'     => $slot['options'] ?? [],
            ];
        }

        return $validated;
    }

    /**
     * Get the storage backend.
     */
    private function storage(): StorageInterface
    {
        return $this->app->getStorage();
    }
}
