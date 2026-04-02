---
name: site-builder-types
description: "Site type catalog for the Site Builder — recommended structures, CPTs, taxonomies, and fields by site type and sector."
trigger: When building a site from scratch and need to determine structure based on site type.
---

# Site Types & Recommended Structures

This guide helps you recommend the right structure based on the type of site the user wants to build.

## Site Types

### 1. Blog Personal

**Ideal for:** writers, journalists, content creators, personal brands.

**Recommended pages:** Home, About Me, Blog, Contact
**Homepage style:** latest posts or hero + featured posts
**CPTs:** none required (use built-in pages + blog category)
**Taxonomies:** Categories, Tags (on blog posts)
**Custom fields:** none required
**Plugins:** none required
**Template:** default for pages, blog-post for articles
**Layout:** sidebar optional (right), max-width 800-900px for readability

---

### 2. Corporativo / Empresa

**Ideal for:** companies, agencies, consulting firms, professional services.

**Recommended pages:** Home, About Us, Services, Team, Blog/News, Contact
**Homepage style:** hero + services overview + testimonials + CTA
**CPTs:**
- `services` — name: "Services", slug: `services`
  - Fields: `description` (richtext), `icon` (text — CSS class or SVG), `price` (text, optional), `featured_image` (image), `order` (number)
- `team` — name: "Team", slug: `team`
  - Fields: `position` (text), `bio` (richtext), `photo` (image), `email` (text), `linkedin` (text), `order` (number)
- `testimonials` — name: "Testimonials", slug: `testimonials`
  - Fields: `author_name` (text), `author_position` (text), `company` (text), `quote` (textarea), `rating` (number, 1-5), `photo` (image)
**Taxonomies:**
- `service-category` (hierarchical) on `services`
**Plugins:** klytos-forms (contact form)
**Template:** default for pages, landing for home
**Layout:** no sidebar, max-width 1200px

---

### 3. Portfolio / Creativo

**Ideal for:** designers, photographers, illustrators, developers, freelancers.

**Recommended pages:** Home, Projects, About Me, Contact
**Homepage style:** project grid/gallery with filters
**CPTs:**
- `projects` — name: "Projects", slug: `projects`
  - Fields: `client` (text), `date_completed` (date), `description` (richtext), `gallery` (gallery), `featured_image` (image), `url` (text), `technologies` (text)
**Taxonomies:**
- `project-type` (hierarchical) on `projects` — e.g., Branding, Web Design, Photography
- `project-tag` (flat) on `projects` — e.g., React, Figma, WordPress
**Plugins:** none required
**Template:** default for pages, landing for home
**Layout:** no sidebar, max-width 1400px for visual impact

---

### 4. Catalogo / Tienda

**Ideal for:** stores, product catalogs, restaurants with menus, real estate agencies.

**Recommended pages:** Home, Products/Catalog, Categories, About Us, Contact
**Homepage style:** hero + featured products + categories grid + CTA
**CPTs:**
- `products` — name: "Products", slug: `products`
  - Fields: `description` (richtext), `price` (text), `sku` (text), `featured_image` (image), `gallery` (gallery), `specs` (repeater: label + value), `availability` (select: in-stock/out-of-stock/preorder)
**Taxonomies:**
- `product-category` (hierarchical) on `products`
- `product-tag` (flat) on `products`
**Plugins:** klytos-forms (inquiry form)
**Template:** default for pages, landing for home
**Layout:** sidebar optional (left for filters), max-width 1200px

**Sector variations:**
- **Restaurant:** CPT `dishes` with fields: `price`, `description`, `photo`, `allergens` (select multiple), `spicy_level` (number). Taxonomy: `dish-category` (Starters, Mains, Desserts, Drinks)
- **Real estate:** CPT `properties` with fields: `price`, `area_sqm`, `bedrooms`, `bathrooms`, `address`, `gallery`, `map_coordinates`. Taxonomy: `property-type` (Apartment, House, Commercial, Land)

---

### 5. Landing Page

**Ideal for:** product launches, events, campaigns, single-purpose sites.

**Recommended pages:** single page (Home) with multiple sections
**Homepage style:** hero + features/benefits + social proof + pricing (optional) + FAQ + CTA
**CPTs:** none required
**Taxonomies:** none required
**Custom fields:** none required
**Plugins:** klytos-forms (lead capture form)
**Template:** landing (full-width, no header/footer navigation distractions)
**Layout:** no sidebar, max-width 1000-1200px

---

### 6. Documentacion / Knowledge Base

**Ideal for:** software docs, help centers, knowledge bases, educational resources.

**Recommended pages:** Home, Guides (parent page), FAQ, Contact
**Homepage style:** search + category cards + getting started section
**CPTs:**
- `docs` — name: "Documentation", slug: `docs`
  - Fields: `content` (richtext), `order` (number), `difficulty` (select: beginner/intermediate/advanced), `last_updated` (date), `related_docs` (relationship)
- `faq` — name: "FAQ", slug: `faq`
  - Fields: `answer` (richtext), `order` (number)
**Taxonomies:**
- `doc-category` (hierarchical) on `docs` — e.g., Getting Started, API, Configuration
- `faq-category` (hierarchical) on `faq`
**Plugins:** none required
**Template:** default with sidebar for navigation
**Layout:** sidebar left, max-width 1200px

---

## Sector Modifiers

These modifiers apply ON TOP of the base site type:

| Sector | Tone | Color tendency | Typography | Special needs |
|--------|------|----------------|------------|---------------|
| Technology | Clean, modern | Blues, grays, dark backgrounds | Sans-serif (Inter, Roboto) | Code blocks, API docs |
| Health | Trustworthy, calm | Blues, greens, whites | Clean sans-serif | WCAG AAA compliance recommended |
| Education | Approachable, clear | Blues, oranges, greens | Readable sans-serif | Large text, high contrast |
| Commerce | Professional, dynamic | Industry-dependent | Bold headings, clean body | Product showcases, CTAs |
| Gastronomy | Warm, inviting | Warm tones, earthy | Serif headings, sans body | Food photography, menus |
| Legal / Finance | Authoritative, trustworthy | Dark blues, grays, gold accents | Serif or conservative sans | Formal tone, credentials |
| Creative / Art | Expressive, unique | Bold or muted — depends on artist | Display fonts for headings | Visual-first layout, galleries |
| NGO / Non-profit | Empathetic, inspiring | Warm greens, oranges, earth tones | Humanist sans-serif | Donation CTAs, impact stories |

## How to Use This Guide

1. Ask the user what type of site they want (or infer from context)
2. Ask about their sector/niche
3. Look up the matching site type above
4. Apply sector modifiers
5. Present the recommended structure to the user for confirmation
6. Adjust based on their feedback before creating anything
