<?php

/**
 * Klytos — FileLock
 * One exclusive critical section spanning a read-modify-write on a JSON file.
 *
 * @package Klytos
 * @since   0.31.1
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

/**
 * Runs a read -> decide -> write cycle on a JSON file while holding ONE
 * exclusive lock for the whole cycle.
 *
 * Why this exists (audit NEW-40 and NEW-20, closed together by D-059): the
 * product's two abuse-bounding counters — the login lockout and
 * MCP\RateLimiter — both read their file, decided, and wrote it back under
 * SEPARATE locks. Between the read and the write another process could read
 * the same pre-increment value, so increments were lost and each lost
 * increment is one request that was never counted against its limit.
 *
 * That was not a theoretical window. Measured before this class existed, with
 * 20 processes issuing one RateLimiter::check() each at the same instant:
 * 2 to 4 of the 20 were recorded. A limiter that counts ~15% of what it
 * receives is not a weak limiter, it is close to an absent one.
 *
 * WHY NOT ActionScheduler::acquireLock(), which audit NEW-40 named as the fix:
 * that method is private to its class AND takes LOCK_EX | LOCK_NB — skip if
 * busy, returning null. Skipping is right for a scheduler (another process is
 * already doing the run) and wrong for a counter: under the parallel burst
 * this class exists to bound, every contender would fail to acquire and skip
 * its own increment, which is a DETERMINISTIC lost update rather than a racy
 * one. The two locks have genuinely different contracts, so they stay
 * separate; that is not the duplication this project treats as a defect.
 *
 * WHY THE CYCLE IS NARROW: the lock spans the counter's own read-modify-write
 * and nothing else. It deliberately does NOT span Auth::login(), because
 * UserManager::authenticate() performs a bcrypt verify on every branch (the
 * NEW-39 equalization) and holding a lock across it would serialise every
 * login attempt on the install behind that verify — a denial-of-service lever
 * built by a hardening fix. The wide window is closed by the IP ceiling in
 * admin/login.php, not by a wider lock.
 *
 * PRE-BOOT SAFE: installer/public/comment-submit.php constructs a RateLimiter
 * BEFORE App::boot() by design (D-043), so nothing here may depend on the
 * autoloader, the hook system or the Klytos logger. Every optional dependency
 * is guarded with function_exists() and the only unconditional sink is
 * error_log() — the same reasoning L-006 recorded for the boot-time logger.
 */
final class FileLock
{
    /**
     * How long a caller waits for the exclusive lock before giving up.
     *
     * Two seconds is generous for a critical section that only decodes, edits
     * and re-encodes a small JSON map: it absorbs a burst without ever being
     * reached in normal operation.
     */
    public const DEFAULT_TIMEOUT_MS = 2000;

    /** Pause between lock attempts, in microseconds. */
    private const RETRY_SLEEP_US = 2000;

    /**
     * Run one transaction against a JSON file under a single exclusive lock.
     *
     * The callback receives the decoded contents and returns the array to
     * persist, or null to persist nothing (a read-only decision). To return a
     * decision as well as new data, capture a variable by reference:
     *
     *     $allowed = false;
     *     FileLock::transaction( $path, function ( array $data ) use ( &$allowed ) {
     *         $allowed = count( $data['hits'] ?? [] ) < 10;
     *         $data['hits'][] = time();
     *         return $data;
     *     } );
     *
     * `use ( &$x )` capture is unaffected by the by-reference refusal D-054
     * added to the hook registries — that check reflects PARAMETERS, and this
     * is not a hook.
     *
     * FAIL DIRECTIONS, decided explicitly in D-059 because they differ:
     *
     *  - The lock could not be taken within the deadline -> returns FALSE and
     *    the callback never runs. The caller must treat that as a refusal:
     *    not counting an attempt is exactly the amplification this class
     *    closes, so "allow it uncounted" is never the safe reading.
     *  - The file is missing, unreadable or undecodable -> the callback is
     *    given an EMPTY array and the transaction proceeds normally. Refusing
     *    everyone because a counter file is corrupt would turn one damaged
     *    file into a total login outage, which is a worse failure than the
     *    race being fixed. The condition is logged rather than swallowed.
     *
     * @param  string   $path      Absolute path to the JSON file. Created if absent.
     * @param  callable $mutate    fn( array $data ): ?array — new contents, or null to write nothing.
     * @param  int      $timeoutMs How long to wait for the lock.
     * @return bool     True if the transaction ran; false if the lock was not acquired.
     */
    public static function transaction(
        string $path,
        callable $mutate,
        int $timeoutMs = self::DEFAULT_TIMEOUT_MS
    ): bool {
        $timeoutMs = self::filterTimeout( $timeoutMs, $path );

        $dir = dirname( $path );
        if ( ! is_dir( $dir ) && ! @mkdir( $dir, 0700, true ) && ! is_dir( $dir ) ) {
            self::report( 'could not create the directory for ' . $path );

            return false;
        }

        // 'c+' opens for read and write, creates when absent and — unlike 'w+' —
        // does NOT truncate. Truncating here would discard the map before the
        // lock is even held, which is the opposite of the point.
        $handle = @fopen( $path, 'c+' );
        if ( $handle === false ) {
            self::report( 'could not open ' . $path );

            return false;
        }

        if ( ! self::acquire( $handle, $timeoutMs ) ) {
            fclose( $handle );
            self::report( 'timed out after ' . $timeoutMs . 'ms waiting for a lock on ' . $path );

            if ( function_exists( 'klytos_do_action' ) ) {
                klytos_do_action( 'file_lock.timeout', $path, $timeoutMs );
            }

            return false;
        }

        try {
            $data = self::readDecoded( $handle, $path );

            $next = $mutate( $data );

            if ( is_array( $next ) ) {
                self::writeEncoded( $handle, $next );
            }
        } finally {
            flock( $handle, LOCK_UN );
            fclose( $handle );
        }

        return true;
    }

    /**
     * Take the exclusive lock, retrying until the deadline.
     *
     * LOCK_NB plus a retry loop rather than a plain blocking LOCK_EX: a
     * blocking flock() has no timeout in PHP, so a stuck holder would hang the
     * request forever instead of refusing it. The deadline is what makes the
     * fail direction a decision rather than an accident.
     *
     * @param  resource $handle
     * @param  int      $timeoutMs
     * @return bool
     */
    private static function acquire( $handle, int $timeoutMs ): bool
    {
        $deadline = microtime( true ) + ( $timeoutMs / 1000 );

        do {
            if ( flock( $handle, LOCK_EX | LOCK_NB ) ) {
                return true;
            }

            usleep( self::RETRY_SLEEP_US );
        } while ( microtime( true ) < $deadline );

        // One last attempt, so a deadline that expires mid-sleep is not a
        // refusal for a lock that is now free.
        return (bool) flock( $handle, LOCK_EX | LOCK_NB );
    }

    /**
     * Read the whole handle and decode it, treating damage as "empty".
     *
     * @param  resource $handle
     * @param  string   $path
     * @return array<mixed>
     */
    private static function readDecoded( $handle, string $path ): array
    {
        rewind( $handle );
        $raw = stream_get_contents( $handle );

        if ( $raw === false || trim( (string) $raw ) === '' ) {
            return [];
        }

        $decoded = json_decode( (string) $raw, true );

        if ( ! is_array( $decoded ) ) {
            // Loud, because with the whole cycle under one lock our own writes
            // can no longer tear: reaching this means something outside the
            // product wrote the file, and a counter silently restarting at zero
            // is exactly the kind of quiet reset an operator should see.
            self::report( 'discarded undecodable contents of ' . $path . ' and started a fresh map' );

            return [];
        }

        return $decoded;
    }

    /**
     * Replace the handle's contents with the encoded array.
     *
     * @param resource     $handle
     * @param array<mixed> $data
     */
    private static function writeEncoded( $handle, array $data ): void
    {
        // An empty map is written as {} rather than [] so the file always
        // decodes to an object, and rather than being deleted so no unlink can
        // race the lock we are holding on it.
        $json = $data === []
            ? '{}'
            : (string) json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        ftruncate( $handle, 0 );
        rewind( $handle );
        fwrite( $handle, $json );
        fflush( $handle );
    }

    /**
     * Let a plugin widen or narrow the wait, when the hook system exists.
     *
     * Guarded: RateLimiter runs pre-boot on the public comment endpoint, where
     * klytos_apply_filters() is not declared. A filter that cannot be called is
     * skipped rather than fataled.
     *
     * @param  int    $timeoutMs
     * @param  string $path
     * @return int
     */
    private static function filterTimeout( int $timeoutMs, string $path ): int
    {
        if ( function_exists( 'klytos_apply_filters' ) ) {
            $timeoutMs = (int) klytos_apply_filters( 'file_lock.timeout_ms', $timeoutMs, $path );
        }

        return max( 0, $timeoutMs );
    }

    /**
     * Report a lock-level problem.
     *
     * error_log() and NOT klytos_log_warning(): this class runs pre-boot on the
     * public comment endpoint, where the Klytos logger does not exist yet. It
     * is also the sink that cannot be dropped — audit NEW-44 is the admin gate
     * passing a category as klytos_log()'s $source, which Logger::write()
     * treats as a plugin ID and discards, so every gate refusal wrote nothing.
     * A guard whose diagnostics are silently dropped is the failure this
     * project has now recorded three times (L-019).
     *
     * @param string $message
     */
    private static function report( string $message ): void
    {
        error_log( 'Klytos FileLock: ' . $message );
    }
}
