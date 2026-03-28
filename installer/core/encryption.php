<?php
/**
 * Klytos — Encryption Engine
 * AES-256-GCM encryption for all stored data.
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
    public static function generateKey(string $path): void
    {
        $key = random_bytes(32);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($path, $key, LOCK_EX);
        chmod($path, 0600);
    }
}
