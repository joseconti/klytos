<?php

/**
 * Klytos — Redis Cache Driver
 * Persistent caching using Redis for multi-server and high-throughput setups.
 *
 * Redis is ideal for distributed Klytos installations, session storage, and
 * scenarios where cache must survive PHP process restarts. Requires the
 * phpredis extension or a compatible Redis client.
 *
 * Group invalidation: uses a version counter per group stored as a Redis key.
 * Flushing a group increments the counter, making all existing keys unreachable.
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

class RedisCache implements CacheInterface
{
    /** @var \Redis|null Redis connection instance. */
    private ?\Redis $redis = null;

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
     * @param array  $config Redis configuration:
     *   - 'host'       => string  (default: '127.0.0.1')
     *   - 'port'       => int     (default: 6379)
     *   - 'password'   => string  (default: '')
     *   - 'database'   => int     (default: 0)
     *   - 'timeout'    => float   Connection timeout in seconds (default: 2.0)
     *   - 'persistent'  => bool   Use persistent connections (default: true)
     * @param string $prefix Global key prefix.
     */
    public function __construct(array $config = [], string $prefix = 'klytos:')
    {
        $this->config = array_merge([
            'host'       => '127.0.0.1',
            'port'       => 6379,
            'password'   => '',
            'database'   => 0,
            'timeout'    => 2.0,
            'persistent' => true,
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
        $raw     = $this->redis->get($fullKey);

        if ($raw === false) {
            $this->misses++;
            return $default;
        }

        $this->hits++;
        return $this->unserialize($raw);
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $fullKey    = $this->buildKey($key);
        $serialized = $this->serialize($value);

        if ($ttl > 0) {
            return $this->redis->setex($fullKey, $ttl, $serialized);
        }

        return $this->redis->set($fullKey, $serialized);
    }

    public function delete(string $key): bool
    {
        if (!$this->connect()) {
            return false;
        }

        return $this->redis->del($this->buildKey($key)) > 0;
    }

    public function has(string $key): bool
    {
        if (!$this->connect()) {
            return false;
        }

        return (bool) $this->redis->exists($this->buildKey($key));
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        if (!$this->connect() || empty($keys)) {
            $this->misses += count($keys);
            return array_fill_keys($keys, $default);
        }

        $fullKeys = [];
        foreach ($keys as $key) {
            $fullKeys[] = $this->buildKey($key);
        }

        $raw    = $this->redis->mget($fullKeys);
        $result = [];

        foreach ($keys as $i => $key) {
            if ($raw[$i] === false) {
                $result[$key] = $default;
                $this->misses++;
            } else {
                $result[$key] = $this->unserialize($raw[$i]);
                $this->hits++;
            }
        }

        return $result;
    }

    public function setMultiple(array $values, int $ttl = 0): bool
    {
        if (!$this->connect()) {
            return false;
        }

        // Use pipeline for atomic batch write.
        $this->redis->multi(\Redis::PIPELINE);

        foreach ($values as $key => $value) {
            $fullKey    = $this->buildKey((string) $key);
            $serialized = $this->serialize($value);

            if ($ttl > 0) {
                $this->redis->setex($fullKey, $ttl, $serialized);
            } else {
                $this->redis->set($fullKey, $serialized);
            }
        }

        $results = $this->redis->exec();

        return !in_array(false, $results, true);
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

        $this->redis->del(...$fullKeys);
        return true;
    }

    public function flush(): bool
    {
        if (!$this->connect()) {
            return false;
        }

        // Only flush keys with our prefix — do not wipe the entire Redis DB.
        $iterator = null;
        $pattern  = $this->prefix . '*';

        while ($keys = $this->redis->scan($iterator, $pattern, 500)) {
            $this->redis->del(...$keys);
        }

        return true;
    }

    public function flushGroup(string $group): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $versionKey = $this->prefix . '_grp_ver:' . $group;
        $this->redis->incr($versionKey);

        return true;
    }

    public function getStats(): array
    {
        $stats = [
            'driver'  => 'redis',
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
            $info = $this->redis->info();
            $stats['memory']  = (int) ($info['used_memory'] ?? 0);
            $stats['uptime']  = (int) ($info['uptime_in_seconds'] ?? 0);
            $stats['entries'] = (int) ($info['db' . $this->config['database']]['keys'] ?? 0);
        } catch (\Throwable) {
            // Stats are best-effort.
        }

        return $stats;
    }

    public function isAvailable(): bool
    {
        if (!extension_loaded('redis')) {
            return false;
        }

        return $this->connect();
    }

    public function getDriverName(): string
    {
        return 'redis';
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Establish the Redis connection (lazy, once per request).
     */
    private function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        if ($this->redis !== null) {
            // Previous connection attempt failed.
            return false;
        }

        try {
            $this->redis = new \Redis();

            $method = $this->config['persistent'] ? 'pconnect' : 'connect';

            $connected = $this->redis->{$method}(
                $this->config['host'],
                (int) $this->config['port'],
                (float) $this->config['timeout']
            );

            if (!$connected) {
                $this->redis = null;
                return false;
            }

            // Authenticate if password is set.
            if (!empty($this->config['password'])) {
                if (!$this->redis->auth($this->config['password'])) {
                    $this->redis = null;
                    return false;
                }
            }

            // Select database.
            if ($this->config['database'] !== 0) {
                $this->redis->select((int) $this->config['database']);
            }

            // Use igbinary serializer if available for better performance.
            if (defined('Redis::SERIALIZER_IGBINARY')) {
                $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
            }

            $this->connected = true;
            return true;
        } catch (\Throwable) {
            $this->redis = null;
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

        return $this->prefix . $group . ':' . $version . ':' . $localKey;
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
        $version    = $this->redis->get($versionKey);

        return $version !== false ? (int) $version : 0;
    }

    /**
     * Serialize a value for Redis storage.
     * We handle serialization manually to support all PHP types.
     */
    private function serialize(mixed $value): string
    {
        return serialize($value);
    }

    /**
     * Unserialize a value from Redis storage.
     */
    private function unserialize(string $raw): mixed
    {
        return unserialize($raw);
    }
}
