<?php

/**
 * Klytos CMS — base case for admin tests that must speak real HTTP
 * (Sprint 1, slice 5; extracted from slice 4's AdminGateHttpTest).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests;

/**
 * A booted `php -S` server plus the request helpers authorization tests need.
 *
 * WHY HTTP AND NOT THE IN-PROCESS TIER: a refusal is delivered by
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
 * defect in authentication, not authorization, and Sprint 1 does not fix it.
 * These tests therefore write the session state directly, the same shape
 * IntegrationTestCase::actingAs() writes and the same shape Auth::login()
 * would write on success (auth.php:129-136).
 *
 * WHY THIS CLASS EXISTS RATHER THAN A SECOND COPY OF THE HARNESS: slice 5 adds
 * a second HTTP test class, and duplicating ~200 lines of server lifecycle
 * would fork the three defects L-008 records — the session cookie name, the
 * proc_open handle shape and the teardown orphan check — so that a later fix
 * to one copy would silently miss the other. Duplication is a defect here, not
 * a shortcut (Keel's reuse rule).
 *
 * Each subclass gets its OWN port and session save path. Sharing one port
 * across classes would make the squatter check below fire on whichever class
 * PHPUnit happened to run second if the first one's teardown were ever slow.
 */
abstract class AdminHttpTestCase extends IntegrationTestCase
{
    protected const HOST = '127.0.0.1';

    /** Written into every synthetic session, so POSTs can carry a valid token. */
    protected const CSRF_TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /** @var resource|null The php -S process handle. */
    private static $server = null;

    /** @var string Session save path handed to the server. */
    private static string $sessionPath = '';

    /** @var string Repository root. */
    protected static string $repoRoot = '';

    /**
     * The port this class's server listens on.
     *
     * Overridden per subclass so two HTTP test classes in one run can never
     * collide. Returning the same value from two subclasses is a defect.
     *
     * @return int
     */
    abstract protected static function serverPort(): int;

    /**
     * The router script this class's server runs.
     *
     * Defaults to the playground router, which is what every admin test wants.
     * Slice 6 needed a server that answers redirect shapes instead, and the
     * choice was to generalize here rather than write a second harness: a copy
     * would fork the three defects L-008 records — the session cookie name, the
     * proc_open handle shape, and the teardown orphan check — so that fixing
     * one copy would silently leave the other broken. Overriding one path is
     * the whole difference between the two servers.
     *
     * @return string Absolute path to the router script.
     */
    protected static function routerScript(): string
    {
        return dirname( KLYTOS_INSTALLER_PATH ) . '/scripts/dev/router.php';
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $port              = static::serverPort();
        self::$repoRoot    = dirname( KLYTOS_INSTALLER_PATH );
        self::$sessionPath = sprintf(
            '%s/klytos-gate-sessions-%d-%d',
            sys_get_temp_dir(),
            getmypid(),
            $port
        );

        if ( ! is_dir( self::$sessionPath ) ) {
            mkdir( self::$sessionPath, 0700, true );
        }

        // Refuse to run against somebody else's server. The wait loop below
        // would be satisfied instantly by a stray from a previous run or a
        // manual debugging session, and the whole class would silently test a
        // process with a different session save path. That produces confusing
        // failures that look like gate defects and are not; it happened once.
        $squatter = @fsockopen( self::HOST, $port, $errno, $errstr, 0.2 );

        if ( $squatter !== false ) {
            fclose( $squatter );
            self::fail(
                sprintf(
                    'Port %d is already in use, so this suite would test a server it did not '
                    . 'start (and whose sessions it cannot write). Stop it first: '
                    . 'pkill -f "%s:%d"',
                    $port,
                    self::HOST,
                    $port
                )
            );
        }

        // php_serialize makes a session file exactly serialize($_SESSION), so a
        // test can write one without reimplementing PHP's default session
        // encoding. A private save_path keeps these synthetic sessions out of
        // the developer's real one.
        //
        // The ARRAY form of proc_open is load-bearing, not a style choice. A
        // command string is run through `sh -c`, so the handle refers to the
        // shell and proc_terminate() kills the shell while `php -S` keeps the
        // port. proc_close() then blocks forever waiting for a grandchild
        // nothing is going to reap — which is exactly how this test first hung,
        // with every assertion already passed. The array form execs php
        // directly, so the handle IS the server.
        $command = [
            'php',
            '-d', 'session.save_path=' . self::$sessionPath,
            '-d', 'session.serialize_handler=php_serialize',
            '-S', self::HOST . ':' . $port,
            '-t', self::$repoRoot,
            static::routerScript(),
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
            $socket = @fsockopen( self::HOST, $port, $errno, $errstr, 0.1 );

            if ( $socket !== false ) {
                fclose( $socket );
                return;
            }

            usleep( 50_000 );
        }

        self::fail( 'The test HTTP server did not come up on port ' . $port );
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
     * not guaranteed, so fall back to `ps`.
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
     * Perform a GET request, optionally as a seeded role.
     *
     * @param  string      $path Path relative to the document root.
     * @param  string|null $role Seeded username, or null for anonymous.
     * @return array{status:int, body:string, content_type:string, location:string}
     */
    protected function request( string $path, ?string $role ): array
    {
        $handle = $this->handleFor( $path );

        if ( $role !== null ) {
            // Auth::startSession() renames the session (core/auth.php:61), so
            // the default PHPSESSID cookie is simply ignored and every request
            // would silently arrive anonymous — which would make the whole
            // suite pass for the wrong reason on the 401/302 assertions.
            curl_setopt( $handle, CURLOPT_COOKIE, 'klytos_session=' . $this->sessionFor( $role ) );
        }

        return $this->send( $handle, $path );
    }

    /**
     * POST form fields to a path as a seeded role, carrying a valid CSRF token.
     *
     * The token has to be genuine or these assertions would pass for the wrong
     * reason: the surfaces under test run their privileged branch only inside
     * `if ( POST && klytos_verify_csrf() )`, so a bad token means the branch
     * never executes and the response is an innocent 200 — a refusal test that
     * never reached the thing it was refusing. The token is the one written
     * into the synthetic session, which is what Helpers::verifyCsrf()
     * validates against (`helpers.php:1027-1035`).
     *
     * A null $role posts ANONYMOUSLY: no session cookie and no CSRF field.
     * Slice 7 needed that for the public comment endpoint, which by definition
     * has neither — and generalizing this method was preferable to adding a
     * second one, for the reason in the class docblock: a copy would fork the
     * three defects L-008 records. A caller passing a role is unaffected.
     *
     * @param  string               $path    Path relative to the document root.
     * @param  array<string,string> $fields  Form fields.
     * @param  string|null          $role    Seeded username, or null for anonymous.
     * @param  array<int,string>    $headers Extra request headers.
     * @return array{status:int, body:string, content_type:string, location:string}
     */
    protected function post( string $path, array $fields, ?string $role, array $headers = [] ): array
    {
        $handle = $this->handleFor( $path );

        if ( $role !== null ) {
            $fields['csrf'] = self::CSRF_TOKEN;

            curl_setopt( $handle, CURLOPT_COOKIE, 'klytos_session=' . $this->sessionFor( $role ) );
        }

        curl_setopt_array( $handle, [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query( $fields ),
        ] );

        if ( $headers !== [] ) {
            curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );
        }

        return $this->send( $handle, $path );
    }

    /**
     * POST a JSON body as a seeded role, carrying a valid CSRF token.
     *
     * Several admin API endpoints read `php://input` and json_decode it rather
     * than $_POST (api/tasks.php:54, api/post-lock.php), so a form-encoded POST
     * reaches them with an empty action and takes the "unknown action" branch —
     * which returns 400 and would make a refusal test pass without ever
     * reaching the code under test.
     *
     * @param  string              $path   Path relative to the document root.
     * @param  array<string,mixed> $body   Decoded JSON body.
     * @param  string              $role   Seeded username.
     * @return array{status:int, body:string, content_type:string, location:string}
     */
    protected function postJson( string $path, array $body, string $role ): array
    {
        $handle = $this->handleFor( $path );

        // Both channels carry the token: klytos_verify_csrf() reads the request
        // superglobals and the header, not the JSON body.
        $body['csrf'] = self::CSRF_TOKEN;

        curl_setopt_array( $handle, [
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => json_encode( $body ),
            CURLOPT_COOKIE     => 'klytos_session=' . $this->sessionFor( $role ),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-CSRF-Token: ' . self::CSRF_TOKEN,
            ],
        ] );

        return $this->send( $handle, $path );
    }

    /**
     * A curl handle with the options every request in this harness shares.
     *
     * @param  string $path Path relative to the document root.
     * @return \CurlHandle
     */
    private function handleFor( string $path )
    {
        $url    = sprintf( 'http://%s:%d/%s', self::HOST, static::serverPort(), ltrim( $path, '/' ) );
        $handle = curl_init( $url );

        curl_setopt_array( $handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
        ] );

        return $handle;
    }

    /**
     * Execute a prepared handle and split the response.
     *
     * @param  \CurlHandle $handle Prepared handle.
     * @param  string      $path   Path, for the failure message only.
     * @return array{status:int, body:string, content_type:string, location:string}
     */
    private function send( $handle, string $path ): array
    {
        $raw = curl_exec( $handle );

        if ( $raw === false ) {
            self::fail( 'Request to ' . $path . ' failed: ' . curl_error( $handle ) );
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
    protected function sessionFor( string $role ): string
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
