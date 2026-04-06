<?php

/**
 * Klytos — Public Comment Submission Endpoint
 *
 * Lightweight endpoint for submitting comments from the static frontend.
 * Does NOT require authentication — this is the public comment form handler.
 *
 * Anti-spam: honeypot field + rate limiting via session.
 *
 * POST /admin-folder/api/comment-submit.php
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

// Only accept POST requests.
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code( 405 );
    header( 'Content-Type: application/json' );
    echo json_encode( ['error' => 'Method not allowed'] );
    exit;
}

// Boot the application.
require_once dirname( __DIR__ ) . '/bootstrap.php';

use Klytos\Core\App;

$app = App::getInstance();

// Check if comments are enabled.
$commentsEnabled = $app->getSiteConfig()->getValue( 'comments_enabled', false );
if ( !$commentsEnabled ) {
    http_response_code( 403 );
    header( 'Content-Type: application/json' );
    echo json_encode( ['error' => 'Comments are disabled'] );
    exit;
}

// Honeypot check: if the hidden field has a value, it's a bot.
$honeypotEnabled = $app->getSiteConfig()->getValue( 'comments_honeypot', true );
if ( $honeypotEnabled && !empty( $_POST['_honeypot'] ?? '' ) ) {
    // Silently accept but discard (don't reveal the check to bots).
    http_response_code( 200 );
    header( 'Content-Type: application/json' );
    echo json_encode( ['success' => true, 'message' => 'Comment submitted for moderation.'] );
    exit;
}

// Rate limiting: max 1 comment per 30 seconds per IP.
session_start();
$now     = time();
$lastComment = $_SESSION['last_comment_at'] ?? 0;
if ( $now - $lastComment < 30 ) {
    http_response_code( 429 );
    header( 'Content-Type: application/json' );
    echo json_encode( ['error' => 'Too many comments. Please wait before posting again.'] );
    exit;
}

try {
    $comment = $app->getCommentManager()->submit( [
        'page_slug'    => $_POST['page_slug'] ?? '',
        'author_name'  => $_POST['author_name'] ?? '',
        'author_email' => $_POST['author_email'] ?? '',
        'content'      => $_POST['content'] ?? '',
        'parent_id'    => $_POST['parent_id'] ?? '',
    ] );

    $_SESSION['last_comment_at'] = $now;

    // Send notification email if configured.
    try {
        $mailer    = $app->getMailer();
        $siteName  = $app->getSiteConfig()->getValue( 'site_name', 'Klytos' );
        $pageSlug  = $comment['page_slug'] ?? '';
        $subject   = "[{$siteName}] New comment on /{$pageSlug}/";
        $body      = "Author: " . ( $comment['author_name'] ?? '' ) . "\n"
                   . "Page: /" . $pageSlug . "/\n\n"
                   . ( $comment['content'] ?? '' ) . "\n\n"
                   . "Status: pending moderation";
        $mailer->send( '', $subject, $body );
    } catch ( \Throwable $e ) {
        // Email failure should not block comment submission.
    }

    http_response_code( 201 );
    header( 'Content-Type: application/json' );
    echo json_encode( [
        'success' => true,
        'message' => 'Comment submitted for moderation.',
        'id'      => $comment['id'],
    ] );

} catch ( \RuntimeException $e ) {
    http_response_code( 400 );
    header( 'Content-Type: application/json' );
    echo json_encode( ['error' => $e->getMessage()] );
}
