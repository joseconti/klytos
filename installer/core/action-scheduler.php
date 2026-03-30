<?php
/**
 * Klytos — Action Scheduler
 * Schedule and execute one-time or recurring actions via server cron.
 *
 * Inspired by WooCommerce's Action Scheduler (https://actionscheduler.org/).
 * Actions are stored in the 'scheduled_actions' collection and executed by
 * the server's native crontab (via HTTP endpoint or CLI command).
 *
 * A fallback pseudo-cron mode runs the queue on admin page loads
 * when the server cron is not yet configured.
 *
 * Plugins schedule actions via the global helper functions:
 *   klytos_schedule_single_action(), klytos_schedule_recurring_action(), etc.
 *
 * @package Klytos
 * @since   2.0.0
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

class ActionScheduler
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string Path to the config directory. */
    private string $configPath;

    /** @var string Collection name for scheduled actions. */
    private const COLLECTION = 'scheduled_actions';

    /** @var string Lock file to prevent concurrent queue processing. */
    private const LOCK_FILE = '.scheduler.lock';

    /** @var int Maximum time in seconds before a running action is considered stale. */
    private const STALE_TIMEOUT = 300;

    /** @var int Default maximum retry attempts. */
    private const DEFAULT_MAX_ATTEMPTS = 3;

    /** @var int Default batch size for queue processing. */
    private const DEFAULT_BATCH_SIZE = 25;

    /** @var int Minimum seconds between fallback (pseudo-cron) runs. */
    private const FALLBACK_INTERVAL = 60;

    /** @var string Config key for the cron token. */
    private const CONFIG_TOKEN_KEY = 'cron_token';

    /** @var string Config key for fallback mode. */
    private const CONFIG_FALLBACK_KEY = 'scheduler_fallback';

    /** @var string Config key for last run timestamp. */
    private const CONFIG_LAST_RUN_KEY = 'scheduler_last_run';

    /**
     * @param StorageInterface $storage    Storage backend instance.
     * @param string           $configPath Absolute path to config/ directory.
     */
    public function __construct(StorageInterface $storage, string $configPath)
    {
        $this->storage    = $storage;
        $this->configPath = $configPath;
    }

    // ─── Scheduling ─────────────────────────────────────────────

    /**
     * Schedule a one-time action.
     *
     * @param  int    $timestamp Unix timestamp when the action should run.
     * @param  string $hook      Hook name to fire on execution.
     * @param  array  $args      Arguments passed to the hook callbacks.
     * @param  string $group     Group name for organization (optional).
     * @return string The action ID.
     */
    public function scheduleSingle(int $timestamp, string $hook, array $args = [], string $group = ''): string
    {
        if (empty($hook)) {
            throw new \InvalidArgumentException('Hook name is required.');
        }

        $id = $this->generateId();
        $now = Helpers::now();

        $action = [
            'id'           => $id,
            'hook'         => $hook,
            'args'         => $args,
            'group'        => $group,
            'status'       => 'pending',
            'type'         => 'single',
            'interval'     => null,
            'scheduled_at' => date('c', $timestamp),
            'started_at'   => null,
            'completed_at' => null,
            'claim_id'     => null,
            'attempts'     => 0,
            'max_attempts'  => self::DEFAULT_MAX_ATTEMPTS,
            'last_error'   => null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];

        $this->storage->write(self::COLLECTION, $id, $action);

        Hooks::doAction('scheduler.action_created', $action);

        return $id;
    }

    /**
     * Schedule a recurring action.
     *
     * @param  int    $timestamp       Unix timestamp for the first run.
     * @param  int    $intervalSeconds  Seconds between recurring runs.
     * @param  string $hook            Hook name to fire on execution.
     * @param  array  $args            Arguments passed to the hook callbacks.
     * @param  string $group           Group name for organization (optional).
     * @return string The action ID.
     */
    public function scheduleRecurring(int $timestamp, int $intervalSeconds, string $hook, array $args = [], string $group = ''): string
    {
        if (empty($hook)) {
            throw new \InvalidArgumentException('Hook name is required.');
        }
        if ($intervalSeconds < 60) {
            throw new \InvalidArgumentException('Interval must be at least 60 seconds.');
        }

        $id = $this->generateId();
        $now = Helpers::now();

        $action = [
            'id'           => $id,
            'hook'         => $hook,
            'args'         => $args,
            'group'        => $group,
            'status'       => 'pending',
            'type'         => 'recurring',
            'interval'     => $intervalSeconds,
            'scheduled_at' => date('c', $timestamp),
            'started_at'   => null,
            'completed_at' => null,
            'claim_id'     => null,
            'attempts'     => 0,
            'max_attempts'  => self::DEFAULT_MAX_ATTEMPTS,
            'last_error'   => null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];

        $this->storage->write(self::COLLECTION, $id, $action);

        Hooks::doAction('scheduler.action_created', $action);

        return $id;
    }

    /**
     * Cancel a single action by ID.
     *
     * @param  string $actionId Action ID.
     * @return bool   True if the action was canceled.
     */
    public function cancel(string $actionId): bool
    {
        try {
            $action = $this->storage->read(self::COLLECTION, $actionId);
        } catch (\RuntimeException $e) {
            return false;
        }

        if ($action['status'] !== 'pending') {
            return false;
        }

        $action['status']     = 'canceled';
        $action['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $actionId, $action);

        Hooks::doAction('scheduler.action_canceled', $action);

        return true;
    }

    /**
     * Cancel all pending actions matching a hook (and optionally args/group).
     *
     * @param  string $hook  Hook name.
     * @param  array  $args  Arguments to match (empty = any).
     * @param  string $group Group to match (empty = any).
     * @return int    Number of actions canceled.
     */
    public function cancelByHook(string $hook, array $args = [], string $group = ''): int
    {
        $filters = ['status' => 'pending', 'hook' => $hook];
        if (!empty($group)) {
            $filters['group'] = $group;
        }

        $actions  = $this->storage->list(self::COLLECTION, $filters);
        $canceled = 0;

        foreach ($actions as $action) {
            if (!empty($args) && $action['args'] !== $args) {
                continue;
            }
            $action['status']     = 'canceled';
            $action['updated_at'] = Helpers::now();
            $this->storage->write(self::COLLECTION, $action['id'], $action);
            $canceled++;
        }

        return $canceled;
    }

    /**
     * Unschedule all pending actions for a hook (alias for cancelByHook).
     *
     * @param  string $hook  Hook name.
     * @param  array  $args  Arguments to match (empty = any).
     * @param  string $group Group to match (empty = any).
     * @return int    Number of actions unscheduled.
     */
    public function unscheduleAll(string $hook, array $args = [], string $group = ''): int
    {
        return $this->cancelByHook($hook, $args, $group);
    }

    // ─── Queries ────────────────────────────────────────────────

    /**
     * Get the timestamp of the next scheduled action for a hook.
     *
     * @param  string   $hook  Hook name.
     * @param  array    $args  Arguments to match (empty = any).
     * @param  string   $group Group to match (empty = any).
     * @return int|null Unix timestamp, or null if not scheduled.
     */
    public function nextScheduled(string $hook, array $args = [], string $group = ''): ?int
    {
        $filters = ['status' => 'pending', 'hook' => $hook];
        if (!empty($group)) {
            $filters['group'] = $group;
        }

        $actions = $this->storage->list(self::COLLECTION, $filters);

        $nextTimestamp = null;

        foreach ($actions as $action) {
            if (!empty($args) && $action['args'] !== $args) {
                continue;
            }
            $ts = strtotime($action['scheduled_at']);
            if ($nextTimestamp === null || $ts < $nextTimestamp) {
                $nextTimestamp = $ts;
            }
        }

        return $nextTimestamp;
    }

    /**
     * Check if an action is scheduled (pending) for a hook.
     *
     * @param  string $hook  Hook name.
     * @param  array  $args  Arguments to match (empty = any).
     * @param  string $group Group to match (empty = any).
     * @return bool
     */
    public function isScheduled(string $hook, array $args = [], string $group = ''): bool
    {
        return $this->nextScheduled($hook, $args, $group) !== null;
    }

    /**
     * Get a single action by ID.
     *
     * @param  string     $actionId Action ID.
     * @return array|null Action data, or null if not found.
     */
    public function getAction(string $actionId): ?array
    {
        try {
            return $this->storage->read(self::COLLECTION, $actionId);
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /**
     * List actions with optional filters.
     *
     * @param  array $filters Key-value filters (status, hook, group, type).
     * @param  int   $limit   Maximum results.
     * @param  int   $offset  Offset for pagination.
     * @return array Array of action records.
     */
    public function listActions(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $actions = $this->storage->list(self::COLLECTION, $filters, $limit, $offset);

        // Sort by scheduled_at ascending.
        usort($actions, function (array $a, array $b): int {
            return strcmp($a['scheduled_at'] ?? '', $b['scheduled_at'] ?? '');
        });

        return $actions;
    }

    /**
     * Count actions with optional filters.
     *
     * @param  array $filters Key-value filters.
     * @return int
     */
    public function countActions(array $filters = []): int
    {
        return $this->storage->count(self::COLLECTION, $filters);
    }

    // ─── Execution ──────────────────────────────────────────────

    /**
     * Process the action queue: execute all due pending actions.
     *
     * This is the main entry point, called by the cron endpoint
     * (HTTP or CLI) or the fallback pseudo-cron.
     *
     * @param  int   $batchSize Maximum actions to process per run.
     * @return array Results: ['processed' => int, 'failed' => int, 'errors' => array]
     */
    public function processQueue(int $batchSize = self::DEFAULT_BATCH_SIZE): array
    {
        // Acquire lock to prevent concurrent execution.
        $lockPath = $this->storage->getDataDir() . '/' . self::LOCK_FILE;
        $lock     = $this->acquireLock($lockPath);

        if ($lock === null) {
            return ['processed' => 0, 'failed' => 0, 'errors' => [], 'skipped' => 'lock_held'];
        }

        try {
            // Recover stale running actions.
            $this->recoverStale();

            // Get due actions: pending and scheduled_at <= now.
            $allPending = $this->storage->list(self::COLLECTION, ['status' => 'pending']);

            $now        = time();
            $dueActions = [];

            foreach ($allPending as $action) {
                $scheduledTs = strtotime($action['scheduled_at'] ?? '');
                if ($scheduledTs !== false && $scheduledTs <= $now) {
                    $dueActions[] = $action;
                }
            }

            // Sort by scheduled_at ascending (oldest first).
            usort($dueActions, function (array $a, array $b): int {
                return strcmp($a['scheduled_at'] ?? '', $b['scheduled_at'] ?? '');
            });

            // Limit batch size.
            $dueActions = array_slice($dueActions, 0, $batchSize);

            $processed = 0;
            $failed    = 0;
            $errors    = [];

            foreach ($dueActions as $action) {
                $result = $this->executeAction($action);

                if ($result['success']) {
                    $processed++;
                } else {
                    $failed++;
                    $errors[$action['id']] = $result['error'];
                }
            }

            // Update last run timestamp.
            $this->setConfigValue(self::CONFIG_LAST_RUN_KEY, time());

            // Fire completion action.
            $results = ['processed' => $processed, 'failed' => $failed, 'errors' => $errors];
            Hooks::doAction('scheduler.batch_complete', $results);

            return $results;

        } finally {
            $this->releaseLock($lock, $lockPath);
        }
    }

    /**
     * Process the queue only if fallback (pseudo-cron) mode is enabled
     * and enough time has elapsed since the last run.
     *
     * Called from admin/bootstrap.php on every admin page load.
     *
     * @return array|null Results, or null if skipped.
     */
    public function processQueueIfFallback(): ?array
    {
        // Check if fallback mode is enabled (default: true).
        if (!$this->isFallbackEnabled()) {
            return null;
        }

        // Throttle: only run once per FALLBACK_INTERVAL seconds.
        $lastRun = $this->getConfigValue(self::CONFIG_LAST_RUN_KEY, 0);
        if ((time() - $lastRun) < self::FALLBACK_INTERVAL) {
            return null;
        }

        return $this->processQueue();
    }

    /**
     * Execute a single action.
     *
     * @param  array $action Action data.
     * @return array ['success' => bool, 'error' => string|null]
     */
    private function executeAction(array $action): array
    {
        $actionId = $action['id'];
        $claimId  = bin2hex(random_bytes(8));

        // Claim the action: set running state.
        $action['status']     = 'running';
        $action['started_at'] = Helpers::now();
        $action['claim_id']   = $claimId;
        $action['attempts']   = ($action['attempts'] ?? 0) + 1;
        $action['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $actionId, $action);

        try {
            // Execute: fire the hook with arguments.
            $hookArgs = $action['args'] ?? [];
            Hooks::doAction($action['hook'], ...$hookArgs);

            // Success: mark complete.
            $action['status']       = 'complete';
            $action['completed_at'] = Helpers::now();
            $action['claim_id']     = null;
            $action['last_error']   = null;
            $action['updated_at']   = Helpers::now();

            $this->storage->write(self::COLLECTION, $actionId, $action);

            Hooks::doAction('scheduler.action_complete', $action);

            // For recurring actions, schedule the next occurrence.
            if ($action['type'] === 'recurring' && !empty($action['interval'])) {
                $nextTimestamp = time() + (int) $action['interval'];

                // Only schedule if no pending action already exists for this hook+group.
                if (!$this->isScheduled($action['hook'], $action['args'] ?? [], $action['group'] ?? '')) {
                    $this->scheduleRecurring(
                        $nextTimestamp,
                        (int) $action['interval'],
                        $action['hook'],
                        $action['args'] ?? [],
                        $action['group'] ?? ''
                    );
                }
            }

            return ['success' => true, 'error' => null];

        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();

            error_log("Klytos Scheduler: action '{$actionId}' (hook: {$action['hook']}) failed: {$errorMessage}");

            $maxAttempts = $action['max_attempts'] ?? self::DEFAULT_MAX_ATTEMPTS;

            if ($action['attempts'] < $maxAttempts) {
                // Retry with exponential backoff: attempt * 60 seconds.
                $retryTimestamp = time() + ($action['attempts'] * 60);

                $action['status']       = 'pending';
                $action['scheduled_at'] = date('c', $retryTimestamp);
                $action['claim_id']     = null;
                $action['last_error']   = $errorMessage;
                $action['updated_at']   = Helpers::now();
            } else {
                // Max attempts reached: mark as failed.
                $action['status']       = 'failed';
                $action['completed_at'] = Helpers::now();
                $action['claim_id']     = null;
                $action['last_error']   = $errorMessage;
                $action['updated_at']   = Helpers::now();
            }

            $this->storage->write(self::COLLECTION, $actionId, $action);

            Hooks::doAction('scheduler.action_failed', $action, $e);

            return ['success' => false, 'error' => $errorMessage];
        }
    }

    // ─── Maintenance ────────────────────────────────────────────

    /**
     * Prune completed/canceled actions older than the retention period.
     *
     * @param  int $retentionDays Days to keep completed actions.
     * @return int Number of records pruned.
     */
    public function pruneCompleted(int $retentionDays = 30): int
    {
        $cutoff = date('c', time() - ($retentionDays * 86400));
        $pruned = 0;

        foreach (['complete', 'failed', 'canceled'] as $status) {
            $actions = $this->storage->list(self::COLLECTION, ['status' => $status]);

            foreach ($actions as $action) {
                $completedAt = $action['completed_at'] ?? $action['updated_at'] ?? '';
                if (!empty($completedAt) && $completedAt < $cutoff) {
                    $this->storage->delete(self::COLLECTION, $action['id']);
                    $pruned++;
                }
            }
        }

        return $pruned;
    }

    /**
     * Recover stale running actions (stuck due to crash/timeout).
     *
     * @param  int $timeoutSeconds Seconds after which a running action is considered stale.
     * @return int Number of actions recovered.
     */
    public function recoverStale(int $timeoutSeconds = self::STALE_TIMEOUT): int
    {
        $actions   = $this->storage->list(self::COLLECTION, ['status' => 'running']);
        $now       = time();
        $recovered = 0;

        foreach ($actions as $action) {
            $startedAt = strtotime($action['started_at'] ?? '');
            if ($startedAt === false) {
                continue;
            }

            if (($now - $startedAt) > $timeoutSeconds) {
                $maxAttempts = $action['max_attempts'] ?? self::DEFAULT_MAX_ATTEMPTS;

                if (($action['attempts'] ?? 0) >= $maxAttempts) {
                    $action['status']       = 'failed';
                    $action['completed_at'] = Helpers::now();
                    $action['last_error']   = 'Action timed out after ' . $timeoutSeconds . ' seconds (max attempts reached).';
                } else {
                    $action['status']       = 'pending';
                    $action['scheduled_at'] = date('c', $now);
                    $action['last_error']   = 'Action timed out after ' . $timeoutSeconds . ' seconds — retrying.';
                }

                $action['claim_id']   = null;
                $action['updated_at'] = Helpers::now();

                $this->storage->write(self::COLLECTION, $action['id'], $action);
                $recovered++;
            }
        }

        return $recovered;
    }

    /**
     * Retry a failed action by resetting it to pending.
     *
     * @param  string $actionId Action ID.
     * @return bool   True if the action was reset for retry.
     */
    public function retry(string $actionId): bool
    {
        try {
            $action = $this->storage->read(self::COLLECTION, $actionId);
        } catch (\RuntimeException $e) {
            return false;
        }

        if ($action['status'] !== 'failed') {
            return false;
        }

        $action['status']       = 'pending';
        $action['scheduled_at'] = date('c', time());
        $action['attempts']     = 0;
        $action['claim_id']     = null;
        $action['last_error']   = null;
        $action['started_at']   = null;
        $action['completed_at'] = null;
        $action['updated_at']   = Helpers::now();

        $this->storage->write(self::COLLECTION, $actionId, $action);

        return true;
    }

    /**
     * Delete a completed, failed, or canceled action.
     *
     * @param  string $actionId Action ID.
     * @return bool   True if deleted.
     */
    public function deleteAction(string $actionId): bool
    {
        try {
            $action = $this->storage->read(self::COLLECTION, $actionId);
        } catch (\RuntimeException $e) {
            return false;
        }

        if (in_array($action['status'], ['complete', 'failed', 'canceled'], true)) {
            return $this->storage->delete(self::COLLECTION, $actionId);
        }

        return false;
    }

    // ─── Token Management ───────────────────────────────────────

    /**
     * Get the cron security token. Generates one if it doesn't exist.
     *
     * @return string 64-character hex token.
     */
    public function getCronToken(): string
    {
        $token = $this->getConfigValue(self::CONFIG_TOKEN_KEY);

        if (empty($token)) {
            $token = $this->regenerateCronToken();
        }

        return $token;
    }

    /**
     * Regenerate the cron security token.
     *
     * @return string New 64-character hex token.
     */
    public function regenerateCronToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->setConfigValue(self::CONFIG_TOKEN_KEY, $token);
        return $token;
    }

    /**
     * Verify a cron token.
     *
     * @param  string $token Token to verify.
     * @return bool   True if the token is valid.
     */
    public function verifyCronToken(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $storedToken = $this->getConfigValue(self::CONFIG_TOKEN_KEY);

        if (empty($storedToken)) {
            return false;
        }

        return hash_equals($storedToken, $token);
    }

    // ─── Configuration ──────────────────────────────────────────

    /**
     * Check if fallback (pseudo-cron) mode is enabled.
     *
     * @return bool Default: true.
     */
    public function isFallbackEnabled(): bool
    {
        $value = $this->getConfigValue(self::CONFIG_FALLBACK_KEY);
        // Default to enabled.
        return $value === null ? true : (bool) $value;
    }

    /**
     * Set fallback mode.
     *
     * @param bool $enabled Whether to enable fallback mode.
     */
    public function setFallbackEnabled(bool $enabled): void
    {
        $this->setConfigValue(self::CONFIG_FALLBACK_KEY, $enabled);
    }

    /**
     * Get the timestamp of the last queue run.
     *
     * @return int|null Unix timestamp, or null if never run.
     */
    public function getLastRunTimestamp(): ?int
    {
        $ts = $this->getConfigValue(self::CONFIG_LAST_RUN_KEY);
        return $ts !== null ? (int) $ts : null;
    }

    // ─── Internal Helpers ───────────────────────────────────────

    /**
     * Generate a unique action ID.
     *
     * @return string
     */
    private function generateId(): string
    {
        return uniqid('act_', true) . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Read a config value from the scheduler config.
     *
     * @param  string     $key     Config key.
     * @param  mixed      $default Default value.
     * @return mixed
     */
    private function getConfigValue(string $key, mixed $default = null): mixed
    {
        try {
            $config = $this->storage->readFrom($this->configPath, 'config.json.enc');
            return $config[$key] ?? $default;
        } catch (\RuntimeException $e) {
            return $default;
        }
    }

    /**
     * Write a config value to the main config.
     *
     * @param string $key   Config key.
     * @param mixed  $value Value to store.
     */
    private function setConfigValue(string $key, mixed $value): void
    {
        try {
            $config = $this->storage->readFrom($this->configPath, 'config.json.enc');
        } catch (\RuntimeException $e) {
            $config = [];
        }

        $config[$key] = $value;
        $this->storage->writeTo($this->configPath, 'config.json.enc', $config);
    }

    // ─── Locking ────────────────────────────────────────────────

    /**
     * Acquire an exclusive lock file.
     *
     * @param  string        $lockPath Absolute path to the lock file.
     * @return resource|null File handle, or null if lock not acquired.
     */
    private function acquireLock(string $lockPath)
    {
        // Check for stale lock.
        if (file_exists($lockPath)) {
            $lockAge = time() - filemtime($lockPath);
            if ($lockAge > self::STALE_TIMEOUT) {
                @unlink($lockPath);
            }
        }

        $handle = @fopen($lockPath, 'w');
        if ($handle === false) {
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }

        fwrite($handle, (string) getmypid());

        return $handle;
    }

    /**
     * Release the lock file.
     *
     * @param resource $handle   Lock file handle.
     * @param string   $lockPath Lock file path.
     */
    private function releaseLock($handle, string $lockPath): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
        @unlink($lockPath);
    }
}
