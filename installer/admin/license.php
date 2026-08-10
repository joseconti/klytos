<?php

/**
 * Klytos Admin — Licence
 *
 * Manifest entry 28 · templates `record-form` (+ `overview-stats`) · H1 **Licence**.
 * Built in Phase 4 Step 4, stage 5 (the form screens), against
 * `SPEC/screens/template-record-form.md` and `SPEC/manifest.md` §28.
 *
 * The file keeps its shipped name. A filename is a URL on a released product,
 * and the manifest's `licence.php` is recorded as a MAPPING in
 * `docs/BUILD-SPEC.md` §5.9 rather than applied — the same rule that kept
 * `theme.php`, `post-types.php`, `post-type-edit.php` and `taxonomy.php`.
 *
 * §28 lists FOUR cards — Plan · Key · Activated domains · Entitlements. TWO are
 * built here, and the other two are not a scheduling choice: the per-screen
 * survey run against the product BEFORE the first line found neither has
 * anything behind it (recorded in `docs/roadmap.md` §0c and D-101):
 *
 *   - **Activated domains (list-table)** — the licence record holds exactly ONE
 *     `domain` and one `site_url` (`core/license.php::activate()`), the remote
 *     API is never asked for a domain list, and nothing in `License` can
 *     enumerate one. There is no collection for a table to draw. This card sat
 *     on the DR-006 list waiting for column widths; the widths were never what
 *     was blocking it, which is now the FOURTH card that correction has covered
 *     — entry 26, entry 27 twice, and this one.
 *   - **Entitlements (stat row)** — `plan` is a bare string copied out of the
 *     activation response, and the product stores no entitlement, quota or
 *     feature record of any kind. Nothing anywhere reads `plan` to grant or
 *     refuse anything. A stat row here would be numbers nobody measured.
 *
 * FIVE DEFECTS THE SHIPPED SCREEN CARRIED, each reproduced before it was fixed
 * (`tests/E2E/licence.spec.js`):
 *
 *   1. **Every label on the screen was a literal catalogue key.** The screen
 *      called `license.title`, `license.status`, `license.key` and ten more,
 *      while the catalogue's root was `plugin_license` — a root nothing in the
 *      tree referenced. A missing key renders as the key itself, so this screen
 *      has read `license.title` / `license.status` / `license.plan` on every
 *      install, in all 20 languages, since it shipped. Driven first:
 *      `<title>` came back `license.title — Klytos Admin`.
 *   2. **A refused CSRF post reported nothing at all** — `if ( POST && verify )`
 *      with no `else`, so an expired token made the whole handler vanish and the
 *      page re-rendered as though nothing had been sent. L-041's family, and the
 *      identical defect entry 27 found on Profile.
 *   3. **Dates were printed with `date()`**, so every timestamp was rendered in
 *      the server's timezone rather than the site's. `klytos_format_datetime()`
 *      is the project's one answer for a stored UTC value.
 *   4. **An error message was built by concatenation** — `__( 'license.key' ) .
 *      ' is required.'` — which no catalogue can reach and which no language
 *      other than English can word correctly.
 *   5. **The activation field's placeholder was a real-looking 56-character
 *      key** committed in the source. It is replaced by a visible hint: §4
 *      forbids placeholder-as-label anywhere in this admin, and a secret-shaped
 *      literal in a tracked file is a thing this project does not write.
 *
 * TWO FINDINGS RECORDED RATHER THAN FIXED HERE, because each is product scope:
 *
 *   - **`License::checkIfDue()` is called by nothing**, so the seven-day
 *     automatic re-verification the manager implements does not happen on any
 *     install: `VERIFY_INTERVAL` and `needsVerification()` are unreachable.
 *     Wiring it into the admin boot would add an outbound request to every admin
 *     page load on a released product. The screen therefore does not CLAIM an
 *     automatic check — its hint says the check is manual, which is what is
 *     true.
 *   - **`License::isActive()` is called by nothing either**, so the licence
 *     gates no feature anywhere. That makes §28's "an expired licence degrades
 *     this screen only — the admin keeps working" already true by construction,
 *     and it is stated here rather than presented as a control that was built.
 *
 * Adaptations, all logged in `docs/BUILD-SPEC.md` §5.9:
 *
 *   - **The activation form stays**, although §28 says the key is `readonly`.
 *     The readonly rule governs the key that IS stored; it cannot govern the one
 *     affordance in the whole product for putting a key there. Both live in the
 *     Key card: the stored key readonly, mono, selectable, with a copy button,
 *     and below it the field that activates or replaces one.
 *   - **The toolbar's primary action is Activate**, not "Save". This form has no
 *     single save: its two writes are distinct operations, and a toolbar Save
 *     that performed neither would be a control that does nothing. Activate is
 *     the screen's primary write and reaches the toolbar exactly as §1
 *     specifies, which also makes it the key field's implicit submit.
 *   - **§28's second delta — "the status bar carries one fact" — is built on the
 *     shell's own `admin.statusbar_degraded` filter**, which stage 2 created for
 *     exactly this and which had no listener until now. The listener lives in
 *     `admin/bootstrap.php` beside the other cross-screen registrations, because
 *     the fact has to appear on EVERY admin page and a screen only renders
 *     itself. It is a read, never a write, so nothing changes state on a GET.
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

$pageTitle = __( 'license.title' );

$license = $app->getLicense();

$success = '';

/** @var array<string,string> Field-level errors, keyed by control name. */
$fieldErrors = [];

/** @var array<int,array{name:string,message:string}> The error summary's rows, in DOM order. */
$summaryRows = [];

// ─── Handle POST ────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! klytos_verify_csrf() ) {
        /*
         * The shipped screen wrote `if ( POST && klytos_verify_csrf() )` with no
         * else, so a refused post produced a page that said nothing whatsoever.
         * The person's key was gone and the screen looked idle.
         */
        $summaryRows[] = [ 'name' => '', 'message' => __( 'license.error_csrf' ) ];
    } else {
        $action = klytos_sanitize_key( (string) ( $_POST['action'] ?? '' ) );

        if ( $action === 'activate' ) {
            $licenseKey = trim( (string) ( $_POST['license_key'] ?? '' ) );

            if ( $licenseKey === '' ) {
                /*
                 * A whole sentence from the catalogue, not a translated noun
                 * with ' is required.' glued to it. The shipped screen's version
                 * could not be worded correctly in any language but English.
                 */
                $fieldErrors['license_key'] = __( 'license.error_key_required' );
                $summaryRows[]              = [
                    'name'    => 'license_key',
                    'message' => __( 'license.error_key_required' ),
                ];
            } else {
                $result = $license->activate( $licenseKey, Helpers::siteUrl( '' ) );

                if ( $result['success'] ) {
                    $success = __( 'license.activated' );
                } else {
                    /*
                     * `$result['error']` is an untranslated sentence from a
                     * remote server and it is not printed. §2's shape for a
                     * server-side failure is a summary naming the cause and the
                     * action; the remote detail goes to the log, where it is
                     * useful and cannot leak into 20 locales as English.
                     */
                    klytos_log_error( 'admin.licence: activation refused — ' . (string) ( $result['error'] ?? '' ) );

                    $message = ( $result['license'] ?? '' ) === 'error'
                        ? __( 'license.error_unreachable' )
                        : __( 'license.error_activate_failed' );

                    $fieldErrors['license_key'] = $message;
                    $summaryRows[]              = [ 'name' => 'license_key', 'message' => $message ];
                }
            }
        } elseif ( $action === 'verify' ) {
            $result = $license->verify();

            if ( $result['success'] ) {
                $success = __( 'license.checked' );
            } else {
                klytos_log_error( 'admin.licence: check failed — ' . (string) ( $result['error'] ?? '' ) );
                $summaryRows[] = [ 'name' => '', 'message' => __( 'license.error_check_failed' ) ];
            }
        }
    }
}

$status        = $license->getStatus();
$licenseStatus = (string) ( $status['license_status'] ?? 'missing' );
$licenseKey    = (string) ( $status['license_key'] ?? '' );
$hasLicense    = $licenseKey !== '';

/**
 * The status word and its badge tone.
 *
 * Colour never carries the state on its own — the word is the state and the
 * badge is decoration around it (`accessibility.md` §1.3).
 */
$statusLabel = match ( $licenseStatus ) {
    'valid'   => __( 'license.active' ),
    'revoked' => __( 'license.revoked' ),
    'expired' => __( 'license.expired' ),
    default   => __( 'license.inactive' ),
};

$statusTone = match ( $licenseStatus ) {
    'valid'   => 'exito',
    'revoked' => 'peligro',
    'expired' => 'aviso',
    default   => 'info',
};

/**
 * Print a stored UTC timestamp in the site's timezone, or the em dash.
 *
 * §2 of the overview template: an absent value is `—`, never `0` and never a
 * fabricated date. The shipped screen used bare `date()`, which renders in the
 * SERVER's timezone — a different fact from the one the label promises.
 */
$displayDate = static function ( ?string $stored ): string {
    $stored = (string) $stored;

    return $stored === '' ? '—' : klytos_format_datetime( $stored, 'Y-m-d H:i' );
};

/*
 * template-record-form.md §1: "The primary Save lives in the TOOLBAR … and it is
 * the same button on every form screen."
 *
 * This screen's primary write is Activate — see the adaptation note in the file
 * header. `form=` associates it across the shell boundary and makes it the key
 * field's implicit submit, so Enter in that field activates. No JavaScript.
 */
klytos_add_filter( 'admin.topbar_actions', static function ( string $html ): string {
    return $html . '<button type="submit" form="k-licence-activate"'
        . ' class="k-btn k-btn--primary k-btn--sm"'
        . ' data-testid="licence.activate">'
        . klytos_esc_html( __( 'license.activate' ) )
        . '</button>';
} );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';

/**
 * Print a field's error exactly as §2 specifies: an `error` icon BEFORE the
 * message, the message in `--color-peligro`, and the id the control's
 * `aria-describedby` already points at.
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
        '<p class="k-error" id="licence-error-%s" data-testid="licence.error.%s">',
        klytos_esc_attr( $field ),
        klytos_esc_attr( $field )
    );
    klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' );
    echo klytos_esc_html( $fieldErrors[ $field ] );
    echo '</p>';
};
?>
<?php klytos_do_action( 'admin.licence.before', $status ); ?>

<?php if ( $success !== '' ) : ?>
    <?php // §2 Success: the page reloads with a role="status" line under the H1. ?>
    <p class="k-status-line" role="status" data-testid="licence.status_line">
        <?php echo klytos_esc_html( $success ); ?>
    </p>
<?php endif; ?>

<?php if ( $summaryRows !== [] ) : ?>
    <?php /*
     * §2 Error — form level: a summary at the top of main, role="alert", focus
     * moved to it on load, every failed field a link to that field.
     */ ?>
    <div class="k-error-summary"
         id="licence-error-summary"
         role="alert"
         tabindex="-1"
         data-testid="licence.error_summary">
        <h2><?php echo klytos_esc_html( __( 'license.summary_title' ) ); ?></h2>
        <ul>
            <?php foreach ( $summaryRows as $index => $row ) : ?>
                <li>
                    <?php if ( $row['name'] !== '' ) : ?>
                        <a href="#licence-field-<?php echo klytos_esc_attr( $row['name'] ); ?>"
                           data-testid="licence.error_link.<?php echo (int) $index; ?>">
                            <?php echo klytos_esc_html( $row['message'] ); ?>
                        </a>
                    <?php else : ?>
                        <?php // A refused post and a failed check have no field to jump to. ?>
                        <?php echo klytos_esc_html( $row['message'] ); ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
/*
 * §28 lists cards, not sections, so the template's optional left column is
 * ABSENT from the DOM rather than rendered empty, and the modifier collapses the
 * grid to one track.
 */
?>
<div class="k-record-form k-record-form--no-nav" data-testid="licence.screen">
    <div class="k-card-stack">

        <?php klytos_do_action( 'admin.licence.before_cards', $status ); ?>

        <?php // ─── Card 1 — Plan ────────────────────────────────────── ?>
        <section class="k-card k-card--padded"
                 id="licence-plan"
                 aria-labelledby="licence-plan-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="licence-plan-heading">
                    <?php echo klytos_esc_html( __( 'license.plan' ) ); ?>
                </h2>

                <?php if ( ! $hasLicense ) : ?>
                    <?php /*
                     * §2 Empty — no data: the sentence and the action, and never
                     * an invented zero. The action is a link to the card below
                     * rather than a second copy of the field: one affordance,
                     * one place.
                     */ ?>
                    <p data-testid="licence.plan_empty">
                        <?php echo klytos_esc_html( __( 'license.plan_empty' ) ); ?>
                    </p>
                    <p>
                        <a href="#licence-field-license_key" data-testid="licence.plan_empty_action">
                            <?php echo klytos_esc_html( __( 'license.plan_empty_action' ) ); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <dl class="k-rec-dl" data-testid="licence.facts">
                        <dt><?php echo klytos_esc_html( __( 'license.status' ) ); ?></dt>
                        <dd>
                            <span class="k-badge k-badge--<?php echo klytos_esc_attr( $statusTone ); ?>"
                                  data-testid="licence.status">
                                <?php echo klytos_esc_html( $statusLabel ); ?>
                            </span>
                        </dd>

                        <dt><?php echo klytos_esc_html( __( 'license.plan' ) ); ?></dt>
                        <dd data-testid="licence.plan">
                            <?php
                            $plan = trim( (string) ( $status['plan'] ?? '' ) );
                            echo klytos_esc_html( $plan === '' ? '—' : $plan );
                            ?>
                        </dd>

                        <dt><?php echo klytos_esc_html( __( 'license.domain' ) ); ?></dt>
                        <dd data-testid="licence.domain">
                            <?php
                            $domain = trim( (string) ( $status['domain'] ?? '' ) );
                            echo klytos_esc_html( $domain === '' ? '—' : $domain );
                            ?>
                        </dd>

                        <dt><?php echo klytos_esc_html( __( 'license.activated_on' ) ); ?></dt>
                        <dd>
                            <?php $activatedAt = (string) ( $status['activated_at'] ?? '' ); ?>
                            <?php if ( $activatedAt !== '' ) : ?>
                                <time datetime="<?php echo klytos_esc_attr( $activatedAt ); ?>"
                                      data-testid="licence.activated_on"><?php
                                        echo klytos_esc_html( $displayDate( $activatedAt ) );
                                        ?></time>
                            <?php else : ?>
                                <span data-testid="licence.activated_on">—</span>
                            <?php endif; ?>
                        </dd>

                        <dt><?php echo klytos_esc_html( __( 'license.last_check' ) ); ?></dt>
                        <dd>
                            <?php $lastVerified = (string) ( $status['last_verified'] ?? '' ); ?>
                            <?php if ( $lastVerified !== '' ) : ?>
                                <time datetime="<?php echo klytos_esc_attr( $lastVerified ); ?>"
                                      data-testid="licence.last_check"><?php
                                        echo klytos_esc_html( $displayDate( $lastVerified ) );
                                        ?></time>
                            <?php else : ?>
                                <span data-testid="licence.last_check">—</span>
                            <?php endif; ?>
                        </dd>
                    </dl>

                    <?php if ( in_array( $licenseStatus, [ 'revoked', 'expired' ], true ) ) : ?>
                        <?php /*
                         * §28's delta: an expired licence degrades THIS SCREEN
                         * only. Nothing in the tree calls `License::isActive()`,
                         * so that is a description of the product rather than a
                         * control this slice built — said plainly here and in
                         * the file header.
                         */ ?>
                        <p class="k-status-line k-status-line--aviso" data-testid="licence.degraded">
                            <?php
                            echo klytos_esc_html(
                                $licenseStatus === 'expired'
                                    ? __( 'license.notice_expired' )
                                    : __( 'license.notice_revoked' )
                            );
                            ?>
                        </p>
                    <?php endif; ?>

                    <?php if ( $licenseStatus === 'revoked' && ! empty( $status['grace_period_until'] ) ) : ?>
                        <p class="k-hint" data-testid="licence.grace_period">
                            <?php
                            echo klytos_esc_html( __( 'license.grace_period', [
                                'date' => $displayDate( (string) $status['grace_period_until'] ),
                            ] ) );
                            ?>
                        </p>
                    <?php endif; ?>

                    <?php /*
                     * `overview-stats` §2 Loading names the on-demand check as a
                     * first-class control, and this is the screen's one instance
                     * of it. It is a plain form post: the result arrives with the
                     * next response, so there is no in-flight state to draw and
                     * no progressbar that would be theatre.
                     */ ?>
                    <p class="k-hint" id="licence-hint-check">
                        <?php echo klytos_esc_html( __( 'license.check_hint' ) ); ?>
                    </p>
                    <div class="k-card-footer">
                        <form method="post">
                            <?php echo klytos_csrf_field(); ?>
                            <input type="hidden" name="action" value="verify">
                            <button type="submit"
                                    class="k-btn k-btn--secondary k-btn--sm"
                                    aria-describedby="licence-hint-check"
                                    data-testid="licence.check_now">
                                <?php echo klytos_esc_html( __( 'license.check_now' ) ); ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php // ─── Card 2 — Licence key ─────────────────────────────── ?>
        <section class="k-card k-card--padded"
                 id="licence-key"
                 aria-labelledby="licence-key-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="licence-key-heading">
                    <?php echo klytos_esc_html( __( 'license.key' ) ); ?>
                </h2>

                <?php if ( $hasLicense ) : ?>
                    <?php /*
                     * §2 "Read-only vs disabled", and §28's own delta: the key is
                     * `readonly`, not `disabled`, and is selectable. It carries
                     * no `name`: a readonly control still posts, and posting a
                     * value nothing reads is how a field starts looking editable
                     * to the next reader.
                     *
                     * It is shown IN FULL. The shipped screen masked it to
                     * eight-and-eight, which makes a copy button pointless — and
                     * the same delta that asks for the copy is the one that asks
                     * for it to be selectable.
                     */ ?>
                    <div class="k-field">
                        <label class="k-label" for="licence-field-stored_key">
                            <?php echo klytos_esc_html( __( 'license.key_current' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="licence-field-stored_key"
                               value="<?php echo klytos_esc_attr( $licenseKey ); ?>"
                               readonly
                               spellcheck="false"
                               autocapitalize="off"
                               autocomplete="off"
                               aria-describedby="licence-hint-stored_key"
                               data-testid="licence.stored_key">
                        <p class="k-hint" id="licence-hint-stored_key">
                            <?php echo klytos_esc_html( __( 'license.key_hint' ) ); ?>
                        </p>
                        <div class="k-card-footer">
                            <button type="button"
                                    class="k-btn k-btn--secondary k-btn--sm"
                                    id="licence-copy-key"
                                    data-testid="licence.copy_key">
                                <?php echo klytos_esc_html( __( 'license.key_copy' ) ); ?>
                            </button>
                        </div>
                        <?php /*
                         * Both outcomes are TRANSLATED STRINGS carried on the
                         * element, never literals inside the script: a sentence
                         * written in a <script> block is a string no catalogue
                         * can reach.
                         */ ?>
                        <p class="k-hint"
                           id="licence-key-copied"
                           role="status"
                           data-copied="<?php echo klytos_esc_attr( __( 'license.key_copied' ) ); ?>"
                           data-failed="<?php echo klytos_esc_attr( __( 'license.key_copy_failed' ) ); ?>"
                           data-testid="licence.key_copied"></p>
                    </div>
                <?php else : ?>
                    <p data-testid="licence.key_none">
                        <?php echo klytos_esc_html( __( 'license.key_none' ) ); ?>
                    </p>
                <?php endif; ?>

                <?php /*
                 * The activation field — adaptation, and the reason is in the
                 * file header: §28's readonly rule governs the key that IS
                 * stored, and cannot govern the only affordance in the product
                 * for putting one there.
                 *
                 * The form's own submit is in the TOOLBAR (§1) via `form=`, so
                 * there is no second Activate at the foot of the card.
                 */ ?>
                <form method="post" id="k-licence-activate" data-testid="licence.form">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="activate">

                    <?php klytos_do_action( 'admin.licence.before_key_field', $status ); ?>

                    <div class="k-field">
                        <label class="k-label" for="licence-field-license_key">
                            <?php echo klytos_esc_html( __( 'license.key_new' ) ); ?>
                        </label>
                        <?php /*
                         * No placeholder. §4: "No placeholder-as-label anywhere
                         * in the admin" — and the shipped placeholder was a
                         * real-looking 56-character key sitting in a tracked
                         * file. The hint says what to paste and where it comes
                         * from, and it is a real, visible sentence.
                         *
                         * `required` is deliberately ABSENT: the browser's own
                         * constraint validation refuses the submit before a
                         * request exists, which would put the empty-key refusal
                         * in Chromium instead of in the handler that owns it.
                         * That is L-042's trap, and this screen's own test
                         * asserts the SERVER's message.
                         */ ?>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="licence-field-license_key"
                               name="license_key"
                               value=""
                               spellcheck="false"
                               autocapitalize="off"
                               autocomplete="off"
                               aria-describedby="<?php
                                    echo klytos_esc_attr(
                                        isset( $fieldErrors['license_key'] )
                                            ? 'licence-hint-license_key licence-error-license_key'
                                            : 'licence-hint-license_key'
                                    );
                                    ?>"
                               <?php echo isset( $fieldErrors['license_key'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="licence.key_field">
                        <p class="k-hint" id="licence-hint-license_key">
                            <?php echo klytos_esc_html( __( 'license.activate_hint' ) ); ?>
                        </p>
                        <?php $fieldError( 'license_key' ); ?>
                    </div>

                    <?php klytos_do_action( 'admin.licence.after_key_field', $status ); ?>
                </form>
            </div>
        </section>

        <?php klytos_do_action( 'admin.licence.after_cards', $status ); ?>

    </div>
</div>

<?php if ( $hasLicense ) : ?>
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
( function () {
    'use strict';

    /*
     * Copy the licence key. A pure enhancement: the value is in a readonly,
     * selectable field the person can copy by hand, so nothing is lost when this
     * button is absent.
     *
     * The result is announced through a role="status" line rather than by
     * rewriting the button's own label — swapping the label moves the accessible
     * name of the control the person just activated, which a screen reader
     * reports as a different button appearing under their finger.
     */
    var button = document.getElementById( 'licence-copy-key' );
    var field = document.getElementById( 'licence-field-stored_key' );
    var statusLine = document.getElementById( 'licence-key-copied' );

    if ( ! button || ! field || ! statusLine ) {
        return;
    }

    button.addEventListener( 'click', function () {
        if ( ! navigator.clipboard ) {
            statusLine.textContent = statusLine.getAttribute( 'data-failed' ) || '';
            return;
        }

        navigator.clipboard.writeText( field.value ).then( function () {
            statusLine.textContent = statusLine.getAttribute( 'data-copied' ) || '';
        }, function () {
            statusLine.textContent = statusLine.getAttribute( 'data-failed' ) || '';
        } );
    } );
}() );
</script>
<?php endif; ?>

<?php klytos_do_action( 'admin.licence.after', $status ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
