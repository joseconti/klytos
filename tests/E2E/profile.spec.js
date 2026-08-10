// Manifest entry 27 — Profile — driven per card and per STATE, in both themes.
//
// THE THREE REPRODUCTIONS, written and seen failing against the SHIPPED markup
// before a line of the rewrite existed:
//
//   1. A REJECTED SAVE MUST NOT HAVE SAVED HALF OF ITSELF. The shipped handler
//      called `UserManager::update()` first and `changePassword()` second, and
//      the 12-character floor lives in `changePassword()`. So a person who typed
//      a short new password was told the save had FAILED while their name, email
//      and bio had already been written. The error is truthful about the
//      password and false about everything else, and nothing on the screen tells
//      them which half landed.
//        red observed: `the save was reported as failed and the identity half
//        had already been written — Expected: "Profile" · Received: "Renamed"`.
//   2. A REJECTED CSRF TOKEN MUST SAY SO. `if ( POST && klytos_verify_csrf() )`
//      has no else: an expired token made the whole handler vanish and the page
//      re-rendered as if nothing had been submitted. The person's edits are gone
//      and the screen reports nothing at all. L-041's family again.
//        red observed: `locator('main [role="alert"]:not(#k-live-alert)') —
//        element(s) not found`.
//   3. EVERY CONTROL HAS AN ACCESSIBLE NAME. Every `<label>` on the shipped
//      screen was a sibling of its control with no `for` and no wrapping, so not
//      one field on this screen had a programmatic label.
//        red observed: `label × 4, select-name × 1, color-contrast × 1`.
//
// And a FOURTH defect the read-back duty found rather than a test: the avatar
// preview requested `https://www.gravatar.com/…` on every load, which the
// admin's OWN Content-Security-Policy (`img-src 'self' data:`) blocks. It has
// been a broken image plus a console error on every install since it shipped.
//        red observed: `console.error: Loading the image
//        'https://www.gravatar.com/avatar/…' violates the following Content
//        Security Policy directive: "img-src 'self' data:". The action has been
//        blocked.`
//
// Rules carried forward, each already paid for:
//   - axe scans the WHOLE PAGE, never `#main` (L-037).
//   - exclusions go in ONE AT A TIME: an array is read as a FRAME PATH (L-037).
//   - persistence is checked with a FRESH GET, never `page.reload()` (D-088).
//   - a test that varies an input reads that input back (L-035).
//   - the theme is baked in BEFORE first paint, and read back (L-035).
//   - controls are scoped to the CARD or to `main`, never `.first()` on the page
//     (L-042) — the shell puts its own search form and its theme form ahead of
//     anything `main` contains, and this screen has a SECOND theme control, so
//     an unscoped theme locator would drive the shell's.
//   - this screen is driven as a DISPOSABLE ACCOUNT, because it edits the
//     password of whoever is logged in — D-099's rule from the other side.

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { test, expect, loginAs, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const URL_PATH = '/installer/admin/profile.php';
const FIXTURE = path.join( __dirname, 'fixtures', 'reset-profile.php' );

const SUBJECT = 'profile-subject';
const SUBJECT_PASSWORD = 'playground-profile-subject-2026';

/** The identity values the fixture rebuilds, mirrored from reset-profile.php. */
const START = {
    first_name: 'Profile',
    last_name: 'Subject',
    website: 'https://example.invalid/profile-subject',
    email: 'profile-subject@example.invalid',
};

/** Rebuild the disposable account, whatever the previous test left it as. */
function resetSubject() {
    execFileSync( 'php', [ FIXTURE ], { cwd: path.join( __dirname, '..', '..' ) } );
}

test.beforeEach( async ( { page } ) => {
    resetSubject();
    await loginAs( page, SUBJECT, SUBJECT_PASSWORD );
} );

/** Open the screen with the theme baked in BEFORE first paint (L-035). */
async function open( page, theme = 'dark' ) {
    await page.context().addCookies( [ {
        name: 'klytos_admin_theme',
        value: theme,
        url: new URL( page.url() ).origin,
    } ] );
    await page.goto( URL_PATH );
    await expect( page.getByTestId( 'profile.screen' ) ).toBeVisible();

    expect(
        await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
        'the theme cookie did not take — this run measured the wrong theme'
    ).toBe( theme );
}

/**
 * The screen's own error region.
 *
 * Scoped away from `#k-live-alert`, which is the SHELL's empty live region and
 * sits inside `main` on every screen: an unscoped `getByRole('alert')` resolves
 * to it and reports "hidden" instead of "the screen said nothing", which is a
 * red about the wrong layer (L-042). This spec lost its first red to exactly
 * that, in the same shape entry 26 did.
 */
function errorSummary( page ) {
    return page.locator( 'main [role="alert"]:not(#k-live-alert)' );
}

/** The value a field holds on a FRESH GET — never `page.reload()` (D-088). */
async function persistedValue( page, name ) {
    await page.goto( URL_PATH );
    return page.locator( `main [name="${ name }"]` ).inputValue();
}

/** Save the form the way a person does: the primary Save, wherever it lives. */
async function save( page ) {
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.locator( 'main [name="email"]' ).press( 'Enter' ),
    ] );
}

/** Fill the confirmation the screen requires for every save. */
async function confirmIdentity( page, password = SUBJECT_PASSWORD ) {
    await page.getByTestId( 'profile.current_password' ).fill( password );
}

/**
 * Post a new password the SERVER has to reject.
 *
 * `minlength` makes the browser refuse to submit before the request is ever
 * made, so a test that only fills the field measures Chromium's constraint
 * validation and never reaches the handler — that was this spec's second wrong
 * red, and it is L-042's rule in its plainest form: establish WHICH layer
 * refused before concluding anything. The floor lives in
 * `UserManager::changePassword()`, and every client that is not this browser —
 * curl, a script, an old user agent — arrives at that floor directly. Removing
 * the attribute is how the test reaches the layer the finding is about.
 */
async function postShortPassword( page ) {
    await page.evaluate( () => {
        document.querySelector( 'main [name="new_password"]' ).removeAttribute( 'minlength' );
    } );
    await page.getByTestId( 'profile.new_password' ).fill( 'short' );
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

function violations( results ) {
    return results.violations.map( ( v ) => `${ v.id } × ${ v.nodes.length }` );
}

// ─── Structure ──────────────────────────────────────────────────

test( 'the screen renders the record-form template with no section nav', async ( { page } ) => {
    await open( page );

    // §27 lists cards, not sections, so the template's optional left column is
    // ABSENT from the DOM rather than rendered empty.
    await expect( page.locator( '.k-record-form--no-nav' ) ).toHaveCount( 1 );
    await expect( page.getByTestId( 'profile.screen' ).locator( '.k-section-nav' ) ).toHaveCount( 0 );

    await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );

    const headings = page.getByTestId( 'profile.screen' ).locator( 'h2' );
    await expect( headings ).toHaveCount( 3 );
    await expect( headings.nth( 0 ) ).toHaveAttribute( 'id', 'profile-identity-heading' );
    await expect( headings.nth( 1 ) ).toHaveAttribute( 'id', 'profile-security-heading' );
    await expect( headings.nth( 2 ) ).toHaveAttribute( 'id', 'profile-preferences-heading' );
} );

test( 'the Sessions card is absent, and absent on purpose', async ( { page } ) => {
    await open( page );

    // §27 names four cards and this build renders three. The fourth has no
    // product behind it at all — no session registry, no per-session revoke,
    // and MCP clients that carry no user id (D-100, roadmap §0c). Pinned so
    // that "deferred" cannot quietly become "forgotten", and so that building
    // it later has to delete this assertion deliberately.
    await expect( page.getByTestId( 'profile.screen' ).locator( '.k-table' ) ).toHaveCount( 0 );
    await expect( page.getByTestId( 'profile.sessions' ) ).toHaveCount( 0 );
} );

test( 'the primary Save is in the toolbar and Enter in a field submits', async ( { page } ) => {
    await open( page );

    const save = page.getByTestId( 'profile.save' );
    await expect( save ).toBeVisible();

    // §1: the toolbar sits OUTSIDE the form, so the button is associated by
    // `form=` — which is also what makes it the form's implicit submit and
    // makes Enter in a text field save (§4).
    await expect( save ).toHaveAttribute( 'form', 'k-profile-form' );
    await expect( save ).toHaveAttribute( 'type', 'submit' );

    // Driven rather than asserted from the attribute: the association only
    // matters if it actually submits.
    await page.getByTestId( 'profile.first_name' ).fill( 'Enter' );
    await confirmIdentity( page );
    await save.click();
    await page.waitForLoadState( 'load' );

    await expect( page.getByTestId( 'profile.status_line' ) ).toBeVisible();
    expect( await persistedValue( page, 'first_name' ) ).toBe( 'Enter' );
} );

// ─── Default state ──────────────────────────────────────────────

test( 'nothing is validated on load', async ( { page } ) => {
    await open( page );

    // §2 Default: "clean, no validation shown. Never validate on load."
    await expect( errorSummary( page ) ).toHaveCount( 0 );
    await expect( page.locator( 'main [aria-invalid="true"]' ) ).toHaveCount( 0 );
    await expect( page.getByTestId( 'profile.status_line' ) ).toHaveCount( 0 );
} );

test( 'the username is readonly and selectable, never disabled', async ( { page } ) => {
    await open( page );

    // §2 "Read-only vs disabled". `UserManager::update()` does not accept
    // `username`, so the constraint is real; the shipped screen used `disabled`,
    // which is the wrong one and also removes it from the tab order.
    const username = page.getByTestId( 'profile.username' );
    await expect( username ).toHaveAttribute( 'readonly', '' );
    await expect( username ).not.toHaveAttribute( 'disabled', /.*/ );
    await expect( username ).toHaveValue( SUBJECT );

    // It carries no `name`: a readonly control still posts, and a value nothing
    // reads is how a field starts looking editable to the next reader.
    expect( await username.getAttribute( 'name' ) ).toBeNull();
} );

test( 'every field carries a real label and a standard autocomplete token', async ( { page } ) => {
    await open( page );

    // §4: "Every control has a visible <label for>. No placeholder-as-label
    // anywhere in the admin." Checked as a property of every control on the
    // screen rather than field by field, so a new field cannot slip through.
    const unlabelled = await page.evaluate( () => {
        const controls = document.querySelectorAll( 'main input:not([type=hidden]), main select, main textarea' );
        return Array.from( controls )
            .filter( ( c ) => ! ( c.id && document.querySelector( `label[for="${ c.id }"]` ) ) )
            .map( ( c ) => c.name || c.id || c.tagName );
    } );
    expect( unlabelled, 'controls with no <label for>' ).toEqual( [] );

    for ( const [ testId, token ] of [
        [ 'profile.first_name', 'given-name' ],
        [ 'profile.last_name', 'family-name' ],
        [ 'profile.email', 'email' ],
        [ 'profile.current_password', 'current-password' ],
        [ 'profile.new_password', 'new-password' ],
    ] ) {
        await expect( page.getByTestId( testId ) ).toHaveAttribute( 'autocomplete', token );
    }
} );

test( 'the social networks are a fieldset with a legend', async ( { page } ) => {
    await open( page );

    // §4: "Grouped controls are in <fieldset><legend>". The shipped screen hid
    // them inside a collapsed <details> instead.
    const group = page.getByTestId( 'profile.social' );
    await expect( group ).toBeVisible();
    await expect( group.locator( 'legend' ) ).toHaveCount( 1 );
    await expect( group.locator( 'input[type="url"]' ) ).toHaveCount( 4 );
} );

// ─── Success ────────────────────────────────────────────────────

test( 'a valid save reports itself and persists', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'profile.first_name' ).fill( 'Renamed' );
    await page.getByTestId( 'profile.website' ).fill( 'https://example.invalid/changed' );
    await confirmIdentity( page );
    await save( page );

    // §2 Success: "a role="status" line under the H1".
    const status = page.getByTestId( 'profile.status_line' );
    await expect( status ).toBeVisible();
    await expect( status ).toHaveAttribute( 'role', 'status' );

    // A test that varies an input reads that input back (L-035), on a FRESH GET
    // rather than a reload, which would only re-submit the POST (D-088).
    expect( await persistedValue( page, 'first_name' ) ).toBe( 'Renamed' );
    expect( await persistedValue( page, 'website' ) ).toBe( 'https://example.invalid/changed' );
} );

test( 'a changed password is the one that works next time', async ( { page, browser } ) => {
    await open( page );

    const next = 'playground-profile-changed-2026';
    await confirmIdentity( page );
    await page.getByTestId( 'profile.new_password' ).fill( next );
    await save( page );
    await expect( page.getByTestId( 'profile.status_line' ) ).toBeVisible();

    // Proven by logging in with it, not by trusting the success line: the
    // shipped screen's success message was printed by the same branch whether
    // or not the password field had been touched.
    const context = await browser.newContext();
    const fresh = await context.newPage();
    await loginAs( fresh, SUBJECT, next );
    await expect( fresh.getByTestId( 'shell.account' ) ).toBeVisible();
    await context.close();
} );

// ─── Reproduction 1 — the half-saved save ───────────────────────

test( 'a rejected new password saves nothing at all', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'profile.first_name' ).fill( 'Renamed' );
    await confirmIdentity( page );
    await postShortPassword( page );

    await save( page );

    // THE FINDING, and it gets a test of its own rather than riding behind the
    // reporting assertion below: the shipped handler wrote the identity half and
    // only then failed on the password, so the error it showed was true about
    // the field it named and false about everything else.
    expect(
        await persistedValue( page, 'first_name' ),
        'the save was reported as failed and the identity half had already been written'
    ).toBe( START.first_name );
} );

test( 'a rejected new password says so', async ( { page } ) => {
    await open( page );

    await confirmIdentity( page );
    await postShortPassword( page );
    await save( page );

    await expect(
        errorSummary( page ),
        'a rejected save reported nothing a screen reader could reach'
    ).toBeVisible();
    await expect( page.getByTestId( 'profile.error.new_password' ) ).toBeVisible();
    await expect( page.getByTestId( 'profile.new_password' ) ).toHaveAttribute( 'aria-invalid', 'true' );
} );

// ─── Reproduction 2 — the silent CSRF refusal ───────────────────

test( 'a refused CSRF token is reported, not swallowed', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'profile.first_name' ).fill( 'Renamed' );
    await confirmIdentity( page );

    // Tamper with the token the way an expired session does — same field, a
    // value the server will not accept.
    await page.evaluate( () => {
        const field = document.querySelector( 'main input[name="csrf"], main input[name="csrf_token"]' );
        if ( ! field ) {
            throw new Error( 'no CSRF field inside main — the form does not carry one' );
        }
        field.value = 'not-the-token';
    } );

    await save( page );

    await expect(
        errorSummary( page ),
        'the post was refused and the screen said nothing — the edits vanished silently'
    ).toBeVisible();

    expect(
        await persistedValue( page, 'first_name' ),
        'a refused post wrote anyway'
    ).toBe( START.first_name );
} );

// ─── Field-level errors ─────────────────────────────────────────

test( 'the wrong current password is a field error, and nothing is written', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'profile.first_name' ).fill( 'Renamed' );
    await confirmIdentity( page, 'not-the-password' );
    await save( page );

    const field = page.getByTestId( 'profile.current_password' );
    await expect( field ).toHaveAttribute( 'aria-invalid', 'true' );
    await expect( page.getByTestId( 'profile.error.current_password' ) ).toBeVisible();

    expect( await persistedValue( page, 'first_name' ) ).toBe( START.first_name );
} );

test( 'the error is described AFTER the hint, and carries an icon', async ( { page } ) => {
    await open( page );

    await confirmIdentity( page, 'not-the-password' );
    await save( page );

    // §4: "Hints and errors are both in aria-describedby, hint first."
    const described = await page.getByTestId( 'profile.current_password' ).getAttribute( 'aria-describedby' );
    expect( described ).toBe( 'profile-hint-current_password profile-error-current_password' );

    // §1.3: colour is never the only channel — the error carries its icon.
    await expect( page.getByTestId( 'profile.error.current_password' ).locator( 'svg' ) ).toHaveCount( 1 );
} );

test( 'an email another account holds is refused, and named as such', async ( { page } ) => {
    await open( page );

    // `owner` is a seeded account, so this is a real collision rather than a
    // contrived one, and the manager would throw on it — the screen catches it
    // first so the message points at the control that has to change (§2).
    await page.getByTestId( 'profile.email' ).fill( 'owner@playground.test' );
    await confirmIdentity( page );
    await save( page );

    await expect( page.getByTestId( 'profile.error.email' ) ).toBeVisible();
    expect( await persistedValue( page, 'email' ) ).toBe( START.email );
} );

test( 'a javascript: URL is refused rather than stored', async ( { page } ) => {
    await open( page );

    // This value reaches the PUBLISHED site, so refusing it is a security
    // property and not a nicety. The shipped screen stored whatever arrived —
    // there was no URL validation of any kind in the handler or the manager.
    await page.evaluate( () => {
        document.querySelector( 'main [name="website"]' ).setAttribute( 'type', 'text' );
    } );
    await page.getByTestId( 'profile.website' ).fill( 'javascript:alert(1)' );
    await confirmIdentity( page );
    await save( page );

    await expect( page.getByTestId( 'profile.error.website' ) ).toBeVisible();
    expect( await persistedValue( page, 'website' ) ).toBe( START.website );
} );

test( 'the error summary is an alert, takes focus and links to its fields', async ( { page } ) => {
    await open( page );

    // The confirmation is filled even though this test is about the email:
    // `current_password` is `required`, so leaving it empty makes CHROMIUM
    // refuse the submission and the test measures constraint validation instead
    // of the handler. That is L-042 for the third time in this one spec, and it
    // is worth the comment: every wrong red here came from a layer in front of
    // the one under test.
    await confirmIdentity( page );
    await page.getByTestId( 'profile.email' ).fill( '' );
    await page.evaluate( () => {
        document.querySelector( 'main [name="email"]' ).removeAttribute( 'required' );
    } );
    await save( page );

    // §2 Error — form level: role="alert", focus moved to it, every failed
    // field a link to that field.
    const summary = page.getByTestId( 'profile.error_summary' );
    await expect( summary ).toBeVisible();
    await expect( summary ).toHaveAttribute( 'tabindex', '-1' );

    const links = summary.locator( 'a' );
    expect( await links.count() ).toBeGreaterThan( 0 );

    // The link resolves to a control that exists — a summary pointing at a
    // fragment nothing owns is the shape that makes an error summary useless.
    for ( const href of await links.evaluateAll( ( els ) => els.map( ( e ) => e.getAttribute( 'href' ) ) ) ) {
        await expect( page.locator( href ) ).toHaveCount( 1 );
    }
} );

// ─── Preferences ────────────────────────────────────────────────

test( 'the theme control is a switch that takes effect immediately', async ( { page } ) => {
    await open( page, 'dark' );

    // §27's delta: each preference "takes effect immediately", and §4 defines
    // that as role="switch". Scoped to the card: the SHELL carries its own theme
    // control, and an unscoped locator drives that one instead (L-042).
    const control = page.getByTestId( 'profile.theme_switch' );
    await expect( control ).toHaveAttribute( 'role', 'switch' );
    await expect( control ).toHaveAttribute( 'aria-checked', 'true' );

    await Promise.all( [ page.waitForLoadState( 'load' ), control.click() ] );

    expect(
        await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
        'the switch reported success and the page did not change theme'
    ).toBe( 'light' );
    await expect( page.getByTestId( 'profile.theme_switch' ) ).toHaveAttribute( 'aria-checked', 'false' );

    // It survives a fresh GET, which is what "preference" means here.
    await page.goto( URL_PATH );
    expect( await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ) ).toBe( 'light' );
} );

// ─── With JavaScript disabled ───────────────────────────────────

test.describe( 'Entry 27 with no JavaScript', () => {
    test.use( { javaScriptEnabled: false } );

    test( 'the form saves and the theme switch still switches', async ( { page } ) => {
        // Nothing on this screen is an enhancement: the Save is a form post and
        // the switch is a submit button. The shipped screen's only script was
        // the avatar preview, which the CSP blocked anyway.
        await page.goto( URL_PATH );

        await page.locator( 'main [name="first_name"]' ).fill( 'NoScript' );
        await page.locator( 'main [name="current_password"]' ).fill( SUBJECT_PASSWORD );
        await Promise.all( [
            page.waitForLoadState( 'load' ),
            page.getByTestId( 'profile.save' ).click(),
        ] );

        await expect( page.getByTestId( 'profile.status_line' ) ).toBeVisible();
        expect( await persistedValue( page, 'first_name' ) ).toBe( 'NoScript' );

        await Promise.all( [
            page.waitForLoadState( 'load' ),
            page.getByTestId( 'profile.theme_switch' ).click(),
        ] );
        expect( await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ) ).toBe( 'light' );
    } );
} );

// ─── Accessibility, per state and per theme ─────────────────────

for ( const theme of [ 'dark', 'light' ] ) {
    test( `at rest the whole page is clean at WCAG 2.2 AA — ${ theme }`, async ( { page } ) => {
        await open( page, theme );
        expect( violations( await scan( page ) ) ).toEqual( [] );
    } );

    test( `the error state is clean at WCAG 2.2 AA — ${ theme }`, async ( { page } ) => {
        await open( page, theme );
        await confirmIdentity( page, 'not-the-password' );
        await postShortPassword( page );
        await save( page );

        // Every state, not only the resting ones (D-091): this one renders the
        // summary, two field errors and their icons, none of which the resting
        // scan can reach.
        await expect( page.getByTestId( 'profile.error_summary' ) ).toBeVisible();
        expect( violations( await scan( page ) ) ).toEqual( [] );
    } );

    test( `the saved state is clean at WCAG 2.2 AA — ${ theme }`, async ( { page } ) => {
        await open( page, theme );
        await confirmIdentity( page );
        await save( page );

        await expect( page.getByTestId( 'profile.status_line' ) ).toBeVisible();
        expect( violations( await scan( page ) ) ).toEqual( [] );
    } );
}

// ─── Every control on the screen has a programmatic label ───────

test( 'every control on the screen has a programmatic label', async ( { page } ) => {
    await open( page );
    expect(
        violations( await scan( page ) ),
        'the whole page must be clean at WCAG 2.2 AA'
    ).toEqual( [] );
} );
