<?php

/**
 * PageFetcher — PHP cURL page fetcher and site crawler.
 *
 * Downloads web pages for content extraction. When no sitemap or export
 * is available, the discover() method performs BFS crawling to build
 * a site map by following internal links.
 *
 * Security: SSRF prevention via IP validation, robots.txt respect,
 * rate limiting per domain (1 req/sec minimum).
 *
 * @package KlytosImporter
 */

declare(strict_types=1);

namespace KlytosImporter;

class PageFetcher
{
    /**
     * Per-domain timestamps of last request (for rate limiting).
     * @var array<string, float>
     */
    private static array $domainTimestamps = [];

    /**
     * Cached robots.txt rules per domain.
     * @var array<string, array>
     */
    private static array $robotsCache = [];

    /**
     * Fetch a single page.
     *
     * @return array {status_code, html, headers, final_url}
     */
    public function fetch( string $url ): array
    {
        if ( !ImportValidator::validateUrl( $url ) ) {
            throw new \InvalidArgumentException( "Invalid or unsafe URL: {$url}" );
        }

        $this->rateLimit( $url );

        // Through SafeHttp, which validates EVERY redirect hop before following
        // it. What this replaced followed redirects with CURLOPT_FOLLOWLOCATION
        // and then checked the final URL afterwards — by which point the body
        // had already been fetched from whatever internal host the redirect
        // pointed at, and the intermediate hops had never been looked at at
        // all. That is the exact pattern D-041 names as unsound, and it cited
        // this very method as the example.
        //
        // The `CURLOPT_RESOLVE, []` line that used to sit here was labelled
        // "DNS resolution SSRF check" and was a no-op — an empty array pins
        // nothing. It is removed rather than left to reassure the next reader
        // about a protection that never existed (L-002).
        $result = klytos_safe_http()->fetch( $url, [
            'timeout' => 30,
            'headers' => [ 'User-Agent' => 'KlytosImporter/1.0' ],
        ] );

        if ( $result['blocked'] !== null ) {
            throw new \RuntimeException( "Refused unsafe URL or redirect while fetching {$url}" );
        }

        if ( $result['error'] !== null ) {
            throw new \RuntimeException( "HTTP error fetching {$url}: {$result['error']}" );
        }

        return [
            'status_code' => $result['status'],
            'html'        => $result['body'],
            'headers'     => $result['headers'],
            'final_url'   => $result['final_url'],
        ];
    }

    /**
     * Check if a URL is allowed by the site's robots.txt.
     */
    public function respectsRobotsTxt( string $url ): bool
    {
        $parsed = parse_url( $url );
        $domain = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' );
        $path   = $parsed['path'] ?? '/';

        if ( !isset( self::$robotsCache[$domain] ) ) {
            self::$robotsCache[$domain] = $this->fetchRobotsTxt( $domain );
        }

        $rules = self::$robotsCache[$domain];

        foreach ( $rules as $rule ) {
            if ( str_starts_with( $path, $rule ) ) {
                return false; // Disallowed.
            }
        }

        return true;
    }

    /**
     * Discover site pages by BFS crawling from a start URL.
     *
     * @param string   $startUrl        Homepage or starting URL.
     * @param int      $maxDepth        Maximum link depth to follow (default 3).
     * @param int      $maxPages        Maximum pages to discover (default 100).
     * @param string[] $includePatterns URL patterns to include (regex).
     * @param string[] $excludePatterns URL patterns to exclude (regex).
     *
     * @return array {site_url, total_discovered, pages[], tree{}}
     */
    public function discover(
        string $startUrl,
        int $maxDepth = 3,
        int $maxPages = 100,
        array $includePatterns = [],
        array $excludePatterns = []
    ): array {
        if ( !ImportValidator::validateUrl( $startUrl ) ) {
            throw new \InvalidArgumentException( "Invalid or unsafe start URL: {$startUrl}" );
        }

        $parsed  = parse_url( $startUrl );
        $baseUrl = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' );

        // BFS queue: [url, depth].
        $queue   = [[$startUrl, 0]];
        $visited = [];
        $pages   = [];
        $tree    = [];

        while ( !empty( $queue ) && count( $pages ) < $maxPages ) {
            [$currentUrl, $depth] = array_shift( $queue );

            // Normalize URL.
            $normalUrl = $this->normalizeUrl( $currentUrl, $baseUrl );
            if ( $normalUrl === null || isset( $visited[$normalUrl] ) ) {
                continue;
            }
            $visited[$normalUrl] = true;

            // Check robots.txt.
            if ( !$this->respectsRobotsTxt( $normalUrl ) ) {
                continue;
            }

            // Check include/exclude patterns.
            if ( !$this->matchesPatterns( $normalUrl, $includePatterns, $excludePatterns ) ) {
                continue;
            }

            // Fetch the page.
            try {
                $result = $this->fetch( $normalUrl );
            } catch ( \Throwable ) {
                continue;
            }

            if ( $result['status_code'] !== 200 ) {
                continue;
            }

            $html = $result['html'];

            // Extract title.
            $title = '';
            if ( preg_match( '/<title[^>]*>(.*?)<\/title>/si', $html, $m ) ) {
                $title = html_entity_decode( trim( $m[1] ), ENT_QUOTES, 'UTF-8' );
            }

            // Extract internal links.
            $internalLinks = $this->extractInternalLinks( $html, $baseUrl );

            $path          = parse_url( $normalUrl, PHP_URL_PATH ) ?? '/';
            $suggestedSlug = $this->pathToSlug( $path );
            $parser        = new SitemapParser( $normalUrl, 1 ); // reuse classifyUrl

            $pages[] = [
                'url'            => $normalUrl,
                'title'          => $title,
                'depth'          => $depth,
                'internal_links' => count( $internalLinks ),
                'suggested_slug' => $suggestedSlug,
                'suggested_type' => $parser->classifyUrl( $normalUrl ),
            ];

            // Build tree.
            $childPaths = [];
            foreach ( $internalLinks as $link ) {
                $childPath = parse_url( $link, PHP_URL_PATH ) ?? '/';
                $childPaths[] = $childPath;
            }
            $tree[$path] = [
                'children' => array_slice( array_unique( $childPaths ), 0, 20 ),
                'title'    => $title,
            ];

            // Add children to queue if within depth.
            if ( $depth < $maxDepth ) {
                foreach ( $internalLinks as $link ) {
                    if ( !isset( $visited[$link] ) && count( $queue ) < $maxPages * 3 ) {
                        $queue[] = [$link, $depth + 1];
                    }
                }
            }
        }

        return [
            'success'          => true,
            'source'           => 'crawl',
            'site_url'         => $baseUrl,
            'total_discovered' => count( $pages ),
            'pages'            => $pages,
            'tree'             => $tree,
        ];
    }

    /**
     * Extract all internal links from HTML.
     *
     * @return string[] Array of absolute internal URLs.
     */
    private function extractInternalLinks( string $html, string $baseUrl ): array
    {
        $links = [];

        if ( !preg_match_all( '/href=["\']([^"\'#]+)/i', $html, $matches ) ) {
            return $links;
        }

        $baseParsed = parse_url( $baseUrl );
        $baseHost   = $baseParsed['host'] ?? '';

        foreach ( $matches[1] as $href ) {
            $href = trim( $href );

            // Skip non-page resources.
            if ( preg_match( '/\.(css|js|png|jpg|jpeg|gif|svg|webp|ico|pdf|zip|xml|json|woff2?|ttf|eot)$/i', $href ) ) {
                continue;
            }

            // Skip mailto, tel, javascript.
            if ( preg_match( '/^(mailto|tel|javascript):/i', $href ) ) {
                continue;
            }

            // Skip query-string-only links.
            if ( str_starts_with( $href, '?' ) ) {
                continue;
            }

            // Resolve relative URLs.
            $absolute = $this->resolveUrl( $href, $baseUrl );
            if ( $absolute === null ) {
                continue;
            }

            // Must be same domain.
            $hrefParsed = parse_url( $absolute );
            if ( ( $hrefParsed['host'] ?? '' ) !== $baseHost ) {
                continue;
            }

            // Remove query strings and fragments.
            $clean = ( $hrefParsed['scheme'] ?? 'https' ) . '://' . $hrefParsed['host'] . ( $hrefParsed['path'] ?? '/' );

            $links[] = $clean;
        }

        return array_values( array_unique( $links ) );
    }

    /**
     * Resolve a potentially relative URL against a base URL.
     */
    private function resolveUrl( string $href, string $baseUrl ): ?string
    {
        // Already absolute.
        if ( preg_match( '#^https?://#i', $href ) ) {
            return $href;
        }

        // Protocol-relative.
        if ( str_starts_with( $href, '//' ) ) {
            $scheme = parse_url( $baseUrl, PHP_URL_SCHEME ) ?? 'https';
            return $scheme . ':' . $href;
        }

        // Absolute path.
        if ( str_starts_with( $href, '/' ) ) {
            return $baseUrl . $href;
        }

        // Relative path.
        return $baseUrl . '/' . $href;
    }

    /**
     * Normalize a URL by removing fragments and trailing slashes.
     */
    private function normalizeUrl( string $url, string $baseUrl ): ?string
    {
        // Remove fragment.
        $pos = strpos( $url, '#' );
        if ( $pos !== false ) {
            $url = substr( $url, 0, $pos );
        }

        if ( empty( $url ) ) {
            return null;
        }

        // Resolve relative.
        $url = $this->resolveUrl( $url, $baseUrl );
        if ( $url === null ) {
            return null;
        }

        // Validate.
        if ( !ImportValidator::validateUrl( $url ) ) {
            return null;
        }

        return rtrim( $url, '/' );
    }

    /**
     * Check if a URL matches include/exclude patterns.
     */
    private function matchesPatterns( string $url, array $include, array $exclude ): bool
    {
        // If include patterns exist, URL must match at least one.
        if ( !empty( $include ) ) {
            $matched = false;
            foreach ( $include as $pattern ) {
                if ( @preg_match( $pattern, $url ) ) {
                    $matched = true;
                    break;
                }
            }
            if ( !$matched ) {
                return false;
            }
        }

        // URL must not match any exclude pattern.
        foreach ( $exclude as $pattern ) {
            if ( @preg_match( $pattern, $url ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert a URL path to a Klytos slug.
     */
    private function pathToSlug( string $path ): string
    {
        $slug = trim( $path, '/' );
        $slug = preg_replace( '/\.(html?|php)$/', '', $slug );
        $slug = strtolower( $slug );
        $slug = preg_replace( '/[^a-z0-9\/-]/', '-', $slug );
        $slug = preg_replace( '/-+/', '-', $slug );
        $slug = trim( $slug, '-/' );

        return $slug ?: 'index';
    }

    /**
     * Enforce rate limiting: minimum 1 second between requests to same domain.
     */
    private function rateLimit( string $url ): void
    {
        $host = parse_url( $url, PHP_URL_HOST ) ?? '';
        $now  = microtime( true );

        if ( isset( self::$domainTimestamps[$host] ) ) {
            $elapsed = $now - self::$domainTimestamps[$host];
            if ( $elapsed < 1.0 ) {
                usleep( (int) ( ( 1.0 - $elapsed ) * 1_000_000 ) );
            }
        }

        self::$domainTimestamps[$host] = microtime( true );
    }

    /**
     * Fetch and parse robots.txt for a domain.
     *
     * @return string[] Array of disallowed path prefixes.
     */
    private function fetchRobotsTxt( string $domain ): array
    {
        $rules = [];

        try {
            // This one was never validated at all — the URL is derived from
            // the crawl target and was fetched straight, on the assumption
            // that a checked target implies a checked robots.txt. It does not:
            // it is a separate request to a separately-constructed URL.
            $result = klytos_safe_http()->fetch( $domain . '/robots.txt', [
                'timeout' => 10,
                'headers' => [ 'User-Agent' => 'KlytosImporter/1.0' ],
            ] );

            if ( $result['blocked'] !== null || $result['error'] !== null || $result['status'] !== 200 ) {
                return $rules;
            }

            $body = $result['body'];

            $inUserAgent = false;
            foreach ( explode( "\n", $body ) as $line ) {
                $line = trim( $line );

                // Skip comments.
                if ( str_starts_with( $line, '#' ) || $line === '' ) {
                    continue;
                }

                if ( preg_match( '/^User-agent:\s*(.+)/i', $line, $m ) ) {
                    $agent = trim( $m[1] );
                    $inUserAgent = ( $agent === '*' || stripos( $agent, 'klytos' ) !== false );
                    continue;
                }

                if ( $inUserAgent && preg_match( '/^Disallow:\s*(.+)/i', $line, $m ) ) {
                    $path = trim( $m[1] );
                    if ( $path !== '' ) {
                        $rules[] = $path;
                    }
                }
            }
        } catch ( \Throwable ) {
            // robots.txt not available — allow everything.
        }

        return $rules;
    }
}
