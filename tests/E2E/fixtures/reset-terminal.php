<?php

/**
 * Klytos CMS — the second factor entry 23 (Terminal) cannot be driven without.
 *
 * `installer/admin/terminal.php` refuses to render its terminal at all unless
 * the caller's account has 2FA active (`$has2fa`, terminal.php:36/42), and
 * `terminal.access` is granted to exactly ONE role — `['owner']`
 * (`user-manager.php:724`). Those two facts together decide the shape of this
 * fixture, and it is worth writing down because the obvious approach is closed:
 *
 *   A DISPOSABLE ACCOUNT IS IMPOSSIBLE HERE. Entry 26 and entry 27 were each
 *   given a subject of their own so a spec could not burn the seed for the specs
 *   after it (D-099, D-100). That route does not exist for entry 23: the owner
 *   role is UNIQUE — `UserManager::create()` refuses a second owner outright
 *   (`user-manager.php:124`) and `update()` refuses to promote one
 *   (`:222`) — so the only account that can reach this screen is the seeded
 *   owner itself.
 *
 * So this fixture turns the real owner's second factor ON, hands the spec the
 * secret, and turns it OFF again. It is deliberately reversible and idempotent:
 * a run that dies between the two leaves an owner who needs a TOTP code to log
 * in, which would strand every other spec in the tier — so `--off` is safe to
 * run at any time, including when 2FA was never on.
 *
 * The secret is GENERATED per run and written to `tests/E2E/artifacts/`, which
 * is gitignored (`.gitignore:137`). It is never committed and never hardcoded:
 * a literal TOTP secret in the repository would be a credential-shaped string in
 * tracked source, which the confidential-data gate is right to stop (D-097).
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-terminal.php --on       enable, write secret
 *   php tests/E2E/fixtures/reset-terminal.php --off      disable, drop secret
 *   php tests/E2E/fixtures/reset-terminal.php --status   print what exists
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

/** The seeded owner — the only account `terminal.access` is granted to. */
const E2E_TERMINAL_USERNAME = 'owner';

/** Where the spec reads the secret from. Gitignored, per-run, throwaway. */
const E2E_TERMINAL_SECRET_FILE = __DIR__ . '/../artifacts/terminal-totp.secret';

$users     = $app->getUserManager();
$twoFactor = new \Klytos\Core\TwoFactor( $app->getStorage() );

$args   = array_slice( $argv, 1 );
$status = in_array( '--status', $args, true );
$off    = in_array( '--off', $args, true );

$owner = $users->getByUsername( E2E_TERMINAL_USERNAME );

if ( $owner === null ) {
    fwrite( STDERR, "owner account ABSENT — run scripts/dev/seed-playground.php first\n" );
    exit( 1 );
}

$enabled = ! empty( $owner['two_factor']['enabled'] );

if ( $status ) {
    echo 'owner: ' . $owner['id'] . ' (' . $owner['username'] . ' · ' . $owner['role'] . ")\n";
    echo '  two_factor.enabled: ' . ( $enabled ? 'yes' : 'no' ) . "\n";
    echo '  secret file: ' . ( is_file( E2E_TERMINAL_SECRET_FILE ) ? 'present' : 'absent' ) . "\n";
    exit( 0 );
}

if ( $off ) {
    /*
     * Idempotent on purpose. `disableTotp()` is safe on an account that never
     * had it, and the file may already be gone; an `afterAll` that throws
     * because the thing it is cleaning up is already clean would turn a passing
     * run red for no reason.
     */
    $twoFactor->disableTotp( $owner['id'] );

    if ( is_file( E2E_TERMINAL_SECRET_FILE ) ) {
        unlink( E2E_TERMINAL_SECRET_FILE );
    }

    echo "second factor: OFF (owner {$owner['id']})\n";
    exit( 0 );
}

// ─── --on (the default) ─────────────────────────────────────────────────

$secret = $twoFactor->generateTotpSecret();
$twoFactor->enableTotp( $owner['id'], $secret );

$dir = dirname( E2E_TERMINAL_SECRET_FILE );
if ( ! is_dir( $dir ) ) {
    mkdir( $dir, 0o755, true );
}

file_put_contents( E2E_TERMINAL_SECRET_FILE, $secret );

/*
 * Read the account back rather than trusting the write — the same rule the
 * product's own test points follow. A fixture that reports success while the
 * writer silently did nothing is the defect class this tier exists to catch.
 */
$after = $users->getByUsername( E2E_TERMINAL_USERNAME );

if ( empty( $after['two_factor']['enabled'] ) || ( $after['two_factor']['totp_secret'] ?? '' ) !== $secret ) {
    fwrite( STDERR, "enableTotp() reported nothing and stored nothing — refusing to claim it worked\n" );
    exit( 1 );
}

echo "second factor: ON (owner {$owner['id']}, secret written to artifacts/)\n";
