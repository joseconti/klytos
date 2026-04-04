<?php

/**
 * Klytos — Timezone Cache
 *
 * In-process cache for the site's DateTimeZone object.
 * Using a class static so that klytos_timezone_reset_cache() can
 * invalidate it cleanly without reflection hacks.
 *
 * @package Klytos
 * @since   0.25.0
 * @internal Do not use directly — call klytos_timezone() instead.
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

/**
 * In-process timezone cache holder.
 *
 * @internal Do not use directly — call klytos_timezone() instead.
 */
final class KlytosTimezoneCache
{
    /** @var \DateTimeZone|null Cached timezone instance. */
    private static ?\DateTimeZone $tz = null;

    /**
     * Get the cached timezone.
     *
     * @return \DateTimeZone|null
     */
    public static function get(): ?\DateTimeZone
    {
        return self::$tz;
    }

    /**
     * Store a timezone in the cache.
     *
     * @param \DateTimeZone $tz Timezone to cache.
     */
    public static function set( \DateTimeZone $tz ): void
    {
        self::$tz = $tz;
    }

    /**
     * Clear the cached timezone.
     */
    public static function reset(): void
    {
        self::$tz = null;
    }
}
