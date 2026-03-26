---
name: klytos-seo-and-indexing
description: Guide for SEO, sitemap.xml, llms.txt, and search engine/AI indexing in Klytos CMS.
trigger: When the user asks about SEO, sitemap, robots.txt, llms.txt, meta tags, Open Graph, structured data, or search engine optimization in Klytos.
---

# Klytos SEO & Indexing Guide

## Overview

Klytos generates 100% static HTML pages. SEO is handled during the build process:
the BuildEngine injects all necessary meta tags, generates sitemap.xml, robots.txt,
llms.txt, and llms-full.txt automatically.

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

## robots.txt

Auto-generated with:
```
User-agent: *
Allow: /
Sitemap: {site_url}/sitemap.xml

# Additional rules from admin settings
{robots_extra}
```

## llms.txt (AI Indexing)

Following the llms.txt specification (https://llmstxt.org/), Klytos generates:

### /llms.txt (summary)
```
# {site_name}

> {site_description}

## Pages

- [{page_title}]({page_url}): {meta_description}
```

### /llms-full.txt (detailed)
Contains the full text content of every published page, formatted for LLM consumption.
Strips HTML, preserves structure with markdown headings.

## MCP Tools for SEO

- `klytos_build_site` — Full site build (regenerates all SEO files)
- `klytos_rebuild_css` — Regenerate CSS only
- `klytos_rebuild_block` — Smart rebuild a global block

The build engine automatically generates sitemap.xml, robots.txt, llms.txt,
and llms-full.txt on every full build.

## Extending SEO via Plugins

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
```
