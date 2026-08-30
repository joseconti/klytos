<?php

/**
 * Klytos CMS — seed a known asset population for manifest entry 4.
 *
 * The gallery, its filters, the alt-text chip, the usage count and the
 * delete-gating are all functions of the `assets` and `asset-usage`
 * collections. Against an empty library the screen renders its empty state and
 * **none of them can be asserted** — a check over a zero population is not
 * evidence (D-079).
 *
 * Everything goes through the REAL `AssetManager::upload()` and
 * `trackUsage()`, so whatever the product does on the way in happens here too:
 * the MIME sniffing, the metadata record, the id. That matters more here than
 * on the other fixtures in this tier, because entry 4's whole tile is derived
 * from what the writer chose to store.
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-assets.php          seed
 *   php tests/E2E/fixtures/reset-assets.php --off    remove
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

$app = \Klytos\Core\App::getInstance();
$app->boot();

$assets  = $app->getAssetManager();
$storage = $app->getStorage();

/** Every file this fixture writes carries the prefix, so removal is exact. */
const PREFIX = 'e2e-asset-';

/*
 * Remove whatever a previous run left, by the FILENAMES this fixture owns.
 *
 * `listWithIds()` gives the storage id back (D-115), so the metadata record is
 * addressable; the physical file goes through the manager's own `delete()`, so
 * the usage index is cleaned up the way the product does it.
 */
$removed = 0;
foreach ( $storage->listWithIds( 'assets' ) as $id => $record ) {
    if ( str_starts_with( (string) ( $record['filename'] ?? '' ), PREFIX ) ) {
        $assets->delete( (string) ( $record['path'] ?? '' ) );
        $storage->delete( 'assets', (string) $id );
        $removed++;
    }
}

if ( in_array( '--off', $argv, true ) ) {
    /*
     * Take the DIRECTORIES back too, not just the files.
     *
     * `AssetManager::upload()` creates `assets/images/` and `assets/files/` under
     * the web root on first use, and `AdminGateHttpTest` asserts the suite leaves
     * no build output in the repository root — NEW-04, and it caught this fixture
     * the first time the whole suite ran after it existed.
     *
     * `rmdir()` succeeds only on an EMPTY directory, which is exactly the safety
     * wanted here: an install that already had a library keeps it, and this
     * fixture removes only what nothing else is using.
     */
    $assetsDir = rtrim( $assets->getAssetsDir(), '/' );

    /*
     * Uploads land in DATED subdirectories (`images/2026/08/`), so the prune
     * walks upwards from the deepest and stops the moment a directory is not
     * empty. `rmdir()` succeeds only on an empty one, which is the safety: the
     * root also holds `css/` and `js/` that belong to the build, and an install
     * with a real library keeps every one of its folders.
     */
    $prune = static function ( string $dir ) use ( $assetsDir ): void {
        $dir = rtrim( $dir, '/' );

        while ( $dir !== $assetsDir && str_starts_with( $dir, $assetsDir ) && is_dir( $dir ) ) {
            if ( ! @rmdir( $dir ) ) {
                return; // Not empty — something else lives here.
            }

            $dir = dirname( $dir );
        }
    };

    foreach ( ['images', 'files'] as $sub ) {
        foreach ( (array) glob( $assetsDir . '/' . $sub . '/*/*', GLOB_ONLYDIR ) as $dated ) {
            $prune( $dated );
        }

        @rmdir( $assetsDir . '/' . $sub );
    }

    // The root itself only if this fixture is the only thing that ever used it.
    @rmdir( $assetsDir );

    echo "reset-assets: removed {$removed} seeded asset(s)\n";
    exit( 0 );
}

/** A 1x1 PNG — the smallest thing that is really an image to a MIME sniffer. */
$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
);

/*
 * The population, chosen so every assertion on the screen has an unambiguous
 * answer:
 *
 *   - THREE kinds, so the Images / Video / Documents chips each match something
 *     different and none of them can pass by matching everything.
 *   - one image WITH alt text and one WITHOUT, so the "No alt text" chip is
 *     asserted to appear on exactly one tile rather than on all or none.
 *   - one asset IN USE and the rest not, so `Unused` excludes exactly one and
 *     the disabled delete has both branches on the same page.
 */
$plan = [
    ['name' => PREFIX . 'used.png',     'data' => $png,                 'dir' => 'images', 'alt' => 'A used image', 'used' => true],
    ['name' => PREFIX . 'no-alt.png',   'data' => $png,                 'dir' => 'images', 'alt' => '',             'used' => false],
    ['name' => PREFIX . 'clip.mp4',     'data' => "\x00\x00\x00\x18ftypmp42",  'dir' => 'files', 'alt' => '',      'used' => false],
    ['name' => PREFIX . 'terms.pdf',    'data' => "%PDF-1.4\n%test\n",  'dir' => 'files',  'alt' => '',             'used' => false],
];

$written = 0;
foreach ( $plan as $item ) {
    $result = $assets->upload( $item['name'], base64_encode( $item['data'] ), $item['dir'] );

    $record = $assets->findAssetByPath( (string) ( $result['path'] ?? '' ) );

    if ( $record === null ) {
        fwrite( STDERR, "reset-assets: no metadata record for {$item['name']}\n" );
        exit( 1 );
    }

    $assetId = (string) $record['id'];

    if ( $item['alt'] !== '' ) {
        $record['alt_text'] = $item['alt'];
        $storage->write( 'assets', $assetId, $record );
    }

    if ( $item['used'] ) {
        $assets->trackUsage( $assetId, 'page', 'home', 'Home', 'content_html' );
    }

    $written++;
}

echo "reset-assets: removed {$removed}, wrote {$written} assets\n";
echo "  kinds     : 2 images, 1 video, 1 document\n";
echo "  alt text  : 1 image has it, 1 does not\n";
echo "  in use    : 1 (on the page 'home'); 3 unused\n";
