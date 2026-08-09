<?php

/**
 * Klytos Admin — Content model
 *
 * Manifest entry 19 · template `record-form` · H1 "Content model".
 * Built in Phase 4 Step 4, stage 5 (the form screens), batch B, against
 * `SPEC/screens/template-record-form.md` and `SPEC/manifest.md` §19.
 *
 * The manifest lists three cards — Post types (list) · Taxonomies (list) ·
 * Statuses (editable set) — and one delta: "each list row links to its own
 * screen (Post type, Taxonomies). This screen creates and orders; it does not
 * edit."
 *
 * TWO of those three are built here. What is NOT built, and why, applying the
 * user's standing answer 1 of D-088 (an unbacked card is deferred to its own
 * slice, never invented):
 *
 *   - **Statuses (editable set)** — there is no global, editable status set in
 *     this product. The four system statuses are class CONSTANTS
 *     (`PostTypeManager::SYSTEM_STATUS_DEFS`) and every custom status belongs
 *     to one post type (`addStatus( $postTypeId, … )`). Entry 39 (Post type)
 *     names the same card at the level where it IS backed, and
 *     `post-type-edit.php` already manages it there. A global editable set is
 *     a new product surface, so it is a slice — `docs/roadmap.md` §0c.
 *
 *   - **"and orders"** — nothing in this product orders post types or
 *     taxonomies. `position` exists on CUSTOM FIELDS alone, and the only
 *     reorder surfaces are `reorderCustomFields()` and `reorderStatuses()`.
 *     Ordering is therefore a manager change, not a card — same deferral.
 *
 * Both stay manifest rows: the redesign is not reportable as complete while
 * they stand.
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

$pageTitle   = __( 'content_model.title' );
$currentPage = 'post-types';
$auth        = $app->getAuth();
$ptManager   = $app->getPostTypeManager();
$pageManager = $app->getPages();

$success = '';

/** @var array<string,string> Field-level errors, keyed by control name. */
$fieldErrors = [];
/** @var array<int,array{name:string,message:string}> The error summary's rows, in DOM order. */
$summaryRows = [];

/**
 * The values the add forms RENDER after a refused save.
 *
 * A rejected form is re-drawn with what the person typed, never blanked back
 * to the defaults: discarding their work is how a validation message becomes
 * a reason to give up (template-record-form.md §2, "Error — field level").
 */
$ptDraft  = [ 'id' => '', 'name' => '', 'slug' => '', 'editor' => 'gutenberg' ];
$taxDraft = [ 'post_type' => '', 'id' => '', 'name' => '', 'slug' => '', 'hierarchical' => false ];

/**
 * The row awaiting its second click, if any.
 *
 * §2 "Destructive section": the button becomes "Confirm delete — …" on first
 * click. It is implemented entirely on the SERVER — the first click posts, the
 * row re-renders armed, the second click deletes — so it behaves identically
 * with JavaScript disabled. A two-step confirm that exists in script alone is
 * a one-step confirm for anyone without it, which is the case §2's "never a
 * browser confirm()" is really about.
 *
 * @var array{kind:string,post_type:string,id:string}|null
 */
$pendingDelete = null;

/** Which card a failed submission belongs to, so only that card re-opens its form. */
$openForm = '';

// ─── Handle POST ────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $action = (string) ( $_POST['action'] ?? '' );

    try {
        if ( $action === 'create_post_type' ) {
            $openForm = 'post_type';

            $ptDraft['id']     = trim( (string) ( $_POST['id'] ?? '' ) );
            $ptDraft['name']   = trim( (string) ( $_POST['name'] ?? '' ) );
            $ptDraft['slug']   = trim( (string) ( $_POST['slug'] ?? '' ) );
            $ptDraft['editor'] = (string) ( $_POST['editor'] ?? 'gutenberg' );

            if ( ! in_array( $ptDraft['editor'], [ 'gutenberg', 'tinymce' ], true ) ) {
                $ptDraft['editor'] = 'gutenberg';
            }

            if ( $ptDraft['id'] === '' ) {
                $fieldErrors['pt_id'] = __( 'content_model.error_id_required' );
                $summaryRows[]        = [ 'name' => 'pt_id', 'message' => __( 'content_model.summary_id_required' ) ];
            } elseif ( Helpers::sanitizeSlug( $ptDraft['id'] ) !== strtolower( $ptDraft['id'] ) ) {
                $fieldErrors['pt_id'] = __( 'content_model.error_id_format' );
                $summaryRows[]        = [ 'name' => 'pt_id', 'message' => __( 'content_model.summary_id_format' ) ];
            }

            if ( $ptDraft['name'] === '' ) {
                $fieldErrors['pt_name'] = __( 'content_model.error_name_required' );
                $summaryRows[]          = [ 'name' => 'pt_name', 'message' => __( 'content_model.summary_name_required' ) ];
            }

            if ( $ptDraft['slug'] === '' ) {
                $fieldErrors['pt_slug'] = __( 'content_model.error_slug_required' );
                $summaryRows[]          = [ 'name' => 'pt_slug', 'message' => __( 'content_model.summary_slug_required' ) ];
            }

            if ( $summaryRows === [] ) {
                $ptManager->create( [
                    'id'     => $ptDraft['id'],
                    'name'   => $ptDraft['name'],
                    'slug'   => $ptDraft['slug'],
                    'editor' => $ptDraft['editor'],
                ] );
                $success  = __( 'content_model.saved_post_type' );
                $ptDraft  = [ 'id' => '', 'name' => '', 'slug' => '', 'editor' => 'gutenberg' ];
                $openForm = '';
            }
        } elseif ( $action === 'create_taxonomy' ) {
            $openForm = 'taxonomy';

            $taxDraft['post_type']    = trim( (string) ( $_POST['post_type'] ?? '' ) );
            $taxDraft['id']           = trim( (string) ( $_POST['tax_id'] ?? '' ) );
            $taxDraft['name']         = trim( (string) ( $_POST['tax_name'] ?? '' ) );
            $taxDraft['slug']         = trim( (string) ( $_POST['tax_slug'] ?? '' ) );
            $taxDraft['hierarchical'] = isset( $_POST['hierarchical'] );

            if ( $taxDraft['post_type'] === '' || ! $ptManager->exists( $taxDraft['post_type'] ) ) {
                $fieldErrors['tax_post_type'] = __( 'content_model.error_post_type_required' );
                $summaryRows[]                = [ 'name' => 'tax_post_type', 'message' => __( 'content_model.summary_post_type_required' ) ];
            }

            if ( $taxDraft['id'] === '' ) {
                $fieldErrors['tax_id'] = __( 'content_model.error_id_required' );
                $summaryRows[]         = [ 'name' => 'tax_id', 'message' => __( 'content_model.summary_tax_id_required' ) ];
            } elseif ( Helpers::sanitizeSlug( $taxDraft['id'] ) !== strtolower( $taxDraft['id'] ) ) {
                $fieldErrors['tax_id'] = __( 'content_model.error_id_format' );
                $summaryRows[]         = [ 'name' => 'tax_id', 'message' => __( 'content_model.summary_tax_id_format' ) ];
            }

            if ( $taxDraft['name'] === '' ) {
                $fieldErrors['tax_name'] = __( 'content_model.error_name_required' );
                $summaryRows[]           = [ 'name' => 'tax_name', 'message' => __( 'content_model.summary_tax_name_required' ) ];
            }

            if ( $summaryRows === [] ) {
                $ptManager->addTaxonomy( $taxDraft['post_type'], [
                    'id'           => $taxDraft['id'],
                    'name'         => $taxDraft['name'],
                    // The manager already defaults an empty slug to the id;
                    // repeating that here would be a second implementation of
                    // one rule, which is how the two drift.
                    'slug'         => $taxDraft['slug'],
                    'hierarchical' => $taxDraft['hierarchical'],
                ] );
                $success  = __( 'content_model.saved_taxonomy' );
                $taxDraft = [ 'post_type' => '', 'id' => '', 'name' => '', 'slug' => '', 'hierarchical' => false ];
                $openForm = '';
            }
        } elseif ( $action === 'confirm_delete_post_type' ) {
            // First click, no JavaScript: re-render with this row armed.
            $pendingDelete = [
                'kind'      => 'post_type',
                'post_type' => '',
                'id'        => trim( (string) ( $_POST['id'] ?? '' ) ),
            ];
        } elseif ( $action === 'confirm_delete_taxonomy' ) {
            $pendingDelete = [
                'kind'      => 'taxonomy',
                'post_type' => trim( (string) ( $_POST['post_type'] ?? '' ) ),
                'id'        => trim( (string) ( $_POST['tax_id'] ?? '' ) ),
            ];
        } elseif ( $action === 'delete_post_type' ) {
            $deleteId = trim( (string) ( $_POST['id'] ?? '' ) );
            // The manager refuses the built-in page type itself; this branch
            // exists so the refusal is a sentence rather than an exception
            // message, and the button is not rendered for it at all.
            $ptManager->delete( $deleteId );
            $success = __( 'content_model.deleted_post_type' );
        } elseif ( $action === 'delete_taxonomy' ) {
            $ptManager->removeTaxonomy(
                trim( (string) ( $_POST['post_type'] ?? '' ) ),
                trim( (string) ( $_POST['tax_id'] ?? '' ) )
            );
            $success = __( 'content_model.deleted_taxonomy' );
        }
    } catch ( \Throwable $e ) {
        /*
         * §2 "Error — the save failed for a server reason": the summary names
         * the cause and the action, never a code alone. The manager's message
         * is the cause; it is escaped at print time like any other value.
         */
        $summaryRows[] = [ 'name' => '', 'message' => $e->getMessage() ];
    }
}

// ─── Read the model ─────────────────────────────────────────────
$postTypes = $ptManager->list();

/**
 * The Taxonomies card's rows.
 *
 * Taxonomies are stored INSIDE their post type, so the flat list this card
 * draws is an aggregation rather than a collection of its own. Each row
 * therefore carries the post type it belongs to — without it the link to
 * `taxonomy.php` cannot be built, and two post types may legitimately hold a
 * taxonomy of the same id.
 *
 * @var array<int,array{post_type:string,post_type_name:string,id:string,name:string,slug:string,hierarchical:bool,terms:int}>
 */
$taxonomyRows = [];
foreach ( $postTypes as $pt ) {
    $ptId = (string) ( $pt['id'] ?? '' );
    foreach ( $pt['taxonomies'] ?? [] as $tax ) {
        $taxId = (string) ( $tax['id'] ?? '' );
        if ( $taxId === '' ) {
            continue;
        }
        $taxonomyRows[] = [
            'post_type'      => $ptId,
            'post_type_name' => (string) ( $pt['name'] ?? $ptId ),
            'id'             => $taxId,
            'name'           => (string) ( $tax['name'] ?? $taxId ),
            'slug'           => (string) ( $tax['slug'] ?? $taxId ),
            'hierarchical'   => ! empty( $tax['hierarchical'] ),
            'terms'          => count( $ptManager->listTerms( $ptId, $taxId ) ),
        ];
    }
}

/** Third parties reorder, filter or extend both collections from outside. */
$postTypes    = klytos_apply_filters( 'admin.content_model.post_types', $postTypes );
$taxonomyRows = klytos_apply_filters( 'admin.content_model.taxonomies', $taxonomyRows );

/**
 * A field's `aria-describedby`, hint FIRST and error second (§4).
 *
 * Written once rather than inline per control: six copies of one ternary is
 * six chances for the order to differ, and the order is the specified part.
 */
/** The two editors the manager accepts, in the order the shipped screen drew them. */
$editorChoices = [
    'gutenberg' => 'content_model.editor_gutenberg',
    'tinymce'   => 'content_model.editor_tinymce',
];

$describedBy = static function ( string $field ) use ( &$fieldErrors ): string {
    $ids = [ 'content-model-hint-' . $field ];
    if ( isset( $fieldErrors[ $field ] ) ) {
        $ids[] = 'content-model-error-' . $field;
    }
    return implode( ' ', $ids );
};

// $spriteUrl is set by templates/sidebar.php, which is required below — the
// one definition every ported screen draws from. A second one here would be a
// second source of truth for the sprite's path, which is L-030's family.
$csrf = $auth->getCsrfToken();

/**
 * How many records a post type would leave behind.
 *
 * §2's example sentence is "Confirm delete — 34 records will be deleted", and
 * in THIS product that sentence would be false: `PostTypeManager::delete()`
 * removes the type and its term data and leaves the records themselves in
 * place. The shape the template specifies is kept — the same button relabelled
 * with the consequence — and the consequence stated is the one that is true.
 * Recorded as an adaptation rather than silently rephrased.
 */
$recordCount = static function ( string $postTypeId ) use ( $pageManager ): int {
    try {
        return $pageManager->count( 'all', $postTypeId );
    } catch ( \Throwable $e ) {
        // A count that cannot be taken is not a count of zero. The sentence
        // drops the number rather than asserting one (L-034).
        return -1;
    }
};

/*
 * template-record-form.md §1 puts the primary Save in the toolbar on every
 * form screen. This screen deliberately renders NO toolbar Save, and that is
 * an adaptation with a reason rather than an omission:
 *
 * entry 19 edits nothing — the manifest says so in its own delta ("it creates
 * and orders; it does not edit") — and the one card that would have carried
 * savable fields is the Statuses set, deferred above. What is left is two
 * collections whose actions are their own, in the card footer §1 provides for.
 * A toolbar Save here would submit nothing: a control that lies about what it
 * does is worse than a control that is absent.
 *
 * Restoring it is one block, at the moment the Statuses card lands.
 */

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.post_types.before' ); ?>

<?php if ( $success !== '' ) : ?>
    <?php // §2 Success: "the page reloads with a role="status" line under the H1". ?>
    <p class="k-status-line" role="status" data-testid="content_model.status_line">
        <?php echo klytos_esc_html( $success ); ?>
    </p>
<?php endif; ?>

<?php if ( $summaryRows !== [] ) : ?>
    <?php /*
     * §2 Error — form level: an error summary at the top of main, role="alert",
     * focus moved to it on load, every failed field a link to that field.
     * tabindex="-1" makes it focusable without putting it in the tab order.
     */ ?>
    <div class="k-error-summary"
         id="content-model-error-summary"
         role="alert"
         tabindex="-1"
         data-testid="content_model.error_summary">
        <h2><?php echo klytos_esc_html( __( 'content_model.summary_title' ) ); ?></h2>
        <ul>
            <?php foreach ( $summaryRows as $index => $row ) : ?>
                <li>
                    <?php if ( $row['name'] !== '' ) : ?>
                        <a href="#content-model-field-<?php echo klytos_esc_attr( $row['name'] ); ?>"
                           data-testid="content_model.error_link.<?php echo (int) $index; ?>">
                            <?php echo klytos_esc_html( $row['message'] ); ?>
                        </a>
                    <?php else : ?>
                        <?php // A server-side failure has no field to jump to. ?>
                        <?php echo klytos_esc_html( $row['message'] ); ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
/*
 * Entry 19 has no section nav in the manifest, so the template's optional left
 * column is absent from the DOM rather than rendered empty.
 *
 * The card stack is NOT one <form> here, as it was on entry 3: this screen has
 * two independent creations and a delete per row, and a form cannot nest
 * inside a form. Each card owns its own.
 */
?>
<div class="k-record-form k-record-form--no-nav" data-testid="content_model.screen">
    <div class="k-card-stack">

        <?php klytos_do_action( 'admin.content_model.before_post_types' ); ?>

        <?php // ─── Card 1 — Post types ──────────────────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="content-model-post-types-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="content-model-post-types-heading">
                    <?php echo klytos_esc_html( __( 'content_model.card_post_types' ) ); ?>
                </h2>

                <?php if ( $postTypes === [] ) : ?>
                    <?php /*
                     * §2 Empty: "a collection inside a form can be empty. That
                     * collection renders ONE ROW: the sentence and the add
                     * action, inside the card, keeping the card's heading."
                     * The add action is the form below, so the row is the
                     * sentence and a link to it.
                     */ ?>
                    <p class="k-empty" data-testid="content_model.post_types_empty">
                        <?php klytos_admin_icon( $spriteUrl, 'ks-category', 'k-empty-icon' ); ?>
                        <span class="k-empty-text">
                            <?php echo klytos_esc_html( __( 'content_model.post_types_empty_sentence' ) ); ?>
                        </span>
                    </p>
                <?php else : ?>
                    <ul class="k-collection" data-testid="content_model.post_types">
                        <?php foreach ( $postTypes as $pt ) : ?>
                            <?php
                            $ptId      = (string) ( $pt['id'] ?? '' );
                            $isBuiltin = ! empty( $pt['builtin'] );
                            $isArmed   = $pendingDelete !== null
                                && $pendingDelete['kind'] === 'post_type'
                                && $pendingDelete['id'] === $ptId;
                            $ptRecords = $isArmed ? $recordCount( $ptId ) : 0;
                            ?>
                            <li class="k-collection-row">
                                <div class="k-collection-main">
                                    <span class="k-collection-title">
                                        <?php /*
                                         * The delta: "each list row links to its
                                         * own screen". The link is on the name,
                                         * which is the row's identity — never on
                                         * a separate "Edit" word, which gives
                                         * every row the same accessible name.
                                         */ ?>
                                        <a href="post-type-edit.php?id=<?php echo klytos_esc_attr( rawurlencode( $ptId ) ); ?>"
                                           data-testid="content_model.post_type_link.<?php echo klytos_esc_attr( $ptId ); ?>">
                                            <?php echo klytos_esc_html( (string) ( $pt['name'] ?? $ptId ) ); ?>
                                        </a>
                                    </span>
                                    <span class="k-collection-meta">
                                        <code><?php echo klytos_esc_html( $ptId ); ?></code>
                                        <code>/<?php echo klytos_esc_html( (string) ( $pt['slug'] ?? '' ) ); ?></code>
                                        <?php if ( $isBuiltin ) : ?>
                                            <span class="k-badge k-badge--info">
                                                <?php echo klytos_esc_html( __( 'content_model.builtin' ) ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="k-collection-actions">
                                    <?php if ( ! $isBuiltin ) : ?>
                                        <?php /*
                                         * §2 Destructive: an inline two-step
                                         * confirm, never a browser confirm().
                                         * aria-live="polite" on the wrapper so
                                         * the relabel is announced.
                                         */ ?>
                                        <form method="post" class="k-confirm-wrap" aria-live="polite">
                                            <?php echo klytos_csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo klytos_esc_attr( $ptId ); ?>">
                                            <?php if ( $isArmed ) : ?>
                                                <input type="hidden" name="action" value="delete_post_type">
                                                <button type="submit"
                                                        class="k-btn k-btn--destructive k-btn--sm"
                                                        data-testid="content_model.post_type_delete_confirm.<?php echo klytos_esc_attr( $ptId ); ?>">
                                                    <?php
                                                    echo klytos_esc_html(
                                                        $ptRecords < 0
                                                            ? __( 'content_model.confirm_delete_post_type_unknown' )
                                                            : __( 'content_model.confirm_delete_post_type', [ 'count' => (string) $ptRecords ] )
                                                    );
                                                    ?>
                                                </button>
                                            <?php else : ?>
                                                <input type="hidden" name="action" value="confirm_delete_post_type">
                                                <button type="submit"
                                                        class="k-btn k-btn--secondary k-btn--sm"
                                                        data-testid="content_model.post_type_delete.<?php echo klytos_esc_attr( $ptId ); ?>">
                                                    <?php echo klytos_esc_html( __( 'common.delete' ) ); ?>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php else : ?>
                                        <?php /*
                                         * §2 Disabled: "a disabled control is
                                         * never hidden and never explained only
                                         * in a tooltip" — the reason is text
                                         * beside it, and it is the button's
                                         * accessible name too.
                                         */ ?>
                                        <button type="button"
                                                class="k-btn k-btn--secondary k-btn--sm"
                                                disabled
                                                aria-describedby="content-model-builtin-reason-<?php echo klytos_esc_attr( $ptId ); ?>"
                                                data-testid="content_model.post_type_delete_disabled.<?php echo klytos_esc_attr( $ptId ); ?>">
                                            <?php echo klytos_esc_html( __( 'common.delete' ) ); ?>
                                        </button>
                                        <span class="k-hint" id="content-model-builtin-reason-<?php echo klytos_esc_attr( $ptId ); ?>">
                                            <?php echo klytos_esc_html( __( 'content_model.builtin_locked' ) ); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php // §1 "[optional] card footer — actions". Here it is the add form. ?>
                <form method="post" class="k-collection-add" data-testid="content_model.post_type_form">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="create_post_type">

                    <h3 class="k-label"><?php echo klytos_esc_html( __( 'content_model.add_post_type' ) ); ?></h3>

                    <div class="k-field-grid k-field-grid--pair">
                        <div class="k-field">
                            <label class="k-label" for="content-model-field-pt_id">
                                <?php echo klytos_esc_html( __( 'content_model.field_id' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control k-control--mono"
                                   id="content-model-field-pt_id"
                                   name="id"
                                   value="<?php echo klytos_esc_attr( $ptDraft['id'] ); ?>"
                                   required
                                   spellcheck="false"
                                   autocapitalize="off"
                                   autocomplete="off"
                                   aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'pt_id' ) ); ?>"
                                   <?php echo isset( $fieldErrors['pt_id'] ) ? 'aria-invalid="true"' : ''; ?>
                                   data-testid="content_model.pt_id">
                            <p class="k-hint" id="content-model-hint-pt_id">
                                <?php echo klytos_esc_html( __( 'content_model.hint_id' ) ); ?>
                            </p>
                            <?php if ( isset( $fieldErrors['pt_id'] ) ) : ?>
                                <p class="k-error" id="content-model-error-pt_id">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors['pt_id'] ); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="k-field">
                            <label class="k-label" for="content-model-field-pt_name">
                                <?php echo klytos_esc_html( __( 'content_model.field_name' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control"
                                   id="content-model-field-pt_name"
                                   name="name"
                                   value="<?php echo klytos_esc_attr( $ptDraft['name'] ); ?>"
                                   required
                                   autocomplete="off"
                                   aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'pt_name' ) ); ?>"
                                   <?php echo isset( $fieldErrors['pt_name'] ) ? 'aria-invalid="true"' : ''; ?>
                                   data-testid="content_model.pt_name">
                            <p class="k-hint" id="content-model-hint-pt_name">
                                <?php echo klytos_esc_html( __( 'content_model.hint_name' ) ); ?>
                            </p>
                            <?php if ( isset( $fieldErrors['pt_name'] ) ) : ?>
                                <p class="k-error" id="content-model-error-pt_name">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors['pt_name'] ); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="content-model-field-pt_slug">
                            <?php echo klytos_esc_html( __( 'content_model.field_slug' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="content-model-field-pt_slug"
                               name="slug"
                               value="<?php echo klytos_esc_attr( $ptDraft['slug'] ); ?>"
                               required
                               spellcheck="false"
                               autocapitalize="off"
                               autocomplete="off"
                               aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'pt_slug' ) ); ?>"
                               <?php echo isset( $fieldErrors['pt_slug'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="content_model.pt_slug">
                        <p class="k-hint" id="content-model-hint-pt_slug">
                            <?php echo klytos_esc_html( __( 'content_model.hint_slug' ) ); ?>
                        </p>
                        <?php if ( isset( $fieldErrors['pt_slug'] ) ) : ?>
                            <p class="k-error" id="content-model-error-pt_slug">
                                <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                <?php echo klytos_esc_html( $fieldErrors['pt_slug'] ); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php // §4: grouped controls in <fieldset><legend>. Every radio group. ?>
                    <fieldset class="k-fieldset">
                        <legend class="k-legend"><?php echo klytos_esc_html( __( 'content_model.field_editor' ) ); ?></legend>
                        <?php foreach ( $editorChoices as $value => $labelKey ) : ?>
                            <label class="k-choice" for="content-model-field-editor-<?php echo klytos_esc_attr( $value ); ?>">
                                <input type="radio"
                                       class="k-radio"
                                       id="content-model-field-editor-<?php echo klytos_esc_attr( $value ); ?>"
                                       name="editor"
                                       value="<?php echo klytos_esc_attr( $value ); ?>"
                                       <?php echo $ptDraft['editor'] === $value ? 'checked' : ''; ?>
                                       data-testid="content_model.pt_editor.<?php echo klytos_esc_attr( $value ); ?>">
                                <?php echo klytos_esc_html( __( $labelKey ) ); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <div class="k-collection-add-actions">
                        <button type="submit" class="k-btn k-btn--primary k-btn--sm" data-testid="content_model.pt_submit">
                            <?php echo klytos_esc_html( __( 'content_model.create_post_type' ) ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <?php klytos_do_action( 'admin.content_model.before_taxonomies' ); ?>

        <?php // ─── Card 2 — Taxonomies ──────────────────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="content-model-taxonomies-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="content-model-taxonomies-heading">
                    <?php echo klytos_esc_html( __( 'content_model.card_taxonomies' ) ); ?>
                </h2>

                <?php if ( $taxonomyRows === [] ) : ?>
                    <p class="k-empty" data-testid="content_model.taxonomies_empty">
                        <?php klytos_admin_icon( $spriteUrl, 'ks-sell', 'k-empty-icon' ); ?>
                        <span class="k-empty-text">
                            <?php echo klytos_esc_html( __( 'content_model.taxonomies_empty_sentence' ) ); ?>
                        </span>
                    </p>
                <?php else : ?>
                    <ul class="k-collection" data-testid="content_model.taxonomies">
                        <?php foreach ( $taxonomyRows as $tax ) : ?>
                            <?php
                            $rowKey  = $tax['post_type'] . '.' . $tax['id'];
                            $taxUrl  = 'taxonomy.php?post_type=' . rawurlencode( $tax['post_type'] )
                                . '&taxonomy=' . rawurlencode( $tax['id'] );
                            $isArmed = $pendingDelete !== null
                                && $pendingDelete['kind'] === 'taxonomy'
                                && $pendingDelete['post_type'] === $tax['post_type']
                                && $pendingDelete['id'] === $tax['id'];
                            ?>
                            <li class="k-collection-row">
                                <div class="k-collection-main">
                                    <span class="k-collection-title">
                                        <a href="<?php echo klytos_esc_url( $taxUrl ); ?>"
                                           data-testid="content_model.taxonomy_link.<?php echo klytos_esc_attr( $rowKey ); ?>">
                                            <?php echo klytos_esc_html( $tax['name'] ); ?>
                                        </a>
                                    </span>
                                    <span class="k-collection-meta">
                                        <code><?php echo klytos_esc_html( $tax['id'] ); ?></code>
                                        <?php /*
                                         * The post type is part of the row's
                                         * identity, not decoration: two post
                                         * types may each hold a taxonomy called
                                         * "category" and the rows would
                                         * otherwise be indistinguishable.
                                         */ ?>
                                        <span><?php echo klytos_esc_html( $tax['post_type_name'] ); ?></span>
                                        <?php $termsLabel = __(
                                            'content_model.term_count',
                                            [ 'count' => (string) $tax['terms'] ]
                                        ); ?>
                                        <span><?php echo klytos_esc_html( $termsLabel ); ?></span>
                                        <?php if ( $tax['hierarchical'] ) : ?>
                                            <span class="k-badge k-badge--info">
                                                <?php echo klytos_esc_html( __( 'content_model.hierarchical' ) ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="k-collection-actions">
                                    <form method="post" class="k-confirm-wrap" aria-live="polite">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="post_type" value="<?php echo klytos_esc_attr( $tax['post_type'] ); ?>">
                                        <input type="hidden" name="tax_id" value="<?php echo klytos_esc_attr( $tax['id'] ); ?>">
                                        <?php if ( $isArmed ) : ?>
                                            <input type="hidden" name="action" value="delete_taxonomy">
                                            <button type="submit"
                                                    class="k-btn k-btn--destructive k-btn--sm"
                                                    data-testid="content_model.taxonomy_delete_confirm.<?php echo klytos_esc_attr( $rowKey ); ?>">
                                                <?php
                                                echo klytos_esc_html( __(
                                                    'content_model.confirm_delete_taxonomy',
                                                    [ 'count' => (string) $tax['terms'] ]
                                                ) );
                                                ?>
                                            </button>
                                        <?php else : ?>
                                            <input type="hidden" name="action" value="confirm_delete_taxonomy">
                                            <button type="submit"
                                                    class="k-btn k-btn--secondary k-btn--sm"
                                                    data-testid="content_model.taxonomy_delete.<?php echo klytos_esc_attr( $rowKey ); ?>">
                                                <?php echo klytos_esc_html( __( 'common.delete' ) ); ?>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <form method="post" class="k-collection-add" data-testid="content_model.taxonomy_form">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="create_taxonomy">

                    <h3 class="k-label"><?php echo klytos_esc_html( __( 'content_model.add_taxonomy' ) ); ?></h3>

                    <div class="k-field">
                        <label class="k-label" for="content-model-field-tax_post_type">
                            <?php echo klytos_esc_html( __( 'content_model.field_post_type' ) ); ?>
                        </label>
                        <?php /*
                         * A taxonomy cannot exist on its own here — it is stored
                         * inside a post type — so the target is a field, not an
                         * assumption. With one post type it is still shown
                         * rather than hidden and defaulted: a hidden choice is
                         * how a person learns what happened only afterwards.
                         */ ?>
                        <select class="k-control"
                                id="content-model-field-tax_post_type"
                                name="post_type"
                                required
                                aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'tax_post_type' ) ); ?>"
                                <?php echo isset( $fieldErrors['tax_post_type'] ) ? 'aria-invalid="true"' : ''; ?>
                                data-testid="content_model.tax_post_type">
                            <option value=""><?php echo klytos_esc_html( __( 'content_model.choose_post_type' ) ); ?></option>
                            <?php foreach ( $postTypes as $pt ) : ?>
                                <option value="<?php echo klytos_esc_attr( (string) ( $pt['id'] ?? '' ) ); ?>"
                                    <?php echo $taxDraft['post_type'] === (string) ( $pt['id'] ?? '' ) ? 'selected' : ''; ?>>
                                    <?php echo klytos_esc_html( (string) ( $pt['name'] ?? $pt['id'] ?? '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="k-hint" id="content-model-hint-tax_post_type">
                            <?php echo klytos_esc_html( __( 'content_model.hint_post_type' ) ); ?>
                        </p>
                        <?php if ( isset( $fieldErrors['tax_post_type'] ) ) : ?>
                            <p class="k-error" id="content-model-error-tax_post_type">
                                <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                <?php echo klytos_esc_html( $fieldErrors['tax_post_type'] ); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="k-field-grid k-field-grid--pair">
                        <div class="k-field">
                            <label class="k-label" for="content-model-field-tax_id">
                                <?php echo klytos_esc_html( __( 'content_model.field_id' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control k-control--mono"
                                   id="content-model-field-tax_id"
                                   name="tax_id"
                                   value="<?php echo klytos_esc_attr( $taxDraft['id'] ); ?>"
                                   required
                                   spellcheck="false"
                                   autocapitalize="off"
                                   autocomplete="off"
                                   aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'tax_id' ) ); ?>"
                                   <?php echo isset( $fieldErrors['tax_id'] ) ? 'aria-invalid="true"' : ''; ?>
                                   data-testid="content_model.tax_id">
                            <p class="k-hint" id="content-model-hint-tax_id">
                                <?php echo klytos_esc_html( __( 'content_model.hint_id' ) ); ?>
                            </p>
                            <?php if ( isset( $fieldErrors['tax_id'] ) ) : ?>
                                <p class="k-error" id="content-model-error-tax_id">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors['tax_id'] ); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="k-field">
                            <label class="k-label" for="content-model-field-tax_name">
                                <?php echo klytos_esc_html( __( 'content_model.field_name' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control"
                                   id="content-model-field-tax_name"
                                   name="tax_name"
                                   value="<?php echo klytos_esc_attr( $taxDraft['name'] ); ?>"
                                   required
                                   autocomplete="off"
                                   aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'tax_name' ) ); ?>"
                                   <?php echo isset( $fieldErrors['tax_name'] ) ? 'aria-invalid="true"' : ''; ?>
                                   data-testid="content_model.tax_name">
                            <p class="k-hint" id="content-model-hint-tax_name">
                                <?php echo klytos_esc_html( __( 'content_model.hint_tax_name' ) ); ?>
                            </p>
                            <?php if ( isset( $fieldErrors['tax_name'] ) ) : ?>
                                <p class="k-error" id="content-model-error-tax_name">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors['tax_name'] ); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="content-model-field-tax_slug">
                            <?php echo klytos_esc_html( __( 'content_model.field_slug' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="content-model-field-tax_slug"
                               name="tax_slug"
                               value="<?php echo klytos_esc_attr( $taxDraft['slug'] ); ?>"
                               spellcheck="false"
                               autocapitalize="off"
                               autocomplete="off"
                               aria-describedby="content-model-hint-tax_slug"
                               data-testid="content_model.tax_slug">
                        <p class="k-hint" id="content-model-hint-tax_slug">
                            <?php echo klytos_esc_html( __( 'content_model.hint_tax_slug' ) ); ?>
                        </p>
                    </div>

                    <label class="k-choice" for="content-model-field-hierarchical">
                        <input type="checkbox"
                               class="k-check"
                               id="content-model-field-hierarchical"
                               name="hierarchical"
                               value="1"
                               <?php echo $taxDraft['hierarchical'] ? 'checked' : ''; ?>
                               data-testid="content_model.tax_hierarchical">
                        <?php echo klytos_esc_html( __( 'content_model.field_hierarchical' ) ); ?>
                    </label>

                    <div class="k-collection-add-actions">
                        <button type="submit" class="k-btn k-btn--primary k-btn--sm" data-testid="content_model.tax_submit">
                            <?php echo klytos_esc_html( __( 'content_model.create_taxonomy' ) ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <?php klytos_do_action( 'admin.content_model.after_taxonomies' ); ?>

    </div>
</div>

<script nonce="<?php echo $cspNonce; ?>">
(function () {
    'use strict';

    /*
     * §2 Error — form level: "focus moved to it on load". The summary is
     * rendered by the server, so this runs once and does not poll.
     */
    var summary = document.getElementById('content-model-error-summary');
    if (summary) {
        summary.focus();
    }
})();
</script>

<?php klytos_do_action( 'admin.post_types.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
