<?php
/**
 * Klytos — MCP Server
 * Streamable HTTP implementation of the Model Context Protocol.
 * Handles JSON-RPC 2.0 requests over POST and server info over GET.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\MCP;

use Klytos\Core\App;
use Klytos\Core\Helpers;

class Server
{
    private App $app;
    private TokenAuth $tokenAuth;
    private ToolRegistry $registry;

    private const PROTOCOL_VERSION = '2025-03-26';
    private const SERVER_NAME      = 'klytos';
    private const MAX_BODY_SIZE    = 1048576; // 1 MB

    public function __construct(App $app)
    {
        $this->app       = $app;
        $this->tokenAuth = new TokenAuth($app->getAuth(), $app);
        $this->registry  = new ToolRegistry($app);

        // Register all tools
        $this->registry->registerAllTools();
    }

    /**
     * Handle GET requests — return server info.
     * Authenticated requests get full info, anonymous get minimal.
     */
    public function handleGet(): void
    {
        if ($this->tokenAuth->validate()) {
            Helpers::jsonResponse([
                'name'    => self::SERVER_NAME,
                'version' => $this->app->getVersion(),
                'status'  => 'ok',
                'mcp'     => self::PROTOCOL_VERSION,
            ]);
        }

        // Unauthenticated: minimal info only
        Helpers::jsonResponse([
            'name'   => self::SERVER_NAME,
            'status' => 'ok',
        ]);
    }

    /**
     * Handle POST requests — process JSON-RPC 2.0 messages.
     */
    public function handlePost(): void
    {
        // Validate request body size before reading
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > self::MAX_BODY_SIZE) {
            http_response_code(413);
            Helpers::jsonResponse(
                JsonRpc::error(-32000, 'Request body too large.'),
                413
            );
        }

        // Check auth failure rate limit
        $rateLimiter = new RateLimiter($this->app->getDataPath());
        $clientIp    = RateLimiter::getClientIp();

        if ($rateLimiter->isAuthBlocked($clientIp)) {
            http_response_code(429);
            header('Retry-After: 60');
            Helpers::jsonResponse(
                JsonRpc::error(-32000, 'Too many authentication failures. Try again later.'),
                429
            );
        }

        // Authenticate
        try {
            $this->tokenAuth->require();
        } catch (\RuntimeException $e) {
            // Record auth failure for rate limiting
            $rateLimiter->recordAuthFailure($clientIp);

            http_response_code(401);
            header('WWW-Authenticate: Bearer');
            Helpers::jsonResponse(
                JsonRpc::error(-32000, 'Unauthorized: Invalid or missing authentication credentials.'),
                401
            );
        }

        // Rate limit authenticated requests
        $authId = $this->tokenAuth->getAuthIdentifier();
        if (!$rateLimiter->check($authId)) {
            http_response_code(429);
            header('Retry-After: 60');
            header('X-RateLimit-Limit: 60');
            header('X-RateLimit-Remaining: 0');
            Helpers::jsonResponse(
                JsonRpc::error(-32000, 'Rate limit exceeded. Try again later.'),
                429
            );
        }

        // Add rate limit headers
        header('X-RateLimit-Limit: 60');
        header('X-RateLimit-Remaining: ' . $rateLimiter->getRemainingRequests($authId));

        // Read and validate body
        $rawBody = file_get_contents('php://input', false, null, 0, self::MAX_BODY_SIZE + 1);
        if ($rawBody === false || strlen($rawBody) > self::MAX_BODY_SIZE) {
            http_response_code(413);
            Helpers::jsonResponse(
                JsonRpc::error(-32000, 'Request body too large.'),
                413
            );
        }

        try {
            $request = JsonRpc::parseRequest($rawBody);
        } catch (\RuntimeException $e) {
            Helpers::jsonResponse(JsonRpc::parseError());
        }

        $method = $request['method'];
        $params = $request['params'];
        $id     = $request['id'];

        // JSON-RPC 2.0: Notifications have no "id" field.
        // The server MUST NOT reply to notifications.
        $isNotification = ( $id === null );

        // Handle known notifications silently.
        if ( $isNotification ) {
            // Accept known MCP notifications without response.
            if ( in_array( $method, [
                'notifications/initialized',
                'notifications/cancelled',
                'notifications/progress',
                'notifications/roots/list_changed',
            ], true ) ) {
                http_response_code( 204 );
                exit;
            }
        }

        // Dispatch JSON-RPC methods (requests with id).
        $response = match ( $method ) {
            'initialize' => $this->handleInitialize( $params, $id ),
            'ping'       => JsonRpc::success( new \stdClass(), $id ),
            'tools/list' => $this->handleToolsList( $id ),
            'tools/call' => $this->handleToolsCall( $params, $id ),
            default      => JsonRpc::methodNotFound( $method, $id ),
        };

        Helpers::jsonResponse( $response );
    }

    /**
     * Handle the initialize method — MCP handshake.
     */
    private function handleInitialize(array $params, string|int|null $id): array
    {
        return JsonRpc::success([
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities'    => [
                'tools' => [
                    'listChanged' => false,
                ],
            ],
            'serverInfo'      => [
                'name'    => self::SERVER_NAME,
                'version' => $this->app->getVersion(),
            ],
        ], $id);
    }

    /**
     * Handle tools/list — return all available tools.
     */
    private function handleToolsList(string|int|null $id): array
    {
        return JsonRpc::success([
            'tools' => $this->registry->listTools(),
        ], $id);
    }

    /**
     * Handle tools/call — execute a specific tool.
     */
    private function handleToolsCall(array $params, string|int|null $id): array
    {
        $toolName  = $params['name'] ?? '';
        $toolArgs  = $params['arguments'] ?? [];

        if (empty($toolName)) {
            return JsonRpc::invalidParams('Missing tool name.', $id);
        }

        if (!$this->registry->exists($toolName)) {
            return JsonRpc::invalidParams("Unknown tool: {$toolName}", $id);
        }

        $result = $this->registry->call($toolName, $toolArgs);

        return JsonRpc::success($result, $id);
    }
}
