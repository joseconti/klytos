<?php

/**
 * Klytos Admin — Blocks (manifest entry 21, template `gallery-grid`)
 *
 * H1 **Blocks**, entry point `blocks.php`, gated centrally at `design.manage`.
 *
 * The SECOND consumer of `template-gallery-grid.md`, built once on entry 4
 * (D-118). The tile, the grid and the empty state are that layer's; what differs
 * is the preview — §1 gives blocks a **wireframe** where assets get a real
 * thumbnail — and the grouping, which §21 requires by category with each group
 * carrying its own `<h2>` and its own labelled `<ul>`.
 *
 * THE SURVEY FINDING: §21's tile draws a **usage count** and `BlockManager`
 * tracks no usage of any kind. Unlike entry 13's due dates or entry 18's
 * settlement lag, the figure is not unmeasurable — a page TEMPLATE holds block
 * ids, and that is the relationship the product stores. So it is counted, and
 * **the screen says what it counts** (templates, not pages) rather than letting
 * the tile imply one and mean the other. **DR-016** asks which §21 intends.
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

use Klytos\Core\BlockManager;
use Klytos\Core\Helpers;

$pageTitle    = __( 'blocks.heading' );
$blockManager = new BlockManager( $app->getStorage() );
$adminPath    = $adminPath ?? Helpers::getBasePath() . 'admin/';

$error       = '';
$previewHtml = '';
$previewId   = klytos_sanitize_key( (string) ( $_GET['preview'] ?? '' ) );

if ( $previewId !== '' ) {
    try {
        $previewHtml = $blockManager->render( $previewId );
    } catch ( \Throwable $e ) {
        // The manager's message is English-only on a screen that ships in
        // twenty locales, so it is logged and a translated line is shown
        // (D-111's shape).
        klytos_log( 'error', 'block preview failed: ' . $e->getMessage() );
        $error = __( 'blocks.error_preview' );
    }
}

$allBlocks = $blockManager->list();

/*
 * The usage count, from ONE traversal of the templates rather than one per tile.
 * A block nothing uses is absent from the map, so zero is drawn by this screen
 * and never invented by the manager.
 */
$usageCounts = $app->getPageTemplateManager()->blockUsageCounts();

// §21: "Grouped by category, each group an <h2> + its own labelled <ul>".
$grouped = [];
foreach ( $allBlocks as $block ) {
    $grouped[ (string) ( $block['category'] ?? 'custom' ) ][] = $block;
}

/*
 * The order is the product's own, and the labels are CATALOGUE KEYS.
 *
 * The shipped screen hard-coded `'Structure'`, `'Content'`, `'Interaction'`,
 * `'Social Proof'` and `'Custom'` in English on a product with twenty
 * catalogues — the same defect as the Forms plugin's Spanish sidebar (D-116),
 * on the opposite side.
 */
$categoryOrder = ['structure', 'content', 'interaction', 'social-proof', 'custom'];

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
klytos_do_action( 'admin.blocks.before' );

/*
 * Defined AFTER the shell: `$spriteUrl` and `klytos_admin_icon()` are created by
 * `templates/sidebar.php`, and a closure binds its `use` variables at DEFINITION
 * time — D-110's defect, which turned a whole screen into a 500.
 */

/** A category's label, falling back to the raw id so a plugin's own category still reads. */
$categoryLabel = static function ( string $category ): string {
    $key   = 'blocks.category_' . str_replace( '-', '_', $category );
    $label = __( $key );

    return $label === $key ? $category : $label;
};
?>

<?php if ( $error !== '' ) : ?>
    <p class="k-status-line k-status-line--aviso" role="alert" data-testid="blocks.error">
        <?php echo klytos_esc_html( $error ); ?>
    </p>
<?php endif; ?>

<?php if ( $previewHtml !== '' ) : ?>
    <section class="k-card k-card--padded" aria-labelledby="block-preview-heading" data-testid="blocks.preview">
        <div class="k-card-body">
            <h2 class="k-card-heading" id="block-preview-heading">
                <?php echo klytos_esc_html( __( 'blocks.preview_of', ['block' => $previewId] ) ); ?>
            </h2>

            <?php // The block's own HTML, filtered through KSES: it is authored
                  // content and this screen is not the place to widen what may
                  // be rendered. ?>
            <div class="k-block-preview"><?php echo klytos_kses_post( $previewHtml ); ?></div>

            <a class="k-btn" href="<?php echo klytos_esc_url( $adminPath . 'blocks.php' ); ?>"
               data-testid="blocks.preview_close">
                <?php echo klytos_esc_html( __( 'common.close' ) ); ?>
            </a>
        </div>
    </section>
<?php endif; ?>

<?php if ( $allBlocks === [] ) : ?>
    <?php // §21's empty state, quoted, with its action. ?>
    <p class="k-empty" data-testid="blocks.empty">
        <?php klytos_admin_icon( $spriteUrl, 'ks-category', 'k-empty-icon' ); ?>
        <span class="k-empty-text"><?php echo klytos_esc_html( __( 'blocks.empty_sentence' ) ); ?></span>
        <a href="<?php echo klytos_esc_url( $adminPath . 'plugins.php' ); ?>" data-testid="blocks.empty_action">
            <?php echo klytos_esc_html( __( 'blocks.open_plugins' ) ); ?>
        </a>
    </p>
<?php else : ?>
    <?php
    // Every category the product knows, then anything a plugin invented, so a
    // custom category is never silently dropped from the screen.
    $categories = array_merge(
        array_values( array_intersect( $categoryOrder, array_keys( $grouped ) ) ),
        array_values( array_diff( array_keys( $grouped ), $categoryOrder ) )
    );

    foreach ( klytos_apply_filters( 'admin.blocks.categories', $categories, $grouped ) as $category ) :
        $blocks = $grouped[ $category ] ?? [];

        if ( $blocks === [] ) {
            continue;
        }

        $headingId = 'blocks-cat-' . klytos_sanitize_key( $category );
        ?>
        <section class="k-gallery-group" aria-labelledby="<?php echo klytos_esc_attr( $headingId ); ?>"
                 data-testid="blocks.group.<?php echo klytos_esc_attr( $category ); ?>">
            <h2 class="k-card-heading" id="<?php echo klytos_esc_attr( $headingId ); ?>">
                <?php echo klytos_esc_html( $categoryLabel( (string) $category ) ); ?>
            </h2>

            <?php // §21: each group carries its OWN labelled list, so a screen
                  // reader can move between them and know which it is in. ?>
            <ul class="k-gallery" aria-labelledby="<?php echo klytos_esc_attr( $headingId ); ?>">
                <?php foreach ( $blocks as $block ) : ?>
                    <?php
                    $blockId = (string) ( $block['id'] ?? '' );
                    $name    = (string) ( $block['name'] ?? $blockId );
                    $usage   = (int) ( $usageCounts[ $blockId ] ?? 0 );
                    ?>
                    <li class="k-tile" data-testid="blocks.tile.<?php echo klytos_esc_attr( $blockId ); ?>">
                        <a class="k-tile-primary"
                           href="<?php echo klytos_esc_url( $adminPath . 'blocks.php?preview=' . rawurlencode( $blockId ) ); ?>"
                           data-testid="blocks.tile_link.<?php echo klytos_esc_attr( $blockId ); ?>">
                            <?php
                            /*
                             * §1: "Previews are wireframes for blocks and
                             * templates (grey rectangles at the real
                             * proportions)". A wireframe is decoration — it
                             * carries no information the name and meta do not —
                             * so it is `aria-hidden` and the link is named by
                             * its text.
                             */
                            ?>
                            <span class="k-tile-preview k-tile-preview--wireframe" aria-hidden="true">
                                <span class="k-wireframe">
                                    <span class="k-wireframe-bar k-wireframe-bar--wide"></span>
                                    <span class="k-wireframe-bar"></span>
                                    <span class="k-wireframe-bar k-wireframe-bar--short"></span>
                                </span>
                            </span>

                            <span class="k-tile-name"><?php echo klytos_esc_html( $name ); ?></span>
                        </a>

                        <p class="k-tile-meta">
                            <span><?php echo klytos_esc_html( $categoryLabel( (string) $category ) ); ?></span>
                            <span data-testid="blocks.usage.<?php echo klytos_esc_attr( $blockId ); ?>">
                                <?php
                                /*
                                 * The label says TEMPLATES, not a bare "usages".
                                 * §21 does not say which it means and the two
                                 * are different facts — a block in one template
                                 * may render on fifty pages. Naming what is
                                 * counted is the honest form while DR-016 is
                                 * open.
                                 */
                                echo klytos_esc_html(
                                    $usage === 0
                                        ? __( 'blocks.in_no_template' )
                                        : __( 'blocks.in_templates', ['count' => (string) $usage] )
                                );
                                ?>
                            </span>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php klytos_do_action( 'admin.blocks.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
