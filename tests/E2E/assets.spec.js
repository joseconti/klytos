// Manifest entry 4 (Assets) — the first consumer of `template-gallery-grid.md`,
// driven in a browser.
//
// The server-rendered contract is already pinned by `AssetsHttpTest` and is NOT
// repeated here. What this file measures is everything the server cannot see:
// geometry, colour, both themes, the reflow, the focus rings the template
// specifies per element, and the hover-revealed actions — which are the one part
// of §2 that a markup assertion genuinely cannot reach.
//
// THE ASSERTION THIS FILE EXISTS FOR is that the tile's actions stay REACHABLE.
// §2 says "Actions are in the DOM at all times", and the obvious way to build a
// hover reveal is `display: none` until hover — which removes the control from
// the tab order and is invisible to every server-side test. This one measures it
// with a keyboard.
//
// @license GPL-3.0-or-later
// @copyright Copyright (c) 2026 José Conti — https://klytos.io

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );
const ASSETS = '/installer/admin/assets.php';

function fixture( args = [] ) {
	return execFileSync(
		'php',
		[ path.join( REPO_ROOT, 'tests', 'E2E', 'fixtures', 'reset-assets.php' ), ...args ],
		{ encoding: 'utf8' }
	);
}

/** Open with the theme baked in BEFORE first paint, and assert it took. */
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

// ─────────────────────────────────────────────────────────────────
// THE TEMPLATE'S GEOMETRY — §1
// ─────────────────────────────────────────────────────────────────

test( 'the preview area is 96px and its glyph is sized, not the SVG default', async ( { page } ) => {
	await open( page, ASSETS );

	const previews = page.locator( '.k-tile-preview' );
	const count = await previews.count();
	expect( count, 'the fixture put tiles on the page' ).toBe( 4 );

	for ( let i = 0; i < count; i++ ) {
		const box = await previews.nth( i ).boundingBox();
		// §1: "preview 96px (assets)".
		expect( box.height, `preview ${ i } height` ).toBeCloseTo( 96, 0 );
	}

	// The non-image tiles draw a glyph, and `klytos_admin_icon()` writes an
	// <svg> with no width and no height — which renders at 300 x 150. That
	// shipped on entry 44's stat tiles for a whole commit (L-048), so every new
	// consumer measures it.
	const glyphs = page.locator( '.k-tile-glyph > svg' );
	const glyphCount = await glyphs.count();
	expect( glyphCount, 'two non-image tiles draw a glyph' ).toBe( 2 );

	for ( let i = 0; i < glyphCount; i++ ) {
		const box = await glyphs.nth( i ).boundingBox();
		expect( box.width, `glyph ${ i } width — 300 means unsized` ).toBeCloseTo( 32, 0 );
		expect( box.height, `glyph ${ i } height — 150 means unsized` ).toBeCloseTo( 32, 0 );
	}
} );

test( 'the grid really wraps into columns, not one tile per row', async ( { page } ) => {
	await page.setViewportSize( { width: 1440, height: 1000 } );
	await open( page, ASSETS );

	// `repeat(auto-fill, minmax(180px, 1fr))` at 1440 must give several tracks.
	// A grid that silently collapsed to one column is the defect entry 18
	// shipped on its tables, where every assertion passed and only the capture
	// saw it (D-113).
	const tracks = await page.locator( '.k-gallery' )
		.evaluate( ( el ) => getComputedStyle( el ).gridTemplateColumns.split( /\s+/ ).length );

	expect( tracks, 'more than one column at 1440 CSS px' ).toBeGreaterThan( 1 );
} );

// ─────────────────────────────────────────────────────────────────
// §2 — the states a server-side test cannot reach
// ─────────────────────────────────────────────────────────────────

test( 'the tile actions are hidden until hover but NEVER leave the tab order', async ( { page } ) => {
	await open( page, ASSETS );

	const firstDelete = page.locator( '.k-tile-actions button:not([disabled])' ).first();

	// §2: "Actions are in the DOM at all times." In the DOM is not enough — the
	// obvious build (`display: none` until hover) also removes the control from
	// the tab order, which no markup assertion can see. Focus is the measurement.
	await firstDelete.focus();

	await expect( firstDelete ).toBeFocused();

	// POLLED, not read once: the reveal is a 120ms transition, and a single read
	// lands mid-flight. That is D-078's defect exactly — a contrast taken during
	// a transition reported a button at 2.59:1 that is 4.86:1 — arriving here on
	// opacity instead of colour.
	await expect.poll(
		async () => Number( await firstDelete.evaluate(
			( el ) => getComputedStyle( el.closest( '.k-tile-actions' ) ).opacity
		) ),
		{ message: 'focusing inside the tile reveals its actions' }
	).toBe( 1 );
} );

test( 'the primary link and the action take their own focus ring', async ( { page } ) => {
	await open( page, ASSETS );

	// §2 Focus: "Where a tile has more than one action, the tile is a <div>
	// containing a primary <a> plus an actions <button>, and each takes its own
	// ring — never a nested-interactive tile."
	const tile = page.locator( '.k-tile' ).first();

	expect(
		await tile.evaluate( ( el ) => el.tagName ),
		'the tile is not itself an anchor'
	).not.toBe( 'A' );

	expect(
		await tile.locator( 'a a' ).count(),
		'no anchor nested inside another anchor'
	).toBe( 0 );

	for ( const selector of [ '.k-tile-primary', '.k-tile-actions button:not([disabled])' ] ) {
		const control = tile.locator( selector ).first();
		await control.focus();

		const ring = await control.evaluate( ( el ) => {
			const s = getComputedStyle( el );
			return { width: s.outlineWidth, style: s.outlineStyle };
		} );

		expect( ring.style, `${ selector } outline-style on focus` ).not.toBe( 'none' );
		expect( parseFloat( ring.width ), `${ selector } outline-width on focus` ).toBeGreaterThan( 0 );
	}
} );

test( 'a disabled delete is not focusable and still announces its reason', async ( { page } ) => {
	await open( page, ASSETS );

	const blocked = page.locator( '.k-tile-actions button[disabled]' );
	await expect( blocked ).toHaveCount( 1 );

	// The reason is in the accessible NAME, so it survives having no tooltip.
	const label = await blocked.getAttribute( 'aria-label' );
	expect( label ).toMatch( /used on 1/ );
} );

test( 'nothing on the page prints a placeholder or the wrong heading', async ( { page } ) => {
	await open( page, ASSETS );

	// THREE defects the CAPTURE found and every assertion passed (D-119):
	//
	//   1. `Max size: {size}MB` — the placeholder printed raw, because the
	//      screen had no way to ask the manager for the real limit.
	//   2. the usage count missing — the tile said "Usages" with no number,
	//      where §4's tile draws "thumbnail · filename · size · usage count".
	//   3. the `<h1>` reading "Files", where `SPEC/navigation.md:62` states
	//      outright that "the screen's <h1> is **Assets**".
	//
	// None of them is reachable from markup structure, which is why the server
	// tier sailed past all three. They are text.
	const body = await page.locator( 'body' ).innerText();

	expect( body, 'an unsubstituted placeholder reached the page' ).not.toMatch( /\{[a-z_]+\}/ );

	await expect( page.locator( 'h1' ) ).toHaveText( 'Assets' );

	await expect(
		page.locator( '[data-testid^="assets.usage."]' ).filter( { hasText: /\d/ } )
	).toHaveCount( 1 );
} );

// ─────────────────────────────────────────────────────────────────
// Accessibility and reflow
// ─────────────────────────────────────────────────────────────────

for ( const theme of [ 'dark', 'light' ] ) {
	test( `axe is clean on the whole page — ${ theme }`, async ( { page } ) => {
		await open( page, ASSETS, theme );

		const results = await axeWholePage( page );

		expect(
			results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` ),
			`assets (${ theme })`
		).toEqual( [] );
	} );

	test( `the "No alt text" chip carries its warning tint — ${ theme }`, async ( { page } ) => {
		await open( page, ASSETS, theme );

		const chip = page.locator( '[data-testid^="assets.no_alt."]' );
		await expect( chip ).toHaveCount( 1 );

		// The tint carries its own text colour — a semantic colour on its own
		// tint is what the badge rule forbids, and `.k-banner--aviso` already
		// sets the precedent.
		const seen = await chip.evaluate( ( el ) => {
			const s = getComputedStyle( el );
			return { color: s.color, background: s.backgroundColor };
		} );

		expect( seen.background, 'the chip is tinted, not transparent' ).not.toBe( 'rgba(0, 0, 0, 0)' );
		expect( seen.color ).not.toBe( seen.background );
	} );
}

test( 'the file-type pill is pinned at its measured floor while DR-005 is open', async ( { page } ) => {
	await open( page, ASSETS, 'light' );

	// `.k-tile-pill` is excluded from the whole-page scan because §1 specifies
	// both halves of the pair (DR-005 addendum 4). An open request is not a
	// licence to regress, so the ratio is measured here and asserted not to fall
	// below what was reported.
	const seen = await page.locator( '.k-tile-pill' ).first().evaluate( ( el ) => {
		const s = getComputedStyle( el );
		return { color: s.color, background: s.backgroundColor };
	} );

	const luminance = ( css ) => {
		const [ r, g, b ] = css.match( /[\d.]+/g ).slice( 0, 3 ).map( Number );
		const channel = ( c ) => {
			const v = c / 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		};
		return 0.2126 * channel( r ) + 0.7152 * channel( g ) + 0.0722 * channel( b );
	};

	const a = luminance( seen.color );
	const b = luminance( seen.background );
	const ratio = ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );

	expect( ratio, 'the pill must not fall below the ratio DR-005 recorded' ).toBeGreaterThanOrEqual( 3.4 );
} );

test( 'WCAG 1.4.10 — 320 CSS px does not scroll sideways', async ( { page } ) => {
	await page.setViewportSize( { width: 320, height: 900 } );
	await open( page, ASSETS );

	// Asserted by TRYING to scroll, not by reading a width: a value read off the
	// document can be right while the page still moves (D-079).
	const moved = await page.evaluate( () => {
		const before = window.scrollX;
		window.scrollTo( 400, 0 );
		const after = window.scrollX;
		window.scrollTo( before, 0 );
		return after - before;
	} );

	expect( moved, 'the assets grid scrolls horizontally at 320 CSS px' ).toBe( 0 );
} );

test( 'the alt-text chip leads to a field that is really on the page', async ( { page } ) => {
	await open( page, ASSETS );

	await page.locator( '[data-testid^="assets.no_alt."]' ).click();

	// The destination is server-rendered — the shipped alt field existed only in
	// JavaScript, so §4's "that chip is a link to the asset's alt field" had
	// nowhere to point.
	const alt = page.locator( '[data-testid="assets.detail_alt"]' );
	await expect( alt ).toBeVisible();
	await expect( alt ).toHaveValue( '' );
} );

// ─────────────────────────────────────────────────────────────────
// CAPTURE AND LOOK — L-048, which has bitten this build ten times
// ─────────────────────────────────────────────────────────────────

for ( const theme of [ 'dark', 'light' ] ) {
	for ( const width of [ 1440, 320 ] ) {
		test( `capture ${ theme } @ ${ width }`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 1000 } );
			await open( page, ASSETS, theme );

			await page.screenshot( {
				path: `tests/E2E/artifacts/captures/assets-${ theme }-${ width }.png`,
				fullPage: true,
			} );
		} );
	}
}
