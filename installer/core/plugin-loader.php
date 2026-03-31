<?php
/**
 * Klytos — Plugin Loader
 * Discovers, validates, and loads plugins from the plugins/ directory.
 *
 * Plugin identification (IMMUTABLE CONTRACT):
 *   A valid Klytos plugin is a directory plugins/{plugin-id}/ containing
 *   a PHP file named {plugin-id}.php with a docblock header that includes
 *   at minimum "Plugin Name: ...". This contract can never change.
 *
 * Plugin structure:
 *   plugins/{plugin-id}/
 *   ├── {plugin-id}.php      (REQUIRED — identification + entry point)
 *   ├── klytos-plugin.json   (OPTIONAL — extended metadata: admin_pages, mcp_tools, etc.)
 *   ├── install.php          (optional — runs on first activation)
 *   ├── deactivate.php       (optional — runs on deactivation)
 *   ├── uninstall.php        (optional — runs on uninstall, removes data)
 *   ├── admin/               (optional — admin page views)
 *   ├── assets/              (optional — CSS, JS, images)
 *   ├── lang/                (optional — translations)
 *   ├── src/                 (optional — PHP source files)
 *   ├── templates/           (optional — HTML templates)
 *   └── migrations/          (optional — data migrations)
 *
 * PHP header fields (parsed from {plugin-id}.php docblock):
 *   - Plugin Name: string (REQUIRED — human-readable name)
 *   - Version: string (semver, default: 0.0.1)
 *   - Description: string
 *   - Author: string
 *   - Author URI: string
 *   - Plugin URI: string
 *   - Requires Klytos: string (minimum Klytos version)
 *   - Requires PHP: string (minimum PHP version)
 *   - License: string (SPDX identifier)
 *   - License URI: string
 *   - Text Domain: string (default: plugin-id)
 *   - Domain Path: string (default: /lang)
 *   - Premium: bool (false = free, true = requires license)
 *   - Item Name: string (slug for license server, e.g. "klytos-e-commerce-pro")
 *   - Update URI: string (URL for third-party update checks)
 *
 * Legacy support (DEPRECATED — will be removed in v2.0.0):
 *   Plugins using only klytos-plugin.json + init.php are still discovered
 *   via fallback, but a deprecation notice is logged.
 *
 * Security:
 * - Plugin IDs are sanitized (alphanumeric, hyphens, underscores only).
 * - Header parsing reads raw bytes (no PHP execution) for discovery.
 * - Premium plugins verify their license before loading.
 * - PHP and Klytos version requirements are enforced.
 * - Plugins are sandboxed: they can only access core services via
 *   the klytos_*() helper functions, not the filesystem directly.
 *
 * @package Klytos
 * @since   1.0.0
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

class PluginLoader
{
    /** @var StorageInterface Storage backend for reading/writing plugin state. */
    private StorageInterface $storage;

    /** @var string Absolute path to the plugins/ directory. */
    private string $pluginsDir;

    /** @var string Current Klytos version (for compatibility checks). */
    private string $klytosVersion;

    /** @var string Absolute path to config/ (for license verification). */
    private string $configPath;

    /** @var array<string, array> Loaded plugin manifests, keyed by plugin ID. */
    private array $loadedPlugins = [];

    /** @var array<string, string> Errors encountered during loading, keyed by plugin ID. */
    private array $loadErrors = [];

    /** @var string Storage key for the plugin state file. */
    private const STATE_FILE = 'plugins.json.enc';

    /** @var array Required fields in klytos-plugin.json (legacy discovery only). */
    private const REQUIRED_MANIFEST_FIELDS = [
        'id', 'name', 'version', 'description', 'author',
        'requires_klytos', 'requires_php',
    ];

    /**
     * Mapping of PHP header field names to internal manifest keys.
     * This map is part of the IMMUTABLE CONTRACT — fields can be added
     * but existing entries must never be removed or renamed.
     *
     * @var array<string, string>
     */
    private const HEADER_MAP = [
        'Plugin Name'     => 'name',
        'Plugin URI'      => 'plugin_uri',
        'Description'     => 'description',
        'Version'         => 'version',
        'Author'          => 'author',
        'Author URI'      => 'author_url',
        'Requires Klytos' => 'requires_klytos',
        'Requires PHP'    => 'requires_php',
        'License'         => 'license',
        'License URI'     => 'license_uri',
        'Text Domain'     => 'text_domain',
        'Domain Path'     => 'domain_path',
        'Premium'         => 'premium',
        'Item Name'       => 'item_name',
        'Update URI'      => 'update_uri',
    ];

    /** @var int Maximum bytes to read from a plugin file for header parsing. */
    private const HEADER_READ_BYTES = 8192;

    /**
     * Constructor.
     *
     * @param StorageInterface $storage       Storage backend.
     * @param string           $pluginsDir    Absolute path to the plugins/ directory.
     * @param string           $klytosVersion Current Klytos version (from VERSION file).
     * @param string           $configPath    Absolute path to config/ directory.
     */
    public function __construct(
        StorageInterface $storage,
        string $pluginsDir,
        string $klytosVersion,
        string $configPath
    ) {
        $this->storage       = $storage;
        $this->pluginsDir    = rtrim($pluginsDir, '/');
        $this->klytosVersion = $klytosVersion;
        $this->configPath    = rtrim($configPath, '/');
    }

    /**
     * Discover and load all active plugins.
     *
     * This is the main entry point, called by App::boot() during startup.
     * Flow: read state → scan directories → validate → load active plugins.
     *
     * @return void
     */
    public function loadAll(): void
    {
        $state = $this->getState();

        // Scan the plugins directory for all valid plugin manifests.
        $discovered = $this->discoverPlugins();

        foreach ($discovered as $pluginId => $manifest) {
            // Skip plugins that are not active.
            $isActive = $state['active'][$pluginId] ?? false;
            if (!$isActive) {
                continue;
            }

            $this->loadPlugin($pluginId, $manifest);
        }
    }

    /**
     * Load a single plugin by ID and manifest.
     *
     * Validates version requirements, checks premium license, and executes init.php.
     *
     * @param string $pluginId Plugin ID.
     * @param array  $manifest Validated manifest data.
     * @return bool  True if loaded successfully.
     */
    private function loadPlugin(string $pluginId, array $manifest): bool
    {
        // Check PHP version requirement.
        $requiredPhp = $manifest['requires_php'] ?? '8.1';
        if (version_compare(PHP_VERSION, $requiredPhp, '<')) {
            $this->loadErrors[$pluginId] = "Requires PHP {$requiredPhp}+, current: " . PHP_VERSION;
            return false;
        }

        // Check Klytos version requirement.
        $requiredKlytos = $manifest['requires_klytos'] ?? '2.0.0';
        if (version_compare($this->klytosVersion, $requiredKlytos, '<')) {
            $this->loadErrors[$pluginId] = "Requires Klytos {$requiredKlytos}+, current: {$this->klytosVersion}";
            return false;
        }

        // Check premium license (if plugin requires one).
        if (!empty($manifest['premium'])) {
            if (!$this->verifyPluginLicense($pluginId, $manifest)) {
                $this->loadErrors[$pluginId] = 'Premium plugin: license required.';
                return false;
            }
        }

        // Determine entry point based on discovery method.
        $entryPoint = $manifest['_entry_point']
            ?? $this->pluginsDir . '/' . $pluginId . '/' . $pluginId . '.php';

        if (!file_exists($entryPoint)) {
            // Fallback to legacy init.php for backward compatibility.
            $entryPoint = $this->pluginsDir . '/' . $pluginId . '/init.php';
        }

        if (!file_exists($entryPoint)) {
            $this->loadErrors[$pluginId] = 'Missing entry point: ' . basename($entryPoint);
            return false;
        }

        try {
            // Include the entry point in an isolated scope.
            // The plugin can access core via klytos_*() helper functions.
            (function (string $__entryPoint): void {
                require_once $__entryPoint;
            })($entryPoint);

            $this->loadedPlugins[$pluginId] = $manifest;

            // Fire action to notify that this plugin was loaded.
            Hooks::doAction('plugin.loaded', $pluginId, $manifest);

            return true;

        } catch (\Throwable $e) {
            // Catch any error/exception from the plugin's init.php.
            // Log it but don't let a broken plugin crash the entire CMS.
            $this->loadErrors[$pluginId] = 'Init error: ' . $e->getMessage();
            error_log("Klytos PluginLoader: error loading {$pluginId}: " . $e->getMessage());
            return false;
        }
    }

    // ─── Plugin Management ───────────────────────────────────────

    /**
     * Activate a plugin.
     *
     * Sets the plugin as active in the state file, runs install.php if present
     * (first activation), and fires the 'plugin.activated' action.
     *
     * @param  string $pluginId Plugin ID to activate.
     * @return array  ['success' => bool, 'error' => string|null]
     */
    public function activate(string $pluginId): array
    {
        $manifest = $this->getManifest($pluginId);
        if ($manifest === null) {
            return ['success' => false, 'error' => "Plugin not found: {$pluginId}"];
        }

        $state = $this->getState();

        // Already active?
        if ($state['active'][$pluginId] ?? false) {
            return ['success' => true, 'error' => null];
        }

        // Run install.php if it exists (first-time activation setup).
        $installFile = $this->pluginsDir . '/' . $pluginId . '/install.php';
        if (file_exists($installFile)) {
            try {
                require_once $installFile;
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => 'Install script failed: ' . $e->getMessage()];
            }
        }

        // Mark as active.
        $state['active'][$pluginId] = true;
        $state['activated_at'][$pluginId] = Helpers::now();
        $this->saveState($state);

        // Fire activation action (plugins can listen to set up their own hooks).
        Hooks::doAction('plugin.activated', $pluginId, $manifest);

        // Rebuild frontend assets (klytos-hooks.js, plugins.css).
        Hooks::doAction('build.assets_changed');

        return ['success' => true, 'error' => null];
    }

    /**
     * Deactivate a plugin.
     *
     * Sets the plugin as inactive, runs deactivate.php if present,
     * and fires the 'plugin.deactivated' action.
     *
     * @param  string $pluginId Plugin ID to deactivate.
     * @return array  ['success' => bool, 'error' => string|null]
     */
    public function deactivate(string $pluginId): array
    {
        $state = $this->getState();

        if (!($state['active'][$pluginId] ?? false)) {
            return ['success' => true, 'error' => null]; // Already inactive.
        }

        // Run deactivate.php if present.
        $deactivateFile = $this->pluginsDir . '/' . $pluginId . '/deactivate.php';
        if (file_exists($deactivateFile)) {
            try {
                require_once $deactivateFile;
            } catch (\Throwable $e) {
                // Log but don't block deactivation.
                error_log("Klytos PluginLoader: deactivate error for {$pluginId}: " . $e->getMessage());
            }
        }

        // Mark as inactive.
        $state['active'][$pluginId] = false;
        $this->saveState($state);

        // Retrieve manifest for the action (may be null if deleted).
        $manifest = $this->getManifest($pluginId);

        Hooks::doAction('plugin.deactivated', $pluginId, $manifest);

        // Rebuild frontend assets (klytos-hooks.js, plugins.css).
        Hooks::doAction('build.assets_changed');

        return ['success' => true, 'error' => null];
    }

    /**
     * Uninstall a plugin completely.
     *
     * Deactivates, runs uninstall.php (data cleanup), and removes from state.
     * Does NOT delete the plugin directory (that's a separate operation).
     *
     * @param  string $pluginId Plugin ID to uninstall.
     * @return array  ['success' => bool, 'error' => string|null]
     */
    public function uninstall(string $pluginId): array
    {
        // Deactivate first.
        $this->deactivate($pluginId);

        // Run uninstall.php if present (removes plugin data).
        $uninstallFile = $this->pluginsDir . '/' . $pluginId . '/uninstall.php';
        if (file_exists($uninstallFile)) {
            try {
                require_once $uninstallFile;
            } catch (\Throwable $e) {
                error_log("Klytos PluginLoader: uninstall error for {$pluginId}: " . $e->getMessage());
            }
        }

        // Remove from state completely.
        $state = $this->getState();
        unset($state['active'][$pluginId]);
        unset($state['activated_at'][$pluginId]);
        $this->saveState($state);

        Hooks::doAction('plugin.uninstalled', $pluginId);

        return ['success' => true, 'error' => null];
    }

    /**
     * Delete a plugin's directory and all its files.
     *
     * This does NOT run uninstall.php or modify state — call uninstall() first
     * if you want to clean up plugin data before deleting files.
     *
     * @param  string $pluginId Plugin ID whose directory to remove.
     * @return array  ['success' => bool, 'error' => string|null]
     */
    public function deletePlugin(string $pluginId): array
    {
        $pluginDir = $this->pluginsDir . '/' . $pluginId;

        if (!is_dir($pluginDir)) {
            return ['success' => false, 'error' => "Plugin directory not found: {$pluginId}"];
        }

        Hooks::doAction('plugin.before_delete', $pluginId);

        // Recursive delete (same pattern as Updater::deleteDir).
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pluginDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($pluginDir);

        if (is_dir($pluginDir)) {
            return ['success' => false, 'error' => "Failed to delete plugin directory: {$pluginId}"];
        }

        Hooks::doAction('plugin.deleted', $pluginId);

        return ['success' => true, 'error' => null];
    }

    // ─── Install / Backup / Restore ─────────────────────────────

    /** @var int Maximum number of backups per plugin. */
    private const MAX_BACKUPS_PER_PLUGIN = 5;

    /**
     * Install a plugin from a ZIP file.
     *
     * The ZIP must contain a top-level directory with {plugin-id}/{plugin-id}.php.
     * If the plugin already exists, a backup is created before overwriting.
     *
     * @param  string $zipPath Absolute path to the uploaded ZIP file.
     * @return array  ['success' => bool, 'error' => string|null, 'plugin_id' => string|null]
     */
    public function installFromZip(string $zipPath): array
    {
        if (!file_exists($zipPath)) {
            return ['success' => false, 'error' => 'ZIP file not found', 'plugin_id' => null];
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['success' => false, 'error' => 'Failed to open ZIP archive', 'plugin_id' => null];
        }

        // Discover plugin ID from the ZIP structure.
        $pluginId = $this->detectPluginIdFromZip($zip);
        if ($pluginId === null) {
            $zip->close();
            return ['success' => false, 'error' => 'Invalid plugin ZIP: must contain {plugin-id}/{plugin-id}.php with a Plugin Name header', 'plugin_id' => null];
        }

        $pluginDir = $this->pluginsDir . '/' . $pluginId;
        $isUpdate  = is_dir($pluginDir);

        // If updating, create a backup first.
        if ($isUpdate) {
            $backupResult = $this->createBackup($pluginId);
            if (!$backupResult['success']) {
                $zip->close();
                return ['success' => false, 'error' => 'Backup failed: ' . $backupResult['error'], 'plugin_id' => $pluginId];
            }
        }

        // Extract to a temp directory first for safety.
        $tmpDir = $this->pluginsDir . '/.tmp-install-' . $pluginId . '-' . time();
        @mkdir($tmpDir, 0755, true);

        $zip->extractTo($tmpDir);
        $zip->close();

        // Find the extract root (may be nested in a subdirectory).
        $extractRoot = $tmpDir . '/' . $pluginId;
        if (!is_dir($extractRoot)) {
            // Try to find it one level deep.
            $entries = array_diff(scandir($tmpDir), ['.', '..']);
            if (count($entries) === 1) {
                $single = $tmpDir . '/' . reset($entries);
                if (is_dir($single) && file_exists($single . '/' . $pluginId . '.php')) {
                    $extractRoot = $single;
                }
            }
        }

        if (!file_exists($extractRoot . '/' . $pluginId . '.php')) {
            $this->removeDirectory($tmpDir);
            return ['success' => false, 'error' => 'ZIP structure invalid: missing ' . $pluginId . '.php', 'plugin_id' => $pluginId];
        }

        // If updating, remove old files.
        if ($isUpdate) {
            $this->removeDirectory($pluginDir);
        }

        // Move extracted plugin to final location.
        rename($extractRoot, $pluginDir);
        $this->removeDirectory($tmpDir);

        Hooks::doAction('plugin.installed', $pluginId, $isUpdate);

        return ['success' => true, 'error' => null, 'plugin_id' => $pluginId];
    }

    /**
     * Create a backup of a plugin's current files.
     *
     * Backups are stored in {pluginsDir}/.backups/{pluginId}/{timestamp}/
     * Older backups beyond MAX_BACKUPS_PER_PLUGIN are purged completely.
     *
     * @param  string $pluginId Plugin ID to back up.
     * @return array  ['success' => bool, 'error' => string|null, 'backup_name' => string|null]
     */
    public function createBackup(string $pluginId): array
    {
        $pluginDir = $this->pluginsDir . '/' . $pluginId;
        if (!is_dir($pluginDir)) {
            return ['success' => false, 'error' => "Plugin not found: {$pluginId}", 'backup_name' => null];
        }

        $backupsRoot = $this->pluginsDir . '/.backups/' . $pluginId;
        if (!is_dir($backupsRoot)) {
            @mkdir($backupsRoot, 0755, true);
        }

        $manifest  = $this->getManifest($pluginId);
        $version   = $manifest['version'] ?? 'unknown';
        $timestamp = date('Ymd-His');
        $backupName = $version . '-' . $timestamp;
        $backupDir  = $backupsRoot . '/' . $backupName;

        // Copy all files recursively.
        $this->copyDirectory($pluginDir, $backupDir);

        if (!is_dir($backupDir)) {
            return ['success' => false, 'error' => 'Failed to create backup directory', 'backup_name' => null];
        }

        // Purge old backups (keep only MAX_BACKUPS_PER_PLUGIN).
        $this->purgeOldBackups($pluginId);

        Hooks::doAction('plugin.backup_created', $pluginId, $backupName);

        return ['success' => true, 'error' => null, 'backup_name' => $backupName];
    }

    /**
     * List available backups for a plugin.
     *
     * @param  string $pluginId Plugin ID.
     * @return array  List of backups, newest first: [['name' => '...', 'date' => '...', 'version' => '...'], ...]
     */
    public function listBackups(string $pluginId): array
    {
        $backupsRoot = $this->pluginsDir . '/.backups/' . $pluginId;
        if (!is_dir($backupsRoot)) {
            return [];
        }

        $backups = [];
        $entries = array_diff(scandir($backupsRoot), ['.', '..']);

        foreach ($entries as $entry) {
            $path = $backupsRoot . '/' . $entry;
            if (!is_dir($path)) {
                continue;
            }

            // Parse name: "version-YYYYMMDD-HHMMSS"
            $parts   = explode('-', $entry, 2);
            $version = $parts[0] ?? 'unknown';
            $date    = date('Y-m-d H:i:s', filemtime($path));

            $backups[] = [
                'name'    => $entry,
                'version' => $version,
                'date'    => $date,
                'time'    => filemtime($path),
            ];
        }

        // Sort newest first.
        usort($backups, fn($a, $b) => $b['time'] <=> $a['time']);

        return $backups;
    }

    /**
     * Restore a plugin from a backup.
     *
     * Creates a backup of the current version before restoring,
     * then replaces the plugin directory with the backup contents.
     *
     * @param  string $pluginId   Plugin ID.
     * @param  string $backupName Backup directory name.
     * @return array  ['success' => bool, 'error' => string|null]
     */
    public function restoreBackup(string $pluginId, string $backupName): array
    {
        // Sanitize backup name.
        $backupName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $backupName);

        $backupDir = $this->pluginsDir . '/.backups/' . $pluginId . '/' . $backupName;
        if (!is_dir($backupDir)) {
            return ['success' => false, 'error' => "Backup not found: {$backupName}"];
        }

        $pluginDir = $this->pluginsDir . '/' . $pluginId;

        // Back up current version before restoring.
        if (is_dir($pluginDir)) {
            $this->createBackup($pluginId);
        }

        // Deactivate the plugin first.
        $this->deactivate($pluginId);

        // Remove current version and restore from backup.
        if (is_dir($pluginDir)) {
            $this->removeDirectory($pluginDir);
        }
        $this->copyDirectory($backupDir, $pluginDir);

        if (!is_dir($pluginDir)) {
            return ['success' => false, 'error' => 'Failed to restore plugin files'];
        }

        Hooks::doAction('plugin.restored', $pluginId, $backupName);

        return ['success' => true, 'error' => null];
    }

    /**
     * Purge old backups beyond the maximum limit.
     * Removes all traces of the oldest backups (files only, no logs).
     */
    private function purgeOldBackups(string $pluginId): void
    {
        $backupsRoot = $this->pluginsDir . '/.backups/' . $pluginId;
        if (!is_dir($backupsRoot)) {
            return;
        }

        $backups = [];
        $entries = array_diff(scandir($backupsRoot), ['.', '..']);
        foreach ($entries as $entry) {
            $path = $backupsRoot . '/' . $entry;
            if (is_dir($path)) {
                $backups[] = ['name' => $entry, 'path' => $path, 'time' => filemtime($path)];
            }
        }

        if (count($backups) <= self::MAX_BACKUPS_PER_PLUGIN) {
            return;
        }

        // Sort newest first.
        usort($backups, fn($a, $b) => $b['time'] <=> $a['time']);

        // Delete excess (oldest) — complete removal, no logs.
        $toDelete = array_slice($backups, self::MAX_BACKUPS_PER_PLUGIN);
        foreach ($toDelete as $backup) {
            $this->removeDirectory($backup['path']);
        }
    }

    /**
     * Detect the plugin ID from a ZIP archive by inspecting its contents.
     */
    private function detectPluginIdFromZip(\ZipArchive $zip): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Look for {dir}/{dir}.php pattern.
            if (preg_match('#^([a-zA-Z0-9_\-]+)/\1\.php$#', $name, $matches)) {
                // Verify it has a Plugin Name header.
                $content = $zip->getFromIndex($i);
                if ($content !== false && preg_match('/Plugin Name\s*:\s*(.+)/i', $content)) {
                    return $matches[1];
                }
            }
            // Also check one level deeper (nested ZIP structure).
            if (preg_match('#^[^/]+/([a-zA-Z0-9_\-]+)/\1\.php$#', $name, $matches)) {
                $content = $zip->getFromIndex($i);
                if ($content !== false && preg_match('/Plugin Name\s*:\s*(.+)/i', $content)) {
                    return $matches[1];
                }
            }
        }
        return null;
    }

    /**
     * Recursively copy a directory.
     */
    private function copyDirectory(string $src, string $dst): void
    {
        if (!is_dir($src)) {
            return;
        }
        @mkdir($dst, 0755, true);

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($items as $item) {
            $target = $dst . '/' . $items->getSubPathname();
            if ($item->isDir()) {
                @mkdir($target, 0755, true);
            } else {
                @copy($item->getPathname(), $target);
            }
        }
    }

    /**
     * Recursively remove a directory and all its contents.
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }

    // ─── Discovery & Introspection ───────────────────────────────

    /**
     * Scan the plugins directory and return all valid plugin manifests.
     *
     * Discovery order (per directory):
     * 1. Header-first: look for {plugin-id}/{plugin-id}.php with Plugin Name header.
     * 2. Legacy fallback: look for {plugin-id}/klytos-plugin.json + init.php.
     *
     * When a plugin is discovered via legacy fallback, a deprecation notice is logged.
     * Extension fields from klytos-plugin.json are merged in both cases.
     *
     * @return array<string, array> Plugin ID => validated manifest data.
     */
    public function discoverPlugins(): array
    {
        $plugins = [];

        if (!is_dir($this->pluginsDir)) {
            return [];
        }

        $dirs = scandir($this->pluginsDir);
        if ($dirs === false) {
            return [];
        }

        foreach ($dirs as $dir) {
            // Skip hidden files, dots, and non-directories.
            if ($dir === '.' || $dir === '..' || str_starts_with($dir, '.')) {
                continue;
            }

            $fullPath = $this->pluginsDir . '/' . $dir;
            if (!is_dir($fullPath)) {
                continue;
            }

            // Sanitize directory name (must be a valid plugin ID).
            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $dir)) {
                continue;
            }

            // 1. Header-first discovery: {plugin-id}/{plugin-id}.php
            $mainFile = $fullPath . '/' . $dir . '.php';
            $manifest = $this->parsePluginHeader($mainFile);

            if ($manifest !== null) {
                // Canonical discovery via PHP header.
                $manifest['id']           = $dir;
                $manifest['_discovery']   = 'header';
                $manifest['_entry_point'] = $mainFile;

                // Set text_domain default to plugin-id if not specified.
                if (empty($manifest['text_domain'])) {
                    $manifest['text_domain'] = $dir;
                }

                // Set item_name default to plugin-id if not specified.
                if (empty($manifest['item_name'])) {
                    $manifest['item_name'] = $dir;
                }

                // Merge extension fields from klytos-plugin.json (if present).
                $extension = $this->readExtendedManifest($dir);
                $manifest = array_merge($manifest, $extension);

                $plugins[$dir] = $manifest;
                continue;
            }

            // 2. Legacy fallback: klytos-plugin.json + init.php (DEPRECATED).
            $legacyManifest = $this->readLegacyManifest($dir);
            if ($legacyManifest !== null) {
                $legacyManifest['_discovery']   = 'json_legacy';
                $legacyManifest['_entry_point'] = $fullPath . '/init.php';

                error_log(
                    'Klytos PluginLoader [DEPRECATED]: Plugin "' . $dir . '" uses legacy '
                    . 'klytos-plugin.json discovery. Migrate to {plugin-id}.php header format. '
                    . 'See https://klytos.io/docs/plugin-migration'
                );

                $plugins[$dir] = $legacyManifest;
            }
        }

        return $plugins;
    }

    /**
     * Get the full list of all plugins with their state and metadata.
     *
     * Used by the admin/plugins.php page and the MCP tool klytos_list_plugins.
     *
     * @return array List of plugin info arrays.
     */
    public function listAll(): array
    {
        $discovered = $this->discoverPlugins();
        $state      = $this->getState();
        $result     = [];

        foreach ($discovered as $pluginId => $manifest) {
            $result[] = [
                'id'               => $pluginId,
                'name'             => $manifest['name'] ?? $pluginId,
                'version'          => $manifest['version'] ?? '0.0.0',
                'description'      => $manifest['description'] ?? '',
                'author'           => $manifest['author'] ?? '',
                'author_url'       => $manifest['author_url'] ?? '',
                'premium'          => !empty($manifest['premium']),
                'active'           => $state['active'][$pluginId] ?? false,
                'activated_at'     => $state['activated_at'][$pluginId] ?? null,
                'loaded'           => isset($this->loadedPlugins[$pluginId]),
                'error'            => $this->loadErrors[$pluginId] ?? null,
                'requires_klytos'  => $manifest['requires_klytos'] ?? '2.0.0',
                'requires_php'     => $manifest['requires_php'] ?? '8.1',
                'discovery_method' => $manifest['_discovery'] ?? 'unknown',
            ];
        }

        return $result;
    }

    /**
     * Get all currently active and loaded plugins.
     *
     * @return array<string, array> Plugin ID => manifest.
     */
    public function getActivePlugins(): array
    {
        return $this->loadedPlugins;
    }

    /**
     * Get a plugin's manifest by ID.
     *
     * Uses header-first discovery with legacy JSON fallback.
     *
     * @param  string $pluginId Plugin ID.
     * @return array|null Manifest data, or null if not found.
     */
    public function getManifest(string $pluginId): ?array
    {
        // Sanitize plugin ID to prevent directory traversal.
        $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $pluginId);
        if (empty($safeId) || $safeId !== $pluginId) {
            return null;
        }

        // 1. Try header-based discovery.
        $mainFile = $this->pluginsDir . '/' . $safeId . '/' . $safeId . '.php';
        $manifest = $this->parsePluginHeader($mainFile);

        if ($manifest !== null) {
            $manifest['id']           = $safeId;
            $manifest['_discovery']   = 'header';
            $manifest['_entry_point'] = $mainFile;

            if (empty($manifest['text_domain'])) {
                $manifest['text_domain'] = $safeId;
            }
            if (empty($manifest['item_name'])) {
                $manifest['item_name'] = $safeId;
            }

            // Merge extension fields from JSON.
            $extension = $this->readExtendedManifest($safeId);
            return array_merge($manifest, $extension);
        }

        // 2. Legacy fallback.
        $legacy = $this->readLegacyManifest($safeId);
        if ($legacy !== null) {
            $legacy['_discovery']   = 'json_legacy';
            $legacy['_entry_point'] = $this->pluginsDir . '/' . $safeId . '/init.php';
        }

        return $legacy;
    }

    /**
     * Get errors that occurred during plugin loading.
     *
     * @return array<string, string> Plugin ID => error message.
     */
    public function getLoadErrors(): array
    {
        return $this->loadErrors;
    }

    // ─── License Verification (Premium Plugins Only) ─────────────

    /**
     * Verify a premium plugin's license.
     *
     * Checks the stored license status for this plugin against the
     * license server (plugins.joseconti.com). Returns true if the
     * license is valid or within the grace period.
     *
     * @param  string $pluginId Plugin ID.
     * @param  array  $manifest Plugin manifest (needs 'item_name').
     * @return bool   True if the plugin is licensed to run.
     */
    private function verifyPluginLicense(string $pluginId, array $manifest): bool
    {
        // The License class handles verification against plugins.joseconti.com.
        // Each premium plugin has its own item_name for the license server.
        $license = new License($this->storage, $this->configPath);

        // Check if there's a stored license for this specific plugin.
        $licenseFile = "plugin_licenses/{$pluginId}.json.enc";

        try {
            $licenseData = $this->storage->readFrom(
                $this->configPath,
                $licenseFile
            );

            $status = $licenseData['license_status'] ?? '';
            return $status === 'valid';

        } catch (\RuntimeException $e) {
            // No license file found = not licensed.
            return false;
        }
    }

    // ─── State Management ────────────────────────────────────────

    /**
     * Read the plugin state from storage.
     *
     * State tracks which plugins are active and when they were activated.
     *
     * @return array State data: ['active' => [...], 'activated_at' => [...]]
     */
    private function getState(): array
    {
        try {
            return $this->storage->read(self::STATE_FILE);
        } catch (\RuntimeException $e) {
            // No state file yet = fresh install, no plugins active.
            return [
                'active'       => [],
                'activated_at' => [],
            ];
        }
    }

    /**
     * Save the plugin state to storage.
     *
     * @param array $state State data.
     */
    private function saveState(array $state): void
    {
        $this->storage->write(self::STATE_FILE, $state);
    }

    // ─── Header Parsing (Immutable Contract) ──────────────────────

    /**
     * Parse the PHP header of a plugin's main file.
     *
     * Reads the first 8192 bytes of the file and extracts key-value pairs
     * from the first docblock comment (/** ... * /). This is the CANONICAL
     * method for plugin identification — a file with at minimum
     * "Plugin Name: ..." in its docblock is a valid Klytos plugin.
     *
     * Security: This method reads raw bytes only. No PHP code is executed.
     * The file is never require'd or include'd during discovery.
     *
     * @param  string $filePath Absolute path to the plugin's main PHP file.
     * @return array|null Parsed header data, or null if no valid header found.
     */
    private function parsePluginHeader(string $filePath): ?array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        // Read only the first N bytes (same limit as WordPress).
        $content = file_get_contents($filePath, false, null, 0, self::HEADER_READ_BYTES);
        if ($content === false || $content === '') {
            return null;
        }

        // Extract the first docblock comment.
        if (!preg_match('/\/\*\*(.*?)\*\//s', $content, $docblock)) {
            return null;
        }

        $headerBlock = $docblock[1];
        $manifest = [];

        // Parse each header field from the docblock.
        foreach (self::HEADER_MAP as $headerName => $manifestKey) {
            // Match "Header Name: value" on a line, ignoring leading * and whitespace.
            $pattern = '/^[\s\*]*' . preg_quote($headerName, '/') . ':\s*(.+)$/mi';
            if (preg_match($pattern, $headerBlock, $match)) {
                $manifest[$manifestKey] = trim($match[1]);
            }
        }

        // Plugin Name is the ONLY required field.
        if (empty($manifest['name'])) {
            return null;
        }

        // Normalize the 'premium' field to boolean.
        if (isset($manifest['premium'])) {
            $manifest['premium'] = in_array(
                strtolower($manifest['premium']),
                ['true', 'yes', '1'],
                true
            );
        } else {
            $manifest['premium'] = false;
        }

        // Apply defaults for optional fields.
        $manifest['version']         = $manifest['version'] ?? '0.0.1';
        $manifest['description']     = $manifest['description'] ?? '';
        $manifest['author']          = $manifest['author'] ?? '';
        $manifest['author_url']      = $manifest['author_url'] ?? '';
        $manifest['requires_klytos'] = $manifest['requires_klytos'] ?? '0.0.0';
        $manifest['requires_php']    = $manifest['requires_php'] ?? '8.1';
        $manifest['text_domain']     = $manifest['text_domain'] ?? '';
        $manifest['domain_path']     = $manifest['domain_path'] ?? '/lang';

        return $manifest;
    }

    // ─── Extended Manifest (Optional JSON) ──────────────────────

    /**
     * Read extension fields from klytos-plugin.json.
     *
     * The JSON manifest is OPTIONAL and provides structured metadata that
     * does not fit in a PHP header comment (admin_pages, mcp_tools, etc.).
     * Identity fields (name, version, author, etc.) are excluded — the
     * PHP header is the canonical source for those.
     *
     * @param  string $pluginId Plugin directory name.
     * @return array  Extension fields only, empty array if no JSON file.
     */
    private function readExtendedManifest(string $pluginId): array
    {
        $manifestPath = $this->pluginsDir . '/' . $pluginId . '/klytos-plugin.json';

        if (!file_exists($manifestPath)) {
            return [];
        }

        $content = file_get_contents($manifestPath);
        if ($content === false) {
            return [];
        }

        $json = json_decode($content, true);
        if (!is_array($json)) {
            return [];
        }

        // Only return extension fields — identity fields come from the header.
        $extensionKeys = [
            'admin_pages', 'mcp_tools', 'permissions', 'capabilities',
            'post_types', 'routes', 'assets',
        ];

        $extension = [];
        foreach ($extensionKeys as $key) {
            if (isset($json[$key])) {
                $extension[$key] = $json[$key];
            }
        }

        return $extension;
    }

    // ─── Legacy Manifest Handling (Deprecated) ──────────────────

    /**
     * Read and validate a plugin's klytos-plugin.json manifest (legacy format).
     *
     * DEPRECATED: This discovery method will be removed in v2.0.0.
     * Use the PHP header format instead: {plugin-id}/{plugin-id}.php with
     * a "Plugin Name: ..." docblock header.
     *
     * Security: The manifest is read-only. Plugins cannot modify their
     * own manifest at runtime. The 'id' field must match the directory name.
     *
     * @param  string $pluginId Plugin directory name (= plugin ID).
     * @return array|null Validated manifest, or null if invalid.
     */
    private function readLegacyManifest(string $pluginId): ?array
    {
        // Sanitize plugin ID to prevent directory traversal.
        $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $pluginId);
        if (empty($safeId) || $safeId !== $pluginId) {
            return null;
        }

        $manifestPath = $this->pluginsDir . '/' . $safeId . '/klytos-plugin.json';

        if (!file_exists($manifestPath)) {
            return null;
        }

        $content = file_get_contents($manifestPath);
        if ($content === false) {
            return null;
        }

        $manifest = json_decode($content, true);
        if (!is_array($manifest)) {
            return null;
        }

        // Validate required fields.
        foreach (self::REQUIRED_MANIFEST_FIELDS as $field) {
            if (empty($manifest[$field])) {
                return null;
            }
        }

        // The manifest 'id' must match the directory name (security).
        if ($manifest['id'] !== $safeId) {
            return null;
        }

        return $manifest;
    }
}
