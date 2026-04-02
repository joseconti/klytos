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
require_once $rootPath . '/core/post-type-manager.php';

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

// ─── Installer Translations ─────────────────────────────────
$installTranslations = [
    'en' => [
        'page_title'         => 'Klytos — Installation',
        'subtitle'           => 'AI-Powered CMS Installation',
        'step_requirements'  => '1. Requirements',
        'step_setup'         => '2. Setup',
        'req_title'          => 'Requirements Check',
        'req_php'            => 'PHP 8.1+',
        'req_php_current'    => 'current',
        'req_extensions'     => 'Required extensions',
        'req_missing'        => 'Missing',
        'req_writable'       => 'writable',
        'req_continue'       => 'Continue to Setup',
        'req_fix'            => 'Please fix the issues above before continuing.',
        'cfg_title'          => 'Site Configuration',
        'cfg_site_name'      => 'Site Name',
        'cfg_language'       => 'Admin Panel Language',
        'cfg_username'       => 'Admin Username',
        'cfg_password'       => 'Password (min 12 characters)',
        'cfg_password_confirm' => 'Confirm Password',
        'cfg_email'          => 'Admin Email',
        'cfg_design'         => 'Design',
        'cfg_appearance'     => 'Admin Panel Appearance',
        'cfg_dark'           => '&#9790; Dark Mode',
        'cfg_light'          => '&#9788; Light Mode',
        'cfg_storage'        => 'Data Storage',
        'cfg_storage_mode'   => 'Storage Mode',
        'cfg_flat_file'      => 'Flat File',
        'cfg_mysql'          => 'MySQL / MariaDB',
        'cfg_flat_title'     => 'Flat File',
        'cfg_flat_1'         => 'No database required — simpler setup',
        'cfg_flat_2'         => 'Easy to backup (just copy files)',
        'cfg_flat_3'         => 'Ideal for small sites with few pages',
        'cfg_flat_4'         => 'Not suited for large amounts of content',
        'cfg_mysql_title'    => 'MySQL / MariaDB',
        'cfg_mysql_1'        => 'Better performance with many pages',
        'cfg_mysql_2'        => 'Supports news posts, custom post types, etc.',
        'cfg_mysql_3'        => 'Advanced search and filtering capabilities',
        'cfg_mysql_4'        => 'Requires a MySQL or MariaDB server',
        'cfg_change_later'   => 'You can change this later in Settings.',
        'cfg_db_host'        => 'Database Host',
        'cfg_db_port'        => 'Port',
        'cfg_db_name'        => 'Database Name',
        'cfg_db_user'        => 'Database User',
        'cfg_db_pass'        => 'Database Password',
        'cfg_db_prefix'      => 'Table Prefix',
        'cfg_db_prefix_help' => 'Only letters, numbers and underscores. Default: kly_',
        'cfg_db_test'        => 'Test Connection',
        'cfg_db_testing'     => 'Testing...',
        'cfg_db_net_error'   => 'Network error. Check your connection.',
        'cfg_install'        => 'Install Klytos',
    ],
    'es' => [
        'page_title'         => 'Klytos — Instalación',
        'subtitle'           => 'Instalación del CMS impulsado por IA',
        'step_requirements'  => '1. Requisitos',
        'step_setup'         => '2. Configuración',
        'req_title'          => 'Comprobación de Requisitos',
        'req_php'            => 'PHP 8.1+',
        'req_php_current'    => 'actual',
        'req_extensions'     => 'Extensiones requeridas',
        'req_missing'        => 'Faltan',
        'req_writable'       => 'escritura',
        'req_continue'       => 'Continuar con la Configuración',
        'req_fix'            => 'Por favor, soluciona los problemas anteriores antes de continuar.',
        'cfg_title'          => 'Configuración del Sitio',
        'cfg_site_name'      => 'Nombre del Sitio',
        'cfg_language'       => 'Idioma del Panel de Administración',
        'cfg_username'       => 'Usuario Administrador',
        'cfg_password'       => 'Contraseña (mínimo 12 caracteres)',
        'cfg_password_confirm' => 'Confirmar Contraseña',
        'cfg_email'          => 'Email del Administrador',
        'cfg_design'         => 'Diseño',
        'cfg_appearance'     => 'Apariencia del Panel',
        'cfg_dark'           => '&#9790; Modo Oscuro',
        'cfg_light'          => '&#9788; Modo Claro',
        'cfg_storage'        => 'Almacenamiento de Datos',
        'cfg_storage_mode'   => 'Modo de Almacenamiento',
        'cfg_flat_file'      => 'Archivos',
        'cfg_mysql'          => 'MySQL / MariaDB',
        'cfg_flat_title'     => 'Archivos',
        'cfg_flat_1'         => 'Sin base de datos — configuración más sencilla',
        'cfg_flat_2'         => 'Fácil de respaldar (solo copiar archivos)',
        'cfg_flat_3'         => 'Ideal para sitios pequeños con pocas páginas',
        'cfg_flat_4'         => 'No apto para grandes cantidades de contenido',
        'cfg_mysql_title'    => 'MySQL / MariaDB',
        'cfg_mysql_1'        => 'Mejor rendimiento con muchas páginas',
        'cfg_mysql_2'        => 'Soporta noticias, tipos de contenido personalizados, etc.',
        'cfg_mysql_3'        => 'Capacidades avanzadas de búsqueda y filtrado',
        'cfg_mysql_4'        => 'Requiere un servidor MySQL o MariaDB',
        'cfg_change_later'   => 'Puedes cambiar esto más tarde en Ajustes.',
        'cfg_db_host'        => 'Host de la Base de Datos',
        'cfg_db_port'        => 'Puerto',
        'cfg_db_name'        => 'Nombre de la Base de Datos',
        'cfg_db_user'        => 'Usuario de la Base de Datos',
        'cfg_db_pass'        => 'Contraseña de la Base de Datos',
        'cfg_db_prefix'      => 'Prefijo de Tablas',
        'cfg_db_prefix_help' => 'Solo letras, números y guiones bajos. Por defecto: kly_',
        'cfg_db_test'        => 'Probar Conexión',
        'cfg_db_testing'     => 'Probando...',
        'cfg_db_net_error'   => 'Error de red. Comprueba tu conexión.',
        'cfg_install'        => 'Instalar Klytos',
    ],
];

// Detect language from GET (passed by installer.php) or POST (form resubmission).
$installLang = $_POST['admin_language'] ?? $_GET['lang'] ?? 'en';
if (!array_key_exists($installLang, $installTranslations)) {
    $installLang = 'en';
}
$t = $installTranslations[$installLang];

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
        $siteName         = trim($_POST['site_name'] ?? '');
        $adminUser        = trim($_POST['admin_user'] ?? '');
        $adminPass        = $_POST['admin_pass'] ?? '';
        $adminPass2       = $_POST['admin_pass_confirm'] ?? '';
        $adminEmail       = trim($_POST['admin_email'] ?? '');
        $adminLang        = $_POST['admin_language'] ?? 'en';
        $designPreference = $_POST['design_preference'] ?? 'dark';
        $storageDriver    = $_POST['storage_driver'] ?? 'file';

        // Derived defaults (configurable later from admin panel).
        $description  = '';
        $colorPreset  = $designPreference === 'dark' ? 'dark' : 'blue';
        $editorChoice = 'gutenberg';
        $adminDirName = ''; // Auto-generated below.

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

        // Auto-generate admin directory name (secret URL) for maximum security.
        $adminDirName = bin2hex(random_bytes(6)) . '-admin'; // e.g. 'a3f7b2c1e9d4-admin'

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
                    'installed_at'       => Helpers::now(),
                    'version'            => KLYTOS_VERSION,
                    'update_channel'     => 'stable',
                    'timezone'           => 'Europe/Madrid',
                    'design_preference'  => $designPreference,
                    'setup_completed'    => false,
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
                    'admin_theme'      => $designPreference,
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

                // ── Step F.1: Set editor on built-in 'page' post type ──
                $ptManager = new \Klytos\Core\PostTypeManager($storage);
                $ptManager->update('page', ['editor' => $editorChoice]);

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
                    $dirRenamed = rename($rootPath, $newDirPath);
                    if (!$dirRenamed) {
                        error_log("Klytos: could not rename admin directory from '{$currentDirName}' to '{$adminDirName}'. Check directory permissions.");
                        // Update config with the REAL directory name so URLs are correct.
                        $config['admin_dir'] = $currentDirName;
                        $storage->writeTo($rootPath . '/config', 'config.json.enc', $config);
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

                // ── Persist correct URLs in config ──
                // The setup wizard needs these to display the correct MCP endpoint,
                // especially after a directory rename.
                $adminFinalPath = $dirRenamed ? $newDirPath : $rootPath;
                $config['admin_dir']    = $finalDir;
                $config['mcp_endpoint'] = $mcpEndpoint;
                $config['admin_url']    = $adminUrl;

                if ($storageDriver === 'file') {
                    $finalStorage = new FileStorage($enc, $adminFinalPath . '/data');
                    $finalStorage->writeTo($adminFinalPath . '/config', 'config.json.enc', $config);
                } else {
                    $storage->writeTo($adminFinalPath . '/config', 'config.json.enc', $config);
                }

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
<html lang="<?php echo klytos_esc_attr( substr( $installLang, 0, 2 ) ); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo klytos_esc_html( $t['page_title'] ); ?></title>
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
        <p><?php echo klytos_esc_html( $t['subtitle'] ); ?></p>
    </div>

    <!-- Step indicators (2 steps: Requirements → Setup) -->
    <div class="steps">
        <div class="step <?php echo $step === 'requirements' ? 'active' : ($step !== 'requirements' ? 'done' : ''); ?>"><?php echo $t['step_requirements']; ?></div>
        <div class="step <?php echo $step === 'config' || $step === 'install' ? 'active' : ''; ?>"><?php echo $t['step_setup']; ?></div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
    <?php endif; ?>

    <!-- ─── Step 1: Requirements ─── -->
    <?php if ($step === 'requirements'): ?>
    <div class="card">
        <h2><?php echo $t['req_title']; ?></h2>
        <ul class="check-list">
            <li>
                <span class="<?php echo $requirements['php_version']['ok'] ? 'check-ok' : 'check-fail'; ?>">
                    <?php echo $requirements['php_version']['ok'] ? '&#10003;' : '&#10007;'; ?>
                </span>
                <?php echo $t['req_php']; ?> (<?php echo $t['req_php_current']; ?>: <?php echo $requirements['php_version']['current']; ?>)
            </li>
            <li>
                <span class="<?php echo $requirements['extensions']['ok'] ? 'check-ok' : 'check-fail'; ?>">
                    <?php echo $requirements['extensions']['ok'] ? '&#10003;' : '&#10007;'; ?>
                </span>
                <?php echo $t['req_extensions']; ?>
                <?php if (!$requirements['extensions']['ok']): ?>
                    — <?php echo $t['req_missing']; ?>: <?php echo implode(', ', $requirements['extensions']['missing']); ?>
                <?php endif; ?>
            </li>
            <?php foreach ($requirements['directories'] as $dir => $writable): ?>
            <li>
                <span class="<?php echo $writable ? 'check-ok' : 'check-fail'; ?>">
                    <?php echo $writable ? '[OK]' : '[FAIL]'; ?>
                </span>
                <?php echo $dir; ?>/ <?php echo $t['req_writable']; ?>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($requirements['all_ok']): ?>
            <form method="get" style="margin-top: 1.5rem;">
                <input type="hidden" name="step" value="config">
                <input type="hidden" name="lang" value="<?php echo klytos_esc_attr( $installLang ); ?>">
                <button type="submit" class="btn btn-block"><?php echo $t['req_continue']; ?></button>
            </form>
        <?php else: ?>
            <div class="alert alert-error" style="margin-top: 1rem;">
                <?php echo $t['req_fix']; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ─── Step 2: Configuration ─── -->
    <?php elseif ($step === 'config'): ?>
    <div class="card">
        <h2><?php echo $t['cfg_title']; ?></h2>
        <form method="post" id="configForm">
            <input type="hidden" name="step" value="install">

            <!-- Site name -->
            <div class="form-group">
                <label for="site_name"><?php echo $t['cfg_site_name']; ?></label>
                <input type="text" id="site_name" name="site_name" placeholder="My Website"
                       required value="<?php echo klytos_esc_attr( $_POST['site_name'] ?? '' ); ?>">
            </div>

            <!-- Language selection (pre-populated from installer.php ?lang= or POST) -->
            <?php $selectedLang = $_POST['admin_language'] ?? $_GET['lang'] ?? 'en'; ?>
            <div class="form-group">
                <label for="admin_language"><?php echo $t['cfg_language']; ?></label>
                <select id="admin_language" name="admin_language">
                    <option value="es" <?php echo $selectedLang === 'es' ? 'selected' : ''; ?>>Español</option>
                    <option value="en" <?php echo $selectedLang === 'en' ? 'selected' : ''; ?>>English</option>
                    <option value="ca" <?php echo $selectedLang === 'ca' ? 'selected' : ''; ?>>Català</option>
                    <option value="fr" <?php echo $selectedLang === 'fr' ? 'selected' : ''; ?>>Français</option>
                    <option value="de" <?php echo $selectedLang === 'de' ? 'selected' : ''; ?>>Deutsch</option>
                    <option value="pt" <?php echo $selectedLang === 'pt' ? 'selected' : ''; ?>>Português</option>
                    <option value="it" <?php echo $selectedLang === 'it' ? 'selected' : ''; ?>>Italiano</option>
                </select>
            </div>

            <!-- Admin credentials -->
            <div class="form-group">
                <label for="admin_user"><?php echo $t['cfg_username']; ?></label>
                <input type="text" id="admin_user" name="admin_user" required
                       value="<?php echo klytos_esc_attr( $_POST['admin_user'] ?? '' ); ?>"
                       autocomplete="off">
            </div>

            <div class="form-group">
                <label for="admin_pass"><?php echo $t['cfg_password']; ?></label>
                <input type="password" id="admin_pass" name="admin_pass"
                       required minlength="12" autocomplete="new-password"
                       data-klytos-pwgen data-klytos-pwgen-confirm="#admin_pass_confirm">
            </div>

            <div class="form-group">
                <label for="admin_pass_confirm"><?php echo $t['cfg_password_confirm']; ?></label>
                <input type="password" id="admin_pass_confirm" name="admin_pass_confirm"
                       required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="admin_email"><?php echo $t['cfg_email']; ?></label>
                <input type="email" id="admin_email" name="admin_email" required
                       value="<?php echo klytos_esc_attr( $_POST['admin_email'] ?? '' ); ?>">
            </div>

            <!-- ── Design Preference ── -->
            <h3><?php echo $t['cfg_design']; ?></h3>

            <div class="form-group">
                <label><?php echo $t['cfg_appearance']; ?></label>
                <div class="storage-toggle">
                    <label>
                        <input type="radio" name="design_preference" value="dark"
                               <?php echo ($_POST['design_preference'] ?? 'dark') === 'dark' ? 'checked' : ''; ?>
                               id="design_dark">
                        <span><?php echo $t['cfg_dark']; ?></span>
                    </label>
                    <label>
                        <input type="radio" name="design_preference" value="light"
                               <?php echo ($_POST['design_preference'] ?? '') === 'light' ? 'checked' : ''; ?>
                               id="design_light">
                        <span><?php echo $t['cfg_light']; ?></span>
                    </label>
                </div>
            </div>

            <!-- ── Storage Driver Selection ── -->
            <h3><?php echo $t['cfg_storage']; ?></h3>

            <div class="form-group">
                <label><?php echo $t['cfg_storage_mode']; ?></label>
                <div class="storage-toggle">
                    <label>
                        <input type="radio" name="storage_driver" value="file"
                               <?php echo ($_POST['storage_driver'] ?? 'file') === 'file' ? 'checked' : ''; ?>
                               id="storage_file">
                        <span><?php echo $t['cfg_flat_file']; ?></span>
                    </label>
                    <label>
                        <input type="radio" name="storage_driver" value="database"
                               <?php echo ($_POST['storage_driver'] ?? '') === 'database' ? 'checked' : ''; ?>
                               id="storage_database">
                        <span><?php echo $t['cfg_mysql']; ?></span>
                    </label>
                </div>
            </div>

            <div id="storageInfo" class="info-grid">
                <div class="info-box">
                    <strong><?php echo $t['cfg_flat_title']; ?></strong>
                    <ul>
                        <li><?php echo $t['cfg_flat_1']; ?></li>
                        <li><?php echo $t['cfg_flat_2']; ?></li>
                        <li><?php echo $t['cfg_flat_3']; ?></li>
                        <li><?php echo $t['cfg_flat_4']; ?></li>
                    </ul>
                </div>
                <div class="info-box alt">
                    <strong><?php echo $t['cfg_mysql_title']; ?></strong>
                    <ul>
                        <li><?php echo $t['cfg_mysql_1']; ?></li>
                        <li><?php echo $t['cfg_mysql_2']; ?></li>
                        <li><?php echo $t['cfg_mysql_3']; ?></li>
                        <li><?php echo $t['cfg_mysql_4']; ?></li>
                    </ul>
                </div>
            </div>
            <p class="small"><?php echo $t['cfg_change_later']; ?></p>

            <!-- Database connection fields (shown/hidden via JS) -->
            <div class="db-fields" id="dbFields">
                <div class="inline-row">
                    <div class="form-group">
                        <label for="db_host"><?php echo $t['cfg_db_host']; ?></label>
                        <input type="text" id="db_host" name="db_host"
                               value="<?php echo klytos_esc_attr( $_POST['db_host'] ?? 'localhost' ); ?>">
                    </div>
                    <div class="form-group">
                        <label for="db_port"><?php echo $t['cfg_db_port']; ?></label>
                        <input type="number" id="db_port" name="db_port"
                               value="<?php echo klytos_esc_attr( $_POST['db_port'] ?? '3306' ); ?>"
                               min="1" max="65535">
                    </div>
                </div>

                <div class="form-group">
                    <label for="db_name"><?php echo $t['cfg_db_name']; ?></label>
                    <input type="text" id="db_name" name="db_name"
                           value="<?php echo klytos_esc_attr( $_POST['db_name'] ?? '' ); ?>"
                           placeholder="klytos_db">
                </div>

                <div class="inline-row">
                    <div class="form-group">
                        <label for="db_user"><?php echo $t['cfg_db_user']; ?></label>
                        <input type="text" id="db_user" name="db_user"
                               value="<?php echo klytos_esc_attr( $_POST['db_user'] ?? '' ); ?>"
                               autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="db_pass"><?php echo $t['cfg_db_pass']; ?></label>
                        <input type="password" id="db_pass" name="db_pass"
                               autocomplete="new-password">
                    </div>
                </div>

                <div class="form-group">
                    <label for="db_prefix"><?php echo $t['cfg_db_prefix']; ?></label>
                    <input type="text" id="db_prefix" name="db_prefix"
                           value="<?php echo klytos_esc_attr( $_POST['db_prefix'] ?? 'kly_' ); ?>"
                           pattern="[a-zA-Z0-9_]+">
                    <p class="small"><?php echo $t['cfg_db_prefix_help']; ?></p>
                </div>

                <!-- Test connection button -->
                <button type="button" class="btn btn-sm btn-secondary" id="testDbBtn">
                    <?php echo $t['cfg_db_test']; ?>
                </button>
                <div class="db-test-result" id="dbTestResult"></div>
            </div>

            <div style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-block" id="installBtn"><?php echo $t['cfg_install']; ?></button>
            </div>
        </form>
    </div>

    <script>
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
        btn.textContent = <?php echo json_encode( $t['cfg_db_testing'] ); ?>;
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
            result.textContent = <?php echo json_encode( $t['cfg_db_net_error'] ); ?>;
        })
        .finally(function() {
            btn.disabled    = false;
            btn.textContent = <?php echo json_encode( $t['cfg_db_test'] ); ?>;
        });
    });
    </script>

    <!-- ─── Step 3: Complete — redirect to login ─── -->
    <?php elseif ($step === 'complete'): ?>
        <?php
        // Installation complete. Redirect to admin login for first-time setup wizard.
        header('Location: ' . $adminUrl . 'login.php');
        exit;
        ?>
    <?php endif; ?>

</div>
<script src="admin/assets/js/klytos-password.js"></script>
</body>
</html>
