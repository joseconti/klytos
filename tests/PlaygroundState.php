<?php

/**
 * Klytos CMS — playground state isolation for the integration tier
 * (Sprint 1, slice 3 prerequisite / D-030).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Snapshot and restore the playground's on-disk state around a single test.
 *
 * WHY THIS EXISTS: the integration tier boots the real App against the real
 * on-disk playground. Slice 1's tests were read-only, so nothing broke; slices
 * 3, 4 and 5 assert refusals on state-changing surfaces, and slice 3's own test
 * point requires DELETING the owner user to prove the v1.x migration is
 * idempotent. Without a rollback, those tests become order-dependent within a
 * run and leave the playground permanently mutated across runs — with nothing
 * hinting that a --reset is needed.
 *
 * WHY A FILE SNAPSHOT RATHER THAN PER-TEST FIXTURES: a fixture each test creates
 * and destroys cannot express "remove the record that already exists and prove
 * boot recreates it". The migration test mutates a record it does not own, so
 * only a whole-state rollback covers it. The cost objection does not apply —
 * installer/config/ + installer/data/ is 31 files and ~124K on a seeded
 * playground, so a full copy is microseconds, not a design trade-off.
 *
 * WHAT IT DOES *NOT* COVER — read this before trusting it:
 * App is a singleton with a private constructor and no reset(), booted once per
 * process. Restoring bytes on disk cannot refresh anything a long-lived object
 * already memoized. Verified caches that survive a restore:
 *
 *   - App::$config              (app.php:217) — decrypted config.json.enc, read
 *                                once at boot (:283), never refreshed. No
 *                                invalidation path exists at all.
 *   - EncryptionLevelTrait::$cachedEncryptionLevel (encryption-level-trait.php:85)
 *                                — held by the LIVE FileStorage. The sharpest
 *                                one: a stale value corrupts writes rather than
 *                                merely reading stale data.
 *   - OptionsManager::$cache (options-manager.php:44) and its static
 *     $sensitivityRegistry (:62).
 *   - AiKeyManager::$cache   (ai/ai-key-manager.php:33).
 *
 * The storage layer itself is clean — FileStorage::read/list/count/exists and
 * UserManager and SiteConfig memoize nothing and touch the filesystem on every
 * call — which is precisely why a file-level restore is sound for the users,
 * pages and site-config records slices 3-5 actually assert against.
 *
 * Rather than reflection-hacking a private singleton property (fragile, and
 * product surgery in a security sprint is what D-026 refused), the uncoverable
 * case is turned into a LOUD FAILURE: assertConfigNotMutated() fails the test
 * when core config changed under a booted App. A test that needs to mutate core
 * config, options, the encryption level or AI keys must run in its own process
 * (@runInSeparateProcess) — the trait tells it so by name when it trips.
 */
trait PlaygroundState
{
    /** @var string|null Absolute path to this test's snapshot directory. */
    private ?string $snapshotDir = null;

    /** @var string|null Raw bytes of config.json.enc at snapshot time. */
    private ?string $configBytesAtSnapshot = null;

    /**
     * Raw bytes of config.json.enc as the test LEFT it, captured before restore.
     *
     * Load-bearing, and it exists because the first version of this guard could
     * not fail. assertConfigNotMutated() runs after restorePlayground() — which
     * is correct, so the playground is left clean even when the assertion fails
     * (D-030) — but it re-hashed the file the restore had just put back, so the
     * comparison was always snapshot-against-itself. Verified with a probe in
     * slice 5: a test that wrote a marker key into core config passed green.
     * The observation has to be taken BEFORE the restore erases it.
     *
     * @var string|null
     */
    private ?string $configBytesBeforeRestore = null;

    /**
     * Config keys that change on their own and do not mean a test mutated state.
     *
     * `scheduler_last_run` is written by ActionScheduler::setConfigValue()
     * (action-scheduler.php:398) every time due actions are processed, which
     * App::boot() triggers on EVERY request. The HTTP tests boot a real server
     * per request, so each one rewrites core config from a separate process as
     * a matter of course. Hashing raw bytes therefore flagged ten passing tests
     * the moment the guard was repaired — a true observation about the file and
     * a false one about the test.
     *
     * The comparison is on DECRYPTED content minus these keys for a second
     * reason too: the stored file is encrypted, so a rewrite of byte-identical
     * content still produces different ciphertext. A byte comparison cannot
     * tell "changed" from "rewritten", and only one of those is a defect.
     *
     * @var array<int, string>
     */
    private const VOLATILE_CONFIG_KEYS = [ 'scheduler_last_run' ];

    /**
     * The two directories that hold every byte of playground runtime state.
     *
     * The encryption key lives in config/ and is therefore snapshotted with it.
     * That is deliberate and it is consistent: the key and the records it
     * encrypts are captured and restored together, so they can never be
     * reunited across a boundary. It is also why this list must NOT be widened
     * to a directory the live Encryption object read at construction — every
     * existing instance would become wrong at once.
     *
     * @return array<int, string> Absolute paths.
     */
    private function stateDirectories(): array
    {
        return [
            KLYTOS_INSTALLER_PATH . '/config',
            KLYTOS_INSTALLER_PATH . '/data',
        ];
    }

    /**
     * Capture the playground's current on-disk state.
     *
     * @return void
     */
    protected function snapshotPlayground(): void
    {
        $this->snapshotDir = sys_get_temp_dir() . '/klytos-snap-' . bin2hex( random_bytes( 8 ) );

        if ( ! mkdir( $this->snapshotDir, 0700, true ) && ! is_dir( $this->snapshotDir ) ) {
            throw new RuntimeException( "Could not create snapshot directory {$this->snapshotDir}" );
        }

        foreach ( $this->stateDirectories() as $source ) {
            $this->copyTree( $source, $this->snapshotDir . '/' . basename( $source ) );
        }

        $this->configBytesAtSnapshot = $this->configBytes();
    }

    /**
     * Roll the playground back to the captured state.
     *
     * Ordering is deliberate and is the safety property of this method: the
     * snapshot is copied back OVER the live tree first, and only then are files
     * that the test added removed. A crash midway therefore leaves a superset of
     * the correct state — never a hole in it. The reverse order (wipe, then
     * copy) would leave a half-empty playground behind on any interruption.
     *
     * @return void
     */
    protected function restorePlayground(): void
    {
        if ( $this->snapshotDir === null ) {
            return;
        }

        // Observe BEFORE overwriting. The restore below is what makes the
        // playground safe for the next test, and it is also what destroys the
        // evidence assertConfigNotMutated() needs — so the evidence is taken
        // here, while it still exists.
        $this->configBytesBeforeRestore = $this->configBytes();

        foreach ( $this->stateDirectories() as $target ) {
            $captured = $this->snapshotDir . '/' . basename( $target );

            $this->copyTree( $captured, $target );
            $this->removeFilesNotIn( $captured, $target );
        }

        $this->removeTree( $this->snapshotDir );
        $this->snapshotDir = null;
    }

    /**
     * Fail the test when core config was mutated under a booted App.
     *
     * This is the honest half of the primitive. Restoring config.json.enc on
     * disk does NOT refresh App::$config, which was decrypted once at boot and
     * has no invalidation path. A test that changes core config and then asserts
     * against App::getConfig() would read the pre-test value and pass for the
     * wrong reason — the exact class of false green this tier exists to prevent.
     *
     * @return void
     */
    protected function assertConfigNotMutated(): void
    {
        if ( $this->configBytesAtSnapshot === null ) {
            return;
        }

        // The bytes as the test left them, not as the restore put them back.
        // Reading the file live here is what made the original guard inert.
        $left                           = $this->configBytesBeforeRestore;
        $this->configBytesBeforeRestore = null;

        // Identical ciphertext means nothing was written at all — the cheap
        // path, and the common one.
        if ( $left === $this->configBytesAtSnapshot ) {
            return;
        }

        if ( $this->meaningfulConfig( $left ) === $this->meaningfulConfig( $this->configBytesAtSnapshot ) ) {
            return;
        }

        self::fail(
            'This test mutated installer/config/config.json.enc while App was already booted. '
            . 'The file has been restored, but App::$config (app.php:217) was decrypted once at '
            . 'boot and cannot be refreshed — any later assertion would read the stale value and '
            . 'pass for the wrong reason. Run this test with #[RunInSeparateProcess] so it gets '
            . 'its own App, or assert against storage directly instead of App::getConfig().'
        );
    }

    /**
     * Decrypted core config, minus the keys that change on their own.
     *
     * Returns a canonical JSON string so two states can be compared directly.
     * When the ciphertext cannot be decrypted — a test that changed the
     * encryption level, a truncated write — the raw bytes are returned instead,
     * so an undecryptable difference still counts as a difference. Failing
     * loudly beats silently treating "I cannot read this" as "nothing changed".
     *
     * @param  string|null $bytes Raw ciphertext, or null when the file was absent.
     * @return string      Canonical comparable form.
     */
    private function meaningfulConfig( ?string $bytes ): string
    {
        if ( $bytes === null ) {
            return '<absent>';
        }

        // Decryption needs the booted App's storage. A test that skipped or
        // failed during setUp may never have got one — treat that as
        // undecryptable rather than fataling inside a teardown assertion.
        if ( ! isset( $this->storage ) ) {
            return $bytes;
        }

        try {
            $config = $this->storage->getEncryption()->decrypt( $bytes );
        } catch ( \Throwable $e ) {
            return $bytes;
        }

        if ( ! is_array( $config ) ) {
            return $bytes;
        }

        foreach ( self::VOLATILE_CONFIG_KEYS as $key ) {
            unset( $config[ $key ] );
        }

        ksort( $config );

        return (string) json_encode( $config );
    }

    /**
     * Raw bytes of the encrypted core config file, or null when absent.
     *
     * @return string|null
     */
    private function configBytes(): ?string
    {
        $path = KLYTOS_INSTALLER_PATH . '/config/config.json.enc';

        return is_file( $path ) ? (string) file_get_contents( $path ) : null;
    }

    /**
     * Recursively copy a directory tree, preserving permissions.
     *
     * Permissions are copied explicitly because copy() does not preserve them
     * and this tree contains mode-0600 secrets (.encryption_key,
     * .playground-access). Restoring them world-readable would be a real
     * regression introduced by the test harness itself.
     *
     * @param  string $source Absolute path to an existing directory.
     * @param  string $target Absolute path; created if absent.
     * @return void
     */
    private function copyTree( string $source, string $target ): void
    {
        if ( ! is_dir( $source ) ) {
            return;
        }

        if ( ! is_dir( $target ) && ! mkdir( $target, 0700, true ) && ! is_dir( $target ) ) {
            throw new RuntimeException( "Could not create directory {$target}" );
        }

        chmod( $target, fileperms( $source ) & 0777 );

        /** @var SplFileInfo $item */
        foreach ( $this->walk( $source ) as $item ) {
            $destination = $target . '/' . $this->relativePath( $source, $item );

            if ( $item->isDir() ) {
                if ( ! is_dir( $destination ) && ! mkdir( $destination, 0700, true ) && ! is_dir( $destination ) ) {
                    throw new RuntimeException( "Could not create directory {$destination}" );
                }
            } else {
                copy( $item->getPathname(), $destination );
            }

            chmod( $destination, $item->getPerms() & 0777 );
        }
    }

    /**
     * Delete everything in $target that has no counterpart in $reference.
     *
     * @param  string $reference Absolute path to the snapshot tree.
     * @param  string $target    Absolute path to the live tree.
     * @return void
     */
    private function removeFilesNotIn( string $reference, string $target ): void
    {
        if ( ! is_dir( $target ) ) {
            return;
        }

        $items = iterator_to_array( $this->walk( $target ), false );

        // Deepest first, so a directory is only removed once it is empty.
        usort(
            $items,
            static fn( SplFileInfo $a, SplFileInfo $b ): int
                => substr_count( $b->getPathname(), '/' ) <=> substr_count( $a->getPathname(), '/' )
        );

        foreach ( $items as $item ) {
            $counterpart = $reference . '/' . $this->relativePath( $target, $item );

            if ( file_exists( $counterpart ) ) {
                continue;
            }

            if ( $item->isDir() ) {
                rmdir( $item->getPathname() );
            } else {
                unlink( $item->getPathname() );
            }
        }
    }

    /**
     * Recursively delete a directory tree.
     *
     * Only ever pointed at this test's own snapshot directory under the system
     * temp dir — never at a tracked tree.
     *
     * @param  string $dir Absolute path.
     * @return void
     */
    private function removeTree( string $dir ): void
    {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $items as $item ) {
            if ( $item->isDir() ) {
                rmdir( $item->getPathname() );
            } else {
                unlink( $item->getPathname() );
            }
        }

        rmdir( $dir );
    }

    /**
     * Iterate a tree top-down, including dotfiles.
     *
     * SKIP_DOTS drops only "." and "..", never ".htaccess" or ".encryption_key"
     * — which is the whole point here, since the tracked .htaccess guards and
     * the encryption key are exactly what must survive a round trip.
     *
     * @param  string $dir Absolute path.
     * @return RecursiveIteratorIterator<RecursiveDirectoryIterator>
     */
    private function walk( string $dir ): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );
    }

    /**
     * Path of $item relative to $root.
     *
     * @param  string      $root Absolute base path.
     * @param  SplFileInfo $item Item beneath it.
     * @return string
     */
    private function relativePath( string $root, SplFileInfo $item ): string
    {
        return ltrim( substr( $item->getPathname(), strlen( $root ) ), '/' );
    }
}
