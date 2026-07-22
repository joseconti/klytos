<?php

/**
 * Klytos — Authentication
 * Handles admin login sessions and MCP bearer token validation.
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

namespace Klytos\Core;

class Auth
{
    private array $config;
    /** @var StorageInterface Storage backend (FileStorage or DatabaseStorage). */
    private StorageInterface $storage;

    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES    = 15;
    private const SESSION_LIFETIME   = 1800; // 30 minutes

    public function __construct(array $config, StorageInterface $storage)
    {
        $this->config  = $config;
        $this->storage = $storage;
    }

    // ─── Admin Session Auth ────────────────────────────────────

    /**
     * Start a secure session for the admin panel.
     */
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure   = Helpers::isHttps();
        $basePath = Helpers::getBasePath();

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $basePath . 'admin/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly'  => true,
            'samesite' => 'Strict',
        ]);

        session_name('klytos_session');
        session_start();
    }

    /**
     * Attempt admin login.
     *
     * @param  string $username
     * @param  string $password
     * @return array  ['success' => bool, 'error' => string]
     */
    /**
     * Attempt admin login.
     *
     * When 2FA is enabled for the user, the login succeeds at the password
     * stage but sets a pending 2FA flag. The caller must then verify the
     * second factor before full access is granted.
     *
     * @param  string $username
     * @param  string $password
     * @return array  ['success' => bool, 'error' => string, 'requires_2fa' => bool, 'user_id' => string|null]
     */
    public function login(string $username, string $password): array
    {
        // Check lockout
        if ($this->isLockedOut()) {
            $minutes = self::LOCKOUT_MINUTES;
            return [
                'success'      => false,
                'error'        => "account_locked:{$minutes}",
                'requires_2fa' => false,
                'user_id'      => null,
            ];
        }

        // Pre-hook: allow plugins to act before credential validation.
        klytos_do_action('auth.before_login', $username);

        $validUser = $this->config['admin_user'] ?? '';
        $validHash = $this->config['admin_pass_hash'] ?? '';

        if ($username === $validUser && password_verify($password, $validHash)) {
            // Regenerate session ID for security
            session_regenerate_id(true);

            // Reset failed attempts
            $this->resetLoginAttempts();

            // Check if the user has 2FA enabled.
            $userId = $this->resolveUserId($username);

            if ($userId && $this->userHasTwoFactor($userId)) {
                // Password verified, but 2FA is required.
                $_SESSION['klytos_2fa_pending'] = true;
                $_SESSION['klytos_2fa_user']    = $username;
                $_SESSION['klytos_2fa_user_id'] = $userId;
                $_SESSION['klytos_2fa_time']    = time();
                $_SESSION['klytos_csrf']        = Helpers::randomHex(32);

                return [
                    'success'      => true,
                    'error'        => '',
                    'requires_2fa' => true,
                    'user_id'      => $userId,
                ];
            }

            // No 2FA -- grant full access immediately.
            $_SESSION['klytos_auth']        = true;
            $_SESSION['klytos_user']        = $username;
            $_SESSION['klytos_user_id']     = $userId;
            $_SESSION['klytos_login_time']  = time();
            $_SESSION['klytos_last_active'] = time();

            // Generate CSRF token
            $_SESSION['klytos_csrf'] = Helpers::randomHex(32);

            // Set admin bar cookie for the public site (non-HttpOnly so JS can read it).
            $this->setAdminBarCookie();

            // Post-hook: notify plugins of successful login.
            klytos_do_action('auth.after_login', $username, $userId);

            return [
                'success'      => true,
                'error'        => '',
                'requires_2fa' => false,
                'user_id'      => $userId,
            ];
        }

        // Record failed attempt
        $this->recordFailedAttempt();

        return [
            'success'      => false,
            'error'        => 'login_failed',
            'requires_2fa' => false,
            'user_id'      => null,
        ];
    }

    /**
     * Complete 2FA verification and grant full session access.
     * Called after the second factor has been successfully verified.
     */
    public function complete2fa(): void
    {
        $username = $_SESSION['klytos_2fa_user'] ?? '';
        $userId   = $_SESSION['klytos_2fa_user_id'] ?? '';

        // Clear 2FA pending state.
        unset(
            $_SESSION['klytos_2fa_pending'],
            $_SESSION['klytos_2fa_user'],
            $_SESSION['klytos_2fa_user_id'],
            $_SESSION['klytos_2fa_time']
        );

        // Grant full access.
        session_regenerate_id(true);

        $_SESSION['klytos_auth']        = true;
        $_SESSION['klytos_user']        = $username;
        $_SESSION['klytos_user_id']     = $userId;
        $_SESSION['klytos_login_time']  = time();
        $_SESSION['klytos_last_active'] = time();
        $_SESSION['klytos_csrf']        = Helpers::randomHex(32);

        // Post-hook: notify plugins of successful login after 2FA completion.
        klytos_do_action('auth.after_login', $username, $userId);
    }

    /**
     * Check if there is a pending 2FA challenge.
     *
     * @return bool
     */
    public function is2faPending(): bool
    {
        if (empty($_SESSION['klytos_2fa_pending'])) {
            return false;
        }

        // 2FA challenge expires after 5 minutes.
        $challengeTime = $_SESSION['klytos_2fa_time'] ?? 0;
        if ((time() - $challengeTime) > 300) {
            $this->cancel2fa();
            return false;
        }

        return true;
    }

    /**
     * Cancel a pending 2FA challenge (e.g. on timeout or user cancellation).
     */
    public function cancel2fa(): void
    {
        unset(
            $_SESSION['klytos_2fa_pending'],
            $_SESSION['klytos_2fa_user'],
            $_SESSION['klytos_2fa_user_id'],
            $_SESSION['klytos_2fa_time']
        );
    }

    /**
     * Get the user ID for the pending 2FA challenge.
     *
     * @return string|null
     */
    public function get2faPendingUserId(): ?string
    {
        return $_SESSION['klytos_2fa_user_id'] ?? null;
    }

    /**
     * Logout the current admin session.
     */
    public function logout(): void
    {
        // Pre-hook: allow plugins to act before session destruction.
        klytos_do_action('user.logout', $_SESSION['klytos_user'] ?? '', $_SESSION['klytos_user_id'] ?? '');

        $_SESSION = [];

        // Clear admin bar cookie.
        setcookie( 'klytos_admin_bar', '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax'] );

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Check if the current session is authenticated.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        if (empty($_SESSION['klytos_auth'])) {
            return false;
        }

        // Check session timeout (30 min inactivity)
        $lastActive = $_SESSION['klytos_last_active'] ?? 0;
        if ((time() - $lastActive) > self::SESSION_LIFETIME) {
            $this->logout();
            return false;
        }

        // Check force_logout_at (throttled to once per 60 seconds).
        $userId = $_SESSION['klytos_user_id'] ?? null;
        $lastForceCheck = $_SESSION['klytos_last_force_check'] ?? 0;

        if ($userId && (time() - $lastForceCheck) > 60) {
            $_SESSION['klytos_last_force_check'] = time();
            try {
                $user = $this->storage->read('users', $userId);
                $forceAt = $user['force_logout_at'] ?? null;
                if ($forceAt && ($_SESSION['klytos_login_time'] ?? 0) < strtotime($forceAt)) {
                    $this->logout();
                    return false;
                }
            } catch (\RuntimeException $e) {
                // User not found in storage — ignore.
            }
        }

        // Update last activity
        $_SESSION['klytos_last_active'] = time();

        // Ensure admin bar cookie exists for the public-site toolbar.
        // The cookie is set at login but may be missing if the feature was
        // deployed after the session started, or if the browser discarded it.
        if ( !isset( $_COOKIE['klytos_admin_bar'] ) ) {
            $this->setAdminBarCookie();
        }

        return true;
    }

    /**
     * Set the admin bar cookie so the public-site toolbar can detect
     * authenticated admins.  Called on login and refreshed on demand.
     */
    private function setAdminBarCookie(): void
    {
        $enabled = true;
        try {
            $enabled = (bool) ( App::getInstance()->getSiteConfig()->getValue( 'admin_bar_enabled', true ) );
        } catch ( \Throwable $e ) {
            // Config may not be loaded yet.
        }

        if ( !$enabled ) {
            return;
        }

        $basePath    = Helpers::getBasePath();
        $adminUrl    = $basePath . 'admin/';
        $cookieValue = json_encode( [
            'admin_url' => $adminUrl,
            'user_name' => $this->getDisplayName(),
            'version'   => defined( 'KLYTOS_VERSION' ) ? KLYTOS_VERSION : '1.0',
        ] );

        setcookie( 'klytos_admin_bar', $cookieValue, [
            'expires'  => 0,
            'path'     => '/',
            'secure'   => Helpers::isHttps(),
            'httponly'  => false,
            'samesite' => 'Lax',
        ] );
    }

    /**
     * Generate and store a CSRF token for forms.
     *
     * @return string
     */
    public function getCsrfToken(): string
    {
        if (empty($_SESSION['klytos_csrf'])) {
            $_SESSION['klytos_csrf'] = Helpers::randomHex(32);
        }

        return $_SESSION['klytos_csrf'];
    }

    /**
     * Validate a CSRF token from a form submission.
     *
     * @param  string $token
     * @return bool
     */
    public function validateCsrf(string $token): bool
    {
        $expected = $_SESSION['klytos_csrf'] ?? '';
        return hash_equals($expected, $token);
    }

    /**
     * Get the currently logged-in username.
     *
     * @return string
     */
    public function getUsername(): string
    {
        return $_SESSION['klytos_user'] ?? '';
    }

    /**
     * Get the currently logged-in user ID.
     *
     * @return string|null
     */
    public function getUserId(): ?string
    {
        return $_SESSION['klytos_user_id'] ?? null;
    }

    /**
     * Get a human-readable display name for the current user.
     * Tries display_name from storage, falls back to session username.
     */
    public function getDisplayName(): string
    {
        $userId = $this->getUserId();
        if ( $userId ) {
            try {
                $user = $this->storage->read( 'users', $userId );
                if ( !empty( $user['display_name'] ) ) {
                    return $user['display_name'];
                }
            } catch ( \RuntimeException $e ) {
                // User not found — fall through.
            }
        }
        return $_SESSION['klytos_user'] ?? 'Admin';
    }

    // ─── MCP Bearer Token Auth ─────────────────────────────────

    /**
     * Validate a Bearer token from the Authorization header.
     *
     * @param  string $token Raw bearer token.
     * @return bool
     */
    public function validateBearerToken(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $tokenHash = Helpers::hashToken($token);

        try {
            $tokensData = $this->storage->read('config', 'tokens');
        } catch (\RuntimeException $e) {
            return false;
        }

        $tokens = $tokensData['tokens'] ?? [];

        foreach ($tokens as $stored) {
            if (hash_equals($stored['hash'] ?? '', $tokenHash)) {
                // Update last used timestamp
                $this->updateTokenLastUsed($tokenHash);
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the actor {user_id, role} carried by a bearer token.
     *
     * Bearer tokens are not tied to a user record — they carry no username — so
     * the role lives on the token record itself, stamped by createBearerToken()
     * and backfilled to 'owner' for pre-Sprint-2 tokens by migrateCredentialRoles().
     * A record with no usable role resolves to a null role, which the MCP gate
     * (D-046) treats as deny — the fail-closed direction UserManager::hasPermission()
     * already takes for a record with no role. Read-only: it does NOT update
     * last_used (validateBearerToken() already did).
     *
     * @param  string $token Raw bearer token.
     * @return array|null    ['user_id' => null, 'role' => string|null], or null if the token is unknown.
     */
    public function getBearerTokenActor(string $token): ?array
    {
        if (empty($token)) {
            return null;
        }

        $tokenHash = Helpers::hashToken($token);

        try {
            $tokensData = $this->storage->read('config', 'tokens');
        } catch (\RuntimeException $e) {
            return null;
        }

        foreach ($tokensData['tokens'] ?? [] as $stored) {
            if (hash_equals($stored['hash'] ?? '', $tokenHash)) {
                $role = $stored['role'] ?? null;
                return [
                    'user_id' => $stored['user_id'] ?? null,
                    'role'    => ( is_string( $role ) && $role !== '' ) ? $role : null,
                ];
            }
        }

        return null;
    }

    /**
     * Create a new MCP bearer token.
     *
     * @param  string $label Optional label for the token.
     * @param  string $role  Role the token operates as (D-047). Defaults to 'owner',
     *                       which reproduces the pre-Sprint-2 behaviour where every
     *                       bearer token held owner-equivalent power (NEW-02). A lower
     *                       role (e.g. 'viewer') mints a genuinely reduced credential —
     *                       the only credential type mintable below owner today, since
     *                       application passwords are pinned to the admin user until
     *                       NEW-11.
     * @return array  ['token' => string (raw), 'id' => string]
     */
    public function createBearerToken(string $label = '', string $role = 'owner'): array
    {
        $rawToken  = Helpers::generateBearerToken();
        $tokenHash = Helpers::hashToken($rawToken);
        $tokenId   = Helpers::randomHex(8);

        try {
            $tokensData = $this->storage->read('config', 'tokens');
        } catch (\RuntimeException $e) {
            $tokensData = ['tokens' => []];
        }

        $tokensData['tokens'][] = [
            'id'         => $tokenId,
            'hash'       => $tokenHash,
            'label'      => $label ?: 'Token ' . klytos_gmdate( 'Y-m-d' ),
            'role'       => $role,
            'user_id'    => null,
            'created_at' => Helpers::now(),
            'last_used'  => null,
        ];

        $this->storage->write('config', 'tokens', $tokensData);

        return [
            'token' => $rawToken,
            'id'    => $tokenId,
        ];
    }

    /**
     * Revoke (delete) a bearer token by ID.
     *
     * @param  string $tokenId
     * @return bool
     */
    public function revokeBearerToken(string $tokenId): bool
    {
        try {
            $tokensData = $this->storage->read('config', 'tokens');
        } catch (\RuntimeException $e) {
            return false;
        }

        $original = count($tokensData['tokens'] ?? []);
        $tokensData['tokens'] = array_values(
            array_filter($tokensData['tokens'] ?? [], fn($t) => ($t['id'] ?? '') !== $tokenId)
        );

        if (count($tokensData['tokens']) === $original) {
            return false;
        }

        $this->storage->write('config', 'tokens', $tokensData);
        return true;
    }

    /**
     * List all bearer tokens (hashed, no raw values).
     *
     * @return array
     */
    public function listBearerTokens(): array
    {
        try {
            $tokensData = $this->storage->read('config', 'tokens');
        } catch (\RuntimeException $e) {
            return [];
        }

        // Return tokens without the hash (for display)
        return array_map(function ($t) {
            return [
                'id'         => $t['id'] ?? '',
                'label'      => $t['label'] ?? '',
                'role'       => $t['role'] ?? null,
                'created_at' => $t['created_at'] ?? '',
                'last_used'  => $t['last_used'] ?? null,
            ];
        }, $tokensData['tokens'] ?? []);
    }

    /**
     * Idempotent migration (D-047): stamp bearer-token records that carry no role
     * with 'owner'.
     *
     * This records what is already true rather than widening anything — every
     * bearer token minted before Sprint 2 operated with owner-equivalent power,
     * because the MCP tool layer had no authorization at all (NEW-02). Stamping
     * them 'owner' lets the new gate (D-046) read a role without changing what any
     * existing token can do.
     *
     * Bearer tokens only. Application passwords and OAuth tokens carry a username,
     * so their role is resolved from the user record at validation time (D-047 as
     * amended in slice 1) — there is nothing to stamp.
     *
     * Safe on every boot: it writes only when it actually stamps something, so a
     * migrated store is a no-op. Called from App::boot() and directly by tests.
     *
     * @return int Number of token records stamped (0 when already migrated or none exist).
     */
    public function migrateCredentialRoles(): int
    {
        try {
            $tokensData = $this->storage->read('config', 'tokens');
        } catch (\RuntimeException $e) {
            // No tokens store yet (fresh install) — nothing to migrate.
            return 0;
        }

        // Guard rather than `?? []` in the foreach: iterating `$x['k'] ?? []` BY
        // REFERENCE binds &$stored to a throwaway temporary produced by `??`, so the
        // stamps never write back to $tokensData — a silent no-op that returns a
        // non-zero count. Caught by the migration test before it was trusted.
        if (!isset($tokensData['tokens']) || !is_array($tokensData['tokens'])) {
            return 0;
        }

        $stamped = 0;

        foreach ($tokensData['tokens'] as &$stored) {
            $role = $stored['role'] ?? null;
            if (!is_string($role) || $role === '') {
                $stored['role']    = 'owner';
                $stored['user_id'] = $stored['user_id'] ?? null;
                $stamped++;
            }
        }
        unset($stored);

        if ($stamped > 0) {
            $this->storage->write('config', 'tokens', $tokensData);
        }

        return $stamped;
    }

    // ─── Application Passwords ──────────────────────────────────

    /**
     * Create a new Application Password.
     *
     * @param  string $label    Label for the password.
     * @param  string $username Admin username this password belongs to.
     * @return array  ['password' => string (raw, show once), 'id' => string]
     */
    public function createAppPassword(string $label, string $username): array
    {
        $rawPassword = $this->generateAppPasswordString();
        $hash        = password_hash($rawPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $passwordId  = 'ap_' . Helpers::randomHex(8);

        $data = $this->loadAppPasswords();

        // Limit to 25 for bcrypt performance
        if (count($data['passwords'] ?? []) >= 25) {
            throw new \RuntimeException('Maximum of 25 application passwords reached.');
        }

        $data['passwords'][] = [
            'id'         => $passwordId,
            'username'   => $username,
            'label'      => $label ?: 'App Password ' . klytos_gmdate( 'Y-m-d' ),
            'hash'       => $hash,
            'created_at' => Helpers::now(),
            'last_used'  => null,
        ];

        $this->storage->write('config', 'app_passwords', $data);

        return [
            'password' => $rawPassword,
            'id'       => $passwordId,
        ];
    }

    /**
     * Validate an Application Password via HTTP Basic Auth credentials.
     *
     * @param  string $username
     * @param  string $password Raw application password.
     * @return string|null      App password ID if valid, null if invalid.
     */
    public function validateAppPassword(string $username, string $password): ?string
    {
        if (empty($username) || empty($password)) {
            return null;
        }

        // Verify username matches the configured admin user
        $validUser = $this->config['admin_user'] ?? '';
        if ($username !== $validUser) {
            return null;
        }

        $data = $this->loadAppPasswords();

        foreach ($data['passwords'] ?? [] as &$stored) {
            if (($stored['username'] ?? '') !== $username) {
                continue;
            }

            if (password_verify($password, $stored['hash'] ?? '')) {
                // Update last used
                $stored['last_used'] = Helpers::now();
                $this->storage->write('config', 'app_passwords', $data);
                return $stored['id'] ?? null;
            }
        }
        unset($stored);

        return null;
    }

    /**
     * Revoke (delete) an Application Password by ID.
     *
     * @param  string $passwordId
     * @return bool
     */
    public function revokeAppPassword(string $passwordId): bool
    {
        $data = $this->loadAppPasswords();

        $original = count($data['passwords'] ?? []);
        $data['passwords'] = array_values(
            array_filter($data['passwords'] ?? [], fn($p) => ($p['id'] ?? '') !== $passwordId)
        );

        if (count($data['passwords']) === $original) {
            return false;
        }

        $this->storage->write('config', 'app_passwords', $data);
        return true;
    }

    /**
     * List all Application Passwords (without hashes).
     *
     * @return array
     */
    public function listAppPasswords(): array
    {
        $data = $this->loadAppPasswords();

        return array_map(function ($p) {
            return [
                'id'         => $p['id'] ?? '',
                'username'   => $p['username'] ?? '',
                'label'      => $p['label'] ?? '',
                'created_at' => $p['created_at'] ?? '',
                'last_used'  => $p['last_used'] ?? null,
            ];
        }, $data['passwords'] ?? []);
    }

    /**
     * Load application passwords data.
     */
    private function loadAppPasswords(): array
    {
        try {
            return $this->storage->read('config', 'app_passwords');
        } catch (\RuntimeException $e) {
            return ['passwords' => []];
        }
    }

    /**
     * Generate a random application password string.
     * Format: xxxx-xxxx-xxxx-xxxx-xxxx-xxxx (24 random chars + hyphens)
     *
     * @return string
     */
    private function generateAppPasswordString(): string
    {
        $raw    = Helpers::randomHex(12); // 24 hex chars
        $chunks = str_split($raw, 4);
        return implode('-', $chunks);
    }

    // ─── Login Attempt Tracking ────────────────────────────────

    /**
     * Check if the account is currently locked out.
     */
    private function isLockedOut(): bool
    {
        $file = $this->getLockoutFile();
        if (!file_exists($file)) {
            return false;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!$data) {
            return false;
        }

        $attempts = $data['attempts'] ?? 0;
        $lastTime = $data['last_attempt'] ?? 0;

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $elapsed = time() - $lastTime;
            if ($elapsed < (self::LOCKOUT_MINUTES * 60)) {
                return true;
            }
            // Lockout expired, reset
            $this->resetLoginAttempts();
        }

        return false;
    }

    /**
     * Record a failed login attempt.
     */
    private function recordFailedAttempt(): void
    {
        $file = $this->getLockoutFile();
        $data = ['attempts' => 0, 'last_attempt' => 0];

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: $data;
        }

        $data['attempts']     = ($data['attempts'] ?? 0) + 1;
        $data['last_attempt'] = time();

        file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Reset failed login attempts.
     */
    private function resetLoginAttempts(): void
    {
        $file = $this->getLockoutFile();
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Get the path to the lockout tracking file.
     */
    private function getLockoutFile(): string
    {
        return sys_get_temp_dir() . '/klytos_lockout_' . md5($this->config['admin_user'] ?? 'admin') . '.json';
    }

    /**
     * Update the last_used timestamp for a token.
     */
    private function updateTokenLastUsed(string $tokenHash): void
    {
        try {
            $tokensData = $this->storage->read('config', 'tokens');
        } catch (\RuntimeException $e) {
            return;
        }

        foreach ($tokensData['tokens'] as &$token) {
            if (hash_equals($token['hash'] ?? '', $tokenHash)) {
                $token['last_used'] = Helpers::now();
                break;
            }
        }
        unset($token);

        $this->storage->write('config', 'tokens', $tokensData);
    }

    // ─── Security Headers ──────────────────────────────────────

    /**
     * Send the response security headers. The ONE place headers are decided.
     *
     * Called once per request from admin/bootstrap.php (which every admin page
     * and API endpoint requires), and from the public entry points. Safe to
     * call again later with a nonce or a custom policy: header() replaces a
     * header of the same name, so the last call wins — which is how a page
     * upgrades the bootstrap's baseline CSP to its own nonced one.
     *
     * @param string|null $nonce     CSP nonce for this request's inline scripts.
     *                               When null the policy FAILS CLOSED to 'self'
     *                               — see the note below.
     * @param string|null $customCsp Complete replacement policy. Bypasses the
     *                               nonce logic entirely.
     */
    public static function sendSecurityHeaders(?string $nonce = null, ?string $customCsp = null): void
    {
        foreach ( self::buildSecurityHeaders( $nonce, $customCsp ) as $name => $value ) {
            header( $name . ': ' . $value );
        }
    }

    /**
     * Compute the security headers for this request, without sending them.
     *
     * Split out from sendSecurityHeaders() so the POLICY can be tested
     * independently of the emission. header() is a no-op under the CLI SAPI
     * and headers_list() returns nothing there, so a unit test that drove
     * sendSecurityHeaders() directly could observe absolutely nothing — and
     * its "the header is absent" assertions would pass against ANY code,
     * including code that never set a header at all. That is L-010's failure
     * mode (a check that cannot fail), and it is why this function exists.
     *
     * The two tiers now prove different halves: the unit tier asserts the
     * policy this returns, and tests/Integration/SecurityHeadersHttpTest.php
     * asserts that it actually reaches the wire on a real response.
     *
     * @param  string|null $nonce     CSP nonce, or null to fail closed.
     * @param  string|null $customCsp Complete replacement policy.
     * @return array<string,string> Header name => value.
     */
    public static function buildSecurityHeaders(?string $nonce = null, ?string $customCsp = null): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'DENY',
            'X-XSS-Protection'       => '1; mode=block',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
        ];

        // S-11 — HSTS, but ONLY over TLS. A browser ignores this header on a
        // cleartext response anyway, and sending it there would be a claim the
        // transport cannot back. Deliberately without includeSubDomains: this
        // is self-hosted software and a sibling subdomain the operator runs on
        // plain HTTP is not ours to break — a directive a browser caches for a
        // year is not something to opt an installed base into by default.
        // Filterable for operators who want to widen it (preload, subdomains).
        if ( Helpers::isHttps() ) {
            $hsts = 'max-age=31536000';

            // function_exists() because this method must stay callable from a
            // PRE-BOOT entry point: klytos_apply_filters() lives in
            // helpers-global.php, which app.php:331 requires inside boot().
            // Public entry points (NEW-18's subject) run without it, and a
            // header function that fatals is worse than an unfilterable one.
            if ( function_exists( 'klytos_apply_filters' ) ) {
                $hsts = klytos_apply_filters( 'security.hsts', $hsts );
            }

            if ( is_string( $hsts ) && $hsts !== '' ) {
                $headers['Strict-Transport-Security'] = $hsts;
            }
        }

        if ($customCsp !== null) {
            $headers['Content-Security-Policy'] = $customCsp;
        } else {
            // FAIL CLOSED. This previously fell back to 'unsafe-inline' when no
            // nonce was supplied, so any caller that forgot one silently got the
            // weakest policy in the product and nothing signalled it. A missing
            // nonce now means "no inline script runs", which is the safe
            // direction to be wrong in: the failure is a visible broken widget,
            // not a silently disabled defence.
            $scriptSrc = $nonce ? "'self' 'nonce-{$nonce}'" : "'self'";

            // style-src keeps 'unsafe-inline' ON PURPOSE (S-10, deferred to its
            // own sprint): 349 inline style= attributes across 40 files cannot
            // carry a nonce. Adding a nonce SOURCE here would make browsers
            // ignore 'unsafe-inline' per CSP Level 3 and break every one of
            // them — which is why the <style> blocks carry nonce attributes
            // that do nothing yet, ready for the slice that removes it.
            $headers['Content-Security-Policy'] = "default-src 'self'; style-src 'self' 'unsafe-inline' fonts.googleapis.com; font-src 'self' fonts.gstatic.com; img-src 'self' data:; script-src {$scriptSrc}; frame-src 'self' blob:";
        }

        $headers['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=()';

        return $headers;
    }

    /**
     * Generate a cryptographic nonce for CSP.
     *
     * @return string Base64-encoded nonce.
     */
    public static function generateCspNonce(): string
    {
        return base64_encode(random_bytes(16));
    }

    // ─── 2FA Helpers ────────────────────────────────────────────

    /**
     * Resolve a username to a user ID from the users collection.
     *
     * @param  string $username
     * @return string|null User ID, or null if not found.
     */
    private function resolveUserId(string $username): ?string
    {
        try {
            $users = $this->storage->list('users');
        } catch (\RuntimeException $e) {
            return null;
        }

        foreach ($users as $user) {
            if (($user['username'] ?? '') === $username) {
                return $user['id'] ?? null;
            }
        }

        return null;
    }

    /**
     * Check if a user has 2FA enabled.
     *
     * @param  string $userId
     * @return bool
     */
    private function userHasTwoFactor(string $userId): bool
    {
        try {
            $user = $this->storage->read('users', $userId);
        } catch (\RuntimeException $e) {
            return false;
        }

        return !empty($user['two_factor']['enabled']);
    }
}
