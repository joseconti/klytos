<?php

/**
 * Klytos Admin API — AI Translation Endpoint
 * Translates text using a configured AI provider.
 *
 * POST JSON:
 *   { "provider": "anthropic", "source_text": "...", "source_locale": "en",
 *     "target_locale": "es", "context": "CMS admin panel translation - key: ..." }
 *
 * @package Klytos
 * @since   0.19.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/bootstrap.php';

use Klytos\Core\Helpers;

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

// ─── Rate limiting (30 AI calls per minute per session) ─────
$now   = time();
$key   = 'klytos_translations_ai_rate';
$limit = 30;

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
$input        = json_decode( file_get_contents( 'php://input' ), true );
$providerId   = $input['provider'] ?? '';
$sourceText   = $input['source_text'] ?? '';
$sourceLocale = $input['source_locale'] ?? 'en';
$targetLocale = $input['target_locale'] ?? '';
$context      = $input['context'] ?? '';

if ( $sourceText === '' || $targetLocale === '' ) {
    Helpers::jsonResponse( ['error' => 'Missing required fields: source_text, target_locale'], 400 );
}

// ─── Validate provider ──────────────────────────────────────
try {
    $aiKeys = new \Klytos\Core\Ai\AiKeyManager( $app->getStorage(), $app->getConfigPath() );

    // If no specific provider, use the active one.
    if ( $providerId === '' ) {
        $active     = $aiKeys->getActive();
        $providerId = $active['provider'] ?? '';
    }

    if ( $providerId === '' || !$aiKeys->hasKey( $providerId ) ) {
        Helpers::jsonResponse( ['error' => 'No AI provider configured or invalid provider.'], 400 );
    }
} catch ( \Throwable $e ) {
    Helpers::jsonResponse( ['error' => 'AI provider error: ' . $e->getMessage()], 500 );
}

// ─── Build translation prompt ───────────────────────────────
$prompt = "Translate the following text from {$sourceLocale} to {$targetLocale}.\n"
    . "Context: This is a UI string for a CMS admin panel.";

if ( $context !== '' ) {
    $prompt .= " {$context}";
}

$prompt .= "\nKeep HTML tags intact if present. Do not add explanations.\n"
    . "Do not translate placeholder variables like {variable}.\n"
    . "Only return the translated text, nothing else.\n\n"
    . "Text: {$sourceText}";

// ─── Call AI via ChatEngine ──────────────────────────────────
try {
    $chatEngine = $app->getChatEngine();
    $userId     = $app->getAuth()->getUser()['id'] ?? 0;

    $messages = [
        ['role' => 'user', 'content' => $prompt],
    ];

    $result = $chatEngine->processMessage( (int) $userId, $messages, [
        'provider'   => $providerId,
        'max_tokens' => 1024,
    ] );

    if ( $result->status === 'success' && $result->assistantMessage !== '' ) {
        $translation = trim( $result->assistantMessage );

        // Remove potential quote wrapping from AI responses.
        if (
            ( str_starts_with( $translation, '"' ) && str_ends_with( $translation, '"' ) ) ||
            ( str_starts_with( $translation, "'" ) && str_ends_with( $translation, "'" ) )
        ) {
            $translation = substr( $translation, 1, -1 );
        }

        Helpers::jsonResponse( [
            'success'     => true,
            'translation' => $translation,
        ] );
    } else {
        Helpers::jsonResponse( [
            'error' => $result->status === 'error'
                ? ( $result->assistantMessage ?: 'AI translation failed.' )
                : 'Empty response from AI provider.',
        ], 500 );
    }
} catch ( \Throwable $e ) {
    Helpers::jsonResponse( ['error' => 'AI error: ' . $e->getMessage()], 500 );
}
