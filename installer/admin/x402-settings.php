<?php

/**
 * Klytos Admin — x402 Settings
 * Global configuration: provider, wallet, network, pricing, bots, license.
 *
 * @package Klytos
 * @since   2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

$pageTitle = __( 'klytos-x402.settings' );
$auth      = $app->getAuth();
$config    = klytos_x402_config();
$registry  = klytos_x402_providers();
$cfg       = $config->getAll();
$success   = '';
$error     = '';
$csrf      = $auth->getCsrfToken();

// ─── Handle POST ───────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {

    $updates = [
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

    $csrf = $auth->getCsrfToken();
}

$allProviders   = $registry->getAll();
$activeProvider = $registry->has( $cfg['provider_id'] ) ? $registry->get( $cfg['provider_id'] ) : null;

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if ( !empty( $success ) ): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>

<?php if ( !empty( $error ) ): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html( $error ); ?></div>
<?php endif; ?>

<?php if ( empty( $allProviders ) ): ?>
    <div class="alert alert-warning">
        <?php echo klytos_esc_html( __( 'klytos-x402.no_provider' ) ); ?>
    </div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?php echo klytos_esc_attr( $csrf ); ?>">

    <!-- Provider Selection -->
    <div class="card">
        <div class="card-header">
            <h2><?php echo klytos_esc_html( __( 'klytos-x402.provider' ) ); ?></h2>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label"><?php echo klytos_esc_html( __( 'klytos-x402.provider' ) ); ?></label>
                <select name="provider_id" class="form-control">
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
                <div class="form-group">
                    <label class="form-label"><?php echo klytos_esc_html( $field['label'] ); ?></label>
                    <input
                        type="<?php echo klytos_esc_attr( $field['type'] === 'password' ? 'password' : 'text' ); ?>"
                        name="provider_<?php echo klytos_esc_attr( $field['key'] ); ?>"
                        value="<?php echo klytos_esc_attr( $cfg['provider_settings'][$field['key']] ?? $field['default'] ?? '' ); ?>"
                        class="form-control"
                        placeholder="<?php echo klytos_esc_attr( $field['default'] ?? '' ); ?>"
                        <?php echo !empty( $field['required'] ) ? 'required' : ''; ?>
                    >
                    <?php if ( !empty( $field['description'] ) ): ?>
                        <small class="text-muted"><?php echo klytos_esc_html( $field['description'] ); ?></small>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Wallet & Pricing -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <h2><?php echo klytos_esc_html( __( 'klytos-x402.wallet_address' ) ); ?></h2>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label"><?php echo klytos_esc_html( __( 'klytos-x402.wallet_address' ) ); ?></label>
                <input type="text" name="wallet_address" value="<?php echo klytos_esc_attr( $cfg['wallet_address'] ); ?>"
                    class="form-control" placeholder="0x...">
                <small class="text-muted"><?php echo klytos_esc_html( __( 'klytos-x402.wallet_address_desc' ) ); ?></small>
            </div>

            <div class="form-group">
                <label class="form-label"><?php echo klytos_esc_html( __( 'klytos-x402.network' ) ); ?></label>
                <select name="network" class="form-control">
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

            <div class="form-group">
                <label class="form-label"><?php echo klytos_esc_html( __( 'klytos-x402.default_price' ) ); ?></label>
                <input type="text" name="default_price_usd" value="<?php echo klytos_esc_attr( $cfg['default_price_usd'] ); ?>"
                    class="form-control" placeholder="0.01">
            </div>

        </div>
    </div>

    <!-- License -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <h2><?php echo klytos_esc_html( __( 'klytos-x402.license_type' ) ); ?></h2>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label"><?php echo klytos_esc_html( __( 'klytos-x402.license_type' ) ); ?></label>
                <select name="license_type" class="form-control">
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
            <div class="form-group">
                <label class="form-label">License Text</label>
                <textarea name="license_text" class="form-control" rows="2"><?php echo klytos_esc_html( $cfg['license']['default_text'] ?? '' ); ?></textarea>
            </div>
        </div>
    </div>

    <!-- Bot User-Agents -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <h2><?php echo klytos_esc_html( __( 'klytos-x402.bot_user_agents' ) ); ?></h2>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Known bots (built-in)</label>
                <textarea class="form-control" rows="4" disabled><?php echo klytos_esc_html( implode( "\n", $cfg['known_bot_user_agents'] ) ); ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Custom bots (one per line)</label>
                <textarea name="custom_bot_user_agents" class="form-control" rows="3"><?php echo klytos_esc_html( implode( "\n", $cfg['custom_bot_user_agents'] ) ); ?></textarea>
            </div>
        </div>
    </div>

    <!-- Logging -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-body">
            <div class="form-group">
                <label>
                    <input type="checkbox" name="logging_enabled" value="1"
                        <?php echo !empty( $cfg['logging_enabled'] ) ? 'checked' : ''; ?>>
                    <?php echo klytos_esc_html( __( 'klytos-x402.logging' ) ); ?>
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="stats_enabled" value="1"
                        <?php echo !empty( $cfg['stats_enabled'] ) ? 'checked' : ''; ?>>
                    <?php echo klytos_esc_html( __( 'klytos-x402.stats_toggle' ) ); ?>
                </label>
            </div>
        </div>
    </div>

    <div style="margin-top: 1.5rem;">
        <button type="submit" class="btn btn-primary">
            <?php echo klytos_esc_html( __( 'klytos-x402.save_settings' ) ); ?>
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
