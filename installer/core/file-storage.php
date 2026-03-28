<?php
/**
 * Klytos — File Storage
 * Flat-file implementation of StorageInterface.
 *
 * Stores each record as an individual AES-256-GCM encrypted JSON file
 * inside a directory structure: {dataDir}/{collection}/{id}.json.enc
 *
 * Security measures:
 * - All data encrypted at rest with AES-256-GCM (authenticated encryption).
 * - Atomic writes with LOCK_EX to prevent partial writes and race conditions.
 * - Directory permissions set to 0700 (owner-only access).
 * - Path traversal prevention: IDs and collection names are sanitized.
 * - File locking for pseudo-transactions to prevent concurrent corruption.
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

class FileStorage implements StorageInterface
{
    /** @var Encryption AES-256-GCM encryption engine. */
    private Encryption $enc;

    /** @var string Absolute path to the data root directory. */
    private string $dataDir;

    /**
     * Lock file handle for pseudo-transactions.
     * Held open while a transaction is in progress to prevent concurrent writes.
     *
     * @var resource|null
     */
    private $transactionLock = null;

    /**
     * Collections that contain sensitive data and MUST be encrypted.
     * Everything else is stored as plain JSON for recoverability.
     *
     * If a collection is 'config', encryption is determined per-ID
     * using SENSITIVE_CONFIG_IDS below.
     */
    private const SENSITIVE_COLLECTIONS = [
        'users',
        'webhooks',
        'analytics-salt',
    ];

    /**
     * Within the 'config' collection, these IDs contain sensitive data.
     * Other config IDs (site, theme, menus, templates) are stored in plain JSON.
     */
    private const SENSITIVE_CONFIG_IDS = [
        'tokens',
        'app_passwords',
        'oauth_clients',
    ];

    /**
     * Constructor.
     *
     * @param Encryption $enc     Encryption engine initialized with the master key.
     * @param string     $dataDir Absolute path to the data directory (e.g. /var/www/klytos/data).
     */
    public function __construct(Encryption $enc, string $dataDir)
    {
        $this->enc     = $enc;
        $this->dataDir = rtrim($dataDir, '/');
    }

    /**
     * Determine if a collection+id pair requires encryption.
     */
    private function isSensitive(string $collection, string $id = ''): bool
    {
        if (in_array($collection, self::SENSITIVE_COLLECTIONS, true)) {
            return true;
        }

        if ($collection === 'config' && in_array($id, self::SENSITIVE_CONFIG_IDS, true)) {
            return true;
        }

        return false;
    }

    // ─── Core CRUD Operations ────────────────────────────────────

    /**
     * Read and decrypt a record from a collection.
     *
     * Flow: resolve path → verify existence → read raw bytes → AES-256-GCM decrypt → JSON decode.
     *
     * @param  string $collection Collection name (e.g. 'pages', 'users').
     * @param  string $id         Record identifier (e.g. 'index', 'user-001').
     * @return array  Decrypted associative array.
     * @throws \RuntimeException If the file does not exist or decryption fails.
     */
    public function read(string $collection, string $id = ''): array
    {
        // v1.0 backwards compatibility: if called with a single argument
        // ending in '.json.enc', treat it as a direct file read from data/.
        // Example: read('tokens.json.enc') → reads data/tokens.json.enc
        if ($id === '' && str_ends_with($collection, '.json.enc')) {
            return $this->readFrom($this->dataDir, $collection);
        }

        $path = $this->resolvePath($collection, $id);

        if (!file_exists($path)) {
            throw new \RuntimeException(
                "Record not found: {$collection}/{$id}"
            );
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException(
                "Cannot read record: {$collection}/{$id}"
            );
        }

        // Only decrypt sensitive data. Plain JSON is stored as-is.
        if ($this->isSensitive($collection, $id)) {
            return $this->enc->decrypt($content);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException(
                "Cannot decode JSON: {$collection}/{$id}"
            );
        }
        return $decoded;
    }

    /**
     * Encrypt and write a record to a collection.
     *
     * Flow: sanitize → ensure directory (0700) → AES-256-GCM encrypt → atomic write with LOCK_EX.
     *
     * LOCK_EX ensures that:
     * 1. No other process can write to the same file simultaneously.
     * 2. The write is atomic — readers won't see partial data.
     *
     * @param string $collection Collection name.
     * @param string $id         Record identifier.
     * @param array  $data       Associative array to encrypt and store.
     * @throws \RuntimeException If the write operation fails.
     */
    public function write(string $collection, string|array $id = '', array $data = []): void
    {
        // v1.0 backwards compatibility: if $id is an array, it means
        // the caller used the old API: write('file.json.enc', $dataArray).
        // Redirect to writeTo() for direct file writes.
        if (is_array($id)) {
            $this->writeTo($this->dataDir, $collection, $id);
            return;
        }

        // If $id is empty and collection ends in .json.enc, it's also old API.
        if ($id === '' && str_ends_with($collection, '.json.enc')) {
            $this->writeTo($this->dataDir, $collection, $data);
            return;
        }

        $path = $this->resolvePath($collection, $id);
        $dir  = dirname($path);

        // Create the collection directory if it doesn't exist.
        // Permissions 0700: only the web server user can read/write/list.
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true)) {
                throw new \RuntimeException(
                    "Cannot create directory: {$dir}"
                );
            }
        }

        // Only encrypt sensitive data. Plain JSON is stored as-is for recoverability.
        if ($this->isSensitive($collection, $id)) {
            $content = $this->enc->encrypt($data);
        } else {
            $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // LOCK_EX: exclusive lock during write — prevents race conditions.
        $result = file_put_contents($path, $content, LOCK_EX);

        if ($result === false) {
            throw new \RuntimeException(
                "Failed to write record: {$collection}/{$id}"
            );
        }
    }

    /**
     * Delete a record from a collection.
     *
     * @param  string $collection Collection name.
     * @param  string $id         Record identifier.
     * @return bool   True if deleted, false if the record didn't exist.
     */
    public function delete(string $collection, string $id = ''): bool
    {
        // v1.0 backwards compatibility.
        if ($id === '' && str_ends_with($collection, '.json.enc')) {
            $path = $this->dataDir . '/' . $collection;
            return file_exists($path) && unlink($path);
        }

        $path = $this->resolvePath($collection, $id);

        if (!file_exists($path)) {
            return false;
        }

        return unlink($path);
    }

    /**
     * Check if a record exists in a collection.
     *
     * @param  string $collection Collection name.
     * @param  string $id         Record identifier.
     * @return bool
     */
    public function exists(string $collection, string $id = ''): bool
    {
        // v1.0 backwards compatibility.
        if ($id === '' && str_ends_with($collection, '.json.enc')) {
            return file_exists($this->dataDir . '/' . $collection);
        }

        return file_exists($this->resolvePath($collection, $id));
    }

    // ─── Query Operations ────────────────────────────────────────

    /**
     * List all records in a collection with optional filtering.
     *
     * Reads and decrypts every file in the collection directory,
     * then applies in-memory filters. This is efficient for small-to-medium
     * datasets (hundreds of records). For large datasets, use DatabaseStorage.
     *
     * @param  string $collection Collection name.
     * @param  array  $filters    Key-value pairs to match (e.g. ['status' => 'published']).
     * @param  int    $limit      Maximum records to return (0 = unlimited).
     * @param  int    $offset     Number of records to skip.
     * @return array  Array of decrypted records.
     */
    public function list(string $collection, array $filters = [], int $limit = 0, int $offset = 0): array
    {
        $dir = $this->dataDir . '/' . $this->sanitizeName($collection);

        if (!is_dir($dir)) {
            return [];
        }

        // Find all encrypted JSON files in the collection directory.
        $files = array_merge(
            glob($dir . '/*.json') ?: [],
            glob($dir . '/*.json.enc') ?: []
        );
        if ($files === false) {
            return [];
        }

        $records = [];

        foreach ($files as $file) {
            try {
                $content = file_get_contents($file);
                if ($content === false) {
                    continue;
                }

                // Determine if file is encrypted based on extension.
                if (str_ends_with($file, '.json.enc')) {
                    $record = $this->enc->decrypt($content);
                } else {
                    $record = json_decode($content, true);
                    if (!is_array($record)) {
                        continue;
                    }
                }

                // Apply filters: every filter key must match the record value.
                if (!$this->matchesFilters($record, $filters)) {
                    continue;
                }

                $records[] = $record;
            } catch (\RuntimeException $e) {
                // Skip corrupted or unreadable files.
                error_log("Klytos FileStorage: skipping corrupted file {$file}: " . $e->getMessage());
                continue;
            }
        }

        // Apply pagination.
        if ($offset > 0) {
            $records = array_slice($records, $offset);
        }

        if ($limit > 0) {
            $records = array_slice($records, 0, $limit);
        }

        return $records;
    }

    /**
     * Count records in a collection with optional filters.
     *
     * @param  string $collection Collection name.
     * @param  array  $filters    Key-value pairs to match.
     * @return int    Total matching records.
     */
    public function count(string $collection, array $filters = []): int
    {
        // If no filters, we can count files without decrypting (much faster).
        if (empty($filters)) {
            $dir = $this->dataDir . '/' . $this->sanitizeName($collection);
            if (!is_dir($dir)) {
                return 0;
            }

            $files = array_merge(
            glob($dir . '/*.json') ?: [],
            glob($dir . '/*.json.enc') ?: []
        );
            return $files !== false ? count($files) : 0;
        }

        // With filters, we must read and decrypt each file.
        return count($this->list($collection, $filters));
    }

    /**
     * Search records by matching a query string against specified fields.
     *
     * Uses case-insensitive substring matching. For full-text search
     * on large datasets, use DatabaseStorage with MySQL FULLTEXT indexes.
     *
     * @param  string $collection Collection name.
     * @param  string $query      Search text (case-insensitive).
     * @param  array  $fields     Fields to search in (e.g. ['title', 'content_html']).
     *                            Empty = search all string fields.
     * @param  int    $limit      Maximum results.
     * @return array  Matching records.
     */
    public function search(string $collection, string $query, array $fields = [], int $limit = 50): array
    {
        if (empty($query)) {
            return [];
        }

        $dir = $this->dataDir . '/' . $this->sanitizeName($collection);

        if (!is_dir($dir)) {
            return [];
        }

        $files = array_merge(
            glob($dir . '/*.json') ?: [],
            glob($dir . '/*.json.enc') ?: []
        );
        $results = [];
        $queryLc = mb_strtolower($query);

        foreach ($files as $file) {
            if (count($results) >= $limit) {
                break;
            }

            try {
                $content = file_get_contents($file);
                if ($content === false) {
                    continue;
                }

                if (str_ends_with($file, '.json.enc')) {
                    $record = $this->enc->decrypt($content);
                } else {
                    $record = json_decode($content, true);
                    if (!is_array($record)) {
                        continue;
                    }
                }

                // Determine which fields to search.
                $searchFields = !empty($fields)
                    ? $fields
                    : array_keys(array_filter($record, 'is_string'));

                // Check if any target field contains the query (case-insensitive).
                foreach ($searchFields as $field) {
                    if (isset($record[$field]) && is_string($record[$field])) {
                        if (mb_strpos(mb_strtolower($record[$field]), $queryLc) !== false) {
                            $results[] = $record;
                            break; // No need to check other fields for this record.
                        }
                    }
                }
            } catch (\RuntimeException $e) {
                // Skip corrupted files silently.
                error_log("Klytos FileStorage: search skip {$file}: " . $e->getMessage());
                continue;
            }
        }

        return $results;
    }

    // ─── Transaction Support ─────────────────────────────────────

    /**
     * Execute a callback within a pseudo-transaction.
     *
     * For flat-file storage, true ACID transactions are not possible.
     * This implementation uses a global lock file to serialize access,
     * preventing concurrent modifications. If the callback throws,
     * the lock is released but changes are NOT rolled back (flat-file
     * limitation — individual writes are already atomic via LOCK_EX).
     *
     * For true transactional guarantees, use DatabaseStorage.
     *
     * @param  callable $callback Receives this FileStorage instance.
     * @return mixed    The callback's return value.
     * @throws \Throwable Re-throws exceptions after releasing the lock.
     */
    public function transaction(callable $callback): mixed
    {
        $lockFile = $this->dataDir . '/.transaction.lock';

        // Create lock file if it doesn't exist.
        if (!file_exists($lockFile)) {
            Helpers::ensureWritableDir($this->dataDir);
            touch($lockFile);
            chmod($lockFile, 0600);
        }

        // Acquire exclusive lock (blocking — waits until available).
        $this->transactionLock = fopen($lockFile, 'r+');
        if ($this->transactionLock === false) {
            throw new \RuntimeException('Cannot open transaction lock file.');
        }

        if (!flock($this->transactionLock, LOCK_EX)) {
            fclose($this->transactionLock);
            $this->transactionLock = null;
            throw new \RuntimeException('Cannot acquire transaction lock.');
        }

        try {
            $result = $callback($this);
            return $result;
        } finally {
            // Always release the lock, even if an exception occurred.
            flock($this->transactionLock, LOCK_UN);
            fclose($this->transactionLock);
            $this->transactionLock = null;
        }
    }

    // ─── Legacy / Config Helpers ─────────────────────────────────

    /**
     * Read a file from an arbitrary base directory (for config files).
     *
     * Used to read files outside the data directory, such as
     * config/config.json.enc or config/license.json.enc.
     *
     * @param  string $basePath Absolute directory path.
     * @param  string $file     Filename (e.g. 'config.json.enc').
     * @return array  Decrypted data.
     * @throws \RuntimeException If file not found or decryption fails.
     */
    public function readFrom(string $basePath, string $file): array
    {
        $path = rtrim($basePath, '/') . '/' . $file;

        if (!file_exists($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file: {$path}");
        }

        return $this->enc->decrypt($content);
    }

    /**
     * Write a file to an arbitrary base directory (for config files).
     *
     * @param string $basePath Absolute directory path.
     * @param string $file     Filename.
     * @param array  $data     Data to encrypt and store.
     * @throws \RuntimeException If the write fails.
     */
    public function writeTo(string $basePath, string $file, array $data): void
    {
        $path = rtrim($basePath, '/') . '/' . $file;
        $dir  = dirname($path);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true)) {
                throw new \RuntimeException("Cannot create directory: {$dir}");
            }
        }

        $encrypted = $this->enc->encrypt($data);
        $result    = file_put_contents($path, $encrypted, LOCK_EX);

        if ($result === false) {
            throw new \RuntimeException("Failed to write file: {$path}");
        }
    }

    /**
     * Get the encryption engine instance.
     *
     * @return Encryption
     */
    public function getEncryption(): Encryption
    {
        return $this->enc;
    }

    /**
     * Get the base data directory path.
     *
     * @return string
     */
    public function getDataDir(): string
    {
        return $this->dataDir;
    }

    // ─── Internal Helpers ────────────────────────────────────────

    /**
     * Resolve a collection + ID pair to an absolute file path.
     *
     * Security: both collection and ID are sanitized to prevent
     * path traversal attacks (e.g. '../config/.encryption_key').
     *
     * @param  string $collection Collection name.
     * @param  string $id         Record identifier.
     * @return string Absolute path to the encrypted file.
     */
    private function resolvePath(string $collection, string $id): string
    {
        $safeCollection = $this->sanitizeName($collection);
        $safeId         = $this->sanitizeId($id);

        $ext = $this->isSensitive($collection, $id) ? '.json.enc' : '.json';
        return $this->dataDir . '/' . $safeCollection . '/' . $safeId . $ext;
    }

    /**
     * Sanitize a collection name to prevent path traversal.
     *
     * Only allows: lowercase letters, digits, hyphens, underscores.
     * Removes any dots, slashes, or other special characters.
     *
     * @param  string $name Raw collection name.
     * @return string Sanitized name.
     * @throws \InvalidArgumentException If the name is empty after sanitization.
     */
    private function sanitizeName(string $name): string
    {
        // Remove anything that isn't alphanumeric, hyphen, or underscore.
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $name);

        if (empty($safe)) {
            throw new \InvalidArgumentException(
                "Invalid collection name: '{$name}'"
            );
        }

        return strtolower($safe);
    }

    /**
     * Sanitize a record ID to prevent path traversal.
     *
     * Allows: letters, digits, hyphens, underscores.
     * Converts slashes to double-hyphens (for nested slugs like 'en/about' → 'en--about').
     * Removes dots and other special characters.
     *
     * @param  string $id Raw record identifier.
     * @return string Sanitized ID.
     * @throws \InvalidArgumentException If the ID is empty after sanitization.
     */
    private function sanitizeId(string $id): string
    {
        // Convert slashes to double-hyphens (preserves slug hierarchy).
        $safe = str_replace('/', '--', $id);

        // Remove anything that isn't alphanumeric, hyphen, or underscore.
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $safe);

        if (empty($safe)) {
            throw new \InvalidArgumentException(
                "Invalid record ID: '{$id}'"
            );
        }

        return $safe;
    }

    /**
     * Check if a record matches all the given filters.
     *
     * Each filter key must exist in the record and its value must match.
     * Supports simple equality comparison.
     *
     * @param  array $record  Decrypted record data.
     * @param  array $filters Key-value pairs to match.
     * @return bool  True if all filters match (or if no filters given).
     */
    private function matchesFilters(array $record, array $filters): bool
    {
        foreach ($filters as $key => $value) {
            // Support dot-notation for nested fields (e.g. 'social.twitter').
            $actual = $this->getNestedValue($record, $key);

            if ($actual !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a nested value from an array using dot notation.
     *
     * @param  array  $data Array to search.
     * @param  string $key  Dot-notation key (e.g. 'social.twitter').
     * @return mixed  The value, or null if not found.
     */
    private function getNestedValue(array $data, string $key): mixed
    {
        $parts = explode('.', $key);
        $value = $data;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }

        return $value;
    }
}
