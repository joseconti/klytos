---
name: klytos-hooks-reference
description: Complete reference of all hooks (actions and filters) available in Klytos CMS. Use when extending the CMS via plugins, modifying behavior, or intercepting events.
trigger: When the user needs to hook into Klytos events, modify data through filters, add custom behavior, or understand the hook system.
---

# Klytos Hooks Reference

## Actions vs Filters

| | Actions | Filters |
|---|---|---|
| **Purpose** | Execute code at specific points | Modify and return data |
| **Return** | None (void) | Modified value |
| **Register** | `klytos_add_action()` | `klytos_add_filter()` |
| **Fire** | `klytos_do_action()` | `klytos_apply_filters()` |

---

## Hook API

```php
klytos_add_action(string $hook, callable $callback, int $priority = 10): void
klytos_do_action(string $hook, mixed ...$args): void
klytos_add_filter(string $hook, callable $callback, int $priority = 10): void
klytos_apply_filters(string $hook, mixed $value, mixed ...$args): mixed
klytos_remove_action(string $hook, callable $callback): bool
klytos_remove_filter(string $hook, callable $callback): bool
klytos_has_action(string $hook): bool
klytos_has_filter(string $hook): bool
```

### Debugging

```php
Hooks::getRegisteredHooks(): array  // ['actions' => ['hook' => count], 'filters' => [...]]
Hooks::getFiredActions(): array     // ['hook' => fire_count, ...]
Hooks::didAction(string $hook): int // Times action was fired
```

---

## Priority System

- **1-9**: Runs BEFORE most plugins
- **10**: Default
- **11-99**: Runs AFTER most plugins

---

## Complete Hook Catalog

### Page Lifecycle

| Hook | Type | Arguments |
|---|---|---|
| `page.before_save` | action | `array $page, string $action` ('create' or 'update') |
| `page.after_save` | action | `array $page, string $action` |
| `page.before_delete` | action | `string $slug` |
| `page.after_delete` | action | `string $slug` |
| `page.content` | filter | `string $html` |

### Build Lifecycle

| Hook | Type | Arguments |
|---|---|---|
| `build.before` | action | — |
| `build.after` | action | — |
| `build.head_html` | filter | `string $html` — inject into `<head>` |
| `build.body_end_html` | filter | `string $html` — inject before `</body>` |
| `build.robots_txt` | filter | `string $content` |
| `build.sitemap_urls` | filter | `array $urls` |
| `block.rendered_html` | filter | `string $html` |

### Admin Panel

| Hook | Type | Arguments |
|---|---|---|
| `admin.sidebar_items` | filter | `array $items` |
| `admin.dashboard_widgets` | filter | `array $widgets` |
| `admin.styles` | filter | `string $html` |
| `admin.scripts` | filter | `string $html` |
| `admin.head` | action | — |
| `admin.footer` | action | — |

### MCP Tools

| Hook | Type | Arguments |
|---|---|---|
| `mcp.tools_list` | filter | `array $tools` |
| `mcp.handle_tool` | filter | `mixed $result, string $toolName, array $params` |
| `mcp.tool_response` | filter | `array $response, string $toolName` |
| `mcp.tool_called` | action | `string $toolName, array $params` |

### Authentication & Permissions

| Hook | Type | Arguments |
|---|---|---|
| `auth.capabilities` | filter | `array $capabilities` |
| `user.login` | action | `array $user` |
| `user.logout` | action | `string $userId` |
| `user.created` | action | `array $user` |

### Plugins

| Hook | Type | Arguments |
|---|---|---|
| `plugin.activated` | action | `string $pluginId` |
| `plugin.deactivated` | action | `string $pluginId` |
| `plugin.uninstalled` | action | `string $pluginId` |

### Webhooks & Cron

| Hook | Type | Arguments |
|---|---|---|
| `webhooks.events` | filter | `array $events` |
| `cron.tasks` | filter | `array $tasks` |

### Options

| Hook | Type | Arguments |
|---|---|---|
| `option.before_set` | action | `string $key, mixed $value` |
| `option.after_set` | action | `string $key, mixed $value` |
| `option.get` | filter | `mixed $value, string $key` |

### Metadata

| Hook | Type | Arguments |
|---|---|---|
| `meta.before_set` | action | `string $collection, string $entityId, string $key, mixed $value` |
| `meta.after_set` | action | `string $collection, string $entityId, string $key, mixed $value` |
| `meta.get` | filter | `mixed $value, string $collection, string $entityId, string $key` |

### Blocks & Templates

| Hook | Type | Arguments |
|---|---|---|
| `block.before_save` | action | `array $block` |
| `block.after_save` | action | `array $block` |
| `block.available_types` | filter | `array $types` |
| `block.slot_types` | filter | `array $types` |
| `block.rendered_html` | filter | `string $html` |
| `block.global_data_changed` | action | `string $blockId, mixed $data` |
| `page_template.available_types` | filter | `array $types` |
| `page_template.wrapper_html` | filter | `string $html` |
| `page_template.approved` | action | `array $template` |

### Post Types

| Hook | Type | Arguments |
|---|---|---|
| `post_type.before_save` | action | `array $postType` |
| `post_type.after_save` | action | `array $postType` |

### KSES

| Hook | Type | Arguments |
|---|---|---|
| `kses_post_allowed_tags` | filter | `array $tags` |

### System

| Hook | Type | Arguments |
|---|---|---|
| `klytos.init` | action | — (fired after boot) |
| `klytos_die` | action | `string $message, string $title, int $status` |

---

## Creating Custom Hooks

```php
// Fire an action in your plugin
klytos_do_action('my_plugin.data_imported', $count, $errors);

// Apply a filter in your plugin
$template = klytos_apply_filters('my_plugin.email_template', $default, $context);

// Other plugins can hook in:
klytos_add_action('my_plugin.data_imported', function (int $count, array $errors): void {
    klytos_log('info', "Imported {$count} records");
});
```

---

## Source Files

- Hook engine: `core/hooks.php`
- Global wrappers: `core/helpers-global.php`
