<?php

/**
 * Klytos CMS — the licence record manifest entry 28 (Licence) is driven against.
 *
 * **This fixture writes one level BELOW the product's own writer, and that is a
 * property of the product rather than a shortcut.** `License` has exactly one
 * writer — `License::activate()` — and it cannot run here: it posts to
 * `plugins.joseconti.com` and stores whatever that server answers. A test tier
 * that reached the vendor's licence server would be measuring the vendor's
 * uptime, and it would be doing it from a machine with no licence to activate.
 *
 * So the fixture calls `StorageInterface::writeTo()` with the SAME file name and
 * the SAME field set `activate()` writes (`core/license.php:75-87`) — the layer
 * immediately under the network, not a hand-rolled JSON file. If that field set
 * ever changes, this fixture is wrong in the same commit, which is the point.
 *
 * Four states, because the screen is specified in terms of them:
 *
 *   --none      no licence at all (the empty state: the Plan card's sentence and
 *               action, the Key card's "no key is stored")
 *   --valid     an active licence (the facts, the readonly key, Copy, Check now)
 *   --expired   the §28 degraded delta, and the status bar's one fact
 *   --revoked   the same, plus the grace-period line
 *   --status    print what is stored, change nothing
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-licence.php --valid
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

use Klytos\Core\Helpers;

$app = \Klytos\Core\App::getInstance();
$app->boot();

/** The file `License` reads and writes — `License::LICENSE_FILE`, which is private. */
const E2E_LICENCE_FILE = 'license.json.enc';

/**
 * The key every entry-28 test asserts on.
 *
 * Deliberately not key-shaped: a 56-character hex string in a tracked file is
 * exactly what this slice removed from `admin/license.php`, and a fixture is no
 * better a place for one. It is long enough to prove the field does not
 * truncate and obviously synthetic to anyone who reads it.
 */
const E2E_LICENCE_KEY = 'e2e-not-a-real-licence-key-0000000000000000000000000000';

$storage    = $app->getStorage();
$configPath = $app->getConfigPath();

$args  = array_slice( $argv, 1 );
$state = 'valid';
foreach ( [ 'none', 'valid', 'expired', 'revoked', 'status' ] as $candidate ) {
    if ( in_array( '--' . $candidate, $args, true ) ) {
        $state = $candidate;
    }
}

/** Read what is stored now, without asserting that anything is. */
$current = null;
try {
    $current = $storage->readFrom( $configPath, E2E_LICENCE_FILE );
} catch ( \Throwable $e ) {
    $current = null;
}

if ( $state === 'status' ) {
    if ( $current === null ) {
        echo "licence: ABSENT\n";
        exit( 0 );
    }
    printf(
        "licence: %s · plan %s · domain %s\n",
        (string) ( $current['license_status'] ?? '?' ),
        (string) ( $current['plan'] ?? '?' ),
        (string) ( $current['domain'] ?? '?' )
    );
    exit( 0 );
}

if ( $state === 'none' ) {
    /*
     * `StorageInterface` has no `deleteFrom()`, so the file is removed with the
     * filesystem call the flat-file backend's own `writeTo()` mirrors. This is
     * the one place the fixture cannot stay on the interface, and it is stated
     * rather than hidden: on the database backend there is nothing to unlink and
     * the record is overwritten as `missing` instead.
     */
    $path = rtrim( $configPath, '/' ) . '/' . E2E_LICENCE_FILE;
    if ( is_file( $path ) ) {
        unlink( $path );
        echo "licence: removed\n";
    } else {
        echo "licence: already absent\n";
    }

    if ( is_file( $path ) ) {
        fwrite( STDERR, "licence: FAILED to remove {$path}\n" );
        exit( 1 );
    }
    exit( 0 );
}

$siteUrl = Helpers::siteUrl( '' );
$domain  = parse_url( $siteUrl, PHP_URL_HOST ) ?: $siteUrl;

// The same field set `License::activate()` writes, in the same order.
$record = [
    'license_key'        => E2E_LICENCE_KEY,
    'license_status'     => $state,
    'license_salt'       => 'e2e-salt',
    'domain'             => $domain,
    'site_url'           => $siteUrl,
    'activated_at'       => Helpers::now(),
    'last_verified'      => Helpers::now(),
    'plan'               => 'pro',
    'grace_period_until' => null,
];

if ( $state === 'revoked' ) {
    // `verify()` sets the grace period the moment it records a revocation, so a
    // revoked record without one is a state the product cannot reach.
    $record['grace_period_until'] = ( new \DateTimeImmutable( '+14 days' ) )->format( 'c' );
}

$storage->writeTo( $configPath, E2E_LICENCE_FILE, $record );

// Read it back through the product, not through the variable just written.
$written = $app->getLicense()->getStatus();
if ( (string) ( $written['license_status'] ?? '' ) !== $state ) {
    fwrite( STDERR, "licence: FAILED — read back '" . (string) ( $written['license_status'] ?? '' ) . "'\n" );
    exit( 1 );
}

printf( "licence: %s · plan %s · domain %s\n", $state, (string) $written['plan'], (string) $written['domain'] );
