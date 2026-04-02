---
name: klytos-custom-post-types
description: Guide for creating Custom Post Types, Taxonomies, and Custom Fields in Klytos CMS. Use when adding new content types beyond pages (products, properties, blog posts, events, portfolio items, etc.), adding taxonomies for content organization, or defining structured custom fields with validation. Covers post type structure, MCP tools, plugin API, admin interface, hooks, and complete end-to-end examples.
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

## Source Files

- Post type manager: `core/post-type-manager.php`
- MCP tools: `core/mcp/tools/post-type-tools.php`, `core/mcp/tools/custom-field-tools.php`
