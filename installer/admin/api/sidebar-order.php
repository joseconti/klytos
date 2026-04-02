<?php

/**
 * Klytos Admin API — Sidebar Order Endpoint
 * Saves, retrieves, and resets per-user sidebar menu order.
 *
 * JSON actions (POST with JSON body + X-CSRF-Token header):
 *   { "action": "save", "order": { "sections": [...], "items": { ... } } }
 *   { "action": "reset" }
 *
 * GET returns the current user's saved order.
 *
 * @package Klytos
 * @since   0.22.0
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

// ─── CSRF verification ──────────────────────────────────────
if (!klytos_verify_csrf()) {
    Helpers::jsonResponse(['error' => 'Invalid CSRF token'], 403);
}

// ─── Rate limiting (20 operations per minute per session) ───
$now   = time();
$key   = 'klytos_sidebar_order_rate';
$limit = 20;

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

// ─── Resolve user ───────────────────────────────────────────
$userId = $app->getAuth()->getUserId();
if (!$userId) {
    Helpers::jsonResponse(['error' => 'User ID not found'], 400);
}

$meta   = $app->getMetaManager();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ─── GET: return current order ──────────────────────────────
if ($method === 'GET') {
    $order = $meta->get('users', $userId, 'klytos.sidebar_order');
    Helpers::jsonResponse(['success' => true, 'order' => $order]);
}

// ─── POST: save or reset ────────────────────────────────────
if ($method !== 'POST') {
    Helpers::jsonResponse(['error' => 'Method not allowed'], 405);
}

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {

    case 'save':
        $order = $input['order'] ?? null;
        if (!is_array($order) || !isset($order['sections']) || !isset($order['items'])) {
            Helpers::jsonResponse(['error' => 'Invalid order structure'], 400);
        }

        // Sanitize: sections must be array of strings.
        $sections = array_values(array_filter($order['sections'], 'is_string'));

        // Sanitize: items must be assoc array of string => string[].
        $items = [];
        foreach ($order['items'] as $section => $ids) {
            if (!is_string($section) || !is_array($ids)) {
                continue;
            }
            $items[$section] = array_values(array_filter($ids, 'is_string'));
        }

        $meta->set('users', $userId, 'klytos.sidebar_order', [
            'sections' => $sections,
            'items'    => $items,
        ]);

        klytos_do_action('admin.sidebar_order.saved', $userId, $sections, $items);

        Helpers::jsonResponse(['success' => true]);
        break;

    case 'reset':
        $meta->delete('users', $userId, 'klytos.sidebar_order');

        klytos_do_action('admin.sidebar_order.reset', $userId);

        Helpers::jsonResponse(['success' => true]);
        break;

    default:
        Helpers::jsonResponse(['error' => "Invalid action: {$action}"], 400);
}
