<?php
/**
 * Klytos Admin — Login Page
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

// Already authenticated? Go to dashboard
if ($auth->isAuthenticated()) {
    Helpers::redirect(Helpers::url('admin/'));
}

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = $auth->login($username, $password);

    if ($result['success']) {
        Helpers::redirect(Helpers::url('admin/'));
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
        .alert { padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <h1>Klytos</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert"><?php echo htmlspecialchars( $error ); ?></div>
        <?php endif; ?>

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
    </div>
</body>
</html>
