<?php

/**
 * Klytos Admin — Logs
 *
 * Manifest entry 41 · template `console-stream` · H1 "Logs".
 *
 * Built in Phase 4 Step 4, stage 4 against
 * `SPEC/screens/template-console-stream.md`, `SPEC/accessibility.md` and
 * `SPEC/manifest.md` §41. It is the one stage-4 surface DR-006 does not block:
 * a console-stream has no `grid-template-columns` to be blocked on.
 *
 * Three things about this screen are deliberate and are not to be "tidied":
 *
 *   1. The stream is NOT `aria-live` (§2, and manifest §41's own delta). A live
 *      log reads continuously and makes the page unusable. Counts are announced
 *      politely in the shell's status region on a 10-second floor instead.
 *   2. `white-space: pre` with horizontal scroll under 900 is CORRECT here and
 *      nowhere else in this admin (§3 — 1.4.10's exception for content
 *      requiring two-dimensional layout). Wrapping a log line is worse than
 *      scrolling it.
 *   3. The delete and delete-all controls are shipped behaviour and STAY, even
 *      though the design draws neither: removing shipped behaviour is not a
 *      fidelity decision (D-076's rule, applied for the third time).
 *
 * The Follow poll reuses `admin/api/logs.php`'s existing `read` action — gated
 * at `site.configure`, CSRF-checked and rate-limited — rather than adding a
 * second endpoint for the same read. Download had no endpoint at all and gets
 * one: `admin/api/log-download.php`.
 *
 * Logs are stored in a secret directory inside data/ and are only generated
 * when Developer Mode is active. Plugins must declare "Logs: true" in their
 * header and have logging enabled.
 *
 * @package Klytos
 * @since   0.16.0
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

use Klytos\Core\Logger;

$pageTitle = __( 'logs.title' );

// ─── Permission check ────────────────────────────────────────
// Enforced centrally since Sprint 1 slice 4: the gate map in
// core/admin-gate.php requires 'site.configure' for this file, and
// admin/bootstrap.php denies before this page's body runs.

$logger    = $app->getLogger();
$auth      = $app->getAuth();
$isDevMode = $app->isDevMode();
$success   = '';
$error     = '';

/**
 * §2's truncation floor. "A stream longer than 5,000 lines shows the last
 * 5,000 and says so at the top of the stream, with a link to download the
 * whole file. It never silently truncates."
 */
const KLYTOS_LOGS_MAX_LINES = 5000;

// ---------------------------------------------------------------------------
// Writes — the shipped delete controls, unchanged in behaviour
// ---------------------------------------------------------------------------

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $postAction = $_POST['log_action'] ?? '';

    if ( $postAction === 'delete' && ! empty( $_POST['file'] ) ) {
        $file = basename( $_POST['file'] );
        if ( $logger->deleteLogFile( $file ) ) {
            $success = __( 'logs.file_deleted' );
        } else {
            $error = __( 'common.error' );
        }
    }

    if ( $postAction === 'delete_all' ) {
        $logger->deleteAllLogFiles();
        $success = __( 'logs.all_deleted' );
    }
}

// ---------------------------------------------------------------------------
// Reads
// ---------------------------------------------------------------------------

$logFiles = klytos_apply_filters( 'admin.logs_file_list', $logger->listLogFiles() );

$selectedFile = basename( $_GET['file'] ?? '' );
$filterLevel  = (string) ( $_GET['level'] ?? '' );
$searchQuery  = trim( (string) ( $_GET['q'] ?? '' ) );

// A level that is not one this Logger writes is dropped rather than passed
// through: it could only ever match nothing, and the chip that produced it
// does not exist.
if ( $filterLevel !== '' && ! in_array( $filterLevel, Logger::LEVELS, true ) ) {
    $filterLevel = '';
}

/** The metadata row for the selected file, when it is one of the listed files. */
$selectedMeta = null;
foreach ( $logFiles as $meta ) {
    if ( ( $meta['name'] ?? '' ) === $selectedFile ) {
        $selectedMeta = $meta;
        break;
    }
}

$isFiltered   = ( $filterLevel !== '' || $searchQuery !== '' );
$fileReadable = $selectedFile !== '' && $logger->isLogFileReadable( $selectedFile );
$totalLines   = 0;
$logLines     = [];
$truncated    = false;

/*
 * The three answers this screen must tell apart — "no file chosen", "the file
 * is empty" and "the file cannot be read" — each get their own state and their
 * own sentence in §2. They became distinguishable at all in D-084, when
 * `readLogFile()` stopped answering the last two identically (and stopped
 * fatalling on the third).
 */
if ( $selectedFile !== '' && $fileReadable ) {
    $totalLines = $logger->countLines( $selectedFile );

    $logLines = $isFiltered
        ? $logger->searchLogs( $selectedFile, $searchQuery, $filterLevel !== '' ? $filterLevel : null )
        : $logger->readLogFile( $selectedFile, 0, 0 );

    if ( count( $logLines ) > KLYTOS_LOGS_MAX_LINES ) {
        $truncated = true;
        $logLines  = array_slice( $logLines, -KLYTOS_LOGS_MAX_LINES );
    }
}

/**
 * The visible level word, and the tint class that goes with it.
 *
 * Code-side adaptation (BUILD-SPEC §5.9): the design names FOUR level labels —
 * `ERROR`, `WARN`, `INFO`, `DEBUG` — and Klytos's Logger writes EIGHT PSR-3
 * levels. The word shown is always the real one, so nothing is hidden and
 * §4's "a monochrome print of a log screen is fully readable" still holds; only
 * the TINT is mapped, and it is mapped by severity, which is the delivery's own
 * ordering rather than a choice made here: everything at or above `error` takes
 * the error tint, `warning` takes the warn tint, and §1's "ERROR and WARN only"
 * means nothing below that is tinted at all.
 *
 * @param  string $level A PSR-3 level, lower case, or '' for an unparsed line.
 * @return string        The modifier suffix: 'error', 'warn', 'info', 'debug' or ''.
 */
$levelTone = static function ( string $level ): string {
    return match ( $level ) {
        'emergency', 'alert', 'critical', 'error' => 'error',
        'warning'                                 => 'warn',
        'notice', 'info'                          => 'info',
        'debug'                                   => 'debug',
        default                                   => '',
    };
};

/**
 * Build a URL for this screen with a set of query parameters replaced.
 *
 * @param  array<string,string|null> $overrides Parameters to set; null removes.
 * @return string
 */
$logsUrl = static function ( array $overrides = [] ) use ( $selectedFile, $filterLevel, $searchQuery ): string {
    $params = array_filter(
        array_merge(
            [
                'file'  => $selectedFile !== '' ? $selectedFile : null,
                'level' => $filterLevel !== '' ? $filterLevel : null,
                'q'     => $searchQuery !== '' ? $searchQuery : null,
            ],
            $overrides
        ),
        static fn( $v ) => $v !== null && $v !== ''
    );

    return 'logs.php' . ( $params ? '?' . http_build_query( $params ) : '' );
};

$downloadUrl = 'api/log-download.php?' . http_build_query( ['file' => $selectedFile] );
$canDownload = $fileReadable && $totalLines > 0;

/**
 * Render one stream line.
 *
 * It builds a string rather than interleaving `<?php ?>` inside the `<pre>`,
 * and that is not a style preference: inside a `<pre>` every character between
 * a `?>` and the next `<?php` is CONTENT, so template indentation would print
 * itself into the log. Returning a string keeps the whitespace deliberate and
 * the file's own indentation ordinary.
 *
 * The line is a `<button>` because selecting it opens the detail panel — §2:
 * "Individual lines are focusable only where selecting a line does something."
 * `data-message` and `data-context` carry the parsed fields to that panel, so
 * the panel never re-parses a line the server already parsed.
 *
 * @param  string $rawLine One stored line.
 * @param  int    $index   Its position in the rendered stream.
 * @return string          The line's HTML, with no surrounding whitespace.
 */
$renderLine = static function ( string $rawLine, int $index ) use ( $levelTone ): string {
    $parsed = Logger::parseLine( $rawLine );
    $tone   = $levelTone( $parsed['level'] );
    $inner  = '';

    if ( $parsed['level'] !== '' ) {
        $inner .= '<span class="k-stream-level k-level k-level--' . klytos_esc_attr( $tone ) . '">'
            . klytos_esc_html( strtoupper( $parsed['level'] ) ) . '</span>';
    }

    if ( $parsed['timestamp'] !== '' ) {
        // §4: "Timestamps are <time datetime>." The stored stamp is UTC —
        // Logger::write() formats it with klytos_gmdate() — so the machine-
        // readable form says so with a Z rather than leaving it ambiguous.
        $inner .= '<time class="k-stream-time" datetime="'
            . klytos_esc_attr( str_replace( ' ', 'T', $parsed['timestamp'] ) . 'Z' ) . '">'
            . klytos_esc_html( $parsed['timestamp'] ) . '</time> ';
    }

    if ( $parsed['source'] !== '' ) {
        $inner .= '<span class="k-stream-source">' . klytos_esc_html( $parsed['source'] ) . '</span> ';
    }

    $inner .= klytos_esc_html( $parsed['message'] );

    $context = $parsed['context']
        ? (string) json_encode( $parsed['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
        : '';

    /*
     * The line's TINT class is added for `error` and `warn` only — §1: "with
     * the tint as the line's background at 11 %/19 % for ERROR and WARN only".
     * `k-line--info` and `k-line--debug` do not exist in the stylesheet and
     * must not exist in the markup either: a class that names a tint the design
     * withholds is a tint waiting to be added by someone reading the HTML.
     * The LABEL still carries its own colour for all four, which is `.k-level--*`
     * and a different thing.
     */
    $lineTint = in_array( $tone, ['error', 'warn'], true ) ? ' k-line--' . $tone : '';

    /*
     * §2's Hover state: "a line takes --fila-hover and reveals its copy
     * affordance at the right. The affordance is in the DOM always." — and §4:
     * "Copy buttons name what they copy: 'Copy this line'."
     *
     * Code-side adaptation (BUILD-SPEC §5.9): the affordance CANNOT sit inside
     * the line. §2 also makes the line itself a `<button>` on this screen, and
     * a button inside a button is invalid HTML — the parser unnests it, so the
     * copy control would end up outside the line anyway, in a position nobody
     * chose. So the row is a wrapper holding two siblings: the line button,
     * which still spans the line and still has the line's text as its name, and
     * the copy button positioned at its right edge. The design intent — one
     * selectable line, one copy control revealed at the right, always present
     * in the DOM — is untouched; only the element nesting differs.
     *
     * "Always in the DOM" is literal and is why it is `opacity`/`visibility` in
     * CSS rather than `display: none`: a control that is not in the tree cannot
     * be reached by a keyboard, and a log line's copy button is exactly what a
     * keyboard user wants.
     */
    return '<div class="k-stream-row">'
        . '<button type="button"'
        . ' class="k-stream-line' . klytos_esc_attr( $lineTint ) . '"'
        . ' aria-pressed="false"'
        . ' data-index="' . $index . '"'
        . ' data-message="' . klytos_esc_attr( $parsed['message'] ) . '"'
        . ' data-context="' . klytos_esc_attr( $context ) . '"'
        . ' data-testid="logs.line.' . $index . '"'
        . '>' . $inner . '</button>'
        . '<button type="button"'
        . ' class="k-stream-copy"'
        . ' data-copy="' . klytos_esc_attr( $rawLine ) . '"'
        . ' data-testid="logs.copy.' . $index . '"'
        . '>' . klytos_esc_html( __( 'logs.copy_line' ) ) . '</button>'
        . '</div>';
};

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.logs.before' ); ?>

<?php if ( $success !== '' ) : ?>
    <p class="k-status-line" role="status" data-testid="logs.status_line">
        <?php echo klytos_esc_html( $success ); ?>
    </p>
<?php endif; ?>

<?php if ( $error !== '' ) : ?>
    <p class="k-status-line k-status-line--aviso" role="alert" data-testid="logs.error_line">
        <?php echo klytos_esc_html( $error ); ?>
    </p>
<?php endif; ?>

<?php if ( ! $isDevMode ) : ?>
    <?php /* Shipped behaviour, kept: without Developer Mode nothing is written
             at all, and a viewer with no explanation for an empty list is the
             worse screen. It is a status line in the flow now, not a card with
             a hardcoded amber. */ ?>
    <p class="k-status-line k-status-line--aviso" data-testid="logs.dev_mode_off">
        <strong><?php echo klytos_esc_html( __( 'logs.dev_mode_off_title' ) ); ?></strong>
        <?php echo klytos_esc_html( __( 'logs.dev_mode_off_desc' ) ); ?>
    </p>
<?php endif; ?>

<?php // ─── Control row (§1): level chips · file picker · search · Follow · Download ─── ?>
<div class="k-console-controls" data-testid="logs.controls">

    <?php
    /*
     * §4: "the level filter chips are links (they change the URL)". They are a
     * <nav>, exactly as the list screens' chips are, and the scroll container
     * they sit in is labelled and focusable so that a keyboard user can reach
     * the ones that scroll out of view under 900 (§3).
     */
    ?>
    <nav aria-label="<?php echo klytos_esc_attr( __( 'logs.level_filter_label' ) ); ?>"
         class="k-console-chips"
         tabindex="0"
         role="group"
         data-testid="logs.levels">
        <a class="k-chip"
           href="<?php echo klytos_esc_url( $logsUrl( ['level' => null] ) ); ?>"
           <?php echo $filterLevel === '' ? 'aria-current="true"' : ''; ?>
           data-testid="logs.chip.all">
            <?php echo klytos_esc_html( __( 'logs.all_levels' ) ); ?>
        </a>
        <?php foreach ( Logger::LEVELS as $lvl ) : ?>
            <a class="k-chip"
               href="<?php echo klytos_esc_url( $logsUrl( ['level' => $lvl] ) ); ?>"
               <?php echo $filterLevel === $lvl ? 'aria-current="true"' : ''; ?>
               data-testid="logs.chip.<?php echo klytos_esc_attr( $lvl ); ?>">
                <?php echo klytos_esc_html( strtoupper( $lvl ) ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php
    /*
     * §4: "the file picker is a <select> inside a form with a visible label and
     * a submit that is not needed when JS is on but exists when it is off."
     * Both halves are literal: the label is visible (not .k-sr), and the submit
     * button is in the DOM unconditionally. The JS below only removes the need
     * to press it.
     */
    ?>
    <form method="get" action="logs.php" data-testid="logs.file_form">
        <?php if ( $filterLevel !== '' ) : ?>
            <input type="hidden" name="level" value="<?php echo klytos_esc_attr( $filterLevel ); ?>">
        <?php endif; ?>
        <?php if ( $searchQuery !== '' ) : ?>
            <input type="hidden" name="q" value="<?php echo klytos_esc_attr( $searchQuery ); ?>">
        <?php endif; ?>
        <label for="logs-file"><?php echo klytos_esc_html( __( 'logs.file_label' ) ); ?></label>
        <select class="k-control" id="logs-file" name="file" data-testid="logs.file_select">
            <option value=""><?php echo klytos_esc_html( __( 'logs.no_file_selected' ) ); ?></option>
            <?php foreach ( $logFiles as $file ) : ?>
                <option value="<?php echo klytos_esc_attr( (string) $file['name'] ); ?>"
                    <?php echo $selectedFile === $file['name'] ? 'selected' : ''; ?>>
                    <?php echo klytos_esc_html( $file['name'] . ' — ' . $file['size_formatted'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="k-btn k-btn--secondary" data-testid="logs.file_submit">
            <?php echo klytos_esc_html( __( 'logs.show_file' ) ); ?>
        </button>
    </form>

    <form method="get" action="logs.php" role="search" data-testid="logs.search_form">
        <?php if ( $selectedFile !== '' ) : ?>
            <input type="hidden" name="file" value="<?php echo klytos_esc_attr( $selectedFile ); ?>">
        <?php endif; ?>
        <?php if ( $filterLevel !== '' ) : ?>
            <input type="hidden" name="level" value="<?php echo klytos_esc_attr( $filterLevel ); ?>">
        <?php endif; ?>
        <label class="k-sr" for="logs-search"><?php echo klytos_esc_html( __( 'logs.search_label' ) ); ?></label>
        <input class="k-control" type="search" id="logs-search" name="q"
               value="<?php echo klytos_esc_attr( $searchQuery ); ?>"
               placeholder="<?php echo klytos_esc_attr( __( 'logs.search' ) ); ?>"
               data-testid="logs.search">
        <button type="submit" class="k-btn k-btn--secondary" data-testid="logs.search_submit">
            <?php echo klytos_esc_html( __( 'common.search' ) ); ?>
        </button>
    </form>

    <?php
    /*
     * §2's Following state: `role="switch" aria-checked`, because it takes
     * effect immediately. It is only rendered where there is a stream to
     * follow — a switch that toggles nothing is not a state the design
     * specifies, and rendering it disabled would be inventing one.
     */
    ?>
    <?php if ( $fileReadable && ! $isFiltered ) : ?>
        <span class="k-switch-row">
            <span id="logs-follow-label"><?php echo klytos_esc_html( __( 'logs.follow' ) ); ?></span>
            <button type="button"
                    class="k-switch"
                    role="switch"
                    aria-checked="false"
                    aria-labelledby="logs-follow-label"
                    id="logs-follow"
                    data-testid="logs.follow">
                <span class="k-switch-thumb"></span>
            </button>
        </span>
    <?php endif; ?>

    <?php
    /*
     * §2's Disabled state: "Download is disabled when the file is empty, with
     * the reason in its name." A link cannot be `disabled`, so the disabled
     * form is a <button disabled> carrying the reason — the accessible name is
     * the reason, not a tooltip, and the control stays in the DOM because
     * hiding an action teaches nothing.
     */
    ?>
    <?php if ( $canDownload ) : ?>
        <a class="k-btn k-btn--secondary"
           href="<?php echo klytos_esc_url( $downloadUrl ); ?>"
           download
           data-testid="logs.download">
            <?php echo klytos_esc_html( __( 'logs.download' ) ); ?>
        </a>
    <?php elseif ( $selectedFile !== '' ) : ?>
        <button type="button" class="k-btn k-btn--secondary" disabled data-testid="logs.download_disabled">
            <?php echo klytos_esc_html( __( 'logs.download' ) ); ?>
            <span class="k-sr">
                — <?php echo klytos_esc_html( $fileReadable ? __( 'logs.download_empty_reason' ) : __( 'logs.download_unreadable_reason' ) ); ?>
            </span>
        </button>
    <?php endif; ?>

    <?php echo klytos_apply_filters( 'admin.logs_toolbar', '' ); ?>

    <?php // Shipped controls, kept (D-076): delete this file, delete all files. ?>
    <?php if ( $selectedFile !== '' ) : ?>
        <form method="post" action="logs.php" data-testid="logs.delete_form">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="log_action" value="delete">
            <input type="hidden" name="file" value="<?php echo klytos_esc_attr( $selectedFile ); ?>">
            <button type="submit" class="k-btn k-btn--destructive" data-testid="logs.delete">
                <?php echo klytos_esc_html( __( 'logs.delete_file' ) ); ?>
            </button>
        </form>
    <?php endif; ?>

    <?php if ( ! empty( $logFiles ) ) : ?>
        <form method="post" action="logs.php" id="logs-delete-all-form"
              data-confirm="<?php echo klytos_esc_attr( __( 'logs.delete_all_confirm' ) ); ?>"
              data-testid="logs.delete_all_form">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="log_action" value="delete_all">
            <button type="submit" class="k-btn k-btn--destructive" data-testid="logs.delete_all">
                <?php echo klytos_esc_html( __( 'logs.delete_all' ) ); ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="k-console-layout">
    <div>
        <div class="k-card k-card--padded">
            <?php if ( $selectedFile === '' ) : ?>
                <?php // Empty — nothing chosen yet. ?>
                <div class="k-empty" data-testid="logs.empty_no_file">
                    <p class="k-empty-text">
                        <?php echo klytos_esc_html(
                            empty( $logFiles ) ? __( 'logs.no_files' ) : __( 'logs.select_file' )
                        ); ?>
                    </p>
                </div>

            <?php elseif ( ! $fileReadable ) : ?>
                <?php
                /*
                 * §2's error state: "`error.log` cannot be read — permission
                 * denied on `/var/log/klytos/`." with two actions, "Open
                 * Health" and "Choose another file".
                 *
                 * Code-side adaptation (BUILD-SPEC §5.9): **"Open Health" is
                 * not rendered.** Health is manifest entry 22 and is DEFERRED
                 * to its own Phase 5 slice (D-072) — no `health.php` exists,
                 * which is exactly why D-075 omitted it from the sidebar too.
                 * A link that 404s from an error state is worse than the state
                 * without it. It returns with the screen it points at.
                 *
                 * The directory is named because §2 names it, and it is the
                 * operator's own logs directory — not user input, and not a
                 * path a caller can influence.
                 */
                ?>
                <div class="k-empty k-empty--error" role="alert" data-testid="logs.error_unreadable">
                    <p class="k-empty-text">
                        <?php echo klytos_esc_html( __( 'logs.cannot_read', [
                            'file' => $selectedFile,
                            'dir'  => $logger->getLogsDir(),
                        ] ) ); ?>
                    </p>
                    <p class="k-empty-text">
                        <a href="logs.php" data-testid="logs.choose_another">
                            <?php echo klytos_esc_html( __( 'logs.choose_another_file' ) ); ?>
                        </a>
                    </p>
                </div>

            <?php elseif ( empty( $logLines ) && $isFiltered ) : ?>
                <?php
                /*
                 * §2: "No `ERROR` lines in the last 24 hours. — Show all
                 * levels. This is a good-news empty state and reads like one."
                 * So it is NOT the error variant, and it offers the way back.
                 */
                ?>
                <div class="k-empty" data-testid="logs.empty_filtered">
                    <p class="k-empty-text">
                        <?php echo klytos_esc_html( __( 'logs.filtered_empty', [
                            'file' => $selectedFile,
                        ] ) ); ?>
                    </p>
                    <p class="k-empty-text">
                        <a href="<?php echo klytos_esc_url( $logsUrl( ['level' => null, 'q' => null] ) ); ?>"
                           data-testid="logs.show_all_levels">
                            <?php echo klytos_esc_html( __( 'logs.show_all_levels' ) ); ?>
                        </a>
                    </p>
                </div>

            <?php elseif ( empty( $logLines ) ) : ?>
                <?php
                /*
                 * §2: "`error.log` is empty. Nothing has been written since it
                 * was rotated on 24 July." The second sentence is only written
                 * when the date is actually known — the filename carries it —
                 * because a rotation date this screen cannot establish is not a
                 * sentence it may print.
                 */
                ?>
                <div class="k-empty" data-testid="logs.empty_file">
                    <p class="k-empty-text">
                        <?php echo klytos_esc_html( __( 'logs.empty_file_named', ['file' => $selectedFile] ) ); ?>
                        <?php if ( ! empty( $selectedMeta['date'] ) ) : ?>
                            <?php echo klytos_esc_html( __( 'logs.empty_file_since', [
                                'date' => klytos_date( 'j F Y', strtotime( (string) $selectedMeta['date'] . ' 00:00:00 UTC' ) ),
                            ] ) ); ?>
                        <?php endif; ?>
                    </p>
                </div>

            <?php else : ?>
                <?php if ( $truncated ) : ?>
                    <?php // §2: it never silently truncates, and it links to the whole file. ?>
                    <p class="k-stream-truncated" data-testid="logs.truncated">
                        <?php echo klytos_esc_html( __( 'logs.truncated', [
                            'shown' => (string) KLYTOS_LOGS_MAX_LINES,
                            'total' => (string) $totalLines,
                        ] ) ); ?>
                        <a href="<?php echo klytos_esc_url( $downloadUrl ); ?>" download data-testid="logs.truncated_download">
                            <?php echo klytos_esc_html( __( 'logs.download_whole_file' ) ); ?>
                        </a>
                    </p>
                <?php endif; ?>

                <?php
                /*
                 * §4: "The stream is <pre> inside a labelled, focusable scroll
                 * container." The container carries the count in its label so a
                 * screen-reader user knows the size of what they are about to
                 * scroll — and NOT `aria-live`, deliberately (§2/§4).
                 */
                ?>
                <div class="k-stream"
                     tabindex="0"
                     role="group"
                     aria-label="<?php echo klytos_esc_attr( __( 'logs.stream_label', ['count' => (string) count( $logLines )] ) ); ?>"
                     id="logs-stream"
                     data-file="<?php echo klytos_esc_attr( $selectedFile ); ?>"
                     data-total="<?php echo klytos_esc_attr( (string) $totalLines ); ?>"
                     data-testid="logs.stream"><pre class="k-stream-pre" id="logs-stream-pre"><?php
                        /*
                         * No separator between lines, and no template
                         * whitespace: inside a `<pre>` with `white-space: pre`
                         * a newline between two block-level buttons is a blank
                         * line on the screen. Each button is its own row
                         * because `.k-stream-line` is `display: block`, not
                         * because of a character printed here. The indentation
                         * around this block is INSIDE the PHP tags, so none of
                         * it reaches the page.
                         */
                        foreach ( $logLines as $index => $rawLine ) {
                            echo $renderLine( (string) $rawLine, (int) $index );
                        }
                        ?></pre></div>

            <?php endif; ?>
        </div>
    </div>

    <?php
    /*
     * §1: "[Logs] detail — right panel 340px — context + stack for the selected
     * line", and §4: "the detail panel's <h2> names the selected event".
     *
     * Code-side adaptation (BUILD-SPEC §5.9): **there is no separate stack
     * field.** The stored line is `[ts] [LEVEL] [source] message {json}`
     * (`Logger::write()`), so the trailing JSON context IS the panel's body,
     * and a stack appears only where a caller logged one into that context.
     * The design is clear about what the panel shows; the data model expresses
     * it as one structure rather than two.
     */
    ?>
    <aside class="k-card k-card--padded k-console-detail" data-testid="logs.detail">
        <h2 id="logs-detail-title"><?php echo klytos_esc_html( __( 'logs.detail_none_title' ) ); ?></h2>
        <p class="k-empty-text" id="logs-detail-empty"><?php echo klytos_esc_html( __( 'logs.detail_none' ) ); ?></p>
        <dl id="logs-detail-context" hidden></dl>
        <?php /* §4: "Copy buttons name what they copy" — this one copies the
                 selected line's whole context, not the line. It is hidden until
                 there is a payload to copy, because a control that copies
                 nothing is not an affordance. */ ?>
        <button type="button" class="k-btn k-btn--secondary" id="logs-copy-payload"
                data-testid="logs.copy_payload" hidden>
            <?php echo klytos_esc_html( __( 'logs.copy_payload' ) ); ?>
        </button>
        <p class="k-empty-text" id="logs-detail-nocontext" hidden><?php echo klytos_esc_html( __( 'logs.detail_no_context' ) ); ?></p>
    </aside>
</div>

<?php
/*
 * The strings and the CSRF token the script needs, as DATA rather than as
 * interpolated JavaScript: escaping a translated sentence into a script body
 * is the shape that breaks on the first catalogue containing a quote, and this
 * screen adds keys to twenty of them. An `application/json` block is inert to
 * the JavaScript parser and is read with JSON.parse.
 *
 * It is emitted BEFORE the script that reads it, because the script reads it
 * at parse time — after would be `null`.
 */
?>
<script type="application/json" id="logs-strings" nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"><?php
echo json_encode(
    [
        'csrf'            => $auth->getCsrfToken(),
        'newLines'        => __( 'logs.new_lines' ),
        'followPaused'    => __( 'logs.follow_paused' ),
        'detailNoneTitle' => __( 'logs.detail_none_title' ),
        'copied'          => __( 'logs.copied' ),
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?></script>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
( function () {
    'use strict';

    var stream = document.getElementById( 'logs-stream' );
    var pre    = document.getElementById( 'logs-stream-pre' );

    /* ── The file picker submits itself when JS is on (§4). The submit button
       stays in the DOM either way; this only removes the need to press it. ── */
    var fileSelect = document.getElementById( 'logs-file' );
    if ( fileSelect ) {
        fileSelect.addEventListener( 'change', function () {
            fileSelect.form.submit();
        } );
    }

    /* ── Shipped confirmation on delete-all, kept. ── */
    var deleteAllForm = document.getElementById( 'logs-delete-all-form' );
    if ( deleteAllForm ) {
        deleteAllForm.addEventListener( 'submit', function ( event ) {
            if ( ! window.confirm( deleteAllForm.dataset.confirm ) ) {
                event.preventDefault();
            }
        } );
    }

    if ( ! stream || ! pre ) {
        return;
    }

    var strings     = JSON.parse( document.getElementById( 'logs-strings' ).textContent );
    var copyPayload = document.getElementById( 'logs-copy-payload' );

    /* ── Selection → the detail panel (§2's selected-line state) ────────── */
    var detailTitle     = document.getElementById( 'logs-detail-title' );
    var detailEmpty     = document.getElementById( 'logs-detail-empty' );
    var detailContext   = document.getElementById( 'logs-detail-context' );
    var detailNoContext = document.getElementById( 'logs-detail-nocontext' );
    var selected        = null;

    function selectLine( button ) {
        if ( selected === button ) {
            // Pressing the selected line again releases it: aria-pressed is a
            // toggle and must behave like one.
            button.setAttribute( 'aria-pressed', 'false' );
            selected = null;
            detailTitle.textContent = strings.detailNoneTitle;
            detailEmpty.hidden      = false;
            detailContext.hidden    = true;
            detailNoContext.hidden  = true;
            if ( copyPayload ) {
                copyPayload.hidden = true;
            }
            return;
        }

        if ( selected ) {
            selected.setAttribute( 'aria-pressed', 'false' );
        }
        selected = button;
        button.setAttribute( 'aria-pressed', 'true' );

        detailTitle.textContent = button.dataset.message || strings.detailNoneTitle;
        detailEmpty.hidden      = true;

        var raw = button.dataset.context || '';
        var context = null;
        if ( raw !== '' ) {
            try {
                context = JSON.parse( raw );
            } catch ( e ) {
                context = null;
            }
        }

        detailContext.textContent = '';
        var keys = context ? Object.keys( context ) : [];
        if ( keys.length === 0 ) {
            detailContext.hidden   = true;
            detailNoContext.hidden = false;
            if ( copyPayload ) {
                copyPayload.hidden = true;
            }
            return;
        }

        keys.forEach( function ( key ) {
            var dt = document.createElement( 'dt' );
            dt.textContent = key;
            var dd = document.createElement( 'dd' );
            var value = context[ key ];
            dd.textContent = ( typeof value === 'string' ) ? value : JSON.stringify( value );
            detailContext.appendChild( dt );
            detailContext.appendChild( dd );
        } );

        detailNoContext.hidden = true;
        detailContext.hidden   = false;
        if ( copyPayload ) {
            copyPayload.dataset.payload = raw;
            copyPayload.hidden = false;
        }
    }

    /*
     * One delegated listener for the whole stream, and the copy branch comes
     * FIRST: the copy button is a sibling of the line inside the row, so a
     * click on it must not also be read as selecting the line under it.
     */
    function copyText( button, text ) {
        if ( ! navigator.clipboard ) {
            return;
        }
        navigator.clipboard.writeText( text ).then( function () {
            // The confirmation is TEXT IN THE FLOW's live-region equivalent —
            // the shell's polite status region — never a floating toast.
            announce( strings.copied );
        } ).catch( function () {
            // A refused clipboard permission is the browser's answer, not a
            // product state; saying nothing is better than inventing an error.
        } );
    }

    pre.addEventListener( 'click', function ( event ) {
        var copy = event.target.closest( '.k-stream-copy' );
        if ( copy ) {
            copyText( copy, copy.dataset.copy || '' );
            return;
        }
        var button = event.target.closest( '.k-stream-line' );
        if ( button ) {
            selectLine( button );
        }
    } );

    if ( copyPayload ) {
        copyPayload.addEventListener( 'click', function () {
            copyText( copyPayload, copyPayload.dataset.payload || '' );
        } );
    }

    /* ── Default state (§2): scrolled to the bottom, newest last. ───────── */
    stream.scrollTop = stream.scrollHeight;

    /* ── Follow (§2): poll, append, announce politely on a 10-second floor.
       The stream itself is NEVER aria-live — the counts go to the shell's
       status region, which is the whole point of the exception. ────────── */
    var followBtn = document.getElementById( 'logs-follow' );
    if ( ! followBtn ) {
        return;
    }

    var following     = false;
    var timer         = null;
    var lastAnnounce  = 0;
    var pendingLines  = 0;
    var pendingErrors = 0;
    var pausedSaid    = false;
    var shownTotal    = parseInt( stream.dataset.total, 10 ) || 0;
    var nextIndex     = pre.querySelectorAll( '.k-stream-line' ).length;
    var POLL_MS       = 5000;
    var ANNOUNCE_MS   = 10000;

    /*
     * The status region is resolved AT ANNOUNCE TIME, not once at start-up.
     *
     * This script runs in the page body and `#k-live-status` is emitted by
     * footer.php, which is included after it — so a lookup at start-up returns
     * null and every announcement is silently dropped. It was, and the driven
     * Follow test is what found it: the switch flipped correctly, the region
     * stayed empty, and nothing anywhere threw. The same ordering trap as the
     * strings block above, one script down.
     */
    function announce( text ) {
        var region = document.getElementById( 'k-live-status' );
        if ( region ) {
            region.textContent = text;
        }
    }

    function maybeAnnounce() {
        var now = Date.now();
        if ( pendingLines === 0 || ( now - lastAnnounce ) < ANNOUNCE_MS ) {
            return;
        }
        announce(
            strings.newLines.replace( '{count}', pendingLines ).replace( '{errors}', pendingErrors )
        );
        lastAnnounce  = now;
        pendingLines  = 0;
        pendingErrors = 0;
    }

    function appendLine( raw ) {
        var button = document.createElement( 'button' );
        button.type = 'button';
        button.className = 'k-stream-line';
        button.setAttribute( 'aria-pressed', 'false' );
        button.dataset.index = String( nextIndex );
        button.dataset.testid = 'logs.line.' + nextIndex;
        nextIndex += 1;

        // The appended line is rendered as plain text rather than re-parsed
        // into spans: PHP's Logger::parseLine() is the single parser, and a
        // second implementation here would be free to drift from it. The level
        // word is still the first thing in the text, which is what §4's
        // "level is text first" actually requires.
        button.textContent = raw;
        button.dataset.message = raw;
        button.dataset.context = '';

        if ( /\[(EMERGENCY|ALERT|CRITICAL|ERROR)\]/.test( raw ) ) {
            button.classList.add( 'k-line--error' );
            pendingErrors += 1;
        } else if ( /\[WARNING\]/.test( raw ) ) {
            button.classList.add( 'k-line--warn' );
        }

        pre.appendChild( button );
        pendingLines += 1;
    }

    function poll() {
        fetch( 'api/logs.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': strings.csrf
            },
            body: JSON.stringify( {
                action: 'read',
                file: stream.dataset.file,
                offset: shownTotal,
                limit: 500
            } )
        } ).then( function ( response ) {
            return response.ok ? response.json() : null;
        } ).then( function ( data ) {
            if ( ! data || ! data.success || ! Array.isArray( data.lines ) ) {
                return;
            }
            data.lines.forEach( appendLine );
            shownTotal = typeof data.total === 'number' ? data.total : shownTotal + data.lines.length;
            if ( following ) {
                stream.scrollTop = stream.scrollHeight;
            }
            maybeAnnounce();
        } ).catch( function () {
            // A failed poll is not a state the design specifies and is not
            // worth a banner: the next tick tries again.
        } );
    }

    function setFollowing( on ) {
        following = on;
        followBtn.setAttribute( 'aria-checked', on ? 'true' : 'false' );

        if ( on ) {
            pausedSaid       = false;
            stream.scrollTop = stream.scrollHeight;
            timer            = window.setInterval( poll, POLL_MS );
        } else if ( timer !== null ) {
            window.clearInterval( timer );
            timer = null;
        }
    }

    followBtn.addEventListener( 'click', function () {
        setFollowing( followBtn.getAttribute( 'aria-checked' ) !== 'true' );
    } );

    /* §2: "Scrolling up turns Follow off automatically and says so once." Once
       is literal — `pausedSaid` is what makes it once, and it resets when
       Follow is turned back on. */
    stream.addEventListener( 'scroll', function () {
        if ( ! following ) {
            return;
        }
        var atBottom = ( stream.scrollHeight - stream.scrollTop - stream.clientHeight ) < 4;
        if ( ! atBottom ) {
            setFollowing( false );
            if ( ! pausedSaid ) {
                announce( strings.followPaused );
                pausedSaid = true;
            }
        }
    } );
} )();
</script>

<?php klytos_do_action( 'admin.logs.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
