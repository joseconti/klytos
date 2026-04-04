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
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

// Single source of truth for the Klytos version.
if (!defined('KLYTOS_VERSION')) {
    $versionFile = dirname(__DIR__) . '/VERSION';
    define('KLYTOS_VERSION', file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0.0.0');
}

class App
{
    /** @var App|null Singleton instance. */
    private static ?App $instance = null;

    // ─── Paths ──────────────────────────────────────────────────

    /** @var string Absolute path to the Klytos root directory (parent of core/). */
    private string $rootPath;

    /** @var string Absolute path to the web root (parent of the admin directory). */
    private string $webRootPath;

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

    /** @var ConsentManager|null Cookie consent management for GDPR/CCPA. */
    private ?ConsentManager $consentManager = null;

    /** @var PrivacyManager|null GDPR data export and erasure tools. */
    private ?PrivacyManager $privacyManager = null;

    /** @var CronManager|null Pseudo-cron task scheduler. */
    private ?CronManager $cronManager = null;

    /** @var ActionScheduler|null Action scheduler for server cron. */
    private ?ActionScheduler $actionScheduler = null;

    /** @var AuditLog|null Activity audit trail. */
    private ?AuditLog $auditLog = null;

    /** @var TwoFactor|null Two-factor authentication manager. */
    private ?TwoFactor $twoFactor = null;

    /** @var PostTypeManager|null Custom post types and taxonomies. */
    private ?PostTypeManager $postTypeManager = null;

    /** @var NoticeManager|null Admin notices API (transient + persistent). */
    private ?NoticeManager $noticeManager = null;

    /** @var Mailer|null Central email sending service. */
    private ?Mailer $mailer = null;

    /** @var OptionsManager|null Public Options API manager. */
    private ?OptionsManager $optionsManager = null;

    /** @var MetaManager|null Public Meta API manager. */
    private ?MetaManager $metaManager = null;

    /** @var TemplateResolver|null Template resolution with 4-level hierarchy. */
    private ?TemplateResolver $templateResolver = null;

    /** @var RouteManager|null Dynamic route manager for plugin routes. */
    private ?RouteManager $routeManager = null;

    /** @var Ai\ChatEngine|null AI chat engine (lazy-loaded). */
    private ?Ai\ChatEngine $chatEngine = null;

    /** @var TerminalExecutor|null Pseudo-terminal command executor (lazy-loaded). */
    private ?TerminalExecutor $terminalExecutor = null;

    /** @var Logger|null Debug logging system (lazy-loaded). */
    private ?Logger $logger = null;

    /** @var DevBar|null Developer bar metrics collector (only when dev mode is active). */
    private ?DevBar $devBar = null;

    /** @var CacheManager|null Persistent cache manager (APCu, Redis, Memcached, or File). */
    private ?CacheManager $cacheManager = null;

    /** @var IntegrityChecker|null File integrity verification system (lazy-loaded). */
    private ?IntegrityChecker $integrityChecker = null;

    /** @var CommentManager|null Page comment management system. */
    private ?CommentManager $commentManager = null;

    /** @var SiteHealthManager|null System diagnostics manager (lazy-loaded). */
    private ?SiteHealthManager $siteHealthManager = null;

    /** @var HttpClient|null HTTP client for outbound requests (lazy-loaded). */
    private ?HttpClient $httpClient = null;

    /** @var ShortcodeManager|null Shortcode processing system (lazy-loaded). */
    private ?ShortcodeManager $shortcodeManager = null;

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
        $this->webRootPath   = dirname($this->rootPath);
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
        $this->assets     = new AssetManager($this->storage, $this->webRootPath);
        $this->updater    = new Updater( $this->storage, $this->configPath );

        // Step 9: Load the Hook engine and global helper functions.
        // These MUST be loaded BEFORE managers and plugins because managers
        // call klytos_do_action() and klytos_apply_filters() in their methods.
        require_once $this->corePath . '/hooks.php';
        require_once $this->corePath . '/helpers-global.php';
        require_once $this->corePath . '/helpers-security.php';
        require_once $this->corePath . '/timezone-cache.php';
        require_once $this->corePath . '/helpers-time.php';
        require_once $this->corePath . '/asset-usage-hooks.php';

        // Step 10: Initialize v2.0 managers.
        $this->userManager         = new UserManager($this->storage);
        $this->taskManager         = new TaskManager($this->storage);
        $this->versionManager      = new VersionManager($this->storage);
        $this->blockManager        = new BlockManager($this->storage);
        $this->pageTemplateManager = new PageTemplateManager($this->storage, $this->blockManager);
        $this->analyticsManager    = new AnalyticsManager($this->storage);
        $this->webhookManager      = new WebhookManager($this->storage);
        $this->consentManager      = new ConsentManager($this->storage);
        $this->cronManager         = new CronManager($this->storage);
        $this->actionScheduler     = new ActionScheduler($this->storage, $this->configPath);
        $this->auditLog            = new AuditLog($this->storage);
        $this->privacyManager      = new PrivacyManager( $this->storage, $this->userManager, $this->auditLog );
        $this->postTypeManager     = new PostTypeManager($this->storage);
        $this->commentManager      = new CommentManager($this->storage);

        // Step 10b: Auto-migrate v1.0 admin user to v2.0 multi-user system.
        // On first boot after upgrade from v1.x, the owner user doesn't exist yet.
        // Create it from the admin credentials stored in config.
        if ($this->userManager->findOwner() === null) {
            $this->userManager->migrateFromV1Config($this->config);
        }

        // Step 10c: Initialize Options and Meta API managers.
        // These must be ready BEFORE plugins load so they can use
        // klytos_get_option() / klytos_set_meta() in their init.php.
        // Note: Meta cleanup is automatic — _meta lives inside the entity document,
        // so deleting an entity deletes its meta too. No cleanup hook needed.
        $this->optionsManager = new OptionsManager($this->storage);
        $this->optionsManager->setActiveTextDomain('_core');
        // CacheManager injection happens later (Step 10e) when it is initialized.
        $this->metaManager    = new MetaManager( $this->storage );
        $this->noticeManager  = new NoticeManager( $this->storage );

        // Step 10d: Initialize TemplateResolver and RouteManager (before plugins so they can register).
        $this->templateResolver = new TemplateResolver($this);
        $this->routeManager     = new RouteManager();
        // Lazy-create custom-templates/ for existing installations that upgraded.
        Helpers::ensureWritableDir($this->rootPath . '/custom-templates');
        Helpers::ensureWritableDir($this->rootPath . '/custom-templates/parts');

        // Register listener to rebuild frontend assets when plugins change.
        $appRef = $this;
        klytos_add_action('build.assets_changed', function () use ($appRef): void {
            $buildEngine = new BuildEngine($appRef);
            $buildEngine->buildHooksJs();
            $buildEngine->buildPluginsCss();
        });

        // Register core scheduled action handlers.
        // 1. Auto-purge trashed pages older than 30 days.
        klytos_add_action('klytos_purge_trash', function () use ($appRef): void {
            $appRef->getPages()->purgeExpiredTrash();
        });

        // 2. Inject approved comments as static HTML during build.
        klytos_add_filter('build.page.output', function ( string $html, array $page ) use ($appRef): string {
            $commentsEnabled = $appRef->getSiteConfig()->getValue( 'comments_enabled', false );
            if ( !$commentsEnabled ) {
                return $html;
            }
            $slug = $page['slug'] ?? '';
            if ( empty( $slug ) ) {
                return $html;
            }
            $lang = $page['lang'] ?? ( $appRef->getSiteConfig()->getValue( 'default_language', 'es' ) );
            $commentsHtml = $appRef->getCommentManager()->renderCommentsHtml( $slug, $lang );
            if ( !empty( $commentsHtml ) ) {
                // Insert before </main> or before </body>.
                if ( str_contains( $html, '</main>' ) ) {
                    $html = str_replace( '</main>', $commentsHtml . '</main>', $html );
                } else {
                    $html = str_replace( '</body>', $commentsHtml . '</body>', $html );
                }
            }
            return $html;
        }, 30);

        // 3. Resolve oEmbed URLs during build (converts bare YouTube/Twitter/etc URLs to embeds).
        klytos_add_filter('build.page.output', function ( string $html ) use ($appRef): string {
            $oembedEnabled = $appRef->getSiteConfig()->getValue( 'oembed_enabled', true );
            if ( !$oembedEnabled ) {
                return $html;
            }
            $resolver = new OEmbedResolver( $appRef->getCacheManager() );
            return $resolver->resolve( $html );
        }, 20);

        // 3. Auto-publish scheduled pages whose publish_at time has arrived.
        klytos_add_action('klytos_publish_scheduled', function () use ($appRef): void {
            $published = $appRef->getPages()->publishScheduled();
            if ( !empty( $published ) ) {
                // Rebuild the affected pages so the static site is updated.
                $buildEngine = new BuildEngine( $appRef );
                foreach ( $published as $slug ) {
                    try {
                        $buildEngine->buildPage( $slug );
                    } catch ( \Throwable $e ) {
                        // Log but don't halt — other pages may still need building.
                    }
                }
            }
        });

        // Step 10e: Initialize persistent cache manager.
        // Reads cache configuration from site config. Falls back to file cache
        // if the configured driver (Redis, Memcached, APCu) is unavailable.
        // Must happen AFTER siteConfig is ready so we can read cache settings.
        $cacheConfig = $this->siteConfig->getValue('cache', []);
        if (!is_array($cacheConfig)) {
            $cacheConfig = [];
        }
        $this->cacheManager = new CacheManager($cacheConfig, $this->dataPath);

        // Inject persistent cache into OptionsManager (enables L2 caching).
        if ($this->optionsManager !== null) {
            $this->optionsManager->setCacheManager($this->cacheManager);
        }

        // Step 10f: Initialize Developer Mode (DevBar + ProfilingStorage).
        // Must happen AFTER siteConfig is ready but BEFORE plugins load
        // so that plugin operations are captured by ProfilingStorage.
        $devConfig = $this->siteConfig->getValue('developer.developer_mode', false);
        if ($devConfig) {
            require_once $this->corePath . '/dev-bar.php';
            require_once $this->corePath . '/profiling-storage.php';
            $this->devBar = DevBar::getInstance();

            // Read slow threshold from config.
            $slowThreshold = (int) $this->siteConfig->getValue('developer.devbar_log_slow_threshold', 200);
            $this->devBar->setSlowThreshold($slowThreshold);

            // Wrap storage with profiling layer.
            $this->storage = new ProfilingStorage($this->storage, $this->devBar);

            // Instrument the hook system.
            klytos_set_profiler(function (string $hookName, string $type, int $count, float $duration) {
                DevBar::getInstance()->logHook($hookName, $type, $count, $duration);
            });
        }

        // Step 11: Discover and load active plugins.
        // Plugins register their hooks/filters in their init.php files.
        require_once $this->corePath . '/plugin-loader.php';
        $this->pluginLoader = new PluginLoader(
            $this->storage,
            $this->rootPath . '/plugins',
            $this->getVersion(),
            $this->configPath
        );
        $this->pluginLoader->loadAll();

        // Step 11b: Ensure core recurring actions are scheduled.
        // Only create if not already scheduled (prevents duplicates on each boot).
        if ( klytos_next_scheduled_action( 'klytos_purge_trash' ) === null ) {
            klytos_schedule_recurring_action( time(), 86400, 'klytos_purge_trash', [], 'klytos_core' );
        }
        if ( klytos_next_scheduled_action( 'klytos_publish_scheduled' ) === null ) {
            klytos_schedule_recurring_action( time(), 300, 'klytos_publish_scheduled', [], 'klytos_core' );
        }

        // Step 11d: Initialize Shortcode Manager and register built-in shortcodes.
        $this->shortcodeManager = new ShortcodeManager();
        $this->shortcodeManager->registerBuiltins( $this );

        // Register build filter to process shortcodes in page output.
        $appRef = $this;
        klytos_add_filter( 'build.page.output', function ( string $html ) use ( $appRef ): string {
            return $appRef->getShortcodeManager()->process( $html );
        }, 15 );

        // Step 11e: Register admin bar injection for built pages.
        // The admin bar is extensible by plugins via the 'admin_bar.items' filter.
        $appRef2 = $this;
        klytos_add_filter( 'build.body_end_html', function ( string $html ) use ( $appRef2 ): string {
            $enabled = (bool) klytos_apply_filters( 'admin_bar.enabled', $appRef2->getSiteConfig()->getValue( 'admin_bar_enabled', true ) );
            if ( !$enabled ) {
                return $html;
            }

            // Build admin bar items array — plugins can add/remove via filter.
            $items = [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'url' => '{{admin_url}}index.php', 'position' => 10],
                ['id' => 'edit_page', 'label' => 'Edit Page', 'url' => '{{admin_url}}page-editor.php?slug={{page_slug}}', 'position' => 20, 'requires_slug' => true],
            ];
            $items = klytos_apply_filters( 'admin_bar.items', $items );

            // Sort by position.
            usort( $items, function ( $a, $b ) { return ( $a['position'] ?? 50 ) <=> ( $b['position'] ?? 50 ); } );

            // Encode items as JSON for the JS to consume.
            $itemsJson = json_encode( $items, JSON_UNESCAPED_UNICODE );

            $jsPath = $appRef2->getCorePath() . '/../admin/js/admin-bar.js';
            if ( !file_exists( $jsPath ) ) {
                return $html;
            }
            $js = file_get_contents( $jsPath );

            // Inject: set items config before the admin bar JS, only when cookie is present.
            $loader = "if(document.cookie.indexOf('klytos_admin_bar=')!==-1){"
                . "window.__klytos_admin_bar_items=" . $itemsJson . ";"
                . $js
                . "}";

            $output = klytos_apply_filters( 'admin_bar.render', "\n<script>" . $loader . "</script>\n" );
            return $html . $output;
        }, 99 );

        // Step 12: Fire the 'klytos.init' action — signals that all core
        // services are ready. Plugins can use this to run post-load setup.
        klytos_do_action('klytos.init', $this);
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
    public function getEncryption(): Encryption
    {
        return $this->encryption;
    }

    /**
     * Get the storage layer (FileStorage or DatabaseStorage).
     *
     * @return StorageInterface
     */
    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    /** Get the license manager. */
    public function getLicense(): License
    {
        return $this->license;
    }

    /** Get the i18n engine. */
    public function getI18n(): I18n
    {
        return $this->i18n;
    }

    /** Get the authentication manager. */
    public function getAuth(): Auth
    {
        return $this->auth;
    }

    /** Get the plugin loader. */
    public function getPluginLoader(): PluginLoader
    {
        return $this->pluginLoader;
    }

    /** Get the page manager. */
    public function getPages(): PageManager
    {
        return $this->pages;
    }

    /** Get the theme manager. */
    public function getTheme(): ThemeManager
    {
        return $this->theme;
    }

    /** Get the menu manager. */
    public function getMenu(): MenuManager
    {
        return $this->menu;
    }

    /** Get the site configuration manager. */
    public function getSiteConfig(): SiteConfig
    {
        return $this->siteConfig;
    }

    /** Get the asset manager. */
    public function getAssets(): AssetManager
    {
        return $this->assets;
    }

    /** @alias getAssets() — preferred name for v2.0+ code. */
    public function getAssetManager(): AssetManager
    {
        return $this->assets;
    }

    /** Get the updater. */
    public function getUpdater(): Updater
    {
        return $this->updater;
    }

    /** Get the decrypted main configuration array. */
    public function getConfig(): array
    {
        return $this->config ?? [];
    }

    // ─── v2.0 Manager Getters ───────────────────────────────────

    /** Get the user manager. */
    public function getUserManager(): UserManager
    {
        return $this->userManager;
    }

    /** Get the task manager. */
    public function getTaskManager(): TaskManager
    {
        return $this->taskManager;
    }

    /** Get the version manager. */
    public function getVersionManager(): VersionManager
    {
        return $this->versionManager;
    }

    /** Get the block manager. */
    public function getBlockManager(): BlockManager
    {
        return $this->blockManager;
    }

    /** Get the page template manager. */
    public function getPageTemplateManager(): PageTemplateManager
    {
        return $this->pageTemplateManager;
    }

    /** Get the analytics manager. */
    public function getAnalyticsManager(): AnalyticsManager
    {
        return $this->analyticsManager;
    }

    /** Get the webhook manager. */
    public function getWebhookManager(): WebhookManager
    {
        return $this->webhookManager;
    }

    /** Get the consent manager. */
    public function getConsentManager(): ConsentManager
    {
        return $this->consentManager;
    }

    /** Get the cron manager. */
    public function getCronManager(): CronManager
    {
        return $this->cronManager;
    }

    /** Get the action scheduler. */
    public function getActionScheduler(): ActionScheduler
    {
        return $this->actionScheduler;
    }

    /** Get the audit log. */
    public function getAuditLog(): AuditLog
    {
        return $this->auditLog;
    }

    /** Get the GDPR privacy manager. */
    public function getPrivacyManager(): PrivacyManager
    {
        return $this->privacyManager;
    }

    /** Get the post type manager. */
    public function getPostTypeManager(): PostTypeManager
    {
        return $this->postTypeManager;
    }

    /** Get the comment manager. */
    public function getCommentManager(): CommentManager
    {
        return $this->commentManager;
    }

    /** Get the site health manager (lazy-loaded). */
    public function getSiteHealthManager(): SiteHealthManager
    {
        if ( $this->siteHealthManager === null ) {
            $this->siteHealthManager = new SiteHealthManager( $this );
        }
        return $this->siteHealthManager;
    }

    /**
     * Get the Shortcode Manager.
     *
     * @return ShortcodeManager
     * @since  0.26.0
     */
    public function getShortcodeManager(): ShortcodeManager
    {
        if ( $this->shortcodeManager === null ) {
            $this->shortcodeManager = new ShortcodeManager();
            $this->shortcodeManager->registerBuiltins( $this );
        }
        return $this->shortcodeManager;
    }

    /**
     * Get the HTTP client for outbound requests.
     *
     * @return HttpClient
     * @since  0.26.0
     */
    public function getHttpClient(): HttpClient
    {
        if ( $this->httpClient === null ) {
            $this->httpClient = new HttpClient( 'Klytos/' . $this->getVersion() );
        }
        return $this->httpClient;
    }

    /** Get the Options API manager. */
    public function getOptionsManager(): OptionsManager
    {
        return $this->optionsManager;
    }

    /**
     * Get the cache manager.
     *
     * Provides access to the persistent cache layer (APCu, Redis, Memcached, or File).
     * Plugins should use klytos_cache() or klytos_cache_get() / klytos_cache_set()
     * helper functions instead of accessing this directly.
     *
     * @return CacheManager
     */
    public function getCacheManager(): CacheManager
    {
        return $this->cacheManager;
    }

    /** Get the Meta API manager. */
    public function getMetaManager(): MetaManager
    {
        return $this->metaManager;
    }

    /** Get the Notice API manager. */
    public function getNoticeManager(): NoticeManager
    {
        return $this->noticeManager;
    }

    /**
     * Get the AI chat engine (lazy-loaded).
     *
     * Creates a shared ToolRegistry, AiProviderRegistry, AiKeyManager,
     * and ChatEngine on first access.
     */
    public function getChatEngine(): Ai\ChatEngine
    {
        if ($this->chatEngine === null) {
            // Load the vendorized SDK (soukicz/php-llm + Guzzle + dependencies).
            $vendorAutoload = $this->rootPath . '/vendor-ai/autoload.php';
            if (file_exists($vendorAutoload)) {
                require_once $vendorAutoload;
            }

            $registry = new MCP\ToolRegistry($this);
            $registry->registerAllTools();

            $keys = new Ai\AiKeyManager($this->storage, $this->configPath);

            $this->chatEngine = new Ai\ChatEngine($keys, $registry, $this);
        }
        return $this->chatEngine;
    }

    /** Get the integrity checker (lazy-loaded). */
    public function getIntegrityChecker(): IntegrityChecker
    {
        if ( $this->integrityChecker === null ) {
            require_once $this->corePath . '/integrity-checker.php';
            $this->integrityChecker = new IntegrityChecker(
                $this->storage,
                $this->rootPath
            );
        }
        return $this->integrityChecker;
    }

    /** Get the terminal executor (lazy-loaded). */
    public function getTerminalExecutor(): TerminalExecutor
    {
        if ($this->terminalExecutor === null) {
            require_once $this->corePath . '/terminal-executor.php';
            $this->terminalExecutor = new TerminalExecutor($this);
        }
        return $this->terminalExecutor;
    }

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

    /**
     * Get the Logger instance (lazy-loaded).
     *
     * The Logger only writes to disk when Developer Mode is active.
     */
    public function getLogger(): Logger
    {
        if ($this->logger === null) {
            $this->logger = new Logger(
                $this->dataPath,
                $this->siteConfig,
                $this->pluginLoader,
                $this->storage
            );
        }
        return $this->logger;
    }

    /**
     * Check if Developer Mode is active.
     *
     * Convenience method — reads the developer.developer_mode flag from site config.
     *
     * @return bool
     */
    public function isDevMode(): bool
    {
        return (bool) $this->siteConfig->getValue('developer.developer_mode', false);
    }

    // ─── Path Getters ───────────────────────────────────────────

    /** Get the Klytos root directory path. */
    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    /** Get the config/ directory path. */
    public function getConfigPath(): string
    {
        return $this->configPath;
    }

    /** Get the data/ directory path. */
    public function getDataPath(): string
    {
        return $this->dataPath;
    }

    /** Get the web root path (parent of admin directory — where public assets live). */
    public function getWebRootPath(): string
    {
        return $this->webRootPath;
    }

    /** Get the public/ directory path (static site output). */
    public function getPublicPath(): string
    {
        return $this->publicPath;
    }

    /** Get the core/ directory path (PHP source). */
    public function getCorePath(): string
    {
        return $this->corePath;
    }

    /** Get the backups/ directory path. */
    public function getBackupsPath(): string
    {
        return $this->backupsPath;
    }

    /** Get the templates/ directory path. */
    public function getTemplatesPath(): string
    {
        return $this->templatesPath;
    }

    /** Get the TemplateResolver instance. */
    public function getTemplateResolver(): TemplateResolver
    {
        return $this->templateResolver;
    }

    /** Get the RouteManager instance for plugin dynamic routes. */
    public function getRouteManager(): RouteManager
    {
        return $this->routeManager;
    }

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
     * Get the current Klytos version.
     *
     * @return string Semantic version (e.g. '0.4.2').
     */
    public function getVersion(): string
    {
        return KLYTOS_VERSION;
    }
}
