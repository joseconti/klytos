// Manifest entry 1 — Pages — driven per STATE, in both themes.
//
// Phase 4 Step 4, stage 4 (the list screens). Pages is the ONLY list-table
// consumer whose `grid-template-columns` the delivery records, so it is the
// only one built in this block; the other twelve are blocked on DR-006.
//
// The pattern is components.spec.js's, and it is deliberate: axe scoped per
// STATE rather than per page, geometry and cascade read back out of the browser
// rather than off the file (L-032 — never assume which rule wins), the 1.4.10
// check asserted by TRYING TO SCROLL rather than by comparing scrollWidth
// (the two disagreed, and the disagreement was the finding), and the theme
// baked in before load rather than toggled after paint (a reading taken
// mid-transition reported a button at 2.59:1 that is 4.86:1).

const { test, expect, login } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );
const PAGES_URL = '/installer/admin/pages.php';

/**
 * Create or remove a page through the REAL PageManager, so a test can build the
 * population it needs instead of skipping.
 *
 * A skipped test is not a passing test: `the site home renders its delete as
 * disabled` and `a bulk unpublish reloads the list` both skipped on the seeded
 * data (no page with the slug `index`, no published page), and a check that
 * never runs over a real population is exactly the "PASS over zero" shape
 * L-030 was written for. This drives the product's own manager rather than
 * writing a JSON file by hand, so the record is the same shape the product
 * makes.
 */
function createIndexPage() {
    // Bootstraps exactly as installer/cli.php does — require core/app.php, get
    // the singleton, boot — so the record is created by the product's own
    // PageManager and not by a hand-written JSON file.
    const php = [
        "require 'installer/core/app.php';",
        '$app = \\Klytos\\Core\\App::getInstance();',
        '$app->boot();',
        '$p = $app->getPages();',
        "try { $p->get('index'); }",
        "catch ( \\Throwable $e ) {",
        "  $p->create( ['slug' => 'index', 'title' => 'Site home', 'status' => 'published'] );",
        '}',
    ].join( ' ' );

    execFileSync( 'php', [ '-r', php ], {
        cwd: REPO_ROOT,
        encoding: 'utf-8',
        env: { ...process.env, XDEBUG_MODE: 'off' },
    } );
}

/*
 * DR-005's excluded elements, by SELECTOR and never by disabling a rule, so
 * every other element in every state stays checked and a NEW defect on the same
 * components still fails. Extend this list ONLY for a registered Design Request.
 */
const KNOWN_DELIVERY_GAPS = [
    // DR-005 gap 1 — a semantic badge inside a selected table row: the badge
    // tint over --fila-seleccion over the card. 4.44:1 light / 3.08:1 dark.
    'tr[aria-selected="true"] .k-badge',

    // DR-005 addendum, light theme only. The filter row sits on
    // --fondo-ventana, OUTSIDE the card, and both chip states fall under AA
    // there while both pass on a card:
    //   unselected  --texto-secundario on --fondo-ventana            4.46:1
    //               — the same token pair DR-005 gap 2 already registers,
    //                 arriving on a second surface.
    //   selected    --sobre-tinte-acento over --fila-seleccion over
    //               --fondo-ventana                                  4.46:1
    //               — a new composition, sibling of gap 1. On a card the same
    //                 chip measures 4.76:1 and passes.
    // Both recomputed independently from the token hexes; axe and the
    // arithmetic agree. No colour was substituted.
    '.k-filters .k-chip',
];

/*
 * axe's aria-required-children fires on <table role="table"> carrying a
 * <caption>: an explicitly-roled table may own only rowgroup/row and a caption
 * is neither. But accessibility.md §2.1 MANDATES both, and Chromium's real
 * accessibility tree is exactly what §2.1 describes — that was read out of the
 * browser rather than argued about (D-078). The rule is disabled here only, and
 * what it would have protected is pinned by the explicit-roles test below.
 */
const TABLE_RULE_EXCEPTIONS = [ 'aria-required-children' ];

async function axeScan( page, selector, { disableRules = [] } = {} ) {
    let builder = new AxeBuilder( { page } )
        .withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
        .include( selector );

    for ( const gap of KNOWN_DELIVERY_GAPS ) {
        builder = builder.exclude( gap );
    }
    if ( disableRules.length ) {
        builder = builder.disableRules( disableRules );
    }

    const result = await builder.analyze();
    return result.violations;
}

/**
 * Open Pages with the theme baked into the markup BEFORE load, which is how the
 * product itself works — server-rendered from the cookie, no flash (D-075).
 * Toggling after paint reads interpolated values out of a 120ms transition.
 */
async function openPages( page, { theme = 'dark', query = '' } = {} ) {
    await page.context().addCookies( [ {
        // The cookie header.php actually reads. Getting this name wrong does
        // not fail loudly — the page simply renders the default theme and every
        // "light" assertion silently measures dark, which is why openPages()
        // asserts the substitution took rather than trusting it.
        name: 'klytos_admin_theme',
        value: theme,
        url: new URL( PAGES_URL, page.url() || 'http://127.0.0.1' ).origin,
    } ] );
    await page.goto( PAGES_URL + query );

    const applied = await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) );
    expect( applied, 'the theme must be in the markup before paint, not toggled after it' ).toBe( theme );
}

test.describe( 'Pages — manifest entry 1, template list-table', () => {
    test.beforeEach( async ( { page } ) => {
        await login( page, 'owner' );
    } );

    // -----------------------------------------------------------------
    // The markup contract — accessibility.md §2.1, element by element
    // -----------------------------------------------------------------

    test( 'the table carries the complete explicit ARIA role set', async ( { page } ) => {
        await openPages( page );
        const table = page.getByTestId( 'pages.table' );

        await expect( table ).toHaveAttribute( 'role', 'table' );
        await expect( table.locator( 'thead' ) ).toHaveAttribute( 'role', 'rowgroup' );
        await expect( table.locator( 'tbody' ) ).toHaveAttribute( 'role', 'rowgroup' );

        // Every <tr> is role="row" — counted, not sampled.
        const rows = table.locator( 'tr' );
        const rowCount = await rows.count();
        expect( rowCount ).toBeGreaterThan( 0 );
        for ( let i = 0; i < rowCount; i++ ) {
            await expect( rows.nth( i ) ).toHaveAttribute( 'role', 'row' );
        }

        // Every <th> in <thead> is columnheader + scope="col".
        const heads = table.locator( 'thead th' );
        const headCount = await heads.count();
        expect( headCount ).toBe( 7 ); // manifest §1's seven columns
        for ( let i = 0; i < headCount; i++ ) {
            await expect( heads.nth( i ) ).toHaveAttribute( 'role', 'columnheader' );
            await expect( heads.nth( i ) ).toHaveAttribute( 'scope', 'col' );
        }

        // Every <td> is role="cell".
        const cells = table.locator( 'tbody td' );
        const cellCount = await cells.count();
        for ( let i = 0; i < cellCount; i++ ) {
            await expect( cells.nth( i ) ).toHaveAttribute( 'role', 'cell' );
        }
    } );

    test( 'exactly one th[role=rowheader][scope=row] per row, and it names the record', async ( { page } ) => {
        await openPages( page );
        const bodyRows = page.getByTestId( 'pages.table' ).locator( 'tbody tr' );
        const n = await bodyRows.count();

        for ( let i = 0; i < n; i++ ) {
            const row = bodyRows.nth( i );
            const headers = row.locator( 'th[role="rowheader"][scope="row"]' );
            await expect( headers ).toHaveCount( 1 );
            // It carries an id, because the row checkbox is aria-labelledby it.
            await expect( headers.first() ).toHaveAttribute( 'id', /.+/ );
        }
    } );

    test( 'row checkboxes are labelled BY the row header, never by an invented string', async ( { page } ) => {
        await openPages( page );
        const bodyRows = page.getByTestId( 'pages.table' ).locator( 'tbody tr' );
        const row = bodyRows.first();

        const headerId = await row.locator( 'th[role="rowheader"]' ).getAttribute( 'id' );
        const labelledBy = await row.locator( 'input[type="checkbox"]' ).getAttribute( 'aria-labelledby' );
        expect( labelledBy ).toBe( headerId );
    } );

    test( 'the caption is visible, carries the count and the page position, and is aria-live', async ( { page } ) => {
        await openPages( page );
        const caption = page.locator( '#pages-caption' );

        await expect( caption ).toBeVisible();
        await expect( caption ).toHaveAttribute( 'aria-live', 'polite' );
        // The count and the page position, both present.
        await expect( page.getByTestId( 'pages.caption_text' ) ).toContainText( /\d+/ );
        // The H1 carries NO count — the count lives here (template §4).
        await expect( page.locator( 'h1' ) ).toHaveText( 'Pages' );
    } );

    test( 'the icon-only action names the RECORD, not the icon', async ( { page } ) => {
        await openPages( page );
        const action = page.locator( '[data-testid^="pages.actions."]' ).first();
        const label = await action.getAttribute( 'aria-label' );

        expect( label ).toBeTruthy();
        expect( label.toLowerCase() ).not.toContain( 'more_horiz' );
        expect( label.toLowerCase() ).not.toBe( 'more actions' );
    } );

    // -----------------------------------------------------------------
    // The per-screen value the whole stage turns on
    // -----------------------------------------------------------------

    test( "grid-template-columns is the manifest's seven tracks, read out of the browser", async ( { page } ) => {
        await openPages( page );

        const tracks = await page.evaluate( () => {
            const tr = document.querySelector( '.k-pages-table tbody tr:not(.k-table-row-full)' );
            return getComputedStyle( tr ).gridTemplateColumns;
        } );

        // Seven tracks, and the fixed ones are the manifest's exact pixel
        // values. The 1fr track resolves to a used width, so it is asserted as
        // "not one of the fixed values" rather than by a number.
        const parts = tracks.split( /\s+/ );
        expect( parts ).toHaveLength( 7 );
        expect( parts[ 0 ] ).toBe( '28px' );
        expect( parts[ 2 ] ).toBe( '116px' );
        expect( parts[ 3 ] ).toBe( '132px' );
        expect( parts[ 4 ] ).toBe( '96px' );
        expect( parts[ 5 ] ).toBe( '132px' );
        expect( parts[ 6 ] ).toBe( '44px' );
    } );

    test( 'the full-width empty row still spans every column — the :not() actually wins', async ( { page } ) => {
        // Filter to something that cannot match, which is the state that uses
        // .k-table-row-full. If .k-pages-table tr had been written without the
        // :not(), it would be (0,1,1) against (0,1,0) and would silently
        // collapse this sentence into the first 28px track.
        await openPages( page, { query: '?q=zzz-no-such-page-zzz' } );

        const tracks = await page.evaluate( () => {
            const tr = document.querySelector( '.k-pages-table tr.k-table-row-full' );
            return tr ? getComputedStyle( tr ).gridTemplateColumns : null;
        } );

        expect( tracks ).not.toBeNull();
        expect( tracks.split( /\s+/ ) ).toHaveLength( 1 );
    } );

    // -----------------------------------------------------------------
    // States — template-list-table.md §2
    // -----------------------------------------------------------------

    for ( const theme of [ 'light', 'dark' ] ) {
        test( `default state passes axe at WCAG 2.2 AA — ${ theme }`, async ( { page } ) => {
            await openPages( page, { theme } );
            const violations = await axeScan( page, '.k-card--table', { disableRules: TABLE_RULE_EXCEPTIONS } );
            expect( violations.map( ( v ) => `${ v.id }: ${ v.nodes.length }` ) ).toEqual( [] );
        } );

        test( `the filter row passes axe at WCAG 2.2 AA — ${ theme }`, async ( { page } ) => {
            await openPages( page, { theme } );
            const violations = await axeScan( page, '.k-filters' );
            expect( violations.map( ( v ) => `${ v.id }: ${ v.nodes.length }` ) ).toEqual( [] );
        } );

        // The chips are excluded from the scan above by KNOWN_DELIVERY_GAPS,
        // which drops them from EVERY rule and not only from contrast. So they
        // are scanned again here with contrast alone disabled — the same shape
        // D-078 used for the table's aria-required-children: narrow the
        // exception, then pin what it would otherwise have hidden.
        test( `the filter chips pass every non-contrast axe rule — ${ theme }`, async ( { page } ) => {
            await openPages( page, { theme } );
            const AxeBuilderLocal = require( '@axe-core/playwright' ).default;
            const result = await new AxeBuilderLocal( { page } )
                .withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
                .include( '.k-filters' )
                .disableRules( [ 'color-contrast' ] )
                .analyze();
            expect( result.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length }` ) ).toEqual( [] );
        } );

        test( `the filtered-empty state passes axe at WCAG 2.2 AA — ${ theme }`, async ( { page } ) => {
            await openPages( page, { theme, query: '?q=zzz-no-such-page-zzz' } );
            await expect( page.getByTestId( 'pages.empty_filtered' ) ).toBeVisible();
            const violations = await axeScan( page, '.k-card--table', { disableRules: TABLE_RULE_EXCEPTIONS } );
            expect( violations.map( ( v ) => `${ v.id }: ${ v.nodes.length }` ) ).toEqual( [] );
        } );

        test( `the selected-row state passes axe at WCAG 2.2 AA — ${ theme }`, async ( { page } ) => {
            await openPages( page, { theme } );
            await page.locator( '.k-row-check' ).first().check();
            await expect( page.locator( 'tr[aria-selected="true"]' ) ).toHaveCount( 1 );

            const violations = await axeScan( page, '.k-card--table', { disableRules: TABLE_RULE_EXCEPTIONS } );
            expect( violations.map( ( v ) => `${ v.id }: ${ v.nodes.length }` ) ).toEqual( [] );
        } );
    }

    test( 'filtering to nothing shows the filtered sentence, not the no-records one', async ( { page } ) => {
        await openPages( page, { query: '?q=zzz-no-such-page-zzz' } );

        // Different sentence, different action, and it never suggests creating
        // a record (template-list-table.md §2).
        await expect( page.getByTestId( 'pages.empty_filtered' ) ).toBeVisible();
        await expect( page.getByTestId( 'pages.empty' ) ).toHaveCount( 0 );
        await expect( page.getByTestId( 'pages.empty_clear_filters' ) ).toBeVisible();
        await expect( page.getByTestId( 'pages.empty_action' ) ).toHaveCount( 0 );

        // The table keeps its header row — it is not replaced by a div.
        await expect( page.getByTestId( 'pages.table' ).locator( 'thead th' ) ).toHaveCount( 7 );
    } );

    test( 'selecting a row raises the bulk bar and sets aria-selected on the <tr>', async ( { page } ) => {
        await openPages( page );
        const bar = page.getByTestId( 'pages.bulkbar' );

        await expect( bar ).toBeHidden();
        await page.locator( '.k-row-check' ).first().check();
        await expect( bar ).toBeVisible();
        await expect( page.getByTestId( 'pages.bulkbar_count' ) ).toContainText( '1' );

        // The bar never covers a focused row: the CONTENT AREA gains 48px
        // (template-list-table.md §2). The content area is <main class="k-main">.
        const pad = await page.evaluate( () => getComputedStyle( document.querySelector( '.k-main' ) ).paddingBottom );
        expect( pad ).toBe( '48px' );

        await page.getByTestId( 'pages.bulk_clear' ).click();
        await expect( bar ).toBeHidden();
        await expect( page.locator( 'tr[aria-selected="true"]' ) ).toHaveCount( 0 );
    } );

    test( 'select-all uses the indeterminate property AND aria-checked=mixed', async ( { page } ) => {
        await openPages( page );
        const boxes = page.locator( '.k-row-check' );
        const total = await boxes.count();
        test.skip( total < 2, 'needs at least two rows to reach the mixed state' );

        await boxes.first().check();
        const selectAll = page.getByTestId( 'pages.select_all' );

        await expect( selectAll ).toHaveAttribute( 'aria-checked', 'mixed' );
        const indeterminate = await selectAll.evaluate( ( el ) => el.indeterminate );
        expect( indeterminate ).toBe( true );

        await selectAll.check();
        await expect( selectAll ).not.toHaveAttribute( 'aria-checked', 'mixed' );
        await expect( page.locator( 'tr[aria-selected="true"]' ) ).toHaveCount( total );
    } );

    test( 'sorting is a link with aria-sort on the <th>, and it is a page load', async ( { page } ) => {
        await openPages( page );
        const titleHead = page.getByTestId( 'pages.sort.title' ).locator( '..' );

        // Unsorted: no aria-sort at all (omitted, not "none").
        await expect( titleHead ).not.toHaveAttribute( 'aria-sort', /.+/ );

        await page.getByTestId( 'pages.sort.title' ).click();
        await expect( page ).toHaveURL( /sort=title/ );
        await expect( page.getByTestId( 'pages.sort.title' ).locator( '..' ) )
            .toHaveAttribute( 'aria-sort', 'ascending' );

        await page.getByTestId( 'pages.sort.title' ).click();
        await expect( page.getByTestId( 'pages.sort.title' ).locator( '..' ) )
            .toHaveAttribute( 'aria-sort', 'descending' );
    } );

    test( 'a filter chip is a link carrying aria-current, never a tab or a button', async ( { page } ) => {
        await openPages( page );
        const chip = page.getByTestId( 'pages.chip.published' );

        expect( await chip.evaluate( ( el ) => el.tagName ) ).toBe( 'A' );
        await expect( chip ).toHaveAttribute( 'href', /.+/ );
        await expect( chip ).not.toHaveAttribute( 'role', 'tab' );

        await chip.click();
        await expect( page.getByTestId( 'pages.chip.published' ) ).toHaveAttribute( 'aria-current', 'true' );
        // "Clear filters" appears whenever any filter is not the default.
        await expect( page.getByTestId( 'pages.clear_filters' ) ).toBeVisible();
    } );

    test( 'the site home renders its delete as disabled with the reason in its name', async ( { page } ) => {
        // The site home is the page whose slug is `index` — build-engine.php
        // routes exactly that slug to /index.html. The seeded playground has no
        // such page, so this test creates one rather than skipping.
        createIndexPage();

        await openPages( page );
        const disabled = page.locator( '[data-testid^="pages.actions_disabled."]' );

        await expect( disabled ).toHaveCount( 1 );
        await expect( disabled.first() ).toHaveAttribute( 'aria-disabled', 'true' );
        const name = await disabled.first().getAttribute( 'aria-label' );
        // The reason is IN the accessible name — hiding the action teaches
        // nothing, and a bare "Delete" teaches nothing either.
        expect( name.toLowerCase() ).toContain( 'home' );
    } );

    // -----------------------------------------------------------------
    // Responsive — template-list-table.md §3
    // -----------------------------------------------------------------

    test( 'at 1024px the table scrolls inside a labelled group and the row header is sticky', async ( { page } ) => {
        await page.setViewportSize( { width: 1024, height: 800 } );
        await openPages( page );

        const overflow = await page.evaluate( () => getComputedStyle( document.querySelector( '.k-table-scroll' ) ).overflowX );
        expect( overflow ).toBe( 'auto' );

        const sticky = await page.evaluate( () => getComputedStyle( document.querySelector( '.k-table tbody th' ) ).position );
        expect( sticky ).toBe( 'sticky' );
    } );

    test( 'under 900px the table is replaced by stacked cards, and only one is in the tree', async ( { page } ) => {
        await page.setViewportSize( { width: 800, height: 800 } );
        await openPages( page );

        // The markup change is real: the <dl> is present and the table is gone
        // from the page, so the ARIA table roles go with it.
        await expect( page.getByTestId( 'pages.reclist' ) ).toBeVisible();
        await expect( page.getByTestId( 'pages.table' ) ).toBeHidden();

        const recs = page.locator( '.k-rec' );
        expect( await recs.count() ).toBeGreaterThan( 0 );
        await expect( recs.first().locator( 'h3' ) ).toBeVisible();
        await expect( recs.first().locator( 'dl dt' ).first() ).toBeVisible();
    } );

    test( 'at 320 CSS px the page does not scroll horizontally (1.4.10)', async ( { page } ) => {
        await page.setViewportSize( { width: 320, height: 800 } );
        await openPages( page );

        // Asserted by TRYING TO SCROLL, not by comparing scrollWidth: the two
        // disagreed once and the disagreement was the finding (L-032).
        const scrolled = await page.evaluate( () => {
            window.scrollTo( 5000, 0 );
            return window.scrollX;
        } );
        expect( scrolled ).toBe( 0 );
    } );

    for ( const theme of [ 'light', 'dark' ] ) {
        test( `the stacked-card layout passes axe at WCAG 2.2 AA — ${ theme }`, async ( { page } ) => {
            await page.setViewportSize( { width: 800, height: 800 } );
            await openPages( page, { theme } );
            const violations = await axeScan( page, '.k-reclist' );
            expect( violations.map( ( v ) => `${ v.id }: ${ v.nodes.length }` ) ).toEqual( [] );
        } );
    }

    // -----------------------------------------------------------------
    // Writes — driven, including the failure branch
    // -----------------------------------------------------------------

    test( 'bulk unpublish and bulk publish both reload the list with a role=status line', async ( { page } ) => {
        // Both directions are driven, and neither is skipped: the seeded pages
        // are PUBLISHED, so unpublish runs first and publish restores them.
        // Reading the caption before and after is what proves the write landed
        // — a status line can be rendered by a handler that changed nothing.
        await openPages( page, { query: '?status=published' } );
        await expect( page.locator( '.k-row-check' ) ).not.toHaveCount( 0 );

        await page.locator( '.k-row-check' ).first().check();
        await page.getByTestId( 'pages.bulk_action' ).selectOption( 'unpublish' );
        await Promise.all( [
            page.waitForLoadState( 'load' ),
            page.getByTestId( 'pages.bulk_apply' ).click(),
        ] );

        const line = page.getByTestId( 'pages.status_line' );
        await expect( line ).toBeVisible();
        await expect( line ).toHaveAttribute( 'role', 'status' );

        // The record really moved: it is in the draft view now.
        await openPages( page, { query: '?status=draft' } );
        await expect( page.locator( '.k-row-check' ) ).not.toHaveCount( 0 );

        // And back, so the branch that publishes is driven too.
        await page.locator( '.k-row-check' ).first().check();
        await page.getByTestId( 'pages.bulk_action' ).selectOption( 'publish' );
        await Promise.all( [
            page.waitForLoadState( 'load' ),
            page.getByTestId( 'pages.bulk_apply' ).click(),
        ] );
        await expect( page.getByTestId( 'pages.status_line' ) ).toBeVisible();
    } );

    test( 'a POST without a CSRF token changes nothing', async ( { page, request } ) => {
        await openPages( page );
        const before = await page.getByTestId( 'pages.caption_text' ).textContent();

        const res = await request.post( PAGES_URL, {
            form: { action: 'bulk_action', bulk_action: 'delete', 'bulk_slugs[]': 'about' },
        } );
        expect( res.status() ).toBeLessThan( 500 );

        await page.reload();
        await expect( page.getByTestId( 'pages.caption_text' ) ).toHaveText( before );
    } );

    // -----------------------------------------------------------------
    // Capability — the screen is readable by a viewer and not writable
    // -----------------------------------------------------------------

    test( 'a viewer reads the list and is offered no write control', async ( { page } ) => {
        await page.context().clearCookies();
        await login( page, 'viewer' );
        await page.goto( PAGES_URL );

        await expect( page.getByTestId( 'pages.table' ) ).toBeVisible();
        await expect( page.getByTestId( 'pages.select_all' ) ).toHaveCount( 0 );
        await expect( page.locator( '.k-row-check' ) ).toHaveCount( 0 );
        await expect( page.getByTestId( 'pages.bulkbar' ) ).toHaveCount( 0 );
        await expect( page.getByTestId( 'pages.create' ) ).toHaveCount( 0 );
    } );
} );
