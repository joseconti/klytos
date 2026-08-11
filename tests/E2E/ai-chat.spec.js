/**
 * Klytos CMS — entry 12 (AI chat), browser-driven tier.
 *
 * The last screen of the Phase 4 redesign build. It is driven in BOTH of its
 * mutually exclusive server-side states, because the fix for one of the three
 * shipped defects is precisely that they are mutually exclusive: with no
 * provider configured the composer is not in the document at all, so nothing
 * can un-hide it and focus it.
 *
 * WHY `api/ai-chat.php` IS INTERCEPTED. Sending a real message needs a real
 * provider and a real key, which this machine does not have and must not need.
 * The interception happens at exactly the boundary the screen consumes — one
 * whole JSON body, which is what `chat-engine.php` actually produces (D-104) —
 * so what is exercised is the screen's rendering of a real response shape, not
 * a mock of the screen.
 *
 * WHAT IS NOT ASSERTED HERE, AND WHY IT IS NOT AN OMISSION: streaming, Stop, a
 * running tool call, an inline permission confirm, the Stopped turn and "Load
 * earlier messages" are the deferred engine interior (D-104, `roadmap.md` §0c).
 * They are states of a partial turn, and this product cannot produce one.
 *
 * @license GPL-3.0-or-later
 */

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const AxeBuilder = require( '@axe-core/playwright' ).default;

const {
	test,
	expect,
	login,
	KNOWN_DELIVERY_GAPS,
	DEV_ONLY_SURFACES,
} = require( './fixtures' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );
const FIXTURE = path.join( __dirname, 'fixtures', 'reset-ai-provider.php' );
const SCREEN = '/installer/admin/ai-chat.php';

function fixture( flag ) {
	return execFileSync( 'php', [ FIXTURE, flag ], { cwd: REPO_ROOT, encoding: 'utf8' } );
}

/**
 * One whole turn, in the shape `ChatResult::toArray()` really produces.
 * `tool_executions` carries `tool`, `input`, `output` and `success` — the four
 * fields `admin/api/ai-chat.php` stores and returns.
 */
function turnBody( { text, tools = [], status = 'success', error = null } ) {
	return JSON.stringify( {
		success: true,
		chat_id: 'e2e-chat',
		result: {
			assistant_message: text,
			tool_executions: tools,
			status,
			error,
			usage: { total_tokens: 12 },
		},
	} );
}

async function interceptSend( page, body ) {
	await page.route( '**/api/ai-chat.php', ( route ) => {
		if ( route.request().method() !== 'POST' ) {
			return route.continue();
		}
		return route.fulfill( { status: 200, contentType: 'application/json', body } );
	} );
}

// ─────────────────────────────────────────────────────────────────
// The state the seed is actually in: no provider configured.
// ─────────────────────────────────────────────────────────────────

test.describe( 'entry 12 — no provider configured', () => {
	test.beforeAll( () => {
		fixture( '--off' );
	} );

	test( 'the composer is REPLACED, not disabled and not hidden', async ( { page } ) => {
		await login( page, 'owner' );
		await page.goto( SCREEN );

		/*
		 * The shipped defect: PHP hid the welcome panel, `showWelcome()`
		 * un-hid it unconditionally and focused a textarea that carried no
		 * `disabled`. The fix is structural — there is no composer in the
		 * document — so this assertion cannot be satisfied by a CSS rule or by
		 * a script that happens not to run.
		 */
		await expect( page.getByTestId( 'ai_chat.composer' ) ).toHaveCount( 0 );
		await expect( page.getByTestId( 'ai_chat.input' ) ).toHaveCount( 0 );

		const line = page.getByTestId( 'ai_chat.not_configured' );
		await expect( line ).toBeVisible();
		await expect( page.getByTestId( 'ai_chat.open_settings' ) ).toBeVisible();

		// And no key rendered as its own key (L-046 reaches runtime here: the
		// static check reads literals, this reads what the browser painted).
		const text = await line.textContent();
		expect( text, 'a catalogue key rendered as itself' ).not.toMatch( /(?<![\w.])ai_chat\.[a-z_]+/ );
	} );

	test( 'nothing takes focus when there is nothing to type into', async ( { page } ) => {
		await login( page, 'owner' );
		await page.goto( SCREEN );

		const focused = await page.evaluate( () => document.activeElement.tagName.toLowerCase() );
		expect( focused, 'something auto-focused on a screen with no composer' ).not.toBe( 'textarea' );
	} );
} );

// ─────────────────────────────────────────────────────────────────
// The configured screen — the one the template actually draws.
// ─────────────────────────────────────────────────────────────────

test.describe( 'entry 12 — the conversation', () => {
	test.beforeAll( () => {
		fixture( '--on' );
	} );

	test.afterAll( () => {
		fixture( '--off' );
	} );

	test( 'the shell is back, and there is exactly ONE h1 and it is the screen', async ( { page } ) => {
		await login( page, 'owner' );
		await page.goto( SCREEN );

		/*
		 * The screen used to ship five `display: none !important` rules that
		 * deleted the navigation, the toolbar and the status bar — and TWO
		 * `<h1>`s, or ZERO of them on a `?panel=` URL while `$pageEmitsOwnH1`
		 * was set. Entry 2's answer: the shell owns the heading.
		 */
		await expect( page.locator( '#k-nav' ) ).toBeVisible();

		const headings = page.locator( 'main h1' );
		await expect( headings ).toHaveCount( 1 );
		await expect( headings.first() ).toHaveText( 'Klytos AI' );

		await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
	} );

	test( 'the transcript is a polite log that announces additions only', async ( { page } ) => {
		await login( page, 'owner' );
		await page.goto( SCREEN );

		const transcript = page.getByTestId( 'ai_chat.transcript' );
		await expect( transcript ).toHaveAttribute( 'role', 'log' );
		await expect( transcript ).toHaveAttribute( 'aria-live', 'polite' );
		await expect( transcript ).toHaveAttribute( 'aria-relevant', 'additions' );

		// §5: "Never aria-live=assertive — the copilot does not interrupt."
		await expect( page.locator( '[aria-live="assertive"]' ) ).toHaveCount( 0 );
	} );

	test( 'the empty state is three starters and a sentence, not a blank panel', async ( { page } ) => {
		await login( page, 'owner' );
		await page.goto( SCREEN );

		await expect( page.getByTestId( 'ai_chat.starters' ) ).toBeVisible();
		for ( const index of [ 0, 1, 2 ] ) {
			await expect( page.getByTestId( `ai_chat.starter.${ index }` ) ).toBeVisible();
		}

		// A starter puts its text in the composer; it does not send on its own.
		await page.getByTestId( 'ai_chat.starter.0' ).click();
		await expect( page.getByTestId( 'ai_chat.input' ) ).not.toHaveValue( '' );
		await expect( page.getByTestId( 'ai_chat.turn.user' ) ).toHaveCount( 0 );
	} );

	test( 'the context row states its own emptiness rather than disappearing', async ( { page } ) => {
		await login( page, 'owner' );
		await page.goto( SCREEN );

		// §2 "Empty — no context available". It is this product's permanent
		// state: nothing anywhere records a page in context (D-104).
		await expect( page.getByTestId( 'ai_chat.context' ) ).toBeVisible();
	} );

	test( 'the composer has a real label and states its own keys', async ( { page } ) => {
		await login( page, 'owner' );
		await page.goto( SCREEN );

		const input = page.getByTestId( 'ai_chat.input' );

		// §5: "a real <label> (visually hidden is acceptable here, the
		// placeholder is not the label)".
		const labelled = await input.evaluate( ( node ) => {
			const label = node.labels && node.labels[0];
			return label ? label.textContent.trim() : '';
		} );
		expect( labelled, 'the composer has no real <label>' ).not.toBe( '' );

		// aria-describedby the Enter/Shift+Enter hint, and the hint says so.
		const describedBy = await input.getAttribute( 'aria-describedby' );
		expect( describedBy ).toBeTruthy();
		const hint = await page.locator( `#${ describedBy }` ).textContent();
		expect( hint ).toMatch( /Enter/i );
		expect( hint ).toMatch( /Shift/i );

		// The placeholder is deliberately NOT the label, and this screen does
		// not carry one at all — §5 names the placeholder as the thing a label
		// may not be replaced by.
		expect( await input.getAttribute( 'placeholder' ) ).toBeNull();
	} );

	test( 'Enter sends and Shift+Enter adds a line', async ( { page } ) => {
		await login( page, 'owner' );
		await interceptSend( page, turnBody( { text: 'A finished answer.' } ) );
		await page.goto( SCREEN );

		const input = page.getByTestId( 'ai_chat.input' );

		await input.fill( 'first line' );
		await input.press( 'Shift+Enter' );
		await input.type( 'second line' );
		await expect( input ).toHaveValue( /first line\nsecond line/ );
		await expect( page.getByTestId( 'ai_chat.turn.user' ) ).toHaveCount( 0 );

		await input.press( 'Enter' );
		await expect( page.getByTestId( 'ai_chat.turn.user' ) ).toHaveCount( 1 );
		await expect( page.getByTestId( 'ai_chat.turn.agent' ) ).toHaveCount( 1 );
		await expect( input ).toHaveValue( '' );
	} );

	test( 'each turn is a focusable article named for who said it and when', async ( { page } ) => {
		await login( page, 'owner' );
		await interceptSend( page, turnBody( { text: 'A finished answer.' } ) );
		await page.goto( SCREEN );

		await page.getByTestId( 'ai_chat.input' ).fill( 'hello' );
		await page.getByTestId( 'ai_chat.input' ).press( 'Enter' );

		const user = page.getByTestId( 'ai_chat.turn.user' ).first();
		const agent = page.getByTestId( 'ai_chat.turn.agent' ).first();

		// §2 Focus: a name of the form "You, 14:02" / "Klytos AI, 14:02", so a
		// keyboard user can move answer by answer.
		expect( await user.getAttribute( 'aria-label' ) ).toMatch( /,\s*\d{1,2}[:.]\d{2}/ );
		expect( await agent.getAttribute( 'aria-label' ) ).toMatch( /Klytos AI,\s*\d{1,2}[:.]\d{2}/ );

		await expect( user ).toHaveAttribute( 'tabindex', '0' );
		await expect( agent ).toHaveAttribute( 'tabindex', '0' );

		// The role comes from the element, which is what §2 asks for.
		expect( await agent.evaluate( ( node ) => node.tagName.toLowerCase() ) ).toBe( 'article' );
	} );

	test( "a turn's actions are in the DOM at all times and reachable by keyboard", async ( { page } ) => {
		await login( page, 'owner' );
		await interceptSend( page, turnBody( { text: 'A finished answer.' } ) );
		await page.goto( SCREEN );

		await page.getByTestId( 'ai_chat.input' ).fill( 'hello' );
		await page.getByTestId( 'ai_chat.input' ).press( 'Enter' );

		// §2 Hover: "they are in the DOM at all times and focusable". A
		// hover-only reveal would put them out of reach of exactly the user
		// they exist for, which is why the stylesheet reveals on :focus-within
		// as well — asserted by focusing, not by hovering.
		const copy = page.getByTestId( 'ai_chat.turn_copy' ).first();
		await expect( copy ).toHaveCount( 1 );

		await copy.focus();
		await expect( copy ).toBeVisible();

		const opacity = await copy.evaluate(
			( node ) => getComputedStyle( node.closest( '.k-turn-actions' ) ).opacity
		);
		expect( Number( opacity ), 'the actions stay invisible when focused' ).toBeGreaterThan( 0.9 );
	} );

	test( 'a finished tool call states its outcome in words, in an ordered list', async ( { page } ) => {
		await login( page, 'owner' );
		await interceptSend( page, turnBody( {
			text: 'Three pages match.',
			tools: [ { tool: 'search_pages', input: { q: 'pricing' }, output: { hits: 3 }, success: true } ],
		} ) );
		await page.goto( SCREEN );

		await page.getByTestId( 'ai_chat.input' ).fill( 'find pricing pages' );
		await page.getByTestId( 'ai_chat.input' ).press( 'Enter' );

		const row = page.getByTestId( 'ai_chat.toolcall.done' );
		await expect( row ).toHaveCount( 1 );

		// §5.10: <li> inside <ol aria-label="Tool calls">, status as TEXT.
		const list = page.locator( 'ol.k-toolcalls' );
		await expect( list ).toHaveAttribute( 'aria-label', /.+/ );
		expect( await row.evaluate( ( node ) => node.tagName.toLowerCase() ) ).toBe( 'li' );
		await expect( row ).toContainText( 'search_pages' );

		// §2: the result is expandable, collapsed by default.
		const details = row.locator( 'details' );
		expect( await details.evaluate( ( node ) => node.open ) ).toBe( false );
	} );

	test( 'a failed tool call names the failure and offers a retry', async ( { page } ) => {
		await login( page, 'owner' );
		await interceptSend( page, turnBody( {
			text: '',
			tools: [ { tool: 'publish_page', input: { id: 4 }, output: { error: 'no h1' }, success: false } ],
		} ) );
		await page.goto( SCREEN );

		await page.getByTestId( 'ai_chat.input' ).fill( 'publish it' );
		await page.getByTestId( 'ai_chat.input' ).press( 'Enter' );

		const row = page.getByTestId( 'ai_chat.toolcall.failed' );
		await expect( row ).toHaveCount( 1 );
		await expect( row ).toContainText( 'publish_page' );
		await expect( page.getByTestId( 'ai_chat.toolcall_retry' ) ).toBeVisible();
	} );

	test( 'an unreachable model is an alert in the transcript and the composer stays usable', async ( { page } ) => {
		await login( page, 'owner' );
		await interceptSend( page, turnBody( {
			text: '',
			status: 'error',
			error: 'the provider returned 503',
		} ) );
		await page.goto( SCREEN );

		await page.getByTestId( 'ai_chat.input' ).fill( 'anything' );
		await page.getByTestId( 'ai_chat.input' ).press( 'Enter' );

		const alert = page.getByTestId( 'ai_chat.error' );
		await expect( alert ).toBeVisible();
		await expect( alert ).toHaveAttribute( 'role', 'alert' );
		await expect( alert ).toContainText( '503' );

		await expect( page.getByTestId( 'ai_chat.error_retry' ) ).toBeVisible();
		await expect( page.getByTestId( 'ai_chat.error_settings' ) ).toBeVisible();

		// §2: "the composer stays usable".
		await expect( page.getByTestId( 'ai_chat.input' ) ).toBeEnabled();
	} );

	test( 'the send button reports busy without disabling the composer', async ( { page } ) => {
		await login( page, 'owner' );

		// Hold the response open so the busy state is observable rather than
		// inferred — reading it after the turn lands would assert nothing.
		let release;
		const held = new Promise( ( resolve ) => {
			release = resolve;
		} );

		await page.route( '**/api/ai-chat.php', async ( route ) => {
			if ( route.request().method() !== 'POST' ) {
				return route.continue();
			}
			await held;
			return route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: turnBody( { text: 'done' } ),
			} );
		} );

		await page.goto( SCREEN );
		await page.getByTestId( 'ai_chat.input' ).fill( 'hello' );
		await page.getByTestId( 'ai_chat.input' ).press( 'Enter' );

		const send = page.getByTestId( 'ai_chat.send' );
		await expect( send ).toHaveAttribute( 'aria-busy', 'true' );

		// §2 Sending: "the composer stays enabled so the next message can be
		// typed" — and the button is busy, not disabled.
		await expect( page.getByTestId( 'ai_chat.input' ) ).toBeEnabled();
		await expect( send ).toBeEnabled();

		release();
		await expect( send ).toHaveAttribute( 'aria-busy', 'false' );
	} );

	test( 'the conversation history is a real disclosure that returns focus', async ( { page } ) => {
		await login( page, 'owner' );
		await page.goto( SCREEN );

		const toggle = page.getByTestId( 'ai_chat.history_toggle' );
		const panel = page.getByTestId( 'ai_chat.history' );

		await expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( panel ).toBeHidden();

		await toggle.click();
		await expect( toggle ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( panel ).toBeVisible();

		await page.keyboard.press( 'Escape' );
		await expect( toggle ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( panel ).toBeHidden();
		await expect( toggle ).toBeFocused();
	} );

	test( 'the panel really hides — a k-* container that ships hidden owes its rule', async ( { page } ) => {
		await login( page, 'owner' );
		await page.goto( SCREEN );

		// The FOURTH shape of this defect in this build, and the reason the
		// rule is a check now: `.k-card` sets `display`, which is the UA's own
		// `[hidden]` specificity, and wins on origin.
		const display = await page.getByTestId( 'ai_chat.history' ).evaluate(
			( node ) => getComputedStyle( node ).display
		);
		expect( display, 'the history panel is `hidden` and still painted' ).toBe( 'none' );
	} );

	test( 'the legacy ?panel= URLs redirect to the real screens', async ( { page } ) => {
		await login( page, 'owner' );

		for ( const [ panel, destination ] of Object.entries( {
			dashboard: 'index.php',
			settings: 'settings.php',
			users: 'users.php',
			profile: 'profile.php',
		} ) ) {
			await page.goto( `${ SCREEN }?panel=${ panel }` );
			expect( page.url(), `?panel=${ panel } did not land on ${ destination }` )
				.toContain( destination );
		}
	} );

	test( 'the page does not scroll horizontally at 320 CSS px', async ( { page } ) => {
		await login( page, 'owner' );
		await page.setViewportSize( { width: 320, height: 720 } );
		await page.goto( SCREEN );

		const overflow = await page.evaluate(
			() => document.documentElement.scrollWidth - document.documentElement.clientWidth
		);
		expect( overflow, `the page scrolls ${ overflow }px sideways at 320 CSS px` ).toBeLessThanOrEqual( 0 );
	} );

	/*
	 * The four geometry assertions below exist because LOOKING at the rendered
	 * screen found four defects that every assertion written before it had
	 * passed over in silence. Each is pinned by reading the computed value out
	 * of the browser (L-032), and each was proven by planting its defect back.
	 */

	test( 'the send button sits BESIDE the composer, not under it', async ( { page } ) => {
		await login( page, 'owner' );
		await page.goto( SCREEN );

		// `.k-field` sets `flex-direction: column`, and the composer field
		// carries both classes: setting `display: flex` said nothing about
		// direction, so the 28px round button landed on its own line.
		const box = await page.evaluate( () => {
			const input = document.querySelector( '.k-conv-input' ).getBoundingClientRect();
			const send = document.querySelector( '.k-conv-send' ).getBoundingClientRect();
			return { inputRight: input.right, sendLeft: send.left, inputBottom: input.bottom, sendTop: send.top };
		} );

		expect( box.sendLeft, 'the send button is not to the right of the composer' )
			.toBeGreaterThanOrEqual( box.inputRight );
		expect( box.sendTop, 'the send button dropped below the composer' )
			.toBeLessThan( box.inputBottom );
	} );

	test( 'the composer opens at the 34px §1 specifies, not at the shared 88px', async ( { page } ) => {
		await login( page, 'owner' );
		await page.goto( SCREEN );

		const height = await page.getByTestId( 'ai_chat.input' ).evaluate(
			( node ) => node.getBoundingClientRect().height
		);
		expect( height, 'the composer did not start at its specified 34px' ).toBeLessThanOrEqual( 40 );

		// …and it grows, up to the 120px ceiling §1 sets.
		await page.getByTestId( 'ai_chat.input' ).fill( 'a\nb\nc\nd\ne\nf\ng\nh\ni\nj\nk\nl' );
		const grown = await page.getByTestId( 'ai_chat.input' ).evaluate(
			( node ) => node.getBoundingClientRect().height
		);
		expect( grown ).toBeGreaterThan( height );
		expect( grown ).toBeLessThanOrEqual( 120 );
	} );

	test( "a turn's glyphs are sized, not the SVG default of 300 × 150", async ( { page } ) => {
		await login( page, 'owner' );
		await interceptSend( page, turnBody( { text: 'done' } ) );
		await page.goto( SCREEN );

		await page.getByTestId( 'ai_chat.input' ).fill( 'hello' );
		await page.getByTestId( 'ai_chat.input' ).press( 'Enter' );

		// An <svg> with no width/height renders at 300 × 150, which made the
		// user's one-line bubble 196px tall.
		const actions = await page.locator( '.k-turn--user .k-turn-actions' ).evaluate(
			( node ) => node.getBoundingClientRect().height
		);
		expect( actions, 'the action row is taller than a button — an unsized glyph' )
			.toBeLessThanOrEqual( 40 );
	} );

	test( 'the status region does not paint a box when it has nothing to say', async ( { page } ) => {
		await login( page, 'owner' );
		await page.goto( SCREEN );

		// `.k-status-line` sets `display: flex`, a tint and padding, so an
		// empty region drew a coloured strip across a screen where nothing had
		// happened yet. Shared with Logs and Terminal.
		const display = await page.getByTestId( 'ai_chat.status' ).evaluate(
			( node ) => getComputedStyle( node ).display
		);
		expect( display, 'an empty status line still painted' ).toBe( 'none' );
	} );

	test( 'the transcript is centred at the 760px the delta specifies', async ( { page } ) => {
		await login( page, 'owner' );
		await page.setViewportSize( { width: 1440, height: 900 } );
		await page.goto( SCREEN );

		// Read the computed value out of the browser, never the stylesheet
		// (L-032): build rule 1 has had seven distinct mechanisms in this build.
		const width = await page.getByTestId( 'ai_chat.transcript' ).evaluate(
			( node ) => node.getBoundingClientRect().width
		);
		expect( width ).toBeLessThanOrEqual( 760 );
	} );

	for ( const theme of [ 'dark', 'light' ] ) {
		test( `axe — the WHOLE page, ${ theme } (L-037)`, async ( { page } ) => {
			await login( page, 'owner' );
			await interceptSend( page, turnBody( {
				text: 'A finished answer with **markup**.',
				tools: [ { tool: 'search_pages', input: {}, output: { hits: 1 }, success: true } ],
			} ) );
			await page.goto( SCREEN );
			await page.evaluate( ( t ) => document.documentElement.setAttribute( 'data-theme', t ), theme );

			// Scanned with a real turn on screen: the empty transcript would
			// not exercise the turn, the tool row or the payload block at all.
			await page.getByTestId( 'ai_chat.input' ).fill( 'hello' );
			await page.getByTestId( 'ai_chat.input' ).press( 'Enter' );
			await expect( page.getByTestId( 'ai_chat.turn.agent' ) ).toHaveCount( 1 );

			await page.getByTestId( 'ai_chat.history_toggle' ).click();
			await expect( page.getByTestId( 'ai_chat.history' ) ).toBeVisible();

			let builder = new AxeBuilder( { page } ).withTags( [
				'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa',
			] );

			// `exclude()` READS AN ARRAY AS A CHAINED SELECTOR — one at a time,
			// or it matches nothing, silently, in the safe-looking direction.
			for ( const selector of [ ...KNOWN_DELIVERY_GAPS, ...DEV_ONLY_SURFACES ] ) {
				builder = builder.exclude( selector );
			}

			const results = await builder.analyze();
			expect(
				results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` )
			).toEqual( [] );
		} );
	}
} );
