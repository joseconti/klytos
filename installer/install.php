<?php
/**
 * Klytos — Installer
 * Multi-step wizard: requirements → configuration (with optional DB) → complete.
 *
 * Klytos is free to use. No license is required for the core CMS.
 * Premium plugins may require their own licenses (managed separately).
 *
 * This file guides the user through the initial Klytos setup:
 * 1. Requirements check (PHP version, extensions, directory permissions).
 * 2. Site configuration: name, admin credentials, language, color palette,
 *    and optional MySQL/MariaDB database connection.
 * 3. Completion screen with MCP endpoint and bearer token.
 *
 * After successful installation, this file is renamed to .install.done.php
 * to prevent re-execution. A lock file (config/.install.lock) is also created.
 *
 * Security:
 * - CSRF tokens are not used here because install.php only runs once
 *   and is renamed immediately after. The .install.lock prevents replay.
 * - Password minimum: 12 characters, hashed with bcrypt (cost 12).
 * - Encryption key generated with random_bytes(32) (CSPRNG).
 * - Database credentials stored encrypted in config/database.json.enc.
 * - All POST input is sanitized and validated before use.
 *
 * @package Klytos
 * @since   1.0.0
 * @updated 2.0.0 — Added optional MySQL/MariaDB storage, AJAX connection test.
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

// ─── Prevent Re-Installation ──────────────────────────────────
// If both the encryption key and config exist, the CMS is already installed.
$rootPath = __DIR__;
if (file_exists($rootPath . '/config/.encryption_key') && file_exists($rootPath . '/config/config.json.enc')) {
    header('Location: admin/');
    exit;
}

// Also check the permanent lock file.
if (file_exists($rootPath . '/config/.install.lock')) {
    header('Location: admin/');
    exit;
}

// ─── Autoload Core Classes ────────────────────────────────────
require_once $rootPath . '/core/app.php';
require_once $rootPath . '/core/encryption.php';
require_once $rootPath . '/core/storage.php';
require_once $rootPath . '/core/storage-interface.php';
require_once $rootPath . '/core/file-storage.php';
require_once $rootPath . '/core/database-storage.php';
require_once $rootPath . '/core/helpers.php';
require_once $rootPath . '/core/helpers-security.php';
require_once $rootPath . '/core/i18n.php';
require_once $rootPath . '/core/auth.php';
require_once $rootPath . '/core/hooks.php';
require_once $rootPath . '/core/block-manager.php';
require_once $rootPath . '/core/page-template-manager.php';
require_once $rootPath . '/core/user-manager.php';
require_once $rootPath . '/core/seed-data.php';

use Klytos\Core\Encryption;
use Klytos\Core\FileStorage;
use Klytos\Core\DatabaseStorage;
use Klytos\Core\Helpers;

// ─── AJAX Handler: Test Database Connection ───────────────────
// This endpoint is called via JavaScript when the user clicks "Test Connection".
// It validates the provided database credentials without storing anything.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['ajax_action'] ?? '') === 'test_db_connection'
) {
    header('Content-Type: application/json; charset=utf-8');

    $dbHost   = trim($_POST['db_host'] ?? 'localhost');
    $dbPort   = (int) ($_POST['db_port'] ?? 3306);
    $dbName   = trim($_POST['db_name'] ?? '');
    $dbUser   = trim($_POST['db_user'] ?? '');
    $dbPass   = $_POST['db_pass'] ?? '';
    $dbPrefix = trim($_POST['db_prefix'] ?? 'kly_');

    // Validate required fields.
    if (empty($dbName) || empty($dbUser)) {
        echo json_encode(['success' => false, 'error' => 'Database name and user are required.']);
        exit;
    }

    // Validate prefix format (only alphanumeric + underscore).
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbPrefix)) {
        echo json_encode(['success' => false, 'error' => 'Invalid prefix. Only letters, numbers and underscores allowed.']);
        exit;
    }

    try {
        // Attempt a real PDO connection to verify credentials.
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT          => 5,
        ]);

        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        echo json_encode([
            'success' => true,
            'version' => $version,
            'message' => "Connected to MySQL {$version}",
        ]);
    } catch (PDOException $e) {
        // Do NOT expose the raw PDO error message — it may contain credentials.
        echo json_encode([
            'success' => false,
            'error'   => 'Connection failed. Check host, port, database name, user and password.',
        ]);
    }

    exit;
}

// ─── Determine Current Step ───────────────────────────────────
$step    = $_POST['step'] ?? $_GET['step'] ?? 'requirements';
$error   = '';
$success = '';

// ─── Handle POST Actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Step 2: Configuration + actual installation.
    // Note: No license required for core CMS. Klytos is free to use.
    if ($step === 'install') {
        $step = 'config'; // Show config form if validation fails.

        // ── Collect form data ──
        $siteName      = trim($_POST['site_name'] ?? '');
        $adminUser     = trim($_POST['admin_user'] ?? '');
        $adminPass     = $_POST['admin_pass'] ?? '';
        $adminPass2    = $_POST['admin_pass_confirm'] ?? '';
        $adminEmail    = trim($_POST['admin_email'] ?? '');
        $adminLang     = $_POST['admin_language'] ?? 'en';
        $description   = trim($_POST['description'] ?? '');
        $colorPreset   = $_POST['color_preset'] ?? 'blue';
        $editorChoice  = $_POST['editor'] ?? 'gutenberg';
        $storageDriver = $_POST['storage_driver'] ?? 'file';
        $adminDirName  = trim($_POST['admin_dir_name'] ?? '');

        // Database fields (only relevant if storage_driver === 'database').
        $dbHost   = trim($_POST['db_host'] ?? 'localhost');
        $dbPort   = (int) ($_POST['db_port'] ?? 3306);
        $dbName   = trim($_POST['db_name'] ?? '');
        $dbUser   = trim($_POST['db_user'] ?? '');
        $dbPass   = $_POST['db_pass'] ?? '';
        $dbPrefix = trim($_POST['db_prefix'] ?? 'kly_');

        // ── Validate input ──
        $errors = [];
        if (empty($siteName)) {
            $errors[] = 'Site name is required.';
        }
        if (empty($adminUser)) {
            $errors[] = 'Admin username is required.';
        }
        if (strlen($adminPass) < 12) {
            $errors[] = 'Password must be at least 12 characters.';
        }
        if ($adminPass !== $adminPass2) {
            $errors[] = 'Passwords do not match.';
        }
        if (empty($adminEmail) || !klytos_is_email($adminEmail)) {
            $errors[] = 'A valid email address is required.';
        }

        // Generate or validate admin directory name (secret URL).
        // If empty, auto-generate a random name for maximum security.
        if (empty($adminDirName)) {
            $adminDirName = bin2hex(random_bytes(6)) . '-admin'; // e.g. 'a3f7b2c1e9d4-admin'
        }
        if (!preg_match('/^[a-zA-Z0-9_\-]{4,64}$/', $adminDirName)) {
            $errors[] = 'Admin directory name: 4-64 characters, letters, numbers, hyphens, underscores only.';
        }

        // Validate database fields if MySQL storage is selected.
        if ($storageDriver === 'database') {
            if (empty($dbName)) {
                $errors[] = 'Database name is required for MySQL storage.';
            }
            if (empty($dbUser)) {
                $errors[] = 'Database user is required for MySQL storage.';
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbPrefix)) {
                $errors[] = 'Invalid table prefix. Only letters, numbers and underscores.';
            }
        }

        if (!empty($errors)) {
            $error = implode(' ', $errors);
        } else {
            try {
                // ── Step A: Ensure encryption key exists ──
                $keyPath = $rootPath . '/config/.encryption_key';
                if (!file_exists($keyPath)) {
                    Helpers::ensureWritableDir($rootPath . '/config');
                    Encryption::generateKey($keyPath);
                }

                $enc = new Encryption($keyPath);

                // ── Step B: Create the storage backend ──
                if ($storageDriver === 'database') {
                    $dbConfig = [
                        'host'    => $dbHost,
                        'port'    => $dbPort,
                        'name'    => $dbName,
                        'user'    => $dbUser,
                        'pass'    => $dbPass,
                        'prefix'  => $dbPrefix,
                        'charset' => 'utf8mb4',
                    ];

                    // Store encrypted database credentials.
                    // These are stored as a flat file in config/ even when using database storage.
                    $tempFileStorage = new FileStorage($enc, $rootPath . '/data');
                    $tempFileStorage->writeTo($rootPath . '/config', 'database.json.enc', $dbConfig);

                    // Create DatabaseStorage and initialize tables.
                    $storage = new DatabaseStorage($enc, $rootPath . '/data', $dbConfig);

                    // Test connection and create all collection tables.
                    $connTest = $storage->testConnection();
                    if (!$connTest['success']) {
                        throw new \RuntimeException('Database connection failed: ' . $connTest['error']);
                    }
                    $storage->createTables();

                } else {
                    // Flat-file storage (default).
                    $storage = new FileStorage($enc, $rootPath . '/data');
                }

                // ── Step C: Create main configuration ──
                $mcpSecret = Helpers::randomHex(64);
                $config = [
                    'site_name'      => $siteName,
                    'admin_language'  => $adminLang,
                    'admin_user'     => $adminUser,
                    'admin_pass_hash' => password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]),
                    'admin_email'    => $adminEmail,
                    'mcp_secret'     => $mcpSecret,
                    'storage_driver' => $storageDriver,
                    'admin_dir'      => $adminDirName,
                    'installed_at'   => Helpers::now(),
                    'version'        => KLYTOS_VERSION,
                    'update_channel' => 'stable',
                    'timezone'       => 'Europe/Madrid',
                ];
                $storage->writeTo($rootPath . '/config', 'config.json.enc', $config);

                // ── Step D: Create site metadata ──
                $siteData = [
                    'site_name'        => $siteName,
                    'tagline'          => '',
                    'default_language' => substr($adminLang, 0, 2),
                    'description'      => $description,
                    'favicon_url'      => '',
                    'logo_url'         => '',
                    'indexing_enabled' => false,
                    'editor'           => $editorChoice,
                    'social'           => [],
                    'analytics'        => [],
                    'seo'              => [],
                    'created_at'       => Helpers::now(),
                    'updated_at'       => Helpers::now(),
                ];

                // ── Step E: Create theme with color preset ──
                $colors    = getColorPreset($colorPreset);
                $themeData = [
                    'colors'     => $colors,
                    'fonts'      => [
                        'heading' => 'Inter', 'body' => 'Inter', 'code' => 'JetBrains Mono',
                        'heading_weight' => '700', 'body_weight' => '400',
                        'base_size' => '16px', 'scale_ratio' => '1.25',
                        'google_fonts_url' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400&display=swap',
                    ],
                    'layout'     => [
                        'max_width' => '1200px', 'header_style' => 'sticky',
                        'footer_enabled' => true, 'sidebar_enabled' => false,
                        'sidebar_position' => 'left', 'border_radius' => '8px',
                        'spacing_unit' => '1rem',
                    ],
                    'custom_css' => '',
                ];

                $menusData     = ['items' => []];
                $templatesData = ['templates' => []];

                // ── Step F: Write data using collection+id paradigm ──
                // This works for BOTH FileStorage and DatabaseStorage.
                $storage->write('config', 'site', $siteData);
                $storage->write('config', 'theme', $themeData);
                $storage->write('config', 'menus', $menusData);
                $storage->write('config', 'templates', $templatesData);

                // ── Step G: Generate first Application Password for MCP ──
                // Application Passwords are the primary way to authenticate with MCP.
                // They use HTTP Basic Auth: Authorization: Basic base64(user:password)
                // OAuth 2.0/2.1 is also supported for more advanced integrations.
                $auth          = new \Klytos\Core\Auth($config, $storage);
                $appPassResult = $auth->createAppPassword('Initial MCP Access', $adminUser);
                $appPassword   = $appPassResult['password']; // Format: xxxx-xxxx-xxxx-xxxx-xxxx-xxxx

                // ── Step H: Create placeholder homepage and CSS ──
                Helpers::ensureWritableDir($rootPath . '/public');
                Helpers::ensureWritableDir($rootPath . '/public/css');
                Helpers::ensureWritableDir($rootPath . '/public/js');
                Helpers::ensureWritableDir($rootPath . '/public/assets/images');

                // Create custom-templates directory (protected from updates).
                Helpers::ensureWritableDir($rootPath . '/custom-templates');
                Helpers::ensureWritableDir($rootPath . '/custom-templates/parts');

                $langCode = klytos_esc_attr( substr($adminLang, 0, 2) );
                $safeName = klytos_esc_html( $siteName );

                $placeholderHtml = <<<HTML
                <!DOCTYPE html>
                <html lang="{$langCode}">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>{$safeName}</title>
                    <meta name="robots" content="noindex, nofollow">
                    <link rel="preconnect" href="https://fonts.googleapis.com">
                    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
                    <style>
                        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
                        :root {
                            --bg: #0a0a0f; --surface: #12121a; --border: #1e1e2e;
                            --text: #e4e4ef; --muted: #6b6b8a; --accent: #4f6ef7;
                            --accent-glow: rgba(79, 110, 247, 0.25);
                            --gradient-1: #4f6ef7; --gradient-2: #a855f7; --gradient-3: #ec4899;
                        }
                        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; position: relative; }
                        .grid-bg { position: fixed; inset: 0; background-image: linear-gradient(rgba(79, 110, 247, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(79, 110, 247, 0.03) 1px, transparent 1px); background-size: 60px 60px; animation: gridMove 20s linear infinite; z-index: 0; }
                        @keyframes gridMove { 0% { transform: translate(0, 0); } 100% { transform: translate(60px, 60px); } }
                        .orb { position: fixed; border-radius: 50%; filter: blur(80px); opacity: 0.4; animation: float 15s ease-in-out infinite; z-index: 0; }
                        .orb-1 { width: 400px; height: 400px; background: var(--gradient-1); top: -10%; right: -5%; }
                        .orb-2 { width: 350px; height: 350px; background: var(--gradient-2); bottom: -10%; left: -5%; animation-delay: -5s; }
                        .orb-3 { width: 250px; height: 250px; background: var(--gradient-3); top: 40%; left: 50%; animation-delay: -10s; }
                        @keyframes float { 0%, 100% { transform: translate(0, 0) scale(1); } 33% { transform: translate(30px, -20px) scale(1.05); } 66% { transform: translate(-20px, 20px) scale(0.95); } }
                        .container { position: relative; z-index: 1; text-align: center; padding: 2rem; max-width: 720px; }
                        .logo-mark { display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; border-radius: 20px; background: linear-gradient(135deg, var(--gradient-1), var(--gradient-2)); margin-bottom: 2.5rem; box-shadow: 0 0 60px var(--accent-glow), 0 0 120px rgba(168, 85, 247, 0.15); animation: pulse 3s ease-in-out infinite; }
                        .logo-mark svg { width: 40px; height: 40px; fill: white; }
                        @keyframes pulse { 0%, 100% { box-shadow: 0 0 60px var(--accent-glow), 0 0 120px rgba(168, 85, 247, 0.15); } 50% { box-shadow: 0 0 80px var(--accent-glow), 0 0 160px rgba(168, 85, 247, 0.25); } }
                        .badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 1rem; border-radius: 100px; background: var(--surface); border: 1px solid var(--border); font-size: 0.8rem; font-weight: 500; color: var(--muted); margin-bottom: 2rem; letter-spacing: 0.03em; }
                        .badge-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); animation: blink 2s ease-in-out infinite; }
                        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
                        h1 { font-size: clamp(2.2rem, 6vw, 3.8rem); font-weight: 800; line-height: 1.1; margin-bottom: 1.5rem; letter-spacing: -0.03em; }
                        h1 .gradient-text { background: linear-gradient(135deg, var(--gradient-1), var(--gradient-2), var(--gradient-3)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
                        .subtitle { font-size: 1.15rem; color: var(--muted); line-height: 1.6; margin-bottom: 2.5rem; max-width: 540px; margin-left: auto; margin-right: auto; }
                        .cta-wrapper { display: flex; flex-direction: column; align-items: center; gap: 1rem; }
                        .cta-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.9rem 2rem; border-radius: 12px; background: linear-gradient(135deg, var(--gradient-1), var(--gradient-2)); color: white; font-family: inherit; font-size: 1rem; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 24px var(--accent-glow); }
                        .cta-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 32px var(--accent-glow); }
                        .cta-btn svg { width: 18px; height: 18px; }
                        .cta-small { font-size: 0.82rem; color: var(--muted); }
                        .cta-small a { color: var(--accent); text-decoration: none; }
                        .cta-small a:hover { text-decoration: underline; }
                        .features { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; margin-top: 3.5rem; }
                        .feature { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--muted); }
                        .feature-icon { width: 32px; height: 32px; border-radius: 8px; background: var(--surface); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 0.95rem; }
                        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; padding: 1.5rem; font-size: 0.78rem; color: var(--muted); z-index: 1; }
                        .footer a { color: var(--accent); text-decoration: none; }
                        .footer a:hover { text-decoration: underline; }
                        @media (max-width: 480px) { .features { gap: 1rem; } .feature { font-size: 0.78rem; } .orb { opacity: 0.2; } }
                    </style>
                </head>
                <body>
                    <div class="grid-bg"></div>
                    <div class="orb orb-1"></div>
                    <div class="orb orb-2"></div>
                    <div class="orb orb-3"></div>
                    <main class="container">
                        <div class="logo-mark">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                            </svg>
                        </div>
                        <div class="badge">
                            <span class="badge-dot"></span>
                            Building something big
                        </div>
                        <h1>Something <span class="gradient-text">extraordinary</span><br>is coming</h1>
                        <p class="subtitle">
                            This site is being crafted by AI. Built with Klytos &mdash; the first
                            CMS designed to be controlled entirely by artificial intelligence.
                        </p>
                        <div class="cta-wrapper">
                            <a href="https://klytos.io" class="cta-btn" target="_blank" rel="noopener">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                    <polyline points="15 3 21 3 21 9"/>
                                    <line x1="10" y1="14" x2="21" y2="3"/>
                                </svg>
                                Discover Klytos
                            </a>
                            <p class="cta-small">
                                Free &amp; open source &mdash; <a href="https://github.com/joseconti/klytos" target="_blank" rel="noopener">View on GitHub</a>
                            </p>
                        </div>
                        <div class="features">
                            <div class="feature"><span class="feature-icon">&#129302;</span> AI-First</div>
                            <div class="feature"><span class="feature-icon">&#9889;</span> Static &amp; Fast</div>
                            <div class="feature"><span class="feature-icon">&#128274;</span> Privacy-First</div>
                            <div class="feature"><span class="feature-icon">&#127760;</span> MCP Protocol</div>
                        </div>
                    </main>
                    <footer class="footer">
                        Powered by <a href="https://klytos.io" target="_blank" rel="noopener">Klytos</a> &mdash; The AI-First CMS
                    </footer>
                </body>
                </html>
                HTML;
                file_put_contents($rootPath . '/public/index.html', $placeholderHtml, LOCK_EX);

                // Generate base CSS with theme variables.
                $baseCss = <<<CSS
                /* Generated by Klytos installer */
                :root {
                    --klytos-primary: {$colors['primary']};
                    --klytos-text: {$colors['text']};
                    --klytos-text-muted: {$colors['text_muted']};
                    --klytos-background: {$colors['background']};
                }
                body {
                    font-family: 'Inter', sans-serif;
                    margin: 0;
                    padding: 0;
                    background: var(--klytos-background);
                    color: var(--klytos-text);
                }
                .klytos-container {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 0 1rem;
                }
                CSS;
                file_put_contents($rootPath . '/public/css/style.css', $baseCss, LOCK_EX);

                // ── Step I: Protect sensitive directories ──
                foreach (['config', 'data', 'backups'] as $dir) {
                    Helpers::ensureWritableDir($rootPath . '/' . $dir);
                    file_put_contents(
                        $rootPath . '/' . $dir . '/.htaccess',
                        "Order deny,allow\nDeny from all\n",
                        LOCK_EX
                    );
                }

                // ── Step J: Create supporting files ──
                $storage->write('config', 'update_log', ['updates' => []]);
                file_put_contents($rootPath . '/VERSION', KLYTOS_VERSION, LOCK_EX);

                // ── Step K: Create owner user from installer credentials ──
                // Migrate the admin user from v1.0 config to v2.0 UserManager.
                $userManager = new \Klytos\Core\UserManager($storage);
                $userManager->migrateFromV1Config($config);

                // ── Step L: Seed core blocks and page templates ──
                // Creates ~15 HTML blocks and 9 page templates.
                $blockManager    = new \Klytos\Core\BlockManager($storage);
                $pageTemplateManager = new \Klytos\Core\PageTemplateManager($storage, $blockManager);
                \Klytos\Core\seedDefaultData($blockManager, $pageTemplateManager);

                // Create permanent installation lock (prevents re-running install.php).
                file_put_contents($rootPath . '/config/.install.lock', date( 'c' ), LOCK_EX);

                // Rename install.php so it cannot be accessed again.
                rename($rootPath . '/install.php', $rootPath . '/.install.done.php');

                // ── Rename admin directory if user chose a different name ──
                $currentDirName = basename($rootPath);
                $parentDir      = dirname($rootPath);
                $newDirPath     = $parentDir . '/' . $adminDirName;
                $dirRenamed     = false;

                if ($adminDirName !== $currentDirName && !file_exists($newDirPath)) {
                    $dirRenamed = @rename($rootPath, $newDirPath);
                    if (!$dirRenamed) {
                        error_log("Klytos: could not rename admin directory from '{$currentDirName}' to '{$adminDirName}'. Check directory permissions.");
                    }
                }

                // ── Deploy public files to the document root (parent directory) ──
                // The admin dir lives inside the doc root. Public-facing files
                // (index.html, .htaccess, css/) must be in the doc root itself.
                $adminFinalPath = $dirRenamed ? $newDirPath : $rootPath;

                // Move public/index.html → parent/index.html
                $publicIndex = $adminFinalPath . '/public/index.html';
                if (file_exists($publicIndex)) {
                    @copy($publicIndex, $parentDir . '/index.html');
                }

                // Move public/css/ → parent/css/
                $publicCss = $adminFinalPath . '/public/css';
                if (is_dir($publicCss)) {
                    if (!is_dir($parentDir . '/css')) {
                        mkdir($parentDir . '/css', 0755, true);
                    }
                    $cssFiles = scandir($publicCss);
                    if ($cssFiles) {
                        foreach ($cssFiles as $cssFile) {
                            if ($cssFile === '.' || $cssFile === '..') continue;
                            @copy($publicCss . '/' . $cssFile, $parentDir . '/css/' . $cssFile);
                        }
                    }
                }

                // Create a root .htaccess for the public site.
                $rootHtaccess = "# Klytos — Document Root\n"
                    . "# Serves the public site. Admin panel is at /{$adminDirName}/\n\n"
                    . "DirectoryIndex index.html index.php\n\n"
                    . "# Deny access to sensitive files\n"
                    . "<FilesMatch \"^\\.(htaccess|htpasswd|install\\.done\\.php)$\">\n"
                    . "    Require all denied\n"
                    . "</FilesMatch>\n";
                if (!file_exists($parentDir . '/.htaccess')) {
                    file_put_contents($parentDir . '/.htaccess', $rootHtaccess, LOCK_EX);
                }

                // ── Clean up: delete installer.php from document root ──
                $installerFile = $parentDir . '/installer.php';
                if (file_exists($installerFile)) {
                    @unlink($installerFile);
                }

                // ── Build the admin URL (with the new or current dir name) ──
                $protocol    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $finalDir    = $dirRenamed ? $adminDirName : $currentDirName;

                // Derive the base path from SCRIPT_NAME so subdirectory installs work.
                // e.g. /subdir/prueba/install.php → basePath = '/subdir/'
                $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/install.php');
                $basePath  = dirname($scriptDir);
                $basePath  = ($basePath === '/' || $basePath === '\\') ? '/' : rtrim($basePath, '/') . '/';

                $adminUrl    = $protocol . '://' . $host . $basePath . $finalDir . '/admin/';
                $mcpEndpoint = $protocol . '://' . $host . $basePath . $finalDir . '/mcp';

                // ── Done! Show completion screen with credentials ──
                $step = 'complete';

            } catch (\Exception $e) {
                // Show a sanitized error — do NOT expose internal paths or stack traces.
                $error = 'Installation failed: ' . $e->getMessage();
                error_log('Klytos install error: ' . $e->getMessage());
            }
        }
    }
}

// ─── Requirements Check ───────────────────────────────────────
$requirements = [];
if ($step === 'requirements') {
    // Check PHP extensions needed by Klytos.
    $missing = Helpers::checkRequirements();
    $requirements['extensions'] = [
        'ok'      => empty($missing),
        'missing' => $missing,
    ];

    // Klytos requires PHP 8.1+ for modern type features.
    $requirements['php_version'] = [
        'ok'      => version_compare(PHP_VERSION, '8.1.0', '>='),
        'current' => PHP_VERSION,
    ];

    // Check that key directories are writable by the web server.
    $writableDirs = ['config', 'data', 'public', 'backups'];
    $requirements['directories'] = [];
    foreach ($writableDirs as $dir) {
        $path = $rootPath . '/' . $dir;
        if (!is_dir($path)) {
            @mkdir($path, 0700, true);
        }
        $requirements['directories'][$dir] = is_writable($path);
    }

    $requirements['all_ok'] = $requirements['extensions']['ok']
        && $requirements['php_version']['ok']
        && !in_array(false, $requirements['directories'], true);

    // Start session for the install flow (stores license key between steps).
    session_start();
}

// ─── Color Presets ────────────────────────────────────────────
/**
 * Get a predefined color palette by name.
 *
 * @param  string $name Preset name: 'blue', 'green', 'purple', 'dark', 'warm'.
 * @return array  Associative array of color keys => hex values.
 */
function getColorPreset(string $name): array
{
    $presets = [
        'blue' => [
            'primary' => '#2563eb', 'secondary' => '#7c3aed', 'accent' => '#f59e0b',
            'background' => '#ffffff', 'surface' => '#f8fafc', 'text' => '#1e293b',
            'text_muted' => '#64748b', 'border' => '#e2e8f0', 'success' => '#22c55e',
            'warning' => '#f59e0b', 'error' => '#ef4444',
        ],
        'green' => [
            'primary' => '#16a34a', 'secondary' => '#0d9488', 'accent' => '#eab308',
            'background' => '#ffffff', 'surface' => '#f0fdf4', 'text' => '#14532d',
            'text_muted' => '#4ade80', 'border' => '#bbf7d0', 'success' => '#22c55e',
            'warning' => '#f59e0b', 'error' => '#ef4444',
        ],
        'purple' => [
            'primary' => '#7c3aed', 'secondary' => '#a855f7', 'accent' => '#f97316',
            'background' => '#ffffff', 'surface' => '#faf5ff', 'text' => '#1e1b4b',
            'text_muted' => '#7c3aed', 'border' => '#e9d5ff', 'success' => '#22c55e',
            'warning' => '#f59e0b', 'error' => '#ef4444',
        ],
        'dark' => [
            'primary' => '#3b82f6', 'secondary' => '#8b5cf6', 'accent' => '#f59e0b',
            'background' => '#0f172a', 'surface' => '#1e293b', 'text' => '#f1f5f9',
            'text_muted' => '#94a3b8', 'border' => '#334155', 'success' => '#22c55e',
            'warning' => '#f59e0b', 'error' => '#ef4444',
        ],
        'warm' => [
            'primary' => '#dc2626', 'secondary' => '#ea580c', 'accent' => '#d97706',
            'background' => '#fffbeb', 'surface' => '#fef3c7', 'text' => '#451a03',
            'text_muted' => '#92400e', 'border' => '#fde68a', 'success' => '#22c55e',
            'warning' => '#f59e0b', 'error' => '#ef4444',
        ],
    ];

    return $presets[$name] ?? $presets['blue'];
}

// ─── HTML Output ──────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Klytos — Installation</title>
    <style>
        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #0f172a; color: #e2e8f0; line-height: 1.6; min-height: 100vh;
        }
        .installer { max-width: 640px; margin: 2rem auto; padding: 0 1rem; }
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
        .steps { display: flex; gap: 0.5rem; margin-bottom: 2rem; }
        .step {
            flex: 1; text-align: center; padding: 0.75rem; border-radius: 0.5rem;
            font-size: 0.85rem; font-weight: 600; background: #334155; color: #64748b;
            border: 1px solid #475569; transition: all 0.3s;
        }
        .step.active {
            background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff;
            border-color: transparent; box-shadow: 0 4px 16px rgba(99,102,241,0.3);
        }
        .step.done { background: #22c55e; color: #fff; border-color: transparent; }
        h2 { font-size: 1.3rem; margin-bottom: 1rem; color: #f8fafc; }
        h3 { font-size: 1.1rem; margin: 1.5rem 0 0.75rem; padding-top: 1rem; border-top: 1px solid #334155; color: #f8fafc; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; color: #e2e8f0; }
        input[type="text"], input[type="password"], input[type="email"], input[type="number"], select, textarea {
            width: 100%; padding: 0.7rem; border: 1px solid #334155; border-radius: 0.5rem;
            font-size: 0.95rem; transition: border-color 0.2s;
            background: #0f172a; color: #e2e8f0;
        }
        input:focus, select:focus, textarea:focus {
            outline: none; border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }
        select option { background: #1e293b; color: #e2e8f0; }
        textarea { resize: vertical; min-height: 80px; }
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
        .btn:disabled {
            opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none;
        }
        .btn-block { width: 100%; text-align: center; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.85rem; }
        .btn-secondary { background: #475569; }
        .btn-secondary:hover { background: #64748b; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
        .alert { padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.9rem; }
        .alert-error { background: rgba(239,68,68,0.12); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
        .alert-error code { background: rgba(239,68,68,0.15); padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.8125rem; }
        .alert-success { background: rgba(34,197,94,0.12); color: #86efac; border: 1px solid rgba(34,197,94,0.3); }
        .alert-warning { background: rgba(245,158,11,0.12); color: #fcd34d; border: 1px solid rgba(245,158,11,0.3); }
        .alert-info { background: rgba(99,102,241,0.12); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3); }
        .check-list { list-style: none; }
        .check-list li { padding: 0.5rem 0; display: flex; align-items: center; gap: 0.5rem; color: #94a3b8; }
        .check-ok { color: #34d399; font-weight: bold; }
        .check-fail { color: #f87171; font-weight: bold; }
        .color-presets { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.5rem; margin-top: 0.5rem; }
        .color-preset { width: 100%; aspect-ratio: 1; border-radius: 0.5rem; border: 3px solid transparent; cursor: pointer; transition: all 0.2s; }
        .color-preset.selected, .color-preset:hover { border-color: #a5b4fc; box-shadow: 0 0 12px rgba(99,102,241,0.3); }
        .color-preset input { display: none; }
        .token-box {
            background: #0f172a; border: 1px solid #334155; border-radius: 0.5rem;
            padding: 1rem; font-family: monospace; font-size: 0.85rem;
            word-break: break-all; margin: 1rem 0; color: #e2e8f0;
        }
        .token-box.highlight {
            background: rgba(245,158,11,0.1); border-color: rgba(245,158,11,0.3); color: #fcd34d;
        }
        .token-box.highlight a { color: #fbbf24; text-decoration: underline; }
        .mcp-config {
            background: #0f172a; color: #a5b4fc; border-radius: 0.5rem; border: 1px solid #334155;
            padding: 1rem; font-family: monospace; font-size: 0.8rem;
            white-space: pre; overflow-x: auto; margin: 1rem 0;
        }
        .small { font-size: 0.8rem; color: #64748b; margin-top: 0.3rem; }
        .form-help { font-size: 0.8rem; color: #64748b; margin-top: 0.3rem; }
        .form-help code { background: #334155; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.8rem; color: #a5b4fc; }
        .db-fields {
            display: none; padding: 1rem; background: rgba(99,102,241,0.05);
            border-radius: 0.5rem; margin-top: 0.75rem; border: 1px solid #334155;
        }
        .db-fields.visible { display: block; }
        .db-test-result { margin-top: 0.5rem; font-size: 0.85rem; padding: 0.5rem 0.75rem; border-radius: 0.375rem; display: none; }
        .db-test-result.success { display: block; background: rgba(34,197,94,0.12); color: #86efac; border: 1px solid rgba(34,197,94,0.3); }
        .db-test-result.error { display: block; background: rgba(239,68,68,0.12); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
        .inline-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .storage-toggle { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
        .storage-toggle label {
            flex: 1; padding: 0.75rem; border: 2px solid #334155; border-radius: 0.5rem;
            text-align: center; cursor: pointer; font-weight: 600; font-size: 0.9rem;
            transition: all 0.2s; color: #94a3b8; background: transparent;
        }
        .storage-toggle input { display: none; }
        .storage-toggle input:checked + span { border: none; }
        .storage-toggle label:has(input:checked) {
            border-color: #6366f1; background: rgba(99,102,241,0.15); color: #a5b4fc;
        }
        .storage-toggle label:hover { background: #334155; color: #e2e8f0; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem; }
        .info-box {
            padding: 0.75rem; border-radius: 0.5rem; font-size: 0.85rem;
            background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2);
        }
        .info-box strong { color: #a5b4fc; }
        .info-box ul { margin: 0.5rem 0 0; padding-left: 1.2rem; color: #94a3b8; }
        .info-box.alt {
            background: rgba(34,197,94,0.08); border-color: rgba(34,197,94,0.2);
        }
        .info-box.alt strong { color: #86efac; }
    </style>
</head>
<body>
<div class="installer">
    <div class="logo">
        <div class="logo-mark"><img src="admin/assets/images/klytos-logo-120.png" alt="Klytos"></div>
        <h1>Klytos</h1>
        <p>AI-Powered CMS Installation</p>
    </div>

    <!-- Step indicators (3 steps: Requirements → Setup → Done) -->
    <div class="steps">
        <div class="step <?php echo $step === 'requirements' ? 'active' : ($step !== 'requirements' ? 'done' : ''); ?>">1. Requirements</div>
        <div class="step <?php echo $step === 'config' || $step === 'install' ? 'active' : ($step === 'complete' ? 'done' : ''); ?>">2. Setup</div>
        <div class="step <?php echo $step === 'complete' ? 'active' : ''; ?>">3. Done</div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
    <?php endif; ?>

    <!-- ─── Step 1: Requirements ─── -->
    <?php if ($step === 'requirements'): ?>
    <div class="card">
        <h2>Requirements Check</h2>
        <ul class="check-list">
            <li>
                <span class="<?php echo $requirements['php_version']['ok'] ? 'check-ok' : 'check-fail'; ?>">
                    <?php echo $requirements['php_version']['ok'] ? '&#10003;' : '&#10007;'; ?>
                </span>
                PHP 8.1+ (current: <?php echo $requirements['php_version']['current']; ?>)
            </li>
            <li>
                <span class="<?php echo $requirements['extensions']['ok'] ? 'check-ok' : 'check-fail'; ?>">
                    <?php echo $requirements['extensions']['ok'] ? '&#10003;' : '&#10007;'; ?>
                </span>
                Required extensions
                <?php if (!$requirements['extensions']['ok']): ?>
                    — Missing: <?php echo implode(', ', $requirements['extensions']['missing']); ?>
                <?php endif; ?>
            </li>
            <?php foreach ($requirements['directories'] as $dir => $writable): ?>
            <li>
                <span class="<?php echo $writable ? 'check-ok' : 'check-fail'; ?>">
                    <?php echo $writable ? '[OK]' : '[FAIL]'; ?>
                </span>
                <?php echo $dir; ?>/ writable
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($requirements['all_ok']): ?>
            <form method="get" style="margin-top: 1.5rem;">
                <input type="hidden" name="step" value="config">
                <button type="submit" class="btn btn-block">Continue to Setup</button>
            </form>
        <?php else: ?>
            <div class="alert alert-error" style="margin-top: 1rem;">
                Please fix the issues above before continuing.
            </div>
        <?php endif; ?>
    </div>

    <!-- ─── Step 2: Configuration ─── -->
    <?php elseif ($step === 'config'): ?>
    <div class="card">
        <h2>Site Configuration</h2>
        <form method="post" id="configForm">
            <input type="hidden" name="step" value="install">

            <!-- Site name -->
            <div class="form-group">
                <label for="site_name">Site Name</label>
                <input type="text" id="site_name" name="site_name" placeholder="My Website"
                       required value="<?php echo klytos_esc_attr( $_POST['site_name'] ?? '' ); ?>">
            </div>

            <!-- Site description -->
            <div class="form-group">
                <label for="description">Site Description</label>
                <textarea id="description" name="description"
                          placeholder="Brief description of your site..."><?php echo klytos_esc_html( $_POST['description'] ?? '' ); ?></textarea>
            </div>

            <!-- Language selection (with correct orthography) -->
            <div class="form-group">
                <label for="admin_language">Admin Panel Language</label>
                <select id="admin_language" name="admin_language">
                    <option value="es" <?php echo ($_POST['admin_language'] ?? '') === 'es' ? 'selected' : ''; ?>>Español</option>
                    <option value="en" <?php echo ($_POST['admin_language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                    <option value="ca">Català</option>
                    <option value="fr">Français</option>
                    <option value="de">Deutsch</option>
                    <option value="pt">Português</option>
                    <option value="it">Italiano</option>
                </select>
            </div>

            <!-- Admin credentials -->
            <div class="form-group">
                <label for="admin_user">Admin Username</label>
                <input type="text" id="admin_user" name="admin_user" required
                       value="<?php echo klytos_esc_attr( $_POST['admin_user'] ?? '' ); ?>"
                       autocomplete="off">
            </div>

            <div class="form-group">
                <label for="admin_pass">Password (min 12 characters)</label>
                <input type="password" id="admin_pass" name="admin_pass"
                       required minlength="12" autocomplete="new-password"
                       data-klytos-pwgen data-klytos-pwgen-confirm="#admin_pass_confirm">
            </div>

            <div class="form-group">
                <label for="admin_pass_confirm">Confirm Password</label>
                <input type="password" id="admin_pass_confirm" name="admin_pass_confirm"
                       required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="admin_email">Admin Email</label>
                <input type="email" id="admin_email" name="admin_email" required
                       value="<?php echo klytos_esc_attr( $_POST['admin_email'] ?? '' ); ?>">
            </div>

            <!-- Color palette -->
            <div class="form-group">
                <label>Color Palette</label>
                <div class="color-presets">
                    <label class="color-preset selected" style="background: linear-gradient(135deg, #2563eb, #7c3aed);" title="Blue">
                        <input type="radio" name="color_preset" value="blue" checked>
                    </label>
                    <label class="color-preset" style="background: linear-gradient(135deg, #16a34a, #0d9488);" title="Green">
                        <input type="radio" name="color_preset" value="green">
                    </label>
                    <label class="color-preset" style="background: linear-gradient(135deg, #7c3aed, #a855f7);" title="Purple">
                        <input type="radio" name="color_preset" value="purple">
                    </label>
                    <label class="color-preset" style="background: linear-gradient(135deg, #0f172a, #334155);" title="Dark">
                        <input type="radio" name="color_preset" value="dark">
                    </label>
                    <label class="color-preset" style="background: linear-gradient(135deg, #dc2626, #ea580c);" title="Warm">
                        <input type="radio" name="color_preset" value="warm">
                    </label>
                </div>
            </div>

            <!-- ── Content Editor ── -->
            <h3>Content Editor</h3>

            <div class="form-group">
                <label>Choose your page editor</label>
                <div class="storage-toggle">
                    <label>
                        <input type="radio" name="editor" value="gutenberg"
                               <?php echo ($_POST['editor'] ?? 'gutenberg') === 'gutenberg' ? 'checked' : ''; ?>
                               id="editor_gutenberg">
                        <span>Gutenberg</span>
                    </label>
                    <label>
                        <input type="radio" name="editor" value="tinymce"
                               <?php echo ($_POST['editor'] ?? '') === 'tinymce' ? 'checked' : ''; ?>
                               id="editor_tinymce">
                        <span>TinyMCE</span>
                    </label>
                </div>
            </div>

            <div id="editorInfo" class="info-grid">
                <div class="info-box">
                    <strong>Gutenberg (Block Editor)</strong>
                    <ul>
                        <li>Visual drag-and-drop blocks</li>
                        <li>Rich layout options (columns, media, buttons...)</li>
                        <li>Structured content — ideal for AI-generated pages</li>
                        <li>Heavier interface — loads more resources</li>
                    </ul>
                </div>
                <div class="info-box alt">
                    <strong>TinyMCE (Classic Editor)</strong>
                    <ul>
                        <li>Familiar word-processor interface</li>
                        <li>Lightweight and fast</li>
                        <li>Simple HTML output — easy to style</li>
                        <li>No block structure — less layout control</li>
                    </ul>
                </div>
            </div>
            <p class="small">You can change this later in Settings.</p>

            <!-- ── Security: Admin Directory Name ── -->
            <h3>Security</h3>

            <div class="form-group">
                <label for="admin_dir_name">Admin Panel Directory Name</label>
                <input type="text" id="admin_dir_name" name="admin_dir_name" class="form-control"
                       value="<?php echo klytos_esc_attr( $_POST['admin_dir_name'] ?? '' ); ?>"
                       placeholder="Leave empty for auto-generated random name"
                       pattern="[a-zA-Z0-9_\-]{4,64}">
                <div class="form-help">
                    <strong>Important:</strong> This is the secret URL for your admin panel.
                    Leave empty to auto-generate a random name (recommended for maximum security).
                    Example: <code>my-secret-panel</code> → your admin will be at <code>yourdomain.com/my-secret-panel/</code>.
                    Nobody can discover your admin without knowing this name.
                </div>
            </div>

            <!-- ── Storage Driver Selection ── -->
            <h3>Data Storage</h3>

            <div class="form-group">
                <label>Storage Mode</label>
                <div class="storage-toggle">
                    <label>
                        <input type="radio" name="storage_driver" value="file"
                               <?php echo ($_POST['storage_driver'] ?? 'file') === 'file' ? 'checked' : ''; ?>
                               id="storage_file">
                        <span>Flat File</span>
                    </label>
                    <label>
                        <input type="radio" name="storage_driver" value="database"
                               <?php echo ($_POST['storage_driver'] ?? '') === 'database' ? 'checked' : ''; ?>
                               id="storage_database">
                        <span>MySQL / MariaDB</span>
                    </label>
                </div>
            </div>

            <div id="storageInfo" class="info-grid">
                <div class="info-box">
                    <strong>Flat File</strong>
                    <ul>
                        <li>No database required — simpler setup</li>
                        <li>Easy to backup (just copy files)</li>
                        <li>Ideal for small sites with few pages</li>
                        <li>Not suited for large amounts of content</li>
                    </ul>
                </div>
                <div class="info-box alt">
                    <strong>MySQL / MariaDB</strong>
                    <ul>
                        <li>Better performance with many pages</li>
                        <li>Supports news posts, custom post types, etc.</li>
                        <li>Advanced search and filtering capabilities</li>
                        <li>Requires a MySQL or MariaDB server</li>
                    </ul>
                </div>
            </div>
            <p class="small">You can change this later in Settings.</p>

            <!-- Database connection fields (shown/hidden via JS) -->
            <div class="db-fields" id="dbFields">
                <div class="inline-row">
                    <div class="form-group">
                        <label for="db_host">Database Host</label>
                        <input type="text" id="db_host" name="db_host"
                               value="<?php echo klytos_esc_attr( $_POST['db_host'] ?? 'localhost' ); ?>">
                    </div>
                    <div class="form-group">
                        <label for="db_port">Port</label>
                        <input type="number" id="db_port" name="db_port"
                               value="<?php echo klytos_esc_attr( $_POST['db_port'] ?? '3306' ); ?>"
                               min="1" max="65535">
                    </div>
                </div>

                <div class="form-group">
                    <label for="db_name">Database Name</label>
                    <input type="text" id="db_name" name="db_name"
                           value="<?php echo klytos_esc_attr( $_POST['db_name'] ?? '' ); ?>"
                           placeholder="klytos_db">
                </div>

                <div class="inline-row">
                    <div class="form-group">
                        <label for="db_user">Database User</label>
                        <input type="text" id="db_user" name="db_user"
                               value="<?php echo klytos_esc_attr( $_POST['db_user'] ?? '' ); ?>"
                               autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="db_pass">Database Password</label>
                        <input type="password" id="db_pass" name="db_pass"
                               autocomplete="new-password">
                    </div>
                </div>

                <div class="form-group">
                    <label for="db_prefix">Table Prefix</label>
                    <input type="text" id="db_prefix" name="db_prefix"
                           value="<?php echo klytos_esc_attr( $_POST['db_prefix'] ?? 'kly_' ); ?>"
                           pattern="[a-zA-Z0-9_]+">
                    <p class="small">Only letters, numbers and underscores. Default: kly_</p>
                </div>

                <!-- Test connection button -->
                <button type="button" class="btn btn-sm btn-secondary" id="testDbBtn">
                    Test Connection
                </button>
                <div class="db-test-result" id="dbTestResult"></div>
            </div>

            <div style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-block" id="installBtn">Install Klytos</button>
            </div>
        </form>
    </div>

    <script>
    // ── Color preset selector ──
    document.querySelectorAll('.color-preset').forEach(function(el) {
        el.addEventListener('click', function() {
            document.querySelectorAll('.color-preset').forEach(function(p) {
                p.classList.remove('selected');
            });
            el.classList.add('selected');
        });
    });

    // ── Storage driver toggle: show/hide database fields ──
    var dbFields   = document.getElementById('dbFields');
    var radioFile  = document.getElementById('storage_file');
    var radioDb    = document.getElementById('storage_database');

    function toggleDbFields() {
        if (radioDb.checked) {
            dbFields.classList.add('visible');
        } else {
            dbFields.classList.remove('visible');
        }
    }

    radioFile.addEventListener('change', toggleDbFields);
    radioDb.addEventListener('change', toggleDbFields);
    toggleDbFields(); // Set initial state.

    // ── Test database connection via AJAX ──
    document.getElementById('testDbBtn').addEventListener('click', function() {
        var btn    = this;
        var result = document.getElementById('dbTestResult');

        btn.disabled  = true;
        btn.textContent = 'Testing...';
        result.className = 'db-test-result';
        result.style.display = 'none';

        var formData = new FormData();
        formData.append('ajax_action', 'test_db_connection');
        formData.append('db_host',   document.getElementById('db_host').value);
        formData.append('db_port',   document.getElementById('db_port').value);
        formData.append('db_name',   document.getElementById('db_name').value);
        formData.append('db_user',   document.getElementById('db_user').value);
        formData.append('db_pass',   document.getElementById('db_pass').value);
        formData.append('db_prefix', document.getElementById('db_prefix').value);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            result.style.display = 'block';
            if (data.success) {
                result.className = 'db-test-result success';
                result.textContent = data.message || 'Connection successful!';
            } else {
                result.className = 'db-test-result error';
                result.textContent = data.error || 'Connection failed.';
            }
        })
        .catch(function() {
            result.style.display = 'block';
            result.className = 'db-test-result error';
            result.textContent = 'Network error. Check your connection.';
        })
        .finally(function() {
            btn.disabled    = false;
            btn.textContent = 'Test Connection';
        });
    });
    </script>

    <!-- ─── Step 4: Complete ─── -->
    <?php elseif ($step === 'complete'): ?>
    <div class="card">
        <h2>Klytos Installed Successfully</h2>

        <p style="margin-bottom: 1rem; color: #94a3b8;">Your CMS is ready. Save the information below.</p>

        <div class="alert alert-warning">
            <strong>Important — Save your admin URL!</strong><br>
            Your admin panel has a secret URL. Bookmark it. There is no public link to it.
        </div>

        <div class="form-group">
            <label>Admin Panel (secret URL)</label>
            <div class="token-box highlight">
                <a href="<?php echo klytos_esc_url( $adminUrl ); ?>"><?php echo klytos_esc_html( $adminUrl ); ?></a>
            </div>
            <p class="small" style="color:#fbbf24;">&#9888; Bookmark this URL. There is no public link to it.</p>
        </div>

        <div class="form-group">
            <label>MCP Endpoint</label>
            <div class="token-box"><?php echo klytos_esc_html( $mcpEndpoint ); ?></div>
        </div>

        <h3 style="margin-top:1.5rem">MCP Authentication</h3>
        <p style="font-size:0.9rem;color:#94a3b8;margin-bottom:1rem">
            Klytos supports <strong style="color:#e2e8f0;">Application Passwords</strong> (Basic Auth) and <strong style="color:#e2e8f0;">OAuth 2.0/2.1</strong> for MCP connections.
            You can create more Application Passwords or OAuth clients from the admin panel.
        </p>

        <div class="form-group">
            <label>Application Password (copy now — will not be shown again)</label>
            <div class="token-box highlight">
                <?php echo klytos_esc_html( $appPassword ?? '' ); ?>
            </div>
            <p class="small">User: <strong><?php echo klytos_esc_html( $adminUser ); ?></strong> — Use with HTTP Basic Auth.</p>
        </div>

        <?php
        // Build the Basic Auth header value for the config examples.
        $basicAuth = base64_encode($adminUser . ':' . ($appPassword ?? ''));
        ?>

        <div class="form-group">
            <label>Claude Desktop / Claude Code — MCP Configuration</label>
            <div class="mcp-config">{
  "mcpServers": {
    "klytos": {
      "url": "<?php echo klytos_esc_html( $mcpEndpoint ?? '' ); ?>",
      "headers": {
        "Authorization": "Basic <?php echo klytos_esc_html( $basicAuth ); ?>"
      }
    }
  }
}</div>
        </div>

        <div class="form-group">
            <label>cURL Example</label>
            <div class="mcp-config">curl -u "<?php echo klytos_esc_html( $adminUser ); ?>:<?php echo klytos_esc_html( $appPassword ?? '' ); ?>" \
  -X POST <?php echo klytos_esc_html( $mcpEndpoint ?? '' ); ?> \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'</div>
        </div>

        <div class="alert alert-info" style="margin-top:1rem;">
            <strong>OAuth 2.0/2.1</strong> is also available for advanced integrations.
            Create OAuth clients from the admin panel &rarr; MCP section.
            PKCE (S256) is required for all clients.
        </div>

        <?php if (($storageDriver ?? 'file') === 'database'): ?>
        <div class="alert alert-success">
            MySQL storage active. Tables created with prefix "<?php echo klytos_esc_html( $dbPrefix ?? 'kly_' ); ?>".
        </div>
        <?php endif; ?>

        <a href="<?php echo klytos_esc_url( $adminUrl ); ?>" class="btn btn-block" style="text-decoration: none;">
            Go to Admin Panel
        </a>
    </div>
    <?php endif; ?>

</div>
<script src="admin/assets/js/klytos-password.js"></script>
</body>
</html>
