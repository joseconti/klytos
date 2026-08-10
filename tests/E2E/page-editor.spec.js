// Manifest entry 2 — Page editor — the CHROME, driven per state.
//
// Stage 6 of 6. What this spec does NOT assert is as deliberate as what it
// does: the canvas interior — blocks as bordered cards, their `role="group"`
// names, their `contenteditable` regions, the "Edit as form" fallback and the
// two hard publish blockers — is Gutenberg's or TinyMCE's own DOM, or product
// that exists nowhere, and is deferred with its reason in `roadmap.md` §0c
// (D-104). Asserting it here would be asserting somebody else's markup.
//
// Everything AROUND it is this build's and is driven: the shell the screen
// used to hide outright, the toolbar's save state and its two actions, the
// canvas URL line, the inspector and its disclosure sections, the sheet modes,
// and the states the product can actually reach.

const { test, expect, login, KNOWN_DELIVERY_GAPS, DEV_ONLY_SURFACES } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;

const EDITOR_URL = '/installer/admin/page-editor.php';
const SEED_SLUG = 'home';

test.beforeEach( async ( { page } ) => {
    await login( page, 'owner' );
} );

/**
 * Open the editor with the theme baked in BEFORE the first paint (L-035): a
 * colour read mid-`transition` is not the colour, and a cookie the shell does
 * not read makes every "light" run measure dark.
 *
 * @param {import('@playwright/test').Page} page
 * @param {object} options slug (null = the create form) and theme.
 */
async function open( page, { slug = SEED_SLUG, theme = 'dark' } = {} ) {
    await page.context().addCookies( [ {
        name: 'klytos_admin_theme',
        value: theme,
        url: new URL( page.url() ).origin,
    } ] );
    await page.goto( slug ? `${ EDITOR_URL }?slug=${ encodeURIComponent( slug ) }` : EDITOR_URL );
    await expect( page.locator( 'h1' ) ).toBeVisible();

    expect(
        await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
        'the theme cookie did not take — this run measured the wrong theme'
    ).toBe( theme );
}

/**
 * An axe pass over the WHOLE page, never `#main` (L-037): the shell is exactly
 * what `#main` does not contain, and DR-005's fourth pair lived there unseen
 * for four stages because of it.
 *
 * The known gaps are applied ONE AT A TIME. `exclude()` reads an array as a
 * CHAINED selector, so `exclude( KNOWN_DELIVERY_GAPS )` matches nothing and
 * excludes nothing — silently, and in the safe-LOOKING direction.
 */
async function scan( page ) {
    let builder = new AxeBuilder( { page } ).withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] );
    for ( const selector of [ ...KNOWN_DELIVERY_GAPS, ...DEV_ONLY_SURFACES ] ) {
        builder = builder.exclude( selector );
    }
    const result = await builder.analyze();
    return result.violations;
}

// ─── The shell this screen used to hide ──────────────────────────────

test( 'the screen keeps the shell: sidebar, toolbar and status bar are all rendered', async ( { page } ) => {
    await open( page );

    // Until stage 6 this page shipped `.k-sidebar { display: none !important }`
    // and three more like it, so the primary navigation, the breadcrumb and the
    // status bar were absent from the one screen a person spends longest on.
    await expect( page.locator( '.k-sidebar' ) ).toBeVisible();
    await expect( page.locator( '.k-toolbar' ) ).toBeVisible();
    await expect( page.locator( '.k-statusbar' ) ).toBeVisible();
} );

test( 'the H1 is the record being edited, not the word Editor, and there is exactly one', async ( { page } ) => {
    await open( page );

    const headings = page.locator( 'h1' );
    await expect( headings ).toHaveCount( 1 );

    const title = ( await page.getByTestId( 'editor.title' ).inputValue() ).trim();
    expect( title ).not.toBe( '' );
    await expect( headings ).toHaveText( title );

    // template-shell.md §1: the last crumb repeats it, is aria-current and is
    // NOT a link.
    const crumb = page.locator( '.k-breadcrumb li[aria-current="page"]' );
    await expect( crumb ).toHaveText( title );
    await expect( crumb.locator( 'a' ) ).toHaveCount( 0 );
} );

test( 'an unsaved record still has a heading, and it is the action rather than an empty string', async ( { page } ) => {
    await open( page, { slug: null } );

    const heading = ( await page.locator( 'h1' ).textContent() ).trim();
    expect( heading ).not.toBe( '' );
    expect( await page.getByTestId( 'editor.title' ).inputValue() ).toBe( '' );
} );

test( 'the page editor still highlights Pages in the nav (navigation.md §5 parentage)', async ( { page } ) => {
    await open( page );
    const current = page.locator( '.k-nav a[aria-current="page"]' );
    await expect( current ).toHaveCount( 1 );
    await expect( current ).toHaveAttribute( 'href', /pages\.php/ );
} );

// ─── §1 Canvas ────────────────────────────────────────────────────────

test( 'the canvas is a labelled section and the URL line is a real form control', async ( { page } ) => {
    await open( page );

    const canvas = page.getByTestId( 'editor.canvas' );
    await expect( canvas ).toHaveAttribute( 'aria-label', /.+/ );

    // §4: "The URL/slug line is a real form control with a visible label, not
    // an inline-editable span." It was a hidden input before this stage, so
    // the slug could not be edited on this screen at all.
    const slug = page.getByTestId( 'editor.slug' );
    await expect( slug ).toBeVisible();
    await expect( slug ).toHaveJSProperty( 'type', 'text' );

    const labelId = await slug.evaluate( ( el ) => {
        const label = el.labels && el.labels[ 0 ];
        return label ? label.textContent.trim() : '';
    } );
    expect( labelId ).not.toBe( '' );
} );

test( 'the engine mounts inside the canvas and nothing else claims that node', async ( { page } ) => {
    await open( page );
    await expect( page.getByTestId( 'editor.canvas' ).locator( '#klytos-editor-container' ) ).toHaveCount( 1 );
} );

// ─── §1 and §2 Inspector ─────────────────────────────────────────────

test( 'the inspector is a labelled aside showing document properties, never blank', async ( { page } ) => {
    await open( page );

    const inspector = page.getByTestId( 'editor.inspector' );
    await expect( inspector ).toBeVisible();
    expect( await inspector.evaluate( ( el ) => el.tagName ) ).toBe( 'ASIDE' );
    await expect( inspector ).toHaveAttribute( 'aria-label', /.+/ );

    // §2 "Empty — no selection in the inspector — never blank: document
    // properties, as above." The screen used to answer this with a second tab
    // whose only content was "Select a block to see its settings".
    await expect( inspector.getByTestId( 'editor.title' ) ).toBeVisible();
    await expect( inspector.getByTestId( 'editor.template' ) ).toBeVisible();
    await expect( inspector.getByTestId( 'editor.lang' ) ).toBeVisible();
} );

test( 'inspector sections are h3 disclosures whose button controls its own panel', async ( { page } ) => {
    await open( page );

    const toggles = page.locator( '.k-inspector-toggle' );
    const count = await toggles.count();
    expect( count ).toBeGreaterThan( 3 );

    for ( let i = 0; i < count; i++ ) {
        const toggle = toggles.nth( i );
        // §4: "Inspector sections are <h3> + aria-expanded disclosure buttons
        // controlling their panel."
        expect( await toggle.evaluate( ( el ) => el.parentElement.tagName ) ).toBe( 'H3' );
        const panelId = await toggle.getAttribute( 'aria-controls' );
        await expect( page.locator( `#${ panelId }` ) ).toHaveCount( 1 );
    }

    const first = toggles.first();
    const panel = page.locator( `#${ await first.getAttribute( 'aria-controls' ) }` );
    await expect( first ).toHaveAttribute( 'aria-expanded', 'true' );
    await expect( panel ).toBeVisible();

    await first.click();
    await expect( first ).toHaveAttribute( 'aria-expanded', 'false' );
    await expect( panel ).toBeHidden();

    await first.click();
    await expect( first ).toHaveAttribute( 'aria-expanded', 'true' );
    await expect( panel ).toBeVisible();
} );

// ─── template-shell.md §1 — the toolbar's bound ──────────────────────

test( 'the toolbar carries at most two actions, secondary then primary', async ( { page } ) => {
    await open( page );

    // The inspector trigger is §3's own control and ships hidden above 1199,
    // so at the reference width the actions region holds the two status
    // buttons and nothing else. "NEVER three."
    const actions = page.locator( '.k-toolbar-actions button:visible, .k-toolbar-actions a:visible' );
    await expect( actions ).toHaveCount( 2 );

    const classes = await actions.evaluateAll( ( els ) => els.map( ( e ) => e.className ) );
    expect( classes[ 0 ] ).toContain( 'k-btn--secondary' );
    expect( classes[ 1 ] ).toContain( 'k-btn--primary' );
} );

test( 'the toolbar Save submits a form it sits outside of, with no JavaScript involved', async ( { page } ) => {
    await open( page );
    const primary = page.locator( '.k-toolbar-actions .k-btn--primary' );
    await expect( primary ).toHaveAttribute( 'form', 'k-page-editor-form' );
} );

// ─── §2 Save state ───────────────────────────────────────────────────

test( 'a saved record shows its resting save state in the toolbar', async ( { page } ) => {
    await open( page );

    const state = page.getByTestId( 'shell.save_state' );
    await expect( state ).toBeVisible();
    await expect( state ).toHaveAttribute( 'aria-busy', 'false' );
    expect( ( await state.textContent() ).trim() ).not.toBe( '' );
} );

test( 'a record that has never been saved claims the slot without inventing a time', async ( { page } ) => {
    await open( page, { slug: null } );
    // Attached, not visible: an empty span has no box. The slot exists so the
    // script has somewhere to write, and it says nothing because nothing has
    // been saved — inventing a time here would be the defect.
    const state = page.getByTestId( 'shell.save_state' );
    await expect( state ).toBeAttached();
    expect( ( await state.textContent() ).trim() ).toBe( '' );
} );

test( 'the autosave alert is in the DOM and does not paint until it is revealed', async ( { page } ) => {
    await open( page );

    // The defect keel-verify's own check caught in this slice, after D-079's
    // bulk bar and D-085's copy button: a `hidden` node whose class declares a
    // display still paints.
    const alert = page.getByTestId( 'editor.autosave_alert' );
    await expect( alert ).toBeHidden();
    expect( await alert.evaluate( ( el ) => getComputedStyle( el ).display ) ).toBe( 'none' );
} );

// ─── The states the product can reach ────────────────────────────────

test( 'saving with no title is refused with an error summary naming the field', async ( { page } ) => {
    await open( page, { slug: null } );

    // A title of whitespace, deliberately: the control carries `required`, so
    // an empty one never leaves the browser and the SERVER's refusal would be
    // unreachable by driving. `trim()` empties this one server-side, which is
    // the branch under test — the browser's own constraint is the test below.
    await page.getByTestId( 'editor.title' ).fill( '   ' );

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.locator( '.k-toolbar-actions .k-btn--primary' ).click(),
    ] );

    const summary = page.getByTestId( 'editor.error_summary' );
    await expect( summary ).toBeVisible();
    await expect( summary ).toHaveAttribute( 'role', 'alert' );

    // Every failed field is a LINK to that field.
    const link = summary.locator( 'a' ).first();
    await expect( link ).toHaveAttribute( 'href', '#k-editor-title' );
} );

test( 'publishing announces the URL in the status region, as a link', async ( { page } ) => {
    const slug = 'stage6-editor-' + Date.now();
    await open( page, { slug: null } );

    await page.getByTestId( 'editor.title' ).fill( 'Stage 6 editor probe' );
    await page.getByTestId( 'editor.slug' ).fill( slug );

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.locator( '.k-toolbar-actions .k-btn--primary' ).click(),
    ] );

    const line = page.getByTestId( 'editor.status_line' );
    await expect( line ).toBeVisible();
    await expect( line ).toHaveAttribute( 'role', 'status' );
    await expect( line.locator( 'a' ) ).toHaveAttribute( 'href', new RegExp( slug ) );
} );

test( 'the slug the URL line posts is the slug that is stored', async ( { page } ) => {
    const slug = 'stage6-slug-' + Date.now();
    await open( page, { slug: null } );

    await page.getByTestId( 'editor.title' ).fill( 'Stage 6 slug probe' );
    await page.getByTestId( 'editor.slug' ).fill( slug );

    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.locator( '.k-toolbar-actions .k-btn--primary' ).click(),
    ] );

    // A FRESH GET, never page.reload(): reload re-submits the POST and would
    // pass whether or not anything was stored (D-088's third test defect).
    await open( page, { slug } );
    await expect( page.getByTestId( 'editor.slug' ) ).toHaveValue( slug );
    await expect( page.getByTestId( 'editor.title' ) ).toHaveValue( 'Stage 6 slug probe' );
} );

// ─── §3 Responsive ───────────────────────────────────────────────────

test( 'above 1199 the inspector is a column and its toolbar trigger is not shown', async ( { page } ) => {
    await page.setViewportSize( { width: 1440, height: 900 } );
    await open( page );

    await expect( page.getByTestId( 'editor.inspector' ) ).toBeVisible();
    await expect( page.getByTestId( 'editor.inspector_trigger' ) ).toBeHidden();
    // Above 1199 the sheet role is removed rather than left behind: a `hidden`
    // or dialog-roled node the CSS un-hides is a lie to the accessibility tree.
    await expect( page.getByTestId( 'editor.inspector' ) ).not.toHaveAttribute( 'role', 'dialog' );
} );

test( 'at 1100 the inspector is a sheet: the trigger opens it, focus moves in, Esc closes and returns focus', async ( { page } ) => {
    await page.setViewportSize( { width: 1100, height: 900 } );
    await open( page );

    const trigger = page.getByTestId( 'editor.inspector_trigger' );
    const inspector = page.getByTestId( 'editor.inspector' );

    await expect( trigger ).toBeVisible();
    await expect( trigger ).toHaveAttribute( 'aria-controls', 'k-inspector' );
    await expect( inspector ).toBeHidden();

    await trigger.click();
    await expect( trigger ).toHaveAttribute( 'aria-expanded', 'true' );
    await expect( inspector ).toBeVisible();
    await expect( inspector ).toHaveAttribute( 'role', 'dialog' );
    // §3: non-modal.
    await expect( inspector ).toHaveAttribute( 'aria-modal', 'false' );
    expect( await page.evaluate( () => document.activeElement.id ) ).toBe( 'k-inspector' );

    await page.keyboard.press( 'Escape' );
    await expect( inspector ).toBeHidden();
    await expect( trigger ).toHaveAttribute( 'aria-expanded', 'false' );
    expect( await page.evaluate( () => document.activeElement.getAttribute( 'data-testid' ) ) )
        .toBe( 'editor.inspector_trigger' );
} );

test( 'the editor does not scroll sideways at 320 CSS px', async ( { page } ) => {
    await page.setViewportSize( { width: 320, height: 800 } );
    await open( page );

    // WCAG 1.4.10, and this build has broken it twice by reading containment
    // instead of measuring the page (D-078's .k-sr, D-079's .k-table-scroll).
    // Measured on the SHELL, not on <html>, and the reason is the dev bar:
    // it is a development-only surface — the same one DEV_ONLY_SURFACES
    // already excludes from the axe pass — that no released install renders,
    // and it is fixed-position at a width of its own. Every node the PRODUCT
    // ships is inside .k-shell.
    const overflow = await page.evaluate( () => {
        const shell = document.querySelector( '.k-shell' );
        return shell.scrollWidth - shell.clientWidth;
    } );
    expect( overflow ).toBeLessThanOrEqual( 0 );
} );

// ─── i18n ────────────────────────────────────────────────────────────

test( 'no catalogue key is rendered as its own name', async ( { page } ) => {
    await open( page );

    // L-046 is a check now, but the check reads STATIC literals: a key
    // assembled at runtime is invisible to it, and this screen assembles the
    // template names from an array. So the SHAPE is matched in the rendered
    // text instead — a missing key renders as the key itself.
    const text = await page.locator( 'body' ).innerText();
    // The lookbehind excludes a preceding word character or hyphen, so the dev
    // bar printing `page-editor.php` is not read as the key `editor.php`. A
    // printed multi-part name contains shorter prefixes the matcher will flag,
    // and this is that case arriving on the first screen to carry the dev bar.
    const suspects = text.match( /(?<![\w-])(editor|pages|common|list|shell)\.[a-z0-9_]+/g ) || [];
    expect( suspects, `untranslated key(s) on the screen: ${ suspects.join( ', ' ) }` ).toEqual( [] );
} );

// ─── The registered gap's FLOOR ──────────────────────────────────────

test( 'the slug pair DR-005 gap 2 covers is pinned at the ratio it was measured at', async ( { page } ) => {
    await open( page, { theme: 'light' } );

    // An open request is not a licence to regress. The pair is excluded from
    // the axe pass by selector, so this is the only thing standing between
    // 4.23:1 and any number below it.
    const measured = await page.getByTestId( 'editor.slug' ).evaluate( ( el ) => {
        const parse = ( c ) => c.match( /\d+/g ).slice( 0, 3 ).map( Number );
        const lum = ( rgb ) => {
            const f = ( v ) => ( v / 255 <= 0.04045 ? v / 255 / 12.92 : ( ( v / 255 + 0.055 ) / 1.055 ) ** 2.4 );
            return 0.2126 * f( rgb[ 0 ] ) + 0.7152 * f( rgb[ 1 ] ) + 0.0722 * f( rgb[ 2 ] );
        };
        const fg = parse( getComputedStyle( el ).color );
        let node = el;
        let bg = 'rgba(0, 0, 0, 0)';
        while ( node && ( bg === 'rgba(0, 0, 0, 0)' || bg === 'transparent' ) ) {
            bg = getComputedStyle( node ).backgroundColor;
            node = node.parentElement;
        }
        const [ a, b ] = [ lum( fg ), lum( parse( bg ) ) ].sort( ( x, y ) => y - x );
        return Math.round( ( ( a + 0.05 ) / ( b + 0.05 ) ) * 100 ) / 100;
    } );

    expect( measured ).toBeGreaterThanOrEqual( 4.23 );
} );

// ─── Accessibility, per state and per theme ──────────────────────────

for ( const theme of [ 'dark', 'light' ] ) {
    test( `axe over the whole page, resting, ${ theme }`, async ( { page } ) => {
        await open( page, { theme } );
        expect( await scan( page ) ).toEqual( [] );
    } );

    test( `axe over the whole page with the inspector sheet open, ${ theme }`, async ( { page } ) => {
        await page.setViewportSize( { width: 1100, height: 900 } );
        await open( page, { theme } );
        await page.getByTestId( 'editor.inspector_trigger' ).click();
        await expect( page.getByTestId( 'editor.inspector' ) ).toBeVisible();
        expect( await scan( page ) ).toEqual( [] );
    } );
}
