<?php

/**
 * Klytos Admin — the Security screen's re-auth step.
 *
 * Manifest entry 6's delta: "2FA and passkey controls are switches (immediate
 * effect, **each confirmed by a re-auth step**)". This partial IS that step, and
 * it is also `template-record-form.md` §2's destructive confirm for the
 * "Turn off two-factor authentication" card — one mechanism, not two, because
 * both are the same thing: state what is about to happen, then require a proof
 * that the person at the keyboard is the account holder.
 *
 * It is a PARTIAL rather than six copies because six copies is six chances for
 * the `aria-describedby` order, the autocomplete token or the cancel path to
 * drift apart, and the order is the specified part (§4).
 *
 * Rendered entirely on the SERVER — the switch posts, the card comes back with
 * this step in it, the second post applies the change — so it behaves
 * identically with JavaScript disabled. A confirmation that lives in script
 * alone is no confirmation for anyone without it, which is what §2's "never a
 * browser confirm()" is really about.
 *
 * Expects, from the including scope:
 *   string                 $reauthFor     One of KLYTOS_SECURITY_REAUTH_ACTIONS' keys.
 *   string                 $reauthCredId  The passkey being removed, or ''.
 *   array<string,string>   $fieldErrors   Field-level errors, keyed by control name.
 *   string                 $spriteUrl     The admin sprite, from the shell.
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

if ( ! isset( $reauthFor ) || ! isset( KLYTOS_SECURITY_REAUTH_ACTIONS[ $reauthFor ] ) ) {
    return;
}

$reauthCredId = isset( $reauthCredId ) ? (string) $reauthCredId : '';

/*
 * §4: hints and errors are BOTH in `aria-describedby`, hint first. Built here
 * rather than inline so the order — which is the specified part — is one
 * expression rather than a ternary embedded in an attribute.
 */
$reauthDescribedBy = 'security-hint-confirm_password';
if ( isset( $fieldErrors['confirm_password'] ) ) {
    $reauthDescribedBy .= ' security-error-confirm_password';
}
?>
<?php /*
 * aria-live="polite" on the wrapper, exactly as §2 specifies for the two-step
 * confirm: the step appears after a post, so a screen reader that stayed on the
 * card is told the control changed rather than left to discover it.
 */ ?>
<div class="k-confirm-wrap k-field" aria-live="polite" data-testid="security.reauth">
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="reauth_confirm">
        <input type="hidden" name="reauth_for" value="<?php echo klytos_esc_attr( $reauthFor ); ?>">
        <?php if ( $reauthCredId !== '' ) : ?>
            <input type="hidden" name="credential_id" value="<?php echo klytos_esc_attr( $reauthCredId ); ?>">
        <?php endif; ?>

        <?php /*
         * The sentence names what THIS toggle will do, never a generic
         * "are you sure?". §2's example — "34 records will be deleted" — is a
         * template for the shape, and the consequence stated has to be the real
         * one (D-089, D-090).
         */ ?>
        <p data-testid="security.reauth_what">
            <?php echo klytos_esc_html( __( KLYTOS_SECURITY_REAUTH_ACTIONS[ $reauthFor ] ) ); ?>
        </p>

        <label class="k-label" for="security-field-confirm_password">
            <?php echo klytos_esc_html( __( 'security.current_password' ) ); ?>
        </label>
        <input type="password"
               class="k-control"
               id="security-field-confirm_password"
               name="confirm_password"
               required
               autocomplete="current-password"
               aria-describedby="<?php echo klytos_esc_attr( $reauthDescribedBy ); ?>"
               <?php echo isset( $fieldErrors['confirm_password'] ) ? 'aria-invalid="true"' : ''; ?>
               data-testid="security.reauth_password">
        <p class="k-hint" id="security-hint-confirm_password">
            <?php echo klytos_esc_html( __( 'security.hint_reauth' ) ); ?>
        </p>
        <?php if ( isset( $fieldErrors['confirm_password'] ) ) : ?>
            <p class="k-error" id="security-error-confirm_password">
                <?php klytos_admin_icon( $spriteUrl, 'ks-error', 'k-error-icon' ); ?>
                <?php echo klytos_esc_html( $fieldErrors['confirm_password'] ); ?>
            </p>
        <?php endif; ?>

        <div class="k-collection-add-actions">
            <button type="submit"
                    class="k-btn k-btn--destructive k-btn--sm"
                    data-testid="security.reauth_confirm">
                <?php echo klytos_esc_html( __( 'security.reauth_confirm' ) ); ?>
            </button>
        </div>
    </form>

    <?php /*
     * Cancel is its own post, not a link: it clears the pending step on the
     * server, which is where the step lives. A link would re-render the page
     * with the step still armed.
     */ ?>
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="action" value="reauth_cancel">
        <button type="submit" class="k-btn k-btn--secondary k-btn--sm" data-testid="security.reauth_cancel">
            <?php echo klytos_esc_html( __( 'common.cancel' ) ); ?>
        </button>
    </form>
</div>
