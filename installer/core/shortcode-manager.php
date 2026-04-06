<?php

/**
 * Klytos — Shortcode Manager
 * Process [shortcode attr="value"] syntax in page content during build.
 *
 * @package Klytos
 * @since   0.26.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 Jose Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class ShortcodeManager
{
    /** @var array<string, array{callback: callable, defaults: array, description: string}> */
    private array $shortcodes = [];

    /**
     * Register a shortcode.
     *
     * @param string   $tag         Shortcode tag (e.g. 'year', 'site_name').
     * @param callable $callback    Receives (array $attrs, string $content, string $tag) → string.
     * @param array    $defaults    Default attribute values.
     * @param string   $description Human-readable description.
     */
    public function register( string $tag, callable $callback, array $defaults = [], string $description = '' ): void
    {
        $this->shortcodes[$tag] = [
            'callback'    => $callback,
            'defaults'    => $defaults,
            'description' => $description,
        ];
        klytos_do_action( 'shortcode.registered', $tag );
    }

    /**
     * Unregister a shortcode.
     */
    public function unregister( string $tag ): void
    {
        unset( $this->shortcodes[$tag] );
    }

    /**
     * Check if a shortcode is registered.
     */
    public function exists( string $tag ): bool
    {
        return isset( $this->shortcodes[$tag] );
    }

    /**
     * List all registered shortcodes.
     *
     * @return array List of {tag, description, has_defaults}.
     */
    public function listAll(): array
    {
        $list = [];
        foreach ( $this->shortcodes as $tag => $def ) {
            $list[] = [
                'tag'          => $tag,
                'description'  => $def['description'],
                'has_defaults' => !empty( $def['defaults'] ),
            ];
        }
        return $list;
    }

    /**
     * Process all shortcodes in the given content string.
     *
     * Supports:
     *   - Self-closing: [tag attr="value"]
     *   - Enclosing:    [tag]content[/tag]
     *   - Nested shortcodes up to 3 levels deep.
     *
     * @param  string $content HTML content to process.
     * @return string Content with shortcodes replaced.
     */
    public function process( string $content ): string
    {
        $content = klytos_apply_filters( 'shortcode.pre_process', $content );

        if ( empty( $this->shortcodes ) || !str_contains( $content, '[' ) ) {
            return $content;
        }

        $tags = implode( '|', array_map( 'preg_quote', array_keys( $this->shortcodes ) ) );

        // Process up to 3 nesting levels.
        for ( $i = 0; $i < 3; $i++ ) {
            $before = $content;

            // Enclosing shortcodes: [tag attr="val"]content[/tag]
            $content = preg_replace_callback(
                '#\[(' . $tags . ')([^\]]*?)\](.*?)\[/\1\]#s',
                function ( array $m ) {
                    return $this->executeShortcode( $m[1], $m[2], $m[3] );
                },
                $content
            );

            // Self-closing shortcodes: [tag attr="val"]
            $content = preg_replace_callback(
                '#\[(' . $tags . ')([^\]]*?)\](?!\s*.*?\[/\1\])#s',
                function ( array $m ) {
                    return $this->executeShortcode( $m[1], $m[2], '' );
                },
                $content
            );

            // No more changes → stop early.
            if ( $content === $before ) {
                break;
            }
        }

        return $content;
    }

    /**
     * Execute a single shortcode.
     */
    private function executeShortcode( string $tag, string $attrString, string $content ): string
    {
        if ( !isset( $this->shortcodes[$tag] ) ) {
            return '[' . $tag . $attrString . ']' . $content . ( $content !== '' ? '[/' . $tag . ']' : '' );
        }

        $def   = $this->shortcodes[$tag];
        $attrs = $this->parseAttributes( $attrString );
        $attrs = array_merge( $def['defaults'], $attrs );

        $output = call_user_func( $def['callback'], $attrs, $content, $tag );

        // Allow plugins to filter the output.
        return klytos_apply_filters( 'shortcode.output', $output, $tag, $attrs );
    }

    /**
     * Parse shortcode attributes string into associative array.
     *
     * Supports: attr="value", attr='value', attr=value, and bare attr.
     */
    private function parseAttributes( string $text ): array
    {
        $attrs = [];
        $text  = trim( $text );
        if ( $text === '' ) {
            return $attrs;
        }

        // Match key="value", key='value', key=value, or bare key.
        preg_match_all(
            '/(\w+)\s*=\s*"([^"]*?)"|(\w+)\s*=\s*\'([^\']*?)\'|(\w+)\s*=\s*(\S+)|(\w+)/',
            $text,
            $matches,
            PREG_SET_ORDER
        );

        foreach ( $matches as $m ) {
            if ( !empty( $m[1] ) ) {
                $attrs[$m[1]] = $m[2];
            } elseif ( !empty( $m[3] ) ) {
                $attrs[$m[3]] = $m[4];
            } elseif ( !empty( $m[5] ) ) {
                $attrs[$m[5]] = $m[6];
            } elseif ( !empty( $m[7] ) ) {
                $attrs[$m[7]] = true;
            }
        }

        return $attrs;
    }

    /**
     * Register built-in shortcodes.
     */
    public function registerBuiltins( App $app ): void
    {
        $this->register( 'year', function (): string {
            return date( 'Y' );
        }, [], 'Current year (e.g. 2026).' );

        $this->register( 'site_name', function () use ( $app ): string {
            return Helpers::escHtml( $app->getSiteConfig()->getValue( 'site_name', '' ) );
        }, [], 'Site name from configuration.' );

        $this->register( 'page_count', function () use ( $app ): string {
            return (string) $app->getPages()->count( 'published' );
        }, [], 'Number of published pages.' );

        $this->register( 'current_date', function ( array $attrs ): string {
            $format = $attrs['format'] ?? 'Y-m-d';
            return date( $format );
        }, ['format' => 'Y-m-d'], 'Current date. Accepts format attribute.' );
    }
}
