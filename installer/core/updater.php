<?php
/**
 * Klytos — Updater via GitHub Releases
 *
 * Checks https://github.com/joseconti/klytos for new releases.
 * Downloads, verifies and installs updates on admin request (not automatic).
 * NEVER touches: config/, data/, plugins/, public/assets/, backups/
 *
 * @package   Klytos
 * @since     2.0.0
 * @copyright 2024-2026 José Conti. All rights reserved.
 * @license   Elastic License 2.0 (ELv2)
 */

declare( strict_types=1 );

namespace Klytos\Core;

class Updater
{
    /** GitHub owner/repo. */
    private const GITHUB_REPO = 'joseconti/klytos';

    /** GitHub API — all releases (needed to filter by channel). */
    private const GITHUB_API_RELEASES = 'https://api.github.com/repos/joseconti/klytos/releases';

    /** Cache duration in seconds (6 hours). */
    private const CACHE_TTL = 21600;

    /** Maximum update log entries. */
    private const MAX_LOG_ENTRIES = 50;

    /**
     * Update channels.
     *
     * Tag format:
     *   v2.1.0          → stable
     *   v2.1.0-rc.1     → rc (Release Candidate)
     *   v2.1.0-beta.1   → beta
     */
    public const CHANNEL_STABLE = 'stable';
    public const CHANNEL_RC     = 'rc';
    public const CHANNEL_BETA   = 'beta';

    /** @var StorageInterface */
    private StorageInterface $storage;

    /** @var string Root path of Klytos installation. */
    private string $rootPath;

    /** @var string Config path for storing cache/logs. */
    private string $configPath;

    public function __construct( StorageInterface $storage, string $configPath )
    {
        $this->storage    = $storage;
        $this->configPath = rtrim( $configPath, '/' );
        $this->rootPath   = dirname( $configPath );
    }

    /**
     * Get the user's update channel preference.
     *
     * @return string One of: stable, rc, beta
     */
    public function getChannel(): string
    {
        try {
            $data = $this->storage->readFrom( $this->configPath, 'update_settings.json.enc' );
            return $data['channel'] ?? self::CHANNEL_STABLE;
        } catch ( \RuntimeException $e ) {
            return self::CHANNEL_STABLE;
        }
    }

    /**
     * Set the update channel preference.
     *
     * @param string $channel One of: stable, rc, beta
     */
    public function setChannel( string $channel ): void
    {
        $valid = [ self::CHANNEL_STABLE, self::CHANNEL_RC, self::CHANNEL_BETA ];
        if ( ! in_array( $channel, $valid, true ) ) {
            $channel = self::CHANNEL_STABLE;
        }

        $this->storage->writeTo( $this->configPath, 'update_settings.json.enc', [
            'channel' => $channel,
        ] );
    }

    /**
     * Determine the channel of a version string.
     *
     * v2.1.0        → stable
     * v2.1.0-rc.1   → rc
     * v2.1.0-beta.1 → beta
     *
     * @param  string $version Version string (with or without 'v' prefix).
     * @return string Channel: stable, rc, or beta.
     */
    public static function versionChannel( string $version ): string
    {
        $version = ltrim( $version, 'v' );

        if ( preg_match( '/-beta\.\d+$/i', $version ) ) {
            return self::CHANNEL_BETA;
        }
        if ( preg_match( '/-rc\.\d+$/i', $version ) ) {
            return self::CHANNEL_RC;
        }
        return self::CHANNEL_STABLE;
    }

    /**
     * Check if a release is acceptable for the given channel.
     *
     * - beta channel:  accepts beta, rc, stable
     * - rc channel:    accepts rc, stable
     * - stable channel: accepts stable only
     *
     * @param  string $releaseChannel Channel of the release.
     * @param  string $userChannel    User's preference.
     * @return bool
     */
    private function isAcceptableForChannel( string $releaseChannel, string $userChannel ): bool
    {
        $hierarchy = [
            self::CHANNEL_BETA   => 0,
            self::CHANNEL_RC     => 1,
            self::CHANNEL_STABLE => 2,
        ];

        $releaseLevel = $hierarchy[ $releaseChannel ] ?? 2;
        $userLevel    = $hierarchy[ $userChannel ] ?? 2;

        return $releaseLevel >= $userLevel;
    }

    /**
     * Get a human-readable label for a version.
     *
     * @param  string $version
     * @return string E.g.: "2.1.0", "2.1.0 RC 1", "2.1.0 Beta 2"
     */
    public static function versionLabel( string $version ): string
    {
        $version = ltrim( $version, 'v' );

        if ( preg_match( '/^(.+)-beta\.(\d+)$/i', $version, $m ) ) {
            return $m[1] . ' Beta ' . $m[2];
        }
        if ( preg_match( '/^(.+)-rc\.(\d+)$/i', $version, $m ) ) {
            return $m[1] . ' RC ' . $m[2];
        }
        return $version;
    }

    /**
     * Get current installed version from VERSION file.
     *
     * @return string Semver version string.
     */
    public function getCurrentVersion(): string
    {
        $versionFile = $this->rootPath . '/VERSION';
        if ( file_exists( $versionFile ) ) {
            return trim( file_get_contents( $versionFile ) );
        }
        return '0.0.0';
    }

    /**
     * Check GitHub for available updates.
     *
     * Respects the user's channel preference:
     * - stable: only stable releases (v2.1.0)
     * - rc:     stable + release candidates (v2.1.0-rc.1)
     * - beta:   stable + rc + beta (v2.1.0-beta.1)
     *
     * Caches the result for 6 hours to avoid hitting GitHub API limits.
     * Returns null if up to date.
     *
     * @param  bool $forceRefresh Skip cache and query GitHub.
     * @return array|null Update info or null if up to date.
     */
    public function checkForUpdate( bool $forceRefresh = false ): ?array
    {
        $channel = $this->getChannel();

        // Check cache first.
        if ( ! $forceRefresh ) {
            $cached = $this->getCachedRelease();
            if ( $cached !== null && ( $cached['channel'] ?? 'stable' ) === $channel ) {
                return $cached;
            }
        }

        // Query GitHub API for all releases (to filter by channel).
        $release = $this->fetchBestRelease( $channel );

        if ( $release === null ) {
            // Cache the "up to date" result too.
            $this->cacheRelease( [], '0.0.0' );
            return null;
        }

        $currentVersion = $this->getCurrentVersion();
        $remoteVersion  = ltrim( $release['tag_name'] ?? '', 'v' );

        // Cache the result.
        $this->cacheRelease( $release, $remoteVersion );

        // Compare using PHP's version_compare (handles semver + pre-release).
        if ( version_compare( $remoteVersion, $currentVersion, '>' ) ) {
            $releaseChannel = self::versionChannel( $remoteVersion );
            $isMajor        = $this->isMajorUpdate( $currentVersion, $remoteVersion );

            return [
                'new_version'     => $remoteVersion,
                'version_label'   => self::versionLabel( $remoteVersion ),
                'current'         => $currentVersion,
                'is_major'        => $isMajor,
                'release_channel' => $releaseChannel,
                'channel'         => $channel,
                'changelog'       => $release['body'] ?? '',
                'html_url'        => $release['html_url'] ?? '',
                'published_at'    => $release['published_at'] ?? '',
                'download_url'    => $this->getZipDownloadUrl( $release ),
            ];
        }

        return null; // Up to date.
    }

    /**
     * Download and install an update.
     *
     * Flow:
     * 1. Verify PHP version
     * 2. Create backup of core/, admin/, templates/
     * 3. Download ZIP from GitHub
     * 4. Verify ZIP integrity (minimum size)
     * 5. Extract to temp directory
     * 6. Apply ONLY: core/, admin/, templates/, VERSION, index.php, .htaccess
     * 7. Run migrations if they exist
     * 8. Log the result
     *
     * NEVER touches: config/, data/, plugins/, public/assets/, backups/
     *
     * @param  string $downloadUrl GitHub release ZIP URL.
     * @return array  Result with success, from_version, to_version, error.
     */
    public function install( string $downloadUrl ): array
    {
        $fromVersion = $this->getCurrentVersion();

        // 1. Check PHP version.
        if ( version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
            return $this->result( false, $fromVersion, '', 'Requires PHP 8.1+. Current: ' . PHP_VERSION );
        }

        // 2. Check ZipArchive.
        if ( ! class_exists( 'ZipArchive' ) ) {
            return $this->result( false, $fromVersion, '', 'ZipArchive PHP extension is required.' );
        }

        // 3. Pre-update backup.
        $backupDir = $this->rootPath . '/backups/pre-update-' . $fromVersion . '-' . date( 'Ymd-His' );
        try {
            $this->createBackup( $backupDir );
        } catch ( \RuntimeException $e ) {
            return $this->result( false, $fromVersion, '', 'Backup failed: ' . $e->getMessage() );
        }

        // 4. Download ZIP.
        $tmpFile = sys_get_temp_dir() . '/klytos-update-' . bin2hex( random_bytes( 8 ) ) . '.zip';
        try {
            $this->downloadFile( $downloadUrl, $tmpFile );
        } catch ( \RuntimeException $e ) {
            return $this->result( false, $fromVersion, '', 'Download failed: ' . $e->getMessage() );
        }

        // 5. Extract and apply.
        $tmpDir = sys_get_temp_dir() . '/klytos-update-' . bin2hex( random_bytes( 8 ) );
        try {
            $this->extractAndApply( $tmpFile, $tmpDir );
        } catch ( \RuntimeException $e ) {
            // Rollback.
            $this->rollback( $backupDir );
            @unlink( $tmpFile );
            $this->removeDir( $tmpDir );

            return $this->result( false, $fromVersion, '', 'Install failed (rolled back): ' . $e->getMessage() );
        }

        // 6. Cleanup.
        $toVersion = $this->getCurrentVersion();
        @unlink( $tmpFile );
        $this->removeDir( $tmpDir );

        // 7. Run migrations.
        $this->runMigrations( $fromVersion, $toVersion );

        // 8. Clear the update cache so the admin shows the correct state.
        $this->clearCache();

        // 9. Log success.
        $this->addLogEntry( [
            'from'        => $fromVersion,
            'to'          => $toVersion,
            'date'        => Helpers::now(),
            'status'      => 'success',
            'backup_path' => basename( $backupDir ),
        ] );

        return $this->result( true, $fromVersion, $toVersion );
    }

    /**
     * Get the update log history.
     *
     * @return array List of update entries (newest first).
     */
    public function getLog(): array
    {
        try {
            $data = $this->storage->readFrom( $this->configPath, 'update_log.json.enc' );
            return $data['entries'] ?? [];
        } catch ( \RuntimeException $e ) {
            return [];
        }
    }

    // ─── GitHub API ──────────────────────────────────────────────────

    /**
     * Fetch the best matching release from GitHub for the given channel.
     *
     * Fetches the 20 most recent releases, filters by channel, and returns
     * the newest one that matches.
     *
     * @param  string $channel User's channel preference (stable, rc, beta).
     * @return array|null Best matching release or null.
     */
    private function fetchBestRelease( string $channel ): ?array
    {
        $ch = curl_init();
        curl_setopt_array( $ch, [
            CURLOPT_URL            => self::GITHUB_API_RELEASES . '?per_page=20',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/vnd.github+json',
                'User-Agent: Klytos-CMS/' . $this->getCurrentVersion(),
                'X-GitHub-Api-Version: 2022-11-28',
            ],
        ] );

        $response = curl_exec( $ch );
        $httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        if ( $httpCode !== 200 || empty( $response ) ) {
            return null;
        }

        $releases = json_decode( $response, true );
        if ( ! is_array( $releases ) ) {
            return null;
        }

        // Filter releases by channel and find the best (newest) match.
        $currentVersion = $this->getCurrentVersion();
        $bestRelease    = null;
        $bestVersion    = $currentVersion;

        foreach ( $releases as $release ) {
            // Skip drafts.
            if ( ! empty( $release['draft'] ) ) {
                continue;
            }

            $tagName = $release['tag_name'] ?? '';
            $version = ltrim( $tagName, 'v' );

            if ( empty( $version ) ) {
                continue;
            }

            // Check if this release's channel is acceptable.
            $releaseChannel = self::versionChannel( $version );
            if ( ! $this->isAcceptableForChannel( $releaseChannel, $channel ) ) {
                continue;
            }

            // Is this newer than what we've found so far?
            if ( version_compare( $version, $bestVersion, '>' ) ) {
                $bestVersion = $version;
                $bestRelease = $release;
            }
        }

        return $bestRelease;
    }

    /**
     * Get the ZIP download URL from a release.
     *
     * Prefers the first .zip asset attached to the release.
     * Falls back to the GitHub auto-generated zipball.
     *
     * @param  array  $release GitHub release data.
     * @return string Download URL.
     */
    private function getZipDownloadUrl( array $release ): string
    {
        // Check for a manually attached ZIP (preferred — curated package).
        $assets = $release['assets'] ?? [];
        foreach ( $assets as $asset ) {
            $name = strtolower( $asset['name'] ?? '' );
            if ( str_ends_with( $name, '.zip' ) && ! empty( $asset['browser_download_url'] ) ) {
                return $asset['browser_download_url'];
            }
        }

        // Fallback: GitHub auto-generated source zipball.
        return $release['zipball_url'] ?? '';
    }

    // ─── Cache ───────────────────────────────────────────────────────

    /**
     * Get cached release check result.
     *
     * @return array|null Cached update info or null if expired/missing.
     */
    private function getCachedRelease(): ?array
    {
        try {
            $cache = $this->storage->readFrom( $this->configPath, 'update_cache.json.enc' );
        } catch ( \RuntimeException $e ) {
            return null;
        }

        $cachedAt = $cache['cached_at'] ?? 0;
        if ( ( time() - $cachedAt ) > self::CACHE_TTL ) {
            return null; // Expired.
        }

        $currentVersion = $this->getCurrentVersion();
        $remoteVersion  = $cache['remote_version'] ?? '0.0.0';

        if ( version_compare( $remoteVersion, $currentVersion, '>' ) ) {
            return [
                'new_version'  => $remoteVersion,
                'current'      => $currentVersion,
                'is_major'     => $this->isMajorUpdate( $currentVersion, $remoteVersion ),
                'changelog'    => $cache['changelog'] ?? '',
                'html_url'     => $cache['html_url'] ?? '',
                'published_at' => $cache['published_at'] ?? '',
                'download_url' => $cache['download_url'] ?? '',
            ];
        }

        return null; // Up to date.
    }

    /**
     * Cache the release check result.
     */
    private function cacheRelease( array $release, string $remoteVersion ): void
    {
        $this->storage->writeTo( $this->configPath, 'update_cache.json.enc', [
            'cached_at'      => time(),
            'remote_version' => $remoteVersion,
            'changelog'      => $release['body'] ?? '',
            'html_url'       => $release['html_url'] ?? '',
            'published_at'   => $release['published_at'] ?? '',
            'download_url'   => $this->getZipDownloadUrl( $release ),
        ] );
    }

    /**
     * Clear the update cache (after a successful update).
     */
    private function clearCache(): void
    {
        try {
            $this->storage->writeTo( $this->configPath, 'update_cache.json.enc', [
                'cached_at'      => 0,
                'remote_version' => '0.0.0',
            ] );
        } catch ( \RuntimeException $e ) {
            // Ignore.
        }
    }

    // ─── Backup & Rollback ───────────────────────────────────────────

    /**
     * Create a pre-update backup.
     * Only backs up code directories, NEVER data.
     */
    private function createBackup( string $backupPath ): void
    {
        if ( ! is_dir( $backupPath ) ) {
            if ( ! mkdir( $backupPath, 0755, true ) ) {
                throw new \RuntimeException( 'Could not create backup directory.' );
            }
        }

        // Directories to backup (code only, never data).
        $dirs = [ 'core', 'admin', 'templates' ];
        foreach ( $dirs as $dir ) {
            $src = $this->rootPath . '/' . $dir;
            if ( is_dir( $src ) ) {
                $this->copyDir( $src, $backupPath . '/' . $dir );
            }
        }

        // Individual files.
        $files = [ 'VERSION', 'index.php', '.htaccess', 'install.php' ];
        foreach ( $files as $file ) {
            $src = $this->rootPath . '/' . $file;
            if ( file_exists( $src ) ) {
                copy( $src, $backupPath . '/' . $file );
            }
        }
    }

    /**
     * Rollback from a backup directory.
     */
    private function rollback( string $backupPath ): void
    {
        if ( ! is_dir( $backupPath ) ) {
            return;
        }

        $dirs = [ 'core', 'admin', 'templates' ];
        foreach ( $dirs as $dir ) {
            $src = $backupPath . '/' . $dir;
            if ( is_dir( $src ) ) {
                $this->copyDir( $src, $this->rootPath . '/' . $dir );
            }
        }

        $files = [ 'VERSION', 'index.php', '.htaccess', 'install.php' ];
        foreach ( $files as $file ) {
            $src = $backupPath . '/' . $file;
            if ( file_exists( $src ) ) {
                copy( $src, $this->rootPath . '/' . $file );
            }
        }

        $this->addLogEntry( [
            'from'   => 'rollback',
            'to'     => $this->getCurrentVersion(),
            'date'   => Helpers::now(),
            'status' => 'rollback',
        ] );
    }

    // ─── Download & Extract ──────────────────────────────────────────

    /**
     * Download a file.
     */
    private function downloadFile( string $url, string $dest ): void
    {
        $ch = curl_init();
        $fp = fopen( $dest, 'wb' );

        if ( ! $fp ) {
            throw new \RuntimeException( 'Cannot write to temporary file.' );
        }

        curl_setopt_array( $ch, [
            CURLOPT_URL            => $url,
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/octet-stream',
                'User-Agent: Klytos-CMS/' . $this->getCurrentVersion(),
            ],
        ] );

        curl_exec( $ch );
        $httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $error    = curl_error( $ch );

        curl_close( $ch );
        fclose( $fp );

        if ( $httpCode !== 200 || filesize( $dest ) < 1000 ) {
            @unlink( $dest );
            throw new \RuntimeException(
                'Download failed (HTTP ' . $httpCode . ').' . ( $error ? ' ' . $error : '' )
            );
        }
    }

    /**
     * Extract ZIP and apply ONLY safe directories.
     *
     * Safe:  core/, admin/, templates/, VERSION, index.php, .htaccess, install.php
     * NEVER: config/, data/, plugins/, public/assets/, backups/
     */
    private function extractAndApply( string $zipFile, string $tmpDir ): void
    {
        $zip = new \ZipArchive();
        if ( $zip->open( $zipFile ) !== true ) {
            throw new \RuntimeException( 'Could not open update package.' );
        }

        if ( ! mkdir( $tmpDir, 0755, true ) ) {
            $zip->close();
            throw new \RuntimeException( 'Could not create temporary directory.' );
        }

        $zip->extractTo( $tmpDir );
        $zip->close();

        // Find the root inside the ZIP.
        // GitHub zipball: klytos-main/ or klytos-v2.1.0/
        // Manual upload: klytos/ or directly the files
        $extractRoot = $this->findExtractRoot( $tmpDir );

        // Apply ONLY allowed directories.
        $allowedDirs = [ 'core', 'admin', 'templates' ];
        foreach ( $allowedDirs as $dir ) {
            $src = $extractRoot . '/' . $dir;
            if ( is_dir( $src ) ) {
                $this->copyDir( $src, $this->rootPath . '/' . $dir );
            }
        }

        // Apply allowed individual files.
        $allowedFiles = [ 'VERSION', 'index.php', '.htaccess', 'install.php' ];
        foreach ( $allowedFiles as $file ) {
            $src = $extractRoot . '/' . $file;
            if ( file_exists( $src ) ) {
                copy( $src, $this->rootPath . '/' . $file );
            }
        }

        // NEVER touch: config/, data/, plugins/, public/, backups/
    }

    /**
     * Find the actual root directory inside an extracted ZIP.
     *
     * GitHub creates a wrapper folder like "klytos-main/" or "klytos-2.1.0/".
     * This method finds the correct root regardless of nesting.
     */
    private function findExtractRoot( string $tmpDir ): string
    {
        $entries = array_diff( scandir( $tmpDir ), [ '.', '..' ] );

        // If there's exactly one directory and no files, go one level deeper.
        if ( count( $entries ) === 1 ) {
            $first = $tmpDir . '/' . reset( $entries );
            if ( is_dir( $first ) ) {
                // Check if this directory contains 'installer' (repo structure).
                $innerEntries = array_diff( scandir( $first ), [ '.', '..' ] );
                foreach ( $innerEntries as $entry ) {
                    $innerPath = $first . '/' . $entry;
                    // Look for the admin folder as a marker.
                    if ( is_dir( $innerPath . '/core' ) && is_dir( $innerPath . '/admin' ) ) {
                        return $innerPath;
                    }
                }
                // The wrapper directory itself might be the root.
                if ( is_dir( $first . '/core' ) && is_dir( $first . '/admin' ) ) {
                    return $first;
                }
                return $first;
            }
        }

        // No wrapper — files are directly in tmpDir.
        return $tmpDir;
    }

    // ─── Migrations ──────────────────────────────────────────────────

    /**
     * Run migration scripts if they exist.
     */
    private function runMigrations( string $fromVersion, string $toVersion ): void
    {
        $migrationDir = $this->rootPath . '/core/migrations';
        if ( ! is_dir( $migrationDir ) ) {
            return;
        }

        // Try exact migration file first.
        $from = str_replace( '.', '_', $fromVersion );
        $to   = str_replace( '.', '_', $toVersion );
        $file = $migrationDir . '/migrate_' . $from . '_to_' . $to . '.php';

        if ( file_exists( $file ) ) {
            try {
                require $file;
            } catch ( \Throwable $e ) {
                $this->addLogEntry( [
                    'from'   => $fromVersion,
                    'to'     => $toVersion,
                    'date'   => Helpers::now(),
                    'status' => 'migration_error',
                    'error'  => $e->getMessage(),
                ] );
            }
        }
    }

    // ─── Logging ─────────────────────────────────────────────────────

    /**
     * Add an entry to the update log.
     */
    private function addLogEntry( array $entry ): void
    {
        $log = [];
        try {
            $data = $this->storage->readFrom( $this->configPath, 'update_log.json.enc' );
            $log  = $data['entries'] ?? [];
        } catch ( \RuntimeException $e ) {
            // No log yet.
        }

        array_unshift( $log, $entry );
        $log = array_slice( $log, 0, self::MAX_LOG_ENTRIES );

        $this->storage->writeTo( $this->configPath, 'update_log.json.enc', [ 'entries' => $log ] );
    }

    // ─── Utilities ───────────────────────────────────────────────────

    /**
     * Build a standardized result array.
     */
    private function result( bool $success, string $from, string $to, string $error = '' ): array
    {
        return [
            'success'      => $success,
            'from_version' => $from,
            'to_version'   => $to,
            'error'        => $error,
        ];
    }

    /**
     * Determine if this is a major version update (e.g., 2.x → 3.x).
     */
    private function isMajorUpdate( string $from, string $to ): bool
    {
        $fromMajor = (int) explode( '.', $from )[0];
        $toMajor   = (int) explode( '.', $to )[0];
        return $toMajor > $fromMajor;
    }

    /**
     * Recursively copy a directory.
     */
    private function copyDir( string $src, string $dst ): void
    {
        if ( ! is_dir( $dst ) ) {
            mkdir( $dst, 0755, true );
        }

        $entries = scandir( $src );
        foreach ( $entries as $entry ) {
            if ( $entry === '.' || $entry === '..' ) {
                continue;
            }

            $srcPath = $src . '/' . $entry;
            $dstPath = $dst . '/' . $entry;

            if ( is_dir( $srcPath ) ) {
                $this->copyDir( $srcPath, $dstPath );
            } else {
                copy( $srcPath, $dstPath );
            }
        }
    }

    /**
     * Recursively remove a directory.
     */
    private function removeDir( string $dir ): void
    {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $entries = scandir( $dir );
        foreach ( $entries as $entry ) {
            if ( $entry === '.' || $entry === '..' ) {
                continue;
            }

            $path = $dir . '/' . $entry;
            if ( is_dir( $path ) ) {
                $this->removeDir( $path );
            } else {
                @unlink( $path );
            }
        }
        @rmdir( $dir );
    }
}
