<?php

/**
 * Klytos — MCP Scheduler Management Tools
 * Tools: klytos_list_scheduled_actions, klytos_schedule_single_action,
 *        klytos_schedule_recurring_action, klytos_cancel_scheduled_action,
 *        klytos_get_scheduler_status.
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

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

function registerSchedulerTools(ToolRegistry $registry, App $app): void
{
    $registry->register(
        'klytos_list_scheduled_actions',
        'List scheduled actions with optional filters by status, group, or hook name.',
        [
            'status' => ['type' => 'string', 'description' => 'Filter by action status (e.g. "pending", "running", "failed", "complete").'],
            'group'  => ['type' => 'string', 'description' => 'Filter by action group.'],
            'hook'   => ['type' => 'string', 'description' => 'Filter by hook name.'],
        ],
        function (array $params, App $app): array {
            $scheduler = $app->getActionScheduler();
            $filters   = [];

            if (!empty($params['status'])) {
                $filters['status'] = $params['status'];
            }
            if (!empty($params['group'])) {
                $filters['group'] = $params['group'];
            }
            if (!empty($params['hook'])) {
                $filters['hook'] = $params['hook'];
            }

            return $scheduler->listActions($filters);
        },
        ['title' => 'List Scheduled Actions', 'readOnlyHint' => true]
    );

    $registry->register(
        'klytos_schedule_single_action',
        'Schedule a one-time action to run at a specific timestamp.',
        [
            'timestamp' => ['type' => 'integer', 'description' => 'Unix timestamp when the action should run.'],
            'hook'      => ['type' => 'string', 'description' => 'Hook name to execute.'],
            'args'      => ['type' => 'array', 'description' => 'Arguments to pass to the hook callback (optional).', 'items' => ['type' => 'string']],
            'group'     => ['type' => 'string', 'description' => 'Group to assign the action to (optional).'],
        ],
        function (array $params, App $app): array {
            if (empty($params['timestamp'])) {
                throw new \InvalidArgumentException('timestamp is required.');
            }
            if (empty($params['hook'])) {
                throw new \InvalidArgumentException('hook is required.');
            }

            $scheduler = $app->getActionScheduler();

            return $scheduler->scheduleSingle(
                (int) $params['timestamp'],
                $params['hook'],
                $params['args'] ?? [],
                $params['group'] ?? ''
            );
        },
        ['title' => 'Schedule Single Action', 'readOnlyHint' => false],
        ['timestamp', 'hook']
    );

    $registry->register(
        'klytos_schedule_recurring_action',
        'Schedule a recurring action that runs at a fixed interval.',
        [
            'timestamp'        => ['type' => 'integer', 'description' => 'Unix timestamp for the first run.'],
            'interval_seconds' => ['type' => 'integer', 'description' => 'Interval in seconds between each run.'],
            'hook'             => ['type' => 'string', 'description' => 'Hook name to execute.'],
            'args'             => ['type' => 'array', 'description' => 'Arguments to pass to the hook callback (optional).', 'items' => ['type' => 'string']],
            'group'            => ['type' => 'string', 'description' => 'Group to assign the action to (optional).'],
        ],
        function (array $params, App $app): array {
            if (empty($params['timestamp'])) {
                throw new \InvalidArgumentException('timestamp is required.');
            }
            if (empty($params['interval_seconds'])) {
                throw new \InvalidArgumentException('interval_seconds is required.');
            }
            if (empty($params['hook'])) {
                throw new \InvalidArgumentException('hook is required.');
            }

            $scheduler = $app->getActionScheduler();

            return $scheduler->scheduleRecurring(
                (int) $params['timestamp'],
                (int) $params['interval_seconds'],
                $params['hook'],
                $params['args'] ?? [],
                $params['group'] ?? ''
            );
        },
        ['title' => 'Schedule Recurring Action', 'readOnlyHint' => false],
        ['timestamp', 'interval_seconds', 'hook']
    );

    $registry->register(
        'klytos_cancel_scheduled_action',
        'Cancel a scheduled action by its ID. This permanently removes the action from the queue.',
        [
            'action_id' => ['type' => 'string', 'description' => 'ID of the scheduled action to cancel.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['action_id'])) {
                throw new \InvalidArgumentException('action_id is required.');
            }

            $scheduler = $app->getActionScheduler();

            return ['cancelled' => $scheduler->cancel($params['action_id'])];
        },
        ['title' => 'Cancel Scheduled Action', 'readOnlyHint' => false, 'destructiveHint' => true],
        ['action_id']
    );

    $registry->register(
        'klytos_get_scheduler_status',
        'Get the current scheduler queue statistics including pending, running, and failed action counts, last run time, and fallback mode status.',
        [],
        function (array $params, App $app): array {
            $scheduler = $app->getActionScheduler();

            return [
                'pending'          => $scheduler->countActions('pending'),
                'running'          => $scheduler->countActions('running'),
                'failed'           => $scheduler->countActions('failed'),
                'complete'         => $scheduler->countActions('complete'),
                'last_run'         => $scheduler->getLastRunTimestamp(),
                'fallback_enabled' => $scheduler->isFallbackEnabled(),
            ];
        },
        ['title' => 'Scheduler Status', 'readOnlyHint' => true]
    );
}
