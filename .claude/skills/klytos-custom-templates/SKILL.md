---
name: Klytos Custom Templates
description: Guide for creating custom page templates, template parts, hook points, reusable blocks, and page template recipes in Klytos CMS. Use when creating new page layout templates, customizing template parts, adding frontend hook points, defining reusable blocks with slots, or building page template recipes combining multiple blocks.
---

# Klytos Custom Templates & Blocks

## When to Use This Skill

Use this reference when you need to create new page layout templates, customize template parts, add frontend hook points, define reusable HTML blocks with configurable slots, or build page template recipes that combine multiple blocks.

---

## Template Systems Overview

| System | Purpose | Stored In |
|---|---|---|
| **TemplateResolver** | 4-level template resolution hierarchy | `core/template-resolver.php` |
| **Template Parts** | Shared fragments (header, footer, head, scripts) | `templates/parts/` + `custom-templates/parts/` |
| **Hook Points** | Frontend JS injection points (`data-klytos-hook`) | `core/assets/klytos-hooks-*.js` |
| **HTML Templates** | Page layout structure | `templates/` + `custom-templates/` |
| **Page Template Recipes** | Block arrangement for a page type | `'page-templates'` collection |
| **Reusable Blocks** | Configurable HTML components with slots | `'blocks'` collection |

---

## 1. TemplateResolver (4-Level Hierarchy)

When the BuildEngine needs a template, it resolves it through this hierarchy (first match wins):

1. `custom-templates/{name}.html` -- User customizations (NEVER overwritten by updates)
2. Plugin-registered templates -- Via `klytos_register_templates()` in plugin `init.php`
3. `templates.json.enc` -- Created from the admin UI (stored in DB/storage)
4. `templates/{name}.html` -- Core templates (overwritten on update)

### Resolution by Post Type

For pages with a `post_type` field, templates are resolved in this order:

1. `single-{post_type}-{slug}` (e.g. `single-product-camiseta-azul`)
2. `single-{post_type}` (e.g. `single-product`)
3. Page's chosen template (from admin editor)
4. `default`

### Plugin Template Registration

```php
// In plugin init.php
klytos_register_templates('my-plugin', [
    'single-product' => [
        'file'        => __DIR__ . '/templates/single-product.html',
        'name'        => 'Product Page',
        'description' => 'Template for product detail pages',
        'post_type'   => 'product',
    ],
    'cart' => [
        'file'        => __DIR__ . '/templates/cart.html',
        'name'        => 'Shopping Cart',
        'description' => 'Cart page template',
        'dynamic'     => true,
    ],
]);
```

### User Customization (Survives Updates)

Place template files in `custom-templates/`:
- `custom-templates/default.html` -- Overrides core `templates/default.html`
- `custom-templates/single-product.html` -- Overrides plugin's product template
- `custom-templates/my-custom.html` -- New custom template

---

## 2. Template Parts

Shared HTML fragments included via `{{klytos_part:NAME}}` syntax. Parts are resolved with the same hierarchy as templates but within `parts/` subdirectories.

### Core Parts

| Part | File | Content |
|---|---|---|
| `head` | `templates/parts/head.html` | Meta tags, CSS, fonts, favicon, plugin CSS link |
| `header` | `templates/parts/header.html` | Logo, navigation menu |
| `footer` | `templates/parts/footer.html` | Footer HTML |
| `scripts` | `templates/parts/scripts.html` | JS, body scripts, hooks JS, plugin body end |

### Using Parts in Templates

```html
<!DOCTYPE html>
<html lang="{{page_lang}}">
<head>
  {{klytos_part:head}}
</head>
<body>
  {{klytos_part:header}}

  <main class="klytos-main">
    <div class="klytos-container">
      {{page_content}}
    </div>
  </main>

  {{klytos_part:footer}}
  {{klytos_part:scripts}}
</body>
</html>
```

### User Override

Place a file at `custom-templates/parts/header.html` to completely replace the core header.

### Structural Block Deduplication (Automatic)

**CRITICAL:** The build engine automatically prevents header/footer duplication between custom templates and page templates.

When a custom template includes structural elements (via `{{klytos_part:header}}`, `{{klytos_part:footer}}`, `{{header_html}}`, `{{footer_html}}`, or hardcoded `<header>`/`<footer>` tags), the corresponding blocks are **automatically excluded** from the page template rendering inside `{{page_content}}`.

| Custom Template Provides | Blocks Excluded from Page Template |
|---|---|
| `{{klytos_part:header}}` or `<header>` | `top-bar`, `header` |
| `{{klytos_part:footer}}` or `<footer>` | `footer` |

**Rules for AI assistants:**
- **DO NOT** manually remove structural blocks from page templates — the engine handles deduplication automatically.
- Page templates MUST remain self-contained (include all structural blocks) because `blank.html` relies on them.
- If creating a custom template with `{{klytos_part:header}}` + `{{klytos_part:footer}}`, the page template's header/footer blocks are automatically skipped.
- If creating a custom template WITHOUT structural parts (like `blank.html`), all blocks from the page template render normally.

**Hooks for plugins:**
- `build.structural_block_mapping` (filter) — Customize which template indicators map to which block IDs.
- `build.exclude_structural_blocks` (filter) — Override the final exclusion list.
- `page_template.structure_after_dedup` (filter) — Modify structure after deduplication filtering.

---

## 3. Frontend Hook Points

HTML elements with `data-klytos-hook` attributes where plugins inject content via JavaScript. This avoids rebuilding thousands of static pages when a plugin is activated/deactivated.

### Plugin Hook Registration

Create `plugins/{plugin-id}/assets/js/hooks.js`:

```javascript
// Register a hook for the "after_add_to_cart" hook point
registerHook('after_add_to_cart', function(el, pageData) {
    if (pageData.type !== 'product') return;
    el.innerHTML = '<div class="share-buttons">Share: ...</div>';
}, 10);
```

### Plugin CSS

Place CSS files in `plugins/{plugin-id}/assets/css/`. They are concatenated into `assets/css/plugins.css` automatically.

### Regeneration Behavior

| Event | JS/CSS rebuilt | Pages rebuilt |
|---|---|---|
| Plugin activated | YES | NO |
| Plugin deactivated | YES | NO |
| `buildAll()` called | YES | YES |
| Page edited | NO | Only that page |

---

## 4. Page Template Recipes

Stored configurations that define which blocks appear on a page type and in what order.

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
```

---

## 5. Reusable Blocks

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
```

---

## Hooks Reference

| Hook | Type | Arguments |
|---|---|---|
| `template_part.{name}` | filter | `?string $html` -- modify a template part |
| `build.assets_changed` | action | (none) -- triggered on plugin activate/deactivate |
| `build.head_html` | filter | `string $html` -- inject into `<head>` |
| `build.body_end_html` | filter | `string $html` -- inject before `</body>` |
| `page.content` | filter | `string $html, array $page` -- modify page content |
| `block.before_save` | action | `array $block` |
| `block.after_save` | action | `array $block` |
| `page_template.before_save` | action | `array $template` |
| `page_template.after_save` | action | `array $template` |

---

## Helper Functions

```php
klytos_register_templates(string $pluginId, array $templates): void
klytos_register_template_part(string $partName, callable $callback, int $priority = 10): void
```

---

## Directory Structure

```
installer/
  templates/                     # CORE — Overwritten on updates
    default.html                 #   Uses {{klytos_part:X}} syntax
    landing.html
    blog-post.html
    blank.html
    parts/                       #   Shared fragments
      head.html
      header.html
      footer.html
      scripts.html

  custom-templates/              # USER — NEVER overwritten
    parts/                       #   Override core parts here

  plugins/{plugin-id}/
    templates/                   #   Plugin templates
    assets/js/hooks.js           #   Frontend hook registrations
    assets/css/*.css             #   Plugin CSS (auto-concatenated)

  core/
    template-resolver.php        #   4-level resolution engine
    build-engine.php             #   Build with parts, hooks, post type resolution
```

---

## Template Placeholders

The following variables are available in HTML templates:

```
{{page_content}}         <!-- Page HTML content -->
{{page_title}}           <!-- Page title -->
{{site_name}}            <!-- Site brand name -->
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
{{plugin_head_html}}     <!-- Plugin <head> injections -->
{{plugin_body_end_html}} <!-- Plugin </body> injections -->
{{plugin_css_link}}      <!-- Plugin CSS stylesheet link -->
{{hooks_js_script}}      <!-- Frontend hooks JS script tag -->
{{klytos_part:NAME}}     <!-- Template part inclusion -->
```

---

## Source Files

- Template resolver: `core/template-resolver.php`
- Build engine: `core/build-engine.php`
- Page template manager: `core/page-template-manager.php`
- Block manager: `core/block-manager.php`
- HTML templates: `templates/`
- Template parts: `templates/parts/`
- Custom templates: `custom-templates/`
- Hook JS assets: `core/assets/klytos-hooks-prelude.js`, `core/assets/klytos-hooks-executor.js`

**For complete v2.0 block rendering, see the `references/v2-block-rendering.md` file.**
