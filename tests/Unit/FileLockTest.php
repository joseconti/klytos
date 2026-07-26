<?php

/**
 * Klytos CMS — FileLock, the exclusive read-modify-write primitive (Sprint 6, slice 1).
 *
 * Closes audit NEW-40 (the login lockout) and NEW-20 (MCP\RateLimiter), which
 * are the same defect in two subsystems: the counter was read, decided on, and
 * written back under SEPARATE locks, so concurrent callers read the same
 * pre-increment value and increments were lost. Each lost increment is one
 * request that was never counted against its limit.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\FileLock;
use Klytos\Tests\UnitTestCase;

final class FileLockTest extends UnitTestCase
{
    /** @var string Path to this test's counter file. */
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = $this->tempDir . '/data/file-lock-test.json';
    }

    /**
     * The happy path: the callback sees the stored map and its return persists.
     */
    public function testATransactionReadsTheStoredMapAndPersistsWhatItReturns(): void
    {
        file_put_contents( $this->path, json_encode( [ 'count' => 7 ] ) );

        $seen = null;

        $ran = FileLock::transaction(
            $this->path,
            static function ( array $data ) use ( &$seen ): array {
                $seen          = $data;
                $data['count'] = (int) $data['count'] + 1;

                return $data;
            }
        );

        self::assertTrue( $ran, 'The transaction reported that it did not run.' );
        self::assertSame( [ 'count' => 7 ], $seen, 'The callback did not receive the stored map.' );
        self::assertSame(
            [ 'count' => 8 ],
            json_decode( (string) file_get_contents( $this->path ), true ),
            'The returned map was not persisted.'
        );
    }

    /**
     * Returning null writes nothing — a read-only decision.
     */
    public function testReturningNullLeavesTheFileUntouched(): void
    {
        file_put_contents( $this->path, json_encode( [ 'count' => 3 ] ) );
        $before = (string) file_get_contents( $this->path );

        $ran = FileLock::transaction( $this->path, static fn( array $d ): ?array => null );

        self::assertTrue( $ran );
        self::assertSame(
            $before,
            (string) file_get_contents( $this->path ),
            'A null return still rewrote the file.'
        );
    }

    /**
     * A missing file is an empty map, and the transaction creates it.
     */
    public function testAMissingFileIsAnEmptyMapAndIsCreated(): void
    {
        self::assertFileDoesNotExist( $this->path );

        $seen = 'not-called';

        $ran = FileLock::transaction(
            $this->path,
            static function ( array $data ) use ( &$seen ): array {
                $seen = $data;

                return [ 'created' => true ];
            }
        );

        self::assertTrue( $ran );
        self::assertSame( [], $seen, 'A missing file should present as an empty map.' );
        self::assertFileExists( $this->path );
    }

    /**
     * An undecodable file starts a fresh map rather than denying everyone.
     *
     * D-059 decided this direction explicitly and it is NOT the same as the
     * lock-timeout direction below. Refusing every request because a counter
     * file is corrupt would turn one damaged file into a total login outage,
     * which is a worse failure than the race being fixed.
     */
    public function testAnUndecodableFileIsTreatedAsEmptyRatherThanRefusing(): void
    {
        file_put_contents( $this->path, 'this is not json {{{' );

        $seen = 'not-called';

        $ran = FileLock::transaction(
            $this->path,
            static function ( array $data ) use ( &$seen ): array {
                $seen = $data;

                return [ 'recovered' => true ];
            }
        );

        self::assertTrue( $ran, 'A corrupt counter file must not refuse the transaction.' );
        self::assertSame( [], $seen, 'Corrupt contents should present as an empty map.' );
        self::assertSame(
            [ 'recovered' => true ],
            json_decode( (string) file_get_contents( $this->path ), true ),
            'The transaction did not recover the file.'
        );
    }

    /**
     * An empty map is written as {} rather than deleted.
     *
     * Deleting would race the lock held on the same path; writing an object
     * keeps the file decodable and the operational story unchanged (removing
     * the file by hand still clears every lockout).
     */
    public function testAnEmptyMapIsWrittenAsAnObject(): void
    {
        FileLock::transaction( $this->path, static fn( array $d ): array => [] );

        self::assertSame(
            '{}',
            trim( (string) file_get_contents( $this->path ) ),
            'An empty map should serialise as {} so the file still decodes to an object.'
        );
    }

    /**
     * A lock held elsewhere refuses, and the callback never runs.
     *
     * This is the fail-closed half of D-059: the caller must be able to tell
     * "I could not count this" from "I counted it", because treating the first
     * as the second is exactly the amplification the primitive closes.
     */
    public function testALockHeldElsewhereRefusesAndTheCallbackNeverRuns(): void
    {
        // Hold the lock from this process, on a separate handle.
        $holder = fopen( $this->path, 'c+' );
        self::assertNotFalse( $holder );
        self::assertTrue( flock( $holder, LOCK_EX ), 'Could not take the lock to hold it.' );

        $called = false;

        $started = microtime( true );
        $ran     = FileLock::transaction(
            $this->path,
            static function ( array $data ) use ( &$called ): array {
                $called = true;

                return $data;
            },
            150 // ms — short, so the test does not pay the 2 s default.
        );
        $elapsed = ( microtime( true ) - $started ) * 1000;

        flock( $holder, LOCK_UN );
        fclose( $holder );

        self::assertFalse( $ran, 'A transaction that could not lock reported success.' );
        self::assertFalse( $called, 'The callback ran without the lock being held.' );
        self::assertGreaterThanOrEqual(
            140,
            $elapsed,
            'It gave up before its deadline, so the deadline is not being honoured.'
        );
    }

    /**
     * THE POINT OF THE SLICE: N concurrent increments all survive.
     *
     * Proven in both directions in one test, because a concurrency assertion
     * that has never been observed failing is indistinguishable from one that
     * cannot fail (L-010, L-016). The 'racy' worker reproduces the exact
     * pre-D-059 pattern; if IT does not lose updates then the harness cannot
     * see the defect and the locked result proves nothing.
     *
     * Measured at the Sprint 6 kickoff against the real MCP\RateLimiter: 20
     * simultaneous check() calls recorded 2-4 of themselves.
     */
    public function testConcurrentIncrementsAreNotLost(): void
    {
        $workers = 12;

        $racy = $this->runWorkers( $workers, 'racy' );
        self::assertLessThan(
            $workers,
            $racy,
            'The racy worker lost NO updates, so this test cannot observe the defect it exists for '
            . "(recorded {$racy} of {$workers}). Do not trust the locked result below until this fails."
        );

        $locked = $this->runWorkers( $workers, 'lock' );
        self::assertSame(
            $workers,
            $locked,
            "FileLock lost an update: {$locked} of {$workers} increments survived."
        );
    }

    /**
     * Spawn N workers that all increment the same counter at the same instant.
     *
     * @param  int    $workers
     * @param  string $mode 'lock' or 'racy'.
     * @return int    The counter's value once every worker has exited.
     */
    private function runWorkers( int $workers, string $mode ): int
    {
        $path = $this->tempDir . '/data/concurrency-' . $mode . '.json';
        @unlink( $path );

        $fixture = dirname( __DIR__ ) . '/fixtures/file-lock-worker.php';
        $startAt = microtime( true ) + 0.75;

        $descriptors = [
            0 => [ 'file', '/dev/null', 'r' ],
            1 => [ 'file', '/dev/null', 'w' ],
            2 => [ 'file', '/dev/null', 'w' ],
        ];

        $procs = [];
        for ( $i = 0; $i < $workers; $i++ ) {
            $proc = proc_open(
                [ PHP_BINARY, $fixture, $path, (string) $startAt, $mode ],
                $descriptors,
                $pipes
            );

            if ( is_resource( $proc ) ) {
                $procs[] = $proc;
            }
        }

        self::assertCount( $workers, $procs, 'Not every worker process started.' );

        foreach ( $procs as $proc ) {
            proc_close( $proc );
        }

        $raw = @file_get_contents( $path );
        self::assertNotFalse( $raw, 'No worker wrote the counter file at all.' );

        $data = json_decode( (string) $raw, true );

        return (int) ( $data['count'] ?? 0 );
    }
}
