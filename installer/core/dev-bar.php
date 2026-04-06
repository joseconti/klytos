<?php

/**
 * Klytos — Developer Bar (Collector)
 * Collects performance metrics, queries, hooks, and debug data during a request.
 *
 * Only instantiated when Developer Mode is active. Renders a persistent bar
 * at the bottom of the admin panel showing real-time metrics.
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

class DevBar
{
    /** @var self|null Singleton instance. */
    private static ?self $instance = null;

    /** @var float Request start time (microseconds). */
    private float $requestStart;

    /** @var array Storage/query operations. */
    private array $storageOps = [];

    /** @var array Hooks fired with timing data. */
    private array $hooksFired = [];

    /** @var array Custom timers registered by plugins. */
    private array $timers = [];

    /** @var array Assets loaded (CSS, JS). */
    private array $assets = [];

    /** @var array In-memory log entries for this request. */
    private array $logs = [];

    /** @var array Cache hit keys. */
    private array $cacheHits = [];

    /** @var array Cache miss keys. */
    private array $cacheMisses = [];

    /** @var array Deprecation warnings. */
    private array $deprecations = [];

    /** @var int Slow threshold in milliseconds. */
    private int $slowThreshold = 200;

    private function __construct()
    {
        $this->requestStart = $_SERVER['REQUEST_FLOAT_TIME'] ?? microtime( true );
    }

    public static function getInstance(): self
    {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set the slow-operation threshold.
     *
     * @param int $ms Milliseconds.
     */
    public function setSlowThreshold( int $ms ): void
    {
        $this->slowThreshold = $ms;
    }

    // ─── Registration Methods ────────────────────────────────────

    /**
     * Log a storage/query operation.
     */
    public function logStorageOp( string $operation, string $collection, float $duration, ?string $caller = null ): void
    {
        $this->storageOps[] = [
            'type'        => $operation,
            'collection'  => $collection,
            'duration_ms' => round( $duration * 1000, 2 ),
            'caller'      => $caller ?? $this->getCaller( 3 ),
        ];
    }

    /**
     * Log a hook execution.
     */
    public function logHook( string $name, string $type, int $callbackCount, float $duration ): void
    {
        $this->hooksFired[] = [
            'name'      => $name,
            'type'      => $type,
            'callbacks' => $callbackCount,
            'duration_ms' => round( $duration * 1000, 2 ),
        ];
    }

    /**
     * Log a custom timer.
     */
    public function logTimer( string $label, float $start, float $end ): void
    {
        $this->timers[] = [
            'label'       => $label,
            'duration_ms' => round( ( $end - $start ) * 1000, 2 ),
        ];
    }

    /**
     * Log an asset loaded in this request.
     */
    public function logAsset( string $type, string $path, int $sizeBytes = 0, ?string $source = null ): void
    {
        $this->assets[] = [
            'type'   => $type,
            'path'   => $path,
            'size'   => $sizeBytes,
            'source' => $source ?? 'core',
        ];
    }

    /**
     * Log a cache hit.
     */
    public function logCacheHit( string $key ): void
    {
        $this->cacheHits[] = $key;
    }

    /**
     * Log a cache miss.
     */
    public function logCacheMiss( string $key ): void
    {
        $this->cacheMisses[] = $key;
    }

    /**
     * Log a deprecation warning.
     */
    public function logDeprecation( string $message, ?string $caller = null ): void
    {
        $this->deprecations[] = [
            'message' => $message,
            'caller'  => $caller ?? $this->getCaller( 2 ),
        ];
    }

    /**
     * Log an in-memory debug message (shown in DevBar Logs tab).
     */
    public function log( string $level, string $message, array $context = [] ): void
    {
        $this->logs[] = [
            'level'   => $level,
            'message' => $message,
            'context' => $context,
            'time'    => round( ( microtime( true ) - $this->requestStart ) * 1000, 2 ),
        ];
    }

    // ─── Getters ─────────────────────────────────────────────────

    /** Execution time in milliseconds. */
    public function getExecutionTime(): float
    {
        return round( ( microtime( true ) - $this->requestStart ) * 1000, 1 );
    }

    /** Current memory usage in bytes. */
    public function getMemoryUsage(): int
    {
        return memory_get_usage( true );
    }

    /** Peak memory usage in bytes. */
    public function getPeakMemory(): int
    {
        return memory_get_peak_usage( true );
    }

    /** Formatted memory string. */
    public function getMemoryUsageFormatted(): string
    {
        return $this->formatBytes( $this->getMemoryUsage() );
    }

    /** Total storage operation count. */
    public function getQueryCount(): int
    {
        return count( $this->storageOps );
    }

    /** Total time spent in storage operations (ms). */
    public function getQueryTime(): float
    {
        $total = 0.0;
        foreach ( $this->storageOps as $op ) {
            $total += $op['duration_ms'];
        }
        return round( $total, 1 );
    }

    public function getQueries(): array
    {
        return $this->storageOps;
    }

    public function getHooksFired(): array
    {
        return $this->hooksFired;
    }

    public function getHookCount(): int
    {
        return count( $this->hooksFired );
    }

    public function getTimers(): array
    {
        return $this->timers;
    }

    public function getAssets(): array
    {
        return $this->assets;
    }

    public function getStorageOps(): array
    {
        return $this->storageOps;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    /** @return string[] */
    public function getIncludedFiles(): array
    {
        return get_included_files();
    }

    /** @return array{hits: int, misses: int} */
    public function getCacheStats(): array
    {
        return [
            'hits'   => count( $this->cacheHits ),
            'misses' => count( $this->cacheMisses ),
        ];
    }

    public function getDeprecations(): array
    {
        return $this->deprecations;
    }

    // ─── Serialisation ───────────────────────────────────────────

    /**
     * Export all collected data as an array (for JSON injection into the frontend).
     */
    public function toArray(): array
    {
        $storageBackend = 'file';
        try {
            $app = App::getInstance();
            $config = $app->getConfig();
            $storageBackend = $config['storage_driver'] ?? 'file';
        } catch ( \Throwable $e ) {
            // Fallback.
        }

        $cpuUser   = null;
        $cpuSystem = null;
        if ( function_exists( 'getrusage' ) ) {
            $usage = getrusage();
            if ( $usage !== false ) {
                $cpuUser   = ( $usage['ru_utime.tv_sec'] ?? 0 ) + ( ( $usage['ru_utime.tv_usec'] ?? 0 ) / 1e6 );
                $cpuSystem = ( $usage['ru_stime.tv_sec'] ?? 0 ) + ( ( $usage['ru_stime.tv_usec'] ?? 0 ) / 1e6 );
            }
        }

        return klytos_apply_filters( 'devbar.data', [
            'meta' => [
                'php_version'     => PHP_VERSION,
                'klytos_version'  => KLYTOS_VERSION,
                'storage_backend' => $storageBackend,
                'page'            => $_SERVER['SCRIPT_NAME'] ?? '',
                'timestamp'       => time(),
                'slow_threshold'  => $this->slowThreshold,
            ],
            'performance' => [
                'execution_time_ms'     => $this->getExecutionTime(),
                'memory_usage'          => $this->getMemoryUsage(),
                'memory_peak'           => $this->getPeakMemory(),
                'memory_formatted'      => $this->getMemoryUsageFormatted(),
                'memory_peak_formatted' => $this->formatBytes( $this->getPeakMemory() ),
                'cpu_user_time'         => $cpuUser,
                'cpu_system_time'       => $cpuSystem,
                'included_files_count'  => count( $this->getIncludedFiles() ),
            ],
            'storage' => [
                'total_ops'     => $this->getQueryCount(),
                'total_time_ms' => $this->getQueryTime(),
                'operations'    => $this->storageOps,
            ],
            'hooks' => [
                'total_fired' => $this->getHookCount(),
                'fired'       => $this->hooksFired,
            ],
            'assets'       => $this->assets,
            'logs'         => $this->logs,
            'deprecations' => $this->deprecations,
            'cache'        => $this->getCacheStats(),
            'timers'       => $this->timers,
        ] );
    }

    // ─── Private Helpers ─────────────────────────────────────────

    /**
     * Format bytes to human-readable string.
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
     * Get a simplified caller string from the backtrace.
     */
    private function getCaller( int $depth = 2 ): string
    {
        $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 1 );
        $frame = $trace[$depth] ?? $trace[count( $trace ) - 1] ?? null;
        if ( $frame === null ) {
            return 'unknown';
        }
        $file = basename( $frame['file'] ?? '' );
        $line = $frame['line'] ?? 0;
        return "{$file}:{$line}";
    }
}
