<?php

/**
 * Klytos — SafeHttp
 * SSRF-resistant outbound HTTP for user- and AI-influenced URLs.
 *
 * @package Klytos
 * @since   0.31.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 Jose Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

/**
 * Fetches remote URLs that an untrusted party influenced, refusing any request
 * that would reach the host's own network.
 *
 * WHY THIS CLASS EXISTS RATHER THAN A CHECK AT EACH CALL SITE: the codebase had
 * exactly one real SSRF control — KlytosImporter\ImportValidator::validateUrl()
 * — reachable only from the importer plugin, while six core call sites fetched
 * user- or AI-influenced URLs behind nothing but filter_var(FILTER_VALIDATE_URL),
 * which happily accepts http://127.0.0.1/ and http://169.254.169.254/. That is
 * the S-07 shape (a rule everyone must remember at every new call site), and it
 * gets the S-07 answer: one implementation, and the unsafe path is the one that
 * takes extra typing. ImportValidator now delegates here, so there is ONE
 * implementation of "is this URL safe to fetch", not two free to drift apart.
 *
 * WHY REDIRECTS ARE FOLLOWED BY HAND: pre-flight validation of the URL the
 * caller supplied is necessary and NOT sufficient. A public host the check
 * allows can answer 302 with Location: http://169.254.169.254/, and cURL's
 * CURLOPT_FOLLOWLOCATION follows it without consulting anyone — so every
 * validated call site in the product was still one attacker-controlled redirect
 * away from the metadata endpoint. This class therefore disables the transport's
 * own redirect handling and walks the chain itself, re-validating every hop.
 * That is the case S-08's test point singles out, and it is the reason the class
 * is a fetcher rather than just a validator.
 *
 * KNOWN AND DELIBERATELY NOT CLOSED HERE — DNS rebinding: the host is resolved
 * to validate it and resolved again by the transport when it connects, so a
 * hostile nameserver answering with a short TTL can return a public address to
 * the first lookup and a private one to the second. Closing it means pinning the
 * validated address for the connection (CURLOPT_RESOLVE) and is recorded as
 * audit finding NEW-15 with its own test point; it is not folded in here because
 * it needs a verification of its own and this slice is already carrying the
 * redirect chain. Stated rather than buried: this class raises the cost of SSRF
 * substantially, it does not make it impossible.
 *
 * IPv6-ONLY HOSTS FAIL CLOSED: resolution uses gethostbynamel(), which returns
 * A records only, so a host with no IPv4 address is refused as unresolvable
 * rather than fetched unchecked. Refusing to fetch what cannot be verified is
 * the correct direction; it is documented because it is a behaviour, not a bug.
 */
class SafeHttp
{
    /** The URL could not be parsed, or carried no host. */
    public const REASON_MALFORMED = 'malformed_url';

    /** The scheme is outside the allow-list (file://, gopher://, ftp://…). */
    public const REASON_SCHEME = 'scheme_not_allowed';

    /** The host has no address this code can verify. */
    public const REASON_UNRESOLVABLE = 'host_does_not_resolve';

    /** The host resolves to a loopback, private, link-local or reserved address. */
    public const REASON_BLOCKED_ADDRESS = 'private_or_reserved_address';

    /** The redirect chain exceeded the hop limit. */
    public const REASON_TOO_MANY_REDIRECTS = 'too_many_redirects';

    /** Schemes that may be fetched at all. */
    private const DEFAULT_SCHEMES = [ 'http', 'https' ];

    /** Hops followed before the chain is abandoned. */
    private const DEFAULT_MAX_REDIRECTS = 5;

    /** Status codes that carry a Location worth following. */
    private const REDIRECT_STATUSES = [ 301, 302, 303, 307, 308 ];

    /** Transport. Injected so tests can drive a real server without a real DNS name. */
    private HttpClient $client;

    /**
     * @param HttpClient|null $client Transport, or null for the default client.
     */
    public function __construct( ?HttpClient $client = null )
    {
        $this->client = $client ?? new HttpClient();
    }

    /**
     * Whether this URL may be fetched.
     *
     * @param  string $url Absolute URL.
     * @return bool
     */
    public function isAllowed( string $url ): bool
    {
        return $this->blockReason( $url ) === null;
    }

    /**
     * Why this URL may not be fetched.
     *
     * Returns a REASON_* constant, or null when the URL is allowed. The reason
     * is for logs and callers, never for a response body: telling an untrusted
     * caller "that address is private" turns the refusal into an oracle that
     * maps the host's internal network one probe at a time.
     *
     * @param  string $url Absolute URL.
     * @return string|null REASON_* constant, or null when allowed.
     */
    public function blockReason( string $url ): ?string
    {
        $parsed = parse_url( $url );

        if ( $parsed === false || empty( $parsed['scheme'] ) ) {
            return self::REASON_MALFORMED;
        }

        /**
         * Filter the schemes SafeHttp will fetch.
         *
         * Widening this is a security decision with the same weight as the
         * admin.gate_map filter (D-032): a plugin CAN weaken a shipped control
         * here, exactly as it can there, because plugins already run as
         * first-party code in this product. What it cannot do is open a hole by
         * omission — the default is the allow-list, not the deny-list.
         *
         * @param string[] $schemes Lowercase scheme names.
         * @param string   $url     The URL being considered.
         */
        $schemes = (array) klytos_apply_filters( 'http.safe.allowed_schemes', self::DEFAULT_SCHEMES, $url );

        // Scheme BEFORE host, deliberately: file:///etc/passwd parses to a
        // scheme with no host, so a host-first order refuses it as "malformed"
        // — the right refusal for the wrong reason, and a wrong reason in a
        // security log teaches the next reader that the scheme allow-list is
        // doing work it is not. It is refused because file:// is not fetchable,
        // and that is what the reason must say.
        if ( ! in_array( strtolower( $parsed['scheme'] ), $schemes, true ) ) {
            return self::REASON_SCHEME;
        }

        if ( empty( $parsed['host'] ) ) {
            return self::REASON_MALFORMED;
        }

        // parse_url() keeps the brackets on an IPv6 literal ("[::1]"), and both
        // gethostbynamel() and filter_var() reject the bracketed form — so
        // without this strip, http://[::1]/ would be refused as "unresolvable"
        // rather than as the loopback address it plainly is. Same refusal,
        // wrong reason, and a wrong reason in a security log is how the next
        // reader concludes the control does something it does not.
        $host = trim( $parsed['host'], '[]' );

        $addresses = $this->resolveHost( $host );

        if ( $addresses === [] ) {
            return self::REASON_UNRESOLVABLE;
        }

        // EVERY address, not just the first: a host that resolves to one public
        // and one private address must be refused, because which one the
        // transport connects to is not this code's decision.
        foreach ( $addresses as $address ) {
            if ( $this->isBlockedAddress( $address ) ) {
                return self::REASON_BLOCKED_ADDRESS;
            }
        }

        return null;
    }

    /**
     * Fetch a URL, validating the original request and every redirect hop.
     *
     * @param  string              $url     Absolute URL.
     * @param  array<string,mixed> $options HttpClient options (timeout, headers, body…).
     *                                      'follow_redirects' and 'max_redirects' are
     *                                      handled here and ignored if passed.
     * @param  string              $method  HTTP method.
     * @return array{status:int, headers:array, body:string, error:?string, blocked:?string, final_url:string}
     *         'blocked' carries a REASON_* constant when the request was refused,
     *         and is null otherwise. A refused request has status 0 and an empty body.
     */
    public function fetch( string $url, array $options = [], string $method = 'GET' ): array
    {
        /**
         * Filter the number of redirect hops SafeHttp will follow.
         *
         * @param int    $max Hop limit.
         * @param string $url The URL the chain started at.
         */
        $maxRedirects = (int) klytos_apply_filters(
            'http.safe.max_redirects',
            self::DEFAULT_MAX_REDIRECTS,
            $url
        );

        // The transport must not follow anything itself — that is the whole
        // point of this loop. Passed explicitly rather than relied on as a
        // default, because a default is exactly what stopped being true when
        // somebody changed HttpClient.
        $options['follow_redirects'] = false;

        $current = $url;

        for ( $hop = 0; $hop <= $maxRedirects; $hop++ ) {
            $reason = $this->blockReason( $current );

            if ( $reason !== null ) {
                return $this->refusal( $reason, $current, $url );
            }

            $result = $this->client->request( $method, $current, $options );

            if ( $result['error'] !== null || ! in_array( $result['status'], self::REDIRECT_STATUSES, true ) ) {
                $result['blocked']   = null;
                $result['final_url'] = $current;

                return $result;
            }

            $location = (string) ( $result['headers']['location'] ?? '' );

            if ( $location === '' ) {
                // A redirect status with no Location is not a redirect anyone
                // can follow; hand it back as the final response rather than
                // inventing a destination for it.
                $result['blocked']   = null;
                $result['final_url'] = $current;

                return $result;
            }

            $next = $this->absolutize( $location, $current );

            /**
             * Fires for each redirect hop that is about to be considered.
             *
             * Precisely: it fires after the Location is absolutized and before
             * the hop is validated. It does NOT guarantee the hop was followed,
             * or even validated — the final iteration fires this and then hits
             * the hop limit, so a listener sees one more URL than was fetched.
             * Treat it as "a redirect was offered", not "a request was made".
             *
             * @param string $from Current URL.
             * @param string $to   Absolutized Location.
             */
            klytos_do_action( 'http.safe.redirect', $current, $next );

            $current = $next;

            // RFC 9110 §15.4: 301, 302 and 303 change the method to GET and
            // drop the body; 307 and 308 preserve both. cURL applies the same
            // rule, so a call site moving from CURLOPT_FOLLOWLOCATION to
            // SafeHttp keeps the behaviour it already had. Clearing the body
            // while leaving the method as POST — which is what a naive loop
            // does — would send a bodiless POST that no correct server expects.
            if ( in_array( $result['status'], [ 301, 302, 303 ], true ) && strtoupper( $method ) !== 'HEAD' ) {
                $method          = 'GET';
                $options['body'] = null;
            }
        }

        return $this->refusal( self::REASON_TOO_MANY_REDIRECTS, $current, $url );
    }

    /**
     * Resolve a host to its IPv4 addresses, or return the literal if it is one.
     *
     * Overridable so a test can drive a real HTTP server without owning a real
     * public DNS name. Production never overrides it.
     *
     * @param  string $host Hostname or IP literal, brackets already stripped.
     * @return string[] Addresses, empty when the host cannot be resolved.
     */
    protected function resolveHost( string $host ): array
    {
        // An IP literal needs no lookup, and asking DNS about one invites a
        // resolver that helpfully "searches" it into something else.
        if ( filter_var( $host, FILTER_VALIDATE_IP ) !== false ) {
            return [ $host ];
        }

        $addresses = gethostbynamel( $host );
        $addresses = $addresses === false ? [] : $addresses;

        // AAAA records too, and this is not thoroughness for its own sake:
        // gethostbynamel() returns A records ONLY, while the transport is
        // dual-stack (no CURLOPT_IPRESOLVE is set anywhere in this codebase).
        // A host publishing a public A record and a private AAAA record would
        // therefore pass a v4-only check and still be connected to over IPv6 —
        // a bypass needing no rebinding trick and no timing, just two DNS
        // records. Checking only the family that is convenient to look up is
        // how a control ends up protecting one half of the internet.
        if ( function_exists( 'dns_get_record' ) ) {
            foreach ( @dns_get_record( $host, DNS_AAAA ) ?: [] as $record ) {
                if ( isset( $record['ipv6'] ) ) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        return array_values( array_unique( $addresses ) );
    }

    /**
     * Whether an address is one this host must never be made to contact.
     *
     * Promoted verbatim from KlytosImporter\ImportValidator::isPrivateIp(), which
     * was the only working instance of this check in the product. Verified
     * against PHP 8.3 rather than assumed: NO_RES_RANGE covers 127.0.0.0/8,
     * ::1, 169.254.0.0/16, fe80::/10 and 0.0.0.0, and NO_PRIV_RANGE covers
     * 10/8, 172.16/12, 192.168/16 and fd00::/8.
     *
     * Overridable so the redirect test can permit loopback ONLY — narrowing the
     * classifier for one address family while every other refusal in the chain
     * still comes from this unmodified implementation.
     *
     * @param  string $address IPv4 or IPv6 address.
     * @return bool
     */
    protected function isBlockedAddress( string $address ): bool
    {
        return self::isReservedAddress( $address );
    }

    /**
     * Whether an address is loopback, private, link-local or otherwise reserved.
     *
     * The single implementation of this classification in the product. It is
     * public and static so KlytosImporter\ImportValidator::isPrivateIp() can
     * delegate to it rather than keep the second copy it used to own — the two
     * were identical, and two identical security checks are one drift away from
     * being two different security checks.
     *
     * @param  string $address IPv4 or IPv6 address.
     * @return bool
     */
    public static function isReservedAddress( string $address ): bool
    {
        // Normalize FIRST. filter_var's reserved-range flags do NOT understand
        // IPv4-mapped IPv6, and this is not a theoretical nicety: before this
        // step, `http://[::ffff:127.0.0.1]/` was ALLOWED by blockReason() and
        // verified to fetch loopback for real (HTTP 200, remote_ip
        // ::ffff:127.0.0.1). Every private address has such a spelling —
        // ::ffff:169.254.169.254 and ::ffff:10.0.0.1 both passed as public —
        // so the whole control was one notation away from being bypassable.
        // Found by TESTING the encodings rather than reasoning about the flags;
        // a review pass had judged this case "very likely already handled".
        $address = self::normalizeAddress( $address );

        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * Reduce an address to the form its reserved-range check understands.
     *
     * IPv4-mapped (`::ffff:127.0.0.1`, `::ffff:7f00:1`) and IPv4-compatible
     * (`::127.0.0.1`) IPv6 addresses are rewritten to their dotted-quad IPv4
     * form, because that is the form `FILTER_FLAG_NO_PRIV_RANGE` and
     * `FILTER_FLAG_NO_RES_RANGE` actually classify. Anything else is returned
     * unchanged.
     *
     * Works on the packed bytes rather than on the text, so every spelling of
     * the same address normalizes identically — `::ffff:127.0.0.1` and
     * `::ffff:7f00:1` are the same 16 bytes and must not be treated differently
     * just because they read differently.
     *
     * @param  string $address IPv4 or IPv6 address.
     * @return string Normalized address.
     */
    private static function normalizeAddress( string $address ): string
    {
        if ( filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) === false ) {
            return $address;
        }

        $packed = @inet_pton( $address );

        if ( $packed === false || strlen( $packed ) !== 16 ) {
            return $address;
        }

        // ::ffff:a.b.c.d — 80 zero bits, then 0xffff, then the IPv4 address.
        if ( str_starts_with( $packed, str_repeat( "\0", 10 ) . "\xff\xff" ) ) {
            return inet_ntop( substr( $packed, 12, 4 ) );
        }

        // ::a.b.c.d (deprecated IPv4-compatible), excluding :: and ::1, which
        // filter_var already classifies correctly as reserved.
        if ( str_starts_with( $packed, str_repeat( "\0", 12 ) ) && substr( $packed, 12, 4 ) > "\0\0\0\1" ) {
            return inet_ntop( substr( $packed, 12, 4 ) );
        }

        return $address;
    }

    /**
     * Resolve a Location header against the URL that produced it.
     *
     * @param  string $location Location header value.
     * @param  string $base     URL the redirect came from.
     * @return string Absolute URL.
     */
    private function absolutize( string $location, string $base ): string
    {
        $location = trim( $location );

        // Already absolute.
        if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $location ) === 1 ) {
            return $location;
        }

        $parts = parse_url( $base );

        if ( $parts === false || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return $location;
        }

        $scheme    = $parts['scheme'];
        $authority = $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );

        // Protocol-relative: //host/path
        if ( str_starts_with( $location, '//' ) ) {
            return $scheme . ':' . $location;
        }

        // Root-relative: /path
        if ( str_starts_with( $location, '/' ) ) {
            return $scheme . '://' . $authority . self::normalizePath( $location );
        }

        // Query-only: replaces the base's query and KEEPS its path (RFC 3986
        // §5.3). Falling through to the path-relative branch below would append
        // it to the base's directory instead, dropping the last path segment —
        // `/foo/bar?x=1` + `?page=2` would resolve to `/foo/?page=2`.
        if ( str_starts_with( $location, '?' ) ) {
            return $scheme . '://' . $authority . ( $parts['path'] ?? '/' ) . $location;
        }

        // Path-relative: resolved against the base's directory.
        $basePath  = $parts['path'] ?? '/';
        $directory = substr( $basePath, 0, (int) strrpos( $basePath, '/' ) + 1 );

        return $scheme . '://' . $authority . self::normalizePath(
            ( $directory === '' ? '/' : $directory ) . $location
        );
    }

    /**
     * Remove `.` and `..` segments from a path (RFC 3986 §5.2.4).
     *
     * Only the path is normalized; the authority is fixed by the caller, so
     * this cannot change which host is contacted. It exists for correctness —
     * a `..` left in place produces a URL the origin server may resolve
     * differently from what this code believes it requested, and a validator
     * that reasons about a different URL than the one sent is the shape of
     * defect NEW-15 already records.
     *
     * @param  string $path Path, possibly with a query string attached.
     * @return string
     */
    private static function normalizePath( string $path ): string
    {
        $query = '';

        if ( str_contains( $path, '?' ) ) {
            [ $path, $query ] = explode( '?', $path, 2 );
            $query            = '?' . $query;
        }

        $out = [];

        foreach ( explode( '/', $path ) as $segment ) {
            if ( $segment === '.' ) {
                continue;
            }

            if ( $segment === '..' ) {
                array_pop( $out );
                continue;
            }

            $out[] = $segment;
        }

        $normalized = implode( '/', $out );

        return ( str_starts_with( $normalized, '/' ) ? $normalized : '/' . $normalized ) . $query;
    }

    /**
     * Build the refusal response, log it and announce it.
     *
     * @param  string $reason  REASON_* constant.
     * @param  string $blocked The URL that was refused.
     * @param  string $origin  The URL the chain started at.
     * @return array{status:int, headers:array, body:string, error:?string, blocked:string, final_url:string}
     */
    private function refusal( string $reason, string $blocked, string $origin ): array
    {
        error_log(
            sprintf(
                'Klytos SafeHttp: refused %s (reason: %s, requested: %s)',
                $blocked,
                $reason,
                $origin
            )
        );

        /**
         * Fires when an outbound request is refused.
         *
         * Deliberately an action and not a filter: a listener that could turn a
         * refusal into a grant would put SSRF policy back in third-party hands,
         * which is the failure this class exists to close (same reasoning as
         * auth.access_denied in D-032).
         *
         * @param string $blocked The URL that was refused.
         * @param string $reason  REASON_* constant.
         * @param string $origin  The URL the chain started at.
         */
        klytos_do_action( 'http.safe.blocked', $blocked, $reason, $origin );

        return [
            'status'    => 0,
            'headers'   => [],
            'body'      => '',
            'error'     => 'Blocked by SafeHttp: ' . $reason,
            'blocked'   => $reason,
            'final_url' => $blocked,
        ];
    }
}
