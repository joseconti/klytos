<?php

/**
 * Klytos CMS — the Assets screen's server-rendered contract (manifest entry 4).
 *
 * The FIRST consumer of `template-gallery-grid.md`, so this file pins that
 * template's contract as well as the screen's.
 *
 * And the point of the whole slice: **every assertion here is made against
 * markup the server produced.** The shipped screen rendered its grid, its
 * filters and its pagination in the browser, so with scripting off it showed
 * nothing — and none of what follows could have been asserted at all. Template
 * §2 is explicit: "Loading — server-rendered. Pagination is a link."
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

/**
 * The Assets gallery against a known, deterministic library.
 */
final class AssetsHttpTest extends AdminHttpTestCase
{
    /** Its own slot in the reserved band; `KLYTOS_TEST_PORT_OFFSET` shifts the whole band. */
    protected static function serverPort(): int
    {
        return 8119;
    }

    /** @param array $args Extra arguments for the fixture, e.g. `['--off']`. */
    private static function runFixture( array $args = [] ): int
    {
        $cmd = escapeshellarg( PHP_BINARY ) . ' '
            . escapeshellarg( self::$repoRoot . '/tests/E2E/fixtures/reset-assets.php' );

        foreach ( $args as $arg ) {
            $cmd .= ' ' . escapeshellarg( (string) $arg );
        }

        exec( $cmd . ' 2>&1', $out, $code );

        return $code;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Two images (one with alt text, one without), one video, one document;
        // exactly one of them in use. Every chip therefore matches something
        // different, and none of them can pass by matching everything.
        if ( self::runFixture() !== 0 ) {
            self::fail( 'the assets fixture could not seed its population' );
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::runFixture( ['--off'] );

        parent::tearDownAfterClass();
    }

    private function page( string $query = '' ): string
    {
        $response = $this->request( '/installer/admin/assets.php' . $query, 'owner' );

        $this->assertSame( 200, $response['status'], 'the screen answers 200 for the owner' );

        return $response['body'];
    }

    // ─── Server-rendered, which is the whole slice ───────────────

    /**
     * THE GRID ARRIVES IN THE HTML.
     *
     * Not "a container exists that JavaScript will fill" — the tiles themselves.
     * The shipped screen's grid was `<!-- Populated by JS -->` inside a card that
     * started `hidden`.
     */
    public function testTheGridIsRenderedByTheServerWithItsTiles(): void
    {
        $html = $this->page();

        $this->assertStringContainsString( 'data-testid="assets.grid"', $html );
        $this->assertSame(
            4,
            substr_count( $html, 'data-testid="assets.tile.' ),
            'four tiles, server-rendered'
        );
        $this->assertStringNotContainsString(
            'Populated by JS',
            $html,
            'nothing on this screen waits for a script'
        );
    }

    /** The filter chips are LINKS with `aria-current`, never tabs or buttons. */
    public function testTheFilterChipsAreLinksCarryingAriaCurrent(): void
    {
        $html = $this->page( '?type=image' );

        $this->assertMatchesRegularExpression(
            '/<a[^>]*type=image[^>]*aria-current="true"/',
            $html,
            'the chosen kind is the current chip'
        );

        $chips = substr( $html, (int) strpos( $html, 'data-testid="assets.filters"' ), 1800 );
        $this->assertStringNotContainsString( 'role="tab"', $chips );
        $this->assertStringNotContainsString( '<button', $chips );
    }

    /**
     * §4's kind chips each select a DIFFERENT set.
     *
     * Asserted by counting tiles, so a filter that quietly matched everything
     * would fail rather than look healthy.
     */
    public function testEachKindChipSelectsItsOwnFiles(): void
    {
        $this->assertSame( 2, substr_count( $this->page( '?type=image' ), 'data-testid="assets.tile.' ) );
        $this->assertSame( 1, substr_count( $this->page( '?type=video' ), 'data-testid="assets.tile.' ) );
        $this->assertSame( 1, substr_count( $this->page( '?type=document' ), 'data-testid="assets.tile.' ) );
    }

    /** §4's `Unused` chip excludes exactly the asset that is in use. */
    public function testTheUnusedChipExcludesTheAssetInUse(): void
    {
        $html = $this->page( '?filter=unused' );

        $this->assertSame( 3, substr_count( $html, 'data-testid="assets.tile.' ) );
        $this->assertStringNotContainsString( 'e2e-asset-used.png', $html );
    }

    // ─── §4's two deltas ─────────────────────────────────────────

    /**
     * The "No alt text" chip appears on EXACTLY the image that lacks alt text,
     * and it is a link to that asset's alt field.
     *
     * The count is the assertion that matters: a chip on every tile and a chip
     * on none are both wrong, and both look plausible in a screenshot.
     */
    public function testTheNoAltChipMarksOnlyTheImageWithoutAltText(): void
    {
        $html = $this->page();

        $this->assertSame(
            1,
            substr_count( $html, 'data-testid="assets.no_alt.' ),
            'one image has no alt text, so exactly one chip'
        );

        // §4: "that chip is a link to the asset's alt field".
        $this->assertMatchesRegularExpression(
            '/<a[^>]*class="[^"]*k-chip[^"]*"[^>]*href="[^"]*asset=[^"]*#alt"/',
            $html,
            'the chip is an anchor pointing at the alt field'
        );
    }

    /**
     * Delete is disabled for an asset in use, AND THE REASON IS IN THE NAME.
     *
     * A disabled control whose reason lives only in a tooltip tells a
     * screen-reader user nothing — entry 1 settled that (D-079).
     */
    public function testDeleteIsDisabledForAnAssetInUseWithItsReasonInTheAccessibleName(): void
    {
        $html = $this->page();

        $this->assertSame( 1, substr_count( $html, 'disabled' ), 'exactly one delete is disabled' );

        $this->assertMatchesRegularExpression(
            '/aria-label="Delete e2e-asset-used\.png[^"]*used on 1[^"]*"/',
            $html,
            'the reason and the count are in the accessible name'
        );

        // And the three that are free to delete are NOT disabled.
        $this->assertSame( 4, substr_count( $html, 'data-testid="assets.delete.' ) );
    }

    /**
     * THE DISABLED ATTRIBUTE IS NOT THE BOUNDARY — the server refuses too.
     *
     * A `disabled` button is a courtesy to whoever is looking at the screen. A
     * crafted POST does not go through the screen.
     */
    public function testAPostedDeleteOfAnAssetInUseIsRefusedByTheServer(): void
    {
        $html = $this->page();

        preg_match( '/data-testid="assets\.tile\.([^"]+)"/', $html, $m );
        $this->assertNotEmpty( $m[1] ?? '', 'a tile id was found to post with' );

        // The asset in use is the one whose delete is disabled; find its id.
        preg_match( '/name="id" value="([^"]+)">\s*<button[^>]*disabled/s', $html, $used );

        if ( ( $used[1] ?? '' ) === '' ) {
            // Fall back to matching through the aria-label, which names the file.
            preg_match(
                '/value="([^"]+)">\s*[^<]*<button[^>]*aria-label="Delete e2e-asset-used/s',
                $html,
                $used
            );
        }

        $this->assertNotEmpty( $used[1] ?? '', 'the in-use asset id was found' );

        $response = $this->post(
            '/installer/admin/assets.php',
            ['action' => 'delete', 'id' => $used[1]],
            'owner'
        );

        $this->assertSame( 200, $response['status'] );
        $this->assertStringContainsString(
            'data-testid="assets.error"',
            $response['body'],
            'the refusal REPORTS itself rather than failing silently'
        );
        $this->assertStringContainsString(
            'e2e-asset-used.png',
            $this->page(),
            'and the asset is still there'
        );
    }

    // ─── States and hygiene ──────────────────────────────────────

    /**
     * The two empty states are DIFFERENT sentences.
     *
     * "Nothing matches your filter" and "nothing has been uploaded" are opposite
     * facts, and a screen that says the second when the first is true sends
     * someone hunting a problem that is not there.
     */
    public function testTheFilteredEmptyStateIsNotTheNothingUploadedOne(): void
    {
        $html = $this->page( '?search=nothing-matches-this-at-all' );

        $this->assertStringContainsString( 'data-testid="assets.empty"', $html );
        $this->assertStringContainsString( 'data-testid="assets.empty_action"', $html );
        $this->assertStringNotContainsString( 'No files yet', $html );
    }

    /** Search is a real GET form and it narrows the grid. */
    public function testSearchNarrowsTheGrid(): void
    {
        $html = $this->page( '?search=no-alt' );

        $this->assertSame( 1, substr_count( $html, 'data-testid="assets.tile.' ) );
        $this->assertStringContainsString( 'e2e-asset-no-alt.png', $html );
    }

    /**
     * A REFUSED CSRF reports itself — and this time the refusal really happens.
     *
     * `AdminHttpTestCase::post()` injected a valid token unconditionally until
     * this slice, which made this test unwritable through it; the one screen
     * that tried sent a field name nothing reads and asserted an error that
     * came from somewhere else entirely (D-118). The harness now keeps a `csrf`
     * the caller supplies, so the token below is genuinely wrong.
     */
    public function testARefusedCsrfPostSaysSo(): void
    {
        $bad = $this->post(
            '/installer/admin/assets.php',
            ['action' => 'sync', 'csrf' => 'not-a-valid-token'],
            'owner'
        );

        $this->assertSame( 200, $bad['status'] );
        $this->assertStringContainsString(
            'data-testid="assets.error"',
            $bad['body'],
            'a refused form REPORTS itself rather than re-rendering as if nothing was sent'
        );
    }

    /** An unknown action is refused and says so, rather than passing silently. */
    public function testAnUnknownActionIsRefused(): void
    {
        $response = $this->post(
            '/installer/admin/assets.php',
            ['action' => 'definitely-not-an-action'],
            'owner'
        );

        $this->assertStringContainsString( 'data-testid="assets.error"', $response['body'] );
    }

    /** No catalogue key reaches the page as copy. */
    public function testNoCatalogueKeyIsPrintedAsCopy(): void
    {
        $html = $this->page();

        $catalogue = json_decode(
            (string) file_get_contents( self::$repoRoot . '/installer/core/lang/en.json' ),
            true
        );

        foreach ( array_keys( $catalogue['assets'] ?? [] ) as $key ) {
            $this->assertStringNotContainsString(
                '>assets.' . $key . '<',
                $html,
                'assets.' . $key . ' rendered as its own key — the catalogue lookup failed'
            );
        }
    }
}
