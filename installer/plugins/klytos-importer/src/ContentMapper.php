<?php

/**
 * ContentMapper — HTML to Gutenberg or clean HTML conversion.
 *
 * Converts generic HTML (and WordPress shortcodes) into either Gutenberg block
 * markup or sanitized HTML, depending on the target post type's editor setting.
 * Use 'gutenberg' format for block editor, 'tinymce' for classic HTML editor.
 *
 * @package KlytosImporter
 */

declare( strict_types=1 );

namespace KlytosImporter;

class ContentMapper
{
    private bool $preserveClasses;

    /** @var string[] Warnings generated during conversion. */
    private array $warnings = [];

    /** @var string[] Unsupported elements found. */
    private array $unsupported = [];

    /** @var int Block counter. */
    private int $blockCount = 0;

    /**
     * Convert HTML content into the appropriate format for the target editor.
     *
     * @param string $html            Raw HTML content.
     * @param string $sourceType      Hint: "html", "wordpress", "markdown".
     * @param bool   $preserveClasses Keep original CSS classes in output.
     * @param string $outputFormat    Target format: "gutenberg" (default) or "tinymce".
     *
     * @return array {gutenberg_html, blocks_count, unsupported_elements, warnings, output_format}
     */
    public function convert( string $html, string $sourceType = 'html', bool $preserveClasses = false, string $outputFormat = 'gutenberg' ): array
    {
        $this->preserveClasses = $preserveClasses;
        $this->warnings        = [];
        $this->unsupported     = [];
        $this->blockCount      = 0;

        if ( empty( trim( $html ) ) ) {
            return [
                'success'              => true,
                'content_html'         => '',
                'blocks_count'         => 0,
                'unsupported_elements' => [],
                'warnings'             => [],
                'output_format'        => $outputFormat,
            ];
        }

        // ── TinyMCE output: clean HTML, no block comments ───
        if ( $outputFormat === 'tinymce' ) {
            return $this->convertToHtml( $html, $sourceType );
        }

        // ── Gutenberg output: full block markup ─────────────

        // Handle WordPress shortcodes first.
        if ( $sourceType === 'wordpress' ) {
            $html = $this->convertShortcodes( $html );
        }

        // Normalize to UTF-8.
        $html = mb_convert_encoding( $html, 'UTF-8', 'UTF-8' );

        $doc  = new \DOMDocument( '1.0', 'UTF-8' );
        $prev = libxml_use_internal_errors( true );
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><body>' . $html . '</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );

        $body = $doc->getElementsByTagName( 'body' )->item( 0 );
        if ( !$body ) {
            return [
                'success'              => true,
                'content_html'         => '',
                'blocks_count'         => 0,
                'unsupported_elements' => [],
                'warnings'             => ['Could not parse HTML content.'],
                'output_format'        => 'gutenberg',
            ];
        }

        $output = $this->processChildren( $body, $doc );

        return [
            'success'              => true,
            'content_html'         => trim( $output ),
            'blocks_count'         => $this->blockCount,
            'unsupported_elements' => array_values( array_unique( $this->unsupported ) ),
            'warnings'             => $this->warnings,
            'output_format'        => 'gutenberg',
        ];
    }

    /**
     * Convert HTML to clean, sanitized HTML for TinyMCE editor.
     *
     * Strips Gutenberg block comments, removes shortcodes (replacing with
     * their inner content), and sanitizes the output.
     */
    private function convertToHtml( string $html, string $sourceType ): array
    {
        // Strip any existing Gutenberg block comments.
        $html = preg_replace( '/<!--\s*\/?wp:[^>]*-->\s*/s', '', $html );

        // Convert WordPress shortcodes to their inner content or plain HTML.
        if ( $sourceType === 'wordpress' ) {
            $html = $this->stripShortcodes( $html );
        }

        // Sanitize the HTML.
        $html = ImportValidator::sanitizeHtml( $html );

        return [
            'success'              => true,
            'content_html'         => trim( $html ),
            'blocks_count'         => 0,
            'unsupported_elements' => [],
            'warnings'             => [],
            'output_format'        => 'tinymce',
        ];
    }

    /**
     * Strip WordPress shortcodes, keeping inner content where applicable.
     *
     * Unlike convertShortcodes() which converts to Gutenberg blocks,
     * this simply removes the shortcode wrappers for clean HTML output.
     */
    private function stripShortcodes( string $content ): string
    {
        // Self-closing shortcodes with media: convert to HTML tags.
        $content = preg_replace_callback(
            '/\[video\s+src=["\']([^"\']+)["\'][^\]]*\]/',
            fn( $m ) => '<video controls src="' . $m[1] . '"></video>',
            $content
        );
        $content = preg_replace_callback(
            '/\[audio\s+src=["\']([^"\']+)["\'][^\]]*\]/',
            fn( $m ) => '<audio controls src="' . $m[1] . '"></audio>',
            $content
        );

        // Paired shortcodes: keep inner content.
        $content = preg_replace( '/\[caption[^\]]*\](.*?)\[\/caption\]/s', '$1', $content );
        $content = preg_replace( '/\[embed\](.*?)\[\/embed\]/s', '$1', $content );
        $content = preg_replace( '/\[columns[^\]]*\]/', '', $content );
        $content = preg_replace( '/\[\/columns\]/', '', $content );
        $content = preg_replace( '/\[column[^\]]*\]/', '', $content );
        $content = preg_replace( '/\[\/column\]/', '', $content );

        // E-commerce shortcodes: remove entirely.
        $content = preg_replace( '/\[woocommerce_\w+[^\]]*\]/', '', $content );

        // Unknown shortcodes: keep inner content of paired, remove self-closing.
        $content = preg_replace( '/\[([a-z_-]+)[^\]]*\](.*?)\[\/\1\]/si', '$2', $content );
        $content = preg_replace( '/\[[a-z_-]+[^\]]*\]/i', '', $content );

        return $content;
    }

    /**
     * Process all child nodes of an element.
     */
    private function processChildren( \DOMNode $parent, \DOMDocument $doc ): string
    {
        $output = '';

        foreach ( $parent->childNodes as $node ) {
            if ( $node->nodeType === XML_TEXT_NODE ) {
                $text = trim( $node->textContent );
                if ( $text !== '' ) {
                    $output .= $this->wrapParagraph( htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ) );
                }
                continue;
            }

            if ( $node->nodeType === XML_COMMENT_NODE ) {
                // Preserve existing Gutenberg block comments.
                $output .= '<!--' . $node->textContent . '-->' . "\n";
                continue;
            }

            if ( $node->nodeType !== XML_ELEMENT_NODE ) {
                continue;
            }

            /** @var \DOMElement $node */
            $output .= $this->mapElement( $node, $doc );
        }

        return $output;
    }

    /**
     * Map a single DOM element to its Gutenberg block equivalent.
     */
    private function mapElement( \DOMElement $el, \DOMDocument $doc ): string
    {
        $tag = strtolower( $el->tagName );

        return match ( $tag ) {
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $this->mapHeading( $el, $doc ),
            'p'                                   => $this->mapParagraph( $el, $doc ),
            'img'                                 => $this->mapImage( $el ),
            'figure'                              => $this->mapFigure( $el, $doc ),
            'ul'                                  => $this->mapList( $el, $doc, false ),
            'ol'                                  => $this->mapList( $el, $doc, true ),
            'blockquote'                          => $this->mapQuote( $el, $doc ),
            'table'                               => $this->mapTable( $el, $doc ),
            'pre'                                 => $this->mapCode( $el, $doc ),
            'hr'                                  => $this->mapSeparator(),
            'video'                               => $this->mapVideo( $el ),
            'audio'                               => $this->mapAudio( $el ),
            'iframe'                              => $this->mapEmbed( $el ),
            'div', 'section', 'article', 'main', 'aside' => $this->mapGroup( $el, $doc ),
            'br'                                  => '',
            'span', 'strong', 'b', 'em', 'i',
            'u', 's', 'del', 'ins', 'mark',
            'small', 'sub', 'sup', 'a',
            'code', 'kbd', 'samp',
            'abbr', 'cite', 'q', 'time'          => $this->getInnerHtml( $el, $doc ),
            default                               => $this->mapDefault( $el, $doc ),
        };
    }

    // ─── Block mappers ──────────────────────────────────────────

    private function mapHeading( \DOMElement $el, \DOMDocument $doc ): string
    {
        $level   = (int) substr( $el->tagName, 1 );
        $content = $this->getInnerHtml( $el, $doc );
        $attrs   = json_encode( ['level' => $level] );
        $class   = $this->classAttr( $el, 'wp-block-heading' );

        $this->blockCount++;

        return "<!-- wp:heading {$attrs} -->\n"
            . "<h{$level}{$class}>{$content}</h{$level}>\n"
            . "<!-- /wp:heading -->\n\n";
    }

    private function mapParagraph( \DOMElement $el, \DOMDocument $doc ): string
    {
        $content = trim( $this->getInnerHtml( $el, $doc ) );
        if ( empty( $content ) ) {
            return '';
        }

        $this->blockCount++;

        return "<!-- wp:paragraph -->\n"
            . "<p>{$content}</p>\n"
            . "<!-- /wp:paragraph -->\n\n";
    }

    private function wrapParagraph( string $text ): string
    {
        if ( empty( trim( $text ) ) ) {
            return '';
        }

        $this->blockCount++;

        return "<!-- wp:paragraph -->\n"
            . "<p>{$text}</p>\n"
            . "<!-- /wp:paragraph -->\n\n";
    }

    private function mapImage( \DOMElement $el ): string
    {
        $src = $el->getAttribute( 'src' );
        $alt = $el->getAttribute( 'alt' );

        if ( empty( $src ) ) {
            return '';
        }

        $altAttr = $alt !== '' ? " alt=\"{$this->escAttr( $alt )}\"" : '';
        $this->blockCount++;

        return "<!-- wp:image -->\n"
            . "<figure class=\"wp-block-image\"><img src=\"{$this->escAttr( $src )}\"{$altAttr}/></figure>\n"
            . "<!-- /wp:image -->\n\n";
    }

    private function mapFigure( \DOMElement $el, \DOMDocument $doc ): string
    {
        $img        = $el->getElementsByTagName( 'img' )->item( 0 );
        $figcaption = $el->getElementsByTagName( 'figcaption' )->item( 0 );

        if ( !$img ) {
            // Figure without img — treat as group.
            return $this->mapGroup( $el, $doc );
        }

        $src     = $img->getAttribute( 'src' );
        $alt     = $img->getAttribute( 'alt' );
        $altAttr = $alt !== '' ? " alt=\"{$this->escAttr( $alt )}\"" : '';

        $captionHtml = '';
        if ( $figcaption ) {
            $captionText = trim( $this->getInnerHtml( $figcaption, $doc ) );
            if ( $captionText !== '' ) {
                $captionHtml = "<figcaption class=\"wp-element-caption\">{$captionText}</figcaption>";
            }
        }

        $this->blockCount++;

        return "<!-- wp:image -->\n"
            . "<figure class=\"wp-block-image\"><img src=\"{$this->escAttr( $src )}\"{$altAttr}/>{$captionHtml}</figure>\n"
            . "<!-- /wp:image -->\n\n";
    }

    private function mapList( \DOMElement $el, \DOMDocument $doc, bool $ordered ): string
    {
        $content = $this->getInnerHtml( $el, $doc );
        $tag     = $ordered ? 'ol' : 'ul';
        $attrs   = $ordered ? ' {"ordered":true}' : '';

        $this->blockCount++;

        return "<!-- wp:list{$attrs} -->\n"
            . "<{$tag}>{$content}</{$tag}>\n"
            . "<!-- /wp:list -->\n\n";
    }

    private function mapQuote( \DOMElement $el, \DOMDocument $doc ): string
    {
        $content = $this->getInnerHtml( $el, $doc );

        $this->blockCount++;

        return "<!-- wp:quote -->\n"
            . "<blockquote class=\"wp-block-quote\">{$content}</blockquote>\n"
            . "<!-- /wp:quote -->\n\n";
    }

    private function mapTable( \DOMElement $el, \DOMDocument $doc ): string
    {
        $content = $this->getInnerHtml( $el, $doc );

        $this->warnings[] = 'Table found — converted to wp:table block, verify formatting.';
        $this->blockCount++;

        return "<!-- wp:table -->\n"
            . "<figure class=\"wp-block-table\"><table>{$content}</table></figure>\n"
            . "<!-- /wp:table -->\n\n";
    }

    private function mapCode( \DOMElement $el, \DOMDocument $doc ): string
    {
        $code = $el->getElementsByTagName( 'code' )->item( 0 );
        $content = $code ? htmlspecialchars( $code->textContent, ENT_QUOTES, 'UTF-8' ) : htmlspecialchars( $el->textContent, ENT_QUOTES, 'UTF-8' );

        $this->blockCount++;

        return "<!-- wp:code -->\n"
            . "<pre class=\"wp-block-code\"><code>{$content}</code></pre>\n"
            . "<!-- /wp:code -->\n\n";
    }

    private function mapSeparator(): string
    {
        $this->blockCount++;

        return "<!-- wp:separator -->\n"
            . "<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n"
            . "<!-- /wp:separator -->\n\n";
    }

    private function mapVideo( \DOMElement $el ): string
    {
        $src = $el->getAttribute( 'src' );
        if ( empty( $src ) ) {
            $source = $el->getElementsByTagName( 'source' )->item( 0 );
            $src = $source ? $source->getAttribute( 'src' ) : '';
        }

        if ( empty( $src ) ) {
            return '';
        }

        $this->blockCount++;

        return "<!-- wp:video -->\n"
            . "<figure class=\"wp-block-video\"><video controls src=\"{$this->escAttr( $src )}\"></video></figure>\n"
            . "<!-- /wp:video -->\n\n";
    }

    private function mapAudio( \DOMElement $el ): string
    {
        $src = $el->getAttribute( 'src' );
        if ( empty( $src ) ) {
            $source = $el->getElementsByTagName( 'source' )->item( 0 );
            $src = $source ? $source->getAttribute( 'src' ) : '';
        }

        if ( empty( $src ) ) {
            return '';
        }

        $this->blockCount++;

        return "<!-- wp:audio -->\n"
            . "<figure class=\"wp-block-audio\"><audio controls src=\"{$this->escAttr( $src )}\"></audio></figure>\n"
            . "<!-- /wp:audio -->\n\n";
    }

    private function mapEmbed( \DOMElement $el ): string
    {
        $src = $el->getAttribute( 'src' );
        if ( empty( $src ) ) {
            return '';
        }

        // Detect provider.
        $provider = 'generic';
        if ( str_contains( $src, 'youtube.com' ) || str_contains( $src, 'youtu.be' ) ) {
            $provider = 'youtube';
        } elseif ( str_contains( $src, 'vimeo.com' ) ) {
            $provider = 'vimeo';
        }

        $attrs = json_encode( [
            'url'          => $src,
            'type'         => 'video',
            'providerNameSlug' => $provider,
        ] );

        $this->blockCount++;

        return "<!-- wp:embed {$attrs} -->\n"
            . "<figure class=\"wp-block-embed is-type-video is-provider-{$provider}\"><div class=\"wp-block-embed__wrapper\">\n"
            . "{$src}\n"
            . "</div></figure>\n"
            . "<!-- /wp:embed -->\n\n";
    }

    private function mapGroup( \DOMElement $el, \DOMDocument $doc ): string
    {
        $inner = $this->processChildren( $el, $doc );
        if ( empty( trim( $inner ) ) ) {
            return '';
        }

        $this->blockCount++;

        return "<!-- wp:group -->\n"
            . "<div class=\"wp-block-group\">\n"
            . $inner
            . "</div>\n"
            . "<!-- /wp:group -->\n\n";
    }

    private function mapDefault( \DOMElement $el, \DOMDocument $doc ): string
    {
        $tag = strtolower( $el->tagName );
        $this->unsupported[] = $tag;

        // Try to extract meaningful content anyway.
        $inner = $this->processChildren( $el, $doc );
        if ( !empty( trim( $inner ) ) ) {
            return $inner;
        }

        $text = trim( $el->textContent );
        if ( !empty( $text ) ) {
            return $this->wrapParagraph( htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ) );
        }

        return '';
    }

    // ─── Shortcode conversion ───────────────────────────────────

    /**
     * Convert WordPress shortcodes to Gutenberg blocks or raw HTML.
     */
    public function convertShortcodes( string $content ): string
    {
        // [gallery ids="1,2,3"]
        $content = preg_replace(
            '/\[gallery[^\]]*\]/',
            "<!-- wp:gallery -->\n<figure class=\"wp-block-gallery has-nested-images columns-default is-cropped\"></figure>\n<!-- /wp:gallery -->",
            $content
        );

        // [caption]...[/caption] → figure with image.
        $content = preg_replace_callback(
            '/\[caption[^\]]*\](.*?)\[\/caption\]/s',
            function ( array $m ) {
                $inner = $m[1];
                if ( preg_match( '/<img[^>]+>/', $inner, $imgMatch ) ) {
                    $caption = strip_tags( str_replace( $imgMatch[0], '', $inner ) );
                    $captionHtml = !empty( trim( $caption ) )
                        ? "<figcaption class=\"wp-element-caption\">" . trim( $caption ) . "</figcaption>"
                        : '';
                    return "<!-- wp:image -->\n<figure class=\"wp-block-image\">{$imgMatch[0]}{$captionHtml}</figure>\n<!-- /wp:image -->";
                }
                return $inner;
            },
            $content
        );

        // [video src="..."]
        $content = preg_replace_callback(
            '/\[video\s+src=["\']([^"\']+)["\'][^\]]*\]/',
            fn( $m ) => "<!-- wp:video -->\n<figure class=\"wp-block-video\"><video controls src=\"{$m[1]}\"></video></figure>\n<!-- /wp:video -->",
            $content
        );

        // [audio src="..."]
        $content = preg_replace_callback(
            '/\[audio\s+src=["\']([^"\']+)["\'][^\]]*\]/',
            fn( $m ) => "<!-- wp:audio -->\n<figure class=\"wp-block-audio\"><audio controls src=\"{$m[1]}\"></audio></figure>\n<!-- /wp:audio -->",
            $content
        );

        // [embed]URL[/embed]
        $content = preg_replace_callback(
            '/\[embed\](.*?)\[\/embed\]/s',
            fn( $m ) => "<!-- wp:embed {\"url\":\"" . trim( $m[1] ) . "\"} -->\n<figure class=\"wp-block-embed\"><div class=\"wp-block-embed__wrapper\">\n" . trim( $m[1] ) . "\n</div></figure>\n<!-- /wp:embed -->",
            $content
        );

        // [columns][column]...[/column][/columns]
        $content = preg_replace( '/\[columns[^\]]*\]/', "<!-- wp:columns -->\n<div class=\"wp-block-columns\">", $content );
        $content = preg_replace( '/\[\/columns\]/', "</div>\n<!-- /wp:columns -->", $content );
        $content = preg_replace( '/\[column[^\]]*\]/', "<!-- wp:column -->\n<div class=\"wp-block-column\">", $content );
        $content = preg_replace( '/\[\/column\]/', "</div>\n<!-- /wp:column -->", $content );

        // E-commerce shortcodes — skip entirely.
        $content = preg_replace( '/\[woocommerce_(?:cart|checkout|my_account|order_tracking)[^\]]*\]/', '', $content );

        // Unknown shortcodes → wp:html with warning.
        $content = preg_replace_callback(
            '/\[([a-z_-]+)[^\]]*\](?:(.*?)\[\/\1\])?/si',
            function ( array $m ) {
                $name  = $m[1];
                $inner = $m[0];
                $this->warnings[] = "Unknown shortcode [{$name}] preserved as raw HTML.";
                return "<!-- wp:html -->\n{$inner}\n<!-- /wp:html -->";
            },
            $content
        );

        return $content;
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /**
     * Get the inner HTML of a DOMElement.
     */
    private function getInnerHtml( \DOMElement $el, \DOMDocument $doc ): string
    {
        $html = '';
        foreach ( $el->childNodes as $child ) {
            $html .= $doc->saveHTML( $child );
        }
        return $html;
    }

    /**
     * Build a class attribute string, optionally preserving original classes.
     */
    private function classAttr( \DOMElement $el, string $baseClass ): string
    {
        $classes = $baseClass;

        if ( $this->preserveClasses && $el->hasAttribute( 'class' ) ) {
            $original = trim( $el->getAttribute( 'class' ) );
            if ( $original !== '' ) {
                $classes .= ' ' . $original;
            }
        }

        return " class=\"{$classes}\"";
    }

    /**
     * Escape an attribute value.
     */
    private function escAttr( string $value ): string
    {
        return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
    }
}
