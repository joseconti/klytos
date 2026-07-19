<?php

/**
 * Klytos CMS — proof that the integration tier rolls the playground back
 * (Sprint 1, slice 3 prerequisite / D-030).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use FilesystemIterator;
use Klytos\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Depends;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Proves the rollback primitive before any slice relies on it.
 *
 * An isolation mechanism that is merely *believed* to work is worse than none:
 * it invites tests to mutate freely while the playground silently rots. So this
 * pair of tests does to the primitive what slice 2 did to the manifest drift
 * guard — makes it demonstrate the property under a real mutation, in the same
 * change that introduces it.
 *
 * The two tests are ordered with #[Depends] rather than by name, because the
 * assertion in the second one is only meaningful after the first has actually
 * mutated something. phpunit.xml sets executionOrder="depends,defects", so the
 * dependency is what schedules them.
 */
final class PlaygroundIsolationTest extends IntegrationTestCase
{
    /** @var string Fingerprint of the untouched playground, captured by the first test. */
    private static string $fingerprintBefore = '';

    /**
     * Mutate the playground destructively, and confirm the mutation took effect.
     *
     * Deleting a seeded user is the mutation deliberately chosen here: it is
     * exactly what slice 3's migration test must do (remove the owner, prove
     * boot recreates it), and it is the case a create-and-destroy fixture cannot
     * express, since the record belongs to the seed rather than to the test.
     *
     * @return void
     */
    public function testAPlaygroundCanBeMutatedDestructively(): void
    {
        self::$fingerprintBefore = $this->fingerprintState();

        $editor = $this->users->getByUsername( 'editor' );
        self::assertNotNull( $editor, 'The seeded editor must exist before the mutation.' );

        $this->storage->delete( 'users', $editor['id'] );

        self::assertNull(
            $this->users->getByUsername( 'editor' ),
            'The mutation must really take effect — otherwise the rollback proves nothing.'
        );

        self::assertNotSame(
            self::$fingerprintBefore,
            $this->fingerprintState(),
            'The on-disk fingerprint must change, or this test is not exercising the primitive.'
        );
    }

    /**
     * The next test must see the untouched playground.
     *
     * Asserts both halves, because they fail for different reasons: the record
     * being back proves the copy-back ran, and the fingerprint matching proves
     * nothing was left behind — a restore that only re-adds what was deleted,
     * without removing what was created, would pass the first check and fail
     * this one.
     *
     * @return void
     */
    #[Depends( 'testAPlaygroundCanBeMutatedDestructively' )]
    public function testBPlaygroundIsRestoredForTheNextTest(): void
    {
        self::assertNotNull(
            $this->users->getByUsername( 'editor' ),
            'The deleted user is still missing — the playground was NOT rolled back, and every '
            . 'later authorization test is running against a mutated fixture.'
        );

        self::assertSame(
            self::$fingerprintBefore,
            $this->fingerprintState(),
            'The playground no longer matches its pre-test state byte-for-byte.'
        );
    }

    /**
     * A stable fingerprint of every byte of playground runtime state.
     *
     * Covers relative path, size, permission bits and content hash for each
     * file, sorted so the result is independent of filesystem iteration order.
     * Permissions are included on purpose: restoring a mode-0600 secret as
     * world-readable would be a regression introduced by the harness itself,
     * and a content-only fingerprint would not notice.
     *
     * @return string
     */
    private function fingerprintState(): string
    {
        $entries = [];

        foreach ( [ 'config', 'data' ] as $dir ) {
            $root = KLYTOS_INSTALLER_PATH . '/' . $dir;

            if ( ! is_dir( $root ) ) {
                continue;
            }

            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ( $items as $item ) {
                $relative = $dir . substr( $item->getPathname(), strlen( $root ) );
                $mode     = sprintf( '%o', $item->getPerms() & 0777 );

                $entries[] = $item->isDir()
                    ? "d {$relative} {$mode}"
                    : "f {$relative} {$mode} {$item->getSize()} " . hash_file( 'sha256', $item->getPathname() );
            }
        }

        sort( $entries );

        return hash( 'sha256', implode( "\n", $entries ) );
    }
}
