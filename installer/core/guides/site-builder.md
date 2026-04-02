---
name: site-builder
description: "Complete 9-phase conversational guide for building a website from scratch with Klytos CMS. Entry point: klytos_start_site_builder tool."
trigger: When the user wants to create, build, or set up their website after installation.
---

# Klytos Site Builder — Conversational Guide

## How This Guide Works

You are an AI assistant guiding a user through building their complete website with Klytos CMS. Follow the 9 phases below in order. Each phase ends with a summary and user confirmation before proceeding.

**CRITICAL RULES:**

1. **Never execute without confirmation.** Always present your plan/recommendations and wait for the user to approve before calling any MCP tool that modifies data.
2. **Ask, don't assume.** When in doubt, ask the user. Better to ask one extra question than to build something wrong.
3. **Be adaptive.** If the user says "I just want a landing page", skip phases that don't apply. Not every site needs CPTs, forms, or multilingual support.
4. **Read guides before creating content.** Before Phase 7 (Content), you MUST call `klytos_get_guide('gutenberg-blocks')` and `klytos_get_guide('seo-content')`.
5. **Progressive disclosure.** Don't overwhelm the user with all options at once. Present the most relevant choices and mention advanced options only if they seem relevant.

---

## PHASE 1 — Discovery & Planning

**Objective:** Understand what we're building before touching anything.

### Questions to Ask

Ask these conversationally — don't dump all at once. Start broad, then drill down:

1. **Site type:** What kind of site do you need?
   - Blog, corporate, portfolio, catalog/store, landing page, documentation, or other?

2. **Sector/niche:** What industry or field?
   - Tech, health, education, commerce, gastronomy, legal, creative, NGO, other?

3. **Language:** What's the main language?
   - Will you need multiple languages?

4. **Target audience:** Who will visit this site?
   - (This affects tone, complexity, accessibility level)

5. **Existing content:** Do you have content to import?
   - (If yes, activate klytos-importer plugin later)

6. **Existing brand:** Do you have a logo, brand colors, or typography preferences?

7. **Page count:** Approximately how many pages do you need?

8. **Forms:** Do you need contact forms or other forms?

9. **Blog:** Do you need a blog/news section in addition to static pages?

10. **Custom content types:** Do you need specialized content like services, products, team members, testimonials, projects, etc.?

### Output

Generate a **Site Plan** text summary:

```
=== SITE PLAN ===
Type: [corporate]
Sector: [technology]
Language: [es] + additional: [en]
Audience: [B2B tech companies]
Pages: [Home, About, Services, Blog, Contact]
CPTs: [Services (with fields), Team, Testimonials]
Forms: [Contact form]
Blog: [Yes]
Import: [No]
Brand: [Has logo + colors: #2563EB, #1E293B]
```

Present this plan and ask: **"Does this look right? Anything to add or change?"**

Only proceed to Phase 2 after confirmation.

### Auxiliary guide

Consult `klytos_get_guide('site-builder-types')` to recommend structures based on the site type.

---

## PHASE 2 — Design Reference

**Objective:** Get a concrete visual reference to guide all design decisions.

### Questions to Ask

1. **"Do you have a website whose design you like or want to use as reference?"**
   - If they provide a URL: visit it (if you have browsing tools), analyze visual structure, colors, typography, layout, style
   - If you can't visit URLs: ask for screenshots
   - If they provide multiple references: identify common patterns

2. **"Is there anything specific about that design you like? Colors, layout, typography, image style..."**

### What to Extract

From the reference(s), determine:
- Dominant color palette
- Typography type (serif, sans-serif, monospace)
- Layout density (spacious vs compact)
- Image style (photos, illustrations, icons)
- General tone (minimalist, corporate, creative, elegant, youthful)

### Output

Generate a **Design Brief** (internal, share key points with user):

```
=== DESIGN BRIEF ===
Reference: [url or "user's screenshots"]
Style: [minimalist, corporate]
Colors: [blues and grays, white background, blue accents]
Typography: [sans-serif, clean, large headings]
Layout: [spacious, full-width hero, cards for content]
Images: [professional photography, minimal illustrations]
Tone: [modern, trustworthy, clean]
```

If the user has NO reference: skip this phase and use sector-based defaults from the palettes guide.

---

## PHASE 3 — Global Site Configuration

**Objective:** Configure everything via `klytos_set_site_config`.

### Collect & Configure

**Site identity:**
- `site_name` — the site name (from Phase 1)
- `tagline` — subtitle or slogan. Ask: "Do you have a tagline or slogan?"
- `description` — global meta description for SEO. Draft one based on Phase 1 info
- `default_language` — from Phase 1
- `languages` — additional languages list (if multilingual)

**Brand images:**
- `favicon_url` — ask if they have a favicon. If not, note as TODO
- `logo_url` — ask if they have a logo. If not, note as TODO
- `seo.default_og_image` — default social sharing image. Can be set later

**Social media:**
- Ask: "Which social media profiles do you use?" — only configure the ones they have
- `social.twitter`, `social.github`, `social.linkedin`, `social.instagram`, `social.youtube`, `social.mastodon`

**SEO:**
- `indexing_enabled` — set to `false` (keep disabled during build, activate in Phase 9)
- `seo.robots_txt_extra` — leave empty for now

**Analytics:**
- Explain: "Klytos has built-in privacy-first analytics that require no configuration."
- Ask: "Do you also use Google Analytics or other third-party analytics?"
- `analytics.google_analytics_id` — only if they use GA
- `analytics.custom_head_scripts` / `analytics.custom_body_scripts` — only if needed

**Admin preferences:**
- `editor` — ask: "Do you prefer the block editor (Gutenberg) or the classic editor (TinyMCE)?" Recommend Gutenberg
- `admin_theme` — ask: "Light or dark admin panel?"

### MCP Tools

```
klytos_set_site_config — apply all settings at once
```

Present a summary of what you'll configure and confirm before executing.

---

## PHASE 4 — Theme & Visual Design

**Objective:** Configure colors, typography, and layout based on Phase 2 brief.

### Auxiliary guides

- Consult `klytos_get_guide('site-builder-palettes')` for pre-designed palettes
- Consult `klytos_get_guide('site-builder-content')` for typography combinations

### Colors (11 parameters)

1. If the user has corporate colors → use them as `primary`/`secondary`
2. If they provided a design reference → extract colors from it
3. Otherwise → present 2-3 palettes from `site-builder-palettes.md` based on sector
4. Derive `background`, `surface`, `text`, `text_muted`, `border` automatically
5. Use standard `success`, `warning`, `error` unless user wants custom

Present the chosen palette visually (list the colors with names) and confirm.

### Typography (8 parameters)

Recommend a combination from `site-builder-content.md` based on site type:
- `fonts.heading` + `fonts.body` + `fonts.code`
- `fonts.heading_weight`, `fonts.body_weight`
- `fonts.base_size` (16px default), `fonts.scale_ratio` (1.25 default)
- `fonts.google_fonts_url`

### Layout (7 parameters)

Recommend based on site type:
- `layout.max_width` — 800-900px for blogs, 1200px for corporate, 1400px for portfolios
- `layout.header_style` — `sticky` recommended for most sites
- `layout.footer_enabled` — `true`
- `layout.sidebar_enabled` — depends on site type
- `layout.sidebar_position` — `right` for blogs, `left` for docs
- `layout.border_radius` — `8px` default (modern), `0px` (sharp), `16px` (playful)
- `layout.spacing_unit` — `1rem` default

### Custom CSS

Ask: "Do you have any custom CSS you want to apply?" (most users won't)

### MCP Tools

```
klytos_set_colors — apply color palette
klytos_set_fonts — apply typography
klytos_set_layout — apply layout settings
klytos_set_theme — apply custom CSS (if any)
```

---

## PHASE 5 — Content Structure

**Objective:** Create the complete information architecture.

### Auxiliary guide

Consult `klytos_get_guide('site-builder-page-trees')` for recommended page hierarchies.

### Pages

1. Propose a page tree based on site type (from `site-builder-page-trees.md`)
2. User confirms, adds, or removes pages
3. Create each page via `klytos_create_page`:
   - All pages start as `draft`
   - Assign correct template
   - Set correct language
   - Set parent_slug for hierarchical pages

### Custom Post Types (if applicable)

Consult `klytos_get_guide('post-types-and-fields')` BEFORE creating any CPT.

1. Propose CPTs based on site type (from `site-builder-types.md`)
2. For each CPT:
   - Create via `klytos_create_post_type` with id, name, slug, slug_i18n
   - Define custom fields via `klytos_add_custom_field` (27 field types available)
   - Create taxonomies via `klytos_add_taxonomy`
   - Create initial terms via `klytos_add_term`

### Homepage

**CRITICAL: The homepage MUST be created with slug `index`.** The build engine maps slug `index` → `/index.html` (site root). Any other slug (e.g., `inicio`, `home`) creates a subdirectory like `/inicio/index.html` which is NOT the homepage. Always use slug `index` regardless of language — the page title can be localized (e.g., title "Inicio" with slug "index").

### MCP Tools

```
klytos_create_page — create each page
klytos_create_post_type — create CPTs
klytos_add_custom_field — define fields for CPTs
klytos_add_taxonomy — create taxonomies
klytos_add_term — create initial terms
klytos_set_site_config — set homepage
```

---

## PHASE 6 — Templates & Blocks

**Objective:** Create custom templates and reusable blocks.

### Auxiliary guides

- `klytos_get_guide('page-structure')` — template system
- `klytos_get_guide('gutenberg-blocks')` — block markup

### Templates

1. Evaluate if the 4 built-in templates cover all needs: `default`, `landing`, `blog-post`, `blank`
2. If not, create custom templates via `klytos_set_custom_template`
3. Configure template parts (header, footer) via `klytos_set_custom_template_part`
4. Create CPT-specific templates if needed

### Reusable Blocks

Identify elements that repeat across pages:
- CTAs (call-to-action sections)
- Banners
- Testimonial sections
- Service/product cards
- Contact sections
- Team sections

For each: create via `klytos_create_block` with HTML, CSS, JS, and configurable slots.

### Page Templates (block combinations)

Create predefined block arrangements:
1. `klytos_create_page_template` — create the template
2. `klytos_add_block_to_template` — add blocks in order
3. `klytos_reorder_template_blocks` — adjust order if needed
4. `klytos_approve_page_template` — finalize

### MCP Tools

```
klytos_set_custom_template — custom templates
klytos_set_custom_template_part — header/footer parts
klytos_create_block — reusable blocks
klytos_set_global_block_data — block global data
klytos_create_page_template — page templates
klytos_add_block_to_template — add blocks to templates
klytos_reorder_template_blocks — reorder blocks
klytos_approve_page_template — approve templates
klytos_rebuild_plugin_assets — rebuild assets
```

---

## PHASE 7 — Content

**Objective:** Generate the actual content for every page and configure navigation.

### CRITICAL: Read These Guides First

```
klytos_get_guide('gutenberg-blocks')  — ALL content MUST use block markup
klytos_get_guide('seo-content')       — SEO structure for every page
klytos_get_guide('accessibility')     — WCAG 2.1 AA compliance
```

### Auxiliary guide

Consult `klytos_get_guide('site-builder-content')` for questions by page type and content source workflows.

### For Each Page

1. Ask the user which content source they prefer (text files, URLs, dictation, AI generation)
2. Generate/adapt content using proper Gutenberg block markup
3. Configure SEO: `title`, `meta_description`, `og_image`
4. Assign the correct template
5. Configure hreflang if multilingual
6. Update the page via `klytos_update_page`

### Images

Consult `site-builder-content.md` for the 5 ways to obtain images.

If Gemini API is configured, actively offer to generate images for:
- Hero sections
- Section backgrounds
- Illustrative icons
- Placeholder team photos

### Custom Post Type Items

Create sample items:
1. `klytos_create_page` with `post_type` parameter
2. `klytos_set_field_value` or `klytos_set_bulk_field_values` for custom fields
3. Assign taxonomy terms

### Navigation Menu

After all pages are created:
1. `klytos_set_menu` — create main menu with full structure
2. `klytos_add_menu_item` — add each item with hierarchy
3. Maximum 7 top-level items, maximum 2 nesting levels
4. Privacy/Terms go in footer, not main menu

### MCP Tools

```
klytos_update_page — update page content
klytos_create_page — create CPT items
klytos_set_field_value — set custom field values
klytos_set_bulk_field_values — set multiple field values
klytos_set_menu — create menu
klytos_add_menu_item — add menu items
klytos_generate_ai_image — generate images (if Gemini configured)
klytos_upload_asset — upload images
```

---

## PHASE 8 — Additional Features

**Objective:** Configure all subsystems beyond content.

### Forms (if needed)

1. Activate klytos-forms: `klytos_activate_plugin` with id `klytos-forms`
2. Create forms with required fields
3. Configure email notifications

### GDPR / Consent

1. Ask: "Do you need a cookie consent banner?"
2. If yes, configure via `klytos_set_consent_config`:
   - `enabled: true`
   - `banner_text` — adapt to site language
   - `privacy_url` — link to privacy policy page
   - `cookie_days` — 365 default
   - `categories` — necessary, functional, analytics, marketing
3. Add declarations for each external service via `klytos_add_consent_declaration`

### Email / SMTP

1. Ask: "Do you want to configure email sending?"
2. Configure `email.from_name`, `email.from_email`, `email.reply_to`
3. If SMTP: ask for `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `smtp_security`

### AI Providers (optional)

Ask: "Do you want to configure AI providers for content generation?"
- Anthropic (Claude), OpenAI (GPT), Google Gemini, OpenRouter
- If Gemini: enables AI image generation

### Users (if additional users needed)

Create via `klytos_create_user` with appropriate roles (admin, editor, viewer).
Recommend 2FA setup for each user.

### Plugins

Review bundled plugins and recommend activation:
- `klytos-forms` — if forms are needed
- `klytos-importer` — if content migration is needed

### Webhooks (if applicable)

Ask: "Do you need notifications to external services when content changes?"
- Available events: page.created, page.updated, page.deleted, build.completed, build.failed, task.created, task.completed, user.created, user.login, plugin.activated, plugin.deactivated

### Scheduled Actions (if applicable)

Ask: "Do you need recurring tasks? (cleanup, backups, sync)"

### Cache

Recommend `auto` (auto-detects best available driver).
If Redis/Memcached available, configure connection parameters.

### Developer Mode

Ask: "Do you want to enable the developer toolbar (DevBar)?"
- For production sites: recommend OFF
- For development: enable with desired panels

### MCP Tools

```
klytos_activate_plugin — activate plugins
klytos_set_consent_config — GDPR settings
klytos_add_consent_declaration — cookie declarations
klytos_set_site_config — email, cache, developer settings
klytos_create_user — additional users
klytos_create_webhook — webhooks
klytos_test_webhook — test webhooks
klytos_schedule_recurring_action — scheduled tasks
```

---

## PHASE 9 — Build, Verification & Launch

**Objective:** Publish the site and verify everything works.

### Auxiliary guide

Consult `klytos_get_guide('site-builder-checklist')` for the complete verification checklist.

### Build

1. `klytos_build_site` — generate full static site
2. `klytos_rebuild_css` — ensure styles are current
3. `klytos_rebuild_plugin_assets` — plugin assets

### Verification

1. `klytos_run_integrity_check` — file integrity
2. Verify all pages generated correctly
3. Verify navigation menu works
4. Verify templates render correctly
5. Verify CPT fields display correctly
6. Verify forms work (if applicable)
7. Verify consent banner appears (if enabled)

### Activate Indexing

1. `klytos_set_site_config` with `indexing_enabled: true`
2. `klytos_build_site` — final build with sitemap.xml and robots.txt

### Present Summary

Use the summary template from `site-builder-checklist.md` to present everything that was created and configured.

### MCP Tools

```
klytos_build_site — generate static site
klytos_rebuild_css — rebuild styles
klytos_rebuild_plugin_assets — rebuild plugin assets
klytos_run_integrity_check — verify integrity
klytos_set_site_config — enable indexing
```

---

## Adaptive Flow Rules

- **Landing page only?** → Phase 1, 2, 3, 4, skip 5 CPTs, skip 6 blocks, 7 (single page), skip 8 advanced, 9
- **Blog only?** → Phase 1, 2, 3, 4, 5 (minimal pages), skip 6, 7, skip 8 advanced, 9
- **User has all content ready?** → Speed through Phase 7 by importing/adapting
- **User wants AI to decide everything?** → Make recommendations at each step, get quick yes/no confirmations
- **User is technical?** → Less explanation, more direct action
- **User is non-technical?** → More explanation, simpler choices, avoid jargon
