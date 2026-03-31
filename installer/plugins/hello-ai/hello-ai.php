<?php

/**
 * Plugin Name: Hello AI
 * Plugin URI: https://klytos.io/plugins/hello-ai
 * Description: A demo plugin that adds a simple AI chat button to the admin topbar. Shows how to build a translatable Klytos plugin.
 * Version: 1.0.0
 * Author: Jose Conti
 * Author URI: https://joseconti.com
 * Requires Klytos: 0.14.0
 * Requires PHP: 8.1
 * License: ELv2
 * License URI: https://www.elastic.co/licensing/elastic-license
 * Text Domain: hello-ai
 * Domain Path: /lang
 */

declare(strict_types=1);

// ─── Register translations ─────────────────────────────────
// This must be done first so __() works for all subsequent calls.

klytos_register_translations('hello-ai', __DIR__ . '/lang');

// ─── Topbar button ──────────────────────────────────────────

klytos_add_filter('admin.topbar_actions', function (string $html): string {
    $label = klytos_esc_html(__('hello_ai.button_label'));
    $html .= '<button type="button" id="hello-ai-toggle" class="btn btn-outline btn-sm" '
           . 'style="display:inline-flex;align-items:center;gap:0.4rem;">'
           . '<i class="fa-solid fa-comments"></i> ' . $label . '</button>';
    return $html;
});

// ─── Load CSS ───────────────────────────────────────────────

klytos_add_action('admin.head', function (string $cspNonce): void {
    $cssUrl = klytos_plugin_url('hello-ai', 'assets/css/hello-ai.css');
    echo '<link rel="stylesheet" href="' . klytos_esc_url($cssUrl) . '">' . "\n";
});

// ─── Modal HTML + JS ────────────────────────────────────────

klytos_add_action('admin.footer', function (string $cspNonce): void {
    $title       = klytos_esc_html(__('hello_ai.modal_title'));
    $welcome     = __('hello_ai.welcome_message'); // Contains HTML, already safe from our JSON.
    $placeholder = klytos_esc_attr(__('hello_ai.input_placeholder'));

    // Build the translations object for JavaScript.
    $jsTranslations = [
        'responses' => [
            __('hello_ai.response_1'),
            __('hello_ai.response_2'),
            __('hello_ai.response_3'),
            __('hello_ai.response_4'),
            __('hello_ai.response_5'),
            __('hello_ai.response_6'),
            __('hello_ai.response_7'),
            __('hello_ai.response_8'),
        ],
        'smart' => [
            'hello'     => __('hello_ai.smart_hello'),
            'hi'        => __('hello_ai.smart_hi'),
            'hola'      => __('hello_ai.smart_hello'),
            'klytos'    => __('hello_ai.smart_klytos'),
            'plugin'    => __('hello_ai.smart_plugin'),
            'mcp'       => __('hello_ai.smart_mcp'),
            'help'      => __('hello_ai.smart_help'),
            'bye'       => __('hello_ai.smart_bye'),
            'adios'     => __('hello_ai.smart_bye'),
            'translate' => __('hello_ai.smart_translate'),
            'traducir'  => __('hello_ai.smart_translate'),
        ],
    ];
    ?>
    <div class="hello-ai-overlay" id="helloAiOverlay">
        <div class="hello-ai-modal">
            <div class="hello-ai-header">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <i class="fa-solid fa-comments" style="color:var(--admin-primary);"></i>
                    <strong><?php echo $title; ?></strong>
                </div>
                <button type="button" id="helloAiClose" class="hello-ai-close">&times;</button>
            </div>
            <div class="hello-ai-messages" id="helloAiMessages">
                <div class="hello-ai-msg hello-ai-msg-assistant">
                    <div class="hello-ai-bubble">
                        <?php echo $welcome; ?>
                    </div>
                </div>
            </div>
            <div class="hello-ai-input-area">
                <input type="text" id="helloAiInput" class="form-control"
                       placeholder="<?php echo $placeholder; ?>" autocomplete="off">
                <button type="button" id="helloAiSend" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
    <script nonce="<?php echo klytos_esc_attr($cspNonce); ?>">
        window.helloAiTranslations = <?php echo json_encode($jsTranslations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    </script>
    <?php
    $jsUrl = klytos_plugin_url('hello-ai', 'assets/js/hello-ai.js');
    echo '<script nonce="' . klytos_esc_attr($cspNonce) . '" src="'
       . klytos_esc_url($jsUrl) . '"></script>' . "\n";
});
