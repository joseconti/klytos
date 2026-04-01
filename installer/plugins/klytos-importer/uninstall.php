<?php

/**
 * Klytos Importer — Uninstall.
 *
 * Removes all import session data.
 */

declare( strict_types=1 );

$storage  = klytos_storage();
$sessions = $storage->list( 'import_sessions', [] );

foreach ( $sessions as $session ) {
    $storage->delete( 'import_sessions', $session['id'] );
}

// Remove plugin options.
klytos_delete_option( 'klytos_importer.settings' );
