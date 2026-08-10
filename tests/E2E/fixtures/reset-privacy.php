<?php

/**
 * Klytos CMS — the disposable data subject entry 26 (Privacy) is driven against.
 *
 * Every erasure this screen performs is IRREVERSIBLE by design: an account is
 * anonymized, form submissions are deleted, audit entries lose their author.
 * Driving that against a seeded role would burn the seed for every other spec in
 * the run, so entry 26 gets a subject of its own that exists to be destroyed.
 *
 * It is rebuilt through the product's OWN writers — `UserManager::create()`,
 * `AuditLog::record()`, `StorageInterface::write()` — and never by writing JSON
 * by hand, so a fixture that appears to work while the product's writer is
 * broken is not possible (the rule `reset-consent.php` records).
 *
 * The subject is given three sections rather than one, because the seeded roles
 * carry only `core:user_account` and a one-row table cannot show a partial
 * erasure, a per-section method, or a skipped row — the states this screen is
 * specified in terms of.
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-privacy.php            rebuild the subject
 *   php tests/E2E/fixtures/reset-privacy.php --status   print what exists
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

/** The username every entry-26 test searches for. */
const E2E_SUBJECT_USERNAME = 'privacy-subject';

/** Its email — the key `core:form_submissions` matches on. */
const E2E_SUBJECT_EMAIL = 'privacy-subject@example.invalid';

/** The two form submissions the subject owns, so a deletion has a count > 1. */
const E2E_SUBMISSION_IDS = [ 'e2e-privacy-sub-1', 'e2e-privacy-sub-2' ];

$users   = $app->getUserManager();
$storage = $app->getStorage();
$audit   = $app->getAuditLog();

$status = in_array( '--status', array_slice( $argv, 1 ), true );

$existing = $users->getByUsername( E2E_SUBJECT_USERNAME );

if ( $status ) {
    if ( $existing === null ) {
        echo "subject: ABSENT\n";
        exit( 0 );
    }

    echo "subject: {$existing['id']} ({$existing['username']} · {$existing['email']} · {$existing['role']})\n";

    foreach ( $app->getPrivacyManager()->collectErasableData( $existing['id'] ) as $section ) {
        echo "  {$section['id']} erasable=" . ( $section['erasable'] ? '1' : '0' )
            . " method={$section['erasure_method']} n={$section['item_count']}\n";
    }

    exit( 0 );
}

// ─── Tear the previous subject down, whatever state it was left in ──────

if ( $existing !== null ) {
    $users->delete( $existing['id'] );
}

/*
 * A previous run's ANONYMIZED subject keeps the account but randomises the
 * username, so `getByUsername()` above cannot find it and it would accumulate
 * one dead row per run. It is found by the marker the fixture itself wrote.
 */
foreach ( $users->list() as $user ) {
    if ( ( $user['first_name'] ?? '' ) === 'E2E' && ( $user['last_name'] ?? '' ) === 'PrivacySubject' ) {
        $users->delete( $user['id'] );
        continue;
    }

    // An anonymized subject has lost its names as well; its email is the tell.
    $email = (string) ( $user['email'] ?? '' );

    if (
        str_starts_with( $email, 'deleted_' )
        && str_ends_with( $email, '@anonymized.invalid' )
        && ( $user['display_name'] ?? '' ) === __( 'privacy.deleted_user' )
    ) {
        $users->delete( $user['id'] );
    }
}

foreach ( E2E_SUBMISSION_IDS as $id ) {
    if ( $storage->exists( 'form-submissions', $id ) ) {
        $storage->delete( 'form-submissions', $id );
    }
}

// ─── Rebuild it ─────────────────────────────────────────────────────────

$subject = $users->create( [
    'username'     => E2E_SUBJECT_USERNAME,
    'password'     => 'playground-privacy-subject-2026',
    'email'        => E2E_SUBJECT_EMAIL,
    'role'         => 'viewer',
    'display_name' => 'Privacy Subject',
    'first_name'   => 'E2E',
    'last_name'    => 'PrivacySubject',
] );

foreach ( E2E_SUBMISSION_IDS as $index => $id ) {
    $storage->write( 'form-submissions', $id, [
        'id'        => $id,
        'form_id'   => 'e2e-contact',
        'email'     => E2E_SUBJECT_EMAIL,
        'name'      => 'Privacy Subject',
        'message'   => 'Entry 26 fixture submission ' . ( $index + 1 ) . '.',
        'created_at' => klytos_gmdate( 'Y-m-d H:i:s' ),
    ] );
}

// Three audit entries, so `core:audit_log` reports a count a test can assert on.
for ( $i = 1; $i <= 3; $i++ ) {
    $audit->record(
        'e2e_privacy_fixture',
        'user',
        $subject['id'],
        [ 'sequence' => $i ],
        'admin',
        $subject['id'],
        E2E_SUBJECT_USERNAME,
    );
}

echo "subject rebuilt: {$subject['id']}\n";

foreach ( $app->getPrivacyManager()->collectErasableData( $subject['id'] ) as $section ) {
    echo "  {$section['id']} erasable=" . ( $section['erasable'] ? '1' : '0' )
        . " method={$section['erasure_method']} n={$section['item_count']}\n";
}
