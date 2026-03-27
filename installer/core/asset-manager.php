<?php
/**
 * Klytos — Asset Manager
 * Manages uploaded files (images, CSS, JS, fonts, etc.)
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

class AssetManager
{
    private string $publicDir;
    private string $assetsDir;
    private int $maxFileSize;

    /**
     * @param string $publicDir  Absolute path to public/ directory.
     * @param int    $maxFileSize Maximum upload size in bytes (default 10MB).
     */
    public function __construct(string $publicDir, int $maxFileSize = 10485760)
    {
        $this->publicDir   = rtrim($publicDir, '/');
        $this->assetsDir   = $this->publicDir . '/assets';
        $this->maxFileSize = $maxFileSize;
    }

    /**
     * Upload a file from base64-encoded data.
     *
     * @param  string $filename   Filename with extension (e.g. 'logo.png').
     * @param  string $dataBase64 Base64-encoded file content.
     * @param  string $directory  Subdirectory within assets/ (default 'images').
     * @return array  Upload result with path and URL info.
     * @throws \RuntimeException On validation failure.
     */
    public function upload(string $filename, string $dataBase64, string $directory = 'images'): array
    {
        // Validate filename.
        $filename = $this->sanitizeFilename($filename);
        if (empty($filename)) {
            throw new \RuntimeException('Invalid filename.');
        }

        if (!Helpers::isAllowedUpload($filename)) {
            throw new \RuntimeException('File type not allowed: ' . Helpers::getExtension($filename));
        }

        // Auto-organize images by date: images/2026/04/filename.jpg
        // This keeps the uploads directory clean and browsable over time.
        if ($directory === 'images') {
            $directory = 'images/' . date('Y') . '/' . date('m');
        }

        // Decode base64
        $data = base64_decode($dataBase64, true);
        if ($data === false) {
            throw new \RuntimeException('Invalid base64 data.');
        }

        // Check size
        if (strlen($data) > $this->maxFileSize) {
            throw new \RuntimeException(
                'File too large. Maximum: ' . Helpers::formatBytes($this->maxFileSize)
            );
        }

        // Ensure directory exists
        $directory = $this->sanitizeDirectory($directory);
        $targetDir = $this->assetsDir . '/' . $directory;
        Helpers::ensureWritableDir($targetDir);

        // Handle duplicate filenames
        $targetPath = $targetDir . '/' . $filename;
        if (file_exists($targetPath)) {
            $filename   = $this->makeUnique($filename, $targetDir);
            $targetPath = $targetDir . '/' . $filename;
        }

        // Write file
        $result = file_put_contents($targetPath, $data, LOCK_EX);
        if ($result === false) {
            throw new \RuntimeException('Failed to write file.');
        }

        $relativePath = "assets/{$directory}/{$filename}";

        return [
            'filename'      => $filename,
            'directory'     => $directory,
            'path'          => $relativePath,
            'size'          => strlen($data),
            'size_human'    => Helpers::formatBytes(strlen($data)),
            'mime_type'     => $this->getMimeType($targetPath),
            'uploaded_at'   => Helpers::now(),
        ];
    }

    /**
     * Upload a file directly from binary data (for AI-generated images).
     *
     * @param  string $filename  Filename with extension.
     * @param  string $data      Raw binary data.
     * @param  string $directory Subdirectory within assets/.
     * @return array  Upload result.
     */
    public function uploadRaw(string $filename, string $data, string $directory = 'images'): array
    {
        return $this->upload($filename, base64_encode($data), $directory);
    }

    /**
     * Delete an asset file.
     *
     * @param  string $relativePath Relative path from public/ (e.g. 'assets/images/logo.png').
     * @return bool
     */
    public function delete(string $relativePath): bool
    {
        $path = $this->publicDir . '/' . ltrim($relativePath, '/');

        // Security: ensure path is within assets/
        $realPath = realpath($path);
        if ($realPath === false || !str_starts_with($realPath, realpath($this->assetsDir))) {
            return false;
        }

        return file_exists($path) && unlink($path);
    }

    /**
     * List all assets, optionally filtered by directory.
     *
     * @param  string $directory Subdirectory filter (empty = all).
     * @return array
     */
    public function list(string $directory = ''): array
    {
        $searchDir = $directory
            ? $this->assetsDir . '/' . $this->sanitizeDirectory($directory)
            : $this->assetsDir;

        if (!is_dir($searchDir)) {
            return [];
        }

        $assets = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($searchDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($this->publicDir . '/', '', $file->getPathname());
                $assets[] = [
                    'filename'  => $file->getFilename(),
                    'path'      => $relativePath,
                    'size'      => $file->getSize(),
                    'size_human'=> Helpers::formatBytes($file->getSize()),
                    'mime_type' => $this->getMimeType($file->getPathname()),
                    'modified'  => date('c', $file->getMTime()),
                ];
            }
        }

        // Sort by modification date, newest first
        usort($assets, fn($a, $b) => strcmp($b['modified'], $a['modified']));

        return $assets;
    }

    /**
     * Get the full filesystem path to the assets directory.
     *
     * @return string
     */
    public function getAssetsDir(): string
    {
        return $this->assetsDir;
    }

    /**
     * Sanitize a filename: remove path separators, special chars.
     */
    private function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        return trim($filename, '_.');
    }

    /**
     * Sanitize a directory name.
     */
    private function sanitizeDirectory(string $dir): string
    {
        $dir = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $dir);
        $dir = preg_replace('/\.\./', '', $dir); // prevent traversal
        return trim($dir, '/');
    }

    /**
     * Make a filename unique by appending a counter.
     */
    private function makeUnique(string $filename, string $dir): string
    {
        $ext  = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $i    = 1;

        do {
            $candidate = "{$name}-{$i}.{$ext}";
            $i++;
        } while (file_exists($dir . '/' . $candidate));

        return $candidate;
    }

    /**
     * Get MIME type of a file.
     */
    private function getMimeType(string $path): string
    {
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
            return $mime ?: 'application/octet-stream';
        }

        // Fallback based on extension
        $map = [
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'webp'  => 'image/webp',
            'ico'   => 'image/x-icon',
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'pdf'   => 'application/pdf',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'mp4'   => 'video/mp4',
            'webm'  => 'video/webm',
            'mp3'   => 'audio/mpeg',
        ];

        $ext = Helpers::getExtension($path);
        return $map[$ext] ?? 'application/octet-stream';
    }
}
