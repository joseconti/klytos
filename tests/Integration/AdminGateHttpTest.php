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

use Klytos\Tests\IntegrationTestCase;

/**
 * The behavioural half of slice 4, over real HTTP against a real server.
 *
 * WHY HTTP AND NOT THE IN-PROCESS TIER: the refusal is delivered by
 * klytos_deny(), which sets a status, sets a Content-Type, writes a body and
 * calls exit. None of that is observable in-process — exit would take the test
 * runner with it — and the status alone is not the property under test. The
 * sprint's criterion is the 403/401 SHAPE: an XHR that receives an HTML login
 * page instead of JSON cannot act on the refusal, which is exactly the defect
 * recorded beside S-07.
 *
 * WHY SESSIONS ARE SYNTHESIZED RATHER THAN LOGGED IN: they have to be.
 * Auth::login() (core/auth.php:99-102) validates ONLY against
 * config['admin_user'] / config['admin_pass_hash'] and never consults
 * UserManager, so admin, editor and viewer CANNOT log in through the form at
 * all — verified live, see NEW-11 in docs/04-adoption-audit.md. That is a
 * defect in authentication, not authorization, and slice 4 does not fix it.
 * These tests therefore write the session state directly, the same shape
 * IntegrationTestCase::actingAs() writes and the same shape Auth::login()
 * would write on success (auth.php:129-136).
 *
 * The server is started by this class rather than assumed to be running: a
 * suite that silently skips its authorization assertions because a developer
 * forgot to start a server is the failure mode slice 1 refused for the
 * playground fixture.
 */
final class AdminGateHttpTest extends IntegrationTestCase
{
    private const HOST = '127.0.0.1';
    private const PORT = 8099;

    /** Written into every synthetic session, so POSTs can carry a valid token. */
    private const CSRF_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /** @var resource|null The php -S process handle. */
    private static $server = null;

    /** @var string Session save path handed to the server. */
    private static string $sessionPath = '';

    /** @var string Repository root. */
    private static string $repoRoot = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$repoRoot    = dirname( KLYTOS_INSTALLER_PATH );
        self::$sessionPath = sys_get_temp_dir() . '/klytos-gate-sessions-' . getmypid();

        if ( ! is_dir( self::$sessionPath ) ) {
            mkdir( self::$sessionPath, 0700, true );
        }

        // php_serialize makes a session file exactly serialize($_SESSION), so
        // a test can write one without reimplementing PHP's default session
        // encoding. A private save_path keeps these synthetic sessions out of
        // the developer's real one.
        // Refuse to run against somebody else's server. setUpBeforeClass waits
        // for the PORT to open, so a server already listening here — a stray
        // from a previous run, a manual debugging session — would satisfy that
        // wait instantly and the whole class would silently test a process with
        // a different session save path. That produces four confusing failures
        // that look like gate defects and are not; it happened once already.
        $squatter = @fsockopen( self::HOST, self::PORT, $errno, $errstr, 0.2 );

        if ( $squatter !== false ) {
            fclose( $squatter );
            self::fail(
                sprintf(
                    'Port %d is already in use, so this suite would test a server it did not '
                    . 'start (and whose sessions it cannot write). Stop it first: '
                    . 'pkill -f "%s:%d"',
                    self::PORT,
                    self::HOST,
                    self::PORT
                )
            );
        }

        // The ARRAY form of proc_open is load-bearing, not a style choice. A
        // command string is run through `sh -c`, so the handle refers to the
        // shell and proc_terminate() kills the shell while `php -S` keeps the
        // port. proc_close() then blocks forever waiting for a grandchild
        // nothing is going to reap — which is exactly how this test first
        // hung, with every assertion already passed. The array form execs php
        // directly, so the handle IS the server.
        $command = [
            'php',
            '-d', 'session.save_path=' . self::$sessionPath,
            '-d', 'session.serialize_handler=php_serialize',
            '-S', self::HOST . ':' . self::PORT,
            '-t', self::$repoRoot,
            self::$repoRoot . '/scripts/dev/router.php',
        ];

        // All three standard descriptors are given explicitly so the server
        // inherits none of the test runner's. An inherited stdout keeps the
        // runner's output pipe open after PHPUnit itself has exited, which
        // looks exactly like a hung test suite and is not one.
        $descriptors = [
            0 => [ 'file', '/dev/null', 'r' ],
            1 => [ 'file', '/dev/null', 'w' ],
            2 => [ 'file', '/dev/null', 'w' ],
        ];

        self::$server = proc_open( $command, $descriptors, $pipes );

        // Wait for the port rather than sleeping a guessed interval.
        for ( $i = 0; $i < 100; $i++ ) {
            $socket = @fsockopen( self::HOST, self::PORT, $errno, $errstr, 0.1 );

            if ( $socket !== false ) {
                fclose( $socket );
                return;
            }

            usleep( 50_000 );
        }

        self::fail( 'The test HTTP server did not come up on port ' . self::PORT );
    }

    public static function tearDownAfterClass(): void
    {
        if ( is_resource( self::$server ) ) {
            // Terminate before closing: proc_close() WAITS for the child, and
            // `php -S` never exits on its own.
            // 15/9 rather than the SIGTERM/SIGKILL constants: those come from
            // ext-pcntl, which is not loaded here and is not a dependency of
            // the product or the harness.
            $pid = proc_get_status( self::$server )['pid'] ?? 0;

            proc_terminate( self::$server, 15 );

            if ( ! self::waitForExit() ) {
                proc_terminate( self::$server, 9 );
                self::waitForExit();
            }

            proc_close( self::$server );
            self::$server = null;

            // Verified, not assumed: an orphaned `php -S` holding the port
            // makes the NEXT run of this class fail to bind, and the failure
            // would look like a defect in the gate rather than in the harness.
            if ( $pid > 0 && self::processExists( $pid ) ) {
                self::fail( 'The test HTTP server (pid ' . $pid . ') outlived the suite.' );
            }
        }

        foreach ( glob( self::$sessionPath . '/*' ) ?: [] as $file ) {
            @unlink( $file );
        }

        @rmdir( self::$sessionPath );

        parent::tearDownAfterClass();
    }

    /**
     * Wait briefly for the server process to exit.
     *
     * @return bool True if it exited within the window.
     */
    private static function waitForExit(): bool
    {
        for ( $i = 0; $i < 100; $i++ ) {
            if ( ! ( proc_get_status( self::$server )['running'] ?? false ) ) {
                return true;
            }

            usleep( 20_000 );
        }

        return false;
    }

    /**
     * Whether a PID is still alive.
     *
     * posix_kill() with signal 0 is the standard existence probe; ext-posix is
     * not guaranteed, so fall back to /proc-less `ps`.
     *
     * @param  int $pid Process ID.
     * @return bool
     */
    private static function processExists( int $pid ): bool
    {
        if ( function_exists( 'posix_kill' ) ) {
            return posix_kill( $pid, 0 );
        }

        exec( 'ps -p ' . escapeshellarg( (string) $pid ) . ' -o pid=', $out, $code );

        return $code === 0 && $out !== [];
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
     * The identity-key export is owner-only, and no longer fatals.
     *
     * Two properties in one request, because this endpoint carried two defects:
     * it decided owner-ness by comparing the session username against
     * config['admin_user'] instead of asking the matrix, and — more
     * immediately — it called Auth::isLoggedIn(), which does not exist, so
     * EVERY request to it died with "Call to undefined method". It was not
     * merely ungated; it was dead for everyone.
     *
     * The owner side asserts "not refused and not crashed" rather than a literal
     * 200 on purpose. The endpoint enforces its own 1-download-per-24-hours
     * limit, so a bare 200 expectation is a function of whether anyone has
     * exported recently — it would pass or fail depending on fixture history
     * rather than on the gate. 500 is the specific value that would mean the
     * fatal is back.
     *
     * @return void
     */
    public function testIdentityExportIsOwnerOnlyAndNoLongerFatals(): void
    {
        $path = 'installer/admin/api/download-identity.php';

        foreach ( [ 'admin', 'editor', 'viewer' ] as $role ) {
            self::assertSame(
                403,
                $this->request( $path, $role )['status'],
                "{$role} must not be able to export the site's private key — users.manage is "
                . 'owner-only.'
            );
        }

        $response = $this->request( $path, 'owner' );

        self::assertNotSame(
            403,
            $response['status'],
            'The owner holds users.manage and must pass the gate.'
        );
        self::assertContains(
            $response['status'],
            [ 200, 429 ],
            "Expected the export or its 24h rate limit, got {$response['status']}."
        );

        // The crash is asserted on the BODY, not the status — and that is the
        // whole point. This endpoint's fatal was verified to return HTTP **200**
        // with the error rendered into the response, because output had already
        // begun. A status-only assertion here could never fail against the
        // broken code, which would have made this test decoration rather than
        // evidence.
        foreach ( [ 'Uncaught Error', 'Call to undefined method', 'Fatal error' ] as $signature ) {
            self::assertStringNotContainsString(
                $signature,
                $response['body'],
                'The endpoint raised a PHP error instead of responding. Auth::isLoggedIn() does '
                . 'not exist — the methods are isAuthenticated() and is2faPending().'
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

    /**
     * Perform an HTTP request, optionally as a seeded role.
     *
     * @param  string      $path Path relative to the document root.
     * @param  string|null $role Seeded username, or null for anonymous.
     * @return array{status:int, body:string, content_type:string, location:string}
     */
    private function request( string $path, ?string $role ): array
    {
        $url    = sprintf( 'http://%s:%d/%s', self::HOST, self::PORT, ltrim( $path, '/' ) );
        $handle = curl_init( $url );

        curl_setopt_array( $handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
        ] );

        if ( $role !== null ) {
            // Auth::startSession() renames the session (core/auth.php:61), so
            // the default PHPSESSID cookie is simply ignored and every request
            // would silently arrive anonymous — which would make the whole
            // suite pass for the wrong reason on the 401/302 assertions.
            curl_setopt( $handle, CURLOPT_COOKIE, 'klytos_session=' . $this->sessionFor( $role ) );
        }

        $raw = curl_exec( $handle );

        if ( $raw === false ) {
            self::fail( 'Request to ' . $url . ' failed: ' . curl_error( $handle ) );
        }

        $status     = (int) curl_getinfo( $handle, CURLINFO_HTTP_CODE );
        $headerSize = (int) curl_getinfo( $handle, CURLINFO_HEADER_SIZE );
        $headers    = substr( (string) $raw, 0, $headerSize );
        $body       = substr( (string) $raw, $headerSize );

        curl_close( $handle );

        return [
            'status'       => $status,
            'body'         => $body,
            'content_type' => $this->header( $headers, 'content-type' ),
            'location'     => $this->header( $headers, 'location' ),
        ];
    }

    /**
     * POST form fields to a path as a seeded role, carrying a valid CSRF token.
     *
     * The token has to be genuine or every one of these assertions would pass
     * for the wrong reason: the pages under test run their privileged branch
     * only inside `if ( POST && klytos_verify_csrf() )`, so a bad token means
     * the branch never executes and the response is an innocent 200 — a
     * refusal test that never reached the thing it was refusing. The token is
     * the one written into the synthetic session, which is what
     * Helpers::verifyCsrf() validates against (`helpers.php:1027-1035`).
     *
     * @param  string               $path   Path relative to the document root.
     * @param  array<string,string> $fields Form fields.
     * @param  string               $role   Seeded username.
     * @return array{status:int, body:string, content_type:string, location:string}
     */
    private function post( string $path, array $fields, string $role ): array
    {
        $sessionId = $this->sessionFor( $role );
        $url       = sprintf( 'http://%s:%d/%s', self::HOST, self::PORT, ltrim( $path, '/' ) );
        $handle    = curl_init( $url );

        $fields['csrf'] = self::CSRF_TOKEN;

        curl_setopt_array( $handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query( $fields ),
            CURLOPT_COOKIE         => 'klytos_session=' . $sessionId,
        ] );

        $raw = curl_exec( $handle );

        if ( $raw === false ) {
            self::fail( 'POST to ' . $url . ' failed: ' . curl_error( $handle ) );
        }

        $status     = (int) curl_getinfo( $handle, CURLINFO_HTTP_CODE );
        $headerSize = (int) curl_getinfo( $handle, CURLINFO_HEADER_SIZE );
        $headers    = substr( (string) $raw, 0, $headerSize );
        $body       = substr( (string) $raw, $headerSize );

        curl_close( $handle );

        return [
            'status'       => $status,
            'body'         => $body,
            'content_type' => $this->header( $headers, 'content-type' ),
            'location'     => $this->header( $headers, 'location' ),
        ];
    }

    /**
     * Write a session file for a seeded role and return its session ID.
     *
     * Mirrors Auth::login()'s successful-session shape (core/auth.php:129-136),
     * for the reason given in the class docblock: non-owner roles cannot reach
     * that code path at all (NEW-11).
     *
     * @param  string $role Seeded username.
     * @return string Session ID.
     */
    private function sessionFor( string $role ): string
    {
        $user = $this->users->getByUsername( $role );

        if ( $user === null ) {
            self::fail(
                "Playground user '{$role}' does not exist. "
                . 'Reseed with: php scripts/dev/seed-playground.php --reset'
            );
        }

        // Deterministic per role, so repeated requests in one test reuse one
        // session instead of littering the save path.
        $sessionId = substr( hash( 'sha256', 'klytos-gate-test-' . $role ), 0, 32 );

        $payload = [
            'klytos_auth'        => true,
            'klytos_user'        => $user['username'],
            'klytos_user_id'     => $user['id'],
            'klytos_login_time'  => time(),
            'klytos_last_active' => time(),
            'klytos_csrf'        => self::CSRF_TOKEN,
        ];

        file_put_contents( self::$sessionPath . '/sess_' . $sessionId, serialize( $payload ) );

        return $sessionId;
    }

    /**
     * Read one header value out of a raw header block.
     *
     * @param  string $headers Raw headers.
     * @param  string $name    Header name, lowercase.
     * @return string Value, or '' when absent.
     */
    private function header( string $headers, string $name ): string
    {
        foreach ( explode( "\n", $headers ) as $line ) {
            $line = trim( $line );

            if ( stripos( $line, $name . ':' ) === 0 ) {
                return trim( substr( $line, strlen( $name ) + 1 ) );
            }
        }

        return '';
    }
}
