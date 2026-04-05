---
name: klytos-page-structure
description: Complete reference for page data structure, creation, editing, and templates in Klytos CMS. Use when creating pages, updating pages, querying pages, understanding page fields, using page templates, working with hierarchical URLs, managing page content, using page hooks, editing content with Gutenberg blocks, or the PageManager API.
---

# Klytos Page Structure Reference

## When to Use This Skill

Use this reference whenever you need to create, edit, delete, or query pages in Klytos CMS — whether via MCP tools, the admin panel, or plugin code.

---

## Page Data Structure

Every page in Klytos has the following fields:

| Field | Type | Description |
|---|---|---|
| `slug` | string | **Required**. Unique URL identifier. Supports hierarchical paths: `"services/marketing"` |
| `parent_slug` | string | Auto-derived from slug. For `"services/marketing"` → `"services"` |
| `title` | string | **Required**. Page title for `<title>` and `<h1>` |
| `content_html` | string | Full HTML body content (Gutenberg block markup recommended) |
| `content_blocks` | string | Gutenberg block JSON (if using block editor) |
| `meta_description` | string | SEO meta description (max 160 chars) |
| `template` | string | Layout template: `'default'`, `'blank'`, `'blog-post'`, `'landing'` |
| `status` | string | Page status. System statuses: `'draft'`, `'published'`, `'scheduled'`, `'trashed'`. Custom statuses per post type are also supported (see below). |
| `custom_css` | string | Page-specific CSS injected inline |
| `custom_js` | string | Page-specific JavaScript injected inline |
| `og_image` | string | Open Graph image URL |
| `og_title` | string | Custom OG title (falls back to `title`) |
| `og_description` | string | Custom OG description (falls back to `meta_description`) |
| `twitter_title` | string | Twitter card title |
| `twitter_description` | string | Twitter card description |
| `canonical_url` | string | Canonical URL for SEO |
| `noindex` | boolean | Exclude from search engines |
| `lang` | string | Language code: `'es'`, `'en'`, `'ca'`, etc. |
| `hreflang_refs` | array | Language alternates: `{"en": "en/about", "es": "about"}` |
| `order` | int | Sort order (lower = first) |
| `post_type` | string | Content type: `'page'` (default) or custom post type ID |
| `llm_optional` | boolean | Move to "Optional" section of llms.txt (default `false`) |
| `llm_exclude` | boolean | Exclude from all LLM files: llms.txt, llms-full.txt, .html.md (default `false`) |
| `created_at` | string | ISO 8601 timestamp (auto-generated) |
| `updated_at` | string | ISO 8601 timestamp (auto-updated) |

---

## Custom Post Statuses

Beyond the 4 system statuses (`draft`, `published`, `scheduled`, `trashed`), each post type can define **custom statuses**. Custom statuses are configured per post type via `PostTypeManager` and can have properties like `is_public` that affect build behavior.

### Key Points

- **System statuses** are always available for every post type. They are defined in `PageManager::SYSTEM_STATUSES` (alias: `PageManager::VALID_STATUSES`).
- **Custom statuses** are defined per post type. Use `klytos_list_post_statuses` (MCP) or `PostTypeManager::getStatusesForPostType($postTypeId)` (PHP) to see all valid statuses for a given post type.
- **Validation** — `PageManager` validates the status against the post type's allowed statuses via `PostTypeManager::isValidStatusForPostType()`. If the status is not valid for that post type, it falls back to `'draft'`.
- **Build engine** — Pages with `'published'` status are built to the live site. Pages with custom statuses that have `is_public: true` are **also** built to the live site.
- **Before setting a status**, always call `klytos_list_post_statuses` to discover which statuses are valid for the target post type.

### Hook: `page.status_changed`

Fires whenever a page's status changes:

```php
klytos_add_action( 'page.status_changed', function ( array $page, string $oldStatus, string $newStatus ): void {
    // React to status transitions
});
```

| Argument | Type | Description |
|---|---|---|
| `$page` | array | The full page data after the change |
| `$oldStatus` | string | Previous status value |
| `$newStatus` | string | New status value |

---

## Available Templates

| Template | Description | Use Case |
|---|---|---|
| `default` | Standard layout with header, navigation, breadcrumbs, footer | Most pages |
| `blank` | Minimal structure, no header/footer | Full custom control |
| `blog-post` | Article layout, narrow content width (720px), meta info | Blog posts |
| `landing` | Hero section with gradient, multiple content sections | Marketing pages |

### Template Placeholders

Templates use `{{variable}}` syntax for content injection:

| Variable | Description |
|---|---|
| `{{page_content}}` | HTML content from page editor |
| `{{page_title}}` | Page title |
| `{{site_name}}` | Site brand name |
| `{{meta_description}}` | SEO description |
| `{{page_lang}}` | Language code |
| `{{page_slug}}` | URL slug |
| `{{menu_html}}` | Navigation menu HTML |
| `{{footer_html}}` | Generated footer |
| `{{base_path}}` | Relative path to root |
| `{{site_url}}` | Absolute site URL |
| `{{og_image}}` | Open Graph image |
| `{{custom_css}}` | Page-specific inline CSS |
| `{{custom_js}}` | Page-specific inline JS |
| `{{head_scripts}}` | Analytics head scripts |
| `{{body_scripts}}` | Analytics body scripts |
| `{{breadcrumbs}}` | Schema.org breadcrumb navigation |
| `{{hreflang_tags}}` | Multilingual alternate links |
| `{{seo_meta_tags}}` | All SEO meta tags (OG, Twitter, JSON-LD) |
| `{{google_fonts_html}}` | Google Fonts link tags |
| `{{css_variables}}` | `:root` CSS variables block |
| `{{tagline}}` | Site tagline |
| `{{site_tagline}}` | Alias for `{{tagline}}` |

### Structural Block Deduplication

When a page uses a custom template that includes structural elements (e.g. `default.html` with `{{klytos_part:header}}` + `{{klytos_part:footer}}`), the build engine **automatically excludes** the corresponding blocks (top-bar, header, footer) from `{{page_content}}` to prevent duplication. Templates without structural parts (like `blank.html`) receive all blocks normally.

See the **klytos-custom-templates** skill for full details and hooks.

---

## PageManager API (Via Plugin Code)

```php
$pages = klytos_app()->getPages();
```

### Create a Page

```php
$page = $pages->create([
    'slug'             => 'about',
    'title'            => 'About Us',
    'content_html'     => '<!-- wp:paragraph --><p>Welcome...</p><!-- /wp:paragraph -->',
    'meta_description' => 'Learn about our company and mission.',
    'template'         => 'default',
    'status'           => 'published',
    'lang'             => 'en',
]);
```

### Update a Page

```php
$page = $pages->update('about', [
    'title'        => 'About Our Company',
    'status'       => 'published',
    'custom_css'   => '.about-hero { background: #f0f0f0; }',
]);
```

### Delete a Page

```php
$deleted = $pages->delete('about'); // returns bool
```

### Query Pages

```php
$page     = $pages->get('about');                         // Single page
$exists   = $pages->exists('about');                      // bool
$all      = $pages->list('published', 'en', 50, 0);      // List with filters
$count    = $pages->count('published');                    // Count
$children = $pages->getChildren('services', 'published'); // Child pages
```

### Breadcrumbs

```php
$crumbs = $pages->getBreadcrumbs('services/marketing', '/');
// [['title' => 'Home', 'url' => '/'], ['title' => 'Services', 'url' => '/services/'], ...]

$html = $pages->renderBreadcrumbs('services/marketing', '/');
// Returns HTML with JSON-LD schema.org markup
```

---

## Via MCP (AI Interface)

### klytos_list_post_statuses

Call this **before** setting a page status to discover valid statuses for the target post type:

```json
{
    "post_type": "page"
}
```

Returns all system statuses plus any custom statuses defined for that post type.

### klytos_create_page

The `status` field accepts any valid status ID for the page's post type — not just the 4 system statuses. Use `klytos_list_post_statuses` to discover valid values.

```json
{
    "slug": "about",
    "title": "About Us",
    "content_html": "<!-- wp:paragraph --><p>Welcome...</p><!-- /wp:paragraph -->",
    "meta_description": "Learn about our company.",
    "template": "default",
    "status": "published",
    "lang": "en"
}
```

### klytos_update_page

The `status` field accepts any valid status ID for the page's post type. Invalid statuses fall back to `'draft'`.

```json
{
    "slug": "about",
    "title": "About Our Company",
    "status": "published"
}
```

### klytos_delete_page

```json
{
    "slug": "about"
}
```

### klytos_get_page

```json
{
    "slug": "about"
}
```

### klytos_list_pages

The `status` filter accepts any valid status ID — system or custom. Use `klytos_list_post_statuses` to discover valid values.

```json
{
    "status": "published",
    "lang": "en",
    "post_type": "page",
    "limit": 50,
    "offset": 0
}
```

---

## Via Admin (Desktop Interface)

- **List pages**: `admin/pages.php` — Table with status badges, edit/delete actions
- **Create page**: `admin/page-editor.php` — Gutenberg visual editor or HTML fallback
- **Edit page**: `admin/page-editor.php?slug=about` — Same editor with pre-filled data
- **Quick edit**: Inline editing for title, slug, status in the list view (AJAX)
- **Autosave**: Drafts auto-saved via `admin/api/autosave.php`

---

## Hierarchical URLs

Klytos natively supports hierarchical (nested) URLs:

```
services/                    → parent page
services/marketing/          → child page (parent_slug = "services")
services/marketing/seo/      → grandchild page
```

- `parent_slug` is auto-derived from the slug
- `getChildren('services')` returns all direct children
- `getBreadcrumbs('services/marketing/seo')` returns the full trail
- Navigation menus can reflect this hierarchy

---

## Page Hooks

| Hook | Type | Arguments |
|---|---|---|
| `page.before_save` | action | `array $page, string $action` (`'create'` or `'update'`) |
| `page.after_save` | action | `array $page, string $action` |
| `page.before_delete` | action | `string $slug` |
| `page.after_delete` | action | `string $slug` |
| `page.status_changed` | action | `array $page, string $oldStatus, string $newStatus` |
| `page.content` | filter | `string $html` — modify content before render |

### Example: Auto-add reading time to blog posts

```php
klytos_add_filter('page.content', function (string $html): string {
    $wordCount = str_word_count(strip_tags($html));
    $minutes   = max(1, (int) ceil($wordCount / 200));
    return "<p class=\"reading-time\">{$minutes} min read</p>" . $html;
});
```

---

## Content Format: Gutenberg Blocks

When creating pages via MCP, the `content_html` field **MUST** use Gutenberg block markup for the visual editor to work correctly. See the `klytos-gutenberg-blocks` skill for the complete block reference.

**Quick example**:

```html
<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">About Us</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We are a company dedicated to excellence.</p>
<!-- /wp:paragraph -->

<!-- wp:image {"url":"/assets/images/team.jpg","alt":"Our team"} -->
<figure class="wp-block-image"><img src="/assets/images/team.jpg" alt="Our team"/></figure>
<!-- /wp:image -->
```

---

## Source Files

- Page manager: `core/page-manager.php`
- MCP page tools: `core/mcp/tools/page-tools.php`
- Templates: `templates/default.html`, `templates/blank.html`, `templates/blog-post.html`, `templates/landing.html`
- Page editor: `admin/page-editor.php`
