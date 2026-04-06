<?php

/**
 * Klytos — Memcached Cache Driver
 * Distributed in-memory caching using the Memcached extension.
 *
 * Memcached is ideal for multi-server setups where cache consistency across
 * multiple application servers is needed. Supports connection pooling,
 * consistent hashing, and binary protocol for performance.
 *
 * Group invalidation: uses a version counter per group. Flushing a group
 * increments the counter, making all existing keys for that group unreachable.
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

class MemcachedCache implements CacheInterface
{
    /** @var \Memcached|null Memcached connection instance. */
    private ?\Memcached $mc = null;

    /** @var string Global key prefix. */
    private string $prefix;

    /** @var array Connection configuration. */
    private array $config;

    /** @var int Request-level hit counter. */
    private int $hits = 0;

    /** @var int Request-level miss counter. */
    private int $misses = 0;

    /** @var bool Whether the connection has been established. */
    private bool $connected = false;

    /**
     * @param array  $config Memcached configuration:
     *   - 'servers' => array  List of [host, port, weight] arrays.
     *                         Default: [['127.0.0.1', 11211, 1]]
     *   - 'username' => string SASL username (default: '')
     *   - 'password' => string SASL password (default: '')
     *   - 'persistent_id' => string Persistent connection ID (default: 'klytos')
     *   - 'binary_protocol' => bool  Use binary protocol (default: true)
     * @param string $prefix Global key prefix.
     */
    public function __construct(array $config = [], string $prefix = 'klytos:')
    {
        $this->config = array_merge([
            'servers'         => [['127.0.0.1', 11211, 1]],
            'username'        => '',
            'password'        => '',
            'persistent_id'   => 'klytos',
            'binary_protocol' => true,
        ], $config);

        $this->prefix = $prefix;
    }

    // ─── CacheInterface ──────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->connect()) {
            $this->misses++;
            return $default;
        }

        $fullKey = $this->buildKey($key);
        $value   = $this->mc->get($fullKey);

        if ($this->mc->getResultCode() === \Memcached::RES_NOTFOUND) {
            $this->misses++;
            return $default;
        }

        $this->hits++;
        return $value;
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if (!$this->connect()) {
            return false;
        }

        return $this->mc->set($this->buildKey($key), $value, $ttl);
    }

    public function delete(string $key): bool
    {
        if (!$this->connect()) {
            return false;
        }

        return $this->mc->delete($this->buildKey($key));
    }

    public function has(string $key): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $this->mc->get($this->buildKey($key));
        return $this->mc->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        if (!$this->connect() || empty($keys)) {
            $this->misses += count($keys);
            return array_fill_keys($keys, $default);
        }

        $fullKeyMap = [];
        foreach ($keys as $key) {
            $fullKeyMap[$key] = $this->buildKey($key);
        }

        $fetched = $this->mc->getMulti(array_values($fullKeyMap));

        if (!is_array($fetched)) {
            $fetched = [];
        }

        $result = [];
        foreach ($keys as $key) {
            $fullKey = $fullKeyMap[$key];
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
        if (!$this->connect()) {
            return false;
        }

        $mapped = [];
        foreach ($values as $key => $value) {
            $mapped[$this->buildKey((string) $key)] = $value;
        }

        return $this->mc->setMulti($mapped, $ttl);
    }

    public function deleteMultiple(array $keys): bool
    {
        if (!$this->connect() || empty($keys)) {
            return true;
        }

        $fullKeys = [];
        foreach ($keys as $key) {
            $fullKeys[] = $this->buildKey($key);
        }

        $results = $this->mc->deleteMulti($fullKeys);

        // deleteMulti returns array of booleans.
        return !in_array(false, $results, true);
    }

    public function flush(): bool
    {
        if (!$this->connect()) {
            return false;
        }

        // Memcached::flush() clears the entire Memcached store (no prefix isolation).
        // This is a known limitation. For shared Memcached, use flushGroup() instead.
        return $this->mc->flush();
    }

    public function flushGroup(string $group): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $versionKey = $this->prefix . '_grp_ver:' . $group;
        $result     = $this->mc->increment($versionKey);

        if ($result === false) {
            // Key does not exist — create it.
            $this->mc->set($versionKey, 1, 0);
        }

        return true;
    }

    public function getStats(): array
    {
        $stats = [
            'driver'  => 'memcached',
            'hits'    => $this->hits,
            'misses'  => $this->misses,
            'memory'  => null,
            'uptime'  => null,
            'entries' => null,
        ];

        if (!$this->connect()) {
            return $stats;
        }

        try {
            $serverStats = $this->mc->getStats();
            $first       = reset($serverStats);

            if (is_array($first)) {
                $stats['memory']  = (int) ($first['bytes'] ?? 0);
                $stats['uptime']  = (int) ($first['uptime'] ?? 0);
                $stats['entries'] = (int) ($first['curr_items'] ?? 0);
            }
        } catch (\Throwable) {
            // Stats are best-effort.
        }

        return $stats;
    }

    public function isAvailable(): bool
    {
        if (!extension_loaded('memcached')) {
            return false;
        }

        return $this->connect();
    }

    public function getDriverName(): string
    {
        return 'memcached';
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Establish the Memcached connection (lazy, once per request).
     */
    private function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        if ($this->mc !== null) {
            return false;
        }

        try {
            $persistentId = $this->config['persistent_id'] ?: null;
            $this->mc     = new \Memcached($persistentId);

            // Only add servers if not already configured (persistent connections).
            if (empty($this->mc->getServerList())) {
                // Configure options before adding servers.
                if ($this->config['binary_protocol']) {
                    $this->mc->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
                }

                $this->mc->setOption(\Memcached::OPT_DISTRIBUTION, \Memcached::DISTRIBUTION_CONSISTENT);
                $this->mc->setOption(\Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
                $this->mc->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 2000); // 2 seconds
                $this->mc->setOption(\Memcached::OPT_RETRY_TIMEOUT, 2);

                // SASL authentication.
                if (!empty($this->config['username']) && !empty($this->config['password'])) {
                    $this->mc->setSaslAuthData(
                        $this->config['username'],
                        $this->config['password']
                    );
                }

                // Add servers.
                foreach ($this->config['servers'] as $server) {
                    $host   = $server[0] ?? '127.0.0.1';
                    $port   = (int) ($server[1] ?? 11211);
                    $weight = (int) ($server[2] ?? 1);
                    $this->mc->addServer($host, $port, $weight);
                }
            }

            // Verify connection by fetching stats.
            $stats = $this->mc->getStats();
            if ($stats === false || empty($stats)) {
                $this->mc = null;
                return false;
            }

            $this->connected = true;
            return true;
        } catch (\Throwable) {
            $this->mc = null;
            return false;
        }
    }

    /**
     * Build the full cache key with prefix and group version.
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

        // Memcached keys max 250 bytes — hash long keys.
        $fullKey = $this->prefix . $group . ':' . $version . ':' . $localKey;

        if (strlen($fullKey) > 200) {
            $fullKey = $this->prefix . $group . ':' . $version . ':' . md5($localKey);
        }

        return $fullKey;
    }

    /**
     * Get the current version counter for a group.
     */
    private function getGroupVersion(string $group): int
    {
        if (!$this->connected) {
            return 0;
        }

        $versionKey = $this->prefix . '_grp_ver:' . $group;
        $version    = $this->mc->get($versionKey);

        if ($this->mc->getResultCode() === \Memcached::RES_NOTFOUND) {
            return 0;
        }

        return (int) $version;
    }
}
