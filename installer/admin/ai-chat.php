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

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<link rel="stylesheet" href="<?php echo klytos_esc_url($basePath . 'admin/assets/css/ai-chat.css'); ?>">
<script nonce="<?php echo $cspNonce; ?>">
    var el = document.querySelector('.admin-main');
    if (el) el.classList.add('ai-chat-page');
</script>

<div id="ai-chat-app"
     class="ai-chat-layout"
     data-csrf="<?php echo klytos_esc_attr($_SESSION['klytos_csrf'] ?? ''); ?>"
     data-api-url="<?php echo klytos_esc_url($basePath . 'admin/api/ai-chat.php'); ?>">

    <!-- Conversation Sidebar -->
    <div class="ai-chat-sidebar">
        <div class="ai-chat-sidebar-header">
            <button class="btn btn-primary ai-chat-new-btn">
                <i class="fa-solid fa-plus"></i>
                <?php echo klytos_esc_html(__('ai_chat.new_conversation')); ?>
            </button>
        </div>
        <div class="ai-chat-list"></div>
    </div>

    <!-- Main Chat Area -->
    <div class="ai-chat-main">

        <!-- Top Bar -->
        <div class="ai-chat-topbar">
            <div class="ai-chat-provider-select">
                <label for="ai-provider-select" style="font-size: 0.85rem; font-weight: 500;">
                    <i class="fa-solid fa-robot"></i>
                </label>
                <select id="ai-provider-select">
                    <?php foreach ($allProviders as $p): ?>
                        <?php
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
                    <?php endforeach; ?>
                </select>
            </div>
            <span class="ai-chat-usage"></span>
        </div>

        <!-- Messages -->
        <div class="ai-chat-messages">
            <?php if (!$hasProvider): ?>
                <div class="ai-chat-no-provider">
                    <h3><i class="fa-solid fa-robot" style="margin-right: 0.5rem; opacity: 0.5;"></i> <?php echo klytos_esc_html(__('ai_chat.no_provider')); ?></h3>
                    <p style="margin-top: 0.75rem;">
                        <?php echo klytos_esc_html(__('ai_chat.configure_key')); ?>
                        <a href="<?php echo klytos_esc_url($basePath . 'admin/mcp.php?tab=api-ia'); ?>">
                            <?php echo klytos_esc_html(__('ai_keys.title')); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <div class="ai-chat-empty">
                    <div>
                        <h3><i class="fa-solid fa-comments" style="margin-right: 0.5rem; opacity: 0.4;"></i> <?php echo klytos_esc_html(__('ai_chat.title')); ?></h3>
                        <p style="margin-top: 0.5rem;"><?php echo klytos_esc_html(__('ai_chat.placeholder')); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Input -->
        <div class="ai-chat-input">
            <div class="ai-chat-input-wrap">
                <textarea rows="1"
                          placeholder="<?php echo klytos_esc_attr(__('ai_chat.placeholder')); ?>"
                          <?php echo (!$hasProvider) ? 'disabled' : ''; ?>></textarea>
                <button class="ai-chat-send-btn"
                        <?php echo (!$hasProvider) ? 'disabled' : ''; ?>>
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
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
