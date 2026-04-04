<?php

/**
 * Klytos — Comment Manager
 * CRUD operations for page comments with moderation, threading, and anti-spam.
 *
 * Comments are stored in the 'comments' collection. Approved comments are
 * rendered as static HTML during build — zero database queries on the frontend.
 * New comments are submitted via a lightweight PHP endpoint.
 *
 * @package Klytos
 * @since   0.18.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class CommentManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string Collection name. */
    private const COLLECTION = 'comments';

    /** @var array Valid comment statuses. */
    public const VALID_STATUSES = ['pending', 'approved', 'spam', 'trash'];

    /** @var int Maximum threading depth. */
    public const MAX_THREAD_DEPTH = 3;

    public function __construct( StorageInterface $storage )
    {
        $this->storage = $storage;
    }

    /**
     * Submit a new comment (from the public form).
     *
     * @param  array $data Comment data: page_slug, author_name, author_email, content, parent_id.
     * @return array The created comment.
     * @throws \RuntimeException On validation failure.
     */
    public function submit( array $data ): array
    {
        $pageSlug = Helpers::sanitizeSlug( $data['page_slug'] ?? '' );
        if ( empty( $pageSlug ) ) {
            throw new \RuntimeException( 'page_slug is required.' );
        }

        $authorName = trim( $data['author_name'] ?? '' );
        if ( empty( $authorName ) ) {
            throw new \RuntimeException( 'author_name is required.' );
        }

        $content = trim( $data['content'] ?? '' );
        if ( empty( $content ) ) {
            throw new \RuntimeException( 'Comment content is required.' );
        }
        // Strip HTML tags from comment content.
        $content = strip_tags( $content );
        if ( mb_strlen( $content ) > 5000 ) {
            $content = mb_substr( $content, 0, 5000 );
        }

        $authorEmail = trim( $data['author_email'] ?? '' );
        $parentId    = $data['parent_id'] ?? '';

        // Verify threading depth.
        if ( !empty( $parentId ) ) {
            $depth = $this->getThreadDepth( $parentId );
            if ( $depth >= self::MAX_THREAD_DEPTH ) {
                $parentId = ''; // Flatten if max depth reached.
            }
        }

        $id  = Helpers::randomHex( 16 );
        $now = Helpers::now();

        // Hash IP for privacy (same daily-salt approach as analytics).
        $ipHash = hash( 'sha256', ( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) . date( 'Y-m-d' ) );

        $comment = [
            'id'          => $id,
            'page_slug'   => $pageSlug,
            'author_name' => Helpers::sanitizeText( $authorName ),
            'author_email_hash' => !empty( $authorEmail ) ? md5( strtolower( trim( $authorEmail ) ) ) : '',
            'content'     => $content,
            'status'      => 'pending', // Always pending until moderated.
            'parent_id'   => $parentId,
            'ip_hash'     => $ipHash,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];

        klytos_do_action( 'comment.before_save', $comment );

        $this->storage->write( self::COLLECTION, $id, $comment );

        klytos_do_action( 'comment.after_save', $comment, 'create' );

        return $comment;
    }

    /**
     * Moderate a comment (approve, spam, trash).
     *
     * @param  string $id     Comment ID.
     * @param  string $status New status: approved, spam, trash.
     * @return array  Updated comment.
     */
    public function moderate( string $id, string $status ): array
    {
        if ( !in_array( $status, self::VALID_STATUSES, true ) ) {
            throw new \RuntimeException( 'Invalid status: ' . $status );
        }

        $comment = $this->storage->read( self::COLLECTION, $id );
        $comment['status']     = $status;
        $comment['updated_at'] = Helpers::now();

        $this->storage->write( self::COLLECTION, $id, $comment );

        klytos_do_action( 'comment.moderated', $comment, $status );

        return $comment;
    }

    /**
     * Bulk moderate comments.
     *
     * @param  array  $ids    Comment IDs.
     * @param  string $status New status.
     * @return int    Number of comments moderated.
     */
    public function bulkModerate( array $ids, string $status ): int
    {
        $count = 0;
        foreach ( $ids as $id ) {
            try {
                $this->moderate( $id, $status );
                $count++;
            } catch ( \Throwable $e ) {
                // Skip invalid IDs.
            }
        }
        return $count;
    }

    /**
     * Delete a comment permanently.
     *
     * @param  string $id Comment ID.
     * @return bool
     */
    public function delete( string $id ): bool
    {
        klytos_do_action( 'comment.before_delete', $id );
        $result = $this->storage->delete( self::COLLECTION, $id );
        if ( $result ) {
            klytos_do_action( 'comment.after_delete', $id );
        }
        return $result;
    }

    /**
     * Get a single comment.
     *
     * @param  string $id Comment ID.
     * @return array
     */
    public function get( string $id ): array
    {
        return $this->storage->read( self::COLLECTION, $id );
    }

    /**
     * List comments with optional filters.
     *
     * @param  string $status   Filter by status ('all', 'pending', 'approved', 'spam', 'trash').
     * @param  string $pageSlug Filter by page slug (empty = all).
     * @param  int    $limit
     * @param  int    $offset
     * @return array
     */
    public function list( string $status = 'all', string $pageSlug = '', int $limit = 50, int $offset = 0 ): array
    {
        $filters = [];
        if ( $status !== 'all' ) {
            $filters['status'] = $status;
        }
        if ( $pageSlug !== '' ) {
            $filters['page_slug'] = $pageSlug;
        }

        $comments = $this->storage->list( self::COLLECTION, $filters );

        // Sort by created_at descending (newest first).
        usort( $comments, function ( array $a, array $b ): int {
            return strcmp( $b['created_at'] ?? '', $a['created_at'] ?? '' );
        } );

        return array_slice( $comments, $offset, $limit > 0 ? $limit : null );
    }

    /**
     * Count comments by status.
     *
     * @param  string $status   Filter by status ('all' = total).
     * @param  string $pageSlug Filter by page slug (empty = all pages).
     * @return int
     */
    public function count( string $status = 'all', string $pageSlug = '' ): int
    {
        $filters = [];
        if ( $status !== 'all' ) {
            $filters['status'] = $status;
        }
        if ( $pageSlug !== '' ) {
            $filters['page_slug'] = $pageSlug;
        }
        return $this->storage->count( self::COLLECTION, $filters );
    }

    /**
     * Get approved comments for a page, organized as a threaded tree.
     *
     * Used by BuildEngine to render static comment HTML.
     *
     * @param  string $pageSlug Page slug.
     * @return array  Threaded comment tree.
     */
    public function getThreaded( string $pageSlug ): array
    {
        $comments = $this->list( 'approved', $pageSlug, 0 );
        return $this->buildThread( $comments );
    }

    /**
     * Render approved comments as static HTML for a page.
     *
     * @param  string $pageSlug Page slug.
     * @param  string $lang     Language for labels.
     * @return string HTML fragment.
     */
    public function renderCommentsHtml( string $pageSlug, string $lang = 'es' ): string
    {
        $threaded = $this->getThreaded( $pageSlug );
        if ( empty( $threaded ) ) {
            return '';
        }

        $labels = [
            'title' => $lang === 'es' ? 'Comentarios' : 'Comments',
            'reply' => $lang === 'es' ? 'Responder' : 'Reply',
        ];

        $html = '<section class="klytos-comments" id="comments">' . "\n";
        $html .= '  <h2>' . $labels['title'] . ' (' . $this->count( 'approved', $pageSlug ) . ')</h2>' . "\n";
        $html .= $this->renderThread( $threaded, $labels );
        $html .= '</section>' . "\n";

        return $html;
    }

    /**
     * Build a threaded tree from a flat array of comments.
     */
    private function buildThread( array $comments, string $parentId = '' ): array
    {
        $tree = [];
        foreach ( $comments as $comment ) {
            if ( ( $comment['parent_id'] ?? '' ) === $parentId ) {
                $comment['children'] = $this->buildThread( $comments, $comment['id'] );
                $tree[] = $comment;
            }
        }
        return $tree;
    }

    /**
     * Render a threaded comment tree as HTML.
     */
    private function renderThread( array $comments, array $labels, int $depth = 0 ): string
    {
        if ( empty( $comments ) ) {
            return '';
        }

        $html = '<ol class="comment-list comment-depth-' . $depth . '">' . "\n";

        foreach ( $comments as $comment ) {
            $authorName = Helpers::escHtml( $comment['author_name'] ?? '' );
            $content    = nl2br( Helpers::escHtml( $comment['content'] ?? '' ) );
            $date       = $comment['created_at'] ?? '';
            $emailHash  = $comment['author_email_hash'] ?? '';
            $gravatarUrl = !empty( $emailHash )
                ? 'https://www.gravatar.com/avatar/' . $emailHash . '?s=48&d=mp'
                : 'https://www.gravatar.com/avatar/?s=48&d=mp';

            $html .= '<li class="comment" id="comment-' . Helpers::escAttr( $comment['id'] ) . '">' . "\n";
            $html .= '  <div class="comment-header">' . "\n";
            $html .= '    <img src="' . $gravatarUrl . '" alt="" class="comment-avatar" width="48" height="48" loading="lazy">' . "\n";
            $html .= '    <strong class="comment-author">' . $authorName . '</strong>' . "\n";
            $html .= '    <time class="comment-date" datetime="' . Helpers::escAttr( $date ) . '">' . $date . '</time>' . "\n";
            $html .= '  </div>' . "\n";
            $html .= '  <div class="comment-body">' . $content . '</div>' . "\n";

            // Render children.
            if ( !empty( $comment['children'] ) && $depth < self::MAX_THREAD_DEPTH ) {
                $html .= $this->renderThread( $comment['children'], $labels, $depth + 1 );
            }

            $html .= '</li>' . "\n";
        }

        $html .= '</ol>' . "\n";

        return $html;
    }

    /**
     * Get threading depth for a comment (how many parents above it).
     */
    private function getThreadDepth( string $commentId, int $depth = 0 ): int
    {
        if ( $depth >= self::MAX_THREAD_DEPTH ) {
            return $depth;
        }

        try {
            $comment = $this->storage->read( self::COLLECTION, $commentId );
            $parentId = $comment['parent_id'] ?? '';
            if ( !empty( $parentId ) ) {
                return $this->getThreadDepth( $parentId, $depth + 1 );
            }
        } catch ( \Throwable $e ) {
            // Parent not found.
        }

        return $depth;
    }
}
