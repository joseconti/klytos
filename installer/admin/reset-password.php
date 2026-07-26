<?php

/**
 * Klytos Admin — Password Reset Page
 * Public page (no auth required) for resetting a user's password via token.
 *
 * @package Klytos
 * @since   1.0.0
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
use Klytos\Core\UserManager;
use Klytos\Core\Auth;

// Reuse the request's nonce. bootstrap.php generated it and already sent the
// headers with it (NEW-14), so minting a second one here and re-sending would
// produce two nonces per request for no gain. The removed re-send is verified
// redundant rather than assumed so (L-007): this page passes no $customCsp, so
// its call produced byte-identical headers to the bootstrap's.
$cspNonce = $GLOBALS['klytos_csp_nonce'] ?? Auth::generateCspNonce();

$userManager = new UserManager($app->getStorage());
$error       = '';
$success     = '';
$showForm    = false;

$userId = $_GET['user_id'] ?? '';
$token  = $_GET['token'] ?? '';

// Validate the token on GET
if (!empty($userId) && !empty($token)) {
    if ($userManager->validatePasswordResetToken($userId, $token)) {
        $showForm = true;
    } else {
        $error = 'This reset link is invalid or has expired.';
    }
} else {
    $error = 'Invalid reset link.';
}

// Handle POST — set new password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm) {
    $newPassword     = $_POST['password'] ?? '';
    $confirmPassword = $_POST['password_confirm'] ?? '';
    $postUserId      = $_POST['user_id'] ?? '';
    $postToken       = $_POST['token'] ?? '';

    // ─── CSRF (audit NEW-26; D-061) ─────────────────────────────
    // The exploitability is genuinely low and is stated rather than inflated: a
    // forged POST still needs the valid user_id + token pair, which is the very
    // secret CSRF would be substituting for. It is closed anyway, with NEW-47,
    // because the two are one defect class on one flow — and because "this form
    // does not need CSRF" is a judgement that has to be re-derived by every
    // reader who notices its absence.
    //
    // The token comes from the session bootstrap.php starts for this page, and
    // klytos_csrf_field() below writes it when the form is rendered — so the
    // GET that shows the form to the recipient of the reset email is what mints
    // it. A refusal therefore means a stale page, never a wrong link, and the
    // message says so.
    if ( ! klytos_verify_csrf() ) {
        http_response_code( 403 );
        $error = __( 'auth.session_expired' );
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 12) {
        $error = 'Password must be at least 12 characters.';
    } elseif (!$userManager->validatePasswordResetToken($postUserId, $postToken)) {
        $error    = 'This reset link is invalid or has expired.';
        $showForm = false;
    } else {
        try {
            $userManager->changePassword($postUserId, $newPassword);
            $userManager->consumePasswordResetToken($postUserId);
            $userManager->forceLogoutAllSessions($postUserId);
            $success  = 'Your password has been reset successfully. You can now log in.';
            $showForm = false;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$basePath = Helpers::getBasePath();
?>
<!DOCTYPE html>
<html lang="<?php echo $app->getI18n()->getLocale(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Reset Password — Klytos</title>
    <style nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
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
        .alert { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1rem; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .back-link { display: block; text-align: center; margin-top: 1.5rem; color: #64748b; font-size: 0.9rem; text-decoration: none; }
        .back-link:hover { color: #2563eb; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <h1>Klytos</h1>
        </div>

        <h2 style="text-align:center;margin-bottom:1.5rem;font-size:1.1rem;font-weight:600;">Reset Password</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo klytos_esc_html($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo klytos_esc_html($success); ?></div>
            <a href="<?php echo klytos_esc_url($basePath . 'admin/login.php'); ?>" class="btn" style="display:block;text-align:center;text-decoration:none;margin-top:1rem;">Go to Login</a>
        <?php endif; ?>

        <?php if ($showForm): ?>
            <form method="post">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="user_id" value="<?php echo klytos_esc_attr($userId); ?>">
                <input type="hidden" name="token" value="<?php echo klytos_esc_attr($token); ?>">

                <div class="form-group">
                    <label>New Password (min 12 characters)</label>
                    <input type="password" name="password" required minlength="12" data-klytos-pwgen id="resetNewPassword">
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirm" required minlength="12" data-klytos-pwgen-confirm="#resetNewPassword">
                </div>

                <button type="submit" class="btn">Set New Password</button>
            </form>
        <?php endif; ?>

        <?php if (!$showForm && empty($success)): ?>
            <a href="<?php echo klytos_esc_url($basePath . 'admin/login.php'); ?>" class="back-link">Back to Login</a>
        <?php endif; ?>
    </div>

    <script nonce="<?php echo klytos_esc_attr($cspNonce); ?>" src="<?php echo klytos_esc_url($basePath . 'admin/assets/js/klytos-password.js'); ?>"></script>
</body>
</html>
