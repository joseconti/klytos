<?php

/**
 * Klytos CMS — base case for the unit tier (Sprint 1, slice 1 / T-01).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests;

use FilesystemIterator;
use Klytos\Core\Encryption;
use Klytos\Core\FileStorage;
use Klytos\Core\Hooks;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Unit tier — no App, no installation, no shared state.
 *
 * Each test gets its own throwaway directory with its own encryption key and
 * its own FileStorage over it, so storage, managers and hooks can be exercised
 * exactly as the application uses them without an installed Klytos anywhere.
 * The tier runs on a bare checkout, which is what makes it the tier that can
 * gate a commit.
 *
 * Hooks are static (core/hooks.php), so they are reset before AND after every
 * test — a listener leaking between tests is the classic way a suite starts
 * passing for the wrong reason.
 */
abstract class UnitTestCase extends TestCase
{
    /** @var string Absolute path to this test's throwaway directory. */
    protected string $tempDir;

    /** @var Encryption Encryption engine over this test's own master key. */
    protected Encryption $encryption;

    /** @var FileStorage Storage backend rooted at {@see $tempDir}/data. */
    protected FileStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/klytos-test-' . bin2hex( random_bytes( 8 ) );

        mkdir( $this->tempDir . '/config', 0700, true );
        mkdir( $this->tempDir . '/data', 0700, true );

        $keyPath = $this->tempDir . '/config/.encryption_key';
        Encryption::generateKey( $keyPath );

        $this->encryption = new Encryption( $keyPath );
        $this->storage    = new FileStorage( $this->encryption, $this->tempDir . '/data' );

        Hooks::reset();
    }

    protected function tearDown(): void
    {
        Hooks::reset();
        $this->removeDirectory( $this->tempDir );

        parent::tearDown();
    }

    /**
     * Absolute path inside this test's throwaway directory.
     *
     * @param  string $relative Path relative to the temp directory root.
     * @return string
     */
    protected function tempPath( string $relative = '' ): string
    {
        return $this->tempDir . ( $relative === '' ? '' : '/' . ltrim( $relative, '/' ) );
    }

    /**
     * Recursively delete a directory.
     *
     * Unlike the playground seeder's equivalent, this one preserves nothing:
     * the target is a temp directory this test created, never a tracked tree.
     *
     * @param  string $dir Absolute path.
     * @return void
     */
    private function removeDirectory( string $dir ): void
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
}
