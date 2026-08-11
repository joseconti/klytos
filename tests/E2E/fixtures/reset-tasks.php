<?php

/**
 * Klytos CMS — seed a known task population for manifest entry 13 (Tasks).
 *
 * A check over a zero population is not evidence (D-079's rejected alternative,
 * and the reason both of that slice's skips were replaced by fixtures that build
 * their own rows). The Tasks screen's row actions, its grouping and its badges
 * cannot be asserted against a seed that happens to have no tasks — so this
 * fixture creates them **through the real `TaskManager`**, never by writing
 * storage by hand, so anything the manager does on the way in happens here too.
 *
 * It is idempotent: every task it owns is removed before it writes, so running
 * it twice leaves the same three rows rather than six.
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-tasks.php          seed three tasks
 *   php tests/E2E/fixtures/reset-tasks.php --off    remove them again
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

use Klytos\Core\TaskManager;

$app = \Klytos\Core\App::getInstance();
$app->boot();

$manager = new TaskManager( $app->getStorage() );

/** The marker that makes this fixture's own rows identifiable and removable. */
const FIXTURE_MARK = '[e2e-tasks]';

// ─── Remove whatever a previous run left ─────────────────────────

$removed = 0;
foreach ( $manager->list( 'all', '', 0 ) as $existing ) {
    if ( strpos( (string) ( $existing['description'] ?? '' ), FIXTURE_MARK ) !== false ) {
        $manager->delete( (string) $existing['id'] );
        $removed++;
    }
}

if ( in_array( '--off', $argv, true ) ) {
    printf( "reset-tasks: removed %d fixture task(s); none seeded.\n", $removed );
    exit( 0 );
}

// ─── Seed ────────────────────────────────────────────────────────

$seed = [
    [
        'page_slug'    => 'home',
        'css_selector' => 'h1',
        'description'  => FIXTURE_MARK . ' The hero heading still says "Lorem ipsum"',
        'priority'     => 'urgent',
    ],
    [
        'page_slug'    => 'about',
        'css_selector' => '',
        'description'  => FIXTURE_MARK . ' Add an alt text to the team photo',
        'priority'     => 'medium',
    ],
    [
        'page_slug'    => 'contact',
        'css_selector' => 'form',
        'description'  => FIXTURE_MARK . ' The contact form has no success message',
        'priority'     => 'low',
    ],
];

$created = [];
foreach ( $seed as $row ) {
    $created[] = $manager->create( $row );
}

// One of them is moved to `in_progress` through the manager, so the screen has
// TWO groups to draw rather than one — the grouped list is the thing under test
// and a single group would not exercise it.
$manager->update( (string) $created[1]['id'], ['status' => 'in_progress'] );

printf(
    "reset-tasks: removed %d, seeded %d (1 urgent open, 1 medium in_progress, 1 low open).\n",
    $removed,
    count( $created )
);
