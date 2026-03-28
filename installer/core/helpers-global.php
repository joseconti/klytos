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
 * @param  string $path     Relative path within the plugin's assets/ dir.
 * @return string Full URL to the plugin asset.
 */
function klytos_plugin_url(string $pluginId, string $path = ''): string
{
    $basePath = \Klytos\Core\Helpers::getBasePath();
    return $basePath . 'plugins/' . urlencode($pluginId) . '/assets/' . ltrim($path, '/');
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

    // In v2.0, this will return full user data from UserManager.
    // For now (v1.x compatibility), return basic info from config.
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

// ─── Logging ─────────────────────────────────────────────────

/**
 * Log a message to the Klytos log file.
 *
 * Log levels follow PSR-3: debug, info, notice, warning, error, critical.
 * Logs are written to data/logs/ as daily files (YYYY-MM-DD.log).
 *
 * @param string $level   Log level: 'debug', 'info', 'warning', 'error', 'critical'.
 * @param string $message Human-readable message.
 * @param array  $context Additional context data (logged as JSON).
 */
function klytos_log(string $level, string $message, array $context = []): void
{
    $validLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical'];
    if (!in_array($level, $validLevels, true)) {
        $level = 'info';
    }

    $logDir = App::getInstance()->getDataPath() . '/logs';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0700, true);
    }

    $logFile = $logDir . '/' . date('Y-m-d') . '.log';

    $entry = sprintf(
        "[%s] [%s] %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : ''
    );

    // Append with LOCK_EX for atomic writes.
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
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
