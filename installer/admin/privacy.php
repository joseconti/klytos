<?php

/**
 * Klytos Admin — Privacy (GDPR data export and erasure)
 *
 * Manifest entry 26 · template `record-form` (+ list-table, deferred) ·
 * H1 "Privacy". Built in Phase 4 Step 4, stage 5 (the form screens) against
 * `SPEC/screens/template-record-form.md`, `SPEC/manifest.md` §26 and
 * `SPEC/accessibility.md`.
 *
 * WHAT THE PER-SCREEN SURVEY FOUND, run against `PrivacyManager`, `AuditLog`
 * and `TaskManager` BEFORE the first line — the rule D-089 earned, and the
 * SEVENTH time it has disagreed with the stage-wide survey:
 *
 *   - **Export requests** and **Erasure requests** are backed and are built.
 *     The manifest's names are queue language and the product has no queue —
 *     nothing anywhere stores a "request". Each card is therefore the flow the
 *     product does have, under the manifest's own heading (adaptation 44).
 *   - **Per-section method and status** (the list-table card) is NOT merely
 *     blocked by DR-006's missing widths. It is unbacked in four independent
 *     ways and is deferred to `docs/roadmap.md` §0c:
 *       · there is no site-wide section registry — `collectErasableData()`
 *         requires a `$userId` and the `privacy.erasable_data` filter is passed
 *         a user, so the product cannot enumerate sections without naming a
 *         person;
 *       · **`Last run` is stored nowhere**. No per-section erasure timestamp
 *         exists in any collection;
 *       · the status vocabulary `Automatic` / `Manual` / `Not covered` maps to
 *         nothing. The product's per-section vocabulary is `erasure_method`
 *         ∈ {delete, anonymize} plus an `erasable` flag with a
 *         `retention_reason` — three axes, none of which is "Manual";
 *       · the delta "a section that is Not covered is a task, and it appears on
 *         Tasks" needs a generator. Tasks are explicitly created records
 *         (`TaskManager::create()`) and nothing derives one from privacy
 *         coverage.
 *
 * THE FINDING, and it is why this screen was rewritten rather than restyled.
 * The shipped screen built a per-section erasure result table — what was
 * anonymized, what was deleted, what was skipped and why — and **that table has
 * never rendered on any install since it shipped**. `$foundUser` was assigned in
 * exactly one branch (`search_user`) while the results block sat nested inside
 * `if ( $foundUser !== null )`, so on the `erase_data` POST it was always null
 * and the whole card was skipped. Somebody who had just irreversibly erased
 * another person's data was shown one green sentence and nothing else.
 * Reproduced first, driven, and pinned by `tests/E2E/privacy.spec.js`.
 *
 * The fix is that the result table no longer depends on the subject at all: it
 * renders on `$erasureResults`, which is the thing it is about. Re-resolving the
 * subject is a separate, smaller improvement and is tested separately — a
 * distinction established by planting each one back, not by reading.
 *
 * @package Klytos
 * @since   0.18.0
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

$pageTitle      = __( 'privacy.title' );
$privacyManager = $app->getPrivacyManager();
$auth           = $app->getAuth();

$success = '';

/** @var array<string,string> Field-level errors, keyed by control name. */
$fieldErrors = [];
/** @var array<int,array{anchor:string,message:string}> The error summary's rows, in DOM order. */
$summaryRows = [];

/*
 * The two cards are INDEPENDENT flows over one page, each holding its own
 * subject. The shipped screen used tabs and a single `$foundUser`; the manifest
 * specifies a card stack, and one shared subject would have meant an export
 * search silently re-pointing the erasure card at a different person.
 */
$exportUser     = null;
$exportSections = null;
$exportQuery    = '';

$erasureUser      = null;
$erasableSections = null;
$erasureResults   = null;
$erasureQuery     = '';

/** @var array<int,string> The sections carried through the two-step confirm. */
$armedSections = [];
/** @var bool True while the confirm step is showing (§2 destructive section). */
$isArmed = false;

/**
 * The statuses `eraseUserData()` can return, and the word each one shows.
 *
 * An explicit map and not `__( 'privacy.' . $status )`: the manager returns
 * `deleted` for form submissions and the catalogue had no `privacy.deleted`, so
 * the most common erasure of all would have rendered its own key as its label.
 * That defect was unreachable only because the table above it never rendered.
 *
 * @var array<string,string>
 */
$statusLabels = klytos_apply_filters( 'privacy.status_labels', [
    'anonymized' => 'privacy.anonymized',
    'deleted'    => 'privacy.deleted',
    'erased'     => 'privacy.erased',
    'skipped'    => 'privacy.skipped',
] );

/**
 * The badge tone per status. Colour is never the only channel — the word above
 * carries the meaning (`SPEC/accessibility.md` §1.3).
 *
 * @var array<string,string>
 */
$statusTones = [
    'anonymized' => 'k-badge--info',
    'deleted'    => 'k-badge--exito',
    'erased'     => 'k-badge--exito',
    'skipped'    => 'k-badge--aviso',
];

/**
 * Resolve a section id to the label a person recognises.
 *
 * The results the manager returns carry the section ID (`core:audit_log`) and
 * never the label, so the shipped table would have printed internal
 * identifiers. The map is built from the erasable list BEFORE the erasure, which
 * is the only moment it still describes what was there.
 *
 * @param  array<string,string> $labels Map of section id → label.
 * @param  string               $id     Section id.
 * @return string
 */
$sectionLabel = static function ( array $labels, string $id ): string {
    return $labels[ $id ] ?? $id;
};

/**
 * The three things an export can do, and the button each one gets.
 *
 * Deliberately NOT filterable, unlike `privacy.status_labels` above. The POST
 * handler dispatches on a closed list of three actions, so a plugin-added entry
 * here would render a button that does nothing — a control declared and not
 * delivered. A fourth delivery route is a change to both halves or it is not a
 * change at all.
 *
 * @var array<string,array{0:string,1:string}> action => [ label key, button class ]
 */
$exportActions = [
    'export_json'       => [ 'privacy.export_json', 'k-btn--primary' ],
    'export_html'       => [ 'privacy.export_html', 'k-btn--secondary' ],
    'send_export_email' => [ 'privacy.export_email', 'k-btn--secondary' ],
];

// ─── Handle POST ────────────────────────────────────────────────

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $action = (string) ( $_POST['action'] ?? '' );

    if ( $action === 'export_search' ) {
        $exportQuery = trim( (string) ( $_POST['export_query'] ?? '' ) );
        $exportUser  = $exportQuery === '' ? null : $privacyManager->findUser( $exportQuery );

        if ( $exportUser === null ) {
            $fieldErrors['export_query'] = __( 'privacy.user_not_found' );
            $summaryRows[]               = [
                'anchor'  => 'privacy-field-export_query',
                'message' => __( 'privacy.summary_user_not_found' ),
            ];
        } else {
            try {
                $exportSections = $privacyManager->collectUserData( $exportUser['id'] )['sections'] ?? [];
            } catch ( \Throwable $e ) {
                $summaryRows[] = [ 'anchor' => 'privacy-export-heading', 'message' => $e->getMessage() ];
            }
        }
    } elseif ( in_array( $action, [ 'export_json', 'export_html', 'send_export_email' ], true ) ) {
        $userId      = (string) ( $_POST['user_id'] ?? '' );
        $exportQuery = trim( (string) ( $_POST['export_query'] ?? '' ) );

        try {
            $user = $app->getUserManager()->getById( $userId );

            if ( $action === 'export_json' || $action === 'export_html' ) {
                $isJson   = $action === 'export_json';
                $body     = $isJson
                    ? $privacyManager->exportAsJson( $userId )
                    : $privacyManager->exportAsHtml( $userId );
                $filename = 'privacy-export-' . $user['username'] . '-' . klytos_gmdate( 'Y-m-d' )
                    . ( $isJson ? '.json' : '.html' );

                header( 'Content-Type: ' . ( $isJson ? 'application/json' : 'text/html' ) . '; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
                header( 'Content-Length: ' . strlen( $body ) );
                echo $body;
                exit;
            }

            $json = $privacyManager->exportAsJson( $userId );

            $emailBody  = '<h2>' . klytos_esc_html( __( 'privacy.export_title' ) ) . '</h2>';
            $emailBody .= '<p>' . klytos_esc_html( __( 'privacy.export_desc' ) ) . '</p>';
            $emailBody .= '<pre>' . klytos_esc_html( $json ) . '</pre>';

            $sent = $app->getMailer()->send( $user['email'], __( 'privacy.export_title' ), $emailBody );

            if ( $sent ) {
                $success = __( 'privacy.export_success' );
                klytos_do_action( 'privacy.export_sent', $userId );
            } else {
                $summaryRows[] = [
                    'anchor'  => 'privacy-export-heading',
                    'message' => __( 'privacy.export_email_failed' ),
                ];
            }

            // The subject stays on screen after a send, so the next action does
            // not begin with searching for the same person again.
            $exportUser     = $privacyManager->findUser( $user['username'] );
            $exportSections = $exportUser === null
                ? null
                : ( $privacyManager->collectUserData( $exportUser['id'] )['sections'] ?? [] );
        } catch ( \Throwable $e ) {
            $summaryRows[] = [ 'anchor' => 'privacy-export-heading', 'message' => $e->getMessage() ];
        }
    } elseif ( $action === 'erasure_search' ) {
        $erasureQuery = trim( (string) ( $_POST['erasure_query'] ?? '' ) );
        $erasureUser  = $erasureQuery === '' ? null : $privacyManager->findUser( $erasureQuery );

        if ( $erasureUser === null ) {
            $fieldErrors['erasure_query'] = __( 'privacy.user_not_found' );
            $summaryRows[]                = [
                'anchor'  => 'privacy-field-erasure_query',
                'message' => __( 'privacy.summary_user_not_found' ),
            ];
        } else {
            try {
                $erasableSections = $privacyManager->collectErasableData( $erasureUser['id'] );
            } catch ( \Throwable $e ) {
                $summaryRows[] = [ 'anchor' => 'privacy-erasure-heading', 'message' => $e->getMessage() ];
            }
        }
    } elseif ( $action === 'erase_arm' || $action === 'erase_confirm' ) {
        $userId       = (string) ( $_POST['user_id'] ?? '' );
        $erasureQuery = trim( (string) ( $_POST['erasure_query'] ?? '' ) );
        $selected     = array_values( array_filter(
            array_map( 'strval', (array) ( $_POST['sections'] ?? [] ) ),
            static fn( string $id ): bool => $id !== ''
        ) );

        try {
            $erasureUser      = $app->getUserManager()->getById( $userId );
            $erasableSections = $privacyManager->collectErasableData( $userId );
        } catch ( \Throwable $e ) {
            $summaryRows[] = [ 'anchor' => 'privacy-erasure-heading', 'message' => $e->getMessage() ];
            $erasureUser   = null;
        }

        if ( $erasureUser !== null && $selected === [] ) {
            /*
             * The shipped screen caught an empty selection in JavaScript, with
             * `alert()`. With scripting off it posted anyway and the server
             * answered with a red banner; with scripting on it was a browser
             * dialog, which `SPEC/accessibility.md` §2 rules out for its
             * siblings and D-091 ruled out here. It is a form error now, in both
             * states, and identified the way every other field error is.
             */
            $fieldErrors['sections'] = __( 'privacy.select_sections' );
            $summaryRows[]           = [
                'anchor'  => 'privacy-erasure-sections',
                'message' => __( 'privacy.summary_select_sections' ),
            ];
        } elseif ( $erasureUser !== null && $action === 'erase_arm' ) {
            // §2 destructive section: an inline two-step confirm, server-side so
            // it holds with JavaScript disabled — the idiom entry 39 established.
            $isArmed       = true;
            $armedSections = $selected;
        } elseif ( $erasureUser !== null ) {
            /*
             * The label map is built from the list as it stands NOW, because
             * after the erasure the sections it names are gone and the results
             * carry ids alone.
             */
            $labels = [];
            foreach ( (array) $erasableSections as $section ) {
                $labels[ (string) $section['id'] ] = (string) $section['label'];
            }

            try {
                $erasureResults = $privacyManager->eraseUserData( $userId, $selected, $auth->getUserId() );

                $skipped = array_filter(
                    $erasureResults,
                    static fn( array $r ): bool => ( $r['status'] ?? '' ) === 'skipped'
                );

                $success = $skipped === []
                    ? __( 'privacy.erasure_success' )
                    : __( 'privacy.erasure_partial' );

                /*
                 * The subject is re-resolved so it — and the sections that are
                 * still there — stay on screen after an erasure, instead of the
                 * person having to search for the same account again to erase a
                 * second section. It is read by ID because the account may have
                 * just been anonymized, so the name it was found by no longer
                 * matches anything.
                 *
                 * This is NOT what fixes the never-rendering result table; the
                 * fix for that is that the table is no longer nested inside a
                 * check on the subject at all. Planting `= null` here left all
                 * 22 tests green, which is how that distinction was established
                 * rather than assumed — and it also showed the behaviour above
                 * had no test, which the next one down now supplies.
                 */
                $erasureUser = $app->getUserManager()->getById( $userId );

                foreach ( $erasureResults as $index => $result ) {
                    $erasureResults[ $index ]['label'] = $sectionLabel( $labels, (string) $result['section'] );
                }

                $erasableSections = $privacyManager->collectErasableData( $userId );
            } catch ( \Throwable $e ) {
                // Owner and self-erasure are refused by the manager, by design.
                $summaryRows[] = [ 'anchor' => 'privacy-erasure-heading', 'message' => $e->getMessage() ];
            }
        }
    }
}

/*
 * template-record-form.md §1 puts the primary Save in the toolbar. ADAPTATION:
 * entry 26 has no savable state at all — every control on it is an action
 * (search, download, send, erase) that completes on its own post. A toolbar
 * button that saves nothing would be an invented control, so the seam is left
 * empty here and the rule is honoured where there is something to save.
 */

/**
 * `aria-describedby` per control: the hint always, the error only when there is
 * one, hint FIRST (`template-record-form.md` §4).
 *
 * Computed here rather than inline so the attribute is one echo — three of these
 * were single lines long enough to trip the 150-character warning, and a wrapped
 * ternary inside an attribute is worse to read than a named value.
 *
 * @var array<string,string>
 */
$describedBy = [];

/** @var array<string,array{0:string,1:string}> control => [ hint id, error id ] */
$describedByIds = [
    'export_query'  => [ 'privacy-hint-export_query', 'privacy-error-export_query' ],
    'erasure_query' => [ 'privacy-hint-erasure_query', 'privacy-error-erasure_query' ],
    'sections'      => [ 'privacy-hint-sections', 'privacy-error-sections' ],
];

foreach ( $describedByIds as $control => $ids ) {
    $describedBy[ $control ] = isset( $fieldErrors[ $control ] )
        ? $ids[0] . ' ' . $ids[1]
        : $ids[0];
}

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.privacy.before' ); ?>

<?php if ( $success !== '' ) : ?>
    <?php // §2 Success: "the page reloads with a role="status" line under the H1". ?>
    <p class="k-status-line" role="status" data-testid="privacy.status_line">
        <?php echo klytos_esc_html( $success ); ?>
    </p>
<?php endif; ?>

<?php if ( $summaryRows !== [] ) : ?>
    <?php /*
     * §2 Error — form level: an error summary at the top of main, role="alert",
     * focus moved to it on load, listing every failed field as a link to it.
     */ ?>
    <div class="k-error-summary"
         id="privacy-error-summary"
         role="alert"
         tabindex="-1"
         data-testid="privacy.error_summary">
        <h2><?php echo klytos_esc_html( __( 'privacy.summary_title' ) ); ?></h2>
        <ul>
            <?php foreach ( $summaryRows as $index => $row ) : ?>
                <li>
                    <a href="#<?php echo klytos_esc_attr( $row['anchor'] ); ?>"
                       data-testid="privacy.error_link.<?php echo (int) $index; ?>">
                        <?php echo klytos_esc_html( $row['message'] ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
/*
 * §26 lists cards, not sections, so the template's optional section nav is
 * ABSENT from the DOM rather than rendered empty (template-record-form.md §1).
 */
?>
<div class="k-record-form k-record-form--no-nav" data-testid="privacy.screen">

    <div class="k-card-stack">

        <?php klytos_do_action( 'admin.privacy.before_export' ); ?>

        <?php // ─── Card 1 — Export requests ──────────────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="privacy-export-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="privacy-export-heading" tabindex="-1">
                    <?php echo klytos_esc_html( __( 'privacy.card_export' ) ); ?>
                </h2>
                <p class="k-hint"><?php echo klytos_esc_html( __( 'privacy.export_desc' ) ); ?></p>

                <form method="post" data-testid="privacy.export_search_form">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="export_search">

                    <div class="k-field">
                        <?php /*
                         * §4: "Every control has a visible <label for>. No
                         * placeholder-as-label anywhere in the admin." The
                         * shipped field had a placeholder and no label at all.
                         */ ?>
                        <label class="k-label" for="privacy-field-export_query">
                            <?php echo klytos_esc_html( __( 'privacy.search_label' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control"
                               id="privacy-field-export_query"
                               name="export_query"
                               value="<?php echo klytos_esc_attr( $exportQuery ); ?>"
                               required
                               spellcheck="false"
                               autocapitalize="off"
                               autocomplete="off"
                               aria-describedby="<?php echo klytos_esc_attr( $describedBy[ 'export_query' ] ); ?>"
                               <?php echo isset( $fieldErrors['export_query'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="privacy.export_query">
                        <p class="k-hint" id="privacy-hint-export_query">
                            <?php echo klytos_esc_html( __( 'privacy.search_hint' ) . ' ' . __( 'common.required' ) ); ?>
                        </p>
                        <?php if ( isset( $fieldErrors['export_query'] ) ) : ?>
                            <p class="k-error" id="privacy-error-export_query">
                                <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                <?php echo klytos_esc_html( $fieldErrors['export_query'] ); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="k-card-footer">
                        <button type="submit"
                                class="k-btn k-btn--secondary k-btn--sm"
                                data-testid="privacy.export_search">
                            <?php echo klytos_esc_html( __( 'privacy.search_button' ) ); ?>
                        </button>
                    </div>
                </form>

                <?php if ( $exportUser !== null ) : ?>
                    <h3 class="k-label" id="privacy-export-subject">
                        <?php echo klytos_esc_html( __( 'privacy.subject' ) ); ?>
                    </h3>
                    <p class="k-hint" data-testid="privacy.export_subject">
                        <?php echo klytos_esc_html(
                            $exportUser['display_name'] . ' · @' . $exportUser['username']
                            . ' · ' . $exportUser['email']
                        ); ?>
                        <span class="k-badge k-badge--info"><?php echo klytos_esc_html( (string) $exportUser['role'] ); ?></span>
                    </p>

                    <?php if ( ! empty( $exportSections ) ) : ?>
                        <?php /*
                         * §2.1: a real <table> carrying the explicit role set,
                         * because applying a grid layout to a table strips its
                         * implicit roles in Chromium and WebKit.
                         */ ?>
                        <div class="k-table-scroll">
                            <table role="table" class="k-table" aria-labelledby="privacy-export-sections-caption"
                                   data-testid="privacy.export_sections">
                                <caption id="privacy-export-sections-caption" class="k-table-caption">
                                    <?php echo klytos_esc_html( __(
                                        'privacy.export_sections_caption',
                                        [ 'count' => (string) count( $exportSections ) ]
                                    ) ); ?>
                                </caption>
                                <thead role="rowgroup">
                                    <tr role="row">
                                        <th role="columnheader" scope="col"><?php echo klytos_esc_html( __( 'privacy.section' ) ); ?></th>
                                        <th role="columnheader" scope="col" class="k-num"><?php echo klytos_esc_html( __( 'privacy.items' ) ); ?></th>
                                    </tr>
                                </thead>
                                <tbody role="rowgroup">
                                    <?php foreach ( $exportSections as $index => $section ) : ?>
                                        <tr role="row">
                                            <th role="rowheader" scope="row" id="privacy-export-row-<?php echo (int) $index; ?>">
                                                <?php echo klytos_esc_html( (string) $section['label'] ); ?>
                                                <?php if ( ( $section['source'] ?? 'core' ) !== 'core' ) : ?>
                                                    <span class="k-badge k-badge--info">
                                                        <?php echo klytos_esc_html( (string) $section['source'] ); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </th>
                                            <td role="cell" class="k-num"><?php echo (int) ( $section['count'] ?? 1 ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="k-card-footer">
                            <?php foreach ( $exportActions as $exportAction => $spec ) : ?>
                                <form method="post">
                                    <?php echo klytos_csrf_field(); ?>
                                    <input type="hidden" name="action" value="<?php echo klytos_esc_attr( $exportAction ); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo klytos_esc_attr( (string) $exportUser['id'] ); ?>">
                                    <input type="hidden" name="export_query" value="<?php echo klytos_esc_attr( $exportQuery ); ?>">
                                    <button type="submit"
                                            class="k-btn <?php echo klytos_esc_attr( $spec[1] ); ?> k-btn--sm"
                                            data-testid="privacy.<?php echo klytos_esc_attr( $exportAction ); ?>">
                                        <?php echo klytos_esc_html( __( $spec[0] ) ); ?>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ( $exportSections !== null ) : ?>
                        <p class="k-empty" data-testid="privacy.export_empty">
                            <?php klytos_admin_icon( $spriteUrl, 'ks-policy', 'k-empty-icon' ); ?>
                            <span class="k-empty-text"><?php echo klytos_esc_html( __( 'privacy.no_data' ) ); ?></span>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>

        <?php klytos_do_action( 'admin.privacy.after_export' ); ?>

        <?php // ─── Card 2 — Erasure requests ─────────────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="privacy-erasure-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="privacy-erasure-heading">
                    <?php echo klytos_esc_html( __( 'privacy.card_erasure' ) ); ?>
                </h2>
                <p class="k-hint"><?php echo klytos_esc_html( __( 'privacy.erasure_desc' ) ); ?></p>

                <form method="post" data-testid="privacy.erasure_search_form">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="erasure_search">

                    <div class="k-field">
                        <label class="k-label" for="privacy-field-erasure_query">
                            <?php echo klytos_esc_html( __( 'privacy.search_label' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control"
                               id="privacy-field-erasure_query"
                               name="erasure_query"
                               value="<?php echo klytos_esc_attr( $erasureQuery ); ?>"
                               required
                               spellcheck="false"
                               autocapitalize="off"
                               autocomplete="off"
                               aria-describedby="<?php echo klytos_esc_attr( $describedBy[ 'erasure_query' ] ); ?>"
                               <?php echo isset( $fieldErrors['erasure_query'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="privacy.erasure_query">
                        <p class="k-hint" id="privacy-hint-erasure_query">
                            <?php echo klytos_esc_html( __( 'privacy.search_hint' ) . ' ' . __( 'common.required' ) ); ?>
                        </p>
                        <?php if ( isset( $fieldErrors['erasure_query'] ) ) : ?>
                            <p class="k-error" id="privacy-error-erasure_query">
                                <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                <?php echo klytos_esc_html( $fieldErrors['erasure_query'] ); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="k-card-footer">
                        <button type="submit"
                                class="k-btn k-btn--secondary k-btn--sm"
                                data-testid="privacy.erasure_search">
                            <?php echo klytos_esc_html( __( 'privacy.search_button' ) ); ?>
                        </button>
                    </div>
                </form>

                <?php if ( $erasureUser !== null ) : ?>
                    <h3 class="k-label" id="privacy-erasure-subject">
                        <?php echo klytos_esc_html( __( 'privacy.subject' ) ); ?>
                    </h3>
                    <p class="k-hint" data-testid="privacy.erasure_subject">
                        <?php echo klytos_esc_html(
                            $erasureUser['display_name'] . ' · @' . $erasureUser['username']
                            . ' · ' . $erasureUser['email']
                        ); ?>
                        <span class="k-badge k-badge--info"><?php echo klytos_esc_html( (string) $erasureUser['role'] ); ?></span>
                    </p>

                    <?php if ( ( $erasureUser['role'] ?? '' ) === 'owner' ) : ?>
                        <p class="k-hint" data-testid="privacy.owner_notice">
                            <?php echo klytos_esc_html( __( 'privacy.owner_cannot_erase' ) ); ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ( $erasureResults !== null ) : ?>
                    <?php /*
                     * THE TABLE THAT NEVER RENDERED. It is reached now because
                     * the subject is re-resolved after the erasure; every row
                     * carries the section's LABEL rather than its internal id,
                     * and a status WORD rather than a colour alone.
                     */ ?>
                    <h3 class="k-label" id="privacy-results-heading">
                        <?php echo klytos_esc_html( __( 'privacy.results_title' ) ); ?>
                    </h3>
                    <div class="k-table-scroll">
                        <table role="table" class="k-table" aria-labelledby="privacy-results-caption"
                               data-testid="privacy.erasure_results">
                            <caption id="privacy-results-caption" class="k-table-caption">
                                <?php echo klytos_esc_html( __(
                                    'privacy.results_caption',
                                    [ 'count' => (string) count( $erasureResults ) ]
                                ) ); ?>
                            </caption>
                            <thead role="rowgroup">
                                <tr role="row">
                                    <th role="columnheader" scope="col"><?php echo klytos_esc_html( __( 'privacy.section' ) ); ?></th>
                                    <th role="columnheader" scope="col"><?php echo klytos_esc_html( __( 'privacy.status' ) ); ?></th>
                                    <th role="columnheader" scope="col" class="k-num"><?php echo klytos_esc_html( __( 'privacy.items' ) ); ?></th>
                                </tr>
                            </thead>
                            <tbody role="rowgroup">
                                <?php foreach ( $erasureResults as $index => $result ) : ?>
                                    <?php
                                    $statusKey = (string) ( $result['status'] ?? '' );
                                    $statusText = isset( $statusLabels[ $statusKey ] )
                                        ? __( $statusLabels[ $statusKey ] )
                                        : $statusKey;
                                    ?>
                                    <tr role="row">
                                        <th role="rowheader" scope="row" id="privacy-result-row-<?php echo (int) $index; ?>">
                                            <?php echo klytos_esc_html(
                                                (string) ( $result['label'] ?? $result['section'] ?? '' )
                                            ); ?>
                                        </th>
                                        <td role="cell">
                                            <span class="k-badge <?php echo klytos_esc_attr( $statusTones[ $statusKey ] ?? 'k-badge--info' ); ?>">
                                                <?php echo klytos_esc_html( $statusText ); ?>
                                            </span>
                                            <?php if ( ( $result['reason'] ?? '' ) === 'legally_retained' ) : ?>
                                                <span class="k-collection-meta">
                                                    <?php echo klytos_esc_html(
                                                        (string) ( $result['detail'] ?? '' ) !== ''
                                                            ? (string) $result['detail']
                                                            : __( 'privacy.legally_retained' )
                                                    ); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td role="cell" class="k-num"><?php echo (int) ( $result['count'] ?? 0 ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ( $erasureUser !== null && $erasableSections !== null ) : ?>
                    <?php $erasable = array_values( array_filter(
                        $erasableSections,
                        static fn( array $s ): bool => ! empty( $s['erasable'] )
                    ) ); ?>

                    <?php if ( $erasableSections === [] ) : ?>
                        <p class="k-empty" data-testid="privacy.erasure_empty">
                            <?php klytos_admin_icon( $spriteUrl, 'ks-policy', 'k-empty-icon' ); ?>
                            <span class="k-empty-text"><?php echo klytos_esc_html( __( 'privacy.no_erasable' ) ); ?></span>
                        </p>
                    <?php else : ?>
                        <?php /*
                         * The section list renders whether or not anything on it
                         * can be erased. Found by driving: the first version
                         * showed the rows only when at least one was erasable,
                         * so on the owner — where every section is retained —
                         * the screen said "nothing can be erased" and never said
                         * WHICH section or WHY. That is the same defect as the
                         * padlock explained only by a `title` attribute, moved
                         * one level up. Only the destructive control is
                         * conditional.
                         */ ?>
                        <form method="post" class="k-confirm-wrap" aria-live="polite"
                              data-testid="privacy.erasure_form">
                            <?php echo klytos_csrf_field(); ?>
                            <input type="hidden" name="user_id" value="<?php echo klytos_esc_attr( (string) $erasureUser['id'] ); ?>">
                            <input type="hidden" name="erasure_query" value="<?php echo klytos_esc_attr( $erasureQuery ); ?>">

                            <?php if ( $isArmed ) : ?>
                                <?php /*
                                 * The armed step names what will happen and to
                                 * how many sections, and carries the selection
                                 * forward in hidden fields so the confirm posts
                                 * exactly what was ticked — no JavaScript
                                 * anywhere in the two steps.
                                 */ ?>
                                <input type="hidden" name="action" value="erase_confirm">
                                <?php foreach ( $armedSections as $sectionId ) : ?>
                                    <input type="hidden" name="sections[]" value="<?php echo klytos_esc_attr( $sectionId ); ?>">
                                <?php endforeach; ?>

                                <p class="k-hint" data-testid="privacy.erase_armed_summary">
                                    <?php
                                    $armedLabels = [];
                                    foreach ( (array) $erasableSections as $section ) {
                                        if ( in_array( (string) $section['id'], $armedSections, true ) ) {
                                            $armedLabels[] = (string) $section['label'];
                                        }
                                    }
                                    echo klytos_esc_html( implode( ' · ', $armedLabels ) );
                                    ?>
                                </p>

                                <div class="k-card-footer">
                                    <button type="submit"
                                            class="k-btn k-btn--destructive k-btn--sm"
                                            data-testid="privacy.erase_confirm">
                                        <?php echo klytos_esc_html( __(
                                            'privacy.erase_confirm',
                                            [ 'count' => (string) count( $armedSections ) ]
                                        ) ); ?>
                                    </button>
                                </div>
                            <?php else : ?>
                                <input type="hidden" name="action" value="erase_arm">

                                <?php // §4: every checkbox set is in <fieldset><legend>. ?>
                                <fieldset class="k-fieldset k-field" id="privacy-erasure-sections">
                                    <legend class="k-legend"><?php echo klytos_esc_html( __( 'privacy.sections_legend' ) ); ?></legend>
                                    <p class="k-hint" id="privacy-hint-sections">
                                        <?php echo klytos_esc_html( __( 'privacy.sections_hint' ) ); ?>
                                    </p>

                                    <?php foreach ( $erasableSections as $section ) : ?>
                                        <?php
                                        $sectionId  = (string) $section['id'];
                                        $controlId  = 'privacy-section-' . preg_replace( '/[^a-z0-9]+/i', '-', $sectionId );
                                        $methodKey  = 'privacy.method_' . (string) ( $section['erasure_method'] ?? 'delete' );
                                        $isErasable = ! empty( $section['erasable'] );
                                        ?>
                                        <?php if ( $isErasable ) : ?>
                                            <label class="k-choice k-hit-24" for="<?php echo klytos_esc_attr( $controlId ); ?>">
                                                <input type="checkbox"
                                                       class="k-check"
                                                       id="<?php echo klytos_esc_attr( $controlId ); ?>"
                                                       name="sections[]"
                                                       value="<?php echo klytos_esc_attr( $sectionId ); ?>"
                                                       aria-describedby="<?php echo klytos_esc_attr( $describedBy['sections'] ); ?>"
                                                       <?php echo isset( $fieldErrors['sections'] ) ? 'aria-invalid="true"' : ''; ?>
                                                       data-testid="privacy.section.<?php echo klytos_esc_attr( $sectionId ); ?>">
                                                <span>
                                                    <?php echo klytos_esc_html( (string) $section['label'] ); ?>
                                                    <span class="k-collection-meta">
                                                        <?php echo klytos_esc_html( __( $methodKey ) ); ?>
                                                        ·
                                                        <?php echo klytos_esc_html( __(
                                                            'privacy.items_count',
                                                            [ 'count' => (string) ( $section['item_count'] ?? 0 ) ]
                                                        ) ); ?>
                                                    </span>
                                                </span>
                                            </label>
                                        <?php else : ?>
                                            <?php /*
                                             * A retained section is stated in
                                             * WORDS beside the row. The shipped
                                             * screen drew a padlock icon whose
                                             * only explanation was its `title`
                                             * attribute — invisible to a
                                             * keyboard and to most screen
                                             * readers.
                                             */ ?>
                                            <p class="k-hint" data-testid="privacy.section_retained.<?php echo klytos_esc_attr( $sectionId ); ?>">
                                                <?php echo klytos_esc_html( (string) $section['label'] ); ?>
                                                <span class="k-badge k-badge--aviso"><?php echo klytos_esc_html( __( 'privacy.retained' ) ); ?></span>
                                                <span class="k-collection-meta">
                                                    <?php
                                                    echo klytos_esc_html( (string) (
                                                        $section['retention_reason'] ?? __( 'privacy.legally_retained' )
                                                    ) );
                                                    ?>
                                                </span>
                                            </p>
                                        <?php endif; ?>
                                    <?php endforeach; ?>

                                    <?php if ( isset( $fieldErrors['sections'] ) ) : ?>
                                        <p class="k-error" id="privacy-error-sections">
                                            <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                            <?php echo klytos_esc_html( $fieldErrors['sections'] ); ?>
                                        </p>
                                    <?php endif; ?>
                                </fieldset>

                                <?php if ( $erasable === [] ) : ?>
                                    <p class="k-empty" data-testid="privacy.erasure_empty">
                                        <?php klytos_admin_icon( $spriteUrl, 'ks-policy', 'k-empty-icon' ); ?>
                                        <span class="k-empty-text"><?php echo klytos_esc_html( __( 'privacy.no_erasable' ) ); ?></span>
                                    </p>
                                <?php else : ?>
                                    <div class="k-card-footer">
                                        <button type="submit"
                                                class="k-btn k-btn--destructive k-btn--sm"
                                                data-testid="privacy.erase_arm">
                                            <?php echo klytos_esc_html( __( 'privacy.erasure_button' ) ); ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>

    </div>
</div>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function () {
    'use strict';

    /*
     * §2 Error — form level: "focus moved to it on load". The summary is only in
     * the DOM when there is one. Nothing else on this screen needs JavaScript:
     * the two-step confirm, the section set and every action are plain posts.
     */
    var summary = document.getElementById('privacy-error-summary');
    if (summary) {
        summary.focus();
    }
})();
</script>

<?php klytos_do_action( 'admin.privacy.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
