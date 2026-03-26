<?php
/**
 * Klytos Admin API — oEmbed Proxy
 *
 * Resolves oEmbed URLs for the Gutenberg editor.
 * Proxies requests to the provider's oEmbed endpoint.
 *
 * @copyright 2024-2026 José Conti. All rights reserved.
 * @license   Elastic License 2.0 (ELv2)
 */

require dirname( __DIR__ ) . '/bootstrap.php';

header( 'Content-Type: application/json; charset=utf-8' );

$url = $_GET['url'] ?? '';

if ( ! $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
    http_response_code( 400 );
    echo json_encode( [ 'error' => 'Invalid URL' ] );
    exit;
}

// Known oEmbed providers.
$providers = [
    // YouTube
    [ 'pattern' => '#https?://(www\.)?youtube\.com/watch#i',   'endpoint' => 'https://www.youtube.com/oembed' ],
    [ 'pattern' => '#https?://youtu\.be/#i',                   'endpoint' => 'https://www.youtube.com/oembed' ],
    // Vimeo
    [ 'pattern' => '#https?://(www\.)?vimeo\.com/#i',          'endpoint' => 'https://vimeo.com/api/oembed.json' ],
    // Twitter/X
    [ 'pattern' => '#https?://(www\.)?(twitter|x)\.com/#i',   'endpoint' => 'https://publish.twitter.com/oembed' ],
    // Instagram
    [ 'pattern' => '#https?://(www\.)?instagram\.com/#i',      'endpoint' => 'https://api.instagram.com/oembed' ],
    // Spotify
    [ 'pattern' => '#https?://open\.spotify\.com/#i',          'endpoint' => 'https://open.spotify.com/oembed' ],
    // SoundCloud
    [ 'pattern' => '#https?://soundcloud\.com/#i',             'endpoint' => 'https://soundcloud.com/oembed' ],
    // WordPress.tv
    [ 'pattern' => '#https?://wordpress\.tv/#i',               'endpoint' => 'https://wordpress.tv/oembed/' ],
    // TikTok
    [ 'pattern' => '#https?://(www\.)?tiktok\.com/#i',        'endpoint' => 'https://www.tiktok.com/oembed' ],
];

$oembedEndpoint = null;

foreach ( $providers as $provider ) {
    if ( preg_match( $provider['pattern'], $url ) ) {
        $oembedEndpoint = $provider['endpoint'];
        break;
    }
}

if ( ! $oembedEndpoint ) {
    // Try oEmbed discovery from the page itself.
    $oembedEndpoint = discoverOembed( $url );
}

if ( ! $oembedEndpoint ) {
    http_response_code( 404 );
    echo json_encode( [ 'error' => 'No oEmbed provider found for this URL' ] );
    exit;
}

// Fetch oEmbed data.
$requestUrl = $oembedEndpoint . '?' . http_build_query( [
    'url'    => $url,
    'format' => 'json',
    'maxwidth' => 800,
] );

$context = stream_context_create( [
    'http' => [
        'timeout' => 10,
        'user_agent' => 'Klytos CMS/2.0',
        'ignore_errors' => true,
    ],
] );

$response = @file_get_contents( $requestUrl, false, $context );

if ( $response === false ) {
    http_response_code( 502 );
    echo json_encode( [ 'error' => 'Failed to fetch oEmbed data' ] );
    exit;
}

$data = json_decode( $response, true );

if ( ! $data ) {
    http_response_code( 502 );
    echo json_encode( [ 'error' => 'Invalid oEmbed response' ] );
    exit;
}

echo json_encode( $data );


/**
 * Try to discover oEmbed endpoint from a page's HTML.
 *
 * @param  string $url
 * @return string|null
 */
function discoverOembed( string $url ): ?string {
    $context = stream_context_create( [
        'http' => [
            'timeout' => 5,
            'user_agent' => 'Klytos CMS/2.0',
            'ignore_errors' => true,
        ],
    ] );

    $html = @file_get_contents( $url, false, $context );

    if ( ! $html ) {
        return null;
    }

    // Look for oEmbed link tag.
    if ( preg_match(
        '/<link[^>]+type=["\']application\/json\+oembed["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i',
        $html,
        $matches
    ) ) {
        return html_entity_decode( $matches[1] );
    }

    return null;
}
