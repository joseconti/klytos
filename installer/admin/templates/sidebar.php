<?php

/**
 * Klytos Admin — Sidebar + Toolbar Template (the shell, part 2 of 3).
 *
 * Phase 4 Step 4, stage 2 of 6. Renders the sidebar (brand, search, primary
 * navigation, account row), the toolbar (breadcrumb, save state, actions) and
 * opens `<main>`.
 *
 * WHAT IS IN THE NAV IS NOT DECIDED HERE. `SPEC/navigation.md` is normative and
 * `installer/core/admin-nav.php` implements it; this file draws what
 * `klytos_admin_nav_groups()` returns. Capability filtering has already
 * happened server-side, so an item the person cannot use was never in the array
 * and its markup is never sent (navigation.md §7 — hidden, not disabled).
 *
 * Plugins extend the navigation through `admin.nav_plugin_items` (placed and
 * bounded by the §6 rules) or `admin.nav_groups` (the last word). The legacy
 * `admin.sidebar_items` filter still fires and is bridged in admin-nav.php.
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

use Klytos\Core\Helpers;

$adminPath   = Helpers::getBasePath() . 'admin/';
$currentPage = $currentPage ?? basename($_SERVER['SCRIPT_NAME'], '.php');
$GLOBALS['klytos_admin_page'] = $currentPage;

$navGroups   = klytos_admin_nav_groups();
$spriteUrl   = $adminPath . 'assets/icons/klytos-ui-icons.svg';
$currentUser = klytos_current_user();
$adminTheme  = $GLOBALS['klytos_admin_theme'] ?? 'dark';
$pageTitle   = $GLOBALS['klytos_page_title'] ?? ( $pageTitle ?? '' );

/*
 * Which nav item carries aria-current="page".
 *
 * navigation.md §5: while a CHILD screen is open the PARENT's nav item is the
 * one that carries aria-current — the sidebar never goes blank because you went
 * one level deeper. The map below is that parentage, and it is why the page
 * editor still highlights Pages.
 */
$navParentOf = [
    'page-editor'     => 'pages',
    'post-type-edit'  => 'content-model',
    'block-data'      => 'blocks',
    'template-preview' => 'templates',
    'index'           => 'overview',
    'theme'           => 'theme',
    'post-types'      => 'content-model',
    'taxonomy'        => 'taxonomies',
    'x402-dashboard'  => 'agent-payments',
    'x402-transactions' => 'transactions',
    'x402-settings'   => 'payment-settings',
    'system-integrity' => 'integrity',
    'system-options'  => 'options',
    'scheduled-actions' => 'scheduled',
    'license'         => 'licence',
    'ai-chat'         => 'ai-chat',
];
$activeItemId = $navParentOf[ $currentPage ] ?? $currentPage;
$activeItemId = klytos_apply_filters( 'admin.nav_active_item', $activeItemId, $currentPage );

/**
 * Print one `<use>` from the delivered sprite.
 *
 * A `<use>` pointing at an id the sprite does not contain renders NOTHING —
 * silently, with no console error (L-030). `keel-verify` check 16 fails the
 * build on a bad id; this helper keeps the markup in one place so there is only
 * one shape to check.
 *
 * @param string $spriteUrl Absolute URL of the sprite.
 * @param string $glyph     Sprite symbol id, e.g. `ks-palette`.
 * @param string $class     CSS class for the <svg> element.
 */
if ( ! function_exists( 'klytos_admin_icon' ) ) {
    function klytos_admin_icon( string $spriteUrl, string $glyph, string $class = 'k-nav-icon' ): void
    {
        printf(
            '<svg class="%s" aria-hidden="true" focusable="false"><use href="%s#%s"></use></svg>',
            klytos_esc_attr( $class ),
            klytos_esc_url( $spriteUrl ),
            klytos_esc_attr( $glyph )
        );
    }
}
?>
<?php klytos_do_action('admin.sidebar.before'); ?>
<div class="k-sidebar" id="k-sidebar">
    <?php // The brand link's target is the overview (template-shell.md §3). ?>
    <a class="k-brand" href="<?php echo klytos_esc_url( $adminPath ); ?>" data-testid="shell.brand">
        <span class="k-brand-name">Klytos</span>
        <span class="k-brand-version">v<?php echo klytos_esc_html( $app->getVersion() ); ?></span>
    </a>

<?php klytos_do_action('admin.sidebar.before_search'); ?>
    <?php
    /*
     * Search — a real <form role="search"> with a real <input type="search">.
     * Focusing it opens the command palette (template-shell.md §1).
     *
     * The form carries NO action. template-shell.md says it "also works as a
     * plain search submit with JS off", but no file in the delivery names the
     * screen such a submit goes to, and this codebase has no admin-wide search
     * surface to point it at (`logs.php` has its own `search` parameter and is
     * not one). Inventing a destination is exactly what Phase 4 rule 2 forbids,
     * so the destination is a registered gap — DR-004 — and NOT guessed here.
     * With JS on, the specified behaviour is complete.
     */
    ?>
    <form class="k-search" role="search" aria-label="<?php echo klytos_esc_attr( __( 'shell.search_label' ) ); ?>" onsubmit="return false">
        <input
            type="search"
            class="k-search-input"
            id="k-search-input"
            name="q"
            autocomplete="off"
            spellcheck="false"
            placeholder="<?php echo klytos_esc_attr( __( 'shell.search_placeholder' ) ); ?>"
            data-testid="shell.search">
        <span class="k-search-hint" aria-hidden="true">&#8984;K</span>
    </form>
<?php klytos_do_action('admin.sidebar.after_search'); ?>

<?php klytos_do_action( 'admin.sidebar.before_sections' ); ?>
    <nav id="k-nav" class="k-nav" aria-label="<?php echo klytos_esc_attr( __( 'shell.nav_label' ) ); ?>">
        <?php foreach ( $navGroups as $group ): ?>
            <?php klytos_do_action( 'admin.sidebar.before_section', $group['id'] ); ?>
            <div class="k-nav-group">
                <?php
                /*
                 * Each caption is a real <h2> labelling its own <ul> — that is
                 * what makes the nav navigable by heading (accessibility.md
                 * §4.2). At the 56px rail it stays in the tree and becomes a
                 * 1px rule visually (template-shell.md §2).
                 */
                $captionId = 'k-nav-caption-' . $group['id'];
                ?>
                <h2 class="k-nav-caption" id="<?php echo klytos_esc_attr( $captionId ); ?>">
                    <?php echo klytos_esc_html( __( $group['caption'] ) ); ?>
                </h2>
                <ul aria-labelledby="<?php echo klytos_esc_attr( $captionId ); ?>">
                    <?php foreach ( $group['items'] as $item ): ?>
                        <?php
                        $isActive = ( $item['id'] === $activeItemId );
                        $label    = ! empty( $item['literal'] ) ? $item['label'] : __( $item['label'] );
                        $count    = $item['count'] ?? null;
                        /*
                         * The count is part of the link's accessible name
                         * ("Comments, 6 pending"), so the visible number is
                         * aria-hidden and the name carries the whole phrase.
                         *
                         * The phrase is PER ITEM, because navigation.md's Count
                         * column gives each one a different meaning: Pages
                         * counts pages of every status, Tasks counts only open
                         * ones, Scheduled actions counts failures. One shared
                         * suffix would be wrong for all but one of them.
                         */
                        $accName = $label;
                        if ( $count !== null ) {
                            $countKey    = 'nav.count.' . str_replace( '-', '_', $item['id'] );
                            $countPhrase = __( $countKey, [ 'count' => $count ] );
                            // An unwired count has no phrase of its own yet; the
                            // bare number is honest, an invented noun is not.
                            $accName = $countPhrase === $countKey
                                ? $label . ', ' . $count
                                : $label . ', ' . $countPhrase;
                        }
                        // Built here rather than inline so the attribute is
                        // either wholly present or wholly absent: an item with
                        // no count takes its accessible name from its text.
                        $ariaLabel = $count !== null
                            ? ' aria-label="' . klytos_esc_attr( $accName ) . '"'
                            : '';
                        ?>
                        <li>
                            <a
                                class="k-nav-item"
                                href="<?php echo klytos_esc_url( $item['url'] ); ?>"
                                <?php echo $isActive ? 'aria-current="page"' : ''; ?>
                                <?php echo $ariaLabel; ?>
                                data-testid="shell.nav.<?php echo klytos_esc_attr( $item['id'] ); ?>">
                                <?php klytos_admin_icon( $spriteUrl, $item['glyph'] ); ?>
                                <span class="k-nav-label"><?php echo klytos_esc_html( $label ); ?></span>
                                <?php if ( $count !== null ): ?>
                                    <span class="k-nav-count" aria-hidden="true"><?php echo klytos_esc_html( (string) $count ); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php klytos_do_action( 'admin.sidebar.after_section', $group['id'] ); ?>
        <?php endforeach; ?>

        <?php
        /*
         * "Expand navigation" — foot of the 56px rail only, icon-only, pointing
         * right because it widens the rail. There is no matching collapse
         * button: collapsing is the breakpoint's job (navigation.md §8).
         */
        ?>
        <button
            type="button"
            class="k-rail-expand"
            id="k-rail-expand"
            aria-label="<?php echo klytos_esc_attr( __( 'shell.expand_navigation' ) ); ?>"
            data-testid="shell.rail_expand">
            <?php klytos_admin_icon( $spriteUrl, 'ks-chevron_right', 'k-nav-icon' ); ?>
        </button>
    </nav>
<?php klytos_do_action( 'admin.sidebar.after_sections' ); ?>

<?php klytos_do_action( 'admin.sidebar.footer' ); ?>
    <?php
    /*
     * Account row — 26px avatar, name + role, theme toggle, log out. It is
     * separate furniture from the <nav> (navigation.md §2, "Account").
     */
    $displayLabel = ! empty( $currentUser['display_name'] ) && ( $currentUser['display_name'] ?? '' ) !== ( $currentUser['username'] ?? '' )
        ? $currentUser['display_name']
        : $app->getAuth()->getUsername();
    $displayLabel = klytos_apply_filters( 'admin.topbar_user_display', $displayLabel, $currentUser );
    $initial      = mb_strtoupper( mb_substr( (string) $displayLabel, 0, 1 ) );
    $nextTheme    = $adminTheme === 'dark' ? 'light' : 'dark';
    ?>
    <div class="k-account">
        <span class="k-avatar" aria-hidden="true"><?php echo klytos_esc_html( $initial ); ?></span>
        <a class="k-account-identity" href="<?php echo klytos_esc_url( $adminPath . 'profile.php' ); ?>" data-testid="shell.account">
            <span class="k-account-name"><?php echo klytos_esc_html( $displayLabel ); ?></span>
            <span class="k-account-role"><?php echo klytos_esc_html( (string) ( $currentUser['role'] ?? '' ) ); ?></span>
        </a>
        <?php
        /*
         * Theme toggle — a <button aria-pressed> whose visible text states the
         * TARGET state, text-only with no glyph (navigation.md §8).
         *
         * It posts rather than following a link with a query parameter, which is
         * how template-shell.md describes the JS-off path. The project forbids
         * changing state on a GET and requires CSRF on every mutating handler,
         * and a bare link would let any page flip a person's theme. The
         * normative part — a <button aria-pressed> naming its target state, and
         * a server-set cookie so the next request renders the right theme with
         * no flash — is unchanged. Logged in BUILD-SPEC §5.9 as adaptation 8.
         */
        ?>
        <form method="post" action="<?php echo klytos_esc_url( $adminPath . 'api/theme.php' ); ?>">
            <?php echo klytos_csrf_field(); ?>
            <input type="hidden" name="theme" value="<?php echo klytos_esc_attr( $nextTheme ); ?>">
            <input type="hidden" name="redirect_to" value="<?php echo klytos_esc_attr( $_SERVER['REQUEST_URI'] ?? '' ); ?>">
            <button
                type="submit"
                class="k-theme-toggle"
                aria-pressed="<?php echo $adminTheme === 'dark' ? 'true' : 'false'; ?>"
                data-testid="shell.theme_toggle">
                <?php echo klytos_esc_html( $nextTheme === 'dark' ? __( 'shell.theme_to_dark' ) : __( 'shell.theme_to_light' ) ); ?>
            </button>
        </form>
        <a class="k-logout" href="<?php echo klytos_esc_url( $adminPath . 'logout.php' ); ?>" data-testid="shell.logout">
            <?php echo klytos_esc_html( __( 'auth.logout' ) ); ?>
        </a>
    </div>
</div><!-- /.k-sidebar -->
<?php klytos_do_action('admin.sidebar.after'); ?>

<?php klytos_do_action('admin.topbar_before'); ?>
<header class="k-toolbar">
    <?php
    /*
     * The drawer trigger is the FIRST control in the toolbar, below 900px
     * (template-shell.md §2). It is in the DOM at every width and hidden by the
     * media query, so the markup does not depend on a server-side guess about
     * the viewport.
     */
    ?>
    <button
        type="button"
        class="k-drawer-trigger"
        id="k-drawer-trigger"
        aria-expanded="false"
        aria-controls="k-sidebar"
        aria-label="<?php echo klytos_esc_attr( __( 'shell.navigation' ) ); ?>"
        data-testid="shell.drawer_trigger">
        <?php klytos_admin_icon( $spriteUrl, 'ks-menu', 'k-nav-icon' ); ?>
    </button>

    <nav class="k-breadcrumb" aria-label="<?php echo klytos_esc_attr( __( 'shell.breadcrumb' ) ); ?>">
        <ol>
            <li>
                <a href="<?php echo klytos_esc_url( Helpers::getBasePath() ); ?>" data-testid="shell.breadcrumb.site">
                    <?php echo klytos_esc_html( $app->getSiteConfig()->getValue( 'site_name', 'Klytos' ) ); ?>
                </a>
            </li>
            <?php foreach ( ( $GLOBALS['klytos_breadcrumb'] ?? [] ) as $crumb ): ?>
                <li>
                    <a href="<?php echo klytos_esc_url( (string) ( $crumb['url'] ?? '#' ) ); ?>">
                        <?php echo klytos_esc_html( (string) ( $crumb['label'] ?? '' ) ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php // The last crumb is 600 13px, aria-current, and NOT a link. ?>
            <li aria-current="page"><?php echo klytos_esc_html( $pageTitle ); ?></li>
        </ol>
    </nav>

    <?php
    // Save state — editor screens only; empty everywhere else.
    $saveState = klytos_apply_filters( 'admin.topbar_center', '' );
    if ( $saveState !== '' ):
        /*
         * aria-busy lives on the WRAPPER, which is the shell's node, because
         * template-editor-split.md §2 puts it there: "the toolbar's save state
         * reads 'Saving…' with aria-busy="true" on its wrapper". The screen
         * fills the text; the shell owns the region and its identity, so a
         * second editor screen cannot invent a second id for the same slot.
         */
        ?>
        <span class="k-save-state"
              id="k-save-state"
              aria-busy="false"
              data-testid="shell.save_state"><?php echo klytos_kses_post( $saveState ); ?></span>
    <?php endif; ?>

    <?php
    /*
     * Actions — up to two `sm` buttons, secondary then primary. NEVER three; a
     * third action belongs in the page (template-shell.md §1). The bound is not
     * enforced here because the buttons come from the screen and from plugins;
     * it is checked per screen in stages 4–6.
     */
    ?>
    <?php
    /*
     * The toolbar's own allow-list, and it exists because klytos_kses_post()
     * cannot express this region. That list is written for post CONTENT — it
     * has no <button> at all — so every action a screen put here was silently
     * flattened to its own label: a Save that renders as the word "Save".
     * Stage 2 built this seam and proved it exists; nothing had ever passed a
     * control through it until stage 5's first form screen, which is L-030's
     * shape once more (the artifact was verified, its CONSUMER was not).
     *
     * Deliberately narrow: the elements a toolbar action is made of and the
     * attributes those need, including `form` (the Save button lives outside
     * the <form> it submits — template-record-form.md §1) and `data-testid`.
     * No event-handler attribute is listed, and kses drops every attribute
     * that is not, so a plugin still cannot inject behaviour here.
     */
    $klytosToolbarTags = klytos_apply_filters( 'admin.toolbar_allowed_tags', [
        'button' => [
            'type' => true, 'class' => true, 'id' => true, 'name' => true, 'value' => true,
            'form' => true, 'disabled' => true, 'hidden' => true, 'aria-label' => true, 'aria-describedby' => true,
            'aria-expanded' => true, 'aria-controls' => true, 'aria-pressed' => true,
            'data-testid' => true,
        ],
        'a' => [
            'href' => true, 'class' => true, 'id' => true, 'rel' => true, 'target' => true,
            'aria-label' => true, 'aria-current' => true, 'data-testid' => true,
        ],
        'span' => [ 'class' => true, 'id' => true, 'aria-hidden' => true ],
        'svg'  => [ 'class' => true, 'aria-hidden' => true, 'focusable' => true, 'width' => true, 'height' => true ],
        'use'  => [ 'href' => true ],
    ] );
    ?>
    <div class="k-toolbar-actions">
        <?php echo klytos_kses( klytos_apply_filters('admin.topbar_left', ''), $klytosToolbarTags ); ?>
        <?php echo klytos_kses( klytos_apply_filters('admin.topbar_actions', ''), $klytosToolbarTags ); ?>
        <?php echo klytos_kses( klytos_apply_filters('admin.topbar_right', ''), $klytosToolbarTags ); ?>
    </div>
</header>
<?php klytos_do_action('admin.topbar_after'); ?>

<div class="k-drawer-scrim" id="k-drawer-scrim" hidden></div>

<main id="main" class="k-main" tabindex="-1">
<?php
// ─── Recovery Keys Warning Banner ───────────────────────────
// Shows a persistent banner when recovery keys have not been confirmed.
// Can be postponed for 24 hours via cookie. Kept through the redesign: it is
// behaviour (a security warning), not decoration the new shell replaces.
klytos_do_action( 'admin.banner.recovery_warning' );
$securityConfig = [];
try {
    $securityConfig = $app->getStorage()->readFrom( $app->getConfigPath(), 'config.json.enc' );
} catch ( \Throwable $e ) {
    $securityConfig = [];
}
if ( empty( $securityConfig['recovery_keys_confirmed'] ) ) {
    $bannerDismissed = isset( $_COOKIE['klytos_recovery_banner_dismiss'] )
        && ( (int) $_COOKIE['klytos_recovery_banner_dismiss'] > time() );

    if ( ! $bannerDismissed ) {
        $securityUrl = $adminPath . 'security.php';
        ?>
<div class="k-banner k-banner--danger" id="recoveryBanner" role="status">
    <span>
        <strong><?php echo klytos_esc_html( __( 'security.banner_warning' ) ); ?></strong>
        <?php echo klytos_esc_html( __( 'security.banner_text' ) ); ?>
    </span>
    <span class="k-banner-actions">
        <a href="<?php echo klytos_esc_url( $securityUrl ); ?>"><?php echo klytos_esc_html( __( 'security.banner_go_security' ) ); ?></a>
        <button type="button" id="dismissRecoveryBanner" data-testid="shell.dismiss_recovery_banner">
            <?php echo klytos_esc_html( __( 'security.banner_remind_24h' ) ); ?>
        </button>
    </span>
</div>
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
document.getElementById('dismissRecoveryBanner').addEventListener('click', function() {
    var expires = new Date(Date.now() + 86400000).toUTCString();
    document.cookie = 'klytos_recovery_banner_dismiss=' + Math.floor(Date.now()/1000 + 86400) + '; expires=' + expires + '; path=/; SameSite=Lax';
    document.getElementById('recoveryBanner').style.display = 'none';
});
</script>
        <?php
    }
}
?>
<?php
/*
 * Exactly one <h1> per screen, in main, and it is the page title that also
 * appears as the last breadcrumb crumb (accessibility.md §4.2). Screens that
 * print their own set $pageEmitsOwnH1 before including the header.
 */
if ( empty( $GLOBALS['klytos_page_owns_h1'] ) ) :
    ?>
<h1><?php echo klytos_esc_html( $pageTitle ); ?></h1>
<?php endif; ?>
<?php klytos_do_action('admin.page.before_content', $currentPage); ?>
