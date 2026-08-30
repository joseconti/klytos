<?php

/**
 * Klytos CMS — front controller for PHP's built-in server (development only).
 *
 * Klytos does not require Apache: Router::parseRoute() (core/router.php:99-118)
 * falls back to REQUEST_URI, so the .htaccess rewrites are an optimisation, not
 * a dependency. What .htaccess ALSO does, and what `php -S` silently drops, is
 * deny access to config/, data/, core/, backups/ and the identity key files.
 * Without this script a playground serves installer/config/.encryption_key over
 * HTTP. That is the reason this file exists — it is a security control, not
 * convenience plumbing.
 *
 * Usage (from the repository root):
 *   php -S 127.0.0.1:8080 -t . scripts/dev/router.php
 *
 * Bind to 127.0.0.1 only. The built-in server is single-threaded and has no
 * hardening; it must never be exposed on a routable interface.
 *
 * URL shape mirrors a production subdirectory install (install_base '/' +
 * admin_dir 'installer'):
 *   /                    → generated static site (installer/public/)
 *   /installer/admin/    → admin panel
 *   /installer/mcp       → MCP JSON-RPC endpoint
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

// ─── This file must only ever be PHP's built-in-server router ───────────────
// Sprint 1, slice 9. `scripts/` is NOT export-ignored, so this file ships in
// the release archive to the site root of every install, and the root
// .htaccess serves any existing file directly (.htaccess:23-25) — it does not
// deny /scripts/. Verified by extracting a real `git archive` and requesting
// this path over HTTP: it EXECUTED and returned its 404 page, disclosing the
// admin path, the MCP endpoint and internal build details to anyone who asked.
//
// TWO conditions, because the SAPI alone is not sufficient — and the first
// version of this guard was wrong for exactly that reason:
//
//   1. Apache/FPM report 'apache2handler'/'fpm-fcgi', so the SAPI test covers
//      a production install.
//   2. `php -S` reports 'cli-server' BOTH when this file is the router AND
//      when it is served as an ordinary file, so the SAPI test alone passes in
//      the second case and the file runs its normal logic. Probed, not
//      reasoned about: as the router, SCRIPT_NAME is the REQUESTED path
//      ('/installer/admin/'); served as a file, it points at this script
//      ('/scripts/dev/router.php'). That is the discriminator.
//
// Its sibling seed-playground.php:35 already had a CLI guard; this file and
// upgrade-assert.php did not. The packaging half — that dev scripts ship at
// all — is recorded as NEW-28.
$selfRequest = strtolower( rawurldecode( $_SERVER['SCRIPT_NAME'] ?? '' ) );

if (
    PHP_SAPI !== 'cli-server'
    || str_ends_with( $selfRequest, '/scripts/dev/router.php' )
) {
    http_response_code( 404 );
    header( 'Content-Type: text/plain; charset=utf-8' );
    exit( "404 — not found.\n" );
}

$repoRoot     = dirname( __DIR__, 2 );
$klytosRoot   = $repoRoot . '/installer';
$adminDir     = 'installer';
$requestPath  = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) ?: '/';
$requestPath  = rawurldecode( $requestPath );

// ─── Deny rules — the php -S equivalent of installer/.htaccess:11-18 ─────
// Matched against the path RELATIVE to the Klytos root, exactly as Apache
// matches them, plus a global dotfile and traversal guard.
$denied = static function ( string $path ) use ( $adminDir ): bool {
    // Reject traversal before any other reasoning about the path.
    if ( str_contains( $path, '..' ) ) {
        return true;
    }

    // Any dot-segment anywhere: .git, .env, .encryption_key, .playground-access.
    foreach ( explode( '/', trim( $path, '/' ) ) as $segment ) {
        if ( $segment !== '' && $segment[0] === '.' ) {
            return true;
        }
    }

    // Identity key material and PEM files, wherever they live.
    if ( str_contains( $path, 'admin-identity.' ) || str_ends_with( $path, '.pem' ) ) {
        return true;
    }

    $relative = $path;
    if ( str_starts_with( $path, '/' . $adminDir . '/' ) ) {
        $relative = substr( $path, strlen( $adminDir ) + 1 );
    }
    $relative = ltrim( $relative, '/' );

    // Protected directories (.htaccess: ^data/ ^config/ ^core/ ^backups/).
    foreach ( [ 'data/', 'config/', 'core/', 'backups/' ] as $prefix ) {
        if ( str_starts_with( $relative, $prefix ) ) {
            return true;
        }
    }

    // Plugin PHP is never served directly (.htaccess: ^plugins/.*\.php$).
    if ( str_starts_with( $relative, 'plugins/' ) && str_ends_with( $relative, '.php' ) ) {
        return true;
    }

    return false;
};

if ( $denied( $requestPath ) ) {
    http_response_code( 403 );
    header( 'Content-Type: text/plain; charset=utf-8' );
    echo "403 Forbidden — blocked by the playground router (mirrors installer/.htaccess).\n";
    return true;
}

// ─── Requests inside the Klytos directory ────────────────────────────────
if ( $requestPath === '/' . $adminDir || str_starts_with( $requestPath, '/' . $adminDir . '/' ) ) {
    $relative = ltrim( substr( $requestPath, strlen( $adminDir ) + 1 ), '/' );
    $target   = $klytosRoot . '/' . $relative;

    // An existing file (admin PHP page, CSS, JS, image): let the built-in
    // server handle it, including PHP execution.
    if ( $relative !== '' && is_file( $target ) ) {
        return false;
    }

    // A directory with an index.php (e.g. /installer/admin/): hand it over.
    if ( is_dir( $target ) && is_file( rtrim( $target, '/' ) . '/index.php' ) ) {
        if ( ! str_ends_with( $requestPath, '/' ) ) {
            header( 'Location: ' . $requestPath . '/', true, 301 );
            return true;
        }
        return false;
    }

    // Everything else is a route: /installer/mcp, /installer/oauth/token,
    // /installer/.well-known/..., /installer/t. Hand it to the front
    // controller the same way the .htaccess rewrites do.
    $_GET['route']            = $relative;
    $_REQUEST['route']        = $relative;
    $_SERVER['SCRIPT_NAME']   = '/' . $adminDir . '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $klytosRoot . '/index.php';

    require $klytosRoot . '/index.php';
    return true;
}

// ─── Uploaded assets, served from the WEB ROOT ───────────────────────────
//
// `AssetManager` writes into `dirname( installer/ )` — the web root — and a real
// install serves that directory directly, so `/assets/images/2026/08/x.png` is a
// working URL in production. This router mapped everything it did not recognise
// into `installer/public/`, so every uploaded thumbnail 404'd under the
// playground and nowhere else.
//
// That is exactly the disagreement the comment below refuses to accept on the
// public entry points, arriving on a different surface: a screen that renders
// correctly in production looked broken here, and entry 4's browser tier caught
// it through the read-back duty rather than through anything visible (D-119).
//
// Scoped to `/assets/` and to real files. `realpath()` confirms the resolved
// path is still inside that directory, so a `..` in the request cannot walk out
// of it.
if ( str_starts_with( $requestPath, '/assets/' ) ) {
    $assetsRoot = realpath( dirname( $klytosRoot ) . '/assets' );
    $candidate  = realpath( dirname( $klytosRoot ) . $requestPath );

    if ( $assetsRoot !== false && $candidate !== false
        && str_starts_with( $candidate, $assetsRoot . DIRECTORY_SEPARATOR )
        && is_file( $candidate )
    ) {
        $mime = match ( strtolower( (string) pathinfo( $candidate, PATHINFO_EXTENSION ) ) ) {
            'png'          => 'image/png',
            'jpg', 'jpeg'  => 'image/jpeg',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
            'svg'          => 'image/svg+xml',
            'avif'         => 'image/avif',
            'mp4'          => 'video/mp4',
            'webm'         => 'video/webm',
            'pdf'          => 'application/pdf',
            'css'          => 'text/css',
            'js'           => 'text/javascript',
            'woff2'        => 'font/woff2',
            default        => 'application/octet-stream',
        };

        header( 'Content-Type: ' . $mime );
        header( 'X-Content-Type-Options: nosniff' );
        readfile( $candidate );
        return true;
    }
}

// ─── Everything else: the generated static site ──────────────────────────
$publicPath = $klytosRoot . '/public' . ( $requestPath === '/' ? '/index.html' : $requestPath );

// Public PHP entry points are EXECUTED, not served. In production these files
// are copied to the web root and run there (public/comment-submit.php by the
// build engine, public/x402-gate.php on plugin activation); streaming them as
// bytes here would both disclose source and make the playground disagree with
// production on the one surface an anonymous visitor can reach. Returning false
// is not an option either — the built-in server would then look for the file
// under the document root, which is the repository, not installer/public.
if ( is_file( $publicPath ) && str_ends_with( $publicPath, '.php' ) ) {
    $_SERVER['SCRIPT_NAME']     = $requestPath;
    $_SERVER['SCRIPT_FILENAME'] = $publicPath;

    require $publicPath;
    return true;
}

if ( is_file( $publicPath ) ) {
    // Serve from public/ by streaming it: returning false would make the
    // built-in server look under the document root, which is the repo, not
    // installer/public.
    $types = [
        'html' => 'text/html; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
        'xml'  => 'application/xml; charset=utf-8',
        'txt'  => 'text/plain; charset=utf-8',
    ];
    $ext = strtolower( pathinfo( $publicPath, PATHINFO_EXTENSION ) );

    header( 'Content-Type: ' . ( $types[ $ext ] ?? 'application/octet-stream' ) );
    readfile( $publicPath );
    return true;
}

http_response_code( 404 );
header( 'Content-Type: text/plain; charset=utf-8' );
echo <<<TXT
404 — not found.

The generated static site is NOT served by the playground. Do not run
`php installer/cli.php build` here: BuildEngine writes to dirname(rootPath),
which in a checkout is the repository root — it overwrites the tracked
.htaccess and scatters generated directories over the repo (audit NEW-04).

Available surfaces:
  Admin panel:  http://{$_SERVER['HTTP_HOST']}/{$adminDir}/admin/
  MCP endpoint: http://{$_SERVER['HTTP_HOST']}/{$adminDir}/mcp

See docs/playground.md.

TXT;
return true;
