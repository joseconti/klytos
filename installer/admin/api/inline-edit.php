<?php
/**
 * Klytos Admin API — Inline Edit Endpoint
 * Receives content changes from the front-end inline editor (klytos-inline-editor.js).
 *
 * Accepts POST with JSON body:
 * {
 *   "csrf": "...",
 *   "slug": "about",
 *   "selector": ".klytos-main h1",
 *   "content": "New heading text"
 * }
 *
 * Authentication: Requires active admin session + CSRF token.
 * Permission: Requires 'pages.edit' capability.
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

// Require authentication.
if (!$app->getAuth()->isAuthenticated()) {
    Helpers::jsonResponse(['error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::jsonResponse(['error' => 'Method not allowed'], 405);
}

try {
    // Parse JSON body.
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        Helpers::jsonResponse(['error' => 'Invalid JSON body'], 400);
    }

    // Validate CSRF.
    if (!$app->getAuth()->validateCsrf($input['csrf'] ?? '')) {
        Helpers::jsonResponse(['error' => 'Invalid CSRF token'], 403);
    }

    $slug        = $input['slug'] ?? '';
    $contentHtml = $input['content_html'] ?? '';

    if (empty($slug)) {
        Helpers::jsonResponse(['error' => 'slug is required'], 400);
    }

    // Update the page content.
    $pageManager   = $app->getPages();
    $versionManager = $app->getVersionManager();

    $page = $pageManager->update($slug, [
        'content_html' => $contentHtml,
    ]);

    // Save a version snapshot for the inline edit.
    $versionManager->save($slug, $page, 'inline', $app->getAuth()->getUsername());

    Helpers::jsonResponse([
        'success' => true,
        'slug'    => $slug,
        'message' => 'Page updated via inline editor.',
    ]);

} catch (\Throwable $e) {
    Helpers::jsonResponse(['error' => $e->getMessage()], 500);
}
