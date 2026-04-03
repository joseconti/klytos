# v2.0 Block-Based Page Rendering

Pages can now use structured block content (v2.0) instead of raw HTML (v1.0).

## Page Data Format (v2.0)

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

## Smart Rebuild

Global blocks (header, footer, top-bar) inject HTML comment markers:
```html
<!--klytos:block:footer-->..content..<!--/klytos:block:footer-->
```

`BuildEngine::smartRebuildBlock($blockId)` finds and replaces these markers across all generated HTML files (~50ms for 100 pages) without a full rebuild.

## Block CSS/JS Aggregation

- `blocks.css` — aggregated CSS from all active blocks → `assets/css/blocks.css`
- `blocks.js` — aggregated JS from blocks that have JS → `assets/js/blocks.js`
- Template placeholders: `{{blocks_css_link}}`, `{{blocks_js_script}}`

## New MCP Build Tools

| Tool | Description |
|------|-------------|
| `klytos_rebuild_block` | Smart rebuild a global block across all HTML files |
| `klytos_rebuild_css` | Regenerate theme CSS + block CSS without full rebuild |

## PageTemplateManager New Methods

- `preview(string $type): string` — Render template with sample_data.
- `previewWithData(string $type, array $data): string` — Render with custom data.

## Admin Pages

- `admin/blocks.php` — Block listing grouped by category (structure, content, interaction, social-proof, custom).
- `admin/block-data.php?id=footer` — Edit global block data with form + live preview + "Save & Rebuild".
- `admin/template-preview.php?type=home` — Full template preview with block structure sidebar.

## Core Blocks (23 total)

| Category | Blocks |
|----------|--------|
| structure | header, footer, breadcrumb, cookie-banner, top-bar, menu, sidebar |
| content | hero, text-block, image-text, gallery, video-embed, blog-list |
| interaction | cta, faq-accordion, stats-counter, contact-form |
| social-proof | testimonials, team-grid, logo-bar, map-embed |

## Structural Block Deduplication

The build engine detects when the custom template (HTML shell) already provides structural elements and automatically excludes those blocks from `{{page_content}}` to prevent duplication.

**Detection:** Before rendering blocks, `BuildEngine::detectProvidedStructure()` scans the template for:
- `{{klytos_part:header}}` / `{{klytos_part:footer}}` (raw template)
- `{{header_html}}` / `{{footer_html}}` (raw template)
- `<header` / `<footer` HTML tags (processed template)

**Result:** Matching block IDs are passed to `PageTemplateManager::renderPage()` as `$excludeBlocks` and filtered out before block assembly.

**Example:** `default.html` has `{{klytos_part:header}}` + `{{klytos_part:footer}}` → `top-bar`, `header`, `footer` blocks are excluded from `{{page_content}}`. `blank.html` has none → all blocks render.

## Additional Hooks (v2.0)

| Hook | Type | Purpose |
|------|------|---------|
| `page_template.structure` | filter | Modify template block structure before rendering |
| `page_template.structure_after_dedup` | filter | Modify structure after structural dedup filtering |
| `build.structural_block_mapping` | filter | Customize structural element → block ID mapping |
| `build.exclude_structural_blocks` | filter | Override the final block exclusion list |
| `block.css` | filter | Modify a block's CSS during aggregation |
| `build.global_blocks` | filter | Modify cached global block HTML during build |

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

## 2. Template Parts (Resolution Order)

Shared HTML fragments included via `{{klytos_part:NAME}}` syntax. Parts are resolved with the same hierarchy as templates but within `parts/` subdirectories.

### Resolution Order

1. `custom-templates/parts/{name}.html` — User override
2. Plugin filter `template_part.{name}` — Plugin injection via hooks
3. `templates/parts/{name}.html` — Core default

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

### Source Files

- Part processing: `core/build-engine.php` (`processTemplateParts()`)
- Part resolution: `core/template-resolver.php` (`resolvePart()`)
- Core part files: `templates/parts/head.html`, `header.html`, `footer.html`, `scripts.html`

## 3. Frontend Hook Points (Resolution Behavior)

HTML elements with `data-klytos-hook` attributes where plugins inject content via JavaScript. This avoids rebuilding thousands of static pages when a plugin is activated/deactivated.

### How It Works

1. Templates include `<div data-klytos-hook="hook_name"></div>` divs
2. Plugins provide `assets/js/hooks.js` with `registerHook()` calls
3. BuildEngine concatenates all plugin hooks into `assets/js/klytos-hooks.js`
4. On page load, the executor runs registered callbacks for each hook point

### Template Variables

- `{{plugin_css_link}}` -- `<link>` tag for plugins.css (empty if no plugin CSS)
- `{{hooks_js_script}}` -- `<script>` tag for klytos-hooks.js (empty if no hooks)

### Source Files

- JS prelude: `core/assets/klytos-hooks-prelude.js`
- JS executor: `core/assets/klytos-hooks-executor.js`
- Build methods: `core/build-engine.php` (`buildHooksJs()`, `buildPluginsCss()`)
- Asset rebuild trigger: `core/plugin-loader.php` (`build.assets_changed` action)

## 5. Page Template Recipes (Structure)

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

## 6. Reusable Blocks (Categories)

Configurable HTML components with "slots" (editable fields).

### Block Categories

| Category | Examples |
|---|---|
| `structure` | header, footer, menu, sidebar, breadcrumb |
| `content` | hero, text-block, image-text, gallery, video, blog-list |
| `interaction` | contact-form, faq-accordion, cta, stats-counter |
| `social-proof` | testimonials, team-grid, logo-bar, map-embed |
| `custom` | Plugin-provided or AI-generated blocks |

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
