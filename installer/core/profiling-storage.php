<?php

/**
 * Klytos — Profiling Storage Wrapper
 * Wraps a StorageInterface to measure operation durations for the DevBar.
 *
 * Only used when Developer Mode is active. Delegates all operations to the
 * inner storage and reports timing to DevBar.
 *
 * @package Klytos
 * @since   0.16.0
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

class ProfilingStorage implements StorageInterface
{
    public function __construct(
        private StorageInterface $inner,
        private DevBar $devBar
    ) {}

    public function read( string $collection, string $id = '' ): array
    {
        $start  = microtime( true );
        $result = $this->inner->read( $collection, $id );
        $this->devBar->logStorageOp( 'read', $collection ?: $id, microtime( true ) - $start );
        return $result;
    }

    public function write( string $collection, string|array $id = '', array $data = [] ): void
    {
        $start = microtime( true );
        $this->inner->write( $collection, $id, $data );
        $label = is_string( $id ) ? ( $collection ?: $id ) : $collection;
        $this->devBar->logStorageOp( 'write', $label, microtime( true ) - $start );
    }

    public function delete( string $collection, string $id = '' ): bool
    {
        $start  = microtime( true );
        $result = $this->inner->delete( $collection, $id );
        $this->devBar->logStorageOp( 'delete', $collection ?: $id, microtime( true ) - $start );
        return $result;
    }

    public function exists( string $collection, string $id = '' ): bool
    {
        $start  = microtime( true );
        $result = $this->inner->exists( $collection, $id );
        $this->devBar->logStorageOp( 'exists', $collection ?: $id, microtime( true ) - $start );
        return $result;
    }

    public function list( string $collection, array $filters = [], int $limit = 0, int $offset = 0 ): array
    {
        $start  = microtime( true );
        $result = $this->inner->list( $collection, $filters, $limit, $offset );
        $this->devBar->logStorageOp( 'list', $collection, microtime( true ) - $start );
        return $result;
    }

    public function listWithIds( string $collection, array $filters = [], int $limit = 0, int $offset = 0 ): array
    {
        $start  = microtime( true );
        $result = $this->inner->listWithIds( $collection, $filters, $limit, $offset );
        $this->devBar->logStorageOp( 'listWithIds', $collection, microtime( true ) - $start );
        return $result;
    }

    public function count( string $collection, array $filters = [] ): int
    {
        $start  = microtime( true );
        $result = $this->inner->count( $collection, $filters );
        $this->devBar->logStorageOp( 'count', $collection, microtime( true ) - $start );
        return $result;
    }

    public function search( string $collection, string $query, array $fields = [], int $limit = 50 ): array
    {
        $start  = microtime( true );
        $result = $this->inner->search( $collection, $query, $fields, $limit );
        $this->devBar->logStorageOp( 'search', $collection, microtime( true ) - $start );
        return $result;
    }

    public function transaction( callable $callback ): mixed
    {
        return $this->inner->transaction( $callback );
    }

    public function readFrom( string $basePath, string $file ): array
    {
        $start  = microtime( true );
        $result = $this->inner->readFrom( $basePath, $file );
        $this->devBar->logStorageOp( 'readFrom', $file, microtime( true ) - $start );
        return $result;
    }

    public function writeTo( string $basePath, string $file, array $data ): void
    {
        $start = microtime( true );
        $this->inner->writeTo( $basePath, $file, $data );
        $this->devBar->logStorageOp( 'writeTo', $file, microtime( true ) - $start );
    }

    public function getEncryption(): Encryption
    {
        return $this->inner->getEncryption();
    }

    public function getDataDir(): string
    {
        return $this->inner->getDataDir();
    }

    public function shouldEncrypt( string $collection, string $id = '' ): bool
    {
        return $this->inner->shouldEncrypt( $collection, $id );
    }

    public function getEncryptionLevel(): string
    {
        return $this->inner->getEncryptionLevel();
    }

    public function changeEncryptionLevel( string $newLevel ): void
    {
        $this->inner->changeEncryptionLevel( $newLevel );
    }
}
