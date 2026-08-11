<?php

/**
 * Klytos Admin — AI chat
 *
 * Manifest entry 12 · template `conversation` (full screen) · H1 "Klytos AI".
 *
 * Built in Phase 4 Step 4, stage 6 (slice 3 of 3) against
 * `SPEC/screens/template-conversation.md`, `SPEC/accessibility.md` and
 * `SPEC/manifest.md` §12.
 *
 * WHAT IS BUILT AND WHAT IS DEFERRED — read this before changing anything here.
 *
 * **The streaming turn is the deferred engine interior (D-104, `roadmap.md`
 * §0c).** `admin/api/ai-chat.php` responds ONCE with the whole result and
 * `core/ai/chat-engine.php` has no streaming path of any kind, so a partial
 * turn cannot exist — and Stop, a tool call in its *running* state, the inline
 * *needs permission* confirm and the *Stopped* state are all states OF a
 * partial turn. Consent today is a sentence in the system prompt
 * (`chat-engine.php:401`), not a UI round trip. `getChat()` reads every message
 * with no limit/offset (`chat-manager.php:108-116`), so "Load earlier messages"
 * has no query to make and no earlier message to fetch. Nothing in the tree
 * records a last screen visited, so starters cannot be drawn from one. The
 * copilot dock is an empty landmark and nothing else (`templates/footer.php`).
 *
 * Everything the product DOES back is built to the letter: the shell, the
 * transcript as `role="log"`, the per-turn `<article>` with its name and its
 * always-present actions, the finished tool-call rows (done and failed), the
 * context chip row, the composer with its real label and its hint, the three
 * starters, the provider-unreachable alert and the not-configured state.
 *
 * THREE SHIPPED DEFECTS CLOSE HERE (D-104's four, minus the terminal's XSS
 * which closed with entry 23):
 *
 *   1. **Two `<h1>`s, and with `?panel=` set, ZERO.** This file printed an
 *      `<h1>` at the chats browser AND another as the greeting, while
 *      `$pageEmitsOwnH1 = true` was set unconditionally — so the panel views
 *      shipped with no `<h1>` at all and the shell had been told not to add
 *      one. Entry 2's answer applies: `$pageEmitsOwnH1` is gone and the shell
 *      owns the heading, exactly once, in `main`.
 *   2. **The no-provider state was defeated at runtime.** PHP hid the welcome
 *      panel and `showWelcome()` un-hid it unconditionally, then focused a
 *      textarea that — unlike the chat view's composer — carried no `disabled`.
 *      The composer is now REPLACED by the delivery's own single line and
 *      action, which cannot be un-hidden because it is not rendered.
 *   3. **`validate_key` validated nothing** — fixed in `api/ai-chat.php`, on
 *      entry 24's precedent, with its claim proven in the PHP tier.
 *
 * `?panel=` NO LONGER RENDERS AN ALTERNATE ADMIN (user decision, taken before
 * the first line of this rewrite). The four partials existed because this
 * screen hid the shell, so Dashboard, Settings, Users and Profile were
 * unreachable from it. The shell is back, the real navigation reaches all four,
 * and a second copy of a privileged screen behind a lower gate is the exact
 * shape audit NEW-31 reported. The four URLs now 302 to the real screens, which
 * gate themselves — so no privileged partial is included from here at all.
 *
 * @package Klytos
 * @since   0.9.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\Helpers;

$basePath  = Helpers::getBasePath();
$adminPath = $basePath . 'admin/';

/*
 * The four legacy panel URLs. They are answered with a redirect rather than a
 * partial: every target gates itself through the central gate map
 * (core/admin-gate.php), so an editor following ?panel=users lands on the same
 * 403 users.php would give them — and this file, which is gated only at
 * ai.use, never includes a privileged surface again.
 *
 * Absent from the map = not a panel, exactly as before, so a URL invented later
 * falls through to the chat instead of reaching anything.
 */
$panelRedirects = [
    'dashboard' => 'index.php',
    'settings'  => 'settings.php',
    'users'     => 'users.php',
    'profile'   => 'profile.php',
];

$panel = $_GET['panel'] ?? null;
if ( is_string( $panel ) && isset( $panelRedirects[ $panel ] ) ) {
    Helpers::redirect( $adminPath . $panelRedirects[ $panel ] );
}

$pageTitle = __( 'ai_chat.title' );

/*
 * §4 of the template: the full-screen chat owns the viewport and centres its
 * transcript at max 760px. That is stage 2's $shellFullBleed, which entry 2
 * used for the same reason — and which this screen used to fake with five
 * `display: none !important` rules that deleted the navigation, the toolbar and
 * the status bar from the screen a person may spend the longest on.
 */
$shellFullBleed = true;

// Provider state. A failure here is not fatal: the screen has a specified state
// for "not configured" and it is the honest one to show.
$active       = [ 'provider' => null, 'model' => null ];
$allProviders = [];
$hasProvider  = false;

try {
    $keys         = new \Klytos\Core\Ai\AiKeyManager( $app->getStorage(), $app->getConfigPath() );
    $active       = $keys->getActive();
    $allProviders = $keys->listProviders();
    $hasProvider  = ! empty( $active['provider'] ) && $keys->hasKey( $active['provider'] );
} catch ( \Throwable $e ) {
    klytos_log( 'AI chat: provider state unavailable — ' . $e->getMessage(), 'warning' );
}

$csrfToken = $app->getAuth()->getCsrfToken();
$spriteUrl = $adminPath . 'assets/icons/klytos-ui-icons.svg';

/**
 * Render one sprite glyph.
 *
 * Icons here are decoration beside text that already carries the state
 * (accessibility.md §1.3: colour and glyph are never the only channel), so
 * every one of them is aria-hidden.
 */
$glyph = static function ( string $id, string $class = 'k-conv-glyph' ) use ( $spriteUrl ): string {
    return sprintf(
        '<svg class="%s" aria-hidden="true" focusable="false"><use href="%s#%s"></use></svg>',
        klytos_esc_attr( $class ),
        klytos_esc_url( $spriteUrl ),
        klytos_esc_attr( $id )
    );
};

/*
 * The three starters. The template quotes them literally and the delta asks for
 * them to be "drawn from the last screen visited" — nothing in this product
 * records a last screen visited (D-104), so the literal three are built and the
 * derivation is the deferred half. They are BUTTONS, not links: a link needs a
 * destination and these have none (the send path is a fetch), which is DR-004's
 * finding on a different control.
 */
$starters = [
    __( 'ai_chat.starter_meta' ),
    __( 'ai_chat.starter_errors' ),
    __( 'ai_chat.starter_pricing' ),
];

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php klytos_do_action( 'admin.ai_chat.before' ); ?>

<div class="k-conv" data-testid="ai_chat.screen">

    <?php // ─── Header (§1: 50px — title, model chip, controls) ─────────── ?>
    <div class="k-conv-header" data-testid="ai_chat.header">
        <?php if ( $hasProvider ) : ?>
            <?php
            /*
             * §5: "The model chip is text, not a coloured dot." It is a real
             * <select> because switching provider mid-conversation is shipped
             * behaviour (D-076's rule), and a select carries its own name from
             * a real <label> rather than from a title attribute.
             */
            ?>
            <p class="k-conv-model">
                <label class="k-sr" for="ai-chat-model"><?php echo klytos_esc_html( __( 'ai_chat.model_label' ) ); ?></label>
                <select id="ai-chat-model" class="k-control k-conv-model-select" data-testid="ai_chat.model">
                    <?php foreach ( $allProviders as $p ) : ?>
                        <?php
                        $provId     = (string) ( $p['id'] ?? '' );
                        $provName   = (string) ( $p['name'] ?? '' );
                        $provModels = $p['models'] ?? [];
                        if ( empty( $p['configured'] ) || empty( $provModels ) ) {
                            continue;
                        }
                        ?>
                        <optgroup label="<?php echo klytos_esc_attr( $provName ); ?>">
                            <?php foreach ( $provModels as $model ) : ?>
                                <?php $modelId = (string) ( $model['id'] ?? '' ); ?>
                                <option value="<?php echo klytos_esc_attr( $provId . '|' . $modelId ); ?>"
                                    <?php echo ( $active['provider'] === $provId && $active['model'] === $modelId ) ? 'selected' : ''; ?>>
                                    <?php echo klytos_esc_html( $model['name'] ?? $modelId ); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </p>
        <?php endif; ?>

        <?php
        /*
         * The conversation history. The template draws no such surface — it is
         * SHIPPED behaviour (a whole sidebar with a list, a search field and a
         * separate browser view), and D-076's rule holds for the fifth time:
         * removing shipped behaviour is not a fidelity decision. It is rebuilt
         * as the delivery's own disclosure semantics inside the one region §1
         * does define, on entry 23's command-reference precedent — the user's
         * decision, taken before this rewrite began.
         */
        ?>
        <div class="k-conv-controls">
            <button type="button" class="k-btn k-btn--secondary k-btn--sm" id="ai-chat-new"
                    data-testid="ai_chat.new">
                <?php echo klytos_esc_html( __( 'ai_chat.new_conversation' ) ); ?>
            </button>
            <button type="button" class="k-btn k-btn--secondary k-btn--sm" id="ai-chat-history-toggle"
                    aria-expanded="false" aria-controls="ai-chat-history"
                    data-testid="ai_chat.history_toggle">
                <?php echo klytos_esc_html( __( 'ai_chat.history_toggle' ) ); ?>
            </button>
        </div>
    </div>

    <aside class="k-card k-card--padded k-conv-history" id="ai-chat-history" hidden
           aria-labelledby="ai-chat-history-title" data-testid="ai_chat.history">
        <div class="k-conv-history-head">
            <h2 id="ai-chat-history-title"><?php echo klytos_esc_html( __( 'ai_chat.history_title' ) ); ?></h2>
            <button type="button" class="k-btn k-btn--secondary k-btn--sm" id="ai-chat-history-close"
                    data-testid="ai_chat.history_close">
                <?php echo klytos_esc_html( __( 'ai_chat.history_close' ) ); ?>
            </button>
        </div>
        <div class="k-field">
            <label class="k-label" for="ai-chat-history-search">
                <?php echo klytos_esc_html( __( 'ai_chat.history_search_label' ) ); ?>
            </label>
            <input type="search" class="k-control" id="ai-chat-history-search"
                   autocomplete="off" data-testid="ai_chat.history_search">
        </div>
        <ul class="k-plain-list k-conv-history-list" id="ai-chat-history-list"></ul>
        <p class="k-empty-text" id="ai-chat-history-empty" hidden data-testid="ai_chat.history_empty">
            <?php echo klytos_esc_html( __( 'ai_chat.no_conversations' ) ); ?>
        </p>
    </aside>

    <?php
    /*
     * The polite status region. §5 forbids `aria-live="assertive"` anywhere in
     * the conversation — "the copilot does not interrupt" — and the transcript
     * carries its own polite live region below, so this one reports what the
     * transcript is not: sending, copy results, history results.
     */
    ?>
    <p class="k-status-line" role="status" id="ai-chat-status" data-testid="ai_chat.status"></p>

    <?php
    /*
     * §5: "Transcript: role="log" aria-live="polite" aria-relevant="additions"."
     * With no streaming path the whole finished turn is appended in one
     * operation, which is precisely the outcome §2 asks streaming to simulate:
     * only the finished turn is announced, never each token.
     */
    ?>
    <div class="k-conv-transcript"
         id="ai-chat-transcript"
         role="log"
         aria-live="polite"
         aria-relevant="additions"
         aria-label="<?php echo klytos_esc_attr( __( 'ai_chat.transcript_label' ) ); ?>"
         data-testid="ai_chat.transcript">

        <?php // §2 "Empty — new conversation": not a blank panel. ?>
        <div class="k-conv-starters" id="ai-chat-starters" data-testid="ai_chat.starters">
            <p class="k-conv-starters-intro"><?php echo klytos_esc_html( __( 'ai_chat.empty_intro' ) ); ?></p>
            <ul class="k-plain-list">
                <?php foreach ( $starters as $i => $starter ) : ?>
                    <li>
                        <button type="button" class="k-btn k-btn--secondary k-btn--sm k-conv-starter"
                                data-testid="ai_chat.starter.<?php echo (int) $i; ?>">
                            <?php echo klytos_esc_html( $starter ); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <?php
    /*
     * §1's context chip row, in the state §2 calls "Empty — no context
     * available": "the context chip row says so rather than disappearing".
     * That is this product's PERMANENT state — nothing anywhere records a page
     * in context (D-104) — so the row is rendered with the sentence the
     * delivery writes for it, and the chips themselves arrive with the context
     * mechanism, not before it.
     */
    ?>
    <p class="k-conv-chips" data-testid="ai_chat.context">
        <?php echo klytos_esc_html( __( 'ai_chat.context_none' ) ); ?>
    </p>

    <?php if ( $hasProvider ) : ?>
        <form class="k-conv-composer" id="ai-chat-composer" data-testid="ai_chat.composer">
            <div class="k-field k-conv-composer-field">
                <label class="k-sr" for="ai-chat-input">
                    <?php echo klytos_esc_html( __( 'ai_chat.composer_label' ) ); ?>
                </label>
                <textarea id="ai-chat-input"
                          class="k-control k-conv-input"
                          rows="1"
                          aria-describedby="ai-chat-hint"
                          data-testid="ai_chat.input"></textarea>
                <button type="submit" class="k-conv-send" aria-busy="false"
                        data-testid="ai_chat.send">
                    <?php echo $glyph( 'ks-arrow_upward', 'k-conv-send-glyph' ); ?>
                    <span class="k-sr"><?php echo klytos_esc_html( __( 'ai_chat.send' ) ); ?></span>
                </button>
            </div>
            <p class="k-hint" id="ai-chat-hint"><?php echo klytos_esc_html( __( 'ai_chat.composer_hint' ) ); ?></p>
        </form>
    <?php else : ?>
        <?php
        /*
         * §2 "Error — no API key": "the composer is replaced by a single line
         * and an action; a disabled composer with no explanation is not
         * acceptable". Replaced, not disabled and not hidden — which is also
         * what makes the shipped defect unrepeatable: there is no composer in
         * the document for a script to un-hide and focus.
         */
        ?>
        <p class="k-conv-unconfigured" role="status" data-testid="ai_chat.not_configured">
            <?php echo klytos_esc_html( __( 'ai_chat.not_configured' ) ); ?>
            <a href="<?php echo klytos_esc_url( $adminPath . 'mcp.php?tab=api-ia' ); ?>"
               data-testid="ai_chat.open_settings">
                <?php echo klytos_esc_html( __( 'ai_chat.open_settings' ) ); ?>
            </a>
        </p>
    <?php endif; ?>

</div>

<?php klytos_do_action( 'admin.ai_chat.after' ); ?>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>" src="<?php echo klytos_esc_url( $adminPath . 'assets/vendor/marked/marked.min.js' ); ?>"></script>
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>" src="<?php echo klytos_esc_url( $adminPath . 'assets/vendor/highlight/highlight.min.js' ); ?>"></script>
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>" src="<?php echo klytos_esc_url( $adminPath . 'assets/vendor/purify/purify.min.js' ); ?>"></script>

<?php
/*
 * Strings and configuration as DATA, never interpolated into a script body —
 * terminal.php's shape for terminal.php's reason: escaping a translated
 * sentence into JavaScript breaks on the first catalogue containing a quote,
 * and this screen adds keys to twenty of them.
 */
?>
<script type="application/json" id="ai-chat-config" nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"><?php
echo json_encode(
    [
        'csrf'          => $csrfToken,
        'apiUrl'        => $adminPath . 'api/ai-chat.php',
        'sprite'        => $spriteUrl,
        'hasProvider'   => $hasProvider,
        'settingsUrl'   => $adminPath . 'mcp.php?tab=api-ia',
        'strings'       => [
            'you'             => __( 'ai_chat.turn_you' ),
            'assistant'       => __( 'ai_chat.turn_assistant' ),
            'copy'            => __( 'ai_chat.copy_turn' ),
            'copied'          => __( 'ai_chat.copied' ),
            'copyFailed'      => __( 'ai_chat.copy_failed' ),
            'retry'           => __( 'ai_chat.retry' ),
            'sending'         => __( 'ai_chat.sending' ),
            'toolRan'         => __( 'ai_chat.tool_ran' ),
            'toolFailed'      => __( 'ai_chat.tool_failed' ),
            'toolCalls'       => __( 'ai_chat.tool_calls' ),
            'toolInput'       => __( 'ai_chat.tool_input' ),
            'toolOutput'      => __( 'ai_chat.tool_output' ),
            'unreachable'     => __( 'ai_chat.unreachable' ),
            'openSettings'    => __( 'ai_chat.open_settings' ),
            'networkError'    => __( 'ai_chat.network_error' ),
            'jumpToLatest'    => __( 'ai_chat.jump_to_latest' ),
            'untitled'        => __( 'ai_chat.untitled' ),
            'deleteConversation' => __( 'ai_chat.delete_conversation' ),
            'deleteConfirm'   => __( 'ai_chat.delete_confirm' ),
            'noConversations' => __( 'ai_chat.no_conversations' ),
            'historyCount'    => __( 'ai_chat.history_count' ),
            'providerChanged' => __( 'ai_chat.provider_changed' ),
        ],
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?></script>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"
        src="<?php echo klytos_esc_url( $adminPath . 'assets/js/klytos-ai-chat.js' ); ?>"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
