<?php

/**
 * Klytos CMS — PHPUnit bootstrap (Sprint 1, slice 1 / T-01).
 *
 * Deliberately thin. All class and function loading comes from Composer's dev
 * autoloader (`autoload-dev` in composer.json):
 *
 *   - PSR-4      Klytos\Tests\*  →  tests/
 *   - classmap   Klytos\Core\*   →  installer/core/
 *   - files      the klytos_* global helper functions
 *
 * Using the classmap rather than re-implementing App::registerAutoloader()
 * matters: that method is private and bound to a booted App instance, so a
 * test-side copy of its CamelCase→kebab-case mapping would be a second
 * implementation of the same rule, free to drift from the first. Composer
 * derives the map from the files themselves, so there is nothing to keep in
 * sync — and the unit tier gets class loading without an App.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! is_file( $autoload ) ) {
    fwrite( STDERR, <<<TXT

    Composer dependencies are not installed.

        composer install

    See docs/playground.md ("Running the tests").

    TXT );
    exit( 1 );
}

require_once $autoload;

/** Absolute path to the repository root. */
define( 'KLYTOS_REPO_ROOT', dirname( __DIR__ ) );

/** Absolute path to the Klytos application root (the installer/ directory). */
define( 'KLYTOS_INSTALLER_PATH', KLYTOS_REPO_ROOT . '/installer' );
