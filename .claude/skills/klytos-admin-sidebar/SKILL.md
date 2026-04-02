---
name: klytos-admin-sidebar
description: Guide for adding items to the admin sidebar, creating admin pages for plugins, and building admin UI in Klytos CMS. Use when adding menu items to the admin panel sidebar, creating admin pages for plugins, building admin forms, working with admin templates, implementing admin styling, creating AJAX endpoints, or understanding the admin panel structure and design patterns.
---

# Klytos Admin Sidebar & Admin Pages

## When to Use This Skill

Use this reference when you need to add your plugin to the admin panel sidebar, create settings pages, or build admin UI that follows the Klytos design patterns.

**IMPORTANT**: If your plugin has an admin page, it MUST also have equivalent MCP tools. MCP is the primary interface; admin is secondary.

---

## Sidebar Item Structure

```php
[
    'id'         => string,         // Unique identifier (e.g. 'my-plugin')
    'title'      => string,         // Display label
    'url'        => string,         // Link href (relative to admin/)
    'icon'       => string,         // FontAwesome class: 'fa-solid fa-star'
    'position'   => int,            // Sort order (lower = higher in menu)
    'section'    => string,         // 'content' or 'system'
    'capability' => string|null,    // Permission required (null = all users)
    'children'   => array|null,     // Submenu items (same structure)
]
```

---

## Core Sidebar Positions

| Position | Item | Section |
|---|---|---|
| 10 | Dashboard | content |
| 20 | Pages | content |
| 23+ | Custom Post Types (auto-generated) | content |
| 30 | Design (Theme) | content |
| 40 | Assets | content |
| 50 | AI Images | content |
| 55 | Tasks | content |
| 60 | Analytics | system |
| 63 | Scheduled Actions | system |
| 65 | Webhooks | system |
| 70 | Users | system |
| 75 | MCP | system |
| 78 | Security | system |
| 79 | Logs | system |
| 80 | Settings | system |
| 85 | Post Types | system |
| 90 | Plugins | system |
| 98 | Updates | system |

**Choose your position wisely**: Use positions 86-89 (between Post Types and Plugins) for plugin items in the `system` section, or 56-59 for content-related plugins.

---

## Adding a Sidebar Item (Via Plugin)

```php
// In your plugin's init.php
klytos_add_filter('admin.sidebar_items', function (array $items): array {
    $items[] = [
        'id'         => 'my-plugin',
        'title'      => 'My Plugin',
        'url'        => klytos_admin_url('plugins/my-plugin/admin/settings.php'),
        'icon'       => 'fa-solid fa-gear',
        'position'   => 87,
        'section'    => 'system',
        'capability' => 'site.configure',
    ];
    return $items;
});
```

### With Submenu

```php
klytos_add_filter('admin.sidebar_items', function (array $items): array {
    $items[] = [
        'id'         => 'my-plugin',
        'title'      => 'My Plugin',
        'url'        => klytos_admin_url('plugins/my-plugin/admin/dashboard.php'),
        'icon'       => 'fa-solid fa-chart-line',
        'position'   => 87,
        'section'    => 'system',
        'capability' => 'site.configure',
        'children'   => [
            [
                'id'    => 'my-plugin-dashboard',
                'title' => 'Dashboard',
                'url'   => klytos_admin_url('plugins/my-plugin/admin/dashboard.php'),
            ],
            [
                'id'    => 'my-plugin-settings',
                'title' => 'Settings',
                'url'   => klytos_admin_url('plugins/my-plugin/admin/settings.php'),
            ],
        ],
    ];
    return $items;
});
```

---

## Creating an Admin Page

### Complete Pattern

```php
<?php
// plugins/my-plugin/admin/settings.php
declare(strict_types=1);

// 1. Bootstrap (loads App, Auth, session, runs cron)
require_once __DIR__ . '/../../../admin/bootstrap.php';

use Klytos\Core\Helpers;

// 2. Page setup
$pageTitle   = 'My Plugin Settings';
$currentPage = 'my-plugin';  // Must match sidebar item 'id'
$auth        = $app->getAuth();
$error       = '';
$success     = '';

// 3. Handle POST form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf()) {
    try {
        // Sanitize ALL inputs
        $apiKey   = klytos_sanitize_text($_POST['api_key'] ?? '');
        $enabled  = isset($_POST['enabled']);
        $columns  = klytos_sanitize_int($_POST['columns'] ?? 3);
        $email    = klytos_sanitize_email($_POST['email'] ?? '');

        // Validate
        if ($apiKey === '') {
            throw new \RuntimeException('API key is required');
        }

        // Save options
        klytos_set_option('my-plugin.api_key', $apiKey);
        klytos_set_option('my-plugin.enabled', $enabled);
        klytos_set_option('my-plugin.columns', $columns);
        klytos_set_option('my-plugin.email', $email);

        $success = 'Settings saved successfully.';
    } catch (\RuntimeException $e) {
        $error = $e->getMessage();
    }
}

// 4. Read current values for form
$apiKey  = klytos_get_option('my-plugin.api_key', '');
$enabled = klytos_get_option('my-plugin.enabled', false);
$columns = klytos_get_option('my-plugin.columns', 3);
$email   = klytos_get_option('my-plugin.email', '');

// 5. Include header and sidebar templates
require_once __DIR__ . '/../../../admin/templates/header.php';
require_once __DIR__ . '/../../../admin/templates/sidebar.php';
?>

<!-- 6. Page content -->
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html($error); ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html($success); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>My Plugin Settings</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <?php echo klytos_csrf_field(); ?>

            <div class="form-group">
                <label class="form-label">API Key</label>
                <input type="text" name="api_key" class="form-control"
                       value="<?php echo klytos_esc_attr($apiKey); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Columns</label>
                <select name="columns" class="form-control">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <option value="<?php echo $i; ?>"
                            <?php echo $columns === $i ? 'selected' : ''; ?>>
                            <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Notification Email</label>
                <input type="email" name="email" class="form-control"
                       value="<?php echo klytos_esc_attr($email); ?>">
            </div>

            <div class="form-check">
                <input type="checkbox" name="enabled" id="enabled" class="form-check-input"
                       <?php echo $enabled ? 'checked' : ''; ?>>
                <label for="enabled" class="form-check-label">Enable Plugin</label>
            </div>

            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>

<?php
// 7. Include footer template
require_once __DIR__ . '/../../../admin/templates/footer.php';
?>
```

---

## Admin CSS Classes Reference

### Layout

| Class | Description |
|---|---|
| `.card` | Card container (white bg, border, shadow) |
| `.card-header` | Card title area |
| `.card-body` | Card content area |

### Buttons

| Class | Description |
|---|---|
| `.btn` | Button base |
| `.btn-primary` | Blue primary action |
| `.btn-secondary` | Gray secondary action |
| `.btn-danger` | Red destructive action |
| `.btn-sm` | Small button |
| `.btn-outline` | Outline variant |

### Forms

| Class | Description |
|---|---|
| `.form-group` | Field wrapper (margin-bottom) |
| `.form-label` | Input label |
| `.form-control` | Input, textarea, select |
| `.form-check` | Checkbox/radio wrapper |
| `.form-check-input` | Checkbox/radio input |
| `.form-check-label` | Checkbox/radio label |

### Alerts

| Class | Description |
|---|---|
| `.alert` | Base alert |
| `.alert-success` | Green success message |
| `.alert-error` | Red error message |
| `.alert-warning` | Orange warning message |
| `.alert-info` | Blue info message |

### Badges

| Class | Description |
|---|---|
| `.badge` | Status badge |
| `.badge-published` | Green (published) |
| `.badge-draft` | Gray (draft) |
| `.badge-active` | Green (active) |
| `.badge-inactive` | Red (inactive) |

### Tables

| Class | Description |
|---|---|
| `.table` | Data table (full width, borders) |
| `thead` | Table header (bold, bg color) |
| `tr:hover td` | Row hover highlight |

### Modal

| Class | Description |
|---|---|
| `.modal` | Modal dialog |
| `.modal-overlay` | Backdrop |
| `.modal-content` | Modal body |
| `.modal-header` | Modal title |
| `.modal-footer` | Modal buttons |

---

## Admin CSS Variables

```css
--admin-primary: #2563eb;
--admin-primary-hover: #1d4ed8;
--admin-bg: #f1f5f9;
--admin-surface: #ffffff;
--admin-sidebar: #1e293b;
--admin-sidebar-text: #cbd5e1;
--admin-sidebar-active: #2563eb;
--admin-text: #1e293b;
--admin-text-muted: #64748b;
--admin-border: #e2e8f0;
--admin-success: #22c55e;
--admin-warning: #f59e0b;
--admin-error: #ef4444;
--admin-radius: 8px;
--admin-card-bg: #ffffff;
```

Dark mode is available via `data-theme="dark"` on the `<html>` element.

---

## FontAwesome Icons

The admin panel includes FontAwesome 6. Use `fa-solid fa-*` classes for icons. Common choices:

| Icon | Class |
|---|---|
| Settings gear | `fa-solid fa-gear` |
| Chart | `fa-solid fa-chart-line` |
| Star | `fa-solid fa-star` |
| Plugin/puzzle | `fa-solid fa-puzzle-piece` |
| Shield | `fa-solid fa-shield` |
| Envelope | `fa-solid fa-envelope` |
| Image | `fa-solid fa-image` |
| Box/package | `fa-solid fa-box` |
| Users | `fa-solid fa-users` |
| Code | `fa-solid fa-code` |

---

## AJAX Endpoints

For dynamic admin functionality, use the existing AJAX patterns:

| Endpoint | Method | Purpose |
|---|---|---|
| `admin/api/autosave.php` | POST | Auto-save draft pages |
| `admin/api/inline-edit.php` | POST | Quick edit in page list |
| `admin/api/tasks.php` | POST | Update task status |
| `admin/api/media-upload.php` | POST | Upload files |

All AJAX calls must include the CSRF token in the request body or `X-CSRF-Token` header.

| `admin/api/sidebar-order.php` | POST/GET | Save/reset/get per-user sidebar order |

---

## Sidebar Drag-and-Drop Reordering

Users can customize the sidebar order per-user via drag-and-drop.

### How It Works

1. A "Customize menu" button at the bottom of the sidebar toggles edit mode
2. In edit mode, grip handles appear on items and section headers
3. Items can be dragged within a section or across sections
4. Section groups can be reordered as a whole
5. Order is auto-saved on each drag via `admin/api/sidebar-order.php`
6. A "Reset order" button restores the default order

### Data Storage

Order is stored as user meta via MetaManager:

```php
// Key: klytos.sidebar_order
// Structure:
[
    'sections' => ['content', 'system'],     // section display order
    'items'    => [
        'content' => ['dashboard', 'pages', 'theme', ...],
        'system'  => ['settings', 'plugins', ...],
    ],
]
```

### PHP: Applying Custom Order

Custom order is read from user meta after the `admin.sidebar_items` filter and before rendering. Items not in the saved order (e.g., new plugin items) are appended at their default position.

### Hooks

| Hook | Type | Args |
|---|---|---|
| `admin.sidebar_section_order` | Filter | `$sectionOrder` (array of section names) |
| `admin.sidebar_section_label` | Filter | `$label, $sectionName` |
| `admin.sidebar_order.saved` | Action | `$userId, $sections, $items` |
| `admin.sidebar_order.reset` | Action | `$userId` |
| `admin.sidebar.before_sections` | Action | — |
| `admin.sidebar.after_sections` | Action | — |

### Source Files

- SortableJS: `admin/assets/vendor/sortable/Sortable.min.js`
- Sort JS: `admin/assets/js/klytos-sidebar-sort.js`
- API: `admin/api/sidebar-order.php`
- CSS: Edit mode styles in `admin/assets/css/klytos-sidebar.css`

---

## Security Checklist for Admin Pages

- [ ] CSRF token in every form (`klytos_csrf_field()`)
- [ ] CSRF validation on every POST (`klytos_verify_csrf()`)
- [ ] All user input sanitized before use
- [ ] All output escaped before rendering
- [ ] Permission check (`klytos_has_permission()`) if page is role-restricted
- [ ] Equivalent MCP tool(s) exist for the same functionality

---

## Source Files

- Sidebar template: `admin/templates/sidebar.php`
- Header template: `admin/templates/header.php`
- Footer template: `admin/templates/footer.php`
- Bootstrap: `admin/bootstrap.php`
- Admin CSS: Inline in `admin/templates/header.php`
