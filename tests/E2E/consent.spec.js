// Manifest entry 25 — Consent — driven per STATE, in both themes, PLUS the
// shipped banner the entry's own delta binds to the same rule.
//
// This screen is the only one in the build whose manifest entry reaches OUT of
// the admin: §25's deltas govern "the banner preview AND the shipped banner",
// and `accessibility.md` §10.4 calls the banner "the hardest case" and gives it
// "the strictest rule". So this spec drives two surfaces:
//
//   1. `consent.php`, like every other stage-5 screen.
//   2. `installer/core/assets/consent-manager.js` — the library the build engine
//      copies into the generated site — served into a real page so its dialog
//      semantics, its focus trap, its Esc behaviour and its two choices'
//      geometry are MEASURED rather than read.
//
// Rules carried forward, each already paid for:
//   - axe scans the WHOLE PAGE, never `#main` (L-037).
//   - exclusions go in ONE AT A TIME: an array is read as a FRAME PATH (L-037).
//   - persistence is checked with a FRESH GET, never `page.reload()` (D-088).
//   - the two-step confirm is driven with JavaScript DISABLED (D-089).
//   - the theme is baked in BEFORE first paint, and read back (L-035).

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );
const CONSENT_URL = '/installer/admin/consent.php';
const BANNER_URL = '/installer/admin/__consent-banner.html';
const LIBRARY_FILE = path.join( REPO_ROOT, 'installer/core/assets/consent-manager.js' );

/** The banner text the fixture stores, and therefore what the preview must show. */
const BANNER_TEXT = 'We use our own cookies to run this site.';

/**
 * Put the playground's consent state back to a known shape.
 *
 * @param {{declare?: boolean, categories?: boolean}} options
 */
function reset( options = {} ) {
    const args = [ path.join( REPO_ROOT, 'tests/E2E/fixtures/reset-consent.php' ) ];
    if ( options.declare ) {
        args.push( '--declare' );
    }
    if ( options.categories ) {
        args.push( '--categories' );
    }
    return JSON.parse( execFileSync( 'php', args, {
        cwd: REPO_ROOT,
        env: { ...process.env, XDEBUG_MODE: 'off' },
    } ).toString() );
}

test.afterEach( async () => {
    reset();
} );

/**
 * Open the screen with the theme baked in BEFORE first paint (L-035).
 *
 * A cookie whose name the shell does not read makes every "light" run measure
 * dark, and nothing in the output would tell you — which is exactly what
 * happened once, so the value is read back out of the document.
 */
async function open( page, theme = 'dark' ) {
    await page.context().addCookies( [ {
        name: 'klytos_admin_theme',
        value: theme,
        url: new URL( page.url() ).origin,
    } ] );
    await page.goto( CONSENT_URL );
    await expect( page.getByTestId( 'consent.form' ) ).toBeVisible();

    expect(
        await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
        'the theme cookie did not take — this run measured the wrong theme'
    ).toBe( theme );
}

/*
 * axe's `aria-required-children` fires on `<table role="table">` carrying a
 * `<caption>`: an explicitly-roled table may own only rowgroup/row, and a
 * caption is neither. But `accessibility.md` §2.1 MANDATES both, and Chromium's
 * real accessibility tree is exactly what §2.1 describes — read out of the
 * browser rather than argued about (D-078), and disabled the same way in
 * pages.spec.js and logs.spec.js.
 *
 * Disabled for the whole-page pass rather than for a table-scoped one, because
 * the pass is whole-page on purpose (L-037) and giving that up to narrow this
 * exception would trade a real class of finding for a false one. What the rule
 * would have protected is pinned by the explicit-role test above, which asserts
 * every role §2.1 requires by name.
 */
const TABLE_RULE_EXCEPTIONS = [ 'aria-required-children' ];

/** The axe pass, over the WHOLE page, never `#main`. */
async function scan( page ) {
    let builder = new AxeBuilder( { page } )
        .withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
        .disableRules( TABLE_RULE_EXCEPTIONS );

    // One at a time: an array is read as a frame path, not as a list (L-037).
    for ( const gap of KNOWN_DELIVERY_GAPS ) {
        builder = builder.exclude( gap );
    }
    for ( const surface of DEV_ONLY_SURFACES ) {
        builder = builder.exclude( surface );
    }

    return builder.analyze();
}

// ─── The admin screen ───────────────────────────────────────────

test.describe( 'Entry 25 — the screen', () => {
    test.beforeEach( async ( { page } ) => {
        reset( { declare: true, categories: true } );
        await login( page, 'owner' );
    } );

    test( 'renders the three backed cards and no Acceptance stats card', async ( { page } ) => {
        await open( page );

        await expect( page.locator( 'h1' ) ).toHaveText( 'Consent' );
        await expect( page.locator( 'h1' ) ).toHaveCount( 1 );

        for ( const heading of [ 'Banner configuration', 'Banner preview' ] ) {
            await expect( page.getByRole( 'heading', { level: 2, name: heading } ) ).toBeVisible();
        }
        // The audit card is labelled by its <caption>, which §2.1 requires to be
        // the visible heading row rather than an <h2> beside it.
        await expect( page.getByTestId( 'consent.audit_table' ) ).toBeVisible();

        // Acceptance stats is DEFERRED: the product stores no acceptance data at
        // all. Asserted as ABSENT so it cannot be quietly invented later from a
        // number nobody measured.
        await expect(
            page.getByRole( 'heading', { name: /acceptance/i } )
        ).toHaveCount( 0 );
    } );

    test( 'the equal-prominence rule is a STATEMENT, never a control', async ( { page } ) => {
        await open( page );

        // manifest §25: "The configuration screen offers NO option to make
        // reject less prominent; that option does not exist." The prototype
        // draws it as a switch, which is the delivery contradicting itself
        // (DR-008). This asserts the SPEC's reading, so the drawing's version
        // cannot arrive later without failing here.
        const note = page.getByTestId( 'consent.prominence_note' );
        await expect( note ).toBeVisible();
        expect( await note.evaluate( ( el ) => el.tagName ) ).toBe( 'P' );
        await expect( note.locator( 'input, button, select, [role="switch"]' ) ).toHaveCount( 0 );

        // And nothing anywhere else on the screen offers it either.
        await expect( page.locator( '[name*="prominen"], [name*="reject"]' ) ).toHaveCount( 0 );
    } );

    test( 'the preview draws Reject and Accept as the same component at the same size', async ( { page } ) => {
        await open( page );

        const reject = page.getByTestId( 'consent.preview_reject' );
        const accept = page.getByTestId( 'consent.preview_accept' );

        await expect( reject ).toBeVisible();
        await expect( accept ).toBeVisible();

        // SAME COMPONENT: identical class lists, so there is no modifier on one
        // that the other lacks. Read out of the DOM rather than assumed.
        const classesOf = ( locator ) =>
            locator.evaluate( ( el ) => Array.from( el.classList ).sort().join( ' ' ) );
        expect( await classesOf( reject ) ).toBe( await classesOf( accept ) );

        // SAME SIZE and SAME WEIGHT, measured in the browser. Height, width and
        // font-weight all have to match: equal padding around unequal words
        // still draws unequal buttons, which is why the rule says "size".
        const boxOf = ( locator ) =>
            locator.evaluate( ( el ) => {
                const rect = el.getBoundingClientRect();
                const style = getComputedStyle( el );
                return {
                    width: Math.round( rect.width ),
                    height: Math.round( rect.height ),
                    weight: style.fontWeight,
                    background: style.backgroundColor,
                    color: style.color,
                };
            } );

        expect( await boxOf( reject ) ).toEqual( await boxOf( accept ) );
    } );

    test( 'the preview shows the stored banner text', async ( { page } ) => {
        await open( page );
        await expect( page.getByTestId( 'consent.preview_text' ) ).toHaveText( BANNER_TEXT );
    } );
} );

// ─── The audit table ────────────────────────────────────────────

test.describe( 'Entry 25 — the cookie audit', () => {
    test.beforeEach( async ( { page } ) => {
        await login( page, 'owner' );
    } );

    test( 'carries §2.1\'s explicit role set and its four columns', async ( { page } ) => {
        reset( { declare: true } );
        await open( page );

        const table = page.getByTestId( 'consent.audit_table' );
        await expect( table ).toHaveAttribute( 'role', 'table' );

        const headers = table.locator( 'thead th[role="columnheader"][scope="col"]' );
        await expect( headers ).toHaveText( [ 'Cookie', 'Type', 'Duration', 'Description' ] );

        // The naming column is a rowheader, not a cell — this is what makes
        // "Analytics, column Type, row _pk_id" work.
        await expect( table.locator( 'tbody th[role="rowheader"][scope="row"]' ) ).toHaveCount( 3 );

        // The grid goes on the table ELEMENTS, and the value is the delivery's.
        const columns = await table
            .locator( 'tbody tr' )
            .first()
            .evaluate( ( el ) => getComputedStyle( el ).gridTemplateColumns );
        expect(
            columns.split( ' ' ).length,
            'the four-track grid did not apply — the row fell back to one column'
        ).toBe( 4 );
    } );

    test( 'the caption counts what is really declared', async ( { page } ) => {
        const state = reset( { declare: true } );
        await open( page );

        // The fixture reads its numbers back through the product's own audit, so
        // this compares the screen against the manager rather than against a
        // number written twice.
        const caption = await page.getByTestId( 'consent.audit_table' ).locator( 'caption' ).innerText();
        expect( caption ).toContain( `declared cookies: ${ state.total_cookies }` );
        expect( caption ).toContain( `plugins: ${ state.total_plugins }` );

        // Guard against the defect this screen was built on top of: an audit
        // that reports zero whatever is declared.
        expect( state.total_cookies ).toBeGreaterThan( 0 );
    } );

    test( 'the empty state is one row spanning every column, not a table replaced by a div', async ( { page } ) => {
        reset();
        await open( page );

        const table = page.getByTestId( 'consent.audit_table' );
        await expect( table ).toBeVisible();
        await expect( page.getByTestId( 'consent.audit_empty' ) ).toBeVisible();

        const span = await table
            .locator( 'tbody td' )
            .first()
            .getAttribute( 'colspan' );
        expect( span ).toBe( '4' );
    } );

    test( 'both exports answer with a real attachment', async ( { page } ) => {
        reset( { declare: true } );
        await open( page );

        for ( const [ testId, fragment ] of [
            [ 'consent.export_json', 'cookie-audit' ],
            [ 'consent.export_csv', 'cookie-audit' ],
        ] ) {
            const [ download ] = await Promise.all( [
                page.waitForEvent( 'download' ),
                page.getByTestId( testId ).click(),
            ] );
            expect( download.suggestedFilename() ).toContain( fragment );
        }
    } );
} );

// ─── Saving ─────────────────────────────────────────────────────

test.describe( 'Entry 25 — saving', () => {
    test.beforeEach( async ( { page } ) => {
        await login( page, 'owner' );
    } );

    test( 'a saved value is stored, checked with a fresh GET', async ( { page } ) => {
        reset();
        await open( page );

        await page.getByTestId( 'consent.banner_text' ).fill( 'Edited banner text.' );
        await page.getByTestId( 'consent.cookie_days' ).fill( '90' );
        await Promise.all( [
            page.waitForLoadState( 'load' ),
            page.getByTestId( 'consent.save' ).click(),
        ] );

        await expect( page.getByTestId( 'consent.status_line' ) ).toBeVisible();

        // A FRESH GET, never page.reload(): reloading a POST response re-submits
        // it, so the check would pass whether or not anything was stored (D-088).
        await page.goto( CONSENT_URL );
        await expect( page.getByTestId( 'consent.banner_text' ) ).toHaveValue( 'Edited banner text.' );
        await expect( page.getByTestId( 'consent.cookie_days' ) ).toHaveValue( '90' );
    } );

    test( 'saving does NOT delete a custom category', async ( { page } ) => {
        // The regression this screen's rewrite fixes. The screen it replaces
        // rebuilt `categories` from a `custom_categories` field its own form
        // never rendered, so it always passed an empty array and `saveConfig()`
        // wiped every category created over MCP. A save from a screen that does
        // not edit a value must not be able to destroy it.
        const before = reset( { categories: true } );
        expect( before.custom_categories ).toContain( 'e2e-custom' );

        await open( page );
        await Promise.all( [
            page.waitForLoadState( 'load' ),
            page.getByTestId( 'consent.save' ).click(),
        ] );
        await expect( page.getByTestId( 'consent.status_line' ) ).toBeVisible();

        const after = JSON.parse( execFileSync( 'php', [
            '-r',
            'require "installer/core/app.php";'
            + '$a=\\Klytos\\Core\\App::getInstance();$a->boot();'
            + 'echo json_encode(array_keys($a->getConsentManager()->getConfig()["categories"] ?? []));',
        ], { cwd: REPO_ROOT, env: { ...process.env, XDEBUG_MODE: 'off' } } ).toString() );

        expect(
            after,
            'the save deleted a custom category it does not edit'
        ).toContain( 'e2e-custom' );
    } );

    test( 'an empty banner text and a bad duration both reach the error summary', async ( { page } ) => {
        reset();
        await open( page );

        await page.getByTestId( 'consent.banner_text' ).fill( '' );
        await page.getByTestId( 'consent.cookie_days' ).fill( '9000' );

        // `required` and `max` would stop the submit in a browser, and the
        // SERVER-side refusal is what this asserts — so the constraints are
        // removed for this one post, which is exactly what a client that ignores
        // them does.
        await page.getByTestId( 'consent.form' ).evaluate( ( form ) => form.setAttribute( 'novalidate', '' ) );

        await Promise.all( [
            page.waitForLoadState( 'load' ),
            page.getByTestId( 'consent.save' ).click(),
        ] );

        const summary = page.getByTestId( 'consent.error_summary' );
        await expect( summary ).toBeVisible();
        await expect( summary ).toHaveAttribute( 'role', 'alert' );
        await expect( summary.locator( 'li' ) ).toHaveCount( 2 );

        // §2: the summary takes focus on load.
        await expect( summary ).toBeFocused();

        // Field level: aria-invalid and the message wired through describedby.
        await expect( page.getByTestId( 'consent.banner_text' ) ).toHaveAttribute( 'aria-invalid', 'true' );
        await expect( page.getByTestId( 'consent.cookie_days' ) ).toHaveAttribute( 'aria-invalid', 'true' );

        const describedBy = await page.getByTestId( 'consent.cookie_days' ).getAttribute( 'aria-describedby' );
        expect( describedBy.split( ' ' )[ 0 ] ).toBe( 'consent-hint-cookie_days' ); // hint FIRST
        expect( describedBy ).toContain( 'consent-error-cookie_days' );
    } );
} );

// ─── The two-step confirm, with JavaScript disabled ─────────────

test.describe( 'Entry 25 — removing a declaration with no JavaScript', () => {
    test.use( { javaScriptEnabled: false } );

    test( 'the confirm is two server-side steps and the armed label states what really happens', async ( { page } ) => {
        reset( { declare: true } );
        await login( page, 'owner' );
        await page.goto( CONSENT_URL );

        const row = page.getByTestId( 'consent.declaration.e2e-analytics' );
        await expect( row ).toBeVisible();

        // Step one arms; it must NOT delete.
        await Promise.all( [
            page.waitForLoadState( 'load' ),
            page.getByTestId( 'consent.remove.e2e-analytics' ).click(),
        ] );

        const confirm = page.getByTestId( 'consent.confirm_remove.e2e-analytics' );
        await expect( confirm ).toBeVisible();
        await expect( page.getByTestId( 'consent.declaration.e2e-analytics' ) ).toBeVisible();

        // The armed label says what `deletePluginDeclaration()` really does: it
        // removes the AUDIT ENTRY. The plugin stays installed and keeps setting
        // its cookies — the template's own example sentence would be false twice.
        await expect( confirm ).toContainText( 'E2E Analytics' );
        await expect( confirm ).toContainText( 'stays installed' );

        // Step two deletes.
        await Promise.all( [
            page.waitForLoadState( 'load' ),
            confirm.click(),
        ] );
        await expect( page.getByTestId( 'consent.declaration.e2e-analytics' ) ).toHaveCount( 0 );
        // The other one is untouched.
        await expect( page.getByTestId( 'consent.declaration.e2e-essential' ) ).toBeVisible();
    } );
} );

// ─── The accessibility pass ─────────────────────────────────────

for ( const theme of [ 'light', 'dark' ] ) {
    test( `no axe violation over the whole page, populated, ${ theme }`, async ( { page } ) => {
        reset( { declare: true } );
        await login( page, 'owner' );
        await open( page, theme );

        const results = await scan( page );
        expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
    } );

    test( `no axe violation over the whole page, empty audit, ${ theme }`, async ( { page } ) => {
        reset();
        await login( page, 'owner' );
        await open( page, theme );

        const results = await scan( page );
        expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
    } );
}

// ─── The SHIPPED banner — accessibility.md §10.4 ────────────────
//
// The library is served into a bare page through route interception, at an
// `/installer/admin/` URL so it shares the playground's real origin. It is the
// same technique the component specimen uses (D-078) and for the same reason:
// the thing under test is a delivered asset, not a product URL, and inventing a
// product URL for it would be inventing product.

// The library is loaded with a real <script src>, not inlined.
//
// Inlining it looked simpler and was wrong: the file's own usage docblock
// contains the literal `</script>`, which closes the block early and leaves the
// rest of the library being parsed as HTML. It failed loudly ("Unexpected token
// '*'", "ConsentManager is not defined") rather than quietly, but the lesson is
// the general one — an external <script src> is also how the generated site
// actually loads it, so the harness now matches the product.
const BANNER_LIBRARY_URL = '/installer/admin/__consent-manager.js';

const BANNER_PAGE = () => `<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Banner harness</title></head>
<body>
  <main>
    <h1>A page with content behind the banner</h1>
    <a href="#somewhere" id="page-link">A link on the page</a>
    <button type="button" id="page-button">A button on the page</button>
  </main>
  <script src="${ BANNER_LIBRARY_URL }"></script>
  <script>
    ConsentManager.init( {
      autoShow: true,
      bannerText: 'We use cookies.',
      cookieDays: 90,
      labels: {
        bannerTitle: 'Cookies',
        acceptAll: 'Accept all',
        rejectAll: 'Reject all',
        preferences: 'Preferences',
        privacyPolicy: 'Privacy policy'
      }
    } );
  </script>
</body>
</html>`;

async function openBanner( page ) {
    const library = fs.readFileSync( LIBRARY_FILE, 'utf8' );

    await page.route( `**${ BANNER_LIBRARY_URL }`, ( route ) =>
        route.fulfill( {
            status: 200,
            contentType: 'text/javascript; charset=utf-8',
            body: library,
        } )
    );
    await page.route( `**${ BANNER_URL }`, ( route ) =>
        route.fulfill( {
            status: 200,
            contentType: 'text/html; charset=utf-8',
            body: BANNER_PAGE(),
        } )
    );

    await page.goto( BANNER_URL );

    // The library must actually have arrived: an unstyled, uninitialised page
    // would fail the assertions below for the wrong reason.
    await expect( page.locator( '.cm-banner' ) ).toBeVisible();
}

test.describe( 'The shipped banner — §10.4, the strictest rule in the bundle', () => {
    test( 'is a modal dialog named by a REAL heading', async ( { page } ) => {
        await openBanner( page );

        const banner = page.locator( '.cm-banner' );
        await expect( banner ).toHaveAttribute( 'role', 'dialog' );
        await expect( banner ).toHaveAttribute( 'aria-modal', 'true' );

        // aria-labelledby pointing at a real <h2>, not an aria-label: §10.4 says
        // "a real heading", and a label names the dialog without giving the page
        // a heading anyone can navigate to.
        const labelledBy = await banner.getAttribute( 'aria-labelledby' );
        expect( labelledBy, 'the banner names itself with aria-label instead of a heading' ).toBeTruthy();
        const heading = page.locator( `#${ labelledBy }` );
        await expect( heading ).toBeVisible();
        expect( await heading.evaluate( ( el ) => el.tagName ) ).toBe( 'H2' );
    } );

    test( 'Reject all is the same component, size and weight as Accept all', async ( { page } ) => {
        await openBanner( page );

        const reject = page.locator( '[data-cm-action="accept-necessary"]' );
        const accept = page.locator( '[data-cm-action="accept-all"]' );

        // What SHIPPED was `cm-btn-primary` against `cm-btn-secondary` — the
        // exact "colour trick" the rule names. Both now carry one class.
        const classesOf = ( locator ) =>
            locator.evaluate( ( el ) => Array.from( el.classList ).sort().join( ' ' ) );
        expect( await classesOf( reject ) ).toBe( await classesOf( accept ) );

        const boxOf = ( locator ) =>
            locator.evaluate( ( el ) => {
                const rect = el.getBoundingClientRect();
                const style = getComputedStyle( el );
                return {
                    width: Math.round( rect.width ),
                    height: Math.round( rect.height ),
                    weight: style.fontWeight,
                    background: style.backgroundColor,
                    color: style.color,
                    fontSize: style.fontSize,
                };
            } );

        expect( await boxOf( reject ) ).toEqual( await boxOf( accept ) );
    } );

    test( 'focus moves into the banner and is trapped in both directions', async ( { page } ) => {
        await openBanner( page );

        const inBanner = () =>
            page.evaluate( () => !! document.activeElement.closest( '.cm-banner' ) );

        /*
         * WAITED FOR, not assumed. The banner takes focus inside the
         * `requestAnimationFrame` that reveals it — deliberately, because
         * focusing an element still translated off-screen scrolls the page to
         * it in Chromium — so `.cm-banner` becoming visible and focus having
         * moved are two different moments.
         *
         * The first version of this test read `activeElement` immediately and
         * passed on its own and failed inside the full run, which is the shape
         * of a race rather than of a defect. Polling asserts the same property
         * — focus DOES arrive — without depending on how loaded the machine is.
         */
        await expect.poll(
            inBanner,
            { message: 'focus was never moved into the dialog' }
        ).toBe( true );

        // Forwards past the last control, and backwards past the first: both
        // must land back inside. Tabbing more times than there are controls is
        // the point — the trap has to survive a wrap.
        for ( let i = 0; i < 6; i++ ) {
            await page.keyboard.press( 'Tab' );
            expect( await inBanner(), `Tab ${ i + 1 } escaped the dialog` ).toBe( true );
        }
        for ( let i = 0; i < 6; i++ ) {
            await page.keyboard.press( 'Shift+Tab' );
            expect( await inBanner(), `Shift+Tab ${ i + 1 } escaped the dialog` ).toBe( true );
        }
    } );

    test( 'Esc rejects non-essential rather than merely dismissing', async ( { page } ) => {
        await openBanner( page );

        await page.keyboard.press( 'Escape' );
        await expect( page.locator( '.cm-banner' ) ).toBeHidden();

        // A CHOICE was recorded, and it was the lawful minimal one. A dismissal
        // that stored nothing would re-open on the next page and would leave
        // non-essential scripts in whatever state they were in.
        const state = await page.evaluate( () => ConsentManager.getConsentState() );
        expect( state, 'Esc dismissed the banner without recording a choice' ).not.toBeNull();
        expect( state.necessary ).toBe( true );
        expect( state.analytics ).toBe( false );
        expect( state.marketing ).toBe( false );
    } );

    test( 'the configured duration reaches the cookie instead of the library constant', async ( { page } ) => {
        // `cookie_days` was an editable admin field that never reached this file:
        // the library's own 365-day constant won every time, so configuring the
        // duration did nothing at all.
        await openBanner( page );
        await page.locator( '[data-cm-action="accept-all"]' ).click();

        const cookie = ( await page.context().cookies() )
            .find( ( c ) => c.name === '__consent_prefs' );

        expect( cookie, 'no consent cookie was written' ).toBeTruthy();

        // 90 days from now, allowing a generous window for clock and rounding.
        const days = ( cookie.expires * 1000 - Date.now() ) / 86400000;
        expect( days ).toBeGreaterThan( 88 );
        expect( days ).toBeLessThan( 92 );
    } );

    test( 'no axe violation while the banner is open', async ( { page } ) => {
        await openBanner( page );

        const results = await new AxeBuilder( { page } )
            .withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
            .analyze();

        expect( results.violations, JSON.stringify( results.violations, null, 2 ) ).toEqual( [] );
    } );
} );
