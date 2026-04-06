<?php

/**
 * Plugin Name: Klytos x402 Stripe
 * Plugin URI: https://klytos.io/plugins/klytos-x402-stripe
 * Description: Stripe payment provider for the Klytos x402 micropayments plugin. Uses Stripe PaymentIntents with crypto deposit mode to process USDC payments from AI bots.
 * Version: 1.0.0
 * Author: Jose Conti
 * Author URI: https://joseconti.com
 * Requires Klytos: 2.0.0
 * Requires PHP: 8.1
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: klytos-x402-stripe
 * Domain Path: /lang
 * Premium: false
 */

declare( strict_types=1 );

// ─── Dependency check ──────────────────────────────────────────
// ─── Load classes ──────────────────────────────────────────────
require_once __DIR__ . '/src/StripeProvider.php';

// ─── Translations ──────────────────────────────────────────────
klytos_register_translations( 'klytos-x402-stripe', __DIR__ . '/lang' );

// ─── Register provider ─────────────────────────────────────────
klytos_add_filter( 'x402.payment_providers', function ( array $providers ): array {
    $providers[] = new \KlytosX402Stripe\StripeProvider();
    return $providers;
}, 10 );
