<?php

/**
 * Klytos CMS — base case for the integration tier (Sprint 1, slice 1 / T-01).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests;

use Klytos\Core\App;
use Klytos\Core\Auth;
use Klytos\Core\Helpers;
use Klytos\Core\StorageInterface;
use Klytos\Core\UserManager;
use PHPUnit\Framework\TestCase;

/**
 * Integration tier — the real App, booted against the seeded playground.
 *
 * WHY THIS TIER EXISTS: authorization is not unit-testable in this codebase.
 * The permission decision runs through App's singleton, Auth's session state
 * and UserManager's stored roles at once, so a refusal can only be asserted
 * against a booted application with a real user session. That is the seam
 * Sprint 1 slices 3, 4 and 5 assert through — this class is built for them,
 * not for its own harness test.
 *
 * The playground is the fixture (T-02, slice 0): one user per role, already
 * seeded. When it is absent, every test here SKIPS with the command to create
 * it — a silent pass would mean the authorization suite reports green on a
 * machine where it never ran.
 *
 * App is a singleton with a private constructor and no reset, so it is booted
 * once per process and shared; per-test isolation is the session plus a
 * snapshot/restore of the playground's on-disk state (see {@see PlaygroundState},
 * D-030), not a fresh App.
 */
abstract class IntegrationTestCase extends TestCase
{
    use PlaygroundState;

    /** @var bool Whether App::boot() has already run in this process. */
    private static bool $booted = false;

    /**
     * Whether to snapshot and roll back the playground around each test.
     *
     * Defaults to ON for every test in the tier rather than being opted into by
     * the tests that mutate state, because which tests mutate is exactly what
     * cannot be known in advance — a helper three calls deep that writes an
     * audit-log record or a rate-limit counter is still a mutation. A test may
     * set this to false only with a recorded reason.
     *
     * @var bool
     */
    protected bool $isolatePlaygroundState = true;

    /** @var App The booted application. */
    protected App $app;

    /** @var StorageInterface The application's configured storage backend. */
    protected StorageInterface $storage;

    /** @var UserManager Users as the application sees them. */
    protected UserManager $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requirePlayground();

        // Snapshot BEFORE boot, so the very first test in a process captures a
        // playground that boot() has not yet written to (App's Step 10b
        // migration and the action scheduler both write on boot).
        if ( $this->isolatePlaygroundState ) {
            $this->snapshotPlayground();
        }

        if ( ! self::$booted ) {
            App::getInstance()->boot();
            self::$booted = true;
        }

        $this->app     = App::getInstance();
        $this->storage = $this->app->getStorage();
        $this->users   = new UserManager( $this->storage );

        $this->actingAsGuest();
    }

    protected function tearDown(): void
    {
        $this->actingAsGuest();

        if ( $this->isolatePlaygroundState ) {
            // Restore first, then assert: the check reports what the test did,
            // but the playground is left correct either way — including when
            // the assertion fails.
            $this->restorePlayground();
            $this->assertConfigNotMutated();
        }

        parent::tearDown();
    }

    /**
     * Authenticate the current request as a seeded playground user.
     *
     * The session keys written here mirror Auth::login() (core/auth.php:129-136)
     * rather than calling it, because login() is a full authentication flow —
     * rate limiting, 2FA branch, audit log, session_start() — and an
     * authorization test needs the resulting STATE, not the path to it.
     *
     * The mirroring is the risk this introduces, so it is guarded rather than
     * trusted: HarnessTest asserts that a session built here is accepted by
     * Auth::isAuthenticated() and resolves to the expected user. If Auth's
     * session shape ever changes, that test fails loudly — instead of every
     * authorization test quietly passing against an anonymous session, which
     * is the failure mode that would make this whole tier worthless.
     *
     * @param  string $username One of the seeded roles: owner, admin, editor, viewer.
     * @return array            The user record now acting.
     */
    protected function actingAs( string $username ): array
    {
        $user = $this->users->getByUsername( $username );

        if ( $user === null ) {
            self::fail(
                "Playground user '{$username}' does not exist. "
                . 'Reseed with: php scripts/dev/seed-playground.php --reset'
            );
        }

        $_SESSION = [
            'klytos_auth'        => true,
            'klytos_user'        => $user['username'],
            'klytos_user_id'     => $user['id'],
            'klytos_login_time'  => time(),
            'klytos_last_active' => time(),
            'klytos_csrf'        => Helpers::randomHex( 32 ),
        ];

        return $user;
    }

    /**
     * Drop all session state — an anonymous, unauthenticated request.
     *
     * This is the state every authorization test must also assert against.
     * NEW-01 recorded that klytos_current_user() used to PROMOTE a session
     * without klytos_user_id to owner; slice 3 closed that, so the case is now
     * a regression guard rather than a live bug — see
     * tests/Integration/CurrentUserFailClosedTest.php, which asserts the
     * denial directly.
     *
     * @return void
     */
    protected function actingAsGuest(): void
    {
        $_SESSION = [];
    }

    /** Get the application's Auth service. */
    protected function auth(): Auth
    {
        return $this->app->getAuth();
    }

    /**
     * Skip the test, with instructions, when the playground is not seeded.
     *
     * Asks the application itself rather than re-checking its two files here:
     * App::isInstalled() reads only $configPath, which the constructor sets, so
     * it is safe to call before boot(). Reimplementing the check would be a
     * second definition of "installed", free to drift from the first — the
     * defect L-004 records.
     *
     * @return void
     */
    private function requirePlayground(): void
    {
        if ( ! App::getInstance()->isInstalled() ) {
            self::markTestSkipped(
                'The playground is not seeded — the integration tier has no fixture. '
                . 'Create it with: php scripts/dev/seed-playground.php'
            );
        }
    }
}
