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
     * Neither refusal logs under a source `Logger::write()` will discard
     * (audit NEW-44, D-059).
     *
     * `klytos_enforce_admin_gate()` has two refusals. Only one — the unmapped
     * surface — is reachable over HTTP, and {@see AdminGateHttpTest} proves
     * that one lands in the log file for real. The other fires when the request
     * does not resolve to a file inside `admin/` at all, which no request
     * through the router can produce, so nothing else can pin it.
     *
     * That is exactly how NEW-44 survived: both calls passed `'security'` as
     * the $source, `Logger::write():122` treats any source other than `'core'`
     * as a PLUGIN ID, no plugin is called `security`, and so both refusals were
     * dropped — with Developer Mode on or off. Reading the call said it worked.
     *
     * Scoped to this ONE file on purpose. Passing a plugin ID as the source is
     * the correct, documented use of that parameter elsewhere in core (that is
     * how a plugin's own entries are attributed), so a repository-wide version
     * of this assertion would be wrong rather than merely broad.
     *
     * @return void
     */
    public function testNeitherGateRefusalLogsUnderASourceTheLoggerWillDiscard(): void
    {
        $path   = KLYTOS_INSTALLER_PATH . '/core/admin-gate.php';
        $source = (string) file_get_contents( $path );

        self::assertNotSame( '', $source, 'admin-gate.php could not be read, so nothing was scanned.' );

        $calls = $this->logCallsIn( $source );

        self::assertNotEmpty(
            $calls,
            'No klytos_log_* call was found in admin-gate.php. Either the gate stopped recording '
            . 'its refusals, or this scan stopped working — it has not passed, it did not run.'
        );

        foreach ( $calls as $call ) {
            self::assertSame(
                1,
                $this->topLevelCommas( $call ),
                "This gate refusal passes a third argument to klytos_log_*(), which is the \$source:\n"
                . $call . "\n"
                . 'Logger::write() drops any source other than "core" unless a PLUGIN of that ID has '
                . 'logging enabled, so the refusal would write nothing at all. Put the category in '
                . 'the message and the context instead — that is what NEW-44 was.'
            );
        }
    }

    /**
     * Every `klytos_log_*( ... )` call in a PHP source, as raw text.
     *
     * Paren-matched rather than regex-terminated, because these calls span
     * several lines and a line-based pattern is a measurement of the calls that
     * happen to fit on one line, not of the calls (L-023).
     *
     * KNOWN LIMIT, stated rather than denied: the matcher counts literal
     * brackets with no awareness of string or comment context. Both messages in
     * `admin-gate.php` today contain the literal `(security)`, and this works
     * only because that pair is BALANCED. An unbalanced bracket inside a future
     * message — natural-language log text makes that plausible — would
     * mis-terminate the captured call and could miscount its arguments in
     * either direction, including silently missing a reintroduced $source. A
     * tokenizer would be exact; this is deliberately not one, because the scan
     * covers a single ten-line function and a `token_get_all()` walk would be
     * more machinery than the thing it guards. If this file's log messages ever
     * carry unbalanced brackets, replace the matcher rather than adjusting the
     * expectation.
     *
     * @param  string $source PHP source.
     * @return array<int, string> Each call's argument list, parentheses included.
     */
    private function logCallsIn( string $source ): array
    {
        $calls  = [];
        $offset = 0;

        while ( preg_match( '/klytos_log_[a-z]+\s*\(/', $source, $m, PREG_OFFSET_CAPTURE, $offset ) === 1 ) {
            $open  = (int) $m[0][1] + strlen( $m[0][0] ) - 1;
            $depth = 0;

            for ( $i = $open, $len = strlen( $source ); $i < $len; $i++ ) {
                if ( $source[ $i ] === '(' ) {
                    $depth++;
                } elseif ( $source[ $i ] === ')' ) {
                    $depth--;

                    if ( $depth === 0 ) {
                        $calls[] = substr( $source, $open, $i - $open + 1 );
                        break;
                    }
                }
            }

            $offset = $open + 1;
        }

        return $calls;
    }

    /**
     * Commas separating this call's own arguments, ignoring nested ones.
     *
     * @param  string $call An argument list, parentheses included.
     * @return int
     */
    private function topLevelCommas( string $call ): int
    {
        $commas = 0;
        $depth  = 0;

        for ( $i = 0, $len = strlen( $call ); $i < $len; $i++ ) {
            $char = $call[ $i ];

            if ( $char === '(' || $char === '[' ) {
                $depth++;
            } elseif ( $char === ')' || $char === ']' ) {
                $depth--;
            } elseif ( $char === ',' && $depth === 1 ) {
                $commas++;
            }
        }

        return $commas;
    }

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

        // ai.use includes editor since Sprint 2 slice 4 (D-051, superseding
        // D-035 on its own recorded trigger). D-035 excluded editor for ONE
        // stated reason — NEW-02 left the MCP tool layer ungated, so the chat
        // amplified any role to owner. Sprint 2 closed that: the chat's tool
        // calls go through the same default-deny gate carrying the caller's own
        // role, so an editor in the chat can do exactly what an editor may do.
        foreach ( [ 'owner', 'admin', 'editor' ] as $role ) {
            $this->actingAs( $role );
            self::assertTrue(
                klytos_has_permission( 'ai.use' ),
                "Role {$role} must hold ai.use — the AI chat no longer amplifies a role, "
                . 'because ToolRegistry::call() gates every tool with the caller\'s own role (D-051).'
            );
        }

        // Viewer stays out: a read-only role has no authoring work for an agent
        // to do, and the gate would refuse nearly everything it asked for.
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
