<?php

/**
 * Klytos Admin — Agent payment settings (x402)
 *
 * Manifest entry 37 · template `record-form` · H1 "Agent payment settings".
 * Built in Phase 4 Step 4, stage 5 (the form screens) against
 * `SPEC/screens/template-record-form.md`, `SPEC/manifest.md` §37 and
 * `SPEC/accessibility.md`.
 *
 * WHAT THE PER-SCREEN SURVEY FOUND, run against `X402\Config`, `BotDetector`
 * and the provider registry BEFORE the first line — the rule D-089 earned and
 * the sixth time it has disagreed with the stage-wide survey:
 *
 *   - **Provider** and **Wallet** are backed and are built.
 *   - **Pricing rules** (repeatable) and **the 402 response body** were already
 *     recorded as unbacked (`docs/roadmap.md` §0c): the product has ONE default
 *     price and no response-body editor.
 *   - **Exempt agents** is unbacked AND INVERTED, which nothing had recorded.
 *     The product's repeatable list is `custom_bot_user_agents`, which ADDS
 *     agents to the charged set; there is no subtractive list, and the
 *     built-in set is a class constant with no removal affordance. An agent not
 *     on the list already passes free, so "exempt" only means anything as an
 *     override of the built-ins — new gate semantics, not a card.
 *   - The delta "the enable/disable control is a switch" has NO backing: there
 *     is no global on/off. `x402_enabled` is per PAGE and `x402_default_enabled`
 *     per POST TYPE, both surfaced on their own screens. The prototype's three
 *     switches live on the same unbacked "Who pays" card, so the delta is
 *     deferred with it and every control here is checkbox + Save, which is what
 *     §37's own second sentence asks for.
 *
 * Five cards are built. Two of them — Licence, and the bot lists — are shipped
 * product the manifest's card list does not name: this screen is their only
 * surface and no other entry claims them, and removing shipped behaviour is not
 * a fidelity decision (D-075, D-079, D-091).
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

$pageTitle = __( 'klytos-x402.page_title' );
$auth      = $app->getAuth();
$config    = klytos_x402_config();
$registry  = klytos_x402_providers();

$cfg     = $config->getAll();
$success = '';

/** @var array<string,string> Field-level errors, keyed by control name. */
$fieldErrors = [];
/** @var array<int,array{anchor:string,message:string}> The error summary's rows, in DOM order. */
$summaryRows = [];

/**
 * The licence types the product grants, and the only values it stores.
 *
 * Filterable so a provider plugin can offer its own terms without forking this
 * screen — the extensibility rule, applied to the one list here that a third
 * party could plausibly need to extend.
 *
 * @var array<string,string>
 */
$licenseTypes = klytos_apply_filters( 'x402.settings.license_types', [
    'inference'      => 'klytos-x402.license_inference',
    'inference-only' => 'klytos-x402.license_inference_only',
    'training'       => 'klytos-x402.license_training',
    'full'           => 'klytos-x402.license_full',
] );

$allProviders   = $registry->getAll();
$activeProvider = $registry->has( (string) $cfg['provider_id'] ) ? $registry->get( (string) $cfg['provider_id'] ) : null;

/**
 * The networks the screen offers.
 *
 * The active provider decides; with none active the four the product has always
 * listed stand in, so the field is never an empty select.
 *
 * @return array<int,string>
 */
$networksFor = static function ( ?object $provider ): array {
    $networks = $provider ? $provider->getSupportedNetworks() : [ 'base', 'base-sepolia', 'polygon', 'solana' ];

    return array_values( klytos_apply_filters( 'x402.settings.networks', $networks, $provider ) );
};

// ─── Handle POST ────────────────────────────────────────────────

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
    $action = (string) ( $_POST['action'] ?? '' );

    /*
     * The bot list is edited by its own two actions rather than by the main
     * Save, because it is a COLLECTION inside a form and a form cannot nest in
     * a form (template-record-form.md §2; the idiom entry 39 established).
     * Both work with JavaScript disabled, and both are driven in that state.
     */
    if ( $action === 'add_agent' ) {
        $agent = trim( (string) ( $_POST['agent'] ?? '' ) );

        if ( $agent === '' ) {
            $fieldErrors['agent'] = __( 'klytos-x402.agent_required' );
            $summaryRows[]        = [ 'anchor' => 'x402-field-agent', 'message' => __( 'klytos-x402.summary_agent_required' ) ];
        } elseif ( in_array( $agent, $config->getBotUserAgents(), true ) ) {
            // Silently accepting a duplicate is how a list grows entries that
            // do nothing and cannot be told apart from the ones that do.
            $fieldErrors['agent'] = __( 'klytos-x402.agent_duplicate' );
            $summaryRows[]        = [ 'anchor' => 'x402-field-agent', 'message' => __( 'klytos-x402.summary_agent_duplicate' ) ];
        } else {
            $agents   = $cfg['custom_bot_user_agents'];
            $agents[] = $agent;

            $config->update( [ 'custom_bot_user_agents' => array_values( $agents ) ] );
            $config->clearCache();
            $cfg     = $config->getAll();
            $success = __( 'klytos-x402.agent_added' );

            klytos_do_action( 'x402.settings.agent_added', $agent );
        }
    } elseif ( $action === 'remove_agent' ) {
        $agent  = (string) ( $_POST['agent'] ?? '' );
        $agents = array_values( array_filter(
            $cfg['custom_bot_user_agents'],
            static fn( $existing ): bool => (string) $existing !== $agent
        ) );

        /*
         * This save is the one `Config::update()` could not perform until this
         * slice: `array_replace_recursive()` merged lists index by index, so a
         * shorter list came back unchanged and nothing said why. Pinned by
         * `tests/Integration/X402ConfigUpdateTest.php`.
         */
        $config->update( [ 'custom_bot_user_agents' => $agents ] );
        $config->clearCache();
        $cfg     = $config->getAll();
        $success = __( 'klytos-x402.agent_removed' );

        klytos_do_action( 'x402.settings.agent_removed', $agent );
    } elseif ( $action === 'save' ) {
        $postedProviderId = (string) ( $_POST['provider_id'] ?? $cfg['provider_id'] );
        $providerChanged  = $postedProviderId !== (string) $cfg['provider_id'];

        $selectedProvider = $registry->has( $postedProviderId ) ? $registry->get( $postedProviderId ) : null;

        if ( $postedProviderId !== '' && $selectedProvider === null ) {
            $fieldErrors['provider_id'] = __( 'klytos-x402.provider_unknown' );
            $summaryRows[]              = [ 'anchor' => 'x402-provider-heading', 'message' => __( 'klytos-x402.summary_provider_unknown' ) ];
        }

        // ── Price ──
        $price = trim( (string) ( $_POST['default_price_usd'] ?? '' ) );

        /*
         * The shipped screen stored whatever was typed. `abc` became the
         * default price of every protected page, and the failure surfaced at
         * payment time, far from the field that accepted it — D-096's
         * `smtp_port` defect on another screen.
         */
        if ( $price === '' || preg_match( '/^\d+(\.\d{1,6})?$/', $price ) !== 1 || (float) $price <= 0 ) {
            $fieldErrors['default_price_usd'] = __( 'klytos-x402.price_invalid' );
            $summaryRows[]                    = [ 'anchor' => 'x402-field-default_price_usd', 'message' => __( 'klytos-x402.summary_price_invalid' ) ];
        }

        // ── Provider-specific settings ──
        $providerSettings = [];

        if ( $selectedProvider !== null && ! $providerChanged ) {
            foreach ( $selectedProvider->getSettingsFields() as $field ) {
                $key   = (string) $field['key'];
                $value = trim( (string) ( $_POST[ 'provider_' . $key ] ?? '' ) );

                /*
                 * A secret field left blank KEEPS what is stored.
                 *
                 * The shipped screen rendered the stored value into the
                 * `value=` attribute of the password input, so a live Stripe
                 * secret key was written into the admin page's HTML on every
                 * load — readable in view-source, in a proxy log, in any cached
                 * copy and in a screenshot of the source. The field is now
                 * never populated, which means a blank post has to mean "keep",
                 * or every save would wipe the key.
                 */
                if ( $value === '' && ( $field['type'] ?? '' ) === 'password' ) {
                    $providerSettings[ $key ] = (string) ( $cfg['provider_settings'][ $key ] ?? '' );
                    continue;
                }

                $providerSettings[ $key ] = $value;
            }

            $validation = $selectedProvider->validateSettings( $providerSettings );

            if ( empty( $validation['valid'] ) ) {
                foreach ( (array) ( $validation['errors'] ?? [] ) as $message ) {
                    // The provider returns sentences and no field keys, so the
                    // summary links to the provider card rather than inventing
                    // an association that is not in the contract.
                    $summaryRows[] = [ 'anchor' => 'x402-provider-heading', 'message' => (string) $message ];
                }
            }
        } elseif ( $selectedProvider !== null ) {
            /*
             * Switching provider saves the CHOICE and asks for that provider's
             * settings next. The shipped screen validated the new provider's
             * required fields against a form that had rendered the OLD
             * provider's, so the required-field error fired every time and the
             * provider could never actually be changed.
             */
            $providerSettings = (array) ( $cfg['provider_settings'] ?? [] );
        }

        if ( $summaryRows === [] ) {
            $updates = [
                'provider_id'        => $postedProviderId,
                'wallet_address'     => trim( (string) ( $_POST['wallet_address'] ?? '' ) ),
                'default_price_usd'  => $price,
                'network'            => (string) ( $_POST['network'] ?? 'base' ),
                'logging_enabled'    => ! empty( $_POST['logging_enabled'] ),
                'stats_enabled'      => ! empty( $_POST['stats_enabled'] ),
                'provider_settings'  => $providerSettings,
                'license'            => [
                    'default_type' => array_key_exists( (string) ( $_POST['license_type'] ?? '' ), $licenseTypes )
                        ? (string) $_POST['license_type']
                        : (string) ( $cfg['license']['default_type'] ?? 'inference' ),
                    'default_text' => trim( (string) ( $_POST['license_text'] ?? '' ) ),
                ],
            ];

            $updates = klytos_apply_filters( 'x402.settings.updates', $updates, $cfg );

            klytos_do_action( 'x402.settings.before_save', $updates, $cfg );

            $config->update( $updates );
            $config->clearCache();
            $cfg = $config->getAll();

            $success = $providerChanged
                ? __( 'klytos-x402.saved_provider_changed' )
                : __( 'klytos-x402.settings_saved' );

            // The released hook, firing exactly where it always did.
            klytos_do_action( 'x402.config.updated', $updates );
            klytos_do_action( 'x402.settings.after_save', $cfg );
        } else {
            /*
             * A REFUSED save re-renders what was POSTED, not what is stored.
             *
             * Found by driving: the first version sent the person back to the
             * stored values, so the screen showed `0.075` beside an error
             * saying the price is not a number — their work discarded and the
             * message pointing at a value that is in fact fine. Only the price
             * field is validated, but every posted field is restored, because
             * a refusal on one field must not silently throw away edits made
             * to the others in the same save. It is display state only:
             * nothing here reaches storage.
             */
            $cfg = array_replace_recursive( $cfg, [
                'provider_id'       => $postedProviderId,
                'wallet_address'    => trim( (string) ( $_POST['wallet_address'] ?? '' ) ),
                'default_price_usd' => $price,
                'network'           => (string) ( $_POST['network'] ?? $cfg['network'] ),
                'logging_enabled'   => ! empty( $_POST['logging_enabled'] ),
                'stats_enabled'     => ! empty( $_POST['stats_enabled'] ),
                'license'           => [
                    'default_type' => array_key_exists( (string) ( $_POST['license_type'] ?? '' ), $licenseTypes )
                        ? (string) $_POST['license_type']
                        : (string) ( $cfg['license']['default_type'] ?? 'inference' ),
                    'default_text' => trim( (string) ( $_POST['license_text'] ?? '' ) ),
                ],
            ] );
        }
    }

    // The registry's view of "active" may have moved with the save.
    $activeProvider = $registry->has( (string) $cfg['provider_id'] ) ? $registry->get( (string) $cfg['provider_id'] ) : null;
}

$networks = $networksFor( $activeProvider );
$csrf     = $auth->getCsrfToken();

$customAgents = array_values( (array) $cfg['custom_bot_user_agents'] );
$knownAgents  = array_values( (array) $cfg['known_bot_user_agents'] );

/*
 * template-record-form.md §1: "The primary Save lives in the TOOLBAR, not at
 * the foot of the page, and it is the same button on every form screen."
 *
 * The toolbar is emitted by the shell, outside <main>, so the button associates
 * by `form=` — which is also what makes Enter in a text field save (§4). No
 * JavaScript is involved in either.
 */
klytos_add_filter( 'admin.topbar_actions', static function ( string $html ): string {
    return $html . '<button type="submit" form="k-x402-form"'
        . ' class="k-btn k-btn--primary k-btn--sm"'
        . ' data-testid="x402_settings.save">'
        . klytos_esc_html( __( 'common.save' ) )
        . '</button>';
} );

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.x402_settings.before' ); ?>

<?php if ( $success !== '' ) : ?>
    <?php // §2 Success: "the page reloads with a role="status" line under the H1". ?>
    <p class="k-status-line" role="status" data-testid="x402_settings.status_line">
        <?php echo klytos_esc_html( $success ); ?>
    </p>
<?php endif; ?>

<?php if ( $summaryRows !== [] ) : ?>
    <?php /*
     * §2 Error — form level: an error summary at the top of main, role="alert",
     * focus moved to it on load, listing every failed field as a link to that
     * field. tabindex="-1" makes it focusable without putting a container in
     * the tab order.
     */ ?>
    <div class="k-error-summary"
         id="x402-error-summary"
         role="alert"
         tabindex="-1"
         data-testid="x402_settings.error_summary">
        <h2><?php echo klytos_esc_html( __( 'klytos-x402.summary_title' ) ); ?></h2>
        <ul>
            <?php foreach ( $summaryRows as $index => $row ) : ?>
                <li>
                    <a href="#<?php echo klytos_esc_attr( $row['anchor'] ); ?>"
                       data-testid="x402_settings.error_link.<?php echo (int) $index; ?>">
                        <?php echo klytos_esc_html( $row['message'] ); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php
/*
 * The form the toolbar Save submits carries no cards of its own: one card is a
 * COLLECTION whose rows own their actions, and a form cannot nest in a form. The
 * savable fields associate with this one by `form=` — entry 39's idiom, and the
 * only one that keeps DOM order, focus order and the implicit submit intact.
 */
?>
<form method="post" id="k-x402-form" data-testid="x402_settings.form">
    <?php echo klytos_csrf_field(); ?>
    <input type="hidden" name="action" value="save">
</form>

<?php
/*
 * Entry 37 has no section nav in the manifest, so the template's optional left
 * column is absent from the DOM rather than rendered empty
 * (template-record-form.md §1, "[optional]").
 */
?>
<div class="k-record-form k-record-form--no-nav" data-testid="x402_settings.screen">

    <div class="k-card-stack">

        <?php klytos_do_action( 'admin.x402_settings.before_provider' ); ?>

        <?php // ─── Card 1 — Payment provider ─────────────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="x402-provider-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="x402-provider-heading" tabindex="-1">
                    <?php echo klytos_esc_html( __( 'klytos-x402.card_provider' ) ); ?>
                </h2>

                <?php if ( $allProviders === [] ) : ?>
                    <?php /*
                     * §2 Empty, applied to the card rather than to a collection:
                     * a provider is registered by a plugin, so the sentence names
                     * the action instead of offering an add button this screen
                     * cannot honour.
                     */ ?>
                    <p class="k-empty" data-testid="x402_settings.no_provider">
                        <?php klytos_admin_icon( $spriteUrl, 'ks-extension', 'k-empty-icon' ); ?>
                        <span class="k-empty-text">
                            <?php echo klytos_esc_html( __( 'klytos-x402.no_provider' ) ); ?>
                        </span>
                    </p>
                <?php else : ?>
                    <?php // §4: every radio group is in <fieldset><legend>. ?>
                    <fieldset class="k-field">
                        <legend class="k-label"><?php echo klytos_esc_html( __( 'klytos-x402.provider' ) ); ?></legend>
                        <p class="k-hint" id="x402-hint-provider">
                            <?php echo klytos_esc_html( __( 'klytos-x402.provider_hint' ) ); ?>
                        </p>

                        <?php foreach ( $allProviders as $prov ) : ?>
                            <?php $provId = (string) $prov->getId(); ?>
                            <label class="k-choice k-hit-24" for="x402-provider-<?php echo klytos_esc_attr( $provId ); ?>">
                                <input type="radio"
                                       class="k-radio"
                                       id="x402-provider-<?php echo klytos_esc_attr( $provId ); ?>"
                                       name="provider_id"
                                       form="k-x402-form"
                                       value="<?php echo klytos_esc_attr( $provId ); ?>"
                                       aria-describedby="x402-hint-provider"
                                       <?php echo $provId === (string) $cfg['provider_id'] ? 'checked' : ''; ?>
                                       data-testid="x402_settings.provider.<?php echo klytos_esc_attr( $provId ); ?>">
                                <span>
                                    <?php echo klytos_esc_html( (string) $prov->getLabel() ); ?>
                                    <span class="k-collection-meta">
                                        <?php echo klytos_esc_html( implode( ' · ', $prov->getSupportedNetworks() ) ); ?>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <?php if ( $activeProvider !== null ) : ?>
                        <h3 class="k-label" id="x402-provider-settings-heading">
                            <?php echo klytos_esc_html( __( 'klytos-x402.provider_settings' ) ); ?>
                        </h3>

                        <?php foreach ( $activeProvider->getSettingsFields() as $field ) : ?>
                            <?php
                            $key        = (string) $field['key'];
                            $isSecret   = ( $field['type'] ?? '' ) === 'password';
                            $isRequired = ! empty( $field['required'] );
                            $stored     = (string) ( $cfg['provider_settings'][ $key ] ?? '' );
                            $hintId     = 'x402-hint-provider-' . $key;
                            ?>
                            <div class="k-field">
                                <label class="k-label" for="x402-field-provider-<?php echo klytos_esc_attr( $key ); ?>">
                                    <?php echo klytos_esc_html( (string) $field['label'] ); ?>
                                </label>

                                <input type="<?php echo $isSecret ? 'password' : 'text'; ?>"
                                       class="k-control k-control--mono"
                                       id="x402-field-provider-<?php echo klytos_esc_attr( $key ); ?>"
                                       name="provider_<?php echo klytos_esc_attr( $key ); ?>"
                                       form="k-x402-form"
                                       <?php /*
                                        * A secret is NEVER rendered back. Anything
                                        * else keeps its stored value so a save does
                                        * not silently blank it.
                                        */ ?>
                                       value="<?php echo $isSecret ? '' : klytos_esc_attr( $stored !== '' ? $stored : (string) ( $field['default'] ?? '' ) ); ?>"
                                       spellcheck="false"
                                       autocapitalize="off"
                                       autocomplete="off"
                                       aria-describedby="<?php echo klytos_esc_attr( $hintId ); ?>"
                                       data-testid="x402_settings.provider_field.<?php echo klytos_esc_attr( $key ); ?>">

                                <p class="k-hint" id="<?php echo klytos_esc_attr( $hintId ); ?>">
                                    <?php
                                    $parts = [];

                                    if ( ! empty( $field['description'] ) ) {
                                        $parts[] = (string) $field['description'];
                                    }

                                    if ( $isSecret ) {
                                        // Whether a secret is stored is the one
                                        // thing the field can no longer show, so
                                        // the hint says it in words.
                                        $parts[] = $stored !== ''
                                            ? __( 'klytos-x402.secret_stored' )
                                            : __( 'klytos-x402.secret_absent' );
                                    }

                                    if ( $isRequired ) {
                                        // §4: the `required` attribute plus the WORD
                                        // Required in the hint. Never an asterisk
                                        // alone — and the attribute is deliberately
                                        // absent on a stored secret, whose blank
                                        // field means "keep".
                                        $parts[] = __( 'common.required' );
                                    }

                                    echo klytos_esc_html( implode( ' ', $parts ) );
                                    ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>

        <?php klytos_do_action( 'admin.x402_settings.after_provider' ); ?>

        <?php // ─── Card 2 — Wallet and pricing ───────────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="x402-wallet-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="x402-wallet-heading">
                    <?php echo klytos_esc_html( __( 'klytos-x402.card_wallet' ) ); ?>
                </h2>

                <div class="k-field">
                    <label class="k-label" for="x402-field-wallet_address">
                        <?php echo klytos_esc_html( __( 'klytos-x402.wallet_address' ) ); ?>
                    </label>

                    <?php /*
                     * ADAPTATION — the manifest says "Wallet (read-only mono +
                     * copy)". Mono and copy are built. Read-only is NOT: this
                     * screen is the only affordance in the product that sets the
                     * address, so making it read-only would leave the value
                     * unreachable by any surface, and removing shipped capability
                     * is not a fidelity decision (D-075). The design's intent —
                     * an address you can read and copy exactly — is met.
                     */ ?>
                    <input type="text"
                           class="k-control k-control--mono"
                           id="x402-field-wallet_address"
                           name="wallet_address"
                           form="k-x402-form"
                           value="<?php echo klytos_esc_attr( (string) $cfg['wallet_address'] ); ?>"
                           spellcheck="false"
                           autocapitalize="off"
                           autocomplete="off"
                           aria-describedby="x402-hint-wallet_address"
                           data-testid="x402_settings.wallet_address">

                    <p class="k-hint" id="x402-hint-wallet_address">
                        <?php echo klytos_esc_html( __( 'klytos-x402.wallet_address_desc' ) ); ?>
                    </p>

                    <div class="k-card-footer">
                        <button type="button"
                                class="k-btn k-btn--secondary k-btn--sm"
                                id="x402-copy-wallet"
                                data-copies="x402-field-wallet_address"
                                data-testid="x402_settings.copy_wallet">
                            <?php echo klytos_esc_html( __( 'klytos-x402.wallet_copy' ) ); ?>
                        </button>
                    </div>
                    <p class="k-hint" id="x402-wallet-copied" role="status" data-testid="x402_settings.wallet_copied"></p>
                </div>

                <div class="k-field-grid k-field-grid--pair">
                    <div class="k-field">
                        <label class="k-label" for="x402-field-network">
                            <?php echo klytos_esc_html( __( 'klytos-x402.network' ) ); ?>
                        </label>
                        <select class="k-control"
                                id="x402-field-network"
                                name="network"
                                form="k-x402-form"
                                aria-describedby="x402-hint-network"
                                data-testid="x402_settings.network">
                            <?php
                            $currentNetwork = (string) ( $cfg['network'] ?? 'base' );

                            // A stored network the active provider no longer
                            // supports is still OFFERED rather than silently
                            // rewritten to the first option by the next save —
                            // D-096's default-language defect, avoided here.
                            $options = $networks;
                            if ( $currentNetwork !== '' && ! in_array( $currentNetwork, $options, true ) ) {
                                array_unshift( $options, $currentNetwork );
                            }

                            foreach ( $options as $net ) :
                                ?>
                                <option value="<?php echo klytos_esc_attr( $net ); ?>"
                                    <?php echo $net === $currentNetwork ? 'selected' : ''; ?>>
                                    <?php echo klytos_esc_html( $net ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="k-hint" id="x402-hint-network">
                            <?php echo klytos_esc_html( __( 'klytos-x402.network_hint' ) ); ?>
                        </p>
                    </div>

                    <div class="k-field">
                        <label class="k-label" for="x402-field-default_price_usd">
                            <?php echo klytos_esc_html( __( 'klytos-x402.default_price' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="x402-field-default_price_usd"
                               name="default_price_usd"
                               form="k-x402-form"
                               value="<?php echo klytos_esc_attr( (string) $cfg['default_price_usd'] ); ?>"
                               inputmode="decimal"
                               spellcheck="false"
                               autocapitalize="off"
                               aria-describedby="x402-hint-default_price_usd<?php echo isset( $fieldErrors['default_price_usd'] ) ? ' x402-error-default_price_usd' : ''; ?>"
                               <?php echo isset( $fieldErrors['default_price_usd'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="x402_settings.default_price">
                        <p class="k-hint" id="x402-hint-default_price_usd">
                            <?php echo klytos_esc_html( __( 'klytos-x402.default_price_hint' ) . ' ' . __( 'common.required' ) ); ?>
                        </p>
                        <?php if ( isset( $fieldErrors['default_price_usd'] ) ) : ?>
                            <p class="k-error" id="x402-error-default_price_usd">
                                <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                <?php echo klytos_esc_html( $fieldErrors['default_price_usd'] ); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <?php // ─── Card 3 — Licence ──────────────────────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="x402-license-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="x402-license-heading">
                    <?php echo klytos_esc_html( __( 'klytos-x402.card_license' ) ); ?>
                </h2>

                <div class="k-field">
                    <label class="k-label" for="x402-field-license_type">
                        <?php echo klytos_esc_html( __( 'klytos-x402.license_type' ) ); ?>
                    </label>
                    <select class="k-control"
                            id="x402-field-license_type"
                            name="license_type"
                            form="k-x402-form"
                            aria-describedby="x402-hint-license_type"
                            data-testid="x402_settings.license_type">
                        <?php $currentType = (string) ( $cfg['license']['default_type'] ?? 'inference' ); ?>
                        <?php foreach ( $licenseTypes as $value => $labelKey ) : ?>
                            <option value="<?php echo klytos_esc_attr( $value ); ?>"
                                <?php echo $value === $currentType ? 'selected' : ''; ?>>
                                <?php echo klytos_esc_html( __( $labelKey ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="k-hint" id="x402-hint-license_type">
                        <?php echo klytos_esc_html( __( 'klytos-x402.license_type_hint' ) ); ?>
                    </p>
                </div>

                <div class="k-field">
                    <label class="k-label" for="x402-field-license_text">
                        <?php echo klytos_esc_html( __( 'klytos-x402.license_text' ) ); ?>
                    </label>
                    <textarea class="k-control"
                              id="x402-field-license_text"
                              name="license_text"
                              form="k-x402-form"
                              rows="3"
                              aria-describedby="x402-hint-license_text"
                              data-testid="x402_settings.license_text"><?php
                                echo klytos_esc_textarea( (string) ( $cfg['license']['default_text'] ?? '' ) );
                                ?></textarea>
                    <p class="k-hint" id="x402-hint-license_text">
                        <?php echo klytos_esc_html( __( 'klytos-x402.license_text_hint' ) ); ?>
                    </p>
                </div>
            </div>
        </section>

        <?php klytos_do_action( 'admin.x402_settings.before_agents' ); ?>

        <?php // ─── Card 4 — Who pays ─────────────────────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="x402-agents-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="x402-agents-heading">
                    <?php echo klytos_esc_html( __( 'klytos-x402.card_who_pays' ) ); ?>
                </h2>
                <p class="k-hint"><?php echo klytos_esc_html( __( 'klytos-x402.who_pays_intro' ) ); ?></p>

                <?php /*
                 * §2 "Read-only vs disabled": the built-in list is a value the
                 * person may copy but not change, so it is `readonly`, mono and
                 * selectable — never `disabled`, which the shipped screen used
                 * and which takes it out of the tab order and out of reach of a
                 * screen reader's browse mode.
                 */ ?>
                <div class="k-field">
                    <label class="k-label" for="x402-field-known_agents">
                        <?php echo klytos_esc_html( __( 'klytos-x402.bot_user_agents' ) ); ?>
                    </label>
                    <textarea class="k-control k-control--mono"
                              id="x402-field-known_agents"
                              rows="4"
                              readonly
                              aria-describedby="x402-hint-known_agents"
                              data-testid="x402_settings.known_agents"><?php
                                echo klytos_esc_textarea( implode( "\n", $knownAgents ) );
                                ?></textarea>
                    <p class="k-hint" id="x402-hint-known_agents">
                        <?php echo klytos_esc_html( __( 'klytos-x402.bot_user_agents_hint' ) ); ?>
                    </p>
                </div>

                <h3 class="k-label" id="x402-custom-agents-heading">
                    <?php echo klytos_esc_html( __( 'klytos-x402.custom_agents' ) ); ?>
                </h3>

                <ul class="k-collection"
                    aria-labelledby="x402-custom-agents-heading"
                    data-testid="x402_settings.custom_agents">
                    <?php if ( $customAgents === [] ) : ?>
                        <?php /*
                         * §2 Empty: "a collection inside a form can be empty …
                         * that collection renders one row: the sentence and the
                         * add action, inside the card, keeping the card's
                         * heading." The add action is the field below, which is
                         * always present, so the row is the sentence.
                         */ ?>
                        <li class="k-collection-row" data-testid="x402_settings.custom_agents_empty">
                            <p class="k-empty">
                                <?php klytos_admin_icon( $spriteUrl, 'ks-smart_toy', 'k-empty-icon' ); ?>
                                <span class="k-empty-text">
                                    <?php echo klytos_esc_html( __( 'klytos-x402.custom_agents_empty' ) ); ?>
                                </span>
                            </p>
                        </li>
                    <?php else : ?>
                        <?php foreach ( $customAgents as $agent ) : ?>
                            <li class="k-collection-row">
                                <div class="k-collection-main">
                                    <span class="k-collection-title k-control--mono">
                                        <?php echo klytos_esc_html( (string) $agent ); ?>
                                    </span>
                                </div>
                                <div class="k-collection-actions">
                                    <?php /*
                                     * Its own form, posting. Removing an agent is
                                     * not destructive to stored data — it stops a
                                     * crawler being charged — so §2's two-step
                                     * confirm does not apply and a single button
                                     * is the honest control.
                                     */ ?>
                                    <form method="post">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="action" value="remove_agent">
                                        <input type="hidden" name="agent" value="<?php echo klytos_esc_attr( (string) $agent ); ?>">
                                        <button type="submit"
                                                class="k-btn k-btn--secondary k-btn--sm"
                                                data-testid="x402_settings.remove_agent.<?php echo klytos_esc_attr( (string) $agent ); ?>">
                                            <?php
                                            // The accessible name names the ROW, so
                                            // a list of identical "Remove" buttons
                                            // is not what assistive technology
                                            // hears.
                                            echo klytos_esc_html( __( 'klytos-x402.remove_agent', [ 'agent' => (string) $agent ] ) );
                                            ?>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <form method="post" class="k-collection-add" data-testid="x402_settings.add_agent_form">
                    <?php echo klytos_csrf_field(); ?>
                    <input type="hidden" name="action" value="add_agent">

                    <div class="k-field">
                        <label class="k-label" for="x402-field-agent">
                            <?php echo klytos_esc_html( __( 'klytos-x402.add_agent_label' ) ); ?>
                        </label>
                        <input type="text"
                               class="k-control k-control--mono"
                               id="x402-field-agent"
                               name="agent"
                               value=""
                               spellcheck="false"
                               autocapitalize="off"
                               autocomplete="off"
                               aria-describedby="x402-hint-agent<?php echo isset( $fieldErrors['agent'] ) ? ' x402-error-agent' : ''; ?>"
                               <?php echo isset( $fieldErrors['agent'] ) ? 'aria-invalid="true"' : ''; ?>
                               data-testid="x402_settings.agent_input">
                        <p class="k-hint" id="x402-hint-agent">
                            <?php echo klytos_esc_html( __( 'klytos-x402.add_agent_hint' ) ); ?>
                        </p>
                        <?php if ( isset( $fieldErrors['agent'] ) ) : ?>
                            <p class="k-error" id="x402-error-agent">
                                <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                <?php echo klytos_esc_html( $fieldErrors['agent'] ); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="k-collection-add-actions">
                        <button type="submit"
                                class="k-btn k-btn--secondary k-btn--sm"
                                data-testid="x402_settings.add_agent">
                            <?php echo klytos_esc_html( __( 'klytos-x402.add_agent' ) ); ?>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <?php // ─── Card 5 — Logging and statistics ───────────────── ?>
        <section class="k-card k-card--padded" aria-labelledby="x402-logging-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="x402-logging-heading">
                    <?php echo klytos_esc_html( __( 'klytos-x402.card_logging' ) ); ?>
                </h2>

                <?php /*
                 * Checkboxes and not switches, deliberately: §4 says a control
                 * that takes effect immediately is `role="switch"` and one that
                 * needs Save is a checkbox. Both of these need Save, and §37's
                 * own delta says "everything else is checkbox + Save".
                 */ ?>
                <fieldset class="k-field">
                    <legend class="k-label"><?php echo klytos_esc_html( __( 'klytos-x402.card_logging' ) ); ?></legend>

                    <label class="k-choice k-hit-24" for="x402-field-logging_enabled">
                        <input type="checkbox"
                               class="k-check"
                               id="x402-field-logging_enabled"
                               name="logging_enabled"
                               form="k-x402-form"
                               value="1"
                               aria-describedby="x402-hint-logging_enabled"
                               <?php echo ! empty( $cfg['logging_enabled'] ) ? 'checked' : ''; ?>
                               data-testid="x402_settings.logging_enabled">
                        <span><?php echo klytos_esc_html( __( 'klytos-x402.logging' ) ); ?></span>
                    </label>
                    <p class="k-hint" id="x402-hint-logging_enabled">
                        <?php echo klytos_esc_html( __( 'klytos-x402.logging_hint' ) ); ?>
                    </p>

                    <label class="k-choice k-hit-24" for="x402-field-stats_enabled">
                        <input type="checkbox"
                               class="k-check"
                               id="x402-field-stats_enabled"
                               name="stats_enabled"
                               form="k-x402-form"
                               value="1"
                               aria-describedby="x402-hint-stats_enabled"
                               <?php echo ! empty( $cfg['stats_enabled'] ) ? 'checked' : ''; ?>
                               data-testid="x402_settings.stats_enabled">
                        <span><?php echo klytos_esc_html( __( 'klytos-x402.stats_toggle' ) ); ?></span>
                    </label>
                    <p class="k-hint" id="x402-hint-stats_enabled">
                        <?php echo klytos_esc_html( __( 'klytos-x402.stats_hint' ) ); ?>
                    </p>
                </fieldset>
            </div>
        </section>

    </div>
</div>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function () {
    'use strict';

    /*
     * The copy button is progressive enhancement and nothing else: with this
     * script absent the wallet field is still labelled, still selectable and
     * still what posts. The outcome is announced in the page's own status
     * region rather than in an alert() (accessibility.md §2's rule about
     * browser dialogs, applied to its siblings — D-091).
     */
    var button = document.getElementById('x402-copy-wallet');
    var status = document.getElementById('x402-wallet-copied');

    var copiedText = <?php echo json_encode( __( 'klytos-x402.wallet_copied' ), JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
    var failedText = <?php echo json_encode( __( 'klytos-x402.wallet_copy_failed' ), JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
    var manualText = <?php echo json_encode( __( 'klytos-x402.wallet_copy_manual' ), JSON_HEX_TAG | JSON_HEX_AMP ); ?>;

    if (button && status) {
        button.addEventListener('click', function () {
            var field = document.getElementById(button.getAttribute('data-copies'));
            if (!field) {
                return;
            }

            field.select();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(field.value).then(function () {
                    status.textContent = copiedText;
                }, function () {
                    status.textContent = failedText;
                });
                return;
            }

            status.textContent = manualText;
        });
    }

    /*
     * §2 Error — form level: "focus moved to it on load". The summary is only
     * in the DOM when there is one.
     */
    var summary = document.getElementById('x402-error-summary');
    if (summary) {
        summary.focus();
    }
})();
</script>

<?php klytos_do_action( 'admin.x402_settings.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
