---
name: klytos-custom-fields
description: Guide for creating and managing custom fields and metadata in Klytos CMS. Use when attaching extra data to pages, users, or custom post types.
trigger: When the user needs to add custom fields to content, store metadata on entities, or work with the Meta API or Field Manager.
---

# Klytos Custom Fields & Metadata

## Two Systems

1. **MetaManager** — Free-form key-value on any entity (flexible, no schema)
2. **FieldManager** — Structured fields on Custom Post Types (typed, validated, 27 types)

---

## MetaManager (Free-Form)

```php
klytos_get_meta(string $collection, string $entityId, string $key): mixed
klytos_set_meta(string $collection, string $entityId, string $key, mixed $value): void
klytos_delete_meta(string $collection, string $entityId, string $key): bool
klytos_get_all_meta(string $collection, string $entityId): array
```

### Key Convention: `plugin_id.key_name`

```php
klytos_set_meta('pages', 'about', 'seo-pro.schema_type', 'Organization');
$schema = klytos_get_meta('pages', 'about', 'seo-pro.schema_type'); // 'Organization'
$all = klytos_get_all_meta('pages', 'about'); // ['seo-pro.schema_type' => 'Organization']
```

### Hooks

| Hook | Type | Arguments |
|---|---|---|
| `meta.before_set` | action | `string $collection, string $entityId, string $key, mixed $value` |
| `meta.after_set` | action | `string $collection, string $entityId, string $key, mixed $value` |
| `meta.get` | filter | `mixed $value, string $collection, string $entityId, string $key` |

---

## FieldManager (Structured, 27 Types)

| Type | Description |
|---|---|
| `text`, `textarea`, `richtext` | Text fields |
| `number`, `range` | Numeric |
| `email`, `phone`, `url` | Contact |
| `date`, `datetime`, `time` | Date/time |
| `color` | Color picker |
| `image`, `file`, `video`, `audio` | Media |
| `select`, `multiselect`, `checkbox`, `radio` | Choice |
| `toggle`, `boolean` | Boolean |
| `password`, `code`, `json` | Special |
| `repeater` | Repeatable group |
| `relationship` | Link to entities |

### Via MCP

**klytos_add_custom_field**:
```json
{"post_type_id": "products", "field_id": "price", "field_type": "number", "label": "Price", "required": true, "validation": {"min": 0}}
```

**klytos_add_custom_field** (select):
```json
{"post_type_id": "products", "field_id": "condition", "field_type": "select", "label": "Condition", "options": [{"value": "new", "label": "New"}, {"value": "used", "label": "Used"}]}
```

**klytos_add_custom_field** (repeater):
```json
{"post_type_id": "products", "field_id": "specs", "field_type": "repeater", "label": "Specs", "sub_fields": [{"field_id": "name", "field_type": "text", "label": "Name"}, {"field_id": "value", "field_type": "text", "label": "Value"}]}
```

**klytos_set_bulk_field_values**:
```json
{"post_type_id": "products", "entry_slug": "iphone", "values": {"price": 999, "condition": "new", "specs": [{"name": "Storage", "value": "256GB"}]}}
```

**klytos_update_custom_field** / **klytos_remove_custom_field** / **klytos_get_field_types**

---

## When to Use Which

| Scenario | System |
|---|---|
| Plugin stores data on a page | MetaManager |
| SEO plugin adds meta to any entity | MetaManager |
| Product has a price field | FieldManager |
| Structured data with validation | FieldManager |
| Temporary/internal plugin data | MetaManager |
| User-facing content fields | FieldManager |

---

## Source Files

- Meta manager: `core/meta-manager.php`
- Field manager: `core/post-type-manager.php`
- MCP tools: `core/mcp/tools/custom-field-tools.php`
- Global meta functions: `core/helpers-global.php` (lines 518-572)
