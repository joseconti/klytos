// Manifest entry 26 — Privacy — driven per card and per STATE, in both themes.
//
// What this spec exists to catch, beyond "the screen renders":
//
//   1. AN ERASURE REPORTS WHAT IT DID. The shipped screen built a per-section
//      result table and it never rendered on any install: `$foundUser` was
//      assigned only in the search branch, and the results block was nested
//      inside a check on it. Somebody who had just irreversibly erased another
//      person's data saw one green sentence and nothing else. Reproduced first,
//      against the shipped markup, before a line of the rewrite was written:
//        red observed: `the erasure reported no per-section outcome at all —
//        Locator: getByText('Form Submissions') … element(s) not found`,
//      with `.alert-success` visible in the same run, which is what made it a
//      report defect and not a failure to erase.
//   2. THE RESULT ROWS NAME SECTIONS, NOT IDENTIFIERS. The manager returns
//      `core:form_submissions` and never a label, so the table would have shown
//      internal ids. Unreachable until (1) was fixed — a defect hiding behind a
//      defect.
//   3. EVERY STATUS HAS A WORD. The manager returns `deleted` for form
//      submissions and the catalogue had no `privacy.deleted`, so the commonest
//      erasure of all would have rendered its own key as its label.
//   4. THE DESTRUCTIVE ACTION IS A TWO-STEP CONFIRM, server-side, with no
//      browser dialog and no JavaScript in either step.
//   5. Every control works with JAVASCRIPT DISABLED.
//
// Rules carried forward, each already paid for:
//   - axe scans the WHOLE PAGE, never `#main` (L-037).
//   - exclusions go in ONE AT A TIME: an array is read as a FRAME PATH (L-037).
//   - persistence is checked with a FRESH GET, never `page.reload()` (D-088).
//   - a test that varies an input reads that input back (L-035).
//   - the theme is baked in BEFORE first paint, and read back (L-035).
//   - a driven failure names WHICH LAYER refused before anything is changed
//     (L-042) — the search controls here are scoped to `main`, because the shell
//     puts its own search form and its theme form ahead of the card's and an
//     unscoped `.first()` submits one of those instead. That cost this spec its
//     first red.

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const URL_PATH = '/installer/admin/privacy.php';
const SUBJECT = 'privacy-subject';
const FIXTURE = path.join( __dirname, 'fixtures', 'reset-privacy.php' );

/**
 * Rebuild the disposable subject.
 *
 * Every erasure this screen performs is irreversible, so each test starts from
 * a subject it is allowed to destroy — never a seeded role, which the rest of
 * the tier depends on.
 */
function resetSubject() {
    execFileSync( 'php', [ FIXTURE ], { cwd: path.join( __dirname, '..', '..' ) } );
}

test.beforeEach( async ( { page } ) => {
    resetSubject();
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
    await expect( page.getByTestId( 'privacy.screen' ) ).toBeVisible();

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

/** Search inside one of the two cards and wait for the answer. */
async function search( page, card, query ) {
    await page.getByTestId( `privacy.${ card }_query` ).fill( query );
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( `privacy.${ card }_search` ).click(),
    ] );
}

/** Click a control and wait for the post it performs. */
async function post( page, testId ) {
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( testId ).click(),
    ] );
}

// ─── Structure ──────────────────────────────────────────────────

test( 'the screen renders the record-form template with no section nav', async ( { page } ) => {
    await open( page );

    // §26 lists cards, not sections, so the template's optional left column is
    // ABSENT from the DOM rather than rendered empty.
    await expect( page.locator( '.k-record-form--no-nav' ) ).toHaveCount( 1 );
    await expect( page.getByTestId( 'privacy.screen' ).locator( '.k-section-nav' ) ).toHaveCount( 0 );
} );

test( 'exactly one h1, and both cards are h2', async ( { page } ) => {
    await open( page );

    await expect( page.locator( 'main h1' ) ).toHaveCount( 1 );
    await expect( page.locator( 'main h1' ) ).toHaveText( /Privacy/i );

    // Two cards: Export requests · Erasure requests. The manifest's third card
    // (per-section method and status) is deferred — roadmap.md §0c.
    await expect( page.locator( 'main .k-card-heading' ) ).toHaveCount( 2 );
    await expect( page.locator( 'main .k-card-heading' ).nth( 0 ) ).toHaveText( /Export requests/i );
    await expect( page.locator( 'main .k-card-heading' ).nth( 1 ) ).toHaveText( /Erasure requests/i );
} );

test( 'the tabs are gone and neither card can retarget the other', async ( { page } ) => {
    await open( page );

    // The shipped screen was two tabs over one shared subject, so searching in
    // one silently re-pointed the other at a different person.
    await search( page, 'export', SUBJECT );
    await expect( page.getByTestId( 'privacy.export_subject' ) ).toContainText( SUBJECT );
    await expect( page.getByTestId( 'privacy.erasure_subject' ) ).toHaveCount( 0 );
} );

test( 'every search control has a visible label, not a placeholder', async ( { page } ) => {
    await open( page );

    for ( const card of [ 'export', 'erasure' ] ) {
        const field = page.getByTestId( `privacy.${ card }_query` );
        const id = await field.getAttribute( 'id' );

        await expect( page.locator( `label[for="${ id }"]` ) ).toBeVisible();
        // §4: "No placeholder-as-label anywhere in the admin." The shipped field
        // had a placeholder and no label at all.
        expect( await field.getAttribute( 'placeholder' ) ).toBeNull();
    }
} );

// ─── Export card ────────────────────────────────────────────────

test( 'an export lists the sections it will include, as a real table', async ( { page } ) => {
    await open( page );
    await search( page, 'export', SUBJECT );

    const table = page.getByTestId( 'privacy.export_sections' );
    await expect( table ).toBeVisible();

    // §2.1: the explicit role set, because a grid layout strips implicit roles.
    await expect( table ).toHaveAttribute( 'role', 'table' );
    await expect( table.locator( 'caption' ) ).toBeVisible();
    await expect( table.locator( 'thead' ) ).toHaveAttribute( 'role', 'rowgroup' );
    await expect( table.locator( 'tbody tr' ).first() ).toHaveAttribute( 'role', 'row' );
    // The column that names the record is a rowheader, never a cell.
    await expect( table.locator( 'tbody th[role="rowheader"]' ).first() ).toBeVisible();
} );

test( 'a search that matches nobody is a field error, not a bare banner', async ( { page } ) => {
    await open( page );
    await search( page, 'export', 'nobody-by-this-name' );

    await expect( page.getByTestId( 'privacy.error_summary' ) ).toBeVisible();
    await expect( page.getByTestId( 'privacy.export_query' ) ).toHaveAttribute( 'aria-invalid', 'true' );

    // The summary links to the field that failed, and the typed value survives.
    await expect( page.getByTestId( 'privacy.error_link.0' ) ).toHaveAttribute(
        'href',
        `#${ await page.getByTestId( 'privacy.export_query' ).getAttribute( 'id' ) }`
    );
    await expect( page.getByTestId( 'privacy.export_query' ) ).toHaveValue( 'nobody-by-this-name' );
} );

test( 'the JSON export downloads the subject and nobody else', async ( { page } ) => {
    await open( page );
    await search( page, 'export', SUBJECT );

    const [ download ] = await Promise.all( [
        page.waitForEvent( 'download' ),
        page.getByTestId( 'privacy.export_json' ).click(),
    ] );

    expect( download.suggestedFilename() ).toContain( SUBJECT );
} );

// ─── Erasure card — the two-step confirm ────────────────────────

test( 'erasing is two steps, and the first one changes nothing', async ( { page } ) => {
    await open( page );
    await search( page, 'erasure', SUBJECT );

    await page.getByTestId( 'privacy.section.core:form_submissions' ).check();
    await post( page, 'privacy.erase_arm' );

    // Armed: the confirm names how many sections, and the section list itself is
    // gone so nothing can be re-ticked between the two steps.
    await expect( page.getByTestId( 'privacy.erase_confirm' ) ).toBeVisible();
    await expect( page.getByTestId( 'privacy.erase_confirm' ) ).toContainText( '1' );
    await expect( page.getByTestId( 'privacy.erase_armed_summary' ) ).toContainText( 'Form Submissions' );
    await expect( page.getByTestId( 'privacy.erasure_results' ) ).toHaveCount( 0 );

    // Nothing was erased by arming: a FRESH GET still finds all three sections.
    await page.goto( URL_PATH );
    await search( page, 'erasure', SUBJECT );
    await expect( page.getByTestId( 'privacy.section.core:form_submissions' ) ).toBeVisible();
} );

test( 'the armed step is a real control, never a browser dialog', async ( { page } ) => {
    await open( page );
    await search( page, 'erasure', SUBJECT );

    let dialogs = 0;
    page.on( 'dialog', async ( dialog ) => {
        dialogs += 1;
        await dialog.dismiss();
    } );

    await page.getByTestId( 'privacy.section.core:audit_log' ).check();
    await post( page, 'privacy.erase_arm' );
    await post( page, 'privacy.erase_confirm' );

    // The shipped screen guarded this with confirm() and alert().
    expect( dialogs, 'the erasure opened a browser dialog' ).toBe( 0 );
    await expect( page.getByTestId( 'privacy.erasure_results' ) ).toBeVisible();
} );

// ─── The finding ────────────────────────────────────────────────

test( 'an erasure reports what it did, section by section', async ( { page } ) => {
    await open( page );
    await search( page, 'erasure', SUBJECT );

    await page.getByTestId( 'privacy.section.core:form_submissions' ).check();
    await post( page, 'privacy.erase_arm' );
    await post( page, 'privacy.erase_confirm' );

    await expect( page.getByTestId( 'privacy.status_line' ) ).toBeVisible();

    // THE DEFECT: this table never rendered on any install since it shipped.
    const results = page.getByTestId( 'privacy.erasure_results' );
    await expect( results ).toBeVisible();
    await expect( results.locator( 'tbody tr' ) ).toHaveCount( 1 );

    // It names the SECTION, not `core:form_submissions`.
    const row = results.locator( 'tbody tr' ).first();
    await expect( row.locator( 'th[role="rowheader"]' ) ).toHaveText( /Form Submissions/i );
    await expect( row.locator( 'th[role="rowheader"]' ) ).not.toContainText( 'core:' );

    // Every status has a WORD, never a bare key and never colour alone. The
    // manager returns `deleted` here and the catalogue had no such key.
    await expect( row.locator( 'td' ).first() ).toHaveText( /Deleted/i );
    await expect( row.locator( 'td' ).first() ).not.toContainText( 'privacy.' );

    // And the count is the two submissions the fixture wrote.
    await expect( row.locator( 'td.k-num' ) ).toHaveText( '2' );
} );

test( 'after an erasure the subject and what remains stay on screen', async ( { page } ) => {
    await open( page );
    await search( page, 'erasure', SUBJECT );

    await page.getByTestId( 'privacy.section.core:form_submissions' ).check();
    await post( page, 'privacy.erase_arm' );
    await post( page, 'privacy.erase_confirm' );

    // Erasing one section must not send you back to the search box to erase the
    // next. This is what re-resolving the subject after the erasure buys, and it
    // had no test until planting that line back left every other test green.
    await expect( page.getByTestId( 'privacy.erasure_subject' ) ).toContainText( SUBJECT );
    await expect( page.getByTestId( 'privacy.section.core:audit_log' ) ).toBeVisible();
    await expect( page.getByTestId( 'privacy.section.core:form_submissions' ) ).toHaveCount( 0 );
} );

test( 'the erasure really happened, checked with a fresh GET', async ( { page } ) => {
    await open( page );
    await search( page, 'erasure', SUBJECT );

    await page.getByTestId( 'privacy.section.core:form_submissions' ).check();
    await post( page, 'privacy.erase_arm' );
    await post( page, 'privacy.erase_confirm' );

    // A fresh GET, never page.reload() — reloading a POST re-submits it, so the
    // check would pass whether or not anything was stored (D-088).
    await page.goto( URL_PATH );
    await search( page, 'erasure', SUBJECT );

    await expect( page.getByTestId( 'privacy.section.core:form_submissions' ) ).toHaveCount( 0 );
    await expect( page.getByTestId( 'privacy.section.core:audit_log' ) ).toBeVisible();
} );

test( 'a partial erasure says which section was retained and why', async ( { page } ) => {
    await open( page );

    // The owner's account cannot be erased, and the product says so per section
    // rather than by hiding the row.
    await search( page, 'erasure', 'owner' );

    await expect( page.getByTestId( 'privacy.section_retained.core:user_account' ) ).toBeVisible();
    await expect( page.getByTestId( 'privacy.section_retained.core:user_account' ) )
        .toContainText( /owner/i );

    // With nothing erasable there is no destructive control at all.
    await expect( page.getByTestId( 'privacy.erasure_empty' ) ).toBeVisible();
    await expect( page.getByTestId( 'privacy.erase_arm' ) ).toHaveCount( 0 );
} );

test( 'erasing nothing is a form error, not an alert and not a no-op', async ( { page } ) => {
    await open( page );
    await search( page, 'erasure', SUBJECT );

    let dialogs = 0;
    page.on( 'dialog', async ( dialog ) => {
        dialogs += 1;
        await dialog.dismiss();
    } );

    await post( page, 'privacy.erase_arm' );

    expect( dialogs, 'the empty selection opened a browser dialog' ).toBe( 0 );
    await expect( page.getByTestId( 'privacy.error_summary' ) ).toBeVisible();
    await expect( page.getByTestId( 'privacy.section.core:form_submissions' ) )
        .toHaveAttribute( 'aria-invalid', 'true' );
    // It did NOT arm.
    await expect( page.getByTestId( 'privacy.erase_confirm' ) ).toHaveCount( 0 );
} );

// ─── JavaScript disabled ────────────────────────────────────────

test.describe( 'with JavaScript disabled', () => {
    test.use( { javaScriptEnabled: false } );

    test( 'search, arm and confirm all work as plain posts', async ( { page } ) => {
        // `beforeEach` already logged in — through the real form, with scripting
        // off, which is itself worth knowing.
        await page.goto( URL_PATH );

        await search( page, 'erasure', SUBJECT );
        await page.getByTestId( 'privacy.section.core:form_submissions' ).check();
        await post( page, 'privacy.erase_arm' );
        await expect( page.getByTestId( 'privacy.erase_confirm' ) ).toBeVisible();

        await post( page, 'privacy.erase_confirm' );
        await expect( page.getByTestId( 'privacy.erasure_results' ) ).toBeVisible();
    } );
} );

// ─── Accessibility ──────────────────────────────────────────────

for ( const theme of [ 'dark', 'light' ] ) {
    test( `axe — the whole page at rest, ${ theme }`, async ( { page } ) => {
        await open( page, theme );
        expect( ( await scan( page ) ).violations ).toEqual( [] );
    } );

    test( `axe — the whole page populated, ${ theme }`, async ( { page } ) => {
        await open( page, theme );
        await search( page, 'export', SUBJECT );
        await search( page, 'erasure', SUBJECT );
        expect( ( await scan( page ) ).violations ).toEqual( [] );
    } );

    test( `axe — the whole page in the error state, ${ theme }`, async ( { page } ) => {
        await open( page, theme );
        await search( page, 'export', 'nobody-by-this-name' );
        expect( ( await scan( page ) ).violations ).toEqual( [] );
    } );

    test( `axe — the whole page showing an erasure result, ${ theme }`, async ( { page } ) => {
        await open( page, theme );
        await search( page, 'erasure', SUBJECT );
        await page.getByTestId( 'privacy.section.core:audit_log' ).check();
        await post( page, 'privacy.erase_arm' );
        await post( page, 'privacy.erase_confirm' );
        expect( ( await scan( page ) ).violations ).toEqual( [] );
    } );
}
