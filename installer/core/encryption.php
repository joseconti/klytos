<?php

/**
 * Klytos — Encryption Engine
 * AES-256-GCM encryption for all stored data.
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

class Encryption
{
    private const CIPHER    = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private string $key;

    /**
     * @param string $keyPath Absolute path to the encryption key file.
     * @throws \RuntimeException If the key file is missing or invalid.
     */
    public function __construct(string $keyPath)
    {
        if (!file_exists($keyPath) || !is_readable($keyPath)) {
            throw new \RuntimeException('Encryption key file not found or not readable.');
        }

        $raw = file_get_contents($keyPath);
        if ($raw === false || strlen($raw) < 32) {
            throw new \RuntimeException('Encryption key is invalid (must be at least 32 bytes).');
        }

        // Take exactly 32 bytes (256 bits)
        $this->key = substr($raw, 0, 32);
    }

    /**
     * Encrypt a PHP array into a storable base64 string.
     *
     * Format: base64( IV[12] + TAG[16] + CIPHERTEXT[n] )
     *
     * @param  array  $data
     * @return string Base64-encoded ciphertext.
     * @throws \RuntimeException On encryption failure.
     */
    public function encrypt(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to JSON-encode data: ' . json_last_error_msg());
        }

        $iv  = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $json,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt a base64-encoded string back into a PHP array.
     *
     * @param  string $encoded Base64-encoded string produced by encrypt().
     * @return array
     * @throws \RuntimeException On decryption failure or data corruption.
     */
    public function decrypt(string $encoded): array
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            throw new \RuntimeException('Failed to base64-decode encrypted data.');
        }

        $minLength = self::IV_LENGTH + self::TAG_LENGTH + 1;
        if (strlen($raw) < $minLength) {
            throw new \RuntimeException('Encrypted data is too short — possibly corrupted.');
        }

        $iv         = substr($raw, 0, self::IV_LENGTH);
        $tag        = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $json = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($json === false) {
            throw new \RuntimeException('Decryption failed — wrong key or corrupted data.');
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Decrypted data is not valid JSON array.');
        }

        return $data;
    }

    /**
     * Re-encrypt all .json.enc files with a new key.
     *
     * @param string $newKeyPath Path to the new encryption key file.
     * @param string $dataDir    Path to the data directory.
     * @param string $configDir  Path to the config directory.
     */
    public function rotateKey(string $newKeyPath, string $dataDir, string $configDir): void
    {
        $newEnc = new self($newKeyPath);

        // Collect all .json.enc files from data/ and config/
        $files = array_merge(
            glob($dataDir . '/*.json.enc') ?: [],
            glob($dataDir . '/pages/*.json.enc') ?: [],
            glob($configDir . '/*.json.enc') ?: []
        );

        foreach ($files as $file) {
            $encoded = file_get_contents($file);
            if ($encoded === false) {
                continue;
            }

            try {
                $data = $this->decrypt($encoded);
                $reEncrypted = $newEnc->encrypt($data);
                file_put_contents($file, $reEncrypted, LOCK_EX);
            } catch (\RuntimeException $e) {
                throw new \RuntimeException("Key rotation failed on {$file}: " . $e->getMessage());
            }
        }
    }

    /**
     * Generate a new random encryption key and save it.
     *
     * @param  string $path Where to save the key.
     * @return void
     */
    public static function generateKey( string $path ): void
    {
        $key = random_bytes( 32 );
        $dir = dirname( $path );

        if ( !is_dir( $dir ) ) {
            mkdir( $dir, 0700, true );
        }

        file_put_contents( $path, $key, LOCK_EX );
        chmod( $path, 0600 );
    }

    // ─── RSA Identity Keys ──────────────────────────────────────

    /**
     * Generate an RSA-2048 key pair for admin identity verification.
     *
     * @return array{private_key: string, public_key: string, fingerprint: string}
     * @throws \RuntimeException If key generation fails.
     */
    public static function generateRsaKeyPair(): array
    {
        $keyPair = openssl_pkey_new( [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ] );

        if ( $keyPair === false ) {
            throw new \RuntimeException( 'RSA key pair generation failed: ' . openssl_error_string() );
        }

        $privateKeyPem = '';
        if ( !openssl_pkey_export( $keyPair, $privateKeyPem ) ) {
            throw new \RuntimeException( 'RSA private key export failed: ' . openssl_error_string() );
        }

        $details      = openssl_pkey_get_details( $keyPair );
        $publicKeyPem = $details['key'];
        $fingerprint  = 'sha256:' . hash( 'sha256', $publicKeyPem );

        return [
            'private_key' => $privateKeyPem,
            'public_key'  => $publicKeyPem,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * Format the AES encryption key as a downloadable file with metadata.
     *
     * @param string $rawKey  Raw 32-byte encryption key.
     * @param string $siteUrl The site URL.
     * @param string $level   Encryption level (basic, medium, professional).
     * @return string Formatted key file content.
     */
    public static function formatEncryptionKeyFile( string $rawKey, string $siteUrl, string $level ): string
    {
        $now = gmdate( 'c' );

        return "-----BEGIN KLYTOS ENCRYPTION KEY-----\n"
            . "Generado: {$now}\n"
            . "Sitio: {$siteUrl}\n"
            . "Nivel: {$level}\n"
            . "---\n"
            . base64_encode( $rawKey ) . "\n"
            . "-----END KLYTOS ENCRYPTION KEY-----\n";
    }

    /**
     * Format the RSA identity private key as a downloadable file with metadata.
     *
     * @param string $privateKeyPem RSA private key in PEM format.
     * @param string $siteUrl       The site URL.
     * @param string $username      Admin username.
     * @param string $fingerprint   Public key fingerprint (sha256:...).
     * @return string Formatted identity file content.
     */
    public static function formatIdentityKeyFile(
        string $privateKeyPem,
        string $siteUrl,
        string $username,
        string $fingerprint
    ): string {
        $now = gmdate( 'c' );

        return "-----BEGIN KLYTOS IDENTITY KEY-----\n"
            . "Generado: {$now}\n"
            . "Sitio: {$siteUrl}\n"
            . "Usuario: {$username}\n"
            . "Fingerprint: {$fingerprint}\n"
            . "---\n"
            . $privateKeyPem
            . "-----END KLYTOS IDENTITY KEY-----\n";
    }

    /**
     * Parse a formatted encryption key file and extract the raw AES key.
     *
     * @param string $content File content from klytos-encryption.key.
     * @return string Raw 32-byte encryption key.
     * @throws \RuntimeException If the file format is invalid.
     */
    public static function parseEncryptionKeyFile( string $content ): string
    {
        if ( !str_contains( $content, '-----BEGIN KLYTOS ENCRYPTION KEY-----' ) ) {
            throw new \RuntimeException( 'Invalid encryption key file format.' );
        }

        // Extract the base64-encoded key between "---" and the END marker.
        $parts = explode( "---\n", $content );
        if ( count( $parts ) < 2 ) {
            throw new \RuntimeException( 'Cannot parse encryption key file.' );
        }

        // The key is in the last section, before the END marker.
        $keySection = $parts[ count( $parts ) - 1 ];
        $keySection = str_replace( "-----END KLYTOS ENCRYPTION KEY-----\n", '', $keySection );
        $keySection = trim( $keySection );

        $rawKey = base64_decode( $keySection, true );
        if ( $rawKey === false || strlen( $rawKey ) < 32 ) {
            throw new \RuntimeException( 'Invalid encryption key: must be at least 32 bytes.' );
        }

        return $rawKey;
    }

    /**
     * Parse a formatted identity key file and extract the RSA private key PEM.
     *
     * @param string $content File content from klytos-identity.pem.
     * @return string RSA private key in PEM format.
     * @throws \RuntimeException If the file format is invalid.
     */
    public static function parseIdentityKeyFile( string $content ): string
    {
        if ( !str_contains( $content, '-----BEGIN KLYTOS IDENTITY KEY-----' ) ) {
            throw new \RuntimeException( 'Invalid identity key file format.' );
        }

        // Extract the RSA private key PEM block.
        $beginMarker = '-----BEGIN RSA PRIVATE KEY-----';
        $endMarker   = '-----END RSA PRIVATE KEY-----';

        // Also support PKCS#8 format.
        if ( !str_contains( $content, $beginMarker ) ) {
            $beginMarker = '-----BEGIN PRIVATE KEY-----';
            $endMarker   = '-----END PRIVATE KEY-----';
        }

        $start = strpos( $content, $beginMarker );
        $end   = strpos( $content, $endMarker );

        if ( $start === false || $end === false ) {
            throw new \RuntimeException( 'Cannot find RSA private key in identity file.' );
        }

        return substr( $content, $start, $end - $start + strlen( $endMarker ) ) . "\n";
    }

    /**
     * Verify that an RSA private key matches a public key using challenge-response.
     *
     * Signs 32 random bytes with the private key and verifies with the public key.
     *
     * @param string $publicKeyPem  RSA public key in PEM format.
     * @param string $privateKeyPem RSA private key in PEM format.
     * @return bool True if the keys match.
     */
    public static function verifyIdentityChallenge( string $publicKeyPem, string $privateKeyPem ): bool
    {
        $privateKey = openssl_pkey_get_private( $privateKeyPem );
        if ( $privateKey === false ) {
            return false;
        }

        $challenge = random_bytes( 32 );
        $signature = '';

        if ( !openssl_sign( $challenge, $signature, $privateKey, OPENSSL_ALGO_SHA256 ) ) {
            return false;
        }

        $publicKey = openssl_pkey_get_public( $publicKeyPem );
        if ( $publicKey === false ) {
            return false;
        }

        return openssl_verify( $challenge, $signature, $publicKey, OPENSSL_ALGO_SHA256 ) === 1;
    }
}
