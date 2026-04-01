<?php

/**
 * Klytos Admin API — Options Management Endpoint
 * Handles option operations: list, delete, delete by domain, migrate.
 *
 * JSON actions (POST with JSON body + X-CSRF-Token header):
 *   { "action": "list|delete|delete_domain|migrate", ... }
 *
 * @package Klytos
 * @since   0.18.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 Jose Conti — https://plugins.joseconti.com — https://klytos.io
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

// ─── Permission check ────────────────────────────────────────
if (!klytos_has_permission('site.configure')) {
    Helpers::jsonResponse(['error' => 'Forbidden'], 403);
}

// ─── CSRF verification ──────────────────────────────────────
if (!klytos_verify_csrf()) {
    Helpers::jsonResponse(['error' => 'Invalid CSRF token'], 403);
}

// ─── Rate limiting (30 operations per minute per session) ───
$now   = time();
$key   = 'klytos_options_api_rate';
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

// ─── Parse input ────────────────────────────────────────────
$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? ($_GET['action'] ?? '');

$optionsManager = $app->getOptionsManager();
$pluginLoader   = $app->getPluginLoader();

switch ($action) {

    case 'list':
        $filter = $input['filter'] ?? ($_GET['filter'] ?? 'all');
        $domain = $input['domain'] ?? ($_GET['domain'] ?? '');

        if ($domain !== '') {
            $options = $optionsManager->getByTextDomain($domain);
            Helpers::jsonResponse(['success' => true, 'options' => $options, 'filter' => 'domain', 'domain' => $domain]);
        }

        $domains    = $pluginLoader->getTextDomainsByStatus();
        $classified = $optionsManager->classifyOptions($domains['active'], $domains['inactive']);

        if ($filter !== 'all' && isset($classified[$filter])) {
            Helpers::jsonResponse(['success' => true, 'options' => $classified[$filter], 'filter' => $filter]);
        }

        // All options with counts per category.
        $summary = [];
        foreach ($classified as $category => $domainGroups) {
            $count = 0;
            foreach ($domainGroups as $records) {
                $count += count($records);
            }
            $summary[$category] = $count;
        }

        Helpers::jsonResponse([
            'success'    => true,
            'summary'    => $summary,
            'classified' => $classified,
            'filter'     => $filter,
        ]);
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helpers::jsonResponse(['error' => 'Method not allowed'], 405);
        }
        $optionKey = $input['key'] ?? '';
        $sanitized = preg_replace('/[^a-zA-Z0-9._\-]/', '', $optionKey);
        if ($sanitized === '') {
            Helpers::jsonResponse(['error' => 'Invalid option key'], 400);
        }
        $deleted = $optionsManager->delete($sanitized);
        Helpers::jsonResponse(['success' => $deleted, 'key' => $sanitized]);
        break;

    case 'delete_domain':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helpers::jsonResponse(['error' => 'Method not allowed'], 405);
        }
        $domain  = $input['domain'] ?? '';
        $confirm = $input['confirm'] ?? false;
        $sanitized = preg_replace('/[^a-zA-Z0-9._\-]/', '', $domain);
        if ($sanitized === '' || !$confirm) {
            Helpers::jsonResponse(['error' => 'domain and confirm=true are required'], 400);
        }
        $count = $optionsManager->deleteByTextDomain($sanitized);
        Helpers::jsonResponse(['success' => true, 'deleted' => $count, 'domain' => $sanitized]);
        break;

    case 'migrate':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Helpers::jsonResponse(['error' => 'Method not allowed'], 405);
        }
        $migrated = $optionsManager->migrateTextDomains();
        Helpers::jsonResponse(['success' => true, 'migrated' => $migrated]);
        break;

    default:
        Helpers::jsonResponse(['error' => "Invalid action: {$action}"], 400);
}
