---
name: klytos-importer
description: Guide for the Klytos Importer plugin — AI-powered content migration from any website (WordPress XML, sitemap, crawling) with MCP tools for analysis, fetching, conversion, and batch import. Use when working with importing content, migrating websites, WordPress exports, sitemap imports, content crawling, or the importer MCP tools.
---

# Klytos Importer Plugin

## Overview

Klytos Importer is a **plugin** (not core) enabling AI-assisted migration of any website into Klytos. It provides MCP tools that an AI agent can use to analyze, fetch, convert, and import content from external websites.

**Plugin location:** `installer/plugins/klytos-importer/`
**Architecture spec:** `docs/IMPORTER-ARCHITECTURE.md`

## Plugin Structure

```
plugins/klytos-importer/
├── klytos-importer.php          (Entry point + MCP tool registration)
├── klytos-plugin.json           (Admin pages + MCP tools manifest)
├── install.php                  (First activation — create import_sessions)
├── deactivate.php               (Mark in-progress sessions as cancelled)
├── uninstall.php                (Remove all import data)
├── src/
│   ├── ImportSession.php        (Session persistence and state management)
│   ├── ImportValidator.php      (URL/XML/HTML validation + SSRF prevention)
│   ├── WPXMLParser.php          (WordPress WXR format parser via XMLReader)
│   ├── ContentMapper.php        (HTML → Gutenberg block conversion)
│   ├── SitemapParser.php        (Sitemap XML/index parser)
│   ├── PageFetcher.php          (cURL fetcher + BFS site crawler)
│   ├── ContentExtractor.php     (Main content extraction from full pages)
│   ├── StyleAnalyzer.php        (CSS analysis → Klytos theme mapping)
│   └── MediaDownloader.php      (Bulk media download + security validation)
├── admin/
│   ├── import.php               (Upload XML, enter URL, view sessions)
│   └── assets/
│       ├── import.css           (Admin styles)
│       └── import.js            (Drag-and-drop, tabs, progress)
└── lang/{en,es}.json            (Translations)
```

## Three Import Methods (Priority Order)

1. **WordPress XML (WXR)** — Direct parsing of WP's export file. Best fidelity, preserves Gutenberg blocks.
2. **Sitemap-guided** — Fetch sitemap.xml, classify URLs, crawl each page. Works with any site that has a sitemap.
3. **AI-driven crawl** — BFS from homepage, discover pages by following internal links. Last resort for sites without sitemap or export.

## MCP Tools (10 tools, all phases) — all gated at `site.configure` (owner/admin)

Since Sprint 2 every `tools/call` passes a default-deny gate, and the plugin declares its own
capabilities through the `mcp.tool_capabilities` filter in `klytos-importer.php`. **All 10 sit at
`site.configure`**: a whole-site migration that fetches arbitrary external URLs and bulk-creates
pages is an operations privilege, the mirror of `klytos_export_site` — not a content-authoring one.
An editor or viewer is refused with a JSON-RPC error object and **HTTP 403**, and does not see these
tools in `tools/list` at all.

Deliberately NOT split into per-phase capabilities (e.g. analysis at `pages.create`): the fetch
phase is the SSRF-relevant one and mixing tiers inside one workflow means the low-tier caller starts
a job it cannot finish. See `docs/reference/mcp-authorization.md`.

### Analysis Phase
- **`klytos_import_analyze_wp_xml`** — Parse WXR file → summary (pages, posts, media, menus, authors). Creates an import session.
  - Params: `file_path` (required)
  - Returns: `session_id`, summary counts, pages_list, posts_list, menus

- **`klytos_import_analyze_sitemap`** — Fetch sitemap.xml (or index), classify URLs, create session.
  - Params: `sitemap_url` (required), `max_urls` (default 500)
  - Returns: `session_id`, total_urls, urls[] with suggested_slug and suggested_type

- **`klytos_import_discover_site`** — BFS crawl from start URL, follow internal links, build site tree.
  - Params: `start_url` (required), `max_depth` (3), `max_pages` (100), `include_patterns`, `exclude_patterns`
  - Returns: `session_id`, pages[], tree{}, total_discovered

- **`klytos_import_analyze_style`** — Extract colors, fonts, layout from a page's CSS. Maps to Klytos theme variables with confidence scores.
  - Params: `url` (required), `css_content`, `html_content` (optional, skip server-side fetch)
  - Returns: detected_colors, detected_fonts, detected_layout, extra_css, confidence

### Fetch Phase
- **`klytos_import_fetch_wp_page`** — Extract single page from analyzed XML by slug.
  - Params: `session_id`, `slug` (both required)
  - Returns: title, slug, content_html, has_gutenberg_blocks, has_shortcodes, media list

- **`klytos_import_fetch_page`** — Download page from URL, extract main content (strips nav/footer/sidebar).
  - Params: `url` or `html_content` (one required), `extract_media` (default true)
  - Returns: title, meta_description, main_content_html, media[], word_count, detected_lang

### Conversion Phase
- **`klytos_import_convert_content`** — HTML → Gutenberg blocks OR clean HTML, depending on the target post type's editor setting.
  - Params: `html` (required), `source_type` (wordpress/html/markdown), `preserve_classes`, `post_type` (auto-detects editor), `output_format` (override: "gutenberg" or "tinymce")
  - Returns: content_html, blocks_count, unsupported_elements, warnings, output_format
  - **IMPORTANT:** Each post type in Klytos has an `editor` field ("gutenberg" or "tinymce"). Always pass `post_type` so the tool outputs the correct format. If `post_type` is "page" and its editor is "tinymce", the output will be clean HTML without block comments.

### Execution Phase
- **`klytos_import_execute_batch`** — Create pages in bulk. Max 20 per call. Tracks progress in session.
  - Params: `session_id`, `pages` array (both required), `url_map` (optional)
  - Each page: slug, title, content_html, meta_description, template, status, lang
  - All pages created as **draft** by default.

- **`klytos_import_download_media`** — Download media files, register as assets, return URL map for rewriting.
  - Params: `session_id`, `media_list` [{src, alt, filename}] (both required), `base_url`
  - Returns: downloaded, failed, results[], url_map{}
  - Security: SSRF check, MIME validation, PHP scan, SVG sanitization, 10MB/file, 500MB/session

- **`klytos_import_session_status`** — Get import progress: total/imported/pending/failed per page.
  - Params: `session_id` (required)

## AI Workflow: WordPress XML Import

```
1. klytos_import_analyze_wp_xml({ file_path: "imports/wp-export.xml" })
   → Get session_id, review summary

2. For each page slug:
   klytos_import_fetch_wp_page({ session_id, slug })
   → If has_gutenberg_blocks: use content_html directly
   → If has_shortcodes or no blocks: klytos_import_convert_content({ html, source_type: "wordpress" })

3. klytos_import_execute_batch({ session_id, pages: [...], url_map: {...} })

4. klytos_build_site()  ← only once at the end
```

## AI Workflow: URL Import (Phase 2)

```
1. Try sitemap: klytos_import_analyze_sitemap({ sitemap_url: "https://example.com/sitemap.xml" })
   → If fails, fall back to: klytos_import_discover_site({ start_url: "https://example.com" })

2. klytos_import_analyze_style({ url: "https://example.com" })
   → Apply theme: klytos_set_theme({ colors, fonts, layout })

3. For each page:
   klytos_import_fetch_page({ url: page_url })
   klytos_import_convert_content({ html: result.main_content_html, post_type: "page" })
   // post_type auto-detects the editor (gutenberg or tinymce) — output format adapts

4. klytos_import_download_media({ session_id, media_list, base_url })

5. klytos_import_execute_batch({ session_id, pages, url_map })

6. klytos_set_menu({ items: [...] })

7. klytos_build_site()
```

## Key Architecture Decisions

- **Two-layer design:** Acquisition (fetching HTML) vs. Processing (conversion). AI agents with browser access can skip PHP-side fetching.
- **Sessions are persistent:** Stored in `import_sessions` collection. Resumable on interruption.
- **Test-first import:** Import 2-3 pages as drafts first. Ask the user to review. If approved, import the rest as published. This avoids manually publishing 100+ pages while keeping quality control.
- **AI decides, plugin executes:** Tools are granular; the AI orchestrates the workflow.
- **Editor-aware conversion:** Each post type has an `editor` field ("gutenberg" or "tinymce"). `convert_content` auto-detects the format from `post_type` and outputs Gutenberg blocks or clean HTML accordingly. Always pass `post_type` when converting.
- **Multi-language:** Check `klytos_get_site_config` for `default_language` and `languages`. If source is monolingual, ask if user wants translation to other configured languages. If source is multilingual, ask which languages to import. Use `lang` and `hreflang_refs` fields on pages. Detect source language from `<html lang>`, hreflang tags, URL patterns (`/en/`, `/es/`), or `detected_lang` from `fetch_page`.
- **Max 20 pages per batch:** Prevents timeouts.

## Hooks for Plugin Extensibility

**Actions:** `importer.before_import`, `importer.after_import`, `importer.before_page`, `importer.after_page`
**Filters:** `importer.page_data`, `importer.session_columns`

## Content Conversion Table

| HTML | Gutenberg Block |
|------|----------------|
| `<h1>`-`<h6>` | `<!-- wp:heading {"level":N} -->` |
| `<p>` | `<!-- wp:paragraph -->` |
| `<img>` | `<!-- wp:image -->` |
| `<ul>`, `<ol>` | `<!-- wp:list -->` |
| `<blockquote>` | `<!-- wp:quote -->` |
| `<table>` | `<!-- wp:table -->` |
| `<pre>`, `<code>` | `<!-- wp:code -->` |
| `<hr>` | `<!-- wp:separator -->` |
| `<video>` | `<!-- wp:video -->` |
| `<iframe>` (YT/Vimeo) | `<!-- wp:embed -->` |
| `<div>` | `<!-- wp:group -->` |
| Unknown shortcodes | `<!-- wp:html -->` + warning |

## Security

- SSRF prevention: URLs validated, private IPs rejected
- XXE prevention: `LIBXML_NONET | LIBXML_NOENT` on all XML parsing
- HTML sanitization: strict allowlist
- File validation: MIME + extension check, PHP code scanning
- All sessions encrypted at rest (same AES-256-GCM as all Klytos data)
