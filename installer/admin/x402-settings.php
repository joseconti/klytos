<?php

/**
 * Klytos x402 — Admin Settings
 *
 * Global configuration: provider, wallet, network, pricing, bots, license.
 *
 * @package KlytosX402
 * @since   1.0.0
 */

declare( strict_types=1 );

$config   = klytos_x402_config();
$registry = klytos_x402_providers();
$cfg      = $config->getAll();
$success  = '';
$error    = '';

// ─── Handle POST ───────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {

    $updates = [
        'x402_default_enabled' => !empty( $_POST['x402_default_enabled'] ),
        'provider_id'          => $_POST['provider_id'] ?? $cfg['provider_id'],
        'wallet_address'       => trim( $_POST['wallet_address'] ?? '' ),
        'default_price_usd'    => trim( $_POST['default_price_usd'] ?? '0.01' ),
        'network'              => $_POST['network'] ?? 'base',
        'logging_enabled'      => !empty( $_POST['logging_enabled'] ),
        'stats_enabled'        => !empty( $_POST['stats_enabled'] ),
        'license'              => [
            'default_type' => $_POST['license_type'] ?? 'inference',
            'default_text' => trim( $_POST['license_text'] ?? '' ),
        ],
    ];

    // Provider-specific settings.
    $providerSettings = [];
    $selectedProvider = $registry->has( $updates['provider_id'] )
        ? $registry->get( $updates['provider_id'] )
        : null;

    if ( $selectedProvider ) {
        foreach ( $selectedProvider->getSettingsFields() as $field ) {
            $key = $field['key'];
            $providerSettings[$key] = trim( $_POST['provider_' . $key] ?? '' );
        }

        $validation = $selectedProvider->validateSettings( $providerSettings );
        if ( !$validation['valid'] ) {
            $error = implode( ' ', $validation['errors'] );
        }
    }

    if ( empty( $error ) ) {
        $updates['provider_settings'] = $providerSettings;

        // Custom bot user-agents.
        $customAgents = trim( $_POST['custom_bot_user_agents'] ?? '' );
        $updates['custom_bot_user_agents'] = array_filter(
            array_map( 'trim', explode( "\n", $customAgents ) )
        );

        $config->update( $updates );
        $config->clearCache();
        $cfg     = $config->getAll();
        $success = __( 'klytos-x402.settings_saved' );

        klytos_do_action( 'x402.config.updated', $updates );
    }
}

$allProviders    = $registry->getAll();
$activeProvider  = $registry->has( $cfg['provider_id'] ) ? $registry->get( $cfg['provider_id'] ) : null;

$cspNonce = $GLOBALS['cspNonce'] ?? '';

?>

<div class="klytos-page-header">
    <h1><?php echo klytos_esc_html( __( 'klytos-x402.settings' ) ); ?></h1>
</div>

<?php if ( !empty( $success ) ): ?>
    <div class="klytos-notice klytos-notice--success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>

<?php if ( !empty( $error ) ): ?>
    <div class="klytos-notice klytos-notice--error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<?php if ( empty( $allProviders ) ): ?>
    <div class="klytos-notice klytos-notice--warning">
        <?php echo klytos_esc_html( __( 'klytos-x402.no_provider' ) ); ?>
    </div>
<?php endif; ?>

<form method="post" class="klytos-form">
    <?php klytos_csrf_field(); ?>

    <!-- Provider Selection -->
    <div class="klytos-card">
        <div class="klytos-card__header">
            <span class="klytos-card__label"><?php echo klytos_esc_html( __( 'klytos-x402.provider' ) ); ?></span>
        </div>
        <div class="klytos-card__body">
            <div class="klytos-field">
                <label class="klytos-field__label"><?php echo klytos_esc_html( __( 'klytos-x402.provider' ) ); ?></label>
                <select name="provider_id" class="klytos-field__select" id="x402-provider-select">
                    <?php foreach ( $allProviders as $prov ): ?>
                    <option value="<?php echo klytos_esc_attr( $prov->getId() ); ?>"
                        <?php echo $prov->getId() === $cfg['provider_id'] ? 'selected' : ''; ?>>
                        <?php echo klytos_esc_html( $prov->getLabel() ); ?>
                        (<?php echo klytos_esc_html( implode( ', ', $prov->getSupportedNetworks() ) ); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Provider-specific settings -->
            <?php if ( $activeProvider ): ?>
                <?php foreach ( $activeProvider->getSettingsFields() as $field ): ?>
                <div class="klytos-field">
                    <label class="klytos-field__label"><?php echo klytos_esc_html( $field['label'] ); ?></label>
                    <input
                        type="<?php echo klytos_esc_attr( $field['type'] === 'password' ? 'password' : 'text' ); ?>"
                        name="provider_<?php echo klytos_esc_attr( $field['key'] ); ?>"
                        value="<?php echo klytos_esc_attr( $cfg['provider_settings'][$field['key']] ?? $field['default'] ?? '' ); ?>"
                        class="klytos-field__input"
                        placeholder="<?php echo klytos_esc_attr( $field['default'] ?? '' ); ?>"
                        <?php echo !empty( $field['required'] ) ? 'required' : ''; ?>
                    />
                    <?php if ( !empty( $field['description'] ) ): ?>
                        <span class="klytos-field__hint"><?php echo klytos_esc_html( $field['description'] ); ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Wallet & Pricing -->
    <div class="klytos-card" style="margin-top: var(--klytos-space-4);">
        <div class="klytos-card__header">
            <span class="klytos-card__label"><?php echo klytos_esc_html( __( 'klytos-x402.wallet_address' ) ); ?> &amp; <?php echo klytos_esc_html( __( 'klytos-x402.default_price' ) ); ?></span>
        </div>
        <div class="klytos-card__body">
            <div class="klytos-field">
                <label class="klytos-field__label"><?php echo klytos_esc_html( __( 'klytos-x402.wallet_address' ) ); ?></label>
                <input type="text" name="wallet_address" value="<?php echo klytos_esc_attr( $cfg['wallet_address'] ); ?>"
                    class="klytos-field__input" placeholder="0x..." />
                <span class="klytos-field__hint"><?php echo klytos_esc_html( __( 'klytos-x402.wallet_address_desc' ) ); ?></span>
            </div>

            <div class="klytos-field">
                <label class="klytos-field__label"><?php echo klytos_esc_html( __( 'klytos-x402.network' ) ); ?></label>
                <select name="network" class="klytos-field__select">
                    <?php
                    $networks = $activeProvider ? $activeProvider->getSupportedNetworks() : ['base', 'base-sepolia', 'polygon', 'solana'];
                    foreach ( $networks as $net ):
                    ?>
                    <option value="<?php echo klytos_esc_attr( $net ); ?>"
                        <?php echo $net === ( $cfg['network'] ?? 'base' ) ? 'selected' : ''; ?>>
                        <?php echo klytos_esc_html( ucfirst( $net ) ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="klytos-field">
                <label class="klytos-field__label"><?php echo klytos_esc_html( __( 'klytos-x402.default_price' ) ); ?></label>
                <input type="text" name="default_price_usd" value="<?php echo klytos_esc_attr( $cfg['default_price_usd'] ); ?>"
                    class="klytos-field__input" placeholder="0.01" />
            </div>

            <div class="klytos-field">
                <label class="klytos-toggle">
                    <input type="checkbox" name="x402_default_enabled" value="1"
                        <?php echo !empty( $cfg['x402_default_enabled'] ) ? 'checked' : ''; ?> />
                    <span class="klytos-toggle__label"><?php echo klytos_esc_html( __( 'klytos-x402.enabled' ) ); ?> (<?php echo klytos_esc_html( __( 'klytos-x402.inherit_on' ) ); ?>)</span>
                </label>
            </div>
        </div>
    </div>

    <!-- License -->
    <div class="klytos-card" style="margin-top: var(--klytos-space-4);">
        <div class="klytos-card__header">
            <span class="klytos-card__label"><?php echo klytos_esc_html( __( 'klytos-x402.license_type' ) ); ?></span>
        </div>
        <div class="klytos-card__body">
            <div class="klytos-field">
                <label class="klytos-field__label"><?php echo klytos_esc_html( __( 'klytos-x402.license_type' ) ); ?></label>
                <select name="license_type" class="klytos-field__select">
                    <?php
                    $licenseTypes = ['inference' => 'Inference', 'inference-only' => 'Inference Only', 'training' => 'Training', 'full' => 'Full'];
                    foreach ( $licenseTypes as $val => $label ):
                    ?>
                    <option value="<?php echo klytos_esc_attr( $val ); ?>"
                        <?php echo ( $cfg['license']['default_type'] ?? 'inference' ) === $val ? 'selected' : ''; ?>>
                        <?php echo klytos_esc_html( $label ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="klytos-field">
                <label class="klytos-field__label">License Text</label>
                <textarea name="license_text" class="klytos-field__textarea" rows="2"><?php echo klytos_esc_html( $cfg['license']['default_text'] ?? '' ); ?></textarea>
            </div>
        </div>
    </div>

    <!-- Bot User-Agents -->
    <div class="klytos-card" style="margin-top: var(--klytos-space-4);">
        <div class="klytos-card__header">
            <span class="klytos-card__label"><?php echo klytos_esc_html( __( 'klytos-x402.bot_user_agents' ) ); ?></span>
        </div>
        <div class="klytos-card__body">
            <div class="klytos-field">
                <label class="klytos-field__label">Known bots (built-in, not editable)</label>
                <textarea class="klytos-field__textarea" rows="4" disabled><?php echo klytos_esc_html( implode( "\n", $cfg['known_bot_user_agents'] ) ); ?></textarea>
            </div>
            <div class="klytos-field">
                <label class="klytos-field__label">Custom bots (one per line)</label>
                <textarea name="custom_bot_user_agents" class="klytos-field__textarea" rows="3"><?php echo klytos_esc_html( implode( "\n", $cfg['custom_bot_user_agents'] ) ); ?></textarea>
            </div>
        </div>
    </div>

    <!-- Logging -->
    <div class="klytos-card" style="margin-top: var(--klytos-space-4);">
        <div class="klytos-card__body">
            <div class="klytos-field">
                <label class="klytos-toggle">
                    <input type="checkbox" name="logging_enabled" value="1"
                        <?php echo !empty( $cfg['logging_enabled'] ) ? 'checked' : ''; ?> />
                    <span class="klytos-toggle__label"><?php echo klytos_esc_html( __( 'klytos-x402.logging' ) ); ?></span>
                </label>
            </div>
            <div class="klytos-field">
                <label class="klytos-toggle">
                    <input type="checkbox" name="stats_enabled" value="1"
                        <?php echo !empty( $cfg['stats_enabled'] ) ? 'checked' : ''; ?> />
                    <span class="klytos-toggle__label"><?php echo klytos_esc_html( __( 'klytos-x402.stats_toggle' ) ); ?></span>
                </label>
            </div>
        </div>
    </div>

    <div style="margin-top: var(--klytos-space-4);">
        <button type="submit" class="klytos-btn klytos-btn--primary">
            <?php echo klytos_esc_html( __( 'klytos-x402.save_settings' ) ); ?>
        </button>
    </div>
</form>
