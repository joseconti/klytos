<?php

/**
 * Klytos Importer — First activation.
 *
 * Creates the import_sessions collection with a test write/delete
 * to ensure the storage backend is ready.
 */

declare( strict_types=1 );

$storage = klytos_storage();

$testId = 'imp_install_test';
$storage->write( 'import_sessions', $testId, [
    'id'         => $testId,
    'source'     => 'test',
    'status'     => 'completed',
    'created_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
] );
$storage->delete( 'import_sessions', $testId );
