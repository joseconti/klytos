---
name: klytos-site-builder
description: Guide for building a complete website from scratch with Klytos CMS. Use when creating a new site, configuring a site after installation, setting up design/content/SEO/navigation, or when the user pastes the post-install prompt. Covers 9 phases: discovery, design reference, global config, theme, content structure, templates, content creation, additional features, and launch.
---

# Klytos Site Builder

## Overview

The Site Builder is a conversational AI guide that walks users through building a complete website with Klytos CMS. It orchestrates 159+ existing MCP tools across 9 phases, from initial discovery to site launch.

**Entry point:** The MCP tool `klytos_start_site_builder` — returns the complete guide for the AI assistant to follow.

**Trigger:** User pastes the post-install prompt (shown at the end of the setup wizard) or asks to "create my site", "build my website", "set up my site", etc.

## Architecture

```
User copies prompt from Setup Wizard (Step 5)
       ↓
Pastes in AI assistant (Claude, ChatGPT, etc.)
       ↓
Prompt says: "Call klytos_start_site_builder"
       ↓
MCP tool returns site-builder.md guide
       ↓
AI follows 9 phases conversationally
```

## Key Files

| File | Purpose |
|------|---------|
| `installer/core/guides/site-builder.md` | Main guide — 9 phases with instructions |
| `installer/core/guides/site-builder-types.md` | Site types catalog (blog, corporate, portfolio, etc.) |
| `installer/core/guides/site-builder-palettes.md` | Color palettes by sector (11 params each) |
| `installer/core/guides/site-builder-page-trees.md` | Page hierarchies and menu structures |
| `installer/core/guides/site-builder-content.md` | Content questions, typography combos, image guidance |
| `installer/core/guides/site-builder-checklist.md` | Final verification checklist and launch sequence |
| `installer/core/mcp/tools/site-builder-tools.php` | MCP tool `klytos_start_site_builder` |
| `installer/admin/setup-wizard.php` | Post-install prompt (lines 766-780) |

## The 9 Phases

| Phase | Objective | Key MCP Tools |
|-------|-----------|---------------|
| 1. Discovery | Understand the project | (questions only) |
| 2. Design Reference | Get visual reference | (analysis only) |
| 3. Global Config | Site identity, SEO, social, analytics | `klytos_set_site_config` |
| 4. Theme & Design | Colors, typography, layout | `klytos_set_colors`, `klytos_set_fonts`, `klytos_set_layout` |
| 5. Content Structure | Pages, CPTs, taxonomies | `klytos_create_page`, `klytos_create_post_type`, `klytos_add_custom_field` |
| 6. Templates & Blocks | Custom templates, reusable blocks | `klytos_set_custom_template`, `klytos_create_block` |
| 7. Content | Page content, images, navigation | `klytos_update_page`, `klytos_set_menu`, `klytos_generate_ai_image` |
| 8. Additional Features | Forms, GDPR, email, users, webhooks | `klytos_activate_plugin`, `klytos_set_consent_config` |
| 9. Build & Launch | Build, verify, enable indexing | `klytos_build_site`, `klytos_run_integrity_check` |

## MCP Tool

### `klytos_start_site_builder`

**Description:** Start the guided website creation process.

**Parameters (all optional):**
- `site_type` (string) — Pre-selected site type: `blog`, `corporate`, `portfolio`, `catalog`, `landing`, `documentation`
- `language` (string) — Preferred language code (e.g., `es`, `en`)

**Returns:** The complete `site-builder.md` guide content + hints + list of auxiliary guides.

**Annotations:** `readOnlyHint: true`, `destructiveHint: false`, `idempotentHint: true`

**File:** `installer/core/mcp/tools/site-builder-tools.php`

## Auxiliary Guides

The main guide references these via `klytos_get_guide()`:

- `site-builder-types` — consulted in Phase 1 for structure recommendations
- `site-builder-palettes` — consulted in Phase 4 for color palette selection
- `site-builder-page-trees` — consulted in Phase 5 for page hierarchy
- `site-builder-content` — consulted in Phase 7 for content generation
- `site-builder-checklist` — consulted in Phase 9 for final verification

Plus existing guides:
- `gutenberg-blocks` — MUST read before creating any page content (Phase 7)
- `seo-content` — MUST read before setting SEO fields (Phase 7)
- `post-types-and-fields` — MUST read before creating CPTs (Phase 5)
- `accessibility` — SHOULD read before creating content (Phase 7)

## Critical Rules

1. **Never execute without confirmation.** Each phase ends with a summary and user approval.
2. **Read guides before content creation.** Always call `klytos_get_guide('gutenberg-blocks')` and `klytos_get_guide('seo-content')` before Phase 7.
3. **Adaptive flow.** Skip irrelevant phases (e.g., no CPTs for a landing page).
4. **Indexing OFF during build.** Keep `indexing_enabled: false` until Phase 9 launch.
5. **All pages start as draft.** Only publish/build in Phase 9.

## Setup Wizard Integration

The setup wizard (`installer/admin/setup-wizard.php`) shows a copiable prompt at Step 5 (Congratulations) that directs the AI to call `klytos_start_site_builder`. The prompt includes the site name, URL, and MCP endpoint.
