<?php
/**
 * Klytos Admin — Security Settings
 * Two-factor authentication configuration for the current user.
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

// ─── Handle POST actions ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf($_POST['csrf'] ?? '') && $userId) {
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

    // Refresh config after changes.
    $tfConfig = $twoFactor->getUserConfig($userId);
}

$csrf = $auth->getCsrfToken();
$totpSetupSecret = $_SESSION['klytos_totp_setup_secret'] ?? null;
$totpSetupUri    = $_SESSION['klytos_totp_setup_uri'] ?? null;

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- ─── Recovery Codes (shown once after generation) ─── -->
<?php if ($recoveryCodes): ?>
<div class="card" style="border: 2px solid #f59e0b; background: #fffbeb;">
    <div class="card-header"><h3><?php echo __('security.recovery_codes_title'); ?></h3></div>
    <p style="color:#92400e;margin-bottom:1rem;"><?php echo __('security.recovery_codes_warning'); ?></p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;max-width:400px;margin-bottom:1rem;">
        <?php foreach ($recoveryCodes as $code): ?>
            <code style="background:#fff;padding:0.5rem;border-radius:6px;text-align:center;font-size:1.1rem;border:1px solid #e5e7eb;"><?php echo htmlspecialchars($code); ?></code>
        <?php endforeach; ?>
    </div>
    <p style="color:#92400e;font-size:0.85rem;"><?php echo __('security.recovery_codes_count', ['count' => count($recoveryCodes)]); ?></p>
</div>
<?php endif; ?>

<!-- ─── 2FA Status Overview ─── -->
<div class="card">
    <div class="card-header">
        <h3><?php echo __('security.2fa_title'); ?></h3>
        <?php if ($tfConfig['enabled'] ?? false): ?>
            <span style="background:#dcfce7;color:#166534;padding:0.25rem 0.75rem;border-radius:20px;font-size:0.8rem;font-weight:600;"><?php echo __('security.2fa_active'); ?></span>
        <?php else: ?>
            <span style="background:#fef2f2;color:#dc2626;padding:0.25rem 0.75rem;border-radius:20px;font-size:0.8rem;font-weight:600;"><?php echo __('security.2fa_inactive'); ?></span>
        <?php endif; ?>
    </div>
    <p style="color:#64748b;margin-bottom:1.5rem;"><?php echo __('security.2fa_description'); ?></p>

    <?php if ($tfConfig['enabled'] ?? false): ?>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
        <?php foreach ($tfConfig['methods'] as $method): ?>
            <span style="background:#eff6ff;color:#2563eb;padding:0.25rem 0.75rem;border-radius:6px;font-size:0.85rem;font-weight:600;">
                <?php echo htmlspecialchars(__('security.method_' . $method)); ?>
            </span>
        <?php endforeach; ?>
    </div>
    <p style="font-size:0.85rem;color:#64748b;">
        <?php echo __('security.recovery_codes_remaining', ['count' => $tfConfig['recovery_codes_left']]); ?>
    </p>
    <?php endif; ?>
</div>

<!-- ─── TOTP (Authenticator App) ─── -->
<div class="card">
    <div class="card-header"><h3><?php echo __('security.totp_title'); ?></h3></div>
    <p style="color:#64748b;margin-bottom:1rem;"><?php echo __('security.totp_description'); ?></p>

    <?php if ($tfConfig['totp_configured'] ?? false): ?>
        <p style="color:#166534;font-weight:600;margin-bottom:1rem;"><?php echo __('security.totp_active'); ?></p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="totp_disable">
            <button type="submit" class="btn btn-outline" onclick="return confirm('<?php echo __('security.confirm_disable'); ?>');"><?php echo __('security.disable_totp'); ?></button>
        </form>
    <?php elseif ($totpSetupSecret): ?>
        <!-- TOTP Setup Step 2: Verify -->
        <div style="background:#f8fafc;padding:1.5rem;border-radius:8px;margin-bottom:1rem;">
            <p style="font-weight:600;margin-bottom:0.5rem;"><?php echo __('security.totp_scan_qr'); ?></p>
            <div style="text-align:center;margin:1rem 0;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($totpSetupUri); ?>" alt="QR Code" style="border-radius:8px;">
            </div>
            <p style="font-size:0.85rem;color:#64748b;margin-bottom:0.5rem;"><?php echo __('security.totp_manual_key'); ?></p>
            <code style="display:block;background:#fff;padding:0.5rem;border-radius:6px;font-size:0.9rem;word-break:break-all;border:1px solid #e5e7eb;"><?php echo htmlspecialchars($totpSetupSecret); ?></code>
        </div>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
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
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="totp_setup">
            <button type="submit" class="btn btn-primary"><?php echo __('security.setup_totp'); ?></button>
        </form>
    <?php endif; ?>
</div>

<!-- ─── Magic Link (Email) ─── -->
<div class="card">
    <div class="card-header"><h3><?php echo __('security.email_title'); ?></h3></div>
    <p style="color:#64748b;margin-bottom:1rem;"><?php echo __('security.email_description'); ?></p>

    <?php if (in_array('email', $tfConfig['methods'] ?? [], true)): ?>
        <p style="color:#166534;font-weight:600;margin-bottom:1rem;"><?php echo __('security.email_active'); ?></p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="email_disable">
            <button type="submit" class="btn btn-outline"><?php echo __('security.disable_email'); ?></button>
        </form>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="email_enable">
            <button type="submit" class="btn btn-primary"><?php echo __('security.enable_email'); ?></button>
        </form>
    <?php endif; ?>
</div>

<!-- ─── Passkeys (WebAuthn) ─── -->
<div class="card">
    <div class="card-header"><h3><?php echo __('security.passkey_title'); ?></h3></div>
    <p style="color:#64748b;margin-bottom:1rem;"><?php echo __('security.passkey_description'); ?></p>

    <?php if (!empty($tfConfig['passkeys'])): ?>
    <table class="table" style="margin-bottom:1rem;">
        <thead><tr><th><?php echo __('common.name'); ?></th><th><?php echo __('common.date'); ?></th><th><?php echo __('common.actions'); ?></th></tr></thead>
        <tbody>
            <?php foreach ($tfConfig['passkeys'] as $pk): ?>
            <tr>
                <td><?php echo htmlspecialchars($pk['label']); ?></td>
                <td><?php echo htmlspecialchars($pk['created_at']); ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action" value="remove_passkey">
                        <input type="hidden" name="credential_id" value="<?php echo htmlspecialchars($pk['credential_id']); ?>">
                        <button type="submit" class="btn btn-sm btn-outline" onclick="return confirm('<?php echo __('common.confirm_delete'); ?>');"><?php echo __('common.delete'); ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <button type="button" class="btn btn-primary" id="register-passkey-btn"><?php echo __('security.add_passkey'); ?></button>

    <script>
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
    <p style="color:#64748b;margin-bottom:1rem;">
        <?php echo __('security.recovery_description'); ?>
        <strong><?php echo __('security.recovery_codes_remaining', ['count' => $tfConfig['recovery_codes_left']]); ?></strong>
    </p>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="action" value="regenerate_recovery">
        <button type="submit" class="btn btn-outline" onclick="return confirm('<?php echo __('security.confirm_regenerate'); ?>');"><?php echo __('security.regenerate_recovery'); ?></button>
    </form>
</div>

<!-- ─── Disable All 2FA ─── -->
<div class="card" style="border-color:#fecaca;">
    <div class="card-header"><h3 style="color:#dc2626;"><?php echo __('security.disable_all_title'); ?></h3></div>
    <p style="color:#64748b;margin-bottom:1rem;"><?php echo __('security.disable_all_description'); ?></p>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="action" value="disable_all">
        <button type="submit" class="btn" style="background:#dc2626;color:#fff;" onclick="return confirm('<?php echo __('security.confirm_disable_all'); ?>');"><?php echo __('security.disable_all_button'); ?></button>
    </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
