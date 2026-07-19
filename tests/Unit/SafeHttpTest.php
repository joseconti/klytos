<?php

/**
 * Klytos CMS — SafeHttp pre-flight validation (Sprint 1, slice 6 / S-08).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\SafeHttp;
use Klytos\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The addresses and schemes S-08's test point names, refused before a socket opens.
 *
 * These run in the unit tier because the decision is pure: parse, resolve,
 * classify. No App, no playground, no network — every literal address below is
 * classified without a DNS lookup (SafeHttp::resolveHost() short-circuits an IP
 * literal), so the tier stays hermetic and these tests cannot go red because a
 * resolver was slow.
 *
 * The redirect half of the test point is NOT here — it needs a real server
 * answering a real 302, and lives in Integration\SafeHttpRedirectTest.
 */
final class SafeHttpTest extends UnitTestCase
{
    private SafeHttp $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new SafeHttp();
    }

    /**
     * The four pre-flight cases the sprint file names, one per row.
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function refusedUrls(): array
    {
        return [
            // S-08's named cases.
            'IPv4 loopback'            => [ 'http://127.0.0.1/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'IPv6 loopback'            => [ 'http://[::1]/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'cloud metadata'           => [ 'http://169.254.169.254/latest/meta-data/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'non-HTTP scheme (file)'   => [ 'file:///etc/passwd', SafeHttp::REASON_SCHEME ],

            // Same class of defect, cases an attacker reaches for next.
            'loopback by another name' => [ 'http://127.0.0.53:53/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'RFC1918 10/8'             => [ 'http://10.0.0.1/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'RFC1918 192.168/16'       => [ 'http://192.168.1.1/admin', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'RFC1918 172.16/12'        => [ 'http://172.16.0.1/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'unspecified address'      => [ 'http://0.0.0.0/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'IPv6 unique-local'        => [ 'http://[fd00::1]/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'IPv6 link-local'          => [ 'http://[fe80::1]/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            // IPv4-mapped and IPv4-compatible IPv6. These were a LIVE BYPASS
            // until the normalization step was added: filter_var's
            // NO_PRIV_RANGE / NO_RES_RANGE flags do not understand the mapped
            // form at all, so `http://[::ffff:127.0.0.1]/` was allowed and was
            // verified to fetch loopback for real (HTTP 200 from
            // ::ffff:127.0.0.1). Every private address has such a spelling, so
            // this row set is the regression cover for the whole class — and
            // both hex and dotted spellings are here because they are the same
            // 16 bytes and must not be treated differently.
            'v4-mapped loopback'       => [ 'http://[::ffff:127.0.0.1]/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'v4-mapped loopback, hex'  => [ 'http://[::ffff:7f00:1]/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'v4-mapped metadata'       => [ 'http://[::ffff:169.254.169.254]/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'v4-mapped RFC1918'        => [ 'http://[::ffff:10.0.0.1]/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'v4-compatible loopback'   => [ 'http://[::127.0.0.1]/', SafeHttp::REASON_BLOCKED_ADDRESS ],

            // Alternative IPv4 notations. These already resolved correctly, but
            // they are pinned so a future change to resolveHost() cannot quietly
            // reopen them.
            'octal loopback'           => [ 'http://0177.0.0.1/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'decimal loopback'         => [ 'http://2130706433/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'hex loopback'             => [ 'http://0x7f000001/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'short-form loopback'      => [ 'http://127.1/', SafeHttp::REASON_BLOCKED_ADDRESS ],
            'userinfo disguise'        => [ 'http://expected.com@127.0.0.1/', SafeHttp::REASON_BLOCKED_ADDRESS ],

            'gopher scheme'            => [ 'gopher://127.0.0.1/', SafeHttp::REASON_SCHEME ],
            'ftp scheme'               => [ 'ftp://example.com/x', SafeHttp::REASON_SCHEME ],
            'no scheme at all'         => [ '/etc/passwd', SafeHttp::REASON_MALFORMED ],
            'empty string'             => [ '', SafeHttp::REASON_MALFORMED ],
        ];
    }

    #[DataProvider( 'refusedUrls' )]
    public function testRefusesUrlsThatWouldReachTheHostsOwnNetwork( string $url, string $expectedReason ): void
    {
        self::assertFalse(
            $this->http->isAllowed( $url ),
            sprintf( 'SafeHttp allowed %s, which S-08 exists to refuse.', $url )
        );

        // The REASON matters, not only the refusal. A URL refused for the wrong
        // reason is a control that will stop working the moment the wrong
        // reason stops applying — http://[::1]/ refused as "unresolvable"
        // instead of "loopback" is exactly that trap, and it is why the
        // bracket-stripping in blockReason() exists.
        self::assertSame(
            $expectedReason,
            $this->http->blockReason( $url ),
            sprintf( 'SafeHttp refused %s, but for the wrong reason.', $url )
        );
    }

    /**
     * The positive case, which is not optional (L-008).
     *
     * Every refusal above would also "pass" against a SafeHttp that refused
     * everything unconditionally. This is the test that says the class is a
     * filter and not a wall — and it is why the assertion is on a documentation
     * IP range rather than on a live third-party host: 192.0.2.0/24 (TEST-NET-1,
     * RFC 5737) is public, routable-looking and guaranteed never to be private,
     * so the check passes with no DNS lookup and no network dependency.
     */
    public function testAllowsAPublicHttpAddress(): void
    {
        self::assertNull( $this->http->blockReason( 'http://192.0.2.10/oembed' ) );
        self::assertTrue( $this->http->isAllowed( 'http://192.0.2.10/oembed' ) );
        self::assertTrue( $this->http->isAllowed( 'https://198.51.100.7/services/oembed?format=json' ) );

        // Public IPv6 too. The normalization added for the v4-mapped bypass
        // must not have turned the class into "refuses IPv6", which is the
        // obvious over-correction and would silently break every v6-only
        // provider.
        self::assertTrue(
            $this->http->isAllowed( 'http://[2606:4700:4700::1111]/' ),
            'A public IPv6 address was refused — the v4-mapped fix over-corrected.'
        );
    }

    /**
     * A host that does not resolve is refused rather than attempted.
     *
     * Fail-closed is the intended direction: SafeHttp cannot classify what it
     * cannot resolve, and "I could not check this" must never mean "go ahead".
     */
    public function testRefusesAHostThatCannotBeResolved(): void
    {
        // .invalid is reserved by RFC 2606 precisely so it can never resolve,
        // so this asserts a property rather than the state of somebody's DNS.
        self::assertSame(
            SafeHttp::REASON_UNRESOLVABLE,
            $this->http->blockReason( 'http://klytos-slice6.invalid/' )
        );
    }

    /**
     * The scheme allow-list is filterable, and the filter is reached.
     *
     * Recorded rather than merely permitted: this filter CAN weaken a shipped
     * security control, exactly as admin.gate_map can (D-032). The test pins
     * that it is wired, so the extension point is a documented surface and not
     * an accident.
     */
    public function testTheSchemeAllowListIsFilterable(): void
    {
        self::assertSame( SafeHttp::REASON_SCHEME, $this->http->blockReason( 'ftp://192.0.2.10/x' ) );

        klytos_add_filter( 'http.safe.allowed_schemes', static fn (): array => [ 'http', 'https', 'ftp' ] );

        self::assertNull(
            $this->http->blockReason( 'ftp://192.0.2.10/x' ),
            'The http.safe.allowed_schemes filter did not reach the scheme check.'
        );
    }

    /**
     * A refusal announces itself, so an operator can see SSRF attempts.
     */
    public function testARefusalFiresTheBlockedAction(): void
    {
        $seen = [];

        klytos_add_action(
            'http.safe.blocked',
            static function ( string $blocked, string $reason ) use ( &$seen ): void {
                $seen[] = [ $blocked, $reason ];
            }
        );

        $result = $this->http->fetch( 'http://169.254.169.254/latest/meta-data/' );

        self::assertSame( SafeHttp::REASON_BLOCKED_ADDRESS, $result['blocked'] );
        self::assertSame( 0, $result['status'] );
        self::assertSame( '', $result['body'], 'A refused request must return no body.' );
        self::assertCount( 1, $seen, 'The http.safe.blocked action did not fire.' );
        self::assertSame( 'http://169.254.169.254/latest/meta-data/', $seen[0][0] );
    }

    /**
     * The importer's validator and the core class are ONE implementation.
     *
     * If ImportValidator ever stops delegating, this fails — which is the point:
     * two copies of a security check are one edit away from being two different
     * security checks, and the copy nobody remembers is the one that rots.
     */
    public function testTheImporterValidatorDelegatesToSafeHttp(): void
    {
        require_once KLYTOS_INSTALLER_PATH . '/plugins/klytos-importer/src/ImportValidator.php';

        self::assertFalse( \KlytosImporter\ImportValidator::validateUrl( 'http://169.254.169.254/' ) );
        self::assertFalse( \KlytosImporter\ImportValidator::validateUrl( 'http://127.0.0.1/' ) );
        self::assertFalse( \KlytosImporter\ImportValidator::validateUrl( 'file:///etc/passwd' ) );
        self::assertTrue( \KlytosImporter\ImportValidator::validateUrl( 'https://192.0.2.10/sitemap.xml' ) );

        // The bracket bug the delegation fixed: the old body handed "[::1]" to
        // gethostbynamel(), which failed, so it refused as unresolvable.
        self::assertFalse( \KlytosImporter\ImportValidator::validateUrl( 'http://[::1]/' ) );

        self::assertTrue( \KlytosImporter\ImportValidator::isPrivateIp( '10.0.0.1' ) );
        self::assertFalse( \KlytosImporter\ImportValidator::isPrivateIp( '192.0.2.10' ) );
    }
}
