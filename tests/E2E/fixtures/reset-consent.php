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
] );
echo "\n";
