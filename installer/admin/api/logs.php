<?php

/**
 * Klytos Admin API — Logs Endpoint
 * Handles log operations via AJAX: list files, read content, delete files.
 *
 * JSON actions (POST with JSON body + X-CSRF-Token header):
 *   { "action": "list|read|delete|delete_all", ... }
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

require_once dirname(__DIR__) . '/bootstrap.php';

use Klytos\Core\Helpers;

header('Content-Type: application/json; charset=utf-8');

// ─── Authentication ──────────────────────────────────────────
if (!$app->getAuth()->isAuthenticated()) {
    Helpers::jsonResponse(['error' => 'Unauthorized'], 401);
}

// ─── Permission check ────────────────────────────────────────
if (!klytos_has_permission('site.configure')) {
    Helpers::jsonResponse(['error' => 'Forbidden'], 403);
}

// ─── Method check ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::jsonResponse(['error' => 'Method not allowed'], 405);
}

// ─── CSRF verification ──────────────────────────────────────
if (!klytos_verify_csrf()) {
    Helpers::jsonResponse(['error' => 'Invalid CSRF token'], 403);
}

// ─── Rate limiting (30 operations per minute per session) ───
$now   = time();
$key   = 'klytos_logs_api_rate';
$limit = 30;

if (!isset($_SESSION[$key])) {
    $_SESSION[$key] = ['count' => 0, 'reset' => $now + 60];
}
if ($_SESSION[$key]['reset'] < $now) {
    $_SESSION[$key] = ['count' => 0, 'reset' => $now + 60];
}
$_SESSION[$key]['count']++;

if ($_SESSION[$key]['count'] > $limit) {
    Helpers::jsonResponse(['error' => 'Rate limit exceeded. Try again in a minute.'], 429);
}

// ─── Parse JSON input ────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    Helpers::jsonResponse(['error' => 'Invalid JSON body'], 400);
}

$action = $input['action'] ?? '';

// ─── Validate action ─────────────────────────────────────────
$validActions = ['list', 'read', 'delete', 'delete_all'];
if (!in_array($action, $validActions, true)) {
    Helpers::jsonResponse(['error' => "Invalid action: {$action}"], 400);
}

$logger = $app->getLogger();

// ─── Execute action ──────────────────────────────────────────
switch ($action) {
    case 'list':
        $files = $logger->listLogFiles();
        Helpers::jsonResponse([
            'success' => true,
            'files'   => $files,
        ]);
        break;

    case 'read':
        $filename = basename($input['file'] ?? '');
        $offset   = (int) ($input['offset'] ?? 0);
        $limit    = (int) ($input['limit'] ?? 500);
        $level    = $input['level'] ?? null;
        $search   = $input['search'] ?? '';

        if (empty($filename)) {
            Helpers::jsonResponse(['error' => 'Missing file parameter'], 400);
        }

        if (!empty($level) || !empty($search)) {
            $lines = $logger->searchLogs($filename, $search, $level);
        } else {
            $lines = $logger->readLogFile($filename, $offset, $limit);
        }

        Helpers::jsonResponse([
            'success'    => true,
            'file'       => $filename,
            'lines'      => $lines,
            'total'      => $logger->countLines($filename),
        ]);
        break;

    case 'delete':
        $filename = basename($input['file'] ?? '');
        if (empty($filename)) {
            Helpers::jsonResponse(['error' => 'Missing file parameter'], 400);
        }

        $deleted = $logger->deleteLogFile($filename);
        Helpers::jsonResponse([
            'success' => $deleted,
            'message' => $deleted ? 'Log file deleted' : 'File not found',
        ]);
        break;

    case 'delete_all':
        $count = $logger->deleteAllLogFiles();
        Helpers::jsonResponse([
            'success' => true,
            'message' => "Deleted {$count} log file(s)",
            'deleted' => $count,
        ]);
        break;
}
