<?php

/**
 * Klytos Admin — Footer Template
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 */

?>
<?php klytos_do_action('admin.page.after_content', $GLOBALS['klytos_admin_page'] ?? ''); ?>
    </div><!-- /.admin-main -->
</div><!-- /.admin-content -->
</div><!-- /.admin-layout -->
<script nonce="<?php echo $cspNonce ?? ''; ?>">
document.querySelectorAll('.confirm-revoke-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        if (!confirm('<?php echo __( 'mcp.confirm_revoke' ); ?>')) {
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
