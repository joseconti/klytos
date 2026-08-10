// Manifest entry 32 — Taxonomies, the ADD-TERM FORM half — driven per state, in
// both themes.
//
// The terms TABLE half stays DR-006-blocked (`grid-template-columns` for the
// terms grid is on DR-006's list and has not been answered), so this spec pins
// the form half and, for the table, only the defects that are not layout: the
// literal catalogue keys in its header and the browser `confirm()` on its
// delete. It deliberately asserts NOTHING about the table's column widths.
//
// THE REPRODUCTIONS, each written and seen failing against the SHIPPED markup
// before a line of the rewrite existed:
//
//   1. THE PARENT FIELD DOES NOT EXIST. `PostTypeManager::addTerm()` accepts
//      `parent`, the shipped handler reads `$_POST['parent'] ?? ''`, and no
//      control named `parent` has ever been rendered anywhere on this screen.
//      So hierarchy — which the product stores, which entry 19 lets you switch
//      on, and which §32 builds its one delta around — has been unreachable
//      from the admin since it shipped, and every term ever created here has
//      `parent => ''`. This is also §32's fourth field: the manifest says the
//      add-term form is "a record-form with four fields" and the shipped form
//      has three.
//   2. LITERAL CATALOGUE KEYS. `common.add`, `common.slug`, `common.no_items`
//      and `common.auto_generated` are called by this screen and defined by no
//      catalogue, so they render as themselves — L-046 exactly, found by the
//      reverse check that lesson says nothing in this project performs.
//   3. A REFUSED CSRF POST REPORTS NOTHING. `if ( klytos_verify_csrf() )` with
//      no else, so an expired token makes the whole handler vanish and the page
//      re-renders as though nothing was sent. The THIRD screen to carry the
//      identical defect, after entry 27 and entry 28.
//   4. A RAW EXCEPTION MESSAGE REACHES THE PERSON. `$error = $e->getMessage()`
//      prints the manager's own English sentence — "Term 'x' already exists in
//      taxonomy 'y'." — to every one of the 20 locales, and it names internal
//      ids at that.
//   5. THE DELETE USES A BROWSER `confirm()`. §2 of the record-form template
//      forbids it by name: "Inline two-step confirm … Never a browser
//      `confirm()`."
//
// Rules carried forward, each already paid for:
//   - axe scans the WHOLE PAGE, never `#main` (L-037).
//   - exclusions go in ONE AT A TIME: an array is read as a FRAME PATH (L-037).
//   - every STATE is scanned, not only the resting ones (D-091).
//   - the theme is baked in BEFORE first paint, and read back (L-035).
//   - controls are scoped to the CARD or to `main`, never `.first()` (L-042).
//   - a test that varies an input reads that input back (L-035), and
//     persistence is checked with a FRESH GET, never `page.reload()`.

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const FIXTURE = path.join( __dirname, 'fixtures', 'reset-taxonomy.php' );
const REPO_ROOT = path.join( __dirname, '..', '..' );

const POST_TYPE = 'e2e-tax';
const HIER = 'e2e-tax-cat';
const FLAT = 'e2e-tax-tag';

const url = ( taxonomy ) =>
    `/installer/admin/taxonomy.php?post_type=${ POST_TYPE }&taxonomy=${ taxonomy }`;

/** Re-create the fixture from nothing. Every test starts from the same records. */
function seed() {
    execFileSync( 'php', [ FIXTURE ], { cwd: REPO_ROOT } );
}

test.beforeEach( async ( { page } ) => {
    seed();
    // `admin-gate.php` requires `pages.edit` for this surface; owner holds it.
    await login( page, 'owner' );
} );

test.afterAll( () => {
    execFileSync( 'php', [ FIXTURE, '--clean' ], { cwd: REPO_ROOT } );
} );

/** Open a taxonomy with the theme baked in BEFORE first paint (L-035). */
async function open( page, taxonomy = HIER, theme = 'dark' ) {
    await page.context().addCookies( [ {
        name: 'klytos_admin_theme',
        value: theme,
        url: new URL( page.url() ).origin,
    } ] );
    await page.goto( url( taxonomy ) );

    // The guard waits for the SHELL, not for this screen's own container.
    // Waiting for `taxonomy.screen` here made all twelve tests fail with the
    // same "element(s) not found" — one red about the container, none about the
    // defects the tests exist to reproduce. A red that is not the test's own red
    // is not a red first (L-041), and the container is asserted where it belongs,
    // in the structure test below.
    await expect( page.locator( 'main' ) ).toBeVisible();

    expect(
        await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
        'the theme cookie did not take — this run measured the wrong theme'
    ).toBe( theme );
}

/**
 * The screen's own error region, scoped away from `#k-live-alert` — the SHELL's
 * empty live region, which sits inside `main` on every screen and which an
 * unscoped `getByRole('alert')` resolves to instead (L-042).
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

/**
 * The failing NODES, not just the rule ids.
 *
 * A bare `color-contrast × 2` says a colour is wrong and not which element, and
 * the answer decides whether the pair is the delivery's (a Design Request) or
 * this build's (a defect to fix). It found the latter here: `.k-code` paints a
 * sunken ground, so putting that class on the slug line inside an elevated card
 * measured 4.46:1 in light. Corrected to the bare `<code>` entry 19 already
 * uses, re-measured OUT OF THE BROWSER at 4.85:1 dark / 5.07:1 light (L-032).
 */
function violations( results ) {
    return results.violations.flatMap( ( v ) =>
        v.nodes.map( ( n ) => `${ v.id } @ ${ n.target.join( ' ' ) }` )
    );
}

// ─── Reproduction 2 — the catalogue keys ────────────────────────

test( 'no catalogue key reaches the screen, in either kind of taxonomy', async ( { page } ) => {
    for ( const taxonomy of [ HIER, FLAT ] ) {
        await open( page, taxonomy );

        // The SHAPE, not the four known keys: the defect is a class, and the
        // next unresolved key on this screen must fail here rather than be
        // noticed by a person reading a rendered page (L-046).
        const text = await page.locator( 'main' ).innerText();
        expect( text, `taxonomy ${ taxonomy }` ).not.toMatch( /\bcommon\.[a-z_]+/ );
        expect( text, `taxonomy ${ taxonomy }` ).not.toMatch( /\btaxonomy\.[a-z_]+/ );

        expect( await page.title(), `taxonomy ${ taxonomy }` ).not.toMatch( /[a-z_]+\.[a-z_]+/ );
    }
} );

// ─── Reproduction 1 — the fourth field ──────────────────────────

test( 'a hierarchical taxonomy offers a Parent field listing its terms', async ( { page } ) => {
    await open( page, HIER );

    const parent = page.getByTestId( 'taxonomy.field.parent' );
    await expect( parent ).toBeVisible();

    // Every control has a VISIBLE `<label for>` (§4). The label is found through
    // the accessible name so that a `<label>` with no `for` — which is what the
    // shipped screen had on all three of its fields — cannot pass this.
    await expect(
        page.getByTestId( 'taxonomy.form' ).getByLabel( 'Parent' )
    ).toBeVisible();

    // It offers the taxonomy's own terms, plus the explicit "no parent" choice.
    // §2 forbids a hidden default: the empty option is a real, readable option.
    const values = await parent.locator( 'option' ).evaluateAll(
        ( nodes ) => nodes.map( ( n ) => n.value )
    );
    expect( values ).toContain( '' );
    expect( values ).toContain( 'e2e-parent-term' );
    expect( values ).toContain( 'e2e-child-term' );
} );

test( 'a flat taxonomy has no Parent field at all — absent, not disabled', async ( { page } ) => {
    await open( page, FLAT );

    // A control that cannot apply is removed, not rendered inert: §2's disabled
    // state is for a control that exists and is momentarily unavailable, and it
    // requires a reason next to the label. A flat taxonomy has no parenthood to
    // explain, so the honest form is three fields.
    await expect( page.getByTestId( 'taxonomy.field.parent' ) ).toHaveCount( 0 );
    await expect( page.getByTestId( 'taxonomy.form' ).locator( '.k-field' ) ).toHaveCount( 3 );
} );

test( 'a parent chosen in the form is stored and read back on a fresh GET', async ( { page } ) => {
    await open( page, HIER );

    await page.getByTestId( 'taxonomy.field.name' ).fill( 'E2E nested term' );
    await page.getByTestId( 'taxonomy.field.slug' ).fill( 'e2e-nested-term' );
    await page.getByTestId( 'taxonomy.field.parent' ).selectOption( 'e2e-parent-term' );

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'taxonomy.submit' ).click(),
    ] );

    await expect( page.getByTestId( 'taxonomy.status_line' ) ).toBeVisible();

    // A FRESH GET, never `page.reload()` — a reload re-submits the POST and so
    // proves nothing about what was stored (L-035).
    await page.goto( url( HIER ) );

    const stored = execFileSync(
        'php',
        [ '-r', `require "installer/core/app.php";
            $a = \\Klytos\\Core\\App::getInstance(); $a->boot();
            echo json_encode( $a->getPostTypeManager()->getTerm( "${ POST_TYPE }", "${ HIER }", "e2e-nested-term" ) );` ],
        { cwd: REPO_ROOT }
    ).toString();

    expect( JSON.parse( stored ).parent ).toBe( 'e2e-parent-term' );
} );

// ─── Reproduction 3 — the refused post ──────────────────────────

test( 'a refused CSRF token is reported, and nothing is written', async ( { page } ) => {
    await open( page, HIER );

    await page.getByTestId( 'taxonomy.field.name' ).fill( 'E2E refused term' );
    await page.getByTestId( 'taxonomy.field.slug' ).fill( 'e2e-refused-term' );

    // Break the token in the DOM rather than by expiring the session: the point
    // is the HANDLER's answer to a token it refuses, and a logged-out session
    // would be answered by the gate instead — a red about the wrong layer
    // (L-042).
    await page.getByTestId( 'taxonomy.form' )
        .locator( 'input[name="csrf"]' )
        .evaluate( ( el ) => { el.value = 'not-the-token'; } );

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'taxonomy.submit' ).click(),
    ] );

    await expect( errorSummary( page ) ).toBeVisible();

    const listed = execFileSync(
        'php',
        [ '-r', `require "installer/core/app.php";
            $a = \\Klytos\\Core\\App::getInstance(); $a->boot();
            echo json_encode( array_column( $a->getPostTypeManager()->listTerms( "${ POST_TYPE }", "${ HIER }" ), "slug" ) );` ],
        { cwd: REPO_ROOT }
    ).toString();

    expect( JSON.parse( listed ) ).not.toContain( 'e2e-refused-term' );
} );

// ─── Reproduction 4 — the raw exception ─────────────────────────

test( 'a duplicate slug is refused in a sentence a catalogue can reach', async ( { page } ) => {
    await open( page, HIER );

    await page.getByTestId( 'taxonomy.field.name' ).fill( 'E2E parent term' );
    await page.getByTestId( 'taxonomy.field.slug' ).fill( 'e2e-parent-term' );

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'taxonomy.submit' ).click(),
    ] );

    // Field level: `aria-invalid`, the message linked by `aria-describedby`, and
    // an icon before it so colour is never the only channel (§2).
    const slug = page.getByTestId( 'taxonomy.field.slug' );
    await expect( slug ).toHaveAttribute( 'aria-invalid', 'true' );

    const described = ( await slug.getAttribute( 'aria-describedby' ) ) || '';
    expect( described.split( /\s+/ ) ).toContain( 'taxonomy-error-slug' );

    // The manager's own sentence names internal ids and is English-only. It must
    // not be what the person is shown.
    const summary = await errorSummary( page ).innerText();
    expect( summary ).not.toMatch( /already exists in taxonomy/ );
    expect( summary ).toMatch( /already/i );
} );

test( 'an empty name is refused by the SERVER, not by Chromium', async ( { page } ) => {
    await open( page, HIER );

    // `required` is deliberately absent from the name field: the browser's own
    // constraint validation would refuse the submit before a request existed,
    // which puts the refusal in Chromium instead of in the handler that owns it
    // (L-042, paid for three times on entry 27).
    await expect( page.getByTestId( 'taxonomy.field.name' ) ).not.toHaveAttribute( 'required', '' );

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'taxonomy.submit' ).click(),
    ] );

    await expect( errorSummary( page ) ).toBeVisible();
    await expect( page.getByTestId( 'taxonomy.field.name' ) ).toHaveAttribute( 'aria-invalid', 'true' );
} );

// ─── Reproduction 5 — the browser confirm ───────────────────────

test( 'delete is an inline two-step confirm, and no browser dialog is ever raised', async ( { page } ) => {
    await open( page, HIER );

    // A dialog that is never dismissed blocks every later command, so this
    // listener both fails the test and unblocks the run.
    let dialogRaised = '';
    page.on( 'dialog', async ( dialog ) => {
        dialogRaised = dialog.message();
        await dialog.dismiss();
    } );

    const row = page.getByTestId( `taxonomy.delete.e2e-child-term` );
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        row.click(),
    ] );

    expect( dialogRaised, 'a browser confirm() was raised — §2 forbids it by name' ).toBe( '' );

    // First click ARMS the control; the record is still there.
    const armed = page.getByTestId( 'taxonomy.delete_confirm.e2e-child-term' );
    await expect( armed ).toBeVisible();
    await expect( armed.locator( 'xpath=ancestor::*[@aria-live][1]' ) ).toHaveAttribute( 'aria-live', 'polite' );

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        armed.click(),
    ] );

    expect( dialogRaised ).toBe( '' );
    await expect( page.getByTestId( 'taxonomy.delete.e2e-child-term' ) ).toHaveCount( 0 );
} );

// ─── Structure and accessibility ────────────────────────────────

test( 'the add-term card is a record-form card with a labelled heading', async ( { page } ) => {
    await open( page, HIER );

    await expect( page.getByTestId( 'taxonomy.screen' ) ).toBeVisible();

    const form = page.getByTestId( 'taxonomy.form' );
    await expect( form ).toBeVisible();

    // §32: "a record-form with four fields" — on a hierarchical taxonomy that is
    // Name, Slug, Parent, Description, which is exactly `addTerm()`'s own scalar
    // field set.
    await expect( form.locator( '.k-field' ) ).toHaveCount( 4 );

    for ( const field of [ 'name', 'slug', 'parent', 'description' ] ) {
        const control = page.getByTestId( `taxonomy.field.${ field }` );
        const id = await control.getAttribute( 'id' );
        expect( id, `${ field } has no id, so no label can point at it` ).toBeTruthy();
        await expect( page.locator( `label[for="${ id }"]` ) ).toBeVisible();

        // Hints are in `aria-describedby`, hint first (§4).
        const described = ( await control.getAttribute( 'aria-describedby' ) ) || '';
        expect( described.split( /\s+/ )[ 0 ], `${ field }` ).toBe( `taxonomy-hint-${ field }` );
    }

    // No placeholder-as-label anywhere in the admin (§4). The shipped slug field
    // carried its only explanation in a `placeholder`.
    const placeholders = await form.locator( '[placeholder]' ).count();
    expect( placeholders ).toBe( 0 );
} );

test( 'the empty state is a sentence, not a bare zero', async ( { page } ) => {
    await open( page, FLAT );

    const empty = page.getByTestId( 'taxonomy.no_terms' );
    await expect( empty ).toBeVisible();
    expect( ( await empty.innerText() ).length ).toBeGreaterThan( 20 );
} );

test( 'the whole page is clean under axe, in every state and both themes', async ( { page } ) => {
    for ( const theme of [ 'dark', 'light' ] ) {
        // Resting, hierarchical.
        await open( page, HIER, theme );
        expect( violations( await scan( page ) ), `resting/${ theme }` ).toEqual( [] );

        // Empty, flat.
        await open( page, FLAT, theme );
        expect( violations( await scan( page ) ), `empty/${ theme }` ).toEqual( [] );

        // The ERROR state — the one a resting-only pass never sees (D-091).
        await open( page, HIER, theme );
        await Promise.all( [
            page.waitForLoadState( 'load' ),
            page.getByTestId( 'taxonomy.submit' ).click(),
        ] );
        await expect( errorSummary( page ) ).toBeVisible();
        expect( violations( await scan( page ) ), `error/${ theme }` ).toEqual( [] );

        // The ARMED delete state.
        await open( page, HIER, theme );
        await Promise.all( [
            page.waitForLoadState( 'load' ),
            page.getByTestId( 'taxonomy.delete.e2e-child-term' ).click(),
        ] );
        await expect( page.getByTestId( 'taxonomy.delete_confirm.e2e-child-term' ) ).toBeVisible();
        expect( violations( await scan( page ) ), `armed/${ theme }` ).toEqual( [] );
    }
} );

test( 'the form works with JavaScript disabled', async ( { browser } ) => {
    const context = await browser.newContext( { javaScriptEnabled: false } );
    const page = await context.newPage();

    await login( page, 'owner' );
    await page.goto( url( HIER ) );

    await page.getByTestId( 'taxonomy.field.name' ).fill( 'E2E no-script term' );
    await page.getByTestId( 'taxonomy.field.slug' ).fill( 'e2e-no-script-term' );
    await page.getByTestId( 'taxonomy.submit' ).click();
    await page.waitForLoadState( 'load' );

    await expect( page.getByTestId( 'taxonomy.status_line' ) ).toBeVisible();

    await context.close();
} );
