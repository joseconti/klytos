<?php

/**
 * Klytos Admin API — Log file download.
 *
 * Manifest entry 41's control row ends with **Download**
 * (`SPEC/screens/template-console-stream.md` §1), and the screen's truncation
 * state links here by name: "a stream longer than 5,000 lines shows the last
 * 5,000 and says so at the top of the stream, with a link to download the whole
 * file" (§2). Nothing in the admin streamed a log file before, so this endpoint
 * is new — the only new endpoint entry 41 needs, because the Follow poll reuses
 * `api/logs.php`'s existing `read` action rather than duplicating it.
 *
 * It is a **GET** deliberately: it changes no state, so the project's "never
 * change state on a GET" rule is satisfied by not changing any, and a download
 * has to be reachable as a plain link (an `<a download>` the browser can
 * follow, and a control that still works with JavaScript off). For the same
 * reason it carries no CSRF token: CSRF protects state changes, and a token in
 * a URL would leak into history and referrers for no gain.
 *
 * Authorization is the same `site.configure` gate the Logs screen and
 * `api/logs.php` carry, enforced centrally by `core/admin-gate.php` before this
 * file's body runs. The filename is resolved by the Logger's own
 * `safeFilePath()` — via `isLogFileReadable()` — so the traversal refusal,
 * prefix and extension validation are the single implementation that already
 * guards every other read.
 *
 * @package Klytos
 * @since   0.31.0
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

// ─── Method check ────────────────────────────────────────────
// A download reads; anything but GET is a caller doing something else.
if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
    header( 'Content-Type: application/json; charset=utf-8' );
    Helpers::jsonResponse( ['error' => 'Method not allowed'], 405 );
}

$logger   = $app->getLogger();
$filename = basename( $_GET['file'] ?? '' );

/*
 * One answer for "missing", "does not resolve" and "cannot be opened": the
 * screen renders Download disabled for a file it cannot read, so reaching here
 * with an unreadable name is not a state a user can produce from the rendered
 * page. It gets a 404 rather than a message that would distinguish a file that
 * exists from one that does not.
 */
if ( $filename === '' || ! $logger->isLogFileReadable( $filename ) ) {
    header( 'Content-Type: application/json; charset=utf-8' );
    Helpers::jsonResponse( ['error' => 'Not found'], 404 );
}

$filePath = $logger->getLogsDir() . '/' . $filename;
$size     = filesize( $filePath );

klytos_do_action( 'admin.log_download', $filename );

/*
 * text/plain, not application/octet-stream: it IS text, and the browser's own
 * viewer is a reasonable destination for a log. `Content-Disposition:
 * attachment` still makes it a download rather than a navigation, and
 * `X-Content-Type-Options: nosniff` — already sent by the security headers —
 * keeps the declared type from being second-guessed.
 */
header( 'Content-Type: text/plain; charset=utf-8' );
header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
if ( $size !== false ) {
    header( 'Content-Length: ' . $size );
}
header( 'Cache-Control: no-store' );

readfile( $filePath );
