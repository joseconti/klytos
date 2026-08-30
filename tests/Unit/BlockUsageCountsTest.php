<?php

/**
 * Klytos CMS — `PageTemplateManager::blockUsageCounts()`.
 *
 * Manifest entry 21's tile draws "wireframe preview 120px · block name ·
 * category · **usage count**", and `BlockManager` tracks no usage of any kind —
 * there is no `block-usage` collection and nothing counts references.
 *
 * But the count is not unmeasurable, which is what separates this from entry
 * 13's due dates and entry 18's settlement lag: a page TEMPLATE holds block ids
 * (`PageTemplateManager::addBlock()`), and that is the direct relationship the
 * product actually stores. So the figure is real, it is counted once for every
 * block rather than once per tile, and **what it counts is stated on the screen**
 * — templates, not pages — with the ambiguity raised as DR-016 rather than
 * settled by whoever built the tile.
 *
 * It lives on `PageTemplateManager` and not on `BlockManager` because the
 * dependency already runs that way: templates know their blocks, blocks know
 * nothing about templates, and inverting that to put the method on the more
 * convenient class would be a cycle.
 *
 * A function of stored records and nothing else — `pure-logic` under the card's
 * test-first policy, so it is written and seen failing first.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\BlockManager;
use Klytos\Core\PageTemplateManager;
use Klytos\Tests\UnitTestCase;

/**
 * How many templates use each block.
 */
final class BlockUsageCountsTest extends UnitTestCase
{
    private PageTemplateManager $templates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templates = new PageTemplateManager( $this->storage, new BlockManager( $this->storage ) );
    }

    /**
     * Create a template and add its blocks THROUGH THE PRODUCT'S OWN WRITER.
     *
     * This helper used to `write()` a record carrying a plain `blocks` list of
     * ids — a shape nothing in the product produces. The test passed and every
     * count on the real screen was ZERO, because `addBlock()` writes
     * `structure` as a list of `['block_id' => …, 'order' => …]`. It was the
     * DRIVEN screen that caught it (D-120).
     *
     * A fixture that invents the shape it is testing against proves only that
     * the code agrees with the fixture — L-051's family, and the reason this one
     * now goes through `save()` and `addBlock()`.
     */
    private function seedTemplate( string $type, array $blockIds ): void
    {
        $this->templates->save( ['type' => $type, 'name' => ucfirst( $type )] );

        foreach ( $blockIds as $blockId ) {
            $this->templates->addBlock( $type, $blockId );
        }
    }

    public function testItCountsTheTemplatesThatUseEachBlock(): void
    {
        $this->seedTemplate( 'landing', ['hero', 'features', 'cta'] );
        $this->seedTemplate( 'about', ['hero', 'cta'] );
        $this->seedTemplate( 'contact', ['cta'] );

        $counts = $this->templates->blockUsageCounts();

        $this->assertSame( 2, $counts['hero'] ?? null );
        $this->assertSame( 1, $counts['features'] ?? null );
        $this->assertSame( 3, $counts['cta'] ?? null );
    }

    /**
     * A block used TWICE in one template counts that template ONCE.
     *
     * The figure answers "how many templates use this", and a template that
     * happens to place a block twice is still one template. Counting the
     * placements instead would make the number mean something else while looking
     * identical on the tile.
     */
    public function testATemplateUsingABlockTwiceCountsOnce(): void
    {
        $this->seedTemplate( 'landing', ['hero', 'cta', 'hero'] );

        $this->assertSame( 1, $this->templates->blockUsageCounts()['hero'] ?? null );
    }

    /** A block nothing uses is ABSENT, so the caller decides how to draw zero. */
    public function testAnUnusedBlockIsAbsentRatherThanZero(): void
    {
        $this->seedTemplate( 'landing', ['hero'] );

        $this->assertArrayNotHasKey( 'orphan', $this->templates->blockUsageCounts() );
    }

    /** No templates at all is an empty map, not an error. */
    public function testNoTemplatesIsAnEmptyMap(): void
    {
        $this->assertSame( [], $this->templates->blockUsageCounts() );
    }

    /** A template with no blocks contributes nothing and breaks nothing. */
    public function testATemplateWithNoBlocksIsHarmless(): void
    {
        $this->seedTemplate( 'empty', [] );
        $this->seedTemplate( 'landing', ['hero'] );

        $this->assertSame( ['hero' => 1], $this->templates->blockUsageCounts() );
    }

    /**
     * A malformed `blocks` value does not take the screen down.
     *
     * The collection is writable by MCP and by plugins, so a string where a list
     * belongs is reachable — and a gallery that fatals on one bad record shows
     * nobody their blocks.
     */
    public function testAMalformedBlocksValueIsIgnored(): void
    {
        $this->storage->write( 'page-templates', 'broken', [
            'type'      => 'broken',
            'structure' => 'not-a-list',
        ] );
        $this->seedTemplate( 'landing', ['hero'] );

        $this->assertSame( ['hero' => 1], $this->templates->blockUsageCounts() );
    }
}
