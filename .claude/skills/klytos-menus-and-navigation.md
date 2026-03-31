---
name: klytos-menus-and-navigation
description: Guide for managing site navigation menus in Klytos CMS. Use when adding, modifying, or removing menu items, or associating menu items with pages.
trigger: When the user needs to create menus, add menu items, build navigation, associate items with pages, or create dropdown submenus.
---

# Klytos Menus & Navigation

## Menu Item Structure

```php
[
    'id'       => string,    // Auto-generated hex ID
    'label'    => string,    // Display text
    'url'      => string,    // Link URL (default '#')
    'target'   => string,    // '_self' or '_blank'
    'icon'     => string,    // Icon name (optional)
    'order'    => int,       // Sort order
    'children' => array,     // Nested items (recursive)
]
```

---

## Via MCP (Primary)

### klytos_get_menu — Get current menu
### klytos_set_menu — Replace entire menu

```json
{
    "items": [
        {"label": "Home", "url": "/", "order": 0},
        {"label": "Services", "url": "/services/", "order": 1, "children": [
            {"label": "Marketing", "url": "/services/marketing/", "order": 0},
            {"label": "Design", "url": "/services/design/", "order": 1}
        ]},
        {"label": "About", "url": "/about/", "order": 2},
        {"label": "Contact", "url": "/contact/", "order": 3}
    ]
}
```

### klytos_add_menu_item — Add single item
```json
{"label": "Blog", "url": "/blog/", "order": 4}
```

### klytos_remove_menu_item — Remove by ID
```json
{"id": "abc123"}
```

---

## Via Plugin Code

```php
$menu = klytos_app()->getMenuManager();

$menu->get();                              // Get menu
$menu->set([...items...]);                 // Replace all
$menu->addItem(['label' => 'Blog', 'url' => '/blog/', 'order' => 3]);
$menu->removeItem('abc123');               // Remove by ID
$html = $menu->toHtml('/');                // Generate HTML
```

---

## Associating Items with Pages

Use the page's URL path as the menu item URL:

| Page slug | Menu URL |
|---|---|
| `about` | `/about/` |
| `services/marketing` | `/services/marketing/` |
| External | `https://example.com` |
| Anchor | `#section` |

---

## Generated CSS Classes

| Class | Element | Description |
|---|---|---|
| `.klytos-nav` | `<nav>` | Container |
| `.klytos-menu` | `<ul>` | Main list |
| `.klytos-menu-item` | `<li>` | Item |
| `.has-children` | `<li>` | Has submenu |
| `.klytos-submenu` | `<ul>` | Nested list |

```html
<nav class="klytos-nav">
    <ul class="klytos-menu">
        <li class="klytos-menu-item"><a href="/">Home</a></li>
        <li class="klytos-menu-item has-children">
            <a href="/services/">Services</a>
            <ul class="klytos-submenu">
                <li class="klytos-menu-item"><a href="/services/marketing/">Marketing</a></li>
            </ul>
        </li>
    </ul>
</nav>
```

Submenus: `display: none` by default, shown on `.has-children:hover`.

---

## Source Files

- Menu manager: `core/menu-manager.php`
- MCP tools: `core/mcp/tools/menu-tools.php`
