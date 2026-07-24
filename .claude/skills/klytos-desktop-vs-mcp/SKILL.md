---
name: klytos-desktop-vs-mcp
description: Guide explaining the differences between the Desktop interface (Admin Panel) and the AI interface (MCP) in Klytos CMS. Essential for implementing features correctly in both interfaces. Covers core philosophy, interface comparison, Admin Panel pattern with CSRF, MCP pattern with JSON-RPC, authentication methods, equivalency table, shared business logic, and plugin development checklist.
---

# Klytos: Desktop (Admin) vs MCP (AI) Interfaces

## Core Philosophy

> **MCP is the PRIMARY interface. The Admin Panel is SECONDARY.**

Klytos is an AI-First CMS. This means:
1. Every feature MUST be fully controllable via MCP tools
2. The admin panel provides a visual fallback for humans
3. When developing a new feature, implement the MCP tool FIRST, then the admin page

---

## Interface Comparison

| Aspect | Admin Panel (Desktop) | MCP (AI) |
|---|---|---|
| **Priority** | Secondary | Primary |
| **User** | Humans via browser | AI assistants via API |
| **Context check** | `klytos_is_admin()` | `klytos_is_mcp()` |
| **Auth method** | Session cookies | Bearer token / OAuth / App Password |
| **CSRF** | Required on all forms | Not needed (token auth) |
| **Data format** | HTML forms → POST | JSON-RPC 2.0 |
| **Response** | Rendered HTML page | JSON response |
| **Rate limiting** | No | 60/min per identifier |
| **Session** | 30-min inactivity timeout | Stateless (per-request) |

---

## Admin Panel (Desktop) Pattern

### Request Flow

```
Browser → admin/{page}.php → bootstrap.php → Session auth → CSRF check → Process → Render HTML
```

### Implementation Template

```php
<?php
declare(strict_types=1);

// 1. Bootstrap: loads App, starts session, checks auth
require_once __DIR__ . '/bootstrap.php';

// 2. Setup
$pageTitle   = 'Feature Name';
$currentPage = 'my-feature'; // Matches sidebar item 'id'
$auth        = $app->getAuth();
$error = $success = '';

// 3. Handle POST (with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf()) {
    try {
        $input = klytos_sanitize_text($_POST['field'] ?? '');
        // ... process
        $success = 'Saved successfully';
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

// 4. Fetch data for display
$data = klytos_app()->getMyManager()->list();

// 5. Render with templates
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html($error); ?></div>
<?php endif; ?>

<div class="card">
    <form method="POST">
        <?php echo klytos_csrf_field(); ?>
        <!-- Form fields with escaping -->
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
```

### Key Requirements
- CSRF token on ALL forms
- All output escaped (`klytos_esc_html`, `klytos_esc_attr`)
- All input sanitized
- Permission checks (`klytos_has_permission()`)
- Header/sidebar/footer templates included

---

## MCP (AI) Pattern

### Request Flow

```
AI Client → /mcp endpoint → JSON-RPC → Token Auth → Rate Limit → Tool Registry
          → AUTHORIZATION GATE (default-deny, the caller's role vs the tool's capability)
          → Handler → JSON Response
```

The gate is the MCP equivalent of the admin panel's `klytos_has_permission()` call, and it is where
the two interfaces converge: **both ask the same capability matrix**
(`UserManager::hasPermission()`). They differ only in where identity comes from — the admin path
reads the session, the MCP path resolves an actor from the **credential**, because it never starts a
session. A tool with no capability entry is refused to everyone, including the owner. Refusal is a
JSON-RPC error object with HTTP **403** (the admin equivalent is `klytos_deny()`'s 403 document or
401 JSON). See `docs/reference/mcp-authorization.md`.

### Implementation Template

```php
// 1. Register tool
klytos_add_filter('mcp.tools_list', function (array $tools): array {
    $tools[] = [
        'name'        => 'my_feature_action',
        'description' => 'Clear description for AI to understand when to use this tool.',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => [
                'field' => ['type' => 'string', 'description' => 'What this field does'],
            ],
            'required' => ['field'],
        ],
        'annotations' => [
            'readOnlyHint'    => false,
            'destructiveHint' => false,
        ],
    ];
    return $tools;
});

// 2. Handle tool call
klytos_add_filter('mcp.handle_tool', function (mixed $result, string $name, array $params): mixed {
    if ($name !== 'my_feature_action') return $result;

    $field = klytos_sanitize_text($params['field'] ?? '');

    try {
        $data = klytos_app()->getMyManager()->doAction($field);
        return [
            'content' => [['type' => 'text', 'text' => json_encode($data)]],
            'isError' => false,
        ];
    } catch (\Exception $e) {
        return [
            'content' => [['type' => 'text', 'text' => 'Error: ' . $e->getMessage()]],
            'isError' => true,
        ];
    }
}, 10);
```

### Key Requirements
- Clear tool description (AI needs to know when to use it)
- JSON Schema for parameters
- Proper annotations (readOnly, destructive, idempotent)
- Sanitized inputs
- Structured JSON response
- Declared in `klytos-plugin.json` under `mcp_tools`

---

## Authentication Methods (by Priority)

MCP requests are authenticated in this order:

| Priority | Method | Header | Format |
|---|---|---|---|
| 1 | Bearer Token | `Authorization: Bearer {token}` | 64-char hex |
| 2 | OAuth 2.0 Access Token | `Authorization: Bearer {access_token}` | OAuth flow |
| 3 | Application Password | `Authorization: Basic {base64}` | HTTP Basic Auth |

Admin panel uses session cookies (started via `login.php`).

---

## Equivalency Table

Every admin feature MUST have an MCP equivalent:

| Admin Page | MCP Tool(s) |
|---|---|
| `pages.php` (list) | `klytos_list_pages` |
| `page-editor.php` (create/edit) | `klytos_create_page`, `klytos_update_page` |
| `page-editor.php` (delete) | `klytos_delete_page` |
| `theme.php` | `klytos_get_theme`, `klytos_set_theme`, `klytos_set_colors`, `klytos_set_fonts` |
| `settings.php` | `klytos_get_site_config`, `klytos_set_site_config` |
| `users.php` | `klytos_list_users`, `klytos_create_user`, `klytos_update_user` |
| `assets.php` | `klytos_list_assets`, `klytos_upload_asset`, `klytos_delete_asset` |
| `plugins.php` | `klytos_list_plugins`, `klytos_activate_plugin`, `klytos_deactivate_plugin` |
| `post-types.php` | `klytos_create_post_type`, `klytos_list_post_types`, etc. |
| `menu.php` | `klytos_get_menu`, `klytos_set_menu`, `klytos_add_menu_item` |
| `scheduled-actions.php` | `klytos_list_scheduled_actions`, `klytos_schedule_*`, `klytos_cancel_*` |
| `webhooks.php` | `klytos_create_webhook`, `klytos_list_webhooks`, `klytos_delete_webhook` |
| `analytics.php` | `klytos_get_analytics`, `klytos_get_top_pages` |
| Build button | `klytos_build_site`, `klytos_build_page` |

---

## Context Detection Helpers

```php
// Check which interface is being used
if (klytos_is_mcp()) {
    // AI is calling via MCP — return JSON data
    return ['success' => true, 'data' => $result];
} elseif (klytos_is_admin()) {
    // Human is using admin panel — redirect or render HTML
    header('Location: ' . klytos_admin_url('pages.php'));
} elseif (klytos_is_cli()) {
    // Running from command line
    echo "Done.\n";
}
```

---

## Shared Business Logic

**IMPORTANT**: Business logic should live in Manager classes, NOT in admin pages or MCP handlers. Both interfaces should call the same manager:

```php
// BAD: Logic in admin page
// admin/pages.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [...];
    klytos_storage()->write('pages', $slug, $data); // Direct storage access!
}

// GOOD: Logic in manager, called by both
// core/page-manager.php
class PageManager {
    public function create(array $data): array { ... }
}

// admin/pages.php calls:
$app->getPages()->create($data);

// MCP handler calls:
$app->getPages()->create($params);
```

This ensures:
- Same validation in both interfaces
- Same hooks fired in both interfaces
- No feature drift between admin and MCP

---

## Plugin Development Checklist

When developing a plugin for Klytos, ensure:

- [ ] **MCP tools defined** for all functionality (`mcp.tools_list` + `mcp.handle_tool`)
- [ ] **MCP tools declared** in `klytos-plugin.json` (`mcp_tools` array)
- [ ] **Admin page** (optional but recommended) with sidebar item
- [ ] **Shared logic** in a manager/service class, not in admin or MCP handlers
- [ ] **Same hooks fire** regardless of which interface triggers the action
- [ ] **CSRF on admin forms** (`klytos_csrf_field()` + `klytos_verify_csrf()`)
- [ ] **Input sanitized** in both interfaces
- [ ] **Output escaped** in admin pages
- [ ] **Permissions checked** in both interfaces

---

## Source Files

- Admin bootstrap: `admin/bootstrap.php`
- MCP server: `core/mcp/server.php`
- MCP auth: `core/mcp/token-auth.php`
- Admin auth: `core/auth.php`
- Context checks: `core/helpers-global.php` (klytos_is_admin, klytos_is_mcp, klytos_is_cli)
