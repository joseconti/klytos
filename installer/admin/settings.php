<?php

/**
 * Klytos Admin — Settings
 *
 * Manifest entry 9 · template `record-form` · H1 "Settings".
 * Built in Phase 4 Step 4, stage 5 (the form screens), batch B screen 5,
 * against `SPEC/screens/template-record-form.md`, `SPEC/manifest.md` §9 and
 * `SPEC/accessibility.md`.
 *
 * THE WORK HERE IS A RE-PARTITION, NOT A NAV (D-095). The shipped screen had
 * ELEVEN independent POST groups — general, social, analytics, email,
 * languages, appearance, developer, encryption_key_backup, ai, maintenance,
 * notices — each its own <form> with its own Save, all stacked as cards on ONE
 * page load. §9 wants a section nav where "each section is its own page load",
 * and the manifest gives no mapping from the eleven onto its seven names. The
 * mapping below is therefore the BUILD's, taken under D-088 answer 3 and
 * recorded in D-095 before the first line was written:
 *
 * - **Site** — general (name, tagline, description) + social (six links).
 * - **Locale** — languages, plus the default language the shipped screen
 *   posted under `general`.
 * - **Intelligence** — the Gemini key + the analytics id. The custom head/body
 *   scripts posted under `analytics` are NOT intelligence; they are Advanced.
 * - **Email** — transport, sender, SMTP and the test send.
 * - **Advanced** — indexing (see below), scripts, admin appearance, notices,
 *   maintenance, developer.
 *
 * **URLs and Media are omitted**, not forgotten: §9 names seven sections and
 * neither of those two has any shipped setting behind it, so a nav item for
 * them would be a page that renders nothing. D-088 answer 3 covers it and
 * `docs/roadmap.md` carries it; each is one line in $sections to restore.
 *
 * ONE FORM, ONE SAVE. The template says the primary Save "lives in the toolbar,
 * not at the foot of the page, and it is the same button on every form screen"
 * — singular. So a section is one <form> carrying every card it holds, and the
 * eleven per-card Save buttons are gone. The toolbar button associates by
 * `form=`, which is also what makes Enter in a text field save (§4).
 *
 * HEADINGS. §9 answers the H1 question explicitly — "H1 stays 'Settings' and
 * the section is <h2>; the breadcrumb carries the section". That delta moves
 * the whole hierarchy down one step from the template's generic "each card
 * <h2>", so cards here are <h3> and a group inside a card is <h4>. The delta
 * wins over the template's default; logged as an adaptation.
 *
 * THE DR-002 DELTA IS BUILT HERE, and it had been recorded and unbuilt since
 * D-072 answer 1: search-engine and AI-crawler indexing is a checkbox + Save in
 * **Advanced**, gated at `site.configure`, with its consequence stated beside
 * it. `admin/index.php` carried that toggle; this slice removes it there in the
 * same change, so the Dashboard cannot keep a control the manifest has moved.
 *
 * WHAT LEFT THIS SCREEN. The encryption-key card is **entry 6's** (Security →
 * Recovery keys), not this screen's: §9 names no encryption section, §6 names
 * Recovery keys, and two surfaces for one duty is the duplication D-090
 * refused. Moved rather than deleted — it holds the only affordance in the
 * product that actually yields the key material. Its `encryption_key_backed_up`
 * write was silently dropped by `SiteConfig::set()` on every install; that is
 * fixed in this slice under `SiteConfigSetTest`.
 *
 * Authorization is NOT asserted in this file. `settings.php` is mapped to
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

$auth = $app->getAuth();

/**
 * The section nav, in `SPEC/manifest.md` §9's order.
 *
 * URLs (after Site) and Media (after Locale) are the two §9 names that are not
 * here; see the file docblock. The array is the single source for the nav, for
 * the routing below and for the breadcrumb, so a section cannot exist in one
 * and not the others.
 *
 * @var array<string,string> slug => i18n key for its label
 */
$sections = klytos_apply_filters( 'admin.settings.sections', [
    'site'         => 'settings.section_site',
    'locale'       => 'settings.section_locale',
    'intelligence' => 'settings.section_intelligence',
    'email'        => 'settings.section_email',
    'advanced'     => 'settings.section_advanced',
] );

/*
 * A section is its own page load, so the section is a GET parameter. An
 * unknown or absent value resolves to the first section rather than 404ing —
 * `settings.php` with no query string is a real entry point (the sidebar links
 * to it) and must land somewhere.
 */
$section = (string) ( $_GET['section'] ?? '' );
if ( ! isset( $sections[ $section ] ) ) {
    $section = (string) array_key_first( $sections );
}

$success = '';

/** @var array<string,string> Field-level errors, keyed by control name. */
$fieldErrors = [];
/** @var array<int,array{name:string,message:string}> The error summary's rows, in DOM order. */
$summaryRows = [];

$aiGenerator = new \Klytos\Core\AiImageGenerator(
    $app->getStorage(),
    $app->getAssets()
);

/*
 * `site.configure` gates the whole screen, so this is not an access check — it
 * is the DR-002 delta's own gate, kept explicit because §9 states it for the
 * indexing control specifically. Where the gate map ever loosens, the control
 * stays gated at the tier the manifest names.
 */
$canConfigureSite = klytos_has_permission( 'site.configure' );

// ─── Handle POST ────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    /*
     * The posted section is the one that is saved — never $section, which the
     * GET decided. They agree in the browser; they would not agree for a
     * crafted post, and saving a section the request did not carry fields for
     * would blank it.
     */
    $posted = (string) ( $_POST['section'] ?? '' );
    if ( ! isset( $sections[ $posted ] ) ) {
        $posted = '';
    }

    klytos_do_action( 'admin.settings.before_save', $posted, $_POST );

    if ( $posted === 'site' ) {
        $siteName = trim( (string) ( $_POST['site_name'] ?? '' ) );

        if ( $siteName === '' ) {
            $fieldErrors['site_name'] = __( 'settings.error_site_name' );
            $summaryRows[]            = [
                'name'    => 'site_name',
                'message' => __( 'settings.summary_site_name' ),
            ];
        }

        if ( $summaryRows === [] ) {
            $app->getSiteConfig()->set( [
                'site_name'   => $siteName,
                'tagline'     => trim( (string) ( $_POST['tagline'] ?? '' ) ),
                'description' => trim( (string) ( $_POST['description'] ?? '' ) ),
                'social'      => [
                    'twitter'   => trim( (string) ( $_POST['twitter'] ?? '' ) ),
                    'github'    => trim( (string) ( $_POST['github'] ?? '' ) ),
                    'linkedin'  => trim( (string) ( $_POST['linkedin'] ?? '' ) ),
                    'instagram' => trim( (string) ( $_POST['instagram'] ?? '' ) ),
                    'youtube'   => trim( (string) ( $_POST['youtube'] ?? '' ) ),
                    'mastodon'  => trim( (string) ( $_POST['mastodon'] ?? '' ) ),
                ],
            ] );
            $success = __( 'settings.saved' );
        }
    } elseif ( $posted === 'locale' ) {
        /*
         * Rows whose code and name are both empty are the empty-row affordance
         * and are dropped silently. A row with one half filled is a mistake the
         * person made and is reported, rather than discarded quietly — the
         * shipped screen dropped both cases without a word.
         */
        $languages = [];
        $codes     = (array) ( $_POST['lang_code'] ?? [] );
        $names     = (array) ( $_POST['lang_name'] ?? [] );

        foreach ( $codes as $i => $rawCode ) {
            $code = trim( (string) $rawCode );
            $name = trim( (string) ( $names[ $i ] ?? '' ) );

            if ( $code === '' && $name === '' ) {
                continue;
            }

            if ( $code === '' || $name === '' ) {
                $fieldErrors[ 'lang_code_' . (int) $i ] = __( 'settings.error_language_row' );
                $summaryRows[]                          = [
                    'name'    => 'lang_code_' . (int) $i,
                    'message' => __( 'settings.summary_language_row' ),
                ];
                continue;
            }

            $languages[] = ['code' => $code, 'name' => $name];
        }

        if ( $summaryRows === [] ) {
            $app->getSiteConfig()->set( [
                'languages'        => $languages,
                'default_language' => (string) ( $_POST['default_language'] ?? 'en' ),
            ] );
            $success = __( 'settings.saved' );
        }
    } elseif ( $posted === 'intelligence' ) {
        $aiGenerator->setApiKey( trim( (string) ( $_POST['gemini_api_key'] ?? '' ) ) );
        $app->getSiteConfig()->set( [
            'analytics' => [
                'google_analytics_id' => trim( (string) ( $_POST['google_analytics_id'] ?? '' ) ),
            ],
        ] );
        $success = __( 'settings.saved' );
    } elseif ( $posted === 'email' ) {
        $fromEmail = trim( (string) ( $_POST['email_from_email'] ?? '' ) );
        $replyTo   = trim( (string) ( $_POST['email_reply_to'] ?? '' ) );
        $smtpPort  = trim( (string) ( $_POST['smtp_port'] ?? '' ) );

        if ( $fromEmail !== '' && ! filter_var( $fromEmail, FILTER_VALIDATE_EMAIL ) ) {
            $fieldErrors['email_from_email'] = __( 'settings.error_from_email' );
            $summaryRows[]                   = [
                'name'    => 'email_from_email',
                'message' => __( 'settings.summary_from_email' ),
            ];
        }

        if ( $replyTo !== '' && ! filter_var( $replyTo, FILTER_VALIDATE_EMAIL ) ) {
            $fieldErrors['email_reply_to'] = __( 'settings.error_reply_to' );
            $summaryRows[]                 = [
                'name'    => 'email_reply_to',
                'message' => __( 'settings.summary_reply_to' ),
            ];
        }

        // A port is 1–65535. The shipped screen cast to int, so "abc" became 0
        // and was stored as a port nothing can connect to.
        $portValid = $smtpPort !== ''
            && ctype_digit( $smtpPort )
            && (int) $smtpPort >= 1
            && (int) $smtpPort <= 65535;

        if ( ! $portValid ) {
            $fieldErrors['smtp_port'] = __( 'settings.error_smtp_port' );
            $summaryRows[]            = [
                'name'    => 'smtp_port',
                'message' => __( 'settings.summary_smtp_port' ),
            ];
        }

        if ( $summaryRows === [] ) {
            $transport = (string) ( $_POST['email_transport'] ?? 'mail' );
            $security  = (string) ( $_POST['smtp_security'] ?? 'tls' );

            $app->getSiteConfig()->set( [
                'email' => [
                    'transport'     => in_array( $transport, ['mail', 'smtp'], true ) ? $transport : 'mail',
                    'from_name'     => trim( (string) ( $_POST['email_from_name'] ?? '' ) ),
                    'from_email'    => $fromEmail,
                    'reply_to'      => $replyTo,
                    'smtp_host'     => trim( (string) ( $_POST['smtp_host'] ?? '' ) ),
                    'smtp_port'     => (int) $smtpPort,
                    'smtp_user'     => trim( (string) ( $_POST['smtp_user'] ?? '' ) ),
                    'smtp_pass'     => (string) ( $_POST['smtp_pass'] ?? '' ),
                    'smtp_security' => in_array( $security, ['tls', 'ssl', ''], true ) ? $security : 'tls',
                ],
            ] );

            if ( isset( $_POST['test_email'] ) ) {
                $adminEmail = (string) ( $app->getConfig()['admin_email'] ?? '' );
                if ( $adminEmail !== '' && $app->getMailer()->sendTest( $adminEmail ) ) {
                    $success = __( 'settings.email_test_sent', ['email' => $adminEmail] );
                } else {
                    $success = __( 'settings.email_test_failed' );
                }
            } else {
                $success = __( 'settings.saved' );
            }
        }
    } elseif ( $posted === 'advanced' ) {
        $threshold = trim( (string) ( $_POST['devbar_log_slow_threshold'] ?? '' ) );

        // Only validated where the control was actually rendered; a section
        // that never drew the field must not fail on it.
        if ( $canConfigureSite && $threshold !== '' && ( ! ctype_digit( $threshold ) || (int) $threshold < 10 ) ) {
            $fieldErrors['devbar_log_slow_threshold'] = __( 'settings.error_slow_threshold' );
            $summaryRows[]                            = [
                'name'    => 'devbar_log_slow_threshold',
                'message' => __( 'settings.summary_slow_threshold' ),
            ];
        }

        if ( $summaryRows === [] ) {
            $adminTheme = (string) ( $_POST['admin_theme'] ?? 'dark' );
            if ( ! in_array( $adminTheme, ['light', 'dark'], true ) ) {
                $adminTheme = 'dark';
            }

            $maintenanceOn = isset( $_POST['maintenance_mode'] );

            $update = [
                'admin_theme'         => $adminTheme,
                'admin_bar_enabled'   => isset( $_POST['admin_bar_enabled'] ),
                'maintenance_mode'    => $maintenanceOn,
                'maintenance_message' => trim( (string) ( $_POST['maintenance_message'] ?? '' ) ),
                'analytics'           => [
                    'custom_head_scripts' => (string) ( $_POST['custom_head_scripts'] ?? '' ),
                    'custom_body_scripts' => (string) ( $_POST['custom_body_scripts'] ?? '' ),
                ],
                'notices'             => [
                    'show_ads' => isset( $_POST['show_ads'] ),
                ],
            ];

            /*
             * DR-002: the indexing control is gated at `site.configure`, and a
             * checkbox that was never rendered must not be read as "unchecked"
             * — that would silently block a site's indexing whenever a role
             * without the permission saved this section.
             */
            if ( $canConfigureSite ) {
                $update['indexing_enabled'] = isset( $_POST['indexing_enabled'] );
                $update['developer']        = [
                    'developer_mode'            => isset( $_POST['developer_mode'] ),
                    'devbar_show_performance'   => isset( $_POST['devbar_show_performance'] ),
                    'devbar_show_queries'       => isset( $_POST['devbar_show_queries'] ),
                    'devbar_show_hooks'         => isset( $_POST['devbar_show_hooks'] ),
                    'devbar_show_assets'        => isset( $_POST['devbar_show_assets'] ),
                    'devbar_show_request'       => isset( $_POST['devbar_show_request'] ),
                    'devbar_show_environment'   => isset( $_POST['devbar_show_environment'] ),
                    'devbar_log_slow_threshold' => max( 10, (int) ( $threshold !== '' ? $threshold : 200 ) ),
                ];
            }

            $app->getSiteConfig()->set( $update );

            if ( $maintenanceOn ) {
                klytos_do_action( 'maintenance.enabled' );
            } else {
                klytos_do_action( 'maintenance.disabled' );
            }

            if ( isset( $_POST['dismiss_all'] ) ) {
                $noticeManager = $app->getNoticeManager();
                foreach ( $noticeManager->list() as $notice ) {
                    if ( ! empty( $notice['dismissible'] ) ) {
                        $noticeManager->dismiss( (string) $notice['id'] );
                    }
                }
            }

            $success = __( 'settings.saved' );
        }
    }

    klytos_do_action( 'admin.settings.after_save', $posted, $_POST );
}

// ─── Read back AFTER the save, so the form shows what is stored ──
$siteConfig  = $app->getSiteConfig()->get();
$devConfig   = $siteConfig['developer'] ?? [];
$aiApiKey    = $aiGenerator->getApiKey();
$sectionKey  = $sections[ $section ];

/*
 * §9: "H1 stays 'Settings' and the section is <h2>; the breadcrumb carries the
 * section." The shell builds the last breadcrumb crumb from $pageTitle and the
 * H1 from the same value, so the two are separated here: $pageTitle becomes the
 * SECTION (last crumb, and the document title), "Settings" becomes the middle
 * crumb, and the screen prints its own H1.
 */
$pageTitle       = __( $sectionKey );
$breadcrumb      = [
    [
        'label' => __( 'settings.title' ),
        'url'   => 'settings.php',
    ],
];
$pageEmitsOwnH1 = true;

/*
 * template-record-form.md §1: "The primary Save lives in the TOOLBAR, not at
 * the foot of the page, and it is the same button on every form screen." The
 * toolbar is emitted by the shell, outside <main> and outside the <form>, so
 * the button associates by `form=` — which is also what makes Enter in a text
 * field save (§4). No JavaScript is involved in either half.
 */
klytos_add_filter( 'admin.topbar_actions', static function ( string $html ): string {
    return $html . '<button type="submit" form="k-settings-form"'
        . ' class="k-btn k-btn--primary k-btn--sm"'
        . ' data-testid="settings.save">'
        . klytos_esc_html( __( 'common.save' ) )
        . '</button>';
} );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.settings.before', $section ); ?>

<h1><?php echo klytos_esc_html( __( 'settings.title' ) ); ?></h1>

<?php if ( $success !== '' ) : ?>
    <?php // §2 Success: "the page reloads with a role="status" line under the H1". ?>
    <p class="k-status-line" role="status" data-testid="settings.status_line">
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
         id="settings-error-summary"
         role="alert"
         tabindex="-1"
         data-testid="settings.error_summary">
        <h2><?php echo klytos_esc_html( __( 'settings.summary_title' ) ); ?></h2>
        <ul>
            <?php foreach ( $summaryRows as $index => $row ) : ?>
                <li>
                    <a href="#settings-field-<?php echo klytos_esc_attr( $row['name'] ); ?>"
                       data-testid="settings.error_link.<?php echo (int) $index; ?>">
                        <?php echo klytos_esc_html( $row['message'] ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="k-record-form" data-testid="settings.screen" data-section="<?php echo klytos_esc_attr( $section ); ?>">

    <?php // §4: section nav is <nav aria-label>; the current section is aria-current="page". ?>
    <nav class="k-section-nav"
         aria-label="<?php echo klytos_esc_attr( __( 'settings.sections_label' ) ); ?>"
         data-testid="settings.section_nav">
        <?php foreach ( $sections as $slug => $labelKey ) : ?>
            <a class="k-section-nav-item"
               href="settings.php?section=<?php echo klytos_esc_attr( $slug ); ?>"
               <?php echo $slug === $section ? 'aria-current="page"' : ''; ?>
               data-testid="settings.section.<?php echo klytos_esc_attr( $slug ); ?>">
                <?php echo klytos_esc_html( __( $labelKey ) ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="post" id="k-settings-form" class="k-card-stack">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="section" value="<?php echo klytos_esc_attr( $section ); ?>">

        <?php // §9's delta: the SECTION is the <h2>, so the cards below are <h3>. ?>
        <h2 class="k-section-heading" data-testid="settings.section_heading">
            <?php echo klytos_esc_html( __( $sectionKey ) ); ?>
        </h2>

        <?php klytos_do_action( 'admin.settings.before_section', $section, $siteConfig ); ?>

        <?php if ( $section === 'site' ) : ?>
            <?php // ─── Card — Site details ──────────────────────── ?>
            <section class="k-card k-card--padded" aria-labelledby="settings-site-details-heading">
                <div class="k-card-body">
                    <h3 class="k-card-heading" id="settings-site-details-heading">
                        <?php echo klytos_esc_html( __( 'settings.card_site_details' ) ); ?>
                    </h3>

                    <div class="k-field">
                        <label class="k-label" for="settings-field-site_name">
                            <?php echo klytos_esc_html( __( 'settings.site_name' ) ); ?>
                        </label>
                        <p class="k-hint" id="settings-hint-site_name">
                            <?php echo klytos_esc_html( __( 'settings.site_name_hint' ) ); ?>
                        </p>
                        <input type="text"
                               class="k-control"
                               id="settings-field-site_name"
                               name="site_name"
                               required
                               autocomplete="organization"
                               value="<?php echo klytos_esc_attr( (string) ( $siteConfig['site_name'] ?? '' ) ); ?>"
                               aria-describedby="settings-hint-site_name<?php echo isset( $fieldErrors['site_name'] ) ? ' settings-error-site_name' : ''; ?>"
                               <?php echo isset( $fieldErrors['site_name'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="settings.site_name">
                        <?php if ( isset( $fieldErrors['site_name'] ) ) : ?>
                            <p class="k-error" id="settings-error-site_name">
                                <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                <?php echo klytos_esc_html( $fieldErrors['site_name'] ); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="settings-field-tagline">
                            <?php echo klytos_esc_html( __( 'settings.tagline' ) ); ?>
                        </label>
                        <p class="k-hint" id="settings-hint-tagline">
                            <?php echo klytos_esc_html( __( 'settings.tagline_hint' ) ); ?>
                        </p>
                        <input type="text"
                               class="k-control"
                               id="settings-field-tagline"
                               name="tagline"
                               value="<?php echo klytos_esc_attr( (string) ( $siteConfig['tagline'] ?? '' ) ); ?>"
                               aria-describedby="settings-hint-tagline"
                               data-testid="settings.tagline">
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="settings-field-description">
                            <?php echo klytos_esc_html( __( 'settings.site_description' ) ); ?>
                        </label>
                        <p class="k-hint" id="settings-hint-description">
                            <?php echo klytos_esc_html( __( 'settings.site_description_hint' ) ); ?>
                        </p>
                        <textarea class="k-control"
                                  id="settings-field-description"
                                  name="description"
                                  rows="3"
                                  aria-describedby="settings-hint-description"
                                  data-testid="settings.description"><?php
                                    echo klytos_esc_textarea( (string) ( $siteConfig['description'] ?? '' ) );
                                    ?></textarea>
                    </div>
                </div>
            </section>

            <?php // ─── Card — Social links ──────────────────────── ?>
            <section class="k-card k-card--padded" aria-labelledby="settings-social-heading">
                <div class="k-card-body">
                    <h3 class="k-card-heading" id="settings-social-heading">
                        <?php echo klytos_esc_html( __( 'settings.card_social' ) ); ?>
                    </h3>

                    <p class="k-hint" id="settings-hint-social">
                        <?php echo klytos_esc_html( __( 'settings.social_hint' ) ); ?>
                    </p>

                    <div class="k-field-grid">
                        <?php
                        $socialNetworks = [
                            'twitter'   => 'settings.social_twitter',
                            'github'    => 'settings.social_github',
                            'linkedin'  => 'settings.social_linkedin',
                            'instagram' => 'settings.social_instagram',
                            'youtube'   => 'settings.social_youtube',
                            'mastodon'  => 'settings.social_mastodon',
                        ];
                        foreach ( $socialNetworks as $networkKey => $networkLabel ) :
                            ?>
                            <div class="k-field">
                                <label class="k-label" for="settings-field-<?php echo klytos_esc_attr( $networkKey ); ?>">
                                    <?php echo klytos_esc_html( __( $networkLabel ) ); ?>
                                </label>
                                <input type="url"
                                       class="k-control"
                                       id="settings-field-<?php echo klytos_esc_attr( $networkKey ); ?>"
                                       name="<?php echo klytos_esc_attr( $networkKey ); ?>"
                                       autocomplete="url"
                                       spellcheck="false"
                                       value="<?php echo klytos_esc_attr( (string) ( $siteConfig['social'][ $networkKey ] ?? '' ) ); ?>"
                                       aria-describedby="settings-hint-social"
                                       data-testid="settings.social.<?php echo klytos_esc_attr( $networkKey ); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

        <?php elseif ( $section === 'locale' ) : ?>
            <?php // ─── Card — Default language ──────────────────── ?>
            <section class="k-card k-card--padded" aria-labelledby="settings-default-language-heading">
                <div class="k-card-body">
                    <h3 class="k-card-heading" id="settings-default-language-heading">
                        <?php echo klytos_esc_html( __( 'settings.card_default_language' ) ); ?>
                    </h3>

                    <div class="k-field">
                        <label class="k-label" for="settings-field-default_language">
                            <?php echo klytos_esc_html( __( 'settings.default_language' ) ); ?>
                        </label>
                        <p class="k-hint" id="settings-hint-default_language">
                            <?php echo klytos_esc_html( __( 'settings.default_language_hint' ) ); ?>
                        </p>
                        <?php
                        /*
                         * The options are the site's OWN language list, not a
                         * hard-coded four. The shipped screen offered exactly
                         * es/en/ca/fr regardless of what the Site languages
                         * card below had been filled with, so a site whose
                         * languages were de and it could not select either.
                         */
                        $configuredLanguages = $siteConfig['languages'] ?? [];
                        $currentDefault      = (string) ( $siteConfig['default_language'] ?? 'en' );
                        $defaultOptions      = [];
                        foreach ( $configuredLanguages as $lang ) {
                            $code = trim( (string) ( $lang['code'] ?? '' ) );
                            if ( $code !== '' ) {
                                $defaultOptions[ $code ] = trim( (string) ( $lang['name'] ?? '' ) ) ?: $code;
                            }
                        }
                        if ( $defaultOptions === [] ) {
                            $defaultOptions = ['en' => 'English'];
                        }
                        if ( ! isset( $defaultOptions[ $currentDefault ] ) && $currentDefault !== '' ) {
                            // Never hide the stored value: a select that cannot
                            // show what is saved silently rewrites it on Save.
                            $defaultOptions[ $currentDefault ] = $currentDefault;
                        }
                        ?>
                        <select class="k-control"
                                id="settings-field-default_language"
                                name="default_language"
                                aria-describedby="settings-hint-default_language"
                                data-testid="settings.default_language">
                            <?php foreach ( $defaultOptions as $code => $label ) : ?>
                                <option value="<?php echo klytos_esc_attr( (string) $code ); ?>"
                                    <?php echo (string) $code === $currentDefault ? 'selected' : ''; ?>>
                                    <?php echo klytos_esc_html( (string) $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <?php // ─── Card — Site languages ────────────────────── ?>
            <section class="k-card k-card--padded" aria-labelledby="settings-languages-heading">
                <div class="k-card-body">
                    <h3 class="k-card-heading" id="settings-languages-heading">
                        <?php echo klytos_esc_html( __( 'settings.card_languages' ) ); ?>
                    </h3>

                    <p class="k-hint" id="settings-hint-languages">
                        <?php echo klytos_esc_html( __( 'settings.languages_hint' ) ); ?>
                    </p>

                    <div id="settings-languages-list">
                        <?php
                        $languageRows = $siteConfig['languages'] ?? [];
                        if ( $languageRows === [] ) {
                            /*
                             * §2 Empty: "a collection inside a form can be
                             * empty… it renders one row: the sentence and the
                             * add action, inside the card, keeping the card's
                             * heading." One blank row IS the add affordance
                             * here, and the sentence sits above it.
                             */
                            $languageRows = [['code' => '', 'name' => '']];
                        }
                        foreach ( $languageRows as $i => $lang ) :
                            $rowError = $fieldErrors[ 'lang_code_' . (int) $i ] ?? null;
                            ?>
                            <div class="k-field-grid k-field-grid--pair" data-testid="settings.language_row.<?php echo (int) $i; ?>">
                                <div class="k-field">
                                    <label class="k-label" for="settings-field-lang_code_<?php echo (int) $i; ?>">
                                        <?php echo klytos_esc_html( __( 'settings.language_code' ) ); ?>
                                    </label>
                                    <input type="text"
                                           class="k-control k-control--mono"
                                           id="settings-field-lang_code_<?php echo (int) $i; ?>"
                                           name="lang_code[]"
                                           spellcheck="false"
                                           autocapitalize="off"
                                           value="<?php echo klytos_esc_attr( (string) ( $lang['code'] ?? '' ) ); ?>"
                                           aria-describedby="settings-hint-languages<?php echo $rowError !== null ? ' settings-error-lang_code_' . (int) $i : ''; ?>"
                                           <?php echo $rowError !== null ? 'aria-invalid="true"' : ''; ?>
                                           data-testid="settings.language_code.<?php echo (int) $i; ?>">
                                </div>
                                <div class="k-field">
                                    <label class="k-label" for="settings-field-lang_name_<?php echo (int) $i; ?>">
                                        <?php echo klytos_esc_html( __( 'settings.language_name' ) ); ?>
                                    </label>
                                    <input type="text"
                                           class="k-control"
                                           id="settings-field-lang_name_<?php echo (int) $i; ?>"
                                           name="lang_name[]"
                                           value="<?php echo klytos_esc_attr( (string) ( $lang['name'] ?? '' ) ); ?>"
                                           aria-describedby="settings-hint-languages"
                                           data-testid="settings.language_name.<?php echo (int) $i; ?>">
                                </div>
                                <?php if ( $rowError !== null ) : ?>
                                    <p class="k-error" id="settings-error-lang_code_<?php echo (int) $i; ?>">
                                        <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                        <?php echo klytos_esc_html( $rowError ); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="k-card-footer">
                        <?php /*
                         * A no-JS fallback is not needed: with JavaScript off
                         * the person edits the rows that are rendered and saves,
                         * and a saved row produces the next blank row on the
                         * reload. The button only saves that round trip.
                         */ ?>
                        <button type="button"
                                class="k-btn k-btn--secondary k-btn--sm"
                                id="settings-add-language"
                                data-testid="settings.add_language">
                            <?php echo klytos_esc_html( __( 'settings.add_language' ) ); ?>
                        </button>
                    </div>
                </div>
            </section>

        <?php elseif ( $section === 'intelligence' ) : ?>
            <?php // ─── Card — AI provider ───────────────────────── ?>
            <section class="k-card k-card--padded k-card--secret" aria-labelledby="settings-ai-heading">
                <div class="k-card-body">
                    <h3 class="k-card-heading" id="settings-ai-heading">
                        <?php echo klytos_esc_html( __( 'settings.card_ai' ) ); ?>
                    </h3>

                    <div class="k-field">
                        <label class="k-label" for="settings-field-gemini_api_key">
                            <?php echo klytos_esc_html( __( 'settings.gemini_api_key' ) ); ?>
                        </label>
                        <p class="k-hint" id="settings-hint-gemini_api_key">
                            <?php echo klytos_esc_html( __( 'settings.gemini_api_key_hint' ) ); ?>
                        </p>
                        <input type="password"
                               class="k-control k-control--mono"
                               id="settings-field-gemini_api_key"
                               name="gemini_api_key"
                               autocomplete="off"
                               spellcheck="false"
                               autocapitalize="off"
                               value="<?php echo klytos_esc_attr( (string) $aiApiKey ); ?>"
                               aria-describedby="settings-hint-gemini_api_key"
                               data-testid="settings.gemini_api_key">
                    </div>
                </div>
            </section>

            <?php // ─── Card — Analytics ─────────────────────────── ?>
            <section class="k-card k-card--padded" aria-labelledby="settings-analytics-heading">
                <div class="k-card-body">
                    <h3 class="k-card-heading" id="settings-analytics-heading">
                        <?php echo klytos_esc_html( __( 'settings.analytics' ) ); ?>
                    </h3>

                    <div class="k-field">
                        <label class="k-label" for="settings-field-google_analytics_id">
                            <?php echo klytos_esc_html( __( 'settings.google_analytics_id' ) ); ?>
                        </label>
                        <p class="k-hint" id="settings-hint-google_analytics_id">
                            <?php echo klytos_esc_html( __( 'settings.google_analytics_id_hint' ) ); ?>
                        </p>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="settings-field-google_analytics_id"
                               name="google_analytics_id"
                               spellcheck="false"
                               autocapitalize="off"
                               value="<?php echo klytos_esc_attr( (string) ( $siteConfig['analytics']['google_analytics_id'] ?? '' ) ); ?>"
                               aria-describedby="settings-hint-google_analytics_id"
                               data-testid="settings.google_analytics_id">
                    </div>
                </div>
            </section>

        <?php elseif ( $section === 'email' ) : ?>
            <?php // ─── Card — Sender ────────────────────────────── ?>
            <section class="k-card k-card--padded" aria-labelledby="settings-sender-heading">
                <div class="k-card-body">
                    <h3 class="k-card-heading" id="settings-sender-heading">
                        <?php echo klytos_esc_html( __( 'settings.card_sender' ) ); ?>
                    </h3>

                    <div class="k-field">
                        <label class="k-label" for="settings-field-email_transport">
                            <?php echo klytos_esc_html( __( 'settings.email_transport' ) ); ?>
                        </label>
                        <p class="k-hint" id="settings-hint-email_transport">
                            <?php echo klytos_esc_html( __( 'settings.email_transport_hint' ) ); ?>
                        </p>
                        <select class="k-control"
                                id="settings-field-email_transport"
                                name="email_transport"
                                aria-describedby="settings-hint-email_transport"
                                data-testid="settings.email_transport">
                            <?php $transport = (string) ( $siteConfig['email']['transport'] ?? 'mail' ); ?>
                            <option value="mail" <?php echo $transport === 'mail' ? 'selected' : ''; ?>>
                                <?php echo klytos_esc_html( __( 'settings.email_transport_mail' ) ); ?>
                            </option>
                            <option value="smtp" <?php echo $transport === 'smtp' ? 'selected' : ''; ?>>
                                <?php echo klytos_esc_html( __( 'settings.email_transport_smtp' ) ); ?>
                            </option>
                        </select>
                    </div>

                    <div class="k-field-grid k-field-grid--pair">
                        <div class="k-field">
                            <label class="k-label" for="settings-field-email_from_name">
                                <?php echo klytos_esc_html( __( 'settings.email_from_name' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control"
                                   id="settings-field-email_from_name"
                                   name="email_from_name"
                                   autocomplete="organization"
                                   value="<?php echo klytos_esc_attr( (string) ( $siteConfig['email']['from_name'] ?? '' ) ); ?>"
                                   data-testid="settings.email_from_name">
                        </div>
                        <div class="k-field">
                            <label class="k-label" for="settings-field-email_from_email">
                                <?php echo klytos_esc_html( __( 'settings.email_from_email' ) ); ?>
                            </label>
                            <input type="email"
                                   class="k-control"
                                   id="settings-field-email_from_email"
                                   name="email_from_email"
                                   autocomplete="email"
                                   spellcheck="false"
                                   value="<?php echo klytos_esc_attr( (string) ( $siteConfig['email']['from_email'] ?? '' ) ); ?>"
                                   <?php echo isset( $fieldErrors['email_from_email'] ) ? 'aria-invalid="true" aria-describedby="settings-error-email_from_email"' : ''; ?>
                                   data-testid="settings.email_from_email">
                            <?php if ( isset( $fieldErrors['email_from_email'] ) ) : ?>
                                <p class="k-error" id="settings-error-email_from_email">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors['email_from_email'] ); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="settings-field-email_reply_to">
                            <?php echo klytos_esc_html( __( 'settings.email_reply_to' ) ); ?>
                        </label>
                        <p class="k-hint" id="settings-hint-email_reply_to">
                            <?php echo klytos_esc_html( __( 'settings.email_reply_to_hint' ) ); ?>
                        </p>
                        <input type="email"
                               class="k-control"
                               id="settings-field-email_reply_to"
                               name="email_reply_to"
                               autocomplete="email"
                               spellcheck="false"
                               value="<?php echo klytos_esc_attr( (string) ( $siteConfig['email']['reply_to'] ?? '' ) ); ?>"
                               aria-describedby="settings-hint-email_reply_to<?php echo isset( $fieldErrors['email_reply_to'] ) ? ' settings-error-email_reply_to' : ''; ?>"
                               <?php echo isset( $fieldErrors['email_reply_to'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="settings.email_reply_to">
                        <?php if ( isset( $fieldErrors['email_reply_to'] ) ) : ?>
                            <p class="k-error" id="settings-error-email_reply_to">
                                <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                <?php echo klytos_esc_html( $fieldErrors['email_reply_to'] ); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="k-card-footer">
                    <button type="submit"
                            name="test_email"
                            value="1"
                            class="k-btn k-btn--secondary k-btn--sm"
                            data-testid="settings.email_test">
                        <?php echo klytos_esc_html( __( 'settings.email_test' ) ); ?>
                    </button>
                </div>
            </section>

            <?php // ─── Card — SMTP server ───────────────────────── ?>
            <section class="k-card k-card--padded" aria-labelledby="settings-smtp-heading">
                <div class="k-card-body">
                    <h3 class="k-card-heading" id="settings-smtp-heading">
                        <?php echo klytos_esc_html( __( 'settings.card_smtp' ) ); ?>
                    </h3>

                    <p class="k-hint" id="settings-hint-smtp">
                        <?php echo klytos_esc_html( __( 'settings.smtp_hint' ) ); ?>
                    </p>

                    <div class="k-field-grid k-field-grid--pair">
                        <div class="k-field">
                            <label class="k-label" for="settings-field-smtp_host">
                                <?php echo klytos_esc_html( __( 'settings.smtp_host' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control k-control--mono"
                                   id="settings-field-smtp_host"
                                   name="smtp_host"
                                   spellcheck="false"
                                   autocapitalize="off"
                                   value="<?php echo klytos_esc_attr( (string) ( $siteConfig['email']['smtp_host'] ?? '' ) ); ?>"
                                   aria-describedby="settings-hint-smtp"
                                   data-testid="settings.smtp_host">
                        </div>
                        <div class="k-field">
                            <label class="k-label" for="settings-field-smtp_port">
                                <?php echo klytos_esc_html( __( 'settings.smtp_port' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control k-control--mono"
                                   id="settings-field-smtp_port"
                                   name="smtp_port"
                                   inputmode="numeric"
                                   spellcheck="false"
                                   value="<?php echo klytos_esc_attr( (string) ( $siteConfig['email']['smtp_port'] ?? 587 ) ); ?>"
                                   aria-describedby="settings-hint-smtp<?php echo isset( $fieldErrors['smtp_port'] ) ? ' settings-error-smtp_port' : ''; ?>"
                                   <?php echo isset( $fieldErrors['smtp_port'] ) ? 'aria-invalid="true"' : ''; ?>
                                   data-testid="settings.smtp_port">
                            <?php if ( isset( $fieldErrors['smtp_port'] ) ) : ?>
                                <p class="k-error" id="settings-error-smtp_port">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors['smtp_port'] ); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="k-field-grid k-field-grid--pair">
                        <div class="k-field">
                            <label class="k-label" for="settings-field-smtp_user">
                                <?php echo klytos_esc_html( __( 'settings.smtp_user' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control"
                                   id="settings-field-smtp_user"
                                   name="smtp_user"
                                   autocomplete="username"
                                   spellcheck="false"
                                   value="<?php echo klytos_esc_attr( (string) ( $siteConfig['email']['smtp_user'] ?? '' ) ); ?>"
                                   data-testid="settings.smtp_user">
                        </div>
                        <div class="k-field">
                            <label class="k-label" for="settings-field-smtp_pass">
                                <?php echo klytos_esc_html( __( 'settings.smtp_pass' ) ); ?>
                            </label>
                            <input type="password"
                                   class="k-control"
                                   id="settings-field-smtp_pass"
                                   name="smtp_pass"
                                   autocomplete="current-password"
                                   value="<?php echo klytos_esc_attr( (string) ( $siteConfig['email']['smtp_pass'] ?? '' ) ); ?>"
                                   data-testid="settings.smtp_pass">
                        </div>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="settings-field-smtp_security">
                            <?php echo klytos_esc_html( __( 'settings.smtp_security' ) ); ?>
                        </label>
                        <?php $smtpSecurity = (string) ( $siteConfig['email']['smtp_security'] ?? 'tls' ); ?>
                        <select class="k-control"
                                id="settings-field-smtp_security"
                                name="smtp_security"
                                data-testid="settings.smtp_security">
                            <option value="tls" <?php echo $smtpSecurity === 'tls' ? 'selected' : ''; ?>>STARTTLS (587)</option>
                            <option value="ssl" <?php echo $smtpSecurity === 'ssl' ? 'selected' : ''; ?>>SSL/TLS (465)</option>
                            <option value="" <?php echo $smtpSecurity === '' ? 'selected' : ''; ?>>
                                <?php echo klytos_esc_html( __( 'settings.smtp_none' ) ); ?>
                            </option>
                        </select>
                    </div>
                </div>
            </section>

        <?php elseif ( $section === 'advanced' ) : ?>
            <?php if ( $canConfigureSite ) : ?>
                <?php // ─── Card — Search engines and AI crawlers (DR-002) ─── ?>
                <section class="k-card k-card--padded" aria-labelledby="settings-indexing-heading">
                    <div class="k-card-body">
                        <h3 class="k-card-heading" id="settings-indexing-heading">
                            <?php echo klytos_esc_html( __( 'settings.card_indexing' ) ); ?>
                        </h3>

                        <div class="k-field">
                            <label class="k-choice k-hit-24" for="settings-field-indexing_enabled">
                                <input type="checkbox"
                                       class="k-check"
                                       id="settings-field-indexing_enabled"
                                       name="indexing_enabled"
                                       value="1"
                                       <?php echo ! empty( $siteConfig['indexing_enabled'] ) ? 'checked' : ''; ?>
                                       aria-describedby="settings-hint-indexing"
                                       data-testid="settings.indexing_enabled">
                                <?php echo klytos_esc_html( __( 'settings.indexing_label' ) ); ?>
                            </label>
                            <?php /*
                             * §9's DR-002 delta: "a checkbox + Save with the
                             * consequence stated NEXT TO IT". The consequence
                             * is stated for BOTH positions, because a person
                             * reading it has to know what the box does whether
                             * it is on or off — one sentence covering only the
                             * unchecked case is half the control.
                             */ ?>
                            <p class="k-hint" id="settings-hint-indexing">
                                <?php echo klytos_esc_html( __( 'settings.indexing_consequence' ) ); ?>
                            </p>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php // ─── Card — Custom scripts ────────────────────── ?>
            <section class="k-card k-card--padded" aria-labelledby="settings-scripts-heading">
                <div class="k-card-body">
                    <h3 class="k-card-heading" id="settings-scripts-heading">
                        <?php echo klytos_esc_html( __( 'settings.card_scripts' ) ); ?>
                    </h3>

                    <div class="k-field">
                        <label class="k-label" for="settings-field-custom_head_scripts">
                            <?php echo klytos_esc_html( __( 'settings.custom_head_scripts' ) ); ?>
                        </label>
                        <p class="k-hint" id="settings-hint-custom_head_scripts">
                            <?php echo klytos_esc_html( __( 'settings.custom_head_scripts_hint' ) ); ?>
                        </p>
                        <textarea class="k-control k-control--mono"
                                  id="settings-field-custom_head_scripts"
                                  name="custom_head_scripts"
                                  rows="4"
                                  spellcheck="false"
                                  autocapitalize="off"
                                  aria-describedby="settings-hint-custom_head_scripts"
                                  data-testid="settings.custom_head_scripts"><?php
                                    echo klytos_esc_textarea( (string) ( $siteConfig['analytics']['custom_head_scripts'] ?? '' ) );
                                    ?></textarea>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="settings-field-custom_body_scripts">
                            <?php echo klytos_esc_html( __( 'settings.custom_body_scripts' ) ); ?>
                        </label>
                        <p class="k-hint" id="settings-hint-custom_body_scripts">
                            <?php echo klytos_esc_html( __( 'settings.custom_body_scripts_hint' ) ); ?>
                        </p>
                        <textarea class="k-control k-control--mono"
                                  id="settings-field-custom_body_scripts"
                                  name="custom_body_scripts"
                                  rows="4"
                                  spellcheck="false"
                                  autocapitalize="off"
                                  aria-describedby="settings-hint-custom_body_scripts"
                                  data-testid="settings.custom_body_scripts"><?php
                                    echo klytos_esc_textarea( (string) ( $siteConfig['analytics']['custom_body_scripts'] ?? '' ) );
                                    ?></textarea>
                    </div>
                </div>
            </section>

            <?php // ─── Card — Admin appearance ──────────────────── ?>
            <section class="k-card k-card--padded" aria-labelledby="settings-appearance-heading">
                <div class="k-card-body">
                    <h3 class="k-card-heading" id="settings-appearance-heading">
                        <?php echo klytos_esc_html( __( 'settings.appearance_title' ) ); ?>
                    </h3>

                    <?php // §4: every radio group is in <fieldset><legend>. ?>
                    <fieldset class="k-fieldset">
                        <legend class="k-legend">
                            <?php echo klytos_esc_html( __( 'settings.appearance_choose' ) ); ?>
                        </legend>
                        <?php
                        $currentTheme = (string) ( $siteConfig['admin_theme'] ?? 'dark' );
                        $themeChoices = [
                            'light' => ['settings.appearance_light', 'settings.appearance_light_desc'],
                            'dark'  => ['settings.appearance_dark', 'settings.appearance_dark_desc'],
                        ];
                        foreach ( $themeChoices as $themeValue => $themeLabels ) :
                            ?>
                            <div class="k-field">
                                <label class="k-choice k-hit-24" for="settings-field-admin_theme_<?php echo klytos_esc_attr( $themeValue ); ?>">
                                    <input type="radio"
                                           class="k-radio"
                                           id="settings-field-admin_theme_<?php echo klytos_esc_attr( $themeValue ); ?>"
                                           name="admin_theme"
                                           value="<?php echo klytos_esc_attr( $themeValue ); ?>"
                                           <?php echo $currentTheme === $themeValue ? 'checked' : ''; ?>
                                           aria-describedby="settings-hint-admin_theme_<?php echo klytos_esc_attr( $themeValue ); ?>"
                                           data-testid="settings.admin_theme.<?php echo klytos_esc_attr( $themeValue ); ?>">
                                    <?php echo klytos_esc_html( __( $themeLabels[0] ) ); ?>
                                </label>
                                <p class="k-hint" id="settings-hint-admin_theme_<?php echo klytos_esc_attr( $themeValue ); ?>">
                                    <?php echo klytos_esc_html( __( $themeLabels[1] ) ); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>

                    <div class="k-field">
                        <label class="k-choice k-hit-24" for="settings-field-admin_bar_enabled">
                            <input type="checkbox"
                                   class="k-check"
                                   id="settings-field-admin_bar_enabled"
                                   name="admin_bar_enabled"
                                   value="1"
                                   <?php echo ( $siteConfig['admin_bar_enabled'] ?? true ) ? 'checked' : ''; ?>
                                   data-testid="settings.admin_bar_enabled">
                            <?php echo klytos_esc_html( __( 'admin_bar.settings_label' ) ); ?>
                        </label>
                    </div>
                </div>
            </section>

            <?php // ─── Card — Notices ───────────────────────────── ?>
            <?php $persistentNotices = $app->getNoticeManager()->list(); ?>
            <section class="k-card k-card--padded" aria-labelledby="settings-notices-heading">
                <div class="k-card-body">
                    <h3 class="k-card-heading" id="settings-notices-heading">
                        <?php echo klytos_esc_html( __( 'settings.notices_title' ) ); ?>
                    </h3>

                    <div class="k-field">
                        <label class="k-choice k-hit-24" for="settings-field-show_ads">
                            <input type="checkbox"
                                   class="k-check"
                                   id="settings-field-show_ads"
                                   name="show_ads"
                                   value="1"
                                   <?php echo ( $siteConfig['notices']['show_ads'] ?? true ) ? 'checked' : ''; ?>
                                   aria-describedby="settings-hint-show_ads"
                                   data-testid="settings.show_ads">
                            <?php echo klytos_esc_html( __( 'settings.notices_show_ads' ) ); ?>
                        </label>
                        <p class="k-hint" id="settings-hint-show_ads">
                            <?php echo klytos_esc_html( __( 'settings.notices_show_ads_help' ) ); ?>
                        </p>
                    </div>

                    <?php /*
                     * §2 Empty: a collection inside a form renders one row with
                     * the sentence, keeping the card's heading. The shipped
                     * screen hid the whole block instead, so a person could not
                     * tell "no notices" from "this card is broken".
                     */ ?>
                    <?php if ( $persistentNotices === [] ) : ?>
                        <p class="k-hint" data-testid="settings.notices_empty">
                            <?php echo klytos_esc_html( __( 'settings.notices_empty' ) ); ?>
                        </p>
                    <?php else : ?>
                        <h4 class="k-label"><?php echo klytos_esc_html( __( 'settings.notices_active_list' ) ); ?></h4>
                        <ul class="k-plain-list" data-testid="settings.notices_list">
                            <?php foreach ( $persistentNotices as $pn ) : ?>
                                <li>
                                    <code class="k-code-key"><?php echo klytos_esc_html( (string) ( $pn['id'] ?? '' ) ); ?></code>
                                    <?php echo klytos_esc_html( (string) ( $pn['message'] ?? '' ) ); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php if ( $persistentNotices !== [] ) : ?>
                    <div class="k-card-footer">
                        <button type="submit"
                                name="dismiss_all"
                                value="1"
                                class="k-btn k-btn--secondary k-btn--sm"
                                data-testid="settings.dismiss_all">
                            <?php echo klytos_esc_html( __( 'settings.notices_dismiss_all' ) ); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </section>

            <?php // ─── Card — Maintenance mode ──────────────────── ?>
            <section class="k-card k-card--padded" aria-labelledby="settings-maintenance-heading">
                <div class="k-card-body">
                    <h3 class="k-card-heading" id="settings-maintenance-heading">
                        <?php echo klytos_esc_html( __( 'maintenance.title' ) ); ?>
                    </h3>

                    <div class="k-field">
                        <label class="k-choice k-hit-24" for="settings-field-maintenance_mode">
                            <input type="checkbox"
                                   class="k-check"
                                   id="settings-field-maintenance_mode"
                                   name="maintenance_mode"
                                   value="1"
                                   <?php echo ! empty( $siteConfig['maintenance_mode'] ) ? 'checked' : ''; ?>
                                   aria-describedby="settings-hint-maintenance"
                                   data-testid="settings.maintenance_mode">
                            <?php echo klytos_esc_html( __( 'maintenance.enabled_label' ) ); ?>
                        </label>
                        <p class="k-hint" id="settings-hint-maintenance">
                            <?php echo klytos_esc_html( __( 'settings.maintenance_hint' ) ); ?>
                        </p>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="settings-field-maintenance_message">
                            <?php echo klytos_esc_html( __( 'maintenance.message_label' ) ); ?>
                        </label>
                        <textarea class="k-control"
                                  id="settings-field-maintenance_message"
                                  name="maintenance_message"
                                  rows="3"
                                  data-testid="settings.maintenance_message"><?php
                                    echo klytos_esc_textarea( (string) ( $siteConfig['maintenance_message'] ?? '' ) );
                                    ?></textarea>
                    </div>
                </div>
            </section>

            <?php if ( $canConfigureSite ) : ?>
                <?php // ─── Card — Developer ─────────────────────── ?>
                <section class="k-card k-card--padded" aria-labelledby="settings-developer-heading">
                    <div class="k-card-body">
                        <h3 class="k-card-heading" id="settings-developer-heading">
                            <?php echo klytos_esc_html( __( 'settings.developer' ) ); ?>
                        </h3>

                        <div class="k-field">
                            <label class="k-choice k-hit-24" for="settings-field-developer_mode">
                                <input type="checkbox"
                                       class="k-check"
                                       id="settings-field-developer_mode"
                                       name="developer_mode"
                                       value="1"
                                       <?php echo ! empty( $devConfig['developer_mode'] ) ? 'checked' : ''; ?>
                                       aria-describedby="settings-hint-developer_mode"
                                       data-testid="settings.developer_mode">
                                <?php echo klytos_esc_html( __( 'settings.developer_mode' ) ); ?>
                            </label>
                            <p class="k-hint" id="settings-hint-developer_mode">
                                <?php echo klytos_esc_html( __( 'settings.developer_mode_help' ) ); ?>
                            </p>
                        </div>

                        <?php if ( ! empty( $devConfig['developer_mode'] ) ) : ?>
                            <p class="k-status-line k-status-line--aviso" data-testid="settings.developer_warning">
                                <?php echo klytos_esc_html( __( 'settings.developer_mode_warning' ) ); ?>
                            </p>
                        <?php endif; ?>

                        <?php /*
                         * The seven devbar controls used to render only while
                         * developer mode was already ON, so the panels could not
                         * be configured in the same save that enables it — and
                         * with the mode off they were invisible rather than
                         * disabled, which §2 rules out ("a disabled control is
                         * never hidden"). They are always rendered now; the
                         * fieldset is `disabled` while the mode is off, and its
                         * legend carries the reason.
                         */ ?>
                        <fieldset class="k-fieldset"
                                  <?php echo empty( $devConfig['developer_mode'] ) ? 'disabled' : ''; ?>
                                  data-testid="settings.devbar_panels">
                            <legend class="k-legend">
                                <?php echo klytos_esc_html( __( 'settings.devbar_panels' ) ); ?>
                            </legend>
                            <?php if ( empty( $devConfig['developer_mode'] ) ) : ?>
                                <p class="k-hint" data-testid="settings.devbar_disabled_reason">
                                    <?php echo klytos_esc_html( __( 'settings.devbar_disabled_reason' ) ); ?>
                                </p>
                            <?php endif; ?>
                            <?php
                            $devbarToggles = [
                                'devbar_show_performance' => 'settings.devbar_performance',
                                'devbar_show_queries'     => 'settings.devbar_queries',
                                'devbar_show_hooks'       => 'settings.devbar_hooks',
                                'devbar_show_assets'      => 'settings.devbar_assets',
                                'devbar_show_request'     => 'settings.devbar_request',
                                'devbar_show_environment' => 'settings.devbar_environment',
                            ];
                            foreach ( $devbarToggles as $toggleKey => $toggleLabel ) :
                                ?>
                                <label class="k-choice k-hit-24" for="settings-field-<?php echo klytos_esc_attr( $toggleKey ); ?>">
                                    <input type="checkbox"
                                           class="k-check"
                                           id="settings-field-<?php echo klytos_esc_attr( $toggleKey ); ?>"
                                           name="<?php echo klytos_esc_attr( $toggleKey ); ?>"
                                           value="1"
                                           <?php echo ( $devConfig[ $toggleKey ] ?? true ) ? 'checked' : ''; ?>
                                           data-testid="settings.<?php echo klytos_esc_attr( $toggleKey ); ?>">
                                    <?php echo klytos_esc_html( __( $toggleLabel ) ); ?>
                                </label>
                            <?php endforeach; ?>

                            <div class="k-field">
                                <label class="k-label" for="settings-field-devbar_log_slow_threshold">
                                    <?php echo klytos_esc_html( __( 'settings.devbar_slow_threshold' ) ); ?>
                                </label>
                                <p class="k-hint" id="settings-hint-slow_threshold">
                                    <?php echo klytos_esc_html( __( 'settings.devbar_slow_threshold_help' ) ); ?>
                                </p>
                                <input type="text"
                                       class="k-control k-control--mono"
                                       id="settings-field-devbar_log_slow_threshold"
                                       name="devbar_log_slow_threshold"
                                       inputmode="numeric"
                                       spellcheck="false"
                                       value="<?php echo klytos_esc_attr( (string) ( $devConfig['devbar_log_slow_threshold'] ?? 200 ) ); ?>"
                                       aria-describedby="settings-hint-slow_threshold<?php echo isset( $fieldErrors['devbar_log_slow_threshold'] ) ? ' settings-error-devbar_log_slow_threshold' : ''; ?>"
                                       <?php echo isset( $fieldErrors['devbar_log_slow_threshold'] ) ? 'aria-invalid="true"' : ''; ?>
                                       data-testid="settings.devbar_slow_threshold">
                                <?php if ( isset( $fieldErrors['devbar_log_slow_threshold'] ) ) : ?>
                                    <p class="k-error" id="settings-error-devbar_log_slow_threshold">
                                        <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                        <?php echo klytos_esc_html( $fieldErrors['devbar_log_slow_threshold'] ); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </fieldset>
                    </div>
                </section>
            <?php endif; ?>

        <?php endif; ?>

        <?php klytos_do_action( 'admin.settings.after_section', $section, $siteConfig ); ?>
    </form>
</div>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function () {
    'use strict';

    /*
     * §2 Error — form level: "focus moved to it on load". Server-rendered, so
     * this runs once and does not poll.
     */
    var summary = document.getElementById('settings-error-summary');
    if (summary) {
        summary.focus();
    }

    /*
     * The Locale section's add-a-row button. It clones the LAST rendered row
     * rather than building markup from a string, so the row it adds carries
     * every attribute the server-rendered rows carry — labels, ids,
     * aria-describedby, data-testid — instead of a second, thinner definition
     * of the same row that drifts the moment the PHP changes.
     */
    var addButton = document.getElementById('settings-add-language');
    var list = document.getElementById('settings-languages-list');

    if (addButton && list) {
        addButton.addEventListener('click', function () {
            var rows = list.querySelectorAll('[data-testid^="settings.language_row."]');
            if (!rows.length) {
                return;
            }

            var index = rows.length;
            var clone = rows[rows.length - 1].cloneNode(true);

            clone.setAttribute('data-testid', 'settings.language_row.' + index);

            // Every id/for/aria-describedby/data-testid in the clone still
            // points at the row it was copied from; re-index them all, or the
            // new row's labels address the old row's controls.
            clone.querySelectorAll('input').forEach(function (input) {
                var suffix = input.name === 'lang_code[]' ? 'lang_code_' : 'lang_name_';
                var kind = input.name === 'lang_code[]' ? 'code' : 'name';

                input.value = '';
                input.id = 'settings-field-' + suffix + index;
                input.setAttribute('data-testid', 'settings.language_' + kind + '.' + index);
                input.removeAttribute('aria-invalid');
                input.setAttribute('aria-describedby', 'settings-hint-languages');
            });

            clone.querySelectorAll('label').forEach(function (label) {
                var forAttr = label.getAttribute('for') || '';
                label.setAttribute('for', forAttr.replace(/_\d+$/, '_' + index));
            });

            // An error message belongs to the row it was rendered for.
            clone.querySelectorAll('.k-error').forEach(function (node) {
                node.remove();
            });

            list.appendChild(clone);

            // Focus the new row's first control: the person pressed "add"
            // because they intend to type, and a row that appears below the
            // fold with no focus is a row they have to go find.
            var firstInput = clone.querySelector('input');
            if (firstInput) {
                firstInput.focus();
            }
        });
    }
})();
</script>

<?php klytos_do_action( 'admin.settings.render_custom_sections', $siteConfig, $section ); ?>

<?php klytos_do_action( 'admin.settings.after', $section ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
