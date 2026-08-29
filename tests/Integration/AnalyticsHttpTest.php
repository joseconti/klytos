<?php

/**
 * Klytos CMS — the Analytics screen's server-rendered contract (manifest entry 7).
 *
 * The SECOND consumer of `template-overview-stats.md` §4's chart pattern. What
 * that pattern requires is already pinned once on entry 18
 * (`X402DashboardHttpTest`) and is deliberately NOT re-asserted here in full;
 * what IS asserted here is the part that is this screen's own — the four stat
 * cards it can honestly measure, the period chips, the dense series that gives
 * the chart one row per day whether or not that day had traffic, and both
 * detail cards.
 *
 * The one pattern rule this file does repeat is the DOM ORDER of chart and
 * table, because the pattern is now consumed twice and a second consumer that
 * quietly reversed them would be exactly the drift the "built once" rule exists
 * to prevent.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

/**
 * The Analytics screen against a known, deterministic population.
 */
final class AnalyticsHttpTest extends AdminHttpTestCase
{
    /** Its own slot in the reserved band; `KLYTOS_TEST_PORT_OFFSET` shifts the whole band. */
    protected static function serverPort(): int
    {
        return 8118;
    }

    /** @param array $args Extra arguments for the fixture, e.g. `['--off']`. */
    private static function runFixture( array $args = [] ): int
    {
        $cmd = escapeshellarg( PHP_BINARY ) . ' '
            . escapeshellarg( self::$repoRoot . '/tests/E2E/fixtures/reset-analytics.php' );

        foreach ( $args as $arg ) {
            $cmd .= ' ' . escapeshellarg( (string) $arg );
        }

        exec( $cmd . ' 2>&1', $out, $code );

        return $code;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // 12 pageviews across four days of a 30-day window, one unambiguous
        // peak. Deterministic on purpose: the chart's accessible headline names
        // the total and the peak, and a random population makes the one thing a
        // screen reader is given unassertable (D-112).
        if ( self::runFixture() !== 0 ) {
            self::fail( 'the analytics fixture could not seed its population' );
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::runFixture( ['--off'] );

        parent::tearDownAfterClass();
    }

    private function page( string $query = '' ): string
    {
        $response = $this->request( '/installer/admin/analytics.php' . $query, 'owner' );

        $this->assertSame( 200, $response['status'], 'the screen answers 200 for the owner' );

        return $response['body'];
    }

    // ─── The stat row ────────────────────────────────────────────

    /**
     * FOUR cards, asserted BY COUNT.
     *
     * The count is the assertion that matters: §7 names five, three of which
     * have no source in the product at all (DR-015), so a later session reading
     * only the manifest could add *Avg. time*, *Bounce* or *Agent hits* back as
     * a number nobody measured. The count refuses that without needing anyone to
     * remember why. It is also the template's own 3–5 floor, which the two
     * §7-named cards alone would break.
     */
    public function testTheStatRowHasExactlyFourCardsAndNoUnbackedFifth(): void
    {
        $html = $this->page();

        $this->assertSame(
            4,
            substr_count( $html, 'data-testid="analytics.stat.' ),
            'four stat cards — never a fifth built from the manifest alone'
        );

        foreach ( ['views', 'visitors', 'avg', 'pages'] as $id ) {
            $this->assertStringContainsString( 'data-testid="analytics.stat.' . $id . '"', $html );
        }

        foreach ( ['time', 'bounce', 'agent'] as $absent ) {
            $this->assertStringNotContainsString(
                'data-testid="analytics.stat.' . $absent . '"',
                $html,
                $absent . ' has no source in the product and must not be drawn'
            );
        }
    }

    /** Each card binds its value to its label, so it reads as one sentence. */
    public function testEachStatCardIsLabelledByItsOwnValueAndLabel(): void
    {
        $html = $this->page();

        foreach ( ['views', 'visitors', 'avg', 'pages'] as $id ) {
            $this->assertMatchesRegularExpression(
                '/aria-labelledby="[^"]*analytics-stat-' . $id . '-value[^"]*analytics-stat-' . $id . '-label/',
                $html,
                $id . ' binds value then label'
            );
        }
    }

    /**
     * The measured figures are the fixture's, read back OFF THE PAGE.
     *
     * 12 pageviews and 5 distinct visitor identities — deliberately different
     * numbers, so a card showing the wrong one cannot pass by coincidence
     * (L-035).
     */
    public function testTheFiguresAreTheOnesTheFixtureWrote(): void
    {
        $html = $this->page( '?period=30d' );

        $this->assertSame( '12', $this->statValue( $html, 'views' ) );

        /*
         * SIX, not the five identities the fixture uses — and the difference is
         * the whole point of the note on that card. The visitor hash is salted
         * per DAY, so `v3` visiting on day -7 and again on day -1 is two
         * distinct hashes. Six is therefore the count of visitor-DAYS, which is
         * what the product measures and what the card's supporting line says it
         * measures. This test asserted `5` on its first run and the product was
         * right; the expectation was the thing that was wrong, so it is corrected
         * here with its reason rather than the screen being changed to match it.
         */
        $this->assertSame( '6', $this->statValue( $html, 'visitors' ) );

        // Three distinct paths in the population: /, /about, /pricing.
        $this->assertSame( '3', $this->statValue( $html, 'pages' ) );

        // 12 views over 30 days.
        $this->assertSame( '0.4', $this->statValue( $html, 'avg' ) );
    }

    /**
     * The *Visitors* card carries the note that says what it really counts.
     *
     * The visitor hash is salted with a salt that ROTATES DAILY, so over a range
     * the figure is distinct visitor-DAYS and not distinct people. §7 names the
     * card "Visitors"; the label stays as the delivery writes it and the note
     * carries the meaning, rather than the card making a claim the data does not
     * support.
     */
    public function testTheVisitorsCardStatesWhatItActuallyCountsOverARange(): void
    {
        $this->assertStringContainsString(
            'analytics-stat-visitors-note',
            $this->page( '?period=30d' ),
            'a multi-day range carries the visitor-day note'
        );

        // Over a single day the caveat does not apply and is not shown — a note
        // that is always there is a note nobody reads.
        $this->assertStringNotContainsString(
            'analytics-stat-visitors-note',
            $this->page( '?period=24h' ),
            'a one-day range has nothing to caveat'
        );
    }

    // ─── The period chips ────────────────────────────────────────

    /** The chips are LINKS carrying aria-current, never tabs and never buttons. */
    public function testThePeriodChipsAreLinksWithAriaCurrentAndNoTabRole(): void
    {
        $html = $this->page( '?period=7d' );

        $this->assertSame( 4, substr_count( $html, 'data-testid="analytics.chip.' ) );
        $this->assertMatchesRegularExpression(
            '/<a[^>]*analytics\.php\?period=7d[^>]*aria-current="true"/',
            $html,
            'the selected chip is the current one'
        );
        $this->assertSame(
            1,
            substr_count( $html, 'aria-current="true"' ),
            'exactly one chip is current'
        );

        $chipBlock = substr( $html, (int) strpos( $html, 'data-testid="analytics.periods"' ), 1400 );
        $this->assertStringNotContainsString( 'role="tab"', $chipBlock );
        $this->assertStringNotContainsString( '<button', $chipBlock );
    }

    /**
     * An unknown period resolves to the default rather than failing.
     *
     * A bookmark from an older version is not an error condition — the same call
     * entry 13 made for `?status=`.
     */
    public function testAnUnknownPeriodFallsBackToTheDefaultAndStillAnswers200(): void
    {
        $html = $this->page( '?period=all-time-please' );

        $this->assertMatchesRegularExpression(
            '/<a[^>]*analytics\.php\?period=30d[^>]*aria-current="true"/',
            $html,
            'an unknown period lands on 30d'
        );
    }

    // ─── The chart and its table ─────────────────────────────────

    /**
     * The chart is `role="img"` and the headline in its `aria-label` carries the
     * numbers, so a screen reader gets the answer without walking 30 points.
     */
    public function testTheChartCarriesItsHeadlineInItsAccessibleName(): void
    {
        $html = $this->page( '?period=30d' );

        $this->assertMatchesRegularExpression(
            '/<svg[^>]*role="img"[^>]*aria-label="[^"]*12[^"]*6[^"]*"/',
            $html,
            'the accessible name names the total and the peak'
        );
    }

    /**
     * THE DENSE SERIES, and it is why this screen has a manager method of its own.
     *
     * `getSummary()`'s `daily_views` holds only the four days that HAVE traffic.
     * A chart drawn from that draws four points evenly spaced and silently
     * misrepresents a month. The table is the chart's own numbers, so counting
     * its rows is counting the chart's points.
     */
    public function testTheDataTableHasOneRowPerDayOfTheRangeNotPerDayWithTraffic(): void
    {
        $html  = $this->page( '?period=30d' );
        $table = $this->between( $html, 'data-testid="analytics.chart_table"', '</table>' );

        $this->assertSame(
            30,
            substr_count( $table, '<tr>' ) - 1, // minus the header row
            'thirty rows for thirty days, not four for the four days with traffic'
        );

        // And the zeroes are really there, not blanks.
        $this->assertGreaterThanOrEqual(
            20,
            substr_count( $table, '>0</td>' ),
            'the days with no traffic are a measured zero'
        );
    }

    /** The table FOLLOWS the chart in the DOM — the pattern's second rule. */
    public function testTheTableFollowsTheChartInTheDom(): void
    {
        $html = $this->page( '?period=30d' );

        $chart = strpos( $html, 'data-testid="analytics.chart"' );
        $table = strpos( $html, 'data-testid="analytics.chart_table"' );

        $this->assertIsInt( $chart, 'the chart is on the page' );
        $this->assertIsInt( $table, 'the table is on the page' );
        $this->assertGreaterThan( $chart, $table, 'the table comes after the chart' );
    }

    // ─── The detail cards ────────────────────────────────────────

    /** Both §7 detail cards are real captioned tables over the fixture's data. */
    public function testBothDetailCardsRenderTheirRows(): void
    {
        $html = $this->page( '?period=30d' );

        $pages = $this->between( $html, 'data-testid="analytics.detail.pages"', '</section>' );
        $this->assertStringContainsString( '/pricing', $pages );
        $this->assertStringContainsString( '<caption', $pages );

        $refs = $this->between( $html, 'data-testid="analytics.detail.referrers"', '</section>' );
        $this->assertStringContainsString( 'duckduckgo.com', $refs );

        // The blank referrer of a direct visit is filtered out by getSummary(),
        // so it must not arrive as an empty row header.
        $this->assertStringNotContainsString( '<th scope="row"></th>', $refs );
    }

    /**
     * The empty state is REACHED, not skipped past.
     *
     * It is §7's own sentence and it carries its action. A state nobody renders
     * is a state nobody has tested (D-111).
     */
    public function testTheEmptyStateIsReachedWhenThereIsNoTraffic(): void
    {
        self::runFixture( ['--off'] );

        try {
            $html = $this->page( '?period=30d' );

            $this->assertStringContainsString( 'data-testid="analytics.empty"', $html );
            $this->assertStringContainsString( 'data-testid="analytics.empty_action"', $html );
            $this->assertStringNotContainsString( 'data-testid="analytics.chart"', $html );

            // `—`, never `0`: a mean over no traffic is the absence of an answer.
            $this->assertSame( '—', $this->statValue( $html, 'avg' ) );
            $this->assertSame( '0', $this->statValue( $html, 'views' ) );
        } finally {
            self::runFixture();
        }
    }

    // ─── i18n ────────────────────────────────────────────────────

    /**
     * No catalogue KEY reaches the page.
     *
     * Checked against the catalogue's own key list rather than by grepping for a
     * dotted string: `analytics.php` and `analytics.view` are both legitimately
     * present in hrefs and testids, and neither is copy (D-111).
     */
    public function testNoCatalogueKeyIsPrintedAsCopy(): void
    {
        $html = $this->page( '?period=30d' );

        $catalogue = json_decode(
            (string) file_get_contents( self::$repoRoot . '/installer/core/lang/en.json' ),
            true
        );

        $this->assertIsArray( $catalogue['analytics'] ?? null, 'the analytics root exists' );

        foreach ( array_keys( $catalogue['analytics'] ) as $key ) {
            $this->assertStringNotContainsString(
                '>analytics.' . $key . '<',
                $html,
                'analytics.' . $key . ' rendered as its own key — the catalogue lookup failed'
            );
        }
    }

    // ─── helpers ─────────────────────────────────────────────────

    private function statValue( string $html, string $id ): string
    {
        $needle = 'data-testid="analytics.stat_value.' . $id . '"';
        $at     = strpos( $html, $needle );

        $this->assertIsInt( $at, 'stat value ' . $id . ' is on the page' );

        $chunk = substr( $html, $at, 400 );
        preg_match( '/>\s*([^<]+?)\s*</', $chunk, $m );

        return trim( html_entity_decode( $m[1] ?? '' ) );
    }

    private function between( string $html, string $start, string $end ): string
    {
        $from = strpos( $html, $start );
        $this->assertIsInt( $from, $start . ' is on the page' );

        $to = strpos( $html, $end, $from );
        $this->assertIsInt( $to, $end . ' closes it' );

        return substr( $html, $from, $to - $from );
    }
}
