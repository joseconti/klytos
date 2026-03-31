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

## Complete Hook Catalog

### 1. System & Lifecycle

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `klytos.init` | action | `App $app` | core/app.php |
| `klytos_die` | action | `string $message, string $title, int $status` | core/helpers.php |
| `router.before_dispatch` | action | `string $route` | core/router.php |
| `router.after_dispatch` | action | `string $route` | core/router.php |

#### MCP Tools

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `mcp.tools_list` | filter | `array $tools` | core/mcp/tool-registry.php |
| `mcp.handle_tool` | filter | `mixed $result, string $toolName, array $params` | core/mcp/tool-registry.php |
| `mcp.tool_response` | filter | `array $response, string $toolName` | core/mcp/tool-registry.php |
| `mcp.tool_called` | action | `string $toolName, array $params` | core/mcp/tool-registry.php |

### 2. Authentication & Users

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `auth.before_login` | action | `string $username` | core/auth.php |
| `auth.after_login` | action | `string $username, string $userId` | core/auth.php |
| `auth.capabilities` | filter | `array $capabilities` | core/user-manager.php, core/helpers-global.php |
| `user.before_create` | action | `array $user` | core/user-manager.php |
| `user.created` | action | `array $user` | core/user-manager.php |
| `user.before_update` | action | `string $userId, array $data, array $user` | core/user-manager.php |
| `user.updated` | action | `array $user` | core/user-manager.php |
| `user.role_changed` | action | `string $userId, string $newRole, string $oldRole` | core/user-manager.php |
| `user.before_delete` | action | `string $userId, array $user` | core/user-manager.php |
| `user.deleted` | action | `string $userId, string $username` | core/user-manager.php |
| `user.login` | action | `array $user` | core/user-manager.php |
| `user.logout` | action | `string $username, string $userId` | core/auth.php |
| `user.ownership_transferred` | action | `string $currentOwnerId, string $newOwnerId` | core/user-manager.php |

#### Login Page

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `login.head` | action | — | admin/login.php |
| `login.before_form` | action | — | admin/login.php |
| `login.after_fields` | action | — | admin/login.php |
| `login.after_form` | action | — | admin/login.php |
| `login.footer` | action | — | admin/login.php |

### 3. Pages

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `page.before_save` | action | `array $page, string $action` ('create' or 'update') | core/page-manager.php |
| `page.after_save` | action | `array $page, string $action` | core/page-manager.php |
| `page.before_delete` | action | `string $slug` | core/page-manager.php |
| `page.after_delete` | action | `string $slug` | core/page-manager.php |
| `page.content` | filter | `string $html, array $page` | core/build-engine.php |

### 4. Post Types, Taxonomies, Terms & Custom Fields

#### Post Types

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `post_type.before_save` | action | `array $postType, string $action` ('create' or 'update') | core/post-type-manager.php |
| `post_type.after_save` | action | `array $postType, string $action` | core/post-type-manager.php |
| `post_type.before_delete` | action | `string $id` | core/post-type-manager.php |
| `post_type.after_delete` | action | `string $id` | core/post-type-manager.php |

#### Taxonomies

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `taxonomy.before_save` | action | `string $postTypeId, mixed $taxData, string $action` ('create' or 'update') | core/post-type-manager.php |
| `taxonomy.after_save` | action | `string $postTypeId, mixed $taxData, string $action` | core/post-type-manager.php |
| `taxonomy.after_delete` | action | `string $postTypeId, string $taxonomyId` | core/post-type-manager.php |

#### Terms

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `term.before_save` | action | `string $postTypeId, string $taxonomyId, mixed $termData, string $action` | core/post-type-manager.php |
| `term.after_save` | action | `string $postTypeId, string $taxonomyId, mixed $termData, string $action` | core/post-type-manager.php |
| `term.before_delete` | action | `string $postTypeId, string $taxonomyId, string $termSlug` | core/post-type-manager.php |
| `term.after_delete` | action | `string $postTypeId, string $taxonomyId, string $termSlug` | core/post-type-manager.php |

#### Custom Fields

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `custom_field.before_save` | action | `string $postTypeId, mixed $fieldData, string $action` ('create' or 'update') | core/post-type-manager.php |
| `custom_field.after_save` | action | `string $postTypeId, mixed $fieldData, string $action` | core/post-type-manager.php |
| `custom_field.before_delete` | action | `string $postTypeId, string $fieldId` | core/post-type-manager.php |
| `custom_field.after_delete` | action | `string $postTypeId, string $fieldId` | core/post-type-manager.php |
| `custom_field.after_reorder` | action | `string $postTypeId, array $fieldIds` | core/post-type-manager.php |

### 5. Blocks & Page Templates

#### Blocks

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `block.before_save` | action | `array $block` | core/block-manager.php |
| `block.after_save` | action | `array $block` | core/block-manager.php |
| `block.global_data_changed` | action | `string $blockId, mixed $data` | core/block-manager.php |
| `block.rendered_html` | filter | `string $html, string $blockId, array $data` | core/block-manager.php |
| `block.available_types` | filter | `array $types` | core/block-manager.php |
| `block.slot_types` | filter | `array $types` | core/block-manager.php |
| `block.css` | filter | `string $css, string $blockId` | core/build-engine.php |

#### Page Templates

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `page_template.before_save` | action | `array $template` | core/page-template-manager.php |
| `page_template.after_save` | action | `array $template` | core/page-template-manager.php |
| `page_template.approved` | action | `array $template` | core/page-template-manager.php |
| `page_template.available_types` | filter | `array $types` | core/page-template-manager.php |
| `page_template.wrapper_html` | filter | `string $html, string $type` | core/page-template-manager.php |
| `page_template.structure` | filter | `array $structure, string $type` | core/page-template-manager.php |
| `template_part.{$partName}` | filter | `mixed $value` — dynamic per template part name | core/template-resolver.php |

### 6. Build & Frontend

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `build.before` | action | — | core/build-engine.php |
| `build.after` | action | `int $pagesBuilt, array $errors` | core/build-engine.php |
| `build.assets_changed` | action | — | core/plugin-loader.php |
| `build.page.before` | action | `array $page` | core/build-engine.php |
| `build.page.after` | action | `array $page, string $outputPath` | core/build-engine.php |
| `build.page.output` | filter | `string $html, array $page` | core/build-engine.php |
| `build.head_html` | filter | `string $html` — inject into `<head>` | core/build-engine.php |
| `build.body_end_html` | filter | `string $html` — inject before `</body>` | core/build-engine.php |
| `build.sitemap_urls` | filter | `array $urls` | core/build-engine.php |
| `build.global_blocks` | filter | `array $cache` | core/build-engine.php |

### 7. Options & Metadata

#### Options

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `option.before_set` | action | `string $key, mixed $value, mixed $oldValue` | core/options-manager.php |
| `option.after_set` | action | `string $key, mixed $value, mixed $oldValue` | core/options-manager.php |
| `option.before_delete` | action | `string $key` | core/options-manager.php |
| `option.after_delete` | action | `string $key` | core/options-manager.php |
| `option.get` | filter | `mixed $value, string $key` | core/options-manager.php |

#### Metadata

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `meta.before_set` | action | `string $collection, string $entityId, string $key, mixed $value` | core/meta-manager.php |
| `meta.after_set` | action | `string $collection, string $entityId, string $key, mixed $value` | core/meta-manager.php |
| `meta.before_delete` | action | `string $collection, string $entityId, string $key` | core/meta-manager.php |
| `meta.after_delete` | action | `string $collection, string $entityId, string $key` | core/meta-manager.php |
| `meta.get` | filter | `mixed $value, string $collection, string $entityId, string $key` | core/meta-manager.php |

### 8. Plugins

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `plugin.loaded` | action | `string $pluginId, array $manifest` | core/plugin-loader.php |
| `plugin.installed` | action | `string $pluginId, bool $isUpdate` | core/plugin-loader.php |
| `plugin.activated` | action | `string $pluginId, array $manifest` | core/plugin-loader.php |
| `plugin.deactivated` | action | `string $pluginId, array $manifest` | core/plugin-loader.php |
| `plugin.uninstalled` | action | `string $pluginId` | core/plugin-loader.php |
| `plugin.before_delete` | action | `string $pluginId` | core/plugin-loader.php |
| `plugin.deleted` | action | `string $pluginId` | core/plugin-loader.php |
| `plugin.backup_created` | action | `string $pluginId, string $backupName` | core/plugin-loader.php |
| `plugin.restored` | action | `string $pluginId, string $backupName` | core/plugin-loader.php |
| `plugin.logs_enabled` | action | `string $pluginId` | core/plugin-loader.php |
| `plugin.logs_disabled` | action | `string $pluginId` | core/plugin-loader.php |

### 9. Logging

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `logger.before_write` | filter | `array $entry` | core/logger.php |
| `logger.after_write` | action | `array $entry, string $logFile` | core/logger.php |
| `logger.max_file_size` | filter | `int $maxBytes` | core/logger.php |
| `logger.log_format` | filter | `string $line, array $entry` | core/logger.php |
| `logger.log_files` | filter | `array $files` | core/logger.php |
| `logger.before_delete` | action | `string $filename` | core/logger.php |
| `logger.after_delete_all` | action | `int $deletedCount` | core/logger.php |
| `admin.logs.before` | action | — | admin/logs.php |
| `admin.logs.after` | action | — | admin/logs.php |
| `admin.logs_file_list` | filter | `array $files` | admin/logs.php |
| `admin.logs_toolbar` | filter | `string $html` | admin/logs.php |

### 10. Admin Panel

#### Layout (Header, Footer, Page Wrapper)

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `admin.head_meta` | action | `string $cspNonce` | admin/templates/header.php |
| `admin.head` | action | `string $cspNonce` | admin/templates/header.php |
| `admin.footer` | action | `string $cspNonce` | admin/templates/footer.php |
| `admin.page.before_content` | action | `string $currentPage` | admin/templates/sidebar.php |
| `admin.page.after_content` | action | `string $currentPage` | admin/templates/footer.php |
| `admin.page_title` | filter | `string $title` | admin/templates/header.php |
| `admin.theme` | filter | `string $theme` | admin/templates/header.php |
| `admin.stylesheets` | filter | `array $stylesheets` | admin/templates/header.php |

#### Sidebar

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `admin.sidebar.before` | action | — | admin/templates/sidebar.php |
| `admin.sidebar.after` | action | — | admin/templates/sidebar.php |
| `admin.sidebar.before_search` | action | — | admin/templates/sidebar.php |
| `admin.sidebar.after_search` | action | — | admin/templates/sidebar.php |
| `admin.sidebar.before_section` | action | `string $sectionName` (content, system, or plugin section) | admin/templates/sidebar.php |
| `admin.sidebar.after_section` | action | `string $sectionName` | admin/templates/sidebar.php |
| `admin.sidebar.footer` | action | — | admin/templates/sidebar.php |
| `admin.sidebar_items` | filter | `array $items` | admin/templates/sidebar.php |

#### Topbar

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `admin.topbar_before` | action | — | admin/templates/sidebar.php |
| `admin.topbar_after` | action | — | admin/templates/sidebar.php |
| `admin.topbar_left` | filter | `string $html` | admin/templates/sidebar.php |
| `admin.topbar_center` | filter | `string $html` | admin/templates/sidebar.php |
| `admin.topbar_right` | filter | `string $html` | admin/templates/sidebar.php |
| `admin.topbar_ai_button` | filter | `string $html` | admin/templates/sidebar.php |
| `admin.topbar_actions` | filter | `string $html` | admin/templates/sidebar.php |
| `admin.topbar_user_display` | filter | `string $label, array $currentUser` | admin/templates/sidebar.php |

#### Per-Page Hooks

Every admin page fires `admin.{pagename}.before` and `admin.{pagename}.after`. Available pages: `dashboard`, `pages`, `editor`, `settings`, `users`, `theme`, `assets`, `blocks`, `block_data`, `templates`, `plugins`, `analytics`, `security`, `mcp`, `webhooks`, `tasks`, `post_types`, `profile`.

#### Dashboard

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `admin.dashboard.before_stats` | action | — | admin/index.php |
| `admin.dashboard.after_stats` | action | — | admin/index.php |
| `admin.dashboard.before_widgets` | action | — | admin/index.php |
| `admin.dashboard.after_widgets` | action | — | admin/index.php |

#### Settings

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `admin.settings.before_save` | action | `string $section, array $_POST` | admin/settings.php |
| `admin.settings.after_save` | action | `string $section, array $_POST` | admin/settings.php |
| `admin.settings.before_section` | action | `string $section` (general, social, analytics, email, languages, appearance, editor, ai) | admin/settings.php |
| `admin.settings.after_section` | action | `string $section` | admin/settings.php |
| `admin.settings.render_custom_sections` | action | `array $siteConfig` | admin/settings.php |

#### Editor

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `editor.before_canvas` | action | `?array $page, bool $isEditing` | admin/page-editor.php |
| `editor.after_canvas` | action | `?array $page, bool $isEditing` | admin/page-editor.php |
| `editor.before_custom_fields` | action | `?array $page, bool $isEditing` | admin/page-editor.php |
| `editor.after_custom_fields` | action | `?array $page, bool $isEditing` | admin/page-editor.php |
| `editor.sidebar.before_seo` | action | `?array $page, bool $isEditing` | admin/page-editor.php |
| `editor.sidebar.after_seo` | action | `?array $page, bool $isEditing` | admin/page-editor.php |
| `editor.sidebar.after_panels` | action | `?array $page, bool $isEditing` | admin/page-editor.php |

#### Users & Profile

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `admin.users.edit_form.before_fields` | action | `array $users` | admin/users.php |
| `admin.users.edit_form.after_fields` | action | `array $users` | admin/users.php |
| `admin.profile.before_fields` | action | `array $user` | admin/profile.php |
| `admin.profile.after_fields` | action | `array $user` | admin/profile.php |

#### Plugins Admin

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `admin.plugins_before_table` | action | — | admin/plugins.php |
| `admin.plugins_after_table` | action | — | admin/plugins.php |
| `admin.plugins_page_scripts` | action | `string $cspNonce` | admin/plugins.php |
| `admin.plugins_column_{$key}` | action | `array $plugin` — dynamic per column key | admin/plugins.php |
| `admin.plugins_columns` | filter | `array $columns` | admin/plugins.php |
| `admin.plugins_page_actions` | filter | `array $bulkActions` | admin/plugins.php |
| `admin.plugins_row_data` | filter | `array $plugin` | admin/plugins.php |
| `admin.plugins_row_actions` | filter | `array $actions, string $pluginId, array $plugin` | admin/plugins.php |

#### Security

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `admin.security.before_2fa` | action | — | admin/security.php |
| `admin.security.after_2fa` | action | — | admin/security.php |

#### Webhooks Admin

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `admin.webhooks.before_form` | action | — | admin/webhooks.php |
| `admin.webhooks.after_form` | action | — | admin/webhooks.php |

### 10. AI & Chat

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `ai.key.configured` | action | `string $providerId` | core/ai/ai-key-manager.php |
| `ai.key.removed` | action | `string $providerId` | core/ai/ai-key-manager.php |
| `ai.chat.before_send` | action | `string $userId, array $messages, string $providerId` | core/ai/chat-engine.php |
| `ai.chat.after_send` | action | `string $userId, array $result, string $providerId` | core/ai/chat-engine.php |
| `ai.chat.error` | action | `string $providerId, Exception $e` | core/ai/chat-engine.php |
| `ai.chat.tool_executed` | action | `string $toolName, mixed $input, string $userId` | core/ai/chat-engine.php |
| `ai.system_prompt` | filter | `string $prompt, string $userId, array $site` | core/ai/chat-engine.php |
| `ai.tools_for_chat` | filter | `array $tools, string $userId` | core/ai/chat-engine.php |

### 11. Terminal

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `terminal.before_execute` | action | `string $commandName, array $args` | core/terminal-executor.php |
| `terminal.after_execute` | action | `string $commandName, string $output` | core/terminal-executor.php |
| `terminal.commands` | filter | `array $commands` | core/terminal-executor.php |
| `terminal.command_output` | filter | `string $output, string $commandName` | core/terminal-executor.php |
| `terminal.category_labels` | filter | `array $labels` | core/terminal-executor.php |

### 12. Mailer, Webhooks, Cron & Scheduler

#### Mailer

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `mailer.before_send` | action | `array $recipients, string $subject` | core/mailer.php |
| `mailer.after_send` | action | `array $recipients, string $subject, bool $result` | core/mailer.php |
| `mailer.send` | filter | `bool $handled, array $options` | core/mailer.php |
| `mailer.headers` | filter | `array $headers, array $options` | core/mailer.php |
| `mailer.html_template` | filter | `string $template, string $content, string $subject, string $siteName` | core/mailer.php |

#### Webhooks

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `webhook.before_create` | action | `array $data` | core/webhook-manager.php |
| `webhook.after_create` | action | `array $webhook` | core/webhook-manager.php |
| `webhook.before_delete` | action | `string $webhookId` | core/webhook-manager.php |
| `webhook.after_delete` | action | `string $webhookId` | core/webhook-manager.php |
| `webhooks.events` | filter | `array $events` | core/webhook-manager.php |

#### Cron & Tasks

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `cron.run` | action | `array $executed, array $errors` | core/cron-manager.php |
| `cron.tasks` | filter | `array $tasks` | core/cron-manager.php |
| `task.created` | action | `array $task` | core/task-manager.php |
| `task.completed` | action | `array $task` | core/task-manager.php |

#### Action Scheduler

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `scheduler.action_created` | action | `array $action` | core/action-scheduler.php |
| `scheduler.action_canceled` | action | `array $action` | core/action-scheduler.php |
| `scheduler.action_complete` | action | `array $action` | core/action-scheduler.php |
| `scheduler.action_failed` | action | `array $action, Exception $e` | core/action-scheduler.php |
| `scheduler.batch_complete` | action | `array $results` | core/action-scheduler.php |
| `{$action['hook']}` | action | `mixed ...$args` — dynamic, fires the scheduled action's hook | core/action-scheduler.php |

### 13. Assets, Theme & Analytics

#### Assets

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `asset.before_upload` | action | `string $filename, string $directory` | core/asset-manager.php |
| `asset.after_upload` | action | `array $result, string $filename` | core/asset-manager.php |
| `asset.before_delete` | action | `string $path` | core/asset-manager.php |
| `asset.after_delete` | action | `string $path` | core/asset-manager.php |
| `asset.allowed_types` | filter | `array $allowed` | core/helpers.php |

#### Theme

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `theme.before_save` | action | `mixed $data` | core/theme-manager.php |
| `theme.after_save` | action | `array $theme` | core/theme-manager.php |
| `theme.data` | filter | `array $themeData` | core/theme-manager.php |

#### Analytics & KSES

| Hook | Type | Arguments | Source |
|---|---|---|---|
| `analytics.event` | filter | `array $entry` | core/analytics-manager.php |
| `kses_post_allowed_tags` | filter | `array $tags` | core/helpers.php |

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

- Hook engine: `installer/core/hooks.php`
- Global wrappers: `installer/core/helpers-global.php`
- Full docs: `docs/KLYTOS-HOOKS-API.md`
