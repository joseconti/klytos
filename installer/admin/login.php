<?php

/**
 * Klytos Admin — Login Page
 * Handles password login + two-factor authentication challenge.
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

// login.php is one of the five admin pages that do NOT include
// templates/header.php, and before slice 8 it was the only one of those with
// inline script that never called sendSecurityHeaders() at all — so it ran
// with no CSP whatsoever. bootstrap.php now sends one for every surface, which
// means this page's inline blocks must carry the request's nonce or the 2FA
// method switcher below stops working. This is why the CSP fail-open mattered:
// under the old 'unsafe-inline' fallback the breakage would have been silent.
$cspNonce = $GLOBALS['klytos_csp_nonce'] ?? \Klytos\Core\Auth::generateCspNonce();

$auth  = $app->getAuth();
$error = '';
$info  = '';
$redirectTo = $_POST['redirect_to'] ?? $_GET['redirect_to'] ?? '';

// Already authenticated? Go to dashboard or redirect_to
if ($auth->isAuthenticated()) {
    Helpers::redirect($redirectTo ? Helpers::sanitizeRedirectUrl($redirectTo) : Helpers::url('admin/'));
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
            Helpers::redirect($redirectTo ? Helpers::sanitizeRedirectUrl($redirectTo) : Helpers::url('admin/'));
        } else {
            $error = __('security.2fa_invalid_code');
        }
    }
}

// ─── Handle 2FA verification POST ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->is2faPending()) {
    if ( ! klytos_verify_csrf() ) {
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
        } elseif ($method === 'passkey') {
            // This page's own front end has posted `2fa_method=passkey` since before
            // there was a branch to receive it: the #passkey-form below fills its
            // hidden 2fa_code field with the JSON assertion from
            // navigator.credentials.get(). TwoFactor::verifyPasskeyAssertion() was
            // likewise complete and had ZERO call sites — the two halves of the
            // feature existed and were never connected, which is why passkey
            // second-factor login has never worked (audit NEW-09).
            $assertion = json_decode($code, true);
            $verified  = is_array($assertion)
                && $twoFactor->verifyPasskeyAssertion($userId, $assertion, Helpers::webauthnRpId());
        } elseif ($method === 'recovery') {
            $verified = $twoFactor->verifyRecoveryCode($userId, $code);
        } elseif ($method === 'email' || $method === 'emergency_email') {
            // Magic link: send email (works for both 2FA-email method and emergency recovery)
            $user = $app->getStorage()->read('users', $userId);
            $email = trim($user['email'] ?? '');
            if ( $email && klytos_is_email( $email ) ) {
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
            Helpers::redirect($redirectTo ? Helpers::sanitizeRedirectUrl($redirectTo) : Helpers::url('admin/'));
        } elseif ($method !== 'email' && !$info) {
            $error = __('security.2fa_invalid_code');
        }
    }
}

// ─── Handle password login POST ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$auth->is2faPending() && !isset($_POST['2fa_method'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // ─── IP ceiling (audit NEW-40, second half; D-059) ──────────
    // The per-account lockout bounds attempts against ONE account. Nothing
    // bounded the endpoint itself, so a burst of invented usernames was
    // limited only by the pruning window — and every request in it paid a
    // bcrypt verify, because authenticate() equalizes its cost on all branches
    // (the NEW-39 fix). That makes the login form a CPU amplifier as well as a
    // credential-stuffing surface.
    //
    // MCP\RateLimiter is REUSED rather than forked: its auth-failure tracking
    // is already exactly this policy (10 failures per 60 s, IP-keyed) and
    // core/mcp/server.php:87,101 already consumes it, so no constant moves.
    // D-056's note 3 rejected this class for the per-ACCOUNT lockout because
    // expressing 5-attempts/15-minutes through it would have meant changing
    // those constants for the MCP surface too; that objection does not apply
    // here, which is why this is reuse and not a second limiter.
    $loginLimiter = new \Klytos\Core\MCP\RateLimiter( $app->getDataPath() );
    $clientIp     = \Klytos\Core\MCP\RateLimiter::getClientIp();

    // The decision is filterable, and the filter is the operator's remedy for
    // audit NEW-17 rather than a convenience: behind a non-loopback proxy every
    // visitor collapses into one bucket, so a whole office on one NAT address
    // shares this ceiling and can lock itself out of its own site. A listener
    // returning false for a known address range exempts it.
    //
    // It is deliberately applied HERE and not inside MCP\RateLimiter: D-056's
    // implementation note 3 and D-059 both turn on that class's constants not
    // moving, because core/mcp/server.php shares them — filtering inside it
    // would weaken the MCP surface to loosen the login form.
    //
    // Like every other weakenable control in this project (admin.gate_map
    // D-032, http.safe.* D-041, security.hsts D-044), a plugin CAN switch this
    // off; plugins already run as first-party code here. What it cannot do is
    // weaken the per-ACCOUNT lockout, which is a separate control with its own
    // counter and no filter.
    $ipIsBlocked = (bool) klytos_apply_filters(
        'auth.login_ip_blocked',
        $loginLimiter->isAuthBlocked( $clientIp ),
        $clientIp
    );

    if ( $ipIsBlocked ) {
        klytos_do_action( 'auth.login_throttled', $clientIp );
        header( 'Retry-After: 60' );
        http_response_code( 429 );

        // The MESSAGE is not set here. Every refusal on this page is worded in
        // the single mapping below, from $result['error'] — the first version
        // of this branch set $error itself and the mapping then overwrote it
        // with auth.login_failed, so the response carried a 429 status and the
        // words "Incorrect username or password", and the auth.too_many_attempts
        // key added to all 20 catalogues in the same slice could never be
        // rendered at all. Found by the HTTP test, not by reading.
        $result = [ 'success' => false, 'error' => 'throttled', 'requires_2fa' => false, 'user_id' => null ];
    } else {
        $result = $auth->login($username, $password);

        // Counted on failure only, so a legitimate user logging in repeatedly
        // never approaches the ceiling.
        if ( ! $result['success'] ) {
            $loginLimiter->recordAuthFailure( $clientIp );
        }
    }

    if ($result['success'] && !$result['requires_2fa']) {
        Helpers::redirect($redirectTo ? Helpers::sanitizeRedirectUrl($redirectTo) : Helpers::url('admin/'));
    } elseif ($result['success'] && $result['requires_2fa']) {
        // 2FA required — page will render the 2FA form below.
    } else {
        if ($result['error'] === 'throttled') {
            // The IP ceiling above. Worded here rather than at the branch that
            // sets it, so this page has exactly ONE place that turns a refusal
            // into words and no later branch can overwrite an earlier one.
            $error = __( 'auth.too_many_attempts' );
        } elseif (str_starts_with($result['error'], 'account_locked:')) {
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
    <style nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { background: #1e293b; border-radius: 1rem; box-shadow: 0 25px 60px rgba(0,0,0,0.4); border: 1px solid #334155; padding: 2.5rem; width: 100%; max-width: 400px; margin: 1rem; }
        .logo { text-align: center; margin-bottom: 2rem; }
        .logo h1 { font-size: 1.8rem; color: #f8fafc; font-weight: 700; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.3rem; color: #e2e8f0; }
        input { width: 100%; padding: 0.7rem; border: 1px solid #334155; border-radius: 8px; font-size: 0.95rem; background: #0f172a; color: #e2e8f0; }
        input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
        .btn { width: 100%; padding: 0.75rem; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.25s; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(99,102,241,0.4); }
        .alert { padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-error { background: rgba(239,68,68,0.12); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
        .alert-info { background: rgba(99,102,241,0.12); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3); }
        .method-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .method-tab { flex: 1; min-width: 80px; padding: 0.5rem; border: 2px solid #334155; border-radius: 8px; background: transparent; cursor: pointer; text-align: center; font-size: 0.8rem; font-weight: 600; color: #94a3b8; transition: all 0.2s; }
        .method-tab:hover { border-color: #6366f1; color: #a5b4fc; }
        .method-tab.active { border-color: #6366f1; background: rgba(99,102,241,0.15); color: #a5b4fc; }
        .method-panel { display: none; }
        .method-panel.active { display: block; }
        .link-cancel { display: block; text-align: center; margin-top: 1rem; color: #94a3b8; text-decoration: none; font-size: 0.85rem; }
        .link-cancel:hover { color: #a5b4fc; }
        .link-emergency { background: none; border: none; color: #94a3b8; font-size: 0.85rem; cursor: pointer; text-decoration: underline; padding: 0; width: 100%; text-align: center; }
        .link-emergency:hover { color: #fca5a5; }
        .tfa-subtitle { text-align: center; color: #94a3b8; font-size: 0.9rem; margin-bottom: 1.5rem; }
        input[type="text"].code-input { text-align: center; font-size: 1.5rem; letter-spacing: 0.3em; font-family: monospace; background: #0f172a; color: #e2e8f0; }
    </style>
<?php klytos_do_action('login.head'); ?>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <h1>Klytos</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
        <?php endif; ?>

        <?php if ($info): ?>
            <div class="alert alert-info"><?php echo klytos_esc_html( $info ); ?></div>
        <?php endif; ?>

        <?php if (!$show2fa): ?>
        <!-- ─── Password Login Form ─── -->
            <?php klytos_do_action('login.before_form'); ?>
        <form method="post">
            <input type="hidden" name="redirect_to" value="<?php echo klytos_esc_attr( $redirectTo ); ?>">
            <div class="form-group">
                <label for="username"><?php echo __( 'auth.username' ); ?></label>
                <input type="text" id="username" name="username" required autofocus value="<?php echo klytos_esc_attr( $_POST['username'] ?? '' ); ?>">
            </div>

            <div class="form-group">
                <label for="password"><?php echo __( 'auth.password' ); ?></label>
                <input type="password" id="password" name="password" required>
            </div>

            <?php klytos_do_action('login.after_fields'); ?>
            <button type="submit" class="btn"><?php echo __( 'auth.login' ); ?></button>
        </form>
            <?php klytos_do_action('login.after_form'); ?>

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
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="redirect_to" value="<?php echo klytos_esc_attr( $redirectTo ); ?>">
                <input type="hidden" name="2fa_method" value="totp">
                <div class="form-group">
                    <label for="totp-code"><?php echo __('security.enter_totp_code'); ?></label>
                    <input type="text" id="totp-code" name="2fa_code" class="code-input" maxlength="6" pattern="\d{6}" autocomplete="one-time-code" inputmode="numeric" required autofocus>
                </div>
                <button type="submit" class="btn"><?php echo __('security.verify'); ?></button>
            </form>
            <form method="post" class="mt-2">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="redirect_to" value="<?php echo klytos_esc_attr( $redirectTo ); ?>">
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
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="redirect_to" value="<?php echo klytos_esc_attr( $redirectTo ); ?>">
                <input type="hidden" name="2fa_method" value="passkey">
                <input type="hidden" name="2fa_code" id="passkey-response">
            </form>
            <form method="post" class="mt-2">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="redirect_to" value="<?php echo klytos_esc_attr( $redirectTo ); ?>">
                <input type="hidden" name="2fa_method" value="emergency_email">
                <button type="submit" class="link-emergency"><?php echo __('security.emergency_email_link'); ?></button>
            </form>
        </div>
            <?php endif; ?>

        <!-- Email (Magic Link) Panel -->
            <?php if (in_array('email', $methods2fa, true)): ?>
        <div class="method-panel" id="panel-email">
            <form method="post">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="redirect_to" value="<?php echo klytos_esc_attr( $redirectTo ); ?>">
                <input type="hidden" name="2fa_method" value="email">
                <p style="color:#64748b;margin-bottom:1rem;font-size:0.9rem;"><?php echo __('security.magic_link_desc'); ?></p>
                <button type="submit" class="btn"><?php echo __('security.send_magic_link'); ?></button>
            </form>
        </div>
            <?php endif; ?>

        <!-- Recovery Code Panel -->
        <div class="method-panel" id="panel-recovery">
            <form method="post">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="redirect_to" value="<?php echo klytos_esc_attr( $redirectTo ); ?>">
                <input type="hidden" name="2fa_method" value="recovery">
                <div class="form-group">
                    <label for="recovery-code"><?php echo __('security.enter_recovery_code'); ?></label>
                    <input type="text" id="recovery-code" name="2fa_code" class="code-input" placeholder="xxxx-xxxx-xxxx" required>
                </div>
                <button type="submit" class="btn"><?php echo __('security.verify'); ?></button>
            </form>
        </div>

        <a href="<?php echo klytos_esc_url( $basePath . 'admin/login.php?cancel_2fa=1' . ($redirectTo ? '&redirect_to=' . urlencode($redirectTo) : '') ); ?>" class="link-cancel"><?php echo __('common.cancel'); ?></a>

        <script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
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
                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?php echo $auth->getCsrfToken(); ?>'},
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
<?php klytos_do_action('login.footer'); ?>
</body>
</html>
