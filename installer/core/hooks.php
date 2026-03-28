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
 * @since   2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
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
     *                           Receives whatever arguments doAction() passes.
     * @param int      $priority Execution order. Lower = earlier. Default: 10.
     * @return void
     */
    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
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
            return;
        }

        // Sort callbacks by priority (lower number runs first).
        $callbacks = self::$actions[$hook];
        usort($callbacks, fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

        // Execute each callback with the provided arguments.
        foreach ($callbacks as $entry) {
            call_user_func_array($entry['callback'], $args);
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
     * @param int      $priority Execution order. Lower = earlier. Default: 10.
     * @return void
     */
    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
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

        // Pass the value through each filter callback.
        // The first argument is always the value being filtered.
        foreach ($callbacks as $entry) {
            $value = call_user_func($entry['callback'], $value, ...$args);
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
