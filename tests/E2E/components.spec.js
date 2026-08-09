// Phase 4 Step 4, stage 3 of 6 — the component layer, driven.
//
// A stylesheet has no consumer until stage 4 builds a screen, and "the CSS looks
// right" is a reading, not a test. So this suite drives the component SPECIMEN
// (tests/E2E/fixtures/components-specimen.html — a test fixture, never a product
// URL) and asserts three separate things:
//
//   1. THE ACCESSIBILITY PASS RUNS PER STATE, NOT PER PAGE. axe-core is scoped to
//      one component section at a time, in BOTH themes — 2 × 12 scoped runs. A
//      whole-page run reports "0 violations" while an empty table, a field showing
//      its error and a disabled control were never in the tree; per-state is the
//      only version of this check that means anything.
//
//   2. THE COMPUTED VALUES ARE READ BACK FROM THE BROWSER. Every geometry
//      assertion below reads getComputedStyle on a rendered element, because the
//      question is what the browser draws, not what the file says. That is the
//      whole point of build rule 1 and of the D-077 defect where a base rule sat
//      after its media query and won on source order — reading the file had
//      agreed with itself twice.
//
//   3. THE TABLE'S ACCESSIBILITY TREE SURVIVES ITS OWN LAYOUT. `display:grid` on
//      <table>/<tr> strips the implicit roles in Chromium and WebKit; that is the
//      documented reason accessibility.md §2.1 writes every role out explicitly.
//      This suite asserts the grid IS applied and the roles ARE still exposed, in
//      the same run — which is the only way to know both halves shipped.
//
// The specimen is served through route interception at a /installer/admin/ URL so
// the page has the playground's real origin: the stylesheet chain is the real one
// and a cross-document <use href> on the icon sprite would be same-origin. The
// route never touches the router, which serves nothing outside /installer/.

const fs = require( 'fs' );
const path = require( 'path' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { test, expect } = require( './fixtures' );

const SPECIMEN_URL = '/installer/admin/__component-specimen.html';
const SPECIMEN_FILE = path.join( __dirname, 'fixtures', 'components-specimen.html' );

const THEMES = [ 'light', 'dark' ];

// The two sections holding a real <table>. They are the only place one axe rule
// is disabled, and the reason is spelled out at the call site.
const TABLE_SECTIONS = [ 'specimen-table', 'specimen-table-empty' ];

// Contrast pairs the DELIVERY specifies and which measure below WCAG 2.2 AA.
// None of these is a build choice: each is a value SPEC/screens/*.md or the
// README states outright, in a composition the delivery's own 72-pair audit
// never measured. The build must not substitute a colour Design did not choose
// (Phase 4 rules 2 and 3), so each is registered in DR-005 and excluded here
// with its measured ratio — visible, counted, and not reported as coverage.
//
// docs/design/design-requests/DR-005.md · opened 2026-07-29 (D-078).
const KNOWN_DELIVERY_GAPS = [
    {
        dr: 'DR-005 gap 1',
        selector: '#specimen-table tr[aria-selected="true"] .k-badge',
        what: 'a semantic badge tint composited over --fila-seleccion',
        measured: 'light 4.44:1 · dark 3.08:1 (need 4.5)',
    },
    {
        dr: 'DR-005 gap 2',
        selector: '#specimen-code .k-code',
        what: '--texto-secundario and --color-acento on the --fondo-ventana payload panel',
        measured: 'light 4.46:1 (values) and 4.23:1 (the focus line); dark passes',
    },
    {
        dr: 'DR-005 gap 3',
        selector: '.k-error, .k-bulkbar .k-btn--destructive',
        what: '--color-peligro on --fondo-elevado, i.e. any error text or destructive button inside a card',
        measured: 'dark 4.32:1 (need 4.5); light passes at 5.39:1',
    },
];

// One entry per component section in the specimen. The axe pass walks this list;
// adding a component to the specimen without adding it here would leave it
// unchecked, so the list is asserted against the DOM in its own test below.
const SECTIONS = [
    'specimen-button',
    'specimen-badge',
    'specimen-chip',
    'specimen-table',
    'specimen-table-empty',
    'specimen-bulkbar',
    'specimen-field',
    'specimen-switch',
    'specimen-stat',
    'specimen-progress',
    'specimen-code',
    'specimen-feedback',
];

/**
 * Serve the specimen at a real same-origin URL and open it in the given theme.
 *
 * THE THEME IS BAKED INTO THE MARKUP BEFORE THE PAGE LOADS, never toggled after
 * paint — and that is not a convenience, it is correctness twice over. It is how
 * the product actually works (the shell renders `<html data-theme>` server-side
 * from the cookie so the first paint is already right — D-075), and toggling it
 * afterwards produced a measurement that lied: `.k-btn` transitions `color` over
 * 120ms, so getComputedStyle during that window returns the INTERPOLATED value.
 * The first run of this suite read the destructive button as the light-theme red
 * on a dark background and reported a 2.59:1 contrast failure for a button that
 * is 4.86:1 once it settles. Two individually-correct things — a specified
 * transition and a test that switches themes — composing into a false failure.
 */
async function openSpecimen( page, theme ) {
    const html = fs
        .readFileSync( SPECIMEN_FILE, 'utf8' )
        .replace( '<html lang="en" data-theme="light">', `<html lang="en" data-theme="${ theme }">` );
    await page.route( `**${ SPECIMEN_URL }`, ( route ) =>
        route.fulfill( { status: 200, contentType: 'text/html; charset=utf-8', body: html } )
    );
    await page.goto( SPECIMEN_URL );
    expect(
        await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
        'the theme substitution did not take — the specimen markup must have changed'
    ).toBe( theme );
    // The token layer must actually have arrived: an unstyled specimen would pass
    // every structural assertion below and prove nothing about the design.
    const accent = await page.evaluate( () =>
        getComputedStyle( document.documentElement ).getPropertyValue( '--color-acento' ).trim()
    );
    expect( accent, 'the delivered token layer did not load' ).not.toBe( '' );
}

test.describe( 'Component layer — the specimen renders with the real stylesheet chain', () => {
    test( 'the section list this suite walks matches the specimen exactly', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const inDom = await page.evaluate( () =>
            Array.from( document.querySelectorAll( 'main > section[id]' ) ).map( ( s ) => s.id )
        );
        // Not a soft check: a component added to the specimen and forgotten here
        // would be a component with no accessibility pass, reported as covered.
        expect( inDom ).toEqual( SECTIONS );
    } );

    test( 'exactly one h1 and no skipped heading level', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const levels = await page.evaluate( () =>
            Array.from( document.querySelectorAll( 'h1,h2,h3,h4,h5,h6' ) ).map( ( h ) =>
                Number( h.tagName.slice( 1 ) )
            )
        );
        expect( levels.filter( ( l ) => l === 1 ) ).toHaveLength( 1 );
        for ( let i = 1; i < levels.length; i++ ) {
            expect(
                levels[ i ] - levels[ i - 1 ],
                `heading level jumped from h${ levels[ i - 1 ] } to h${ levels[ i ] }`
            ).toBeLessThanOrEqual( 1 );
        }
    } );
} );

/* ============================================================
   THE PER-STATE ACCESSIBILITY PASS — 12 sections × 2 themes.
   ============================================================ */

for ( const theme of THEMES ) {
    for ( const section of SECTIONS ) {
        test( `axe WCAG 2.2 AA — ${ section } (${ theme })`, async ( { page } ) => {
            await openSpecimen( page, theme );

            let builder = new AxeBuilder( { page } )
                // 2.2 AA is the floor D-007 commits to, so the tag set is exactly
                // that ladder and nothing is quietly excluded.
                .withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
                .include( `#${ section }` );

            if ( TABLE_SECTIONS.includes( section ) ) {
                // THE ONE RULE THIS SUITE DISABLES, AND THE ONLY ONE — with the
                // evidence, because "we turned the check off" is otherwise
                // indistinguishable from hiding a defect.
                //
                // axe's `aria-required-children` fires on `<table role="table">`
                // because an explicitly-roled table may own only rowgroup/row,
                // and a <caption> is neither. But <caption> is required here:
                // accessibility.md §2.1 mandates it, carrying the result count as
                // the visible heading row. The explicit role is equally mandated,
                // because `display:grid` strips the implicit one.
                //
                // The real accessibility tree was read back from Chromium rather
                // than argued about, and it is exactly right:
                //   table → caption + rowgroup → row → columnheader ×7
                //         → rowgroup → row [selected] → cell, rowheader, cell…
                // So the rule is wrong about this markup, and what it would have
                // protected is asserted directly by TWO tests below, which cover
                // different halves and were each proven against a planted defect:
                //   · "the explicit roles survive the grid" pins the MARKUP — it
                //     went red when `role="rowgroup"` was removed from <thead>.
                //   · the aria-snapshot test pins the OUTCOME — the tree Chromium
                //     actually builds. It stayed green on that same plant, and
                //     correctly so: the implicit rowgroup still survived, which
                //     is precisely the redundancy §2.1 asks for. Stated plainly
                //     because it means the snapshot alone would NOT catch a lost
                //     explicit role; the pair is what covers this, not either one.
                builder = builder.disableRules( [ 'aria-required-children' ] );
            }

            // THREE CONTRAST PAIRS THE DELIVERY ITSELF SPECIFIES AND WHICH FAIL
            // AA. They are excluded by SELECTOR, never by disabling the rule, so
            // every other element in the section is still checked and any NEW
            // violation on these components still fails. Each is registered in
            // DR-005 with its measured ratio; when Design answers, this list
            // empties. Leaving the suite red instead was rejected for the reason
            // D-072 already recorded: a check that is red for weeks stops being
            // read, which is L-010's failure mode pointed at a verifier.
            for ( const gap of KNOWN_DELIVERY_GAPS ) {
                builder = builder.exclude( gap.selector );
            }

            const results = await builder.analyze();

            const summary = results.violations
                .map( ( v ) => `${ v.id } (${ v.impact }): ${ v.help }\n    ${ v.nodes.map( ( n ) => n.html ).join( '\n    ' ) }` )
                .join( '\n' );
            expect( results.violations, `axe violations in #${ section } (${ theme }):\n${ summary }` ).toEqual( [] );
        } );
    }
}

/* ============================================================
   THE TABLE — the layout and the accessibility tree, in one run.
   ============================================================ */

test.describe( 'Table — accessibility.md §2.1', () => {
    test( 'the grid is on the table elements, not on a wrapper', async ( { page } ) => {
        await openSpecimen( page, 'light' );

        const computed = await page.evaluate( () => {
            const table = document.querySelector( '#specimen-table .k-table' );
            const thead = table.querySelector( 'thead' );
            const tbody = table.querySelector( 'tbody' );
            const row = tbody.querySelector( 'tr' );
            return {
                table: getComputedStyle( table ).display,
                thead: getComputedStyle( thead ).display,
                tbody: getComputedStyle( tbody ).display,
                row: getComputedStyle( row ).display,
                columns: getComputedStyle( row ).gridTemplateColumns.split( ' ' ).length,
                gap: getComputedStyle( row ).columnGap,
            };
        } );

        expect( computed.table ).toBe( 'grid' );
        expect( computed.thead ).toBe( 'contents' );
        expect( computed.tbody ).toBe( 'contents' );
        expect( computed.row ).toBe( 'grid' );
        // The consuming screen declares seven columns; the component layer only
        // supplies the gap. Both halves have to be true for a real list to render.
        expect( computed.columns ).toBe( 7 );
        expect( computed.gap ).toBe( '12px' );
    } );

    test( 'the explicit roles survive the grid — every one of them', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const scope = page.locator( '#specimen-table' );

        // This is the assertion the whole "element AND role" decision exists for:
        // with display:grid applied, the implicit roles are gone in Chromium, so
        // anything found here is found because the markup wrote it out.
        await expect( scope.locator( 'table[role="table"]' ) ).toHaveCount( 1 );
        await expect( scope.locator( 'thead[role="rowgroup"]' ) ).toHaveCount( 1 );
        await expect( scope.locator( 'tbody[role="rowgroup"]' ) ).toHaveCount( 1 );
        await expect( scope.locator( 'tr[role="row"]' ) ).toHaveCount( 3 );
        await expect( scope.locator( 'thead th[role="columnheader"][scope="col"]' ) ).toHaveCount( 7 );

        // Exactly one rowheader per row — the column that names the record.
        const bodyRows = scope.locator( 'tbody tr[role="row"]' );
        const count = await bodyRows.count();
        for ( let i = 0; i < count; i++ ) {
            await expect( bodyRows.nth( i ).locator( 'th[role="rowheader"][scope="row"]' ) ).toHaveCount( 1 );
        }
    } );

    test( 'the accessibility tree Chromium actually builds is the one §2.1 describes', async ( { page } ) => {
        await openSpecimen( page, 'light' );

        // This is the assertion that earns the right to disable
        // `aria-required-children` for this section: instead of trusting a rule
        // that misreads a legal <caption>, read the tree Chromium exposes and
        // pin it. If display:grid ever did strip the roles — the exact failure
        // §2.1's belt-and-braces decision exists to survive — this snapshot
        // changes and the test fails.
        await expect( page.locator( '#specimen-table .k-table' ) ).toMatchAriaSnapshot( `
            - table "Pages — 34 results, page 1 of 3":
              - caption: Pages — 34 results, page 1 of 3
              - rowgroup:
                - row /Select all pages on this page Title Status/:
                  - columnheader "Select all pages on this page":
                    - checkbox "Select all pages on this page"
                  - columnheader "Title":
                    - link "Title"
                  - columnheader "Status"
                  - columnheader "Template"
                  - columnheader "Locale"
                  - columnheader "Last edit"
                  - columnheader "Actions"
              - rowgroup:
                - row /Pricing Published Marketing/:
                  - cell "Pricing":
                    - checkbox "Pricing"
                  - rowheader "Pricing":
                    - link "Pricing"
                  - cell "Published"
                  - cell "Marketing"
                  - cell "English"
                  - cell "21 Jul, 14:03":
                    - time: 21 Jul, 14:03
                  - cell "More actions for Pricing":
                    - link "More actions for Pricing"
                - row /About us Draft Default/:
                  - cell "About us":
                    - checkbox "About us"
                  - rowheader "About us":
                    - link "About us"
                  - cell "Draft"
                  - cell "Default"
                  - cell "Spanish"
                  - cell "20 Jul, 09:11":
                    - time: 20 Jul, 09:11
                  - cell "Delete — this page is the site home":
                    - link "Delete — this page is the site home"
        ` );
    } );

    test( 'the caption is visible, carries the count, and is a live region', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const caption = page.locator( '#specimen-table caption' );
        await expect( caption ).toBeVisible();
        await expect( caption ).toHaveText( /34 results, page 1 of 3/ );
        await expect( caption ).toHaveAttribute( 'aria-live', 'polite' );
    } );

    test( 'row checkboxes are named by the row header, never by an invented label', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const box = page.getByTestId( 'specimen.table.select.42' );
        const labelledBy = await box.getAttribute( 'aria-labelledby' );
        expect( labelledBy ).toBe( 'row-42-title' );
        await expect( page.locator( `#${ labelledBy }` ) ).toHaveText( 'Pricing' );
        await expect( box ).not.toHaveAttribute( 'aria-label', /./ );
    } );

    test( 'the select-all checkbox reaches the mixed state through the DOM property AND aria-checked', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const all = page.getByTestId( 'specimen.table.select-all' );
        await all.evaluate( ( el ) => {
            el.indeterminate = true;
            el.setAttribute( 'aria-checked', 'mixed' );
        } );
        expect( await all.evaluate( ( el ) => el.indeterminate ) ).toBe( true );
        await expect( all ).toHaveAttribute( 'aria-checked', 'mixed' );
    } );

    test( "the record's name is primary text, not the accent link colour", async ( { page } ) => {
        await openSpecimen( page, 'light' );
        // Regression test for a cascade defect this suite found: the generic
        // in-table link rule is (0,2,1) and `.k-table tbody th a` is only
        // (0,1,3), so the row header's link was rendering accent-on-selection at
        // 4.11:1 while the stylesheet appeared to say otherwise. Asserted from
        // the computed value, which is the only version of this that is true.
        const selected = await page
            .locator( '#specimen-table tr[aria-selected="true"] th a' )
            .evaluate( ( el ) => getComputedStyle( el ).color );
        expect( selected ).toBe( 'rgb(29, 29, 31)' ); // --texto-primario, light
    } );

    test( 'the selected row is tinted and the row itself is never focusable', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const selected = page.locator( '#specimen-table tr[aria-selected="true"]' );
        const bg = await selected.evaluate( ( el ) => getComputedStyle( el ).backgroundColor );
        expect( bg ).not.toBe( 'rgba(0, 0, 0, 0)' );
        // template-list-table.md §2: "The row itself is not focusable and never
        // gets a ring."
        await expect( selected ).not.toHaveAttribute( 'tabindex', /./ );
    } );

    test( 'the scroll container is reachable by keyboard and labelled', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const scroll = page.locator( '#specimen-table .k-table-scroll' );
        await expect( scroll ).toHaveAttribute( 'role', 'group' );
        await expect( scroll ).toHaveAttribute( 'tabindex', '0' );
        await expect( scroll ).toHaveAttribute( 'aria-label', /scrollable/ );
    } );

    test( 'the empty result is a row inside the table, with the header row kept', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const table = page.locator( '#specimen-table-empty .k-table' ).first();
        await expect( table.locator( 'thead th' ) ).toHaveCount( 2 );
        const cell = table.locator( 'tbody .k-table-row-full td' );
        await expect( cell ).toHaveCount( 1 );
        await expect( cell ).toContainText( 'No pages yet' );
        // Never a bare "No results": a sentence plus the action.
        await expect( cell.locator( 'a' ) ).toHaveText( 'Create the first page' );
        const height = await cell.evaluate( ( el ) => el.getBoundingClientRect().height );
        expect( height ).toBeGreaterThanOrEqual( 120 );
    } );

    test( 'pagination is links, and the current page is not one', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const nav = page.locator( '#specimen-table nav[aria-label="Pagination"]' );
        await expect( nav.locator( 'button' ) ).toHaveCount( 0 );
        const current = nav.locator( '[aria-current="page"]' );
        await expect( current ).toHaveText( '1' );
        expect( await current.evaluate( ( el ) => el.tagName ) ).not.toBe( 'A' );
    } );
} );

/* ============================================================
   GEOMETRY — read back from the browser, never from the file.
   Each expectation names the delivery line it comes from.
   ============================================================ */

test.describe( 'Geometry matches the delivery, measured in the browser', () => {
    test( 'button heights: sm 28, default 34, auth 38 (README "Button")', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const h = async ( id ) =>
            page.getByTestId( id ).evaluate( ( el ) => el.getBoundingClientRect().height );
        expect( await h( 'specimen.button.primary' ) ).toBe( 34 );
        expect( await h( 'specimen.button.sm' ) ).toBe( 28 );
        expect( await h( 'specimen.button.auth' ) ).toBe( 38 );
    } );

    test( 'badge is a 20px pill; chip is a 24px pill (README "Badge", "Chip")', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const badge = page.locator( '#specimen-badge .k-badge' ).first();
        expect( await badge.evaluate( ( el ) => el.getBoundingClientRect().height ) ).toBe( 20 );
        const chip = page.getByTestId( 'specimen.chip.all' );
        expect( await chip.evaluate( ( el ) => el.getBoundingClientRect().height ) ).toBe( 24 );
    } );

    test( 'field control is 34px tall with radius 6 and the CONTROL border, not the separator', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const control = page.getByTestId( 'specimen.field.title' );
        const m = await control.evaluate( ( el ) => {
            const cs = getComputedStyle( el );
            return {
                height: el.getBoundingClientRect().height,
                radius: cs.borderTopLeftRadius,
                border: cs.borderTopColor,
            };
        } );
        expect( m.height ).toBe( 34 );
        expect( m.radius ).toBe( '6px' );
        // --borde-control is #86868B in light. --separador is rgba(0,0,0,.08) and
        // is 1.19:1 — it identifies nothing, which is why klytos-admin.css forbids
        // it as a control boundary (WCAG 1.4.11).
        expect( m.border ).toBe( 'rgb(134, 134, 139)' );
    } );

    test( 'checkbox draws 13px and radio 14px, and BOTH have a 24 × 24 hit area', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const box = page.getByTestId( 'specimen.check.published' );
        const radio = page.getByTestId( 'specimen.radio.public' );
        expect( await box.evaluate( ( el ) => el.getBoundingClientRect().width ) ).toBe( 13 );
        expect( await radio.evaluate( ( el ) => el.getBoundingClientRect().width ) ).toBe( 14 );

        // §7: the drawing does not change size — .k-hit-24 centres a 24 × 24
        // pseudo-element on the control. Measured from the pseudo-element itself,
        // because that IS the target.
        const hit = await box.evaluate( ( el ) => {
            const label = el.closest( '.k-hit-24' );
            const cs = getComputedStyle( label, '::before' );
            return { w: cs.width, h: cs.height };
        } );
        expect( hit.w ).toBe( '24px' );
        expect( hit.h ).toBe( '24px' );
    } );

    test( 'switch draws 38 × 22 and its row is at least 24 tall (§7)', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const sw = page.getByTestId( 'specimen.switch.follow' );
        const box = await sw.evaluate( ( el ) => {
            const r = el.getBoundingClientRect();
            const row = el.closest( '.k-switch-row' ).getBoundingClientRect();
            return { w: r.width, h: r.height, rowH: row.height };
        } );
        expect( box.w ).toBe( 38 );
        expect( box.h ).toBe( 22 );
        expect( box.rowH ).toBeGreaterThanOrEqual( 24 );
    } );

    test( 'the stat tile is 32px and the value is mono 20px semibold', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const tile = page.locator( '#specimen-stat .k-stat-tile' ).first();
        expect( await tile.evaluate( ( el ) => el.getBoundingClientRect().width ) ).toBe( 32 );
        const v = await page.locator( '#stat-1-v' ).evaluate( ( el ) => {
            const cs = getComputedStyle( el );
            return { size: cs.fontSize, weight: cs.fontWeight, family: cs.fontFamily };
        } );
        expect( v.size ).toBe( '20px' );
        expect( v.weight ).toBe( '600' );
        expect( v.family ).toContain( 'Geist Mono' );
    } );

    test( 'the progress track is 8px with a pill radius', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const m = await page.getByTestId( 'specimen.progress.upload' ).evaluate( ( el ) => {
            const cs = getComputedStyle( el );
            return { h: el.getBoundingClientRect().height, r: cs.borderTopLeftRadius };
        } );
        expect( m.h ).toBe( 8 );
        expect( m.r ).toBe( '999px' );
    } );

    test( 'card radius 10 and the table card pays no padding of its own', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const padded = await page.locator( '#specimen-field .k-card--padded' ).first().evaluate( ( el ) => {
            const cs = getComputedStyle( el );
            return { r: cs.borderTopLeftRadius, p: cs.paddingTop, shadow: cs.boxShadow };
        } );
        expect( padded.r ).toBe( '10px' );
        expect( padded.p ).toBe( '20px' );
        expect( padded.shadow ).not.toBe( 'none' );

        const tableCard = await page.locator( '#specimen-table .k-card--table' ).evaluate( ( el ) =>
            getComputedStyle( el ).paddingTop
        );
        expect( tableCard ).toBe( '0px' );
    } );

    test( 'body type resolves to the KLYTOS values, not the shadowed PackDesk ones', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        // Build rule 1: typography.css declares --type-body and --type-caption
        // TWICE and the Klytos block at the foot wins — 13px/17px and 11px/16px.
        // Asserted from the computed value so a reordering of that file fails
        // here rather than shipping a silently different scale.
        const body = await page.locator( '#specimen-table tbody td' ).first().evaluate( ( el ) => {
            const cs = getComputedStyle( el );
            return { size: cs.fontSize, line: cs.lineHeight };
        } );
        expect( body.size ).toBe( '13px' );
        expect( body.line ).toBe( '17px' );

        const hint = await page.locator( '#f-title-hint' ).evaluate( ( el ) => {
            const cs = getComputedStyle( el );
            return { size: cs.fontSize, line: cs.lineHeight };
        } );
        expect( hint.size ).toBe( '11px' );
        expect( hint.line ).toBe( '16px' );
    } );
} );

/* ============================================================
   STATE — the behaviour the CSS is supposed to express.
   ============================================================ */

test.describe( 'Component states', () => {
    test( 'every component is styled in BOTH themes — no rule that only exists in light', async ( { page } ) => {
        for ( const theme of THEMES ) {
            await openSpecimen( page, theme );
            const badge = await page.locator( '#specimen-badge .k-badge--exito' ).evaluate( ( el ) => {
                const cs = getComputedStyle( el );
                return { bg: cs.backgroundColor, fg: cs.color };
            } );
            expect( badge.bg, `badge tint missing in ${ theme }` ).not.toBe( 'rgba(0, 0, 0, 0)' );
            expect( badge.fg, `badge text colour missing in ${ theme }` ).not.toBe( '' );
        }
    } );

    test( 'badge text is --sobre-tinte-*, never the raw semantic colour on its own tint', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const fg = await page.locator( '#specimen-badge .k-badge--exito' ).evaluate( ( el ) =>
            getComputedStyle( el ).color
        );
        // --color-exito is #257D36 in light; --sobre-tinte-exito is #227231. The
        // whole reason klytos-admin.css exists is that the first fails AA on its
        // own tint, so painting it here would undo the audit's one fix.
        expect( fg ).toBe( 'rgb(34, 114, 49)' );
        expect( fg ).not.toBe( 'rgb(37, 125, 54)' );
    } );

    test( 'the selected chip is aria-current, not a tab and not a button', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const nav = page.locator( '#specimen-chip nav[aria-label="Filter by status"]' );
        await expect( nav.locator( '[role="tab"]' ) ).toHaveCount( 0 );
        await expect( nav.locator( 'button' ) ).toHaveCount( 0 );
        await expect( nav.locator( 'a.k-chip' ) ).toHaveCount( 4 );
        const selected = nav.locator( '[aria-current="true"]' );
        await expect( selected ).toHaveCount( 1 );
        const bg = await selected.evaluate( ( el ) => getComputedStyle( el ).backgroundColor );
        expect( bg ).not.toBe( 'rgba(0, 0, 0, 0)' );
    } );

    test( 'a disabled control keeps a legible border and is not hidden', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const wallet = page.getByTestId( 'specimen.field.wallet' );
        await expect( wallet ).toBeVisible();
        const border = await wallet.evaluate( ( el ) => getComputedStyle( el ).borderTopColor );
        // --borde-deshabilitado #B9B9BE: the 3:1 floor Klytos sets for a disabled
        // control, which 1.4.3 does not require and this project does.
        expect( border ).toBe( 'rgb(185, 185, 190)' );
        // The reason lives next to the label, not only in a tooltip.
        await expect( page.locator( '#f-wallet-hint' ) ).toContainText( 'Locked' );
        await expect( wallet ).not.toHaveAttribute( 'title', /./ );
    } );

    test( 'read-only is not disabled: still focusable, still selectable', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const key = page.getByTestId( 'specimen.field.key' );
        await expect( key ).not.toBeDisabled();
        await key.focus();
        await expect( key ).toBeFocused();
    } );

    test( 'a field error is four channels, not a colour', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const slug = page.getByTestId( 'specimen.field.slug' );
        // 1: aria-invalid. 2: the icon. 3: the word. 4: the border.
        await expect( slug ).toHaveAttribute( 'aria-invalid', 'true' );
        const described = ( await slug.getAttribute( 'aria-describedby' ) ).split( /\s+/ );
        expect( described ).toContain( 'f-slug-err' );
        // Hint first, then the error — the order accessibility.md §5.7 states.
        expect( described[ 0 ] ).toBe( 'f-slug-hint' );
        await expect( page.locator( '#f-slug-err .k-error-icon' ) ).toBeVisible();
        await expect( page.locator( '#f-slug-err' ) ).toContainText( 'Enter a slug' );
        const border = await slug.evaluate( ( el ) => getComputedStyle( el ).borderTopColor );
        expect( border ).toBe( 'rgb(192, 58, 53)' ); // --color-peligro, light
    } );

    test( 'focusing a field adds the accent border AND keeps the delivered ring', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const title = page.getByTestId( 'specimen.field.title' );
        await title.focus();
        const cs = await title.evaluate( ( el ) => {
            const s = getComputedStyle( el );
            return { border: s.borderTopColor, outlineWidth: s.outlineWidth, outlineStyle: s.outlineStyle };
        } );
        expect( cs.border ).toBe( 'rgb(14, 128, 116)' ); // --color-acento, light
        // The ring comes from klytos-admin.css and must keep winning: the
        // component layer never removes an outline without a substitute.
        expect( cs.outlineStyle ).toBe( 'solid' );
        expect( cs.outlineWidth ).toBe( '2px' );
    } );

    test( 'the switch reflects aria-checked and moves its thumb', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const on = page.getByTestId( 'specimen.switch.follow' );
        const off = page.getByTestId( 'specimen.switch.compact' );
        await expect( on ).toHaveAttribute( 'role', 'switch' );
        const onBg = await on.evaluate( ( el ) => getComputedStyle( el ).backgroundColor );
        const offBg = await off.evaluate( ( el ) => getComputedStyle( el ).backgroundColor );
        expect( onBg ).not.toBe( offBg );
        const onThumb = await on.locator( '.k-switch-thumb' ).evaluate( ( el ) =>
            el.getBoundingClientRect().left - el.parentElement.getBoundingClientRect().left
        );
        const offThumb = await off.locator( '.k-switch-thumb' ).evaluate( ( el ) =>
            el.getBoundingClientRect().left - el.parentElement.getBoundingClientRect().left
        );
        expect( onThumb ).toBeGreaterThan( offThumb );
    } );

    test( 'the empty stat renders an em dash, never a zero', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        // template-overview-stats.md §2: "— as the value (not 0, which is a
        // claim)". Same reasoning as the nine unwired nav counts (§5.9).
        await expect( page.locator( '#stat-4-v' ) ).toHaveText( '—' );
    } );

    test( 'a linked stat card is ONE anchor, not a chevron in its corner', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const card = page.getByTestId( 'specimen.stat.pages' );
        expect( await card.evaluate( ( el ) => el.tagName ) ).toBe( 'A' );
        await expect( card.locator( 'a' ) ).toHaveCount( 0 );
        const labelledBy = ( await card.getAttribute( 'aria-labelledby' ) ).split( /\s+/ );
        expect( labelledBy ).toEqual( [ 'stat-1-v', 'stat-1-l' ] );
    } );

    test( 'the log level is a word before it is a tint', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const log = page.getByTestId( 'specimen.code.log' );
        await expect( log ).toContainText( 'ERROR' );
        await expect( log ).toContainText( 'WARN' );
        await expect( log ).toHaveAttribute( 'tabindex', '0' );
        await expect( log ).toHaveAttribute( 'aria-label', /scrollable/ );
        // Only ERROR and WARN carry a line tint (template-console-stream.md §1).
        const tinted = await page.locator( '#specimen-code .k-line--error, #specimen-code .k-line--warn' ).count();
        expect( tinted ).toBe( 2 );
    } );

    test( 'the percentage is rendered as text beside the bar, not inside it', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        await expect( page.locator( '#specimen-progress .k-progress-value' ).first() ).toHaveText( '62 %' );
    } );

    test( 'the error summary is an alert that can take focus, listing links to the fields', async ( { page } ) => {
        await openSpecimen( page, 'light' );
        const summary = page.getByTestId( 'specimen.error.summary' );
        await expect( summary ).toHaveAttribute( 'role', 'alert' );
        await expect( summary ).toHaveAttribute( 'tabindex', '-1' );
        const links = summary.locator( 'a' );
        await expect( links ).toHaveCount( 2 );
        // Each entry is a link TO the field, so it is not merely a restatement.
        expect( await links.first().getAttribute( 'href' ) ).toBe( '#f-slug' );
    } );

    test( 'reduced motion flattens every transition the components declare', async ( { page } ) => {
        await page.emulateMedia( { reducedMotion: 'reduce' } );
        await openSpecimen( page, 'light' );
        const durations = await page.evaluate( () =>
            [ '.k-btn', '.k-chip', '.k-switch', '.k-switch-thumb' ].map( ( sel ) =>
                getComputedStyle( document.querySelector( sel ) ).transitionDuration
            )
        );
        // klytos-admin.css collapses these to 1ms; nothing here may reintroduce
        // a longer one.
        for ( const d of durations ) {
            expect( d ).toBe( '0.001s' );
        }
    } );
} );

/* ============================================================
   RESPONSIVE — the media queries at the foot of the stylesheet.
   The D-077 defect was a base rule that lost to source order, so each
   breakpoint is asserted at a real viewport rather than read.
   ============================================================ */

test.describe( 'Responsive behaviour, at real viewports', () => {
    test( 'at 1440 the table does not scroll and the row header is not sticky', async ( { page } ) => {
        await page.setViewportSize( { width: 1440, height: 976 } );
        await openSpecimen( page, 'light' );
        const m = await page.evaluate( () => {
            const scroll = document.querySelector( '#specimen-table .k-table-scroll' );
            const th = document.querySelector( '#specimen-table tbody th' );
            return {
                overflow: getComputedStyle( scroll ).overflowX,
                position: getComputedStyle( th ).position,
            };
        } );
        expect( m.overflow ).toBe( 'visible' );
        expect( m.position ).toBe( 'static' );
    } );

    test( 'at 1024 the table scrolls and the row header sticks left', async ( { page } ) => {
        await page.setViewportSize( { width: 1024, height: 800 } );
        await openSpecimen( page, 'light' );
        const m = await page.evaluate( () => {
            const scroll = document.querySelector( '#specimen-table .k-table-scroll' );
            const th = document.querySelector( '#specimen-table tbody th' );
            return {
                overflow: getComputedStyle( scroll ).overflowX,
                position: getComputedStyle( th ).position,
                left: getComputedStyle( th ).left,
            };
        } );
        expect( m.overflow ).toBe( 'auto' );
        expect( m.position ).toBe( 'sticky' );
        expect( m.left ).toBe( '0px' );
    } );

    test( 'at 800 card padding drops to 16 and the stat row goes single column', async ( { page } ) => {
        await page.setViewportSize( { width: 800, height: 800 } );
        await openSpecimen( page, 'light' );
        const padding = await page.locator( '#specimen-field .k-card--padded' ).first().evaluate( ( el ) =>
            getComputedStyle( el ).paddingTop
        );
        expect( padding ).toBe( '16px' );
        const columns = await page.locator( '#specimen-stat .k-stat-row' ).evaluate( ( el ) =>
            getComputedStyle( el ).gridTemplateColumns.split( ' ' ).length
        );
        expect( columns ).toBe( 1 );
    } );

    test( 'at 320 nothing forces the PAGE to scroll horizontally (1.4.10)', async ( { page } ) => {
        await page.setViewportSize( { width: 320, height: 800 } );
        await openSpecimen( page, 'light' );
        // Asserted by TRYING TO SCROLL, not by comparing scrollWidth. Both are
        // measured below because they disagreed once and the disagreement was
        // the finding: every containment measurement in the chain read correct
        // (280 inside 320 at every level, overflow-x:auto present) while the
        // page really did scroll 346px, because a position:absolute `.k-sr`
        // escaped two overflow:hidden boxes. Scrolling is what a person
        // experiences, so it is the assertion that decides.
        const overflow = await page.evaluate( () => {
            window.scrollTo( 5000, 0 );
            const scrolledX = window.scrollX;
            window.scrollTo( 0, 0 );
            return {
                scrolledX,
                doc: document.documentElement.scrollWidth,
                client: document.documentElement.clientWidth,
            };
        } );
        expect( overflow.scrolledX, 'the page scrolled horizontally at 320 CSS px' ).toBe( 0 );
        // Data tables are the permitted exception and scroll inside their own
        // container; the page around them does not.
        expect( overflow.doc ).toBeLessThanOrEqual( overflow.client );
    } );
} );
