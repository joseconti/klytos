<?php

/**
 * Klytos x402 — Bot Detector
 *
 * Detects AI bot requests via User-Agent matching and x402 header presence.
 *
 * @package Klytos
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace Klytos\Core\X402;

class BotDetector
{
    private array $botAgents;

    public function __construct( array $botAgents )
    {
        $this->botAgents = $botAgents;
    }

    public function isBot( array $serverVars ): bool
    {
        if ( $this->hasX402Headers( $serverVars ) ) {
            return true;
        }

        $userAgent = $serverVars['HTTP_USER_AGENT'] ?? '';

        return $userAgent !== '' && $this->matchesUserAgent( $userAgent );
    }

    public function hasX402Headers( array $serverVars ): bool
    {
        return !empty( $serverVars['HTTP_X_PAYMENT'] )
            || !empty( $serverVars['HTTP_X_PAYMENT_RESPONSE'] );
    }

    public function hasPaymentReceipt( array $serverVars ): bool
    {
        return !empty( $serverVars['HTTP_X_PAYMENT'] );
    }

    public function getPaymentReceipt( array $serverVars ): string
    {
        return $serverVars['HTTP_X_PAYMENT'] ?? '';
    }

    private function matchesUserAgent( string $userAgent ): bool
    {
        if ( empty( $this->botAgents ) ) {
            return false;
        }

        $pattern = '/' . implode( '|', array_map( 'preg_quote', $this->botAgents ) ) . '/i';

        return (bool) preg_match( $pattern, $userAgent );
    }

    public function buildHtaccessPattern(): string
    {
        return implode( '|', array_map( fn( string $a ) => preg_quote( $a, '/' ), $this->botAgents ) );
    }
}
