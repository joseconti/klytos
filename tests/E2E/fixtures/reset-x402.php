<?php

/**
 * Klytos CMS — seed a known transaction population for manifest entry 18.
 *
 * The x402 dashboard's chart, its data table, its four stat cards and its two
 * detail cards are all functions of the transaction log. Against an empty log
 * the screen renders its empty state and **none of them can be asserted** — a
 * check over a zero population is not evidence (D-079).
 *
 * Everything is written through the real `TransactionLog::log()`, so whatever
 * the product does on the way in happens here too. It is idempotent: the day
 * collections this fixture owns are removed before it writes.
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-x402.php          seed
 *   php tests/E2E/fixtures/reset-x402.php --off    remove
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

$app = \Klytos\Core\App::getInstance();
$app->boot();

$storage = $app->getStorage();

/**
 * The day collections the log writes into, for the window the screen reads.
 *
 * `TransactionLog::log()` always writes into TODAY's collection — the date key
 * is `gmdate( 'Y-m-d' )` and no parameter overrides it — so a fixture that
 * wants a 30-day series has to write the day collections directly. That is
 * stated rather than hidden: it is the ONE place this fixture does not go
 * through the product's own writer, and the reason is that the writer has no
 * seam for it. The record shape below is copied from `log()` and must stay in
 * step with it.
 */
$days = [];
for ( $i = 0; $i < 30; $i++ ) {
    $days[] = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
}

$removed = 0;
foreach ( $days as $date ) {
    $collection = 'x402-transactions/' . $date;
    try {
        if ( $storage->exists( $collection, 'transactions' ) ) {
            $storage->delete( $collection, 'transactions' );
            $removed++;
        }
    } catch ( \Throwable $e ) {
        // Absent is the normal case; nothing to undo.
    }
}

if ( in_array( '--off', $argv, true ) ) {
    printf( "reset-x402: cleared %d day collection(s); none seeded.\n", $removed );
    exit( 0 );
}

// ─── Seed a deterministic 30-day series ──────────────────────────
//
// Deterministic on purpose: a random series makes the chart's headline — the
// total and the peak — unassertable, and the headline is the whole accessible
// answer §4 asks for.

$agents = ['GPTBot/1.0', 'ClaudeBot/1.0', 'PerplexityBot/1.0'];
$slugs  = ['home', 'about', 'pricing'];

$seeded = 0;
foreach ( $days as $i => $date ) {
    // Three days carry traffic; the peak is unambiguous and is day -3.
    $count = match ( $i ) {
        3       => 4,
        7       => 2,
        14      => 1,
        default => 0,
    };

    if ( $count === 0 ) {
        continue;
    }

    $records = [];
    for ( $n = 0; $n < $count; $n++ ) {
        $records[] = [
            'id'             => 'tx_fixture_' . $i . '_' . $n,
            'slug'           => $slugs[ $n % count( $slugs ) ],
            'format'         => 'html',
            'provider_id'    => 'fixture',
            'bot_user_agent' => $agents[ $n % count( $agents ) ],
            'bot_ip_hash'    => str_repeat( '0', 16 ),
            'amount_usd'     => '0.0100',
            'amount_raw'     => '10000',
            'network'        => 'base',
            'tx_hash'        => '',
            'facilitator_ok' => true,
            'license_type'   => 'inference',
            'created_at'     => $date . 'T12:00:00Z',
        ];
        $seeded++;
    }

    $storage->write( 'x402-transactions/' . $date, 'transactions', [
        'date'         => $date,
        'transactions' => $records,
    ] );
}

printf(
    "reset-x402: cleared %d, seeded %d transaction(s) across 3 day(s); peak is day -3.\n",
    $removed,
    $seeded
);
