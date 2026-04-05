<?php

/**
 * Klytos — Export Manager
 * Export site content in multiple formats: JSON (native), WXR (WordPress-compatible XML), CSV.
 *
 * @package Klytos
 * @since   0.18.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class ExportManager
{
    /** @var App Application instance. */
    private App $app;

    public function __construct( App $app )
    {
        $this->app = $app;
    }

    /**
     * Export site content as a JSON archive.
     *
     * Includes: pages, post types, taxonomies, terms, blocks, menus,
     * page templates, theme config, and site config.
     *
     * @param  array $collections Collections to export (empty = all).
     * @return array ['format' => 'json', 'data' => string, 'filename' => string]
     */
    public function exportJson( array $collections = [] ): array
    {
        $data = $this->gatherData( $collections );

        $json = json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

        return [
            'format'   => 'json',
            'data'     => $json,
            'filename' => 'klytos-export-' . date( 'Y-m-d' ) . '.json',
            'mime'     => 'application/json',
        ];
    }

    /**
     * Export pages as CSV.
     *
     * @param  string $postType Filter by post type (empty = all).
     * @return array ['format' => 'csv', 'data' => string, 'filename' => string]
     */
    public function exportCsv( string $postType = '' ): array
    {
        $pages = $this->app->getPages()->list( 'all', '', 0, 0, $postType );

        $output = fopen( 'php://temp', 'r+' );

        // CSV header.
        fputcsv( $output, [
            'slug', 'title', 'status', 'template', 'post_type', 'lang',
            'meta_description', 'og_image', 'order', 'created_at', 'updated_at',
        ] );

        foreach ( $pages as $page ) {
            fputcsv( $output, [
                $page['slug'] ?? '',
                $page['title'] ?? '',
                $page['status'] ?? '',
                $page['template'] ?? '',
                $page['post_type'] ?? 'page',
                $page['lang'] ?? '',
                $page['meta_description'] ?? '',
                $page['og_image'] ?? '',
                $page['order'] ?? 0,
                $page['created_at'] ?? '',
                $page['updated_at'] ?? '',
            ] );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        $suffix = $postType !== '' ? '-' . $postType : '';

        return [
            'format'   => 'csv',
            'data'     => $csv,
            'filename' => 'klytos-pages' . $suffix . '-' . date( 'Y-m-d' ) . '.csv',
            'mime'     => 'text/csv',
        ];
    }

    /**
     * Export pages as WordPress eXtended RSS (WXR) XML.
     *
     * Generates a WordPress-compatible import file so content can be
     * migrated from Klytos to WordPress.
     *
     * @param  string $postType Filter by post type (empty = all).
     * @return array ['format' => 'wxr', 'data' => string, 'filename' => string]
     */
    public function exportWxr( string $postType = '' ): array
    {
        $pages      = $this->app->getPages()->list( 'all', '', 0, 0, $postType );
        $siteConfig = $this->app->getSiteConfig()->get();
        $siteUrl    = Helpers::publicUrl();
        $siteName   = $siteConfig['site_name'] ?? 'Klytos Site';
        $now        = gmdate( 'D, d M Y H:i:s +0000' );

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0"' . "\n";
        $xml .= '     xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"' . "\n";
        $xml .= '     xmlns:content="http://purl.org/rss/1.0/modules/content/"' . "\n";
        $xml .= '     xmlns:wp="http://wordpress.org/export/1.2/">' . "\n";
        $xml .= '<channel>' . "\n";
        $xml .= '  <title>' . Helpers::escHtml( $siteName ) . '</title>' . "\n";
        $xml .= '  <link>' . Helpers::escUrl( $siteUrl ) . '</link>' . "\n";
        $xml .= '  <description>' . Helpers::escHtml( $siteConfig['tagline'] ?? '' ) . '</description>' . "\n";
        $xml .= '  <pubDate>' . $now . '</pubDate>' . "\n";
        $xml .= '  <language>' . ( $siteConfig['default_language'] ?? 'es' ) . '</language>' . "\n";
        $xml .= '  <wp:wxr_version>1.2</wp:wxr_version>' . "\n";
        $xml .= '  <wp:base_site_url>' . Helpers::escUrl( $siteUrl ) . '</wp:base_site_url>' . "\n";
        $xml .= '  <wp:base_blog_url>' . Helpers::escUrl( $siteUrl ) . '</wp:base_blog_url>' . "\n";
        $xml .= '  <generator>Klytos/' . KLYTOS_VERSION . '</generator>' . "\n";

        $postId = 1;
        foreach ( $pages as $page ) {
            $slug   = $page['slug'] ?? 'index';
            $title  = $page['title'] ?? '';
            $status = $page['status'] ?? 'draft';

            // Map Klytos status to WordPress status.
            $wpStatus = 'draft';
            if ( $status === 'published' ) {
                $wpStatus = 'publish';
            } elseif ( $status === 'trashed' ) {
                $wpStatus = 'trash';
            } elseif ( $status === 'scheduled' ) {
                $wpStatus = 'future';
            }

            $wpPostType = ( $page['post_type'] ?? 'page' ) === 'page' ? 'page' : 'post';

            $xml .= '  <item>' . "\n";
            $xml .= '    <title>' . Helpers::escHtml( $title ) . '</title>' . "\n";
            $xml .= '    <link>' . Helpers::escUrl( $siteUrl . $slug . '/' ) . '</link>' . "\n";
            $xml .= '    <wp:post_id>' . $postId . '</wp:post_id>' . "\n";
            $xml .= '    <wp:post_date><![CDATA[' . ( $page['created_at'] ?? '' ) . ']]></wp:post_date>' . "\n";
            $xml .= '    <wp:post_name><![CDATA[' . $slug . ']]></wp:post_name>' . "\n";
            $xml .= '    <wp:status><![CDATA[' . $wpStatus . ']]></wp:status>' . "\n";
            $xml .= '    <wp:post_type><![CDATA[' . $wpPostType . ']]></wp:post_type>' . "\n";
            $xml .= '    <content:encoded><![CDATA[' . ( $page['content_html'] ?? '' ) . ']]></content:encoded>' . "\n";
            $xml .= '    <excerpt:encoded><![CDATA[' . ( $page['meta_description'] ?? '' ) . ']]></excerpt:encoded>' . "\n";
            $xml .= '  </item>' . "\n";

            $postId++;
        }

        $xml .= '</channel>' . "\n";
        $xml .= '</rss>' . "\n";

        return [
            'format'   => 'wxr',
            'data'     => $xml,
            'filename' => 'klytos-export-' . date( 'Y-m-d' ) . '.xml',
            'mime'     => 'application/xml',
        ];
    }

    /**
     * Gather all site data for JSON export.
     *
     * @param  array $collections Collections to include (empty = all).
     * @return array Complete site data.
     */
    private function gatherData( array $collections ): array
    {
        $all  = empty( $collections );

        // Build backup metadata with encryption/identity info.
        $mainConfig = [];
        try {
            $mainConfig = $this->app->getStorage()->readFrom(
                $this->app->getConfigPath(),
                'config.json.enc'
            );
        } catch ( \Throwable $e ) {
            // Config not available — proceed without metadata.
        }

        $data = [
            'klytos_version'       => KLYTOS_VERSION,
            'exported_at'          => Helpers::now(),
            'backup_meta'          => [
                'klytos_version'       => KLYTOS_VERSION,
                'backup_date'          => Helpers::now(),
                'encryption_level'     => $mainConfig['encryption_level'] ?? 'basic',
                'identity_fingerprint' => $mainConfig['identity_fingerprint'] ?? null,
                'files_encrypted'      => true,
                'php_version'          => PHP_VERSION,
                'site_url'             => $mainConfig['admin_url'] ?? '',
                'backup_type'          => empty( $collections ) ? 'full' : 'partial',
            ],
        ];

        if ( $all || in_array( 'pages', $collections, true ) ) {
            $data['pages'] = $this->app->getPages()->list( 'all', '', 0 );
        }

        if ( $all || in_array( 'post_types', $collections, true ) ) {
            $data['post_types'] = $this->app->getPostTypeManager()->list();
        }

        if ( $all || in_array( 'blocks', $collections, true ) ) {
            $data['blocks'] = $this->app->getBlockManager()->list();
        }

        if ( $all || in_array( 'page_templates', $collections, true ) ) {
            $data['page_templates'] = $this->app->getPageTemplateManager()->list();
        }

        if ( $all || in_array( 'menu', $collections, true ) ) {
            $data['menu'] = $this->app->getMenu()->get();
        }

        if ( $all || in_array( 'theme', $collections, true ) ) {
            $data['theme'] = $this->app->getTheme()->get();
        }

        if ( $all || in_array( 'site_config', $collections, true ) ) {
            $config = $this->app->getSiteConfig()->get();
            // Remove sensitive data from export.
            unset( $config['analytics'], $config['email'] );
            $data['site_config'] = $config;
        }

        // Allow plugins to add their own data to the export.
        $data = klytos_apply_filters( 'export.data', $data, $collections );

        return $data;
    }
}
