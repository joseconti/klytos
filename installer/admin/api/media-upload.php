<?php
/**
 * Klytos Admin API — Media Upload
 *
 * Handles file uploads from the Gutenberg editor.
 * Returns media object in the format Gutenberg expects.
 *
 * @copyright 2024-2026 José Conti. All rights reserved.
 * @license   Elastic License 2.0 (ELv2)
 */

require dirname( __DIR__ ) . '/bootstrap.php';

header( 'Content-Type: application/json; charset=utf-8' );

// Only POST allowed.
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    echo json_encode( [ 'error' => 'Method not allowed' ] );
    exit;
}

// CSRF validation.
$auth = $app->getAuth();
if ( ! $auth->validateCsrf( $_POST['csrf'] ?? '' ) ) {
    http_response_code( 403 );
    echo json_encode( [ 'error' => 'Invalid CSRF token' ] );
    exit;
}

// Check file.
if ( ! isset( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
    http_response_code( 400 );
    echo json_encode( [ 'error' => 'No file uploaded or upload error' ] );
    exit;
}

$file = $_FILES['file'];
$maxSize = 10 * 1024 * 1024; // 10 MB

if ( $file['size'] > $maxSize ) {
    http_response_code( 413 );
    echo json_encode( [ 'error' => 'File too large. Maximum: 10MB' ] );
    exit;
}

// Allowed MIME types.
$allowedTypes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    'video/mp4', 'video/webm',
    'audio/mpeg', 'audio/ogg', 'audio/wav',
    'application/pdf',
    'text/css', 'text/javascript',
    'font/woff', 'font/woff2', 'font/ttf', 'font/otf',
];

$finfo = new finfo( FILEINFO_MIME_TYPE );
$mimeType = $finfo->file( $file['tmp_name'] );

if ( ! in_array( $mimeType, $allowedTypes, true ) ) {
    http_response_code( 415 );
    echo json_encode( [ 'error' => 'File type not allowed: ' . $mimeType ] );
    exit;
}

// Sanitize filename.
$originalName = basename( $file['name'] );
$extension    = strtolower( pathinfo( $originalName, PATHINFO_EXTENSION ) );
$safeName     = preg_replace( '/[^a-zA-Z0-9._-]/', '-', pathinfo( $originalName, PATHINFO_FILENAME ) );
$safeName     = substr( $safeName, 0, 100 );

// Organize by date: assets/images/2026/03/filename.ext
$dateDir  = date( 'Y/m' );
$subDir   = 'images/' . $dateDir;
$filename = $safeName . '-' . bin2hex( random_bytes( 4 ) ) . '.' . $extension;

// Use AssetManager if available, otherwise manual upload.
$assetManager = $app->getAssetManager();
$publicDir    = dirname( dirname( __DIR__ ) ) . '/public/';

// Ensure directory exists.
$targetDir = $publicDir . 'assets/' . $subDir;
if ( ! is_dir( $targetDir ) ) {
    mkdir( $targetDir, 0755, true );
}

$targetPath = $targetDir . '/' . $filename;

if ( ! move_uploaded_file( $file['tmp_name'], $targetPath ) ) {
    http_response_code( 500 );
    echo json_encode( [ 'error' => 'Failed to save file' ] );
    exit;
}

// Build URL.
$siteUrl  = rtrim( $app->getSiteConfig()->get( 'site_url', '' ), '/' );
$assetUrl = $siteUrl . '/assets/' . $subDir . '/' . $filename;

// Get image dimensions if it's an image.
$sizes = [];
if ( strpos( $mimeType, 'image/' ) === 0 && function_exists( 'getimagesize' ) ) {
    $imageInfo = @getimagesize( $targetPath );
    if ( $imageInfo ) {
        $sizes['full'] = [
            'url'    => $assetUrl,
            'width'  => $imageInfo[0],
            'height' => $imageInfo[1],
        ];
    }
}

// Return media object in Gutenberg format.
echo json_encode( [
    'id'    => crc32( $filename ),
    'url'   => $assetUrl,
    'alt'   => $safeName,
    'title' => $safeName,
    'mime'  => $mimeType,
    'type'  => explode( '/', $mimeType )[0],
    'sizes' => (object) $sizes,
] );
