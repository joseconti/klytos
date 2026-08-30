<?php

/**
 * Klytos CMS — seed a known block population for manifest entry 21.
 *
 * The gallery, its per-category grouping and the usage count are all functions
 * of the `blocks` and `page-templates` collections. Against an empty set the
 * screen renders its empty state and **none of them can be asserted** — a check
 * over a zero population is not evidence (D-079).
 *
 * Everything goes through the real `BlockManager::save()` and
 * `PageTemplateManager::save()` / `addBlock()`, so the validation, the category
 * fallback and the record shape are the product's own.
 *
 * The population is chosen so the two things §21 asks for are unambiguous:
 *
 *   - THREE categories, so "grouped by category" cannot pass by rendering one
 *     group that happens to hold everything.
 *   - usage counts of 2, 1 and 0, so the count is a measured number rather than
 *     a value that would look right at any total — and the zero branch, which
 *     draws a different sentence, is on the same page as the others.
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-blocks.php          seed
 *   php tests/E2E/fixtures/reset-blocks.php --off    remove
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

$app = \Klytos\Core\App::getInstance();
$app->boot();

$blocks    = new \Klytos\Core\BlockManager( $app->getStorage() );
$templates = $app->getPageTemplateManager();
$storage   = $app->getStorage();

/** Everything this fixture writes carries the prefix, so removal is exact. */
const PREFIX = 'e2eblock-';
const TPL     = 'e2etpl-';

$removed = 0;

foreach ( $storage->listWithIds( 'blocks' ) as $id => $record ) {
    if ( str_starts_with( (string) $id, PREFIX ) ) {
        $storage->delete( 'blocks', (string) $id );
        $removed++;
    }
}

foreach ( $storage->listWithIds( 'page-templates' ) as $id => $record ) {
    if ( str_starts_with( (string) $id, TPL ) ) {
        $storage->delete( 'page-templates', (string) $id );
        $removed++;
    }
}

if ( in_array( '--off', $argv, true ) ) {
    echo "reset-blocks: removed {$removed} record(s)\n";
    exit( 0 );
}

$plan = [
    // [ id suffix, name, category ]
    ['hero',      'Hero banner',   'structure'],
    ['features',  'Feature grid',  'content'],
    ['testimony', 'Testimonials',  'social-proof'],
];

foreach ( $plan as [ $suffix, $name, $category ] ) {
    $blocks->save( [
        'id'       => PREFIX . $suffix,
        'name'     => $name,
        'category' => $category,
        'html'     => '<section class="e2e-block"><h2>' . $name . '</h2></section>',
    ] );
}

/*
 * Two templates, so the counts differ: `hero` in both, `features` in one,
 * `testimony` in none. A count that is the same everywhere would pass whatever
 * the code did.
 */
$templates->save( ['type' => TPL . 'landing', 'name' => 'E2E landing'] );
$templates->save( ['type' => TPL . 'about',   'name' => 'E2E about'] );

$templates->addBlock( TPL . 'landing', PREFIX . 'hero' );
$templates->addBlock( TPL . 'landing', PREFIX . 'features' );
$templates->addBlock( TPL . 'about',   PREFIX . 'hero' );

echo "reset-blocks: removed {$removed}, wrote 3 blocks and 2 templates\n";
echo "  categories : structure, content, social-proof\n";
echo "  usage      : hero 2, features 1, testimony 0\n";
