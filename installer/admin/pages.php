<?php

/**
 * Klytos Admin — Pages
 *
 * Manifest entry 1 · template `list-table` · H1 "Pages".
 *
 * Built in Phase 4 Step 4, stage 4 (the list screens) against
 * `SPEC/screens/template-list-table.md`, `SPEC/accessibility.md` §2.1 and
 * `SPEC/manifest.md` §1. This is the ONLY list-table consumer whose
 * `grid-template-columns` the delivery actually records — the other twelve are
 * blocked on DR-006 — so the value below is quoted from the manifest and is not
 * derived from anything.
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

$auth    = $app->getAuth();
$pages   = $app->getPages();
$error   = '';
$success = '';

/*
 * The site home is the page whose slug is `index` — that is what
 * PageManager::renderBreadcrumbs() tests, so it is the tree's own definition
 * and not one invented here. Manifest §1's delta: "Delete on the site home is
 * `disabled` with the reason in its name."
 */
const KLYTOS_HOME_SLUG = 'index';

// Post type filter: when accessed via ?post_type=casas, only show that type.
$postTypeFilter = trim( $_GET['post_type'] ?? '' );
$postTypeName   = '';

if ( $postTypeFilter !== '' ) {
    try {
        $ptDef        = $app->getPostTypeManager()->get( $postTypeFilter );
        $postTypeName = $ptDef['name'] ?? ucfirst( $postTypeFilter );
    } catch ( \Throwable $e ) {
        $postTypeName = ucfirst( $postTypeFilter );
    }
    $pageTitle   = $postTypeName;
    $currentPage = 'pt-' . $postTypeFilter;
} else {
    // H1 is the plural noun of the record, sentence case, NO count — the count
    // lives in the caption where it can update (template-list-table.md §4).
    $pageTitle   = __( 'pages.title' );
    $currentPage = 'pages';
}

// ---------------------------------------------------------------------------
// Writes
// ---------------------------------------------------------------------------

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    // The list is gated at 'pages.view' so a viewer can read it; every action
    // below removes, restores or rewrites content, which is the 'pages.delete'
    // tier (owner + admin). Gating the page at that tier instead would have
    // locked editors and viewers out of the page list entirely.
    klytos_require_permission( 'pages.delete' );

    $action = $_POST['action'] ?? '';
    $slug   = $_POST['slug'] ?? '';

    switch ( $action ) {
        case 'delete':
            if ( $slug === KLYTOS_HOME_SLUG ) {
                // The control is rendered disabled, so reaching here means the
                // request did not come from the rendered screen. Refuse rather
                // than trust the markup.
                $error = __( 'pages.delete_home_refused' );
            } elseif ( $pages->delete( $slug ) ) {
                $success = __( 'pages.moved_to_trash' );
            } else {
                $error = __( 'common.error' );
            }
            break;

        case 'restore':
            try {
                $pages->restore( $slug );
                $success = __( 'pages.restored' );
            } catch ( \Throwable $e ) {
                $error = $e->getMessage();
            }
            break;

        case 'permanent_delete':
            if ( $pages->permanentDelete( $slug ) ) {
                $success = __( 'pages.permanently_deleted' );
            } else {
                $error = __( 'common.error' );
            }
            break;

        case 'empty_trash':
            $count   = $pages->emptyTrash();
            $success = __( 'pages.trash_emptied', ['count' => $count] );
            break;

        case 'bulk_action':
            /*
             * Manifest §1's bulk set is "publish, unpublish, delete, change
             * template". Three of those are the product's existing operations
             * under the design's names — `unpublish` is the draft transition
             * and `delete` is the trash transition — and `change_template` is
             * new here. The trash view keeps restore and permanent delete,
             * which the design does not draw because it draws no trash.
             */
            $bulkAction   = $_POST['bulk_action'] ?? '';
            $bulkSlugs    = $_POST['bulk_slugs'] ?? [];
            $bulkTemplate = trim( $_POST['bulk_template'] ?? '' );

            if ( $bulkAction !== '' && ! empty( $bulkSlugs ) && is_array( $bulkSlugs ) ) {
                $bulkCount = 0;
                $bulkFail  = [];
                klytos_do_action( 'admin.bulk_action.before', $bulkAction, $bulkSlugs );

                foreach ( $bulkSlugs as $bSlug ) {
                    $bSlug = (string) $bSlug;
                    try {
                        switch ( $bulkAction ) {
                            case 'publish':
                                $pages->update( $bSlug, ['status' => 'published'] );
                                break;
                            case 'unpublish':
                                $pages->update( $bSlug, ['status' => 'draft'] );
                                break;
                            case 'delete':
                                if ( $bSlug === KLYTOS_HOME_SLUG ) {
                                    throw new \RuntimeException( __( 'pages.delete_home_reason' ) );
                                }
                                $pages->delete( $bSlug );
                                break;
                            case 'change_template':
                                if ( $bulkTemplate === '' ) {
                                    throw new \RuntimeException( __( 'pages.bulk_template_missing' ) );
                                }
                                $pages->update( $bSlug, ['template' => $bulkTemplate] );
                                break;
                            case 'restore':
                                $pages->restore( $bSlug );
                                break;
                            case 'permanent_delete':
                                $pages->permanentDelete( $bSlug );
                                break;
                            default:
                                // A custom post-type status, applied directly.
                                $pages->update( $bSlug, ['status' => $bulkAction] );
                                break;
                        }
                        $bulkCount++;
                    } catch ( \Throwable $e ) {
                        // "Error — a bulk action partly failed": the list
                        // reloads with a role="alert" summary listing what
                        // failed and why, per record
                        // (template-list-table.md §2).
                        $bulkFail[] = ['slug' => $bSlug, 'reason' => $e->getMessage()];
                    }
                }

                klytos_do_action( 'admin.bulk_action.after', $bulkAction, $bulkCount, $bulkFail );
                $success = __( 'bulk.success', ['count' => $bulkCount] );
            }
            break;
    }
}

// ---------------------------------------------------------------------------
// Reads
// ---------------------------------------------------------------------------

// Custom statuses for the current post type.
$customStatuses = [];
$statusDefs     = [];

if ( $postTypeFilter !== '' ) {
    try {
        $statusDefs     = $app->getPostTypeManager()->getStatusesForPostType( $postTypeFilter );
        $customStatuses = array_filter( $statusDefs, fn( $s ) => empty( $s['system'] ) );
    } catch ( \Throwable $e ) {
        // Fall back to system statuses only.
    }
} else {
    $statusDefs = \Klytos\Core\PostTypeManager::SYSTEM_STATUS_DEFS;
}

$statusView = trim( $_GET['status'] ?? '' );
$searchTerm = trim( $_GET['q'] ?? '' );
$sortColumn = trim( $_GET['sort'] ?? '' );
$sortDir    = ( ( $_GET['dir'] ?? '' ) === 'desc' ) ? 'desc' : 'asc';
$pageNumber = max( 1, (int) ( $_GET['p'] ?? 1 ) );

/*
 * Sorting and searching happen HERE, over the fetched set, rather than in
 * PageManager::list() — a user decision taken at the start of stage 4. The
 * manager's public signature is (status, lang, limit, offset, post_type) with
 * no sort and no search, and widening a released public surface inside a
 * fidelity stage would be a change of a different kind. Recorded so the cost
 * is visible rather than discovered: this fetches the whole status-filtered
 * collection on every request in order to keep the caption's result count and
 * the pagination honest. Pushing both into the storage layer is a Phase 5
 * slice.
 */
const KLYTOS_PAGES_PER_PAGE = 50;

$loadFailed = false;
$allPages   = [];
$totalRows  = 0;
$totalPages = 1;

try {
    $statusQuery = ( $statusView === '' || $statusView === 'all' ) ? 'all' : $statusView;
    $fetchLimit  = max( 1, $pages->count( $statusQuery, $postTypeFilter ) );
    $allPages    = $pages->list( $statusQuery, '', $fetchLimit, 0, $postTypeFilter );

    if ( $searchTerm !== '' ) {
        $needle   = mb_strtolower( $searchTerm );
        $allPages = array_values( array_filter(
            $allPages,
            static function ( array $p ) use ( $needle ): bool {
                $haystack = mb_strtolower(
                    ( $p['title'] ?? '' ) . ' ' . ( $p['slug'] ?? '' )
                );
                return str_contains( $haystack, $needle );
            }
        ) );
    }

    if ( $sortColumn !== '' ) {
        $field = [
            'title'    => 'title',
            'status'   => 'status',
            'template' => 'template',
            'locale'   => 'lang',
            'updated'  => 'updated_at',
        ][ $sortColumn ] ?? '';

        if ( $field !== '' ) {
            usort(
                $allPages,
                static function ( array $a, array $b ) use ( $field, $sortDir ): int {
                    $cmp = strnatcasecmp( (string) ( $a[$field] ?? '' ), (string) ( $b[$field] ?? '' ) );
                    return $sortDir === 'desc' ? -$cmp : $cmp;
                }
            );
        }
    }

    $totalRows  = count( $allPages );
    $totalPages = max( 1, (int) ceil( $totalRows / KLYTOS_PAGES_PER_PAGE ) );
    $pageNumber = min( $pageNumber, $totalPages );
    $allPages   = array_slice( $allPages, ( $pageNumber - 1 ) * KLYTOS_PAGES_PER_PAGE, KLYTOS_PAGES_PER_PAGE );
} catch ( \Throwable $e ) {
    // "Error — the list could not be loaded" (template-list-table.md §2).
    $loadFailed = true;
    klytos_log_error( 'pages.php: the page index could not be read: ' . $e->getMessage() );
}

// Counts for the filter chips.
$chipCounts = [];
foreach ( ['all', 'published', 'draft', 'scheduled', 'trashed'] as $chipStatus ) {
    try {
        $chipCounts[$chipStatus] = $pages->count( $chipStatus, $postTypeFilter );
    } catch ( \Throwable $e ) {
        $chipCounts[$chipStatus] = null;
    }
}

// Templates offered by the "change template" bulk action.
$availableTemplates = [];
try {
    $themeManager = $app->getThemeManager();
    if ( method_exists( $themeManager, 'listTemplates' ) ) {
        $availableTemplates = $themeManager->listTemplates();
    }
} catch ( \Throwable $e ) {
    $availableTemplates = [];
}
if ( empty( $availableTemplates ) ) {
    $availableTemplates = ['default'];
}

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';

// ---------------------------------------------------------------------------
// Render helpers — local to this screen
// ---------------------------------------------------------------------------

/**
 * Build a URL for this screen with a set of query parameters replaced.
 *
 * @param  array<string,string|int|null> $overrides Parameters to set; null removes.
 * @return string
 */
$pagesUrl = static function ( array $overrides = [] ) use ( $postTypeFilter, $statusView, $searchTerm, $sortColumn, $sortDir, $pageNumber ): string {
    $params = array_filter(
        array_merge(
            [
                'post_type' => $postTypeFilter !== '' ? $postTypeFilter : null,
                'status'    => $statusView !== '' ? $statusView : null,
                'q'         => $searchTerm !== '' ? $searchTerm : null,
                'sort'      => $sortColumn !== '' ? $sortColumn : null,
                'dir'       => $sortColumn !== '' ? $sortDir : null,
                'p'         => $pageNumber > 1 ? $pageNumber : null,
            ],
            $overrides
        ),
        static fn( $v ) => $v !== null && $v !== ''
    );

    return 'pages.php' . ( $params ? '?' . http_build_query( $params ) : '' );
};

/**
 * The badge tone for a page status.
 *
 * Manifest §1's delta names three of them outright — Published `exito`, Draft
 * `offline`, Scheduled `sync` — and a fourth, Private `offline`, for a status
 * Klytos does not have (DR-006 addendum). `trashed` is not in the design's set
 * because the design draws no trash; it takes `offline`, which is the tone the
 * delivery already assigns to every not-live state, rather than a new colour
 * chosen here. Custom post-type statuses take the same neutral tone: their
 * `color` field is arbitrary plugin-supplied data whose contrast against a
 * tint cannot be measured, and this build ships no unmeasured pair. The status
 * WORD is always present, so colour is never the only channel
 * (accessibility.md §1.3).
 */
$statusTone = static function ( string $status ): string {
    return match ( $status ) {
        'published' => 'exito',
        'scheduled' => 'sync',
        default     => 'offline',
    };
};

/**
 * The visible label for a page status: the four system statuses through the
 * catalogue, a custom post-type status through its own definition. Defined
 * once because the table row and the under-900 record card must never drift
 * apart — they are two renderings of the same record, and a label that differs
 * between them is a defect no screenshot would catch.
 */
$statusLabelFor = static function ( string $status ) use ( $statusDefs ): string {
    foreach ( $statusDefs as $stDef ) {
        if ( ( $stDef['id'] ?? '' ) === $status && empty( $stDef['system'] ) ) {
            return (string) ( $stDef['label'] ?? $status );
        }
    }

    return match ( $status ) {
        'published' => __( 'pages.published' ),
        'scheduled' => __( 'pages.scheduled' ),
        'trashed'   => __( 'pages.trashed' ),
        'draft'     => __( 'pages.draft' ),
        default     => $status,
    };
};

$sortLink = static function ( string $column ) use ( $pagesUrl, $sortColumn, $sortDir ): array {
    $isActive = ( $sortColumn === $column );
    $nextDir  = ( $isActive && $sortDir === 'asc' ) ? 'desc' : 'asc';

    return [
        'url'      => $pagesUrl( ['sort' => $column, 'dir' => $nextDir, 'p' => null] ),
        'ariaSort' => $isActive ? ( $sortDir === 'asc' ? 'ascending' : 'descending' ) : null,
        'glyph'    => $isActive ? ( $sortDir === 'asc' ? 'ks-expand_less' : 'ks-expand_more' ) : 'ks-unfold_more',
    ];
};

$isFiltered = ( $searchTerm !== '' || ( $statusView !== '' && $statusView !== 'all' ) );
$canWrite   = klytos_has_permission( 'pages.delete' );

/** Columns after the row header, reused by the table and by the stacked cards. */
$dataColumns = [
    'status'   => __( 'common.status' ),
    'template' => __( 'pages.template' ),
    'locale'   => __( 'pages.language' ),
    'updated'  => __( 'pages.last_edit' ),
];
?>
<?php klytos_do_action( 'admin.pages.before' ); ?>

<?php if ( $success !== '' ) : ?>
    <?php /* "Success" state: text in the flow, not a floating toast, so it
             survives a screenshot and a screen reader; it disappears on the
             next navigation, not on a timer (template-list-table.md §2). */ ?>
    <p class="k-status-line" role="status" data-testid="pages.status_line">
        <?php echo klytos_esc_html( $success ); ?>
    </p>
<?php endif; ?>

<?php if ( $error !== '' ) : ?>
    <p class="k-status-line k-status-line--aviso" role="alert" data-testid="pages.error_line">
        <?php echo klytos_esc_html( $error ); ?>
    </p>
<?php endif; ?>

<?php // Filter row: height 42, gap 8 — chips + search (template-list-table.md §1). ?>
<div class="k-filters">
    <nav aria-label="<?php echo klytos_esc_attr( __( 'pages.filter_label' ) ); ?>" class="k-filters">
        <?php
        /*
         * The four chips the design names that map to a real Klytos status,
         * plus Trash and any custom post-type statuses. "Private" is NOT
         * rendered: manifest §1 lists it and this codebase has exactly four
         * system statuses, none of them private, so the chip would be a filter
         * that can never return a row. Registered as DR-006 item 4 rather than
         * shipped broken — the same call D-075 made for Comments and Health in
         * the sidebar.
         */
        $chips = [
            ''          => __( 'pages.tab_all' ),
            'published' => __( 'pages.published' ),
            'draft'     => __( 'pages.draft' ),
            'scheduled' => __( 'pages.scheduled' ),
        ];
        foreach ( $customStatuses as $csSt ) {
            $chips[ (string) ( $csSt['id'] ?? '' ) ] = (string) ( $csSt['label'] ?? '' );
        }
        $chips['trashed'] = __( 'pages.tab_trash' );

        foreach ( $chips as $chipValue => $chipLabel ) :
            if ( $chipLabel === '' ) {
                continue;
            }
            $chipSelected = ( $statusView === $chipValue ) || ( $chipValue === '' && ( $statusView === '' || $statusView === 'all' ) );
            $chipCount    = $chipCounts[ $chipValue === '' ? 'all' : $chipValue ] ?? null;
            ?>
            <a class="k-chip"
               href="<?php echo klytos_esc_url( $pagesUrl( ['status' => $chipValue !== '' ? $chipValue : null, 'p' => null] ) ); ?>"
               <?php echo $chipSelected ? 'aria-current="true"' : ''; ?>
               data-testid="pages.chip.<?php echo klytos_esc_attr( $chipValue !== '' ? $chipValue : 'all' ); ?>">
                <?php echo klytos_esc_html( $chipLabel ); ?>
                <?php if ( $chipCount !== null ) : ?>
                    <span class="k-num"><?php echo (int) $chipCount; ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php // "Clear filters" appears whenever any filter is not the default. ?>
    <?php if ( $isFiltered ) : ?>
        <a href="pages.php<?php echo $postTypeFilter !== '' ? '?post_type=' . urlencode( $postTypeFilter ) : ''; ?>"
           data-testid="pages.clear_filters">
            <?php echo klytos_esc_html( __( 'list.clear_filters' ) ); ?>
        </a>
    <?php endif; ?>

    <form method="get" action="pages.php" role="search" class="k-filters">
        <?php if ( $postTypeFilter !== '' ) : ?>
            <input type="hidden" name="post_type" value="<?php echo klytos_esc_attr( $postTypeFilter ); ?>">
        <?php endif; ?>
        <?php if ( $statusView !== '' ) : ?>
            <input type="hidden" name="status" value="<?php echo klytos_esc_attr( $statusView ); ?>">
        <?php endif; ?>
        <label class="k-sr" for="pages-search"><?php echo klytos_esc_html( __( 'pages.search_label' ) ); ?></label>
        <input class="k-control" type="search" id="pages-search" name="q"
               value="<?php echo klytos_esc_attr( $searchTerm ); ?>"
               placeholder="<?php echo klytos_esc_attr( __( 'common.search' ) ); ?>"
               data-testid="pages.search">
        <button type="submit" class="k-btn k-btn--secondary" data-testid="pages.search_submit">
            <?php echo klytos_esc_html( __( 'common.search' ) ); ?>
        </button>
    </form>
</div>

<style nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
/*
 * `grid-template-columns` is PER SCREEN (template-list-table.md §1), so it
 * lives here, on the screen that owns it, and not in klytos-components.css.
 * The value is quoted verbatim from SPEC/manifest.md §1:
 *
 *   - `grid-template-columns: 28px 1fr 116px 132px 96px 132px 44px`
 *
 * matching its column list: checkbox · Title (row header) · Status · Template ·
 * Locale · Last edit (num) · actions. It is the ONLY such value in the whole
 * delivery — the other twelve list surfaces are blocked on DR-006 — so nothing
 * here is derived, interpolated or taken from a prototype drawing. The Pages
 * prototype in `Klytos Admin - Screens.dc.html` draws SIX tracks and the
 * manifest gives SEVEN; the manifest wins because it is the SPEC and it is
 * screen-specific, and DR-006 asks Design to confirm that reading.
 *
 * The :not() is load-bearing, not tidiness. `.k-table-row-full` is (0,1,0) and
 * carries `grid-template-columns:1fr` for the empty, filtered-empty and error
 * rows that span every column. A bare `.k-pages-table tr` is (0,1,1) and would
 * silently outrank it, collapsing those three states into a seven-column grid
 * with the sentence stuck in the first 28px. That is build rule 1's third
 * mechanism — specificity, not source order — and L-032's rule is to never
 * assume which one wins, so this was read back out of the browser.
 */
.k-pages-table tr:not(.k-table-row-full) {
    grid-template-columns: 28px 1fr 116px 132px 96px 132px 44px;
}
</style>

<form method="post" id="pages-bulk-form" data-testid="pages.bulk_form">
    <?php echo klytos_csrf_field(); ?>
    <input type="hidden" name="action" value="bulk_action">

    <div class="k-card k-card--table">
        <?php
        /*
         * The caption is VISIBLE — it is the table card's heading row, not a
         * screen-reader-only crumb — and it carries the result count and the
         * page position. aria-live="polite" announces the new count once after
         * a filter change (accessibility.md §2.1).
         */
        ?>
        <?php
        /*
         * The horizontal scroll container. Below 1200px the table scrolls
         * inside it, and it carries tabindex="0" + role="group" with an
         * aria-label so a keyboard user can reach that scroll
         * (accessibility.md §2.1, template-list-table.md §3). It exists at
         * every width; only the overflow behaviour changes.
         *
         * It is also what carries the table OUT of the page under 900px, where
         * the stacked record cards replace it — so it is not optional chrome:
         * omitting it left a seven-column table on a 320px viewport and the
         * page scrolled 349px, a WCAG 1.4.10 reflow failure.
         *
         * position:relative on this element is load-bearing for a second
         * reason recorded in klytos-components.css: it contains the
         * absolutely-positioned .k-sr spans that §2.1's own markup puts in the
         * header row.
         *
         * `tabindex="0"` is UNCONDITIONAL, where §2.1 words it as "when the
         * table scrolls horizontally below 1200px". The server has no viewport,
         * so the only alternatives were adding the attribute with JavaScript on
         * resize — which would make the keyboard path to the scroll depend on
         * JS, and nothing else in this shell does — or omitting it, which would
         * remove that path entirely at the width that needs it. An always-
         * focusable labelled group at a width where it happens not to scroll is
         * a superset of the spec, not a departure from it. Logged as
         * adaptation 12 in docs/BUILD-SPEC.md §5.9.
         */
        ?>
        <div class="k-table-scroll" tabindex="0" role="group"
             aria-label="<?php echo klytos_esc_attr( __( 'pages.table_scroll_label' ) ); ?>"
             data-testid="pages.table_scroll">
        <table class="k-table k-pages-table" role="table" aria-labelledby="pages-caption" data-testid="pages.table">
            <caption class="k-table-caption" id="pages-caption" aria-live="polite">
                <span data-testid="pages.caption_text">
                    <?php
                    echo klytos_esc_html( __( 'pages.caption', [
                        'count' => (string) $totalRows,
                        'page'  => (string) $pageNumber,
                        'pages' => (string) $totalPages,
                    ] ) );
                    ?>
                </span>
                <?php if ( $statusView !== 'trashed' && $canWrite ) : ?>
                    <a class="k-btn k-btn--primary"
                       href="page-editor.php<?php echo $postTypeFilter !== '' ? '?post_type=' . urlencode( $postTypeFilter ) : ''; ?>"
                       data-testid="pages.create">
                        <?php echo klytos_esc_html( __( 'pages.create_page' ) ); ?>
                    </a>
                <?php endif; ?>
            </caption>

            <?php
            /*
             * The scroll container carries tabindex="0" and role="group" with
             * an aria-label so keyboard users can reach the horizontal scroll
             * below 1200px (accessibility.md §2.1). It wraps nothing at wider
             * widths — see .k-table-scroll.
             *
             * grid-template-columns is quoted verbatim from SPEC/manifest.md
             * §1. It is the only such value in the whole delivery; the other
             * twelve list surfaces are blocked on DR-006.
             */
            ?>
            <thead role="rowgroup">
                <tr role="row" class="k-table-row">
                    <?php if ( $canWrite ) : ?>
                        <th role="columnheader" scope="col" class="k-col-check">
                            <input type="checkbox" id="pages-select-all"
                                   aria-label="<?php echo klytos_esc_attr( __( 'pages.select_all' ) ); ?>"
                                   data-testid="pages.select_all">
                        </th>
                    <?php else : ?>
                        <th role="columnheader" scope="col" class="k-col-check"><span class="k-sr"><?php echo klytos_esc_html( __( 'bulk.select_all' ) ); ?></span></th>
                    <?php endif; ?>

                    <?php $titleSort = $sortLink( 'title' ); ?>
                    <th role="columnheader" scope="col"
                        <?php echo $titleSort['ariaSort'] !== null ? 'aria-sort="' . klytos_esc_attr( $titleSort['ariaSort'] ) . '"' : ''; ?>>
                        <a href="<?php echo klytos_esc_url( $titleSort['url'] ); ?>" data-testid="pages.sort.title">
                            <?php echo klytos_esc_html( __( 'pages.page_title' ) ); ?>
                            <?php klytos_admin_icon( $spriteUrl, $titleSort['glyph'], 'k-sort-icon' ); ?>
                        </a>
                    </th>

                    <?php foreach ( $dataColumns as $colKey => $colLabel ) : ?>
                        <?php $colSort = $sortLink( $colKey ); ?>
                        <th role="columnheader" scope="col"
                            class="<?php echo $colKey === 'updated' ? 'k-num' : ''; ?>"
                            <?php echo $colSort['ariaSort'] !== null ? 'aria-sort="' . klytos_esc_attr( $colSort['ariaSort'] ) . '"' : ''; ?>>
                            <a href="<?php echo klytos_esc_url( $colSort['url'] ); ?>"
                               data-testid="pages.sort.<?php echo klytos_esc_attr( $colKey ); ?>">
                                <?php echo klytos_esc_html( $colLabel ); ?>
                                <?php klytos_admin_icon( $spriteUrl, $colSort['glyph'], 'k-sort-icon' ); ?>
                            </a>
                        </th>
                    <?php endforeach; ?>

                    <?php // Icon-only action column: the header is present and named for assistive technology. ?>
                    <th role="columnheader" scope="col"><span class="k-sr"><?php echo klytos_esc_html( __( 'common.actions' ) ); ?></span></th>
                </tr>
            </thead>

            <tbody role="rowgroup">
                <?php if ( $loadFailed ) : ?>
                    <?php /* "Error — the list could not be loaded": the card renders an
                             error row IN PLACE of the rows, with a retry link that is a
                             plain reload of the current URL. The design's second link,
                             "Open Health", is NOT rendered — Health is manifest entry 22
                             and is deferred (D-072), so `health.php` does not exist and
                             the link would 404. Same call D-075 made in the sidebar. */ ?>
                    <tr role="row" class="k-table-row-full">
                        <td role="cell" class="k-empty k-empty--error" data-testid="pages.error_state">
                            <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-empty-icon' ); ?>
                            <span class="k-empty-text"><?php echo klytos_esc_html( __( 'pages.error_sentence' ) ); ?></span>
                            <a href="<?php echo klytos_esc_url( $pagesUrl() ); ?>" data-testid="pages.retry">
                                <?php echo klytos_esc_html( __( 'list.retry' ) ); ?>
                            </a>
                        </td>
                    </tr>
                <?php elseif ( empty( $allPages ) && $isFiltered ) : ?>
                    <?php /* "Empty — filtered to nothing": a different sentence, a
                             different action, and it never suggests creating a record
                             (template-list-table.md §2). */ ?>
                    <tr role="row" class="k-table-row-full">
                        <td role="cell" class="k-empty" data-testid="pages.empty_filtered">
                            <?php klytos_admin_icon( $spriteUrl, 'ks-search_off', 'k-empty-icon' ); ?>
                            <span class="k-empty-text">
                                <?php
                                $activeFilters = [];
                                if ( $statusView !== '' && $statusView !== 'all' ) {
                                    $activeFilters[] = $chips[$statusView] ?? $statusView;
                                }
                                if ( $searchTerm !== '' ) {
                                    $activeFilters[] = $searchTerm;
                                }
                                echo klytos_esc_html( __( 'pages.empty_filtered', [
                                    'filters' => implode( ', ', $activeFilters ),
                                ] ) );
                                ?>
                            </span>
                            <a href="pages.php<?php echo $postTypeFilter !== '' ? '?post_type=' . urlencode( $postTypeFilter ) : ''; ?>"
                               data-testid="pages.empty_clear_filters">
                                <?php echo klytos_esc_html( __( 'list.clear_filters' ) ); ?>
                            </a>
                        </td>
                    </tr>
                <?php elseif ( empty( $allPages ) ) : ?>
                    <?php /* "Empty — no records at all": the table keeps its header row
                             and renders a single row spanning all columns, 120px tall,
                             centred — a 20px --texto-sutil icon, one sentence, one
                             primary action. Never a bare "No results". */ ?>
                    <tr role="row" class="k-table-row-full">
                        <td role="cell" class="k-empty" data-testid="pages.empty">
                            <?php klytos_admin_icon( $spriteUrl, 'ks-description', 'k-empty-icon' ); ?>
                            <span class="k-empty-text">
                                <?php echo klytos_esc_html( $statusView === 'trashed' ? __( 'pages.trash_empty' ) : __( 'pages.empty_sentence' ) ); ?>
                            </span>
                            <?php if ( $statusView !== 'trashed' && $canWrite ) : ?>
                                <a class="k-btn k-btn--primary"
                                   href="page-editor.php<?php echo $postTypeFilter !== '' ? '?post_type=' . urlencode( $postTypeFilter ) : ''; ?>"
                                   data-testid="pages.empty_action">
                                    <?php echo klytos_esc_html( __( 'pages.empty_action' ) ); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $allPages as $page ) : ?>
                        <?php
                        $rowSlug   = (string) ( $page['slug'] ?? '' );
                        $rowTitle  = (string) ( $page['title'] ?? '' );
                        $rowStatus = (string) ( $page['status'] ?? 'draft' );
                        $rowId     = 'page-row-' . preg_replace( '/[^a-z0-9_-]/i', '-', $rowSlug );
                        $isHome    = ( $rowSlug === KLYTOS_HOME_SLUG );
                        $rowUpdated = (string) ( $page['updated_at'] ?? '' );

                        $statusLabel = $statusLabelFor( $rowStatus );
                        ?>
                        <tr role="row" class="k-table-row" data-testid="pages.row.<?php echo klytos_esc_attr( $rowSlug ); ?>">
                            <td role="cell" class="k-col-check">
                                <?php if ( $canWrite ) : ?>
                                    <?php // Row checkboxes are labelled BY the row header's id, never by an invented "Select row 3". ?>
                                    <input type="checkbox" class="k-row-check" name="bulk_slugs[]"
                                           value="<?php echo klytos_esc_attr( $rowSlug ); ?>"
                                           aria-labelledby="<?php echo klytos_esc_attr( $rowId ); ?>"
                                           data-testid="pages.check.<?php echo klytos_esc_attr( $rowSlug ); ?>">
                                <?php endif; ?>
                            </td>

                            <?php // The column that names the record is a <th role="rowheader" scope="row">, not a <td>. ?>
                            <th role="rowheader" scope="row" id="<?php echo klytos_esc_attr( $rowId ); ?>">
                                <a href="page-editor.php?slug=<?php echo urlencode( $rowSlug ); ?>"
                                   data-testid="pages.title.<?php echo klytos_esc_attr( $rowSlug ); ?>">
                                    <?php echo klytos_esc_html( $rowTitle !== '' ? $rowTitle : $rowSlug ); ?>
                                </a>
                            </th>

                            <td role="cell">
                                <span class="k-badge k-badge--<?php echo klytos_esc_attr( $statusTone( $rowStatus ) ); ?>">
                                    <?php echo klytos_esc_html( $statusLabel ); ?>
                                </span>
                            </td>

                            <td role="cell"><?php echo klytos_esc_html( (string) ( $page['template'] ?? 'default' ) ); ?></td>

                            <td role="cell">
                                <?php $rowLang = (string) ( $page['lang'] ?? '' ); ?>
                                <?php if ( $rowLang !== '' ) : ?>
                                    <span lang="<?php echo klytos_esc_attr( $rowLang ); ?>"><?php echo klytos_esc_html( $rowLang ); ?></span>
                                <?php else : ?>
                                    <span aria-hidden="true">—</span><span class="k-sr"><?php echo klytos_esc_html( __( 'list.not_set' ) ); ?></span>
                                <?php endif; ?>
                            </td>

                            <td role="cell" class="k-num">
                                <?php if ( $rowUpdated !== '' ) : ?>
                                    <time datetime="<?php echo klytos_esc_attr( $rowUpdated ); ?>">
                                        <?php echo klytos_esc_html( klytos_format_datetime( $rowUpdated, 'j M, H:i' ) ); ?>
                                    </time>
                                <?php else : ?>
                                    <span aria-hidden="true">—</span><span class="k-sr"><?php echo klytos_esc_html( __( 'list.not_set' ) ); ?></span>
                                <?php endif; ?>
                            </td>

                            <td role="cell">
                                <?php
                                /*
                                 * Icon-only action cells carry an aria-label naming the
                                 * RECORD, not the icon (accessibility.md §2.1). A
                                 * "disabled row action" is rendered disabled with
                                 * aria-disabled="true" and the reason in its accessible
                                 * name, never hidden — hiding an action teaches nothing
                                 * (template-list-table.md §2).
                                 */
                                ?>
                                <?php if ( $isHome && $statusView !== 'trashed' ) : ?>
                                    <span class="k-hit-24 k-is-disabled" aria-disabled="true"
                                          role="link"
                                          aria-label="<?php echo klytos_esc_attr( __( 'pages.delete_home_reason' ) ); ?>"
                                          data-testid="pages.actions_disabled.<?php echo klytos_esc_attr( $rowSlug ); ?>">
                                        <?php klytos_admin_icon( $spriteUrl, 'ks-more_horiz', 'k-row-action-icon' ); ?>
                                    </span>
                                <?php else : ?>
                                    <a class="k-hit-24"
                                       href="page-editor.php?slug=<?php echo urlencode( $rowSlug ); ?>"
                                       aria-label="<?php echo klytos_esc_attr( __( 'pages.row_actions', ['title' => $rowTitle !== '' ? $rowTitle : $rowSlug] ) ); ?>"
                                       data-testid="pages.actions.<?php echo klytos_esc_attr( $rowSlug ); ?>">
                                        <?php klytos_admin_icon( $spriteUrl, 'ks-more_horiz', 'k-row-action-icon' ); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div><!-- /.k-table-scroll -->

        <?php
        /*
         * Under 900px the table is replaced by stacked record cards — one
         * <article> per record, the row header as its <h3>, the remaining
         * columns as a <dl>. The ARIA table roles go away with the table,
         * because a definition list is honest about what it is
         * (template-list-table.md §3). Exactly one of the two markups is in
         * the accessibility tree at any width: klytos-components.css hides the
         * other with display:none, which removes it from that tree too.
         */
        ?>
        <div class="k-reclist" data-testid="pages.reclist">
            <?php foreach ( $allPages as $page ) : ?>
                <?php
                $recSlug   = (string) ( $page['slug'] ?? '' );
                $recTitle  = (string) ( $page['title'] ?? '' );
                $recStatus = (string) ( $page['status'] ?? 'draft' );
                $recUpdated = (string) ( $page['updated_at'] ?? '' );
                $recStatusLabel = $statusLabelFor( $recStatus );
                ?>
                <article class="k-rec" data-testid="pages.rec.<?php echo klytos_esc_attr( $recSlug ); ?>">
                    <h3 class="k-rec-title">
                        <a href="page-editor.php?slug=<?php echo urlencode( $recSlug ); ?>">
                            <?php echo klytos_esc_html( $recTitle !== '' ? $recTitle : $recSlug ); ?>
                        </a>
                    </h3>
                    <dl class="k-rec-dl">
                        <dt><?php echo klytos_esc_html( __( 'common.status' ) ); ?></dt>
                        <dd>
                            <span class="k-badge k-badge--<?php echo klytos_esc_attr( $statusTone( $recStatus ) ); ?>">
                                <?php echo klytos_esc_html( $recStatusLabel ); ?>
                            </span>
                        </dd>
                        <dt><?php echo klytos_esc_html( __( 'pages.template' ) ); ?></dt>
                        <dd><?php echo klytos_esc_html( (string) ( $page['template'] ?? 'default' ) ); ?></dd>
                        <dt><?php echo klytos_esc_html( __( 'pages.language' ) ); ?></dt>
                        <dd><?php echo klytos_esc_html( (string) ( $page['lang'] ?? '' ) ); ?></dd>
                        <dt><?php echo klytos_esc_html( __( 'pages.last_edit' ) ); ?></dt>
                        <dd>
                            <?php if ( $recUpdated !== '' ) : ?>
                                <time datetime="<?php echo klytos_esc_attr( $recUpdated ); ?>"><?php echo klytos_esc_html( klytos_format_datetime( $recUpdated, 'j M, H:i' ) ); ?></time>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ( $totalPages > 1 ) : ?>
            <?php // Pagination is a <nav aria-label="Pagination"> of LINKS, never buttons; the current page is aria-current="page" and is not a link. ?>
            <nav class="k-pagination" aria-label="<?php echo klytos_esc_attr( __( 'list.pagination' ) ); ?>" data-testid="pages.pagination">
                <?php if ( $pageNumber > 1 ) : ?>
                    <a href="<?php echo klytos_esc_url( $pagesUrl( ['p' => $pageNumber - 1] ) ); ?>" data-testid="pages.page_prev">
                        <?php echo klytos_esc_html( __( 'list.previous' ) ); ?>
                    </a>
                <?php endif; ?>
                <?php for ( $p = 1; $p <= $totalPages; $p++ ) : ?>
                    <?php if ( $p === $pageNumber ) : ?>
                        <span aria-current="page" data-testid="pages.page_current"><?php echo (int) $p; ?></span>
                    <?php else : ?>
                        <a href="<?php echo klytos_esc_url( $pagesUrl( ['p' => $p] ) ); ?>"
                           aria-label="<?php echo klytos_esc_attr( __( 'list.page_n', ['n' => (string) $p] ) ); ?>"
                           data-testid="pages.page.<?php echo (int) $p; ?>"><?php echo (int) $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ( $pageNumber < $totalPages ) : ?>
                    <a href="<?php echo klytos_esc_url( $pagesUrl( ['p' => $pageNumber + 1] ) ); ?>" data-testid="pages.page_next">
                        <?php echo klytos_esc_html( __( 'list.next' ) ); ?>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>

    <?php
    /*
     * The bulk bar: selecting ≥ 1 row raises it. 48px, pinned to the bottom of
     * the content area, --fondo-elevado + card shadow, holding "n selected",
     * the actions and "Clear". The content area gains 48px bottom padding so
     * the bar never covers a focused row. It is part of the same <form> and
     * its actions are submit buttons (template-list-table.md §2).
     */
    ?>
    <?php if ( $canWrite && ! empty( $allPages ) ) : ?>
        <div class="k-bulkbar" id="pages-bulkbar" hidden data-testid="pages.bulkbar">
            <span class="k-bulkbar-count" id="pages-bulkbar-count" role="status" data-testid="pages.bulkbar_count"></span>

            <label class="k-sr" for="pages-bulk-action"><?php echo klytos_esc_html( __( 'bulk.action_label' ) ); ?></label>
            <select class="k-control" name="bulk_action" id="pages-bulk-action" data-testid="pages.bulk_action">
                <?php if ( $statusView === 'trashed' ) : ?>
                    <option value="restore"><?php echo klytos_esc_html( __( 'pages.restore' ) ); ?></option>
                    <option value="permanent_delete"><?php echo klytos_esc_html( __( 'pages.permanent_delete' ) ); ?></option>
                <?php else : ?>
                    <?php
                    // Manifest §1: publish, unpublish, delete, change template.
                    $bulkActions = [
                        'publish'         => __( 'pages.published' ),
                        'unpublish'       => __( 'pages.bulk_unpublish' ),
                        'delete'          => __( 'common.delete' ),
                        'change_template' => __( 'pages.bulk_change_template' ),
                    ];
                    foreach ( $customStatuses as $csBulk ) {
                        $bulkActions[ (string) ( $csBulk['id'] ?? '' ) ] = (string) ( $csBulk['label'] ?? '' );
                    }
                    $bulkActions = klytos_apply_filters( 'pages.bulk_actions', $bulkActions );
                    foreach ( $bulkActions as $bVal => $bLabel ) :
                        if ( (string) $bVal === '' || (string) $bLabel === '' ) {
                            continue;
                        }
                        ?>
                        <option value="<?php echo klytos_esc_attr( (string) $bVal ); ?>"><?php echo klytos_esc_html( (string) $bLabel ); ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <?php if ( $statusView !== 'trashed' ) : ?>
                <label class="k-sr" for="pages-bulk-template"><?php echo klytos_esc_html( __( 'pages.bulk_template_label' ) ); ?></label>
                <select class="k-control" name="bulk_template" id="pages-bulk-template" data-testid="pages.bulk_template">
                    <?php foreach ( $availableTemplates as $tplValue ) : ?>
                        <?php $tplValue = is_array( $tplValue ) ? (string) ( $tplValue['id'] ?? '' ) : (string) $tplValue; ?>
                        <?php if ( $tplValue !== '' ) : ?>
                            <option value="<?php echo klytos_esc_attr( $tplValue ); ?>"><?php echo klytos_esc_html( $tplValue ); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <button type="submit" class="k-btn k-btn--primary" data-testid="pages.bulk_apply">
                <?php echo klytos_esc_html( __( 'bulk.apply' ) ); ?>
            </button>
            <button type="button" class="k-btn k-btn--secondary" id="pages-bulk-clear" data-testid="pages.bulk_clear">
                <?php echo klytos_esc_html( __( 'list.clear_selection' ) ); ?>
            </button>
        </div>
    <?php endif; ?>
</form>

<?php if ( $statusView === 'trashed' && ( $chipCounts['trashed'] ?? 0 ) > 0 && $canWrite ) : ?>
    <form method="post" id="pages-empty-trash-form">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="empty_trash">
        <button type="submit" class="k-btn k-btn--destructive" data-testid="pages.empty_trash">
            <?php echo klytos_esc_html( __( 'pages.empty_trash' ) ); ?>
        </button>
    </form>
<?php endif; ?>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function () {
    'use strict';

    var selectAll = document.getElementById( 'pages-select-all' );
    var bulkbar   = document.getElementById( 'pages-bulkbar' );
    var countEl   = document.getElementById( 'pages-bulkbar-count' );
    var clearBtn  = document.getElementById( 'pages-bulk-clear' );
    var boxes     = Array.prototype.slice.call( document.querySelectorAll( '.k-row-check' ) );
    var countTpl  = <?php echo json_encode( __( 'list.selected' ) ); ?>;

    if ( ! bulkbar || ! boxes.length ) {
        return;
    }

    function selected() {
        return boxes.filter( function ( b ) { return b.checked; } );
    }

    function sync() {
        var n = selected().length;

        // "Row selected" — aria-selected on the <tr>, which is what carries
        // --fila-seleccion (template-list-table.md §2).
        boxes.forEach( function ( b ) {
            var row = b.closest( 'tr' );
            if ( row ) {
                row.setAttribute( 'aria-selected', b.checked ? 'true' : 'false' );
            }
        } );

        bulkbar.hidden = ( n === 0 );

        // "The content area gains 48px bottom padding so the bar never covers a
        // focused row" (template-list-table.md §2) — the CONTENT AREA, which is
        // <main class="k-main">, not <body>. Toggling it on body did nothing
        // visible because body's padding is not what positions the rows, and
        // the test read 36px where the contract says 48.
        var main = document.querySelector( '.k-main' ) || document.body;
        main.classList.toggle( 'k-has-bulkbar', n > 0 );
        countEl.textContent = countTpl.replace( '{count}', String( n ) );

        if ( selectAll ) {
            // The select-all indeterminate state is set with the DOM property
            // AND aria-checked="mixed" (accessibility.md §2.1).
            var mixed = ( n > 0 && n < boxes.length );
            selectAll.indeterminate = mixed;
            selectAll.checked = ( n === boxes.length );
            if ( mixed ) {
                selectAll.setAttribute( 'aria-checked', 'mixed' );
            } else {
                selectAll.removeAttribute( 'aria-checked' );
            }
        }
    }

    boxes.forEach( function ( b ) {
        b.addEventListener( 'change', sync );
    } );

    if ( selectAll ) {
        selectAll.addEventListener( 'change', function () {
            boxes.forEach( function ( b ) { b.checked = selectAll.checked; } );
            sync();
        } );
    }

    if ( clearBtn ) {
        clearBtn.addEventListener( 'click', function () {
            boxes.forEach( function ( b ) { b.checked = false; } );
            if ( selectAll ) {
                selectAll.checked = false;
            }
            sync();
        } );
    }

    sync();
}());
</script>

<?php klytos_do_action( 'admin.pages.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
