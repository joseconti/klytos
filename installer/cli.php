<?php
/**
 * Klytos — Command Line Interface
 * Thin adapter over TerminalExecutor for shell usage.
 *
 * Usage:
 *   php cli.php <command> [arguments] [--flag=value]
 *
 * Examples:
 *   php cli.php build
 *   php cli.php build:page my-page
 *   php cli.php analytics --period=30d
 *   php cli.php backup:create --label=weekly
 *   php cli.php help
 *   php cli.php help backup:create
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

// ─── Ensure CLI context ──────────────────────────────────────
if ( php_sapi_name() !== 'cli' ) {
    echo "This script can only be run from the command line.\n";
    exit( 1 );
}

// ─── Bootstrap ───────────────────────────────────────────────
$rootPath = __DIR__;
require_once $rootPath . '/core/app.php';

use Klytos\Core\App;
use Klytos\Core\TerminalExecutor;

$app = App::getInstance();

if ( ! $app->isInstalled() ) {
    fwrite( STDERR, "\033[31m✗ Klytos is not installed. Run the web installer first.\033[0m\n" );
    exit( 1 );
}

$app->boot();

// ─── Parse Arguments ─────────────────────────────────────────
$command = $argv[1] ?? 'help';
$args    = [];
$flags   = [];

// Strip "klytos" prefix if user types "php cli.php klytos build".
if ( strtolower( $command ) === 'klytos' ) {
    $command = $argv[2] ?? 'help';
    $rawArgs = array_slice( $argv, 3 );
} else {
    $rawArgs = array_slice( $argv, 2 );
}

foreach ( $rawArgs as $arg ) {
    if ( str_starts_with( $arg, '--' ) ) {
        $flag = substr( $arg, 2 );
        if ( str_contains( $flag, '=' ) ) {
            [ $key, $value ] = explode( '=', $flag, 2 );
            $flags[ $key ]   = $value;
        } else {
            $flags[ $flag ] = 'true';
        }
    } else {
        $args[] = $arg;
    }
}

// ─── Dispatch via TerminalExecutor ───────────────────────────
$executor = new TerminalExecutor( $app );
$result   = $executor->dispatch( $command, $args, $flags );

// ─── Output ──────────────────────────────────────────────────
if ( $result['output'] !== '' ) {
    if ( $result['success'] ) {
        echo $result['output'] . "\n";
    } else {
        fwrite( STDERR, "\033[31m" . $result['output'] . "\033[0m\n" );
    }
}

exit( $result['success'] ? 0 : 1 );
