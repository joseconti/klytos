<?php
/**
 * Klytos Admin — Footer Template
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 */
?>
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
</script>
</body>
</html>
