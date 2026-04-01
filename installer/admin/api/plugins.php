<?php

/**
 * Klytos Admin API — Plugins Endpoint
 * Handles all plugin operations via AJAX: activate, deactivate, delete, uninstall,
 * install from ZIP, backup listing, and restore.
 *
 * JSON actions (POST with JSON body + X-CSRF-Token header):
 *   { "action": "activate|deactivate|delete|uninstall|check_updates|update|list_backups|restore", "plugins": [...] }
 *
 * File upload action (POST multipart/form-data):
 *   action=install, file=<zip>, csrf=<token>
 *
 * @package Klytos
 * @since   0.15.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
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
$key   = 'klytos_plugin_api_rate';
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

// ─── Detect request type ────────────────────────────────────
$pluginLoader = $app->getPluginLoader();
$contentType  = $_SERVER['CONTENT_TYPE'] ?? '';
$isUpload     = str_contains($contentType, 'multipart/form-data');

// ─── Handle file upload (install from ZIP) ──────────────────
if ($isUpload) {
    $action = $_POST['action'] ?? '';

    if ($action !== 'install') {
        Helpers::jsonResponse(['error' => 'Only "install" action supports file upload'], 400);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write file',
        ];
        $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        $msg  = $uploadErrors[$code] ?? 'Unknown upload error';
        Helpers::jsonResponse(['error' => $msg], 400);
    }

    $file    = $_FILES['file'];
    $maxSize = 50 * 1024 * 1024; // 50 MB

    if ($file['size'] > $maxSize) {
        Helpers::jsonResponse(['error' => 'File too large. Maximum: 50MB'], 413);
    }

    // Verify MIME type.
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
        Helpers::jsonResponse(['error' => 'Invalid file type. Only ZIP files are accepted.'], 400);
    }

    // Verify file extension.
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'zip') {
        Helpers::jsonResponse(['error' => 'Invalid file extension. Only .zip files are accepted.'], 400);
    }

    $result = $pluginLoader->installFromZip($file['tmp_name']);

    Helpers::jsonResponse([
        'success'   => $result['success'],
        'plugin_id' => $result['plugin_id'] ?? null,
        'message'   => $result['success'] ? 'Plugin installed successfully' : null,
        'error'     => $result['error'] ?? null,
    ]);
}

// ─── Handle JSON actions ────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    Helpers::jsonResponse(['error' => 'Invalid JSON body'], 400);
}

$action  = $input['action'] ?? '';
$plugins = $input['plugins'] ?? [];

if (empty($action)) {
    Helpers::jsonResponse(['error' => 'Missing action parameter'], 400);
}

// ─── Validate action ─────────────────────────────────────────
$validActions = ['activate', 'deactivate', 'delete', 'uninstall', 'check_updates', 'update', 'list_backups', 'restore', 'enable_logs', 'disable_logs'];
if (!in_array($action, $validActions, true)) {
    Helpers::jsonResponse(['error' => "Invalid action: {$action}"], 400);
}

// list_backups and restore need plugins; all others also require plugins.
if (!is_array($plugins) || empty($plugins)) {
    Helpers::jsonResponse(['error' => 'Missing or invalid plugins parameter'], 400);
}

// ─── Sanitize plugin IDs ────────────────────────────────────
$pluginIds = [];
foreach ($plugins as $id) {
    if (!is_string($id)) {
        continue;
    }
    $sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
    if (!empty($sanitized)) {
        $pluginIds[] = $sanitized;
    }
}

if (empty($pluginIds)) {
    Helpers::jsonResponse(['error' => 'No valid plugin IDs provided'], 400);
}

// ─── Execute action ──────────────────────────────────────────
$results = [];

foreach ($pluginIds as $pluginId) {
    switch ($action) {
        case 'activate':
            $result = $pluginLoader->activate($pluginId);
            $results[$pluginId] = [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Plugin activated successfully' : null,
                'error'   => $result['error'] ?? null,
            ];
            break;

        case 'deactivate':
            $result = $pluginLoader->deactivate($pluginId);
            $results[$pluginId] = [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Plugin deactivated successfully' : null,
                'error'   => $result['error'] ?? null,
            ];
            break;

        case 'delete':
            // Deactivate first if active, then delete files (no data cleanup).
            $pluginLoader->deactivate($pluginId);
            $result = $pluginLoader->deletePlugin($pluginId);
            $results[$pluginId] = [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Plugin deleted successfully' : null,
                'error'   => $result['error'] ?? null,
            ];
            break;

        case 'uninstall':
            // Full uninstall: run uninstall.php + delete files.
            $uninstallResult = $pluginLoader->uninstall($pluginId);
            if ($uninstallResult['success']) {
                $deleteResult = $pluginLoader->deletePlugin($pluginId);
                $results[$pluginId] = [
                    'success' => $deleteResult['success'],
                    'message' => $deleteResult['success'] ? 'Plugin uninstalled and deleted successfully' : null,
                    'error'   => $deleteResult['error'] ?? null,
                ];
            } else {
                $results[$pluginId] = [
                    'success' => false,
                    'message' => null,
                    'error'   => $uninstallResult['error'] ?? 'Uninstall failed',
                ];
            }
            break;

        case 'list_backups':
            $backups = $pluginLoader->listBackups($pluginId);
            $results[$pluginId] = [
                'success' => true,
                'backups' => $backups,
            ];
            break;

        case 'restore':
            $backupName = $input['backup_name'] ?? '';
            $backupName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $backupName);
            if (empty($backupName)) {
                $results[$pluginId] = [
                    'success' => false,
                    'error'   => 'Missing backup_name parameter',
                ];
                break;
            }
            $result = $pluginLoader->restoreBackup($pluginId, $backupName);
            $results[$pluginId] = [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Plugin restored successfully' : null,
                'error'   => $result['error'] ?? null,
            ];
            break;

        case 'check_updates':
            // Placeholder for marketplace integration.
            $results[$pluginId] = [
                'success'    => true,
                'current'    => null,
                'latest'     => null,
                'has_update' => false,
            ];
            break;

        case 'update':
            // Placeholder for marketplace integration.
            $results[$pluginId] = [
                'success' => false,
                'error'   => 'Update functionality not yet available',
            ];
            break;

        case 'enable_logs':
            $result = $pluginLoader->enableLogs($pluginId);
            $results[$pluginId] = [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Logging enabled' : null,
                'error'   => $result['error'] ?? null,
            ];
            break;

        case 'disable_logs':
            $result = $pluginLoader->disableLogs($pluginId);
            $results[$pluginId] = [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Logging disabled' : null,
                'error'   => $result['error'] ?? null,
            ];
            break;
    }
}

// ─── Response ────────────────────────────────────────────────
$allSuccess = true;
foreach ($results as $r) {
    if (!($r['success'] ?? false)) {
        $allSuccess = false;
        break;
    }
}

Helpers::jsonResponse([
    'success' => $allSuccess,
    'results' => $results,
]);
