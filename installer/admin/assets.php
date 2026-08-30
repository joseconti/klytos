<?php

/**
 * Klytos Admin — Assets (manifest entry 4, template `gallery-grid`)
 *
 * H1 **Assets**, entry point `assets.php`, gated centrally at `assets.manage`.
 *
 * THE FIRST CONSUMER of `template-gallery-grid.md`, so this screen carries that
 * template's whole §5.3 table.
 *
 * AND IT IS A RE-ARCHITECTURE, NOT A RE-SKIN. The shipped screen rendered
 * everything in the browser — `<!-- Populated by JS -->`, a card that started
 * `hidden`, pagination built in JavaScript, every filter a `<select>` filtered
 * client-side — so with scripting off it showed nothing at all. The template is
 * explicit in §2: "**Loading — server-rendered.** Pagination is a link. There is
 * no infinite scroll anywhere in the admin." Every control on this screen now
 * works with JavaScript disabled, like the twelve screens before it (D-118).
 *
 * The selection itself lives in `AssetManager::query()`, which the JSON endpoint
 * also consumes — one definition of "which assets does this person see", never
 * two (L-004).
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\Helpers;

$pageTitle    = __( 'assets.title' );
$assetManager = $app->getAssetManager();
$siteUrl      = rtrim( (string) $app->getSiteConfig()->getValue( 'site_url', '' ), '/' );
$adminPath    = $adminPath ?? Helpers::getBasePath() . 'admin/';

$success = '';
$error   = '';

/*
 * ─── Actions ─────────────────────────────────────────────────────
 *
 * Every one of them is a real POST with a CSRF token, handled here. They used to
 * be `fetch()` calls from the toolbar into the JSON endpoint, so none of them
 * worked without JavaScript. The endpoint keeps its actions for MCP and for any
 * script that wants them; these are the same operations reachable from a form.
 *
 * A refused CSRF REPORTS itself. Five screens in this build shipped a silent
 * refusal before someone noticed (D-111), and a person who is told nothing
 * assumes the click worked.
 */
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! klytos_verify_csrf() ) {
        $error = __( 'assets.error_csrf' );
    } else {
        $action = klytos_sanitize_key( (string) ( $_POST['action'] ?? '' ) );

        try {
            switch ( $action ) {
                case 'upload':
                    if ( ! isset( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
                        $error = __( 'assets.upload_error' );
                        break;
                    }

                    $data = file_get_contents( $_FILES['file']['tmp_name'] );

                    if ( $data === false ) {
                        $error = __( 'assets.upload_error' );
                        break;
                    }

                    $assetManager->upload(
                        (string) $_FILES['file']['name'],
                        base64_encode( $data ),
                        klytos_sanitize_key( (string) ( $_POST['directory'] ?? 'images' ) )
                    );
                    $success = __( 'assets.upload_success' );
                    break;

                case 'update':
                    $id     = klytos_sanitize_key( (string) ( $_POST['id'] ?? '' ) );
                    $record = $assetManager->getStorage()->read( 'assets', $id );

                    foreach ( ['title', 'alt_text', 'description'] as $field ) {
                        if ( array_key_exists( $field, $_POST ) ) {
                            $record[ $field ] = klytos_sanitize_text( (string) $_POST[ $field ] );
                        }
                    }

                    $record['categories'] = array_map(
                        'strval',
                        array_filter( (array) ( $_POST['categories'] ?? [] ) )
                    );
                    $record['updated_at'] = Helpers::now();

                    $assetManager->getStorage()->write( 'assets', $id, $record );
                    $success = __( 'assets.saved' );
                    break;

                case 'delete':
                    $id     = klytos_sanitize_key( (string) ( $_POST['id'] ?? '' ) );
                    $record = $assetManager->getStorage()->read( 'assets', $id );

                    /*
                     * §4's delta disables delete for an asset in use. **A
                     * disabled control is not a security boundary** — it is a
                     * courtesy to whoever is looking at the screen — so the same
                     * rule is enforced HERE, where a crafted POST arrives. The
                     * shipped JSON endpoint does not do this and deletes
                     * whatever it is given; recorded rather than changed from a
                     * screen slice.
                     */
                    if ( $assetManager->isAssetInUse( $id ) ) {
                        $error = __( 'assets.error_delete_in_use' );
                        break;
                    }

                    $assetManager->delete( (string) ( $record['path'] ?? '' ) );
                    $success = __( 'assets.deleted' );
                    break;

                case 'sync':
                    $synced  = $assetManager->syncExistingAssets();
                    $success = __( 'assets.sync_done', ['count' => (string) $synced] );
                    break;

                case 'rebuild_usage':
                    $assetManager->rebuildUsageIndex();
                    $success = __( 'assets.rebuild_done' );
                    break;

                default:
                    $error = __( 'assets.error_action' );
                    break;
            }
        } catch ( \Throwable $e ) {
            // The manager's own message is not shown: it is English-only on a
            // screen that ships in twenty locales (D-111's shape).
            klytos_log( 'error', 'assets screen action failed: ' . $e->getMessage() );
            $error = __( 'assets.error_action' );
        }
    }
}

/*
 * ─── The listing ─────────────────────────────────────────────────
 */
$filter   = klytos_sanitize_key( (string) ( $_GET['filter'] ?? 'all' ) );
$type     = klytos_sanitize_key( (string) ( $_GET['type'] ?? '' ) );
$category = klytos_sanitize_key( (string) ( $_GET['category'] ?? '' ) );
$search   = klytos_sanitize_text( (string) ( $_GET['search'] ?? '' ) );
$page     = max( 1, (int) ( $_GET['page'] ?? 1 ) );

$result = $assetManager->query( [
    'filter'   => $filter,
    'type'     => $type,
    'category' => $category,
    'search'   => $search,
    'page'     => $page,
    'per_page' => 24,
] );

$assets     = $result['assets'];
$total      = $result['total'];
$pages      = $result['pages'];
$categories = $assetManager->listCategories();

/** True when the listing is empty because of a filter, rather than because nothing is uploaded. */
$isFiltered = $filter !== 'all' || $type !== '' || $category !== '' || $search !== '';

/** The asset whose detail panel is open, if any. */
$openAssetId = klytos_sanitize_key( (string) ( $_GET['asset'] ?? '' ) );
$openAsset   = null;

if ( $openAssetId !== '' ) {
    try {
        $openAsset = $assetManager->getStorage()->read( 'assets', $openAssetId );
    } catch ( \Throwable $e ) {
        $openAsset = null;
    }
}

/** Build a listing URL, preserving every filter the person already chose. */
$listUrl = static function ( array $overrides = [] ) use ( $adminPath, $filter, $type, $category, $search, $page ): string {
    $params = array_merge( [
        'filter'   => $filter,
        'type'     => $type,
        'category' => $category,
        'search'   => $search,
        'page'     => $page,
    ], $overrides );

    // Empty values are dropped so a shared URL says only what was chosen.
    $params = array_filter( $params, static fn( $v ): bool => $v !== '' && $v !== null && $v !== 0 );

    return $adminPath . 'assets.php' . ( $params === [] ? '' : '?' . http_build_query( $params ) );
};

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
klytos_do_action( 'admin.assets.before' );

/*
 * Defined AFTER the shell: `$spriteUrl` and `klytos_admin_icon()` are created by
 * `templates/sidebar.php`, and a closure binds its `use` variables at DEFINITION
 * time — D-110's defect, which turned a whole screen into a 500.
 */

/** The file-kind a tile shows, from the stored MIME type. */
$kindOf = static function ( string $mime ): string {
    if ( str_starts_with( $mime, 'image/' ) ) {
        return 'image';
    }
    if ( str_starts_with( $mime, 'video/' ) ) {
        return 'video';
    }
    if ( str_starts_with( $mime, 'font/' ) ) {
        return 'font';
    }

    return 'document';
};
?>

<?php if ( $success !== '' ) : ?>
    <p class="k-status-line k-status-line--info" role="status" data-testid="assets.success">
        <?php echo klytos_esc_html( $success ); ?>
    </p>
<?php endif; ?>

<?php if ( $error !== '' ) : ?>
    <p class="k-status-line k-status-line--aviso" role="alert" data-testid="assets.error">
        <?php echo klytos_esc_html( $error ); ?>
    </p>
<?php endif; ?>

<?php klytos_do_action( 'admin.assets.before_toolbar' ); ?>

<?php
// ─── The filter row ──────────────────────────────────────────────
//
// §4's chips are All · Images · Video · Documents · Unused. **Fonts is kept as a
// sixth**: it is a shipped control (`assets.php:95` before this rewrite) and
// removing a filter someone may be using is not a fidelity decision — the same
// call as entry 13's *In progress* chip and entry 7's 24h period.
$chips = [
    ['label' => __( 'assets.filter_all' ),   'params' => ['filter' => 'all', 'type' => '', 'page' => 1],      'active' => $filter === 'all' && $type === ''],
    ['label' => __( 'assets.type_image' ),    'params' => ['filter' => 'all', 'type' => 'image', 'page' => 1],    'active' => $type === 'image'],
    ['label' => __( 'assets.type_video' ),    'params' => ['filter' => 'all', 'type' => 'video', 'page' => 1],    'active' => $type === 'video'],
    ['label' => __( 'assets.type_document' ), 'params' => ['filter' => 'all', 'type' => 'document', 'page' => 1], 'active' => $type === 'document'],
    ['label' => __( 'assets.type_font' ),     'params' => ['filter' => 'all', 'type' => 'font', 'page' => 1],     'active' => $type === 'font'],
    ['label' => __( 'assets.unused' ),        'params' => ['filter' => 'unused', 'type' => '', 'page' => 1],   'active' => $filter === 'unused'],
];
?>
<nav class="k-filters" aria-label="<?php echo klytos_esc_attr( __( 'assets.filter_label' ) ); ?>"
     data-testid="assets.filters">
    <?php foreach ( klytos_apply_filters( 'admin.assets.filters', $chips ) as $i => $chip ) : ?>
        <a class="k-chip" href="<?php echo klytos_esc_url( $listUrl( (array) $chip['params'] ) ); ?>"
           <?php echo $chip['active'] ? 'aria-current="true"' : ''; ?>
           data-testid="assets.chip.<?php echo (int) $i; ?>">
            <?php echo klytos_esc_html( (string) $chip['label'] ); ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php // Search and category are a real GET form, so both work with no script. ?>
<form method="get" action="<?php echo klytos_esc_url( $adminPath . 'assets.php' ); ?>"
      class="k-filter-form" data-testid="assets.search_form">
    <input type="hidden" name="filter" value="<?php echo klytos_esc_attr( $filter ); ?>">
    <input type="hidden" name="type" value="<?php echo klytos_esc_attr( $type ); ?>">

    <label class="k-field">
        <span class="k-label"><?php echo klytos_esc_html( __( 'assets.search_label' ) ); ?></span>
        <input class="k-control" type="search" name="search"
               value="<?php echo klytos_esc_attr( $search ); ?>"
               data-testid="assets.search">
    </label>

    <label class="k-field">
        <span class="k-label"><?php echo klytos_esc_html( __( 'assets.categories' ) ); ?></span>
        <select class="k-control" name="category" data-testid="assets.category">
            <option value=""><?php echo klytos_esc_html( __( 'assets.all_categories' ) ); ?></option>
            <?php foreach ( $categories as $cat ) : ?>
                <option value="<?php echo klytos_esc_attr( (string) ( $cat['id'] ?? '' ) ); ?>"
                    <?php echo $category === ( $cat['id'] ?? '' ) ? 'selected' : ''; ?>>
                    <?php echo klytos_esc_html( (string) ( $cat['name'] ?? '' ) ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <button class="k-btn" type="submit"><?php echo klytos_esc_html( __( 'assets.apply' ) ); ?></button>
</form>

<?php // The maintenance actions, as forms rather than fetch() calls. ?>
<div class="k-toolbar" data-testid="assets.maintenance">
    <form method="post" class="k-inline-form">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="sync">
        <button class="k-btn k-btn--sm" type="submit" data-testid="assets.sync">
            <?php echo klytos_esc_html( __( 'assets.sync' ) ); ?>
        </button>
    </form>

    <form method="post" class="k-inline-form">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="rebuild_usage">
        <button class="k-btn k-btn--sm" type="submit" data-testid="assets.rebuild">
            <?php echo klytos_esc_html( __( 'assets.rebuild_usage' ) ); ?>
        </button>
    </form>
</div>
<?php klytos_do_action( 'admin.assets.after_toolbar' ); ?>

<?php
/*
 * ─── The drop zone ───────────────────────────────────────────────
 *
 * Template §1 gives Assets a drop zone, and §2's Drag-over state says "A
 * keyboard user reaches the same thing through 'Choose files', which is always
 * present". The file input IS that control and it is the whole mechanism here:
 * the drag-over and uploading states are client-side by nature and this screen
 * has no script (adaptations 103–104, the same family as 72–73).
 */
?>
<form method="post" enctype="multipart/form-data" class="k-dropzone" data-testid="assets.upload_form">
    <?php echo klytos_csrf_field(); ?>
    <input type="hidden" name="action" value="upload">

    <label class="k-field">
        <span class="k-label"><?php echo klytos_esc_html( __( 'assets.drop_zone_text' ) ); ?></span>
        <input class="k-control" type="file" name="file" required data-testid="assets.file">
    </label>

    <p class="k-hint"><?php echo klytos_esc_html( __( 'assets.max_size' ) ); ?></p>

    <button class="k-btn k-btn--primary" type="submit" data-testid="assets.upload">
        <?php echo klytos_esc_html( __( 'assets.upload' ) ); ?>
    </button>
</form>

<?php // ─── The grid ────────────────────────────────────────────── ?>
<?php if ( $assets === [] ) : ?>
    <p class="k-empty" data-testid="assets.empty">
        <?php klytos_admin_icon( $spriteUrl, 'ks-perm_media', 'k-empty-icon' ); ?>
        <span class="k-empty-text">
            <?php
            // Two DIFFERENT sentences. "Nothing matches your filter" and
            // "nothing has been uploaded" are opposite facts, and a screen that
            // says the second when the first is true sends someone looking for a
            // problem that is not there.
            echo klytos_esc_html(
                $isFiltered ? __( 'assets.empty_filtered' ) : __( 'assets.empty_none' )
            );
            ?>
        </span>
        <?php if ( $isFiltered ) : ?>
            <a href="<?php echo klytos_esc_url( $listUrl( ['filter' => 'all', 'type' => '', 'category' => '', 'search' => '', 'page' => 1] ) ); ?>"
               data-testid="assets.empty_action">
                <?php echo klytos_esc_html( __( 'assets.clear_filters' ) ); ?>
            </a>
        <?php endif; ?>
    </p>
<?php else : ?>
    <section aria-labelledby="assets-grid-heading" data-testid="assets.grid_section">
        <h2 class="k-card-heading" id="assets-grid-heading">
            <?php echo klytos_esc_html( __( 'assets.asset_count', ['count' => (string) $total] ) ); ?>
        </h2>

        <ul class="k-gallery" data-testid="assets.grid">
            <?php foreach ( $assets as $asset ) : ?>
                <?php
                $assetId  = (string) ( $asset['id'] ?? '' );
                $filename = (string) ( $asset['filename'] ?? '' );
                $mime     = (string) ( $asset['mime_type'] ?? '' );
                $kind     = $kindOf( $mime );
                $usage    = (int) ( $asset['usage_count'] ?? 0 );
                $hasAlt   = trim( (string) ( $asset['alt_text'] ?? '' ) ) !== '';
                $url      = $siteUrl . '/' . ltrim( (string) ( $asset['path'] ?? '' ), '/' );
                $detail   = $listUrl( ['asset' => $assetId] );
                ?>
                <li class="k-tile" data-testid="assets.tile.<?php echo klytos_esc_attr( $assetId ); ?>">
                    <?php
                    /*
                     * Template §2 Focus: "the tile is a `<div>` containing a
                     * primary `<a>` plus an actions `<button>`, and each takes
                     * its own ring — never a nested-interactive tile." This tile
                     * has more than one action, so that is the shape used.
                     */
                    ?>
                    <a class="k-tile-primary" href="<?php echo klytos_esc_url( $detail ); ?>"
                       data-testid="assets.tile_link.<?php echo klytos_esc_attr( $assetId ); ?>">
                        <span class="k-tile-preview">
                            <?php if ( $kind === 'image' ) : ?>
                                <?php
                                // The alt is EMPTY on purpose: the filename is
                                // right below it in text, so a description here
                                // would be read twice. A decorative image inside
                                // a labelled link is `alt=""`.
                                ?>
                                <img src="<?php echo klytos_esc_url( $url ); ?>" alt="" loading="lazy"
                                     width="96" height="96">
                            <?php else : ?>
                                <span class="k-tile-glyph" aria-hidden="true">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-description', '' ); ?>
                                </span>
                            <?php endif; ?>

                            <span class="k-tile-pill"><?php echo klytos_esc_html( strtoupper( (string) pathinfo( $filename, PATHINFO_EXTENSION ) ) ); ?></span>
                        </span>

                        <span class="k-tile-name"><?php echo klytos_esc_html( $filename ); ?></span>
                    </a>

                    <p class="k-tile-meta">
                        <span><?php echo klytos_esc_html( (string) ( $asset['size_human'] ?? '' ) ); ?></span>

                        <?php if ( $usage > 0 ) : ?>
                            <?php // §4's delta: the usage count links to the pages. ?>
                            <a href="<?php echo klytos_esc_url( $detail . '#usage' ); ?>"
                               data-testid="assets.usage.<?php echo klytos_esc_attr( $assetId ); ?>">
                                <?php echo klytos_esc_html( __( 'assets.usages', ['count' => (string) $usage] ) ); ?>
                            </a>
                        <?php else : ?>
                            <span data-testid="assets.usage.<?php echo klytos_esc_attr( $assetId ); ?>">
                                <?php echo klytos_esc_html( __( 'assets.not_in_use' ) ); ?>
                            </span>
                        <?php endif; ?>
                    </p>

                    <?php if ( $kind === 'image' && ! $hasAlt ) : ?>
                        <?php
                        // §4's delta: the "No alt text" chip is a LINK to the
                        // asset's alt field. That field is on the detail panel
                        // below, which this screen renders server-side — the
                        // shipped one existed only in JavaScript, so the link
                        // had nowhere to go.
                        ?>
                        <a class="k-chip k-chip--aviso" href="<?php echo klytos_esc_url( $detail . '#alt' ); ?>"
                           data-testid="assets.no_alt.<?php echo klytos_esc_attr( $assetId ); ?>">
                            <?php echo klytos_esc_html( __( 'assets.no_alt_text' ) ); ?>
                        </a>
                    <?php endif; ?>

                    <form method="post" class="k-tile-actions">
                        <?php echo klytos_csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo klytos_esc_attr( $assetId ); ?>">
                        <?php
                        // §4's delta: delete is disabled for an asset in use,
                        // and THE REASON IS IN THE ACCESSIBLE NAME — a disabled
                        // control whose reason is only in a tooltip tells a
                        // screen-reader user nothing (entry 1's rule, D-079).
                        $deleteName = $usage > 0
                            ? __( 'assets.delete_blocked', ['file' => $filename, 'count' => (string) $usage] )
                            : __( 'assets.delete_named', ['file' => $filename] );
                        ?>
                        <button class="k-btn k-btn--destructive k-btn--sm" type="submit"
                                aria-label="<?php echo klytos_esc_attr( $deleteName ); ?>"
                                <?php echo $usage > 0 ? 'disabled' : ''; ?>
                                data-testid="assets.delete.<?php echo klytos_esc_attr( $assetId ); ?>">
                            <?php echo klytos_esc_html( __( 'assets.delete' ) ); ?>
                        </button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ( $pages > 1 ) : ?>
            <?php // Pagination is LINKS — template §2: "Pagination is a link." ?>
            <nav class="k-pagination" aria-label="<?php echo klytos_esc_attr( __( 'assets.pagination_label' ) ); ?>"
                 data-testid="assets.pagination">
                <?php for ( $p = 1; $p <= $pages; $p++ ) : ?>
                    <a href="<?php echo klytos_esc_url( $listUrl( ['page' => $p] ) ); ?>"
                       <?php echo $p === $page ? 'aria-current="page"' : ''; ?>
                       data-testid="assets.page.<?php echo (int) $p; ?>">
                        <?php echo (int) $p; ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php // ─── The detail panel, server-rendered ──────────────────── ?>
<?php if ( $openAsset !== null ) : ?>
    <section class="k-card k-card--padded" aria-labelledby="asset-detail-heading" data-testid="assets.detail">
        <div class="k-card-body">
            <h2 class="k-card-heading" id="asset-detail-heading">
                <?php echo klytos_esc_html( (string) ( $openAsset['filename'] ?? '' ) ); ?>
            </h2>

            <form method="post">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo klytos_esc_attr( $openAssetId ); ?>">

                <label class="k-field">
                    <span class="k-label"><?php echo klytos_esc_html( __( 'assets.field_title' ) ); ?></span>
                    <input class="k-control" type="text" name="title"
                           value="<?php echo klytos_esc_attr( (string) ( $openAsset['title'] ?? '' ) ); ?>"
                           data-testid="assets.detail_title">
                </label>

                <?php // `id="alt"` is the "No alt text" chip's destination. ?>
                <label class="k-field" id="alt">
                    <span class="k-label"><?php echo klytos_esc_html( __( 'assets.field_alt' ) ); ?></span>
                    <input class="k-control" type="text" name="alt_text"
                           value="<?php echo klytos_esc_attr( (string) ( $openAsset['alt_text'] ?? '' ) ); ?>"
                           data-testid="assets.detail_alt">
                </label>

                <label class="k-field">
                    <span class="k-label"><?php echo klytos_esc_html( __( 'assets.field_description' ) ); ?></span>
                    <textarea class="k-control" name="description" rows="3"
                              data-testid="assets.detail_description"><?php echo klytos_esc_textarea( (string) ( $openAsset['description'] ?? '' ) ); ?></textarea>
                </label>

                <button class="k-btn k-btn--primary" type="submit" data-testid="assets.detail_save">
                    <?php echo klytos_esc_html( __( 'assets.save' ) ); ?>
                </button>
            </form>

            <?php $usageRecords = $assetManager->getUsage( $openAssetId ); ?>
            <h3 class="k-card-heading" id="usage"><?php echo klytos_esc_html( __( 'assets.used_in' ) ); ?></h3>

            <?php if ( $usageRecords === [] ) : ?>
                <p class="k-empty">
                    <span class="k-empty-text"><?php echo klytos_esc_html( __( 'assets.not_in_use' ) ); ?></span>
                </p>
            <?php else : ?>
                <table class="k-table k-assets-table" data-testid="assets.usage_table">
                    <caption class="k-table-caption"><?php echo klytos_esc_html( __( 'assets.used_in' ) ); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo klytos_esc_html( __( 'assets.context' ) ); ?></th>
                            <th scope="col"><?php echo klytos_esc_html( __( 'assets.field' ) ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $usageRecords as $usageRecord ) : ?>
                            <tr>
                                <th scope="row">
                                    <?php
                                    $contextType  = (string) ( $usageRecord['context_type'] ?? '' );
                                    $contextId    = (string) ( $usageRecord['context_id'] ?? '' );
                                    $contextLabel = (string) ( $usageRecord['context_label'] ?? $contextId );
                                    ?>
                                    <?php if ( $contextType === 'page' && $contextId !== '' ) : ?>
                                        <a href="<?php echo klytos_esc_url( $adminPath . 'page-editor.php?slug=' . rawurlencode( $contextId ) ); ?>">
                                            <?php echo klytos_esc_html( $contextLabel ); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo klytos_esc_html( $contextLabel ); ?>
                                    <?php endif; ?>
                                </th>
                                <td><?php echo klytos_esc_html( (string) ( $usageRecord['field'] ?? '' ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<style nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
/*
 * `grid-template-columns` is PER SCREEN (template-list-table.md §1) and §4
 * records none for the usage table — DR-006's gap on its seventeenth surface,
 * covered by the same addendum. Content-driven, replaced verbatim when DR-006
 * answers. Adaptation 105.
 */
.k-assets-table tr:not(.k-table-row-full) {
    grid-template-columns: minmax(0, 1fr) max-content;
}
</style>

<?php klytos_do_action( 'admin.assets.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
