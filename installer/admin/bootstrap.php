<?php

/**
 * Klytos Admin — Bootstrap
 * Common initialization for all admin pages.
 * Include this at the top of every admin page.
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

$rootPath = dirname( __DIR__ );

// ─── Define __() translation function ONCE ───────────────────
// This function reads from $GLOBALS['klytos_i18n'].
// Before boot(): klytos_i18n is null → returns fallback label.
// After boot(): klytos_i18n is the real I18n instance → returns translation.
if ( ! function_exists( '__' ) ) {
    function __( string $key, array $replacements = [] ): string
    {
        if ( isset( $GLOBALS['klytos_i18n'] ) && $GLOBALS['klytos_i18n'] !== null ) {
            return $GLOBALS['klytos_i18n']->get( $key, $replacements );
        }
        $parts = explode( '.', $key );
        $label = ucfirst( str_replace( '_', ' ', end( $parts ) ) );
        foreach ( $replacements as $k => $v ) {
            $label = str_replace( '{' . $k . '}', (string) $v, $label );
        }
        return $label;
    }
}

// ─── Determine install URL before loading anything ───────────
// This must work even if core files fail to load.
$installUrl = dirname( $_SERVER['SCRIPT_NAME'] ) . '/../install.php';
$installUrl = str_replace( '//', '/', $installUrl );

// ─── Check if Klytos is installed (lightweight check) ────────
// Before even loading the App class, check if the encryption key exists.
// If it doesn't, the system is not installed → redirect to installer.
$configPath = $rootPath . '/config';
if ( ! file_exists( $configPath . '/.encryption_key' ) || ! file_exists( $configPath . '/config.json.enc' ) ) {
    header( 'Location: ' . $installUrl );
    exit;
}

// ─── Load the application ────────────────────────────────────
try {
    require_once $rootPath . '/core/app.php';
} catch ( \Throwable $e ) {
    error_log( 'Klytos: failed to load core — ' . $e->getMessage() );
    header( 'Location: ' . $installUrl );
    exit;
}

use Klytos\Core\App;
use Klytos\Core\Helpers;

// Load security helpers early so klytos_esc_html() is available
// in the error handler below, even if boot() fails midway.
require_once $rootPath . '/core/helpers-security.php';

try {
    $app = App::getInstance();

    if ( ! $app->isInstalled() ) {
        header( 'Location: ' . $installUrl );
        exit;
    }

    $app->boot();
} catch ( \Throwable $e ) {
    error_log( 'Klytos boot error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
    error_log( 'Klytos boot trace: ' . $e->getTraceAsString() );

    http_response_code( 500 );
    echo '<!DOCTYPE html><html><head><title>Klytos Error</title></head><body>';
    echo '<div style="max-width:600px;margin:4rem auto;font-family:sans-serif;text-align:center">';
    echo '<h1 style="color:#dc2626">Klytos Boot Error</h1>';
    echo '<p>The application failed to start. Check the PHP error log for details.</p>';
    echo '<pre style="background:#f1f5f9;padding:1rem;border-radius:8px;text-align:left;font-size:0.85rem;overflow:auto">';
    echo klytos_esc_html( $e->getMessage() );
    echo '</pre>';
    echo '</div></body></html>';
    exit( 1 );
}

// ─── Security headers (NEW-14, S-11) ─────────────────────────
// ONE enforcement point for all 64 admin surfaces, in the same shape and for
// the same reason as the authorization gate below: Auth::sendSecurityHeaders()
// had six call sites repo-wide and NONE was an admin API endpoint, so 0 of the
// 23 files in admin/api/ sent any header, while login.php and logout.php —
// pages, but the two that do not include templates/header.php — sent none
// either. Adding 25 remembered calls has the S-07 failure mode: surface 26
// forgets. This call cannot be forgotten, because every admin file requires
// this bootstrap (verified mechanically, zero exceptions).
//
// PLACEMENT IS LOAD-BEARING, and it is bounded on both sides:
//   - It must run BEFORE anything that emits. Everything below this line that
//     can produce output — the pending-rename redirect, the auth guard's 401
//     JSON and its 302, the gate's 403 refusal document, the setup-wizard
//     redirect — is therefore covered, as are all page and endpoint bodies.
//   - It cannot run any EARLIER than this. App::boot() Step 1 is
//     registerAutoloader() (app.php:268), so the Auth class does not resolve
//     before boot returns, and klytos_apply_filters() does not exist until
//     app.php:331 requires helpers-global.php during the same boot. Verified
//     by probe, not inferred — L-006's rule is that initialization-time code
//     may only use services already initialized at that exact point.
//
// The consequence of that lower bound, stated rather than left implied: the
// boot-FAILURE page above (the 500 that echoes an escaped exception message)
// and the two pre-boot redirects still send no security headers, because the
// autoloader does not exist yet on those paths. Recorded as NEW-22.
//
// The nonce is generated ONCE per request here and published in $GLOBALS, so
// templates/header.php reuses it rather than minting a second one. A page that
// needs its own policy re-calls sendSecurityHeaders() with a $customCsp;
// header() replaces same-name headers, so the later call simply wins.
$GLOBALS['klytos_csp_nonce'] = \Klytos\Core\Auth::generateCspNonce();
\Klytos\Core\Auth::sendSecurityHeaders( $GLOBALS['klytos_csp_nonce'] );

// ─── Fatal-error shutdown handler ────────────────────────────
// Catches fatal errors that occur AFTER boot (during page rendering).
// These are invisible to try-catch and would otherwise go unlogged.
register_shutdown_function( function () use ( $app ) {
    $error = error_get_last();
    if ( $error === null ) {
        return;
    }

    // Only catch fatal-class errors.
    $fatalTypes = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
    if ( ! ( $error['type'] & $fatalTypes ) ) {
        return;
    }

    $message = sprintf(
        'Fatal error: %s in %s:%d',
        $error['message'],
        $error['file'],
        $error['line']
    );

    // Always log to PHP error log as fallback.
    error_log( 'Klytos fatal: ' . $message );

    // Write to Klytos logs unconditionally (bypasses Developer Mode).
    try {
        $app->getLogger()->writeAlways( 'critical', $message, [
            'type' => $error['type'],
            'file' => $error['file'],
            'line' => $error['line'],
        ] );
    } catch ( \Throwable $e ) {
        // Logger itself failed — PHP error log is our last resort.
        error_log( 'Klytos: shutdown logger failed — ' . $e->getMessage() );
    }
} );

// ─── Pending directory rename ────────────────────────────────
// If the installer couldn't rename the directory (common on shared hosting
// because PHP had files open), retry now on the first subsequent request.
$pendingRename = $app->getConfig()['pending_rename'] ?? null;
if ( $pendingRename !== null ) {
    $currentDir = basename( dirname( __DIR__ ) );
    $parentDir  = dirname( dirname( __DIR__ ) );
    $targetPath = $parentDir . '/' . $pendingRename;

    if ( $currentDir !== $pendingRename && ! is_dir( $targetPath ) ) {
        $renamed = @rename( dirname( __DIR__ ), $targetPath );

        if ( ! $renamed && function_exists( 'exec' ) ) {
            $src = escapeshellarg( dirname( __DIR__ ) );
            $dst = escapeshellarg( $targetPath );
            @exec( "mv {$src} {$dst} 2>&1", $out, $code );
            $renamed = ( $code === 0 && is_dir( $targetPath ) );
        }

        if ( $renamed ) {
            // Update config: clear pending flag, set real admin_dir + URLs.
            $cfg = $app->getConfig();
            $cfg['admin_dir'] = $pendingRename;
            unset( $cfg['pending_rename'] );

            // Recalculate URLs using install_base (set during installation).
            $scheme      = Helpers::isHttps() ? 'https' : 'http';
            $host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $installBase = $cfg['install_base'] ?? '/';

            $cfg['mcp_endpoint'] = $scheme . '://' . $host . rtrim( $installBase, '/' ) . '/' . $pendingRename . '/mcp';
            $cfg['admin_url']    = $scheme . '://' . $host . rtrim( $installBase, '/' ) . '/' . $pendingRename . '/admin/';

            $app->getStorage()->writeTo( $targetPath . '/config', 'config.json.enc', $cfg );

            // Redirect to the new URL.
            $newUrl = $cfg['admin_url'] . basename( $_SERVER['SCRIPT_NAME'] );
            if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
                $newUrl .= '?' . $_SERVER['QUERY_STRING'];
            }
            header( 'Location: ' . $newUrl );
            exit;
        }
    }
}

// ─── Start admin session ─────────────────────────────────────
$app->getAuth()->startSession();

// ─── Run pseudo-cron (non-blocking) ──────────────────────────
try {
    $app->getCronManager()->runDueTasks();
} catch ( \Throwable $e ) {
    error_log( 'Klytos cron error: ' . $e->getMessage() );
}

// ─── Action Scheduler fallback (pseudo-cron) ────────────────
try {
    $app->getActionScheduler()->processQueueIfFallback();
} catch ( \Throwable $e ) {
    error_log( 'Klytos scheduler fallback error: ' . $e->getMessage() );
}

// ─── Admin Notices auto-render ──────────────────────────────
// Render queued notices at the top of every admin page via the
// 'admin.page.before_content' hook fired in sidebar.php.
klytos_add_action( 'admin.page.before_content', function ( string $currentPage ) use ( $app ): void {
    $cspNonce = $GLOBALS['klytos_csp_nonce'] ?? '';
    $app->getNoticeManager()->render( $cspNonce, $currentPage );
}, 5 );

// ─── System Notices ─────────────────────────────────────────
// Register core system notices with condition hooks.
klytos_add_action( 'klytos.init', function ( $app ): void {
    // Indexing-blocked warning: only shows when indexing is disabled.
    $app->getNoticeManager()->ensureSystemNotice( 'indexing-blocked', [
        'message'        => __( 'indexing.blocked_title' ) . ' — ' . __( 'indexing.blocked_description' ),
        'type'           => 'warning',
        'dismissible'    => false,
        'context'        => 'dashboard',
        'condition_hook' => 'notice.condition.indexing_blocked',
        'ads'            => false,
    ] );
} );

klytos_add_filter( 'notice.condition.indexing_blocked', function ( bool $show ): bool {
    $config = klytos_app()->getSiteConfig()->get();
    return ! ( $config['indexing_enabled'] ?? false );
} );

// Encryption key backup warning: shows until the user confirms backup.
klytos_add_action( 'klytos.init', function ( $app ): void {
    $app->getNoticeManager()->ensureSystemNotice( 'encryption-key-backup', [
        'message'        => 'Your encryption key has not been backed up. If this key is lost, ALL site data is permanently unrecoverable. Go to Settings to download your encryption key.',
        'type'           => 'error',
        'dismissible'    => false,
        'context'        => '',
        'condition_hook' => 'notice.condition.encryption_key_not_backed_up',
        'ads'            => false,
    ] );
} );

klytos_add_filter( 'notice.condition.encryption_key_not_backed_up', function ( bool $show ): bool {
    $config = klytos_app()->getSiteConfig()->get();
    return ! ( $config['encryption_key_backed_up'] ?? false );
} );

// ─── Auth guard ──────────────────────────────────────────────
// If not authenticated and not on a pre-auth page, refuse — in the shape the
// caller can parse. This used to 302 EVERY unauthenticated request to the HTML
// login page, including the 24 JSON endpoints under admin/api/, so an XHR
// received login HTML and a JSON parse error instead of a status it could act
// on. It also made the isAuthenticated() re-checks inside 20 of those
// endpoints unreachable, and with them the 401 contract they advertise.
// Recorded next to S-07 in docs/04-adoption-audit.md.
$currentScript = basename( $_SERVER['SCRIPT_NAME'] );

// api/webauthn-challenge.php is deliberately NOT exempt, although the passkey
// second-factor flow needs it to be. Slice 4 added the exemption and then
// REMOVED it, because the security-auditor pass showed the exemption opens a
// full account-takeover primitive (NEW-09):
//
//   is2faPending() is true as soon as a caller supplies a correct PASSWORD
//   (auth.php:112-118) — before any second factor. That endpoint gates all
//   four of its actions on ( isAuthenticated() || is2faPending() ), and
//   TwoFactor::completePasskeyRegistration() (two-factor.php:507-530) appends
//   the new credential and sets enabled = true without checking that the
//   caller ever passed an EXISTING factor. So a password alone would let an
//   attacker enrol their own authenticator and hold the account permanently.
//
// The redirect is what was incidentally preventing that, and removing it buys
// nothing today anyway: passkey login cannot complete regardless, because
// login.php's 2FA dispatcher has no 'passkey' branch and
// TwoFactor::verifyPasskeyAssertion() (two-factor.php:586) has zero call
// sites. Fixing this properly means restricting the endpoint's actions AND
// building the missing verification path — its own slice, with its own tests.
$preAuthScripts = [ 'login.php', 'logout.php', 'reset-password.php' ];

if ( ! in_array( $currentScript, $preAuthScripts, true ) ) {
    if ( ! $app->getAuth()->isAuthenticated() ) {
        if ( klytos_current_surface() === 'api' ) {
            \Klytos\Core\Helpers::jsonResponse(
                [ 'error' => __( 'common.authentication_required' ), 'code' => 'authentication_required' ],
                401
            );
        }

        $loginUrl = dirname( $_SERVER['SCRIPT_NAME'] ) . '/login.php?redirect_to=' . urlencode( $_SERVER['REQUEST_URI'] );
        header( 'Location: ' . $loginUrl );
        exit;
    }
}

// ─── Authorization gate (S-07) ───────────────────────────────
// The central default-deny gate. Every admin page and API endpoint requires
// this bootstrap, so this single call gates all 66 of them — and an admin file
// with no entry in the map is REFUSED, not waved through. Runs after the auth
// guard (it needs an identity) and before the setup-wizard redirect (so a
// non-owner is told 403 rather than bounced into a wizard that would then
// refuse them anyway).
//
// Pages that mix privilege tiers — the dashboard's indexing toggle, the page
// list's trash purge, the encryption-level change — call
// klytos_require_permission() again inline on the privileged branch. The map
// entry is the floor, not the ceiling.
klytos_enforce_admin_gate();

// ─── Setup wizard redirect (first-login only) ──────────────
// Fresh installations set 'setup_completed' => false in config.
// Existing/upgraded installs don't have this key, so they skip the wizard.
if ( $currentScript !== 'setup-wizard.php'
    && $currentScript !== 'login.php'
    && $currentScript !== 'logout.php'
    && $currentScript !== 'reset-password.php'
) {
    $klytosConfig = $app->getConfig();
    if ( isset( $klytosConfig['setup_completed'] ) && $klytosConfig['setup_completed'] === false ) {
        $wizardUrl = dirname( $_SERVER['SCRIPT_NAME'] ) . '/setup-wizard.php';
        header( 'Location: ' . $wizardUrl );
        exit;
    }
}
