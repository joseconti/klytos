<?php

/**
 * WPXMLParser — WordPress WXR (eXtended RSS) export parser.
 *
 * Uses XMLReader for memory-efficient streaming of large exports.
 * Extracts pages, posts, categories, tags, menus, media, and authors.
 *
 * @package KlytosImporter
 */

declare( strict_types=1 );

namespace KlytosImporter;

class WPXMLParser
{
    private string $filePath;

    /** @var array|null Cached parsed data after first analyze(). */
    private ?array $parsed = null;

    public function __construct( string $filePath )
    {
        if ( !ImportValidator::validateXmlFile( $filePath ) ) {
            throw new \InvalidArgumentException( "Invalid WordPress XML file: {$filePath}" );
        }

        $this->filePath = $filePath;
    }

    /**
     * Analyze the WXR file and return a structured summary.
     *
     * Returns the full analysis structure as documented in the architecture spec
     * (section 5.1 — klytos_import_analyze_wp_xml response).
     */
    public function analyze(): array
    {
        if ( $this->parsed !== null ) {
            return $this->buildSummary();
        }

        $this->parsed = [
            'wp_version' => '',
            'site_url'   => '',
            'site_title' => '',
            'items'      => [],
            'categories' => [],
            'tags'       => [],
            'menus'      => [],
            'authors'    => [],
        ];

        $reader = new \XMLReader();
        $prev   = libxml_use_internal_errors( true );

        $reader->open( $this->filePath, 'UTF-8', LIBXML_NONET | LIBXML_NOENT );

        while ( $reader->read() ) {
            if ( $reader->nodeType !== \XMLReader::ELEMENT ) {
                continue;
            }

            switch ( $reader->localName ) {
                case 'title':
                    if ( empty( $this->parsed['site_title'] ) && $reader->depth <= 3 ) {
                        $this->parsed['site_title'] = $this->readText( $reader );
                    }
                    break;

                case 'link':
                    if ( empty( $this->parsed['site_url'] ) && $reader->depth <= 3 ) {
                        $this->parsed['site_url'] = $this->readText( $reader );
                    }
                    break;

                case 'wxr_version':
                    $this->parsed['wp_version'] = $this->readText( $reader );
                    break;

                case 'wp_author':
                    $this->parsed['authors'][] = $this->parseAuthor( $reader );
                    break;

                case 'category':
                    if ( $reader->namespaceURI !== '' ) {
                        $this->parsed['categories'][] = $this->parseTaxonomyTerm( $reader, 'category' );
                    }
                    break;

                case 'tag':
                    if ( $reader->namespaceURI !== '' ) {
                        $this->parsed['tags'][] = $this->parseTaxonomyTerm( $reader, 'tag' );
                    }
                    break;

                case 'item':
                    $item = $this->parseItem( $reader );
                    if ( $item !== null ) {
                        $this->parsed['items'][] = $item;
                    }
                    break;
            }
        }

        $reader->close();
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );

        // Extract menus from nav_menu_item items.
        $this->extractMenus();

        return $this->buildSummary();
    }

    /**
     * Extract a single page/post by slug from the parsed data.
     *
     * Returns the page data structure documented in section 5.2
     * (klytos_import_fetch_wp_page response).
     */
    public function extractPage( string $slug ): array
    {
        if ( $this->parsed === null ) {
            $this->analyze();
        }

        foreach ( $this->parsed['items'] as $item ) {
            if ( $item['slug'] === $slug ) {
                $contentHtml = $item['content'] ?? '';
                $hasBlocks   = $this->detectGutenbergBlocks( $contentHtml );
                $shortcodes  = $this->detectShortcodes( $contentHtml );
                $media       = $this->extractMediaFromContent( $contentHtml );

                // Add featured image to media list.
                if ( !empty( $item['featured_image'] ) ) {
                    $media[] = [
                        'src'  => $item['featured_image'],
                        'alt'  => $item['title'] ?? '',
                        'type' => 'image',
                    ];
                }

                return [
                    'success'              => true,
                    'title'                => $item['title'] ?? '',
                    'slug'                 => $item['slug'],
                    'content_html'         => $contentHtml,
                    'status'               => $item['status'] ?? 'draft',
                    'date'                 => $item['date'] ?? '',
                    'author'               => $item['author'] ?? '',
                    'categories'           => $item['categories'] ?? [],
                    'tags'                 => $item['tags'] ?? [],
                    'featured_image'       => $item['featured_image'] ?? '',
                    'meta_description'     => $item['meta_description'] ?? '',
                    'template'             => $item['template'] ?? '',
                    'post_type'            => $item['post_type'] ?? 'page',
                    'menu_order'           => $item['menu_order'] ?? 0,
                    'parent_slug'          => $item['parent_slug'] ?? '',
                    'media'                => $media,
                    'has_gutenberg_blocks' => $hasBlocks,
                    'has_shortcodes'       => !empty( $shortcodes ),
                    'shortcodes_found'     => $shortcodes,
                ];
            }
        }

        throw new \RuntimeException( "Page with slug '{$slug}' not found in the WordPress export." );
    }

    /**
     * Build the summary response from parsed data.
     */
    private function buildSummary(): array
    {
        $pages   = [];
        $posts   = [];
        $cpts    = [];
        $media   = 0;

        foreach ( $this->parsed['items'] as $item ) {
            $type = $item['post_type'] ?? 'post';

            if ( $type === 'page' ) {
                $pages[] = [
                    'title'       => $item['title'],
                    'slug'        => $item['slug'],
                    'status'      => $item['status'],
                    'has_content' => !empty( $item['content'] ),
                ];
            } elseif ( $type === 'post' ) {
                $posts[] = [
                    'title'      => $item['title'],
                    'slug'       => $item['slug'],
                    'date'       => $item['date'],
                    'categories' => $item['categories'],
                ];
            } elseif ( $type === 'attachment' ) {
                $media++;
            } elseif ( $type !== 'nav_menu_item' ) {
                $cpts[$type] = ( $cpts[$type] ?? 0 ) + 1;
            }
        }

        return [
            'success'    => true,
            'source'     => 'wordpress',
            'wp_version' => $this->parsed['wp_version'],
            'site_url'   => $this->parsed['site_url'],
            'site_title' => $this->parsed['site_title'],
            'summary'    => [
                'pages'             => count( $pages ),
                'posts'             => count( $posts ),
                'categories'        => count( $this->parsed['categories'] ),
                'tags'              => count( $this->parsed['tags'] ),
                'menus'             => count( $this->parsed['menus'] ),
                'media_attachments' => $media,
                'authors'           => count( $this->parsed['authors'] ),
                'custom_post_types' => $cpts,
            ],
            'pages_list' => $pages,
            'posts_list' => $posts,
            'menus'      => $this->parsed['menus'],
        ];
    }

    /**
     * Parse a single <item> element from the WXR feed.
     */
    private function parseItem( \XMLReader $reader ): ?array
    {
        $item = [
            'title'            => '',
            'slug'             => '',
            'content'          => '',
            'excerpt'          => '',
            'date'             => '',
            'status'           => 'draft',
            'post_type'        => 'post',
            'author'           => '',
            'categories'       => [],
            'tags'             => [],
            'featured_image'   => '',
            'meta_description' => '',
            'template'         => '',
            'menu_order'       => 0,
            'parent_slug'      => '',
            'meta'             => [],
        ];

        $depth = $reader->depth;

        while ( $reader->read() ) {
            // End of <item>.
            if ( $reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'item' && $reader->depth === $depth ) {
                break;
            }

            if ( $reader->nodeType !== \XMLReader::ELEMENT ) {
                continue;
            }

            switch ( $reader->localName ) {
                case 'title':
                    $item['title'] = $this->readText( $reader );
                    break;

                case 'post_name':
                    $item['slug'] = $this->readText( $reader );
                    break;

                case 'encoded':
                    // <content:encoded> vs <excerpt:encoded>
                    $prefix = $reader->prefix ?? '';
                    $text   = $this->readText( $reader );
                    if ( $prefix === 'content' || str_contains( $reader->namespaceURI ?? '', 'content' ) ) {
                        $item['content'] = $text;
                    } elseif ( $prefix === 'excerpt' || str_contains( $reader->namespaceURI ?? '', 'excerpt' ) ) {
                        $item['excerpt'] = $text;
                    }
                    break;

                case 'post_date':
                    $item['date'] = $this->readText( $reader );
                    break;

                case 'status':
                    $item['status'] = $this->readText( $reader );
                    break;

                case 'post_type':
                    $item['post_type'] = $this->readText( $reader );
                    break;

                case 'creator':
                    $item['author'] = $this->readText( $reader );
                    break;

                case 'category':
                    $domain = $reader->getAttribute( 'domain' ) ?? '';
                    $nicename = $reader->getAttribute( 'nicename' ) ?? '';
                    $label = $this->readText( $reader );
                    if ( $domain === 'category' ) {
                        $item['categories'][] = $label;
                    } elseif ( $domain === 'post_tag' ) {
                        $item['tags'][] = $label;
                    }
                    break;

                case 'menu_order':
                    $item['menu_order'] = (int) $this->readText( $reader );
                    break;

                case 'post_parent':
                    $parentId = $this->readText( $reader );
                    if ( $parentId && $parentId !== '0' ) {
                        $item['parent_slug'] = $parentId; // Resolved later.
                    }
                    break;

                case 'postmeta':
                    $meta = $this->parsePostMeta( $reader );
                    if ( $meta !== null ) {
                        $item['meta'][$meta['key']] = $meta['value'];
                    }
                    break;

                case 'attachment_url':
                    $item['featured_image'] = $this->readText( $reader );
                    break;
            }
        }

        // Extract meta-based fields.
        if ( isset( $item['meta']['_wp_page_template'] ) ) {
            $item['template'] = $item['meta']['_wp_page_template'];
        }
        if ( isset( $item['meta']['_thumbnail_id'] ) ) {
            // Will be resolved from attachments map later.
            $item['_thumbnail_id'] = $item['meta']['_thumbnail_id'];
        }

        // Skip empty slugs (revisions, auto-drafts).
        if ( empty( $item['slug'] ) && $item['post_type'] !== 'attachment' ) {
            return null;
        }

        return $item;
    }

    /**
     * Parse a <wp:postmeta> element.
     */
    private function parsePostMeta( \XMLReader $reader ): ?array
    {
        $key   = '';
        $value = '';
        $depth = $reader->depth;

        while ( $reader->read() ) {
            if ( $reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'postmeta' && $reader->depth === $depth ) {
                break;
            }

            if ( $reader->nodeType !== \XMLReader::ELEMENT ) {
                continue;
            }

            if ( $reader->localName === 'meta_key' ) {
                $key = $this->readText( $reader );
            } elseif ( $reader->localName === 'meta_value' ) {
                $value = $this->readText( $reader );
            }
        }

        return !empty( $key ) ? ['key' => $key, 'value' => $value] : null;
    }

    /**
     * Parse a <wp:author> element.
     */
    private function parseAuthor( \XMLReader $reader ): array
    {
        $author = ['login' => '', 'email' => '', 'display_name' => ''];
        $depth  = $reader->depth;

        while ( $reader->read() ) {
            if ( $reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'wp_author' && $reader->depth === $depth ) {
                break;
            }
            if ( $reader->nodeType !== \XMLReader::ELEMENT ) {
                continue;
            }

            switch ( $reader->localName ) {
                case 'author_login':
                    $author['login'] = $this->readText( $reader );
                    break;
                case 'author_email':
                    $author['email'] = $this->readText( $reader );
                    break;
                case 'author_display_name':
                    $author['display_name'] = $this->readText( $reader );
                    break;
            }
        }

        return $author;
    }

    /**
     * Parse a taxonomy term (category or tag) from wp: namespace.
     */
    private function parseTaxonomyTerm( \XMLReader $reader, string $type ): array
    {
        $term  = ['slug' => '', 'name' => '', 'parent' => ''];
        $depth = $reader->depth;

        while ( $reader->read() ) {
            if ( $reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === $type && $reader->depth === $depth ) {
                break;
            }
            if ( $reader->nodeType !== \XMLReader::ELEMENT ) {
                continue;
            }

            $localName = $reader->localName;

            if ( str_contains( $localName, 'slug' ) || str_contains( $localName, 'nicename' ) ) {
                $term['slug'] = $this->readText( $reader );
            } elseif ( str_contains( $localName, 'name' ) || str_contains( $localName, 'cat_name' ) ) {
                $term['name'] = $this->readText( $reader );
            } elseif ( str_contains( $localName, 'parent' ) ) {
                $term['parent'] = $this->readText( $reader );
            }
        }

        return $term;
    }

    /**
     * Extract navigation menus from nav_menu_item items.
     */
    private function extractMenus(): void
    {
        $menuItems = [];

        foreach ( $this->parsed['items'] as $item ) {
            if ( ( $item['post_type'] ?? '' ) === 'nav_menu_item' ) {
                // Group by menu name (from categories).
                $menuName = $item['categories'][0] ?? 'Menu';
                $menuItems[$menuName][] = $item;
            }
        }

        $this->parsed['menus'] = [];
        foreach ( $menuItems as $name => $items ) {
            $this->parsed['menus'][] = [
                'name'        => $name,
                'items_count' => count( $items ),
            ];
        }
    }

    /**
     * Detect if content contains Gutenberg block comments.
     */
    private function detectGutenbergBlocks( string $content ): bool
    {
        return (bool) preg_match( '/<!--\s+wp:/', $content );
    }

    /**
     * Detect WordPress shortcodes in content.
     *
     * @return string[] Array of shortcode names found.
     */
    private function detectShortcodes( string $content ): array
    {
        if ( !preg_match_all( '/\[([a-z_-]+)[\s\]]/i', $content, $matches ) ) {
            return [];
        }

        return array_values( array_unique( $matches[1] ) );
    }

    /**
     * Extract media URLs from HTML content.
     *
     * @return array Array of {src, alt, type} objects.
     */
    private function extractMediaFromContent( string $content ): array
    {
        $media = [];

        // Images.
        if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
            foreach ( $matches[1] as $i => $src ) {
                $alt = '';
                if ( preg_match( '/alt=["\']([^"\']*)["\']/', $matches[0][$i], $altMatch ) ) {
                    $alt = $altMatch[1];
                }
                $media[] = ['src' => $src, 'alt' => $alt, 'type' => 'image'];
            }
        }

        // Videos.
        if ( preg_match_all( '/<(?:video|source)[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
            foreach ( $matches[1] as $src ) {
                $media[] = ['src' => $src, 'alt' => '', 'type' => 'video'];
            }
        }

        return $media;
    }

    /**
     * Read the text content of the current element.
     */
    private function readText( \XMLReader $reader ): string
    {
        if ( $reader->isEmptyElement ) {
            return '';
        }

        $text  = '';
        $depth = $reader->depth;
        $name  = $reader->localName;

        while ( $reader->read() ) {
            if ( $reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === $name && $reader->depth === $depth ) {
                break;
            }

            if ( $reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA ) {
                $text .= $reader->value;
            }
        }

        return trim( $text );
    }
}
