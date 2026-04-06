<?php

/**
 * Klytos x402 — Provider Registry
 *
 * Collects payment providers registered via the `x402.payment_providers`
 * filter hook and resolves the active provider for each request.
 *
 * @package Klytos
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace Klytos\Core\X402\Providers;

class ProviderRegistry
{
    /** @var PaymentProviderInterface[] Indexed by provider ID. */
    private array $providers = [];

    /** @var string Default provider ID (first registered). */
    private string $defaultId = '';

    public function register( PaymentProviderInterface $provider ): void
    {
        $id = $provider->getId();
        $this->providers[$id] = $provider;

        if ( $this->defaultId === '' ) {
            $this->defaultId = $id;
        }
    }

    public function get( string $id ): ?PaymentProviderInterface
    {
        return $this->providers[$id] ?? null;
    }

    public function getAll(): array
    {
        return $this->providers;
    }

    public function resolve( string $id ): PaymentProviderInterface
    {
        if ( !isset( $this->providers[$id] ) ) {
            throw new \RuntimeException( "x402 payment provider '{$id}' is not registered." );
        }

        return $this->providers[$id];
    }

    public function getDefault(): ?PaymentProviderInterface
    {
        if ( $this->defaultId === '' || !isset( $this->providers[$this->defaultId] ) ) {
            return null;
        }

        return $this->providers[$this->defaultId];
    }

    public function has( string $id ): bool
    {
        return isset( $this->providers[$id] );
    }

    public function isEmpty(): bool
    {
        return empty( $this->providers );
    }

    /**
     * Serialize all providers for MCP/API responses.
     */
    public function toArray( string $activeId = '' ): array
    {
        $result = [];

        foreach ( $this->providers as $id => $provider ) {
            $result[] = [
                'id'                 => $id,
                'label'              => $provider->getLabel(),
                'supported_networks' => $provider->getSupportedNetworks(),
                'supported_assets'   => $provider->getSupportedAssets(),
                'facilitator_url'    => $provider->getFacilitatorUrl(),
                'is_active'          => $id === $activeId,
            ];
        }

        return $result;
    }
}
