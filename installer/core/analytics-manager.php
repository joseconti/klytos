<?php
/**
 * Klytos — Analytics Manager
 * Privacy-first, cookie-free, GDPR-compliant page view analytics.
 *
 * Design principles:
 * - NO cookies. Zero. Ever.
 * - NO fingerprinting. No canvas, WebGL, or font detection.
 * - IP addresses are hashed (SHA-256 + daily rotating salt) — never stored raw.
 * - Minimal JS footprint (~2KB).
 * - No external requests (no Google Analytics, no third-party trackers).
 * - All data stored locally (encrypted like everything else in Klytos).
 *
 * What is tracked:
 * - Page path (which page was visited).
 * - Referrer domain (where the visitor came from, NOT the full URL).
 * - Screen width category (mobile/tablet/desktop — not exact pixels).
 * - Visitor hash (SHA-256 of IP + daily salt — unique per day, not linkable across days).
 * - Timestamp.
 *
 * What is NOT tracked:
 * - Raw IP addresses.
 * - User agent strings.
 * - Exact screen dimensions.
 * - Any personally identifiable information (PII).
 * - Cross-session behavior (no cookies = no returning visitor tracking).
 *
 * Storage:
 * - Collection 'analytics' in StorageInterface.
 * - Each entry keyed by: YYYY-MM-DD-{random} (daily granularity).
 * - Old data pruned after configurable retention (default: 90 days).
 *
 * @package Klytos
 * @since   2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class AnalyticsManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string Collection name for analytics data. */
    private const COLLECTION = 'analytics';

    /** @var string Collection for daily salt (rotated every 24h). */
    private const SALT_COLLECTION = 'analytics-salt';

    /** @var int Default data retention in days. */
    private const DEFAULT_RETENTION_DAYS = 90;

    /**
     * @param StorageInterface $storage Storage backend instance.
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    // ─── Data Collection ─────────────────────────────────────────

    /**
     * Record a page view event.
     *
     * Called by t.php (the tracking pixel/beacon endpoint).
     * All data is anonymized before storage.
     *
     * @param string $pagePath    Page path (e.g. '/about', '/en/services').
     * @param string $referrer    Full referrer URL (only domain is stored).
     * @param int    $screenWidth Screen width in pixels (categorized, not stored raw).
     * @param string $ipAddress   Visitor IP (hashed with daily salt, never stored raw).
     */
    public function recordPageView(
        string $pagePath,
        string $referrer = '',
        int $screenWidth = 0,
        string $ipAddress = '',
    ): void {
        // Anonymize the IP address: SHA-256 hash with a daily rotating salt.
        // This allows counting unique visitors per day WITHOUT storing real IPs.
        // The salt rotates daily, so the same IP produces different hashes each day
        // (impossible to track visitors across days).
        $visitorHash = $this->hashVisitorIdentity($ipAddress);

        // Extract only the referrer domain (not the full URL — privacy).
        $referrerDomain = $this->extractDomain($referrer);

        // Categorize screen width into device classes (not exact pixels — privacy).
        $deviceCategory = $this->categorizeDevice($screenWidth);

        // Build the analytics entry.
        $entry = [
            'page_path'       => $this->sanitizePath($pagePath),
            'referrer_domain' => $referrerDomain,
            'device_category' => $deviceCategory,
            'visitor_hash'    => $visitorHash,
            'date'            => date('Y-m-d'),
            'timestamp'       => Helpers::now(),
        ];

        // Allow plugins to modify/extend the analytics event (e.g. add UTM params).
        $entry = Hooks::applyFilters('analytics.event', $entry);

        // Generate a unique ID for this entry.
        $entryId = date('Ymd') . '-' . Helpers::randomHex(6);

        try {
            $this->storage->write(self::COLLECTION, $entryId, $entry);
        } catch (\Throwable $e) {
            // Analytics failures should NEVER crash the site.
            error_log('Klytos Analytics: failed to record pageview: ' . $e->getMessage());
        }
    }

    // ─── Data Querying ───────────────────────────────────────────

    /**
     * Get analytics summary for a date range.
     *
     * @param  string $dateFrom Start date (YYYY-MM-DD).
     * @param  string $dateTo   End date (YYYY-MM-DD).
     * @return array  Summary: total_views, unique_visitors, top_pages, top_referrers, devices.
     */
    public function getSummary(string $dateFrom, string $dateTo): array
    {
        $entries = $this->getEntriesInRange($dateFrom, $dateTo);

        // Count total page views.
        $totalViews = count($entries);

        // Count unique visitors (distinct visitor hashes).
        $uniqueHashes   = array_unique(array_column($entries, 'visitor_hash'));
        $uniqueVisitors = count($uniqueHashes);

        // Top pages by view count.
        $pageCounts = array_count_values(array_column($entries, 'page_path'));
        arsort($pageCounts);
        $topPages = array_slice($pageCounts, 0, 20, true);

        // Top referrer domains.
        $referrers = array_filter(array_column($entries, 'referrer_domain'));
        $refCounts = array_count_values($referrers);
        arsort($refCounts);
        $topReferrers = array_slice($refCounts, 0, 10, true);

        // Device breakdown.
        $devices    = array_count_values(array_column($entries, 'device_category'));
        $totalDev   = array_sum($devices);
        $devicePerc = [];
        foreach ($devices as $cat => $count) {
            $devicePerc[$cat] = [
                'count'      => $count,
                'percentage' => $totalDev > 0 ? round(($count / $totalDev) * 100, 1) : 0,
            ];
        }

        // Daily breakdown for charting.
        $dailyViews = [];
        foreach ($entries as $entry) {
            $date = $entry['date'] ?? '';
            $dailyViews[$date] = ($dailyViews[$date] ?? 0) + 1;
        }
        ksort($dailyViews);

        return [
            'date_from'        => $dateFrom,
            'date_to'          => $dateTo,
            'total_views'      => $totalViews,
            'unique_visitors'  => $uniqueVisitors,
            'top_pages'        => $topPages,
            'top_referrers'    => $topReferrers,
            'devices'          => $devicePerc,
            'daily_views'      => $dailyViews,
        ];
    }

    /**
     * Get the top pages by view count.
     *
     * @param  string $dateFrom Start date (YYYY-MM-DD).
     * @param  string $dateTo   End date (YYYY-MM-DD).
     * @param  int    $limit    Maximum pages to return.
     * @return array  Array of ['path' => string, 'views' => int].
     */
    public function getTopPages(string $dateFrom, string $dateTo, int $limit = 20): array
    {
        $entries    = $this->getEntriesInRange($dateFrom, $dateTo);
        $pageCounts = array_count_values(array_column($entries, 'page_path'));
        arsort($pageCounts);

        $result = [];
        $i      = 0;
        foreach ($pageCounts as $path => $views) {
            if ($i >= $limit) break;
            $result[] = ['path' => $path, 'views' => $views];
            $i++;
        }

        return $result;
    }

    /**
     * Prune analytics data older than the retention period.
     *
     * Called by CronManager periodically.
     *
     * @param  int $retentionDays Days to keep. Default: 90.
     * @return int Number of entries pruned.
     */
    public function prune(int $retentionDays = self::DEFAULT_RETENTION_DAYS): int
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$retentionDays} days"));
        $entries    = $this->storage->list(self::COLLECTION);
        $pruned     = 0;

        foreach ($entries as $entry) {
            $entryDate = $entry['date'] ?? '';
            if (!empty($entryDate) && $entryDate < $cutoffDate) {
                // Reconstruct entry ID from the date.
                // Entry IDs start with YYYYMMDD so we can filter by prefix.
                // This is a best-effort approach for the flat-file backend.
                $this->storage->delete(self::COLLECTION, $entry['id'] ?? '');
                $pruned++;
            }
        }

        return $pruned;
    }

    // ─── Anonymization Helpers ───────────────────────────────────

    /**
     * Hash a visitor's IP address with a daily rotating salt.
     *
     * The salt changes every day at midnight, so:
     * - Same IP on the same day → same hash (for unique counting).
     * - Same IP on different days → different hashes (no cross-day tracking).
     * - The raw IP is NEVER stored.
     *
     * @param  string $ipAddress Raw IP address.
     * @return string SHA-256 hash (hex, 64 chars).
     */
    private function hashVisitorIdentity(string $ipAddress): string
    {
        $salt = $this->getDailySalt();
        return hash('sha256', $ipAddress . $salt);
    }

    /**
     * Get or generate the daily salt for IP hashing.
     *
     * The salt is a random 32-byte value that rotates every 24 hours.
     * Stored encrypted in the analytics-salt collection.
     *
     * @return string Daily salt (hex string).
     */
    private function getDailySalt(): string
    {
        $today = date('Y-m-d');

        try {
            $saltData = $this->storage->read(self::SALT_COLLECTION, $today);
            return $saltData['salt'] ?? '';
        } catch (\RuntimeException $e) {
            // No salt for today — generate a new one.
            $salt = Helpers::randomHex(32);
            $this->storage->write(self::SALT_COLLECTION, $today, [
                'salt' => $salt,
                'date' => $today,
            ]);
            return $salt;
        }
    }

    /**
     * Extract only the domain from a referrer URL.
     *
     * We store "google.com" not "https://google.com/search?q=klytos+cms&hl=en".
     * This protects visitor privacy while still showing traffic sources.
     *
     * @param  string $referrer Full referrer URL.
     * @return string Domain only (e.g. 'google.com'), or empty string.
     */
    private function extractDomain(string $referrer): string
    {
        if (empty($referrer)) {
            return '';
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (empty($host)) {
            return '';
        }

        // Remove 'www.' prefix for consistency.
        return preg_replace('/^www\./', '', strtolower($host));
    }

    /**
     * Categorize screen width into a device class.
     *
     * We DON'T store the exact pixel width (that's fingerprinting).
     * Instead, we store a broad category.
     *
     * @param  int    $width Screen width in pixels.
     * @return string Device category: 'mobile', 'tablet', 'desktop', or 'unknown'.
     */
    private function categorizeDevice(int $width): string
    {
        if ($width <= 0) {
            return 'unknown';
        }
        if ($width < 768) {
            return 'mobile';
        }
        if ($width < 1024) {
            return 'tablet';
        }
        return 'desktop';
    }

    /**
     * Sanitize a page path for storage.
     *
     * @param  string $path Raw page path.
     * @return string Sanitized path.
     */
    private function sanitizePath(string $path): string
    {
        // Remove query strings and fragments (privacy: no tracking params).
        $path = strtok($path, '?');
        $path = strtok($path, '#');

        // Normalize: lowercase, trim trailing slashes, ensure leading slash.
        $path = strtolower(trim($path, '/'));
        return '/' . $path;
    }

    /**
     * Get all analytics entries within a date range.
     *
     * @param  string $dateFrom Start date (YYYY-MM-DD).
     * @param  string $dateTo   End date (YYYY-MM-DD).
     * @return array  All matching entries.
     */
    private function getEntriesInRange(string $dateFrom, string $dateTo): array
    {
        $entries = $this->storage->list(self::COLLECTION);

        return array_filter($entries, function (array $entry) use ($dateFrom, $dateTo): bool {
            $date = $entry['date'] ?? '';
            return $date >= $dateFrom && $date <= $dateTo;
        });
    }
}
