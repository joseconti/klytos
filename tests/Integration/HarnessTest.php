<?php

/**
 * Klytos CMS — integration tier self-check (Sprint 1, slice 1 / T-01).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\IntegrationTestCase;

/**
 * Proves the integration tier's seam works, before any slice depends on it.
 *
 * The load-bearing assertion is testSessionSeamIsAcceptedByAuth(): it verifies
 * that a session assembled by IntegrationTestCase::actingAs() is accepted by
 * the application's own Auth as that user. Without it, a drift in Auth's
 * session shape would leave every future authorization test asserting refusals
 * against an anonymous session — passing for entirely the wrong reason.
 */
final class HarnessTest extends IntegrationTestCase
{
    public function testAppBootsAgainstThePlayground(): void
    {
        self::assertTrue( $this->app->isInstalled() );
        self::assertSame( 'Klytos Playground', $this->app->getConfig()['site_name'] ?? null );
    }

    /**
     * One user per role is what makes authorization testable at all (slice 0).
     */
    public function testEverySeededRoleExists(): void
    {
        foreach ( [ 'owner', 'admin', 'editor', 'viewer' ] as $role ) {
            $user = $this->users->getByUsername( $role );

            self::assertNotNull( $user, "Seeded user '{$role}' is missing" );
            self::assertSame( $role, $user['role'] );
            self::assertSame( 'active', $user['status'] );
        }
    }

    public function testSessionSeamIsAcceptedByAuth(): void
    {
        $viewer = $this->actingAs( 'viewer' );

        self::assertTrue(
            $this->auth()->isAuthenticated(),
            'actingAs() produced a session Auth does not accept — the session shape has drifted '
            . 'from Auth::login(). Every authorization test built on this seam is now meaningless.'
        );
        self::assertSame( $viewer['id'], $this->auth()->getUserId() );
        self::assertSame( 'viewer', klytos_current_user()['role'] ?? null );
    }

    public function testEachRoleResolvesToItsOwnIdentity(): void
    {
        foreach ( [ 'owner', 'admin', 'editor', 'viewer' ] as $role ) {
            $this->actingAs( $role );

            self::assertSame( $role, klytos_current_user()['role'] ?? null );
            self::assertSame( $role, klytos_current_user()['username'] ?? null );
        }
    }

    public function testGuestIsNotAuthenticated(): void
    {
        $this->actingAsGuest();

        self::assertFalse( $this->auth()->isAuthenticated() );
        self::assertNull( $this->auth()->getUserId() );
        self::assertNull( klytos_current_user() );
    }
}
