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
        if (!klytos_verify_csrf()) {
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
            // The gate map puts this file at 'tasks.create' (owner/admin/editor)
            // so an editor can raise a task, but updating or completing SOMEBODY
            // ELSE'S task is 'tasks.manage' (owner/admin). The page already drew
            // that line (admin/tasks.php:38); this endpoint did not, so an editor
            // was refused through the UI and allowed through the API twin —
            // audit S-06, closed in slice 5. A page-level capability is a floor,
            // not a ceiling, and an API twin has to re-gate the same branches its
            // page does or the model is enforced in only one of them.
            klytos_require_permission( 'tasks.manage' );

            $taskId = $input['task_id'] ?? '';
            if (empty($taskId)) {
                Helpers::jsonResponse(['error' => 'task_id is required'], 400);
            }
            $task = $taskManager->update($taskId, $input);
            Helpers::jsonResponse(['success' => true, 'task' => $task]);

        } elseif ($action === 'complete') {
            klytos_require_permission( 'tasks.manage' );

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
