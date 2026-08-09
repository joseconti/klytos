<?php

/**
 * Klytos CMS — log fixtures for the browser tier (manifest entry 41, Logs).
 *
 * The Logs screen has six states and five of them need a file of a particular
 * SHAPE — populated, empty, unreadable, over the 5,000-line truncation floor —
 * which the seeded playground has no reason to contain. This writes them.
 *
 * It resolves the directory through the product's own Logger rather than
 * guessing: the logs directory carries a random suffix that only the Logger
 * (and its encrypted config) knows, so a hand-built path would be wrong on
 * every install.
 *
 * Usage:  php tests/E2E/fixtures/seed-logs.php create|remove
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

$app = \Klytos\Core\App::getInstance();
$app->boot();

$dir = $app->getLogger()->getLogsDir();
if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
    fwrite( STDERR, "Could not create the logs directory at {$dir}\n" );
    exit( 1 );
}

/** The four fixture files, by role in the screen's state matrix. */
const FIXTURES = [
    'populated'  => 'debug-2026-08-01.log',
    'empty'      => 'debug-2026-08-02.log',
    'unreadable' => 'debug-2026-08-03.log',
    'truncating' => 'debug-2026-08-04.log',
];

$mode = $argv[1] ?? 'create';

if ( $mode === 'remove' ) {
    foreach ( FIXTURES as $name ) {
        $path = $dir . '/' . $name;
        if ( file_exists( $path ) ) {
            @chmod( $path, 0644 );
            unlink( $path );
        }
    }
    echo "removed\n";
    exit( 0 );
}

/*
 * The populated file. Every level the tint mapping distinguishes appears, the
 * ERROR line carries a JSON context (which is what the detail panel renders),
 * and one line is deliberately NOT in this Logger's format — a stray line is
 * shown as a message rather than dropped, and the screen must prove it.
 */
$populated = implode( "\n", [
    '[2026-08-01 09:00:01] [DEBUG] [core] Router matched /installer/admin/logs.php',
    '[2026-08-01 09:00:02] [INFO] [core] Site build finished',
    '[2026-08-01 09:00:03] [NOTICE] [klytos-forms] Submission stored {"form":3}',
    '[2026-08-01 09:00:04] [WARNING] [core] Cache directory is not writable {"path":"data/cache"}',
    '[2026-08-01 09:00:05] [ERROR] [core] Payment capture failed {"order":17,"gateway":"redsys"}',
    '[2026-08-01 09:00:06] [CRITICAL] [core] Storage backend unreachable',
    'Fatal-looking stray line that this Logger did not write',
] ) . "\n";

file_put_contents( $dir . '/' . FIXTURES['populated'], $populated );

// The empty file: zero bytes, which is a DIFFERENT state from unreadable.
file_put_contents( $dir . '/' . FIXTURES['empty'], '' );

/*
 * The unreadable file. Mode 0000 does not stop root, so a run as root produces
 * a readable file and the test that consumes this SKIPS rather than passing
 * vacuously — the same honesty rule LoggerReadFailureTest applies.
 */
$unreadable = $dir . '/' . FIXTURES['unreadable'];
// Re-creating over a previous run's 0000 file fails to open it — the fixture
// has to undo its own trap before it can rewrite it.
if ( file_exists( $unreadable ) ) {
    chmod( $unreadable, 0644 );
}
file_put_contents( $unreadable, "[2026-08-03 09:00:01] [INFO] [core] You cannot read me\n" );
chmod( $unreadable, 0000 );

/*
 * 5,200 lines — 200 past §2's floor, so the screen shows the last 5,000 and
 * says so. The first line is identifiable so a test can assert it is the one
 * that got cut.
 */
$lines = ['[2026-08-04 09:00:00] [INFO] [core] FIRST LINE — must be truncated away'];
for ( $i = 1; $i < 5200; $i++ ) {
    $lines[] = sprintf( '[2026-08-04 09:00:00] [INFO] [core] Filler line %d', $i );
}
file_put_contents( $dir . '/' . FIXTURES['truncating'], implode( "\n", $lines ) . "\n" );

echo json_encode( [
    'dir'        => $dir,
    'files'      => FIXTURES,
    'unreadable' => ! is_readable( $unreadable ),
] ) . "\n";
