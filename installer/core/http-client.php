<?php

/**
 * Klytos — HTTP Client
 * Consistent HTTP client for outbound requests (like WP wp_remote_get).
 *
 * @package Klytos
 * @since   0.26.0
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

class HttpClient
{
    private string $userAgent;

    public function __construct( string $userAgent = '' )
    {
        $this->userAgent = $userAgent ?: 'Klytos/' . ( defined( 'KLYTOS_VERSION' ) ? KLYTOS_VERSION : '1.0' );
    }

    /**
     * Perform a GET request.
     *
     * @param  string $url     Request URL.
     * @param  array  $headers Additional headers.
     * @param  array  $options Options: timeout, ssl_verify, follow_redirects, max_redirects.
     * @return array  ['status' => int, 'headers' => array, 'body' => string, 'error' => ?string]
     */
    public function get( string $url, array $headers = [], array $options = [] ): array
    {
        return $this->request( 'GET', $url, array_merge( $options, ['headers' => $headers] ) );
    }

    /**
     * Perform a POST request.
     *
     * @param  string      $url     Request URL.
     * @param  mixed       $body    Request body (string, array for JSON, or null).
     * @param  array       $headers Additional headers.
     * @param  array       $options Options.
     * @return array
     */
    public function post( string $url, mixed $body = null, array $headers = [], array $options = [] ): array
    {
        return $this->request( 'POST', $url, array_merge( $options, [
            'headers' => $headers,
            'body'    => $body,
        ]));
    }

    /**
     * Perform an HTTP request.
     *
     * @param  string $method  HTTP method (GET, POST, PUT, DELETE, etc.).
     * @param  string $url     Request URL.
     * @param  array  $options Options:
     *   - 'timeout'          => int (seconds, default 30)
     *   - 'ssl_verify'       => bool (default true)
     *   - 'follow_redirects' => bool (default true)
     *   - 'max_redirects'    => int (default 5)
     *   - 'headers'          => array of key=>value
     *   - 'body'             => string|array|null (array auto-JSON-encodes)
     * @return array  ['status' => int, 'headers' => array, 'body' => string, 'error' => ?string]
     */
    public function request( string $method, string $url, array $options = [] ): array
    {
        $timeout       = (int) ( $options['timeout'] ?? 30 );
        $sslVerify     = (bool) ( $options['ssl_verify'] ?? true );
        $followRedirs  = (bool) ( $options['follow_redirects'] ?? true );
        $maxRedirects  = (int) ( $options['max_redirects'] ?? 5 );
        $headers       = (array) ( $options['headers'] ?? [] );
        $body          = $options['body'] ?? null;

        // Auto-JSON-encode array bodies.
        if ( is_array( $body ) ) {
            $body = json_encode( $body );
            $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
        }

        // Apply before-request filter.
        [$method, $url, $options] = klytos_apply_filters( 'http.before_request', [$method, $url, $options] );

        $startTime = microtime( true );

        // Try cURL first, then fall back to stream context.
        if ( function_exists( 'curl_init' ) ) {
            $result = $this->requestWithCurl( $method, $url, $headers, $body, $timeout, $sslVerify, $followRedirs, $maxRedirects );
        } else {
            $result = $this->requestWithStream(
                $method,
                $url,
                $headers,
                $body,
                $timeout,
                $sslVerify,
                $followRedirs,
                $maxRedirects
            );
        }

        $durationMs = round( ( microtime( true ) - $startTime ) * 1000, 1 );

        klytos_do_action( 'http.after_request', $result, $method, $url, $durationMs );

        if ( $result['error'] !== null ) {
            klytos_do_action( 'http.error', $result['error'], $method, $url );
        }

        return $result;
    }

    /**
     * cURL-based request.
     */
    private function requestWithCurl(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout,
        bool $sslVerify,
        bool $followRedirs,
        int $maxRedirects
    ): array {
        $ch = curl_init();
        curl_setopt_array( $ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min( $timeout, 10 ),
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => $followRedirs,
            CURLOPT_MAXREDIRS      => $maxRedirects,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_CUSTOMREQUEST  => strtoupper( $method ),
        ]);

        // Build header array.
        $curlHeaders = [];
        foreach ( $headers as $key => $val ) {
            $curlHeaders[] = $key . ': ' . $val;
        }
        if ( !empty( $curlHeaders ) ) {
            curl_setopt( $ch, CURLOPT_HTTPHEADER, $curlHeaders );
        }

        if ( $body !== null ) {
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
        }

        $raw = curl_exec( $ch );

        if ( $raw === false ) {
            $error = curl_error( $ch );
            curl_close( $ch );
            return ['status' => 0, 'headers' => [], 'body' => '', 'error' => $error];
        }

        $status     = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $headerSize = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
        curl_close( $ch );

        $rawHeaders = substr( $raw, 0, $headerSize );
        $bodyStr    = substr( $raw, $headerSize );

        return [
            'status'  => $status,
            'headers' => $this->parseHeaders( $rawHeaders ),
            'body'    => $bodyStr,
            'error'   => null,
        ];
    }

    /**
     * Stream-context-based fallback.
     *
     * $followRedirs and $maxRedirects were previously accepted by request() and
     * then dropped on this path, which hardcoded follow_location => 1: a caller
     * that switched redirects OFF got them anyway whenever cURL was unavailable.
     * SafeHttp validates every hop itself and relies on the transport not moving
     * behind its back, so on a host without ext-curl that silent override would
     * have handed back exactly the SSRF the class exists to prevent.
     */
    private function requestWithStream(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout,
        bool $sslVerify,
        bool $followRedirs = true,
        int $maxRedirects = 5
    ): array {
        $headers['User-Agent'] = $headers['User-Agent'] ?? $this->userAgent;

        $headerStr = '';
        foreach ( $headers as $key => $val ) {
            $headerStr .= $key . ': ' . $val . "\r\n";
        }

        $contextOpts = [
            'http' => [
                'method'          => strtoupper( $method ),
                'header'          => $headerStr,
                'timeout'         => $timeout,
                'ignore_errors'   => true,
                'follow_location' => $followRedirs ? 1 : 0,
                'max_redirects'   => $maxRedirects,
            ],
            'ssl' => [
                'verify_peer'      => $sslVerify,
                'verify_peer_name' => $sslVerify,
            ],
        ];

        if ( $body !== null ) {
            $contextOpts['http']['content'] = $body;
        }

        $context  = stream_context_create( $contextOpts );
        $response = @file_get_contents( $url, false, $context );

        if ( $response === false ) {
            return ['status' => 0, 'headers' => [], 'body' => '', 'error' => 'Request failed'];
        }

        // Parse status from $http_response_header.
        $status         = 0;
        $responseHeaders = [];
        if ( isset( $http_response_header ) && is_array( $http_response_header ) ) {
            foreach ( $http_response_header as $line ) {
                if ( preg_match( '#^HTTP/[\d.]+ (\d+)#', $line, $m ) ) {
                    $status = (int) $m[1];
                } elseif ( str_contains( $line, ':' ) ) {
                    [$k, $v] = explode( ':', $line, 2 );
                    $responseHeaders[strtolower( trim( $k ) )] = trim( $v );
                }
            }
        }

        return [
            'status'  => $status,
            'headers' => $responseHeaders,
            'body'    => $response,
            'error'   => null,
        ];
    }

    /**
     * Parse raw HTTP headers string into associative array.
     */
    private function parseHeaders( string $raw ): array
    {
        $headers = [];
        foreach ( explode( "\r\n", trim( $raw ) ) as $line ) {
            if ( str_contains( $line, ':' ) ) {
                [$key, $val] = explode( ':', $line, 2 );
                $headers[strtolower( trim( $key ) )] = trim( $val );
            }
        }
        return $headers;
    }
}
