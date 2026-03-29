<?php
/**
 * Klytos — Database Storage
 * MySQL/MariaDB implementation of StorageInterface.
 *
 * Uses a single generic table per collection with encrypted JSON data columns.
 * Each collection maps to a database table: {prefix}{collection}
 * (e.g. kly_pages, kly_users, kly_tasks).
 *
 * Security measures:
 * - PDO with ATTR_EMULATE_PREPARES = false (true prepared statements).
 * - PDO with ERRMODE_EXCEPTION for fail-fast error handling.
 * - All data columns encrypted with AES-256-GCM (same as flat-file).
 * - Index columns duplicated in cleartext for efficient filtering
 *   WITHOUT needing to decrypt (e.g. status, lang, slug).
 * - Table prefix validated: only alphanumeric + underscore allowed.
 * - Parameter binding on ALL queries — no string interpolation of user data.
 * - Database credentials stored encrypted in config/database.json.enc.
 *
 * Table structure (created per collection):
 * ┌───────────────┬──────────────┬─────────────────────────────────────────┐
 * │ Column        │ Type         │ Purpose                                 │
 * ├───────────────┼──────────────┼─────────────────────────────────────────┤
 * │ id            │ VARCHAR(255) │ Record identifier (PK)                  │
 * │ data          │ LONGTEXT     │ AES-256-GCM encrypted JSON              │
 * │ idx_status    │ VARCHAR(50)  │ Cleartext index for filtering           │
 * │ idx_lang      │ VARCHAR(10)  │ Cleartext index for filtering           │
 * │ idx_type      │ VARCHAR(100) │ Cleartext index for filtering           │
 * │ idx_slug      │ VARCHAR(255) │ Cleartext index for filtering           │
 * │ created_at    │ DATETIME     │ Record creation timestamp               │
 * │ updated_at    │ DATETIME     │ Last modification timestamp             │
 * └───────────────┴──────────────┴─────────────────────────────────────────┘
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

class DatabaseStorage implements StorageInterface
{
    /** @var \PDO Database connection (lazy-initialized). */
    private ?\PDO $pdo = null;

    /** @var Encryption AES-256-GCM encryption engine. */
    private Encryption $enc;

    /** @var string Absolute path to the data directory (for legacy readFrom/writeTo). */
    private string $dataDir;

    /** @var string Table name prefix (e.g. 'kly_'). */
    private string $prefix;

    /** @var array Database connection parameters. */
    private array $dbConfig;

    /**
     * Collections that contain sensitive data and MUST be encrypted.
     * Everything else is stored as plain JSON for recoverability.
     */
    private const SENSITIVE_COLLECTIONS = [
        'users',
        'webhooks',
        'analytics-salt',
    ];

    /**
     * Within the 'config' collection, these IDs contain sensitive data.
     */
    private const SENSITIVE_CONFIG_IDS = [
        'tokens',
        'app_passwords',
        'oauth_clients',
    ];

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

    /**
     * Indexable fields per collection.
     * Maps collection names to the record fields that should be
     * stored as cleartext index columns for efficient filtering.
     *
     * @var array<string, array<string, string>>
     */
    private const INDEX_FIELDS = [
        'pages'            => ['status' => 'idx_status', 'lang' => 'idx_lang', 'slug' => 'idx_slug', 'post_type' => 'idx_type'],
        'users'            => ['role' => 'idx_status', 'username' => 'idx_slug'],
        'tasks'            => ['status' => 'idx_status', 'priority' => 'idx_type'],
        'page-versions'    => ['page_slug' => 'idx_slug'],
        'analytics'        => ['page_path' => 'idx_slug'],
        'form-submissions' => ['form_id' => 'idx_type', 'status' => 'idx_status'],
        'audit-log'        => ['action' => 'idx_type', 'user_id' => 'idx_slug'],
        'webhooks'         => ['status' => 'idx_status'],
        'plugins'          => ['status' => 'idx_status'],
        'blocks'           => ['category' => 'idx_type', 'status' => 'idx_status', 'scope' => 'idx_lang'],
        'page-templates'   => ['status' => 'idx_status'],
        'config'           => ['key' => 'idx_slug'],
    ];

    /**
     * Constructor.
     *
     * Does NOT open the database connection immediately (lazy init).
     * The connection is established on the first query.
     *
     * @param Encryption $enc      Encryption engine initialized with the master key.
     * @param string     $dataDir  Absolute path to the data directory.
     * @param array      $dbConfig Database connection parameters:
     *                             - host: MySQL host (default 'localhost').
     *                             - port: MySQL port (default 3306).
     *                             - name: Database name.
     *                             - user: Database username.
     *                             - pass: Database password.
     *                             - prefix: Table prefix (default 'kly_').
     *                             - charset: Connection charset (default 'utf8mb4').
     */
    public function __construct(Encryption $enc, string $dataDir, array $dbConfig)
    {
        $this->enc     = $enc;
        $this->dataDir = rtrim($dataDir, '/');

        // Validate and sanitize the table prefix.
        // Only alphanumeric characters and underscores are allowed.
        $prefix = $dbConfig['prefix'] ?? 'kly_';
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) {
            throw new \InvalidArgumentException(
                "Invalid table prefix: '{$prefix}'. Only alphanumeric and underscores allowed."
            );
        }
        $this->prefix = $prefix;

        $this->dbConfig = $dbConfig;
    }

    // ─── Core CRUD Operations ────────────────────────────────────

    /**
     * Read and decrypt a record from a collection.
     *
     * v1.0 compat: read('file.json.enc') reads from the data/ directory (flat file).
     * v2.0 API: read('collection', 'id') reads from the database table.
     *
     * @param  string $collection Collection name.
     * @param  string $id         Record identifier.
     * @return array  Decrypted data.
     * @throws \RuntimeException If the record does not exist.
     */
    public function read(string $collection, string $id = ''): array
    {
        // v1.0 backwards compatibility: read('file.json.enc') → flat file.
        if ($id === '' && str_ends_with($collection, '.json.enc')) {
            return $this->readFrom($this->dataDir, $collection);
        }

        $table = $this->tableName($collection);
        $pdo   = $this->getPdo();

        try {
            $stmt = $pdo->prepare(
                "SELECT `data` FROM `{$table}` WHERE `id` = :id LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
        } catch (\PDOException $e) {
            if ($this->isTableNotFound($e)) {
                throw new \RuntimeException("Record not found: {$collection}/{$id}");
            }
            throw $e;
        }

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new \RuntimeException(
                "Record not found: {$collection}/{$id}"
            );
        }

        // Only decrypt sensitive data. Plain JSON is stored as-is.
        if ($this->isSensitive($collection, $id)) {
            return $this->enc->decrypt($row['data']);
        }

        $decoded = json_decode($row['data'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Cannot decode JSON: {$collection}/{$id}");
        }
        return $decoded;
    }

    /**
     * Encrypt and write (INSERT or UPDATE) a record in a collection.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert.
     * Also updates cleartext index columns for efficient filtering.
     *
     * @param string $collection Collection name.
     * @param string $id         Record identifier.
     * @param array  $data       Data to encrypt and store.
     */
    public function write(string $collection, string|array $id = '', array $data = []): void
    {
        // v1.0 backwards compatibility.
        if (is_array($id)) {
            $this->writeTo($this->dataDir, $collection, $id);
            return;
        }
        if ($id === '' && str_ends_with($collection, '.json.enc')) {
            $this->writeTo($this->dataDir, $collection, $data);
            return;
        }

        $table   = $this->tableName($collection);
        $pdo     = $this->getPdo();
        $now     = date('Y-m-d H:i:s');

        // Only encrypt sensitive data. Plain JSON is stored as-is for recoverability.
        if ($this->isSensitive($collection, $id)) {
            $encrypted = $this->enc->encrypt($data);
        } else {
            $encrypted = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Extract index values from the data for cleartext index columns.
        $indexes = $this->extractIndexValues($collection, $data);

        // Build the upsert query with parameterized values.
        // Auto-create table if it doesn't exist (supports dynamic collections like terms-*).
        try {
            $this->executeUpsert($table, $id, $encrypted, $indexes, $now);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), '1146') || str_contains($e->getMessage(), 'doesn\'t exist')) {
                $this->createTables([$collection]);
                $this->executeUpsert($table, $id, $encrypted, $indexes, $now);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Execute an upsert (INSERT ... ON DUPLICATE KEY UPDATE) query.
     */
    private function executeUpsert(string $table, string $id, string $encrypted, array $indexes, string $now): void
    {
        $pdo  = $this->getPdo();
        $stmt = $pdo->prepare(
            "INSERT INTO `{$table}` (`id`, `data`, `idx_status`, `idx_lang`, `idx_type`, `idx_slug`, `created_at`, `updated_at`)
             VALUES (:id, :data, :idx_status, :idx_lang, :idx_type, :idx_slug, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
               `data` = VALUES(`data`),
               `idx_status` = VALUES(`idx_status`),
               `idx_lang` = VALUES(`idx_lang`),
               `idx_type` = VALUES(`idx_type`),
               `idx_slug` = VALUES(`idx_slug`),
               `updated_at` = VALUES(`updated_at`)"
        );

        $stmt->execute([
            ':id'         => $id,
            ':data'       => $encrypted,
            ':idx_status' => $indexes['idx_status'],
            ':idx_lang'   => $indexes['idx_lang'],
            ':idx_type'   => $indexes['idx_type'],
            ':idx_slug'   => $indexes['idx_slug'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    /**
     * Delete a record from a collection.
     *
     * @param  string $collection Collection name.
     * @param  string $id         Record identifier.
     * @return bool   True if a row was deleted, false if not found.
     */
    public function delete(string $collection, string $id = ''): bool
    {
        $table = $this->tableName($collection);
        $pdo   = $this->getPdo();

        try {
            $stmt = $pdo->prepare(
                "DELETE FROM `{$table}` WHERE `id` = :id"
            );
            $stmt->execute([':id' => $id]);
        } catch (\PDOException $e) {
            if ($this->isTableNotFound($e)) {
                return false;
            }
            throw $e;
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if a record exists in a collection.
     *
     * Uses COUNT(1) which is faster than fetching the full row.
     *
     * @param  string $collection Collection name.
     * @param  string $id         Record identifier.
     * @return bool
     */
    public function exists(string $collection, string $id = ''): bool
    {
        $table = $this->tableName($collection);
        $pdo   = $this->getPdo();

        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(1) FROM `{$table}` WHERE `id` = :id"
            );
            $stmt->execute([':id' => $id]);
        } catch (\PDOException $e) {
            if ($this->isTableNotFound($e)) {
                return false;
            }
            throw $e;
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    // ─── Query Operations ────────────────────────────────────────

    /**
     * List all records in a collection with optional filtering.
     *
     * Filters are applied against the cleartext index columns when possible,
     * avoiding the need to decrypt every row. If a filter field doesn't
     * have a corresponding index column, all rows are decrypted and filtered
     * in memory (slower, but correct).
     *
     * @param  string $collection Collection name.
     * @param  array  $filters    Key-value pairs (e.g. ['status' => 'published']).
     * @param  int    $limit      Maximum results (0 = unlimited).
     * @param  int    $offset     Records to skip.
     * @return array  Array of decrypted records.
     */
    public function list(string $collection, array $filters = [], int $limit = 0, int $offset = 0): array
    {
        $table = $this->tableName($collection);
        $pdo   = $this->getPdo();

        // Build WHERE clause from index-mappable filters.
        $where      = [];
        $params     = [];
        $memFilters = []; // Filters that can't use indexes → applied in memory.
        $indexMap   = self::INDEX_FIELDS[$collection] ?? [];

        foreach ($filters as $key => $value) {
            if (isset($indexMap[$key])) {
                // This filter maps to a cleartext index column.
                $col             = $indexMap[$key];
                $paramName       = ':f_' . str_replace('-', '_', $col);
                $where[]         = "`{$col}` = {$paramName}";
                $params[$paramName] = $value;
            } else {
                // No index column available — filter in memory after decryption.
                $memFilters[$key] = $value;
            }
        }

        // Build SQL query.
        $sql = "SELECT `data` FROM `{$table}`";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY `updated_at` DESC';

        // Apply LIMIT/OFFSET only if no memory filters (otherwise we need all rows).
        if (empty($memFilters)) {
            if ($limit > 0) {
                $sql .= ' LIMIT ' . (int) $limit;
                if ($offset > 0) {
                    $sql .= ' OFFSET ' . (int) $offset;
                }
            } elseif ($offset > 0) {
                // MySQL requires LIMIT with OFFSET.
                $sql .= ' LIMIT 18446744073709551615 OFFSET ' . (int) $offset;
            }
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            if ($this->isTableNotFound($e)) {
                return [];
            }
            throw $e;
        }

        $records = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            try {
                if ($this->isSensitive($collection, $row['id'] ?? '')) {
                    $record = $this->enc->decrypt($row['data']);
                } else {
                    $record = json_decode($row['data'], true);
                    if (!is_array($record)) {
                        continue;
                    }
                }

                // Apply memory filters for non-indexed fields.
                if (!empty($memFilters)) {
                    $match = true;
                    foreach ($memFilters as $key => $value) {
                        if (($record[$key] ?? null) !== $value) {
                            $match = false;
                            break;
                        }
                    }
                    if (!$match) {
                        continue;
                    }
                }

                $records[] = $record;
            } catch (\RuntimeException $e) {
                // Skip corrupted records.
                error_log("Klytos DatabaseStorage: corrupted record in {$table}: " . $e->getMessage());
                continue;
            }
        }

        // Apply pagination for memory-filtered results.
        if (!empty($memFilters)) {
            if ($offset > 0) {
                $records = array_slice($records, $offset);
            }
            if ($limit > 0) {
                $records = array_slice($records, 0, $limit);
            }
        }

        return $records;
    }

    /**
     * Count records in a collection with optional filters.
     *
     * Uses SQL COUNT when possible (indexed filters), otherwise
     * falls back to counting decrypted results.
     *
     * @param  string $collection Collection name.
     * @param  array  $filters    Key-value pairs.
     * @return int
     */
    public function count(string $collection, array $filters = []): int
    {
        $table    = $this->tableName($collection);
        $pdo      = $this->getPdo();
        $indexMap = self::INDEX_FIELDS[$collection] ?? [];

        // Check if ALL filters can use index columns.
        $allIndexed = true;
        foreach (array_keys($filters) as $key) {
            if (!isset($indexMap[$key])) {
                $allIndexed = false;
                break;
            }
        }

        if ($allIndexed) {
            // Fast path: COUNT with indexed WHERE clause.
            $where  = [];
            $params = [];
            foreach ($filters as $key => $value) {
                $col             = $indexMap[$key];
                $paramName       = ':f_' . str_replace('-', '_', $col);
                $where[]         = "`{$col}` = {$paramName}";
                $params[$paramName] = $value;
            }

            $sql = "SELECT COUNT(1) FROM `{$table}`";
            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } catch (\PDOException $e) {
                if ($this->isTableNotFound($e)) {
                    return 0;
                }
                throw $e;
            }

            return (int) $stmt->fetchColumn();
        }

        // Slow path: decrypt and count in memory.
        return count($this->list($collection, $filters));
    }

    /**
     * Search records by matching a query string against fields.
     *
     * Decrypts all records and performs case-insensitive substring matching.
     * For large datasets, consider adding FULLTEXT indexes on specific columns.
     *
     * @param  string $collection Collection name.
     * @param  string $query      Search text.
     * @param  array  $fields     Fields to search (empty = all string fields).
     * @param  int    $limit      Maximum results.
     * @return array  Matching records.
     */
    public function search(string $collection, string $query, array $fields = [], int $limit = 50): array
    {
        if (empty($query)) {
            return [];
        }

        $table   = $this->tableName($collection);
        $pdo     = $this->getPdo();
        $queryLc = mb_strtolower($query);

        $stmt = $pdo->prepare("SELECT `id`, `data` FROM `{$table}`");
        $stmt->execute();

        $results = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (count($results) >= $limit) {
                break;
            }

            try {
                if ($this->isSensitive($collection, $row['id'] ?? '')) {
                    $record = $this->enc->decrypt($row['data']);
                } else {
                    $record = json_decode($row['data'], true);
                    if (!is_array($record)) {
                        continue;
                    }
                }

                $searchFields = !empty($fields)
                    ? $fields
                    : array_keys(array_filter($record, 'is_string'));

                foreach ($searchFields as $field) {
                    if (isset($record[$field]) && is_string($record[$field])) {
                        if (mb_strpos(mb_strtolower($record[$field]), $queryLc) !== false) {
                            $results[] = $record;
                            break;
                        }
                    }
                }
            } catch (\RuntimeException $e) {
                error_log("Klytos DatabaseStorage: search error: " . $e->getMessage());
                continue;
            }
        }

        return $results;
    }

    // ─── Transaction Support ─────────────────────────────────────

    /**
     * Execute a callback within a database transaction.
     *
     * True ACID transaction: if the callback throws, all changes are rolled back.
     * This is the primary advantage of DatabaseStorage over FileStorage.
     *
     * @param  callable $callback Receives this DatabaseStorage instance.
     * @return mixed    The callback's return value.
     * @throws \Throwable Re-throws exceptions after rollback.
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->getPdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($this);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ─── Legacy / Config Helpers ─────────────────────────────────

    /**
     * Read a file from an arbitrary base directory.
     *
     * Config files (config.json.enc, license.json.enc) are always stored
     * as flat files, even when using DatabaseStorage for data.
     * This keeps the encryption key and config separate from the database.
     *
     * @param  string $basePath Absolute directory path.
     * @param  string $file     Filename.
     * @return array  Decrypted data.
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
     * Write a file to an arbitrary base directory.
     *
     * @param string $basePath Absolute directory path.
     * @param string $file     Filename.
     * @param array  $data     Data to encrypt and store.
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

    // ─── Database Management ─────────────────────────────────────

    /**
     * Get the PDO connection (lazy initialization).
     *
     * Opens the connection on first use and configures security settings:
     * - ERRMODE_EXCEPTION: errors throw PDOException (fail-fast).
     * - EMULATE_PREPARES = false: uses real database-level prepared statements.
     * - STRINGIFY_FETCHES = false: preserves native types.
     * - Charset utf8mb4 for full Unicode support.
     *
     * @return \PDO
     * @throws \RuntimeException If the connection fails.
     */
    public function getPdo(): \PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $host    = $this->dbConfig['host'] ?? 'localhost';
        $port    = (int) ($this->dbConfig['port'] ?? 3306);
        $dbName  = $this->dbConfig['name'] ?? '';
        $user    = $this->dbConfig['user'] ?? '';
        $pass    = $this->dbConfig['pass'] ?? '';
        $charset = $this->dbConfig['charset'] ?? 'utf8mb4';

        if (empty($dbName) || empty($user)) {
            throw new \RuntimeException(
                'Database configuration incomplete: name and user are required.'
            );
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

        try {
            $this->pdo = new \PDO($dsn, $user, $pass, [
                // Throw exceptions on errors — never silently fail.
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                // Use real prepared statements (not emulated).
                // This prevents SQL injection even if PDO::quote() has bugs.
                \PDO::ATTR_EMULATE_PREPARES   => false,
                // Preserve native data types (integers stay integers).
                \PDO::ATTR_STRINGIFY_FETCHES   => false,
                // Use associative arrays by default.
                \PDO::ATTR_DEFAULT_FETCH_MODE  => \PDO::FETCH_ASSOC,
                // Timeout: 5 seconds to prevent hanging.
                \PDO::ATTR_TIMEOUT             => 5,
            ]);

            // Set strict SQL mode for data integrity.
            $this->pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");

        } catch (\PDOException $e) {
            // Don't expose connection details in the error message.
            throw new \RuntimeException(
                'Database connection failed. Check your credentials and ensure the server is running.'
            );
        }

        return $this->pdo;
    }

    /**
     * Create all collection tables in the database.
     *
     * Called during installation when the user selects MySQL storage.
     * Each table has the same structure: id, encrypted data, index columns, timestamps.
     *
     * @param  array $collections List of collection names to create tables for.
     *                            Default: all known collections.
     * @throws \RuntimeException If table creation fails.
     */
    public function createTables(array $collections = []): void
    {
        if (empty($collections)) {
            $collections = [
                'config',
                'pages',
                'users',
                'tasks',
                'blocks',
                'page-templates',
                'page-versions',
                'webhooks',
                'webhook-logs',
                'analytics',
                'analytics-salt',
                'audit-log',
                'plugins',
                'cron',
                'post-types',
            ];
        }

        $pdo = $this->getPdo();

        foreach ($collections as $collection) {
            $table = $this->tableName($collection);

            // Table name is safe because tableName() sanitizes it.
            $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
                `id`         VARCHAR(255)  NOT NULL,
                `data`       LONGTEXT      NOT NULL COMMENT 'AES-256-GCM encrypted JSON',
                `idx_status` VARCHAR(50)   DEFAULT NULL COMMENT 'Cleartext index: status/role',
                `idx_lang`   VARCHAR(10)   DEFAULT NULL COMMENT 'Cleartext index: language/scope',
                `idx_type`   VARCHAR(100)  DEFAULT NULL COMMENT 'Cleartext index: type/category',
                `idx_slug`   VARCHAR(255)  DEFAULT NULL COMMENT 'Cleartext index: slug/key',
                `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_status`    (`idx_status`),
                INDEX `idx_lang`      (`idx_lang`),
                INDEX `idx_type`      (`idx_type`),
                INDEX `idx_slug`      (`idx_slug`),
                INDEX `idx_updated`   (`updated_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Klytos {$collection} — encrypted data store'";

            $pdo->exec($sql);
        }
    }

    /**
     * Test the database connection.
     *
     * Used by the installer to verify credentials before proceeding.
     *
     * @return array ['success' => bool, 'error' => string|null, 'version' => string|null]
     */
    public function testConnection(): array
    {
        try {
            $pdo     = $this->getPdo();
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();

            return [
                'success' => true,
                'error'   => null,
                'version' => $version,
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'version' => null,
            ];
        }
    }

    // ─── Internal Helpers ────────────────────────────────────────

    /**
     * Build the full table name from a collection name.
     *
     * Sanitizes the collection name to prevent SQL injection via table names.
     * Converts hyphens to underscores (MySQL table naming convention).
     *
     * @param  string $collection Collection name (e.g. 'pages', 'page-versions').
     * @return string Full table name (e.g. 'kly_pages', 'kly_page_versions').
     * @throws \InvalidArgumentException If the collection name is invalid.
     */
    private function tableName(string $collection): string
    {
        // Remove anything that isn't alphanumeric, hyphen, or underscore.
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $collection);

        if (empty($safe)) {
            throw new \InvalidArgumentException(
                "Invalid collection name: '{$collection}'"
            );
        }

        // Convert hyphens to underscores for SQL compatibility.
        $safe = str_replace('-', '_', strtolower($safe));

        return $this->prefix . $safe;
    }

    /**
     * Check if a PDOException is a "table not found" error (MySQL error 1146).
     *
     * Used to gracefully handle dynamic collections that haven't been
     * created yet (e.g. taxonomy term collections like terms-coches-colores).
     *
     * @param  \PDOException $e The exception to check.
     * @return bool True if the error is "table doesn't exist".
     */
    private function isTableNotFound(\PDOException $e): bool
    {
        return str_contains($e->getMessage(), '1146')
            || str_contains($e->getMessage(), 'doesn\'t exist');
    }

    /**
     * Extract cleartext index values from record data.
     *
     * Maps record fields to index columns based on the collection's
     * INDEX_FIELDS configuration. Values are truncated to fit column limits.
     *
     * @param  string $collection Collection name.
     * @param  array  $data       Record data.
     * @return array  Index column values: ['idx_status', 'idx_lang', 'idx_type', 'idx_slug'].
     */
    private function extractIndexValues(string $collection, array $data): array
    {
        $indexes = [
            'idx_status' => null,
            'idx_lang'   => null,
            'idx_type'   => null,
            'idx_slug'   => null,
        ];

        $map = self::INDEX_FIELDS[$collection] ?? [];

        foreach ($map as $dataField => $indexCol) {
            if (isset($data[$dataField]) && is_scalar($data[$dataField])) {
                $value = (string) $data[$dataField];

                // Truncate to fit column size limits.
                $maxLengths = [
                    'idx_status' => 50,
                    'idx_lang'   => 10,
                    'idx_type'   => 100,
                    'idx_slug'   => 255,
                ];

                $maxLen = $maxLengths[$indexCol] ?? 255;
                $indexes[$indexCol] = mb_substr($value, 0, $maxLen);
            }
        }

        return $indexes;
    }
}
