<?php

/**
 * Klytos — Route Manager
 * Manages dynamic routes registered by plugins.
 *
 * Plugins register routes in their init.php using klytos_register_route().
 * The Router consults this manager before serving static files.
 *
 * Route types:
 * - 'page':    Renders HTML within a template (like a normal page).
 * - 'api':     Returns JSON (for AJAX/REST requests).
 * - 'webhook': Receives callbacks from external services (Stripe, PayPal, etc.).
 *
 * @package Klytos
 * @since   0.16.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

namespace Klytos\Core;

class RouteManager
{
    /**
     * Registered routes.
     *
     * @var array<int, array{pattern: string, regex: string, params: string[], config: array}>
     */
    private array $routes = [];

    /**
     * Register a dynamic route.
     *
     * @param string $pattern URL pattern. May contain parameters: /product/{slug}
     * @param array  $config  Route configuration:
     *   - 'callback'   (required): callable. Receives array $params, returns string|array.
     *   - 'type'       (required): 'page', 'api', or 'webhook'.
     *   - 'method'     (optional): 'GET', 'POST', 'GET|POST'. Default: 'GET'.
     *   - 'template'   (optional): Template name for type 'page'. Default: 'default'.
     *   - 'title'      (optional): Page title for type 'page'. Default: ''.
     *   - 'auth'       (optional): false | 'frontend' | 'admin'. Default: false.
     *   - 'capability' (optional): Permission string. Default: null.
     *   - 'plugin_id'  (optional): Plugin ID (auto-filled by helper).
     *
     * @throws \InvalidArgumentException On invalid config.
     */
    public function register( string $pattern, array $config ): void
    {
        if ( empty( $config['callback'] ) || !is_callable( $config['callback'] ) ) {
            throw new \InvalidArgumentException(
                "Route '{$pattern}': 'callback' is required and must be callable."
            );
        }
        if ( empty( $config['type'] ) || !in_array( $config['type'], ['page', 'api', 'webhook'], true ) ) {
            throw new \InvalidArgumentException(
                "Route '{$pattern}': 'type' must be 'page', 'api', or 'webhook'."
            );
        }

        $pattern = '/' . trim( $pattern, '/' );

        // Convert pattern to regex: /account/{section} => #^/account/(?P<section>[^/]+)/?$#
        $paramNames = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function ( array $matches ) use ( &$paramNames ): string {
                $paramNames[] = $matches[1];
                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $pattern
        );
        $regex = '#^' . $regex . '/?$#';

        $config = array_merge( [
            'method'     => 'GET',
            'type'       => 'page',
            'template'   => 'default',
            'title'      => '',
            'auth'       => false,
            'capability' => null,
            'plugin_id'  => null,
        ], $config );

        $this->routes[] = [
            'pattern' => $pattern,
            'regex'   => $regex,
            'params'  => $paramNames,
            'config'  => $config,
        ];
    }

    /**
     * Match a URL against registered routes.
     *
     * @param  string $url    Clean URL (no query string). E.g. 'account/orders'.
     * @param  string $method HTTP method: 'GET', 'POST', etc.
     * @return array|null     Matched route with params, or null.
     */
    public function match( string $url, string $method = 'GET' ): ?array
    {
        $url = '/' . trim( $url, '/' );

        foreach ( $this->routes as $route ) {
            $allowedMethods = array_map( 'trim', explode( '|', strtoupper( $route['config']['method'] ) ) );
            if ( !in_array( strtoupper( $method ), $allowedMethods, true ) ) {
                continue;
            }

            if ( preg_match( $route['regex'], $url, $matches ) ) {
                $params = [];
                foreach ( $route['params'] as $paramName ) {
                    $params[$paramName] = preg_replace( '/[^a-zA-Z0-9_\-\.]/', '', $matches[$paramName] ?? '' );
                }

                return [
                    'config'  => $route['config'],
                    'params'  => $params,
                    'pattern' => $route['pattern'],
                ];
            }
        }

        return null;
    }

    /**
     * Get all registered routes (for debugging / admin display).
     *
     * @return array
     */
    public function listRoutes(): array
    {
        return array_map( function ( array $route ): array {
            return [
                'pattern'   => $route['pattern'],
                'method'    => $route['config']['method'],
                'type'      => $route['config']['type'],
                'auth'      => $route['config']['auth'],
                'plugin_id' => $route['config']['plugin_id'],
            ];
        }, $this->routes );
    }

    /**
     * Check if a pattern already has a registered route.
     *
     * @param  string $pattern Exact pattern (e.g. '/cart').
     * @return bool
     */
    public function hasRoute( string $pattern ): bool
    {
        $pattern = '/' . trim( $pattern, '/' );
        foreach ( $this->routes as $route ) {
            if ( $route['pattern'] === $pattern ) {
                return true;
            }
        }
        return false;
    }
}
