<?php

/**
 * Klytos CMS — playground seeder (development only).
 *
 * Creates a complete, disposable Klytos installation IN PLACE by writing the
 * two files App::isInstalled() checks for, then seeding roles, an application
 * password for MCP and synthetic content.
 *
 * WHY THIS EXISTS INSTEAD OF THE WEB INSTALLER: installer/install.php is
 * destructive to a checkout. It renames the tracked install.php to
 * .install.done.php (install.php:750), renames the whole installer/ directory
 * to <hex>-admin and copies files into the REPOSITORY'S PARENT
 * (install.php:811-824). Never run the wizard in a working copy — run this.
 *
 * Usage:
 *   php scripts/dev/seed-playground.php            # seed (refuses to overwrite)
 *   php scripts/dev/seed-playground.php --reset    # wipe runtime state, reseed
 *
 * Credentials are throwaway and local-only. Everything this writes is
 * gitignored; see docs/playground.md.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

use Klytos\Core\App;
use Klytos\Core\Encryption;
use Klytos\Core\FileStorage;
use Klytos\Core\Helpers;

if ( PHP_SAPI !== 'cli' ) {
    http_response_code( 403 );
    exit( "This script is CLI-only.\n" );
}

$rootPath   = dirname( __DIR__, 2 ) . '/installer';
$configPath = $rootPath . '/config';
$dataPath   = $rootPath . '/data';

if ( ! is_dir( $rootPath ) ) {
    fwrite( STDERR, "Cannot find installer/ at {$rootPath}. Run this from the repository.\n" );
    exit( 1 );
}

$reset = in_array( '--reset', $argv, true );

/**
 * Delete a directory's contents, preserving .htaccess guards.
 *
 * The .htaccess files in config/ and data/ are TRACKED and are the production
 * access control for those directories — removing them would be a real
 * security regression, not a cleanup.
 */
$purgeDir = static function ( string $dir ): void {
    if ( ! is_dir( $dir ) ) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ( $items as $item ) {
        if ( $item->getFilename() === '.htaccess' ) {
            continue;
        }

        if ( $item->isDir() ) {
            @rmdir( $item->getPathname() );
        } else {
            @unlink( $item->getPathname() );
        }
    }
};

/**
 * Remove build-engine output, leaving tracked static assets in place.
 */
$purgeGenerated = static function ( string $publicDir ): void {
    foreach ( [ 'index.html', 'sitemap.xml', 'robots.txt', 'llms.txt', 'llms-full.txt' ] as $file ) {
        @unlink( $publicDir . '/' . $file );
    }
};

// ─── Guard: never silently destroy an existing installation ──────────────
$keyFile    = $configPath . '/.encryption_key';
$configFile = $configPath . '/config.json.enc';

if ( ( file_exists( $keyFile ) || file_exists( $configFile ) ) && ! $reset ) {
    fwrite( STDERR, <<<TXT
    An installation already exists in installer/config.

    This script will NOT overwrite it, because it may be a real local install
    whose encrypted data cannot be recovered once the encryption key is gone.

    To wipe it and reseed a fresh playground:
        php scripts/dev/seed-playground.php --reset

    TXT );
    exit( 1 );
}

if ( $reset ) {
    echo "Resetting playground state…\n";
    $purgeDir( $configPath );
    $purgeDir( $dataPath );
    $purgeGenerated( $rootPath . '/public' );
}

// ─── Autoload core, in the ordering proven by install.php:55-72 ──────────
require_once $rootPath . '/core/app.php';
require_once $rootPath . '/core/encryption.php';
require_once $rootPath . '/core/storage.php';
require_once $rootPath . '/core/storage-interface.php';
require_once $rootPath . '/core/encryption-level-trait.php';
require_once $rootPath . '/core/file-storage.php';
require_once $rootPath . '/core/helpers-time.php';
require_once $rootPath . '/core/helpers.php';
require_once $rootPath . '/core/helpers-security.php';
require_once $rootPath . '/core/i18n.php';
require_once $rootPath . '/core/auth.php';
require_once $rootPath . '/core/hooks.php';
require_once $rootPath . '/core/helpers-global.php';
require_once $rootPath . '/core/user-manager.php';

// ─── Playground identity — throwaway, documented, never production ───────
// Passwords must be >= 12 chars (UserManager::MIN_PASSWORD_LENGTH).
const SEED_USERS = [
    'owner'  => [ 'pass' => 'playground-owner-2026',  'email' => 'owner@playground.test' ],
    'admin'  => [ 'pass' => 'playground-admin-2026',  'email' => 'admin@playground.test' ],
    'editor' => [ 'pass' => 'playground-editor-2026', 'email' => 'editor@playground.test' ],
    'viewer' => [ 'pass' => 'playground-viewer-2026', 'email' => 'viewer@playground.test' ],
];

echo "Seeding Klytos playground…\n";

Helpers::ensureWritableDir( $configPath );
Helpers::ensureWritableDir( $dataPath );

// ─── Encryption key + admin identity key pair ────────────────────────────
Encryption::generateKey( $keyFile );
$enc = new Encryption( $keyFile );

$rsaKeys     = Encryption::generateRsaKeyPair();
$fingerprint = $rsaKeys['fingerprint'];

file_put_contents(
    $configPath . '/admin-identity.pub.enc',
    $enc->encrypt( [
        'public_key'  => $rsaKeys['public_key'],
        'fingerprint' => $fingerprint,
        'created_at'  => date( 'c' ),
        'admin_user'  => 'owner',
    ] ),
    LOCK_EX
);
file_put_contents(
    $configPath . '/admin-identity.priv.enc',
    $enc->encrypt( [
        'private_key' => $rsaKeys['private_key'],
        'fingerprint' => $fingerprint,
        'created_at'  => date( 'c' ),
        'admin_user'  => 'owner',
    ] ),
    LOCK_EX
);

// ─── Configuration ───────────────────────────────────────────────────────
// install_base + admin_dir drive Helpers::getBasePath(): '/' + 'installer'
// resolves to /installer/, so the repo root is the document root and the
// admin lives at /installer/admin/ — mirroring a production subdirectory
// install rather than inventing a playground-only URL shape.
$storage = new FileStorage( $enc, $dataPath );

$config = [
    'site_name'                   => 'Klytos Playground',
    'admin_language'              => 'en',
    'admin_user'                  => 'owner',
    'admin_pass_hash'             => password_hash( SEED_USERS['owner']['pass'], PASSWORD_BCRYPT, [ 'cost' => 12 ] ),
    'admin_email'                 => SEED_USERS['owner']['email'],
    'mcp_secret'                  => Helpers::randomHex( 64 ),
    'storage_driver'              => 'file',
    'admin_dir'                   => 'installer',
    'install_base'                => '/',
    'installed_at'                => Helpers::now(),
    'version'                     => KLYTOS_VERSION,
    'update_channel'              => 'stable',
    'timezone'                    => 'Europe/Madrid',
    'design_preference'           => 'dark',
    // true so the admin is usable immediately: with false, bootstrap.php:256-267
    // redirects every request to the setup wizard.
    'setup_completed'             => true,
    // 'professional' keeps seeded records written as *.json.enc. .gitignore
    // covers the plain *.json form too, but encrypting by default means a
    // mis-set level can never turn playground data into readable tracked files.
    'encryption_level'            => 'professional',
    'identity_fingerprint'        => $fingerprint,
    'recovery_keys_confirmed'     => true,
    'recovery_keys_confirmed_at'  => Helpers::now(),
    'identity_last_downloaded_at' => null,
    'identity_download_count'     => 0,
];

$storage->writeTo( $configPath, 'config.json.enc', $config );
echo "  config written\n";

// ─── Boot the real application and seed through its own managers ─────────
// Using the managers rather than hand-writing records keeps the seeder honest:
// if a manager's contract changes, the seeder breaks instead of writing data
// the application would never have produced.
$app = App::getInstance();
$app->boot();

$storage = $app->getStorage();

$storage->write( 'config', 'site', [
    'site_name'        => 'Klytos Playground',
    'tagline'          => 'Disposable verification environment',
    'default_language' => 'en',
    'description'      => 'Synthetic content for Keel test points. Not a real site.',
    'favicon_url'      => '',
    'logo_url'         => '',
    'indexing_enabled' => false,
    'editor'           => 'gutenberg',
    'admin_theme'      => 'dark',
    'social'           => [],
    'analytics'        => [],
    'seo'              => [],
    // Developer mode is the product's debug-log switch: Logger::write()
    // (core/logger.php:116) drops every entry unless it is on. Keel requires
    // the log ON through Phase 5 and OFF at release, so the playground turns
    // it on and docs/playground.md documents how to read and flip it.
    'developer'        => [ 'developer_mode' => true ],
    'created_at'       => Helpers::now(),
    'updated_at'       => Helpers::now(),
] );
$storage->write( 'config', 'menus', [ 'items' => [] ] );
$storage->write( 'config', 'templates', [ 'templates' => [] ] );

// ─── One user per role — this is what makes authorization testable ───────
$users = $app->getUserManager();
foreach ( SEED_USERS as $role => $creds ) {
    if ( $users->getByUsername( $role ) !== null ) {
        echo "  user {$role} already exists, skipping\n";
        continue;
    }

    $users->create( [
        'username'     => $role,
        'password'     => $creds['pass'],
        'email'        => $creds['email'],
        'role'         => $role,
        'display_name' => ucfirst( $role ) . ' (playground)',
    ] );
    echo "  user {$role} created\n";
}

// ─── Application password for MCP (HTTP Basic) ───────────────────────────
$appPassword = $app->getAuth()->createAppPassword( 'Playground MCP access', 'owner' )['password'];
echo "  MCP application password created\n";

// ─── Synthetic content ───────────────────────────────────────────────────
$pages = $app->getPages();
// Gutenberg block delimiters are mandatory: the visual editor cannot parse
// content without them (see the klytos-gutenberg-blocks skill).
$block = static fn( string $text ): string =>
    '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->';

$seedPages = [
    [ 'slug' => 'home',    'title' => 'Playground home', 'content' => $block( 'Synthetic home page for Keel test points.' ) ],
    [ 'slug' => 'about',   'title' => 'About',           'content' => $block( 'Synthetic about page.' ) ],
    [ 'slug' => 'contact', 'title' => 'Contact',         'content' => $block( 'Synthetic contact page.' ) ],
];

foreach ( $seedPages as $page ) {
    if ( $storage->exists( 'pages', $page['slug'] ) ) {
        echo "  page {$page['slug']} already exists, skipping\n";
        continue;
    }

    $pages->create( $page + [ 'status' => 'published' ] );
    echo "  page {$page['slug']} created\n";
}

// ─── Access details, for docs/playground.md and the user ─────────────────
// Written to a gitignored file because the application password is generated
// and cannot be documented statically.
$userLines = '';
foreach ( SEED_USERS as $role => $creds ) {
    $userLines .= sprintf( "  %-7s / %s\n", $role, $creds['pass'] );
}

$access = <<<TXT
Klytos playground access — generated {$config['installed_at']}
THROWAWAY LOCAL CREDENTIALS. Never reuse these anywhere real.

Admin:  http://127.0.0.1:8080/installer/admin/
MCP:    http://127.0.0.1:8080/installer/mcp

Users (username / password):
{$userLines}
MCP application password (HTTP Basic, user 'owner'):
  {$appPassword}

TXT;

file_put_contents( $configPath . '/.playground-access', $access, LOCK_EX );
chmod( $configPath . '/.playground-access', 0600 );

echo <<<TXT

Playground ready.

  Start it:  php -S 127.0.0.1:8080 -t . scripts/dev/router.php
  Admin:     http://127.0.0.1:8080/installer/admin/
  MCP:       http://127.0.0.1:8080/installer/mcp

  Log in as any of: owner / admin / editor / viewer
  Passwords are in docs/playground.md (throwaway, local only).

  MCP application password (user 'owner'):
    {$appPassword}

  Also saved to installer/config/.playground-access (gitignored).
  Full instructions: docs/playground.md

TXT;
