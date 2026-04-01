<?php

/**
 * Klytos — File Cache Driver
 * Filesystem-based cache for environments without APCu, Redis, or Memcached.
 *
 * This is the universal fallback driver — it works on any PHP installation
 * with no extensions required. Cache entries are stored as serialized PHP
 * files in a dedicated cache directory inside data/.
 *
 * Performance: slower than in-memory drivers but still significantly faster
 * than re-reading and decrypting storage records on every request.
 *
 * Group invalidation: uses a version file per group. Flushing a group
 * increments the counter; stale entries are cleaned up lazily on read.
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

class FileCache implements CacheInterface
{
    /** @var string Base directory for cache files. */
    private string $cacheDir;

    /** @var int Request-level hit counter. */
    private int $hits = 0;

    /** @var int Request-level miss counter. */
    private int $misses = 0;

    /** @var array<string, int> In-memory cache for group versions (avoids repeated reads). */
    private array $groupVersions = [];

    /**
     * @param string $cacheDir Absolute path to the cache directory (e.g. data/_cache/).
     */
    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->ensureDir($this->cacheDir);
    }

    // ─── CacheInterface ──────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->keyToPath($key);

        if (!file_exists($file)) {
            $this->misses++;
            return $default;
        }

        $data = $this->readFile($file);

        if ($data === null) {
            $this->misses++;
            return $default;
        }

        // Check TTL expiry.
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            @unlink($file);
            $this->misses++;
            return $default;
        }

        $this->hits++;
        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $file = $this->keyToPath($key);
        $dir  = dirname($file);
        $this->ensureDir($dir);

        $data = [
            'value'   => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'created' => time(),
        ];

        $serialized = serialize($data);
        $tmpFile    = $file . '.tmp.' . getmypid();

        if (file_put_contents($tmpFile, $serialized, LOCK_EX) === false) {
            @unlink($tmpFile);
            return false;
        }

        // Atomic rename.
        if (!rename($tmpFile, $file)) {
            @unlink($tmpFile);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $file = $this->keyToPath($key);

        if (file_exists($file)) {
            return @unlink($file);
        }

        return false;
    }

    public function has(string $key): bool
    {
        $file = $this->keyToPath($key);

        if (!file_exists($file)) {
            return false;
        }

        $data = $this->readFile($file);

        if ($data === null) {
            return false;
        }

        // Check TTL.
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            @unlink($file);
            return false;
        }

        return true;
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(array $values, int $ttl = 0): bool
    {
        $ok = true;
        foreach ($values as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                $ok = false;
            }
        }
        return $ok;
    }

    public function deleteMultiple(array $keys): bool
    {
        $ok = true;
        foreach ($keys as $key) {
            if (!$this->delete($key) && $this->has($key)) {
                $ok = false;
            }
        }
        return $ok;
    }

    public function flush(): bool
    {
        return $this->deleteDirectory($this->cacheDir, false);
    }

    public function flushGroup(string $group): bool
    {
        $versionFile = $this->cacheDir . '/_versions/' . $this->sanitize($group) . '.ver';
        $this->ensureDir(dirname($versionFile));

        $current = 0;
        if (file_exists($versionFile)) {
            $raw     = file_get_contents($versionFile);
            $current = $raw !== false ? (int) $raw : 0;
        }

        $newVersion = $current + 1;
        file_put_contents($versionFile, (string) $newVersion, LOCK_EX);

        // Update in-memory cache.
        $this->groupVersions[$group] = $newVersion;

        return true;
    }

    public function getStats(): array
    {
        $entries = 0;
        $size    = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->cacheDir,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'cache') {
                $entries++;
                $size += $file->getSize();
            }
        }

        return [
            'driver'  => 'file',
            'hits'    => $this->hits,
            'misses'  => $this->misses,
            'memory'  => $size,
            'uptime'  => null,
            'entries' => $entries,
        ];
    }

    public function isAvailable(): bool
    {
        return is_dir($this->cacheDir) && is_writable($this->cacheDir);
    }

    public function getDriverName(): string
    {
        return 'file';
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Convert a cache key to a filesystem path.
     *
     * Format: {cacheDir}/{group}/{version}/{hash}.cache
     */
    private function keyToPath(string $key): string
    {
        $colonPos = strpos($key, ':');

        if ($colonPos !== false) {
            $group    = $this->sanitize(substr($key, 0, $colonPos));
            $localKey = substr($key, $colonPos + 1);
        } else {
            $group    = '_default';
            $localKey = $key;
        }

        $version = $this->getGroupVersion($group);

        // Hash the key to prevent filesystem issues with special characters.
        $hash = md5($localKey);

        // Use first 2 chars as subdirectory for distribution (avoids too many files per dir).
        $sub = substr($hash, 0, 2);

        return $this->cacheDir . '/' . $group . '/v' . $version . '/' . $sub . '/' . $hash . '.cache';
    }

    /**
     * Get the current version counter for a group.
     */
    private function getGroupVersion(string $group): int
    {
        if (isset($this->groupVersions[$group])) {
            return $this->groupVersions[$group];
        }

        $versionFile = $this->cacheDir . '/_versions/' . $this->sanitize($group) . '.ver';

        if (!file_exists($versionFile)) {
            $this->groupVersions[$group] = 0;
            return 0;
        }

        $raw     = file_get_contents($versionFile);
        $version = $raw !== false ? (int) $raw : 0;

        $this->groupVersions[$group] = $version;
        return $version;
    }

    /**
     * Read and unserialize a cache file.
     *
     * @return array|null Null if the file is unreadable or corrupt.
     */
    private function readFile(string $file): ?array
    {
        $raw = @file_get_contents($file);

        if ($raw === false) {
            return null;
        }

        try {
            $data = unserialize($raw);
        } catch (\Throwable) {
            @unlink($file);
            return null;
        }

        if (!is_array($data) || !array_key_exists('value', $data)) {
            @unlink($file);
            return null;
        }

        return $data;
    }

    /**
     * Sanitize a string for use as a directory name.
     */
    private function sanitize(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
    }

    /**
     * Ensure a directory exists with proper permissions.
     */
    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
    }

    /**
     * Recursively delete a directory's contents.
     *
     * @param string $dir        Directory path.
     * @param bool   $removeSelf Also remove the directory itself.
     * @return bool
     */
    private function deleteDirectory(string $dir, bool $removeSelf = true): bool
    {
        if (!is_dir($dir)) {
            return true;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        if ($removeSelf) {
            @rmdir($dir);
        }

        return true;
    }
}
