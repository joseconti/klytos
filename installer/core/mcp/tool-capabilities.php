<?php

/**
 * Klytos — Central MCP tool→capability map (Sprint 2, slice 2 / audit NEW-02).
 *
 * NEW-02 recorded that across installer/core/mcp/tools/ (33 loaded files, 169
 * live tools) klytos_has_permission appears ZERO times: MCP authentication
 * proves WHO the caller is and never WHAT they may do, so any credential holder
 * has owner-equivalent power, including destructive tools. This file closes that
 * the same way admin-gate.php closed S-07 for the admin panel: with data, not
 * scattered checks.
 *
 * The map is deliberately central and default-deny. A per-tool annotation has
 * the S-07 failure mode one file later — a new tool file defaults to *ungated*.
 * A central map where OMISSION denies inverts it: a new tool is refused until it
 * is mapped deliberately, and scripts/keel-verify (check 10) fails the build
 * when a registered tool name has neither a map entry nor a plugin-declared one.
 *
 * The decision itself still lives in ONE place — UserManager::hasPermission()
 * (S-04). This map only names WHICH capability each tool requires; the gate in
 * ToolRegistry::call() (D-046) asks the matrix, it does not add a second one.
 *
 * @package Klytos
 * @since   0.32.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

/**
 * The capability required by every MCP tool, keyed by tool name.
 *
 * A value that is a capability STRING requires it (the gate passes it to
 * UserManager::hasPermission(), the ONE matrix, S-04). A value of NULL is the
 * audited "no capability required" exception — every null carries the reason it
 * is one, exactly like admin-gate.php. It does NOT mean unauthenticated: the MCP
 * transport authenticates the caller and resolves an actor first (D-047), and
 * the gate still requires a usable role even for a null-mapped tool.
 *
 * A tool ABSENT from this map is denied. That is the point of the slice: a new
 * MCP tool is refused until it is mapped deliberately, and check 10 in
 * scripts/keel-verify fails the build when a registered tool has no entry.
 *
 * Read vs write within a domain: a read tool that the content-creation flow
 * REQUIRES of an editor (klytos_create_page's own description tells the model to
 * call klytos_get_post_type / klytos_list_custom_fields / klytos_list_post_statuses
 * before creating an entry) is mapped at 'pages.view' so an editor can make it;
 * a read tool that only concerns site administration follows its domain's manage
 * capability, mirroring the admin gate. Where in doubt the higher (more
 * restrictive) tier is chosen — over-restriction fails safe, under-restriction
 * fails open.
 *
 * Scope note: this covers ONLY the 169 live core tools (the 33 files in
 * ToolRegistry::registerAllTools()). integrity-tools.php (3 tools) is wired in
 * and mapped in slice 3, and the two shipped MCP plugins declare their tools'
 * capabilities through the mcp.tool_capabilities filter in slice 3 — mapping
 * either here now would make keel-verify check 10 fail against what actually
 * registers today.
 *
 * @return array<string, string|null>
 */
function klytos_mcp_tool_capabilities(): array
{
    $map = [
        // ── Pages (page-tools.php) ────────────────────────────────
        // Reads at 'pages.view' (every role); creation/editing at the
        // editor tiers; the whole trash lifecycle (trash, restore,
        // permanent delete, empty) at 'pages.delete' (owner/admin),
        // because undoing or purging a deletion is the same privilege
        // as making one.
        'klytos_create_page'          => 'pages.create',
        'klytos_update_page'          => 'pages.edit',
        'klytos_delete_page'          => 'pages.delete',
        'klytos_restore_page'         => 'pages.delete',
        'klytos_permanent_delete_page' => 'pages.delete',
        'klytos_empty_trash'          => 'pages.delete',
        'klytos_get_page'             => 'pages.view',
        'klytos_list_pages'           => 'pages.view',
        // Editing locks: taking/releasing a lock is an edit-concurrency
        // control (mirrors admin api/post-lock.php → pages.edit); reading
        // the lock state is a view.
        'klytos_lock_page'            => 'pages.edit',
        'klytos_unlock_page'          => 'pages.edit',
        'klytos_check_page_lock'      => 'pages.view',

        // ── Theme (theme-tools.php) ───────────────────────────────
        // Whole domain at theme.manage, mirroring admin theme.php. Reads
        // included: an editor does not need the theme to create content,
        // so nothing in the content flow drops these to pages.view.
        'klytos_set_theme'   => 'theme.manage',
        'klytos_get_theme'   => 'theme.manage',
        'klytos_set_colors'  => 'theme.manage',
        'klytos_set_fonts'   => 'theme.manage',
        'klytos_set_layout'  => 'theme.manage',

        // ── Menus (menu-tools.php) ────────────────────────────────
        'klytos_set_menu'         => 'menu.manage',
        'klytos_get_menu'         => 'menu.manage',
        'klytos_add_menu_item'    => 'menu.manage',
        'klytos_remove_menu_item' => 'menu.manage',

        // ── Site config (site-tools.php) ──────────────────────────
        'klytos_set_site_config' => 'site.configure',
        'klytos_get_site_config' => 'site.configure',

        // ── Media (asset-tools.php) ───────────────────────────────
        // assets.manage is editor+; the whole asset domain (reads,
        // uploads, categories, usage, cleanup, image edit) sits there,
        // mirroring admin assets.php → assets.manage.
        'klytos_upload_asset'            => 'assets.manage',
        'klytos_list_assets'             => 'assets.manage',
        'klytos_delete_asset'            => 'assets.manage',
        'klytos_assets_list_filtered'    => 'assets.manage',
        'klytos_assets_get_usage'        => 'assets.manage',
        'klytos_assets_get_unused'       => 'assets.manage',
        'klytos_assets_update_metadata'  => 'assets.manage',
        'klytos_asset_categories_list'   => 'assets.manage',
        'klytos_asset_categories_create' => 'assets.manage',
        'klytos_assets_sync'             => 'assets.manage',
        'klytos_assets_rebuild_usage'    => 'assets.manage',
        'klytos_assets_cleanup_unused'   => 'assets.manage',
        'klytos_edit_image'              => 'assets.manage',

        // ── Templates & template parts (template-tools.php,
        //    part-tools.php) ──────────────────────────────────────
        // templates.manage (owner/admin). Template parts are shared site
        // chrome (klytos_set_part accepts js/html emitted on every page —
        // D-024's stored-XSS surface), so the owner/admin bar is
        // deliberate. Plugin-asset rebuild/status live in this file and
        // are the same owner/admin operation.
        'klytos_set_template'              => 'templates.manage',
        'klytos_delete_template'           => 'templates.manage',
        'klytos_list_templates'            => 'templates.manage',
        'klytos_get_template'              => 'templates.manage',
        'klytos_resolve_template'          => 'templates.manage',
        'klytos_set_custom_template'       => 'templates.manage',
        'klytos_get_custom_template'       => 'templates.manage',
        'klytos_delete_custom_template'    => 'templates.manage',
        'klytos_list_custom_templates'     => 'templates.manage',
        'klytos_list_template_parts'       => 'templates.manage',
        'klytos_get_template_part'         => 'templates.manage',
        'klytos_set_custom_template_part'  => 'templates.manage',
        'klytos_delete_custom_template_part' => 'templates.manage',
        'klytos_rebuild_plugin_assets'     => 'templates.manage',
        'klytos_get_plugin_assets_status'  => 'templates.manage',
        'klytos_list_parts'                => 'templates.manage',
        'klytos_get_part'                  => 'templates.manage',
        'klytos_set_part'                  => 'templates.manage',
        'klytos_set_part_data'             => 'templates.manage',
        'klytos_delete_part'               => 'templates.manage',
        'klytos_migrate_global_blocks_to_parts' => 'templates.manage',

        // ── Build (build-tools.php) ───────────────────────────────
        // build.run (owner/admin). Preview and status are part of the
        // build pipeline and stay at the same bar.
        'klytos_build_site'       => 'build.run',
        'klytos_build_page'       => 'build.run',
        'klytos_preview_page'     => 'build.run',
        'klytos_get_build_status' => 'build.run',
        'klytos_rebuild_block'    => 'build.run',
        'klytos_rebuild_css'      => 'build.run',

        // ── AI images (ai-image-tools.php) ────────────────────────
        // Generated images are assets; mirrors admin ai-images.php →
        // assets.manage.
        'klytos_generate_image'  => 'assets.manage',
        'klytos_list_ai_images'  => 'assets.manage',

        // ── Users (user-tools.php) ────────────────────────────────
        // users.manage is owner-only. Creating/updating accounts,
        // resetting passwords and forcing logout are all owner privilege.
        'klytos_list_users'          => 'users.manage',
        'klytos_create_user'         => 'users.manage',
        'klytos_update_user'         => 'users.manage',
        'klytos_reset_user_password' => 'users.manage',
        'klytos_force_logout_user'   => 'users.manage',

        // ── Tasks (task-tools.php) ────────────────────────────────
        // The list/create floor is tasks.create (editor+), mirroring
        // admin tasks.php. Updating and completing are tasks.manage
        // (owner/admin): the admin page re-gates those at tasks.manage
        // (tasks.php:38, audit S-06), and MCP cannot establish task
        // ownership — a bearer token has no user at all — so the higher,
        // fail-closed bar is used rather than granting editors blanket
        // edit/complete over everyone's tasks.
        'klytos_list_tasks'    => 'tasks.create',
        'klytos_get_task'      => 'tasks.create',
        'klytos_create_task'   => 'tasks.create',
        'klytos_update_task'   => 'tasks.manage',
        'klytos_complete_task' => 'tasks.manage',

        // ── Versions (version-tools.php) ──────────────────────────
        // Page revisions: reading/diffing is a view; restoring a version
        // rewrites the page, so it is pages.edit.
        'klytos_list_versions'   => 'pages.view',
        'klytos_get_version'     => 'pages.view',
        'klytos_diff_versions'   => 'pages.view',
        'klytos_restore_version' => 'pages.edit',

        // ── Blocks (block-tools.php) ──────────────────────────────
        // blocks.manage (owner/admin), mirroring admin blocks.php /
        // block-data.php.
        'klytos_create_block'          => 'blocks.manage',
        'klytos_update_block'          => 'blocks.manage',
        'klytos_get_block'             => 'blocks.manage',
        'klytos_list_blocks'           => 'blocks.manage',
        'klytos_delete_block'          => 'blocks.manage',
        'klytos_preview_block'         => 'blocks.manage',
        'klytos_set_global_block_data' => 'blocks.manage',
        'klytos_get_block_slots'       => 'blocks.manage',

        // ── Page templates (page-template-tools.php) ──────────────
        // templates.manage, except approval, which the matrix reserves
        // for the owner alone (templates.approve).
        'klytos_create_page_template'       => 'templates.manage',
        'klytos_get_page_template'          => 'templates.manage',
        'klytos_list_page_templates'        => 'templates.manage',
        'klytos_add_block_to_template'      => 'templates.manage',
        'klytos_remove_block_from_template' => 'templates.manage',
        'klytos_reorder_template_blocks'    => 'templates.manage',
        'klytos_approve_page_template'      => 'templates.approve',
        'klytos_preview_page_template'      => 'templates.manage',
        'klytos_get_template_content_schema' => 'templates.manage',

        // ── Analytics (analytics-tools.php) ───────────────────────
        'klytos_get_analytics' => 'analytics.view',
        'klytos_get_top_pages' => 'analytics.view',

        // ── Webhooks (webhook-tools.php) ──────────────────────────
        'klytos_create_webhook'      => 'webhooks.manage',
        'klytos_list_webhooks'       => 'webhooks.manage',
        'klytos_delete_webhook'      => 'webhooks.manage',
        'klytos_list_webhook_events' => 'webhooks.manage',
        'klytos_test_webhook'        => 'webhooks.manage',

        // ── Consent / GDPR (consent-tools.php) ────────────────────
        // Cookie-consent configuration is site administration; mirrors
        // admin consent.php → site.configure.
        'klytos_get_consent_config'         => 'site.configure',
        'klytos_set_consent_config'         => 'site.configure',
        'klytos_list_consent_declarations'  => 'site.configure',
        'klytos_add_consent_declaration'    => 'site.configure',
        'klytos_delete_consent_declaration' => 'site.configure',
        'klytos_get_consent_audit'          => 'site.configure',

        // ── Scheduled actions (scheduler-tools.php) ───────────────
        // Mirrors admin scheduled-actions.php → site.configure.
        'klytos_list_scheduled_actions'     => 'site.configure',
        'klytos_schedule_single_action'     => 'site.configure',
        'klytos_schedule_recurring_action'  => 'site.configure',
        'klytos_cancel_scheduled_action'    => 'site.configure',
        'klytos_get_scheduler_status'       => 'site.configure',

        // ── Plugins (plugin-tools.php) ────────────────────────────
        // plugins.manage is owner-only. Activation/deactivation runs
        // third-party code, so listing sits at the same bar rather than
        // advertising the installed set more widely.
        'klytos_list_plugins'       => 'plugins.manage',
        'klytos_activate_plugin'    => 'plugins.manage',
        'klytos_deactivate_plugin'  => 'plugins.manage',

        // ── Guides (guide-tools.php) ──────────────────────────────
        // NULL — audited no-capability exception. These read the
        // instructional markdown shipped in installer/core/guides/; no
        // user data, config, or secrets, and no mutation. The AI relies
        // on these guides to operate (klytos_create_page's description
        // tells it to read several BEFORE creating content), so any
        // authenticated MCP caller with a usable role may read them —
        // the MCP analogue of admin-gate.php's null entries.
        'klytos_list_guides' => null,
        'klytos_get_guide'   => null,

        // ── Post types & taxonomies (post-type-tools.php) ─────────
        // Defining post types and taxonomies is structural site config
        // (site.configure). But the create-content flow REQUIRES an
        // editor to read the post-type definition and its terms, so the
        // reads drop to pages.view, and managing terms (classifying
        // content) is editor-level pages.edit.
        'klytos_create_post_type' => 'site.configure',
        'klytos_update_post_type' => 'site.configure',
        'klytos_delete_post_type' => 'site.configure',
        'klytos_get_post_type'    => 'pages.view',
        'klytos_list_post_types'  => 'pages.view',
        'klytos_add_taxonomy'     => 'site.configure',
        'klytos_update_taxonomy'  => 'site.configure',
        'klytos_remove_taxonomy'  => 'site.configure',
        'klytos_add_term'         => 'pages.edit',
        'klytos_update_term'      => 'pages.edit',
        'klytos_delete_term'      => 'pages.edit',
        'klytos_list_terms'       => 'pages.view',

        // ── Post statuses (post-status-tools.php) ─────────────────
        // Defining a workflow status is structural (site.configure);
        // listing valid statuses is needed by the editor content flow
        // (klytos_create_page references klytos_list_post_statuses).
        'klytos_add_post_status'    => 'site.configure',
        'klytos_update_post_status' => 'site.configure',
        'klytos_remove_post_status' => 'site.configure',
        'klytos_list_post_statuses' => 'pages.view',

        // ── Custom fields (custom-field-tools.php) ────────────────
        // Defining/reordering fields is structural (site.configure);
        // reading field definitions and values, and setting values on an
        // entry, are content operations the editor flow needs
        // (klytos_create_page references klytos_list_custom_fields and
        // klytos_set_bulk_field_values).
        'klytos_get_field_types'       => 'pages.view',
        'klytos_add_custom_field'      => 'site.configure',
        'klytos_update_custom_field'   => 'site.configure',
        'klytos_remove_custom_field'   => 'site.configure',
        'klytos_get_custom_field'      => 'pages.view',
        'klytos_list_custom_fields'    => 'pages.view',
        'klytos_reorder_custom_fields' => 'site.configure',
        'klytos_set_field_value'       => 'pages.edit',
        'klytos_get_field_value'       => 'pages.view',
        'klytos_get_all_field_values'  => 'pages.view',
        'klytos_set_bulk_field_values' => 'pages.edit',

        // ── Options (option-tools.php) ────────────────────────────
        // Low-level options administration; mirrors admin
        // api/options-management.php → site.configure.
        'klytos_options_list_by_domain' => 'site.configure',
        'klytos_options_classify'       => 'site.configure',
        'klytos_options_delete_domain'  => 'site.configure',
        'klytos_options_migrate'        => 'site.configure',

        // ── AI provider config (ai-tools.php) ─────────────────────
        // AI provider setup and usage live under the MCP/AI settings,
        // gated in admin at mcp.php → mcp.manage; reading which providers
        // and keys are configured is an owner/admin concern.
        'klytos_ai_get_config'    => 'mcp.manage',
        'klytos_ai_list_providers' => 'mcp.manage',
        'klytos_ai_get_usage'     => 'mcp.manage',

        // ── Translations (translation-tools.php) ──────────────────
        // Mirrors admin translations.php / api/translations*.php →
        // site.configure.
        'klytos_list_translation_sources' => 'site.configure',
        'klytos_get_translations'         => 'site.configure',
        'klytos_translate'                => 'site.configure',
        'klytos_translate_with_ai'        => 'site.configure',

        // ── Site builder (site-builder-tools.php) ─────────────────
        // Orchestrates whole-site setup (theme, structure, content).
        'klytos_start_site_builder' => 'site.configure',

        // ── Export (export-tools.php) ─────────────────────────────
        // A full-site data export. Owner/admin (site.configure) — an
        // editor should not egress the entire site.
        'klytos_export_site' => 'site.configure',

        // ── Comments (comment-tools.php) ──────────────────────────
        // The matrix has no comments capability, so comment work maps to
        // the closest existing tiers: reading is a view, moderating is
        // editor-level content work (pages.edit, matching WordPress's
        // moderate_comments being an editor capability), permanent
        // deletion is destructive (pages.delete), and the comment SETTINGS
        // are site configuration.
        'klytos_list_comments'          => 'pages.view',
        'klytos_moderate_comment'       => 'pages.edit',
        'klytos_bulk_moderate_comments' => 'pages.edit',
        'klytos_delete_comment'         => 'pages.delete',
        'klytos_get_comment_settings'   => 'site.configure',
        'klytos_set_comment_settings'   => 'site.configure',

        // ── Site health (site-health-tools.php) ───────────────────
        // Diagnostics expose system internals (versions, config issues) —
        // an owner/admin operations concern.
        'klytos_run_site_health' => 'site.configure',

        // ── Maintenance mode (maintenance-tools.php) ──────────────
        'klytos_set_maintenance_mode' => 'site.configure',
        'klytos_get_maintenance_mode' => 'site.configure',

        // ── Bulk pages (bulk-tools.php) ───────────────────────────
        // klytos_bulk_update_pages can 'trash' and 'delete' permanently,
        // so it is gated at its most destructive action — pages.delete —
        // not at pages.edit. A tool that CAN delete requires the delete
        // capability.
        'klytos_bulk_update_pages' => 'pages.delete',

        // ── Shortcodes (shortcode-tools.php) ──────────────────────
        // Reading the available shortcodes; needed by the editor content
        // flow, so pages.view.
        'klytos_list_shortcodes' => 'pages.view',
    ];

    /**
     * Filter the MCP tool→capability map.
     *
     * The supported way for a plugin to declare capabilities for its own MCP
     * tools (the admin.gate_map precedent, D-032). A plugin that registers tools
     * through mcp.tools_list / mcp.handle_tool adds their entries here so the
     * gate can authorize them; a tool with no entry is DENIED.
     *
     * Note the direction of risk honestly: like admin.gate_map and
     * auth.capabilities this filter CAN weaken a shipped capability, and plugins
     * already run as first-party code in this product. What it cannot do is open
     * a hole by omission — an absent entry denies the tool, it does not allow it.
     *
     * @param array<string, string|null> $map Tool name => capability (or null).
     */
    return klytos_apply_filters( 'mcp.tool_capabilities', $map );
}
