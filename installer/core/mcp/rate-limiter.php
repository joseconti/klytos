<?php

/**
 * Klytos — Rate Limiter
 * Sliding-window rate limiter using flat-file storage.
 * Used by MCP endpoint, OAuth token endpoint, and auth failure tracking.
 *
 * @package Klytos
 * @since   1.1.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\MCP;

use Klytos\Core\FileLock;

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
        self::ensureFileLock();

        $allowed = false;

        // The whole read -> decide -> write runs under ONE exclusive lock
        // (D-059, audit NEW-20). It previously read under a shared lock,
        // decided, and wrote under a separate exclusive one, so concurrent
        // callers all read the same pre-increment window and all but one
        // increment was lost. Measured on the unfixed code: 20 simultaneous
        // check() calls recorded 2-4 of themselves.
        $ran = FileLock::transaction(
            $this->filePath,
            function (array $data) use ($identifier, $maxRequests, &$allowed): array {
                $now    = time();
                $cutoff = $now - self::WINDOW_SECONDS;

                $timestamps = array_values(array_filter(
                    $data['requests'][$identifier] ?? [],
                    static fn($ts): bool => (int) $ts > $cutoff
                ));

                $allowed = count($timestamps) < $maxRequests;

                // Over the limit: the cleaned window is still written back, so
                // expired entries are pruned even on a refused request.
                if ($allowed) {
                    $timestamps[] = $now;
                }

                $data['requests'][$identifier] = $timestamps;

                // Probabilistic cleanup
                if (mt_rand(1, 100) <= (int)(self::CLEANUP_PROBABILITY * 100)) {
                    $data = $this->cleanup($data);
                }

                return $data;
            }
        );

        // A lock that could not be taken REFUSES (D-059). Returning true here
        // would hand out an uncounted request, which is precisely the
        // amplification this change closes — "we could not count it" is never
        // a reason to allow it.
        return $ran && $allowed;
    }

    /**
     * Record an authentication failure for an IP address.
     *
     * @param  string $ip Client IP address.
     * @return bool   True if still under limit, false if should block.
     */
    public function recordAuthFailure(string $ip): bool
    {
        self::ensureFileLock();

        $underLimit = false;

        // One exclusive lock across the whole cycle, for the same reason as
        // check() (D-059): a failure that is not counted is a free attempt.
        $ran = FileLock::transaction(
            $this->filePath,
            function (array $data) use ($ip, &$underLimit): array {
                $now    = time();
                $cutoff = $now - self::WINDOW_SECONDS;
                $key    = 'ip:' . $ip;

                $failures = array_values(array_filter(
                    $data['auth_failures'][$key] ?? [],
                    static fn($ts): bool => (int) $ts > $cutoff
                ));

                $failures[] = $now;

                $data['auth_failures'][$key] = $failures;
                $underLimit = count($failures) <= self::MAX_AUTH_FAILURES;

                return $data;
            }
        );

        // Unable to record it -> report "at the limit", not "still fine". The
        // caller uses this to decide whether to keep serving the address, and
        // the fail-closed direction is the one that cannot be gamed.
        return $ran && $underLimit;
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
     * Make sure FileLock is loaded before the two methods that need it.
     *
     * It cannot be a plain autoload and it cannot be a file-level require.
     * installer/public/comment-submit.php constructs this class BEFORE
     * App::boot() by design (D-043), and the autoloader is only registered
     * during boot — so the class would not resolve there. A require at file
     * scope fixes that and makes this file both declare a symbol and cause a
     * side effect, which is a phpcs warning and would have grown the D-025
     * baseline. Loading it lazily, at the two call sites that need it, is
     * correct on both counts. require_once is idempotent; the class_exists
     * check with autoload disabled just avoids the repeated path lookup.
     */
    private static function ensureFileLock(): void
    {
        if ( ! class_exists( FileLock::class, false ) ) {
            require_once __DIR__ . '/../file-lock.php';
        }
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

    // saveData() was removed when check() and recordAuthFailure() moved inside
    // FileLock::transaction() (D-059). Its exit condition was checked before
    // deleting it, per L-007: it was private, and after the conversion it had
    // zero call sites in this class, so nothing could reach it. Keeping it
    // would have left a second way to write this file — one that takes its own
    // separate lock, which is the exact defect audit NEW-20 records.
}
