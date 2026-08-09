<?php

/**
 * Klytos CMS — remove every post type this tier created (manifest entry 19).
 *
 * A form screen's tests mutate stored state by definition. `design.spec.js`
 * restores the seeded palette through the product's own writer; entry 19
 * CREATES records rather than editing one, so the equivalent is to delete what
 * the run created — through the manager, never by unlinking a storage file,
 * so a delete that has stopped working also shows up here.
 *
 * Only ids beginning `e2e-` are touched. The seeded `page` type is built in and
 * the manager refuses it anyway; anything else on the playground belongs to
 * whoever put it there.
 *
 * Usage:  php tests/E2E/fixtures/reset-content-model.php
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

$app = \Klytos\Core\App::getInstance();
$app->boot();

$manager = $app->getPostTypeManager();
$removed = [];

foreach ( $manager->list() as $postType ) {
    $id = (string) ( $postType['id'] ?? '' );
    if ( $id === '' || strpos( $id, 'e2e-' ) !== 0 ) {
        continue;
    }
    $manager->delete( $id );
    $removed[] = $id;
}

echo json_encode( $removed );
echo "\n";
