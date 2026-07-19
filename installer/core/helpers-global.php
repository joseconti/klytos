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
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
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

    $userId = $auth->getUserId();

    // FAIL CLOSED (NEW-01, D-021). This function used to fall back here to a
    // hardcoded ['role' => 'owner'] built from config whenever the session had
    // no klytos_user_id or the lookup failed. That silently promoted ANY
    // authenticated session lacking a user id to owner, which defeated every
    // permission gate in the product rather than merely weakening one — a gate
    // is only as good as the identity it is handed.
    //
    // An unidentifiable session is now denied. The compensating path for real
    // v1.x installs is the migration at boot (app.php Step 10b), which creates
    // the owner record this lookup then finds; it is idempotent and runs before
    // any request is served, so a migrated install never reaches this branch.
    if ( ! $userId ) {
        klytos_log_warning(
            'Authenticated session without klytos_user_id — denied.',
            [ 'username' => $_SESSION['klytos_user'] ?? null ],
            'auth'
        );

        return null;
    }

    try {
        $userManager = new \Klytos\Core\UserManager( App::getInstance()->getStorage() );
        return $userManager->getById( $userId );
    } catch ( \RuntimeException $e ) {
        // The session names a user that no longer exists (deleted, or storage
        // unreadable). Denied, and logged: a session pointing at a missing user
        // is worth seeing in the log, not swallowing.
        klytos_log_warning(
            'Session user id does not resolve to a user — denied.',
            [ 'user_id' => $userId, 'error' => $e->getMessage() ],
            'auth'
        );

        return null;
    }
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

    // ONE MATRIX (S-04). The capability matrix used to be duplicated verbatim
    // here and in UserManager::hasPermission() — 22 identical entries in two
    // places, with nothing keeping them in step. They had not diverged yet, so
    // this is a drift hazard being removed before it becomes a security bug:
    // a permission tightened in one copy and missed in the other fails OPEN on
    // whichever path the caller happens to take.
    //
    // UserManager owns it because it is the lower layer: it decides for an
    // explicitly supplied user, while this helper's job is resolving WHICH user
    // is current. Sprint 2's MCP gating and slice 4's klytos_require_permission()
    // both reuse the same single implementation.
    $userManager = new \Klytos\Core\UserManager( App::getInstance()->getStorage() );

    return $userManager->hasPermission( $user, $permission );
}

/**
 * Determine which response shape the current request expects.
 *
 * A refusal is only useful if the caller can parse it. An XHR that receives
 * an HTML page gets a JSON parse error instead of a status it can act on,
 * which is exactly the defect S-07 recorded next to the gap itself
 * (admin/bootstrap.php used to 302 API endpoints to an HTML login page).
 *
 * @return string One of: 'api', 'mcp', 'cli', 'page'.
 */
function klytos_current_surface(): string
{
    if ( klytos_is_cli() ) {
        return 'cli';
    }

    // Checked before klytos_is_admin(): admin/api/* is an admin path AND an
    // API path, and the API shape is the one its callers can parse.
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if ( str_contains( $scriptName, '/admin/api/' ) ) {
        return 'api';
    }

    if ( klytos_is_mcp() ) {
        return 'mcp';
    }

    return 'page';
}

/**
 * Refuse the current request and stop, in the shape its caller can parse.
 *
 * Promoted from the shape already proven in core/router.php:438-447, which
 * gates plugin-registered dynamic routes and already branches denial by
 * surface. Static admin pages never pass through that router, which is the
 * mechanical reason ~70% of them were ungated (S-07) — so the fix is to lift
 * that branch into a helper every surface can call, not to invent a second one.
 *
 * @param  int    $status  HTTP status: 401 (not authenticated) or 403 (denied).
 * @param  string $message Human-readable, already translated.
 * @param  string $code    Stable machine-readable code for API callers.
 * @param  string|null $surface Override the detected surface. Testing seam.
 * @return never
 */
function klytos_deny( int $status, string $message, string $code = 'forbidden', ?string $surface = null ): never
{
    $surface = $surface ?? klytos_current_surface();

    /**
     * Fires immediately before a request is refused.
     *
     * The audit hook: this is where a deployment logs or alerts on refusals.
     * It cannot reverse the decision — a filter that could turn a denial into
     * a grant would put the product's authorization back in third-party hands,
     * which is the failure S-07 exists to close.
     */
    klytos_do_action( 'auth.access_denied', $status, $code, $surface );

    if ( $surface === 'api' || $surface === 'mcp' ) {
        \Klytos\Core\Helpers::jsonResponse( [ 'error' => $message, 'code' => $code ], $status );
    }

    if ( $surface === 'cli' ) {
        fwrite( STDERR, $message . PHP_EOL );
        exit( 1 );
    }

    // Page surface. Deliberately self-contained: no header/sidebar/footer
    // chrome, because this runs BEFORE a page has set up its own context
    // ($pageTitle, the admin-page global, its own data). A gate that can
    // itself fatal while rendering a refusal is not a gate — the same
    // reasoning L-006 recorded for the boot-time logger.
    http_response_code( $status );
    header( 'Content-Type: text/html; charset=utf-8' );

    $title = $status === 401 ? __( 'common.authentication_required' ) : __( 'common.forbidden' );

    // Ask the I18n instance rather than a global: $GLOBALS['klytos_i18n'] is
    // the only thing bootstrap.php's __() fallback knows about, and it is null
    // until boot() sets it. A refusal must render correctly on both sides of
    // that boundary, so the null case falls back rather than dereferencing.
    $i18n   = $GLOBALS['klytos_i18n'] ?? null;
    $locale = $i18n !== null ? $i18n->getLocale() : 'en';

    echo '<!DOCTYPE html><html lang="' . klytos_esc_attr( $locale ) . '"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<meta name="robots" content="noindex,nofollow">';
    echo '<title>' . klytos_esc_html( $title ) . '</title></head>';
    echo '<body style="font-family:system-ui,sans-serif;max-width:34rem;margin:4rem auto;padding:0 1rem">';
    echo '<h1 style="font-size:1.25rem">' . klytos_esc_html( $title ) . '</h1>';
    echo '<p>' . klytos_esc_html( $message ) . '</p>';
    echo '<p><a href="' . klytos_esc_url( klytos_admin_url() ) . '">'
        . klytos_esc_html( __( 'common.back' ) ) . '</a></p>';
    echo '</body></html>';
    exit;
}

/**
 * Require a capability, or refuse the request and stop.
 *
 * The enforcing counterpart to klytos_has_permission(), which only ANSWERS.
 * S-07's finding was not that the answer was wrong — it was that ~70% of admin
 * surfaces never asked. This is the single call every gated surface makes, and
 * it reuses UserManager::hasPermission() as the one decision point (S-04)
 * rather than adding a second.
 *
 * Distinguishes the two refusals, because they are different facts about the
 * caller and different fixes for them: 401 means "we do not know who you are",
 * 403 means "we know, and it is not enough".
 *
 * @param  string      $permission Capability key, e.g. 'users.manage'.
 * @param  string|null $surface    Override the detected surface. Testing seam.
 * @return void
 */
function klytos_require_permission( string $permission, ?string $surface = null ): void
{
    if ( klytos_current_user() === null ) {
        klytos_deny(
            401,
            __( 'common.authentication_required' ),
            'authentication_required',
            $surface
        );
    }

    if ( ! klytos_has_permission( $permission ) ) {
        klytos_deny( 403, __( 'common.no_permission' ), 'forbidden', $surface );
    }
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

/**
 * Register an option with its data sensitivity classification.
 *
 * Call this during plugin activation or in your main plugin file to tell
 * Klytos how to handle encryption for this option. This determines whether
 * the option is encrypted at rest based on the site's encryption level.
 *
 * @param string      $key       Option key (e.g. 'my-plugin.api_key').
 * @param bool|string $sensitive Sensitivity level:
 *                               - true:        Always encrypted (API keys, tokens, secrets).
 *                               - 'user_data': Encrypted from 'medium' level (emails, IPs, GDPR data).
 *                               - false:       Only encrypted at 'professional' level (default).
 * @param array       $meta      Optional metadata: ['type' => 'string', 'default' => ''].
 */
function klytos_register_option( string $key, bool|string $sensitive = false, array $meta = [] ): void
{
    \Klytos\Core\OptionsManager::registerOption( $key, $sensitive, $meta );
}

/**
 * Get the declared sensitivity level for an option.
 *
 * @param  string $key Option key.
 * @return bool|string|null Sensitivity level, or null if not registered.
 */
function klytos_get_option_sensitivity( string $key ): bool|string|null
{
    return \Klytos\Core\OptionsManager::getSensitivity( $key );
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

// ─── Notice API ─────────────────────────────────────────────
// Admin notices displayed at the top of admin pages.
// Two modes: transient (flash, shown once) and persistent (stored, survives requests).

/**
 * Add a transient (flash) admin notice.
 *
 * The notice is shown once on the current page load (or after a redirect).
 * Text-only — HTML is stripped automatically.
 *
 * @param string $message     Plain text message.
 * @param string $type        Notice type: 'success', 'error', 'warning', 'info'.
 * @param bool   $dismissible Whether the user can close the notice.
 */
function klytos_add_notice( string $message, string $type = 'info', bool $dismissible = true ): void
{
    App::getInstance()->getNoticeManager()->addTransient( $message, $type, $dismissible );
}

/**
 * Add or update a persistent admin notice (idempotent by ID).
 *
 * Persistent notices survive across page loads and sessions.
 * If a notice with the given ID already exists, it is updated.
 *
 * @param  string $id          Unique notice identifier.
 * @param  string $message     Plain text message.
 * @param  string $type        Notice type: 'success', 'error', 'warning', 'info'.
 * @param  bool   $dismissible Whether the user can close the notice.
 * @param  array  $options     Optional: 'context' (page filter), 'condition_hook' (conditional display filter), 'ads' (bool, default true — set false for non-advertising notices).
 * @return array  The notice record.
 */
function klytos_add_persistent_notice( string $id, string $message, string $type = 'info', bool $dismissible = true, array $options = [] ): array
{
    $data = array_merge( $options, [
        'message'     => $message,
        'type'        => $type,
        'dismissible' => $dismissible,
    ] );

    return App::getInstance()->getNoticeManager()->ensureSystemNotice( $id, $data );
}

/**
 * Dismiss a persistent notice for the current user session.
 *
 * @param string $id Notice ID to dismiss.
 */
function klytos_dismiss_notice( string $id ): void
{
    App::getInstance()->getNoticeManager()->dismiss( $id );
}

/**
 * Get all notices that should be rendered on the current page.
 *
 * @param  string $currentPage Current admin page slug (optional).
 * @return array  Array of notice records.
 */
function klytos_get_notices( string $currentPage = '' ): array
{
    return App::getInstance()->getNoticeManager()->getRenderable( $currentPage );
}

// ─── Cache API ──────────────────────────────────────────────
// Persistent cache with auto-detected driver (APCu, Redis, Memcached, File).
// Keys should use group:key notation for group-level flush support.
// Example: 'options:my_key', 'pages:home', 'sessions:abc123'.

/**
 * Get the CacheManager instance.
 *
 * @return \Klytos\Core\CacheManager
 */
function klytos_cache(): \Klytos\Core\CacheManager
{
    return App::getInstance()->getCacheManager();
}

/**
 * Get a value from the cache.
 *
 * @param  string $key     Cache key (e.g. 'options:my_setting').
 * @param  mixed  $default Value to return if the key does not exist.
 * @return mixed
 */
function klytos_cache_get(string $key, mixed $default = null): mixed
{
    return App::getInstance()->getCacheManager()->get($key, $default);
}

/**
 * Store a value in the cache.
 *
 * @param string $key   Cache key.
 * @param mixed  $value Value to store (must be serializable).
 * @param int    $ttl   Time-to-live in seconds. 0 = use default TTL from config.
 * @return bool  True on success.
 */
function klytos_cache_set(string $key, mixed $value, int $ttl = 0): bool
{
    return App::getInstance()->getCacheManager()->set($key, $value, $ttl);
}

/**
 * Delete a value from the cache.
 *
 * @param  string $key Cache key.
 * @return bool   True if the key existed and was deleted.
 */
function klytos_cache_delete(string $key): bool
{
    return App::getInstance()->getCacheManager()->delete($key);
}

/**
 * Get or compute: returns cached value, or computes, caches, and returns it.
 *
 * Recommended pattern for caching expensive operations:
 *
 *   $pages = klytos_cache_remember('pages:published', function () {
 *       return klytos_storage()->list('pages', ['status' => 'published']);
 *   }, 1800);
 *
 * @param string   $key      Cache key.
 * @param callable $callback Function that computes the value if not cached.
 * @param int      $ttl      TTL in seconds (0 = use default).
 * @return mixed
 */
function klytos_cache_remember(string $key, callable $callback, int $ttl = 0): mixed
{
    return App::getInstance()->getCacheManager()->remember($key, $callback, $ttl);
}

/**
 * Flush a specific cache group.
 *
 * Groups correspond to the part before the colon in a key:
 * 'options:my_key' belongs to group 'options'.
 *
 * @param  string $group Group name (e.g. 'options', 'pages', 'sessions').
 * @return bool
 */
function klytos_cache_flush_group(string $group): bool
{
    return App::getInstance()->getCacheManager()->flushGroup($group);
}

/**
 * Flush all cache groups (global flush).
 *
 * Fires the 'cache.all_flushed' action hook after completion.
 * Plugins can register additional groups via the 'cache.groups' filter.
 *
 * @return bool
 */
function klytos_cache_flush_all(): bool
{
    return App::getInstance()->getCacheManager()->flushAll();
}

/**
 * Flush the entire cache store.
 *
 * This removes ALL cached entries (including those from other groups).
 * Prefer klytos_cache_flush_all() for controlled group-based invalidation.
 *
 * @return bool
 */
function klytos_cache_flush(): bool
{
    return App::getInstance()->getCacheManager()->flush();
}

/**
 * Get cache statistics for diagnostics.
 *
 * @return array ['driver', 'hits', 'misses', 'memory', 'uptime', 'entries', ...]
 */
function klytos_cache_stats(): array
{
    return App::getInstance()->getCacheManager()->getStats();
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

// ─── Integrity API ──────────────────────────────────────────────
// Verify that core and plugin files have not been tampered with.

/**
 * Run a full integrity verification (core + all plugins).
 *
 * @param  bool  $forceRefresh Force manifest re-download (ignore cache).
 * @return array Full verification report.
 */
function klytos_integrity_check( bool $forceRefresh = false ): array
{
    return App::getInstance()->getIntegrityChecker()->verify( $forceRefresh );
}

/**
 * Get the last integrity verification report.
 *
 * @return array|null The last report, or null if no check has been run.
 */
function klytos_integrity_status(): ?array
{
    return App::getInstance()->getIntegrityChecker()->getLastReport();
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

// ─── Shortcodes API ───────────────────────────────────────────

/**
 * Register a shortcode.
 *
 * @param string   $tag         Shortcode tag.
 * @param callable $callback    Callback: (array $attrs, string $content, string $tag) → string.
 * @param string   $description Optional description.
 * @since 0.26.0
 */
function klytos_add_shortcode( string $tag, callable $callback, string $description = '' ): void
{
    App::getInstance()->getShortcodeManager()->register( $tag, $callback, [], $description );
}

/**
 * Process shortcodes in a string.
 *
 * @param  string $content Content containing [shortcodes].
 * @return string Processed content.
 * @since  0.26.0
 */
function klytos_do_shortcode( string $content ): string
{
    return App::getInstance()->getShortcodeManager()->process( $content );
}

/**
 * Check if a shortcode is registered.
 *
 * @param  string $tag Shortcode tag.
 * @return bool
 * @since  0.26.0
 */
function klytos_shortcode_exists( string $tag ): bool
{
    return App::getInstance()->getShortcodeManager()->exists( $tag );
}

// ─── HTTP API ─────────────────────────────────────────────────
// Outbound HTTP request helpers wrapping HttpClient.

/**
 * Get the HttpClient instance.
 *
 * @return \Klytos\Core\HttpClient
 * @since  0.26.0
 */
function klytos_http(): \Klytos\Core\HttpClient
{
    return App::getInstance()->getHttpClient();
}

/**
 * Perform an HTTP GET request.
 *
 * @param  string $url  Request URL.
 * @param  array  $args Options: headers, timeout, ssl_verify, etc.
 * @return array  ['status' => int, 'headers' => array, 'body' => string, 'error' => ?string]
 * @since  0.26.0
 */
function klytos_http_get( string $url, array $args = [] ): array
{
    $headers = $args['headers'] ?? [];
    unset( $args['headers'] );
    return App::getInstance()->getHttpClient()->get( $url, $headers, $args );
}

/**
 * Perform an HTTP POST request.
 *
 * @param  string $url  Request URL.
 * @param  mixed  $body Request body (string, array for JSON, or null).
 * @param  array  $args Options: headers, timeout, ssl_verify, etc.
 * @return array
 * @since  0.26.0
 */
function klytos_http_post( string $url, mixed $body = null, array $args = [] ): array
{
    $headers = $args['headers'] ?? [];
    unset( $args['headers'] );
    return App::getInstance()->getHttpClient()->post( $url, $body, $headers, $args );
}

/**
 * Return a SafeHttp fetcher for URLs an untrusted party influenced.
 *
 * Use this — never klytos_http_get() — whenever any part of the URL comes from
 * a request parameter, stored content, an MCP tool argument, a plugin header or
 * anything else the operator did not type themselves. It refuses loopback,
 * private, link-local and reserved addresses, refuses non-HTTP(S) schemes, and
 * re-validates every redirect hop instead of letting the transport follow them
 * unchecked. See docs/reference/safe-http.md.
 *
 * @return \Klytos\Core\SafeHttp
 * @since  0.31.0
 */
function klytos_safe_http(): \Klytos\Core\SafeHttp
{
    // Stateless and cheap, so it is constructed per call rather than held as an
    // App service — which also keeps it usable in the unit tier, where no App
    // is booted.
    return new \Klytos\Core\SafeHttp();
}

// ─── Transients API ───────────────────────────────────────────
// Temporary data storage with automatic expiration.
// Thin wrappers over CacheManager with a 'transient:' prefix.

/**
 * Set a transient value with TTL.
 *
 * @param string $key   Transient name.
 * @param mixed  $value Value to store.
 * @param int    $ttl   Time-to-live in seconds (default 1 hour).
 * @return bool
 * @since 0.26.0
 */
function klytos_set_transient( string $key, mixed $value, int $ttl = 3600 ): bool
{
    $cache = App::getInstance()->getCacheManager();
    $cache->set( 'transient:' . $key, $value, $ttl );
    klytos_do_action( 'transient.set_' . $key, $value, $ttl );
    return true;
}

/**
 * Get a transient value.
 *
 * @param string $key Transient name.
 * @return mixed The stored value, or false if expired/missing.
 * @since 0.26.0
 */
function klytos_get_transient( string $key ): mixed
{
    // Allow plugins to short-circuit.
    $pre = klytos_apply_filters( 'transient.pre_get_' . $key, null );
    if ( $pre !== null ) {
        return $pre;
    }

    $cache = App::getInstance()->getCacheManager();
    $value = $cache->get( 'transient:' . $key );
    return $value === null ? false : $value;
}

/**
 * Delete a transient.
 *
 * @param string $key Transient name.
 * @return bool
 * @since 0.26.0
 */
function klytos_delete_transient( string $key ): bool
{
    $cache = App::getInstance()->getCacheManager();
    $cache->delete( 'transient:' . $key );
    klytos_do_action( 'transient.delete_' . $key );
    return true;
}

// ─── Avatar API ───────────────────────────────────────────────

/**
 * Get the avatar URL for a user.
 *
 * Returns the custom avatar if one is set, otherwise falls back to
 * Gravatar based on the user's email address.
 *
 * @param  array  $user User data array (must contain 'email', may contain 'avatar').
 * @param  int    $size Avatar size in pixels (default 80).
 * @return string Avatar URL (Gravatar or custom).
 * @since  0.26.0
 */
function klytos_get_avatar_url( array $user, int $size = 80 ): string
{
    // If a custom avatar is uploaded, use it.
    $customAvatar = trim( $user['avatar'] ?? '' );
    if ( $customAvatar !== '' ) {
        return klytos_apply_filters( 'user.avatar_url', $customAvatar, $user, $size );
    }

    // Fall back to Gravatar.
    $email = strtolower( trim( $user['email'] ?? '' ) );
    $hash  = md5( $email );
    $url   = 'https://www.gravatar.com/avatar/' . $hash . '?s=' . $size . '&d=mp&r=g';

    return klytos_apply_filters( 'user.avatar_url', $url, $user, $size );
}

/**
 * Get the Gravatar URL for an email address.
 *
 * @param  string $email Email address.
 * @param  int    $size  Avatar size in pixels.
 * @return string Gravatar URL.
 * @since  0.26.0
 */
function klytos_gravatar_url( string $email, int $size = 80 ): string
{
    $hash = md5( strtolower( trim( $email ) ) );
    return 'https://www.gravatar.com/avatar/' . $hash . '?s=' . $size . '&d=mp&r=g';
}

// ─── Maintenance Mode API ─────────────────────────────────────

/**
 * Check whether maintenance mode is currently enabled.
 *
 * @return bool
 * @since  0.26.0
 */
function klytos_is_maintenance_mode(): bool
{
    return (bool) App::getInstance()->getSiteConfig()->getValue( 'maintenance_mode', false );
}

// ─── Dashboard Widgets API ────────────────────────────────────
// Register, unregister, and retrieve extensible dashboard widgets.

/**
 * Register a dashboard widget.
 *
 * Plugins and core may call this during the `admin.dashboard.init` action
 * to add cards to the admin dashboard.
 *
 * @param string        $id         Unique widget identifier.
 * @param string        $title      Widget card title.
 * @param callable      $callback   Renders widget body HTML. Receives no arguments.
 * @param int           $position   Sort position (lower = higher). Default 50.
 * @param string|null   $capability Required capability to see the widget, or null for all.
 * @since 0.26.0
 */
function klytos_register_dashboard_widget( string $id, string $title, callable $callback, int $position = 50, ?string $capability = null ): void
{
    if ( !isset( $GLOBALS['_klytos_dashboard_widgets'] ) ) {
        $GLOBALS['_klytos_dashboard_widgets'] = [];
    }

    $GLOBALS['_klytos_dashboard_widgets'][$id] = [
        'id'         => $id,
        'title'      => $title,
        'callback'   => $callback,
        'position'   => $position,
        'capability' => $capability,
    ];
}

/**
 * Unregister a dashboard widget.
 *
 * @param string $id Widget identifier.
 * @since 0.26.0
 */
function klytos_unregister_dashboard_widget( string $id ): void
{
    unset( $GLOBALS['_klytos_dashboard_widgets'][$id] );
}

/**
 * Get all registered dashboard widgets, sorted by position.
 *
 * @return array List of widget definitions sorted by position ascending.
 * @since 0.26.0
 */
function klytos_get_dashboard_widgets(): array
{
    $widgets = $GLOBALS['_klytos_dashboard_widgets'] ?? [];

    // Allow plugins to add/remove/reorder widgets via filter.
    $widgets = klytos_apply_filters( 'admin.dashboard.widgets', $widgets );

    // Sort by position, then by registration order.
    uasort( $widgets, function ( array $a, array $b ): int {
        return $a['position'] <=> $b['position'];
    });

    return array_values( $widgets );
}
