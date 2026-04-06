<?php

/**
 * Klytos x402 Coinbase CDP — Payment Provider
 *
 * Default payment provider using the x402.org facilitator (Coinbase CDP).
 * Supports Base, Base Sepolia (testnet), Polygon and Solana networks
 * with USDC as the payment asset.
 *
 * @package KlytosX402Coinbase
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace KlytosX402Coinbase;

use Klytos\Core\X402\Providers\PaymentProviderInterface;

class CoinbaseCdpProvider implements PaymentProviderInterface
{
    /**
     * USDC contract addresses per network.
     */
    private const USDC_CONTRACTS = [
        'base'         => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
        'base-sepolia' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
        'polygon'      => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
        'solana'       => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v',
    ];

    /**
     * USDC decimal places (6 for all networks).
     */
    private const USDC_DECIMALS = 6;

    // ─── PaymentProviderInterface ───────────────────────────────

    public function getId(): string
    {
        return 'x402-coinbase-cdp';
    }

    public function getLabel(): string
    {
        return 'Coinbase CDP (x402.org)';
    }

    public function getSupportedNetworks(): array
    {
        return ['base', 'base-sepolia', 'polygon', 'solana'];
    }

    public function getSupportedAssets(): array
    {
        return ['USDC'];
    }

    public function getFacilitatorUrl(): ?string
    {
        return 'https://x402.org/facilitator';
    }

    public function getSettingsFields(): array
    {
        return [
            [
                'key'         => 'facilitator_url',
                'type'        => 'url',
                'label'       => __( 'klytos-x402-coinbase.facilitator_url' ),
                'description' => __( 'klytos-x402-coinbase.facilitator_url_desc' ),
                'default'     => 'https://x402.org/facilitator',
                'required'    => true,
            ],
        ];
    }

    public function validateSettings( array $settings ): array
    {
        $errors = [];

        if ( isset( $settings['facilitator_url'] ) ) {
            $url = filter_var( $settings['facilitator_url'], FILTER_VALIDATE_URL );
            if ( $url === false ) {
                $errors[] = 'Invalid facilitator URL.';
            }
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

        $priceUsd      = $pageConfig['x402_price_usd'] ?? $globalConfig['default_price_usd'] ?? '0.01';
        $amountRaw     = $this->usdToRawAmount( $priceUsd );
        $walletAddress = $globalConfig['wallet_address'] ?? '';
        $facilitator   = $globalConfig['provider_settings']['facilitator_url']
                         ?? $this->getFacilitatorUrl();

        $licenseType = $pageConfig['x402_license_type']
                       ?? $globalConfig['license']['default_type']
                       ?? 'inference';
        $licenseText = $globalConfig['license']['default_text']
                       ?? 'Content licensed for AI inference only. Not for training.';

        $assetContract = self::USDC_CONTRACTS[$network] ?? self::USDC_CONTRACTS['base'];
        $ext           = $format === 'md' ? 'html.md' : 'html';
        $resource      = '/' . $slug . '.' . $ext;
        $mimeType      = $format === 'md' ? 'text/markdown' : 'text/html';

        $siteName = klytos_get_option( 'site_name', '' );
        $title    = $pageConfig['title'] ?? $slug;

        return [
            'x402' => [
                'version'     => '2',
                'accepts'     => [
                    [
                        'scheme'             => 'exact',
                        'network'            => $network,
                        'maxAmountRequired'  => $amountRaw,
                        'resource'           => $resource,
                        'description'        => "Access to page: {$title}",
                        'mimeType'           => $mimeType,
                        'payTo'              => $walletAddress,
                        'maxTimeoutSeconds'  => 300,
                        'asset'              => $assetContract,
                        'extra'              => [
                            'license' => [
                                'type' => $licenseType,
                                'text' => $licenseText,
                                'spdx' => 'LicenseRef-x402-' . $licenseType,
                            ],
                            'name'      => $title,
                            'site'      => $siteName,
                            'generator' => 'Klytos CMS',
                        ],
                    ],
                ],
                'facilitator' => $facilitator,
            ],
        ];
    }

    public function verifyPayment( string $paymentHeader, array $pageConfig, array $globalConfig ): array
    {
        $facilitator = $globalConfig['provider_settings']['facilitator_url']
                       ?? $this->getFacilitatorUrl();

        $priceUsd  = $pageConfig['x402_price_usd'] ?? $globalConfig['default_price_usd'] ?? '0.01';
        $amountRaw = $this->usdToRawAmount( $priceUsd );
        $network   = $globalConfig['network'] ?? 'base';
        $wallet    = $globalConfig['wallet_address'] ?? '';

        $payload = json_encode( [
            'payment' => $paymentHeader,
            'payTo'   => $wallet,
            'amount'  => $amountRaw,
            'network' => $network,
        ] );

        $ch = curl_init( rtrim( $facilitator, '/' ) . '/verify' );

        if ( $ch === false ) {
            return ['valid' => false, 'tx_hash' => null, 'error' => 'Failed to init cURL.'];
        }

        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ] );

        $response = curl_exec( $ch );
        $httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $curlErr  = curl_error( $ch );
        curl_close( $ch );

        if ( $response === false ) {
            return ['valid' => false, 'tx_hash' => null, 'error' => 'cURL error: ' . $curlErr];
        }

        $data = json_decode( (string) $response, true );

        if ( $httpCode === 200 && ( $data['valid'] ?? false ) === true ) {
            return [
                'valid'   => true,
                'tx_hash' => $data['tx_hash'] ?? $data['transactionHash'] ?? null,
                'error'   => null,
            ];
        }

        return [
            'valid'   => false,
            'tx_hash' => null,
            'error'   => $data['error'] ?? $data['message'] ?? "Facilitator returned HTTP {$httpCode}.",
        ];
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Convert a USD string (e.g. '0.01') to USDC raw amount (e.g. '10000').
     *
     * USDC has 6 decimals, so $0.01 = 10000.
     */
    private function usdToRawAmount( string $priceUsd ): string
    {
        $price     = (float) $priceUsd;
        $rawAmount = (int) round( $price * ( 10 ** self::USDC_DECIMALS ) );

        return (string) $rawAmount;
    }
}
