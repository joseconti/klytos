<?php

/**
 * Klytos — oEmbed Resolver
 * Resolves bare URLs in page content into rich embed HTML during build.
 *
 * Scans page HTML for standalone URLs (on their own paragraph) that match
 * known oEmbed providers (YouTube, Vimeo, Twitter/X, Instagram, Spotify, etc.)
 * and replaces them with the provider's embed HTML.
 *
 * Results are cached to avoid repeated API calls on subsequent builds.
 *
 * @package Klytos
 * @since   0.18.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class OEmbedResolver
{
    /** @var CacheManager|null Cache for resolved embeds. */
    private ?CacheManager $cache;

    /** @var array Provider registry: regex => oEmbed endpoint URL. */
    private array $providers;

    /** @var int HTTP timeout for oEmbed requests (seconds). */
    private const HTTP_TIMEOUT = 5;

    /** @var int Cache TTL for resolved embeds (seconds): 7 days. */
    private const CACHE_TTL = 604800;

    public function __construct( ?CacheManager $cache = null )
    {
        $this->cache = $cache;
        $this->providers = $this->getDefaultProviders();
    }

    /**
     * Process HTML content and resolve bare URLs into embeds.
     *
     * Looks for URLs that appear alone inside a paragraph or inside
     * a Gutenberg wp:paragraph block and replaces them with embed HTML.
     *
     * @param  string $html Page HTML content.
     * @return string Modified HTML with embeds resolved.
     */
    public function resolve( string $html ): string
    {
        // Allow plugins to modify the provider list.
        $this->providers = klytos_apply_filters( 'build.oembed.providers', $this->providers );

        // Match bare URLs inside paragraphs:
        // <p>https://www.youtube.com/watch?v=dQw4w9WgXcQ</p>
        // Also handles Gutenberg: <!-- wp:paragraph --><p>URL</p><!-- /wp:paragraph -->
        $pattern = '/<p>\s*(https?:\/\/[^\s<]+)\s*<\/p>/i';

        return preg_replace_callback( $pattern, function ( array $matches ) {
            $url = trim( $matches[1] );
            return $this->resolveUrl( $url ) ?? $matches[0];
        }, $html );
    }

    /**
     * Resolve a single URL to its oEmbed HTML.
     *
     * @param  string $url URL to resolve.
     * @return string|null Embed HTML or null if not resolvable.
     */
    public function resolveUrl( string $url ): ?string
    {
        // Check cache first.
        $cacheKey = 'oembed_' . md5( $url );
        if ( $this->cache !== null ) {
            $cached = $this->cache->get( $cacheKey );
            if ( $cached !== null ) {
                return $cached !== '' ? $cached : null;
            }
        }

        // Find matching provider.
        $endpoint = null;
        foreach ( $this->providers as $regex => $providerEndpoint ) {
            if ( preg_match( $regex, $url ) ) {
                $endpoint = $providerEndpoint;
                break;
            }
        }

        if ( $endpoint === null ) {
            // Cache negative result to avoid re-checking.
            $this->cacheResult( $cacheKey, '' );
            return null;
        }

        // Call the oEmbed endpoint.
        $oembedUrl = $endpoint . '?url=' . urlencode( $url ) . '&format=json&maxwidth=720';

        $context = stream_context_create( [
            'http' => [
                'timeout'       => self::HTTP_TIMEOUT,
                'ignore_errors' => true,
                'user_agent'    => 'Klytos/' . KLYTOS_VERSION,
            ],
        ] );

        $response = @file_get_contents( $oembedUrl, false, $context );

        if ( $response === false ) {
            $this->cacheResult( $cacheKey, '' );
            return null;
        }

        $data = json_decode( $response, true );

        if ( empty( $data['html'] ) ) {
            $this->cacheResult( $cacheKey, '' );
            return null;
        }

        // Wrap the embed in a responsive container.
        $embedHtml = '<div class="klytos-embed">' . $data['html'] . '</div>';

        $this->cacheResult( $cacheKey, $embedHtml );

        return $embedHtml;
    }

    /**
     * Store a result in cache.
     */
    private function cacheResult( string $key, string $value ): void
    {
        if ( $this->cache !== null ) {
            $this->cache->set( $key, $value, self::CACHE_TTL );
        }
    }

    /**
     * Get the default oEmbed provider registry.
     *
     * Each entry: regex pattern => oEmbed JSON endpoint.
     *
     * @return array
     */
    private function getDefaultProviders(): array
    {
        return [
            // YouTube
            '#https?://(www\.)?youtube\.com/watch\?v=#i'     => 'https://www.youtube.com/oembed',
            '#https?://youtu\.be/#i'                          => 'https://www.youtube.com/oembed',
            '#https?://(www\.)?youtube\.com/shorts/#i'        => 'https://www.youtube.com/oembed',

            // Vimeo
            '#https?://(www\.)?vimeo\.com/#i'                 => 'https://vimeo.com/api/oembed.json',

            // Twitter / X
            '#https?://(twitter\.com|x\.com)/.+/status/#i'   => 'https://publish.twitter.com/oembed',

            // Instagram
            '#https?://(www\.)?instagram\.com/(p|reel)/#i'   => 'https://graph.facebook.com/v18.0/instagram_oembed',

            // Spotify
            '#https?://open\.spotify\.com/(track|album|playlist|episode)/#i' => 'https://open.spotify.com/oembed',

            // SoundCloud
            '#https?://soundcloud\.com/.+/.+#i'              => 'https://soundcloud.com/oembed',

            // TikTok
            '#https?://(www\.)?tiktok\.com/.+/video/#i'      => 'https://www.tiktok.com/oembed',

            // Dailymotion
            '#https?://(www\.)?dailymotion\.com/video/#i'    => 'https://www.dailymotion.com/services/oembed',

            // CodePen
            '#https?://codepen\.io/.+/pen/#i'                => 'https://codepen.io/api/oembed',

            // SlideShare
            '#https?://(www\.)?slideshare\.net/#i'           => 'https://www.slideshare.net/api/oembed/2',
        ];
    }
}
