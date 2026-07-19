<?php

/**
 * Klytos CMS — SafeHttp redirect-hop validation (Sprint 1, slice 6 / S-08).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Core\SafeHttp;
use Klytos\Tests\AdminHttpTestCase;
use Klytos\Tests\Support\LoopbackPermittingSafeHttp;

/**
 * The half of S-08 that pre-flight validation cannot reach.
 *
 * Validating the URL the caller supplied is necessary and not sufficient: a
 * host that passes the check can answer 302 and send the request somewhere the
 * check would have refused. Every fetch in the product followed redirects with
 * cURL's CURLOPT_FOLLOWLOCATION (or PHP's http wrapper, which follows up to 20
 * by default) and re-validated none of them, so a single attacker-controlled
 * redirect defeated the entire control. This is the test that says it does not.
 */
final class SafeHttpRedirectTest extends AdminHttpTestCase
{
    protected static function serverPort(): int
    {
        // 8099 and 8100 belong to the two slice-4/5 HTTP classes.
        return 8102;
    }

    protected static function routerScript(): string
    {
        return dirname( KLYTOS_INSTALLER_PATH ) . '/tests/fixtures/redirect-server.php';
    }

    /**
     * Build a fixture URL on this class's server.
     */
    private function fixture( string $path ): string
    {
        return sprintf( 'http://%s:%d%s', self::HOST, static::serverPort(), $path );
    }

    private function http(): LoopbackPermittingSafeHttp
    {
        return new LoopbackPermittingSafeHttp();
    }

    /**
     * POSITIVE CONTROL — the harness can follow a redirect all the way through.
     *
     * Not optional (L-008). Every refusal below would pass identically against a
     * SafeHttp that simply failed on every redirect, or against a fixture server
     * that never answered at all. This test is what distinguishes "refused the
     * dangerous hop" from "cannot follow redirects".
     *
     * It also exercises the relative-Location path: the fixture answers
     * `Location: /final`, so absolutize() has to rebuild the absolute URL.
     */
    public function testFollowsARedirectThroughToItsDestination(): void
    {
        $result = $this->http()->fetch( $this->fixture( '/redirect-relative' ) );

        self::assertNull( $result['blocked'], 'A benign redirect must not be refused.' );
        self::assertSame( 200, $result['status'] );
        self::assertSame( 'FINAL-BODY', $result['body'] );
        self::assertSame(
            $this->fixture( '/final' ),
            $result['final_url'],
            'A root-relative Location was not absolutized against the URL that issued it.'
        );
    }

    /**
     * THE CASE THE SLICE EXISTS FOR: public URL, 302, cloud metadata endpoint.
     *
     * Proven to FAIL against the unfixed code before it was trusted — the old
     * oembed.php fetchUrl() set CURLOPT_FOLLOWLOCATION with MAXREDIRS 5, so it
     * fetched 169.254.169.254 without asking anyone. Evidence in
     * docs/05-test-points.md.
     */
    public function testRefusesAPublicUrlThatRedirectsToTheCloudMetadataEndpoint(): void
    {
        $result = $this->http()->fetch( $this->fixture( '/redirect-to-metadata' ) );

        self::assertSame(
            SafeHttp::REASON_BLOCKED_ADDRESS,
            $result['blocked'],
            'A 302 into 169.254.169.254 was followed instead of refused.'
        );
        self::assertSame( 'http://169.254.169.254/latest/meta-data/', $result['final_url'] );
        self::assertSame( 0, $result['status'] );
        self::assertSame( '', $result['body'], 'A refused hop must return no body to the caller.' );
    }

    /**
     * The same defect reached through the IPv6 loopback literal.
     *
     * parse_url() leaves the brackets on "[::1]", and both gethostbynamel() and
     * filter_var() reject the bracketed form — so without the strip in
     * blockReason() this would be refused as "unresolvable", which is the right
     * answer for the wrong reason and stops being the right answer the moment
     * anything about resolution changes.
     */
    public function testRefusesARedirectToTheIpv6LoopbackAddress(): void
    {
        $result = $this->http()->fetch( $this->fixture( '/redirect-to-ipv6-loopback' ) );

        self::assertSame( SafeHttp::REASON_BLOCKED_ADDRESS, $result['blocked'] );
        self::assertSame( 'http://[::1]/admin', $result['final_url'] );
    }

    /**
     * A redirect that leaves HTTP entirely.
     *
     * CURLOPT_REDIR_PROTOCOLS is set nowhere in this repository, so on the
     * transport alone nothing stopped a redirect from switching scheme.
     */
    public function testRefusesARedirectThatLeavesHttp(): void
    {
        $result = $this->http()->fetch( $this->fixture( '/redirect-to-file-scheme' ) );

        self::assertSame( SafeHttp::REASON_SCHEME, $result['blocked'] );
        self::assertSame( 'file:///etc/passwd', $result['final_url'] );
    }

    /**
     * An endless chain is abandoned rather than followed forever.
     */
    public function testAbandonsAnEndlessRedirectChain(): void
    {
        $result = $this->http()->fetch( $this->fixture( '/redirect-loop' ) );

        self::assertSame( SafeHttp::REASON_TOO_MANY_REDIRECTS, $result['blocked'] );
    }

    /**
     * A query-only Location keeps the base's path (RFC 3986 §5.3).
     *
     * Appending it to the base's DIRECTORY instead — which is what a naive
     * relative-path branch does — drops the last segment, so `/deep/final`
     * plus `?page=2` would resolve to `/deep/?page=2` and fetch the wrong
     * resource. Not a security bug (host and scheme are untouched), but a real
     * one, and it had no coverage until a review pass named it.
     */
    public function testAQueryOnlyLocationKeepsTheBasePath(): void
    {
        $result = $this->http()->fetch( $this->fixture( '/deep/final' ) );

        self::assertNull( $result['blocked'] );
        self::assertSame( 'QUERY-ONLY-OK', $result['body'] );
        self::assertSame( $this->fixture( '/deep/final?page=2' ), $result['final_url'] );
    }

    /**
     * Dot segments in a relative Location are normalized away.
     */
    public function testDotSegmentsInARelativeLocationAreResolved(): void
    {
        $result = $this->http()->fetch( $this->fixture( '/deep/dotted' ) );

        self::assertNull( $result['blocked'] );
        self::assertSame( 'FINAL-BODY', $result['body'] );
        self::assertSame(
            $this->fixture( '/final' ),
            $result['final_url'],
            'A "../" segment survived into the fetched URL.'
        );
    }

    /**
     * The hop limit is filterable, and lowering it actually bites.
     */
    public function testTheHopLimitIsFilterable(): void
    {
        // Temporary, so a filter that DISABLES redirect-following cannot leak
        // into a later test and make its refusal assertion pass for the wrong
        // reason. That leak was possible until this slice; see
        // IntegrationTestCase::$hookBaseline.
        $this->addTemporaryFilter( 'http.safe.max_redirects', static fn (): int => 0 );

        $result = $this->http()->fetch( $this->fixture( '/redirect-relative' ) );

        self::assertSame(
            SafeHttp::REASON_TOO_MANY_REDIRECTS,
            $result['blocked'],
            'The http.safe.max_redirects filter did not reach the redirect loop.'
        );
    }

    /**
     * Each hop is announced, so a chain is auditable rather than opaque.
     */
    public function testEachRedirectHopFiresItsAction(): void
    {
        $hops = [];

        $this->addTemporaryAction(
            'http.safe.redirect',
            static function ( string $from, string $to ) use ( &$hops ): void {
                $hops[] = $from . ' -> ' . $to;
            }
        );

        $this->http()->fetch( $this->fixture( '/redirect-to-metadata' ) );

        self::assertSame(
            [ $this->fixture( '/redirect-to-metadata' ) . ' -> http://169.254.169.254/latest/meta-data/' ],
            $hops
        );
    }
}
