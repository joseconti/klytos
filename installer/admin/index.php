<?php

/**
 * Klytos Admin — Dashboard (manifest entry 44, template `overview-stats`)
 *
 * The admin's landing screen and the target of the shell's brand link
 * (`template-shell.md` §3). Its job is one sentence, and `SPEC/manifest.md` §44
 * states it: *what state is this install in, and what should I do next.*
 *
 * It is deliberately NOT a fifth set of numbers. Analytics owns traffic, Tasks
 * owns work, Health owns checks, x402 owns money; this screen shows the state
 * of the INSTALL, and every figure links to the screen that owns the detail.
 *
 * Three things this file will not do, each for a recorded reason:
 *
 *   - **It never blocks on the network.** The *Pending updates* figure comes
 *     from `Updater::getCachedUpdateState()`, which reads the six-hour cache and
 *     never fetches. `checkForUpdate()` is the right call on `updates.php`,
 *     whose job is to check; on the screen a person lands on after every login
 *     it would mean waiting on GitHub.
 *   - **It fabricates no zero.** §44 is explicit that `0` is an answer and `—`
 *     is the absence of one. Last build with no build, and Pending updates with
 *     no usable cache, both render `—`.
 *   - **It has no POST handler for indexing.** DR-002, confirmed in D-072
 *     answer 1 and built with entry 9: indexing is a Settings control. What
 *     lives here is the warning and a link, and the warning carries no toggle —
 *     a site-wide setting is not changed in passing from the landing screen.
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

$pageTitle = __( 'dashboard.title' );

$success = '';
$error   = '';

/*
 * The ONLY write on this screen: §44's "Build now", the third setup step.
 *
 * The page itself is mapped to `pages.view` in `core/admin-gate.php`, and a
 * build publishes the whole site — so the handler carries its own
 * `site.configure` check rather than inheriting the page's tier. That is
 * exactly the defect the indexing toggle had here before DR-002 removed it, and
 * it is not being reintroduced in another shape.
 */
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ( $_POST['action'] ?? '' ) === 'build' ) {
    if ( ! klytos_verify_csrf() ) {
        // A refused token is REPORTED. Rendering as if nothing had been sent is
        // the defect four screens of this build have now carried (D-103).
        $error = __( 'dashboard.error_csrf' );
    } elseif ( ! klytos_has_permission( 'site.configure' ) ) {
        $error = __( 'common.no_permission' );
    } else {
        try {
            klytos_do_action( 'admin.dashboard.before_build' );
            $result = ( new BuildEngine( $app ) )->buildAll();
            $built  = (int) ( $result['pages'] ?? count( $result['built'] ?? [] ) );
            klytos_do_action( 'admin.dashboard.after_build', $result );
            $success = __( 'dashboard.build_finished', ['count' => $built] );
        } catch ( \Throwable $e ) {
            // The person gets a sentence that names cause and action; the
            // exception text goes to the log, where it is useful and where it
            // cannot leak internals into twenty locales (D-103).
            klytos_log( 'error', 'Dashboard build failed: ' . $e->getMessage() );
            $error = __( 'dashboard.build_failed' );
        }
    }
}

// ─── The facts the screen draws ──────────────────────────────────

$siteConfig      = $app->getSiteConfig()->get();
$pageCount       = $app->getPages()->count( 'all' );
$tokenCount      = count( $app->getAuth()->listBearerTokens() );
$lastBuild       = $siteConfig['last_build'] ?? null;
$indexingEnabled = (bool) ( $siteConfig['indexing_enabled'] ?? false );

// The health summary is `good` / `warning` / `critical` per check; "failing" is
// everything that is not good. Every check is a local read — `ini_get`,
// `is_writable`, `file_exists` — so running them on this screen costs nothing
// that a page load does not already cost.
$healthReport  = $app->getSiteHealthManager()->runAll();
$healthTotal   = (int) ( $healthReport['summary']['total'] ?? 0 );
$healthFailing = (int) ( $healthReport['summary']['warning'] ?? 0 )
               + (int) ( $healthReport['summary']['critical'] ?? 0 );

$updateState   = $app->getUpdater()->getCachedUpdateState();

/*
 * §44's empty state, and it is the important one. A brand-new site is one that
 * has never completed all three setup steps; the panel replaces the widget grid
 * until it has, and disappears the moment step 3 is done — it does not linger
 * as a congratulation.
 *
 * Step 2's signal is whether the theme record has ever been written. The
 * ThemeManager returns defaults for a site that has never saved one, so reading
 * its VALUES cannot answer the question — the record's existence can.
 */
$hasPages = $pageCount > 0;
$hasTheme = $app->getStorage()->exists( 'theme', 'theme' );
$hasBuilt = $lastBuild !== null && $lastBuild !== '';
$setupDone = $hasPages && $hasTheme && $hasBuilt;

$adminPath = $adminPath ?? '';

/**
 * §44's "3 hours ago" detail line, in units that carry no plural.
 *
 * D-076: this i18n mechanism has NO plural forms, so every count-bearing string
 * is number-neutral. "3 hours ago" is not — "1 hours ago" is wrong in English
 * and worse in the nineteen other catalogues — so the units are abbreviated,
 * which is number-neutral and is what the delivery's own caption size affords.
 *
 * Deliberately a closure and not a `klytos_*` helper: one consumer exists.
 * Promoting it is one block the moment a second screen wants it, and a public
 * surface with a single caller is a documentation burden nobody asked for.
 *
 * @param string $utcDatetime A stored UTC datetime.
 */
$relativeTime = static function ( string $utcDatetime ): string {
    $then = klytos_datetime_to_timestamp( $utcDatetime );
    if ( $then <= 0 ) {
        return '';
    }

    $seconds = max( 0, klytos_time() - $then );

    if ( $seconds < 60 ) {
        return __( 'dashboard.time_just_now' );
    }
    if ( $seconds < 3600 ) {
        return __( 'dashboard.time_min_ago', ['count' => (int) floor( $seconds / 60 )] );
    }
    if ( $seconds < 86400 ) {
        return __( 'dashboard.time_h_ago', ['count' => (int) floor( $seconds / 3600 )] );
    }

    return __( 'dashboard.time_d_ago', ['count' => (int) floor( $seconds / 86400 )] );
};

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
klytos_do_action( 'admin.dashboard.before' );

/*
 * The stat renderer is defined AFTER the shell, deliberately.
 *
 * `$spriteUrl` and `klytos_admin_icon()` are both created by
 * `templates/sidebar.php`, and a closure captures its `use` variables at
 * DEFINITION time, not at call time. Defined above the requires, this one
 * captured `null` and every card fataled on `klytos_admin_icon()`'s
 * `string` parameter under `strict_types`. Found by the integration tier,
 * which asserts the admin emits a nonced script and got a 500 instead —
 * a defect no amount of reading this file would have shown.
 */
/**
 * Render one stat card.
 *
 * `template-overview-stats.md` §4: a linked stat card is ONE `<a>` wrapping the
 * whole card, never a chevron in its corner, and `aria-labelledby` binds the
 * value to its label so it reads "4 — failing checks". The icon tile is
 * `aria-hidden`. A card with no destination is a `<div>` — an anchor with no
 * href is not a link and is not focusable, which would be worse than either.
 *
 * @param array $stat id, glyph, tone, value, label, detail, href.
 */
$renderStat = static function ( array $stat ) use ( $spriteUrl ): void {
    $id    = (string) $stat['id'];
    $href  = (string) ( $stat['href'] ?? '' );
    $tag   = $href !== '' ? 'a' : 'div';
    $valId = 'dash-stat-' . $id . '-value';
    $labId = 'dash-stat-' . $id . '-label';
    ?>
    <<?php echo $tag; ?> class="k-stat"
        <?php echo $href !== '' ? 'href="' . klytos_esc_url( $href ) . '"' : ''; ?>
        aria-labelledby="<?php echo klytos_esc_attr( $valId . ' ' . $labId ); ?>"
        data-testid="dashboard.stat.<?php echo klytos_esc_attr( $id ); ?>">
        <span class="k-stat-tile k-stat-tile--<?php echo klytos_esc_attr( (string) $stat['tone'] ); ?>" aria-hidden="true">
            <?php klytos_admin_icon( $spriteUrl, (string) $stat['glyph'], '' ); ?>
        </span>
        <p class="k-stat-value" id="<?php echo klytos_esc_attr( $valId ); ?>"
           data-testid="dashboard.stat_value.<?php echo klytos_esc_attr( $id ); ?>">
            <?php echo $stat['value_html'] ?? klytos_esc_html( (string) $stat['value'] ); ?>
        </p>
        <p class="k-stat-label" id="<?php echo klytos_esc_attr( $labId ); ?>">
            <?php echo klytos_esc_html( (string) $stat['label'] ); ?>
        </p>
        <?php if ( ( $stat['detail'] ?? '' ) !== '' ) : ?>
            <p class="k-stat-delta"><?php echo klytos_esc_html( (string) $stat['detail'] ); ?></p>
        <?php endif; ?>
    </<?php echo $tag; ?>>
    <?php
};

?>

<?php if ( $success !== '' ) : ?>
    <p class="k-status-line k-status-line--info" role="status" data-testid="dashboard.success">
        <?php echo klytos_esc_html( $success ); ?>
    </p>
<?php endif; ?>

<?php if ( $error !== '' ) : ?>
    <p class="k-status-line k-status-line--aviso" role="alert" data-testid="dashboard.error">
        <?php echo klytos_esc_html( $error ); ?>
    </p>
<?php endif; ?>

<?php
/*
 * §44's indexing banner. `role="status"` and not `role="alert"`: it is true on
 * arrival, it did not just happen. Not dismissible — it disappears when the
 * condition does. It carries NO toggle.
 *
 * It replaces the `indexing-blocked` system notice, which drew the same warning
 * in the same place in undesigned markup. The notice's condition filter
 * `notice.condition.indexing_blocked` is KEPT and is what gates this banner, so
 * a plugin listening on it still decides whether the warning shows — D-076's
 * rule: the design wins, the seams are preserved.
 */
$showIndexingBanner = klytos_apply_filters( 'notice.condition.indexing_blocked', ! $indexingEnabled );
?>
<?php if ( $showIndexingBanner ) : ?>
    <p class="k-banner k-banner--aviso" role="status" data-testid="dashboard.indexing_banner">
        <?php klytos_admin_icon( $spriteUrl, 'ks-block', 'k-banner-icon' ); ?>
        <span><?php echo klytos_esc_html( __( 'dashboard.indexing_blocked' ) ); ?></span>
        <a href="<?php echo klytos_esc_url( $adminPath . 'settings.php?section=advanced' ); ?>"
           data-testid="dashboard.indexing_link">
            <?php echo klytos_esc_html( __( 'dashboard.indexing_action' ) ); ?>
        </a>
    </p>
<?php endif; ?>

<?php klytos_do_action( 'admin.dashboard.before_stats' ); ?>
<div class="k-stat-row" data-testid="dashboard.stats">
    <?php
    /*
     * §44's five, in its order. The Klytos and PHP versions are deliberately
     * NOT here — §44: "they are facts, not figures", and they are already in
     * the status bar and the System info widget.
     *
     * `Failing checks` is the one card with no destination: entry 22 (Health)
     * is deferred (D-072) and `health.php` does not exist, so a link would 404
     * the landing screen's most alarming figure. Same call D-075 made in the
     * sidebar and adaptation 14 made on Logs.
     */
    $lastBuildTs   = $hasBuilt ? klytos_datetime_to_timestamp( (string) $lastBuild ) : 0;
    $lastBuildHtml = $lastBuildTs > 0
        ? '<time datetime="' . klytos_esc_attr( klytos_gmdate( 'c', $lastBuildTs ) ) . '">'
            . klytos_esc_html( klytos_gmdate( 'Y-m-d H:i', $lastBuildTs ) ) . ' UTC</time>'
        : '—';

    $stats = [
        [
            'id'         => 'last_build',
            'glyph'      => 'ks-cloud_upload',
            'tone'       => $hasBuilt ? 'info' : 'offline',
            'value_html' => $lastBuildHtml,
            'value'      => '',
            'label'      => __( 'dashboard.last_build' ),
            'detail'     => $lastBuildTs > 0 ? $relativeTime( (string) $lastBuild ) : __( 'dashboard.never_built' ),
            'href'       => $adminPath . 'updates.php',
        ],
        [
            'id'     => 'pages',
            'glyph'  => 'ks-description',
            'tone'   => 'info',
            'value'  => (string) $pageCount,
            'label'  => __( 'dashboard.pages' ),
            'detail' => '',
            'href'   => $adminPath . 'pages.php',
        ],
        [
            'id'     => 'mcp',
            'glyph'  => 'ks-key',
            'tone'   => $tokenCount > 0 ? 'exito' : 'offline',
            'value'  => (string) $tokenCount,
            'label'  => __( 'dashboard.mcp' ),
            'detail' => '',
            'href'   => $adminPath . 'mcp.php',
        ],
        [
            // §44: with zero failures this card must READ like an answer, not
            // like an absence — "All 24 checks passed", `ks-task_alt`, exito.
            'id'     => 'checks',
            'glyph'  => $healthFailing === 0 ? 'ks-task_alt' : 'ks-rule',
            'tone'   => $healthFailing === 0 ? 'exito' : 'peligro',
            'value'  => (string) $healthFailing,
            'label'  => __( 'dashboard.failing_checks' ),
            'detail' => $healthFailing === 0
                ? __( 'dashboard.all_checks_passed', ['count' => $healthTotal] )
                : __( 'dashboard.checks_of_total', ['count' => $healthTotal] ),
            'href'   => '',
        ],
        [
            // Three states, never two: `—` when nothing has been checked
            // recently, because a zero here would be a claim nobody made.
            'id'         => 'updates',
            'glyph'      => 'ks-system_update_alt',
            'tone'       => $updateState['state'] === 'pending' ? 'aviso' : 'offline',
            'value_html' => $updateState['state'] === 'unknown'
                ? '—'
                : ( $updateState['state'] === 'pending' ? '1' : '0' ),
            'value'      => '',
            'label'      => __( 'dashboard.pending_updates' ),
            'detail'     => $updateState['state'] === 'unknown'
                ? __( 'dashboard.updates_unknown' )
                : ( $updateState['state'] === 'pending'
                    ? (string) ( $updateState['update']['new_version'] ?? '' )
                    : __( 'dashboard.updates_current' ) ),
            'href'       => $adminPath . 'updates.php',
        ],
    ];

    foreach ( klytos_apply_filters( 'admin.dashboard.stats', $stats ) as $stat ) {
        $renderStat( $stat );
    }
    ?>
</div>
<?php klytos_do_action( 'admin.dashboard.after_stats' ); ?>

<?php if ( ! $setupDone ) : ?>
    <?php
    /*
     * §44's *Set up the site* panel. It is an `<ol>`, not a list of divs: the
     * steps are ordered and the order is information. Step state is TEXT first
     * — `Done` / `Next` / `Later` — and only the `Next` step's action is a
     * primary button; a `Later` step's action is disabled with the reason in
     * its accessible name.
     */
    $steps = [
        [
            'id'     => 'page',
            'title'  => __( 'dashboard.step_page_title' ),
            'body'   => __( 'dashboard.step_page_body' ),
            'action' => __( 'dashboard.step_page_action' ),
            'href'   => $adminPath . 'pages.php',
            'done'   => $hasPages,
        ],
        [
            'id'     => 'theme',
            'title'  => __( 'dashboard.step_theme_title' ),
            'body'   => __( 'dashboard.step_theme_body' ),
            'action' => __( 'dashboard.step_theme_action' ),
            // Entry 3's shipped filename is `theme.php` (adaptation 2): a
            // filename is a URL on a released product.
            'href'   => $adminPath . 'theme.php',
            'done'   => $hasTheme,
        ],
        [
            'id'     => 'build',
            'title'  => __( 'dashboard.step_build_title' ),
            'body'   => __( 'dashboard.step_build_body' ),
            'action' => __( 'dashboard.step_build_action' ),
            'href'   => '',
            'done'   => $hasBuilt,
        ],
    ];

    // The FIRST not-done step is `Next`; everything after it is `Later`.
    $nextIndex = null;
    foreach ( $steps as $i => $step ) {
        if ( ! $step['done'] ) {
            $nextIndex = $i;
            break;
        }
    }
    ?>
    <section class="k-card k-card--padded" aria-labelledby="dashboard-setup-heading"
             data-testid="dashboard.setup">
        <div class="k-card-body">
            <h2 class="k-card-heading" id="dashboard-setup-heading">
                <?php echo klytos_esc_html( __( 'dashboard.setup_heading' ) ); ?>
            </h2>

            <ol class="k-steps">
                <?php foreach ( $steps as $i => $step ) : ?>
                    <?php
                    $state = $step['done'] ? 'done' : ( $i === $nextIndex ? 'next' : 'later' );
                    $stateLabel = __( 'dashboard.step_state_' . $state );
                    ?>
                    <li class="k-step k-step--<?php echo klytos_esc_attr( $state ); ?>"
                        data-testid="dashboard.step.<?php echo klytos_esc_attr( (string) $step['id'] ); ?>">
                        <p class="k-step-state" data-testid="dashboard.step_state.<?php echo klytos_esc_attr( (string) $step['id'] ); ?>">
                            <?php if ( $state === 'done' ) : ?>
                                <?php klytos_admin_icon( $spriteUrl, 'ks-check_circle', 'k-step-glyph' ); ?>
                            <?php elseif ( $state === 'next' ) : ?>
                                <?php klytos_admin_icon( $spriteUrl, 'ks-chevron_right', 'k-step-glyph' ); ?>
                            <?php endif; ?>
                            <?php echo klytos_esc_html( $stateLabel ); ?>
                        </p>

                        <div class="k-step-body">
                            <p class="k-step-title"><?php echo klytos_esc_html( (string) $step['title'] ); ?></p>
                            <p class="k-hint"><?php echo klytos_esc_html( (string) $step['body'] ); ?></p>
                        </div>

                        <div class="k-step-action">
                            <?php if ( $state === 'done' ) : ?>
                                <?php // A finished step offers nothing to do. ?>
                            <?php elseif ( $step['id'] === 'build' ) : ?>
                                <?php if ( $state === 'later' ) : ?>
                                    <?php
                                    /*
                                     * §44: a `Later` step's action is disabled
                                     * with the REASON in its accessible name —
                                     * "Build now — create a page first". It is
                                     * rendered, never hidden.
                                     */
                                    $reason = __( 'dashboard.step_build_reason' );
                                    ?>
                                    <button type="button" class="k-btn k-btn--secondary" disabled
                                            aria-disabled="true"
                                            aria-label="<?php echo klytos_esc_attr( $step['action'] . ' — ' . $reason ); ?>"
                                            data-testid="dashboard.step_action.build">
                                        <?php echo klytos_esc_html( (string) $step['action'] ); ?>
                                    </button>
                                <?php else : ?>
                                    <?php // §44: Build now is a form post with CSRF, never a link. ?>
                                    <form method="post" data-testid="dashboard.build_form">
                                        <?php klytos_csrf_field(); ?>
                                        <input type="hidden" name="action" value="build">
                                        <button type="submit" class="k-btn k-btn--primary"
                                                data-testid="dashboard.step_action.build">
                                            <?php echo klytos_esc_html( (string) $step['action'] ); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else : ?>
                                <a class="k-btn <?php echo $state === 'next' ? 'k-btn--primary' : 'k-btn--secondary'; ?>"
                                   href="<?php echo klytos_esc_url( (string) $step['href'] ); ?>"
                                   data-testid="dashboard.step_action.<?php echo klytos_esc_attr( (string) $step['id'] ); ?>">
                                    <?php echo klytos_esc_html( (string) $step['action'] ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>
<?php else : ?>
    <?php
    // ─── The widget grid (§44's detail cards) ────────────────────
    // Plugins register before rendering.
    klytos_do_action( 'admin.dashboard.init' );

    klytos_register_dashboard_widget(
        'quick_actions',
        __( 'dashboard.quick_actions' ),
        function () use ( $adminPath ): void {
            /*
             * §44: a vertical stack of LINKS, never buttons — they navigate.
             * Each is filtered by capability before render, so a widget with
             * nothing left to show renders nothing at all rather than an empty
             * card (the `null` capability rows are visible to everyone who can
             * see the Dashboard at all).
             */
            $links = [
                ['href' => 'pages.php',     'label' => __( 'dashboard.quick_create_page' ), 'cap' => 'pages.create'],
                ['href' => 'theme.php',     'label' => __( 'dashboard.quick_open_design' ), 'cap' => 'site.configure'],
                ['href' => 'mcp.php',       'label' => __( 'dashboard.quick_create_token' ), 'cap' => 'mcp.manage'],
                ['href' => 'ai-images.php', 'label' => __( 'dashboard.quick_generate_image' ), 'cap' => 'media.upload'],
            ];
            $links = klytos_apply_filters( 'admin.dashboard.quick_actions', $links );

            $visible = array_values( array_filter(
                $links,
                static fn( array $l ): bool => empty( $l['cap'] ) || klytos_has_permission( (string) $l['cap'] )
            ) );

            if ( $visible === [] ) {
                return;
            }
            ?>
            <ul class="k-plain-list">
                <?php foreach ( $visible as $link ) : ?>
                    <li>
                        <a href="<?php echo klytos_esc_url( $adminPath . (string) $link['href'] ); ?>">
                            <?php echo klytos_esc_html( (string) $link['label'] ); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php
        },
        10
    );

    klytos_register_dashboard_widget(
        'system_info',
        __( 'dashboard.system_info' ),
        function () use ( $app ): void {
            // §44: a REAL <table> with a caption, per accessibility.md §2.1.
            ?>
            <table class="k-table" data-testid="dashboard.system_info_table">
                <caption class="k-table-caption"><?php echo klytos_esc_html( __( 'dashboard.system_info' ) ); ?></caption>
                <tbody>
                    <tr>
                        <th scope="row"><?php echo klytos_esc_html( __( 'dashboard.klytos_version' ) ); ?></th>
                        <td class="k-control--mono"><?php echo klytos_esc_html( $app->getVersion() ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo klytos_esc_html( __( 'dashboard.php_version' ) ); ?></th>
                        <td class="k-control--mono"><?php echo klytos_esc_html( PHP_VERSION ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo klytos_esc_html( __( 'dashboard.server' ) ); ?></th>
                        <td class="k-control--mono"><?php echo klytos_esc_html( $_SERVER['SERVER_SOFTWARE'] ?? __( 'list.not_set' ) ); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php
        },
        20
    );

    $currentUser   = klytos_current_user();
    $currentUserId = (string) ( $currentUser['id'] ?? '' );
    $widgetPrefs   = $currentUserId !== ''
        ? ( klytos_get_meta( 'user', $currentUserId, 'dashboard_widgets' ) ?: [] )
        : [];

    klytos_do_action( 'admin.dashboard.before_widgets' );
    $allWidgets = klytos_get_dashboard_widgets();

    $visibleWidgets = array_filter( $allWidgets, static function ( array $w ) use ( $widgetPrefs ): bool {
        // §44: the grid never renders a "widget hidden" placeholder.
        if ( isset( $widgetPrefs[ $w['id'] ] ) && $widgetPrefs[ $w['id'] ] === false ) {
            return false;
        }
        return $w['capability'] === null || klytos_has_permission( (string) $w['capability'] );
    } );
    ?>

    <?php if ( $visibleWidgets !== [] ) : ?>
        <div class="k-widget-grid" data-testid="dashboard.widgets">
            <?php foreach ( $visibleWidgets as $widget ) : ?>
                <?php $wid = 'dashboard-widget-' . preg_replace( '/[^a-z0-9_-]/i', '', (string) $widget['id'] ); ?>
                <section class="k-card k-card--padded"
                         aria-labelledby="<?php echo klytos_esc_attr( $wid ); ?>"
                         data-widget-id="<?php echo klytos_esc_attr( (string) $widget['id'] ); ?>"
                         data-testid="dashboard.widget.<?php echo klytos_esc_attr( (string) $widget['id'] ); ?>">
                    <div class="k-card-body">
                        <h2 class="k-card-heading" id="<?php echo klytos_esc_attr( $wid ); ?>">
                            <?php echo klytos_esc_html( (string) $widget['title'] ); ?>
                        </h2>
                        <?php call_user_func( $widget['callback'] ); ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php klytos_do_action( 'admin.dashboard.after_widgets' ); ?>
<?php endif; ?>

<?php klytos_do_action( 'admin.dashboard.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
