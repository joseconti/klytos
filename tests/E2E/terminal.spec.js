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

const AxeBuilder = require( '@axe-core/playwright' ).default;

const {
	test,
	expect,
	login,
	passwordFor,
	KNOWN_DELIVERY_GAPS,
	DEV_ONLY_SURFACES,
} = require( './fixtures' );

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

	/* ─────────────────────────────────────────────────────────────────────
	 * THE REDESIGN — stage 6 slice 2.
	 *
	 * Entry 23's chrome, built against `template-console-stream.md` and
	 * `manifest.md` §23. The STREAM is not asserted here and must not be: it
	 * is the deferred engine interior (D-104, `roadmap.md` §0c), so what these
	 * tests own is the labelled container it mounts into and everything around
	 * it. Asserting a `<pre>` line model this build deliberately did not
	 * produce would be a test of a decision, not of the screen.
	 * ───────────────────────────────────────────────────────────────────── */

	test( 'the shell owns the h1, and the screen emits exactly one', async ( { page } ) => {
		await loginOwnerWithSecondFactor( page );
		await page.goto( '/installer/admin/terminal.php' );

		const h1 = page.locator( 'h1' );
		await expect( h1 ).toHaveCount( 1 );
		await expect( h1 ).toHaveText( 'Terminal' );
	} );

	test( 'the canvas is a labelled, focusable container — not an unnamed element', async ( { page } ) => {
		await loginOwnerWithSecondFactor( page );
		await page.goto( '/installer/admin/terminal.php' );

		const canvas = page.getByTestId( 'terminal.console' );
		await expect( canvas ).toBeVisible();
		await expect( canvas ).toHaveAttribute( 'role', 'group' );
		await expect( canvas ).toHaveAttribute( 'tabindex', '0' );

		// A NAME, not merely an attribute that exists: the shipped screen
		// mounted xterm into a bare <div id="klytos-terminal"> with nothing an
		// assistive technology could announce.
		const name = await canvas.getAttribute( 'aria-label' );
		expect( name && name.trim().length ).toBeGreaterThan( 0 );

		// aria-busy is present and FALSE at rest — the machine-readable half of
		// the running state. Present-but-true at rest would be worse than absent.
		await expect( canvas ).toHaveAttribute( 'aria-busy', 'false' );
	} );

	test( 'the control row carries Copy all, named for its content (§2)', async ( { page } ) => {
		await loginOwnerWithSecondFactor( page );
		await page.goto( '/installer/admin/terminal.php' );

		await expect( page.getByTestId( 'terminal.controls' ) ).toBeVisible();

		const copyAll = page.getByTestId( 'terminal.copy_all' );
		await expect( copyAll ).toBeVisible();

		// §2: on a consumer with no detail panel the control names WHAT it
		// copies. A bare "Copy" would pass a visibility check and fail the spec.
		const label = ( await copyAll.textContent() ).trim();
		expect( label.length ).toBeGreaterThan( 'Copy'.length );

		// And there is no per-line copy anywhere: §7.1 condition 1.
		await expect( page.locator( '.k-stream-copy' ) ).toHaveCount( 0 );
	} );

	test( 'the command reference is a real disclosure, and closing it returns focus', async ( { page } ) => {
		await loginOwnerWithSecondFactor( page );
		await page.goto( '/installer/admin/terminal.php' );

		const toggle = page.getByTestId( 'terminal.commands_toggle' );
		const panel  = page.getByTestId( 'terminal.commands_panel' );

		// The shipped version toggled a class on a display:none rule, with no
		// aria-expanded and no aria-controls at all.
		await expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( toggle ).toHaveAttribute( 'aria-controls', 'cmd-panel' );
		await expect( panel ).toBeHidden();

		await toggle.click();
		await expect( toggle ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( panel ).toBeVisible();

		await page.getByTestId( 'terminal.commands_close' ).click();
		await expect( panel ).toBeHidden();
		await expect( toggle ).toBeFocused();
	} );

	test( 'THE REPRODUCTION — every string on the screen comes from the catalogue (NEW-33)', async ( { page } ) => {
		await loginOwnerWithSecondFactor( page );
		await page.goto( '/installer/admin/terminal.php' );

		const body = await page.locator( 'body' ).innerText();

		/*
		 * `terminal.php` called `__()` ZERO times and every string on it was a
		 * Spanish literal — several of them unaccented, which is the shape the
		 * orthography rule exists for. These are the exact words the shipped
		 * screen printed to all 20 locales.
		 */
		for ( const literal of [
			'autenticacion',
			'Referencia rapida',
			'Sesion de terminal expirada',
			'Comandos',
			'Terminal integrado',
		] ) {
			expect( body, `the screen still prints the pre-NEW-33 literal "${ literal }"` )
				.not.toContain( literal );
		}

		/*
		 * L-046's other half, and the reason the check alone is not enough: a
		 * MISSING key renders as the key itself, so a screen can be fully
		 * `__()`-wrapped and still print `terminal.copy_all` at a person. The
		 * lookbehind is L-046's own correction — the dev bar prints
		 * `terminal.php`, and a naive matcher reads that as a key.
		 */
		const keyShaped = body.match( /(?<![\w./-])terminal\.[a-z0-9_]+(?![\w-])/g );
		expect( keyShaped, 'an unresolved catalogue key is rendered as its own key' ).toBeNull();
	} );

	test( 'a real command still runs, prints, and announces its state', async ( { page } ) => {
		/*
		 * The chrome tests above all pass on a screen whose terminal does not
		 * work: they never send a command. This one does, end to end, against
		 * the real endpoint — because the whole 500-line input-and-execution
		 * block moved out of `terminal.php` into `klytos-terminal.js` in this
		 * slice, and "the markup is right" is not the same claim as "the shell
		 * still executes".
		 *
		 * `version` is chosen deliberately: it is the one command with
		 * `permission => null`, it writes nothing, and its output contains a
		 * value this test can predict without hardcoding a version number.
		 *
		 * IT GOES THROUGH REVALIDATION, AND THAT IS THE PRODUCT, NOT THE TEST.
		 * `checkRevalidation()` reads `$_SESSION['klytos_terminal_last_command']`,
		 * which is UNSET on a fresh session — so `time() - 0 > 600` is true and
		 * the FIRST command of every terminal session demands a fresh second
		 * factor, seconds after the login that already required one. Found by
		 * driving this, and deliberately NOT "fixed": the session reaching this
		 * page may be hours old, `terminal.access` is owner-only, and the
		 * commands behind it delete backups and rewrite config. Seeding that
		 * timer at page load to make a test shorter would weaken a real control
		 * for the tester's convenience. So the test proves the whole path
		 * instead — which also exercises the dialog's SUCCESS branch, the one
		 * the Esc test above cannot reach.
		 */
		const secret = fs.readFileSync( SECRET_FILE, 'utf8' ).trim();

		await loginOwnerWithSecondFactor( page );
		await page.goto( '/installer/admin/terminal.php' );

		const status = page.getByTestId( 'terminal.status' );
		await expect( status ).toHaveText( '' );

		await page.getByTestId( 'terminal.console' ).click();
		await page.keyboard.type( 'version' );
		await page.keyboard.press( 'Enter' );

		const dialog = page.getByTestId( 'terminal.revalidate' );
		await expect( dialog ).toBeVisible();
		await page.getByTestId( 'terminal.revalidate_code' ).fill( totpCode( secret ) );
		await page.getByTestId( 'terminal.revalidate_submit' ).click();

		// The dialog closes and the PENDING command is retried — the product's
		// own promise, and the reason `pendingCommand` exists at all.
		await expect( dialog ).toBeHidden();

		// The polite status region reports the end state. `aria-busy` goes back
		// to false with it — a canvas left busy forever is the failure mode of
		// setting it at all.
		await expect( status ).toHaveText( /.+/ );
		await expect( page.getByTestId( 'terminal.console' ) ).toHaveAttribute( 'aria-busy', 'false' );

		// And the OUTPUT reached the screen. xterm paints to a canvas, so the
		// assertion goes through its own accessibility layer — which is exactly
		// what `screenReaderMode` was turned on for, and reading it here is the
		// only way this test could tell a working shell from a silent one.
		await expect.poll(
			async () => page.locator( '#klytos-terminal' ).innerText(),
			{ message: 'the terminal printed nothing for `version`' }
		).toContain( 'Klytos v' );
	} );

	test( 'the revalidation overlay is a real dialog: focus in, Esc out, focus returned', async ( { page } ) => {
		await loginOwnerWithSecondFactor( page );
		await page.goto( '/installer/admin/terminal.php' );

		const dialog = page.getByTestId( 'terminal.revalidate' );

		// It ships hidden — and the CONTAINER must really hide, which is the
		// defect keel-verify caught on `.k-error` inside this very dialog.
		await expect( dialog ).toBeHidden();

		await expect( dialog ).toHaveAttribute( 'role', 'dialog' );
		await expect( dialog ).toHaveAttribute( 'aria-modal', 'true' );
		await expect( dialog ).toHaveAttribute( 'aria-labelledby', 'revalidation-title' );

		const field = page.getByTestId( 'terminal.revalidate_code' );

		// Drive the dialog the way the product opens it, without waiting ten
		// idle minutes: the server decides `requires_2fa`, so the response is
		// intercepted at exactly the boundary the product reads it from.
		await page.route( '**/api/terminal.php', ( route ) =>
			route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( { success: false, output: '', requires_2fa: true } ),
			} )
		);

		await page.getByTestId( 'terminal.console' ).click();
		await page.keyboard.type( 'version' );
		await page.keyboard.press( 'Enter' );

		await expect( dialog ).toBeVisible();
		await expect( field ).toBeFocused();

		// The code field has a real, VISIBLE label — the shipped one had a
		// placeholder ("000000") and nothing else. Asserted here rather than
		// before the dialog opens: a label inside a hidden dialog is hidden,
		// and a test that reads it there is measuring its own ordering.
		await expect( page.locator( 'label[for="revalidation-code"]' ) ).toBeVisible();

		// Esc closes it. The shipped overlay had no Esc and no cancel: the only
		// way out was succeeding.
		await page.keyboard.press( 'Escape' );
		await expect( dialog ).toBeHidden();
	} );

	test( 'the no-second-factor state is the delivery\'s empty state, not a hardcoded amber box', async ( { page } ) => {
		// The fixture turned the owner's second factor ON for this file, so the
		// refusal is reached by turning it off for the length of this test and
		// putting it back — never by leaving the tier's only owner unable to
		// log in (the reason `--off` is unconditional in afterAll).
		execFileSync( 'php', [ FIXTURE, '--off' ], { cwd: REPO_ROOT } );

		try {
			await login( page, 'owner' );
			await page.goto( '/installer/admin/terminal.php' );

			const notice = page.getByTestId( 'terminal.two_factor_required' );
			await expect( notice ).toBeVisible();
			await expect( notice ).toHaveAttribute( 'role', 'alert' );

			// It links to the screen that fixes it, and the terminal is absent.
			await expect( page.getByTestId( 'terminal.go_to_security' ) ).toBeVisible();
			await expect( page.getByTestId( 'terminal.console' ) ).toHaveCount( 0 );

			// No literal hex from the shipped inline styles survives.
			const html = await page.content();
			expect( html ).not.toContain( '#fef3c7' );
			expect( html ).not.toContain( '#92400e' );
		} finally {
			const out = execFileSync( 'php', [ FIXTURE, '--on' ], { cwd: REPO_ROOT } ).toString();
			expect( out ).toContain( 'second factor: ON' );
		}
	} );

	test( 'the page does not scroll horizontally at 320 CSS px', async ( { page } ) => {
		await loginOwnerWithSecondFactor( page );
		await page.setViewportSize( { width: 320, height: 720 } );
		await page.goto( '/installer/admin/terminal.php' );

		// Build rule 1's seventh mechanism was a grid TRACK sizing to its
		// content, found on entry 2 at exactly this width. This screen puts a
		// canvas in that track.
		const overflow = await page.evaluate(
			() => document.documentElement.scrollWidth - document.documentElement.clientWidth
		);
		expect( overflow, `the page scrolls ${ overflow }px sideways at 320 CSS px` ).toBeLessThanOrEqual( 0 );
	} );

	for ( const theme of [ 'dark', 'light' ] ) {
		test( `axe — the WHOLE page, ${ theme } (L-037)`, async ( { page } ) => {
			await loginOwnerWithSecondFactor( page );
			await page.goto( '/installer/admin/terminal.php' );
			await page.evaluate( ( t ) => document.documentElement.setAttribute( 'data-theme', t ), theme );

			let builder = new AxeBuilder( { page } ).withTags( [
				'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa',
			] );

			/*
			 * `exclude()` READS AN ARRAY AS A CHAINED SELECTOR, so the gaps go
			 * in ONE AT A TIME — passing the list matches nothing and excludes
			 * nothing, silently and in the safe-looking direction.
			 *
			 * The xterm canvas is excluded as the DEFERRED INTERIOR, not as a
			 * delivery gap: it is third-party DOM this build does not emit, and
			 * D-104 recorded it as unbacked product. Everything around it is
			 * still scanned, which is the whole point of scanning the page and
			 * not `#main` (L-037).
			 */
			for ( const selector of [ ...KNOWN_DELIVERY_GAPS, ...DEV_ONLY_SURFACES, '.k-terminal-canvas' ] ) {
				builder = builder.exclude( selector );
			}

			const results = await builder.analyze();
			expect(
				results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` )
			).toEqual( [] );
		} );
	}

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
