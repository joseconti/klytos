<?php

/**
 * Plugin Name: Klytos Importer
 * Plugin URI: https://klytos.io/plugins/importer
 * Description: AI-powered content migration from any website to Klytos. Supports WordPress XML exports, sitemap-guided import, and AI-driven web scraping.
 * Version: 1.0.0
 * Author: Jose Conti
 * Author URI: https://joseconti.com
 * Requires Klytos: 0.20.0
 * Requires PHP: 8.1
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: klytos-importer
 * Domain Path: /lang
 * Premium: false
 */

declare( strict_types=1 );

// ─── Load classes ───────────────────────────────────────────
require_once __DIR__ . '/src/ImportValidator.php';
require_once __DIR__ . '/src/ImportSession.php';
require_once __DIR__ . '/src/WPXMLParser.php';
require_once __DIR__ . '/src/ContentMapper.php';
require_once __DIR__ . '/src/SitemapParser.php';
require_once __DIR__ . '/src/PageFetcher.php';
require_once __DIR__ . '/src/ContentExtractor.php';
require_once __DIR__ . '/src/StyleAnalyzer.php';
require_once __DIR__ . '/src/MediaDownloader.php';

// ─── Boot ───────────────────────────────────────────────────

$klytosImporterSession = new \KlytosImporter\ImportSession( klytos_storage() );

$GLOBALS['klytos_importer_session'] = $klytosImporterSession;

/**
 * Get the ImportSession instance.
 */
function klytos_importer(): \KlytosImporter\ImportSession
{
    return $GLOBALS['klytos_importer_session'];
}

// ─── Translations ───────────────────────────────────────────
klytos_register_translations( 'klytos-importer', __DIR__ . '/lang' );

// ─── Admin sidebar ──────────────────────────────────────────
klytos_add_filter( 'admin.sidebar_items', function ( array $items ): array {
    $adminPath = \Klytos\Core\Helpers::getBasePath() . 'admin/';

    $items[] = [
        'id'         => 'klytos-importer',
        'title'      => __( 'klytos_importer.menu_title' ),
        'url'        => $adminPath . 'plugin-page.php?plugin=klytos-importer&page=import',
        'icon'       => 'fa-solid fa-download',
        'position'   => 65,
        'section'    => 'content',
        'capability' => 'site.configure',
        'children'   => [],
    ];

    return $items;
} );

// ─── MCP Tools ──────────────────────────────────────────────
klytos_add_filter( 'mcp.tools_list', function ( array $tools ): array {
    $importerTools = [
        // ── Analysis Phase ──────────────────────────────────
        [
            'name'        => 'klytos_import_analyze_wp_xml',
            'description' => 'Parse a WordPress WXR export file and return a structured summary of its contents (pages, posts, media, menus, authors, custom post types).',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'file_path' => [
                        'type'        => 'string',
                        'description' => 'Path to the uploaded XML file within Klytos assets.',
                    ],
                ],
                'required' => ['file_path'],
            ],
            'annotations' => [
                'title'           => 'Analyze WordPress XML Export',
                'readOnlyHint'    => true,
                'destructiveHint' => false,
                'idempotentHint'  => true,
            ],
        ],

        [
            'name'        => 'klytos_import_fetch_wp_page',
            'description' => 'Extract a single page or post from an already-analyzed WordPress XML export by slug. Returns content, metadata, media references, and Gutenberg/shortcode detection.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'session_id' => [
                        'type'        => 'string',
                        'description' => 'Import session ID from the analysis step.',
                    ],
                    'slug' => [
                        'type'        => 'string',
                        'description' => 'Slug of the page/post to extract from the XML.',
                    ],
                ],
                'required' => ['session_id', 'slug'],
            ],
            'annotations' => [
                'title'           => 'Fetch WordPress Page from XML',
                'readOnlyHint'    => true,
                'destructiveHint' => false,
                'idempotentHint'  => true,
            ],
        ],

        // ── Conversion Phase ────────────────────────────────
        [
            'name'        => 'klytos_import_convert_content',
            'description' => 'Convert generic HTML content into the appropriate format for the target post type editor. Outputs Gutenberg blocks for post types using the block editor, or clean HTML for TinyMCE. Auto-detects format from post_type if provided, or use output_format to override.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'html' => [
                        'type'        => 'string',
                        'description' => 'Raw HTML content to convert.',
                    ],
                    'source_type' => [
                        'type'        => 'string',
                        'description' => 'Hint about source: "wordpress", "html", "markdown". Default: "html".',
                    ],
                    'preserve_classes' => [
                        'type'        => 'boolean',
                        'description' => 'Keep original CSS classes in block markup. Default: false.',
                    ],
                    'post_type' => [
                        'type'        => 'string',
                        'description' => 'Target post type ID. Used to auto-detect editor format (gutenberg or tinymce). Default: "page".',
                    ],
                    'output_format' => [
                        'type'        => 'string',
                        'description' => 'Force output format: "gutenberg" or "tinymce". Overrides post_type auto-detection.',
                    ],
                ],
                'required' => ['html'],
            ],
            'annotations' => [
                'title'           => 'Convert HTML Content',
                'readOnlyHint'    => true,
                'destructiveHint' => false,
                'idempotentHint'  => true,
            ],
        ],

        // ── Execution Phase ─────────────────────────────────
        [
            'name'        => 'klytos_import_execute_batch',
            'description' => 'Import multiple pages in a single call. Creates pages via PageManager, tracks progress in the import session. All pages default to draft status. Processes max 20 pages per call.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'session_id' => [
                        'type'        => 'string',
                        'description' => 'Import session ID.',
                    ],
                    'pages' => [
                        'type'        => 'array',
                        'description' => 'Array of page objects with: slug, title, content_html, meta_description, template, status, lang, custom_css, og_image, post_type, order.',
                    ],
                    'url_map' => [
                        'type'        => 'object',
                        'description' => 'URL replacement map from media download step. Keys are original URLs, values are local Klytos paths.',
                    ],
                ],
                'required' => ['session_id', 'pages'],
            ],
            'annotations' => [
                'title'           => 'Batch Import Pages',
                'readOnlyHint'    => false,
                'destructiveHint' => true,
                'idempotentHint'  => false,
            ],
        ],

        [
            'name'        => 'klytos_import_session_status',
            'description' => 'Get the current status of an import session: progress, errors, and per-page status (imported/pending/failed).',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'session_id' => [
                        'type'        => 'string',
                        'description' => 'Import session ID.',
                    ],
                ],
                'required' => ['session_id'],
            ],
            'annotations' => [
                'title'           => 'Import Session Status',
                'readOnlyHint'    => true,
                'destructiveHint' => false,
                'idempotentHint'  => true,
            ],
        ],

        // ── Phase 2: Sitemap + URL Import ───────────────────
        [
            'name'        => 'klytos_import_analyze_sitemap',
            'description' => 'Fetch and parse a sitemap.xml (or sitemap index) and return the list of discovered URLs with metadata, suggested slugs, and content type classification.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'sitemap_url' => [
                        'type'        => 'string',
                        'description' => 'Full URL to sitemap.xml or sitemap index.',
                    ],
                    'max_urls' => [
                        'type'        => 'integer',
                        'description' => 'Maximum URLs to process. Default: 500.',
                    ],
                ],
                'required' => ['sitemap_url'],
            ],
            'annotations' => [
                'title'           => 'Analyze Sitemap',
                'readOnlyHint'    => true,
                'destructiveHint' => false,
                'idempotentHint'  => true,
            ],
        ],

        [
            'name'        => 'klytos_import_fetch_page',
            'description' => 'Download a single page and extract its main content, stripping navigation, headers, footers, sidebars, and scripts. If html_content is provided, skips server-side fetch (use when AI has browser access for JS-rendered sites).',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'url' => [
                        'type'        => 'string',
                        'description' => 'URL of the page to fetch. Required if html_content not provided.',
                    ],
                    'html_content' => [
                        'type'        => 'string',
                        'description' => 'Raw HTML (if the AI already fetched the page). Skips server-side fetch.',
                    ],
                    'extract_media' => [
                        'type'        => 'boolean',
                        'description' => 'Also extract image/video URLs from content. Default: true.',
                    ],
                ],
            ],
            'annotations' => [
                'title'           => 'Fetch Page Content',
                'readOnlyHint'    => true,
                'destructiveHint' => false,
                'idempotentHint'  => true,
            ],
        ],

        [
            'name'        => 'klytos_import_discover_site',
            'description' => 'Starting from a URL, crawl the site by following internal links. Returns a site map with page hierarchy, titles, and suggested types. Respects robots.txt and rate-limits to 1 request/second.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'start_url' => [
                        'type'        => 'string',
                        'description' => 'Homepage or starting URL.',
                    ],
                    'max_depth' => [
                        'type'        => 'integer',
                        'description' => 'Maximum link depth to follow. Default: 3.',
                    ],
                    'max_pages' => [
                        'type'        => 'integer',
                        'description' => 'Maximum pages to discover. Default: 100.',
                    ],
                    'include_patterns' => [
                        'type'        => 'array',
                        'description' => 'URL regex patterns to include. Empty = all.',
                    ],
                    'exclude_patterns' => [
                        'type'        => 'array',
                        'description' => 'URL regex patterns to exclude. E.g. ["/tag/", "/author/"].',
                    ],
                ],
                'required' => ['start_url'],
            ],
            'annotations' => [
                'title'           => 'Discover Site Structure',
                'readOnlyHint'    => true,
                'destructiveHint' => false,
                'idempotentHint'  => false,
            ],
        ],

        // ── Phase 3: Style Replication ──────────────────────
        [
            'name'        => 'klytos_import_analyze_style',
            'description' => 'Analyze the visual style of a website (colors, fonts, layout) and return a Klytos theme mapping with confidence scores. If AI has browser access, pass html_content and css_content to skip server-side fetch.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'url' => [
                        'type'        => 'string',
                        'description' => 'URL of a representative page (usually homepage).',
                    ],
                    'css_content' => [
                        'type'        => 'string',
                        'description' => 'Raw CSS content (if the AI already fetched it). Skips server-side fetch.',
                    ],
                    'html_content' => [
                        'type'        => 'string',
                        'description' => 'Raw HTML content (if the AI already fetched it). Used for inline style analysis.',
                    ],
                ],
                'required' => ['url'],
            ],
            'annotations' => [
                'title'           => 'Analyze Site Style',
                'readOnlyHint'    => true,
                'destructiveHint' => false,
                'idempotentHint'  => true,
            ],
        ],

        // ── Phase 4: Media Download ─────────────────────────
        [
            'name'        => 'klytos_import_download_media',
            'description' => 'Download external media files and register them as Klytos assets. Returns a URL map for content rewriting. Validates files for security (SSRF, PHP injection, SVG sanitization). Max 10MB per file, 500MB per session.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'session_id' => [
                        'type'        => 'string',
                        'description' => 'Import session ID.',
                    ],
                    'media_list' => [
                        'type'        => 'array',
                        'description' => 'Array of {src, alt, filename} objects.',
                    ],
                    'base_url' => [
                        'type'        => 'string',
                        'description' => 'Base URL to resolve relative paths.',
                    ],
                ],
                'required' => ['session_id', 'media_list'],
            ],
            'annotations' => [
                'title'           => 'Download Media',
                'readOnlyHint'    => false,
                'destructiveHint' => false,
                'idempotentHint'  => false,
            ],
        ],
    ];

    return array_merge( $tools, $importerTools );
} );

// ─── MCP tool capabilities (Sprint 2, slice 3 — NEW-02 / D-048) ──────────────
// The gate in ToolRegistry::call() default-denies any tool with no entry in the
// capability map, so a filter-injected tool (this plugin registers through
// mcp.tools_list / mcp.handle_tool, not the core loader) MUST declare its tools'
// capabilities here or every role — including owner — is refused them. A site
// import is a whole-site migration: it fetches arbitrary EXTERNAL URLs (an SSRF
// surface, even guarded), downloads external media, and bulk-creates pages. That
// is an operations privilege, the mirror image of klytos_export_site (also
// site.configure — an editor should not egress, nor ingest, the entire site), so
// all 10 tools take site.configure (owner/admin). The matrix has no import
// capability and none is needed; over-restriction fails safe. keel-verify
// check 10 covers only the static core map, so these are verified by this
// plugin's own MCP-gate test instead.
klytos_add_filter( 'mcp.tool_capabilities', function ( array $map ): array {
    $map['klytos_import_analyze_wp_xml']  = 'site.configure';
    $map['klytos_import_fetch_wp_page']   = 'site.configure';
    $map['klytos_import_convert_content'] = 'site.configure';
    $map['klytos_import_execute_batch']   = 'site.configure';
    $map['klytos_import_session_status']  = 'site.configure';
    $map['klytos_import_analyze_sitemap'] = 'site.configure';
    $map['klytos_import_fetch_page']      = 'site.configure';
    $map['klytos_import_discover_site']   = 'site.configure';
    $map['klytos_import_analyze_style']   = 'site.configure';
    $map['klytos_import_download_media']  = 'site.configure';
    return $map;
}, 10 );

// ─── MCP Tool Handler ───────────────────────────────────────
klytos_add_filter( 'mcp.handle_tool', function ( mixed $result, string $toolName, array $params ): mixed {
    if ( !str_starts_with( $toolName, 'klytos_import_' ) ) {
        return $result;
    }

    $session = $GLOBALS['klytos_importer_session'] ?? null;
    if ( !$session ) {
        return $result;
    }

    $handlers = [

        // ── klytos_import_analyze_wp_xml ────────────────────
        'klytos_import_analyze_wp_xml' => function ( array $p ) use ( $session ): array {
            $filePath = $p['file_path'] ?? '';
            if ( empty( $filePath ) ) {
                throw new \InvalidArgumentException( 'file_path is required.' );
            }

            // Resolve relative to data dir.
            $basePath = klytos_app()->getStorage()->getDataDir();
            $fullPath = str_starts_with( $filePath, '/' ) ? $filePath : $basePath . '/' . $filePath;

            $parser   = new \KlytosImporter\WPXMLParser( $fullPath );
            $analysis = $parser->analyze();

            // Create a session.
            $sess = $session->create( 'wordpress', $analysis['site_url'] ?? '', $fullPath );
            $session->update( $sess['id'], [
                'status'   => 'ready',
                'analysis' => $analysis,
            ] );

            // Add pages to session.
            $pages = [];
            foreach ( $analysis['pages_list'] as $page ) {
                $pages[] = [
                    'original_url' => ( $analysis['site_url'] ?? '' ) . '/' . $page['slug'],
                    'slug'         => $page['slug'],
                    'title'        => $page['title'],
                    'status'       => 'pending',
                ];
            }
            foreach ( $analysis['posts_list'] as $post ) {
                $pages[] = [
                    'original_url' => ( $analysis['site_url'] ?? '' ) . '/' . $post['slug'],
                    'slug'         => $post['slug'],
                    'title'        => $post['title'],
                    'status'       => 'pending',
                ];
            }
            if ( !empty( $pages ) ) {
                $session->addPages( $sess['id'], $pages );
            }

            $analysis['session_id'] = $sess['id'];
            return $analysis;
        },

        // ── klytos_import_fetch_wp_page ─────────────────────
        'klytos_import_fetch_wp_page' => function ( array $p ) use ( $session ): array {
            $sessionId = $p['session_id'] ?? '';
            $slug      = $p['slug'] ?? '';

            if ( empty( $sessionId ) || empty( $slug ) ) {
                throw new \InvalidArgumentException( 'session_id and slug are required.' );
            }

            $sess     = $session->get( $sessionId );
            $filePath = $sess['source_file'] ?? '';

            if ( empty( $filePath ) ) {
                throw new \RuntimeException( 'No source file associated with this session.' );
            }

            $parser = new \KlytosImporter\WPXMLParser( $filePath );
            return $parser->extractPage( $slug );
        },

        // ── klytos_import_convert_content ───────────────────
        'klytos_import_convert_content' => function ( array $p ): array {
            $html            = $p['html'] ?? '';
            $sourceType      = $p['source_type'] ?? 'html';
            $preserveClasses = (bool) ( $p['preserve_classes'] ?? false );
            $postType        = $p['post_type'] ?? 'page';
            $outputFormat    = $p['output_format'] ?? null;

            if ( empty( $html ) ) {
                throw new \InvalidArgumentException( 'html is required.' );
            }

            // Auto-detect output format from post type editor setting.
            if ( $outputFormat === null ) {
                $outputFormat = 'gutenberg';
                try {
                    $ptDef = klytos_app()->getPostTypeManager()->get( $postType );
                    $outputFormat = $ptDef['editor'] ?? 'gutenberg';
                } catch ( \Throwable ) {
                    // Post type not found — default to gutenberg.
                }
            }

            $mapper = new \KlytosImporter\ContentMapper();
            return $mapper->convert( $html, $sourceType, $preserveClasses, $outputFormat );
        },

        // ── klytos_import_execute_batch ─────────────────────
        'klytos_import_execute_batch' => function ( array $p ) use ( $session ): array {
            $sessionId = $p['session_id'] ?? '';
            $pages     = $p['pages'] ?? [];
            $urlMap    = $p['url_map'] ?? [];

            if ( empty( $sessionId ) ) {
                throw new \InvalidArgumentException( 'session_id is required.' );
            }
            if ( empty( $pages ) ) {
                throw new \InvalidArgumentException( 'pages array is required and cannot be empty.' );
            }

            // Limit batch size to 20 to prevent timeouts.
            $pages = array_slice( $pages, 0, 20 );

            $session->update( $sessionId, ['status' => 'in_progress'] );
            $pageManager = klytos_app()->getPages();

            $created = 0;
            $failed  = 0;
            $results = [];

            klytos_do_action( 'importer.before_import', $sessionId, $pages );

            foreach ( $pages as $pageData ) {
                $slug  = $pageData['slug'] ?? '';
                $title = $pageData['title'] ?? '';

                try {
                    $contentHtml = $pageData['content_html'] ?? '';

                    // Apply URL replacements.
                    if ( !empty( $urlMap ) && !empty( $contentHtml ) ) {
                        $contentHtml = str_replace(
                            array_keys( $urlMap ),
                            array_values( $urlMap ),
                            $contentHtml
                        );
                    }

                    $contentHtml = klytos_apply_filters( 'importer.page_data', $contentHtml, $pageData );

                    // Create page via PageManager.
                    $pageManager->create( [
                        'slug'             => $slug,
                        'title'            => $title,
                        'content_html'     => $contentHtml,
                        'meta_description' => $pageData['meta_description'] ?? '',
                        'template'         => $pageData['template'] ?? 'default',
                        'status'           => $pageData['status'] ?? 'draft',
                        'lang'             => $pageData['lang'] ?? '',
                        'custom_css'       => $pageData['custom_css'] ?? '',
                        'og_image'         => $pageData['og_image'] ?? '',
                        'post_type'        => $pageData['post_type'] ?? 'page',
                        'order'            => $pageData['order'] ?? 0,
                    ] );

                    $session->updatePageStatus( $sessionId, $slug, 'imported' );
                    $results[] = ['slug' => $slug, 'status' => 'created', 'title' => $title];
                    $created++;
                } catch ( \Throwable $e ) {
                    $session->updatePageStatus( $sessionId, $slug, 'failed', $e->getMessage() );
                    $results[] = ['slug' => $slug, 'status' => 'failed', 'error' => $e->getMessage()];
                    $failed++;
                }
            }

            // Update session overall status.
            $progress = $session->getProgress( $sessionId );
            $newStatus = ( $progress['progress']['pending'] ?? 0 ) === 0 ? 'completed' : 'in_progress';
            $session->update( $sessionId, ['status' => $newStatus] );

            klytos_do_action( 'importer.after_import', $sessionId, $results );

            return [
                'success'    => true,
                'session_id' => $sessionId,
                'total'      => count( $pages ),
                'created'    => $created,
                'failed'     => $failed,
                'results'    => $results,
            ];
        },

        // ── klytos_import_session_status ────────────────────
        'klytos_import_session_status' => function ( array $p ) use ( $session ): array {
            $sessionId = $p['session_id'] ?? '';
            if ( empty( $sessionId ) ) {
                throw new \InvalidArgumentException( 'session_id is required.' );
            }

            $data     = $session->get( $sessionId );
            $progress = $session->getProgress( $sessionId );

            return [
                'success'     => true,
                'session_id'  => $sessionId,
                'source'      => $data['source'],
                'source_url'  => $data['source_url'],
                'created_at'  => $data['created_at'],
                'status'      => $data['status'],
                'total_pages' => $progress['progress']['total'] ?? 0,
                'imported'    => $progress['progress']['imported'] ?? 0,
                'pending'     => $progress['progress']['pending'] ?? 0,
                'failed'      => $progress['progress']['failed'] ?? 0,
                'pages'       => $data['pages'],
            ];
        },

        // ── Phase 4: klytos_import_download_media ────────────
        'klytos_import_download_media' => function ( array $p ) use ( $session ): array {
            $sessionId = $p['session_id'] ?? '';
            $mediaList = $p['media_list'] ?? [];
            $baseUrl   = $p['base_url'] ?? null;

            if ( empty( $sessionId ) ) {
                throw new \InvalidArgumentException( 'session_id is required.' );
            }
            if ( empty( $mediaList ) ) {
                throw new \InvalidArgumentException( 'media_list is required and cannot be empty.' );
            }

            klytos_do_action( 'importer.before_media_download', $sessionId, $mediaList );

            $downloader = new \KlytosImporter\MediaDownloader(
                $session,
                klytos_app()->getAssetManager()
            );
            $result = $downloader->download( $sessionId, $mediaList, $baseUrl );

            klytos_do_action( 'importer.after_media_download', $sessionId, $result );

            return $result;
        },

        // ── Phase 3: klytos_import_analyze_style ─────────────
        'klytos_import_analyze_style' => function ( array $p ): array {
            $url         = $p['url'] ?? '';
            $cssContent  = $p['css_content'] ?? null;
            $htmlContent = $p['html_content'] ?? null;

            if ( empty( $url ) ) {
                throw new \InvalidArgumentException( 'url is required.' );
            }

            $analyzer = new \KlytosImporter\StyleAnalyzer();
            return $analyzer->analyze( $url, $cssContent, $htmlContent );
        },

        // ── Phase 2: klytos_import_analyze_sitemap ──────────
        'klytos_import_analyze_sitemap' => function ( array $p ) use ( $session ): array {
            $sitemapUrl = $p['sitemap_url'] ?? '';
            if ( empty( $sitemapUrl ) ) {
                throw new \InvalidArgumentException( 'sitemap_url is required.' );
            }

            $maxUrls = (int) ( $p['max_urls'] ?? 500 );

            klytos_do_action( 'importer.before_analyze', 'sitemap', $sitemapUrl );

            $parser   = new \KlytosImporter\SitemapParser( $sitemapUrl, $maxUrls );
            $analysis = $parser->parse();

            // Create a session.
            $sess = $session->create( 'sitemap', $analysis['site_url'] ?? '' );
            $session->update( $sess['id'], [
                'status'   => 'ready',
                'analysis' => $analysis,
            ] );

            // Add discovered pages to session.
            $pages = [];
            foreach ( $analysis['urls'] as $urlEntry ) {
                $type = $urlEntry['suggested_type'] ?? 'page';
                if ( in_array( $type, ['pagination', 'skip'], true ) ) {
                    continue;
                }
                $pages[] = [
                    'original_url' => $urlEntry['loc'],
                    'slug'         => $urlEntry['suggested_slug'],
                    'title'        => '',
                    'status'       => 'pending',
                ];
            }
            if ( !empty( $pages ) ) {
                $session->addPages( $sess['id'], $pages );
            }

            $analysis['session_id'] = $sess['id'];

            klytos_do_action( 'importer.after_analyze', 'sitemap', $analysis );

            return $analysis;
        },

        // ── Phase 2: klytos_import_fetch_page ───────────────
        'klytos_import_fetch_page' => function ( array $p ): array {
            $url         = $p['url'] ?? '';
            $htmlContent = $p['html_content'] ?? '';

            if ( empty( $url ) && empty( $htmlContent ) ) {
                throw new \InvalidArgumentException( 'Either url or html_content is required.' );
            }

            klytos_do_action( 'importer.before_fetch', $url );

            $html = $htmlContent;

            // Fetch via cURL if no HTML provided.
            if ( empty( $html ) ) {
                $fetcher = new \KlytosImporter\PageFetcher();
                $result  = $fetcher->fetch( $url );

                if ( $result['status_code'] !== 200 ) {
                    throw new \RuntimeException( "HTTP {$result['status_code']} fetching {$url}" );
                }

                $html = $result['html'];
            }

            // Extract main content.
            $extractor = new \KlytosImporter\ContentExtractor();
            $extracted = $extractor->extract( $html );

            $response = [
                'success'          => true,
                'url'              => $url,
                'title'            => $extracted['title'],
                'meta_description' => $extracted['meta_description'],
                'og_image'         => $extracted['og_image'],
                'main_content_html' => $extracted['main_content_html'],
                'media'            => $extracted['media'],
                'detected_lang'    => $extracted['detected_lang'],
                'word_count'       => $extracted['word_count'],
                'has_forms'        => $extracted['has_forms'],
                'has_video'        => $extracted['has_video'],
            ];

            klytos_do_action( 'importer.after_fetch', $url, $response );

            return $response;
        },

        // ── Phase 2: klytos_import_discover_site ────────────
        'klytos_import_discover_site' => function ( array $p ) use ( $session ): array {
            $startUrl = $p['start_url'] ?? '';
            if ( empty( $startUrl ) ) {
                throw new \InvalidArgumentException( 'start_url is required.' );
            }

            $maxDepth        = (int) ( $p['max_depth'] ?? 3 );
            $maxPages        = (int) ( $p['max_pages'] ?? 100 );
            $includePatterns = $p['include_patterns'] ?? [];
            $excludePatterns = $p['exclude_patterns'] ?? [];

            klytos_do_action( 'importer.before_analyze', 'crawl', $startUrl );

            $fetcher  = new \KlytosImporter\PageFetcher();
            $analysis = $fetcher->discover( $startUrl, $maxDepth, $maxPages, $includePatterns, $excludePatterns );

            // Create a session.
            $sess = $session->create( 'crawl', $analysis['site_url'] ?? '' );
            $session->update( $sess['id'], [
                'status'   => 'ready',
                'analysis' => $analysis,
            ] );

            // Add discovered pages to session.
            $pages = [];
            foreach ( $analysis['pages'] as $pageEntry ) {
                $type = $pageEntry['suggested_type'] ?? 'page';
                if ( in_array( $type, ['pagination', 'skip'], true ) ) {
                    continue;
                }
                $pages[] = [
                    'original_url' => $pageEntry['url'],
                    'slug'         => $pageEntry['suggested_slug'],
                    'title'        => $pageEntry['title'],
                    'status'       => 'pending',
                ];
            }
            if ( !empty( $pages ) ) {
                $session->addPages( $sess['id'], $pages );
            }

            $analysis['session_id'] = $sess['id'];

            klytos_do_action( 'importer.after_analyze', 'crawl', $analysis );

            return $analysis;
        },
    ];

    if ( !isset( $handlers[$toolName] ) ) {
        return $result;
    }

    try {
        $data = $handlers[$toolName]( $params );
        return [
            'content' => [['type' => 'text', 'text' => json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )]],
            'isError' => false,
        ];
    } catch ( \Throwable $e ) {
        return [
            'content' => [['type' => 'text', 'text' => json_encode( ['error' => $e->getMessage()] )]],
            'isError' => true,
        ];
    }
}, 10 );

// ─── Admin asset enqueue ────────────────────────────────────
klytos_add_action( 'admin.head', function ( string $cspNonce ): void {
    $page = $_GET['page'] ?? '';
    if ( $page !== 'import' ) {
        return;
    }

    $cssUrl = klytos_plugin_url( 'klytos-importer', 'admin/assets/import.css' );
    echo '<link rel="stylesheet" href="' . klytos_esc_url( $cssUrl ) . '" nonce="' . klytos_esc_attr( $cspNonce ) . '">' . "\n";
} );

klytos_add_action( 'admin.footer', function ( string $cspNonce ): void {
    $page = $_GET['page'] ?? '';
    if ( $page !== 'import' ) {
        return;
    }

    $jsUrl = klytos_plugin_url( 'klytos-importer', 'admin/assets/import.js' );
    echo '<script src="' . klytos_esc_url( $jsUrl ) . '" nonce="' . klytos_esc_attr( $cspNonce ) . '"></script>' . "\n";
} );
