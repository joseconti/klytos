<?php

/**
 * ImportSession — Persistent import session management.
 *
 * Tracks the state, progress, and page list of each import operation.
 * Sessions are stored in the 'import_sessions' storage collection.
 *
 * @package KlytosImporter
 */

declare( strict_types=1 );

namespace KlytosImporter;

use Klytos\Core\StorageInterface;

class ImportSession
{
    private const COLLECTION = 'import_sessions';

    private StorageInterface $storage;

    public function __construct( StorageInterface $storage )
    {
        $this->storage = $storage;
    }

    /**
     * Create a new import session.
     *
     * @param string      $source     One of: wordpress, sitemap, crawl.
     * @param string      $sourceUrl  URL of the source site.
     * @param string|null $sourceFile Path to uploaded file (WP XML).
     *
     * @return array The created session record.
     */
    public function create( string $source, string $sourceUrl, ?string $sourceFile = null ): array
    {
        $id  = 'imp_' . bin2hex( random_bytes( 6 ) );
        $now = gmdate( 'Y-m-d\TH:i:s\Z' );

        $session = [
            'id'          => $id,
            'source'      => $source,
            'source_url'  => $sourceUrl,
            'source_file' => $sourceFile,
            'status'      => 'analyzing',
            'created_at'  => $now,
            'updated_at'  => $now,
            'config'      => [],
            'analysis'    => [],
            'progress'    => [
                'total'    => 0,
                'imported' => 0,
                'pending'  => 0,
                'failed'   => 0,
            ],
            'url_map'   => [],
            'media_map' => [],
            'pages'     => [],
            'errors'    => [],
        ];

        $this->storage->write( self::COLLECTION, $id, $session );

        return $session;
    }

    /**
     * Get a session by ID.
     *
     * @throws \RuntimeException If the session does not exist.
     */
    public function get( string $sessionId ): array
    {
        if ( !$this->storage->exists( self::COLLECTION, $sessionId ) ) {
            throw new \RuntimeException( "Import session '{$sessionId}' not found." );
        }

        return $this->storage->read( self::COLLECTION, $sessionId );
    }

    /**
     * Partial update of a session.
     */
    public function update( string $sessionId, array $data ): void
    {
        $session = $this->get( $sessionId );
        $merged  = array_merge( $session, $data );
        $merged['updated_at'] = gmdate( 'Y-m-d\TH:i:s\Z' );

        $this->storage->write( self::COLLECTION, $sessionId, $merged );
    }

    /**
     * Update the status of a single page within the session.
     */
    public function updatePageStatus(
        string $sessionId,
        string $slug,
        string $status,
        ?string $error = null
    ): void {
        $session = $this->get( $sessionId );

        foreach ( $session['pages'] as &$page ) {
            if ( $page['slug'] === $slug ) {
                $page['status'] = $status;
                if ( $error !== null ) {
                    $page['error'] = $error;
                }
                break;
            }
        }
        unset( $page );

        $session['progress'] = $this->computeProgress( $session['pages'] );
        $session['updated_at'] = gmdate( 'Y-m-d\TH:i:s\Z' );

        $this->storage->write( self::COLLECTION, $sessionId, $session );
    }

    /**
     * Append pages to the session page list.
     */
    public function addPages( string $sessionId, array $pages ): void
    {
        $session = $this->get( $sessionId );

        foreach ( $pages as $page ) {
            $session['pages'][] = array_merge( [
                'original_url' => '',
                'slug'         => '',
                'title'        => '',
                'status'       => 'pending',
                'error'        => null,
            ], $page );
        }

        $session['progress']   = $this->computeProgress( $session['pages'] );
        $session['updated_at'] = gmdate( 'Y-m-d\TH:i:s\Z' );

        $this->storage->write( self::COLLECTION, $sessionId, $session );
    }

    /**
     * List sessions with optional filters.
     */
    public function list( array $filters = [], int $limit = 50 ): array
    {
        return $this->storage->list( self::COLLECTION, $filters, $limit );
    }

    /**
     * Delete a session.
     */
    public function delete( string $sessionId ): bool
    {
        return $this->storage->delete( self::COLLECTION, $sessionId );
    }

    /**
     * Get the progress summary for a session.
     */
    public function getProgress( string $sessionId ): array
    {
        $session = $this->get( $sessionId );

        return [
            'session_id' => $sessionId,
            'source'     => $session['source'],
            'source_url' => $session['source_url'],
            'created_at' => $session['created_at'],
            'status'     => $session['status'],
            'progress'   => $session['progress'],
            'pages'      => $session['pages'],
        ];
    }

    /**
     * Reset a failed page back to pending for retry.
     */
    public function retry( string $sessionId, string $slug ): void
    {
        $this->updatePageStatus( $sessionId, $slug, 'pending', null );
    }

    /**
     * Compute progress counters from the pages array.
     */
    private function computeProgress( array $pages ): array
    {
        $total    = count( $pages );
        $imported = 0;
        $pending  = 0;
        $failed   = 0;

        foreach ( $pages as $page ) {
            match ( $page['status'] ) {
                'imported' => $imported++,
                'pending'  => $pending++,
                'failed'   => $failed++,
                default    => null,
            };
        }

        return compact( 'total', 'imported', 'pending', 'failed' );
    }
}
