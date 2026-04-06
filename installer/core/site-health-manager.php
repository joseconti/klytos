<?php

/**
 * Klytos — Site Health Manager
 * Comprehensive system diagnostics: PHP, security, storage, build, performance.
 *
 * @package Klytos
 * @since   0.18.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class SiteHealthManager
{
    /** @var App Application instance. */
    private App $app;

    public function __construct( App $app )
    {
        $this->app = $app;
    }

    /**
     * Run all health checks and return a full report.
     *
     * @return array Report with 'checks' array and overall 'score' (0-100).
     */
    public function runAll(): array
    {
        $checks = [];

        $checks = array_merge( $checks, $this->checkPhp() );
        $checks = array_merge( $checks, $this->checkSecurity() );
        $checks = array_merge( $checks, $this->checkStorage() );
        $checks = array_merge( $checks, $this->checkBuild() );
        $checks = array_merge( $checks, $this->checkPerformance() );

        // Allow plugins to add their own checks.
        $checks = klytos_apply_filters( 'site_health.checks', $checks );

        // Calculate score.
        $total = count( $checks );
        $good  = 0;
        foreach ( $checks as $check ) {
            if ( ( $check['status'] ?? '' ) === 'good' ) {
                $good++;
            }
        }
        $score = $total > 0 ? (int) round( ( $good / $total ) * 100 ) : 100;

        return [
            'score'   => $score,
            'checks'  => $checks,
            'summary' => [
                'good'     => $good,
                'warning'  => count( array_filter( $checks, fn( $c ) => ( $c['status'] ?? '' ) === 'warning' ) ),
                'critical' => count( array_filter( $checks, fn( $c ) => ( $c['status'] ?? '' ) === 'critical' ) ),
                'total'    => $total,
            ],
        ];
    }

    /**
     * PHP environment checks.
     */
    private function checkPhp(): array
    {
        $checks = [];

        // PHP version.
        $phpVersion = PHP_VERSION;
        $checks[] = [
            'category'       => 'php',
            'label'          => 'PHP Version',
            'status'         => version_compare( $phpVersion, '8.1.0', '>=' ) ? 'good' : 'critical',
            'value'          => $phpVersion,
            'recommendation' => 'PHP 8.1 or higher is required. PHP 8.3+ is recommended.',
        ];

        // Required extensions.
        $required = ['openssl', 'json', 'mbstring', 'session', 'curl', 'fileinfo'];
        foreach ( $required as $ext ) {
            $loaded = extension_loaded( $ext );
            $checks[] = [
                'category'       => 'php',
                'label'          => 'Extension: ' . $ext,
                'status'         => $loaded ? 'good' : 'critical',
                'value'          => $loaded ? 'Loaded' : 'Missing',
                'recommendation' => $loaded ? '' : 'Install the ' . $ext . ' PHP extension.',
            ];
        }

        // Memory limit.
        $memoryLimit = ini_get( 'memory_limit' );
        $memoryBytes = $this->parseBytes( $memoryLimit );
        $checks[] = [
            'category'       => 'php',
            'label'          => 'Memory Limit',
            'status'         => $memoryBytes >= 128 * 1024 * 1024 ? 'good' : 'warning',
            'value'          => $memoryLimit,
            'recommendation' => 'At least 128M recommended for large sites.',
        ];

        // Upload max size.
        $uploadMax = ini_get( 'upload_max_filesize' );
        $uploadBytes = $this->parseBytes( $uploadMax );
        $checks[] = [
            'category'       => 'php',
            'label'          => 'Upload Max Filesize',
            'status'         => $uploadBytes >= 8 * 1024 * 1024 ? 'good' : 'warning',
            'value'          => $uploadMax,
            'recommendation' => 'At least 8M recommended for media uploads.',
        ];

        return $checks;
    }

    /**
     * Security checks.
     */
    private function checkSecurity(): array
    {
        $checks = [];

        // HTTPS.
        $isHttps = ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' )
                || ( ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) === 'https' )
                || ( ( $_SERVER['SERVER_PORT'] ?? '' ) === '443' );
        $checks[] = [
            'category'       => 'security',
            'label'          => 'HTTPS',
            'status'         => $isHttps ? 'good' : 'warning',
            'value'          => $isHttps ? 'Active' : 'Not detected',
            'recommendation' => 'HTTPS is strongly recommended for all production sites.',
        ];

        // Encryption key exists.
        $keyExists = file_exists( $this->app->getConfigPath() . '/.encryption_key' );
        $checks[] = [
            'category'       => 'security',
            'label'          => 'Encryption Key',
            'status'         => $keyExists ? 'good' : 'critical',
            'value'          => $keyExists ? 'Present' : 'Missing',
            'recommendation' => 'The encryption key is essential for data security.',
        ];

        // Config directory permissions.
        $configPerms = substr( sprintf( '%o', fileperms( $this->app->getConfigPath() ) ), -3 );
        $configSafe  = in_array( $configPerms, ['700', '750', '755'], true );
        $checks[] = [
            'category'       => 'security',
            'label'          => 'Config Directory Permissions',
            'status'         => $configSafe ? 'good' : 'warning',
            'value'          => $configPerms,
            'recommendation' => 'Config directory should be 750 or more restrictive.',
        ];

        // Data directory .htaccess protection.
        $htaccessExists = file_exists( $this->app->getDataPath() . '/.htaccess' );
        $checks[] = [
            'category'       => 'security',
            'label'          => 'Data Directory Protection',
            'status'         => $htaccessExists ? 'good' : 'critical',
            'value'          => $htaccessExists ? '.htaccess present' : '.htaccess missing',
            'recommendation' => 'The data/ directory must be protected from direct web access.',
        ];

        return $checks;
    }

    /**
     * Storage checks.
     */
    private function checkStorage(): array
    {
        $checks = [];

        // Data directory writable.
        $dataWritable = is_writable( $this->app->getDataPath() );
        $checks[] = [
            'category'       => 'storage',
            'label'          => 'Data Directory Writable',
            'status'         => $dataWritable ? 'good' : 'critical',
            'value'          => $dataWritable ? 'Yes' : 'No',
            'recommendation' => 'The data/ directory must be writable for storage operations.',
        ];

        // Disk space.
        $freeSpace = @disk_free_space( $this->app->getRootPath() );
        if ( $freeSpace !== false ) {
            $freeSpaceMb = round( $freeSpace / ( 1024 * 1024 ) );
            $checks[] = [
                'category'       => 'storage',
                'label'          => 'Free Disk Space',
                'status'         => $freeSpaceMb > 100 ? 'good' : ( $freeSpaceMb > 20 ? 'warning' : 'critical' ),
                'value'          => $freeSpaceMb . ' MB',
                'recommendation' => 'At least 100 MB free space recommended.',
            ];
        }

        // Page count sanity check.
        $pageCount = $this->app->getPages()->count();
        $checks[] = [
            'category'       => 'storage',
            'label'          => 'Total Pages',
            'status'         => 'good',
            'value'          => (string) $pageCount,
            'recommendation' => '',
        ];

        return $checks;
    }

    /**
     * Build checks.
     */
    private function checkBuild(): array
    {
        $checks = [];

        // Last build timestamp.
        $lastBuild = $this->app->getSiteConfig()->getValue( 'last_build_at', '' );
        $hasBuilt  = !empty( $lastBuild );
        $checks[] = [
            'category'       => 'build',
            'label'          => 'Last Build',
            'status'         => $hasBuilt ? 'good' : 'warning',
            'value'          => $hasBuilt ? $lastBuild : 'Never built',
            'recommendation' => $hasBuilt ? '' : 'Run a full site build to generate the static site.',
        ];

        // Output directory exists.
        $outputExists = is_dir( dirname( $this->app->getRootPath() ) );
        $checks[] = [
            'category'       => 'build',
            'label'          => 'Output Directory',
            'status'         => $outputExists ? 'good' : 'critical',
            'value'          => $outputExists ? 'Exists' : 'Missing',
            'recommendation' => 'The web root directory must exist for the build engine.',
        ];

        // sitemap.xml exists.
        $sitemapExists = file_exists( dirname( $this->app->getRootPath() ) . '/sitemap.xml' );
        $checks[] = [
            'category'       => 'build',
            'label'          => 'Sitemap',
            'status'         => $sitemapExists ? 'good' : 'warning',
            'value'          => $sitemapExists ? 'Generated' : 'Missing',
            'recommendation' => 'Run a site build to generate sitemap.xml for SEO.',
        ];

        return $checks;
    }

    /**
     * Performance checks.
     */
    private function checkPerformance(): array
    {
        $checks = [];

        // OPcache.
        $opcacheEnabled = function_exists( 'opcache_get_status' ) && @opcache_get_status( false ) !== false;
        $checks[] = [
            'category'       => 'performance',
            'label'          => 'OPcache',
            'status'         => $opcacheEnabled ? 'good' : 'warning',
            'value'          => $opcacheEnabled ? 'Enabled' : 'Disabled',
            'recommendation' => 'OPcache significantly improves PHP performance.',
        ];

        // Cache driver.
        $cacheConfig = $this->app->getSiteConfig()->getValue( 'cache', [] );
        $cacheDriver = $cacheConfig['driver'] ?? 'file';
        $checks[] = [
            'category'       => 'performance',
            'label'          => 'Cache Driver',
            'status'         => $cacheDriver !== 'file' ? 'good' : 'warning',
            'value'          => ucfirst( $cacheDriver ),
            'recommendation' => $cacheDriver === 'file'
                ? 'Consider using Redis or APCu for better cache performance.'
                : '',
        ];

        // Klytos version.
        $checks[] = [
            'category'       => 'performance',
            'label'          => 'Klytos Version',
            'status'         => 'good',
            'value'          => KLYTOS_VERSION,
            'recommendation' => '',
        ];

        return $checks;
    }

    /**
     * Parse a PHP ini size value to bytes.
     *
     * @param  string $value Value like '128M', '1G', '256K'.
     * @return int    Size in bytes.
     */
    private function parseBytes( string $value ): int
    {
        $value = trim( $value );
        $num   = (int) $value;
        $unit  = strtolower( substr( $value, -1 ) );

        switch ( $unit ) {
            case 'g':
                return $num * 1024 * 1024 * 1024;
            case 'm':
                return $num * 1024 * 1024;
            case 'k':
                return $num * 1024;
            default:
                return $num;
        }
    }
}
