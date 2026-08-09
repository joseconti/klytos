// Manifest entry 19 — Content model — driven per STATE, in both themes.
//
// The second `record-form` screen and the first consumer of the template's
// "collection inside a form" (§2 Empty), so this spec carries that component's
// contract as well as the screen's: a row that links to its own screen, a
// disabled action whose reason is TEXT and not a tooltip, an inline two-step
// destructive confirm that never calls window.confirm(), and the empty row
// that keeps its card's heading.
//
// Two rules this spec exists to hold, both of them earned:
//
//   - persistence is checked with a FRESH GET, never page.reload(). Reloading
//     a POST response re-submits it, so the check passes whether or not
//     anything was stored (D-088).
//   - the two-step confirm is driven with JavaScript DISABLED as well as
//     enabled, because it is specified as behaviour and not as an enhancement.

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );
const CONTENT_MODEL_URL = '/installer/admin/post-types.php';

/** Every post type this spec creates carries this prefix; the cleanup keys on it. */
const E2E_PREFIX = 'e2e-';

function cleanUp() {
    execFileSync( 'php', [ path.join( REPO_ROOT, 'tests/E2E/fixtures/reset-content-model.php' ) ], {
        cwd: REPO_ROOT,
        env: { ...process.env, XDEBUG_MODE: 'off' },
    } );
}

test.beforeEach( async ( { page } ) => {
    cleanUp();
    await login( page, 'owner' );
} );

test.afterEach( async () => {
    cleanUp();
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
    await page.goto( CONTENT_MODEL_URL );
    await expect( page.getByTestId( 'content_model.screen' ) ).toBeVisible();

    expect(
        await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
        'the theme cookie did not take — this run measured the wrong theme'
    ).toBe( theme );
}

async function createPostType( page, { id, name, slug } ) {
    await page.getByTestId( 'content_model.pt_id' ).fill( id );
    await page.getByTestId( 'content_model.pt_name' ).fill( name );
    await page.getByTestId( 'content_model.pt_slug' ).fill( slug );
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'content_model.pt_submit' ).click(),
    ] );
}

/**
 * The axe pass, scoped exactly once.
 *
 * The exclusions are applied ONE AT A TIME, deliberately. `exclude()` reads an
 * ARRAY as a frame path — "inside frame A, the element B" — not as a list of
 * selectors, so `exclude( KNOWN_DELIVERY_GAPS )` matches nothing at all and
 * excludes nothing. It fails silently and in the safe-looking direction: the
 * run simply reports the violations it was supposed to have registered, which
 * is how it was caught here, but the same mistake on a list of REAL exclusions
 * would have produced noise nobody could clear. The sibling specs loop for this
 * reason; this one now does too.
 *
 * Unlike design.spec.js and logs.spec.js, the scan is NOT scoped to `#main`.
 * That is the whole reason entry 19 found DR-005 addendum 2: the shell rides on
 * every screen and `#main` is precisely where it is not.
 */
async function scan( page ) {
    let builder = new AxeBuilder( { page } )
        .withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] );

    for ( const gap of KNOWN_DELIVERY_GAPS ) {
        builder = builder.exclude( gap );
    }
    // Dev-mode scaffolding, not a redesign surface — its own measured defects
    // are recorded in fixtures.js rather than dropped.
    for ( const surface of DEV_ONLY_SURFACES ) {
        builder = builder.exclude( surface );
    }

    return builder.analyze();
}

// ─── Structure ──────────────────────────────────────────────────

test( 'renders the two cards the manifest backs, and no third', async ( { page } ) => {
    await open( page );

    await expect( page.locator( 'h1' ) ).toHaveText( 'Content model' );
    await expect( page.getByRole( 'heading', { level: 2, name: 'Post types' } ) ).toBeVisible();
    await expect( page.getByRole( 'heading', { level: 2, name: 'Taxonomies' } ) ).toBeVisible();

    // The Statuses card is DEFERRED (roadmap §0c) because no global editable
    // status set exists. Asserting its absence is what stops it being built by
    // accident from the manifest alone, and what will fail the day it lands
    // without its roadmap row being cleared.
    await expect( page.getByRole( 'heading', { level: 2, name: 'Statuses' } ) ).toHaveCount( 0 );

    // §4: exactly one h1 on the page.
    await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
} );

test( 'the seeded page type links to its own screen and cannot be deleted', async ( { page } ) => {
    await open( page );

    const link = page.getByTestId( 'content_model.post_type_link.page' );
    await expect( link ).toBeVisible();
    await expect( link ).toHaveAttribute( 'href', 'post-type-edit.php?id=page' );

    // §2 Disabled: "a disabled control is never hidden and never explained only
    // in a tooltip". The reason is real text, and it is the button's
    // description — a title attribute would satisfy neither half.
    const disabled = page.getByTestId( 'content_model.post_type_delete_disabled.page' );
    await expect( disabled ).toBeDisabled();

    const describedBy = await disabled.getAttribute( 'aria-describedby' );
    expect( describedBy, 'the disabled action states no reason' ).toBeTruthy();
    await expect( page.locator( `#${ describedBy }` ) ).toBeVisible();
    await expect( page.locator( `#${ describedBy }` ) ).toContainText( 'cannot be deleted' );
} );

// ─── Creating ───────────────────────────────────────────────────

test( 'creates a post type and it survives a FRESH GET', async ( { page } ) => {
    await open( page );

    await createPostType( page, { id: `${ E2E_PREFIX }product`, name: 'E2E Products', slug: 'e2e-products' } );

    await expect( page.getByTestId( 'content_model.status_line' ) ).toContainText( 'Post type created.' );
    await expect( page.getByTestId( `content_model.post_type_link.${ E2E_PREFIX }product` ) ).toBeVisible();

    // A fresh GET, never page.reload(): reload re-POSTs, so the row would
    // appear again whether or not anything was stored (D-088).
    await page.goto( CONTENT_MODEL_URL );
    await expect( page.getByTestId( `content_model.post_type_link.${ E2E_PREFIX }product` ) ).toBeVisible();
} );

test( 'a taxonomy is created into a chosen post type and links to that pair', async ( { page } ) => {
    await open( page );
    await createPostType( page, { id: `${ E2E_PREFIX }event`, name: 'E2E Events', slug: 'e2e-events' } );

    await page.getByTestId( 'content_model.tax_post_type' ).selectOption( `${ E2E_PREFIX }event` );
    await page.getByTestId( 'content_model.tax_id' ).fill( 'venue' );
    await page.getByTestId( 'content_model.tax_name' ).fill( 'Venues' );
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'content_model.tax_submit' ).click(),
    ] );

    await expect( page.getByTestId( 'content_model.status_line' ) ).toContainText( 'Taxonomy created.' );

    // The link carries BOTH halves of the identity. A taxonomy id alone is not
    // unique — two post types may each hold a "category".
    const link = page.getByTestId( `content_model.taxonomy_link.${ E2E_PREFIX }event.venue` );
    await expect( link ).toHaveAttribute( 'href', `taxonomy.php?post_type=${ E2E_PREFIX }event&taxonomy=venue` );

    await page.goto( CONTENT_MODEL_URL );
    await expect( page.getByTestId( `content_model.taxonomy_link.${ E2E_PREFIX }event.venue` ) ).toBeVisible();
} );

// ─── Validation ─────────────────────────────────────────────────

test( 'an empty submission produces the error summary, and it takes focus', async ( { page } ) => {
    await open( page );

    // `required` would stop the submission in the browser, so the server-side
    // branch is reached by removing the attribute — the server is what has to
    // refuse, and a client-only refusal is not a refusal.
    await page.evaluate( () => {
        document.querySelectorAll( '[data-testid="content_model.post_type_form"] [required]' )
            .forEach( ( el ) => el.removeAttribute( 'required' ) );
    } );

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'content_model.pt_submit' ).click(),
    ] );

    const summary = page.getByTestId( 'content_model.error_summary' );
    await expect( summary ).toBeVisible();
    await expect( summary ).toHaveAttribute( 'role', 'alert' );

    // §2: "focus moved to it on load".
    await expect( summary ).toBeFocused();

    // Every failed field is a LINK to that field, and the target exists.
    for ( const field of [ 'pt_id', 'pt_name', 'pt_slug' ] ) {
        await expect( page.getByTestId( `content_model.${ field }` ) ).toHaveAttribute( 'aria-invalid', 'true' );
        await expect( page.locator( `a[href="#content-model-field-${ field }"]` ) ).toBeVisible();
    }
} );

test( 'hint and error are BOTH in aria-describedby, hint first', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'content_model.pt_id' ).fill( 'Not A Slug' );
    await page.getByTestId( 'content_model.pt_name' ).fill( 'Whatever' );
    await page.getByTestId( 'content_model.pt_slug' ).fill( 'whatever' );
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'content_model.pt_submit' ).click(),
    ] );

    const control = page.getByTestId( 'content_model.pt_id' );
    await expect( control ).toHaveAttribute( 'aria-invalid', 'true' );

    const ids = ( await control.getAttribute( 'aria-describedby' ) ).split( /\s+/ );
    expect( ids ).toEqual( [ 'content-model-hint-pt_id', 'content-model-error-pt_id' ] );

    // Both are real, visible text — §4 asks for both, not for one that replaces
    // the other, and colour is never the only channel (§1.3).
    for ( const id of ids ) {
        await expect( page.locator( `#${ id }` ) ).toBeVisible();
    }
} );

test( 'a taxonomy with no post type chosen is refused by the server', async ( { page } ) => {
    await open( page );

    await page.evaluate( () => {
        document.querySelectorAll( '[data-testid="content_model.taxonomy_form"] [required]' )
            .forEach( ( el ) => el.removeAttribute( 'required' ) );
    } );
    await page.getByTestId( 'content_model.tax_id' ).fill( 'orphan' );
    await page.getByTestId( 'content_model.tax_name' ).fill( 'Orphan' );
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'content_model.tax_submit' ).click(),
    ] );

    await expect( page.getByTestId( 'content_model.tax_post_type' ) ).toHaveAttribute( 'aria-invalid', 'true' );
    await expect( page.getByTestId( 'content_model.error_summary' ) ).toContainText( 'choose a post type' );
} );

// ─── The destructive two-step ───────────────────────────────────

test( 'delete is a two-step inline confirm whose armed label states what really happens', async ( { page } ) => {
    await open( page );
    await createPostType( page, { id: `${ E2E_PREFIX }case`, name: 'E2E Cases', slug: 'e2e-cases' } );

    const first = page.getByTestId( `content_model.post_type_delete.${ E2E_PREFIX }case` );
    await expect( first ).toBeVisible();

    // The wrapper is the live region, so the relabel is announced (§2).
    await expect( first.locator( 'xpath=ancestor::form[1]' ) ).toHaveAttribute( 'aria-live', 'polite' );

    await Promise.all( [ page.waitForLoadState( 'load' ), first.click() ] );

    // Still there after one click — one click never deletes.
    const armed = page.getByTestId( `content_model.post_type_delete_confirm.${ E2E_PREFIX }case` );
    await expect( armed ).toBeVisible();
    await expect( page.getByTestId( `content_model.post_type_link.${ E2E_PREFIX }case` ) ).toBeVisible();

    /*
     * The template's example sentence is "34 records will be deleted". In THIS
     * product that would be false: delete() removes the type and its term data
     * and leaves the records. The armed label therefore says what is true, and
     * this assertion is what stops the design's example being copied back in
     * as a claim the code does not honour.
     */
    await expect( armed ).toContainText( 'the post type only' );
    await expect( armed ).toContainText( 'Records kept:' );

    await Promise.all( [ page.waitForLoadState( 'load' ), armed.click() ] );

    await expect( page.getByTestId( 'content_model.status_line' ) ).toContainText( 'Post type deleted.' );

    await page.goto( CONTENT_MODEL_URL );
    await expect( page.getByTestId( `content_model.post_type_link.${ E2E_PREFIX }case` ) ).toHaveCount( 0 );
} );

test( 'the two-step confirm works with JavaScript DISABLED', async ( { browser } ) => {
    // Specified as behaviour, not as an enhancement: a confirm that lives in
    // script alone is a one-step delete for anyone without it, which is the
    // real content of §2's "never a browser confirm()".
    const context = await browser.newContext( { javaScriptEnabled: false } );
    const page = await context.newPage();

    await page.goto( '/installer/admin/login.php' );
    await page.locator( 'input[name="username"]' ).fill( 'owner' );
    await page.locator( 'input[name="password"]' ).fill( 'playground-owner-2026' );
    await page.locator( 'form button[type="submit"]' ).first().click();
    await page.waitForLoadState( 'load' );

    await page.goto( CONTENT_MODEL_URL );
    await page.getByTestId( 'content_model.pt_id' ).fill( `${ E2E_PREFIX }nojs` );
    await page.getByTestId( 'content_model.pt_name' ).fill( 'E2E No JS' );
    await page.getByTestId( 'content_model.pt_slug' ).fill( 'e2e-nojs' );
    await page.getByTestId( 'content_model.pt_submit' ).click();
    await page.waitForLoadState( 'load' );

    await expect( page.getByTestId( `content_model.post_type_link.${ E2E_PREFIX }nojs` ) ).toBeVisible();

    await page.getByTestId( `content_model.post_type_delete.${ E2E_PREFIX }nojs` ).click();
    await page.waitForLoadState( 'load' );
    const armed = page.getByTestId( `content_model.post_type_delete_confirm.${ E2E_PREFIX }nojs` );
    await expect( armed ).toBeVisible();

    await armed.click();
    await page.waitForLoadState( 'load' );
    await expect( page.getByTestId( `content_model.post_type_link.${ E2E_PREFIX }nojs` ) ).toHaveCount( 0 );

    await context.close();
} );

// ─── The empty collection ───────────────────────────────────────

test( 'an empty collection renders its sentence and KEEPS its card heading', async ( { page } ) => {
    await open( page );

    // The seeded install has no taxonomies, which is the state §2 describes.
    await expect( page.getByTestId( 'content_model.taxonomies_empty' ) ).toBeVisible();
    await expect( page.getByTestId( 'content_model.taxonomies' ) ).toHaveCount( 0 );

    // "…inside the card, KEEPING the card's heading" — the card does not
    // collapse into a bare sentence.
    await expect( page.getByRole( 'heading', { level: 2, name: 'Taxonomies' } ) ).toBeVisible();

    // And the add action is still reachable: an empty collection that also
    // hides the way to fill it is a dead end.
    await expect( page.getByTestId( 'content_model.tax_submit' ) ).toBeVisible();
} );

// ─── Accessibility and layout, measured in the browser ──────────

for ( const theme of [ 'dark', 'light' ] ) {
    test( `axe: WCAG 2.2 AA on the default state — ${ theme }`, async ( { page } ) => {
        await open( page, theme );

        const results = await scan( page );

        expect(
            results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` ),
            JSON.stringify( results.violations, null, 2 )
        ).toEqual( [] );
    } );

    test( `axe: WCAG 2.2 AA on the ERROR state — ${ theme }`, async ( { page } ) => {
        await open( page, theme );

        await page.evaluate( () => {
            document.querySelectorAll( '[data-testid="content_model.post_type_form"] [required]' )
                .forEach( ( el ) => el.removeAttribute( 'required' ) );
        } );
        await Promise.all( [
            page.waitForLoadState( 'load' ),
            page.getByTestId( 'content_model.pt_submit' ).click(),
        ] );
        await expect( page.getByTestId( 'content_model.error_summary' ) ).toBeVisible();

        const results = await scan( page );

        expect(
            results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` ),
            JSON.stringify( results.violations, null, 2 )
        ).toEqual( [] );
    } );
}

test( 'WCAG 1.4.10 — 320 CSS px does not scroll sideways', async ( { page } ) => {
    await open( page );
    await createPostType( page, {
        id: `${ E2E_PREFIX }reflow`,
        name: 'A deliberately long post type name for the reflow check',
        slug: 'e2e-reflow',
    } );

    await page.setViewportSize( { width: 320, height: 800 } );

    // The page's REAL scroll width, read from the browser. Every containment
    // reading said 280-inside-320 while the page really scrolled (D-078).
    const overflow = await page.evaluate( () => document.documentElement.scrollWidth
        - document.documentElement.clientWidth );
    expect( overflow, 'the page scrolls horizontally at 320 CSS px' ).toBeLessThanOrEqual( 0 );
} );

test( 'the collection row paints from the redesign layer, not the superseded sheet', async ( { page } ) => {
    // L-032 and L-033: never assume which rule wins — read the computed value
    // out of the browser, on a REAL screen. The legacy sheets are still loaded
    // (adaptation 9), so every newly ported screen re-opens this question.
    await open( page );

    const painted = await page.getByTestId( 'content_model.post_type_link.page' ).evaluate( ( el ) => {
        const styles = getComputedStyle( el );
        return { color: styles.color, fontSize: getComputedStyle( el.closest( '.k-collection-meta' ) || el ).fontSize };
    } );

    // #5B8DEF is the pre-redesign klytos-base.css link colour that beat the
    // redesign's :where() layer for a whole stage (D-079 defect 1). Its return
    // on any newly ported screen is the regression this pins.
    expect( painted.color ).not.toBe( 'rgb(91, 141, 239)' );
} );
