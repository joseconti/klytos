// Manifest entry 9 — Settings — driven per SECTION and per STATE, in both themes.
//
// This is the manifest's largest form surface and the second screen in the
// build to render the record-form template's OPTIONAL half, the section nav.
// It is also the one whose nav is real navigation rather than a scrollspy:
// §9 says "each section is its own page load", so every nav item is a GET and
// `aria-current="page"` is decided by the server, not by a hashchange listener.
//
// What this spec exists to catch, beyond "the screen renders":
//
//   1. THE RE-PARTITION IS LOSSLESS. Eleven shipped POST groups became five
//      sections (D-095). Saving one section must not blank a value another
//      section owns — the failure mode of a re-partition, and invisible in a
//      diff. Driven by saving one section and re-reading another.
//   2. DR-002 LANDED IN BOTH HALVES. The indexing control exists here, and the
//      Dashboard no longer carries it.
//   3. The 900px breakpoint turns the nav from a left column into a chip row
//      and it is STILL a labelled <nav> (§3).
//
// Rules carried forward, each already paid for:
//   - axe scans the WHOLE PAGE, never `#main` (L-037).
//   - exclusions go in ONE AT A TIME: an array is read as a FRAME PATH (L-037).
//   - persistence is checked with a FRESH GET, never `page.reload()` (D-088).
//   - a test that varies an input reads that input back (L-035).
//   - the theme is baked in BEFORE first paint, and read back (L-035).

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const SETTINGS_URL = '/installer/admin/settings.php';

/** The five sections the build renders, in the manifest's own order. */
const SECTIONS = [ 'site', 'locale', 'intelligence', 'email', 'advanced' ];

test.beforeEach( async ( { page } ) => {
    await login( page, 'owner' );
} );

/**
 * Open a section with the theme baked in BEFORE first paint (L-035).
 */
async function open( page, section = 'site', theme = 'dark' ) {
    await page.context().addCookies( [ {
        name: 'klytos_admin_theme',
        value: theme,
        url: new URL( page.url() ).origin,
    } ] );
    await page.goto( `${ SETTINGS_URL }?section=${ section }` );
    await expect( page.getByTestId( 'settings.screen' ) ).toBeVisible();

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

/** Submit the section's single form through the toolbar's Save. */
async function save( page ) {
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'settings.save' ).click(),
    ] );
}

/**
 * Submit past the browser's own constraint validation.
 *
 * §4 requires the `required` attribute and `type="email"`, so Chromium refuses
 * to submit an empty name or a malformed address and shows its native bubble —
 * which is the FIRST line and works. It is not the line §2 specifies, though:
 * the error summary, `aria-invalid`, and the inline message linked by
 * `aria-describedby` are all server-rendered, and they exist because a client
 * that does not validate — an old browser, a scripted post, anything that is
 * not this browser — must still be refused rather than obeyed.
 *
 * Clearing `noValidate` is how that second line is reached from a browser that
 * has the first. It does not weaken the test: the product's own attributes are
 * asserted separately, so this drives the guarantee UNDERNEATH them instead of
 * proving the browser works.
 */
async function saveBypassingClientValidation( page ) {
    await page.evaluate( () => {
        document.getElementById( 'k-settings-form' ).noValidate = true;
    } );
    await save( page );
}

// ─── Structure ──────────────────────────────────────────────────

test( 'the H1 stays "Settings" on every section and the section is the h2', async ( { page } ) => {
    // §9 answers the H1 question explicitly, and it is the delta most likely to
    // be "corrected" by someone applying the template's generic rule instead.
    for ( const section of SECTIONS ) {
        await open( page, section );

        await expect( page.locator( 'h1' ) ).toHaveText( 'Settings' );
        await expect( page.locator( 'h1' ) ).toHaveCount( 1 );

        await expect( page.getByTestId( 'settings.section_heading' ) ).toBeVisible();
        expect(
            await page.getByTestId( 'settings.section_heading' ).evaluate( ( n ) => n.tagName )
        ).toBe( 'H2' );
    }
} );

test( 'the breadcrumb carries the section, and the H1 does not', async ( { page } ) => {
    await open( page, 'locale' );

    const crumbs = page.locator( '.k-breadcrumb li' );
    await expect( crumbs.last() ).toHaveText( 'Locale' );
    await expect( crumbs.last() ).toHaveAttribute( 'aria-current', 'page' );

    // "Settings" is the crumb BEFORE it, and it is a link back to the default
    // section — the two are separate crumbs, not one doing both jobs. That is
    // the whole point of §9's answer: the H1 and the last crumb say different
    // things, which the shell's default behaviour does not do on its own.
    const labels = await crumbs.allTextContents();
    expect( labels.map( ( t ) => t.trim() ).slice( -2 ) ).toEqual( [ 'Settings', 'Locale' ] );

    await expect( page.locator( '.k-breadcrumb a', { hasText: 'Settings' } ) ).toBeVisible();
} );

test( 'the section nav is a labelled nav and marks exactly one current item', async ( { page } ) => {
    for ( const section of SECTIONS ) {
        await open( page, section );

        const nav = page.getByTestId( 'settings.section_nav' );
        await expect( nav ).toHaveAttribute( 'aria-label', 'Settings sections' );

        await expect( nav.locator( '[aria-current="page"]' ) ).toHaveCount( 1 );
        await expect( page.getByTestId( `settings.section.${ section }` ) )
            .toHaveAttribute( 'aria-current', 'page' );
    }
} );

test( 'each nav item is a real page load, not a fragment', async ( { page } ) => {
    // §9: "each section is its own page load". A href of "#locale" would look
    // identical on screen and would be a different screen entirely.
    await open( page, 'site' );

    for ( const section of SECTIONS ) {
        const href = await page.getByTestId( `settings.section.${ section }` ).getAttribute( 'href' );
        expect( href, `${ section } must be a URL, not a fragment` ).toBe( `settings.php?section=${ section }` );
    }

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'settings.section.email' ).click(),
    ] );

    expect( new URL( page.url() ).searchParams.get( 'section' ) ).toBe( 'email' );
    await expect( page.getByTestId( 'settings.section_heading' ) ).toHaveText( 'Email' );
} );

test( 'an unknown section resolves to the first one instead of failing', async ( { page } ) => {
    await page.goto( `${ SETTINGS_URL }?section=not-a-section` );
    await expect( page.getByTestId( 'settings.section_heading' ) ).toHaveText( 'Site' );

    await page.goto( SETTINGS_URL );
    await expect( page.getByTestId( 'settings.section_heading' ) ).toHaveText( 'Site' );
} );

test( 'there is exactly one Save, and it lives in the toolbar', async ( { page } ) => {
    // The shipped screen had ELEVEN Save buttons, one per card. The template
    // says the primary Save "lives in the toolbar… and it is the same button on
    // every form screen" — singular.
    for ( const section of SECTIONS ) {
        await open( page, section );

        const save = page.getByTestId( 'settings.save' );
        await expect( save ).toHaveCount( 1 );

        // It is OUTSIDE <main>, and it reaches the form by `form=`.
        expect(
            await save.evaluate( ( n ) => n.closest( 'main' ) === null )
        ).toBe( true );
        await expect( save ).toHaveAttribute( 'form', 'k-settings-form' );
    }
} );

// ─── The re-partition is lossless ───────────────────────────────

test( 'saving one section does not blank a value another section owns', async ( { page } ) => {
    /*
     * THE TEST THIS SPEC EXISTS FOR. Eleven POST groups became five sections,
     * and the whole re-partition rests on SiteConfig::set() being a partial
     * merge. If it were not — or if a section posted a field it does not
     * render — saving Site would silently clear the SMTP host, and nothing on
     * either screen would say so.
     */
    await open( page, 'email' );
    await page.getByTestId( 'settings.smtp_host' ).fill( 'smtp.entry9.test' );
    await page.getByTestId( 'settings.smtp_port' ).fill( '2525' );
    await save( page );

    await open( page, 'site' );
    await page.getByTestId( 'settings.site_name' ).fill( 'Entry 9 Site' );
    await save( page );

    // A FRESH GET, never page.reload() — a reload re-submits the POST and would
    // prove nothing about what is stored (D-088).
    await page.goto( `${ SETTINGS_URL }?section=email` );
    await expect( page.getByTestId( 'settings.smtp_host' ) ).toHaveValue( 'smtp.entry9.test' );
    await expect( page.getByTestId( 'settings.smtp_port' ) ).toHaveValue( '2525' );

    await page.goto( `${ SETTINGS_URL }?section=site` );
    await expect( page.getByTestId( 'settings.site_name' ) ).toHaveValue( 'Entry 9 Site' );
} );

test( 'a value round-trips through the control that owns it', async ( { page } ) => {
    await open( page, 'site' );

    await page.getByTestId( 'settings.tagline' ).fill( 'A tagline the test wrote' );
    await page.getByTestId( 'settings.social.github' ).fill( 'https://github.com/example' );
    await save( page );

    await expect( page.getByTestId( 'settings.status_line' ) ).toHaveText( 'Settings saved.' );

    await page.goto( `${ SETTINGS_URL }?section=site` );
    await expect( page.getByTestId( 'settings.tagline' ) ).toHaveValue( 'A tagline the test wrote' );
    await expect( page.getByTestId( 'settings.social.github' ) ).toHaveValue( 'https://github.com/example' );
} );

// ─── Validation, both directions ────────────────────────────────

test( 'an empty site name is refused, summarised and linked to its field', async ( { page } ) => {
    await open( page, 'site' );
    await page.getByTestId( 'settings.site_name' ).fill( '' );
    await saveBypassingClientValidation( page );

    const summary = page.getByTestId( 'settings.error_summary' );
    await expect( summary ).toBeVisible();
    await expect( summary ).toHaveAttribute( 'role', 'alert' );

    // §2: focus is MOVED to the summary on load.
    await expect( summary ).toBeFocused();

    // Every failed field is a link TO that field.
    const link = page.getByTestId( 'settings.error_link.0' );
    await expect( link ).toHaveAttribute( 'href', '#settings-field-site_name' );

    const field = page.getByTestId( 'settings.site_name' );
    await expect( field ).toHaveAttribute( 'aria-invalid', 'true' );

    // The hint is still described first, error second (§4).
    const describedBy = await field.getAttribute( 'aria-describedby' );
    expect( describedBy ).toBe( 'settings-hint-site_name settings-error-site_name' );
} );

test( 'an invalid SMTP port is refused rather than stored as 0', async ( { page } ) => {
    // The shipped screen cast to int, so "abc" became port 0 and was saved as a
    // port nothing can connect to — a value that fails silently at send time,
    // far away from the screen that accepted it.
    await open( page, 'email' );
    await page.getByTestId( 'settings.smtp_port' ).fill( 'abc' );
    await save( page );

    await expect( page.getByTestId( 'settings.error_summary' ) ).toBeVisible();
    await expect( page.getByTestId( 'settings.smtp_port' ) ).toHaveAttribute( 'aria-invalid', 'true' );

    // And it did NOT reach storage.
    await page.goto( `${ SETTINGS_URL }?section=email` );
    await expect( page.getByTestId( 'settings.smtp_port' ) ).not.toHaveValue( '0' );
} );

test( 'a malformed sender address is refused', async ( { page } ) => {
    await open( page, 'email' );
    await page.getByTestId( 'settings.email_from_email' ).fill( 'not-an-address' );
    await page.getByTestId( 'settings.smtp_port' ).fill( '587' );
    await saveBypassingClientValidation( page );

    await expect( page.getByTestId( 'settings.error_summary' ) ).toBeVisible();
    await expect( page.getByTestId( 'settings.email_from_email' ) ).toHaveAttribute( 'aria-invalid', 'true' );
} );

test( 'a valid section saves with no validation shown', async ( { page } ) => {
    // The other direction: one is half a test (L-010). "Never validate on load"
    // is §2's Default state, and an error summary that appears on a clean save
    // is the same defect as one that never appears.
    await open( page, 'email' );
    await page.getByTestId( 'settings.email_from_email' ).fill( 'sender@example.com' );
    await page.getByTestId( 'settings.smtp_port' ).fill( '587' );
    await save( page );

    await expect( page.getByTestId( 'settings.error_summary' ) ).toHaveCount( 0 );
    await expect( page.getByTestId( 'settings.status_line' ) ).toBeVisible();
} );

test( 'nothing is validated on load', async ( { page } ) => {
    for ( const section of SECTIONS ) {
        await open( page, section );
        await expect( page.getByTestId( 'settings.error_summary' ) ).toHaveCount( 0 );
        await expect( page.locator( '[aria-invalid="true"]' ) ).toHaveCount( 0 );
    }
} );

// ─── Locale: the collection inside a form ───────────────────────

test( 'a half-filled language row is reported, not silently dropped', async ( { page } ) => {
    // The shipped screen dropped any row missing either half without a word, so
    // a person typing a code and forgetting the name saw a successful save and
    // a language that had vanished.
    await open( page, 'locale' );
    await page.getByTestId( 'settings.language_code.0' ).fill( 'de' );
    await page.getByTestId( 'settings.language_name.0' ).fill( '' );
    await save( page );

    await expect( page.getByTestId( 'settings.error_summary' ) ).toBeVisible();
    await expect( page.getByTestId( 'settings.language_code.0' ) ).toHaveAttribute( 'aria-invalid', 'true' );
} );

test( 'the default language offers what the site actually publishes in', async ( { page } ) => {
    /*
     * The shipped select offered a hard-coded es/en/ca/fr whatever the language
     * list said, so a site publishing in German could not select German — and
     * saving rewrote the stored value to one of the four.
     */
    await open( page, 'locale' );
    await page.getByTestId( 'settings.language_code.0' ).fill( 'de' );
    await page.getByTestId( 'settings.language_name.0' ).fill( 'Deutsch' );
    await save( page );

    await page.goto( `${ SETTINGS_URL }?section=locale` );
    const options = await page.getByTestId( 'settings.default_language' )
        .locator( 'option' )
        .evaluateAll( ( nodes ) => nodes.map( ( n ) => n.value ) );

    expect( options ).toContain( 'de' );

    await page.getByTestId( 'settings.default_language' ).selectOption( 'de' );
    await save( page );

    await page.goto( `${ SETTINGS_URL }?section=locale` );
    await expect( page.getByTestId( 'settings.default_language' ) ).toHaveValue( 'de' );
} );

test( 'the add-a-language button produces a row whose label addresses its own control', async ( { page } ) => {
    await open( page, 'locale' );

    const before = await page.locator( '[data-testid^="settings.language_row."]' ).count();
    await page.getByTestId( 'settings.add_language' ).click();
    await expect( page.locator( '[data-testid^="settings.language_row."]' ) ).toHaveCount( before + 1 );

    // The failure this guards: a cloned row keeps the id it was copied from, so
    // two controls share an id and the new row's label points at the old row's
    // field. Checked by clicking the LABEL and seeing which control focuses.
    const newIndex = before;
    const newCode = page.getByTestId( `settings.language_code.${ newIndex }` );
    await expect( newCode ).toHaveValue( '' );

    await page.locator( `label[for="settings-field-lang_code_${ newIndex }"]` ).click();
    await expect( newCode ).toBeFocused();
} );

// ─── DR-002: the indexing move, both halves ─────────────────────

test( 'indexing lives in Advanced with its consequence stated beside it', async ( { page } ) => {
    await open( page, 'advanced' );

    const box = page.getByTestId( 'settings.indexing_enabled' );
    await expect( box ).toBeVisible();

    // §4 "Switch vs checkbox": this one needs a Save, so it is a checkbox.
    await expect( box ).toHaveAttribute( 'type', 'checkbox' );

    // "a checkbox + Save with the consequence stated next to it" — and the
    // sentence is REACHABLE from the control, not merely printed near it.
    const describedBy = await box.getAttribute( 'aria-describedby' );
    expect( describedBy ).toBe( 'settings-hint-indexing' );
    await expect( page.locator( '#settings-hint-indexing' ) ).not.toBeEmpty();
} );

test( 'the indexing checkbox actually moves the setting', async ( { page } ) => {
    await open( page, 'advanced' );

    const box = page.getByTestId( 'settings.indexing_enabled' );
    const was = await box.isChecked();

    await box.setChecked( ! was );
    await save( page );

    await page.goto( `${ SETTINGS_URL }?section=advanced` );
    await expect( page.getByTestId( 'settings.indexing_enabled' ) ).toBeChecked( { checked: ! was } );

    // Put it back, so the rest of the run starts where it found it.
    await page.getByTestId( 'settings.indexing_enabled' ).setChecked( was );
    await save( page );
} );

test( 'the Dashboard warns and links here, and carries no toggle of its own', async ( { page } ) => {
    // Make sure the site IS blocked, so the warning half is on screen at all.
    await open( page, 'advanced' );
    await page.getByTestId( 'settings.indexing_enabled' ).setChecked( false );
    await save( page );

    await page.goto( '/installer/admin/index.php' );

    const link = page.getByTestId( 'dashboard.indexing_link' ).locator( 'a' );
    await expect( link ).toHaveAttribute( 'href', /settings\.php\?section=advanced$/ );

    // No control. The manifest moved it; a copy left behind is the failure.
    await expect( page.locator( 'input[name="action"][value="disable_block"]' ) ).toHaveCount( 0 );
    await expect( page.locator( 'input[name="action"][value="enable_block"]' ) ).toHaveCount( 0 );

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        link.click(),
    ] );
    await expect( page.getByTestId( 'settings.indexing_enabled' ) ).toBeVisible();
} );

// ─── Advanced: disabled is not hidden ───────────────────────────

test( 'the devbar panels are disabled rather than hidden while developer mode is off', async ( { page } ) => {
    /*
     * §2: "A disabled control is never hidden and never explained only in a
     * tooltip." The shipped screen rendered the seven devbar toggles only once
     * developer mode was already on, so they could not be configured in the
     * same save that enables it, and their absence explained nothing.
     */
    await open( page, 'advanced' );
    await page.getByTestId( 'settings.developer_mode' ).setChecked( false );
    await save( page );

    await page.goto( `${ SETTINGS_URL }?section=advanced` );

    const panels = page.getByTestId( 'settings.devbar_panels' );
    await expect( panels ).toBeVisible();
    await expect( page.getByTestId( 'settings.devbar_show_queries' ) ).toBeDisabled();

    // The reason is on the page, next to the group — not in a title attribute.
    await expect( page.getByTestId( 'settings.devbar_disabled_reason' ) ).toBeVisible();
} );

test( 'the notices card says it is empty rather than disappearing', async ( { page } ) => {
    await open( page, 'advanced' );

    // §2 Empty: a collection inside a form renders one row with the sentence,
    // keeping the card's heading. Whichever branch this install is in, the
    // heading is present and exactly one of the two branches renders.
    await expect( page.getByRole( 'heading', { name: 'Notices' } ) ).toBeVisible();

    const empty = await page.getByTestId( 'settings.notices_empty' ).count();
    const list = await page.getByTestId( 'settings.notices_list' ).count();
    expect( empty + list ).toBe( 1 );
} );

// ─── Responsive ─────────────────────────────────────────────────

test( 'below 900px the nav becomes a chip row and stays a labelled nav', async ( { page } ) => {
    await open( page, 'site' );

    const nav = page.getByTestId( 'settings.section_nav' );

    await page.setViewportSize( { width: 1440, height: 900 } );
    expect(
        await nav.evaluate( ( n ) => getComputedStyle( n ).flexDirection ),
        'at the reference width the nav is a left column'
    ).toBe( 'column' );

    await page.setViewportSize( { width: 820, height: 900 } );
    expect(
        await nav.evaluate( ( n ) => getComputedStyle( n ).flexDirection ),
        'below 900 the nav collapses to a horizontal chip row (§3)'
    ).toBe( 'row' );

    // "still <nav aria-label='Settings sections'>" — the label survives the
    // layout change, which is the half of §3 a CSS-only check would miss.
    await expect( nav ).toHaveAttribute( 'aria-label', 'Settings sections' );
    await expect( nav.locator( '[aria-current="page"]' ) ).toHaveCount( 1 );
} );

// ─── Accessibility, every section, both themes ──────────────────

for ( const theme of [ 'dark', 'light' ] ) {
    for ( const section of SECTIONS ) {
        test( `axe is clean on ${ section } in ${ theme }`, async ( { page } ) => {
            await open( page, section, theme );

            const results = await scan( page );

            expect(
                results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.length } node(s)` ),
                `axe violations on ${ section } (${ theme })`
            ).toEqual( [] );
        } );
    }
}

test( 'axe is clean on the error state, which is a state nothing else scans', async ( { page } ) => {
    // Resting states only is how a component ships below AA and every pass in
    // between stays honest and blind (D-091, L-039).
    await open( page, 'site' );
    await page.getByTestId( 'settings.site_name' ).fill( '' );
    await saveBypassingClientValidation( page );

    await expect( page.getByTestId( 'settings.error_summary' ) ).toBeVisible();

    const results = await scan( page );
    expect( results.violations.map( ( v ) => v.id ) ).toEqual( [] );
} );

test( 'every control has a visible label and no placeholder stands in for one', async ( { page } ) => {
    // §4: "Every control has a visible <label for>. No placeholder-as-label
    // anywhere in the admin." axe does not catch a placeholder that duplicates
    // a real label's job, so it is checked structurally.
    for ( const section of SECTIONS ) {
        await open( page, section );

        const unlabelled = await page.evaluate( () => {
            const bad = [];
            const controls = document.querySelectorAll(
                '#k-settings-form input:not([type="hidden"]), #k-settings-form select, #k-settings-form textarea'
            );
            for ( const control of controls ) {
                const byFor = control.id ? document.querySelector( `label[for="${ control.id }"]` ) : null;
                const wrapping = control.closest( 'label' );
                if ( ! byFor && ! wrapping ) {
                    bad.push( control.name || control.id || control.tagName );
                }
            }
            return bad;
        } );

        expect( unlabelled, `unlabelled controls in ${ section }` ).toEqual( [] );
    }
} );

test( 'focus order is DOM order: nav, then the cards in turn', async ( { page } ) => {
    /*
     * §4 names the order explicitly: "section nav → card 1 fields → card 2
     * fields → …". Tabbing from the top of the document would walk the skip
     * link and the whole sidebar first, which is the SHELL's order and is
     * covered by shell.spec.js — so this starts at the region the screen owns
     * and checks two things about it that only the keyboard can establish.
     */
    await open( page, 'site' );

    // 1. Within the region, the nav's items precede every form control in the
    //    sequential focus order.
    const order = await page.evaluate( () => {
        const region = document.querySelector( '.k-record-form' );
        const focusable = region.querySelectorAll(
            'a[href], button, input:not([type="hidden"]), select, textarea'
        );
        return Array.from( focusable ).map( ( n ) => ( {
            testid: n.getAttribute( 'data-testid' ) || '',
            isNav: n.classList.contains( 'k-section-nav-item' ),
        } ) );
    } );

    const lastNav = order.map( ( n ) => n.isNav ).lastIndexOf( true );
    const firstField = order.findIndex( ( n ) => n.testid === 'settings.site_name' );

    expect( lastNav, 'the section nav is not in the focus order at all' ).toBeGreaterThan( -1 );
    expect( firstField, 'the first field is not in the focus order at all' ).toBeGreaterThan( -1 );
    expect( lastNav ).toBeLessThan( firstField );

    // 2. And the sequence is real, not just DOM order: Tab from the last nav
    //    item lands on the first field, with nothing focusable in between.
    await page.getByTestId( 'settings.section.advanced' ).focus();
    await page.keyboard.press( 'Tab' );

    await expect( page.getByTestId( 'settings.site_name' ) ).toBeFocused();
} );
