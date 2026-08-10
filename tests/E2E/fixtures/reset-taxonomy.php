<?php

/**
 * Klytos CMS — the taxonomy screen's fixture (manifest entry 32).
 *
 * Entry 32 is scoped to ONE taxonomy of ONE post type: `taxonomy.php` refuses
 * to render without both `post_type` and `taxonomy` in the query string. So the
 * fixture's job is to put a known post type on the playground carrying BOTH
 * kinds of taxonomy the product can store, because the add-term form's fourth
 * field exists only for one of them:
 *
 *   - `e2e-tax-cat`  hierarchical → the Parent field is rendered
 *   - `e2e-tax-tag`  flat         → the Parent field is absent, not disabled
 *
 * Everything is written through `PostTypeManager`, never by hand into storage —
 * the same rule `reset-content-model.php` follows, and for the same reason: a
 * fixture that writes storage directly stops testing the writer, so a writer
 * that breaks still leaves the tier green.
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-taxonomy.php            re-create the fixture
 *   php tests/E2E/fixtures/reset-taxonomy.php --clean    remove it
 *
 * Only the `e2e-` prefix is ever touched.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

$app = \Klytos\Core\App::getInstance();
$app->boot();

$manager = $app->getPostTypeManager();

const POST_TYPE = 'e2e-tax';
const HIER      = 'e2e-tax-cat';
const FLAT      = 'e2e-tax-tag';

$clean = in_array( '--clean', $argv, true );

// Always start from nothing: `addTaxonomy` and `addTerm` both refuse a
// duplicate, so a re-run has to remove before it creates.
foreach ( $manager->list() as $postType ) {
    if ( ( $postType['id'] ?? '' ) === POST_TYPE ) {
        $manager->delete( POST_TYPE );
    }
}

if ( $clean ) {
    echo json_encode( [ 'state' => 'clean' ] );
    echo "\n";
    return;
}

$manager->create( [
    'id'   => POST_TYPE,
    'name' => 'E2E taxonomy host',
    'slug' => 'e2e-tax',
] );

$manager->addTaxonomy( POST_TYPE, [
    'id'           => HIER,
    'name'         => 'E2E categories',
    'slug'         => 'e2e-categories',
    'hierarchical' => true,
] );

$manager->addTaxonomy( POST_TYPE, [
    'id'           => FLAT,
    'name'         => 'E2E tags',
    'slug'         => 'e2e-tags',
    'hierarchical' => false,
] );

/*
 * Clear whatever the term collections already hold.
 *
 * Deleting the post type above is NOT enough on a playground seeded before
 * `PostTypeManager::delete()` was fixed: that method re-read the record it had
 * just removed, so its cleanup never ran and every term of every deleted post
 * type is still in storage (tests/Unit/PostTypeTermCleanupTest.php). Every real
 * install carries the same orphans, so a fixture that assumed a clean slate
 * would be testing a world that does not exist.
 *
 * The removal goes through `deleteTerm()` — the product's own writer — so a
 * delete that stops working also shows up here, rather than being papered over
 * by unlinking storage files.
 */
foreach ( [ HIER, FLAT ] as $taxonomy ) {
    foreach ( $manager->listTerms( POST_TYPE, $taxonomy ) as $term ) {
        $manager->deleteTerm( POST_TYPE, $taxonomy, (string) ( $term['slug'] ?? '' ) );
    }
}

// One parent and one child in the hierarchical taxonomy, so the Parent select
// has something to offer and the nesting has something to express. The flat
// taxonomy is left EMPTY on purpose: its empty state is one of the states the
// spec drives.
$manager->addTerm( POST_TYPE, HIER, [
    'name'        => 'E2E parent term',
    'slug'        => 'e2e-parent-term',
    'description' => 'The term the child hangs from.',
] );

$manager->addTerm( POST_TYPE, HIER, [
    'name'        => 'E2E child term',
    'slug'        => 'e2e-child-term',
    'parent'      => 'e2e-parent-term',
    'description' => '',
] );

echo json_encode( [
    'state'        => 'seeded',
    'post_type'    => POST_TYPE,
    'hierarchical' => HIER,
    'flat'         => FLAT,
] );
echo "\n";
