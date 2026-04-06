<?php

/**
 * Klytos x402 — Gate Handler
 *
 * Core request handler for the x402 payment gate.
 * Intercepts AI bot requests, checks x402 status, returns HTTP 402
 * or verifies payment and serves content.
 *
 * @package Klytos
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace Klytos\Core\X402;

use Klytos\Core\X402\Providers\ProviderRegistry;
use Klytos\Core\Helpers;

class Gate
{
    private Config           $config;
    private ProviderRegistry $providers;
    private BotDetector      $detector;
    private TransactionLog   $log;

    public function __construct(
        Config           $config,
        ProviderRegistry $providers,
        BotDetector      $detector,
        TransactionLog   $log
    ) {
        $this->config    = $config;
        $this->providers = $providers;
        $this->detector  = $detector;
        $this->log       = $log;
    }

    /**
     * Handle an incoming request for a protected resource.
     */
    public function handle( string $slug, string $format, array $server ): void
    {
        $slug = $this->sanitizeSlug( $slug );

        if ( empty( $slug ) ) {
            $this->sendError( 404, 'Page not found.' );
            return;
        }

        // Check if page exists.
        $outputPath = $this->getPublicPath();
        $ext        = $format === 'md' ? 'html.md' : 'html';

        $filePath = $outputPath . '/' . $slug . '/index.' . $ext;
        if ( !file_exists( $filePath ) ) {
            $filePath = $outputPath . '/' . $slug . '.' . $ext;
        }
        if ( !file_exists( $filePath ) ) {
            $this->sendError( 404, 'Page not found.' );
            return;
        }

        // Get effective x402 config.
        $effective = $this->config->getEffective( $slug );

        // Filter: x402.should_protect.
        $shouldProtect = klytos_apply_filters(
            'x402.should_protect',
            $effective['enabled'],
            $slug,
            ['headers' => $this->extractHeaders( $server ), 'server' => $server]
        );

        if ( !$shouldProtect ) {
            $this->serveFile( $filePath, $format );
            return;
        }

        // Check for payment receipt.
        if ( $this->detector->hasPaymentReceipt( $server ) ) {
            $this->handlePaymentVerification( $slug, $format, $filePath, $effective, $server );
            return;
        }

        $this->sendPaymentRequired( $slug, $format, $effective );
    }

    private function handlePaymentVerification(
        string $slug, string $format, string $filePath, array $effective, array $server
    ): void {
        $paymentHeader = $this->detector->getPaymentReceipt( $server );
        $globalConfig  = $this->config->getAll();
        $providerId    = $effective['provider_id'];

        try {
            $provider = $this->providers->resolve( $providerId );
        } catch ( \RuntimeException $e ) {
            $this->sendError( 500, $e->getMessage() );
            return;
        }

        $pageConfig = array_merge( $effective, ['format' => $format] );
        $result     = $provider->verifyPayment( $paymentHeader, $pageConfig, $globalConfig );

        if ( $result['valid'] ) {
            $priceUsd = $effective['price_usd'];
            $this->log->log( [
                'slug'           => $slug,
                'format'         => $format,
                'provider_id'    => $providerId,
                'bot_user_agent' => $server['HTTP_USER_AGENT'] ?? '',
                'bot_ip_hash'    => hash( 'sha256', $server['REMOTE_ADDR'] ?? '' ),
                'amount_usd'     => $priceUsd,
                'amount_raw'     => (string) (int) round( (float) $priceUsd * 1_000_000 ),
                'network'        => $globalConfig['network'] ?? 'base',
                'tx_hash'        => $result['tx_hash'] ?? '',
                'facilitator_ok' => true,
                'license_type'   => $effective['license_type'],
            ] );

            klytos_do_action( 'x402.payment_received', $slug, $priceUsd, $result['tx_hash'] ?? '', $server['HTTP_USER_AGENT'] ?? '' );
            $this->serveFile( $filePath, $format );
            return;
        }

        klytos_do_action( 'x402.payment_failed', $slug, $result['error'] ?? 'Unknown error', $server['HTTP_USER_AGENT'] ?? '' );
        $this->sendPaymentRequired( $slug, $format, $effective, $result['error'] );
    }

    private function sendPaymentRequired( string $slug, string $format, array $effective, ?string $error = null ): void
    {
        $globalConfig = $this->config->getAll();
        $providerId   = $effective['provider_id'];

        try {
            $provider = $this->providers->resolve( $providerId );
        } catch ( \RuntimeException $e ) {
            $this->sendError( 500, $e->getMessage() );
            return;
        }

        $pageConfig = array_merge( $effective, ['format' => $format] );
        $payload    = $provider->buildPaymentRequirements( $pageConfig, $globalConfig );

        $payload = klytos_apply_filters( 'x402.response_payload', $payload, $slug );

        if ( $error !== null && isset( $payload['x402'] ) ) {
            $payload['x402']['error'] = $error;
        }

        http_response_code( 402 );
        header( 'Content-Type: application/json' );
        header( 'X-Payment-Required: true' );
        header( 'X-Payment-Network: ' . ( $globalConfig['network'] ?? 'base' ) );
        header( 'X-Payment-Asset: USDC' );
        header( 'Cache-Control: no-store' );

        echo json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        exit;
    }

    private function serveFile( string $filePath, string $format ): void
    {
        $contentType = $format === 'md' ? 'text/markdown; charset=utf-8' : 'text/html; charset=utf-8';
        header( 'Content-Type: ' . $contentType );
        header( 'X-Served-By: klytos-x402' );
        readfile( $filePath );
        exit;
    }

    private function sendError( int $code, string $message ): void
    {
        http_response_code( $code );
        header( 'Content-Type: application/json' );
        echo json_encode( ['error' => $message] );
        exit;
    }

    private function sanitizeSlug( string $slug ): string
    {
        $slug = str_replace( ['..', "\0"], '', $slug );
        $slug = preg_replace( '/[^a-zA-Z0-9\-_\/]/', '', $slug );
        return trim( $slug, '/' );
    }

    private function extractHeaders( array $server ): array
    {
        $headers = [];
        foreach ( $server as $key => $value ) {
            if ( str_starts_with( $key, 'HTTP_' ) ) {
                $headers[str_replace( '_', '-', substr( $key, 5 ) )] = $value;
            }
        }
        return $headers;
    }

    private function getPublicPath(): string
    {
        return dirname( Helpers::getRootPath() );
    }
}
