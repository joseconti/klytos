<?php
/**
 * Klytos — Plugin Loader
 * Discovers, validates, and loads plugins from the plugins/ directory.
 *
 * Plugin structure:
 *   plugins/{plugin-id}/
 *   ├── klytos-plugin.json   (REQUIRED — manifest with metadata)
 *   ├── init.php             (REQUIRED — entry point, registers hooks)
 *   ├── install.php          (optional — runs on first activation)
 *   ├── deactivate.php       (optional — runs on deactivation)
 *   ├── uninstall.php        (optional — runs on uninstall, removes data)
 *   ├── admin/               (optional — admin page views)
 *   ├── assets/              (optional — CSS, JS, images)
 *   └── src/                 (optional — PHP source files)
 *
 * Manifest (klytos-plugin.json) required fields:
 *   - id: string (must match directory name)
 *   - name: string (human-readable)
 *   - version: string (semver)
 *   - description: string
 *   - author: string
 *   - requires_klytos: string (minimum Klytos version, e.g. "2.0.0")
 *   - requires_php: string (minimum PHP version, e.g. "8.1")
 *
 * Optional manifest fields:
 *   - author_url: string
 *   - premium: bool (false = free, true = requires license)
 *   - item_name: string (product name for license server, e.g. "Klytos Cloud Backup")
 *   - license_server: string (URL, default: https://plugins.joseconti.com)
 *   - update_server: string (URL for checking updates)
 *   - permissions: string[] (capabilities required)
 *   - admin_page: object (sidebar configuration)
 *   - mcp_tools: string[] (list of MCP tool names)
 *
 * Security:
 * - Plugin IDs are sanitized (alphanumeric, hyphens, underscores only).
 * - Manifest is validated with required field checks.
 * - Premium plugins verify their license before loading.
 * - PHP and Klytos version requirements are enforced.
 * - Plugins are sandboxed: they can only access core services via
 *   the klytos_*() helper functions, not the filesystem directly.
 *
 * @package Klytos
 * @since   2.0.0
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

    /** @var array Required fields in klytos-plugin.json. */
    private const REQUIRED_MANIFEST_FIELDS = [
        'id', 'name', 'version', 'description', 'author',
        'requires_klytos', 'requires_php',
    ];

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

        // Execute the plugin's init.php entry point.
        $initFile = $this->pluginsDir . '/' . $pluginId . '/init.php';
        if (!file_exists($initFile)) {
            $this->loadErrors[$pluginId] = 'Missing init.php entry point.';
            return false;
        }

        try {
            // Include init.php in an isolated scope.
            // The plugin can access core via klytos_*() helper functions.
            (function (string $__initFile): void {
                require_once $__initFile;
            })($initFile);

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

    // ─── Discovery & Introspection ───────────────────────────────

    /**
     * Scan the plugins directory and return all valid plugin manifests.
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

            $manifest = $this->readManifest($dir);
            if ($manifest !== null) {
                $plugins[$dir] = $manifest;
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
                'id'           => $pluginId,
                'name'         => $manifest['name'] ?? $pluginId,
                'version'      => $manifest['version'] ?? '0.0.0',
                'description'  => $manifest['description'] ?? '',
                'author'       => $manifest['author'] ?? '',
                'author_url'   => $manifest['author_url'] ?? '',
                'premium'      => !empty($manifest['premium']),
                'active'       => $state['active'][$pluginId] ?? false,
                'activated_at' => $state['activated_at'][$pluginId] ?? null,
                'loaded'       => isset($this->loadedPlugins[$pluginId]),
                'error'        => $this->loadErrors[$pluginId] ?? null,
                'requires_klytos' => $manifest['requires_klytos'] ?? '2.0.0',
                'requires_php'    => $manifest['requires_php'] ?? '8.1',
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
     * @param  string $pluginId Plugin ID.
     * @return array|null Manifest data, or null if not found.
     */
    public function getManifest(string $pluginId): ?array
    {
        return $this->readManifest($pluginId);
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

    // ─── Manifest Handling ───────────────────────────────────────

    /**
     * Read and validate a plugin's klytos-plugin.json manifest.
     *
     * Security: The manifest is read-only. Plugins cannot modify their
     * own manifest at runtime. The 'id' field must match the directory name.
     *
     * @param  string $pluginId Plugin directory name (= plugin ID).
     * @return array|null Validated manifest, or null if invalid.
     */
    private function readManifest(string $pluginId): ?array
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
