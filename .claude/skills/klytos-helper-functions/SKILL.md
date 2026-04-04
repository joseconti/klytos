---
name: Klytos Helper Functions
description: Reference of all global helper functions available in Klytos CMS. Use when developing plugins, extending the CMS, accessing core services, checking permissions, generating URLs, logging messages, or calling any klytos_* global function from PHP code.
---

# Klytos Helper Functions Reference

## When to Use This Skill

Use this reference whenever you need to call a Klytos core function from a plugin, a custom template, or any PHP code running inside the CMS. All functions are prefixed with `klytos_` and are available globally after boot.

These functions are loaded by `App::boot()` BEFORE plugins, so they are available in every plugin's `init.php`.

---

## Hook Wrappers

### Actions (fire-and-forget events)

```php
klytos_add_action(string $hook, callable $callback, int $priority = 10): void
```
Register a callback for an action hook. Lower priority = runs earlier. Default: 10.

```php
klytos_do_action(string $hook, mixed ...$args): void
```
Fire an action hook, executing all registered callbacks with the provided arguments.

```php
klytos_remove_action(string $hook, callable $callback): bool
```
Remove a specific callback from an action hook. Returns true if removed.

```php
klytos_has_action(string $hook): bool
```
Check if any callbacks are registered for an action hook.

### Filters (modify and return data)

```php
klytos_add_filter(string $hook, callable $callback, int $priority = 10): void
```
Register a callback that receives, modifies, and returns a value.

```php
klytos_apply_filters(string $hook, mixed $value, mixed ...$args): mixed
```
Pass a value through all registered filter callbacks. Each callback receives the value and returns its modified version.

```php
klytos_remove_filter(string $hook, callable $callback): bool
```
Remove a specific callback from a filter hook.

```php
klytos_has_filter(string $hook): bool
```
Check if any callbacks are registered for a filter hook.

### Bulk Removal

```php
klytos_remove_all_actions(string $hook): void
```
Remove ALL callbacks from an action hook. Use with caution.

```php
klytos_remove_all_filters(string $hook): void
```
Remove ALL callbacks from a filter hook. Use with caution.

### Debugging / Introspection

```php
klytos_did_action(string $hook): int
```
Check how many times an action has been fired in this request.

```php
klytos_get_fired_actions(): array
```
Get all actions that have been fired. Returns `['hook' => fire_count, ...]`.

```php
klytos_get_registered_hooks(): array
```
Get all registered hooks and callback counts. Returns `['actions' => [...], 'filters' => [...]]`.

### Example

```php
// Register an action
klytos_add_action('page.after_save', function (array $page, string $action): void {
    klytos_log('info', "Page {$page['slug']} was {$action}d");
});

// Register a filter
klytos_add_filter('page.content', function (string $html): string {
    return '<div class="wrapper">' . $html . '</div>';
});
```

> **IMPORTANT**: ALWAYS use the global `klytos_*` functions. NEVER use `Hooks::` class methods directly.
> The `Hooks` class is an internal engine — all code must use the `klytos_*` wrappers.

---

## Core Service Accessors

```php
klytos_app(): App
```
Get the App singleton instance. Provides access to all managers and services.

```php
klytos_storage(): StorageInterface
```
Get the storage layer (FileStorage or DatabaseStorage). Use this for all data operations.

```php
klytos_auth(): Auth
```
Get the authentication manager. Provides session management, CSRF, bearer tokens, OAuth.

---

## Configuration

```php
klytos_config(string $key, mixed $default = null): mixed
```
Read a configuration value using dot-notation. Examples:
- `klytos_config('site_name')` -> `'My Site'`
- `klytos_config('admin_language', 'en')` -> `'es'`
- `klytos_config('social.twitter')` -> `'@handle'`

```php
klytos_set_config(string $key, mixed $value): void
```
Write a configuration value. **Top-level keys only** (no dot-notation for writing). Use sparingly — most plugin settings should use `klytos_set_option()` instead.

```php
klytos_version(): string
```
Get the current Klytos version. Returns semantic version string (e.g. `'0.11.0'`).

---

## URL Helpers

```php
klytos_url(string $path = ''): string
```
Generate a full URL relative to the Klytos site root.

```php
klytos_admin_url(string $path = ''): string
```
Generate a full admin URL.

```php
klytos_plugin_url(string $pluginId, string $path = ''): string
```
Get the public URL for a plugin's assets directory.

```php
klytos_plugin_path(string $pluginId, string $path = ''): string
```
Get the absolute filesystem path for a plugin's directory.

---

## Context Checks

```php
klytos_is_admin(): bool   // True if in admin panel context
klytos_is_mcp(): bool     // True if MCP API request
klytos_is_cli(): bool     // True if command line
```

### Usage Pattern

```php
if (klytos_is_mcp()) {
    // MCP-specific logic (return JSON)
} elseif (klytos_is_admin()) {
    // Admin panel logic (render HTML)
}
```

---

## User & Permissions

```php
klytos_current_user(): ?array
```
Get the currently authenticated user. Returns `null` if not logged in.
Returns: `['id' => string, 'username' => string, 'role' => string, 'email' => string]`

```php
klytos_has_permission(string $permission): bool
```
Check if the current user has a specific permission. Owner role has ALL permissions.

### Built-in Permissions

| Permission | Roles Allowed |
|---|---|
| `pages.view` | owner, admin, editor, viewer |
| `pages.create` | owner, admin, editor |
| `pages.edit` | owner, admin, editor |
| `pages.delete` | owner, admin |
| `theme.manage` | owner, admin |
| `menu.manage` | owner, admin |
| `blocks.manage` | owner, admin |
| `templates.manage` | owner, admin |
| `templates.approve` | owner |
| `build.run` | owner, admin |
| `assets.manage` | owner, admin, editor |
| `tasks.create` | owner, admin, editor |
| `tasks.manage` | owner, admin |
| `users.manage` | owner |
| `mcp.manage` | owner, admin |
| `site.configure` | owner, admin |
| `plugins.manage` | owner |
| `analytics.view` | owner, admin, editor |
| `forms.manage` | owner, admin |
| `webhooks.manage` | owner, admin |
| `updates.manage` | owner |

### Extending Permissions from a Plugin

```php
klytos_add_filter('auth.capabilities', function (array $capabilities): array {
    $capabilities['my_plugin.manage'] = ['owner', 'admin'];
    $capabilities['my_plugin.view']   = ['owner', 'admin', 'editor'];
    return $capabilities;
});
```

---

## Options API (Plugin Settings)

```php
klytos_get_option(string $key, mixed $default = null): mixed
klytos_set_option(string $key, mixed $value): void
klytos_delete_option(string $key): bool
klytos_option_exists(string $key): bool
```

Convention: `'plugin_id.setting_name'` (e.g. `'my-gallery.columns'`).

---

## Meta API (Entity Metadata)

```php
klytos_get_meta(string $collection, string $entityId, string $key): mixed
klytos_set_meta(string $collection, string $entityId, string $key, mixed $value): void
klytos_delete_meta(string $collection, string $entityId, string $key): bool
klytos_get_all_meta(string $collection, string $entityId): array
```

---

## Time & Timezone API

```php
klytos_timezone(): \DateTimeZone              // Site's timezone object (cached)
klytos_timezone_string(): string              // IANA timezone string (e.g. 'Europe/Madrid')
klytos_timezone_offset(): int                 // UTC offset in seconds (DST-aware)
klytos_timezone_reset_cache(): void           // Clear timezone cache after config change
klytos_now_utc(): string                      // Current UTC time as ISO 8601
klytos_now_local(): string                    // Current local time as ISO 8601
klytos_time(): int                            // Current Unix timestamp (filterable via 'time.now')
klytos_gmdate( string $fmt, ?int $ts ): string          // Format in UTC
klytos_date( string $fmt, ?int $ts ): string             // Format in local timezone
klytos_format_datetime( string $iso, string $fmt ): string // Format ISO string for display
klytos_utc_to_local( string $utc, string $fmt ): string  // UTC to local conversion
klytos_local_to_utc( string $local, string $fmt ): string // Local to UTC conversion
klytos_datetime_to_timestamp( string $dt ): int           // ISO string to Unix timestamp
klytos_timestamp_to_datetime( int $ts ): string           // Unix timestamp to ISO 8601 UTC
klytos_timezone_list(): array                              // Grouped IANA timezones for UI
```

**Rule: store in UTC (`klytos_now_utc()`), display in local (`klytos_format_datetime()`).** Never use bare `date()`. See `klytos-time` skill for full details.

Source: `core/helpers-time.php`, `core/timezone-cache.php`

---

## Action Scheduler API

```php
klytos_schedule_single_action(int $timestamp, string $hook, array $args = [], string $group = ''): string
klytos_schedule_recurring_action(int $timestamp, int $intervalSeconds, string $hook, array $args = [], string $group = ''): string
klytos_cancel_scheduled_action(string $actionId): bool
klytos_unschedule_all_actions(string $hook, array $args = [], string $group = ''): int
klytos_next_scheduled_action(string $hook, array $args = [], string $group = ''): ?int
klytos_is_scheduled_action(string $hook, array $args = [], string $group = ''): bool
```

---

## Route & Admin Page Registration

```php
klytos_register_route( string $pattern, array $config ): void
```
Register a dynamic route from a plugin. Config requires `callback` (callable) and `type` ('page', 'api', 'webhook'). Optional: `method` (default 'GET'), `template`, `title`, `auth` (false|'frontend'|'admin'), `capability`. See `core/route-manager.php`.

```php
klytos_register_admin_page( string $pluginId, array $page ): void
```
Register a plugin admin page with sidebar entry. The `$page` array requires `id` (maps to `plugins/{pluginId}/admin/{id}.php`) and `title`. Optional: `icon`, `position` (85-89), `capability`, `children` (sub-pages). Routes through `admin/plugin-page.php`.

## Internationalization (i18n)

```php
klytos_register_translations(string $pluginId, string $langDir): void
__(string $key, array $replacements = []): string
```

---

## Logging

```php
klytos_log(string $level, string $message, array $context = [], string $source = 'core'): void
```
PSR-3 levels: `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`.
Requires Developer Mode active. For plugin sources, plugin must have logging enabled.
Logs written to `data/logs-{random}/debug-YYYY-MM-DD.log` with daily rotation and 5MB file-size splitting.

**Level-specific helpers** (all accept `$message`, `$context = []`, `$source = 'core'`):

```php
klytos_log_emergency(), klytos_log_alert(), klytos_log_critical(),
klytos_log_error(), klytos_log_warning(), klytos_log_notice(),
klytos_log_info(), klytos_log_debug()
```

### Unconditional Logging (Fatal Errors)

```php
$app->getLogger()->writeAlways(string $level, string $message, array $context = [], string $source = 'core'): void
```
Bypasses Developer Mode and per-plugin checks. Used by the shutdown handler for fatal errors that MUST always be logged. Not intended for normal application logging — use `klytos_log_*()` for that.

### Fatal Error Shutdown Handler

Registered in `admin/bootstrap.php` via `register_shutdown_function`. Catches PHP fatal errors (`E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_COMPILE_ERROR`, `E_USER_ERROR`) that occur after boot and logs them unconditionally to both PHP `error_log()` and Klytos logs via `Logger::writeAlways()`.

---

## Utility Functions (Helpers class)

```php
Helpers::sanitizeSlug(string $slug): string      // "Cafe & Musica" -> "cafe-musica"
Helpers::transliterate(string $text): string      // e->e, u->u, n->n
Helpers::smartTruncate(string $text, int $max = 160): string
Helpers::now(): string                            // ISO 8601 timestamp
Helpers::randomHex(int $bytes = 32): string       // Secure random hex
Helpers::generateBearerToken(): string            // 64-char hex token
Helpers::hashToken(string $token): string         // SHA-256 hash
```

---

## Shortcodes API (v0.26.0)

```php
klytos_add_shortcode(string $tag, callable $callback, string $description = ''): void
```
Register a shortcode `[tag]`. Callback: `(array $attrs, string $content, string $tag) → string`.

```php
klytos_do_shortcode(string $content): string
```
Process all registered shortcodes in a string.

```php
klytos_shortcode_exists(string $tag): bool
```
Check if a shortcode tag is registered.

## Transients API (v0.26.0)

```php
klytos_set_transient(string $key, mixed $value, int $ttl = 3600): bool
klytos_get_transient(string $key): mixed      // returns false on miss
klytos_delete_transient(string $key): bool
```
Temporary data storage with auto-expiration. Wraps CacheManager with `transient:` prefix.

## HTTP API (v0.26.0)

```php
klytos_http(): \Klytos\Core\HttpClient
klytos_http_get(string $url, array $args = []): array
klytos_http_post(string $url, mixed $body = null, array $args = []): array
```
Returns: `['status' => int, 'headers' => array, 'body' => string, 'error' => ?string]`.

## Dashboard Widgets API (v0.26.0)

```php
klytos_register_dashboard_widget(string $id, string $title, callable $callback, int $position = 50, ?string $capability = null): void
klytos_unregister_dashboard_widget(string $id): void
klytos_get_dashboard_widgets(): array
```
Register widgets in the `admin.dashboard.init` action.

## Maintenance Mode (v0.26.0)

```php
klytos_is_maintenance_mode(): bool
```

---

## Storage Interface

```php
$storage = klytos_storage();
$storage->read(string $collection, string $id): array
$storage->write(string $collection, string $id, array $data): void
$storage->delete(string $collection, string $id): bool
$storage->exists(string $collection, string $id): bool
$storage->list(string $collection, array $filters = []): array
$storage->count(string $collection, array $filters = []): int
$storage->search(string $collection, string $query, array $fields = []): array
$storage->transaction(callable $fn): mixed
```

---

## Source Files

- Global helpers: `core/helpers-global.php`
- Utility class: `core/helpers.php`
- App singleton: `core/app.php`
- Storage interface: `core/storage-interface.php`
