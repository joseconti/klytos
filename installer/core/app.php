<?php
/**
 * Klytos — Application Bootstrap
 * Main entry point that initializes all core components.
 *
 * This is the central orchestrator for Klytos. It creates the singleton App
 * instance, boots all services, and provides access to every core component.
 *
 * v2.0 Changes:
 * - Storage abstraction: uses StorageInterface (FileStorage or DatabaseStorage).
 * - Storage driver selection based on config ('file' or 'database').
 * - Database credentials loaded from config/database.json.enc when needed.
 *
 * @package Klytos
 * @since   1.0.0
 * @updated 2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class App
{
    /** @var App|null Singleton instance. */
    private static ?App $instance = null;

    // ─── Paths ──────────────────────────────────────────────────

    /** @var string Absolute path to the Klytos root directory (parent of core/). */
    private string $rootPath;

    /** @var string Path to config/ (encryption key, config.json.enc, database.json.enc). */
    private string $configPath;

    /** @var string Path to data/ (pages, users, tokens, etc.). */
    private string $dataPath;

    /** @var string Path to public/ (static site output, assets). */
    private string $publicPath;

    /** @var string Path to core/ (PHP source code). */
    private string $corePath;

    /** @var string Path to backups/ (local backup archives). */
    private string $backupsPath;

    /** @var string Path to templates/ (HTML page templates). */
    private string $templatesPath;

    // ─── Core Services ──────────────────────────────────────────

    /** @var Encryption|null AES-256-GCM encryption engine. */
    private ?Encryption $encryption = null;

    /**
     * Storage layer — either FileStorage or DatabaseStorage.
     * All managers receive this interface; they don't know which implementation is active.
     *
     * @var StorageInterface|null
     */
    private ?StorageInterface $storage = null;

    /**
     * License manager — used ONLY for premium plugin licenses, NOT for the core CMS.
     * Klytos core is free to use. This manager verifies plugin licenses against
     * plugins.joseconti.com when premium plugins are activated.
     *
     * @var License|null
     */
    private ?License $license = null;

    /** @var I18n|null Internationalization engine. */
    private ?I18n $i18n = null;

    /** @var Auth|null Authentication manager (session, bearer, OAuth, app passwords). */
    private ?Auth $auth = null;

    /** @var PluginLoader|null Plugin discovery and loading system. */
    private ?PluginLoader $pluginLoader = null;

    // ─── Content Managers ───────────────────────────────────────

    /** @var PageManager|null CRUD for site pages. */
    private ?PageManager $pages = null;

    /** @var ThemeManager|null Visual theme configuration. */
    private ?ThemeManager $theme = null;

    /** @var MenuManager|null Site navigation menus. */
    private ?MenuManager $menu = null;

    /** @var SiteConfig|null Global site settings. */
    private ?SiteConfig $siteConfig = null;

    /** @var AssetManager|null Uploaded files management. */
    private ?AssetManager $assets = null;

    /** @var Updater|null Version update checker and applier. */
    private ?Updater $updater = null;

    // ─── v2.0 Managers ──────────────────────────────────────────

    /** @var UserManager|null Multi-user management with roles. */
    private ?UserManager $userManager = null;

    /** @var TaskManager|null Review tasks and annotations. */
    private ?TaskManager $taskManager = null;

    /** @var VersionManager|null Page version history. */
    private ?VersionManager $versionManager = null;

    /** @var BlockManager|null Modular HTML block system. */
    private ?BlockManager $blockManager = null;

    /** @var PageTemplateManager|null Page template recipes. */
    private ?PageTemplateManager $pageTemplateManager = null;

    /** @var AnalyticsManager|null Privacy-first analytics. */
    private ?AnalyticsManager $analyticsManager = null;

    /** @var WebhookManager|null Event notification system. */
    private ?WebhookManager $webhookManager = null;

    /** @var CronManager|null Pseudo-cron task scheduler. */
    private ?CronManager $cronManager = null;

    /** @var AuditLog|null Activity audit trail. */
    private ?AuditLog $auditLog = null;

    /** @var TwoFactor|null Two-factor authentication manager. */
    private ?TwoFactor $twoFactor = null;

    /** @var Mailer|null Central email sending service. */
    private ?Mailer $mailer = null;

    // ─── Configuration ──────────────────────────────────────────

    /** @var array|null Decrypted main configuration (from config/config.json.enc). */
    private ?array $config = null;

    /**
     * Private constructor — use getInstance() to access.
     * Sets up all path constants relative to the root directory.
     */
    private function __construct()
    {
        $this->rootPath      = dirname(__DIR__);
        $this->configPath    = $this->rootPath . '/config';
        $this->dataPath      = $this->rootPath . '/data';
        $this->publicPath    = $this->rootPath . '/public';
        $this->corePath      = $this->rootPath . '/core';
        $this->backupsPath   = $this->rootPath . '/backups';
        $this->templatesPath = $this->rootPath . '/templates';
    }

    /**
     * Get the singleton App instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Boot the application: load config, init storage, init all services.
     *
     * Boot sequence:
     * 1. Register PSR-4 autoloader for Klytos\Core namespace.
     * 2. Check installation status (encryption key + config exist).
     * 3. Initialize encryption engine with the master key.
     * 4. Create the appropriate storage backend (FileStorage or DatabaseStorage).
     * 5. Load decrypted main configuration.
     * 6. Initialize i18n, auth, and all content managers.
     *
     * Note: Klytos core is free. No license is required to use the CMS.
     * The License manager is initialized for premium plugin validation only.
     *
     * @return void
     */
    public function boot(): void
    {
        // Step 1: Register autoloader for Klytos namespace.
        $this->registerAutoloader();

        // Step 2: Check if Klytos is installed.
        if (!$this->isInstalled()) {
            return; // Caller should redirect to install.php.
        }

        // Step 3: Initialize AES-256-GCM encryption.
        $this->encryption = new Encryption(
            $this->configPath . '/.encryption_key'
        );

        // Step 4: Create the storage backend.
        // We need a temporary FileStorage to read the config file first.
        $fileStorage  = new FileStorage($this->encryption, $this->dataPath);
        $this->config = $fileStorage->readFrom($this->configPath, 'config.json.enc');

        // Determine which storage driver to use.
        $storageDriver = $this->config['storage_driver'] ?? 'file';

        if ($storageDriver === 'database') {
            // Load encrypted database credentials.
            $dbConfig = $fileStorage->readFrom(
                $this->configPath,
                'database.json.enc'
            );

            $this->storage = new DatabaseStorage(
                $this->encryption,
                $this->dataPath,
                $dbConfig
            );
        } else {
            // Default: flat-file storage.
            $this->storage = $fileStorage;
        }

        // Step 5: Initialize internationalization.
        $locale     = $this->config['admin_language'] ?? 'en';
        $this->i18n = new I18n($locale, $this->corePath . '/lang');
        $this->registerI18nGlobal();

        // Step 6: Initialize license manager (for premium plugins only, NOT core).
        // Klytos core is free to use. The License class handles plugin license
        // verification against plugins.joseconti.com when premium plugins are active.
        $this->license = new License($this->storage, $this->configPath);

        // Step 7: Initialize authentication.
        $this->auth = new Auth($this->config, $this->storage);

        // Step 8: Initialize content managers.
        // All managers receive StorageInterface — they work with both backends.
        $this->pages      = new PageManager($this->storage);
        $this->theme      = new ThemeManager($this->storage);
        $this->menu       = new MenuManager($this->storage);
        $this->siteConfig = new SiteConfig($this->storage);
        $this->assets     = new AssetManager($this->publicPath);
        $this->updater    = new Updater( $this->storage, $this->configPath );

        // Step 9: Load the Hook engine and global helper functions.
        // These MUST be loaded BEFORE managers and plugins because managers
        // call Hooks::doAction() and Hooks::applyFilters() in their methods.
        require_once $this->corePath . '/hooks.php';
        require_once $this->corePath . '/helpers-global.php';

        // Step 10: Initialize v2.0 managers.
        $this->userManager         = new UserManager($this->storage);
        $this->taskManager         = new TaskManager($this->storage);
        $this->versionManager      = new VersionManager($this->storage);
        $this->blockManager        = new BlockManager($this->storage);
        $this->pageTemplateManager = new PageTemplateManager($this->storage, $this->blockManager);
        $this->analyticsManager    = new AnalyticsManager($this->storage);
        $this->webhookManager      = new WebhookManager($this->storage);
        $this->cronManager         = new CronManager($this->storage);
        $this->auditLog            = new AuditLog($this->storage);

        // Step 10b: Auto-migrate v1.0 admin user to v2.0 multi-user system.
        // On first boot after upgrade from v1.x, the owner user doesn't exist yet.
        // Create it from the admin credentials stored in config.
        if ($this->userManager->findOwner() === null) {
            $this->userManager->migrateFromV1Config($this->config);
        }

        // Step 10: Discover and load active plugins.
        // Plugins register their hooks/filters in their init.php files.
        require_once $this->corePath . '/plugin-loader.php';
        $this->pluginLoader = new PluginLoader(
            $this->storage,
            $this->rootPath . '/plugins',
            $this->getVersion(),
            $this->configPath
        );
        $this->pluginLoader->loadAll();

        // Step 11: Fire the 'klytos.init' action — signals that all core
        // services are ready. Plugins can use this to run post-load setup.
        Hooks::doAction('klytos.init', $this);
    }

    /**
     * Check if Klytos is installed.
     *
     * Installation is complete when both the encryption key
     * and the encrypted config file exist.
     *
     * @return bool
     */
    public function isInstalled(): bool
    {
        return file_exists($this->configPath . '/.encryption_key')
            && file_exists($this->configPath . '/config.json.enc');
    }

    /**
     * Register the PSR-4 style autoloader for the Klytos\Core namespace.
     *
     * Converts CamelCase class names to kebab-case filenames:
     *   PageManager     → core/page-manager.php
     *   MCP\Server      → core/mcp/server.php
     *   StorageInterface → core/storage-interface.php
     *   FileStorage     → core/file-storage.php
     *   DatabaseStorage → core/database-storage.php
     */
    private function registerAutoloader(): void
    {
        spl_autoload_register(function (string $class) {
            $prefix = 'Klytos\\Core\\';

            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));

            // Convert namespace separators to directory separators.
            $path = str_replace('\\', '/', $relative);

            // Convert CamelCase to kebab-case for each path segment.
            $parts = explode('/', $path);
            $parts = array_map(function (string $part): string {
                return strtolower(
                    preg_replace('/([a-z])([A-Z])/', '$1-$2', $part)
                );
            }, $parts);

            $file = $this->corePath . '/' . implode('/', $parts) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }

    /**
     * Register the global __() translation function.
     *
     * This function is available everywhere in Klytos for i18n:
     *   __('auth.login_failed') → "User or password incorrect"
     *   __('dashboard.update_available', ['version' => '2.1.0'])
     */
    private function registerI18nGlobal(): void
    {
        if (!function_exists('__')) {
            /**
             * Global translation function.
             *
             * @param  string $key          Dot-notation translation key.
             * @param  array  $replacements Placeholder values (e.g. {version}).
             * @return string Translated string.
             */
            function __(string $key, array $replacements = []): string
            {
                global $klytos_i18n;
                return $klytos_i18n->get($key, $replacements);
            }
        }

        $GLOBALS['klytos_i18n'] = $this->i18n;
    }

    // ─── Service Getters ────────────────────────────────────────

    /** Get the AES-256-GCM encryption engine. */
    public function getEncryption(): Encryption { return $this->encryption; }

    /**
     * Get the storage layer (FileStorage or DatabaseStorage).
     *
     * @return StorageInterface
     */
    public function getStorage(): StorageInterface { return $this->storage; }

    /** Get the license manager. */
    public function getLicense(): License { return $this->license; }

    /** Get the i18n engine. */
    public function getI18n(): I18n { return $this->i18n; }

    /** Get the authentication manager. */
    public function getAuth(): Auth { return $this->auth; }

    /** Get the plugin loader. */
    public function getPluginLoader(): PluginLoader { return $this->pluginLoader; }

    /** Get the page manager. */
    public function getPages(): PageManager { return $this->pages; }

    /** Get the theme manager. */
    public function getTheme(): ThemeManager { return $this->theme; }

    /** Get the menu manager. */
    public function getMenu(): MenuManager { return $this->menu; }

    /** Get the site configuration manager. */
    public function getSiteConfig(): SiteConfig { return $this->siteConfig; }

    /** Get the asset manager. */
    public function getAssets(): AssetManager { return $this->assets; }

    /** Get the updater. */
    public function getUpdater(): Updater { return $this->updater; }

    /** Get the decrypted main configuration array. */
    public function getConfig(): array { return $this->config ?? []; }

    // ─── v2.0 Manager Getters ───────────────────────────────────

    /** Get the user manager. */
    public function getUserManager(): UserManager { return $this->userManager; }

    /** Get the task manager. */
    public function getTaskManager(): TaskManager { return $this->taskManager; }

    /** Get the version manager. */
    public function getVersionManager(): VersionManager { return $this->versionManager; }

    /** Get the block manager. */
    public function getBlockManager(): BlockManager { return $this->blockManager; }

    /** Get the page template manager. */
    public function getPageTemplateManager(): PageTemplateManager { return $this->pageTemplateManager; }

    /** Get the analytics manager. */
    public function getAnalyticsManager(): AnalyticsManager { return $this->analyticsManager; }

    /** Get the webhook manager. */
    public function getWebhookManager(): WebhookManager { return $this->webhookManager; }

    /** Get the cron manager. */
    public function getCronManager(): CronManager { return $this->cronManager; }

    /** Get the audit log. */
    public function getAuditLog(): AuditLog { return $this->auditLog; }

    /** Get the two-factor authentication manager. */
    public function getTwoFactor(): TwoFactor
    {
        if ($this->twoFactor === null) {
            $this->twoFactor = new TwoFactor($this->storage);
        }
        return $this->twoFactor;
    }

    /**
     * Get the central email service.
     *
     * Lazy-loaded: reads email configuration from site config on first access.
     * All components that send email MUST use this instead of mail() directly.
     */
    public function getMailer(): Mailer
    {
        if ($this->mailer === null) {
            $siteConfig  = $this->siteConfig->get();
            $emailConfig = $siteConfig['email'] ?? [];
            $siteName    = $siteConfig['site_name'] ?? 'Klytos';
            $this->mailer = new Mailer($emailConfig, $siteName);
        }
        return $this->mailer;
    }

    // ─── Path Getters ───────────────────────────────────────────

    /** Get the Klytos root directory path. */
    public function getRootPath(): string { return $this->rootPath; }

    /** Get the config/ directory path. */
    public function getConfigPath(): string { return $this->configPath; }

    /** Get the data/ directory path. */
    public function getDataPath(): string { return $this->dataPath; }

    /** Get the public/ directory path (static site output). */
    public function getPublicPath(): string { return $this->publicPath; }

    /** Get the core/ directory path (PHP source). */
    public function getCorePath(): string { return $this->corePath; }

    /** Get the backups/ directory path. */
    public function getBackupsPath(): string { return $this->backupsPath; }

    /** Get the templates/ directory path. */
    public function getTemplatesPath(): string { return $this->templatesPath; }

    /**
     * Get the base URL path (auto-detected from the HTTP request).
     *
     * @return string e.g. '/installer'
     */
    public function getBasePath(): string
    {
        return Helpers::getBasePath();
    }

    /**
     * Get the full site URL.
     *
     * @return string e.g. 'https://example.com/installer'
     */
    public function getSiteUrl(): string
    {
        return Helpers::siteUrl();
    }

    /**
     * Get the current Klytos version from the VERSION file.
     *
     * @return string Semantic version (e.g. '2.0.0').
     */
    public function getVersion(): string
    {
        $versionFile = $this->rootPath . '/VERSION';

        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        return '0.0.0';
    }
}
