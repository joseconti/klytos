<?php
/**
 * Klytos — MCP Task Management Tools
 * Tools: klytos_list_tasks, klytos_get_task, klytos_update_task, klytos_complete_task.
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

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

function registerTaskTools(ToolRegistry $registry, App $app): void
{
    $registry->register(
        'klytos_list_tasks',
        'List review tasks with optional filters by status and page.',
        [
            'status'    => ['type' => 'string', 'description' => 'Filter: all, open, in_progress, completed, dismissed.', 'enum' => ['all', 'open', 'in_progress', 'completed', 'dismissed']],
            'page_slug' => ['type' => 'string', 'description' => 'Filter by page slug (optional).'],
        ],
        function (array $params, App $app): array {
            $taskManager = new \Klytos\Core\TaskManager($app->getStorage());
            return $taskManager->list($params['status'] ?? 'all', $params['page_slug'] ?? '');
        },
        ['title' => 'List Tasks', 'readOnlyHint' => true]
    );

    $registry->register(
        'klytos_get_task',
        'Get a specific task by its ID.',
        [
            'task_id' => ['type' => 'string', 'description' => 'Task ID.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['task_id'])) {
                throw new \InvalidArgumentException('task_id is required.');
            }
            $taskManager = new \Klytos\Core\TaskManager($app->getStorage());
            return $taskManager->get($params['task_id']);
        },
        ['title' => 'Get Task', 'readOnlyHint' => true],
        ['task_id']
    );

    $registry->register(
        'klytos_create_task',
        'Create a new review task for a page.',
        [
            'page_slug'    => ['type' => 'string', 'description' => 'Page slug this task belongs to.'],
            'description'  => ['type' => 'string', 'description' => 'What needs to be done.'],
            'css_selector' => ['type' => 'string', 'description' => 'CSS selector of the target element (optional).'],
            'priority'     => ['type' => 'string', 'description' => 'Priority: low, medium, high, urgent.', 'enum' => ['low', 'medium', 'high', 'urgent']],
        ],
        function (array $params, App $app): array {
            $taskManager = new \Klytos\Core\TaskManager($app->getStorage());
            return $taskManager->create($params);
        },
        ['title' => 'Create Task', 'readOnlyHint' => false],
        ['page_slug', 'description']
    );

    $registry->register(
        'klytos_update_task',
        'Update a task. Only provide the fields you want to change.',
        [
            'task_id'     => ['type' => 'string', 'description' => 'Task ID to update.'],
            'description' => ['type' => 'string', 'description' => 'New description.'],
            'priority'    => ['type' => 'string', 'description' => 'New priority.', 'enum' => ['low', 'medium', 'high', 'urgent']],
            'status'      => ['type' => 'string', 'description' => 'New status.', 'enum' => ['open', 'in_progress', 'completed', 'dismissed']],
            'assigned_to' => ['type' => 'string', 'description' => 'User ID to assign to.'],
        ],
        function (array $params, App $app): array {
            $taskId = $params['task_id'] ?? '';
            if (empty($taskId)) {
                throw new \InvalidArgumentException('task_id is required.');
            }
            unset($params['task_id']);
            $taskManager = new \Klytos\Core\TaskManager($app->getStorage());
            return $taskManager->update($taskId, $params);
        },
        ['title' => 'Update Task', 'readOnlyHint' => false],
        ['task_id']
    );

    $registry->register(
        'klytos_complete_task',
        'Mark a task as completed.',
        [
            'task_id' => ['type' => 'string', 'description' => 'Task ID to complete.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['task_id'])) {
                throw new \InvalidArgumentException('task_id is required.');
            }
            $taskManager = new \Klytos\Core\TaskManager($app->getStorage());
            return $taskManager->complete($params['task_id']);
        },
        ['title' => 'Complete Task', 'readOnlyHint' => false],
        ['task_id']
    );
}
