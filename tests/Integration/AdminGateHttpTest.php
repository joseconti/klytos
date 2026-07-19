<?php

/**
 * Klytos CMS — the admin gate refuses in the SHAPE the caller can parse
 * (Sprint 1, slice 4 / S-07).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

/**
 * The behavioural half of slice 4, over real HTTP against a real server.
 *
 * The server lifecycle, the synthesized sessions and the request helpers live
 * in {@see AdminHttpTestCase} — extracted in slice 5, when a second HTTP test
 * class was added, so the three harness defects recorded in L-008 exist in one
 * place rather than two. The reasoning for HTTP-over-in-process and for
 * synthesized-over-real logins is in that class's docblock.
 *
 * This class asserts the SHAPE and the COVERAGE of the central gate. The
 * per-finding escalation proofs (S-01, S-02, S-03, S-05, S-06, S-12) live in
 * {@see NamedEscalationsTest}.
 */
final class AdminGateHttpTest extends AdminHttpTestCase
{
    protected static function serverPort(): int
    {
        return 8099;
    }

    /**
     * An API endpoint refuses an under-privileged role as parseable JSON 403.
     *
     * @return void
     */
    public function testApiEndpointRefusesWithJson403(): void
    {
        $response = $this->request( 'installer/admin/api/plugins.php', 'viewer' );

        self::assertSame( 403, $response['status'], 'A viewer must not reach the plugin API.' );
        self::assertStringContainsString(
            'application/json',
            $response['content_type'],
            'An API refusal must be JSON. An XHR that receives HTML gets a parse error, not a '
            . 'status it can act on — the defect recorded beside S-07.'
        );

        $body = json_decode( $response['body'], true );

        self::assertIsArray( $body, 'The 403 body must be valid JSON. Got: ' . $response['body'] );
        self::assertSame( 'forbidden', $body['code'] ?? null );
        self::assertNotEmpty( $body['error'] ?? '', 'The refusal must carry a human-readable message.' );
    }

    /**
     * An unauthenticated API request gets JSON 401, not a redirect to HTML.
     *
     * Before slice 4 this 302'd to the login PAGE, which also made the
     * isAuthenticated() re-checks inside 20 endpoints unreachable and their
     * advertised 401 contract unobservable.
     *
     * @return void
     */
    public function testUnauthenticatedApiRequestGetsJson401(): void
    {
        $response = $this->request( 'installer/admin/api/plugins.php', null );

        self::assertSame( 401, $response['status'], 'An anonymous API call must be 401, not 302.' );
        self::assertStringContainsString( 'application/json', $response['content_type'] );

        $body = json_decode( $response['body'], true );

        self::assertIsArray( $body );
        self::assertSame( 'authentication_required', $body['code'] ?? null );
    }

    /**
     * An admin PAGE refuses with an HTML 403, not a redirect to the dashboard.
     *
     * Four pages used to redirect to admin/ on denial, which a caller cannot
     * distinguish from "moved".
     *
     * @return void
     */
    public function testAdminPageRefusesWithHtml403(): void
    {
        $response = $this->request( 'installer/admin/users.php', 'viewer' );

        self::assertSame( 403, $response['status'], 'A viewer must not reach user management.' );
        self::assertStringContainsString( 'text/html', $response['content_type'] );
        self::assertStringContainsString(
            'You do not have permission',
            $response['body'],
            'The refusal must be readable and translated, not a bare status.'
        );
    }

    /**
     * An unauthenticated PAGE request still redirects to login.
     *
     * The page half of the shape split: a browser navigating to an admin page
     * should land on the login form, and only the API half changed.
     *
     * @return void
     */
    public function testUnauthenticatedPageRequestRedirectsToLogin(): void
    {
        $response = $this->request( 'installer/admin/users.php', null );

        self::assertSame( 302, $response['status'] );
        self::assertStringContainsString( 'login.php', $response['location'] );
    }

    /**
     * The per-role matrix across representative surfaces of every tier.
     *
     * This is the sprint's "per-role integration tests against representative
     * pages and endpoints". Each expectation is the capability the gate map
     * assigns, asserted end to end rather than by re-reading the map.
     *
     * @return void
     */
    public function testPerRoleAccessMatrixAcrossRepresentativeSurfaces(): void
    {
        // path => [ owner, admin, editor, viewer ]
        $expected = [
            // Owner-only tiers.
            'installer/admin/users.php'    => [ 200, 403, 403, 403 ],
            'installer/admin/plugins.php'  => [ 200, 403, 403, 403 ],
            'installer/admin/updates.php'  => [ 200, 403, 403, 403 ],
            'installer/admin/terminal.php' => [ 200, 403, 403, 403 ],

            // Owner + admin.
            'installer/admin/settings.php' => [ 200, 200, 403, 403 ],
            'installer/admin/mcp.php'      => [ 200, 200, 403, 403 ],
            'installer/admin/theme.php'    => [ 200, 200, 403, 403 ],
            'installer/admin/ai-chat.php'  => [ 200, 200, 403, 403 ],

            // Owner + admin + editor.
            'installer/admin/analytics.php' => [ 200, 200, 200, 403 ],

            // The four files whose redundant redirect-style gates were removed
            // in this slice. Covered explicitly: nothing else proves the
            // central gate enforces what the deleted code enforced, and a
            // redirect-to-dashboard would have looked like success.
            'installer/admin/logs.php'           => [ 200, 200, 403, 403 ],
            'installer/admin/system-options.php' => [ 200, 200, 403, 403 ],
            'installer/admin/translations.php'   => [ 200, 200, 403, 403 ],

            // Self-service page tier — every role reaches its OWN security
            // settings. The privileged branches inside it are re-gated and
            // asserted separately.
            'installer/admin/security.php' => [ 200, 200, 200, 200 ],

            // Editor tier.
            'installer/admin/tasks.php' => [ 200, 200, 200, 403 ],

            // Every role — the self-service and read tiers.
            'installer/admin/profile.php' => [ 200, 200, 200, 200 ],
            'installer/admin/pages.php'   => [ 200, 200, 200, 200 ],

            // API tiers.
            'installer/admin/api/update-install.php' => [ 405, 403, 403, 403 ],
            'installer/admin/api/notices.php'        => [ 200, 200, 200, 200 ],

        ];

        $roles    = [ 'owner', 'admin', 'editor', 'viewer' ];
        $failures = [];

        foreach ( $expected as $path => $statuses ) {
            foreach ( $roles as $i => $role ) {
                $actual = $this->request( $path, $role )['status'];

                if ( $actual !== $statuses[ $i ] ) {
                    $failures[] = sprintf(
                        '%s as %s: expected %d, got %d',
                        $path,
                        $role,
                        $statuses[ $i ],
                        $actual
                    );
                }
            }
        }

        self::assertSame( [], $failures, "Gate mismatches:\n" . implode( "\n", $failures ) );
    }

    /**
     * A page's privileged POST branch refuses a role that may only view it.
     *
     * The gate map entry is a FLOOR, not a ceiling: three pages are mapped at
     * the tier needed to see them and re-gate inline. Without this test the
     * inline calls are an unverified claim — and the page-level 200 in the
     * matrix above would look like coverage while the destructive action
     * underneath it stayed open, which is precisely the shape of S-06.
     *
     * @return void
     */
    public function testPrivilegedPostBranchesRefuseTheViewTierRole(): void
    {
        // path => [ field data, role that may VIEW but must not ACT ]
        $cases = [
            // Dashboard is pages.view (all roles); the indexing toggle decides
            // whether the whole site is indexable — site.configure.
            [ 'installer/admin/index.php', [ 'action' => 'disable_block' ], 'viewer' ],
            [ 'installer/admin/index.php', [ 'action' => 'disable_block' ], 'editor' ],

            // Page list is pages.view; trashing is pages.delete.
            [ 'installer/admin/pages.php', [ 'action' => 'delete', 'slug' => 'home' ], 'viewer' ],
            [ 'installer/admin/pages.php', [ 'action' => 'delete', 'slug' => 'home' ], 'editor' ],

            // Task list is tasks.create; completing anyone's task is tasks.manage.
            [ 'installer/admin/tasks.php', [ 'action' => 'complete', 'task_id' => 'x' ], 'editor' ],
        ];

        foreach ( $cases as [ $path, $fields, $role ] ) {
            // The page itself must remain reachable — otherwise this test would
            // pass for the wrong reason, by the role simply being locked out.
            self::assertSame(
                200,
                $this->request( $path, $role )['status'],
                "{$role} must still be able to VIEW {$path} — the map entry is a floor."
            );

            $response = $this->post( $path, $fields, $role );

            self::assertSame(
                403,
                $response['status'],
                "{$role} must be refused the privileged POST branch of {$path}."
            );
        }
    }

    /**
     * An owner IS allowed through the same privileged POST branches.
     *
     * The positive half. A refusal test that never checks the allow path can
     * pass because the request never arrived authenticated at all — the exact
     * failure L-008 records, where every request was silently anonymous.
     *
     * @return void
     */
    public function testPrivilegedPostBranchesAdmitTheOwner(): void
    {
        $response = $this->post(
            'installer/admin/index.php',
            [ 'action' => 'disable_block' ],
            'owner'
        );

        self::assertSame(
            200,
            $response['status'],
            'The owner must pass the site.configure re-gate on the dashboard toggle.'
        );
    }

    /**
     * The Encryption & Recovery section is not even rendered below its tier.
     *
     * security.php is mapped 'security.self' so every role can manage their own
     * second factor, but the encryption-level, recovery-key and identity-key
     * controls inside it are site-wide and destructive. Their POST branches are
     * re-gated at 'site.configure'; this asserts the UI does not OFFER an action
     * the gate will refuse.
     *
     * Worth having beyond tidiness: this section's visibility was the LAST
     * hand-rolled `in_array( $role, ['owner','admin'] )` in the product, and it
     * survived slice 4's first pass precisely because no test exercised
     * security.php as a non-admin role — the page-level 200 looked like
     * coverage. The `code-reviewer` pass caught it; this test is what stops it
     * coming back.
     *
     * @return void
     */
    public function testEncryptionSectionIsHiddenBelowItsTier(): void
    {
        $marker = 'name="action" value="change_encryption_level"';

        foreach ( [ 'owner', 'admin' ] as $role ) {
            self::assertStringContainsString(
                $marker,
                $this->request( 'installer/admin/security.php', $role )['body'],
                "{$role} holds site.configure and must see the encryption controls."
            );
        }

        foreach ( [ 'editor', 'viewer' ] as $role ) {
            $body = $this->request( 'installer/admin/security.php', $role )['body'];

            self::assertStringNotContainsString(
                $marker,
                $body,
                "{$role} must not be offered the encryption-level control — it is site.configure, "
                . 'and the page is only mapped at the self-service tier.'
            );
            self::assertStringNotContainsString(
                'name="action" value="generate_identity_keys"',
                $body,
                "{$role} must not be offered identity-key regeneration."
            );
        }
    }

    /**
     * A plugin admin page that declares no capability is refused (D-034).
     *
     * Driven end to end through a real, activated plugin rather than by
     * reasoning about the branch: before slice 4 the gate was SKIPPED entirely
     * when a manifest declared no capability, so the page was open to any
     * authenticated user. A fixture that stopped short of activating the plugin
     * would never reach the branch under test.
     *
     * @return void
     */
    public function testPluginPageDeclaringNoCapabilityIsRefused(): void
    {
        $pluginId  = 'zz-gate-fixture';
        $pluginDir = KLYTOS_INSTALLER_PATH . '/plugins/' . $pluginId;

        // A manifest with NO admin_pages entry: $requiredCapability stays null.
        mkdir( $pluginDir . '/admin', 0755, true );
        file_put_contents(
            $pluginDir . '/' . $pluginId . '.php',
            "<?php\n/**\n * Plugin Name: ZZ Gate Fixture\n * Version: 1.0.0\n */\n"
        );
        file_put_contents(
            $pluginDir . '/admin/report.php',
            "<?php\necho 'REACHED THE PLUGIN PAGE';\n"
        );

        $loader = $this->app->getPluginLoader();

        // Activating a plugin rebuilds the frontend asset bundle, and the build
        // engine writes to dirname( rootPath ) — correct in production, the
        // REPOSITORY ROOT in a checkout (audit NEW-04, deferred by D-026). So
        // this test generates repo-root files as a side effect and has to clean
        // them up itself; the playground snapshot covers installer/config and
        // installer/data, not the repo root. Remember whether the directory was
        // already there, so a real one is never deleted.
        $generatedAssets = dirname( KLYTOS_INSTALLER_PATH ) . '/assets';
        $assetsPreExist  = is_dir( $generatedAssets );

        try {
            $loader->activate( $pluginId );

            $response = $this->request(
                'installer/admin/plugin-page.php?plugin=' . $pluginId . '&page=report',
                'owner'
            );

            self::assertSame(
                403,
                $response['status'],
                'A plugin page declaring no capability must be refused, even to the owner — an '
                . 'undeclared capability is not a grant.'
            );
            self::assertStringNotContainsString(
                'REACHED THE PLUGIN PAGE',
                $response['body'],
                'The refusal must happen before the plugin page renders.'
            );
        } finally {
            try {
                $loader->deactivate( $pluginId );
            } catch ( \Throwable $e ) {
                // Deactivation state is restored by the playground snapshot.
            }

            @unlink( $pluginDir . '/admin/report.php' );
            @unlink( $pluginDir . '/' . $pluginId . '.php' );
            @rmdir( $pluginDir . '/admin' );
            @rmdir( $pluginDir );

            if ( ! $assetsPreExist && is_dir( $generatedAssets ) ) {
                self::removeDirectory( $generatedAssets );
            }
        }

        self::assertDirectoryDoesNotExist(
            $generatedAssets,
            'The suite must not leave build output in the repository root (NEW-04).'
        );
    }

    /**
     * Recursively delete a directory the test itself created.
     *
     * Scoped deliberately: it is only ever called with a path this test created
     * and only when the directory did not exist beforehand.
     *
     * @param  string $dir Absolute path.
     * @return void
     */
    private static function removeDirectory( string $dir ): void
    {
        foreach ( scandir( $dir ) ?: [] as $entry ) {
            if ( $entry === '.' || $entry === '..' ) {
                continue;
            }

            $path = $dir . '/' . $entry;

            is_dir( $path ) ? self::removeDirectory( $path ) : @unlink( $path );
        }

        @rmdir( $dir );
    }

    /**
     * A surface with no map entry is refused even to the owner.
     *
     * The default-deny property, proven at the HTTP boundary rather than
     * inferred from the map: a genuinely new admin file is dropped in, and the
     * most privileged role in the product is refused it. This is what makes
     * "a file cannot forget its gate" a fact rather than a claim.
     *
     * @return void
     */
    public function testAnUnmappedAdminFileIsDeniedEvenToTheOwner(): void
    {
        $file = KLYTOS_INSTALLER_PATH . '/admin/zz-unmapped-probe.php';

        file_put_contents(
            $file,
            "<?php\nrequire_once __DIR__ . '/bootstrap.php';\necho 'REACHED THE BODY';\n"
        );

        try {
            $response = $this->request( 'installer/admin/zz-unmapped-probe.php', 'owner' );

            self::assertSame(
                403,
                $response['status'],
                'An unmapped admin file must be denied by default, to every role including owner.'
            );
            self::assertStringNotContainsString(
                'REACHED THE BODY',
                $response['body'],
                'Default-deny must refuse BEFORE the page body executes.'
            );
        } finally {
            @unlink( $file );
        }
    }
}
