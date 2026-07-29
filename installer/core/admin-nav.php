<?php

/**
 * Klytos Admin — primary navigation model.
 *
 * The normative source for what is IN the admin sidebar is
 * `docs/design/design-handoff/SPEC/navigation.md` (Phase 4, DR-003 / D-074).
 * That file wins over any prototype's `navGroups` array, and this file is its
 * implementation: eight groups in a fixed order, each item's label, glyph,
 * target, count and capability, the plugin placement rule, and the capability
 * rule — which for navigation is HIDDEN, not disabled (navigation.md §7), the
 * opposite of the admin's in-screen rule.
 *
 * Loaded from `App::boot()` beside `admin-gate.php` and for the same reason:
 * the map is readable from every context that needs to reason about it — the
 * test suite and `scripts/keel-verify` consult it without booting an admin
 * request. This file defines functions and returns data; it renders nothing.
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

use Klytos\Core\App;
use Klytos\Core\Helpers;

if ( ! function_exists( 'klytos_admin_nav_group_order' ) ) {
    /**
     * The eight groups, in the fixed order navigation.md §1 declares.
     *
     * The order is not personalisable: a group is never reordered, never
     * collapsed by default and never renamed by a plugin. A group whose items
     * are all hidden by capability renders nothing at all — no caption, no
     * empty list (§1) — which `klytos_admin_nav_groups()` enforces.
     *
     * @return array<int, string> Group ids, top to bottom.
     */
    function klytos_admin_nav_group_order(): array
    {
        return [ 'site', 'content', 'design', 'intelligence', 'monetisation', 'compliance', 'system', 'account' ];
    }
}

if ( ! function_exists( 'klytos_admin_nav_capability_group' ) ) {
    /**
     * Map a plugin's primary capability onto the group that owns it.
     *
     * navigation.md §6. A plugin cannot choose *site* or *account*: Site is the
     * install's own state and Account is the person's, and neither is a
     * plugin's to occupy — so anything unrecognised, or nothing declared at
     * all, lands in *system*.
     *
     * @param  string $capability The plugin's declared primary capability.
     * @return string             One of the group ids in klytos_admin_nav_group_order().
     */
    function klytos_admin_nav_capability_group( string $capability ): string
    {
        $domain = strtolower( trim( $capability ) );
        $dot    = strpos( $domain, '.' );
        if ( $dot !== false ) {
            $domain = substr( $domain, 0, $dot );
        }

        switch ( $domain ) {
            case 'content':
            case 'pages':
                return 'content';
            case 'design':
            case 'theme':
            case 'blocks':
            case 'templates':
                return 'design';
            case 'ai':
            case 'mcp':
                return 'intelligence';
            case 'payments':
            case 'x402':
                return 'monetisation';
            case 'privacy':
            case 'consent':
                return 'compliance';
            default:
                return 'system';
        }
    }
}

if ( ! function_exists( 'klytos_admin_nav_counts' ) ) {
    /**
     * The counts shown to the right of a nav label.
     *
     * A count is a call to action, not a magnitude (navigation.md §2) — which
     * is why Transactions, Logs and Options deliberately carry none. A count of
     * zero is ABSENT, not "0", so this function returns only non-zero values
     * and the renderer prints only what it is given.
     *
     * Every count is computed under the same capability filter as its own
     * screen (§7): the caller filters the item list first and only asks for the
     * counts of items that survived.
     *
     * NOT EVERY COUNT navigation.md SPECIFIES IS WIRED YET, and the unwired
     * ones return nothing rather than a guess — an absent count and a zero
     * count look identical to the reader, so a fabricated zero would be a lie
     * that cannot be seen. What is missing is recorded in `docs/BUILD-SPEC.md`
     * §5.9 with the surface each one needs, not left as an implicit gap.
     *
     * @return array<string, int> Item id => count, non-zero values only.
     */
    function klytos_admin_nav_counts(): array
    {
        $counts = [];
        $app    = App::getInstance();

        // Each source is guarded on its own: a manager that throws must cost
        // its own count, never the whole navigation. The sidebar renders on
        // every authenticated screen, so a fatal here is a fatal everywhere.
        $sources = [
            'pages'         => static fn (): int => (int) $app->getPages()->count( 'all' ),
            'tasks'         => static fn (): int => (int) $app->getTaskManager()->count( 'open' ),
            'assets'        => static fn (): int => count( $app->getAssetManager()->list() ),
            'content-model' => static fn (): int => count( $app->getPostTypeManager()->list() ),
            'blocks'        => static fn (): int => count( $app->getBlockManager()->list() ),
            'templates'     => static fn (): int => count( $app->getPageTemplateManager()->list() ),
            'scheduled'     => static fn (): int => (int) $app->getCronManager()->countActions( [ 'status' => 'failed' ] ),
        ];

        foreach ( $sources as $itemId => $resolve ) {
            try {
                $value = $resolve();
            } catch ( \Throwable $e ) {
                continue;
            }
            if ( $value > 0 ) {
                $counts[ $itemId ] = $value;
            }
        }

        /**
         * Filter: the nav counts, after the core sources have run.
         *
         * The hook a plugin uses to supply a count Klytos does not compute, or
         * to correct one it does. Returning a value of 0 or less removes the
         * count, matching the "a zero count is absent" rule.
         *
         * @param array<string, int> $counts Item id => count.
         */
        $counts = klytos_apply_filters( 'admin.nav_counts', $counts );

        return array_filter(
            is_array( $counts ) ? $counts : [],
            static fn ( $value ): bool => is_int( $value ) && $value > 0
        );
    }
}

if ( ! function_exists( 'klytos_admin_nav_definition' ) ) {
    /**
     * The full item definition, before capability filtering and before counts.
     *
     * One row per item in navigation.md §2, in the order that file lists them.
     * Capabilities are taken from the admin gate map (`admin-gate.php`) rather
     * than restated, so a nav item can never be visible to someone the gate
     * would refuse.
     *
     * `deferred` marks an item that navigation.md specifies but this build does
     * not render yet, because its screen does not exist: D-072 moved Comments
     * (entry 14) and Health (entry 22) into their own Phase 5 slices, and the
     * user's decision on 2026-07-29 was to omit the nav items until then rather
     * than ship a 404 on the primary navigation. They stay HERE, described in
     * full, so restoring each one is deleting a single line rather than
     * rediscovering what it was.
     *
     * @return array<string, array<int, array<string, mixed>>> Group id => items.
     */
    function klytos_admin_nav_definition(): array
    {
        $admin = Helpers::getBasePath() . 'admin/';

        return [
            'site' => [
                // navigation.md §2: "Overview", not "Dashboard" — it is the
                // brand link's target and the word the rest of the admin uses
                // when it sends you back. The <h1> stays "Dashboard".
                [
                    'id'         => 'overview',
                    'label'      => 'nav.item.overview',
                    'glyph'      => 'ks-space_dashboard',
                    'url'        => $admin,
                    'entry'      => 44,
                    // §7: the one item always present for every authenticated
                    // person, so that someone with nothing else can still land
                    // somewhere. Verified against the real matrix rather than
                    // assumed: index.php is gated at pages.view, and pages.view
                    // is held by all four roles, so this is not a widening.
                    'capability' => null,
                ],
                [
                    'id'         => 'analytics',
                    'label'      => 'nav.item.analytics',
                    'glyph'      => 'ks-monitoring',
                    'url'        => $admin . 'analytics.php',
                    'entry'      => 7,
                    'capability' => 'analytics.view',
                ],
                [
                    'id'         => 'tasks',
                    'label'      => 'nav.item.tasks',
                    'glyph'      => 'ks-checklist',
                    'url'        => $admin . 'tasks.php',
                    'entry'      => 13,
                    'capability' => 'tasks.create',
                ],
            ],

            'content' => [
                [
                    'id'         => 'pages',
                    'label'      => 'nav.item.pages',
                    'glyph'      => 'ks-description',
                    'url'        => $admin . 'pages.php',
                    'entry'      => 1,
                    'capability' => 'pages.view',
                ],
                [
                    'id'         => 'comments',
                    'label'      => 'nav.item.comments',
                    'glyph'      => 'ks-forum',
                    'url'        => $admin . 'comments.php',
                    'entry'      => 14,
                    'capability' => 'pages.edit',
                    // D-072 deferred entry 14 out of Phase 4; the screen does
                    // not exist. Rendering it would 404 the primary navigation.
                    'deferred'   => true,
                ],
                [
                    'id'         => 'assets',
                    'label'      => 'nav.item.assets',
                    'glyph'      => 'ks-perm_media',
                    'url'        => $admin . 'assets.php',
                    'entry'      => 4,
                    'capability' => 'assets.manage',
                ],
                [
                    'id'         => 'content-model',
                    'label'      => 'nav.item.content_model',
                    'glyph'      => 'ks-category',
                    'url'        => $admin . 'post-types.php',
                    'entry'      => 19,
                    'capability' => 'site.configure',
                ],
                [
                    'id'         => 'taxonomies',
                    'label'      => 'nav.item.taxonomies',
                    'glyph'      => 'ks-sell',
                    'url'        => $admin . 'taxonomy.php',
                    'entry'      => 32,
                    'capability' => 'pages.edit',
                ],
                [
                    'id'         => 'translations',
                    'label'      => 'nav.item.translations',
                    'glyph'      => 'ks-translate',
                    'url'        => $admin . 'translations.php',
                    'entry'      => 20,
                    'capability' => 'site.configure',
                ],
            ],

            'design' => [
                // §3: Blocks belongs to Design, not Content. Entry 21 is an
                // inventory of registered block TYPES, like Templates and
                // Theme; Content holds records a person authored.
                [
                    'id'         => 'theme',
                    'label'      => 'nav.item.theme',
                    'glyph'      => 'ks-palette',
                    'url'        => $admin . 'theme.php',
                    'entry'      => 3,
                    'capability' => 'theme.manage',
                ],
                [
                    'id'         => 'templates',
                    'label'      => 'nav.item.templates',
                    'glyph'      => 'ks-dashboard_customize',
                    'url'        => $admin . 'templates.php',
                    'entry'      => 31,
                    'capability' => 'templates.manage',
                ],
                [
                    'id'         => 'blocks',
                    'label'      => 'nav.item.blocks',
                    'glyph'      => 'ks-widgets',
                    'url'        => $admin . 'blocks.php',
                    'entry'      => 21,
                    'capability' => 'blocks.manage',
                ],
            ],

            'intelligence' => [
                [
                    'id'         => 'ai-chat',
                    'label'      => 'nav.item.ai_chat',
                    'glyph'      => 'ks-auto_awesome',
                    'url'        => $admin . 'ai-chat.php',
                    'entry'      => 12,
                    'capability' => 'ai.use',
                ],
                [
                    'id'         => 'ai-images',
                    'label'      => 'nav.item.ai_images',
                    'glyph'      => 'ks-imagesmode',
                    'url'        => $admin . 'ai-images.php',
                    'entry'      => 29,
                    'capability' => 'assets.manage',
                ],
                [
                    'id'         => 'mcp',
                    'label'      => 'nav.item.mcp',
                    'glyph'      => 'ks-smart_toy',
                    'url'        => $admin . 'mcp.php',
                    'entry'      => 8,
                    'capability' => 'mcp.manage',
                ],
                [
                    'id'         => 'webhooks',
                    'label'      => 'nav.item.webhooks',
                    'glyph'      => 'ks-webhook',
                    'url'        => $admin . 'webhooks.php',
                    'entry'      => 24,
                    'capability' => 'webhooks.manage',
                ],
            ],

            'monetisation' => [
                // §2: the labels say "agent payments", not "x402" — the
                // protocol belongs in the screens' prose and their <h1>s, not
                // in a nav label a new owner has to decode.
                [
                    'id'         => 'agent-payments',
                    'label'      => 'nav.item.agent_payments',
                    'glyph'      => 'ks-toll',
                    'url'        => $admin . 'x402-dashboard.php',
                    'entry'      => 18,
                    'capability' => 'analytics.view',
                ],
                [
                    'id'         => 'transactions',
                    'label'      => 'nav.item.transactions',
                    'glyph'      => 'ks-receipt_long',
                    'url'        => $admin . 'x402-transactions.php',
                    'entry'      => 36,
                    'capability' => 'analytics.view',
                ],
                [
                    'id'         => 'payment-settings',
                    'label'      => 'nav.item.payment_settings',
                    'glyph'      => 'ks-tune',
                    'url'        => $admin . 'x402-settings.php',
                    'entry'      => 37,
                    'capability' => 'site.configure',
                ],
            ],

            'compliance' => [
                [
                    'id'         => 'consent',
                    'label'      => 'nav.item.consent',
                    'glyph'      => 'ks-cookie',
                    'url'        => $admin . 'consent.php',
                    'entry'      => 25,
                    'capability' => 'site.configure',
                ],
                [
                    'id'         => 'privacy',
                    'label'      => 'nav.item.privacy',
                    'glyph'      => 'ks-policy',
                    'url'        => $admin . 'privacy.php',
                    'entry'      => 26,
                    'capability' => 'users.manage',
                ],
            ],

            'system' => [
                [
                    'id'         => 'users',
                    'label'      => 'nav.item.users',
                    'glyph'      => 'ks-group',
                    'url'        => $admin . 'users.php',
                    'entry'      => 5,
                    'capability' => 'users.manage',
                ],
                [
                    'id'         => 'security',
                    'label'      => 'nav.item.security',
                    'glyph'      => 'ks-shield',
                    'url'        => $admin . 'security.php',
                    'entry'      => 6,
                    'capability' => 'security.self',
                ],
                [
                    'id'         => 'plugins',
                    'label'      => 'nav.item.plugins',
                    'glyph'      => 'ks-extension',
                    'url'        => $admin . 'plugins.php',
                    'entry'      => 15,
                    'capability' => 'plugins.manage',
                ],
                [
                    'id'         => 'updates',
                    'label'      => 'nav.item.updates',
                    'glyph'      => 'ks-system_update_alt',
                    'url'        => $admin . 'updates.php',
                    'entry'      => 35,
                    'capability' => 'updates.manage',
                ],
                [
                    'id'         => 'integrity',
                    'label'      => 'nav.item.integrity',
                    'glyph'      => 'ks-verified_user',
                    'url'        => $admin . 'system-integrity.php',
                    'entry'      => 34,
                    'capability' => 'site.configure',
                ],
                [
                    'id'         => 'health',
                    'label'      => 'nav.item.health',
                    'glyph'      => 'ks-monitor_heart',
                    'url'        => $admin . 'health.php',
                    'entry'      => 22,
                    'capability' => 'site.configure',
                    // D-072 deferred entry 22 out of Phase 4; no backing
                    // surface exists in the tree at all, so its slice builds a
                    // data source before it builds a screen.
                    'deferred'   => true,
                ],
                [
                    'id'         => 'scheduled',
                    'label'      => 'nav.item.scheduled_actions',
                    'glyph'      => 'ks-schedule',
                    'url'        => $admin . 'scheduled-actions.php',
                    'entry'      => 33,
                    'capability' => 'site.configure',
                ],
                [
                    'id'         => 'logs',
                    'label'      => 'nav.item.logs',
                    'glyph'      => 'ks-format_align_left',
                    'url'        => $admin . 'logs.php',
                    'entry'      => 41,
                    'capability' => 'site.configure',
                ],
                [
                    'id'         => 'terminal',
                    'label'      => 'nav.item.terminal',
                    'glyph'      => 'ks-terminal',
                    'url'        => $admin . 'terminal.php',
                    'entry'      => 23,
                    'capability' => 'terminal.access',
                ],
                [
                    'id'         => 'options',
                    'label'      => 'nav.item.options',
                    'glyph'      => 'ks-data_object',
                    'url'        => $admin . 'system-options.php',
                    'entry'      => 30,
                    'capability' => 'site.configure',
                ],
                // §2: Settings is always last — it is where you go when nothing
                // more specific fits, so the bottom of the group is where that
                // reading is natural.
                [
                    'id'         => 'settings',
                    'label'      => 'nav.item.settings',
                    'glyph'      => 'ks-tune',
                    'url'        => $admin . 'settings.php',
                    'entry'      => 9,
                    'capability' => 'site.configure',
                ],
            ],

            'account' => [
                [
                    'id'         => 'profile',
                    'label'      => 'nav.item.profile',
                    'glyph'      => 'ks-account_circle',
                    'url'        => $admin . 'profile.php',
                    'entry'      => 27,
                    'capability' => 'profile.edit',
                ],
                [
                    'id'         => 'licence',
                    'label'      => 'nav.item.licence',
                    'glyph'      => 'ks-workspace_premium',
                    'url'        => $admin . 'license.php',
                    'entry'      => 28,
                    'capability' => 'site.configure',
                ],
            ],
        ];
    }
}

if ( ! function_exists( 'klytos_admin_nav_plugin_items' ) ) {
    /**
     * Plugin-contributed nav items, placed and bounded per navigation.md §6.
     *
     * A plugin contributes ONE item. Its group comes from its declared primary
     * capability; it may not choose *site* or *account*. Plugin items sort
     * after every core item in their group and alphabetically among themselves,
     * with no separator and no visual difference — inside the shell a plugin
     * screen is a screen. Beyond five items in one group, the first five show
     * and a sixth, "More plugins", links to the Plugins screen, which is always
     * the complete list. A plugin item never carries a count.
     *
     * The legacy `admin.sidebar_items` filter is honoured here rather than
     * retired: Klytos is released and plugins already use it. An item that
     * filter contributes and that is not a core id is treated as a plugin item
     * and routed through exactly the same placement rule.
     *
     * @param  array<int, string> $coreGlyphsByGroup Group id => glyphs already used by core items.
     * @return array<string, array<int, array<string, mixed>>> Group id => plugin items.
     */
    function klytos_admin_nav_plugin_items( array $coreGlyphsByGroup = [] ): array
    {
        $admin = Helpers::getBasePath() . 'admin/';

        /**
         * Filter: plugin-contributed primary-navigation items.
         *
         * Each item: id, label (a translation key or literal), glyph (a sprite
         * id), url, and capability — the declared primary capability, which
         * both gates the item and chooses its group.
         *
         * @param array<int, array<string, mixed>> $items
         */
        $items = klytos_apply_filters( 'admin.nav_plugin_items', [] );
        $items = is_array( $items ) ? $items : [];

        // Legacy bridge. `admin.sidebar_items` predates navigation.md and is in
        // use by released plugins, so it keeps firing and keeps meaning what it
        // meant. Core ids are dropped from its result because the core nav is
        // now defined by navigation.md, not by that array.
        $coreIds = [];
        foreach ( klytos_admin_nav_definition() as $groupItems ) {
            foreach ( $groupItems as $item ) {
                $coreIds[ $item['id'] ] = true;
            }
        }
        $legacyCoreIds = [
            'dashboard' => true, 'pages' => true, 'post-types' => true, 'tasks' => true,
            'theme' => true, 'assets' => true, 'ai-images' => true, 'users' => true,
            'consent' => true, 'privacy' => true, 'analytics' => true, 'mcp' => true,
            'webhooks' => true, 'scheduled-actions' => true, 'settings' => true,
            'security' => true, 'system-integrity' => true, 'system-options' => true,
            'translations' => true, 'logs' => true, 'plugins' => true, 'updates' => true,
            'terminal' => true,
        ];

        $legacy = klytos_apply_filters( 'admin.sidebar_items', [] );
        if ( is_array( $legacy ) ) {
            foreach ( $legacy as $legacyItem ) {
                if ( ! is_array( $legacyItem ) || empty( $legacyItem['id'] ) ) {
                    continue;
                }
                $id = (string) $legacyItem['id'];
                if ( isset( $coreIds[ $id ] ) || isset( $legacyCoreIds[ $id ] ) ) {
                    continue;
                }
                // A custom post type registers through this filter today and is
                // a content record type, so it lands in Content regardless of
                // the capability it declares.
                $items[] = [
                    'id'         => $id,
                    'label'      => (string) ( $legacyItem['title'] ?? $id ),
                    'literal'    => true,
                    'glyph'      => '',
                    'url'        => (string) ( $legacyItem['url'] ?? $admin ),
                    'capability' => (string) ( $legacyItem['capability'] ?? '' ),
                ];
            }
        }

        $sprite  = klytos_admin_nav_sprite_ids();
        $grouped = [];

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) || empty( $item['id'] ) || empty( $item['url'] ) ) {
                continue;
            }

            $capability = (string) ( $item['capability'] ?? '' );
            if ( $capability !== '' && function_exists( 'klytos_has_permission' ) && ! klytos_has_permission( $capability ) ) {
                continue;
            }

            $group = klytos_admin_nav_capability_group( $capability );

            // A plugin's glyph is chosen by the plugin from this sprite. If it
            // names a glyph that is not in the sprite, or one already used by a
            // core item in its group, the shell falls back to ks-extension —
            // which is also why a plugin can never blank the icon by omission.
            $glyph = (string) ( $item['glyph'] ?? '' );
            $used  = $coreGlyphsByGroup[ $group ] ?? [];
            if ( $glyph === '' || ! isset( $sprite[ $glyph ] ) || in_array( $glyph, $used, true ) ) {
                $glyph = 'ks-extension';
            }

            $grouped[ $group ][] = [
                'id'      => (string) $item['id'],
                'label'   => (string) $item['label'],
                'literal' => ! empty( $item['literal'] ),
                'glyph'   => $glyph,
                'url'     => (string) $item['url'],
                'plugin'  => true,
            ];
        }

        foreach ( $grouped as $group => $groupItems ) {
            usort(
                $groupItems,
                static fn ( array $a, array $b ): int => strcasecmp( $a['label'], $b['label'] )
            );

            // The sidebar has a bound; the Plugins screen is the complete list.
            if ( count( $groupItems ) > 5 ) {
                $groupItems   = array_slice( $groupItems, 0, 5 );
                $groupItems[] = [
                    'id'      => 'more-plugins-' . $group,
                    'label'   => 'nav.item.more_plugins',
                    'literal' => false,
                    'glyph'   => 'ks-more_horiz',
                    'url'     => $admin . 'plugins.php',
                    'plugin'  => true,
                ];
            }

            $grouped[ $group ] = $groupItems;
        }

        return $grouped;
    }
}

if ( ! function_exists( 'klytos_admin_nav_sprite_ids' ) ) {
    /**
     * Every `<symbol id>` in the delivered icon sprite, as a lookup.
     *
     * Read once per request and cached in a static. A `<use>` pointing at an id
     * the sprite does not contain renders NOTHING — silently, with no console
     * error — which is the defect L-030 records, so a plugin's glyph is checked
     * against this set before it is printed rather than trusted.
     *
     * @return array<string, true> Sprite ids, keyed for isset() lookup.
     */
    function klytos_admin_nav_sprite_ids(): array
    {
        static $ids = null;

        if ( $ids !== null ) {
            return $ids;
        }

        $ids    = [];
        $sprite = dirname( __DIR__ ) . '/admin/assets/icons/klytos-ui-icons.svg';

        if ( is_readable( $sprite ) ) {
            $svg = (string) file_get_contents( $sprite );
            if ( preg_match_all( '/<symbol[^>]+id="([^"]+)"/', $svg, $matches ) ) {
                foreach ( $matches[1] as $id ) {
                    $ids[ $id ] = true;
                }
            }
        }

        return $ids;
    }
}

if ( ! function_exists( 'klytos_admin_nav_groups' ) ) {
    /**
     * The primary navigation, ready to render.
     *
     * Applies, in this order: the deferral filter, the capability filter
     * (server-side, before render — the markup for a hidden item is never
     * sent), plugin placement, the counts, and the empty-group rule.
     *
     * @return array<int, array<string, mixed>> Ordered groups, each with a non-empty `items` list.
     */
    function klytos_admin_nav_groups(): array
    {
        $definition = klytos_admin_nav_definition();
        $counts     = klytos_admin_nav_counts();
        $canCheck   = function_exists( 'klytos_has_permission' );

        $visible          = [];
        $coreGlyphsByGrp  = [];

        foreach ( $definition as $groupId => $items ) {
            foreach ( $items as $item ) {
                if ( ! empty( $item['deferred'] ) ) {
                    continue;
                }

                $capability = $item['capability'] ?? null;
                if ( $capability !== null && $canCheck && ! klytos_has_permission( $capability ) ) {
                    continue;
                }

                if ( isset( $counts[ $item['id'] ] ) ) {
                    $item['count'] = $counts[ $item['id'] ];
                }

                $visible[ $groupId ][]          = $item;
                $coreGlyphsByGrp[ $groupId ][]  = $item['glyph'];
            }
        }

        $pluginItems = klytos_admin_nav_plugin_items( $coreGlyphsByGrp );
        foreach ( $pluginItems as $groupId => $items ) {
            foreach ( $items as $item ) {
                $visible[ $groupId ][] = $item;
            }
        }

        $groups = [];
        foreach ( klytos_admin_nav_group_order() as $groupId ) {
            // §1: a group whose items are all hidden renders nothing at all —
            // no caption, no empty <ul>.
            if ( empty( $visible[ $groupId ] ) ) {
                continue;
            }
            $groups[] = [
                'id'      => $groupId,
                'caption' => 'nav.group.' . $groupId,
                'items'   => $visible[ $groupId ],
            ];
        }

        /**
         * Filter: the finished primary navigation, after capability filtering.
         *
         * The last word on the sidebar's contents. An item added here bypasses
         * the placement and capability rules above, so a plugin that wants
         * those applied should use `admin.nav_plugin_items` instead.
         *
         * @param array<int, array<string, mixed>> $groups Ordered groups.
         */
        $groups = klytos_apply_filters( 'admin.nav_groups', $groups );

        return is_array( $groups ) ? $groups : [];
    }
}
