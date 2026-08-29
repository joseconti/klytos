<?php

/**
 * Klytos CMS — the analytics retention actually deletes what it promises to.
 *
 * THE BUG THIS REPRODUCES, and it is a privacy failure rather than a bug in a
 * screen. `AnalyticsManager::prune()` deleted with `$entry['id'] ?? ''`, and
 * `recordPageView()` never writes an `id` field, so the call reached
 * `delete( 'analytics', '' )` and threw:
 *
 *     InvalidArgumentException: Invalid record ID: ''
 *
 * `CronManager` registers `klytos.analytics_prune` on an 86400s interval calling
 * `prune( 90 )` (`cron-manager.php:164-167`, `:333`, `:351-354`), so on any
 * install holding data past the retention window that daily job fatalled — and
 * the 90-day retention the engine's own header promises has never once run. Data
 * a person was told would be deleted was kept indefinitely.
 *
 * The root was one layer below: `StorageInterface::list()` returned `$records[]`
 * with the storage ids DISCARDED, so nothing that read it could delete a record
 * it had just read. The fix is `listWithIds()` on the contract, and these tests
 * pin the retention that now depends on it.
 *
 * Written BEFORE the fix and seen failing — the unconditional rule at every
 * value of `Test-first policy:`: a regression test written after the fix never
 * reproduced anything and so proves nothing about the bug's return.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\AnalyticsManager;
use Klytos\Tests\UnitTestCase;

/**
 * A retention control that is declared and does not run is a privacy failure.
 */
final class AnalyticsRetentionTest extends UnitTestCase
{
    private const COLLECTION = 'analytics';

    /**
     * Write one pageview dated `$daysAgo` days back, through the real writer's
     * own record shape.
     */
    private function seed( string $id, int $daysAgo ): void
    {
        $this->storage->write( self::COLLECTION, $id, [
            'page_path'       => '/',
            'referrer_domain' => '',
            'device_category' => 'desktop',
            'visitor_hash'    => hash( 'sha256', $id ),
            'date'            => gmdate( 'Y-m-d', time() - $daysAgo * 86400 ),
            'timestamp'       => gmdate( 'c', time() - $daysAgo * 86400 ),
        ] );
    }

    /**
     * THE REPRODUCTION. Before the fix this did not fail an assertion — it threw.
     */
    public function testPruneDeletesExpiredEntriesInsteadOfThrowing(): void
    {
        $this->seed( 'old-a', 200 );
        $this->seed( 'old-b', 100 );
        $this->seed( 'recent', 5 );

        $manager = new AnalyticsManager( $this->storage );

        $pruned = $manager->prune( 90 );

        $this->assertSame( 2, $pruned, 'both entries past the window are pruned' );
        $this->assertCount(
            1,
            $this->storage->list( self::COLLECTION ),
            'exactly the recent entry survives'
        );
    }

    /**
     * The count it returns is the count it DELETED.
     *
     * The old code incremented its counter next to a delete that never removed
     * anything, so even in the branch where it did not throw it reported work it
     * had not done — the "declared is not delivered" defect, in a return value.
     */
    public function testTheReturnedCountIsWhatWasActuallyRemoved(): void
    {
        foreach ( range( 1, 5 ) as $i ) {
            $this->seed( 'old-' . $i, 120 );
        }

        $before  = count( $this->storage->list( self::COLLECTION ) );
        $manager = new AnalyticsManager( $this->storage );
        $pruned  = $manager->prune( 90 );
        $after   = count( $this->storage->list( self::COLLECTION ) );

        $this->assertSame( 5, $before );
        $this->assertSame( 0, $after );
        $this->assertSame( $before - $after, $pruned, 'the report matches the deletion' );
    }

    /** Nothing inside the window is touched, whatever else is in the collection. */
    public function testEntriesInsideTheWindowAreNeverTouched(): void
    {
        $this->seed( 'edge-inside', 89 );
        $this->seed( 'edge-outside', 91 );

        $manager = new AnalyticsManager( $this->storage );
        $manager->prune( 90 );

        $survivors = $this->storage->list( self::COLLECTION );

        $this->assertCount( 1, $survivors );
        $this->assertSame(
            gmdate( 'Y-m-d', time() - 89 * 86400 ),
            $survivors[0]['date'],
            'the entry inside the window is the one that survived'
        );
    }

    /** An empty collection is a no-op that reports zero, not an error. */
    public function testPruningAnEmptyCollectionIsANoOp(): void
    {
        $manager = new AnalyticsManager( $this->storage );

        $this->assertSame( 0, $manager->prune( 90 ) );
    }

    /**
     * A record with no usable date is KEPT, not deleted.
     *
     * Retention decides what to destroy, so its unknown case must fail towards
     * keeping data rather than towards deleting it: a malformed row is a bug to
     * investigate, and deleting it destroys the evidence.
     */
    public function testARecordWithNoDateIsKeptRatherThanDeleted(): void
    {
        $this->storage->write( self::COLLECTION, 'undated', [
            'page_path'    => '/',
            'visitor_hash' => 'x',
        ] );

        $manager = new AnalyticsManager( $this->storage );

        $this->assertSame( 0, $manager->prune( 90 ) );
        $this->assertCount( 1, $this->storage->list( self::COLLECTION ) );
    }
}
