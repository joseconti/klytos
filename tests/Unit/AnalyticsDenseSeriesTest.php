<?php

/**
 * Klytos CMS — `AnalyticsManager::denseDailyViews()` fills the gaps a chart cannot.
 *
 * Written for manifest entry 7 (Analytics), whose panel is a 30-day chart with
 * its `<details>` data table — `template-overview-stats.md` §4's pattern, built
 * once on entry 18 (D-112) and consumed here for the second time.
 *
 * `getSummary()` returns `daily_views` keyed by date, and it only carries days
 * that HAVE entries: a range with no traffic on the 3rd simply has no `03` key.
 * A chart drawn from that is not a chart with a gap — it is a chart that draws
 * the 2nd next to the 4th at the same spacing as any other pair and silently
 * MISREPRESENTS the shape of the traffic. Entry 18 avoided it by having
 * `getDailyRevenue( 30 )` build a dense series in the data layer; the analytics
 * side has no equivalent, and adding a second full-collection read to get one
 * would double the cost of the screen (`getEntriesInRange()` lists the whole
 * collection).
 *
 * So the densification is a **pure function of its inputs** — a sparse map plus
 * two dates in, a dense ordered series out, no storage and no clock. That is
 * exactly the class the project card's `Test-first policy: pure-logic` names,
 * so this test is written and seen FAILING before the method exists.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\AnalyticsManager;
use Klytos\Tests\UnitTestCase;

/**
 * A chart's series is only honest if every day in the range is in it.
 */
final class AnalyticsDenseSeriesTest extends UnitTestCase
{
    /** Every date in the range appears, in order, once. */
    public function testItEmitsOneRowPerDayOfTheRangeInclusive(): void
    {
        $series = AnalyticsManager::denseDailyViews( [], '2026-08-01', '2026-08-05' );

        $this->assertCount( 5, $series, 'a 5-day inclusive range has 5 rows' );
        $this->assertSame(
            ['2026-08-01', '2026-08-02', '2026-08-03', '2026-08-04', '2026-08-05'],
            array_column( $series, 'date' ),
            'the dates are consecutive and ascending'
        );
    }

    /** A day with no traffic is a measured ZERO, not an absent row. */
    public function testADayWithNoTrafficIsZeroAndNotMissing(): void
    {
        $series = AnalyticsManager::denseDailyViews(
            ['2026-08-02' => 7],
            '2026-08-01',
            '2026-08-03'
        );

        $this->assertSame( [0, 7, 0], array_column( $series, 'views' ) );
    }

    /** The counts that ARE present are carried through untouched. */
    public function testPresentCountsAreCarriedThroughUnchanged(): void
    {
        $series = AnalyticsManager::denseDailyViews(
            ['2026-08-01' => 12, '2026-08-02' => 0, '2026-08-03' => 340],
            '2026-08-01',
            '2026-08-03'
        );

        $this->assertSame( [12, 0, 340], array_column( $series, 'views' ) );
    }

    /**
     * A date OUTSIDE the range is dropped rather than appended.
     *
     * `getSummary()` filters by range already, so this cannot arrive from the
     * shipped caller — but `analytics.event` lets a plugin write the map, and a
     * series whose length does not match its own range breaks the chart's
     * geometry silently.
     */
    public function testDatesOutsideTheRangeAreDropped(): void
    {
        $series = AnalyticsManager::denseDailyViews(
            ['2026-07-31' => 99, '2026-08-01' => 5, '2026-08-09' => 99],
            '2026-08-01',
            '2026-08-02'
        );

        $this->assertCount( 2, $series );
        $this->assertSame( [5, 0], array_column( $series, 'views' ) );
    }

    /** A single-day range is one row, not zero and not two. */
    public function testASingleDayRangeIsOneRow(): void
    {
        $series = AnalyticsManager::denseDailyViews( ['2026-08-04' => 3], '2026-08-04', '2026-08-04' );

        $this->assertSame( [['date' => '2026-08-04', 'views' => 3]], $series );
    }

    /**
     * An inverted range answers EMPTY rather than looping or guessing.
     *
     * `?period=` is user input on this screen, and a caller that computes its
     * own `from`/`to` can invert them. An empty series draws the template's
     * empty state; a silently swapped one draws a chart nobody asked for.
     */
    public function testAnInvertedRangeIsEmpty(): void
    {
        $this->assertSame( [], AnalyticsManager::denseDailyViews( ['2026-08-04' => 3], '2026-08-05', '2026-08-01' ) );
    }

    /** A non-numeric count from a plugin filter becomes an int, never a string. */
    public function testCountsAreCoercedToIntegers(): void
    {
        $series = AnalyticsManager::denseDailyViews( ['2026-08-01' => '42'], '2026-08-01', '2026-08-01' );

        $this->assertSame( 42, $series[0]['views'] );
    }
}
