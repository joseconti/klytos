<?php

/**
 * Klytos — Global Helper Functions
 * Convenience wrappers for the Hook engine and core services.
 *
 * These functions provide a clean, WordPress-style API for plugin developers.
 * Instead of writing Hooks::addAction(...), plugins can write klytos_add_action(...).
 * Instead of accessing App::getInstance()->getStorage(), plugins use klytos_storage().
 *
 * All functions are prefixed with 'klytos_' to avoid naming collisions.
 *
 * This file is loaded by App::boot() BEFORE plugins are loaded,
 * so these functions are available in every plugin's init.php.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

use Klytos\Core\Hooks;
use Klytos\Core\App;

// ─── Hook Wrappers ───────────────────────────────────────────

/**
 * Register a callback for an action hook.
 *
 * @param string   $hook     Hook name (e.g. 'page.after_save').
 * @param callable $callback Function to execute when the hook fires.
 * @param int      $priority Execution order: lower = earlier. Default: 10.
 * @see   Hooks::addAction()
 */
function klytos_add_action(string $hook, callable $callback, int $priority = 10): void
{
    Hooks::addAction($hook, $callback, $priority);
}

/**
 * Fire an action hook, executing all registered callbacks.
 *
 * @param string $hook    Hook name.
 * @param mixed  ...$args Arguments passed to each callback.
 * @see   Hooks::doAction()
 */
function klytos_do_action(string $hook, mixed ...$args): void
{
    Hooks::doAction($hook, ...$args);
}

/**
 * Register a callback for a filter hook.
 *
 * @param string   $hook     Hook name (e.g. 'page.content').
 * @param callable $callback Function that receives, modifies, and returns the value.
 * @param int      $priority Execution order: lower = earlier. Default: 10.
 * @see   Hooks::addFilter()
 */
function klytos_add_filter(string $hook, callable $callback, int $priority = 10): void
{
    Hooks::addFilter($hook, $callback, $priority);
}

/**
 * Apply all registered filter callbacks to a value.
 *
 * @param  string $hook  Hook name.
 * @param  mixed  $value The value to filter through callbacks.
 * @param  mixed  ...$args Additional read-only context.
 * @return mixed  The filtered value.
 * @see    Hooks::applyFilters()
 */
function klytos_apply_filters(string $hook, mixed $value, mixed ...$args): mixed
{
    return Hooks::applyFilters($hook, $value, ...$args);
}

/**
 * Remove a callback from an action hook.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback The exact callback to remove.
 * @return bool
 */
function klytos_remove_action(string $hook, callable $callback): bool
{
    return Hooks::removeAction($hook, $callback);
}

/**
 * Remove a callback from a filter hook.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback The exact callback to remove.
 * @return bool
 */
function klytos_remove_filter(string $hook, callable $callback): bool
{
    return Hooks::removeFilter($hook, $callback);
}

/**
 * Check if any callbacks are registered for an action hook.
 *
 * @param  string $hook Hook name.
 * @return bool
 */
function klytos_has_action(string $hook): bool
{
    return Hooks::hasAction($hook);
}

/**
 * Check if any callbacks are registered for a filter hook.
 *
 * @param  string $hook Hook name.
 * @return bool
 */
function klytos_has_filter(string $hook): bool
{
    return Hooks::hasFilter($hook);
}

/**
 * Remove ALL callbacks from an action hook.
 *
 * Use with caution — this removes callbacks from all plugins.
 *
 * @param string $hook Hook name.
 */
function klytos_remove_all_actions( string $hook ): void
{
    Hooks::removeAllActions( $hook );
}

/**
 * Remove ALL callbacks from a filter hook.
 *
 * Use with caution — this removes callbacks from all plugins.
 *
 * @param string $hook Hook name.
 */
function klytos_remove_all_filters( string $hook ): void
{
    Hooks::removeAllFilters( $hook );
}

/**
 * Check how many times an action has been fired in this request.
 *
 * @param  string $hook Hook name.
 * @return int    Number of times doAction() was called for this hook.
 */
function klytos_did_action( string $hook ): int
{
    return Hooks::didAction( $hook );
}

/**
 * Get all actions that have been fired in this request.
 *
 * @return array<string, int> Hook name => fire count.
 */
function klytos_get_fired_actions(): array
{
    return Hooks::getFiredActions();
}

/**
 * Get a list of all registered hooks (actions + filters) and their callback counts.
 *
 * @return array ['actions' => ['hook.name' => count, ...], 'filters' => [...]]
 */
function klytos_get_registered_hooks(): array
{
    return Hooks::getRegisteredHooks();
}

// ─── Core Service Accessors ──────────────────────────────────

/**
 * Get the storage layer (FileStorage or DatabaseStorage).
 *
 * @return \Klytos\Core\StorageInterface
 */
function klytos_storage(): \Klytos\Core\StorageInterface
{
    return App::getInstance()->getStorage();
}

/**
 * Get the App singleton instance.
 *
 * @return App
 */
function klytos_app(): App
{
    return App::getInstance();
}

/**
 * Get the authentication manager.
 *
 * @return \Klytos\Core\Auth
 */
function klytos_auth(): \Klytos\Core\Auth
{
    return App::getInstance()->getAuth();
}

/**
 * Read a configuration value by dot-notation key.
 *
 * Examples:
 *   klytos_config('site_name') → 'My Site'
 *   klytos_config('admin_language', 'en') → 'es'
 *
 * @param  string $key     Dot-notation key (e.g. 'admin_language').
 * @param  mixed  $default Value to return if key not found.
 * @return mixed
 */
function klytos_config(string $key, mixed $default = null): mixed
{
    $config = App::getInstance()->getConfig();
    $parts  = explode('.', $key);
    $value  = $config;

    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

/**
 * Generate a full URL relative to the Klytos site root.
 *
 * @param  string $path Relative path (e.g. 'admin/settings.php').
 * @return string Full URL (e.g. 'https://example.com/klytos/admin/settings.php').
 */
function klytos_url(string $path = ''): string
{
    return \Klytos\Core\Helpers::url($path);
}

/**
 * Generate a full admin URL.
 *
 * @param  string $path Relative path from admin/ (e.g. 'plugins.php').
 * @return string Full URL to the admin page.
 */
function klytos_admin_url(string $path = ''): string
{
    return \Klytos\Core\Helpers::url('admin/' . ltrim($path, '/'));
}

/**
 * Get the public URL for a plugin's assets directory.
 *
 * @param  string $pluginId Plugin ID (from klytos-plugin.json).
 * @param  string $path     Relative path within the plugin directory (e.g. 'assets/css/style.css').
 * @return string Full URL to the plugin file.
 */
function klytos_plugin_url(string $pluginId, string $path = ''): string
{
    $basePath = \Klytos\Core\Helpers::getBasePath();
    $url = $basePath . 'plugins/' . urlencode($pluginId);
    if ($path !== '') {
        $url .= '/' . ltrim($path, '/');
    }
    return $url;
}

/**
 * Get the filesystem path for a plugin's directory.
 *
 * @param  string $pluginId Plugin ID.
 * @param  string $path     Relative path within the plugin directory.
 * @return string Absolute filesystem path.
 */
function klytos_plugin_path(string $pluginId, string $path = ''): string
{
    $rootPath = App::getInstance()->getRootPath();
    // Sanitize plugin ID to prevent directory traversal.
    $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $pluginId);
    return $rootPath . '/plugins/' . $safeId . ($path ? '/' . ltrim($path, '/') : '');
}

/**
 * Get a plugin's parsed header data (name, version, author, etc.).
 *
 * Returns the merged manifest: PHP header fields + klytos-plugin.json extension fields.
 * The PHP header is the canonical source for identity fields.
 *
 * @param  string $pluginId Plugin ID (directory name).
 * @return array  Plugin data, or empty array if not found.
 */
function klytos_get_plugin_data(string $pluginId): array
{
    $manifest = App::getInstance()->getPluginLoader()->getManifest($pluginId);
    return $manifest ?? [];
}

/**
 * Get the current Klytos version.
 *
 * @return string Semantic version (e.g. '0.4.2').
 */
function klytos_version(): string
{
    return KLYTOS_VERSION;
}

// ─── Context Checks ──────────────────────────────────────────

/**
 * Check if the current request is an admin panel request.
 *
 * @return bool True if running in the admin context.
 */
function klytos_is_admin(): bool
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    return str_contains($scriptName, '/admin/');
}

/**
 * Check if the current request is an MCP API request.
 *
 * @return bool True if the request targets the MCP endpoint.
 */
function klytos_is_mcp(): bool
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $queryRoute = $_GET['route'] ?? '';
    return str_contains($requestUri, '/mcp') || $queryRoute === 'mcp';
}

/**
 * Check if Klytos is running from the command line (CLI).
 *
 * @return bool True if running via php cli.php.
 */
function klytos_is_cli(): bool
{
    return php_sapi_name() === 'cli';
}

// ─── User & Permissions ──────────────────────────────────────

/**
 * Get the currently authenticated user, or null if not logged in.
 *
 * Returns an associative array with user data (id, username, role, email),
 * or null if no user is authenticated.
 *
 * @return array|null User data or null.
 */
function klytos_current_user(): ?array
{
    $auth = App::getInstance()->getAuth();

    if (!$auth->isAuthenticated()) {
        return null;
    }

    // v2.0: return full user data from UserManager when available.
    $userId = $auth->getUserId();

    if ($userId) {
        try {
            $userManager = new \Klytos\Core\UserManager(App::getInstance()->getStorage());
            return $userManager->getById($userId);
        } catch (\RuntimeException $e) {
            // Fall through to v1.x fallback.
        }
    }

    // v1.x fallback: basic info from config.
    $config = App::getInstance()->getConfig();
    return [
        'id'       => 'admin',
        'username' => $config['admin_user'] ?? 'admin',
        'role'     => 'owner',
        'email'    => $config['admin_email'] ?? '',
    ];
}

/**
 * Check if the current user has a specific permission.
 *
 * Permissions can be extended by plugins via the 'auth.capabilities' filter.
 *
 * @param  string $permission Permission key (e.g. 'pages.create', 'theme.manage').
 * @return bool   True if the current user has the permission.
 */
function klytos_has_permission(string $permission): bool
{
    $user = klytos_current_user();

    if ($user === null) {
        return false;
    }

    // Owner has all permissions.
    if ($user['role'] === 'owner') {
        return true;
    }

    // Default capabilities per role.
    $capabilities = [
        'pages.view'      => ['owner', 'admin', 'editor', 'viewer'],
        'pages.create'    => ['owner', 'admin', 'editor'],
        'pages.edit'      => ['owner', 'admin', 'editor'],
        'pages.delete'    => ['owner', 'admin'],
        'theme.manage'    => ['owner', 'admin'],
        'menu.manage'     => ['owner', 'admin'],
        'blocks.manage'   => ['owner', 'admin'],
        'templates.manage' => ['owner', 'admin'],
        'templates.approve' => ['owner'],
        'build.run'       => ['owner', 'admin'],
        'assets.manage'   => ['owner', 'admin', 'editor'],
        'tasks.create'    => ['owner', 'admin', 'editor'],
        'tasks.manage'    => ['owner', 'admin'],
        'users.manage'    => ['owner'],
        'mcp.manage'      => ['owner', 'admin'],
        'site.configure'  => ['owner', 'admin'],
        'plugins.manage'  => ['owner'],
        'analytics.view'  => ['owner', 'admin', 'editor'],
        'forms.manage'    => ['owner', 'admin'],
        'webhooks.manage' => ['owner', 'admin'],
        'updates.manage'  => ['owner'],
        'terminal.access'   => ['owner'],
    ];

    // Allow plugins to extend or modify capabilities.
    $capabilities = klytos_apply_filters('auth.capabilities', $capabilities);

    $allowedRoles = $capabilities[$permission] ?? [];

    return in_array($user['role'], $allowedRoles, true);
}

// ─── i18n ────────────────────────────────────────────────────

/**
 * Register a plugin's translation files directory.
 *
 * Plugins call this in their init.php to make their translations available.
 * Translation files should be named by locale: en.json, es.json, etc.
 *
 * @param string $pluginId Plugin ID (used as namespace prefix).
 * @param string $langDir  Absolute path to the plugin's lang/ directory.
 */
function klytos_register_translations(string $pluginId, string $langDir): void
{
    $i18n = App::getInstance()->getI18n();

    // Load the translation file for the current locale.
    $locale = klytos_config('admin_language', 'en');
    $file   = rtrim($langDir, '/') . '/' . $locale . '.json';

    if (file_exists($file)) {
        $content = file_get_contents($file);
        if ($content !== false) {
            $data = json_decode($content, true);
            if (is_array($data)) {
                // Merge plugin translations under the plugin's namespace.
                $i18n->mergeTranslations($pluginId, $data);
            }
        }
    }
}

// ─── Profiler ────────────────────────────────────────────────

/**
 * Set a profiler callback for measuring hook execution times.
 *
 * Used by DevBar to instrument the hook system when Developer Mode is active.
 *
 * @param \Closure $fn Callback: function(string $hookName, string $type, int $count, float $duration)
 */
function klytos_set_profiler( \Closure $fn ): void
{
    Hooks::setProfiler( $fn );
}

// ─── Logging ─────────────────────────────────────────────────

/**
 * Log a message to the Klytos debug log.
 *
 * Uses the centralized Logger which writes to the secret logs directory.
 * Logs are only written when Developer Mode is active.
 * For plugin sources, the plugin must also have logging enabled.
 *
 * Levels follow PSR-3: emergency, alert, critical, error, warning, notice, info, debug.
 *
 * @param string $level   PSR-3 log level.
 * @param string $message Human-readable message.
 * @param array  $context Additional context data (logged as JSON).
 * @param string $source  Source identifier: 'core' or a plugin ID.
 */
function klytos_log( string $level, string $message, array $context = [], string $source = 'core' ): void
{
    App::getInstance()->getLogger()->write( $level, $message, $context, $source );
}

/** Log an emergency message. */
function klytos_log_emergency( string $message, array $context = [], string $source = 'core' ): void
{
    klytos_log( 'emergency', $message, $context, $source );
}

/** Log an alert message. */
function klytos_log_alert( string $message, array $context = [], string $source = 'core' ): void
{
    klytos_log( 'alert', $message, $context, $source );
}

/** Log a critical message. */
function klytos_log_critical( string $message, array $context = [], string $source = 'core' ): void
{
    klytos_log( 'critical', $message, $context, $source );
}

/** Log an error message. */
function klytos_log_error( string $message, array $context = [], string $source = 'core' ): void
{
    klytos_log( 'error', $message, $context, $source );
}

/** Log a warning message. */
function klytos_log_warning( string $message, array $context = [], string $source = 'core' ): void
{
    klytos_log( 'warning', $message, $context, $source );
}

/** Log a notice message. */
function klytos_log_notice( string $message, array $context = [], string $source = 'core' ): void
{
    klytos_log( 'notice', $message, $context, $source );
}

/** Log an info message. */
function klytos_log_info( string $message, array $context = [], string $source = 'core' ): void
{
    klytos_log( 'info', $message, $context, $source );
}

/** Log a debug message. */
function klytos_log_debug( string $message, array $context = [], string $source = 'core' ): void
{
    klytos_log( 'debug', $message, $context, $source );
}

/**
 * Write or update a configuration value.
 *
 * This saves the value to the main config file. Use sparingly — most
 * plugin settings should use their own config files via klytos_storage().
 *
 * @param string $key   Configuration key (top-level only, no dot-notation).
 * @param mixed  $value Value to store.
 */
function klytos_set_config(string $key, mixed $value): void
{
    $app     = App::getInstance();
    $storage = $app->getStorage();
    $config  = $app->getConfig();

    $config[$key] = $value;

    $storage->writeTo($app->getConfigPath(), 'config.json.enc', $config);
}

// ─── Options API ─────────────────────────────────────────────
// Key-value storage for plugin and feature settings.
// The CMS decides the backend (files or database) transparently.

/**
 * Get an option value.
 *
 * @param  string $key     Option key (e.g. 'myplugin.theme_color').
 * @param  mixed  $default Value to return if the option does not exist.
 * @return mixed
 */
function klytos_get_option(string $key, mixed $default = null): mixed
{
    return App::getInstance()->getOptionsManager()->get($key, $default);
}

/**
 * Set (create or update) an option.
 *
 * @param string $key   Option key.
 * @param mixed  $value Value to store (must be JSON-serialisable).
 */
function klytos_set_option(string $key, mixed $value): void
{
    App::getInstance()->getOptionsManager()->set($key, $value);
}

/**
 * Delete an option.
 *
 * @param  string $key Option key.
 * @return bool   True if the option existed and was deleted.
 */
function klytos_delete_option(string $key): bool
{
    return App::getInstance()->getOptionsManager()->delete($key);
}

/**
 * Check if an option exists.
 *
 * @param  string $key Option key.
 * @return bool
 */
function klytos_option_exists(string $key): bool
{
    return App::getInstance()->getOptionsManager()->exists($key);
}

/**
 * Set an option with an explicit text domain.
 *
 * @param string $textDomain Text domain (e.g. 'my-plugin').
 * @param string $key        Option key.
 * @param mixed  $value      Value to store.
 */
function klytos_set_option_for(string $textDomain, string $key, mixed $value): void
{
    App::getInstance()->getOptionsManager()->set($key, $value, $textDomain);
}

/**
 * Get all options belonging to a text domain.
 *
 * @param  string $textDomain Text domain to filter by.
 * @return array<string, array> key => full record.
 */
function klytos_get_options_by_domain(string $textDomain): array
{
    return App::getInstance()->getOptionsManager()->getByTextDomain($textDomain);
}

/**
 * Delete all options belonging to a text domain.
 *
 * @param  string $textDomain Text domain to delete.
 * @return int    Number of options deleted.
 */
function klytos_delete_options_by_domain(string $textDomain): int
{
    return App::getInstance()->getOptionsManager()->deleteByTextDomain($textDomain);
}

// ─── Meta API ────────────────────────────────────────────────
// Attach arbitrary metadata to any entity (pages, users, post types, etc.).
// Meta is stored as a '_meta' field inside the entity document itself.

/**
 * Get a meta value for an entity.
 *
 * @param  string $collection Entity collection (e.g. 'pages', 'users').
 * @param  string $entityId   Entity identifier (e.g. page slug, user ID).
 * @param  string $key        Meta key (e.g. 'myplugin.custom_field').
 * @return mixed  The value, or null if the key does not exist.
 */
function klytos_get_meta(string $collection, string $entityId, string $key): mixed
{
    return App::getInstance()->getMetaManager()->get($collection, $entityId, $key);
}

/**
 * Set a meta value (create or replace).
 * The value can be any JSON-serialisable type: string, int, bool, array.
 *
 * @param string $collection Entity collection.
 * @param string $entityId   Entity identifier.
 * @param string $key        Meta key.
 * @param mixed  $value      Value to store.
 */
function klytos_set_meta(string $collection, string $entityId, string $key, mixed $value): void
{
    App::getInstance()->getMetaManager()->set($collection, $entityId, $key, $value);
}

/**
 * Delete a meta key from an entity.
 *
 * @param  string $collection Entity collection.
 * @param  string $entityId   Entity identifier.
 * @param  string $key        Meta key to remove.
 * @return bool   True if the key existed and was removed.
 */
function klytos_delete_meta(string $collection, string $entityId, string $key): bool
{
    return App::getInstance()->getMetaManager()->delete($collection, $entityId, $key);
}

/**
 * Get ALL meta for an entity as an associative array.
 *
 * @param  string $collection Entity collection.
 * @param  string $entityId   Entity identifier.
 * @return array  Associative array: meta_key => value.
 */
function klytos_get_all_meta(string $collection, string $entityId): array
{
    return App::getInstance()->getMetaManager()->getAll($collection, $entityId);
}

// ─── Action Scheduler API ───────────────────────────────────
// Schedule, cancel, and query scheduled actions.
// Actions are executed by the server's native cron or the fallback pseudo-cron.

/**
 * Schedule a one-time action.
 *
 * @param  int    $timestamp Unix timestamp when the action should run.
 * @param  string $hook      Hook name to fire on execution.
 * @param  array  $args      Arguments passed to the hook callbacks.
 * @param  string $group     Group name for organization (optional).
 * @return string The action ID.
 */
function klytos_schedule_single_action(int $timestamp, string $hook, array $args = [], string $group = ''): string
{
    return App::getInstance()->getActionScheduler()->scheduleSingle($timestamp, $hook, $args, $group);
}

/**
 * Schedule a recurring action.
 *
 * @param  int    $timestamp       Unix timestamp for the first run.
 * @param  int    $intervalSeconds Seconds between recurring runs (minimum 60).
 * @param  string $hook            Hook name to fire on execution.
 * @param  array  $args            Arguments passed to the hook callbacks.
 * @param  string $group           Group name for organization (optional).
 * @return string The action ID.
 */
function klytos_schedule_recurring_action(int $timestamp, int $intervalSeconds, string $hook, array $args = [], string $group = ''): string
{
    return App::getInstance()->getActionScheduler()->scheduleRecurring($timestamp, $intervalSeconds, $hook, $args, $group);
}

/**
 * Cancel a scheduled action by ID.
 *
 * @param  string $actionId Action ID.
 * @return bool   True if the action was canceled.
 */
function klytos_cancel_scheduled_action(string $actionId): bool
{
    return App::getInstance()->getActionScheduler()->cancel($actionId);
}

/**
 * Unschedule all pending actions for a hook.
 *
 * @param  string $hook  Hook name.
 * @param  array  $args  Arguments to match (empty = any).
 * @param  string $group Group to match (empty = any).
 * @return int    Number of actions unscheduled.
 */
function klytos_unschedule_all_actions(string $hook, array $args = [], string $group = ''): int
{
    return App::getInstance()->getActionScheduler()->unscheduleAll($hook, $args, $group);
}

/**
 * Get the Unix timestamp of the next scheduled action for a hook.
 *
 * @param  string   $hook  Hook name.
 * @param  array    $args  Arguments to match (empty = any).
 * @param  string   $group Group to match (empty = any).
 * @return int|null Unix timestamp, or null if not scheduled.
 */
function klytos_next_scheduled_action(string $hook, array $args = [], string $group = ''): ?int
{
    return App::getInstance()->getActionScheduler()->nextScheduled($hook, $args, $group);
}

/**
 * Check if an action is currently scheduled (pending) for a hook.
 *
 * @param  string $hook  Hook name.
 * @param  array  $args  Arguments to match (empty = any).
 * @param  string $group Group to match (empty = any).
 * @return bool
 */
function klytos_is_scheduled_action(string $hook, array $args = [], string $group = ''): bool
{
    return App::getInstance()->getActionScheduler()->isScheduled($hook, $args, $group);
}

// ─── Routing API ──────────────────────────────────────────────

/**
 * Register a dynamic route from a plugin.
 *
 * @param string $pattern URL pattern (e.g. '/cart', '/account/{section}').
 * @param array  $config  Route configuration:
 *   - 'callback'   (required): callable. Receives array $params, returns string|array.
 *   - 'type'       (required): 'page', 'api', or 'webhook'.
 *   - 'method'     (optional): 'GET', 'POST', 'GET|POST'. Default: 'GET'.
 *   - 'template'   (optional): Template name for type 'page'. Default: 'default'.
 *   - 'title'      (optional): Page title for type 'page'. Default: ''.
 *   - 'auth'       (optional): false | 'frontend' | 'admin'. Default: false.
 *   - 'capability' (optional): Permission string. Default: null.
 *
 * @see \Klytos\Core\RouteManager::register()
 */
function klytos_register_route( string $pattern, array $config ): void
{
    App::getInstance()->getRouteManager()->register( $pattern, $config );
}

/**
 * Register a plugin admin page.
 *
 * This is a convenience wrapper that adds a sidebar menu item pointing to
 * admin/plugin-page.php?plugin={id}&page={page}. The actual PHP file
 * must exist at plugins/{pluginId}/admin/{pageId}.php.
 *
 * @param string $pluginId Plugin ID.
 * @param array  $page     Page definition:
 *   - 'id'         (required): Page identifier (maps to admin/{id}.php in the plugin).
 *   - 'title'      (required): Menu item title.
 *   - 'icon'       (optional): Emoji or SVG for the sidebar.
 *   - 'position'   (optional): Sidebar position (85-89 = plugin zone).
 *   - 'capability' (optional): Required permission.
 *   - 'children'   (optional): Sub-pages array [{id, title, capability}].
 */
function klytos_register_admin_page( string $pluginId, array $page ): void
{
    $pageId = $page['id'] ?? '';
    if ( empty( $pageId ) ) {
        return;
    }

    $baseUrl = klytos_admin_url( 'plugin-page.php?plugin=' . urlencode( $pluginId ) . '&page=' );

    $item = [
        'id'         => $pluginId . '-' . $pageId,
        'title'      => $page['title'] ?? $pageId,
        'url'        => $baseUrl . urlencode( $pageId ),
        'icon'       => $page['icon'] ?? '🔌',
        'position'   => $page['position'] ?? 86,
        'capability' => $page['capability'] ?? null,
        'children'   => [],
    ];

    foreach ( ( $page['children'] ?? [] ) as $child ) {
        $childId = $child['id'] ?? '';
        if ( empty( $childId ) ) {
            continue;
        }
        $item['children'][] = [
            'id'         => $pluginId . '-' . $childId,
            'title'      => $child['title'] ?? $childId,
            'url'        => $baseUrl . urlencode( $childId ),
            'capability' => $child['capability'] ?? $item['capability'],
        ];
    }

    klytos_add_filter( 'admin.sidebar_items', function ( array $items ) use ( $item ): array {
        $items[] = $item;
        return $items;
    } );
}

// ─── Template API ──────────────────────────────────────────────

/**
 * Register templates provided by a plugin.
 *
 * @param string $pluginId  Plugin ID.
 * @param array  $templates Array of templates: name => [name, description, file, dynamic, post_type].
 */
function klytos_register_templates(string $pluginId, array $templates): void
{
    App::getInstance()->getTemplateResolver()->registerPluginTemplates($pluginId, $templates);
}

/**
 * Register or modify a template part via filter.
 *
 * @param string   $partName Part name (e.g. 'header').
 * @param callable $callback Function that receives the current HTML and returns the modified HTML.
 * @param int      $priority Execution priority (lower = earlier).
 */
function klytos_register_template_part(string $partName, callable $callback, int $priority = 10): void
{
    klytos_add_filter( 'template_part.' . $partName, $callback, $priority );
}

// ─── Admin Page Detection ─────────────────────────────────────

/**
 * Get the current admin page identifier.
 *
 * Returns the basename of the current admin script without `.php`,
 * e.g. 'settings', 'users', 'dashboard' (index maps to 'dashboard').
 *
 * @return string Current admin page name, or empty string if not in admin.
 * @since  0.16.0
 */
function klytos_current_admin_page(): string
{
    return $GLOBALS['klytos_admin_page'] ?? '';
}

/**
 * Check whether the current admin page matches the given identifier.
 *
 * Supports exact match ('settings') and prefix match ('settings.*')
 * via wildcard at the end.
 *
 * @param string $page Page identifier to check against.
 * @return bool
 * @since  0.16.0
 */
function klytos_is_admin_page(string $page): bool
{
    $current = $GLOBALS['klytos_admin_page'] ?? '';
    if ($current === '') {
        return false;
    }
    if (str_ends_with($page, '.*')) {
        return str_starts_with($current, substr($page, 0, -1));
    }
    return $current === $page;
}
