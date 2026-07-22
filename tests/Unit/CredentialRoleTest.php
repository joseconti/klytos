<?php

/**
 * Klytos CMS — MCP bearer-token role: stamp, resolve, migrate
 * (Sprint 2, slice 1 / NEW-02, D-046, D-047).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\Auth;
use Klytos\Tests\UnitTestCase;

/**
 * Bearer tokens carry no user, so — unlike application passwords and OAuth tokens,
 * which resolve their role from the user record — a bearer's role lives on the token
 * record itself: stamped by createBearerToken(), backfilled by migrateCredentialRoles(),
 * and read back by getBearerTokenActor().
 *
 * These run on a bare checkout: they need only Auth over a temp FileStorage, no App and
 * no session — which is the point, because the MCP path has no session and identity must
 * come from the credential (D-046).
 *
 * Every assertion is a property only the fix produces: a role on the record, and a null
 * for an ABSENT role. A getBearerTokenActor() that defaulted an absent role to 'owner' —
 * the tempting-but-wrong choice D-047 rejects — fails testATokenRecordWithNoRole...; a
 * migrateCredentialRoles() that did not stamp fails testMigrationStamps.... Both were
 * observed failing against those reverted behaviours before being trusted (L-010).
 */
final class CredentialRoleTest extends UnitTestCase
{
    private function auth(): Auth
    {
        return new Auth( [], $this->storage );
    }

    public function testCreateBearerTokenStampsTheGivenRole(): void
    {
        $auth    = $this->auth();
        $created = $auth->createBearerToken( 'viewer token', 'viewer' );

        self::assertSame(
            [ 'user_id' => null, 'role' => 'viewer' ],
            $auth->getBearerTokenActor( $created['token'] ),
            'A bearer token minted with role=viewer resolves to a viewer actor with no user.'
        );
    }

    public function testCreateBearerTokenDefaultsToOwner(): void
    {
        $auth    = $this->auth();
        $created = $auth->createBearerToken( 'default token' );

        self::assertSame(
            'owner',
            $auth->getBearerTokenActor( $created['token'] )['role'],
            'The default role reproduces the pre-Sprint-2 owner-equivalent power (NEW-02).'
        );
    }

    public function testUnknownTokenResolvesToNull(): void
    {
        self::assertNull(
            $this->auth()->getBearerTokenActor( 'not-a-real-token' ),
            'A token that is not in the store is not an actor.'
        );
    }

    public function testATokenRecordWithNoRoleResolvesToANullRoleNotOwner(): void
    {
        $auth    = $this->auth();
        $created = $auth->createBearerToken( 'legacy token', 'viewer' );

        // Simulate a pre-Sprint-2 record: strip the role so it looks like a token
        // the boot migration has not reached.
        $data = $this->storage->read( 'config', 'tokens' );
        unset( $data['tokens'][0]['role'] );
        $this->storage->write( 'config', 'tokens', $data );

        self::assertNull(
            $auth->getBearerTokenActor( $created['token'] )['role'],
            'An absent role resolves to null (deny), never a default of owner (D-047).'
        );
    }

    public function testMigrationStampsRolelessRecordsWithOwnerAndIsIdempotent(): void
    {
        $auth    = $this->auth();
        $created = $auth->createBearerToken( 'legacy token', 'viewer' );

        $data = $this->storage->read( 'config', 'tokens' );
        unset( $data['tokens'][0]['role'] );
        $this->storage->write( 'config', 'tokens', $data );

        self::assertSame(
            1,
            $auth->migrateCredentialRoles(),
            'The one role-less record is stamped.'
        );
        self::assertSame(
            'owner',
            $auth->getBearerTokenActor( $created['token'] )['role'],
            'Migration records the owner-equivalent power the token already had (NEW-02).'
        );
        self::assertSame(
            0,
            $auth->migrateCredentialRoles(),
            'The migration is idempotent: a migrated store is a no-op.'
        );
    }

    public function testMigrationLeavesAnExistingRoleUntouched(): void
    {
        $auth = $this->auth();
        $auth->createBearerToken( 'viewer token', 'viewer' );

        self::assertSame(
            0,
            $auth->migrateCredentialRoles(),
            'A token that already carries a role must not be re-stamped to owner — a real viewer token survives migration.'
        );
    }

    public function testMigrationOnAnEmptyStoreDoesNothing(): void
    {
        self::assertSame(
            0,
            $this->auth()->migrateCredentialRoles(),
            'No tokens store yet (fresh install) — nothing to migrate, and no error.'
        );
    }
}
