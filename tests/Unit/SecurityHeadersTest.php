<?php

/**
 * Klytos CMS — the header policy itself (Sprint 1, slice 8).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\Auth;
use Klytos\Core\Helpers;
use Klytos\Tests\UnitTestCase;

/**
 * The half of S-11 that a real HTTP response cannot reach.
 *
 * SecurityHeadersHttpTest asserts on real responses, which is the right way to
 * prove headers arrive — but the playground speaks plain HTTP and `php -S`
 * cannot terminate TLS, so the HTTPS branch of the HSTS decision has no real
 * response to be observed on. Here $_SERVER['HTTPS'] can simply be set.
 *
 * These tests drive Auth::buildSecurityHeaders(), NOT sendSecurityHeaders().
 * That split exists because of a false pass caught while writing this file:
 * the first version called sendSecurityHeaders() and read headers_list(), and
 * under the CLI SAPI header() is a no-op and headers_list() returns an empty
 * array — so every "the header is ABSENT" assertion passed against an empty
 * string and would have passed against any code whatsoever, including code
 * that set no headers at all. Three tests in this file are absence
 * assertions, so three of them were worthless. That is L-010's failure mode
 * (a check that cannot fail) and it was caught only because the PRESENCE
 * assertions in the same file failed loudly for the same underlying reason.
 */
final class SecurityHeadersTest extends UnitTestCase
{
    /**
     * Read one directive out of a CSP policy string.
     *
     * Scoped lookups matter here: style-src legitimately keeps 'unsafe-inline'
     * (S-10), so asserting on the whole policy string would pass happily while
     * script-src was wide open.
     *
     * @param  string $csp       Full policy.
     * @param  string $directive Directive name.
     * @return string The directive's value, or '' when absent.
     */
    private function directive( string $csp, string $directive ): string
    {
        preg_match( '/' . preg_quote( $directive, '/' ) . ' ([^;]*)/', $csp, $matches );

        return trim( $matches[1] ?? '' );
    }

    // ─── S-11 — HSTS ────────────────────────────────────────────

    public function testHstsIsSentOverHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';

        $headers = Auth::buildSecurityHeaders( 'test-nonce' );

        self::assertSame(
            'max-age=31536000',
            $headers['Strict-Transport-Security'] ?? '',
            'HSTS must be sent over TLS'
        );
    }

    /**
     * No includeSubDomains, by decision (D-044).
     *
     * A browser caches that directive for the full max-age, so it is not
     * something to opt an installed base into by default: a sibling subdomain
     * the operator runs on plain HTTP would become unreachable and they could
     * not easily undo it. Pinned so it cannot be "strengthened" without the
     * decision being reopened.
     */
    public function testHstsDoesNotClaimSubdomains(): void
    {
        $_SERVER['HTTPS'] = 'on';

        $headers = Auth::buildSecurityHeaders( 'test-nonce' );

        self::assertStringNotContainsString(
            'includeSubDomains',
            $headers['Strict-Transport-Security'] ?? '',
            'HSTS must not claim subdomains the operator never opted in'
        );
    }

    public function testHstsIsAbsentOverPlainHttp(): void
    {
        unset( $_SERVER['HTTPS'] );

        $headers = Auth::buildSecurityHeaders( 'test-nonce' );

        self::assertArrayNotHasKey(
            'Strict-Transport-Security',
            $headers,
            'HSTS must not be sent over cleartext'
        );
    }

    /**
     * 'off' is a real value Apache and IIS send; treating it as truthy would
     * make every cleartext request look secure.
     */
    public function testHstsIsAbsentWhenHttpsIsTheStringOff(): void
    {
        $_SERVER['HTTPS'] = 'off';

        self::assertArrayNotHasKey(
            'Strict-Transport-Security',
            Auth::buildSecurityHeaders( 'test-nonce' )
        );
        self::assertFalse( Helpers::isHttps(), "Helpers::isHttps() must read 'off' as not-secure" );
    }

    /**
     * The operator escape hatch. Filterable so an install that DOES want
     * preload or subdomains can have them without patching core.
     */
    public function testHstsIsFilterable(): void
    {
        $_SERVER['HTTPS'] = 'on';

        // Raw registration is correct in THIS tier: UnitTestCase calls
        // Hooks::reset() before and after every test (tests/UnitTestCase.php:62,67),
        // so nothing leaks. addTemporaryFilter() exists only on the integration
        // tier, which cannot reset (D-042).
        klytos_add_filter( 'security.hsts', static function (): string {
            return 'max-age=63072000; includeSubDomains; preload';
        } );

        $headers = Auth::buildSecurityHeaders( 'test-nonce' );

        self::assertSame(
            'max-age=63072000; includeSubDomains; preload',
            $headers['Strict-Transport-Security'] ?? ''
        );
    }

    // ─── The CSP fail-open ──────────────────────────────────────

    /**
     * A missing nonce must NOT relax the policy.
     *
     * This is the assertion that fails against the pre-slice-8 code, where the
     * identical call produced script-src 'self' 'unsafe-inline'.
     */
    public function testCspFailsClosedWhenNoNonceIsSupplied(): void
    {
        $scriptSrc = $this->directive(
            Auth::buildSecurityHeaders()['Content-Security-Policy'] ?? '',
            'script-src'
        );

        self::assertStringNotContainsString(
            "'unsafe-inline'",
            $scriptSrc,
            'a missing nonce must fail CLOSED, not fall back to unsafe-inline'
        );
        self::assertStringContainsString( "'self'", $scriptSrc );
    }

    public function testCspNamesTheNonceWhenOneIsSupplied(): void
    {
        $scriptSrc = $this->directive(
            Auth::buildSecurityHeaders( 'abc123' )['Content-Security-Policy'] ?? '',
            'script-src'
        );

        self::assertStringContainsString( "'nonce-abc123'", $scriptSrc );
        self::assertStringNotContainsString( "'unsafe-inline'", $scriptSrc );
    }

    /**
     * style-src keeps 'unsafe-inline' deliberately (S-10).
     *
     * Asserting a WEAKNESS on purpose. 349 inline style= attributes across 40
     * files cannot carry a nonce, and per CSP Level 3 adding a nonce source to
     * style-src makes browsers IGNORE 'unsafe-inline' — so a well-meaning
     * tightening here would break the admin's rendering everywhere at once.
     * This exists so that removal is a deliberate act by the slice that also
     * converts those attributes, not a side effect of tidying the policy.
     */
    public function testStyleSrcStillAllowsInlineWhileS10IsOpen(): void
    {
        $styleSrc = $this->directive(
            Auth::buildSecurityHeaders( 'abc123' )['Content-Security-Policy'] ?? '',
            'style-src'
        );

        self::assertStringContainsString(
            "'unsafe-inline'",
            $styleSrc,
            "style-src must keep 'unsafe-inline' until S-10 converts the 349 inline style attributes"
        );
    }

    /**
     * A custom policy replaces the default outright — the mechanism
     * installer/index.php uses to state the public site's weaker policy
     * explicitly at its call site rather than inheriting it by accident.
     */
    public function testACustomPolicyReplacesTheDefault(): void
    {
        $headers = Auth::buildSecurityHeaders( null, "default-src 'none'" );

        self::assertSame( "default-src 'none'", $headers['Content-Security-Policy'] ?? '' );
    }

    /**
     * The baseline set is present regardless of nonce or policy — these are the
     * headers NEW-14's 25 surfaces were missing entirely.
     */
    public function testTheBaselineHeadersAreAlwaysPresent(): void
    {
        $headers = Auth::buildSecurityHeaders();

        self::assertSame( 'nosniff', $headers['X-Content-Type-Options'] ?? '' );
        self::assertSame( 'DENY', $headers['X-Frame-Options'] ?? '' );
        self::assertSame( 'strict-origin-when-cross-origin', $headers['Referrer-Policy'] ?? '' );
        self::assertSame(
            'camera=(), microphone=(), geolocation=()',
            $headers['Permissions-Policy'] ?? ''
        );
    }
}
