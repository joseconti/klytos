<?php

/**
 * Klytos CMS — the four-role walk through the REAL login form over HTTP
 * (Sprint 5, slice 1 / audit NEW-11, D-056).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

/**
 * AuthLoginTest drives `Auth::login()` in-process; this drives the form a person
 * actually fills in, over the wire, through `admin/login.php`.
 *
 * Both exist deliberately. The in-process tests can assert the returned array and
 * the record; only this one proves the whole request path — the form's own POST
 * handler, the redirect on success, and the rendered refusal on failure. The
 * measurement that opened this sprint was taken exactly this way (owner 302,
 * admin/editor/viewer 200 + "Incorrect username or password"), so this is that
 * measurement turned into a permanent test.
 *
 * The password login POST carries no CSRF check (`login.php:111` gates only the
 * 2FA branch on `klytos_verify_csrf()`), so these post anonymously — no session
 * cookie, no token — which is what a real first login does.
 */
final class AuthLoginHttpTest extends AdminHttpTestCase
{
    /** Ports 8099-8105 are taken by the other HTTP test classes. */
    protected static function serverPort(): int
    {
        return 8106;
    }

    private const LOGIN = '/installer/admin/login.php';

    /**
     * The seeded playground credentials (scripts/dev/seed-playground.php SEED_USERS).
     */
    private const SEEDED = [
        'owner'  => 'playground-owner-2026',
        'admin'  => 'playground-admin-2026',
        'editor' => 'playground-editor-2026',
        'viewer' => 'playground-viewer-2026',
    ];

    /**
     * Every role reaches the admin panel through the form — NEW-11's own measurement.
     *
     * Against the unfixed tree this reports 302 / 200 / 200 / 200: three of the four
     * accounts exist, hold their roles, carry valid bcrypt hashes, and cannot get in.
     */
    public function testEveryRoleCanLogInThroughTheRealForm(): void
    {
        foreach (self::SEEDED as $username => $password) {
            $response = $this->post(self::LOGIN, [
                'username' => $username,
                'password' => $password,
            ], null);

            self::assertSame(
                302,
                $response['status'],
                "'{$username}' was refused by the real login form (HTTP {$response['status']})."
            );
            self::assertStringNotContainsString(
                'Incorrect username or password',
                $response['body'],
                "'{$username}' got the refusal text back."
            );
        }
    }

    /**
     * A wrong password is refused, and the REASON is asserted rather than the status.
     *
     * L-009: a PHP fatal answers 200 with the error in the body, so a status-only
     * assertion cannot tell "refused" from "crashed". L-012: asserting the reason is
     * what separates a refusal from any other non-redirect.
     */
    public function testAWrongPasswordIsRefusedWithTheRefusalText(): void
    {
        $response = $this->post(self::LOGIN, [
            'username' => 'owner',
            'password' => 'definitely-not-the-password',
        ], null);

        self::assertSame(200, $response['status'], 'A failed login did not re-render the form.');
        self::assertStringContainsString('Incorrect username or password', $response['body']);
        self::assertStringNotContainsString('Fatal error', $response['body']);
        self::assertStringNotContainsString('Uncaught', $response['body']);
    }

    /**
     * An unknown username is indistinguishable from a wrong password.
     *
     * The login form must not become an account oracle — the same property the
     * per-account lockout was designed around (D-056, implementation note 2).
     */
    public function testAnUnknownUsernameIsRefusedIdenticallyToAWrongPassword(): void
    {
        $unknown = $this->post(self::LOGIN, [
            'username' => 'no-such-account-here',
            'password' => 'definitely-not-the-password',
        ], null);

        $wrongPassword = $this->post(self::LOGIN, [
            'username' => 'viewer',
            'password' => 'definitely-not-the-password',
        ], null);

        self::assertSame(200, $unknown['status']);
        self::assertSame($wrongPassword['status'], $unknown['status']);
        self::assertStringContainsString('Incorrect username or password', $unknown['body']);
        self::assertStringContainsString('Incorrect username or password', $wrongPassword['body']);
    }
}
