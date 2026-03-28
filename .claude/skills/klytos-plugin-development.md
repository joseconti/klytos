---
name: klytos-plugin-development
description: Complete guide for developing Klytos CMS plugins. Use when creating, modifying, or debugging plugins for Klytos.
trigger: When the user asks to create a plugin, extend Klytos functionality, add MCP tools, add admin pages, or register hooks/filters.
---

# Klytos Plugin Development Guide

## Architecture Overview

Klytos is an **AI-First CMS** controlled via MCP (Model Context Protocol). Plugins extend core functionality through a WordPress-inspired hook system (actions + filters) WITHOUT modifying core files.

**Key principle**: Every feature should be exposed as an MCP tool FIRST, admin UI second.

## Plugin Structure

```
plugins/{plugin-id}/
├── klytos-plugin.json   ← REQUIRED: manifest with metadata
├── init.php             ← REQUIRED: entry point, registers all hooks
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
└── src/                 ← Optional: PHP source classes
    └── MyManager.php
```

## Manifest (klytos-plugin.json)

```json
{
  "id": "my-plugin",
  "name": "My Plugin",
  "version": "1.0.0",
  "description": "What this plugin does",
  "author": "Author Name",
  "author_url": "https://example.com",
  "requires_klytos": "2.0.0",
  "requires_php": "8.1",
  "premium": false,
  "item_name": "My Plugin",
  "permissions": ["pages.edit"],
  "admin_page": {
    "title": "My Plugin",
    "icon": "P",
    "position": 86,
    "section": "system"
  },
  "mcp_tools": ["my_plugin_do_something"]
}
```

**IMPORTANT**: The `id` field MUST match the directory name exactly.

## init.php — Entry Point

This is where ALL hooks are registered. It runs every time Klytos loads (if the plugin is active).

```php
<?php
// plugins/my-plugin/init.php

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

### Actions (fire-and-forget)
```php
klytos_add_action(string $hook, callable $callback, int $priority = 10): void
klytos_do_action(string $hook, mixed ...$args): void
klytos_remove_action(string $hook, callable $callback): bool
klytos_has_action(string $hook): bool
```

### Filters (modify data)
```php
klytos_add_filter(string $hook, callable $callback, int $priority = 10): void
klytos_apply_filters(string $hook, mixed $value, mixed ...$args): mixed
klytos_remove_filter(string $hook, callable $callback): bool
klytos_has_filter(string $hook): bool
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

## Security Requirements for Plugins

1. **Never access the filesystem directly** — use `klytos_storage()`.
2. **Always sanitize HTML output** — use `htmlspecialchars()` or `Helpers::sanitizeHtml()`.
3. **Always validate input** — check types, lengths, and formats.
4. **Use capabilities for access control** — register via `auth.capabilities` filter.
5. **Never store secrets in cleartext** — use the encrypted storage.
6. **Include the ELv2 license header** in all PHP files if distributing.

## File Locations

- Klytos root: `/installer/` (configurable)
- Core: `/installer/core/`
- Plugins: `/installer/plugins/`
- Admin: `/installer/admin/`
- Public output: `/installer/public/`
- Data (encrypted): `/installer/data/`
- Config: `/installer/config/`
