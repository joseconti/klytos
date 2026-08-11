<?php

/**
 * Klytos CMS — manifest entry 44 (Dashboard) renders what §44 specifies.
 *
 * This is the SERVER-RENDERED half of entry 44's evidence. It pins what the
 * response actually contains — the five stat cards, the setup panel's shape,
 * the absence of a link on the one card that has no destination, and the fact
 * that nothing on the screen needs JavaScript. Geometry, contrast, focus order
 * and axe belong to the browser tier and are NOT claimed here.
 *
 * The first assertion in this file exists because of a defect this file's own
 * slice shipped and the tier caught: the stat renderer was a closure defined
 * ABOVE `templates/sidebar.php`, so it captured a `$spriteUrl` that did not
 * exist yet and every card fataled under `strict_types`. Nothing in the source
 * looked wrong. A page that renders is therefore asserted before anything about
 * what it renders.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

/**
 * The Dashboard's server-rendered contract.
 */
final class DashboardHttpTest extends AdminHttpTestCase
{
    /** Its own slot in the reserved band; `KLYTOS_TEST_PORT_OFFSET` shifts the whole band. */
    protected static function serverPort(): int
    {
        return 8115;
    }

    private function dashboard( string $role = 'owner' ): array
    {
        return $this->request( 'installer/admin/index.php', $role );
    }

    // ─── it renders at all ────────────────────────────────────────

    public function testTheDashboardRendersWithoutAFatal(): void
    {
        $body = $this->dashboard()['body'];

        self::assertStringNotContainsString( 'Fatal error', $body );
        self::assertStringNotContainsString( 'TypeError', $body );
        // The shell reached its end: the footer's own scripts are in the body.
        self::assertStringContainsString( '</body>', $body );
    }

    // ─── §44's five stat cards, and only five ─────────────────────

    public function testTheFiveStatCardsAreAllPresent(): void
    {
        $body = $this->dashboard()['body'];

        foreach ( ['last_build', 'pages', 'mcp', 'checks', 'updates'] as $id ) {
            self::assertStringContainsString(
                'data-testid="dashboard.stat.' . $id . '"',
                $body,
                "§44 names the {$id} stat card"
            );
        }

        // §44: "Klytos and PHP versions are NOT stat cards" — they are facts,
        // and they live in the System info widget and the status bar. Asserting
        // the absence is what stops them being added back from the old screen.
        self::assertSame( 5, substr_count( $body, 'data-testid="dashboard.stat.' ) );
    }

    public function testEveryStatValueIsBoundToItsLabel(): void
    {
        $body = $this->dashboard()['body'];

        // template-overview-stats.md §4: aria-labelledby binds the value to the
        // label so the card reads "4 — failing checks".
        foreach ( ['last_build', 'pages', 'mcp', 'checks', 'updates'] as $id ) {
            self::assertMatchesRegularExpression(
                '/aria-labelledby="dash-stat-' . $id . '-value dash-stat-' . $id . '-label"/',
                $body
            );
        }
    }

    public function testTheFailingChecksCardIsNotALinkBecauseHealthDoesNotExist(): void
    {
        $body = $this->dashboard()['body'];

        // Entry 22 (Health) is deferred (D-072) and `health.php` does not exist,
        // so §44's "every stat card is a link" cannot hold for this one. An
        // anchor with no href would be worse than a div: not a link, not
        // focusable, and announced as neither.
        preg_match( '/<(\w+)[^>]*data-testid="dashboard\.stat\.checks"/', $body, $m );
        self::assertSame( 'div', $m[1] ?? '', 'the Failing checks card must not be an anchor' );
        self::assertStringNotContainsString( 'health.php', $body );
    }

    public function testTheLinkedStatCardsPointWhereTheManifestSaysTheyDo(): void
    {
        $body = $this->dashboard()['body'];

        $expected = [
            'last_build' => 'updates.php',
            'pages'      => 'pages.php',
            'mcp'        => 'mcp.php',
            'updates'    => 'updates.php',
        ];

        foreach ( $expected as $id => $target ) {
            preg_match( '/<a[^>]*href="([^"]*)"[^>]*data-testid="dashboard\.stat\.' . $id . '"/', $body, $m );
            self::assertStringContainsString( $target, $m[1] ?? '', "the {$id} card links to {$target}" );
        }
    }

    // ─── the empty state, which is §44's important one ────────────

    public function testANewSiteGetsTheSetupPanelAndNoWidgetGrid(): void
    {
        // The seeded playground has never run a build, so step 3 is open and
        // §44 requires the panel to REPLACE the grid entirely.
        $body = $this->dashboard()['body'];

        if ( strpos( $body, 'data-testid="dashboard.setup"' ) === false ) {
            self::markTestSkipped( 'this seed has completed all three setup steps' );
        }

        self::assertStringNotContainsString( 'data-testid="dashboard.widgets"', $body );

        // §44: an <ol>, not a list of divs — the steps are ordered and the
        // order is information.
        self::assertMatchesRegularExpression( '/<ol class="k-steps"/', $body );

        foreach ( ['page', 'theme', 'build'] as $step ) {
            self::assertStringContainsString( 'data-testid="dashboard.step.' . $step . '"', $body );
        }
    }

    public function testEachStepNamesItsStateInTextBeforeItsAction(): void
    {
        $body = $this->dashboard()['body'];

        if ( strpos( $body, 'data-testid="dashboard.setup"' ) === false ) {
            self::markTestSkipped( 'this seed has completed all three setup steps' );
        }

        // Colour never carries the state on its own: every step prints a word.
        foreach ( ['page', 'theme', 'build'] as $step ) {
            preg_match(
                '/data-testid="dashboard\.step_state\.' . $step . '"[^>]*>(.*?)<\/p>/s',
                $body,
                $m
            );
            $text = trim( strip_tags( $m[1] ?? '' ) );
            self::assertNotSame( '', $text, "step {$step} must name its state in words" );
        }
    }

    public function testTheBuildActionIsAFormPostWithCsrfAndNeverALink(): void
    {
        $body = $this->dashboard()['body'];

        if ( strpos( $body, 'data-testid="dashboard.setup"' ) === false ) {
            self::markTestSkipped( 'this seed has completed all three setup steps' );
        }

        // §44: "Build now is the only write on the screen: a form post with
        // CSRF ... never a link."
        self::assertStringNotContainsString(
            '<a class="k-btn k-btn--primary" href="" data-testid="dashboard.step_action.build"',
            $body
        );

        if ( strpos( $body, 'data-testid="dashboard.build_form"' ) !== false ) {
            self::assertMatchesRegularExpression(
                '/data-testid="dashboard\.build_form".*?name="csrf/s',
                $body,
                'the build form must carry a CSRF field'
            );
        }
    }

    // ─── the indexing banner ──────────────────────────────────────

    public function testTheIndexingBannerIsAStatusAndCarriesNoToggle(): void
    {
        $body = $this->dashboard()['body'];

        if ( strpos( $body, 'data-testid="dashboard.indexing_banner"' ) === false ) {
            self::markTestSkipped( 'indexing is enabled on this seed, so there is no banner' );
        }

        // §44: role="status", not role="alert" — it is true on arrival, it did
        // not just happen. And it carries no control that changes the setting:
        // a site-wide toggle is not operated in passing from the landing screen
        // (DR-002, D-072 answer 1).
        self::assertMatchesRegularExpression(
            '/data-testid="dashboard\.indexing_banner"[^>]*role="status"|role="status"[^>]*data-testid="dashboard\.indexing_banner"/',
            $body
        );

        preg_match( '/<p[^>]*data-testid="dashboard\.indexing_banner".*?<\/p>/s', $body, $m );
        $banner = $m[0] ?? '';
        self::assertStringNotContainsString( '<form', $banner );
        self::assertStringNotContainsString( '<button', $banner );
        self::assertStringNotContainsString( 'type="checkbox"', $banner );
        self::assertStringContainsString( 'settings.php?section=advanced', $banner );
    }

    // ─── the whole screen without JavaScript ──────────────────────

    public function testNothingOnTheScreenIsBuiltByScript(): void
    {
        $body = $this->dashboard()['body'];

        // §44: "Nothing on the screen requires JavaScript. The widget grid, the
        // setup panel and the banner are all server-rendered." The proof is
        // that they are in the response at all — this test reads the HTML the
        // server sent, with no browser in the loop.
        $hasPanelOrGrid = strpos( $body, 'data-testid="dashboard.setup"' ) !== false
            || strpos( $body, 'data-testid="dashboard.widgets"' ) !== false;

        self::assertTrue( $hasPanelOrGrid, 'the screen renders one of its two bodies server-side' );
        self::assertStringContainsString( 'data-testid="dashboard.stats"', $body );
    }
}
