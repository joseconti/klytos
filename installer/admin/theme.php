<?php

/**
 * Klytos Admin — Design (theme)
 *
 * Manifest entry 3 · template `record-form` · H1 "Design".
 * Built in Phase 4 Step 4, stage 5 (the form screens) against
 * `SPEC/screens/template-record-form.md`, `SPEC/manifest.md` §3 and
 * `SPEC/accessibility.md` §10.7.
 *
 * Three cards, exactly as the manifest lists them: Palette · Type scale ·
 * Radii and spacing. The fourth card the manifest names — Preview — is
 * DEFERRED to its own Phase 5 slice: no file in the delivery says what it
 * previews or in what form, and inventing that is Phase 4 rule 2. It stays a
 * manifest row and the redesign is not complete while it is open
 * (`docs/roadmap.md` §0b).
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
use Klytos\Core\ThemeManager;

$pageTitle = __( 'design.title' );
$auth      = $app->getAuth();
$theme     = $app->getTheme();
$success   = '';

/**
 * The colour keys the Palette card edits, in the order it draws them.
 *
 * The labels live under `design.*` in the catalogues. The screen this replaces
 * asked for `theme.colors`, `theme.primary_color` and so on — keys that do not
 * exist in any of the 20 catalogues, so every label on the shipped Design
 * screen rendered as its own key. Fixed here rather than filed: it is one
 * prefix, and the strings were already translated under the right one.
 */
$colorKeys = [
    'primary'    => 'design.primary_color',
    'secondary'  => 'design.secondary_color',
    'accent'     => 'design.accent_color',
    'background' => 'design.background_color',
    'surface'    => 'design.surface_color',
    'text'       => 'design.text_color',
    'text_muted' => 'design.text_muted_color',
    'border'     => 'design.border_color',
];

/** Human labels for the four guarded pairs, keyed as ThemeManager returns them. */
$pairLabels = [
    'text/background'       => 'design.pair_text_background',
    'text/surface'          => 'design.pair_text_surface',
    'text_muted/background' => 'design.pair_text_muted_background',
    'text_muted/surface'    => 'design.pair_text_muted_surface',
];

$themeData = $theme->get();

/** @var array<string,string> Field-level errors, keyed by control name. */
$fieldErrors = [];
/** @var array<int,array{name:string,message:string}> The error summary's rows, in DOM order. */
$summaryRows = [];
/** @var array<int,array> Pairs that refused the save, if any. */
$refusedPairs = [];

// The palette the screen RENDERS. On a refused save it is what the person
// posted, not what is stored — sending them back to the old values would
// discard their work and hide which colour caused the refusal.
$palette = $themeData['colors'] ?? [];

// ─── Handle POST ────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $posted = [];
    foreach ( array_keys( $colorKeys ) as $key ) {
        $value = trim( (string) ( $_POST[ $key ] ?? '' ) );

        if ( $value === '' ) {
            // An empty field keeps the stored value rather than blanking the
            // token — a theme with no background is not a state the front end
            // can render.
            $posted[ $key ] = $palette[ $key ] ?? '';
            continue;
        }

        if ( ! Helpers::isValidHexColor( $value ) ) {
            $fieldErrors[ $key ] = __( 'design.invalid_hex' );
            $summaryRows[]       = [
                'name'    => $key,
                'message' => __( 'design.summary_invalid_hex', [ 'field' => __( $colorKeys[ $key ] ) ] ),
            ];
            // Kept in the palette so the field renders what was typed and the
            // person can see and fix it.
            $posted[ $key ] = $value;
            continue;
        }

        $posted[ $key ] = $value;
    }

    $palette = array_merge( $palette, $posted );

    /*
     * accessibility.md §10.7 — the guard.
     *
     * Measured on the POSTED palette, before anything is written: a refusal
     * that fires after the save is not a refusal. `ThemeManager::contrastPairs()`
     * is the single implementation of both halves of §10.7 — the ratio this
     * screen shows and the verdict it gates on come from the same call.
     */
    $candidatePairs = ThemeManager::contrastPairs( $palette );
    $failingPairs   = array_values( array_filter(
        $candidatePairs,
        static fn( array $pair ): bool => ! $pair['passes']
    ) );

    $overrideRequested = ! empty( $_POST['contrast_override'] );

    if ( $fieldErrors !== [] ) {
        // A colour that is not a colour cannot be measured, so the contrast
        // guard has nothing to say yet. One problem reported at a time, in the
        // order the person can act on it.
        $refusedPairs = [];
    } elseif ( $failingPairs !== [] && ! $overrideRequested ) {
        $refusedPairs = $failingPairs;

        foreach ( $failingPairs as $pair ) {
            $pairKey = $pair['foreground'] . '/' . $pair['background'];

            $summaryRows[] = [
                'name'    => $pair['foreground'],
                'message' => $pair['measurable']
                    ? __( 'design.summary_contrast', [
                        'pair'  => __( $pairLabels[ $pairKey ] ?? $pairKey ),
                        'ratio' => number_format( (float) $pair['ratio'], 2 ),
                    ] )
                    : __( 'design.summary_unmeasurable', [
                        'pair' => __( $pairLabels[ $pairKey ] ?? $pairKey ),
                    ] ),
            ];
        }
    }

    if ( $summaryRows === [] ) {
        $theme->setColors( $palette );

        $theme->setFonts( [
            'heading'          => trim( (string) ( $_POST['heading'] ?? '' ) ),
            'body'             => trim( (string) ( $_POST['body'] ?? '' ) ),
            'code'             => trim( (string) ( $_POST['code'] ?? '' ) ),
            'base_size'        => trim( (string) ( $_POST['base_size'] ?? '16px' ) ),
            'google_fonts_url' => trim( (string) ( $_POST['google_fonts_url'] ?? '' ) ),
        ] );

        $theme->setLayout( [
            'max_width'     => trim( (string) ( $_POST['max_width'] ?? '1200px' ) ),
            'header_style'  => $_POST['header_style'] ?? 'sticky',
            'border_radius' => trim( (string) ( $_POST['border_radius'] ?? '8px' ) ),
        ] );

        /*
         * §10.7: "without an explicit override that is RECORDED". A checkbox
         * that only unblocks the save is not a record — six months later
         * nobody could tell a considered exception from a mis-click. So each
         * overridden pair is written with its measured ratio, who recorded it
         * and when, and it survives in the theme document.
         */
        if ( $overrideRequested && $failingPairs !== [] ) {
            $recorded = $themeData['contrast_overrides'] ?? [];

            foreach ( $failingPairs as $pair ) {
                $recorded[] = [
                    'pair'  => $pair['foreground'] . '/' . $pair['background'],
                    'ratio' => $pair['ratio'],
                    'by'    => $auth->getUsername(),
                    'at'    => klytos_gmdate( 'c' ),
                ];
            }

            $theme->set( [ 'contrast_overrides' => $recorded ] );
        }

        $success   = __( 'design.saved' );
        $themeData = $theme->get();
        $palette   = $themeData['colors'] ?? [];
    }
}

// The pairs the screen RENDERS — measured on whatever is on screen, so the
// number beside a pair always describes the values in the fields.
$contrastPairs = ThemeManager::contrastPairs( $palette );
$hasFailingPair = array_filter( $contrastPairs, static fn( array $p ): bool => ! $p['passes'] ) !== [];

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
    return $html . '<button type="submit" form="k-design-form"'
        . ' class="k-btn k-btn--primary k-btn--sm"'
        . ' data-testid="design.save">'
        . klytos_esc_html( __( 'common.save' ) )
        . '</button>';
} );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.theme.before' ); ?>

<?php if ( $success !== '' ) : ?>
    <?php // §2 Success: "the page reloads with a role="status" line under the H1". ?>
    <p class="k-status-line" role="status" data-testid="design.status_line">
        <?php echo klytos_esc_html( $success ); ?>
    </p>
<?php endif; ?>

<?php if ( $summaryRows !== [] ) : ?>
    <?php /*
     * §2 Error — form level: an error summary at the top of main, role="alert",
     * focus moved to it on load, listing every failed field as a link to that
     * field. tabindex="-1" makes it focusable without putting it in the tab
     * order (a container in the tab order is a trap for everyone else).
     */ ?>
    <div class="k-error-summary"
         id="design-error-summary"
         role="alert"
         tabindex="-1"
         data-testid="design.error_summary">
        <h2><?php echo klytos_esc_html( __( 'design.summary_title' ) ); ?></h2>
        <ul>
            <?php foreach ( $summaryRows as $index => $row ) : ?>
                <li>
                    <a href="#design-field-<?php echo klytos_esc_attr( $row['name'] ); ?>"
                       data-testid="design.error_link.<?php echo (int) $index; ?>">
                        <?php echo klytos_esc_html( $row['message'] ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
/*
 * Entry 3 has no section nav in the manifest, so the template's optional left
 * column is absent from the DOM rather than rendered empty — the modifier
 * collapses the grid to one track (template-record-form.md §1, "[optional]").
 */
?>
<form method="post"
      id="k-design-form"
      class="k-record-form k-record-form--no-nav"
      data-testid="design.form">
    <?php echo klytos_csrf_field(); ?>

    <div class="k-card-stack">

        <?php klytos_do_action( 'admin.theme.before_palette' ); ?>

        <?php // ─── Card 1 — Palette ─────────────────────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="design-palette-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="design-palette-heading">
                    <?php echo klytos_esc_html( __( 'design.card_palette' ) ); ?>
                </h2>

                <div class="k-field-grid k-field-grid--pair">
                    <?php foreach ( $colorKeys as $key => $labelKey ) : ?>
                        <?php
                        $value      = (string) ( $palette[ $key ] ?? '' );
                        $hasError   = isset( $fieldErrors[ $key ] );
                        $describedBy = 'design-hint-' . $key . ( $hasError ? ' design-error-' . $key : '' );
                        ?>
                        <div class="k-field">
                            <?php // §4: every control has a VISIBLE <label for>. ?>
                            <label class="k-label" for="design-field-<?php echo klytos_esc_attr( $key ); ?>">
                                <?php echo klytos_esc_html( __( $labelKey ) ); ?>
                            </label>

                            <div class="k-swatch-row">
                                <?php /*
                                 * Manifest §3: "colour inputs are <input type="color">
                                 * PLUS a mono hex field with a visible label — the picker
                                 * alone is not keyboard-friendly enough."
                                 *
                                 * The picker carries NO name. The screen this replaces gave
                                 * both controls the same name, so the picker's value was
                                 * posted last and silently won — and with JavaScript off the
                                 * hex field a person had just typed into was discarded. The
                                 * hex field is the value; the picker is a mirror of it.
                                 */ ?>
                                <input type="color"
                                       class="k-swatch"
                                       id="design-swatch-<?php echo klytos_esc_attr( $key ); ?>"
                                       value="<?php echo klytos_esc_attr( strlen( $value ) === 7 ? $value : '#000000' ); ?>"
                                       aria-label="<?php echo klytos_esc_attr( __( 'design.picker_label', [ 'field' => __( $labelKey ) ] ) ); ?>"
                                       data-mirrors="design-field-<?php echo klytos_esc_attr( $key ); ?>"
                                       data-testid="design.swatch.<?php echo klytos_esc_attr( $key ); ?>">

                                <input type="text"
                                       class="k-control k-control--mono"
                                       id="design-field-<?php echo klytos_esc_attr( $key ); ?>"
                                       name="<?php echo klytos_esc_attr( $key ); ?>"
                                       value="<?php echo klytos_esc_attr( $value ); ?>"
                                       spellcheck="false"
                                       autocapitalize="off"
                                       aria-describedby="<?php echo klytos_esc_attr( $describedBy ); ?>"
                                       <?php echo $hasError ? 'aria-invalid="true"' : ''; ?>
                                       data-testid="design.hex.<?php echo klytos_esc_attr( $key ); ?>">
                            </div>

                            <p class="k-hint" id="design-hint-<?php echo klytos_esc_attr( $key ); ?>">
                                <?php echo klytos_esc_html( __( 'design.hex_hint' ) ); ?>
                            </p>

                            <?php if ( $hasError ) : ?>
                                <p class="k-error" id="design-error-<?php echo klytos_esc_attr( $key ); ?>">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors[ $key ] ); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php // ─── accessibility.md §10.7 — the measured pairs ─── ?>
                <h3 class="k-label" id="design-contrast-heading">
                    <?php echo klytos_esc_html( __( 'design.contrast_heading' ) ); ?>
                </h3>
                <p class="k-hint"><?php echo klytos_esc_html( __( 'design.contrast_intro' ) ); ?></p>

                <ul class="k-card-body" aria-labelledby="design-contrast-heading" data-testid="design.pairs">
                    <?php foreach ( $contrastPairs as $pair ) : ?>
                        <?php
                        $pairKey   = $pair['foreground'] . '/' . $pair['background'];
                        $pairLabel = __( $pairLabels[ $pairKey ] ?? $pairKey );
                        ?>
                        <li class="k-pair" data-testid="design.pair.<?php echo klytos_esc_attr( str_replace( '/', '_', $pairKey ) ); ?>">
                            <?php /*
                             * The swatch draws the two colours the ratio beside it
                             * describes, and it carries NO TEXT.
                             *
                             * The first version wrote the word "Sample" in the
                             * foreground colour on the background colour, which is
                             * exactly what the pair is — so on a failing pair the
                             * specimen itself failed axe's contrast rule at 2.32:1.
                             * That is not a false positive: it was real text, really
                             * unreadable. But a screen whose job is to SHOW a failing
                             * pair cannot also be forbidden from rendering one, and
                             * lowering the pair to pass would defeat the screen. So
                             * the sample stops being text: a bar in the foreground
                             * colour on the background colour says the same thing
                             * with nothing to read. The ratio and the verdict beside
                             * it are the information, and both are real text.
                             */ ?>
                            <span class="k-pair-swatch"
                                  style="background:<?php echo klytos_esc_attr( (string) ( $pair['background_hex'] ?? '' ) ); ?>"
                                  aria-hidden="true">
                                <span class="k-pair-bar"
                                      style="background:<?php echo klytos_esc_attr( (string) ( $pair['foreground_hex'] ?? '' ) ); ?>"></span>
                            </span>

                            <span><?php echo klytos_esc_html( $pairLabel ); ?></span>

                            <span class="k-ratio">
                                <?php if ( $pair['measurable'] ) : ?>
                                    <?php echo klytos_esc_html( number_format( (float) $pair['ratio'], 2 ) . ':1' ); ?>
                                    <?php /*
                                     * §1.3: colour is never the only channel. The verdict is
                                     * a WORD in a badge; the badge tone only repeats it.
                                     */ ?>
                                    <span class="k-badge <?php echo $pair['passes'] ? 'k-badge--exito' : 'k-badge--peligro'; ?>">
                                        <?php echo klytos_esc_html( $pair['passes']
                                            ? __( 'design.contrast_pass' )
                                            : __( 'design.contrast_fail' ) ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="k-badge k-badge--offline">
                                        <?php echo klytos_esc_html( __( 'design.contrast_unmeasurable' ) ); ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ( $hasFailingPair ) : ?>
                    <?php /*
                     * The override is offered only when there is something to
                     * override. A checkbox that is always on screen invites the
                     * habit of ticking it, which is the opposite of "explicit".
                     * It is a checkbox and not a switch: it needs Save
                     * (template-record-form.md §4).
                     */ ?>
                    <div class="k-field">
                        <label class="k-choice k-hit-24" for="design-contrast-override">
                            <input type="checkbox"
                                   class="k-check"
                                   id="design-contrast-override"
                                   name="contrast_override"
                                   value="1"
                                   aria-describedby="design-override-hint"
                                   data-testid="design.override">
                            <span><?php echo klytos_esc_html( __( 'design.override_label' ) ); ?></span>
                        </label>
                        <p class="k-hint" id="design-override-hint">
                            <?php echo klytos_esc_html( __( 'design.override_hint' ) ); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php klytos_do_action( 'admin.theme.after_palette' ); ?>

        <?php // ─── Card 2 — Type scale ──────────────────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="design-type-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="design-type-heading">
                    <?php echo klytos_esc_html( __( 'design.card_type_scale' ) ); ?>
                </h2>

                <div class="k-field-grid k-field-grid--pair">
                    <?php
                    $typeFields = [
                        'heading'   => 'design.heading_font',
                        'body'      => 'design.body_font',
                        'code'      => 'design.code_font',
                        'base_size' => 'design.base_size',
                    ];
                    foreach ( $typeFields as $name => $labelKey ) :
                        ?>
                        <div class="k-field">
                            <label class="k-label" for="design-field-<?php echo klytos_esc_attr( $name ); ?>">
                                <?php echo klytos_esc_html( __( $labelKey ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control"
                                   id="design-field-<?php echo klytos_esc_attr( $name ); ?>"
                                   name="<?php echo klytos_esc_attr( $name ); ?>"
                                   value="<?php echo klytos_esc_attr( (string) ( $themeData['fonts'][ $name ] ?? '' ) ); ?>"
                                   data-testid="design.font.<?php echo klytos_esc_attr( $name ); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="k-field">
                    <label class="k-label" for="design-field-google_fonts_url">
                        <?php echo klytos_esc_html( __( 'design.google_fonts_url' ) ); ?>
                    </label>
                    <input type="url"
                           class="k-control k-control--mono"
                           id="design-field-google_fonts_url"
                           name="google_fonts_url"
                           value="<?php echo klytos_esc_attr( (string) ( $themeData['fonts']['google_fonts_url'] ?? '' ) ); ?>"
                           spellcheck="false"
                           autocapitalize="off"
                           autocomplete="url"
                           data-testid="design.font.google_fonts_url">
                </div>
            </div>
        </section>

        <?php // ─── Card 3 — Radii and spacing ───────────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="design-layout-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="design-layout-heading">
                    <?php echo klytos_esc_html( __( 'design.card_radii_spacing' ) ); ?>
                </h2>

                <div class="k-field-grid k-field-grid--pair">
                    <div class="k-field">
                        <label class="k-label" for="design-field-max_width">
                            <?php echo klytos_esc_html( __( 'design.max_width' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="design-field-max_width"
                               name="max_width"
                               value="<?php echo klytos_esc_attr( (string) ( $themeData['layout']['max_width'] ?? '1200px' ) ); ?>"
                               spellcheck="false"
                               autocapitalize="off"
                               data-testid="design.layout.max_width">
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="design-field-border_radius">
                            <?php echo klytos_esc_html( __( 'design.border_radius' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="design-field-border_radius"
                               name="border_radius"
                               value="<?php echo klytos_esc_attr( (string) ( $themeData['layout']['border_radius'] ?? '8px' ) ); ?>"
                               spellcheck="false"
                               autocapitalize="off"
                               data-testid="design.layout.border_radius">
                    </div>
                </div>

                <div class="k-field">
                    <label class="k-label" for="design-field-header_style">
                        <?php echo klytos_esc_html( __( 'design.header_style' ) ); ?>
                    </label>
                    <select class="k-control"
                            id="design-field-header_style"
                            name="header_style"
                            data-testid="design.layout.header_style">
                        <?php
                        $headerStyles = [
                            'sticky' => 'design.header_sticky',
                            'fixed'  => 'design.header_fixed',
                            'static' => 'design.header_static',
                        ];
                        $currentStyle = (string) ( $themeData['layout']['header_style'] ?? 'sticky' );
                        foreach ( $headerStyles as $value => $labelKey ) :
                            ?>
                            <option value="<?php echo klytos_esc_attr( $value ); ?>"
                                <?php echo $currentStyle === $value ? 'selected' : ''; ?>>
                                <?php echo klytos_esc_html( __( $labelKey ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

    </div>
</form>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function () {
    'use strict';

    /*
     * The picker mirrors the hex field, in both directions. It is progressive
     * enhancement and nothing else: with this script absent the hex field is
     * still the value, still labelled, and still what posts.
     */
    var swatches = document.querySelectorAll('[data-mirrors]');

    Array.prototype.forEach.call(swatches, function (swatch) {
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
     * §2 Error — form level: "focus moved to it on load". The summary is only
     * in the DOM when there is one.
     */
    var summary = document.getElementById('design-error-summary');
    if (summary) {
        summary.focus();
    }
})();
</script>

<?php klytos_do_action( 'admin.theme.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
