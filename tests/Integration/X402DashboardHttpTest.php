<?php

/**
 * Klytos CMS — manifest entry 18 (Agent payments) renders what §18 specifies.
 *
 * The SERVER-RENDERED half of entry 18's evidence, and the first evidence
 * anywhere in this build for `template-overview-stats.md` §4's **chart
 * pattern** — which is mandatory, is the only accessible chart pattern the
 * admin has, and will be consumed again by Analytics (entry 7). What is pinned
 * here is what makes it accessible: the `role="img"` and its headline, and a
 * real `<table>` carrying the SAME numbers, in the DOM, after it.
 *
 * Geometry, contrast and axe belong to the browser tier and are NOT claimed.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

/**
 * The Agent payments screen's server-rendered contract.
 */
final class X402DashboardHttpTest extends AdminHttpTestCase
{
    /** Its own slot in the reserved band; `KLYTOS_TEST_PORT_OFFSET` shifts the whole band. */
    protected static function serverPort(): int
    {
        return 8117;
    }

    /** @param array $args Extra arguments for the fixture, e.g. `['--off']`. */
    private static function runFixture( array $args = [] ): int
    {
        $cmd = escapeshellarg( PHP_BINARY ) . ' '
            . escapeshellarg( self::$repoRoot . '/tests/E2E/fixtures/reset-x402.php' );

        foreach ( $args as $arg ) {
            $cmd .= ' ' . escapeshellarg( (string) $arg );
        }

        exec( $cmd . ' 2>&1', $out, $code );

        return $code;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // A chart with no bars proves nothing about a chart. The fixture writes
        // a DETERMINISTIC series — seven transactions across three days, with an
        // unambiguous peak — because a random one makes the headline, which is
        // the whole accessible answer, unassertable.
        if ( self::runFixture() !== 0 ) {
            self::markTestSkipped( 'the x402 fixture could not seed' );
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::runFixture( ['--off'] );

        parent::tearDownAfterClass();
    }

    private function screen(): array
    {
        return $this->request( 'installer/admin/x402-dashboard.php', 'owner' );
    }

    public function testTheScreenRendersWithoutAFatal(): void
    {
        $body = $this->screen()['body'];

        self::assertStringNotContainsString( 'Fatal error', $body );
        self::assertStringNotContainsString( 'TypeError', $body );
        self::assertStringContainsString( '</body>', $body );
    }

    // ─── the stat row ─────────────────────────────────────────────

    public function testFourStatCardsAreBuiltAndSettlementLagIsNotAmongThem(): void
    {
        $body = $this->screen()['body'];

        foreach ( ['revenue', 'requests', 'agents', 'avg'] as $id ) {
            self::assertStringContainsString( 'data-testid="x402.stat.' . $id . '"', $body );
        }

        // FOUR, not §18's five. *Settlement lag* needs a settlement time and the
        // transaction record holds exactly one timestamp, `created_at`, with
        // `facilitator_ok` a bare bool beside it — so the lag is between one
        // point and nothing (DR-014). Asserting the count is what stops a fifth
        // card being built later from the manifest alone.
        self::assertSame( 4, substr_count( $body, 'data-testid="x402.stat.' ) );

        // And four is inside `template-overview-stats.md` §1's 3–5.
        self::assertGreaterThanOrEqual( 3, substr_count( $body, 'data-testid="x402.stat.' ) );
    }

    public function testTheStatsCountTheSeededPopulationAndNotSomethingElse(): void
    {
        $body = $this->screen()['body'];

        // Seven transactions, three distinct agents — read back off the page, so
        // the numbers are the product's own and not the fixture's arithmetic
        // repeated (L-035: a test that varies an input must read that input back).
        self::assertMatchesRegularExpression(
            '/data-testid="x402\.stat_value\.requests"[^>]*>\s*7\s*</',
            $body
        );
        self::assertMatchesRegularExpression(
            '/data-testid="x402\.stat_value\.agents"[^>]*>\s*3\s*</',
            $body
        );
    }

    public function testMoneyCarriesItsCurrencyAsText(): void
    {
        $body = $this->screen()['body'];

        // §18's delta: all money is numeric, right-aligned, **with the currency
        // as text** — never a bare glyph and never a colour.
        preg_match( '/data-testid="x402\.stat_value\.revenue"[^>]*>(.*?)<\/p>/s', $body, $m );
        self::assertStringContainsString( 'USD', trim( strip_tags( $m[1] ?? '' ) ) );
    }

    // ─── the chart pattern, which is the point of this screen ─────

    public function testTheChartIsAnImageWithAHeadlineRatherThanThirtyBarsToWalk(): void
    {
        $body = $this->screen()['body'];

        preg_match( '/<svg[^>]*class="k-chart-svg"[^>]*>/', $body, $m );
        $svg = $m[0] ?? '';

        self::assertNotSame( '', $svg, 'the chart must render with a seeded population' );
        self::assertStringContainsString( 'role="img"', $svg );

        // The headline carries the TOTAL and the PEAK — the answer a screen
        // reader needs without touching a single bar (§4).
        self::assertMatchesRegularExpression( '/aria-label="[^"]*USD[^"]*"/', $svg );
    }

    public function testTheChartSvgCarriesItsOwnWidthAndHeight(): void
    {
        $body = $this->screen()['body'];

        preg_match( '/<svg[^>]*class="k-chart-svg"[^>]*>/', $body, $m );
        $svg = $m[0] ?? '';

        // L-048, whose NINTH occurrence shipped one slice ago: an <svg> with no
        // width/height renders at the SVG default of 300 x 150. The stylesheet
        // sizes it too, but the attributes are what survive a missing
        // stylesheet — and a 300x150 chart is not a smaller chart, it is a
        // broken page.
        self::assertMatchesRegularExpression( '/\bwidth="\d+"/', $svg );
        self::assertMatchesRegularExpression( '/\bheight="\d+"/', $svg );
    }

    public function testARealTableFollowsTheChartInTheDomWithTheSameNumbers(): void
    {
        $body = $this->screen()['body'];

        $chartAt = strpos( $body, 'data-testid="x402.chart"' );
        $tableAt = strpos( $body, 'data-testid="x402.chart_table"' );

        self::assertIsInt( $chartAt );
        self::assertIsInt( $tableAt );
        self::assertGreaterThan( $chartAt, $tableAt, 'the table follows the chart in the DOM (§4)' );

        // Thirty days in, thirty rows out — the SAME numbers, not a summary.
        preg_match( '/data-testid="x402\.chart_table".*?<tbody>(.*?)<\/tbody>/s', $body, $m );
        self::assertSame( 30, substr_count( $m[1] ?? '', '<tr>' ) );
    }

    public function testTheTableIsOpenAtEveryWidthAndNeedsNoScript(): void
    {
        $body = $this->screen()['body'];

        // Adaptation 92: §4 opens the <details> below 900px and the SERVER HAS
        // NO VIEWPORT. Opening it always is the only option that does not make
        // the accessible path depend on JavaScript — which is the one thing §4
        // exists to prevent.
        self::assertMatchesRegularExpression(
            '/<details[^>]*class="k-chart-details"[^>]*\bopen\b/',
            $body
        );
    }

    // ─── the detail cards and the empty state ─────────────────────

    public function testBothDetailCardsRenderAsRealTables(): void
    {
        $body = $this->screen()['body'];

        foreach ( ['pages', 'agents'] as $id ) {
            self::assertStringContainsString( 'data-testid="x402.detail.' . $id . '"', $body );
        }

        // Every table on this screen carries a caption, per accessibility.md
        // §2.1 — including the two that are content rather than the chart's.
        self::assertGreaterThanOrEqual( 3, substr_count( $body, 'class="k-table-caption"' ) );
    }

    public function testWithNoPaymentsTheScreenAnswersInsteadOfShowingAnEmptyChart(): void
    {
        // REACHED, not skipped past: the population is removed, the screen is
        // read, and the population is put back.
        self::runFixture( ['--off'] );

        try {
            $body = $this->screen()['body'];

            self::assertStringContainsString( 'data-testid="x402.empty"', $body );
            self::assertStringContainsString( 'No agent payments yet', $body );

            // §2: `—` and `0` are different claims. A mean over no requests is
            // the absence of an answer, so the average price is a dash.
            self::assertMatchesRegularExpression(
                '/data-testid="x402\.stat_value\.avg"[^>]*>\s*—\s*</u',
                $body
            );

            // And no chart is drawn at all — an empty chart is worse than none.
            self::assertStringNotContainsString( 'data-testid="x402.chart"', $body );
        } finally {
            self::runFixture();
        }
    }

    public function testNoCatalogueKeyReachesTheScreen(): void
    {
        $body = strip_tags( $this->screen()['body'] );

        $catalogue = json_decode(
            (string) file_get_contents( self::$repoRoot . '/installer/lang/x402/en.json' ),
            true
        );

        $keys = array_keys( $catalogue['klytos-x402'] ?? [] );
        self::assertNotEmpty( $keys, 'the `klytos-x402` catalogue root must exist' );

        foreach ( $keys as $key ) {
            self::assertStringNotContainsString(
                'klytos-x402.' . $key,
                $body,
                "the key `klytos-x402.{$key}` reached the rendered page"
            );
        }
    }
}
