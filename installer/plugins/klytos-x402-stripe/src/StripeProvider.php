<?php

/**
 * Klytos x402 Stripe — Payment Provider
 *
 * Payment provider using Stripe's x402 PaymentIntents API.
 * Creates PaymentIntents with crypto deposit mode, monitors on-chain
 * settlement, and verifies payments via deposit address caching.
 *
 * Requires: Stripe API key with crypto payments enabled.
 * API version: 2026-03-04.preview
 *
 * @package KlytosX402Stripe
 * @since   1.0.0
 * @see     https://docs.stripe.com/payments/machine/x402
 */

declare( strict_types=1 );

namespace KlytosX402Stripe;

use Klytos\Core\X402\Providers\PaymentProviderInterface;

class StripeProvider implements PaymentProviderInterface
{
    /**
     * Stripe API base URL.
     */
    private const API_BASE = 'https://api.stripe.com/v1';

    /**
     * Stripe API version required for x402 crypto payments.
     */
    private const API_VERSION = '2026-03-04.preview';

    /**
     * USDC contract addresses per network.
     */
    private const USDC_CONTRACTS = [
        'base'   => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
        'solana' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
    ];

    // ─── PaymentProviderInterface ───────────────────────────────

    public function getId(): string
    {
        return 'x402-stripe';
    }

    public function getLabel(): string
    {
        return 'Stripe x402';
    }

    public function getSupportedNetworks(): array
    {
        return ['base', 'solana'];
    }

    public function getSupportedAssets(): array
    {
        return ['USDC'];
    }

    public function getFacilitatorUrl(): ?string
    {
        // Stripe acts as its own facilitator via PaymentIntents API.
        return null;
    }

    public function getSettingsFields(): array
    {
        return [
            [
                'key'         => 'stripe_secret_key',
                'type'        => 'password',
                'label'       => __( 'klytos-x402-stripe.secret_key' ),
                'description' => __( 'klytos-x402-stripe.secret_key_desc' ),
                'default'     => '',
                'required'    => true,
            ],
        ];
    }

    public function validateSettings( array $settings ): array
    {
        $errors = [];

        $key = $settings['stripe_secret_key'] ?? '';
        if ( empty( $key ) ) {
            $errors[] = 'Stripe secret key is required.';
        } elseif ( !str_starts_with( $key, 'sk_' ) && !str_starts_with( $key, 'rk_' ) ) {
            $errors[] = 'Invalid Stripe key format. Must start with sk_ or rk_.';
        }

        return [
            'valid'  => empty( $errors ),
            'errors' => $errors,
        ];
    }

    public function buildPaymentRequirements( array $pageConfig, array $globalConfig ): array
    {
        $slug    = $pageConfig['slug'] ?? '';
        $format  = $pageConfig['format'] ?? 'html';
        $network = $globalConfig['network'] ?? 'base';

        $priceUsd    = $pageConfig['x402_price_usd'] ?? $globalConfig['default_price_usd'] ?? '0.01';
        $amountCents = $this->usdToCents( $priceUsd );
        $stripeKey   = $globalConfig['provider_settings']['stripe_secret_key'] ?? '';

        // Create a Stripe PaymentIntent with crypto deposit mode.
        $piData = $this->createPaymentIntent( $stripeKey, $amountCents, $network );

        if ( isset( $piData['error'] ) ) {
            return [
                'x402' => [
                    'version' => '2',
                    'accepts' => [],
                    'error'   => $piData['error'],
                ],
            ];
        }

        $depositAddress = $this->extractDepositAddress( $piData, $network );
        $assetContract  = self::USDC_CONTRACTS[$network] ?? self::USDC_CONTRACTS['base'];

        $ext      = $format === 'md' ? 'html.md' : 'html';
        $resource = '/' . $slug . '.' . $ext;
        $mimeType = $format === 'md' ? 'text/markdown' : 'text/html';

        $siteName    = klytos_get_option( 'site_name', '' );
        $title       = $pageConfig['title'] ?? $slug;
        $licenseType = $pageConfig['x402_license_type']
                       ?? $globalConfig['license']['default_type']
                       ?? 'inference';
        $licenseText = $globalConfig['license']['default_text']
                       ?? 'Content licensed for AI inference only. Not for training.';

        // Cache PI ID for later verification.
        $this->cachePaymentIntent( $depositAddress, $piData['id'] ?? '' );

        return [
            'x402' => [
                'version' => '2',
                'accepts' => [
                    [
                        'scheme'            => 'exact',
                        'network'           => $network,
                        'maxAmountRequired' => $this->usdToRawAmount( $priceUsd ),
                        'resource'          => $resource,
                        'description'       => "Access to page: {$title}",
                        'mimeType'          => $mimeType,
                        'payTo'             => $depositAddress,
                        'maxTimeoutSeconds' => 300,
                        'asset'             => $assetContract,
                        'extra'             => [
                            'license' => [
                                'type' => $licenseType,
                                'text' => $licenseText,
                                'spdx' => 'LicenseRef-x402-' . $licenseType,
                            ],
                            'name'                  => $title,
                            'site'                  => $siteName,
                            'generator'             => 'Klytos CMS',
                            'stripe_payment_intent' => $piData['id'] ?? '',
                        ],
                    ],
                ],
                'facilitator' => 'stripe',
            ],
        ];
    }

    public function verifyPayment( string $paymentHeader, array $pageConfig, array $globalConfig ): array
    {
        $stripeKey = $globalConfig['provider_settings']['stripe_secret_key'] ?? '';

        if ( empty( $stripeKey ) ) {
            return ['valid' => false, 'tx_hash' => null, 'error' => 'Stripe key not configured.'];
        }

        // Decode the payment header to extract the deposit address.
        $decoded        = json_decode( base64_decode( $paymentHeader, true ) ?: '', true );
        $depositAddress = $decoded['payload']['authorization']['to'] ?? '';

        if ( empty( $depositAddress ) ) {
            return ['valid' => false, 'tx_hash' => null, 'error' => 'Missing deposit address in payment header.'];
        }

        // Look up the PaymentIntent for this deposit address.
        $piId = $this->lookupPaymentIntent( $depositAddress, $stripeKey );

        if ( empty( $piId ) ) {
            return ['valid' => false, 'tx_hash' => null, 'error' => 'No PaymentIntent found for deposit address.'];
        }

        // Check PaymentIntent status via Stripe API.
        $pi = $this->retrievePaymentIntent( $stripeKey, $piId );

        if ( $pi === null ) {
            return ['valid' => false, 'tx_hash' => null, 'error' => 'Failed to retrieve PaymentIntent from Stripe.'];
        }

        $status = $pi['status'] ?? '';

        if ( $status === 'succeeded' ) {
            return [
                'valid'   => true,
                'tx_hash' => $pi['id'] ?? null,
                'error'   => null,
            ];
        }

        if ( $status === 'requires_action' || $status === 'processing' ) {
            return [
                'valid'   => false,
                'tx_hash' => null,
                'error'   => 'Payment pending on-chain confirmation. Status: ' . $status,
            ];
        }

        return [
            'valid'   => false,
            'tx_hash' => null,
            'error'   => 'PaymentIntent status: ' . $status,
        ];
    }

    // ─── Stripe API Helpers ─────────────────────────────────────

    /**
     * Create a Stripe PaymentIntent with crypto deposit mode.
     */
    private function createPaymentIntent( string $apiKey, int $amountCents, string $network ): array
    {
        $postFields = http_build_query( [
            'amount'                                                      => $amountCents,
            'currency'                                                    => 'usd',
            'payment_method_types[]'                                      => 'crypto',
            'payment_method_data[type]'                                   => 'crypto',
            'payment_method_options[crypto][mode]'                        => 'deposit',
            'payment_method_options[crypto][deposit_options][networks][]' => $network,
            'confirm'                                                     => 'true',
        ] );

        $ch = curl_init( self::API_BASE . '/payment_intents' );

        if ( $ch === false ) {
            return ['error' => 'Failed to init cURL.'];
        }

        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Stripe-Version: ' . self::API_VERSION,
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ] );

        $response = curl_exec( $ch );
        $httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $curlErr  = curl_error( $ch );
        curl_close( $ch );

        if ( $response === false ) {
            return ['error' => 'cURL error: ' . $curlErr];
        }

        $data = json_decode( (string) $response, true );

        if ( $httpCode !== 200 || isset( $data['error'] ) ) {
            $msg = $data['error']['message'] ?? "Stripe API returned HTTP {$httpCode}.";
            return ['error' => $msg];
        }

        return $data;
    }

    /**
     * Retrieve a PaymentIntent from Stripe.
     */
    private function retrievePaymentIntent( string $apiKey, string $piId ): ?array
    {
        $ch = curl_init( self::API_BASE . '/payment_intents/' . urlencode( $piId ) );

        if ( $ch === false ) {
            return null;
        }

        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Stripe-Version: ' . self::API_VERSION,
            ],
        ] );

        $response = curl_exec( $ch );
        curl_close( $ch );

        if ( $response === false ) {
            return null;
        }

        return json_decode( (string) $response, true );
    }

    /**
     * Extract the deposit address from a PaymentIntent response.
     */
    private function extractDepositAddress( array $pi, string $network ): string
    {
        $details = $pi['next_action']['crypto_display_details']['deposit_addresses'] ?? [];

        return $details[$network]['address'] ?? '';
    }

    /**
     * Cache the mapping deposit_address -> PaymentIntent ID.
     * Uses Klytos cache with 5 minute TTL.
     */
    private function cachePaymentIntent( string $depositAddress, string $piId ): void
    {
        if ( empty( $depositAddress ) || empty( $piId ) ) {
            return;
        }

        try {
            $cache = klytos_cache();
            $cache->set( 'x402_stripe_' . md5( $depositAddress ), json_encode( [
                'pi_id' => $piId,
            ] ), 300 );
        } catch ( \Throwable ) {
            // Cache unavailable.
        }
    }

    /**
     * Look up the PaymentIntent ID for a deposit address.
     */
    private function lookupPaymentIntent( string $depositAddress, string $apiKey ): string
    {
        // Try cache first.
        try {
            $cache  = klytos_cache();
            $cached = $cache->get( 'x402_stripe_' . md5( $depositAddress ) );

            if ( $cached !== null ) {
                $data = json_decode( $cached, true );
                return $data['pi_id'] ?? '';
            }
        } catch ( \Throwable ) {
            // Cache unavailable.
        }

        // Fallback: list recent PaymentIntents and search for matching deposit address.
        $ch = curl_init( self::API_BASE . '/payment_intents?limit=10' );

        if ( $ch === false ) {
            return '';
        }

        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Stripe-Version: ' . self::API_VERSION,
            ],
        ] );

        $response = curl_exec( $ch );
        curl_close( $ch );

        if ( $response === false ) {
            return '';
        }

        $data = json_decode( (string) $response, true );

        foreach ( $data['data'] ?? [] as $pi ) {
            $details = $pi['next_action']['crypto_display_details']['deposit_addresses'] ?? [];
            foreach ( $details as $networkAddresses ) {
                if ( ( $networkAddresses['address'] ?? '' ) === $depositAddress ) {
                    return $pi['id'];
                }
            }
        }

        return '';
    }

    // ─── Amount Helpers ─────────────────────────────────────────

    private function usdToCents( string $priceUsd ): int
    {
        return (int) round( (float) $priceUsd * 100 );
    }

    private function usdToRawAmount( string $priceUsd ): string
    {
        return (string) (int) round( (float) $priceUsd * 1_000_000 );
    }
}
