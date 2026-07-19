<?php

/**
 * Klytos CMS — the v1.x → v2.0 owner migration is idempotent and fails safely
 * (Sprint 1, slice 3 / D-021).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\IntegrationTestCase;
use RuntimeException;

/**
 * The compensating half of NEW-01.
 *
 * Removing the owner fallback from klytos_current_user() is only safe because a
 * real v1.x install gets a genuine owner record instead — created by the
 * migration wired into boot (app.php Step 10b). If that migration were not
 * idempotent, or did not run, the fail-closed change would lock owners out of
 * production installs. This file is what makes that claim testable rather than
 * asserted.
 *
 * These tests DELETE the seeded owner. They are only safe because the tier
 * snapshots and restores the playground around every test (D-030) — this is the
 * consumer that decision was made for, and the case a create-and-destroy fixture
 * could not express, since the owner record belongs to the seed.
 */
final class V1MigrationTest extends IntegrationTestCase
{
    /**
     * A v1.x-shaped config, i.e. credentials in config with no users collection.
     *
     * Built explicitly rather than read from the playground's own config,
     * because the playground is a v2.0 install: its config may or may not still
     * carry the v1 keys, and a test that silently degrades when they are absent
     * proves nothing. This is the shape the migration actually targets.
     *
     * @return array<string, string>
     */
    private function v1Config(): array
    {
        return [
            'admin_user'      => 'legacyadmin',
            'admin_email'     => 'legacy@example.test',
            'admin_pass_hash' => password_hash( 'irrelevant-for-this-test', PASSWORD_DEFAULT ),
            'installed_at'    => '2024-01-01 00:00:00',
        ];
    }

    /**
     * Remove every owner record, leaving the install in the pre-migration state.
     *
     * @return void
     */
    private function removeOwners(): void
    {
        foreach ( $this->storage->list( 'users' ) as $user ) {
            if ( ( $user['role'] ?? '' ) === 'owner' ) {
                $this->storage->delete( 'users', $user['id'] );
            }
        }

        self::assertNull(
            $this->users->findOwner(),
            'Precondition: the install must have no owner, or the migration is not being exercised.'
        );
    }

    /**
     * Running the migration twice creates exactly one owner.
     *
     * Idempotency is the property the boot wiring depends on: Step 10b runs on
     * EVERY request, not only the first after an upgrade, so a migration that
     * appended an owner per boot would multiply owners without limit.
     *
     * @return void
     */
    public function testMigrationIsIdempotent(): void
    {
        $this->removeOwners();

        $first = $this->users->migrateFromV1Config( $this->v1Config() );

        self::assertSame( 'owner', $first['role'] );
        self::assertSame( 'legacyadmin', $first['username'] );

        $second = $this->users->migrateFromV1Config( $this->v1Config() );

        self::assertSame(
            $first['id'],
            $second['id'],
            'The second run must return the existing owner, never mint a new one.'
        );

        $owners = array_filter(
            $this->storage->list( 'users' ),
            static fn( array $u ): bool => ( $u['role'] ?? '' ) === 'owner'
        );

        self::assertCount( 1, $owners, 'Exactly one owner must exist after two migration runs.' );
    }

    /**
     * The migrated owner is a real, resolvable identity — not a synthetic one.
     *
     * This is the difference between the fix and the bug it replaces. The old
     * fallback fabricated ['id' => 'admin', 'role' => 'owner'] in memory, which
     * resolved to no stored record at all. The migration must produce a user
     * that klytos_current_user() can actually look up by id.
     *
     * @return void
     */
    public function testMigratedOwnerIsResolvableAsARealUser(): void
    {
        $this->removeOwners();

        $migrated = $this->users->migrateFromV1Config( $this->v1Config() );

        $this->actingAs( 'legacyadmin' );

        $resolved = klytos_current_user();

        self::assertNotNull( $resolved, 'The migrated owner must resolve through the normal path.' );
        self::assertSame( $migrated['id'], $resolved['id'] );
        self::assertSame( 'owner', $resolved['role'] );
        self::assertTrue( klytos_has_permission( 'users.manage' ) );
    }

    /**
     * The migrated owner never carries the password hash outward.
     *
     * @return void
     */
    public function testMigratedOwnerIsSanitized(): void
    {
        $this->removeOwners();

        $migrated = $this->users->migrateFromV1Config( $this->v1Config() );

        self::assertArrayNotHasKey( 'pass_hash', $migrated );
    }

    /**
     * A v1 config with no usable admin_email makes the migration throw.
     *
     * Pinned deliberately, because this throw is what the boot sequence now
     * catches. Before slice 3 it propagated out of App::boot() uncaught and took
     * the whole application down on every request — a white screen on an install
     * that was already in trouble. If this behaviour ever changes, the catch in
     * app.php Step 10b becomes dead code and this test says so.
     *
     * @return void
     */
    public function testMigrationRejectsAConfigWithoutAUsableEmail(): void
    {
        $this->removeOwners();

        $broken = $this->v1Config();
        unset( $broken['admin_email'] );

        $this->expectException( RuntimeException::class );

        $this->users->migrateFromV1Config( $broken );
    }

    /**
     * A failed migration leaves NO owner — it never half-creates one.
     *
     * The security-relevant half of the previous test. Fail-closed only holds if
     * a rejected migration leaves the install with no owner at all; a partially
     * written owner record with an empty password hash would be worse than the
     * crash it replaced.
     *
     * @return void
     */
    public function testAFailedMigrationLeavesNoOwnerBehind(): void
    {
        $this->removeOwners();

        $broken = $this->v1Config();
        $broken['admin_email'] = 'not-an-email';

        try {
            $this->users->migrateFromV1Config( $broken );
            self::fail( 'The migration should have rejected an invalid admin_email.' );
        } catch ( RuntimeException ) {
            // Expected.
        }

        self::assertNull(
            $this->users->findOwner(),
            'A rejected migration must leave no owner record at all.'
        );

        // And the consequence that makes this safe rather than merely tidy:
        // with no owner, nobody is silently promoted into the gap.
        $this->actingAsGuest();
        self::assertNull( klytos_current_user() );
        self::assertFalse( klytos_has_permission( 'users.manage' ) );
    }
}
