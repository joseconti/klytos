<?php

/**
 * Klytos CMS — `listWithIds()` is the contract that makes a listed record actionable.
 *
 * `list()` promised records and not identity, so a caller could read a record and
 * have no way back to the id it needed in order to write or delete it. Six
 * managers compensated by storing an `id` field inside the record; six did not,
 * and every one of those that also looped list-then-delete was broken — a crash
 * on the file backend and a silent no-op on the database one (D-115).
 *
 * These tests pin the new contract, and the last one pins the property that keeps
 * the defect class closed: `list()` is DERIVED from `listWithIds()`, so the two
 * cannot answer differently about the same collection.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Tests\UnitTestCase;

/**
 * The identity a caller needs is the storage key, and it always exists.
 */
final class StorageListWithIdsTest extends UnitTestCase
{
    private const COLLECTION = 'widgets';

    private function seed(): void
    {
        $this->storage->write( self::COLLECTION, 'alpha', ['name' => 'Alpha', 'kind' => 'a'] );
        $this->storage->write( self::COLLECTION, 'beta',  ['name' => 'Beta',  'kind' => 'b'] );
        $this->storage->write( self::COLLECTION, 'gamma', ['name' => 'Gamma', 'kind' => 'a'] );
    }

    /** The keys are the storage ids, and no `id` field had to be stored. */
    public function testItKeysRecordsByTheirStorageId(): void
    {
        $this->seed();

        $rows = $this->storage->listWithIds( self::COLLECTION );

        $this->assertSame( ['alpha', 'beta', 'gamma'], array_keys( $rows ) );
        $this->assertSame( 'Beta', $rows['beta']['name'] );
        $this->assertArrayNotHasKey(
            'id',
            $rows['beta'],
            'the identity is the KEY — nothing was injected into the record'
        );
    }

    /**
     * THE POINT OF THE WHOLE CHANGE: a record you listed, you can delete.
     */
    public function testARecordThatWasListedCanBeDeleted(): void
    {
        $this->seed();

        foreach ( $this->storage->listWithIds( self::COLLECTION ) as $id => $row ) {
            if ( $row['kind'] === 'a' ) {
                $this->assertTrue(
                    $this->storage->delete( self::COLLECTION, (string) $id ),
                    'the id recovered from the listing really addresses the record'
                );
            }
        }

        $survivors = $this->storage->listWithIds( self::COLLECTION );

        $this->assertSame( ['beta'], array_keys( $survivors ) );
    }

    /** Filters apply, and the surviving rows keep their ids. */
    public function testFiltersApplyAndTheKeysSurviveThem(): void
    {
        $this->seed();

        $rows = $this->storage->listWithIds( self::COLLECTION, ['kind' => 'a'] );

        $this->assertSame( ['alpha', 'gamma'], array_keys( $rows ) );
    }

    /**
     * Pagination preserves the keys.
     *
     * `array_slice()` drops string keys unless told not to, which would have made
     * this method silently useless the moment anyone passed a limit.
     */
    public function testPaginationPreservesTheKeys(): void
    {
        $this->seed();

        $this->assertSame(
            ['beta'],
            array_keys( $this->storage->listWithIds( self::COLLECTION, [], 1, 1 ) ),
            'offset 1, limit 1 is the second record, still keyed'
        );
    }

    /** A missing collection is empty, not an error. */
    public function testAMissingCollectionIsEmpty(): void
    {
        $this->assertSame( [], $this->storage->listWithIds( 'nothing-here' ) );
    }

    /**
     * `list()` IS `listWithIds()` without the keys — one traversal, two views.
     *
     * This is the assertion that keeps the defect class closed. The two used to
     * be separate traversals, which is how one could recover an id and the other
     * could not; if they ever diverge again, this fails.
     */
    public function testListIsExactlyListWithIdsWithoutTheKeys(): void
    {
        $this->seed();

        foreach ( [[], ['kind' => 'a']] as $filters ) {
            foreach ( [[0, 0], [2, 0], [1, 1]] as [$limit, $offset] ) {
                $this->assertSame(
                    array_values( $this->storage->listWithIds( self::COLLECTION, $filters, $limit, $offset ) ),
                    $this->storage->list( self::COLLECTION, $filters, $limit, $offset ),
                    sprintf( 'the two views disagree at limit %d offset %d', $limit, $offset )
                );
            }
        }
    }
}
