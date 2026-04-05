<?php

/**
 * Klytos — HTML to Markdown Converter
 * Converts HTML content to clean Markdown for LLM discoverability files.
 *
 * Pure PHP implementation with regex and string manipulation.
 * No external dependencies. Deterministic, lightweight (~1ms per page).
 *
 * @package Klytos
 * @since   0.27.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

namespace Klytos\Core;

class HtmlToMarkdown
{
    /**
     * Convert HTML content to clean Markdown.
     *
     * @param  string $html Raw HTML content.
     * @return string Clean Markdown text.
     */
    public static function convert( string $html ): string
    {
        if ( empty( trim( $html ) ) ) {
            return '';
        }

        $html = self::stripNonContent( $html );
        $md   = self::convertElements( $html );
        $md   = self::cleanup( $md );

        return $md;
    }

    /**
     * Strip structural/non-content elements before conversion.
     *
     * Removes: script, style, noscript, nav, form, iframe,
     * Klytos block markers, HTML comments.
     *
     * @param  string $html Raw HTML.
     * @return string Cleaned HTML ready for conversion.
     */
    private static function stripNonContent( string $html ): string
    {
        // Remove script, style, noscript tags and their content.
        $html = preg_replace( '/<(script|style|noscript)\b[^>]*>.*?<\/\1>/si', '', $html );

        // Remove form tags and their content.
        $html = preg_replace( '/<form\b[^>]*>.*?<\/form>/si', '', $html );

        // Replace iframes with a placeholder.
        $html = preg_replace_callback(
            '/<iframe\b[^>]*\bsrc=["\']([^"\']*)["\'][^>]*>.*?<\/iframe>/si',
            function ( $m ) {
                return '[Embedded content: ' . $m[1] . ']';
            },
            $html
        );
        // Handle self-closing iframes.
        $html = preg_replace_callback(
            '/<iframe\b[^>]*\bsrc=["\']([^"\']*)["\'][^>]*\/?>/si',
            function ( $m ) {
                return '[Embedded content: ' . $m[1] . ']';
            },
            $html
        );

        // Remove Klytos block marker comments.
        $html = preg_replace( '/<!--\s*klytos:block:[^>]*-->/i', '', $html );

        // Remove all HTML comments.
        $html = preg_replace( '/<!--.*?-->/s', '', $html );

        // Remove nav, header, footer tags (structural, not content).
        $html = preg_replace( '/<(nav|header|footer)\b[^>]*>.*?<\/\1>/si', '', $html );

        return $html;
    }

    /**
     * Convert HTML elements to Markdown equivalents.
     *
     * @param  string $html Cleaned HTML.
     * @return string Markdown text.
     */
    private static function convertElements( string $html ): string
    {
        $md = $html;

        // --- Pre/code blocks (before inline code to avoid conflicts) ---
        $md = preg_replace_callback(
            '/<pre\b[^>]*>\s*<code\b[^>]*>(.*?)<\/code>\s*<\/pre>/si',
            function ( $m ) {
                $code = html_entity_decode( strip_tags( $m[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                return "\n\n```\n" . trim( $code ) . "\n```\n\n";
            },
            $md
        );

        // --- Tables ---
        $md = preg_replace_callback(
            '/<table\b[^>]*>(.*?)<\/table>/si',
            function ( $m ) {
                return self::convertTable( $m[0] );
            },
            $md
        );

        // --- Headings ---
        for ( $i = 6; $i >= 1; $i-- ) {
            $hashes = str_repeat( '#', $i );
            $md = preg_replace_callback(
                "/<h{$i}\b[^>]*>(.*?)<\/h{$i}>/si",
                function ( $m ) use ( $hashes ) {
                    $text = trim( strip_tags( $m[1] ) );
                    return "\n\n{$hashes} {$text}\n\n";
                },
                $md
            );
        }

        // --- Blockquotes ---
        $md = preg_replace_callback(
            '/<blockquote\b[^>]*>(.*?)<\/blockquote>/si',
            function ( $m ) {
                $inner = trim( strip_tags( $m[1] ) );
                $lines = explode( "\n", $inner );
                $quoted = array_map( function ( $line ) {
                    return '> ' . trim( $line );
                }, $lines );
                return "\n\n" . implode( "\n", $quoted ) . "\n\n";
            },
            $md
        );

        // --- Images (before links to avoid nested conflicts) ---
        $md = preg_replace_callback(
            '/<img\b[^>]*\bsrc=["\']([^"\']*)["\'][^>]*\balt=["\']([^"\']*)["\'][^>]*\/?>/si',
            function ( $m ) {
                return '![' . $m[2] . '](' . $m[1] . ')';
            },
            $md
        );
        // Handle img with alt before src.
        $md = preg_replace_callback(
            '/<img\b[^>]*\balt=["\']([^"\']*)["\'][^>]*\bsrc=["\']([^"\']*)["\'][^>]*\/?>/si',
            function ( $m ) {
                return '![' . $m[1] . '](' . $m[2] . ')';
            },
            $md
        );
        // Images without alt.
        $md = preg_replace_callback(
            '/<img\b[^>]*\bsrc=["\']([^"\']*)["\'][^>]*\/?>/si',
            function ( $m ) {
                return '![](' . $m[1] . ')';
            },
            $md
        );

        // --- Links ---
        $md = preg_replace_callback(
            '/<a\b[^>]*\bhref=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/si',
            function ( $m ) {
                $text = trim( strip_tags( $m[2] ) );
                $href = $m[1];
                if ( empty( $text ) ) {
                    return $href;
                }
                return '[' . $text . '](' . $href . ')';
            },
            $md
        );

        // --- Unordered lists ---
        $md = preg_replace_callback(
            '/<ul\b[^>]*>(.*?)<\/ul>/si',
            function ( $m ) {
                $items = '';
                preg_match_all( '/<li\b[^>]*>(.*?)<\/li>/si', $m[1], $lis );
                foreach ( $lis[1] as $li ) {
                    $text = trim( strip_tags( $li ) );
                    $items .= "- {$text}\n";
                }
                return "\n\n" . $items . "\n";
            },
            $md
        );

        // --- Ordered lists ---
        $md = preg_replace_callback(
            '/<ol\b[^>]*>(.*?)<\/ol>/si',
            function ( $m ) {
                $items = '';
                $n = 1;
                preg_match_all( '/<li\b[^>]*>(.*?)<\/li>/si', $m[1], $lis );
                foreach ( $lis[1] as $li ) {
                    $text = trim( strip_tags( $li ) );
                    $items .= "{$n}. {$text}\n";
                    $n++;
                }
                return "\n\n" . $items . "\n";
            },
            $md
        );

        // --- Inline code ---
        $md = preg_replace_callback(
            '/<code\b[^>]*>(.*?)<\/code>/si',
            function ( $m ) {
                $code = html_entity_decode( strip_tags( $m[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                return '`' . $code . '`';
            },
            $md
        );

        // --- Bold ---
        $md = preg_replace_callback(
            '/<(strong|b)\b[^>]*>(.*?)<\/\1>/si',
            function ( $m ) {
                $text = trim( $m[2] );
                return "**{$text}**";
            },
            $md
        );

        // --- Italic ---
        $md = preg_replace_callback(
            '/<(em|i)\b[^>]*>(.*?)<\/\1>/si',
            function ( $m ) {
                $text = trim( $m[2] );
                return "*{$text}*";
            },
            $md
        );

        // --- Horizontal rules ---
        $md = preg_replace( '/<hr\b[^>]*\/?>/si', "\n\n---\n\n", $md );

        // --- Line breaks ---
        $md = preg_replace( '/<br\s*\/?>/si', "\n", $md );

        // --- Paragraphs ---
        $md = preg_replace( '/<\/p>/si', "\n\n", $md );
        $md = preg_replace( '/<p\b[^>]*>/si', '', $md );

        // --- Remove decorative wrapper tags (preserve inner content) ---
        $md = preg_replace( '/<\/?(div|span|section|article|main|aside|figure|figcaption)\b[^>]*>/si', '', $md );

        // --- Strip any remaining HTML tags ---
        $md = strip_tags( $md );

        // --- Decode HTML entities ---
        $md = html_entity_decode( $md, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        return $md;
    }

    /**
     * Convert an HTML table to a Markdown pipe table.
     *
     * @param  string $tableHtml Full <table>...</table> HTML.
     * @return string Markdown table with pipes and separator.
     */
    private static function convertTable( string $tableHtml ): string
    {
        $rows = [];

        // Extract header row from <thead> if present.
        if ( preg_match( '/<thead\b[^>]*>(.*?)<\/thead>/si', $tableHtml, $thead ) ) {
            preg_match_all( '/<th\b[^>]*>(.*?)<\/th>/si', $thead[1], $ths );
            if ( ! empty( $ths[1] ) ) {
                $rows[] = array_map( function ( $cell ) {
                    return trim( strip_tags( $cell ) );
                }, $ths[1] );
            }
        }

        // Extract body rows from <tbody> or directly from <tr>.
        $bodyHtml = $tableHtml;
        if ( preg_match( '/<tbody\b[^>]*>(.*?)<\/tbody>/si', $tableHtml, $tbody ) ) {
            $bodyHtml = $tbody[1];
        }

        preg_match_all( '/<tr\b[^>]*>(.*?)<\/tr>/si', $bodyHtml, $trs );
        foreach ( $trs[1] as $trContent ) {
            // Skip if this is a header row we already processed.
            if ( preg_match( '/<th\b/i', $trContent ) && ! empty( $rows ) ) {
                continue;
            }

            preg_match_all( '/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/si', $trContent, $cells );
            if ( ! empty( $cells[1] ) ) {
                $rows[] = array_map( function ( $cell ) {
                    return trim( strip_tags( $cell ) );
                }, $cells[1] );
            }
        }

        if ( empty( $rows ) ) {
            return '';
        }

        // Calculate column count.
        $colCount = max( array_map( 'count', $rows ) );

        // Pad rows to equal column count.
        foreach ( $rows as &$row ) {
            while ( count( $row ) < $colCount ) {
                $row[] = '';
            }
        }
        unset( $row );

        // Build Markdown table.
        $mdTable = '';
        foreach ( $rows as $idx => $row ) {
            $mdTable .= '| ' . implode( ' | ', $row ) . " |\n";

            // Add separator after header row.
            if ( $idx === 0 ) {
                $sep = array_fill( 0, $colCount, '---' );
                $mdTable .= '| ' . implode( ' | ', $sep ) . " |\n";
            }
        }

        return "\n\n" . $mdTable . "\n";
    }

    /**
     * Clean up resulting Markdown: normalize whitespace,
     * remove excessive blank lines, trim.
     *
     * @param  string $markdown Raw converted Markdown.
     * @return string Polished Markdown.
     */
    private static function cleanup( string $markdown ): string
    {
        // Collapse 3+ consecutive newlines into 2.
        $markdown = preg_replace( '/\n{3,}/', "\n\n", $markdown );

        // Remove trailing whitespace on each line.
        $markdown = preg_replace( '/[ \t]+$/m', '', $markdown );

        // Remove leading/trailing whitespace from the whole document.
        $markdown = trim( $markdown );

        return $markdown;
    }
}
