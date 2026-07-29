<?php

/**
 * Klytos Admin API — Theme Toggle Endpoint
 *
 * Sets the acting person's admin colour scheme as a cookie and returns them to
 * the screen they were on. The cookie is what lets the SERVER render the right
 * theme on the next request, which is how `SPEC/screens/template-shell.md` §1
 * requires the toggle to work: there is no flash of the wrong theme, ever.
 *
 * POST only, with a CSRF token. template-shell.md describes the JS-off path as
 * "a link with a query parameter that sets the same cookie"; a link is a GET,
 * and this project does not change state on a GET and requires CSRF on every
 * mutating handler. A bare link would also let any page flip a person's theme.
 * The normative part of the design — a `<button aria-pressed>` naming its target
 * state, and a server-set cookie — is unchanged. See BUILD-SPEC §5.9,
 * adaptation 8.
 *
 * @package Klytos
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Klytos\Core\Helpers;

// ─── Authorization ──────────────────────────────────────────
// The capability check lives INSIDE the endpoint, never inferred from the
// includer (NEW-31 is exactly that defect). `ui.preferences` is the existing
// self-service capability for per-user UI state — the same one api/notices.php
// and api/sidebar-order.php use — so no new capability is invented and every
// role holds it: nobody is locked out of their own colour scheme.
klytos_require_permission( 'ui.preferences' );

if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
    http_response_code( 405 );
    header( 'Allow: POST' );
    exit;
}

if ( ! klytos_verify_csrf() ) {
    http_response_code( 403 );
    exit;
}

// ─── The value ──────────────────────────────────────────────
// Only the two themes the token layer defines are accepted; anything else is
// ignored rather than stored, so a crafted value can never reach the
// `data-theme` attribute.
$theme = $_POST['theme'] ?? '';
if ( in_array( $theme, [ 'light', 'dark' ], true ) ) {
    /**
     * Filter: the admin theme about to be persisted for this person.
     *
     * @param string $theme One of 'light' or 'dark'.
     */
    $theme = klytos_apply_filters( 'admin.theme_choice', $theme );

    setcookie(
        'klytos_admin_theme',
        $theme,
        [
            'expires'  => time() + ( 86400 * 365 ),
            'path'     => '/',
            'secure'   => ! empty( $_SERVER['HTTPS'] ),
            'httponly' => false,
            'samesite' => 'Lax',
        ]
    );

    klytos_do_action( 'admin.theme_changed', $theme );
}

// ─── Back where they were ───────────────────────────────────
// Only a same-site, relative path is honoured: an absolute or protocol-relative
// value would turn this endpoint into an open redirect.
$redirectTo = (string) ( $_POST['redirect_to'] ?? '' );
if ( $redirectTo === '' || $redirectTo[0] !== '/' || str_starts_with( $redirectTo, '//' ) ) {
    $redirectTo = Helpers::getBasePath() . 'admin/';
}

Helpers::redirect( $redirectTo );
