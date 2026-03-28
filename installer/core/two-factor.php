<?php
/**
 * Klytos -- Two-Factor Authentication Manager
 * Supports three 2FA methods: TOTP, Magic Link (email), and Passkeys (WebAuthn).
 *
 * TOTP (RFC 6238):
 *   Time-based one-time passwords compatible with Google Authenticator,
 *   1Password, Authy, and any RFC 6238 compliant app.
 *
 * Magic Link:
 *   Sends a one-time login link via email with a cryptographic token.
 *   Token expires after 10 minutes. Single use.
 *
 * Passkeys (WebAuthn / FIDO2):
 *   Passwordless authentication using biometrics, security keys, or
 *   platform authenticators (1Password, iCloud Keychain, Windows Hello).
 *
 * Recovery Codes:
 *   Eight single-use codes generated when 2FA is first enabled.
 *   Stored as bcrypt hashes. Each code can only be used once.
 *
 * @package Klytos
 * @since   0.5.0
 *
 * @license    Elastic License 2.0 (ELv2) -- https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 Jose Conti -- https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class TwoFactor
{
    private StorageInterface $storage;

    /** @var int TOTP code validity window (30 seconds). */
    private const TOTP_PERIOD = 30;

    /** @var int TOTP code length (6 digits). */
    private const TOTP_DIGITS = 6;

    /** @var int Allow +/- 1 period for clock drift. */
    private const TOTP_WINDOW = 1;

    /** @var int Magic link token lifetime in seconds (10 minutes). */
    private const MAGIC_LINK_LIFETIME = 600;

    /** @var int WebAuthn challenge lifetime in seconds (5 minutes). */
    private const WEBAUTHN_CHALLENGE_LIFETIME = 300;

    /** @var int Number of recovery codes to generate. */
    private const RECOVERY_CODE_COUNT = 8;

    /** @var string Collection name for 2FA data. */
    private const COLLECTION = 'users';

    /** @var string Collection for magic link tokens. */
    private const MAGIC_LINKS_COLLECTION = 'magic-links';

    /** @var string Collection for WebAuthn challenges. */
    private const WEBAUTHN_CHALLENGES_COLLECTION = 'webauthn-challenges';

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    // ================================================================
    //  TOTP -- Time-based One-Time Password (RFC 6238)
    // ================================================================

    /**
     * Generate a new TOTP secret for a user.
     *
     * @return string Base32-encoded secret (160 bits / 20 bytes).
     */
    public function generateTotpSecret(): string
    {
        $bytes = random_bytes(20);
        return self::base32Encode($bytes);
    }

    /**
     * Build the otpauth:// URI for QR code generation.
     *
     * @param string $secret  Base32-encoded secret.
     * @param string $account User email or username.
     * @param string $issuer  Site name (e.g. "Klytos").
     * @return string otpauth:// URI.
     */
    public function getTotpUri(string $secret, string $account, string $issuer = 'Klytos'): string
    {
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::TOTP_DIGITS,
            'period' => self::TOTP_PERIOD,
        ]);

        $label = rawurlencode($issuer) . ':' . rawurlencode($account);

        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Verify a TOTP code against a secret.
     *
     * @param string $secret Base32-encoded secret.
     * @param string $code   6-digit code from the user.
     * @return bool
     */
    public function verifyTotp(string $secret, string $code): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $secretBytes = self::base32Decode($secret);
        $timeSlice   = (int) floor(time() / self::TOTP_PERIOD);

        // Check current period and +/- window for clock drift.
        for ($i = -self::TOTP_WINDOW; $i <= self::TOTP_WINDOW; $i++) {
            $calculatedCode = self::hotpCode($secretBytes, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enable TOTP for a user. Stores the secret in their user record.
     *
     * @param string $userId User ID.
     * @param string $secret Base32-encoded TOTP secret.
     */
    public function enableTotp(string $userId, string $secret): void
    {
        $user = $this->storage->read(self::COLLECTION, $userId);
        $twoFactor = $user['two_factor'] ?? $this->defaultTwoFactor();

        $twoFactor['totp_secret']   = $secret;
        $twoFactor['totp_verified'] = true;

        if (!in_array('totp', $twoFactor['methods'], true)) {
            $twoFactor['methods'][] = 'totp';
        }

        $twoFactor['enabled'] = true;
        $user['two_factor']   = $twoFactor;
        $user['updated_at']   = Helpers::now();

        $this->storage->write(self::COLLECTION, $userId, $user);
    }

    /**
     * Disable TOTP for a user.
     *
     * @param string $userId User ID.
     */
    public function disableTotp(string $userId): void
    {
        $user = $this->storage->read(self::COLLECTION, $userId);
        $twoFactor = $user['two_factor'] ?? $this->defaultTwoFactor();

        $twoFactor['totp_secret']   = null;
        $twoFactor['totp_verified'] = false;
        $twoFactor['methods']       = array_values(
            array_filter($twoFactor['methods'], fn($m) => $m !== 'totp')
        );

        if (empty($twoFactor['methods'])) {
            $twoFactor['enabled'] = false;
        }

        $user['two_factor'] = $twoFactor;
        $user['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $userId, $user);
    }

    // ================================================================
    //  Magic Link -- Email-based one-time login
    // ================================================================

    /**
     * Generate a magic link token for a user.
     *
     * @param string $userId User ID.
     * @param string $email  User email address.
     * @return array ['token' => raw token, 'expires_at' => ISO timestamp]
     */
    public function createMagicLink(string $userId, string $email): array
    {
        $rawToken = Helpers::randomHex(32);
        $tokenHash = hash('sha256', $rawToken);

        $data = [
            'user_id'    => $userId,
            'email'      => $email,
            'hash'       => $tokenHash,
            'created_at' => Helpers::now(),
            'expires_at' => date('c', time() + self::MAGIC_LINK_LIFETIME),
            'used'       => false,
        ];

        $tokenId = 'ml_' . Helpers::randomHex(8);
        $this->storage->write(self::MAGIC_LINKS_COLLECTION, $tokenId, $data);

        return [
            'token'      => $rawToken,
            'token_id'   => $tokenId,
            'expires_at' => $data['expires_at'],
        ];
    }

    /**
     * Verify a magic link token.
     *
     * @param string $token   Raw token from the email link.
     * @param string $userId  Expected user ID.
     * @return bool True if valid and not expired.
     */
    public function verifyMagicLink(string $token, string $userId): bool
    {
        $tokenHash = hash('sha256', $token);
        $links = $this->storage->list(self::MAGIC_LINKS_COLLECTION);

        foreach ($links as $link) {
            if ($link['user_id'] !== $userId) {
                continue;
            }
            if ($link['used']) {
                continue;
            }
            if (!hash_equals($link['hash'], $tokenHash)) {
                continue;
            }

            // Check expiry.
            if (strtotime($link['expires_at']) < time()) {
                return false;
            }

            // Mark as used (single use).
            $link['used'] = true;
            // Find the token ID from the stored data.
            $this->markMagicLinkUsed($tokenHash);

            return true;
        }

        return false;
    }

    /**
     * Send a magic link email via the central Mailer service.
     *
     * @param string $email  Recipient email.
     * @param string $url    Full magic link URL.
     * @param Mailer $mailer The Mailer instance (from $app->getMailer()).
     * @return bool True if mail was sent.
     */
    public function sendMagicLinkEmail(string $email, string $url, Mailer $mailer): bool
    {
        $i18n = $GLOBALS['klytos_i18n'] ?? null;

        $subject = $i18n
            ? $i18n->get('security.magic_link_subject')
            : 'Your login link';

        $message = $i18n
            ? $i18n->get('security.magic_link_body', ['url' => $url, 'minutes' => '10'])
            : "Click the following link to log in. This link expires in 10 minutes and can only be used once.";

        $buttonText = $i18n
            ? $i18n->get('security.send_magic_link')
            : 'Log in';

        return $mailer->sendWithButton($email, $subject, $message, $buttonText, $url);
    }

    /**
     * Enable magic link 2FA for a user.
     *
     * @param string $userId User ID.
     */
    public function enableMagicLink(string $userId): void
    {
        $user = $this->storage->read(self::COLLECTION, $userId);
        $twoFactor = $user['two_factor'] ?? $this->defaultTwoFactor();

        if (!in_array('email', $twoFactor['methods'], true)) {
            $twoFactor['methods'][] = 'email';
        }

        $twoFactor['enabled'] = true;
        $user['two_factor']   = $twoFactor;
        $user['updated_at']   = Helpers::now();

        $this->storage->write(self::COLLECTION, $userId, $user);
    }

    /**
     * Disable magic link 2FA for a user.
     *
     * @param string $userId User ID.
     */
    public function disableMagicLink(string $userId): void
    {
        $user = $this->storage->read(self::COLLECTION, $userId);
        $twoFactor = $user['two_factor'] ?? $this->defaultTwoFactor();

        $twoFactor['methods'] = array_values(
            array_filter($twoFactor['methods'], fn($m) => $m !== 'email')
        );

        if (empty($twoFactor['methods'])) {
            $twoFactor['enabled'] = false;
        }

        $user['two_factor'] = $twoFactor;
        $user['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $userId, $user);
    }

    /**
     * Clean up expired magic link tokens.
     */
    public function cleanupMagicLinks(): void
    {
        $links = $this->storage->list(self::MAGIC_LINKS_COLLECTION);

        foreach ($links as $link) {
            if ($link['used'] || strtotime($link['expires_at'] ?? '2000-01-01') < time()) {
                $id = $link['id'] ?? null;
                if ($id) {
                    $this->storage->delete(self::MAGIC_LINKS_COLLECTION, $id);
                }
            }
        }
    }

    // ================================================================
    //  Passkeys -- WebAuthn / FIDO2
    // ================================================================

    /**
     * Generate a WebAuthn registration challenge.
     *
     * @param string $userId      User ID.
     * @param string $username    Username for display.
     * @param string $displayName Display name.
     * @param string $rpId        Relying Party ID (domain, e.g. "example.com").
     * @param string $rpName      Relying Party name (e.g. "Klytos").
     * @return array PublicKeyCredentialCreationOptions (JSON-serializable).
     */
    public function createPasskeyRegistrationChallenge(
        string $userId,
        string $username,
        string $displayName,
        string $rpId,
        string $rpName = 'Klytos'
    ): array {
        $challenge = random_bytes(32);
        $challengeB64 = self::base64UrlEncode($challenge);

        // Get existing passkeys to exclude.
        $user = $this->storage->read(self::COLLECTION, $userId);
        $existingPasskeys = $user['two_factor']['passkeys'] ?? [];

        $excludeCredentials = array_map(fn($pk) => [
            'type' => 'public-key',
            'id'   => $pk['credential_id'],
        ], $existingPasskeys);

        $options = [
            'challenge' => $challengeB64,
            'rp' => [
                'id'   => $rpId,
                'name' => $rpName,
            ],
            'user' => [
                'id'          => self::base64UrlEncode($userId),
                'name'        => $username,
                'displayName' => $displayName,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'timeout' => self::WEBAUTHN_CHALLENGE_LIFETIME * 1000,
            'authenticatorSelection' => [
                'residentKey'      => 'preferred',
                'userVerification' => 'preferred',
            ],
            'attestation' => 'none',
        ];

        if (!empty($excludeCredentials)) {
            $options['excludeCredentials'] = $excludeCredentials;
        }

        // Store challenge for verification.
        $this->storeWebAuthnChallenge($userId, $challengeB64, 'registration');

        return $options;
    }

    /**
     * Complete passkey registration by verifying the attestation response.
     *
     * @param string $userId              User ID.
     * @param array  $attestationResponse Client attestation response.
     * @param string $rpId                Relying Party ID.
     * @param string $label               Optional label for the passkey.
     * @return array The stored passkey data (without private key material).
     * @throws \RuntimeException On verification failure.
     */
    public function completePasskeyRegistration(
        string $userId,
        array $attestationResponse,
        string $rpId,
        string $label = ''
    ): array {
        // Verify the stored challenge.
        $storedChallenge = $this->getWebAuthnChallenge($userId, 'registration');
        if (!$storedChallenge) {
            throw new \RuntimeException('No pending registration challenge found.');
        }

        $clientDataJSON = self::base64UrlDecode($attestationResponse['clientDataJSON'] ?? '');
        $clientData = json_decode($clientDataJSON, true);

        if (!$clientData) {
            throw new \RuntimeException('Invalid clientDataJSON.');
        }

        // Verify type.
        if (($clientData['type'] ?? '') !== 'webauthn.create') {
            throw new \RuntimeException('Invalid ceremony type.');
        }

        // Verify challenge.
        if (($clientData['challenge'] ?? '') !== $storedChallenge) {
            throw new \RuntimeException('Challenge mismatch.');
        }

        // Verify origin.
        $expectedOrigin = 'https://' . $rpId;
        $origin = $clientData['origin'] ?? '';
        if ($origin !== $expectedOrigin && $origin !== 'https://localhost') {
            // Allow localhost for development.
            if (!str_starts_with($origin, 'https://localhost:')) {
                throw new \RuntimeException('Origin mismatch.');
            }
        }

        // Parse attestation object (CBOR-encoded).
        $attestationObject = self::base64UrlDecode($attestationResponse['attestationObject'] ?? '');
        $parsed = self::parseCborAttestationObject($attestationObject);

        $authData = $parsed['authData'] ?? '';
        if (strlen($authData) < 37) {
            throw new \RuntimeException('Invalid authenticator data.');
        }

        // Verify RP ID hash (first 32 bytes of authData).
        $rpIdHash = substr($authData, 0, 32);
        if (!hash_equals(hash('sha256', $rpId, true), $rpIdHash)) {
            throw new \RuntimeException('RP ID hash mismatch.');
        }

        // Flags byte (byte 32).
        $flags = ord($authData[32]);
        $userPresent  = ($flags & 0x01) !== 0;
        $attestedCred = ($flags & 0x40) !== 0;

        if (!$userPresent) {
            throw new \RuntimeException('User presence flag not set.');
        }

        if (!$attestedCred) {
            throw new \RuntimeException('Attested credential data not present.');
        }

        // Parse attested credential data (starts at byte 37).
        $aaguid = substr($authData, 37, 16);
        $credIdLen = unpack('n', substr($authData, 53, 2))[1];
        $credentialId = substr($authData, 55, $credIdLen);
        $credentialIdB64 = self::base64UrlEncode($credentialId);

        // The public key is the CBOR-encoded data after credential ID.
        $publicKeyCbor = substr($authData, 55 + $credIdLen);
        $publicKeyB64 = self::base64UrlEncode($publicKeyCbor);

        // Store the passkey.
        $passkey = [
            'credential_id' => $credentialIdB64,
            'public_key'    => $publicKeyB64,
            'aaguid'        => bin2hex($aaguid),
            'label'         => $label ?: 'Passkey ' . date('Y-m-d'),
            'sign_count'    => unpack('N', substr($authData, 33, 4))[1],
            'created_at'    => Helpers::now(),
            'last_used'     => null,
        ];

        $user = $this->storage->read(self::COLLECTION, $userId);
        $twoFactor = $user['two_factor'] ?? $this->defaultTwoFactor();
        $twoFactor['passkeys'][] = $passkey;

        if (!in_array('passkey', $twoFactor['methods'], true)) {
            $twoFactor['methods'][] = 'passkey';
        }

        $twoFactor['enabled'] = true;
        $user['two_factor']   = $twoFactor;
        $user['updated_at']   = Helpers::now();

        $this->storage->write(self::COLLECTION, $userId, $user);

        // Clean up challenge.
        $this->deleteWebAuthnChallenge($userId, 'registration');

        return [
            'credential_id' => $credentialIdB64,
            'label'         => $passkey['label'],
            'created_at'    => $passkey['created_at'],
        ];
    }

    /**
     * Generate a WebAuthn authentication challenge.
     *
     * @param string $userId User ID.
     * @param string $rpId   Relying Party ID.
     * @return array PublicKeyCredentialRequestOptions (JSON-serializable).
     */
    public function createPasskeyAuthChallenge(string $userId, string $rpId): array
    {
        $challenge = random_bytes(32);
        $challengeB64 = self::base64UrlEncode($challenge);

        $user = $this->storage->read(self::COLLECTION, $userId);
        $passkeys = $user['two_factor']['passkeys'] ?? [];

        $allowCredentials = array_map(fn($pk) => [
            'type' => 'public-key',
            'id'   => $pk['credential_id'],
        ], $passkeys);

        $options = [
            'challenge'        => $challengeB64,
            'rpId'             => $rpId,
            'timeout'          => self::WEBAUTHN_CHALLENGE_LIFETIME * 1000,
            'userVerification' => 'preferred',
        ];

        if (!empty($allowCredentials)) {
            $options['allowCredentials'] = $allowCredentials;
        }

        $this->storeWebAuthnChallenge($userId, $challengeB64, 'authentication');

        return $options;
    }

    /**
     * Verify a WebAuthn authentication assertion.
     *
     * @param string $userId            User ID.
     * @param array  $assertionResponse Client assertion response.
     * @param string $rpId              Relying Party ID.
     * @return bool True if assertion is valid.
     */
    public function verifyPasskeyAssertion(
        string $userId,
        array $assertionResponse,
        string $rpId
    ): bool {
        $storedChallenge = $this->getWebAuthnChallenge($userId, 'authentication');
        if (!$storedChallenge) {
            return false;
        }

        $credentialIdB64 = $assertionResponse['credentialId'] ?? '';
        $clientDataJSON  = self::base64UrlDecode($assertionResponse['clientDataJSON'] ?? '');
        $authData        = self::base64UrlDecode($assertionResponse['authenticatorData'] ?? '');
        $signature       = self::base64UrlDecode($assertionResponse['signature'] ?? '');

        $clientData = json_decode($clientDataJSON, true);
        if (!$clientData) {
            return false;
        }

        // Verify type.
        if (($clientData['type'] ?? '') !== 'webauthn.get') {
            return false;
        }

        // Verify challenge.
        if (($clientData['challenge'] ?? '') !== $storedChallenge) {
            return false;
        }

        // Verify RP ID hash.
        $rpIdHash = substr($authData, 0, 32);
        if (!hash_equals(hash('sha256', $rpId, true), $rpIdHash)) {
            return false;
        }

        // Check user presence.
        $flags = ord($authData[32]);
        if (($flags & 0x01) === 0) {
            return false;
        }

        // Find the matching passkey.
        $user = $this->storage->read(self::COLLECTION, $userId);
        $passkeys = $user['two_factor']['passkeys'] ?? [];
        $matchedIndex = null;

        foreach ($passkeys as $index => $pk) {
            if ($pk['credential_id'] === $credentialIdB64) {
                $matchedIndex = $index;
                break;
            }
        }

        if ($matchedIndex === null) {
            return false;
        }

        $storedPasskey = $passkeys[$matchedIndex];

        // Verify signature.
        $publicKeyCbor = self::base64UrlDecode($storedPasskey['public_key']);
        $publicKeyPem = self::coseKeyToPem($publicKeyCbor);

        if (!$publicKeyPem) {
            return false;
        }

        // The signed data is authData + SHA-256(clientDataJSON).
        $clientDataHash = hash('sha256', $clientDataJSON, true);
        $signedData = $authData . $clientDataHash;

        $algorithm = self::getCoseAlgorithm($publicKeyCbor);
        $opensslAlgo = $algorithm === -257 ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA256;

        $valid = openssl_verify($signedData, $signature, $publicKeyPem, $opensslAlgo);

        if ($valid !== 1) {
            return false;
        }

        // Update sign count and last used.
        $newSignCount = unpack('N', substr($authData, 33, 4))[1];
        $passkeys[$matchedIndex]['sign_count'] = $newSignCount;
        $passkeys[$matchedIndex]['last_used']  = Helpers::now();

        $user['two_factor']['passkeys'] = $passkeys;
        $this->storage->write(self::COLLECTION, $userId, $user);

        // Clean up challenge.
        $this->deleteWebAuthnChallenge($userId, 'authentication');

        return true;
    }

    /**
     * Remove a passkey from a user.
     *
     * @param string $userId       User ID.
     * @param string $credentialId Base64URL-encoded credential ID.
     * @return bool True if removed.
     */
    public function removePasskey(string $userId, string $credentialId): bool
    {
        $user = $this->storage->read(self::COLLECTION, $userId);
        $twoFactor = $user['two_factor'] ?? $this->defaultTwoFactor();

        $original = count($twoFactor['passkeys']);
        $twoFactor['passkeys'] = array_values(
            array_filter($twoFactor['passkeys'], fn($pk) => $pk['credential_id'] !== $credentialId)
        );

        if (count($twoFactor['passkeys']) === $original) {
            return false;
        }

        if (empty($twoFactor['passkeys'])) {
            $twoFactor['methods'] = array_values(
                array_filter($twoFactor['methods'], fn($m) => $m !== 'passkey')
            );
        }

        if (empty($twoFactor['methods'])) {
            $twoFactor['enabled'] = false;
        }

        $user['two_factor'] = $twoFactor;
        $user['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $userId, $user);
        return true;
    }

    // ================================================================
    //  Recovery Codes
    // ================================================================

    /**
     * Generate recovery codes for a user.
     * Replaces any existing recovery codes.
     *
     * @param string $userId User ID.
     * @return array Raw recovery codes (show once, then discard).
     */
    public function generateRecoveryCodes(string $userId): array
    {
        $rawCodes = [];
        $hashedCodes = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            // Format: xxxx-xxxx-xxxx (12 hex chars with hyphens).
            $hex = Helpers::randomHex(6);
            $raw = substr($hex, 0, 4) . '-' . substr($hex, 4, 4) . '-' . substr($hex, 8, 4);
            $rawCodes[] = $raw;
            $hashedCodes[] = password_hash($raw, PASSWORD_BCRYPT, ['cost' => 10]);
        }

        $user = $this->storage->read(self::COLLECTION, $userId);
        $twoFactor = $user['two_factor'] ?? $this->defaultTwoFactor();
        $twoFactor['recovery_codes'] = $hashedCodes;

        $user['two_factor'] = $twoFactor;
        $user['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $userId, $user);

        return $rawCodes;
    }

    /**
     * Verify and consume a recovery code.
     *
     * @param string $userId User ID.
     * @param string $code   Raw recovery code.
     * @return bool True if valid (and now consumed).
     */
    public function verifyRecoveryCode(string $userId, string $code): bool
    {
        $code = trim(strtolower($code));
        $user = $this->storage->read(self::COLLECTION, $userId);
        $twoFactor = $user['two_factor'] ?? $this->defaultTwoFactor();

        foreach ($twoFactor['recovery_codes'] as $index => $hash) {
            if (password_verify($code, $hash)) {
                // Remove used code (single use).
                array_splice($twoFactor['recovery_codes'], $index, 1);
                $user['two_factor'] = $twoFactor;
                $this->storage->write(self::COLLECTION, $userId, $user);
                return true;
            }
        }

        return false;
    }

    /**
     * Count remaining recovery codes for a user.
     *
     * @param string $userId User ID.
     * @return int
     */
    public function countRecoveryCodes(string $userId): int
    {
        try {
            $user = $this->storage->read(self::COLLECTION, $userId);
        } catch (\RuntimeException $e) {
            return 0;
        }

        return count($user['two_factor']['recovery_codes'] ?? []);
    }

    // ================================================================
    //  Query Methods
    // ================================================================

    /**
     * Check if a user has 2FA enabled.
     *
     * @param string $userId User ID.
     * @return bool
     */
    public function isEnabled(string $userId): bool
    {
        try {
            $user = $this->storage->read(self::COLLECTION, $userId);
        } catch (\RuntimeException $e) {
            return false;
        }

        return !empty($user['two_factor']['enabled']);
    }

    /**
     * Get the 2FA configuration for a user (without secrets).
     *
     * @param string $userId User ID.
     * @return array Sanitized 2FA config.
     */
    public function getUserConfig(string $userId): array
    {
        try {
            $user = $this->storage->read(self::COLLECTION, $userId);
        } catch (\RuntimeException $e) {
            return $this->defaultTwoFactor();
        }

        $twoFactor = $user['two_factor'] ?? $this->defaultTwoFactor();

        // Sanitize: remove secrets.
        return [
            'enabled'              => $twoFactor['enabled'] ?? false,
            'methods'              => $twoFactor['methods'] ?? [],
            'totp_configured'      => !empty($twoFactor['totp_secret']),
            'passkey_count'        => count($twoFactor['passkeys'] ?? []),
            'passkeys'             => array_map(fn($pk) => [
                'credential_id' => $pk['credential_id'],
                'label'         => $pk['label'],
                'created_at'    => $pk['created_at'],
                'last_used'     => $pk['last_used'],
            ], $twoFactor['passkeys'] ?? []),
            'recovery_codes_left'  => count($twoFactor['recovery_codes'] ?? []),
        ];
    }

    /**
     * Get the enabled methods for a user.
     *
     * @param string $userId User ID.
     * @return array List of method strings (e.g. ['totp', 'email', 'passkey']).
     */
    public function getEnabledMethods(string $userId): array
    {
        try {
            $user = $this->storage->read(self::COLLECTION, $userId);
        } catch (\RuntimeException $e) {
            return [];
        }

        $twoFactor = $user['two_factor'] ?? [];
        if (empty($twoFactor['enabled'])) {
            return [];
        }

        return $twoFactor['methods'] ?? [];
    }

    /**
     * Completely disable 2FA for a user, removing all methods and data.
     *
     * @param string $userId User ID.
     */
    public function disableAll(string $userId): void
    {
        $user = $this->storage->read(self::COLLECTION, $userId);
        $user['two_factor'] = $this->defaultTwoFactor();
        $user['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $userId, $user);
    }

    // ================================================================
    //  Internal Helpers
    // ================================================================

    /**
     * Default two_factor structure for a user.
     */
    private function defaultTwoFactor(): array
    {
        return [
            'enabled'        => false,
            'methods'        => [],
            'totp_secret'    => null,
            'totp_verified'  => false,
            'recovery_codes' => [],
            'passkeys'       => [],
        ];
    }

    /**
     * Mark a magic link as used by its hash.
     */
    private function markMagicLinkUsed(string $tokenHash): void
    {
        $links = $this->storage->list(self::MAGIC_LINKS_COLLECTION);
        foreach ($links as $link) {
            if (hash_equals($link['hash'] ?? '', $tokenHash)) {
                $link['used'] = true;
                $id = $link['id'] ?? null;
                if ($id) {
                    $this->storage->write(self::MAGIC_LINKS_COLLECTION, $id, $link);
                }
                return;
            }
        }
    }

    /**
     * Store a WebAuthn challenge.
     */
    private function storeWebAuthnChallenge(string $userId, string $challenge, string $type): void
    {
        $id = 'wac_' . md5($userId . '_' . $type);
        $this->storage->write(self::WEBAUTHN_CHALLENGES_COLLECTION, $id, [
            'user_id'    => $userId,
            'challenge'  => $challenge,
            'type'       => $type,
            'created_at' => Helpers::now(),
            'expires_at' => date('c', time() + self::WEBAUTHN_CHALLENGE_LIFETIME),
        ]);
    }

    /**
     * Retrieve and validate a WebAuthn challenge.
     */
    private function getWebAuthnChallenge(string $userId, string $type): ?string
    {
        $id = 'wac_' . md5($userId . '_' . $type);
        try {
            $data = $this->storage->read(self::WEBAUTHN_CHALLENGES_COLLECTION, $id);
        } catch (\RuntimeException $e) {
            return null;
        }

        if ($data['user_id'] !== $userId || $data['type'] !== $type) {
            return null;
        }

        if (strtotime($data['expires_at']) < time()) {
            $this->storage->delete(self::WEBAUTHN_CHALLENGES_COLLECTION, $id);
            return null;
        }

        return $data['challenge'];
    }

    /**
     * Delete a WebAuthn challenge.
     */
    private function deleteWebAuthnChallenge(string $userId, string $type): void
    {
        $id = 'wac_' . md5($userId . '_' . $type);
        try {
            $this->storage->delete(self::WEBAUTHN_CHALLENGES_COLLECTION, $id);
        } catch (\RuntimeException $e) {
            // Ignore.
        }
    }

    // ================================================================
    //  HOTP / TOTP Core (RFC 4226 / RFC 6238)
    // ================================================================

    /**
     * Calculate a HOTP code (RFC 4226).
     *
     * @param string $secret Raw binary secret.
     * @param int    $counter Counter value.
     * @return string Zero-padded code string.
     */
    private static function hotpCode(string $secret, int $counter): string
    {
        // Pack counter as 8-byte big-endian.
        $counterBytes = pack('J', $counter);

        // HMAC-SHA1.
        $hash = hash_hmac('sha1', $counterBytes, $secret, true);

        // Dynamic truncation.
        $offset = ord($hash[19]) & 0x0F;
        $code = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            ((ord($hash[$offset + 3]) & 0xFF))
        ) % (10 ** self::TOTP_DIGITS);

        return str_pad((string) $code, self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }

    // ================================================================
    //  Base32 Encoding / Decoding (RFC 4648)
    // ================================================================

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Encode raw bytes to Base32 (RFC 4648, no padding).
     */
    public static function base32Encode(string $data): string
    {
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $result = '';
        $chunks = str_split($binary, 5);
        foreach ($chunks as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $result .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $result;
    }

    /**
     * Decode a Base32 string to raw bytes.
     */
    public static function base32Decode(string $data): string
    {
        $data = strtoupper(rtrim($data, '='));
        $binary = '';

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $pos = strpos(self::BASE32_ALPHABET, $data[$i]);
            if ($pos === false) {
                continue; // Skip invalid characters.
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        $chunks = str_split($binary, 8);
        foreach ($chunks as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }
            $result .= chr(bindec($chunk));
        }

        return $result;
    }

    // ================================================================
    //  Base64URL Encoding / Decoding (WebAuthn)
    // ================================================================

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');
        return base64_decode(strtr($padded, '-_', '+/'), true) ?: '';
    }

    // ================================================================
    //  Minimal CBOR Parser (for WebAuthn attestation objects)
    // ================================================================

    /**
     * Parse a CBOR-encoded attestation object.
     * Minimal implementation: handles only what WebAuthn needs.
     *
     * @param string $data Raw CBOR bytes.
     * @return array Parsed map with 'fmt', 'attStmt', 'authData'.
     */
    private static function parseCborAttestationObject(string $data): array
    {
        $offset = 0;
        return self::parseCborValue($data, $offset);
    }

    /**
     * Parse a CBOR value at the given offset.
     */
    private static function parseCborValue(string $data, int &$offset): mixed
    {
        if ($offset >= strlen($data)) {
            return null;
        }

        $byte = ord($data[$offset]);
        $majorType = ($byte >> 5) & 0x07;
        $additional = $byte & 0x1F;
        $offset++;

        $value = self::parseCborAdditional($data, $offset, $additional);

        switch ($majorType) {
            case 0: // Unsigned integer.
                return $value;

            case 1: // Negative integer.
                return -1 - $value;

            case 2: // Byte string.
                $result = substr($data, $offset, (int) $value);
                $offset += (int) $value;
                return $result;

            case 3: // Text string.
                $result = substr($data, $offset, (int) $value);
                $offset += (int) $value;
                return $result;

            case 4: // Array.
                $arr = [];
                for ($i = 0; $i < $value; $i++) {
                    $arr[] = self::parseCborValue($data, $offset);
                }
                return $arr;

            case 5: // Map.
                $map = [];
                for ($i = 0; $i < $value; $i++) {
                    $key = self::parseCborValue($data, $offset);
                    $val = self::parseCborValue($data, $offset);
                    if (is_string($key) || is_int($key)) {
                        $map[$key] = $val;
                    }
                }
                return $map;

            case 7: // Simple / float.
                return $value;

            default:
                return null;
        }
    }

    /**
     * Parse CBOR additional info to get the integer value.
     */
    private static function parseCborAdditional(string $data, int &$offset, int $additional): int
    {
        if ($additional < 24) {
            return $additional;
        }

        switch ($additional) {
            case 24:
                $val = ord($data[$offset]);
                $offset += 1;
                return $val;
            case 25:
                $val = unpack('n', substr($data, $offset, 2))[1];
                $offset += 2;
                return $val;
            case 26:
                $val = unpack('N', substr($data, $offset, 4))[1];
                $offset += 4;
                return $val;
            case 27:
                $val = unpack('J', substr($data, $offset, 8))[1];
                $offset += 8;
                return (int) $val;
            default:
                return 0;
        }
    }

    // ================================================================
    //  COSE Key to PEM Conversion (for WebAuthn signature verification)
    // ================================================================

    /**
     * Convert a COSE public key (CBOR-encoded) to PEM format.
     *
     * @param string $cborKey CBOR-encoded COSE key.
     * @return string|null PEM-encoded public key, or null on failure.
     */
    private static function coseKeyToPem(string $cborKey): ?string
    {
        $offset = 0;
        $key = self::parseCborValue($cborKey, $offset);

        if (!is_array($key)) {
            return null;
        }

        // COSE key type (1 = OKP, 2 = EC2, 3 = RSA).
        $kty = $key[1] ?? null;
        // Algorithm (-7 = ES256, -257 = RS256).
        $alg = $key[3] ?? null;

        if ($kty === 2 && $alg === -7) {
            // EC2 / ES256 (P-256).
            $x = $key[-2] ?? '';
            $y = $key[-3] ?? '';

            if (strlen($x) !== 32 || strlen($y) !== 32) {
                return null;
            }

            // Uncompressed point: 0x04 || x || y.
            $point = "\x04" . $x . $y;

            // Wrap in SubjectPublicKeyInfo ASN.1 structure.
            // OID 1.2.840.10045.2.1 (ecPublicKey) + OID 1.2.840.10045.3.1.7 (P-256).
            $der = "\x30\x59" .                    // SEQUENCE (89 bytes)
                   "\x30\x13" .                    // SEQUENCE (19 bytes)
                   "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" . // OID ecPublicKey
                   "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" . // OID P-256
                   "\x03\x42\x00" .                // BIT STRING (66 bytes)
                   $point;

            $pem = "-----BEGIN PUBLIC KEY-----\n" .
                   chunk_split(base64_encode($der), 64, "\n") .
                   "-----END PUBLIC KEY-----\n";

            return $pem;
        }

        if ($kty === 3 && $alg === -257) {
            // RSA / RS256.
            $n = $key[-1] ?? '';
            $e = $key[-2] ?? '';

            if (empty($n) || empty($e)) {
                return null;
            }

            // Build RSA public key DER.
            $modulus  = self::asn1Integer($n);
            $exponent = self::asn1Integer($e);
            $rsaKey   = self::asn1Sequence($modulus . $exponent);
            $bitString = "\x00" . $rsaKey;

            // OID 1.2.840.113549.1.1.1 (rsaEncryption).
            $algorithmId = self::asn1Sequence(
                "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01" . // OID
                "\x05\x00" // NULL
            );

            $der = self::asn1Sequence(
                $algorithmId .
                self::asn1BitString($bitString)
            );

            $pem = "-----BEGIN PUBLIC KEY-----\n" .
                   chunk_split(base64_encode($der), 64, "\n") .
                   "-----END PUBLIC KEY-----\n";

            return $pem;
        }

        return null;
    }

    /**
     * Get the COSE algorithm from a CBOR-encoded key.
     */
    private static function getCoseAlgorithm(string $cborKey): int
    {
        $offset = 0;
        $key = self::parseCborValue($cborKey, $offset);
        return is_array($key) ? ($key[3] ?? -7) : -7;
    }

    // ─── ASN.1 DER Helpers ──────────────────────────────────────

    private static function asn1Length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        if ($length < 0x100) {
            return "\x81" . chr($length);
        }
        return "\x82" . pack('n', $length);
    }

    private static function asn1Sequence(string $data): string
    {
        return "\x30" . self::asn1Length(strlen($data)) . $data;
    }

    private static function asn1Integer(string $data): string
    {
        // Ensure positive integer (prepend 0x00 if high bit set).
        if (ord($data[0]) & 0x80) {
            $data = "\x00" . $data;
        }
        return "\x02" . self::asn1Length(strlen($data)) . $data;
    }

    private static function asn1BitString(string $data): string
    {
        return "\x03" . self::asn1Length(strlen($data)) . $data;
    }
}
