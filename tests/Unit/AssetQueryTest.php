<?php

/**
 * Klytos CMS — `AssetManager::query()`, the ONE asset selection both surfaces use.
 *
 * Manifest entry 4 (Assets) is `template-gallery-grid`, and §2 of that template
 * is explicit: "**Loading — server-rendered.** Pagination is a link. There is no
 * infinite scroll anywhere in the admin." The shipped screen is the opposite —
 * `<!-- Populated by JS -->`, a card that starts `hidden`, and pagination built
 * in JavaScript — so with scripting off it shows nothing at all.
 *
 * Making it server-rendered means the screen needs the same filtering, search,
 * sort and pagination the API endpoint already performs. Writing that a second
 * time in the screen is L-004's shape at its most expensive: two
 * implementations of "which assets does this person see", free to drift, one of
 * them deciding what a page shows and the other what an MCP client is told.
 *
 * So the selection moves DOWN into the manager, and both callers use it. This
 * test pins it: it is a function of stored records and its arguments, which is
 * what the card's `Test-first policy: pure-logic` names, so it is written and
 * seen failing first.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\AssetManager;
use Klytos\Tests\UnitTestCase;

/**
 * One selection, two surfaces.
 */
final class AssetQueryTest extends UnitTestCase
{
    private AssetManager $assets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assets = new AssetManager( $this->storage, $this->tempDir );

        // Records in the shape `upload()` writes (asset-manager.php:120-133).
        $this->seedAsset( 'a1', 'hero.jpg', 'image/jpeg', '2026-08-01', 'A hero' );
        $this->seedAsset( 'a2', 'diagram.png', 'image/png', '2026-08-03', '' );
        $this->seedAsset( 'a3', 'intro.mp4', 'video/mp4', '2026-08-02', '' );
        $this->seedAsset( 'a4', 'terms.pdf', 'application/pdf', '2026-08-04', '' );

        // `a1` is used on a page; nothing else is.
        $this->storage->write( 'asset-usage', 'a1--page--home', [
            'id'            => 'a1--page--home',
            'asset_id'      => 'a1',
            'context_type'  => 'page',
            'context_id'    => 'home',
            'context_label' => 'Home',
            'field'         => 'content_html',
            'added_at'      => '2026-08-05T00:00:00Z',
        ] );
    }

    private function seedAsset( string $id, string $filename, string $mime, string $uploaded, string $alt ): void
    {
        $this->storage->write( 'assets', $id, [
            'id'          => $id,
            'filename'    => $filename,
            'path'        => 'images/' . $filename,
            'mime_type'   => $mime,
            'size'        => 1024,
            'size_human'  => '1 KB',
            'alt_text'    => $alt,
            'title'       => pathinfo( $filename, PATHINFO_FILENAME ),
            'description' => '',
            'categories'  => [],
            'uploaded_by' => 'system',
            'uploaded_at' => $uploaded . 'T00:00:00Z',
            'updated_at'  => $uploaded . 'T00:00:00Z',
        ] );
    }

    /** @param array<string,mixed> $args */
    private function ids( array $args = [] ): array
    {
        return array_column( $this->assets->query( $args )['assets'], 'id' );
    }

    /** Everything, newest first — the order the shipped endpoint already used. */
    public function testItReturnsEverythingNewestFirstByDefault(): void
    {
        $this->assertSame( ['a4', 'a2', 'a3', 'a1'], $this->ids() );
    }

    /** §4's `Unused` filter, backed by the manager's own definition of unused. */
    public function testTheUnusedFilterExcludesAssetsThatAreInUse(): void
    {
        $ids = $this->ids( ['filter' => 'unused'] );

        $this->assertNotContains( 'a1', $ids, 'a1 is used on a page' );
        $this->assertContains( 'a2', $ids );
        $this->assertCount( 3, $ids );
    }

    /** And its mirror, so a screen can offer both without a second code path. */
    public function testTheInUseFilterKeepsOnlyAssetsThatAreInUse(): void
    {
        $this->assertSame( ['a1'], $this->ids( ['filter' => 'in_use'] ) );
    }

    /**
     * §4's kind filters are Images · Video · Documents.
     *
     * The first two are a MIME prefix. **Documents is not** — it is everything
     * that is neither, which is why it takes its own value rather than a third
     * prefix: `application/pdf`, `text/csv` and a Word file share no prefix, and
     * a screen offering "Documents" must not quietly mean "PDFs".
     */
    public function testTheKindFilterCoversImagesVideoAndDocuments(): void
    {
        $this->assertSame( ['a2', 'a1'], $this->ids( ['type' => 'image'] ) );
        $this->assertSame( ['a3'], $this->ids( ['type' => 'video'] ) );
        $this->assertSame( ['a4'], $this->ids( ['type' => 'document'] ) );
    }

    /**
     * Any other value is a MIME PREFIX, which is what this filter has always meant.
     *
     * This test asserted the opposite on its first run — that an unknown kind
     * narrows nothing — and that expectation was wrong, not the product's. The
     * shipped screen sends `application` for its Documents option and `font` for
     * its Fonts one (`assets.php:94-95`), so "unknown means no filter" would
     * silently widen two live controls. The named kinds are additions on top of
     * the prefix behaviour, never a replacement for it.
     */
    public function testAnyOtherKindIsStillAMimePrefix(): void
    {
        $this->assertSame( ['a4'], $this->ids( ['type' => 'application'] ) );
        $this->assertSame( [], $this->ids( ['type' => 'hologram'] ) );
    }

    /** Search matches the filename and the title, case-insensitively. */
    public function testSearchMatchesFilenameAndTitle(): void
    {
        $this->assertSame( ['a2'], $this->ids( ['search' => 'DIAGRAM'] ) );
        $this->assertSame( [], $this->ids( ['search' => 'nothing-like-this'] ) );
    }

    /** Pagination reports the totals a link-based pager needs. */
    public function testPaginationReportsTotalsAndPages(): void
    {
        $page1 = $this->assets->query( ['per_page' => 3, 'page' => 1] );
        $page2 = $this->assets->query( ['per_page' => 3, 'page' => 2] );

        $this->assertSame( 4, $page1['total'] );
        $this->assertSame( 2, $page1['pages'] );
        $this->assertCount( 3, $page1['assets'] );
        $this->assertCount( 1, $page2['assets'] );
        $this->assertSame( 2, $page2['page'] );
    }

    /** A page beyond the end is empty and says so, rather than wrapping around. */
    public function testAPageBeyondTheEndIsEmpty(): void
    {
        $result = $this->assets->query( ['per_page' => 3, 'page' => 99] );

        $this->assertSame( [], $result['assets'] );
        $this->assertSame( 4, $result['total'] );
    }

    /**
     * The usage count rides along with each asset.
     *
     * §4's tile draws it and its delta links it to the pages, so computing it
     * per tile in the screen would be one storage traversal per asset. It is
     * counted once here.
     */
    public function testEachAssetCarriesItsUsageCount(): void
    {
        $byId = [];
        foreach ( $this->assets->query()['assets'] as $asset ) {
            $byId[ $asset['id'] ] = $asset;
        }

        $this->assertSame( 1, $byId['a1']['usage_count'] );
        $this->assertSame( 0, $byId['a2']['usage_count'] );
    }

    /** Filters compose rather than overriding one another. */
    public function testFiltersCompose(): void
    {
        $this->assertSame(
            ['a2'],
            $this->ids( ['type' => 'image', 'filter' => 'unused'] ),
            'an unused image is both, and only a2 is'
        );
    }
}
