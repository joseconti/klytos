<?php

/**
 * Klytos Admin — Post-Install Setup Wizard
 * Guides new users through 2FA, AI configuration, and MCP setup.
 *
 * Screens:
 * 1. Two-Factor Authentication setup (TOTP)
 * 2. Connection type selection (MCP / API Keys / Both)
 * 3. AI Provider API keys (conditional)
 * 4. Application Password + MCP configuration
 * 5. Congratulations + AI prompt
 *
 * @package Klytos
 * @since   0.25.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\Auth;
use Klytos\Core\Helpers;

$auth      = $app->getAuth();
$userId    = $auth->getUserId();
$username  = $auth->getUsername();
$twoFactor = $app->getTwoFactor();
$storage   = $app->getStorage();
$rootPath  = dirname( __DIR__ );
$config    = $app->getConfig();

// If setup is already completed, go to dashboard.
if ( !isset( $config['setup_completed'] ) || $config['setup_completed'] !== false ) {
    Helpers::redirect( Helpers::url( 'admin/' ) );
}

// ─── CSP nonce ──────────────────────────────────────────────
$cspNonce = Auth::generateCspNonce();
$GLOBALS['klytos_csp_nonce'] = $cspNonce;
Auth::sendSecurityHeaders( $cspNonce );

// ─── Session step management ────────────────────────────────
if ( !isset( $_SESSION['klytos_setup_step'] ) ) {
    $_SESSION['klytos_setup_step'] = '2fa';
}

$step    = $_SESSION['klytos_setup_step'];
$error   = '';
$success = '';

// Data persisted across steps.
$newAppPassword = '';
$recoveryCodes  = [];
$totpSecret     = $_SESSION['klytos_setup_totp_secret'] ?? '';
$totpUri        = '';

// ─── Handle POST Actions ────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $action = $_POST['wizard_action'] ?? '';

    // ── Screen 1: 2FA ──
    if ( $action === 'skip_2fa' ) {
        $_SESSION['klytos_setup_step'] = 'encryption_key';
        Helpers::redirect( Helpers::url( 'admin/setup-wizard.php' ) );
    }

    if ( $action === 'generate_totp' ) {
        $totpSecret = $twoFactor->generateTotpSecret();
        $_SESSION['klytos_setup_totp_secret'] = $totpSecret;
        $_SESSION['klytos_setup_step'] = '2fa_verify';
        Helpers::redirect( Helpers::url( 'admin/setup-wizard.php' ) );
    }

    if ( $action === 'verify_totp' ) {
        $step = '2fa_verify';
        $code = trim( $_POST['totp_code'] ?? '' );
        $secret = $_SESSION['klytos_setup_totp_secret'] ?? '';

        if ( empty( $secret ) ) {
            $error = 'Session expired. Please start again.';
            $_SESSION['klytos_setup_step'] = '2fa';
        } elseif ( $twoFactor->verifyTotp( $secret, $code ) ) {
            $twoFactor->enableTotp( $userId, $secret );
            $recoveryCodes = $twoFactor->generateRecoveryCodes( $userId );
            $_SESSION['klytos_setup_recovery_codes'] = $recoveryCodes;
            $_SESSION['klytos_setup_step'] = '2fa_recovery';
            Helpers::redirect( Helpers::url( 'admin/setup-wizard.php' ) );
        } else {
            $error = 'Invalid code. Please try again.';
        }
    }

    if ( $action === 'recovery_confirmed' ) {
        unset( $_SESSION['klytos_setup_totp_secret'], $_SESSION['klytos_setup_recovery_codes'] );
        $_SESSION['klytos_setup_step'] = 'encryption_key';
        Helpers::redirect( Helpers::url( 'admin/setup-wizard.php' ) );
    }

    if ( $action === 'encryption_key_confirmed' ) {
        // Mark the key as backed up in site config.
        $app->getSiteConfig()->set( ['encryption_key_backed_up' => true] );
        $_SESSION['klytos_setup_step'] = 'connection';
        Helpers::redirect( Helpers::url( 'admin/setup-wizard.php' ) );
    }

    // ── Screen 2: Connection Types ──
    if ( $action === 'set_connection' ) {
        $connectionType = $_POST['connection_type'] ?? 'both';
        if ( !in_array( $connectionType, ['mcp', 'api_keys', 'both'], true ) ) {
            $connectionType = 'both';
        }
        $_SESSION['klytos_setup_connection'] = $connectionType;

        if ( $connectionType === 'mcp' ) {
            // Skip AI keys screen, go directly to App Password.
            $_SESSION['klytos_setup_step'] = 'app_password';
        } else {
            $_SESSION['klytos_setup_step'] = 'ai_keys';
        }
        Helpers::redirect( Helpers::url( 'admin/setup-wizard.php' ) );
    }

    // ── Screen 3: AI Provider Keys ──
    if ( $action === 'save_ai_keys' ) {
        require_once $rootPath . '/core/ai/ai-key-manager.php';
        $aiKeys = new \Klytos\Core\Ai\AiKeyManager( $storage, $rootPath . '/config' );

        foreach ( \Klytos\Core\Ai\AiKeyManager::PROVIDERS as $providerId => $providerInfo ) {
            $key   = trim( $_POST['ai_key_' . $providerId] ?? '' );
            $model = $_POST['ai_model_' . $providerId] ?? '';

            if ( !empty( $key ) ) {
                $defaultModel = $model ?: $aiKeys->getDefaultModelForProvider( $providerId );
                $aiKeys->setKey( $providerId, $key, $defaultModel );
            }
        }

        $_SESSION['klytos_setup_step'] = 'app_password';
        Helpers::redirect( Helpers::url( 'admin/setup-wizard.php' ) );
    }

    if ( $action === 'skip_ai_keys' ) {
        $_SESSION['klytos_setup_step'] = 'app_password';
        Helpers::redirect( Helpers::url( 'admin/setup-wizard.php' ) );
    }

    // ── Screen 4: Generate App Password ──
    if ( $action === 'generate_app_password' ) {
        $result = $auth->createAppPassword( 'AI Assistant Access', $username );
        $_SESSION['klytos_setup_app_password'] = $result['password'];
        $_SESSION['klytos_setup_step'] = 'app_password_show';
        Helpers::redirect( Helpers::url( 'admin/setup-wizard.php' ) );
    }

    // ── Screen 4 → Screen 5: Go to congratulations ──
    if ( $action === 'go_to_congrats' ) {
        $_SESSION['klytos_setup_step'] = 'congrats';
        Helpers::redirect( Helpers::url( 'admin/setup-wizard.php' ) );
    }

    // ── Screen 5: Complete — go to dashboard ──
    if ( $action === 'complete_setup' ) {
        // Mark setup as completed in main config.
        $config['setup_completed'] = true;
        $storage->writeTo( $rootPath . '/config', 'config.json.enc', $config );

        // Clean up session data.
        unset(
            $_SESSION['klytos_setup_step'],
            $_SESSION['klytos_setup_totp_secret'],
            $_SESSION['klytos_setup_recovery_codes'],
            $_SESSION['klytos_setup_connection'],
            $_SESSION['klytos_setup_app_password']
        );

        Helpers::redirect( Helpers::url( 'admin/' ) );
    }
}

// ─── Re-read step from session after POST redirects ─────────
$step = $_SESSION['klytos_setup_step'] ?? '2fa';

// ─── Prepare data for current step ──────────────────────────
if ( $step === '2fa_verify' ) {
    $totpSecret = $_SESSION['klytos_setup_totp_secret'] ?? '';
    $totpUri    = $twoFactor->getTotpUri( $totpSecret, $username, $config['site_name'] ?? 'Klytos' );
}

if ( $step === '2fa_recovery' ) {
    $recoveryCodes = $_SESSION['klytos_setup_recovery_codes'] ?? [];
}

if ( $step === 'app_password_show' ) {
    $newAppPassword = $_SESSION['klytos_setup_app_password'] ?? '';
}

// Build MCP endpoint URL.
// Prefer the URL persisted by the installer (correct even after directory rename).
$mcpEndpoint = $config['mcp_endpoint'] ?? Helpers::siteUrl( 'mcp' );

// CSRF token for forms.
$csrf = klytos_csrf_field();

// Step mapping for progress indicator.
$stepOrder = ['2fa', '2fa_verify', '2fa_recovery', 'encryption_key', 'connection', 'ai_keys', 'app_password', 'app_password_show', 'congrats'];
$stepLabels = [
    '2fa'            => 1,
    '2fa_verify'     => 1,
    '2fa_recovery'   => 1,
    'encryption_key' => 2,
    'connection'     => 3,
    'ai_keys'        => 4,
    'app_password'       => 5,
    'app_password_show'  => 5,
    'congrats'       => 6,
];
$currentStepNum = $stepLabels[$step] ?? 1;

// ─── Determine total steps (3 or 4 depends on connection type) ──
$connectionType = $_SESSION['klytos_setup_connection'] ?? 'both';
$totalSteps = ($connectionType === 'mcp') ? 4 : 5;

// ─── Provider logos (inline SVG paths) ──────────────────────
$providerLogos = [
    'anthropic'  => '<img src="assets/images/claude-color.webp" width="32" height="32" alt="Anthropic Claude" style="border-radius:6px">',
    'openai'     => '<img src="assets/images/openai-white.webp" width="32" height="32" alt="OpenAI" style="border-radius:6px">',
    'gemini'     => '<img src="assets/images/gemini-color.webp" width="32" height="32" alt="Google Gemini" style="border-radius:6px">',
    'openrouter' => '<img src="assets/images/openrouter-white.webp" width="32" height="32" alt="OpenRouter" style="border-radius:6px">',
];

?>
<!DOCTYPE html>
<html lang="<?php echo klytos_esc_attr( substr( $config['admin_language'] ?? 'en', 0, 2 ) ); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Klytos — Setup Wizard</title>
    <style nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #0f172a; color: #e2e8f0; line-height: 1.6; min-height: 100vh;
        }
        .wizard { max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
        .card {
            background: #1e293b; border-radius: 1rem; border: 1px solid #334155;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4); padding: 2rem; margin-bottom: 1.5rem;
        }
        .logo { text-align: center; margin-bottom: 2rem; }
        .logo-mark {
            width: 80px; height: 80px; margin: 0 auto 1.5rem;
            border-radius: 1.25rem; display: flex; align-items: center; justify-content: center;
        }
        .logo-mark img { width: 80px; height: 80px; border-radius: 1.25rem; }
        .logo h1 { font-size: 1.5rem; font-weight: 700; color: #f8fafc; }
        .logo p { color: #94a3b8; font-size: 0.925rem; }

        /* Step progress */
        .steps { display: flex; gap: 0.5rem; margin-bottom: 2rem; }
        .step-dot {
            flex: 1; height: 4px; border-radius: 2px; background: #334155;
            transition: background 0.3s;
        }
        .step-dot.active { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
        .step-dot.done { background: #22c55e; }

        h2 { font-size: 1.3rem; margin-bottom: 0.5rem; color: #f8fafc; }
        h3 { font-size: 1.1rem; margin: 1.5rem 0 0.75rem; color: #f8fafc; }
        .subtitle { color: #94a3b8; font-size: 0.9rem; margin-bottom: 1.5rem; }

        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; color: #e2e8f0; }
        input[type="text"], input[type="password"], select {
            width: 100%; padding: 0.7rem; border: 1px solid #334155; border-radius: 0.5rem;
            font-size: 0.95rem; transition: border-color 0.2s;
            background: #0f172a; color: #e2e8f0;
        }
        input:focus, select:focus {
            outline: none; border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            padding: 0.875rem 2rem; border: none; border-radius: 0.625rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer;
            transition: all 0.25s; text-decoration: none;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(99,102,241,0.4);
        }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-block { width: 100%; text-align: center; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.85rem; }
        .btn-secondary { background: #475569; }
        .btn-secondary:hover { background: #64748b; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        .btn-success { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .btn-success:hover { box-shadow: 0 8px 24px rgba(34,197,94,0.4); }
        .btn-ghost { background: transparent; border: 1px solid #475569; color: #94a3b8; }
        .btn-ghost:hover { background: #334155; color: #e2e8f0; box-shadow: none; }
        .btn-row { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
        .btn-row .btn { flex: 1; }

        .alert { padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-error { background: rgba(239,68,68,0.12); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
        .alert-success { background: rgba(34,197,94,0.12); color: #86efac; border: 1px solid rgba(34,197,94,0.3); }
        .alert-warning { background: rgba(245,158,11,0.12); color: #fcd34d; border: 1px solid rgba(245,158,11,0.3); }
        .alert-info { background: rgba(99,102,241,0.12); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3); }

        .token-box {
            background: #0f172a; border: 1px solid #334155; border-radius: 0.5rem;
            padding: 1rem; font-family: monospace; font-size: 0.85rem;
            word-break: break-all; margin: 0.75rem 0; color: #e2e8f0;
            position: relative;
        }
        .token-box.highlight {
            background: rgba(245,158,11,0.1); border-color: rgba(245,158,11,0.3); color: #fcd34d;
        }
        .mcp-config {
            background: #0f172a; color: #a5b4fc; border-radius: 0.5rem; border: 1px solid #334155;
            padding: 1rem; font-family: monospace; font-size: 0.8rem;
            white-space: pre; overflow-x: auto; margin: 0.75rem 0;
            position: relative;
        }
        .copy-btn {
            position: absolute; top: 0.5rem; right: 0.5rem;
            background: #475569; border: none; color: #e2e8f0; padding: 0.25rem 0.5rem;
            border-radius: 0.25rem; font-size: 0.75rem; cursor: pointer; transition: background 0.2s;
        }
        .copy-btn:hover { background: #6366f1; }
        .copy-btn.copied { background: #22c55e; }

        /* QR Code container */
        .qr-container {
            text-align: center; padding: 1.5rem; background: #fff; border-radius: 0.75rem;
            display: inline-block; margin: 1rem 0;
        }

        /* TOTP code input */
        .totp-input {
            font-size: 2rem; text-align: center; letter-spacing: 0.5rem;
            font-family: monospace; max-width: 240px; margin: 0 auto; display: block;
        }

        /* Recovery codes grid */
        .recovery-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin: 1rem 0;
        }
        .recovery-code {
            background: #0f172a; border: 1px solid #334155; border-radius: 0.375rem;
            padding: 0.5rem; font-family: monospace; font-size: 0.9rem; text-align: center;
            color: #fcd34d;
        }

        /* Connection type cards */
        .connection-cards { display: grid; grid-template-columns: 1fr; gap: 0.75rem; margin: 1rem 0; }
        .connection-card {
            padding: 1.25rem; border: 2px solid #334155; border-radius: 0.75rem;
            cursor: pointer; transition: all 0.2s; background: transparent;
        }
        .connection-card:hover { background: rgba(99,102,241,0.05); border-color: #475569; }
        .connection-card.selected { border-color: #6366f1; background: rgba(99,102,241,0.1); }
        .connection-card input { display: none; }
        .connection-card strong { color: #f8fafc; font-size: 1rem; display: block; margin-bottom: 0.25rem; }
        .connection-card span { color: #94a3b8; font-size: 0.85rem; }

        /* AI Provider cards */
        .provider-card {
            padding: 1.25rem; border: 1px solid #334155; border-radius: 0.75rem;
            margin-bottom: 1rem; background: rgba(15,23,42,0.5);
        }
        .provider-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
        .provider-logo { flex-shrink: 0; }
        .provider-name { font-weight: 600; color: #f8fafc; font-size: 1rem; }
        .provider-note { font-size: 0.8rem; color: #94a3b8; }
        .provider-fields { display: grid; grid-template-columns: 1fr auto; gap: 0.75rem; align-items: end; }
        .provider-fields .form-group { margin-bottom: 0; }
        .provider-fields select { min-width: 160px; }
        .gemini-banner {
            background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3);
            border-radius: 0.5rem; padding: 0.75rem 1rem; margin-bottom: 1rem;
            font-size: 0.85rem; color: #fcd34d;
        }
        .gemini-banner strong { color: #fbbf24; }

        /* Prompt box */
        .prompt-box {
            background: #0f172a; border: 1px solid #334155; border-radius: 0.75rem;
            padding: 1.25rem; font-size: 0.85rem; line-height: 1.7; color: #cbd5e1;
            white-space: pre-wrap; word-wrap: break-word; margin: 1rem 0;
            position: relative; max-height: 400px; overflow-y: auto;
        }

        /* Celebration */
        .celebration { text-align: center; padding: 1rem 0; }
        .celebration-icon { font-size: 4rem; margin-bottom: 1rem; }

        .small { font-size: 0.8rem; color: #64748b; margin-top: 0.3rem; }

        /* Password visibility toggle */
        .input-with-toggle { position: relative; }
        .input-with-toggle input { padding-right: 3rem; }
        .toggle-visibility {
            position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #94a3b8; cursor: pointer;
            font-size: 0.85rem; padding: 0.25rem;
        }
        .toggle-visibility:hover { color: #e2e8f0; }
    </style>
</head>
<body>
<div class="wizard">
    <div class="logo">
        <div class="logo-mark"><img src="assets/images/klytos-logo-120.png" alt="Klytos"></div>
        <h1>Klytos</h1>
        <p>Setup Wizard</p>
    </div>

    <!-- Step progress bar -->
    <div class="steps">
        <?php for ( $i = 1; $i <= 6; $i++ ): ?>
            <div class="step-dot <?php
                if ( $i < $currentStepNum ) echo 'done';
                elseif ( $i === $currentStepNum ) echo 'active';
            ?>"></div>
        <?php endfor; ?>
    </div>

    <?php if ( !empty( $error ) ): ?>
        <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
    <?php endif; ?>

    <?php if ( !empty( $success ) ): ?>
        <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
    <?php endif; ?>

    <!-- ─── Screen 1: Two-Factor Authentication ─── -->
    <?php if ( $step === '2fa' ): ?>
    <div class="card">
        <h2>Secure Your Account</h2>
        <p class="subtitle">
            Two-factor authentication adds an extra layer of security. We strongly recommend enabling it now.
        </p>

        <div class="alert alert-info">
            You will need an authenticator app like <strong>Google Authenticator</strong>,
            <strong>1Password</strong>, or <strong>Authy</strong>.
        </div>

        <div class="btn-row">
            <form method="post">
                <?php echo $csrf; ?>
                <input type="hidden" name="wizard_action" value="generate_totp">
                <button type="submit" class="btn btn-block">Set up 2FA</button>
            </form>
            <form method="post">
                <?php echo $csrf; ?>
                <input type="hidden" name="wizard_action" value="skip_2fa">
                <button type="submit" class="btn btn-ghost btn-block">Skip for now</button>
            </form>
        </div>
    </div>

    <!-- ─── Screen 1b: Verify TOTP Code ─── -->
    <?php elseif ( $step === '2fa_verify' ): ?>
    <div class="card">
        <h2>Scan QR Code</h2>
        <p class="subtitle">
            Scan this QR code with your authenticator app, then enter the 6-digit code to verify.
        </p>

        <div style="text-align: center;">
            <div class="qr-container" id="qrCode"></div>
        </div>

        <div class="form-group" style="margin-top: 1rem;">
            <label>Manual entry key</label>
            <div class="token-box"><?php echo klytos_esc_html( $totpSecret ); ?></div>
        </div>

        <form method="post">
            <?php echo $csrf; ?>
            <input type="hidden" name="wizard_action" value="verify_totp">
            <div class="form-group">
                <label for="totp_code">Enter 6-digit code</label>
                <input type="text" id="totp_code" name="totp_code" class="totp-input"
                       maxlength="6" pattern="\d{6}" autocomplete="one-time-code"
                       inputmode="numeric" autofocus required>
            </div>
            <button type="submit" class="btn btn-block">Verify</button>
        </form>
    </div>

    <script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>" src="assets/js/klytos-qrcode.js"></script>
    <script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            var uri = <?php echo json_encode( $totpUri, JSON_UNESCAPED_SLASHES ); ?>;
            var container = document.getElementById('qrCode');
            if (container && window.KlytosQR) {
                window.KlytosQR.generate('qrCode', uri, { moduleSize: 4, quietZone: 4 });
            }
        });
    </script>

    <!-- ─── Screen 1c: Recovery Codes ─── -->
    <?php elseif ( $step === '2fa_recovery' ): ?>
    <div class="card">
        <h2>Recovery Codes</h2>
        <p class="subtitle">
            Save these recovery codes in a safe place. Each code can only be used once.
            If you lose access to your authenticator app, these codes are the only way to log in.
        </p>

        <div class="alert alert-warning">
            <strong>Save these codes now!</strong> They will not be shown again.
        </div>

        <div class="recovery-grid">
            <?php foreach ( $recoveryCodes as $code ): ?>
                <div class="recovery-code"><?php echo klytos_esc_html( $code ); ?></div>
            <?php endforeach; ?>
        </div>

        <form method="post">
            <?php echo $csrf; ?>
            <input type="hidden" name="wizard_action" value="recovery_confirmed">
            <button type="submit" class="btn btn-success btn-block" style="margin-top: 1rem;">
                I have saved my recovery codes
            </button>
        </form>
    </div>

    <!-- ─── Screen 1d: Encryption Key Backup ─── -->
    <?php elseif ( $step === 'encryption_key' ): ?>
    <?php
        $keyPath = dirname( __DIR__ ) . '/config/.encryption_key';
        $keyExists = file_exists( $keyPath );
        $keyBase64 = $keyExists ? base64_encode( file_get_contents( $keyPath ) ) : '';
    ?>
    <div class="card">
        <h2>Encryption Key Backup</h2>
        <p class="subtitle">
            Klytos encrypts all your data with AES-256-GCM. The encryption key is the
            <strong>only way</strong> to access your content. If this key is lost,
            <strong>all data is permanently unrecoverable</strong>.
        </p>

        <div class="alert alert-error" style="border-color:rgba(239,68,68,0.5)">
            <strong>This is critical.</strong> Without this key, your pages, users, settings,
            and all encrypted data cannot be recovered — not even by us.
        </div>

        <div class="form-group">
            <label>Your Encryption Key</label>
            <div class="token-box highlight" id="encKeyBox" style="font-size:0.75rem">
                <?php echo klytos_esc_html( $keyBase64 ); ?>
                <button type="button" class="copy-btn" data-copy="encKeyBox">Copy</button>
            </div>
            <p class="small">Format: Base64-encoded (decode to get the raw 32-byte key).</p>
        </div>

        <div class="form-group">
            <label>Download as file</label>
            <a href="#" id="downloadKeyBtn" class="btn btn-secondary btn-sm" style="display:inline-flex">
                Download .key file
            </a>
            <p class="small">
                Store this file in a safe place (password manager, encrypted USB, offline backup).
                Never share it publicly.
            </p>
        </div>

        <form method="post" style="margin-top: 1.5rem;">
            <?php echo $csrf; ?>
            <input type="hidden" name="wizard_action" value="encryption_key_confirmed">
            <button type="submit" class="btn btn-success btn-block">
                I have saved my encryption key
            </button>
        </form>
    </div>

    <script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            // Copy button
            document.querySelectorAll('.copy-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var targetId = btn.getAttribute('data-copy');
                    var el = document.getElementById(targetId);
                    var text = el.textContent.replace(/Copy|Copied!/, '').trim();
                    navigator.clipboard.writeText(text).then(function() {
                        btn.textContent = 'Copied!';
                        btn.classList.add('copied');
                        setTimeout(function() { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
                    });
                });
            });

            // Download .key file
            var dlBtn = document.getElementById('downloadKeyBtn');
            if (dlBtn) {
                dlBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var keyB64 = <?php echo json_encode( $keyBase64 ); ?>;
                    var raw = atob(keyB64);
                    var bytes = new Uint8Array(raw.length);
                    for (var i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
                    var blob = new Blob([bytes], { type: 'application/octet-stream' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'klytos-encryption.key';
                    a.click();
                    URL.revokeObjectURL(url);
                });
            }
        });
    </script>

    <!-- ─── Screen 2: Connection Types ─── -->
    <?php elseif ( $step === 'connection' ): ?>
    <div class="card">
        <h2>How will you connect?</h2>
        <p class="subtitle">
            Choose how AI assistants will interact with your Klytos site.
        </p>

        <form method="post" id="connectionForm">
            <?php echo $csrf; ?>
            <input type="hidden" name="wizard_action" value="set_connection">

            <div class="connection-cards">
                <label class="connection-card selected">
                    <input type="radio" name="connection_type" value="both" checked>
                    <strong>MCP + API Keys (Recommended)</strong>
                    <span>Maximum flexibility. Use MCP protocol for AI assistants and API keys for direct AI integration (chat, image generation).</span>
                </label>
                <label class="connection-card">
                    <input type="radio" name="connection_type" value="mcp">
                    <strong>MCP Only</strong>
                    <span>Connect AI assistants via Model Context Protocol. AI features managed externally.</span>
                </label>
                <label class="connection-card">
                    <input type="radio" name="connection_type" value="api_keys">
                    <strong>API Keys Only</strong>
                    <span>Direct integration with AI providers for built-in chat and image generation.</span>
                </label>
            </div>

            <button type="submit" class="btn btn-block" style="margin-top: 1rem;">Continue</button>
        </form>
    </div>

    <script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            var cards = document.querySelectorAll('.connection-card');
            cards.forEach(function(card) {
                card.addEventListener('click', function() {
                    cards.forEach(function(c) { c.classList.remove('selected'); });
                    card.classList.add('selected');
                    card.querySelector('input').checked = true;
                });
            });
        });
    </script>

    <!-- ─── Screen 3: AI Provider API Keys ─── -->
    <?php elseif ( $step === 'ai_keys' ): ?>
    <div class="card">
        <h2>AI Provider API Keys</h2>
        <p class="subtitle">
            Add your API keys for AI providers. All fields are optional &mdash; you can configure them later from the admin panel.
        </p>

        <div class="gemini-banner">
            <strong>&#9888; Google Gemini is essential for AI image generation.</strong>
            Without a Gemini API key, your AI assistant won't be able to create images for your site.
        </div>

        <form method="post">
            <?php echo $csrf; ?>
            <input type="hidden" name="wizard_action" value="save_ai_keys">

            <?php foreach ( \Klytos\Core\Ai\AiKeyManager::PROVIDERS as $providerId => $provider ): ?>
            <div class="provider-card">
                <div class="provider-header">
                    <div class="provider-logo">
                        <?php echo $providerLogos[$providerId] ?? ''; ?>
                    </div>
                    <div>
                        <div class="provider-name"><?php echo klytos_esc_html( $provider['name'] ); ?></div>
                        <?php if ( $providerId === 'gemini' ): ?>
                            <div class="provider-note" style="color: #fbbf24;">Required for image generation</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="provider-fields">
                    <div class="form-group">
                        <label for="ai_key_<?php echo klytos_esc_attr( $providerId ); ?>">API Key</label>
                        <div class="input-with-toggle">
                            <input type="password" id="ai_key_<?php echo klytos_esc_attr( $providerId ); ?>"
                                   name="ai_key_<?php echo klytos_esc_attr( $providerId ); ?>"
                                   placeholder="sk-..." autocomplete="off">
                            <button type="button" class="toggle-visibility" data-target="ai_key_<?php echo klytos_esc_attr( $providerId ); ?>">Show</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="ai_model_<?php echo klytos_esc_attr( $providerId ); ?>">Model</label>
                        <select id="ai_model_<?php echo klytos_esc_attr( $providerId ); ?>"
                                name="ai_model_<?php echo klytos_esc_attr( $providerId ); ?>">
                            <?php foreach ( $provider['models'] as $model ): ?>
                                <option value="<?php echo klytos_esc_attr( $model['id'] ); ?>"
                                    <?php echo $model['id'] === $provider['default_model'] ? 'selected' : ''; ?>>
                                    <?php echo klytos_esc_html( $model['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="btn-row">
                <button type="submit" class="btn" style="flex:1;">Save Keys</button>
                <button type="submit" name="wizard_action" value="skip_ai_keys" class="btn btn-ghost" style="flex:1;">Skip for now</button>
            </div>
        </form>
    </div>

    <script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.toggle-visibility').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var targetId = btn.getAttribute('data-target');
                    var input = document.getElementById(targetId);
                    if (input.type === 'password') {
                        input.type = 'text';
                        btn.textContent = 'Hide';
                    } else {
                        input.type = 'password';
                        btn.textContent = 'Show';
                    }
                });
            });
        });
    </script>

    <!-- ─── Screen 4: Application Password + MCP ─── -->
    <?php elseif ( $step === 'app_password' ): ?>
    <div class="card">
        <h2>Connect Your AI Assistant</h2>
        <p class="subtitle">
            Generate an Application Password so your AI assistant can control Klytos via MCP.
        </p>

        <div class="alert alert-info">
            This creates a secure password specifically for your AI tool (Claude, Cursor, etc.).
            Your admin password remains separate.
        </div>

        <form method="post">
            <?php echo $csrf; ?>
            <input type="hidden" name="wizard_action" value="generate_app_password">
            <button type="submit" class="btn btn-block">Generate Application Password</button>
        </form>
    </div>

    <?php elseif ( $step === 'app_password_show' ): ?>
    <?php
        // Build the full MCP URL with embedded credentials.
        // Format: https://user:pass@domain.com/path/mcp
        $parsedUrl  = parse_url( $mcpEndpoint );
        $mcpAuthUrl = ( $parsedUrl['scheme'] ?? 'https' ) . '://'
                    . urlencode( $username ) . ':' . urlencode( $newAppPassword )
                    . '@' . ( $parsedUrl['host'] ?? '' )
                    . ( isset( $parsedUrl['port'] ) ? ':' . $parsedUrl['port'] : '' )
                    . ( $parsedUrl['path'] ?? '' );

        $basicAuth = base64_encode( $username . ':' . $newAppPassword );
        $mcpJson   = json_encode([
            'mcpServers' => [
                'klytos' => [
                    'url'     => $mcpEndpoint,
                    'headers' => [
                        'Authorization' => 'Basic ' . $basicAuth,
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    ?>
    <div class="card">
        <h2>Your MCP Configuration</h2>
        <p class="subtitle">
            Copy this URL and paste it into your AI assistant (Claude Desktop, Claude Code, Cursor, etc.).
        </p>

        <div class="alert alert-warning">
            <strong>Save this information now!</strong> The Application Password will not be shown again.
        </div>

        <!-- ① MCP URL with credentials (PRIMARY — copy and paste) -->
        <div class="form-group">
            <label>MCP Connection URL</label>
            <p class="small" style="margin-top:0;margin-bottom:0.5rem">
                This is the URL your AI assistant will use to connect to your site.
            </p>
            <div class="token-box highlight" id="mcpUrlBox">
                <?php echo klytos_esc_html( $mcpAuthUrl ); ?>
                <button type="button" class="copy-btn" data-copy="mcpUrlBox">Copy</button>
            </div>
        </div>

        <!-- ② JSON config (SECONDARY — collapsible) -->
        <details style="margin-bottom: 1.25rem;">
            <summary style="cursor:pointer;font-weight:600;font-size:0.9rem;color:#a5b4fc;">
                JSON Configuration (for manual setup)
            </summary>
            <div class="mcp-config" id="mcpConfigBox" style="margin-top:0.75rem"><?php echo klytos_esc_html( $mcpJson ); ?><button type="button" class="copy-btn" data-copy="mcpConfigBox">Copy</button></div>
        </details>

        <!-- ③ Credentials (TERTIARY — collapsible) -->
        <details style="margin-bottom: 1.25rem;">
            <summary style="cursor:pointer;font-weight:600;font-size:0.9rem;color:#94a3b8;">
                Show credentials separately
            </summary>
            <div style="margin-top:0.75rem;">
                <div class="form-group">
                    <label>MCP Endpoint</label>
                    <div class="token-box" id="endpointBox">
                        <?php echo klytos_esc_html( $mcpEndpoint ); ?>
                        <button type="button" class="copy-btn" data-copy="endpointBox">Copy</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>User</label>
                    <div class="token-box"><?php echo klytos_esc_html( $username ); ?></div>
                </div>
                <div class="form-group">
                    <label>Application Password</label>
                    <div class="token-box highlight" id="appPassBox">
                        <?php echo klytos_esc_html( $newAppPassword ); ?>
                        <button type="button" class="copy-btn" data-copy="appPassBox">Copy</button>
                    </div>
                </div>
            </div>
        </details>

        <form method="post" style="margin-top: 1rem;">
            <?php echo $csrf; ?>
            <input type="hidden" name="wizard_action" value="go_to_congrats">
            <button type="submit" class="btn btn-success btn-block">Continue</button>
        </form>
    </div>

    <script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.copy-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var targetId = btn.getAttribute('data-copy');
                    var el = document.getElementById(targetId);
                    var text = el.textContent.replace(/Copy|Copied!/, '').trim();
                    navigator.clipboard.writeText(text).then(function() {
                        btn.textContent = 'Copied!';
                        btn.classList.add('copied');
                        setTimeout(function() {
                            btn.textContent = 'Copy';
                            btn.classList.remove('copied');
                        }, 2000);
                    });
                });
            });
        });
    </script>

    <!-- ─── Screen 5: Congratulations ─── -->
    <?php elseif ( $step === 'congrats' ): ?>
    <?php
        $siteName    = klytos_esc_html( $config['site_name'] ?? 'My Site' );
        $siteUrl     = Helpers::publicUrl();
        $adminLang   = $config['admin_language'] ?? 'en';
        $isSpanish   = str_starts_with( $adminLang, 'es' );

        if ( $isSpanish ) {
            $promptText = "Quiero crear mi sitio web con Klytos CMS.

Mi sitio se llama \"{$siteName}\" y esta disponible en {$siteUrl}
El endpoint MCP es: {$mcpEndpoint}

Llama a la herramienta klytos_start_site_builder para comenzar el proceso guiado de creacion del sitio.
Sigue todas las fases de la guia que recibiras, preguntandome en cada paso antes de ejecutar nada.";
        } else {
            $promptText = "I want to create my website with Klytos CMS.

My site is called \"{$siteName}\" and it's available at {$siteUrl}
The MCP endpoint is: {$mcpEndpoint}

Call the klytos_start_site_builder tool to start the guided site creation process.
Follow all phases from the guide you'll receive, asking me at each step before executing anything.";
        }
    ?>
    <div class="card">
        <div class="celebration">
            <div class="celebration-icon">&#127881;</div>
            <h2><?php echo $isSpanish ? 'Klytos esta listo' : 'Klytos is Ready'; ?></h2>
            <p class="subtitle">
                <?php echo $isSpanish
                    ? 'Tu CMS esta completamente configurado. Copia el siguiente prompt y pegalo en tu asistente de IA para comenzar a crear tu sitio web.'
                    : 'Your CMS is fully configured. Copy the prompt below and paste it into your AI assistant to start building your website.'; ?>
            </p>
        </div>

        <div class="form-group">
            <label><?php echo $isSpanish ? 'Prompt para tu asistente de IA' : 'Prompt for your AI assistant'; ?></label>
            <div class="prompt-box" id="promptBox"><?php echo klytos_esc_html( $promptText ); ?><button type="button" class="copy-btn" data-copy="promptBox"><?php echo $isSpanish ? 'Copiar' : 'Copy'; ?></button></div>
        </div>

        <div class="alert alert-info">
            <?php echo $isSpanish
                ? 'Este prompt es un punto de partida. Si ya tienes experiencia, puedes escribir tu propio prompt detallado para crear todo el sitio de una vez.'
                : 'This prompt is a starting point. If you already have experience, you can write your own detailed prompt to create the entire site at once.'; ?>
        </div>

        <form method="post" style="margin-top: 1.5rem;">
            <?php echo $csrf; ?>
            <input type="hidden" name="wizard_action" value="complete_setup">
            <button type="submit" class="btn btn-success btn-block">
                <?php echo $isSpanish ? 'Ir al Panel de Administracion' : 'Go to Dashboard'; ?>
            </button>
        </form>
    </div>

    <script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.copy-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var targetId = btn.getAttribute('data-copy');
                    var el = document.getElementById(targetId);
                    var text = el.textContent.replace(/Copiar|Copy|Copied!|Copiado!/, '').trim();
                    navigator.clipboard.writeText(text).then(function() {
                        var lang = document.documentElement.lang;
                        btn.textContent = lang === 'es' ? 'Copiado!' : 'Copied!';
                        btn.classList.add('copied');
                        setTimeout(function() {
                            btn.textContent = lang === 'es' ? 'Copiar' : 'Copy';
                            btn.classList.remove('copied');
                        }, 2000);
                    });
                });
            });
        });
    </script>

    <?php endif; ?>
</div>
</body>
</html>
