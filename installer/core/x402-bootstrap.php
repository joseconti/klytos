<?php

/**
 * Klytos x402 — Core Bootstrap
 *
 * Initializes the x402 micropayments system as a core module.
 * Loaded from App::boot() BEFORE plugins, so provider plugins can
 * register via the `x402.payment_providers` filter hook.
 *
 * @package Klytos
 * @since   2.0.0
 */

declare( strict_types=1 );

// ─── Translations ──────────────────────────────────────────────

klytos_register_translations( 'klytos-x402', __DIR__ . '/../lang/x402' );

// ─── Boot x402 services ────────────────────────────────────────

$x402Config   = new \Klytos\Core\X402\Config();
$x402Registry = new \Klytos\Core\X402\Providers\ProviderRegistry();
$x402Log      = new \Klytos\Core\X402\TransactionLog();
$x402Stats    = new \Klytos\Core\X402\Stats( $x402Log );
$x402Detector = new \Klytos\Core\X402\BotDetector( $x402Config->getBotUserAgents() );
$x402Gate     = new \Klytos\Core\X402\Gate( $x402Config, $x402Registry, $x402Detector, $x402Log );
$x402Writer   = new \Klytos\Core\X402\HtaccessWriter( $x402Detector );

// Expose globally for the gate script and provider plugins.
$GLOBALS['klytos_x402_config']   = $x402Config;
$GLOBALS['klytos_x402_registry'] = $x402Registry;
$GLOBALS['klytos_x402_gate']     = $x402Gate;
$GLOBALS['klytos_x402_log']      = $x402Log;
$GLOBALS['klytos_x402_stats']    = $x402Stats;
$GLOBALS['klytos_x402_writer']   = $x402Writer;

// ─── Global helper functions ───────────────────────────────────

function klytos_x402_providers(): \Klytos\Core\X402\Providers\ProviderRegistry
{
    return $GLOBALS['klytos_x402_registry'];
}

function klytos_x402_config(): \Klytos\Core\X402\Config
{
    return $GLOBALS['klytos_x402_config'];
}

function klytos_x402_stats(): \Klytos\Core\X402\Stats
{
    return $GLOBALS['klytos_x402_stats'];
}

function klytos_x402_log(): \Klytos\Core\X402\TransactionLog
{
    return $GLOBALS['klytos_x402_log'];
}

// ─── Collect providers AFTER plugins load ──────────────────────

klytos_add_action( 'plugins.loaded', function () use ( $x402Registry ): void {
    $providers = klytos_apply_filters( 'x402.payment_providers', [] );

    foreach ( $providers as $provider ) {
        if ( $provider instanceof \Klytos\Core\X402\Providers\PaymentProviderInterface ) {
            $x402Registry->register( $provider );
        }
    }
}, 99 );

// ─── Capabilities ──────────────────────────────────────────────

klytos_add_filter( 'auth.capabilities', function ( array $capabilities ): array {
    $capabilities['x402.manage'] = ['owner', 'admin'];
    $capabilities['x402.view']   = ['owner', 'admin', 'editor'];
    return $capabilities;
} );

// ─── Admin Sidebar ─────────────────────────────────────────────

klytos_add_filter( 'admin.sidebar_items', function ( array $items ): array {
    $adminPath = \Klytos\Core\Helpers::getBasePath() . 'admin/';

    $items[] = [
        'id'         => 'klytos-x402',
        'title'      => __( 'klytos-x402.sidebar_title' ),
        'url'        => $adminPath . 'x402-dashboard.php',
        'icon'       => 'fa-solid fa-coins',
        'position'   => 86,
        'section'    => 'tools',
        'capability' => 'x402.manage',
        'children'   => [
            [
                'id'    => 'x402-dashboard',
                'title' => __( 'klytos-x402.dashboard' ),
                'url'   => $adminPath . 'x402-dashboard.php',
            ],
            [
                'id'    => 'x402-settings',
                'title' => __( 'klytos-x402.settings' ),
                'url'   => $adminPath . 'x402-settings.php',
            ],
            [
                'id'    => 'x402-transactions',
                'title' => __( 'klytos-x402.transactions' ),
                'url'   => $adminPath . 'x402-transactions.php',
            ],
        ],
    ];

    return $items;
} );

// ─── Page editor integration ───────────────────────────────────

klytos_add_action( 'editor.sidebar.after_seo', function ( array $page, bool $isEditing ) use ( $x402Config ): void {
    $globalDefault = $x402Config->get( 'x402_default_enabled', false );
    $pageEnabled   = $page['x402_enabled'] ?? null;
    $pagePrice     = $page['x402_price_usd'] ?? '';
    $defaultPrice  = $x402Config->get( 'default_price_usd', '0.01' );

    $effectiveEnabled = $pageEnabled !== null ? (bool) $pageEnabled : $globalDefault;
    $inheritLabel     = $globalDefault ? __( 'klytos-x402.inherit_on' ) : __( 'klytos-x402.inherit_off' );

    echo '<div class="klytos-sidebar__section">';
    echo '<h3 class="klytos-sidebar__heading">' . klytos_esc_html( __( 'klytos-x402.x402_protection' ) ) . '</h3>';

    echo '<div class="klytos-field">';
    echo '<label class="klytos-field__label">' . klytos_esc_html( __( 'klytos-x402.enabled' ) ) . '</label>';
    echo '<select name="x402_enabled" class="klytos-field__select">';
    echo '<option value=""'  . ( $pageEnabled === null ? ' selected' : '' ) . '>' . klytos_esc_html( $inheritLabel ) . '</option>';
    echo '<option value="1"' . ( $pageEnabled === true ? ' selected' : '' )  . '>' . klytos_esc_html( __( 'klytos-x402.enabled_on' ) ) . '</option>';
    echo '<option value="0"' . ( $pageEnabled === false ? ' selected' : '' ) . '>' . klytos_esc_html( __( 'klytos-x402.enabled_off' ) ) . '</option>';
    echo '</select>';
    echo '</div>';

    echo '<div class="klytos-field">';
    echo '<label class="klytos-field__label">' . klytos_esc_html( __( 'klytos-x402.price_usd' ) ) . '</label>';
    echo '<input type="text" name="x402_price_usd" value="' . klytos_esc_attr( $pagePrice ) . '" '
       . 'placeholder="' . klytos_esc_attr( $defaultPrice ) . '" class="klytos-field__input" />';
    echo '<span class="klytos-field__hint">' . klytos_esc_html( __( 'klytos-x402.price_hint' ) ) . '</span>';
    echo '</div>';

    echo '</div>';
} );

// ─── Page before_save — inject x402 defaults ───────────────────

klytos_add_action( 'page.before_save', function ( array &$data, string $action ) use ( $x402Config ): void {
    if ( $action !== 'create' ) return;

    if ( !array_key_exists( 'x402_enabled', $data ) || $data['x402_enabled'] === null ) {
        $data['x402_enabled'] = $x402Config->get( 'x402_default_enabled', false );
    }
} );

// ─── Build integration ─────────────────────────────────────────

klytos_add_action( 'build.after', function () use ( $x402Writer, $x402Config ): void {
    $x402Writer->writeRules();

    $publicRoot   = dirname( \Klytos\Core\Helpers::getRootPath() );
    $wellKnownDir = $publicRoot . '/.well-known';

    if ( !is_dir( $wellKnownDir ) ) {
        mkdir( $wellKnownDir, 0755, true );
    }

    $config         = $x402Config->getAll();
    $allPages       = klytos_storage()->getAll( 'pages' );
    $protectedCount = 0;

    foreach ( $allPages as $page ) {
        $enabled = $page['x402_enabled'] ?? null;
        if ( $enabled === null ) $enabled = $config['x402_default_enabled'];
        if ( $enabled ) $protectedCount++;
    }

    $registry   = klytos_x402_providers();
    $providerId = $config['provider_id'] ?? '';
    $provider   = $registry->has( $providerId ) ? $registry->get( $providerId ) : null;

    file_put_contents( $wellKnownDir . '/x402.json', json_encode( [
        'x402' => [
            'version'           => '2',
            'provider'          => $providerId,
            'facilitator'       => $provider ? $provider->getFacilitatorUrl() : null,
            'network'           => $config['network'] ?? 'base',
            'asset'             => 'USDC',
            'wallet'            => $config['wallet_address'] ?? '',
            'protected_pages'   => $protectedCount,
            'total_pages'       => count( $allPages ),
            'default_price_usd' => $config['default_price_usd'] ?? '0.01',
            'license_types'     => ['inference', 'inference-only', 'training', 'full'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ), LOCK_EX );

    // Append x402 info to robots.txt.
    $robotsPath = $publicRoot . '/robots.txt';
    if ( file_exists( $robotsPath ) ) {
        $robots = file_get_contents( $robotsPath );
        if ( $robots !== false && strpos( $robots, 'x402' ) === false ) {
            $robots .= "\n# x402 Micropayments - AI bots can access paid content via x402 protocol\n";
            $robots .= "# Payment info: See /.well-known/x402.json\n";
            file_put_contents( $robotsPath, $robots, LOCK_EX );
        }
    }

    // Ensure x402-gate.php is in public root.
    $gateSource = \Klytos\Core\Helpers::getRootPath() . '/public/x402-gate.php';
    $gateDest   = $publicRoot . '/x402-gate.php';
    if ( file_exists( $gateSource ) && !file_exists( $gateDest ) ) {
        copy( $gateSource, $gateDest );
    }
} );

// ─── llms.txt integration ──────────────────────────────────────

klytos_add_filter( 'build.llms_txt', function ( string $content ) use ( $x402Config ): string {
    $config = $x402Config->getAll();
    $pages  = klytos_storage()->getAll( 'pages' );

    $protected = [];
    foreach ( $pages as $page ) {
        $enabled = $page['x402_enabled'] ?? null;
        if ( $enabled === null ) $enabled = $config['x402_default_enabled'];
        if ( !$enabled ) continue;
        $protected[] = [
            'title' => $page['title'] ?? $page['slug'] ?? '',
            'slug'  => $page['slug'] ?? '',
            'price' => $page['x402_price_usd'] ?? $config['default_price_usd'] ?? '0.01',
        ];
    }

    if ( empty( $protected ) ) return $content;

    $content .= "\n## Paid Content (x402)\n";
    $content .= "The following pages require x402 payment for AI access:\n";
    foreach ( $protected as $p ) {
        $content .= "- [{$p['title']}](/{$p['slug']}.html.md): \${$p['price']} USD (USDC)\n";
    }

    return $content;
} );

// ─── Webhook events ────────────────────────────────────────────

klytos_add_filter( 'webhooks.events', function ( array $events ): array {
    $events['x402.payment.received'] = 'x402: Payment received from AI bot';
    $events['x402.payment.failed']   = 'x402: Payment verification failed';
    $events['x402.config.updated']   = 'x402: Configuration updated';
    return $events;
} );

// ─── MCP Tools ─────────────────────────────────────────────────

require_once __DIR__ . '/x402-mcp-tools.php';
