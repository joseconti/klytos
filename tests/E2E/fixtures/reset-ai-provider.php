<?php

/**
 * Klytos CMS — the provider entry 12 (AI chat) cannot show its composer without.
 *
 * `installer/admin/ai-chat.php` decides between two mutually exclusive states
 * SERVER-SIDE: with no configured provider the composer is not rendered at all
 * and the delivery's "not configured" line is rendered instead (§2 "Error — no
 * API key"). That is a deliberate property of the fix for the shipped defect —
 * there is no composer in the document for a script to un-hide — and it means
 * `page.route()` cannot conjure one: the branch is taken before any JavaScript
 * runs.
 *
 * So this fixture writes a throwaway key, and removes it again. It is
 * reversible and idempotent for the reason `reset-terminal.php` is: a run that
 * dies between the two must not leave the seed carrying a provider the next
 * spec does not expect.
 *
 * THE KEY IS A LITERAL AND THAT IS SAFE, which is worth saying because the
 * confidential-data gate is right to be suspicious of key-shaped strings in
 * tracked source. This value reaches no provider: `--on` never sends a request,
 * and the one spec that exercises sending intercepts `api/ai-chat.php` at the
 * browser, so nothing ever leaves the machine. It is stored encrypted at rest
 * by `AiKeyManager` like any other key and removed by `--off`.
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-ai-provider.php --on       configure anthropic
 *   php tests/E2E/fixtures/reset-ai-provider.php --off      remove it
 *   php tests/E2E/fixtures/reset-ai-provider.php --status   print what exists
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

$app = \Klytos\Core\App::getInstance();
$app->boot();

/** The provider the spec drives. Its models are a class constant, so the
 *  model chip has real options without any network call. */
const E2E_AI_PROVIDER = 'anthropic';

/** A value no provider ever issued. Never sent anywhere — see the docblock. */
const E2E_AI_PLACEHOLDER = 'e2e-placeholder-not-a-real-credential';

$keys = new \Klytos\Core\Ai\AiKeyManager( $app->getStorage(), $app->getConfigPath() );
$args = array_slice( $argv, 1 );

if ( in_array( '--status', $args, true ) ) {
    $active = $keys->getActive();
    echo 'provider configured: ' . ( $keys->hasKey( E2E_AI_PROVIDER ) ? 'yes' : 'no' ) . "\n";
    echo 'active provider: ' . ( $active['provider'] ?? '(none)' ) . "\n";
    echo 'active model: ' . ( $active['model'] ?? '(none)' ) . "\n";
    exit( 0 );
}

if ( in_array( '--on', $args, true ) ) {
    $model = $keys->getDefaultModelForProvider( E2E_AI_PROVIDER );
    $keys->setKey( E2E_AI_PROVIDER, E2E_AI_PLACEHOLDER, $model );
    $keys->setActive( E2E_AI_PROVIDER, $model );

    // Read back rather than assume: a fixture that reports success without
    // checking is the defect this screen's own slice was fixing.
    if ( ! $keys->hasKey( E2E_AI_PROVIDER ) ) {
        fwrite( STDERR, "FAILED: the key was not stored.\n" );
        exit( 1 );
    }

    echo 'on: ' . E2E_AI_PROVIDER . ' / ' . $model . "\n";
    exit( 0 );
}

if ( in_array( '--off', $args, true ) ) {
    $keys->removeKey( E2E_AI_PROVIDER );

    if ( $keys->hasKey( E2E_AI_PROVIDER ) ) {
        fwrite( STDERR, "FAILED: the key is still configured.\n" );
        exit( 1 );
    }

    echo "off\n";
    exit( 0 );
}

fwrite( STDERR, "Usage: reset-ai-provider.php --on | --off | --status\n" );
exit( 1 );
