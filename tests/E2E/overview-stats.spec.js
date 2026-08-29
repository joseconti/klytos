// Manifest entries 44 (Dashboard), 13 (Tasks) and 18 (Agent payments) —
// the three built consumers of `template-overview-stats.md`, driven in a browser.
//
// ONE file for three screens, deliberately. What is under test here is mostly
// the TEMPLATE — the stat card, the tile, the chart pattern, the reflow — and
// three per-screen files would have carried three copies of it, which is L-004's
// shape. Each screen's own server-rendered contract is already pinned by its
// integration test (`DashboardHttpTest`, `TasksHttpTest`,
// `X402DashboardHttpTest`) and is deliberately NOT repeated here.
//
// THE ASSERTION THIS FILE EXISTS FOR is `the stat tile's glyph is 18 x 18`.
// `klytos_admin_icon()` writes an `<svg>` with no width and no height, and an
// `<svg>` with neither renders at the SVG DEFAULT of 300 x 150. That shipped on
// entry 44 for a whole commit (D-110 → D-111) and no server-side assertion could
// have seen it: geometry is only checked where somebody thought to measure it.
// That is L-048, and this is the measurement.
//
// @license GPL-3.0-or-later
// @copyright Copyright (c) 2026 José Conti — https://klytos.io

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );

const SCREENS = {
	dashboard: '/installer/admin/index.php',
	tasks: '/installer/admin/tasks.php',
	x402: '/installer/admin/x402-dashboard.php',
};

/** Run one of the two population fixtures through the real managers. */
function fixture( name, args = [] ) {
	return execFileSync(
		'php',
		[ path.join( REPO_ROOT, 'tests', 'E2E', 'fixtures', name ), ...args ],
		{ encoding: 'utf8' }
	);
}

/**
 * Open a screen with the theme baked in BEFORE the first paint.
 *
 * Toggling after load and reading a colour back mid-transition reported a
 * button at 2.59:1 that is 4.86:1 (D-078), and the product itself sets
 * `<html data-theme>` server-side from the cookie — so this is also how a real
 * visit works.
 */
async function open( page, url, theme = 'dark' ) {
	await page.context().addCookies( [ {
		name: 'klytos_admin_theme',
		value: theme,
		url: new URL( page.url() ).origin,
	} ] );

	await page.goto( url );
	await expect( page.locator( 'h1' ) ).toBeVisible();

	// ASSERT THE THEME ACTUALLY TOOK. A "both themes" claim with no check that
	// the second theme arrived is the exact shape of false green this project
	// keeps finding (D-088).
	expect(
		await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
		'the theme cookie did not take — this run measured the wrong theme'
	).toBe( theme );
}

/**
 * axe at WCAG 2.2 AA over the WHOLE PAGE.
 *
 * Never scoped to `#main`: L-037 is this project's record of what that costs —
 * the component on every screen is the one nothing scans. The delivery's
 * registered gaps are excluded ONE AT A TIME, because `exclude()` reads an
 * array as a CHAINED selector and would silently exclude nothing.
 */
async function axeWholePage( page ) {
	let builder = new AxeBuilder( { page } )
		.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] );

	for ( const gap of KNOWN_DELIVERY_GAPS ) {
		builder = builder.exclude( gap );
	}

	// The dev bar is not part of the product a normal install serves, and every
	// other whole-page scan in this tier excludes it. This file did not, which
	// is why its first run reported four violations that belong to nobody's
	// screen: `.devbar-tab-content` (scrollable, no focusable content) in both
	// themes and three `.devbar-*` contrast nodes in light — the exact set
	// `fixtures.js` already records against DEV_ONLY_SURFACES.
	for ( const surface of DEV_ONLY_SURFACES ) {
		builder = builder.exclude( surface );
	}

	return builder.analyze();
}

test.beforeAll( () => {
	fixture( 'reset-tasks.php' );
	fixture( 'reset-x402.php' );
} );

test.afterAll( () => {
	fixture( 'reset-tasks.php', [ '--off' ] );
	fixture( 'reset-x402.php', [ '--off' ] );
} );

test.beforeEach( async ( { page } ) => {
	await login( page, 'owner' );
} );

// ─────────────────────────────────────────────────────────────────
// THE TEMPLATE'S CONTRACT, measured on all three screens
// ─────────────────────────────────────────────────────────────────

for ( const [ name, url ] of Object.entries( SCREENS ) ) {
	test( `${ name }: the stat tile is 32px and its glyph is 18x18, not the SVG default`, async ( { page } ) => {
		await open( page, url );

		const tiles = page.locator( '.k-stat-tile' );
		const count = await tiles.count();
		expect( count, 'the screen must draw at least one stat card' ).toBeGreaterThan( 0 );

		for ( let i = 0; i < count; i++ ) {
			const tile = tiles.nth( i );
			const box = await tile.boundingBox();

			expect( box.width, `tile ${ i } width` ).toBeCloseTo( 32, 0 );
			expect( box.height, `tile ${ i } height` ).toBeCloseTo( 32, 0 );

			// The glyph itself. 300 x 150 is what an unsized <svg> renders at,
			// and it is what shipped on entry 44 for one commit.
			const glyph = await tile.locator( 'svg' ).boundingBox();
			expect( glyph.width, `tile ${ i } glyph width — 300 means unsized` ).toBeCloseTo( 18, 0 );
			expect( glyph.height, `tile ${ i } glyph height — 150 means unsized` ).toBeCloseTo( 18, 0 );
		}
	} );

	test( `${ name }: a linked stat card is ONE anchor and takes the ring around the whole card`, async ( { page } ) => {
		await open( page, url );

		const links = page.locator( 'a.k-stat' );
		const count = await links.count();

		if ( count === 0 ) {
			// Tasks and Agent payments link nowhere from their stat row; that is
			// the screen's own decision and not a template failure.
			test.skip( true, `${ name } has no linked stat card` );
			return;
		}

		for ( let i = 0; i < count; i++ ) {
			const card = links.nth( i );

			// ONE anchor wrapping the whole card — never a chevron in its corner.
			expect( await card.locator( 'a' ).count(), 'a stat card contains no second anchor' ).toBe( 0 );

			await card.focus();
			const ring = await card.evaluate( ( el ) => {
				const s = getComputedStyle( el );
				return { width: s.outlineWidth, style: s.outlineStyle };
			} );

			expect( ring.style, `stat card ${ i } outline-style on focus` ).not.toBe( 'none' );
			expect( parseFloat( ring.width ), `stat card ${ i } outline-width on focus` ).toBeGreaterThan( 0 );
		}
	} );

	test( `${ name }: WCAG 1.4.10 — 320 CSS px does not scroll sideways`, async ( { page } ) => {
		await page.setViewportSize( { width: 320, height: 900 } );
		await open( page, url );

		// Asserted by TRYING to scroll, not by reading a width: a value read off
		// the document can be right while the page still moves (D-079).
		const moved = await page.evaluate( () => {
			const before = window.scrollX;
			window.scrollTo( 400, 0 );
			const after = window.scrollX;
			window.scrollTo( before, 0 );
			return after - before;
		} );

		expect( moved, `${ name } scrolls horizontally at 320 CSS px` ).toBe( 0 );
	} );

	for ( const theme of [ 'dark', 'light' ] ) {
		test( `${ name }: a link inside a tinted banner takes the tint's colour, not the accent — ${ theme }`, async ( { page } ) => {
			await open( page, url, theme );

			const banner = page.locator( '.k-banner' ).first();
			if ( await banner.count() === 0 ) {
				test.skip( true, `${ name } renders no banner in this state` );
				return;
			}

			const link = banner.locator( 'a' ).first();
			if ( await link.count() === 0 ) {
				test.skip( true, `${ name }'s banner carries no link` );
				return;
			}

			// Read the COMPUTED values out of the browser rather than reasoning
			// about which rule wins — build rule 1, four times over in this build.
			const seen = await link.evaluate( ( el ) => ( {
				link: getComputedStyle( el ).color,
				container: getComputedStyle( el.closest( '.k-banner' ) ).color,
				decoration: getComputedStyle( el ).textDecorationLine,
			} ) );

			// `--color-acento` over `--tinte-aviso` measured 3.68:1 in light on
			// this very link. The tint's own `--sobre-tinte-*` is what the
			// delivery specifies for text on a tint, and it is what the
			// container already carries.
			expect( seen.link, 'banner link colour == the banner\'s own colour' ).toBe( seen.container );

			// Colour is not the only means of distinguishing the link (WCAG 1.4.1),
			// which is what taking the container's colour would otherwise cost.
			expect( seen.decoration, 'banner link is underlined' ).toContain( 'underline' );
		} );

		test( `${ name }: axe is clean on the whole page — ${ theme }`, async ( { page } ) => {
			await open( page, url, theme );

			const results = await axeWholePage( page );

			expect(
				results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` ),
				`${ name } (${ theme })`
			).toEqual( [] );
		} );
	}
}

// ─────────────────────────────────────────────────────────────────
// THE CHART PATTERN — entry 18, its only consumer so far
// ─────────────────────────────────────────────────────────────────

test( 'x402: the chart draws real bars and its <details> table is open beside it', async ( { page } ) => {
	await open( page, SCREENS.x402 );

	const svg = page.locator( '.k-chart-svg' );
	await expect( svg ).toBeVisible();

	// The chart is sized by the stylesheet AND by its own attributes. If either
	// were missing this box would be 300 x 150 or a full-width 150px strip.
	const box = await svg.boundingBox();
	expect( box.height, 'chart height' ).toBeCloseTo( 240, 0 );
	expect( box.width, 'chart width' ).toBeGreaterThan( 300 );

	// Seven transactions across three days: three bars, not thirty.
	expect( await page.locator( '.k-chart-bar' ).count() ).toBe( 3 );

	// §4's table equivalent, open and reachable without a click.
	await expect( page.locator( '[data-testid="x402.chart_table"]' ) ).toBeVisible();
} );

test( 'x402: the data table has real COLUMNS at 1440, not one cell per row', async ( { page } ) => {
	await page.setViewportSize( { width: 1440, height: 1000 } );
	await open( page, SCREENS.x402 );

	// `.k-table` is `display: grid` and `klytos-components.css` gives `tr` a
	// deliberate `grid-template-columns: 1fr` fallback so that a screen which
	// forgets to declare its own is "visibly wrong rather than subtly wrong".
	// Entry 18 forgot, and shipped: all three of its tables rendered ONE CELL
	// PER ROW at every width, turning a 1000px viewport into a 5731px page.
	//
	// Nothing asserted it and nothing could have at the level the tests were
	// written — the table WAS present, WAS open and DID follow the chart in the
	// DOM, all of which is true of a one-column table. It was found by looking
	// at the capture (L-048). This is the assertion that makes the reading
	// unnecessary next time.
	const tracks = await page.locator( '[data-testid="x402.chart_table"] tbody tr' ).first()
		.evaluate( ( el ) => getComputedStyle( el ).gridTemplateColumns.split( /\s+/ ).length );

	expect( tracks, 'the chart table renders three tracks — 1 means the 1fr fallback' ).toBe( 3 );
} );

test( 'x402: below 900px the chart is REPLACED by its table, not shrunk', async ( { page } ) => {
	await page.setViewportSize( { width: 880, height: 900 } );
	await open( page, SCREENS.x402 );

	// §3: "the chart is replaced by its data table, not shrunk — a 320px-wide
	// line chart is decoration, a table is information."
	await expect( page.locator( '.k-chart-svg' ) ).toBeHidden();
	await expect( page.locator( '[data-testid="x402.chart_table"]' ) ).toBeVisible();
} );

// ─────────────────────────────────────────────────────────────────
// CAPTURE AND LOOK — L-048, which has bitten this build twice
// ─────────────────────────────────────────────────────────────────

for ( const [ name, url ] of Object.entries( SCREENS ) ) {
	for ( const theme of [ 'dark', 'light' ] ) {
		for ( const width of [ 1440, 320 ] ) {
			test( `${ name }: capture ${ theme } @ ${ width }`, async ( { page } ) => {
				await page.setViewportSize( { width, height: 1000 } );
				await open( page, url, theme );

				// The capture is the artifact a person READS. Twenty-one passing
				// assertions and four defects sitting in the screenshot is what
				// L-048 records; this test exists to produce the image, and the
				// reading of it is the session's own duty.
				await page.screenshot( {
					path: `tests/E2E/artifacts/captures/${ name }-${ theme }-${ width }.png`,
					fullPage: true,
				} );
			} );
		}
	}
}
