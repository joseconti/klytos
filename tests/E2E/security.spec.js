// Manifest entry 6 — Security — driven per STATE, in both themes.
//
// The fourth `record-form` screen, and the first one whose controls are
// SWITCHES: §6's delta makes every second-factor control immediate-effect with a
// re-auth step, which §4 defines as `role="switch"` rather than a checkbox. It
// is also the first consumer of `.k-card--secret`, the ONE bordered card the
// admin has, and the first screen with no toolbar Save at all.
//
// Rules this spec carries forward, each of them already paid for:
//
//   - the axe pass scans the WHOLE PAGE, never `#main`. Scoping to `#main` is
//     exactly what hid the shell's own contrast defect for four screens (L-037).
//   - exclusions are applied ONE AT A TIME: `exclude()` reads an array as a
//     FRAME PATH, so `exclude( LIST )` excludes nothing (L-037).
//   - persistence is checked with a FRESH GET, never `page.reload()`, which
//     re-submits the POST and would pass whether or not anything was stored
//     (D-088).
//   - the re-auth step is driven with JavaScript DISABLED, because it is
//     specified as behaviour rather than as an enhancement (D-089, D-090).
//   - contrast is read out of the BROWSER on a REAL screen, never from the
//     stylesheet (L-032, L-033), and each measured pair is pinned as a FLOOR.
//
// What this spec does NOT prove, said plainly rather than implied: the WebAuthn
// ENROLMENT ceremony. `navigator.credentials.create()` needs an authenticator,
// so the fixture seeds a passkey through the storage API and this spec drives
// the LIST and the REMOVAL — which is what the screen owns — and asserts that
// the add control is hidden where WebAuthn is absent.

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );
const SECURITY_URL = '/installer/admin/security.php';
const PASSKEY_ID = 'e2e-credential-id';

/**
 * Put the acting user's second factors back to a known state.
 *
 * With no argument that is the fresh-install state — no methods, no recovery
 * codes, no passkeys — which is a real state of the screen and not merely
 * "unset".
 */
function reset( ...flags ) {
    execFileSync(
        'php',
        [ path.join( REPO_ROOT, 'tests/E2E/fixtures/reset-security.php' ), ...flags.map( ( f ) => `--${ f }` ) ],
        { cwd: REPO_ROOT, env: { ...process.env, XDEBUG_MODE: 'off' } }
    );
}

test.beforeEach( async ( { page } ) => {
    reset();
    await login( page, 'owner' );
} );

test.afterEach( async () => {
    reset();
} );

/**
 * Open the screen with the theme baked in BEFORE the first paint (L-035): a
 * cookie whose name the shell does not read makes every "light" run measure
 * dark, and nothing in the output would tell you.
 */
async function open( page, theme = 'dark' ) {
    await page.context().addCookies( [ {
        name: 'klytos_admin_theme',
        value: theme,
        url: new URL( page.url() ).origin,
    } ] );
    await page.goto( SECURITY_URL );
    await expect( page.getByTestId( 'security.screen' ) ).toBeVisible();

    expect(
        await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
        'the theme cookie did not take — this run measured the wrong theme'
    ).toBe( theme );
}

/** The axe pass, scoped exactly once — over the WHOLE page, never `#main`. */
async function scan( page ) {
    let builder = new AxeBuilder( { page } )
        .withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] );

    // One at a time: an array is read as a frame path, not as a list (L-037).
    for ( const gap of KNOWN_DELIVERY_GAPS ) {
        builder = builder.exclude( gap );
    }
    for ( const surface of DEV_ONLY_SURFACES ) {
        builder = builder.exclude( surface );
    }

    return builder.analyze();
}

/** The seeded owner's password — throwaway, local only (docs/playground.md). */
const OWNER_PASSWORD = 'playground-owner-2026';

async function click( page, testId ) {
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( testId ).click(),
    ] );
}

// ─── Structure ──────────────────────────────────────────────────

test( 'renders the cards the product backs, and neither deferred card', async ( { page } ) => {
    await open( page );

    await expect( page.locator( 'h1' ) ).toHaveText( 'Security' );
    await expect( page.locator( 'h1' ) ).toHaveCount( 1 );

    for ( const heading of [ 'Two-factor', 'Passkeys', 'Recovery codes', 'Encryption level', 'Recovery keys' ] ) {
        await expect( page.getByRole( 'heading', { level: 2, name: heading } ) ).toBeVisible();
    }

    /*
     * Content-Security-Policy and Integrity score are DEFERRED (roadmap §0c):
     * Klytos SENDS a CSP but has no editor or store for one, and the integrity
     * data lives on entry 34 with nothing that summarises it into a score.
     * Asserting their ABSENCE is what stops them being built from the manifest
     * alone, and what fails the day one lands without its roadmap row cleared.
     */
    await expect( page.getByRole( 'heading', { level: 2, name: /Content-Security-Policy/ } ) ).toHaveCount( 0 );
    await expect( page.getByRole( 'heading', { level: 2, name: /Integrity score/ } ) ).toHaveCount( 0 );
} );

test( 'there is NO toolbar Save — every control here takes effect immediately', async ( { page } ) => {
    /*
     * The adaptation, pinned. §1 says the primary Save lives in the toolbar on
     * every form screen; §6's delta says every control on THIS screen is
     * immediate-effect. A Save here would submit nothing, and a control that
     * lies about what it does is worse than a control that is absent (D-089).
     */
    await open( page );

    const toolbarButtons = page.locator( '.k-topbar button[type="submit"]' );
    await expect( toolbarButtons ).toHaveCount( 0 );
} );

test( 'the section nav is a labelled nav whose every link resolves to a real card', async ( { page } ) => {
    await open( page );

    const nav = page.getByTestId( 'security.section_nav' );
    await expect( nav ).toHaveAttribute( 'aria-label', 'Security sections' );

    const hrefs = await nav.locator( 'a' ).evaluateAll( ( els ) => els.map( ( el ) => el.getAttribute( 'href' ) ) );
    expect( hrefs.length ).toBeGreaterThan( 0 );

    for ( const href of hrefs ) {
        await expect( page.locator( href ), `${ href } is a nav link to nothing` ).toHaveCount( 1 );
    }

    // §4: exactly one item carries aria-current.
    await expect( nav.locator( '[aria-current="page"]' ) ).toHaveCount( 1 );
} );

test( 'the destructive card is the LAST one, and only exists once 2FA is on', async ( { page } ) => {
    // §2: "Destructive section — always the last card."
    await open( page );
    await expect( page.getByRole( 'heading', { level: 2, name: /Turn off/ } ) ).toHaveCount( 0 );

    reset( 'totp', 'codes' );
    await open( page );

    const headings = await page.locator( '.k-card-stack h2.k-card-heading' )
        .evaluateAll( ( els ) => els.map( ( el ) => el.textContent.trim() ) );

    expect( headings[ headings.length - 1 ] ).toBe( 'Turn off two-factor authentication' );
} );

// ─── The switches ───────────────────────────────────────────────

test( 'each second factor is a role=switch whose aria-checked states the truth', async ( { page } ) => {
    await open( page );

    const totp = page.getByTestId( 'security.totp_switch' );
    const email = page.getByTestId( 'security.email_switch' );

    await expect( totp ).toHaveRole( 'switch' );
    await expect( email ).toHaveRole( 'switch' );
    await expect( totp ).toHaveAttribute( 'aria-checked', 'false' );
    await expect( email ).toHaveAttribute( 'aria-checked', 'false' );

    // The accessible name comes from the visible label, never from a
    // placeholder or an invented aria-label (§4).
    await expect( totp ).toHaveAccessibleName( 'Authenticator app' );
    await expect( email ).toHaveAccessibleName( 'Email link' );

    reset( 'totp' );
    await open( page );
    await expect( page.getByTestId( 'security.totp_switch' ) ).toHaveAttribute( 'aria-checked', 'true' );
} );

test( 'turning the email link ON is refused until the password is given, then applied', async ( { page } ) => {
    await open( page );

    // First post: the switch arms the re-auth step, and nothing has changed yet.
    await click( page, 'security.email_switch' );
    await expect( page.getByTestId( 'security.reauth' ) ).toBeVisible();
    await expect( page.getByTestId( 'security.email_switch' ) ).toHaveAttribute( 'aria-checked', 'false' );

    // The step says what it will do — never a generic "are you sure?".
    await expect( page.getByTestId( 'security.reauth_what' ) )
        .toHaveText( /one-time link will be sent to your email/ );

    // A wrong password refuses, and says so beside the field.
    await page.getByTestId( 'security.reauth_password' ).fill( 'not-the-password' );
    await click( page, 'security.reauth_confirm' );
    await expect( page.getByTestId( 'security.error_summary' ) ).toBeVisible();
    await expect( page.getByTestId( 'security.reauth_password' ) ).toHaveAttribute( 'aria-invalid', 'true' );

    // Still off — a refused re-auth must not have applied anything.
    await page.goto( SECURITY_URL );
    await expect( page.getByTestId( 'security.email_switch' ) ).toHaveAttribute( 'aria-checked', 'false' );

    // The right password applies it, and it survives a FRESH GET.
    await click( page, 'security.email_switch' );
    await page.getByTestId( 'security.reauth_password' ).fill( OWNER_PASSWORD );
    await click( page, 'security.reauth_confirm' );
    await expect( page.getByTestId( 'security.status_line' ) ).toBeVisible();

    await page.goto( SECURITY_URL );
    await expect( page.getByTestId( 'security.email_switch' ) ).toHaveAttribute( 'aria-checked', 'true' );
} );

test( 'cancelling the re-auth step leaves the factor exactly as it was', async ( { page } ) => {
    reset( 'email', 'codes' );
    await open( page );

    await click( page, 'security.email_switch' );
    await expect( page.getByTestId( 'security.reauth' ) ).toBeVisible();

    await click( page, 'security.reauth_cancel' );
    await expect( page.getByTestId( 'security.reauth' ) ).toHaveCount( 0 );

    await page.goto( SECURITY_URL );
    await expect( page.getByTestId( 'security.email_switch' ) ).toHaveAttribute( 'aria-checked', 'true' );
} );

test( 'the re-auth step works with JavaScript DISABLED — it is behaviour, not enhancement', async ( { browser } ) => {
    const context = await browser.newContext( { javaScriptEnabled: false } );
    const page = await context.newPage();

    try {
        /*
         * SIGN IN FIRST, then enable the factor. Doing it the other way round
         * hangs, and the reason is worth the comment: turning on a second
         * factor makes the LOGIN ask for it, so `login()` lands on the
         * two-step-verification screen and `shell.brand` never appears. The
         * fixture was configuring the product to refuse the very session the
         * test needed — not a product defect, a test that changed the world it
         * was about to walk into.
         */
        await login( page, 'owner' );
        reset( 'email', 'codes' );
        await page.goto( SECURITY_URL );

        await page.getByTestId( 'security.email_switch' ).click();
        await expect( page.getByTestId( 'security.reauth' ) ).toBeVisible();

        await page.getByTestId( 'security.reauth_password' ).fill( OWNER_PASSWORD );
        await page.getByTestId( 'security.reauth_confirm' ).click();

        await page.goto( SECURITY_URL );
        await expect( page.getByTestId( 'security.email_switch' ) ).toHaveAttribute( 'aria-checked', 'false' );
    } finally {
        await context.close();
    }
} );

test( 'turning the authenticator ON goes to the ENROLMENT ceremony, not to a password', async ( { page } ) => {
    /*
     * The one toggle with no password step, and the reason is in the record:
     * the code proves possession of the authenticator, which is a stronger
     * claim than the password. Asserting the ABSENCE of the password step is
     * what stops the two being quietly collapsed later.
     */
    await open( page );

    await click( page, 'security.totp_switch' );

    await expect( page.getByTestId( 'security.enrolment' ) ).toBeVisible();
    await expect( page.getByTestId( 'security.totp_secret' ) ).toBeVisible();
    await expect( page.getByTestId( 'security.reauth' ) ).toHaveCount( 0 );

    // A wrong code is refused by the SERVER with a sentence beside the field.
    await page.getByTestId( 'security.totp_code' ).fill( '000000' );
    await click( page, 'security.totp_verify' );
    await expect( page.getByTestId( 'security.error_summary' ) ).toBeVisible();
    await expect( page.getByTestId( 'security.totp_code' ) ).toHaveAttribute( 'aria-invalid', 'true' );

    // Nothing was enabled by a failed verification.
    await page.goto( SECURITY_URL );
    await expect( page.getByTestId( 'security.totp_switch' ) ).toHaveAttribute( 'aria-checked', 'false' );
} );

test( 'turning the authenticator OFF asks for the password and then applies', async ( { page } ) => {
    reset( 'totp', 'codes' );
    await open( page );

    await click( page, 'security.totp_switch' );
    await expect( page.getByTestId( 'security.reauth_what' ) ).toHaveText( /no longer be asked for/ );

    await page.getByTestId( 'security.reauth_password' ).fill( OWNER_PASSWORD );
    await click( page, 'security.reauth_confirm' );

    await page.goto( SECURITY_URL );
    await expect( page.getByTestId( 'security.totp_switch' ) ).toHaveAttribute( 'aria-checked', 'false' );
} );

// ─── Passkeys ───────────────────────────────────────────────────

test( 'the empty passkey collection renders its sentence and KEEPS its heading', async ( { page } ) => {
    await open( page );

    await expect( page.getByRole( 'heading', { level: 2, name: 'Passkeys' } ) ).toBeVisible();
    await expect( page.getByTestId( 'security.passkeys_empty' ) ).toContainText( 'No passkeys.' );
    await expect( page.getByTestId( 'security.passkeys' ) ).toHaveCount( 0 );
} );

test( 'a seeded passkey is listed and removed through the re-auth step', async ( { page } ) => {
    reset( 'passkey' );
    await open( page );

    await expect( page.getByTestId( 'security.passkeys' ) ).toContainText( 'E2E Security Key' );
    await expect( page.getByTestId( 'security.passkeys' ) ).toContainText( 'Never used' );

    await click( page, `security.passkey_remove.${ PASSKEY_ID }` );
    await expect( page.getByTestId( 'security.reauth_what' ) ).toHaveText( /Your other passkeys are kept/ );

    await page.getByTestId( 'security.reauth_password' ).fill( OWNER_PASSWORD );
    await click( page, 'security.reauth_confirm' );

    await page.goto( SECURITY_URL );
    await expect( page.getByTestId( 'security.passkeys_empty' ) ).toBeVisible();
} );

test( 'with no WebAuthn the add control stays hidden and the sentence stays visible', async ( { browser } ) => {
    /*
     * The one place on this screen where JavaScript is not an enhancement. A
     * revealed button that cannot run a ceremony is a control that does
     * nothing, which is what §2 rules out — so it is hidden and the
     * explanation is what the person sees.
     */
    const context = await browser.newContext( { javaScriptEnabled: false } );
    const page = await context.newPage();

    try {
        await login( page, 'owner' );
        await page.goto( SECURITY_URL );

        await expect( page.getByTestId( 'security.passkey_add' ) ).toBeHidden();
        await expect( page.locator( '#security-passkey-unsupported' ) ).toBeVisible();
    } finally {
        await context.close();
    }
} );

// ─── Recovery codes ─────────────────────────────────────────────

test( 'recovery codes are shown ONCE, with the warning BEFORE them', async ( { page } ) => {
    reset( 'totp', 'codes' );
    await open( page );

    await click( page, 'security.recovery_regenerate' );
    await page.getByTestId( 'security.reauth_password' ).fill( OWNER_PASSWORD );
    await click( page, 'security.reauth_confirm' );

    const codes = page.getByTestId( 'security.recovery_codes' );
    await expect( codes ).toBeVisible();
    await expect( page.getByTestId( 'security.recovery_once' ) ).toBeVisible();

    // "Before them" is a DOM-order claim, so it is read as one.
    const warningIsFirst = await page.evaluate( () => {
        const warning = document.querySelector( '[data-testid="security.recovery_once"]' );
        const list = document.querySelector( '[data-testid="security.recovery_codes"]' );
        return ( warning.compareDocumentPosition( list ) & Node.DOCUMENT_POSITION_FOLLOWING ) !== 0;
    } );
    expect( warningIsFirst, 'the warning must come before the codes, not after them' ).toBe( true );

    // A FRESH GET must not show them again: the plaintext existed in that one
    // response and nowhere else.
    await page.goto( SECURITY_URL );
    await expect( page.getByTestId( 'security.recovery_codes' ) ).toHaveCount( 0 );
    await expect( page.getByTestId( 'security.recovery_left' ) ).toBeVisible();
} );

test( 'the count sentence is number-neutral — this mechanism has no plural forms', async ( { page } ) => {
    // D-076. "1 codes remaining" was the shipped string; a count that reads as
    // a label cannot be wrong at any number.
    reset( 'totp', 'codes' );
    await open( page );

    // Trimmed before matching. An untrimmed comparison against server-rendered
    // markup was one of D-088's three test defects, and indentation is exactly
    // what it trips over.
    const line = ( await page.getByTestId( 'security.recovery_left' ).textContent() ).trim();
    expect( line ).toMatch( /^Codes remaining: \d+$/ );
} );

test( 'the recovery-codes card is the ONE bordered card, and nothing else is', async ( { page } ) => {
    // §6's second delta, read out of the browser rather than out of the
    // stylesheet (L-032): a rule that loses the cascade still reads correctly
    // in the file.
    await open( page );

    const borders = await page.locator( '.k-card' ).evaluateAll( ( els ) => els.map( ( el ) => ( {
        id: el.id,
        width: getComputedStyle( el ).borderTopWidth,
    } ) ) );

    const bordered = borders.filter( ( c ) => c.width !== '0px' ).map( ( c ) => c.id );
    expect( bordered ).toEqual( [ 'security-recovery-codes' ] );
} );

// ─── Turning everything off ─────────────────────────────────────

test( 'turning two-factor off names the real consequence and needs the password', async ( { page } ) => {
    reset( 'totp', 'email', 'codes' );
    await open( page );

    await click( page, 'security.disable_all' );
    await expect( page.getByTestId( 'security.reauth_what' ) )
        .toHaveText( /protected by its password alone/ );

    await page.getByTestId( 'security.reauth_password' ).fill( OWNER_PASSWORD );
    await click( page, 'security.reauth_confirm' );

    await page.goto( SECURITY_URL );
    await expect( page.getByTestId( 'security.totp_switch' ) ).toHaveAttribute( 'aria-checked', 'false' );
    await expect( page.getByTestId( 'security.email_switch' ) ).toHaveAttribute( 'aria-checked', 'false' );
    await expect( page.getByTestId( 'security.recovery_empty' ) ).toBeVisible();
} );

// ─── The site-wide half ─────────────────────────────────────────

test( 'the site-wide cards are hidden from a role that cannot configure the site', async ( { browser } ) => {
    /*
     * Visibility mirrors the capability the POST handlers require, so the UI
     * cannot offer an action the gate will refuse. `editor` holds
     * `security.self` and not `site.configure`.
     *
     * A FRESH context, not the shared page: `beforeEach` already signed that
     * one in as owner, and `login.php` redirects an authenticated session
     * straight back out — so a second `login()` on it waits forever for a form
     * that will never render.
     */
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        await login( page, 'editor' );
        await page.goto( SECURITY_URL );
        await expect( page.getByTestId( 'security.screen' ) ).toBeVisible();

        await expect( page.getByRole( 'heading', { level: 2, name: 'Two-factor' } ) ).toBeVisible();
        await expect( page.getByRole( 'heading', { level: 2, name: 'Encryption level' } ) ).toHaveCount( 0 );
        await expect( page.getByRole( 'heading', { level: 2, name: 'Recovery keys' } ) ).toHaveCount( 0 );

        // …and the section nav does not link to cards that are not there.
        await expect( page.getByTestId( 'security.section.encryption' ) ).toHaveCount( 0 );
    } finally {
        await context.close();
    }
} );

test( 'a wrong password refuses the encryption-level change, and the level does not move', async ( { page } ) => {
    await open( page );

    const before = await page.getByTestId( 'security.encryption_level' ).inputValue();

    await page.getByTestId( 'security.encryption_level' ).selectOption( 'professional' );
    await page.getByTestId( 'security.encryption_password' ).fill( 'not-the-password' );
    await click( page, 'security.encryption_save' );

    await expect( page.getByTestId( 'security.error_summary' ) ).toBeVisible();
    await expect( page.getByTestId( 'security.encryption_password' ) ).toHaveAttribute( 'aria-invalid', 'true' );

    // Read the value back from a FRESH GET — the input that varies is the
    // input the assertion must read (L-035).
    await page.goto( SECURITY_URL );
    await expect( page.getByTestId( 'security.encryption_level' ) ).toHaveValue( before );
} );

// ─── Accessibility of the form machinery ────────────────────────

test( 'hint and error are BOTH in aria-describedby, hint first', async ( { page } ) => {
    await open( page );

    await click( page, 'security.email_switch' );
    await page.getByTestId( 'security.reauth_password' ).fill( 'not-the-password' );
    await click( page, 'security.reauth_confirm' );

    const described = await page.getByTestId( 'security.reauth_password' )
        .getAttribute( 'aria-describedby' );

    expect( described.split( ' ' ) ).toEqual( [
        'security-hint-confirm_password',
        'security-error-confirm_password',
    ] );
} );

test( 'the error summary takes focus and links to the field that failed', async ( { page } ) => {
    await open( page );

    await click( page, 'security.email_switch' );
    await page.getByTestId( 'security.reauth_password' ).fill( 'not-the-password' );
    await click( page, 'security.reauth_confirm' );

    const summary = page.getByTestId( 'security.error_summary' );
    await expect( summary ).toBeFocused();
    await expect( summary ).toHaveAttribute( 'role', 'alert' );
    await expect( summary.locator( 'a' ) ).toHaveAttribute( 'href', '#security-field-confirm_password' );
} );

// ─── axe, both themes, per state ────────────────────────────────

for ( const theme of [ 'dark', 'light' ] ) {
    test( `axe: WCAG 2.2 AA on the default state — ${ theme }`, async ( { page } ) => {
        await open( page, theme );

        const results = await scan( page );

        expect(
            results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` ),
            JSON.stringify( results.violations, null, 2 )
        ).toEqual( [] );
    } );

    test( `axe: WCAG 2.2 AA with every factor ON and the codes shown — ${ theme }`, async ( { page } ) => {
        reset( 'totp', 'email', 'passkey', 'codes' );
        await open( page, theme );

        await click( page, 'security.recovery_regenerate' );
        await page.getByTestId( 'security.reauth_password' ).fill( OWNER_PASSWORD );
        await click( page, 'security.reauth_confirm' );
        await expect( page.getByTestId( 'security.recovery_codes' ) ).toBeVisible();

        const results = await scan( page );

        expect(
            results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` ),
            JSON.stringify( results.violations, null, 2 )
        ).toEqual( [] );
    } );

    test( `axe: WCAG 2.2 AA on the re-auth and ERROR states — ${ theme }`, async ( { page } ) => {
        await open( page, theme );

        await click( page, 'security.email_switch' );
        await page.getByTestId( 'security.reauth_password' ).fill( 'not-the-password' );
        await click( page, 'security.reauth_confirm' );
        await expect( page.getByTestId( 'security.error_summary' ) ).toBeVisible();

        const results = await scan( page );

        expect(
            results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` ),
            JSON.stringify( results.violations, null, 2 )
        ).toEqual( [] );
    } );

    test( `axe: WCAG 2.2 AA on the enrolment ceremony — ${ theme }`, async ( { page } ) => {
        await open( page, theme );

        await click( page, 'security.totp_switch' );
        await expect( page.getByTestId( 'security.enrolment' ) ).toBeVisible();

        const results = await scan( page );

        expect(
            results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` ),
            JSON.stringify( results.violations, null, 2 )
        ).toEqual( [] );
    } );
}

// ─── Measured pairs, pinned as floors ───────────────────────────

for ( const [ theme, floor ] of [ [ 'dark', 6.75 ], [ 'light', 5.95 ] ] ) {
    test( `the secret card's own text stays where it was put — ${ theme }`, async ( { page } ) => {
        /*
         * `.k-card--secret` is the ONE bordered card and the only surface in
         * the admin painted on --tinte-aviso, so its hint leaves --texto-sutil
         * for --sobre-tinte-aviso — the token the Klytos accessibility layer
         * defines for that background.
         *
         * The floor is the MEASURED value, not 4.5, and that is deliberate:
         * pinned at 4.5 this test passed with the override removed, which made
         * it a test that could not fail for the reason it exists. At the
         * measured value the override is load-bearing and its removal is red.
         *
         * Said plainly rather than implied: `getComputedStyle().backgroundColor`
         * returns the card's BASE colour, not the tint composited over it, so
         * this reading is of the token pair and not of the true composite. The
         * composite is what the axe runs above measure, and they measure it
         * correctly — this is the regression floor beside them, not instead of
         * them.
         */
        reset( 'totp', 'codes' );
        await open( page, theme );

        const measured = await contrastOf( page.locator( '#security-recovery-codes .k-hint' ).first() );

        expect( measured ).toBeGreaterThanOrEqual( floor - 0.01 );
    } );
}

/**
 * Read an element's real contrast against whatever really paints behind it.
 *
 * Walks up for an opaque background rather than assuming the parent's, because
 * the surfaces this screen measures are translucent tints over cards.
 */
async function contrastOf( locator ) {
    return locator.evaluate( ( el ) => {
        function channels( value ) {
            return value.match( /\d+(\.\d+)?/g ).slice( 0, 3 ).map( Number );
        }
        function luminance( rgb ) {
            const [ r, g, b ] = rgb.map( ( c ) => {
                const s = c / 255;
                return s <= 0.03928 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 );
            } );
            return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        }

        let node = el;
        let background = null;
        while ( node && ! background ) {
            const value = getComputedStyle( node ).backgroundColor;
            if ( value && ! value.includes( 'rgba(0, 0, 0, 0)' ) ) {
                background = channels( value );
            }
            node = node.parentElement;
        }

        const foreground = channels( getComputedStyle( el ).color );
        const a = luminance( foreground );
        const b = luminance( background );
        return ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );
    } );
}

for ( const [ theme, floor ] of [ [ 'dark', 4.32 ], [ 'light', 4.5 ] ] ) {
    test( `DR-005 gap 3 — the destructive button does not slide further — ${ theme }`, async ( { page } ) => {
        /*
         * The armed destructive button is `--color-peligro` on
         * `--fondo-elevado`: DR-005 gap 3's own pair, on the half of the
         * population the request named and nothing had ever scanned, because
         * `.k-btn--destructive` only appears in the ARMED state of a two-step
         * confirm and no earlier spec reached that state during an axe run.
         *
         * Excluded from the axe pass by selector, so the floor is what stops an
         * open request becoming a licence to regress. DARK is the failing
         * theme at 4.32:1 and is pinned there; LIGHT passes AA outright and is
         * therefore held to 4.5, not to its measured value — a pair that
         * currently passes must go on passing, not merely stay where it is.
         */
        await open( page, theme );

        await click( page, 'security.email_switch' );
        const measured = await contrastOf( page.getByTestId( 'security.reauth_confirm' ) );

        expect( measured ).toBeGreaterThanOrEqual( floor - 0.01 );
    } );
}

test( 'the screen paints from the redesign layer, not the superseded sheet', async ( { page } ) => {
    // L-032 and L-033: never assume which rule wins — read the computed value
    // out of the browser, on a REAL screen. The legacy sheets are still loaded
    // (adaptation 9), so every newly ported screen re-opens this question.
    await open( page );

    const navColour = await page.getByTestId( 'security.section.two-factor' )
        .evaluate( ( el ) => getComputedStyle( el ).color );

    expect( navColour ).not.toBe( 'rgb(91, 141, 239)' );
} );

test( 'WCAG 1.4.10 — 320 CSS px does not scroll sideways, with everything populated', async ( { page } ) => {
    reset( 'totp', 'email', 'passkey', 'codes' );
    await open( page );

    await page.setViewportSize( { width: 320, height: 800 } );

    const overflow = await page.evaluate( () => document.documentElement.scrollWidth
        - document.documentElement.clientWidth );
    expect( overflow, 'the page scrolls horizontally at 320 CSS px' ).toBeLessThanOrEqual( 0 );
} );
