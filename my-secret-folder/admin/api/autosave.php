<?php
/**
 * Klytos Admin API — Autosave Endpoint
 * Saves page content automatically every 60 seconds from the admin editor.
 *
 * Accepts POST with JSON body:
 * { "csrf": "...", "slug": "about", "content_html": "...", "title": "..." }
 *
 * Does NOT create a version entry (autosave is a draft buffer, not a commit).
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

if (!$app->getAuth()->isAuthenticated()) {
    Helpers::jsonResponse(['error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::jsonResponse(['error' => 'Method not allowed'], 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        Helpers::jsonResponse(['error' => 'Invalid JSON body'], 400);
    }

    if (!$app->getAuth()->validateCsrf($input['csrf'] ?? '')) {
        Helpers::jsonResponse(['error' => 'Invalid CSRF token'], 403);
    }

    $slug = $input['slug'] ?? '';
    if (empty($slug)) {
        Helpers::jsonResponse(['error' => 'slug is required'], 400);
    }

    // Build update data from provided fields.
    $updateData = [];
    $allowedFields = ['title', 'content_html', 'meta_description', 'custom_css', 'custom_js'];
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateData[$field] = $input[$field];
        }
    }

    if (empty($updateData)) {
        Helpers::jsonResponse(['error' => 'No fields to update'], 400);
    }

    // Silent update — no version created (autosave is a draft buffer).
    $page = $app->getPages()->update($slug, $updateData);

    Helpers::jsonResponse([
        'success'    => true,
        'slug'       => $slug,
        'autosaved'  => true,
        'updated_at' => $page['updated_at'] ?? '',
    ]);

} catch (\Throwable $e) {
    Helpers::jsonResponse(['error' => $e->getMessage()], 500);
}
