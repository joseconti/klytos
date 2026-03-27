<?php
/**
 * Klytos — OAuth 2.0/2.1 Authorization Server
 * Implements Authorization Code + PKCE (S256), Client Credentials, and Refresh Token flows.
 *
 * OAuth 2.1 compliance:
 * - PKCE required for ALL clients (S256 only, plain rejected)
 * - No Implicit Grant (response_type=token rejected)
 * - No Resource Owner Password Credentials (grant_type=password rejected)
 * - Refresh token rotation (one-time use)
 * - Redirect URI exact string match
 * - Bearer tokens in Authorization header only (query params rejected)
 *
 * @package Klytos
 * @since   1.1.0
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
use Klytos\Core\StorageInterface;
use Klytos\Core\Helpers;

class OAuthServer
{
    private Auth $auth;

    /** @var StorageInterface Storage backend (FileStorage or DatabaseStorage). */
    private StorageInterface $storage;

    private RateLimiter $rateLimiter;

    private const CLIENT_FILE     = 'oauth_clients.json.enc';
    private const CODE_FILE       = 'oauth_codes.json.enc';
    private const TOKEN_FILE      = 'oauth_tokens.json.enc';
    private const CODE_LIFETIME   = 600;       // 10 minutes
    private const ACCESS_LIFETIME = 3600;      // 1 hour
    private const REFRESH_LIFETIME = 2592000;  // 30 days

    public function __construct(Auth $auth, StorageInterface $storage, RateLimiter $rateLimiter)
    {
        $this->auth        = $auth;
        $this->storage     = $storage;
        $this->rateLimiter = $rateLimiter;
    }

    // ─── Client Management ────────────────────────────────────

    /**
     * Create a new OAuth client.
     *
     * @param  string $name           Client display name.
     * @param  string $redirectUri    Exact redirect URI.
     * @param  bool   $isConfidential Whether client has a secret (server-side apps).
     * @return array  ['client_id', 'client_secret' (if confidential), 'name']
     */
    public function createClient(string $name, string $redirectUri, bool $isConfidential = true): array
    {
        $clientId     = Helpers::randomHex(16); // 32 hex chars
        $clientSecret = null;
        $secretHash   = null;

        if ($isConfidential) {
            $clientSecret = Helpers::randomHex(32); // 64 hex chars
            $secretHash   = Helpers::hashToken($clientSecret);
        }

        $data = $this->loadClients();

        $data['clients'][] = [
            'client_id'          => $clientId,
            'client_secret_hash' => $secretHash,
            'name'               => $name,
            'redirect_uri'       => $redirectUri,
            'is_confidential'    => $isConfidential,
            'created_at'         => Helpers::now(),
        ];

        $this->storage->write(self::CLIENT_FILE, $data);

        $result = [
            'client_id' => $clientId,
            'name'      => $name,
        ];

        if ($clientSecret !== null) {
            $result['client_secret'] = $clientSecret;
        }

        return $result;
    }

    /**
     * List all OAuth clients (without secrets).
     *
     * @return array
     */
    public function listClients(): array
    {
        $data = $this->loadClients();

        return array_map(function ($c) {
            return [
                'client_id'       => $c['client_id'] ?? '',
                'name'            => $c['name'] ?? '',
                'redirect_uri'    => $c['redirect_uri'] ?? '',
                'is_confidential' => $c['is_confidential'] ?? true,
                'created_at'      => $c['created_at'] ?? '',
            ];
        }, $data['clients'] ?? []);
    }

    /**
     * Revoke a client and all its tokens/codes.
     *
     * @param  string $clientId
     * @return bool
     */
    public function revokeClient(string $clientId): bool
    {
        // Remove client
        $clientData = $this->loadClients();
        $original   = count($clientData['clients'] ?? []);
        $clientData['clients'] = array_values(
            array_filter($clientData['clients'] ?? [], fn($c) => ($c['client_id'] ?? '') !== $clientId)
        );

        if (count($clientData['clients']) === $original) {
            return false;
        }

        $this->storage->write(self::CLIENT_FILE, $clientData);

        // Remove all codes for this client
        $codeData = $this->loadCodes();
        $codeData['codes'] = array_values(
            array_filter($codeData['codes'] ?? [], fn($c) => ($c['client_id'] ?? '') !== $clientId)
        );
        $this->storage->write(self::CODE_FILE, $codeData);

        // Remove all tokens for this client
        $tokenData = $this->loadTokens();
        $tokenData['tokens'] = array_values(
            array_filter($tokenData['tokens'] ?? [], fn($t) => ($t['client_id'] ?? '') !== $clientId)
        );
        $this->storage->write(self::TOKEN_FILE, $tokenData);

        return true;
    }

    // ─── Authorization Code Flow ──────────────────────────────

    /**
     * Validate an authorization request before showing consent screen.
     *
     * @param  array $params Request parameters.
     * @return array ['valid' => bool, 'error' => string, 'error_description' => string, 'client' => array|null]
     */
    public function validateAuthorizeRequest(array $params): array
    {
        $clientId            = $params['client_id'] ?? '';
        $redirectUri         = $params['redirect_uri'] ?? '';
        $responseType        = $params['response_type'] ?? '';
        $codeChallenge       = $params['code_challenge'] ?? '';
        $codeChallengeMethod = $params['code_challenge_method'] ?? '';

        // OAuth 2.1: Only response_type=code is allowed
        if ($responseType === 'token') {
            return [
                'valid'             => false,
                'error'             => 'unsupported_response_type',
                'error_description' => 'Implicit grant (response_type=token) is not supported. Use authorization code flow with PKCE.',
                'client'            => null,
            ];
        }

        if ($responseType !== 'code') {
            return [
                'valid'             => false,
                'error'             => 'unsupported_response_type',
                'error_description' => 'Only response_type=code is supported.',
                'client'            => null,
            ];
        }

        // Validate client
        $client = $this->findClient($clientId);
        if ($client === null) {
            return [
                'valid'             => false,
                'error'             => 'invalid_client',
                'error_description' => 'Unknown client_id.',
                'client'            => null,
            ];
        }

        // Redirect URI exact match (OAuth 2.1 requirement)
        if ($redirectUri !== ($client['redirect_uri'] ?? '')) {
            return [
                'valid'             => false,
                'error'             => 'invalid_request',
                'error_description' => 'Redirect URI does not match the registered URI.',
                'client'            => null,
            ];
        }

        // OAuth 2.1: PKCE is REQUIRED for ALL clients
        if (empty($codeChallenge)) {
            return [
                'valid'             => false,
                'error'             => 'invalid_request',
                'error_description' => 'PKCE code_challenge is required.',
                'client'            => null,
            ];
        }

        // OAuth 2.1: Only S256 method is accepted
        if ($codeChallengeMethod !== 'S256') {
            return [
                'valid'             => false,
                'error'             => 'invalid_request',
                'error_description' => 'Only code_challenge_method=S256 is supported. Plain is not allowed.',
                'client'            => null,
            ];
        }

        return [
            'valid'             => true,
            'error'             => '',
            'error_description' => '',
            'client'            => $client,
        ];
    }

    /**
     * Generate an authorization code after admin approval.
     *
     * @param  array $params Validated authorize parameters.
     * @param  string $adminUser The admin user who approved.
     * @return array ['code' => string, 'redirect_uri' => string, 'state' => string]
     */
    public function handleAuthorize(array $params, string $adminUser): array
    {
        $rawCode = Helpers::randomHex(32); // 64 hex chars
        $codeHash = Helpers::hashToken($rawCode);

        $data = $this->loadCodes();

        $data['codes'][] = [
            'code_hash'              => $codeHash,
            'client_id'              => $params['client_id'],
            'redirect_uri'           => $params['redirect_uri'],
            'code_challenge'         => $params['code_challenge'],
            'code_challenge_method'  => $params['code_challenge_method'] ?? 'S256',
            'user'                   => $adminUser,
            'expires_at'             => time() + self::CODE_LIFETIME,
            'created_at'             => Helpers::now(),
        ];

        $this->storage->write(self::CODE_FILE, $data);

        // Cleanup expired codes probabilistically
        if (mt_rand(1, 10) === 1) {
            $this->cleanupExpiredCodes();
        }

        return [
            'code'         => $rawCode,
            'redirect_uri' => $params['redirect_uri'],
            'state'        => $params['state'] ?? '',
        ];
    }

    // ─── Token Endpoint ───────────────────────────────────────

    /**
     * Handle a token request (POST /oauth/token).
     *
     * @param  array $params Form parameters.
     * @return array Token response or error.
     */
    public function handleTokenRequest(array $params): array
    {
        $grantType = $params['grant_type'] ?? '';

        // OAuth 2.1: Reject ROPC
        if ($grantType === 'password') {
            return $this->tokenError(
                'unsupported_grant_type',
                'Resource Owner Password Credentials grant is not supported.'
            );
        }

        return match ($grantType) {
            'authorization_code' => $this->exchangeCode($params),
            'refresh_token'      => $this->refreshToken($params),
            'client_credentials' => $this->clientCredentials($params),
            default              => $this->tokenError('unsupported_grant_type', "Unsupported grant_type: {$grantType}"),
        };
    }

    /**
     * Exchange an authorization code for tokens.
     */
    private function exchangeCode(array $params): array
    {
        $code         = $params['code'] ?? '';
        $clientId     = $params['client_id'] ?? '';
        $clientSecret = $params['client_secret'] ?? '';
        $redirectUri  = $params['redirect_uri'] ?? '';
        $codeVerifier = $params['code_verifier'] ?? '';

        if (empty($code) || empty($clientId)) {
            return $this->tokenError('invalid_request', 'Missing required parameters.');
        }

        // Find and validate client
        $client = $this->findClient($clientId);
        if ($client === null) {
            return $this->tokenError('invalid_client', 'Unknown client.');
        }

        // Verify client secret for confidential clients
        if ($client['is_confidential'] ?? true) {
            if (empty($clientSecret)) {
                return $this->tokenError('invalid_client', 'Client secret required.');
            }
            $expectedHash = $client['client_secret_hash'] ?? '';
            if (!hash_equals($expectedHash, Helpers::hashToken($clientSecret))) {
                return $this->tokenError('invalid_client', 'Invalid client credentials.');
            }
        }

        // Find the auth code
        $codeHash = Helpers::hashToken($code);
        $codeData = $this->loadCodes();
        $foundIndex = null;
        $foundCode  = null;

        foreach ($codeData['codes'] ?? [] as $i => $stored) {
            if (hash_equals($stored['code_hash'] ?? '', $codeHash)) {
                $foundIndex = $i;
                $foundCode  = $stored;
                break;
            }
        }

        if ($foundCode === null) {
            return $this->tokenError('invalid_grant', 'Invalid or expired authorization code.');
        }

        // Verify code not expired
        if (time() > ($foundCode['expires_at'] ?? 0)) {
            // Remove expired code
            array_splice($codeData['codes'], $foundIndex, 1);
            $codeData['codes'] = array_values($codeData['codes']);
            $this->storage->write(self::CODE_FILE, $codeData);
            return $this->tokenError('invalid_grant', 'Authorization code has expired.');
        }

        // Verify client_id matches
        if ($clientId !== ($foundCode['client_id'] ?? '')) {
            return $this->tokenError('invalid_grant', 'Client mismatch.');
        }

        // Verify redirect_uri matches
        if ($redirectUri !== ($foundCode['redirect_uri'] ?? '')) {
            return $this->tokenError('invalid_grant', 'Redirect URI mismatch.');
        }

        // PKCE: Verify code_verifier against stored code_challenge
        $storedChallenge = $foundCode['code_challenge'] ?? '';
        if (!empty($storedChallenge)) {
            if (empty($codeVerifier)) {
                return $this->tokenError('invalid_grant', 'PKCE code_verifier is required.');
            }

            if (!$this->verifyCodeChallenge($codeVerifier, $storedChallenge, $foundCode['code_challenge_method'] ?? 'S256')) {
                return $this->tokenError('invalid_grant', 'PKCE verification failed.');
            }
        }

        // Auth code is single-use — remove it
        array_splice($codeData['codes'], $foundIndex, 1);
        $codeData['codes'] = array_values($codeData['codes']);
        $this->storage->write(self::CODE_FILE, $codeData);

        // Generate tokens
        return $this->issueTokens($clientId, $foundCode['user'] ?? 'admin');
    }

    /**
     * Refresh an access token using a refresh token.
     * OAuth 2.1: Refresh token rotation — old refresh token is invalidated.
     */
    private function refreshToken(array $params): array
    {
        $refreshTokenRaw = $params['refresh_token'] ?? '';
        $clientId        = $params['client_id'] ?? '';
        $clientSecret    = $params['client_secret'] ?? '';

        if (empty($refreshTokenRaw) || empty($clientId)) {
            return $this->tokenError('invalid_request', 'Missing required parameters.');
        }

        // Validate client
        $client = $this->findClient($clientId);
        if ($client === null) {
            return $this->tokenError('invalid_client', 'Unknown client.');
        }

        // Verify secret for confidential clients
        if ($client['is_confidential'] ?? true) {
            if (empty($clientSecret)) {
                return $this->tokenError('invalid_client', 'Client secret required.');
            }
            if (!hash_equals($client['client_secret_hash'] ?? '', Helpers::hashToken($clientSecret))) {
                return $this->tokenError('invalid_client', 'Invalid client credentials.');
            }
        }

        // Find the refresh token
        $refreshHash = Helpers::hashToken($refreshTokenRaw);
        $tokenData   = $this->loadTokens();
        $foundIndex  = null;
        $foundToken  = null;

        foreach ($tokenData['tokens'] ?? [] as $i => $stored) {
            if (hash_equals($stored['refresh_token_hash'] ?? '', $refreshHash)) {
                $foundIndex = $i;
                $foundToken = $stored;
                break;
            }
        }

        if ($foundToken === null) {
            return $this->tokenError('invalid_grant', 'Invalid refresh token.');
        }

        // Verify not expired
        if (time() > ($foundToken['refresh_expires_at'] ?? 0)) {
            array_splice($tokenData['tokens'], $foundIndex, 1);
            $tokenData['tokens'] = array_values($tokenData['tokens']);
            $this->storage->write(self::TOKEN_FILE, $tokenData);
            return $this->tokenError('invalid_grant', 'Refresh token has expired.');
        }

        // Verify client_id matches
        if ($clientId !== ($foundToken['client_id'] ?? '')) {
            return $this->tokenError('invalid_grant', 'Client mismatch.');
        }

        // OAuth 2.1: Rotation — invalidate old token pair
        array_splice($tokenData['tokens'], $foundIndex, 1);
        $tokenData['tokens'] = array_values($tokenData['tokens']);
        $this->storage->write(self::TOKEN_FILE, $tokenData);

        // Issue new token pair
        return $this->issueTokens($clientId, $foundToken['user'] ?? 'admin');
    }

    /**
     * Client Credentials grant — server-to-server auth.
     * Only for confidential clients. No refresh token issued.
     */
    private function clientCredentials(array $params): array
    {
        $clientId     = $params['client_id'] ?? '';
        $clientSecret = $params['client_secret'] ?? '';

        if (empty($clientId) || empty($clientSecret)) {
            return $this->tokenError('invalid_request', 'Missing client credentials.');
        }

        $client = $this->findClient($clientId);
        if ($client === null) {
            return $this->tokenError('invalid_client', 'Unknown client.');
        }

        if (!($client['is_confidential'] ?? true)) {
            return $this->tokenError('unauthorized_client', 'Public clients cannot use client_credentials grant.');
        }

        if (!hash_equals($client['client_secret_hash'] ?? '', Helpers::hashToken($clientSecret))) {
            return $this->tokenError('invalid_client', 'Invalid client credentials.');
        }

        // Issue access token only (no refresh token for client_credentials)
        $rawAccessToken  = Helpers::randomHex(32);
        $accessTokenHash = Helpers::hashToken($rawAccessToken);
        $tokenId         = 'ot_' . Helpers::randomHex(8);

        $tokenData = $this->loadTokens();
        $tokenData['tokens'][] = [
            'id'                  => $tokenId,
            'access_token_hash'   => $accessTokenHash,
            'refresh_token_hash'  => null,
            'client_id'           => $clientId,
            'user'                => null,
            'access_expires_at'   => time() + self::ACCESS_LIFETIME,
            'refresh_expires_at'  => null,
            'created_at'          => Helpers::now(),
            'last_used'           => null,
        ];
        $this->storage->write(self::TOKEN_FILE, $tokenData);

        return [
            'success'      => true,
            'access_token' => $rawAccessToken,
            'token_type'   => 'Bearer',
            'expires_in'   => self::ACCESS_LIFETIME,
        ];
    }

    // ─── Token Validation ─────────────────────────────────────

    /**
     * Validate an OAuth access token.
     *
     * @param  string $token Raw Bearer token.
     * @return array|null    Token metadata if valid, null if invalid/expired.
     */
    public function validateAccessToken(string $token): ?array
    {
        $tokenHash = Helpers::hashToken($token);
        $tokenData = $this->loadTokens();

        foreach ($tokenData['tokens'] as &$stored) {
            if (hash_equals($stored['access_token_hash'] ?? '', $tokenHash)) {
                // Check expiry
                if (time() > ($stored['access_expires_at'] ?? 0)) {
                    return null; // Expired
                }

                // Update last_used
                $stored['last_used'] = Helpers::now();
                $this->storage->write(self::TOKEN_FILE, $tokenData);

                // Probabilistic cleanup
                if (mt_rand(1, 100) === 1) {
                    $this->cleanupExpiredTokens();
                }

                return [
                    'token_id'  => $stored['id'] ?? '',
                    'client_id' => $stored['client_id'] ?? '',
                    'user'      => $stored['user'] ?? null,
                ];
            }
        }
        unset($stored);

        return null;
    }

    // ─── Server Metadata (RFC 8414) ───────────────────────────

    /**
     * Get OAuth server metadata for .well-known endpoint.
     *
     * @param  string $baseUrl Site base URL.
     * @return array
     */
    public function getServerMetadata(string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');

        return [
            'issuer'                                 => $baseUrl,
            'authorization_endpoint'                 => $baseUrl . '/oauth/authorize',
            'token_endpoint'                         => $baseUrl . '/oauth/token',
            'response_types_supported'               => ['code'],
            'grant_types_supported'                  => ['authorization_code', 'client_credentials', 'refresh_token'],
            'token_endpoint_auth_methods_supported'  => ['client_secret_post'],
            'code_challenge_methods_supported'        => ['S256'],
            'service_documentation'                   => 'https://klytos.io/docs/oauth',
        ];
    }

    // ─── Cleanup ──────────────────────────────────────────────

    /**
     * Remove expired authorization codes.
     */
    private function cleanupExpiredCodes(): void
    {
        $data = $this->loadCodes();
        $now  = time();

        $data['codes'] = array_values(
            array_filter($data['codes'] ?? [], fn($c) => ($c['expires_at'] ?? 0) > $now)
        );

        $this->storage->write(self::CODE_FILE, $data);
    }

    /**
     * Remove expired tokens.
     */
    private function cleanupExpiredTokens(): void
    {
        $data = $this->loadTokens();
        $now  = time();

        $data['tokens'] = array_values(
            array_filter($data['tokens'] ?? [], function ($t) use ($now) {
                // Keep if access token hasn't expired, or if refresh token hasn't expired
                $accessAlive  = ($t['access_expires_at'] ?? 0) > $now;
                $refreshAlive = ($t['refresh_expires_at'] ?? 0) > $now;
                return $accessAlive || $refreshAlive;
            })
        );

        $this->storage->write(self::TOKEN_FILE, $data);
    }

    // ─── PKCE ─────────────────────────────────────────────────

    /**
     * Verify a PKCE code_verifier against a stored code_challenge.
     *
     * @param  string $verifier  code_verifier from token request.
     * @param  string $challenge code_challenge from authorize request.
     * @param  string $method    Challenge method (only S256 accepted).
     * @return bool
     */
    private function verifyCodeChallenge(string $verifier, string $challenge, string $method): bool
    {
        if ($method !== 'S256') {
            return false;
        }

        // S256: BASE64URL(SHA256(code_verifier)) == code_challenge
        $hash = hash('sha256', $verifier, true);
        $computed = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        return hash_equals($challenge, $computed);
    }

    // ─── Token Issuance ───────────────────────────────────────

    /**
     * Issue a new access + refresh token pair.
     */
    private function issueTokens(string $clientId, string $user): array
    {
        $rawAccessToken   = Helpers::randomHex(32);
        $rawRefreshToken  = Helpers::randomHex(32);
        $accessTokenHash  = Helpers::hashToken($rawAccessToken);
        $refreshTokenHash = Helpers::hashToken($rawRefreshToken);
        $tokenId          = 'ot_' . Helpers::randomHex(8);

        $tokenData = $this->loadTokens();
        $tokenData['tokens'][] = [
            'id'                  => $tokenId,
            'access_token_hash'   => $accessTokenHash,
            'refresh_token_hash'  => $refreshTokenHash,
            'client_id'           => $clientId,
            'user'                => $user,
            'access_expires_at'   => time() + self::ACCESS_LIFETIME,
            'refresh_expires_at'  => time() + self::REFRESH_LIFETIME,
            'created_at'          => Helpers::now(),
            'last_used'           => null,
        ];

        $this->storage->write(self::TOKEN_FILE, $tokenData);

        return [
            'success'       => true,
            'access_token'  => $rawAccessToken,
            'token_type'    => 'Bearer',
            'expires_in'    => self::ACCESS_LIFETIME,
            'refresh_token' => $rawRefreshToken,
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────

    /**
     * Find a client by client_id.
     */
    private function findClient(string $clientId): ?array
    {
        $data = $this->loadClients();

        foreach ($data['clients'] ?? [] as $client) {
            if (($client['client_id'] ?? '') === $clientId) {
                return $client;
            }
        }

        return null;
    }

    /**
     * Build a standard OAuth error response.
     */
    private function tokenError(string $error, string $description): array
    {
        return [
            'success'           => false,
            'error'             => $error,
            'error_description' => $description,
        ];
    }

    private function loadClients(): array
    {
        try {
            return $this->storage->read(self::CLIENT_FILE);
        } catch (\RuntimeException $e) {
            return ['clients' => []];
        }
    }

    private function loadCodes(): array
    {
        try {
            return $this->storage->read(self::CODE_FILE);
        } catch (\RuntimeException $e) {
            return ['codes' => []];
        }
    }

    private function loadTokens(): array
    {
        try {
            return $this->storage->read(self::TOKEN_FILE);
        } catch (\RuntimeException $e) {
            return ['tokens' => []];
        }
    }
}
