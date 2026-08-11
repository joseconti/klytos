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
     * The AI chat's PANELS require their own tier, not merely `ai.use`.
     *
     * Found by the sprint-close `security-auditor` pass on D-051 (audit
     * NEW-31), and it is the S-07 shape one door later: `ai-chat.php` is gated
     * at `ai.use`, and it `require_once`s `partials/ai-panel-{$panel}.php` for
     * a caller-supplied `?panel=`. Those partials had **zero** permission
     * checks — `ai-panel-users.php` creates users and changes ANY account's
     * password behind a CSRF token alone, work `users.php` reserves to
     * `users.manage` (owner-only), and `ai-panel-settings.php` writes site and
     * SMTP settings that `settings.php` reserves to `site.configure`.
     *
     * It was already wrong for `admin` before this sprint; D-051's widening of
     * `ai.use` to `editor` is what would have made it a two-tier jump. Asserted
     * on the wire per role, in BOTH directions, because a check that refused
     * everyone would pass a refusal-only test (L-008).
     *
     * **UPDATED BY D-108 (Phase 4, stage 6, entry 12), and the claim is now
     * proven one layer STRONGER rather than relaxed.** The panels no longer
     * exist: `?panel=` renders nothing at all and 302s to the real screen,
     * which gates itself through the same central map. So the assertion is no
     * longer "the inline capability check refuses" — it is "no privileged
     * markup is served from this door in the first place, and the destination
     * still refuses the role that must not have it." The test was NOT loosened
     * to accept the redirect: it follows the redirect and asserts the refusal
     * where it now lives, plus the fact that the panel body is gone.
     *
     * @return void
     */
    public function testAiChatPanelsRequireTheirOwnTierNotJustAiUse(): void
    {
        // The chat itself: ai.use, held by editor since D-051.
        self::assertSame(
            200,
            $this->request( 'installer/admin/ai-chat.php', 'editor' )['status'],
            'An editor must reach the AI chat — that is what D-051 widened.'
        );

        $targets = [
            'users'     => [ 'users.manage', 'installer/admin/users.php' ],
            'settings'  => [ 'site.configure', 'installer/admin/settings.php' ],
            'dashboard' => [ 'pages.view', 'installer/admin/index.php' ],
            'profile'   => [ 'profile.edit', 'installer/admin/profile.php' ],
        ];

        foreach ( $targets as $panel => [ $capability, $destination ] ) {
            $response = $this->request( 'installer/admin/ai-chat.php?panel=' . $panel, 'editor' );

            // Nothing privileged is SERVED here any more, at any role: the
            // door answers with a redirect and an empty body.
            self::assertSame(
                302,
                $response['status'],
                "?panel={$panel} must no longer render a panel — it redirects to the real screen."
            );

            self::assertStringContainsString(
                basename( $destination ),
                $response['location'] ?? '',
                "?panel={$panel} redirected somewhere other than {$destination}."
            );
        }

        // And the destination still refuses the role that must not hold it —
        // which is the claim this test has always been about. Following the
        // redirect is what proves the escalation is closed, not merely moved.
        foreach ( [ 'users' => 'installer/admin/users.php', 'settings' => 'installer/admin/settings.php' ] as $capabilityPanel => $destination ) {
            self::assertSame(
                403,
                $this->request( $destination, 'editor' )['status'],
                "An editor reached {$destination} through the chat door (?panel={$capabilityPanel})."
            );

            self::assertSame(
                200,
                $this->request( $destination, 'owner' )['status'],
                "The owner must still reach {$destination}."
            );
        }

        // The unprivileged destinations stay reachable: the change must not
        // turn the whole feature off for the role it was opened to.
        foreach ( [ 'installer/admin/index.php', 'installer/admin/profile.php' ] as $destination ) {
            self::assertSame(
                200,
                $this->request( $destination, 'editor' )['status'],
                "{$destination} sits at a tier an editor holds and must remain reachable."
            );
        }
    }

    /**
     * `api/ai-chat.php?action=get_providers` does not hand partial API keys to
     * a role below `site.configure` (audit NEW-31).
     *
     * `AiKeyManager::listProviders()` includes `masked_key` — the first 6 and
     * last 4 characters of each configured provider key. Every other
     * key-management action in that file checks `site.configure`; this one did
     * not. The omission was invisible while `ai.use` and `site.configure` were
     * the same owner+admin set, and became a real disclosure the moment D-051
     * split them.
     *
     * @return void
     */
    public function testAiChatApiDoesNotDiscloseProviderKeysBelowSiteConfigure(): void
    {
        $editor = $this->request( 'installer/admin/api/ai-chat.php?action=get_providers', 'editor' );

        self::assertSame( 403, $editor['status'], 'An editor must not enumerate provider keys.' );
        self::assertStringNotContainsString(
            'masked_key',
            $editor['body'],
            'The refusal must not carry the payload it is refusing.'
        );

        $owner = $this->request( 'installer/admin/api/ai-chat.php?action=get_providers', 'owner' );

        self::assertSame( 200, $owner['status'], 'The owner holds site.configure and must still be served.' );
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

            // Owner + admin + editor.
            'installer/admin/analytics.php' => [ 200, 200, 200, 403 ],

            // ai-chat.php moved up a tier in Sprint 2 slice 4 (D-051): an
            // editor now reaches the AI chat, because the MCP tool layer the
            // chat drives gates every call with the caller's own role. Asserted
            // on the wire rather than in the matrix — the widening is only real
            // if the editor's request actually returns 200.
            'installer/admin/ai-chat.php'  => [ 200, 200, 200, 403 ],

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
            /*
             * The Dashboard's two rows USED to be here, and they are gone
             * because the branch they guarded is gone, not because they became
             * inconvenient.
             *
             * DR-002 moved the indexing toggle to Settings → Advanced (built
             * with manifest entry 9), so `index.php` no longer has a privileged
             * POST branch at all — it has no POST handler of any kind. A case
             * asserting 403 on a branch that does not exist would be asserting
             * the gate map's own page tier, which the matrix above already
             * covers, and it would go green for a reason unrelated to its name.
             *
             * The coverage did not evaporate with the rows: it moved to
             * `testIndexingIsRefusedToEveryRoleBelowSiteConfigure()` below,
             * which pins BOTH halves of the move — the control is gated where
             * it landed, and the Dashboard did not keep a copy.
             */

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
     * DR-002's move is gated where it landed, and left no copy behind.
     *
     * Search-engine and AI-crawler indexing decides whether the whole site is
     * findable. It used to be a bare button on the Dashboard — a screen every
     * role can see — which is why `index.php` needed an inline `site.configure`
     * re-check on top of its `pages.view` page tier. Manifest entry 9 moved it
     * to Settings → Advanced, where `site.configure` is the PAGE tier and the
     * inline re-check is no longer the only thing standing between an editor
     * and the setting.
     *
     * A move is two claims and this asserts both, because verifying only the
     * first is how a control ends up existing in two places: the setting is
     * refused below `site.configure` at its new home, AND the old home no
     * longer accepts the post that used to change it. Without the second half,
     * reinstating the Dashboard toggle would break nothing.
     *
     * @return void
     */
    public function testIndexingIsRefusedToEveryRoleBelowSiteConfigure(): void
    {
        foreach ( [ 'viewer', 'editor' ] as $role ) {
            $response = $this->post(
                'installer/admin/settings.php',
                [ 'section' => 'advanced', 'indexing_enabled' => '1' ],
                $role
            );

            self::assertSame(
                403,
                $response['status'],
                "{$role} must be refused the indexing control at its new home."
            );
        }

        /*
         * The old home. An owner is used deliberately — the point is not that
         * the ROLE is refused (it is not; an owner may still view the
         * Dashboard) but that the BRANCH is gone, so the post is simply
         * ignored and the value does not move. A 403 here would mean the
         * toggle is still wired and merely gated; a 200 with the value
         * unchanged is what "the control moved" actually looks like.
         */
        $before = $this->request( 'installer/admin/index.php', 'owner' )['status'];
        self::assertSame( 200, $before, 'An owner must still be able to view the Dashboard.' );

        $response = $this->post(
            'installer/admin/index.php',
            [ 'action' => 'disable_block' ],
            'owner'
        );

        self::assertSame(
            200,
            $response['status'],
            'The Dashboard has no POST branch to refuse or accept — it must simply render.'
        );

        self::assertStringNotContainsString(
            'name="action" value="disable_block"',
            $response['body'],
            'The Dashboard must not carry the indexing toggle the manifest moved to Settings.'
        );
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
        $response = $this->requestUnmappedProbe( 'zz-unmapped-probe.php', "echo 'REACHED THE BODY';" );

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
    }

    /**
     * A default-deny refusal REACHES THE LOG FILE (audit NEW-44, D-059).
     *
     * The gate has always called klytos_log_warning() on both of its refusals,
     * and reading that call said the feature worked. It did not:
     * `admin-gate.php` passed `'security'` as the $source, and
     * `Logger::write():122` treats any source other than `'core'` as a PLUGIN
     * ID and drops the entry unless a plugin of that ID has logging enabled.
     * No plugin is called `security`, so every refusal by the central S-07 gate
     * wrote NOTHING — Developer Mode on or off. An authorization system that
     * refuses silently cannot be operated.
     *
     * So this test reads the FILE. Asserting on the call, or on a spy, would
     * reproduce exactly the mistake being fixed — and this is L-019's shape for
     * the third time in this project, the two previous ones having been caught
     * in documents rather than in the product.
     *
     * Both directions, because one is half a test (L-010): with Developer Mode
     * ON the entry appears, with it OFF nothing is written. The OFF half is
     * what proves the bytes read in the ON half actually came from this
     * refusal, rather than from some unconditional writer running in the same
     * request.
     *
     * @return void
     */
    public function testAGateRefusalReachesTheLogFile(): void
    {
        $siteConfig = $this->app->getSiteConfig();

        // Developer Mode is Logger::write()'s FIRST condition, so a refusal
        // logged under a valid source still writes nothing without it. This
        // writes data/config/site.json.enc, which the playground snapshot
        // covers and D-039's guard does not (that one watches core config).
        $siteConfig->setValue( 'developer.developer_mode', true );

        $before   = $this->logSizes();
        $response = $this->requestUnmappedProbe( 'zz-gate-log-probe.php' );
        $written  = $this->logBytesSince( $before );

        self::assertSame( 403, $response['status'], 'The probe was not refused, so nothing was logged.' );

        self::assertNotSame(
            '',
            $written,
            'The gate refused and wrote nothing to any log file under ' . $this->logsRoot()
            . '. That is audit NEW-44: the refusal is discarded by Logger::write() because the '
            . '$source is not "core".'
        );
        self::assertStringContainsString(
            'no entry in the gate map',
            $written,
            'Something was logged, but not this refusal.'
        );
        self::assertStringContainsString(
            'zz-gate-log-probe.php',
            $written,
            'The entry does not name the surface that was refused, so an operator cannot act on it.'
        );
        self::assertStringContainsString(
            '[core]',
            $written,
            'The entry must be written under the core source. Any other value is dropped by '
            . 'Logger::write() unless a PLUGIN of that ID has logging enabled.'
        );
        self::assertStringContainsString(
            '"category":"security"',
            $written,
            'The security category moved into the context when the source became "core"; without '
            . 'it an operator cannot filter authorization refusals out of the log.'
        );

        // Negative control.
        $siteConfig->setValue( 'developer.developer_mode', false );

        $beforeOff  = $this->logSizes();
        $refusedOff = $this->requestUnmappedProbe( 'zz-gate-log-probe-off.php' );

        self::assertSame( 403, $refusedOff['status'], 'The refusal itself must not depend on logging.' );
        self::assertSame(
            '',
            $this->logBytesSince( $beforeOff ),
            'Something wrote to the log with Developer Mode OFF, so the assertions above cannot be '
            . 'attributed to the gate.'
        );
    }

    /**
     * Drop an unmapped admin file in, request it as the owner, remove it.
     *
     * A genuinely new admin file is the only honest way to assert default-deny
     * at the HTTP boundary: every real surface is mapped, so mapping one out
     * would be testing the map rather than the default.
     *
     * @param  string $name Filename to create under admin/.
     * @param  string $body Extra PHP for the probe's body.
     * @return array{status:int, body:string, content_type:string, location:string, headers:string}
     */
    private function requestUnmappedProbe( string $name, string $body = '' ): array
    {
        $file = KLYTOS_INSTALLER_PATH . '/admin/' . $name;

        file_put_contents(
            $file,
            "<?php\nrequire_once __DIR__ . '/bootstrap.php';\n" . ( $body === '' ? '' : $body . "\n" )
        );

        try {
            return $this->request( 'installer/admin/' . $name, 'owner' );
        } finally {
            // Left behind, this file would fail AdminGateMapTest's coverage
            // check on every later run and look like a gate defect.
            @unlink( $file );
        }
    }

    /**
     * The directory the per-install log directories live under.
     *
     * @return string
     */
    private function logsRoot(): string
    {
        return $this->storage->getDataDir();
    }

    /**
     * Current size of every log file under data/, keyed by absolute path.
     *
     * NEITHER the directory NOR the file name is predicted, and both halves of
     * that are load-bearing rather than defensive:
     *
     *  - Logger::resolveLogFile() rotates by date AND by size, so a 5 MB
     *    debug-<date>.log sends the next entry to debug-<date>-2.log.
     *  - The logs directory has a random name persisted in encrypted config,
     *    and the Logger CACHES it per instance. This tier boots the App once
     *    per process (D-030), so asking the test process's own Logger returns
     *    whichever directory it resolved first, while the server resolves one
     *    per request from the file the playground restore has just put back.
     *    Measured, not reasoned about: this test passed alone and FAILED in the
     *    full suite for exactly that reason, reading an empty string from a
     *    directory the server had not written to.
     *
     * So the measurement scans every log file the install has, which is also
     * the honest form of the property under test — the refusal must reach a log
     * an operator can read, not one particular path.
     *
     * @return array<string, int> Absolute path => size in bytes.
     */
    private function logSizes(): array
    {
        $sizes = [];

        foreach ( glob( $this->logsRoot() . '/logs-*/debug-*.log' ) ?: [] as $file ) {
            $sizes[ $file ] = (int) filesize( $file );
        }

        return $sizes;
    }

    /**
     * Everything appended to any log file since the given sizes.
     *
     * A file that did not exist when the sizes were taken counts from byte 0,
     * so a request that creates a brand-new log directory is still observed.
     *
     * @param  array<string, int> $before Result of logSizes() taken earlier.
     * @return string             The appended bytes, concatenated.
     */
    private function logBytesSince( array $before ): string
    {
        $appended = '';

        foreach ( glob( $this->logsRoot() . '/logs-*/debug-*.log' ) ?: [] as $file ) {
            $offset  = $before[ $file ] ?? 0;
            $current = (string) file_get_contents( $file );

            if ( strlen( $current ) > $offset ) {
                $appended .= substr( $current, $offset );
            }
        }

        return $appended;
    }
}
