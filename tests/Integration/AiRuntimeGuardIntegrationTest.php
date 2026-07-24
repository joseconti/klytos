<?php

/**
 * Klytos CMS — the NEW-06 guard is transparent on a supported runtime
 * (Sprint 3, slice 2).
 *
 * The unit tier proves the decision and the ordering. This tier proves the half
 * that matters most in practice and that a unit test cannot reach: on a host
 * that CAN run the AI stack, the guard does nothing at all — `getChatEngine()`
 * still returns a working engine and the vendored autoloader still loads.
 *
 * This is the positive control L-008 requires. A guard tested only in its
 * refusing direction is indistinguishable from a guard that refuses everything,
 * and "AI chat is disabled on every host" would be a far worse regression than
 * the fatal this slice removes.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Core\Ai\ChatEngine;
use Klytos\Core\App;
use PHPUnit\Framework\Attributes\Group;
use Klytos\Tests\IntegrationTestCase;

/**
 * The group is applied PER METHOD here, not to the class — deliberately. Two of
 * these three tests load the vendored AI stack and cannot run below PHP 8.3, so
 * CI's 8.2 leg excludes them by group (D-045's "a skip is a hard failure" rule
 * stays intact that way). The third asserts only that the refusal MESSAGE
 * resolves, which needs no vendored code — and 8.2 is precisely the runtime where
 * that message is the thing a real operator would see, so it must keep running
 * there. A class-level group would have silently dropped it.
 */
final class AiRuntimeGuardIntegrationTest extends IntegrationTestCase
{
    #[Group( 'ai-runtime' )]
    public function testThisRuntimeIsSupportedSoTheGuardMustBeTransparent(): void
    {
        // SKIP, not fail, below the floor. An earlier version of this asserted
        // the runtime was supported, which turned CI's PHP 8.2 leg red for
        // behaviour that is CORRECT there — this slice's own subject used as a
        // stick to beat itself with. Caught by the slice's code-reviewer pass.
        $this->requireAiRuntime();

        $engine = $this->app->getChatEngine();

        $this->assertInstanceOf(
            ChatEngine::class,
            $engine,
            'getChatEngine() must still build an engine on a supported runtime — '
            . 'the NEW-06 guard is a floor, not a switch.'
        );
    }

    /**
     * The vendored autoloader is the thing the guard stands in front of; on a
     * supported runtime it must still have run, which is only observable through
     * a class it provides.
     */
    #[Group( 'ai-runtime' )]
    public function testTheVendoredAutoloaderStillRunsOnASupportedRuntime(): void
    {
        $this->requireAiRuntime();

        $this->app->getChatEngine();

        $this->assertTrue(
            class_exists( \GuzzleHttp\Client::class ),
            'The vendored autoloader did not run, so the guard is refusing a '
            . 'runtime it should allow.'
        );
    }

    /**
     * The refusal MESSAGE, pinned separately — because on a supported runtime the
     * throwing branch never executes, and an unresolvable `__()` there would turn
     * this guard into a worse fault than the one it removes: an "undefined
     * function" fatal instead of a Composer fatal.
     *
     * That risk is real rather than theoretical. Per NEW-18 the GLOBAL `__()`
     * exists only under `admin/`, and one of `getChatEngine()`'s three callers is
     * an MCP tool that never loads `admin/bootstrap.php`. What makes it safe is
     * that `registerI18nGlobal()` declares the function inside a namespaced
     * method body, so it is really `Klytos\Core\__()` — and PHP resolves an
     * unqualified call by the namespace of the CALLING FILE (`app.php`), not of
     * the caller. This test is that reasoning turned into an assertion.
     *
     * `I18n::get()` returns the KEY itself when it cannot resolve one
     * (`i18n.php`), so "the result is not the key" is exactly the property that
     * distinguishes a real translation from a silent miss.
     */
    public function testTheRefusalMessageResolvesAndSubstitutesBothPlaceholders(): void
    {
        $message = \Klytos\Core\__(
            'ai.unsupported_runtime',
            [ 'required' => '8.3', 'running' => '8.2.15' ]
        );

        $this->assertNotSame(
            'ai.unsupported_runtime',
            $message,
            'The catalogue key did not resolve — I18n returns the key verbatim when '
            . 'it cannot find it, so the refusal would show a raw key to the operator.'
        );

        $this->assertStringContainsString( '8.3', $message, 'The {required} placeholder was not substituted.' );
        $this->assertStringContainsString( '8.2.15', $message, 'The {running} placeholder was not substituted.' );
        $this->assertStringNotContainsString( '{required}', $message );
        $this->assertStringNotContainsString( '{running}', $message );
    }
}
