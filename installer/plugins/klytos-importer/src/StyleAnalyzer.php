<?php

/**
 * StyleAnalyzer — CSS analysis and Klytos theme mapping.
 *
 * Analyzes the visual style of a website by extracting colors, fonts,
 * and layout properties from CSS (external stylesheets + inline styles).
 * Maps detected values to Klytos theme variables with confidence scores.
 *
 * @package KlytosImporter
 */

declare(strict_types=1);

namespace KlytosImporter;

class StyleAnalyzer
{
    /**
     * Analyze a website's visual style.
     *
     * @param string      $url        URL of a representative page (usually homepage).
     * @param string|null $cssContent Raw CSS content (if the AI already fetched it).
     * @param string|null $htmlContent Raw HTML content (if the AI already fetched it).
     *
     * @return array {detected_colors, detected_fonts, detected_layout, extra_css, confidence}
     */
    public function analyze( string $url, ?string $cssContent = null, ?string $htmlContent = null ): array
    {
        $html = $htmlContent ?? '';
        $css  = $cssContent ?? '';

        // Fetch the page if no content provided.
        if ( empty( $html ) && !empty( $url ) ) {
            $fetcher = new PageFetcher();
            try {
                $result = $fetcher->fetch( $url );
                $html   = $result['html'];
            } catch ( \Throwable ) {
                // Continue with empty HTML.
            }
        }

        // Extract CSS from HTML if no CSS provided.
        if ( empty( $css ) && !empty( $html ) ) {
            $css = $this->extractCssFromHtml( $html, $url );
        }

        $colors = $this->analyzeColors( $css );
        $fonts  = $this->analyzeFonts( $css, $html );
        $layout = $this->analyzeLayout( $css );

        $confidence = $this->calculateConfidence( $colors, $fonts, $layout );

        $mappedVars = array_merge(
            array_values( $colors ),
            array_values( $fonts ),
            array_values( $layout )
        );
        $extraCss = $this->generateExtraCss( $css, $mappedVars );

        return [
            'success'         => true,
            'detected_colors' => $colors,
            'detected_fonts'  => $fonts,
            'detected_layout' => $layout,
            'extra_css'       => $extraCss,
            'confidence'      => $confidence,
        ];
    }

    /**
     * Extract CSS from HTML <link> and <style> tags.
     */
    private function extractCssFromHtml( string $html, string $baseUrl ): string
    {
        $css = '';

        // Extract inline <style> blocks.
        if ( preg_match_all( '/<style[^>]*>(.*?)<\/style>/si', $html, $matches ) ) {
            foreach ( $matches[1] as $block ) {
                $css .= $block . "\n";
            }
        }

        // Extract external stylesheet URLs and fetch them.
        if ( preg_match_all( '/<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches ) ) {
            $fetcher = new PageFetcher();
            $count   = 0;

            foreach ( $matches[1] as $href ) {
                if ( $count >= 5 ) {
                    break; // Limit to 5 stylesheets.
                }

                $cssUrl = $this->resolveUrl( $href, $baseUrl );
                if ( empty( $cssUrl ) || !ImportValidator::validateUrl( $cssUrl ) ) {
                    continue;
                }

                try {
                    $result = $fetcher->fetch( $cssUrl );
                    if ( $result['status_code'] === 200 ) {
                        $css .= $result['html'] . "\n";
                        $count++;
                    }
                } catch ( \Throwable ) {
                    continue;
                }
            }
        }

        return $css;
    }

    /**
     * Analyze CSS for color declarations.
     *
     * Scans color, background-color, border-color, fill, and gradients.
     * Groups by frequency and usage context to map to theme variables.
     */
    private function analyzeColors( string $css ): array
    {
        $colors = [
            'background' => [],
            'text'       => [],
            'border'     => [],
            'accent'     => [],
        ];

        // Extract all color values.
        $patterns = [
            'background' => '/background(?:-color)?\s*:\s*([^;}{]+)/i',
            'text'       => '/(?<!background-)color\s*:\s*([^;}{]+)/i',
            'border'     => '/border(?:-color)?\s*:[^;]*?(#[0-9a-f]{3,8}|rgba?\([^)]+\)|hsla?\([^)]+\))/i',
            'accent'     => '/(?:accent-color|caret-color)\s*:\s*([^;}{]+)/i',
        ];

        foreach ( $patterns as $category => $pattern ) {
            if ( preg_match_all( $pattern, $css, $matches ) ) {
                foreach ( $matches[1] as $value ) {
                    $hex = $this->normalizeColor( trim( $value ) );
                    if ( $hex !== null ) {
                        $colors[$category][] = $hex;
                    }
                }
            }
        }

        // Also extract colors from link/button selectors for primary/accent detection.
        $linkColors = [];
        if ( preg_match_all( '/(?:^|\})[^{}]*(?:a\b|\.btn|button|\.button|\.cta)[^{]*\{[^}]*color\s*:\s*([^;}{]+)/im', $css, $matches ) ) {
            foreach ( $matches[1] as $value ) {
                $hex = $this->normalizeColor( trim( $value ) );
                if ( $hex !== null ) {
                    $linkColors[] = $hex;
                }
            }
        }

        // Frequency-based mapping.
        $bgFreq   = $this->frequencySort( $colors['background'] );
        $textFreq = $this->frequencySort( $colors['text'] );
        $linkFreq = $this->frequencySort( $linkColors );

        return [
            'primary'    => $linkFreq[0] ?? $this->findAccentColor( $bgFreq, $textFreq ) ?? '#2563eb',
            'secondary'  => $linkFreq[1] ?? '#1e40af',
            'accent'     => $this->findAccentColor( $bgFreq, $textFreq ) ?? '#f59e0b',
            'background' => $this->findLightColor( $bgFreq ) ?? '#ffffff',
            'surface'    => $this->findSurfaceColor( $bgFreq ) ?? '#f8fafc',
            'text'       => $this->findDarkColor( $textFreq ) ?? '#1e293b',
            'text_muted' => $textFreq[1] ?? '#64748b',
            'border'     => $this->frequencySort( $colors['border'] )[0] ?? '#e2e8f0',
        ];
    }

    /**
     * Analyze CSS for font declarations.
     */
    private function analyzeFonts( string $css, string $html ): array
    {
        $bodyFont    = '';
        $headingFont = '';
        $bodyWeight  = '400';
        $headingWeight = '700';
        $baseSize    = '16px';
        $googleFontsUrl = '';

        // Extract Google Fonts URL from HTML.
        if ( preg_match( '/href=["\']([^"\']*fonts\.googleapis\.com[^"\']*)["\']/', $html, $m ) ) {
            $googleFontsUrl = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
        }

        // Body font.
        if ( preg_match( '/body[^{]*\{[^}]*font-family\s*:\s*([^;}{]+)/i', $css, $m ) ) {
            $bodyFont = $this->cleanFontFamily( $m[1] );
        }

        // Heading font.
        if ( preg_match( '/h[1-6][^{]*\{[^}]*font-family\s*:\s*([^;}{]+)/i', $css, $m ) ) {
            $headingFont = $this->cleanFontFamily( $m[1] );
        }

        // If no heading font found, use body font.
        if ( empty( $headingFont ) ) {
            $headingFont = $bodyFont;
        }
        if ( empty( $bodyFont ) && !empty( $headingFont ) ) {
            $bodyFont = $headingFont;
        }

        // Font weights.
        if ( preg_match( '/body[^{]*\{[^}]*font-weight\s*:\s*(\d+|bold|normal)/i', $css, $m ) ) {
            $bodyWeight = $this->normalizeWeight( $m[1] );
        }
        if ( preg_match( '/h[1-6][^{]*\{[^}]*font-weight\s*:\s*(\d+|bold|normal)/i', $css, $m ) ) {
            $headingWeight = $this->normalizeWeight( $m[1] );
        }

        // Base font size.
        if ( preg_match( '/(?:body|html|:root)[^{]*\{[^}]*font-size\s*:\s*([^;}{]+)/i', $css, $m ) ) {
            $baseSize = trim( $m[1] );
        }

        return [
            'heading'          => $headingFont ?: 'system-ui',
            'body'             => $bodyFont ?: 'system-ui',
            'heading_weight'   => $headingWeight,
            'body_weight'      => $bodyWeight,
            'base_size'        => $baseSize,
            'google_fonts_url' => $googleFontsUrl,
        ];
    }

    /**
     * Analyze CSS for layout properties.
     */
    private function analyzeLayout( string $css ): array
    {
        $maxWidth     = '1200px';
        $headerStyle  = 'static';
        $borderRadius = '8px';
        $spacingUnit  = '1rem';

        // Container max-width.
        if ( preg_match( '/(?:\.container|\.wrapper|\.content|main|\.main)[^{]*\{[^}]*max-width\s*:\s*([^;}{]+)/i', $css, $m ) ) {
            $maxWidth = trim( $m[1] );
        }

        // Header position.
        if ( preg_match( '/(?:header|\.header|\.site-header|\.navbar|nav)[^{]*\{[^}]*position\s*:\s*(fixed|sticky)/i', $css, $m ) ) {
            $headerStyle = strtolower( $m[1] );
        }

        // Border radius.
        $radiusValues = [];
        if ( preg_match_all( '/border-radius\s*:\s*([^;}{]+)/i', $css, $matches ) ) {
            foreach ( $matches[1] as $val ) {
                $cleaned = trim( explode( ' ', trim( $val ) )[0] );
                if ( preg_match( '/^\d+/', $cleaned ) ) {
                    $radiusValues[] = $cleaned;
                }
            }
        }
        if ( !empty( $radiusValues ) ) {
            $borderRadius = $this->frequencySort( $radiusValues )[0] ?? '8px';
        }

        // Spacing unit from padding/margin.
        $spacingValues = [];
        if ( preg_match_all( '/(?:padding|margin|gap)\s*:\s*([^;}{]+)/i', $css, $matches ) ) {
            foreach ( $matches[1] as $val ) {
                $first = trim( explode( ' ', trim( $val ) )[0] );
                if ( preg_match( '/^\d+rem$/', $first ) ) {
                    $spacingValues[] = $first;
                }
            }
        }
        if ( !empty( $spacingValues ) ) {
            $spacingUnit = $this->frequencySort( $spacingValues )[0] ?? '1rem';
        }

        return [
            'max_width'     => $maxWidth,
            'header_style'  => $headerStyle,
            'border_radius' => $borderRadius,
            'spacing_unit'  => $spacingUnit,
        ];
    }

    /**
     * Calculate confidence scores for each category.
     */
    private function calculateConfidence( array $colors, array $fonts, array $layout ): array
    {
        // Colors: higher confidence if we found non-default values.
        $colorScore = 0.5;
        $defaults = ['#2563eb', '#1e40af', '#f59e0b', '#ffffff', '#f8fafc', '#1e293b', '#64748b', '#e2e8f0'];
        $nonDefault = 0;
        foreach ( $colors as $val ) {
            if ( !in_array( $val, $defaults, true ) ) {
                $nonDefault++;
            }
        }
        $colorScore = min( 1.0, 0.3 + ( $nonDefault * 0.1 ) );

        // Fonts: higher if we found actual font families.
        $fontScore = 0.5;
        if ( $fonts['body'] !== 'system-ui' ) {
            $fontScore += 0.2;
        }
        if ( $fonts['heading'] !== 'system-ui' ) {
            $fontScore += 0.15;
        }
        if ( !empty( $fonts['google_fonts_url'] ) ) {
            $fontScore += 0.15;
        }
        $fontScore = min( 1.0, $fontScore );

        // Layout: higher if we found specific values.
        $layoutScore = 0.5;
        if ( $layout['max_width'] !== '1200px' ) {
            $layoutScore += 0.15;
        }
        if ( $layout['header_style'] !== 'static' ) {
            $layoutScore += 0.15;
        }
        $layoutScore = min( 1.0, $layoutScore );

        return [
            'colors' => round( $colorScore, 2 ),
            'fonts'  => round( $fontScore, 2 ),
            'layout' => round( $layoutScore, 2 ),
        ];
    }

    /**
     * Generate extra CSS for rules that don't map to theme variables.
     */
    private function generateExtraCss( string $css, array $mappedValues ): string
    {
        $extra = [];

        // Extract gradient declarations.
        if ( preg_match_all( '/[^{}]+\{[^}]*(?:linear-gradient|radial-gradient)\([^}]+\}/i', $css, $matches ) ) {
            foreach ( $matches[0] as $rule ) {
                $extra[] = trim( $rule );
            }
        }

        // Extract animation/keyframe rules.
        if ( preg_match_all( '/@keyframes\s+[^{]+\{[^}]+(?:\{[^}]+\}[^}]*)+\}/i', $css, $matches ) ) {
            foreach ( $matches[0] as $rule ) {
                $extra[] = trim( $rule );
            }
        }

        if ( empty( $extra ) ) {
            return '';
        }

        return "/* Additional CSS rules that don't map to theme variables */\n" . implode( "\n\n", $extra );
    }

    // ─── Color helpers ──────────────────────────────────────────

    /**
     * Normalize a CSS color value to hex.
     */
    private function normalizeColor( string $value ): ?string
    {
        $value = trim( $value );

        // Already hex.
        if ( preg_match( '/^#([0-9a-f]{3,8})$/i', $value ) ) {
            return strtolower( $value );
        }

        // rgb/rgba.
        if ( preg_match( '/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/', $value, $m ) ) {
            return sprintf( '#%02x%02x%02x', (int) $m[1], (int) $m[2], (int) $m[3] );
        }

        // Named colors (common ones).
        $named = [
            'white' => '#ffffff', 'black' => '#000000', 'red' => '#ff0000',
            'blue' => '#0000ff', 'green' => '#008000', 'transparent' => null,
            'inherit' => null, 'currentcolor' => null, 'initial' => null,
            'unset' => null, 'revert' => null,
        ];

        $lower = strtolower( $value );
        if ( isset( $named[$lower] ) ) {
            return $named[$lower];
        }

        return null;
    }

    /**
     * Sort values by frequency (most common first).
     *
     * @return string[]
     */
    private function frequencySort( array $values ): array
    {
        if ( empty( $values ) ) {
            return [];
        }

        $counts = array_count_values( $values );
        arsort( $counts );

        return array_keys( $counts );
    }

    /**
     * Find the lightest color (likely background).
     */
    private function findLightColor( array $sortedColors ): ?string
    {
        foreach ( $sortedColors as $color ) {
            if ( $this->isLightColor( $color ) ) {
                return $color;
            }
        }
        return $sortedColors[0] ?? null;
    }

    /**
     * Find a surface color (slightly darker than background).
     */
    private function findSurfaceColor( array $sortedColors ): ?string
    {
        foreach ( $sortedColors as $color ) {
            $brightness = $this->colorBrightness( $color );
            if ( $brightness > 200 && $brightness < 250 ) {
                return $color;
            }
        }
        return null;
    }

    /**
     * Find the darkest color (likely text).
     */
    private function findDarkColor( array $sortedColors ): ?string
    {
        foreach ( $sortedColors as $color ) {
            if ( !$this->isLightColor( $color ) ) {
                return $color;
            }
        }
        return $sortedColors[0] ?? null;
    }

    /**
     * Find an accent/primary color (not too light, not too dark).
     */
    private function findAccentColor( array $bgColors, array $textColors ): ?string
    {
        // Look for saturated colors that aren't near-white or near-black.
        $all = array_merge( $bgColors, $textColors );
        foreach ( $all as $color ) {
            $brightness = $this->colorBrightness( $color );
            if ( $brightness > 50 && $brightness < 200 ) {
                return $color;
            }
        }
        return null;
    }

    /**
     * Check if a color is light (brightness > 180).
     */
    private function isLightColor( string $hex ): bool
    {
        return $this->colorBrightness( $hex ) > 180;
    }

    /**
     * Calculate perceived brightness of a hex color (0-255).
     */
    private function colorBrightness( string $hex ): int
    {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( strlen( $hex ) < 6 ) {
            return 128;
        }

        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );

        // Perceived brightness formula.
        return (int) ( ( $r * 299 + $g * 587 + $b * 114 ) / 1000 );
    }

    // ─── Font helpers ───────────────────────────────────────────

    /**
     * Clean a font-family CSS value to just the primary font name.
     */
    private function cleanFontFamily( string $value ): string
    {
        $value = trim( $value );

        // Take the first font in the stack.
        $parts = explode( ',', $value );
        $first = trim( $parts[0], " \t\n\r\0\x0B\"'" );

        // Skip generic families.
        $generic = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui', 'ui-sans-serif', 'ui-serif', 'ui-monospace'];
        if ( in_array( strtolower( $first ), $generic, true ) ) {
            return count( $parts ) > 1 ? 'system-ui' : $first;
        }

        return $first;
    }

    /**
     * Normalize font weight keywords to numeric values.
     */
    private function normalizeWeight( string $weight ): string
    {
        return match ( strtolower( trim( $weight ) ) ) {
            'normal' => '400',
            'bold'   => '700',
            default  => trim( $weight ),
        };
    }

    // ─── URL helper ─────────────────────────────────────────────

    /**
     * Resolve a potentially relative URL.
     */
    private function resolveUrl( string $href, string $baseUrl ): string
    {
        if ( preg_match( '#^https?://#i', $href ) ) {
            return $href;
        }
        if ( str_starts_with( $href, '//' ) ) {
            return ( parse_url( $baseUrl, PHP_URL_SCHEME ) ?? 'https' ) . ':' . $href;
        }
        if ( str_starts_with( $href, '/' ) ) {
            $parsed = parse_url( $baseUrl );
            return ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' ) . $href;
        }
        return $baseUrl . '/' . $href;
    }
}
