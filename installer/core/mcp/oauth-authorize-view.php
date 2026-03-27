<?php
/**
 * Klytos — OAuth 2.0/2.1 Authorization View
 * Handles the authorize endpoint: admin login → consent screen → redirect with code.
 *
 * @package Klytos
 * @since   1.1.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\MCP;

use Klytos\Core\App;
use Klytos\Core\Auth;
use Klytos\Core\Helpers;

/**
 * Handle the OAuth authorize endpoint.
 * Called by Router::handleOAuthAuthorize().
 */
function handleOAuthAuthorizeView(App $app): void
{
    $auth = $app->getAuth();
    $auth->startSession();

    $error     = '';
    $client    = null;
    $params    = [];
    $showLogin = false;
    $showConsent = false;

    // Collect OAuth parameters from GET (initial request) or POST (form submissions)
    $params = [
        'client_id'             => $_GET['client_id'] ?? $_POST['client_id'] ?? '',
        'redirect_uri'          => $_GET['redirect_uri'] ?? $_POST['redirect_uri'] ?? '',
        'response_type'         => $_GET['response_type'] ?? $_POST['response_type'] ?? '',
        'state'                 => $_GET['state'] ?? $_POST['state'] ?? '',
        'code_challenge'        => $_GET['code_challenge'] ?? $_POST['code_challenge'] ?? '',
        'code_challenge_method' => $_GET['code_challenge_method'] ?? $_POST['code_challenge_method'] ?? '',
        'scope'                 => $_GET['scope'] ?? $_POST['scope'] ?? '',
    ];

    // Validate the authorize request
    require_once __DIR__ . '/oauth-server.php';
    require_once __DIR__ . '/rate-limiter.php';

    $rateLimiter = new RateLimiter($app->getDataPath());
    $oauthServer = new OAuthServer($auth, $app->getStorage(), $rateLimiter);

    $validation = $oauthServer->validateAuthorizeRequest($params);

    if (!$validation['valid']) {
        // If we have a redirect_uri and the error is not about the redirect itself,
        // redirect with error. Otherwise show error page.
        $redirectUri = $params['redirect_uri'];
        $errorCode   = $validation['error'];

        if (!empty($redirectUri) && !in_array($errorCode, ['invalid_client'], true)) {
            $sep = (str_contains($redirectUri, '?') ? '&' : '?');
            $errorUrl = $redirectUri . $sep . http_build_query([
                'error'             => $errorCode,
                'error_description' => $validation['error_description'] ?? '',
                'state'             => $params['state'],
            ]);
            Helpers::redirect($errorUrl);
            return;
        }

        $error = $validation['error_description'] ?? $errorCode;
    } else {
        $client = $validation['client'];
    }

    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
        $action = $_POST['action'] ?? '';

        if ($action === 'login') {
            // Admin login attempt
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $result   = $auth->login($username, $password);

            if (!$result['success']) {
                $error = 'Invalid credentials.';
                $showLogin = true;
            }
        } elseif ($action === 'authorize') {
            // Admin approved — validate CSRF and generate code
            if (!$auth->isAuthenticated()) {
                $error = 'Session expired. Please log in again.';
                $showLogin = true;
            } elseif (!$auth->validateCsrf($_POST['csrf'] ?? '')) {
                $error = 'Invalid CSRF token.';
            } else {
                // Generate authorization code
                $result = $oauthServer->handleAuthorize($params, $auth->getUsername());

                $sep = str_contains($result['redirect_uri'], '?') ? '&' : '?';
                $redirectUrl = $result['redirect_uri'] . $sep . http_build_query(array_filter([
                    'code'  => $result['code'],
                    'state' => $result['state'],
                ]));

                Helpers::redirect($redirectUrl);
                return;
            }
        } elseif ($action === 'deny') {
            // Admin denied
            $redirectUri = $params['redirect_uri'];
            $sep = str_contains($redirectUri, '?') ? '&' : '?';
            $denyUrl = $redirectUri . $sep . http_build_query([
                'error'             => 'access_denied',
                'error_description' => 'The resource owner denied the request.',
                'state'             => $params['state'],
            ]);
            Helpers::redirect($denyUrl);
            return;
        }
    }

    // Determine which screen to show
    if (empty($error) && $client !== null) {
        if ($auth->isAuthenticated()) {
            $showConsent = true;
        } else {
            $showLogin = true;
        }
    }

    $csrf    = $auth->getCsrfToken();
    $nonce   = Auth::generateCspNonce();
    Auth::sendSecurityHeaders($nonce);

    // Render the page
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Authorize — Klytos</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; }
        .auth-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); padding: 2rem; width: 100%; max-width: 440px; }
        .auth-card h1 { font-size: 1.4rem; margin-bottom: 0.25rem; }
        .auth-card .subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.25rem; }
        .form-control { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; }
        .form-control:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .btn { display: inline-block; padding: 0.6rem 1.25rem; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-group { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .client-info { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; }
        .client-info dt { font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
        .client-info dd { font-size: 0.95rem; margin-bottom: 0.75rem; word-break: break-all; }
        .brand { text-align: center; margin-bottom: 1.5rem; }
        .brand h2 { font-size: 1.1rem; color: #2563eb; }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="brand">
        <h2>Klytos</h2>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert-error"><?php echo htmlspecialchars( $error ); ?></div>
    <?php endif; ?>

    <?php if ($showLogin): ?>
        <!-- Login Form -->
        <h1>Sign In</h1>
        <p class="subtitle">An application is requesting access to your Klytos site.</p>

        <form method="post">
            <?php foreach ($params as $k => $v): ?>
                <input type="hidden" name="<?php echo htmlspecialchars( $k ); ?>" value="<?php echo htmlspecialchars( $v ); ?>">
            <?php endforeach; ?>
            <input type="hidden" name="action" value="login">

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required autocomplete="username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Sign In</button>
        </form>

    <?php elseif ($showConsent): ?>
        <!-- Consent Screen -->
        <h1>Authorize Application</h1>
        <p class="subtitle"><strong><?php echo htmlspecialchars( $client['name'] ?? 'Unknown'); ?></strong> is requesting access to your Klytos site.</p>

        <dl class="client-info">
            <dt>Application</dt>
            <dd><?php echo htmlspecialchars( $client['name'] ?? ''); ?></dd>
            <dt>Redirect URI</dt>
            <dd><?php echo htmlspecialchars( $params['redirect_uri'] ); ?></dd>
            <?php if (!empty($params['scope'])): ?>
                <dt>Scope</dt>
                <dd><?php echo htmlspecialchars( $params['scope'] ); ?></dd>
            <?php endif; ?>
        </dl>

        <form method="post">
            <?php foreach ($params as $k => $v): ?>
                <input type="hidden" name="<?php echo htmlspecialchars( $k ); ?>" value="<?php echo htmlspecialchars( $v ); ?>">
            <?php endforeach; ?>
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">

            <div class="btn-group">
                <button type="submit" name="action" value="authorize" class="btn btn-primary" style="flex:1;">Authorize</button>
                <button type="submit" name="action" value="deny" class="btn btn-danger" style="flex:1;">Deny</button>
            </div>
        </form>

    <?php elseif (empty($error)): ?>
        <div class="alert-error">Invalid authorization request.</div>
    <?php endif; ?>
</div>
</body>
</html>
<?php
    exit;
}
