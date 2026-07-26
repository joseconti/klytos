<?php

/**
 * Klytos CMS — concurrency worker for FileLockTest.
 *
 * ONE process, ONE increment of a counter held in a JSON file, through the
 * primitive under test. The driver spawns N of these and asserts that all N
 * increments survive. Against the pre-D-059 read-then-write pattern most of
 * them did not: 20 simultaneous MCP\RateLimiter::check() calls recorded 2-4 of
 * themselves when this was measured at the Sprint 6 kickoff.
 *
 * argv[1] = path to the JSON file
 * argv[2] = the wall-clock instant every worker starts at, as a float
 * argv[3] = 'lock' to use FileLock::transaction(), 'racy' to reproduce the
 *           old read-decide-write. The racy mode exists so the test can prove
 *           the harness can SEE a lost update — a concurrency assertion that
 *           has never been observed failing is indistinguishable from one that
 *           cannot fail (L-010, L-016).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../installer/core/file-lock.php';

use Klytos\Core\FileLock;

$path    = (string) ( $argv[1] ?? '' );
$startAt = (float) ( $argv[2] ?? 0 );
$mode    = (string) ( $argv[3] ?? 'lock' );

// Barrier: every worker begins at the same instant. Without it the processes
// stagger themselves by spawn time, the windows never overlap, and even the
// racy mode would report no lost updates — a false PASS.
while ( microtime( true ) < $startAt ) {
    usleep( 200 );
}

if ( $mode === 'racy' ) {
    // The shape both audit NEW-20 and NEW-40 describe: read, decide, write,
    // with the lock covering only the write.
    $raw  = @file_get_contents( $path );
    $data = $raw === false ? [] : ( json_decode( (string) $raw, true ) ?: [] );

    $data['count'] = (int) ( $data['count'] ?? 0 ) + 1;

    usleep( 1000 ); // Widen the window so the race is reliably observable.

    file_put_contents( $path, json_encode( $data ), LOCK_EX );

    exit( 0 );
}

FileLock::transaction(
    $path,
    static function ( array $data ): array {
        $data['count'] = (int) ( $data['count'] ?? 0 ) + 1;

        usleep( 1000 ); // The same window the racy mode loses updates in.

        return $data;
    }
);
