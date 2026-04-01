<?php

/**
 * SitemapParser — Sitemap XML and sitemap index parser.
 *
 * Fetches and parses sitemap.xml files following the Sitemaps Protocol.
 * Supports standard <urlset>, sitemap index files (<sitemapindex>),
 * and classifies URLs by content type heuristics.
 *
 * @package KlytosImporter
 */

declare( strict_types=1 );

namespace KlytosImporter;

class SitemapParser
{
    private string $sitemapUrl;
    private int $maxUrls;

    /** @var array Collected URLs. */
    private array $urls = [];

    /** @var int Number of sub-sitemaps found in index. */
    private int $sitemapsFound = 0;

    public function __construct( string $sitemapUrl, int $maxUrls = 500 )
    {
        if ( !ImportValidator::validateUrl( $sitemapUrl ) ) {
            throw new \InvalidArgumentException( "Invalid or unsafe sitemap URL: {$sitemapUrl}" );
        }

        $this->sitemapUrl = $sitemapUrl;
        $this->maxUrls    = $maxUrls;
    }

    /**
     * Parse the sitemap and return a structured result.
     *
     * Returns the structure documented in IMPORTER-ARCHITECTURE.md section 5.1
     * (klytos_import_analyze_sitemap response).
     */
    public function parse(): array
    {
        $this->urls          = [];
        $this->sitemapsFound = 0;

        $xml = $this->fetchXml( $this->sitemapUrl );
        if ( $xml === null ) {
            throw new \RuntimeException( "Could not fetch or parse sitemap: {$this->sitemapUrl}" );
        }

        // Detect sitemap index vs. urlset.
        if ( isset( $xml->sitemap ) ) {
            $this->parseSitemapIndex( $xml );
        } elseif ( isset( $xml->url ) ) {
            $this->parseUrlset( $xml );
        } else {
            throw new \RuntimeException( 'Sitemap contains neither <urlset> nor <sitemapindex>.' );
        }

        // Extract base site URL.
        $siteUrl = '';
        if ( !empty( $this->urls ) ) {
            $parsed  = parse_url( $this->urls[0]['loc'] ?? '' );
            $siteUrl = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' );
        }

        return [
            'success'        => true,
            'source'         => 'sitemap',
            'site_url'       => $siteUrl,
            'total_urls'     => count( $this->urls ),
            'urls'           => $this->urls,
            'sitemaps_found' => $this->sitemapsFound,
        ];
    }

    /**
     * Parse a <sitemapindex> — fetch each sub-sitemap.
     */
    private function parseSitemapIndex( \SimpleXMLElement $xml ): void
    {
        foreach ( $xml->sitemap as $sitemap ) {
            if ( count( $this->urls ) >= $this->maxUrls ) {
                break;
            }

            $loc = (string) ( $sitemap->loc ?? '' );
            if ( empty( $loc ) || !ImportValidator::validateUrl( $loc ) ) {
                continue;
            }

            $this->sitemapsFound++;

            // Rate limit: 1 second between sub-sitemap fetches.
            if ( $this->sitemapsFound > 1 ) {
                usleep( 1_000_000 );
            }

            $subXml = $this->fetchXml( $loc );
            if ( $subXml !== null && isset( $subXml->url ) ) {
                $this->parseUrlset( $subXml );
            }
        }
    }

    /**
     * Parse a standard <urlset>.
     */
    private function parseUrlset( \SimpleXMLElement $xml ): void
    {
        foreach ( $xml->url as $urlNode ) {
            if ( count( $this->urls ) >= $this->maxUrls ) {
                break;
            }

            $loc = (string) ( $urlNode->loc ?? '' );
            if ( empty( $loc ) ) {
                continue;
            }

            $lastmod  = (string) ( $urlNode->lastmod ?? '' );
            $priority = (string) ( $urlNode->priority ?? '' );

            $this->urls[] = [
                'loc'            => $loc,
                'lastmod'        => $lastmod,
                'priority'       => $priority,
                'suggested_slug' => $this->suggestSlug( $loc ),
                'suggested_type' => $this->classifyUrl( $loc ),
            ];
        }
    }

    /**
     * Classify a URL by content type based on path patterns.
     *
     * Pattern table from IMPORTER-ARCHITECTURE.md section 6.2.
     */
    public function classifyUrl( string $url ): string
    {
        $path = trim( parse_url( $url, PHP_URL_PATH ) ?? '/', '/' );

        // Homepage.
        if ( $path === '' || $path === 'index' || $path === 'index.html' ) {
            return 'homepage';
        }

        // Pagination — always skip.
        if ( preg_match( '#/page/\d+#', $path ) ) {
            return 'pagination';
        }

        // Archives.
        if ( preg_match( '#^(category|tag|tags|autor|author)/#i', $path ) ) {
            return 'archive';
        }

        // Author pages.
        if ( preg_match( '#^author/#i', $path ) ) {
            return 'author';
        }

        // Blog posts: date-based patterns.
        if ( preg_match( '#^\d{4}/\d{2}/#', $path ) || preg_match( '#^blog/.+#', $path ) ) {
            return 'post';
        }

        // Feed/API endpoints.
        if ( preg_match( '#^(feed|rss|api|wp-json)#i', $path ) ) {
            return 'skip';
        }

        // Search results.
        if ( str_starts_with( $path, 'search' ) || str_contains( $url, '?s=' ) ) {
            return 'skip';
        }

        // E-commerce pages.
        if ( preg_match( '#^(cart|checkout|my-account|account)$#i', $path ) ) {
            return 'skip';
        }

        // Default: treat as page.
        return 'page';
    }

    /**
     * Suggest a Klytos slug from a URL.
     */
    public function suggestSlug( string $url ): string
    {
        $path = trim( parse_url( $url, PHP_URL_PATH ) ?? '/', '/' );

        if ( $path === '' || $path === 'index.html' ) {
            return 'index';
        }

        // Remove file extension.
        $path = preg_replace( '/\.(html?|php)$/', '', $path );

        // Sanitize: lowercase, replace non-alnum with hyphens.
        $slug = strtolower( $path );
        $slug = preg_replace( '/[^a-z0-9\/-]/', '-', $slug );
        $slug = preg_replace( '/-+/', '-', $slug );
        $slug = trim( $slug, '-/' );

        return $slug ?: 'index';
    }

    /**
     * Fetch and parse an XML URL.
     */
    private function fetchXml( string $url ): ?\SimpleXMLElement
    {
        $ch = curl_init();
        curl_setopt_array( $ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'KlytosImporter/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ] );

        $body = curl_exec( $ch );
        $code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        if ( $body === false || $code !== 200 ) {
            return null;
        }

        $prev = libxml_use_internal_errors( true );
        $xml  = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );

        return $xml !== false ? $xml : null;
    }
}
