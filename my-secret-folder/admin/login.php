<?php
/**
 * Klytos Admin — Login Page
 * Handles password login + two-factor authentication challenge.
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

$auth  = $app->getAuth();
$error = '';
$info  = '';

// Already authenticated? Go to dashboard
if ($auth->isAuthenticated()) {
    Helpers::redirect(Helpers::url('admin/'));
}

// ─── Handle 2FA cancellation ────────────────────────────────
if (isset($_GET['cancel_2fa'])) {
    $auth->cancel2fa();
    Helpers::redirect(Helpers::url('admin/login.php'));
}

// ─── Handle Magic Link verification (GET with token) ────────
if (isset($_GET['magic_token']) && $auth->is2faPending()) {
    $token  = $_GET['magic_token'];
    $userId = $auth->get2faPendingUserId();

    if ($userId) {
        $twoFactor = $app->getTwoFactor();
        if ($twoFactor->verifyMagicLink($token, $userId)) {
            $auth->complete2fa();
            Helpers::redirect(Helpers::url('admin/'));
        } else {
            $error = __('security.2fa_invalid_code');
        }
    }
}

// ─── Handle 2FA verification POST ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->is2faPending()) {
    $csrf = $_POST['csrf'] ?? '';
    if (!$auth->validateCsrf($csrf)) {
        $error = __('common.error');
    } else {
        $method = $_POST['2fa_method'] ?? '';
        $code   = trim($_POST['2fa_code'] ?? '');
        $userId = $auth->get2faPendingUserId();

        if (!$userId) {
            $auth->cancel2fa();
            Helpers::redirect(Helpers::url('admin/login.php'));
        }

        $twoFactor = $app->getTwoFactor();
        $verified  = false;

        if ($method === 'totp') {
            $user = $app->getStorage()->read('users', $userId);
            $secret = $user['two_factor']['totp_secret'] ?? '';
            $verified = $twoFactor->verifyTotp($secret, $code);
        } elseif ($method === 'recovery') {
            $verified = $twoFactor->verifyRecoveryCode($userId, $code);
        } elseif ($method === 'email' || $method === 'emergency_email') {
            // Magic link: send email (works for both 2FA-email method and emergency recovery)
            $user = $app->getStorage()->read('users', $userId);
            $email = trim($user['email'] ?? '');
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $link = $twoFactor->createMagicLink($userId, $email);
                $siteConfig = $app->getSiteConfig()->get();
                $baseUrl = rtrim($siteConfig['site_url'] ?? '', '/');
                $magicUrl = $baseUrl . '/' . basename(dirname($_SERVER['SCRIPT_NAME'])) . '/login.php?magic_token=' . urlencode($link['token']);
                $twoFactor->sendMagicLinkEmail($email, $magicUrl, $app->getMailer());
                $info = __('security.emergency_email_sent');
            } else {
                $error = __('security.no_email');
            }
        }

        if ($verified) {
            $auth->complete2fa();
            Helpers::redirect(Helpers::url('admin/'));
        } elseif ($method !== 'email' && !$info) {
            $error = __('security.2fa_invalid_code');
        }
    }
}

// ─── Handle password login POST ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$auth->is2faPending() && !isset($_POST['2fa_method'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = $auth->login($username, $password);

    if ($result['success'] && !$result['requires_2fa']) {
        Helpers::redirect(Helpers::url('admin/'));
    } elseif ($result['success'] && $result['requires_2fa']) {
        // 2FA required — page will render the 2FA form below.
    } else {
        if (str_starts_with($result['error'], 'account_locked:')) {
            $minutes = (int) explode(':', $result['error'])[1];
            $error   = __( 'auth.account_locked', ['minutes' => $minutes]);
        } else {
            $error = __( 'auth.login_failed' );
        }
    }
}

$basePath = Helpers::getBasePath();
$show2fa  = $auth->is2faPending();

// Get available 2FA methods for the pending user.
$methods2fa = [];
if ($show2fa) {
    $userId = $auth->get2faPendingUserId();
    if ($userId) {
        $methods2fa = $app->getTwoFactor()->getEnabledMethods($userId);
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $app->getI18n()->getLocale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo __( 'auth.login' ); ?> — Klytos</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 2.5rem; width: 100%; max-width: 400px; margin: 1rem; }
        .logo { text-align: center; margin-bottom: 2rem; }
        .logo h1 { font-size: 1.8rem; color: #2563eb; font-weight: 700; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.3rem; }
        input { width: 100%; padding: 0.7rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; }
        input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .btn { width: 100%; padding: 0.75rem; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #1d4ed8; }
        .alert { padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-info { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .method-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .method-tab { flex: 1; min-width: 80px; padding: 0.5rem; border: 2px solid #e2e8f0; border-radius: 8px; background: #fff; cursor: pointer; text-align: center; font-size: 0.8rem; font-weight: 600; color: #64748b; transition: all 0.2s; }
        .method-tab:hover { border-color: #2563eb; color: #2563eb; }
        .method-tab.active { border-color: #2563eb; background: #eff6ff; color: #2563eb; }
        .method-panel { display: none; }
        .method-panel.active { display: block; }
        .link-cancel { display: block; text-align: center; margin-top: 1rem; color: #64748b; text-decoration: none; font-size: 0.85rem; }
        .link-cancel:hover { color: #2563eb; }
        .link-emergency { background: none; border: none; color: #64748b; font-size: 0.85rem; cursor: pointer; text-decoration: underline; padding: 0; width: 100%; text-align: center; }
        .link-emergency:hover { color: #dc2626; }
        .tfa-subtitle { text-align: center; color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem; }
        input[type="text"].code-input { text-align: center; font-size: 1.5rem; letter-spacing: 0.3em; font-family: monospace; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <h1>Klytos</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars( $error ); ?></div>
        <?php endif; ?>

        <?php if ($info): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars( $info ); ?></div>
        <?php endif; ?>

        <?php if (!$show2fa): ?>
        <!-- ─── Password Login Form ─── -->
        <form method="post">
            <div class="form-group">
                <label for="username"><?php echo __( 'auth.username' ); ?></label>
                <input type="text" id="username" name="username" required autofocus value="<?php echo htmlspecialchars( $_POST['username'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password"><?php echo __( 'auth.password' ); ?></label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn"><?php echo __( 'auth.login' ); ?></button>
        </form>

        <?php else: ?>
        <!-- ─── Two-Factor Authentication ─── -->
        <p class="tfa-subtitle"><?php echo __('security.2fa_verify_title'); ?></p>

        <?php if (count($methods2fa) > 1 || in_array('email', $methods2fa, true)): ?>
        <div class="method-tabs">
            <?php if (in_array('totp', $methods2fa, true)): ?>
                <button type="button" class="method-tab active" data-method="totp"><?php echo __('security.method_totp'); ?></button>
            <?php endif; ?>
            <?php if (in_array('passkey', $methods2fa, true)): ?>
                <button type="button" class="method-tab<?php echo !in_array('totp', $methods2fa, true) ? ' active' : ''; ?>" data-method="passkey"><?php echo __('security.method_passkey'); ?></button>
            <?php endif; ?>
            <?php if (in_array('email', $methods2fa, true)): ?>
                <button type="button" class="method-tab" data-method="email"><?php echo __('security.method_email'); ?></button>
            <?php endif; ?>
            <button type="button" class="method-tab" data-method="recovery"><?php echo __('security.method_recovery'); ?></button>
        </div>
        <?php endif; ?>

        <!-- TOTP Panel -->
        <?php if (in_array('totp', $methods2fa, true)): ?>
        <div class="method-panel active" id="panel-totp">
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo $auth->getCsrfToken(); ?>">
                <input type="hidden" name="2fa_method" value="totp">
                <div class="form-group">
                    <label for="totp-code"><?php echo __('security.enter_totp_code'); ?></label>
                    <input type="text" id="totp-code" name="2fa_code" class="code-input" maxlength="6" pattern="\d{6}" autocomplete="one-time-code" inputmode="numeric" required autofocus>
                </div>
                <button type="submit" class="btn"><?php echo __('security.verify'); ?></button>
            </form>
            <form method="post" style="margin-top:1rem;">
                <input type="hidden" name="csrf" value="<?php echo $auth->getCsrfToken(); ?>">
                <input type="hidden" name="2fa_method" value="emergency_email">
                <button type="submit" class="link-emergency"><?php echo __('security.emergency_email_link'); ?></button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Passkey Panel -->
        <?php if (in_array('passkey', $methods2fa, true)): ?>
        <div class="method-panel<?php echo !in_array('totp', $methods2fa, true) ? ' active' : ''; ?>" id="panel-passkey">
            <p style="text-align:center;color:#64748b;margin-bottom:1rem;"><?php echo __('security.passkey_prompt'); ?></p>
            <button type="button" class="btn" id="passkey-auth-btn"><?php echo __('security.use_passkey'); ?></button>
            <form method="post" id="passkey-form" style="display:none;">
                <input type="hidden" name="csrf" value="<?php echo $auth->getCsrfToken(); ?>">
                <input type="hidden" name="2fa_method" value="passkey">
                <input type="hidden" name="2fa_code" id="passkey-response">
            </form>
            <form method="post" style="margin-top:1rem;">
                <input type="hidden" name="csrf" value="<?php echo $auth->getCsrfToken(); ?>">
                <input type="hidden" name="2fa_method" value="emergency_email">
                <button type="submit" class="link-emergency"><?php echo __('security.emergency_email_link'); ?></button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Email (Magic Link) Panel -->
        <?php if (in_array('email', $methods2fa, true)): ?>
        <div class="method-panel" id="panel-email">
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo $auth->getCsrfToken(); ?>">
                <input type="hidden" name="2fa_method" value="email">
                <p style="color:#64748b;margin-bottom:1rem;font-size:0.9rem;"><?php echo __('security.magic_link_desc'); ?></p>
                <button type="submit" class="btn"><?php echo __('security.send_magic_link'); ?></button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Recovery Code Panel -->
        <div class="method-panel" id="panel-recovery">
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo $auth->getCsrfToken(); ?>">
                <input type="hidden" name="2fa_method" value="recovery">
                <div class="form-group">
                    <label for="recovery-code"><?php echo __('security.enter_recovery_code'); ?></label>
                    <input type="text" id="recovery-code" name="2fa_code" class="code-input" placeholder="xxxx-xxxx-xxxx" required>
                </div>
                <button type="submit" class="btn"><?php echo __('security.verify'); ?></button>
            </form>
        </div>

        <a href="<?php echo $basePath; ?>admin/login.php?cancel_2fa=1" class="link-cancel"><?php echo __('common.cancel'); ?></a>

        <script>
        // Tab switching for 2FA methods
        document.querySelectorAll('.method-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.method-tab').forEach(function(t) { t.classList.remove('active'); });
                document.querySelectorAll('.method-panel').forEach(function(p) { p.classList.remove('active'); });
                tab.classList.add('active');
                var panel = document.getElementById('panel-' + tab.dataset.method);
                if (panel) panel.classList.add('active');
            });
        });

        // Passkey WebAuthn authentication
        <?php if (in_array('passkey', $methods2fa, true)): ?>
        document.getElementById('passkey-auth-btn').addEventListener('click', async function() {
            try {
                var resp = await fetch('<?php echo $basePath; ?>admin/api/webauthn-challenge.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'auth_challenge', csrf: '<?php echo $auth->getCsrfToken(); ?>'})
                });
                var options = await resp.json();

                // Decode challenge and credential IDs from base64url
                options.challenge = base64UrlToBuffer(options.challenge);
                if (options.allowCredentials) {
                    options.allowCredentials = options.allowCredentials.map(function(c) {
                        c.id = base64UrlToBuffer(c.id);
                        return c;
                    });
                }

                var assertion = await navigator.credentials.get({publicKey: options});

                // Encode response
                var assertionData = {
                    credentialId: bufferToBase64Url(assertion.rawId),
                    clientDataJSON: bufferToBase64Url(assertion.response.clientDataJSON),
                    authenticatorData: bufferToBase64Url(assertion.response.authenticatorData),
                    signature: bufferToBase64Url(assertion.response.signature)
                };

                document.getElementById('passkey-response').value = JSON.stringify(assertionData);
                document.getElementById('passkey-form').submit();
            } catch (e) {
                console.error('Passkey auth error:', e);
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
        <?php endif; ?>
        </script>
        <?php endif; ?>
    </div>
</body>
</html>
