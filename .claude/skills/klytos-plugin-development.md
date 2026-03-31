---
name: klytos-plugin-development
description: Complete guide for developing Klytos CMS plugins. Use when creating, modifying, or debugging plugins for Klytos.
trigger: When the user asks to create a plugin, extend Klytos functionality, add MCP tools, add admin pages, or register hooks/filters.
---

# Klytos Plugin Development Guide

## Architecture Overview

Klytos is an **AI-First CMS** controlled via MCP (Model Context Protocol). Plugins extend core functionality through a WordPress-inspired hook system (actions + filters) WITHOUT modifying core files.

**Key principle**: Every feature should be exposed as an MCP tool FIRST, admin UI second.

## Plugin Identification (IMMUTABLE CONTRACT)

A Klytos plugin is identified by a directory `plugins/{plugin-id}/` containing a PHP file named `{plugin-id}.php` with a `Plugin Name:` header in its docblock. This contract can NEVER change.

### Minimum Viable Plugin

```php
<?php
// plugins/hello-world/hello-world.php
/**
 * Plugin Name: Hello World
 */
```

That's it. Klytos discovers it, lists it in admin, and allows activation.

## Plugin Structure

```
plugins/{plugin-id}/
├── {plugin-id}.php      ← REQUIRED: identification + entry point (PHP header)
├── klytos-plugin.json   ← OPTIONAL: extended metadata (admin_pages, mcp_tools, etc.)
├── install.php          ← Optional: runs on first activation
├── deactivate.php       ← Optional: runs on deactivation
├── uninstall.php        ← Optional: removes plugin data permanently
├── admin/               ← Optional: admin page views
│   └── settings.php
├── assets/              ← Optional: CSS, JS, images (publicly accessible)
│   ├── style.css
│   └── script.js
├── lang/                ← Optional: translation files
│   ├── en.json
│   └── es.json
├── src/                 ← Optional: PHP source classes
│   └── MyManager.php
├── templates/           ← Optional: HTML templates
└── migrations/          ← Optional: data migrations
```

## PHP Header (Canonical Identity)

The main PHP file MUST contain a docblock with at least `Plugin Name:`. All other fields are optional.

```php
<?php
// plugins/my-plugin/my-plugin.php
/**
 * Plugin Name: My Plugin
 * Plugin URI: https://example.com/my-plugin
 * Description: What this plugin does.
 * Version: 1.0.0
 * Author: Author Name
 * Author URI: https://example.com
 * Requires Klytos: 0.15.0
 * Requires PHP: 8.1
 * License: ELv2
 * Text Domain: my-plugin
 * Premium: false
 * Logs: true
 */
```

## Extended Manifest (klytos-plugin.json) — OPTIONAL

For complex structured data that doesn't fit in a PHP header comment. The `id` field is NOT needed — it's derived from the directory name. Identity fields in the JSON are ignored (the PHP header is canonical).

```json
{
  "permissions": ["pages.edit"],
  "admin_pages": [
    {
      "id": "my-plugin-settings",
      "title": "My Plugin",
      "icon": "P",
      "position": 86,
      "section": "system"
    }
  ],
  "mcp_tools": ["my_plugin_do_something"]
}
```

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

> **IMPORTANT**: ALWAYS use the global `klytos_*` functions below. NEVER use `Hooks::` class methods directly.

### Actions (fire-and-forget)
```php
klytos_add_action(string $hook, callable $callback, int $priority = 10): void
klytos_do_action(string $hook, mixed ...$args): void
klytos_remove_action(string $hook, callable $callback): bool
klytos_has_action(string $hook): bool
klytos_remove_all_actions(string $hook): void
klytos_did_action(string $hook): int
```

### Filters (modify data)
```php
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

## Available Hooks

### Page Lifecycle
- `page.before_save` (action) — args: $page, $action ('create'|'update')
- `page.after_save` (action) — args: $page, $action
- `page.before_delete` (action) — args: $slug
- `page.after_delete` (action) — args: $slug
- `page.content` (filter) — modify page HTML content

### Build Lifecycle
- `build.before` (action)
- `build.after` (action)
- `build.head_html` (filter) — inject CSS/meta into <head>
- `build.body_end_html` (filter) — inject JS before </body>
- `build.robots_txt` (filter) — modify robots.txt content
- `build.sitemap_urls` (filter) — add URLs to sitemap.xml
- `block.rendered_html` (filter) — modify block output

### Admin Panel
- `admin.sidebar_items` (filter) — add menu items
- `admin.dashboard_widgets` (filter) — add dashboard widgets
- `admin.styles` (filter) — enqueue CSS
- `admin.scripts` (filter) — enqueue JS
- `admin.head` (action) — inject into admin <head>
- `admin.footer` (action) — inject before admin </body>

### MCP Tools
- `mcp.tools_list` (filter) — register new MCP tools
- `mcp.handle_tool` (filter) — handle tool calls
- `mcp.tool_response` (filter) — modify tool responses
- `mcp.tool_called` (action) — notification when a tool is called

### Authentication & Permissions
- `auth.capabilities` (filter) — register custom permissions
- `user.login` (action) — user logged in
- `user.logout` (action) — user logged out
- `user.created` (action) — new user created

### Plugins
- `plugin.activated` (action) — plugin activated
- `plugin.deactivated` (action) — plugin deactivated
- `plugin.uninstalled` (action) — plugin uninstalled

### Webhooks & Cron
- `webhooks.events` (filter) — register custom webhook events
- `cron.tasks` (filter) — register scheduled tasks

### Blocks & Templates
- `block.available_types` (filter) — register custom block types
- `block.slot_types` (filter) — register custom slot types
- `block.rendered_html` (filter) — modify rendered block HTML
- `block.global_data_changed` (action) — global block data updated
- `page_template.available_types` (filter) — register custom templates
- `page_template.wrapper_html` (filter) — modify template wrapper
- `page_template.approved` (action) — template approved

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

## Premium Plugin License Verification

Premium plugins set `"premium": true` in the manifest. The PluginLoader automatically
verifies their license against plugins.joseconti.com before loading.

License data is stored in: `config/plugin_licenses/{plugin-id}.json.enc`

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

## Security Requirements for Plugins

1. **Never access the filesystem directly** — use `klytos_storage()`.
2. **Always sanitize HTML output** — use `htmlspecialchars()` or `Helpers::sanitizeHtml()`.
3. **Always validate input** — check types, lengths, and formats.
4. **Use capabilities for access control** — register via `auth.capabilities` filter.
5. **Never store secrets in cleartext** — use the encrypted storage.
6. **Include the ELv2 license header** in all PHP files if distributing.

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

## Translations — Plugin i18n

### Registering Translations

Place JSON translation files in `plugins/{plugin-id}/lang/`:

```
plugins/my-plugin/lang/
├── en.json
└── es.json
```

Register them in your main plugin file:

```php
klytos_register_translations('my-plugin', klytos_plugin_path('my-plugin', 'lang'));
```

### Translation File Format

Both flat dot-notation keys and nested arrays are supported:

```json
// FLAT FORMAT (recommended for plugins — simpler):
{
    "my_plugin.settings_title": "My Plugin Settings",
    "my_plugin.save": "Save Changes",
    "my_plugin.success": "Operation completed"
}

// NESTED FORMAT (also works):
{
    "my_plugin": {
        "settings_title": "My Plugin Settings",
        "save": "Save Changes"
    }
}
```

**IMPORTANT**: Use underscores (`_`) in translation keys, not hyphens. The key prefix should match a consistent namespace for your plugin (e.g. `my_plugin.`). The dot is the separator for nested lookup.

### Using Plugin Translations

```php
// Keys use dot-notation — the I18n system resolves them:
echo __('my_plugin.settings_title');  // → "My Plugin Settings"
echo __('my_plugin.success');         // → "Operation completed"

// With replacements:
echo __('my_plugin.greeting', ['name' => 'José']);  // → "Hello, José!"
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

## File Locations

- Klytos root: `/installer/` (configurable)
- Core: `/installer/core/`
- Plugins: `/installer/plugins/`
- Plugin backups: `/installer/plugins/.backups/`
- Admin: `/installer/admin/`
- Public output: `/installer/public/`
- Data (encrypted): `/installer/data/`
- Config: `/installer/config/`
