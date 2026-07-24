<?php

/**
 * Klytos — the AI stack cannot run on this PHP (Sprint 3, slice 2 / audit NEW-06).
 *
 * Thrown by App::getChatEngine() when the running PHP is older than the vendored
 * AI dependency tree requires, INSTEAD of letting the vendored autoloader be
 * required and fail on its own terms.
 *
 * WHY A TYPED EXCEPTION RATHER THAN LETTING COMPOSER'S CHECK FIRE. Composer
 * generates `vendor-ai/composer/platform_check.php` and `autoload_real.php`
 * requires it unconditionally, so on an unsupported runtime it (a) sends
 * `HTTP/1.1 500 Internal Server Error`, (b) echoes "Composer detected issues in
 * your platform" straight into the response body, and (c) throws a bare
 * \RuntimeException. All three happen inside a vendored file, before Klytos gets
 * a say: the operator sees a 500 with third-party text and no indication that AI
 * chat is the feature at fault or that the rest of the CMS is fine. Keel's
 * "external dependencies fail safe" rule requires the opposite — an absent or
 * version-incompatible dependency disables its feature with an explicit message
 * and never fatals the host.
 *
 * Catching this specific type is what lets a caller distinguish "this host
 * cannot run AI chat" from "the AI call failed", which a bare \RuntimeException
 * cannot express. All three call sites already wrap getChatEngine() in
 * try/catch (\Throwable), so they degrade without modification; a caller that
 * wants to say something more precise catches this type instead.
 *
 * @package Klytos
 * @since   0.32.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\Ai;

class UnsupportedRuntimeException extends \RuntimeException
{
    /**
     * @param string $message  The translated, operator-facing message.
     * @param int    $required The PHP_VERSION_ID the AI stack requires.
     * @param int    $running  The PHP_VERSION_ID actually running.
     */
    public function __construct(
        string $message,
        private int $required,
        private int $running
    ) {
        parent::__construct( $message );
    }

    /**
     * The PHP_VERSION_ID the vendored AI stack requires (e.g. 80300).
     *
     * @return int
     */
    public function getRequiredVersionId(): int
    {
        return $this->required;
    }

    /**
     * The PHP_VERSION_ID this host is actually running.
     *
     * @return int
     */
    public function getRunningVersionId(): int
    {
        return $this->running;
    }
}
