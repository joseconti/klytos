<?php

/**
 * Klytos — a hook listener declared a by-reference parameter (Sprint 4, slice 1 / audit NEW-03).
 *
 * Thrown by Hooks::addAction() and Hooks::addFilter() when the callback being
 * registered declares a parameter by reference (`&$data`). It is refused at
 * REGISTRATION rather than at dispatch, so the failure arrives where the mistake
 * was made instead of at some later moment on some other request.
 *
 * WHY THIS IS A REFUSAL AND NOT A WARNING. Both dispatch paths pass their
 * arguments by value — doAction() collects them variadically and applyFilters()
 * spreads them — so PHP cannot bind a by-reference parameter to them. What it
 * does instead is worse than a failure: it emits a warning, runs the callback
 * body against a COPY, and discards the write. The listener looks registered, the
 * body demonstrably executes, and its effect silently does not exist. That is how
 * the x402 post-type default sat broken in every production install from adoption
 * to Sprint 4 while its code read as correct (audit NEW-03, L-005).
 *
 * WHAT TO DO INSTEAD. Use a filter and return the modified value. Actions are
 * fire-and-forget by design — they observe, they do not modify — which is what
 * the Hooks class docblock has always said and what is now enforced rather than
 * merely documented.
 *
 * BLAST RADIUS OF THE REFUSAL, stated because it is a breaking change:
 * PluginLoader::loadPlugin() wraps every plugin entry point in
 * try/catch (\Throwable) and records a named load error, so a third-party plugin
 * carrying such a listener fails to load with a readable reason instead of
 * taking the CMS down. Code registered directly by core is NOT covered by that
 * catch, which is why tests/Unit/HooksTest.php asserts core registers none.
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

namespace Klytos\Core;

class HookContractException extends \RuntimeException
{
    /**
     * @param string $message  Developer-facing message naming the hook, the parameter and the fix.
     * @param string $hookName The hook the refused callback was being registered on.
     * @param string $kind     'action' or 'filter' — which registry refused it.
     * @param string $location Absolute `file:line` where the callback is declared, or '' if unknown.
     */
    public function __construct(
        string $message,
        private string $hookName,
        private string $kind,
        private string $location
    ) {
        parent::__construct( $message );
    }

    /**
     * The hook name the refused callback was being registered on.
     *
     * @return string
     */
    public function getHookName(): string
    {
        return $this->hookName;
    }

    /**
     * Which registry refused the callback: 'action' or 'filter'.
     *
     * @return string
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * Where the refused callback is declared, as `file:line`.
     *
     * Empty when the callback is a built-in function, which has no source
     * location. Reported separately from the message so a caller can log or
     * display it without parsing prose.
     *
     * @return string
     */
    public function getCallbackLocation(): string
    {
        return $this->location;
    }
}
