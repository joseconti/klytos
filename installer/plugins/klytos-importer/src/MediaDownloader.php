<?php

/**
 * MediaDownloader — Bulk media download with deduplication.
 *
 * Downloads external media files, validates them for security,
 * registers them via AssetManager, and returns a URL map for
 * content rewriting.
 *
 * @package KlytosImporter
 */

declare(strict_types=1);

namespace KlytosImporter;

class MediaDownloader
{
    /** Maximum file size per asset: 10MB. */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /** Maximum total media per session: 500MB. */
    private const MAX_SESSION_SIZE = 500 * 1024 * 1024;

    /** Download timeout per file: 30 seconds. */
    private const DOWNLOAD_TIMEOUT = 30;

    /** Allowed MIME types. */
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'image/avif', 'image/bmp', 'image/tiff',
        'video/mp4', 'video/webm', 'video/ogg',
        'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/webm',
        'application/pdf',
        'font/woff', 'font/woff2', 'font/ttf', 'font/otf',
        'application/font-woff', 'application/font-woff2',
    ];

    private ImportSession $session;

    /** @var object AssetManager instance. */
    private $assetManager;

    /** @var array Content hashes for deduplication within session. */
    private array $hashCache = [];

    public function __construct( ImportSession $session, $assetManager )
    {
        $this->session      = $session;
        $this->assetManager = $assetManager;
    }

    /**
     * Download a batch of media files and register them as Klytos assets.
     *
     * @param string      $sessionId Import session ID.
     * @param array       $mediaList Array of {src, alt, filename} objects.
     * @param string|null $baseUrl   Base URL to resolve relative paths.
     *
     * @return array {downloaded, failed, results[], url_map{}}
     */
    public function download( string $sessionId, array $mediaList, ?string $baseUrl = null ): array
    {
        $downloaded  = 0;
        $failed      = 0;
        $results     = [];
        $urlMap      = [];
        $totalSize   = 0;

        foreach ( $mediaList as $media ) {
            $src      = $media['src'] ?? '';
            $alt      = $media['alt'] ?? '';
            $filename = $media['filename'] ?? '';

            if ( empty( $src ) ) {
                continue;
            }

            // Resolve relative URLs.
            $absoluteUrl = $this->resolveUrl( $src, $baseUrl );
            if ( empty( $absoluteUrl ) ) {
                $results[] = [
                    'original_src' => $src,
                    'local_path'   => null,
                    'klytos_url'   => null,
                    'status'       => 'failed',
                    'error'        => 'Could not resolve URL.',
                ];
                $failed++;
                continue;
            }

            // Validate URL (SSRF prevention).
            if ( !ImportValidator::validateUrl( $absoluteUrl ) ) {
                $results[] = [
                    'original_src' => $src,
                    'local_path'   => null,
                    'klytos_url'   => null,
                    'status'       => 'failed',
                    'error'        => 'URL blocked by security policy.',
                ];
                $failed++;
                continue;
            }

            // Check session size limit.
            if ( $totalSize >= self::MAX_SESSION_SIZE ) {
                $results[] = [
                    'original_src' => $src,
                    'local_path'   => null,
                    'klytos_url'   => null,
                    'status'       => 'failed',
                    'error'        => 'Session media size limit reached (500MB).',
                ];
                $failed++;
                continue;
            }

            try {
                $result = $this->downloadFile( $absoluteUrl, $filename, $alt );

                if ( $result === null ) {
                    // Deduplicated — find the existing URL.
                    $results[] = [
                        'original_src' => $src,
                        'local_path'   => null,
                        'klytos_url'   => null,
                        'status'       => 'skipped',
                        'error'        => 'Duplicate content (already downloaded).',
                    ];
                    continue;
                }

                $totalSize += $result['size'];
                $urlMap[$src]         = $result['klytos_url'];
                $urlMap[$absoluteUrl] = $result['klytos_url'];

                $results[] = [
                    'original_src' => $src,
                    'local_path'   => $result['local_path'],
                    'klytos_url'   => $result['klytos_url'],
                    'status'       => 'ok',
                ];
                $downloaded++;
            } catch ( \Throwable $e ) {
                $results[] = [
                    'original_src' => $src,
                    'local_path'   => null,
                    'klytos_url'   => null,
                    'status'       => 'failed',
                    'error'        => $e->getMessage(),
                ];
                $failed++;
            }
        }

        // Store URL map in session.
        $sess = $this->session->get( $sessionId );
        $existingMap = $sess['media_map'] ?? [];
        $this->session->update( $sessionId, [
            'media_map' => array_merge( $existingMap, $urlMap ),
        ] );

        return [
            'success'    => true,
            'downloaded' => $downloaded,
            'failed'     => $failed,
            'results'    => $results,
            'url_map'    => $urlMap,
        ];
    }

    /**
     * Replace URLs in HTML content using a URL map.
     */
    public function rewriteUrls( string $html, array $urlMap ): string
    {
        if ( empty( $urlMap ) || empty( $html ) ) {
            return $html;
        }

        // Sort by key length descending to avoid partial replacements.
        uksort( $urlMap, fn( $a, $b ) => strlen( $b ) - strlen( $a ) );

        return str_replace( array_keys( $urlMap ), array_values( $urlMap ), $html );
    }

    /**
     * Download a single file, validate it, and register as asset.
     *
     * @return array|null {local_path, klytos_url, size} or null if deduplicated.
     */
    private function downloadFile( string $url, string $filename, string $alt ): ?array
    {
        // Rate limit.
        $host = parse_url( $url, PHP_URL_HOST ) ?? '';
        static $lastRequest = [];
        $now = microtime( true );
        if ( isset( $lastRequest[$host] ) && ( $now - $lastRequest[$host] ) < 1.0 ) {
            usleep( (int) ( ( 1.0 - ( $now - $lastRequest[$host] ) ) * 1_000_000 ) );
        }
        $lastRequest[$host] = microtime( true );

        // Download through SafeHttp, which validates every redirect hop. This
        // was the worst of the importer's three fetchers: it followed redirects
        // and never re-checked the destination even once, so a 302 into the
        // internal network wrote that response to disk as a "media asset" —
        // giving an attacker not just a blind request but the response body,
        // stored and servable.
        $result = klytos_safe_http()->fetch( $url, [
            'timeout' => self::DOWNLOAD_TIMEOUT,
            'headers' => [ 'User-Agent' => 'KlytosImporter/1.0' ],
        ] );

        if ( $result['blocked'] !== null ) {
            throw new \RuntimeException( 'Refused unsafe URL or redirect.' );
        }

        $body = $result['error'] === null ? $result['body'] : false;
        $code = $result['status'];
        $mime = $result['headers']['content-type'] ?? '';

        if ( $body === false || $code !== 200 ) {
            throw new \RuntimeException( "HTTP {$code}" );
        }

        $size = strlen( $body );

        // Size check.
        if ( $size > self::MAX_FILE_SIZE ) {
            throw new \RuntimeException( 'File exceeds 10MB limit.' );
        }

        if ( $size === 0 ) {
            throw new \RuntimeException( 'Empty file.' );
        }

        // Deduplication by content hash.
        $hash = md5( $body );
        if ( isset( $this->hashCache[$hash] ) ) {
            return null;
        }
        $this->hashCache[$hash] = true;

        // MIME type validation.
        $mimeBase = explode( ';', $mime ?? '' )[0];
        $mimeBase = trim( strtolower( $mimeBase ) );
        if ( !empty( $mimeBase ) && !in_array( $mimeBase, self::ALLOWED_MIMES, true ) ) {
            throw new \RuntimeException( "Disallowed MIME type: {$mimeBase}" );
        }

        // Generate safe filename.
        $safeName = $this->generateSafeFilename( $url, $filename, substr( $hash, 0, 8 ) );

        // Security checks on content.
        if ( !$this->isContentSafe( $body, $safeName ) ) {
            throw new \RuntimeException( 'File failed security scan.' );
        }

        // SVG sanitization.
        if ( str_ends_with( strtolower( $safeName ), '.svg' ) ) {
            $body = $this->sanitizeSvg( $body );
        }

        // Register via AssetManager (base64 upload).
        $base64  = base64_encode( $body );
        $result  = $this->assetManager->upload( $safeName, $base64 );

        $localPath = $result['path'] ?? "assets/images/{$safeName}";
        $klytosUrl = '/' . ltrim( $localPath, '/' );

        return [
            'local_path' => $localPath,
            'klytos_url' => $klytosUrl,
            'size'       => $size,
        ];
    }

    /**
     * Generate a safe, unique filename.
     */
    private function generateSafeFilename( string $url, string $requestedName, string $hash ): string
    {
        $name = '';

        if ( !empty( $requestedName ) ) {
            $name = pathinfo( $requestedName, PATHINFO_FILENAME );
            $ext  = pathinfo( $requestedName, PATHINFO_EXTENSION );
        } else {
            $path = parse_url( $url, PHP_URL_PATH ) ?? '';
            $name = pathinfo( $path, PATHINFO_FILENAME );
            $ext  = pathinfo( $path, PATHINFO_EXTENSION );
        }

        // Sanitize name.
        $name = preg_replace( '/[^a-z0-9\-_]/i', '-', $name );
        $name = preg_replace( '/-+/', '-', $name );
        $name = trim( $name, '-' );

        if ( empty( $name ) ) {
            $name = 'media';
        }

        // Sanitize extension.
        $ext = strtolower( preg_replace( '/[^a-z0-9]/i', '', $ext ?? '' ) );
        if ( empty( $ext ) ) {
            $ext = 'jpg';
        }

        // Reject dangerous extensions.
        $dangerous = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'phps', 'exe', 'sh', 'bat'];
        if ( in_array( $ext, $dangerous, true ) ) {
            $ext = 'bin';
        }

        return "{$name}-{$hash}.{$ext}";
    }

    /**
     * Verify file content is safe (no PHP injection).
     */
    private function isContentSafe( string $content, string $filename ): bool
    {
        // Check extension.
        if ( !ImportValidator::isFileSafe( $filename ) ) {
            // isFileSafe checks extension, but we're working with in-memory content.
            // Re-check dangerous extensions.
            $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
            $dangerous = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5'];
            if ( in_array( $ext, $dangerous, true ) ) {
                return false;
            }
        }

        // Scan first 8KB for PHP opening tags.
        $chunk = substr( $content, 0, 8192 );
        if ( str_contains( $chunk, '<?php' ) || str_contains( $chunk, '<?=' ) ) {
            return false;
        }

        return true;
    }

    /**
     * Sanitize SVG content by removing script tags and event handlers.
     */
    private function sanitizeSvg( string $svg ): string
    {
        // Remove <script> tags.
        $svg = preg_replace( '/<script[^>]*>.*?<\/script>/si', '', $svg );

        // Remove event handler attributes (on*).
        $svg = preg_replace( '/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $svg );

        // Remove javascript: URLs.
        $svg = preg_replace( '/href\s*=\s*["\']javascript:[^"\']*["\']/i', '', $svg );
        $svg = preg_replace( '/xlink:href\s*=\s*["\']javascript:[^"\']*["\']/i', '', $svg );

        return $svg;
    }

    /**
     * Resolve a potentially relative URL.
     */
    private function resolveUrl( string $href, ?string $baseUrl ): string
    {
        if ( preg_match( '#^https?://#i', $href ) ) {
            return $href;
        }

        if ( empty( $baseUrl ) ) {
            return '';
        }

        if ( str_starts_with( $href, '//' ) ) {
            $scheme = parse_url( $baseUrl, PHP_URL_SCHEME ) ?? 'https';
            return $scheme . ':' . $href;
        }

        if ( str_starts_with( $href, '/' ) ) {
            $parsed = parse_url( $baseUrl );
            return ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' ) . $href;
        }

        return rtrim( $baseUrl, '/' ) . '/' . $href;
    }
}
