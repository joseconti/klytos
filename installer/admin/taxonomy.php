<?php

/**
 * Klytos Admin — Taxonomy terms.
 *
 * Manifest entry 32 · templates `list-table` + `record-form` · H1 **Taxonomies**.
 * Built in Phase 4 Step 4, stage 5 (the form screens), against
 * `SPEC/screens/template-record-form.md` and `SPEC/manifest.md` §32.
 *
 * THE FORM HALF IS BUILT HERE. THE TABLE HALF IS NOT, and for two reasons
 * rather than the one that was on file:
 *
 *   - `grid-template-columns` for the terms grid is on DR-006's list and has
 *     not been answered (DR-006 row 32).
 *   - **§32's `Count` column has nothing behind it.** The per-screen survey run
 *     against the product BEFORE the first line found that NO record anywhere
 *     is ever associated with a term: `PageManager` stores no term, no MCP tool
 *     assigns one, and the only count in the tree is entry 19's count of terms
 *     IN a taxonomy — a different fact from the number of records carrying a
 *     term. A `Count` column would be a number nobody measured.
 *
 * That is the FIFTH card recorded as DR-006-blocked whose real obstruction was
 * never a column width, after entry 26, entry 27 twice and entry 28. The
 * shipped table therefore stays exactly as it was, minus its defects, and
 * §32's five-column grid is not attempted. Recorded in `docs/roadmap.md` §0c.
 *
 * THE SCREEN'S IDENTITY IS A RECORDED CONTRADICTION — DR-010, drafted, not sent.
 * §32 specifies ONE screen, `taxonomies.php`, H1 "Taxonomies", and
 * `SPEC/navigation.md` gives it a nav item at a bare URL. The product stores
 * taxonomies INSIDE post types, and this file refuses to render without both
 * `post_type` and `taxonomy` in the query string — so that nav item has
 * redirected to Content model on every install since stage 2 shipped it, and
 * has never once reached the screen it names. Entry 32 is its first consumer,
 * which is where the defect surfaced: the same trap entry 28 hit as the first
 * consumer of `admin.statusbar_degraded`. Two recorded artifacts disagree, so
 * neither is silently picked: the composite title the screen has always shown
 * is kept, and DR-010 asks Design for the selection state.
 *
 * FIVE DEFECTS THE SHIPPED SCREEN CARRIED, each reproduced before it was fixed
 * (`tests/E2E/taxonomy.spec.js`):
 *
 *   1. **The Parent field did not exist.** `PostTypeManager::addTerm()` accepts
 *      `parent`, the shipped handler read `$_POST['parent'] ?? ''`, and no
 *      control of that name was ever rendered. Hierarchy — which the product
 *      stores, which entry 19 lets you switch on, and which §32 builds its one
 *      delta around — has been unreachable from the admin since it shipped, and
 *      every term ever created here carries `parent => ''`. It is also §32's
 *      fourth field: the manifest says four and the form had three.
 *   2. **Three labels were literal catalogue keys.** `common.add`,
 *      `common.slug` and `common.auto_generated` are called by this screen and
 *      defined by no catalogue, so they rendered as themselves in all 20
 *      languages. L-046 exactly — and found by the reverse check that lesson
 *      says nothing in this project performs, now `keel-verify` check 22.
 *   3. **A refused CSRF post reported nothing at all** — `if (
 *      klytos_verify_csrf() )` with no else. The THIRD screen with the
 *      identical defect, after entry 27 and entry 28.
 *   4. **A raw exception message reached the person.** `$error =
 *      $e->getMessage()` printed the manager's own English sentence — "Term 'x'
 *      already exists in taxonomy 'y'." — to every locale, naming internal ids.
 *   5. **Delete used a browser `confirm()`**, which §2 forbids by name: "Inline
 *      two-step confirm … Never a browser `confirm()`."
 *
 * A SIXTH, found by the fixture rather than by the screen, was fixed in the
 * manager and committed first: `PostTypeManager::delete()` never deleted a
 * post type's terms, on any install (`tests/Unit/PostTypeTermCleanupTest.php`).
 *
 * AND ONE DEAD BRANCH REMOVED: the shipped handler implemented `update_term`,
 * and nothing on the screen could ever post it — no edit form, no link, no
 * control of any kind. Editing a term is not built here (it is not in §32), so
 * the branch goes rather than being left as an entry point nothing guards.
 *
 * Adaptations, all logged in `docs/BUILD-SPEC.md` §5.9:
 *
 *   - **The file keeps its shipped name.** A filename is a URL on a released
 *     product; `taxonomies.php` stays a recorded mapping, as with `theme.php`,
 *     `post-types.php`, `post-type-edit.php` and `license.php`.
 *   - **The H1 stays the composite the screen has always shown**, pending
 *     DR-010 — see above.
 *   - **The Parent field is ABSENT on a flat taxonomy, not disabled.** §2's
 *     disabled state is for a control that exists and is momentarily
 *     unavailable, and it requires a reason beside the label. A flat taxonomy
 *     has no parenthood to explain, so the honest form is three fields.
 *   - **The toolbar's primary action is "Add term".** §1 puts the primary
 *     action in the toolbar; this screen's one write is the add, which also
 *     makes it the form's implicit submit, so Enter in a field adds.
 *
 * @package Klytos
 * @since   1.0.0
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

$postTypeId = klytos_sanitize_key( (string) ( $_GET['post_type'] ?? '' ) );
$taxonomyId = klytos_sanitize_key( (string) ( $_GET['taxonomy'] ?? '' ) );

if ( $postTypeId === '' || $taxonomyId === '' ) {
    header( 'Location: post-types.php' );
    exit;
}

$ptManager = $app->getPostTypeManager();
$postType  = $ptManager->get( $postTypeId );

// The taxonomy lives inside the post type's record; there is no other store.
$taxonomyConfig = null;
foreach ( $postType['taxonomies'] ?? [] as $tax ) {
    if ( ( $tax['id'] ?? '' ) === $taxonomyId ) {
        $taxonomyConfig = $tax;
        break;
    }
}

if ( $taxonomyConfig === null ) {
    header( 'Location: post-types.php' );
    exit;
}

$taxonomyName = (string) ( $taxonomyConfig['name'] ?? $taxonomyId );
$postTypeName = (string) ( $postType['name'] ?? $postTypeId );
$isHierarchic = ! empty( $taxonomyConfig['hierarchical'] );

$pageTitle   = $taxonomyName . ' — ' . $postTypeName;
$currentPage = 'tax-' . $postTypeId . '-' . $taxonomyId;

$screenUrl = 'taxonomy.php?post_type=' . rawurlencode( $postTypeId )
    . '&taxonomy=' . rawurlencode( $taxonomyId );

$success = '';

/** @var array<string,string> Field-level errors, keyed by control name. */
$fieldErrors = [];

/** @var array<int,array{name:string,message:string}> The error summary's rows, in DOM order. */
$summaryRows = [];

/** @var array<string,string> The add form's values, so a refused post comes back filled in. */
$draft = [ 'name' => '', 'slug' => '', 'parent' => '', 'description' => '' ];

/** @var string The term slug whose delete control is armed, if any. */
$pendingDelete = '';

// ─── Handle POST ────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! klytos_verify_csrf() ) {
        /*
         * The shipped screen wrote `if ( klytos_verify_csrf() )` with no else,
         * so a refused post produced a page that said nothing whatsoever — the
         * person's term gone and the screen idle. Driven: zero alerts.
         */
        $summaryRows[] = [ 'name' => '', 'message' => __( 'taxonomy.error_csrf' ) ];
    } else {
        $action = klytos_sanitize_key( (string) ( $_POST['action'] ?? '' ) );

        if ( $action === 'add_term' ) {
            $draft['name']        = trim( (string) ( $_POST['name'] ?? '' ) );
            $draft['slug']        = trim( (string) ( $_POST['slug'] ?? '' ) );
            $draft['parent']      = $isHierarchic ? trim( (string) ( $_POST['parent'] ?? '' ) ) : '';
            $draft['description'] = trim( (string) ( $_POST['description'] ?? '' ) );

            // The slug the manager will derive, computed here so the refusal is
            // field-level rather than an exception the person reads raw.
            $slug = Helpers::sanitizeSlug( $draft['slug'] !== '' ? $draft['slug'] : $draft['name'] );

            $existing = array_column( $ptManager->listTerms( $postTypeId, $taxonomyId ), 'slug' );

            if ( $draft['name'] === '' ) {
                $fieldErrors['name'] = __( 'taxonomy.error_name_required' );
            }

            if ( $draft['name'] !== '' && $slug === '' ) {
                // A name of nothing but punctuation sanitizes away to an empty
                // slug, which the manager would refuse with an exception.
                $fieldErrors['slug'] = __( 'taxonomy.error_slug_invalid' );
            }

            if ( $slug !== '' && in_array( $slug, $existing, true ) ) {
                $fieldErrors['slug'] = __( 'taxonomy.error_slug_taken' );
            }

            if ( $draft['parent'] !== '' && ! in_array( $draft['parent'], $existing, true ) ) {
                $fieldErrors['parent'] = __( 'taxonomy.error_parent_unknown' );
            }

            foreach ( [ 'name', 'slug', 'parent' ] as $field ) {
                if ( isset( $fieldErrors[ $field ] ) ) {
                    $summaryRows[] = [ 'name' => $field, 'message' => $fieldErrors[ $field ] ];
                }
            }

            if ( $summaryRows === [] ) {
                try {
                    $ptManager->addTerm( $postTypeId, $taxonomyId, [
                        'name'        => $draft['name'],
                        'slug'        => $slug,
                        'parent'      => $draft['parent'],
                        'description' => $draft['description'],
                    ] );

                    $success = __( 'taxonomy.added' );
                    $draft   = [ 'name' => '', 'slug' => '', 'parent' => '', 'description' => '' ];
                } catch ( \Throwable $e ) {
                    /*
                     * The manager's own message is English, names internal ids
                     * and cannot be translated. It goes to the log, where it is
                     * useful; the person gets §2's server-failure shape.
                     */
                    klytos_log_error( 'admin.taxonomy: add refused — ' . $e->getMessage() );
                    $summaryRows[] = [ 'name' => '', 'message' => __( 'taxonomy.error_add_failed' ) ];
                }
            }
        } elseif ( $action === 'confirm_delete_term' ) {
            // First click ARMS the control. Nothing is written on this pass.
            $pendingDelete = klytos_sanitize_key( (string) ( $_POST['slug'] ?? '' ) );
        } elseif ( $action === 'delete_term' ) {
            $slug = klytos_sanitize_key( (string) ( $_POST['slug'] ?? '' ) );

            try {
                $ptManager->deleteTerm( $postTypeId, $taxonomyId, $slug );
                $success = __( 'taxonomy.deleted' );
            } catch ( \Throwable $e ) {
                klytos_log_error( 'admin.taxonomy: delete refused — ' . $e->getMessage() );
                $summaryRows[] = [ 'name' => '', 'message' => __( 'taxonomy.error_delete_failed' ) ];
            }
        }
    }
}

$terms = $ptManager->listTerms( $postTypeId, $taxonomyId );

/**
 * The Parent select's options.
 *
 * Every existing term is offerable: a term being created cannot be its own
 * ancestor, so there is nothing to exclude on this screen. Filterable, like
 * every other list this admin renders.
 *
 * @var array<int,array{slug:string,name:string}>
 */
$parentOptions = klytos_apply_filters(
    'admin.taxonomy.parent_options',
    array_map(
        static fn( array $t ): array => [
            'slug' => (string) ( $t['slug'] ?? '' ),
            'name' => (string) ( $t['name'] ?? $t['slug'] ?? '' ),
        ],
        $terms
    ),
    $postTypeId,
    $taxonomyId
);

/*
 * template-record-form.md §1: "The primary Save lives in the TOOLBAR … and it is
 * the same button on every form screen." This screen's one write is the add.
 * `form=` associates it across the shell boundary and makes it the form's
 * implicit submit, so Enter in a field adds the term. No JavaScript.
 */
klytos_add_filter( 'admin.topbar_actions', static function ( string $html ): string {
    return $html . '<button type="submit" form="k-taxonomy-add"'
        . ' class="k-btn k-btn--primary k-btn--sm"'
        . ' data-testid="taxonomy.submit">'
        . klytos_esc_html( __( 'taxonomy.add' ) )
        . '</button>';
} );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';

/**
 * The `aria-describedby` value for a field: hint first, then its error (§4).
 *
 * @param  string $field The control's name.
 * @return string
 */
$describedBy = static function ( string $field ) use ( &$fieldErrors ): string {
    $ids = [ 'taxonomy-hint-' . $field ];
    if ( isset( $fieldErrors[ $field ] ) ) {
        $ids[] = 'taxonomy-error-' . $field;
    }
    return implode( ' ', $ids );
};

/**
 * Print a field's error exactly as §2 specifies: an `error` icon BEFORE the
 * message, so colour is never the only channel.
 *
 * `$spriteUrl` is defined by `templates/sidebar.php`, which is why this sits
 * below that include.
 *
 * @param string $field The control's name.
 */
$fieldError = static function ( string $field ) use ( &$fieldErrors, $spriteUrl ): void {
    if ( ! isset( $fieldErrors[ $field ] ) ) {
        return;
    }
    printf(
        '<p class="k-error" id="taxonomy-error-%s" data-testid="taxonomy.error.%s">',
        klytos_esc_attr( $field ),
        klytos_esc_attr( $field )
    );
    klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' );
    echo klytos_esc_html( $fieldErrors[ $field ] );
    echo '</p>';
};
?>
<?php klytos_do_action( 'admin.taxonomy.before', $postTypeId, $taxonomyId ); ?>

<?php if ( $success !== '' ) : ?>
    <?php // §2 Success: the page reloads with a role="status" line under the H1. ?>
    <p class="k-status-line" role="status" data-testid="taxonomy.status_line">
        <?php echo klytos_esc_html( $success ); ?>
    </p>
<?php endif; ?>

<?php if ( $summaryRows !== [] ) : ?>
    <?php /*
     * §2 Error — form level: a summary at the top of main, role="alert", focus
     * moved to it on load, every failed field a link to that field.
     */ ?>
    <div class="k-error-summary"
         id="taxonomy-error-summary"
         role="alert"
         tabindex="-1"
         data-testid="taxonomy.error_summary">
        <h2><?php echo klytos_esc_html( __( 'taxonomy.summary_title' ) ); ?></h2>
        <ul>
            <?php foreach ( $summaryRows as $index => $row ) : ?>
                <li>
                    <?php if ( $row['name'] !== '' ) : ?>
                        <a href="#taxonomy-field-<?php echo klytos_esc_attr( $row['name'] ); ?>"
                           data-testid="taxonomy.error_link.<?php echo (int) $index; ?>">
                            <?php echo klytos_esc_html( $row['message'] ); ?>
                        </a>
                    <?php else : ?>
                        <?php // A refused post has no field to jump to. ?>
                        <?php echo klytos_esc_html( $row['message'] ); ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
/*
 * §32 lists cards, not sections, so the template's optional left column is
 * ABSENT from the DOM rather than rendered empty, and the modifier collapses the
 * grid to one track.
 */
?>
<div class="k-record-form k-record-form--no-nav" data-testid="taxonomy.screen">
    <div class="k-card-stack">

        <?php klytos_do_action( 'admin.taxonomy.before_cards', $postTypeId, $taxonomyId ); ?>

        <?php // ─── Card 1 — Add a term (the record-form half) ───────── ?>
        <section class="k-card k-card--padded"
                 id="taxonomy-add"
                 aria-labelledby="taxonomy-add-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="taxonomy-add-heading">
                    <?php echo klytos_esc_html( __( 'taxonomy.add_heading' ) ); ?>
                </h2>

                <form method="post" id="k-taxonomy-add" data-testid="taxonomy.form">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="add_term">

                    <?php klytos_do_action( 'admin.taxonomy.before_fields', $postTypeId, $taxonomyId ); ?>

                    <div class="k-field">
                        <label class="k-label" for="taxonomy-field-name">
                            <?php echo klytos_esc_html( __( 'taxonomy.field_name' ) ); ?>
                        </label>
                        <?php /*
                         * `required` is deliberately ABSENT: the browser's own
                         * constraint validation refuses the submit before a
                         * request exists, which would put the empty-name refusal
                         * in Chromium instead of in the handler that owns it
                         * (L-042). The hint carries the word "Required" (§4).
                         */ ?>
                        <input type="text"
                               class="k-control"
                               id="taxonomy-field-name"
                               name="name"
                               value="<?php echo klytos_esc_attr( $draft['name'] ); ?>"
                               autocomplete="off"
                               aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'name' ) ); ?>"
                               <?php echo isset( $fieldErrors['name'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="taxonomy.field.name">
                        <p class="k-hint" id="taxonomy-hint-name">
                            <?php echo klytos_esc_html( __( 'taxonomy.hint_name' ) ); ?>
                        </p>
                        <?php $fieldError( 'name' ); ?>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="taxonomy-field-slug">
                            <?php echo klytos_esc_html( __( 'taxonomy.field_slug' ) ); ?>
                        </label>
                        <?php /*
                         * No placeholder. §4: "No placeholder-as-label anywhere
                         * in the admin" — and the shipped placeholder was
                         * `common.auto_generated`, a key no catalogue defines,
                         * so the field's only explanation rendered as its own
                         * key. The hint is a real, visible sentence.
                         */ ?>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="taxonomy-field-slug"
                               name="slug"
                               value="<?php echo klytos_esc_attr( $draft['slug'] ); ?>"
                               spellcheck="false"
                               autocapitalize="off"
                               autocomplete="off"
                               aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'slug' ) ); ?>"
                               <?php echo isset( $fieldErrors['slug'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="taxonomy.field.slug">
                        <p class="k-hint" id="taxonomy-hint-slug">
                            <?php echo klytos_esc_html( __( 'taxonomy.hint_slug' ) ); ?>
                        </p>
                        <?php $fieldError( 'slug' ); ?>
                    </div>

                    <?php if ( $isHierarchic ) : ?>
                        <?php /*
                         * §32's fourth field, and the delta the whole screen is
                         * built around. It is rendered ONLY for a hierarchical
                         * taxonomy: on a flat one there is no parenthood to
                         * explain, and §2's disabled state requires a reason
                         * beside the label that would not be true.
                         */ ?>
                        <div class="k-field">
                            <label class="k-label" for="taxonomy-field-parent">
                                <?php echo klytos_esc_html( __( 'taxonomy.field_parent' ) ); ?>
                            </label>
                            <select class="k-control"
                                    id="taxonomy-field-parent"
                                    name="parent"
                                    aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'parent' ) ); ?>"
                                    <?php echo isset( $fieldErrors['parent'] ) ? 'aria-invalid="true"' : ''; ?>
                                    data-testid="taxonomy.field.parent">
                                <?php // A hidden default is how a person learns what happened only afterwards. ?>
                                <option value=""><?php echo klytos_esc_html( __( 'taxonomy.parent_none' ) ); ?></option>
                                <?php foreach ( $parentOptions as $option ) : ?>
                                    <option value="<?php echo klytos_esc_attr( $option['slug'] ); ?>"
                                        <?php echo $draft['parent'] === $option['slug'] ? 'selected' : ''; ?>>
                                        <?php echo klytos_esc_html( $option['name'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="k-hint" id="taxonomy-hint-parent">
                                <?php echo klytos_esc_html( __( 'taxonomy.hint_parent' ) ); ?>
                            </p>
                            <?php $fieldError( 'parent' ); ?>
                        </div>
                    <?php endif; ?>

                    <div class="k-field">
                        <label class="k-label" for="taxonomy-field-description">
                            <?php echo klytos_esc_html( __( 'taxonomy.field_description' ) ); ?>
                        </label>
                        <textarea class="k-control"
                                  id="taxonomy-field-description"
                                  name="description"
                                  rows="3"
                                  aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'description' ) ); ?>"
                                  data-testid="taxonomy.field.description"><?php
                                    echo klytos_esc_textarea( $draft['description'] );
                                    ?></textarea>
                        <p class="k-hint" id="taxonomy-hint-description">
                            <?php echo klytos_esc_html( __( 'taxonomy.hint_description' ) ); ?>
                        </p>
                    </div>

                    <?php klytos_do_action( 'admin.taxonomy.after_fields', $postTypeId, $taxonomyId ); ?>
                </form>
            </div>
        </section>

        <?php // ─── Card 2 — The terms ───────────────────────────────── ?>
        <section class="k-card k-card--padded"
                 id="taxonomy-terms"
                 aria-labelledby="taxonomy-terms-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="taxonomy-terms-heading">
                    <?php echo klytos_esc_html( __( 'taxonomy.terms_heading' ) ); ?>
                </h2>

                <?php if ( $terms === [] ) : ?>
                    <?php /*
                     * §2 Empty — the sentence and the action, never a bare zero.
                     * The action is the field above rather than a second copy of
                     * it: one affordance, one place.
                     */ ?>
                    <p data-testid="taxonomy.no_terms">
                        <?php echo klytos_esc_html( __( 'taxonomy.no_terms' ) ); ?>
                    </p>
                    <p>
                        <a href="#taxonomy-field-name" data-testid="taxonomy.no_terms_action">
                            <?php echo klytos_esc_html( __( 'taxonomy.no_terms_action' ) ); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <?php /*
                     * §32's five-column terms TABLE is NOT built — see the file
                     * header. Its widths are DR-006's, and its `Count` column
                     * has no data source in the product at all. The terms are
                     * listed with the collection component entry 19 already uses
                     * for the same records, which needs no grid widths and
                     * invents no column.
                     */ ?>
                    <ul class="k-collection" data-testid="taxonomy.terms">
                        <?php foreach ( $terms as $term ) : ?>
                            <?php
                            $slug   = (string) ( $term['slug'] ?? '' );
                            $parent = (string) ( $term['parent'] ?? '' );
                            $isArmed = $pendingDelete !== '' && $pendingDelete === $slug;
                            ?>
                            <li class="k-collection-row" data-testid="taxonomy.term.<?php echo klytos_esc_attr( $slug ); ?>">
                                <div class="k-collection-main">
                                    <span class="k-collection-title">
                                        <?php echo klytos_esc_html( (string) ( $term['name'] ?? $slug ) ); ?>
                                    </span>
                                    <span class="k-collection-meta">
                                        <?php /*
                                         * A BARE `<code>`, exactly as entry 19
                                         * writes the same fact. `.k-code` is the
                                         * block component: it paints
                                         * `--fondo-ventana`, a SUNKEN ground,
                                         * and `--texto-secundario` over that
                                         * inside an elevated card measures
                                         * 4.45:1 in light — below AA. Driven and
                                         * measured out of the browser before it
                                         * was changed. `.k-collection-meta code`
                                         * already styles this line and keeps it
                                         * on the card's own ground.
                                         */ ?>
                                        <code><?php echo klytos_esc_html( $slug ); ?></code>
                                        <?php if ( $parent !== '' ) : ?>
                                            <?php /*
                                             * The parent is named rather than
                                             * drawn as indentation: §32 asks for
                                             * real structure and indentation
                                             * alone is not structure. The nested
                                             * list it specifies belongs to the
                                             * table half.
                                             */ ?>
                                            <span class="k-badge k-badge--info"
                                                  data-testid="taxonomy.parent_of.<?php echo klytos_esc_attr( $slug ); ?>">
                                                <?php
                                                echo klytos_esc_html( __( 'taxonomy.child_of', [ 'parent' => $parent ] ) );
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ( ( $term['description'] ?? '' ) !== '' ) : ?>
                                            <span><?php echo klytos_esc_html( (string) $term['description'] ); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="k-collection-actions">
                                    <?php /*
                                     * §2's inline two-step confirm. The shipped
                                     * screen raised a browser `confirm()`, which
                                     * §2 forbids by name — driven: "Are you sure
                                     * you want to delete this?".
                                     */ ?>
                                    <form method="post" class="k-confirm-wrap" aria-live="polite">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="slug" value="<?php echo klytos_esc_attr( $slug ); ?>">
                                        <?php if ( $isArmed ) : ?>
                                            <input type="hidden" name="action" value="delete_term">
                                            <button type="submit"
                                                    class="k-btn k-btn--destructive k-btn--sm"
                                                    data-testid="taxonomy.delete_confirm.<?php echo klytos_esc_attr( $slug ); ?>">
                                                <?php echo klytos_esc_html( __( 'taxonomy.confirm_delete' ) ); ?>
                                            </button>
                                        <?php else : ?>
                                            <input type="hidden" name="action" value="confirm_delete_term">
                                            <button type="submit"
                                                    class="k-btn k-btn--secondary k-btn--sm"
                                                    data-testid="taxonomy.delete.<?php echo klytos_esc_attr( $slug ); ?>">
                                                <?php echo klytos_esc_html( __( 'common.delete' ) ); ?>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <?php klytos_do_action( 'admin.taxonomy.after_cards', $postTypeId, $taxonomyId ); ?>

    </div>
</div>

<?php if ( $summaryRows !== [] ) : ?>
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
( function () {
    'use strict';

    /*
     * §2 Error — form level: "focus moved to it on load". A pure enhancement —
     * the summary is the first thing in `main` and is reachable by keyboard
     * without this, so nothing is lost when the script is absent.
     */
    var summary = document.getElementById( 'taxonomy-error-summary' );
    if ( summary ) {
        summary.focus();
    }
}() );
</script>
<?php endif; ?>

<?php klytos_do_action( 'admin.taxonomy.after', $postTypeId, $taxonomyId ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
