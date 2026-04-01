---
name: klytos-mcp-tools
description: Guide for adding MCP (Model Context Protocol) tools to Klytos CMS. This is the MOST IMPORTANT skill — everything added to Klytos MUST be configurable via MCP.
trigger: When the user needs to register MCP tools, handle tool calls, extend the MCP API, or understand how the MCP system works.
---

# Klytos MCP Tools — Adding AI-Controllable Functionality

## GOLDEN RULE

**Everything added to Klytos MUST be configurable via MCP.** MCP is the PRIMARY interface of this AI-First CMS. The admin panel is secondary. If a feature cannot be controlled by an AI via MCP, it is incomplete.

---

## When to Use This Skill

Use this reference whenever you need to:
- Add a new MCP tool to a plugin
- Add a new MCP tool to the core
- Understand how MCP tools are registered and executed
- Build AI-controllable features

---

## MCP Tool Registration (Plugin Pattern)

Plugins register MCP tools using three hooks:

### Step 1: Register the Tool (mcp.tools_list)

```php
klytos_add_filter('mcp.tools_list', function (array $tools): array {
    $tools[] = [
        'name'        => 'my_plugin_do_something',
        'description' => 'Does something useful. Describe clearly what the tool does so the AI knows when to use it.',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => [
                'param1' => [
                    'type'        => 'string',
                    'description' => 'First parameter — be descriptive.',
                ],
                'param2' => [
                    'type'        => 'integer',
                    'description' => 'Second parameter.',
                ],
                'status' => [
                    'type'        => 'string',
                    'enum'        => ['active', 'inactive'],
                    'description' => 'Filter by status.',
                ],
            ],
            'required' => ['param1'],
        ],
        'annotations' => [
            'title'           => 'Do Something',
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'idempotentHint'  => false,
            'openWorldHint'   => false,
        ],
    ];
    return $tools;
});
```

### Step 2: Handle Tool Calls (mcp.handle_tool)

```php
klytos_add_filter('mcp.handle_tool', function (mixed $result, string $toolName, array $params): mixed {
    if ($toolName !== 'my_plugin_do_something') {
        return $result; // Not our tool — pass through
    }

    try {
        // 1. Sanitize inputs
        $param1 = klytos_sanitize_text($params['param1'] ?? '');
        $param2 = klytos_sanitize_int($params['param2'] ?? 0);

        // 2. Validate
        if ($param1 === '') {
            throw new \InvalidArgumentException('param1 is required');
        }

        // 3. Execute logic
        $data = doSomethingUseful($param1, $param2);

        // 4. Return success response
        return [
            'content' => [
                ['type' => 'text', 'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)],
            ],
            'isError' => false,
        ];
    } catch (\Exception $e) {
        // 5. Return error response
        return [
            'content' => [
                ['type' => 'text', 'text' => 'Error: ' . $e->getMessage()],
            ],
            'isError' => true,
        ];
    }
}, 10);
```

### Step 3: Declare in Manifest (klytos-plugin.json)

```json
{
    "id": "my-plugin",
    "name": "My Plugin",
    "version": "1.0.0",
    "mcp_tools": ["my_plugin_do_something"]
}
```

---

## MCP Tool Registration (Core Pattern)

For core tools, use the ToolRegistry directly:

```php
// In core/mcp/tools/my-feature-tools.php
$registry->register(
    'klytos_my_feature',                    // Tool name
    'Does something in the core.',          // Description
    [                                       // Schema properties
        'param1' => ['type' => 'string', 'description' => 'Parameter description'],
    ],
    function (array $params, App $app): mixed {  // Handler
        $result = $app->getMyManager()->doSomething($params['param1']);
        return ['success' => true, 'data' => $result];
    },
    [                                       // Annotations
        'readOnlyHint'    => false,
        'destructiveHint' => false,
        'idempotentHint'  => true,
    ],
    ['param1']                              // Required properties
);
```

---

## Tool Annotations

| Annotation | Type | Description |
|---|---|---|
| `readOnlyHint` | bool | `true` = does NOT modify state (safe to call anytime) |
| `destructiveHint` | bool | `true` = may delete or irreversibly modify data |
| `idempotentHint` | bool | `true` = safe to call multiple times with same params |
| `openWorldHint` | bool | `true` = accepts arbitrary/unknown input parameters |

### Examples

```php
// List tools (read-only, safe)
['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]

// Create tool (modifies state, not idempotent)
['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false]

// Delete tool (destructive)
['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true]

// Update tool (modifies state, idempotent)
['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true]
```

---

## Response Format

All MCP tools MUST return this format:

```php
// Success
[
    'content' => [
        ['type' => 'text', 'text' => 'Human-readable result or JSON data'],
    ],
    'isError' => false,
]

// Error
[
    'content' => [
        ['type' => 'text', 'text' => 'Error: description of what went wrong'],
    ],
    'isError' => true,
]
```

For structured data, return JSON in the text field:

```php
return [
    'content' => [
        ['type' => 'text', 'text' => json_encode([
            'success' => true,
            'items'   => $items,
            'total'   => count($items),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)],
    ],
    'isError' => false,
];
```

---

## MCP Hooks

| Hook | Type | Arguments | Description |
|---|---|---|---|
| `mcp.tools_list` | filter | `array $tools` | Register new tools |
| `mcp.handle_tool` | filter | `mixed $result, string $toolName, array $params` | Handle tool calls |
| `mcp.tool_response` | filter | `array $response, string $toolName` | Modify responses before sending |
| `mcp.tool_called` | action | `string $toolName, array $params` | Audit: tool was called |

---

## Core MCP Tools Catalog (60+)

### Pages
- `klytos_create_page` — Create new page/custom post type entry
- `klytos_update_page` — Update existing page
- `klytos_delete_page` — Delete page by slug
- `klytos_get_page` — Get page by slug
- `klytos_list_pages` — List pages with filters

### Site Configuration
- `klytos_get_site_config` — Get global config
- `klytos_set_site_config` — Update global config

### Theme
- `klytos_get_theme` — Get theme configuration
- `klytos_set_theme` — Update full theme
- `klytos_set_colors` — Update color palette
- `klytos_set_fonts` — Update fonts
- `klytos_set_layout` — Update layout settings

### Users
- `klytos_list_users` — List users
- `klytos_create_user` — Create user
- `klytos_update_user` — Update user
- `klytos_reset_user_password` — Reset password
- `klytos_force_logout_user` — Force logout

### Custom Post Types
- `klytos_create_post_type` — Create post type
- `klytos_update_post_type` — Update post type
- `klytos_delete_post_type` — Delete post type
- `klytos_get_post_type` / `klytos_list_post_types`
- `klytos_add_taxonomy` / `klytos_update_taxonomy` / `klytos_delete_taxonomy`
- `klytos_add_term` / `klytos_update_term` / `klytos_delete_term` / `klytos_list_terms`

### Custom Fields
- `klytos_get_field_types` — List 27 field types
- `klytos_add_custom_field` / `klytos_update_custom_field` / `klytos_remove_custom_field`
- `klytos_set_bulk_field_values` — Set values on entries

### Menus
- `klytos_get_menu` / `klytos_set_menu`
- `klytos_add_menu_item` / `klytos_remove_menu_item`

### Templates
- `klytos_create_page_template` / `klytos_get_page_template` / `klytos_list_page_templates`
- `klytos_add_block_to_template` / `klytos_remove_block_from_template`

### Blocks
- `klytos_create_block` / `klytos_update_block` / `klytos_get_block` / `klytos_list_blocks` / `klytos_delete_block`

### Assets
- `klytos_upload_asset` / `klytos_list_assets` / `klytos_delete_asset`

### Build
- `klytos_build_site` — Build entire static site
- `klytos_build_page` — Build single page
- `klytos_preview_page` — Live preview
- `klytos_get_build_status`

### Scheduler
- `klytos_schedule_single_action` / `klytos_schedule_recurring_action`
- `klytos_cancel_scheduled_action` / `klytos_list_scheduled_actions` / `klytos_get_scheduler_status`

### Webhooks
- `klytos_create_webhook` / `klytos_list_webhooks` / `klytos_delete_webhook`
- `klytos_list_webhook_events` / `klytos_test_webhook`

### Consent Manager
- `klytos_get_consent_config` / `klytos_set_consent_config` — Read/update consent configuration
- `klytos_list_consent_declarations` / `klytos_add_consent_declaration` / `klytos_delete_consent_declaration` — Manage plugin cookie/script declarations
- `klytos_get_consent_audit` — Full audit report grouped by category

### Version History
- `klytos_list_versions` / `klytos_get_version` / `klytos_restore_version` / `klytos_diff_versions`

### Plugins
- `klytos_list_plugins` / `klytos_activate_plugin` / `klytos_deactivate_plugin`

### Analytics
- `klytos_get_analytics` / `klytos_get_top_pages`

### Tasks
- `klytos_list_tasks` / `klytos_get_task` / `klytos_create_task` / `klytos_update_task` / `klytos_complete_task`

### AI
- `klytos_generate_image` / `klytos_list_ai_images`
- `klytos_ai_get_config` / `klytos_ai_list_providers` / `klytos_ai_get_usage`

### Guides
- `klytos_list_guides` / `klytos_get_guide`

---

## Complete Plugin Example with MCP

```php
<?php
// plugins/analytics-export/init.php
declare(strict_types=1);

// 1. Register MCP tool
klytos_add_filter('mcp.tools_list', function (array $tools): array {
    $tools[] = [
        'name'        => 'analytics_export_generate',
        'description' => 'Generate an analytics export for a date range. Returns CSV data.',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => [
                'start_date' => ['type' => 'string', 'description' => 'Start date (YYYY-MM-DD)'],
                'end_date'   => ['type' => 'string', 'description' => 'End date (YYYY-MM-DD)'],
                'format'     => ['type' => 'string', 'enum' => ['csv', 'json'], 'description' => 'Export format'],
            ],
            'required' => ['start_date', 'end_date'],
        ],
        'annotations' => ['readOnlyHint' => true, 'title' => 'Export Analytics'],
    ];
    return $tools;
});

// 2. Handle tool calls
klytos_add_filter('mcp.handle_tool', function (mixed $result, string $name, array $params): mixed {
    if ($name !== 'analytics_export_generate') return $result;

    $startDate = klytos_sanitize_text($params['start_date'] ?? '');
    $endDate   = klytos_sanitize_text($params['end_date'] ?? '');
    $format    = $params['format'] ?? 'json';

    // Fetch analytics data
    $analytics = klytos_app()->getAnalyticsManager();
    $data = $analytics->getRange($startDate, $endDate);

    if ($format === 'csv') {
        $csv = "date,page,views\n";
        foreach ($data as $row) {
            $csv .= "{$row['date']},{$row['page']},{$row['views']}\n";
        }
        return ['content' => [['type' => 'text', 'text' => $csv]], 'isError' => false];
    }

    return [
        'content' => [['type' => 'text', 'text' => json_encode($data, JSON_PRETTY_PRINT)]],
        'isError' => false,
    ];
}, 10);

// 3. Also add admin page (secondary interface)
klytos_add_filter('admin.sidebar_items', function (array $items): array {
    $items[] = [
        'id'         => 'analytics-export',
        'title'      => 'Export Analytics',
        'url'        => klytos_admin_url('plugins/analytics-export/admin/export.php'),
        'icon'       => 'fa-solid fa-file-export',
        'position'   => 61,
        'section'    => 'system',
        'capability' => 'analytics.view',
    ];
    return $items;
});
```

---

## Security Checklist for MCP Tools

- [ ] Sanitize ALL input parameters
- [ ] Validate required fields
- [ ] Check permissions where appropriate
- [ ] Return proper error messages (no stack traces)
- [ ] Use appropriate annotations (readOnly, destructive, idempotent)
- [ ] Declare tool in `klytos-plugin.json` under `mcp_tools`
- [ ] Write clear descriptions so AI knows when to use the tool

---

## Source Files

- Tool registry: `core/mcp/tool-registry.php`
- MCP server: `core/mcp/server.php`
- JSON-RPC parser: `core/mcp/json-rpc.php`
- Auth for MCP: `core/mcp/token-auth.php`
- Rate limiter: `core/mcp/rate-limiter.php`
- All tool definitions: `core/mcp/tools/` (21 files)
