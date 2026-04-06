<?php

/**
 * Klytos — APCu Cache Driver
 * In-memory caching using the APCu extension (fastest for single-server setups).
 *
 * APCu stores data in shared memory across PHP processes on the same server.
 * It is the recommended default for Klytos installations that run on a single
 * machine. No external daemon is required — just the APCu PHP extension.
 *
 * Group invalidation: uses a version counter per group. When a group is flushed,
 * the counter increments and all existing keys for that group become unreachable.
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

namespace Klytos\Core\Cache;

use Klytos\Core\CacheInterface;

class ApcuCache implements CacheInterface
{
    /** @var string Global prefix to avoid collisions with other applications. */
    private string $prefix;

    /** @var int Request-level hit counter. */
    private int $hits = 0;

    /** @var int Request-level miss counter. */
    private int $misses = 0;

    /**
     * @param string $prefix Global key prefix (e.g. 'klytos:'). Keeps Klytos keys
     *                       isolated from other applications sharing the same APCu store.
     */
    public function __construct(string $prefix = 'klytos:')
    {
        $this->prefix = $prefix;
    }

    // ─── CacheInterface ──────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        $fullKey = $this->buildKey($key);
        $success = false;
        $value   = apcu_fetch($fullKey, $success);

        if ($success) {
            $this->hits++;
            return $value;
        }

        $this->misses++;
        return $default;
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return apcu_store($this->buildKey($key), $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return apcu_delete($this->buildKey($key));
    }

    public function has(string $key): bool
    {
        return apcu_exists($this->buildKey($key));
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result   = [];
        $fullKeys = [];

        foreach ($keys as $key) {
            $fullKeys[$key] = $this->buildKey($key);
        }

        $fetched = apcu_fetch(array_values($fullKeys), $success);

        if (!is_array($fetched)) {
            $fetched = [];
        }

        foreach ($keys as $key) {
            $fullKey = $fullKeys[$key];
            if (array_key_exists($fullKey, $fetched)) {
                $result[$key] = $fetched[$fullKey];
                $this->hits++;
            } else {
                $result[$key] = $default;
                $this->misses++;
            }
        }

        return $result;
    }

    public function setMultiple(array $values, int $ttl = 0): bool
    {
        $mapped = [];
        foreach ($values as $key => $value) {
            $mapped[$this->buildKey((string) $key)] = $value;
        }

        $errors = apcu_store($mapped, null, $ttl);

        // apcu_store with array returns array of failed keys.
        return empty($errors);
    }

    public function deleteMultiple(array $keys): bool
    {
        $ok = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $ok = false;
            }
        }
        return $ok;
    }

    public function flush(): bool
    {
        return apcu_clear_cache();
    }

    public function flushGroup(string $group): bool
    {
        // Increment the group version counter — all existing keys become stale.
        $versionKey = $this->prefix . '_grp_ver:' . $group;
        $newVersion = apcu_inc($versionKey);

        if ($newVersion === false) {
            // Counter did not exist yet — create it.
            apcu_store($versionKey, 1, 0);
        }

        return true;
    }

    public function getStats(): array
    {
        $info   = apcu_cache_info(true);
        $sma    = apcu_sma_info(true);

        return [
            'driver'  => 'apcu',
            'hits'    => $this->hits,
            'misses'  => $this->misses,
            'memory'  => ($sma['seg_size'] ?? 0) - ($sma['avail_mem'] ?? 0),
            'uptime'  => isset($info['start_time']) ? time() - (int) $info['start_time'] : null,
            'entries' => $info['num_entries'] ?? null,
        ];
    }

    public function isAvailable(): bool
    {
        return extension_loaded('apcu') && apcu_enabled();
    }

    public function getDriverName(): string
    {
        return 'apcu';
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Build the full cache key including prefix and group version.
     *
     * Format: {prefix}{group}:{version}:{localKey}
     * If the key contains a colon, the part before the first colon is the group.
     * If no colon, the group is '_default'.
     *
     * @param  string $key User-supplied key (e.g. 'options:my_key' or 'simple_key').
     * @return string Full APCu key.
     */
    private function buildKey(string $key): string
    {
        $colonPos = strpos($key, ':');

        if ($colonPos !== false) {
            $group    = substr($key, 0, $colonPos);
            $localKey = substr($key, $colonPos + 1);
        } else {
            $group    = '_default';
            $localKey = $key;
        }

        $version = $this->getGroupVersion($group);

        return $this->prefix . $group . ':' . $version . ':' . $localKey;
    }

    /**
     * Get the current version counter for a group.
     *
     * @param  string $group Group name.
     * @return int    Version number (starts at 0).
     */
    private function getGroupVersion(string $group): int
    {
        $versionKey = $this->prefix . '_grp_ver:' . $group;
        $success    = false;
        $version    = apcu_fetch($versionKey, $success);

        return $success ? (int) $version : 0;
    }
}
