<?php

/**
 * Klytos CMS — MCP actor resolution: identity from the credential, fail-closed
 * (Sprint 2, slice 1 / NEW-02, D-046, D-047).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Core\Helpers;
use Klytos\Core\MCP\TokenAuth;
use Klytos\Tests\IntegrationTestCase;

/**
 * The MCP path has NO session — Auth::startSession() runs only in the admin path —
 * so klytos_current_user() returns null on every tools/call and identity must be built
 * from the credential instead (D-046). TokenAuth::validate() now resolves an actor
 * {user_id, role} from whichever credential authenticated, and getActor() surfaces it
 * for the gate that slice 2 adds.
 *
 * These drive validate() against the REAL App with REAL credentials — an app password,
 * bearer tokens at two roles — and, crucially, the deny direction: a credential that
 * still authenticates but that the store can no longer attribute to a usable role must
 * resolve to no actor, so the gate denies rather than escalating (the NEW-08 link).
 */
final class McpActorResolutionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAuthHeaders();
    }

    protected function tearDown(): void
    {
        $this->clearAuthHeaders();
        parent::tearDown();
    }

    /**
     * TokenAuth reads the credential from $_SERVER; leaking one between tests would
     * make a later test authenticate as the wrong caller. Cleared both sides.
     *
     * @return void
     */
    private function clearAuthHeaders(): void
    {
        unset(
            $_SERVER['HTTP_AUTHORIZATION'],
            $_SERVER['PHP_AUTH_USER'],
            $_SERVER['PHP_AUTH_PW'],
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        );
    }

    private function tokenAuth(): TokenAuth
    {
        return new TokenAuth( $this->app->getAuth(), $this->app );
    }

    /**
     * The v1 config admin user; in the seeded playground this is the owner.
     *
     * Application passwords used to be PINNED to this username
     * (core/auth.php::validateAppPassword compared against config['admin_user']),
     * which is why these tests were written around it. Since D-056 they are not:
     * the credential must belong to an active USER RECORD, so any account can hold
     * one — see testANonOwnerApplicationPasswordCarriesItsOwnRole below.
     *
     * @return string
     */
    private function adminUser(): string
    {
        return $this->app->getConfig()['admin_user'] ?? 'owner';
    }

    /**
     * A non-owner's application password authenticates AND arrives at the gate
     * carrying its own role (Sprint 5 slice 1 / D-056).
     *
     * This is the credential the product could already mint and could never use:
     * `admin/mcp.php:48` creates application passwords under
     * `$auth->getUsername()`, and that page is gated `mcp.manage` — owner AND
     * admin. So the moment an admin could log in, they could issue a credential
     * that `validateAppPassword()` refused, because it compared the username
     * against config['admin_user'].
     *
     * The role assertion is the half that matters for D-046's gate: an editor's
     * credential must reach it as an EDITOR, not as the owner. `resolveUserActor()`
     * was already written to read the role from the record (D-047, deliberately
     * "NEW-11-ready"), so no change was needed there — which this proves rather
     * than assumes.
     */
    public function testANonOwnerApplicationPasswordCarriesItsOwnRole(): void
    {
        $record = $this->users->getByUsername( 'editor' );
        self::assertNotNull( $record, 'PRECONDITION: the playground has no editor.' );

        $created = $this->app->getAuth()->createAppPassword( 'editor MCP credential', 'editor' );

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( 'editor:' . $created['password'] );

        $auth = $this->tokenAuth();

        self::assertTrue(
            $auth->validate(),
            "An editor's application password does not authenticate at all."
        );
        self::assertSame(
            'editor',
            $auth->getActor()['role'] ?? null,
            "The editor's credential reached the gate carrying the wrong role."
        );
        self::assertSame(
            $record['id'],
            $auth->getActor()['user_id'] ?? null,
            'The credential resolved to a different user.'
        );
    }

    /**
     * A suspended user's application password stops working.
     *
     * Suspension has to reach every credential the account holds, not only the
     * login form — otherwise "suspended" means "may no longer use the browser".
     */
    public function testASuspendedUsersApplicationPasswordIsRefused(): void
    {
        $record = $this->users->getByUsername( 'viewer' );
        self::assertNotNull( $record );

        $created = $this->app->getAuth()->createAppPassword( 'viewer MCP credential', 'viewer' );

        // Positive control first: the credential must be one that WOULD work, or
        // the refusal below proves nothing (L-010).
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( 'viewer:' . $created['password'] );
        self::assertTrue(
            $this->tokenAuth()->validate(),
            'PRECONDITION: this credential does not authenticate even before suspension.'
        );

        $this->users->update( $record['id'], [ 'status' => 'suspended' ] );

        $auth = $this->tokenAuth();

        self::assertFalse(
            $auth->validate(),
            "A suspended user's application password still authenticates."
        );
        self::assertNull( $auth->getActor(), 'A refused credential still produced an actor.' );
    }

    public function testApplicationPasswordResolvesToTheOwnerActor(): void
    {
        $username = $this->adminUser();
        $created  = $this->app->getAuth()->createAppPassword( 'actor test', $username );

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( $username . ':' . $created['password'] );

        $auth = $this->tokenAuth();

        self::assertTrue( $auth->validate(), 'Precondition: the app password must authenticate.' );

        $actor = $auth->getActor();
        self::assertNotNull( $actor, 'A valid app password must resolve to an actor.' );
        self::assertSame(
            'owner',
            $actor['role'],
            'An app password is pinned to the admin user, who is the owner — resolved from the user record.'
        );

        $owner = $this->users->getByUsername( $username );
        self::assertSame( $owner['id'], $actor['user_id'], 'The actor carries the resolved user id, for audit.' );
    }

    public function testViewerBearerTokenResolvesToAViewerActor(): void
    {
        $created                      = $this->app->getAuth()->createBearerToken( 'viewer actor', 'viewer' );
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $created['token'];

        $auth = $this->tokenAuth();

        self::assertTrue( $auth->validate() );
        self::assertSame(
            [ 'user_id' => null, 'role' => 'viewer' ],
            $auth->getActor(),
            'A role=viewer bearer token is the sprint\'s reduced-credential proof: it resolves to viewer, not owner.'
        );
    }

    public function testDefaultBearerTokenResolvesToOwner(): void
    {
        $created                      = $this->app->getAuth()->createBearerToken( 'default actor' );
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $created['token'];

        $auth = $this->tokenAuth();

        self::assertTrue( $auth->validate() );
        self::assertSame( 'owner', $auth->getActor()['role'] );
    }

    /**
     * Write an OAuth access-token record straight to the store — the shape
     * OAuthServer::issueTokens() produces — and return the raw token. Minting one
     * through the full authorize/exchange flow would need a registered client and a
     * consent step; this exercises exactly the branch under test: the stored `user`
     * subject flowing into the actor via resolveUserActor().
     *
     * @param  string $user The token's subject (a real username, or one that does not resolve).
     * @return string       The raw bearer token to present.
     */
    private function seedOAuthToken( string $user ): string
    {
        $raw = Helpers::randomHex( 32 );

        $record = [
            'id'                => 'ot_' . Helpers::randomHex( 4 ),
            'access_token_hash' => Helpers::hashToken( $raw ),
            'client_id'         => 'test-client',
            'user'              => $user,
            'access_expires_at' => time() + 3600,
        ];

        $this->storage->write( 'oauth_tokens.json.enc', [ 'tokens' => [ $record ] ] );

        return $raw;
    }

    public function testOAuthTokenResolvesToItsSubjectsRole(): void
    {
        $raw                           = $this->seedOAuthToken( $this->adminUser() );
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $raw;

        $auth = $this->tokenAuth();

        self::assertTrue( $auth->validate(), 'Precondition: the OAuth token must authenticate.' );

        $actor = $auth->getActor();
        self::assertNotNull( $actor, 'A valid OAuth token must resolve to an actor.' );
        self::assertSame(
            'owner',
            $actor['role'],
            'An OAuth token resolves to the role of its stored subject user (D-047).'
        );
    }

    public function testOAuthTokenForAnUnknownSubjectResolvesToNoActor(): void
    {
        $raw                           = $this->seedOAuthToken( 'ghost-' . Helpers::randomHex( 4 ) );
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $raw;

        $auth = $this->tokenAuth();

        self::assertTrue( $auth->validate(), 'The OAuth token itself authenticates.' );
        self::assertNull(
            $auth->getActor(),
            'An OAuth token whose subject is not a user resolves to no actor — deny, not escalate.'
        );
    }

    public function testABearerTokenWithNoRoleResolvesToANullRole(): void
    {
        $created = $this->app->getAuth()->createBearerToken( 'legacy actor', 'viewer' );

        // Strip the role so the record looks like a pre-Sprint-2 token; this asserts
        // the RESOLVER is fail-closed on its own, independent of the boot migration.
        $data = $this->storage->read( 'config', 'tokens' );
        foreach ( $data['tokens'] as &$token ) {
            unset( $token['role'] );
        }
        unset( $token );
        $this->storage->write( 'config', 'tokens', $data );

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $created['token'];

        $auth = $this->tokenAuth();

        self::assertTrue( $auth->validate(), 'The token still authenticates — only its role is absent.' );
        self::assertNull(
            $auth->getActor()['role'],
            'An absent role resolves to null (deny), never owner.'
        );
    }

    public function testAValidCredentialWhoseUserIsGoneResolvesToNoActor(): void
    {
        $username = $this->adminUser();
        $created  = $this->app->getAuth()->createAppPassword( 'orphan test', $username );

        // Remove the user the app password authenticates as, at the storage layer —
        // bypassing UserManager::delete()'s "cannot delete the owner" rule on purpose,
        // because the state being simulated is exactly NEW-08: a corrupted / half-migrated
        // install whose owner record is gone. ('users' is UserManager::COLLECTION.)
        $owner = $this->users->getByUsername( $username );
        $this->storage->delete( 'users', $owner['id'] );

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( $username . ':' . $created['password'] );

        $auth = $this->tokenAuth();

        // BOTH assertions are the point, and the FIRST one changed with D-056.
        // Until the user record became the sole login authority, validateAppPassword()
        // compared the username against config['admin_user'], so an orphan credential
        // still AUTHENTICATED and was stopped one layer later by a null actor (403 at
        // D-046's gate). It now requires the username to resolve to an ACTIVE record,
        // so it is refused at authentication instead (401). The property this test
        // exists for — a credential the store cannot attribute must DENY, never
        // escalate — is unchanged and now holds one layer earlier; recorded as
        // implementation note 1 on D-056 before this test was touched.
        self::assertFalse(
            $auth->validate(),
            'A credential whose user record is gone no longer authenticates at all (D-056).'
        );
        self::assertNull(
            $auth->getActor(),
            'A credential the store can no longer attribute to a user resolves to no actor — deny, not escalate (NEW-08).'
        );
    }
}
