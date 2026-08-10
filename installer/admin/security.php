<?php

/**
 * Klytos Admin — Security
 *
 * Manifest entry 6 · template `record-form` · H1 **Security**.
 * Built in Phase 4 Step 4, stage 5 (the form screens), batch B, screen 3,
 * against `SPEC/screens/template-record-form.md` and `SPEC/manifest.md` §6.
 *
 * The manifest lists FIVE cards — Two-factor · Passkeys · Content-Security-Policy
 * · Integrity score · Recovery codes. THREE are built here. What is not, and why
 * (both deferred under D-088 answer 1 and recorded in `docs/roadmap.md` §0c —
 * they stay manifest rows, and the redesign is not reportable as complete while
 * they stand):
 *
 *   - **Content-Security-Policy** — Klytos SENDS a CSP (`Auth::sendSecurityHeaders()`
 *     with a per-request nonce); it has no editor for one and no store for a
 *     directive set. A textarea with a validate action over a live security
 *     header is a slice with its own failure modes, not a card.
 *
 *   - **Integrity score** — the data lives on manifest entry 34 (System
 *     integrity). A score summarised onto this screen needs a source that
 *     summarises, and none exists: `IntegrityManager` reports per-file verdicts,
 *     not a figure.
 *
 * THREE cards the manifest's list does NOT name are built here anyway, because
 * they are shipped product and this screen is their only surface — removing
 * shipped behaviour is not a fidelity decision (D-075, D-079, and the standing
 * rule since): **Encryption level**, **Recovery keys** and the destructive
 * **Turn off two-factor authentication**. All three are gated exactly as they
 * were; nothing gains or loses access here.
 *
 * Two adaptations with reasons, both logged in `docs/BUILD-SPEC.md` §5.9:
 *
 *   - **The re-auth step §6's delta requires is built as a server-side second
 *     step**, and it doubles as §2's destructive confirm. The switch posts, the
 *     card re-renders asking for the current password, and the second post
 *     applies the change. It behaves identically with JavaScript disabled, and
 *     it is the same shape as the two-step delete D-089 established. Turning
 *     TOTP ON is the one toggle with no password step: its confirmation is the
 *     enrolment ceremony itself, which proves possession of the authenticator —
 *     a strictly stronger proof than the password, asked in the same breath.
 *
 *   - **The Passkeys card is a COLLECTION, not a switch.** §6 says "2FA and
 *     passkey controls are switches", and a switch restores what it turns off.
 *     A passkey is created by an authenticator ceremony and destroyed by
 *     `removePasskey()`; there is no state an "off" position could put back, and
 *     an account may hold several. So each row carries a destructive action
 *     behind the same re-auth step, and adding one is the WebAuthn ceremony.
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

$pageTitle = __( 'security.title' );

$auth        = $app->getAuth();
$twoFactor   = $app->getTwoFactor();
$userManager = $app->getUserManager();

$username = $auth->getUsername();

$success = '';

/** @var array<string,string> Field-level errors, keyed by control name. */
$fieldErrors = [];
/** @var array<int,array{name:string,message:string}> The error summary's rows, in DOM order. */
$summaryRows = [];

/**
 * The recovery codes to show ONCE, immediately after they are generated.
 *
 * `generateRecoveryCodes()` stores hashes; the plaintext exists only in this
 * response. The card says so before the codes appear, not after.
 *
 * @var array<int,string>|null
 */
$recoveryCodes = null;

/**
 * The toggle waiting for its password, if any.
 *
 * §6's delta: "each confirmed by a re-auth step". Implemented entirely on the
 * SERVER — the switch posts, the card re-renders with the password field, the
 * second post applies it — so it behaves identically with JavaScript disabled,
 * exactly like the two-step delete in D-089. It is also §2's destructive
 * confirm for `disable_all`: one mechanism, not two.
 *
 * @var array{for:string,credential_id:string}|null
 */
$pendingReauth = null;

/**
 * Every toggle the re-auth step is allowed to apply, and the sentence each one
 * states before it is applied.
 *
 * An allow-list rather than a dispatch on whatever `reauth_for` arrives: the
 * value comes from a form field, and a switch statement over an unbounded string
 * is one refactor away from reaching a branch nobody meant to expose.
 *
 * @var array<string,string>
 */
const KLYTOS_SECURITY_REAUTH_ACTIONS = [
    'totp_disable'        => 'security.reauth_what_totp_disable',
    'email_enable'        => 'security.reauth_what_email_enable',
    'email_disable'       => 'security.reauth_what_email_disable',
    'remove_passkey'      => 'security.reauth_what_remove_passkey',
    'regenerate_recovery' => 'security.reauth_what_regenerate_recovery',
    'disable_all'         => 'security.reauth_what_disable_all',
];

/*
 * Resolve the acting user through the manager rather than by scanning the
 * users collection here. The screen this replaces walked `list('users')` by
 * hand for the id; `getByUsername()` is the documented surface for exactly
 * that, already used by `admin/profile.php`, and it sanitizes the record on
 * the way out so no hash reaches this file at all.
 */
$user   = $userManager->getByUsername( $username );
$userId = $user['id'] ?? null;

if ( ! $userId ) {
    // Not a field error and not a validation failure: the session names a user
    // the store does not have. §2's "the save failed for a server reason" is
    // the right shape — the summary names the cause, never a code alone.
    $summaryRows[] = [ 'name' => '', 'message' => __( 'security.error_user_missing' ) ];
}

$tfConfig = $userId ? $twoFactor->getUserConfig( $userId ) : [];

// ─── Handle POST ────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() && $userId ) {
    $action = (string) ( $_POST['action'] ?? '' );

    try {
        if ( $action === 'totp_setup' ) {
            /*
             * Turning the authenticator ON. This is the one toggle with no
             * password step, because the enrolment ceremony below IS the
             * confirmation: a code from the authenticator proves possession,
             * which is a stronger claim than the password and is asked for in
             * the same interaction.
             */
            $secret = $twoFactor->generateTotpSecret();
            $_SESSION['klytos_totp_setup_secret'] = $secret;

            $siteConfig = $app->getSiteConfig()->get();
            $_SESSION['klytos_totp_setup_uri'] = $twoFactor->getTotpUri(
                $secret,
                (string) ( $user['email'] ?? $username ),
                (string) ( $siteConfig['site_name'] ?? 'Klytos' )
            );
        } elseif ( $action === 'totp_cancel' ) {
            unset( $_SESSION['klytos_totp_setup_secret'], $_SESSION['klytos_totp_setup_uri'] );
        } elseif ( $action === 'totp_verify' ) {
            $secret = (string) ( $_SESSION['klytos_totp_setup_secret'] ?? '' );
            $code   = trim( (string) ( $_POST['totp_code'] ?? '' ) );

            if ( $code === '' ) {
                $fieldErrors['totp_code'] = __( 'security.error_code_required' );
                $summaryRows[]            = [ 'name' => 'totp_code', 'message' => __( 'security.summary_code_required' ) ];
            } elseif ( $secret === '' || ! $twoFactor->verifyTotp( $secret, $code ) ) {
                $fieldErrors['totp_code'] = __( 'security.2fa_invalid_code' );
                $summaryRows[]            = [ 'name' => 'totp_code', 'message' => __( 'security.summary_code_invalid' ) ];
            } else {
                $twoFactor->enableTotp( $userId, $secret );
                unset( $_SESSION['klytos_totp_setup_secret'], $_SESSION['klytos_totp_setup_uri'] );

                // The first factor is what makes recovery codes meaningful, so
                // they are generated with it and shown once, here.
                if ( $twoFactor->countRecoveryCodes( $userId ) === 0 ) {
                    $recoveryCodes = $twoFactor->generateRecoveryCodes( $userId );
                }
                $success = __( 'security.totp_enabled' );
            }
        } elseif ( $action === 'reauth' ) {
            $for = (string) ( $_POST['reauth_for'] ?? '' );
            if ( isset( KLYTOS_SECURITY_REAUTH_ACTIONS[ $for ] ) ) {
                $pendingReauth = [
                    'for'           => $for,
                    'credential_id' => trim( (string) ( $_POST['credential_id'] ?? '' ) ),
                ];
            }
        } elseif ( $action === 'reauth_cancel' ) {
            $pendingReauth = null;
        } elseif ( $action === 'reauth_confirm' ) {
            $for      = (string) ( $_POST['reauth_for'] ?? '' );
            $password = (string) ( $_POST['confirm_password'] ?? '' );
            $credId   = trim( (string) ( $_POST['credential_id'] ?? '' ) );

            if ( ! isset( KLYTOS_SECURITY_REAUTH_ACTIONS[ $for ] ) ) {
                $summaryRows[] = [ 'name' => '', 'message' => __( 'security.error_unknown_action' ) ];
            } elseif ( $password === '' ) {
                $pendingReauth                   = [ 'for' => $for, 'credential_id' => $credId ];
                $fieldErrors['confirm_password'] = __( 'security.error_password_required' );
                $summaryRows[]                   = [
                    'name'    => 'confirm_password',
                    'message' => __( 'security.summary_password_required' ),
                ];
            } elseif ( $userManager->authenticate( $username, $password ) === null ) {
                /*
                 * `authenticate()` and not a hash comparison written here: it is
                 * the same authority the login gate uses (D-056), and it returns
                 * null for EVERY failure — unknown user, suspended account,
                 * wrong password — which is exactly one refusal for the caller
                 * and no account oracle.
                 */
                $pendingReauth                   = [ 'for' => $for, 'credential_id' => $credId ];
                $fieldErrors['confirm_password'] = __( 'security.wrong_password' );
                $summaryRows[]                   = [
                    'name'    => 'confirm_password',
                    'message' => __( 'security.summary_password_wrong' ),
                ];
            } elseif ( $for === 'totp_disable' ) {
                $twoFactor->disableTotp( $userId );
                $success = __( 'security.totp_disabled' );
            } elseif ( $for === 'email_enable' ) {
                $twoFactor->enableMagicLink( $userId );
                if ( $twoFactor->countRecoveryCodes( $userId ) === 0 ) {
                    $recoveryCodes = $twoFactor->generateRecoveryCodes( $userId );
                }
                $success = __( 'security.email_enabled' );
            } elseif ( $for === 'email_disable' ) {
                $twoFactor->disableMagicLink( $userId );
                $success = __( 'security.email_disabled' );
            } elseif ( $for === 'remove_passkey' ) {
                if ( $credId === '' ) {
                    $summaryRows[] = [ 'name' => '', 'message' => __( 'security.error_passkey_missing' ) ];
                } else {
                    $twoFactor->removePasskey( $userId, $credId );
                    $success = __( 'security.passkey_removed' );
                }
            } elseif ( $for === 'regenerate_recovery' ) {
                $recoveryCodes = $twoFactor->generateRecoveryCodes( $userId );
                $success       = __( 'security.recovery_regenerated' );
            } elseif ( $for === 'disable_all' ) {
                $twoFactor->disableAll( $userId );
                $success = __( 'security.2fa_disabled' );
            }
        } elseif ( $action === 'change_encryption_level' ) {
            /*
             * The page is gated at `security.self` — every role may manage their
             * OWN second factor. This action and the two below are site-wide, so
             * they are re-gated here at the higher tier: the gate map entry is
             * the floor, not the ceiling. Unchanged from the screen this
             * replaces (slice 3's S-04 fix); the markup's visibility asks
             * `klytos_has_permission()` for the same capability, so the UI
             * cannot offer an action the gate will refuse.
             */
            klytos_require_permission( 'site.configure' );

            $newLevel    = (string) ( $_POST['new_encryption_level'] ?? '' );
            $confirmPass = (string) ( $_POST['enc_password'] ?? '' );

            if ( ! in_array( $newLevel, [ 'basic', 'medium', 'professional' ], true ) ) {
                $fieldErrors['new_encryption_level'] = __( 'security.invalid_level' );
                $summaryRows[]                        = [
                    'name'    => 'new_encryption_level',
                    'message' => __( 'security.summary_invalid_level' ),
                ];
            } elseif ( $userManager->authenticate( $username, $confirmPass ) === null ) {
                $fieldErrors['enc_password'] = __( 'security.wrong_password' );
                $summaryRows[]               = [
                    'name'    => 'enc_password',
                    'message' => __( 'security.summary_password_wrong' ),
                ];
            } else {
                $app->getStorage()->changeEncryptionLevel( $newLevel );
                $success = __( 'security.level_changed' );
            }
        } elseif ( $action === 'confirm_recovery_keys' ) {
            klytos_require_permission( 'site.configure' );

            $mainConfig                               = $app->getStorage()->readFrom( $app->getConfigPath(), 'config.json.enc' );
            $mainConfig['recovery_keys_confirmed']    = true;
            $mainConfig['recovery_keys_confirmed_at'] = date( 'c' );
            $app->getStorage()->writeTo( $app->getConfigPath(), 'config.json.enc', $mainConfig );

            /*
             * TWO FLAGS RECORDED ONE DUTY, and collapsing the two surfaces onto
             * this card is what makes them one control.
             *
             * `recovery_keys_confirmed` (config.json.enc) drives the shell's
             * red recovery banner; `encryption_key_backed_up` (SiteConfig)
             * drives an UNDISMISSABLE system error notice on every admin page.
             * Settings owned the second and Security the first, so confirming
             * on either screen left the other's warning standing. Now the one
             * confirmation clears both.
             *
             * The SiteConfig write only started working in this same slice:
             * `set()` silently dropped this key on every install, so the notice
             * had never been clearable by any control in the product
             * (SiteConfigSetTest).
             */
            $app->getSiteConfig()->set( ['encryption_key_backed_up' => true] );

            $success = __( 'security.recovery_confirmed' );
        } elseif ( $action === 'generate_identity_keys' ) {
            /*
             * Regenerating the site identity invalidates the previous key pair,
             * so it sits at the same tier as the encryption-level change.
             */
            klytos_require_permission( 'site.configure' );

            $rsaKeys    = \Klytos\Core\Encryption::generateRsaKeyPair();
            $enc        = $app->getStorage()->getEncryption();
            $configPath = $app->getConfigPath();

            $identityPubData = [
                'public_key'  => $rsaKeys['public_key'],
                'fingerprint' => $rsaKeys['fingerprint'],
                'created_at'  => date( 'c' ),
                'admin_user'  => $username,
            ];
            $identityPrivData = [
                'private_key' => $rsaKeys['private_key'],
                'fingerprint' => $rsaKeys['fingerprint'],
                'created_at'  => date( 'c' ),
                'admin_user'  => $username,
            ];

            file_put_contents( $configPath . '/admin-identity.pub.enc', $enc->encrypt( $identityPubData ), LOCK_EX );
            file_put_contents( $configPath . '/admin-identity.priv.enc', $enc->encrypt( $identityPrivData ), LOCK_EX );

            $mainConfig                         = $app->getStorage()->readFrom( $configPath, 'config.json.enc' );
            $mainConfig['identity_fingerprint'] = $rsaKeys['fingerprint'];
            $app->getStorage()->writeTo( $configPath, 'config.json.enc', $mainConfig );

            $success = __( 'security.identity_generated' );
        }
    } catch ( \Throwable $e ) {
        // §2 "Error — the save failed for a server reason": the summary names
        // the cause and the action, never a code alone.
        $summaryRows[] = [ 'name' => '', 'message' => $e->getMessage() ];
    }

    // Re-read the model after any change.
    $tfConfig = $twoFactor->getUserConfig( $userId );
}

// ─── Read the model ─────────────────────────────────────────────

$totpConfigured = (bool) ( $tfConfig['totp_configured'] ?? false );
$emailEnabled   = in_array( 'email', $tfConfig['methods'] ?? [], true );
$twoFactorOn    = (bool) ( $tfConfig['enabled'] ?? false );
$recoveryLeft   = (int) ( $tfConfig['recovery_codes_left'] ?? 0 );

$totpSetupSecret = $_SESSION['klytos_totp_setup_secret'] ?? null;
$totpSetupUri    = $_SESSION['klytos_totp_setup_uri'] ?? null;

/** Third parties extend, filter or annotate the collection from outside. */
$passkeys = klytos_apply_filters( 'admin.security.passkeys', $tfConfig['passkeys'] ?? [], $userId );

/**
 * The second-factor methods the Two-factor card draws, in DOM order.
 *
 * A filter rather than a hardcoded pair: a plugin that adds a second factor has
 * nowhere else to render it, and every other collection on this build is
 * filterable for the same reason.
 *
 * @var array<int,array<string,mixed>>
 */
$methods = klytos_apply_filters( 'admin.security.methods', [
    [
        'id'      => 'totp',
        'label'   => __( 'security.method_totp' ),
        'hint'    => __( 'security.totp_description' ),
        'on'      => $totpConfigured,
        // Turning it ON starts the enrolment ceremony; turning it OFF asks for
        // the password. Two different actions behind one control, which is why
        // the action is part of the row rather than assumed by the renderer.
        'action'  => $totpConfigured ? 'reauth' : 'totp_setup',
        'for'     => 'totp_disable',
        'testid'  => 'security.totp_switch',
    ],
    [
        'id'      => 'email',
        'label'   => __( 'security.method_email' ),
        'hint'    => __( 'security.email_description' ),
        'on'      => $emailEnabled,
        'action'  => 'reauth',
        'for'     => $emailEnabled ? 'email_disable' : 'email_enable',
        'testid'  => 'security.email_switch',
    ],
], $userId );

$canConfigureSite = klytos_has_permission( 'site.configure' );

$encryptionLevel     = 'basic';
$recoveryConfirmed   = false;
$identityFingerprint = null;

/**
 * The master key's material, base64, for the affordance that moved here from
 * Settings with entry 9. Empty string where the file is absent — which is a
 * state the card reports rather than one it hides.
 *
 * Read behind `site.configure` exactly like everything else in this block: the
 * screen itself is gated at `security.self`, which every role holds, and this
 * is the key that decrypts the entire installation.
 *
 * @var string
 */
$encryptionKeyBase64 = '';

if ( $canConfigureSite ) {
    $mainConfig          = $app->getStorage()->readFrom( $app->getConfigPath(), 'config.json.enc' );
    $encryptionLevel     = (string) ( $mainConfig['encryption_level'] ?? 'basic' );
    $recoveryConfirmed   = (bool) ( $mainConfig['recovery_keys_confirmed'] ?? false );
    $identityFingerprint = $mainConfig['identity_fingerprint'] ?? null;

    $encryptionKeyPath = $app->getConfigPath() . '/.encryption_key';
    if ( is_readable( $encryptionKeyPath ) ) {
        $rawKey = file_get_contents( $encryptionKeyPath );
        if ( $rawKey !== false ) {
            $encryptionKeyBase64 = base64_encode( $rawKey );
        }
    }
}

/**
 * A field's `aria-describedby`, hint FIRST and error second (§4).
 *
 * Written once rather than inline per control, so the ORDER — which is the
 * specified part — has exactly one definition.
 */
$describedBy = static function ( string $field ) use ( &$fieldErrors ): string {
    $ids = [ 'security-hint-' . $field ];
    if ( isset( $fieldErrors[ $field ] ) ) {
        $ids[] = 'security-error-' . $field;
    }
    return implode( ' ', $ids );
};

/** The sections the nav links to, in DOM order (§4: focus order is DOM order). */
$sections = [
    'two-factor'     => 'security.card_two_factor',
    'passkeys'       => 'security.card_passkeys',
    'recovery-codes' => 'security.card_recovery_codes',
];
if ( $canConfigureSite ) {
    $sections['encryption']    = 'security.card_encryption';
    $sections['recovery-keys'] = 'security.card_recovery_keys';
}
if ( $twoFactorOn ) {
    // §2: the destructive section is always the LAST card.
    $sections['turn-off'] = 'security.card_turn_off';
}

$csrf = $auth->getCsrfToken();

/*
 * template-record-form.md §1: "The primary Save lives in the TOOLBAR … and it is
 * the same button on every form screen."
 *
 * It is deliberately ABSENT here, and that is an adaptation rather than an
 * omission (§5.9). Every control on this screen takes effect immediately —
 * §6's delta says so in as many words, and §4 defines exactly that as the
 * switch idiom — so there is no pending form state for a Save to submit. A
 * toolbar Save here would post nothing, and a control that lies about what it
 * does is worse than a control that is absent (D-089's rule, earned on entry
 * 19). The one card with a Save of its own is Encryption level, whose Save
 * sits in that card's footer because it submits that card's two fields and
 * nothing else.
 */

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.security.before', $userId ); ?>

<?php if ( $success !== '' ) : ?>
    <?php // §2 Success: "the page reloads with a role="status" line under the H1". ?>
    <p class="k-status-line" role="status" data-testid="security.status_line">
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
         id="security-error-summary"
         role="alert"
         tabindex="-1"
         data-testid="security.error_summary">
        <h2><?php echo klytos_esc_html( __( 'security.summary_title' ) ); ?></h2>
        <ul>
            <?php foreach ( $summaryRows as $index => $row ) : ?>
                <li>
                    <?php if ( $row['name'] !== '' ) : ?>
                        <a href="#security-field-<?php echo klytos_esc_attr( $row['name'] ); ?>"
                           data-testid="security.error_link.<?php echo (int) $index; ?>">
                            <?php echo klytos_esc_html( $row['message'] ); ?>
                        </a>
                    <?php else : ?>
                        <?php // A server-side failure has no field to jump to. ?>
                        <?php echo klytos_esc_html( $row['message'] ); ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="k-record-form" data-testid="security.screen">

    <?php // §4: section nav is <nav aria-label>; the current section is aria-current="page". ?>
    <nav class="k-section-nav"
         aria-label="<?php echo klytos_esc_attr( __( 'security.sections_label' ) ); ?>"
         data-testid="security.section_nav">
        <?php $firstSection = true; ?>
        <?php foreach ( $sections as $anchor => $labelKey ) : ?>
            <a class="k-section-nav-item"
               href="#security-<?php echo klytos_esc_attr( $anchor ); ?>"
               <?php echo $firstSection ? 'aria-current="page"' : ''; ?>
               data-testid="security.section.<?php echo klytos_esc_attr( $anchor ); ?>">
                <?php echo klytos_esc_html( __( $labelKey ) ); ?>
            </a>
            <?php $firstSection = false; ?>
        <?php endforeach; ?>
    </nav>

    <div class="k-card-stack">

        <?php klytos_do_action( 'admin.security.before_2fa', $tfConfig, $userId ); ?>

        <?php // ─── Card 1 — Two-factor ──────────────────────────── ?>
        <section class="k-card k-card--padded"
                 id="security-two-factor"
                 aria-labelledby="security-two-factor-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="security-two-factor-heading">
                    <?php echo klytos_esc_html( __( 'security.card_two_factor' ) ); ?>
                </h2>

                <p class="k-hint" id="security-hint-two-factor">
                    <?php echo klytos_esc_html( __( 'security.2fa_description' ) ); ?>
                </p>

                <p>
                    <?php if ( $twoFactorOn ) : ?>
                        <span class="k-badge k-badge--exito" data-testid="security.status_badge">
                            <?php echo klytos_esc_html( __( 'security.2fa_active' ) ); ?>
                        </span>
                    <?php else : ?>
                        <span class="k-badge k-badge--aviso" data-testid="security.status_badge">
                            <?php echo klytos_esc_html( __( 'security.2fa_inactive' ) ); ?>
                        </span>
                    <?php endif; ?>
                </p>

                <?php foreach ( $methods as $method ) : ?>
                    <?php
                    $methodId = (string) ( $method['id'] ?? '' );
                    $isOn     = (bool) ( $method['on'] ?? false );
                    $armed    = $pendingReauth !== null && $pendingReauth['for'] === (string) ( $method['for'] ?? '' );
                    ?>
                    <div class="k-field">
                        <?php /*
                         * §4 "Switch vs checkbox": a control that takes effect
                         * immediately is role="switch". It is a SUBMIT button,
                         * so the effect is a post — no script is involved, and
                         * the control works with JavaScript disabled.
                         */ ?>
                        <form method="post" class="k-switch-row">
                            <?php echo klytos_csrf_field(); ?>
                            <input type="hidden" name="action" value="<?php echo klytos_esc_attr( (string) $method['action'] ); ?>">
                            <input type="hidden" name="reauth_for" value="<?php echo klytos_esc_attr( (string) $method['for'] ); ?>">
                            <span class="k-label" id="security-label-<?php echo klytos_esc_attr( $methodId ); ?>">
                                <?php echo klytos_esc_html( (string) $method['label'] ); ?>
                            </span>
                            <button type="submit"
                                    role="switch"
                                    class="k-switch"
                                    aria-checked="<?php echo $isOn ? 'true' : 'false'; ?>"
                                    aria-labelledby="security-label-<?php echo klytos_esc_attr( $methodId ); ?>"
                                    aria-describedby="security-hint-<?php echo klytos_esc_attr( $methodId ); ?>"
                                    data-testid="<?php echo klytos_esc_attr( (string) $method['testid'] ); ?>">
                                <span class="k-switch-thumb"></span>
                            </button>
                        </form>
                        <p class="k-hint" id="security-hint-<?php echo klytos_esc_attr( $methodId ); ?>">
                            <?php echo klytos_esc_html( (string) $method['hint'] ); ?>
                        </p>

                        <?php if ( $armed ) : ?>
                            <?php
                            $reauthFor    = $pendingReauth['for'];
                            $reauthCredId = $pendingReauth['credential_id'];
                            require __DIR__ . '/partials/security-reauth.php';
                            ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if ( $totpSetupSecret && ! $totpConfigured ) : ?>
                    <?php /*
                     * The enrolment ceremony — the "re-auth step" for turning
                     * the authenticator ON. Proving possession of the
                     * authenticator is a stronger claim than the password, and
                     * it is asked for in the same interaction.
                     */ ?>
                    <div class="k-field" id="security-enrolment" data-testid="security.enrolment">
                        <h3 class="k-label"><?php echo klytos_esc_html( __( 'security.enrolment_title' ) ); ?></h3>
                        <p class="k-hint"><?php echo klytos_esc_html( __( 'security.totp_scan_qr' ) ); ?></p>
                        <div id="klytos-qr-code" data-testid="security.qr"></div>
                        <p class="k-hint"><?php echo klytos_esc_html( __( 'security.totp_manual_key' ) ); ?></p>
                        <p><code class="k-code-key" data-testid="security.totp_secret"><?php echo klytos_esc_html( $totpSetupSecret ); ?></code></p>

                        <form method="post">
                            <?php echo klytos_csrf_field(); ?>
                            <input type="hidden" name="action" value="totp_verify">
                            <label class="k-label" for="security-field-totp_code">
                                <?php echo klytos_esc_html( __( 'security.enter_totp_code' ) ); ?>
                            </label>
                            <input type="text"
                                   class="k-control k-control--mono"
                                   id="security-field-totp_code"
                                   name="totp_code"
                                   maxlength="6"
                                   inputmode="numeric"
                                   autocomplete="one-time-code"
                                   spellcheck="false"
                                   autocapitalize="off"
                                   required
                                   aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'totp_code' ) ); ?>"
                                   <?php echo isset( $fieldErrors['totp_code'] ) ? 'aria-invalid="true"' : ''; ?>
                                   data-testid="security.totp_code">
                            <p class="k-hint" id="security-hint-totp_code">
                                <?php echo klytos_esc_html( __( 'security.hint_totp_code' ) ); ?>
                            </p>
                            <?php if ( isset( $fieldErrors['totp_code'] ) ) : ?>
                                <p class="k-error" id="security-error-totp_code">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors['totp_code'] ); ?>
                                </p>
                            <?php endif; ?>
                            <div class="k-collection-add-actions">
                                <button type="submit" class="k-btn k-btn--primary k-btn--sm" data-testid="security.totp_verify">
                                    <?php echo klytos_esc_html( __( 'security.verify_and_enable' ) ); ?>
                                </button>
                            </div>
                        </form>

                        <form method="post">
                            <?php echo klytos_csrf_field(); ?>
                            <input type="hidden" name="action" value="totp_cancel">
                            <button type="submit" class="k-btn k-btn--secondary k-btn--sm" data-testid="security.totp_cancel">
                                <?php echo klytos_esc_html( __( 'common.cancel' ) ); ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php klytos_do_action( 'admin.security.before_passkeys', $passkeys, $userId ); ?>

        <?php // ─── Card 2 — Passkeys ────────────────────────────── ?>
        <section class="k-card k-card--padded"
                 id="security-passkeys"
                 aria-labelledby="security-passkeys-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="security-passkeys-heading">
                    <?php echo klytos_esc_html( __( 'security.card_passkeys' ) ); ?>
                </h2>
                <p class="k-hint"><?php echo klytos_esc_html( __( 'security.passkey_description' ) ); ?></p>

                <?php if ( $passkeys === [] ) : ?>
                    <?php // §2 Empty — "a collection inside a form can be". ?>
                    <p class="k-empty" data-testid="security.passkeys_empty">
                        <?php klytos_admin_icon( $spriteUrl, 'ks-fingerprint', 'k-empty-icon' ); ?>
                        <span class="k-empty-text">
                            <?php echo klytos_esc_html( __( 'security.passkeys_empty_sentence' ) ); ?>
                        </span>
                    </p>
                <?php else : ?>
                    <ul class="k-collection" data-testid="security.passkeys">
                        <?php foreach ( $passkeys as $pk ) : ?>
                            <?php
                            $credId  = (string) ( $pk['credential_id'] ?? '' );
                            $isArmed = $pendingReauth !== null
                                && $pendingReauth['for'] === 'remove_passkey'
                                && $pendingReauth['credential_id'] === $credId;

                            /*
                             * Stored UTC, shown local — the project's standing
                             * rule. `klytos_format_datetime()` parses the ISO
                             * string the record holds; `klytos_date()` takes a
                             * timestamp and would have needed a conversion
                             * first. Both sentences are built here rather than
                             * inline, so the markup below stays readable.
                             */
                            $createdLine = __(
                                'security.passkey_created',
                                [ 'date' => klytos_format_datetime( (string) ( $pk['created_at'] ?? '' ), 'Y-m-d H:i' ) ]
                            );
                            $usedLine = empty( $pk['last_used'] )
                                ? __( 'security.passkey_never_used' )
                                : __(
                                    'security.passkey_last_used',
                                    [ 'date' => klytos_format_datetime( (string) $pk['last_used'], 'Y-m-d H:i' ) ]
                                );
                            ?>
                            <li class="k-collection-row">
                                <div class="k-collection-main">
                                    <span class="k-collection-title">
                                        <?php echo klytos_esc_html( (string) ( $pk['label'] ?? $credId ) ); ?>
                                    </span>
                                    <span class="k-collection-meta">
                                        <span><?php echo klytos_esc_html( $createdLine ); ?></span>
                                        <span><?php echo klytos_esc_html( $usedLine ); ?></span>
                                    </span>
                                </div>

                                <div class="k-collection-actions">
                                    <form method="post" class="k-confirm-wrap">
                                        <?php echo klytos_csrf_field(); ?>
                                        <input type="hidden" name="action" value="reauth">
                                        <input type="hidden" name="reauth_for" value="remove_passkey">
                                        <input type="hidden" name="credential_id" value="<?php echo klytos_esc_attr( $credId ); ?>">
                                        <button type="submit"
                                                class="k-btn k-btn--secondary k-btn--sm"
                                                data-testid="security.passkey_remove.<?php echo klytos_esc_attr( $credId ); ?>">
                                            <?php echo klytos_esc_html( __( 'common.delete' ) ); ?>
                                        </button>
                                    </form>
                                </div>

                                <?php if ( $isArmed ) : ?>
                                    <?php
                                    $reauthFor    = 'remove_passkey';
                                    $reauthCredId = $credId;
                                    require __DIR__ . '/partials/security-reauth.php';
                                    ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php /*
                 * Adding a passkey is a WebAuthn ceremony and cannot happen
                 * without script — `navigator.credentials` is the whole
                 * mechanism. So the control is HIDDEN until the script that
                 * gives it its behaviour has run, and a browser without
                 * WebAuthn sees the sentence instead of a button that does
                 * nothing (§2's rule about controls, applied to the one place
                 * on this screen where JavaScript is not an enhancement).
                 */ ?>
                <div class="k-field" id="security-passkey-add" hidden>
                    <label class="k-label" for="security-field-passkey_label">
                        <?php echo klytos_esc_html( __( 'security.passkey_name_label' ) ); ?>
                    </label>
                    <input type="text"
                           class="k-control"
                           id="security-field-passkey_label"
                           value="<?php echo klytos_esc_attr( __( 'security.passkey_default_name' ) ); ?>"
                           maxlength="60"
                           autocomplete="off"
                           aria-describedby="security-hint-passkey_label"
                           data-testid="security.passkey_label">
                    <p class="k-hint" id="security-hint-passkey_label">
                        <?php echo klytos_esc_html( __( 'security.passkey_name_hint' ) ); ?>
                    </p>
                    <div class="k-collection-add-actions">
                        <button type="button"
                                class="k-btn k-btn--primary k-btn--sm"
                                id="security-passkey-add-btn"
                                data-testid="security.passkey_add">
                            <?php echo klytos_esc_html( __( 'security.add_passkey' ) ); ?>
                        </button>
                    </div>
                    <?php /*
                     * The ceremony's outcome is announced in the page, never
                     * through alert(): a browser dialog blocks the page and is
                     * exactly what §2 rules out for the confirm.
                     */ ?>
                    <p class="k-status-line" role="status" id="security-passkey-status" data-testid="security.passkey_status"></p>
                </div>

                <p class="k-hint" id="security-passkey-unsupported">
                    <?php echo klytos_esc_html( __( 'security.passkey_unsupported' ) ); ?>
                </p>
            </div>
        </section>

        <?php klytos_do_action( 'admin.security.before_recovery_codes', $recoveryLeft, $userId ); ?>

        <?php /*
         * ─── Card 3 — Recovery codes ───────────────────────────
         *
         * §6's second delta: "the recovery-codes card is --tinte-aviso with a
         * 1px color-mix border — the ONE bordered card in the admin, because it
         * is a one-time secret." `.k-card--secret` is that variant and exists
         * for this card alone.
         */ ?>
        <section class="k-card k-card--padded k-card--secret"
                 id="security-recovery-codes"
                 aria-labelledby="security-recovery-codes-heading">
            <div class="k-card-body">
                <h2 class="k-card-heading" id="security-recovery-codes-heading">
                    <?php echo klytos_esc_html( __( 'security.card_recovery_codes' ) ); ?>
                </h2>
                <p class="k-hint"><?php echo klytos_esc_html( __( 'security.recovery_description' ) ); ?></p>

                <?php if ( $recoveryCodes !== null ) : ?>
                    <?php /*
                     * Shown ONCE. `generateRecoveryCodes()` stores hashes, so
                     * the plaintext exists in this response and nowhere else —
                     * the warning therefore comes BEFORE the codes, not after
                     * them, which is entry 8's stated rule for the same shape of
                     * one-time secret.
                     */ ?>
                    <p class="k-status-line k-status-line--aviso" role="status" data-testid="security.recovery_once">
                        <?php echo klytos_esc_html( __( 'security.recovery_codes_warning' ) ); ?>
                    </p>
                    <ul class="k-collection" data-testid="security.recovery_codes">
                        <?php foreach ( $recoveryCodes as $code ) : ?>
                            <li class="k-collection-row">
                                <code class="k-code-key"><?php echo klytos_esc_html( $code ); ?></code>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif ( $recoveryLeft === 0 ) : ?>
                    <p class="k-empty" data-testid="security.recovery_empty">
                        <?php klytos_admin_icon( $spriteUrl, 'ks-vpn_key', 'k-empty-icon' ); ?>
                        <span class="k-empty-text">
                            <?php echo klytos_esc_html( __( 'security.recovery_empty_sentence' ) ); ?>
                        </span>
                    </p>
                <?php else : ?>
                    <p data-testid="security.recovery_left">
                        <?php echo klytos_esc_html( __( 'security.recovery_codes_remaining', [ 'count' => (string) $recoveryLeft ] ) ); ?>
                    </p>
                <?php endif; ?>

                <?php if ( $twoFactorOn ) : ?>
                    <div class="k-collection-add-actions">
                        <form method="post" class="k-confirm-wrap">
                            <?php echo klytos_csrf_field(); ?>
                            <input type="hidden" name="action" value="reauth">
                            <input type="hidden" name="reauth_for" value="regenerate_recovery">
                            <button type="submit" class="k-btn k-btn--secondary k-btn--sm" data-testid="security.recovery_regenerate">
                                <?php echo klytos_esc_html( __( 'security.regenerate_recovery' ) ); ?>
                            </button>
                        </form>
                    </div>

                    <?php if ( $pendingReauth !== null && $pendingReauth['for'] === 'regenerate_recovery' ) : ?>
                        <?php
                        $reauthFor    = 'regenerate_recovery';
                        $reauthCredId = '';
                        require __DIR__ . '/partials/security-reauth.php';
                        ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>

        <?php klytos_do_action( 'admin.security.after_2fa', $tfConfig, $userId ); ?>

        <?php if ( $canConfigureSite ) : ?>
            <?php klytos_do_action( 'admin.security.before_encryption', $encryptionLevel ); ?>

            <?php // ─── Card 4 — Encryption level ────────────────── ?>
            <section class="k-card k-card--padded"
                     id="security-encryption"
                     aria-labelledby="security-encryption-heading">
                <div class="k-card-body">
                    <h2 class="k-card-heading" id="security-encryption-heading">
                        <?php echo klytos_esc_html( __( 'security.card_encryption' ) ); ?>
                    </h2>
                    <p class="k-hint"><?php echo klytos_esc_html( __( 'security.encryption_description' ) ); ?></p>

                    <form method="post" data-testid="security.encryption_form">
                        <?php echo klytos_csrf_field(); ?>
                        <input type="hidden" name="action" value="change_encryption_level">

                        <div class="k-field">
                            <label class="k-label" for="security-field-new_encryption_level">
                                <?php echo klytos_esc_html( __( 'security.encryption_level_label' ) ); ?>
                            </label>
                            <select class="k-control"
                                    id="security-field-new_encryption_level"
                                    name="new_encryption_level"
                                    aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'new_encryption_level' ) ); ?>"
                                    <?php echo isset( $fieldErrors['new_encryption_level'] ) ? 'aria-invalid="true"' : ''; ?>
                                    data-testid="security.encryption_level">
                                <?php foreach ( [ 'basic', 'medium', 'professional' ] as $level ) : ?>
                                    <option value="<?php echo klytos_esc_attr( $level ); ?>"
                                        <?php echo $encryptionLevel === $level ? 'selected' : ''; ?>>
                                        <?php echo klytos_esc_html( __( 'security.enc_' . $level ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="k-hint" id="security-hint-new_encryption_level">
                                <?php echo klytos_esc_html( __( 'security.hint_encryption_level' ) ); ?>
                            </p>
                            <?php if ( isset( $fieldErrors['new_encryption_level'] ) ) : ?>
                                <p class="k-error" id="security-error-new_encryption_level">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors['new_encryption_level'] ); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="k-field">
                            <label class="k-label" for="security-field-enc_password">
                                <?php echo klytos_esc_html( __( 'security.current_password' ) ); ?>
                            </label>
                            <input type="password"
                                   class="k-control"
                                   id="security-field-enc_password"
                                   name="enc_password"
                                   required
                                   autocomplete="current-password"
                                   aria-describedby="<?php echo klytos_esc_attr( $describedBy( 'enc_password' ) ); ?>"
                                   <?php echo isset( $fieldErrors['enc_password'] ) ? 'aria-invalid="true"' : ''; ?>
                                   data-testid="security.encryption_password">
                            <p class="k-hint" id="security-hint-enc_password">
                                <?php echo klytos_esc_html( __( 'security.hint_reauth' ) ); ?>
                            </p>
                            <?php if ( isset( $fieldErrors['enc_password'] ) ) : ?>
                                <p class="k-error" id="security-error-enc_password">
                                    <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                                    <?php echo klytos_esc_html( $fieldErrors['enc_password'] ); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="k-card-footer">
                            <button type="submit" class="k-btn k-btn--primary k-btn--sm" data-testid="security.encryption_save">
                                <?php echo klytos_esc_html( __( 'security.change_level' ) ); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <?php // ─── Card 5 — Recovery keys ───────────────────── ?>
            <section class="k-card k-card--padded"
                     id="security-recovery-keys"
                     aria-labelledby="security-recovery-keys-heading">
                <div class="k-card-body">
                    <h2 class="k-card-heading" id="security-recovery-keys-heading">
                        <?php echo klytos_esc_html( __( 'security.card_recovery_keys' ) ); ?>
                    </h2>

                    <p>
                        <?php if ( $recoveryConfirmed ) : ?>
                            <span class="k-badge k-badge--exito" data-testid="security.recovery_keys_badge">
                                <?php echo klytos_esc_html( __( 'security.confirmed' ) ); ?>
                            </span>
                        <?php else : ?>
                            <span class="k-badge k-badge--peligro" data-testid="security.recovery_keys_badge">
                                <?php echo klytos_esc_html( __( 'security.not_confirmed' ) ); ?>
                            </span>
                        <?php endif; ?>
                    </p>

                    <div class="k-field">
                        <h3 class="k-label"><?php echo klytos_esc_html( __( 'security.enc_key_title' ) ); ?></h3>
                        <p class="k-hint"><?php echo klytos_esc_html( __( 'security.enc_key_location' ) ); ?></p>
                        <p><code class="k-code-key" data-testid="security.enc_key_path">config/.encryption_key</code></p>

                        <?php /*
                         * MOVED HERE FROM `settings.php` WITH ENTRY 9 (D-095's
                         * open check, answered by reading both files against the
                         * manager rather than assuming).
                         *
                         * Settings carried a second "Encryption Key" card that
                         * showed this key's material with Copy / Download / Mark
                         * as backed up. `manifest.md` §9 names no encryption
                         * section and §6 names Recovery keys, so two surfaces
                         * offered one duty — the duplication D-090 refused for
                         * the Taxonomies card. It moved rather than being
                         * deleted, because it holds the ONLY affordance in the
                         * product that actually yields the key material, and
                         * without it "back up your encryption key" is an
                         * instruction with nothing behind it.
                         *
                         * §2 "Read-only vs disabled": a value the person may
                         * copy but not change is `readonly`, mono, selectable,
                         * with a copy button — not `disabled`.
                         */ ?>
                        <?php if ( $encryptionKeyBase64 !== '' ) : ?>
                            <label class="k-label" for="security-field-enc_key">
                                <?php echo klytos_esc_html( __( 'security.enc_key_material' ) ); ?>
                            </label>
                            <p class="k-hint" id="security-hint-enc_key">
                                <?php echo klytos_esc_html( __( 'security.enc_key_material_hint' ) ); ?>
                            </p>
                            <textarea class="k-control k-control--mono"
                                      id="security-field-enc_key"
                                      readonly
                                      rows="2"
                                      spellcheck="false"
                                      aria-describedby="security-hint-enc_key"
                                      data-testid="security.enc_key_material"><?php
                                        echo klytos_esc_textarea( $encryptionKeyBase64 );
                                        ?></textarea>
                            <div class="k-card-footer">
                                <button type="button"
                                        class="k-btn k-btn--secondary k-btn--sm"
                                        id="security-copy-enc-key"
                                        data-testid="security.copy_enc_key">
                                    <?php echo klytos_esc_html( __( 'security.enc_key_copy' ) ); ?>
                                </button>
                                <?php /*
                                 * A real link with `download`, not a Blob built
                                 * in script: the key is already in the DOM as a
                                 * data: URL's payload would be, and an <a> works
                                 * with JavaScript off, which the previous
                                 * button did not.
                                 */ ?>
                                <a class="k-btn k-btn--secondary k-btn--sm"
                                   href="data:application/octet-stream;base64,<?php echo klytos_esc_attr( $encryptionKeyBase64 ); ?>"
                                   download="klytos-encryption.key"
                                   data-testid="security.download_enc_key">
                                    <?php echo klytos_esc_html( __( 'security.enc_key_download' ) ); ?>
                                </a>
                            </div>
                            <?php /*
                             * The two outcomes are TRANSLATED STRINGS carried
                             * on the element, not literals in the script. A
                             * user-facing sentence inside a <script> block is a
                             * string that no catalogue can ever reach.
                             */ ?>
                            <p class="k-hint"
                               id="security-enc-key-copied"
                               role="status"
                               data-copied="<?php echo klytos_esc_attr( __( 'security.enc_key_copied' ) ); ?>"
                               data-failed="<?php echo klytos_esc_attr( __( 'security.enc_key_copy_failed' ) ); ?>"
                               data-testid="security.enc_key_copied"></p>
                        <?php else : ?>
                            <p class="k-status-line k-status-line--aviso" data-testid="security.enc_key_missing">
                                <?php echo klytos_esc_html( __( 'security.enc_key_missing' ) ); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="k-field">
                        <h3 class="k-label"><?php echo klytos_esc_html( __( 'security.id_key_title' ) ); ?></h3>
                        <?php if ( $identityFingerprint ) : ?>
                            <p class="k-hint"><?php echo klytos_esc_html( __( 'security.fingerprint' ) ); ?></p>
                            <p><code class="k-code-key" data-testid="security.identity_fingerprint"><?php
                                echo klytos_esc_html( (string) $identityFingerprint );
                            ?></code></p>
                            <?php /*
                             * Posts DIRECTLY to the export endpoint. It used to
                             * post here and be 302-redirected, which the browser
                             * follows as a GET — so a state-writing secret export
                             * answered GET (audit S-12). A redirect cannot carry a
                             * POST, so the form has to target the endpoint itself.
                             */ ?>
                            <form method="post" action="<?php echo klytos_esc_url( $basePath . 'admin/api/download-identity.php' ); ?>">
                                <?php echo klytos_csrf_field(); ?>
                                <button type="submit" class="k-btn k-btn--secondary k-btn--sm" data-testid="security.download_identity">
                                    <?php echo klytos_esc_html( __( 'security.download_identity' ) ); ?>
                                </button>
                            </form>
                        <?php else : ?>
                            <p class="k-hint"><?php echo klytos_esc_html( __( 'security.no_identity_keys' ) ); ?></p>
                            <form method="post">
                                <?php echo klytos_csrf_field(); ?>
                                <input type="hidden" name="action" value="generate_identity_keys">
                                <button type="submit" class="k-btn k-btn--primary k-btn--sm" data-testid="security.generate_identity">
                                    <?php echo klytos_esc_html( __( 'security.generate_identity' ) ); ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if ( ! $recoveryConfirmed ) : ?>
                        <form method="post">
                            <?php echo klytos_csrf_field(); ?>
                            <input type="hidden" name="action" value="confirm_recovery_keys">
                            <div class="k-field">
                                <label class="k-choice k-hit-24" for="security-field-confirm_checkbox">
                                    <?php /*
                                     * §4 "Switch vs checkbox": this one needs a
                                     * Save, so it is a checkbox and not a switch.
                                     */ ?>
                                    <input type="checkbox"
                                           class="k-check"
                                           id="security-field-confirm_checkbox"
                                           name="confirm_checkbox"
                                           required
                                           data-testid="security.confirm_recovery_checkbox">
                                    <span><?php echo klytos_esc_html( __( 'security.confirm_recovery_checkbox' ) ); ?></span>
                                </label>
                            </div>
                            <div class="k-card-footer">
                                <button type="submit" class="k-btn k-btn--primary k-btn--sm" data-testid="security.confirm_recovery">
                                    <?php echo klytos_esc_html( __( 'security.confirm_recovery_btn' ) ); ?>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <?php klytos_do_action( 'admin.security.after_encryption', $encryptionLevel ); ?>

        <?php endif; ?>

        <?php if ( $twoFactorOn ) : ?>
            <?php /*
             * §2 "Destructive section — always the last card, heading is what it
             * does, no 'Danger zone'". The two-step confirm is the same re-auth
             * step every other toggle uses: the password field IS the second
             * step, and it names what will happen before it happens.
             */ ?>
            <section class="k-card k-card--padded"
                     id="security-turn-off"
                     aria-labelledby="security-turn-off-heading">
                <div class="k-card-body">
                    <h2 class="k-card-heading" id="security-turn-off-heading">
                        <?php echo klytos_esc_html( __( 'security.card_turn_off' ) ); ?>
                    </h2>
                    <p class="k-hint"><?php echo klytos_esc_html( __( 'security.disable_all_description' ) ); ?></p>

                    <form method="post" class="k-confirm-wrap">
                        <?php echo klytos_csrf_field(); ?>
                        <input type="hidden" name="action" value="reauth">
                        <input type="hidden" name="reauth_for" value="disable_all">
                        <button type="submit" class="k-btn k-btn--destructive k-btn--sm" data-testid="security.disable_all">
                            <?php echo klytos_esc_html( __( 'security.disable_all_button' ) ); ?>
                        </button>
                    </form>

                    <?php if ( $pendingReauth !== null && $pendingReauth['for'] === 'disable_all' ) : ?>
                        <?php
                        $reauthFor    = 'disable_all';
                        $reauthCredId = '';
                        require __DIR__ . '/partials/security-reauth.php';
                        ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

    </div>
</div>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function () {
    'use strict';

    /*
     * §2 Error — form level: "focus moved to it on load". Server-rendered, so
     * this runs once and does not poll.
     */
    var summary = document.getElementById('security-error-summary');
    if (summary) {
        summary.focus();
    }

    /*
     * §4: "the current section is aria-current='page'". Without a script the
     * first section is current, which is true on load — the page opens at the
     * top. From here the attribute follows the fragment, and it is MOVED rather
     * than added, so exactly one item ever carries it.
     */
    var navItems = document.querySelectorAll('.k-section-nav-item');

    function markCurrent(hash) {
        var matched = false;
        Array.prototype.forEach.call(navItems, function (item) {
            var isCurrent = hash !== '' && item.getAttribute('href') === hash;
            if (isCurrent) {
                matched = true;
                item.setAttribute('aria-current', 'page');
            } else {
                item.removeAttribute('aria-current');
            }
        });
        if (!matched && navItems.length) {
            navItems[0].setAttribute('aria-current', 'page');
        }
    }

    window.addEventListener('hashchange', function () {
        markCurrent(window.location.hash);
    });
    if (window.location.hash) {
        markCurrent(window.location.hash);
    }

    /*
     * Copy the master key. A pure enhancement: the value is in a readonly
     * textarea the person can select and copy by hand, and the Download beside
     * it is a real <a download> that needs no script at all — so this button is
     * the only part that disappears with JavaScript off, and nothing is lost
     * with it.
     *
     * The result is announced through a role="status" line rather than by
     * rewriting the button's own label. Swapping the label moves the accessible
     * name of the control the person just activated, which a screen reader
     * reports as a different button appearing under their finger.
     */
    var copyKeyButton = document.getElementById('security-copy-enc-key');
    var copyKeyField = document.getElementById('security-field-enc_key');
    var copyKeyStatus = document.getElementById('security-enc-key-copied');

    if (copyKeyButton && copyKeyField && copyKeyStatus) {
        copyKeyButton.addEventListener('click', function () {
            var value = copyKeyField.value;

            if (!navigator.clipboard) {
                copyKeyStatus.textContent = copyKeyStatus.getAttribute('data-failed') || '';
                return;
            }

            navigator.clipboard.writeText(value).then(function () {
                copyKeyStatus.textContent = copyKeyStatus.getAttribute('data-copied') || '';
            }, function () {
                copyKeyStatus.textContent = copyKeyStatus.getAttribute('data-failed') || '';
            });
        });
    }

    /*
     * Passkey enrolment. This is the ONE control on the screen that is not an
     * enhancement: `navigator.credentials` is the whole mechanism, so the form
     * is revealed only where it exists and the sentence explaining its absence
     * is removed at the same moment. Neither state is a control that does
     * nothing.
     */
    var addPanel = document.getElementById('security-passkey-add');
    var unsupported = document.getElementById('security-passkey-unsupported');
    var addButton = document.getElementById('security-passkey-add-btn');
    var labelField = document.getElementById('security-field-passkey_label');
    var statusLine = document.getElementById('security-passkey-status');

    if (!addPanel || !addButton || !window.PublicKeyCredential || !navigator.credentials) {
        return;
    }

    addPanel.hidden = false;
    if (unsupported) {
        unsupported.remove();
    }

    function base64UrlToBuffer(b64) {
        var s = b64.replace(/-/g, '+').replace(/_/g, '/');
        while (s.length % 4) {
            s += '=';
        }
        var bin = atob(s);
        var buf = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) {
            buf[i] = bin.charCodeAt(i);
        }
        return buf.buffer;
    }

    function bufferToBase64Url(buf) {
        var bytes = new Uint8Array(buf);
        var s = '';
        for (var i = 0; i < bytes.length; i++) {
            s += String.fromCharCode(bytes[i]);
        }
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    var endpoint = <?php echo json_encode( $basePath . 'admin/api/webauthn-challenge.php', JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
    var csrfToken = <?php echo json_encode( $csrf, JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
    var failedText = <?php echo json_encode( __( 'security.passkey_add_failed' ), JSON_HEX_TAG | JSON_HEX_AMP ); ?>;

    function post(payload) {
        return fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(Object.assign({ csrf: csrfToken }, payload))
        }).then(function (response) {
            return response.json();
        });
    }

    addButton.addEventListener('click', function () {
        // The outcome is announced in the page's own status region. A browser
        // alert() blocks the page and is exactly what §2 rules out.
        statusLine.textContent = '';
        addButton.setAttribute('aria-busy', 'true');

        post({ action: 'register_challenge' }).then(function (options) {
            options.challenge = base64UrlToBuffer(options.challenge);
            options.user.id = base64UrlToBuffer(options.user.id);
            if (options.excludeCredentials) {
                options.excludeCredentials = options.excludeCredentials.map(function (c) {
                    c.id = base64UrlToBuffer(c.id);
                    return c;
                });
            }
            return navigator.credentials.create({ publicKey: options });
        }).then(function (credential) {
            return post({
                action: 'register_complete',
                label: (labelField && labelField.value) || 'Passkey',
                attestation: {
                    clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
                    attestationObject: bufferToBase64Url(credential.response.attestationObject)
                }
            });
        }).then(function (result) {
            if (result && result.success) {
                window.location.assign(window.location.pathname);
                return;
            }
            statusLine.textContent = (result && result.error) || failedText;
        }).catch(function () {
            statusLine.textContent = failedText;
        }).then(function () {
            addButton.removeAttribute('aria-busy');
        });
    });
})();
</script>

<?php if ( $totpSetupSecret && ! $totpConfigured ) : ?>
    <script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"
            src="<?php echo klytos_esc_url( $basePath . 'admin/assets/js/klytos-qrcode.js' ); ?>"></script>
    <script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
        KlytosQR.generate(
            'klytos-qr-code',
            <?php echo json_encode( $totpSetupUri, JSON_HEX_TAG | JSON_HEX_AMP ); ?>,
            { moduleSize: 5, quietZone: 4 }
        );
    </script>
<?php endif; ?>

<?php klytos_do_action( 'admin.security.after', $tfConfig, $userId ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
