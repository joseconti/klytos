<?php

/**
 * Klytos — MCP Authentication
 * Multi-method authentication for the MCP endpoint.
 * Supports: Bearer tokens, OAuth 2.0/2.1 access tokens, Application Passwords.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
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

    /** @var array|null The resolved actor {user_id, role} for the current request, or null. */
    private ?array $actor = null;

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
        $this->actor          = null;

        // 1. Try Bearer token (existing tokens.json.enc)
        $bearerToken = $this->extractToken();
        if ($bearerToken !== null) {
            if ($this->auth->validateBearerToken($bearerToken)) {
                $this->authMethod     = 'bearer';
                $this->authIdentifier = 'token:' . substr(hash('sha256', $bearerToken), 0, 16);
                // Bearer tokens carry no user — the role lives on the token record (D-047).
                $this->actor          = $this->auth->getBearerTokenActor($bearerToken);
                return true;
            }

            // 2. Try as OAuth access token
            if ($this->app !== null) {
                $oauthResult = $this->validateOAuthToken($bearerToken);
                if ($oauthResult !== null) {
                    $this->authMethod     = 'oauth';
                    $this->authIdentifier = 'oauth:' . ($oauthResult['token_id'] ?? '');
                    // OAuth tokens carry a username — resolve its role from the user store.
                    $this->actor          = $this->resolveUserActor($oauthResult['user'] ?? null);
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
                // App passwords are pinned to the admin user — resolve its role from the store.
                $this->actor          = $this->resolveUserActor($basicAuth['username']);
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
     * Get the resolved actor {user_id, role} for the current authenticated request.
     *
     * The MCP path builds identity from the CREDENTIAL, not a session: there is no
     * session here (Auth::startSession() runs only in the admin path), so
     * klytos_current_user() returns null on this path. The MCP authorization gate
     * (D-046) reads this actor's role. A null actor — or a null role inside it —
     * means the request could not be attributed to a usable role and MUST be denied.
     *
     * @return array|null ['user_id' => int|string|null, 'role' => string|null], or null.
     */
    public function getActor(): ?array
    {
        return $this->actor;
    }

    /**
     * Resolve the actor for a credential that carries a username (application
     * password or OAuth token) by reading the user's role from the single user
     * store. DRY and forward-compatible with per-user credentials (NEW-11): the
     * role follows the user record rather than a copy stamped on the credential.
     *
     * An empty username, a username that no longer resolves to a user, or any
     * storage error yields null — the fail-closed direction the gate treats as
     * deny (this is the NEW-08 link: a valid credential whose owner record is gone
     * denies rather than escalating).
     *
     * @param  string|null $username
     * @return array|null  ['user_id' => int|string|null, 'role' => string|null], or null.
     */
    private function resolveUserActor(?string $username): ?array
    {
        if ($username === null || $username === '' || $this->app === null) {
            return null;
        }

        try {
            $user = $this->app->getUserManager()->getByUsername($username);
        } catch (\Throwable $e) {
            return null;
        }

        if ($user === null) {
            return null;
        }

        $role = $user['role'] ?? null;

        return [
            'user_id' => $user['id'] ?? null,
            'role'    => ( is_string( $role ) && $role !== '' ) ? $role : null,
        ];
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
     * @return array|null    ['token_id' => string, 'client_id' => string, 'user' => string|null]
     *                       if valid, null if not. The 'user' subject is what the actor
     *                       resolver reads to attribute the request to a role (D-047).
     */
    private function validateOAuthToken(string $token): ?array
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
                return $result;
            }
        } catch (\Throwable $e) {
            // OAuth server not available or error — fall through
        }

        return null;
    }
}
