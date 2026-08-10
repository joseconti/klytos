/**
 * Klytos CMS — entry 23 (Terminal), browser-driven tier.
 *
 * This file opens with a SECURITY reproduction rather than with the redesign,
 * because the per-screen survey (D-104) found a live cross-site scripting hole
 * in this screen before a line of the redesign was written, and a fix without a
 * test that first failed proves nothing about the defect's return
 * (Keel: every bug fix starts from a failing reproduction, at every value of
 * `Test-first policy:`).
 *
 * WHAT THE DEFECT IS. `populateCommandPanel()` in `installer/admin/terminal.php`
 * built the command reference panel by string concatenation —
 * `'<div class="cmd-item" title="' + cmd.usage + '">'`, then `cmd.name` and
 * `cmd.description` into element bodies — and assigned the result to
 * `panel.innerHTML`. Those values are served by `api/terminal-autocomplete.php`
 * from `TerminalExecutor::getCommandsMetadata()`, which passes the command table
 * through the **`terminal.commands` filter** (`terminal-executor.php:62`,
 * `:1016`). So any installed plugin could put markup in a command description
 * and have it execute in the owner's admin.
 *
 * WHY THE PROBE IS A `<span>` AND NOT AN `<img onerror>`. The read-back duty
 * (`fixtures.js`) fails a test on any console error or failed request, and the
 * classic `<img src=x onerror=…>` payload produces both — so the test would go
 * red for the wrong reason and would stay red after the fix. L-042's rule
 * ("establish WHICH layer refused before changing anything") applies to a test's
 * own failure too. An element that the page had no business creating is the
 * whole assertion; making it execute as well proves nothing extra and costs the
 * ability to tell a fixed screen from a broken harness.
 *
 * WHY THE METADATA IS INTERCEPTED RATHER THAN PLANTED IN A PLUGIN. The vulnerable
 * path is server-supplied metadata → `innerHTML`. `page.route()` reproduces
 * exactly that path at exactly the boundary the untrusted value crosses, without
 * installing a hostile plugin into the playground to prove a point about hostile
 * plugins.
 *
 * @license GPL-3.0-or-later
 */

const { execFileSync } = require( 'child_process' );
const crypto = require( 'crypto' );
const fs = require( 'fs' );
const path = require( 'path' );

const { test, expect, passwordFor } = require( './fixtures' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );
const FIXTURE = path.join( __dirname, 'fixtures', 'reset-terminal.php' );
const SECRET_FILE = path.join( __dirname, 'artifacts', 'terminal-totp.secret' );

/**
 * RFC 4648 base32, no padding — the encoding `TwoFactor::base32Encode()` writes.
 */
function base32Decode( input ) {
	const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	let bits = 0;
	let value = 0;
	const out = [];

	for ( const char of input.replace( /=+$/, '' ).toUpperCase() ) {
		const index = ALPHABET.indexOf( char );
		if ( index < 0 ) {
			continue;
		}
		value = ( value << 5 ) | index;
		bits += 5;
		if ( bits >= 8 ) {
			out.push( ( value >>> ( bits - 8 ) ) & 0xff );
			bits -= 8;
		}
	}

	return Buffer.from( out );
}

/**
 * The same TOTP the product verifies: HMAC-SHA1, 30-second period, 6 digits,
 * counter packed big-endian (`two-factor.php:1105-1120`). Computed here rather
 * than read from anywhere, so the login this spec performs is the real one.
 */
function totpCode( secret ) {
	const key = base32Decode( secret );
	const slice = Math.floor( Date.now() / 1000 / 30 );

	const counter = Buffer.alloc( 8 );
	counter.writeUInt32BE( Math.floor( slice / 2 ** 32 ), 0 );
	counter.writeUInt32BE( slice >>> 0, 4 );

	const hash = crypto.createHmac( 'sha1', key ).update( counter ).digest();
	const offset = hash[ hash.length - 1 ] & 0x0f;
	const code =
		( ( ( hash[ offset ] & 0x7f ) << 24 ) |
			( ( hash[ offset + 1 ] & 0xff ) << 16 ) |
			( ( hash[ offset + 2 ] & 0xff ) << 8 ) |
			( hash[ offset + 3 ] & 0xff ) ) %
		1000000;

	return String( code ).padStart( 6, '0' );
}

/**
 * Log in as the owner THROUGH the second factor, because for this screen there
 * is no other kind of owner login: the fixture turned 2FA on for the run.
 *
 * It is not `fixtures.js::login()` with a step bolted on — that helper is shared
 * by every other spec in the tier and asserting a 2FA screen there would make
 * every one of them depend on this file's fixture state.
 */
async function loginOwnerWithSecondFactor( page ) {
	const secret = fs.readFileSync( SECRET_FILE, 'utf8' ).trim();

	await page.goto( '/installer/admin/login.php' );
	await page.locator( 'input[name="username"]' ).fill( 'owner' );
	await page.locator( 'input[name="password"]' ).fill( passwordFor( 'owner' ) );
	await page.locator( 'form button[type="submit"]' ).first().click();

	// The second factor is a real screen, not a modal: assert it arrived rather
	// than assuming the password step redirected there.
	const codeField = page.locator( 'input[name="2fa_code"]' ).first();
	await expect( codeField ).toBeVisible();

	await codeField.fill( totpCode( secret ) );
	await page.locator( '#panel-totp button[type="submit"]' ).first().click();

	// The session is real only if the shell rendered for it.
	await expect( page.getByTestId( 'shell.brand' ) ).toBeVisible();
}

test.describe( 'Entry 23 — Terminal', () => {
	test.beforeAll( () => {
		const out = execFileSync( 'php', [ FIXTURE, '--on' ], { cwd: REPO_ROOT } ).toString();
		expect( out ).toContain( 'second factor: ON' );
	} );

	test.afterAll( () => {
		// Unconditional: an owner left needing a TOTP code would strand every
		// other spec in the tier, so this runs whether the body passed or not.
		execFileSync( 'php', [ FIXTURE, '--off' ], { cwd: REPO_ROOT } );
	} );

	test( 'a plugin cannot inject markup through the command reference panel', async ( { page } ) => {
		/*
		 * The three values a plugin controls, each carrying a different shape of
		 * injection, so a fix that escapes one and forgets another still fails:
		 *   description → an element in a body
		 *   name        → an element in a different body
		 *   usage       → an attribute break-out
		 */
		await page.route( '**/api/terminal-autocomplete.php*', ( route ) =>
			route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( {
					suggestions: [ 'probe:command' ],
					commands: {
						'probe:command': {
							category: 'general',
							description: '<span class="k-xss-probe-description">injected</span>',
							usage: 'probe:command" data-xss-probe-usage="1',
						},
						'probe:name<span class="k-xss-probe-name">x</span>': {
							category: 'general',
							description: 'a second command whose NAME carries the markup',
							usage: 'probe:name',
						},
					},
				} ),
			} )
		);

		await loginOwnerWithSecondFactor( page );
		await page.goto( '/installer/admin/terminal.php' );

		// The screen renders its terminal only for an account with 2FA active;
		// if the fixture had not landed, this is where it says so rather than
		// failing later on a missing panel for the wrong reason.
		await expect( page.locator( '#klytos-terminal' ) ).toBeVisible();

		await page.locator( '#toggle-cmd-panel' ).click();

		const panel = page.locator( '#cmd-panel-list' );
		await expect( panel ).toContainText( 'probe:command' );

		// The assertion: nothing the server said became an ELEMENT or an
		// ATTRIBUTE. Each is checked separately — a partial fix must not pass.
		await expect( panel.locator( '.k-xss-probe-description' ) ).toHaveCount( 0 );
		await expect( panel.locator( '.k-xss-probe-name' ) ).toHaveCount( 0 );
		await expect( panel.locator( '[data-xss-probe-usage]' ) ).toHaveCount( 0 );

		// And the mirror of it: the value is still SHOWN, as text. A fix that
		// silently drops the description would pass the three counts above while
		// breaking the panel, so the panel's own job is asserted too.
		await expect( panel ).toContainText( '<span class="k-xss-probe-description">injected</span>' );
	} );
} );
