<?php
/**
 * Klytos — Options Manager
 * Public API for storing and retrieving arbitrary key-value options.
 *
 * Plugins and the CMS use this API to persist settings without knowing
 * whether the backend is file-based or database-backed. The StorageInterface
 * handles that transparently.
 *
 * Convention: plugins should namespace their keys as 'plugin_id.setting_name'
 * to avoid collisions (e.g. 'my-gallery.columns', 'seo-pro.sitemap_enabled').
 *
 * @package Klytos
 * @since   2.1.0
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

class OptionsManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string Collection name used in StorageInterface. */
    private const COLLECTION = 'options';

    /** @var array<string, mixed> In-memory cache for the current request (key => value). */
    private array $cache = [];

    /** @var array<string, bool> Tracks which keys are in the cache. */
    private array $cacheHits = [];

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Get an option value.
     *
     * @param  string $key     Option key (e.g. 'myplugin.theme_color').
     * @param  mixed  $default Value to return if the option does not exist.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->sanitizeKey($key);

        // Check the in-memory cache first (avoids repeated reads in the same request).
        if (isset($this->cacheHits[$key])) {
            $value = $this->cache[$key];
            return Hooks::applyFilters('option.get', $value, $key);
        }

        // Read from storage.
        if (!$this->storage->exists(self::COLLECTION, $key)) {
            return $default;
        }

        try {
            $record = $this->storage->read(self::COLLECTION, $key);
        } catch (\Throwable) {
            return $default;
        }

        $value = $record['value'] ?? $default;

        // Populate cache for subsequent reads in the same request.
        $this->cache[$key]     = $value;
        $this->cacheHits[$key] = true;

        return Hooks::applyFilters('option.get', $value, $key);
    }

    /**
     * Set (create or update) an option.
     *
     * @param string $key   Option key.
     * @param mixed  $value Value to store (must be JSON-serialisable).
     */
    public function set(string $key, mixed $value): void
    {
        $key = $this->sanitizeKey($key);

        // Retrieve old value for hooks.
        $oldValue = $this->get($key);

        Hooks::doAction('option.before_set', $key, $value, $oldValue);

        $now    = Helpers::now();
        $exists = $this->storage->exists(self::COLLECTION, $key);

        $record = [
            'key'        => $key,
            'value'      => $value,
            'created_at' => $exists ? ($this->storage->read(self::COLLECTION, $key)['created_at'] ?? $now) : $now,
            'updated_at' => $now,
        ];

        $this->storage->write(self::COLLECTION, $key, $record);

        // Update the in-memory cache.
        $this->cache[$key]     = $value;
        $this->cacheHits[$key] = true;

        Hooks::doAction('option.after_set', $key, $value, $oldValue);
    }

    /**
     * Delete an option.
     *
     * @param  string $key Option key.
     * @return bool   True if the option existed and was deleted.
     */
    public function delete(string $key): bool
    {
        $key = $this->sanitizeKey($key);

        if (!$this->storage->exists(self::COLLECTION, $key)) {
            return false;
        }

        Hooks::doAction('option.before_delete', $key);

        $this->storage->delete(self::COLLECTION, $key);

        // Remove from cache.
        unset($this->cache[$key], $this->cacheHits[$key]);

        Hooks::doAction('option.after_delete', $key);

        return true;
    }

    /**
     * Check if an option exists in storage.
     *
     * @param  string $key Option key.
     * @return bool
     */
    public function exists(string $key): bool
    {
        $key = $this->sanitizeKey($key);

        if (isset($this->cacheHits[$key])) {
            return true;
        }

        return $this->storage->exists(self::COLLECTION, $key);
    }

    /**
     * Get all options for a given plugin (by key prefix).
     *
     * @param  string $pluginId Plugin ID (e.g. 'my-gallery').
     * @return array<string, mixed> Associative array of key => value.
     */
    public function getForPlugin(string $pluginId): array
    {
        $prefix = $pluginId . '.';
        $all    = $this->storage->list(self::COLLECTION);
        $result = [];

        foreach ($all as $record) {
            $key = $record['key'] ?? '';
            if (str_starts_with($key, $prefix)) {
                $result[$key] = $record['value'] ?? null;
            }
        }

        return $result;
    }

    /**
     * Delete all options for a plugin (useful in uninstall.php).
     *
     * @param  string $pluginId Plugin ID.
     * @return int    Number of options deleted.
     */
    public function deleteForPlugin(string $pluginId): int
    {
        $prefix  = $pluginId . '.';
        $all     = $this->storage->list(self::COLLECTION);
        $deleted = 0;

        foreach ($all as $record) {
            $key = $record['key'] ?? '';
            if ($key !== '' && str_starts_with($key, $prefix)) {
                $this->delete($key);
                $deleted++;
            }
        }

        return $deleted;
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Sanitize an option key.
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
                "Invalid option key: keys cannot be empty or start with '_' (reserved for internal use)."
            );
        }

        return $key;
    }
}
