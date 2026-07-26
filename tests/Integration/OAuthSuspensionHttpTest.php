<?php

/**
 * Klytos CMS — suspension takes effect on OAuth too (Sprint 6 slice 2 / audit
 * NEW-41, D-060), proven through the real MCP HTTP surface.
 *
 * The finding: TokenAuth::resolveUserActor() read the user record's `role` and
 * never its `status`, so suspending an account left its OAuth access token
 * working until the token expired — up to an hour. Of the three credential
 * types, OAuth was the only one where an operator's suspension did not take
 * effect, and the audit entry says plainly that the INCONSISTENCY is the more
 * dangerous half: an operator who suspends an account reasonably believes
 * access is gone.
 *
 * The property under test is the layer as much as the outcome (D-060): the
 * refusal is **401 at authentication**, where D-056's implementation note 1 put
 * the application-password credential, and NOT 403 at the authorization gate.
 * A status assertion alone would not separate those two, so every refusal here
 * also asserts the wire MESSAGE — the transport's Unauthorized sentence rather
 * than the catalogue's permission-denied one — which is what makes "the same
 * one resolver, one answer" observable instead of asserted.
 *
 * The token is minted through the product's OWN OAuth flow — createClient →
 * handleAuthorize → handleTokenRequest with a real PKCE verifier — never by
 * writing a token record into storage. L-005: a fixture that bypasses the
 * application proves only that the fixture works, and here the fixture would
 * bypass exactly the code path (`user` carried on the token record) the fix
 * reads.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Core\Helpers;
use Klytos\Core\MCP\OAuthServer;
use Klytos\Core\MCP\RateLimiter;
use Klytos\Tests\AdminHttpTestCase;

final class OAuthSuspensionHttpTest extends AdminHttpTestCase
{
    /** A read tool: pages.view, held by every role including viewer. */
    private const READ_TOOL = 'klytos_get_page';

    /** The account this class suspends — never the owner, which update() guards. */
    private const SUBJECT = 'editor';

    /** Exact redirect URI, echoed back on the token exchange. */
    private const REDIRECT_URI = 'http://127.0.0.1/oauth-suspension-test/callback';

    /**
     * Its own port: 8099–8108 are taken by the other HTTP test classes (slice 1
     * took 8108) and a shared port would make the base class's squatter check
     * fire on whichever class ran second.
     */
    protected static function serverPort(): int
    {
        return 8109;
    }

    /**
     * POSITIVE CONTROL — recorded as such rather than counted as evidence for
     * the fix. An OAuth access token for an ACTIVE user reaches a tool and gets
     * a JSON-RPC success, so the refusals below cannot be passing because the
     * minting flow, the transport or the tool is broken. This test passes
     * against the unfixed tree too, which is exactly what a control is for.
     */
    public function testAnOAuthTokenForAnActiveUserIsAccepted(): void
    {
        $token = $this->mintOAuthTokenFor( self::SUBJECT );

        $response = $this->mcpCall( $token, 'tools/call', [
            'name'      => self::READ_TOOL,
            'arguments' => [ 'slug' => 'index' ],
        ] );

        self::assertSame( 200, $response['status'], 'an active user\'s OAuth token must work' );
        self::assertIsArray( $response['json'] );
        self::assertArrayHasKey( 'result', $response['json'] );
        self::assertArrayNotHasKey( 'error', $response['json'] );
    }

    /**
     * THE HEADLINE (NEW-41): suspend the user and the SAME token is refused on
     * the NEXT request — not in an hour when it expires.
     *
     * Observed failing first against the unfixed tree: the suspended user's
     * token returned **200 with a result**, because resolveUserActor() read
     * only `role`. The pre-suspension assertion inside this test is what makes
     * the failure unambiguous — the same token, the same call, one changed
     * field on the user record.
     */
    public function testSuspendingTheUserRefusesTheSameTokenOnTheNextRequest(): void
    {
        $token = $this->mintOAuthTokenFor( self::SUBJECT );

        $before = $this->mcpCall( $token, 'tools/call', [
            'name'      => self::READ_TOOL,
            'arguments' => [ 'slug' => 'index' ],
        ] );
        self::assertSame( 200, $before['status'], 'the token must work before the suspension' );

        $this->suspend( self::SUBJECT );

        $after = $this->mcpCall( $token, 'tools/call', [
            'name'      => self::READ_TOOL,
            'arguments' => [ 'slug' => 'index' ],
        ] );

        self::assertSame(
            401,
            $after['status'],
            'a suspended user\'s OAuth token must be refused at AUTHENTICATION (401), not at the '
            . 'gate (403) — D-060 puts it at the layer D-056 put application passwords'
        );
        self::assertIsArray( $after['json'] );
        self::assertArrayHasKey( 'error', $after['json'] );
        self::assertArrayNotHasKey( 'result', $after['json'] );
        self::assertStringContainsString(
            'Unauthorized',
            (string) ( $after['json']['error']['message'] ?? '' ),
            'the refusal must be the transport\'s authentication answer, not the gate\'s refusal'
        );
    }

    /**
     * The check reads the LIVE record, and revocation is deliberately NOT what
     * this slice built (D-060): reactivating the account makes the very same
     * token work again, because the token was never destroyed. Pinned rather
     * than left implicit, so `docs/reference/mcp-authorization.md`'s statement
     * that this is deny-at-validation is a property the suite holds, not a
     * sentence in a document.
     */
    public function testReactivatingTheUserRestoresTheSameToken(): void
    {
        $token = $this->mintOAuthTokenFor( self::SUBJECT );
        $user  = $this->suspend( self::SUBJECT );

        $denied = $this->mcpCall( $token, 'tools/call', [
            'name'      => self::READ_TOOL,
            'arguments' => [ 'slug' => 'index' ],
        ] );
        self::assertSame( 401, $denied['status'], 'the suspension must take effect first' );

        $this->users->update( $user['id'], [ 'status' => 'active' ] );

        $restored = $this->mcpCall( $token, 'tools/call', [
            'name'      => self::READ_TOOL,
            'arguments' => [ 'slug' => 'index' ],
        ] );

        self::assertSame(
            200,
            $restored['status'],
            'the token is not revoked — the status is read per request, which is what this slice '
            . 'delivers and what the reference doc must say it delivers'
        );
        self::assertArrayHasKey( 'result', $restored['json'] );
    }

    /**
     * The consequence D-060 named rather than discovered later: an OAuth token
     * whose user record is GONE also moves from 403 to 401. Same direction
     * D-056 chose for the same condition on the application-password path —
     * both fail closed, only the layer moves.
     *
     * Observed failing first: against the unfixed tree this answered **403**,
     * the gate's null-actor default-deny (D-046).
     */
    public function testAnOAuthTokenForADeletedUserIsRefusedAtAuthenticationToo(): void
    {
        $token = $this->mintOAuthTokenFor( self::SUBJECT );
        $user  = $this->users->getByUsername( self::SUBJECT );

        self::assertNotNull( $user );
        self::assertTrue( $this->users->delete( $user['id'] ), 'the subject must actually be deleted' );

        $response = $this->mcpCall( $token, 'tools/call', [
            'name'      => self::READ_TOOL,
            'arguments' => [ 'slug' => 'index' ],
        ] );

        self::assertSame( 401, $response['status'], 'an unattributable OAuth token denies at authentication' );
        self::assertStringContainsString(
            'Unauthorized',
            (string) ( $response['json']['error']['message'] ?? '' )
        );
    }

    /**
     * Mint a real OAuth access token for a username through the product's own
     * authorization-code + PKCE flow.
     *
     * @param  string $username Seeded playground user.
     * @return string           The raw access token.
     */
    private function mintOAuthTokenFor( string $username ): string
    {
        $oauth = new OAuthServer(
            $this->auth(),
            $this->storage,
            new RateLimiter( $this->storage->getDataDir() )
        );

        $client = $oauth->createClient( 'oauth-suspension-test', self::REDIRECT_URI, false );

        // PKCE S256, computed the way the server verifies it: the point is to
        // drive the real exchange, so the verifier is genuine.
        $verifier  = Helpers::randomHex( 32 );
        $challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );

        $authorized = $oauth->handleAuthorize(
            [
                'client_id'             => $client['client_id'],
                'redirect_uri'          => self::REDIRECT_URI,
                'code_challenge'        => $challenge,
                'code_challenge_method' => 'S256',
            ],
            $username
        );

        $issued = $oauth->handleTokenRequest( [
            'grant_type'    => 'authorization_code',
            'code'          => $authorized['code'],
            'client_id'     => $client['client_id'],
            'redirect_uri'  => self::REDIRECT_URI,
            'code_verifier' => $verifier,
        ] );

        self::assertTrue(
            $issued['success'] ?? false,
            'the OAuth flow must issue a token: ' . json_encode( $issued )
        );

        return (string) $issued['access_token'];
    }

    /**
     * Suspend a seeded user through the product's own manager.
     *
     * @param  string $username Seeded playground user.
     * @return array            The user record as it stood before the change.
     */
    private function suspend( string $username ): array
    {
        $user = $this->users->getByUsername( $username );

        self::assertNotNull( $user, "the playground user '{$username}' must exist" );

        $this->users->update( $user['id'], [ 'status' => 'suspended' ] );

        $reloaded = $this->users->getByUsername( $username );
        self::assertSame( 'suspended', $reloaded['status'] ?? null, 'the suspension must have persisted' );

        return $user;
    }
}
