---
name: klytos-forms
description: Guide for the Klytos Forms system — creating, managing, and rendering forms with conditional logic, multi-step, anti-spam, entries, notifications, and MCP tools.
trigger: When the user works with forms, form fields, form entries, form submissions, conditional logic, form notifications, or the forms MCP tools.
---

# Klytos Forms System

## Overview

Klytos has a complete forms system (comparable to Gravity Forms) with an AI-first approach. Everything can be created, modified, and queried via MCP tools. The admin panel is complementary.

**Key features:**
- 19 field types with validation
- Conditional logic (show/hide) with 14 operators
- Multi-step (wizard/stepper) forms
- Anti-spam: honeypot + rate limiting per IP
- Email notifications with merge tags
- Entry management with status, search, export
- Shortcode `{{form:form-id}}` for page insertion

---

## Architecture

### Core Classes (installer/core/)

| File | Class | Purpose |
|------|-------|---------|
| `form-conditional-engine.php` | `FormConditionalEngine` | Evaluates conditional rules (backend) |
| `form-manager.php` | `FormManager` | Full lifecycle: CRUD forms, fields, entries, submissions, notifications |
| `form-renderer.php` | `FormRenderer` | Renders HTML + CSS + JS for static pages |

### Frontend (installer/public/)

| File | Purpose |
|------|---------|
| `js/klytos-forms.js` | KlytosFormEngine class: conditionals, stepper, AJAX submit |

### Storage

- Collection `forms` — form definitions (indexed by status)
- Collection `form-entries` — submissions (indexed by form_id, status)

### Admin (installer/admin/)

| File | Purpose |
|------|---------|
| `forms.php` | List all forms |
| `form-editor.php` | Create/edit form (tabs: Fields, Settings, Notifications, Anti-spam, Insert) |
| `form-entries.php` | View entries with filters, search, export |
| `api/forms.php` | Admin REST API for all form operations |

### Endpoint

- `POST /api/forms/submit` — Public form submission (via Router)

---

## Field Types

text, email, url, phone, number, textarea, select, radio, checkbox, checkbox_group, date, time, file, hidden, html, section, consent, password, range

## Conditional Operators

is, is_not, contains, not_contains, starts_with, ends_with, greater_than, less_than, is_empty, is_not_empty, is_checked, is_not_checked, in, not_in

## MCP Tools (16 total)

**Forms:** klytos_forms_create, klytos_forms_get, klytos_forms_list, klytos_forms_update, klytos_forms_delete, klytos_forms_duplicate

**Fields:** klytos_forms_add_field, klytos_forms_update_field, klytos_forms_remove_field, klytos_forms_reorder_fields

**Entries:** klytos_forms_list_entries, klytos_forms_get_entry, klytos_forms_update_entry_status, klytos_forms_delete_entry, klytos_forms_export_entries, klytos_forms_stats

## Hooks

| Hook | Parameters | When |
|------|-----------|------|
| `form.after_create` | `$form` | After form created |
| `form.after_update` | `$form` | After form updated |
| `form.before_delete` | `$formId` | Before form deleted |
| `form.after_delete` | `$formId` | After form deleted |
| `form.entry_created` | `$entry, $form` | After submission saved |
| `form.before_validate` | `$form, $data` | Before validation |
| `form.after_validate` | `$errors, $form, $data` | After validation (filter) |
| `form.notification_sent` | `$notification, $entry, $success` | After email attempt |
| `form.before_render` | `$form, $options` | Before HTML render (filter) |

## Helper Functions

```php
klytos_forms()                    // Get FormManager instance
klytos_render_form( $formId )     // Render form as HTML
```

## Inserting in Pages

Use the shortcode in any page content:
```
{{form:contact-form}}
```

The BuildEngine resolves this via `preg_replace_callback` in `build-engine.php`.
