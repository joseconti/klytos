<?php

/**
 * Klytos CMS — redirect fixture server (Sprint 1, slice 6 / S-08).
 *
 * A `php -S` router that answers the redirect shapes SafeHttp must handle, so
 * the redirect half of S-08's test point is proven against a REAL socket, a
 * REAL 302 and a REAL Location header rather than a stubbed transport.
 *
 * It is a test fixture: it lives under tests/, is never loaded by the product,
 * and is not reachable from the playground router.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

$path = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) ?: '/';
$self = 'http://' . ( $_SERVER['HTTP_HOST'] ?? '127.0.0.1' );

switch ( $path ) {
    // The case the whole slice exists for: a host the caller was allowed to
    // reach, bouncing the request at the cloud metadata endpoint.
    case '/redirect-to-metadata':
        header( 'Location: http://169.254.169.254/latest/meta-data/', true, 302 );
        break;

    // Same shape, IPv6 loopback — proves the bracket handling in
    // SafeHttp::blockReason() is exercised on a redirect target too.
    case '/redirect-to-ipv6-loopback':
        header( 'Location: http://[::1]/admin', true, 302 );
        break;

    // A redirect that leaves HTTP entirely. cURL's CURLOPT_REDIR_PROTOCOLS is
    // never set anywhere in this repo, so nothing else would stop this.
    case '/redirect-to-file-scheme':
        header( 'Location: file:///etc/passwd', true, 302 );
        break;

    // Root-relative Location, to exercise absolutize() rather than assuming
    // every real-world redirect is absolute.
    case '/redirect-relative':
        header( 'Location: /final', true, 302 );
        break;

    // Query-only Location. RFC 3986 §5.3 keeps the base's PATH and replaces
    // only the query, so this must land on /deep/final?page=2 — not on
    // /deep/?page=2, which is what appending to the base directory produces.
    case '/deep/final':
        if ( ( $_SERVER['QUERY_STRING'] ?? '' ) === '' ) {
            header( 'Location: ?page=2', true, 302 );
            break;
        }

        header( 'Content-Type: text/plain' );
        echo 'QUERY-ONLY-OK';
        break;

    // Dot segments in a relative Location, for path normalization.
    case '/deep/dotted':
        header( 'Location: ../final', true, 302 );
        break;

    // An endless chain, for the hop limit.
    case '/redirect-loop':
        header( 'Location: ' . $self . '/redirect-loop', true, 302 );
        break;

    // POSITIVE CONTROL, and it is not optional (L-008): if the harness could
    // not follow a redirect successfully at all, every refusal above would pass
    // for entirely the wrong reason and the test would prove nothing.
    case '/final':
        header( 'Content-Type: text/plain' );
        echo 'FINAL-BODY';
        break;

    default:
        http_response_code( 404 );
        echo 'no such fixture route';
}
