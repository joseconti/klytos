<?php

/**
 * Klytos x402 — Statistics
 *
 * Aggregates transaction data for dashboard and MCP tools.
 *
 * @package Klytos
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace Klytos\Core\X402;

class Stats
{
    private TransactionLog $log;

    public function __construct( TransactionLog $log )
    {
        $this->log = $log;
    }

    public function getRevenue( string $from, string $to ): array
    {
        $result     = $this->log->list( ['from' => $from, 'to' => $to], 10000, 0 );
        $totalUsd   = 0.0;
        $byProvider = [];

        foreach ( $result['transactions'] as $tx ) {
            $amount     = (float) ( $tx['amount_usd'] ?? 0 );
            $totalUsd  += $amount;
            $pid        = $tx['provider_id'] ?? 'unknown';
            if ( !isset( $byProvider[$pid] ) ) $byProvider[$pid] = ['total_usd' => 0.0, 'count' => 0];
            $byProvider[$pid]['total_usd'] += $amount;
            $byProvider[$pid]['count']++;
        }

        foreach ( $byProvider as &$p ) $p['total_usd'] = number_format( $p['total_usd'], 4, '.', '' );

        return [
            'total_usd'         => number_format( $totalUsd, 4, '.', '' ),
            'transaction_count' => count( $result['transactions'] ),
            'by_provider'       => $byProvider,
        ];
    }

    public function getSummary(): array
    {
        $today = gmdate( 'Y-m-d' );
        return [
            'today' => $this->getRevenue( $today, $today ),
            'week'  => $this->getRevenue( gmdate( 'Y-m-d', strtotime( '-7 days' ) ), $today ),
            'month' => $this->getRevenue( gmdate( 'Y-m-d', strtotime( '-30 days' ) ), $today ),
            'total' => $this->getRevenue( gmdate( 'Y-m-d', strtotime( '-365 days' ) ), $today ),
        ];
    }

    public function getTopPages( int $limit = 10 ): array
    {
        $result = $this->log->list( ['from' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ), 'to' => gmdate( 'Y-m-d' )], 10000, 0 );
        $pages  = [];
        foreach ( $result['transactions'] as $tx ) {
            $s = $tx['slug'] ?? '';
            if ( !isset( $pages[$s] ) ) $pages[$s] = ['slug' => $s, 'count' => 0, 'total_usd' => 0.0];
            $pages[$s]['count']++;
            $pages[$s]['total_usd'] += (float) ( $tx['amount_usd'] ?? 0 );
        }
        usort( $pages, fn( $a, $b ) => $b['count'] <=> $a['count'] );
        $pages = array_slice( $pages, 0, $limit );
        foreach ( $pages as &$p ) $p['total_usd'] = number_format( $p['total_usd'], 4, '.', '' );
        return $pages;
    }

    public function getTopBots( int $limit = 10 ): array
    {
        $result = $this->log->list( ['from' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ), 'to' => gmdate( 'Y-m-d' )], 10000, 0 );
        $bots   = [];
        foreach ( $result['transactions'] as $tx ) {
            $key = explode( '/', explode( ' ', $tx['bot_user_agent'] ?? 'unknown' )[0] )[0];
            if ( !isset( $bots[$key] ) ) $bots[$key] = ['bot' => $key, 'count' => 0, 'total_usd' => 0.0];
            $bots[$key]['count']++;
            $bots[$key]['total_usd'] += (float) ( $tx['amount_usd'] ?? 0 );
        }
        usort( $bots, fn( $a, $b ) => $b['count'] <=> $a['count'] );
        $bots = array_slice( $bots, 0, $limit );
        foreach ( $bots as &$b ) $b['total_usd'] = number_format( $b['total_usd'], 4, '.', '' );
        return $bots;
    }

    public function getDailyRevenue( int $days = 30 ): array
    {
        $result = [];
        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $date  = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $txs   = $this->log->getByDate( $date );
            $total = 0.0;
            foreach ( $txs as $tx ) $total += (float) ( $tx['amount_usd'] ?? 0 );
            $result[] = ['date' => $date, 'total_usd' => number_format( $total, 4, '.', '' ), 'count' => count( $txs )];
        }
        return $result;
    }
}
