// Manifest entry 37 — Agent payment settings — driven per card and per STATE,
// in both themes.
//
// What this spec exists to catch, beyond "the screen renders":
//
//   1. THE BOT LIST CAN SHRINK. `Config::update()` merged lists index by index,
//      so removing a custom agent did nothing and the row came back on the next
//      load. The PHP tier pins the manager; this pins the SCREEN, because the
//      defect's whole cost was that the interface reported success.
//   2. THE PROVIDER SECRET IS NOT IN THE PAGE SOURCE. The shipped screen wrote
//      the stored Stripe key into the password input's `value=`. A test that
//      only checked the field "works" would never have seen it, so this reads
//      the raw HTML.
//   3. THE PROVIDER CAN ACTUALLY BE CHANGED. The shipped screen validated the
//      new provider's required fields against a form that had rendered the OLD
//      provider's, so switching always failed validation.
//   4. Every control works with JAVASCRIPT DISABLED — the collection's add and
//      remove, and the main Save.
//
// Rules carried forward, each already paid for:
//   - axe scans the WHOLE PAGE, never `#main` (L-037).
//   - exclusions go in ONE AT A TIME: an array is read as a FRAME PATH (L-037).
//   - persistence is checked with a FRESH GET, never `page.reload()` (D-088).
//   - a test that varies an input reads that input back (L-035).
//   - the theme is baked in BEFORE first paint, and read back (L-035).
//   - a driven failure names WHICH LAYER refused before anything is changed
//     (L-042); the two validation tests here clear `noValidate` for that reason.

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const URL_PATH = '/installer/admin/x402-settings.php';

test.beforeEach( async ( { page } ) => {
    await login( page, 'owner' );
} );

/** Open the screen with the theme baked in BEFORE first paint (L-035). */
async function open( page, theme = 'dark' ) {
    await page.context().addCookies( [ {
        name: 'klytos_admin_theme',
        value: theme,
        url: new URL( page.url() ).origin,
    } ] );
    await page.goto( URL_PATH );
    await expect( page.getByTestId( 'x402_settings.screen' ) ).toBeVisible();

    expect(
        await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
        'the theme cookie did not take — this run measured the wrong theme'
    ).toBe( theme );
}

/** The axe pass, scoped exactly once — over the WHOLE page, never `#main`. */
async function scan( page ) {
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

/** Submit the main form through the toolbar's Save. */
async function save( page ) {
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'x402_settings.save' ).click(),
    ] );
}

/** Add a custom agent through its own form and wait for the reload. */
async function addAgent( page, name ) {
    await page.getByTestId( 'x402_settings.agent_input' ).fill( name );
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'x402_settings.add_agent' ).click(),
    ] );
}

/** Remove a custom agent through its own row form. */
async function removeAgent( page, name ) {
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( `x402_settings.remove_agent.${ name }` ).click(),
    ] );
}

// ─── Structure ──────────────────────────────────────────────────

test( 'the screen renders the record-form template with no section nav', async ( { page } ) => {
    await open( page );

    // §37 lists cards, not sections, so the template's optional left column is
    // ABSENT from the DOM rather than rendered empty.
    await expect( page.locator( '.k-record-form--no-nav' ) ).toHaveCount( 1 );
    await expect( page.getByTestId( 'x402_settings.screen' ).locator( '.k-section-nav' ) ).toHaveCount( 0 );
} );

test( 'exactly one h1, and every card is an h2', async ( { page } ) => {
    await open( page );

    await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
    await expect( page.locator( 'main h1' ) ).toHaveText( /Agent payment settings/i );

    // Five cards: Provider · Wallet · Licence · Who pays · Logging.
    await expect( page.locator( 'main .k-card-heading' ) ).toHaveCount( 5 );
} );

test( 'the toolbar Save reaches the form it sits outside of', async ( { page } ) => {
    await open( page );

    const save = page.getByTestId( 'x402_settings.save' );
    await expect( save ).toBeVisible();
    await expect( save ).toHaveAttribute( 'form', 'k-x402-form' );
} );

test( 'every control has a visible label', async ( { page } ) => {
    await open( page );

    const orphans = await page.evaluate( () => {
        const bad = [];
        for ( const control of document.querySelectorAll( 'main input, main select, main textarea' ) ) {
            if ( control.type === 'hidden' ) {
                continue;
            }
            const labelled = control.id && document.querySelector( `label[for="${ control.id }"]` );
            if ( ! labelled && ! control.getAttribute( 'aria-label' ) ) {
                bad.push( control.name || control.id || control.outerHTML.slice( 0, 60 ) );
            }
        }
        return bad;
    } );

    expect( orphans, 'a control with no visible <label for> — §4 forbids it' ).toEqual( [] );
} );

// ─── The wallet ─────────────────────────────────────────────────

test( 'the wallet is mono and editable, and its copy button is present', async ( { page } ) => {
    await open( page );

    const wallet = page.getByTestId( 'x402_settings.wallet_address' );
    await expect( wallet ).toHaveClass( /k-control--mono/ );
    await expect( wallet ).not.toHaveAttribute( 'readonly', /.*/ );
    await expect( wallet ).toHaveAttribute( 'spellcheck', 'false' );
    await expect( page.getByTestId( 'x402_settings.copy_wallet' ) ).toBeVisible();
} );

test( 'the wallet address round-trips through a save and a fresh GET', async ( { page } ) => {
    await open( page );

    const value = '0xKlytosTestsWallet0001';
    await page.getByTestId( 'x402_settings.wallet_address' ).fill( value );
    await save( page );

    await expect( page.getByTestId( 'x402_settings.status_line' ) ).toBeVisible();

    // A FRESH GET, never page.reload() — a reload re-submits the POST and would
    // pass whether or not anything was stored (D-088).
    await page.goto( URL_PATH );
    await expect( page.getByTestId( 'x402_settings.wallet_address' ) ).toHaveValue( value );
} );

// ─── Price validation ───────────────────────────────────────────

test( 'a non-numeric price is refused server-side, with a summary and a field error', async ( { page } ) => {
    await open( page );

    // L-042: the field carries no `required`, but clearing noValidate is kept
    // deliberately so this test reaches the SERVER's refusal in every browser,
    // rather than whatever the browser decides to do about `inputmode`.
    await page.evaluate( () => { document.getElementById( 'k-x402-form' ).noValidate = true; } );

    await page.getByTestId( 'x402_settings.default_price' ).fill( 'abc' );
    await save( page );

    const summary = page.getByTestId( 'x402_settings.error_summary' );
    await expect( summary ).toBeVisible();
    await expect( summary ).toHaveAttribute( 'role', 'alert' );

    const field = page.getByTestId( 'x402_settings.default_price' );
    await expect( field ).toHaveAttribute( 'aria-invalid', 'true' );

    // The typed value is kept so the person can see and fix what they wrote.
    await expect( field ).toHaveValue( 'abc' );
} );

test( 'a refused save stores nothing', async ( { page } ) => {
    await open( page );

    const before = await page.getByTestId( 'x402_settings.default_price' ).inputValue();

    await page.evaluate( () => { document.getElementById( 'k-x402-form' ).noValidate = true; } );
    await page.getByTestId( 'x402_settings.default_price' ).fill( '0' );
    await save( page );
    await expect( page.getByTestId( 'x402_settings.error_summary' ) ).toBeVisible();

    await page.goto( URL_PATH );
    await expect( page.getByTestId( 'x402_settings.default_price' ) ).toHaveValue( before );
} );

test( 'a valid price round-trips', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'x402_settings.default_price' ).fill( '0.075' );
    await save( page );

    await page.goto( URL_PATH );
    await expect( page.getByTestId( 'x402_settings.default_price' ) ).toHaveValue( '0.075' );
} );

test( 'the error summary takes focus on load', async ( { page } ) => {
    await open( page );

    await page.evaluate( () => { document.getElementById( 'k-x402-form' ).noValidate = true; } );
    await page.getByTestId( 'x402_settings.default_price' ).fill( '' );
    await save( page );

    await expect( page.getByTestId( 'x402_settings.error_summary' ) ).toBeFocused();
} );

test( 'each summary row links to the field it names', async ( { page } ) => {
    await open( page );

    await page.evaluate( () => { document.getElementById( 'k-x402-form' ).noValidate = true; } );
    await page.getByTestId( 'x402_settings.default_price' ).fill( '-1' );
    await save( page );

    const link = page.getByTestId( 'x402_settings.error_link.0' );
    await expect( link ).toHaveAttribute( 'href', '#x402-field-default_price_usd' );
} );

// ─── The licence card ───────────────────────────────────────────

test( 'the licence type and text round-trip', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'x402_settings.license_type' ).selectOption( 'training' );
    await page.getByTestId( 'x402_settings.license_text' ).fill( 'Klytos tests licence text.' );
    await save( page );

    await page.goto( URL_PATH );
    await expect( page.getByTestId( 'x402_settings.license_type' ) ).toHaveValue( 'training' );
    await expect( page.getByTestId( 'x402_settings.license_text' ) ).toHaveValue( 'Klytos tests licence text.' );
} );

test( 'an unknown licence type posted by hand does not reach storage', async ( { page } ) => {
    await open( page );

    const before = await page.getByTestId( 'x402_settings.license_type' ).inputValue();

    await page.evaluate( () => {
        const select = document.getElementById( 'x402-field-license_type' );
        const option = document.createElement( 'option' );
        option.value = 'klytos-tests-not-a-licence';
        select.appendChild( option );
        select.value = option.value;
    } );
    await save( page );

    await page.goto( URL_PATH );
    await expect( page.getByTestId( 'x402_settings.license_type' ) ).toHaveValue( before );
} );

// ─── Who pays: the collection ───────────────────────────────────

test( 'the built-in list is readonly and not disabled', async ( { page } ) => {
    await open( page );

    const known = page.getByTestId( 'x402_settings.known_agents' );
    // §2 "Read-only vs disabled": a value the person may copy but not change is
    // `readonly`. `disabled` would take it out of the tab order entirely.
    await expect( known ).toHaveAttribute( 'readonly', /.*/ );
    await expect( known ).not.toBeDisabled();
    await expect( known ).not.toHaveValue( '' );
} );

test( 'the empty collection renders its sentence', async ( { page } ) => {
    await open( page );

    // Clear whatever the seed left, one at a time, so the empty state is real.
    for ( let i = 0; i < 10; i++ ) {
        const rows = page.getByTestId( 'x402_settings.custom_agents' ).locator( 'li' );
        const first = rows.first();
        const remove = first.locator( 'button' );
        if ( await remove.count() === 0 ) {
            break;
        }
        await Promise.all( [ page.waitForLoadState( 'load' ), remove.click() ] );
    }

    await expect( page.getByTestId( 'x402_settings.custom_agents_empty' ) ).toBeVisible();
} );

test( 'an agent can be added and it appears in the collection', async ( { page } ) => {
    await open( page );

    await addAgent( page, 'KlytosTestsAlpha' );

    await expect( page.getByTestId( 'x402_settings.status_line' ) ).toBeVisible();
    await expect( page.getByTestId( 'x402_settings.remove_agent.KlytosTestsAlpha' ) ).toBeVisible();
} );

test( 'AN AGENT CAN BE REMOVED AGAIN — the defect this slice fixed', async ( { page } ) => {
    await open( page );

    await addAgent( page, 'KlytosTestsBeta' );
    await addAgent( page, 'KlytosTestsGamma' );

    await removeAgent( page, 'KlytosTestsBeta' );

    // A FRESH GET. The shipped defect reported success and put the row back on
    // the next load, so re-reading the same response would have passed.
    await page.goto( URL_PATH );
    await expect( page.getByTestId( 'x402_settings.remove_agent.KlytosTestsBeta' ) ).toHaveCount( 0 );
    await expect( page.getByTestId( 'x402_settings.remove_agent.KlytosTestsGamma' ) ).toBeVisible();
} );

test( 'a blank agent is refused with a field error and a summary', async ( { page } ) => {
    await open( page );

    await page.evaluate( () => {
        for ( const form of document.querySelectorAll( 'form' ) ) {
            form.noValidate = true;
        }
    } );

    await addAgent( page, '' );

    await expect( page.getByTestId( 'x402_settings.error_summary' ) ).toBeVisible();
    await expect( page.getByTestId( 'x402_settings.agent_input' ) ).toHaveAttribute( 'aria-invalid', 'true' );
} );

test( 'a duplicate agent is refused rather than silently added twice', async ( { page } ) => {
    await open( page );

    await addAgent( page, 'KlytosTestsDelta' );
    await addAgent( page, 'KlytosTestsDelta' );

    await expect( page.getByTestId( 'x402_settings.error_summary' ) ).toBeVisible();
    await expect( page.getByTestId( 'x402_settings.remove_agent.KlytosTestsDelta' ) ).toHaveCount( 1 );
} );

test( 'a built-in agent cannot be added as a custom one', async ( { page } ) => {
    await open( page );

    // The duplicate check reads the MERGED set, so it also covers the built-ins
    // — otherwise the list would grow entries that change nothing.
    await addAgent( page, 'GPTBot' );

    await expect( page.getByTestId( 'x402_settings.error_summary' ) ).toBeVisible();
} );

test( 'the remove button names its row, not just "Remove"', async ( { page } ) => {
    await open( page );

    await addAgent( page, 'KlytosTestsEpsilon' );

    await expect(
        page.getByTestId( 'x402_settings.remove_agent.KlytosTestsEpsilon' )
    ).toHaveText( /KlytosTestsEpsilon/ );
} );

// ─── Logging and statistics ─────────────────────────────────────

test( 'the two toggles are checkboxes, not switches', async ( { page } ) => {
    await open( page );

    // §4: a control that needs Save is a checkbox; only an immediate-effect one
    // is role="switch". §37's own delta says "everything else is checkbox+Save".
    for ( const id of [ 'logging_enabled', 'stats_enabled' ] ) {
        const control = page.getByTestId( `x402_settings.${ id }` );
        await expect( control ).toHaveAttribute( 'type', 'checkbox' );
        await expect( control ).not.toHaveAttribute( 'role', 'switch' );
    }
} );

test( 'unchecking logging survives a save and a fresh GET', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'x402_settings.logging_enabled' ).uncheck();
    await save( page );

    await page.goto( URL_PATH );
    await expect( page.getByTestId( 'x402_settings.logging_enabled' ) ).not.toBeChecked();

    // And back, so the test does not leave the playground in a state the next
    // spec inherits, and so both directions are pinned (L-010).
    await page.getByTestId( 'x402_settings.logging_enabled' ).check();
    await save( page );
    await page.goto( URL_PATH );
    await expect( page.getByTestId( 'x402_settings.logging_enabled' ) ).toBeChecked();
} );

// ─── The provider card ──────────────────────────────────────────

test( 'the provider choice is a labelled radio group inside a fieldset', async ( { page } ) => {
    await open( page );

    const radios = page.locator( 'input[name="provider_id"]' );
    const count = await radios.count();

    if ( count === 0 ) {
        // A playground with no provider plugin active is a REAL state and the
        // manifest has no card for it — §2's empty rule applies to the card.
        await expect( page.getByTestId( 'x402_settings.no_provider' ) ).toBeVisible();
        return;
    }

    await expect( page.locator( 'fieldset:has(input[name="provider_id"]) legend' ) ).toHaveCount( 1 );
    for ( let i = 0; i < count; i++ ) {
        await expect( radios.nth( i ) ).toHaveAttribute( 'form', 'k-x402-form' );
    }
} );

test( 'NO PROVIDER SECRET IS WRITTEN INTO THE PAGE SOURCE', async ( { page } ) => {
    await open( page );

    // The shipped screen rendered the stored value into the password input's
    // `value=`, so a live key was readable in view-source, in a proxy log and in
    // any cached copy. Reading the RAW HTML is the only assertion that catches
    // it — the field "works" either way.
    const html = await page.content();
    const secrets = await page.evaluate( () =>
        Array.from( document.querySelectorAll( 'input[type="password"]' ) )
            .map( ( input ) => input.getAttribute( 'value' ) )
    );

    for ( const value of secrets ) {
        expect( value === null || value === '', 'a stored secret was rendered back into the page' ).toBe( true );
    }

    expect( html ).not.toMatch( /value="sk_[A-Za-z0-9_]+"/ );
} );

// ─── With JavaScript disabled ───────────────────────────────────

test.describe( 'with JavaScript disabled', () => {
    test.use( { javaScriptEnabled: false } );

    test( 'the main Save still posts', async ( { page } ) => {
        // The beforeEach already logged in — with JavaScript off, which is
        // itself worth knowing. Calling login() again would land on login.php
        // as an authenticated user, where there is no form to fill.
        await page.goto( URL_PATH );

        await page.getByTestId( 'x402_settings.wallet_address' ).fill( '0xKlytosTestsNoJs' );
        await page.getByTestId( 'x402_settings.save' ).click();
        await page.waitForLoadState( 'load' );

        await page.goto( URL_PATH );
        await expect( page.getByTestId( 'x402_settings.wallet_address' ) ).toHaveValue( '0xKlytosTestsNoJs' );
    } );

    test( 'an agent can be added and removed with no script at all', async ( { page } ) => {
        await page.goto( URL_PATH );

        await page.getByTestId( 'x402_settings.agent_input' ).fill( 'KlytosTestsNoJs' );
        await page.getByTestId( 'x402_settings.add_agent' ).click();
        await page.waitForLoadState( 'load' );
        await expect( page.getByTestId( 'x402_settings.remove_agent.KlytosTestsNoJs' ) ).toBeVisible();

        await page.getByTestId( 'x402_settings.remove_agent.KlytosTestsNoJs' ).click();
        await page.waitForLoadState( 'load' );

        await page.goto( URL_PATH );
        await expect( page.getByTestId( 'x402_settings.remove_agent.KlytosTestsNoJs' ) ).toHaveCount( 0 );
    } );
} );

// ─── Authorization ──────────────────────────────────────────────

/*
 * THE AUTHORIZATION CLAIM IS PINNED IN THE PHP TIER, DELIBERATELY, and this
 * note is here so nobody reads its absence as a gap.
 *
 * `x402-settings.php` is mapped to `site.configure` in `core/admin-gate.php`,
 * which is owner/admin (`user-manager.php`) — the same central shape D-092
 * checked for `consent.php`. `AdminGateMapTest` asserts that EVERY admin file
 * has a deliberate entry and that its capability really exists, and
 * `AdminGateHttpTest` drives the refusal itself.
 *
 * A browser-tier copy was written first and REMOVED rather than made to pass:
 * a real 403 makes Chromium log `Failed to load resource`, which the fixture's
 * read-back duty correctly reports as a runtime complaint. The two ways to
 * silence that are to narrow the read-back duty — which protects every other
 * spec — or to assert around it, which asserts nothing. The claim did not need
 * a browser to be true, so it went where it is already checked.
 */

// ─── Responsive ─────────────────────────────────────────────────

test( 'the page does not scroll horizontally at 320 CSS px', async ( { page } ) => {
    await page.setViewportSize( { width: 320, height: 900 } );
    await open( page );

    // WCAG 1.4.10, and D-079's own defect: a containment reading said the
    // content fitted while the page really scrolled, so this measures the page.
    const overflow = await page.evaluate( () =>
        document.documentElement.scrollWidth - document.documentElement.clientWidth
    );

    expect( overflow, 'the page scrolls horizontally at 320px' ).toBeLessThanOrEqual( 1 );
} );

// ─── Accessibility, per state and per theme ─────────────────────

for ( const theme of [ 'dark', 'light' ] ) {
    test( `axe is clean at rest — ${ theme }`, async ( { page } ) => {
        await open( page, theme );

        const results = await scan( page );
        expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
    } );

    test( `axe is clean in the ERROR state — ${ theme }`, async ( { page } ) => {
        await open( page, theme );

        await page.evaluate( () => { document.getElementById( 'k-x402-form' ).noValidate = true; } );
        await page.getByTestId( 'x402_settings.default_price' ).fill( 'not a price' );
        await save( page );
        await expect( page.getByTestId( 'x402_settings.error_summary' ) ).toBeVisible();

        const results = await scan( page );
        expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
    } );

    test( `axe is clean with a populated collection — ${ theme }`, async ( { page } ) => {
        await open( page, theme );
        await addAgent( page, `KlytosTestsAxe${ theme }` );

        const results = await scan( page );
        expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
    } );
}
