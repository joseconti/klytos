<?php

/**
 * Klytos CMS — passkey second-factor login, end to end (Sprint 5, slice 2 /
 * audit NEW-09, D-036).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

/**
 * A REAL passkey login, with no browser and no mocking of the product.
 *
 * The two halves of this feature both existed and were never connected: the login
 * page's own front end has posted `2fa_method=passkey` since before there was a
 * branch to receive it, and `TwoFactor::verifyPasskeyAssertion()` was complete
 * with ZERO call sites (audit NEW-09). So the fix is small and the RISK is not —
 * D-036 records that the obvious one-line version (exempting
 * `api/webauthn-challenge.php` from the auth guard) opens a full account
 * takeover, because `is2faPending()` is true after a correct password alone and
 * that endpoint's registration actions would then be reachable.
 *
 * THE ORDER IS THE POINT, and these tests are written to hold it:
 * `testRegistrationIsRefusedWhileTwoFactorIsMerelyPending` is the takeover proof
 * and it must pass for the exemption to be safe at all.
 *
 * Nothing here is a stub. A P-256 key is generated with `openssl_pkey_new`, the
 * COSE public key and the CBOR attestation object are hand-encoded, and every
 * signature is a real ES256 signature over `authData || SHA-256(clientDataJSON)`
 * — which is what makes this a test of the product's verification rather than of
 * a fixture that agrees with itself. The credential is enrolled through the
 * product's OWN `completePasskeyRegistration()` rather than written into storage,
 * so registration and login are proven to compose (L-005).
 */
final class PasskeyLoginTest extends AdminHttpTestCase
{
    /** Ports 8099-8106 are taken by the other HTTP test classes. */
    protected static function serverPort(): int
    {
        return 8107;
    }

    private const ENDPOINT = '/installer/admin/api/webauthn-challenge.php';
    private const LOGIN    = '/installer/admin/login.php';

    /** The rpId the server derives from this harness's Host header. */
    private const RP_ID = '127.0.0.1';

    /** @var \OpenSSLAsymmetricKey|null The authenticator's private key. */
    private $privateKey = null;

    // ─── WebAuthn fixture ───────────────────────────────────────────────────

    private static function b64u( string $raw ): string
    {
        return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
    }

    /**
     * CBOR: a definite-length byte string.
     */
    private static function cborBytes( string $raw ): string
    {
        return self::cborHead( 2, strlen( $raw ) ) . $raw;
    }

    /**
     * CBOR: a definite-length text string.
     */
    private static function cborText( string $text ): string
    {
        return self::cborHead( 3, strlen( $text ) ) . $text;
    }

    /**
     * CBOR: a major-type head with its argument, for the sizes this fixture uses.
     */
    private static function cborHead( int $major, int $value ): string
    {
        if ( $value < 24 ) {
            return chr( ( $major << 5 ) | $value );
        }
        if ( $value < 256 ) {
            return chr( ( $major << 5 ) | 24 ) . chr( $value );
        }

        return chr( ( $major << 5 ) | 25 ) . pack( 'n', $value );
    }

    /**
     * CBOR: a negative integer (major type 1, encoded as -1 - n).
     */
    private static function cborNegative( int $n ): string
    {
        return self::cborHead( 1, -1 - $n );
    }

    /**
     * The COSE_Key for an ES256 public key, as an authenticator would emit it.
     *
     * Map: {1: 2 (EC2), 3: -7 (ES256), -1: 1 (P-256), -2: x, -3: y} — the exact
     * shape `TwoFactor::coseKeyToPem()` reads.
     */
    private function coseKey(): string
    {
        $details = openssl_pkey_get_details( $this->privateKey );

        $x = str_pad( $details['ec']['x'], 32, "\x00", STR_PAD_LEFT );
        $y = str_pad( $details['ec']['y'], 32, "\x00", STR_PAD_LEFT );

        return chr( ( 5 << 5 ) | 5 )                       // map of 5
            . self::cborHead( 0, 1 ) . self::cborHead( 0, 2 )     //  1 : 2   kty EC2
            . self::cborHead( 0, 3 ) . self::cborNegative( -7 )   //  3 : -7  alg ES256
            . self::cborNegative( -1 ) . self::cborHead( 0, 1 )   // -1 : 1   crv P-256
            . self::cborNegative( -2 ) . self::cborBytes( $x )    // -2 : x
            . self::cborNegative( -3 ) . self::cborBytes( $y );   // -3 : y
    }

    /**
     * Authenticator data: rpIdHash || flags || signCount [|| attested credential].
     *
     * @param bool   $attested     Include attested credential data (registration).
     * @param string $credentialId Raw credential id.
     */
    private function authData( bool $attested, string $credentialId = '' ): string
    {
        $flags = $attested ? 0x41 : 0x01;   // UP, plus AT when attesting.

        $data = hash( 'sha256', self::RP_ID, true )
            . chr( $flags )
            . pack( 'N', 1 );

        if ( ! $attested ) {
            return $data;
        }

        return $data
            . str_repeat( "\x00", 16 )                  // aaguid
            . pack( 'n', strlen( $credentialId ) )
            . $credentialId
            . $this->coseKey();
    }

    private function clientData( string $type, string $challenge ): string
    {
        return json_encode( [
            'type'      => $type,
            'challenge' => $challenge,
            'origin'    => 'https://' . self::RP_ID,
        ], JSON_UNESCAPED_SLASHES );
    }

    /**
     * Enrol a passkey through the product's own registration path.
     *
     * @return string The raw credential id.
     */
    private function enrolPasskey( string $userId ): string
    {
        $this->privateKey = openssl_pkey_new( [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ] );
        self::assertNotFalse( $this->privateKey, 'Could not generate a P-256 key.' );

        $twoFactor = $this->app->getTwoFactor();

        // The product mints and stores the challenge; the fixture never invents one.
        $options   = $twoFactor->createPasskeyRegistrationChallenge(
            $userId,
            'passkey-user',
            'Passkey user',
            self::RP_ID,
            'Klytos test'
        );

        $credentialId  = random_bytes( 32 );
        $clientDataRaw = $this->clientData( 'webauthn.create', $options['challenge'] );

        $attestationObject = chr( ( 5 << 5 ) | 3 )        // map of 3
            . self::cborText( 'fmt' ) . self::cborText( 'none' )
            . self::cborText( 'attStmt' ) . chr( ( 5 << 5 ) | 0 )
            . self::cborText( 'authData' ) . self::cborBytes( $this->authData( true, $credentialId ) );

        $stored = $twoFactor->completePasskeyRegistration( $userId, [
            'clientDataJSON'    => self::b64u( $clientDataRaw ),
            'attestationObject' => self::b64u( $attestationObject ),
        ], self::RP_ID, 'Test authenticator' );

        self::assertSame(
            self::b64u( $credentialId ),
            $stored['credential_id'],
            'The product stored a different credential id than the one attested.'
        );

        return $credentialId;
    }

    /**
     * Sign an authentication challenge exactly as an authenticator would.
     *
     * @return array<string, string> The assertion, as the browser posts it.
     */
    private function signAssertion( string $credentialId, string $challenge ): array
    {
        $clientDataRaw = $this->clientData( 'webauthn.get', $challenge );
        $authData      = $this->authData( false );

        $signature = '';
        openssl_sign(
            $authData . hash( 'sha256', $clientDataRaw, true ),
            $signature,
            $this->privateKey,
            OPENSSL_ALGO_SHA256
        );

        return [
            'credentialId'      => self::b64u( $credentialId ),
            'clientDataJSON'    => self::b64u( $clientDataRaw ),
            'authenticatorData' => self::b64u( $authData ),
            'signature'         => self::b64u( $signature ),
        ];
    }

    // ─── The tests ──────────────────────────────────────────────────────────

    /**
     * THE TAKEOVER PROOF. Registration is refused when 2FA is merely pending.
     *
     * `is2faPending()` is true after a correct PASSWORD alone, and
     * `completePasskeyRegistration()` enrols a credential and sets
     * `enabled = true` without checking any existing factor. If these two actions
     * were reachable in that state, a stolen password would let an attacker enrol
     * their own authenticator and hold the account permanently — 2FA defeated by
     * the endpoint that provides it. This is what D-036 refused to ship, and the
     * exemption below it is safe only while this test passes.
     */
    public function testRegistrationIsRefusedWhileTwoFactorIsMerelyPending(): void
    {
        $pending = $this->pendingTwoFactorSessionFor( 'owner' );

        foreach ( [ 'register_challenge', 'register_complete' ] as $action ) {
            $response = $this->postJson( self::ENDPOINT, [ 'action' => $action ], 'owner', $pending );

            self::assertSame(
                403,
                $response['status'],
                "'{$action}' was reachable with only a password — this is the "
                . 'account-takeover path D-036 records.'
            );
        }
    }

    /**
     * The authentication challenge IS reachable while 2FA is pending — that is
     * the whole legitimate flow, and refusing it would break passkey login again.
     *
     * One direction is half a test (L-010): without this, an endpoint that
     * refused everything would look identical to a correctly restricted one.
     */
    public function testTheAuthenticationChallengeIsReachableWhileTwoFactorIsPending(): void
    {
        $owner = $this->users->getByUsername( 'owner' );
        self::assertNotNull( $owner );
        $this->enrolPasskey( $owner['id'] );

        $pending  = $this->pendingTwoFactorSessionFor( 'owner' );
        $response = $this->postJson( self::ENDPOINT, [ 'action' => 'auth_challenge' ], 'owner', $pending );

        self::assertSame( 200, $response['status'], 'The passkey challenge is unreachable at login.' );

        $options = json_decode( $response['body'], true );
        self::assertIsArray( $options );
        self::assertArrayHasKey( 'challenge', $options, 'No challenge was issued.' );
        self::assertSame( self::RP_ID, $options['rpId'] ?? null );
    }

    /**
     * A REAL passkey completes a second-factor login, end to end, over HTTP.
     *
     * Everything the product does is the product's: it mints the challenge, it
     * stores it, its dispatcher receives `2fa_method=passkey`, and
     * `verifyPasskeyAssertion()` verifies a genuine ES256 signature against the
     * COSE key it stored at registration. The only thing the test supplies is the
     * authenticator.
     */
    public function testAPasskeyCompletesASecondFactorLogin(): void
    {
        $owner = $this->users->getByUsername( 'owner' );
        self::assertNotNull( $owner );

        $credentialId = $this->enrolPasskey( $owner['id'] );
        $pending      = $this->pendingTwoFactorSessionFor( 'owner' );

        $challenge = json_decode(
            $this->postJson( self::ENDPOINT, [ 'action' => 'auth_challenge' ], 'owner', $pending )['body'],
            true
        )['challenge'] ?? null;
        self::assertIsString( $challenge, 'No challenge to sign.' );

        $response = $this->post( self::LOGIN, [
            '2fa_method' => 'passkey',
            '2fa_code'   => json_encode( $this->signAssertion( $credentialId, $challenge ) ),
            'csrf'       => self::CSRF_TOKEN,
        ], null, [ 'Cookie: klytos_session=' . $pending ] );

        self::assertSame(
            302,
            $response['status'],
            'A valid passkey assertion did not complete the login. Body: '
            . substr( $response['body'], 0, 400 )
        );
    }

    /**
     * The endpoint accepts EXACTLY what the shipped page sends — CSRF in the JSON
     * body, with no `X-CSRF-Token` header.
     *
     * This test exists because the slice's own `security-auditor` pass found that
     * every other test here was passing for a reason the product does not have.
     * `AdminHttpTestCase::postJson()` adds an `X-CSRF-Token` header; `login.php`'s
     * and `security.php`'s fetch() calls set only `Content-Type: application/json`
     * and put the token INSIDE the body. `Helpers::verifyCsrf()` reads `$_POST`,
     * the header and `$_GET` — and PHP does not populate `$_POST` for a JSON body,
     * so the real browser call was answered **403** while the whole suite was
     * green. Passkey login could not complete for a single real user.
     *
     * The tell was in the endpoint the whole time: it read `$input['csrf']` into a
     * variable and never used it.
     *
     * So this request is deliberately built without the header. It is the only
     * test here that reproduces the shipped page byte for byte, and it is the one
     * that would catch this class of defect again — L-016's rule applied to a
     * harness that was quietly fixing the product it was measuring.
     */
    public function testTheEndpointAcceptsTheTokenTheShippedPageActuallySends(): void
    {
        $pending = $this->pendingTwoFactorSessionFor( 'owner' );

        $handle = curl_init( sprintf(
            'http://%s:%d%s',
            self::HOST,
            static::serverPort(),
            self::ENDPOINT
        ) );

        curl_setopt_array( $handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode( [
                'action' => 'auth_challenge',
                'csrf'   => self::CSRF_TOKEN,
            ] ),
            CURLOPT_COOKIE         => 'klytos_session=' . $pending,
            // EXACTLY the shipped page's headers. Adding X-CSRF-Token here would
            // reintroduce the defect this test exists to prevent.
            CURLOPT_HTTPHEADER     => [ 'Content-Type: application/json' ],
        ] );

        $raw = curl_exec( $handle );
        self::assertNotFalse( $raw, 'Request failed: ' . curl_error( $handle ) );

        $status = curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
        $body   = substr( $raw, curl_getinfo( $handle, CURLINFO_HEADER_SIZE ) );
        curl_close( $handle );

        self::assertSame(
            200,
            $status,
            'The endpoint refused the CSRF token the shipped page sends (body: '
            . substr( $body, 0, 200 ) . ')'
        );
        self::assertArrayHasKey( 'challenge', json_decode( $body, true ) ?? [] );
    }

    /**
     * A tampered signature is refused — so the test above cannot be passing
     * because the dispatcher accepts anything shaped like an assertion.
     */
    public function testATamperedAssertionIsRefused(): void
    {
        $owner = $this->users->getByUsername( 'owner' );
        self::assertNotNull( $owner );

        $credentialId = $this->enrolPasskey( $owner['id'] );
        $pending      = $this->pendingTwoFactorSessionFor( 'owner' );

        $challenge = json_decode(
            $this->postJson( self::ENDPOINT, [ 'action' => 'auth_challenge' ], 'owner', $pending )['body'],
            true
        )['challenge'] ?? null;
        self::assertIsString( $challenge );

        $assertion = $this->signAssertion( $credentialId, $challenge );

        // Flip one byte of the signature: still a well-formed assertion, no longer
        // a valid one.
        $raw                    = base64_decode( strtr( $assertion['signature'], '-_', '+/' ) . '==' );
        $raw[ strlen( $raw ) - 1 ] = chr( ord( $raw[ strlen( $raw ) - 1 ] ) ^ 0xFF );
        $assertion['signature'] = self::b64u( $raw );

        $response = $this->post( self::LOGIN, [
            '2fa_method' => 'passkey',
            '2fa_code'   => json_encode( $assertion ),
            'csrf'       => self::CSRF_TOKEN,
        ], null, [ 'Cookie: klytos_session=' . $pending ] );

        self::assertSame( 200, $response['status'], 'A tampered assertion logged the caller in.' );
    }
}
