<?php

/**
 * Klytos — Helper Functions
 * Utility functions used across the application.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
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
     * Generate a short unique ID (hex string).
     *
     * Used for asset IDs and other records where a compact,
     * collision-resistant identifier is preferred over a full UUID.
     *
     * @param  int    $length Desired length in hex characters (default 8).
     * @return string
     */
    public static function generateShortId( int $length = 8 ): string
    {
        return substr( bin2hex( random_bytes( max( 4, (int) ceil( $length / 2 ) ) ) ), 0, $length );
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
     *
     * Transliterates accented/special characters to ASCII equivalents,
     * then strips anything that isn't alphanumeric, hyphen, or slash.
     * Allows: lowercase alphanumeric, hyphens, forward slashes (for hierarchical URLs).
     *
     * Examples:
     *   "Café & Música"      → "cafe-musica"
     *   "Über die Straße"    → "uber-die-strasse"
     *   "Pisos en España"    → "pisos-en-espana"
     *   "servicios/diseño"   → "servicios/diseno"
     *
     * @param  string $slug
     * @return string
     */
    public static function sanitizeSlug(string $slug): string
    {
        $slug = mb_strtolower(trim($slug, '/'));

        // Transliterate accented and special characters to ASCII.
        $slug = self::transliterate($slug);

        // Replace spaces and non-URL-safe characters with hyphens.
        $slug = preg_replace('/[^a-z0-9\-\/]/', '-', $slug);

        // Collapse multiple hyphens and slashes.
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = preg_replace('/\/+/', '/', $slug);

        // Trim hyphens from each path segment (avoid "/-segment-/" artifacts).
        $segments = explode('/', $slug);
        $segments = array_map(fn(string $s): string => trim($s, '-'), $segments);
        $segments = array_filter($segments, fn(string $s): bool => $s !== '');
        $slug = implode('/', $segments);

        return $slug;
    }

    /**
     * Transliterate Unicode characters to their ASCII equivalents.
     *
     * Uses iconv with //TRANSLIT when available, with a manual fallback
     * map for common Latin-script characters (Spanish, French, German,
     * Portuguese, Catalan, Italian, etc.).
     *
     * @param  string $str
     * @return string
     */
    private static function transliterate(string $str): string
    {
        // Manual map for reliable results across all PHP installations.
        static $map = [
            // Vowels with accents.
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ą' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ę' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            // Consonants.
            'ñ' => 'n', 'ń' => 'n',
            'ç' => 'c', 'ć' => 'c', 'č' => 'c',
            'ß' => 'ss',
            'ð' => 'd', 'ď' => 'd',
            'ğ' => 'g',
            'ł' => 'l', 'ľ' => 'l',
            'ř' => 'r',
            'š' => 's', 'ś' => 's', 'ş' => 's',
            'ť' => 't',
            'ž' => 'z', 'ź' => 'z', 'ż' => 'z',
            'þ' => 'th',
            // Ligatures and special.
            'æ' => 'ae', 'œ' => 'oe',
            // Currency and symbols (common in user input).
            '&' => '-', '@' => '-', '#' => '-',
        ];

        return strtr($str, $map);
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
     * Get the base URL path of the Klytos installation (the PUBLIC web root).
     *
     * The admin directory has a randomized name (e.g. "b2aa9a98e70d-admin")
     * that must NEVER appear in public URLs. This method computes the path
     * to the web root (parent of the admin directory) relative to DOCUMENT_ROOT.
     *
     * @return string Base path with trailing slash (e.g. '/prueba/' or '/').
     */
    public static function getBasePath(): string
    {
        // Prefer the base path persisted at install time — always correct
        // regardless of directory renames or hosting quirks.
        try {
            $config = App::getInstance()->getConfig();
            $installBase = $config['install_base'] ?? null;
            $adminDir    = $config['admin_dir'] ?? null;

            if ( $installBase !== null && $adminDir !== null ) {
                return rtrim( $installBase, '/' ) . '/' . $adminDir . '/';
            }
        } catch ( \Throwable ) {
            // App not booted yet (e.g. during installation) — fall through.
        }

        // Fallback: derive from filesystem paths.
        $klytosRoot = realpath( dirname( __DIR__ ) );
        $docRoot    = realpath( $_SERVER['DOCUMENT_ROOT'] ?? dirname( $klytosRoot ) );

        if ( $docRoot && $klytosRoot && str_starts_with( $klytosRoot, $docRoot ) ) {
            $basePath = substr( $klytosRoot, strlen( $docRoot ) );
        } else {
            // Last resort: parse SCRIPT_NAME.
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $basePath   = dirname( $scriptName );
            $basePath   = preg_replace( '#/(admin|public)(/.*)?$#', '', $basePath );
        }

        return rtrim( str_replace( '\\', '/', $basePath ), '/' ) . '/';
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
     * Get the public base path (web root, WITHOUT the admin directory).
     *
     * getBasePath() returns: /prueba/b2aa9a98e70d-admin/  (admin root)
     * getPublicBasePath() returns: /prueba/                (public root)
     *
     * @return string Public base path with trailing slash.
     */
    public static function getPublicBasePath(): string
    {
        // Prefer install_base from config — set at installation time.
        try {
            $config = App::getInstance()->getConfig();
            $installBase = $config['install_base'] ?? null;
            if ( $installBase !== null ) {
                return rtrim( $installBase, '/' ) . '/';
            }
        } catch ( \Throwable ) {
            // App not booted yet — fall through.
        }

        // Fallback: derive from DOCUMENT_ROOT.
        $klytosRoot = realpath( dirname( __DIR__ ) );
        $parentDir  = dirname( $klytosRoot );
        $docRoot    = realpath( $_SERVER['DOCUMENT_ROOT'] ?? $parentDir );

        if ( $docRoot && $parentDir && str_starts_with( $parentDir, $docRoot ) ) {
            $base = substr( $parentDir, strlen( $docRoot ) );
            return rtrim( str_replace( '\\', '/', $base ), '/' ) . '/';
        }

        return '/';
    }

    /**
     * Get the public site URL (web root, WITHOUT the admin directory path).
     *
     * siteUrl() returns: https://klytos.io/prueba/b2aa9a98e70d-admin/some/path
     * publicUrl() returns: https://klytos.io/prueba/some/path
     *
     * This is used for canonical URLs, sitemap, Open Graph, menus, etc.
     * because the public HTML pages are at the web root, not inside the admin dir.
     *
     * @param  string $path Optional path to append.
     * @return string Full public URL.
     */
    public static function publicUrl( string $path = '' ): string
    {
        $scheme   = ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $basePath = self::getPublicBasePath();
        $url      = $scheme . '://' . $host . $basePath;

        if ( !empty( $path ) ) {
            $url = rtrim( $url, '/' ) . '/' . ltrim( $path, '/' );
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
     * Sanitize a redirect URL to prevent open-redirect attacks.
     * Only allows relative URLs starting with a single slash.
     *
     * @param  string $url The URL to sanitize.
     * @return string      Safe URL or fallback to admin dashboard.
     */
    public static function sanitizeRedirectUrl( string $url ): string
    {
        $url = trim( $url );
        if ( $url === '' || !str_starts_with( $url, '/' ) || str_starts_with( $url, '//' ) ) {
            return self::url( 'admin/' );
        }
        return $url;
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
    public static function ensureWritableDir( string $dir, int $permissions = 0755 ): bool
    {
        if ( !is_dir( $dir ) ) {
            if ( !mkdir( $dir, $permissions, true ) ) {
                return false;
            }
        }

        return is_writable( $dir );
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

        // Allow plugins to extend or restrict the allowed extensions.
        $allowed = klytos_apply_filters('asset.allowed_types', $allowed);

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

    // ─── Output Escaping ─────────────────────────────────────────

    /**
     * Escape a string for safe output in an HTML context.
     *
     * Prevents XSS by converting special characters to HTML entities.
     * Safe against double-encoding: '&amp;' stays '&amp;', not '&amp;amp;'.
     *
     * @param  string $text Text to escape.
     * @return string Escaped text safe for HTML body content.
     */
    public static function escHtml( string $text ): string
    {
        return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
    }

    /**
     * Escape a string for safe use inside an HTML attribute.
     *
     * In addition to standard HTML escaping, strips tabs, newlines, and
     * carriage returns to prevent attribute-injection via whitespace.
     *
     * @param  string $text Text to escape.
     * @return string Escaped text safe for HTML attribute values.
     */
    public static function escAttr( string $text ): string
    {
        $text = preg_replace( '/[\t\n\r]/', '', $text );
        return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
    }

    /**
     * Escape a URL for safe output in an HTML context.
     *
     * Validates the protocol against an allowlist and rejects dangerous
     * schemes like javascript:, data:, and vbscript:. Returns an empty
     * string if the URL is invalid or uses a disallowed protocol.
     *
     * @param  string   $url       URL to escape.
     * @param  string[] $protocols Allowed protocols. Default: http, https, mailto, tel.
     * @return string   Escaped URL safe for href/src attributes, or empty string.
     */
    public static function escUrl( string $url, array $protocols = [ 'http', 'https', 'mailto', 'tel' ] ): string
    {
        $url = trim( $url );

        if ( $url === '' ) {
            return '';
        }

        // Strip NULL bytes and control characters.
        $url = preg_replace( '/[\x00-\x1f\x7f]/', '', $url );

        // Decode HTML entities first to prevent double-encoding bypass.
        $url = html_entity_decode( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );

        // Strip NULL bytes again after entity decode.
        $url = preg_replace( '/[\x00-\x1f\x7f]/', '', $url );

        // Allow fragment-only, root-relative, and protocol-relative URLs.
        if ( preg_match( '/^(#|\/[^\/]|\/{2})/', $url ) ) {
            return htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
        }

        // Reject dangerous protocols (case-insensitive, whitespace-stripped).
        $testUrl = preg_replace( '/\s/', '', strtolower( $url ) );
        $dangerous = [ 'javascript:', 'vbscript:', 'data:' ];
        foreach ( $dangerous as $scheme ) {
            if ( str_starts_with( $testUrl, $scheme ) ) {
                return '';
            }
        }

        // Validate protocol against allowlist.
        if ( preg_match( '#^([a-z][a-z0-9+.\-]*):#i', $url, $m ) ) {
            if ( ! in_array( strtolower( $m[1] ), $protocols, true ) ) {
                return '';
            }
        }

        return htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
    }

    /**
     * Escape a string for safe embedding inside a JavaScript string literal.
     *
     * Handles single quotes, double quotes, backslashes, newlines, and the
     * closing </script> sequence. Does NOT apply HTML escaping — this is
     * for JS context only.
     *
     * @param  string $string String to escape for JS.
     * @return string Escaped string safe for use inside JS quotes.
     */
    public static function escJs( string $string ): string
    {
        return str_replace(
            [ '\\',   "'",    '"',    "\n",  "\r",  '</' ],
            [ '\\\\', "\\'",  '\\"',  '\\n', '\\r', '<\\/' ],
            $string
        );
    }

    /**
     * Escape a string for safe output inside a <textarea> element.
     *
     * Functionally identical to escHtml() but kept separate so plugins
     * can apply independent filters for textarea contexts.
     *
     * @param  string $text Text to escape.
     * @return string Escaped text safe for textarea content.
     */
    public static function escTextarea( string $text ): string
    {
        return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false );
    }

    // ─── Input Sanitization ──────────────────────────────────────

    /**
     * Sanitize a plain-text input field.
     *
     * Strips all HTML tags, removes NULL bytes, normalizes whitespace
     * to single spaces, and trims leading/trailing whitespace.
     *
     * @param  string $text Raw input text.
     * @return string Sanitized plain text.
     */
    public static function sanitizeText( string $text ): string
    {
        $text = strip_tags( $text );
        $text = str_replace( "\0", '', $text );
        $text = preg_replace( '/\s+/', ' ', $text );
        return trim( $text );
    }

    /**
     * Sanitize an email address.
     *
     * Trims, lowercases, strips invalid characters, and validates the
     * result. Returns an empty string if the email is invalid.
     *
     * @param  string $email Raw email input.
     * @return string Sanitized email or empty string if invalid.
     */
    public static function sanitizeEmail( string $email ): string
    {
        $email = trim( strtolower( $email ) );
        $email = preg_replace( '/[^a-z0-9+_.@\-]/', '', $email );

        if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) === false ) {
            return '';
        }

        return $email;
    }

    /**
     * Sanitize a URL for safe storage.
     *
     * Strips control characters, validates the protocol, and returns an
     * empty string if the URL is invalid. Use escUrl() for output escaping.
     *
     * @param  string $url Raw URL input.
     * @return string Sanitized URL or empty string if invalid.
     */
    public static function sanitizeUrl( string $url ): string
    {
        $url = trim( $url );

        if ( $url === '' ) {
            return '';
        }

        // Strip control characters and NULL bytes.
        $url = preg_replace( '/[\x00-\x1f\x7f]/', '', $url );

        // Reject dangerous protocols.
        $testUrl = preg_replace( '/\s/', '', strtolower( $url ) );
        $dangerous = [ 'javascript:', 'vbscript:', 'data:' ];
        foreach ( $dangerous as $scheme ) {
            if ( str_starts_with( $testUrl, $scheme ) ) {
                return '';
            }
        }

        // Validate with filter_var.
        $sanitized = filter_var( $url, FILTER_SANITIZE_URL );
        if ( $sanitized === false || filter_var( $sanitized, FILTER_VALIDATE_URL ) === false ) {
            // Allow relative URLs (starting with / or #).
            if ( preg_match( '/^(\/|#)/', $url ) ) {
                return $url;
            }
            return '';
        }

        return $sanitized;
    }

    /**
     * Sanitize a filename for safe filesystem use.
     *
     * Strips directory components, replaces unsafe characters with
     * underscores, collapses runs, and provides a fallback name.
     *
     * @param  string $name Raw filename.
     * @return string Sanitized filename (basename only, no directory).
     */
    public static function sanitizeFilename( string $name ): string
    {
        // Strip directory components.
        $name = basename( $name );

        // Replace unsafe characters with underscore.
        $name = preg_replace( '/[^a-zA-Z0-9._\-]/', '_', $name );

        // Collapse multiple underscores.
        $name = preg_replace( '/_+/', '_', $name );

        // Trim leading/trailing underscores and dots.
        $name = trim( $name, '_.' );

        return $name !== '' ? $name : 'unnamed';
    }

    /**
     * Sanitize a string for use as an identifier key.
     *
     * Lowercases the string and strips everything except lowercase
     * alphanumerics, dashes, and underscores.
     *
     * @param  string $key Raw key string.
     * @return string Sanitized key.
     */
    public static function sanitizeKey( string $key ): string
    {
        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
    }

    /**
     * Sanitize a string for use as a display title / slug.
     *
     * Delegates to sanitizeSlug() for transliteration and cleanup.
     *
     * @param  string $title Raw title string.
     * @return string Sanitized slug suitable for URLs.
     */
    public static function sanitizeTitle( string $title ): string
    {
        return self::sanitizeSlug( $title );
    }

    /**
     * Sanitize a value to an integer.
     *
     * Accepts mixed input (string, float, int) and returns a safe int.
     *
     * @param  mixed $value Raw value.
     * @return int   Sanitized integer.
     */
    public static function sanitizeInt( mixed $value ): int
    {
        return (int) filter_var( $value, FILTER_SANITIZE_NUMBER_INT );
    }

    /**
     * Sanitize a value to a float.
     *
     * Accepts mixed input and returns a safe float. Allows decimal point.
     *
     * @param  mixed $value Raw value.
     * @return float Sanitized float.
     */
    public static function sanitizeFloat( mixed $value ): float
    {
        return (float) filter_var( $value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
    }

    // ─── HTML Filtering (KSES) ───────────────────────────────────

    /**
     * Default allowed tags for post/page content.
     *
     * Format: [ 'tag' => [ 'attr' => true, ... ], ... ]
     * Tags not in this list are stripped. Attributes not listed for a
     * tag are removed. Use ksesPost() for the convenience wrapper.
     */
    private static array $ksesPostTags = [
        'h1'         => [ 'id' => true, 'class' => true, 'style' => true ],
        'h2'         => [ 'id' => true, 'class' => true, 'style' => true ],
        'h3'         => [ 'id' => true, 'class' => true, 'style' => true ],
        'h4'         => [ 'id' => true, 'class' => true, 'style' => true ],
        'h5'         => [ 'id' => true, 'class' => true, 'style' => true ],
        'h6'         => [ 'id' => true, 'class' => true, 'style' => true ],
        'p'          => [ 'id' => true, 'class' => true, 'style' => true ],
        'br'         => [],
        'hr'         => [ 'class' => true ],
        'a'          => [ 'href' => true, 'title' => true, 'target' => true, 'rel' => true, 'class' => true, 'id' => true ],
        'img'        => [ 'src' => true, 'alt' => true, 'width' => true, 'height' => true, 'class' => true, 'loading' => true ],
        'ul'         => [ 'class' => true, 'style' => true ],
        'ol'         => [ 'class' => true, 'style' => true, 'start' => true, 'type' => true ],
        'li'         => [ 'class' => true, 'style' => true ],
        'table'      => [ 'class' => true, 'style' => true ],
        'thead'      => [],
        'tbody'      => [],
        'tr'         => [ 'class' => true ],
        'th'         => [ 'class' => true, 'style' => true, 'colspan' => true, 'rowspan' => true ],
        'td'         => [ 'class' => true, 'style' => true, 'colspan' => true, 'rowspan' => true ],
        'strong'     => [ 'class' => true ],
        'em'         => [ 'class' => true ],
        'b'          => [],
        'i'          => [ 'class' => true ],
        'u'          => [],
        's'          => [],
        'blockquote' => [ 'class' => true, 'cite' => true ],
        'pre'        => [ 'class' => true ],
        'code'       => [ 'class' => true ],
        'span'       => [ 'class' => true, 'style' => true, 'id' => true ],
        'div'        => [ 'class' => true, 'style' => true, 'id' => true ],
        'section'    => [ 'class' => true, 'style' => true, 'id' => true ],
        'article'    => [ 'class' => true, 'id' => true ],
        'header'     => [ 'class' => true, 'id' => true ],
        'footer'     => [ 'class' => true, 'id' => true ],
        'nav'        => [ 'class' => true, 'id' => true ],
        'main'       => [ 'class' => true, 'id' => true ],
        'aside'      => [ 'class' => true, 'id' => true ],
        'figure'     => [ 'class' => true ],
        'figcaption' => [ 'class' => true ],
        'video'      => [ 'src' => true, 'controls' => true, 'width' => true, 'height' => true, 'class' => true, 'poster' => true, 'preload' => true ],
        'audio'      => [ 'src' => true, 'controls' => true, 'class' => true, 'preload' => true ],
        'source'     => [ 'src' => true, 'type' => true ],
        'details'    => [ 'class' => true, 'open' => true ],
        'summary'    => [ 'class' => true ],
        'mark'       => [ 'class' => true ],
        'small'      => [],
        'sub'        => [],
        'sup'        => [],
        'dl'         => [ 'class' => true ],
        'dt'         => [ 'class' => true ],
        'dd'         => [ 'class' => true ],
    ];

    /**
     * Filter HTML through a tag/attribute allowlist (KSES).
     *
     * Uses DOMDocument for robust parsing. Tags not in the allowlist
     * are replaced by their text content. Attributes not in the tag's
     * allowlist are removed. href/src attributes are validated with escUrl().
     *
     * @param  string $html        HTML string to filter.
     * @param  array  $allowedTags Allowlist: [ 'tag' => [ 'attr' => true, ... ], ... ]
     * @return string Filtered HTML containing only allowed tags and attributes.
     */
    public static function kses( string $html, array $allowedTags ): string
    {
        if ( $html === '' ) {
            return '';
        }

        // Normalize tag names to lowercase.
        $allowed = [];
        foreach ( $allowedTags as $tag => $attrs ) {
            $allowed[ strtolower( $tag ) ] = array_change_key_case( $attrs, CASE_LOWER );
        }

        // Use DOMDocument for safe parsing.
        $doc = new \DOMDocument( '1.0', 'UTF-8' );

        // Suppress warnings from malformed HTML.
        $prev = libxml_use_internal_errors( true );

        // Wrap in a root element to handle fragments. Use UTF-8 meta for correct encoding.
        $wrapped = '<?xml encoding="UTF-8"><div id="kses-root">' . $html . '</div>';
        $doc->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

        libxml_clear_errors();
        libxml_use_internal_errors( $prev );

        // Find our wrapper element.
        $root = $doc->getElementById( 'kses-root' );
        if ( $root === null ) {
            return self::escHtml( $html );
        }

        // Recursively filter nodes.
        self::ksesFilterNode( $root, $allowed );

        // Serialize only the inner HTML of our wrapper.
        $output = '';
        foreach ( $root->childNodes as $child ) {
            $output .= $doc->saveHTML( $child );
        }

        return trim( $output );
    }

    /**
     * Recursively filter DOM nodes against the allowlist.
     *
     * @param  \DOMNode $node    Current node.
     * @param  array    $allowed Normalized allowlist.
     */
    private static function ksesFilterNode( \DOMNode $node, array $allowed ): void
    {
        // Collect children first (modifying during iteration breaks things).
        $children = [];
        foreach ( $node->childNodes as $child ) {
            $children[] = $child;
        }

        foreach ( $children as $child ) {
            if ( $child->nodeType === XML_ELEMENT_NODE ) {
                $tagName = strtolower( $child->nodeName );

                if ( ! isset( $allowed[ $tagName ] ) ) {
                    // Tag not allowed: replace with its children (preserve content).
                    self::ksesFilterNode( $child, $allowed );

                    // Move children to parent, before this node.
                    while ( $child->firstChild ) {
                        $node->insertBefore( $child->firstChild, $child );
                    }
                    $node->removeChild( $child );
                } else {
                    // Tag is allowed: filter its attributes.
                    $allowedAttrs = $allowed[ $tagName ];

                    // Collect attributes to remove (can't modify during iteration).
                    $removeAttrs = [];
                    foreach ( $child->attributes as $attr ) {
                        $attrName = strtolower( $attr->nodeName );

                        if ( ! isset( $allowedAttrs[ $attrName ] ) ) {
                            $removeAttrs[] = $attr->nodeName;
                            continue;
                        }

                        // Reject event handlers that slipped through.
                        if ( str_starts_with( $attrName, 'on' ) ) {
                            $removeAttrs[] = $attr->nodeName;
                            continue;
                        }

                        // Validate URL attributes.
                        if ( in_array( $attrName, [ 'href', 'src', 'action' ], true ) ) {
                            $safeUrl = self::escUrl( $attr->value );
                            if ( $safeUrl === '' ) {
                                $removeAttrs[] = $attr->nodeName;
                            } else {
                                // Decode entities since escUrl returns escaped — DOM re-escapes.
                                $attr->value = html_entity_decode( $safeUrl, ENT_QUOTES, 'UTF-8' );
                            }
                        }
                    }

                    foreach ( $removeAttrs as $attrName ) {
                        $child->removeAttribute( $attrName );
                    }

                    // Recurse into allowed children.
                    self::ksesFilterNode( $child, $allowed );
                }
            }
            // Text nodes and comments are kept as-is.
        }
    }

    /**
     * Filter HTML for post/page content with a sensible default allowlist.
     *
     * This is the primary function plugin developers should use when
     * displaying user-generated HTML content. It excludes dangerous tags
     * like <script>, <style>, <iframe>, <form>, <object>, <embed>, <svg>.
     *
     * The allowlist can be extended via the 'kses_post_allowed_tags' filter.
     *
     * @param  string $html HTML content to filter.
     * @return string Filtered HTML safe for display.
     */
    public static function ksesPost( string $html ): string
    {
        $tags = self::$ksesPostTags;

        // Allow plugins to extend the default allowlist.
        if ( function_exists( 'klytos_apply_filters' ) ) {
            $tags = klytos_apply_filters( 'kses_post_allowed_tags', $tags );
        }

        return self::kses( $html, $tags );
    }

    // ─── Form / CSRF Helpers ─────────────────────────────────────

    /**
     * Generate a hidden CSRF token field for forms.
     *
     * Returns a complete <input> element ready to echo inside a <form>.
     *
     * @return string HTML hidden input with the current CSRF token.
     */
    public static function csrfField(): string
    {
        $token = App::getInstance()->getAuth()->getCsrfToken();
        return '<input type="hidden" name="csrf" value="' . self::escAttr( $token ) . '">';
    }

    /**
     * Verify the CSRF token from the current request.
     *
     * Checks $_POST['csrf'] first, then the X-CSRF-Token header (for
     * AJAX requests), then $_GET['csrf'] as last resort.
     *
     * @return bool True if the token is valid.
     */
    public static function verifyCsrf(): bool
    {
        $token = $_POST['csrf']
              ?? $_SERVER['HTTP_X_CSRF_TOKEN']
              ?? $_GET['csrf']
              ?? '';

        return App::getInstance()->getAuth()->validateCsrf( $token );
    }

    // ─── Validation Helpers ──────────────────────────────────────

    /**
     * Check if a string is a valid email address.
     *
     * @param  string $email Email to validate.
     * @return bool   True if valid.
     */
    public static function isEmail( string $email ): bool
    {
        return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
    }

    /**
     * Check if a string is a valid URL (http or https).
     *
     * @param  string $url URL to validate.
     * @return bool   True if valid.
     */
    public static function isUrl( string $url ): bool
    {
        if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
            return false;
        }

        $scheme = strtolower( parse_url( $url, PHP_URL_SCHEME ) ?? '' );
        return in_array( $scheme, [ 'http', 'https' ], true );
    }

    /**
     * Terminate execution with a safe error page.
     *
     * Outputs a minimal HTML error page with escaped content, fires the
     * 'klytos_die' action for plugin override, and exits.
     *
     * @param  string $message Error message (plain text or pre-escaped HTML).
     * @param  string $title   Page title. Default: 'Error'.
     * @param  int    $status  HTTP status code. Default: 500.
     * @return never
     */
    public static function klytoDie( string $message, string $title = 'Error', int $status = 500 ): never
    {
        // Allow plugins to intercept.
        if ( function_exists( 'klytos_do_action' ) ) {
            klytos_do_action( 'klytos_die', $message, $title, $status );
        }

        http_response_code( $status );

        $safeTitle   = self::escHtml( $title );
        $safeMessage = self::escHtml( $message );

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>' . $safeTitle . '</title>'
           . '<style>body{font-family:system-ui,sans-serif;display:flex;justify-content:center;'
           . 'align-items:center;min-height:100vh;margin:0;background:#f5f5f5;color:#333}'
           . '.box{max-width:500px;padding:2rem;background:#fff;border-radius:8px;'
           . 'box-shadow:0 2px 8px rgba(0,0,0,.1);text-align:center}'
           . 'h1{margin:0 0 1rem;font-size:1.25rem}</style></head>'
           . '<body><div class="box"><h1>' . $safeTitle . '</h1>'
           . '<p>' . $safeMessage . '</p></div></body></html>';

        exit;
    }
}
