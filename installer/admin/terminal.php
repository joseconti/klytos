<?php

/**
 * Klytos Admin — Terminal
 *
 * Manifest entry 23 · template `console-stream` · H1 "Terminal".
 *
 * Built in Phase 4 Step 4, stage 6 against
 * `SPEC/screens/template-console-stream.md`, `SPEC/accessibility.md` and
 * `SPEC/manifest.md` §23.
 *
 * WHAT IS BUILT AND WHAT IS DEFERRED — read this before changing anything here.
 *
 * **The stream is the deferred engine interior (D-104, `roadmap.md` §0c), on
 * the user's decision taken before a line of this file was written.** The
 * delivery draws the stream as a `<pre>` whose lines are focusable and
 * selectable, carrying streamed output, elapsed seconds, an exit code with its
 * meaning, a working Stop/`Ctrl+C` and a 5,000-line truncation notice. This
 * product renders into an **xterm.js canvas**, and behind it nothing backs the
 * rest: `dispatch()` buffers with `ob_start()`/`ob_get_clean()` and returns one
 * whole string (`terminal-executor.php:146-156`), nothing anywhere measures a
 * command's duration, the declared return type is
 * `array{success: bool, output: string}` with no numeric status (`:122`), and
 * handlers run synchronously in-process with no interrupt point — so there is
 * nothing for a Stop to cancel. The canvas therefore STAYS, in a labelled
 * container, exactly as entry 2 kept Gutenberg.
 *
 * Everything AROUND it is built to the letter: the control row, the
 * no-second-factor state, the command reference, the revalidation dialog, the
 * status region, and the screen's whole string set.
 *
 * Three consequences of the deferral, stated so they are not read as defects:
 *
 *   1. **`accessibility.md` §7.1 is not claimed on this screen.** The exception
 *      it grants is for a stream LINE that is a pointer target, and this screen
 *      has none — the canvas is one element. Every control here is ≥ 24px.
 *   2. **§1's "the prompt is a real form" is not built.** The canvas owns the
 *      keyboard, so a second input beside it would be two prompts for one
 *      shell. It arrives with the interior, not before it.
 *   3. **The shipped `.klytos-terminal-header` is gone.** The design's anatomy
 *      is h1 · control row · stream, with no chrome bar of its own, and the
 *      version it carried is one `version` command away.
 *
 * NEW-33 CLOSES HERE. This file called `__()` zero times and every string on it
 * was a Spanish literal, several unaccented ("autenticacion", "Sesion",
 * "rapida"), against the recorded English-base rule (D-006). Every one is now a
 * `terminal.*` key present in all 20 catalogues.
 *
 * Requires 2FA active + `terminal.access` (owner only). Commands execute in
 * pure PHP through `TerminalExecutor` — no exec/shell_exec.
 *
 * @package Klytos
 * @since   0.12.0
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

// Gate: must have terminal.access permission.
// Enforced centrally since Sprint 1 slice 4 — 'terminal.access' in the gate
// map (core/admin-gate.php), refused by admin/bootstrap.php with a 403 before
// this body runs. The 2FA requirement below is a SEPARATE condition and stays
// here: it is about how recently the caller proved their identity, not about
// what their role permits.

$currentUser = klytos_current_user();
$has2fa      = ! empty( $currentUser['two_factor']['enabled'] );
$pageTitle   = __( 'terminal.title' );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php klytos_do_action( 'admin.terminal.before' ); ?>

<?php if ( ! $has2fa ) : ?>
    <?php
    /*
     * The no-second-factor state. It was a hand-built amber box with inline
     * `background:#fef3c7` and five more literal hex values — colours the
     * redesign does not have — carrying unaccented Spanish. It is now the
     * delivery's own error empty state, which means it also follows the theme.
     */
    ?>
    <div class="k-empty k-empty--error" role="alert" data-testid="terminal.two_factor_required">
        <p class="k-empty-text">
            <strong><?php echo klytos_esc_html( __( 'terminal.two_factor_required' ) ); ?></strong>
        </p>
        <p class="k-empty-text">
            <?php echo klytos_esc_html( __( 'terminal.two_factor_required_desc' ) ); ?>
            <a href="<?php echo klytos_esc_url( $basePath . 'admin/security.php' ); ?>"
               data-testid="terminal.go_to_security">
                <?php echo klytos_esc_html( __( 'terminal.two_factor_go_to_security' ) ); ?>
            </a>
        </p>
    </div>
    <?php
    klytos_do_action( 'admin.terminal.after' );
    require_once __DIR__ . '/templates/footer.php';
    return;
    ?>
<?php endif; ?>

<?php
$csrfToken = $app->getAuth()->getCsrfToken();
$apiBase   = klytos_esc_url( Helpers::getBasePath() . 'admin/' );

/*
 * The category labels the reference panel and `help` both draw. They go
 * through the SAME filter the executor's own help uses
 * (`terminal.category_labels`), so a plugin that renames a category renames it
 * in both places rather than in one — which is what happened before, when this
 * file carried its own hardcoded Spanish copy of the map.
 */
$categoryLabels = klytos_apply_filters( 'terminal.category_labels', [
    'general' => __( 'terminal.category_general' ),
    'build'   => __( 'terminal.category_build' ),
    'content' => __( 'terminal.category_content' ),
    'system'  => __( 'terminal.category_system' ),
    'users'   => __( 'terminal.category_users' ),
    'plugins' => __( 'terminal.category_plugins' ),
    'backup'  => __( 'terminal.category_backup' ),
    'update'  => __( 'terminal.category_update' ),
    'config'  => __( 'terminal.category_config' ),
] );
?>

<?php // ─── Control row (§1) ─────────────────────────────────────────────── ?>
<?php
/*
 * §2: "On the consumers with no detail panel — Terminal, Health, Webhooks,
 * Block data — only **Copy all** exists, named for its content ('Copy the
 * whole payload')." There is deliberately no per-line copy and no Download:
 * the terminal has no file behind it to download.
 */
?>
<div class="k-console-controls" data-testid="terminal.controls">
    <button type="button" class="k-btn k-btn--secondary" id="terminal-copy-all"
            data-testid="terminal.copy_all">
        <?php echo klytos_esc_html( __( 'terminal.copy_all' ) ); ?>
    </button>

    <?php
    /*
     * The command reference is SHIPPED behaviour the design does not draw, and
     * D-076's rule applies for the fourth time: removing shipped behaviour is
     * not a fidelity decision. It keeps the panel, and takes the delivery's
     * disclosure semantics — `aria-expanded` + `aria-controls`, which the
     * previous `classList.toggle` had neither of.
     */
    ?>
    <button type="button" class="k-btn k-btn--secondary" id="toggle-cmd-panel"
            aria-expanded="false" aria-controls="cmd-panel"
            data-testid="terminal.commands_toggle">
        <?php echo klytos_esc_html( __( 'terminal.commands_toggle' ) ); ?>
    </button>
</div>

<?php
/*
 * §2/§4's status region: polite, in the flow, never `aria-live` on the stream
 * itself. It carries "Running …" / "Finished." and the copy result. The
 * canvas is aria-busy for the same duration, which is the machine-readable
 * half of the same fact.
 */
?>
<p class="k-status-line" role="status" id="terminal-status" data-testid="terminal.status"></p>

<div class="k-console-layout">
    <div class="k-card k-card--padded">
        <?php
        /*
         * THE DEFERRED INTERIOR. xterm.js mounts into this element. It is
         * labelled and focusable so that the chrome around it is complete and
         * the canvas is reachable and named — everything INSIDE it (the line
         * model, streaming, elapsed, exit code, Stop) is the engine interior
         * recorded in `roadmap.md` §0c.
         *
         * It is NOT `.k-stream`: that class styles the delivery's `<pre>`
         * panel, and painting a canvas with a `<pre>`'s typography would be
         * markup claiming to be something it is not.
         */
        ?>
        <div class="k-terminal-canvas"
             id="klytos-terminal"
             role="group"
             tabindex="0"
             aria-busy="false"
             aria-label="<?php echo klytos_esc_attr( __( 'terminal.console_label' ) ); ?>"
             data-testid="terminal.console"></div>
    </div>

    <?php // The command reference, in the console layout's own right panel. ?>
    <aside class="k-card k-card--padded k-console-detail" id="cmd-panel" hidden
           data-testid="terminal.commands_panel">
        <div class="k-console-detail-head">
            <h2 id="cmd-panel-title"><?php echo klytos_esc_html( __( 'terminal.commands_title' ) ); ?></h2>
            <button type="button" class="k-btn k-btn--secondary k-btn--sm" id="close-cmd-panel"
                    data-testid="terminal.commands_close">
                <?php echo klytos_esc_html( __( 'terminal.commands_close' ) ); ?>
            </button>
        </div>
        <p class="k-empty-text"><?php echo klytos_esc_html( __( 'terminal.commands_hint' ) ); ?></p>
        <div id="cmd-panel-list"></div>
        <p class="k-empty-text" id="cmd-panel-empty" hidden>
            <?php echo klytos_esc_html( __( 'terminal.commands_empty' ) ); ?>
        </p>
    </aside>
</div>

<?php
/*
 * The revalidation dialog. Shipped behaviour (the executor demands a fresh
 * second factor after ten idle minutes) and therefore kept — but it was a
 * `<div>` with a click handler and no dialog semantics of any kind: no role,
 * no `aria-modal`, no heading association, no focus trap, no Esc, and no way
 * out at all except succeeding. `accessibility.md` §3.2 governs every overlay
 * in this admin, and the command palette in `templates/footer.php` is the
 * pattern it is built to.
 */
?>
<div class="k-modal" id="revalidation-modal" hidden>
    <div class="k-modal-window" role="dialog" aria-modal="true"
         aria-labelledby="revalidation-title" data-testid="terminal.revalidate">
        <h2 id="revalidation-title"><?php echo klytos_esc_html( __( 'terminal.revalidate_title' ) ); ?></h2>
        <p><?php echo klytos_esc_html( __( 'terminal.revalidate_desc' ) ); ?></p>

        <div class="k-field">
            <label class="k-label" for="revalidation-code">
                <?php echo klytos_esc_html( __( 'terminal.revalidate_label' ) ); ?>
            </label>
            <input type="text"
                   class="k-control k-control--mono"
                   id="revalidation-code"
                   maxlength="6"
                   autocomplete="one-time-code"
                   inputmode="numeric"
                   pattern="[0-9]*"
                   spellcheck="false"
                   aria-describedby="revalidation-error"
                   data-testid="terminal.revalidate_code">
            <p class="k-error" id="revalidation-error" role="alert" hidden></p>
        </div>

        <div class="k-toolbar-actions">
            <button type="button" class="k-btn k-btn--primary" id="revalidation-submit"
                    data-testid="terminal.revalidate_submit">
                <?php echo klytos_esc_html( __( 'terminal.revalidate_submit' ) ); ?>
            </button>
            <button type="button" class="k-btn k-btn--secondary" id="revalidation-cancel"
                    data-testid="terminal.revalidate_cancel">
                <?php echo klytos_esc_html( __( 'terminal.revalidate_cancel' ) ); ?>
            </button>
        </div>
    </div>
</div>

<?php klytos_do_action( 'admin.terminal.after' ); ?>

<link rel="stylesheet" href="<?php echo klytos_esc_url( $basePath . 'admin/assets/vendor/xterm/xterm.min.css' ); ?>">
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"
        src="<?php echo klytos_esc_url( $basePath . 'admin/assets/vendor/xterm/xterm.min.js' ); ?>"></script>
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"
        src="<?php echo klytos_esc_url( $basePath . 'admin/assets/vendor/xterm/addon-fit.min.js' ); ?>"></script>

<?php
/*
 * The strings and the CSRF token the script needs, as DATA rather than as
 * interpolated JavaScript — logs.php's shape, for logs.php's reason: escaping
 * a translated sentence into a script body breaks on the first catalogue
 * containing a quote, and this screen adds keys to twenty of them. An
 * `application/json` block is inert to the JavaScript parser.
 *
 * Emitted BEFORE the script that reads it, because the script reads it at
 * parse time — after would be `null`.
 */
?>
<script type="application/json" id="terminal-strings" nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"><?php
echo json_encode(
    [
        'csrf'            => $csrfToken,
        'apiBase'         => Helpers::getBasePath() . 'admin/',
        'categoryLabels'  => $categoryLabels,
        'welcomeIntro'    => __( 'terminal.welcome_intro' ),
        'welcomeKeys'     => __( 'terminal.welcome_keys' ),
        'running'         => __( 'terminal.running' ),
        'finished'        => __( 'terminal.finished' ),
        'copied'          => __( 'terminal.copied' ),
        'copyFailed'      => __( 'terminal.copy_failed' ),
        'copyEmpty'       => __( 'terminal.copy_empty' ),
        'connectionError' => __( 'terminal.connection_error' ),
        'serverError'     => __( 'terminal.server_error' ),
        'revalidateError' => __( 'terminal.revalidate_error' ),
        'revalidateBad'   => __( 'terminal.revalidate_rejected' ),
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?></script>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"
        src="<?php echo klytos_esc_url( $basePath . 'admin/assets/js/klytos-terminal.js' ); ?>"></script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
