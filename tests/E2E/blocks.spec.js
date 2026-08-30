// Manifest entry 21 (Blocks) — the SECOND consumer of
// `template-gallery-grid.md`, driven in a browser.
//
// The layer's own contract was measured once on entry 4 (`assets.spec.js`) and
// is not repeated. What this file measures is what differs: the WIREFRAME
// preview, which §1 gives blocks where assets get a real thumbnail, and the
// per-category grouping §21 requires — plus axe in both themes, because a new
// component (`.k-wireframe`) is on the page and a new container is exactly where
// this build keeps finding contrast defects.
//
// @license GPL-3.0-or-later
// @copyright Copyright (c) 2026 José Conti — https://klytos.io

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );
const BLOCKS = '/installer/admin/blocks.php';

function fixture( args = [] ) {
	return execFileSync(
		'php',
		[ path.join( REPO_ROOT, 'tests', 'E2E', 'fixtures', 'reset-blocks.php' ), ...args ],
		{ encoding: 'utf8' }
	);
}

async function open( page, url, theme = 'dark' ) {
	await page.context().addCookies( [ {
		name: 'klytos_admin_theme',
		value: theme,
		url: new URL( page.url() ).origin,
	} ] );

	await page.goto( url );
	await expect( page.locator( 'h1' ) ).toBeVisible();

	expect(
		await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
		'the theme cookie did not take — this run measured the wrong theme'
	).toBe( theme );
}

async function axeWholePage( page ) {
	let builder = new AxeBuilder( { page } )
		.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] );

	for ( const gap of KNOWN_DELIVERY_GAPS ) {
		builder = builder.exclude( gap );
	}
	for ( const surface of DEV_ONLY_SURFACES ) {
		builder = builder.exclude( surface );
	}

	return builder.analyze();
}

test.beforeAll( () => {
	fixture();
} );

test.afterAll( () => {
	fixture( [ '--off' ] );
} );

test.beforeEach( async ( { page } ) => {
	await login( page, 'owner' );
} );

test( 'the wireframe preview is 120px, where an asset preview is 96px', async ( { page } ) => {
	await open( page, BLOCKS );

	const previews = page.locator( '.k-tile-preview--wireframe' );
	await expect( previews ).toHaveCount( 3 );

	for ( let i = 0; i < 3; i++ ) {
		const box = await previews.nth( i ).boundingBox();
		// §1 gives the two consumers different heights on purpose.
		expect( box.height, `wireframe ${ i } height` ).toBeCloseTo( 120, 0 );
	}
} );

test( 'the wireframe is decoration and is hidden from assistive technology', async ( { page } ) => {
	await open( page, BLOCKS );

	// A wireframe carries nothing the name and meta do not, so exposing it would
	// add noise a screen reader has to walk past. `aria-hidden` is the assertion
	// because it is the thing that would silently stop being true.
	await expect( page.locator( '.k-tile-preview--wireframe' ).first() )
		.toHaveAttribute( 'aria-hidden', 'true' );

	// The link is still named — by its text, not by the picture.
	const link = page.locator( '[data-testid^="blocks.tile_link."]' ).first();
	expect( ( await link.innerText() ).trim().length, 'the tile link has a text name' ).toBeGreaterThan( 0 );
} );

test( 'each category is its own group and each list is labelled by its heading', async ( { page } ) => {
	await open( page, BLOCKS );

	const lists = page.locator( 'ul.k-gallery' );
	await expect( lists ).toHaveCount( 3 );

	for ( let i = 0; i < 3; i++ ) {
		const labelledBy = await lists.nth( i ).getAttribute( 'aria-labelledby' );
		expect( labelledBy, 'the list points at a heading' ).toMatch( /^blocks-cat-/ );

		// And the heading it points at really exists on the page — an
		// `aria-labelledby` aimed at nothing is worse than none at all, and it
		// is invisible to every markup assertion that only checks the attribute.
		await expect( page.locator( `#${ labelledBy }` ) ).toHaveCount( 1 );
	}
} );

test( 'the category groups are separated from one another', async ( { page } ) => {
	await open( page, BLOCKS );

	// Three groups stacked with no rhythm put every <h2> flush against the tile
	// above it. The markup was correct and every assertion passed — the CAPTURE
	// is what showed it (D-120), which is the tenth time in this build that the
	// image was the only witness.
	const groups = page.locator( '.k-gallery-group' );
	await expect( groups ).toHaveCount( 3 );

	const gap = await page.evaluate( () => {
		const all = [ ...document.querySelectorAll( '.k-gallery-group' ) ];
		const first = all[ 0 ].getBoundingClientRect();
		const second = all[ 1 ].getBoundingClientRect();
		return second.top - first.bottom;
	} );

	expect( gap, 'consecutive groups are visibly separated' ).toBeGreaterThanOrEqual( 16 );
} );

test( 'the grid wraps into columns, not one tile per row', async ( { page } ) => {
	await page.setViewportSize( { width: 1440, height: 1000 } );
	await open( page, BLOCKS );

	const tracks = await page.locator( 'ul.k-gallery' ).first()
		.evaluate( ( el ) => getComputedStyle( el ).gridTemplateColumns.split( /\s+/ ).length );

	expect( tracks, 'more than one column at 1440 CSS px' ).toBeGreaterThan( 1 );
} );

for ( const theme of [ 'dark', 'light' ] ) {
	test( `axe is clean on the whole page — ${ theme }`, async ( { page } ) => {
		await open( page, BLOCKS, theme );

		const results = await axeWholePage( page );

		expect(
			results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` ),
			`blocks (${ theme })`
		).toEqual( [] );
	} );
}

test( 'WCAG 1.4.10 — 320 CSS px does not scroll sideways', async ( { page } ) => {
	await page.setViewportSize( { width: 320, height: 900 } );
	await open( page, BLOCKS );

	const moved = await page.evaluate( () => {
		const before = window.scrollX;
		window.scrollTo( 400, 0 );
		const after = window.scrollX;
		window.scrollTo( before, 0 );
		return after - before;
	} );

	expect( moved, 'the blocks gallery scrolls horizontally at 320 CSS px' ).toBe( 0 );
} );

test( 'nothing prints a placeholder and the usage counts differ from each other', async ( { page } ) => {
	await open( page, BLOCKS );

	const body = await page.locator( 'body' ).innerText();
	expect( body, 'an unsubstituted placeholder reached the page (D-119)' ).not.toMatch( /\{[a-z_]+\}/ );

	// Three blocks, three DIFFERENT answers. The first build of this screen read
	// a field nothing writes and printed "In no template" for every one of them
	// — a constant that looks entirely plausible on a tile (D-120).
	const counts = await page.locator( '[data-testid^="blocks.usage."]' ).allInnerTexts();
	expect( counts ).toHaveLength( 3 );
	expect( new Set( counts.map( ( c ) => c.trim() ) ).size, 'the counts are not all the same' ).toBe( 3 );
} );

for ( const theme of [ 'dark', 'light' ] ) {
	for ( const width of [ 1440, 320 ] ) {
		test( `capture ${ theme } @ ${ width }`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 1000 } );
			await open( page, BLOCKS, theme );

			await page.screenshot( {
				path: `tests/E2E/artifacts/captures/blocks-${ theme }-${ width }.png`,
				fullPage: true,
			} );
		} );
	}
}
