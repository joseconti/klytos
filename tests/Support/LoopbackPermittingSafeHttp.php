<?php

/**
 * Klytos CMS — SafeHttp with IPv4 loopback treated as public (Sprint 1, slice 6).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Support;

use Klytos\Core\SafeHttp;

/**
 * SafeHttp permitting IPv4 loopback, and nothing else.
 *
 * WHY THIS EXISTS, stated plainly because a test double that weakens the
 * control it is testing has to justify itself: the case S-08's test point
 * requires is "a PUBLIC URL that 302-redirects to a PRIVATE one". A test suite
 * cannot own a public host, and a locally-served redirect starts at 127.0.0.1 —
 * which the real classifier refuses at hop zero, so the redirect would never be
 * reached and the test would prove nothing.
 *
 * The narrowing is therefore exactly one address, applied to exactly the first
 * hop's problem: 127.0.0.1 is treated as public so a local fixture server can
 * stand in for a public host. EVERY refusal the tests assert still comes from
 * the real, unmodified SafeHttp::isReservedAddress() — 169.254.169.254, ::1 and
 * the file:// scheme are refused by production logic, not by this subclass. The
 * redirect walk itself, which is the thing actually under test, is untouched.
 *
 * What is NOT faked: the socket, the HTTP request, the 302 status, the Location
 * header, the absolutization of a relative Location, and the hop limit.
 *
 * It lives under tests/Support/ rather than beside its test because PSR-1
 * allows one class per file, and the project's phpcs ruleset enforces it.
 */
final class LoopbackPermittingSafeHttp extends SafeHttp
{
    /**
     * Treat IPv4 loopback as public; defer everything else to the real check.
     *
     * @param  string $address IPv4 or IPv6 address.
     * @return bool
     */
    protected function isBlockedAddress( string $address ): bool
    {
        // ONLY IPv4 loopback, and only so a local fixture can play the part of
        // a public host. ::1 is deliberately NOT included — one of the tests
        // asserts that a redirect to it is refused, and that assertion would be
        // worthless if this method had already waved it through.
        if ( $address === '127.0.0.1' ) {
            return false;
        }

        return parent::isBlockedAddress( $address );
    }
}
