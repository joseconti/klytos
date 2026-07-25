<?php

/**
 * Klytos CMS — the login gate consults the user record (Sprint 5, slice 1 /
 * audit NEW-11 + NEW-37, D-056).
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
 * THE GATE IS `Auth::login()`, AND THAT IS WHY THIS FILE EXISTS.
 *
 * L-024 was recorded one sprint ago because a test written to prove that recovery
 * restored ACCESS asserted `UserManager::authenticate()` — the manager — and passed
 * against a command that restored nothing. Every assertion here goes through
 * `Auth::login()`, the function the admin login form actually calls
 * (`admin/login.php:115`), never through the manager it delegates to.
 *
 * Two audit findings close here and they share one cause — two authorities for one
 * decision:
 *   NEW-11  `Auth::login()` validated against config['admin_user'] /
 *           config['admin_pass_hash'], so admin, editor and viewer could not log in
 *           at all, whatever their records said.
 *   NEW-37  every password-change surface writes the RECORD, so a rotated password
 *           was refused and the old one kept working — on the only account that
 *           could log in.
 *
 * These tests need a REAL session, unlike the rest of this tier: `Auth::login()`
 * calls `session_regenerate_id( true )` on success, and `Auth::logout()` calls
 * `session_destroy()`. IntegrationTestCase deliberately builds `$_SESSION` directly
 * instead (an authorization test wants the resulting state, not the path to it), so
 * a login test is the documented exception — the `OwnerRepairTest` precedent.
 */
final class AuthLoginTest extends IntegrationTestCase
{
    /**
     * The seeded playground credentials (scripts/dev/seed-playground.php SEED_USERS).
     * Local throwaway values; the confidential-data rule covers test data too, and
     * these exist only inside a disposable playground.
     */
    private const SEEDED = [
        'owner'  => 'playground-owner-2026',
        'admin'  => 'playground-admin-2026',
        'editor' => 'playground-editor-2026',
        'viewer' => 'playground-viewer-2026',
    ];

    /**
     * Run a callable with a real PHP session active, then close it.
     *
     * @param  callable $fn
     * @return mixed
     */
    private function withSession(callable $fn): mixed
    {
        $savePath = sys_get_temp_dir() . '/klytos-auth-login-session';
        if (! is_dir($savePath)) {
            mkdir($savePath, 0700, true);
        }
        session_save_path($savePath);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        try {
            return $fn();
        } finally {
            // logout() destroys the session, so the status is re-checked rather
            // than assumed — session_write_close() on a destroyed session warns,
            // and phpunit.xml sets failOnWarning="true".
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }

    /**
     * ALL FOUR seeded roles log in through the real gate.
     *
     * Against the unfixed tree exactly one of these four passes, which is the whole
     * of NEW-11: `owner` succeeds because it happens to be config['admin_user'],
     * and admin/editor/viewer are refused with "Incorrect username or password"
     * although their records are active and their hashes valid.
     */
    public function testEverySeededRoleLogsInThroughTheRealGate(): void
    {
        foreach (self::SEEDED as $username => $password) {
            $record = $this->users->getByUsername($username);
            self::assertNotNull($record, "PRECONDITION: the playground has no '{$username}' user.");

            // The wrong password FIRST. It returns before touching the session, so it
            // needs none — and running it first means the positive assertion below
            // cannot be the only thing observed (L-010).
            $refused = $this->auth()->login($username, 'not-' . $password);
            self::assertFalse($refused['success'], "A wrong password logged '{$username}' in.");
            self::assertSame('login_failed', $refused['error']);

            $granted = $this->withSession(fn() => $this->auth()->login($username, $password));

            self::assertTrue(
                $granted['success'],
                "'{$username}' cannot log in through Auth::login() with the correct password."
            );
            self::assertSame(
                $record['id'],
                $granted['user_id'],
                "'{$username}' logged in but the gate resolved a different user id."
            );
        }
    }

    /**
     * A rotated password reaches the gate, and the old one stops working — NEW-37.
     *
     * The proof is sharper than "the new password works": config['admin_pass_hash']
     * is asserted to STILL verify the old password at the moment the old password is
     * refused. So this fails the instant anything reads config again, and it cannot
     * pass by the config and the record happening to agree.
     */
    public function testARotatedPasswordReachesTheGateAndTheOldOneStopsWorking(): void
    {
        $username = 'owner';
        $old      = self::SEEDED[$username];
        $new      = 'rotated-owner-password-2026';

        $record = $this->users->getByUsername($username);
        self::assertNotNull($record);

        $this->users->changePassword($record['id'], $new);

        // The config still holds a hash of the OLD password: nothing in the product
        // writes config['admin_pass_hash'] on a password change. That is the
        // divergence NEW-37 is about, and it is what makes the next assertion mean
        // something rather than being a tautology.
        self::assertTrue(
            password_verify($old, (string) ($this->app->getConfig()['admin_pass_hash'] ?? '')),
            'PRECONDITION: config no longer carries the old password hash, so this '
            . 'test can no longer prove which authority the gate consulted.'
        );

        $withOld = $this->auth()->login($username, $old);
        self::assertFalse(
            $withOld['success'],
            'The OLD password still logs in, so the gate is still reading config '
            . '(audit NEW-37).'
        );

        $withNew = $this->withSession(fn() => $this->auth()->login($username, $new));
        self::assertTrue(
            $withNew['success'],
            'The rotated password does not reach the gate — a password change is '
            . 'still invisible to login (audit NEW-37).'
        );
    }

    /**
     * A suspended account cannot log in, and it is refused indistinguishably.
     *
     * `UserManager::authenticate()` already refused a non-active account; it simply
     * was not on the login path. The second half is the one worth pinning: the
     * refusal must not tell a caller WHY, or the login form becomes an account-status
     * oracle.
     *
     * THE POSITIVE CONTROL IS LOAD-BEARING AND WAS ADDED AFTER MEASURING. Without
     * it this test PASSED against the unfixed tree — for entirely the wrong reason:
     * there, `editor` cannot log in whether suspended or not (NEW-11), so all three
     * refusals were trivially identical and the assertion observed nothing. Proving
     * the account can log in FIRST is what makes the refusal afterwards evidence
     * (L-010, L-016).
     */
    public function testASuspendedAccountIsRefusedAtLoginWithTheSameMessageAsAnyOtherFailure(): void
    {
        $record = $this->users->getByUsername('editor');
        self::assertNotNull($record);

        $before = $this->withSession(fn() => $this->auth()->login('editor', self::SEEDED['editor']));
        self::assertTrue(
            $before['success'],
            'PRECONDITION: this account cannot log in even before it is suspended, so '
            . 'refusing it afterwards would prove nothing.'
        );

        $this->users->update($record['id'], ['status' => 'suspended']);

        $suspended = $this->auth()->login('editor', self::SEEDED['editor']);
        self::assertFalse($suspended['success'], 'A suspended account logged in.');

        $wrongPassword = $this->auth()->login('viewer', 'definitely-wrong');
        $noSuchUser    = $this->auth()->login('nobody-by-this-name', 'definitely-wrong');

        self::assertSame(
            $wrongPassword['error'],
            $suspended['error'],
            'A suspended account is distinguishable from a wrong password.'
        );
        self::assertSame(
            $wrongPassword['error'],
            $noSuchUser['error'],
            'A non-existent account is distinguishable from a wrong password.'
        );
    }

    /**
     * Suspending an account ends its LIVE session, not just its next login.
     *
     * `isAuthenticated()` already re-read the user record every 60 seconds for
     * `force_logout_at`; the same read now checks `status`. Without it, "suspended"
     * would have meant "suspended once the 30-minute inactivity timeout expires".
     */
    public function testSuspendingAnAccountEndsItsLiveSession(): void
    {
        $record = $this->withSession(function () {
            $record = $this->actingAs('editor');

            // Positive control, and it is load-bearing: it proves the session this
            // test builds is one Auth accepts, so the assertion below cannot pass
            // because the session was never valid in the first place (L-008).
            self::assertTrue(
                $this->auth()->isAuthenticated(),
                'PRECONDITION: the acting session is not authenticated at all.'
            );

            return $record;
        });

        $this->users->update($record['id'], ['status' => 'suspended']);

        $stillIn = $this->withSession(function () {
            // The re-read is throttled to once per 60 seconds and the positive
            // control above just stamped it. Clearing the stamp simulates the
            // window having passed — the check itself is unchanged, and without
            // this the assertion would pass or fail on the clock rather than on
            // the code.
            unset($_SESSION['klytos_last_force_check']);

            return $this->auth()->isAuthenticated();
        });

        self::assertFalse(
            $stillIn,
            'A suspended user keeps a working admin session until it times out.'
        );
    }

    /**
     * Locking one account does not lock any other — and the lockout is real.
     *
     * The old bucket was keyed md5( config['admin_user'] ), i.e. ONE bucket for the
     * whole install. Harmless while a single account could log in; from D-056 onward
     * five failures against any username would have locked out everyone, owner
     * included. This asserts both halves: A is genuinely locked (so the test cannot
     * pass by the lockout never engaging) and B is unaffected.
     */
    public function testLockingOneAccountDoesNotLockAnother(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->auth()->login('admin', 'wrong-password-' . $i);
        }

        $lockedOut = $this->auth()->login('admin', self::SEEDED['admin']);
        self::assertFalse($lockedOut['success'], 'Five failures did not lock the account at all.');
        self::assertStringStartsWith(
            'account_locked:',
            (string) $lockedOut['error'],
            'The account was refused, but not because it is locked out.'
        );

        $other = $this->withSession(fn() => $this->auth()->login('editor', self::SEEDED['editor']));
        self::assertTrue(
            $other['success'],
            "Locking 'admin' also locked 'editor' — the lockout is still one global bucket."
        );
    }

    /**
     * The lockout file lives inside the install, not in the shared temp directory.
     *
     * sys_get_temp_dir() is predictable and world-writable, so on shared hosting a
     * neighbour could forge a permanent lockout or delete a real one. Being under
     * data/ also puts it inside the integration tier's snapshot (D-030).
     *
     * The temp-directory half compares BEFORE against AFTER rather than asserting
     * the directory is empty of `klytos_lockout_*`. The first version did assert
     * that and failed — on a file the OLD implementation had written during this
     * slice's own prove-it-fails run. It was measuring history rather than
     * behaviour, and it would have failed the same way on any real install that
     * had ever run a previous version. L-016: ask what the measurement would say
     * if the thing being measured did not exist.
     */
    public function testTheLockoutStateIsStoredInsideTheInstall(): void
    {
        $dataDir = $this->storage->getDataDir();
        $pattern = sys_get_temp_dir() . '/klytos_lockout_*.json';
        $before  = glob($pattern) ?: [];

        $this->auth()->login('viewer', 'wrong-password');

        self::assertFileExists(
            $dataDir . '/login_lockouts.json',
            'The lockout state is not being written inside the install data directory.'
        );

        $appeared = array_diff(glob($pattern) ?: [], $before);
        self::assertSame(
            [],
            $appeared,
            'A failed login wrote a lockout file into the shared temp directory: '
            . implode(', ', $appeared)
        );
    }

    /**
     * The owner cannot be suspended, mirroring delete()'s protection.
     *
     * Without this the owner could suspend themselves out of an install that
     * `owner:repair` ALSO refuses to help, because that command refuses whenever an
     * owner record exists (D-055) — permanently unrecoverable through the product.
     */
    public function testTheOwnerCannotBeSuspended(): void
    {
        $owner = $this->users->findOwner();
        self::assertNotNull($owner, 'PRECONDITION: the seeded playground has no owner.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot suspend the owner');

        $this->users->update($owner['id'], ['status' => 'suspended']);
    }

    /**
     * A non-owner CAN still be suspended — the guard is not "nobody may be suspended".
     *
     * One direction is half a test (L-010): without this, a guard that refused every
     * status change would look identical to the correct one.
     */
    public function testANonOwnerCanStillBeSuspended(): void
    {
        $record = $this->users->getByUsername('viewer');
        self::assertNotNull($record);

        $updated = $this->users->update($record['id'], ['status' => 'suspended']);

        self::assertSame('suspended', $updated['status']);
    }

    /**
     * The owner cannot be suspended by demoting in the SAME call — the bypass this
     * slice's own code-reviewer found, and it was real.
     *
     * `role` is processed before `status` in `update()`'s `$updatable` list and the
     * loop mutates `$user` in place, so a guard reading `$user['role']` saw 'admin'
     * by the time it ran. `update( $ownerId, [ 'role' => 'admin', 'status' =>
     * 'suspended' ] )` demoted AND suspended the owner in one call, straight through
     * the guard. Reachable owner-only, including over MCP via `klytos_update_user`,
     * which passes both fields through as independent parameters.
     */
    public function testTheOwnerCannotBeSuspendedByDemotingInTheSameCall(): void
    {
        $owner = $this->users->findOwner();
        self::assertNotNull($owner);

        $this->expectException(RuntimeException::class);

        $this->users->update($owner['id'], [
            'role'   => 'admin',
            'status' => 'suspended',
        ]);
    }

    /**
     * The owner's role cannot be removed here either — `transferOwnership()` is the
     * one supported path.
     *
     * Without this an owner could demote themselves and leave the install with NO
     * owner: the state D-031 contains and D-055 exists to repair. The class documents
     * "only one user can be owner at a time" at the top of the file; this is the half
     * of that invariant `update()` was not enforcing.
     */
    public function testTheOwnersRoleCannotBeRemovedThroughUpdate(): void
    {
        $owner = $this->users->findOwner();
        self::assertNotNull($owner);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot remove the owner role');

        $this->users->update($owner['id'], ['role' => 'admin']);
    }

    /**
     * A failed login costs the same whether the account exists, is suspended, or
     * never existed — so response time is not an account oracle.
     *
     * MEASURED before it was fixed, not reasoned about: 218.98 ms for an active
     * account with a wrong password against 0.65 ms for a suspended one and 0.64 ms
     * for a name that does not exist. A 340x difference is readable over a network
     * from a single request, and it made this file's own "no account oracle"
     * assertion false in the one channel the response body cannot hide. It only
     * became reachable unauthenticated when D-056 put authenticate() behind the
     * public login form.
     *
     * The threshold is deliberately loose. bcrypt at cost 12 takes ~200 ms on this
     * hardware and the unequalized path took well under 1 ms, so 20 ms sits two
     * orders of magnitude from both — it cannot flake on a slow or a fast machine,
     * and it still fails immediately if the equalizing verify is ever removed.
     */
    public function testAFailedLoginCostsTheSameWhoeverTheUsernameBelongsTo(): void
    {
        $started = hrtime(true);
        $this->auth()->login('no-such-account-at-all', 'wrong-password');
        $elapsedMs = (hrtime(true) - $started) / 1e6;

        self::assertGreaterThan(
            20.0,
            $elapsedMs,
            sprintf(
                'An unknown username was refused in %.2f ms, far below the cost of a '
                . 'bcrypt verify — the timing oracle is back.',
                $elapsedMs
            )
        );
    }
}
