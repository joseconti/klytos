<?php

/**
 * Klytos — Null Cache Driver
 * No-op implementation for when caching is explicitly disabled.
 *
 * All reads return the default value; all writes silently succeed.
 * Used when cache.driver is set to 'none' in the site configuration.
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

namespace Klytos\Core\Cache;

use Klytos\Core\CacheInterface;

class NullCache implements CacheInterface
{
    private int $misses = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        $this->misses++;
        return $default;
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return true;
    }

    public function delete(string $key): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $this->misses += count($keys);
        return array_fill_keys($keys, $default);
    }

    public function setMultiple(array $values, int $ttl = 0): bool
    {
        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        return true;
    }

    public function flush(): bool
    {
        return true;
    }

    public function flushGroup(string $group): bool
    {
        return true;
    }

    public function getStats(): array
    {
        return [
            'driver'  => 'none',
            'hits'    => 0,
            'misses'  => $this->misses,
            'memory'  => null,
            'uptime'  => null,
            'entries' => 0,
        ];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getDriverName(): string
    {
        return 'none';
    }
}
