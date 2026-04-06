<?php

/**
 * Klytos — Storage Interface
 * Abstraction layer for data persistence (flat-file or database).
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

interface StorageInterface
{
    /**
     * Read a record from a collection.
     *
     * @param  string $collection Collection name (e.g. 'pages', 'users', 'tasks').
     * @param  string $id         Record identifier (e.g. slug, user_id).
     * @return array  Decrypted data.
     * @throws \RuntimeException If the record does not exist.
     */
    /**
     * v2.0 API: read('collection', 'id') — reads from collection.
     * v1.0 compat: read('file.json.enc') — reads direct file from data/.
     */
    public function read(string $collection, string $id = ''): array;

    /**
     * v2.0 API: write('collection', 'id', $data) — writes to collection.
     * v1.0 compat: write('file.json.enc', $data) — writes direct file to data/.
     */
    public function write(string $collection, string|array $id = '', array $data = []): void;

    /**
     * v2.0 API: delete('collection', 'id') — deletes from collection.
     * v1.0 compat: delete('file.json.enc') — deletes direct file from data/.
     */
    public function delete(string $collection, string $id = ''): bool;

    /**
     * v2.0 API: exists('collection', 'id') — checks collection.
     * v1.0 compat: exists('file.json.enc') — checks direct file in data/.
     */
    public function exists(string $collection, string $id = ''): bool;

    /**
     * List all records in a collection.
     *
     * @param  string $collection Collection name.
     * @param  array  $filters    Optional key-value filters applied to decrypted data.
     * @param  int    $limit      Maximum records to return (0 = no limit).
     * @param  int    $offset     Number of records to skip.
     * @return array  Array of decrypted records.
     */
    public function list(string $collection, array $filters = [], int $limit = 0, int $offset = 0): array;

    /**
     * Count records in a collection.
     *
     * @param  string $collection Collection name.
     * @param  array  $filters    Optional key-value filters.
     * @return int
     */
    public function count(string $collection, array $filters = []): int;

    /**
     * Search records by matching a value against one or more fields.
     *
     * @param  string $collection Collection name.
     * @param  string $query      Search query.
     * @param  array  $fields     Fields to search in (e.g. ['title', 'content_html']).
     * @param  int    $limit      Maximum results.
     * @return array  Matching records.
     */
    public function search(string $collection, string $query, array $fields = [], int $limit = 50): array;

    /**
     * Execute a callback within a transaction (or pseudo-transaction for flat-file).
     *
     * @param  callable $callback Receives the storage instance as argument.
     * @return mixed    The callback's return value.
     * @throws \Throwable Re-throws any exception from the callback after rollback.
     */
    public function transaction(callable $callback): mixed;

    // ─── Encryption Level Management ────────────────────────────────

    /**
     * Determine if a collection+id pair requires encryption
     * based on the current encryption level.
     *
     * @param  string $collection Collection name (e.g. 'pages', 'users').
     * @param  string $id         Record identifier.
     * @return bool   True if the data should be encrypted.
     */
    public function shouldEncrypt( string $collection, string $id = '' ): bool;

    /**
     * Get the current encryption level.
     *
     * @return string One of: 'basic', 'medium', 'professional'.
     */
    public function getEncryptionLevel(): string;

    /**
     * Change the encryption level, encrypting or decrypting
     * affected collections as needed.
     *
     * @param string $newLevel The target level ('basic', 'medium', 'professional').
     * @throws \InvalidArgumentException If the level is invalid.
     */
    public function changeEncryptionLevel( string $newLevel ): void;

    // ─── Legacy / Config Helpers ──────────────────────────────────

    /**
     * Read a single file from an arbitrary base path (for config files).
     *
     * @param  string $basePath Absolute directory path.
     * @param  string $file     Filename (e.g. 'config.json.enc').
     * @return array  Decrypted data.
     */
    public function readFrom(string $basePath, string $file): array;

    /**
     * Write a single file to an arbitrary base path (for config files).
     *
     * @param string $basePath Absolute directory path.
     * @param string $file     Filename.
     * @param array  $data     Data to encrypt and store.
     */
    public function writeTo(string $basePath, string $file, array $data): void;

    /**
     * Get the encryption engine instance.
     *
     * @return Encryption
     */
    public function getEncryption(): Encryption;

    /**
     * Get the base data directory path.
     *
     * @return string
     */
    public function getDataDir(): string;
}
