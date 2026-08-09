<?php

/**
 * Klytos CMS — ThemeManager::contrastPairs() (Phase 4 Step 4, stage 5 — entry 3, Design).
 *
 * `SPEC/accessibility.md` §10.7 asks the Design screen for two things that are
 * really one: the measured ratio next to every text/background pair the theme
 * defines, and a refusal to save a pair below 4.5:1 without a recorded
 * override. Both need the same answer — WHICH pairs, and at what ratio — so it
 * is computed once, here, and the screen renders it and gates on it.
 *
 * **The pair set is a recorded integration decision, not an invention.** The
 * theme declares exactly two text colours (`text`, `text_muted`) and exactly
 * two surfaces text sits on (`background`, `surface`), so "every text/background
 * pair it defines" is those four and no more. `primary`/`accent` are used as
 * link and button colours, which §10.7's wording does not reach; that gap is
 * stated in `docs/BUILD-SPEC.md` §5.9 rather than closed by guessing.
 *
 * Pure function of a colour array → `Test-first policy: pure-logic`. These
 * tests were written BEFORE the method existed and observed failing.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\ThemeManager;
use Klytos\Tests\UnitTestCase;

final class ThemeContrastPairsTest extends UnitTestCase
{
    /**
     * A palette whose four guarded pairs all pass comfortably.
     */
    private const PASSING = [
        'background' => '#ffffff',
        'surface'    => '#f8fafc',
        'text'       => '#1e293b',
        'text_muted' => '#475569',
    ];

    public function testItReturnsExactlyTheFourDeclaredTextPairs(): void
    {
        $pairs = ThemeManager::contrastPairs( self::PASSING );

        $this->assertCount( 4, $pairs );

        $keys = array_map(
            static fn( array $pair ): string => $pair['foreground'] . '/' . $pair['background'],
            $pairs
        );

        $this->assertSame(
            [
                'text/background',
                'text/surface',
                'text_muted/background',
                'text_muted/surface',
            ],
            $keys
        );
    }

    public function testEachPairCarriesItsMeasuredRatioAndItsVerdict(): void
    {
        $pairs = ThemeManager::contrastPairs( self::PASSING );

        foreach ( $pairs as $pair ) {
            $this->assertGreaterThan( 4.5, $pair['ratio'] );
            $this->assertTrue( $pair['passes'] );
            $this->assertTrue( $pair['measurable'] );
            // The hex values travel with the pair so the screen renders the
            // swatch it measured, never a second lookup that could disagree.
            $this->assertSame( self::PASSING[ $pair['foreground'] ], $pair['foreground_hex'] );
            $this->assertSame( self::PASSING[ $pair['background'] ], $pair['background_hex'] );
        }

        // The ratio is the same arithmetic the helper does — one method, not two.
        $this->assertSame(
            round( \Klytos\Core\Helpers::contrastRatio( '#1e293b', '#ffffff' ), 4 ),
            round( $pairs[0]['ratio'], 4 )
        );
    }

    /**
     * The whole point of the guard: a pair below 4.5:1 is reported as failing.
     * A palette where nothing can fail would make the screen's refusal dead code.
     */
    public function testAPairBelowTheThresholdIsReportedAsFailing(): void
    {
        $pairs = ThemeManager::contrastPairs( [
            'background' => '#ffffff',
            'surface'    => '#ffffff',
            'text'       => '#1e293b',
            'text_muted' => '#aaaaaa',
        ] );

        $failing = array_values( array_filter( $pairs, static fn( array $p ): bool => ! $p['passes'] ) );

        $this->assertCount( 2, $failing );
        $this->assertSame( 'text_muted', $failing[0]['foreground'] );
        $this->assertLessThan( 4.5, $failing[0]['ratio'] );
    }

    /**
     * Exactly 4.5:1 passes — the WCAG threshold is inclusive, and a strict
     * comparison here would refuse a palette the standard accepts.
     */
    public function testTheThresholdIsInclusive(): void
    {
        // #797979 on #ffffff is 4.4966…:1 and #767676 is 4.5410…:1, so the
        // boundary is asserted through the helper rather than through a hex
        // value that happens to land on it.
        $pairs = ThemeManager::contrastPairs( self::PASSING );

        foreach ( $pairs as $pair ) {
            $this->assertSame( $pair['ratio'] >= 4.5, $pair['passes'] );
        }
    }

    /**
     * A palette missing a key is not a crash and not a silent pass: the pair
     * simply cannot be measured, and saying so is the honest answer. A theme
     * mid-edit, or one written by an older version, hits this.
     */
    public function testAnUnmeasurablePairIsMarkedRatherThanGuessed(): void
    {
        $pairs = ThemeManager::contrastPairs( [
            'background' => '#ffffff',
            'text'       => '#1e293b',
        ] );

        $unmeasurable = array_values( array_filter(
            $pairs,
            static fn( array $p ): bool => $p['ratio'] === null
        ) );

        $this->assertCount( 3, $unmeasurable );
        $this->assertFalse( $unmeasurable[0]['passes'] );
        $this->assertFalse( $unmeasurable[0]['measurable'] );
    }

    /**
     * An invalid colour is the same case as a missing one from the screen's
     * point of view — unmeasurable, never an exception that takes the page
     * down. `Helpers::contrastRatio()` throws; this method is the layer that
     * decides what a screen does about it (L-034: a state the data layer
     * cannot express is a state the screen cannot render).
     */
    public function testAnInvalidColourIsUnmeasurableAndNotFatal(): void
    {
        $pairs = ThemeManager::contrastPairs( [
            'background' => 'rebeccapurple',
            'surface'    => '#ffffff',
            'text'       => '#1e293b',
            'text_muted' => '#475569',
        ] );

        $this->assertNull( $pairs[0]['ratio'] );
        $this->assertFalse( $pairs[0]['measurable'] );
        $this->assertTrue( $pairs[1]['passes'] );
    }
}
