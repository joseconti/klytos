<?php

/**
 * Klytos CMS — the capability matrix has one definition and it denies by default
 * (Sprint 1, slice 3 / S-04).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\IntegrationTestCase;

/**
 * Asserts the refusals, not the structure — a matrix that is merely deduplicated
 * but still answers "yes" to a viewer is not a fix.
 *
 * S-04 was recorded in the adoption audit as a live divergence between the two
 * copies of the matrix. The Sprint 1 kickoff re-validation REFUTED that: they
 * were byte-for-byte identical, so the finding is a drift hazard, not an active
 * inconsistency. That is why this file leads with behaviour and keeps the
 * single-definition check as a guard underneath it: the danger was never today's
 * values, it was the next edit landing in one copy only and failing OPEN on
 * whichever path the caller took.
 */
final class PermissionMatrixTest extends IntegrationTestCase
{
    /**
     * A viewer is refused an owner-only permission.
     *
     * @return void
     */
    public function testViewerIsDeniedAnOwnerOnlyPermission(): void
    {
        $this->actingAs( 'viewer' );

        self::assertFalse(
            klytos_has_permission( 'users.manage' ),
            'A viewer must not hold users.manage — it is owner-only in the matrix.'
        );
        self::assertFalse( klytos_has_permission( 'plugins.manage' ) );
        self::assertFalse( klytos_has_permission( 'terminal.access' ) );
    }

    /**
     * An unknown permission is refused for every role except owner.
     *
     * This is the default-deny property. An unrecognised key resolves to an
     * empty allow-list, so a typo in a future gate ("pages.publsh") denies
     * rather than silently permitting everyone — the failure direction that
     * matters.
     *
     * @return void
     */
    public function testUnknownPermissionIsDenied(): void
    {
        foreach ( [ 'viewer', 'editor', 'admin' ] as $role ) {
            $this->actingAs( $role );

            self::assertFalse(
                klytos_has_permission( 'this.permission.does.not.exist' ),
                "Role {$role} must be denied an unknown permission key."
            );
        }
    }

    /**
     * The owner shortcut still grants everything, including unknown keys.
     *
     * Deliberately asserted rather than assumed: the owner branch returns early
     * before the matrix is consulted, so a change to the matrix cannot break it
     * — but a change to the SHORTCUT would silently lock the owner out of the
     * product, and nothing else in the suite would notice.
     *
     * @return void
     */
    public function testOwnerShortcutIsIntact(): void
    {
        $this->actingAs( 'owner' );

        self::assertTrue( klytos_has_permission( 'users.manage' ) );
        self::assertTrue( klytos_has_permission( 'pages.delete' ) );
        self::assertTrue(
            klytos_has_permission( 'a.permission.no.matrix.defines' ),
            'The owner shortcut grants everything by design, unknown keys included.'
        );
    }

    /**
     * Editors and admins land where the matrix says, not merely "somewhere".
     *
     * @return void
     */
    public function testIntermediateRolesMatchTheMatrix(): void
    {
        $this->actingAs( 'editor' );
        self::assertTrue( klytos_has_permission( 'pages.create' ) );
        self::assertFalse( klytos_has_permission( 'pages.delete' ) );
        self::assertFalse( klytos_has_permission( 'site.configure' ) );

        $this->actingAs( 'admin' );
        self::assertTrue( klytos_has_permission( 'pages.delete' ) );
        self::assertTrue( klytos_has_permission( 'site.configure' ) );
        self::assertFalse( klytos_has_permission( 'users.manage' ) );
    }

    /**
     * Both entry points give the same answer for every role and permission.
     *
     * This is the S-04 guard. klytos_has_permission() now delegates to
     * UserManager::hasPermission(), so agreement is structural rather than
     * coincidental — but asserting it across the whole cross-product is what
     * makes a future re-fork of the matrix fail here instead of in production.
     *
     * @return void
     */
    public function testBothEntryPointsAgreeAcrossTheWholeMatrix(): void
    {
        $permissions = [
            'pages.view', 'pages.create', 'pages.edit', 'pages.delete',
            'theme.manage', 'menu.manage', 'blocks.manage', 'templates.manage',
            'templates.approve', 'build.run', 'assets.manage', 'tasks.create',
            'tasks.manage', 'users.manage', 'mcp.manage', 'site.configure',
            'plugins.manage', 'analytics.view', 'forms.manage', 'webhooks.manage',
            'updates.manage', 'terminal.access',
            'an.unknown.key',
        ];

        foreach ( [ 'owner', 'admin', 'editor', 'viewer' ] as $role ) {
            $user = $this->actingAs( $role );

            foreach ( $permissions as $permission ) {
                self::assertSame(
                    $this->users->hasPermission( $user, $permission ),
                    klytos_has_permission( $permission ),
                    "The two entry points disagree for {$role} / {$permission} — the matrix has "
                    . 'been forked again (S-04).'
                );
            }
        }
    }

    /**
     * A user record carrying no usable role holds nothing.
     *
     * Raised by the slice-3 code-reviewer pass. Collapsing the two matrices made
     * UserManager the single decision point, and its `$user['role'] ?? 'viewer'`
     * default meant a malformed or partial record silently inherited the viewer
     * row — pages.view, analytics.view — instead of being refused. Nothing
     * legitimate depends on that default (every record UserManager writes has a
     * VALID_ROLES-checked role), and guessing an identity's privileges is least
     * acceptable at the one place that decides them.
     *
     * @return void
     */
    public function testARecordWithNoUsableRoleIsDenied(): void
    {
        foreach ( [ [], [ 'role' => null ], [ 'role' => '' ], [ 'role' => [ 'owner' ] ] ] as $i => $malformed ) {
            self::assertFalse(
                $this->users->hasPermission( $malformed, 'pages.view' ),
                "Malformed record #{$i} must hold no permission, not inherit the viewer row."
            );
            self::assertFalse( $this->users->hasPermission( $malformed, 'users.manage' ) );
        }
    }

    /**
     * The capability matrix is defined in exactly one place in the codebase.
     *
     * A source-level guard, in the spirit of the slice-2 manifest drift test:
     * the behavioural checks above would still pass if someone reintroduced a
     * second identical copy, and it would sit there until the two drifted. This
     * fails the moment a second definition appears.
     *
     * @return void
     */
    public function testTheMatrixIsDefinedExactlyOnce(): void
    {
        $coreFiles = glob( KLYTOS_INSTALLER_PATH . '/core/*.php' ) ?: [];
        $definitions = [];

        foreach ( $coreFiles as $file ) {
            $source = (string) file_get_contents( $file );

            // The marker is the owner-only entry every copy of the matrix
            // carries; matching a whole array literal would be brittle.
            if ( str_contains( $source, "'templates.approve'" ) && str_contains( $source, "'pages.view'" ) ) {
                $definitions[] = basename( $file );
            }
        }

        self::assertSame(
            [ 'user-manager.php' ],
            $definitions,
            'The capability matrix must exist only in UserManager::hasPermission(). Found in: '
            . implode( ', ', $definitions )
        );
    }
}
