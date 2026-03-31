<?php

/**
 * Klytos — AI Panel: Dashboard
 * Dashboard content rendered inside the AI chat interface.
 *
 * @package Klytos
 * @since   0.9.0
 */

if (!isset($app)) {
    return;
}

$siteConfig = $app->getSiteConfig()->get();
$pageCount  = $app->getPages()->count('all');
$published  = $app->getPages()->count('published');
$drafts     = $app->getPages()->count('draft');
$tokens     = $app->getAuth()->listBearerTokens();
$lastBuild  = $siteConfig['last_build'] ?? null;
?>

<div class="ai-chat-panel">
    <div class="ai-chat-panel-sidebar">
        <a href="<?php echo klytos_esc_url($basePath . 'admin/ai-chat.php'); ?>" class="ai-chat-panel-back">
            <i class="fa-solid fa-chevron-left"></i> <?php echo klytos_esc_html(__('ai_chat.chats')); ?>
        </a>
        <div class="ai-chat-panel-title"><?php echo klytos_esc_html(__('ai_chat.dashboard')); ?></div>
    </div>
    <div class="ai-chat-panel-content">

        <div class="ai-panel-stats">
            <div class="ai-panel-stat">
                <div class="ai-panel-stat-label"><?php echo klytos_esc_html(__('dashboard.total_pages')); ?></div>
                <div class="ai-panel-stat-value"><?php echo $pageCount; ?></div>
                <div class="ai-panel-stat-detail"><?php echo $published; ?> <?php echo klytos_esc_html(__('pages.published')); ?> / <?php echo $drafts; ?> <?php echo klytos_esc_html(__('pages.draft')); ?></div>
            </div>
            <div class="ai-panel-stat">
                <div class="ai-panel-stat-label"><?php echo klytos_esc_html(__('dashboard.last_build')); ?></div>
                <div class="ai-panel-stat-value" style="font-size:1rem;"><?php echo $lastBuild ? date('Y-m-d H:i', strtotime($lastBuild)) : '—'; ?></div>
                <div class="ai-panel-stat-detail"><?php echo $lastBuild ? 'UTC' : klytos_esc_html(__('pages.no_pages')); ?></div>
            </div>
            <div class="ai-panel-stat">
                <div class="ai-panel-stat-label"><?php echo klytos_esc_html(__('dashboard.mcp_status')); ?></div>
                <div class="ai-panel-stat-value" style="font-size:1rem;">
                    <span class="ai-panel-badge ai-panel-badge-<?php echo count($tokens) > 0 ? 'active' : 'inactive'; ?>">
                        <?php echo count($tokens) > 0 ? klytos_esc_html(__('common.status')) . ': OK' : klytos_esc_html(__('mcp.no_tokens')); ?>
                    </span>
                </div>
                <div class="ai-panel-stat-detail"><?php echo count($tokens); ?> <?php echo klytos_esc_html(__('mcp.tokens')); ?></div>
            </div>
            <div class="ai-panel-stat">
                <div class="ai-panel-stat-label"><?php echo klytos_esc_html(__('dashboard.klytos_version')); ?></div>
                <div class="ai-panel-stat-value" style="font-size:1rem;">v<?php echo klytos_esc_html($app->getVersion()); ?></div>
                <div class="ai-panel-stat-detail">PHP <?php echo PHP_VERSION; ?></div>
            </div>
        </div>

        <h3><?php echo klytos_esc_html(__('dashboard.system_info')); ?></h3>
        <div class="ai-panel-card">
            <table class="ai-panel-table">
                <tr><td style="font-weight:600;"><?php echo klytos_esc_html(__('dashboard.klytos_version')); ?></td><td><?php echo klytos_esc_html($app->getVersion()); ?></td></tr>
                <tr><td style="font-weight:600;"><?php echo klytos_esc_html(__('dashboard.php_version')); ?></td><td><?php echo PHP_VERSION; ?></td></tr>
                <tr><td style="font-weight:600;">Server</td><td><?php echo klytos_esc_html($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></td></tr>
            </table>
        </div>

    </div>
</div>
