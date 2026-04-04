---
name: klytos-custom-post-types
description: Guide for creating Custom Post Types, Taxonomies, Custom Fields, and Custom Post Statuses in Klytos CMS. Use when adding new content types beyond pages (products, properties, blog posts, events, portfolio items, etc.), adding taxonomies for content organization, defining structured custom fields with validation, or creating custom workflow statuses per post type. Covers post type structure, MCP tools, plugin API, admin interface, hooks, and complete end-to-end examples.
---

# Klytos Custom Post Types, Taxonomies & Custom Fields

## Post Type Structure

```php
[
    'id'             => string,     // Post type ID (e.g. 'products')
    'name'           => string,     // Display name (e.g. 'Products')
    'slug'           => string,     // Base URL slug (e.g. '/products/')
    'slug_i18n'      => array,      // Localized slugs: {'es': '/productos/', 'en': '/products/'}
    'taxonomies'     => array,      // Taxonomy definitions
    'custom_fields'  => array,      // Field definitions
    'statuses'       => array,      // Custom workflow statuses (optional)
    'builtin'        => bool,       // true only for 'page'
]
```

**Reserved IDs**: `page`, `post`, `attachment`, `revision`, `nav_menu_item`

---

## Via MCP (Primary)

### klytos_create_post_type
```json
{"id": "products", "name": "Products", "slug": "/products/", "slug_i18n": {"es": "/productos/", "en": "/products/"}}
```

### klytos_update_post_type
```json
{"id": "products", "name": "Our Products", "slug": "/shop/"}
```

### klytos_delete_post_type / klytos_get_post_type / klytos_list_post_types

---

## Taxonomies

### klytos_add_taxonomy
```json
{"post_type_id": "products", "taxonomy_slug": "category", "taxonomy_name": "Categories", "hierarchical": true}
```

### klytos_update_taxonomy / klytos_delete_taxonomy

### Terms: klytos_add_term / klytos_update_term / klytos_delete_term / klytos_list_terms
```json
{"post_type_id": "products", "taxonomy_slug": "category", "term_name": "Electronics", "term_slug": "electronics"}
```

---

## Custom Fields (27 Types)

Types: `text`, `textarea`, `richtext`, `number`, `email`, `phone`, `url`, `date`, `datetime`, `time`, `color`, `image`, `file`, `video`, `audio`, `select`, `multiselect`, `checkbox`, `radio`, `toggle`, `range`, `password`, `code`, `json`, `repeater`, `relationship`, `boolean`

### klytos_add_custom_field
```json
{"post_type_id": "products", "field_id": "price", "field_type": "number", "label": "Price", "required": true, "validation": {"min": 0}}
```

### klytos_set_bulk_field_values
```json
{"post_type_id": "products", "entry_slug": "iphone-15", "values": {"price": 999.99, "sku": "IP15"}}
```

### klytos_get_field_types — Returns all 27 types with validation rules

---

## Via Plugin Code

```php
$pt = klytos_app()->getPostTypeManager();
$pt->create(['id' => 'products', 'name' => 'Products', 'slug' => '/products/']);
$pt->update('products', ['name' => 'Our Products']);
$pt->delete('products');
$pt->get('products');
$pt->list();
```

---

## Via Admin

- Post types page: `admin/post-types.php`
- Content listing: `admin/pages.php?post_type=products`
- Auto-generates sidebar items at position 23+ with children (All items, taxonomies, settings)

---

## Hooks

| Hook | Type | Arguments |
|---|---|---|
| `post_type.before_save` | action | `array $postType` |
| `post_type.after_save` | action | `array $postType` |

---

## Complete Example: Real Estate

```json
// 1. Create type
{"id": "properties", "name": "Properties", "slug": "/properties/"}

// 2. Add taxonomy
{"post_type_id": "properties", "taxonomy_slug": "neighborhood", "taxonomy_name": "Neighborhoods"}

// 3. Add fields
{"post_type_id": "properties", "field_id": "price", "field_type": "number", "label": "Price"}
{"post_type_id": "properties", "field_id": "bedrooms", "field_type": "number", "label": "Bedrooms"}

// 4. Create content
{"slug": "properties/luxury-flat", "title": "Luxury Flat", "post_type": "properties", "status": "published"}

// 5. Set values
{"post_type_id": "properties", "entry_slug": "properties/luxury-flat", "values": {"price": 850000, "bedrooms": 3}}
```

---

## Custom Statuses

Each post type can define custom workflow statuses in addition to the 4 system statuses (`draft`, `published`, `scheduled`, `trashed`).

### Status Definition Structure

```php
'statuses' => [
    [
        'id'        => 'review',        // slug, max 20 chars, cannot collide with system statuses
        'label'     => 'In Review',     // human-readable
        'color'     => '#f59e0b',       // hex color for badges
        'icon'      => 'eye',           // optional icon
        'is_public' => false,           // if true, pages built to live site like published
    ],
]
```

### MCP Tools

- `klytos_add_post_status` -- Add custom status to a post type (params: `post_type_id`, `id`, `label`, `color`, `icon`, `is_public`)
- `klytos_update_post_status` -- Update status definition (params: `post_type_id`, `id`, `label`, `color`, `icon`, `is_public`)
- `klytos_remove_post_status` -- Remove status, reassigns affected pages to draft (params: `post_type_id`, `id`)
- `klytos_list_post_statuses` -- List system + custom statuses for a post type (params: `post_type_id`)

### PostTypeManager Methods

- `getStatusesForPostType(string $postTypeId): array` -- returns system + custom
- `isValidStatusForPostType(string $postTypeId, string $status): bool`
- `addStatus(string $postTypeId, array $statusData): array`
- `updateStatus(string $postTypeId, string $statusId, array $data): array`
- `removeStatus(string $postTypeId, string $statusId, PageManager $pageManager): array`
- `reorderStatuses(string $postTypeId, array $orderedIds): array`

### Hooks

| Hook | Type | Arguments |
|---|---|---|
| `status.before_save` | action | `array $status` |
| `status.after_save` | action | `array $status` |
| `status.after_delete` | action | `array $status` |
| `page.status_changed` | action | `array $page, string $oldStatus, string $newStatus` |
| `build.buildable_statuses` | filter | `array $statuses` (modify which custom statuses are built) |

### Admin UI

- Post type creation modal includes statuses repeater
- Post type edit page has full status management (add/edit/remove)
- Pages list shows custom status tabs, badges with custom colors, bulk actions
- Page editor shows dynamic status buttons per post type

### Workflow in klytos_create_post_type

Step (4) asks: "Would you like to define custom workflow statuses for your [name]?"

---

## Source Files

- Post type manager: `core/post-type-manager.php` (CRUD, validation, SYSTEM_STATUSES constant)
- MCP tools: `core/mcp/tools/post-type-tools.php`, `core/mcp/tools/custom-field-tools.php`
- Post status MCP tools: `core/mcp/tools/post-status-tools.php`
- Page manager: `core/page-manager.php` (setPostTypeManager(), dynamic status validation)
- Build engine: `core/build-engine.php` (getBuildablePages() includes is_public statuses)
