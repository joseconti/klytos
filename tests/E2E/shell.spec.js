// The shell's JavaScript half — the four behaviours D-075 had to record as
// `⚠ unverified` because Playwright was not installed when stage 2 was built.
// Every one of them is now DRIVEN. The server-rendered half (nav contents per
// role, landmark uniqueness, §5 child parentage, the theme toggle's 403/405/
// invalid/open-redirect branches) is already covered by the PHP integration
// tier and is not duplicated here.
//
// Source of truth: SPEC/screens/template-shell.md (behaviour),
// SPEC/navigation.md (contents), SPEC/accessibility.md §5.11 (the palette's
// combobox semantics) and §3.2 (overlay focus containment).
// Implementation: installer/admin/assets/js/klytos-shell.js.

const { test, expect, login } = require( './fixtures' );

test.describe( 'command palette (⌘K)', () => {
    test.beforeEach( async ( { page } ) => {
        await login( page, 'owner' );
    } );

    test( 'opens on ⌘K, closes on ⌘K, and is hidden to begin with', async ( { page } ) => {
        const palette = page.locator( '#k-palette' );
        await expect( palette ).toBeHidden();

        await page.keyboard.press( 'ControlOrMeta+k' );
        await expect( palette ).toBeVisible();

        // The same shortcut toggles it shut — template-shell.md §1.
        await page.keyboard.press( 'ControlOrMeta+k' );
        await expect( palette ).toBeHidden();
    } );

    test( 'Escape closes it and focus returns to whatever opened it', async ( { page } ) => {
        const search = page.getByTestId( 'shell.search' );

        // A REAL click, not `locator.focus()`. Driving this the programmatic way
        // was a test defect worth recording: calling .focus() from outside the
        // page opens the palette but leaves focus on the search field, whereas a
        // genuine user gesture completes the handoff to the palette input. The
        // test must reproduce what a person does, or it measures the harness.
        await search.click();

        // Focusing the sidebar search field opens the palette (template-shell.md §1).
        await expect( page.locator( '#k-palette' ) ).toBeVisible();
        await expect( page.getByTestId( 'shell.palette_input' ) ).toBeFocused();

        await page.keyboard.press( 'Escape' );
        await expect( page.locator( '#k-palette' ) ).toBeHidden();

        // Focus restoration is the part a keyboard user actually feels.
        await expect( search ).toBeFocused();
    } );

    test( 'carries the combobox semantics accessibility.md §5.11 specifies', async ( { page } ) => {
        await page.keyboard.press( 'ControlOrMeta+k' );

        const input = page.getByTestId( 'shell.palette_input' );
        await expect( input ).toHaveAttribute( 'role', 'combobox' );
        await expect( input ).toHaveAttribute( 'aria-controls', 'k-palette-list' );
        await expect( page.locator( '#k-palette-list' ) ).toHaveAttribute( 'role', 'listbox' );

        const window_ = page.locator( '#k-palette .k-palette-window' );
        await expect( window_ ).toHaveAttribute( 'role', 'dialog' );
        await expect( window_ ).toHaveAttribute( 'aria-modal', 'true' );

        // Every rendered row is an option, and exactly one is selected.
        const options = page.locator( '#k-palette-list .k-palette-option' );
        expect( await options.count() ).toBeGreaterThan( 0 );
        await expect( options.first() ).toHaveAttribute( 'role', 'option' );
        await expect( page.locator( '#k-palette-list [aria-selected="true"]' ) ).toHaveCount( 1 );
    } );

    test( 'ArrowDown / ArrowUp move the active option and focus never leaves the input', async ( { page } ) => {
        await page.keyboard.press( 'ControlOrMeta+k' );
        const input = page.getByTestId( 'shell.palette_input' );
        const options = page.locator( '#k-palette-list .k-palette-option' );

        await expect( input ).toHaveAttribute( 'aria-activedescendant', 'k-palette-option-0' );

        await page.keyboard.press( 'ArrowDown' );
        await expect( input ).toHaveAttribute( 'aria-activedescendant', 'k-palette-option-1' );
        await expect( options.nth( 1 ) ).toHaveAttribute( 'aria-selected', 'true' );
        await expect( options.nth( 0 ) ).toHaveAttribute( 'aria-selected', 'false' );

        await page.keyboard.press( 'ArrowUp' );
        await expect( input ).toHaveAttribute( 'aria-activedescendant', 'k-palette-option-0' );

        // Wrap-around: ArrowUp from the first lands on the last.
        const count = await options.count();
        await page.keyboard.press( 'ArrowUp' );
        await expect( input ).toHaveAttribute( 'aria-activedescendant', `k-palette-option-${ count - 1 }` );

        // The combobox pattern keeps DOM focus on the input throughout — that is
        // what aria-activedescendant is for.
        await expect( input ).toBeFocused();
    } );

    test( 'filters as you type and states the no-match case in words', async ( { page } ) => {
        await page.keyboard.press( 'ControlOrMeta+k' );
        const input = page.getByTestId( 'shell.palette_input' );
        const options = page.locator( '#k-palette-list .k-palette-option' );

        const all = await options.count();
        await input.fill( 'set' );
        const filtered = await options.count();
        expect( filtered ).toBeGreaterThan( 0 );
        expect( filtered ).toBeLessThan( all );

        // A query that matches nothing shows the sentence, not an empty box.
        await input.fill( 'zzzznotacommandzzzz' );
        await expect( options ).toHaveCount( 0 );
        const empty = page.locator( '#k-palette-empty' );
        await expect( empty ).toBeVisible();
        await expect( empty ).not.toBeEmpty();

        // With no matches there is nothing to be active.
        await expect( input ).not.toHaveAttribute( 'aria-activedescendant', /.*/ );
    } );

    test( 'Enter navigates to the active option', async ( { page } ) => {
        await page.keyboard.press( 'ControlOrMeta+k' );
        const input = page.getByTestId( 'shell.palette_input' );
        const first = page.locator( '#k-palette-list .k-palette-option' ).first();
        const target = await first.getAttribute( 'data-url' );
        expect( target ).toBeTruthy();

        await input.press( 'Enter' );
        await page.waitForURL( ( url ) => url.href.includes( target.replace( /^.*?(?=\/installer)/, '' ) ) );
        await expect( page.getByTestId( 'shell.brand' ) ).toBeVisible();
    } );

    test( 'a click on the scrim closes it, like Escape', async ( { page } ) => {
        await page.keyboard.press( 'ControlOrMeta+k' );
        await expect( page.locator( '#k-palette' ) ).toBeVisible();

        // Click the overlay itself, well away from the window.
        await page.locator( '#k-palette' ).click( { position: { x: 5, y: 5 } } );
        await expect( page.locator( '#k-palette' ) ).toBeHidden();
    } );

    test( 'Tab is trapped inside the palette while it is open (§3.2)', async ( { page } ) => {
        await page.keyboard.press( 'ControlOrMeta+k' );
        await expect( page.locator( '#k-palette' ) ).toBeVisible();

        // Tab repeatedly; focus must never escape to the shell behind it.
        for ( let i = 0; i < 6; i++ ) {
            await page.keyboard.press( 'Tab' );
            const insidePalette = await page.evaluate( () => {
                const p = document.getElementById( 'k-palette' );
                return p.contains( document.activeElement ) || document.activeElement === document.body;
            } );
            expect( insidePalette, `focus escaped the palette after ${ i + 1 } Tab(s)` ).toBe( true );
        }
    } );
} );

test.describe( 'off-canvas drawer (below 900px)', () => {
    test.use( { viewport: { width: 720, height: 900 } } );

    test.beforeEach( async ( { page } ) => {
        await login( page, 'owner' );
    } );

    test( 'the trigger opens it, reports aria-expanded, and makes the sidebar a modal dialog', async ( { page } ) => {
        const trigger = page.getByTestId( 'shell.drawer_trigger' );
        const sidebar = page.locator( '#k-sidebar' );

        await expect( trigger ).toBeVisible();
        await expect( trigger ).toHaveAttribute( 'aria-expanded', 'false' );

        // The dialog role exists only while the element IS a drawer.
        await expect( sidebar ).not.toHaveAttribute( 'role', 'dialog' );

        await trigger.click();
        await expect( trigger ).toHaveAttribute( 'aria-expanded', 'true' );
        await expect( sidebar ).toHaveAttribute( 'role', 'dialog' );
        await expect( sidebar ).toHaveAttribute( 'aria-modal', 'true' );
    } );

    test( 'Escape closes it and returns focus to the trigger', async ( { page } ) => {
        const trigger = page.getByTestId( 'shell.drawer_trigger' );
        await trigger.click();
        await expect( trigger ).toHaveAttribute( 'aria-expanded', 'true' );

        await page.keyboard.press( 'Escape' );
        await expect( trigger ).toHaveAttribute( 'aria-expanded', 'false' );
        await expect( page.locator( '#k-sidebar' ) ).not.toHaveAttribute( 'role', 'dialog' );
        await expect( trigger ).toBeFocused();
    } );

    test( 'a click on the scrim closes it', async ( { page } ) => {
        const trigger = page.getByTestId( 'shell.drawer_trigger' );
        await trigger.click();

        const scrim = page.locator( '#k-drawer-scrim' );
        await expect( scrim ).toBeVisible();

        // Click the scrim where the drawer is NOT. The scrim spans the viewport
        // and the drawer sits over its left edge, so clicking at (5, 5) lands on
        // the brand link inside the drawer, not the scrim.
        await page.mouse.click( 690, 450 );
        await expect( trigger ).toHaveAttribute( 'aria-expanded', 'false' );
    } );

    test( 'focus stays inside the drawer, or inside the palette it deliberately hands off to (§3.2)', async ( { page } ) => {
        await page.getByTestId( 'shell.drawer_trigger' ).click();
        await expect( page.locator( '#k-sidebar' ) ).toHaveAttribute( 'aria-modal', 'true' );

        // Two SPEC clauses meet here and the first driven run is what surfaced it.
        // accessibility.md §3.2 puts the search field second in the sidebar's tab
        // order; template-shell.md §1 says focusing that field opens the command
        // palette. So one Tab inside the drawer lands in the palette — by design,
        // not by accident. The user is not stranded: the palette is itself modal,
        // traps its own focus, and Escape returns focus to the search field, which
        // is inside the drawer. What must NEVER happen is focus reaching the shell
        // behind both overlays, and that is what this asserts.
        for ( let i = 0; i < 8; i++ ) {
            await page.keyboard.press( 'Tab' );
            const where = await page.evaluate( () => {
                const a = document.activeElement;
                const sidebar = document.getElementById( 'k-sidebar' );
                const palette = document.getElementById( 'k-palette' );
                if ( ! a || a === document.body ) {
                    return 'body';
                }
                if ( sidebar.contains( a ) ) {
                    return 'drawer';
                }
                if ( palette.contains( a ) ) {
                    return 'palette';
                }
                return `ESCAPED: ${ a.tagName }#${ a.id }.${ a.className }`;
            } );
            expect(
                [ 'drawer', 'palette', 'body' ],
                `focus reached the shell behind the overlay after ${ i + 1 } Tab(s): ${ where }`
            ).toContain( where );
        }
    } );
} );

test.describe( 'the 56px rail (900–1199px)', () => {
    test.use( { viewport: { width: 1024, height: 900 } } );

    test( 'Expand navigation restores the full sidebar and the choice survives a reload', async ( { page } ) => {
        await login( page, 'owner' );

        const shell = page.locator( '#k-shell' );
        const expand = page.getByTestId( 'shell.rail_expand' );

        await expect( expand ).toBeVisible();
        await expect( shell ).not.toHaveClass( /k-shell--nav-expanded/ );

        await expand.click();
        await expect( shell ).toHaveClass( /k-shell--nav-expanded/ );

        // "It restores 232px and remembers the choice" — template-shell.md §2.
        expect( await page.evaluate( () => window.localStorage.getItem( 'klytos_nav_expanded' ) ) ).toBe( '1' );

        await page.reload();
        await expect( page.locator( '#k-shell' ) ).toHaveClass( /k-shell--nav-expanded/ );

        // There is deliberately no collapse button — collapsing is the
        // breakpoint's job (template-shell.md §2).
        await expect( page.locator( '#k-shell #k-rail-collapse' ) ).toHaveCount( 0 );
    } );
} );

test.describe( 'status bar — offline', () => {
    test( 'going offline repaints the status bar and going back online restores it', async ( { page, context } ) => {
        await login( page, 'owner' );

        const status = page.locator( '#k-statusbar-right' );
        const onlineText = await status.getAttribute( 'data-online-text' );
        const offlineText = await status.getAttribute( 'data-offline-text' );
        expect( onlineText ).toBeTruthy();
        expect( offlineText ).toBeTruthy();
        expect( offlineText ).not.toBe( onlineText );

        await expect( status ).toHaveText( onlineText );
        await expect( status ).not.toHaveClass( /k-statusbar-offline/ );

        await context.setOffline( true );
        await expect( status ).toHaveText( offlineText );
        await expect( status ).toHaveClass( /k-statusbar-offline/ );

        // "The rest of the shell is unchanged; the admin does not throw up a
        // full-screen offline state" — template-shell.md §1.
        await expect( page.getByTestId( 'shell.brand' ) ).toBeVisible();
        await expect( page.locator( 'nav[aria-label="Main"]' ) ).toHaveCount( 1 );

        await context.setOffline( false );
        await expect( status ).toHaveText( onlineText );
        await expect( status ).not.toHaveClass( /k-statusbar-offline/ );
    } );
} );

test.describe( 'DR-005 addendum 2 — the current nav item\'s contrast floor', () => {
    /*
     * The sidebar's current item paints --color-acento on --fila-seleccion over
     * the sidebar's own background, and that pair is below AA in BOTH themes:
     *
     *   dark   #3CC3B2 on #2B4C4B   4.31:1
     *   light  #0E8074 on #D7E4E5   3.70:1
     *
     * Both recomputed independently from the token hexes; axe reports 4.3 and
     * 3.69 and the arithmetic agrees. `template-shell.md` §2 specifies the pair,
     * so the palette is Design's and the build substitutes nothing (Phase 4
     * rule 2) — it is registered in DR-005 and excluded by selector from the
     * axe passes in fixtures.js.
     *
     * The exclusion is what makes this test necessary. An excluded selector is
     * an unchecked selector, and an open Design Request must never become a
     * licence to regress: the measured ratios are pinned here as FLOORS, so the
     * pair may only move UP while the request is open.
     *
     * It has been true on every ported screen since stage 2 and no pass saw it,
     * because every earlier spec scoped axe to `#main`. The shell is the one
     * component every screen carries and was the one component nothing scanned.
     */
    const FLOORS = { dark: 4.31, light: 3.70 };

    for ( const theme of [ 'dark', 'light' ] ) {
        test( `the current nav item is no worse than ${ FLOORS[ theme ] }:1 — ${ theme }`, async ( { page } ) => {
            await login( page, 'owner' );
            await page.context().addCookies( [ {
                name: 'klytos_admin_theme',
                value: theme,
                url: new URL( page.url() ).origin,
            } ] );
            // Baked in before the first paint: a ratio read mid-transition is
            // not the ratio (D-078), and a cookie the shell does not read makes
            // every "light" run measure dark (L-035).
            await page.goto( '/installer/admin/pages.php' );
            expect(
                await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
                'the theme cookie did not take — this run measured the wrong theme'
            ).toBe( theme );

            const measured = await page.evaluate( () => {
                const label = document.querySelector( '.k-nav-item[aria-current="page"] .k-nav-label' );
                if ( ! label ) {
                    return null;
                }

                // COMPOSITE the translucent selection tint over what is behind
                // it rather than taking the first non-transparent background.
                // Reading the first opaque ancestor reported 1.12:1 for a pair
                // that measures 4.53:1 in D-085 — a false FAILURE, which is as
                // much a tooling defect as a false pass (L-036).
                const parse = ( value ) => {
                    const n = value.match( /[\d.]+/g ).map( Number );
                    return { r: n[ 0 ], g: n[ 1 ], b: n[ 2 ], a: n.length > 3 ? n[ 3 ] : 1 };
                };
                const over = ( top, bottom ) => ( {
                    r: top.r * top.a + bottom.r * ( 1 - top.a ),
                    g: top.g * top.a + bottom.g * ( 1 - top.a ),
                    b: top.b * top.a + bottom.b * ( 1 - top.a ),
                    a: 1,
                } );

                const stack = [];
                for ( let el = label; el; el = el.parentElement ) {
                    const bg = parse( getComputedStyle( el ).backgroundColor );
                    if ( bg.a > 0 ) {
                        stack.push( bg );
                    }
                    if ( bg.a === 1 ) {
                        break;
                    }
                }
                let background = stack.pop() || { r: 255, g: 255, b: 255, a: 1 };
                while ( stack.length ) {
                    background = over( stack.pop(), background );
                }

                const lum = ( c ) => {
                    const ch = ( v ) => {
                        const s = v / 255;
                        return s <= 0.04045 ? s / 12.92 : Math.pow( ( s + 0.055 ) / 1.055, 2.4 );
                    };
                    return 0.2126 * ch( c.r ) + 0.7152 * ch( c.g ) + 0.0722 * ch( c.b );
                };
                const fg = parse( getComputedStyle( label ).color );
                const a = lum( fg );
                const b = lum( background );
                return ( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );
            } );

            expect( measured, 'no current nav item was found to measure' ).not.toBeNull();
            expect(
                Number( measured.toFixed( 2 ) ),
                `the current nav item regressed below DR-005 addendum 2's recorded floor in ${ theme }`
            ).toBeGreaterThanOrEqual( FLOORS[ theme ] );
        } );
    }
} );
