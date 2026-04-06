<?php

/**
 * Klytos — Cache Interface
 * Abstraction layer for persistent caching (APCu, Redis, Memcached, File).
 *
 * Provides a PSR-16-like API for storing and retrieving cached data.
 * All implementations must support key prefixing (groups), TTL, and
 * atomic flush per group or global.
 *
 * @package Klytos
 * @since   2.2.0
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

interface CacheInterface
{
    /**
     * Retrieve a value from the cache.
     *
     * @param  string $key     Cache key.
     * @param  mixed  $default Value to return if the key does not exist or has expired.
     * @return mixed  The cached value, or $default.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store a value in the cache.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to store (must be serializable).
     * @param int    $ttl   Time-to-live in seconds. 0 = no expiry (driver default).
     * @return bool  True on success.
     */
    public function set(string $key, mixed $value, int $ttl = 0): bool;

    /**
     * Delete a value from the cache.
     *
     * @param  string $key Cache key.
     * @return bool   True if the key existed and was deleted, false otherwise.
     */
    public function delete(string $key): bool;

    /**
     * Check if a key exists in the cache and has not expired.
     *
     * @param  string $key Cache key.
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Retrieve multiple values at once.
     *
     * @param  array $keys    Array of cache keys.
     * @param  mixed $default Default value for missing keys.
     * @return array Associative array: key => value (or $default for misses).
     */
    public function getMultiple(array $keys, mixed $default = null): array;

    /**
     * Store multiple values at once.
     *
     * @param  array $values Associative array: key => value.
     * @param  int   $ttl    Time-to-live in seconds. 0 = no expiry.
     * @return bool  True if ALL values were stored successfully.
     */
    public function setMultiple(array $values, int $ttl = 0): bool;

    /**
     * Delete multiple keys at once.
     *
     * @param  array $keys Array of cache keys.
     * @return bool  True if ALL keys were deleted (or did not exist).
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * Flush (clear) all cache entries managed by this driver.
     *
     * @return bool True on success.
     */
    public function flush(): bool;

    /**
     * Flush only entries that belong to a specific group.
     *
     * Groups are implemented via key prefixes (e.g. 'options:my_key').
     * Drivers that support tagging can use native group deletion;
     * others invalidate the group version counter.
     *
     * @param  string $group Group name (e.g. 'options', 'pages', 'sessions').
     * @return bool   True on success.
     */
    public function flushGroup(string $group): bool;

    /**
     * Get cache statistics for diagnostics (DevBar, admin panel).
     *
     * @return array Associative array with keys like:
     *   - 'driver'    => string   Driver name ('apcu', 'redis', 'memcached', 'file')
     *   - 'hits'      => int      Number of cache hits in this request
     *   - 'misses'    => int      Number of cache misses in this request
     *   - 'memory'    => int|null Memory usage in bytes (if available)
     *   - 'uptime'    => int|null Uptime in seconds (if available)
     *   - 'entries'   => int|null Number of stored entries (if available)
     */
    public function getStats(): array;

    /**
     * Check if the cache backend is available and connected.
     *
     * @return bool True if the driver is operational.
     */
    public function isAvailable(): bool;

    /**
     * Get the driver name.
     *
     * @return string One of: 'apcu', 'redis', 'memcached', 'file'.
     */
    public function getDriverName(): string;
}
