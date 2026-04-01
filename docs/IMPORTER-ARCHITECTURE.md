# Klytos Importer — Plugin Architecture

**Version:** 1.0.0-draft
**Date:** 2026-04-01
**Status:** Proposal
**Author:** Jose Conti + AI Architecture

---

## Table of Contents

1. [Overview](#1-overview)
2. [Problem Statement](#2-problem-statement)
3. [Architecture Principles](#3-architecture-principles)
4. [Plugin Structure](#4-plugin-structure)
5. [MCP Tools Reference](#5-mcp-tools-reference)
6. [Import Sources](#6-import-sources)
7. [Content Conversion Pipeline](#7-content-conversion-pipeline)
8. [Style Replication System](#8-style-replication-system)
9. [Media Handling](#9-media-handling)
10. [Import Session Management](#10-import-session-management)
11. [Admin UI](#11-admin-ui)
12. [AI Assistant SKILL](#12-ai-assistant-skill)
13. [Security Considerations](#13-security-considerations)
14. [Implementation Phases](#14-implementation-phases)

---

## 1. Overview

Klytos Importer is a plugin that enables AI-assisted migration of any website into Klytos. It provides MCP tools that an AI agent (the built-in Klytos Chat, Claude, or any MCP-compatible assistant) can use to analyze, fetch, convert, and import content from external websites.

The plugin supports three input methods:

- **WordPress XML export** — Direct parsing of WP's standard export format (WXR).
- **Sitemap-guided import** — Feed a sitemap.xml URL and the AI crawls each listed page.
- **AI-driven scraping** — The AI navigates the site starting from the homepage, discovers pages, and imports them.

The AI orchestrates the entire process autonomously, using the importer tools in combination with Klytos core tools (`klytos_create_page`, `klytos_set_theme`, `klytos_set_menu`, etc.).

---

## 2. Problem Statement

Migrating content to a new CMS is historically painful. Users face:

- Sites with no export functionality at all (static sites, custom CMSs, legacy platforms).
- Export formats that lose styling, structure, or media.
- Manual copy-paste that takes hours or days.
- Technical knowledge barriers (XML parsing, HTML cleanup, CSS mapping).

Klytos is uniquely positioned to solve this because:

- The entire CMS is AI-controlled via MCP — the AI already knows how to create pages, set themes, and build sites.
- Content is stored as Gutenberg block markup, which is a well-understood format for AI.
- The template/block system is flexible enough to reproduce almost any layout.
- The AI can make intelligent decisions about content structure, not just blind copying.

---

## 3. Architecture Principles

**3.1 Two-layer design: Acquisition vs. Processing**

The plugin separates content acquisition (fetching HTML from external sources) from content processing (parsing, converting, mapping). This is critical because:

- When the AI agent has its own browsing capabilities (Claude with Chrome, ChatGPT with browsing, etc.), it can fetch pages itself and pass the raw HTML to the processing tools. This handles JavaScript-rendered sites, authentication, and avoids server-side restrictions.
- When the AI agent does not have browsing capabilities (Klytos built-in chat, API-only connections), the plugin provides PHP-based fetching via cURL as a fallback.
- The processing layer is always PHP on the server — deterministic, fast, and consistent.

**3.2 Analyze first, import second**

Every import begins with an analysis phase that returns a structured report. The AI reviews this report, may ask the user one clarifying question if needed, then proceeds with the actual import. This prevents importing 500 blog posts when the user only wanted 10 pages.

**3.3 Incremental and resumable**

Import sessions are persistent. If the process is interrupted (timeout, network error, user closes browser), it can be resumed from where it left off. Each page tracks its own status within the session.

**3.4 Non-destructive by default**

Imported pages are created with `status: "draft"`. Nothing goes live until the user explicitly publishes. The AI should inform the user of this at the start of the process.

**3.5 The AI decides, the plugin executes**

The plugin tools are deliberately granular. The AI decides the order, which pages to import, how to handle edge cases, and what template to assign. The plugin just provides the capabilities. The SKILL section (Section 12) guides the AI on how to make these decisions well.

---

## 4. Plugin Structure

```
plugins/klytos-importer/
  klytos-importer.php              Entry point, hook registration
  klytos-plugin.json               Extended metadata and MCP tool declarations
  install.php                      First activation: create import_sessions collection
  deactivate.php                   Cleanup active sessions
  uninstall.php                    Remove all import data
  src/
    ImportSession.php              Session persistence and state management
    WPXMLParser.php                WordPress WXR format parser
    SitemapParser.php              Sitemap XML/index parser
    PageFetcher.php                PHP cURL page fetcher (fallback)
    ContentExtractor.php           HTML to clean content extraction
    StyleAnalyzer.php              CSS analysis and theme mapping
    ContentMapper.php              Generic HTML to Gutenberg block conversion
    MediaDownloader.php            Download and register external media
    ImportValidator.php            Pre-import validation checks
  admin/
    import.php                     Admin page: upload WP XML, enter URL, view sessions
    assets/
      import.css                   Import UI styles
      import.js                    Import UI logic (progress, file upload)
  lang/
    en.json                        English translations
    es.json                        Spanish translations
  migrations/
    001-create-import-sessions.php Initial data structure
```

### 4.1 Plugin Header

```php
<?php
/**
 * Plugin Name: Klytos Importer
 * Plugin URI: https://klytos.io/plugins/importer
 * Description: AI-powered content migration from any website to Klytos
 * Version: 1.0.0
 * Author: Jose Conti
 * Author URI: https://joseconti.com
 * Requires Klytos: 0.9.0
 * Requires PHP: 8.1
 * License: ELv2
 * License URI: https://www.elastic.co/licensing/elastic-license
 * Text Domain: klytos-importer
 * Domain Path: /lang
 * Premium: false
 */
```

### 4.2 klytos-plugin.json

```json
{
  "admin_pages": [
    {
      "id": "klytos-importer",
      "label": "Site Importer",
      "icon": "download",
      "parent": null,
      "order": 80
    }
  ],
  "mcp_tools": [
    {
      "name": "importer_tools",
      "description": "Content migration and import tools",
      "file": "src/mcp-tools.php",
      "function": "registerImporterTools"
    }
  ],
  "permissions": [
    "manage_pages",
    "manage_theme",
    "manage_assets"
  ]
}
```

---

## 5. MCP Tools Reference

The plugin registers 10 MCP tools. They are organized by phase: **analyze**, **fetch**, **convert**, and **execute**.

---

### 5.1 ANALYSIS PHASE

#### `klytos_import_analyze_wp_xml`

Parse a WordPress WXR export file and return a structured summary of its contents.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `file_path` | string | yes | Path to the uploaded XML file within Klytos assets |

**Returns:**
```json
{
  "success": true,
  "source": "wordpress",
  "wp_version": "6.5",
  "site_url": "https://example.com",
  "site_title": "Example Site",
  "summary": {
    "pages": 12,
    "posts": 87,
    "categories": 5,
    "tags": 23,
    "menus": 2,
    "media_attachments": 156,
    "authors": 3,
    "custom_post_types": {
      "portfolio": 15,
      "testimonial": 8
    }
  },
  "pages_list": [
    {"title": "Home", "slug": "home", "status": "publish", "has_content": true},
    {"title": "About Us", "slug": "about", "status": "publish", "has_content": true}
  ],
  "posts_list": [
    {"title": "First Post", "slug": "first-post", "date": "2025-03-15", "categories": ["News"]}
  ],
  "menus": [
    {"name": "Main Menu", "items_count": 8}
  ],
  "session_id": "imp_abc123"
}
```

**Annotations:** readOnlyHint=true, destructiveHint=false, idempotentHint=true

---

#### `klytos_import_analyze_sitemap`

Fetch and parse a sitemap.xml (or sitemap index) and return the list of discovered URLs with metadata.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sitemap_url` | string | yes | Full URL to sitemap.xml or sitemap index |
| `max_urls` | integer | no | Maximum URLs to process (default: 500) |

**Returns:**
```json
{
  "success": true,
  "source": "sitemap",
  "site_url": "https://example.com",
  "total_urls": 45,
  "urls": [
    {
      "loc": "https://example.com/",
      "lastmod": "2025-12-01",
      "priority": "1.0",
      "suggested_slug": "index",
      "suggested_type": "page"
    },
    {
      "loc": "https://example.com/about",
      "lastmod": "2025-11-15",
      "priority": "0.8",
      "suggested_slug": "about",
      "suggested_type": "page"
    }
  ],
  "sitemaps_found": 1,
  "session_id": "imp_def456"
}
```

The tool infers `suggested_type` from URL patterns: paths like `/blog/post-title` or `/2025/03/post` are tagged as "post", others as "page".

**Annotations:** readOnlyHint=true, destructiveHint=false, idempotentHint=true

---

#### `klytos_import_discover_site`

Starting from a URL, crawl the site by following internal links. Returns a site map with page hierarchy.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `start_url` | string | yes | Homepage or starting URL |
| `max_depth` | integer | no | Maximum link depth to follow (default: 3) |
| `max_pages` | integer | no | Maximum pages to discover (default: 100) |
| `include_patterns` | array | no | URL patterns to include (regex). Empty = all. |
| `exclude_patterns` | array | no | URL patterns to exclude (regex). E.g. `["/tag/", "/author/", "/page/\\d+"]` |

**Returns:**
```json
{
  "success": true,
  "source": "crawl",
  "site_url": "https://example.com",
  "total_discovered": 34,
  "pages": [
    {
      "url": "https://example.com/",
      "title": "Home - Example",
      "depth": 0,
      "internal_links": 12,
      "suggested_slug": "index",
      "suggested_type": "page"
    }
  ],
  "tree": {
    "/": {
      "children": ["/about", "/services", "/contact", "/blog"],
      "title": "Home"
    },
    "/services": {
      "children": ["/services/design", "/services/development"],
      "title": "Services"
    }
  },
  "session_id": "imp_ghi789"
}
```

**Annotations:** readOnlyHint=true, destructiveHint=false, idempotentHint=false

---

#### `klytos_import_analyze_style`

Analyze the visual style of a website and return a Klytos theme mapping.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `url` | string | yes | URL of a representative page (usually homepage) |
| `css_content` | string | no | Raw CSS content (if the AI already fetched it). Skips server-side fetch. |
| `html_content` | string | no | Raw HTML content (if the AI already fetched it). Used for inline style analysis. |

**Returns:**
```json
{
  "success": true,
  "detected_colors": {
    "primary": "#2563eb",
    "secondary": "#1e40af",
    "accent": "#f59e0b",
    "background": "#ffffff",
    "surface": "#f8fafc",
    "text": "#1e293b",
    "text_muted": "#64748b",
    "border": "#e2e8f0"
  },
  "detected_fonts": {
    "heading": "Inter",
    "body": "Inter",
    "heading_weight": "700",
    "body_weight": "400",
    "base_size": "16px",
    "google_fonts_url": "https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap"
  },
  "detected_layout": {
    "max_width": "1200px",
    "header_style": "sticky",
    "border_radius": "8px",
    "spacing_unit": "1rem"
  },
  "extra_css": "/* Additional CSS rules that don't map to theme variables */\n.hero-section { background: linear-gradient(135deg, #2563eb, #1e40af); }",
  "confidence": {
    "colors": 0.92,
    "fonts": 0.85,
    "layout": 0.78
  }
}
```

**Annotations:** readOnlyHint=true, destructiveHint=false, idempotentHint=true

---

### 5.2 FETCH PHASE

#### `klytos_import_fetch_page`

Download a single page and extract its main content, stripping navigation, headers, footers, sidebars, and scripts.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `url` | string | yes* | URL of the page to fetch (*required if html_content not provided) |
| `html_content` | string | no | Raw HTML (if the AI already fetched the page). Skips server-side fetch. |
| `extract_media` | boolean | no | Also extract image/video URLs from content (default: true) |

**Returns:**
```json
{
  "success": true,
  "url": "https://example.com/about",
  "title": "About Us",
  "meta_description": "Learn about our company and mission.",
  "og_image": "https://example.com/images/about-og.jpg",
  "main_content_html": "<h1>About Us</h1><p>We are a company...</p><img src=\"/images/team.jpg\" alt=\"Team\">",
  "media": [
    {
      "src": "https://example.com/images/team.jpg",
      "alt": "Our team",
      "type": "image",
      "context": "content"
    },
    {
      "src": "https://example.com/images/about-og.jpg",
      "alt": "",
      "type": "image",
      "context": "og_image"
    }
  ],
  "detected_lang": "en",
  "word_count": 340,
  "has_forms": false,
  "has_video": false
}
```

The `html_content` parameter is key. When the AI agent has browser access (Claude in Chrome, etc.), it can fetch the fully rendered page (including JS-generated content) and pass it here. The tool then only does extraction, not fetching. When `html_content` is empty, the tool fetches via PHP cURL (works for static/SSR sites).

**Annotations:** readOnlyHint=true, destructiveHint=false, idempotentHint=true

---

#### `klytos_import_fetch_wp_page`

Extract a single page/post from an already-analyzed WordPress XML export.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `session_id` | string | yes | Import session ID from the analysis step |
| `slug` | string | yes | Slug of the page/post to extract from the XML |

**Returns:**
```json
{
  "success": true,
  "title": "About Us",
  "slug": "about",
  "content_html": "<!-- wp:heading --><h2>Our Story</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Founded in 2010...</p><!-- /wp:paragraph -->",
  "status": "publish",
  "date": "2024-06-15T10:30:00",
  "author": "admin",
  "categories": [],
  "tags": [],
  "featured_image": "https://example.com/wp-content/uploads/hero.jpg",
  "meta_description": "",
  "template": "",
  "post_type": "page",
  "menu_order": 2,
  "parent_slug": "",
  "media": [
    {"src": "https://example.com/wp-content/uploads/hero.jpg", "alt": "Hero image"}
  ],
  "has_shortcodes": false,
  "has_gutenberg_blocks": true,
  "shortcodes_found": []
}
```

When `has_gutenberg_blocks` is true, the `content_html` is already in Gutenberg format and can be passed almost directly to `klytos_create_page`. When `has_shortcodes` is true, the AI should use `klytos_import_convert_content` to transform them.

**Annotations:** readOnlyHint=true, destructiveHint=false, idempotentHint=true

---

### 5.3 CONVERSION PHASE

#### `klytos_import_convert_content`

Convert generic HTML content into Gutenberg block markup compatible with Klytos.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `html` | string | yes | Raw HTML content to convert |
| `source_type` | string | no | Hint about source: "wordpress", "html", "markdown" (default: "html") |
| `preserve_classes` | boolean | no | Keep original CSS classes in block markup (default: false) |

**Returns:**
```json
{
  "success": true,
  "gutenberg_html": "<!-- wp:heading {\"level\":2} -->\n<h2>Our Story</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Founded in 2010, we started...</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:image {\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"/assets/images/team.jpg\" alt=\"Team\"/></figure>\n<!-- /wp:image -->",
  "blocks_count": 12,
  "unsupported_elements": [],
  "warnings": ["Table found at line 45 — converted to wp:table block, verify formatting."]
}
```

**Conversion rules:**

| HTML Element | Gutenberg Block |
|-------------|-----------------|
| `<h1>` to `<h6>` | `<!-- wp:heading {"level":N} -->` |
| `<p>` | `<!-- wp:paragraph -->` |
| `<img>` | `<!-- wp:image -->` |
| `<ul>`, `<ol>` | `<!-- wp:list -->` / `<!-- wp:list {"ordered":true} -->` |
| `<blockquote>` | `<!-- wp:quote -->` |
| `<table>` | `<!-- wp:table -->` |
| `<pre>`, `<code>` | `<!-- wp:code -->` |
| `<hr>` | `<!-- wp:separator -->` |
| `<figure>` + `<figcaption>` | `<!-- wp:image -->` with caption |
| `<video>` | `<!-- wp:video -->` |
| `<audio>` | `<!-- wp:audio -->` |
| `<iframe>` (YouTube/Vimeo) | `<!-- wp:embed -->` |
| `<div>` with content | `<!-- wp:group -->` |
| WordPress shortcodes | Best-effort conversion (see below) |

**Shortcode handling:** Common WP shortcodes are converted:
- `[gallery]` to `<!-- wp:gallery -->`
- `[caption]` to `<!-- wp:image -->` with caption
- `[video]` to `<!-- wp:video -->`
- `[audio]` to `<!-- wp:audio -->`
- `[embed]` to `<!-- wp:embed -->`
- `[columns]` / `[column]` to `<!-- wp:columns -->` / `<!-- wp:column -->`
- Unknown shortcodes are wrapped in `<!-- wp:html -->` as raw HTML with a warning

**Annotations:** readOnlyHint=true, destructiveHint=false, idempotentHint=true

---

### 5.4 EXECUTION PHASE

#### `klytos_import_download_media`

Download external media files and register them as Klytos assets.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `session_id` | string | yes | Import session ID |
| `media_list` | array | yes | Array of `{src, alt, filename}` objects |
| `base_url` | string | no | Base URL to resolve relative paths |

**Returns:**
```json
{
  "success": true,
  "downloaded": 12,
  "failed": 1,
  "results": [
    {
      "original_src": "https://example.com/images/team.jpg",
      "local_path": "assets/images/team.jpg",
      "klytos_url": "/assets/images/team.jpg",
      "status": "ok"
    },
    {
      "original_src": "https://example.com/images/broken.png",
      "local_path": null,
      "klytos_url": null,
      "status": "failed",
      "error": "HTTP 404"
    }
  ],
  "url_map": {
    "https://example.com/images/team.jpg": "/assets/images/team.jpg",
    "https://example.com/images/logo.svg": "/assets/images/logo.svg"
  }
}
```

The `url_map` is the key output: the AI uses it to search-and-replace URLs in `content_html` before calling `klytos_create_page`.

**Annotations:** readOnlyHint=false, destructiveHint=false, idempotentHint=false

---

#### `klytos_import_execute_batch`

Import multiple pages in a single call. Creates pages via the core PageManager, tracks progress in the import session.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `session_id` | string | yes | Import session ID |
| `pages` | array | yes | Array of page objects (same structure as `klytos_create_page` params) |
| `url_map` | object | no | URL replacement map from media download step |

Each page object in the `pages` array has the same parameters as `klytos_create_page`: `slug`, `title`, `content_html`, `meta_description`, `template`, `status`, `lang`, `custom_css`, `og_image`, `post_type`, `order`.

If `url_map` is provided, all URLs in `content_html` that match a key in the map are replaced with the local Klytos path before page creation.

**Returns:**
```json
{
  "success": true,
  "session_id": "imp_abc123",
  "total": 15,
  "created": 14,
  "failed": 1,
  "results": [
    {"slug": "about", "status": "created", "title": "About Us"},
    {"slug": "services", "status": "created", "title": "Services"},
    {"slug": "broken-page", "status": "failed", "error": "content_html is empty"}
  ]
}
```

All pages are created with `status: "draft"` regardless of the original status, unless the AI explicitly sets `status: "published"`.

**Annotations:** readOnlyHint=false, destructiveHint=true, idempotentHint=false

---

#### `klytos_import_session_status`

Get the current status of an import session: progress, errors, pending pages.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `session_id` | string | yes | Import session ID |

**Returns:**
```json
{
  "success": true,
  "session_id": "imp_abc123",
  "source": "sitemap",
  "source_url": "https://example.com",
  "created_at": "2026-04-01T14:30:00Z",
  "status": "in_progress",
  "total_pages": 34,
  "imported": 20,
  "pending": 13,
  "failed": 1,
  "pages": [
    {"slug": "about", "status": "imported", "title": "About Us"},
    {"slug": "contact", "status": "pending", "title": "Contact"},
    {"slug": "broken", "status": "failed", "error": "Empty content"}
  ]
}
```

**Annotations:** readOnlyHint=true, destructiveHint=false, idempotentHint=true

---

## 6. Import Sources

### 6.1 WordPress XML (WXR)

The WXR format is an extended RSS feed that WordPress generates via Tools > Export. It contains:

- Posts and pages with full Gutenberg/classic content
- Categories and tags (taxonomy terms)
- Media attachments with URLs
- Navigation menus with hierarchy
- Authors
- Custom post types and their entries
- Post meta (custom fields)

The parser (`WPXMLParser.php`) reads the XML with `XMLReader` for memory efficiency (exports can be hundreds of MB) and extracts all entities into indexed arrays.

**Key conversion details for WordPress content:**

- Gutenberg blocks (`<!-- wp:xxx -->`) transfer almost directly. The only changes needed are media URL remapping.
- Classic editor content (no block comments) is treated as raw HTML and passed through `klytos_import_convert_content`.
- Shortcodes are detected and flagged. Common ones are auto-converted; unknown ones are preserved as raw HTML blocks.
- WordPress `[caption]` shortcode wraps images — these are converted to `<!-- wp:image -->` blocks with captions.
- Internal links (`href="/some-page"` or `href="https://oldsite.com/some-page"`) are rewritten to the new Klytos slug structure.

### 6.2 Sitemap

Standard sitemap.xml parsing following the Sitemaps Protocol (sitemaps.org). Supports:

- Standard `<urlset>` sitemaps
- Sitemap index files (`<sitemapindex>`) that reference multiple sitemaps
- Namespace-based image/video/news sitemaps (extracts metadata when present)
- `lastmod`, `changefreq`, and `priority` attributes

The parser reads the sitemap, builds the URL list, and applies heuristics to classify each URL:

| URL Pattern | Classification |
|-------------|---------------|
| `/` or `/index` | Homepage |
| `/about`, `/contact`, `/services`, etc. | Page |
| `/blog/YYYY/MM/title` or `/YYYY/title` | Blog post |
| `/category/X`, `/tag/X` | Archive (skip by default) |
| `/author/X` | Author page (skip by default) |
| `/page/N` | Pagination (skip always) |

### 6.3 AI-Driven Crawling

For sites without a sitemap, the crawler starts from a given URL and:

1. Fetches the page HTML (via cURL or AI browser).
2. Extracts all internal links (same domain, no fragments, no query strings by default).
3. Normalizes URLs and deduplicates.
4. Follows links up to `max_depth` levels.
5. Respects `robots.txt` directives (Disallow rules are honored).
6. Rate-limits requests: 1 request per second by default (configurable).
7. Returns a hierarchical tree of discovered pages.

The AI then selects which pages to import based on the tree structure and user intent.

---

## 7. Content Conversion Pipeline

The full pipeline from raw HTML to Klytos page:

```
External HTML
     |
     v
[ContentExtractor]     Removes nav, header, footer, sidebar, scripts, ads
     |                 Identifies main content area (<main>, <article>, role="main")
     |                 Extracts <title>, meta description, OG tags
     v
Clean HTML
     |
     v
[ContentMapper]        Converts HTML elements to Gutenberg block markup
     |                 Handles tables, lists, images, embeds, code blocks
     |                 Preserves semantic structure (headings hierarchy)
     v
Gutenberg HTML
     |
     v
[URL Rewriting]        Replaces external media URLs with local Klytos paths
     |                 Rewrites internal links to match new slug structure
     v
Final content_html     Ready for klytos_create_page
```

### 7.1 Content Extraction Heuristics

The `ContentExtractor` identifies the main content area using a scoring algorithm:

1. Elements with role="main", `<main>`, or `<article>` get highest priority.
2. Elements with id/class containing "content", "post", "entry", "article" score high.
3. Elements with id/class containing "nav", "sidebar", "footer", "header", "menu", "widget", "ad" are excluded.
4. Text density scoring: blocks with the highest ratio of text to HTML tags are preferred.
5. If no clear main content is found, the largest `<div>` by text content is used.

### 7.2 Internal Link Rewriting

When importing a full site, internal links must point to the new Klytos slugs. The AI builds a URL-to-slug map during the analysis phase:

```
https://example.com/about-us/      -->  /about-us/
https://example.com/services/web/   -->  /services/web/
https://example.com/blog/my-post/   -->  /blog/my-post/
```

The `klytos_import_execute_batch` tool applies this map automatically when the `url_map` parameter includes both media and page URL mappings.

---

## 8. Style Replication System

### 8.1 Analysis

The `StyleAnalyzer` processes CSS (external stylesheets + inline styles) and extracts:

**Colors:** Scans all CSS rules for `color`, `background-color`, `border-color`, `fill`, and gradient definitions. Groups colors by frequency and usage context. The most frequent background becomes `background`, the most frequent text color becomes `text`, the most prominent accent color (buttons, links, CTAs) becomes `primary`.

**Fonts:** Identifies `font-family` declarations in body and heading selectors. Detects Google Fonts URLs from `<link>` tags. Maps weights and sizes.

**Layout:** Reads `max-width` on container elements, detects fixed/sticky headers via `position` rules, identifies border-radius patterns, and spacing units from padding/margin values.

### 8.2 Mapping to Klytos Theme

The analysis output maps directly to `klytos_set_theme` parameters:

| Detected | Klytos Theme Variable |
|----------|----------------------|
| Most used background | `colors.background` |
| Most used surface/card bg | `colors.surface` |
| Primary brand color (links, buttons) | `colors.primary` |
| Secondary brand color | `colors.secondary` |
| CTA/accent color | `colors.accent` |
| Body text color | `colors.text` |
| Lighter text (captions, meta) | `colors.text_muted` |
| Border color | `colors.border` |
| Heading font-family | `fonts.heading` |
| Body font-family | `fonts.body` |
| Container max-width | `layout.max_width` |
| Header behavior | `layout.header_style` |

### 8.3 Extra CSS

CSS rules that cannot be mapped to theme variables (gradients, complex animations, specific component styles) are collected into an `extra_css` string. The AI can:

- Apply it globally via `klytos_set_theme({ custom_css: extra_css })`
- Apply it per-page via `klytos_create_page({ custom_css: extra_css })` for page-specific styles
- Use it as reference to create custom Klytos blocks that replicate specific visual patterns

### 8.4 Confidence Scores

Each detection category returns a confidence score (0-1):

- **> 0.8:** High confidence, apply automatically.
- **0.5-0.8:** Medium confidence, apply but inform the user.
- **< 0.5:** Low confidence, show the user what was detected and let them decide.

---

## 9. Media Handling

### 9.1 Discovery

Media is discovered during the fetch phase from multiple sources:

- `<img src="...">` in content
- `<video>` and `<source>` tags
- CSS `background-image` URLs (in inline styles and extracted CSS)
- Open Graph image meta tags
- WordPress XML attachment records

### 9.2 Download and Registration

The `MediaDownloader` class:

1. Validates the URL (same domain or known CDN patterns).
2. Downloads the file with cURL (follows redirects, respects timeouts).
3. Validates the file type (only allows images, videos, fonts, SVG, PDF).
4. Generates a safe filename: `original-name-{hash8}.ext`
5. Saves to `public/assets/images/` (or appropriate subdirectory).
6. Calls `klytos_upload_asset` internally to register in the asset system.
7. Returns the URL mapping for content rewriting.

### 9.3 Size and Limit Considerations

- Maximum file size per asset: 10MB (configurable).
- Maximum total media per import session: 500MB (configurable).
- Concurrent downloads: 3 (to avoid overwhelming the source server).
- Timeout per download: 30 seconds.
- Duplicate detection: files with identical content hash are not re-downloaded.

---

## 10. Import Session Management

### 10.1 Session Structure

Each import operation creates a persistent session stored in the `import_sessions` collection:

```json
{
  "id": "imp_abc123",
  "source": "sitemap|wordpress|crawl",
  "source_url": "https://example.com",
  "source_file": "wp-export.xml",
  "status": "analyzing|ready|in_progress|completed|failed|cancelled",
  "created_at": "2026-04-01T14:30:00Z",
  "updated_at": "2026-04-01T15:00:00Z",
  "config": {
    "import_pages": true,
    "import_posts": true,
    "import_media": true,
    "import_menus": true,
    "import_theme": true,
    "target_language": "es",
    "post_type_mapping": {
      "post": "post",
      "portfolio": "portfolio"
    }
  },
  "analysis": { },
  "progress": {
    "total": 34,
    "imported": 20,
    "pending": 13,
    "failed": 1
  },
  "url_map": { },
  "media_map": { },
  "pages": [
    {
      "original_url": "https://example.com/about",
      "slug": "about",
      "title": "About Us",
      "status": "imported|pending|failed|skipped",
      "error": null
    }
  ],
  "errors": []
}
```

### 10.2 Session Lifecycle

```
analyze_* tool called  -->  Session created (status: analyzing)
                            |
Analysis complete      -->  status: ready
                            |
AI reviews, user       -->  AI calls execute_batch or fetch+create per page
confirms (if needed)        status: in_progress
                            |
All pages processed    -->  status: completed
                            |
(or) Error/interrupt   -->  status: failed (can be resumed)
```

### 10.3 Resumability

When a session has status "in_progress" or "failed", the AI can:

1. Call `klytos_import_session_status` to see what is pending.
2. Continue importing only the pending pages.
3. Retry failed pages after fixing the issue.

---

## 11. Admin UI

The plugin adds an "Site Importer" page in the admin sidebar.

### 11.1 Main View

Three tabs:

**Tab 1: Import from URL**
- Text input for site URL
- Radio buttons: "Detect sitemap automatically" / "Enter sitemap URL" / "Crawl from homepage"
- Options: max pages, include/exclude patterns
- "Analyze Site" button

**Tab 2: Import from WordPress**
- File upload for .xml files (drag & drop or browse)
- "Analyze Export" button

**Tab 3: Import Sessions**
- Table of past/active sessions with: source, date, status, progress bar, actions (resume/delete)

### 11.2 Analysis Results View

After analysis, shows:

- Summary card: total pages, posts, media found
- Filterable table of discovered pages with checkboxes (select which to import)
- Theme preview: detected colors and fonts as swatches
- "Start Import" button (calls the AI to begin the process)
- "Let AI decide" checkbox (default on): the AI imports everything it considers relevant without further confirmation

### 11.3 Progress View

During import:

- Real-time progress bar (pages imported / total)
- Live log of actions (creating page X, downloading image Y)
- Error list (if any)
- "Pause" and "Cancel" buttons

---

## 12. AI Assistant SKILL

This section defines the behavior instructions for any AI assistant that uses the Klytos Importer tools. It should be included in the system prompt or tool documentation when the importer plugin is active.

---

### KLYTOS IMPORTER — AI ASSISTANT INSTRUCTIONS

You have access to the Klytos Importer tools for migrating content from external websites into this Klytos installation. Below are your instructions for using these tools effectively.

#### GENERAL PRINCIPLES

1. **Be autonomous.** Once the user tells you what to import, do the entire migration yourself. Do not stop after every page to ask for confirmation. Import everything, then report results.

2. **Ask only when genuinely ambiguous.** Limit yourself to ONE question at the start if needed. Good reasons to ask:
   - The site has both a blog (87 posts) and pages (12 pages) and you are not sure if the user wants all blog posts imported.
   - The site has multiple languages (see principle 6 below).
   - The site uses a custom post type that could map to different Klytos structures.

   Bad reasons to ask (just decide yourself):
   - Which template to use for a page (analyze the content and pick the best one).
   - Whether to import media (yes, always).
   - Whether to apply the detected theme (yes, always).
   - What slug to use (use the original, cleaned up).

3. **Test import first, then bulk publish.** Follow this pattern:
   - First, import only 2-3 representative pages as **drafts** (e.g. the homepage and one inner page).
   - Tell the user: "I've imported 2 test pages as drafts so you can review them. Check them and tell me if the result looks correct."
   - Wait for the user to confirm.
   - If confirmed: import the remaining pages with `status: "published"` directly.
   - If the user wants changes: adjust (template, style, content mapping) and repeat the test with another 2-3 pages.
   - This avoids having to manually publish 100+ pages, while still giving the user control over quality.

4. **Import the style first, then the content.** Apply the theme before creating pages so that previews look correct.

5. **Build the site at the end.** Call `klytos_build_site` once after all pages are created, not after each page.

6. **Multi-language handling.** Before importing, check the Klytos site's language configuration via `klytos_get_site_config` (`default_language` and `languages` array). Then apply the correct strategy:

   **Case A — Source site is monolingual:**
   - Import the content in its original language, setting `lang` on each page to match the source language.
   - Ask the user: "The source site is in [detected language]. Would you like me to translate it to [other configured languages]?"
   - If yes, after importing the original pages, use the translation tools to translate each page to the additional languages configured in Klytos, and set up `hreflang_refs` between versions.

   **Case B — Source site is already multilingual:**
   - Detect the available languages (from hreflang tags, URL patterns like `/en/`, `/es/`, language switchers, or sitemap structure).
   - Ask: "This site has content in [X, Y, Z]. Which languages do you want to import?"
   - Import each language version with the correct `lang` field, and link them together via `hreflang_refs`.
   - Only translate missing language versions if the user explicitly requests it.

   **Language detection hints:**
   - `<html lang="...">` attribute on fetched pages
   - `hreflang` link tags in the HTML `<head>`
   - URL patterns: `/en/about`, `/es/about` or `en.example.com`, `es.example.com`
   - WordPress XML exports include language metadata from WPML or Polylang plugins
   - The `klytos_import_fetch_page` tool returns `detected_lang` for each page

#### WORKFLOW: IMPORT FROM URL

When the user says something like "import this site", "migrate example.com to Klytos", "copy the content from this URL":

**Step 1 — Discover the site structure.**

Try the sitemap first:
```
klytos_import_analyze_sitemap({ sitemap_url: "https://example.com/sitemap.xml" })
```

If the sitemap does not exist or returns an error, fall back to crawling:
```
klytos_import_discover_site({ start_url: "https://example.com", max_depth: 3 })
```

**Step 2 — Analyze the style.**
```
klytos_import_analyze_style({ url: "https://example.com" })
```

If you have browser access and can fetch the page yourself, pass the HTML and CSS directly:
```
klytos_import_analyze_style({ url: "https://example.com", html_content: "...", css_content: "..." })
```

**Step 3 — Apply the theme.**

Using the style analysis results, call the Klytos core tool:
```
klytos_set_theme({
  colors: { ...detected_colors },
  fonts: { ...detected_fonts },
  layout: { ...detected_layout },
  custom_css: extra_css
})
```

**Step 4 — Decide what to import.**

Review the discovered pages. Apply these rules:
- Skip pagination pages (`/page/2`, `/page/3`).
- Skip tag and category archives (`/tag/X`, `/category/X`).
- Skip author pages (`/author/X`).
- Skip search results pages.
- Skip login/register/cart/checkout pages (e-commerce artifacts).
- Include all content pages, blog posts, and custom post types.
- If the site has more than 50 blog posts, ask the user if they want all posts or just pages. Otherwise, import everything.

**Step 5 — Fetch and convert each page.**

For each page to import:
```
result = klytos_import_fetch_page({ url: page_url })
converted = klytos_import_convert_content({ html: result.main_content_html })
```

Collect all media URLs from the fetch results.

If you have browser access, you can be smarter: fetch the page yourself, and pass the fully rendered HTML to skip PHP-side fetching:
```
result = klytos_import_fetch_page({ html_content: "...rendered HTML from browser..." })
```

**Step 6 — Download media.**
```
klytos_import_download_media({
  session_id: "...",
  media_list: [ ...all collected media... ],
  base_url: "https://example.com"
})
```

**Step 7 — Import all pages.**
```
klytos_import_execute_batch({
  session_id: "...",
  pages: [
    {
      slug: "about",
      title: "About Us",
      content_html: converted.gutenberg_html,
      meta_description: result.meta_description,
      template: "default",
      status: "draft",
      og_image: result.og_image
    },
    ...
  ],
  url_map: media_download_result.url_map
})
```

**Step 8 — Set up the menu.**

Based on the original site navigation (detected during crawling or from the site header):
```
klytos_set_menu({
  items: [
    { label: "Home", url: "/", order: 1 },
    { label: "About", url: "/about/", order: 2 },
    { label: "Services", url: "/services/", children: [
      { label: "Design", url: "/services/design/" },
      { label: "Development", url: "/services/development/" }
    ], order: 3 },
    { label: "Contact", url: "/contact/", order: 4 }
  ]
})
```

**Step 9 — Build the site.**
```
klytos_build_site()
```

**Step 10 — Report to the user.**

Give a concise summary:
- How many pages were imported.
- Whether the theme was applied and with what confidence.
- Any pages that failed and why.
- Remind them that all pages are in draft status.
- Suggest they review the imported pages and publish when ready.

#### WORKFLOW: IMPORT FROM WORDPRESS XML

When the user uploads a .xml file or says "import my WordPress export":

**Step 1 — Analyze the export.**
```
klytos_import_analyze_wp_xml({ file_path: "path/to/export.xml" })
```

**Step 2 — Decide what to import.**

Review the summary. If the export contains both pages and posts:
- If total items < 50: import everything without asking.
- If total items >= 50: ask the user one question: "The export contains X pages and Y posts. Do you want me to import everything, or just the pages?"

**Step 3 — If the export has menus, extract them for later.**

Store the menu structure from the XML analysis to apply in Step 7.

**Step 4 — Analyze the style (optional but recommended).**

If the original site is still accessible:
```
klytos_import_analyze_style({ url: analysis.site_url })
```

If not accessible, skip theme import and inform the user they can configure the theme manually.

**Step 5 — Apply the theme** (if analyzed).

Same as URL import Step 3.

**Step 6 — Fetch and convert each page.**

For WordPress with Gutenberg content, the content comes pre-formatted:
```
page_data = klytos_import_fetch_wp_page({ session_id: "...", slug: "about" })
```

If `page_data.has_gutenberg_blocks` is true, use `content_html` as-is (just remap media URLs).
If `page_data.has_shortcodes` is true or `has_gutenberg_blocks` is false:
```
converted = klytos_import_convert_content({ html: page_data.content_html, source_type: "wordpress" })
```

**Step 7 — Download media, import pages, set menu, build site.**

Same as URL import Steps 6-9.

**Step 8 — Report.**

Same as URL import Step 10.

#### WORKFLOW: RESUMING AN INTERRUPTED IMPORT

If the user says "continue the import", "resume", or "what happened with the import":

```
status = klytos_import_session_status({ session_id: "..." })
```

If there are pending pages, continue from where it left off. If there are failed pages, try to re-import them and report persistent failures.

#### TEMPLATE ASSIGNMENT RULES

When creating pages, choose the template based on content analysis:

| Content Pattern | Template |
|----------------|----------|
| Page with hero section, features, testimonials | `landing` |
| Blog entry with date, author, categories | `blog-post` |
| Any other content page | `default` |
| Page with only an embed or minimal content | `blank` |

If the Klytos installation has custom page templates (check with `klytos_list_page_templates`), prefer those over core templates when they match the content pattern.

#### HANDLING EDGE CASES

**Site requires JavaScript rendering:** If PHP cURL returns minimal content (< 100 characters of text in the main content area), the site likely uses client-side rendering. Tell the user: "This site uses JavaScript rendering. I need to use browser access to fetch the pages correctly." If you have browser access, use it. If not, inform the user that this site cannot be imported without browser capabilities.

**Site blocks scraping:** If fetch requests return 403/429 errors, inform the user. Suggest they provide a WordPress XML export instead, or try again later.

**Very large site (> 200 pages):** Process in batches of 20 pages. After each batch, update the session status. This prevents timeouts.

**Duplicate slugs:** If two source pages would result in the same Klytos slug, append a numeric suffix: `about`, `about-2`.

**Non-UTF8 content:** The content mapper normalizes all text to UTF-8. Characters that cannot be converted are replaced with their closest ASCII equivalent.

**Pages with forms:** Forms cannot be migrated as functional elements. Convert them to static content (the form fields as text) and add a warning in the import report: "Page X contained a form that was converted to static content. Consider adding a Klytos Forms block."

#### ERROR RECOVERY

If a tool call fails:
1. Check the error message.
2. If it is a network error (timeout, DNS, connection refused), retry once after a brief pause.
3. If it is a validation error (missing required field, invalid format), fix the input and retry.
4. If it is a persistent error (site unreachable, file corrupt), skip the item, log it, and continue with the rest.
5. Never let a single page failure stop the entire import.

#### WHAT NOT TO DO

- Do not create pages one by one asking for confirmation after each one.
- Do not ask the user which template to use for each page.
- Do not ask the user to manually provide CSS or style information.
- Do not import the same page twice in the same session.
- Do not call `klytos_build_site` after every page creation — only once at the end.
- Do not import pages from other domains found in the links (external links are not part of the import).
- Do not import RSS feeds, JSON API endpoints, or non-HTML resources.
- Do not store or display credentials, cookies, or authentication tokens from the source site.

---

## 13. Security Considerations

### 13.1 Input Validation

- All URLs are validated: must be HTTP/HTTPS, must not be local/private IPs (prevent SSRF).
- XML files are parsed with entity expansion disabled (`libxml_disable_entity_loader`) to prevent XXE attacks.
- HTML content is sanitized with a strict allowlist before storage.
- File uploads are validated by MIME type and extension, not just extension.
- Base64 data is validated before asset registration.

### 13.2 Network Security

- PHP fetcher follows a maximum of 5 redirects.
- Connection timeout: 10 seconds. Transfer timeout: 30 seconds.
- User-Agent identifies as "KlytosImporter/1.0" (not disguised as a browser).
- Respects `robots.txt` Disallow directives.
- Rate limiting: minimum 1 second between requests to the same domain.

### 13.3 Storage Security

- Import sessions are stored encrypted (same AES-256-GCM as all Klytos data).
- Source site credentials are never stored.
- Downloaded media is scanned for PHP code injection (no `.php` extensions, no `<?php` in file content).
- SVG files are sanitized to remove script tags and event handlers.

### 13.4 Permissions

- Only users with `manage_pages` + `manage_assets` permissions can use importer tools.
- The plugin respects Klytos role-based access control.

---

## 14. Implementation Phases

### Phase 1: Foundation (Core Plugin + WordPress XML)

- Plugin scaffold with install/deactivate/uninstall lifecycle.
- Import session management (create, track, resume).
- `WPXMLParser` — full WXR parsing.
- `ContentMapper` — HTML to Gutenberg block conversion.
- `klytos_import_analyze_wp_xml` tool.
- `klytos_import_fetch_wp_page` tool.
- `klytos_import_convert_content` tool.
- `klytos_import_execute_batch` tool.
- `klytos_import_session_status` tool.
- Admin UI: WordPress XML upload tab + sessions tab.
- AI SKILL integration for WordPress workflow.

### Phase 2: Sitemap + URL Import

- `SitemapParser` — sitemap.xml/index parsing.
- `PageFetcher` — PHP cURL with content extraction.
- `ContentExtractor` — main content identification algorithm.
- `klytos_import_analyze_sitemap` tool.
- `klytos_import_fetch_page` tool.
- `klytos_import_discover_site` tool (crawling).
- Admin UI: URL import tab.
- AI SKILL integration for URL workflow.

### Phase 3: Style Replication

- `StyleAnalyzer` — CSS parsing and theme mapping.
- `klytos_import_analyze_style` tool.
- Confidence scoring system.
- AI SKILL integration for style workflow.

### Phase 4: Media + Polish

- `MediaDownloader` — bulk media download with deduplication.
- `klytos_import_download_media` tool.
- URL rewriting in content.
- Admin UI: progress view with real-time updates.
- Error recovery and resume improvements.
- Security hardening (SSRF prevention, SVG sanitization, etc.).

---

## Appendix A: Tool Registration Code Pattern

```php
// In klytos-importer.php entry point:

klytos_add_filter('mcp.tools_list', function (array $tools): array {
    $importer = new \Klytos\Plugin\KlytosImporter\ImporterTools();
    return array_merge($tools, $importer->getToolDefinitions());
});

klytos_add_filter('mcp.handle_tool', function ($result, string $name, array $params) {
    if (str_starts_with($name, 'klytos_import_')) {
        $importer = new \Klytos\Plugin\KlytosImporter\ImporterTools();
        return $importer->handle($name, $params);
    }
    return $result;
}, 10);
```

## Appendix B: Content Extraction Example

**Input (external HTML):**
```html
<!DOCTYPE html>
<html>
<head><title>About Us - Example Corp</title></head>
<body>
  <nav class="main-nav">
    <a href="/">Home</a>
    <a href="/about">About</a>
  </nav>
  <main>
    <h1>About Us</h1>
    <p>We are a company dedicated to excellence.</p>
    <img src="/images/team.jpg" alt="Our team">
    <h2>Our Mission</h2>
    <p>To deliver the best products.</p>
  </main>
  <footer>
    <p>Copyright 2025 Example Corp</p>
  </footer>
</body>
</html>
```

**Output after ContentExtractor:**
```html
<h1>About Us</h1>
<p>We are a company dedicated to excellence.</p>
<img src="/images/team.jpg" alt="Our team">
<h2>Our Mission</h2>
<p>To deliver the best products.</p>
```

**Output after ContentMapper:**
```html
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">About Us</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>We are a company dedicated to excellence.</p>
<!-- /wp:paragraph -->

<!-- wp:image -->
<figure class="wp-block-image"><img src="/assets/images/team.jpg" alt="Our team"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Our Mission</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>To deliver the best products.</p>
<!-- /wp:paragraph -->
```

## Appendix C: WordPress Shortcode Conversion Table

| WordPress Shortcode | Klytos Block | Notes |
|--------------------|-------------|-------|
| `[gallery ids="1,2,3"]` | `<!-- wp:gallery -->` | Images fetched from XML media |
| `[caption]...[/caption]` | `<!-- wp:image -->` with figcaption | |
| `[video src="..."]` | `<!-- wp:video -->` | |
| `[audio src="..."]` | `<!-- wp:audio -->` | |
| `[embed]URL[/embed]` | `<!-- wp:embed -->` | Detects provider |
| `[columns][column]...[/column][/columns]` | `<!-- wp:columns -->` | |
| `[contact-form-7 ...]` | `<!-- wp:html -->` + warning | Cannot be functional |
| `[woocommerce_cart]` | Skipped | E-commerce artifact |
| `[woocommerce_checkout]` | Skipped | E-commerce artifact |
| Unknown `[shortcode]` | `<!-- wp:html -->` + warning | Preserved as raw HTML |
