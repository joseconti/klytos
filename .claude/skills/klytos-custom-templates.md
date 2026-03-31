---
name: klytos-custom-templates
description: Guide for creating custom page templates, template parts, hook points, reusable blocks, and page template recipes in Klytos CMS.
trigger: When the user needs to create new page templates, customize template parts, add hook points, add reusable blocks, define block slots, or customize the page rendering structure.
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

### Source Files

- TemplateResolver: `core/template-resolver.php`
- BuildEngine integration: `core/build-engine.php` (`resolveTemplateForPage()`, `loadTemplate()`)
- App initialization: `core/app.php` (Step 10d in `boot()`)

---

## 2. Template Parts

Shared HTML fragments included via `{{klytos_part:NAME}}` syntax. Parts are resolved with the same hierarchy as templates but within `parts/` subdirectories.

### Resolution Order

1. `custom-templates/parts/{name}.html` -- User override
2. Plugin filter `template_part.{name}` -- Plugin injection via hooks
3. `templates/parts/{name}.html` -- Core default

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

### Plugin Modification of Parts

```php
// In plugin init.php: add cart icon to header
klytos_register_template_part('header', function(?string $html): string {
    $cartIcon = '<div class="cart-icon"><a href="/cart">Cart</a></div>';
    if ($html !== null) {
        return str_replace('</header>', $cartIcon . '</header>', $html);
    }
    return $cartIcon;
}, 10);
```

### User Override

Place a file at `custom-templates/parts/header.html` to completely replace the core header.

### Source Files

- Part processing: `core/build-engine.php` (`processTemplateParts()`)
- Part resolution: `core/template-resolver.php` (`resolvePart()`)
- Core part files: `templates/parts/head.html`, `header.html`, `footer.html`, `scripts.html`

---

## 3. Frontend Hook Points

HTML elements with `data-klytos-hook` attributes where plugins inject content via JavaScript. This avoids rebuilding thousands of static pages when a plugin is activated/deactivated.

### How It Works

1. Templates include `<div data-klytos-hook="hook_name"></div>` divs
2. Plugins provide `assets/js/hooks.js` with `registerHook()` calls
3. BuildEngine concatenates all plugin hooks into `assets/js/klytos-hooks.js`
4. On page load, the executor runs registered callbacks for each hook point

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

### Regeneration

| Event | JS/CSS rebuilt | Pages rebuilt |
|---|---|---|
| Plugin activated | YES | NO |
| Plugin deactivated | YES | NO |
| `buildAll()` called | YES | YES |
| Page edited | NO | Only that page |

### Template Variables

- `{{plugin_css_link}}` -- `<link>` tag for plugins.css (empty if no plugin CSS)
- `{{hooks_js_script}}` -- `<script>` tag for klytos-hooks.js (empty if no hooks)

### Source Files

- JS prelude: `core/assets/klytos-hooks-prelude.js`
- JS executor: `core/assets/klytos-hooks-executor.js`
- Build methods: `core/build-engine.php` (`buildHooksJs()`, `buildPluginsCss()`)
- Asset rebuild trigger: `core/plugin-loader.php` (`build.assets_changed` action)

---

## 4. HTML Templates (Layout Files)

Files in the `templates/` directory that define the overall page structure.

### Available Templates

| Template | File | Use Case |
|---|---|---|
| `default` | `templates/default.html` | Standard pages (uses template parts) |
| `blank` | `templates/blank.html` | Minimal, no chrome |
| `blog-post` | `templates/blog-post.html` | Articles (narrow width, meta info) |
| `landing` | `templates/landing.html` | Marketing (hero, sections) |

### Template Placeholders

```html
{{page_content}}         <!-- Page HTML content -->
{{page_title}}           <!-- Page title -->
{{site_name}}            <!-- Site brand name -->
{{title_separator}}      <!-- Smart separator ( -- ) or empty -->
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
{{plugin_head_html}}     <!-- Plugin <head> injections -->
{{plugin_body_end_html}} <!-- Plugin </body> injections -->
{{plugin_css_link}}      <!-- Plugin CSS stylesheet link -->
{{hooks_js_script}}      <!-- Frontend hooks JS script tag -->
{{klytos_part:NAME}}     <!-- Template part inclusion -->
```

---

## 5. Page Template Recipes

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

## 6. Reusable Blocks

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
| `block.rendered_html` | filter | `string $html` |
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
  templates/                     # CORE -- Overwritten on updates
    default.html                 #   Uses {{klytos_part:X}} syntax
    landing.html
    blog-post.html
    blank.html
    parts/                       #   Shared fragments
      head.html
      header.html
      footer.html
      scripts.html

  custom-templates/              # USER -- NEVER overwritten
    parts/                       #   Override core parts here

  plugins/{plugin-id}/
    templates/                   #   Plugin templates
    assets/js/hooks.js           #   Frontend hook registrations
    assets/css/*.css              #   Plugin CSS (auto-concatenated)

  core/
    template-resolver.php        #   4-level resolution engine
    build-engine.php             #   Build with parts, hooks, post type resolution
    assets/
      klytos-hooks-prelude.js    #   Hook registry + utilities
      klytos-hooks-executor.js   #   DOMContentLoaded executor
```

---

## v2.0 Block-Based Page Rendering

Pages can now use structured block content (v2.0) instead of raw HTML (v1.0).

### Page Data Format (v2.0)

```json
{
  "slug": "index",
  "template": "home",
  "content": {
    "hero": { "heading": "Welcome", "subheading": "...", "cta_text": "Contact", "cta_url": "/contact/" },
    "testimonials": { "heading": "Reviews", "testimonials_html": "..." }
  }
}
```

- **v2.0 detection:** `PageManager::hasBlockContent($page)` — returns true when `$page['content']` is a non-empty array.
- **Backward compatible:** Pages with only `content_html` (v1.0) continue to work unchanged.
- **Build engine** checks: if page has `content` + `template`, it uses `PageTemplateManager::renderPage()` to assemble blocks; otherwise uses raw `content_html`.

### Smart Rebuild

Global blocks (header, footer, top-bar) inject HTML comment markers:
```html
<!--klytos:block:footer-->..content..<!--/klytos:block:footer-->
```

`BuildEngine::smartRebuildBlock($blockId)` finds and replaces these markers across all generated HTML files (~50ms for 100 pages) without a full rebuild.

### Block CSS/JS Aggregation

- `blocks.css` — aggregated CSS from all active blocks → `assets/css/blocks.css`
- `blocks.js` — aggregated JS from blocks that have JS → `assets/js/blocks.js`
- Template placeholders: `{{blocks_css_link}}`, `{{blocks_js_script}}`

### New MCP Build Tools

| Tool | Description |
|------|-------------|
| `klytos_rebuild_block` | Smart rebuild a global block across all HTML files |
| `klytos_rebuild_css` | Regenerate theme CSS + block CSS without full rebuild |

### PageTemplateManager New Methods

- `preview(string $type): string` — Render template with sample_data.
- `previewWithData(string $type, array $data): string` — Render with custom data.

### Admin Pages

- `admin/blocks.php` — Block listing grouped by category (structure, content, interaction, social-proof, custom).
- `admin/block-data.php?id=footer` — Edit global block data with form + live preview + "Save & Rebuild".
- `admin/template-preview.php?type=home` — Full template preview with block structure sidebar.

### Core Blocks (23 total)

| Category | Blocks |
|----------|--------|
| structure | header, footer, breadcrumb, cookie-banner, top-bar, menu, sidebar |
| content | hero, text-block, image-text, gallery, video-embed, blog-list |
| interaction | cta, faq-accordion, stats-counter, contact-form |
| social-proof | testimonials, team-grid, logo-bar, map-embed |

### Additional Hooks (v2.0)

| Hook | Type | Purpose |
|------|------|---------|
| `page_template.structure` | filter | Modify template block structure before rendering |
| `block.css` | filter | Modify a block's CSS during aggregation |
| `build.global_blocks` | filter | Modify cached global block HTML during build |

---

## Source Files

- Template resolver: `core/template-resolver.php`
- Build engine: `core/build-engine.php`
- Page template manager: `core/page-template-manager.php`
- Block manager: `core/block-manager.php`
- Page manager: `core/page-manager.php`
- Seed data: `core/seed-data.php`
- HTML templates: `templates/`
- Template parts: `templates/parts/`
- Custom templates: `custom-templates/`
- Hook JS assets: `core/assets/klytos-hooks-prelude.js`, `core/assets/klytos-hooks-executor.js`
- Helper functions: `core/helpers-global.php`
- MCP template tools: `core/mcp/tools/page-template-tools.php`
- MCP block tools: `core/mcp/tools/block-tools.php`
- MCP build tools: `core/mcp/tools/build-tools.php`
- Admin blocks: `admin/blocks.php`
- Admin block data: `admin/block-data.php`
- Admin template preview: `admin/template-preview.php`
