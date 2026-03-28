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
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

use Klytos\Core\Helpers;
use Klytos\Core\Hooks;

$adminPath   = Helpers::getBasePath() . 'admin/';
$currentPage = basename($_SERVER['SCRIPT_NAME'], '.php');

// ─── Build the sidebar menu items ────────────────────────────
// Core items are defined here. Plugins can add/modify items
// via the 'admin.sidebar_items' filter.

$sidebarItems = [
    // ── Content section ──
    [
        'id'         => 'dashboard',
        'title'      => __( 'dashboard.title' ),
        'url'        => $adminPath,
        'icon'       => 'D',
        'position'   => 10,
        'section'    => 'content',
        'capability' => null, // Visible to all authenticated users.
    ],
    [
        'id'         => 'pages',
        'title'      => __( 'pages.title' ),
        'url'        => $adminPath . 'pages.php',
        'icon'       => 'P',
        'position'   => 20,
        'section'    => 'content',
        'capability' => 'pages.view',
    ],
    [
        'id'         => 'theme',
        'title'      => __( 'design.title' ),
        'url'        => $adminPath . 'theme.php',
        'icon'       => 'D',
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
        ],
    ],
    [
        'id'         => 'assets',
        'title'      => __( 'assets.title' ),
        'url'        => $adminPath . 'assets.php',
        'icon'       => 'F',
        'position'   => 40,
        'section'    => 'content',
        'capability' => 'assets.manage',
    ],
    [
        'id'         => 'ai-images',
        'title'      => __( 'ai_images.title' ),
        'url'        => $adminPath . 'ai-images.php',
        'icon'       => 'I',
        'position'   => 50,
        'section'    => 'content',
        'capability' => 'assets.manage',
    ],
    [
        'id'         => 'post-types',
        'title'      => 'Post Types',
        'url'        => $adminPath . 'post-types.php',
        'icon'       => 'T',
        'position'   => 22,
        'section'    => 'content',
        'capability' => 'pages.view',
    ],
    [
        'id'         => 'tasks',
        'title'      => 'Tasks',
        'url'        => $adminPath . 'tasks.php',
        'icon'       => 'K',
        'position'   => 55,
        'section'    => 'content',
        'capability' => 'tasks.create',
    ],
    [
        'id'         => 'analytics',
        'title'      => 'Analytics',
        'url'        => $adminPath . 'analytics.php',
        'icon'       => 'A',
        'position'   => 60,
        'section'    => 'content',
        'capability' => 'analytics.view',
    ],

    // ── System section ──
    [
        'id'         => 'webhooks',
        'title'      => 'Webhooks',
        'url'        => $adminPath . 'webhooks.php',
        'icon'       => 'W',
        'position'   => 65,
        'section'    => 'system',
        'capability' => 'webhooks.manage',
    ],
    [
        'id'         => 'users',
        'title'      => 'Users',
        'url'        => $adminPath . 'users.php',
        'icon'       => 'U',
        'position'   => 70,
        'section'    => 'system',
        'capability' => 'users.manage',
    ],
    [
        'id'         => 'mcp',
        'title'      => __( 'mcp.title' ),
        'url'        => $adminPath . 'mcp.php',
        'icon'       => 'M',
        'position'   => 75,
        'section'    => 'system',
        'capability' => 'mcp.manage',
    ],
    [
        'id'         => 'security',
        'title'      => __( 'security.title' ),
        'url'        => $adminPath . 'security.php',
        'icon'       => 'L',
        'position'   => 78,
        'section'    => 'system',
        'capability' => null, // Visible to all authenticated users (each manages their own 2FA).
    ],
    [
        'id'         => 'settings',
        'title'      => __( 'settings.title' ),
        'url'        => $adminPath . 'settings.php',
        'icon'       => 'S',
        'position'   => 80,
        'section'    => 'system',
        'capability' => 'site.configure',
    ],
    [
        'id'         => 'plugins',
        'title'      => 'Plugins',
        'url'        => $adminPath . 'plugins.php',
        'icon'       => 'X',
        'position'   => 90,
        'section'    => 'system',
        'capability' => 'plugins.manage',
    ],
    [
        'id'         => 'updates',
        'title'      => __( 'updates.title' ),
        'url'        => $adminPath . 'updates.php',
        'icon'       => 'U',
        'position'   => 98,
        'section'    => 'system',
        'capability' => 'updates.manage',
    ],
];

// Hook: allow plugins to add, remove, or modify sidebar items.
$sidebarItems = Hooks::applyFilters('admin.sidebar_items', $sidebarItems);

// Sort items by position (lower = higher in the menu).
usort($sidebarItems, fn(array $a, array $b): int => ($a['position'] ?? 99) <=> ($b['position'] ?? 99));

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
<aside class="admin-sidebar" id="sidebar">
    <div class="sidebar-brand">
        <h2>Klytos</h2>
        <small>v<?php echo htmlspecialchars( $app->getVersion()); ?></small>
    </div>

    <nav class="sidebar-nav">
        <?php if (!empty($sections['content'])): ?>
            <div class="sidebar-section"><?php echo __( 'common.name' ); ?></div>
            <?php foreach ($sections['content'] as $item): ?>
                <?php
                $isParentActive = $currentPage === $item['id'];
                $hasChildren    = !empty( $item['children'] );
                if ($hasChildren) {
                    foreach ($item['children'] as $child) {
                        if ($currentPage === $child['id']) {
                            $isParentActive = true;
                        }
                    }
                }
                ?>
                <a href="<?php echo htmlspecialchars( $item['url'] ); ?>"
                   class="<?php echo $isParentActive ? 'active' : ''; ?>">
                    <span>[<?php echo htmlspecialchars( $item['icon'] ?? '?' ); ?>]</span>
                    <?php echo htmlspecialchars( $item['title'] ); ?>
                    <?php if (!empty( $item['badge'] )): ?>
                        <span class="badge"><?php echo htmlspecialchars( (string) $item['badge'] ); ?></span>
                    <?php endif; ?>
                </a>
                <?php if ($hasChildren && $isParentActive): ?>
                    <?php foreach ($item['children'] as $child): ?>
                        <a href="<?php echo htmlspecialchars( $child['url'] ); ?>"
                           class="sidebar-child <?php echo $currentPage === $child['id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars( $child['title'] ); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($sections['system'])): ?>
            <div class="sidebar-section">System</div>
            <?php foreach ($sections['system'] as $item): ?>
                <?php
                $isParentActive = $currentPage === $item['id'];
                $hasChildren    = !empty( $item['children'] );
                if ($hasChildren) {
                    foreach ($item['children'] as $child) {
                        if ($currentPage === $child['id']) {
                            $isParentActive = true;
                        }
                    }
                }
                ?>
                <a href="<?php echo htmlspecialchars( $item['url'] ); ?>"
                   class="<?php echo $isParentActive ? 'active' : ''; ?>">
                    <span>[<?php echo htmlspecialchars( $item['icon'] ?? '?' ); ?>]</span>
                    <?php echo htmlspecialchars( $item['title'] ); ?>
                    <?php if (!empty( $item['badge'] )): ?>
                        <span class="badge"><?php echo htmlspecialchars( (string) $item['badge'] ); ?></span>
                    <?php endif; ?>
                </a>
                <?php if ($hasChildren && $isParentActive): ?>
                    <?php foreach ($item['children'] as $child): ?>
                        <a href="<?php echo htmlspecialchars( $child['url'] ); ?>"
                           class="sidebar-child <?php echo $currentPage === $child['id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars( $child['title'] ); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php
        // Render any additional custom sections added by plugins.
        foreach ($sections as $sectionName => $items):
            if ($sectionName === 'content' || $sectionName === 'system') continue;
        ?>
            <div class="sidebar-section"><?php echo htmlspecialchars(ucfirst($sectionName)); ?></div>
            <?php foreach ($items as $item): ?>
                <a href="<?php echo htmlspecialchars( $item['url'] ); ?>"
                   class="<?php echo $currentPage === $item['id'] ? 'active' : ''; ?>">
                    <span>[<?php echo htmlspecialchars( $item['icon'] ?? '?'); ?>]</span>
                    <?php echo htmlspecialchars( $item['title'] ); ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</aside>

<div class="admin-content">
    <div class="admin-topbar">
        <div>
            <strong><?php echo htmlspecialchars( $pageTitle ?? ''); ?></strong>
        </div>
        <div style="display:flex;align-items:center;gap:1rem;">
            <span style="font-size:0.85rem;color:var(--admin-text-muted);">
                <?php echo htmlspecialchars( $app->getAuth()->getUsername()); ?>
            </span>
            <a href="<?php echo $adminPath; ?>logout.php" class="btn btn-outline btn-sm">
                <?php echo __( 'auth.logout' ); ?>
            </a>
        </div>
    </div>
    <div class="admin-main">
