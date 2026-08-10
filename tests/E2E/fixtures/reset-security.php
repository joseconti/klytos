<?php

/**
 * Klytos CMS — put the playground back into a known state for manifest entry 6.
 *
 * Entry 6 edits the ACTING USER's own second factors, so the reset is per user
 * and not per record: `disableAll()` takes the seeded owner back to the state a
 * fresh install has (no methods, no recovery codes, no passkeys), which is also
 * one of the screen's real states rather than merely "unset".
 *
 * Everything that CAN go through the product's own managers does — `enableTotp()`,
 * `enableMagicLink()`, `generateRecoveryCodes()`, `disableAll()` — so a manager
 * that has stopped working shows up here instead of being papered over.
 *
 * **One deliberate exception, and it is the whole reason this file needs a
 * comment.** A passkey cannot be created by any manager call: it is the output
 * of a WebAuthn ceremony, and `completePasskeyRegistration()` takes a real
 * attestation from a real authenticator. Seeding one therefore goes through the
 * STORAGE API — never a file write — with the record shaped exactly as that
 * method shapes it. What this buys is the collection's non-empty state and the
 * removal path, which are the parts the screen owns; what it does NOT prove is
 * the enrolment ceremony, and the spec says so where it matters rather than
 * implying the fixture covers it.
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-security.php
 *   php tests/E2E/fixtures/reset-security.php --totp --email --passkey
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

$app = \Klytos\Core\App::getInstance();
$app->boot();

/** The seeded account every entry-6 test signs in as. */
const E2E_SECURITY_USER = 'owner';

/** The passkey the collection draws when one is asked for. */
const E2E_PASSKEY_ID    = 'e2e-credential-id';
const E2E_PASSKEY_LABEL = 'E2E Security Key';

$flags = array_slice( $argv, 1 );
$want  = static fn( string $flag ): bool => in_array( '--' . $flag, $flags, true );

$userManager = $app->getUserManager();
$twoFactor   = $app->getTwoFactor();
$storage     = $app->getStorage();

$user = $userManager->getByUsername( E2E_SECURITY_USER );
if ( ! $user ) {
    fwrite( STDERR, "Seeded user '" . E2E_SECURITY_USER . "' not found — reseed the playground with --reset.\n" );
    exit( 1 );
}
$userId = (string) $user['id'];

// Always start from the fresh-install state, whatever is asked for after it.
$twoFactor->disableAll( $userId );

if ( $want( 'totp' ) ) {
    $twoFactor->enableTotp( $userId, $twoFactor->generateTotpSecret() );
}

if ( $want( 'email' ) ) {
    $twoFactor->enableMagicLink( $userId );
}

if ( $want( 'codes' ) ) {
    $twoFactor->generateRecoveryCodes( $userId );
}

if ( $want( 'passkey' ) ) {
    // See the header: no manager can produce this without an authenticator.
    $record                    = $storage->read( 'users', $userId );
    $tf                        = $record['two_factor'];
    $tf['passkeys'][]          = [
        'credential_id' => E2E_PASSKEY_ID,
        'public_key'    => 'e2e-public-key',
        'aaguid'        => str_repeat( '0', 32 ),
        'label'         => E2E_PASSKEY_LABEL,
        'sign_count'    => 0,
        'created_at'    => \Klytos\Core\Helpers::now(),
        'last_used'     => null,
    ];
    $tf['methods'][]           = 'passkey';
    $tf['enabled']             = true;
    $record['two_factor']      = $tf;
    $storage->write( 'users', $userId, $record );
}

$config = $twoFactor->getUserConfig( $userId );

echo json_encode( [
    'user'            => E2E_SECURITY_USER,
    'enabled'         => $config['enabled'],
    'methods'         => $config['methods'],
    'passkeys'        => $config['passkey_count'],
    'recovery_codes'  => $config['recovery_codes_left'],
] );
echo "\n";
