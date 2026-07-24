---
name: klytos-forms
description: Guide for the Klytos Forms plugin — creating, managing, and rendering forms with conditional logic, multi-step, anti-spam, notifications, and MCP tools. Use when working with forms, form fields, form entries, form submissions, conditional logic, form notifications, or the forms MCP tools.
---

# Klytos Forms Plugin

## Overview

Klytos Forms is a **plugin** (not core) providing a complete form system comparable to Gravity Forms. AI-first: everything can be managed via MCP tools.

**Plugin location:** `installer/plugins/klytos-forms/`

## Plugin Structure

```
plugins/klytos-forms/
├── klytos-forms.php          (Entry point + hooks registration)
├── klytos-plugin.json        (Admin pages manifest)
├── src/
│   ├── FormConditionalEngine.php  (Conditional logic evaluator)
│   ├── FormManager.php            (CRUD forms, fields, entries, submissions)
│   └── FormRenderer.php           (HTML + CSS + JS rendering)
├── admin/
│   ├── forms.php                  (Form list page)
│   ├── form-editor.php            (GF-style visual editor)
│   └── form-entries.php           (Entry viewer)
├── assets/js/klytos-forms.js     (Frontend engine)
└── lang/{en,es}.json              (Translations)
```

## Key Hooks Registered

- `admin.sidebar_items` filter — adds Formularios section
- `page.content` filter — resolves `{{form:id}}` shortcodes
- `mcp.tools_list` filter — registers 16 MCP tools
- `mcp.handle_tool` filter — handles tool calls
- Route `api/forms/submit` — public form submission endpoint

## Helper Functions

```php
klytos_forms()                    // Get FormManager instance
klytos_render_form( $formId )     // Render form as HTML
```

## MCP Tools (16 total) — all gated at `forms.manage` (owner/admin)

Since Sprint 2 every `tools/call` passes a default-deny gate, and the plugin declares its own
capabilities through the `mcp.tool_capabilities` filter in `klytos-forms.php`. **All 16 sit at
`forms.manage`, reads included** — form entries carry data people submitted, and the capability
matrix has no `forms.view`, so the whole domain sits at the manage bar rather than inventing a second
matrix. An editor or viewer calling any of them — including `klytos_forms_list` — gets a JSON-RPC
error object and **HTTP 403**; they are also absent from that role's `tools/list`.

If you add a 17th tool, add its entry to the same filter or it is refused to everyone, owner
included. See `docs/reference/mcp-authorization.md`.

**Forms:** klytos_forms_create, klytos_forms_get, klytos_forms_list, klytos_forms_update, klytos_forms_delete, klytos_forms_duplicate
**Fields:** klytos_forms_add_field, klytos_forms_update_field, klytos_forms_remove_field, klytos_forms_reorder_fields
**Entries:** klytos_forms_list_entries, klytos_forms_get_entry, klytos_forms_update_entry_status, klytos_forms_delete_entry, klytos_forms_export_entries, klytos_forms_stats

## Storage Collections

- `forms` — form definitions
- `form-entries` — submissions

## Admin Pages (via plugin-page.php)

- `plugin-page.php?plugin=klytos-forms&page=forms` — List
- `plugin-page.php?plugin=klytos-forms&page=form-editor` — Editor
- `plugin-page.php?plugin=klytos-forms&page=form-entries` — Entries

## Inserting in Pages

Use shortcode in page content: `{{form:contact-form}}`
Resolved by the `page.content` filter registered in the plugin.
