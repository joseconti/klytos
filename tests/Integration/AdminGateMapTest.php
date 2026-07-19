<?php

/**
 * Klytos CMS — the admin gate map covers every surface and denies by default
 * (Sprint 1, slice 4 / S-07).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\IntegrationTestCase;

/**
 * The structural half of slice 4. The behavioural half — that a refusal
 * actually reaches the caller as a 403/401 in the right SHAPE — is asserted
 * over real HTTP in {@see AdminGateHttpTest}, because a gate that decides
 * correctly but renders an HTML login page to an XHR has not refused in any
 * way that caller can act on.
 *
 * S-07's finding was that 51 of 66 admin files never asked for a capability.
 * The fix is not 51 new calls — that has the same failure mode one file later.
 * It is that an UNMAPPED file is refused, which is what these tests pin.
 */
final class AdminGateMapTest extends IntegrationTestCase
{
    /**
     * Every admin page and API endpoint carries a gate-map entry.
     *
     * This is the sprint's acceptance criterion "all 66 files carry a map
     * entry", asserted against the filesystem rather than against a count
     * written down once — a new admin file added next month fails here.
     *
     * @return void
     */
    public function testEveryAdminFileHasAGateMapEntry(): void
    {
        $map     = klytos_admin_gate_map();
        $missing = [];

        foreach ( $this->adminFiles() as $key ) {
            if ( ! array_key_exists( $key, $map ) ) {
                $missing[] = $key;
            }
        }

        self::assertSame(
            [],
            $missing,
            'These admin surfaces have no entry in klytos_admin_gate_map(), so they are denied '
            . 'to everyone by default. Map them deliberately: ' . implode( ', ', $missing )
        );
    }

    /**
     * The map has no entry for a file that does not exist.
     *
     * The other direction of the same parity. A stale entry is how a map rots
     * into fiction: it reads as coverage while the file it names is gone, and
     * the file that replaced it is unmapped.
     *
     * @return void
     */
    public function testTheMapHasNoEntryForAMissingFile(): void
    {
        $onDisk = $this->adminFiles();
        $stale  = array_diff( array_keys( klytos_admin_gate_map() ), $onDisk );

        self::assertSame(
            [],
            array_values( $stale ),
            'The gate map names files that do not exist: ' . implode( ', ', $stale )
        );
    }

    /**
     * bootstrap.php is deliberately NOT mapped, so requesting it is refused.
     *
     * It is the file that HOSTS the gate, not a surface the gate protects.
     * Leaving it unmapped means a direct request for it hits default-deny —
     * which is the correct answer, since it renders nothing and exists only to
     * be included.
     *
     * @return void
     */
    public function testBootstrapIsNotAMappedSurface(): void
    {
        self::assertArrayNotHasKey(
            'bootstrap.php',
            klytos_admin_gate_map(),
            'bootstrap.php must stay unmapped: it is the gate host, not a routable surface.'
        );
    }

    /**
     * Every capability the map names actually exists in the matrix.
     *
     * A typo here fails CLOSED — an unknown key grants nobody but the owner —
     * so it would never announce itself as a security hole. It would announce
     * itself as "only the owner can reach Settings", months later, as a bug
     * report. The matrix is read through its own auth.capabilities filter
     * rather than by parsing the source, so this asserts what the product
     * actually decides with, plugin extensions included.
     *
     * @return void
     */
    public function testEveryMappedCapabilityExistsInTheMatrix(): void
    {
        $known   = $this->capabilityMatrixKeys();
        $unknown = [];

        foreach ( klytos_admin_gate_map() as $surface => $capability ) {
            if ( $capability === null ) {
                continue;
            }

            if ( ! in_array( $capability, $known, true ) ) {
                $unknown[] = "{$surface} => {$capability}";
            }
        }

        self::assertSame(
            [],
            $unknown,
            'The gate map names capabilities the matrix does not define. These deny everyone '
            . 'except the owner, silently: ' . implode( ', ', $unknown )
        );
    }

    /**
     * The four capabilities slice 4 introduced resolve as designed.
     *
     * Asserted behaviourally, per role, rather than by reading the matrix
     * literal — the point is what a viewer can and cannot do, not what an
     * array says.
     *
     * @return void
     */
    public function testTheNewCapabilitiesGrantWhatTheyClaim(): void
    {
        // Self-service: held by every role, or the gate locks people out of
        // their own password.
        foreach ( [ 'owner', 'admin', 'editor', 'viewer' ] as $role ) {
            $this->actingAs( $role );

            foreach ( [ 'profile.edit', 'security.self', 'ui.preferences' ] as $capability ) {
                self::assertTrue(
                    klytos_has_permission( $capability ),
                    "Role {$role} must hold {$capability} — it is a self-service surface."
                );
            }
        }

        // ai.use is deliberately owner+admin while NEW-02 is open: the chat
        // executes MCP tools, and the tool layer has no permission checks yet.
        $this->actingAs( 'owner' );
        self::assertTrue( klytos_has_permission( 'ai.use' ) );
        $this->actingAs( 'admin' );
        self::assertTrue( klytos_has_permission( 'ai.use' ) );
        $this->actingAs( 'editor' );
        self::assertFalse(
            klytos_has_permission( 'ai.use' ),
            'An editor must NOT hold ai.use while NEW-02 is open — reaching the AI chat is '
            . 'owner-equivalent power until Sprint 2 gates the MCP tool layer.'
        );
        $this->actingAs( 'viewer' );
        self::assertFalse( klytos_has_permission( 'ai.use' ) );

        // setup.run is owner-only: before slice 4 any authenticated user could
        // complete the wizard on a fresh install and mint an app password.
        $this->actingAs( 'owner' );
        self::assertTrue( klytos_has_permission( 'setup.run' ) );
        foreach ( [ 'admin', 'editor', 'viewer' ] as $role ) {
            $this->actingAs( $role );
            self::assertFalse(
                klytos_has_permission( 'setup.run' ),
                "Role {$role} must not hold setup.run."
            );
        }
    }

    /**
     * A surface absent from the map resolves to no capability at all.
     *
     * The default-deny property itself, asserted at the map rather than
     * through HTTP: klytos_enforce_admin_gate() refuses on array_key_exists()
     * failing, so what matters is that a plausible-looking new file is genuinely
     * absent rather than matched by some prefix or fallback.
     *
     * @return void
     */
    public function testAnUnmappedSurfaceIsAbsentRatherThanDefaulted(): void
    {
        $map = klytos_admin_gate_map();

        foreach ( [ 'brand-new-page.php', 'api/brand-new-endpoint.php', '../core/app.php' ] as $unmapped ) {
            self::assertArrayNotHasKey(
                $unmapped,
                $map,
                "{$unmapped} must not resolve to any capability — default-deny depends on it."
            );
        }
    }

    /**
     * The gate key is derived from the executed file, not the requested URL.
     *
     * SCRIPT_NAME is caller-influenced; SCRIPT_FILENAME is what PHP actually
     * ran. A gate that can be pointed at a different map row by rewriting a
     * URL is not a gate.
     *
     * @return void
     */
    public function testTheGateKeyResolvesFromTheRealFileAndRefusesOutsiders(): void
    {
        $adminPath = KLYTOS_INSTALLER_PATH . '/admin';

        self::assertSame( 'users.php', klytos_admin_gate_key( $adminPath . '/users.php' ) );
        self::assertSame( 'api/plugins.php', klytos_admin_gate_key( $adminPath . '/api/plugins.php' ) );

        // Traversal resolves to its real location, which is outside admin/.
        self::assertNull(
            klytos_admin_gate_key( $adminPath . '/../core/app.php' ),
            'A path that resolves outside admin/ must not produce a gate key.'
        );
        self::assertNull( klytos_admin_gate_key( KLYTOS_INSTALLER_PATH . '/core/app.php' ) );
        self::assertNull( klytos_admin_gate_key( '' ) );
        self::assertNull( klytos_admin_gate_key( '/nonexistent/file.php' ) );
    }

    /**
     * Every admin file on disk, keyed the way the gate map keys them.
     *
     * bootstrap.php is excluded because it is the includer rather than a
     * surface — asserted separately by testBootstrapIsNotAMappedSurface().
     *
     * @return array<int, string>
     */
    private function adminFiles(): array
    {
        $adminPath = KLYTOS_INSTALLER_PATH . '/admin';
        $keys      = [];

        foreach ( glob( $adminPath . '/*.php' ) ?: [] as $file ) {
            $name = basename( $file );

            if ( $name === 'bootstrap.php' ) {
                continue;
            }

            $keys[] = $name;
        }

        foreach ( glob( $adminPath . '/api/*.php' ) ?: [] as $file ) {
            $keys[] = 'api/' . basename( $file );
        }

        sort( $keys );

        return $keys;
    }

    /**
     * The capability matrix's keys, read through the product's own filter.
     *
     * @return array<int, string>
     */
    private function capabilityMatrixKeys(): array
    {
        $captured = [];

        $capture = static function ( array $capabilities ) use ( &$captured ): array {
            $captured = $capabilities;
            return $capabilities;
        };

        klytos_add_filter( 'auth.capabilities', $capture );

        // Any non-owner check reaches the matrix; the owner shortcut returns
        // before it is built, so this must NOT act as owner.
        $this->actingAs( 'viewer' );
        klytos_has_permission( 'pages.view' );

        klytos_remove_filter( 'auth.capabilities', $capture );

        self::assertNotEmpty( $captured, 'The auth.capabilities filter yielded no matrix.' );

        return array_keys( $captured );
    }
}
