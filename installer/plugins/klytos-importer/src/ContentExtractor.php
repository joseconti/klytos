<?php

/**
 * ContentExtractor — Main content extraction from full HTML pages.
 *
 * Identifies and extracts the primary content area from a full HTML page,
 * stripping navigation, headers, footers, sidebars, scripts, and ads.
 * Uses a multi-level scoring algorithm per IMPORTER-ARCHITECTURE.md section 7.1.
 *
 * @package KlytosImporter
 */

declare( strict_types=1 );

namespace KlytosImporter;

class ContentExtractor
{
    /**
     * Selectors that indicate main content (positive scoring).
     */
    private const CONTENT_SELECTORS = [
        'main', 'article', '[role="main"]',
    ];

    /**
     * ID/class patterns for main content (high score).
     */
    private const CONTENT_PATTERNS = [
        'content', 'post', 'entry', 'article', 'main-content',
        'page-content', 'post-content', 'entry-content',
        'single-content', 'site-content',
    ];

    /**
     * ID/class patterns to exclude (negative scoring).
     */
    private const EXCLUDE_PATTERNS = [
        'nav', 'navigation', 'sidebar', 'footer', 'header', 'menu',
        'widget', 'comment', 'ad', 'advertisement', 'social',
        'share', 'related', 'cookie', 'popup', 'modal', 'banner',
        'breadcrumb', 'pagination', 'pager', 'search-form',
        'newsletter', 'subscribe',
    ];

    /**
     * Extract main content from a full HTML page.
     *
     * Returns the structure documented in IMPORTER-ARCHITECTURE.md
     * (klytos_import_fetch_page response).
     *
     * @return array {title, meta_description, og_image, main_content_html,
     *               media[], detected_lang, word_count, has_forms, has_video}
     */
    public function extract( string $html ): array
    {
        if ( empty( trim( $html ) ) ) {
            return $this->emptyResult();
        }

        $doc  = new \DOMDocument( '1.0', 'UTF-8' );
        $prev = libxml_use_internal_errors( true );
        $doc->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );

        $xpath = new \DOMXPath( $doc );

        // ─── Extract metadata ───────────────────────────────
        $title           = $this->extractTitle( $doc, $xpath );
        $metaDescription = $this->extractMetaContent( $xpath, 'description' );
        $ogImage         = $this->extractMetaContent( $xpath, 'og:image', 'property' );
        $detectedLang    = $this->extractLang( $doc, $xpath );

        // ─── Find main content element ──────────────────────
        $mainElement = $this->findMainContent( $doc, $xpath );

        if ( $mainElement === null ) {
            return array_merge( $this->emptyResult(), [
                'title'            => $title,
                'meta_description' => $metaDescription,
                'og_image'         => $ogImage,
                'detected_lang'    => $detectedLang,
            ] );
        }

        // ─── Remove unwanted children from main content ─────
        $this->removeExcludedElements( $mainElement, $xpath );

        // ─── Remove script and style tags ───────────────────
        $this->removeTagsByName( $mainElement, ['script', 'style', 'noscript', 'link', 'meta'] );

        // ─── Serialize to HTML ──────────────────────────────
        $contentHtml = '';
        foreach ( $mainElement->childNodes as $child ) {
            $contentHtml .= $doc->saveHTML( $child );
        }
        $contentHtml = trim( $contentHtml );

        // ─── Extract media ──────────────────────────────────
        $media = $this->extractMedia( $mainElement );

        // ─── Detect forms and videos ────────────────────────
        $hasForms = $mainElement->getElementsByTagName( 'form' )->length > 0;
        $hasVideo = $mainElement->getElementsByTagName( 'video' )->length > 0
                 || $mainElement->getElementsByTagName( 'iframe' )->length > 0;

        // ─── Word count ─────────────────────────────────────
        $textContent = trim( $mainElement->textContent );
        $wordCount   = str_word_count( $textContent );

        return [
            'title'             => $title,
            'meta_description'  => $metaDescription,
            'og_image'          => $ogImage,
            'main_content_html' => $contentHtml,
            'media'             => $media,
            'detected_lang'     => $detectedLang,
            'word_count'        => $wordCount,
            'has_forms'         => $hasForms,
            'has_video'         => $hasVideo,
        ];
    }

    /**
     * Find the main content element using a multi-level scoring algorithm.
     *
     * 1. Semantic elements: <main>, <article>, role="main" → highest priority.
     * 2. ID/class matching content patterns → high score.
     * 3. Exclude elements matching exclude patterns.
     * 4. Text density scoring (text-to-tag ratio).
     * 5. Fallback: largest <div> by text content.
     */
    private function findMainContent( \DOMDocument $doc, \DOMXPath $xpath ): ?\DOMElement
    {
        // Level 1: Semantic elements.
        $candidates = [
            $xpath->query( '//main' ),
            $xpath->query( '//*[@role="main"]' ),
            $xpath->query( '//article' ),
        ];

        foreach ( $candidates as $nodeList ) {
            if ( $nodeList->length > 0 ) {
                /** @var \DOMElement $node */
                $node = $nodeList->item( 0 );
                $textLen = strlen( trim( $node->textContent ) );
                if ( $textLen > 50 ) {
                    return $node;
                }
            }
        }

        // Level 2: ID/class pattern matching with scoring.
        $scored = [];
        $allElements = $xpath->query( '//*[@id or @class]' );

        foreach ( $allElements as $el ) {
            /** @var \DOMElement $el */
            $id    = strtolower( $el->getAttribute( 'id' ) );
            $class = strtolower( $el->getAttribute( 'class' ) );
            $combined = $id . ' ' . $class;

            $score = 0;

            // Positive scoring.
            foreach ( self::CONTENT_PATTERNS as $pattern ) {
                if ( str_contains( $combined, $pattern ) ) {
                    $score += 10;
                }
            }

            // Negative scoring.
            foreach ( self::EXCLUDE_PATTERNS as $pattern ) {
                if ( str_contains( $combined, $pattern ) ) {
                    $score -= 15;
                }
            }

            // Text density bonus.
            $text    = trim( $el->textContent );
            $textLen = strlen( $text );
            $htmlLen = strlen( $doc->saveHTML( $el ) );

            if ( $htmlLen > 0 && $textLen > 100 ) {
                $density = $textLen / $htmlLen;
                $score  += (int) ( $density * 10 );
            }

            if ( $score > 0 && $textLen > 50 ) {
                $scored[] = ['element' => $el, 'score' => $score, 'textLen' => $textLen];
            }
        }

        // Sort by score descending, then text length.
        usort( $scored, function ( $a, $b ) {
            $diff = $b['score'] - $a['score'];
            return $diff !== 0 ? $diff : $b['textLen'] - $a['textLen'];
        } );

        if ( !empty( $scored ) ) {
            return $scored[0]['element'];
        }

        // Level 5: Fallback — largest <div> by text content.
        $divs   = $xpath->query( '//div' );
        $best   = null;
        $bestLen = 0;

        foreach ( $divs as $div ) {
            /** @var \DOMElement $div */
            $textLen = strlen( trim( $div->textContent ) );
            if ( $textLen > $bestLen ) {
                $bestLen = $textLen;
                $best    = $div;
            }
        }

        return $best;
    }

    /**
     * Remove excluded child elements from the main content.
     */
    private function removeExcludedElements( \DOMElement $parent, \DOMXPath $xpath ): void
    {
        $toRemove = [];

        foreach ( $xpath->query( './/*[@id or @class]', $parent ) as $el ) {
            /** @var \DOMElement $el */
            $id    = strtolower( $el->getAttribute( 'id' ) );
            $class = strtolower( $el->getAttribute( 'class' ) );
            $combined = $id . ' ' . $class;

            foreach ( self::EXCLUDE_PATTERNS as $pattern ) {
                if ( str_contains( $combined, $pattern ) ) {
                    $toRemove[] = $el;
                    break;
                }
            }
        }

        // Also remove nav, header, footer, aside within the content.
        foreach ( ['nav', 'header', 'footer', 'aside'] as $tag ) {
            foreach ( $xpath->query( './/' . $tag, $parent ) as $el ) {
                $toRemove[] = $el;
            }
        }

        foreach ( $toRemove as $el ) {
            if ( $el->parentNode ) {
                $el->parentNode->removeChild( $el );
            }
        }
    }

    /**
     * Remove elements by tag name.
     */
    private function removeTagsByName( \DOMElement $parent, array $tags ): void
    {
        foreach ( $tags as $tag ) {
            $elements = $parent->getElementsByTagName( $tag );
            $toRemove = [];
            foreach ( $elements as $el ) {
                $toRemove[] = $el;
            }
            foreach ( $toRemove as $el ) {
                if ( $el->parentNode ) {
                    $el->parentNode->removeChild( $el );
                }
            }
        }
    }

    /**
     * Extract the page title from <title> tag.
     */
    private function extractTitle( \DOMDocument $doc, \DOMXPath $xpath ): string
    {
        $titles = $doc->getElementsByTagName( 'title' );
        if ( $titles->length > 0 ) {
            $title = trim( $titles->item( 0 )->textContent );

            // Remove common suffixes: " - Site Name", " | Site Name".
            $title = preg_replace( '/\s*[|\-–—]\s*[^|\-–—]+$/', '', $title );

            return trim( $title );
        }

        // Fallback: first <h1>.
        $h1s = $doc->getElementsByTagName( 'h1' );
        if ( $h1s->length > 0 ) {
            return trim( $h1s->item( 0 )->textContent );
        }

        return '';
    }

    /**
     * Extract meta tag content by name or property.
     */
    private function extractMetaContent( \DOMXPath $xpath, string $name, string $attribute = 'name' ): string
    {
        $query = "//meta[@{$attribute}='{$name}']/@content";
        $nodes = $xpath->query( $query );

        if ( $nodes->length > 0 ) {
            return trim( $nodes->item( 0 )->textContent );
        }

        return '';
    }

    /**
     * Detect the page language from <html lang=""> or meta tags.
     */
    private function extractLang( \DOMDocument $doc, \DOMXPath $xpath ): string
    {
        $htmls = $doc->getElementsByTagName( 'html' );
        if ( $htmls->length > 0 ) {
            $lang = $htmls->item( 0 )->getAttribute( 'lang' );
            if ( !empty( $lang ) ) {
                return strtolower( substr( $lang, 0, 2 ) );
            }
        }

        // Fallback: meta Content-Language.
        $nodes = $xpath->query( '//meta[@http-equiv="Content-Language"]/@content' );
        if ( $nodes->length > 0 ) {
            return strtolower( substr( trim( $nodes->item( 0 )->textContent ), 0, 2 ) );
        }

        return '';
    }

    /**
     * Extract media (images, videos) from a DOM element.
     *
     * @return array Array of {src, alt, type, context} objects.
     */
    private function extractMedia( \DOMElement $element ): array
    {
        $media = [];

        // Images.
        $images = $element->getElementsByTagName( 'img' );
        foreach ( $images as $img ) {
            /** @var \DOMElement $img */
            $src = $img->getAttribute( 'src' ) ?: $img->getAttribute( 'data-src' );
            if ( !empty( $src ) ) {
                $media[] = [
                    'src'     => $src,
                    'alt'     => $img->getAttribute( 'alt' ) ?: '',
                    'type'    => 'image',
                    'context' => 'content',
                ];
            }
        }

        // Videos.
        $videos = $element->getElementsByTagName( 'video' );
        foreach ( $videos as $video ) {
            /** @var \DOMElement $video */
            $src = $video->getAttribute( 'src' );
            if ( empty( $src ) ) {
                $source = $video->getElementsByTagName( 'source' )->item( 0 );
                $src = $source ? $source->getAttribute( 'src' ) : '';
            }
            if ( !empty( $src ) ) {
                $media[] = [
                    'src'     => $src,
                    'alt'     => '',
                    'type'    => 'video',
                    'context' => 'content',
                ];
            }
        }

        // Background images from inline styles.
        $allElements = $element->getElementsByTagName( '*' );
        foreach ( $allElements as $el ) {
            /** @var \DOMElement $el */
            $style = $el->getAttribute( 'style' );
            if ( !empty( $style ) && preg_match( '/background-image:\s*url\(["\']?([^"\')\s]+)["\']?\)/i', $style, $m ) ) {
                $media[] = [
                    'src'     => $m[1],
                    'alt'     => '',
                    'type'    => 'image',
                    'context' => 'background',
                ];
            }
        }

        return $media;
    }

    /**
     * Return an empty extraction result.
     */
    private function emptyResult(): array
    {
        return [
            'title'             => '',
            'meta_description'  => '',
            'og_image'          => '',
            'main_content_html' => '',
            'media'             => [],
            'detected_lang'     => '',
            'word_count'        => 0,
            'has_forms'         => false,
            'has_video'         => false,
        ];
    }
}
