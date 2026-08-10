<?php

/**
 * Klytos CMS — E2E fixture: a known webhook population for manifest entry 24.
 *
 * Seeded through the product's own manager (`WebhookManager::create()`), never
 * by hand-writing storage records — L-005: a fixture that bypasses the
 * application proves only that the fixture works, and it is exactly the real
 * API path that fires the real hooks and the real validation.
 *
 * ONE DELIBERATE DEVIATION, and it is stated rather than hidden. `create()`
 * refuses any URL SafeHttp will not fetch, so a seeded webhook necessarily
 * carries a PUBLIC address — and the screen under test has a "Send test" button
 * that would then make a real outbound POST to somebody else's server on every
 * run. So each record is created through `create()` (the validation, the hooks
 * and the generated secret are all real) and its `url` is then rewritten
 * through storage to a refused address. That is not a fictional state: it is
 * exactly the case `WebhookManager::sendHttpPost()`'s own comment describes — a
 * host that resolved publicly when the subscription was stored and resolves
 * privately by the time an event fires — which is why delivery re-validates at
 * all. The test send is therefore refused before any network I/O.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

use Klytos\Core\WebhookManager;

$app = \Klytos\Core\App::getInstance();
$app->boot();

$storage = $app->getStorage();
$manager = new WebhookManager( $storage );

/** A URL SafeHttp refuses at delivery, so no test send ever opens a socket. */
const E2E_REFUSED_URL = 'http://127.0.0.1/klytos-e2e-hook';

/** The address `create()` is given, which it must accept. */
const E2E_SEED_URL = 'https://example.com/klytos-e2e-hook';

// ─── Clear everything this screen reads ──────────────────────────
foreach ( $manager->list() as $existing ) {
    $manager->delete( (string) ( $existing['id'] ?? '' ) );
}

foreach ( $storage->list( 'webhook-logs' ) as $log ) {
    $id = (string) ( $log['id'] ?? '' );
    if ( $id !== '' ) {
        $storage->delete( 'webhook-logs', $id );
    }
}

// ─── Seed ────────────────────────────────────────────────────────
$seeds = [
    [
        'events'      => [ 'page.created', 'page.updated' ],
        'description' => 'E2E delivery target',
        'failures'    => 0,
        'status'      => 'active',
    ],
    [
        // The auto-disabled case: `deliver()` reaches it by itself after ten
        // consecutive failures, so the screen has to be able to say so.
        'events'      => [ 'build.failed' ],
        'description' => '',
        'failures'    => 11,
        'status'      => 'disabled',
    ],
];

$ids = [];

foreach ( $seeds as $seed ) {
    $created = $manager->create( [
        'url'         => E2E_SEED_URL,
        'events'      => $seed['events'],
        'description' => $seed['description'],
    ] );

    $id      = (string) $created['id'];
    $ids[]   = $id;
    $record  = $storage->read( 'webhooks', $id );

    $record['url']           = E2E_REFUSED_URL;
    $record['status']        = $seed['status'];
    $record['failure_count'] = $seed['failures'];

    $storage->write( 'webhooks', $id, $record );
}

// The ids are printed so the spec can address a specific endpoint's controls
// rather than reaching for `.first()` on the page, which is how a test starts
// asserting about whichever row happens to be drawn first.
echo json_encode( [ 'ids' => $ids ] ), "\n";
