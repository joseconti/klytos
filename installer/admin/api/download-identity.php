<?php

/**
 * Klytos Admin API — Identity Key Download
 * Protected endpoint for downloading the klytos-identity.pem file.
 *
 * Exports the site's RSA private key — the highest-value secret in the system.
 *
 * Security, as ACTUALLY implemented:
 * - Authentication: enforced by admin/bootstrap.php before this file runs.
 * - Authorization: the gate map requires 'users.manage', which is owner-only.
 * - Method: POST required. This endpoint writes state, so it must not answer a GET.
 * - CSRF: verified below.
 * - Rate limit: 1 download per 24 hours.
 * - Audit log entry (writeAlways, so it survives Developer Mode being off).
 *
 * NOT implemented, and previously claimed here in a way that made this endpoint
 * look far better protected than it was (audit S-12, the L-002 class of defect
 * — a doc asserting a property the code does not have):
 * - Re-authentication with the current password.
 * - 2FA verification.
 * - Email notification to the owner.
 *
 * Those three are recorded as audit finding NEW-13, bound to the authentication
 * slice that also owns NEW-09 and NEW-11 — that slice is already opening the
 * password and 2FA plumbing all three need. Until it runs, a stolen admin
 * SESSION is sufficient to export the key; the password is not re-checked.
 * Do not re-add the claims above to this block without the code to match.
 *
 * @package Klytos
 * @since   1.1.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/bootstrap.php';

use Klytos\Core\Encryption;
use Klytos\Core\Helpers;

// ─── Authorization ──────────────────────────────────────────
// Two defects were fixed here in Sprint 1 slice 4:
//
// 1. This called $auth->isLoggedIn(), which DOES NOT EXIST on Auth (the
//    methods are isAuthenticated() and is2faPending()). Every request to this
//    endpoint therefore died with "Call to undefined method" — verified live
//    against the playground as an authenticated owner. Downloading the site
//    identity key has been impossible in production, not merely ungated.
//
// 2. Owner-ness was decided by comparing the session username against
//    config['admin_user']. The ROLE is the authority on privilege, not string
//    equality with a config value that the v1→v2 migration also writes. The
//    gate map requires 'users.manage', which is owner-only in the matrix, so
//    the same set of people pass — through the one decision point (S-04)
//    rather than a second hand-rolled one.
//
// Authentication itself is guaranteed by admin/bootstrap.php, which refuses
// unauthenticated API requests with 401 JSON before this file runs.
$auth = $app->getAuth();

// ─── Method: POST only (audit S-12) ─────────────────────────
// This endpoint WRITES state further down (identity_last_downloaded_at and
// identity_download_count, persisted to config at :98), and it previously had
// no REQUEST_METHOD check at all — so a secret-exporting, state-writing
// operation answered GET. security.php reached it by 302-redirecting, which
// the browser follows as a GET, so that was the normal path rather than an
// edge case.
//
// The exposure was denial-of-service and audit noise rather than exfiltration:
// an attacker could force the owner's browser to issue the request with an
// <img src> or a link, burning the 24-hour rate limit and writing config, but
// could NOT read the key material back, because the response is an
// octet-stream attachment. It still breaks the project's own "don't change
// state on a GET" rule, and a rate limit an unauthenticated third party can
// exhaust for the owner is a real defect.
if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
    header( 'HTTP/1.1 405 Method Not Allowed' );
    header( 'Allow: POST' );
    header( 'Content-Type: text/plain; charset=utf-8' );
    echo 'Method Not Allowed. This endpoint writes state and requires POST.';
    exit;
}

// ─── CSRF ───────────────────────────────────────────────────
// The confirmed half of S-12: there was no klytos_verify_csrf() anywhere in
// this file. The method check above is not a substitute — a cross-origin form
// can POST just as easily as an image tag can GET.
if ( ! klytos_verify_csrf() ) {
    header( 'HTTP/1.1 403 Forbidden' );
    header( 'Content-Type: text/plain; charset=utf-8' );
    echo 'Invalid CSRF token.';
    exit;
}

// Still needed below: it labels the exported key file and the audit-log entry.
// It is no longer what DECIDES access — that is the gate map's 'users.manage'.
$username = $auth->getUsername();
$config   = $app->getStorage()->readFrom( $app->getConfigPath(), 'config.json.enc' );

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
// A SECOND non-existent method call lived here, masked by the first one:
// Logger has no log(). Its API is write()/writeAlways( $level, $message,
// $context, $source ). The `method_exists( $app, 'getLogger' )` guard did not
// catch it because it interrogates App — which does have getLogger() — rather
// than the Logger the call is actually made against, so it passed and then
// fataled one line later.
//
// writeAlways() rather than write(): exporting the site's RSA private key is
// an audit event, and write() discards everything unless Developer Mode is on
// (logger.php:116). An audit trail that only exists in debug mode is not one.
$app->getLogger()->writeAlways(
    'warning',
    'Identity key downloaded',
    [
        'user'       => $username,
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    ],
    'security'
);

// ─── Send File ──────────────────────────────────────────────
header( 'Content-Type: application/octet-stream' );
header( 'Content-Disposition: attachment; filename="klytos-identity.pem"' );
header( 'Content-Length: ' . strlen( $content ) );
header( 'Cache-Control: no-store, no-cache, must-revalidate' );
header( 'Pragma: no-cache' );
header( 'Expires: 0' );
echo $content;
exit;
