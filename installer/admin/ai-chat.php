<?php

/**
 * Klytos Admin — AI Chat
 * Integrated AI chat for controlling the CMS from the admin panel.
 *
 * @package Klytos
 * @since   0.9.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\Helpers;

$pageTitle = __('ai_chat.title');
$customCsp = null;
$basePath  = Helpers::getBasePath();

// Load AI key manager safely.
$active       = ['provider' => null, 'model' => null];
$allProviders = [];
$hasProvider  = false;

try {
    $keys         = new \Klytos\Core\Ai\AiKeyManager($app->getStorage(), $app->getConfigPath());
    $active       = $keys->getActive();
    $allProviders = $keys->listProviders();
    $hasProvider  = !empty($active['provider']) && $keys->hasKey($active['provider']);
} catch (\Throwable $e) {
    // Fail gracefully — show the chat page without provider.
}

$currentUser  = klytos_current_user();
$username     = (!empty($currentUser['display_name']) && ($currentUser['display_name'] ?? '') !== ($currentUser['username'] ?? ''))
    ? $currentUser['display_name']
    : $app->getAuth()->getUsername();
$userInitial  = mb_strtoupper(mb_substr($username, 0, 1));

// Panel routing (dashboard, settings, users)
$panel = $_GET['panel'] ?? null;
$validPanels = ['dashboard', 'settings', 'users', 'profile'];
if ($panel && !in_array($panel, $validPanels, true)) {
    $panel = null;
}

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo klytos_esc_url($basePath . 'admin/assets/css/ai-chat.css'); ?>">

<?php
    $providerLogos = [
        'anthropic'  => [ 'color' => 'claude-color.webp' ],
        'openai'     => [ 'light' => 'openai-black.webp', 'dark' => 'openai-white.webp' ],
        'gemini'     => [ 'color' => 'gemini-color.webp' ],
        'openrouter' => [ 'light' => 'openrouter-black.webp', 'dark' => 'openrouter-white.webp' ],
    ];
    $imgBase = $basePath . 'admin/assets/images/';
    ?>

<style>
    .admin-sidebar  { display: none !important; }
    .admin-topbar   { display: none !important; }
    .admin-content  { margin-left: 0 !important; }
    .admin-main     { padding: 0 !important; }
    .admin-layout   { display: block !important; }
</style>

<?php
// Build the provider <option> list once, used in two selects.
ob_start();
foreach ($allProviders as $p):
    $provConfigured = !empty($p['configured']);
    $provId         = $p['id'] ?? '';
    $provName       = $p['name'] ?? '';
    $provModels     = $p['models'] ?? [];
    ?>
    <optgroup label="<?php echo klytos_esc_attr($provName); ?>">
        <?php if ($provConfigured && !empty($provModels)): ?>
            <?php foreach ($provModels as $model): ?>
                <option value="<?php echo klytos_esc_attr($provId . '|' . ($model['id'] ?? '')); ?>"
                    <?php echo ($active['provider'] === $provId && $active['model'] === ($model['id'] ?? '')) ? 'selected' : ''; ?>>
                    <?php echo klytos_esc_html($model['name'] ?? $model['id'] ?? ''); ?>
                </option>
            <?php endforeach; ?>
        <?php else: ?>
            <option disabled>-- <?php echo klytos_esc_html(__('ai_chat.no_provider')); ?></option>
        <?php endif; ?>
    </optgroup>
    <?php
endforeach;
$providerOptions = ob_get_clean();
?>

<div id="ai-chat-app"
     class="ai-chat-page-wrap"
     data-csrf="<?php echo klytos_esc_attr($_SESSION['klytos_csrf'] ?? ''); ?>"
     data-api-url="<?php echo klytos_esc_url($basePath . 'admin/api/ai-chat.php'); ?>"
     data-username="<?php echo klytos_esc_attr($username); ?>"
     data-no-results="<?php echo klytos_esc_attr(__('ai_chat.no_results')); ?>"
     data-img-base="<?php echo klytos_esc_url($imgBase); ?>"
     data-provider-logos="<?php echo klytos_esc_attr(json_encode($providerLogos)); ?>">

        <!-- ─── Sidebar ──────────────────────────────────────────── -->
        <div class="ai-chat-sidebar">
            <div class="ai-chat-sidebar-header">
                <button class="ai-chat-new-btn">
                    <i class="fa-solid fa-plus"></i>
                    <?php echo klytos_esc_html(__('ai_chat.new_conversation')); ?>
                </button>
            </div>

            <div class="ai-chat-sidebar-section">
                <div class="ai-chat-sidebar-nav">
                    <button class="ai-chat-nav-item" id="ai-chat-search-toggle">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <?php echo klytos_esc_html(__('ai_chat.search')); ?>
                    </button>
                    <button class="ai-chat-nav-item active" id="ai-chat-chats-toggle">
                        <i class="fa-regular fa-message"></i>
                        <?php echo klytos_esc_html(__('ai_chat.chats')); ?>
                    </button>
                </div>

                <div class="ai-chat-search-box" id="ai-chat-search-box" style="display:none;">
                    <input type="text" class="ai-chat-search-input" id="ai-chat-search-input"
                           placeholder="<?php echo klytos_esc_attr(__('ai_chat.search_placeholder')); ?>"
                           autocomplete="off">
                </div>

                <div class="ai-chat-sidebar-label" id="ai-chat-sidebar-label"><?php echo klytos_esc_html(__('ai_chat.recent')); ?></div>
                <div class="ai-chat-list"></div>
            </div>

            <div class="ai-chat-sidebar-footer" id="ai-chat-footer-toggle">
                <div class="ai-chat-popup-menu" id="ai-chat-popup-menu">
                    <a href="<?php echo klytos_esc_url($basePath . 'admin/ai-chat.php?panel=dashboard'); ?>" class="ai-chat-popup-menu-item">
                        <i class="fa-solid fa-chart-line"></i> <?php echo klytos_esc_html(__('ai_chat.dashboard')); ?>
                    </a>
                    <a href="<?php echo klytos_esc_url($basePath . 'admin/ai-chat.php?panel=settings'); ?>" class="ai-chat-popup-menu-item">
                        <i class="fa-solid fa-gear"></i> <?php echo klytos_esc_html(__('ai_chat.settings')); ?>
                    </a>
                    <a href="<?php echo klytos_esc_url($basePath . 'admin/ai-chat.php?panel=users'); ?>" class="ai-chat-popup-menu-item">
                        <i class="fa-solid fa-users"></i> <?php echo klytos_esc_html(__('ai_chat.users')); ?>
                    </a>
                    <a href="<?php echo klytos_esc_url($basePath . 'admin/ai-chat.php?panel=profile'); ?>" class="ai-chat-popup-menu-item">
                        <i class="fa-solid fa-user-pen"></i> My Profile
                    </a>
                    <div class="ai-chat-popup-sep"></div>
                    <a href="<?php echo klytos_esc_url($adminPath . 'index.php'); ?>" class="ai-chat-popup-menu-item">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i> <?php echo klytos_esc_html(__('ai_chat.classic_mode')); ?>
                    </a>
                </div>
                <div class="ai-chat-user-avatar"><?php echo klytos_esc_html($userInitial); ?></div>
                <span class="ai-chat-user-name"><?php echo klytos_esc_html($username); ?></span>
                <i class="fa-solid fa-chevron-up" style="color:var(--chat-text-dim);font-size:0.7rem;margin-left:auto;"></i>
            </div>
        </div>

        <!-- ─── Main Area ────────────────────────────────────────── -->
        <div class="ai-chat-main">

            <?php if ($panel): ?>
                <!-- Panel View -->
                <?php require_once __DIR__ . '/partials/ai-panel-' . $panel . '.php'; ?>
            <?php else: ?>
                <!-- Chats Browser (hidden by default) -->
                <div class="ai-chat-browser" id="ai-chat-browser" style="display:none;">
                    <div class="ai-chat-browser-header">
                        <h1><?php echo klytos_esc_html(__('ai_chat.chats')); ?></h1>
                        <button class="ai-chat-browser-new-btn" id="ai-chat-browser-new">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                    <div class="ai-chat-browser-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="ai-chat-browser-search-input"
                               placeholder="<?php echo klytos_esc_attr(__('ai_chat.search_in_chats')); ?>"
                               autocomplete="off">
                    </div>
                    <div class="ai-chat-browser-label">
                        <?php echo klytos_esc_html(__('ai_chat.your_conversations')); ?>
                    </div>
                    <div class="ai-chat-browser-list" id="ai-chat-browser-list"></div>
                </div>

                <!-- Welcome Screen -->
                <div class="ai-chat-welcome" id="ai-chat-welcome"<?php echo (!$hasProvider) ? ' style="display:none;"' : ''; ?>>
                    <div class="ai-chat-welcome-inner">
                        <h1 class="ai-chat-greeting" id="ai-chat-greeting"></h1>
                        <div class="ai-chat-welcome-input-wrap">
                            <textarea id="ai-chat-welcome-textarea"
                                      rows="1"
                                      placeholder="<?php echo klytos_esc_attr(__('ai_chat.welcome_placeholder')); ?>"></textarea>
                            <div class="ai-chat-welcome-actions">
                                <div class="ai-chat-model-select">
                                    <img class="ai-chat-provider-logo" id="ai-chat-provider-logo-welcome" src="" alt="" style="height: 20px; width: auto; display: none;">
                                    <select id="ai-provider-select-welcome">
                                        <?php echo $providerOptions; ?>
                                    </select>
                                </div>
                                <button class="ai-chat-send-btn" id="ai-chat-welcome-send">
                                    <i class="fa-solid fa-arrow-up"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Provider State -->
                <?php if (!$hasProvider): ?>
                    <div class="ai-chat-no-provider" id="ai-chat-no-provider">
                        <h3><i class="fa-solid fa-robot" style="margin-right: 0.5rem; opacity: 0.5;"></i> <?php echo klytos_esc_html(__('ai_chat.no_provider')); ?></h3>
                        <p style="margin-top: 0.75rem;">
                            <?php echo klytos_esc_html(__('ai_chat.configure_key')); ?>
                            <a href="<?php echo klytos_esc_url($basePath . 'admin/mcp.php?tab=api-ia'); ?>">
                                <?php echo klytos_esc_html(__('ai_keys.title')); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Chat View (hidden by default) -->
                <div class="ai-chat-view" id="ai-chat-view" style="display:none;">
                    <div class="ai-chat-view-topbar">
                        <div class="ai-chat-model-select">
                            <img class="ai-chat-provider-logo" id="ai-chat-provider-logo" src="" alt="" style="height: 20px; width: auto; display: none;">
                            <select id="ai-provider-select">
                                <?php echo $providerOptions; ?>
                            </select>
                        </div>
                        <span class="ai-chat-usage"></span>
                    </div>

                    <div class="ai-chat-messages"></div>

                    <div class="ai-chat-input">
                        <div class="ai-chat-input-wrap">
                            <textarea rows="1"
                                      placeholder="<?php echo klytos_esc_attr(__('ai_chat.placeholder')); ?>"
                                      <?php echo (!$hasProvider) ? 'disabled' : ''; ?>></textarea>
                            <button class="ai-chat-send-btn"
                                    <?php echo (!$hasProvider) ? 'disabled' : ''; ?>>
                                <i class="fa-solid fa-arrow-up"></i>
                            </button>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </div><!-- /.ai-chat-main -->
    </div><!-- /#ai-chat-app -->

<!-- Vendor JS (bundled) -->
<script nonce="<?php echo $cspNonce; ?>" src="<?php echo klytos_esc_url($basePath . 'admin/assets/vendor/marked/marked.min.js'); ?>"></script>
<script nonce="<?php echo $cspNonce; ?>" src="<?php echo klytos_esc_url($basePath . 'admin/assets/vendor/highlight/highlight.min.js'); ?>"></script>
<script nonce="<?php echo $cspNonce; ?>" src="<?php echo klytos_esc_url($basePath . 'admin/assets/vendor/purify/purify.min.js'); ?>"></script>

<!-- Chat JS -->
<script nonce="<?php echo $cspNonce; ?>" src="<?php echo klytos_esc_url($basePath . 'admin/assets/js/klytos-ai-chat.js'); ?>"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
