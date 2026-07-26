<?php

/**
 * Klytos CMS — the login endpoint's IP ceiling, over real HTTP through the
 * SHIPPED form (Sprint 6, slice 1 / audit NEW-40, D-059).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

/**
 * The per-account lockout bounds attempts against ONE account. Nothing bounded
 * the endpoint itself, so a burst of INVENTED usernames never touched any
 * account's bucket and was limited only by the pruning window — while every
 * request in it paid a bcrypt verify, because authenticate() equalizes its cost
 * on every branch since the NEW-39 fix. D-059 closes that with an IP ceiling
 * that REUSES the shipped MCP\RateLimiter (10 auth failures per 60 s, IP-keyed,
 * no constant moved).
 *
 * WHY THIS IS AN HTTP TEST AND NOT AN IN-PROCESS ONE: the ceiling does not live
 * in Auth at all. It lives in `admin/login.php`, around the `$auth->login()`
 * call, and it answers by setting a status and a Retry-After header on the
 * response. None of that exists in-process, and the property under test is
 * precisely that a real request is refused.
 *
 * WHY THE REQUEST IS BUILT FIELD BY FIELD (L-026): Sprint 5 slice 2 shipped a
 * green suite over a feature no browser could use, because the harness sent a
 * header the shipped page never sends. So this class posts EXACTLY the three
 * fields `login.php`'s password form emits — `redirect_to`, `username`,
 * `password` — with no session cookie and no CSRF token, which is what a real
 * first login is; and {@see testTheRequestThisClassSendsIsTheRequestTheShippedFormSends}
 * derives that set from the page's own source so the comparison is a check
 * rather than a claim that rots.
 *
 * (`AuthLoginHttpTest`, which drives the same form for D-056, omits the hidden
 * `redirect_to`. Harmless there — an empty value takes the same branch as an
 * absent one — but this class does not inherit the shortcut, because the whole
 * point of L-026 is that "harmless" is a conclusion, not an assumption.)
 */
final class LoginCeilingHttpTest extends AdminHttpTestCase
{
    /** Ports 8099-8107 are taken by the other HTTP test classes; 8109 is slice 2's. */
    protected static function serverPort(): int
    {
        return 8108;
    }

    private const LOGIN = '/installer/admin/login.php';

    /**
     * The seeded owner (scripts/dev/seed-playground.php SEED_USERS).
     *
     * A local throwaway value that exists only inside a disposable playground —
     * the confidential-data rule covers test data too, and this is the same
     * synthetic credential AuthLoginTest and AuthLoginHttpTest already carry
     * with the same note.
     */
    private const OWNER_PASSWORD = 'playground-owner-2026';

    /**
     * MCP\RateLimiter::MAX_AUTH_FAILURES — the ceiling this endpoint reuses.
     *
     * Duplicated here deliberately rather than read through reflection: the
     * constant is private, and a test that reads the implementation's own value
     * would pass for any value at all. Written out, a change to the shipped
     * policy fails this test and has to be acknowledged.
     */
    private const MAX_AUTH_FAILURES = 10;

    /**
     * Exactly the fields `login.php`'s password form emits, in its order.
     *
     * One definition, consumed by both the requests below and the source-parity
     * assertion, so the two cannot drift apart.
     *
     * @var array<int, string>
     */
    private const SHIPPED_FIELDS = [ 'redirect_to', 'username', 'password' ];

    /**
     * A burst of invented usernames from one address is refused by the ceiling.
     *
     * Every attempt uses a DIFFERENT username on purpose. The per-account
     * lockout (5 attempts / 15 minutes, keyed by the submitted name) therefore
     * never fires, so a refusal here can only have come from the IP ceiling —
     * which is the gap NEW-40 records and the exact traffic shape it describes.
     * Asserting the reason rather than only the status is L-012's rule: two
     * different controls both refuse, and only one of them is under test.
     *
     * @return void
     */
    public function testTheIpCeilingRefusesABurstOfInventedUsernamesThroughTheShippedForm(): void
    {
        for ( $i = 1; $i <= self::MAX_AUTH_FAILURES; $i++ ) {
            $response = $this->postLoginForm( 'ghost-account-' . $i, 'not-the-password' );

            self::assertSame(
                200,
                $response['status'],
                "Attempt {$i} of " . self::MAX_AUTH_FAILURES . ' was refused before the ceiling was '
                . 'reached, so this test would pass without the ceiling existing.'
            );
            self::assertStringContainsString(
                'Incorrect username or password',
                $response['body'],
                "Attempt {$i} did not get the ordinary refusal."
            );
        }

        $blocked = $this->postLoginForm( 'ghost-account-over-the-line', 'not-the-password' );

        self::assertSame(
            429,
            $blocked['status'],
            'The attempt past the ceiling was served normally: the login endpoint has no IP ceiling.'
        );
        self::assertSame(
            '60',
            $this->headerValue( $blocked, 'Retry-After' ),
            'A 429 without Retry-After tells a client nothing about when to come back.'
        );
        self::assertStringContainsString(
            'Too many login attempts from this address',
            $blocked['body'],
            'The refusal must say WHY, in the translated string, not merely carry a status.'
        );
        self::assertStringNotContainsString(
            'Account locked',
            $blocked['body'],
            'That is the per-ACCOUNT lockout. Every attempt here used a different username, so if '
            . 'this fires, the test is measuring the wrong control.'
        );
        self::assertStringNotContainsString( 'Fatal error', $blocked['body'] );
    }

    /**
     * Below the ceiling, a correct password still gets in.
     *
     * The positive control, and it is not a formality: a ceiling that refused
     * every request — or one whose counter never pruned — would satisfy the
     * test above completely (L-008). It also pins the operational promise the
     * reference doc makes, that ordinary failed logins do not lock the site's
     * own users out of it.
     *
     * @return void
     */
    public function testBelowTheCeilingACorrectPasswordStillGetsIn(): void
    {
        for ( $i = 1; $i <= 3; $i++ ) {
            $this->postLoginForm( 'ghost-account-' . $i, 'not-the-password' );
        }

        $response = $this->postLoginForm( 'owner', self::OWNER_PASSWORD );

        self::assertSame(
            302,
            $response['status'],
            'Three failed attempts from this address locked the owner out of their own site.'
        );
        self::assertStringNotContainsString( 'Too many login attempts', $response['body'] );
    }

    /**
     * A plugin can exempt an address from the ceiling — proven with a REAL
     * subscriber, not by observing that the filter is called.
     *
     * The filter exists for one recorded reason: `getClientIp()` trusts
     * `X-Forwarded-For` only from loopback, so behind a non-loopback proxy every
     * visitor collapses into one bucket and a whole office on one NAT address
     * shares this ceiling (audit **NEW-17**). `docs/sprints/sprint-6.md` offered
     * filterability as the operator's remedy for exactly that — and when this
     * slice's review checked, **no such filter existed**, which is why one was
     * added here rather than the claim being quietly softened.
     *
     * Driven through a plugin because that is the only honest way: the listener
     * has to be registered inside the SERVER process, after `App::boot()` has
     * built the hook system and before `login.php`'s POST handler runs, and
     * `PluginLoader` is the product's own seam for exactly that. A filter
     * registered in the test process would prove nothing about the process that
     * serves the request — and **L-019** was recorded because this project once
     * believed a hook with no reachable subscriber was a working feature.
     *
     * @return void
     */
    public function testAPluginCanExemptAnAddressFromTheCeiling(): void
    {
        $this->withCeilingExemptionPlugin( function (): void {
            for ( $i = 1; $i <= self::MAX_AUTH_FAILURES + 2; $i++ ) {
                $response = $this->postLoginForm( 'ghost-exempt-' . $i, 'not-the-password' );

                self::assertSame(
                    200,
                    $response['status'],
                    "Attempt {$i} was throttled although a plugin exempted this address, so the "
                    . 'auth.login_ip_blocked filter has no effect and NEW-17 has no remedy.'
                );
            }
        } );

        // The exemption is gone with the plugin: without it, the SAME burst
        // that was just served must be refused. Without this half the test
        // would pass against a ceiling that had simply stopped working.
        $stillEnforced = false;

        for ( $i = 1; $i <= self::MAX_AUTH_FAILURES + 2; $i++ ) {
            if ( $this->postLoginForm( 'ghost-after-' . $i, 'not-the-password' )['status'] === 429 ) {
                $stillEnforced = true;
                break;
            }
        }

        self::assertTrue(
            $stillEnforced,
            'With the plugin gone the ceiling did not engage, so the exemption above proved nothing.'
        );
    }

    /**
     * The request this class sends IS the request the shipped page sends.
     *
     * L-026 in mechanical form. That lesson was written because a test helper
     * added an `X-CSRF-Token` header no shipped page sends, so a feature that
     * could not work in any browser had five green tests. The remedy recorded
     * there is to compare the test's request against the product's own client
     * field by field — and a comparison performed once, by hand, in a docblock,
     * is exactly the kind of claim this project has watched rot.
     *
     * So the field set is derived from `login.php`'s own source. Adding a field
     * to the form (a CSRF token being the obvious one) fails this test, which
     * is the intended outcome: the request above must be updated in the same
     * slice, rather than quietly ceasing to resemble the shipped one.
     *
     * @return void
     */
    public function testTheRequestThisClassSendsIsTheRequestTheShippedFormSends(): void
    {
        $source = (string) file_get_contents( KLYTOS_INSTALLER_PATH . '/admin/login.php' );

        self::assertNotSame( '', $source, 'login.php could not be read, so nothing was compared.' );

        // The password form is the one carrying the password input; the other
        // five forms on this page are the 2FA branch.
        $forms = [];
        preg_match_all( '#<form\b.*?</form>#s', $source, $forms );

        $passwordForms = array_values( array_filter(
            $forms[0],
            static fn( string $form ): bool => str_contains( $form, 'name="password"' )
        ) );

        self::assertCount(
            1,
            $passwordForms,
            'Expected exactly one password form in login.php; found ' . count( $passwordForms )
            . '. The parse below would be measuring the wrong markup.'
        );

        $names = [];
        preg_match_all( '/name="([a-z0-9_]+)"/i', $passwordForms[0], $names );

        self::assertSame(
            self::SHIPPED_FIELDS,
            $names[1],
            'The shipped login form no longer emits the fields this class posts. Update the '
            . 'requests in this file to match it — a test whose request has drifted from the '
            . "product's own client is the L-026 defect."
        );

        // A CSRF field is emitted by a helper call, not by a literal name=
        // attribute, so the parse above cannot see it. Checked separately
        // rather than assumed: the password POST branch runs no
        // klytos_verify_csrf(), and if that ever changes, this class's
        // token-less requests would start being answered 403 and every
        // assertion above would pass for the wrong reason.
        self::assertStringNotContainsString(
            'klytos_csrf_field',
            $passwordForms[0],
            'The password form now emits a CSRF token that these requests do not send.'
        );
    }

    /**
     * Run $body with a plugin active that exempts this address from the ceiling.
     *
     * The plugin is written to `installer/plugins/` and activated by writing the
     * plugin state directly — the seeder's own technique
     * (`scripts/dev/seed-playground.php`), chosen for the same reason it was
     * chosen there: `PluginLoader::activate()` would run `install.php` and
     * rebuild plugin assets, side effects a test has no use for. Both the
     * directory and the state are removed in a `finally`, and the playground
     * snapshot (D-030) restores the state file regardless.
     *
     * @param  callable $body
     * @return void
     */
    private function withCeilingExemptionPlugin( callable $body ): void
    {
        $id  = 'zz-ceiling-exemption-probe';
        $dir = KLYTOS_INSTALLER_PATH . '/plugins/' . $id;

        // The plugin contract is immutable: the ID is the directory name AND
        // the {id}.php entry point AND the PHP header. Not klytos-plugin.json,
        // which is an optional extension.
        mkdir( $dir, 0755, true );
        file_put_contents(
            $dir . '/' . $id . '.php',
            "<?php\n"
            . "/**\n"
            . " * Plugin Name: Ceiling Exemption Probe\n"
            . " * Description: Test-only. Exempts this address from the login IP ceiling.\n"
            . " * Version: 1.0.0\n"
            . " */\n"
            . "klytos_add_filter( 'auth.login_ip_blocked', static fn(): bool => false );\n"
        );

        $state = $this->storage->read( 'plugins.json.enc' );
        $this->storage->write( 'plugins.json.enc', [
            'active'       => ( $state['active'] ?? [] ) + [ $id => true ],
            'activated_at' => ( $state['activated_at'] ?? [] ) + [ $id => \Klytos\Core\Helpers::now() ],
            'logs_enabled' => $state['logs_enabled'] ?? [],
        ] );

        try {
            $body();
        } finally {
            $this->storage->write( 'plugins.json.enc', $state );

            @unlink( $dir . '/' . $id . '.php' );
            @rmdir( $dir );
        }
    }

    /**
     * POST the login form exactly as the shipped page does.
     *
     * Anonymous — no session cookie and no CSRF token — because the password
     * branch of login.php gates on neither (`klytos_verify_csrf()` guards only
     * the 2FA branch), and a first login has neither to send.
     *
     * @param  string $username Submitted username.
     * @param  string $password Submitted password.
     * @return array{status:int, body:string, content_type:string, location:string, headers:string}
     */
    private function postLoginForm( string $username, string $password ): array
    {
        return $this->post(
            self::LOGIN,
            [
                'redirect_to' => '',
                'username'    => $username,
                'password'    => $password,
            ],
            null
        );
    }
}
