<?php
/**
 * Klytos — Audit Log
 * Tracks all user actions for security, compliance, and debugging.
 *
 * Every significant action is logged with:
 * - Who (user_id, username)
 * - What (action type: create, update, delete, login, etc.)
 * - On what (entity_type + entity_id: page/index, user/abc123, etc.)
 * - From where (source: admin, mcp, cli, plugin)
 * - IP address (for security investigation)
 * - Timestamp
 *
 * Storage:
 * - Flat-file mode: data/audit_log/YYYY-MM-DD.json.enc (daily files).
 * - Database mode: kly_audit_log table.
 *
 * Retention: configurable, default 90 days. Older logs are pruned automatically.
 *
 * Security:
 * - Audit logs are encrypted at rest (same as all Klytos data).
 * - Logs cannot be modified or deleted by non-owner users.
 * - Failed login attempts are logged (without the attempted password).
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

class AuditLog
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string Collection name for daily log files (flat-file) or table (database). */
    private const COLLECTION = 'audit-log';

    /** @var int Default retention period in days. */
    private const DEFAULT_RETENTION_DAYS = 90;

    /**
     * @param StorageInterface $storage Storage backend instance.
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Record an action in the audit log.
     *
     * This is the primary method — called throughout the CMS whenever
     * a significant action occurs.
     *
     * @param string      $action     Action type: 'create', 'update', 'delete', 'login',
     *                                'logout', 'login_failed', 'activate', 'deactivate',
     *                                'build', 'backup', 'restore', 'settings_changed'.
     * @param string      $entityType Entity type: 'page', 'user', 'theme', 'menu',
     *                                'plugin', 'token', 'oauth_client', 'backup', 'build'.
     * @param string      $entityId   Entity identifier (e.g. page slug, user ID).
     * @param array       $details    Additional context data (e.g. changed fields).
     * @param string      $source     Where the action originated: 'admin', 'mcp', 'cli',
     *                                'plugin', 'cron', 'front_editor'.
     * @param string|null $userId     User ID who performed the action (null for system actions).
     * @param string|null $username   Username (for display without looking up the user).
     */
    public function record(
        string $action,
        string $entityType,
        string $entityId,
        array $details = [],
        string $source = 'admin',
        ?string $userId = null,
        ?string $username = null,
    ): void {
        $entry = [
            'timestamp'   => Helpers::now(),
            'user_id'     => $userId,
            'username'    => $username,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'details'     => $details,
            'source'      => $source,
            'ip_address'  => $this->getClientIp(),
        ];

        // Generate a unique entry ID (timestamp + random for uniqueness).
        $entryId = date('Ymd-His') . '-' . Helpers::randomHex(4);

        try {
            $this->storage->write(self::COLLECTION, $entryId, $entry);
        } catch (\Throwable $e) {
            // Audit log failures should NEVER crash the CMS.
            // Log to PHP's error log as a fallback.
            error_log('Klytos AuditLog: failed to write entry: ' . $e->getMessage());
        }
    }

    /**
     * Query audit log entries with optional filters.
     *
     * @param  array  $filters  Key-value filters: action, entity_type, user_id, source.
     * @param  int    $limit    Maximum entries to return.
     * @param  int    $offset   Entries to skip (for pagination).
     * @return array  Array of log entries, newest first.
     */
    public function query(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $entries = $this->storage->list(self::COLLECTION, $filters, $limit, $offset);

        // Sort by timestamp descending (newest first).
        usort($entries, function (array $a, array $b): int {
            return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
        });

        return $entries;
    }

    /**
     * Get recent activity (last N entries, no filters).
     *
     * Used by the admin dashboard widget.
     *
     * @param  int   $count Number of entries to return.
     * @return array Recent log entries.
     */
    public function getRecentActivity(int $count = 10): array
    {
        return $this->query([], $count);
    }

    /**
     * Prune old audit log entries beyond the retention period.
     *
     * Called by the pseudo-cron system (CronManager) periodically.
     *
     * @param  int $retentionDays Number of days to keep. Default: 90.
     * @return int Number of entries pruned.
     */
    public function prune(int $retentionDays = self::DEFAULT_RETENTION_DAYS): int
    {
        $cutoff  = date('c', strtotime("-{$retentionDays} days"));
        $entries = $this->storage->list(self::COLLECTION);
        $pruned  = 0;

        foreach ($entries as $entry) {
            $entryTime = $entry['timestamp'] ?? '';
            if (!empty($entryTime) && $entryTime < $cutoff) {
                // Reconstruct the entry ID to delete it.
                // The entry ID is embedded in the storage key.
                $entryId = $this->reconstructEntryId($entry);
                if ($entryId !== null) {
                    $this->storage->delete(self::COLLECTION, $entryId);
                    $pruned++;
                }
            }
        }

        return $pruned;
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Get the client's IP address.
     *
     * Trusts X-Forwarded-For ONLY when the request comes from localhost
     * (reverse proxy on the same machine). Otherwise uses REMOTE_ADDR.
     *
     * @return string Client IP address.
     */
    private function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Only trust X-Forwarded-For from loopback (reverse proxy on same machine).
        if (in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if (!empty($forwarded)) {
                // Take the first (leftmost) IP — the original client.
                $parts = explode(',', $forwarded);
                return trim($parts[0]);
            }
        }

        return $remoteAddr;
    }

    /**
     * Reconstruct an entry ID from its data for deletion.
     *
     * Since entry IDs contain timestamps, we can approximate the ID from the entry data.
     * This is an imperfect match — in practice, the storage layer handles this.
     *
     * @param  array $entry Log entry data.
     * @return string|null Reconstructed entry ID, or null if not possible.
     */
    private function reconstructEntryId(array $entry): ?string
    {
        // The entry ID format is: YYYYMMDD-HHiiss-XXXX
        // We can search by timestamp prefix in the storage.
        $timestamp = $entry['timestamp'] ?? '';
        if (empty($timestamp)) {
            return null;
        }

        // Convert ISO timestamp to entry ID format.
        $date = date('Ymd-His', strtotime($timestamp));

        // Search for entries matching this timestamp prefix.
        $entries = $this->storage->search(self::COLLECTION, $date, ['timestamp']);

        // Return the first match (there should only be one per exact timestamp).
        return !empty($entries) ? ($entries[0]['id'] ?? null) : null;
    }
}
