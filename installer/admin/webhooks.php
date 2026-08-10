<?php

/**
 * Klytos Admin — Webhooks (manifest entry 24, the record-form half).
 *
 * Built against `SPEC/screens/template-record-form.md`, `SPEC/accessibility.md`
 * and `SPEC/manifest.md` §24. Stage 5 batch B, screen 11.
 *
 * WHAT IS BUILT: §24's first two cards — Endpoints (the form) and Event
 * subscriptions (a checkbox set in a `<fieldset>`) — as ONE form, plus the list
 * of endpoints the form creates, with the per-endpoint test and the inline
 * two-step delete.
 *
 * WHAT IS NOT BUILT, AND WHY — the per-screen survey ran against the manager
 * before the first line, as it has for every screen in this stage, and it
 * disagreed with the delivery for the ELEVENTH time in eleven:
 *
 *   - **§24's HMAC secret card is deferred** (`docs/roadmap.md` §0c, user
 *     decision 2026-08-10). It specifies "read-only mono + rotate" with a
 *     two-step confirm stating the consequence, and the product has no rotate
 *     of any kind: `WebhookManager::update()`'s `$updatable` list is
 *     `['url','events','description','status']`, `secret` is excluded, and
 *     nothing in the tree regenerates one. The card also assumes ONE secret
 *     while the product stores one PER WEBHOOK. Rotation is a product change on
 *     live integrations — the manifest's own sentence says existing endpoints
 *     stop being able to verify deliveries — not a card.
 *
 *   - **§24's Delivery log list-table is deferred, and the widths were never
 *     what blocked it.** THIS IS THE SIXTH CARD RECORDED AS DR-006-BLOCKED
 *     WHOSE REAL OBSTRUCTION IS A MISSING DATA SOURCE, after entry 26, entry 27
 *     twice, entry 28 and entry 32. `logDelivery()` writes exactly five fields
 *     — `webhook_id`, `success`, `attempts`, `error`, `timestamp` — so of the
 *     six specified columns, **Event**, **Code** and **Duration** have no
 *     source at all: no event name is recorded, the HTTP code is not kept (only
 *     a bool and a free-text error that sometimes reads "HTTP 502"), and no
 *     duration is measured anywhere. The delta "Retry is a form post per
 *     delivery" has no primitive either — the log does not keep the payload, so
 *     there is nothing to re-send. Three columns of six would be numbers nobody
 *     measured.
 *
 * A SHIPPED DEFECT THE SURVEY FOUND, fixed test-first before this screen was
 * touched (`tests/Unit/WebhookTestEventTest.php`, red observed):
 * **"Send test event" has reached no endpoint on any install, and reported
 * success.** Both test controls called `dispatch( 'test.ping', … )`, which
 * resolves targets by subscription, and `test.ping` is subscribable nowhere.
 * `WebhookManager::sendTestEvent()` is the fix, and it is per endpoint — which
 * is what the MCP tool's own schema always claimed to do.
 *
 * FOUR MORE THE SHIPPED SCREEN CARRIED, each the same shape as entry 32's:
 *
 *   1. **A refused CSRF post reported nothing at all** — `if ( … &&
 *      klytos_verify_csrf() )` with no else, so the person's endpoint vanished
 *      and the screen said nothing. The FOURTH screen with this defect, after
 *      entries 27, 28 and 32.
 *   2. **The manager's raw English exception reached the person** in all 20
 *      locales — `$error = $e->getMessage()`, printing "Invalid webhook URL."
 *      whatever language the admin is in.
 *   3. **Delete raised a browser `confirm()`**, which §2 forbids by name.
 *   4. **The whole file had no `__()` call in it** — every string was hardcoded
 *      English, and `date()` was used for a stored UTC timestamp instead of
 *      `klytos_date()`, so the time shown was the server's, not the person's.
 *
 * Authorization is NOT gated here and that is correct: `core/admin-gate.php`
 * maps `webhooks.php` to `webhooks.manage` centrally, verified before building.
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

use Klytos\Core\WebhookManager;

$webhookManager = new WebhookManager( $app->getStorage() );

/*
 * §24's H1 is "Webhooks" and `SPEC/navigation.md` gives the nav item the same
 * label — this screen is not one of the five where the two differ on purpose —
 * so the key is shared rather than a second copy of one word in 20 catalogues.
 */
$pageTitle   = __( 'nav.item.webhooks' );
$currentPage = 'webhooks';

$success = '';

/** @var array<string,string> Field-level errors, keyed by control name. */
$fieldErrors = [];

/** @var array<int,array{name:string,message:string}> The error summary's rows, in DOM order. */
$summaryRows = [];

/** @var array{url:string,description:string,events:array<int,string>} The form's values, so a refused post comes back filled in. */
$draft = [ 'url' => '', 'description' => '', 'events' => [] ];

/** @var string The webhook id whose delete control is armed, if any. */
$pendingDelete = '';

/**
 * The signing secret of a webhook created on THIS request, shown once.
 *
 * §2's read-only rule ("a value the user may copy but not change … is
 * `readonly`, mono, selectable") governs how it is drawn. It is shown on the
 * creating request alone, which is the behaviour this screen has always had:
 * with no rotate in the product, a secret redisplayed on every page load would
 * be a permanently readable credential rather than a one-time handover.
 *
 * @var string
 */
$createdSecret = '';

$availableEvents = $webhookManager->getAvailableEvents();

// ─── Handle POST ────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( ! klytos_verify_csrf() ) {
        /*
         * The shipped screen wrote `if ( … && klytos_verify_csrf() )` with no
         * else, so a refused post produced a page that said nothing whatsoever.
         * The fourth screen in this stage with the identical defect.
         */
        $summaryRows[] = [ 'name' => '', 'message' => __( 'webhooks.error_csrf' ) ];
    } else {
        $action = klytos_sanitize_key( (string) ( $_POST['action'] ?? '' ) );

        if ( $action === 'add_endpoint' ) {
            $draft['url']         = trim( (string) ( $_POST['url'] ?? '' ) );
            $draft['description'] = trim( (string) ( $_POST['description'] ?? '' ) );

            $posted = $_POST['events'] ?? [];
            $draft['events'] = is_array( $posted )
                ? array_values( array_filter(
                    array_map( 'strval', $posted ),
                    static fn( string $event ): bool => isset( $availableEvents[ $event ] )
                ) )
                : [];

            if ( $draft['url'] === '' ) {
                $fieldErrors['url'] = __( 'webhooks.error_url_required' );
            } elseif ( ! filter_var( $draft['url'], FILTER_VALIDATE_URL ) ) {
                $fieldErrors['url'] = __( 'webhooks.error_url_invalid' );
            }

            if ( $draft['events'] === [] ) {
                $fieldErrors['events'] = __( 'webhooks.error_events_required' );
            }

            foreach ( [ 'url', 'events' ] as $field ) {
                if ( isset( $fieldErrors[ $field ] ) ) {
                    $summaryRows[] = [ 'name' => $field, 'message' => $fieldErrors[ $field ] ];
                }
            }

            if ( $summaryRows === [] ) {
                try {
                    $created = $webhookManager->create( [
                        'url'         => $draft['url'],
                        'events'      => $draft['events'],
                        'description' => $draft['description'],
                    ] );

                    $createdSecret = (string) ( $created['secret'] ?? '' );
                    $success       = __( 'webhooks.added' );
                    $draft         = [ 'url' => '', 'description' => '', 'events' => [] ];
                } catch ( \Throwable $e ) {
                    /*
                     * The manager's own message is English and cannot be
                     * translated, and here it is deliberately generic besides:
                     * `create()` collapses "malformed" and "points inside the
                     * network" into one sentence so a refusal cannot be used to
                     * map the host's internal network. It goes to the log, where
                     * the operator can read the real reason and the caller
                     * cannot; the person gets §2's server-failure shape.
                     */
                    klytos_log_error( 'admin.webhooks: create refused — ' . $e->getMessage() );
                    $fieldErrors['url'] = __( 'webhooks.error_url_refused' );
                    $summaryRows[]      = [ 'name' => 'url', 'message' => $fieldErrors['url'] ];
                }
            }
        } elseif ( $action === 'confirm_delete_endpoint' ) {
            // First click ARMS the control. Nothing is written on this pass.
            $pendingDelete = klytos_sanitize_key( (string) ( $_POST['webhook_id'] ?? '' ) );
        } elseif ( $action === 'delete_endpoint' ) {
            try {
                $webhookManager->delete( klytos_sanitize_key( (string) ( $_POST['webhook_id'] ?? '' ) ) );
                $success = __( 'webhooks.deleted' );
            } catch ( \Throwable $e ) {
                klytos_log_error( 'admin.webhooks: delete refused — ' . $e->getMessage() );
                $summaryRows[] = [ 'name' => '', 'message' => __( 'webhooks.error_delete_failed' ) ];
            }
        } elseif ( $action === 'test_endpoint' ) {
            $testId = klytos_sanitize_key( (string) ( $_POST['webhook_id'] ?? '' ) );

            try {
                $result = $webhookManager->sendTestEvent( $testId );

                if ( $result['success'] ) {
                    $success = __( 'webhooks.test_sent', [ 'code' => (string) $result['code'] ] );
                } else {
                    /*
                     * A failed test is the ANSWER to the question the person
                     * asked, not an error in the admin — but it is still a
                     * failure, so it takes §2's summary rather than the status
                     * line. The endpoint's own reason is shown: it is the
                     * receiving end's response, not an internal detail, and
                     * withholding it would leave the control as useless as the
                     * one it replaces.
                     */
                    $summaryRows[] = [
                        'name'    => '',
                        'message' => __( 'webhooks.test_failed', [ 'reason' => $result['error'] ] ),
                    ];
                }
            } catch ( \Throwable $e ) {
                klytos_log_error( 'admin.webhooks: test refused — ' . $e->getMessage() );
                $summaryRows[] = [ 'name' => '', 'message' => __( 'webhooks.error_test_failed' ) ];
            }
        }
    }
}

$webhooks = $webhookManager->list();

/**
 * The event checkboxes, in the order they are drawn.
 *
 * Filterable like every other list this admin renders, so a plugin that adds
 * events through `webhooks.events` can also order or trim what the form offers.
 *
 * @var array<string,string>
 */
$eventChoices = (array) klytos_apply_filters( 'admin.webhooks.event_choices', $availableEvents );

/*
 * template-record-form.md §1: "The primary Save lives in the TOOLBAR … and it
 * is the same button on every form screen." This screen's write is the add,
 * which `form=` also makes the form's implicit submit, so Enter in a field
 * adds the endpoint. No JavaScript.
 */
klytos_add_filter( 'admin.topbar_actions', static function ( string $html ): string {
    return $html . '<button type="submit" form="k-webhook-add"'
        . ' class="k-btn k-btn--primary k-btn--sm"'
        . ' data-testid="webhooks.submit">'
        . klytos_esc_html( __( 'webhooks.add' ) )
        . '</button>';
} );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';

/**
 * The `aria-describedby` value for a field: hint first, then its error (§4).
 *
 * @param  string $field The control's name.
 * @return string
 */
$describedBy = static function ( string $field ) use ( &$fieldErrors ): string {
    $ids = [ 'webhooks-hint-' . $field ];
    if ( isset( $fieldErrors[ $field ] ) ) {
        $ids[] = 'webhooks-error-' . $field;
    }
    return implode( ' ', $ids );
};

/**
 * Print a field's error exactly as §2 specifies: an `error` icon BEFORE the
 * message, so colour is never the only channel.
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
        '<p class="k-error" id="webhooks-error-%s" data-testid="webhooks.error.%s">',
        klytos_esc_attr( $field ),
        klytos_esc_attr( $field )
    );
    klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' );
    echo klytos_esc_html( $fieldErrors[ $field ] );
    echo '</p>';
};
?>
<?php klytos_do_action( 'admin.webhooks.before' ); ?>

<?php if ( $success !== '' ) : ?>
    <?php // §2 Success: the page reloads with a role="status" line under the H1. ?>
    <p class="k-status-line" role="status" data-testid="webhooks.status_line">
        <?php echo klytos_esc_html( $success ); ?>
    </p>
<?php endif; ?>

<?php if ( $summaryRows !== [] ) : ?>
    <?php /*
     * §2 Error — form level: a summary at the top of main, role="alert", focus
     * moved to it on load, every failed field a link to that field.
     */ ?>
    <div class="k-error-summary"
         id="webhooks-error-summary"
         role="alert"
         tabindex="-1"
         data-testid="webhooks.error_summary">
        <h2><?php echo klytos_esc_html( __( 'webhooks.summary_title' ) ); ?></h2>
        <ul>
            <?php foreach ( $summaryRows as $index => $row ) : ?>
                <li>
                    <?php if ( $row['name'] !== '' ) : ?>
                        <a href="#webhooks-field-<?php echo klytos_esc_attr( $row['name'] ); ?>"
                           data-testid="webhooks.error_link.<?php echo (int) $index; ?>">
                            <?php echo klytos_esc_html( $row['message'] ); ?>
                        </a>
                    <?php else : ?>
                        <?php // A refused post has no field to jump to. ?>
                        <?php echo klytos_esc_html( $row['message'] ); ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
/*
 * §24 lists cards, not sections, so the template's optional left column is
 * ABSENT from the DOM rather than rendered empty, and the modifier collapses
 * the grid to one track.
 */
?>
<div class="k-record-form k-record-form--no-nav" data-testid="webhooks.screen">
    <div class="k-card-stack">

        <?php klytos_do_action( 'admin.webhooks.before_cards' ); ?>

        <?php if ( $createdSecret !== '' ) : ?>
            <?php /*
             * The one-time handover of the signing secret. §2's read-only rule:
             * `readonly`, not `disabled`, mono and selectable, with a copy
             * button — never a `disabled` control, which is for something
             * momentarily unavailable.
             */ ?>
            <section class="k-card k-card--padded"
                     id="webhooks-secret"
                     aria-labelledby="webhooks-secret-heading">
                <div class="k-card-body">
                    <h2 class="k-card-heading" id="webhooks-secret-heading">
                        <?php echo klytos_esc_html( __( 'webhooks.secret_heading' ) ); ?>
                    </h2>
                    <div class="k-field">
                        <label class="k-label" for="webhooks-field-secret">
                            <?php echo klytos_esc_html( __( 'webhooks.secret_label' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="webhooks-field-secret"
                               value="<?php echo klytos_esc_attr( $createdSecret ); ?>"
                               readonly
                               spellcheck="false"
                               autocapitalize="off"
                               aria-describedby="webhooks-hint-secret"
                               data-testid="webhooks.field.secret">
                        <p class="k-hint" id="webhooks-hint-secret">
                            <?php echo klytos_esc_html( __( 'webhooks.hint_secret' ) ); ?>
                        </p>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php // ─── Card 1 — Endpoints (the record-form half) ─────────── ?>
        <section class="k-card k-card--padded"
                 id="webhooks-add"
                 aria-labelledby="webhooks-add-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="webhooks-add-heading">
                    <?php echo klytos_esc_html( __( 'webhooks.add_heading' ) ); ?>
                </h2>

                <?php
                /*
                 * `admin.webhooks.before_form` / `after_form` are SHIPPED
                 * extension points and keep firing, whatever the redesign did to
                 * the markup around them (D-076): a released plugin may be
                 * listening, and removing a seam is not a fidelity decision. In
                 * the shipped screen they bracketed the create modal; here they
                 * bracket the form that replaced it, which is the same seam.
                 */
                klytos_do_action( 'admin.webhooks.before_form' );
                ?>

                <form method="post" id="k-webhook-add" data-testid="webhooks.form">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="add_endpoint">

                    <?php klytos_do_action( 'admin.webhooks.before_fields' ); ?>

                    <div class="k-field">
                        <label class="k-label" for="webhooks-field-url">
                            <?php echo klytos_esc_html( __( 'webhooks.field_url' ) ); ?>
                        </label>
                        <?php /*
                         * `type="url"` and `required` are deliberately ABSENT.
                         * Both hand the refusal to Chromium's own constraint
                         * validation, which refuses the submit before a request
                         * exists — putting the empty and malformed cases in the
                         * browser instead of in the handler that owns them, and
                         * out of reach of any test of this screen (L-042). The
                         * hint carries the word "Required" (§4).
                         */ ?>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="webhooks-field-url"
                               name="url"
                               value="<?php echo klytos_esc_attr( $draft['url'] ); ?>"
                               inputmode="url"
                               spellcheck="false"
                               autocapitalize="off"
                               autocomplete="url"
                               aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'url' ) ); ?>"
                               <?php echo isset( $fieldErrors['url'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="webhooks.field.url">
                        <p class="k-hint" id="webhooks-hint-url">
                            <?php echo klytos_esc_html( __( 'webhooks.hint_url' ) ); ?>
                        </p>
                        <?php $fieldError( 'url' ); ?>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="webhooks-field-description">
                            <?php echo klytos_esc_html( __( 'webhooks.field_description' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control"
                               id="webhooks-field-description"
                               name="description"
                               value="<?php echo klytos_esc_attr( $draft['description'] ); ?>"
                               autocomplete="off"
                               aria-describedby="webhooks-hint-description"
                               data-testid="webhooks.field.description">
                        <p class="k-hint" id="webhooks-hint-description">
                            <?php echo klytos_esc_html( __( 'webhooks.hint_description' ) ); ?>
                        </p>
                    </div>

                    <?php klytos_do_action( 'admin.webhooks.after_fields' ); ?>
                </form>

                <?php klytos_do_action( 'admin.webhooks.after_form' ); ?>
            </div>
        </section>

        <?php // ─── Card 2 — Event subscriptions ──────────────────────── ?>
        <section class="k-card k-card--padded"
                 id="webhooks-events"
                 aria-labelledby="webhooks-events-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="webhooks-events-heading">
                    <?php echo klytos_esc_html( __( 'webhooks.events_heading' ) ); ?>
                </h2>

                <?php /*
                 * §24 draws Endpoints and Event subscriptions as two cards, and
                 * `create()` needs both in one write. The controls therefore
                 * carry `form="k-webhook-add"` rather than the cards being
                 * wrapped in one element — the same association the toolbar's
                 * Save already uses across the shell boundary, and the only
                 * mechanism that keeps two separate cards posting as one
                 * record without JavaScript.
                 *
                 * §4: "Grouped controls are in <fieldset><legend> — every radio
                 * group, every checkbox set", and these are checkboxes rather
                 * than switches because this template has a Save.
                 */ ?>
                <fieldset class="k-fieldset"
                          aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'events' ) ); ?>"
                          <?php echo isset( $fieldErrors['events'] ) ? 'aria-invalid="true"' : ''; ?>
                          data-testid="webhooks.field.events">
                    <legend class="k-legend" id="webhooks-field-events">
                        <?php echo klytos_esc_html( __( 'webhooks.events_legend' ) ); ?>
                    </legend>

                    <?php foreach ( $eventChoices as $event => $description ) : ?>
                        <?php $choiceId = 'webhooks-event-' . klytos_sanitize_key( (string) $event ); ?>
                        <label class="k-choice k-hit-24" for="<?php echo klytos_esc_attr( $choiceId ); ?>">
                            <input type="checkbox"
                                   class="k-check"
                                   id="<?php echo klytos_esc_attr( $choiceId ); ?>"
                                   form="k-webhook-add"
                                   name="events[]"
                                   value="<?php echo klytos_esc_attr( (string) $event ); ?>"
                                   <?php echo in_array( (string) $event, $draft['events'], true ) ? 'checked' : ''; ?>
                                   data-testid="webhooks.event.<?php echo klytos_esc_attr( (string) $event ); ?>">
                            <?php /*
                             * The event's own description is the visible label,
                             * with the machine name beside it. The shipped
                             * screen showed the machine name alone and hid the
                             * description in a `title` attribute, which no
                             * screen reader announces reliably and no touch user
                             * can reach at all.
                             */ ?>
                            <span>
                                <?php echo klytos_esc_html( (string) $description ); ?>
                                <code><?php echo klytos_esc_html( (string) $event ); ?></code>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <p class="k-hint" id="webhooks-hint-events">
                    <?php echo klytos_esc_html( __( 'webhooks.hint_events' ) ); ?>
                </p>
                <?php $fieldError( 'events' ); ?>
            </div>
        </section>

        <?php // ─── Card 3 — The endpoints that exist ─────────────────── ?>
        <section class="k-card k-card--padded"
                 id="webhooks-list"
                 aria-labelledby="webhooks-list-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="webhooks-list-heading">
                    <?php echo klytos_esc_html( __( 'webhooks.endpoints_heading' ) ); ?>
                </h2>

                <?php if ( $webhooks === [] ) : ?>
                    <?php /*
                     * §2 Empty — the sentence and the action, never a bare zero.
                     * The action is the field above rather than a second copy of
                     * it: one affordance, one place.
                     */ ?>
                    <p data-testid="webhooks.no_endpoints">
                        <?php echo klytos_esc_html( __( 'webhooks.no_endpoints' ) ); ?>
                    </p>
                    <p>
                        <a href="#webhooks-field-url" data-testid="webhooks.no_endpoints_action">
                            <?php echo klytos_esc_html( __( 'webhooks.no_endpoints_action' ) ); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <?php /*
                     * §24's DELIVERY LOG is the list-table on this screen, and
                     * it is not built — see the file header: three of its six
                     * columns have no data source in the product. The ENDPOINTS
                     * are drawn with the collection component entries 19 and 32
                     * already use for the same shape of record, which needs no
                     * grid widths and invents no column.
                     */ ?>
                    <ul class="k-collection" data-testid="webhooks.endpoints">
                        <?php foreach ( $webhooks as $webhook ) : ?>
                            <?php
                            $webhookId = (string) ( $webhook['id'] ?? '' );
                            $status    = (string) ( $webhook['status'] ?? '' );
                            $failures  = (int) ( $webhook['failure_count'] ?? 0 );
                            $triggered = (string) ( $webhook['last_triggered'] ?? '' );
                            $isArmed   = $pendingDelete !== '' && $pendingDelete === $webhookId;
                            ?>
                            <li class="k-collection-row"
                                data-testid="webhooks.endpoint.<?php echo klytos_esc_attr( $webhookId ); ?>">
                                <div class="k-collection-main">
                                    <span class="k-collection-title">
                                        <?php /*
                                         * A bare `<code>`, exactly as entries 19
                                         * and 32 write the same fact.
                                         * `.k-code` paints `--fondo-ventana`, a
                                         * SUNKEN ground, which inside an
                                         * elevated card measures below AA
                                         * (D-102, measured out of the browser).
                                         */ ?>
                                        <code><?php echo klytos_esc_html( (string) ( $webhook['url'] ?? '' ) ); ?></code>
                                    </span>
                                    <span class="k-collection-meta">
                                        <?php if ( ( $webhook['description'] ?? '' ) !== '' ) : ?>
                                            <span><?php echo klytos_esc_html( (string) $webhook['description'] ); ?></span>
                                        <?php endif; ?>

                                        <?php /*
                                         * The status is a WORD, never a colour
                                         * alone (§1.3). `disabled` is a state the
                                         * manager reaches by itself after ten
                                         * consecutive failures, so it has to be
                                         * legible here or an endpoint stops
                                         * working with no explanation anywhere.
                                         */ ?>
                                        <span class="k-badge <?php echo $status === 'active' ? 'k-badge--exito' : 'k-badge--aviso'; ?>"
                                              data-testid="webhooks.status.<?php echo klytos_esc_attr( $webhookId ); ?>">
                                            <?php
                                            echo klytos_esc_html(
                                                $status === 'active'
                                                    ? __( 'webhooks.status_active' )
                                                    : __( 'webhooks.status_disabled' )
                                            );
                                            ?>
                                        </span>

                                        <?php if ( $failures > 0 ) : ?>
                                            <span class="k-badge k-badge--peligro"
                                                  data-testid="webhooks.failures.<?php echo klytos_esc_attr( $webhookId ); ?>">
                                                <?php
                                                // Number-neutral wording: this i18n
                                                // mechanism has no plural forms (D-076).
                                                echo klytos_esc_html(
                                                    __( 'webhooks.failures', [ 'count' => (string) $failures ] )
                                                );
                                                ?>
                                            </span>
                                        <?php endif; ?>

                                        <span data-testid="webhooks.last_triggered.<?php echo klytos_esc_attr( $webhookId ); ?>">
                                            <?php if ( $triggered !== '' ) : ?>
                                                <?php /*
                                                 * Stored UTC, displayed local
                                                 * (`klytos_date`). The shipped
                                                 * screen used bare `date()`, so
                                                 * every install showed the
                                                 * server's clock as if it were
                                                 * the reader's.
                                                 */ ?>
                                                <time datetime="<?php echo klytos_esc_attr( $triggered ); ?>">
                                                    <?php
                                                    echo klytos_esc_html( __( 'webhooks.last_triggered', [
                                                        'when' => klytos_date(
                                                            'j M Y H:i',
                                                            klytos_datetime_to_timestamp( $triggered )
                                                        ),
                                                    ] ) );
                                                    ?>
                                                </time>
                                            <?php else : ?>
                                                <?php echo klytos_esc_html( __( 'webhooks.never_triggered' ) ); ?>
                                            <?php endif; ?>
                                        </span>

                                        <?php foreach ( (array) ( $webhook['events'] ?? [] ) as $event ) : ?>
                                            <span class="k-badge k-badge--info">
                                                <?php echo klytos_esc_html( (string) $event ); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </span>
                                </div>

                                <div class="k-collection-actions">
                                    <?php /*
                                     * The test is per endpoint, because that is
                                     * what a test is for and what the product
                                     * can now actually do. The shipped control
                                     * was one screen-level "Send Test Event"
                                     * that reached nobody.
                                     */ ?>
                                    <form method="post">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="action" value="test_endpoint">
                                        <input type="hidden" name="webhook_id" value="<?php echo klytos_esc_attr( $webhookId ); ?>">
                                        <button type="submit"
                                                class="k-btn k-btn--secondary k-btn--sm"
                                                data-testid="webhooks.test.<?php echo klytos_esc_attr( $webhookId ); ?>">
                                            <?php echo klytos_esc_html( __( 'webhooks.test' ) ); ?>
                                        </button>
                                    </form>

                                    <?php /*
                                     * §2's inline two-step confirm. The shipped
                                     * screen raised a browser `confirm()`, which
                                     * §2 forbids by name.
                                     */ ?>
                                    <form method="post" class="k-confirm-wrap" aria-live="polite">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="webhook_id" value="<?php echo klytos_esc_attr( $webhookId ); ?>">
                                        <?php if ( $isArmed ) : ?>
                                            <input type="hidden" name="action" value="delete_endpoint">
                                            <button type="submit"
                                                    class="k-btn k-btn--destructive k-btn--sm"
                                                    data-testid="webhooks.delete_confirm.<?php echo klytos_esc_attr( $webhookId ); ?>">
                                                <?php echo klytos_esc_html( __( 'webhooks.confirm_delete' ) ); ?>
                                            </button>
                                        <?php else : ?>
                                            <input type="hidden" name="action" value="confirm_delete_endpoint">
                                            <button type="submit"
                                                    class="k-btn k-btn--secondary k-btn--sm"
                                                    data-testid="webhooks.delete.<?php echo klytos_esc_attr( $webhookId ); ?>">
                                                <?php echo klytos_esc_html( __( 'common.delete' ) ); ?>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <?php klytos_do_action( 'admin.webhooks.after_cards' ); ?>

    </div>
</div>

<?php if ( $summaryRows !== [] ) : ?>
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
( function () {
    'use strict';

    /*
     * §2 Error — form level: "focus moved to it on load". A pure enhancement —
     * the summary is the first thing in `main` and is reachable by keyboard
     * without this, so nothing is lost when the script is absent.
     */
    var summary = document.getElementById( 'webhooks-error-summary' );
    if ( summary ) {
        summary.focus();
    }
}() );
</script>
<?php endif; ?>

<?php klytos_do_action( 'admin.webhooks.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
