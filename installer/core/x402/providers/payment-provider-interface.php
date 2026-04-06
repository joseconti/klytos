<?php

/**
 * Klytos x402 — Payment Provider Interface
 *
 * Every payment provider (facilitator) must implement this interface.
 * Third-party plugins register providers via the `x402.payment_providers` filter hook.
 *
 * @package Klytos
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace Klytos\Core\X402\Providers;

interface PaymentProviderInterface
{
    /** Unique identifier. E.g. 'x402-coinbase-cdp', 'x402-stripe'. */
    public function getId(): string;

    /** Human-readable label for the admin UI dropdown. */
    public function getLabel(): string;

    /** Blockchain networks supported. E.g. ['base', 'base-sepolia', 'polygon']. */
    public function getSupportedNetworks(): array;

    /** Payment assets supported. E.g. ['USDC', 'EURC']. */
    public function getSupportedAssets(): array;

    /** Return the facilitator URL, or null if not applicable. */
    public function getFacilitatorUrl(): ?string;

    /**
     * Settings fields this provider needs in the admin UI.
     * Each field: ['key', 'type', 'label', 'description', 'default', 'required'].
     */
    public function getSettingsFields(): array;

    /** Validate provider-specific settings. Returns ['valid' => bool, 'errors' => string[]]. */
    public function validateSettings( array $settings ): array;

    /**
     * Build the HTTP 402 response payload for a protected page.
     * Returns the full x402 response payload (root object with 'x402' key).
     */
    public function buildPaymentRequirements( array $pageConfig, array $globalConfig ): array;

    /**
     * Verify a payment receipt from the X-Payment header.
     * Returns ['valid' => bool, 'tx_hash' => string|null, 'error' => string|null].
     */
    public function verifyPayment( string $paymentHeader, array $pageConfig, array $globalConfig ): array;
}
