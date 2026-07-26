<?php

/**
 * Klytos CMS — the two anonymous forms verify CSRF (Sprint 6 slice 4 / audit
 * NEW-47 and NEW-26, D-061), driven over real HTTP exactly as a browser drives
 * them.
 *
 * WHAT NEW-47 ACTUALLY IS, because it is the finding people wave away: login
 * CSRF is not "an attacker logs into your account". It is the reverse. An
 * attacker who holds their own account on this install forces the victim's
 * browser to submit the ATTACKER's credentials; the victim carries on working,
 * unaware, inside an account the attacker controls, and everything they write,
 * upload or connect from then on is the attacker's to read. `SameSite=Strict`
 * does not help, because the victim has no session cookie to withhold — the
 * request that creates one is the attack.
 *
 * WHY EVERY REQUEST HERE FETCHES THE PAGE FIRST: the token and the session must
 * be the ones the SHIPPED page issued. L-026 exists because a harness once sent
 * a header no page sends and a broken feature had a green suite; a
 * harness-minted CSRF token would be the same defect with the sign flipped —
 * proof that the harness agrees with itself.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

final class LoginCsrfHttpTest extends AdminHttpTestCase
{
    /**
     * Its own port: 8099–8109 are taken by the other HTTP test classes, and
     * `docs/playground.md` documents 8080 / 8110 / 8111 / 8123 for human use —
     * a test class must not collide with a port the document tells a reader to
     * start a server on.
     */
    protected static function serverPort(): int
    {
        return 8112;
    }

    private const LOGIN = '/installer/admin/login.php';

    private const RESET = '/installer/admin/reset-password.php';

    /** Seeded playground credentials (scripts/dev/seed-playground.php SEED_USERS). */
    private const OWNER_PASSWORD  = 'playground-owner-2026';
    private const EDITOR_PASSWORD = 'playground-editor-2026';

    /**
     * THE FINDING: a login POST with no CSRF token is refused.
     *
     * The credentials are CORRECT and the account is real — that is the whole
     * point, and it is why this is not a variant of "a bad login fails". Against
     * the unfixed tree this request answered **302** and established a session:
     * the forced login worked. It now answers 403 and no session is created.
     */
    public function testALoginPostWithNoCsrfTokenIsRefused(): void
    {
        $response = $this->post(
            self::LOGIN,
            [
                'redirect_to' => '',
                'username'    => 'owner',
                'password'    => self::OWNER_PASSWORD,
            ],
            null
        );

        self::assertSame(
            403,
            $response['status'],
            'A login POST carrying valid credentials and no CSRF token was accepted — that is '
            . 'audit NEW-47: an attacker can log a victim into the attacker\'s own account.'
        );
        self::assertNotSame(
            302,
            $response['status'],
            'The refusal must not be a redirect, because a redirect here IS the successful login.'
        );
        // "No session was established" is asserted by USING the session the
        // refused response handed back, not by looking for a Set-Cookie header:
        // bootstrap.php starts a session on every admin request, so that header
        // is always present and asserting its absence would measure something
        // the product never does. What matters is whether the browser is now
        // logged in — so ask the dashboard.
        $session = [];
        preg_match( '/^Set-Cookie:\s*klytos_session=([^;\s]+)/mi', $response['headers'], $session );

        self::assertNotEmpty( $session[1] ?? '', 'No session cookie came back, so nothing was checked.' );

        $dashboard = $this->request( '/installer/admin/', null, $session[1] );

        self::assertSame(
            302,
            $dashboard['status'],
            'The forged POST logged the browser in after all: the session it handed back reaches '
            . 'the dashboard instead of being bounced to the login form.'
        );
        self::assertStringContainsString(
            'session expired',
            $response['body'],
            'The refusal must say the session expired, not that the credentials were wrong — a '
            . 'legitimate user with a stale page must not be sent to change a working password.'
        );
    }

    /**
     * A token belonging to somebody ELSE's session is refused.
     *
     * The sharper half of the property: the check must bind the token to the
     * session that received it, not merely require a non-empty string. An
     * attacker can always obtain a valid-looking token — by loading the login
     * page themselves — so a check that accepted any token at all would refuse
     * nothing.
     */
    public function testATokenFromAnotherSessionIsRefused(): void
    {
        $victim   = $this->formSession( self::LOGIN );
        $attacker = $this->formSession( self::LOGIN );

        self::assertNotSame(
            $victim['csrf'],
            $attacker['csrf'],
            'Two sessions were issued the same CSRF token, so this test could not tell them apart.'
        );

        $response = $this->post(
            self::LOGIN,
            [
                'csrf'        => $attacker['csrf'],
                'redirect_to' => '',
                'username'    => 'owner',
                'password'    => self::OWNER_PASSWORD,
            ],
            null,
            [],
            $victim['session']
        );

        self::assertSame(
            403,
            $response['status'],
            'A token minted for a different session was accepted, so the check is a presence test '
            . 'rather than a CSRF control.'
        );
    }

    /**
     * THE POSITIVE CONTROL, and the one that matters most in this slice: the
     * form still logs people in.
     *
     * A CSRF fix that quietly broke the login form would be the worst possible
     * outcome — a security change locking every operator out of their own site.
     * So this drives the exact browser sequence: GET the page, keep its session,
     * post back its token.
     */
    public function testTheFormStillLogsInWhenSubmittedTheWayTheShippedPageDoes(): void
    {
        $form = $this->formSession( self::LOGIN );

        $response = $this->post(
            self::LOGIN,
            [
                'csrf'        => $form['csrf'],
                'redirect_to' => '',
                'username'    => 'owner',
                'password'    => self::OWNER_PASSWORD,
            ],
            null,
            [],
            $form['session']
        );

        self::assertSame(
            302,
            $response['status'],
            'The shipped form no longer logs anyone in — the CSRF fix broke authentication.'
        );
        self::assertStringNotContainsString( 'Incorrect username or password', $response['body'] );
        self::assertStringNotContainsString( 'session expired', $response['body'] );
    }

    /**
     * The empty-token hole this slice had to close first (audit NEW-50).
     *
     * `hash_equals( '', '' )` is TRUE, so before the fix `Auth::validateCsrf('')`
     * returned true for any request arriving in a session that held no token —
     * which is exactly the anonymous state these two forms live in. Adding
     * `klytos_verify_csrf()` to the login branch therefore changed nothing until
     * the primitive itself was fixed. Pinned here, over HTTP, because that is
     * where it was observed: the token-less POST above must be refused even
     * though BOTH sides of the comparison are empty.
     */
    public function testAnEmptyTokenIsRefusedEvenWhenTheSessionHoldsNoneEither(): void
    {
        $response = $this->post(
            self::LOGIN,
            [
                'csrf'        => '',
                'redirect_to' => '',
                'username'    => 'owner',
                'password'    => self::OWNER_PASSWORD,
            ],
            null
        );

        self::assertSame(
            403,
            $response['status'],
            'An empty CSRF token was accepted against an empty session value — hash_equals("","") '
            . 'is true, and that is audit NEW-50.'
        );
    }

    /**
     * NEW-26: the password-reset form is refused without its token, and the
     * password is genuinely unchanged.
     *
     * Asserting the response alone would not settle it — the page could refuse
     * and still have written the password (the L-009 shape) — so the old
     * credential is driven through the real login form afterwards.
     */
    public function testThePasswordResetFormIsRefusedWithoutItsCsrfToken(): void
    {
        $editor = $this->users->getByUsername( 'editor' );
        self::assertNotNull( $editor, 'Precondition: the seeded editor must exist.' );

        $token = $this->users->generatePasswordResetToken( $editor['id'] );
        $url   = self::RESET . '?user_id=' . urlencode( $editor['id'] ) . '&token=' . urlencode( $token );

        $response = $this->post(
            $url,
            [
                'user_id'          => $editor['id'],
                'token'            => $token,
                'password'         => 'a-brand-new-password-2026',
                'password_confirm' => 'a-brand-new-password-2026',
            ],
            null
        );

        self::assertSame( 403, $response['status'], 'The reset form accepted a POST with no CSRF token.' );
        self::assertStringNotContainsString( 'has been reset successfully', $response['body'] );

        // The password must be untouched: prove it through the real gate, not by
        // re-reading the record (L-024).
        $login = $this->postLoginWithFreshToken( 'editor', self::EDITOR_PASSWORD );
        self::assertSame(
            302,
            $login['status'],
            'The refused reset still changed the password — the editor can no longer log in with it.'
        );
    }

    /**
     * The positive control for the reset flow: submitted the way the shipped
     * page submits it, the reset completes AND the new password works.
     *
     * Same reasoning as the login control — a CSRF fix that broke password
     * recovery would strand exactly the users who cannot log in.
     */
    public function testThePasswordResetStillWorksWhenSubmittedWithItsToken(): void
    {
        $editor = $this->users->getByUsername( 'editor' );
        self::assertNotNull( $editor );

        $token = $this->users->generatePasswordResetToken( $editor['id'] );
        $url   = self::RESET . '?user_id=' . urlencode( $editor['id'] ) . '&token=' . urlencode( $token );

        $form = $this->formSession( $url );

        $newPassword = 'a-brand-new-password-2026';

        $response = $this->post(
            $url,
            [
                'csrf'             => $form['csrf'],
                'user_id'          => $editor['id'],
                'token'            => $token,
                'password'         => $newPassword,
                'password_confirm' => $newPassword,
            ],
            null,
            [],
            $form['session']
        );

        self::assertSame( 200, $response['status'] );
        self::assertStringContainsString(
            'has been reset successfully',
            $response['body'],
            'The reset did not complete, so the CSRF field broke password recovery.'
        );

        $login = $this->postLoginWithFreshToken( 'editor', $newPassword );
        self::assertSame(
            302,
            $login['status'],
            'The reset reported success and the new password does not log in.'
        );
    }

    /**
     * NEW-51 — the SECOND login form. The OAuth consent screen's own
     * `action=login` POST is the other `Auth::login()` call site in the product,
     * and it had no CSRF check either.
     *
     * Found by this slice's own `security-auditor` pass and fixed in path rather
     * than recorded, because closing one of two identical paths is precisely the
     * failure D-041's review cycle wrote down: NEW-47's vulnerability would have
     * stayed exploitable through a different URL while its entry said CLOSED.
     * The sibling `action=authorize` branch in the same file has verified CSRF
     * all along, which is what makes the gap visible once anyone looks.
     */
    public function testTheOauthConsentScreenLoginIsRefusedWithoutACsrfToken(): void
    {
        $authorizeUrl = $this->oauthAuthorizeUrl();

        $response = $this->post(
            $authorizeUrl,
            [
                'action'   => 'login',
                'username' => 'owner',
                'password' => self::OWNER_PASSWORD,
            ],
            null
        );

        self::assertStringContainsString(
            'Invalid CSRF token',
            $response['body'],
            'The consent screen logged a caller in with no CSRF token — NEW-47 through a second door.'
        );

        // The marker is the consent BUTTON, not the word "Authorize": that word is
        // also the page's own heading ("Authorize Application"), so asserting on it
        // measures the chrome rather than the screen. Caught by this assertion
        // failing while the response was correct — L-018's shape, a blocklist word
        // that can legitimately appear.
        self::assertStringNotContainsString(
            'value="authorize"',
            $response['body'],
            'The consent step was reached, which means the forced login succeeded.'
        );
        self::assertStringContainsString(
            'value="login"',
            $response['body'],
            'The login form was not re-rendered, so the caller cannot retry.'
        );
    }

    /**
     * The positive control for that second form: submitted with the token it
     * emits, the consent screen still authenticates and shows the consent step.
     */
    public function testTheOauthConsentScreenStillLogsInWithItsToken(): void
    {
        $authorizeUrl = $this->oauthAuthorizeUrl();
        $form         = $this->formSession( $authorizeUrl );

        $response = $this->post(
            $authorizeUrl,
            [
                'csrf'     => $form['csrf'],
                'action'   => 'login',
                'username' => 'owner',
                'password' => self::OWNER_PASSWORD,
            ],
            null,
            [],
            $form['session']
        );

        self::assertStringNotContainsString(
            'Invalid CSRF token',
            $response['body'],
            'The consent screen refused a token it had just issued itself.'
        );
        self::assertStringNotContainsString( 'Invalid credentials', $response['body'] );
        self::assertStringContainsString(
            'value="authorize"',
            $response['body'],
            'The login did not reach the consent step, so the CSRF field broke the OAuth flow.'
        );
    }

    /**
     * Register a real OAuth client and build the authorize URL for it.
     *
     * Through the product's own `OAuthServer::createClient()` and the parameter
     * set `validateAuthorizeRequest()` actually requires (PKCE included, which
     * OAuth 2.1 makes mandatory) — an invented URL would be refused before the
     * login form is ever rendered, and the test would pass on the error page.
     *
     * @return string Path + query, relative to the document root.
     */
    private function oauthAuthorizeUrl(): string
    {
        $oauth = new \Klytos\Core\MCP\OAuthServer(
            $this->auth(),
            $this->storage,
            new \Klytos\Core\MCP\RateLimiter( $this->storage->getDataDir() )
        );

        $redirectUri = 'http://127.0.0.1/oauth-csrf-test/callback';
        $client      = $oauth->createClient( 'oauth-csrf-test', $redirectUri, false );

        $verifier  = \Klytos\Core\Helpers::randomHex( 32 );
        $challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );

        return '/installer/oauth/authorize?' . http_build_query( [
            'client_id'             => $client['client_id'],
            'redirect_uri'          => $redirectUri,
            'response_type'         => 'code',
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
            'state'                 => 'csrf-test-state',
        ] );
    }

    /**
     * Log in through the shipped form with a session and token freshly issued
     * by the page itself.
     *
     * @param  string $username Submitted username.
     * @param  string $password Submitted password.
     * @return array{status:int, body:string, content_type:string, location:string, headers:string}
     */
    private function postLoginWithFreshToken( string $username, string $password ): array
    {
        $form = $this->formSession( self::LOGIN );

        return $this->post(
            self::LOGIN,
            [
                'csrf'        => $form['csrf'],
                'redirect_to' => '',
                'username'    => $username,
                'password'    => $password,
            ],
            null,
            [],
            $form['session']
        );
    }
}
