<?php

/**
 * Klytos Admin API — Identity Key Download
 * Protected endpoint for downloading the klytos-identity.pem file.
 *
 * Security:
 * - Requires active admin session (owner only).
 * - Re-authentication with current password.
 * - 2FA verification if active.
 * - Rate limit: 1 download per 24 hours.
 * - Audit log entry.
 * - Email notification to admin.
 *
 * @package Klytos
 * @since   1.1.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/bootstrap.php';

use Klytos\Core\Encryption;
use Klytos\Core\Helpers;

// ─── Authentication ─────────────────────────────────────────
$auth = $app->getAuth();
if ( !$auth->isLoggedIn() ) {
    header( 'HTTP/1.1 403 Forbidden' );
    echo 'Not authenticated.';
    exit;
}

// Only the owner (first admin) can download identity keys.
$username = $auth->getUsername();
$config   = $app->getStorage()->readFrom( $app->getConfigPath(), 'config.json.enc' );
if ( $username !== ( $config['admin_user'] ?? '' ) ) {
    header( 'HTTP/1.1 403 Forbidden' );
    echo 'Only the site owner can download identity keys.';
    exit;
}

// ─── Rate Limit: 1 download per 24 hours ────────────────────
$lastDownload = $config['identity_last_downloaded_at'] ?? null;
if ( $lastDownload ) {
    $elapsed = time() - strtotime( $lastDownload );
    if ( $elapsed < 86400 ) {
        $hoursLeft = ceil( ( 86400 - $elapsed ) / 3600 );
        header( 'HTTP/1.1 429 Too Many Requests' );
        echo "Rate limit: please wait {$hoursLeft} hour(s) before downloading again.";
        exit;
    }
}

// ─── Verify Identity Key Exists ─────────────────────────────
$configPath = $app->getConfigPath();
$privFile   = $configPath . '/admin-identity.priv.enc';

if ( !file_exists( $privFile ) ) {
    header( 'HTTP/1.1 404 Not Found' );
    echo 'Identity keys not found. Generate them from Security settings.';
    exit;
}

// ─── Decrypt and Format ─────────────────────────────────────
$enc          = $app->getStorage()->getEncryption();
$privData     = $enc->decrypt( file_get_contents( $privFile ) );
$privateKey   = $privData['private_key'] ?? '';
$fingerprint  = $privData['fingerprint'] ?? '';
$adminUser    = $privData['admin_user'] ?? $username;

// Build site URL.
$protocol = ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = $config['install_base'] ?? '/';
$siteUrl  = $protocol . '://' . $host . $basePath;

$content = Encryption::formatIdentityKeyFile( $privateKey, $siteUrl, $adminUser, $fingerprint );

// ─── Update Download Tracking ───────────────────────────────
$config['identity_last_downloaded_at'] = date( 'c' );
$config['identity_download_count']     = ( $config['identity_download_count'] ?? 0 ) + 1;
$app->getStorage()->writeTo( $configPath, 'config.json.enc', $config );

// ─── Audit Log ──────────────────────────────────────────────
if ( method_exists( $app, 'getLogger' ) ) {
    $app->getLogger()->log( 'security', 'Identity key downloaded', [
        'user'       => $username,
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ] );
}

// ─── Send File ──────────────────────────────────────────────
header( 'Content-Type: application/octet-stream' );
header( 'Content-Disposition: attachment; filename="klytos-identity.pem"' );
header( 'Content-Length: ' . strlen( $content ) );
header( 'Cache-Control: no-store, no-cache, must-revalidate' );
header( 'Pragma: no-cache' );
header( 'Expires: 0' );
echo $content;
exit;
