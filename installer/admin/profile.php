<?php

/**
 * Klytos Admin — Profile
 *
 * Manifest entry 27 · template `record-form` · H1 **Your profile**.
 * Built in Phase 4 Step 4, stage 5 (the form screens), batch B, screen 8,
 * against `SPEC/screens/template-record-form.md` and `SPEC/manifest.md` §27.
 *
 * The manifest lists FOUR cards — Identity · Sessions · Security · Preferences.
 * THREE are built here, and the fourth is not a scheduling choice: the per-screen
 * survey run against the product BEFORE the first line found it has nothing
 * behind it at all (recorded in `docs/roadmap.md` §0c and D-100):
 *
 *   - **Sessions (list-table, "including MCP clients")** — this product keeps no
 *     session registry of any kind. Sessions are plain `$_SESSION`
 *     (`core/auth.php`), so no device, client, IP, location or start time is
 *     stored anywhere, and there is nothing to list. The only revocation
 *     primitive is `UserManager::forceLogoutAllSessions()`, which is
 *     all-or-nothing and cannot revoke the one row a table would draw. And the
 *     MCP clients are NOT per-person: `core/mcp/oauth-server.php` stores a
 *     client with no user id, so they are install-wide and already live on
 *     manifest entry 8 behind `mcp.manage` — an editor's profile cannot list
 *     them, and duplicating that surface here is the duplication D-090 refused.
 *     This card sat on the DR-006 list waiting for column widths; the widths
 *     were never what was blocking it, which is entry 26's correction arriving a
 *     second time.
 *
 * The **Preferences** card is built with ONE of the five preferences its delta
 * names, for the same reason and on the user's decision (D-100): only the theme
 * is persisted by the product (the `klytos_admin_theme` cookie written by
 * `admin/api/theme.php`). "Dock mode" and "sidebar collapse" exist as
 * `localStorage` keys the server never sees, so a control here would be a
 * JavaScript-only switch on a screen where everything else works without it;
 * "table density" and "last filter" exist nowhere in the tree.
 *
 * The **Security** card carries the password and nothing else, also on the
 * user's decision: manifest entry 6 (`security.php`) is this product's
 * self-service surface for the second factor, passkeys and recovery codes, and a
 * second set of the same controls here would be two surfaces for one duty.
 * The card names where they are and links to them.
 *
 * Three adaptations with reasons, all logged in `docs/BUILD-SPEC.md` §5.9:
 *
 *   - **The avatar preview is withdrawn** (row 46). It could never render on any
 *     install: the admin's own Content-Security-Policy is `img-src 'self' data:`
 *     (`Auth::buildSecurityHeaders()`), and the default avatar for every account
 *     is a Gravatar URL — off-origin by construction. Driving the shipped screen
 *     produced a blocked request and a console error on every single load. The
 *     URL field stays, because the value is used by the PUBLISHED site where
 *     that policy does not apply; what goes is an `<img>` that the product
 *     forbids itself from loading, and the JavaScript that kept re-pointing it.
 *   - **The username field is `readonly`, not `disabled`** (row 47) — §2's own
 *     rule for a value a person may copy but not change.
 *     `UserManager::update()` does not accept `username` at all, so the
 *     constraint is real.
 *   - **The four social networks are a FILTERED list the handler also reads**
 *     (row 48), so a plugin that adds one gets a field that actually saves. An
 *     extension point the handler cannot honour is a defect, not generosity
 *     (entry 26).
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
use Klytos\Core\UserManager;

$pageTitle = __( 'profile.title' );

$auth        = $app->getAuth();
$userManager = $app->getUserManager();

$success = '';

/** @var array<string,string> Field-level errors, keyed by control name. */
$fieldErrors = [];

/** @var array<int,array{name:string,message:string}> The error summary's rows, in DOM order. */
$summaryRows = [];

$userId = $auth->getUserId();
if ( ! $userId ) {
    Helpers::redirect( Helpers::url( 'admin/' ) );
}

$user = $userManager->getById( $userId );

/**
 * Filter: the social networks the Identity card offers.
 *
 * Read ONCE and used twice — to render the fields and to collect them in the
 * POST handler — so a network a plugin adds is a field that actually saves. The
 * shipped screen hardcoded the same four names in both places, which is the
 * shape that makes an extension point a lie (D-099).
 *
 * `label` is a brand name and is deliberately not a translation key; `example`
 * is a URL shown as a placeholder beside a real, visible `<label>`.
 *
 * @param array<string,array{label:string,example:string}> $networks Keyed by storage key.
 */
$socialNetworks = klytos_apply_filters( 'admin.profile.social_networks', [
    'twitter'  => [ 'label' => 'X (Twitter)', 'example' => 'https://x.com/…' ],
    'linkedin' => [ 'label' => 'LinkedIn', 'example' => 'https://linkedin.com/in/…' ],
    'github'   => [ 'label' => 'GitHub', 'example' => 'https://github.com/…' ],
    'mastodon' => [ 'label' => 'Mastodon', 'example' => 'https://mastodon.social/@…' ],
] );

/** The URL fields, each sanitized and reported the same way. */
$urlFields = array_merge(
    [ 'avatar', 'website' ],
    array_map( static fn( string $key ): string => 'social_' . $key, array_keys( $socialNetworks ) )
);

// ─── Handle POST ────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! klytos_verify_csrf() ) {
        /*
         * The shipped screen wrote `if ( POST && klytos_verify_csrf() )` with no
         * else, so a token that had expired made the entire handler disappear
         * and the page re-rendered as though nothing had been sent. The person's
         * edits were gone and the screen said nothing at all — L-041's family,
         * and it is reproduced by `tests/E2E/profile.spec.js`.
         */
        $summaryRows[] = [ 'name' => '', 'message' => __( 'profile.error_csrf' ) ];
    } else {
        $currentPassword = (string) ( $_POST['current_password'] ?? '' );
        $newPassword     = (string) ( $_POST['new_password'] ?? '' );
        $email           = klytos_sanitize_email( (string) ( $_POST['email'] ?? '' ) );
        $rawEmail        = trim( (string) ( $_POST['email'] ?? '' ) );

        /** @var array<string,string> The sanitized URL values, keyed by control name. */
        $urlValues = [];
        foreach ( $urlFields as $field ) {
            $raw = trim( (string) ( $_POST[ $field ] ?? '' ) );
            $urlValues[ $field ] = $raw === '' ? '' : klytos_sanitize_url( $raw );

            if ( $raw !== '' && $urlValues[ $field ] === '' ) {
                // The sanitizer rejects anything that is not a plain http(s)
                // address — `javascript:` included, which matters because this
                // value is rendered on the PUBLISHED site. Reporting the refusal
                // is the point: the shipped screen stored whatever arrived, and
                // storing nothing silently would be no better.
                $fieldErrors[ $field ] = __( 'profile.error_url' );
                $summaryRows[]         = [ 'name' => $field, 'message' => __( 'profile.error_url' ) ];
            }
        }

        /*
         * EVERYTHING IS VALIDATED BEFORE ANYTHING IS WRITTEN, and that ordering
         * is the fix for this slice's finding rather than a style preference.
         * The shipped handler called `update()` first and `changePassword()`
         * second, and the 12-character floor lives inside `changePassword()`:
         * so a person whose new password was too short was told the save had
         * FAILED while their name, email and bio had already been written.
         * Reproduced against the server before this was written —
         * `Expected: "Profile" · Received: "Renamed"` — and re-proven by
         * planting this ordering back and watching that test alone go red.
         */
        if ( $currentPassword === '' ) {
            $fieldErrors['current_password'] = __( 'profile.error_current_password_required' );
            $summaryRows[]                   = [
                'name'    => 'current_password',
                'message' => __( 'profile.error_current_password_required' ),
            ];
        } elseif ( $userManager->authenticate( (string) ( $user['username'] ?? '' ), $currentPassword ) === null ) {
            /*
             * `authenticate()` and not a hash comparison written here: it is the
             * same authority the login gate uses (D-056) and it returns null for
             * every failure alike, so this screen is no account oracle.
             */
            $fieldErrors['current_password'] = __( 'profile.error_current_password_wrong' );
            $summaryRows[]                   = [
                'name'    => 'current_password',
                'message' => __( 'profile.error_current_password_wrong' ),
            ];
        }

        if ( $rawEmail === '' ) {
            $fieldErrors['email'] = __( 'profile.error_email_required' );
            $summaryRows[]        = [ 'name' => 'email', 'message' => __( 'profile.error_email_required' ) ];
        } elseif ( $email === '' ) {
            $fieldErrors['email'] = __( 'profile.error_email_invalid' );
            $summaryRows[]        = [ 'name' => 'email', 'message' => __( 'profile.error_email_invalid' ) ];
        } else {
            $owner = $userManager->getByEmail( $email );
            if ( $owner !== null && (string) ( $owner['id'] ?? '' ) !== (string) $userId ) {
                // The manager enforces this too, and throws. Checking it here as
                // well is what turns a server-reason summary into a field-level
                // error pointing at the control that has to change (§2).
                $fieldErrors['email'] = __( 'profile.error_email_taken' );
                $summaryRows[]        = [ 'name' => 'email', 'message' => __( 'profile.error_email_taken' ) ];
            }
        }

        if ( $newPassword !== '' && strlen( $newPassword ) < UserManager::MIN_PASSWORD_LENGTH ) {
            $fieldErrors['new_password'] = __( 'profile.error_password_short', [ 'min' => UserManager::MIN_PASSWORD_LENGTH ] );
            $summaryRows[]               = [
                'name'    => 'new_password',
                'message' => __( 'profile.error_password_short', [ 'min' => UserManager::MIN_PASSWORD_LENGTH ] ),
            ];
        }

        if ( $summaryRows === [] ) {
            $socialLinks = [];
            foreach ( array_keys( $socialNetworks ) as $key ) {
                $socialLinks[ $key ] = $urlValues[ 'social_' . $key ] ?? '';
            }

            try {
                $userManager->update( $userId, [
                    'first_name'   => klytos_sanitize_text( (string) ( $_POST['first_name'] ?? '' ) ),
                    'last_name'    => klytos_sanitize_text( (string) ( $_POST['last_name'] ?? '' ) ),
                    'email'        => $email,
                    'bio'          => mb_substr( klytos_sanitize_text( (string) ( $_POST['bio'] ?? '' ) ), 0, 500 ),
                    'avatar'       => $urlValues['avatar'] ?? '',
                    'website'      => $urlValues['website'] ?? '',
                    'locale'       => klytos_sanitize_key( (string) ( $_POST['locale'] ?? '' ) ),
                    'social_links' => $socialLinks,
                ] );

                if ( $newPassword !== '' ) {
                    $userManager->changePassword( $userId, $newPassword );
                }

                $success = __( 'profile.saved' );
                $user    = $userManager->getById( $userId );
            } catch ( \Throwable $e ) {
                /*
                 * Everything this screen can name has been named above, so what
                 * reaches here is a genuine server-side failure. §2's shape: the
                 * summary states the cause and the action, and the detail goes
                 * to the log rather than into the page — the manager's messages
                 * are untranslated and can carry another account's email.
                 */
                klytos_log_error( 'admin.profile: save failed — ' . $e->getMessage() );
                $summaryRows[] = [ 'name' => '', 'message' => __( 'profile.error_save_failed' ) ];
            }
        }
    }
}

$csrf = $auth->getCsrfToken();

/** The locales the language select offers: the two built in, plus the site's own. */
$localeOptions = [ 'en' => 'English', 'es' => 'Español' ];
foreach ( (array) $app->getSiteConfig()->getValue( 'languages', [] ) as $language ) {
    $code = (string) ( $language['code'] ?? '' );
    if ( $code === '' || isset( $localeOptions[ $code ] ) ) {
        continue;
    }
    $localeOptions[ $code ] = (string) ( $language['name'] ?? $code );
}

/** The theme in force for this response, resolved exactly as the shell resolves it. */
$currentTheme = ( $_COOKIE['klytos_admin_theme'] ?? '' ) === 'light' ? 'light' : 'dark';

/**
 * A field's `aria-describedby`, hint FIRST and error second (§4).
 *
 * Written once rather than inline per control, so the ORDER — which is the
 * specified part — has exactly one definition.
 */
$describedBy = static function ( string $field, bool $hasHint = true ) use ( &$fieldErrors ): string {
    $ids = [];
    if ( $hasHint ) {
        $ids[] = 'profile-hint-' . $field;
    }
    if ( isset( $fieldErrors[ $field ] ) ) {
        $ids[] = 'profile-error-' . $field;
    }
    return implode( ' ', $ids );
};

/*
 * template-record-form.md §1: "The primary Save lives in the TOOLBAR … and it is
 * the same button on every form screen."
 *
 * The toolbar is emitted by the shell, outside <main> and outside the form, so
 * the button is associated with `form=`. That association also makes it the
 * form's implicit submit button, which is what §4 asks for: Enter in a text
 * field saves. No JavaScript is involved in either. The Preferences card is
 * NOT part of that form — it takes effect immediately and posts elsewhere.
 */
klytos_add_filter( 'admin.topbar_actions', static function ( string $html ): string {
    return $html . '<button type="submit" form="k-profile-form"'
        . ' class="k-btn k-btn--primary k-btn--sm"'
        . ' data-testid="profile.save">'
        . klytos_esc_html( __( 'common.save' ) )
        . '</button>';
} );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';

/**
 * Print a field's error exactly as §2 specifies it: an `error` icon BEFORE the
 * message, the message in `--color-peligro`, and the id the control's
 * `aria-describedby` already points at.
 *
 * Written once and called six times rather than pasted six times — the six
 * copies would be six chances for one of them to lose the icon, which is the
 * channel §1.3 requires beside the colour. `$spriteUrl` is defined by
 * `templates/sidebar.php`, which is why this sits below that include.
 *
 * @param string $field The control's name.
 */
$fieldError = static function ( string $field ) use ( &$fieldErrors, $spriteUrl ): void {
    if ( ! isset( $fieldErrors[ $field ] ) ) {
        return;
    }
    printf(
        '<p class="k-error" id="profile-error-%s" data-testid="profile.error.%s">',
        klytos_esc_attr( $field ),
        klytos_esc_attr( $field )
    );
    klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' );
    echo klytos_esc_html( $fieldErrors[ $field ] );
    echo '</p>';
};
?>
<?php klytos_do_action( 'admin.profile.before' ); ?>

<?php if ( $success !== '' ) : ?>
    <?php // §2 Success: "the page reloads with a role="status" line under the H1". ?>
    <p class="k-status-line" role="status" data-testid="profile.status_line">
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
         id="profile-error-summary"
         role="alert"
         tabindex="-1"
         data-testid="profile.error_summary">
        <h2><?php echo klytos_esc_html( __( 'profile.summary_title' ) ); ?></h2>
        <ul>
            <?php foreach ( $summaryRows as $index => $row ) : ?>
                <li>
                    <?php if ( $row['name'] !== '' ) : ?>
                        <a href="#profile-field-<?php echo klytos_esc_attr( $row['name'] ); ?>"
                           data-testid="profile.error_link.<?php echo (int) $index; ?>">
                            <?php echo klytos_esc_html( $row['message'] ); ?>
                        </a>
                    <?php else : ?>
                        <?php // A refused post and a server failure have no field to jump to. ?>
                        <?php echo klytos_esc_html( $row['message'] ); ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
/*
 * §27 lists cards, not sections, so the template's optional left column is
 * ABSENT from the DOM rather than rendered empty (entry 26's reading, and the
 * modifier collapses the grid to one track).
 */
?>
<div class="k-record-form k-record-form--no-nav" data-testid="profile.screen">
    <div class="k-card-stack">

        <?php /*
         * Cards 1 and 2 are one form with one Save; card 3 takes effect
         * immediately and posts to its own endpoint. A nested <form> is invalid
         * HTML, so the saved half is its own stack INSIDE the page stack —
         * same gap, same width, no new CSS.
         */ ?>
        <form method="post" id="k-profile-form" class="k-card-stack" data-testid="profile.form">
            <?php echo klytos_csrf_field(); ?>

            <?php klytos_do_action( 'admin.profile.before_fields', $user ); ?>

            <?php // ─── Card 1 — Identity ────────────────────────────── ?>
            <section class="k-card k-card--padded"
                     id="profile-identity"
                     aria-labelledby="profile-identity-heading">
                <div class="k-card-body">
                    <h2 class="k-card-heading" id="profile-identity-heading">
                        <?php echo klytos_esc_html( __( 'profile.card_identity' ) ); ?>
                    </h2>

                    <div class="k-field">
                        <label class="k-label" for="profile-field-username">
                            <?php echo klytos_esc_html( __( 'profile.username' ) ); ?>
                        </label>
                        <?php /*
                         * §2 "Read-only vs disabled": a value the person may copy
                         * but not change is `readonly` and selectable, never
                         * `disabled`. `UserManager::update()` does not accept
                         * `username`, so the constraint is real rather than
                         * decorative. It carries no `name`: a readonly control
                         * still posts, and posting a value nothing reads is how
                         * a field starts looking editable to the next reader.
                         */ ?>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="profile-field-username"
                               value="<?php echo klytos_esc_attr( (string) ( $user['username'] ?? '' ) ); ?>"
                               readonly
                               spellcheck="false"
                               autocapitalize="off"
                               aria-describedby="profile-hint-username"
                               data-testid="profile.username">
                        <p class="k-hint" id="profile-hint-username">
                            <?php echo klytos_esc_html( __( 'profile.username_hint' ) ); ?>
                        </p>
                    </div>

                    <div class="k-field-grid k-field-grid--pair">
                        <div class="k-field">
                            <label class="k-label" for="profile-field-first_name">
                                <?php echo klytos_esc_html( __( 'profile.first_name' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control"
                                   id="profile-field-first_name"
                                   name="first_name"
                                   value="<?php echo klytos_esc_attr( (string) ( $user['first_name'] ?? '' ) ); ?>"
                                   autocomplete="given-name"
                                   data-testid="profile.first_name">
                        </div>
                        <div class="k-field">
                            <label class="k-label" for="profile-field-last_name">
                                <?php echo klytos_esc_html( __( 'profile.last_name' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control"
                                   id="profile-field-last_name"
                                   name="last_name"
                                   value="<?php echo klytos_esc_attr( (string) ( $user['last_name'] ?? '' ) ); ?>"
                                   autocomplete="family-name"
                                   data-testid="profile.last_name">
                        </div>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="profile-field-email">
                            <?php echo klytos_esc_html( __( 'profile.email' ) ); ?>
                        </label>
                        <input type="email"
                               class="k-control"
                               id="profile-field-email"
                               name="email"
                               value="<?php echo klytos_esc_attr( (string) ( $user['email'] ?? '' ) ); ?>"
                               autocomplete="email"
                               required
                               aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'email' ) ); ?>"
                               <?php echo isset( $fieldErrors['email'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="profile.email">
                        <p class="k-hint" id="profile-hint-email">
                            <?php echo klytos_esc_html( __( 'profile.email_hint' ) ); ?>
                        </p>
                        <?php $fieldError( 'email' ); ?>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="profile-field-bio">
                            <?php echo klytos_esc_html( __( 'profile.bio' ) ); ?>
                        </label>
                        <textarea class="k-control"
                                  id="profile-field-bio"
                                  name="bio"
                                  rows="3"
                                  maxlength="500"
                                  aria-describedby="profile-hint-bio"
                                  data-testid="profile.bio"><?php echo klytos_esc_textarea( (string) ( $user['bio'] ?? '' ) ); ?></textarea>
                        <p class="k-hint" id="profile-hint-bio">
                            <?php echo klytos_esc_html( __( 'profile.bio_hint' ) ); ?>
                        </p>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="profile-field-avatar">
                            <?php echo klytos_esc_html( __( 'profile.avatar' ) ); ?>
                        </label>
                        <?php /*
                         * No preview, and that is adaptation 46 rather than an
                         * omission: the admin sends `img-src 'self' data:`, so an
                         * image hosted anywhere else cannot load here — and the
                         * default for every account is a Gravatar URL, which is
                         * off-origin by construction. The shipped screen drew it
                         * anyway and produced a blocked request plus a console
                         * error on every load, on every install.
                         */ ?>
                        <input type="url"
                               class="k-control"
                               id="profile-field-avatar"
                               name="avatar"
                               value="<?php echo klytos_esc_attr( (string) ( $user['avatar'] ?? '' ) ); ?>"
                               placeholder="https://…"
                               autocomplete="url"
                               spellcheck="false"
                               autocapitalize="off"
                               aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'avatar' ) ); ?>"
                               <?php echo isset( $fieldErrors['avatar'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="profile.avatar">
                        <p class="k-hint" id="profile-hint-avatar">
                            <?php echo klytos_esc_html( __( 'profile.avatar_hint' ) ); ?>
                        </p>
                        <?php $fieldError( 'avatar' ); ?>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="profile-field-website">
                            <?php echo klytos_esc_html( __( 'profile.website' ) ); ?>
                        </label>
                        <input type="url"
                               class="k-control"
                               id="profile-field-website"
                               name="website"
                               value="<?php echo klytos_esc_attr( (string) ( $user['website'] ?? '' ) ); ?>"
                               placeholder="https://…"
                               autocomplete="url"
                               spellcheck="false"
                               autocapitalize="off"
                               aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'website', false ) ); ?>"
                               <?php echo isset( $fieldErrors['website'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="profile.website">
                        <?php $fieldError( 'website' ); ?>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="profile-field-locale">
                            <?php echo klytos_esc_html( __( 'profile.locale' ) ); ?>
                        </label>
                        <select class="k-control"
                                id="profile-field-locale"
                                name="locale"
                                data-testid="profile.locale">
                            <option value=""><?php echo klytos_esc_html( __( 'common.default' ) ); ?></option>
                            <?php foreach ( $localeOptions as $code => $name ) : ?>
                                <option value="<?php echo klytos_esc_attr( $code ); ?>"
                                    <?php echo ( (string) ( $user['locale'] ?? '' ) === $code ) ? 'selected' : ''; ?>>
                                    <?php echo klytos_esc_html( $name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php /*
                     * §4: "Grouped controls are in <fieldset><legend>". The
                     * shipped screen hid these four inside a collapsed
                     * <details>, so a form's own fields were behind a disclosure
                     * nothing announced as required or optional.
                     */ ?>
                    <fieldset class="k-fieldset" data-testid="profile.social">
                        <legend class="k-legend"><?php echo klytos_esc_html( __( 'profile.social_links' ) ); ?></legend>
                        <p class="k-hint" id="profile-hint-social">
                            <?php echo klytos_esc_html( __( 'profile.social_hint' ) ); ?>
                        </p>
                        <div class="k-field-grid k-field-grid--pair">
                            <?php foreach ( $socialNetworks as $key => $network ) : ?>
                                <?php
                                $field = 'social_' . $key;

                                // One shared hint for the group, then this
                                // field's own error — hint first (§4).
                                $socialDescribes = 'profile-hint-social';
                                if ( isset( $fieldErrors[ $field ] ) ) {
                                    $socialDescribes .= ' profile-error-' . $field;
                                }
                                ?>
                                <div class="k-field">
                                    <label class="k-label" for="profile-field-<?php echo klytos_esc_attr( $field ); ?>">
                                        <?php echo klytos_esc_html( (string) $network['label'] ); ?>
                                    </label>
                                    <input type="url"
                                           class="k-control"
                                           id="profile-field-<?php echo klytos_esc_attr( $field ); ?>"
                                           name="<?php echo klytos_esc_attr( $field ); ?>"
                                           value="<?php echo klytos_esc_attr( (string) ( $user['social_links'][ $key ] ?? '' ) ); ?>"
                                           placeholder="<?php echo klytos_esc_attr( (string) $network['example'] ); ?>"
                                           spellcheck="false"
                                           autocapitalize="off"
                                           aria-describedby="<?php echo klytos_esc_attr( $socialDescribes ); ?>"
                                           <?php echo isset( $fieldErrors[ $field ] ) ? 'aria-invalid="true"' : ''; ?>
                                           data-testid="profile.<?php echo klytos_esc_attr( $field ); ?>">
                                    <?php $fieldError( $field ); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <?php klytos_do_action( 'admin.profile.custom_fields', $user ); ?>
                </div>
            </section>

            <?php klytos_do_action( 'admin.profile.before_security', $user ); ?>

            <?php // ─── Card 2 — Security ────────────────────────────── ?>
            <section class="k-card k-card--padded"
                     id="profile-security"
                     aria-labelledby="profile-security-heading">
                <div class="k-card-body">
                    <h2 class="k-card-heading" id="profile-security-heading">
                        <?php echo klytos_esc_html( __( 'profile.card_security' ) ); ?>
                    </h2>

                    <div class="k-field">
                        <label class="k-label" for="profile-field-current_password">
                            <?php echo klytos_esc_html( __( 'profile.current_password' ) ); ?>
                        </label>
                        <input type="password"
                               class="k-control"
                               id="profile-field-current_password"
                               name="current_password"
                               autocomplete="current-password"
                               required
                               aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'current_password' ) ); ?>"
                               <?php echo isset( $fieldErrors['current_password'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="profile.current_password">
                        <p class="k-hint" id="profile-hint-current_password">
                            <?php echo klytos_esc_html( __( 'profile.current_password_hint' ) ); ?>
                        </p>
                        <?php $fieldError( 'current_password' ); ?>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="profile-field-new_password">
                            <?php echo klytos_esc_html( __( 'profile.new_password' ) ); ?>
                        </label>
                        <?php /*
                         * `minlength` and the hint both read the manager's own
                         * floor. While that constant was private, the number was
                         * hand-copied into this screen — and the server-side
                         * check it duplicated was the one that produced this
                         * slice's finding.
                         */ ?>
                        <input type="password"
                               class="k-control"
                               id="profile-field-new_password"
                               name="new_password"
                               autocomplete="new-password"
                               minlength="<?php echo (int) UserManager::MIN_PASSWORD_LENGTH; ?>"
                               data-klytos-pwgen
                               aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'new_password' ) ); ?>"
                               <?php echo isset( $fieldErrors['new_password'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="profile.new_password">
                        <p class="k-hint" id="profile-hint-new_password">
                            <?php
                            echo klytos_esc_html( __(
                                'profile.new_password_hint',
                                [ 'min' => UserManager::MIN_PASSWORD_LENGTH ]
                            ) );
                            ?>
                        </p>
                        <?php $fieldError( 'new_password' ); ?>
                    </div>

                    <?php /*
                     * The second factor, passkeys and recovery codes are manifest
                     * entry 6's, and building a second set of them here would be
                     * two surfaces for one duty (D-090). The card says where they
                     * are instead of pretending they are not this person's to
                     * manage — `security.self` is held by every role.
                     */ ?>
                    <p class="k-hint">
                        <?php echo klytos_esc_html( __( 'profile.security_elsewhere' ) ); ?>
                    </p>
                    <?php /*
                     * The link is its OWN line rather than a word inside the
                     * sentence above. Inside the sentence it fails WCAG 1.4.1:
                     * `--color-acento` against `--texto-sutil` does not reach the
                     * 3:1 that lets colour alone distinguish a link, and the
                     * `.k-hint` text carries no underline to fall back on —
                     * driven, `link-in-text-block × 1`. Standing alone it is an
                     * action, which is also how §2 writes one ("Open Health").
                     */ ?>
                    <p>
                        <a href="<?php echo klytos_esc_url( Helpers::url( 'admin/security.php' ) ); ?>"
                           data-testid="profile.security_link">
                            <?php echo klytos_esc_html( __( 'profile.security_link' ) ); ?>
                        </a>
                    </p>
                </div>
            </section>

            <?php klytos_do_action( 'admin.profile.after_fields', $user ); ?>
        </form>

        <?php klytos_do_action( 'admin.profile.before_preferences', $user ); ?>

        <?php // ─── Card 3 — Preferences ─────────────────────────────── ?>
        <section class="k-card k-card--padded"
                 id="profile-preferences"
                 aria-labelledby="profile-preferences-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="profile-preferences-heading">
                    <?php echo klytos_esc_html( __( 'profile.card_preferences' ) ); ?>
                </h2>

                <div class="k-field">
                    <?php /*
                     * §27's delta: each preference is "a switch or a select that
                     * takes effect immediately, because they are personal and
                     * reversible", and §4 defines the immediate-effect control as
                     * role="switch". It is a SUBMIT button, so the effect is a
                     * post to the endpoint the shell's own toggle uses — no
                     * script, and it works with JavaScript disabled.
                     */ ?>
                    <form method="post"
                          action="<?php echo klytos_esc_url( Helpers::url( 'admin/api/theme.php' ) ); ?>"
                          class="k-switch-row">
                        <?php echo klytos_csrf_field(); ?>
                        <input type="hidden" name="theme" value="<?php echo $currentTheme === 'dark' ? 'light' : 'dark'; ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo klytos_esc_attr( Helpers::url( 'admin/profile.php' ) ); ?>">
                        <span class="k-label" id="profile-label-theme">
                            <?php echo klytos_esc_html( __( 'profile.theme_dark' ) ); ?>
                        </span>
                        <button type="submit"
                                role="switch"
                                class="k-switch"
                                aria-checked="<?php echo $currentTheme === 'dark' ? 'true' : 'false'; ?>"
                                aria-labelledby="profile-label-theme"
                                aria-describedby="profile-hint-theme"
                                data-testid="profile.theme_switch">
                            <span class="k-switch-thumb"></span>
                        </button>
                    </form>
                    <p class="k-hint" id="profile-hint-theme">
                        <?php echo klytos_esc_html( __( 'profile.theme_hint' ) ); ?>
                    </p>
                </div>
            </div>
        </section>

    </div>
</div>

<?php klytos_do_action( 'admin.profile.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
