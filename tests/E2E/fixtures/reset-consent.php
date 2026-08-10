<?php

/**
 * Klytos CMS — put the playground back into a known state for manifest entry 25.
 *
 * Two things this screen reads, and the spec needs both in a known shape:
 *
 *   - the consent CONFIG, restored to a fixed banner text, privacy URL and
 *     duration so the preview card and the persistence checks have a value to
 *     compare against.
 *   - the plugin DECLARATIONS, which are what the cookie audit draws. With
 *     `--declare` two are created — one in a required category and one in a
 *     non-required one — so the table has rows of both tones, and the
 *     declarations group has two rows to arm the two-step confirm on. Without
 *     it every declaration is removed, which is the card's EMPTY state and a
 *     state the manifest names rather than merely the absence of data.
 *
 * Everything goes through `ConsentManager` and never through a storage file, so
 * a save or a delete that has stopped working fails HERE instead of being
 * papered over by a fixture that writes JSON directly.
 *
 * One deliberate exception is recorded rather than hidden: the config is written
 * with `saveConfig()`, which is the same method the screen calls, so a fixture
 * that appeared to work while the product's own writer was broken is not
 * possible.
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-consent.php
 *   php tests/E2E/fixtures/reset-consent.php --declare
 *   php tests/E2E/fixtures/reset-consent.php --declare --categories
 *
 * `--categories` additionally stores a CUSTOM category, which exists to pin one
 * thing: saving from the admin screen must not delete it. The screen this
 * replaced wiped every custom category on every save, because it rebuilt the
 * list from a form field it never rendered.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

$app = \Klytos\Core\App::getInstance();
$app->boot();

/** The banner text every entry-25 test starts from. */
const E2E_BANNER_TEXT = 'We use our own cookies to run this site.';

/** The custom category `--categories` stores, and that a save must not destroy. */
const E2E_CUSTOM_CATEGORY = 'e2e-custom';

$declare    = in_array( '--declare', array_slice( $argv, 1 ), true );
$categories = in_array( '--categories', array_slice( $argv, 1 ), true );

$manager = $app->getConsentManager();

// Every declaration goes, whichever mode this run is in — a leftover from the
// previous test would change the audit's counts and the table's row count.
$removed = [];
foreach ( $manager->getPluginDeclarations() as $declaration ) {
    $id = (string) ( $declaration['plugin_id'] ?? '' );
    if ( $id === '' ) {
        continue;
    }
    $manager->deletePluginDeclaration( $id );
    $removed[] = $id;
}

$manager->saveConfig( [
    'enabled'     => true,
    'banner_text' => E2E_BANNER_TEXT,
    'privacy_url' => '/privacy',
    'cookie_days' => 365,
    'categories'  => $categories
        ? [
            E2E_CUSTOM_CATEGORY => [
                'name'        => 'E2E custom category',
                'description' => 'Stored so a save from the admin screen can be proven not to delete it.',
                'required'    => false,
            ],
        ]
        : [],
] );

$declared = [];

if ( $declare ) {
    // `necessary` is required, so its badge takes the always-on tone; `analytics`
    // is not, so the table has both tones and the tone rule has something to be
    // wrong about.
    $manager->savePluginDeclaration( [
        'plugin_id'   => 'e2e-essential',
        'name'        => 'E2E Essential',
        'category'    => 'necessary',
        'description' => 'Keeps the session.',
        'vendor'      => 'Klytos',
        'cookies'     => [
            [
                'name'        => 'e2e_session',
                'duration'    => 'Session',
                'description' => 'Keeps the visitor signed in.',
                'type'        => 'cookie',
            ],
        ],
        'scripts'     => [],
    ] );

    $manager->savePluginDeclaration( [
        'plugin_id'   => 'e2e-analytics',
        'name'        => 'E2E Analytics',
        'category'    => 'analytics',
        'description' => 'Measures traffic.',
        'vendor'      => 'E2E',
        'cookies'     => [
            [
                'name'        => 'e2e_visitor',
                'duration'    => '13 months',
                'description' => 'Visitor identifier.',
                'type'        => 'cookie',
            ],
            [
                'name'        => 'e2e_session_id',
                'duration'    => '30 minutes',
                'description' => 'Session identifier.',
                'type'        => 'cookie',
            ],
        ],
        'scripts'     => [ 'https://example.invalid/e2e.js' ],
    ] );

    $declared = [ 'e2e-essential', 'e2e-analytics' ];
}

/*
 * REMOVE THE GENERATED SITE THIS SPEC'S OWN SAVES PRODUCE.
 *
 * Saving on the Consent screen calls `BuildEngine::buildAll()` — it has to, or
 * the banner a visitor sees does not change — and `buildAll()` writes the
 * published site into the REPOSITORY ROOT. Two consequences, and the second one
 * is why this cleanup lives here rather than in `.gitignore` alone:
 *
 *  1. it dirtied the working tree on every run (now gitignored, D-092);
 *  2. `AdminGateHttpTest` asserts, for NEW-04, that no build output sits in the
 *     repository root — so an E2E run that left it made the PHP suite fail on
 *     code that was fine. That is L-025's shape exactly: two tiers sharing one
 *     working tree, each corrupting the other's preconditions.
 *
 * Scoped deliberately: only the paths `BuildEngine` generates, only relative to
 * the installer's own parent, and `.htaccess` is NEVER touched because it is
 * tracked source the build rewrites rather than output the build creates.
 */
$root = dirname( __DIR__, 3 );

$generated = [
    'home', 'about', 'contact', 'search', 'assets', '.well-known',
    'llms.txt', 'llms-full.txt', 'robots.txt', 'sitemap.xml',
    'search-index.json', 'x402-gate.php',
    // `index.html.md` is pure output. `index.html` and `.htaccess` are NOT in
    // this list and must never be: both are TRACKED source that the build
    // REWRITES, so deleting them would destroy repository content, and the
    // build rewrites them from whatever the playground happens to be seeded
    // with — which is why the rewrite must never be committed either.
    'index.html.md',
];

$removeTree = static function ( string $path ) use ( &$removeTree ): void {
    if ( is_file( $path ) || is_link( $path ) ) {
        @unlink( $path );
        return;
    }
    if ( ! is_dir( $path ) ) {
        return;
    }
    foreach ( scandir( $path ) ?: [] as $entry ) {
        if ( $entry === '.' || $entry === '..' ) {
            continue;
        }
        $removeTree( $path . '/' . $entry );
    }
    @rmdir( $path );
};

$cleaned = [];
foreach ( $generated as $name ) {
    $path = $root . '/' . $name;
    if ( file_exists( $path ) ) {
        $removeTree( $path );
        $cleaned[] = $name;
    }
}

// Read back through the product's own reader rather than reporting what was
// asked for: a fixture that prints its intentions proves nothing (L-035).
$stored = $manager->getConfig();
$audit  = $manager->getAuditReport();

echo json_encode( [
    'removed'          => $removed,
    'declared'         => $declared,
    'banner_text'      => $stored['banner_text'] ?? '',
    'cookie_days'      => $stored['cookie_days'] ?? null,
    'custom_categories' => array_keys( $stored['categories'] ?? [] ),
    'total_cookies'    => $audit['total_cookies'] ?? 0,
    'total_plugins'    => $audit['total_plugins'] ?? 0,
    'build_output_removed' => $cleaned,
] );
echo "\n";
