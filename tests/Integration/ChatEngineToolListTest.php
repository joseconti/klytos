<?php

/**
 * Klytos CMS — the AI chat advisory tool list is honest (Sprint 2, slice 3 /
 * audit NEW-02).
 *
 * ChatEngine::getAvailableTools() used to filter only for exactly viewer/editor
 * (no else), so any OTHER role fell through and got the full list; it was also
 * wrapped in if(function_exists('klytos_current_user')), so an absent helper
 * skipped the filter entirely. Slice 2 neutralised the teeth — listTools() is
 * capability-filtered and call() gates every dispatch regardless — so this list
 * is advisory, but it must not be WIDER than what the gate allows. This slice
 * default-denies an unrecognised role to an empty list.
 *
 * To isolate getAvailableTools()'s own role handling from listTools()'s
 * capability filter, the base registry here carries an OWNER actor, so
 * listTools() returns the FULL set; any narrowing then comes from the method
 * under test. Against the unfixed method, an unknown role returns that full
 * list; the fix returns [].
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Core\Ai\ChatEngine;
use Klytos\Core\MCP\ToolRegistry;
use PHPUnit\Framework\Attributes\Group;
use Klytos\Tests\IntegrationTestCase;

/**
 * Grouped so CI's PHP 8.2 leg can EXCLUDE these explicitly rather than let them
 * skip. Klytos declares PHP 8.1+ and CI verifies 8.2, but the vendored AI stack
 * needs 8.3 (NEW-06 / D-053), so these tests cannot run there. Excluding a named
 * group keeps D-045's 'a skip is a hard failure' rule intact and meaningful — a
 * silently skipped integration tier is exactly what that rule exists to catch.
 */
#[Group( 'ai-runtime' )]
final class ChatEngineToolListTest extends IntegrationTestCase
{
    /**
     * A ChatEngine over a registry whose actor is OWNER, so its listTools()
     * returns the full tool set — the base list getAvailableTools() then narrows
     * by the SESSION role, which is what these tests exercise.
     */
    private function engineWithFullBaseList(): ChatEngine
    {
        // getChatEngine() below loads the vendored AI stack, which needs PHP 8.3
        // while Klytos declares 8.1+ and CI runs the suite on 8.2 too. Below the
        // floor the guard refuses (NEW-06 / D-053) — correct there, and these
        // tests have nothing to say about it. Pre-dates Sprint 3: this class has
        // called getChatEngine() unconditionally since Sprint 2 slice 3, so the
        // 8.2 leg would have broken here first. It was never observed because
        // CI has never actually run — every commit since the workflow was written
        // is still unpushed.
        $this->requireAiRuntime();

        $registry = new ToolRegistry( $this->app );
        $registry->registerAllTools();
        $registry->setActor( 1, 'owner' );

        $keys = $this->app->getChatEngine()->getKeys();

        return new ChatEngine( $keys, $registry, $this->app );
    }

    /**
     * Invoke the private getAvailableTools() directly — the method is where the
     * advisory-list decision lives, and driving the full processMessage() would
     * require a live AI provider.
     *
     * @return array<int, array<string, mixed>>
     */
    private function availableTools( ChatEngine $engine, int $userId ): array
    {
        $method = new \ReflectionMethod( ChatEngine::class, 'getAvailableTools' );
        $method->setAccessible( true );

        return (array) $method->invoke( $engine, $userId );
    }

    /**
     * Give the acting user a role the matrix does not know. UserManager::create
     * rejects one, so it is written to storage directly (reverted by the tier's
     * isolation); klytos_current_user() reads the record, so getAvailableTools()
     * sees 'superadmin'. The fix default-denies it to an empty list.
     */
    public function testAnUnknownRoleGetsAnEmptyAiToolList(): void
    {
        $viewer         = $this->users->getByUsername( 'viewer' );
        $viewer['role'] = 'superadmin';
        $this->storage->write( 'users', (string) $viewer['id'], $viewer );

        $this->actingAs( 'viewer' );

        $tools = $this->availableTools( $this->engineWithFullBaseList(), (int) $viewer['id'] );

        self::assertSame( [], $tools, 'an unrecognised role must get an empty AI tool list (default-deny)' );
    }

    /**
     * The positive control: an OWNER session gets a non-empty list from the same
     * setup, so the empty result above is the role handling and not a broken
     * fixture (L-008).
     */
    public function testAnOwnerGetsANonEmptyAiToolList(): void
    {
        $this->actingAs( 'owner' );
        $owner = $this->users->getByUsername( 'owner' );

        $tools = $this->availableTools( $this->engineWithFullBaseList(), (int) $owner['id'] );

        self::assertNotEmpty( $tools, 'an owner must get a non-empty AI tool list' );
    }

    /**
     * A viewer session still gets ONLY read-only tools — the advisory annotation
     * filter for a known lower role is preserved, not collapsed into the
     * default-deny branch.
     */
    public function testAViewerGetsOnlyReadOnlyTools(): void
    {
        $this->actingAs( 'viewer' );
        $viewer = $this->users->getByUsername( 'viewer' );

        $tools = $this->availableTools( $this->engineWithFullBaseList(), (int) $viewer['id'] );

        self::assertNotEmpty( $tools, 'a viewer must still get its read-only tools' );
        foreach ( $tools as $tool ) {
            $annotations = (array) ( $tool['annotations'] ?? [] );
            self::assertTrue(
                ( $annotations['readOnlyHint'] ?? false ) === true,
                'a viewer must be advertised read-only tools only'
            );
        }
    }
}
