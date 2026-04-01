<?php

/**
 * ImportValidator — Input validation and security checks.
 *
 * Provides static methods for URL validation (SSRF prevention),
 * XML file validation, session ID validation, and HTML sanitization.
 *
 * @package KlytosImporter
 */

declare( strict_types=1 );

namespace KlytosImporter;

class ImportValidator
{
    /**
     * Allowed HTML tags for content sanitization.
     */
    private const ALLOWED_TAGS = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'p', 'br', 'hr',
        'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'mark', 'small', 'sub', 'sup',
        'a', 'img', 'figure', 'figcaption',
        'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
        'blockquote', 'pre', 'code', 'kbd', 'samp',
        'div', 'span', 'section', 'article', 'main', 'aside', 'header', 'footer', 'nav',
        'video', 'audio', 'source', 'iframe',
        'details', 'summary',
        'dl', 'dt', 'dd',
        'abbr', 'cite', 'q', 'time',
    ];

    /**
     * Allowed HTML attributes.
     */
    private const ALLOWED_ATTRS = [
        'href', 'src', 'alt', 'title', 'class', 'id', 'style',
        'width', 'height', 'target', 'rel',
        'colspan', 'rowspan', 'scope', 'headers',
        'controls', 'autoplay', 'loop', 'muted', 'preload', 'poster',
        'type', 'start', 'reversed',
        'datetime', 'cite', 'data-*',
        'loading', 'decoding', 'fetchpriority', 'srcset', 'sizes',
        'allow', 'allowfullscreen', 'frameborder',
        'open',
    ];

    /**
     * Validate a URL for import usage.
     *
     * Ensures HTTP/HTTPS scheme and rejects private/reserved IPs (SSRF prevention).
     */
    public static function validateUrl( string $url ): bool
    {
        $parsed = parse_url( $url );

        if ( $parsed === false || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
            return false;
        }

        // Only HTTP and HTTPS allowed.
        if ( !in_array( strtolower( $parsed['scheme'] ), ['http', 'https'], true ) ) {
            return false;
        }

        $host = $parsed['host'];

        // Resolve hostname to check for private IPs.
        $ips = gethostbynamel( $host );
        if ( $ips === false ) {
            return false; // DNS resolution failed.
        }

        foreach ( $ips as $ip ) {
            if ( self::isPrivateIp( $ip ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if an IP address is private, reserved, or loopback.
     */
    public static function isPrivateIp( string $ip ): bool
    {
        // FILTER_VALIDATE_IP with private/reserved flags.
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        return filter_var( $ip, FILTER_VALIDATE_IP, $flags ) === false;
    }

    /**
     * Validate an uploaded XML file.
     */
    public static function validateXmlFile( string $path ): bool
    {
        if ( !file_exists( $path ) || !is_readable( $path ) ) {
            return false;
        }

        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        if ( $ext !== 'xml' ) {
            return false;
        }

        // Check file size (max 500MB).
        $size = filesize( $path );
        if ( $size === false || $size === 0 || $size > 500 * 1024 * 1024 ) {
            return false;
        }

        // Peek at the first bytes for XML declaration or root tag.
        $handle = fopen( $path, 'rb' );
        if ( $handle === false ) {
            return false;
        }

        $header = fread( $handle, 1024 );
        fclose( $handle );

        if ( $header === false ) {
            return false;
        }

        // Must contain XML declaration or an opening tag.
        return str_contains( $header, '<?xml' ) || str_contains( $header, '<rss' ) || str_contains( $header, '<wp:' );
    }

    /**
     * Validate a session ID format.
     */
    public static function validateSessionId( string $id ): bool
    {
        return (bool) preg_match( '/^imp_[a-f0-9]{12}$/', $id );
    }

    /**
     * Sanitize HTML content with a strict allowlist.
     */
    public static function sanitizeHtml( string $html ): string
    {
        if ( empty( $html ) ) {
            return '';
        }

        $allowedTagStr = '<' . implode( '><', self::ALLOWED_TAGS ) . '>';
        $html = strip_tags( $html, $allowedTagStr );

        // Use DOMDocument to sanitize attributes.
        $doc = new \DOMDocument( '1.0', 'UTF-8' );

        // Suppress warnings for malformed HTML.
        $prev = libxml_use_internal_errors( true );
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );

        self::sanitizeAttributes( $doc->documentElement );

        $result = '';
        $wrapper = $doc->getElementsByTagName( 'div' )->item( 0 );
        if ( $wrapper ) {
            foreach ( $wrapper->childNodes as $child ) {
                $result .= $doc->saveHTML( $child );
            }
        }

        return trim( $result );
    }

    /**
     * Recursively remove disallowed attributes and dangerous values.
     */
    private static function sanitizeAttributes( \DOMNode $node ): void
    {
        if ( $node->nodeType !== XML_ELEMENT_NODE ) {
            return;
        }

        /** @var \DOMElement $node */
        $removeAttrs = [];

        foreach ( $node->attributes as $attr ) {
            $name  = strtolower( $attr->name );
            $value = $attr->value;

            // Remove event handlers (on*).
            if ( str_starts_with( $name, 'on' ) ) {
                $removeAttrs[] = $attr->name;
                continue;
            }

            // Remove javascript: URLs.
            if ( in_array( $name, ['href', 'src', 'action'], true ) ) {
                $cleaned = trim( strtolower( preg_replace( '/\s+/', '', $value ) ) );
                if ( str_starts_with( $cleaned, 'javascript:' ) || str_starts_with( $cleaned, 'data:text/html' ) ) {
                    $removeAttrs[] = $attr->name;
                    continue;
                }
            }

            // Check against allowed attribute patterns.
            $allowed = false;
            foreach ( self::ALLOWED_ATTRS as $pattern ) {
                if ( str_ends_with( $pattern, '*' ) ) {
                    $prefix = substr( $pattern, 0, -1 );
                    if ( str_starts_with( $name, $prefix ) ) {
                        $allowed = true;
                        break;
                    }
                } elseif ( $name === $pattern ) {
                    $allowed = true;
                    break;
                }
            }

            if ( !$allowed ) {
                $removeAttrs[] = $attr->name;
            }
        }

        foreach ( $removeAttrs as $attrName ) {
            $node->removeAttribute( $attrName );
        }

        // Recurse into child elements.
        foreach ( $node->childNodes as $child ) {
            self::sanitizeAttributes( $child );
        }
    }

    /**
     * Validate that a downloaded file does not contain PHP code.
     */
    public static function isFileSafe( string $filePath ): bool
    {
        // Reject PHP extensions.
        $ext = strtolower( pathinfo( $filePath, PATHINFO_EXTENSION ) );
        $dangerous = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'phps'];
        if ( in_array( $ext, $dangerous, true ) ) {
            return false;
        }

        // Scan first 8KB for PHP opening tags.
        $handle = fopen( $filePath, 'rb' );
        if ( $handle === false ) {
            return false;
        }

        $chunk = fread( $handle, 8192 );
        fclose( $handle );

        if ( $chunk === false ) {
            return false;
        }

        if ( str_contains( $chunk, '<?php' ) || str_contains( $chunk, '<?=' ) ) {
            return false;
        }

        return true;
    }
}
