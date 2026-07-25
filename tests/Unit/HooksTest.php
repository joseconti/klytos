<?php

/**
 * Klytos CMS — the hook engine's contract (Sprint 4, slice 1 / audit NEW-03).
 *
 * The engine had NO test of any kind before this file, which is part of why
 * NEW-03 survived from adoption to Sprint 4: the defect is invisible in a diff
 * and only exists at dispatch time (L-005).
 *
 * What is pinned here is the contract itself, in both directions: a listener
 * that declares a by-reference parameter is REFUSED at registration, and a
 * listener that does not is untouched. One direction is half a test (L-010).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\HookContractException;
use Klytos\Core\Hooks;
use Klytos\Tests\UnitTestCase;

/**
 * The action/filter contract: arguments are passed by value, and a listener
 * that says otherwise is refused rather than silently ignored.
 *
 * Before this slice, `Hooks::doAction()` collected its arguments variadically
 * (`mixed ...$args`) and dispatched with `call_user_func_array()`. Variadics
 * copy, so a `&$param` listener could not bind: PHP emitted a warning, ran the
 * callback body against a copy, and discarded the write. Registration accepted
 * it happily, so nothing in the system ever said "this cannot work".
 */
final class HooksTest extends UnitTestCase
{
    // ─── The refusal (the NEW-03 fix) ────────────────────────────

    public function testAByReferenceActionListenerIsRefusedAtRegistration(): void
    {
        $this->expectException( HookContractException::class );

        Hooks::addAction( 'klytos_tests_byref', static function ( array &$data ): void {
            $data['never'] = true;
        } );
    }

    public function testAByReferenceFilterListenerIsRefusedAtRegistration(): void
    {
        // applyFilters() threads the RETURN value, so by-reference is never
        // needed there either — and `call_user_func($cb, $value, ...$args)`
        // copies exactly as the action path does, so a `&$value` filter loses
        // its write in the same way. Closed in the same move rather than left
        // as the next NEW-03.
        $this->expectException( HookContractException::class );

        Hooks::addFilter( 'klytos_tests_byref_filter', static function ( array &$value ): array {
            return $value;
        } );
    }

    public function testARefusalNamesTheHookTheParameterAndWhereTheCallbackWasDeclared(): void
    {
        // A refusal a plugin author cannot act on is a refusal that gets
        // worked around. The message must locate the callback, not just
        // announce a rule.
        try {
            Hooks::addAction( 'klytos_tests_message', static function ( array &$payload ): void {
            } );
            self::fail( 'Expected HookContractException, none thrown.' );
        } catch ( HookContractException $e ) {
            self::assertSame( 'klytos_tests_message', $e->getHookName() );
            self::assertStringContainsString( 'klytos_tests_message', $e->getMessage() );
            self::assertStringContainsString( '$payload', $e->getMessage() );
            self::assertStringContainsString( basename( __FILE__ ), $e->getMessage() );
            self::assertStringContainsString( __FILE__, $e->getCallbackLocation() );
        }
    }

    public function testTheByReferenceParameterIsCaughtInAnyPosition(): void
    {
        // The real listener (x402-bootstrap.php:194) declared it first, so a
        // check that only inspected parameter #1 would have passed this slice
        // and still missed every other shape.
        $this->expectException( HookContractException::class );

        Hooks::addAction( 'klytos_tests_second_position', static function ( string $a, array &$b ): void {
        } );
    }

    // ─── The positive controls (L-008: assert what SHOULD work, too) ──

    public function testAByValueActionListenerRegistersAndFiresWithItsArgumentsIntact(): void
    {
        $seen = [];

        Hooks::addAction( 'klytos_tests_byvalue', static function ( array $page, string $action ) use ( &$seen ): void {
            $seen[] = [ $page['slug'], $action ];
        } );

        Hooks::doAction( 'klytos_tests_byvalue', [ 'slug' => 'about' ], 'create' );

        self::assertSame( [ [ 'about', 'create' ] ], $seen );
    }

    public function testAByReferenceCLOSUREUSEIsNotAByReferenceParameter(): void
    {
        // `use ( &$seen )` is by-reference CAPTURE, a different mechanism that
        // works correctly and is used by four existing tests in this suite.
        // A check that conflated the two would refuse them all — so the
        // distinction is pinned rather than assumed.
        $captured = [];

        Hooks::addAction( 'klytos_tests_capture', static function ( string $value ) use ( &$captured ): void {
            $captured[] = $value;
        } );

        Hooks::doAction( 'klytos_tests_capture', 'fired' );

        self::assertSame( [ 'fired' ], $captured );
    }

    public function testAByValueFilterRegistersAndThreadsItsReturnValue(): void
    {
        Hooks::addFilter( 'klytos_tests_filter', static function ( array $data, string $action ): array {
            $data['action'] = $action;
            return $data;
        } );

        $out = Hooks::applyFilters( 'klytos_tests_filter', [ 'slug' => 'about' ], 'create' );

        self::assertSame( [ 'slug' => 'about', 'action' => 'create' ], $out );
    }

    // ─── Dispatch shapes that must survive the change ────────────

    public function testDoActionStillPassesTheMeasuredMaximumOfFourPayloadArguments(): void
    {
        // Four is the measured maximum across all 363 action fire sites
        // (e.g. http-client.php:113, meta-manager.php:110, x402/gate.php:123).
        $seen = null;

        Hooks::addAction( 'klytos_tests_four', static function ( $a, $b, $c, $d ) use ( &$seen ): void {
            $seen = [ $a, $b, $c, $d ];
        } );

        Hooks::doAction( 'klytos_tests_four', 'one', 2, [ 3 ], null );

        self::assertSame( [ 'one', 2, [ 3 ], null ], $seen );
    }

    public function testDoActionStillAcceptsArgumentUnpacking(): void
    {
        // Pins action-scheduler.php:458 — `klytos_do_action($action['hook'], ...$hookArgs)`
        // fires an arbitrary STORED hook name with arbitrary stored arity. It is
        // the one dispatch path that cannot be analysed statically, so it is the
        // one that most needs a test.
        $seen = null;

        Hooks::addAction( 'klytos_tests_unpack', static function ( string $a, int $b ) use ( &$seen ): void {
            $seen = [ $a, $b ];
        } );

        $storedArgs = [ 'from-storage', 7 ];
        Hooks::doAction( 'klytos_tests_unpack', ...$storedArgs );

        self::assertSame( [ 'from-storage', 7 ], $seen );
    }

    public function testPriorityOrderingIsUnaffected(): void
    {
        $order = [];

        Hooks::addAction( 'klytos_tests_priority', static function () use ( &$order ): void {
            $order[] = 'late';
        }, 20 );
        Hooks::addAction( 'klytos_tests_priority', static function () use ( &$order ): void {
            $order[] = 'early';
        }, 1 );

        Hooks::doAction( 'klytos_tests_priority' );

        self::assertSame( [ 'early', 'late' ], $order );
    }

    // ─── The invariant the enforcement depends on ────────────────

    public function testCoreItselfRegistersNoByReferenceListener(): void
    {
        // This is a SAFETY interlock, not a style check. PluginLoader::loadPlugin()
        // wraps every plugin entry point in try/catch (\Throwable) and records a
        // named load error (plugin-loader.php:245-252), so a third-party listener
        // that trips the new refusal fails safe. Core is NOT loaded that way —
        // x402-bootstrap.php is required directly by App::boot() — so a
        // by-reference listener there would throw OUT of boot and take the whole
        // CMS down.
        //
        // The scan covers installer/core/ AND installer/admin/, and the second
        // is the one that is easy to forget: admin/bootstrap.php registers five
        // hooks at top level, is required by every admin page and endpoint, and
        // sits OUTSIDE both PluginLoader's catch and its own two try/catch blocks
        // (which close well above those registrations). No test boots it either —
        // IntegrationTestCase calls App::boot() directly rather than requiring
        // it — so a by-reference listener added there would be caught by nothing
        // at all until a real admin request hit it.
        //
        // WHAT THIS SCAN DOES AND DOES NOT COVER, stated rather than implied:
        // it matches an inline closure or arrow function registered directly in
        // a klytos_add_action/filter or Hooks::addAction/addFilter call. It does
        // NOT see a callback assigned to a variable first, a named function
        // passed by string, or an array callable — the closure body then sits
        // outside the call this pattern anchors on.
        //
        // That is acceptable because this is the SECOND line of defence, not the
        // first: Hooks::refuseByReferenceCallback() reflects every callback at
        // registration regardless of how the call site is spelled, so a
        // regression is caught the moment any test boots the App. What this scan
        // buys is catching it in the UNIT tier, without a playground, with the
        // offending file named — before the integration tier fails obscurely at
        // boot. A cheap early warning, not the guarantee.
        $offenders = [];
        $scanned   = 0;

        $files = new \AppendIterator();
        foreach ( [ '/core', '/admin' ] as $dir ) {
            $files->append(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        KLYTOS_INSTALLER_PATH . $dir,
                        \FilesystemIterator::SKIP_DOTS
                    )
                )
            );
        }

        foreach ( $files as $file ) {
            if ( $file->getExtension() !== 'php' ) {
                continue;
            }

            ++$scanned;
            $source = file_get_contents( $file->getPathname() );

            // Two patterns, because core registers through either spelling:
            // the global helper or Hooks:: directly. Both `function` and the
            // arrow-function form are matched — PHP arrow functions support
            // by-reference parameters too, and requiring the `function` keyword
            // would miss `fn ( array &$d ) => …` entirely.
            $inlineRegistration = '/(?:klytos_add_(?:action|filter)|Hooks::add(?:Action|Filter))'
                . '\s*\(\s*[^)]*?(?:function|fn)\s*\([^)]*&\s*\$/s';

            if ( preg_match( $inlineRegistration, $source ) ) {
                $offenders[] = str_replace( KLYTOS_INSTALLER_PATH . '/', '', $file->getPathname() );
            }
        }

        // A scan that read nothing must not report success. keel-verify's
        // placeholder check shipped with exactly this hole — it printed
        // "PASS (0 files)" when its file list came back empty, indistinguishable
        // from "nothing to flag" (L-016).
        self::assertGreaterThan(
            100,
            $scanned,
            'The scan read ' . $scanned . ' files — it has not passed, it did not run.'
        );

        self::assertSame(
            [],
            $offenders,
            'A by-reference hook listener in installer/core/ or installer/admin/ would throw out '
            . 'of boot, which PluginLoader\'s catch does not cover. Convert it to a filter.'
        );
    }
}
