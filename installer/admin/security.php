<?php

/**
 * Klytos Admin — Security Settings
 * Two-factor authentication configuration for the current user.
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

$pageTitle = __('security.title');
$auth      = $app->getAuth();
$twoFactor = $app->getTwoFactor();
$success   = '';
$error     = '';
$recoveryCodes = null;

// Resolve current user ID.
$username = $auth->getUsername();
$userId   = null;
$users    = $app->getStorage()->list('users');
foreach ($users as $u) {
    if (($u['username'] ?? '') === $username) {
        $userId = $u['id'];
        break;
    }
}

if (!$userId) {
    $error = 'User not found.';
}

$tfConfig = $userId ? $twoFactor->getUserConfig($userId) : [];

// This file used to decrypt the main config here, for one consumer: the
// admin_pass_hash comparison in change_encryption_level. That comparison now
// goes to the user record (D-056), and every remaining $mainConfig user below
// re-reads the file for itself (:170, :218, :491), so the read is gone rather
// than left orphaned. Checked before removing, per L-007: nothing between this
// point and the next assignment reads it.

// ─── Handle POST actions ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() && $userId) {
    $action = $_POST['action'] ?? '';

    // ── TOTP Setup ──
    if ($action === 'totp_setup') {
        $secret = $twoFactor->generateTotpSecret();
        $_SESSION['klytos_totp_setup_secret'] = $secret;
        $siteConfig = $app->getSiteConfig()->get();
        $user = $app->getStorage()->read('users', $userId);
        $totpUri = $twoFactor->getTotpUri($secret, $user['email'] ?? $username, $siteConfig['site_name'] ?? 'Klytos');
        $_SESSION['klytos_totp_setup_uri'] = $totpUri;
    }

    // ── TOTP Verify & Enable ──
    if ($action === 'totp_verify') {
        $secret = $_SESSION['klytos_totp_setup_secret'] ?? '';
        $code   = trim($_POST['totp_code'] ?? '');
        if ($secret && $twoFactor->verifyTotp($secret, $code)) {
            $twoFactor->enableTotp($userId, $secret);
            unset($_SESSION['klytos_totp_setup_secret'], $_SESSION['klytos_totp_setup_uri']);
            // Generate recovery codes if first 2FA method.
            if ($twoFactor->countRecoveryCodes($userId) === 0) {
                $recoveryCodes = $twoFactor->generateRecoveryCodes($userId);
            }
            $success = __('security.totp_enabled');
        } else {
            $error = __('security.2fa_invalid_code');
        }
    }

    // ── TOTP Disable ──
    if ($action === 'totp_disable') {
        $twoFactor->disableTotp($userId);
        $success = __('security.totp_disabled');
    }

    // ── Magic Link Enable ──
    if ($action === 'email_enable') {
        $twoFactor->enableMagicLink($userId);
        if ($twoFactor->countRecoveryCodes($userId) === 0) {
            $recoveryCodes = $twoFactor->generateRecoveryCodes($userId);
        }
        $success = __('security.email_enabled');
    }

    // ── Magic Link Disable ──
    if ($action === 'email_disable') {
        $twoFactor->disableMagicLink($userId);
        $success = __('security.email_disabled');
    }

    // ── Regenerate Recovery Codes ──
    if ($action === 'regenerate_recovery') {
        $recoveryCodes = $twoFactor->generateRecoveryCodes($userId);
        $success = __('security.recovery_regenerated');
    }

    // ── Disable All 2FA ──
    if ($action === 'disable_all') {
        $twoFactor->disableAll($userId);
        $success = __('security.2fa_disabled');
    }

    // ── Remove Passkey ──
    if ($action === 'remove_passkey') {
        $credId = $_POST['credential_id'] ?? '';
        if ($credId) {
            $twoFactor->removePasskey($userId, $credId);
            $success = __('security.passkey_removed');
        }
    }

    // ── Change Encryption Level (site.configure) ──
    // The page itself is gated at 'security.self' — every role may manage
    // their OWN second factor. These two actions are site-wide and
    // destructive, so they are re-gated here at the higher tier: the map
    // entry is the floor, not the ceiling.
    //
    // This replaces a hand-rolled in_array( $role, ['owner','admin'] ) — a
    // second authorization decision point living outside the matrix, which
    // slice 3's "defined exactly once" guard never saw because that guard only
    // scans core/*.php. 'site.configure' resolves to the same owner+admin set,
    // so no one gains or loses access; the difference is that the matrix now
    // decides, and a future change to it reaches this branch too.
    //
    // It also changes the failure mode. Before, a non-admin POST simply fell
    // through both `if`s and rendered the page again with no error, so the
    // caller could not tell a refusal from a no-op.
    if ( $action === 'change_encryption_level' ) {
        klytos_require_permission( 'site.configure' );
        $newLevel = $_POST['new_encryption_level'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        // Re-authentication goes through the SAME authority as the login gate
        // (D-056): the user record. It used to verify against
        // config['admin_pass_hash'], which was already the wrong credential —
        // and would have become a trap the moment password rotation started
        // working, since it would have gone on demanding the OLD password
        // forever (audit NEW-37). UserManager::authenticate() is reused rather
        // than a third comparison written: admin/profile.php:45 and
        // partials/ai-panel-profile.php:33 already re-authenticate this way.
        // getByUsername()/authenticate() and NOT getById(): getById() throws
        // when the record is missing, which would answer a wrong password with
        // an uncaught RuntimeException. authenticate() returns null for every
        // failure — unknown user, suspended account, wrong password — which is
        // exactly one refusal for the caller and no account oracle.
        if ( !in_array( $newLevel, ['basic', 'medium', 'professional'], true ) ) {
            $error = __( 'security.invalid_level' );
        } elseif ( $app->getUserManager()->authenticate( $auth->getUsername(), $confirmPass ) === null ) {
            $error = __( 'security.wrong_password' );
        } else {
            $storage = $app->getStorage();
            $storage->changeEncryptionLevel( $newLevel );
            $success = __( 'security.level_changed' );
        }
    }

    // ── Confirm Recovery Keys (site.configure) ──
    if ( $action === 'confirm_recovery_keys' ) {
        klytos_require_permission( 'site.configure' );

        $mainConfig = $app->getStorage()->readFrom( $app->getConfigPath(), 'config.json.enc' );
        $mainConfig['recovery_keys_confirmed']    = true;
        $mainConfig['recovery_keys_confirmed_at'] = date( 'c' );
        $app->getStorage()->writeTo( $app->getConfigPath(), 'config.json.enc', $mainConfig );
        $success = __( 'security.recovery_confirmed' );
    }

    // ── Identity Key Download: no longer routed through this page ──
    // The 'request_identity_download' branch was REMOVED in slice 5 (audit
    // S-12). It gated on users.manage and then 302-redirected to
    // api/download-identity.php — and a browser follows a 302 with a GET, so
    // that redirect was precisely what made a state-writing secret export
    // answer GET. A redirect cannot carry a POST, so the fix is structural:
    // the form posts straight to the endpoint, which now requires POST and a
    // CSRF token and is gated at users.manage by the gate map.
    //
    // Removed rather than left in place (per L-007, having stated what would
    // still execute it): a stale cached page could POST this action, and the
    // branch would then redirect to a guaranteed 405 — a path that cannot
    // succeed. Falling through to a re-render is the less misleading outcome.

    // ── Generate Identity Keys (site.configure) ──
    // Regenerating the site identity invalidates the previous key pair, so it
    // sits at the same tier as the encryption-level change.
    if ( $action === 'generate_identity_keys' ) {
        klytos_require_permission( 'site.configure' );

        $rsaKeys     = \Klytos\Core\Encryption::generateRsaKeyPair();
        $enc         = $app->getStorage()->getEncryption();
        $configPath  = $app->getConfigPath();

        $identityPubData = [
            'public_key'  => $rsaKeys['public_key'],
            'fingerprint' => $rsaKeys['fingerprint'],
            'created_at'  => date( 'c' ),
            'admin_user'  => $username,
        ];
        $identityPrivData = [
            'private_key' => $rsaKeys['private_key'],
            'fingerprint' => $rsaKeys['fingerprint'],
            'created_at'  => date( 'c' ),
            'admin_user'  => $username,
        ];

        file_put_contents( $configPath . '/admin-identity.pub.enc', $enc->encrypt( $identityPubData ), LOCK_EX );
        file_put_contents( $configPath . '/admin-identity.priv.enc', $enc->encrypt( $identityPrivData ), LOCK_EX );

        // Update config with fingerprint.
        $mainConfig = $app->getStorage()->readFrom( $configPath, 'config.json.enc' );
        $mainConfig['identity_fingerprint'] = $rsaKeys['fingerprint'];
        $app->getStorage()->writeTo( $configPath, 'config.json.enc', $mainConfig );

        $success = __( 'security.identity_generated' );
    }

    // Refresh config after changes.
    $tfConfig = $twoFactor->getUserConfig($userId);
}

$csrf = $auth->getCsrfToken();
$totpSetupSecret = $_SESSION['klytos_totp_setup_secret'] ?? null;
$totpSetupUri    = $_SESSION['klytos_totp_setup_uri'] ?? null;

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.security.before' ); ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html($error); ?></div>
<?php endif; ?>

<!-- ─── Recovery Codes (shown once after generation) ─── -->
<?php if ($recoveryCodes): ?>
<div class="card security-recovery-card" style="border: 2px solid var(--klytos-warning); background: var(--klytos-warning-subtle);">
    <div class="card-header"><h3><?php echo __('security.recovery_codes_title'); ?></h3></div>
    <p class="security-recovery-text mb-2" style="color:#92400e;"><?php echo __('security.recovery_codes_warning'); ?></p>
    <div class="grid-2 max-w-sm mb-2">
        <?php foreach ($recoveryCodes as $code): ?>
            <code class="security-recovery-code text-center text-lg" style="background:var(--klytos-surface);padding:0.5rem;border-radius:6px;border:1px solid var(--klytos-border);"><?php echo klytos_esc_html($code); ?></code>
        <?php endforeach; ?>
    </div>
    <p class="security-recovery-text text-sm" style="color:#92400e;"><?php echo __('security.recovery_codes_count', ['count' => count($recoveryCodes)]); ?></p>
</div>
<?php endif; ?>

<?php klytos_do_action( 'admin.security.before_2fa' ); ?>

<!-- ─── 2FA Status Overview ─── -->
<div class="card">
    <div class="card-header">
        <h3><?php echo __('security.2fa_title'); ?></h3>
        <?php if ($tfConfig['enabled'] ?? false): ?>
            <span class="security-status-active badge-status badge-active text-xs font-bold" style="padding:0.25rem 0.75rem;border-radius:20px;"><?php echo __('security.2fa_active'); ?></span>
        <?php else: ?>
            <span class="security-status-inactive badge-status badge-inactive text-xs font-bold" style="padding:0.25rem 0.75rem;border-radius:20px;"><?php echo __('security.2fa_inactive'); ?></span>
        <?php endif; ?>
    </div>
    <p class="text-muted mb-3"><?php echo __('security.2fa_description'); ?></p>

    <?php if ($tfConfig['enabled'] ?? false): ?>
    <div class="flex flex-gap-sm flex-wrap mb-2">
        <?php foreach ($tfConfig['methods'] as $method): ?>
            <span class="security-method-badge text-sm font-bold" style="background:#eff6ff;color:#2563eb;padding:0.25rem 0.75rem;border-radius:6px;">
                <?php echo klytos_esc_html(__('security.method_' . $method)); ?>
            </span>
        <?php endforeach; ?>
    </div>
    <p class="text-sm text-muted">
        <?php echo __('security.recovery_codes_remaining', ['count' => $tfConfig['recovery_codes_left']]); ?>
    </p>
    <?php endif; ?>
</div>

<!-- ─── TOTP (Authenticator App) ─── -->
<div class="card">
    <div class="card-header"><h3><?php echo __('security.totp_title'); ?></h3></div>
    <p class="text-muted mb-2"><?php echo __('security.totp_description'); ?></p>

    <?php if ($tfConfig['totp_configured'] ?? false): ?>
        <p class="security-active-text font-bold mb-2" style="color:#166534;"><?php echo __('security.totp_active'); ?></p>
        <form method="post">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="totp_disable">
            <button type="submit" class="btn btn-outline" data-confirm="<?php echo klytos_esc_attr(__('security.confirm_disable')); ?>"><?php echo __('security.disable_totp'); ?></button>
        </form>
    <?php elseif ($totpSetupSecret): ?>
        <!-- TOTP Setup Step 2: Verify -->
        <div class="totp-setup-box">
            <p class="font-bold mb-1"><?php echo __('security.totp_scan_qr'); ?></p>
            <div id="klytos-qr-code" class="text-center" style="margin:1rem 0;"></div>
            <p class="text-sm text-muted mb-1"><?php echo __('security.totp_manual_key'); ?></p>
            <code class="totp-manual-key"><?php echo klytos_esc_html($totpSetupSecret); ?></code>
        </div>
        <script nonce="<?php echo klytos_esc_attr($cspNonce); ?>" src="<?php echo klytos_esc_url($basePath . 'admin/assets/js/klytos-qrcode.js'); ?>"></script>
        <script nonce="<?php echo klytos_esc_attr($cspNonce); ?>">
            KlytosQR.generate('klytos-qr-code', <?php echo json_encode($totpSetupUri, JSON_HEX_TAG | JSON_HEX_AMP); ?>, {moduleSize: 5, quietZone: 4});
        </script>
        <form method="post">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="totp_verify">
            <div class="form-group">
                <label><?php echo __('security.enter_totp_code'); ?></label>
                <input type="text" name="totp_code" class="form-control" maxlength="6" pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code" style="max-width:200px;text-align:center;font-size:1.3rem;letter-spacing:0.2em;" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo __('security.verify_and_enable'); ?></button>
        </form>
    <?php else: ?>
        <!-- TOTP Setup Step 1: Start -->
        <form method="post">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="totp_setup">
            <button type="submit" class="btn btn-primary"><?php echo __('security.setup_totp'); ?></button>
        </form>
    <?php endif; ?>
</div>

<!-- ─── Magic Link (Email) ─── -->
<div class="card">
    <div class="card-header"><h3><?php echo __('security.email_title'); ?></h3></div>
    <p class="text-muted mb-2"><?php echo __('security.email_description'); ?></p>

    <?php if (in_array('email', $tfConfig['methods'] ?? [], true)): ?>
        <p class="security-active-text font-bold mb-2" style="color:#166534;"><?php echo __('security.email_active'); ?></p>
        <form method="post">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="email_disable">
            <button type="submit" class="btn btn-outline"><?php echo __('security.disable_email'); ?></button>
        </form>
    <?php else: ?>
        <form method="post">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="action" value="email_enable">
            <button type="submit" class="btn btn-primary"><?php echo __('security.enable_email'); ?></button>
        </form>
    <?php endif; ?>
</div>

<!-- ─── Passkeys (WebAuthn) ─── -->
<div class="card">
    <div class="card-header"><h3><?php echo __('security.passkey_title'); ?></h3></div>
    <p class="text-muted mb-2"><?php echo __('security.passkey_description'); ?></p>

    <?php if (!empty($tfConfig['passkeys'])): ?>
    <table class="table mb-2">
        <thead><tr><th><?php echo __('common.name'); ?></th><th><?php echo __('common.date'); ?></th><th><?php echo __('common.actions'); ?></th></tr></thead>
        <tbody>
            <?php foreach ($tfConfig['passkeys'] as $pk): ?>
            <tr>
                <td><?php echo klytos_esc_html($pk['label']); ?></td>
                <td><?php echo klytos_esc_html($pk['created_at']); ?></td>
                <td>
                    <form method="post" class="inline-form">
                        <?php echo klytos_csrf_field(); ?>
                        <input type="hidden" name="action" value="remove_passkey">
                        <input type="hidden" name="credential_id" value="<?php echo klytos_esc_attr($pk['credential_id']); ?>">
                        <button type="submit" class="btn btn-sm btn-outline" data-confirm="<?php echo klytos_esc_attr(__('common.confirm_delete')); ?>"><?php echo __('common.delete'); ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <button type="button" class="btn btn-primary" id="register-passkey-btn"><?php echo __('security.add_passkey'); ?></button>

    <script nonce="<?php echo klytos_esc_attr($cspNonce); ?>">
    document.getElementById('register-passkey-btn').addEventListener('click', async function() {
        try {
            // Get registration challenge from server.
            var resp = await fetch('<?php echo Helpers::getBasePath(); ?>admin/api/webauthn-challenge.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'register_challenge', csrf: '<?php echo $csrf; ?>'})
            });
            var options = await resp.json();

            // Decode base64url fields.
            options.challenge = base64UrlToBuffer(options.challenge);
            options.user.id = base64UrlToBuffer(options.user.id);
            if (options.excludeCredentials) {
                options.excludeCredentials = options.excludeCredentials.map(function(c) {
                    c.id = base64UrlToBuffer(c.id);
                    return c;
                });
            }

            var credential = await navigator.credentials.create({publicKey: options});

            // Send attestation to server.
            var attestation = {
                clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                attestationObject: bufferToBase64Url(credential.response.attestationObject)
            };

            var label = prompt('<?php echo __('security.passkey_label_prompt'); ?>', 'Passkey');
            if (!label) return;

            var verifyResp = await fetch('<?php echo Helpers::getBasePath(); ?>admin/api/webauthn-challenge.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'register_complete', csrf: '<?php echo $csrf; ?>', attestation: attestation, label: label})
            });
            var result = await verifyResp.json();

            if (result.success) {
                window.location.reload();
            } else {
                alert(result.error || 'Registration failed');
            }
        } catch (e) {
            console.error('Passkey registration error:', e);
            alert('Passkey registration failed: ' + e.message);
        }
    });

    function base64UrlToBuffer(b64) {
        var s = b64.replace(/-/g, '+').replace(/_/g, '/');
        while (s.length % 4) s += '=';
        var bin = atob(s);
        var buf = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
        return buf.buffer;
    }
    function bufferToBase64Url(buf) {
        var bytes = new Uint8Array(buf);
        var s = '';
        for (var i = 0; i < bytes.length; i++) s += String.fromCharCode(bytes[i]);
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }
    </script>
</div>

<!-- ─── Recovery Codes ─── -->
<?php if ($tfConfig['enabled'] ?? false): ?>
<div class="card">
    <div class="card-header"><h3><?php echo __('security.recovery_title'); ?></h3></div>
    <p class="text-muted mb-2">
        <?php echo __('security.recovery_description'); ?>
        <strong><?php echo __('security.recovery_codes_remaining', ['count' => $tfConfig['recovery_codes_left']]); ?></strong>
    </p>
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="regenerate_recovery">
        <button type="submit" class="btn btn-outline" data-confirm="<?php echo klytos_esc_attr(__('security.confirm_regenerate')); ?>"><?php echo __('security.regenerate_recovery'); ?></button>
    </form>
</div>

<!-- ─── Disable All 2FA ─── -->
<div class="card security-danger-card" style="border-color:var(--klytos-error-subtle);">
    <div class="card-header"><h3 class="text-error"><?php echo __('security.disable_all_title'); ?></h3></div>
    <p class="text-muted mb-2"><?php echo __('security.disable_all_description'); ?></p>
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="disable_all">
        <button type="submit" class="btn btn-danger" data-confirm="<?php echo klytos_esc_attr(__('security.confirm_disable_all')); ?>"><?php echo __('security.disable_all_button'); ?></button>
    </form>
</div>
<?php endif; ?>

<?php klytos_do_action( 'admin.security.after_2fa' ); ?>

<?php
// ─── Encryption & Recovery Section ─────────────────────────
// Visibility mirrors the capability its POST handlers require, so the UI
// cannot offer an action the gate will refuse. Asks the matrix rather than
// comparing the role by hand: this was the LAST hand-rolled decision point in
// the product (S-04), and it survived slice 4's first pass precisely because
// it gates markup rather than a handler — the `code-reviewer` pass caught it.
//
// klytos_has_permission() rather than klytos_require_permission(): this is a
// visibility decision, not an enforcement point. The enforcement lives on the
// POST branches above.
if ( klytos_has_permission( 'site.configure' ) ):

// Reload config in case POST handlers modified it.
$mainConfig       = $app->getStorage()->readFrom( $app->getConfigPath(), 'config.json.enc' );
$encryptionLevel  = $mainConfig['encryption_level'] ?? 'basic';
$recoveryConfirmed = $mainConfig['recovery_keys_confirmed'] ?? false;
$identityFingerprint = $mainConfig['identity_fingerprint'] ?? null;
$hasIdentityKeys  = file_exists( $app->getConfigPath() . '/admin-identity.pub.enc' );

$levelLabels = [
    'basic'        => __( 'security.enc_basic' ),
    'medium'       => __( 'security.enc_medium' ),
    'professional' => __( 'security.enc_professional' ),
];
?>

<?php klytos_do_action( 'admin.security.before_encryption' ); ?>

<!-- ─── Encryption Level ─── -->
<div class="card">
    <div class="card-header">
        <h3><?php echo __( 'security.encryption_title' ); ?></h3>
        <span class="badge-status badge-active text-xs font-bold" style="padding:0.25rem 0.75rem;border-radius:20px;">
            <?php echo klytos_esc_html( $levelLabels[$encryptionLevel] ?? $encryptionLevel ); ?>
        </span>
    </div>
    <p class="text-muted mb-3"><?php echo __( 'security.encryption_description' ); ?></p>

    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="change_encryption_level">
        <div class="form-group">
            <label><?php echo __( 'security.encryption_level_label' ); ?></label>
            <select name="new_encryption_level" class="form-control" style="max-width:300px;">
                <option value="basic" <?php echo $encryptionLevel === 'basic' ? 'selected' : ''; ?>><?php echo __( 'security.enc_basic' ); ?></option>
                <option value="medium" <?php echo $encryptionLevel === 'medium' ? 'selected' : ''; ?>><?php echo __( 'security.enc_medium' ); ?></option>
                <option value="professional" <?php echo $encryptionLevel === 'professional' ? 'selected' : ''; ?>><?php echo __( 'security.enc_professional' ); ?></option>
            </select>
        </div>
        <div class="form-group">
            <label><?php echo __( 'security.current_password' ); ?></label>
            <input type="password" name="confirm_password" class="form-control" style="max-width:300px;" required>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __( 'security.change_level' ); ?></button>
    </form>
</div>

<!-- ─── Recovery Keys Status ─── -->
<div class="card" <?php if ( !$recoveryConfirmed ): ?>style="border: 2px solid var(--klytos-error);"<?php endif; ?>>
    <div class="card-header">
        <h3><?php echo __( 'security.recovery_keys_title' ); ?></h3>
        <?php if ( $recoveryConfirmed ): ?>
            <span class="badge-status badge-active text-xs font-bold" style="padding:0.25rem 0.75rem;border-radius:20px;"><?php echo __( 'security.confirmed' ); ?></span>
        <?php else: ?>
            <span class="badge-status badge-inactive text-xs font-bold" style="padding:0.25rem 0.75rem;border-radius:20px;background:#fef2f2;color:#dc2626;"><?php echo __( 'security.not_confirmed' ); ?></span>
        <?php endif; ?>
    </div>

    <div style="display:grid; gap:1rem; grid-template-columns: 1fr 1fr; margin-bottom:1rem;">
        <!-- Encryption Key -->
        <div style="padding:1rem; border:1px solid var(--klytos-border); border-radius:8px;">
            <h4 style="margin-bottom:0.5rem;"><?php echo __( 'security.enc_key_title' ); ?></h4>
            <p class="text-muted text-sm"><?php echo __( 'security.enc_key_location' ); ?></p>
            <code class="text-sm" style="word-break:break-all;">config/.encryption_key</code>
        </div>

        <!-- Identity Key -->
        <div style="padding:1rem; border:1px solid var(--klytos-border); border-radius:8px;">
            <h4 style="margin-bottom:0.5rem;"><?php echo __( 'security.id_key_title' ); ?></h4>
            <?php if ( $identityFingerprint ): ?>
                <p class="text-muted text-sm"><?php echo __( 'security.fingerprint' ); ?>:</p>
                <code class="text-sm" style="word-break:break-all;"><?php echo klytos_esc_html( substr( $identityFingerprint, 0, 24 ) . '...' ); ?></code>
                <?php // Posts DIRECTLY to the export endpoint. It used to post here and be
                      // 302-redirected, which the browser follows as a GET — so a
                      // state-writing secret export answered GET (audit S-12). A redirect
                      // cannot carry a POST, so the form has to target the endpoint itself. ?>
                <?php $identityExportUrl = $basePath . 'admin/api/download-identity.php'; ?>
                <form method="post" action="<?php echo klytos_esc_url( $identityExportUrl ); ?>" style="margin-top:0.75rem;">
                    <?php echo klytos_csrf_field(); ?>
                    <button type="submit" class="btn btn-sm btn-outline"><?php echo __( 'security.download_identity' ); ?></button>
                </form>
            <?php else: ?>
                <p class="text-muted text-sm"><?php echo __( 'security.no_identity_keys' ); ?></p>
                <form method="post" style="margin-top:0.75rem;">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="generate_identity_keys">
                    <button type="submit" class="btn btn-sm btn-primary"><?php echo __( 'security.generate_identity' ); ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( !$recoveryConfirmed ): ?>
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="confirm_recovery_keys">
        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; margin-bottom:1rem;">
            <input type="checkbox" name="confirm_checkbox" required style="width:18px; height:18px;">
            <?php echo __( 'security.confirm_recovery_checkbox' ); ?>
        </label>
        <button type="submit" class="btn btn-primary"><?php echo __( 'security.confirm_recovery_btn' ); ?></button>
    </form>
    <?php endif; ?>
</div>

<?php klytos_do_action( 'admin.security.after_encryption' ); ?>

<?php endif; // owner/admin role check ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
