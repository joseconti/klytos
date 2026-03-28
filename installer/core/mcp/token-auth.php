<?php
/**
 * Klytos — MCP Authentication
 * Multi-method authentication for the MCP endpoint.
 * Supports: Bearer tokens, OAuth 2.0/2.1 access tokens, Application Passwords.
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

use Klytos\Core\Auth;
use Klytos\Core\App;

class TokenAuth
{
    private Auth $auth;
    private ?App $app;

    /** @var string The authentication method used ('bearer'|'oauth'|'app_password'|'') */
    private string $authMethod = '';

    /** @var string Identifier for rate limiting (e.g. 'token:abc123', 'apppass:ap_xyz') */
    private string $authIdentifier = '';

    public function __construct(Auth $auth, ?App $app = null)
    {
        $this->auth = $auth;
        $this->app  = $app;
    }

    /**
     * Extract the Bearer token from the Authorization header.
     *
     * @return string|null The raw token, or null if not present.
     */
    public function extractToken(): ?string
    {
        $header = $this->getAuthorizationHeader();

        if (empty($header)) {
            return null;
        }

        // Extract Bearer token
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Extract Basic Auth credentials from the Authorization header.
     *
     * Tries multiple sources in order:
     * 1. Authorization header (standard, via getAuthorizationHeader).
     * 2. PHP_AUTH_USER / PHP_AUTH_PW (set by Apache/PHP automatically).
     * 3. Embedded URL credentials (user:pass@host converted by HTTP client).
     *
     * @return array|null ['username' => string, 'password' => string] or null.
     */
    public function extractBasicAuth(): ?array
    {
        // 1. Try the Authorization header.
        $header = $this->getAuthorizationHeader();

        if ( !empty( $header ) && preg_match( '/^Basic\s+(.+)$/i', $header, $matches ) ) {
            $decoded = base64_decode( trim( $matches[1] ), true );
            if ( $decoded !== false ) {
                $parts = explode( ':', $decoded, 2 );
                if ( count( $parts ) === 2 ) {
                    return [
                        'username' => $parts[0],
                        'password' => $parts[1],
                    ];
                }
            }
        }

        // 2. Try PHP_AUTH_USER / PHP_AUTH_PW (Apache module mode,
        //    or CGI with .htaccess rewrite rule passing Authorization).
        if ( !empty( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
            return [
                'username' => $_SERVER['PHP_AUTH_USER'],
                'password' => $_SERVER['PHP_AUTH_PW'],
            ];
        }

        return null;
    }

    /**
     * Validate the current request using all available auth methods.
     * Tries in order: Bearer token -> OAuth access token -> Basic Auth (App Password).
     *
     * @return bool True if any authentication method succeeds.
     */
    public function validate(): bool
    {
        $this->authMethod     = '';
        $this->authIdentifier = '';

        // 1. Try Bearer token (existing tokens.json.enc)
        $bearerToken = $this->extractToken();
        if ($bearerToken !== null) {
            if ($this->auth->validateBearerToken($bearerToken)) {
                $this->authMethod     = 'bearer';
                $this->authIdentifier = 'token:' . substr(hash('sha256', $bearerToken), 0, 16);
                return true;
            }

            // 2. Try as OAuth access token
            if ($this->app !== null) {
                $oauthResult = $this->validateOAuthToken($bearerToken);
                if ($oauthResult !== null) {
                    $this->authMethod     = 'oauth';
                    $this->authIdentifier = 'oauth:' . $oauthResult;
                    return true;
                }
            }
        }

        // 3. Try Basic Auth (Application Passwords)
        $basicAuth = $this->extractBasicAuth();
        if ($basicAuth !== null) {
            $appPassId = $this->auth->validateAppPassword(
                $basicAuth['username'],
                $basicAuth['password']
            );
            if ($appPassId !== null) {
                $this->authMethod     = 'app_password';
                $this->authIdentifier = 'apppass:' . $appPassId;
                return true;
            }
        }

        return false;
    }

    /**
     * Require authentication — throws RuntimeException if invalid.
     *
     * @return void
     * @throws \RuntimeException If authentication fails.
     */
    public function require(): void
    {
        if (!$this->validate()) {
            throw new \RuntimeException('Unauthorized: Invalid or missing authentication credentials.');
        }
    }

    /**
     * Get the authentication method used for the current request.
     *
     * @return string 'bearer', 'oauth', 'app_password', or '' if not authenticated.
     */
    public function getAuthMethod(): string
    {
        return $this->authMethod;
    }

    /**
     * Get the identifier for rate limiting purposes.
     *
     * @return string e.g. 'token:abc123', 'oauth:ot_xyz', 'apppass:ap_xyz'
     */
    public function getAuthIdentifier(): string
    {
        return $this->authIdentifier;
    }

    /**
     * Get the Authorization header from various sources.
     *
     * @return string
     */
    private function getAuthorizationHeader(): string
    {
        // Try standard header
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        // Fallback for Apache (CGI mode)
        if (empty($header) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header  = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        // Fallback for REDIRECT_HTTP_AUTHORIZATION (some proxy setups)
        if (empty($header)) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        return $header;
    }

    /**
     * Validate a Bearer token as an OAuth 2.0 access token.
     *
     * @param  string $token Raw Bearer token.
     * @return string|null   OAuth token ID if valid, null if not.
     */
    private function validateOAuthToken(string $token): ?string
    {
        try {
            require_once dirname(__FILE__) . '/oauth-server.php';

            $oauthServer = new OAuthServer(
                $this->auth,
                $this->app->getStorage(),
                new RateLimiter($this->app->getStorage()->getDataDir())
            );

            $result = $oauthServer->validateAccessToken($token);
            if ($result !== null) {
                return $result['token_id'] ?? null;
            }
        } catch (\Throwable $e) {
            // OAuth server not available or error — fall through
        }

        return null;
    }
}
