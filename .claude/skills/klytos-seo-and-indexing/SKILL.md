---
name: klytos-seo-and-indexing
description: Guide for SEO, sitemap.xml, llms.txt, and search engine and AI indexing in Klytos CMS. Use when asking about SEO, sitemap, robots.txt, llms.txt, meta tags, Open Graph, structured data, search engine optimization, AI indexing, page indexing, canonical URLs, hreflang tags, JSON-LD schema, or the build engine SEO process.
---

# Klytos SEO & Indexing Guide

## Overview

Klytos generates 100% static HTML pages. SEO is handled during the build process:
the BuildEngine injects all necessary meta tags, generates sitemap.xml, robots.txt,
and LLM discoverability files (llms.txt, llms-full.txt, per-page .html.md) automatically.

## Meta Tags (injected in every page <head>)

```html
<!-- Generator identification -->
<meta name="generator" content="Klytos {version}">

<!-- Basic SEO -->
<title>{page_title} — {site_name}</title>
<meta name="description" content="{meta_description}">
<link rel="canonical" href="{canonical_url}">

<!-- Open Graph (Facebook, LinkedIn, etc.) -->
<meta property="og:type" content="website">
<meta property="og:title" content="{page_title}">
<meta property="og:description" content="{meta_description}">
<meta property="og:url" content="{canonical_url}">
<meta property="og:image" content="{og_image}">
<meta property="og:site_name" content="{site_name}">
<meta property="og:locale" content="{locale}">

<!-- Twitter Cards -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{page_title}">
<meta name="twitter:description" content="{meta_description}">
<meta name="twitter:image" content="{og_image}">

<!-- Multilingual (hreflang) -->
<link rel="alternate" hreflang="{lang}" href="{url}">

<!-- JSON-LD Structured Data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": "{page_title}",
  "description": "{meta_description}",
  "url": "{canonical_url}",
  "publisher": {
    "@type": "Organization",
    "name": "{site_name}"
  }
}
</script>
```

## sitemap.xml

Auto-generated during build. Includes all published pages with:
- `<loc>` — full canonical URL
- `<lastmod>` — page last modification date (ISO 8601)
- `<changefreq>` — based on page type (homepage: daily, others: weekly)
- `<priority>` — homepage: 1.0, top-level pages: 0.8, nested: 0.6

Plugins can add URLs via the `build.sitemap_urls` filter.

**Note:** `.md` and `llms*.txt` files are NOT included in sitemap.xml — only `.html` pages.

## robots.txt

Auto-generated with:
```
User-agent: *
Allow: /
Sitemap: {site_url}/sitemap.xml
# AI-friendly content index
# llms.txt: {site_url}/llms.txt

# Additional rules from admin settings
{robots_extra}
```

## LLM Discoverability (AI-Ready Content)

Klytos is the first CMS with **native** LLM discoverability. Following the llms.txt
specification (https://llmstxt.org/), the build engine generates three types of files
for AI agents (ChatGPT, Claude, Perplexity, Gemini) to discover and cite content.

### Configuration (site config `seo` object)

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `llms_txt_enabled` | boolean | `true` | Generate `/llms.txt` |
| `llms_full_txt_enabled` | boolean | `true` | Generate `/llms-full.txt` |
| `llms_md_pages_enabled` | boolean | `true` | Generate per-page `.html.md` files |

All enabled by default — LLM compatibility comes out of the box.

### Page-level LLM fields

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `llm_optional` | boolean | `false` | Moves page to "Optional" section of llms.txt |
| `llm_exclude` | boolean | `false` | Excludes page from all LLM files (llms.txt, llms-full.txt, .html.md) |

Typical usage: mark legal pages (privacy, legal notice) as `llm_optional`, and
exclude internal/staging pages with `llm_exclude`.

### /llms.txt (curated index)

```markdown
# {site_name}

> {site_description}

{tagline}

## Pages

- [{page_title}]({page_url}): {meta_description}

## Optional

- [{page_title}]({page_url}): {meta_description}
```

Ordering: index (home) first, then default language pages, then other languages.

### /llms-full.txt (complete content)

Single Markdown file with full content of every published page. Allows an AI agent
to load the entire site in one request. Uses proper Markdown conversion (headings,
lists, links, images, tables) via the `HtmlToMarkdown` class.

### Per-page .html.md files

Each published page gets a Markdown version at `{slug}/index.html.md` with:
- H1 title, blockquote description
- URL, Language, Last modified metadata
- Full page content in clean Markdown

### HtmlToMarkdown converter

File: `core/html-to-markdown.php` — Pure PHP, no external dependencies.

Converts HTML to clean Markdown:
- Headings, paragraphs, bold/italic, links, images
- Ordered/unordered lists
- Blockquotes, code blocks, inline code
- Tables (pipe format with header separator)
- Horizontal rules, line breaks

Strips non-content elements: script, style, noscript, form, iframe, nav, header,
footer, HTML comments, Klytos block markers.

### .htaccess headers

The build engine adds rules so `.md` and `llms*.txt` files are served with:
- `Content-Type: text/markdown; charset=utf-8`
- `X-Robots-Tag: noindex` (prevents Google from indexing duplicate content)

## MCP Tools for SEO

- `klytos_build_site` — Full site build (regenerates all SEO + LLM files)
- `klytos_build_page` — Rebuild single page (regenerates both .html and .html.md)
- `klytos_get_build_status` — Includes LLM config status
- `klytos_rebuild_block` — Smart rebuild a global block (does NOT regenerate .md files)
- `klytos_rebuild_css` — Regenerate CSS only

## Extending SEO & LLM via Plugins

```php
// Add custom URLs to sitemap.xml
klytos_add_filter('build.sitemap_urls', function (array $urls): array {
    $urls[] = [
        'loc'        => 'https://example.com/custom-page',
        'lastmod'    => '2025-01-15',
        'changefreq' => 'monthly',
        'priority'   => '0.5',
    ];
    return $urls;
});

// Add custom rules to robots.txt
klytos_add_filter('build.robots_txt', function (string $robots): string {
    $robots .= "\nDisallow: /private/\n";
    return $robots;
});

// Inject custom meta tags into <head>
klytos_add_filter('build.head_html', function (string $head): string {
    $head .= '<meta name="custom-tag" content="value">';
    return $head;
});

// Add entries to llms.txt (e.g., blog posts from a plugin)
klytos_add_filter('build.llms_pages', function (array $pages): array {
    $pages[] = [
        'slug'             => 'blog/my-post',
        'title'            => 'My Blog Post',
        'lang'             => 'en',
        'meta_description' => 'A blog post about something',
        'markdown_content' => 'The full content in Markdown...',
        'is_index'         => false,
        'llm_optional'     => false,
        'llm_exclude'      => false,
        'updated_at'       => '2026-04-05T10:00:00Z',
    ];
    return $pages;
});

// Modify llms.txt content before writing
klytos_add_filter('build.llms_txt', function (string $content): string {
    return $content;
});

// Modify llms-full.txt content before writing
klytos_add_filter('build.llms_full_txt', function (string $content): string {
    return $content;
});

// Modify per-page Markdown before writing
klytos_add_filter('build.page_markdown', function (string $md, array $page): string {
    return $md;
});

// React after LLM files are generated
klytos_add_action('build.llms_generated', function (array $stats): void {
    // $stats: md_files_built, llms_txt_generated, llms_full_txt_generated
});
```
