<?php
/**
 * Klytos — Helper Functions
 * Utility functions used across the application.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class Helpers
{
    /**
     * Generate a cryptographically secure random hex string.
     *
     * @param  int    $bytes Number of random bytes (output will be 2x this length in hex).
     * @return string
     */
    public static function randomHex(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Generate a secure random token for MCP bearer auth.
     *
     * @return string 64-char hex string.
     */
    public static function generateBearerToken(): string
    {
        return self::randomHex(32);
    }

    /**
     * Hash a bearer token for storage (SHA-256).
     *
     * @param  string $token Raw token.
     * @return string SHA-256 hash.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Sanitize a slug for URL use.
     * Allows: lowercase alphanumeric, hyphens, forward slashes (for language prefixes).
     *
     * @param  string $slug
     * @return string
     */
    public static function sanitizeSlug(string $slug): string
    {
        $slug = mb_strtolower(trim($slug, '/'));
        $slug = preg_replace('/[^a-z0-9\-\/]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = preg_replace('/\/+/', '/', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }

    /**
     * Sanitize HTML content using an allowlist approach.
     * Strips dangerous tags/attributes while preserving safe HTML.
     *
     * @param  string $html
     * @return string
     */
    public static function sanitizeHtml(string $html): string
    {
        // Allowed tags for page content
        $allowed = '<h1><h2><h3><h4><h5><h6><p><br><hr><a><img><ul><ol><li>'
                 . '<table><thead><tbody><tr><th><td><strong><em><b><i><u><s>'
                 . '<blockquote><pre><code><span><div><section><article><header>'
                 . '<footer><nav><main><aside><figure><figcaption><video><audio>'
                 . '<source><iframe><form><input><textarea><button><select><option>'
                 . '<label><details><summary><mark><small><sub><sup><dl><dt><dd>';

        $clean = strip_tags($html, $allowed);

        // Remove event handler attributes (onclick, onerror, etc.)
        $clean = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $clean);
        $clean = preg_replace('/\s+on\w+\s*=\s*\S+/i', '', $clean);

        // Remove javascript: protocol in href/src
        $clean = preg_replace('/(?:href|src)\s*=\s*["\']?\s*javascript\s*:/i', 'href="#"', $clean);

        return $clean;
    }

    /**
     * Validate a hex color string.
     *
     * @param  string $color
     * @return bool
     */
    public static function isValidHexColor(string $color): bool
    {
        return (bool) preg_match('/^#[0-9a-fA-F]{3,8}$/', $color);
    }

    /**
     * Get the base URL path of the Klytos installation.
     * Auto-detects the subdirectory from the request.
     *
     * @return string Base path with trailing slash (e.g. '/klytos/' or '/cms/').
     */
    public static function getBasePath(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath   = dirname($scriptName);

        // Normalize
        $basePath = rtrim(str_replace('\\', '/', $basePath), '/') . '/';

        // Remove admin/ or other subdirectories from detection
        $basePath = preg_replace('#/(admin|public)/.*$#', '/', $basePath);

        return $basePath;
    }

    /**
     * Get the absolute filesystem path to the Klytos root directory.
     *
     * @return string
     */
    public static function getRootPath(): string
    {
        // Assume this file is in core/, so root is one level up
        return dirname(__DIR__);
    }

    /**
     * Get a relative URL from the Klytos base.
     *
     * @param  string $path Relative path (e.g. 'admin/login.php').
     * @return string Full relative URL.
     */
    public static function url(string $path = ''): string
    {
        return self::getBasePath() . ltrim($path, '/');
    }

    /**
     * Get the full site URL.
     *
     * @param  string $path Optional relative path.
     * @return string
     */
    public static function siteUrl(string $path = ''): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . self::url($path);
    }

    /**
     * Get the public site URL (domain root, WITHOUT the admin directory path).
     *
     * siteUrl() returns: https://klytos.io/28974823476283542/some/path
     * publicUrl() returns: https://klytos.io/some/path
     *
     * This is used for canonical URLs, sitemap, Open Graph, etc.
     * because the public HTML pages are at the web root, not inside the admin dir.
     *
     * @param  string $path Optional path to append.
     * @return string Full public URL.
     */
    public static function publicUrl( string $path = '' ): string
    {
        $scheme = ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url    = $scheme . '://' . $host . '/';

        if ( !empty( $path ) ) {
            $url .= ltrim( $path, '/' );
        }

        return $url;
    }

    /**
     * Get the domain name (without scheme or path).
     *
     * @return string
     */
    public static function getDomain(): string
    {
        return $_SERVER['HTTP_HOST'] ?? 'localhost';
    }

    /**
     * Send a JSON response and exit.
     *
     * @param  mixed $data
     * @param  int   $statusCode HTTP status code.
     * @return never
     */
    public static function jsonResponse(mixed $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Redirect to a URL and exit.
     *
     * @param  string $url
     * @param  int    $code HTTP redirect code (301, 302, 303).
     * @return never
     */
    public static function redirect(string $url, int $code = 302): never
    {
        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }

    /**
     * Get current ISO 8601 timestamp.
     *
     * @return string
     */
    public static function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c');
    }

    /**
     * Validate that required PHP extensions are loaded.
     *
     * @return array List of missing extensions (empty if all OK).
     */
    public static function checkRequirements(): array
    {
        $required = ['openssl', 'json', 'mbstring', 'session', 'curl', 'zip'];
        $missing  = [];

        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        return $missing;
    }

    /**
     * Ensure a directory is writable, optionally creating it.
     *
     * @param  string $dir
     * @return bool
     */
    public static function ensureWritableDir(string $dir): bool
    {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0700, true)) {
                return false;
            }
        }

        return is_writable($dir);
    }

    /**
     * Format bytes into human-readable size.
     *
     * @param  int $bytes
     * @return string
     */
    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i     = 0;
        $size  = (float) $bytes;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Get file extension in lowercase.
     *
     * @param  string $filename
     * @return string
     */
    public static function getExtension(string $filename): string
    {
        return mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Check if a file extension is allowed for upload.
     *
     * @param  string $filename
     * @return bool
     */
    public static function isAllowedUpload(string $filename): bool
    {
        $allowed = [
            'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico',
            'css', 'js',
            'pdf', 'zip',
            'woff', 'woff2', 'ttf', 'eot',
            'mp4', 'webm', 'mp3', 'ogg',
        ];

        return in_array(self::getExtension($filename), $allowed, true);
    }

    /**
     * Truncate a string to a max length at a word boundary.
     *
     * If the string is shorter than $maxLength, it is returned as-is.
     * Otherwise, it is truncated at the nearest word boundary before $maxLength
     * and an ellipsis (…) is appended.
     *
     * @param  string $text      The text to truncate.
     * @param  int    $maxLength Maximum character length (default 160).
     * @return string Truncated text.
     */
    public static function smartTruncate( string $text, int $maxLength = 160 ): string
    {
        $text = trim( $text );

        if ( mb_strlen( $text ) <= $maxLength ) {
            return $text;
        }

        // Cut at maxLength - 1 to leave room for ellipsis.
        $truncated = mb_substr( $text, 0, $maxLength - 1 );

        // Find the last space to avoid cutting mid-word.
        $lastSpace = mb_strrpos( $truncated, ' ' );
        if ( $lastSpace !== false && $lastSpace > $maxLength * 0.7 ) {
            $truncated = mb_substr( $truncated, 0, $lastSpace );
        }

        return $truncated . '…';
    }
}
