// Shared fixtures for the Klytos browser-driven tier.
//
// Two things every test in this directory gets for free:
//
//  1. `login( page, role )` — a REAL login through the form, not a forged cookie.
//     A forged session proves the screen renders for a session that the product
//     itself never issued, which is the shape of defect L-026 (the harness sent a
//     header the product never sends, so a feature that could not work in any
//     browser had a green suite).
//
//  2. The READ-BACK DUTY (docs/03-technical-plan.md §Testing). A screen that
//     looks right while throwing has not passed. Every test subscribes to
//     `console` (error level), `pageerror`, `requestfailed` and any response with
//     status >= 500, and additionally reads `installer/data/logs-*/` after the
//     flow — Klytos writes its own log there and a PHP notice never reaches the
//     browser. Any of them non-empty fails the test.
//
// Seeded logins are throwaway and local only (docs/playground.md).
// The login form's CSRF field is named `csrf`, not `csrf_token`.

const base = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );

const REPO_ROOT = path.resolve( __dirname, '..', '..' );
const LOG_ROOT = path.join( REPO_ROOT, 'installer', 'data' );

const ROLES = [ 'owner', 'admin', 'editor', 'viewer' ];

function passwordFor( role ) {
    if ( ! ROLES.includes( role ) ) {
        throw new Error( `Unknown seeded role "${ role }". Seeded roles: ${ ROLES.join( ', ' ) }` );
    }
    return `playground-${ role }-2026`;
}

/**
 * Snapshot the byte length of every Klytos product log, so the test can tell
 * what IT appended from what was already there. Reading the whole file would
 * fail every test on a log the previous run left behind.
 */
function snapshotLogs() {
    const sizes = {};
    let dirs = [];
    try {
        dirs = fs.readdirSync( LOG_ROOT ).filter( ( d ) => d.startsWith( 'logs-' ) );
    } catch ( e ) {
        return sizes; // No data directory yet — nothing to read back.
    }
    for ( const dir of dirs ) {
        const full = path.join( LOG_ROOT, dir );
        let files = [];
        try {
            files = fs.readdirSync( full );
        } catch ( e ) {
            continue;
        }
        for ( const file of files ) {
            const p = path.join( full, file );
            try {
                sizes[ p ] = fs.statSync( p ).size;
            } catch ( e ) {
                sizes[ p ] = 0;
            }
        }
    }
    return sizes;
}

/**
 * Everything appended to any product log since the snapshot, filtered to the
 * lines that actually denote a failure.
 */
function logsSince( snapshot ) {
    const found = [];
    const now = snapshotLogs();
    for ( const p of Object.keys( now ) ) {
        const before = snapshot[ p ] || 0;
        if ( now[ p ] <= before ) {
            continue;
        }
        let added = '';
        try {
            const fd = fs.openSync( p, 'r' );
            const buf = Buffer.alloc( now[ p ] - before );
            fs.readSync( fd, buf, 0, buf.length, before );
            fs.closeSync( fd );
            added = buf.toString( 'utf8' );
        } catch ( e ) {
            continue;
        }
        for ( const line of added.split( '\n' ) ) {
            if ( /\b(ERROR|CRITICAL|ALERT|EMERGENCY|Fatal error|Parse error|Uncaught)\b/i.test( line ) ) {
                found.push( `${ path.relative( REPO_ROOT, p ) }: ${ line.trim() }` );
            }
        }
    }
    return found;
}

const test = base.test.extend( {
    /**
     * Auto-fixture: installs the read-back listeners before the test body runs
     * and asserts on them after it finishes.
     */
    readBack: [ async ( { page }, use ) => {
        const problems = [];
        const logSnapshot = snapshotLogs();

        page.on( 'console', ( msg ) => {
            if ( msg.type() === 'error' ) {
                problems.push( `console.error: ${ msg.text() }` );
            }
        } );
        page.on( 'pageerror', ( err ) => {
            problems.push( `pageerror: ${ err.message }` );
        } );
        page.on( 'requestfailed', ( req ) => {
            // net::ERR_ABORTED is what a normal navigation-cancels-a-fetch looks
            // like; everything else is a real failed request.
            const failure = req.failure();
            const text = failure ? failure.errorText : 'unknown';
            if ( text !== 'net::ERR_ABORTED' ) {
                problems.push( `requestfailed: ${ req.method() } ${ req.url() } — ${ text }` );
            }
        } );
        page.on( 'response', ( res ) => {
            if ( res.status() >= 500 ) {
                problems.push( `http ${ res.status() }: ${ res.url() }` );
            }
        } );

        await use( null );

        const logProblems = logsSince( logSnapshot );
        const all = problems.concat( logProblems );
        base.expect(
            all,
            `Read-back duty failed — the flow completed but the runtime complained:\n${ all.join( '\n' ) }`
        ).toEqual( [] );
    }, { auto: true } ],
} );

/**
 * Log in through the real form as one of the four seeded roles.
 * Asserts the session actually landed rather than trusting the redirect.
 */
async function login( page, role ) {
    await page.goto( '/installer/admin/login.php' );

    // login.php predates the data-testid convention (Keel v5.0.0) — it is
    // manifest entry "Login" and gets its identifiers when stage 5 rebuilds it.
    // Until then, locate by the form's own name attributes.
    await page.locator( 'input[name="username"]' ).fill( role );
    await page.locator( 'input[name="password"]' ).fill( passwordFor( role ) );
    await Promise.all( [
        page.waitForURL( ( url ) => ! url.pathname.endsWith( '/login.php' ) ),
        page.locator( 'form button[type="submit"]' ).first().click(),
    ] );

    // The session is real only if the shell rendered for it.
    await base.expect( page.getByTestId( 'shell.brand' ) ).toBeVisible();
}

/*
 * DR-005's excluded elements, by SELECTOR and never by disabling a rule, so
 * every other element in every state stays checked and a NEW defect on the same
 * components still fails. Extend this list ONLY for a registered Design Request.
 *
 * It lives HERE rather than in one spec because the second screen to hit the
 * chip pair proved it is not one screen's problem: DR-005's addendum predicted
 * "thirteen filter rows", and a per-spec copy would have let screen twelve
 * exclude a pair screen one had already fixed, or miss one it had not. One list,
 * one set of measured ratios, every consumer.
 */
const KNOWN_DELIVERY_GAPS = [
    // DR-005 gap 1 — a semantic badge inside a selected table row: the badge
    // tint over --fila-seleccion over the card. 4.44:1 light / 3.08:1 dark.
    'tr[aria-selected="true"] .k-badge',

    // DR-005 addendum, light theme only. A chip row sits on --fondo-ventana,
    // OUTSIDE any card, and both chip states fall under AA there while both
    // pass on a card:
    //   unselected  --texto-secundario on --fondo-ventana            4.46:1
    //               — the same token pair DR-005 gap 2 already registers,
    //                 arriving on a second surface.
    //   selected    --sobre-tinte-acento over --fila-seleccion over
    //               --fondo-ventana                                  4.46:1
    //               — a new composition, sibling of gap 1. On a card the same
    //                 chip measures 4.76:1 and passes.
    // Both recomputed independently from the token hexes; axe and the
    // arithmetic agree. No colour was substituted.
    //
    // Two containers, not a bare `.k-chip`: a chip INSIDE a card passes, and
    // excluding it everywhere would hide a real defect to silence a known one.
    '.k-filters .k-chip',
    '.k-console-chips .k-chip',

    // DR-005 gap 3, dark theme: --color-peligro (#e6685f) on --fondo-elevado
    // (#2c2c2e) measures 4.32:1. The request itself predicted the population —
    // "every error message and destructive button inside a card" — and stage
    // 5's first form screen is where that arrived: the field-level error
    // message the record-form template puts under a control, inside the card.
    //
    // Registered rather than fixed: the palette is Design's (Phase 4 rule 2),
    // and substituting a colour here would diverge the admin from the delivery
    // on the one pair a Design Request is already open about. The floor is
    // pinned by a test in design.spec.js, so a REGRESSION below 4.32 still
    // fails while the request is open.
    '.k-error',
];

module.exports = { test, expect: base.expect, login, ROLES, passwordFor, KNOWN_DELIVERY_GAPS };
