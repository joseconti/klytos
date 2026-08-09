// Manifest entry 3 — Design (theme) — driven per STATE, in both themes.
//
// The first `record-form` screen, so this spec carries the template's own
// contract as well as the screen's: a visible label on every control, hint and
// error both in aria-describedby, the toolbar Save (which lives OUTSIDE the
// form it submits), the form-level error summary that takes focus, and
// accessibility.md §10.7 — the measured ratio beside every pair and the refusal
// to save one below 4.5:1 without a recorded override.
//
// Every state is REACHED BY DRIVING IT. A screen whose error state is asserted
// from source has not had its error state tested.

const { test, expect, login, KNOWN_DELIVERY_GAPS } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );
const DESIGN_URL = '/installer/admin/theme.php';

/**
 * The seeded palette, restored after every test that saves.
 *
 * A form screen's tests mutate stored state by definition — that is what a
 * form does — so each one puts it back through the product's own writer rather
 * than editing the theme document behind the product's back.
 */
const SEED_PALETTE = {
    primary: '#2563eb',
    secondary: '#7c3aed',
    accent: '#f59e0b',
    background: '#ffffff',
    surface: '#f8fafc',
    text: '#1e293b',
    text_muted: '#64748b',
    border: '#e2e8f0',
};

test.beforeEach( async ( { page } ) => {
    await login( page, 'owner' );
} );

test.afterEach( async ( { page } ) => {
    // Restore through the screen itself, so a broken save also shows up here.
    await open( page );
    await fillPalette( page, SEED_PALETTE );
    await save( page );
} );

/**
 * Open Design with the theme baked in BEFORE the first paint (D-078, L-035):
 * a colour read mid-`transition` is not the colour, and a cookie whose name
 * the shell does not read makes every "light" run measure dark.
 */
async function open( page, theme = 'dark' ) {
    await page.context().addCookies( [ {
        name: 'klytos_admin_theme',
        value: theme,
        url: new URL( page.url() ).origin,
    } ] );
    await page.goto( DESIGN_URL );
    await expect( page.locator( 'h1' ) ).toBeVisible();

    expect(
        await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
        'the theme cookie did not take — this run measured the wrong theme'
    ).toBe( theme );
}

async function fillPalette( page, values ) {
    for ( const [ key, value ] of Object.entries( values ) ) {
        await page.getByTestId( `design.hex.${ key }` ).fill( value );
    }
}

async function save( page ) {
    await Promise.all( [
        page.waitForLoadState( 'load' ),
        page.getByTestId( 'design.save' ).click(),
    ] );
}

/** axe at WCAG 2.2 AA over one region, so a failure names the state it is in. */
async function axeOn( page, selector ) {
    let builder = new AxeBuilder( { page } )
        .withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
        .include( selector );

    for ( const gap of KNOWN_DELIVERY_GAPS ) {
        builder = builder.exclude( gap );
    }

    return builder.analyze();
}

// ─── The template's structure (template-record-form.md §1) ──────────

test( 'the three manifest cards are present, each with its own h2', async ( { page } ) => {
    await open( page );

    const headings = ( await page.locator( '.k-record-form .k-card-heading' ).allTextContents() )
        .map( ( t ) => t.trim() );

    expect( headings ).toEqual( [ 'Palette', 'Type scale', 'Radii and spacing' ] );

    // §4: one <h1>. The shell emits it; the screen must not add a second.
    await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
} );

test( 'the primary Save is in the toolbar, not at the foot of the page', async ( { page } ) => {
    await open( page );

    const save = page.getByTestId( 'design.save' );
    await expect( save ).toBeVisible();

    /*
     * The button must really be INSIDE the toolbar and really be associated
     * with the form. This is the assertion that would have caught the seam
     * defect this slice fixed: klytos_kses_post() has no <button> tag, so the
     * filter output rendered as the bare word "Save" — visible, in the right
     * place, and not a control at all.
     */
    expect( await save.evaluate( ( el ) => el.closest( '.k-toolbar' ) !== null ) ).toBe( true );
    expect( await save.evaluate( ( el ) => el.tagName ) ).toBe( 'BUTTON' );
    expect( await save.evaluate( ( el ) => el.form && el.form.id ) ).toBe( 'k-design-form' );
} );

test( 'every control has a visible label bound to it', async ( { page } ) => {
    await open( page );

    const unlabelled = await page.evaluate( () => {
        const bad = [];
        const controls = document.querySelectorAll(
            '#k-design-form input:not([type="hidden"]):not([type="color"]), #k-design-form select'
        );
        for ( const control of controls ) {
            const label = control.id ? document.querySelector( `label[for="${ control.id }"]` ) : null;
            if ( ! label ) {
                bad.push( control.name || control.id || control.outerHTML.slice( 0, 60 ) );
                continue;
            }
            // Visible, not sr-only: §4 says "No placeholder-as-label anywhere".
            const rect = label.getBoundingClientRect();
            if ( rect.width === 0 || rect.height === 0 ) {
                bad.push( `${ control.name } (label present but not visible)` );
            }
        }
        return bad;
    } );

    expect( unlabelled ).toEqual( [] );
} );

test( 'no control uses a placeholder as its label', async ( { page } ) => {
    await open( page );

    const placeholders = await page.locator( '#k-design-form [placeholder]' ).count();
    expect( placeholders ).toBe( 0 );
} );

test( 'the hex field is the value and the colour picker carries no name', async ( { page } ) => {
    await open( page );

    /*
     * The screen this replaced gave the picker and the hex field the SAME
     * name, so the picker's value posted last and won — and with JavaScript
     * off, the value a person typed was discarded silently. The picker is a
     * mirror; only the hex field posts.
     */
    expect( await page.getByTestId( 'design.swatch.text' ).evaluate( ( el ) => el.name ) ).toBe( '' );
    expect( await page.getByTestId( 'design.hex.text' ).evaluate( ( el ) => el.name ) ).toBe( 'text' );
} );

test( 'the picker and the hex field mirror each other', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'design.hex.primary' ).fill( '#123456' );
    await expect( page.getByTestId( 'design.swatch.primary' ) ).toHaveValue( '#123456' );

    await page.getByTestId( 'design.swatch.primary' ).evaluate( ( el ) => {
        el.value = '#abcdef';
        el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
    } );
    await expect( page.getByTestId( 'design.hex.primary' ) ).toHaveValue( '#abcdef' );
} );

// ─── accessibility.md §10.7 — the measured pairs and the refusal ────

test( 'the four declared pairs are shown, each with its measured ratio and a word', async ( { page } ) => {
    await open( page );

    const pairs = page.locator( '[data-testid^="design.pair."]' );
    await expect( pairs ).toHaveCount( 4 );

    // The ratio is a number the person can read, and the verdict is a WORD —
    // never colour alone (accessibility.md §1.3).
    const first = pairs.first();
    await expect( first ).toContainText( /\d+\.\d{2}:1/ );
    await expect( first.locator( '.k-badge' ) ).toHaveText( 'Passes AA' );
} );

test( 'the shown ratio matches the arithmetic, not a rounded guess', async ( { page } ) => {
    await open( page );

    /*
     * #1e293b on #ffffff = 14.63:1. Recomputed independently, in Python, with
     * the WCAG formula written from the standard rather than from this
     * codebase — the first version of this assertion said 14.79, which is a
     * pair from the ADMIN palette, not the theme's. The screen was right and
     * the expectation was wrong; the number below is the arithmetic's.
     */
    await expect(
        page.getByTestId( 'design.pair.text_background' )
    ).toContainText( '14.63:1' );
} );

test( 'a pair below 4.5:1 REFUSES the save and nothing is written', async ( { page } ) => {
    await open( page );

    // #aaaaaa on #ffffff is 2.32:1 — comfortably under.
    await page.getByTestId( 'design.hex.text' ).fill( '#aaaaaa' );
    await save( page );

    const summary = page.getByTestId( 'design.error_summary' );
    await expect( summary ).toBeVisible();
    await expect( summary ).toContainText( 'This theme was not saved.' );
    await expect( summary ).toContainText( '2.32:1' );

    // Nothing was written. A fresh GET, never page.reload(): reloading a POST
    // response re-submits it, so it would re-render the refused state and the
    // check would pass whether or not anything had been stored.
    await open( page );
    await expect( page.getByTestId( 'design.hex.text' ) ).toHaveValue( SEED_PALETTE.text );
} );

test( 'the refused values stay on screen so the person can fix them', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'design.hex.text' ).fill( '#aaaaaa' );
    await save( page );

    // Sending them back to the stored value would discard their work and hide
    // which colour caused the refusal.
    await expect( page.getByTestId( 'design.hex.text' ) ).toHaveValue( '#aaaaaa' );
} );

test( 'the error summary is an alert and takes focus on load', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'design.hex.text' ).fill( '#aaaaaa' );
    await save( page );

    const summary = page.getByTestId( 'design.error_summary' );
    await expect( summary ).toHaveAttribute( 'role', 'alert' );
    // tabindex="-1": focusable, but not in the tab order.
    await expect( summary ).toHaveAttribute( 'tabindex', '-1' );
    expect(
        await page.evaluate( () => document.activeElement.getAttribute( 'data-testid' ) )
    ).toBe( 'design.error_summary' );
} );

test( 'each summary row links to the field it names', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'design.hex.text' ).fill( '#aaaaaa' );
    await save( page );

    const href = await page.getByTestId( 'design.error_link.0' ).getAttribute( 'href' );
    expect( href ).toBe( '#design-field-text' );
    await expect( page.locator( href ) ).toBeVisible();
} );

test( 'the override is offered only when there is something to override', async ( { page } ) => {
    await open( page );

    // A passing palette offers nothing: a checkbox that is always on screen
    // invites the habit of ticking it, which is the opposite of "explicit".
    await expect( page.getByTestId( 'design.override' ) ).toHaveCount( 0 );

    await page.getByTestId( 'design.hex.text' ).fill( '#aaaaaa' );
    await save( page );

    await expect( page.getByTestId( 'design.override' ) ).toBeVisible();
} );

test( 'an explicit override saves the pair AND records it', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'design.hex.text' ).fill( '#aaaaaa' );
    await save( page );

    await page.getByTestId( 'design.override' ).check();
    await save( page );

    await expect( page.getByTestId( 'design.status_line' ) ).toHaveText( 'Design saved.' );
    await open( page );
    await expect( page.getByTestId( 'design.hex.text' ) ).toHaveValue( '#aaaaaa' );

    /*
     * §10.7 says the override must be RECORDED. A checkbox that only unblocks
     * the save is not a record — six months on nobody could tell a considered
     * exception from a mis-click. Read the stored document, not the screen.
     */
    const stored = execFileSync(
        'php',
        [ 'tests/E2E/fixtures/read-theme.php', 'contrast_overrides' ],
        { cwd: REPO_ROOT, env: { ...process.env, XDEBUG_MODE: 'off' }, encoding: 'utf8' }
    );
    const overrides = JSON.parse( stored.trim() );
    /*
     * One save records EVERY pair it overrode, not just the first: changing
     * `text` drops it below AA on both `background` and `surface`, and a
     * record that mentioned one of them would understate what was accepted.
     */
    const recorded = overrides.filter( ( o ) => o.pair === 'text/background' );
    expect( recorded.length ).toBeGreaterThan( 0 );

    const last = recorded[ recorded.length - 1 ];
    expect( last.by ).toBe( 'owner' );
    expect( Number( last.ratio ).toFixed( 2 ) ).toBe( '2.32' );
    expect( overrides.some( ( o ) => o.pair === 'text/surface' ) ).toBe( true );
} );

// ─── Field-level validation (template-record-form.md §2) ───────────

test( 'a value that is not a colour is a FIELD error, not a contrast error', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'design.hex.text' ).fill( 'rebeccapurple' );
    await save( page );

    const field = page.getByTestId( 'design.hex.text' );
    await expect( field ).toHaveAttribute( 'aria-invalid', 'true' );

    // One problem at a time, in the order the person can act on it: an
    // unmeasurable colour has no ratio to report.
    const summary = page.getByTestId( 'design.error_summary' );
    await expect( summary ).toContainText( 'enter a hex colour' );
    await expect( summary ).not.toContainText( ':1' );
} );

test( 'hint and error are both in aria-describedby, hint first', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'design.hex.text' ).fill( 'nope' );
    await save( page );

    const described = await page.getByTestId( 'design.hex.text' ).getAttribute( 'aria-describedby' );
    expect( described ).toBe( 'design-hint-text design-error-text' );

    for ( const id of described.split( ' ' ) ) {
        await expect( page.locator( `#${ id }` ) ).toBeVisible();
    }
} );

test( 'the field error is never colour alone — it carries an icon and a sentence', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'design.hex.text' ).fill( 'nope' );
    await save( page );

    const error = page.locator( '#design-error-text' );
    await expect( error ).toContainText( 'hex colour' );
    await expect( error.locator( 'svg' ) ).toHaveCount( 1 );
} );

test( 'an empty field keeps the stored colour rather than blanking the token', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'design.hex.border' ).fill( '' );
    await save( page );

    await expect( page.getByTestId( 'design.status_line' ) ).toBeVisible();
    await expect( page.getByTestId( 'design.hex.border' ) ).toHaveValue( SEED_PALETTE.border );
} );

/*
 * DR-005 gap 3 is EXCLUDED from the axe pass by selector, which is only
 * acceptable while the exclusion cannot become a licence to regress. This
 * pins the measured ratio as a FLOOR: the pair may stay at 4.32:1 until
 * Design answers, and anything worse fails here.
 */
test( 'the excluded error colour stays at its measured floor, never worse', async ( { page } ) => {
    await open( page, 'dark' );

    await page.getByTestId( 'design.hex.text' ).fill( 'nope' );
    await save( page );

    const measured = await page.locator( '#design-error-text' ).evaluate( ( el ) => {
        const parse = ( c ) => c.match( /[\d.]+/g ).slice( 0, 3 ).map( Number );
        const lum = ( rgb ) => {
            const [ r, g, b ] = rgb.map( ( v ) => {
                const s = v / 255;
                return s <= 0.04045 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 );
            } );
            return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        };

        // Walk up for the first non-transparent background, exactly as the
        // eye does — the message itself paints none.
        let node = el;
        let background = null;
        while ( node && ! background ) {
            const c = getComputedStyle( node ).backgroundColor;
            if ( c && ! /rgba\(0, 0, 0, 0\)|transparent/.test( c ) ) {
                background = parse( c );
            }
            node = node.parentElement;
        }

        const foreground = parse( getComputedStyle( el ).color );
        const l1 = lum( foreground );
        const l2 = lum( background );
        return ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 );
    } );

    expect( Number( measured.toFixed( 2 ) ) ).toBeGreaterThanOrEqual( 4.32 );
} );

// ─── The happy path ────────────────────────────────────────────────

test( 'a passing palette saves and says so in a status region', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'design.hex.primary' ).fill( '#1d4ed8' );
    await save( page );

    const status = page.getByTestId( 'design.status_line' );
    await expect( status ).toHaveAttribute( 'role', 'status' );
    await expect( status ).toHaveText( 'Design saved.' );

    await open( page );
    await expect( page.getByTestId( 'design.hex.primary' ) ).toHaveValue( '#1d4ed8' );
} );

test( 'the type scale and layout cards save with the palette, in one post', async ( { page } ) => {
    await open( page );

    await page.getByTestId( 'design.font.heading' ).fill( 'Literata' );
    await page.getByTestId( 'design.layout.border_radius' ).fill( '12px' );
    await page.getByTestId( 'design.layout.header_style' ).selectOption( 'fixed' );
    await save( page );

    await open( page );
    await expect( page.getByTestId( 'design.font.heading' ) ).toHaveValue( 'Literata' );
    await expect( page.getByTestId( 'design.layout.border_radius' ) ).toHaveValue( '12px' );
    await expect( page.getByTestId( 'design.layout.header_style' ) ).toHaveValue( 'fixed' );
} );

// ─── Responsive (template-record-form.md §3) ───────────────────────

test( 'the card stack stops at 880px on the reference viewport', async ( { page } ) => {
    await page.setViewportSize( { width: 1440, height: 976 } );
    await open( page );

    const width = await page.locator( '.k-record-form .k-card-stack' )
        .evaluate( ( el ) => el.getBoundingClientRect().width );

    // Read out of the browser, never off the stylesheet (L-032).
    expect( width ).toBeLessThanOrEqual( 880 );
} );

test( 'the page does not scroll sideways at 320 CSS px', async ( { page } ) => {
    await page.setViewportSize( { width: 320, height: 800 } );
    await open( page );

    /*
     * WCAG 1.4.10. Measured, not reasoned about: D-079's fourth defect was a
     * scroll container written in CSS and never rendered, and every
     * containment reading said 280-inside-320 while the page really scrolled.
     */
    const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - document.documentElement.clientWidth
    );
    expect( overflow ).toBeLessThanOrEqual( 0 );
} );

test( 'field grids are single-column below 1200px', async ( { page } ) => {
    await page.setViewportSize( { width: 1100, height: 900 } );
    await open( page );

    const columns = await page.locator( '.k-field-grid--pair' ).first()
        .evaluate( ( el ) => getComputedStyle( el ).gridTemplateColumns.split( ' ' ).length );

    expect( columns ).toBe( 1 );
} );

test( 'field grids are two-column at 1200px and above', async ( { page } ) => {
    await page.setViewportSize( { width: 1440, height: 976 } );
    await open( page );

    const columns = await page.locator( '.k-field-grid--pair' ).first()
        .evaluate( ( el ) => getComputedStyle( el ).gridTemplateColumns.split( ' ' ).length );

    expect( columns ).toBe( 2 );
} );

// ─── The automated accessibility pass, per state, in both themes ───

for ( const theme of [ 'dark', 'light' ] ) {
    test( `axe finds nothing on the default state — ${ theme }`, async ( { page } ) => {
        await open( page, theme );
        const results = await axeOn( page, '#k-design-form' );
        expect( results.violations ).toEqual( [] );
    } );

    test( `axe finds nothing on the refused state — ${ theme }`, async ( { page } ) => {
        await open( page, theme );
        await page.getByTestId( 'design.hex.text' ).fill( '#aaaaaa' );
        await save( page );
        const results = await axeOn( page, 'main' );
        expect( results.violations ).toEqual( [] );
    } );

    test( `axe finds nothing on the field-error state — ${ theme }`, async ( { page } ) => {
        await open( page, theme );
        await page.getByTestId( 'design.hex.text' ).fill( 'nope' );
        await save( page );
        const results = await axeOn( page, 'main' );
        expect( results.violations ).toEqual( [] );
    } );
}
