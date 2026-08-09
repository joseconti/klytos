// Playwright configuration — Klytos CMS browser-driven tier.
//
// Keel v5.1.0: a headless run is invisible, and an invisible run that reports
// "passed" asks the reader to take the runner's word for it. So the run stays
// headless by DEFAULT and always leaves something a human can open: `video` and
// `trace` are on for every test, and their artifacts land under
// `tests/E2E/artifacts/`, which is gitignored.
//
// Three declared run modes:
//   npm run test:e2e         headless (the default; what CI and every test point run)
//   npm run test:e2e:watch   headed, slowMo 250ms — TAKES THE SCREEN while it lasts
//   npm run test:e2e:ui      the interactive runner
//
// The playground is NOT started by this config. Port 8080 has been squatted in
// every session since 2026-07-19 (L-021), so the port is always explicit and the
// bind is always confirmed by hand before a run — see docs/playground.md:
//
//   export KPORT=8137
//   XDEBUG_MODE=off php scripts/dev/seed-playground.php --reset   # L-028
//   php -S 127.0.0.1:$KPORT -t . scripts/dev/router.php
//
// KPORT is read here so that changing the port stays a one-line edit in the shell.

const { defineConfig, devices } = require( '@playwright/test' );

const KPORT = process.env.KPORT || '8137';

module.exports = defineConfig( {
    testDir: './tests/E2E',
    outputDir: './tests/E2E/artifacts/test-results',

    // A failing test that only fails on a retry is a flaky test, and a flaky test
    // that is silently retried is a test nobody can trust. Zero retries, always.
    retries: 0,
    fullyParallel: false,
    workers: 1,

    reporter: [
        [ 'list' ],
        [ 'html', { outputFolder: './tests/E2E/artifacts/report', open: 'never' } ],
        [ 'json', { outputFile: './tests/E2E/artifacts/results.json' } ],
    ],

    use: {
        baseURL: `http://127.0.0.1:${ KPORT }`,

        // The recording. NOT conditional on failure: a passing run that nobody can
        // inspect is exactly the trust problem this settles.
        trace: 'on',
        video: 'on',
        screenshot: 'on',

        // Klytos ships 20 locales; a locator bound to visible text breaks on a
        // language switch. Locators use data-testid — see docs/03-technical-plan.md
        // "Element addressability".
        testIdAttribute: 'data-testid',

        ignoreHTTPSErrors: false,

        // Headed runs are for watching, so they need to be slow enough to watch.
        // `npm run test:e2e:watch` sets PWSLOWMO; a headless run leaves it at 0.
        launchOptions: {
            slowMo: Number( process.env.PWSLOWMO || 0 ),
        },
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices[ 'Desktop Chrome' ] },
        },
    ],
} );
