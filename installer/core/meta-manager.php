<?php

/**
 * Klytos — Meta Manager
 * Public API for attaching arbitrary metadata to any entity.
 *
 * Meta is stored as a '_meta' field inside the entity's own document.
 * This means meta lives and dies with the entity — no orphan records,
 * no separate collection, no compound IDs.
 *
 * Plugins should namespace their meta keys as 'plugin_id.key_name'
 * to avoid collisions (e.g. 'seo-pro.schema_type', 'gallery.columns').
 *
 * The value can be any JSON-serialisable type: string, int, bool, array, object.
 *
 * @package Klytos
 * @since   2.1.0
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

class MetaManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string Reserved field name inside entity documents. */
    private const META_FIELD = '_meta';

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Get a single meta value.
     *
     * @param  string $collection Entity collection (e.g. 'pages', 'users').
     * @param  string $entityId   Entity identifier (e.g. page slug, user ID).
     * @param  string $key        Meta key (e.g. 'myplugin.setting').
     * @return mixed  The value, or null if the key does not exist.
     */
    public function get(string $collection, string $entityId, string $key): mixed
    {
        $key = $this->sanitizeKey($key);

        if (!$this->storage->exists($collection, $entityId)) {
            return null;
        }

        try {
            $record = $this->storage->read($collection, $entityId);
        } catch (\Throwable) {
            return null;
        }

        $meta  = $record[self::META_FIELD] ?? [];
        $value = $meta[$key] ?? null;

        return klytos_apply_filters('meta.get', $value, $collection, $entityId, $key);
    }

    /**
     * Get ALL meta for an entity.
     *
     * @param  string $collection Entity collection.
     * @param  string $entityId   Entity identifier.
     * @return array  Associative array of key => value.
     */
    public function getAll(string $collection, string $entityId): array
    {
        if (!$this->storage->exists($collection, $entityId)) {
            return [];
        }

        try {
            $record = $this->storage->read($collection, $entityId);
        } catch (\Throwable) {
            return [];
        }

        return $record[self::META_FIELD] ?? [];
    }

    /**
     * Set a meta value (create or update).
     *
     * @param string $collection Entity collection.
     * @param string $entityId   Entity identifier.
     * @param string $key        Meta key.
     * @param mixed  $value      Value to store (any JSON-serialisable type).
     *
     * @throws \RuntimeException If the entity does not exist.
     */
    public function set(string $collection, string $entityId, string $key, mixed $value): void
    {
        $key = $this->sanitizeKey($key);

        $record = $this->storage->read($collection, $entityId);

        klytos_do_action('meta.before_set', $collection, $entityId, $key, $value);

        $record[self::META_FIELD]       = $record[self::META_FIELD] ?? [];
        $record[self::META_FIELD][$key] = $value;

        $this->storage->write($collection, $entityId, $record);

        klytos_do_action('meta.after_set', $collection, $entityId, $key, $value);
    }

    /**
     * Delete a meta key from an entity.
     *
     * @param  string $collection Entity collection.
     * @param  string $entityId   Entity identifier.
     * @param  string $key        Meta key to remove.
     * @return bool   True if the key existed and was removed.
     */
    public function delete(string $collection, string $entityId, string $key): bool
    {
        $key = $this->sanitizeKey($key);

        if (!$this->storage->exists($collection, $entityId)) {
            return false;
        }

        try {
            $record = $this->storage->read($collection, $entityId);
        } catch (\Throwable) {
            return false;
        }

        $meta = $record[self::META_FIELD] ?? [];

        if (!array_key_exists($key, $meta)) {
            return false;
        }

        klytos_do_action('meta.before_delete', $collection, $entityId, $key);

        unset($record[self::META_FIELD][$key]);

        // Remove the _meta field entirely if empty.
        if (empty($record[self::META_FIELD])) {
            unset($record[self::META_FIELD]);
        }

        $this->storage->write($collection, $entityId, $record);

        klytos_do_action('meta.after_delete', $collection, $entityId, $key);

        return true;
    }

    /**
     * Check if a meta key exists for an entity.
     *
     * @param  string $collection Entity collection.
     * @param  string $entityId   Entity identifier.
     * @param  string $key        Meta key.
     * @return bool
     */
    public function exists(string $collection, string $entityId, string $key): bool
    {
        $key = $this->sanitizeKey($key);

        if (!$this->storage->exists($collection, $entityId)) {
            return false;
        }

        try {
            $record = $this->storage->read($collection, $entityId);
        } catch (\Throwable) {
            return false;
        }

        $meta = $record[self::META_FIELD] ?? [];

        return array_key_exists($key, $meta);
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Sanitize a meta key.
     * Allows: alphanumeric, dots, hyphens, underscores.
     *
     * @param  string $key Raw key.
     * @return string Sanitized key.
     * @throws \InvalidArgumentException If key is empty or reserved.
     */
    private function sanitizeKey(string $key): string
    {
        $key = preg_replace('/[^a-zA-Z0-9._\-]/', '', $key);

        if ($key === '' || str_starts_with($key, '_')) {
            throw new \InvalidArgumentException(
                "Invalid meta key: keys cannot be empty or start with '_' (reserved for internal use)."
            );
        }

        return $key;
    }
}
