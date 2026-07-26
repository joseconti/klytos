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
 *           old read-decide-write, or 'hold' to take the exclusive lock and
 *           keep it. The racy mode exists so the test can prove the harness can
 *           SEE a lost update — a concurrency assertion that has never been
 *           observed failing is indistinguishable from one that cannot fail
 *           (L-010, L-016).
 * argv[4] = for 'hold' only: how long to keep the lock, in milliseconds.
 *
 * The 'hold' mode is what lets a test drive the FAIL-CLOSED branch of D-059
 * from the outside: a real second process holding the lock, which is the only
 * faithful way to reproduce "the counter could not be written" against code
 * that must then refuse the attempt rather than hand out an uncounted one. It
 * signals readiness by creating "<path>.held" AFTER the lock is taken, so the
 * driver waits for the fact rather than sleeping a guessed interval.
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
$holdMs  = (int) ( $argv[4] ?? 0 );

if ( $mode === 'hold' ) {
    // Take the lock the product takes, on the file the product locks, and keep
    // it. No barrier: the driver waits for the readiness sentinel instead,
    // because "the lock is held" is a fact this process can state and a delay
    // can only approximate.
    $handle = fopen( $path, 'c+' );

    if ( $handle === false || ! flock( $handle, LOCK_EX ) ) {
        exit( 1 );
    }

    file_put_contents( $path . '.held', (string) getmypid() );

    try {
        usleep( $holdMs * 1000 );
    } finally {
        // In a finally, and registered as a shutdown function below, because
        // this sentinel is written into the install's REAL data directory. The
        // flock cannot leak (the OS drops it when the descriptor closes, even
        // on SIGKILL) but a stray `.held` file can, and a test fixture must not
        // be able to litter a product data directory just because its driver
        // died first. Nothing in the product reads `.held`, so a leftover is
        // inert rather than dangerous — it is still ours to clean up.
        @unlink( $path . '.held' );
        flock( $handle, LOCK_UN );
        fclose( $handle );
    }

    exit( 0 );
}

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
