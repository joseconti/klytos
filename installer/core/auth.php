<?php
/**
 * Klytos — Authentication
 * Handles admin login sessions and MCP bearer token validation.
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

        $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
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
        $_SESSION = [];

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

        // Update last activity
        $_SESSION['klytos_last_active'] = time();

        return true;
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
     * Create a new MCP bearer token.
     *
     * @param  string $label Optional label for the token.
     * @return array  ['token' => string (raw), 'id' => string]
     */
    public function createBearerToken(string $label = ''): array
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
            'label'      => $label ?: 'Token ' . date('Y-m-d'),
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
                'created_at' => $t['created_at'] ?? '',
                'last_used'  => $t['last_used'] ?? null,
            ];
        }, $tokensData['tokens'] ?? []);
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
            'label'      => $label ?: 'App Password ' . date('Y-m-d'),
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
     * Send security headers for admin pages.
     *
     * @param string|null $nonce CSP nonce for inline scripts. If null, falls back to unsafe-inline.
     */
    public static function sendSecurityHeaders(?string $nonce = null): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        $scriptSrc = $nonce
            ? "'self' 'nonce-{$nonce}'"
            : "'self' 'unsafe-inline'";

        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' fonts.googleapis.com; font-src fonts.gstatic.com; img-src 'self' data:; script-src {$scriptSrc}; frame-src 'self' blob:");
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
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
