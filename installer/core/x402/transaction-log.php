<?php

/**
 * Klytos x402 — Transaction Log
 *
 * Records payment transactions using the Klytos Storage API.
 * Stored per-day for efficient querying.
 *
 * @package Klytos
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace Klytos\Core\X402;

class TransactionLog
{
    private const COLLECTION = 'x402-transactions';

    public function log( array $transaction ): string
    {
        $id  = 'tx_' . bin2hex( random_bytes( 8 ) );
        $now = gmdate( 'Y-m-d\TH:i:s\Z' );

        $record = [
            'id'             => $id,
            'slug'           => $transaction['slug'] ?? '',
            'format'         => $transaction['format'] ?? 'html',
            'provider_id'    => $transaction['provider_id'] ?? '',
            'bot_user_agent' => $transaction['bot_user_agent'] ?? '',
            'bot_ip_hash'    => $transaction['bot_ip_hash'] ?? '',
            'amount_usd'     => $transaction['amount_usd'] ?? '0.00',
            'amount_raw'     => $transaction['amount_raw'] ?? '0',
            'network'        => $transaction['network'] ?? 'base',
            'tx_hash'        => $transaction['tx_hash'] ?? '',
            'facilitator_ok' => $transaction['facilitator_ok'] ?? true,
            'license_type'   => $transaction['license_type'] ?? 'inference',
            'created_at'     => $now,
        ];

        $dateKey       = gmdate( 'Y-m-d' );
        $dayCollection = self::COLLECTION . '/' . $dateKey;
        $storage       = klytos_storage();

        try {
            $dayRecords = $storage->read( $dayCollection, 'transactions' );
        } catch ( \Throwable ) {
            $dayRecords = ['date' => $dateKey, 'transactions' => []];
        }

        $dayRecords['transactions'][] = $record;
        $storage->write( $dayCollection, 'transactions', $dayRecords );

        return $id;
    }

    public function list( array $filters = [], int $limit = 50, int $offset = 0 ): array
    {
        $from    = $filters['from'] ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) );
        $to      = $filters['to']   ?? gmdate( 'Y-m-d' );
        $all     = [];
        $storage = klytos_storage();
        $current = $from;

        while ( $current <= $to ) {
            try {
                $day = $storage->read( self::COLLECTION . '/' . $current, 'transactions' );
                if ( isset( $day['transactions'] ) ) {
                    $all = array_merge( $all, $day['transactions'] );
                }
            } catch ( \Throwable ) {}
            $current = date( 'Y-m-d', strtotime( $current . ' +1 day' ) );
        }

        if ( !empty( $filters['slug'] ) )
            $all = array_filter( $all, fn( $tx ) => $tx['slug'] === $filters['slug'] );
        if ( !empty( $filters['provider_id'] ) )
            $all = array_filter( $all, fn( $tx ) => $tx['provider_id'] === $filters['provider_id'] );
        if ( !empty( $filters['bot_user_agent'] ) ) {
            $s = $filters['bot_user_agent'];
            $all = array_filter( $all, fn( $tx ) => stripos( $tx['bot_user_agent'], $s ) !== false );
        }

        usort( $all, fn( $a, $b ) => strcmp( $b['created_at'], $a['created_at'] ) );

        return ['transactions' => array_values( array_slice( $all, $offset, $limit ) ), 'total' => count( $all )];
    }

    public function getByDate( string $date ): array
    {
        try {
            $day = klytos_storage()->read( self::COLLECTION . '/' . $date, 'transactions' );
            return $day['transactions'] ?? [];
        } catch ( \Throwable ) {
            return [];
        }
    }
}
