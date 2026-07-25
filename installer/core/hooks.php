<?php

/**
 * Klytos — Hook Engine
 * WordPress-inspired action/filter system for extensibility.
 *
 * This is the backbone of the Klytos plugin architecture. It allows plugins
 * to execute code at specific points (actions) and to modify data as it flows
 * through the system (filters) — all without touching core files.
 *
 * Concepts:
 * - **Action**: A hook that executes callbacks at a specific point in time.
 *   Example: 'page.after_save' fires after a page is saved.
 * - **Filter**: A hook that passes data through callbacks, each one can modify it.
 *   Example: 'page.content' allows plugins to modify page HTML before rendering.
 * - **Priority**: Lower number = runs first. Default is 10.
 *   Use 1-9 for "before most plugins", 10 for normal, 11-99 for "after most plugins".
 *
 * Usage:
 *   Hooks::addAction('page.after_save', function(array $page) { ... });
 *   Hooks::doAction('page.after_save', $page);
 *
 *   Hooks::addFilter('page.content', function(string $html) { return $html; });
 *   $html = Hooks::applyFilters('page.content', $html);
 *
 * Thread safety: This class is NOT thread-safe. PHP is single-threaded per request,
 * so this is not an issue in standard web server configurations.
 *
 * @package Klytos
 * @since   1.0.0
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

class Hooks
{
    /**
     * Registered action callbacks, keyed by hook name.
     * Structure: ['hook.name' => [['callback' => callable, 'priority' => int], ...]]
     *
     * @var array<string, array<int, array{callback: callable, priority: int}>>
     */
    private static array $actions = [];

    /**
     * Registered filter callbacks, keyed by hook name.
     * Same structure as $actions.
     *
     * @var array<string, array<int, array{callback: callable, priority: int}>>
     */
    private static array $filters = [];

    /**
     * Tracks which actions have been fired and how many times.
     * Useful for debugging: "was this hook actually triggered?"
     *
     * @var array<string, int>
     */
    private static array $actionsFired = [];

    /**
     * Optional profiler callback for DevBar.
     * Receives: (string $hookName, string $type, int $callbackCount, float $duration).
     *
     * @var \Closure|null
     */
    private static ?\Closure $profiler = null;

    /**
     * Set a profiler callback for measuring hook execution times.
     *
     * @param \Closure $fn Callback: function(string $hookName, string $type, int $count, float $duration)
     */
    public static function setProfiler( \Closure $fn ): void
    {
        self::$profiler = $fn;
    }

    // ─── Actions ─────────────────────────────────────────────────

    /**
     * Register a callback for an action hook.
     *
     * Actions are "fire and forget" — they execute code but don't return values.
     * Multiple callbacks can be registered for the same hook; they run in
     * priority order (lower number first).
     *
     * @param string   $hook     Hook name (e.g. 'page.after_save', 'build.before').
     * @param callable $callback Function to call when the action fires.
     *                           Receives whatever arguments doAction() passes,
     *                           BY VALUE. A by-reference parameter is refused.
     * @param int      $priority Execution order. Lower = earlier. Default: 10.
     * @return void
     * @throws HookContractException If $callback declares a by-reference parameter.
     */
    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        self::refuseByReferenceCallback( $hook, $callback, 'action' );

        self::$actions[$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];

        // Mark as unsorted so we re-sort on next execution.
        // This is more efficient than sorting on every add.
    }

    /**
     * Execute all callbacks registered for an action hook.
     *
     * Callbacks are sorted by priority (ascending) before execution.
     * All arguments after $hook are passed to each callback.
     *
     * @param string $hook Hook name to fire.
     * @param mixed  ...$args Arguments to pass to each callback.
     * @return void
     */
    public static function doAction(string $hook, mixed ...$args): void
    {
        // Track that this action was fired (for debugging/introspection).
        self::$actionsFired[$hook] = (self::$actionsFired[$hook] ?? 0) + 1;

        if (empty(self::$actions[$hook])) {
            // Report to profiler even if no callbacks (for visibility).
            if (self::$profiler !== null) {
                (self::$profiler)($hook, 'action', 0, 0.0);
            }
            return;
        }

        // Sort callbacks by priority (lower number runs first).
        $callbacks = self::$actions[$hook];
        usort($callbacks, fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

        $start = self::$profiler !== null ? microtime(true) : 0;

        // Execute each callback with the provided arguments.
        foreach ($callbacks as $entry) {
            call_user_func_array($entry['callback'], $args);
        }

        if (self::$profiler !== null) {
            (self::$profiler)($hook, 'action', count($callbacks), microtime(true) - $start);
        }
    }

    /**
     * Check if any callbacks are registered for an action hook.
     *
     * @param  string $hook Hook name.
     * @return bool   True if at least one callback is registered.
     */
    public static function hasAction(string $hook): bool
    {
        return !empty(self::$actions[$hook]);
    }

    /**
     * Remove a specific callback from an action hook.
     *
     * Compares callbacks by identity. For closures, you must pass
     * the exact same closure instance that was registered.
     *
     * @param string   $hook     Hook name.
     * @param callable $callback The exact callback to remove.
     * @return bool    True if the callback was found and removed.
     */
    public static function removeAction(string $hook, callable $callback): bool
    {
        return self::removeCallback(self::$actions, $hook, $callback);
    }

    /**
     * Remove ALL callbacks from an action hook.
     *
     * Use with caution — this removes callbacks from all plugins.
     *
     * @param string $hook Hook name.
     * @return void
     */
    public static function removeAllActions(string $hook): void
    {
        unset(self::$actions[$hook]);
    }

    /**
     * Check how many times an action has been fired in this request.
     *
     * @param  string $hook Hook name.
     * @return int    Number of times doAction() was called for this hook.
     */
    public static function didAction(string $hook): int
    {
        return self::$actionsFired[$hook] ?? 0;
    }

    // ─── Filters ─────────────────────────────────────────────────

    /**
     * Register a callback for a filter hook.
     *
     * Filters modify data: each callback receives a value, can modify it,
     * and MUST return the (modified or unmodified) value. The returned value
     * is passed to the next callback in the chain.
     *
     * @param string   $hook     Hook name (e.g. 'page.content', 'menu.items').
     * @param callable $callback Function to call. MUST return the filtered value.
     *                           First argument is the value to filter.
     *                           Additional arguments are context (read-only).
     *                           All are passed BY VALUE; a by-reference parameter
     *                           is refused — return the value instead.
     * @param int      $priority Execution order. Lower = earlier. Default: 10.
     * @return void
     * @throws HookContractException If $callback declares a by-reference parameter.
     */
    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        self::refuseByReferenceCallback( $hook, $callback, 'filter' );

        self::$filters[$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];
    }

    /**
     * Apply all filter callbacks to a value and return the result.
     *
     * The first argument ($value) is the data being filtered.
     * Each callback receives the current value (possibly modified by previous
     * callbacks) and must return it. Additional arguments are read-only context.
     *
     * If no filters are registered, the original value is returned unchanged.
     *
     * @param string $hook  Hook name.
     * @param mixed  $value The value to filter.
     * @param mixed  ...$args Additional read-only context arguments.
     * @return mixed The filtered value.
     */
    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (empty(self::$filters[$hook])) {
            return $value;
        }

        // Sort callbacks by priority (lower number runs first).
        $callbacks = self::$filters[$hook];
        usort($callbacks, fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

        $start = self::$profiler !== null ? microtime(true) : 0;

        // Pass the value through each filter callback.
        // The first argument is always the value being filtered.
        foreach ($callbacks as $entry) {
            $value = call_user_func($entry['callback'], $value, ...$args);
        }

        if (self::$profiler !== null) {
            (self::$profiler)($hook, 'filter', count($callbacks), microtime(true) - $start);
        }

        return $value;
    }

    /**
     * Check if any callbacks are registered for a filter hook.
     *
     * @param  string $hook Hook name.
     * @return bool   True if at least one callback is registered.
     */
    public static function hasFilter(string $hook): bool
    {
        return !empty(self::$filters[$hook]);
    }

    /**
     * Remove a specific callback from a filter hook.
     *
     * @param string   $hook     Hook name.
     * @param callable $callback The exact callback to remove.
     * @return bool    True if the callback was found and removed.
     */
    public static function removeFilter(string $hook, callable $callback): bool
    {
        return self::removeCallback(self::$filters, $hook, $callback);
    }

    /**
     * Remove ALL callbacks from a filter hook.
     *
     * @param string $hook Hook name.
     * @return void
     */
    public static function removeAllFilters(string $hook): void
    {
        unset(self::$filters[$hook]);
    }

    // ─── Debugging / Introspection ───────────────────────────────

    /**
     * Get a list of all registered hooks (actions + filters) and their callback counts.
     *
     * Useful for debugging and the admin "Hooks" panel.
     *
     * @return array ['actions' => ['hook.name' => count, ...], 'filters' => [...]]
     */
    public static function getRegisteredHooks(): array
    {
        $actions = [];
        foreach (self::$actions as $hook => $callbacks) {
            $actions[$hook] = count($callbacks);
        }

        $filters = [];
        foreach (self::$filters as $hook => $callbacks) {
            $filters[$hook] = count($callbacks);
        }

        return [
            'actions' => $actions,
            'filters' => $filters,
        ];
    }

    /**
     * Get all actions that have been fired in this request.
     *
     * @return array<string, int> Hook name => fire count.
     */
    public static function getFiredActions(): array
    {
        return self::$actionsFired;
    }

    /**
     * Reset all hooks and fired action tracking.
     *
     * Only used in testing. Never call this in production.
     *
     * @return void
     * @internal
     */
    public static function reset(): void
    {
        self::$actions      = [];
        self::$filters      = [];
        self::$actionsFired = [];
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Refuse a callback that declares a by-reference parameter.
     *
     * Both dispatch paths pass arguments BY VALUE — doAction() collects them
     * variadically (`mixed ...$args`) and applyFilters() spreads them into
     * call_user_func() — and PHP cannot bind a by-reference parameter to a value.
     * It does not fail cleanly either: it emits a warning, invokes the callback
     * against a COPY, and throws the write away. The listener therefore looks
     * registered and its body demonstrably runs, while its effect does not exist.
     *
     * That is audit NEW-03, and the reason it survived from adoption to Sprint 4
     * is precisely that nothing refused it — the defect is invisible in a diff and
     * only exists at dispatch. Refusing at REGISTRATION puts the failure where the
     * mistake is, with the file and line of the offending closure.
     *
     * Deliberately shared by addAction() and addFilter(): the filter path has the
     * same defect on its own arguments, no code relies on it, and leaving it
     * unchecked would preserve exactly the by-omission gap this method closes.
     *
     * By-reference CAPTURE (`use ( &$x )`) is a different mechanism, works
     * correctly, and is NOT affected — reflection reports parameters only.
     *
     * @param  string   $hook     Hook name being registered on.
     * @param  callable $callback The callback to inspect.
     * @param  string   $kind     'action' or 'filter', for the message.
     * @return void
     * @throws HookContractException If any parameter is declared by reference.
     */
    private static function refuseByReferenceCallback( string $hook, callable $callback, string $kind ): void
    {
        try {
            $reflection = is_array( $callback )
                ? new \ReflectionMethod( $callback[0], $callback[1] )
                : new \ReflectionFunction( \Closure::fromCallable( $callback ) );
        } catch ( \ReflectionException ) {
            // Not introspectable (an exotic callable shape). Registering it is
            // strictly better than refusing something that may be perfectly
            // valid: the contract is unchanged and the old behaviour applies.
            return;
        }

        foreach ( $reflection->getParameters() as $parameter ) {
            if ( ! $parameter->isPassedByReference() ) {
                continue;
            }

            $file     = $reflection->getFileName();
            $location = $file === false ? '' : $file . ':' . $reflection->getStartLine();

            // The MESSAGE carries only the basename, because it travels: a plugin
            // that trips this fails to load, and PluginLoader stores the message
            // in loadErrors, which the admin plugins page and the MCP tool
            // klytos_list_plugins both surface. Both are gated at plugins.manage,
            // so this is convention rather than a boundary — the convention being
            // plugin-loader.php's own "Missing entry point: basename(...)" two
            // lines from where this message lands. The absolute path stays
            // available, structured, through getCallbackLocation().
            $shortLocation = $file === false
                ? 'an unknown location'
                : basename( $file ) . ':' . $reflection->getStartLine();

            throw new HookContractException(
                sprintf(
                    'Hook "%s": the %s listener declared at %s takes parameter #%d ($%s) by '
                    . 'reference. Actions and filters pass their arguments by value, so the '
                    . 'change would be discarded silently. Register a filter instead and return '
                    . 'the modified value.',
                    $hook,
                    $kind,
                    $shortLocation,
                    $parameter->getPosition() + 1,
                    $parameter->getName()
                ),
                $hook,
                $kind,
                $location
            );
        }
    }

    /**
     * Remove a specific callback from a hook registry (actions or filters).
     *
     * @param  array    &$registry Reference to $actions or $filters.
     * @param  string   $hook      Hook name.
     * @param  callable $callback  The callback to remove.
     * @return bool     True if found and removed.
     */
    private static function removeCallback(array &$registry, string $hook, callable $callback): bool
    {
        if (empty($registry[$hook])) {
            return false;
        }

        $found = false;

        foreach ($registry[$hook] as $index => $entry) {
            if ($entry['callback'] === $callback) {
                unset($registry[$hook][$index]);
                $found = true;
                break;
            }
        }

        // Re-index the array after removal to prevent gaps.
        if ($found) {
            $registry[$hook] = array_values($registry[$hook]);
        }

        return $found;
    }
}
