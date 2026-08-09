<?php

/**
 * Klytos — Logger
 * PSR-3 compatible logging system with daily rotation and per-plugin control.
 *
 * Logs are written to a secret directory inside data/ with a random name
 * (e.g. data/logs-a1b2c3d4e5f6/). Files are rotated daily and split when
 * they exceed the maximum file size (default 5 MB).
 *
 * Logging is conditional:
 * - Developer Mode must be globally active (Settings > Developer).
 * - For plugin sources, the plugin must declare "Logs: true" in its header
 *   AND the admin must have enabled logging for that plugin.
 *
 * Plugins write logs via the global helpers:
 *   klytos_log('info', 'Message', ['ctx' => 'data'], 'my-plugin');
 *   klytos_log_error('Something broke', [], 'my-plugin');
 *
 * @package Klytos
 * @since   0.16.0
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

class Logger
{
    /** @var string Absolute path to the data/ directory. */
    private string $dataPath;

    /** @var SiteConfig Site configuration (for developer mode check). */
    private SiteConfig $siteConfig;

    /** @var PluginLoader Plugin loader (for per-plugin log state). */
    private PluginLoader $pluginLoader;

    /** @var string Cached absolute path to the logs directory. */
    private string $logsDir = '';

    /** @var int Default maximum log file size in bytes (5 MB). */
    private const DEFAULT_MAX_FILE_SIZE = 5 * 1024 * 1024;

    /** @var string Config file name for persisting log directory name. */
    private const CONFIG_FILE = 'logger-config.json.enc';

    /** @var string Log file prefix. */
    private const FILE_PREFIX = 'debug-';

    /** @var string Log file extension. */
    private const FILE_EXT = '.log';

    /**
     * PSR-3 log levels in severity order (highest first).
     *
     * @var array<int, string>
     */
    public const LEVELS = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
    ];

    /** @var StorageInterface Storage backend for reading/writing config. */
    private StorageInterface $storage;

    /**
     * Constructor.
     *
     * @param string           $dataPath     Absolute path to the data/ directory.
     * @param SiteConfig       $siteConfig   Site configuration.
     * @param PluginLoader     $pluginLoader Plugin loader.
     * @param StorageInterface $storage      Storage backend.
     */
    public function __construct(
        string $dataPath,
        SiteConfig $siteConfig,
        PluginLoader $pluginLoader,
        StorageInterface $storage
    ) {
        $this->dataPath     = rtrim( $dataPath, '/' );
        $this->siteConfig   = $siteConfig;
        $this->pluginLoader = $pluginLoader;
        $this->storage      = $storage;
    }

    // ─── Writing ─────────────────────────────────────────────────

    /**
     * Write a log entry.
     *
     * Logs are only written when Developer Mode is active. For plugin
     * sources, the plugin must also have logging enabled via the admin.
     *
     * @param string $level   PSR-3 level (emergency … debug).
     * @param string $message Human-readable message.
     * @param array  $context Additional context (serialized as JSON).
     * @param string $source  Source identifier: 'core' or a plugin ID.
     */
    public function write( string $level, string $message, array $context = [], string $source = 'core' ): void
    {
        // ── Condition 1: Developer Mode must be active (cheap check first) ──
        $devMode = $this->siteConfig->getValue( 'developer.developer_mode', false );
        if ( ! $devMode ) {
            return;
        }

        // ── Condition 2: Plugin source must have logging enabled ──
        if ( $source !== 'core' && ! $this->isPluginLoggingEnabled( $source ) ) {
            return;
        }

        // ── Filter: allow plugins to modify or suppress the entry ──
        $entry = klytos_apply_filters( 'logger.before_write', [
            'level'   => $level,
            'message' => $message,
            'context' => $context,
            'source'  => $source,
        ] );

        if ( $entry === null ) {
            return; // Suppressed by filter.
        }

        // Unpack (filter may have modified values).
        $level   = $entry['level']   ?? $level;
        $message = $entry['message'] ?? $message;
        $context = $entry['context'] ?? $context;
        $source  = $entry['source']  ?? $source;

        // ── Validate level ──
        if ( ! in_array( $level, self::LEVELS, true ) ) {
            $level = 'info';
        }

        // ── Resolve file path with rotation ──
        $logFile = $this->resolveLogFile( klytos_gmdate( 'Y-m-d' ) );

        // ── Format the line ──
        $line = klytos_apply_filters( 'logger.log_format', sprintf(
            "[%s] [%s] [%s] %s%s\n",
            klytos_gmdate( 'Y-m-d H:i:s' ),
            strtoupper( $level ),
            $source,
            $message,
            ! empty( $context ) ? ' ' . json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : ''
        ), $entry );

        // ── Write atomically ──
        file_put_contents( $logFile, $line, FILE_APPEND | LOCK_EX );

        // ── Action: notify after write ──
        klytos_do_action( 'logger.after_write', $entry, $logFile );
    }

    /**
     * Write a critical/fatal log entry unconditionally.
     *
     * Unlike write(), this method bypasses Developer Mode and plugin checks.
     * It is intended for fatal errors and shutdown handlers that MUST be
     * logged regardless of configuration.
     *
     * @param string $level   PSR-3 level (typically 'critical' or 'emergency').
     * @param string $message Human-readable message.
     * @param array  $context Additional context (serialized as JSON).
     * @param string $source  Source identifier.
     */
    public function writeAlways( string $level, string $message, array $context = [], string $source = 'core' ): void
    {
        if ( ! in_array( $level, self::LEVELS, true ) ) {
            $level = 'critical';
        }

        $logFile = $this->resolveLogFile( klytos_gmdate( 'Y-m-d' ) );

        $line = sprintf(
            "[%s] [%s] [%s] %s%s\n",
            klytos_gmdate( 'Y-m-d H:i:s' ),
            strtoupper( $level ),
            $source,
            $message,
            ! empty( $context ) ? ' ' . json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : ''
        );

        file_put_contents( $logFile, $line, FILE_APPEND | LOCK_EX );
    }

    // ─── Reading ─────────────────────────────────────────────────

    /**
     * List all log files with metadata.
     *
     * @return array<int, array{name: string, size: int, size_formatted: string, date: string, modified: int}>
     */
    public function listLogFiles(): array
    {
        $dir = $this->getLogsDir();
        if ( ! is_dir( $dir ) ) {
            return [];
        }

        $files  = [];
        $handle = opendir( $dir );
        if ( $handle === false ) {
            return [];
        }

        while ( ( $entry = readdir( $handle ) ) !== false ) {
            if ( $entry === '.' || $entry === '..' || $entry === '.htaccess' ) {
                continue;
            }

            $filePath = $dir . '/' . $entry;
            if ( ! is_file( $filePath ) || ! str_ends_with( $entry, self::FILE_EXT ) ) {
                continue;
            }

            $size = filesize( $filePath );
            $files[] = [
                'name'           => $entry,
                'size'           => $size,
                'size_formatted' => $this->formatBytes( $size ),
                'date'           => $this->extractDateFromFilename( $entry ),
                'modified'       => filemtime( $filePath ),
            ];
        }
        closedir( $handle );

        // Sort by modification time, newest first.
        usort( $files, fn( array $a, array $b ): int => $b['modified'] <=> $a['modified'] );

        return klytos_apply_filters( 'logger.log_files', $files );
    }

    /**
     * Read lines from a log file.
     *
     * A file that exists but cannot be opened returns an empty list, exactly
     * like an empty one. It used to raise a TypeError instead: `file()` answers
     * `false` on a failed open and the count that followed it was handed that
     * `false`, so an unreadable log took the whole request down rather than
     * producing a state the caller could render. Callers that must tell the two
     * apart ask {@see isLogFileReadable()} — "no lines" alone cannot.
     *
     * @param  string $filename Log file name (basename only, sanitized).
     * @param  int    $offset   Number of lines to skip from the start.
     * @param  int    $limit    Maximum lines to return (0 = all).
     * @return array<int, string> Lines from the file; empty if it is missing,
     *                            empty, unreadable, or the name does not resolve.
     */
    public function readLogFile( string $filename, int $offset = 0, int $limit = 500 ): array
    {
        $filePath = $this->safeFilePath( $filename );
        if ( $filePath === null || ! file_exists( $filePath ) || ! is_readable( $filePath ) ) {
            return [];
        }

        $lines = file( $filePath, FILE_IGNORE_NEW_LINES );
        if ( $lines === false ) {
            // is_readable() passed and the open still failed — a race, or a
            // mode the check cannot see through. Same answer, no fatal.
            return [];
        }

        return array_slice( $lines, $offset, $limit > 0 ? $limit : null );
    }

    /**
     * Whether a log file exists and can actually be opened for reading.
     *
     * The log screen has to distinguish "this file is empty" from "this file
     * cannot be read" — `template-console-stream.md` §2 specifies a different
     * state, and a different sentence, for each — and {@see readLogFile()}
     * answers both with an empty list. This is the question that separates
     * them.
     *
     * It lives here rather than in the screen because answering it means
     * resolving the filename to a path inside the logs directory, and that
     * resolution is a security boundary (traversal refusal, extension and
     * prefix validation). A second copy of it in a page would be a second
     * implementation of the same rule, free to drift from this one.
     *
     * @param  string $filename Log file name (basename only, sanitized).
     * @return bool   True only if the file resolves, exists, and is readable.
     */
    public function isLogFileReadable( string $filename ): bool
    {
        $filePath = $this->safeFilePath( $filename );

        return $filePath !== null && is_file( $filePath ) && is_readable( $filePath );
    }

    /**
     * Get the total line count for a log file.
     *
     * @param  string $filename Log file name.
     * @return int
     */
    public function countLines( string $filename ): int
    {
        $filePath = $this->safeFilePath( $filename );
        if ( $filePath === null || ! file_exists( $filePath ) ) {
            return 0;
        }

        $count = 0;
        $fh    = fopen( $filePath, 'r' );
        if ( $fh === false ) {
            return 0;
        }
        while ( ! feof( $fh ) ) {
            $count += substr_count( fread( $fh, 8192 ), "\n" );
        }
        fclose( $fh );

        return $count;
    }

    /**
     * Delete a single log file.
     *
     * @param  string $filename Log file name (basename only).
     * @return bool   True if deleted.
     */
    public function deleteLogFile( string $filename ): bool
    {
        $filePath = $this->safeFilePath( $filename );
        if ( $filePath === null || ! file_exists( $filePath ) ) {
            return false;
        }

        klytos_do_action( 'logger.before_delete', $filename );

        return @unlink( $filePath );
    }

    /**
     * Delete all log files.
     *
     * @return int Number of files deleted.
     */
    public function deleteAllLogFiles(): int
    {
        $files   = $this->listLogFiles();
        $deleted = 0;

        foreach ( $files as $file ) {
            if ( $this->deleteLogFile( $file['name'] ) ) {
                $deleted++;
            }
        }

        klytos_do_action( 'logger.after_delete_all', $deleted );

        return $deleted;
    }

    /**
     * Get unique dates that have log files.
     *
     * @return array<int, string> Dates in Y-m-d format, newest first.
     */
    public function getLogDates(): array
    {
        $files = $this->listLogFiles();
        $dates = [];

        foreach ( $files as $file ) {
            if ( ! empty( $file['date'] ) && ! in_array( $file['date'], $dates, true ) ) {
                $dates[] = $file['date'];
            }
        }

        rsort( $dates );

        return $dates;
    }

    /**
     * Search within a log file by query string and optional level filter.
     *
     * @param  string      $filename Log file name.
     * @param  string      $query    Text to search for (case-insensitive).
     * @param  string|null $level    Optional PSR-3 level filter.
     * @return array<int, string> Matching lines.
     */
    public function searchLogs( string $filename, string $query = '', ?string $level = null ): array
    {
        $lines   = $this->readLogFile( $filename, 0, 0 );
        $results = [];

        foreach ( $lines as $line ) {
            // Level filter.
            if ( $level !== null && $level !== '' ) {
                if ( ! str_contains( $line, '[' . strtoupper( $level ) . ']' ) ) {
                    continue;
                }
            }

            // Text search.
            if ( $query !== '' && stripos( $line, $query ) === false ) {
                continue;
            }

            $results[] = $line;
        }

        return $results;
    }

    // ─── Plugin Log State ────────────────────────────────────────

    /**
     * Check if logging is enabled for a specific plugin.
     *
     * @param  string $pluginId Plugin identifier.
     * @return bool
     */
    public function isPluginLoggingEnabled( string $pluginId ): bool
    {
        return $this->pluginLoader->isLogsEnabled( $pluginId );
    }

    // ─── Directory Management ────────────────────────────────────

    /**
     * Get the absolute path to the logs directory.
     *
     * On first call, reads the directory name from encrypted config.
     * If no config exists, generates a new random directory name,
     * creates the directory, and persists the name.
     *
     * @return string Absolute path to the logs directory.
     */
    public function getLogsDir(): string
    {
        if ( $this->logsDir !== '' ) {
            return $this->logsDir;
        }

        // Read from encrypted config.
        $config = $this->readConfig();
        if ( ! empty( $config['logs_dir_name'] ) ) {
            $dir = $this->dataPath . '/' . $config['logs_dir_name'];
            if ( is_dir( $dir ) ) {
                $this->logsDir = $dir;
                return $dir;
            }
        }

        // Generate new random directory.
        $dirName = 'logs-' . bin2hex( random_bytes( 6 ) ); // 12 hex chars.
        $dir     = $this->dataPath . '/' . $dirName;

        Helpers::ensureWritableDir( $dir );

        // Write .htaccess to deny web access.
        file_put_contents( $dir . '/.htaccess', "Deny from all\n", LOCK_EX );

        // Persist config.
        $this->writeConfig( [ 'logs_dir_name' => $dirName ] );
        $this->logsDir = $dir;

        return $dir;
    }

    // ─── Private Helpers ─────────────────────────────────────────

    /**
     * Resolve the log file path for a given date, applying size-based rotation.
     *
     * @param  string $date Date in Y-m-d format.
     * @return string Absolute file path.
     */
    private function resolveLogFile( string $date ): string
    {
        $dir     = $this->getLogsDir();
        $maxSize = (int) klytos_apply_filters( 'logger.max_file_size', self::DEFAULT_MAX_FILE_SIZE );
        $base    = $dir . '/' . self::FILE_PREFIX . $date . self::FILE_EXT;

        if ( ! file_exists( $base ) || filesize( $base ) < $maxSize ) {
            return $base;
        }

        $part = 2;
        while ( true ) {
            $file = $dir . '/' . self::FILE_PREFIX . $date . '-' . $part . self::FILE_EXT;
            if ( ! file_exists( $file ) || filesize( $file ) < $maxSize ) {
                return $file;
            }
            $part++;
        }
    }

    /**
     * Validate and return the full path for a log filename.
     *
     * Prevents directory traversal by stripping paths and validating extension.
     *
     * @param  string $filename Raw filename input.
     * @return string|null Safe absolute path, or null if invalid.
     */
    private function safeFilePath( string $filename ): ?string
    {
        // Strip any directory components.
        $filename = basename( $filename );

        // Must be a .log file.
        if ( ! str_ends_with( $filename, self::FILE_EXT ) ) {
            return null;
        }

        // Must start with the expected prefix.
        if ( ! str_starts_with( $filename, self::FILE_PREFIX ) ) {
            return null;
        }

        // Only allow safe characters.
        if ( ! preg_match( '/^debug-\d{4}-\d{2}-\d{2}(-\d+)?\.log$/', $filename ) ) {
            return null;
        }

        $filePath = $this->getLogsDir() . '/' . $filename;

        // Final safety: ensure file is inside the logs directory.
        $realDir  = realpath( $this->getLogsDir() );
        $realFile = realpath( $filePath );
        if ( $realDir === false ) {
            return null;
        }
        // For new files that don't exist yet, check the dirname.
        if ( $realFile === false ) {
            return $filePath; // File doesn't exist yet, but path is validated.
        }
        if ( ! str_starts_with( $realFile, $realDir ) ) {
            return null; // Directory traversal attempt.
        }

        return $filePath;
    }

    /**
     * Extract the date from a log filename.
     *
     * @param  string $filename e.g. "debug-2026-04-01.log" or "debug-2026-04-01-2.log"
     * @return string Date in Y-m-d format, or empty string.
     */
    private function extractDateFromFilename( string $filename ): string
    {
        if ( preg_match( '/^debug-(\d{4}-\d{2}-\d{2})/', $filename, $m ) ) {
            return $m[1];
        }
        return '';
    }

    /**
     * Format bytes to a human-readable string.
     *
     * @param  int $bytes
     * @return string e.g. "4.2 KB", "1.3 MB"
     */
    private function formatBytes( int $bytes ): string
    {
        if ( $bytes === 0 ) {
            return '0 B';
        }

        $units = [ 'B', 'KB', 'MB', 'GB' ];
        $i     = (int) floor( log( $bytes, 1024 ) );
        $i     = min( $i, count( $units ) - 1 );

        return round( $bytes / pow( 1024, $i ), 1 ) . ' ' . $units[$i];
    }

    /**
     * Read the logger config from encrypted storage.
     *
     * @return array Config data.
     */
    private function readConfig(): array
    {
        try {
            return $this->storage->read( self::CONFIG_FILE );
        } catch ( \RuntimeException $e ) {
            return [];
        }
    }

    /**
     * Write the logger config to encrypted storage.
     *
     * @param array $data Config data to persist.
     */
    private function writeConfig( array $data ): void
    {
        $this->storage->write( self::CONFIG_FILE, $data );
    }
}
