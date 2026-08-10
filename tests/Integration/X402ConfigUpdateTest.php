<?php

/**
 * Klytos CMS — `X402\Config::update()` lets a list SHRINK.
 *
 * FOUND WHILE SURVEYING manifest entry 37 (x402 settings) against the manager
 * that backs it, before the first line of the screen was written.
 *
 * `update()` merges with `array_replace_recursive()`, which merges arrays
 * INDEX BY INDEX:
 *
 *     array_replace_recursive( ['A', 'B'], ['A'] )  →  ['A', 'B']
 *
 * Every scalar setting on the screen therefore behaves, and every LIST setting
 * is one-way. `custom_bot_user_agents` is the list the screen edits: the shipped
 * settings screen posts the whole textarea as the new list, so adding an agent
 * works and REMOVING one does nothing at all — the row comes back on the next
 * page load, the save reported success, and nothing anywhere says why.
 *
 * WHAT IT COSTS, which is more than an annoyance: this list decides who meets
 * the paywall. `Config::getBotUserAgents()` merges it into the detector's match
 * set and `HtaccessWriter` compiles it into the rewrite rules at every build. An
 * agent added by mistake — a typo that matches a real browser's user agent, a
 * partner's crawler added for a trial — can be added by anyone with
 * `site.configure` and removed by nobody through any surface in the product.
 * The same method backs `klytos_x402_update_config` over MCP, so the AI
 * interface cannot undo it either.
 *
 * These tests were written BEFORE the fix and observed FAILING against it — the
 * unconditional rule that a bug fix starts from a reproduction test, which holds
 * at every value of `Test-first policy:`. The failure line is recorded in
 * `docs/05-test-points.md`.
 *
 * The integration tier and not the unit tier, for L-041's recorded reason:
 * `Config::getAll()` reads through `klytos_get_option()`, which resolves
 * `App::getOptionsManager()`, so a red raised in `tests/Unit` would name the
 * absent App rather than the defect. A red you did not read is not a red first.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Core\X402\Config;
use Klytos\Tests\IntegrationTestCase;

final class X402ConfigUpdateTest extends IntegrationTestCase
{
    private function freshConfig(): Config
    {
        // A NEW instance every time, so every assertion reads what was STORED
        // rather than the in-memory array the setter happened to keep. The
        // screen reads it on the next request, which is a fresh instance by
        // definition.
        return new Config();
    }

    // ─── The reproduction (the defect) ───────────────────────────

    public function testACustomBotAgentCanBeRemovedAgain(): void
    {
        $this->freshConfig()->update( ['custom_bot_user_agents' => ['AlphaBot', 'BetaBot']] );
        $this->freshConfig()->update( ['custom_bot_user_agents' => ['AlphaBot']] );

        $this->assertSame(
            ['AlphaBot'],
            array_values( $this->freshConfig()->getAll()['custom_bot_user_agents'] ),
            'update() merged the list index by index, so a removed agent survives '
            . 'the save that removed it and keeps meeting the paywall.'
        );
    }

    public function testAListCanBeEmptiedCompletely(): void
    {
        $this->freshConfig()->update( ['custom_bot_user_agents' => ['AlphaBot', 'BetaBot']] );
        $this->freshConfig()->update( ['custom_bot_user_agents' => []] );

        $this->assertSame(
            [],
            $this->freshConfig()->getAll()['custom_bot_user_agents'],
            'The last agent could not be removed: an empty post left the stored list intact.'
        );
    }

    public function testTheDetectorStopsMatchingARemovedAgent(): void
    {
        // The list is not the point; what it DOES is. This asserts the fix at
        // the boundary that actually decides who pays, so a later refactor of
        // the merge cannot quietly re-open the hole.
        $this->freshConfig()->update( ['custom_bot_user_agents' => ['KlytosTestsAgent']] );
        $this->assertContains( 'KlytosTestsAgent', $this->freshConfig()->getBotUserAgents() );

        $this->freshConfig()->update( ['custom_bot_user_agents' => []] );
        $this->assertNotContains( 'KlytosTestsAgent', $this->freshConfig()->getBotUserAgents() );
    }

    // ─── The other direction: what must NOT change ───────────────

    public function testAPartialScalarWriteStillLeavesTheOtherSettingsAlone(): void
    {
        /*
         * One direction is half a test (L-010). `update()` is documented as a
         * partial update and both the screen and `klytos_x402_update_config`
         * depend on it: the fix replaces LIST values wholesale and must not
         * turn the method into a wholesale replacement of everything else.
         */
        $this->freshConfig()->update( [
            'wallet_address'    => '0xabc',
            'default_price_usd' => '0.05',
        ] );
        $this->freshConfig()->update( ['network' => 'polygon'] );

        $stored = $this->freshConfig()->getAll();

        $this->assertSame( '0xabc', $stored['wallet_address'] );
        $this->assertSame( '0.05', $stored['default_price_usd'] );
        $this->assertSame( 'polygon', $stored['network'] );
    }

    public function testAStringKeyedBranchStillMergesRatherThanReplacing(): void
    {
        // `license` is an associative branch, not a list. Writing one of its
        // members must not blank the other — that is the recursive merge doing
        // exactly what it is there for, and the fix is scoped to lists so that
        // it survives.
        $this->freshConfig()->update( ['license' => ['default_type' => 'training', 'default_text' => 'Keep me']] );
        $this->freshConfig()->update( ['license' => ['default_type' => 'full']] );

        $stored = $this->freshConfig()->getAll()['license'];

        $this->assertSame( 'full', $stored['default_type'] );
        $this->assertSame( 'Keep me', $stored['default_text'] );
    }
}
