// Manifest entry 24 — Webhooks, the RECORD-FORM half — driven per state, in
// both themes.
//
// Two of §24's four cards are deliberately NOT built and this spec deliberately
// asserts nothing about them (`docs/roadmap.md` §0c, D-103): the HMAC secret
// card, because the product has no rotate anywhere, and the Delivery log
// list-table, because three of its six columns have no data source at all.
//
// THE REPRODUCTIONS, each written against the SHIPPED markup and seen failing
// before the rewrite existed:
//
//   1. "SEND TEST EVENT" REACHED NO ENDPOINT, ON ANY INSTALL, and said it had.
//      Both test controls dispatched `test.ping`, which resolves targets by
//      SUBSCRIPTION, and nothing can subscribe to it. The screen answered
//      "Test event dispatched to all active webhooks" every single time,
//      whatever happened. Now the test is per endpoint and the screen reports
//      what the endpoint actually did. The unit tier owns the delivery
//      semantics (`tests/Unit/WebhookTestEventTest.php`); this tier owns the
//      person's side of it — that pressing the control on a row reports a real
//      outcome and never a fabricated success.
//   2. A REFUSED CSRF POST REPORTED NOTHING AT ALL — `if ( … &&
//      klytos_verify_csrf() )` with no else. The FOURTH screen with this exact
//      defect, after entries 27, 28 and 32.
//   3. DELETE RAISED A BROWSER `confirm()`, which `SPEC/screens/
//      template-record-form.md` §2 forbids by name. Asserted by failing the
//      test if any dialog is raised at all, which is the only assertion that
//      cannot pass for the wrong reason.
//   4. THE SCREEN HAD NO `__()` CALL IN IT — every string was hardcoded
//      English. L-046's shape: asserted here by matching the SHAPE of an
//      unresolved key rather than a list of known keys, because `keel-verify`
//      check 22 reads only STATIC literals and a key assembled at runtime stays
//      invisible to it.
//
// L-037 is carried: axe scans the WHOLE page, never `#main`. The sidebar's
// current nav item is one of DR-005's registered exclusions, which is exactly
// what scoping to `#main` used to hide.

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const FIXTURE = path.join( __dirname, 'fixtures', 'reset-webhooks.php' );
const REPO_ROOT = path.join( __dirname, '..', '..' );

const URL = '/installer/admin/webhooks.php';

/** Re-create the fixture from nothing. Every test starts from the same records. */
function seed() {
	const out = execFileSync( 'php', [ FIXTURE ], { cwd: REPO_ROOT } ).toString();
	return JSON.parse( out.trim().split( '\n' ).pop() ).ids;
}

/**
 * A `root.key`-shaped string anywhere in the visible text is an unresolved
 * catalogue key rendering as itself. Matching the SHAPE rather than a list is
 * the whole point (L-046): a key nobody thought to list is caught too.
 *
 * The event machine names this screen prints on purpose (`page.created`) have
 * the same shape, so they are excluded by being read out of the page's own
 * `<code>` elements rather than by being hard-coded here.
 */
async function assertNoRawCatalogueKeys( page ) {
	const text = await page.locator( 'body' ).innerText();
	const codes = await page.locator( 'code' ).allInnerTexts();
	const allowed = new Set( codes.map( ( c ) => c.trim() ) );

	const suspects = ( text.match( /\b[a-z][a-z0-9_]*\.[a-z][a-z0-9_]+\b/g ) || [] )
		// `x402.payment.received` is printed on purpose and the regex matches
		// its two-dot PREFIX, which the allow-set does not hold literally — so
		// a printed name covers every prefix of itself.
		.filter( ( s ) => ! [ ...allowed ].some( ( a ) => a === s || a.startsWith( s + '.' ) ) )
		// A URL's host is not a catalogue key.
		.filter( ( s ) => ! text.includes( '://' + s ) && ! s.endsWith( '.com' ) && ! s.endsWith( '.php' ) );

	expect( suspects, 'unresolved catalogue keys rendered as themselves' ).toEqual( [] );
}

let ids = [];

test.beforeEach( async ( { page } ) => {
	ids = seed();
	await login( page, 'owner' );
} );

test.describe( 'entry 24 — the add-endpoint form', () => {
	test( 'renders both cards of the one form, with real labels', async ( { page } ) => {
		await page.goto( URL );

		await expect( page.getByTestId( 'webhooks.screen' ) ).toBeVisible();

		// §4: every control has a visible <label for>. No placeholder-as-label.
		const url = page.getByTestId( 'webhooks.field.url' );
		await expect( url ).toBeVisible();
		await expect( url ).toHaveAttribute( 'placeholder', /^$/ ).catch( async () => {
			expect( await url.getAttribute( 'placeholder' ) ).toBeNull();
		} );

		// The event set is a real <fieldset> with a <legend> (§4).
		const events = page.getByTestId( 'webhooks.field.events' );
		await expect( events ).toBeVisible();
		expect( await events.evaluate( ( el ) => el.tagName ) ).toBe( 'FIELDSET' );
		await expect( events.locator( 'legend' ) ).toBeVisible();

		// Every core event is offered, each as a real checkbox.
		await expect( page.getByTestId( 'webhooks.event.page.created' ) ).toHaveAttribute( 'type', 'checkbox' );

		await assertNoRawCatalogueKeys( page );
	} );

	test( 'the two cards post as ONE record — the checkboxes belong to the form', async ( { page } ) => {
		await page.goto( URL );

		// This is the whole reason the association exists: the events live in a
		// different card from the URL, so without form= they would not be sent.
		const owner = await page
			.getByTestId( 'webhooks.event.page.created' )
			.evaluate( ( el ) => el.form && el.form.id );

		expect( owner ).toBe( 'k-webhook-add' );
	} );

	test( 'the toolbar Save submits the form it sits outside of', async ( { page } ) => {
		await page.goto( URL );

		const submit = page.getByTestId( 'webhooks.submit' );
		await expect( submit ).toBeVisible();
		expect( await submit.getAttribute( 'form' ) ).toBe( 'k-webhook-add' );
	} );

	test( 'adds an endpoint and hands over the signing secret once', async ( { page } ) => {
		await page.goto( URL );

		await page.getByTestId( 'webhooks.field.url' ).fill( 'https://example.org/klytos-new' );
		await page.getByTestId( 'webhooks.field.description' ).fill( 'Added by the driven test' );
		await page.getByTestId( 'webhooks.event.page.created' ).check();
		await page.getByTestId( 'webhooks.submit' ).click();

		await expect( page.getByTestId( 'webhooks.status_line' ) ).toBeVisible();

		// §2's read-only rule: readonly, NOT disabled, and selectable.
		const secret = page.getByTestId( 'webhooks.field.secret' );
		await expect( secret ).toBeVisible();
		expect( await secret.getAttribute( 'readonly' ) ).not.toBeNull();
		expect( await secret.getAttribute( 'disabled' ) ).toBeNull();
		expect( ( await secret.inputValue() ).length ).toBe( 64 );

		// A test that varies an input must read that input back (L-035), and a
		// fresh GET rather than a reload, which would re-submit the POST.
		await page.goto( URL );
		await expect( page.getByText( 'https://example.org/klytos-new' ) ).toBeVisible();

		// Shown ONCE: the secret is not on the screen after a plain GET.
		await expect( page.getByTestId( 'webhooks.field.secret' ) ).toHaveCount( 0 );
	} );

	test( 'an empty URL is refused by the HANDLER, with a field error and a summary', async ( { page } ) => {
		await page.goto( URL );

		// `required` and type="url" are deliberately absent so the refusal is
		// the handler's and not Chromium's (L-042) — which is what makes this
		// assertion about the product at all.
		await page.getByTestId( 'webhooks.event.page.created' ).check();
		await page.getByTestId( 'webhooks.submit' ).click();

		const summary = page.getByTestId( 'webhooks.error_summary' );
		await expect( summary ).toBeVisible();
		await expect( summary ).toHaveAttribute( 'role', 'alert' );

		await expect( page.getByTestId( 'webhooks.error.url' ) ).toBeVisible();
		await expect( page.getByTestId( 'webhooks.field.url' ) ).toHaveAttribute( 'aria-invalid', 'true' );

		// The summary row links to the field it names (§2).
		await expect( page.getByTestId( 'webhooks.error_link.0' ) ).toHaveAttribute( 'href', '#webhooks-field-url' );
	} );

	test( 'an endpoint with no event chosen is refused, and the form comes back filled in', async ( { page } ) => {
		await page.goto( URL );

		await page.getByTestId( 'webhooks.field.url' ).fill( 'https://example.org/no-events' );
		await page.getByTestId( 'webhooks.submit' ).click();

		await expect( page.getByTestId( 'webhooks.error.events' ) ).toBeVisible();

		// The draft survives the refusal — losing what somebody typed is the
		// defect this assertion exists to prevent.
		await expect( page.getByTestId( 'webhooks.field.url' ) ).toHaveValue( 'https://example.org/no-events' );
	} );

	test( 'a malformed URL is refused at the field, never with a raw exception', async ( { page } ) => {
		await page.goto( URL );

		// The MALFORMED case, which `filter_var` refuses before the manager is
		// reached and which therefore writes no log line.
		//
		// The other refusal — a well-formed URL SafeHttp will not fetch —
		// deliberately writes an ERROR line naming the manager's own message,
		// because that is where the real reason belongs and the caller must not
		// see it. The read-back duty is RIGHT to fail a browser test that
		// produces one, so that claim belongs to the PHP tier and is asserted
		// there: `tests/Integration/WebhookAdminRefusalHttpTest.php`.
		await page.getByTestId( 'webhooks.field.url' ).fill( 'not a url at all' );
		await page.getByTestId( 'webhooks.event.page.created' ).check();
		await page.getByTestId( 'webhooks.submit' ).click();

		await expect( page.getByTestId( 'webhooks.error.url' ) ).toBeVisible();
		await expect( page.locator( 'body' ) ).not.toContainText( 'Invalid webhook URL.' );
	} );
} );

test.describe( 'entry 24 — the endpoints', () => {
	test( 'states the status as a WORD, and names the failures', async ( { page } ) => {
		await page.goto( URL );

		// §1.3: colour is never the only channel. The disabled endpoint is a
		// state the manager reaches by itself after ten failures, so it has to
		// be legible or an endpoint stops working with no explanation anywhere.
		await expect( page.getByTestId( `webhooks.status.${ ids[ 1 ] }` ) ).toBeVisible();
		await expect( page.getByTestId( `webhooks.status.${ ids[ 1 ] }` ) ).not.toBeEmpty();
		await expect( page.getByTestId( `webhooks.failures.${ ids[ 1 ] }` ) ).toContainText( '11' );

		// The healthy one carries no failure badge at all.
		await expect( page.getByTestId( `webhooks.failures.${ ids[ 0 ] }` ) ).toHaveCount( 0 );
	} );

	test( 'a never-triggered endpoint says so rather than drawing a dash', async ( { page } ) => {
		await page.goto( URL );

		const cell = page.getByTestId( `webhooks.last_triggered.${ ids[ 0 ] }` );
		await expect( cell ).toBeVisible();
		await expect( cell ).not.toHaveText( '—' );
	} );

	test( 'THE REPRODUCTION — a test send reports what the endpoint really did', async ( { page } ) => {
		await page.goto( URL );

		// Scoped to the row (never .first() on the page).
		await page.getByTestId( `webhooks.test.${ ids[ 0 ] }` ).click();

		// The fixture's endpoints are refused before any socket opens, so the
		// honest answer is a FAILURE. The shipped screen reported success here,
		// unconditionally and for every endpoint at once.
		await expect( page.getByTestId( 'webhooks.error_summary' ) ).toBeVisible();
		await expect( page.getByTestId( 'webhooks.status_line' ) ).toHaveCount( 0 );
	} );

	test( 'THE REPRODUCTION — delete is a two-step inline confirm, never a browser dialog', async ( { page } ) => {
		let dialogs = 0;
		page.on( 'dialog', async ( d ) => {
			dialogs++;
			await d.dismiss();
		} );

		await page.goto( URL );

		// First click ARMS. Nothing is written on this pass.
		await page.getByTestId( `webhooks.delete.${ ids[ 0 ] }` ).click();
		await expect( page.getByTestId( `webhooks.delete_confirm.${ ids[ 0 ] }` ) ).toBeVisible();
		await expect( page.getByTestId( `webhooks.endpoint.${ ids[ 0 ] }` ) ).toBeVisible();

		// The wrapper announces the change (§2).
		const wrap = page.getByTestId( `webhooks.delete_confirm.${ ids[ 0 ] }` ).locator( 'xpath=ancestor::form[1]' );
		await expect( wrap ).toHaveAttribute( 'aria-live', 'polite' );

		// Second click deletes.
		await page.getByTestId( `webhooks.delete_confirm.${ ids[ 0 ] }` ).click();
		await expect( page.getByTestId( `webhooks.endpoint.${ ids[ 0 ] }` ) ).toHaveCount( 0 );

		expect( dialogs, 'the screen raised a browser dialog, which §2 forbids by name' ).toBe( 0 );
	} );

	test( 'the empty state is a sentence and an action, never a bare zero', async ( { page } ) => {
		await page.goto( URL );

		for ( const id of ids ) {
			await page.getByTestId( `webhooks.delete.${ id }` ).click();
			await page.getByTestId( `webhooks.delete_confirm.${ id }` ).click();
		}

		await expect( page.getByTestId( 'webhooks.no_endpoints' ) ).toBeVisible();
		await expect( page.getByTestId( 'webhooks.no_endpoints_action' ) ).toHaveAttribute(
			'href',
			'#webhooks-field-url'
		);
	} );
} );

test.describe( 'entry 24 — a refused post says so', () => {
	test( 'THE REPRODUCTION — a bad CSRF token reports the refusal', async ( { page } ) => {
		await page.goto( URL );

		// Break the token the way an expired form really is broken, then post.
		await page.evaluate( () => {
			document
				.querySelectorAll( '#k-webhook-add input[type=hidden]' )
				.forEach( ( el ) => {
					if ( el.name !== 'action' ) {
						el.value = 'not-a-valid-token';
					}
				} );
		} );

		await page.getByTestId( 'webhooks.field.url' ).fill( 'https://example.org/csrf' );
		await page.getByTestId( 'webhooks.event.page.created' ).check();
		await page.getByTestId( 'webhooks.submit' ).click();

		// The shipped screen said NOTHING here — the person's endpoint gone and
		// the screen idle.
		await expect( page.getByTestId( 'webhooks.error_summary' ) ).toBeVisible();
	} );
} );

for ( const theme of [ 'light', 'dark' ] ) {
	test( `axe — the WHOLE page, ${ theme } (L-037)`, async ( { page } ) => {
		await page.goto( URL );
		// Baked in before load, which is how the product itself works (D-075,
		// L-035): a reading taken mid-transition measures the wrong colours.
		await page.context().addCookies( [
			{ name: 'klytos_theme', value: theme, url: 'http://127.0.0.1' },
		] );
		await page.goto( URL );

		// ONE AT A TIME. `exclude()` reads an array as a CHAINED selector, not as
		// a list, so `exclude( KNOWN_DELIVERY_GAPS )` matches nothing and
		// excludes nothing — and it fails in the safe-LOOKING direction, which
		// is why it is written out rather than trusted. Already recorded in
		// content-model.spec.js; this spec re-learned it on its first run.
		let builder = new AxeBuilder( { page } )
			.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] );

		for ( const gap of KNOWN_DELIVERY_GAPS ) {
			builder = builder.exclude( gap );
		}
		for ( const surface of DEV_ONLY_SURFACES ) {
			builder = builder.exclude( surface );
		}

		const results = await builder.analyze();

		expect( results.violations ).toEqual( [] );
	} );
}
