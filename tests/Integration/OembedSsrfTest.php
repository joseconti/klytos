<?php

/**
 * Klytos CMS — S-08, the oEmbed proxy refuses SSRF targets (Sprint 1, slice 6).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The finding as an attacker would reach it: over HTTP, as a real editor.
 *
 * SafeHttpTest proves the validator refuses these addresses. This proves the
 * ENDPOINT does — that the validator is actually wired into the request path
 * and not merely present in the tree. Slice 4 (S-07) and slice 5 both turned up
 * cases where a control existed and the surface did not call it, which is the
 * defect class this file exists to rule out for S-08.
 *
 * Assertions are on the BODY as well as the status (L-009): a PHP fatal on this
 * path returns HTTP 200 with the error rendered into the response, so a
 * status-only assertion can pass against completely broken code.
 */
final class OembedSsrfTest extends AdminHttpTestCase
{
    /** Slice 4 took 8099, slice 5 took 8100, the redirect fixture takes 8102. */
    protected static function serverPort(): int
    {
        return 8101;
    }

    /**
     * The addresses S-08's test point names, as query strings.
     *
     * @return array<string, array{0:string}>
     */
    public static function ssrfTargets(): array
    {
        return [
            'IPv4 loopback'  => [ 'http://127.0.0.1/' ],
            'IPv6 loopback'  => [ 'http://[::1]/' ],
            'cloud metadata' => [ 'http://169.254.169.254/latest/meta-data/' ],
            'file scheme'    => [ 'file:///etc/passwd' ],
            'gopher scheme'  => [ 'gopher://127.0.0.1:6379/' ],
            'private range'  => [ 'http://192.168.1.1/' ],
        ];
    }

    /**
     * Proven to FAIL against the unfixed code before being trusted: every row
     * below returned 404 "No oEmbed provider found" — which is what the endpoint
     * answers AFTER it has already fetched the address on the server's behalf.
     * Evidence in docs/05-test-points.md.
     */
    #[DataProvider( 'ssrfTargets' )]
    public function testS08OembedProxyRefusesAddressesOnTheServersOwnNetwork( string $target ): void
    {
        $response = $this->request(
            '/installer/admin/api/oembed.php?url=' . rawurlencode( $target ),
            'owner'
        );

        self::assertSame(
            400,
            $response['status'],
            sprintf( 'The oEmbed proxy did not refuse %s before fetching it.', $target )
        );

        // The refusal is deliberately indistinguishable from "malformed URL".
        // A distinct reply per reason would turn an authenticated editor into a
        // port scanner for the host's internal network, one request per probe.
        self::assertStringContainsString( 'Invalid URL', $response['body'] );

        // L-009: this path has thrown before, and a fatal here answers 200 with
        // the error in the body rather than a 500.
        self::assertStringNotContainsString( 'Fatal error', $response['body'] );
        self::assertStringNotContainsString( 'Uncaught Error', $response['body'] );
        self::assertStringNotContainsString( 'Call to undefined', $response['body'] );

        // Nothing from the target may reach the caller.
        self::assertStringNotContainsString( 'root:', $response['body'] );
        self::assertStringNotContainsString( 'ami-id', $response['body'] );
    }

    /**
     * THE POSITIVE CASE, which is not optional (L-008).
     *
     * Every refusal above would pass identically against an endpoint that
     * returned 400 for everything — including one broken by this slice. A URL
     * matching a hardcoded provider pattern must still be accepted and routed,
     * which means it must NOT come back as 400 "Invalid URL".
     *
     * The assertion is deliberately "not a validation refusal" rather than
     * "200": the playground has no outbound network guarantee, so the provider
     * fetch itself may legitimately fail with 502. What must never happen is
     * the request being rejected as invalid — that would mean the SSRF check
     * had swallowed a legitimate embed, and the editor's YouTube block would
     * silently stop working.
     */
    public function testAKnownProviderUrlIsStillAccepted(): void
    {
        $response = $this->request(
            '/installer/admin/api/oembed.php?url='
                . rawurlencode( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ),
            'owner'
        );

        self::assertNotSame(
            400,
            $response['status'],
            'A legitimate YouTube URL was refused as invalid — the SSRF check is over-broad '
            . 'and has broken the editor embed flow.'
        );

        self::assertStringNotContainsString( 'Fatal error', $response['body'] );
        self::assertStringNotContainsString( 'Call to undefined', $response['body'] );
    }

    /**
     * The endpoint remains behind the authorization gate slice 4 built.
     *
     * Guards against the classic regression where hardening one axis quietly
     * relaxes another: an SSRF fix that made this endpoint anonymous would
     * "pass" every assertion above.
     */
    public function testTheOembedProxyStillRequiresAuthentication(): void
    {
        $response = $this->request(
            '/installer/admin/api/oembed.php?url=' . rawurlencode( 'https://www.youtube.com/watch?v=x' ),
            null
        );

        self::assertContains(
            $response['status'],
            [ 302, 401, 403 ],
            'The oEmbed proxy answered an unauthenticated caller.'
        );
    }
}
