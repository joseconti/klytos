<?php

/**
 * Klytos CMS — klytos_current_user() denies instead of promoting
 * (Sprint 1, slice 3 / NEW-01, D-021).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Core\Helpers;
use Klytos\Tests\IntegrationTestCase;

/**
 * The prerequisite finding of the whole sprint, asserted directly.
 *
 * klytos_current_user() used to fall back to a hardcoded ['role' => 'owner']
 * built from config whenever the session carried no klytos_user_id or the user
 * lookup failed. That did not weaken one gate — it defeated every gate the
 * sprint adds, because a gate is only as good as the identity it is handed.
 *
 * These tests construct the session shapes that triggered the promotion and
 * assert denial. They are the reason slice 3 comes before slices 4 and 5.
 */
final class CurrentUserFailClosedTest extends IntegrationTestCase
{
    /**
     * An authenticated session with no klytos_user_id resolves to nobody.
     *
     * This is the exact NEW-01 shape: Auth::isAuthenticated() is satisfied
     * (klytos_auth is true and the activity timestamps are fresh), but there is
     * no user id. The old code answered "you are the owner" here.
     *
     * @return void
     */
    public function testSessionWithoutUserIdIsDeniedNotPromoted(): void
    {
        $_SESSION = [
            'klytos_auth'        => true,
            'klytos_user'        => 'someone',
            'klytos_login_time'  => time(),
            'klytos_last_active' => time(),
            'klytos_csrf'        => Helpers::randomHex( 32 ),
            // klytos_user_id deliberately absent — this is the finding.
        ];

        self::assertTrue(
            $this->auth()->isAuthenticated(),
            'Precondition: the session must pass authentication, or this test proves nothing.'
        );

        self::assertNull(
            klytos_current_user(),
            'A session without klytos_user_id must resolve to nobody (NEW-01).'
        );
    }

    /**
     * ...and therefore holds no permission at all — not even the mildest one.
     *
     * Asserted separately from the null check because this is the consequence
     * that mattered: the promotion granted 'owner', which short-circuits the
     * matrix entirely and returns true for every permission that exists.
     *
     * @return void
     */
    public function testSessionWithoutUserIdHoldsNoPermission(): void
    {
        $_SESSION = [
            'klytos_auth'        => true,
            'klytos_user'        => 'someone',
            'klytos_login_time'  => time(),
            'klytos_last_active' => time(),
            'klytos_csrf'        => Helpers::randomHex( 32 ),
        ];

        self::assertFalse( klytos_has_permission( 'users.manage' ) );
        self::assertFalse( klytos_has_permission( 'plugins.manage' ) );
        self::assertFalse(
            klytos_has_permission( 'pages.view' ),
            'Not even the weakest permission — an unidentified session is not a viewer, it is nobody.'
        );
    }

    /**
     * A session naming a user that does not exist is denied, not promoted.
     *
     * The second half of NEW-01: the old code caught the RuntimeException from
     * the failed lookup and fell through to the same owner fallback, so a
     * deleted user's live session gained owner rights the moment their record
     * disappeared.
     *
     * @return void
     */
    public function testSessionNamingAMissingUserIsDenied(): void
    {
        $_SESSION = [
            'klytos_auth'        => true,
            'klytos_user'        => 'ghost',
            'klytos_user_id'     => 'ffffffffffffffff',
            'klytos_login_time'  => time(),
            'klytos_last_active' => time(),
            'klytos_csrf'        => Helpers::randomHex( 32 ),
        ];

        self::assertNull( klytos_current_user() );
        self::assertFalse( klytos_has_permission( 'pages.view' ) );
    }

    /**
     * An anonymous request is denied, as it always was.
     *
     * A regression guard on the branch that was already correct, so that
     * tightening the branches around it cannot quietly break this one.
     *
     * @return void
     */
    public function testGuestIsDenied(): void
    {
        $this->actingAsGuest();

        self::assertNull( klytos_current_user() );
        self::assertFalse( klytos_has_permission( 'pages.view' ) );
    }

    /**
     * A properly authenticated session still resolves to its real user.
     *
     * The fail-closed change is only correct if it did not also break the happy
     * path — a version of this function that returned null unconditionally would
     * pass every other test in this file.
     *
     * @return void
     */
    public function testValidSessionStillResolvesToTheRealUser(): void
    {
        foreach ( [ 'owner', 'admin', 'editor', 'viewer' ] as $role ) {
            $expected = $this->actingAs( $role );
            $actual   = klytos_current_user();

            self::assertNotNull( $actual, "A valid {$role} session must resolve." );
            self::assertSame( $expected['id'], $actual['id'] );
            self::assertSame( $role, $actual['role'] );
        }
    }

    /**
     * The resolved user never carries the password hash.
     *
     * klytos_current_user() feeds templates and API responses, so it is a real
     * exposure surface. UserManager::getById() sanitizes; this pins that it
     * stays that way.
     *
     * @return void
     */
    public function testResolvedUserNeverCarriesThePasswordHash(): void
    {
        $this->actingAs( 'owner' );

        $user = klytos_current_user();

        self::assertNotNull( $user );
        self::assertArrayNotHasKey( 'pass_hash', $user );
    }
}
