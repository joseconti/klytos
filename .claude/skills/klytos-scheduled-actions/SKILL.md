---
name: klytos-scheduled-actions
description: Guide for scheduling background tasks, cron jobs, and recurring actions in Klytos CMS. Use when scheduling background tasks, creating cron jobs, running periodic cleanup, executing deferred actions, querying scheduled actions, setting up recurring tasks, handling action retries, managing the action scheduler, or working with ActionScheduler or CronManager.
---

# Klytos Scheduled Actions & Cron

## When to Use This Skill

Use this reference when your plugin needs to run background tasks: daily cleanups, periodic API syncs, deferred processing, or any task that runs on a schedule.

Klytos has two systems:
1. **Action Scheduler** (primary) — Flexible, supports single and recurring actions, retry logic
2. **CronManager** (legacy) — Simple interval-based pseudo-cron running on admin page loads

---

## Action Scheduler (Primary System)

### Schedule a One-Time Action

```php
klytos_schedule_single_action(int $timestamp, string $hook, array $args = [], string $group = ''): string
```

Returns the action ID.

```php
// Run in 1 hour
$actionId = klytos_schedule_single_action(
    time() + 3600,
    'my_plugin.send_report',
    ['report_id' => 42],
    'my-plugin'
);

// Handle the action when it fires
klytos_add_action('my_plugin.send_report', function (array $args): void {
    $reportId = $args['report_id'];
    // ... send the report
    klytos_log('info', "Report {$reportId} sent");
});
```

### Schedule a Recurring Action

```php
klytos_schedule_recurring_action(int $timestamp, int $intervalSeconds, string $hook, array $args = [], string $group = ''): string
```

Minimum interval: **60 seconds**.

```php
// Run every hour starting now
$actionId = klytos_schedule_recurring_action(
    time(),
    3600,                           // Every hour
    'my_plugin.sync_data',
    [],
    'my-plugin'
);

// Run daily (86400 seconds)
$actionId = klytos_schedule_recurring_action(
    time(),
    86400,
    'my_plugin.daily_cleanup',
    [],
    'my-plugin'
);
```

### Cancel Actions

```php
// Cancel by ID
klytos_cancel_scheduled_action(string $actionId): bool

// Cancel ALL pending actions for a hook
klytos_unschedule_all_actions(string $hook, array $args = [], string $group = ''): int
```

```php
// Cancel specific action
klytos_cancel_scheduled_action($actionId);

// Cancel all sync actions
$count = klytos_unschedule_all_actions('my_plugin.sync_data', [], 'my-plugin');
```

### Query Scheduled Actions

```php
// Next run timestamp (or null)
klytos_next_scheduled_action(string $hook, array $args = [], string $group = ''): ?int

// Is action scheduled?
klytos_is_scheduled_action(string $hook, array $args = [], string $group = ''): bool
```

```php
// Check before scheduling (avoid duplicates)
if (!klytos_is_scheduled_action('my_plugin.sync_data')) {
    klytos_schedule_recurring_action(time(), 3600, 'my_plugin.sync_data', [], 'my-plugin');
}

// Get next run time
$next = klytos_next_scheduled_action('my_plugin.sync_data');
if ($next) {
    echo "Next sync: " . date('Y-m-d H:i:s', $next);
}
```

### Action Data Structure

```php
[
    'id'           => string,        // UUID
    'hook'         => string,        // Hook name to fire
    'args'         => array,         // Arguments for the hook
    'group'        => string,        // Organization group
    'status'       => string,        // 'pending', 'completed', 'failed', 'canceled'
    'type'         => string,        // 'single' or 'recurring'
    'interval'     => ?int,          // Seconds between runs (null for single)
    'scheduled_at' => string,        // ISO 8601 — when it should run
    'started_at'   => ?string,       // When execution started
    'completed_at' => ?string,       // When execution finished
    'attempts'     => int,           // Current attempt count
    'max_attempts' => int,           // Max retries (default: 3)
    'last_error'   => ?string,       // Last error message
    'created_at'   => string,        // ISO 8601
    'updated_at'   => string,        // ISO 8601
]
```

### Execution: How Actions Run

1. **Server cron** (preferred): Configure your server's crontab to call `cli.php scheduler:run` every minute
2. **Fallback pseudo-cron**: On every admin page load, the Action Scheduler checks for due actions

Failed actions are retried up to `max_attempts` (default: 3) with exponential backoff.

---

## Via MCP (AI Interface)

### klytos_schedule_single_action

```json
{
    "timestamp": 1711900800,
    "hook": "my_plugin.send_report",
    "args": {"report_id": 42},
    "group": "my-plugin"
}
```

### klytos_schedule_recurring_action

```json
{
    "timestamp": 1711900800,
    "interval_seconds": 86400,
    "hook": "my_plugin.daily_cleanup",
    "args": {},
    "group": "my-plugin"
}
```

### klytos_cancel_scheduled_action

```json
{
    "action_id": "abc123-def456"
}
```

### klytos_list_scheduled_actions

```json
{
    "status": "pending",
    "group": "my-plugin"
}
```

### klytos_get_scheduler_status

Returns scheduler health: queue size, last run, next run, failed count.

---

## Via Admin (Desktop Interface)

- **Scheduled Actions page**: `admin/scheduled-actions.php`
- View all scheduled actions with status, next run, group
- Cancel or retry failed actions
- View execution history

---

## CronManager (Legacy Pseudo-Cron)

For simple recurring tasks. Runs on every admin page load.

### Register a Cron Task

```php
klytos_add_filter('cron.tasks', function (array $tasks): array {
    $tasks[] = [
        'id'       => 'my_plugin_daily_task',
        'callback' => function (): void {
            klytos_log('info', 'Running daily task');
            // ... task logic
        },
        'interval' => 'daily',
    ];
    return $tasks;
});
```

### Available Intervals

| Interval | Seconds | Description |
|---|---|---|
| `'hourly'` | 3600 | Every hour |
| `'daily'` | 86400 | Every 24 hours |
| `'weekly'` | 604800 | Every 7 days |
| `'monthly'` | 2592000 | Every 30 days |

### Core Cron Tasks

| Task | Interval | Purpose |
|---|---|---|
| `analytics_prune` | daily | Prune analytics data older than 90 days |
| `audit_log_prune` | daily | Prune audit logs older than 90 days |
| `rate_limit_cleanup` | hourly | Clean expired rate limit entries |

---

## When to Use Which System

| Scenario | System | Why |
|---|---|---|
| Run once in 1 hour | Action Scheduler | Single action with timestamp |
| Run every day | Action Scheduler | Recurring with 86400 interval |
| Retry on failure | Action Scheduler | Built-in retry logic |
| Simple daily cleanup | CronManager | Simple, no retry needed |
| Process queue of items | Action Scheduler | Each item can be its own action |
| Deferred heavy processing | Action Scheduler | Runs via server cron, not on page load |

**Recommendation**: Use the **Action Scheduler** for new code. CronManager is maintained for backwards compatibility.

---

## Best Practices

1. **Always check before scheduling** to avoid duplicate actions:
   ```php
   if (!klytos_is_scheduled_action('my_plugin.sync')) {
       klytos_schedule_recurring_action(time(), 3600, 'my_plugin.sync');
   }
   ```

2. **Use groups** to organize actions by plugin:
   ```php
   klytos_schedule_single_action(time() + 60, 'my_hook', [], 'my-plugin');
   ```

3. **Clean up on deactivation**:
   ```php
   // plugins/my-plugin/deactivate.php
   klytos_unschedule_all_actions('my_plugin.sync', [], 'my-plugin');
   klytos_unschedule_all_actions('my_plugin.cleanup', [], 'my-plugin');
   ```

4. **Keep actions short** — long-running actions may timeout. Break large tasks into smaller actions.

5. **Log results** for debugging:
   ```php
   klytos_add_action('my_plugin.sync', function (): void {
       $start = microtime(true);
       // ... work
       $duration = round(microtime(true) - $start, 2);
       klytos_log('info', "Sync completed in {$duration}s");
   });
   ```

---

## Source Files

- Action Scheduler: `core/action-scheduler.php`
- CronManager: `core/cron-manager.php`
- Global functions: `core/helpers-global.php` (lines 574-655)
- MCP scheduler tools: `core/mcp/tools/scheduler-tools.php`
- Admin page: `admin/scheduled-actions.php`
