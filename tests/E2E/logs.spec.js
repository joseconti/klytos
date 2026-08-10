// Manifest entry 41 — Logs — driven per STATE, in both themes.
//
// Phase 4 Step 4, stage 4. Logs is the one stage-4 surface DR-006 does not
// block: a console-stream has no `grid-template-columns` to be blocked on.
//
// The pattern is pages.spec.js's, and it is deliberate: axe scoped per STATE
// rather than per page, geometry and cascade read back out of the browser
// rather than off the file (L-032 — never assume which rule wins), and the
// theme baked in before load rather than toggled after paint.
//
// One check here inverts the rest of the suite and that is the point: every
// other screen asserts the page does NOT scroll horizontally at 320px, and this
// one asserts the STREAM does. §3 is explicit that this is the single place in
// the admin where horizontal scroll of content is correct — 1.4.10's exception
// for content requiring two-dimensional layout — because wrapping a log line is
// worse than scrolling it.

const { test, expect, login, passwordFor, KNOWN_DELIVERY_GAPS } = require( './fixtures' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );
const LOGS_URL = '/installer/admin/logs.php';

const FILE_POPULATED = 'debug-2026-08-01.log';
const FILE_EMPTY = 'debug-2026-08-02.log';
const FILE_UNREADABLE = 'debug-2026-08-03.log';
const FILE_TRUNCATING = 'debug-2026-08-04.log';

let fixtures = null;

/**
 * Write the four fixture log files through the product's own Logger directory.
 *
 * In `beforeAll`, deliberately: the read-back duty snapshots the product logs
 * when each test's fixtures are set up, so files written before that are part
 * of the baseline. Written inside a test body instead, the fixture's own ERROR
 * and CRITICAL lines would be read back as if the flow had produced them and
 * every test in the file would fail on its own scenery.
 */
test.beforeAll( () => {
    const out = execFileSync(
        'php',
        [ path.join( 'tests', 'E2E', 'fixtures', 'seed-logs.php' ), 'create' ],
        { cwd: REPO_ROOT, env: { ...process.env, XDEBUG_MODE: 'off' }, encoding: 'utf8' }
    );
    fixtures = JSON.parse( out.trim().split( '\n' ).pop() );
} );

test.afterAll( () => {
    execFileSync(
        'php',
        [ path.join( 'tests', 'E2E', 'fixtures', 'seed-logs.php' ), 'remove' ],
        { cwd: REPO_ROOT, env: { ...process.env, XDEBUG_MODE: 'off' }, encoding: 'utf8' }
    );
} );

/**
 * Open Logs in a given theme, with the theme baked in BEFORE the first paint.
 *
 * Toggling after load and reading a colour back mid-`transition` reported a
 * button at 2.59:1 that is 4.86:1 (D-078). The product itself sets
 * `<html data-theme>` server-side from the cookie (D-075), so this is also how
 * a real visit works.
 */
async function open( page, query = '', theme = 'dark' ) {
    await page.context().addCookies( [ {
        name: 'klytos_admin_theme',
        value: theme,
        url: new URL( page.url() ).origin,
    } ] );
    await page.goto( LOGS_URL + query );
    await expect( page.locator( 'h1' ) ).toBeVisible();

    /*
     * ASSERT THE THEME ACTUALLY TOOK. The first version of this helper set a
     * cookie named `klytos_theme`; the shell reads `klytos_admin_theme`
     * (header.php), so every "light" run silently rendered DARK and the suite
     * reported both themes verified while measuring one. A "both themes"
     * claim with no check that the second theme arrived is exactly the shape
     * of false green this project keeps finding — so the check is the fix,
     * not the corrected cookie name.
     */
    expect(
        await page.evaluate( () => document.documentElement.getAttribute( 'data-theme' ) ),
        'the theme cookie did not take — this run measured the wrong theme'
    ).toBe( theme );
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

/*
 * THE STREAM LINE IS AUDITED IN TWO PASSES, AND THE SPLIT IS THE HONEST PART.
 *
 * DR-007 IS RESOLVED (D-093) AND THIS COMMENT IS THE RECORD OF WHAT CHANGED.
 *
 * Driving this screen originally found three things the delivery specified that
 * measured below WCAG 2.2 AA. Two of them were answered by the re-delivery and
 * are now specified to PASS; the third was answered by granting an explicit,
 * conditional exception. What remains disabled below is `target-size` alone,
 * and it is disabled because `accessibility.md` §7.1 GRANTS it — not because a
 * request is open. The four conditions of that grant are asserted by
 * `the 19px line holds §7.1's exception on all four conditions` below, so the
 * exception is checked rather than merely claimed.
 *
 * The original three, kept because the history is the point:
 *
 *   1. TARGET SIZE (2.5.8). `template-console-stream.md` §1 sets the stream at
 *      `--type-code` (12px/19px) and §2 makes each line a `<button>` spanning
 *      it, so every line target is 19px in its constrained dimension.
 *      `accessibility.md` §7 lists exactly two exceptions, neither of which is
 *      this, and closes with "No other exception exists. If a build produces a
 *      smaller target, that is a defect." The two files contradict each other
 *      and `.k-hit-24` cannot reconcile them: stacked 19px rows leave no
 *      undisturbed 24px space whatever the pseudo-element does. → DR-007 gap 1.
 *   2. THE SELECTED LINE'S COLOURS. §2 paints the selected line
 *      `--fila-seleccion`; §1 paints the text `--texto-secundario` and the keys
 *      `--texto-sutil`. That composition is not among the delivery's 72 audited
 *      pairs and measures 3.59:1 / 3.37:1 (dark) and 3.90:1 / 3.84:1 (light).
 *      → DR-007 gap 2.
 *   3. THE PANEL'S OWN TEXT. `--texto-secundario` on `--fondo-ventana` is
 *      4.45:1 in light — DR-005 gap 2, already sent on 2026-07-29, reaching a
 *      second surface exactly as that request predicted.
 *
 * WHERE EACH ONE LANDED:
 *
 *   1 → GRANTED as `accessibility.md` §7.1, a third documented exception, with
 *       four testable conditions. `target-size` stays disabled on the stream
 *       lines and ONLY there, and the four conditions are asserted separately.
 *   2 → FIXED in the delivery: a selected line now paints all its text
 *       `--texto-primario` (12.72 light / 9.52 dark) with a 3px accent bar, and
 *       an unselected line's values take `--texto-primario` too. `color-contrast`
 *       is therefore RE-ENABLED on the stream pass — it was disabled only while
 *       the pairs were below AA.
 *   3 → closed with 2, for this surface: it was the same token pair.
 *
 * So: pass A audits the whole screen with EVERY rule on and the stream lines
 * excluded; pass B audits the stream lines with `target-size` alone turned off,
 * on that selector alone. Everything else is enabled everywhere.
 */
const DR_EXEMPT_RULES = [ 'target-size' ];

/*
 * `target-offset` reports the same DR-007 gap 1 finding from the other side —
 * the space AROUND a 19px line rather than the line itself — but axe 4.12.1
 * does not accept it in `rules` (it is not in `axe.getRules()`, and passing it
 * to `disableRules` throws "unknown rule"), so it cannot be disabled the way
 * the other two are. It is filtered out of the RESULT instead, by id, only on
 * the stream-line pass, and named here so the difference in mechanism is not
 * mistaken for a difference in judgement.
 */
const DR_EXEMPT_RESULT_IDS = [ 'target-offset' ];

/*
 * DR-009 — FOUND BY RE-ENABLING `color-contrast` AFTER DR-007 CLOSED, which is
 * the whole argument for un-pinning an exclusion the moment its request is
 * answered. It had been sitting under the old blanket exemption, unmeasured.
 *
 * §1 gives an unselected line's STRUCTURE (the timestamp and the source)
 * `--texto-sutil`, and gives ERROR and WARN lines the tint as their BACKGROUND.
 * Composed, that is `--texto-sutil` on a tint over `--fondo-ventana`, and it
 * fails in both tints and both themes:
 *
 *   light  on --tinte-peligro  3.88:1     dark  on --tinte-peligro  4.18:1
 *   light  on --tinte-aviso    3.94:1     dark  on --tinte-aviso    3.73:1
 *
 * Recomputed independently in Python from the token hexes; axe reports 3.93 for
 * the light/aviso case and the arithmetic agrees.
 *
 * BOTH HALVES ARE THE DELIVERY'S — `--texto-sutil` for structure and the tint
 * for the line are each specified in §1 — so this is Phase 4 rule 2 and is
 * registered rather than fixed. And it is pointed: the composed-pairs table
 * DR-007 just added states the general rule ("a token that passes on a surface
 * is not thereby passing on a tint over that surface"), lists `--texto-sutil`
 * on `--fondo-ventana` as PASS, and never measures it on a tint over that
 * surface — which is exactly what an ERROR or a WARN line renders.
 *
 * SELECTED tinted lines are NOT affected: DR-007's answer makes all their text
 * `--texto-primario` (12.67 / 11.74 / 12.86 / 10.49), so the exclusion is
 * scoped to the unselected state and the selected one stays fully checked.
 */
const DR009_PAIRS = [
    '.k-stream-line.k-line--error:not([aria-pressed="true"]) .k-stream-time',
    '.k-stream-line.k-line--error:not([aria-pressed="true"]) .k-stream-source',
    '.k-stream-line.k-line--warn:not([aria-pressed="true"]) .k-stream-time',
    '.k-stream-line.k-line--warn:not([aria-pressed="true"]) .k-stream-source',
];

async function axeScreen( page ) {
    let builder = new AxeBuilder( { page } )
        .withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
        .include( '#main' )
        .exclude( '.k-stream-line' );
    for ( const gap of KNOWN_DELIVERY_GAPS ) {
        builder = builder.exclude( gap );
    }
    return builder.analyze();
}

async function axeStreamLines( page ) {
    let builder = new AxeBuilder( { page } )
        .withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
        .include( '.k-stream-line' )
        .disableRules( DR_EXEMPT_RULES );

    // One at a time: exclude() reads an ARRAY as a FRAME PATH (L-037).
    for ( const pair of DR009_PAIRS ) {
        builder = builder.exclude( pair );
    }

    const result = await builder.analyze();

    return {
        ...result,
        violations: result.violations.filter( ( v ) => ! DR_EXEMPT_RESULT_IDS.includes( v.id ) ),
    };
}

/** WCAG relative luminance / contrast, computed in the page from real pixels. */
const CONTRAST_IN_PAGE = ( selector ) => {
    const el = document.querySelector( selector );
    const parse = ( c ) => {
        const n = c.match( /[\d.]+/g ).map( Number );
        return { r: n[ 0 ], g: n[ 1 ], b: n[ 2 ], a: n.length > 3 ? n[ 3 ] : 1 };
    };

    /*
     * THE TINTS ARE TRANSLUCENT, so the background has to be COMPOSITED, not
     * read. `--tinte-peligro` is an rgba() over the panel, which is itself over
     * the card: taking the first non-transparent backgroundColor returns the
     * tint's own nominal colour and produces a ratio for a colour no pixel on
     * the screen actually has. The first version of this helper did exactly
     * that and reported 1.12:1 for a pair that measures 4.53:1 — a false
     * FAILURE, which is the mirror of the false pass this suite keeps hunting,
     * and just as much a defect in the tooling.
     */
    const stack = [];
    let node = el;
    while ( node ) {
        const c = parse( getComputedStyle( node ).backgroundColor );
        if ( c.a > 0 ) {
            stack.push( c );
            if ( c.a === 1 ) {
                break;
            }
        }
        node = node.parentElement;
    }
    // Bottom-up: an opaque base, then each translucent layer painted over it.
    let bg = stack.pop() || { r: 255, g: 255, b: 255, a: 1 };
    while ( stack.length ) {
        const over = stack.pop();
        bg = {
            r: over.r * over.a + bg.r * ( 1 - over.a ),
            g: over.g * over.a + bg.g * ( 1 - over.a ),
            b: over.b * over.a + bg.b * ( 1 - over.a ),
            a: 1,
        };
    }

    const fg = parse( getComputedStyle( el ).color );
    const lum = ( c ) => {
        const f = ( v ) => {
            v /= 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
        };
        return 0.2126 * f( c.r ) + 0.7152 * f( c.g ) + 0.0722 * f( c.b );
    };
    const l1 = lum( fg );
    const l2 = lum( bg );
    return Math.round( ( ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 ) ) * 100 ) / 100;
};

test.describe( 'Logs — the states', () => {

    test.beforeEach( async ( { page } ) => {
        await login( page, 'owner' );
    } );

    test( 'no file selected: the screen asks for one and shows no stream', async ( { page } ) => {
        await open( page );

        await expect( page.getByTestId( 'logs.empty_no_file' ) ).toBeVisible();
        await expect( page.getByTestId( 'logs.stream' ) ).toHaveCount( 0 );

        // §2's Disabled state belongs to a CHOSEN file; with none chosen there
        // is nothing to download and nothing to disable.
        await expect( page.getByTestId( 'logs.download' ) ).toHaveCount( 0 );
        await expect( page.getByTestId( 'logs.download_disabled' ) ).toHaveCount( 0 );
    } );

    test( 'a populated file streams its lines, level first', async ( { page } ) => {
        await open( page, `?file=${ FILE_POPULATED }` );

        const stream = page.getByTestId( 'logs.stream' );
        await expect( stream ).toBeVisible();

        // Seven fixture lines, including the stray one this Logger did not
        // write — a log viewer that hides what it cannot parse is worse than
        // one that shows it.
        await expect( page.locator( '.k-stream-line' ) ).toHaveCount( 7 );
        await expect( page.getByTestId( 'logs.line.6' ) )
            .toContainText( 'Fatal-looking stray line' );

        // §4: "Level is text first, tint second." The word is IN the line, not
        // conveyed by the background.
        const first = page.getByTestId( 'logs.line.0' );
        await expect( first.locator( '.k-stream-level' ) ).toHaveText( 'DEBUG' );

        // §1: the tint is the line background for ERROR and WARN ONLY.
        await expect( page.getByTestId( 'logs.line.4' ) ).toHaveClass( /k-line--error/ );
        await expect( page.getByTestId( 'logs.line.3' ) ).toHaveClass( /k-line--warn/ );
        await expect( page.getByTestId( 'logs.line.1' ) ).not.toHaveClass( /k-line--/ );

        // CRITICAL is above ERROR in severity and takes the error tint while
        // still printing its own word — the code-side adaptation for a Logger
        // that writes eight levels where the design names four.
        const critical = page.getByTestId( 'logs.line.5' );
        await expect( critical ).toHaveClass( /k-line--error/ );
        await expect( critical.locator( '.k-stream-level' ) ).toHaveText( 'CRITICAL' );

        // §4: "Timestamps are <time datetime>."
        await expect( first.locator( 'time' ) ).toHaveAttribute( 'datetime', '2026-08-01T09:00:01Z' );
    } );

    test( 'the per-line copy affordance is GONE, and copy lives outside the stream', async ( { page, context } ) => {
        await context.grantPermissions( [ 'clipboard-read', 'clipboard-write' ] );
        await open( page, `?file=${ FILE_POPULATED }` );

        /*
         * DR-007's answer withdrew it (D-093). Asserted as ABSENT rather than
         * simply not tested: `accessibility.md` §7.1 grants the 19px line its
         * target-size exception only while the line is the ONLY target in its
         * row, so a second control reappearing here would silently revoke the
         * grounds for the exception the pass above relies on.
         */
        await expect( page.locator( '.k-stream-copy' ) ).toHaveCount( 0 );
        await expect( page.getByTestId( 'logs.copy.4' ) ).toHaveCount( 0 );

        // Copy line: hidden until a line is selected, because a control that
        // copies nothing is not an affordance.
        const copyLine = page.getByTestId( 'logs.copy_line' );
        await expect( copyLine ).toBeHidden();

        await page.getByTestId( 'logs.line.4' ).click();
        await expect( copyLine ).toBeVisible();

        // §4: "Copy buttons name what they copy."
        await expect( copyLine ).toHaveText( /copy line/i );

        // And it really copies the line AS STORED — read back out of the
        // clipboard, not asserted from the click having happened.
        await copyLine.click();
        const clip = await page.evaluate( () => navigator.clipboard.readText() );
        expect( clip ).toContain( 'Payment capture failed' );
        // As stored means the whole record, not just the message.
        expect( clip ).toContain( '[ERROR]' );
    } );

    test( 'Copy all names the count it copies, and copies exactly that', async ( { page, context } ) => {
        await context.grantPermissions( [ 'clipboard-read', 'clipboard-write' ] );
        await open( page, `?file=${ FILE_POPULATED }` );

        const rendered = await page.locator( '.k-stream-line' ).count();
        const copyAll = page.getByTestId( 'logs.copy_all' );

        // §4: the label carries the count of what the stream is SHOWING.
        await expect( copyAll ).toHaveText( new RegExp( `\\(${ rendered }\\)` ) );

        await copyAll.click();
        const clip = await page.evaluate( () => navigator.clipboard.readText() );
        expect( clip.split( '\n' ).length ).toBe( rendered );
    } );

    test( "the 19px line holds §7.1's exception on all four conditions", async ( { page } ) => {
        await open( page, `?file=${ FILE_POPULATED }` );

        /*
         * `accessibility.md` §7.1 grants the stream line a target-size exception
         * "only with all four of these, and a build that drops one has produced
         * a defect". `target-size` is disabled on the stream pass BECAUSE of
         * this grant, so the grant itself has to be checked — otherwise the
         * disabled rule is just a disabled rule.
         */
        const row = page.locator( '.k-stream-row' ).first();

        // 1. The line is the ONLY target in its row.
        await expect(
            row.locator( 'button, a, input, select, [tabindex]:not([tabindex="-1"])' )
        ).toHaveCount( 1 );

        // 2. The unconstrained dimension is the FULL line: the target spans the
        //    stream's whole width, so what is being aimed at is visibly the row.
        const widths = await page.evaluate( () => {
            const line = document.querySelector( '.k-stream-line' );
            const pre  = document.getElementById( 'logs-stream-pre' );
            return {
                line: Math.round( line.getBoundingClientRect().width ),
                pre:  Math.round( pre.getBoundingClientRect().width ),
            };
        } );
        expect( widths.line ).toBe( widths.pre );

        // 3. `.k-hit-24` is NEVER applied to a stream line — §7.1 says so in as
        //    many words, because it cannot help and it moves the overlap.
        await expect( page.locator( '.k-stream-line.k-hit-24' ) ).toHaveCount( 0 );

        // 4. Every OTHER control on the screen is still ≥ 24px — the exception
        //    is for the line and nothing else, and that is the half a blanket
        //    disable would have hidden.
        const small = await page.evaluate( () => {
            const out = [];
            document.querySelectorAll( '#main button, #main a' ).forEach( ( el ) => {
                if ( el.closest( '.k-stream-pre' ) ) return;

                /*
                 * §7's table gives some controls their target through a WRAPPER
                 * rather than through their own box — "Switch | 38 × 22 |
                 * 38 × 24 | label row is 24px tall; the switch's own label is
                 * part of the target". Measuring the control alone reported the
                 * switch at 38×22 and called it a defect; the spec says the row
                 * is the target, so the row is what gets measured. That was a
                 * defect in this test, not in the screen.
                 */
                const target = el.closest( '.k-switch-row' ) || el.closest( '.k-hit-24' ) || el;
                const r = target.getBoundingClientRect();
                if ( r.width === 0 && r.height === 0 ) return;
                if ( r.height < 24 || r.width < 24 ) {
                    out.push( ( el.dataset.testid || el.className ) + ` ${ Math.round( r.width ) }x${ Math.round( r.height ) }` );
                }
            } );
            return out;
        } );
        expect( small, 'a control outside the stream is under 24px' ).toEqual( [] );
    } );

    test( 'the stream is fully operable from the keyboard — §7.1 condition 3', async ( { page } ) => {
        await open( page, `?file=${ FILE_POPULATED }` );

        /*
         * This is a CONDITION of the target-size exception, not a convenience:
         * §7.1 grants the 19px line only where "↑/↓ move between lines inside
         * the focusable stream group, Enter or Space selects, and the detail
         * panel is reachable with no pointer precision at all". Driven with the
         * real keyboard, never by calling focus().
         */
        await page.getByTestId( 'logs.stream' ).focus();

        await page.keyboard.press( 'ArrowDown' );
        await expect( page.getByTestId( 'logs.line.0' ) ).toBeFocused();

        await page.keyboard.press( 'ArrowDown' );
        await expect( page.getByTestId( 'logs.line.1' ) ).toBeFocused();

        await page.keyboard.press( 'ArrowUp' );
        await expect( page.getByTestId( 'logs.line.0' ) ).toBeFocused();

        // Enter selects, and the detail panel populates — reached with no
        // pointer at all, which is the condition's actual claim.
        await page.keyboard.press( 'Enter' );
        await expect( page.getByTestId( 'logs.line.0' ) ).toHaveAttribute( 'aria-pressed', 'true' );
        await expect( page.getByTestId( 'logs.copy_line' ) ).toBeVisible();

        // ↑ at the first line stays put rather than escaping the stream: the
        // group is the boundary the exception is scoped to.
        await page.keyboard.press( 'ArrowUp' );
        await expect( page.getByTestId( 'logs.line.0' ) ).toBeFocused();
    } );

    test( 'a selected line paints ALL its text --texto-primario and takes the 3px bar', async ( { page } ) => {
        await open( page, `?file=${ FILE_POPULATED }` );
        await page.getByTestId( 'logs.line.4' ).click();

        /*
         * DR-007's answer, §2: "All text in a selected line is --texto-primario
         * — the role colours are suppressed for the duration of the selection",
         * plus a 3px accent bar so selection is not carried by a 1.16:1
         * background change alone. Read out of the browser (L-032), never from
         * the stylesheet.
         */
        const read = await page.evaluate( () => {
            const line = document.querySelector( '.k-stream-line[aria-pressed="true"]' );
            const primary = getComputedStyle( document.documentElement )
                .getPropertyValue( '--texto-primario' ).trim();
            const seen = new Set();
            line.querySelectorAll( '.k-stream-level, .k-stream-time, .k-stream-source' )
                .forEach( ( el ) => seen.add( getComputedStyle( el ).color ) );
            return {
                roles: Array.from( seen ),
                lineColor: getComputedStyle( line ).color,
                shadow: getComputedStyle( line ).boxShadow,
                primary,
            };
        } );

        // Every role inside the line resolved to ONE colour — the suppression
        // is what makes that true, and a stale role colour would show up as a
        // second entry rather than as a ratio nobody looks at.
        expect( read.roles.length ).toBe( 1 );
        expect( read.roles[ 0 ] ).toBe( read.lineColor );

        // The bar. 3px, and inset, so it is inside the row rather than a border
        // that would move the text.
        expect( read.shadow ).toContain( 'inset' );
        expect( read.shadow ).toMatch( /3px/ );
    } );

    test( 'Copy all follows the FILTER rather than the file, which is what its name promises', async ( { page, context } ) => {
        await context.grantPermissions( [ 'clipboard-read', 'clipboard-write' ] );
        await open( page, `?file=${ FILE_POPULATED }&level=error` );

        const rendered = await page.locator( '.k-stream-line' ).count();
        expect( rendered, 'the error filter matched nothing, so this proves nothing' ).toBeGreaterThan( 0 );

        const copyAll = page.getByTestId( 'logs.copy_all' );
        await expect( copyAll ).toHaveText( new RegExp( `\\(${ rendered }\\)` ) );

        await copyAll.click();
        const clip = await page.evaluate( () => navigator.clipboard.readText() );

        // A control that names a count it does not copy is worse than one that
        // names none — so every copied line must be an ERROR line.
        const lines = clip.split( '\n' ).filter( Boolean );
        expect( lines.length ).toBe( rendered );
        for ( const line of lines ) {
            expect( line ).toContain( '[ERROR]' );
        }
    } );

    test( 'the detail panel copies the whole payload, and only when there is one', async ( { page, context } ) => {
        await context.grantPermissions( [ 'clipboard-read', 'clipboard-write' ] );
        await open( page, `?file=${ FILE_POPULATED }` );

        // Nothing selected: nothing to copy, so no control.
        await expect( page.getByTestId( 'logs.copy_payload' ) ).toBeHidden();

        await page.getByTestId( 'logs.line.4' ).click();
        const payload = page.getByTestId( 'logs.copy_payload' );
        await expect( payload ).toBeVisible();
        await payload.click();
        expect( await page.evaluate( () => navigator.clipboard.readText() ) ).toContain( '"order":17' );

        // A line with no context has no payload to copy, and the control goes
        // away rather than copying an empty string.
        await page.getByTestId( 'logs.line.1' ).click();
        await expect( payload ).toBeHidden();
    } );

    test( 'the stream is a labelled, focusable group and is NOT aria-live', async ( { page } ) => {
        await open( page, `?file=${ FILE_POPULATED }` );

        const stream = page.getByTestId( 'logs.stream' );
        await expect( stream ).toHaveAttribute( 'role', 'group' );
        await expect( stream ).toHaveAttribute( 'tabindex', '0' );
        await expect( stream ).toHaveAttribute( 'aria-label', /7/ );

        /*
         * The deliberate exception (§2, §4, and manifest §41's own delta): a
         * live log reads continuously and makes the page unusable. This asserts
         * the ABSENCE of a property, which is worth a test precisely because a
         * later well-meaning edit "fixing the missing live region" is exactly
         * what it must catch.
         */
        await expect( stream ).not.toHaveAttribute( 'aria-live', /.*/ );
        expect( await stream.locator( '[aria-live]' ).count() ).toBe( 0 );

        // It is reachable by keyboard, which is the whole reason it is
        // tabindex="0" — a scroll container nobody can focus cannot be scrolled
        // without a mouse.
        await stream.focus();
        await expect( stream ).toBeFocused();
    } );

    test( 'selecting a line fills the detail panel and toggles aria-pressed', async ( { page } ) => {
        await open( page, `?file=${ FILE_POPULATED }` );

        const errorLine = page.getByTestId( 'logs.line.4' );
        await expect( errorLine ).toHaveAttribute( 'aria-pressed', 'false' );

        await errorLine.click();

        await expect( errorLine ).toHaveAttribute( 'aria-pressed', 'true' );

        // §4: "the detail panel's <h2> names the selected event."
        const detail = page.getByTestId( 'logs.detail' );
        await expect( detail.locator( 'h2' ) ).toHaveText( 'Payment capture failed' );

        // The body is the line's context — the trailing JSON, which is where
        // this data model keeps what the design calls "context and stack".
        await expect( detail.locator( 'dt' ) ).toHaveCount( 2 );
        await expect( detail.locator( 'dd' ).first() ).toHaveText( '17' );

        // Only one line is selected at a time.
        await page.getByTestId( 'logs.line.1' ).click();
        await expect( errorLine ).toHaveAttribute( 'aria-pressed', 'false' );
        await expect( page.getByTestId( 'logs.line.1' ) ).toHaveAttribute( 'aria-pressed', 'true' );

        // A line with no context says so rather than showing an empty panel.
        await expect( page.locator( '#logs-detail-nocontext' ) ).toBeVisible();

        // Pressing the selected line again releases it: aria-pressed is a
        // toggle and must behave like one.
        await page.getByTestId( 'logs.line.1' ).click();
        await expect( page.getByTestId( 'logs.line.1' ) ).toHaveAttribute( 'aria-pressed', 'false' );
    } );

    test( 'an empty file gets the empty state, and Download is disabled with the reason in its name', async ( { page } ) => {
        await open( page, `?file=${ FILE_EMPTY }` );

        await expect( page.getByTestId( 'logs.empty_file' ) ).toBeVisible();
        await expect( page.getByTestId( 'logs.empty_file' ) ).toContainText( FILE_EMPTY );
        await expect( page.getByTestId( 'logs.stream' ) ).toHaveCount( 0 );

        // §2's Disabled: "Download is disabled when the file is empty, with the
        // reason in its name" — the accessible NAME, not a tooltip.
        const download = page.getByTestId( 'logs.download_disabled' );
        await expect( download ).toBeDisabled();
        const name = await download.evaluate( ( el ) => el.textContent.replace( /\s+/g, ' ' ).trim() );
        expect( name ).toMatch( /empty/i );

        // It is NOT the error variant: an empty log is not a fault.
        await expect( page.getByTestId( 'logs.error_unreadable' ) ).toHaveCount( 0 );
    } );

    test( 'an unreadable file gets the ERROR state, which is a different state and a different sentence', async ( { page } ) => {
        test.skip( ! fixtures.unreadable, 'The running user can read mode 0000 (root) — the state is unreachable here.' );

        await open( page, `?file=${ FILE_UNREADABLE }` );

        const error = page.getByTestId( 'logs.error_unreadable' );
        await expect( error ).toBeVisible();
        await expect( error ).toHaveAttribute( 'role', 'alert' );
        await expect( error ).toContainText( FILE_UNREADABLE );

        // The state that D-084 made reachable at all: before that fix this URL
        // was a TypeError and the page never rendered.
        await expect( page.getByTestId( 'logs.empty_file' ) ).toHaveCount( 0 );
        await expect( page.getByTestId( 'logs.stream' ) ).toHaveCount( 0 );

        // §2 offers "Choose another file"; "Open Health" is not rendered
        // because health.php does not exist (D-072 deferred it), and a link
        // that 404s from an error state is worse than the state without it.
        await expect( page.getByTestId( 'logs.choose_another' ) ).toBeVisible();

        const download = page.getByTestId( 'logs.download_disabled' );
        await expect( download ).toBeDisabled();
        const name = await download.evaluate( ( el ) => el.textContent.replace( /\s+/g, ' ' ).trim() );
        expect( name ).toMatch( /read/i );
    } );

    test( 'filtered to nothing is a GOOD-NEWS empty state with the way back', async ( { page } ) => {
        await open( page, `?file=${ FILE_POPULATED }&q=nothing-matches-this-string` );

        const empty = page.getByTestId( 'logs.empty_filtered' );
        await expect( empty ).toBeVisible();

        // Not the error variant — §2: "This is a good-news empty state and
        // reads like one."
        await expect( empty ).not.toHaveClass( /k-empty--error/ );
        await expect( empty ).not.toHaveAttribute( 'role', 'alert' );

        // "— Show all levels" is a real way back and it clears the filter.
        await page.getByTestId( 'logs.show_all_levels' ).click();
        await expect( page.getByTestId( 'logs.stream' ) ).toBeVisible();
        await expect( page ).toHaveURL( /file=debug-2026-08-01\.log/ );
        await expect( page ).not.toHaveURL( /[?&]q=/ );
    } );

    test( 'truncation is stated, never silent, and links to the whole file', async ( { page } ) => {
        await open( page, `?file=${ FILE_TRUNCATING }` );

        const notice = page.getByTestId( 'logs.truncated' );
        await expect( notice ).toBeVisible();
        await expect( notice ).toContainText( '5000' );
        await expect( notice ).toContainText( '5200' );

        // The last 5,000 of 5,200: the marked first line is the one cut.
        await expect( page.locator( '.k-stream-line' ) ).toHaveCount( 5000 );
        await expect( page.getByTestId( 'logs.stream' ) ).not.toContainText( 'FIRST LINE' );

        // The link is the whole file, and it really streams it. The href is
        // RELATIVE to the screen, so it is resolved against the page's own URL
        // — `page.request.get()` resolves against baseURL, which would ask for
        // /api/log-download.php at the site root and get an honest 404.
        const href = await page.getByTestId( 'logs.truncated_download' ).getAttribute( 'href' );
        const response = await page.request.get( new URL( href, page.url() ).toString() );
        expect( response.status() ).toBe( 200 );
        expect( response.headers()[ 'content-disposition' ] ).toContain( FILE_TRUNCATING );
        expect( ( await response.text() ) ).toContain( 'FIRST LINE' );
    } );
} );

test.describe( 'Logs — the controls', () => {

    test.beforeEach( async ( { page } ) => {
        await login( page, 'owner' );
    } );

    test( 'the level filters are LINKS that change the URL', async ( { page } ) => {
        await open( page, `?file=${ FILE_POPULATED }` );

        // §4: "the level filter chips are links (they change the URL)". Not
        // buttons, not tabs — the state is in the address bar and is shareable.
        const chip = page.getByTestId( 'logs.chip.error' );
        await expect( chip ).toHaveJSProperty( 'tagName', 'A' );

        await chip.click();
        await expect( page ).toHaveURL( /level=error/ );
        await expect( page.getByTestId( 'logs.chip.error' ) ).toHaveAttribute( 'aria-current', 'true' );

        // The filter really filters: only the ERROR line survives.
        await expect( page.locator( '.k-stream-line' ) ).toHaveCount( 1 );
        await expect( page.getByTestId( 'logs.stream' ) ).toContainText( 'Payment capture failed' );
    } );

    test( 'the file picker has a VISIBLE label and a submit that works with JS off', async ( { page } ) => {
        await open( page, `?file=${ FILE_POPULATED }` );

        // §4: "a <select> inside a form with a visible label and a submit that
        // is not needed when JS is on but exists when it is off." Both halves
        // are asserted, and "visible" is asserted as visible — a .k-sr label
        // would satisfy a naming check and fail the spec.
        const label = page.locator( 'label[for="logs-file"]' );
        await expect( label ).toBeVisible();
        await expect( label ).not.toHaveClass( /k-sr/ );

        await expect( page.getByTestId( 'logs.file_submit' ) ).toBeAttached();
    } );

    test( 'with JavaScript disabled the file picker still works, through its submit', async ( { browser } ) => {
        // The claim is about JS being OFF, so it is tested with JS off. A
        // context with `javaScriptEnabled: false` is the only honest way to
        // check a no-JS fallback; asserting the button exists proves markup,
        // not behaviour.
        const context = await browser.newContext( { javaScriptEnabled: false } );
        const page = await context.newPage();

        await page.goto( '/installer/admin/login.php' );
        await page.locator( 'input[name="username"]' ).fill( 'owner' );
        await page.locator( 'input[name="password"]' ).fill( passwordFor( 'owner' ) );
        await page.locator( 'form button[type="submit"]' ).first().click();
        await page.waitForURL( ( url ) => ! url.pathname.endsWith( '/login.php' ) );

        await page.goto( LOGS_URL );
        await page.selectOption( '#logs-file', FILE_POPULATED );
        await page.getByTestId( 'logs.file_submit' ).click();

        await expect( page ).toHaveURL( new RegExp( `file=${ FILE_POPULATED.replace( /\./g, '\\.' ) }` ) );
        await expect( page.getByTestId( 'logs.stream' ) ).toBeVisible();

        await context.close();
    } );

    test( 'Follow is a switch, and scrolling up turns it off and says so once', async ( { page } ) => {
        await open( page, `?file=${ FILE_TRUNCATING }` );

        const follow = page.getByTestId( 'logs.follow' );
        // §2: "the Follow toggle is role="switch" aria-checked (it takes effect
        // immediately)". A checkbox would be the wrong control — that is for
        // something you then Save.
        await expect( follow ).toHaveAttribute( 'role', 'switch' );
        await expect( follow ).toHaveAttribute( 'aria-checked', 'false' );

        await follow.click();
        await expect( follow ).toHaveAttribute( 'aria-checked', 'true' );

        // Following sticks the view to the bottom.
        const stream = page.getByTestId( 'logs.stream' );
        const atBottom = await stream.evaluate(
            ( el ) => ( el.scrollHeight - el.scrollTop - el.clientHeight ) < 4
        );
        expect( atBottom ).toBe( true );

        // §2: "Scrolling up turns Follow off automatically and says so once in
        // the status region." The announcement goes to the SHELL's polite
        // status region — never to the stream, which is the whole point of the
        // no-aria-live exception.
        await stream.evaluate( ( el ) => { el.scrollTop = 0; } );
        await expect( follow ).toHaveAttribute( 'aria-checked', 'false' );
        await expect( page.locator( '#k-live-status' ) ).toContainText( /paused/i );
    } );

    test( 'Download streams the file, and only for a caller that may configure the site', async ( { page } ) => {
        await open( page, `?file=${ FILE_POPULATED }` );

        const href = await page.getByTestId( 'logs.download' ).getAttribute( 'href' );
        const ok = await page.request.get( new URL( href, page.url() ).toString() );
        expect( ok.status() ).toBe( 200 );
        expect( ok.headers()[ 'content-type' ] ).toContain( 'text/plain' );
        expect( await ok.text() ).toContain( 'Payment capture failed' );

        // A name that does not resolve inside the logs directory is a 404, and
        // the traversal refusal is the Logger's single implementation of that
        // rule rather than a second copy in the endpoint.
        const traversal = await page.request.get(
            '/installer/admin/api/log-download.php?file=../../config/config.json'
        );
        expect( traversal.status() ).toBe( 404 );
    } );

    test( 'a viewer cannot reach the download endpoint at all', async ( { browser } ) => {
        // The endpoint is new, so its gate is asserted rather than assumed: it
        // was absent from the gate map until this slice added it, and a file
        // absent from that map is denied — but "denied by omission" and "denied
        // by rule" are not the same guarantee.
        const context = await browser.newContext();
        const page = await context.newPage();
        await login( page, 'viewer' );

        const denied = await page.request.get(
            `/installer/admin/api/log-download.php?file=${ FILE_POPULATED }`
        );
        expect( denied.status() ).toBe( 403 );

        await context.close();
    } );
} );

test.describe( 'Logs — accessibility and layout', () => {

    test.beforeEach( async ( { page } ) => {
        await login( page, 'owner' );
    } );

    for ( const theme of [ 'dark', 'light' ] ) {
        test( `axe finds nothing on the populated stream — ${ theme }`, async ( { page } ) => {
            await open( page, `?file=${ FILE_POPULATED }`, theme );
            expect( ( await axeScreen( page ) ).violations ).toEqual( [] );
            expect( ( await axeStreamLines( page ) ).violations ).toEqual( [] );
        } );

        test( `axe finds nothing on the empty state — ${ theme }`, async ( { page } ) => {
            await open( page, `?file=${ FILE_EMPTY }`, theme );
            // No stream here, so the whole region is audited with every rule on.
            expect( ( await axeOn( page, '#main' ) ).violations ).toEqual( [] );
        } );

        test( `axe finds nothing on the error state — ${ theme }`, async ( { page } ) => {
            test.skip( ! fixtures.unreadable, 'The running user can read mode 0000 (root).' );
            await open( page, `?file=${ FILE_UNREADABLE }`, theme );
            expect( ( await axeOn( page, '#main' ) ).violations ).toEqual( [] );
        } );

        test( `axe finds nothing with a line selected — ${ theme }`, async ( { page } ) => {
            await open( page, `?file=${ FILE_POPULATED }`, theme );
            await page.getByTestId( 'logs.line.4' ).click();
            expect( ( await axeScreen( page ) ).violations ).toEqual( [] );
            expect( ( await axeStreamLines( page ) ).violations ).toEqual( [] );
        } );

        test( `a SELECTED tinted line keeps its tint, so its text stays on the measured pair — ${ theme }`, async ( { page } ) => {
            await open( page, `?file=${ FILE_POPULATED }`, theme );
            await page.getByTestId( 'logs.line.4' ).click();

            /*
             * The build defect this pins: `.k-stream-line[aria-pressed]` set
             * --fila-seleccion for every line, so a selected ERROR line put
             * --sobre-tinte-peligro on the SELECTION colour at 4.31:1 — a pair
             * nobody specified. The tinted rules now restore the tint, which
             * puts the text back on the pair klytos-admin.css measured
             * (4.53:1 light / 5.35:1 dark — measured here, and they agree).
             * Read out of the browser, because which rule wins is never
             * assumed here (L-032).
             */
            const ratio = await page.evaluate(
                CONTRAST_IN_PAGE,
                '.k-stream-line.k-line--error[aria-pressed="true"]'
            );
            expect( ratio, `selected ERROR line in ${ theme }` ).toBeGreaterThanOrEqual( 4.5 );
        } );

        /*
         * THIS TEST USED TO BE A FLOOR AND IS NOW A CHECK — DR-007 is RESOLVED
         * (D-093), and its own comment said this is what would happen.
         *
         * The bounds were 3.6 and 4.4: below AA, recorded so an OPEN request
         * could not become a licence to regress. The delivery has since
         * specified passing values, so the bounds are now the real ones and a
         * slide back to the old colours fails HERE rather than in an axe run
         * three screens later. Un-pinning this mattered as much as the fix: an
         * exclusion that outlives its Design Request is a permanent blind spot
         * wearing the costume of a known issue.
         */
        test( `DR-009's excluded pairs are pinned as floors, so an open request is no licence — ${ theme }`, async ( { page } ) => {
            await open( page, `?file=${ FILE_POPULATED }`, theme );

            /*
             * The four compositions the axe pass skips by selector, measured
             * here at their REAL values rather than at 4.5 — a floor set at the
             * threshold would pass even after a slide, which is the mistake
             * D-091 caught on `.k-card--secret` (a floor that could not fail).
             */
            const floors = theme === 'light'
                ? { error: 3.88, warn: 3.94 }
                : { error: 4.18, warn: 3.73 };

            for ( const [ tone, floor ] of Object.entries( floors ) ) {
                const cls = tone === 'error' ? 'k-line--error' : 'k-line--warn';
                const ratio = await page.evaluate(
                    CONTRAST_IN_PAGE,
                    `.k-stream-line.${ cls }:not([aria-pressed="true"]) .k-stream-time`
                );
                expect( ratio, `${ tone } structure text in ${ theme } (DR-009)` )
                    .toBeGreaterThanOrEqual( floor - 0.05 );
            }
        } );

        test( `a SELECTED tinted line is NOT affected by DR-009 — ${ theme }`, async ( { page } ) => {
            await open( page, `?file=${ FILE_POPULATED }`, theme );

            // DR-007's answer makes every role in a selected line
            // --texto-primario, so the tinted selected line clears AA by a wide
            // margin. This is what scopes DR-009 to the unselected state, and
            // asserting it is what stops the exclusion widening later.
            const line = page.locator( '.k-stream-line.k-line--error' ).first();
            await line.click();

            const ratio = await page.evaluate(
                CONTRAST_IN_PAGE,
                '.k-stream-line.k-line--error[aria-pressed="true"] .k-stream-time'
            );
            expect( ratio, `selected ERROR structure text in ${ theme }` )
                .toBeGreaterThanOrEqual( 10 );
        } );

        test( `the selected and unselected lines both clear AA — ${ theme }`, async ( { page } ) => {
            await open( page, `?file=${ FILE_POPULATED }`, theme );
            await page.getByTestId( 'logs.line.1' ).click();

            // Selected line: --texto-primario on --fila-seleccion over
            // --fondo-ventana. The delivery measures 12.72 light / 9.52 dark;
            // recomputed independently at 12.76 / 9.47. Pinned at 9 rather than
            // at 4.5 so a quiet slide toward the threshold fails here.
            const ratio = await page.evaluate(
                CONTRAST_IN_PAGE,
                '.k-stream-line[aria-pressed="true"]'
            );
            expect( ratio, `selected-line contrast in ${ theme }` )
                .toBeGreaterThanOrEqual( 9 );

            // Unselected line: --texto-primario on --fondo-ventana, 14.79 /
            // 15.29. This is DR-005 gap 2's own pair, closed for this surface
            // by the delivery rather than re-raised.
            const plain = await page.evaluate(
                CONTRAST_IN_PAGE,
                '.k-stream-line[aria-pressed="false"]'
            );
            expect( plain, `stream text on the panel in ${ theme }` )
                .toBeGreaterThanOrEqual( 14 );
        } );
    }

    test( 'at 320px the PAGE does not scroll sideways but the STREAM does', async ( { page } ) => {
        await page.setViewportSize( { width: 320, height: 720 } );
        await open( page, `?file=${ FILE_POPULATED }` );

        /*
         * 1.4.10 on the page, asserted by TRYING to scroll rather than by
         * comparing scrollWidth — the two disagreed once and the disagreement
         * was the finding (D-079).
         */
        const pageScrolled = await page.evaluate( () => {
            const before = document.documentElement.scrollLeft;
            document.documentElement.scrollLeft = 500;
            const after = document.documentElement.scrollLeft;
            document.documentElement.scrollLeft = before;
            return after;
        } );
        expect( pageScrolled ).toBe( 0 );

        // And the exception, which is the inverse assertion of every other
        // screen in this suite: the stream itself DOES scroll sideways,
        // because §3 says wrapping a log line is worse than scrolling it.
        const streamScrolled = await page.getByTestId( 'logs.stream' ).evaluate( ( el ) => {
            el.scrollLeft = 400;
            return el.scrollLeft;
        } );
        expect( streamScrolled ).toBeGreaterThan( 0 );

        // Read the computed value out of the BROWSER, never off the file
        // (L-032). `pre-wrap` here would silently undo §3.
        const whiteSpace = await page.locator( '#logs-stream-pre' ).evaluate(
            ( el ) => getComputedStyle( el ).whiteSpace
        );
        expect( whiteSpace ).toBe( 'pre' );
    } );

    test( 'the detail panel is a side panel at 1440 and in the flow below 1200', async ( { page } ) => {
        await page.setViewportSize( { width: 1440, height: 900 } );
        await open( page, `?file=${ FILE_POPULATED }` );

        // §3's reference: stream + 340px detail panel side by side. Measured,
        // not read off the stylesheet.
        const wide = await page.getByTestId( 'logs.detail' ).boundingBox();
        const wideStream = await page.getByTestId( 'logs.stream' ).boundingBox();
        expect( Math.round( wide.width ) ).toBe( 340 );
        expect( wide.x ).toBeGreaterThan( wideStream.x );

        // 1200–1439: the panel narrows to 300.
        await page.setViewportSize( { width: 1280, height: 900 } );
        const mid = await page.getByTestId( 'logs.detail' ).boundingBox();
        expect( Math.round( mid.width ) ).toBe( 300 );

        // Below 1200 it leaves the side and joins the flow under the stream.
        await page.setViewportSize( { width: 1000, height: 900 } );
        const narrow = await page.getByTestId( 'logs.detail' ).boundingBox();
        const narrowStream = await page.getByTestId( 'logs.stream' ).boundingBox();
        expect( narrow.y ).toBeGreaterThan( narrowStream.y );
    } );
} );
