<?php

/**
 * Klytos Admin API — Translation Save Endpoint
 * Saves individual translations via AJAX.
 *
 * POST JSON:
 *   { "source": "core", "locale": "es", "key": "common.save", "value": "Guardar" }
 *
 * @package Klytos
 * @since   0.19.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/bootstrap.php';

use Klytos\Core\Helpers;
use Klytos\Core\TranslationManager;

header( 'Content-Type: application/json; charset=utf-8' );

// ─── Authentication ──────────────────────────────────────────
if ( !$app->getAuth()->isAuthenticated() ) {
    Helpers::jsonResponse( ['error' => 'Unauthorized'], 401 );
}

// ─── Permission check ────────────────────────────────────────
if ( !klytos_has_permission( 'site.configure' ) ) {
    Helpers::jsonResponse( ['error' => 'Forbidden'], 403 );
}

// ─── CSRF verification ──────────────────────────────────────
if ( !klytos_verify_csrf() ) {
    Helpers::jsonResponse( ['error' => 'Invalid CSRF token'], 403 );
}

// ─── Rate limiting (60 operations per minute per session) ───
$now   = time();
$key   = 'klytos_translations_api_rate';
$limit = 60;

if ( !isset( $_SESSION[$key] ) ) {
    $_SESSION[$key] = ['count' => 0, 'reset' => $now + 60];
}
if ( $_SESSION[$key]['reset'] < $now ) {
    $_SESSION[$key] = ['count' => 0, 'reset' => $now + 60];
}
$_SESSION[$key]['count']++;

if ( $_SESSION[$key]['count'] > $limit ) {
    Helpers::jsonResponse( ['error' => 'Rate limit exceeded. Try again in a minute.'], 429 );
}

// ─── Parse input ─────────────────────────────────────────────
$input  = json_decode( file_get_contents( 'php://input' ), true );
$source = $input['source'] ?? '';
$locale = $input['locale'] ?? '';
$tKey   = $input['key'] ?? '';
$value  = $input['value'] ?? '';

if ( $source === '' || $locale === '' || $tKey === '' ) {
    Helpers::jsonResponse( ['error' => 'Missing required fields: source, locale, key'], 400 );
}

// ─── Save ────────────────────────────────────────────────────
try {
    $tm = new TranslationManager( $app );
    $tm->saveTranslation( $source, $locale, $tKey, (string) $value );
    Helpers::jsonResponse( ['success' => true] );
} catch ( \InvalidArgumentException $e ) {
    Helpers::jsonResponse( ['error' => $e->getMessage()], 400 );
} catch ( \RuntimeException $e ) {
    Helpers::jsonResponse( ['error' => $e->getMessage()], 500 );
}
