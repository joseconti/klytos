<?php

/**
 * Klytos x402 — Configuration Manager
 *
 * Reads and writes x402 configuration via the Klytos Options API.
 * Handles cascade: page-level overrides > global defaults.
 *
 * @package Klytos
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace Klytos\Core\X402;

class Config
{
    private const OPTIONS_KEY = 'klytos-x402.settings';

    private const DEFAULTS = [
        'provider_id'            => '',
        'wallet_address'         => '',
        'default_price_usd'      => '0.01',
        'network'                => 'base',
        'provider_settings'      => [],
        'license'                => [
            'default_type' => 'inference',
            'default_text' => 'Content licensed for AI inference only. Not for training.',
        ],
        'known_bot_user_agents'  => [
            'GPTBot', 'OAI-SearchBot', 'ChatGPT-User',
            'ClaudeBot', 'Claude-Web', 'anthropic-ai',
            'Google-Extended', 'GoogleOther', 'PerplexityBot',
            'Amazonbot', 'Bytespider', 'CCBot',
            'Applebot-Extended', 'cohere-ai', 'Diffbot',
            'YouBot', 'Timpibot', 'Meta-ExternalAgent',
            'Omgilibot', 'ImagesiftBot', 'Kangaroo Bot',
        ],
        'custom_bot_user_agents' => [],
        'logging_enabled'        => true,
        'stats_enabled'          => true,
    ];

    private ?array $cache = null;

    public function getAll(): array
    {
        if ( $this->cache !== null ) {
            return $this->cache;
        }

        $stored      = klytos_get_option( self::OPTIONS_KEY, [] );
        $this->cache = array_replace_recursive( self::DEFAULTS, is_array( $stored ) ? $stored : [] );

        return $this->cache;
    }

    public function get( string $key, mixed $default = null ): mixed
    {
        $config = $this->getAll();
        $parts  = explode( '.', $key );

        foreach ( $parts as $part ) {
            if ( !is_array( $config ) || !array_key_exists( $part, $config ) ) {
                return $default;
            }
            $config = $config[$part];
        }

        return $config;
    }

    public function update( array $updates ): void
    {
        $config      = $this->getAll();
        $config      = array_replace_recursive( $config, $updates );
        $this->cache = $config;

        klytos_set_option( self::OPTIONS_KEY, $config );
    }

    /**
     * Get effective x402 config for a specific page.
     * Cascade: page override → Post Type default.
     */
    public function getEffective( string $slug ): array
    {
        $global  = $this->getAll();
        $storage = klytos_storage();
        $page    = [];

        try {
            $page = $storage->read( 'pages', $slug );
        } catch ( \Throwable ) {
            // Page not found.
        }

        $enabled = $page['x402_enabled'] ?? null;

        // If page doesn't override, use Post Type default.
        if ( $enabled === null ) {
            $postType = $page['post_type'] ?? 'page';
            try {
                $ptData  = klytos_app()->getPostTypeManager()->get( $postType );
                $enabled = $ptData['x402_default_enabled'] ?? false;
            } catch ( \Throwable ) {
                $enabled = false;
            }
        }

        return [
            'enabled'      => (bool) $enabled,
            'price_usd'    => $page['x402_price_usd'] ?? $global['default_price_usd'],
            'license_type' => $page['x402_license_type'] ?? $global['license']['default_type'] ?? 'inference',
            'provider_id'  => $page['x402_provider'] ?? $global['provider_id'],
            'slug'         => $slug,
            'title'        => $page['title'] ?? $slug,
        ];
    }

    public function getBotUserAgents(): array
    {
        $config = $this->getAll();

        $agents = array_merge(
            $config['known_bot_user_agents'],
            $config['custom_bot_user_agents']
        );

        return klytos_apply_filters( 'x402.bot_user_agents', $agents );
    }

    public function clearCache(): void
    {
        $this->cache = null;
    }
}
