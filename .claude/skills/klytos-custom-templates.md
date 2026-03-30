---
name: klytos-custom-templates
description: Guide for creating custom page templates, reusable blocks, and page template recipes in Klytos CMS.
trigger: When the user needs to create new page templates, add reusable blocks, define block slots, or customize the page rendering structure.
---

# Klytos Custom Templates & Blocks

## When to Use This Skill

Use this reference when you need to create new page layout templates, define reusable HTML blocks with configurable slots, or build page template recipes that combine multiple blocks.

---

## Three Template Systems

| System | Purpose | Stored In |
|---|---|---|
| **HTML Templates** | Page layout structure (header, footer, content area) | `templates/` directory |
| **Page Template Recipes** | Block arrangement for a page type | `'page-templates'` collection |
| **Reusable Blocks** | Configurable HTML components with slots | `'blocks'` collection |

---

## 1. HTML Templates (Layout Files)

Files in the `templates/` directory that define the overall page structure.

### Available Templates

| Template | File | Use Case |
|---|---|---|
| `default` | `templates/default.html` | Standard pages with header, nav, footer |
| `blank` | `templates/blank.html` | Minimal, no chrome |
| `blog-post` | `templates/blog-post.html` | Articles (narrow width, meta info) |
| `landing` | `templates/landing.html` | Marketing (hero, sections) |

### Template Placeholders

```html
{{page_content}}         <!-- Page HTML content -->
{{page_title}}           <!-- Page title -->
{{site_name}}            <!-- Site brand name -->
{{meta_description}}     <!-- SEO description -->
{{page_lang}}            <!-- Language code -->
{{page_slug}}            <!-- URL slug -->
{{menu_html}}            <!-- Navigation menu -->
{{footer_html}}          <!-- Footer HTML -->
{{base_path}}            <!-- Root path -->
{{site_url}}             <!-- Absolute site URL -->
{{breadcrumbs}}          <!-- Breadcrumb navigation + JSON-LD -->
{{seo_meta_tags}}        <!-- OG, Twitter, JSON-LD -->
{{css_variables}}        <!-- :root CSS variables -->
{{google_fonts_html}}    <!-- Google Fonts links -->
{{custom_css}}           <!-- Page-specific CSS -->
{{custom_js}}            <!-- Page-specific JS -->
{{head_scripts}}         <!-- Analytics head scripts -->
{{body_scripts}}         <!-- Analytics body scripts -->
{{hreflang_tags}}        <!-- Multilingual links -->
{{og_image}}             <!-- Open Graph image -->
```

### Creating a New HTML Template

Create a file in `templates/my-template.html`:

```html
<!DOCTYPE html>
<html lang="{{page_lang}}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{page_title}} — {{site_name}}</title>
    {{seo_meta_tags}}
    {{hreflang_tags}}
    <style>{{css_variables}}</style>
    <link rel="stylesheet" href="{{base_path}}assets/css/style.css">
    {{google_fonts_html}}
    {{head_scripts}}
    {{custom_css}}
</head>
<body>
    <header class="klytos-header">
        <div class="klytos-container">
            <a href="{{base_path}}" class="klytos-logo">{{site_name}}</a>
            {{menu_html}}
        </div>
    </header>

    <main class="klytos-main">
        <div class="klytos-container">
            {{breadcrumbs}}
            <div class="my-custom-layout">
                {{page_content}}
            </div>
        </div>
    </main>

    <footer class="klytos-footer">
        {{footer_html}}
    </footer>

    {{body_scripts}}
    {{custom_js}}
</body>
</html>
```

Then assign it to pages via the `template` field:

```json
// Via MCP: klytos_create_page
{
    "slug": "special-page",
    "title": "Special Page",
    "template": "my-template",
    "content_html": "..."
}
```

---

## 2. Page Template Recipes

Stored configurations that define which blocks appear on a page type and in what order.

### Structure

```php
[
    'type'         => string,     // Template ID (e.g. 'landing', 'home')
    'name'         => string,     // Display name
    'description'  => string,     // Description
    'structure'    => array,      // Array of block references
    'wrapper_html' => string,     // Wrapper with {{blocks_html}} placeholder
    'status'       => string,     // 'draft' or 'active'
    'version'      => string,     // Semantic version
]
```

### Core Template Types

`home`, `page`, `post`, `contact`, `landing`, `gallery`, `faq`, `team`, `services`

### Via MCP

#### klytos_create_page_template

```json
{
    "type": "product-page",
    "name": "Product Page",
    "description": "Layout for product detail pages",
    "structure": [
        {"block_id": "hero", "order": 1},
        {"block_id": "product-gallery", "order": 2},
        {"block_id": "product-specs", "order": 3},
        {"block_id": "cta", "order": 4}
    ],
    "wrapper_html": "<div class=\"klytos-page product-layout\">{{blocks_html}}</div>",
    "status": "active"
}
```

#### klytos_add_block_to_template / klytos_remove_block_from_template

```json
{
    "template_type": "product-page",
    "block_id": "testimonials",
    "order": 5
}
```

### Via Plugin Code

```php
$templates = klytos_app()->getPageTemplateManager();

$templates->save([
    'type'        => 'product-page',
    'name'        => 'Product Page',
    'description' => 'For product detail pages',
    'structure'   => [
        ['block_id' => 'hero', 'order' => 1],
        ['block_id' => 'product-specs', 'order' => 2],
    ],
    'wrapper_html' => '<div class="klytos-page">{{blocks_html}}</div>',
    'status'       => 'active',
    'version'      => '1.0.0',
]);

$template = $templates->get('product-page');
$all      = $templates->list('active');
$templates->delete('product-page');
```

---

## 3. Reusable Blocks

Configurable HTML components with "slots" (editable fields).

### Block Structure

```php
[
    'id'          => string,      // Block ID
    'name'        => string,      // Display name
    'category'    => string,      // Category
    'version'     => string,      // Semantic version
    'status'      => string,      // 'active' or 'draft'
    'scope'       => string,      // 'global', 'template', or 'page'
    'slots'       => array,       // Slot definitions
    'html'        => string,      // Template with {{slot_name}} placeholders
    'css'         => string,      // Custom CSS
    'js'          => string,      // Custom JS
    'sample_data' => array,       // Preview data
]
```

### Block Categories

| Category | Examples |
|---|---|
| `structure` | header, footer, menu, sidebar, breadcrumb |
| `content` | hero, text-block, image-text, gallery, video, blog-list |
| `interaction` | contact-form, faq-accordion, cta, stats-counter |
| `social-proof` | testimonials, team-grid, logo-bar, map-embed |
| `custom` | Plugin-provided or AI-generated blocks |

### Block Scopes

| Scope | Description |
|---|---|
| `global` | Same data across entire site (e.g. header, footer). Edited once. |
| `template` | Configured at page template level |
| `page` | Each page has its own data for this block |

### Slot Types

`text`, `richtext`, `image`, `url`, `icon`, `color`, `number`, `select`, `boolean`, `array`, `html`, `date`, `email`, `phone`

Extend with the `block.slot_types` filter.

### Via MCP

#### klytos_create_block

```json
{
    "id": "pricing-card",
    "name": "Pricing Card",
    "category": "interaction",
    "scope": "page",
    "slots": [
        {"name": "plan_name", "type": "text", "label": "Plan Name", "required": true},
        {"name": "price", "type": "number", "label": "Monthly Price", "required": true},
        {"name": "features", "type": "richtext", "label": "Features List"},
        {"name": "cta_text", "type": "text", "label": "Button Text", "default": "Get Started"},
        {"name": "cta_url", "type": "url", "label": "Button URL"},
        {"name": "highlighted", "type": "boolean", "label": "Highlight Card", "default": false}
    ],
    "html": "<div class=\"pricing-card {{highlighted ? 'highlighted' : ''}}\"><h3>{{plan_name}}</h3><div class=\"price\">€{{price}}/mo</div><div class=\"features\">{{features}}</div><a href=\"{{cta_url}}\" class=\"klytos-btn klytos-btn-primary\">{{cta_text}}</a></div>",
    "css": ".pricing-card { background: var(--klytos-surface); padding: 2rem; border-radius: var(--klytos-radius); text-align: center; } .pricing-card.highlighted { border: 2px solid var(--klytos-primary); }",
    "status": "active"
}
```

### Via Plugin Code

```php
$blocks = klytos_app()->getBlockManager();

$blocks->save([
    'id'       => 'pricing-card',
    'name'     => 'Pricing Card',
    'category' => 'interaction',
    'scope'    => 'page',
    'slots'    => [...],
    'html'     => '...',
    'css'      => '...',
    'status'   => 'active',
]);

$block   = $blocks->get('pricing-card');
$all     = $blocks->list('interaction', 'active');
$html    = $blocks->render('pricing-card', ['plan_name' => 'Pro', 'price' => 29]);
$blocks->delete('pricing-card');
```

### Hooks

| Hook | Type | Arguments |
|---|---|---|
| `block.before_save` | action | `array $block` |
| `block.after_save` | action | `array $block` |
| `block.available_types` | filter | `array $types` |
| `block.slot_types` | filter | `array $types` |
| `block.rendered_html` | filter | `string $html` |
| `block.global_data_changed` | action | `string $blockId, mixed $data` |
| `page_template.before_save` | action | `array $template` |
| `page_template.after_save` | action | `array $template` |
| `page_template.available_types` | filter | `array $types` |
| `page_template.wrapper_html` | filter | `string $html` |
| `build.head_html` | filter | `string $html` — inject into `<head>` |
| `build.body_end_html` | filter | `string $html` — inject before `</body>` |

---

## Source Files

- Page template manager: `core/page-template-manager.php`
- Block manager: `core/block-manager.php`
- Build engine: `core/build-engine.php`
- HTML templates: `templates/`
- MCP template tools: `core/mcp/tools/page-template-tools.php`
- MCP block tools: `core/mcp/tools/block-tools.php`
