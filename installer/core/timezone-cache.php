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
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
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
