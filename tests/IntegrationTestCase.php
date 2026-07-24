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
use Klytos\Core\Hooks;
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

    /**
     * Hook registry as it stood once the App had booted.
     *
     * The unit tier calls Hooks::reset() around every test; this tier CANNOT,
     * because App::boot() runs once per process (see $booted) and registers the
     * core and plugin hooks as it goes — a blanket reset would strip them from
     * every test after the first and leave the application quietly half-wired.
     * So the tier records the post-boot baseline instead and asserts each test
     * hands it back unchanged.
     *
     * Found while writing slice 6, which is the first test in this tier to
     * register a hook at all: a test that filters http.safe.allowed_schemes to
     * permit ftp:// was silently permitting it for every test that ran
     * afterwards. Nothing was passing for the wrong reason yet — but the next
     * security test to register a filter would have been, and it would have
     * looked exactly like a green suite (L-010).
     *
     * @var array{actions: array<string,int>, filters: array<string,int>}
     */
    private array $hookBaseline = [];

    /**
     * Hooks this test registered through the helpers below, for exact removal.
     *
     * @var list<array{kind: string, hook: string, callback: callable}>
     */
    private array $temporaryHooks = [];

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

        // Taken AFTER boot, so the baseline is "the application's own hooks"
        // and not "no hooks at all".
        $this->hookBaseline   = Hooks::getRegisteredHooks();
        $this->temporaryHooks = [];
    }

    protected function tearDown(): void
    {
        $this->actingAsGuest();

        $this->removeTemporaryHooks();

        // The hook check runs FIRST among the assertions, and the ordering is
        // load-bearing rather than arbitrary. A failed assertion throws
        // immediately, so if the config check ran first, a test that mutated
        // config AND leaked a hook would report only the config mutation — and
        // the leak would silently affect every later test in the process while
        // attention went to the louder failure. That is L-010's masking shape
        // one level removed: not a check that cannot fail, but a check that
        // never gets to run.
        //
        // Reading the state before restorePlayground() is safe here for the
        // reason L-010 demands be stated explicitly: this assertion reads the
        // static Hooks registry, which the playground restore does not touch,
        // so no cleanup runs between the observation and the check.
        $this->assertNoHookLeaked();

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
     * Register a filter for the duration of this test only.
     *
     * Use this instead of klytos_add_filter() in the integration tier. The
     * callback is removed by identity in tearDown, so it cannot survive into a
     * later test — which matters most for filters that WEAKEN something, since
     * a leaked one makes a later test pass without the control it is asserting.
     *
     * @param  string   $hook     Hook name.
     * @param  callable $callback Listener.
     * @param  int      $priority Priority.
     * @return void
     */
    protected function addTemporaryFilter( string $hook, callable $callback, int $priority = 10 ): void
    {
        Hooks::addFilter( $hook, $callback, $priority );

        $this->temporaryHooks[] = [ 'kind' => 'filter', 'hook' => $hook, 'callback' => $callback ];
    }

    /**
     * Register an action for the duration of this test only.
     *
     * @param  string   $hook     Hook name.
     * @param  callable $callback Listener.
     * @param  int      $priority Priority.
     * @return void
     */
    protected function addTemporaryAction( string $hook, callable $callback, int $priority = 10 ): void
    {
        Hooks::addAction( $hook, $callback, $priority );

        $this->temporaryHooks[] = [ 'kind' => 'action', 'hook' => $hook, 'callback' => $callback ];
    }

    /**
     * Remove exactly the listeners this test registered.
     *
     * By callback identity rather than removeAllFilters( $hook ): a test that
     * hooks a name the application also uses must not take the application's
     * listeners down with its own on the way out.
     *
     * @return void
     */
    private function removeTemporaryHooks(): void
    {
        foreach ( $this->temporaryHooks as $registered ) {
            if ( $registered['kind'] === 'filter' ) {
                Hooks::removeFilter( $registered['hook'], $registered['callback'] );
            } else {
                Hooks::removeAction( $registered['hook'], $registered['callback'] );
            }
        }

        $this->temporaryHooks = [];
    }

    /**
     * Fail if this test left a hook registered above the post-boot baseline.
     *
     * @return void
     */
    private function assertNoHookLeaked(): void
    {
        $current = Hooks::getRegisteredHooks();

        foreach ( [ 'actions', 'filters' ] as $kind ) {
            foreach ( $current[ $kind ] as $hook => $count ) {
                $baseline = $this->hookBaseline[ $kind ][ $hook ] ?? 0;

                if ( $count > $baseline ) {
                    self::fail( sprintf(
                        'This test left %d extra listener(s) on the %s "%s", which would '
                        . 'leak into every later test in this process. Register throwaway '
                        . 'hooks with addTemporaryFilter()/addTemporaryAction() instead of '
                        . 'klytos_add_filter()/klytos_add_action().',
                        $count - $baseline,
                        rtrim( $kind, 's' ),
                        $hook
                    ) );
                }
            }
        }
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

    /**
     * Skip a test that touches the vendored AI stack when this runtime cannot
     * load it (audit NEW-06 / D-053).
     *
     * WHY THIS EXISTS, and it is not hypothetical. `.github/workflows/ci.yml`
     * runs the FULL suite on PHP **8.2** as well as 8.3, because Klytos declares
     * 8.1+ — but `installer/vendor-ai/` needs 8.3. Every test that reaches
     * App::getChatEngine() therefore fails on the 8.2 leg: the guard refuses (as
     * designed), and an uncaught UnsupportedRuntimeException errors the test.
     * Measured, not assumed: with the floor temporarily raised so this host
     * counts as unsupported, **8 tests across 3 classes** broke.
     *
     * Skipping is the honest answer rather than a workaround. The refusal IS the
     * correct product behaviour on 8.2; a test asserting the AI stack loads is
     * asserting something that must not be true there, so it has nothing to say
     * on that runtime. What must NOT happen is a silent pass — hence a skip with
     * a reason, which CI already promotes to a hard failure if the playground is
     * missing (so a skip storm cannot hide).
     *
     * Uses the same shape as requirePlayground() above rather than a second
     * mechanism: ask the application's own policy, never re-derive it here.
     *
     * @return void
     */
    protected function requireAiRuntime(): void
    {
        $reason = App::aiRuntimeUnsupportedReason( PHP_VERSION_ID );

        if ( $reason !== null ) {
            self::markTestSkipped( sprintf(
                'This runtime cannot load the vendored AI stack (%s): PHP %s is below the '
                . 'required %d. The guard refusing here is CORRECT product behaviour (NEW-06), '
                . 'so a test that asserts the stack loads has nothing to prove on it.',
                $reason,
                PHP_VERSION,
                App::AI_MIN_PHP_VERSION_ID
            ) );
        }
    }
}
