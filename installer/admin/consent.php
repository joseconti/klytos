<?php

/**
 * Klytos Admin — Consent
 *
 * Manifest entry 25 · template `record-form` · H1 "Consent".
 * Built in Phase 4 Step 4, stage 5 (the form screens), batch B screen 4,
 * against `SPEC/screens/template-record-form.md`, `SPEC/manifest.md` §25 and
 * `SPEC/accessibility.md` §10.4.
 *
 * THREE CARDS, and the manifest lists three. Two of them are its own; the third
 * is not:
 *
 * - **Banner configuration** — backed by `ConsentManager::getConfig()` /
 *   `saveConfig()`.
 * - **Cookie audit** — backed by `getAuditReport()` / `getPluginDeclarations()`.
 * - **Banner preview** — the manifest's first delta names a preview and gives
 *   the rule it must satisfy; it is built here.
 * - **Acceptance stats (stat row)** — NOT BACKED, and deferred under D-088's
 *   standing answer 1. The prototype draws it as "Accepted everything 62% ·
 *   Essential only 31% · Ignored the banner 7%", which is visitor telemetry:
 *   Klytos publishes a STATIC site, the visitor's choice is written to a cookie
 *   in their own browser, and there is no endpoint that receives it, no
 *   collection that stores it and nothing that aggregates it. A percentage here
 *   would be a number this build invented. Carried in `docs/roadmap.md` §0c;
 *   the redesign is not reportable as complete while it stands.
 *
 * THE STRICTEST RULE IN THE BUNDLE, and the delivery contradicts itself on it.
 * `manifest.md` §25 says the configuration screen "offers **no** option to make
 * reject less prominent; that option does not exist", and `accessibility.md`
 * §10.4 says "Reject all is a control of the same prominence, size and level as
 * Accept all". The PROTOTYPE draws both of the things those two forbid: a
 * switch labelled "Reject is as prominent as accept", and a preview whose
 * "Aceptar" is `variant="primary"` beside a `variant="secondary"` "Rechazar".
 * Two normative files agree with each other and only the drawing disagrees, so
 * this screen follows the SPEC — the same precedent as D-074, where
 * `SPEC/navigation.md` beat the prototypes' own `navGroups`. The contradiction
 * is registered as **DR-008** rather than absorbed silently.
 *
 * Authorization is NOT asserted in this file. `consent.php` is mapped to
 * `site.configure` in `klytos_admin_gate_map()` and enforced centrally by
 * `klytos_enforce_admin_gate()` (bootstrap.php) — the default-deny fix S-07
 * produced. A second in-file check would be a second place for one decision.
 *
 * @package Klytos
 * @since   0.17.0
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

use Klytos\Core\BuildEngine;

$pageTitle      = __( 'consent.title' );
$auth           = $app->getAuth();
$consentManager = $app->getConsentManager();

$success = '';

/** @var array<string,string> Field-level errors, keyed by control name. */
$fieldErrors = [];
/** @var array<int,array{name:string,message:string}> The error summary's rows, in DOM order. */
$summaryRows = [];

$config = $consentManager->getConfig();

/** The plugin id whose Remove is ARMED, if any (§2's inline two-step confirm). */
$armedPlugin = '';

// ─── Handle POST ────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $action = (string) ( $_POST['action'] ?? '' );

    if ( $action === 'save_config' ) {
        $bannerText = trim( (string) ( $_POST['banner_text'] ?? '' ) );
        $privacyUrl = trim( (string) ( $_POST['privacy_url'] ?? '' ) );
        $cookieDays = trim( (string) ( $_POST['cookie_days'] ?? '' ) );

        if ( $bannerText === '' ) {
            $fieldErrors['banner_text'] = __( 'consent.error_banner_text' );
            $summaryRows[]              = [
                'name'    => 'banner_text',
                'message' => __( 'consent.summary_banner_text' ),
            ];
        }

        // 1–730 is the range the shipped control already offered; the manager
        // clamps the floor at 1 and this reports the ceiling rather than
        // silently accepting a value it will not honour.
        $cookieDaysValid = $cookieDays !== ''
            && ctype_digit( $cookieDays )
            && (int) $cookieDays >= 1
            && (int) $cookieDays <= 730;

        if ( ! $cookieDaysValid ) {
            $fieldErrors['cookie_days'] = __( 'consent.error_cookie_days' );
            $summaryRows[]              = [
                'name'    => 'cookie_days',
                'message' => __( 'consent.summary_cookie_days' ),
            ];
        }

        if ( $summaryRows === [] ) {
            try {
                /*
                 * `categories` is carried through from what is STORED, and that
                 * is a bug fix rather than a detail.
                 *
                 * `saveConfig()` replaces the whole `categories` array with
                 * whatever it is handed. The screen this replaces built that
                 * array from `$_POST['custom_categories']` — a field its own
                 * form never rendered — so it always passed an empty array, and
                 * every save from this screen silently WIPED every custom
                 * category. They can only be created over MCP
                 * (`klytos_set_consent_config`, which merges and therefore
                 * preserves them), so the two surfaces disagreed and the admin
                 * one destroyed the other's data.
                 *
                 * This screen has no categories card in the manifest, so it does
                 * not edit them — and a screen that does not edit a value must
                 * not be able to delete it. Pinned by its own test.
                 */
                $consentManager->saveConfig( [
                    'enabled'     => ! empty( $_POST['enabled'] ),
                    'banner_text' => $bannerText,
                    'privacy_url' => $privacyUrl,
                    'cookie_days' => (int) $cookieDays,
                    'categories'  => $config['categories'] ?? [],
                ] );

                // The banner lives in the generated site, so a change here is
                // not visible to a visitor until the site is rebuilt.
                ( new BuildEngine( $app ) )->buildAll();

                $success = __( 'consent.saved' );
                $config  = $consentManager->getConfig();
            } catch ( \Throwable $e ) {
                // §2 "Error — the save failed for a server reason": the summary
                // names the cause, never a code alone. The exception text is
                // the cause; it is escaped at print time like any other value.
                $summaryRows[] = [
                    'name'    => 'banner_text',
                    'message' => __( 'consent.summary_server', [ 'reason' => $e->getMessage() ] ),
                ];
            }
        }
    } elseif ( $action === 'arm_delete_declaration' ) {
        // First click of §2's two-step confirm. It POSTs and the row re-renders
        // armed, so the confirm works with JavaScript disabled — a two-step
        // that lives in script alone is a one-step delete for anyone without it.
        $armedPlugin = (string) ( $_POST['plugin_id'] ?? '' );
    } elseif ( $action === 'delete_declaration' ) {
        try {
            $consentManager->deletePluginDeclaration( (string) ( $_POST['plugin_id'] ?? '' ) );
            $success = __( 'consent.declaration_removed' );
        } catch ( \Throwable $e ) {
            $summaryRows[] = [
                'name'    => 'banner_text',
                'message' => __( 'consent.summary_server', [ 'reason' => $e->getMessage() ] ),
            ];
        }
    } elseif ( $action === 'export_json' ) {
        $audit = $consentManager->getAuditReport();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="cookie-audit-' . klytos_gmdate( 'Y-m-d' ) . '.json"' );
        echo json_encode( $audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        exit;
    } elseif ( $action === 'export_csv' ) {
        $declarations = $consentManager->getPluginDeclarations();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="cookie-audit-' . klytos_gmdate( 'Y-m-d' ) . '.csv"' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, ['Plugin ID', 'Plugin Name', 'Category', 'Cookie', 'Type', 'Duration', 'Description'] );
        foreach ( $declarations as $decl ) {
            if ( empty( $decl['cookies'] ) ) {
                fputcsv( $out, [$decl['plugin_id'], $decl['name'], $decl['category'], '-', '-', '-', '-'] );
            } else {
                foreach ( $decl['cookies'] as $cookie ) {
                    fputcsv( $out, [
                        $decl['plugin_id'],
                        $decl['name'],
                        $decl['category'],
                        $cookie['name'] ?? '',
                        $cookie['type'] ?? 'cookie',
                        $cookie['duration'] ?? '',
                        $cookie['description'] ?? '',
                    ] );
                }
            }
        }
        fclose( $out );
        exit;
    }
}

// ─── Load the audit ─────────────────────────────────────────────
$audit        = $consentManager->getAuditReport();
$declarations = $consentManager->getPluginDeclarations();

/**
 * The audit table's rows: one per declared cookie, flattened across plugins.
 *
 * The manifest gives this card four columns and no more (Cookie · Type ·
 * Duration · Description), which is a per-COOKIE shape, while a declaration —
 * and therefore its removal — is per-PLUGIN. Rather than add a fifth column
 * that would repeat one plugin's Remove on each of its cookies, the removals
 * live in their own `<h3>` group inside the same card (§4 provides for groups
 * inside a card), reusing the `.k-collection` layer built in D-089.
 *
 * @var array<int,array{name:string,type:string,duration:string,description:string,category:string,tone:string,plugin:string}>
 */
$auditRows = [];

/** Category id → badge tone. Necessary is the only always-on category. */
$categoryTone = static function ( array $category ): string {
    if ( ! empty( $category['required'] ) ) {
        return 'k-badge--info';
    }

    return ( $category['id'] ?? '' ) === 'marketing' ? 'k-badge--aviso' : 'k-badge--acento';
};

foreach ( $audit['categories'] as $catData ) {
    $category = $catData['category'];

    foreach ( $catData['plugins'] as $plugin ) {
        foreach ( $plugin['cookies'] ?? [] as $cookie ) {
            $auditRows[] = [
                'name'        => (string) ( $cookie['name'] ?? '' ),
                'type'        => (string) ( $category['name'] ?? $category['id'] ?? '' ),
                'duration'    => (string) ( $cookie['duration'] ?? '' ),
                'description' => (string) ( $cookie['description'] ?? '' ),
                'tone'        => $categoryTone( $category ),
                'plugin'      => (string) ( $plugin['name'] ?? $plugin['plugin_id'] ?? '' ),
            ];
        }
    }
}

/** @var array<int,array> Filterable so a plugin can surface a cookie it sets outside a declaration. */
$auditRows = klytos_apply_filters( 'admin.consent.audit_rows', $auditRows, $audit );

/** @var array<int,array> Filterable for the same reason, one level up. */
$declarations = klytos_apply_filters( 'admin.consent.declarations', $declarations );

$csrf = $auth->getCsrfToken();

/*
 * template-record-form.md §1: "The primary Save lives in the TOOLBAR, not at
 * the foot of the page, and it is the same button on every form screen."
 *
 * It is present here, unlike entry 19 and entry 6, because the Banner
 * configuration card carries four savable fields. The toolbar is emitted by the
 * shell, outside <main> and outside the form, so the button associates by
 * `form=` — which is also what makes Enter in a text field save (§4). No
 * JavaScript is involved in either half.
 */
klytos_add_filter( 'admin.topbar_actions', static function ( string $html ): string {
    return $html . '<button type="submit" form="k-consent-form"'
        . ' class="k-btn k-btn--primary k-btn--sm"'
        . ' data-testid="consent.save">'
        . klytos_esc_html( __( 'common.save' ) )
        . '</button>';
} );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.consent.before' ); ?>

<?php if ( $success !== '' ) : ?>
    <?php // §2 Success: "the page reloads with a role="status" line under the H1". ?>
    <p class="k-status-line" role="status" data-testid="consent.status_line">
        <?php echo klytos_esc_html( $success ); ?>
    </p>
<?php endif; ?>

<?php if ( $summaryRows !== [] ) : ?>
    <?php /*
     * §2 Error — form level: a summary at the top of main, role="alert", focus
     * moved to it on load, every failed field a link to that field.
     * tabindex="-1" makes it focusable without putting it in the tab order.
     */ ?>
    <div class="k-error-summary"
         id="consent-error-summary"
         role="alert"
         tabindex="-1"
         data-testid="consent.error_summary">
        <h2><?php echo klytos_esc_html( __( 'consent.summary_title' ) ); ?></h2>
        <ul>
            <?php foreach ( $summaryRows as $index => $row ) : ?>
                <li>
                    <a href="#consent-field-<?php echo klytos_esc_attr( $row['name'] ); ?>"
                       data-testid="consent.error_link.<?php echo (int) $index; ?>">
                        <?php echo klytos_esc_html( $row['message'] ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<style nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
/*
 * `grid-template-columns` is PER SCREEN (template-list-table.md §1), so it
 * lives on the screen that owns it and not in klytos-components.css.
 *
 * WHERE THE VALUE COMES FROM, since the manifest does not carry it. §25 names
 * this card a list-table and records neither its columns nor its widths — the
 * same shape as 28 Licence and 35 Updates, except DR-006's table never
 * enumerated entry 25 at all, so it is a fourteenth blocked surface the sent
 * request omits. What makes this one different from those twelve is that
 * nothing contradicts: DR-006's stated reason for refusing the prototypes was
 * that their tables carry DIFFERENT COLUMNS from the manifest's, and here the
 * manifest has none to differ from. Three sources agree instead —
 * `Klytos Admin - Screens 4.dc.html` draws `170px 120px 90px 1fr` over
 * Cookie · Type · Duration · Description, and the shipped screen already
 * renders those same four headings. So this is read from the delivery rather
 * than derived, and the missing row is added to DR-006 so Design still records
 * it normatively.
 *
 * The :not() is load-bearing, exactly as on Pages: `.k-table-row-full` is
 * (0,1,0) and carries `grid-template-columns:1fr` for the empty row that spans
 * every column, while a bare `.k-consent-audit-table tr` is (0,1,1) and would
 * silently outrank it — build rule 1's specificity mechanism, which L-032 says
 * to read back out of the browser rather than reason about.
 */
.k-consent-audit-table tr:not(.k-table-row-full) {
    grid-template-columns: 170px 120px 90px 1fr;
}

/*
 * The preview. It is a picture of the shipped banner, so it borrows the
 * shipped banner's proportions and none of its colours: this is an admin
 * surface and it uses admin tokens, which is what keeps it legible in both
 * themes while the real banner is a light surface on the public site.
 */
.k-consent-preview {
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: var(--esp-8);
}

.k-consent-preview-banner {
    display: flex;
    flex-direction: column;
    gap: var(--esp-8);
    padding: var(--esp-16);
    border: 1px solid var(--separador);
    border-radius: 10px;
    background: var(--fondo-ventana);
}

.k-consent-preview-title {
    margin: 0;
    font: var(--type-card-heading);
    color: var(--texto-primario);
}

/*
 * `--texto-primario`, NOT `--texto-secundario`, and the reason is measured.
 *
 * Secondary on `--fondo-ventana` is **4.46:1 in light** — under AA by 0.04, and
 * DR-005 gap 2's own token pair arriving on a fourth surface. It is NOT
 * registered as a further addendum, because no delivered file states this
 * control's colour: the preview card is entry 25's own delta and the token was
 * this build's choice, so the defect is this build's too. That is exactly the
 * call D-090 made for the section nav, and D-078 before it for three pairs.
 *
 * Now 14.79:1 light / 15.29:1 dark, both recomputed independently from the
 * token hexes in Python and agreeing with axe.
 */
.k-consent-preview-text {
    margin: 0;
    font: var(--type-body);
    color: var(--texto-primario);
    text-wrap: pretty;
}

.k-consent-preview-actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--esp-8);
}

/*
 * accessibility.md §10.4, and it is the whole point of this card being here.
 *
 * Reject and Accept are ONE rule, so there is no second declaration for one of
 * them to drift away in. `min-width` is not decoration: "Accept all" and
 * "Reject all" are different lengths in every one of the 20 catalogues, and
 * equal padding around unequal text draws unequal buttons — which is precisely
 * the "same size" half of the rule. The shipped banner's `.cm-btn-choice`
 * carries the identical construction for the identical reason.
 */
.k-consent-preview-actions > .k-btn--primary {
    min-width: 168px;
}
</style>

<?php
/*
 * Three cards, like entry 3 — the template's optional section nav is absent
 * from the DOM rather than rendered empty, and the modifier collapses the grid
 * to one track (template-record-form.md §1, "[optional]").
 */
?>
<form method="post"
      id="k-consent-form"
      class="k-record-form k-record-form--no-nav"
      data-testid="consent.form">
    <?php echo klytos_csrf_field(); ?>
    <input type="hidden" name="action" value="save_config">

    <div class="k-card-stack">

        <?php klytos_do_action( 'admin.consent.before_banner' ); ?>

        <?php // ─── Card 1 — Banner configuration ────────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="consent-banner-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="consent-banner-heading">
                    <?php echo klytos_esc_html( __( 'consent.card_banner' ) ); ?>
                </h2>

                <div class="k-field">
                    <?php /*
                     * A CHECKBOX and not a switch, deliberately.
                     * template-record-form.md §4: "a control that takes effect
                     * immediately is role='switch'; a control that needs Save is
                     * a checkbox. On this template most are checkboxes, because
                     * this template has a Save button." This one needs Save —
                     * and it needs a site rebuild after it.
                     */ ?>
                    <label class="k-choice k-hit-24" for="consent-field-enabled">
                        <input type="checkbox"
                               class="k-check"
                               id="consent-field-enabled"
                               name="enabled"
                               value="1"
                               aria-describedby="consent-hint-enabled"
                               <?php echo ! empty( $config['enabled'] ) ? 'checked' : ''; ?>
                               data-testid="consent.enabled">
                        <span><?php echo klytos_esc_html( __( 'consent.enabled_label' ) ); ?></span>
                    </label>
                    <p class="k-hint" id="consent-hint-enabled">
                        <?php echo klytos_esc_html( __( 'consent.enabled_hint' ) ); ?>
                    </p>
                </div>

                <?php
                $bannerTextError = isset( $fieldErrors['banner_text'] );
                ?>
                <div class="k-field">
                    <label class="k-label" for="consent-field-banner_text">
                        <?php echo klytos_esc_html( __( 'consent.banner_text_label' ) ); ?>
                    </label>
                    <textarea class="k-control"
                              id="consent-field-banner_text"
                              name="banner_text"
                              rows="3"
                              required
                              aria-describedby="consent-hint-banner_text<?php echo $bannerTextError ? ' consent-error-banner_text' : ''; ?>"
                              <?php echo $bannerTextError ? 'aria-invalid="true"' : ''; ?>
                              data-testid="consent.banner_text"><?php
                                echo klytos_esc_textarea( (string) ( $config['banner_text'] ?? '' ) );
                                ?></textarea>
                    <p class="k-hint" id="consent-hint-banner_text">
                        <?php echo klytos_esc_html( __( 'consent.banner_text_hint' ) ); ?>
                    </p>
                    <?php if ( $bannerTextError ) : ?>
                        <p class="k-error" id="consent-error-banner_text">
                            <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                            <?php echo klytos_esc_html( $fieldErrors['banner_text'] ); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="k-field-grid k-field-grid--pair">
                    <div class="k-field">
                        <label class="k-label" for="consent-field-privacy_url">
                            <?php echo klytos_esc_html( __( 'consent.privacy_url_label' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="consent-field-privacy_url"
                               name="privacy_url"
                               value="<?php echo klytos_esc_attr( (string) ( $config['privacy_url'] ?? '' ) ); ?>"
                               spellcheck="false"
                               autocapitalize="off"
                               autocomplete="url"
                               aria-describedby="consent-hint-privacy_url"
                               data-testid="consent.privacy_url">
                        <p class="k-hint" id="consent-hint-privacy_url">
                            <?php echo klytos_esc_html( __( 'consent.privacy_url_hint' ) ); ?>
                        </p>
                    </div>

                    <?php
                    $cookieDaysError = isset( $fieldErrors['cookie_days'] );
                    ?>
                    <div class="k-field">
                        <label class="k-label" for="consent-field-cookie_days">
                            <?php echo klytos_esc_html( __( 'consent.cookie_days_label' ) ); ?>
                        </label>
                        <input type="number"
                               class="k-control"
                               id="consent-field-cookie_days"
                               name="cookie_days"
                               value="<?php echo klytos_esc_attr( (string) ( $config['cookie_days'] ?? 365 ) ); ?>"
                               min="1"
                               max="730"
                               inputmode="numeric"
                               required
                               aria-describedby="consent-hint-cookie_days<?php echo $cookieDaysError ? ' consent-error-cookie_days' : ''; ?>"
                               <?php echo $cookieDaysError ? 'aria-invalid="true"' : ''; ?>
                               data-testid="consent.cookie_days">
                        <p class="k-hint" id="consent-hint-cookie_days">
                            <?php echo klytos_esc_html( __( 'consent.cookie_days_hint' ) ); ?>
                        </p>
                        <?php if ( $cookieDaysError ) : ?>
                            <p class="k-error" id="consent-error-cookie_days">
                                <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                <?php echo klytos_esc_html( $fieldErrors['cookie_days'] ); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php /*
                 * manifest.md §25: "The configuration screen offers NO option to
                 * make reject less prominent; that option does not exist."
                 *
                 * So this is a STATEMENT and not a control. The prototype draws
                 * it as a switch — "Reject is as prominent as accept" — which is
                 * exactly the option the manifest says must not exist, and
                 * exactly what a site owner under pressure would turn off. It is
                 * the delivery contradicting itself; DR-008 asks Design to
                 * correct the drawing. Asserted as NOT a control by its own test,
                 * so it cannot quietly become one later.
                 */ ?>
                <p class="k-hint" data-testid="consent.prominence_note">
                    <?php echo klytos_esc_html( __( 'consent.prominence_note' ) ); ?>
                </p>
            </div>
        </section>

        <?php klytos_do_action( 'admin.consent.after_banner' ); ?>

        <?php // ─── Card 2 — Banner preview ──────────────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="consent-preview-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="consent-preview-heading">
                    <?php echo klytos_esc_html( __( 'consent.card_preview' ) ); ?>
                </h2>
                <p class="k-hint"><?php echo klytos_esc_html( __( 'consent.preview_intro' ) ); ?></p>

                <?php /*
                 * The preview's two choices are SPANS, not buttons.
                 *
                 * They must be measurable — the manifest's delta binds "the
                 * banner preview AND the shipped banner" to the same rule, so a
                 * test has to be able to read this pair's component, size and
                 * weight. But they must not be operable: a control here would do
                 * nothing, and §2's Disabled state is for a control that could
                 * act and currently may not, which is not this. A span carrying
                 * the same classes is drawn identically, is measurable, and is
                 * honest about being a picture.
                 *
                 * Both carry `k-btn k-btn--primary` and neither carries a
                 * modifier the other does not — the same one-rule-for-both
                 * construction as the shipped banner's `.cm-btn-choice`, and for
                 * the same reason: there is no second class to drift.
                 */ ?>
                <figure class="k-consent-preview" data-testid="consent.preview">
                    <div class="k-consent-preview-banner">
                        <p class="k-consent-preview-title">
                            <?php echo klytos_esc_html( __( 'consent.banner_title' ) ); ?>
                        </p>
                        <p class="k-consent-preview-text" data-testid="consent.preview_text">
                            <?php echo klytos_esc_html( (string) ( $config['banner_text'] ?? '' ) ); ?>
                        </p>
                        <div class="k-consent-preview-actions">
                            <span class="k-btn k-btn--primary" data-testid="consent.preview_reject">
                                <?php echo klytos_esc_html( __( 'consent.banner_reject_all' ) ); ?>
                            </span>
                            <span class="k-btn k-btn--primary" data-testid="consent.preview_accept">
                                <?php echo klytos_esc_html( __( 'consent.banner_accept_all' ) ); ?>
                            </span>
                            <span class="k-btn k-btn--secondary" data-testid="consent.preview_preferences">
                                <?php echo klytos_esc_html( __( 'consent.banner_preferences' ) ); ?>
                            </span>
                        </div>
                    </div>
                    <figcaption class="k-hint">
                        <?php echo klytos_esc_html( __( 'consent.preview_caption' ) ); ?>
                    </figcaption>
                </figure>
            </div>
        </section>

        <?php klytos_do_action( 'admin.consent.before_audit' ); ?>

        <?php // ─── Card 3 — Cookie and script audit ─────────────── ?>
        <section class="k-card k-card--table" aria-labelledby="consent-audit-caption">
            <?php /*
             * accessibility.md §2.1's exact markup: real <table> carrying the
             * full explicit role set, the naming column a <th role="rowheader">,
             * a VISIBLE <caption> with the count, and the grid on the table
             * elements rather than on a wrapper.
             *
             * Columns and widths come from the delivery's own prototype
             * (`Klytos Admin - Screens 4.dc.html`: Cookie · Type · Duration ·
             * Description at 170px 120px 90px 1fr). The manifest names this card
             * a list-table and records neither — the same shape as 28 Licence and
             * 35 Updates, except DR-006's table never enumerated entry 25 at all.
             * Unlike those twelve, nothing here contradicts: the manifest is
             * silent, the prototype is explicit, and the shipped screen already
             * renders those same four columns. Recorded as an adaptation, and the
             * missing row is added to DR-006 so Design still records it
             * normatively.
             */ ?>
            <div class="k-table-scroll"
                 tabindex="0"
                 role="group"
                 aria-labelledby="consent-audit-caption">
                <table class="k-table k-consent-audit-table"
                       role="table"
                       aria-labelledby="consent-audit-caption"
                       data-testid="consent.audit_table">
                    <caption class="k-table-caption" id="consent-audit-caption" aria-live="polite">
                        <?php echo klytos_esc_html( __( 'consent.audit_caption', [
                            'cookies' => (string) count( $auditRows ),
                            'plugins' => (string) (int) $audit['total_plugins'],
                            'scripts' => (string) (int) $audit['total_scripts'],
                        ] ) ); ?>
                    </caption>

                    <thead role="rowgroup">
                        <tr role="row">
                            <th role="columnheader" scope="col">
                                <?php echo klytos_esc_html( __( 'consent.col_cookie' ) ); ?>
                            </th>
                            <th role="columnheader" scope="col">
                                <?php echo klytos_esc_html( __( 'consent.col_type' ) ); ?>
                            </th>
                            <th role="columnheader" scope="col">
                                <?php echo klytos_esc_html( __( 'consent.col_duration' ) ); ?>
                            </th>
                            <th role="columnheader" scope="col">
                                <?php echo klytos_esc_html( __( 'consent.col_description' ) ); ?>
                            </th>
                        </tr>
                    </thead>

                    <tbody role="rowgroup">
                        <?php if ( $auditRows === [] ) : ?>
                            <?php /*
                             * §2.1 Empty result: "one row spanning all columns
                             * containing the empty-state sentence and its action
                             * — not a table replaced by a div."
                             */ ?>
                            <tr role="row" class="k-table-row-full">
                                <td role="cell" colspan="4">
                                    <div class="k-empty" data-testid="consent.audit_empty">
                                        <p class="k-empty-text">
                                            <?php echo klytos_esc_html( __( 'consent.audit_empty' ) ); ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $auditRows as $index => $row ) : ?>
                                <tr role="row">
                                    <th role="rowheader"
                                        scope="row"
                                        id="consent-cookie-<?php echo (int) $index; ?>"
                                        class="k-num">
                                        <?php echo klytos_esc_html( $row['name'] ); ?>
                                    </th>
                                    <td role="cell">
                                        <?php /*
                                         * §1.3: colour is never the only channel.
                                         * The category is a WORD; the badge tone
                                         * only repeats what the word already says.
                                         */ ?>
                                        <span class="k-badge <?php echo klytos_esc_attr( $row['tone'] ); ?>">
                                            <?php echo klytos_esc_html( $row['type'] ); ?>
                                        </span>
                                    </td>
                                    <td role="cell" class="k-num">
                                        <?php echo klytos_esc_html( $row['duration'] ); ?>
                                    </td>
                                    <td role="cell">
                                        <?php echo klytos_esc_html( $row['description'] ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="k-card-body">
                <?php /*
                 * §4 allows an <h3> for a group inside a card. The removals are
                 * per-PLUGIN and the table above is per-COOKIE, so they cannot
                 * share a row without either a fifth column the manifest does not
                 * give or one plugin's Remove repeated on each of its cookies.
                 * The `.k-collection` layer (D-089) is the template's own
                 * "collection inside a form" and is reused rather than rebuilt.
                 */ ?>
                <h3 class="k-card-heading" id="consent-declarations-heading">
                    <?php echo klytos_esc_html( __( 'consent.declarations_heading' ) ); ?>
                </h3>

                <?php if ( $declarations === [] ) : ?>
                    <p class="k-hint" data-testid="consent.declarations_empty">
                        <?php echo klytos_esc_html( __( 'consent.declarations_empty' ) ); ?>
                    </p>
                <?php else : ?>
                    <ul class="k-collection"
                        aria-labelledby="consent-declarations-heading"
                        data-testid="consent.declarations">
                        <?php foreach ( $declarations as $declaration ) : ?>
                            <?php
                            $pluginId   = (string) ( $declaration['plugin_id'] ?? '' );
                            $pluginName = (string) ( $declaration['name'] ?? $pluginId );
                            $isArmed    = $armedPlugin !== '' && $armedPlugin === $pluginId;
                            $cookieCount = count( $declaration['cookies'] ?? [] );
                            $scriptCount = count( $declaration['scripts'] ?? [] );
                            ?>
                            <li class="k-collection-row"
                                data-testid="consent.declaration.<?php echo klytos_esc_attr( $pluginId ); ?>">
                                <div class="k-collection-main">
                                    <span class="k-collection-title">
                                        <?php echo klytos_esc_html( $pluginName ); ?>
                                    </span>
                                    <span class="k-collection-meta">
                                        <?php echo klytos_esc_html( __( 'consent.declaration_meta', [
                                            'id'      => $pluginId,
                                            'cookies' => (string) $cookieCount,
                                            'scripts' => (string) $scriptCount,
                                        ] ) ); ?>
                                    </span>
                                </div>

                                <div class="k-collection-actions k-confirm-wrap" aria-live="polite">
                                    <?php if ( $isArmed ) : ?>
                                        <?php /*
                                         * §2's armed label states what the code
                                         * really does. `deletePluginDeclaration()`
                                         * removes the AUDIT ENTRY and nothing
                                         * else: the plugin stays installed, its
                                         * cookies keep being set, and they simply
                                         * stop being declared — which is the
                                         * compliance consequence worth naming.
                                         * The template's example sentence ("34
                                         * records will be deleted") would be false
                                         * here in both halves.
                                         */ ?>
                                        <button type="submit"
                                                form="k-consent-declaration-<?php echo klytos_esc_attr( $pluginId ); ?>"
                                                class="k-btn k-btn--destructive k-btn--sm"
                                                data-testid="consent.confirm_remove.<?php echo klytos_esc_attr( $pluginId ); ?>">
                                            <?php echo klytos_esc_html( __( 'consent.confirm_remove_declaration', [
                                                'plugin' => $pluginName,
                                            ] ) ); ?>
                                        </button>
                                    <?php else : ?>
                                        <button type="submit"
                                                form="k-consent-arm-<?php echo klytos_esc_attr( $pluginId ); ?>"
                                                class="k-btn k-btn--secondary k-btn--sm"
                                                data-testid="consent.remove.<?php echo klytos_esc_attr( $pluginId ); ?>">
                                            <?php echo klytos_esc_html( __( 'consent.remove_declaration' ) ); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <?php /*
             * §1's card footer: "actions, right-aligned". Both exports are
             * shipped behaviour with no other admin surface, so they stay
             * (D-075's standing rule) — moved from the card's header, where the
             * shipped screen put them, into the footer the template provides.
             */ ?>
            <div class="k-card-footer">
                <button type="submit"
                        form="k-consent-export-json"
                        class="k-btn k-btn--secondary k-btn--sm"
                        data-testid="consent.export_json">
                    <?php echo klytos_esc_html( __( 'consent.export_json' ) ); ?>
                </button>
                <button type="submit"
                        form="k-consent-export-csv"
                        class="k-btn k-btn--secondary k-btn--sm"
                        data-testid="consent.export_csv">
                    <?php echo klytos_esc_html( __( 'consent.export_csv' ) ); ?>
                </button>
            </div>
        </section>

        <?php klytos_do_action( 'admin.consent.after_audit' ); ?>

    </div>
</form>

<?php /*
 * The forms every button outside `k-consent-form` submits.
 *
 * A form cannot nest inside a form, and the card stack above IS a form — so the
 * exports and the two-step confirm live here, after it, and their controls
 * associate by `form=`. That is the same mechanism the toolbar Save uses, and
 * the same one entry 39 used for exactly the same reason. None of it needs
 * JavaScript, which is what makes the two-step confirm a real two-step confirm.
 */ ?>
<?php foreach ( $declarations as $declaration ) : ?>
    <?php $pluginId = (string) ( $declaration['plugin_id'] ?? '' ); ?>
    <form method="post" id="k-consent-arm-<?php echo klytos_esc_attr( $pluginId ); ?>" hidden>
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="arm_delete_declaration">
        <input type="hidden" name="plugin_id" value="<?php echo klytos_esc_attr( $pluginId ); ?>">
    </form>
    <form method="post" id="k-consent-declaration-<?php echo klytos_esc_attr( $pluginId ); ?>" hidden>
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="delete_declaration">
        <input type="hidden" name="plugin_id" value="<?php echo klytos_esc_attr( $pluginId ); ?>">
    </form>
<?php endforeach; ?>

<form method="post" id="k-consent-export-json" hidden>
    <?php echo klytos_csrf_field(); ?>
    <input type="hidden" name="action" value="export_json">
</form>
<form method="post" id="k-consent-export-csv" hidden>
    <?php echo klytos_csrf_field(); ?>
    <input type="hidden" name="action" value="export_csv">
</form>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function () {
    'use strict';

    /*
     * §2 Error — form level: "focus moved to it on load". The summary is only
     * in the DOM when there is one.
     */
    var summary = document.getElementById('consent-error-summary');
    if (summary) {
        summary.focus();
    }
})();
</script>

<?php klytos_do_action( 'admin.consent.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
