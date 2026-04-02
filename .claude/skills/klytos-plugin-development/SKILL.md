---
name: Klytos Plugin Development
description: Complete guide for developing Klytos CMS plugins including structure, entry points, MCP tools, admin pages, hooks, routes, and best practices. Use when creating, modifying, extending Klytos functionality, adding MCP tools, admin pages, hooks, filters, or debugging plugins.
---

# Klytos Plugin Development Guide

## Architecture Overview

Klytos is an **AI-First CMS** controlled via MCP (Model Context Protocol). Plugins extend core functionality through a WordPress-inspired hook system (actions + filters) WITHOUT modifying core files.

**Key principle**: Every feature should be exposed as an MCP tool FIRST, admin UI second.

---

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

---

## Plugin Structure

```
plugins/{plugin-id}/
├── {plugin-id}.php      ← REQUIRED: identification + entry point (PHP header)
├── klytos-plugin.json   ← OPTIONAL: extended metadata
├── install.php          ← Optional: runs on first activation
├── deactivate.php       ← Optional: runs on deactivation
├── uninstall.php        ← Optional: removes plugin data permanently
├── admin/               ← Optional: admin page views
├── assets/              ← Optional: CSS, JS, images (publicly accessible)
├── lang/                ← Optional: translation files
├── src/                 ← Optional: PHP source classes
└── migrations/          ← Optional: data migrations
```

---

## PHP Header (Canonical Identity)

The main PHP file MUST contain a docblock with at least `Plugin Name:`. All other fields are optional.

```php
<?php
/**
 * Plugin Name: My Plugin
 * Plugin URI: https://example.com/my-plugin
 * Description: What this plugin does.
 * Version: 1.0.0
 * Author: Author Name
 * Requires Klytos: 0.15.0
 * Requires PHP: 8.1
 * License: ELv2
 * Text Domain: my-plugin
 * Premium: false
 * Logs: true
 */
```

---

## Extended Manifest (klytos-plugin.json)

For complex structured data that doesn't fit in a PHP header comment. The `id` field is NOT needed — it's derived from the directory name.

```json
{
  "permissions": ["pages.edit"],
  "admin_pages": [
    {
      "id": "settings",
      "title": "My Plugin Settings",
      "icon": "P",
      "position": 86
    }
  ],
  "mcp_tools": ["my_plugin_do_something"]
}
```

---

## Main Plugin File — Entry Point

The `{plugin-id}.php` file is both the identification AND the entry point. All hooks are registered here.

```php
<?php
/**
 * Plugin Name: My Plugin
 * Version: 1.0.0
 */

// 1. Register admin sidebar menu item
klytos_add_filter('admin.sidebar_items', function (array $items): array {
    $items[] = [
        'id'       => 'my-plugin',
        'title'    => 'My Plugin',
        'url'      => klytos_admin_url('plugins/my-plugin/admin/settings.php'),
        'icon'     => 'P',
        'position' => 86,
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
    ];
    return $tools;
});

// 3. Handle MCP tool calls
klytos_add_filter('mcp.handle_tool', function (mixed $result, string $toolName, array $params): mixed {
    if ($toolName !== 'my_plugin_do_something') {
        return $result;
    }

    return [
        'content' => [['type' => 'text', 'text' => 'Done!']],
        'isError' => false,
    ];
}, 10);

// 4. Register translations
klytos_register_translations('my-plugin', klytos_plugin_path('my-plugin', 'lang'));

// 5. Hook into page lifecycle
klytos_add_action('page.after_save', function (array $page, string $action): void {
    klytos_log('info', 'My plugin: page saved', ['slug' => $page['slug']]);
});
```

---

## Registering Admin Pages

Use `klytos_register_admin_page()` to add sidebar items:

```php
klytos_register_admin_page( 'my-plugin', [
    'id'         => 'settings',
    'title'      => 'My Plugin Settings',
    'icon'       => 'P',
    'position'   => 86,
    'capability' => 'plugins.manage',
] );
```

The PHP file at `plugins/my-plugin/admin/settings.php` renders inside the admin layout automatically. It receives `$app`, `$auth`, `$pluginId`, `$pageName`, `$manifest`.

---

## Core Service Accessors

```php
klytos_storage()          → StorageInterface (read/write encrypted data)
klytos_app()              → App instance
klytos_auth()             → Auth instance
klytos_config($key, $default) → Read config value (dot notation)
klytos_version()          → Current Klytos version
klytos_is_admin()         → True if in admin context
klytos_is_mcp()           → True if in MCP context
klytos_current_user()     → Current user array or null
klytos_has_permission($perm) → Permission check
klytos_log($level, $msg, $ctx) → Write to log file
```

---

## Available Hooks

### Page Lifecycle
- `page.before_save`, `page.after_save`, `page.before_delete`, `page.after_delete`
- `page.content` (filter) — modify page HTML content

### Build Lifecycle
- `build.before`, `build.after`, `build.page.before`, `build.page.after` (actions)
- `build.head_html`, `build.body_end_html` (filters) — inject CSS/JS

### Admin Panel
- `admin.sidebar_items` (filter) — add menu items
- `admin.head`, `admin.footer` (actions) — inject into admin HTML
- `admin.{page}.before`, `admin.{page}.after` (actions) — per-page hooks

### Blocks & Templates
- `block.before_save`, `block.after_save`, `block.rendered_html`
- `page_template.before_save`, `page_template.after_save`

### Plugins
- `plugin.activated`, `plugin.deactivated`, `plugin.loaded`

---

## Internationalization (i18n)

Place JSON translation files in `plugins/{plugin-id}/lang/`:

```
plugins/my-plugin/lang/
├── en.json
└── es.json
```

Register them:

```php
klytos_register_translations('my-plugin', klytos_plugin_path('my-plugin', 'lang'));
```

Translation file format (flat recommended):

```json
{
    "my_plugin.settings_title": "My Plugin Settings",
    "my_plugin.save": "Save Changes"
}
```

Use translations:

```php
echo __('my_plugin.settings_title');  // "My Plugin Settings"
echo __('my_plugin.greeting', ['name' => 'Jose']);
```

---

## Plugin Assets (CSS, JS, Images)

Plugin static assets live in `plugins/{plugin-id}/assets/` and are publicly accessible via the web.

### Building Asset URLs

```php
// CORRECT — full path from plugin root:
klytos_plugin_url('my-plugin', 'assets/css/style.css')
// → /admin/plugins/my-plugin/assets/css/style.css

// Loading in admin pages:
$cssUrl = klytos_plugin_url('my-plugin', 'assets/css/style.css');
?>
<link rel="stylesheet" href="<?php echo klytos_esc_url($cssUrl); ?>" nonce="<?php echo klytos_esc_attr($cspNonce); ?>">
<script src="<?php echo klytos_esc_url($jsUrl); ?>" nonce="<?php echo klytos_esc_attr($cspNonce); ?>"></script>
```

### CRITICAL: CSP Nonce Requirement

All `<script>` and `<link>` tags MUST include a `nonce` attribute:

```html
<!-- CORRECT: Will load successfully -->
<script src="..." nonce="<?php echo klytos_esc_attr($cspNonce); ?>"></script>

<!-- WRONG: Will be blocked by CSP -->
<script src="..."></script>
```

---

## Plugin Logging

Plugins opt into logging by declaring `Logs: true` in the PHP header. When declared, an "Enable Logs" action appears in the plugin management page.

```php
/**
 * Plugin Name: My Plugin
 * Logs: true
 */
```

Writing logs:

```php
klytos_log('info', 'Order processed', ['order_id' => 42], 'my-plugin');
klytos_log_error('Payment failed', ['gateway' => 'stripe'], 'my-plugin');
klytos_log_warning('Rate limit approaching', [], 'my-plugin');
klytos_log_info('Cache refreshed', [], 'my-plugin');
```

---

## Storage Pattern for Plugin Data

```php
// Read/write plugin-specific data
$storage = klytos_storage();

// Write plugin data to its own collection
$storage->write('my-plugin-data', 'settings', [
    'api_key' => 'xxx',
    'enabled' => true,
]);

// Read it back
$data = $storage->read('my-plugin-data', 'settings');
```

---

## Security Requirements

1. **Never access the filesystem directly** — use `klytos_storage()`
2. **Always sanitize HTML output** — use `htmlspecialchars()` or `Helpers::sanitizeHtml()`
3. **Always validate input** — check types, lengths, and formats
4. **Use capabilities for access control** — register via `auth.capabilities` filter
5. **Never store secrets in cleartext** — use the encrypted storage
6. **Include the ELv2 license header** in all PHP files if distributing

---

## Troubleshooting

### Error: "Requires Klytos X+, current: X-beta.Y"

In semver, pre-release versions are lower than the release:
- `0.14.0-beta.4 < 0.14.0`

Always set `Requires Klytos` to the OLDEST version you actually need, not the current one.

```php
/**
 * Plugin Name: My Plugin
 * Requires Klytos: 0.13.0     ← Use the last stable, not the current beta
 */
```

### Error: Plugin assets return 403 (Forbidden)

The `.htaccess` in the `plugins/` directory blocks access to executable files. Plugin PHP files are only executed server-side by the PluginLoader (`require_once`), never accessed directly via URL.

### Error: Plugin JS blocked by Content-Security-Policy

The `<script>` tag is missing the CSP nonce attribute. Always include `nonce="<?php echo klytos_esc_attr($cspNonce); ?>"`

---

## File Locations

- Klytos root: `/installer/` (configurable)
- Core: `/installer/core/`
- Plugins: `/installer/plugins/`
- Admin: `/installer/admin/`
- Data (encrypted): `/installer/data/`
- Config: `/installer/config/`

**For advanced topics like premium licensing, webhooks, version requirements, and more, see the `references/advanced-features.md` file.**
