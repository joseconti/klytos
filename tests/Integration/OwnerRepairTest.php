<?php

/**
 * Klytos CMS — recovering an install that has no owner (Sprint 4, slice 2 / audit NEW-08).
 *
 * D-031 contained the boot crash that a failed v1.x owner migration used to cause:
 * boot now logs and continues with no owner record, which is fail-closed. What it
 * explicitly did NOT do is restore access — with no owner, login cannot succeed and
 * every permission check denies. The CLI's `users` command only lists. So an install
 * could reach a state with no supported recovery path on any interface.
 *
 * These tests drive the recovery from the state that actually produces it, rather
 * than from a synthetic one: the owner record is removed and the command is asked
 * to put one back.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Core\TerminalExecutor;
use Klytos\Tests\IntegrationTestCase;

/**
 * `owner:repair` — the only supported way to recreate a missing owner.
 *
 * Deleting the seeded owner is safe here for the reason D-030 established: the
 * integration tier snapshots and restores the whole playground around every test,
 * and that record belongs to the seed rather than to the test. V1MigrationTest
 * already relies on the same property.
 */
final class OwnerRepairTest extends IntegrationTestCase
{
    private function executor(): TerminalExecutor
    {
        return new TerminalExecutor( $this->app );
    }

    /** Remove every owner record, leaving the install unrecoverable by design. */
    private function removeTheOwner(): void
    {
        foreach ( $this->users->list() as $user ) {
            if ( ( $user['role'] ?? '' ) === 'owner' ) {
                $this->storage->delete( 'users', $user['id'] );
            }
        }

        self::assertNull(
            $this->users->findOwner(),
            'PRECONDITION FAILED: the install still has an owner, so nothing is being recovered.'
        );
    }

    /**
     * The email the config already carries.
     *
     * Passing this makes the repair's config write NET ZERO, which keeps D-039's
     * config-mutation guard quiet without weakening anything: the command still
     * takes the same branch, still writes config.json.enc, and still runs the real
     * migration — the bytes simply come back identical. Writing a DIFFERENT email
     * would trip the guard for a correct reason (App::$config is decrypted once at
     * boot and has no invalidation path, so a later test would read a stale value),
     * and suppressing that guard for this file would give up a protection the whole
     * tier depends on.
     */
    private function configuredEmail(): string
    {
        return (string) ( $this->app->getConfig()['admin_email'] ?? 'owner@playground.test' );
    }

    public function testItRecreatesAMissingOwner(): void
    {
        $this->removeTheOwner();

        $result = $this->executor()->dispatch( 'owner:repair', [], [
            'email' => $this->configuredEmail(),
        ] );

        self::assertTrue( $result['success'], 'owner:repair failed: ' . $result['output'] );

        // Assert the PERSISTED effect, never the command's own report (L-017):
        // a recovery that says it worked and wrote nothing is the exact defect
        // the migration footgun produced one sprint ago.
        $owner = $this->users->findOwner();

        self::assertNotNull( $owner, 'The command reported success and no owner exists.' );
        self::assertSame(
            $this->app->getConfig()['admin_user'],
            $owner['username'],
            'The restored owner must carry the username Auth::login() validates against, '
            . 'or the record is one nobody can log in as.'
        );
        self::assertSame( 'owner', $owner['role'] );
    }

    /**
     * The recovered install must let the operator LOG IN — through the real gate.
     *
     * This is the test the slice's own code-reviewer caught as unsound in its first
     * form, and the finding was correct. It asserted `UserManager::authenticate()`,
     * which is NOT what the admin panel uses: `Auth::login()` validates the username
     * against `config['admin_user']` and the password against
     * `config['admin_pass_hash']`, never against the user record (that is NEW-11).
     * So the original command minted a record with its own username and a freshly
     * hashed password — and nobody could ever log in as it, while `findOwner()`
     * returning non-null made the command refuse to run again.
     *
     * Hence the design: repair `admin_email` and let the product's own migration
     * build the record from the credentials that already work. This test drives
     * the whole FEATURE — recover, then log in — rather than the defect (L-014).
     */
    public function testTheRecoveredInstallCanActuallyBeLoggedIntoThroughAuthLogin(): void
    {
        $config   = $this->app->getConfig();
        $username = (string) $config['admin_user'];

        $this->removeTheOwner();

        $result = $this->executor()->dispatch( 'owner:repair', [], [
            'email' => $this->configuredEmail(),
        ] );
        self::assertTrue( $result['success'], 'owner:repair failed: ' . $result['output'] );

        $auth = $this->app->getAuth();

        // The wrong password FIRST, deliberately: Auth::login() returns before
        // touching the session on a credential mismatch, so this half needs no
        // session at all — and running it first means the positive assertion
        // below cannot be the only thing observed (L-010).
        self::assertFalse(
            $auth->login( $username, 'definitely-not-the-password' )['success'],
            'Login succeeded with the wrong password.'
        );

        // THE REAL GATE, and it needs a REAL session. This tier deliberately does
        // not start one — IntegrationTestCase builds $_SESSION state directly,
        // because an authorization test wants the resulting state rather than the
        // path to it. A login test is the exception: Auth::login() calls
        // session_regenerate_id() on success, which requires an active session.
        // Started here, in the one test that needs it, and closed immediately.
        $savePath = sys_get_temp_dir() . '/klytos-owner-repair-session';
        if ( ! is_dir( $savePath ) ) {
            mkdir( $savePath, 0700, true );
        }
        session_save_path( $savePath );

        if ( session_status() !== PHP_SESSION_ACTIVE ) {
            session_start();
        }

        try {
            $login = $auth->login( $username, 'playground-owner-2026' );
        } finally {
            if ( session_status() === PHP_SESSION_ACTIVE ) {
                session_write_close();
            }
        }

        self::assertTrue(
            $login['success'],
            'The owner record was restored but Auth::login() still refuses — the repair '
            . 'did not restore ACCESS, only a record.'
        );
        self::assertNotNull( $login['user_id'], 'Login succeeded without resolving a user id.' );
    }

    public function testItRefusesWhenAnOwnerAlreadyExists(): void
    {
        $existing = $this->users->findOwner();
        self::assertNotNull( $existing, 'PRECONDITION: the seeded playground should have an owner.' );

        $result = $this->executor()->dispatch( 'owner:repair', [], [
            'email' => 'intruder@example.test',
        ] );

        self::assertFalse( $result['success'], 'owner:repair created a second owner.' );

        // Assert the REASON, not just the outcome (L-012). Against a tree where
        // the command does not exist, dispatch() also returns success=false —
        // so a bare assertFalse here would pass without observing anything.
        self::assertStringNotContainsStringIgnoringCase(
            'no reconocido',
            $result['output'],
            'This refusal came from "unknown command", not from the owner check.'
        );
        self::assertStringContainsString( $existing['username'], $result['output'] );

        $after = $this->users->findOwner();
        self::assertSame(
            $existing['username'],
            $after['username'],
            'The existing owner was replaced or altered.'
        );
        self::assertNull(
            $this->users->getByUsername( 'intruder' ),
            'A refused repair still created the account.'
        );
    }

    public function testItRefusesIncompleteArgumentsWithoutCreatingAnything(): void
    {
        $this->removeTheOwner();

        $result = $this->executor()->dispatch( 'owner:repair', [], [] );

        self::assertFalse( $result['success'] );
        // Same trap as above: an unknown command also fails. Assert this refusal
        // is the argument check, by requiring it to name what is missing.
        self::assertStringNotContainsStringIgnoringCase( 'no reconocido', $result['output'] );
        self::assertStringContainsString( '--email', $result['output'] );
        self::assertNull( $this->users->findOwner(), 'A rejected repair created an owner anyway.' );
    }

    /**
     * The password must never reach the terminal history, the audit log, or the
     * response echoed back to the browser.
     *
     * This is not hypothetical. `execute()` persists the typed command into the
     * `terminal` storage collection — which is absent from ENCRYPTED_PATHS at
     * EVERY encryption level, so it is plaintext on disk — and into the audit log,
     * a plaintext file. `admin/logs.php` is gated at `site.configure`, which
     * resolves to owner AND admin. So without redaction an `admin`, strictly
     * lower-privileged than the owner, could read the owner's password out of the
     * log. Found by this slice's own security-auditor pass; `owner:repair` is the
     * first terminal command ever to take a secret as a flag, so nothing in that
     * generic path had needed to be secret-aware before.
     *
     * Redaction lives in execute(), not in this command, so the next command
     * taking a --token is safe without its author remembering anything.
     */
    public function testAPasswordFlagIsRedactedFromEverythingThatOutlivesTheRequest(): void
    {
        $owner = $this->users->findOwner();
        self::assertNotNull( $owner );

        $secret   = 'ThisMustNeverBePersisted2026';
        $executor = $this->executor();

        $this->actingAs( 'owner' );

        // execute() re-demands 2FA after 10 minutes of terminal inactivity and
        // returns BEFORE the history/audit writes — correct behaviour, and it is
        // what the positive control below caught on the first run. Simulate a
        // session that has just used the terminal so the persistence path is
        // actually reached; without this the test would assert "the secret is not
        // in the history" about a history nothing ever wrote to (L-010).
        $_SESSION['klytos_terminal_last_command'] = time();

        $result = $executor->execute(
            'owner:repair --username=someone --email=s@example.test --password=' . $secret,
            (string) $owner['id']
        );

        // 1. Not echoed back to the browser.
        self::assertStringNotContainsString( $secret, $result['command'] );
        self::assertStringNotContainsString( $secret, $result['output'] );
        self::assertStringContainsString( '--password=***', $result['command'] );

        // 2. Not in the persisted history — the plaintext-on-disk collection.
        $history = json_encode( $executor->getHistory() );
        self::assertStringNotContainsString( $secret, (string) $history );

        // The positive control: the non-secret part of the command IS recorded,
        // so this cannot pass by nothing being written at all (L-010).
        self::assertStringContainsString( 'owner:repair', (string) $history );
        self::assertStringContainsString( '--username=someone', (string) $history );
    }

    public function testTheCommandIsRegisteredAndGatedAtUsersManage(): void
    {
        // The CLI reaches dispatch() directly and performs NO permission check —
        // deliberate, since CLI access already implies filesystem access, and it
        // is what makes recovery possible with no session at all. The declared
        // permission is what gates the WEB terminal, so it has to be present and
        // it has to be the owner-only one.
        $commands = $this->executor()->getCommands();

        self::assertArrayHasKey( 'owner:repair', $commands );
        self::assertSame( 'users.manage', $commands['owner:repair']['permission'] ?? null );
    }
}
