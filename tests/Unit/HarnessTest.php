<?php

/**
 * Klytos CMS — unit tier self-check (Sprint 1, slice 1 / T-01).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\Hooks;
use Klytos\Tests\UnitTestCase;

/**
 * Proves the unit tier itself works, before any slice depends on it.
 *
 * Not a test of Klytos behaviour: a test of the harness. It asserts the three
 * properties later slices will assume — storage is real and isolated, the
 * temp directory is genuinely per-test, and static hook state does not leak
 * between tests.
 */
final class HarnessTest extends UnitTestCase
{
    public function testStorageRoundTripsThroughTheRealBackend(): void
    {
        $record = [
            'title'  => 'Harness record',
            'nested' => [ 'a' => 1, 'b' => [ true, null, 'x' ] ],
        ];

        $this->storage->write( 'pages', 'harness', $record );

        self::assertTrue( $this->storage->exists( 'pages', 'harness' ) );
        self::assertSame( $record, $this->storage->read( 'pages', 'harness' ) );
    }

    public function testTemporaryDirectoryIsIsolatedPerTest(): void
    {
        // The previous test wrote 'pages/harness'. If the temp directory were
        // shared — or not cleaned — this would find it. It must not.
        self::assertFalse( $this->storage->exists( 'pages', 'harness' ) );
        self::assertDirectoryExists( $this->tempPath( 'data' ) );
        self::assertFileExists( $this->tempPath( 'config/.encryption_key' ) );
    }

    public function testHooksFireAndDoNotLeakBetweenTests(): void
    {
        $seen = [];

        Hooks::addAction( 'klytos_tests_harness', static function ( string $value ) use ( &$seen ): void {
            $seen[] = $value;
        } );

        Hooks::doAction( 'klytos_tests_harness', 'fired' );

        self::assertSame( [ 'fired' ], $seen );
        self::assertSame( 1, Hooks::didAction( 'klytos_tests_harness' ) );
    }

    public function testHookRegistryWasResetBeforeThisTest(): void
    {
        // Depends on the test above having registered a listener on this hook.
        // UnitTestCase resets Hooks in both setUp and tearDown, so the registry
        // must be empty here regardless of execution order.
        self::assertFalse( Hooks::hasAction( 'klytos_tests_harness' ) );
        self::assertSame( 0, Hooks::didAction( 'klytos_tests_harness' ) );
    }
}
