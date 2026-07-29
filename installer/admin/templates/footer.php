<?php

/**
 * Klytos Admin — Footer Template (the shell, part 3 of 3).
 *
 * Phase 4 Step 4, stage 2 of 6. Closes `<main>`, emits the two shared live
 * regions and the copilot dock, draws the status bar and closes the shell.
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

$adminPath = $adminPath ?? '';
$cspNonce  = $cspNonce ?? ( $GLOBALS['klytos_csp_nonce'] ?? '' );
?>
<?php klytos_do_action('admin.page.after_content', $GLOBALS['klytos_admin_page'] ?? ''); ?>
<?php
/*
 * The two live regions exist ONCE, in the shell, and every screen writes into
 * them; screens do not create their own (template-shell.md §3). They are inside
 * `main` because that is where the SPEC's markup puts them.
 */
?>
<p class="k-sr" role="status" aria-live="polite" id="k-live-status"></p>
<div role="alert" id="k-live-alert"></div>
</main><!-- /#main -->

<?php
/*
 * Copilot dock — `complementary`, after `main` in the DOM in every mode
 * (accessibility.md §3.2 item 6). It is built in stage 6 with the conversation
 * template; the landmark is emitted hidden now so the DOM order the
 * accessibility spec fixes is correct from this stage rather than retrofitted.
 */
?>
<aside class="k-copilot" role="complementary" aria-label="Klytos AI" hidden></aside>

<?php
/*
 * Status bar — mono facts. "It is not decoration; it is the fastest way to see
 * the server is healthy" (README, The shell).
 */
$renderStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
$renderMs    = $renderStart !== null ? (int) round( ( microtime( true ) - (float) $renderStart ) * 1000 ) : null;

/**
 * Filter: the degraded fact on the status bar's left side.
 *
 * When a subsystem is unhealthy the left side gains ONE fact with a link. It
 * never becomes a banner and never grows to two lines (template-shell.md §1),
 * so a listener returns a short phrase or nothing at all.
 *
 * @param string $degraded
 */
$statusDegraded = klytos_apply_filters( 'admin.statusbar_degraded', '' );
?>
<footer class="k-statusbar">
    <span class="k-statusbar-left">
        <span>Klytos <?php echo klytos_esc_html( $app->getVersion() ); ?></span>
        <span aria-hidden="true">&middot;</span>
        <span>PHP <?php echo klytos_esc_html( PHP_VERSION ); ?></span>
        <?php if ( is_string( $statusDegraded ) && $statusDegraded !== '' ): ?>
            <span class="k-statusbar-degraded"><?php echo klytos_kses_post( $statusDegraded ); ?></span>
        <?php endif; ?>
    </span>
    <span class="k-statusbar-right" id="k-statusbar-right" data-online-text="<?php echo klytos_esc_attr( $renderMs !== null ? __( 'shell.rendered_in', [ 'ms' => $renderMs ] ) : '' ); ?>" data-offline-text="<?php echo klytos_esc_attr( __( 'shell.offline' ) ); ?>">
        <?php if ( $renderMs !== null ): ?>
            <?php echo klytos_esc_html( __( 'shell.rendered_in', [ 'ms' => $renderMs ] ) ); ?>
        <?php endif; ?>
    </span>
</footer>

<?php
/*
 * Command palette — ⌘K / Ctrl+K, the only global shortcut (accessibility.md
 * §3.3). Closed, there is nothing in the DOM but the trigger and this shell;
 * the options are built from the navigation the server already sent, which is
 * why the palette never has a loading state (template-shell.md §1).
 */
?>
<div class="k-palette" id="k-palette" hidden>
    <div class="k-palette-window" role="dialog" aria-modal="true" aria-label="<?php echo klytos_esc_attr( __( 'shell.palette' ) ); ?>">
        <input
            type="text"
            class="k-palette-input"
            id="k-palette-input"
            role="combobox"
            aria-expanded="true"
            aria-controls="k-palette-list"
            aria-autocomplete="list"
            autocomplete="off"
            spellcheck="false"
            placeholder="<?php echo klytos_esc_attr( __( 'shell.palette_placeholder' ) ); ?>"
            data-testid="shell.palette_input">
        <ul class="k-palette-list" id="k-palette-list" role="listbox" aria-label="<?php echo klytos_esc_attr( __( 'shell.palette' ) ); ?>"></ul>
        <p class="k-palette-empty" id="k-palette-empty" hidden></p>
    </div>
</div>
</div><!-- /.k-shell -->
<?php
/*
 * The palette searches an index the server already sent — the same
 * capability-filtered navigation the sidebar drew, so it can never offer a
 * screen the person cannot open, and it never needs a loading state.
 */
$paletteItems = [];
foreach ( ( $navGroups ?? klytos_admin_nav_groups() ) as $paletteGroup ) {
    foreach ( $paletteGroup['items'] as $paletteItem ) {
        $paletteItems[] = [
            'label' => ! empty( $paletteItem['literal'] ) ? $paletteItem['label'] : __( $paletteItem['label'] ),
            'group' => __( $paletteGroup['caption'] ),
            'url'   => $paletteItem['url'],
        ];
    }
}
$paletteJson = json_encode( $paletteItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
$noResultsJson = json_encode( __( 'shell.palette_no_results' ), JSON_UNESCAPED_UNICODE );
?>
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
window.__KLYTOS_SHELL__ = {
    noResults: <?php echo $noResultsJson; ?>,
    items: <?php echo $paletteJson; ?>
};
</script>
<script src="<?php echo klytos_esc_url( $adminPath . 'assets/js/klytos-shell.js' ); ?>?v=<?php echo klytos_esc_attr( $app->getVersion() ); ?>" nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"></script>
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
document.querySelectorAll('.confirm-revoke-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        if (!confirm('<?php echo klytos_esc_js( __( 'mcp.confirm_revoke' ) ); ?>')) {
            e.preventDefault();
        }
    });
});

/* Confirm dialogs — buttons/forms with data-confirm attribute */
document.querySelectorAll('[data-confirm]').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (!confirm(this.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    });
});

/* Custom field actions — event delegation for dynamic elements */
document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-action]');
    if (!btn) return;
    var action = btn.getAttribute('data-action');
    var target = btn.getAttribute('data-target');

    if (action === 'remove-parent') {
        btn.parentElement.remove();
    } else if (action === 'remove-closest' && target) {
        var el = btn.closest(target);
        if (el) el.remove();
    } else if (action === 'add-gallery-row' || action === 'add-rel-row') {
        var list = document.getElementById(target);
        if (!list) return;
        var name = btn.getAttribute('data-name');
        var placeholder = action === 'add-gallery-row' ? 'Image URL' : 'Entry slug';
        var div = document.createElement('div');
        div.className = 'flex-center flex-gap-sm mb-1';
        div.innerHTML = '<input type="url" name="' + name + '[]" class="form-control flex-1" placeholder="' + placeholder + '">' +
                        '<button type="button" class="btn btn-danger btn-sm" data-action="remove-parent">x</button>';
        list.appendChild(div);
    } else if (action === 'add-repeater-row') {
        var container = document.getElementById(target);
        if (!container) return;
        var rowName = btn.getAttribute('data-name');
        var idx = container.children.length;
        if (typeof addRepeaterRow === 'function') {
            var subfields = JSON.parse(btn.getAttribute('data-subfields') || '[]');
            addRepeaterRow(target.replace('-rows', ''), rowName, subfields);
        }
    }
});

/* Range input → output sync */
document.querySelectorAll('[data-range-output]').forEach(function(input) {
    input.addEventListener('input', function() {
        var out = document.getElementById(this.getAttribute('data-range-output'));
        if (out) out.textContent = this.value;
    });
});

/* Color picker ↔ text sync */
document.querySelectorAll('[data-color-sync]').forEach(function(textInput) {
    var colorId = textInput.getAttribute('data-color-sync');
    var colorInput = document.getElementById(colorId);
    if (!colorInput) return;
    textInput.addEventListener('input', function() { colorInput.value = this.value; });
    colorInput.addEventListener('input', function() { textInput.value = this.value; });
});

/* Selection Cards — highlight on radio/checkbox change */
document.querySelectorAll('.selection-cards').forEach(function(group) {
    group.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach(function(input) {
        /* Set initial state */
        if (input.checked) {
            input.closest('.selection-card').classList.add('selected');
        }
        input.addEventListener('change', function() {
            if (this.type === 'radio') {
                group.querySelectorAll('.selection-card').forEach(function(card) {
                    card.classList.remove('selected');
                });
            }
            this.closest('.selection-card').classList.toggle('selected', this.checked);
        });
    });
});
</script>
<script src="<?php echo $adminPath ?? ''; ?>assets/js/klytos-password.js" nonce="<?php echo $cspNonce ?? ''; ?>"></script>
<?php klytos_do_action('admin.footer', $cspNonce ?? ''); ?>
<?php
$currentUser = klytos_current_user();
if ( $app->isDevMode() && in_array( $currentUser['role'] ?? '', ['owner', 'admin'], true ) ):
?>
    <link rel="stylesheet" href="<?php echo klytos_esc_url( $adminPath . 'css/dev-bar.css' ); ?>" nonce="<?php echo klytos_esc_attr( $cspNonce ?? '' ); ?>">
    <script nonce="<?php echo klytos_esc_attr( $cspNonce ?? '' ); ?>">
        window.__KLYTOS_DEVBAR_DATA__ = <?php echo json_encode(
            \Klytos\Core\DevBar::getInstance()->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ); ?>;
        window.__KLYTOS_DEVBAR_CONFIG__ = <?php echo json_encode(
            $app->getSiteConfig()->getValue( 'developer', [] ),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ); ?>;
    </script>
    <script src="<?php echo klytos_esc_url( $adminPath . 'js/dev-bar.js' ); ?>" nonce="<?php echo klytos_esc_attr( $cspNonce ?? '' ); ?>"></script>
<?php endif; ?>
</body>
</html>
