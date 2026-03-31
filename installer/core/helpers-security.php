<?php

/**
 * Klytos — Security Helper Functions
 * Global sanitization, escaping, and validation functions for plugin developers.
 *
 * These functions provide a WordPress-style security API. Plugin developers
 * should use these instead of raw PHP functions like htmlspecialchars().
 *
 * All functions are prefixed with 'klytos_' to avoid naming collisions.
 * Each wraps a static method in \Klytos\Core\Helpers for OOP usage.
 *
 * This file is loaded by App::boot() BEFORE plugins are loaded,
 * so these functions are available in every plugin's init.php.
 *
 * @package Klytos
 * @since   0.7.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

use Klytos\Core\Helpers;

// ─── Output Escaping ─────────────────────────────────────────

/**
 * Escape a string for safe output in an HTML context.
 *
 * Use this whenever echoing user-provided or dynamic text inside HTML body.
 * Safe against double-encoding.
 *
 * @param  string $text Text to escape.
 * @return string Escaped text.
 */
function klytos_esc_html( string $text ): string
{
    return Helpers::escHtml( $text );
}

/**
 * Escape a string for safe use inside an HTML attribute.
 *
 * In addition to HTML escaping, strips tabs/newlines to prevent
 * attribute-injection via whitespace.
 *
 * @param  string $text Text to escape.
 * @return string Escaped text safe for attribute values.
 */
function klytos_esc_attr( string $text ): string
{
    return Helpers::escAttr( $text );
}

/**
 * Escape a URL for safe output in href/src attributes.
 *
 * Validates the protocol against an allowlist. Returns empty string
 * for dangerous URLs (javascript:, data:, vbscript:).
 *
 * @param  string   $url       URL to escape.
 * @param  string[] $protocols Allowed protocols. Default: http, https, mailto, tel.
 * @return string   Escaped URL or empty string if invalid.
 */
function klytos_esc_url( string $url, array $protocols = [ 'http', 'https', 'mailto', 'tel' ] ): string
{
    return Helpers::escUrl( $url, $protocols );
}

/**
 * Escape a string for safe embedding inside a JavaScript string literal.
 *
 * Does NOT apply HTML escaping — this is for JS context only.
 *
 * @param  string $string String to escape for JS.
 * @return string Escaped string safe inside JS quotes.
 */
function klytos_esc_js( string $string ): string
{
    return Helpers::escJs( $string );
}

/**
 * Escape a string for safe output inside a <textarea> element.
 *
 * @param  string $text Text to escape.
 * @return string Escaped text safe for textarea content.
 */
function klytos_esc_textarea( string $text ): string
{
    return Helpers::escTextarea( $text );
}

// ─── Input Sanitization ──────────────────────────────────────

/**
 * Sanitize a plain-text input field.
 *
 * Strips all HTML tags, removes NULL bytes, normalizes whitespace, and trims.
 *
 * @param  string $text Raw input text.
 * @return string Sanitized plain text.
 */
function klytos_sanitize_text( string $text ): string
{
    return Helpers::sanitizeText( $text );
}

/**
 * Sanitize an email address.
 *
 * Returns empty string if the email is invalid.
 *
 * @param  string $email Raw email input.
 * @return string Sanitized email or empty string.
 */
function klytos_sanitize_email( string $email ): string
{
    return Helpers::sanitizeEmail( $email );
}

/**
 * Sanitize a URL for safe storage.
 *
 * Rejects dangerous protocols. Returns empty string if invalid.
 * Use klytos_esc_url() for output escaping.
 *
 * @param  string $url Raw URL input.
 * @return string Sanitized URL or empty string.
 */
function klytos_sanitize_url( string $url ): string
{
    return Helpers::sanitizeUrl( $url );
}

/**
 * Sanitize a filename for safe filesystem use.
 *
 * Strips directory components and replaces unsafe characters.
 *
 * @param  string $name Raw filename.
 * @return string Sanitized filename (basename only).
 */
function klytos_sanitize_filename( string $name ): string
{
    return Helpers::sanitizeFilename( $name );
}

/**
 * Sanitize a string for use as an identifier key.
 *
 * Lowercase, alphanumeric, dashes, and underscores only.
 *
 * @param  string $key Raw key string.
 * @return string Sanitized key.
 */
function klytos_sanitize_key( string $key ): string
{
    return Helpers::sanitizeKey( $key );
}

/**
 * Sanitize a string for use as a display title / slug.
 *
 * @param  string $title Raw title string.
 * @return string Sanitized slug.
 */
function klytos_sanitize_title( string $title ): string
{
    return Helpers::sanitizeTitle( $title );
}

/**
 * Sanitize HTML content using an allowlist approach.
 *
 * Wrapper for Helpers::sanitizeHtml(). Strips dangerous tags/attributes
 * while preserving safe HTML.
 *
 * @param  string $html Raw HTML content.
 * @return string Sanitized HTML.
 */
function klytos_sanitize_html( string $html ): string
{
    return Helpers::sanitizeHtml( $html );
}

/**
 * Sanitize a value to an integer.
 *
 * @param  mixed $value Raw value.
 * @return int   Sanitized integer.
 */
function klytos_sanitize_int( mixed $value ): int
{
    return Helpers::sanitizeInt( $value );
}

/**
 * Sanitize a value to a float.
 *
 * @param  mixed $value Raw value.
 * @return float Sanitized float.
 */
function klytos_sanitize_float( mixed $value ): float
{
    return Helpers::sanitizeFloat( $value );
}

// ─── HTML Filtering (KSES) ───────────────────────────────────

/**
 * Filter HTML through a tag/attribute allowlist (KSES).
 *
 * Uses DOMDocument for robust parsing. Tags not in the allowlist are
 * replaced by their content. Attributes not in the tag's allowlist
 * are removed. href/src attributes are validated with escUrl().
 *
 * Format: [ 'a' => [ 'href' => true, 'title' => true ], 'strong' => [] ]
 *
 * @param  string $html        HTML to filter.
 * @param  array  $allowedTags Allowlist of tags and their allowed attributes.
 * @return string Filtered HTML.
 */
function klytos_kses( string $html, array $allowedTags ): string
{
    return Helpers::kses( $html, $allowedTags );
}

/**
 * Filter HTML for post/page content with a sensible default allowlist.
 *
 * Excludes dangerous tags (<script>, <style>, <iframe>, <form>, etc.).
 * The allowlist can be extended via the 'kses_post_allowed_tags' filter.
 *
 * @param  string $html HTML content to filter.
 * @return string Filtered HTML safe for display.
 */
function klytos_kses_post( string $html ): string
{
    return Helpers::ksesPost( $html );
}

// ─── Form / CSRF Helpers ─────────────────────────────────────

/**
 * Generate a hidden CSRF token field for forms.
 *
 * Returns a complete <input> element. Echo inside a <form>.
 *
 * @return string HTML hidden input with the CSRF token.
 */
function klytos_csrf_field(): string
{
    return Helpers::csrfField();
}

/**
 * Verify the CSRF token from the current request.
 *
 * Checks POST, X-CSRF-Token header, and GET (in that order).
 *
 * @return bool True if the token is valid.
 */
function klytos_verify_csrf(): bool
{
    return Helpers::verifyCsrf();
}

// ─── Validation Helpers ──────────────────────────────────────

/**
 * Check if a string is a valid email address.
 *
 * @param  string $email Email to validate.
 * @return bool   True if valid.
 */
function klytos_is_email( string $email ): bool
{
    return Helpers::isEmail( $email );
}

/**
 * Check if a string is a valid URL (http or https).
 *
 * @param  string $url URL to validate.
 * @return bool   True if valid.
 */
function klytos_is_url( string $url ): bool
{
    return Helpers::isUrl( $url );
}

// ─── Safe Error Page ─────────────────────────────────────────

/**
 * Terminate execution with a safe error page.
 *
 * Fires the 'klytos_die' action for plugin override before output.
 *
 * @param  string $message Error message.
 * @param  string $title   Page title. Default: 'Error'.
 * @param  int    $status  HTTP status code. Default: 500.
 * @return never
 */
function klytos_die( string $message, string $title = 'Error', int $status = 500 ): never
{
    Helpers::klytoDie( $message, $title, $status );
}
