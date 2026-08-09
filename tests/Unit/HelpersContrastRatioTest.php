<?php

/**
 * Klytos CMS — Helpers::contrastRatio() (Phase 4 Step 4, stage 5 — entry 3, Design).
 *
 * `SPEC/accessibility.md` §10.7 requires the theme editor to show the measured
 * ratio next to every text/background pair the theme defines, and to refuse to
 * save a pair below 4.5:1 without a recorded override — "computed with the same
 * method as `SPEC/color-contrast-audit.md`", which is the WCAG 2.x arithmetic:
 * sRGB → linearised channels → relative luminance → (L1 + 0.05) / (L2 + 0.05).
 *
 * The arithmetic is a pure function of two hex strings, which the project card's
 * `Test-first policy: pure-logic` puts squarely in the test-first set: these
 * tests were written BEFORE the method existed and observed failing.
 *
 * The reference values below are the WCAG standard's own fixed points (black on
 * white is exactly 21, any colour against itself is exactly 1) plus two pairs
 * recomputed independently at the Phase 4 gate (`docs/BUILD-SPEC.md` §1c), so
 * agreement here is agreement with the delivery's audit, not with this code.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\Helpers;
use Klytos\Tests\UnitTestCase;

final class HelpersContrastRatioTest extends UnitTestCase
{
    public function testBlackOnWhiteIsTheStandardMaximum(): void
    {
        $this->assertSame( 21.0, round( Helpers::contrastRatio( '#000000', '#ffffff' ), 2 ) );
    }

    public function testAColourAgainstItselfIsTheStandardMinimum(): void
    {
        $this->assertSame( 1.0, round( Helpers::contrastRatio( '#3b6ef5', '#3b6ef5' ), 2 ) );
    }

    /**
     * The ratio is symmetric: WCAG divides the lighter luminance by the darker
     * one, so argument order is not part of the result. A naive implementation
     * that always divides the FIRST by the second returns a value below 1 here.
     */
    public function testTheRatioIsSymmetric(): void
    {
        $this->assertSame(
            round( Helpers::contrastRatio( '#767676', '#ffffff' ), 4 ),
            round( Helpers::contrastRatio( '#ffffff', '#767676' ), 4 )
        );
    }

    /**
     * #767676 on white is the canonical WCAG boundary case: it is the darkest
     * grey that still passes 4.5:1 for normal text, at 4.54:1.
     */
    public function testTheCanonicalBoundaryGreyPassesNormalText(): void
    {
        $ratio = Helpers::contrastRatio( '#767676', '#ffffff' );

        $this->assertSame( 4.54, round( $ratio, 2 ) );
        $this->assertGreaterThanOrEqual( 4.5, $ratio );
    }

    /**
     * One shade lighter fails, which is what makes the boundary meaningful: a
     * guard built on a ratio that never crosses 4.5 would refuse nothing.
     */
    public function testOneShadeLighterThanTheBoundaryFailsNormalText(): void
    {
        $this->assertLessThan( 4.5, Helpers::contrastRatio( '#777777', '#ffffff' ) );
    }

    /**
     * Three-digit hex is expanded, not truncated: `#fff` is white, so it must
     * give the same 21 as `#ffffff`. An implementation that reads two
     * characters off the front returns the ratio for `#ff0000`-ish nonsense.
     */
    public function testThreeDigitHexIsExpanded(): void
    {
        $this->assertSame(
            round( Helpers::contrastRatio( '#000000', '#ffffff' ), 4 ),
            round( Helpers::contrastRatio( '#000', '#fff' ), 4 )
        );
    }

    /**
     * Case is not part of a colour.
     */
    public function testHexCaseIsIrrelevant(): void
    {
        $this->assertSame(
            round( Helpers::contrastRatio( '#AABBCC', '#112233' ), 4 ),
            round( Helpers::contrastRatio( '#aabbcc', '#112233' ), 4 )
        );
    }

    /**
     * Eight-digit hex carries an alpha channel this arithmetic cannot honour —
     * a translucent colour's real ratio depends on what is behind it, which a
     * two-argument function does not know. The alpha is ignored and the RGB
     * used, which is stated here rather than left for a reader to discover.
     */
    public function testEightDigitHexIgnoresTheAlphaChannel(): void
    {
        $this->assertSame(
            round( Helpers::contrastRatio( '#000000', '#ffffff' ), 4 ),
            round( Helpers::contrastRatio( '#000000ff', '#ffffff80' ), 4 )
        );
    }

    /**
     * A value that is not a colour is not silently treated as black — that
     * would report 21:1 for a typo and pass a guard whose whole purpose is to
     * fail. The refusal is a thrown error the caller must handle.
     */
    public function testAnInvalidColourIsRefused(): void
    {
        $this->expectException( \InvalidArgumentException::class );

        Helpers::contrastRatio( 'rebeccapurple', '#ffffff' );
    }
}
