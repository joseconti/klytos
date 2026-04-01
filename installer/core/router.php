<?php

/**
 * Klytos — Request Router
 * Routes incoming HTTP requests to the appropriate handler.
 * Auto-detects the installation subdirectory.
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

class Router
{
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Dispatch the current request to the appropriate handler.
     *
     * @return void
     */
    public function dispatch(): void
    {
        // Get the route from query string (set by .htaccess) or parse from URI
        $route = $_GET['route'] ?? $this->parseRoute();

        // Hook: allow plugins to act before the route is dispatched.
        klytos_do_action( 'router.before_dispatch', $route );

        switch ($route) {
            case 'mcp':
                $this->handleMcp();
                break;

            case 'oauth/authorize':
                $this->handleOAuthAuthorize();
                break;

            case 'oauth/token':
                $this->handleOAuthToken();
                break;

            case '.well-known/oauth-authorization-server':
                $this->handleOAuthMetadata();
                break;

            case 'cron':
                $this->handleCron();
                break;

            case 'install':
                $this->handleInstall();
                break;

            case 't':
            case 't.php':
                // Analytics tracking pixel endpoint.
                $this->handleAnalyticsPixel();
                break;

            default:
                // Dynamic routes registered by plugins.
                $matched = $this->app->getRouteManager()->match( $route, $_SERVER['REQUEST_METHOD'] ?? 'GET' );
                if ( $matched !== null ) {
                    $this->handleDynamicRoute( $matched );
                    return;
                }

                // Static site — serve from public/
                $this->handlePublic($route);
                break;
        }

        // Hook: allow plugins to act after the route is dispatched.
        klytos_do_action( 'router.after_dispatch', $route );
    }

    /**
     * Parse the route from the request URI.
     * Strips the base path to get the relative route.
     *
     * @return string
     */
    private function parseRoute(): string
    {
        $uri      = $_SERVER['REQUEST_URI'] ?? '/';
        $basePath = $this->app->getBasePath();

        // Remove query string
        $uri = strtok($uri, '?');

        // Remove base path prefix
        if (str_starts_with($uri, $basePath)) {
            $route = substr($uri, strlen($basePath));
        } else {
            $route = ltrim($uri, '/');
        }

        // Clean up
        $route = trim($route, '/');

        return $route ?: 'index';
    }

    /**
     * Handle MCP endpoint requests (JSON-RPC 2.0).
     */
    private function handleMcp(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // CORS headers for MCP — configurable origins
        $this->sendCorsHeaders();

        // Handle preflight
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        require_once $this->app->getCorePath() . '/mcp/server.php';

        $server = new MCP\Server($this->app);

        if ($method === 'GET') {
            $server->handleGet();
        } elseif ($method === 'POST') {
            $server->handlePost();
        } else {
            Helpers::jsonResponse(['error' => 'Method not allowed'], 405);
        }
    }

    /**
     * Handle analytics tracking pixel requests.
     * Forwards to t.php which responds with a 1x1 GIF and records the pageview.
     */
    private function handleAnalyticsPixel(): void
    {
        $tFile = $this->app->getRootPath() . '/t.php';
        if (file_exists($tFile)) {
            require_once $tFile;
        } else {
            http_response_code(204);
        }
        exit;
    }

    /**
     * Handle install requests.
     */
    private function handleInstall(): void
    {
        // Check permanent installation lock
        $lockFile = $this->app->getConfigPath() . '/.install.lock';
        if (file_exists($lockFile)) {
            Helpers::redirect(Helpers::url('admin/'));
            return;
        }

        $installFile = $this->app->getRootPath() . '/install.php';

        if (!file_exists($installFile)) {
            // Already installed
            Helpers::redirect(Helpers::url('admin/'));
            return;
        }

        require_once $installFile;
    }

    /**
     * Handle OAuth authorization endpoint.
     * GET: Shows consent screen (requires admin login).
     * POST: Admin approves, generates auth code, redirects.
     */
    private function handleOAuthAuthorize(): void
    {
        require_once $this->app->getCorePath() . '/mcp/oauth-authorize-view.php';
        handleOAuthAuthorizeView($this->app);
    }

    /**
     * Handle OAuth token endpoint (POST only).
     * Exchanges auth codes for tokens, refreshes tokens, client credentials.
     */
    private function handleOAuthToken(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // CORS for token endpoint
        $this->sendCorsHeaders();

        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        if ($method !== 'POST') {
            Helpers::jsonResponse(['error' => 'method_not_allowed', 'error_description' => 'Only POST is accepted.'], 405);
        }

        // Rate limit per IP
        $rateLimiter = new MCP\RateLimiter($this->app->getDataPath());
        $clientIp    = MCP\RateLimiter::getClientIp();

        if (!$rateLimiter->check('oauth_token:' . $clientIp, 30)) {
            http_response_code(429);
            header('Retry-After: 60');
            Helpers::jsonResponse(['error' => 'rate_limit_exceeded'], 429);
        }

        // Parse form body (application/x-www-form-urlencoded)
        $params = $_POST;
        if (empty($params)) {
            // Try reading raw body for clients sending JSON
            $raw = file_get_contents('php://input', false, null, 0, 65536);
            if (!empty($raw)) {
                parse_str($raw, $params);
            }
        }

        require_once $this->app->getCorePath() . '/mcp/oauth-server.php';
        require_once $this->app->getCorePath() . '/mcp/rate-limiter.php';

        $oauthServer = new MCP\OAuthServer(
            $this->app->getAuth(),
            $this->app->getStorage(),
            $rateLimiter
        );

        $result = $oauthServer->handleTokenRequest($params);

        if ($result['success'] ?? false) {
            // Remove internal 'success' flag from response
            unset($result['success']);
            header('Cache-Control: no-store');
            header('Pragma: no-cache');
            Helpers::jsonResponse($result);
        } else {
            unset($result['success']);
            Helpers::jsonResponse($result, 400);
        }
    }

    /**
     * Handle OAuth server metadata endpoint (RFC 8414).
     */
    private function handleOAuthMetadata(): void
    {
        require_once $this->app->getCorePath() . '/mcp/oauth-server.php';
        require_once $this->app->getCorePath() . '/mcp/rate-limiter.php';

        $rateLimiter = new MCP\RateLimiter($this->app->getDataPath());
        $oauthServer = new MCP\OAuthServer(
            $this->app->getAuth(),
            $this->app->getStorage(),
            $rateLimiter
        );

        $metadata = $oauthServer->getServerMetadata(Helpers::siteUrl());

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        Helpers::jsonResponse($metadata);
    }

    /**
     * Handle requests for the static public site.
     *
     * @param string $route
     */
    private function handlePublic(string $route): void
    {
        $publicPath = $this->app->getPublicPath();

        // Try direct file (e.g. css/style.css, assets/images/logo.png)
        $directFile = $publicPath . '/' . $route;
        if (is_file($directFile)) {
            $this->serveStaticFile($directFile);
            return;
        }

        // Try as HTML page
        $htmlFile = $publicPath . '/' . $route . '.html';
        if (is_file($htmlFile)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($htmlFile);
            return;
        }

        // Try index.html in subdirectory (e.g. /en/ → /en/index.html)
        $indexFile = $publicPath . '/' . $route . '/index.html';
        if (is_file($indexFile)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($indexFile);
            return;
        }

        // Default homepage
        $homeFile = $publicPath . '/index.html';
        if ($route === 'index' && is_file($homeFile)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($homeFile);
            return;
        }

        // 404
        http_response_code(404);
        if (is_file($publicPath . '/404.html')) {
            readfile($publicPath . '/404.html');
        } else {
            echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 — Page not found</h1></body></html>';
        }
    }

    /**
     * Serve a static file with proper content type.
     */
    private function serveStaticFile(string $path): void
    {
        $mimeTypes = [
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'json'  => 'application/json',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'webp'  => 'image/webp',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject',
            'mp4'   => 'video/mp4',
            'webm'  => 'video/webm',
            'mp3'   => 'audio/mpeg',
            'ogg'   => 'audio/ogg',
            'pdf'   => 'application/pdf',
            'xml'   => 'application/xml',
            'txt'   => 'text/plain',
        ];

        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));

        // Cache static assets for 1 year
        if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'woff', 'woff2'])) {
            header('Cache-Control: public, max-age=31536000, immutable');
        }

        readfile($path);
        exit;
    }

    /**
     * Handle cron endpoint: process the action scheduler queue.
     * Protected by a secret token passed as ?token= query parameter.
     */
    private function handleCron(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Validate cron token.
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Missing token.']);
            exit;
        }

        $scheduler = $this->app->getActionScheduler();

        if (!$scheduler->verifyCronToken($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid token.']);
            exit;
        }

        // Process the queue.
        $results = $scheduler->processQueue();

        // Find next due action.
        $nextDue = null;
        $pending = $scheduler->listActions(['status' => 'pending'], 1);
        if (!empty($pending)) {
            $nextDue = $pending[0]['scheduled_at'] ?? null;
        }

        $results['next_due'] = $nextDue;

        echo json_encode($results);
        exit;
    }

    /**
     * Handle a dynamic route registered by a plugin.
     *
     * @param array $matched Result from RouteManager::match().
     */
    private function handleDynamicRoute( array $matched ): void
    {
        $config = $matched['config'];
        $params = $matched['params'];

        // Auth check.
        if ( $config['auth'] === 'admin' ) {
            if ( !$this->app->getAuth()->isAuthenticated() ) {
                if ( $config['type'] === 'api' || $config['type'] === 'webhook' ) {
                    Helpers::jsonResponse( ['error' => 'Authentication required'], 401 );
                }
                Helpers::redirect( Helpers::url( 'admin/login.php?redirect_to=' . urlencode( $_SERVER['REQUEST_URI'] ) ) );
                return;
            }
        }

        // Capability check.
        if ( !empty( $config['capability'] ) && !klytos_has_permission( $config['capability'] ) ) {
            if ( $config['type'] === 'api' || $config['type'] === 'webhook' ) {
                Helpers::jsonResponse( ['error' => 'Forbidden'], 403 );
            } else {
                http_response_code( 403 );
                echo '<!DOCTYPE html><html><body><h1>403 — Access denied</h1></body></html>';
            }
            return;
        }

        // Rate limiting for API and webhooks.
        if ( $config['type'] === 'api' || $config['type'] === 'webhook' ) {
            $rateLimiter = new MCP\RateLimiter( $this->app->getDataPath() );
            $clientIp    = MCP\RateLimiter::getClientIp();
            if ( !$rateLimiter->check( 'route:' . $clientIp . ':' . $matched['pattern'], 60 ) ) {
                http_response_code( 429 );
                header( 'Retry-After: 60' );
                Helpers::jsonResponse( ['error' => 'Rate limit exceeded'], 429 );
                return;
            }
        }

        // Execute callback.
        try {
            $result = call_user_func( $config['callback'], $params );
        } catch ( \Throwable $e ) {
            error_log( "Klytos Router: error in dynamic route {$matched['pattern']}: " . $e->getMessage() );
            if ( $config['type'] === 'api' || $config['type'] === 'webhook' ) {
                Helpers::jsonResponse( ['error' => 'Internal server error'], 500 );
            } else {
                http_response_code( 500 );
                echo '<!DOCTYPE html><html><body><h1>500 — Internal error</h1></body></html>';
            }
            return;
        }

        // Send response based on type.
        switch ( $config['type'] ) {
            case 'api':
                $this->sendCorsHeaders();
                Helpers::jsonResponse( $result );
                break;

            case 'webhook':
                Helpers::jsonResponse( $result );
                break;

            case 'page':
                // Render within a template.
                $templateResolver = $this->app->getTemplateResolver();
                $templateHtml     = $templateResolver->resolve( $config['template'] ?? 'default' );

                $replacements = [
                    '{{page_title}}'   => Helpers::escHtml( $config['title'] ?? '' ),
                    '{{page_content}}' => is_string( $result ) ? $result : '',
                ];
                foreach ( $replacements as $key => $value ) {
                    $templateHtml = str_replace( $key, $value, $templateHtml );
                }

                header( 'Content-Type: text/html; charset=utf-8' );
                echo $templateHtml;
                break;
        }
    }

    /**
     * Send CORS headers based on configuration.
     * Defaults to '*' for backward compatibility.
     */
    private function sendCorsHeaders(): void
    {
        $config = $this->app->getConfig();
        $allowedOrigins = $config['cors_allowed_origins'] ?? ['*'];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array('*', $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: *');
        } elseif (!empty($origin) && in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
        // If origin not in allowlist, no CORS header is sent (browser blocks)

        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
    }
}
