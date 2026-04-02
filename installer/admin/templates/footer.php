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
