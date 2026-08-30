<?php

/**
 * Klytos CMS — the Blocks screen's server-rendered contract (manifest entry 21).
 *
 * The SECOND consumer of `template-gallery-grid.md`. What the template requires
 * is already pinned on entry 4 (`AssetsHttpTest`) and is not repeated; what is
 * asserted here is entry 21's own — the per-category grouping §21 requires, and
 * the usage count, which is the finding this slice turned up.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

/**
 * Blocks, grouped and counted.
 */
final class BlocksHttpTest extends AdminHttpTestCase
{
    /** Its own slot in the reserved band; `KLYTOS_TEST_PORT_OFFSET` shifts the whole band. */
    protected static function serverPort(): int
    {
        return 8120;
    }

    /** @param array $args Extra arguments for the fixture, e.g. `['--off']`. */
    private static function runFixture( array $args = [] ): int
    {
        $cmd = escapeshellarg( PHP_BINARY ) . ' '
            . escapeshellarg( self::$repoRoot . '/tests/E2E/fixtures/reset-blocks.php' );

        foreach ( $args as $arg ) {
            $cmd .= ' ' . escapeshellarg( (string) $arg );
        }

        exec( $cmd . ' 2>&1', $out, $code );

        return $code;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Three categories and usage counts of 2, 1 and 0 — so "grouped by
        // category" cannot pass by rendering one group holding everything, and
        // the count cannot pass by being the same number everywhere.
        if ( self::runFixture() !== 0 ) {
            self::fail( 'the blocks fixture could not seed its population' );
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::runFixture( ['--off'] );

        parent::tearDownAfterClass();
    }

    private function page( string $query = '' ): string
    {
        $response = $this->request( '/installer/admin/blocks.php' . $query, 'owner' );

        $this->assertSame( 200, $response['status'], 'the screen answers 200 for the owner' );

        return $response['body'];
    }

    /** §21: the heading is Blocks, and the screen is server-rendered. */
    public function testTheScreenRendersItsGalleryFromTheServer(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression( '/<h1[^>]*>\s*Blocks\s*</', $html );
        $this->assertSame( 3, substr_count( $html, 'data-testid="blocks.tile.' ) );
    }

    /**
     * §21: "Grouped by category, each group an `<h2>` + its own labelled `<ul>`".
     *
     * Three groups, three headings, and each list POINTING AT its own heading —
     * a single `<ul>` holding everything would satisfy a looser assertion and
     * lose the structure a screen reader moves through.
     */
    public function testEachCategoryIsItsOwnGroupWithItsOwnLabelledList(): void
    {
        $html = $this->page();

        $this->assertSame( 3, substr_count( $html, 'data-testid="blocks.group.' ) );
        $this->assertSame( 3, substr_count( $html, 'class="k-gallery"' ) );

        foreach ( ['structure', 'content', 'social-proof'] as $category ) {
            $this->assertMatchesRegularExpression(
                '/<ul class="k-gallery" aria-labelledby="blocks-cat-' . preg_quote( $category, '/' ) . '"/',
                $html,
                $category . "'s list is labelled by its own heading"
            );
        }
    }

    /** The category labels are translated, never hard-coded English. */
    public function testTheCategoryLabelsComeFromTheCatalogue(): void
    {
        $html = $this->page();

        // The shipped screen hard-coded 'Structure', 'Content', 'Interaction',
        // 'Social Proof' and 'Custom' on a product with twenty catalogues.
        $catalogue = json_decode(
            (string) file_get_contents( self::$repoRoot . '/installer/core/lang/en.json' ),
            true
        );

        $this->assertSame(
            $catalogue['blocks']['category_social_proof'],
            'Social proof',
            'the catalogue is the source of the label'
        );
        $this->assertStringContainsString( '>Social proof<', $html );
    }

    /**
     * THE USAGE COUNT IS MEASURED, and it says what it counts.
     *
     * The fixture puts `hero` in two templates, `features` in one and
     * `testimony` in none. Three different answers on one page, so a count that
     * was constant — which is what the first build produced, reading a field
     * nothing writes — cannot pass.
     */
    public function testTheUsageCountIsRealAndNamesWhatItCounts(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression(
            '/data-testid="blocks\.usage\.e2eblock-hero">\s*In 2 template/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-testid="blocks\.usage\.e2eblock-features">\s*In 1 template/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-testid="blocks\.usage\.e2eblock-testimony">\s*In no template/',
            $html,
            'zero is a different sentence, not the number 0'
        );
    }

    /** The preview renders the block, and it is reachable as a plain link. */
    public function testThePreviewRendersTheBlock(): void
    {
        $html = $this->page( '?preview=e2eblock-hero' );

        $this->assertStringContainsString( 'data-testid="blocks.preview"', $html );
        $this->assertStringContainsString( 'Hero banner', $html );
        $this->assertStringContainsString( 'data-testid="blocks.preview_close"', $html );
    }

    /** An unknown block reports itself rather than rendering an empty panel. */
    public function testAnUnknownPreviewReportsItself(): void
    {
        $html = $this->page( '?preview=does-not-exist' );

        $this->assertStringContainsString( 'data-testid="blocks.error"', $html );
        $this->assertStringNotContainsString( 'data-testid="blocks.preview"', $html );
    }

    /** No catalogue key and no unsubstituted placeholder reaches the page. */
    public function testNoKeyOrPlaceholderIsPrinted(): void
    {
        $html = $this->page();

        $catalogue = json_decode(
            (string) file_get_contents( self::$repoRoot . '/installer/core/lang/en.json' ),
            true
        );

        foreach ( array_keys( $catalogue['blocks'] ?? [] ) as $key ) {
            $this->assertStringNotContainsString( '>blocks.' . $key . '<', $html );
        }

        $this->assertDoesNotMatchRegularExpression(
            '/>\s*[^<]*\{(count|block)\}/',
            $html,
            'an unsubstituted placeholder reached the page — entry 4 shipped one (D-119)'
        );
    }
}
