<?php

/**
 * Klytos x402 — MCP Tools Registration
 *
 * Registers 8 MCP tools for AI agent control of x402 micropayments.
 * Loaded from x402-bootstrap.php.
 *
 * @package Klytos
 * @since   2.0.0
 */

declare( strict_types=1 );

klytos_add_filter( 'mcp.tools_list', function ( array $tools ): array {
    $x402Tools = [
        ['name' => 'klytos_x402_get_config', 'description' => 'Get the global x402 micropayments configuration, including active provider, wallet, pricing defaults, and bot detection settings.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()], 'annotations' => ['title' => 'Get x402 Configuration', 'readOnlyHint' => true]],

        ['name' => 'klytos_x402_set_config', 'description' => 'Update the global x402 micropayments configuration. Controls default protection, wallet address, pricing, network, and active payment provider.', 'inputSchema' => ['type' => 'object', 'properties' => ['x402_default_enabled' => ['type' => 'boolean', 'description' => 'Whether new pages are payment-protected by default'], 'provider_id' => ['type' => 'string', 'description' => 'Payment provider ID (e.g. x402-coinbase-cdp, x402-stripe)'], 'wallet_address' => ['type' => 'string', 'description' => 'Wallet address to receive payments (EVM-compatible)'], 'default_price_usd' => ['type' => 'string', 'description' => 'Default price in USD (e.g. 0.01)'], 'network' => ['type' => 'string', 'description' => 'Blockchain network for settlements'], 'provider_settings' => ['type' => 'object', 'description' => 'Provider-specific settings (e.g. facilitator_url, stripe_secret_key)']]], 'annotations' => ['title' => 'Update x402 Configuration', 'readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true]],

        ['name' => 'klytos_x402_get_page_status', 'description' => 'Get the x402 payment protection status for a specific page, including effective settings after cascade resolution.', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string', 'description' => 'Page slug']], 'required' => ['slug']], 'annotations' => ['title' => 'Get Page x402 Status', 'readOnlyHint' => true]],

        ['name' => 'klytos_x402_set_page_status', 'description' => 'Enable or disable x402 payment protection for a specific page. When enabled, AI bots must pay to access the page.', 'inputSchema' => ['type' => 'object', 'properties' => ['slug' => ['type' => 'string', 'description' => 'Page slug to update'], 'x402_enabled' => ['type' => 'boolean', 'description' => 'true = pay, false = free, null = inherit global'], 'x402_price_usd' => ['type' => 'string', 'description' => 'Custom price in USD (null = use global default)'], 'x402_license_type' => ['type' => 'string', 'enum' => ['inference', 'inference-only', 'training', 'full'], 'description' => 'License type granted after payment'], 'x402_provider' => ['type' => 'string', 'description' => 'Provider ID override for this page (null = use global)']], 'required' => ['slug']], 'annotations' => ['title' => 'Set Page x402 Status', 'readOnlyHint' => false, 'idempotentHint' => true]],

        ['name' => 'klytos_x402_bulk_set_status', 'description' => 'Enable or disable x402 payment protection for multiple pages at once.', 'inputSchema' => ['type' => 'object', 'properties' => ['slugs' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Array of page slugs'], 'x402_enabled' => ['type' => 'boolean', 'description' => 'true = pay, false = free'], 'x402_price_usd' => ['type' => 'string', 'description' => 'Custom price in USD (null = use global default)']], 'required' => ['slugs', 'x402_enabled']], 'annotations' => ['title' => 'Bulk Set x402 Status', 'readOnlyHint' => false, 'idempotentHint' => true]],

        ['name' => 'klytos_x402_get_stats', 'description' => 'Get x402 payment statistics: revenue today, this week, this month, total, and breakdowns by provider.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()], 'annotations' => ['title' => 'Get x402 Stats', 'readOnlyHint' => true]],

        ['name' => 'klytos_x402_list_transactions', 'description' => 'List recent x402 payment transactions with optional filters.', 'inputSchema' => ['type' => 'object', 'properties' => ['from' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'], 'to' => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'], 'slug' => ['type' => 'string', 'description' => 'Filter by page slug'], 'provider_id' => ['type' => 'string', 'description' => 'Filter by provider'], 'bot_user_agent' => ['type' => 'string', 'description' => 'Filter by bot user-agent'], 'limit' => ['type' => 'integer', 'description' => 'Max results (default 50)'], 'offset' => ['type' => 'integer', 'description' => 'Skip first N results']]], 'annotations' => ['title' => 'List x402 Transactions', 'readOnlyHint' => true]],

        ['name' => 'klytos_x402_list_providers', 'description' => 'List all registered x402 payment providers, their capabilities (networks, assets), and which one is currently active.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()], 'annotations' => ['title' => 'List x402 Providers', 'readOnlyHint' => true]],
    ];

    return array_merge( $tools, $x402Tools );
} );

klytos_add_filter( 'mcp.handle_tool', function ( mixed $result, string $toolName, array $params ): mixed {
    if ( !str_starts_with( $toolName, 'klytos_x402_' ) ) return $result;

    $config   = klytos_x402_config();
    $registry = klytos_x402_providers();
    $stats    = klytos_x402_stats();
    $log      = klytos_x402_log();
    $storage  = klytos_storage();

    $handlers = [
        'klytos_x402_get_config' => function () use ( $config, $registry ): array {
            $cfg = $config->getAll();
            $pid = $cfg['provider_id'] ?? '';
            $p   = $registry->has( $pid ) ? $registry->get( $pid ) : null;
            return ['config' => $cfg, 'active_provider' => $p ? ['id' => $p->getId(), 'label' => $p->getLabel(), 'networks' => $p->getSupportedNetworks(), 'assets' => $p->getSupportedAssets(), 'facilitator' => $p->getFacilitatorUrl()] : null];
        },

        'klytos_x402_set_config' => function ( array $p ) use ( $config, $registry ): array {
            $updates = [];
            foreach ( ['x402_default_enabled', 'provider_id', 'wallet_address', 'default_price_usd', 'network', 'provider_settings'] as $key ) {
                if ( array_key_exists( $key, $p ) ) $updates[$key] = $p[$key];
            }
            if ( isset( $updates['provider_id'] ) && !$registry->has( $updates['provider_id'] ) ) {
                return ['error' => "Provider '{$updates['provider_id']}' is not registered."];
            }
            $config->update( $updates );
            klytos_do_action( 'x402.config.updated', $updates );
            return ['success' => true, 'config' => $config->getAll()];
        },

        'klytos_x402_get_page_status' => function ( array $p ) use ( $config ): array {
            $slug = $p['slug'] ?? '';
            return empty( $slug ) ? ['error' => 'slug is required.'] : ['page_status' => $config->getEffective( $slug )];
        },

        'klytos_x402_set_page_status' => function ( array $p ) use ( $storage ): array {
            $slug = $p['slug'] ?? '';
            if ( empty( $slug ) ) return ['error' => 'slug is required.'];
            try { $page = $storage->read( 'pages', $slug ); } catch ( \Throwable ) { return ['error' => "Page '{$slug}' not found."]; }
            foreach ( ['x402_enabled', 'x402_price_usd', 'x402_license_type', 'x402_provider'] as $key ) {
                if ( array_key_exists( $key, $p ) ) $page[$key] = $p[$key];
            }
            $storage->write( 'pages', $slug, $page );
            return ['success' => true, 'page' => $slug];
        },

        'klytos_x402_bulk_set_status' => function ( array $p ) use ( $storage ): array {
            $updated = []; $errors = [];
            foreach ( $p['slugs'] ?? [] as $slug ) {
                try {
                    $page = $storage->read( 'pages', $slug );
                    $page['x402_enabled'] = $p['x402_enabled'] ?? null;
                    if ( isset( $p['x402_price_usd'] ) ) $page['x402_price_usd'] = $p['x402_price_usd'];
                    $storage->write( 'pages', $slug, $page );
                    $updated[] = $slug;
                } catch ( \Throwable ) { $errors[] = $slug; }
            }
            return ['updated' => $updated, 'errors' => $errors];
        },

        'klytos_x402_get_stats' => fn() => ['stats' => $stats->getSummary()],

        'klytos_x402_list_transactions' => function ( array $p ) use ( $log ): array {
            $limit = (int) ( $p['limit'] ?? 50 ); $offset = (int) ( $p['offset'] ?? 0 );
            unset( $p['limit'], $p['offset'] );
            return $log->list( $p, $limit, $offset );
        },

        'klytos_x402_list_providers' => fn() => ['providers' => $registry->toArray( $config->get( 'provider_id', '' ) )],
    ];

    if ( !isset( $handlers[$toolName] ) ) return $result;

    try {
        $data = $handlers[$toolName]( $params );
        return ['content' => [['type' => 'text', 'text' => json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )]], 'isError' => false];
    } catch ( \Throwable $e ) {
        return ['content' => [['type' => 'text', 'text' => json_encode( ['error' => $e->getMessage()] )]], 'isError' => true];
    }
}, 10 );
