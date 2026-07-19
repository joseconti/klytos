---
name: Klytos Hooks Reference
description: Complete reference of all hooks (actions and filters) available in Klytos CMS for intercepting and extending functionality. Use when hooking into Klytos events, modifying behavior through filters, adding custom behavior at lifecycle points, or understanding the hook system and priorities.
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

### Bulk Removal

```php
klytos_remove_all_actions(string $hook): void
klytos_remove_all_filters(string $hook): void
```

### Debugging

```php
klytos_did_action(string $hook): int           // Times action was fired
klytos_get_fired_actions(): array              // ['hook' => fire_count, ...]
klytos_get_registered_hooks(): array           // ['actions' => ['hook' => count], 'filters' => [...]]
```

> **IMPORTANT**: ALWAYS use the global `klytos_*` functions. NEVER use `Hooks::` class methods directly.
> The `Hooks` class is an internal engine — plugins and core code must use the `klytos_*` wrappers.

---

## Priority System

- **1-9**: Runs BEFORE most plugins
- **10**: Default
- **11-99**: Runs AFTER most plugins

---

## Page Detection Helpers

```php
klytos_current_admin_page(): string       // 'settings', 'users', 'dashboard', etc.
klytos_is_admin_page(string $page): bool  // Exact ('settings') or prefix ('settings.*')
```

---

## Quick Hook Reference

### Page Lifecycle
- `page.before_save`, `page.after_save`, `page.before_delete`, `page.after_delete` (actions)
- `page.content` (filter)

### Build Process
- `build.before`, `build.after`, `build.page.before`, `build.page.after` (actions)
- `build.page.output`, `build.head_html`, `build.body_end_html`, `build.sitemap_urls` (filters)

### LLM Discoverability (Build)
- `build.llms_generated` (action) — after llms.txt + llms-full.txt + .html.md are generated, receives `$stats` array
- `build.llms_txt` (filter) — modify llms.txt content before writing
- `build.llms_full_txt` (filter) — modify llms-full.txt content before writing
- `build.page_markdown` (filter) — modify per-page Markdown before writing .html.md, receives `($md, $page)`
- `build.llms_pages` (filter) — modify/reorder/add pages array for LLM file generation

### MCP Tools
- `mcp.tools_list` (filter) — register new tools
- `mcp.handle_tool` (filter) — handle tool calls
- `mcp.tool_response` (filter) — modify responses
- `mcp.tool_called` (action) — tool was called

### Authentication and authorization
- `auth.before_login`, `auth.after_login` (actions)
- `auth.capabilities` (filter) — the capability matrix; add or re-scope a permission here
- `auth.access_denied` (action) — fires immediately before a request is refused; receives
  `( int $status, string $code, string $surface )`. The **audit hook**: log or alert on refusals.
  It deliberately **cannot reverse the decision**
- `admin.gate_map` (filter) — the admin gate map, `path relative to admin/ => capability`. Add an
  entry to gate your own admin file; a file with no entry is **denied by default**

### Public comments (the one anonymous write surface)
- `comment.before_save`, `comment.after_save` (actions) — fired by `CommentManager`
- `comment.rate_limit` (filter) — `( int $max, string $clientIp )`, comments allowed per
  60-second window from one address. Default 2. **Cannot raise the hard flood ceiling**, which is
  enforced before plugins are loaded and is therefore not filterable
- `comment.notification_recipient` (filter) — `( string $email, array $comment )`, who is told
  about a new comment. Return `''` to disable the notification
- `comment.honeypot_rejected` (action) — `( array{page_slug: string, ip: string} )`, a bot filled
  the hidden field
- `comment.rate_limited` (action) — `( string $clientIp )`, a submission was refused

The last two are actions rather than filters on purpose: a listener able to turn a refusal into an
acceptance would put anti-spam policy in third-party hands. Full reference:
`docs/reference/public-comments.md`.

### Admin Panel
- `admin.sidebar_items` (filter) — customize sidebar
- `admin.head`, `admin.footer` (actions)
- `admin.{page}.before`, `admin.{page}.after` (actions)

### Blocks & Templates
- `block.before_save`, `block.after_save` (actions)
- `block.rendered_html` (filter)
- `page_template.before_save`, `page_template.after_save`, `page_template.approved` (actions)

### Plugins
- `plugin.activated`, `plugin.deactivated` (actions)
- `plugin.loaded`, `plugin.installed` (actions)

---

## Source Files

- Hook engine: `installer/core/hooks.php`
- Global wrappers: `installer/core/helpers-global.php`

**For the complete hook catalog with all arguments and sources, see the `references/complete-hooks.md` file.**
