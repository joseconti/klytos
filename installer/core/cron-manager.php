<?php

/**
 * Klytos — Pseudo-Cron Manager
 * Executes scheduled tasks on admin page loads (no real cron daemon needed).
 *
 * Since Klytos runs on shared hosting without access to system cron,
 * scheduled tasks are executed "piggyback" on admin panel requests.
 * Each time an admin page loads, the CronManager checks if any tasks
 * are due and executes them.
 *
 * Core tasks:
 * - Clean old analytics data (daily, respects retention period).
 * - Prune page version history (daily, keeps last N versions).
 * - Clean old audit log entries (daily, respects retention period).
 * - Clean old webhook delivery logs (daily).
 * - Clean expired rate limit entries (hourly).
 *
 * Plugins can register their own cron tasks via the 'cron.tasks' filter.
 * Each task defines: id, callback, interval ('hourly', 'daily', 'weekly', 'monthly').
 *
 * Execution is serialized: only one task runs at a time. A lock file prevents
 * concurrent execution from multiple simultaneous admin requests.
 *
 * Storage: cron state in data/cron_state.json.enc
 *
 * @package Klytos
 * @since   1.0.0
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

class CronManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string State file name (tracks last run time per task). */
    private const STATE_FILE = 'cron_state.json.enc';

    /** @var string Lock file to prevent concurrent execution. */
    private const LOCK_FILE = '.cron.lock';

    /** @var int Maximum execution time in seconds before giving up the lock. */
    private const MAX_EXECUTION_TIME = 30;

    /**
     * Interval definitions in seconds.
     * @var array<string, int>
     */
    private const INTERVALS = [
        'hourly'  => 3600,
        'daily'   => 86400,
        'weekly'  => 604800,
        'monthly' => 2592000,
    ];

    /**
     * @param StorageInterface $storage Storage backend instance.
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /** @var bool Whether migration to ActionScheduler has been attempted. */
    private bool $migrated = false;

    /**
     * Run all due tasks.
     *
     * This is the main entry point, called from admin/bootstrap.php on every
     * admin page load. It's designed to be fast when no tasks are due:
     * 1. Read state (one storage read).
     * 2. Check timestamps (pure math, no I/O).
     * 3. Only execute tasks that are actually due.
     *
     * A lock file prevents concurrent execution from multiple admin requests.
     *
     * After running legacy tasks, migrates core tasks to the ActionScheduler.
     *
     * @return array Results: ['executed' => [...task IDs...], 'errors' => [...]]
     */
    public function runDueTasks(): array
    {
        // Acquire lock to prevent concurrent execution.
        $lockPath = $this->storage->getDataDir() . '/' . self::LOCK_FILE;
        $lock     = $this->acquireLock($lockPath);

        if ($lock === null) {
            // Another request is already running cron tasks.
            return ['executed' => [], 'errors' => [], 'skipped' => 'lock_held'];
        }

        try {
            $state     = $this->getState();
            $tasks     = $this->getAllTasks();
            $now       = time();
            $executed  = [];
            $errors    = [];

            foreach ($tasks as $task) {
                $taskId   = $task['id'] ?? '';
                $interval = self::INTERVALS[$task['interval'] ?? 'daily'] ?? 86400;
                $lastRun  = $state[$taskId] ?? 0;

                // Is this task due?
                if (($now - $lastRun) < $interval) {
                    continue; // Not yet.
                }

                // Execute the task.
                try {
                    $callback = $task['callback'] ?? null;
                    if (is_callable($callback)) {
                        $callback();
                        $executed[] = $taskId;
                    }
                } catch (\Throwable $e) {
                    $errors[$taskId] = $e->getMessage();
                    error_log("Klytos Cron: task '{$taskId}' failed: " . $e->getMessage());
                }

                // Update last run time (even on failure, to avoid retry storm).
                $state[$taskId] = $now;
            }

            // Save updated state.
            $this->saveState($state);

            // Fire action so plugins know cron ran.
            klytos_do_action('cron.run', $executed, $errors);

            // Migrate core tasks to ActionScheduler (once).
            $this->migrateToScheduler();

            return ['executed' => $executed, 'errors' => $errors];

        } finally {
            $this->releaseLock($lock, $lockPath);
        }
    }

    /**
     * Get all registered cron tasks (core + plugin-registered).
     *
     * Core tasks are defined here. Plugins add theirs via 'cron.tasks' filter.
     *
     * @return array Array of task definitions.
     */
    private function getAllTasks(): array
    {
        $coreTasks = [
            [
                'id'       => 'analytics_prune',
                'callback' => function (): void {
                    $analytics = new AnalyticsManager($this->storage);
                    $analytics->prune(90);
                },
                'interval' => 'daily',
            ],
            [
                'id'       => 'audit_log_prune',
                'callback' => function (): void {
                    $auditLog = new AuditLog($this->storage);
                    $auditLog->prune(90);
                },
                'interval' => 'daily',
            ],
            [
                'id'       => 'rate_limit_cleanup',
                'callback' => function (): void {
                    // Rate limit file cleanup is handled probabilistically
                    // by the RateLimiter itself, but we do a forced cleanup here.
                    $rateLimitFile = $this->storage->getDataDir() . '/rate_limits.json';
                    if (file_exists($rateLimitFile)) {
                        $data = json_decode(file_get_contents($rateLimitFile), true);
                        if (is_array($data)) {
                            $now = time();
                            foreach ($data as $key => $entry) {
                                $timestamps = $entry['timestamps'] ?? [];
                                $timestamps = array_filter($timestamps, fn(int $t): bool => ($now - $t) < 60);
                                if (empty($timestamps)) {
                                    unset($data[$key]);
                                } else {
                                    $data[$key]['timestamps'] = array_values($timestamps);
                                }
                            }
                            file_put_contents($rateLimitFile, json_encode($data), LOCK_EX);
                        }
                    }
                },
                'interval' => 'hourly',
            ],
        ];

        $coreTasks[] = [
            'id'       => 'integrity_check',
            'callback' => function (): void {
                $checker = App::getInstance()->getIntegrityChecker();
                $result  = $checker->verifyBatch();

                // If the batch did not complete, reschedule in 5 minutes.
                if ( !$result['completed'] ) {
                    // Re-run will continue where it left off.
                    klytos_schedule_single_action(
                        time() + 300,
                        'cron.integrity_check_continue',
                        [],
                        'integrity'
                    );
                }
            },
            'interval' => 'daily',
        ];

        // Allow plugins to register their own cron tasks.
        // Each task must have: id (string), callback (callable), interval (string).
        $allTasks = klytos_apply_filters('cron.tasks', $coreTasks);

        return $allTasks;
    }

    // ─── State Management ────────────────────────────────────────

    /**
     * Read the cron state: last run timestamps per task.
     *
     * @return array Task ID => last run Unix timestamp.
     */
    private function getState(): array
    {
        try {
            return $this->storage->read(self::STATE_FILE);
        } catch (\RuntimeException $e) {
            return []; // First run — no state yet.
        }
    }

    /**
     * Save the cron state.
     *
     * @param array $state Task ID => last run timestamp.
     */
    private function saveState(array $state): void
    {
        $this->storage->write(self::STATE_FILE, $state);
    }

    // ─── Locking ─────────────────────────────────────────────────

    /**
     * Acquire an exclusive lock file to prevent concurrent cron execution.
     *
     * Returns the file handle on success, or null if the lock is already held.
     * Stale locks (older than MAX_EXECUTION_TIME) are automatically broken.
     *
     * @param  string $lockPath Absolute path to the lock file.
     * @return resource|null File handle, or null if lock not acquired.
     */
    private function acquireLock(string $lockPath)
    {
        // Check for stale lock.
        if (file_exists($lockPath)) {
            $lockAge = time() - filemtime($lockPath);
            if ($lockAge > self::MAX_EXECUTION_TIME) {
                // Stale lock — break it.
                @unlink($lockPath);
            }
        }

        // Try to create and lock the file.
        $handle = @fopen($lockPath, 'w');
        if ($handle === false) {
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            // Non-blocking lock failed — another process holds it.
            fclose($handle);
            return null;
        }

        // Write PID for debugging.
        fwrite($handle, (string) getmypid());

        return $handle;
    }

    /**
     * Release the lock file.
     *
     * @param resource $handle  Lock file handle.
     * @param string   $lockPath Lock file path.
     */
    private function releaseLock($handle, string $lockPath): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
        @unlink($lockPath);
    }

    // ─── ActionScheduler Migration ──────────────────────────────

    /**
     * Migrate core cron tasks to the ActionScheduler as recurring actions.
     * Runs once per request. Safe to call multiple times (idempotent).
     */
    private function migrateToScheduler(): void
    {
        if ($this->migrated) {
            return;
        }
        $this->migrated = true;

        try {
            $scheduler = App::getInstance()->getActionScheduler();
        } catch (\Throwable $e) {
            return; // ActionScheduler not available yet.
        }

        // Map of core tasks: hook => [interval_seconds, group].
        $coreMigrations = [
            'klytos.analytics_prune'    => [86400, 'klytos-core'],
            'klytos.audit_log_prune'    => [86400, 'klytos-core'],
            'klytos.rate_limit_cleanup' => [3600,  'klytos-core'],
        ];

        foreach ($coreMigrations as $hook => [$interval, $group]) {
            if (!$scheduler->isScheduled($hook, [], $group)) {
                $scheduler->scheduleRecurring(
                    time() + $interval,
                    $interval,
                    $hook,
                    [],
                    $group
                );
            }
        }

        // Register the hook callbacks for migrated tasks.
        if (!klytos_has_action('klytos.analytics_prune')) {
            klytos_add_action('klytos.analytics_prune', function (): void {
                $analytics = new AnalyticsManager($this->storage);
                $analytics->prune(90);
            });
        }
        if (!klytos_has_action('klytos.audit_log_prune')) {
            klytos_add_action('klytos.audit_log_prune', function (): void {
                $auditLog = new AuditLog($this->storage);
                $auditLog->prune(90);
            });
        }
        if (!klytos_has_action('klytos.rate_limit_cleanup')) {
            klytos_add_action('klytos.rate_limit_cleanup', function (): void {
                $rateLimitFile = $this->storage->getDataDir() . '/rate_limits.json';
                if (file_exists($rateLimitFile)) {
                    $data = json_decode(file_get_contents($rateLimitFile), true);
                    if (is_array($data)) {
                        $now = time();
                        foreach ($data as $key => $entry) {
                            $timestamps = $entry['timestamps'] ?? [];
                            $timestamps = array_filter($timestamps, fn(int $t): bool => ($now - $t) < 60);
                            if (empty($timestamps)) {
                                unset($data[$key]);
                            } else {
                                $data[$key]['timestamps'] = array_values($timestamps);
                            }
                        }
                        file_put_contents($rateLimitFile, json_encode($data), LOCK_EX);
                    }
                }
            });
        }
    }
}
