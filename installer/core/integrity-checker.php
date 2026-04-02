<?php

/**
 * Klytos — Integrity Checker
 * Verifies that core and plugin files have not been modified, deleted, or injected.
 *
 * Compares local SHA-256 hashes against signed manifests from trusted sources:
 * - Core:        signed by Klytos, served from api.klytos.io
 * - Marketplace: signed by Klytos, served from api.klytos.io
 * - External:    signed by the plugin developer, served from their own server
 *
 * @package Klytos
 * @since   2.1.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 Jose Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class IntegrityChecker
{
    /** Storage collection for reports, manifest caches, and batch state. */
    private const COLLECTION = 'integrity';

    /** Hash algorithm used for file verification. */
    private const ALGORITHM = 'sha256';

    private StorageInterface $storage;
    private string $basePath;
    private string $apiBaseUrl;
    private int $batchSize;
    private int $cacheLifetime;

    /**
     * @param StorageInterface $storage       Storage backend.
     * @param string           $basePath      Absolute path to the Klytos root directory.
     * @param string           $apiBaseUrl    Base URL of the Klytos API.
     * @param int              $batchSize     Files per batch for cron verification.
     * @param int              $cacheLifetime Manifest cache lifetime in seconds.
     */
    public function __construct(
        StorageInterface $storage,
        string $basePath,
        string $apiBaseUrl = 'https://api.klytos.io',
        int $batchSize = 100,
        int $cacheLifetime = 86400
    ) {
        $this->storage       = $storage;
        $this->basePath      = rtrim( $basePath, '/' );
        $this->apiBaseUrl    = rtrim( $apiBaseUrl, '/' );
        $this->batchSize     = $batchSize;
        $this->cacheLifetime = $cacheLifetime;
    }

    // ─── Public API ─────────────────────────────────────────────

    /**
     * Run a full integrity verification (core + all plugins).
     *
     * @param  bool  $forceRefresh  Force manifest re-download (ignore cache).
     * @return array Full verification report.
     */
    public function verify( bool $forceRefresh = false ): array
    {
        klytos_do_action( 'integrity.before_verify', $forceRefresh );

        $report = [
            'status'     => 'ok',
            'checked_at' => Helpers::now(),
            'core'       => $this->verifyCore( $forceRefresh ),
            'plugins'    => $this->verifyAllPlugins( $forceRefresh ),
            'summary'    => [],
        ];

        // Determine global status.
        if ( $report['core']['status'] === 'error'
             || $this->hasPluginWithStatus( $report['plugins'], 'error' ) ) {
            $report['status'] = 'error';
        } elseif ( $report['core']['status'] === 'warning'
                   || $this->hasPluginWithStatus( $report['plugins'], 'warning' )
                   || $this->hasPluginWithStatus( $report['plugins'], 'unverified' ) ) {
            $report['status'] = 'warning';
        }

        $report['summary'] = $this->buildSummary( $report );

        // Persist.
        $this->storage->write( self::COLLECTION, 'last-report', $report );

        klytos_do_action( 'integrity.after_verify', $report );

        return $report;
    }

    /**
     * Run verification in batches (designed for cron).
     *
     * @return array ['completed' => bool, 'progress' => int, 'total' => int]
     */
    public function verifyBatch(): array
    {
        $batchState = null;
        try {
            $batchState = $this->storage->read( self::COLLECTION, 'batch-state' );
        } catch ( \Throwable ) {
            // No existing batch — start fresh.
        }

        if ( $batchState === null || ( $batchState['completed'] ?? false ) ) {
            $allFiles   = $this->collectAllFilesToVerify();
            $batchState = [
                'files'      => $allFiles,
                'offset'     => 0,
                'total'      => count( $allFiles ),
                'results'    => ['modified' => [], 'added' => [], 'missing' => []],
                'completed'  => false,
                'started_at' => Helpers::now(),
            ];
        }

        $offset = $batchState['offset'];
        $batch  = array_slice( $batchState['files'], $offset, $this->batchSize, true );

        foreach ( $batch as $fileInfo ) {
            $fullPath     = $fileInfo['base_path'] . '/' . $fileInfo['relative_path'];
            $expectedHash = $fileInfo['expected_hash'];

            if ( !file_exists( $fullPath ) ) {
                $batchState['results']['missing'][] = $fileInfo['label'];
            } else {
                $localHash = hash_file( self::ALGORITHM, $fullPath );
                if ( $localHash !== $expectedHash ) {
                    $batchState['results']['modified'][] = $fileInfo['label'];
                }
            }
        }

        $batchState['offset'] += $this->batchSize;

        if ( $batchState['offset'] >= $batchState['total'] ) {
            $batchState['completed']    = true;
            $batchState['completed_at'] = Helpers::now();

            $hasProblems = !empty( $batchState['results']['modified'] )
                        || !empty( $batchState['results']['added'] );

            $this->storage->write( self::COLLECTION, 'last-report', [
                'status'     => $hasProblems ? 'error' : 'ok',
                'checked_at' => Helpers::now(),
                'results'    => $batchState['results'],
                'mode'       => 'batch',
            ] );
        }

        $this->storage->write( self::COLLECTION, 'batch-state', $batchState );

        return [
            'completed' => $batchState['completed'],
            'progress'  => min( $batchState['offset'], $batchState['total'] ),
            'total'     => $batchState['total'],
        ];
    }

    /**
     * Get the last stored verification report.
     *
     * @return array|null
     */
    public function getLastReport(): ?array
    {
        try {
            return $this->storage->read( self::COLLECTION, 'last-report' );
        } catch ( \Throwable ) {
            return null;
        }
    }

    /**
     * Verify a single plugin by ID.
     *
     * @param  string $pluginId     Plugin identifier.
     * @param  bool   $forceRefresh Ignore manifest cache.
     * @return array  Plugin verification result.
     */
    public function verifyOnePlugin( string $pluginId, bool $forceRefresh = false ): array
    {
        $app     = App::getInstance();
        $loader  = $app->getPluginLoader();
        $plugins = $loader->getDiscoveredPlugins();

        if ( !isset( $plugins[$pluginId] ) ) {
            return [
                'status'  => 'error',
                'message' => "Plugin '{$pluginId}' not found.",
            ];
        }

        return $this->verifyPlugin( $pluginId, $plugins[$pluginId], $forceRefresh );
    }

    /**
     * Register a developer's public key (called once at plugin installation).
     *
     * @param  string $pluginId Plugin identifier.
     * @param  string $keyUrl   HTTPS URL to the developer's public PEM key.
     * @return bool   True on success.
     */
    public function registerDeveloperKey( string $pluginId, string $keyUrl ): bool
    {
        try {
            $context = stream_context_create( [
                'http' => [
                    'timeout' => 15,
                    'header'  => "Accept: application/x-pem-file\r\n"
                               . 'User-Agent: Klytos/' . KLYTOS_VERSION . "\r\n",
                ],
                'ssl' => [
                    'verify_peer' => true,
                ],
            ] );

            $publicKey = @file_get_contents( $keyUrl, false, $context );

            if ( $publicKey === false ) {
                return false;
            }

            // Validate it is a real PEM public key.
            $key = openssl_pkey_get_public( $publicKey );
            if ( $key === false ) {
                return false;
            }

            $this->storage->write( 'integrity-keys', $pluginId, [
                'plugin_id'     => $pluginId,
                'public_key'    => $publicKey,
                'key_url'       => $keyUrl,
                'registered_at' => Helpers::now(),
            ] );

            return true;

        } catch ( \Throwable ) {
            return false;
        }
    }

    // ─── Core Verification ──────────────────────────────────────

    /**
     * Verify integrity of core files.
     */
    private function verifyCore( bool $forceRefresh = false ): array
    {
        $version  = KLYTOS_VERSION;
        $manifest = $this->fetchManifest(
            'core',
            "{$this->apiBaseUrl}/integrity/core/{$version}.json",
            $forceRefresh
        );

        if ( $manifest === null ) {
            return [
                'status'  => 'error',
                'message' => 'No se pudo obtener el manifiesto de integridad del core.',
            ];
        }

        if ( !$this->verifySignature( $manifest, 'core' ) ) {
            return [
                'status'  => 'error',
                'message' => 'La firma del manifiesto del core no es valida.',
            ];
        }

        return $this->compareFiles( $manifest, $this->basePath );
    }

    // ─── Plugin Verification ────────────────────────────────────

    /**
     * Verify all discovered plugins.
     */
    private function verifyAllPlugins( bool $forceRefresh = false ): array
    {
        $app     = App::getInstance();
        $loader  = $app->getPluginLoader();
        $plugins = $loader->getDiscoveredPlugins();
        $results = [];

        foreach ( $plugins as $pluginId => $manifest ) {
            $results[$pluginId] = $this->verifyPlugin( $pluginId, $manifest, $forceRefresh );
        }

        return $results;
    }

    /**
     * Verify a single plugin.
     */
    private function verifyPlugin( string $pluginId, array $pluginManifest, bool $forceRefresh ): array
    {
        $version = $pluginManifest['version'] ?? '0.0.0';
        $source  = $pluginManifest['source'] ?? 'unknown';

        $manifestUrl = $this->resolvePluginManifestUrl( $pluginId, $version, $pluginManifest );

        if ( $manifestUrl === null ) {
            return [
                'status'   => 'unverified',
                'message'  => 'Este plugin no proporciona verificacion de integridad. '
                            . 'No es posible confirmar que sus archivos no han sido modificados. '
                            . 'Contacta con el desarrollador del plugin y solicitale que implemente '
                            . 'el endpoint de verificacion de integridad de Klytos.',
                'docs_url' => 'https://developers.klytos.io/integrity',
            ];
        }

        $manifest = $this->fetchManifest( "plugin:{$pluginId}", $manifestUrl, $forceRefresh );

        if ( $manifest === null ) {
            return [
                'status'  => 'warning',
                'message' => "No se pudo descargar el manifiesto de integridad desde: {$manifestUrl}",
            ];
        }

        // Marketplace plugins use Klytos key; external plugins use developer key.
        $signatureSource = ( $source === 'marketplace' ) ? 'klytos' : "developer:{$pluginId}";
        if ( !$this->verifySignature( $manifest, $signatureSource ) ) {
            return [
                'status'  => 'error',
                'message' => 'La firma del manifiesto de integridad no es valida.',
            ];
        }

        $pluginPath = $this->basePath . '/plugins/' . $pluginId;
        return $this->compareFiles( $manifest, $pluginPath );
    }

    /**
     * Determine the manifest URL for a plugin based on its source.
     */
    private function resolvePluginManifestUrl( string $pluginId, string $version, array $pluginManifest ): ?string
    {
        $source = $pluginManifest['source'] ?? 'unknown';

        if ( $source === 'marketplace' ) {
            return "{$this->apiBaseUrl}/integrity/plugins/{$pluginId}/{$version}.json";
        }

        $integrityUrl = $pluginManifest['integrity_url'] ?? null;

        if ( $integrityUrl === null ) {
            return null;
        }

        return str_replace( '{version}', $version, $integrityUrl );
    }

    // ─── File Comparison ────────────────────────────────────────

    /**
     * Compare local files against a manifest.
     *
     * @return array Result with status, modified, added, and missing lists.
     */
    private function compareFiles( array $manifest, string $basePath ): array
    {
        $expectedFiles = $manifest['files'] ?? [];
        $excludes      = $manifest['exclude'] ?? [];

        $modified = [];
        $missing  = [];
        $added    = [];

        // 1. Check expected files.
        foreach ( $expectedFiles as $relativePath => $expectedHash ) {
            $fullPath = $basePath . '/' . $relativePath;

            if ( !file_exists( $fullPath ) ) {
                $missing[] = $relativePath;
                continue;
            }

            $localHash = hash_file( self::ALGORITHM, $fullPath );
            if ( $localHash !== $expectedHash ) {
                $modified[] = $relativePath;
            }
        }

        // 2. Detect added files (not in manifest).
        $localFiles = $this->scanDirectory( $basePath, $excludes );

        foreach ( $localFiles as $relativePath ) {
            if ( !isset( $expectedFiles[$relativePath] ) ) {
                $added[] = $relativePath;
            }
        }

        // 3. Determine status.
        $status = 'ok';
        if ( !empty( $modified ) || !empty( $added ) ) {
            $status = 'error';
        } elseif ( !empty( $missing ) ) {
            $status = 'warning';
        }

        return [
            'status'        => $status,
            'checked'       => count( $expectedFiles ),
            'modified'      => $modified,
            'added'         => $added,
            'missing'       => $missing,
            'version'       => $manifest['version'] ?? 'unknown',
            'manifest_date' => $manifest['generated_at'] ?? 'unknown',
        ];
    }

    /**
     * Recursively scan a directory, respecting exclusion patterns.
     *
     * @return string[] Relative file paths.
     */
    private function scanDirectory( string $basePath, array $excludes = [] ): array
    {
        $files = [];

        if ( !is_dir( $basePath ) ) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $basePath, \FilesystemIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ( $iterator as $file ) {
            if ( !$file->isFile() ) {
                continue;
            }

            $relativePath = str_replace( $basePath . '/', '', $file->getPathname() );

            if ( $this->matchesExclude( $relativePath, $excludes ) ) {
                continue;
            }

            $files[] = $relativePath;
        }

        return $files;
    }

    /**
     * Check if a path matches any exclusion pattern.
     */
    private function matchesExclude( string $path, array $excludes ): bool
    {
        foreach ( $excludes as $pattern ) {
            if ( fnmatch( $pattern, $path ) ) {
                return true;
            }
        }
        return false;
    }

    // ─── Manifest Download & Cache ──────────────────────────────

    /**
     * Download a manifest, using local cache.
     *
     * @param  string     $cacheKey     Unique cache identifier.
     * @param  string     $url          Manifest URL.
     * @param  bool       $forceRefresh Skip cache.
     * @return array|null Decoded manifest or null on failure.
     */
    private function fetchManifest( string $cacheKey, string $url, bool $forceRefresh = false ): ?array
    {
        $cacheId = 'manifest-cache-' . md5( $cacheKey );

        // Check cache.
        if ( !$forceRefresh ) {
            try {
                $cached = $this->storage->read( self::COLLECTION, $cacheId );
                if ( isset( $cached['fetched_at'] )
                     && ( time() - strtotime( $cached['fetched_at'] ) ) < $this->cacheLifetime ) {
                    return $cached['data'];
                }
            } catch ( \Throwable ) {
                // No cache — proceed to download.
            }
        }

        // Download.
        try {
            $context = stream_context_create( [
                'http' => [
                    'timeout' => 15,
                    'header'  => "Accept: application/json\r\n"
                               . 'User-Agent: Klytos/' . KLYTOS_VERSION . "\r\n",
                ],
                'ssl' => [
                    'verify_peer' => true,
                ],
            ] );

            $response = @file_get_contents( $url, false, $context );

            if ( $response === false ) {
                return null;
            }

            $data = json_decode( $response, true );

            if ( !is_array( $data ) || !isset( $data['files'] ) ) {
                return null;
            }

            // Detect manifest tampering: content changed without version bump.
            if ( !$forceRefresh ) {
                $this->detectManifestTampering( $cacheKey, $cacheId, $data );
            }

            // Store in cache.
            $this->storage->write( self::COLLECTION, $cacheId, [
                'url'        => $url,
                'fetched_at' => Helpers::now(),
                'data'       => $data,
            ] );

            return $data;

        } catch ( \Throwable ) {
            return null;
        }
    }

    /**
     * Detect if a remote manifest changed without a version bump (suspicious).
     */
    private function detectManifestTampering( string $cacheKey, string $cacheId, array $newData ): void
    {
        try {
            $cached = $this->storage->read( self::COLLECTION, $cacheId );
        } catch ( \Throwable ) {
            return; // No previous cache — nothing to compare.
        }

        if ( !isset( $cached['data'] ) ) {
            return;
        }

        $cachedVersion = $cached['data']['version'] ?? '';
        $newVersion    = $newData['version'] ?? '';
        $cachedHash    = md5( json_encode( $cached['data']['files'] ?? [] ) );
        $newHash       = md5( json_encode( $newData['files'] ?? [] ) );

        if ( $cachedVersion === $newVersion && $cachedHash !== $newHash ) {
            klytos_log(
                'warning',
                "INTEGRITY WARNING: Manifest for '{$cacheKey}' changed without version bump. "
                . "Previous hash: {$cachedHash}, New hash: {$newHash}"
            );

            $this->storeAlert( $cacheKey, 'manifest_changed_without_version_bump', [
                'previous_hash' => $cachedHash,
                'new_hash'      => $newHash,
                'version'       => $newVersion,
            ] );
        }
    }

    // ─── Signature Verification ─────────────────────────────────

    /**
     * Verify the RSA-SHA256 signature of a manifest.
     *
     * @param  array  $manifest       Full manifest (includes 'signature' field).
     * @param  string $signatureSource Key source: 'core', 'klytos', or 'developer:{pluginId}'.
     * @return bool
     */
    private function verifySignature( array $manifest, string $signatureSource ): bool
    {
        $signature = $manifest['signature'] ?? null;
        if ( $signature === null ) {
            return false;
        }

        $publicKey = $this->getPublicKey( $signatureSource );
        if ( $publicKey === null ) {
            return false;
        }

        // Reconstruct the signed payload (manifest without the signature field).
        $payload = $manifest;
        unset( $payload['signature'] );
        $payloadJson = json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        $signatureDecoded = base64_decode( $signature, true );
        if ( $signatureDecoded === false ) {
            return false;
        }

        $result = openssl_verify( $payloadJson, $signatureDecoded, $publicKey, OPENSSL_ALGO_SHA256 );

        return $result === 1;
    }

    /**
     * Get the public key for signature verification.
     *
     * @param  string      $source 'core' | 'klytos' | 'developer:{pluginId}'
     * @return string|null PEM-encoded public key.
     */
    private function getPublicKey( string $source ): ?string
    {
        if ( $source === 'core' || $source === 'klytos' ) {
            $keyPath = $this->basePath . '/core/keys/klytos-integrity.pub';
            if ( file_exists( $keyPath ) ) {
                return file_get_contents( $keyPath );
            }
            return null;
        }

        if ( str_starts_with( $source, 'developer:' ) ) {
            $pluginId = substr( $source, strlen( 'developer:' ) );
            try {
                $keyRecord = $this->storage->read( 'integrity-keys', $pluginId );
                return $keyRecord['public_key'] ?? null;
            } catch ( \Throwable ) {
                return null;
            }
        }

        return null;
    }

    // ─── Batch Helpers ──────────────────────────────────────────

    /**
     * Collect all files that need verification (core + all plugins with manifests).
     *
     * @return array Flat list of file descriptors for batch processing.
     */
    private function collectAllFilesToVerify(): array
    {
        $allFiles = [];

        // Core manifest.
        $coreManifest = $this->fetchManifest(
            'core',
            "{$this->apiBaseUrl}/integrity/core/" . KLYTOS_VERSION . '.json',
            false
        );

        if ( $coreManifest !== null && isset( $coreManifest['files'] ) ) {
            foreach ( $coreManifest['files'] as $relativePath => $hash ) {
                $allFiles[] = [
                    'base_path'     => $this->basePath,
                    'relative_path' => $relativePath,
                    'expected_hash' => $hash,
                    'label'         => 'core:' . $relativePath,
                ];
            }
        }

        // Plugin manifests.
        $app     = App::getInstance();
        $loader  = $app->getPluginLoader();
        $plugins = $loader->getDiscoveredPlugins();

        foreach ( $plugins as $pluginId => $pluginManifest ) {
            $version     = $pluginManifest['version'] ?? '0.0.0';
            $manifestUrl = $this->resolvePluginManifestUrl( $pluginId, $version, $pluginManifest );

            if ( $manifestUrl === null ) {
                continue;
            }

            $manifest = $this->fetchManifest( "plugin:{$pluginId}", $manifestUrl, false );

            if ( $manifest === null || !isset( $manifest['files'] ) ) {
                continue;
            }

            $pluginPath = $this->basePath . '/plugins/' . $pluginId;
            foreach ( $manifest['files'] as $relativePath => $hash ) {
                $allFiles[] = [
                    'base_path'     => $pluginPath,
                    'relative_path' => $relativePath,
                    'expected_hash' => $hash,
                    'label'         => $pluginId . ':' . $relativePath,
                ];
            }
        }

        return $allFiles;
    }

    // ─── Alerts ─────────────────────────────────────────────────

    /**
     * Store an integrity alert for display in the admin panel.
     */
    private function storeAlert( string $cacheKey, string $type, array $data ): void
    {
        $alertId = 'alert-' . md5( $cacheKey . $type );

        try {
            $this->storage->write( self::COLLECTION, $alertId, [
                'cache_key'  => $cacheKey,
                'type'       => $type,
                'data'       => $data,
                'created_at' => Helpers::now(),
            ] );
        } catch ( \Throwable ) {
            // Non-critical — log and continue.
        }
    }

    // ─── Summary ────────────────────────────────────────────────

    /**
     * Build a human-readable summary from a verification report.
     */
    private function buildSummary( array $report ): array
    {
        $summary = [
            'total_plugins'      => 0,
            'verified_klytos'    => 0,
            'verified_developer' => 0,
            'unverified'         => 0,
            'plugins_ok'         => 0,
            'plugins_warning'    => 0,
            'plugins_error'      => 0,
            'core_status'        => $report['core']['status'] ?? 'unknown',
            'core_files_checked' => $report['core']['checked'] ?? 0,
        ];

        foreach ( $report['plugins'] as $pluginId => $result ) {
            $summary['total_plugins']++;
            $status = $result['status'] ?? 'unknown';

            match ( $status ) {
                'ok'         => $summary['plugins_ok']++,
                'warning'    => $summary['plugins_warning']++,
                'error'      => $summary['plugins_error']++,
                'unverified' => $summary['unverified']++,
                default      => null,
            };
        }

        $summary['verified_klytos']    = $summary['plugins_ok'] + $summary['plugins_warning'] + $summary['plugins_error'];
        $summary['verified_developer'] = 0; // Refined in future with source tracking.

        return $summary;
    }

    /**
     * Check if any plugin has a given status.
     */
    private function hasPluginWithStatus( array $plugins, string $status ): bool
    {
        foreach ( $plugins as $result ) {
            if ( ( $result['status'] ?? '' ) === $status ) {
                return true;
            }
        }
        return false;
    }
}
