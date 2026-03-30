<?php
/**
 * Klytos Admin — AI Chat
 * Integrated AI chat for controlling the CMS from the admin panel.
 *
 * @package Klytos
 * @since   0.9.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
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

$username = $app->getAuth()->getUsername();
$userInitial = mb_strtoupper(mb_substr($username, 0, 1));

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo klytos_esc_url($basePath . 'admin/assets/css/ai-chat.css'); ?>">

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

<div class="ai-chat-page-wrap">

    <div id="ai-chat-app"
         class="ai-chat-page-wrap"
         data-csrf="<?php echo klytos_esc_attr($_SESSION['klytos_csrf'] ?? ''); ?>"
         data-api-url="<?php echo klytos_esc_url($basePath . 'admin/api/ai-chat.php'); ?>"
         data-username="<?php echo klytos_esc_attr($username); ?>">

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
                    <button class="ai-chat-nav-item" disabled>
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <?php echo klytos_esc_html(__('ai_chat.search')); ?>
                    </button>
                    <a class="ai-chat-nav-item active" href="#">
                        <i class="fa-regular fa-message"></i>
                        <?php echo klytos_esc_html(__('ai_chat.chats')); ?>
                    </a>
                </div>

                <div class="ai-chat-sidebar-label"><?php echo klytos_esc_html(__('ai_chat.recent')); ?></div>
                <div class="ai-chat-list"></div>
            </div>

            <div class="ai-chat-sidebar-footer">
                <div class="ai-chat-user-avatar"><?php echo klytos_esc_html($userInitial); ?></div>
                <span class="ai-chat-user-name"><?php echo klytos_esc_html($username); ?></span>
                <a href="<?php echo klytos_esc_url($adminPath . 'index.php'); ?>" class="ai-chat-settings-link" title="<?php echo klytos_esc_attr(__('ai_chat.classic_mode')); ?>">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </a>
            </div>
        </div>

        <!-- ─── Main Area ────────────────────────────────────────── -->
        <div class="ai-chat-main">

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

        </div>
    </div>
</div>

<!-- Vendor JS (bundled) -->
<script nonce="<?php echo $cspNonce; ?>" src="<?php echo klytos_esc_url($basePath . 'admin/assets/vendor/marked/marked.min.js'); ?>"></script>
<script nonce="<?php echo $cspNonce; ?>" src="<?php echo klytos_esc_url($basePath . 'admin/assets/vendor/highlight/highlight.min.js'); ?>"></script>
<script nonce="<?php echo $cspNonce; ?>" src="<?php echo klytos_esc_url($basePath . 'admin/assets/vendor/purify/purify.min.js'); ?>"></script>

<!-- Chat JS -->
<script nonce="<?php echo $cspNonce; ?>" src="<?php echo klytos_esc_url($basePath . 'admin/assets/js/klytos-ai-chat.js'); ?>"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
