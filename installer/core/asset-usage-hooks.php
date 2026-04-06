<?php

/**
 * Klytos — Asset Usage Tracking Hooks
 *
 * Automatically tracks where assets are used by listening to
 * page and theme save/delete actions.
 *
 * @package Klytos
 * @since   0.18.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

use Klytos\Core\App;

// ──────────────────────────────────────────────────────────
//  page.after_save — scan page content for asset references
// ──────────────────────────────────────────────────────────

klytos_add_action( 'page.after_save', function ( array $page ) {
    $app          = App::getInstance();
    $assetManager = $app->getAssetManager();
    $pageSlug     = $page['slug'] ?? '';
    $pageTitle    = $page['title'] ?? $pageSlug;

    if ( empty( $pageSlug ) ) {
        return;
    }

    // Get previous usage records for this page.
    $previousUsages   = $assetManager->getAssetsForContext( 'page', $pageSlug );
    $previousAssetIds = array_map( fn( $u ) => $u['asset_id'], $previousUsages );

    // Scan HTML content for image paths.
    $contentHtml = $page['content_html'] ?? '';
    $foundPaths  = [];

    // Match <img src="..."> containing /assets/
    if ( preg_match_all( '/src=["\']([^"\']*\/assets\/[^"\']+)["\']/i', $contentHtml, $matches ) ) {
        foreach ( $matches[1] as $src ) {
            $pos = strpos( $src, 'assets/' );
            if ( $pos !== false ) {
                $foundPaths[] = substr( $src, $pos );
            }
        }
    }

    // Match CSS background-image: url(...) containing /assets/
    if ( preg_match_all( '/url\(["\']?([^"\')\s]*\/assets\/[^"\')\s]+)["\']?\)/i', $contentHtml, $matches ) ) {
        foreach ( $matches[1] as $url ) {
            $pos = strpos( $url, 'assets/' );
            if ( $pos !== false ) {
                $foundPaths[] = substr( $url, $pos );
            }
        }
    }

    // Also check data-asset-id attributes for direct references.
    if ( preg_match_all( '/data-asset-id=["\']([a-f0-9]+)["\']/i', $contentHtml, $matches ) ) {
        foreach ( $matches[1] as $directId ) {
            // Track directly by ID — skip path resolution.
            $assetManager->trackUsage( $directId, 'page', $pageSlug, $pageTitle, 'content_html' );
            $currentAssetIds[] = $directId;
        }
    }

    // Check featured_image field.
    if ( !empty( $page['featured_image'] ) ) {
        $pos = strpos( $page['featured_image'], 'assets/' );
        if ( $pos !== false ) {
            $foundPaths[] = substr( $page['featured_image'], $pos );
        }
    }

    // Check og_image field.
    if ( !empty( $page['og_image'] ) ) {
        $pos = strpos( $page['og_image'], 'assets/' );
        if ( $pos !== false ) {
            $foundPaths[] = substr( $page['og_image'], $pos );
        }
    }

    $foundPaths = array_unique( $foundPaths );

    // Resolve paths to asset IDs and register usage.
    $currentAssetIds = $currentAssetIds ?? [];
    foreach ( $foundPaths as $path ) {
        $asset = $assetManager->findAssetByPath( $path );
        if ( $asset ) {
            $currentAssetIds[] = $asset['id'];
            $assetManager->trackUsage( $asset['id'], 'page', $pageSlug, $pageTitle, 'content_html' );
        }
    }

    // Remove usage records for assets no longer referenced.
    foreach ( $previousAssetIds as $oldId ) {
        if ( !in_array( $oldId, $currentAssetIds, true ) ) {
            $assetManager->removeUsage( $oldId, 'page', $pageSlug );
        }
    }
}, 20 );

// ──────────────────────────────────────────────────────────
//  page.after_delete — clean up all usage for deleted page
// ──────────────────────────────────────────────────────────

klytos_add_action( 'page.after_delete', function ( string $slug ) {
    App::getInstance()->getAssetManager()->deleteUsageForContext( 'page', $slug );
}, 20 );

// ──────────────────────────────────────────────────────────
//  theme.after_save — scan theme config for asset references
// ──────────────────────────────────────────────────────────

klytos_add_action( 'theme.after_save', function ( array $themeConfig ) {
    $app          = App::getInstance();
    $assetManager = $app->getAssetManager();

    // Clear previous theme-related usage records.
    $assetManager->deleteUsageForContext( 'theme', 'global' );
    $assetManager->deleteUsageForContext( 'header', 'global' );
    $assetManager->deleteUsageForContext( 'footer', 'global' );
    $assetManager->deleteUsageForContext( 'favicon', 'global' );
    $assetManager->deleteUsageForContext( 'og_image', 'global' );

    // Map theme config keys to context types and fields.
    $themeFields = [
        'logo'             => ['type' => 'header',   'field' => 'logo'],
        'favicon'          => ['type' => 'favicon',  'field' => 'favicon'],
        'default_og_image' => ['type' => 'og_image', 'field' => 'default'],
        'background_image' => ['type' => 'theme',    'field' => 'background'],
    ];

    foreach ( $themeFields as $configKey => $meta ) {
        $value = $themeConfig[$configKey] ?? '';
        if ( !empty( $value ) ) {
            $pos = strpos( $value, 'assets/' );
            if ( $pos !== false ) {
                $path  = substr( $value, $pos );
                $asset = $assetManager->findAssetByPath( $path );
                if ( $asset ) {
                    $assetManager->trackUsage(
                        $asset['id'],
                        $meta['type'],
                        'global',
                        ucfirst( $meta['type'] ) . ' - ' . $meta['field'],
                        $meta['field']
                    );
                }
            }
        }
    }
}, 20 );
