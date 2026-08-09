<?php

/**
 * Klytos Admin — Post type
 *
 * Manifest entry 39 · template `record-form` · H1 = the post type's name.
 * Built in Phase 4 Step 4, stage 5 (the form screens), batch B, screen 2,
 * against `SPEC/screens/template-record-form.md` and `SPEC/manifest.md` §39.
 *
 * The manifest lists SIX cards — Identity · Editor choice · Custom fields
 * (repeatable) · Statuses (editable set) · Per-locale slugs · Exposure (REST,
 * MCP, sitemap, feeds). FIVE are built here. What is not, and why:
 *
 *   - **Exposure** — per-post-type exposure switches do not exist in this
 *     product: `buildPostTypeData()` stores id, name, slug, slug_i18n, editor,
 *     taxonomies, custom_fields and statuses, and nothing else. The card would
 *     change what the outside world can read, so it is a slice with an
 *     authorization review of its own, not a card invented inside a fidelity
 *     stage. Deferred under D-088 answer 1 and recorded in `docs/roadmap.md`
 *     §0c. It stays a manifest row: the redesign is not reportable as complete
 *     while it stands.
 *
 * Two further deliberate calls, each an adaptation with a reason rather than an
 * omission (both logged in `docs/BUILD-SPEC.md` §5.9):
 *
 *   - **No Taxonomies card**, although the screen this replaces had one. The
 *     manifest gives taxonomies to entry 19 (Content model), which was built in
 *     the previous slice and now creates a taxonomy into a chosen post type,
 *     deletes one, and links each to `taxonomy.php`. Nothing is removed from
 *     the product — the same three operations are one screen away, and drawing
 *     them twice would be two implementations of one collection, free to drift.
 *
 *   - **No "Delete this post type" card.** §2 names a destructive section on
 *     this template and uses a post type as its example, but entry 39's own
 *     card list does not include one, and entry 19's Post types collection
 *     already carries the delete with its driven two-step confirm and its
 *     truthful armed label. A second destructive path to the same operation is
 *     duplication, not fidelity. Raised in the session report rather than
 *     decided silently.
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
use Klytos\Core\PostTypeManager;

$ptId = trim( (string) ( $_GET['id'] ?? '' ) );
if ( $ptId === '' ) {
    header( 'Location: post-types.php' );
    exit;
}

$ptManager   = $app->getPostTypeManager();
$pageManager = $app->getPages();

try {
    $postType = $ptManager->get( $ptId );
} catch ( \RuntimeException $e ) {
    header( 'Location: post-types.php' );
    exit;
}

$auth = $app->getAuth();

/** H1 = the post type's name (manifest §39). */
$pageTitle   = (string) ( $postType['name'] ?? $ptId );
$currentPage = 'pt-' . $ptId;

$success = '';

/** @var array<string,string> Field-level errors, keyed by control name. */
$fieldErrors = [];
/** @var array<int,array{name:string,message:string}> The error summary's rows, in DOM order. */
$summaryRows = [];

/**
 * The locales the Per-locale slugs card draws a field for.
 *
 * Read from the site configuration, never guessed: a slug for a locale the site
 * does not serve is a value nothing will ever read.
 *
 * @var array<int,array{code:string,name:string}>
 */
$languages = $app->getSiteConfig()->get()['languages'] ?? [];

/**
 * The values the add forms RENDER after a refused save.
 *
 * A rejected form is re-drawn with what the person typed, never blanked back to
 * the defaults (template-record-form.md §2, "Error — field level").
 */
$cfDraft = [
    'id'          => '',
    'type'        => 'text',
    'label'       => '',
    'description' => '',
    'placeholder' => '',
    'required'    => false,
];
$stDraft = [ 'id' => '', 'label' => '', 'color' => '#6b7280', 'icon' => '', 'is_public' => false ];

/**
 * The row awaiting its second click, if any.
 *
 * §2 "Destructive section": the button becomes "Confirm delete — …" on first
 * click. Implemented entirely on the SERVER — first click posts, the row
 * re-renders armed, the second click deletes — so it behaves identically with
 * JavaScript disabled. A two-step confirm that lives in script alone is a
 * one-step delete for anyone without it, which is what §2's "never a browser
 * confirm()" is really about.
 *
 * @var array{kind:string,id:string}|null
 */
$pendingDelete = null;

/** Which card a failed submission belongs to, so only that card re-opens. */
$openForm = '';

/** How many option rows the add-a-field form draws without JavaScript. */
const KLYTOS_STATIC_OPTION_ROWS = 3;

// ─── Handle POST ────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $action = (string) ( $_POST['action'] ?? '' );

    try {
        if ( $action === 'update' ) {
            $name = trim( (string) ( $_POST['name'] ?? '' ) );
            $slug = trim( (string) ( $_POST['slug'] ?? '' ) );

            if ( $name === '' ) {
                $fieldErrors['name'] = __( 'post_type.error_name_required' );
                $summaryRows[]       = [ 'name' => 'name', 'message' => __( 'post_type.summary_name_required' ) ];
            }

            if ( $slug === '' ) {
                $fieldErrors['slug'] = __( 'post_type.error_slug_required' );
                $summaryRows[]       = [ 'name' => 'slug', 'message' => __( 'post_type.summary_slug_required' ) ];
            }

            $editor = (string) ( $_POST['editor'] ?? 'gutenberg' );
            if ( ! in_array( $editor, [ 'gutenberg', 'tinymce' ], true ) ) {
                $editor = 'gutenberg';
            }

            /*
             * One field per configured locale, each `lang`-tagged (§39's own
             * delta). An empty field is an ABSENT override, not an empty slug:
             * storing "" would make the locale resolve to nothing rather than
             * fall back to the default slug.
             */
            $slugI18n = [];
            foreach ( $languages as $lang ) {
                $code = (string) ( $lang['code'] ?? '' );
                if ( $code === '' ) {
                    continue;
                }
                $value = trim( (string) ( $_POST[ 'slug_i18n_' . $code ] ?? '' ) );
                if ( $value !== '' ) {
                    $slugI18n[ $code ] = $value;
                }
            }

            if ( $summaryRows === [] ) {
                $updateData = [
                    'name'      => $name,
                    'slug'      => $slug,
                    'editor'    => $editor,
                    'slug_i18n' => $slugI18n,
                ];

                // Released extension point — a plugin persists its own keys on a
                // post type through this filter. Kept exactly as shipped.
                $updateData = klytos_apply_filters( 'admin.post_type_edit.update_data', $updateData, $ptId, $_POST );

                $postType  = $ptManager->update( $ptId, $updateData );
                $pageTitle = (string) ( $postType['name'] ?? $ptId );
                $success   = __( 'post_type.saved' );
            } else {
                // Re-render what was posted, so the person sees and fixes it.
                $postType['name']      = $name;
                $postType['slug']      = $slug;
                $postType['editor']    = $editor;
                $postType['slug_i18n'] = $slugI18n;
            }
        } elseif ( $action === 'add_custom_field' ) {
            $openForm = 'custom_field';

            $cfDraft['id']          = trim( (string) ( $_POST['cf_id'] ?? '' ) );
            $cfDraft['type']        = trim( (string) ( $_POST['cf_type'] ?? 'text' ) );
            $cfDraft['label']       = trim( (string) ( $_POST['cf_label'] ?? '' ) );
            $cfDraft['description'] = trim( (string) ( $_POST['cf_description'] ?? '' ) );
            $cfDraft['placeholder'] = trim( (string) ( $_POST['cf_placeholder'] ?? '' ) );
            $cfDraft['required']    = isset( $_POST['cf_required'] );

            if ( $cfDraft['id'] === '' ) {
                $fieldErrors['cf_id'] = __( 'post_type.error_id_required' );
                $summaryRows[]        = [ 'name' => 'cf_id', 'message' => __( 'post_type.summary_field_id_required' ) ];
            } elseif ( Helpers::sanitizeSlug( $cfDraft['id'] ) !== strtolower( $cfDraft['id'] ) ) {
                $fieldErrors['cf_id'] = __( 'post_type.error_id_format' );
                $summaryRows[]        = [ 'name' => 'cf_id', 'message' => __( 'post_type.summary_field_id_format' ) ];
            }

            if ( $cfDraft['label'] === '' ) {
                $fieldErrors['cf_label'] = __( 'post_type.error_label_required' );
                $summaryRows[]           = [ 'name' => 'cf_label', 'message' => __( 'post_type.summary_field_label_required' ) ];
            }

            /*
             * Option rows are posted as parallel arrays. A row with no value is
             * an unused row, not an error: the form draws three of them and most
             * field types use none.
             */
            $options   = [];
            $optValues = (array) ( $_POST['cf_opt_value'] ?? [] );
            $optLabels = (array) ( $_POST['cf_opt_label'] ?? [] );
            foreach ( $optValues as $index => $optValue ) {
                // Scalar-only, deliberately: `cf_opt_value[]` is the one input
                // on this screen that arrives as an ARRAY, so a nested array is
                // a shape a caller can actually send. Casting it to string
                // would store the word "Array" and emit a notice; skipping it
                // refuses the value without inventing one.
                if ( ! is_scalar( $optValue ) ) {
                    continue;
                }
                $optValue = trim( (string) $optValue );
                if ( $optValue === '' ) {
                    continue;
                }
                $rawLabel  = $optLabels[ $index ] ?? '';
                $optLabel  = is_scalar( $rawLabel ) ? trim( (string) $rawLabel ) : '';
                $options[] = [
                    'value' => $optValue,
                    'label' => $optLabel !== '' ? $optLabel : $optValue,
                ];
            }

            if ( $summaryRows === [] ) {
                $postType = $ptManager->addCustomField( $ptId, [
                    'id'          => $cfDraft['id'],
                    'type'        => $cfDraft['type'],
                    'label'       => $cfDraft['label'],
                    'description' => $cfDraft['description'],
                    'placeholder' => $cfDraft['placeholder'],
                    'required'    => $cfDraft['required'],
                    'options'     => $options,
                ] );
                $success  = __( 'post_type.saved_field' );
                $cfDraft  = [
                    'id'          => '',
                    'type'        => 'text',
                    'label'       => '',
                    'description' => '',
                    'placeholder' => '',
                    'required'    => false,
                ];
                $openForm = '';
            }
        } elseif ( $action === 'confirm_delete_custom_field' ) {
            $pendingDelete = [ 'kind' => 'custom_field', 'id' => trim( (string) ( $_POST['cf_field_id'] ?? '' ) ) ];
        } elseif ( $action === 'delete_custom_field' ) {
            $postType = $ptManager->removeCustomField( $ptId, trim( (string) ( $_POST['cf_field_id'] ?? '' ) ) );
            $success  = __( 'post_type.deleted_field' );
        } elseif ( $action === 'add_status' ) {
            $openForm = 'status';

            $stDraft['id']        = trim( (string) ( $_POST['st_id'] ?? '' ) );
            $stDraft['label']     = trim( (string) ( $_POST['st_label'] ?? '' ) );
            $stDraft['color']     = trim( (string) ( $_POST['st_color'] ?? '#6b7280' ) );
            $stDraft['icon']      = trim( (string) ( $_POST['st_icon'] ?? '' ) );
            $stDraft['is_public'] = isset( $_POST['st_is_public'] );

            if ( $stDraft['id'] === '' ) {
                $fieldErrors['st_id'] = __( 'post_type.error_id_required' );
                $summaryRows[]        = [ 'name' => 'st_id', 'message' => __( 'post_type.summary_status_id_required' ) ];
            } elseif ( Helpers::sanitizeSlug( $stDraft['id'] ) !== strtolower( $stDraft['id'] ) ) {
                $fieldErrors['st_id'] = __( 'post_type.error_id_format' );
                $summaryRows[]        = [ 'name' => 'st_id', 'message' => __( 'post_type.summary_status_id_format' ) ];
            } elseif ( in_array( $stDraft['id'], PostTypeManager::SYSTEM_STATUSES, true ) ) {
                // The manager refuses this too. Refusing it here as well makes
                // the refusal a sentence beside the field rather than an
                // exception message in the summary — the same value, said where
                // the person can act on it.
                $fieldErrors['st_id'] = __( 'post_type.error_status_reserved' );
                $summaryRows[]        = [ 'name' => 'st_id', 'message' => __( 'post_type.summary_status_reserved' ) ];
            }

            if ( $stDraft['label'] === '' ) {
                $fieldErrors['st_label'] = __( 'post_type.error_label_required' );
                $summaryRows[]           = [ 'name' => 'st_label', 'message' => __( 'post_type.summary_status_label_required' ) ];
            }

            if ( $summaryRows === [] ) {
                $postType = $ptManager->addStatus( $ptId, $stDraft );
                $success  = __( 'post_type.saved_status' );
                $stDraft  = [ 'id' => '', 'label' => '', 'color' => '#6b7280', 'icon' => '', 'is_public' => false ];
                $openForm = '';
            }
        } elseif ( $action === 'update_status' ) {
            $statusId = trim( (string) ( $_POST['st_id'] ?? '' ) );
            $label    = trim( (string) ( $_POST['st_label'] ?? '' ) );

            if ( $label === '' ) {
                $fieldErrors[ 'st_label_' . $statusId ] = __( 'post_type.error_label_required' );
                $summaryRows[]                          = [
                    'name'    => 'st_label_' . $statusId,
                    'message' => __( 'post_type.summary_status_label_required' ),
                ];
            } else {
                $postType = $ptManager->updateStatus( $ptId, $statusId, [
                    'label'     => $label,
                    'color'     => trim( (string) ( $_POST['st_color'] ?? '#6b7280' ) ),
                    'icon'      => trim( (string) ( $_POST['st_icon'] ?? '' ) ),
                    'is_public' => isset( $_POST['st_is_public'] ),
                ] );
                $success  = __( 'post_type.saved_status' );
            }
        } elseif ( $action === 'confirm_delete_status' ) {
            $pendingDelete = [ 'kind' => 'status', 'id' => trim( (string) ( $_POST['st_id'] ?? '' ) ) ];
        } elseif ( $action === 'delete_status' ) {
            $postType = $ptManager->removeStatus( $ptId, trim( (string) ( $_POST['st_id'] ?? '' ) ), $pageManager );
            $success  = __( 'post_type.deleted_status' );
        }
    } catch ( \Throwable $e ) {
        /*
         * §2 "Error — the save failed for a server reason": the summary names
         * the cause and the action, never a code alone. The manager's message is
         * the cause; it is escaped at print time like any other value.
         */
        $summaryRows[] = [ 'name' => '', 'message' => $e->getMessage() ];
    }
}

// ─── Read the model ─────────────────────────────────────────────

/** Custom fields in their stored order (`position`), which is what the product uses. */
$customFields = $postType['custom_fields'] ?? [];
usort( $customFields, static fn( array $a, array $b ): int => ( $a['position'] ?? 0 ) <=> ( $b['position'] ?? 0 ) );

/**
 * The whole status set — the four system definitions followed by this post
 * type's own — read through the manager, which is the single place that merges
 * them. The card is an EDITABLE SET (manifest §39): the custom half is editable
 * in place, and the system half is shown locked rather than hidden, because a
 * set that hides four of its members is not the set.
 */
$statuses = $ptManager->getStatusesForPostType( $ptId );

/** Third parties reorder, filter or extend both collections from outside. */
$customFields = klytos_apply_filters( 'admin.post_type.custom_fields', $customFields, $ptId );
$statuses     = klytos_apply_filters( 'admin.post_type.statuses', $statuses, $ptId );

/**
 * The field types the manager accepts, grouped by its own `category`.
 *
 * Read from `PostTypeManager::getFieldTypes()` rather than re-listed here: the
 * screen this replaces hardcoded seven groups of type names, so a type added to
 * the manager would never have appeared in the select and nothing would have
 * said so. The option TEXT is the type id in mono, exactly as the shipped
 * screen drew it — an identifier, not prose, so it needs no catalogue entry and
 * gets no invented translation.
 *
 * @var array<string,array<int,string>>
 */
$fieldTypeGroups = [];
foreach ( PostTypeManager::getFieldTypes() as $typeId => $meta ) {
    $category                       = (string) ( $meta['category'] ?? 'other' );
    $fieldTypeGroups[ $category ][] = (string) $typeId;
}

/** The category headings, in the order `getFieldTypes()` declares them. */
$categoryLabels = [
    'text'     => 'post_type.type_group_text',
    'number'   => 'post_type.type_group_number',
    'datetime' => 'post_type.type_group_datetime',
    'choice'   => 'post_type.type_group_choice',
    'media'    => 'post_type.type_group_media',
    'data'     => 'post_type.type_group_data',
    'advanced' => 'post_type.type_group_advanced',
    'other'    => 'post_type.type_group_other',
];

/**
 * A field's `aria-describedby`, hint FIRST and error second (§4).
 *
 * Written once rather than inline per control: fourteen copies of one ternary
 * is fourteen chances for the order to differ, and the order is the specified
 * part.
 */
$describedBy = static function ( string $field ) use ( &$fieldErrors ): string {
    $ids = [ 'post-type-hint-' . $field ];
    if ( isset( $fieldErrors[ $field ] ) ) {
        $ids[] = 'post-type-error-' . $field;
    }
    return implode( ' ', $ids );
};

/** The five sections the nav links to, in DOM order (§4: focus order is DOM order). */
$sections = [
    'identity'      => 'post_type.card_identity',
    'editor'        => 'post_type.card_editor',
    'custom-fields' => 'post_type.card_custom_fields',
    'statuses'      => 'post_type.card_statuses',
    'slugs'         => 'post_type.card_slugs',
];

$csrf = $auth->getCsrfToken();

/*
 * template-record-form.md §1: "The primary Save lives in the TOOLBAR, not at
 * the foot of the page, and it is the same button on every form screen."
 *
 * The toolbar is emitted by the shell, outside <main> and outside the form, so
 * the button is associated with `form=`. That association also makes it the
 * form's implicit submit button, which is what §4 asks for: Enter in a text
 * field saves. No JavaScript is involved in either.
 */
klytos_add_filter( 'admin.topbar_actions', static function ( string $html ): string {
    return $html . '<button type="submit" form="k-post-type-form"'
        . ' class="k-btn k-btn--primary k-btn--sm"'
        . ' data-testid="post_type.save">'
        . klytos_esc_html( __( 'common.save' ) )
        . '</button>';
} );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.post_type.before', $postType, $ptId ); ?>

<?php if ( $success !== '' ) : ?>
    <?php // §2 Success: "the page reloads with a role="status" line under the H1". ?>
    <p class="k-status-line" role="status" data-testid="post_type.status_line">
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
         id="post-type-error-summary"
         role="alert"
         tabindex="-1"
         data-testid="post_type.error_summary">
        <h2><?php echo klytos_esc_html( __( 'post_type.summary_title' ) ); ?></h2>
        <ul>
            <?php foreach ( $summaryRows as $index => $row ) : ?>
                <li>
                    <?php if ( $row['name'] !== '' ) : ?>
                        <a href="#post-type-field-<?php echo klytos_esc_attr( $row['name'] ); ?>"
                           data-testid="post_type.error_link.<?php echo (int) $index; ?>">
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
 * The form the toolbar Save submits carries no cards of its own.
 *
 * Two of this screen's five cards are COLLECTIONS whose rows own their actions
 * (add, save-this-row, delete-this-row), and a form cannot nest inside a form.
 * So the savable fields associate with this one by `form=` — the same
 * mechanism the toolbar button already uses, and the only one that keeps DOM
 * order, focus order and the implicit submit all intact. It is placed outside
 * the card stack so it adds no gap to the layout.
 */
?>
<form method="post" id="k-post-type-form" data-testid="post_type.form">
    <?php echo klytos_csrf_field(); ?>
    <input type="hidden" name="action" value="update">
</form>

<div class="k-record-form" data-testid="post_type.screen">

    <?php // §4: section nav is <nav aria-label>; the current section is aria-current="page". ?>
    <nav class="k-section-nav" aria-label="<?php echo klytos_esc_attr( __( 'post_type.sections_label' ) ); ?>"
         data-testid="post_type.section_nav">
        <?php foreach ( $sections as $anchor => $labelKey ) : ?>
            <a class="k-section-nav-item"
               href="#post-type-<?php echo klytos_esc_attr( $anchor ); ?>"
               <?php echo $anchor === 'identity' ? 'aria-current="page"' : ''; ?>
               data-testid="post_type.section.<?php echo klytos_esc_attr( $anchor ); ?>">
                <?php echo klytos_esc_html( __( $labelKey ) ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="k-card-stack">

        <?php klytos_do_action( 'admin.post_type.before_identity', $postType, $ptId ); ?>

        <?php // ─── Card 1 — Identity ────────────────────────────── ?>
        <section class="k-card k-card--padded"
                 id="post-type-identity"
                 aria-labelledby="post-type-identity-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="post-type-identity-heading">
                    <?php echo klytos_esc_html( __( 'post_type.card_identity' ) ); ?>
                </h2>

                <div class="k-field">
                    <label class="k-label" for="post-type-field-id">
                        <?php echo klytos_esc_html( __( 'post_type.field_id' ) ); ?>
                    </label>
                    <?php /*
                     * §2 "Read-only vs disabled": a value the user may copy but
                     * not change is `readonly`, mono, selectable, with a copy
                     * button — NOT `disabled`. The id is the storage key and
                     * `update()` refuses it by construction, so read-only states
                     * the truth; disabled would say "not now", which is wrong.
                     */ ?>
                    <div class="k-swatch-row">
                        <input type="text"
                               class="k-control k-control--mono"
                               id="post-type-field-id"
                               value="<?php echo klytos_esc_attr( $ptId ); ?>"
                               readonly
                               spellcheck="false"
                               autocapitalize="off"
                               aria-describedby="post-type-hint-id"
                               data-testid="post_type.id">
                        <?php /*
                         * Hidden until the script that gives it its behaviour
                         * has run: a copy button with no clipboard is a control
                         * that does nothing, which §2 is explicit about.
                         */ ?>
                        <button type="button"
                                class="k-btn k-btn--secondary k-btn--sm"
                                id="post-type-copy-id"
                                data-copy="<?php echo klytos_esc_attr( $ptId ); ?>"
                                data-testid="post_type.copy_id"
                                hidden>
                            <?php echo klytos_esc_html( __( 'post_type.copy_id' ) ); ?>
                        </button>
                    </div>
                    <p class="k-hint" id="post-type-hint-id">
                        <?php echo klytos_esc_html( __( 'post_type.hint_id' ) ); ?>
                    </p>
                </div>

                <div class="k-field-grid k-field-grid--pair">
                    <div class="k-field">
                        <label class="k-label" for="post-type-field-name">
                            <?php echo klytos_esc_html( __( 'post_type.field_name' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control"
                               id="post-type-field-name"
                               form="k-post-type-form"
                               name="name"
                               value="<?php echo klytos_esc_attr( (string) ( $postType['name'] ?? '' ) ); ?>"
                               required
                               autocomplete="off"
                               aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'name' ) ); ?>"
                               <?php echo isset( $fieldErrors['name'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="post_type.name">
                        <p class="k-hint" id="post-type-hint-name">
                            <?php echo klytos_esc_html( __( 'post_type.hint_name' ) ); ?>
                        </p>
                        <?php if ( isset( $fieldErrors['name'] ) ) : ?>
                            <p class="k-error" id="post-type-error-name">
                                <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                <?php echo klytos_esc_html( $fieldErrors['name'] ); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="post-type-field-slug">
                            <?php echo klytos_esc_html( __( 'post_type.field_slug' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="post-type-field-slug"
                               form="k-post-type-form"
                               name="slug"
                               value="<?php echo klytos_esc_attr( (string) ( $postType['slug'] ?? '' ) ); ?>"
                               required
                               spellcheck="false"
                               autocapitalize="off"
                               autocomplete="off"
                               aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'slug' ) ); ?>"
                               <?php echo isset( $fieldErrors['slug'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="post_type.slug">
                        <p class="k-hint" id="post-type-hint-slug">
                            <?php echo klytos_esc_html( __( 'post_type.hint_slug' ) ); ?>
                        </p>
                        <?php if ( isset( $fieldErrors['slug'] ) ) : ?>
                            <p class="k-error" id="post-type-error-slug">
                                <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                <?php echo klytos_esc_html( $fieldErrors['slug'] ); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <?php // ─── Card 2 — Editor choice ───────────────────────── ?>
        <section class="k-card k-card--padded"
                 id="post-type-editor"
                 aria-labelledby="post-type-editor-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="post-type-editor-heading">
                    <?php echo klytos_esc_html( __( 'post_type.card_editor' ) ); ?>
                </h2>

                <?php // §4: grouped controls in <fieldset><legend>. Every radio group. ?>
                <fieldset class="k-fieldset">
                    <legend class="k-legend"><?php echo klytos_esc_html( __( 'post_type.field_editor' ) ); ?></legend>
                    <?php
                    $editorChoices = [
                        'gutenberg' => [ 'post_type.editor_gutenberg', 'editor.gutenberg_desc' ],
                        'tinymce'   => [ 'post_type.editor_tinymce', 'editor.tinymce_desc' ],
                    ];
                    $currentEditor = (string) ( $postType['editor'] ?? 'gutenberg' );
                    foreach ( $editorChoices as $value => $keys ) :
                        ?>
                        <div class="k-field">
                            <label class="k-choice k-hit-24" for="post-type-field-editor-<?php echo klytos_esc_attr( $value ); ?>">
                                <input type="radio"
                                       class="k-radio"
                                       id="post-type-field-editor-<?php echo klytos_esc_attr( $value ); ?>"
                                       form="k-post-type-form"
                                       name="editor"
                                       value="<?php echo klytos_esc_attr( $value ); ?>"
                                       aria-describedby="post-type-hint-editor-<?php echo klytos_esc_attr( $value ); ?>"
                                       <?php echo $currentEditor === $value ? 'checked' : ''; ?>
                                       data-testid="post_type.editor.<?php echo klytos_esc_attr( $value ); ?>">
                                <span><?php echo klytos_esc_html( __( $keys[0] ) ); ?></span>
                            </label>
                            <p class="k-hint" id="post-type-hint-editor-<?php echo klytos_esc_attr( $value ); ?>">
                                <?php echo klytos_esc_html( __( $keys[1] ) ); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <?php // Released extension point, kept exactly as shipped. ?>
                <?php klytos_do_action( 'admin.post_type_edit.after_settings', $postType, $ptId ); ?>
            </div>
        </section>

        <?php klytos_do_action( 'admin.post_type.before_custom_fields', $customFields, $ptId ); ?>

        <?php // ─── Card 3 — Custom fields (repeatable) ──────────── ?>
        <section class="k-card k-card--padded"
                 id="post-type-custom-fields"
                 aria-labelledby="post-type-custom-fields-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="post-type-custom-fields-heading">
                    <?php echo klytos_esc_html( __( 'post_type.card_custom_fields' ) ); ?>
                </h2>

                <?php if ( $customFields === [] ) : ?>
                    <?php /*
                     * §2 Empty, and the manifest writes this screen's sentence
                     * out in full: "No custom fields. A custom field adds a
                     * value to every record of this type. — Add a field". The
                     * add action is the form below, so the row is the sentence
                     * and the form is reachable underneath it.
                     */ ?>
                    <p class="k-empty" data-testid="post_type.custom_fields_empty">
                        <?php klytos_admin_icon( $spriteUrl, 'ks-dynamic_form', 'k-empty-icon' ); ?>
                        <span class="k-empty-text">
                            <?php echo klytos_esc_html( __( 'post_type.custom_fields_empty_sentence' ) ); ?>
                        </span>
                    </p>
                <?php else : ?>
                    <ul class="k-collection" data-testid="post_type.custom_fields">
                        <?php foreach ( $customFields as $cf ) : ?>
                            <?php
                            $cfId    = (string) ( $cf['id'] ?? '' );
                            $isArmed = $pendingDelete !== null
                                && $pendingDelete['kind'] === 'custom_field'
                                && $pendingDelete['id'] === $cfId;
                            ?>
                            <li class="k-collection-row">
                                <div class="k-collection-main">
                                    <span class="k-collection-title">
                                        <?php echo klytos_esc_html( (string) ( $cf['label'] ?? $cfId ) ); ?>
                                    </span>
                                    <span class="k-collection-meta">
                                        <code><?php echo klytos_esc_html( $cfId ); ?></code>
                                        <code><?php echo klytos_esc_html( (string) ( $cf['type'] ?? '' ) ); ?></code>
                                        <?php if ( ! empty( $cf['required'] ) ) : ?>
                                            <span class="k-badge k-badge--info">
                                                <?php echo klytos_esc_html( __( 'post_type.required_badge' ) ); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $cf['options'] ) ) : ?>
                                            <span>
                                                <?php echo klytos_esc_html( __(
                                                    'post_type.option_count',
                                                    [ 'count' => (string) count( $cf['options'] ) ]
                                                ) ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="k-collection-actions">
                                    <form method="post" class="k-confirm-wrap" aria-live="polite">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="cf_field_id" value="<?php echo klytos_esc_attr( $cfId ); ?>">
                                        <?php if ( $isArmed ) : ?>
                                            <input type="hidden" name="action" value="delete_custom_field">
                                            <button type="submit"
                                                    class="k-btn k-btn--destructive k-btn--sm"
                                                    data-testid="post_type.custom_field_delete_confirm.<?php echo klytos_esc_attr( $cfId ); ?>">
                                                <?php /*
                                                 * The armed label states what
                                                 * `removeCustomField()` really
                                                 * does: it drops the DEFINITION
                                                 * and leaves whatever records
                                                 * already store for that key.
                                                 * §2's example sentence would be
                                                 * false here, so the shape is
                                                 * kept and the consequence is
                                                 * the true one.
                                                 */ ?>
                                                <?php echo klytos_esc_html( __( 'post_type.confirm_delete_field' ) ); ?>
                                            </button>
                                        <?php else : ?>
                                            <input type="hidden" name="action" value="confirm_delete_custom_field">
                                            <button type="submit"
                                                    class="k-btn k-btn--secondary k-btn--sm"
                                                    data-testid="post_type.custom_field_delete.<?php echo klytos_esc_attr( $cfId ); ?>">
                                                <?php echo klytos_esc_html( __( 'common.delete' ) ); ?>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php // §1 "[optional] card footer — actions". Here it is the add form. ?>
                <form method="post" class="k-collection-add" data-testid="post_type.custom_field_form">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="add_custom_field">

                    <h3 class="k-label"><?php echo klytos_esc_html( __( 'post_type.add_custom_field' ) ); ?></h3>

                    <div class="k-field-grid k-field-grid--pair">
                        <div class="k-field">
                            <label class="k-label" for="post-type-field-cf_id">
                                <?php echo klytos_esc_html( __( 'post_type.field_field_id' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control k-control--mono"
                                   id="post-type-field-cf_id"
                                   name="cf_id"
                                   value="<?php echo klytos_esc_attr( $cfDraft['id'] ); ?>"
                                   required
                                   spellcheck="false"
                                   autocapitalize="off"
                                   autocomplete="off"
                                   aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'cf_id' ) ); ?>"
                                   <?php echo isset( $fieldErrors['cf_id'] ) ? 'aria-invalid="true"' : ''; ?>
                                   data-testid="post_type.cf_id">
                            <p class="k-hint" id="post-type-hint-cf_id">
                                <?php echo klytos_esc_html( __( 'post_type.hint_field_id' ) ); ?>
                            </p>
                            <?php if ( isset( $fieldErrors['cf_id'] ) ) : ?>
                                <p class="k-error" id="post-type-error-cf_id">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors['cf_id'] ); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="k-field">
                            <label class="k-label" for="post-type-field-cf_label">
                                <?php echo klytos_esc_html( __( 'post_type.field_field_label' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control"
                                   id="post-type-field-cf_label"
                                   name="cf_label"
                                   value="<?php echo klytos_esc_attr( $cfDraft['label'] ); ?>"
                                   required
                                   autocomplete="off"
                                   aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'cf_label' ) ); ?>"
                                   <?php echo isset( $fieldErrors['cf_label'] ) ? 'aria-invalid="true"' : ''; ?>
                                   data-testid="post_type.cf_label">
                            <p class="k-hint" id="post-type-hint-cf_label">
                                <?php echo klytos_esc_html( __( 'post_type.hint_field_label' ) ); ?>
                            </p>
                            <?php if ( isset( $fieldErrors['cf_label'] ) ) : ?>
                                <p class="k-error" id="post-type-error-cf_label">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors['cf_label'] ); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="post-type-field-cf_type">
                            <?php echo klytos_esc_html( __( 'post_type.field_field_type' ) ); ?>
                        </label>
                        <select class="k-control k-control--mono"
                                id="post-type-field-cf_type"
                                name="cf_type"
                                required
                                aria-describedby="post-type-hint-cf_type"
                                data-testid="post_type.cf_type">
                            <?php foreach ( $categoryLabels as $category => $labelKey ) : ?>
                                <?php if ( empty( $fieldTypeGroups[ $category ] ) ) : ?>
                                    <?php continue; ?>
                                <?php endif; ?>
                                <optgroup label="<?php echo klytos_esc_attr( __( $labelKey ) ); ?>">
                                    <?php foreach ( $fieldTypeGroups[ $category ] as $typeId ) : ?>
                                        <option value="<?php echo klytos_esc_attr( $typeId ); ?>"
                                            <?php echo $cfDraft['type'] === $typeId ? 'selected' : ''; ?>>
                                            <?php echo klytos_esc_html( $typeId ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <p class="k-hint" id="post-type-hint-cf_type">
                            <?php echo klytos_esc_html( __( 'post_type.hint_field_type' ) ); ?>
                        </p>
                    </div>

                    <div class="k-field-grid k-field-grid--pair">
                        <div class="k-field">
                            <label class="k-label" for="post-type-field-cf_description">
                                <?php echo klytos_esc_html( __( 'post_type.field_field_description' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control"
                                   id="post-type-field-cf_description"
                                   name="cf_description"
                                   value="<?php echo klytos_esc_attr( $cfDraft['description'] ); ?>"
                                   autocomplete="off"
                                   aria-describedby="post-type-hint-cf_description"
                                   data-testid="post_type.cf_description">
                            <p class="k-hint" id="post-type-hint-cf_description">
                                <?php echo klytos_esc_html( __( 'post_type.hint_field_description' ) ); ?>
                            </p>
                        </div>

                        <div class="k-field">
                            <label class="k-label" for="post-type-field-cf_placeholder">
                                <?php echo klytos_esc_html( __( 'post_type.field_field_placeholder' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control"
                                   id="post-type-field-cf_placeholder"
                                   name="cf_placeholder"
                                   value="<?php echo klytos_esc_attr( $cfDraft['placeholder'] ); ?>"
                                   autocomplete="off"
                                   aria-describedby="post-type-hint-cf_placeholder"
                                   data-testid="post_type.cf_placeholder">
                            <p class="k-hint" id="post-type-hint-cf_placeholder">
                                <?php echo klytos_esc_html( __( 'post_type.hint_field_placeholder' ) ); ?>
                            </p>
                        </div>
                    </div>

                    <label class="k-choice k-hit-24" for="post-type-field-cf_required">
                        <input type="checkbox"
                               class="k-check"
                               id="post-type-field-cf_required"
                               name="cf_required"
                               value="1"
                               <?php echo $cfDraft['required'] ? 'checked' : ''; ?>
                               data-testid="post_type.cf_required">
                        <span><?php echo klytos_esc_html( __( 'post_type.field_field_required' ) ); ?></span>
                    </label>

                    <?php /*
                     * The options a choice field offers.
                     *
                     * The screen this replaces built these rows in JavaScript
                     * alone and showed them only for choice types, so with the
                     * script absent a select could be created with no options at
                     * all and nothing said why. Three rows are always in the
                     * markup instead; "Add another option" is the enhancement on
                     * top, revealed only once its script has run.
                     */ ?>
                    <fieldset class="k-fieldset">
                        <legend class="k-legend"><?php echo klytos_esc_html( __( 'post_type.options_legend' ) ); ?></legend>
                        <p class="k-hint" id="post-type-hint-cf_options">
                            <?php echo klytos_esc_html( __( 'post_type.options_hint' ) ); ?>
                        </p>
                        <div id="post-type-options-list">
                            <?php for ( $i = 0; $i < KLYTOS_STATIC_OPTION_ROWS; $i++ ) : ?>
                                <div class="k-field-grid k-field-grid--pair">
                                    <div class="k-field">
                                        <label class="k-label" for="post-type-field-cf_opt_value_<?php echo (int) $i; ?>">
                                            <?php echo klytos_esc_html( __(
                                                'post_type.option_value',
                                                [ 'number' => (string) ( $i + 1 ) ]
                                            ) ); ?>
                                        </label>
                                        <input type="text"
                                               class="k-control k-control--mono"
                                               id="post-type-field-cf_opt_value_<?php echo (int) $i; ?>"
                                               name="cf_opt_value[]"
                                               spellcheck="false"
                                               autocapitalize="off"
                                               autocomplete="off"
                                               data-testid="post_type.cf_opt_value.<?php echo (int) $i; ?>">
                                    </div>
                                    <div class="k-field">
                                        <label class="k-label" for="post-type-field-cf_opt_label_<?php echo (int) $i; ?>">
                                            <?php echo klytos_esc_html( __(
                                                'post_type.option_label',
                                                [ 'number' => (string) ( $i + 1 ) ]
                                            ) ); ?>
                                        </label>
                                        <input type="text"
                                               class="k-control"
                                               id="post-type-field-cf_opt_label_<?php echo (int) $i; ?>"
                                               name="cf_opt_label[]"
                                               autocomplete="off"
                                               data-testid="post_type.cf_opt_label.<?php echo (int) $i; ?>">
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <div>
                            <button type="button"
                                    class="k-btn k-btn--secondary k-btn--sm"
                                    id="post-type-add-option"
                                    data-testid="post_type.add_option"
                                    hidden>
                                <?php echo klytos_esc_html( __( 'post_type.add_option' ) ); ?>
                            </button>
                        </div>
                    </fieldset>

                    <div class="k-collection-add-actions">
                        <button type="submit" class="k-btn k-btn--primary k-btn--sm" data-testid="post_type.cf_submit">
                            <?php echo klytos_esc_html( __( 'post_type.create_custom_field' ) ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <?php // ─── Card 4 — Statuses (editable set) ─────────────── ?>
        <section class="k-card k-card--padded"
                 id="post-type-statuses"
                 aria-labelledby="post-type-statuses-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="post-type-statuses-heading">
                    <?php echo klytos_esc_html( __( 'post_type.card_statuses' ) ); ?>
                </h2>

                <?php /*
                 * The set is never empty — the four system statuses are always
                 * in it — so this card has no empty state to render. They are
                 * shown LOCKED rather than hidden: a set that draws four of its
                 * members nowhere is not the set, and a person adding "In
                 * review" needs to see what the ids already are.
                 */ ?>
                <ul class="k-collection" data-testid="post_type.statuses">
                    <?php foreach ( $statuses as $status ) : ?>
                        <?php
                        $stId     = (string) ( $status['id'] ?? '' );
                        $isSystem = ! empty( $status['system'] );
                        $isArmed  = $pendingDelete !== null
                            && $pendingDelete['kind'] === 'status'
                            && $pendingDelete['id'] === $stId;
                        $labelErrorKey = 'st_label_' . $stId;
                        $stColor       = (string) ( $status['color'] ?? '#6b7280' );
                        // A colour input accepts only #rrggbb; anything else
                        // would silently become black and misreport the stored
                        // value, so the hex field keeps the real one and the
                        // picker falls back.
                        $stSwatch      = strlen( $stColor ) === 7 ? $stColor : '#6b7280';
                        $stPickerLabel = __( 'post_type.status_picker_label', [ 'id' => $stId ] );
                        ?>
                        <li class="k-collection-row">
                            <?php if ( $isSystem ) : ?>
                                <div class="k-collection-main">
                                    <span class="k-collection-title">
                                        <?php echo klytos_esc_html( (string) ( $status['label'] ?? $stId ) ); ?>
                                    </span>
                                    <span class="k-collection-meta">
                                        <code><?php echo klytos_esc_html( $stId ); ?></code>
                                        <?php if ( ! empty( $status['is_public'] ) ) : ?>
                                            <span class="k-badge k-badge--exito">
                                                <?php echo klytos_esc_html( __( 'post_type.status_public' ) ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="k-collection-actions">
                                    <?php /*
                                     * §2 Disabled: the control is never hidden
                                     * and never explained only in a tooltip —
                                     * the reason is real text beside it and is
                                     * the button's description.
                                     */ ?>
                                    <button type="button"
                                            class="k-btn k-btn--secondary k-btn--sm"
                                            disabled
                                            aria-describedby="post-type-system-reason-<?php echo klytos_esc_attr( $stId ); ?>"
                                            data-testid="post_type.status_delete_disabled.<?php echo klytos_esc_attr( $stId ); ?>">
                                        <?php echo klytos_esc_html( __( 'common.delete' ) ); ?>
                                    </button>
                                    <span class="k-hint" id="post-type-system-reason-<?php echo klytos_esc_attr( $stId ); ?>">
                                        <?php echo klytos_esc_html( __( 'post_type.system_status_locked' ) ); ?>
                                    </span>
                                </div>
                            <?php else : ?>
                                <?php /*
                                 * "Statuses (EDITABLE set)": a custom status is
                                 * edited in place and saved by its own row, so
                                 * the whole set is editable with no modal and no
                                 * JavaScript. Each row is its own <form> — which
                                 * is also why the card stack is not one form.
                                 */ ?>
                                <form method="post"
                                      class="k-collection-edit"
                                      data-testid="post_type.status_form.<?php echo klytos_esc_attr( $stId ); ?>">
                                    <?php echo klytos_csrf_field(); ?>
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="st_id" value="<?php echo klytos_esc_attr( $stId ); ?>">

                                    <div class="k-field">
                                        <label class="k-label" for="post-type-field-<?php echo klytos_esc_attr( $labelErrorKey ); ?>">
                                            <?php echo klytos_esc_html( __(
                                                'post_type.status_label_for',
                                                [ 'id' => $stId ]
                                            ) ); ?>
                                        </label>
                                        <input type="text"
                                               class="k-control"
                                               id="post-type-field-<?php echo klytos_esc_attr( $labelErrorKey ); ?>"
                                               name="st_label"
                                               value="<?php echo klytos_esc_attr( (string) ( $status['label'] ?? '' ) ); ?>"
                                               required
                                               autocomplete="off"
                                               aria-describedby="<?php echo klytos_esc_attr( $describedBy( $labelErrorKey ) ); ?>"
                                               <?php echo isset( $fieldErrors[ $labelErrorKey ] ) ? 'aria-invalid="true"' : ''; ?>
                                               data-testid="post_type.status_label.<?php echo klytos_esc_attr( $stId ); ?>">
                                        <p class="k-hint" id="post-type-hint-<?php echo klytos_esc_attr( $labelErrorKey ); ?>">
                                            <code><?php echo klytos_esc_html( $stId ); ?></code>
                                        </p>
                                        <?php if ( isset( $fieldErrors[ $labelErrorKey ] ) ) : ?>
                                            <p class="k-error" id="post-type-error-<?php echo klytos_esc_attr( $labelErrorKey ); ?>">
                                                <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                                <?php echo klytos_esc_html( $fieldErrors[ $labelErrorKey ] ); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="k-field">
                                        <label class="k-label" for="post-type-field-st_color_<?php echo klytos_esc_attr( $stId ); ?>">
                                            <?php echo klytos_esc_html( __( 'post_type.field_status_color' ) ); ?>
                                        </label>
                                        <?php /*
                                         * The picker carries NO name and the hex
                                         * field is the value — D-088's shipped
                                         * defect on the Design screen was the
                                         * two sharing one name, so the picker
                                         * posted last and a typed value was
                                         * discarded with JavaScript off.
                                         */ ?>
                                        <div class="k-swatch-row">
                                            <input type="color"
                                                   class="k-swatch"
                                                   id="post-type-swatch-<?php echo klytos_esc_attr( $stId ); ?>"
                                                   value="<?php echo klytos_esc_attr( $stSwatch ); ?>"
                                                   aria-label="<?php echo klytos_esc_attr( $stPickerLabel ); ?>"
                                                   data-mirrors="post-type-field-st_color_<?php echo klytos_esc_attr( $stId ); ?>">
                                            <input type="text"
                                                   class="k-control k-control--mono"
                                                   id="post-type-field-st_color_<?php echo klytos_esc_attr( $stId ); ?>"
                                                   name="st_color"
                                                   value="<?php echo klytos_esc_attr( $stColor ); ?>"
                                                   spellcheck="false"
                                                   autocapitalize="off"
                                                   data-testid="post_type.status_color.<?php echo klytos_esc_attr( $stId ); ?>">
                                        </div>
                                    </div>

                                    <label class="k-choice k-hit-24" for="post-type-field-st_public_<?php echo klytos_esc_attr( $stId ); ?>">
                                        <input type="checkbox"
                                               class="k-check"
                                               id="post-type-field-st_public_<?php echo klytos_esc_attr( $stId ); ?>"
                                               name="st_is_public"
                                               value="1"
                                               <?php echo ! empty( $status['is_public'] ) ? 'checked' : ''; ?>
                                               data-testid="post_type.status_public.<?php echo klytos_esc_attr( $stId ); ?>">
                                        <span><?php echo klytos_esc_html( __( 'post_type.field_status_public' ) ); ?></span>
                                    </label>

                                    <button type="submit"
                                            class="k-btn k-btn--secondary k-btn--sm"
                                            data-testid="post_type.status_save.<?php echo klytos_esc_attr( $stId ); ?>">
                                        <?php echo klytos_esc_html( __( 'common.save' ) ); ?>
                                    </button>
                                </form>

                                <div class="k-collection-actions">
                                    <form method="post" class="k-confirm-wrap" aria-live="polite">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="st_id" value="<?php echo klytos_esc_attr( $stId ); ?>">
                                        <?php if ( $isArmed ) : ?>
                                            <input type="hidden" name="action" value="delete_status">
                                            <button type="submit"
                                                    class="k-btn k-btn--destructive k-btn--sm"
                                                    data-testid="post_type.status_delete_confirm.<?php echo klytos_esc_attr( $stId ); ?>">
                                                <?php /*
                                                 * `removeStatus()` reassigns every
                                                 * record holding this status to
                                                 * `draft`. That is the consequence
                                                 * the armed label has to state —
                                                 * "34 records will be deleted"
                                                 * would be false twice over.
                                                 */ ?>
                                                <?php echo klytos_esc_html( __( 'post_type.confirm_delete_status' ) ); ?>
                                            </button>
                                        <?php else : ?>
                                            <input type="hidden" name="action" value="confirm_delete_status">
                                            <button type="submit"
                                                    class="k-btn k-btn--secondary k-btn--sm"
                                                    data-testid="post_type.status_delete.<?php echo klytos_esc_attr( $stId ); ?>">
                                                <?php echo klytos_esc_html( __( 'common.delete' ) ); ?>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <form method="post" class="k-collection-add" data-testid="post_type.status_add_form">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="add_status">

                    <h3 class="k-label"><?php echo klytos_esc_html( __( 'post_type.add_status' ) ); ?></h3>

                    <div class="k-field-grid k-field-grid--pair">
                        <div class="k-field">
                            <label class="k-label" for="post-type-field-st_id">
                                <?php echo klytos_esc_html( __( 'post_type.field_status_id' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control k-control--mono"
                                   id="post-type-field-st_id"
                                   name="st_id"
                                   value="<?php echo klytos_esc_attr( $stDraft['id'] ); ?>"
                                   required
                                   spellcheck="false"
                                   autocapitalize="off"
                                   autocomplete="off"
                                   aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'st_id' ) ); ?>"
                                   <?php echo isset( $fieldErrors['st_id'] ) ? 'aria-invalid="true"' : ''; ?>
                                   data-testid="post_type.st_id">
                            <p class="k-hint" id="post-type-hint-st_id">
                                <?php echo klytos_esc_html( __( 'post_type.hint_status_id' ) ); ?>
                            </p>
                            <?php if ( isset( $fieldErrors['st_id'] ) ) : ?>
                                <p class="k-error" id="post-type-error-st_id">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors['st_id'] ); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="k-field">
                            <label class="k-label" for="post-type-field-st_label">
                                <?php echo klytos_esc_html( __( 'post_type.field_status_label' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control"
                                   id="post-type-field-st_label"
                                   name="st_label"
                                   value="<?php echo klytos_esc_attr( $stDraft['label'] ); ?>"
                                   required
                                   autocomplete="off"
                                   aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'st_label' ) ); ?>"
                                   <?php echo isset( $fieldErrors['st_label'] ) ? 'aria-invalid="true"' : ''; ?>
                                   data-testid="post_type.st_label">
                            <p class="k-hint" id="post-type-hint-st_label">
                                <?php echo klytos_esc_html( __( 'post_type.hint_status_label' ) ); ?>
                            </p>
                            <?php if ( isset( $fieldErrors['st_label'] ) ) : ?>
                                <p class="k-error" id="post-type-error-st_label">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors['st_label'] ); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="post-type-field-st_color">
                            <?php echo klytos_esc_html( __( 'post_type.field_status_color' ) ); ?>
                        </label>
                        <div class="k-swatch-row">
                            <input type="color"
                                   class="k-swatch"
                                   id="post-type-swatch-new"
                                   value="<?php echo klytos_esc_attr(
                                       strlen( $stDraft['color'] ) === 7 ? $stDraft['color'] : '#6b7280'
                                   ); ?>"
                                   aria-label="<?php echo klytos_esc_attr( __( 'post_type.new_status_picker_label' ) ); ?>"
                                   data-mirrors="post-type-field-st_color">
                            <input type="text"
                                   class="k-control k-control--mono"
                                   id="post-type-field-st_color"
                                   name="st_color"
                                   value="<?php echo klytos_esc_attr( $stDraft['color'] ); ?>"
                                   spellcheck="false"
                                   autocapitalize="off"
                                   data-testid="post_type.st_color">
                        </div>
                    </div>

                    <label class="k-choice k-hit-24" for="post-type-field-st_is_public">
                        <input type="checkbox"
                               class="k-check"
                               id="post-type-field-st_is_public"
                               name="st_is_public"
                               value="1"
                               aria-describedby="post-type-hint-st_is_public"
                               <?php echo $stDraft['is_public'] ? 'checked' : ''; ?>
                               data-testid="post_type.st_is_public">
                        <span><?php echo klytos_esc_html( __( 'post_type.field_status_public' ) ); ?></span>
                    </label>
                    <p class="k-hint" id="post-type-hint-st_is_public">
                        <?php echo klytos_esc_html( __( 'post_type.hint_status_public' ) ); ?>
                    </p>

                    <div class="k-collection-add-actions">
                        <button type="submit" class="k-btn k-btn--primary k-btn--sm" data-testid="post_type.st_submit">
                            <?php echo klytos_esc_html( __( 'post_type.create_status' ) ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <?php klytos_do_action( 'admin.post_type.after_statuses', $statuses, $ptId ); ?>

        <?php // ─── Card 5 — Per-locale slugs ────────────────────── ?>
        <section class="k-card k-card--padded"
                 id="post-type-slugs"
                 aria-labelledby="post-type-slugs-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="post-type-slugs-heading">
                    <?php echo klytos_esc_html( __( 'post_type.card_slugs' ) ); ?>
                </h2>

                <?php if ( $languages === [] ) : ?>
                    <?php /*
                     * A card with no locales configured is not an error and not
                     * a form with nothing in it: it is the empty state, and the
                     * sentence says where the locales come from, because the
                     * answer is on another screen.
                     */ ?>
                    <p class="k-empty" data-testid="post_type.slugs_empty">
                        <?php klytos_admin_icon( $spriteUrl, 'ks-translate', 'k-empty-icon' ); ?>
                        <span class="k-empty-text">
                            <?php echo klytos_esc_html( __( 'post_type.slugs_empty_sentence' ) ); ?>
                        </span>
                    </p>
                <?php else : ?>
                    <?php /*
                     * §39's delta, in its own words: "the per-locale slug fields
                     * are in a <fieldset> whose <legend> is 'Slugs by locale';
                     * each field's label is the locale's name, and the field
                     * carries lang."
                     */ ?>
                    <fieldset class="k-fieldset">
                        <legend class="k-legend"><?php echo klytos_esc_html( __( 'post_type.slugs_legend' ) ); ?></legend>

                        <div class="k-field-grid k-field-grid--pair">
                            <?php foreach ( $languages as $lang ) : ?>
                                <?php
                                $code = (string) ( $lang['code'] ?? '' );
                                if ( $code === '' ) {
                                    continue;
                                }
                                $localeName  = (string) ( $lang['name'] ?? $code );
                                $localeSlug  = (string) ( $postType['slug_i18n'][ $code ] ?? '' );
                                ?>
                                <div class="k-field">
                                    <label class="k-label" for="post-type-field-slug_i18n_<?php echo klytos_esc_attr( $code ); ?>">
                                        <?php echo klytos_esc_html( $localeName ); ?>
                                    </label>
                                    <input type="text"
                                           class="k-control k-control--mono"
                                           id="post-type-field-slug_i18n_<?php echo klytos_esc_attr( $code ); ?>"
                                           form="k-post-type-form"
                                           name="slug_i18n_<?php echo klytos_esc_attr( $code ); ?>"
                                           value="<?php echo klytos_esc_attr( $localeSlug ); ?>"
                                           lang="<?php echo klytos_esc_attr( $code ); ?>"
                                           spellcheck="false"
                                           autocapitalize="off"
                                           autocomplete="off"
                                           aria-describedby="post-type-hint-slug_i18n_<?php echo klytos_esc_attr( $code ); ?>"
                                           data-testid="post_type.slug_i18n.<?php echo klytos_esc_attr( $code ); ?>">
                                    <p class="k-hint" id="post-type-hint-slug_i18n_<?php echo klytos_esc_attr( $code ); ?>">
                                        <?php echo klytos_esc_html( __(
                                            'post_type.hint_slug_locale',
                                            [ 'slug' => (string) ( $postType['slug'] ?? '' ) ]
                                        ) ); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                <?php endif; ?>
            </div>
        </section>

    </div>
</div>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function () {
    'use strict';

    /*
     * §2 Error — form level: "focus moved to it on load". Server-rendered, so
     * this runs once and does not poll.
     */
    var summary = document.getElementById('post-type-error-summary');
    if (summary) {
        summary.focus();
    }

    /*
     * §4: "the current section is aria-current='page'". Without a script the
     * first section is current, which is true on load — the page opens at the
     * top. From here the attribute follows the fragment, and it is MOVED rather
     * than added, so exactly one item ever carries it.
     */
    var navItems = document.querySelectorAll('.k-section-nav-item');

    function markCurrent(hash) {
        var matched = false;
        Array.prototype.forEach.call(navItems, function (item) {
            var isCurrent = hash !== '' && item.getAttribute('href') === hash;
            if (isCurrent) {
                matched = true;
                item.setAttribute('aria-current', 'page');
            } else {
                item.removeAttribute('aria-current');
            }
        });
        if (!matched && navItems.length) {
            navItems[0].setAttribute('aria-current', 'page');
        }
    }

    window.addEventListener('hashchange', function () {
        markCurrent(window.location.hash);
    });
    if (window.location.hash) {
        markCurrent(window.location.hash);
    }

    /*
     * Every colour picker mirrors its hex field, in both directions. The hex
     * field is the value that posts; this is enhancement and nothing else.
     */
    Array.prototype.forEach.call(document.querySelectorAll('[data-mirrors]'), function (swatch) {
        var field = document.getElementById(swatch.getAttribute('data-mirrors'));
        if (!field) {
            return;
        }
        swatch.addEventListener('input', function () {
            field.value = swatch.value;
        });
        field.addEventListener('input', function () {
            // Only a complete 6-digit value can go into a colour input; a
            // half-typed one would make the picker jump to black mid-edit.
            if (/^#[0-9a-fA-F]{6}$/.test(field.value)) {
                swatch.value = field.value;
            }
        });
    });

    /*
     * The copy button is revealed only where the clipboard exists, so it is
     * never a control that does nothing.
     */
    var copyButton = document.getElementById('post-type-copy-id');
    if (copyButton && navigator.clipboard) {
        copyButton.hidden = false;
        copyButton.addEventListener('click', function () {
            navigator.clipboard.writeText(copyButton.getAttribute('data-copy') || '');
        });
    }

    /*
     * "Add another option" appends a row beyond the three the markup always
     * carries. Revealed here rather than in the markup, for the same reason as
     * the copy button.
     */
    var addOption = document.getElementById('post-type-add-option');
    var optionList = document.getElementById('post-type-options-list');
    if (addOption && optionList) {
        addOption.hidden = false;
        addOption.addEventListener('click', function () {
            var index = optionList.querySelectorAll('input[name="cf_opt_value[]"]').length;
            var row = optionList.firstElementChild.cloneNode(true);

            Array.prototype.forEach.call(row.querySelectorAll('input'), function (input) {
                input.value = '';
                var suffix = input.name === 'cf_opt_value[]' ? 'value' : 'label';
                var id = 'post-type-field-cf_opt_' + suffix + '_' + index;
                var label = row.querySelector('label[for="' + input.id + '"]');
                if (label) {
                    label.setAttribute('for', id);
                    label.textContent = label.textContent.replace(/\d+/, String(index + 1));
                }
                input.id = id;
                input.setAttribute('data-testid', 'post_type.cf_opt_' + suffix + '.' + index);
            });

            optionList.appendChild(row);
        });
    }
})();
</script>

<?php klytos_do_action( 'admin.post_type.after', $postType, $ptId ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
