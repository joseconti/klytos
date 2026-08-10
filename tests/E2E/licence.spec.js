// Manifest entry 28 — Licence — driven per card and per STATE, in both themes.
//
// THE THREE REPRODUCTIONS, written and seen failing against the SHIPPED markup
// before a line of the rewrite existed:
//
//   1. EVERY LABEL ON THE SCREEN WAS A LITERAL CATALOGUE KEY. The screen called
//      `license.title`, `license.status`, `license.key`, `license.plan` and ten
//      more, while the catalogue's root was `plugin_license` — a root nothing in
//      the tree referenced. A missing key renders as the key itself
//      (`core/i18n.php::get()`), so this screen has read `license.title` on
//      every install, in all 20 languages, since it shipped.
//        red observed: `TITLE license.title — Klytos Admin`, and main's text was
//        `license.title / license.status / license.status / license.inactive /
//        license.plan / license.domain / license.activated_on / license.last_check
//        / license.key / license.activate / license.key / license.activate`.
//   2. A REJECTED CSRF TOKEN MUST SAY SO. `if ( POST && klytos_verify_csrf() )`
//      has no else, so a refused post made the whole handler vanish and the page
//      re-rendered as if nothing had been sent — the person's key gone and the
//      screen silent. L-041's family, and the identical defect entry 27 found.
//        red observed: `locator('main [role="alert"]:not(#k-live-alert)') —
//        element(s) not found`.
//   3. AN EMPTY KEY MUST BE REFUSED BY THE SERVER, IN A SENTENCE A CATALOGUE CAN
//      REACH. The shipped handler built its message by concatenation —
//      `__( 'license.key' ) . ' is required.'` — so no language but English
//      could word it, and the key it concatenated did not resolve either.
//        red observed: `Expected: /Enter a licence key/ · Received:
//        "license.key is required."`.
//
// And two findings the READ-BACK of the manager found rather than a test, both
// recorded rather than fixed here because each is product scope (D-101):
// `License::checkIfDue()` and `License::isActive()` have no caller anywhere in
// the tree, so the seven-day automatic re-check never runs and the licence gates
// no feature. The screen is worded to match what is true, and this spec pins
// that wording — a hint promising an automatic check would be a claim about a
// control that does not exist.
//
// Rules carried forward, each already paid for:
//   - axe scans the WHOLE PAGE, never `#main` (L-037).
//   - exclusions go in ONE AT A TIME: an array is read as a FRAME PATH (L-037).
//   - every STATE is scanned, not only the resting ones (D-091).
//   - the theme is baked in BEFORE first paint, and read back (L-035).
//   - controls are scoped to the CARD or to `main`, never `.first()` (L-042).
//   - `required` is absent from the key field on purpose: the browser's own
//     constraint validation refuses a submit before a request exists, which
//     would put the refusal in Chromium instead of in the handler that owns it
//     (L-042, paid for three times on entry 27).

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const URL_PATH = '/installer/admin/license.php';
const DASHBOARD = '/installer/admin/';
const FIXTURE = path.join( __dirname, 'fixtures', 'reset-licence.php' );
const REPO_ROOT = path.join( __dirname, '..', '..' );

/** The key `reset-licence.php` stores — mirrored, never re-invented. */
const KEY = 'e2e-not-a-real-licence-key-0000000000000000000000000000';

/** Put the licence in one of the four states the screen is specified in terms of. */
function licence( state ) {
    execFileSync( 'php', [ FIXTURE, `--${ state }` ], { cwd: REPO_ROOT } );
}

test.beforeEach( async ( { page } ) => {
    // The owner is the seeded role that holds `site.configure`, which is what
    // `admin-gate.php` requires for this screen.
    await login( page, 'owner' );
} );

test.afterAll( () => {
    // Leave the playground as the other specs expect to find it.
    licence( 'none' );
} );

/** Open the screen with the theme baked in BEFORE first paint (L-035). */
async function open( page, theme = 'dark' ) {
    await page.context().addCookies( [ {
        name: 'klytos_admin_theme',
        value: theme,
        url: new URL( page.url() ).origin,
    } ] );
    await page.goto( URL_PATH );
    await expect( page.getByTestId( 'licence.screen' ) ).toBeVisible();

    expect(
        await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
        'the theme cookie did not take — this run measured the wrong theme'
    ).toBe( theme );
}

/**
 * The screen's own error region.
 *
 * Scoped away from `#k-live-alert`, the SHELL's empty live region, which sits
 * inside `main` on every screen: an unscoped `getByRole('alert')` resolves to it
 * and reports "hidden" instead of "the screen said nothing" — a red about the
 * wrong layer (L-042).
 */
function errorSummary( page ) {
    return page.locator( 'main [role="alert"]:not(#k-live-alert)' );
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

// ─── Reproduction 1 — the catalogue root ────────────────────────

test( 'no catalogue key reaches the screen, in any state', async ( { page } ) => {
    for ( const state of [ 'none', 'valid', 'expired', 'revoked' ] ) {
        licence( state );
        await open( page );

        // The H1 and the document title both came from `license.title`, and both
        // printed it verbatim for as long as this screen has existed.
        await expect( page.locator( 'main h1' ) ).toHaveText( 'Licence' );
        expect( await page.title(), `state ${ state }` ).toBe( 'Licence — Klytos Admin' );

        // Nothing anywhere in `main` may look like `root.key`. This is broader
        // than the thirteen keys the shipped screen used on purpose: the defect
        // is a class, and the next unresolved key on this screen must fail here
        // rather than be noticed by a person reading a rendered page.
        const text = await page.locator( 'main' ).innerText();
        expect( text, `state ${ state }` ).not.toMatch( /\blicense\.[a-z_]+/ );
        expect( text, `state ${ state }` ).not.toMatch( /\bcommon\.[a-z_]+/ );
    }
} );

// ─── Structure ──────────────────────────────────────────────────

test( 'the screen renders the record-form template with no section nav', async ( { page } ) => {
    licence( 'valid' );
    await open( page );

    // §28 lists cards, not sections, so the template's optional left column is
    // ABSENT from the DOM rather than rendered empty.
    await expect( page.locator( '.k-record-form--no-nav' ) ).toHaveCount( 1 );
    await expect( page.getByTestId( 'licence.screen' ).locator( '.k-section-nav' ) ).toHaveCount( 0 );

    await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );

    const headings = page.getByTestId( 'licence.screen' ).locator( 'h2' );
    await expect( headings ).toHaveCount( 2 );
    await expect( headings.nth( 0 ) ).toHaveAttribute( 'id', 'licence-plan-heading' );
    await expect( headings.nth( 1 ) ).toHaveAttribute( 'id', 'licence-key-heading' );
} );

test( 'the two unbacked cards are absent, and absent on purpose', async ( { page } ) => {
    licence( 'valid' );
    await open( page );

    // §28 names four cards and this build renders two. Activated domains has no
    // collection behind it — the record holds ONE domain — and Entitlements has
    // no entitlement record of any kind (D-101, roadmap §0c). Pinned so that
    // "deferred" cannot quietly become "forgotten", and so that building either
    // later has to delete this assertion deliberately.
    await expect( page.getByTestId( 'licence.screen' ).locator( '.k-table' ) ).toHaveCount( 0 );
    await expect( page.getByTestId( 'licence.screen' ).locator( '.k-stat-row' ) ).toHaveCount( 0 );
} );

test( 'the primary action is in the toolbar and Enter in the key field submits', async ( { page } ) => {
    licence( 'none' );
    await open( page );

    const activate = page.getByTestId( 'licence.activate' );
    await expect( activate ).toBeVisible();
    await expect( activate ).toHaveAttribute( 'form', 'k-licence-activate' );

    // A toolbar button associated by `form=` is also that form's implicit
    // submit, which is what §4 asks for: Enter in a text field saves. No script
    // is involved, so this is asserted by pressing Enter and watching a real
    // navigation — not by reading the attribute twice.
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'licence.key_field' ).press( 'Enter' ),
    ] );

    // The server refused it, which proves the post left the browser: an empty
    // field with `required` would never have got this far (L-042).
    await expect( errorSummary( page ) ).toBeVisible();
} );

// ─── Empty state ────────────────────────────────────────────────

test( 'with no licence the Plan card carries a sentence and an action, never a zero', async ( { page } ) => {
    licence( 'none' );
    await open( page );

    await expect( page.getByTestId( 'licence.plan_empty' ) ).toBeVisible();
    await expect( page.getByTestId( 'licence.plan_empty_action' ) ).toHaveAttribute(
        'href',
        '#licence-field-license_key'
    );

    // §2 Empty: the facts are not drawn as zeros or dashes-with-no-meaning; the
    // whole list is absent and the sentence stands in its place.
    await expect( page.getByTestId( 'licence.facts' ) ).toHaveCount( 0 );
    await expect( page.getByTestId( 'licence.key_none' ) ).toBeVisible();

    // Nothing to check, so no check trigger — rather than a disabled one.
    await expect( page.getByTestId( 'licence.check_now' ) ).toHaveCount( 0 );
    await expect( page.getByTestId( 'licence.copy_key' ) ).toHaveCount( 0 );
} );

// ─── The stored key ─────────────────────────────────────────────

test( 'the stored key is readonly, mono, whole, and does not post', async ( { page } ) => {
    licence( 'valid' );
    await open( page );

    const field = page.getByTestId( 'licence.stored_key' );
    await expect( field ).toBeVisible();

    // §2 "Read-only vs disabled": readonly, never disabled — a disabled control
    // cannot be focused, and this one must be selectable.
    await expect( field ).toHaveAttribute( 'readonly', '' );
    expect( await field.getAttribute( 'disabled' ) ).toBeNull();

    // IN FULL. The shipped screen printed `abcdefgh...12345678`, which makes the
    // copy button §28 asks for pointless.
    await expect( field ).toHaveValue( KEY );

    // It carries no `name`: a readonly control still posts, and posting a value
    // nothing reads is how a field starts looking editable to the next reader.
    expect( await field.getAttribute( 'name' ) ).toBeNull();

    await expect( field ).toHaveClass( /k-control--mono/ );
    await expect( page.getByTestId( 'licence.copy_key' ) ).toBeVisible();
} );

test( 'the facts read from the record, with dates as machine-readable time', async ( { page } ) => {
    licence( 'valid' );
    await open( page );

    await expect( page.getByTestId( 'licence.status' ) ).toHaveText( 'Active' );
    await expect( page.getByTestId( 'licence.plan' ) ).toHaveText( 'pro' );

    // The shipped screen printed both dates with bare `date()`, so every
    // timestamp rendered in the SERVER's timezone rather than the site's. The
    // `datetime` attribute carries the stored UTC value and the text carries the
    // local rendering, which is the only shape that is true in both places.
    const activated = page.getByTestId( 'licence.activated_on' );
    await expect( activated ).toHaveAttribute( 'datetime', /^\d{4}-\d{2}-\d{2}T/ );
    await expect( activated ).toHaveText( /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/ );
} );

// ─── The degraded delta, and the status bar's one fact ──────────

test( 'an expired licence degrades this screen and puts one fact on the status bar', async ( { page } ) => {
    licence( 'expired' );
    await open( page );

    await expect( page.getByTestId( 'licence.status' ) ).toHaveText( 'Expired' );
    await expect( page.getByTestId( 'licence.degraded' ) ).toBeVisible();

    // §28: "the admin keeps working". The rest of the screen is still there.
    await expect( page.getByTestId( 'licence.check_now' ) ).toBeVisible();
    await expect( page.getByTestId( 'licence.key_field' ) ).toBeEnabled();

    // The one fact, and it is on EVERY page — asserted on a different screen,
    // because a status bar that only appears on the licence screen carries the
    // fact to the one person who is already looking at it.
    const bar = page.locator( '.k-statusbar-degraded' );
    await expect( bar ).toHaveText( 'Licence expired' );

    await page.goto( DASHBOARD );
    await expect( page.locator( '.k-statusbar-degraded' ) ).toHaveText( 'Licence expired' );
    await expect( page.locator( '.k-statusbar-degraded a' ) ).toHaveAttribute( 'href', /license\.php$/ );
} );

test( 'a revoked licence names its grace period; a valid one puts nothing on the bar', async ( { page } ) => {
    licence( 'revoked' );
    await open( page );

    await expect( page.getByTestId( 'licence.status' ) ).toHaveText( 'Revoked' );
    await expect( page.getByTestId( 'licence.grace_period' ) ).toBeVisible();
    await expect( page.locator( '.k-statusbar-degraded' ) ).toHaveText( 'Licence revoked' );

    licence( 'valid' );
    await page.goto( DASHBOARD );
    // A filter that returns its input unchanged is what keeps a filter a filter:
    // a healthy licence adds nothing at all, rather than an empty element.
    await expect( page.locator( '.k-statusbar-degraded' ) ).toHaveCount( 0 );
} );

// ─── Reproduction 2 — a refused post says so ────────────────────

test( 'a refused CSRF post reports it instead of rendering as if nothing was sent', async ( { page } ) => {
    licence( 'none' );
    await open( page );

    await page.evaluate( () => {
        document.querySelector( '#k-licence-activate [name="csrf"]' ).value = 'not-the-token';
    } );
    await page.getByTestId( 'licence.key_field' ).fill( KEY );

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'licence.activate' ).click(),
    ] );

    const summary = errorSummary( page );
    await expect( summary ).toBeVisible();
    await expect( summary ).toContainText( 'expired before it was sent' );

    // And it really did not activate — the refusal is honest in both directions.
    await expect( page.getByTestId( 'licence.key_none' ) ).toBeVisible();
} );

// ─── Reproduction 3 — the server refuses an empty key, in a sentence ──

test( 'an empty key is refused by the SERVER, with a field error and a summary link', async ( { page } ) => {
    licence( 'none' );
    await open( page );

    // No `required` on the field, so the browser does not intercept: this post
    // reaches the handler, which is the layer the finding is about.
    expect( await page.getByTestId( 'licence.key_field' ).getAttribute( 'required' ) ).toBeNull();

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'licence.activate' ).click(),
    ] );

    const field = page.getByTestId( 'licence.key_field' );
    await expect( field ).toHaveAttribute( 'aria-invalid', 'true' );

    const error = page.getByTestId( 'licence.error.license_key' );
    await expect( error ).toHaveText( /Enter a licence key/ );
    // §1.3: colour is never the only channel — the icon is beside the message.
    await expect( error.locator( 'svg' ) ).toHaveCount( 1 );

    // §2 Error — form level: every failed field is a LINK to that field.
    await expect( page.getByTestId( 'licence.error_link.0' ) ).toHaveAttribute(
        'href',
        '#licence-field-license_key'
    );

    // The hint comes FIRST in `aria-describedby`, the error second (§4).
    await expect( field ).toHaveAttribute(
        'aria-describedby',
        'licence-hint-license_key licence-error-license_key'
    );
} );

// ─── The wording matches what the product actually does ─────────

test( 'the check hint claims a manual check, because nothing checks automatically', async ( { page } ) => {
    licence( 'valid' );
    await open( page );

    // `License::checkIfDue()` has no caller in the tree, so the seven-day
    // re-verification the manager implements never runs. A hint promising an
    // automatic check would be a claim about a control that does not exist, and
    // this assertion is what stops a later edit from adding one back (D-101).
    const hint = page.locator( '#licence-hint-check' );
    await expect( hint ).toContainText( 'Nothing checks it on its own' );
    await expect( hint ).not.toContainText( /automatic/i );
} );

// ─── With JavaScript disabled ───────────────────────────────────

test.describe( 'with JavaScript disabled', () => {
    test.use( { javaScriptEnabled: false } );

    test( 'every control works except Copy, which is the only enhancement', async ( { page } ) => {
        licence( 'valid' );
        await page.goto( URL_PATH );
        await expect( page.getByTestId( 'licence.screen' ) ).toBeVisible();

        // The facts, the readonly key and both form posts are server-rendered.
        await expect( page.getByTestId( 'licence.stored_key' ) ).toHaveValue( KEY );
        await expect( page.getByTestId( 'licence.check_now' ) ).toBeVisible();
        await expect( page.getByTestId( 'licence.activate' ) ).toBeVisible();

        // Copy is still drawn and still does nothing without script — which is
        // why the value beside it is selectable and complete rather than masked.
        await expect( page.getByTestId( 'licence.copy_key' ) ).toBeVisible();
    } );
} );

// ─── Accessibility: every state, both themes ────────────────────

for ( const theme of [ 'dark', 'light' ] ) {
    for ( const state of [ 'none', 'valid', 'expired', 'revoked' ] ) {
        test( `axe — ${ state }, ${ theme }`, async ( { page } ) => {
            licence( state );
            await open( page, theme );

            const results = await scan( page );
            expect( violations( results ), `${ state } / ${ theme }` ).toEqual( [] );
        } );
    }
}

test( 'axe — the error state, which no resting scan reaches', async ( { page } ) => {
    licence( 'none' );
    await open( page );

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'licence.activate' ).click(),
    ] );
    await expect( errorSummary( page ) ).toBeVisible();

    // D-091: scan every STATE, not only the resting ones. The error summary, the
    // field's error colour and its icon exist in no other scan on this screen.
    const results = await scan( page );
    expect( violations( results ) ).toEqual( [] );
} );
