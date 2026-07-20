<?php

/**
 * Klytos CMS — security headers on real responses (Sprint 1, slice 8).
 *
 * Covers audit S-11 (no HSTS), the CSP fail-open, and NEW-14 (the header
 * function was never CALLED on the admin API surfaces).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

/**
 * Asserts on the headers of REAL responses, never on the fact that
 * Auth::sendSecurityHeaders() was called.
 *
 * That distinction is the whole point of testing this over HTTP. A header set
 * after output has begun is not set at all — PHP emits a warning and carries
 * on — so a test that verified the call site would pass against code whose
 * headers never reach the client. Only the response can answer the question.
 *
 * NEW-14's shape, restated because it is what these tests pin: before slice 8
 * the function had six call sites repo-wide and NONE was an admin API
 * endpoint, so 0 of the 23 files in admin/api/ sent any header — while
 * login.php and logout.php, both admin PAGES, sent none either, because they
 * are two of the five pages that do not include templates/header.php. The
 * audit's "every admin page gets them via header.php" was wrong about those
 * two, and testAdminLoginPageSendsSecurityHeaders is why that is now on record.
 */
final class SecurityHeadersHttpTest extends AdminHttpTestCase
{
    /** 8099, 8100, 8101, 8102 and 8103 are taken by earlier slices' classes. */
    protected static function serverPort(): int
    {
        return 8104;
    }

    /**
     * The headers every admin response must carry, whatever its shape.
     *
     * @return array<string,string>
     */
    private function baselineHeaders(): array
    {
        return [
            'X-Content-Type-Options'  => 'nosniff',
            'X-Frame-Options'         => 'DENY',
            'Referrer-Policy'         => 'strict-origin-when-cross-origin',
            'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=()',
        ];
    }

    /**
     * Assert a response carries the full baseline set.
     *
     * @param  array<string,mixed> $response Response from request()/post().
     * @param  string              $context  Surface description for failures.
     * @return void
     */
    private function assertBaselineHeaders( array $response, string $context ): void
    {
        foreach ( $this->baselineHeaders() as $name => $expected ) {
            self::assertSame(
                $expected,
                $this->headerValue( $response, $name ),
                $context . ' must send ' . $name . ': ' . $expected
            );
        }

        self::assertNotSame(
            '',
            $this->headerValue( $response, 'Content-Security-Policy' ),
            $context . ' must send a Content-Security-Policy'
        );
    }

    // ─── NEW-14 — the surfaces that sent nothing at all ──────────

    /**
     * An admin API endpoint carries the headers. This is NEW-14's core claim:
     * 0 of 23 sent any before slice 8.
     */
    public function testAdminApiEndpointSendsSecurityHeaders(): void
    {
        $response = $this->request( 'installer/admin/api/notices.php', 'owner' );

        $this->assertBaselineHeaders( $response, 'An admin API endpoint' );
    }

    /**
     * A second, unrelated endpoint — so the first cannot pass by accident of
     * that one file happening to include something the others do not.
     */
    public function testASecondAdminApiEndpointSendsSecurityHeaders(): void
    {
        $response = $this->request( 'installer/admin/api/plugins.php', 'owner' );

        $this->assertBaselineHeaders( $response, 'A second admin API endpoint' );
    }

    /**
     * login.php — an admin PAGE that does not include templates/header.php and
     * never called sendSecurityHeaders() itself, so it ran with no CSP at all.
     */
    public function testAdminLoginPageSendsSecurityHeaders(): void
    {
        $response = $this->request( 'installer/admin/login.php', null );

        self::assertSame( 200, $response['status'], 'login.php should render for an anonymous caller' );
        $this->assertBaselineHeaders( $response, 'The login page' );
    }

    /**
     * A normal admin page, via templates/header.php. Guards the other
     * direction: the bootstrap change must not have BROKEN the surfaces that
     * already worked.
     */
    public function testAdminPageStillSendsSecurityHeaders(): void
    {
        $response = $this->request( 'installer/admin/index.php', 'owner' );

        self::assertSame( 200, $response['status'], 'the dashboard should render for the owner' );
        $this->assertBaselineHeaders( $response, 'The dashboard' );
    }

    // ─── Placement: refusals emit, so they prove the ordering ────

    /**
     * The anonymous 401 JSON refusal carries the headers.
     *
     * This is the ordering assertion. klytos_deny() and the auth guard both
     * write a body and exit, so if the header call sat anywhere below them in
     * bootstrap.php these headers would be missing — the trap slice 7 recorded
     * for rate limits, in its header form.
     */
    public function testAnonymousApiRefusalCarriesSecurityHeaders(): void
    {
        $response = $this->request( 'installer/admin/api/plugins.php', null );

        self::assertSame( 401, $response['status'], 'an anonymous API call must be refused 401' );
        $this->assertBaselineHeaders( $response, 'The 401 refusal' );
    }

    /**
     * The 403 gate refusal document carries the headers too — and it is an HTML
     * document, so its X-Frame-Options and CSP are load-bearing, not cosmetic.
     */
    public function testGateDenialPageCarriesSecurityHeaders(): void
    {
        $response = $this->request( 'installer/admin/users.php', 'viewer' );

        self::assertSame( 403, $response['status'], 'a viewer must be refused users.php' );
        $this->assertBaselineHeaders( $response, 'The 403 gate refusal' );
    }

    // ─── S-11 — HSTS ────────────────────────────────────────────

    /**
     * HSTS is NOT sent over cleartext.
     *
     * The playground speaks plain HTTP, so this is the half that can be
     * asserted end to end here; the HTTPS half is pinned by the unit tier
     * (tests/Unit/SecurityHeadersTest.php), which can set $_SERVER['HTTPS']
     * because php -S cannot terminate TLS. Asserting absence is not a
     * consolation prize: sending HSTS over http:// would be a claim the
     * transport cannot back, and browsers ignore it there anyway.
     */
    public function testHstsIsNotSentOverPlainHttp(): void
    {
        $response = $this->request( 'installer/admin/index.php', 'owner' );

        self::assertSame(
            '',
            $this->headerValue( $response, 'Strict-Transport-Security' ),
            'HSTS must not be sent over a cleartext connection'
        );
    }

    // ─── The CSP fail-open ──────────────────────────────────────

    /**
     * An admin page's script-src names a nonce and does NOT allow inline
     * script. Before slice 8 a caller that passed no nonce silently received
     * script-src 'self' 'unsafe-inline' — the weakest policy in the product,
     * with nothing to signal it.
     */
    public function testAdminCspNamesANonceAndForbidsInlineScript(): void
    {
        $response = $this->request( 'installer/admin/index.php', 'owner' );
        $csp      = $this->headerValue( $response, 'Content-Security-Policy' );

        self::assertMatchesRegularExpression(
            "/script-src [^;]*'nonce-/",
            $csp,
            'the admin CSP must carry a script nonce'
        );

        // Scoped to the script-src directive on purpose: style-src legitimately
        // keeps 'unsafe-inline' (S-10), so asserting on the whole policy string
        // would pass while script-src was wide open.
        preg_match( '/script-src ([^;]*)/', $csp, $matches );

        self::assertStringNotContainsString(
            "'unsafe-inline'",
            $matches[1] ?? '',
            "script-src must not allow 'unsafe-inline'"
        );
    }

    /**
     * The nonce in the CSP header is the SAME nonce the markup carries.
     *
     * Slice 8 moved nonce generation into bootstrap.php and made header.php
     * reuse it. If either minted its own, the header would name one value and
     * the page would emit another — every inline script would be refused, the
     * suite would stay green, and the breakage would only appear in a browser.
     * This is the assertion that catches that class of defect.
     */
    public function testTheCspNonceMatchesTheNonceInTheMarkup(): void
    {
        $response = $this->request( 'installer/admin/index.php', 'owner' );

        preg_match( "/'nonce-([^']+)'/", $this->headerValue( $response, 'Content-Security-Policy' ), $header );
        self::assertNotEmpty( $header[1] ?? '', 'the CSP header must name a nonce' );

        preg_match( '/<script[^>]+nonce="([^"]+)"/', $response['body'], $markup );
        self::assertNotEmpty( $markup[1] ?? '', 'the page must emit at least one nonced script' );

        self::assertSame(
            $header[1],
            $markup[1],
            'the nonce in the CSP header and the nonce in the markup must be the same value'
        );
    }

    /**
     * login.php's inline script carries the request's nonce.
     *
     * Directly load-bearing: this page had no CSP before slice 8, so its 2FA
     * method switcher ran unconditionally. Giving it a fail-closed CSP without
     * nonce-ing that block would have broken two-factor login — a regression
     * shipped by a security fix.
     */
    public function testLoginPageInlineScriptCarriesTheNonce(): void
    {
        $response = $this->request( 'installer/admin/login.php', null );

        preg_match( "/'nonce-([^']+)'/", $this->headerValue( $response, 'Content-Security-Policy' ), $header );
        self::assertNotEmpty( $header[1] ?? '', 'login.php must send a CSP naming a nonce' );

        // Every inline element the response actually renders must carry the
        // nonce named in the header it was sent with.
        //
        // Scoped to the elements themselves rather than searched for anywhere
        // in the body: the first version of this assertion looked for the
        // nonce string in the whole document and was PROVEN not to be
        // evidence — with the nonce stripped from the script tag it still
        // passed, because login.php's <style> block carries one too.
        preg_match_all( '/<(?:script|style)\b[^>]*>/i', $response['body'], $tags );

        $inline = array_filter(
            $tags[0] ?? [],
            static fn( string $tag ): bool => ! str_contains( $tag, ' src=' )
        );

        self::assertNotEmpty( $inline, 'login.php should render at least one inline script or style' );

        foreach ( $inline as $tag ) {
            self::assertStringContainsString(
                'nonce="' . $header[1] . '"',
                $tag,
                'every inline block on login.php must carry the nonce named in its own CSP header. '
                . 'Offending tag: ' . $tag
            );
        }
    }

    /**
     * No inline block anywhere in login.php's SOURCE lacks a nonce.
     *
     * A source-level assertion on purpose, because the response-level one
     * above cannot reach everything: login.php's 2FA method switcher
     * (the inline script this slice nonced) renders ONLY when a second factor
     * is pending, so a plain GET never emits it — which is exactly how the
     * first version of this test came to assert nothing about it.
     *
     * That conditional block is the one with real consequences: it is what
     * makes two-factor login usable, and under the fail-closed CSP an
     * un-nonced version would be silently refused by the browser. Reaching it
     * over HTTP would mean completing a password login, which only
     * config['admin_user'] can do at all (NEW-11). Reading the file is the
     * honest way to cover it, and it also catches the likelier future
     * regression: someone adding a new inline block to this page.
     */
    public function testLoginPageHasNoUnNoncedInlineBlockInItsSource(): void
    {
        $source = (string) file_get_contents(
            KLYTOS_INSTALLER_PATH . '/admin/login.php'
        );

        preg_match_all( '/<(?:script|style)\b[^>]*>/i', $source, $tags );

        foreach ( $tags[0] as $tag ) {
            if ( str_contains( $tag, ' src=' ) ) {
                continue;
            }

            self::assertStringContainsString(
                'nonce=',
                $tag,
                'login.php has an inline block with no nonce, which a fail-closed CSP will refuse. '
                . 'Offending tag: ' . $tag
            );
        }
    }

    /**
     * style-src still allows 'unsafe-inline' — deliberately (S-10).
     *
     * This asserts a WEAKNESS on purpose, and it is not a lowered bar. 349
     * inline style= attributes across 40 files cannot carry a nonce, and per
     * CSP Level 3 adding a nonce source to style-src makes browsers IGNORE
     * 'unsafe-inline' — so a well-meaning tightening here would break the
     * admin's rendering everywhere at once. The test exists so that removal is
     * a deliberate act by the slice that also converts those attributes, not a
     * side effect of someone tidying the policy string.
     */
    public function testStyleSrcStillAllowsInlineWhileS10IsOpen(): void
    {
        $response = $this->request( 'installer/admin/index.php', 'owner' );
        $csp      = $this->headerValue( $response, 'Content-Security-Policy' );

        preg_match( '/style-src ([^;]*)/', $csp, $matches );

        self::assertStringContainsString(
            "'unsafe-inline'",
            $matches[1] ?? '',
            "style-src must keep 'unsafe-inline' until S-10 converts the 349 inline style attributes"
        );
    }
}
