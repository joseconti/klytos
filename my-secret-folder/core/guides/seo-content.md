---
description: "Use when creating or editing page content in Klytos CMS. Ensures every page has proper SEO structure, HTML semantics, meta tags, structured data, and accessibility for maximum search engine visibility."
globs: ["**/*.php", "**/*.html"]
alwaysApply: false
---

# Klytos CMS — SEO Content Creation Guide

## CRITICAL RULES

Every page created via MCP (`klytos_create_page`, `klytos_update_page`) MUST follow these SEO rules. A page without proper SEO is invisible to search engines.

---

## 1. Page Title (`title` field)

The `title` field becomes the `<title>` tag — the MOST important SEO element.

**Rules:**
- Maximum 60 characters (Google truncates at ~60)
- Primary keyword FIRST, brand name LAST (or omitted — Klytos appends it automatically)
- Unique per page — never duplicate titles
- Action-oriented or descriptive — tells the user AND Google what the page is about
- Do NOT include the site name — Klytos adds ` — Site Name` automatically if not present

**Good examples:**
```
Build Websites with AI — Free CMS          ← 38 chars, keyword first
SEO Marketing Services for Small Business   ← 45 chars, descriptive
How to Create a Landing Page in 5 Minutes   ← 45 chars, how-to
```

**Bad examples:**
```
Home                                        ← No keywords, too generic
Klytos — Klytos CMS — AI Website Builder   ← Repetitive, keyword stuffed
Welcome to our amazing website page!!!      ← No keywords, unprofessional
```

---

## 2. Meta Description (`meta_description` field)

Appears below the title in search results. Not a ranking factor, but critical for CTR.

**Rules:**
- 120-155 characters (Google truncates at ~155-160)
- Include the primary keyword naturally
- Include a call-to-action (CTA)
- Unique per page
- Describe what the user will FIND, not what the page IS
- Klytos truncates at word boundary if you exceed 160 chars

**Good examples:**
```
Learn how to build a complete website using AI in under 5 minutes. Free, open-source CMS with visual editor. Get started today.
```
```
Professional SEO services for small businesses. Increase organic traffic by 300%. Free site audit included. Contact us now.
```

**Bad examples:**
```
This is our homepage where you can find information about our company and services.
```
```
SEO SEO SEO marketing marketing digital marketing best SEO agency SEO services.
```

---

## 3. Heading Structure (H1-H6)

Search engines use headings to understand content hierarchy. This is critical.

**Rules:**
- **ONE H1 per page** — always the main topic. Often matches the `title` but can be longer.
- **H2 for main sections** — each H2 should target a secondary keyword
- **H3 for subsections** — under an H2
- **Never skip levels** — don't go from H2 to H4
- **Include keywords naturally** in headings — don't force them
- **Descriptive, not decorative** — headings are for structure, not visual styling

**Correct structure:**
```html
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Complete Guide to AI Website Building</h1>
<!-- /wp:heading -->

  <!-- wp:heading -->
  <h2 class="wp-block-heading">What is an AI-First CMS?</h2>
  <!-- /wp:heading -->

    <!-- wp:heading {"level":3} -->
    <h3 class="wp-block-heading">How MCP Protocol Works</h3>
    <!-- /wp:heading -->

    <!-- wp:heading {"level":3} -->
    <h3 class="wp-block-heading">Supported AI Assistants</h3>
    <!-- /wp:heading -->

  <!-- wp:heading -->
  <h2 class="wp-block-heading">Getting Started in 5 Minutes</h2>
  <!-- /wp:heading -->

    <!-- wp:heading {"level":3} -->
    <h3 class="wp-block-heading">Step 1: Installation</h3>
    <!-- /wp:heading -->

    <!-- wp:heading {"level":3} -->
    <h3 class="wp-block-heading">Step 2: Connect Your AI</h3>
    <!-- /wp:heading -->
```

**Wrong structure:**
```html
<h1>Welcome</h1>
<h3>About Us</h3>        ← Skipped H2!
<h2>Services</h2>
<h1>Contact Us</h1>      ← Second H1!
<h4>Phone Number</h4>    ← Skipped H3!
```

---

## 4. Image SEO

Every image MUST have proper SEO attributes.

**Rules:**
- **`alt` attribute is MANDATORY** — describes the image for search engines and screen readers
- Alt text should be descriptive, 5-15 words, include keywords naturally
- **`loading="lazy"`** for images below the fold (NOT for the first visible image)
- Use descriptive filenames: `ai-website-builder-dashboard.jpg` not `IMG_4523.jpg`
- Use WebP format when possible (smaller, faster)
- Always include `width` and `height` to prevent layout shift (CLS)

**Correct image block:**
```html
<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large">
<img src="/assets/images/klytos-dashboard-screenshot.jpg"
     alt="Klytos CMS admin dashboard showing page management and analytics"
     width="1200" height="630" loading="lazy" />
<figcaption class="wp-element-caption">The Klytos admin panel with real-time analytics.</figcaption>
</figure>
<!-- /wp:image -->
```

**Wrong:**
```html
<img src="/assets/images/screenshot.png" />     ← No alt, no dimensions, generic filename
<img src="/assets/images/img.jpg" alt="image" /> ← Useless alt text
```

---

## 5. Internal Linking

Internal links distribute SEO authority and help users navigate.

**Rules:**
- **Every page should link to at least 2-3 other pages** on the site
- Use **descriptive anchor text** — NOT "click here" or "read more"
- Link to related content naturally within the text
- Parent pages should link to all child pages
- Use relative URLs for internal links: `/servicios/marketing/` not `https://example.com/servicios/marketing/`

**Good internal linking:**
```html
<!-- wp:paragraph -->
<p>Our <a href="/servicios/seo/">SEO services</a> can help you improve your organic search rankings. Combined with our <a href="/servicios/marketing/">digital marketing strategies</a>, we deliver measurable results for businesses of all sizes.</p>
<!-- /wp:paragraph -->
```

**Bad internal linking:**
```html
<!-- wp:paragraph -->
<p>For more information, <a href="/servicios/seo/">click here</a>.</p>
<!-- /wp:paragraph -->
```

---

## 6. Content Length and Quality

**Rules:**
- **Homepage:** 300-500 words minimum
- **Service/product pages:** 500-1000 words
- **Blog posts:** 1000-2000 words for ranking
- **Landing pages:** 500-800 words focused on conversion
- First paragraph should contain the primary keyword
- Use short paragraphs (2-4 sentences max)
- Use lists to break up text
- Include relevant keywords naturally — NEVER keyword stuff
- Every page should answer a user's question or solve a problem

---

## 7. URL Structure (slug)

**Rules:**
- Lowercase, hyphens only: `servicios/marketing-digital`
- 3-5 words maximum
- Include primary keyword
- No stop words unless needed for readability
- Hierarchical: `servicios/` → `servicios/seo/` → `servicios/seo/auditorias/`
- NEVER change URLs after publishing without setting up redirects

**Good slugs:**
```
servicios
servicios/marketing-digital
blog/como-crear-web-con-ia
precios
contacto
```

**Bad slugs:**
```
page-1
servicios/marketing-digital-seo-sem-ppc-social-media    ← Too long
SerVicios/MARKETING                                      ← Mixed case
servicios/marketing digital                               ← Spaces
```

---

## 8. Open Graph & Social Meta (og_image field)

When someone shares a page on social media, these tags control the preview.

**Rules:**
- **`og_image` is critical** — pages without it get generic/ugly previews
- Image should be 1200x630 pixels (Facebook/LinkedIn optimal)
- The image should be relevant to the page content
- Klytos auto-generates: `og:title`, `og:description`, `og:url`, `og:site_name`, `og:type`
- Klytos also generates Twitter Card tags automatically

**When creating a page:**
```json
{
  "slug": "servicios/seo",
  "title": "SEO Services — Improve Your Search Rankings",
  "meta_description": "Professional SEO services that deliver measurable results. Free site audit included.",
  "og_image": "/assets/images/seo-services-og.jpg",
  "content_html": "..."
}
```

---

## 9. Structured Data (JSON-LD)

Klytos auto-generates WebPage schema. For richer results, add custom JSON-LD.

### FAQ Schema (for FAQ pages)
```html
<!-- wp:html -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "What is Klytos CMS?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Klytos is an AI-first CMS controlled entirely via MCP (Model Context Protocol)."
      }
    },
    {
      "@type": "Question",
      "name": "Is Klytos free?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes, the core CMS is free. Premium plugins are available in the marketplace."
      }
    }
  ]
}
</script>
<!-- /wp:html -->
```

### Article Schema (for blog posts)
```html
<!-- wp:html -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "How to Build a Website with AI in 5 Minutes",
  "description": "Step-by-step guide to creating a complete website using Klytos CMS and AI.",
  "author": {
    "@type": "Person",
    "name": "José Conti"
  },
  "datePublished": "2026-03-26",
  "dateModified": "2026-03-26",
  "image": "/assets/images/build-website-ai.jpg"
}
</script>
<!-- /wp:html -->
```

### LocalBusiness Schema (for business sites)
```html
<!-- wp:html -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "My Business Name",
  "description": "Description of the business.",
  "url": "https://example.com",
  "telephone": "+34 123 456 789",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Calle Example 123",
    "addressLocality": "Barcelona",
    "postalCode": "08001",
    "addressCountry": "ES"
  },
  "openingHours": "Mo-Fr 09:00-18:00"
}
</script>
<!-- /wp:html -->
```

### Product Schema (for product pages)
```html
<!-- wp:html -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Klytos CMS",
  "description": "AI-first CMS powered by MCP.",
  "brand": {
    "@type": "Brand",
    "name": "Klytos"
  },
  "offers": {
    "@type": "Offer",
    "price": "0",
    "priceCurrency": "USD",
    "availability": "https://schema.org/InStock"
  }
}
</script>
<!-- /wp:html -->
```

### BreadcrumbList Schema
Klytos generates this automatically via the breadcrumbs system. No need to add manually.

---

## 10. Content Formatting Best Practices

### Use semantic HTML within blocks
```html
<!-- wp:paragraph -->
<p>Use <strong>bold</strong> for important keywords and <em>italic</em> for emphasis. Use <mark>highlight</mark> sparingly for key phrases.</p>
<!-- /wp:paragraph -->
```

### Use lists for scannable content
```html
<!-- wp:list -->
<ul class="wp-block-list">
<!-- wp:list-item -->
<li><strong>Fast:</strong> Static HTML, no database queries on frontend</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>Secure:</strong> AES-256-GCM encryption, CSP headers, rate limiting</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>Private:</strong> GDPR compliant, no cookies, no tracking</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
```

### Use tables for comparisons
```html
<!-- wp:table -->
<figure class="wp-block-table">
<table>
<thead><tr><th>Feature</th><th>Klytos</th><th>WordPress</th></tr></thead>
<tbody>
<tr><td>Page Speed</td><td>100/100</td><td>60-80/100</td></tr>
<tr><td>Security</td><td>AES-256 encrypted</td><td>Plugin dependent</td></tr>
<tr><td>AI Control</td><td>Native MCP</td><td>Plugin required</td></tr>
</tbody>
</table>
</figure>
<!-- /wp:table -->
```

### Call-to-Action sections
```html
<!-- wp:group {"style":{"color":{"background":"#1e293b"},"spacing":{"padding":{"top":"3rem","bottom":"3rem"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="background-color:#1e293b;padding-top:3rem;padding-bottom:3rem">

<!-- wp:heading {"textAlign":"center","style":{"color":{"text":"#ffffff"}}} -->
<h2 class="wp-block-heading has-text-align-center has-text-color" style="color:#ffffff">Ready to Get Started?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"color":{"text":"#94a3b8"}}} -->
<p class="has-text-align-center has-text-color" style="color:#94a3b8">Join thousands of sites already powered by AI.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">
<!-- wp:button {"style":{"color":{"background":"#3b82f6"}}} -->
<div class="wp-block-button">
<a class="wp-block-button__link has-background wp-element-button" style="background-color:#3b82f6" href="/get-started/">Start Free</a>
</div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

</div>
<!-- /wp:group -->
```

---

## 11. SEO Checklist — Use Before Publishing

Before setting `status: "published"` on any page, verify:

- [ ] **Title** is under 60 chars, contains primary keyword
- [ ] **Meta description** is 120-155 chars, contains keyword and CTA
- [ ] **H1** exists, is unique, contains primary keyword
- [ ] **Heading hierarchy** is correct (H1 → H2 → H3, no skips)
- [ ] **All images** have descriptive `alt` text
- [ ] **Internal links** to at least 2-3 related pages
- [ ] **URL slug** is short, descriptive, lowercase with hyphens
- [ ] **og_image** is set (1200x630px)
- [ ] **Content length** meets minimum for page type
- [ ] **First paragraph** mentions the primary keyword
- [ ] **No duplicate content** — each page has unique content
- [ ] **Structured data** added for FAQs, articles, products, or businesses
- [ ] **Call-to-action** present on every commercial page
