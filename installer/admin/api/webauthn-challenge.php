<?php

/**
 * Klytos Admin API — WebAuthn Challenge Endpoint
 * Handles passkey registration and authentication challenges.
 *
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json');

$auth = $app->getAuth();

// Must be authenticated or have a pending 2FA challenge.
if (!$auth->isAuthenticated() && !$auth->is2faPending()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// CSRF validation, from EITHER channel — and the body one is not optional.
//
// This endpoint is called with `Content-Type: application/json`, and PHP does not
// populate $_POST for a JSON body. Helpers::verifyCsrf() reads $_POST, the
// X-CSRF-Token header and $_GET — so with the token carried inside the JSON body,
// as login.php and security.php have always sent it, it saw NOTHING and answered
// 403. Passkey registration and passkey login were both unreachable for a real
// browser for as long as this file has existed, on top of the two defects NEW-09
// already named.
//
// The tell was here all along: $csrf was read into a variable and never used. It
// is used now. The two callers also send the header, so either channel works and
// neither a future JS change nor a future helper change can silently break this
// again — which is the whole reason both are accepted rather than one.
$csrf = $input['csrf'] ?? '';
if (!klytos_verify_csrf() && !$auth->validateCsrf(is_string($csrf) ? $csrf : '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$action    = $input['action'] ?? '';
$twoFactor = $app->getTwoFactor();

// ─── The registration actions require a FULLY authenticated caller ──────────
//
// This is the restriction that makes the pre-auth exemption in
// admin/bootstrap.php's $preAuthScripts safe, and it is deliberately written
// BEFORE that exemption exists (D-036, and the order is the whole point).
//
// is2faPending() becomes true as soon as a caller supplies a correct PASSWORD
// (auth.php, the 2FA branch of login()). completePasskeyRegistration() appends a
// credential, adds 'passkey' to the enabled methods and sets enabled = true
// WITHOUT checking that the caller ever passed an existing second factor. Gate
// those two actions on the same weak condition as auth_challenge — as this file
// used to — and a stolen password alone lets an attacker enrol their own
// authenticator and hold the account permanently: 2FA defeated by the endpoint
// that exists to provide it. Slice 4 of Sprint 1 implemented that exemption,
// found this, and reverted it the same day.
//
// auth_challenge stays reachable while 2FA is pending because that is the entire
// legitimate flow: a user who has passed the password stage asking for the
// challenge their authenticator must sign. It reads that user's own credential
// ids and stores a challenge scoped to their id; it grants nothing.
if (
    ( $action === 'register_challenge' || $action === 'register_complete' )
    && ! $auth->isAuthenticated()
) {
    http_response_code( 403 );
    echo json_encode( [ 'error' => 'Passkey registration requires a fully authenticated session.' ] );
    exit;
}

// Resolve user ID.
$userId = null;
if ($auth->isAuthenticated()) {
    $username = $auth->getUsername();
    $users = $app->getStorage()->list('users');
    foreach ($users as $u) {
        if (($u['username'] ?? '') === $username) {
            $userId = $u['id'];
            break;
        }
    }
} elseif ($auth->is2faPending()) {
    $userId = $auth->get2faPendingUserId();
}

if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'User not found']);
    exit;
}

// One definition, shared with admin/login.php's assertion verification: a passkey
// is bound to the rpId it was registered under, so the two must derive it
// identically or every stored credential silently stops working.
$rpId = \Klytos\Core\Helpers::webauthnRpId();

$siteConfig = $app->getSiteConfig()->get();

if ($action === 'register_challenge') {
    $user = $app->getStorage()->read('users', $userId);
    $options = $twoFactor->createPasskeyRegistrationChallenge(
        $userId,
        $user['username'] ?? '',
        $user['display_name'] ?? $user['username'] ?? '',
        $rpId,
        $siteConfig['site_name'] ?? 'Klytos'
    );
    echo json_encode($options);

} elseif ($action === 'register_complete') {
    $attestation = $input['attestation'] ?? [];
    $label       = trim($input['label'] ?? '');

    try {
        $result = $twoFactor->completePasskeyRegistration($userId, $attestation, $rpId, $label);
    } catch (\RuntimeException $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }

    // The credential is now STORED. Everything below is notification, and the
    // answer is sent first precisely so that nothing below can change it.
    //
    // The first version of this ran the mail and the action INSIDE the try above,
    // which the slice's own code-reviewer caught: Hooks::doAction() has no
    // per-listener guard, so a plugin throwing RuntimeException would have been
    // caught by the registration catch and reported an enrolment that actually
    // succeeded as a 400 failure — and anything else it threw would have escaped
    // uncaught. The comment beside it claimed "neither can fail the enrolment",
    // which was true of the mail (it catches internally) and false of the action.
    echo json_encode(['success' => true, 'passkey' => $result]);

    try {
        $user  = $app->getStorage()->read( 'users', $userId );
        $email = trim( $user['email'] ?? '' );

        // The notification is the compensating control for enrolment needing no
        // existing second factor (NEW-13 is out of scope, D-057), so a silent
        // failure to send is worth a log line rather than nothing at all.
        if ( $email !== '' && klytos_is_email( $email ) ) {
            $sent = $twoFactor->sendPasskeyEnrolledEmail( $email, $result['label'] ?? '', $app->getMailer() );

            if ( ! $sent ) {
                error_log( 'Klytos: passkey enrolled for user ' . $userId . ' but the notification email was not sent.' );
            }
        }

        klytos_do_action( 'user.passkey_enrolled', $userId, $result['credential_id'] ?? '', $result['label'] ?? '' );
    } catch ( \Throwable $e ) {
        error_log( 'Klytos: passkey enrolment notification failed: ' . $e->getMessage() );
    }

} elseif ($action === 'auth_challenge') {
    $options = $twoFactor->createPasskeyAuthChallenge($userId, $rpId);
    echo json_encode($options);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
}
