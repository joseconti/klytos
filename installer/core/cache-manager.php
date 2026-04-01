<?php

/**
 * Klytos — Cache Manager
 * Central cache orchestrator with driver auto-detection and fallback chain.
 *
 * The CacheManager is the single entry point for all caching in Klytos.
 * It initializes the configured cache driver, falls back gracefully when
 * a driver is unavailable, and provides a unified API for all consumers.
 *
 * Driver priority (configurable):
 *   1. apcu      — Fastest; requires APCu extension. Best for single-server.
 *   2. redis     — Persistent; requires phpredis extension. Best for multi-server.
 *   3. memcached — Distributed; requires memcached extension. Alternative to Redis.
 *   4. file      — Universal fallback; no extensions needed. Always available.
 *
 * Configuration lives in site config under the 'cache' key:
 *   - cache.driver     => 'auto' | 'apcu' | 'redis' | 'memcached' | 'file' | 'none'
 *   - cache.prefix     => string  (default: 'klytos:')
 *   - cache.default_ttl => int    (default: 3600)
 *   - cache.redis      => [host, port, password, database, timeout, persistent]
 *   - cache.memcached  => [servers, username, password, persistent_id, binary_protocol]
 *
 * When driver is 'auto', the manager tries each driver in priority order
 * and uses the first one that is available.
 *
 * When driver is 'none', caching is completely disabled (NullCache behavior).
 *
 * @package Klytos
 * @since   2.2.0
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

use Klytos\Core\Cache\ApcuCache;
use Klytos\Core\Cache\RedisCache;
use Klytos\Core\Cache\MemcachedCache;
use Klytos\Core\Cache\FileCache;
use Klytos\Core\Cache\NullCache;

class CacheManager implements CacheInterface
{
    /** @var CacheInterface The active cache driver. */
    private CacheInterface $driver;

    /** @var int Default TTL in seconds when none is specified. */
    private int $defaultTtl;

    /** @var array Full cache configuration. */
    private array $config;

    /** @var string The name of the driver that was resolved. */
    private string $resolvedDriver;

    /**
     * @param array  $config  Cache configuration from site config 'cache' key.
     * @param string $dataDir Absolute path to data/ (for file cache fallback).
     */
    public function __construct(array $config, string $dataDir)
    {
        $this->config     = $config;
        $this->defaultTtl = (int) ($config['default_ttl'] ?? 3600);

        $requestedDriver  = $config['driver'] ?? 'auto';
        $prefix           = $config['prefix'] ?? 'klytos:';

        $this->driver         = $this->resolveDriver($requestedDriver, $prefix, $dataDir);
        $this->resolvedDriver = $this->driver->getDriverName();
    }

    // ─── CacheInterface Delegation ───────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver->get($key, $default);
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $effectiveTtl = $ttl > 0 ? $ttl : $this->defaultTtl;
        return $this->driver->set($key, $value, $effectiveTtl);
    }

    public function delete(string $key): bool
    {
        return $this->driver->delete($key);
    }

    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        return $this->driver->getMultiple($keys, $default);
    }

    public function setMultiple(array $values, int $ttl = 0): bool
    {
        $effectiveTtl = $ttl > 0 ? $ttl : $this->defaultTtl;
        return $this->driver->setMultiple($values, $effectiveTtl);
    }

    public function deleteMultiple(array $keys): bool
    {
        return $this->driver->deleteMultiple($keys);
    }

    public function flush(): bool
    {
        $result = $this->driver->flush();

        // Fire hook so plugins can react (e.g. flush their own caches).
        if (function_exists('klytos_do_action')) {
            klytos_do_action('cache.flushed', $this->resolvedDriver);
        }

        return $result;
    }

    public function flushGroup(string $group): bool
    {
        $result = $this->driver->flushGroup($group);

        if (function_exists('klytos_do_action')) {
            klytos_do_action('cache.group_flushed', $group, $this->resolvedDriver);
        }

        return $result;
    }

    public function getStats(): array
    {
        $stats = $this->driver->getStats();
        $stats['configured_driver'] = $this->config['driver'] ?? 'auto';
        $stats['resolved_driver']   = $this->resolvedDriver;
        $stats['default_ttl']       = $this->defaultTtl;
        return $stats;
    }

    public function isAvailable(): bool
    {
        return $this->driver->isAvailable();
    }

    public function getDriverName(): string
    {
        return $this->driver->getDriverName();
    }

    // ─── High-Level Convenience Methods ──────────────────────────

    /**
     * Get or compute: returns the cached value, or computes, caches, and returns it.
     *
     * This is the recommended pattern for caching expensive operations:
     *
     *   $value = $cache->remember('pages:list_published', function () {
     *       return $this->storage->list('pages', ['status' => 'published']);
     *   }, 1800);
     *
     * @param string   $key      Cache key.
     * @param callable $callback Function that computes the value if not cached.
     * @param int      $ttl      TTL in seconds (0 = use default).
     * @return mixed   The cached or computed value.
     */
    public function remember(string $key, callable $callback, int $ttl = 0): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Increment a numeric value atomically (if supported by driver).
     *
     * For drivers without native increment (file), this is a read-modify-write.
     *
     * @param string $key  Cache key.
     * @param int    $step Increment amount.
     * @return int   New value.
     */
    public function increment(string $key, int $step = 1): int
    {
        $current = $this->get($key, 0);
        $new     = (int) $current + $step;
        $this->set($key, $new);
        return $new;
    }

    /**
     * Decrement a numeric value atomically (if supported by driver).
     *
     * @param string $key  Cache key.
     * @param int    $step Decrement amount.
     * @return int   New value.
     */
    public function decrement(string $key, int $step = 1): int
    {
        return $this->increment($key, -$step);
    }

    /**
     * Flush all known core groups.
     *
     * Useful for admin "flush everything" button.
     *
     * @return bool
     */
    public function flushAll(): bool
    {
        $groups = [
            'options', 'pages', 'config', 'users', 'sessions',
            'menu', 'theme', 'blocks', 'templates', 'analytics',
        ];

        // Allow plugins to register their own groups.
        if (function_exists('klytos_apply_filters')) {
            $groups = klytos_apply_filters('cache.groups', $groups);
        }

        $ok = true;
        foreach ($groups as $group) {
            if (!$this->flushGroup($group)) {
                $ok = false;
            }
        }

        if (function_exists('klytos_do_action')) {
            klytos_do_action('cache.all_flushed', $this->resolvedDriver);
        }

        return $ok;
    }

    /**
     * Get the resolved (active) driver name.
     *
     * @return string e.g. 'apcu', 'redis', 'memcached', 'file', 'none'
     */
    public function getResolvedDriver(): string
    {
        return $this->resolvedDriver;
    }

    /**
     * Get the full cache configuration.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    // ─── Driver Resolution ───────────────────────────────────────

    /**
     * Resolve the cache driver based on configuration and availability.
     *
     * @param string $requested Requested driver name or 'auto'.
     * @param string $prefix    Key prefix.
     * @param string $dataDir   Path to data/ directory.
     * @return CacheInterface
     */
    private function resolveDriver(string $requested, string $prefix, string $dataDir): CacheInterface
    {
        // 'none' = no caching at all (NullCache behavior).
        if ($requested === 'none') {
            return new NullCache();
        }

        // Explicit driver selection.
        if ($requested !== 'auto') {
            $driver = $this->createDriver($requested, $prefix, $dataDir);

            if ($driver !== null && $driver->isAvailable()) {
                return $driver;
            }

            // Fallback to file if the requested driver is unavailable.
            return new FileCache($dataDir . '/_cache');
        }

        // Auto-detection: try drivers in priority order.
        $priorities = ['apcu', 'redis', 'memcached', 'file'];

        foreach ($priorities as $name) {
            $driver = $this->createDriver($name, $prefix, $dataDir);

            if ($driver !== null && $driver->isAvailable()) {
                return $driver;
            }
        }

        // Final fallback (should never happen — FileCache is always available).
        return new FileCache($dataDir . '/_cache');
    }

    /**
     * Create a driver instance by name.
     *
     * @return CacheInterface|null Null if the extension is not loaded.
     */
    private function createDriver(string $name, string $prefix, string $dataDir): ?CacheInterface
    {
        switch ($name) {
            case 'apcu':
                if (!extension_loaded('apcu')) {
                    return null;
                }
                return new ApcuCache($prefix);

            case 'redis':
                if (!extension_loaded('redis')) {
                    return null;
                }
                return new RedisCache($this->config['redis'] ?? [], $prefix);

            case 'memcached':
                if (!extension_loaded('memcached')) {
                    return null;
                }
                return new MemcachedCache($this->config['memcached'] ?? [], $prefix);

            case 'file':
                return new FileCache($dataDir . '/_cache');

            default:
                return null;
        }
    }
}
