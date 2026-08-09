<?php

/**
 * Klytos CMS — put the playground back into a known state for manifest entry 39.
 *
 * Entry 39 EDITS one record rather than creating them, so the equivalent of
 * entry 19's cleanup is to rebuild the record the spec edits: every `e2e-` post
 * type is deleted and one is created fresh, through the manager — never by
 * writing a storage file, so a create or a delete that has stopped working
 * shows up here rather than being papered over.
 *
 * The Per-locale slugs card needs configured locales to have any fields at all,
 * and the seeded playground has none — which is itself one of the card's two
 * states. `--locales=` therefore sets the site's locale list, and the default
 * (no argument) restores it to the seeded empty list. The spec never leaves the
 * playground carrying locales it invented.
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-post-type.php
 *   php tests/E2E/fixtures/reset-post-type.php --locales=en,es
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

$app = \Klytos\Core\App::getInstance();
$app->boot();

/** The post type every entry-39 test edits. */
const E2E_POST_TYPE = 'e2e-pt';

/** Locale names, so the label the card renders is a real name and not a code. */
const LOCALE_NAMES = [
    'en' => 'English',
    'es' => 'Español',
    'fr' => 'Français',
];

$requestedLocales = [];
foreach ( array_slice( $argv, 1 ) as $arg ) {
    if ( strpos( $arg, '--locales=' ) !== 0 ) {
        continue;
    }
    foreach ( explode( ',', substr( $arg, strlen( '--locales=' ) ) ) as $code ) {
        $code = trim( $code );
        if ( $code === '' ) {
            continue;
        }
        $requestedLocales[] = [
            'code' => $code,
            'name' => LOCALE_NAMES[ $code ] ?? $code,
        ];
    }
}

$manager = $app->getPostTypeManager();
$removed = [];

foreach ( $manager->list() as $postType ) {
    $id = (string) ( $postType['id'] ?? '' );
    if ( $id === '' || strpos( $id, 'e2e-' ) !== 0 ) {
        continue;
    }
    $manager->delete( $id );
    $removed[] = $id;
}

$manager->create( [
    'id'     => E2E_POST_TYPE,
    'name'   => 'E2E Post Type',
    'slug'   => 'e2e-pt',
    'editor' => 'gutenberg',
] );

// Absent argument means the seeded empty list, which is a real state of the
// screen and not merely "unset".
$app->getSiteConfig()->set( [ 'languages' => $requestedLocales ] );

echo json_encode( [
    'removed'   => $removed,
    'created'   => E2E_POST_TYPE,
    'locales'   => array_column( $requestedLocales, 'code' ),
] );
echo "\n";
