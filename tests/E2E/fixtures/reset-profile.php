<?php

/**
 * Klytos CMS — the disposable account entry 27 (Profile) is driven AS.
 *
 * This screen edits the person who is logged in, and two of the things it edits
 * are load-bearing for the rest of the tier: the account's PASSWORD, which
 * `fixtures.js::passwordFor()` hardcodes for the four seeded roles, and its
 * email, which other specs match on. Driving entry 27 as a seeded role would
 * therefore burn the seed for every spec that runs after it — the same reason
 * entry 26 got a subject of its own (D-099), arriving from the other direction:
 * there the screen destroyed somebody else's data, here it destroys the tester's
 * own credentials.
 *
 * It is rebuilt through the product's OWN writer — `UserManager::create()` —
 * never by writing JSON by hand, so a fixture that appears to work while the
 * product's writer is broken is not possible.
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-profile.php            rebuild the account
 *   php tests/E2E/fixtures/reset-profile.php --status   print what exists
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

$app = \Klytos\Core\App::getInstance();
$app->boot();

/** The account every entry-27 test logs in as. */
const E2E_PROFILE_USERNAME = 'profile-subject';

/** Its password. The spec changes it, and this file is what puts it back. */
const E2E_PROFILE_PASSWORD = 'playground-profile-subject-2026';

/** Its email. Uniqueness is enforced by the manager, so it is rebuilt too. */
const E2E_PROFILE_EMAIL = 'profile-subject@example.invalid';

/**
 * The identity values every test starts from.
 *
 * They are asserted by name in the spec: a test that varies an input reads that
 * input back (L-035), and it can only do that against a known starting point.
 */
const E2E_PROFILE_IDENTITY = [
    'first_name' => 'Profile',
    'last_name'  => 'Subject',
    'bio'        => 'The account entry 27 is driven as.',
    'website'    => 'https://example.invalid/profile-subject',
];

$users = $app->getUserManager();

$status = in_array( '--status', array_slice( $argv, 1 ), true );

$existing = $users->getByUsername( E2E_PROFILE_USERNAME );

if ( $status ) {
    if ( $existing === null ) {
        echo "account: ABSENT\n";
        exit( 0 );
    }

    echo "account: {$existing['id']} ({$existing['username']} · {$existing['email']} · {$existing['role']})\n";
    echo '  first_name=' . ( $existing['first_name'] ?? '' )
        . ' last_name=' . ( $existing['last_name'] ?? '' )
        . ' website=' . ( $existing['website'] ?? '' ) . "\n";

    exit( 0 );
}

// ─── Tear the previous account down, whatever state it was left in ──────

if ( $existing !== null ) {
    $users->delete( $existing['id'] );
}

/*
 * A run that changed the email and then died leaves an account this file's
 * `getByUsername()` still finds — the username is not editable, so that lookup
 * is sufficient here and no marker scan is needed. What is NOT sufficient is
 * assuming the delete above ran: an account left behind would collide on the
 * email's uniqueness check inside `create()` and fail loudly, which is the
 * behaviour wanted.
 */

// ─── Rebuild it ─────────────────────────────────────────────────────────

$subject = $users->create( array_merge(
    [
        'username'     => E2E_PROFILE_USERNAME,
        'password'     => E2E_PROFILE_PASSWORD,
        'email'        => E2E_PROFILE_EMAIL,
        'role'         => 'editor',
        'display_name' => 'Profile Subject',
    ],
    E2E_PROFILE_IDENTITY
) );

echo "account rebuilt: {$subject['id']} ({$subject['username']} · {$subject['email']})\n";
