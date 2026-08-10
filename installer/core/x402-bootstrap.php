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

klytos_add_action( 'editor.sidebar.after_seo', function ( ?array $page, bool $isEditing ) use ( $x402Config ): void {
    if ( $page === null ) {
        return;
    }
    // Resolve Post Type default for the inherit label.
    $postType = $page['post_type'] ?? 'page';
    $ptDefault = false;
    try {
        $ptData    = klytos_app()->getPostTypeManager()->get( $postType );
        $ptDefault = $ptData['x402_default_enabled'] ?? false;
    } catch ( \Throwable ) {}

    $pageEnabled  = $page['x402_enabled'] ?? null;
    $pagePrice    = $page['x402_price_usd'] ?? '';
    $defaultPrice = $x402Config->get( 'default_price_usd', '0.01' );
    $inheritLabel = $ptDefault ? __( 'klytos-x402.inherit_on' ) : __( 'klytos-x402.inherit_off' );

    echo '<div class="klytos-sidebar__section">';
    echo '<h3 class="klytos-sidebar__heading">' . klytos_esc_html( __( 'klytos-x402.x402_protection' ) ) . '</h3>';

    echo '<div class="klytos-field">';
    // The label carried no `for` and did not wrap its control, so both fields
    // shipped with NO accessible name — axe reports it critical. Pre-existing;
    // found by the first accessibility pass that reached the page editor.
    echo '<label class="klytos-field__label" for="x402-enabled">' . klytos_esc_html( __( 'klytos-x402.enabled' ) ) . '</label>';
    echo '<select name="x402_enabled" id="x402-enabled" class="klytos-field__select">';
    echo '<option value=""'  . ( $pageEnabled === null ? ' selected' : '' ) . '>' . klytos_esc_html( $inheritLabel ) . '</option>';
    echo '<option value="1"' . ( $pageEnabled === true ? ' selected' : '' )  . '>' . klytos_esc_html( __( 'klytos-x402.enabled_on' ) ) . '</option>';
    echo '<option value="0"' . ( $pageEnabled === false ? ' selected' : '' ) . '>' . klytos_esc_html( __( 'klytos-x402.enabled_off' ) ) . '</option>';
    echo '</select>';
    echo '</div>';

    echo '<div class="klytos-field">';
    echo '<label class="klytos-field__label" for="x402-price-usd">' . klytos_esc_html( __( 'klytos-x402.price_usd' ) ) . '</label>';
    echo '<input type="text" name="x402_price_usd" id="x402-price-usd" value="' . klytos_esc_attr( $pagePrice ) . '" '
       . 'placeholder="' . klytos_esc_attr( $defaultPrice ) . '" class="klytos-field__input" '
       . 'aria-describedby="x402-price-hint" />';
    echo '<span class="klytos-field__hint" id="x402-price-hint">' . klytos_esc_html( __( 'klytos-x402.price_hint' ) ) . '</span>';
    echo '</div>';

    echo '</div>';
} );

// ─── Post Type edit integration ────────────────────────────────

klytos_add_action( 'admin.post_type_edit.after_settings', function ( array $postType, string $ptId ) use ( $x402Config ): void {
    $ptEnabled = $postType['x402_default_enabled'] ?? false;
    $ptPrice   = $postType['x402_price_usd'] ?? '';
    $defaultPrice = $x402Config->get( 'default_price_usd', '0.01' );

    echo '<h4 class="mt-3 mb-1">' . klytos_esc_html( __( 'klytos-x402.x402_protection' ) ) . '</h4>';

    echo '<div class="form-group">';
    echo '<label>';
    echo '<input type="checkbox" name="x402_default_enabled" value="1"' . ( $ptEnabled ? ' checked' : '' ) . '> ';
    echo klytos_esc_html( __( 'klytos-x402.enabled' ) );
    echo '</label>';
    echo '<p class="form-help">' . klytos_esc_html( 'New entries of this post type will have x402 enabled by default. Can be changed per entry.' ) . '</p>';
    echo '</div>';

    echo '<div class="form-group">';
    echo '<label class="form-label">' . klytos_esc_html( __( 'klytos-x402.price_usd' ) ) . '</label>';
    echo '<input type="text" name="x402_price_usd" class="form-control" value="' . klytos_esc_attr( $ptPrice ) . '" placeholder="' . klytos_esc_attr( $defaultPrice ) . '">';
    echo '<p class="form-help">' . klytos_esc_html( __( 'klytos-x402.price_hint' ) ) . '</p>';
    echo '</div>';
} );

// ─── Post Type update — save x402 fields ───────────────────────

// Declare the two keys this module owns so PostTypeManager::update() persists
// them. Without this the checkbox rendered above saved nothing: the update
// allow-list dropped both fields, so the setting came back unticked and no page
// could ever inherit a default that was never stored.
klytos_add_filter( 'post_type.updatable_fields', function ( array $fields ): array {
    $fields[] = 'x402_default_enabled';
    $fields[] = 'x402_price_usd';

    return $fields;
} );

klytos_add_filter( 'admin.post_type_edit.update_data', function ( array $data, string $ptId, array $post ): array {
    // Checkbox: sent as "1" when checked, absent when unchecked.
    $data['x402_default_enabled'] = !empty( $post['x402_default_enabled'] );

    $price = trim( $post['x402_price_usd'] ?? '' );
    $data['x402_price_usd'] = $price !== '' ? $price : null;

    return $data;
} );

// ─── Page before_save — inject x402 defaults from Post Type ────

// A FILTER, not an action. This listener modifies the record, and actions pass
// their arguments by value — the by-reference version this replaced could never
// bind, so PHP warned on every page create in every install and discarded the
// write silently (audit NEW-03). It is the same idiom the post-type listener
// above already uses: take the data, return the data.
klytos_add_filter( 'page.save_data', function ( array $data, string $action ): array {
    if ( $action !== 'create' ) {
        return $data;
    }

    if ( !array_key_exists( 'x402_enabled', $data ) || $data['x402_enabled'] === null ) {
        $postType = $data['post_type'] ?? 'page';
        try {
            $ptData = klytos_app()->getPostTypeManager()->get( $postType );
            $data['x402_enabled'] = $ptData['x402_default_enabled'] ?? false;
        } catch ( \Throwable ) {
            $data['x402_enabled'] = false;
        }
    }

    return $data;
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
    $allPages       = klytos_storage()->list( 'pages' );
    $protectedCount = 0;

    $ptManager = klytos_app()->getPostTypeManager();
    foreach ( $allPages as $page ) {
        $enabled = $page['x402_enabled'] ?? null;
        if ( $enabled === null ) {
            $pt = $page['post_type'] ?? 'page';
            try { $ptData = $ptManager->get( $pt ); $enabled = $ptData['x402_default_enabled'] ?? false; }
            catch ( \Throwable ) { $enabled = false; }
        }
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
    $pages  = klytos_storage()->list( 'pages' );

    $protected = [];
    $ptManager = klytos_app()->getPostTypeManager();
    foreach ( $pages as $page ) {
        $enabled = $page['x402_enabled'] ?? null;
        if ( $enabled === null ) {
            $pt = $page['post_type'] ?? 'page';
            try { $ptData = $ptManager->get( $pt ); $enabled = $ptData['x402_default_enabled'] ?? false; }
            catch ( \Throwable ) { $enabled = false; }
        }
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
