// Manifest entry 39 — Post type — driven per STATE, in both themes.
//
// The third `record-form` screen, and the first consumer of two things the
// template layer had built with nobody using them:
//
//   - the SECTION NAV (§1's optional left column, §3's chip row at 900–1199).
//     Entries 3 and 19 both render `--no-nav`, so until this screen the nav's
//     CSS had never painted anything. A seam with no consumer is an untested
//     seam (L-030, four occurrences in this build).
//   - the `.k-collection*` layer's SECOND consumer, which is what proves it was
//     not built for one screen (D-079's rule).
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
//   - the two-step confirm is driven with JavaScript DISABLED, because it is
//     specified as behaviour rather than as an enhancement (D-089).

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );
const POST_TYPE_ID = 'e2e-pt';
const POST_TYPE_URL = `/installer/admin/post-type-edit.php?id=${ POST_TYPE_ID }`;

/**
 * Rebuild the record this spec edits, and set the site's locales.
 *
 * With no argument the locale list goes back to the seeded empty one, which is
 * the Per-locale slugs card's own empty state rather than merely "unset".
 */
function reset( locales ) {
    const args = [ path.join( REPO_ROOT, 'tests/E2E/fixtures/reset-post-type.php' ) ];
    if ( locales ) {
        args.push( `--locales=${ locales }` );
    }
    execFileSync( 'php', args, {
        cwd: REPO_ROOT,
        env: { ...process.env, XDEBUG_MODE: 'off' },
    } );
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
    await page.goto( POST_TYPE_URL );
    await expect( page.getByTestId( 'post_type.screen' ) ).toBeVisible();

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

async function submitToolbarSave( page ) {
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'post_type.save' ).click(),
    ] );
}

// ─── Structure ──────────────────────────────────────────────────

test( 'renders the five cards the manifest backs, and no Exposure card', async ( { page } ) => {
    await open( page );

    // H1 is the post type's NAME (manifest §39), not a screen title.
    await expect( page.locator( 'h1' ) ).toHaveText( 'E2E Post Type' );
    await expect( page.locator( 'h1' ) ).toHaveCount( 1 );

    for ( const heading of [ 'Identity', 'Editor choice', 'Custom fields', 'Statuses', 'Per-locale slugs' ] ) {
        await expect( page.getByRole( 'heading', { level: 2, name: heading } ) ).toBeVisible();
    }

    // Exposure is DEFERRED (roadmap §0c) because per-post-type exposure
    // switches do not exist in this product. Asserting its ABSENCE is what
    // stops it being built from the manifest alone, and what fails the day it
    // lands without its roadmap row being cleared.
    await expect( page.getByRole( 'heading', { level: 2, name: 'Exposure' } ) ).toHaveCount( 0 );
} );

test( 'the section nav is a labelled nav whose every link resolves to a real card', async ( { page } ) => {
    await open( page );

    const nav = page.getByTestId( 'post_type.section_nav' );
    await expect( nav ).toHaveAttribute( 'aria-label', 'Sections' );

    const links = nav.locator( 'a' );
    await expect( links ).toHaveCount( 5 );

    // Every target exists. A section nav pointing at nothing is the shape of
    // defect this screen is the first to be able to have at all.
    const hrefs = await links.evaluateAll( ( els ) => els.map( ( el ) => el.getAttribute( 'href' ) ) );
    for ( const href of hrefs ) {
        expect( href.startsWith( '#' ) ).toBe( true );
        await expect( page.locator( href ) ).toHaveCount( 1 );
    }

    // §4: the current section is aria-current="page", and exactly one item
    // carries it.
    await expect( nav.locator( '[aria-current="page"]' ) ).toHaveCount( 1 );
} );

test( 'the section nav actually paints — the layer had no consumer before this screen', async ( { page } ) => {
    // L-030's rule: a seam nothing has used is an untested seam. Read the
    // computed values out of the browser, on a real screen (L-032, L-033).
    await open( page );
    await page.setViewportSize( { width: 1440, height: 900 } );

    const layout = await page.getByTestId( 'post_type.screen' ).evaluate( ( el ) => {
        const styles = getComputedStyle( el );
        return { display: styles.display, columns: styles.gridTemplateColumns };
    } );

    expect( layout.display ).toBe( 'grid' );
    // §3: "section nav 180px sticky at left, card stack fills the rest."
    expect( layout.columns.startsWith( '180px ' ) ).toBe( true );

    const nav = await page.getByTestId( 'post_type.section_nav' ).evaluate( ( el ) => {
        const styles = getComputedStyle( el );
        return { position: styles.position, direction: styles.flexDirection };
    } );
    expect( nav.position ).toBe( 'sticky' );
    expect( nav.direction ).toBe( 'column' );
} );

test( 'at 900–1199 the section nav becomes a chip row above the cards', async ( { page } ) => {
    await open( page );
    await page.setViewportSize( { width: 1024, height: 900 } );

    const measured = await page.getByTestId( 'post_type.section_nav' ).evaluate( ( el ) => {
        const styles = getComputedStyle( el );
        return {
            position: styles.position,
            direction: styles.flexDirection,
            columns: getComputedStyle( el.parentElement ).gridTemplateColumns,
        };
    } );

    expect( measured.position ).toBe( 'static' );
    expect( measured.direction ).toBe( 'row' );
    // One track: the nav no longer occupies a column of its own.
    expect( measured.columns.split( ' ' ).length ).toBe( 1 );

    // §3 keeps it a <nav> with the same label — it is a chip row, not a
    // different component.
    await expect( page.getByTestId( 'post_type.section_nav' ) ).toHaveAttribute( 'aria-label', 'Sections' );
} );

// ─── Identity ───────────────────────────────────────────────────

test( 'the ID is readonly rather than disabled, and is never posted', async ( { page } ) => {
    await open( page );

    const id = page.getByTestId( 'post_type.id' );
    // §2 "Read-only vs disabled": a value the user may copy but not change is
    // readonly, mono and selectable — NOT disabled.
    await expect( id ).toHaveAttribute( 'readonly', '' );
    await expect( id ).not.toBeDisabled();
    await expect( id ).toHaveValue( POST_TYPE_ID );

    // No name and no form association: `update()` refuses the id by
    // construction, so posting it would be a value nothing reads.
    expect( await id.getAttribute( 'name' ) ).toBeNull();
    expect( await id.getAttribute( 'form' ) ).toBeNull();

    const painted = await id.evaluate( ( el ) => getComputedStyle( el ).fontFamily );
    expect( painted.toLowerCase() ).toContain( 'mono' );
} );

test( 'the toolbar Save submits the form it sits outside of, and survives a FRESH GET', async ( { page } ) => {
    await open( page );

    // §1: "the primary Save lives in the TOOLBAR". The toolbar is emitted by
    // the shell, outside <main>, so the association is the `form` attribute.
    await expect( page.getByTestId( 'post_type.save' ) ).toHaveAttribute( 'form', 'k-post-type-form' );

    await page.getByTestId( 'post_type.name' ).fill( 'Renamed by the driver' );
    await page.getByTestId( 'post_type.slug' ).fill( 'renamed-by-the-driver' );
    await page.getByTestId( 'post_type.editor.tinymce' ).check();
    await submitToolbarSave( page );

    await expect( page.getByTestId( 'post_type.status_line' ) ).toContainText( 'Post type saved.' );
    // The H1 is the name, so it moved too.
    await expect( page.locator( 'h1' ) ).toHaveText( 'Renamed by the driver' );

    // A fresh GET, never page.reload(): reload re-POSTs (D-088).
    await page.goto( POST_TYPE_URL );
    await expect( page.getByTestId( 'post_type.name' ) ).toHaveValue( 'Renamed by the driver' );
    await expect( page.getByTestId( 'post_type.slug' ) ).toHaveValue( 'renamed-by-the-driver' );
    await expect( page.getByTestId( 'post_type.editor.tinymce' ) ).toBeChecked();
} );

test( 'Enter in a text field saves — the toolbar button is the implicit submit', async ( { page } ) => {
    // §4: "the toolbar's Save … also exists as the form's implicit submit
    // button, so Enter in a text field saves." That is a property of the form
    // association, not of any script.
    await open( page );

    await page.getByTestId( 'post_type.name' ).fill( 'Saved with the Enter key' );
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'post_type.name' ).press( 'Enter' ),
    ] );

    await expect( page.getByTestId( 'post_type.status_line' ) ).toBeVisible();

    await page.goto( POST_TYPE_URL );
    await expect( page.getByTestId( 'post_type.name' ) ).toHaveValue( 'Saved with the Enter key' );
} );

test( 'an empty name is refused by the SERVER, and the summary takes focus', async ( { page } ) => {
    await open( page );

    // `required` would stop the submission in the browser; the server is what
    // has to refuse, and a client-only refusal is not a refusal.
    await page.evaluate( () => {
        document.querySelectorAll( '#k-post-type-form, [form="k-post-type-form"]' )
            .forEach( ( el ) => el.removeAttribute( 'required' ) );
        document.querySelectorAll( '[required][form="k-post-type-form"]' )
            .forEach( ( el ) => el.removeAttribute( 'required' ) );
    } );
    await page.getByTestId( 'post_type.name' ).fill( '' );
    await page.getByTestId( 'post_type.slug' ).fill( '' );
    await submitToolbarSave( page );

    const summary = page.getByTestId( 'post_type.error_summary' );
    await expect( summary ).toBeVisible();
    await expect( summary ).toHaveAttribute( 'role', 'alert' );
    await expect( summary ).toBeFocused();

    for ( const field of [ 'name', 'slug' ] ) {
        await expect( page.getByTestId( `post_type.${ field }` ) ).toHaveAttribute( 'aria-invalid', 'true' );
        await expect( page.locator( `a[href="#post-type-field-${ field }"]` ) ).toBeVisible();
    }

    // Nothing was written: the stored name is still what the fixture created.
    await page.goto( POST_TYPE_URL );
    await expect( page.getByTestId( 'post_type.name' ) ).toHaveValue( 'E2E Post Type' );
} );

test( 'hint and error are BOTH in aria-describedby, hint first', async ( { page } ) => {
    await open( page );

    await page.evaluate( () => {
        document.querySelectorAll( '[required]' ).forEach( ( el ) => el.removeAttribute( 'required' ) );
    } );
    await page.getByTestId( 'post_type.name' ).fill( '' );
    await submitToolbarSave( page );

    const control = page.getByTestId( 'post_type.name' );
    await expect( control ).toHaveAttribute( 'aria-invalid', 'true' );

    const ids = ( await control.getAttribute( 'aria-describedby' ) ).split( /\s+/ );
    expect( ids ).toEqual( [ 'post-type-hint-name', 'post-type-error-name' ] );
    for ( const id of ids ) {
        await expect( page.locator( `#${ id }` ) ).toBeVisible();
    }
} );

// ─── Custom fields ──────────────────────────────────────────────

test( 'the empty custom-field collection renders the manifest sentence and KEEPS its heading', async ( { page } ) => {
    await open( page );

    // The manifest writes this screen's empty sentence out in full.
    const empty = page.getByTestId( 'post_type.custom_fields_empty' );
    await expect( empty ).toBeVisible();
    await expect( empty ).toContainText( 'No custom fields. A custom field adds a value to every record of this type.' );

    // "…inside the card, KEEPING the card's heading."
    await expect( page.getByRole( 'heading', { level: 2, name: 'Custom fields' } ) ).toBeVisible();
    // And the way to fill it is still reachable: an empty collection that also
    // hides its add action is a dead end.
    await expect( page.getByTestId( 'post_type.cf_submit' ) ).toBeVisible();
} );

test( 'a custom field is added, listed and deleted through a two-step confirm', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'post_type.cf_id' ).fill( 'price' );
    await page.getByTestId( 'post_type.cf_label' ).fill( 'Price' );
    await page.getByTestId( 'post_type.cf_type' ).selectOption( 'number' );
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'post_type.cf_submit' ).click(),
    ] );

    await expect( page.getByTestId( 'post_type.status_line' ) ).toContainText( 'Field added.' );
    await expect( page.getByTestId( 'post_type.custom_fields' ) ).toContainText( 'Price' );

    await page.goto( POST_TYPE_URL );
    await expect( page.getByTestId( 'post_type.custom_fields' ) ).toContainText( 'price' );

    const first = page.getByTestId( 'post_type.custom_field_delete.price' );
    await expect( first.locator( 'xpath=ancestor::form[1]' ) ).toHaveAttribute( 'aria-live', 'polite' );
    await Promise.all( [ page.waitForLoadState( 'load' ), first.click() ] );

    // One click never deletes.
    const armed = page.getByTestId( 'post_type.custom_field_delete_confirm.price' );
    await expect( armed ).toBeVisible();
    await expect( page.getByTestId( 'post_type.custom_fields' ) ).toContainText( 'Price' );

    // The armed label states what removeCustomField() really does: it drops the
    // definition and leaves whatever records already store. §2's example
    // sentence would be false here, and this assertion is what stops it being
    // copied back in as a claim the code does not honour.
    await expect( armed ).toContainText( 'only' );
    await expect( armed ).toContainText( 'kept' );

    await Promise.all( [ page.waitForLoadState( 'load' ), armed.click() ] );
    await expect( page.getByTestId( 'post_type.status_line' ) ).toContainText( 'Field deleted.' );

    await page.goto( POST_TYPE_URL );
    await expect( page.getByTestId( 'post_type.custom_fields_empty' ) ).toBeVisible();
} );

test( 'the option rows exist in the MARKUP, so a choice field can be built with no JavaScript', async ( { browser } ) => {
    // The screen this replaces built the option rows in script alone, so with
    // the script absent a select could be created with no options at all and
    // nothing said why.
    const context = await browser.newContext( { javaScriptEnabled: false } );
    const page = await context.newPage();

    await page.goto( '/installer/admin/login.php' );
    await page.locator( 'input[name="username"]' ).fill( 'owner' );
    await page.locator( 'input[name="password"]' ).fill( 'playground-owner-2026' );
    await page.locator( 'form button[type="submit"]' ).first().click();
    await page.waitForLoadState( 'load' );

    await page.goto( POST_TYPE_URL );

    await page.getByTestId( 'post_type.cf_id' ).fill( 'size' );
    await page.getByTestId( 'post_type.cf_label' ).fill( 'Size' );
    await page.getByTestId( 'post_type.cf_type' ).selectOption( 'select' );
    await page.getByTestId( 'post_type.cf_opt_value.0' ).fill( 's' );
    await page.getByTestId( 'post_type.cf_opt_label.0' ).fill( 'Small' );
    await page.getByTestId( 'post_type.cf_opt_value.1' ).fill( 'l' );
    await page.getByTestId( 'post_type.cf_opt_label.1' ).fill( 'Large' );
    await page.getByTestId( 'post_type.cf_submit' ).click();
    await page.waitForLoadState( 'load' );

    // An empty option row is an unused row, not an error — three are drawn and
    // two were filled.
    await expect( page.getByTestId( 'post_type.custom_fields' ) ).toContainText( 'Options: 2' );

    // And the enhancement stays hidden where its script never ran.
    await expect( page.getByTestId( 'post_type.add_option' ) ).toBeHidden();

    await context.close();
} );

// ─── Statuses ───────────────────────────────────────────────────

test( 'the four system statuses are shown LOCKED, with the reason as text', async ( { page } ) => {
    await open( page );

    for ( const id of [ 'draft', 'published', 'scheduled', 'trashed' ] ) {
        const disabled = page.getByTestId( `post_type.status_delete_disabled.${ id }` );
        await expect( disabled ).toBeDisabled();

        // §2: "a disabled control is never hidden and never explained only in a
        // tooltip". A title attribute would satisfy neither half.
        const describedBy = await disabled.getAttribute( 'aria-describedby' );
        expect( describedBy, `${ id } states no reason` ).toBeTruthy();
        await expect( page.locator( `#${ describedBy }` ) ).toContainText( 'cannot be removed' );
    }
} );

test( 'a custom status is added, EDITED IN PLACE and deleted — the set is editable', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'post_type.st_id' ).fill( 'in-review' );
    await page.getByTestId( 'post_type.st_label' ).fill( 'In review' );
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'post_type.st_submit' ).click(),
    ] );
    await expect( page.getByTestId( 'post_type.status_line' ) ).toContainText( 'Status saved.' );

    // "Statuses (EDITABLE set)": the row itself edits, with no modal and no
    // JavaScript.
    await page.getByTestId( 'post_type.status_label.in-review' ).fill( 'Awaiting review' );
    await page.getByTestId( 'post_type.status_color.in-review' ).fill( '#123456' );
    await page.getByTestId( 'post_type.status_public.in-review' ).check();
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'post_type.status_save.in-review' ).click(),
    ] );

    await page.goto( POST_TYPE_URL );
    await expect( page.getByTestId( 'post_type.status_label.in-review' ) ).toHaveValue( 'Awaiting review' );
    await expect( page.getByTestId( 'post_type.status_color.in-review' ) ).toHaveValue( '#123456' );
    await expect( page.getByTestId( 'post_type.status_public.in-review' ) ).toBeChecked();

    const first = page.getByTestId( 'post_type.status_delete.in-review' );
    await Promise.all( [ page.waitForLoadState( 'load' ), first.click() ] );

    const armed = page.getByTestId( 'post_type.status_delete_confirm.in-review' );
    await expect( armed ).toBeVisible();
    // removeStatus() reassigns every record holding this status to `draft`.
    // That is the consequence the armed label has to state.
    await expect( armed ).toContainText( 'drafts' );

    await Promise.all( [ page.waitForLoadState( 'load' ), armed.click() ] );
    await expect( page.getByTestId( 'post_type.status_line' ) ).toContainText( 'Status deleted.' );

    await page.goto( POST_TYPE_URL );
    await expect( page.getByTestId( 'post_type.status_label.in-review' ) ).toHaveCount( 0 );
} );

test( 'a reserved status id is refused with a sentence, not an exception', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'post_type.st_id' ).fill( 'draft' );
    await page.getByTestId( 'post_type.st_label' ).fill( 'Draft again' );
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'post_type.st_submit' ).click(),
    ] );

    await expect( page.getByTestId( 'post_type.st_id' ) ).toHaveAttribute( 'aria-invalid', 'true' );
    await expect( page.getByTestId( 'post_type.error_summary' ) ).toContainText( 'system status' );

    // The manager refuses it too; the screen's own refusal is what puts the
    // message beside the field the person has to change.
    await expect( page.getByTestId( 'post_type.error_summary' ) ).not.toContainText( 'Exception' );
} );

test( 'the two-step status confirm works with JavaScript DISABLED', async ( { browser } ) => {
    const context = await browser.newContext( { javaScriptEnabled: false } );
    const page = await context.newPage();

    await page.goto( '/installer/admin/login.php' );
    await page.locator( 'input[name="username"]' ).fill( 'owner' );
    await page.locator( 'input[name="password"]' ).fill( 'playground-owner-2026' );
    await page.locator( 'form button[type="submit"]' ).first().click();
    await page.waitForLoadState( 'load' );

    await page.goto( POST_TYPE_URL );
    await page.getByTestId( 'post_type.st_id' ).fill( 'nojs' );
    await page.getByTestId( 'post_type.st_label' ).fill( 'No JS' );
    await page.getByTestId( 'post_type.st_submit' ).click();
    await page.waitForLoadState( 'load' );

    await expect( page.getByTestId( 'post_type.status_label.nojs' ) ).toBeVisible();

    await page.getByTestId( 'post_type.status_delete.nojs' ).click();
    await page.waitForLoadState( 'load' );
    const armed = page.getByTestId( 'post_type.status_delete_confirm.nojs' );
    await expect( armed ).toBeVisible();

    await armed.click();
    await page.waitForLoadState( 'load' );
    await expect( page.getByTestId( 'post_type.status_label.nojs' ) ).toHaveCount( 0 );

    await context.close();
} );

// ─── Per-locale slugs ───────────────────────────────────────────

test( 'with no locales configured the card renders its empty state, not an empty fieldset', async ( { page } ) => {
    await open( page );

    await expect( page.getByTestId( 'post_type.slugs_empty' ) ).toBeVisible();
    await expect( page.getByRole( 'heading', { level: 2, name: 'Per-locale slugs' } ) ).toBeVisible();
    await expect( page.getByRole( 'group', { name: 'Slugs by locale' } ) ).toHaveCount( 0 );
} );

test( 'each locale field is lang-tagged, labelled with the locale NAME, and persists', async ( { page } ) => {
    reset( 'en,es' );
    await open( page );

    // §39's delta: a <fieldset> whose <legend> is "Slugs by locale".
    await expect( page.getByRole( 'group', { name: 'Slugs by locale' } ) ).toBeVisible();

    const es = page.getByTestId( 'post_type.slug_i18n.es' );
    // "each field's label is the locale's NAME" — not its code.
    await expect( page.locator( 'label[for="post-type-field-slug_i18n_es"]' ) ).toHaveText( 'Español' );
    // "…and the field carries lang."
    await expect( es ).toHaveAttribute( 'lang', 'es' );

    await es.fill( 'articulos' );
    await submitToolbarSave( page );

    await page.goto( POST_TYPE_URL );
    await expect( page.getByTestId( 'post_type.slug_i18n.es' ) ).toHaveValue( 'articulos' );
    // An empty field is an ABSENT override, not an empty slug.
    await expect( page.getByTestId( 'post_type.slug_i18n.en' ) ).toHaveValue( '' );
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

    test( `axe: WCAG 2.2 AA with every collection POPULATED — ${ theme }`, async ( { page } ) => {
        reset( 'en,es' );
        await open( page, theme );

        await page.getByTestId( 'post_type.cf_id' ).fill( 'colour' );
        await page.getByTestId( 'post_type.cf_label' ).fill( 'Colour' );
        await Promise.all( [
            page.waitForLoadState( 'load' ),
            page.getByTestId( 'post_type.cf_submit' ).click(),
        ] );

        await page.getByTestId( 'post_type.st_id' ).fill( 'staged' );
        await page.getByTestId( 'post_type.st_label' ).fill( 'Staged' );
        await Promise.all( [
            page.waitForLoadState( 'load' ),
            page.getByTestId( 'post_type.st_submit' ).click(),
        ] );

        await expect( page.getByTestId( 'post_type.status_label.staged' ) ).toBeVisible();

        const results = await scan( page );

        expect(
            results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` ),
            JSON.stringify( results.violations, null, 2 )
        ).toEqual( [] );
    } );

    test( `axe: WCAG 2.2 AA on the ERROR state — ${ theme }`, async ( { page } ) => {
        await open( page, theme );

        await page.evaluate( () => {
            document.querySelectorAll( '[required]' ).forEach( ( el ) => el.removeAttribute( 'required' ) );
        } );
        await page.getByTestId( 'post_type.name' ).fill( '' );
        await submitToolbarSave( page );
        await expect( page.getByTestId( 'post_type.error_summary' ) ).toBeVisible();

        const results = await scan( page );

        expect(
            results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` ),
            JSON.stringify( results.violations, null, 2 )
        ).toEqual( [] );
    } );
}

test( 'WCAG 1.4.10 — 320 CSS px does not scroll sideways, with the collections populated', async ( { page } ) => {
    reset( 'en,es' );
    await open( page );

    await page.getByTestId( 'post_type.cf_id' ).fill( 'a-deliberately-long-custom-field-identifier' );
    await page.getByTestId( 'post_type.cf_label' ).fill( 'A deliberately long custom field label for the reflow check' );
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'post_type.cf_submit' ).click(),
    ] );

    await page.setViewportSize( { width: 320, height: 800 } );

    // The page's REAL scroll width, read from the browser: every containment
    // reading said 280-inside-320 while the page really scrolled (D-078).
    const overflow = await page.evaluate( () => document.documentElement.scrollWidth
        - document.documentElement.clientWidth );
    expect( overflow, 'the page scrolls horizontally at 320 CSS px' ).toBeLessThanOrEqual( 0 );
} );

for ( const [ theme, floor ] of [ [ 'dark', 15.29 ], [ 'light', 14.79 ] ] ) {
    test( `the resting section-nav item stays above AA — ${ theme }`, async ( { page } ) => {
        /*
         * The defect this pins was REAL and this screen is what found it: the
         * item was --texto-secundario on --fondo-ventana, which measures
         * 4.46:1 in light — DR-005 gap 2's pair, under AA by 0.04. Entries 3
         * and 19 both render `--no-nav`, so the rule had never painted
         * anything and no pass had ever measured it (L-030).
         *
         * The floor is the FIXED value, not the AA minimum, so a quiet slide
         * back toward 4.5 fails here rather than at the next axe run on some
         * other screen.
         */
        await open( page, theme );

        const measured = await page.getByTestId( 'post_type.section.editor' ).evaluate( ( el ) => {
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

            // The nav has no background of its own, so the pair is the item's
            // colour over whatever really paints behind it. Walk up until an
            // opaque background is found rather than assuming the parent's.
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

        expect( measured ).toBeGreaterThanOrEqual( floor - 0.01 );
    } );
}

test( 'the screen paints from the redesign layer, not the superseded sheet', async ( { page } ) => {
    // L-032 and L-033: never assume which rule wins — read the computed value
    // out of the browser, on a REAL screen. The legacy sheets are still loaded
    // (adaptation 9), so every newly ported screen re-opens this question.
    await open( page );

    const navColour = await page.getByTestId( 'post_type.section.identity' )
        .evaluate( ( el ) => getComputedStyle( el ).color );

    // #5B8DEF is the pre-redesign klytos-base.css link colour that beat the
    // redesign's :where() layer for a whole stage (D-079 defect 1). Its return
    // on any newly ported screen is the regression this pins.
    expect( navColour ).not.toBe( 'rgb(91, 141, 239)' );
} );
