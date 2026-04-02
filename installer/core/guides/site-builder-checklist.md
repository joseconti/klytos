---
name: site-builder-checklist
description: "Final verification checklist for the Site Builder — build, integrity, navigation, and launch verification."
trigger: When completing the site building process and preparing for launch.
---

# Site Builder — Final Checklist

Use this checklist during Phase 9 (Build, Verification & Launch) to ensure everything works correctly before going live.

---

## Pre-Launch Checklist

### 1. Build & Assets

- [ ] Run `klytos_build_site` — full static site generation
- [ ] Run `klytos_rebuild_css` — ensure all styles are current
- [ ] Run `klytos_rebuild_plugin_assets` — plugin CSS/JS up to date
- [ ] Verify build completed without errors
- [ ] Check build output: number of pages generated matches expected count

### 2. Integrity

- [ ] Run `klytos_run_integrity_check` — verify file integrity
- [ ] Check for any warnings or errors in the integrity report
- [ ] Resolve any integrity issues before proceeding

### 3. Pages & Content

- [ ] All pages have content (no empty pages)
- [ ] All pages have proper SEO: title, meta_description, og_image
- [ ] Heading hierarchy is correct on each page (H1 > H2 > H3)
- [ ] No placeholder text left (Lorem ipsum, TODO, TBD)
- [ ] Images have alt text set
- [ ] Internal links point to correct pages
- [ ] External links open in new tab where appropriate

### 4. Navigation

- [ ] Main menu includes all primary pages
- [ ] Menu order is logical (Home first, Contact last)
- [ ] Dropdown submenus work correctly (if any)
- [ ] All menu links point to correct pages
- [ ] Footer links work (Privacy, Terms)
- [ ] No broken links

### 5. Custom Post Types

- [ ] All CPTs are created and visible
- [ ] Custom fields are defined for each CPT
- [ ] At least 1-2 sample items exist per CPT (if applicable)
- [ ] Taxonomies have initial terms
- [ ] CPT items display their custom fields correctly

### 6. Templates & Blocks

- [ ] Each page uses the correct template
- [ ] Custom templates render correctly
- [ ] Header/footer template parts show correct content
- [ ] Reusable blocks render in all pages where used
- [ ] No broken block markup in any page

### 7. Design & Theme

- [ ] Colors match the user's approved palette
- [ ] Typography is consistent across all pages
- [ ] Layout width and spacing are correct
- [ ] Responsive behavior works (if user can test)
- [ ] Favicon is set (or noted as TODO)
- [ ] Logo is displayed in header (or noted as TODO)

### 8. Forms (if applicable)

- [ ] Contact form exists and has all required fields
- [ ] Form submission works (test with `klytos-forms` tools)
- [ ] Email notifications are configured
- [ ] Success/error messages are appropriate

### 9. GDPR / Consent (if applicable)

- [ ] Consent banner is enabled
- [ ] Banner text matches site language
- [ ] Privacy policy page exists and is linked
- [ ] Cookie categories are properly defined
- [ ] Consent declarations exist for each external service (Analytics, etc.)

### 10. Email / SMTP (if configured)

- [ ] From name and email are set
- [ ] Reply-to email is set
- [ ] SMTP credentials work (if SMTP transport)

### 11. Users (if additional users created)

- [ ] All users have correct roles
- [ ] 2FA recommended for each user

### 12. SEO & Indexing

- [ ] `indexing_enabled` is still FALSE during verification (activate LAST)
- [ ] Each page has unique title and meta_description
- [ ] sitemap.xml will be generated on final build
- [ ] robots.txt rules are correct

---

## Launch Sequence

Execute these steps IN ORDER:

1. **Final content review** — user confirms all pages look correct
2. **Activate indexing** — `klytos_set_site_config` with `indexing_enabled: true`
3. **Final build** — `klytos_build_site` one last time (generates sitemap.xml + robots.txt)
4. **Verify sitemap** — confirm sitemap.xml lists all published pages
5. **Present summary** to the user (see template below)

---

## Summary Template

Present this summary to the user at the end:

```
=== SITE CREATION SUMMARY ===

SITE: {site_name}
URL: {site_url}

--- CONTENT CREATED ---
Pages: {count} ({list of page titles})
Custom Post Types: {count} ({list of CPT names})
  - {cpt_name}: {item_count} items, {field_count} custom fields
Taxonomies: {count} ({list})
  - {taxonomy_name}: {term_count} terms
Reusable Blocks: {count} ({list})
Templates: {count} custom ({list}) + 4 built-in
Menu Items: {count}

--- CONFIGURATION ---
Theme: {primary_color} + {secondary_color} + {accent_color}
Typography: {heading_font} / {body_font}
Layout: {max_width}, header {header_style}
SEO: configured, indexing {enabled/disabled}
Analytics: {Google Analytics ID or "Klytos privacy-first only"}
GDPR: {enabled/disabled}
Email: {transport} ({from_email})
AI Providers: {list or "none configured"}
Plugins Active: {list}
Cache: {driver}

--- NEXT STEPS ---
1. Add more content to your pages
2. Create more items in your custom post types
3. Configure 2FA if you haven't already
4. Plan regular backups
5. Review site performance (enable DevBar in Settings)
6. Consider webhooks for third-party integrations
7. Keep Klytos updated when new versions are available
```

---

## Post-Launch Notes

Remind the user:
- They can always modify pages, settings, and design through MCP tools or the admin panel
- Content changes require a rebuild (`klytos_build_site`) to appear on the live site
- SEO improvements take time — search engines need to discover and index the site
- They can disable indexing again temporarily if they need to make major changes
