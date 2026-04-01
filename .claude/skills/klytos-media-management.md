---
name: klytos-media-management
description: Guide for working with the Klytos Media Management system — asset metadata, categories, usage tracking, admin UI, API endpoints, and MCP tools.
trigger: When working with asset/media management, image categories, usage tracking, asset cleanup, or the media library admin UI.
---

# Klytos Media Management — Categories & Usage Tracking

## Overview

Klytos has an enhanced media management system that goes beyond simple file storage. It provides:

1. **Asset metadata registry** — Every uploaded file gets a record in the `assets` collection with ID, title, alt text, description, categories, and upload info.
2. **Asset categories** — Custom categories (`asset-categories` collection) to organize media files.
3. **Usage tracking** — The `asset-usage` collection records where each asset is used (pages, header, footer, theme settings).
4. **Automatic sync** — Hooks on `page.after_save`, `page.after_delete`, and `theme.after_save` keep usage records up to date.

---

## Key Files

| File | Purpose |
|------|---------|
| `installer/core/asset-manager.php` | `AssetManager` class — upload, delete, categories, usage tracking, sync, rebuild |
| `installer/core/asset-usage-hooks.php` | 3 hooks for automatic usage tracking |
| `installer/admin/assets.php` | Admin UI — grid/list views, filters, detail panel, category management, bulk cleanup |
| `installer/admin/api/assets-management.php` | REST API for asset CRUD, categories, sync, rebuild |
| `installer/admin/api/media-upload.php` | Gutenberg editor upload endpoint (returns `asset_id`) |
| `installer/core/mcp/tools/asset-tools.php` | 12 MCP tools for AI-driven asset management |

---

## AssetManager Methods

### Upload & Delete
- `upload(filename, dataBase64, directory)` — Uploads file, auto-registers metadata, returns result with `asset_id`
- `uploadRaw(filename, data, directory)` — Same but with raw binary data
- `delete(relativePath)` — Deletes file + metadata + all usage records

### Categories
- `createCategory(name, description, parent)` — Create category (ID = slug)
- `listCategories()` — List all categories
- `updateCategory(categoryId, data)` — Update name/description/parent/order
- `deleteCategory(categoryId)` — Delete category, unlink from assets
- `setAssetCategories(assetId, categoryIds)` — Assign categories to asset
- `getAssetsByCategory(categoryId)` — Get assets in a category

### Usage Tracking
- `trackUsage(assetId, contextType, contextId, contextLabel, field)` — Record usage
- `removeUsage(assetId, contextType, contextId)` — Remove usage record
- `getUsage(assetId)` — Get all places an asset is used
- `getAssetsForContext(contextType, contextId)` — Get assets used in a context
- `isAssetInUse(assetId)` — Check if asset has any usage
- `getUnusedAssets()` — Get all assets with no usage records
- `deleteUsageForAsset(assetId)` — Remove all usage for an asset
- `deleteUsageForContext(contextType, contextId)` — Remove all usage for a context
- `findAssetByPath(relativePath)` — Look up asset by file path

### Sync & Rebuild
- `syncExistingAssets()` — Scan filesystem, register untracked files
- `rebuildUsageIndex()` — Delete all usage records and rebuild from content

---

## Database Collections

### `assets`
```json
{
    "id": "a1b2c3d4",
    "filename": "hero.jpg",
    "path": "assets/images/2026/04/hero.jpg",
    "mime_type": "image/jpeg",
    "size": 245760,
    "size_human": "240 KB",
    "alt_text": "",
    "title": "Hero",
    "description": "",
    "categories": ["banners"],
    "uploaded_by": "admin",
    "uploaded_at": "2026-04-01T10:00:00+00:00",
    "updated_at": "2026-04-01T10:00:00+00:00"
}
```

### `asset-categories`
```json
{
    "id": "banners",
    "name": "Banners",
    "slug": "banners",
    "description": "Banner images",
    "parent": null,
    "order": 0,
    "created_at": "2026-04-01T10:00:00+00:00"
}
```

### `asset-usage`
```json
{
    "id": "a1b2c3d4--page--about-us",
    "asset_id": "a1b2c3d4",
    "context_type": "page",
    "context_id": "about-us",
    "context_label": "About Us",
    "field": "content_html",
    "added_at": "2026-04-01T10:00:00+00:00"
}
```

Context types: `page`, `post`, `header`, `footer`, `sidebar`, `widget`, `theme`, `plugin`, `favicon`, `og_image`

---

## MCP Tools

### Original (v1.0)
- `klytos_upload_asset` — Upload base64 file
- `klytos_list_assets` — List files from filesystem
- `klytos_delete_asset` — Delete file by path

### Media Management (v0.18.0)
- `klytos_assets_list_filtered` — List registered assets with filters (usage, category, type, search) and pagination
- `klytos_assets_get_usage` — Get all locations where an asset is used
- `klytos_assets_get_unused` — Get assets not used anywhere
- `klytos_assets_update_metadata` — Update title, alt_text, description, categories
- `klytos_asset_categories_list` — List categories with asset counts
- `klytos_asset_categories_create` — Create a new category
- `klytos_assets_sync` — Sync filesystem with database
- `klytos_assets_rebuild_usage` — Rebuild usage index from content
- `klytos_assets_cleanup_unused` — Delete all unused assets (requires `confirm: true`)

---

## API Endpoints

File: `installer/admin/api/assets-management.php`

**GET actions:**
- `?action=list&filter=all|in_use|unused&category=slug&type=image&search=term&page=1&per_page=20`
- `?action=get&id=assetId` — Get asset detail with usage
- `?action=list_categories` — List categories with counts

**POST actions** (require CSRF + JSON body):
- `action=update` + `id`, `title`, `alt_text`, `description`, `categories`
- `action=delete` + `id`
- `action=bulk_delete` + `ids[]`
- `action=sync`
- `action=rebuild_usage`
- `action=create_category` + `name`, `description`
- `action=update_category` + `id`, `name`, `description`, `parent`, `order`
- `action=delete_category` + `id`

---

## Admin UI

The media library at `admin/assets.php` provides:

- **Toolbar** — Usage filter, category filter, type filter, search, grid/list toggle
- **Upload zone** — Drag & drop with directory selector
- **Grid view** — Thumbnails with click-to-detail
- **List view** — Table with filename, type, size, categories, usages, date
- **Detail panel** — Modal with preview, editable metadata, usage table, technical info, save/copy URL/delete
- **Categories modal** — Create/delete categories with asset counts
- **Cleanup modal** — Preview and bulk-delete unused assets
- **Sync & Rebuild** — Toolbar buttons for maintenance operations

### Extensibility Hooks
- `admin.assets.before_toolbar`
- `admin.assets.after_toolbar`
- `admin.assets.detail_panel_extra`
- `admin.assets.before`
- `admin.assets.after`

---

## Gutenberg Integration

`admin/api/media-upload.php` returns `asset_id` in the response so the editor can store `data-asset-id` attributes on `<img>` tags. This enables the `page.after_save` hook to identify assets by ID rather than just path.
