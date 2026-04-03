---
description: "Complete guide for creating and managing forms with the klytos-forms plugin. Read this guide before creating contact forms, registration forms, feedback forms, or any form on the site. Covers field types, conditional logic, notifications, multi-step forms, and embedding."
globs: ["**/*.php", "**/*.html"]
alwaysApply: false
---

# Klytos Forms — Complete Guide for AI Assistants

## Prerequisites

Before creating forms, the klytos-forms plugin MUST be activated:
```
klytos_activate_plugin with id "klytos-forms"
```

## Quick Start: Create a Contact Form in 3 Steps

### Step 1: Create the form
```
klytos_forms_create with:
  title: "Contact Form"
  fields: [
    {
      "id": "name",
      "type": "text",
      "label": "Name",
      "placeholder": "Your full name",
      "required": true,
      "validation": {"min_length": 2, "max_length": 100}
    },
    {
      "id": "email",
      "type": "email",
      "label": "Email",
      "placeholder": "you@example.com",
      "required": true
    },
    {
      "id": "subject",
      "type": "select",
      "label": "Subject",
      "required": true,
      "options": [
        {"value": "general", "label": "General inquiry"},
        {"value": "support", "label": "Technical support"},
        {"value": "billing", "label": "Billing question"},
        {"value": "other", "label": "Other"}
      ]
    },
    {
      "id": "message",
      "type": "textarea",
      "label": "Message",
      "placeholder": "How can we help you?",
      "required": true,
      "validation": {"min_length": 10, "rows": 5}
    },
    {
      "id": "privacy",
      "type": "consent",
      "label": "Privacy",
      "consent_text": "I accept the <a href='/privacy/'>privacy policy</a>",
      "required": true
    }
  ]
  settings: {
    "submit_label": "Send Message",
    "success_message": "Thank you for contacting us. We will respond within 24 hours.",
    "enable_ajax": true,
    "layout": "stacked"
  }
  notifications: [
    {
      "id": "admin_notification",
      "name": "Admin Notification",
      "enabled": true,
      "to": "admin@example.com",
      "reply_to": "{{email}}",
      "subject": "New contact: {{subject}} — {{name}}",
      "body": "New contact form submission:\n\nName: {{name}}\nEmail: {{email}}\nSubject: {{subject}}\n\nMessage:\n{{message}}\n\n---\nSubmitted: {{entry_date}}\nIP: {{entry_ip}}",
      "format": "text"
    },
    {
      "id": "user_confirmation",
      "name": "User Confirmation",
      "enabled": true,
      "to": "{{email}}",
      "subject": "We received your message — {{site_name}}",
      "body": "Hello {{name}},\n\nThank you for reaching out. We have received your message and will respond within 24 hours.\n\nBest regards,\n{{site_name}}",
      "format": "text"
    }
  ]
  anti_spam: {
    "honeypot": true,
    "rate_limit": 3,
    "rate_limit_window": 60
  }
```

### Step 2: Embed in a page

The response includes a `shortcode` field. Use it in the page content:

**For Gutenberg editor:**
```html
<!-- wp:html -->
<div class="klytos-container" style="max-width:700px;margin:0 auto;padding:3rem 1rem">
  <h1>Contact Us</h1>
  <p>Fill out the form below and we will get back to you within 24 hours.</p>
  {{form:contact-form}}
</div>
<!-- /wp:html -->
```

**For TinyMCE editor:**
```html
<div class="klytos-container" style="max-width:700px;margin:0 auto;padding:3rem 1rem">
  <h1>Contact Us</h1>
  <p>Fill out the form below and we will get back to you within 24 hours.</p>
  {{form:contact-form}}
</div>
```

### Step 3: Build the site
```
klytos_build_site
```

The form will be rendered with all fields, validation, AJAX submission, and anti-spam protection.

---

## Available Field Types (18)

### Text Input Fields
| Type | Description | Key Validation Options |
|------|-------------|----------------------|
| `text` | Single-line text | `min_length`, `max_length`, `pattern`, `pattern_message` |
| `email` | Email with validation | (auto-validates email format) |
| `url` | URL with validation | (auto-validates URL format) |
| `phone` | Telephone | `pattern` (for phone format) |
| `password` | Password (masked) | `min_length`, `max_length` |

### Numeric & Selection
| Type | Description | Key Options |
|------|-------------|------------|
| `number` | Numeric input | `min`, `max`, `step` |
| `range` | Slider control | `min`, `max`, `step` |
| `select` | Dropdown | `options` array, `multiple` (boolean) |
| `radio` | Radio buttons | `options` array |
| `checkbox` | Single checkbox | (boolean value) |
| `checkbox_group` | Multiple checkboxes | `options` array, `min_selected`, `max_selected` |

### Date/Time
| Type | Description | Key Options |
|------|-------------|------------|
| `date` | Date picker | `min_date`, `max_date` |
| `time` | Time picker | (standard time input) |

### Text Area & File
| Type | Description | Key Options |
|------|-------------|------------|
| `textarea` | Multi-line text | `rows`, `min_length`, `max_length` |
| `file` | File upload | `max_size` (MB), `allowed_types` (array of extensions), `max_files` |

### Special Fields
| Type | Description | Key Options |
|------|-------------|------------|
| `consent` | Consent checkbox | `consent_text` (HTML allowed for links) |
| `hidden` | Hidden field | `default_value` (preset value) |
| `html` | Custom HTML content | Not an input — displays custom HTML |
| `section` | Section separator | Visual divider with optional title |

---

## Field Configuration Reference

Every field supports these properties:

```json
{
  "id": "unique_field_id",
  "type": "text",
  "label": "Field Label",
  "placeholder": "Hint text",
  "required": false,
  "default_value": "",
  "css_class": "extra-css-class",
  "step": 1,
  "order": 1,
  "validation": {},
  "conditional": null,
  "options": []
}
```

- `id`: Unique identifier (lowercase, underscores). Used in merge tags: `{{id}}`
- `type`: One of the 18 field types above
- `label`: Display label shown to the user
- `placeholder`: Placeholder text inside the input
- `required`: Whether the field must be filled
- `default_value`: Pre-filled value
- `css_class`: Additional CSS classes for styling
- `step`: Which step this field belongs to (for multi-step forms)
- `order`: Display order within its step
- `validation`: Type-specific validation rules (see each type)
- `conditional`: Show/hide logic (see Conditional Logic section)
- `options`: For select, radio, checkbox_group — array of `{value, label}` objects

---

## Notification Configuration

Notifications send emails when a form is submitted. Each form can have multiple notifications.

```json
{
  "id": "notification_id",
  "name": "Display Name",
  "enabled": true,
  "to": "recipient@example.com",
  "reply_to": "{{email_field}}",
  "subject": "Subject line with {{merge_tags}}",
  "body": "Email body with {{merge_tags}}",
  "format": "text",
  "conditional": null
}
```

### Available Merge Tags
- `{{field_id}}` — Any form field value (e.g., `{{name}}`, `{{email}}`)
- `{{all_fields}}` — All submitted field values, formatted
- `{{site_name}}` — Site name
- `{{site_email}}` — Site email
- `{{site_url}}` — Site URL
- `{{form_title}}` — Form title
- `{{entry_id}}` — Submission ID
- `{{entry_date}}` — Submission date/time
- `{{entry_ip}}` — Submitter IP address

### Conditional Notifications
Send a notification only when certain conditions are met:

```json
{
  "conditional": {
    "action": "show",
    "logic": "all",
    "rules": [
      {"field_id": "department", "operator": "is", "value": "sales"}
    ]
  }
}
```

---

## Conditional Logic

Show or hide fields based on other field values. Supports 14 operators:

`is`, `is_not`, `contains`, `not_contains`, `starts_with`, `ends_with`, `greater_than`, `less_than`, `is_empty`, `is_not_empty`, `is_checked`, `is_not_checked`, `in`, `not_in`

### Example: Show a field only when "Other" is selected

```json
{
  "id": "other_reason",
  "type": "textarea",
  "label": "Please specify",
  "conditional": {
    "action": "show",
    "logic": "all",
    "rules": [
      {"field_id": "subject", "operator": "is", "value": "other"}
    ]
  }
}
```

### Example: Show upload field only for support requests

```json
{
  "id": "screenshot",
  "type": "file",
  "label": "Attach a screenshot",
  "validation": {"max_size": 5, "allowed_types": ["jpg", "png", "pdf"]},
  "conditional": {
    "action": "show",
    "logic": "all",
    "rules": [
      {"field_id": "subject", "operator": "is", "value": "support"}
    ]
  }
}
```

---

## Multi-Step Forms

Create forms with multiple steps/pages:

```json
{
  "settings": {
    "steps": [
      {"step": 1, "title": "Personal Info"},
      {"step": 2, "title": "Details"},
      {"step": 3, "title": "Confirmation"}
    ]
  }
}
```

Assign fields to steps with the `step` property:
```json
{"id": "name", "type": "text", "label": "Name", "step": 1},
{"id": "email", "type": "email", "label": "Email", "step": 1},
{"id": "message", "type": "textarea", "label": "Message", "step": 2},
{"id": "privacy", "type": "consent", "label": "Privacy", "step": 3}
```

---

## Form Settings

```json
{
  "submit_label": "Send",
  "success_message": "Thank you! We will be in touch.",
  "success_action": "message",
  "success_redirect": "/thank-you/",
  "enable_ajax": true,
  "css_class": "",
  "layout": "stacked"
}
```

- `submit_label`: Text on the submit button
- `success_message`: Message shown after successful submission
- `success_action`: `"message"` (show message) or `"redirect"` (redirect to URL)
- `success_redirect`: URL to redirect to (only if success_action is "redirect")
- `enable_ajax`: Submit without page reload
- `layout`: `"stacked"` (labels above inputs) or `"inline"` (compact)

---

## Anti-Spam Configuration

```json
{
  "honeypot": true,
  "rate_limit": 3,
  "rate_limit_window": 60
}
```

- `honeypot`: Invisible trap field that bots fill (recommended: always true)
- `rate_limit`: Maximum submissions per IP address
- `rate_limit_window`: Time window in seconds (0 = unlimited)

---

## Common Form Recipes

### Newsletter Signup
```json
{
  "title": "Newsletter",
  "fields": [
    {"id": "email", "type": "email", "label": "Email", "required": true, "placeholder": "your@email.com"},
    {"id": "consent", "type": "consent", "label": "Newsletter", "consent_text": "I agree to receive newsletters", "required": true}
  ],
  "settings": {"submit_label": "Subscribe", "layout": "inline", "enable_ajax": true, "success_message": "You are now subscribed!"}
}
```

### Support Request Form
```json
{
  "title": "Support Request",
  "fields": [
    {"id": "name", "type": "text", "label": "Name", "required": true},
    {"id": "email", "type": "email", "label": "Email", "required": true},
    {"id": "order_id", "type": "text", "label": "Order ID", "placeholder": "e.g. ORD-12345"},
    {"id": "priority", "type": "select", "label": "Priority", "options": [
      {"value": "low", "label": "Low"}, {"value": "medium", "label": "Medium"}, {"value": "high", "label": "High — Urgent"}
    ]},
    {"id": "description", "type": "textarea", "label": "Describe the issue", "required": true, "validation": {"rows": 6}},
    {"id": "screenshot", "type": "file", "label": "Attach screenshot", "validation": {"max_size": 5, "allowed_types": ["jpg", "png", "pdf"]}},
    {"id": "privacy", "type": "consent", "label": "Privacy", "consent_text": "I accept the <a href='/privacy/'>privacy policy</a>", "required": true}
  ],
  "notifications": [
    {"id": "admin", "name": "Admin", "enabled": true, "to": "support@example.com", "reply_to": "{{email}}", "subject": "[{{priority}}] Support: {{name}}", "body": "{{all_fields}}", "format": "text"}
  ]
}
```

### Feedback Form with Rating
```json
{
  "title": "Feedback",
  "fields": [
    {"id": "rating", "type": "radio", "label": "How would you rate our service?", "required": true, "options": [
      {"value": "5", "label": "Excellent"}, {"value": "4", "label": "Good"}, {"value": "3", "label": "Average"}, {"value": "2", "label": "Poor"}, {"value": "1", "label": "Very poor"}
    ]},
    {"id": "liked", "type": "textarea", "label": "What did you like?", "validation": {"rows": 3}},
    {"id": "improve", "type": "textarea", "label": "What could we improve?", "validation": {"rows": 3}},
    {"id": "email", "type": "email", "label": "Email (optional)", "required": false}
  ],
  "settings": {"submit_label": "Send Feedback", "success_message": "Thank you for your feedback!"}
}
```

### Quote Request Form (Multi-Step)
```json
{
  "title": "Request a Quote",
  "fields": [
    {"id": "company", "type": "text", "label": "Company Name", "required": true, "step": 1},
    {"id": "name", "type": "text", "label": "Contact Name", "required": true, "step": 1},
    {"id": "email", "type": "email", "label": "Email", "required": true, "step": 1},
    {"id": "phone", "type": "phone", "label": "Phone", "step": 1},
    {"id": "services", "type": "checkbox_group", "label": "Services Needed", "required": true, "step": 2, "options": [
      {"value": "web", "label": "Web Development"}, {"value": "seo", "label": "SEO"}, {"value": "marketing", "label": "Digital Marketing"}, {"value": "design", "label": "Graphic Design"}
    ]},
    {"id": "budget", "type": "select", "label": "Budget Range", "step": 2, "options": [
      {"value": "small", "label": "Under 5,000"}, {"value": "medium", "label": "5,000 - 20,000"}, {"value": "large", "label": "Over 20,000"}
    ]},
    {"id": "details", "type": "textarea", "label": "Project Details", "step": 2, "validation": {"rows": 5}},
    {"id": "privacy", "type": "consent", "label": "Privacy", "consent_text": "I accept the <a href='/privacy/'>privacy policy</a>", "required": true, "step": 2}
  ],
  "settings": {
    "submit_label": "Request Quote",
    "success_message": "Thank you! We will send you a quote within 48 hours.",
    "steps": [{"step": 1, "title": "Your Information"}, {"step": 2, "title": "Project Details"}]
  }
}
```

---

## MCP Tools Reference

| Tool | Purpose |
|------|---------|
| `klytos_forms_create` | Create a new form with fields, settings, notifications |
| `klytos_forms_get` | Get a form definition by ID |
| `klytos_forms_list` | List all forms |
| `klytos_forms_update` | Update form (partial updates supported) |
| `klytos_forms_delete` | Delete a form (requires confirm: true) |
| `klytos_forms_duplicate` | Clone a form |
| `klytos_forms_add_field` | Add a field to an existing form |
| `klytos_forms_update_field` | Update a field in a form |
| `klytos_forms_remove_field` | Remove a field from a form |
| `klytos_forms_reorder_fields` | Reorder fields |
| `klytos_forms_list_entries` | List form submissions |
| `klytos_forms_get_entry` | Get a specific submission |
| `klytos_forms_update_entry_status` | Change entry status |
| `klytos_forms_delete_entry` | Delete an entry |
| `klytos_forms_export_entries` | Export entries as CSV or JSON |
| `klytos_forms_stats` | Get submission statistics |

---

## Important Notes

1. **Always activate the plugin first** with `klytos_activate_plugin` (id: "klytos-forms")
2. **Embed with shortcode:** Use `{{form:form-id}}` in page content
3. **Email configuration:** Forms use the site email settings (configured in `klytos_set_site_config`). For SMTP, configure email.smtp_* settings
4. **File uploads** are stored in `assets/form-uploads/` and managed by the asset system
5. **Consent fields** are important for GDPR compliance — always include one for forms that collect personal data
6. **AJAX submission** is enabled by default and recommended for better user experience
7. **Localization:** The form engine respects the site language for default messages. Customize via settings
