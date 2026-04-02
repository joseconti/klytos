---
name: site-builder-page-trees
description: "Page tree structures by site type for the Site Builder — slugs, templates, hierarchy, and menu structure."
trigger: When defining the page structure during site building.
---

# Page Trees by Site Type

This guide provides recommended page hierarchies, slugs, templates, and menu structures for each site type.

## How to Create Pages in Klytos

For each page, call `klytos_create_page` with:
- `title` — page title
- `slug` — URL slug (lowercase, hyphens)
- `template` — template to use (default, landing, blog-post, blank, or custom)
- `status` — always `draft` during building (publish in Phase 9)
- `parent_slug` — parent page slug for hierarchy (optional)
- `language` — language code (e.g., `es`, `en`)

Built-in templates: `default`, `landing`, `blog-post`, `blank`

**CRITICAL: Homepage slug MUST be `index`.**
The build engine maps slug `index` → `/index.html` (site root). Any other slug (e.g., `inicio`, `home`, `accueil`) creates a subdirectory like `/inicio/index.html` which is NOT the homepage. Always use slug `index` for the homepage regardless of language.

---

## 1. Blog Personal

```
Home (/)                          — template: landing
About Me (/about)                 — template: default
Blog (/blog)                      — template: default
Contact (/contact)                — template: default
Privacy Policy (/privacy)         — template: default
```

**Menu:**
```
Home | About Me | Blog | Contact
```

**Notes:**
- Home shows latest posts or hero + featured content
- Blog page lists all blog posts
- Simple, clean structure — no subpages needed

---

## 2. Corporativo / Empresa

```
Home (/)                          — template: landing
About Us (/about)                 — template: default
  Our History (/about/history)    — template: default    [optional]
  Team (/about/team)              — template: default    [optional]
Services (/services)              — template: default
  Service A (/services/a)         — template: default    [per service]
Blog (/blog)                      — template: default
Contact (/contact)                — template: default
Privacy Policy (/privacy)         — template: default
Terms (/terms)                    — template: default    [optional]
```

**Menu:**
```
Home | About Us > [History, Team] | Services > [Service A, B, C] | Blog | Contact
```

**Notes:**
- Services can be subpages or use the `services` CPT (recommended for 4+ services)
- Team can be a subpage or use the `team` CPT
- If using CPTs, the Services/Team pages display lists via custom templates

---

## 3. Portfolio / Creativo

```
Home (/)                          — template: landing
Projects (/projects)              — template: default
  Project A (/projects/a)         — template: default    [per project]
About Me (/about)                 — template: default
Contact (/contact)                — template: default
Privacy Policy (/privacy)         — template: default
```

**Menu:**
```
Home | Projects | About Me | Contact
```

**Notes:**
- Projects page shows a filterable grid (by project-type taxonomy)
- Individual projects are CPT items, not subpages
- Home typically shows a curated selection of best projects

---

## 4. Catalogo / Tienda

```
Home (/)                          — template: landing
Products (/products)              — template: default
  Category A (/products/cat-a)    — template: default    [per category]
About Us (/about)                 — template: default
Contact (/contact)                — template: default
Privacy Policy (/privacy)         — template: default
Terms (/terms)                    — template: default
```

**Menu:**
```
Home | Products > [Category A, B, C] | About Us | Contact
```

**Notes:**
- Products page shows all products or category grid
- Individual products are CPT items
- Categories page is optional — product-category taxonomy handles filtering
- Consider adding a search/filter interface

---

## 5. Landing Page

```
Home (/)                          — template: landing
Privacy Policy (/privacy)         — template: default
Terms (/terms)                    — template: default    [optional]
```

**Menu:**
```
[Minimal or none — navigation within the single page via anchors]
```

**Notes:**
- Single page with sections: hero, features, social proof, pricing, FAQ, CTA
- Privacy and Terms are separate pages (legal requirement)
- Navigation can be anchor links to sections within the page
- Footer links to Privacy/Terms

---

## 6. Documentacion / Knowledge Base

```
Home (/)                          — template: landing
Guides (/guides)                  — template: default
  Getting Started (/guides/start) — template: default    [category parent]
  API (/guides/api)               — template: default    [category parent]
  Configuration (/guides/config)  — template: default    [category parent]
FAQ (/faq)                        — template: default
Contact (/contact)                — template: default
Privacy Policy (/privacy)         — template: default
```

**Menu:**
```
Home | Guides > [Getting Started, API, Configuration] | FAQ | Contact
```

**Notes:**
- Guides page shows category cards with doc counts
- Individual docs are CPT items organized by doc-category taxonomy
- Sidebar navigation within docs section (prev/next, category tree)
- FAQ can be a page with embedded FAQ CPT items or a standalone page

---

## Menu Creation

After creating all pages, build the navigation menu:

```
1. klytos_set_menu with the full menu structure
2. For each item: klytos_add_menu_item with:
   - type: "page" (for internal pages) or "url" (for external links)
   - page_slug: slug of the page
   - label: display text
   - parent_id: ID of parent item (for dropdowns)
   - order: position in menu
```

**Menu rules:**
- Maximum 7 top-level items (recommended: 5-6)
- Maximum 2 levels of nesting
- Most important pages first (Home is always first)
- Contact is typically last
- Privacy/Terms go in the footer, not the main menu

---

## Multilingual Sites

For multilingual sites, create each page for each language:

```
klytos_create_page with language="es" → /es/sobre-nosotros
klytos_create_page with language="en" → /en/about-us
klytos_create_page with language="fr" → /fr/a-propos
```

**Slug i18n examples:**

| Page | ES | EN | FR | CA |
|------|----|----|----|-----|
| Home | index | index | index | index |
| About | sobre-nosotros | about | a-propos | sobre-nosaltres |
| Services | servicios | services | services | serveis |
| Blog | blog | blog | blog | blog |
| Contact | contacto | contact | contact | contacte |
| Privacy | privacidad | privacy | confidentialite | privacitat |
| Projects | proyectos | projects | projets | projectes |
| Products | productos | products | produits | productes |
| FAQ | preguntas-frecuentes | faq | foire-aux-questions | preguntes-frequents |
