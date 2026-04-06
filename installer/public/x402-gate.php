<?php

/**
 * Klytos x402 — Public Gate Script
 *
 * This file is copied to the web root during plugin activation.
 * It handles incoming requests from AI bots, checking x402 status
 * and either serving content or returning HTTP 402.
 *
 * Flow:
 * 1. Apache/Nginx detects bot User-Agent or x402 headers via .htaccess
 * 2. Request is rewritten to: x402-gate.php?slug={slug}&format={html|md}
 * 3. This script bootstraps Klytos, resolves the x402 gate, and handles the request.
 *
 * @package KlytosX402
 * @since   1.0.0
 */

declare( strict_types=1 );

// ─── Rate limiting (simple, before bootstrap) ──────────────────

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateFile = __DIR__ . '/../data/x402-rate-limits.json';
$maxPerMinute = 60;

if ( file_exists( $rateFile ) ) {
    $rateData = json_decode( file_get_contents( $rateFile ) ?: '{}', true ) ?: [];
} else {
    $rateData = [];
}

$ipHash = md5( $clientIp );
$now    = time();

// Clean old entries.
$rateData[$ipHash] = array_filter(
    $rateData[$ipHash] ?? [],
    fn( $ts ) => $ts > $now - 60
);

if ( count( $rateData[$ipHash] ) >= $maxPerMinute ) {
    http_response_code( 429 );
    header( 'Content-Type: application/json' );
    header( 'Retry-After: 60' );
    echo json_encode( ['error' => 'Rate limit exceeded. Max 60 requests/minute.'] );
    exit;
}

$rateData[$ipHash][] = $now;
@file_put_contents( $rateFile, json_encode( $rateData ), LOCK_EX );

// ─── Bootstrap Klytos ──────────────────────────────────────────

// Find the admin directory (one level up from public web root).
// The admin directory name is dynamic (security feature), so we detect it.
$webRoot = __DIR__;

// Look for the Klytos admin bootstrap.
$adminBootstrap = null;
$candidates     = glob( $webRoot . '/*/core/app.php' );

if ( !empty( $candidates ) ) {
    // Found the admin directory.
    $adminDir      = dirname( dirname( $candidates[0] ) );
    $adminBootstrap = $adminDir . '/core/bootstrap-minimal.php';

    if ( !file_exists( $adminBootstrap ) ) {
        // Fallback: use the index.php autoloader approach.
        $adminBootstrap = $adminDir . '/index.php';
    }
}

// Try alternative: check for a marker file left by the installer.
if ( $adminBootstrap === null || !file_exists( $adminBootstrap ) ) {
    // Check for .klytos-admin-path marker.
    $markerFile = $webRoot . '/.klytos-admin-path';
    if ( file_exists( $markerFile ) ) {
        $adminDir       = trim( file_get_contents( $markerFile ) );
        $adminBootstrap = $webRoot . '/' . $adminDir . '/core/bootstrap-minimal.php';
    }
}

if ( $adminBootstrap === null || !file_exists( $adminBootstrap ) ) {
    http_response_code( 500 );
    header( 'Content-Type: application/json' );
    echo json_encode( ['error' => 'x402 gate: Cannot locate Klytos admin directory.'] );
    exit;
}

// Bootstrap the Klytos application (minimal — no admin UI, just core + plugins).
require_once $adminBootstrap;

// ─── Handle the request ────────────────────────────────────────

$slug   = $_GET['slug'] ?? '';
$format = $_GET['format'] ?? 'html';

// Normalize format.
if ( $format === 'html.md' ) {
    $format = 'md';
} elseif ( $format !== 'md' ) {
    $format = 'html';
}

// Check that the x402 plugin is active and the gate is available.
if ( !function_exists( 'klytos_x402_providers' ) ) {
    http_response_code( 500 );
    header( 'Content-Type: application/json' );
    echo json_encode( ['error' => 'x402 plugin is not active.'] );
    exit;
}

// Get the gate instance from the plugin globals.
$gate = $GLOBALS['klytos_x402_gate'] ?? null;

if ( $gate === null ) {
    http_response_code( 500 );
    header( 'Content-Type: application/json' );
    echo json_encode( ['error' => 'x402 gate not initialized.'] );
    exit;
}

// Handle the request.
$gate->handle( $slug, $format, $_SERVER );
