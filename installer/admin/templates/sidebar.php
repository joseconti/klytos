<?php

/**
 * Klytos Admin — Sidebar Template
 * Renders the admin navigation sidebar.
 *
 * Plugins can add their own menu items via the 'admin.sidebar_items' filter.
 * Each item must have: id, title, url, icon, position, capability.
 *
 * Standard positions:
 *   10 = Dashboard, 20 = Pages, 30 = Theme, 40 = Assets, 50 = AI Images,
 *   75 = MCP, 80 = Settings, 85-89 = Plugin items, 90 = Plugins, 98 = Updates.
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

use Klytos\Core\Helpers;

$adminPath   = Helpers::getBasePath() . 'admin/';
$currentPage = $currentPage ?? basename($_SERVER['SCRIPT_NAME'], '.php');
$GLOBALS['klytos_admin_page'] = $currentPage;

// Resolve the effective sidebar item ID for the current page.
// Custom post type pages use a shared PHP file with query params,
// so we need to map them back to their sidebar item IDs.
// Use the raw script name because some pages override $currentPage.
$scriptName    = basename($_SERVER['SCRIPT_NAME'], '.php');
$currentItemId = $currentPage;
if ($scriptName === 'post-type-edit' && isset($_GET['id'])) {
    $currentItemId = 'pt-' . $_GET['id'] . '-settings';
}
if ($scriptName === 'taxonomy' && isset($_GET['post_type'], $_GET['taxonomy'])) {
    $currentItemId = 'tax-' . $_GET['post_type'] . '-' . $_GET['taxonomy'];
}
if ($scriptName === 'pages' && !empty($_GET['post_type'])) {
    $currentItemId = 'pt-' . $_GET['post_type'] . '-all';
}
if ($scriptName === 'page-editor' && !empty($_GET['post_type'])) {
    $currentItemId = 'pt-' . $_GET['post_type'] . '-all';
}

// ─── Build the sidebar menu items ────────────────────────────
// Core items are defined here. Plugins can add/modify items
// via the 'admin.sidebar_items' filter.

$sidebarItems = [
    // ── Content section ──
    [
        'id'         => 'dashboard',
        'title'      => __( 'dashboard.title' ),
        'url'        => $adminPath,
        'icon'       => 'fa-solid fa-gauge-high',
        'position'   => 10,
        'section'    => 'content',
        'capability' => null, // Visible to all authenticated users.
    ],
    [
        'id'         => 'pages',
        'title'      => __( 'pages.title' ),
        'url'        => $adminPath . 'pages.php',
        'icon'       => 'fa-solid fa-file-lines',
        'position'   => 20,
        'section'    => 'content',
        'capability' => 'pages.view',
    ],
    [
        'id'         => 'theme',
        'title'      => __( 'design.title' ),
        'url'        => $adminPath . 'theme.php',
        'icon'       => 'fa-solid fa-palette',
        'position'   => 30,
        'section'    => 'content',
        'capability' => 'theme.manage',
        'children'   => [
            [
                'id'    => 'theme-visual',
                'title' => __( 'design.theme' ),
                'url'   => $adminPath . 'theme.php',
            ],
            [
                'id'    => 'templates',
                'title' => __( 'design.templates' ),
                'url'   => $adminPath . 'templates.php',
            ],
            [
                'id'    => 'blocks',
                'title' => __( 'design.blocks' ),
                'url'   => $adminPath . 'blocks.php',
            ],
        ],
    ],
    [
        'id'         => 'assets',
        'title'      => __( 'assets.title' ),
        'url'        => $adminPath . 'assets.php',
        'icon'       => 'fa-solid fa-folder-open',
        'position'   => 40,
        'section'    => 'content',
        'capability' => 'assets.manage',
    ],
    [
        'id'         => 'ai-images',
        'title'      => __( 'ai_images.title' ),
        'url'        => $adminPath . 'ai-images.php',
        'icon'       => 'fa-solid fa-wand-magic-sparkles',
        'position'   => 50,
        'section'    => 'content',
        'capability' => 'assets.manage',
    ],
    [
        'id'         => 'post-types',
        'title'      => 'Post Types',
        'url'        => $adminPath . 'post-types.php',
        'icon'       => 'fa-solid fa-layer-group',
        'position'   => 85,
        'section'    => 'system',
        'capability' => 'site.configure',
    ],
    [
        'id'         => 'tasks',
        'title'      => 'Tasks',
        'url'        => $adminPath . 'tasks.php',
        'icon'       => 'fa-solid fa-list-check',
        'position'   => 55,
        'section'    => 'content',
        'capability' => 'tasks.create',
    ],
    [
        'id'         => 'analytics',
        'title'      => 'Analytics',
        'url'        => $adminPath . 'analytics.php',
        'icon'       => 'fa-solid fa-chart-line',
        'position'   => 60,
        'section'    => 'content',
        'capability' => 'analytics.view',
    ],

    // ── System section ──
    [
        'id'         => 'scheduled-actions',
        'title'      => 'Scheduled Actions',
        'url'        => $adminPath . 'scheduled-actions.php',
        'icon'       => 'fa-solid fa-clock',
        'position'   => 63,
        'section'    => 'system',
        'capability' => 'site.configure',
    ],
    [
        'id'         => 'webhooks',
        'title'      => 'Webhooks',
        'url'        => $adminPath . 'webhooks.php',
        'icon'       => 'fa-solid fa-bolt',
        'position'   => 65,
        'section'    => 'system',
        'capability' => 'webhooks.manage',
    ],
    [
        'id'         => 'consent',
        'title'      => 'Consent Manager',
        'url'        => $adminPath . 'consent.php',
        'icon'       => 'fa-solid fa-cookie-bite',
        'position'   => 66,
        'section'    => 'system',
        'capability' => 'site.configure',
    ],
    [
        'id'         => 'privacy',
        'title'      => __( 'privacy.title' ),
        'url'        => $adminPath . 'privacy.php',
        'icon'       => 'fa-solid fa-user-shield',
        'position'   => 67,
        'section'    => 'system',
        'capability' => 'users.manage',
    ],
    [
        'id'         => 'users',
        'title'      => 'Users',
        'url'        => $adminPath . 'users.php',
        'icon'       => 'fa-solid fa-users',
        'position'   => 70,
        'section'    => 'system',
        'capability' => 'users.manage',
    ],
    [
        'id'         => 'mcp',
        'title'      => __( 'mcp.title' ),
        'url'        => $adminPath . 'mcp.php',
        'icon'       => 'fa-solid fa-robot',
        'position'   => 75,
        'section'    => 'system',
        'capability' => 'mcp.manage',
    ],
    [
        'id'         => 'security',
        'title'      => __( 'security.title' ),
        'url'        => $adminPath . 'security.php',
        'icon'       => 'fa-solid fa-shield-halved',
        'position'   => 78,
        'section'    => 'system',
        'capability' => null, // Visible to all authenticated users (each manages their own 2FA).
    ],
    [
        'id'         => 'system-integrity',
        'title'      => 'Integrity',
        'url'        => $adminPath . 'system-integrity.php',
        'icon'       => 'fa-solid fa-file-shield',
        'position'   => 78.5,
        'section'    => 'system',
        'capability' => 'site.configure',
    ],
    [
        'id'         => 'logs',
        'title'      => __( 'logs.title' ),
        'url'        => $adminPath . 'logs.php',
        'icon'       => 'fa-solid fa-scroll',
        'position'   => 79,
        'section'    => 'system',
        'capability' => 'site.configure',
    ],
    [
        'id'         => 'settings',
        'title'      => __( 'settings.title' ),
        'url'        => $adminPath . 'settings.php',
        'icon'       => 'fa-solid fa-gear',
        'position'   => 80,
        'section'    => 'system',
        'capability' => 'site.configure',
    ],
    [
        'id'         => 'system-options',
        'title'      => __( 'options.title' ),
        'url'        => $adminPath . 'system-options.php',
        'icon'       => 'fa-solid fa-sliders',
        'position'   => 81,
        'section'    => 'system',
        'capability' => 'site.configure',
    ],
    [
        'id'         => 'translations',
        'title'      => __( 'translations.title' ),
        'url'        => $adminPath . 'translations.php',
        'icon'       => 'fa-solid fa-language',
        'position'   => 82,
        'section'    => 'system',
        'capability' => 'site.configure',
    ],
    [
        'id'         => 'plugins',
        'title'      => 'Plugins',
        'url'        => $adminPath . 'plugins.php',
        'icon'       => 'fa-solid fa-puzzle-piece',
        'position'   => 90,
        'section'    => 'system',
        'capability' => 'plugins.manage',
    ],
    [
        'id'         => 'updates',
        'title'      => __( 'updates.title' ),
        'url'        => $adminPath . 'updates.php',
        'icon'       => 'fa-solid fa-cloud-arrow-down',
        'position'   => 98,
        'section'    => 'system',
        'capability' => 'updates.manage',
    ],
];

// Terminal: visible for owners. 2FA is checked on the page itself.
$sidebarItems[] = [
    'id'         => 'terminal',
    'title'      => 'Terminal',
    'url'        => $adminPath . 'terminal.php',
    'icon'       => 'fa-solid fa-terminal',
    'position'   => 95,
    'section'    => 'system',
    'capability' => 'terminal.access',
];

// Dynamic: add custom post types to the sidebar menu.
// Each custom post type gets its own menu item with taxonomy children.
try {
    $ptMenuItems = $app->getPostTypeManager()->getMenuItems();
    $ptPosition  = 23; // Start after "Post Types" (22).
    foreach ($ptMenuItems as $ptItem) {
        $children = [];
        // "All items" links to content listing filtered by post type.
        $children[] = [
            'id'    => 'pt-' . $ptItem['id'] . '-all',
            'title' => 'All ' . $ptItem['name'],
            'url'   => $adminPath . 'pages.php?post_type=' . urlencode($ptItem['id']),
        ];
        foreach ($ptItem['taxonomies'] as $tax) {
            $children[] = [
                'id'    => 'tax-' . $ptItem['id'] . '-' . $tax['id'],
                'title' => $tax['name'],
                'url'   => $adminPath . 'taxonomy.php?post_type=' . urlencode($ptItem['id']) . '&taxonomy=' . urlencode($tax['id']),
            ];
        }
        $children[] = [
            'id'    => 'pt-' . $ptItem['id'] . '-settings',
            'title' => 'Settings',
            'url'   => $adminPath . 'post-type-edit.php?id=' . urlencode($ptItem['id']),
        ];
        $sidebarItems[] = [
            'id'         => 'pt-' . $ptItem['id'],
            'title'      => $ptItem['name'],
            'url'        => $adminPath . 'pages.php?post_type=' . urlencode($ptItem['id']),
            'icon'       => $ptItem['icon'] ?? 'fa-solid fa-newspaper',
            'position'   => $ptPosition++,
            'section'    => 'content',
            'capability' => 'pages.view',
            'children'   => $children,
        ];
    }
} catch (\Throwable $e) {
    // Silently skip if PostTypeManager not available yet.
}

// Hook: allow plugins to add, remove, or modify sidebar items.
$sidebarItems = klytos_apply_filters('admin.sidebar_items', $sidebarItems);

// Sort items by position (lower = higher in the menu).
usort($sidebarItems, fn(array $a, array $b): int => ($a['position'] ?? 99) <=> ($b['position'] ?? 99));

// ─── Apply per-user custom sidebar order (if any) ────────────
$customOrder      = null;
$userSectionOrder = null;
try {
    $currentUserId = $app->getAuth()->getUserId();
    if ( $currentUserId ) {
        $customOrder = $app->getMetaManager()->get( 'users', $currentUserId, 'klytos.sidebar_order' );
    }
} catch ( \Throwable $e ) {
    // Silently ignore — use default order.
}

if ( ! empty( $customOrder ) && is_array( $customOrder ) ) {
    $itemsById = [];
    foreach ( $sidebarItems as $item ) {
        $itemsById[$item['id']] = $item;
    }

    $reordered    = [];
    $itemOrderMap = $customOrder['items'] ?? [];

    foreach ( $itemOrderMap as $section => $orderedIds ) {
        if ( ! is_array( $orderedIds ) ) {
            continue;
        }
        $position = 1;
        foreach ( $orderedIds as $id ) {
            if ( isset( $itemsById[$id] ) ) {
                $itemsById[$id]['section']  = $section;
                $itemsById[$id]['position'] = $position++;
                $reordered[] = $itemsById[$id];
                unset( $itemsById[$id] );
            }
        }
    }

    // Append any remaining items not in the custom order (e.g., new plugin items).
    foreach ( $itemsById as $item ) {
        $reordered[] = $item;
    }

    $sidebarItems = $reordered;
    usort( $sidebarItems, fn( array $a, array $b ): int => ( $a['position'] ?? 99 ) <=> ( $b['position'] ?? 99 ) );
    $userSectionOrder = $customOrder['sections'] ?? null;
}

// Group items by section for rendering.
$sections = [];
foreach ($sidebarItems as $item) {
    $section = $item['section'] ?? 'system';

    // Check capability (if set). Skip items the user can't access.
    if (!empty($item['capability']) && function_exists('klytos_has_permission')) {
        if (!klytos_has_permission($item['capability'])) {
            continue;
        }
    }

    $sections[$section][] = $item;
}
?>
<?php
if ( ! function_exists( 'klytos_render_sidebar_item' ) ) {
/**
 * Renders a single sidebar item with tooltip wrapper for collapsed mode.
 *
 * @param array  $item          The sidebar menu item data.
 * @param string $currentItemId The current page item ID for active state.
 */
    function klytos_render_sidebar_item( array $item, string $currentItemId ): void
    {
        $isParentActive = $currentItemId === $item['id'];
        $hasChildren    = !empty($item['children']);
        if ($hasChildren) {
            foreach ($item['children'] as $child) {
                if ($currentItemId === $child['id']) {
                    $isParentActive = true;
                }
            }
        }
        ?>
    <div class="sidebar-item-wrap" data-item-id="<?php echo klytos_esc_attr( $item['id'] ); ?>">
        <a href="<?php echo klytos_esc_url($item['url']); ?>"
           class="<?php echo $isParentActive ? 'active' : ''; ?>">
            <span class="sidebar-item-drag-handle" aria-hidden="true">&#x2807;</span>
            <i class="<?php echo klytos_esc_attr($item['icon'] ?? 'fa-solid fa-circle'); ?>"></i>
            <span class="sidebar-label"><?php echo klytos_esc_html($item['title']); ?></span>
            <?php if (!empty($item['badge'])): ?>
                <span class="badge"><?php echo klytos_esc_html((string) $item['badge']); ?></span>
            <?php endif; ?>
        </a>
        <?php // Tooltip for collapsed state ?>
        <div class="sidebar-tooltip">
            <a href="<?php echo klytos_esc_url($item['url']); ?>" class="tooltip-title">
                <?php echo klytos_esc_html($item['title']); ?>
            </a>
            <?php if ($hasChildren): ?>
                <?php foreach ($item['children'] as $child): ?>
                    <a href="<?php echo klytos_esc_url($child['url']); ?>" class="tooltip-child">
                        <?php echo klytos_esc_html($child['title']); ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php // Expanded child items (shown when expanded and parent is active) ?>
        <?php if ($hasChildren && $isParentActive): ?>
            <?php foreach ($item['children'] as $child): ?>
                <a href="<?php echo klytos_esc_url($child['url']); ?>"
                   class="sidebar-child <?php echo $currentItemId === $child['id'] ? 'active' : ''; ?>">
                    <?php echo klytos_esc_html($child['title']); ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
        <?php
    }
} // End function_exists.
?>
<?php klytos_do_action('admin.sidebar.before'); ?>
<aside class="admin-sidebar" id="sidebar"
    data-csrf="<?php echo klytos_esc_attr( $app->getAuth()->getCsrfToken() ); ?>"
    data-api-url="<?php echo klytos_esc_url( $adminPath . 'api/sidebar-order.php' ); ?>">
    <div class="sidebar-brand">
        <h2>Klytos</h2>
        <small>v<?php echo klytos_esc_html( $app->getVersion() ); ?></small>
    </div>

<?php klytos_do_action('admin.sidebar.before_search'); ?>
    <div class="sidebar-search" id="sidebarSearch">
        <div class="sidebar-search-wrap">
            <i class="fa-solid fa-magnifying-glass sidebar-search-icon"></i>
            <?php
            $search_placeholder = __( 'common.search' );
            if ( empty( $search_placeholder ) ) {
                $search_placeholder = 'Search';
            }
            ?>
            <input type="text" id="sidebarSearchInput" placeholder="<?php echo klytos_esc_attr( $search_placeholder ); ?>…" autocomplete="off" spellcheck="false">
            <kbd class="sidebar-search-kbd">/</kbd>
        </div>
    </div>
<?php klytos_do_action('admin.sidebar.after_search'); ?>

    <nav class="sidebar-nav">
<?php
// Determine section rendering order.
$sectionOrder = $userSectionOrder ?? ['content', 'system'];
// Append any custom plugin sections not already listed.
foreach ( array_keys( $sections ) as $sName ) {
    if ( ! in_array( $sName, $sectionOrder, true ) ) {
        $sectionOrder[] = $sName;
    }
}
$sectionOrder = klytos_apply_filters( 'admin.sidebar_section_order', $sectionOrder );

$sectionLabels = [
    'content' => __( 'sidebar.section_content' ),
    'system'  => 'System',
];
?>
<?php klytos_do_action( 'admin.sidebar.before_sections' ); ?>
<div class="sidebar-sections-container" id="sidebarSectionsContainer">
<?php foreach ( $sectionOrder as $sectionName ):
    if ( empty( $sections[$sectionName] ) ) {
        continue;
    }
    $sectionLabel = $sectionLabels[$sectionName] ?? ucfirst( $sectionName );
    $sectionLabel = klytos_apply_filters( 'admin.sidebar_section_label', $sectionLabel, $sectionName );
?>
    <div class="sidebar-section-group" data-section="<?php echo klytos_esc_attr( $sectionName ); ?>">
        <?php klytos_do_action( 'admin.sidebar.before_section', $sectionName ); ?>
        <div class="sidebar-section">
            <span class="sidebar-section-drag-handle" aria-hidden="true">&#x2807;</span>
            <span class="sidebar-section-label"><?php echo klytos_esc_html( $sectionLabel ); ?></span>
        </div>
        <div class="sidebar-section-items" data-section="<?php echo klytos_esc_attr( $sectionName ); ?>">
            <?php foreach ( $sections[$sectionName] as $item ): ?>
                <?php klytos_render_sidebar_item( $item, $currentItemId ); ?>
            <?php endforeach; ?>
        </div>
        <?php klytos_do_action( 'admin.sidebar.after_section', $sectionName ); ?>
    </div>
<?php endforeach; ?>
</div>
<?php klytos_do_action( 'admin.sidebar.after_sections' ); ?>
        <div class="sidebar-search-no-results" id="sidebarNoResults">
            <?php echo klytos_esc_html( __( 'common.no_results' ) ); ?>
        </div>
<?php klytos_do_action( 'admin.sidebar.footer' ); ?>
        <div class="sidebar-edit-controls" id="sidebarEditControls">
            <button type="button" class="sidebar-edit-toggle" id="sidebarEditToggle" title="<?php echo klytos_esc_attr( __( 'sidebar.customize' ) ); ?>">
                <i class="fa-solid fa-grip-vertical"></i>
                <span class="sidebar-label"><?php echo klytos_esc_html( __( 'sidebar.customize' ) ); ?></span>
            </button>
            <button type="button" class="sidebar-edit-reset" id="sidebarEditReset" style="display:none;" title="<?php echo klytos_esc_attr( __( 'sidebar.reset_order' ) ); ?>">
                <i class="fa-solid fa-rotate-left"></i>
                <span class="sidebar-label"><?php echo klytos_esc_html( __( 'sidebar.reset_order' ) ); ?></span>
            </button>
        </div>
    </nav>
</aside>
<?php klytos_do_action('admin.sidebar.after'); ?>

<div class="admin-content">
    <?php klytos_do_action('admin.topbar_before'); ?>
    <div class="admin-topbar">
        <div class="topbar-left">
            <button type="button" class="sidebar-toggle" id="sidebarToggle" title="Toggle sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <strong><?php echo klytos_esc_html( $pageTitle ?? '' ); ?></strong>
            <?php echo klytos_apply_filters('admin.topbar_left', ''); ?>
        </div>
        <div class="topbar-center">
            <?php echo klytos_apply_filters('admin.topbar_center', ''); ?>
        </div>
        <div class="topbar-right">
            <?php
            $aiButtonHtml = '<a href="' . klytos_esc_url($adminPath . 'ai-chat.php') . '" class="btn btn-outline btn-sm">'
                          . '<i class="fa-solid fa-robot"></i> '
                          . klytos_esc_html(__( 'ai_chat.ai_mode' ))
                          . '</a>';
            echo klytos_apply_filters('admin.topbar_ai_button', $aiButtonHtml);
            ?>
            <?php echo klytos_apply_filters('admin.topbar_actions', ''); ?>
            <?php
                $currentUser  = klytos_current_user();
                $displayLabel = !empty($currentUser['display_name']) && ($currentUser['display_name'] ?? '') !== ($currentUser['username'] ?? '')
                    ? $currentUser['display_name']
                    : $app->getAuth()->getUsername();
                $displayLabel = klytos_apply_filters('admin.topbar_user_display', $displayLabel, $currentUser);
            ?>
            <a href="<?php echo klytos_esc_url($adminPath . 'profile.php'); ?>" class="text-sm text-muted">
                <?php echo klytos_esc_html( $displayLabel ); ?>
            </a>
            <?php echo klytos_apply_filters('admin.topbar_right', ''); ?>
            <a href="<?php echo $adminPath; ?>logout.php" class="btn btn-outline btn-sm">
                <?php echo __( 'auth.logout' ); ?>
            </a>
        </div>
    </div>
    <?php klytos_do_action('admin.topbar_after'); ?>
    <script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
    (function() {
        var sidebar  = document.getElementById('sidebar');
        var toggle   = document.getElementById('sidebarToggle');
        var content  = sidebar.nextElementSibling;
        var KEY      = 'klytos_sidebar_collapsed';

        function apply(collapsed) {
            if (collapsed) {
                sidebar.classList.add('collapsed');
                content.style.marginLeft = '60px';
            } else {
                sidebar.classList.remove('collapsed');
                content.style.marginLeft = '260px';
            }
        }

        // Restore saved state.
        apply(localStorage.getItem(KEY) === '1');

        toggle.addEventListener('click', function() {
            var collapsed = !sidebar.classList.contains('collapsed');
            apply(collapsed);
            localStorage.setItem(KEY, collapsed ? '1' : '0');
        });

        // Sidebar search filter.
        var searchInput  = document.getElementById('sidebarSearchInput');
        var searchKbd    = sidebar.querySelector('.sidebar-search-kbd');
        var noResults    = document.getElementById('sidebarNoResults');
        var nav          = sidebar.querySelector('.sidebar-nav');
        var allItems     = nav.querySelectorAll('.sidebar-item-wrap');

        function filterSidebar() {
            var query = searchInput.value.trim().toLowerCase();

            if (!query) {
                // Reset: show all, remove search classes.
                allItems.forEach(function(wrap) {
                    wrap.classList.remove('search-hidden', 'search-child-match');
                });
                nav.querySelectorAll('.sidebar-section-group').forEach(function(g) {
                    g.classList.remove('search-hidden');
                });
                noResults.classList.remove('visible');
                return;
            }

            var anyVisible = false;

            allItems.forEach(function(wrap) {
                var mainLink  = wrap.querySelector('a:not(.sidebar-child):not(.tooltip-title):not(.tooltip-child)');
                var label     = mainLink ? (mainLink.querySelector('.sidebar-label') || mainLink).textContent.toLowerCase() : '';
                var children  = wrap.querySelectorAll('a.sidebar-child');
                var childMatch = false;

                // Also check tooltip children (for collapsed items with inactive parents).
                var tooltipChildren = wrap.querySelectorAll('.sidebar-tooltip .tooltip-child');

                children.forEach(function(child) {
                    if (child.textContent.toLowerCase().indexOf(query) !== -1) {
                        childMatch = true;
                    }
                });
                if (!childMatch) {
                    tooltipChildren.forEach(function(tc) {
                        if (tc.textContent.toLowerCase().indexOf(query) !== -1) {
                            childMatch = true;
                        }
                    });
                }

                if (label.indexOf(query) !== -1 || childMatch) {
                    wrap.classList.remove('search-hidden');
                    anyVisible = true;
                    if (childMatch) {
                        wrap.classList.add('search-child-match');
                    } else {
                        wrap.classList.remove('search-child-match');
                    }
                } else {
                    wrap.classList.add('search-hidden');
                    wrap.classList.remove('search-child-match');
                }
            });

            // Hide section groups if all their items are hidden.
            var allGroups = nav.querySelectorAll('.sidebar-section-group');
            allGroups.forEach(function(group) {
                var groupItems = group.querySelectorAll('.sidebar-item-wrap');
                var groupHasVisible = false;
                groupItems.forEach(function(item) {
                    if (!item.classList.contains('search-hidden')) {
                        groupHasVisible = true;
                    }
                });
                if (groupHasVisible) {
                    group.classList.remove('search-hidden');
                } else {
                    group.classList.add('search-hidden');
                }
            });

            if (anyVisible) {
                noResults.classList.remove('visible');
            } else {
                noResults.classList.add('visible');
            }
        }

        searchInput.addEventListener('input', filterSidebar);

        // Hide kbd hint when input is focused/has value.
        searchInput.addEventListener('focus', function() { searchKbd.style.display = 'none'; });
        searchInput.addEventListener('blur', function() {
            if (!searchInput.value) { searchKbd.style.display = ''; }
        });

        // Keyboard shortcut: "/" to focus search, Escape to clear & blur.
        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                var tag = (e.target.tagName || '').toLowerCase();
                if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) return;
                e.preventDefault();
                if (sidebar.classList.contains('collapsed')) {
                    apply(false);
                    localStorage.setItem(KEY, '0');
                }
                searchInput.focus();
            }
            if (e.key === 'Escape' && document.activeElement === searchInput) {
                searchInput.value = '';
                filterSidebar();
                searchInput.blur();
            }
        });

        // Position tooltips vertically when hovering sidebar items.
        var wraps = sidebar.querySelectorAll('.sidebar-item-wrap');
        wraps.forEach(function(wrap) {
            wrap.addEventListener('mouseenter', function() {
                var tooltip = wrap.querySelector('.sidebar-tooltip');
                if (!tooltip) return;
                var rect = wrap.getBoundingClientRect();
                tooltip.style.top = rect.top + 'px';
                // If tooltip goes below viewport, adjust upward.
                requestAnimationFrame(function() {
                    var tRect = tooltip.getBoundingClientRect();
                    if (tRect.bottom > window.innerHeight) {
                        tooltip.style.top = Math.max(0, window.innerHeight - tRect.height) + 'px';
                    }
                });
            });
        });
    })();
    </script>
    <script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>" src="<?php echo klytos_esc_url( $adminPath . 'assets/vendor/sortable/Sortable.min.js' ); ?>"></script>
    <script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>" src="<?php echo klytos_esc_url( $adminPath . 'assets/js/klytos-sidebar-sort.js' ); ?>"></script>
    <div class="admin-main">
<?php klytos_do_action('admin.page.before_content', $currentPage); ?>
