<?php
/**
 * Klytos Admin API — Tasks Endpoint
 * AJAX endpoint for creating, updating, and listing tasks from the front-end
 * review widget (klytos-review.js) and the admin panel.
 *
 * Methods:
 * - GET  ?action=list[&page_slug=xxx][&status=open]  → List tasks
 * - POST action=create  → Create a task
 * - POST action=update  → Update a task
 * - POST action=complete → Mark task as completed
 *
 * Authentication: Requires active admin session + CSRF token.
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

require_once dirname(__DIR__) . '/bootstrap.php';

use Klytos\Core\Helpers;

header('Content-Type: application/json; charset=utf-8');

// Require authentication for all API calls.
if (!$app->getAuth()->isAuthenticated()) {
    Helpers::jsonResponse(['error' => 'Unauthorized'], 401);
}

$taskManager = $app->getTaskManager();
$method      = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        // List tasks with optional filters.
        $status   = $_GET['status'] ?? 'all';
        $pageSlug = $_GET['page_slug'] ?? '';
        $tasks    = $taskManager->list($status, $pageSlug);

        Helpers::jsonResponse(['success' => true, 'tasks' => $tasks]);

    } elseif ($method === 'POST') {
        // Parse JSON body.
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        // Validate CSRF.
        $csrf = $input['csrf'] ?? '';
        if (!$app->getAuth()->validateCsrf($csrf)) {
            Helpers::jsonResponse(['error' => 'Invalid CSRF token'], 403);
        }

        $action = $input['action'] ?? '';

        if ($action === 'create') {
            $task = $taskManager->create([
                'page_slug'    => $input['page_slug'] ?? '',
                'description'  => $input['description'] ?? '',
                'css_selector' => $input['css_selector'] ?? '',
                'priority'     => $input['priority'] ?? 'medium',
                'created_by'   => $app->getAuth()->getUsername(),
            ]);
            Helpers::jsonResponse(['success' => true, 'task' => $task]);

        } elseif ($action === 'update') {
            $taskId = $input['task_id'] ?? '';
            if (empty($taskId)) {
                Helpers::jsonResponse(['error' => 'task_id is required'], 400);
            }
            $task = $taskManager->update($taskId, $input);
            Helpers::jsonResponse(['success' => true, 'task' => $task]);

        } elseif ($action === 'complete') {
            $taskId = $input['task_id'] ?? '';
            if (empty($taskId)) {
                Helpers::jsonResponse(['error' => 'task_id is required'], 400);
            }
            $task = $taskManager->complete($taskId);
            Helpers::jsonResponse(['success' => true, 'task' => $task]);

        } else {
            Helpers::jsonResponse(['error' => 'Unknown action'], 400);
        }

    } else {
        Helpers::jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (\Throwable $e) {
    Helpers::jsonResponse(['error' => $e->getMessage()], 500);
}
