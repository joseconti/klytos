<?php
/**
 * Klytos — Rate Limiter
 * Sliding-window rate limiter using flat-file storage.
 * Used by MCP endpoint, OAuth token endpoint, and auth failure tracking.
 *
 * @package Klytos
 * @since   1.1.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\MCP;

class RateLimiter
{
    private string $filePath;

    private const WINDOW_SECONDS          = 60;
    private const MAX_REQUESTS_PER_WINDOW = 60;
    private const MAX_AUTH_FAILURES       = 10;
    private const CLEANUP_PROBABILITY     = 0.01;

    /**
     * @param string $dataDir Absolute path to the data directory.
     */
    public function __construct(string $dataDir)
    {
        $this->filePath = rtrim($dataDir, '/') . '/rate_limits.json';
    }

    /**
     * Check if a request is allowed for the given identifier.
     * Increments the counter if allowed.
     *
     * @param  string $identifier e.g. "token:abc123", "ip:192.168.1.1", "apppass:ap_xyz"
     * @param  int    $maxRequests Maximum requests per window.
     * @return bool   True if allowed, false if rate limited.
     */
    public function check(string $identifier, int $maxRequests = self::MAX_REQUESTS_PER_WINDOW): bool
    {
        $data = $this->loadData();
        $now  = time();
        $cutoff = $now - self::WINDOW_SECONDS;

        // Get current window timestamps for this identifier
        $timestamps = $data['requests'][$identifier] ?? [];

        // Remove expired entries
        $timestamps = array_values(array_filter($timestamps, fn(int $ts) => $ts > $cutoff));

        if (count($timestamps) >= $maxRequests) {
            // Over limit — save cleaned data but don't add new timestamp
            $data['requests'][$identifier] = $timestamps;
            $this->saveData($data);
            return false;
        }

        // Add current request
        $timestamps[] = $now;
        $data['requests'][$identifier] = $timestamps;

        // Probabilistic cleanup
        if (mt_rand(1, 100) <= (int)(self::CLEANUP_PROBABILITY * 100)) {
            $data = $this->cleanup($data);
        }

        $this->saveData($data);
        return true;
    }

    /**
     * Record an authentication failure for an IP address.
     *
     * @param  string $ip Client IP address.
     * @return bool   True if still under limit, false if should block.
     */
    public function recordAuthFailure(string $ip): bool
    {
        $data   = $this->loadData();
        $now    = time();
        $cutoff = $now - self::WINDOW_SECONDS;

        $key = 'ip:' . $ip;
        $failures = $data['auth_failures'][$key] ?? [];

        // Remove expired
        $failures = array_values(array_filter($failures, fn(int $ts) => $ts > $cutoff));

        // Add current failure
        $failures[] = $now;
        $data['auth_failures'][$key] = $failures;

        $this->saveData($data);

        return count($failures) <= self::MAX_AUTH_FAILURES;
    }

    /**
     * Check if an IP is blocked due to too many auth failures.
     *
     * @param  string $ip Client IP address.
     * @return bool   True if blocked.
     */
    public function isAuthBlocked(string $ip): bool
    {
        $data   = $this->loadData();
        $cutoff = time() - self::WINDOW_SECONDS;

        $key = 'ip:' . $ip;
        $failures = $data['auth_failures'][$key] ?? [];

        // Count recent failures
        $recentFailures = array_filter($failures, fn(int $ts) => $ts > $cutoff);

        return count($recentFailures) >= self::MAX_AUTH_FAILURES;
    }

    /**
     * Get remaining requests for a given identifier.
     *
     * @param  string $identifier
     * @param  int    $maxRequests
     * @return int
     */
    public function getRemainingRequests(string $identifier, int $maxRequests = self::MAX_REQUESTS_PER_WINDOW): int
    {
        $data   = $this->loadData();
        $cutoff = time() - self::WINDOW_SECONDS;

        $timestamps = $data['requests'][$identifier] ?? [];
        $recent     = array_filter($timestamps, fn(int $ts) => $ts > $cutoff);

        return max(0, $maxRequests - count($recent));
    }

    /**
     * Get the client IP address.
     * Only trusts X-Forwarded-For first hop behind known proxies.
     *
     * @return string
     */
    public static function getClientIp(): string
    {
        // Direct connection IP is always trusted
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Only use X-Forwarded-For if behind a reverse proxy (loopback = proxy)
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if (!empty($forwarded)) {
                // Take only the first (client) IP
                $parts = explode(',', $forwarded);
                $clientIp = trim($parts[0]);
                if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
                    return $clientIp;
                }
            }
        }

        return $ip;
    }

    /**
     * Remove expired entries from all identifiers.
     *
     * @param  array $data
     * @return array Cleaned data.
     */
    private function cleanup(array $data): array
    {
        $cutoff = time() - self::WINDOW_SECONDS;

        // Clean request counters
        foreach ($data['requests'] ?? [] as $id => $timestamps) {
            $filtered = array_filter($timestamps, fn(int $ts) => $ts > $cutoff);
            if (empty($filtered)) {
                unset($data['requests'][$id]);
            } else {
                $data['requests'][$id] = array_values($filtered);
            }
        }

        // Clean auth failure counters
        foreach ($data['auth_failures'] ?? [] as $id => $timestamps) {
            $filtered = array_filter($timestamps, fn(int $ts) => $ts > $cutoff);
            if (empty($filtered)) {
                unset($data['auth_failures'][$id]);
            } else {
                $data['auth_failures'][$id] = array_values($filtered);
            }
        }

        return $data;
    }

    /**
     * Load rate limit data from file.
     *
     * @return array
     */
    private function loadData(): array
    {
        if (!file_exists($this->filePath)) {
            return ['requests' => [], 'auth_failures' => []];
        }

        $fp = fopen($this->filePath, 'r');
        if ($fp === false) {
            return ['requests' => [], 'auth_failures' => []];
        }

        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if (empty($content)) {
            return ['requests' => [], 'auth_failures' => []];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return ['requests' => [], 'auth_failures' => []];
        }

        return $data;
    }

    /**
     * Save rate limit data to file with exclusive lock.
     *
     * @param array $data
     */
    private function saveData(array $data): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $json = json_encode($data, JSON_UNESCAPED_SLASHES);

        $fp = fopen($this->filePath, 'c');
        if ($fp === false) {
            return;
        }

        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
