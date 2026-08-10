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
    return loginAs( page, role, passwordFor( role ) );
}

/**
 * Log in through the real form as ANY account, seeded or not.
 *
 * Entry 27 (Profile) edits the person who is logged in — including their
 * password — so it is driven as a disposable account of its own
 * (`fixtures/reset-profile.php`) rather than as a seeded role whose password
 * `passwordFor()` hardcodes for every other spec in the run.
 *
 * `login()` above delegates here rather than duplicating the form walk: one
 * definition of "a real login", two entry points.
 */
async function loginAs( page, username, password ) {
    await page.goto( '/installer/admin/login.php' );

    // login.php predates the data-testid convention (Keel v5.0.0) — it is
    // manifest entry "Login" and gets its identifiers when stage 5 rebuilds it.
    // Until then, locate by the form's own name attributes.
    await page.locator( 'input[name="username"]' ).fill( username );
    await page.locator( 'input[name="password"]' ).fill( password );
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
    // DR-005 gap 2, light theme, arriving on its THIRD surface. The request
    // itself measured --color-acento on --fondo-ventana at 4.23:1 in light,
    // and stage 6's canvas renders exactly that pair: template-editor-split.md
    // §1 requires the page editor's slug "in --color-acento", and the canvas
    // sits on --fondo-ventana outside any card. Both halves are the
    // delivery's, so it is registered rather than substituted (Phase 4 rule 2)
    // and the ratio is pinned as a FLOOR in page-editor.spec.js.
    //
    // Scoped to the URL line, never a bare .k-control: the same control inside
    // a card passes, and excluding it everywhere would hide a real defect to
    // silence a known one.
    // DR-005, ADDENDUM 3 — the sidebar's current nav item AGAIN, and this time
    // its COUNT. Addendum 2 registered the item's LABEL (--color-acento on
    // --fila-seleccion); the count beside it is --texto-sutil over the same
    // tint and measures 3.39:1 dark / 3.96:1 light — worse than the label, and
    // a different token pair, so it is not covered by that exclusion.
    //
    // It took until stage 6 to render because the current nav item has to
    // carry a count for the pair to exist at all, and every earlier spec's
    // screen (Design, Logs, Settings, Consent…) is a nav item with no count.
    // The page editor's parent is Pages, which has one. L-037's shape a third
    // time: the right scope, the right viewport, the wrong SCREEN.
    //
    // Both halves are §1's, so it is Design's (Phase 4 rule 2). Registered,
    // never substituted, with both ratios pinned as floors.
    '.k-nav-item[aria-current="page"] .k-nav-count',

    '.k-editor-url .k-control',

    '.k-error',

    // DR-005 gap 3 AGAIN, dark theme, on the other half of the population the
    // request itself named: "every error message and destructive button inside
    // a card". The message arrived on entry 3; the BUTTON arrives on entry 6,
    // where the re-auth step's Confirm is `--color-peligro` on
    // `--fondo-elevado` — the identical pair, measured at 4.32:1.
    //
    // It is not new and it is not entry 6's: `.k-btn--destructive` has existed
    // since stage 3 and entry 19 and entry 39 both render it. Neither ever
    // scanned it, because in both screens it appears only in the ARMED state of
    // a two-step confirm and every axe run there measured the default, the
    // populated and the error states. This screen's re-auth pass is the first
    // to scan a page with an armed destructive control on it — L-030's shape
    // once more, this time about which STATE a pass reaches rather than which
    // element it scopes to.
    //
    // Registered rather than fixed, exactly like the message: the palette is
    // Design's (Phase 4 rule 2). The floor is pinned in security.spec.js.
    '.k-btn--destructive',

    // DR-005 ADDENDUM 2, both themes. The SIDEBAR's current item:
    // --color-acento on --fila-seleccion composited over the sidebar's own
    // background.
    //   dark   #3CC3B2 on #2B4C4B   4.31:1
    //   light  #0E8074 on #D7E4E5   3.70:1   <- the worse of the two
    // Both recomputed independently from the token hexes; axe reports 4.3 and
    // 3.69 and the arithmetic agrees. `template-shell.md` specifies this pair,
    // so it is Design's (Phase 4 rule 2) exactly like the three gaps above.
    //
    // It has been on EVERY ported screen since stage 2 and no pass saw it,
    // because design.spec.js, logs.spec.js and pages.spec.js all scope axe to
    // `#main` or to a section. The shell is the one component every screen
    // carries and it was the one component nothing scanned — L-031's rule
    // ("the unverified fraction is not a random fraction") arriving on the
    // tooling rather than on the product. Entry 19's spec scans the whole page,
    // which is what surfaced it.
    //
    // Both ratios are pinned as FLOORS in shell.spec.js, so an open request
    // cannot become a licence to regress.
    '.k-nav-item[aria-current="page"] .k-nav-label',
];

/*
 * Surfaces that are NOT part of the product a normal install serves, and are
 * therefore outside the redesign's accessibility contract.
 *
 * Kept apart from KNOWN_DELIVERY_GAPS deliberately: that list is a register of
 * things DESIGN owes an answer on, and padding it with scaffolding would make
 * the open-request count lie. These are excluded because the redesign never
 * covered them, not because a Design Request is pending.
 *
 * `.devbar` renders only when `$app->isDevMode()` AND the user is owner/admin
 * (installer/admin/templates/footer.php), so it reaches no ordinary install. It
 * does carry real defects — measured on entry 19's whole-page scan, light
 * theme: `.devbar-time--fast` and `.devbar-memory--ok` at 1.91:1, and
 * `.devbar-value > span` at 2.15:1, plus a scrollable `.devbar-tab-content`
 * with no focusable content (WCAG 2.1.1). They are recorded here rather than
 * silently dropped, and they belong to whichever slice takes the dev bar on.
 */
const DEV_ONLY_SURFACES = [ '.devbar' ];

module.exports = {
    test,
    expect: base.expect,
    login,
    loginAs,
    ROLES,
    passwordFor,
    KNOWN_DELIVERY_GAPS,
    DEV_ONLY_SURFACES,
};
