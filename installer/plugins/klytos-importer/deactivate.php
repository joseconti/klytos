<?php

/**
 * Klytos Importer — Deactivation.
 *
 * Marks any in-progress import sessions as cancelled.
 */

declare( strict_types=1 );

$storage  = klytos_storage();
$sessions = $storage->list( 'import_sessions', ['status' => 'in_progress'] );

foreach ( $sessions as $session ) {
    $session['status']     = 'cancelled';
    $session['updated_at'] = gmdate( 'Y-m-d\TH:i:s\Z' );
    $storage->write( 'import_sessions', $session['id'], $session );
}
