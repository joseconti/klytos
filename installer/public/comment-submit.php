<?php

/**
 * Klytos — Public Comment Submission Endpoint (web root)
 *
 * This file is deliberately NOT part of the admin tree. It is copied to the
 * site's WEB ROOT by the build engine, so the URL a generated page posts to is
 * `/comment-submit.php` — with no segment naming the randomized admin
 * directory. That is the whole point of moving it (audit S-09):
 * `Helpers::getBasePath()` (core/helpers.php:192-197) states the admin
 * directory name "must NEVER appear in public URLs", and the previous location
 * — `<admin>/api/comment-submit.php` — could not be reached by a public form
 * without putting it there.
 *
 * Reaching it through the ADMIN bootstrap was never viable either, and not only
 * for the URL. `admin/bootstrap.php` runs the cron manager and the action
 * scheduler on every request (bootstrap.php:184-196), so exempting an
 * anonymous endpoint from its auth guard would have handed every passer-by a
 * scheduler trigger. `installer/index.php` does neither (index.php:62), and
 * this file does neither.
 *
 * Anti-spam: honeypot field + a PERSISTENT, IP-keyed rate limit. The rate limit
 * deliberately does NOT use the session: Auth::startSession() scopes the admin
 * cookie to `path=<base>/admin/` with `SameSite=Strict` (core/auth.php:52-62),
 * so a form on the generated static site never sends it back and every
 * submission would arrive with a brand-new session. The previous
 * `$_SESSION['last_comment_at']` check was therefore not a weak rate limit, it
 * was no rate limit at all.
 *
 * POST /comment-submit.php
 * Body: page_slug, author_name, author_email, content, parent_id, _honeypot
 *
 * @package Klytos
 * @since   0.18.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

// Never leak internals to an anonymous caller.
ini_set( 'display_errors', '0' );

// Answer with a JSON body and stop. A closure rather than a named function:
// this file is copied to the site's WEB ROOT, and an entry point there should
// add nothing to the global function namespace that the application might one
// day collide with.
$respond = static function ( int $status, array $payload ): void {
    http_response_code( $status );
    header( 'Content-Type: application/json; charset=utf-8' );

    // Same flags as Helpers::jsonResponse( ) (helpers.php:345), which every
    // other endpoint answers through. This file cannot call it — two of the
    // responses below fire before core/app.php is even required — but its
    // output must not differ from the rest of the product's, or an accented
    // author name would come back \uXXXX-escaped here and literal everywhere
    // else.
    echo json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    exit;
};

// Only accept POST. Checked before the application is located or booted, so a
// crawler issuing GET costs nothing.
if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'POST' ) {
    header( 'Allow: POST' );
    $respond( 405, [ 'error' => 'Method not allowed' ] );
}

// ─── Locate the Klytos installation ──────────────────────────────
// The admin directory name is randomized at install time and is not knowable
// statically, so it is discovered: it is the only child of the web root that
// contains core/app.php. This mirrors the discovery in public/x402-gate.php,
// which is the product's existing answer to the same problem — but NOT its
// bootstrap, which requires core/bootstrap-minimal.php, a file that does not
// exist in this repository (it falls through to index.php, which dispatches
// the router instead of returning). Booting the documented way instead.
$klytosRoot = null;

foreach ( glob( __DIR__ . '/*/core/app.php' ) ?: [] as $candidate ) {
    $klytosRoot = dirname( dirname( $candidate ) );
    break;
}

// In the repository the file has not been copied to a web root yet: it still
// sits in installer/public/, so the installation is its own grandparent. This
// is what makes the endpoint exercisable in the playground, where the build
// that would copy it cannot be run (audit NEW-04).
if ( $klytosRoot === null && is_file( dirname( __DIR__ ) . '/core/app.php' ) ) {
    $klytosRoot = dirname( __DIR__ );
}

if ( $klytosRoot === null ) {
    $respond( 500, [ 'error' => 'Comments are unavailable.' ] );
}

use Klytos\Core\App;
use Klytos\Core\MCP\RateLimiter;

// ─── Flood ceiling — BEFORE the application is booted ────────────
// Ordering here is a security property, not tidiness. App::boot() decrypts the
// config, builds ~25 managers and runs PluginLoader::loadAll(), executing every
// active plugin's init.php (app.php:530-537). Every other check in this file
// needs a booted App, so a naive ordering makes an anonymous caller pay the
// full boot cost on every request — at a FIXED, scannable URL on every install,
// where previously reaching that cost anonymously meant guessing the randomized
// admin directory name. That is a resource-amplification DoS this slice would
// have introduced, and it is exactly the class of thing D-036 exists to make
// somebody ask about before making a surface public.
//
// The rate limiter needs nothing but a directory path (rate-limiter.php:35-38)
// and App::$dataPath is always rootPath . '/data' (app.php:228), so the ceiling
// can be enforced for the price of one JSON read/write. The class is required
// explicitly because the autoloader is only registered during boot — the same
// reason t.php requires the specific core files it uses.
require_once $klytosRoot . '/core/mcp/rate-limiter.php';

/**
 * Hard flood ceiling per address per 60-second window.
 *
 * Deliberately NOT filterable: it runs before plugins are loaded, so no filter
 * could exist yet, and a ceiling a plugin can raise is not a ceiling. It is set
 * well above the comment policy below — it exists to bound cost, not to express
 * editorial intent.
 */
const KLYTOS_COMMENT_FLOOD_CEILING = 10;

/** Default comments allowed per address per window. Filterable after boot. */
const KLYTOS_COMMENT_RATE_DEFAULT = 2;

$clientIp    = RateLimiter::getClientIp();
$rateKey     = 'comment:' . $clientIp;
$rateLimiter = new RateLimiter( $klytosRoot . '/data' );

if ( ! $rateLimiter->check( $rateKey, KLYTOS_COMMENT_FLOOD_CEILING ) ) {
    header( 'Retry-After: 60' );
    $respond( 429, [ 'error' => 'Too many requests.' ] );
}

require_once $klytosRoot . '/core/app.php';

try {
    $app = App::getInstance();
    $app->boot();
} catch ( \Throwable $e ) {
    error_log( 'Klytos comment endpoint: boot failed — ' . $e->getMessage() );
    $respond( 500, [ 'error' => 'Comments are unavailable.' ] );
}

// Translate a catalogue key. The global __() helper is NOT available here: it
// is defined in admin/bootstrap.php:28 — inside the ADMIN bootstrap — while
// app.php:785 declares a NAMESPACED Klytos\Core\__() that an unnamespaced file
// cannot reach. That is why every other public entry point in this product
// (t.php, x402-gate.php) hardcodes English. Rather than add a third copy of
// that shim, this calls the I18n service the application already booted.
$text = static function ( string $key ) use ( $app ): string {
    return $app->getI18n()->get( $key );
};

// ─── Comments must be switched on ────────────────────────────────
if ( ! $app->getSiteConfig()->getValue( 'comments_enabled', false ) ) {
    $respond( 403, [ 'error' => $text( 'comments.disabled' ) ] );
}

// ─── Comment rate policy — the filterable half ───────────────────
// The ceiling above already consumed this request's slot; this evaluates the
// site's actual policy against the same window WITHOUT recording a second one.
// Splitting it this way is what lets the expensive path stay bounded while the
// policy remains extensible: the ceiling runs before plugins exist, the policy
// runs once they do.
//
// Reuses the product's one rate limiter (core/mcp/rate-limiter.php), already
// behind the MCP endpoint, the OAuth token endpoint and the plugin route layer.
// Its window is a fixed 60 seconds, so the policy is a count within it rather
// than a bespoke interval; inventing a second limiter to express "one per 30
// seconds" exactly would be the duplication this project treats as a defect.

/**
 * Filter the number of comments one address may submit per 60-second window.
 *
 * Values above KLYTOS_COMMENT_FLOOD_CEILING have no effect — the ceiling is
 * enforced before any plugin is loaded and cannot be raised from a filter.
 *
 * @param int    $max      Maximum submissions per window.
 * @param string $clientIp Resolved client address.
 */
$maxPerWindow = (int) klytos_apply_filters(
    'comment.rate_limit',
    KLYTOS_COMMENT_RATE_DEFAULT,
    $clientIp
);

// getRemainingRequests() only reads, so the count is derived rather than
// re-recorded: used = ceiling - remaining.
$used = KLYTOS_COMMENT_FLOOD_CEILING - $rateLimiter->getRemainingRequests(
    $rateKey,
    KLYTOS_COMMENT_FLOOD_CEILING
);

if ( $used > $maxPerWindow ) {
    klytos_do_action( 'comment.rate_limited', $clientIp );

    header( 'Retry-After: 60' );
    $respond( 429, [ 'error' => $text( 'comments.rate_limited' ) ] );
}

// ─── Honeypot ────────────────────────────────────────────────────
// A populated hidden field means a bot filled every input it found. Answer
// exactly as a success would, so the bot cannot learn the field is a trap —
// but store nothing.
//
// This runs AFTER the rate limit deliberately. When it ran before, a flood that
// simply set _honeypot on every request took the cheap 200 branch and was never
// counted — the one control meant to bound repeated abuse never engaged for the
// traffic most likely to trigger it.
$honeypotOn  = (bool) $app->getSiteConfig()->getValue( 'comments_honeypot', true );
$honeypotHit = ! empty( $_POST['_honeypot'] ?? '' );

if ( $honeypotOn && $honeypotHit ) {
    klytos_do_action( 'comment.honeypot_rejected', [
        'page_slug' => (string) ( $_POST['page_slug'] ?? '' ),
        'ip'        => $clientIp,
    ] );

    // Byte-identical in SHAPE and in STATUS to the success below, including a
    // syntactically valid identifier. Answering 200 here while a real
    // submission answers 201 let a bot distinguish the two by status alone,
    // which is the whole thing this branch exists to prevent — the code said
    // "exactly as a success would" and then did not.
    $respond( 201, [
        'success' => true,
        'message' => $text( 'comments.submitted' ),
        'id'      => bin2hex( random_bytes( 16 ) ),
    ] );
}

// ─── Submit ──────────────────────────────────────────────────────
try {
    $comment = $app->getCommentManager()->submit( [
        'page_slug'    => $_POST['page_slug'] ?? '',
        'author_name'  => $_POST['author_name'] ?? '',
        'author_email' => $_POST['author_email'] ?? '',
        'content'      => $_POST['content'] ?? '',
        'parent_id'    => $_POST['parent_id'] ?? '',
    ] );
} catch ( \RuntimeException $e ) {
    // The manager's validation messages describe the caller's own input
    // (a missing field, an over-long name) and are safe to return.
    $respond( 400, [ 'error' => $e->getMessage() ] );
} catch ( \Throwable $e ) {
    error_log( 'Klytos comment endpoint: submit failed — ' . $e->getMessage() );
    $respond( 500, [ 'error' => $text( 'comments.submit_failed' ) ] );
}

// ─── Moderation notification ─────────────────────────────────────
// Best-effort: a mail failure must never lose a comment that is already
// stored. Note the recipient — the previous implementation called
// `$mailer->send( '', ... )`, which hits the empty-recipient guard at
// mailer.php:107-110 and returns false into a swallowed catch, so this
// notification had never once been delivered.
try {
    $notifyTo = (string) klytos_apply_filters(
        'comment.notification_recipient',
        (string) ( $app->getConfig()['admin_email'] ?? '' ),
        $comment
    );

    if ( $notifyTo !== '' ) {
        $siteName = (string) $app->getSiteConfig()->getValue( 'site_name', 'Klytos' );
        $pageSlug = (string) ( $comment['page_slug'] ?? '' );

        $app->getMailer()->send(
            $notifyTo,
            sprintf( '[%s] %s /%s/', $siteName, $text( 'comments.notification_subject' ), $pageSlug ),
            sprintf(
                "%s: %s\n%s: /%s/\n\n%s\n\n%s",
                $text( 'comments.author' ),
                (string) ( $comment['author_name'] ?? '' ),
                $text( 'comments.page' ),
                $pageSlug,
                (string) ( $comment['content'] ?? '' ),
                $text( 'comments.status_pending' )
            )
        );
    }
} catch ( \Throwable $e ) {
    error_log( 'Klytos comment endpoint: notification failed — ' . $e->getMessage() );
}

$respond( 201, [
    'success' => true,
    'message' => $text( 'comments.submitted' ),
    'id'      => $comment['id'],
] );
