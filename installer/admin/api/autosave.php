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

    if (!klytos_verify_csrf()) {
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
