<?php

/**
 * Klytos CMS — assertions run inside an upgraded install (Sprint 1, slice 3).
 *
 * Driven by scripts/dev/upgrade-test.sh. Kept separate from that script because
 * these checks must run INSIDE the installed application (booting its App, using
 * its own helpers), while the shell around it only lays out files and drives the
 * installer over HTTP.
 *
 * Usage: php upgrade-assert.php <admin-dir> <pre-upgrade|post-upgrade>
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

$adminDir = $argv[1] ?? '';
$phase    = $argv[2] ?? '';

$phases = [ 'pre-upgrade', 'post-upgrade', 'break-migration', 'boot-must-survive' ];

if ( ! is_dir( $adminDir ) || ! in_array( $phase, $phases, true ) ) {
    fwrite( STDERR, 'Usage: php upgrade-assert.php <admin-dir> <' . implode( '|', $phases ) . ">\n" );
    exit( 1 );
}

// Buffer everything. Auth sends session/security headers when it is exercised,
// and this script prints progress as it goes, so without a buffer PHP reports
// "headers already sent" on every assertion — noise from the harness, not a
// product defect, and exactly the kind of noise that trains a reader to skip
// real warnings. Flushed at exit.
ob_start();

$failures = 0;

/**
 * Record an assertion result.
 *
 * @param  bool   $condition Whether the assertion held.
 * @param  string $message   What was being asserted.
 * @return void
 */
function check( bool $condition, string $message ): void
{
    global $failures;

    if ( $condition ) {
        echo "   PASS  {$message}\n";
        return;
    }

    echo "   FAIL  {$message}\n";
    ++$failures;
}

// The installed tree is a normal Klytos application; boot it the way the
// product does rather than reimplementing its wiring.
require_once $adminDir . '/core/app.php';

$app = \Klytos\Core\App::getInstance();

check( $app->isInstalled(), 'the install is recognised as installed' );

// Boot must not fatal. Before slice 3 an install whose v1 config lacked a usable
// admin_email threw straight out of App::boot() on every request; that is the
// crash the Step 10b try/catch now contains.
//
// For the boot-must-survive phase this line IS the assertion: the preceding
// phase deliberately put the install into the state that used to be fatal — no
// owner record, and a config the migration cannot use — so reaching the next
// line at all means the catch did its job. It has to happen in a fresh process,
// because App is a singleton that boots once and cannot be rebooted.
$app->boot();
check( true, 'App::boot() completed without a fatal' );

$storage = $app->getStorage();
$users   = new \Klytos\Core\UserManager( $storage );

if ( $phase === 'boot-must-survive' ) {
    check(
        $users->findOwner() === null,
        'boot left the install with no owner, rather than half-creating one'
    );

    // Fail-closed is the whole point of surviving: the app must be UP and
    // refusing, not up and permissive.
    $_SESSION = [
        'klytos_auth'        => true,
        'klytos_user'        => 'upgradeowner',
        'klytos_login_time'  => time(),
        'klytos_last_active' => time(),
    ];

    check( klytos_current_user() === null, 'no session is promoted into the missing-owner gap' );
    check( ! klytos_has_permission( 'users.manage' ), 'the surviving app denies owner-only permissions' );
    check( ! klytos_has_permission( 'pages.view' ), 'the surviving app denies every permission' );

    echo "\n   " . ( $failures === 0 ? 'boot survived a failing migration and fails closed' : "{$failures} assertion(s) FAILED" ) . "\n";
    ob_end_flush();
    exit( $failures === 0 ? 0 : 1 );
}

if ( $phase === 'break-migration' ) {
    // Put the install into the exact state that used to crash every request:
    // no owner record, and a config migrateFromV1Config() must reject. Writing
    // through the product's own encrypted storage rather than editing files by
    // hand — a hand-written config.json.enc would simply fail to decrypt and
    // would prove something else entirely.
    foreach ( $storage->list( 'users' ) as $user ) {
        $storage->delete( 'users', $user['id'] );
    }

    $config = $app->getConfig();
    unset( $config['admin_email'] );

    $encryption  = new \Klytos\Core\Encryption( $adminDir . '/config/.encryption_key' );
    $fileStorage = new \Klytos\Core\FileStorage( $encryption, $adminDir . '/data' );
    $fileStorage->writeTo( $adminDir . '/config', 'config.json.enc', $config );

    check( $users->findOwner() === null, 'the install now has no owner record' );
    check( ! isset( $fileStorage->readFrom( $adminDir . '/config', 'config.json.enc' )['admin_email'] ), 'the config no longer carries a usable admin_email' );

    echo "   -- install broken deliberately; the next boot must survive it\n";
    ob_end_flush();
    exit( $failures === 0 ? 0 : 1 );
}

$owner = $users->findOwner();
check( $owner !== null, 'the install has an owner record' );
check(
    ( $owner['username'] ?? '' ) === 'upgradeowner',
    'the owner is the account created by the previous version\'s installer'
);

$ownerCount = count(
    array_filter(
        $storage->list( 'users' ),
        static fn( array $u ): bool => ( $u['role'] ?? '' ) === 'owner'
    )
);
check( $ownerCount === 1, "exactly one owner exists (found {$ownerCount})" );

if ( $phase === 'pre-upgrade' ) {
    echo "   -- pre-upgrade state verified on VERSION " . ( $app->getConfig()['version'] ?? 'n/a' ) . "\n";
    ob_end_flush();
    exit( $failures === 0 ? 0 : 1 );
}

// ── post-upgrade only: the slice-3 properties, on a REAL upgraded install ────

// The migration wired into boot (Step 10b) is idempotent: it has now run on a
// booted install that already had an owner, and must not have minted a second.
$users->migrateFromV1Config( $app->getConfig() );
$ownerCountAfter = count(
    array_filter(
        $storage->list( 'users' ),
        static fn( array $u ): bool => ( $u['role'] ?? '' ) === 'owner'
    )
);
check( $ownerCountAfter === 1, "the migration is idempotent on an upgraded install (found {$ownerCountAfter})" );

// The upgraded owner can still authenticate and still holds owner rights —
// the "did the fail-closed change lock out the installed base?" question,
// answered against a real upgraded install rather than a fixture.
$_SESSION = [
    'klytos_auth'        => true,
    'klytos_user'        => $owner['username'],
    'klytos_user_id'     => $owner['id'],
    'klytos_login_time'  => time(),
    'klytos_last_active' => time(),
];

$current = klytos_current_user();
check( $current !== null, 'the upgraded owner still resolves after the fallback removal' );
check( ( $current['role'] ?? '' ) === 'owner', 'the upgraded owner still holds the owner role' );
check( klytos_has_permission( 'users.manage' ), 'the upgraded owner still holds owner-only permissions' );

// NEW-01 on a real upgraded install: a session with no klytos_user_id must be
// denied, not promoted. This is the escalation the whole slice exists to close.
$_SESSION = [
    'klytos_auth'        => true,
    'klytos_user'        => 'someone',
    'klytos_login_time'  => time(),
    'klytos_last_active' => time(),
];

check( klytos_current_user() === null, 'a session without klytos_user_id is DENIED, not promoted (NEW-01)' );
check( ! klytos_has_permission( 'users.manage' ), 'that session holds no owner permission' );
check( ! klytos_has_permission( 'pages.view' ), 'that session holds no permission at all' );

echo "\n   " . ( $failures === 0 ? 'all post-upgrade assertions passed' : "{$failures} assertion(s) FAILED" ) . "\n";

ob_end_flush();

exit( $failures === 0 ? 0 : 1 );
