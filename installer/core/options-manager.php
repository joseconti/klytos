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

    /** @var CacheManager|null Persistent cache layer (set after boot). */
    private ?CacheManager $cacheManager = null;

    /** @var string Collection name used in StorageInterface. */
    private const COLLECTION = 'options';

    /** @var string Cache group prefix for persistent cache keys. */
    private const CACHE_GROUP = 'options';

    /** @var array<string, mixed> In-memory cache for the current request (key => value). */
    private array $cache = [];

    /** @var array<string, bool> Tracks which keys are in the cache. */
    private array $cacheHits = [];

    /** @var string|null Text domain of the currently executing plugin. */
    private ?string $activeTextDomain = null;

    /**
     * Registry of declared option sensitivity levels.
     *
     * Keys are option keys, values are sensitivity levels:
     * - true:        Always encrypted, regardless of encryption level. For API keys, tokens, secrets.
     * - 'user_data': Encrypted from 'medium' level onwards. For emails, IPs, personal data (GDPR).
     * - false:       Only encrypted at 'professional' level. Normal non-sensitive data.
     *
     * @var array<string, bool|string>
     */
    private static array $sensitivityRegistry = [];

    public function __construct( StorageInterface $storage )
    {
        $this->storage = $storage;
    }

    /**
     * Inject the persistent cache manager.
     *
     * Called by App::boot() after the CacheManager is initialized.
     * This allows OptionsManager to use persistent caching across requests.
     */
    public function setCacheManager(CacheManager $cacheManager): void
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * Set the active text domain (called by PluginLoader before executing a plugin).
     */
    public function setActiveTextDomain(?string $textDomain): void
    {
        $this->activeTextDomain = $textDomain;
    }

    /**
     * Get the active text domain.
     */
    public function getActiveTextDomain(): ?string
    {
        return $this->activeTextDomain;
    }

    // ─── Option Sensitivity Registration ────────────────────────

    /**
     * Register an option with its sensitivity classification.
     *
     * Call this during plugin activation or in your plugin's main file
     * to declare how Klytos should handle encryption for this option.
     *
     * @param string            $key         Option key (e.g. 'my-plugin.api_key').
     * @param bool|string       $sensitive   Sensitivity level:
     *                                       - true:        Always encrypted (API keys, tokens, secrets).
     *                                       - 'user_data': Encrypted from 'medium' level (emails, IPs, GDPR).
     *                                       - false:       Only encrypted at 'professional' level (default).
     * @param array             $meta        Optional metadata: ['type' => string, 'default' => mixed].
     */
    public static function registerOption( string $key, bool|string $sensitive = false, array $meta = [] ): void
    {
        self::$sensitivityRegistry[$key] = $sensitive;

        klytos_do_action( 'option.registered', $key, $sensitive, $meta );
    }

    /**
     * Get the declared sensitivity level for an option.
     *
     * @param  string $key Option key.
     * @return bool|string|null Sensitivity level, or null if not registered.
     */
    public static function getSensitivity( string $key ): bool|string|null
    {
        return self::$sensitivityRegistry[$key] ?? null;
    }

    /**
     * Check if an option should be encrypted based on its declared
     * sensitivity and the current encryption level.
     *
     * @param string $key      Option key.
     * @param int    $levelNum Current encryption level number (0=basic, 1=medium, 2=professional).
     * @return bool  True if this option should be stored encrypted.
     */
    public static function shouldEncryptOption( string $key, int $levelNum ): bool
    {
        $sensitivity = self::$sensitivityRegistry[$key] ?? false;

        // true = always encrypted, regardless of level.
        if ( $sensitivity === true ) {
            return true;
        }

        // 'user_data' = encrypted from medium (level 1) onwards.
        if ( $sensitivity === 'user_data' && $levelNum >= 1 ) {
            return true;
        }

        // false (default) = only encrypted at professional (level 2).
        // This is handled by the encryption level trait's ENCRYPTED_PATHS,
        // so we return false here — the trait decides.
        return false;
    }

    /**
     * Get all registered option sensitivities.
     *
     * @return array<string, bool|string>
     */
    public static function getSensitivityRegistry(): array
    {
        return self::$sensitivityRegistry;
    }

    // ─── Core CRUD Operations ────────────────────────────────────

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

        // Level 1: In-memory request cache (fastest — no I/O).
        if (isset($this->cacheHits[$key])) {
            $value = $this->cache[$key];
            return klytos_apply_filters('option.get', $value, $key);
        }

        // Level 2: Persistent cache (APCu/Redis/Memcached/File — much faster than storage).
        if ($this->cacheManager !== null) {
            $cacheKey = self::CACHE_GROUP . ':' . $key;
            $cached   = $this->cacheManager->get($cacheKey);

            if ($cached !== null) {
                // Populate L1 cache from L2 hit.
                $this->cache[$key]     = $cached;
                $this->cacheHits[$key] = true;
                return klytos_apply_filters('option.get', $cached, $key);
            }
        }

        // Level 3: Storage (encrypted files or database — slowest).
        if (!$this->storage->exists(self::COLLECTION, $key)) {
            return $default;
        }

        try {
            $record = $this->storage->read(self::COLLECTION, $key);
        } catch (\Throwable) {
            return $default;
        }

        $value = $record['value'] ?? $default;

        // Populate L1 request cache.
        $this->cache[$key]     = $value;
        $this->cacheHits[$key] = true;

        // Populate L2 persistent cache for future requests.
        if ($this->cacheManager !== null) {
            $this->cacheManager->set(self::CACHE_GROUP . ':' . $key, $value);
        }

        return klytos_apply_filters('option.get', $value, $key);
    }

    /**
     * Set (create or update) an option.
     *
     * @param string      $key        Option key.
     * @param mixed       $value      Value to store (must be JSON-serialisable).
     * @param string|null $textDomain Explicit text domain. If null, resolved automatically.
     */
    public function set(string $key, mixed $value, ?string $textDomain = null): void
    {
        $key = $this->sanitizeKey($key);

        // Retrieve old value for hooks.
        $oldValue = $this->get($key);

        klytos_do_action('option.before_set', $key, $value, $oldValue);

        $now    = Helpers::now();
        $exists = $this->storage->exists(self::COLLECTION, $key);

        // Read existing record if it exists (for created_at and text_domain preservation).
        $existingRecord = [];
        if ($exists) {
            try {
                $existingRecord = $this->storage->read(self::COLLECTION, $key);
            } catch (\Throwable) {
                // Ignore if unreadable.
            }
        }

        // Resolve text_domain: explicit param > existing > active context > infer from key > _unknown.
        $resolvedDomain = $textDomain
            ?? $existingRecord['text_domain'] ?? null
            ?? $this->activeTextDomain
            ?? $this->inferTextDomain($key)
            ?? '_unknown';

        $record = [
            'key'         => $key,
            'value'       => $value,
            'text_domain' => $resolvedDomain,
            'created_at'  => $existingRecord['created_at'] ?? $now,
            'updated_at'  => $now,
        ];

        $this->storage->write(self::COLLECTION, $key, $record);

        // Update L1 in-memory cache.
        $this->cache[$key]     = $value;
        $this->cacheHits[$key] = true;

        // Update L2 persistent cache.
        if ($this->cacheManager !== null) {
            $this->cacheManager->set(self::CACHE_GROUP . ':' . $key, $value);
        }

        klytos_do_action('option.after_set', $key, $value, $oldValue);
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

        klytos_do_action('option.before_delete', $key);

        $this->storage->delete(self::COLLECTION, $key);

        // Remove from L1 in-memory cache.
        unset($this->cache[$key], $this->cacheHits[$key]);

        // Remove from L2 persistent cache.
        if ($this->cacheManager !== null) {
            $this->cacheManager->delete(self::CACHE_GROUP . ':' . $key);
        }

        klytos_do_action('option.after_delete', $key);

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
     * Matches by key prefix AND by text_domain to cover all cases.
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
            $key    = $record['key'] ?? '';
            $domain = $record['text_domain'] ?? '';

            // Delete if matches by key prefix OR by text_domain.
            if (($key !== '' && str_starts_with($key, $prefix)) || $domain === $pluginId) {
                $this->delete($key);
                $deleted++;
            }
        }

        return $deleted;
    }

    // ─── Text Domain Methods ────────────────────────────────────

    /**
     * Get all options for a specific text domain.
     *
     * @param  string $textDomain Text domain to filter by.
     * @return array<string, array> Associative key => full record.
     */
    public function getByTextDomain(string $textDomain): array
    {
        $all    = $this->storage->list(self::COLLECTION);
        $result = [];

        foreach ($all as $record) {
            $domain = $record['text_domain'] ?? $this->inferTextDomain($record['key'] ?? '');
            if ($domain === $textDomain) {
                $result[$record['key']] = $record;
            }
        }

        return $result;
    }

    /**
     * Delete all options for a specific text domain.
     *
     * @param  string $textDomain Text domain to delete.
     * @return int    Number of options deleted.
     */
    public function deleteByTextDomain(string $textDomain): int
    {
        $options = $this->getByTextDomain($textDomain);
        $deleted = 0;

        foreach ($options as $key => $record) {
            if ($this->delete($key)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Count how many options belong to a text domain.
     *
     * @param  string $textDomain Text domain to count.
     * @return int
     */
    public function countByTextDomain(string $textDomain): int
    {
        return count($this->getByTextDomain($textDomain));
    }

    /**
     * List all options grouped by text domain.
     *
     * @return array<string, array> text_domain => [records]
     */
    public function listGroupedByTextDomain(): array
    {
        $all    = $this->storage->list(self::COLLECTION);
        $groups = [];

        foreach ($all as $record) {
            $domain = $record['text_domain']
                ?? $this->inferTextDomain($record['key'] ?? '')
                ?? '_unknown';
            $groups[$domain][] = $record;
        }

        ksort($groups);
        return $groups;
    }

    /**
     * Classify options by plugin status.
     *
     * @param  array $activePlugins   Text domains of active plugins.
     * @param  array $inactivePlugins Text domains of inactive plugins.
     * @return array With keys: 'core', 'active', 'inactive', 'orphan', 'unknown'.
     */
    public function classifyOptions(array $activePlugins, array $inactivePlugins): array
    {
        $grouped = $this->listGroupedByTextDomain();

        $classified = [
            'core'     => [],
            'active'   => [],
            'inactive' => [],
            'orphan'   => [],
            'unknown'  => [],
        ];

        foreach ($grouped as $domain => $records) {
            if ($domain === '_core') {
                $classified['core'][$domain] = $records;
            } elseif ($domain === '_unknown') {
                $classified['unknown'][$domain] = $records;
            } elseif (in_array($domain, $activePlugins, true)) {
                $classified['active'][$domain] = $records;
            } elseif (in_array($domain, $inactivePlugins, true)) {
                $classified['inactive'][$domain] = $records;
            } else {
                $classified['orphan'][$domain] = $records;
            }
        }

        return $classified;
    }

    /**
     * Migrate legacy options that have no text_domain field.
     * Infers the domain from the key prefix (part before the first dot).
     *
     * @return int Number of records migrated.
     */
    public function migrateTextDomains(): int
    {
        $all      = $this->storage->list(self::COLLECTION);
        $migrated = 0;

        foreach ($all as $record) {
            if (!isset($record['text_domain']) || $record['text_domain'] === '') {
                $key    = $record['key'] ?? '';
                $domain = $this->inferTextDomain($key) ?? '_unknown';

                $record['text_domain'] = $domain;
                $this->storage->write(self::COLLECTION, $key, $record);
                $migrated++;
            }
        }

        return $migrated;
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Infer the text domain from the key prefix.
     * Convention: 'my-gallery.columns' → 'my-gallery'.
     *
     * @param  string $key Option key.
     * @return string|null Inferred domain, or null if no dot found.
     */
    private function inferTextDomain(string $key): ?string
    {
        $dotPos = strpos($key, '.');
        if ($dotPos !== false && $dotPos > 0) {
            return substr($key, 0, $dotPos);
        }
        return null;
    }

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
