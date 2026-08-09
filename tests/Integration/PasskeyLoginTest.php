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

    /**
     * The signature counter this fixture's authenticator reports at enrolment.
     *
     * Until slice 3 the fixture emitted 1 at enrolment AND 1 at every assertion,
     * i.e. it modelled an authenticator that never increments — which is
     * precisely the condition WebAuthn's counter exists to flag. Nothing noticed,
     * because nothing compared the two. Now that clone detection is in place the
     * fixture has to be faithful to what a real authenticator does, so the two
     * counts are named and distinct. This is a fixture correction, not a
     * loosened assertion: the property `testAPasskeyCompletesASecondFactorLogin`
     * asserts is unchanged, and it is now the POSITIVE CONTROL for the
     * incrementing case (recorded in D-063).
     */
    private const ENROLLED_COUNT = 1;

    /** What the same authenticator reports on the NEXT ceremony. */
    private const NEXT_COUNT = 2;

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
     * @param int    $signCount    Signature counter this ceremony reports.
     */
    private function authData( bool $attested, string $credentialId = '', int $signCount = self::ENROLLED_COUNT ): string
    {
        $flags = $attested ? 0x41 : 0x01;   // UP, plus AT when attesting.

        $data = hash( 'sha256', self::RP_ID, true )
            . chr( $flags )
            . pack( 'N', $signCount );

        if ( ! $attested ) {
            return $data;
        }

        return $data
            . str_repeat( "\x00", 16 )                  // aaguid
            . pack( 'n', strlen( $credentialId ) )
            . $credentialId
            . $this->coseKey();
    }

    /**
     * @param string      $type      Ceremony type.
     * @param string      $challenge Challenge the product minted.
     * @param string|null $origin    Override; defaults to this RP's own origin.
     */
    private function clientData( string $type, string $challenge, ?string $origin = null ): string
    {
        return json_encode( [
            'type'      => $type,
            'challenge' => $challenge,
            'origin'    => $origin ?? 'https://' . self::RP_ID,
        ], JSON_UNESCAPED_SLASHES );
    }

    /**
     * Enrol a passkey through the product's own registration path.
     *
     * @param  string $userId    User to enrol against.
     * @param  int    $signCount Counter the authenticator reports at enrolment.
     *                           Zero models a SYNCED platform passkey (iCloud
     *                           Keychain, Google Password Manager), which reports
     *                           0 permanently and must still be able to log in.
     * @return string The raw credential id.
     */
    private function enrolPasskey( string $userId, int $signCount = self::ENROLLED_COUNT ): string
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
            . self::cborText( 'authData' )
            . self::cborBytes( $this->authData( true, $credentialId, $signCount ) );

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
     * @param  string      $credentialId Raw credential id.
     * @param  string      $challenge    Challenge the product minted.
     * @param  int         $signCount    Counter this ceremony reports. The
     *                                   default EXCEEDS the enrolment count,
     *                                   because that is what a real hardware
     *                                   authenticator does.
     * @param  string|null $origin       Override; defaults to this RP's origin.
     * @return array<string, string> The assertion, as the browser posts it.
     */
    private function signAssertion(
        string $credentialId,
        string $challenge,
        int $signCount = self::NEXT_COUNT,
        ?string $origin = null
    ): array {
        $clientDataRaw = $this->clientData( 'webauthn.get', $challenge, $origin );
        $authData      = $this->authData( false, '', $signCount );

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

        $response = $this->postAssertion(
            $pending,
            $this->signAssertion( $credentialId, $this->authChallenge( $pending ) )
        );

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
            static::resolvedPort(),
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

        $assertion = $this->signAssertion( $credentialId, $this->authChallenge( $pending ) );

        // Flip one byte of the signature: still a well-formed assertion, no longer
        // a valid one.
        $raw                    = base64_decode( strtr( $assertion['signature'], '-_', '+/' ) . '==' );
        $raw[ strlen( $raw ) - 1 ] = chr( ord( $raw[ strlen( $raw ) - 1 ] ) ^ 0xFF );
        $assertion['signature'] = self::b64u( $raw );

        $response = $this->postAssertion( $pending, $assertion );

        self::assertSame( 200, $response['status'], 'A tampered assertion logged the caller in.' );
    }

    // ─── Slice 3 — audit NEW-42 (D-063) ─────────────────────────────────────

    /**
     * THE ONE THAT WOULD HAVE BROKEN REAL USERS. A synced platform passkey
     * reports signCount = 0 forever, and it must still log in.
     *
     * iCloud Keychain, Google Password Manager and every other authenticator
     * that exists in more than one place by design report a permanent zero, so
     * the obvious spelling of clone detection — "the new count must exceed the
     * stored one" — would have refused the second login of most authenticators
     * in use today: a security fix that breaks authentication, which is the
     * trap D-044 recorded.
     *
     * Note on the rule this pins, because the docblock originally got it wrong:
     * Klytos requires BOTH counters to be non-zero, while WebAuthn §7.2 guards
     * on OR. The two differ only for "stored non-zero, presented zero", and the
     * divergence is deliberate and recorded in D-063 implementation note 2.
     * This test is unaffected either way — under both rules a 0/0 authenticator
     * skips the comparison entirely, which is exactly the property here.
     *
     * This test is written first among the slice's tests for that reason, and it
     * fails against the naive rule while the clone test below still passes.
     */
    public function testASyncedPasskeyReportingZeroForeverStillCompletesLogin(): void
    {
        $owner = $this->users->getByUsername( 'owner' );
        self::assertNotNull( $owner );

        $credentialId = $this->enrolPasskey( $owner['id'], 0 );
        $pending      = $this->pendingTwoFactorSessionFor( 'owner' );

        $response = $this->postAssertion(
            $pending,
            $this->signAssertion( $credentialId, $this->authChallenge( $pending ), 0 )
        );

        self::assertSame(
            302,
            $response['status'],
            'A synced passkey (signCount 0 at enrolment AND at login) was refused. Clone detection '
            . 'must not fire when either counter is zero. Body: ' . substr( $response['body'], 0, 400 )
        );

        // Not "it did not say no" — it is actually logged in (D-061's rule).
        $granted = [];
        preg_match( '/^Set-Cookie:\s*klytos_session=([^;\s]+)/mi', $response['headers'], $granted );
        self::assertNotEmpty( $granted[1] ?? '', 'The success handed back no session cookie.' );

        self::assertSame(
            200,
            $this->request( '/installer/admin/', null, $granted[1] )['status'],
            'The 302 was not a login: the session it returned does not reach the dashboard.'
        );
    }

    /**
     * A counter that goes BACKWARDS, with both counters non-zero, is refused —
     * the condition the sign counter exists to reveal.
     */
    public function testACounterRegressionIsRefusedWhenBothCountersAreNonZero(): void
    {
        $owner = $this->users->getByUsername( 'owner' );
        self::assertNotNull( $owner );

        $credentialId = $this->enrolPasskey( $owner['id'], 5 );
        $pending      = $this->pendingTwoFactorSessionFor( 'owner' );

        // 4 < 5: a second authenticator answering for one credential.
        $response = $this->postAssertion(
            $pending,
            $this->signAssertion( $credentialId, $this->authChallenge( $pending ), 4 )
        );

        self::assertSame(
            200,
            $response['status'],
            'A cloned authenticator (stored counter 5, presented 4) completed the login.'
        );

        self::assertSame(
            302,
            $this->request( '/installer/admin/', null, $pending )['status'],
            'The refused assertion left the browser logged in anyway.'
        );
    }

    /**
     * A REPEATED counter is refused too — equal is not "greater than".
     *
     * Separated from the regression above because they are different real-world
     * events: a replayed ceremony rather than a second device, and an
     * implementation that used `<` instead of `<=` would pass that test and fail
     * this one.
     */
    public function testARepeatedCounterIsRefusedToo(): void
    {
        $owner = $this->users->getByUsername( 'owner' );
        self::assertNotNull( $owner );

        $credentialId = $this->enrolPasskey( $owner['id'], 5 );
        $pending      = $this->pendingTwoFactorSessionFor( 'owner' );

        $response = $this->postAssertion(
            $pending,
            $this->signAssertion( $credentialId, $this->authChallenge( $pending ), 5 )
        );

        self::assertSame( 200, $response['status'], 'A repeated counter (5 then 5) completed the login.' );
    }

    /**
     * Clone detection fires its action, carrying BOTH counters.
     *
     * Driven in-process because the HTTP tests above run the product in another
     * process, where a listener registered here could never be reached — and
     * "an action exists" is not the same as "an action fires", which is L-019's
     * whole subject. Nothing in core subscribes to this hook; a deployment that
     * wants to be told registers a listener, and this is the proof that one
     * would actually hear about it.
     */
    public function testCloneDetectionFiresItsActionWithBothCounters(): void
    {
        $owner = $this->users->getByUsername( 'owner' );
        self::assertNotNull( $owner );

        $credentialId = $this->enrolPasskey( $owner['id'], 9 );
        $twoFactor    = $this->app->getTwoFactor();

        $heard = [];
        $this->addTemporaryAction(
            'user.passkey_clone_detected',
            function ( string $userId, string $credential, int $stored, int $presented ) use ( &$heard ): void {
                $heard = [
                    'user'      => $userId,
                    'stored'    => $stored,
                    'presented' => $presented,
                ];
            }
        );

        $challenge = $twoFactor->createPasskeyAuthChallenge( $owner['id'], self::RP_ID )['challenge'];

        self::assertFalse(
            $twoFactor->verifyPasskeyAssertion(
                $owner['id'],
                $this->signAssertion( $credentialId, $challenge, 3 ),
                self::RP_ID
            ),
            'A counter regression was accepted.'
        );

        self::assertSame(
            [ 'user' => $owner['id'], 'stored' => 9, 'presented' => 3 ],
            $heard,
            'user.passkey_clone_detected did not fire with the two counters that caused the refusal.'
        );
    }

    /**
     * An assertion produced for a DIFFERENT origin is refused, exactly as the
     * enrolment that created the credential would have been.
     *
     * The asymmetry this closes favoured the path that runs once per enrolment
     * over the path that runs at every login (audit NEW-42 item 2).
     */
    public function testAnAssertionFromAnotherOriginIsRefused(): void
    {
        $owner = $this->users->getByUsername( 'owner' );
        self::assertNotNull( $owner );

        $credentialId = $this->enrolPasskey( $owner['id'] );
        $pending      = $this->pendingTwoFactorSessionFor( 'owner' );

        $response = $this->postAssertion(
            $pending,
            $this->signAssertion(
                $credentialId,
                $this->authChallenge( $pending ),
                self::NEXT_COUNT,
                'https://attacker.example'
            )
        );

        self::assertSame(
            200,
            $response['status'],
            'An assertion carrying origin https://attacker.example completed the login.'
        );

        self::assertSame(
            302,
            $this->request( '/installer/admin/', null, $pending )['status'],
            'The refused cross-origin assertion left the browser logged in anyway.'
        );
    }

    /**
     * The SAME rule accepts registration's own localhost allowance, so this
     * slice cannot have tightened the assertion path past its sibling.
     *
     * One direction is half a test (L-010): without this, an origin check that
     * refused everything would look identical to a correct one, and the local
     * development login it would break is not exercised anywhere else.
     */
    public function testTheLocalhostDevelopmentAllowanceSurvivesOnBothPaths(): void
    {
        $method = new \ReflectionMethod( \Klytos\Core\TwoFactor::class, 'originIsAcceptable' );
        $method->setAccessible( true );

        foreach ( [ 'https://localhost', 'https://localhost:8443', 'https://' . self::RP_ID ] as $origin ) {
            self::assertTrue(
                $method->invoke( null, $origin, self::RP_ID ),
                $origin . ' was refused, which would break local development logins.'
            );
        }

        foreach ( [ 'http://' . self::RP_ID, 'https://localhost.evil.example', 'https://evil.example', '' ] as $origin ) {
            self::assertFalse(
                $method->invoke( null, $origin, self::RP_ID ),
                var_export( $origin, true ) . ' was accepted as an origin.'
            );
        }

        // THE WEAKNESS, ASSERTED ON PURPOSE — audit NEW-53, raised by this
        // slice's security-auditor pass.
        //
        // The development allowance is a PREFIX match, not a format check, so
        // strings that are not origins at all satisfy it: a userinfo component
        // (`localhost:8443` read as user:password, host `evil.example`) and a
        // path both slip through. A browser never serialises an origin that
        // way — origin is scheme://host[:port] and nothing else — and a
        // non-browser caller must still produce a valid ES256 signature over
        // clientDataJSON, so this is not a login bypass. It is a control that
        // is looser than it reads.
        //
        // It is PRE-EXISTING: the rule was carried over byte for byte from the
        // registration path, which has always had it, and the equivalence was
        // proven rather than assumed. Tightening it changes registration's
        // behaviour and belongs to its own decision, so the boundary is pinned
        // here instead — the D-044 precedent, where a test asserts style-src's
        // 'unsafe-inline' on purpose so that removing it shows up as a
        // deliberate change rather than as a mystery failure.
        foreach ( [ 'https://localhost:8443@evil.example', 'https://localhost:8443/path', 'https://localhost:notaport' ] as $origin ) {
            self::assertTrue(
                $method->invoke( null, $origin, self::RP_ID ),
                var_export( $origin, true ) . ' is now REFUSED. If that was deliberate, NEW-53 has '
                . 'been closed and this assertion should be inverted with its decision recorded.'
            );
        }
    }

    /**
     * A 32-byte authenticatorData fails closed AND emits no PHP warning.
     *
     * It already failed closed before this slice — but by way of
     * `ord($authData[32])` reading past the end of the string, which raises
     * "Uninitialized string offset 32". The user-visible defect is therefore the
     * warning, not the refusal, and the warning is what this asserts: phpunit.xml
     * has carried failOnWarning="true" since D-054, so a PHP warning raised here
     * fails this test rather than scrolling past.
     *
     * That is also why it runs IN-PROCESS. Over HTTP the warning would be raised
     * in the server's process and land in a log nobody reads, which is exactly
     * the shape of an assertion that cannot fail.
     *
     * 32 bytes is not an arbitrary length: authenticatorData begins with
     * sha256(rpId) and the rpId is public, so this input is trivially
     * precomputable by anyone.
     */
    public function testATruncatedAuthenticatorDataIsRefusedWithNoPhpWarning(): void
    {
        $owner = $this->users->getByUsername( 'owner' );
        self::assertNotNull( $owner );

        $credentialId = $this->enrolPasskey( $owner['id'] );
        $twoFactor    = $this->app->getTwoFactor();

        $challenge     = $twoFactor->createPasskeyAuthChallenge( $owner['id'], self::RP_ID )['challenge'];
        $clientDataRaw = $this->clientData( 'webauthn.get', $challenge );

        // Exactly the rpIdHash and nothing else: 32 bytes, so the flags byte at
        // offset 32 does not exist. The origin and the challenge are VALID, so
        // this test isolates the length guard instead of being refused earlier
        // for an unrelated reason.
        $truncated = hash( 'sha256', self::RP_ID, true );
        self::assertSame( 32, strlen( $truncated ) );

        self::assertFalse(
            $twoFactor->verifyPasskeyAssertion( $owner['id'], [
                'credentialId'      => self::b64u( $credentialId ),
                'clientDataJSON'    => self::b64u( $clientDataRaw ),
                'authenticatorData' => self::b64u( $truncated ),
                'signature'         => self::b64u( 'not-checked-this-far' ),
            ], self::RP_ID ),
            'A 32-byte authenticatorData was accepted.'
        );
    }

    /**
     * During an INCOMPLETE setup the endpoint answers its JSON contract instead
     * of a 302 into the setup wizard (audit NEW-42 item 4).
     *
     * The two skip-lists in admin/bootstrap.php did not move together: D-058
     * keyed the pre-auth list on klytos_admin_gate_key() and left this one
     * matching a basename, which cannot express an `api/` path at all. So on a
     * fresh install the passkey ceremony's fetch() received a redirect where it
     * expects a JSON body.
     *
     * This is the one property in the slice that no existing test could reach,
     * because it needs `setup_completed => false` — so the config is written,
     * the request is made, and the ORIGINAL is written back in a finally. The
     * comparison D-039's guard makes is on decrypted content, so a net-zero
     * rewrite passes it; anything else would fail this test in teardown rather
     * than leak into the next one.
     */
    public function testTheWebauthnEndpointAnswersJsonDuringAnIncompleteSetup(): void
    {
        $configDir = KLYTOS_INSTALLER_PATH . '/config';
        $original  = $this->storage->readFrom( $configDir, 'config.json.enc' );

        self::assertNotSame(
            false,
            $original['setup_completed'] ?? null,
            'This seed already has setup_completed = false, so the test would prove nothing.'
        );

        $incomplete                    = $original;
        $incomplete['setup_completed'] = false;
        $this->storage->writeTo( $configDir, 'config.json.enc', $incomplete );

        try {
            $pending  = $this->pendingTwoFactorSessionFor( 'owner' );
            $response = $this->postJson( self::ENDPOINT, [ 'action' => 'auth_challenge' ], 'owner', $pending );

            self::assertSame(
                200,
                $response['status'],
                'The endpoint answered ' . $response['status'] . ' (Location: ' . $response['location']
                . ') during an incomplete setup, where its callers parse JSON.'
            );
            self::assertArrayHasKey(
                'challenge',
                json_decode( $response['body'], true ) ?? [],
                'The body was not the challenge the endpoint contracts to return.'
            );
        } finally {
            $this->storage->writeTo( $configDir, 'config.json.enc', $original );
        }
    }

    // ─── Shared drivers ─────────────────────────────────────────────────────

    /**
     * Ask the product for an authentication challenge, through its own endpoint.
     *
     * @param  string $pendingSession A 2FA-pending session id.
     * @return string The challenge the product minted and stored.
     */
    private function authChallenge( string $pendingSession ): string
    {
        $response = $this->postJson( self::ENDPOINT, [ 'action' => 'auth_challenge' ], 'owner', $pendingSession );

        self::assertSame(
            200,
            $response['status'],
            'The challenge endpoint refused, so nothing below measures the assertion path: '
            . substr( $response['body'], 0, 200 )
        );

        $challenge = json_decode( $response['body'], true )['challenge'] ?? null;
        self::assertIsString( $challenge, 'No challenge to sign.' );

        return $challenge;
    }

    /**
     * Post an assertion to the real login form, the way the shipped page does.
     *
     * @param  string               $pendingSession A 2FA-pending session id.
     * @param  array<string,string> $assertion      A signed assertion.
     * @return array{status:int, body:string, headers:string, location:string, content_type:string}
     */
    private function postAssertion( string $pendingSession, array $assertion ): array
    {
        return $this->post( self::LOGIN, [
            '2fa_method' => 'passkey',
            '2fa_code'   => json_encode( $assertion ),
            'csrf'       => self::CSRF_TOKEN,
        ], null, [ 'Cookie: klytos_session=' . $pendingSession ] );
    }
}
