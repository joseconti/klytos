<?php
/**
 * Klytos — Storage Layer
 * CRUD operations over encrypted JSON files.
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

class Storage
{
    private Encryption $enc;
    private string $dataDir;

    /**
     * @param Encryption $enc     Encryption engine instance.
     * @param string     $dataDir Absolute path to the data directory.
     */
    public function __construct(Encryption $enc, string $dataDir)
    {
        $this->enc     = $enc;
        $this->dataDir = rtrim($dataDir, '/');
    }

    /**
     * Read and decrypt a JSON file.
     *
     * @param  string $file Relative path from base dir (e.g. 'site.json.enc' or 'pages/index.json.enc').
     * @return array
     * @throws \RuntimeException If the file does not exist or cannot be read.
     */
    public function read(string $file): array
    {
        $path = $this->resolvePath($file);

        if (!file_exists($path)) {
            throw new \RuntimeException("Storage file not found: {$file}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read storage file: {$file}");
        }

        return $this->enc->decrypt($content);
    }

    /**
     * Encrypt and write a JSON file.
     *
     * @param string $file Relative path from base dir.
     * @param array  $data Data to encrypt and store.
     */
    public function write(string $file, array $data): void
    {
        $path = $this->resolvePath($file);
        $dir  = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $encrypted = $this->enc->encrypt($data);
        $result    = file_put_contents($path, $encrypted, LOCK_EX);

        if ($result === false) {
            throw new \RuntimeException("Failed to write storage file: {$file}");
        }
    }

    /**
     * Check if a file exists.
     *
     * @param  string $file Relative path from base dir.
     * @return bool
     */
    public function exists(string $file): bool
    {
        return file_exists($this->resolvePath($file));
    }

    /**
     * Delete a file.
     *
     * @param  string $file Relative path from base dir.
     * @return bool  True if deleted, false if file didn't exist.
     */
    public function delete(string $file): bool
    {
        $path = $this->resolvePath($file);

        if (!file_exists($path)) {
            return false;
        }

        return unlink($path);
    }

    /**
     * List encrypted files in a subdirectory.
     *
     * @param  string $dir Relative subdirectory (e.g. 'pages').
     * @return array  List of filenames (without path).
     */
    public function listFiles(string $dir = ''): array
    {
        $fullPath = $this->dataDir . ($dir ? '/' . trim($dir, '/') : '');

        if (!is_dir($fullPath)) {
            return [];
        }

        $files = glob($fullPath . '/*.json.enc');
        if ($files === false) {
            return [];
        }

        return array_map('basename', $files);
    }

    /**
     * Read a file from an arbitrary base directory (for config files).
     *
     * @param  string $basePath Absolute directory path.
     * @param  string $file     Filename.
     * @return array
     */
    public function readFrom(string $basePath, string $file): array
    {
        $path = rtrim($basePath, '/') . '/' . $file;

        if (!file_exists($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read file: {$path}");
        }

        return $this->enc->decrypt($content);
    }

    /**
     * Write a file to an arbitrary base directory (for config files).
     *
     * @param string $basePath Absolute directory path.
     * @param string $file     Filename.
     * @param array  $data     Data to encrypt and store.
     */
    public function writeTo(string $basePath, string $file, array $data): void
    {
        $path = rtrim($basePath, '/') . '/' . $file;
        $dir  = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $encrypted = $this->enc->encrypt($data);
        $result    = file_put_contents($path, $encrypted, LOCK_EX);

        if ($result === false) {
            throw new \RuntimeException("Failed to write file: {$path}");
        }
    }

    /**
     * Get the encryption engine (for other components that need it).
     *
     * @return Encryption
     */
    public function getEncryption(): Encryption
    {
        return $this->enc;
    }

    /**
     * Get the data directory path.
     *
     * @return string
     */
    public function getDataDir(): string
    {
        return $this->dataDir;
    }

    /**
     * Resolve a relative file path to absolute.
     *
     * @param  string $file
     * @return string
     */
    private function resolvePath(string $file): string
    {
        // If it starts with config/ or an absolute path, treat differently
        $file = ltrim($file, '/');
        return $this->dataDir . '/' . $file;
    }
}
