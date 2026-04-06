<?php

/**
 * Klytos — Asset Manager
 * Manages uploaded files (images, CSS, JS, fonts, etc.)
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

class AssetManager
{
    private string $publicDir;
    private string $assetsDir;
    private int $maxFileSize;
    private StorageInterface $storage;

    /**
     * @param StorageInterface $storage     Storage backend for asset metadata.
     * @param string           $webRootDir  Absolute path to the web root (parent of admin dir).
     * @param int              $maxFileSize Maximum upload size in bytes (default 10MB).
     */
    public function __construct( StorageInterface $storage, string $webRootDir, int $maxFileSize = 10485760 )
    {
        $this->storage     = $storage;
        $this->publicDir   = rtrim( $webRootDir, '/' );
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

        // Fire before_upload action (after validation, before file write).
        klytos_do_action('asset.before_upload', $filename, $directory);

        // Auto-organize images by date: images/2026/04/filename.jpg
        // This keeps the uploads directory clean and browsable over time.
        if ($directory === 'images') {
            $directory = 'images/' . klytos_gmdate( 'Y' ) . '/' . klytos_gmdate( 'm' );
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

        $result = [
            'filename'      => $filename,
            'directory'     => $directory,
            'path'          => $relativePath,
            'size'          => strlen($data),
            'size_human'    => Helpers::formatBytes(strlen($data)),
            'mime_type'     => $this->getMimeType($targetPath),
            'uploaded_at'   => Helpers::now(),
        ];

        // Auto-register asset metadata in storage.
        // Wrapped in try/catch: physical file is critical, metadata can be rebuilt.
        try {
            $assetId = Helpers::generateShortId();

            $assetRecord = [
                'id'          => $assetId,
                'filename'    => $filename,
                'path'        => $relativePath,
                'mime_type'   => $result['mime_type'],
                'size'        => $result['size'],
                'size_human'  => $result['size_human'],
                'alt_text'    => '',
                'title'       => pathinfo( $filename, PATHINFO_FILENAME ),
                'description' => '',
                'categories'  => [],
                'uploaded_by' => ( klytos_current_user()['id'] ?? 'system' ),
                'uploaded_at' => $result['uploaded_at'],
                'updated_at'  => $result['uploaded_at'],
            ];

            $this->storage->write( 'assets', $assetId, $assetRecord );
            $result['asset_id'] = $assetId;
        } catch ( \Throwable $e ) {
            // Non-fatal: log the failure but return the upload result anyway.
            klytos_do_action( 'asset.metadata_error', $e->getMessage(), $relativePath );
        }

        klytos_do_action('asset.after_upload', $result, $filename);

        return $result;
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

        // Look up the asset record before deleting the physical file.
        $assetRecord = $this->findAssetByPath( $relativePath );

        klytos_do_action( 'asset.before_delete', $path, $assetRecord );

        $deleted = file_exists($path) && unlink($path);

        if ($deleted) {
            // Clean up metadata and usage records.
            if ( $assetRecord ) {
                $this->storage->delete( 'assets', $assetRecord['id'] );
                $this->deleteUsageForAsset( $assetRecord['id'] );
            }
            klytos_do_action( 'asset.after_delete', $path, $assetRecord );
        }

        return $deleted;
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
                    'size_human' => Helpers::formatBytes($file->getSize()),
                    'mime_type' => $this->getMimeType($file->getPathname()),
                    'modified'  => klytos_timestamp_to_datetime( $file->getMTime() ),
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
     * Get the storage backend.
     *
     * @return StorageInterface
     */
    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    // ──────────────────────────────────────────────────────────
    //  Sync & Rebuild
    // ─── Image Editing ─────────────────────────────────────────

    /**
     * Edit an image (crop, rotate, flip, resize) using GD.
     *
     * @param  string $path       Asset path relative to web root.
     * @param  array  $operations Operations to apply:
     *   - crop:   {x: int, y: int, width: int, height: int}
     *   - rotate: int (degrees: 90, 180, 270)
     *   - flip:   'horizontal' | 'vertical'
     *   - resize: {width: int, height?: int}
     * @param  string $saveAs     Optional new filename. If empty, overwrites original.
     * @return array  Updated asset metadata.
     * @throws \RuntimeException If GD is not available or file not found.
     */
    public function editImage( string $path, array $operations, string $saveAs = '' ): array
    {
        if ( !extension_loaded( 'gd' ) ) {
            throw new \RuntimeException( 'GD extension is required for image editing.' );
        }

        $fullPath = $this->assetsDir . '/' . ltrim( $path, '/' );
        if ( !str_starts_with( $fullPath, $this->assetsDir ) ) {
            $fullPath = rtrim( dirname( $this->assetsDir, 2 ), '/' ) . '/' . ltrim( $path, '/' );
        }

        if ( !file_exists( $fullPath ) ) {
            throw new \RuntimeException( 'Image file not found: ' . $path );
        }

        klytos_do_action( 'asset.before_edit', $path, $operations );

        $info = getimagesize( $fullPath );
        if ( $info === false ) {
            throw new \RuntimeException( 'Not a valid image file.' );
        }

        $mime  = $info['mime'] ?? '';
        $image = match ( $mime ) {
            'image/jpeg' => imagecreatefromjpeg( $fullPath ),
            'image/png'  => imagecreatefrompng( $fullPath ),
            'image/gif'  => imagecreatefromgif( $fullPath ),
            'image/webp' => function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $fullPath ) : false,
            default      => false,
        };

        if ( $image === false ) {
            throw new \RuntimeException( 'Unsupported image format: ' . $mime );
        }

        // Apply operations in order.
        if ( isset( $operations['crop'] ) ) {
            $c = $operations['crop'];
            $cropped = imagecrop( $image, [
                'x'      => (int) ( $c['x'] ?? 0 ),
                'y'      => (int) ( $c['y'] ?? 0 ),
                'width'  => (int) ( $c['width'] ?? imagesx( $image ) ),
                'height' => (int) ( $c['height'] ?? imagesy( $image ) ),
            ]);
            if ( $cropped !== false ) {
                imagedestroy( $image );
                $image = $cropped;
            }
        }

        if ( isset( $operations['rotate'] ) ) {
            $degrees = (int) $operations['rotate'];
            // GD rotates counter-clockwise, so negate for clockwise.
            $rotated = imagerotate( $image, -$degrees, 0 );
            if ( $rotated !== false ) {
                imagedestroy( $image );
                $image = $rotated;
            }
        }

        if ( isset( $operations['flip'] ) ) {
            $mode = match ( $operations['flip'] ) {
                'horizontal' => IMG_FLIP_HORIZONTAL,
                'vertical'   => IMG_FLIP_VERTICAL,
                default      => IMG_FLIP_HORIZONTAL,
            };
            imageflip( $image, $mode );
        }

        if ( isset( $operations['resize'] ) ) {
            $newW = (int) ( $operations['resize']['width'] ?? 0 );
            $newH = (int) ( $operations['resize']['height'] ?? 0 );
            if ( $newW > 0 ) {
                if ( $newH <= 0 ) {
                    // Maintain aspect ratio.
                    $newH = (int) round( imagesy( $image ) * ( $newW / imagesx( $image ) ) );
                }
                $resized = imagescale( $image, $newW, $newH );
                if ( $resized !== false ) {
                    imagedestroy( $image );
                    $image = $resized;
                }
            }
        }

        // Determine output path.
        $outputPath = $fullPath;
        if ( $saveAs !== '' ) {
            $outputPath = dirname( $fullPath ) . '/' . basename( $saveAs );
        }

        // Save based on mime type.
        match ( $mime ) {
            'image/jpeg' => imagejpeg( $image, $outputPath, 90 ),
            'image/png'  => imagepng( $image, $outputPath, 6 ),
            'image/gif'  => imagegif( $image, $outputPath ),
            'image/webp' => imagewebp( $image, $outputPath, 85 ),
            default      => imagejpeg( $image, $outputPath, 90 ),
        };

        imagedestroy( $image );

        // Update asset metadata.
        $relativePath = str_replace( rtrim( dirname( $this->assetsDir, 2 ), '/' ) . '/', '', $outputPath );
        $result = [
            'path'      => $relativePath,
            'filename'  => basename( $outputPath ),
            'size'      => filesize( $outputPath ),
            'size_human' => Helpers::humanFileSize( filesize( $outputPath ) ),
            'mime_type' => $mime,
            'width'     => imagesx( $image ?? imagecreatefromstring( file_get_contents( $outputPath ) ) ),
            'height'    => imagesy( $image ?? imagecreatefromstring( file_get_contents( $outputPath ) ) ),
        ];

        // Re-read dimensions from file since $image is destroyed.
        $newInfo = getimagesize( $outputPath );
        if ( $newInfo !== false ) {
            $result['width']  = $newInfo[0];
            $result['height'] = $newInfo[1];
        }

        klytos_do_action( 'asset.after_edit', $path, $relativePath, $operations );

        return $result;
    }

    // ──────────────────────────────────────────────────────────

    /**
     * Scan the assets directory and create records for files
     * that are not yet registered in the 'assets' collection.
     *
     * @return int Number of newly registered assets.
     */
    public function syncExistingAssets(): int
    {
        $allFiles      = $this->list();
        $allRegistered = $this->storage->list( 'assets' );

        // Build a set of already-registered paths.
        $registeredPaths = [];
        foreach ( $allRegistered as $record ) {
            $registeredPaths[$record['path'] ?? ''] = true;
        }

        $synced = 0;

        foreach ( $allFiles as $file ) {
            if ( !isset( $registeredPaths[$file['path']] ) ) {
                $assetId = Helpers::generateShortId();

                $record = [
                    'id'          => $assetId,
                    'filename'    => $file['filename'],
                    'path'        => $file['path'],
                    'mime_type'   => $file['mime_type'],
                    'size'        => $file['size'],
                    'size_human'  => $file['size_human'],
                    'alt_text'    => '',
                    'title'       => pathinfo( $file['filename'], PATHINFO_FILENAME ),
                    'description' => '',
                    'categories'  => [],
                    'uploaded_by' => 'system',
                    'uploaded_at' => $file['modified'],
                    'updated_at'  => $file['modified'],
                ];

                $this->storage->write( 'assets', $assetId, $record );
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * Rebuild the entire usage index by scanning all content.
     *
     * WARNING: deletes all existing usage records and recreates them.
     *
     * @return array Stats: ['scanned_pages' => int, 'usages_found' => int]
     */
    public function rebuildUsageIndex(): array
    {
        // 1. Delete all existing usage records.
        $allUsage = $this->storage->list( 'asset-usage' );
        foreach ( $allUsage as $usage ) {
            $this->storage->delete( 'asset-usage', $usage['id'] );
        }

        $stats = ['scanned_pages' => 0, 'usages_found' => 0];

        // 2. Scan all pages — triggers the page.after_save hook
        //    which populates asset-usage records.
        $pages = $this->storage->list( 'pages' );
        foreach ( $pages as $page ) {
            $stats['scanned_pages']++;
            klytos_do_action( 'page.after_save', $page, 'rebuild' );
        }

        // 3. Scan theme configuration.
        try {
            $themeConfig = $this->storage->read( 'config', 'theme' );
            if ( !empty( $themeConfig ) ) {
                klytos_do_action( 'theme.after_save', $themeConfig );
            }
        } catch ( \Throwable $e ) {
            // Theme config may not exist yet — safe to ignore.
        }

        // 4. Count the created usage records.
        $stats['usages_found'] = $this->storage->count( 'asset-usage' );

        return $stats;
    }

    // ──────────────────────────────────────────────────────────
    //  Asset Categories
    // ──────────────────────────────────────────────────────────

    /**
     * Create an asset category.
     *
     * @param  string      $name        Category display name.
     * @param  string      $description Optional description.
     * @param  string|null $parent      Optional parent category slug.
     * @return array The created category record.
     * @throws \RuntimeException If category already exists.
     */
    public function createCategory( string $name, string $description = '', ?string $parent = null ): array
    {
        $slug = Helpers::sanitizeSlug( $name );
        $id   = $slug;

        if ( $this->storage->exists( 'asset-categories', $id ) ) {
            throw new \RuntimeException( "Asset category '{$slug}' already exists." );
        }

        klytos_do_action( 'asset_category.before_create', $name, $slug );

        $record = [
            'id'          => $id,
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
            'parent'      => $parent,
            'order'       => 0,
            'created_at'  => Helpers::now(),
        ];

        $this->storage->write( 'asset-categories', $id, $record );

        klytos_do_action( 'asset_category.after_create', $record );

        return $record;
    }

    /**
     * List all asset categories.
     *
     * @return array
     */
    public function listCategories(): array
    {
        return $this->storage->list( 'asset-categories' );
    }

    /**
     * Delete a category (does NOT delete images, only unlinks them).
     *
     * @param  string $categoryId Category slug/ID.
     * @return bool
     */
    public function deleteCategory( string $categoryId ): bool
    {
        if ( !$this->storage->exists( 'asset-categories', $categoryId ) ) {
            return false;
        }

        klytos_do_action( 'asset_category.before_delete', $categoryId );

        // Unlink the category from all assets that have it.
        $assets = $this->storage->list( 'assets' );
        foreach ( $assets as $asset ) {
            if ( isset( $asset['categories'] ) && in_array( $categoryId, $asset['categories'], true ) ) {
                $asset['categories'] = array_values(
                    array_filter( $asset['categories'], fn( $c ) => $c !== $categoryId )
                );
                $this->storage->write( 'assets', $asset['id'], $asset );
            }
        }

        $deleted = $this->storage->delete( 'asset-categories', $categoryId );

        if ( $deleted ) {
            klytos_do_action( 'asset_category.after_delete', $categoryId );
        }

        return $deleted;
    }

    /**
     * Update a category's metadata.
     *
     * @param  string $categoryId Category slug/ID.
     * @param  array  $data       Fields to update (name, description, parent, order).
     * @return array  The updated record.
     * @throws \RuntimeException If category does not exist.
     */
    public function updateCategory( string $categoryId, array $data ): array
    {
        if ( !$this->storage->exists( 'asset-categories', $categoryId ) ) {
            throw new \RuntimeException( "Asset category '{$categoryId}' not found." );
        }

        $record = $this->storage->read( 'asset-categories', $categoryId );

        $allowed = ['name', 'description', 'parent', 'order'];
        foreach ( $allowed as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                $record[$field] = $data[$field];
            }
        }

        $this->storage->write( 'asset-categories', $categoryId, $record );

        return $record;
    }

    /**
     * Assign categories to an asset.
     *
     * @param string $assetId     Asset ID.
     * @param array  $categoryIds Array of category slugs.
     */
    public function setAssetCategories( string $assetId, array $categoryIds ): void
    {
        $record = $this->storage->read( 'assets', $assetId );
        $record['categories'] = $categoryIds;
        $record['updated_at'] = Helpers::now();
        $this->storage->write( 'assets', $assetId, $record );
    }

    /**
     * Get all assets belonging to a category.
     *
     * @param  string $categoryId Category slug.
     * @return array
     */
    public function getAssetsByCategory( string $categoryId ): array
    {
        $all    = $this->storage->list( 'assets' );
        $result = [];

        foreach ( $all as $asset ) {
            if ( isset( $asset['categories'] ) && in_array( $categoryId, $asset['categories'], true ) ) {
                $result[] = $asset;
            }
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────
    //  Asset Usage Tracking
    // ──────────────────────────────────────────────────────────

    /**
     * Register that an asset is used in a specific context.
     *
     * @param string $assetId      Asset ID.
     * @param string $contextType  Context type (page, header, footer, theme, favicon, og_image, etc.).
     * @param string $contextId    Context identifier (page slug, 'global', widget ID, etc.).
     * @param string $contextLabel Human-readable label for the context.
     * @param string $field        Specific field where the asset is used.
     */
    public function trackUsage( string $assetId, string $contextType, string $contextId, string $contextLabel = '', string $field = 'content_html' ): void
    {
        $usageId = "{$assetId}--{$contextType}--{$contextId}";

        // If already exists, just update the label if it changed.
        if ( $this->storage->exists( 'asset-usage', $usageId ) ) {
            $existing = $this->storage->read( 'asset-usage', $usageId );
            if ( $existing['context_label'] !== $contextLabel ) {
                $existing['context_label'] = $contextLabel;
                $this->storage->write( 'asset-usage', $usageId, $existing );
            }
            return;
        }

        $record = [
            'id'            => $usageId,
            'asset_id'      => $assetId,
            'context_type'  => $contextType,
            'context_id'    => $contextId,
            'context_label' => $contextLabel,
            'field'         => $field,
            'added_at'      => Helpers::now(),
        ];

        $this->storage->write( 'asset-usage', $usageId, $record );
    }

    /**
     * Remove a usage record for an asset in a specific context.
     *
     * @param string $assetId     Asset ID.
     * @param string $contextType Context type.
     * @param string $contextId   Context identifier.
     */
    public function removeUsage( string $assetId, string $contextType, string $contextId ): void
    {
        $usageId = "{$assetId}--{$contextType}--{$contextId}";
        $this->storage->delete( 'asset-usage', $usageId );
    }

    /**
     * Get all usage records for an asset.
     *
     * @param  string $assetId Asset ID.
     * @return array  List of usage records.
     */
    public function getUsage( string $assetId ): array
    {
        $all    = $this->storage->list( 'asset-usage' );
        $result = [];

        foreach ( $all as $record ) {
            if ( ( $record['asset_id'] ?? '' ) === $assetId ) {
                $result[] = $record;
            }
        }

        return $result;
    }

    /**
     * Get all assets used in a specific context.
     *
     * @param  string $contextType Context type.
     * @param  string $contextId   Context identifier.
     * @return array
     */
    public function getAssetsForContext( string $contextType, string $contextId ): array
    {
        $all    = $this->storage->list( 'asset-usage' );
        $result = [];

        foreach ( $all as $record ) {
            if ( ( $record['context_type'] ?? '' ) === $contextType
                && ( $record['context_id'] ?? '' ) === $contextId ) {
                $result[] = $record;
            }
        }

        return $result;
    }

    /**
     * Check if an asset is used anywhere.
     *
     * @param  string $assetId Asset ID.
     * @return bool
     */
    public function isAssetInUse( string $assetId ): bool
    {
        return count( $this->getUsage( $assetId ) ) > 0;
    }

    /**
     * Get all assets that are not used anywhere.
     *
     * @return array
     */
    public function getUnusedAssets(): array
    {
        $allAssets = $this->storage->list( 'assets' );
        $allUsage  = $this->storage->list( 'asset-usage' );

        // Build a set of asset IDs that are in use.
        $usedIds = [];
        foreach ( $allUsage as $usage ) {
            $usedIds[$usage['asset_id'] ?? ''] = true;
        }

        $unused = [];
        foreach ( $allAssets as $asset ) {
            if ( !isset( $usedIds[$asset['id']] ) ) {
                $unused[] = $asset;
            }
        }

        return $unused;
    }

    /**
     * Delete all usage records for a given asset.
     *
     * @param  string $assetId Asset ID.
     * @return int    Number of records deleted.
     */
    public function deleteUsageForAsset( string $assetId ): int
    {
        $usages  = $this->getUsage( $assetId );
        $deleted = 0;

        foreach ( $usages as $usage ) {
            if ( $this->storage->delete( 'asset-usage', $usage['id'] ) ) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Delete all usage records for a specific context (e.g. when a page is deleted).
     *
     * @param  string $contextType Context type.
     * @param  string $contextId   Context identifier.
     * @return int    Number of records deleted.
     */
    public function deleteUsageForContext( string $contextType, string $contextId ): int
    {
        $usages  = $this->getAssetsForContext( $contextType, $contextId );
        $deleted = 0;

        foreach ( $usages as $usage ) {
            if ( $this->storage->delete( 'asset-usage', $usage['id'] ) ) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Find an asset record by its relative path.
     *
     * @param  string     $relativePath Relative path (e.g. 'assets/images/2026/04/hero.jpg').
     * @return array|null The asset record, or null if not found.
     */
    public function findAssetByPath( string $relativePath ): ?array
    {
        $all = $this->storage->list( 'assets' );

        foreach ( $all as $asset ) {
            if ( ( $asset['path'] ?? '' ) === $relativePath ) {
                return $asset;
            }
        }

        return null;
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
