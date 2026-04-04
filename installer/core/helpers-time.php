<?php

/**
 * Klytos — Time & Timezone Helper Functions
 *
 * Philosophy: store in UTC, display in local.
 *
 * - All internal storage uses UTC (ISO 8601 / Unix timestamps).
 * - The site's timezone (IANA string, e.g. 'Europe/Madrid') is read from config.
 * - IANA timezone strings handle DST automatically — no manual offsets needed.
 * - All functions use DateTimeImmutable for safety (no accidental mutation).
 *
 * @package Klytos
 * @since   0.25.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

// ─── Timezone Resolution ────────────────────────────────────────

/**
 * Get the site's timezone as a DateTimeZone object.
 *
 * Reads the 'timezone' key from config (IANA string like 'Europe/Madrid').
 * Falls back to UTC if the configured timezone is invalid or missing.
 *
 * Result is cached in-process so config is only read once per request.
 * Call klytos_timezone_reset_cache() after changing the config value.
 *
 * @return \DateTimeZone
 */
function klytos_timezone(): \DateTimeZone
{
    $cached = KlytosTimezoneCache::get();

    if ( $cached !== null ) {
        return $cached;
    }

    $tzString = klytos_timezone_string();

    try {
        $tz = new \DateTimeZone( $tzString );
    } catch ( \Exception ) {
        $tz = new \DateTimeZone( 'UTC' );
    }

    KlytosTimezoneCache::set( $tz );

    return $tz;
}

/**
 * Get the site's timezone as an IANA string.
 *
 * @return string IANA timezone identifier (e.g. 'Europe/Madrid', 'America/New_York').
 *                Returns 'UTC' if not configured or invalid.
 */
function klytos_timezone_string(): string
{
    $configured = klytos_config( 'timezone', 'UTC' );

    if ( !is_string( $configured ) || $configured === '' ) {
        return 'UTC';
    }

    return $configured;
}

/**
 * Get the current UTC offset of the site's timezone in seconds.
 *
 * This value changes automatically with DST transitions because it is
 * computed from the IANA timezone — not stored as a static offset.
 *
 * @return int Offset in seconds (e.g. 3600 for UTC+1, -18000 for UTC-5).
 */
function klytos_timezone_offset(): int
{
    return ( new \DateTimeImmutable( 'now', klytos_timezone() ) )->getOffset();
}

/**
 * Reset the cached timezone object.
 *
 * Call this after changing the 'timezone' config value so that
 * subsequent calls to klytos_timezone() pick up the new value.
 */
function klytos_timezone_reset_cache(): void
{
    KlytosTimezoneCache::reset();
}

// ─── Current Time ───────────────────────────────────────────────

/**
 * Get the current UTC timestamp in ISO 8601 format.
 *
 * This is the canonical function for all internal storage timestamps.
 *
 * @return string ISO 8601 timestamp (e.g. '2026-04-04T17:20:00+00:00').
 */
function klytos_now_utc(): string
{
    return ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->format( 'c' );
}

/**
 * Get the current local timestamp in ISO 8601 format.
 *
 * Uses the site's configured timezone. Useful for display, never for storage.
 *
 * @return string ISO 8601 timestamp in local timezone.
 */
function klytos_now_local(): string
{
    return ( new \DateTimeImmutable( 'now', klytos_timezone() ) )->format( 'c' );
}

/**
 * Get the current Unix timestamp.
 *
 * Wrapper around time() that fires a filter, allowing plugins to
 * override the clock (useful for testing scheduled actions, etc.).
 *
 * @return int Unix timestamp (always UTC-based by definition).
 */
function klytos_time(): int
{
    return (int) klytos_apply_filters( 'time.now', time() );
}

// ─── UTC Date Formatting ────────────────────────────────────────

/**
 * Format a Unix timestamp as a UTC date string.
 *
 * Always produces UTC output regardless of PHP's default timezone.
 * Use this instead of bare date() or gmdate() calls.
 *
 * @param  string   $format    PHP date format (e.g. 'Y-m-d H:i:s', 'c').
 * @param  int|null $timestamp Unix timestamp. Defaults to current time.
 * @return string Formatted date string in UTC.
 */
function klytos_gmdate( string $format, ?int $timestamp = null ): string
{
    $ts = $timestamp ?? klytos_time();

    return ( new \DateTimeImmutable( '@' . $ts ) )
        ->setTimezone( new \DateTimeZone( 'UTC' ) )
        ->format( $format );
}

// ─── Local Date Formatting ──────────────────────────────────────

/**
 * Format a Unix timestamp as a local date string.
 *
 * Converts to the site's configured timezone before formatting.
 * Use this for all user-facing date/time display.
 *
 * @param  string   $format    PHP date format (e.g. 'Y-m-d H:i:s', 'c').
 * @param  int|null $timestamp Unix timestamp. Defaults to current time.
 * @return string Formatted date string in site's local timezone.
 */
function klytos_date( string $format, ?int $timestamp = null ): string
{
    $ts = $timestamp ?? klytos_time();

    return ( new \DateTimeImmutable( '@' . $ts ) )
        ->setTimezone( klytos_timezone() )
        ->format( $format );
}

/**
 * Format an ISO 8601 (or MySQL-style) datetime string for display in local time.
 *
 * Parses the input (assumed UTC unless it contains a timezone offset),
 * converts to the site's timezone, and formats.
 *
 * @param  string $datetime ISO 8601 or 'Y-m-d H:i:s' datetime string.
 * @param  string $format   PHP date format for output. Default: 'Y-m-d H:i:s'.
 * @return string Formatted local datetime string.
 */
function klytos_format_datetime( string $datetime, string $format = 'Y-m-d H:i:s' ): string
{
    try {
        $dt = new \DateTimeImmutable( $datetime, new \DateTimeZone( 'UTC' ) );

        return $dt->setTimezone( klytos_timezone() )->format( $format );
    } catch ( \Exception ) {
        return $datetime;
    }
}

// ─── Timezone Conversions ───────────────────────────────────────

/**
 * Convert a UTC datetime string to the site's local timezone.
 *
 * @param  string $utcDatetime UTC datetime string (ISO 8601 or 'Y-m-d H:i:s').
 * @param  string $format      Output format. Default: ISO 8601 ('c').
 * @return string Datetime in local timezone.
 */
function klytos_utc_to_local( string $utcDatetime, string $format = 'c' ): string
{
    try {
        $dt = new \DateTimeImmutable( $utcDatetime, new \DateTimeZone( 'UTC' ) );

        return $dt->setTimezone( klytos_timezone() )->format( $format );
    } catch ( \Exception ) {
        return $utcDatetime;
    }
}

/**
 * Convert a local datetime string to UTC.
 *
 * @param  string $localDatetime Datetime string in site's local timezone.
 * @param  string $format        Output format. Default: ISO 8601 ('c').
 * @return string Datetime in UTC.
 */
function klytos_local_to_utc( string $localDatetime, string $format = 'c' ): string
{
    try {
        $dt = new \DateTimeImmutable( $localDatetime, klytos_timezone() );

        return $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( $format );
    } catch ( \Exception ) {
        return $localDatetime;
    }
}

// ─── Unix Timestamp Helpers ─────────────────────────────────────

/**
 * Convert an ISO 8601 (or MySQL) datetime string to a Unix timestamp.
 *
 * @param  string $datetime Datetime string (timezone-aware or assumed UTC).
 * @return int Unix timestamp, or 0 on parse failure.
 */
function klytos_datetime_to_timestamp( string $datetime ): int
{
    try {
        return ( new \DateTimeImmutable( $datetime, new \DateTimeZone( 'UTC' ) ) )
            ->getTimestamp();
    } catch ( \Exception ) {
        return 0;
    }
}

/**
 * Convert a Unix timestamp to an ISO 8601 UTC datetime string.
 *
 * @param  int $timestamp Unix timestamp.
 * @return string ISO 8601 datetime in UTC.
 */
function klytos_timestamp_to_datetime( int $timestamp ): string
{
    return ( new \DateTimeImmutable( '@' . $timestamp ) )
        ->setTimezone( new \DateTimeZone( 'UTC' ) )
        ->format( 'c' );
}

// ─── Timezone Listing (for admin UI) ────────────────────────────

/**
 * Get all valid IANA timezones grouped by continent.
 *
 * Returns an associative array suitable for building an HTML <select>
 * grouped with <optgroup> elements.
 *
 * Each entry includes the current UTC offset so the UI can display
 * something like "Europe/Madrid (UTC+02:00)".
 *
 * @return array<string, array<int, array{id: string, label: string, offset: string}>>
 */
function klytos_timezone_list(): array
{
    $filtered = klytos_apply_filters( 'time.timezone_list', null );

    if ( is_array( $filtered ) ) {
        return $filtered;
    }

    $identifiers = \DateTimeZone::listIdentifiers( \DateTimeZone::ALL );
    $now         = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
    $grouped     = [];

    // Continents we want to show.
    $validContinents = [
        'Africa', 'America', 'Antarctica', 'Arctic',
        'Asia', 'Atlantic', 'Australia', 'Europe',
        'Indian', 'Pacific', 'UTC',
    ];

    foreach ( $identifiers as $id ) {
        $parts = explode( '/', $id, 2 );

        if ( count( $parts ) < 2 ) {
            $continent = 'UTC';
            $city      = $id;
        } else {
            $continent = $parts[0];
            $city      = str_replace( [ '/', '_' ], [ ' / ', ' ' ], $parts[1] );
        }

        if ( !in_array( $continent, $validContinents, true ) ) {
            continue;
        }

        try {
            $tz     = new \DateTimeZone( $id );
            $offset = $tz->getOffset( $now );
            $hours  = intdiv( $offset, 3600 );
            $mins   = (int) ( abs( $offset % 3600 ) / 60 );
            $sign   = $offset >= 0 ? '+' : '-';
            $label  = sprintf(
                '%s (UTC%s%02d:%02d)',
                $city,
                $sign,
                abs( $hours ),
                $mins
            );
        } catch ( \Exception ) {
            continue;
        }

        $grouped[$continent][] = [
            'id'     => $id,
            'label'  => $label,
            'offset' => $sign . sprintf( '%02d:%02d', abs( $hours ), $mins ),
        ];
    }

    // Sort entries within each group alphabetically.
    foreach ( $grouped as &$entries ) {
        usort( $entries, fn( $a, $b ) => strcmp( $a['label'], $b['label'] ) );
    }
    unset( $entries );

    // Sort continents alphabetically, UTC last.
    uksort( $grouped, function ( $a, $b ) {
        if ( $a === 'UTC' ) {
            return 1;
        }
        if ( $b === 'UTC' ) {
            return -1;
        }
        return strcmp( $a, $b );
    } );

    return $grouped;
}
