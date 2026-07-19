<?php

/**
 * Klytos Admin API — oEmbed Proxy
 *
 * Resolves oEmbed URLs for the Gutenberg editor.
 * Proxies requests to the provider's oEmbed endpoint.
 *
 * @copyright 2024-2026 José Conti. All rights reserved.
 * @license   GPL-3.0-or-later
 */

require dirname( __DIR__ ) . '/bootstrap.php';

header( 'Content-Type: application/json; charset=utf-8' );

$url = $_GET['url'] ?? '';

// S-08: filter_var( FILTER_VALIDATE_URL ) was the ONLY check here, and it
// accepts http://127.0.0.1:6379/, http://169.254.169.254/latest/meta-data/ and
// http://[::1]/ without complaint — so an authenticated editor could point this
// proxy at anything the server could reach. SafeHttp refuses loopback, private,
// link-local and reserved addresses and non-HTTP(S) schemes, and re-validates
// every redirect hop rather than trusting the transport to stop.
//
// The refusal deliberately reuses the SAME generic 400 as a malformed URL. A
// distinct "that host is private" reply would turn this endpoint into an
// internal-network scanner that answers one address per request; the specific
// reason goes to the error log instead.
if ( ! $url || ! filter_var( $url, FILTER_VALIDATE_URL ) || ! klytos_safe_http()->isAllowed( $url ) ) {
    http_response_code( 400 );
    echo json_encode( [ 'error' => 'Invalid URL' ] );
    exit;
}

// Known oEmbed providers — mirrors WordPress core (class-wp-oembed.php).
$providers = [
    // YouTube
    [ 'pattern' => '#https?://((m|www)\.)?youtube\.com/watch.*#i',    'endpoint' => 'https://www.youtube.com/oembed' ],
    [ 'pattern' => '#https?://((m|www)\.)?youtube\.com/playlist.*#i', 'endpoint' => 'https://www.youtube.com/oembed' ],
    [ 'pattern' => '#https?://((m|www)\.)?youtube\.com/shorts/*#i',   'endpoint' => 'https://www.youtube.com/oembed' ],
    [ 'pattern' => '#https?://((m|www)\.)?youtube\.com/live/*#i',     'endpoint' => 'https://www.youtube.com/oembed' ],
    [ 'pattern' => '#https?://youtu\.be/.*#i',                        'endpoint' => 'https://www.youtube.com/oembed' ],
    // Vimeo
    [ 'pattern' => '#https?://(.+\.)?vimeo\.com/.*#i',               'endpoint' => 'https://vimeo.com/api/oembed.json' ],
    // Dailymotion
    [ 'pattern' => '#https?://(www\.)?dailymotion\.com/.*#i',        'endpoint' => 'https://www.dailymotion.com/services/oembed' ],
    [ 'pattern' => '#https?://dai\.ly/.*#i',                          'endpoint' => 'https://www.dailymotion.com/services/oembed' ],
    // Flickr
    [ 'pattern' => '#https?://(www\.)?flickr\.com/.*#i',             'endpoint' => 'https://www.flickr.com/services/oembed/' ],
    [ 'pattern' => '#https?://flic\.kr/.*#i',                         'endpoint' => 'https://www.flickr.com/services/oembed/' ],
    // SmugMug
    [ 'pattern' => '#https?://(.+\.)?smugmug\.com/.*#i',             'endpoint' => 'https://api.smugmug.com/services/oembed/' ],
    // Scribd
    [ 'pattern' => '#https?://(www\.)?scribd\.com/(doc|document)/.*#i', 'endpoint' => 'https://www.scribd.com/services/oembed' ],
    // WordPress.tv
    [ 'pattern' => '#https?://wordpress\.tv/.*#i',                    'endpoint' => 'https://wordpress.tv/oembed/' ],
    // Crowdsignal (formerly Polldaddy)
    [ 'pattern' => '#https?://(.+\.)?crowdsignal\.net/.*#i',         'endpoint' => 'https://api.crowdsignal.com/oembed' ],
    [ 'pattern' => '#https?://(.+\.)?polldaddy\.com/.*#i',           'endpoint' => 'https://api.crowdsignal.com/oembed' ],
    [ 'pattern' => '#https?://poll\.fm/.*#i',                         'endpoint' => 'https://api.crowdsignal.com/oembed' ],
    [ 'pattern' => '#https?://(.+\.)?survey\.fm/.*#i',               'endpoint' => 'https://api.crowdsignal.com/oembed' ],
    // Twitter / X
    [ 'pattern' => '#https?://(www\.)?(twitter|x)\.com/\w{1,15}/status(es)?/.*#i', 'endpoint' => 'https://publish.twitter.com/oembed' ],
    [ 'pattern' => '#https?://(www\.)?(twitter|x)\.com/\w{1,15}$#i',               'endpoint' => 'https://publish.twitter.com/oembed' ],
    [ 'pattern' => '#https?://(www\.)?(twitter|x)\.com/\w{1,15}/likes$#i',          'endpoint' => 'https://publish.twitter.com/oembed' ],
    [ 'pattern' => '#https?://(www\.)?(twitter|x)\.com/\w{1,15}/lists/.*#i',        'endpoint' => 'https://publish.twitter.com/oembed' ],
    [ 'pattern' => '#https?://(www\.)?(twitter|x)\.com/i/moments/.*#i',             'endpoint' => 'https://publish.twitter.com/oembed' ],
    // SoundCloud
    [ 'pattern' => '#https?://(www\.)?soundcloud\.com/.*#i',         'endpoint' => 'https://soundcloud.com/oembed' ],
    // Spotify
    [ 'pattern' => '#https?://(open|play)\.spotify\.com/.*#i',       'endpoint' => 'https://embed.spotify.com/oembed/' ],
    // Imgur
    [ 'pattern' => '#https?://(.+\.)?imgur\.com/.*#i',               'endpoint' => 'https://api.imgur.com/oembed' ],
    // Issuu
    [ 'pattern' => '#https?://(www\.)?issuu\.com/.+/docs/.+#i',     'endpoint' => 'https://issuu.com/oembed_wp' ],
    // Mixcloud
    [ 'pattern' => '#https?://(www\.)?mixcloud\.com/.*#i',           'endpoint' => 'https://app.mixcloud.com/oembed/' ],
    // TED
    [ 'pattern' => '#https?://(www\.|embed\.)?ted\.com/talks/.*#i', 'endpoint' => 'https://www.ted.com/services/v1/oembed.json' ],
    // Animoto
    [ 'pattern' => '#https?://(www\.|embed\.)?animoto\.com/play/.*#i', 'endpoint' => 'https://animoto.com/oembeds/create' ],
    [ 'pattern' => '#https?://(www\.)?(video214)\.com/play/.*#i',      'endpoint' => 'https://animoto.com/oembeds/create' ],
    // Tumblr
    [ 'pattern' => '#https?://(.+)\.tumblr\.com/.*#i',               'endpoint' => 'https://www.tumblr.com/oembed/1.0' ],
    // Kickstarter
    [ 'pattern' => '#https?://(www\.)?kickstarter\.com/projects/.*#i', 'endpoint' => 'https://www.kickstarter.com/services/oembed' ],
    [ 'pattern' => '#https?://kck\.st/.*#i',                          'endpoint' => 'https://www.kickstarter.com/services/oembed' ],
    // Cloudup
    [ 'pattern' => '#https?://cloudup\.com/.*#i',                     'endpoint' => 'https://cloudup.com/oembed' ],
    // ReverbNation
    [ 'pattern' => '#https?://(www\.)?reverbnation\.com/.*#i',       'endpoint' => 'https://www.reverbnation.com/oembed' ],
    // VideoPress
    [ 'pattern' => '#https?://videopress\.com/v/.*#i',               'endpoint' => 'https://public-api.wordpress.com/oembed/' ],
    // Reddit
    [ 'pattern' => '#https?://(www\.)?reddit\.com/r/[^/]+/comments/.*#i', 'endpoint' => 'https://www.reddit.com/oembed' ],
    // Speaker Deck
    [ 'pattern' => '#https?://(www\.)?speakerdeck\.com/.*#i',        'endpoint' => 'https://speakerdeck.com/oembed.json' ],
    // Amazon Kindle
    [ 'pattern' => '#https?://([a-z0-9-]+\.)?amazon\.(com|com\.mx|com\.br|ca)/.*#i',    'endpoint' => 'https://read.amazon.com/kp/api/oembed' ],
    [ 'pattern' => '#https?://([a-z0-9-]+\.)?amazon\.(co\.uk|de|fr|it|es|in|nl|ru)/.*#i', 'endpoint' => 'https://read.amazon.co.uk/kp/api/oembed' ],
    [ 'pattern' => '#https?://([a-z0-9-]+\.)?amazon\.(co\.jp|com\.au)/.*#i',            'endpoint' => 'https://read.amazon.com.au/kp/api/oembed' ],
    [ 'pattern' => '#https?://([a-z0-9-]+\.)?amazon\.cn/.*#i',       'endpoint' => 'https://read.amazon.cn/kp/api/oembed' ],
    [ 'pattern' => '#https?://(www\.)?a\.co/.*#i',                    'endpoint' => 'https://read.amazon.com/kp/api/oembed' ],
    [ 'pattern' => '#https?://(www\.)?amzn\.to/.*#i',                 'endpoint' => 'https://read.amazon.com/kp/api/oembed' ],
    [ 'pattern' => '#https?://(www\.)?amzn\.eu/.*#i',                 'endpoint' => 'https://read.amazon.co.uk/kp/api/oembed' ],
    [ 'pattern' => '#https?://(www\.)?amzn\.in/.*#i',                 'endpoint' => 'https://read.amazon.in/kp/api/oembed' ],
    [ 'pattern' => '#https?://(www\.)?amzn\.asia/.*#i',               'endpoint' => 'https://read.amazon.com.au/kp/api/oembed' ],
    [ 'pattern' => '#https?://(www\.)?z\.cn/.*#i',                    'endpoint' => 'https://read.amazon.cn/kp/api/oembed' ],
    // Someecards
    [ 'pattern' => '#https?://www\.someecards\.com/.+-cards/.+#i',   'endpoint' => 'https://www.someecards.com/v2/oembed/' ],
    [ 'pattern' => '#https?://www\.someecards\.com/usercards/viewcard/.+#i', 'endpoint' => 'https://www.someecards.com/v2/oembed/' ],
    [ 'pattern' => '#https?://some\.ly/.+#i',                         'endpoint' => 'https://www.someecards.com/v2/oembed/' ],
    // TikTok
    [ 'pattern' => '#https?://(www\.)?tiktok\.com/.*/video/.*#i',    'endpoint' => 'https://www.tiktok.com/oembed' ],
    [ 'pattern' => '#https?://(www\.)?tiktok\.com/@.*#i',            'endpoint' => 'https://www.tiktok.com/oembed' ],
    // Pinterest
    [ 'pattern' => '#https?://([a-z]{2}|www)\.pinterest\.com(\.(au|mx))?/.*#i', 'endpoint' => 'https://www.pinterest.com/oembed.json' ],
    // Wolfram Cloud
    [ 'pattern' => '#https?://(www\.)?wolframcloud\.com/obj/.+#i',   'endpoint' => 'https://www.wolframcloud.com/oembed' ],
    // Pocket Casts
    [ 'pattern' => '#https?://pca\.st/.+#i',                          'endpoint' => 'https://pca.st/oembed.json' ],
    // Anghami
    [ 'pattern' => '#https?://((play|www)\.)?anghami\.com/.*#i',     'endpoint' => 'https://api.anghami.com/rest/v1/oembed.view' ],
    // Bluesky
    [ 'pattern' => '#https?://bsky\.app/profile/.*/post/.*#i',       'endpoint' => 'https://embed.bsky.app/oembed' ],
    // Canva
    [ 'pattern' => '#https?://(www\.)?canva\.com/design/.*/view.*#i', 'endpoint' => 'https://canva.com/_oembed' ],
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
    'url'      => $url,
    'format'   => 'json',
    'maxwidth' => 800,
] );

$response = fetchUrl( $requestUrl );

if ( $response === false ) {
    http_response_code( 502 );
    echo json_encode( [ 'error' => 'Failed to fetch oEmbed data from provider' ] );
    exit;
}

$data = json_decode( $response, true );

if ( ! $data ) {
    http_response_code( 502 );
    echo json_encode( [ 'error' => 'Invalid oEmbed response', 'raw' => substr( $response, 0, 500 ) ] );
    exit;
}

echo json_encode( $data );


/**
 * Fetch a URL through SafeHttp.
 *
 * Every fetch this file makes goes through here, and that is load-bearing
 * rather than tidy: the DISCOVERED endpoint is attacker-controlled too. When no
 * hardcoded provider pattern matches, discoverOembed() reads an
 * <link type="application/json+oembed" href="..."> out of the fetched page and
 * hands it straight back to be fetched by the `fetchUrl( $requestUrl )` call
 * below the provider lookup — and unlike the page fetch, that response IS
 * echoed to the caller. Validating only the URL the caller supplied would leave
 * that second, fully arbitrary fetch wide open.
 *
 * The hand-rolled cURL and file_get_contents transports this replaced followed
 * redirects with no per-hop validation (CURLOPT_FOLLOWLOCATION with
 * MAXREDIRS 5, and PHP's http wrapper following up to 20 by default), so a
 * public URL answering 302 -> http://169.254.169.254/ defeated any pre-flight
 * check. SafeHttp walks the chain itself and re-validates every hop.
 *
 * @param  string $url
 * @return string|false Response body, or false on failure or refusal.
 */
function fetchUrl( string $url )
{
    $result = klytos_safe_http()->fetch( $url, [
        'timeout' => 10,
        'headers' => [ 'Accept' => 'application/json' ],
    ] );

    // SafeHttp already logged the specific reason. Collapsing a refusal into
    // the same false the caller returns for any other failure is deliberate —
    // see the note at the top of this file about not answering as an oracle.
    if ( $result['blocked'] !== null ) {
        return false;
    }

    if ( $result['error'] !== null || $result['status'] >= 400 ) {
        error_log( "Klytos oEmbed: fetch failed for {$url} — HTTP {$result['status']}" );
        return false;
    }

    return $result['body'];
}

/**
 * Try to discover oEmbed endpoint from a page's HTML.
 *
 * @param  string $url
 * @return string|null
 */
function discoverOembed( string $url ): ?string
{
    $html = fetchUrl( $url );

    if ( ! $html ) {
        return null;
    }

    // Look for oEmbed link tag.
    if (
        preg_match(
            '/<link[^>]+type=["\']application\/json\+oembed["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i',
            $html,
            $matches
        )
    ) {
        return html_entity_decode( $matches[1] );
    }

    return null;
}
