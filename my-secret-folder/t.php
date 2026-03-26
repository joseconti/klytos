<?php
/**
 * Klytos — Analytics Tracking Endpoint
 * Receives page view events from the klytos-analytics.js script.
 *
 * This is a lightweight endpoint designed for speed:
 * - No session, no cookies, no output buffering.
 * - Responds with a 1x1 transparent GIF (pixel tracker fallback).
 * - Accepts Beacon API POST (preferred) or GET with query params.
 * - Records the page view via AnalyticsManager.
 *
 * Security:
 * - No cookies set (ever).
 * - IP address is hashed (SHA-256 + daily salt) — never stored raw.
 * - Input sanitized before processing.
 * - Rate limited: max 100 hits per IP per minute.
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

// ─── Fast Response ───────────────────────────────────────────
// Send response immediately, then process in the background.
// This ensures the tracking pixel doesn't slow down page loads.

// Disable error output (don't leak info to clients).
ini_set('display_errors', '0');

// ─── Send 1x1 transparent GIF response ──────────────────────
// This allows the endpoint to work as an image pixel (fallback).
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 1x1 transparent GIF (43 bytes).
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

// Flush output to the client immediately.
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ob_end_flush();
    flush();
}

// ─── Process the pageview (after response is sent) ───────────

$rootPath = __DIR__;

// Minimal autoloading — only what we need for analytics.
require_once $rootPath . '/core/encryption.php';
require_once $rootPath . '/core/storage-interface.php';
require_once $rootPath . '/core/file-storage.php';
require_once $rootPath . '/core/helpers.php';
require_once $rootPath . '/core/hooks.php';
require_once $rootPath . '/core/analytics-manager.php';

use Klytos\Core\Encryption;
use Klytos\Core\FileStorage;
use Klytos\Core\AnalyticsManager;

try {
    // Check if installed.
    $keyPath = $rootPath . '/config/.encryption_key';
    if (!file_exists($keyPath)) {
        exit; // Not installed yet.
    }

    // Initialize encryption and storage (file-based for t.php — always fast).
    $enc     = new Encryption($keyPath);
    $storage = new FileStorage($enc, $rootPath . '/data');

    // Extract tracking data from the request.
    // Beacon API sends POST with JSON body.
    // Fallback sends GET with query params.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true) ?? [];
    } else {
        $data = $_GET;
    }

    $pagePath    = $data['p'] ?? $data['path'] ?? '/';
    $referrer    = $data['r'] ?? $data['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    $screenWidth = (int) ($data['w'] ?? $data['width'] ?? 0);
    $ipAddress   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Record the page view.
    $analytics = new AnalyticsManager($storage);
    $analytics->recordPageView($pagePath, $referrer, $screenWidth, $ipAddress);

} catch (\Throwable $e) {
    // Silently fail — analytics should NEVER break the user's site.
    error_log('Klytos t.php error: ' . $e->getMessage());
}
