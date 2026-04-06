# Advanced Klytos Plugin Features

## Registering Admin Pages

Use `klytos_register_admin_page()` to add sidebar items that route to `admin/plugin-page.php`:

```php
klytos_register_admin_page( 'my-plugin', [
    'id'         => 'settings',          // maps to plugins/my-plugin/admin/settings.php
    'title'      => 'My Plugin',
    'icon'       => '🔌',
    'position'   => 86,                  // 85-89 = plugin zone
    'capability' => 'plugins.manage',
    'children'   => [
        ['id' => 'history', 'title' => 'History'],
    ],
] );
```

The PHP file at `plugins/{id}/admin/{page-id}.php` receives `$app`, `$auth`, `$pluginId`, `$pageName`, `$manifest` and renders inside the admin layout automatically.

## Registering Dynamic Routes

Plugins can register public-facing routes (pages, API endpoints, webhooks):

```php
klytos_register_route( '/cart', [
    'type'     => 'page',               // 'page', 'api', or 'webhook'
    'callback' => fn($params) => '<h1>Cart</h1>',
    'template' => 'default',
    'title'    => 'Shopping Cart',
] );

klytos_register_route( '/api/orders/{id}/status', [
    'type'     => 'api',
    'method'   => 'GET',
    'auth'     => 'admin',
    'callback' => fn($params) => ['order_id' => $params['id'], 'status' => 'shipped'],
] );
```

Routes are matched by `RouteManager` (`core/route-manager.php`) before static files. Auth, capability, and rate limiting are enforced by the Router.

## Main Plugin File — Entry Point

The `{plugin-id}.php` file is both the identification AND the entry point. All hooks are registered here. It runs every time Klytos loads (if the plugin is active).

```php
<?php
// plugins/my-plugin/my-plugin.php
/**
 * Plugin Name: My Plugin
 * Version: 1.0.0
 * Author: Author Name
 * Requires Klytos: 0.15.0
 * Requires PHP: 8.1
 */

// 1. Register admin sidebar menu item
klytos_add_filter('admin.sidebar_items', function (array $items): array {
    $items[] = [
        'id'         => 'my-plugin',
        'title'      => 'My Plugin',
        'url'        => klytos_admin_url('plugins/my-plugin/admin/settings.php'),
        'icon'       => 'P',
        'position'   => 86,
        'section'    => 'system',
        'capability' => 'site.configure',
    ];
    return $items;
});

// 2. Register MCP tools
klytos_add_filter('mcp.tools_list', function (array $tools): array {
    $tools[] = [
        'name'        => 'my_plugin_do_something',
        'description' => 'Does something useful.',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => [
                'param1' => ['type' => 'string', 'description' => 'First parameter.'],
            ],
        ],
        'annotations' => [
            'title'          => 'Do Something',
            'readOnlyHint'   => false,
            'destructiveHint' => false,
        ],
    ];
    return $tools;
});

// 3. Handle MCP tool calls
klytos_add_filter('mcp.handle_tool', function (mixed $result, string $toolName, array $params): mixed {
    if ($toolName !== 'my_plugin_do_something') {
        return $result; // Not our tool — pass through.
    }

    // Execute the tool logic.
    return [
        'content' => [['type' => 'text', 'text' => 'Done! Param was: ' . ($params['param1'] ?? '')]],
        'isError' => false,
    ];
}, 10);

// 4. Register translations
klytos_register_translations('my-plugin', klytos_plugin_path('my-plugin', 'lang'));

// 5. Hook into page lifecycle
klytos_add_action('page.after_save', function (array $page, string $action): void {
    // Do something after a page is saved.
    klytos_log('info', 'My plugin: page saved', ['slug' => $page['slug']]);
});
```

## Available Hook Functions

**IMPORTANT**: ALWAYS use the global `klytos_*` functions. NEVER use `Hooks::` class methods directly.

```php
klytos_add_action(string $hook, callable $callback, int $priority = 10): void
klytos_do_action(string $hook, mixed ...$args): void
klytos_remove_action(string $hook, callable $callback): bool
klytos_has_action(string $hook): bool
klytos_remove_all_actions(string $hook): void
klytos_did_action(string $hook): int

klytos_add_filter(string $hook, callable $callback, int $priority = 10): void
klytos_apply_filters(string $hook, mixed $value, mixed ...$args): mixed
klytos_remove_filter(string $hook, callable $callback): bool
klytos_has_filter(string $hook): bool
klytos_remove_all_filters(string $hook): void
```

## Core Service Accessors

```php
klytos_storage()          → StorageInterface (read/write encrypted data)
klytos_app()              → App instance
klytos_auth()             → Auth instance
klytos_config($key, $default) → Read config value (dot notation)
klytos_set_config($key, $value) → Write config value
klytos_url($path)         → Full site URL
klytos_admin_url($path)   → Full admin URL
klytos_plugin_url($id, $path) → Public URL for plugin assets
klytos_plugin_path($id, $path) → Filesystem path for plugin files
klytos_get_plugin_data($id)   → Plugin header data (name, version, author, etc.)
klytos_version()          → Current Klytos version
klytos_is_admin()         → True if in admin context
klytos_is_mcp()           → True if in MCP context
klytos_is_cli()           → True if in CLI context
klytos_current_user()     → Current user array or null
klytos_has_permission($perm) → Permission check
klytos_log($level, $msg, $ctx) → Write to log file
klytos_register_translations($pluginId, $langDir) → Register i18n
```

## Premium Plugin License Verification

Premium plugins set `"premium": true` in the manifest. The PluginLoader automatically verifies their license against plugins.joseconti.com before loading.

License data is stored in: `config/plugin_licenses/{plugin-id}.json.enc`

```php
/**
 * Plugin Name: Premium Plugin
 * Premium: true
 */
```

## Registering Cron Tasks

```php
klytos_add_filter('cron.tasks', function (array $tasks): array {
    $tasks[] = [
        'id'       => 'my_plugin_daily_task',
        'callback' => function (): void {
            // This runs once per day.
            klytos_log('info', 'My plugin daily task executed.');
        },
        'interval' => 'daily', // 'hourly', 'daily', 'weekly', 'monthly'
    ];
    return $tasks;
});
```

## Registering Webhook Events

```php
klytos_add_filter('webhooks.events', function (array $events): array {
    $events['my_plugin.data_synced'] = 'My Plugin data synchronization completed';
    return $events;
});

// Trigger the event when something happens.
$webhookManager = new \Klytos\Core\WebhookManager(klytos_storage());
$webhookManager->dispatch('my_plugin.data_synced', ['records' => 42]);
```

## Registering Translations (i18n) — MANDATORY

When a plugin uses translations (`__()` function), it is **MANDATORY** to create the file `lang/en.json` with ALL translation keys in English. This file serves as the master reference for the Translation Manager in the admin panel.

Without `en.json`, the plugin's keys will NOT appear in System > Translations and cannot be translated from the admin UI or via MCP tools.

Minimum structure for `lang/en.json`:

```json
{
    "plugin_id.key_name": "English text",
    "plugin_id.another_key": "Another English text"
}
```

Optionally, include other language files (`es.json`, `fr.json`, etc.) to ship built-in translations with the plugin.

## Plugin Assets (CSS, JS, Images)

Plugin static assets live in `plugins/{plugin-id}/assets/` and are publicly accessible via the web.

### How Assets Are Served

The `plugins/` directory is protected by `.htaccess` — **all files are accessible by default** (CSS, JS, images, fonts, etc.) but **executable files are blocked**. This is a blacklist approach: block the dangerous, allow everything else.

**Blocked extensions**: `.php`, `.phtml`, `.pht`, `.phar`, `.cgi`, `.pl`, `.py`, `.rb`, `.sh`, `.bash`, `.exe`, `.bat`, `.cmd`, `.com`, `.vbs`, `.wsf`, `.ps1`

Plugin PHP files are only executed server-side by the PluginLoader (`require_once`), never accessed directly via URL.

### klytos_plugin_url() — Building Asset URLs

`klytos_plugin_url($pluginId, $path)` returns the public URL to a file inside the plugin directory. The `$path` is relative to the plugin root — it does NOT auto-append `/assets/`.

```php
// CORRECT — full path from plugin root:
klytos_plugin_url('my-plugin', 'assets/css/style.css')
// → /admin/plugins/my-plugin/assets/css/style.css

// WRONG — do NOT omit 'assets/' prefix:
klytos_plugin_url('my-plugin', 'css/style.css')
// → /admin/plugins/my-plugin/css/style.css  (404!)
```

### Loading Plugin Assets in Admin Pages

```php
$cssUrl = klytos_plugin_url('my-plugin', 'assets/css/style.css');
$jsUrl  = klytos_plugin_url('my-plugin', 'assets/js/script.js');
?>
<link rel="stylesheet" href="<?php echo klytos_esc_url($cssUrl); ?>" nonce="<?php echo klytos_esc_attr($cspNonce); ?>">
<script src="<?php echo klytos_esc_url($jsUrl); ?>" nonce="<?php echo klytos_esc_attr($cspNonce); ?>"></script>
```

### Loading Plugin Assets in the Admin Topbar or Site-Wide

Use the `admin.head` action to inject CSS/JS into the admin `<head>`:

```php
klytos_add_action('admin.head', function (string $cspNonce): void {
    $cssUrl = klytos_plugin_url('my-plugin', 'assets/css/style.css');
    $jsUrl  = klytos_plugin_url('my-plugin', 'assets/js/script.js');
    echo '<link rel="stylesheet" href="' . klytos_esc_url($cssUrl) . '" nonce="' . klytos_esc_attr($cspNonce) . '">';
    echo '<script src="' . klytos_esc_url($jsUrl) . '" nonce="' . klytos_esc_attr($cspNonce) . '" defer></script>';
});
```

### CRITICAL: CSP Nonce Requirement

Klytos uses Content-Security-Policy headers. All `<script>` and `<link>` tags MUST include a `nonce` attribute:

```html
<!-- CORRECT: Will load successfully -->
<script src="..." nonce="<?php echo klytos_esc_attr($cspNonce); ?>"></script>

<!-- WRONG: Will be blocked by CSP -->
<script src="..."></script>
```

The `$cspNonce` variable is available in all admin pages (set in `templates/header.php`). For hooks like `admin.head` and `admin.footer`, it's passed as the first argument to the callback.

### CRITICAL: No Inline Event Handlers

CSP blocks inline event handlers. Never use `onclick="..."`, `onload="..."`, etc. Use `addEventListener` in your JS file instead:

```javascript
// CORRECT
document.getElementById('my-btn').addEventListener('click', function() { ... });

// WRONG — will be blocked by CSP
// <button onclick="doSomething()">
```

## Version Requirements — Semver Gotcha

### The Problem

In semver, pre-release versions (alpha, beta, rc) are **lower** than the release:

```
0.14.0-beta.4 < 0.14.0    ← beta comes BEFORE the release
0.14.0        < 0.14.1
```

If your plugin sets `Requires Klytos: 0.14.0` and the user runs `0.14.0-beta.4`, the plugin will **fail to load** with:

> Requires Klytos 0.14.0+, current: 0.14.0-beta.4

### The Rule

**Always set `Requires Klytos` to the OLDEST version you actually need, not the current one.**

- If you develop on `0.14.0-beta.4`, set `Requires Klytos: 0.13.0` (last stable) or `Requires Klytos: 0.14.0-beta.1`
- Only set `Requires Klytos: 0.15.0` if your plugin uses features introduced in that exact version
- When in doubt, use the **last stable release** as the requirement

```php
/**
 * Plugin Name: My Plugin
 * Requires Klytos: 0.13.0     ← Use the last stable, not the current beta
 */
```

## Installing Plugins via ZIP

Plugins can be installed or updated by uploading a ZIP file through the admin UI (`Plugins → Install Plugin`).

### ZIP Structure Requirements

The ZIP must contain a directory with `{plugin-id}/{plugin-id}.php`:

```
my-plugin.zip
└── my-plugin/
    ├── my-plugin.php      ← REQUIRED (with Plugin Name header)
    ├── assets/
    │   ├── style.css
    │   └── script.js
    └── ...
```

### Automatic Backups on Update

When installing a plugin that already exists, Klytos automatically creates a backup of the current version before overwriting. Backups are stored in `plugins/.backups/{plugin-id}/` and can be restored from the admin UI.

- Maximum **5 backups** per plugin (oldest are purged automatically with no trace)
- Restore is available via the **Restore** button in each plugin's action row

## Plugin Logging

Plugins can opt into the centralized logging system by declaring `Logs: true` in their PHP header. When declared, an "Enable Logs" action appears in the plugin management page.

**Requirements for logging to work:**
1. Developer Mode must be active (Settings → Developer → `developer_mode: true`)
2. The plugin must declare `Logs: true` in its header
3. The admin must enable logging for the plugin (via Plugins page action)

**Writing logs from a plugin:**

```php
// PSR-3 levels: emergency, alert, critical, error, warning, notice, info, debug
klytos_log( 'info', 'Order processed', ['order_id' => 42], 'my-plugin' );

// Convenience helpers (last param is always the plugin ID):
klytos_log_error( 'Payment failed', ['gateway' => 'stripe'], 'my-plugin' );
klytos_log_warning( 'Rate limit approaching', [], 'my-plugin' );
klytos_log_info( 'Cache refreshed', [], 'my-plugin' );
klytos_log_debug( 'Request payload', $data, 'my-plugin' );
```

Logs are stored in `data/logs-{random}/debug-YYYY-MM-DD.log` with daily rotation and 5MB file-size splitting. View logs in System → Logs.

## Storage Pattern for Plugin Data

```php
// Read/write plugin-specific data using the storage API.
$storage = klytos_storage();

// Write plugin data to its own collection.
$storage->write('my-plugin-data', 'settings', [
    'api_key' => 'xxx',
    'enabled' => true,
]);

// Read it back.
$data = $storage->read('my-plugin-data', 'settings');
```

## Security Requirements for Plugins

1. **Never access the filesystem directly** — use `klytos_storage()`.
2. **Always sanitize HTML output** — use `htmlspecialchars()` or `Helpers::sanitizeHtml()`.
3. **Always validate input** — check types, lengths, and formats.
4. **Use capabilities for access control** — register via `auth.capabilities` filter.
5. **Never store secrets in cleartext** — use the encrypted storage.
6. **Include the GPL-3.0-or-later license header** in all PHP files if distributing.

## Troubleshooting — Common Plugin Errors

### Error: "Requires Klytos X+, current: X-beta.Y"

**Cause**: The `Requires Klytos` version in the plugin header is higher than the current Klytos version. Beta/RC versions are lower than stable in semver.

**Fix**: Lower the `Requires Klytos` value in the plugin's PHP header to match the minimum version actually needed. See "Version Requirements — Semver Gotcha" above.

### Error: "Call to undefined method Klytos\Core\I18n::mergeTranslations()"

**Cause**: The plugin calls `klytos_register_translations()` but the Klytos version installed does not have the `mergeTranslations()` method in the I18n class. This method was added in v0.15.0.

**Fix**: Ensure `Requires Klytos: 0.15.0` (or the version where mergeTranslations was added) is set, OR wrap the call in a version check:

```php
if (method_exists(klytos_app()->getI18n(), 'mergeTranslations')) {
    klytos_register_translations('my-plugin', klytos_plugin_path('my-plugin', 'lang'));
}
```

### Error: Plugin assets return 403 (Forbidden)

**Cause**: The `.htaccess` in the `plugins/` directory blocks access to the asset files. This can happen if:
1. The server runs Apache 2.4+ but the `.htaccess` uses old Apache 2.2 directives (`Order deny,allow`)
2. The file extension is not in the allowed list

**Fix**: The `plugins/.htaccess` uses a blacklist approach — block executables, allow everything else. It must support both Apache 2.2 and 2.4:

```apache
# Apache 2.4+
<IfModule mod_authz_core.c>
    <FilesMatch "\.(php|phtml|php[0-9]|pht|phar|cgi|pl|py|rb|sh|bash|exe|bat|cmd|com|vbs|wsf|ps1)$">
        Require all denied
    </FilesMatch>
</IfModule>

# Apache 2.2 fallback
<IfModule !mod_authz_core.c>
    <FilesMatch "\.(php|phtml|php[0-9]|pht|phar|cgi|pl|py|rb|sh|bash|exe|bat|cmd|com|vbs|wsf|ps1)$">
        Order deny,allow
        Deny from all
    </FilesMatch>
</IfModule>
```

**Important**: PHP files inside `plugins/` are ALSO blocked by the main `.htaccess` rule: `RewriteRule ^plugins/.*\.php$ - [F,L]`. Plugin PHP files are only executed server-side by the PluginLoader (`require_once`), never accessed directly via URL.

### Error: Plugin JS blocked by Content-Security-Policy

**Cause**: The `<script>` tag is missing the CSP nonce attribute.

**Fix**: Always add `nonce="<?php echo klytos_esc_attr($cspNonce); ?>"` to script tags. See "CSP Nonce Requirement" above.

### Error: "Plugin not found: {id}"

**Cause**: The plugin directory or main PHP file doesn't follow the naming contract.

**Fix**: Verify:
1. Directory is `plugins/{plugin-id}/` (lowercase, hyphens/underscores only)
2. Main file is `plugins/{plugin-id}/{plugin-id}.php` (exact same name as directory)
3. The file contains `Plugin Name: ...` in a PHP docblock comment

### Plugin loads but hooks don't fire

**Cause**: The plugin's main PHP file runs at load time, but hooks registered there may target events that already happened.

**Fix**: Ensure hooks are registered at the top level of the main file (not inside functions or classes). The PluginLoader runs plugin files during `App::boot()`, before most lifecycle events. Do NOT wrap hook registrations in conditions like `if (is_admin())` — Klytos handles context automatically.

### Plugin admin page shows blank or broken layout

**Cause**: Missing `require_once` for templates or wrong path.

**Fix**: Plugin admin pages should include the standard admin templates:

```php
<?php
// plugins/my-plugin/admin/settings.php
require_once dirname(__DIR__, 3) . '/admin/bootstrap.php';

$pageTitle = 'My Plugin Settings';
require_once dirname(__DIR__, 3) . '/admin/templates/header.php';
require_once dirname(__DIR__, 3) . '/admin/templates/sidebar.php';
?>

<!-- Your page content here -->

<?php require_once dirname(__DIR__, 3) . '/admin/templates/footer.php'; ?>
```

## Extending Permissions from a Plugin

Register custom permissions that plugins can use:

```php
klytos_add_filter('auth.capabilities', function (array $capabilities): array {
    $capabilities['my_plugin.manage'] = ['owner', 'admin'];
    $capabilities['my_plugin.view']   = ['owner', 'admin', 'editor'];
    return $capabilities;
});
```

## Options API (Plugin Settings)

Convention: `'plugin_id.setting_name'` (e.g. `'my-gallery.columns'`).

```php
klytos_get_option(string $key, mixed $default = null): mixed
klytos_set_option(string $key, mixed $value): void
klytos_delete_option(string $key): bool
klytos_option_exists(string $key): bool
```

## Meta API (Entity Metadata)

```php
klytos_get_meta(string $collection, string $entityId, string $key): mixed
klytos_set_meta(string $collection, string $entityId, string $key, mixed $value): void
klytos_delete_meta(string $collection, string $entityId, string $key): bool
klytos_get_all_meta(string $collection, string $entityId): array
```

## Action Scheduler API

```php
klytos_schedule_single_action(int $timestamp, string $hook, array $args = [], string $group = ''): string
klytos_schedule_recurring_action(int $timestamp, int $intervalSeconds, string $hook, array $args = [], string $group = ''): string
klytos_cancel_scheduled_action(string $actionId): bool
klytos_unschedule_all_actions(string $hook, array $args = [], string $group = ''): int
klytos_next_scheduled_action(string $hook, array $args = [], string $group = ''): ?int
klytos_is_scheduled_action(string $hook, array $args = [], string $group = ''): bool
```

## File Locations

- Klytos root: `/installer/` (configurable)
- Core: `/installer/core/`
- Plugins: `/installer/plugins/`
- Plugin backups: `/installer/plugins/.backups/`
- Admin: `/installer/admin/`
- Public output: `/installer/public/`
- Data (encrypted): `/installer/data/`
- Config: `/installer/config/`
