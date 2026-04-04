<?php

/**
 * Klytos — Build Engine
 * Generates the static HTML site from data, templates, and theme.
 *
 * @package Klytos
 * @since   1.0.0
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

class BuildEngine
{
    /** @var App Application instance. */
    private App $app;

    /**
     * Output directory for the generated static site.
     *
     * This is the WEB ROOT (parent of the admin directory), NOT public/ inside admin.
     * The admin directory is SECRET — build output goes to the web root so that
     * public-facing URLs never reveal the admin path.
     *
     * Example:
     * - Admin dir:  /var/www/html/x7k9m2-panel/
     * - Output dir: /var/www/html/ (the parent = web root)
     * - Pages:      /var/www/html/index.html, /var/www/html/about.html
     * - Assets:     /var/www/html/assets/css/style.css
     *
     * @var string
     */
    private string $outputPath;

    /** @var string Path to HTML templates. */
    private string $templatesPath;

    /** @var array Cached rendered HTML for global-scope blocks (populated during buildAll). */
    private array $globalBlocksCache = [];

    public function __construct(App $app)
    {
        $this->app           = $app;
        $this->templatesPath = $app->getTemplatesPath();

        // Output goes to the web root (parent of the admin/Klytos directory).
        // This ensures public URLs never expose the admin directory name.
        $this->outputPath = dirname($app->getRootPath());
    }

    /**
     * Build the entire static site.
     *
     * @return array Build result summary.
     */
    public function buildAll(): array
    {
        $startTime  = microtime(true);
        $pagesBuilt = 0;
        $errors     = [];

        // Fire build.before hook for plugins.
        klytos_do_action('build.before');

        // 1. Generate CSS
        $this->generateCss();

        // 1b. Generate plugin hook JS (klytos-hooks.js)
        $this->buildHooksJs();

        // 1c. Generate plugin CSS (plugins.css)
        $this->buildPluginsCss();

        // 1d. Generate block CSS (blocks.css)
        $this->generateBlocksCss();

        // 1e. Generate block JS (blocks.js)
        $this->generateBlocksJs();

        // 1f. Generate consent manager JS (consent-manager.js)
        $this->buildConsentManagerJs();

        // 1g. Pre-render global blocks (scope=global) and cache in memory.
        $this->globalBlocksCache = $this->cacheGlobalBlocks();

        // 2. Get global data
        $siteConfig = $this->app->getSiteConfig()->get();
        $menuHtml   = $this->app->getMenu()->toHtml(Helpers::getPublicBasePath());
        $theme      = $this->app->getTheme()->get();

        // 3. Build each buildable page (published + custom statuses with is_public).
        $pages = $this->getBuildablePages();

        foreach ($pages as $page) {
            try {
                klytos_do_action('build.page.before', $page);
                $this->writePageHtml($page, $siteConfig, $menuHtml, $theme);
                klytos_do_action('build.page.after', $page, $this->outputPath);
                $pagesBuilt++;
            } catch (\Exception $e) {
                $errors[] = [
                    'slug'  => $page['slug'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        // 4. Generate robots.txt
        $this->generateRobotsTxt($siteConfig);

        // 5. Generate sitemap.xml
        $this->generateSitemap($pages, $siteConfig);

        // 6. Generate llms.txt and llms-full.txt for AI crawler indexing
        $this->generateLlmsTxt($pages, $siteConfig);

        // 6b. Generate search index JSON for client-side search.
        $searchEnabled = $siteConfig['search_enabled'] ?? true;
        if ( $searchEnabled ) {
            $this->generateSearchIndex( $pages, $siteConfig );
        }

        // 7. Ensure public .htaccess exists with clean URL rewrite rules.
        $this->ensurePublicHtaccess();

        // 7b. Handle maintenance mode files.
        $this->handleMaintenanceMode( $siteConfig );

        // 8. Update build timestamp
        $this->app->getSiteConfig()->updateBuildTimestamp();

        // 9. Fire build.after hook for plugins.
        klytos_do_action('build.after', $pagesBuilt, $errors);

        $durationMs = round((microtime(true) - $startTime) * 1000);

        return [
            'success'      => empty($errors),
            'pages_built'  => $pagesBuilt,
            'errors'       => $errors,
            'duration_ms'  => $durationMs,
        ];
    }

    /**
     * Get all pages that should be built to the public site.
     *
     * Includes pages with 'published' status plus pages with custom statuses
     * that have is_public=true on their post type definition.
     *
     * @return array Buildable pages (deduplicated by slug).
     */
    private function getBuildablePages(): array
    {
        $pages = $this->app->getPages()->list( 'published' );

        // Collect custom statuses marked as public across all post types.
        $publicCustomStatuses = [];
        $postTypes = $this->app->getPostTypeManager()->list();
        foreach ( $postTypes as $pt ) {
            foreach ( $pt['statuses'] ?? [] as $st ) {
                if ( !empty( $st['is_public'] ) && !in_array( $st['id'], $publicCustomStatuses, true ) ) {
                    $publicCustomStatuses[] = $st['id'];
                }
            }
        }

        // Filter: allow plugins to modify which statuses are buildable.
        $publicCustomStatuses = klytos_apply_filters( 'build.buildable_statuses', $publicCustomStatuses );

        // Fetch pages for each public custom status.
        foreach ( $publicCustomStatuses as $customStatus ) {
            $extraPages = $this->app->getPages()->list( $customStatus );
            $pages = array_merge( $pages, $extraPages );
        }

        // Deduplicate by slug.
        $seen   = [];
        $unique = [];
        foreach ( $pages as $page ) {
            $slug = $page['slug'] ?? '';
            if ( !isset( $seen[$slug] ) ) {
                $seen[$slug] = true;
                $unique[]    = $page;
            }
        }

        return $unique;
    }

    /**
     * Build a single page.
     *
     * @param  string $slug
     * @return array
     */
    public function buildPage(string $slug): array
    {
        $page       = $this->app->getPages()->get($slug);
        $siteConfig = $this->app->getSiteConfig()->get();
        $menuHtml   = $this->app->getMenu()->toHtml(Helpers::getPublicBasePath());
        $theme      = $this->app->getTheme()->get();

        $this->writePageHtml($page, $siteConfig, $menuHtml, $theme);

        return ['success' => true, 'slug' => $slug];
    }

    /**
     * Render a page to HTML without writing to disk.
     *
     * @param  string $slug
     * @return string Rendered HTML.
     */
    public function renderPage(string $slug): string
    {
        $page       = $this->app->getPages()->get($slug);
        $siteConfig = $this->app->getSiteConfig()->get();
        $menuHtml   = $this->app->getMenu()->toHtml(Helpers::getPublicBasePath());
        $theme      = $this->app->getTheme()->get();

        return $this->renderTemplate($page, $siteConfig, $menuHtml, $theme);
    }

    /**
     * Generate the CSS file from the theme.
     */
    public function generateCss(): void
    {
        $theme     = $this->app->getTheme();
        $variables = $theme->generateCssVariables();
        $fontsUrl  = $theme->getGoogleFontsUrl();

        $css = "/* Generated by Klytos Build Engine */\n\n";

        // Google Fonts import
        if (!empty($fontsUrl)) {
            $css .= "@import url('{$fontsUrl}');\n\n";
        }

        // CSS Variables
        $css .= $variables . "\n\n";

        // CSS Reset and base styles
        $css .= $this->getBaseCss();

        // Custom CSS from theme
        $themeData = $theme->get();
        if (!empty($themeData['custom_css'])) {
            $css .= "\n/* Custom CSS */\n" . $themeData['custom_css'] . "\n";
        }

        // CSS goes to assets/css/ in the web root (public-facing).
        $cssDir = $this->outputPath . '/assets/css';
        Helpers::ensureWritableDir($cssDir);
        file_put_contents($cssDir . '/style.css', $css, LOCK_EX);
    }

    /**
     * Write a page as an HTML file using clean URLs.
     *
     * URL mapping:
     * - slug 'index'                → /index.html (homepage)
     * - slug 'about'                → /about/index.html  (accessible as /about/)
     * - slug 'servicios'            → /servicios/index.html
     * - slug 'servicios/marketing'  → /servicios/marketing/index.html
     * - slug 'servicios/marketing/seo' → /servicios/marketing/seo/index.html
     *
     * This means all pages are accessible with clean trailing-slash URLs:
     * - midominio.com/
     * - midominio.com/about/
     * - midominio.com/servicios/
     * - midominio.com/servicios/marketing/
     */
    private function writePageHtml(array $page, array $siteConfig, string $menuHtml, array $theme): void
    {
        $html = $this->renderTemplate($page, $siteConfig, $menuHtml, $theme);
        $slug = $page['slug'] ?? 'index';

        // Allow plugins to modify the final HTML before writing to disk.
        $html = klytos_apply_filters('build.page.output', $html, $page);

        // Password-protect the page content if a password is set.
        $password = $page['password'] ?? '';
        if ( $password !== '' ) {
            $html = $this->encryptPageContent( $html, $password );
        }

        if ($slug === 'index') {
            // Homepage goes directly to /index.html at the web root.
            $filePath = $this->outputPath . '/index.html';
        } else {
            // All other pages: create a directory and put index.html inside.
            // 'about' → /about/index.html
            // 'servicios/marketing' → /servicios/marketing/index.html
            $filePath = $this->outputPath . '/' . $slug . '/index.html';
        }

        $dir = dirname($filePath);
        Helpers::ensureWritableDir($dir);
        file_put_contents($filePath, $html, LOCK_EX);
    }

    /**
     * Render a page using its template.
     */
    /**
     * Build the replacement map for a page.
     *
     * Public so the Router can use it for dynamic plugin pages.
     *
     * @param  array  $page          Page data array.
     * @param  array  $siteConfig    Site configuration.
     * @param  string $menuHtml      Rendered navigation menu HTML.
     * @param  array  $theme         Theme configuration.
     * @param  array  $excludeBlocks Block IDs to exclude from page template rendering.
     * @return array  Key => value replacement map.
     */
    public function buildReplacements( array $page, array $siteConfig, string $menuHtml, array $theme, array $excludeBlocks = [] ): array
    {
        $hreflangHtml = $this->buildHreflangTags( $page, $siteConfig );
        $basePath     = Helpers::getPublicBasePath();
        $siteUrl      = Helpers::publicUrl();
        $fontsUrl     = $theme['fonts']['google_fonts_url'] ?? '';

        $googleFontsHtml = '';
        if ( !empty( $fontsUrl ) ) {
            $googleFontsHtml = '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n  "
                             . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n  "
                             . '<link href="' . Helpers::escUrl( $fontsUrl ) . '" rel="stylesheet">';
        }

        $seoMetaTags    = $this->buildSeoMetaTags( $page, $siteConfig );
        $breadcrumbHtml = $this->app->getPages()->renderBreadcrumbs(
            $page['slug'] ?? 'index',
            Helpers::getPublicBasePath()
        );

        $pluginHeadHtml    = klytos_apply_filters( 'build.head_html', '' );
        $pluginBodyEndHtml = klytos_apply_filters( 'build.body_end_html', '' );

        if ( PageManager::hasBlockContent( $page ) && !empty( $page['template'] ) ) {
            // Only use PageTemplateManager if the template exists as a v2.0
            // page-template (in storage). Core templates (default, landing,
            // blog-post, blank) are HTML files — they use {{page_content}}
            // and the block content should be rendered inline, not via the
            // PageTemplateManager.
            try {
                $this->app->getPageTemplateManager()->get( $page['template'] );
                $pageContent = $this->renderBlockContent( $page, $excludeBlocks );
            } catch ( \RuntimeException $e ) {
                // Template not in page-templates storage — fall back to
                // content_html or render blocks inline without the manager.
                $pageContent = $page['content_html'] ?? '';
            }
        } else {
            $pageContent = $page['content_html'] ?? '';
        }

        $pageContent = klytos_apply_filters( 'page.content', $pageContent, $page );

        // Rewrite internal links in content for subdirectory installations.
        // Content authored via MCP uses root-relative links like href="/about/"
        // which must become href="/prueba/about/" when installed in a subdirectory.
        $pageContent = $this->rewriteInternalLinks( $pageContent, $basePath );

        $rawSiteName  = $siteConfig['site_name'] ?? '';
        $rawPageTitle = $page['title'] ?? '';
        $titleSeparator = '';
        if ( !empty( $rawSiteName ) && !str_contains( strtolower( $rawPageTitle ), strtolower( $rawSiteName ) ) ) {
            $titleSeparator = ' — ';
        }

        return [
            '{{site_name}}'            => Helpers::escHtml( $rawSiteName ),
            '{{title_separator}}'      => $titleSeparator,
            '{{tagline}}'              => Helpers::escHtml( $siteConfig['tagline'] ?? '' ),
            '{{site_tagline}}'         => Helpers::escHtml( $siteConfig['tagline'] ?? '' ),
            '{{default_language}}'     => $siteConfig['default_language'] ?? 'es',
            '{{page_title}}'           => Helpers::escHtml( $page['title'] ?? '' ),
            '{{page_content}}'         => $pageContent,
            '{{meta_description}}'     => Helpers::escHtml( $page['meta_description'] ?? '' ),
            '{{page_lang}}'            => $page['lang'] ?? ( $siteConfig['default_language'] ?? 'es' ),
            '{{hreflang_tags}}'        => $hreflangHtml,
            '{{seo_meta_tags}}'        => $seoMetaTags,
            '{{page_slug}}'            => $page['slug'] ?? '',
            '{{menu_html}}'            => $menuHtml,
            '{{current_year}}'         => klytos_gmdate( 'Y' ),
            '{{og_image}}'             => $page['og_image'] ?? ( $siteConfig['seo']['default_og_image'] ?? '' ),
            '{{custom_css}}'           => !empty( $page['custom_css'] ) ? '<style>' . $page['custom_css'] . '</style>' : '',
            '{{custom_js}}'            => !empty( $page['custom_js'] ) ? '<script>' . $page['custom_js'] . '</script>' : '',
            '{{google_fonts_url}}'     => $fontsUrl,
            '{{google_fonts_html}}'    => $googleFontsHtml,
            '{{favicon_url}}'          => $siteConfig['favicon_url'] ?? '',
            '{{logo_url}}'             => $siteConfig['logo_url'] ?? '',
            '{{head_scripts}}'         => $siteConfig['analytics']['custom_head_scripts'] ?? '',
            '{{body_scripts}}'         => $siteConfig['analytics']['custom_body_scripts'] ?? '',
            '{{css_variables}}'        => $this->app->getTheme()->generateCssVariables(),
            '{{sitemap_url}}'          => $siteUrl . 'sitemap.xml',
            '{{base_path}}'            => $basePath,
            '{{site_url}}'             => $siteUrl,
            '{{header_html}}'          => '',
            '{{footer_html}}'          => $this->buildFooterHtml( $siteConfig ),
            '{{sidebar_html}}'         => '',
            '{{breadcrumbs}}'          => $breadcrumbHtml,
            '{{plugin_head_html}}'     => $pluginHeadHtml,
            '{{plugin_body_end_html}}' => $pluginBodyEndHtml,
            '{{plugin_css_link}}'      => $this->buildPluginCssLink( $basePath ),
            '{{blocks_css_link}}'      => $this->buildBlocksCssLink( $basePath ),
            '{{blocks_js_script}}'     => $this->buildBlocksJsTag( $basePath ),
            '{{hooks_js_script}}'      => $this->buildHooksJsTag( $basePath ),
            '{{consent_manager_script}}' => $this->buildConsentManagerJsTag( $basePath ),
        ];
    }

    private function renderTemplate( array $page, array $siteConfig, string $menuHtml, array $theme ): string
    {
        $rawTemplateHtml = $this->resolveTemplateForPage( $page );

        // Process template parts ({{klytos_part:X}}) BEFORE variable replacement.
        $templateHtml = $this->processTemplateParts( $rawTemplateHtml );

        // Detect which structural blocks the custom template already provides
        // so they are not duplicated inside {{page_content}}.
        $excludeBlocks = $this->detectProvidedStructure( $rawTemplateHtml, $templateHtml );

        $replacements = $this->buildReplacements( $page, $siteConfig, $menuHtml, $theme, $excludeBlocks );

        $html = $templateHtml;
        foreach ( $replacements as $key => $value ) {
            $html = str_replace( $key, $value, $html );
        }

        return $html;
    }

    /**
     * Load a template's HTML content.
     */
    private function loadTemplate(string $name): string
    {
        return $this->app->getTemplateResolver()->resolve($name);
    }

    /**
     * Resolve the template for a page using post type hierarchy.
     *
     * Candidate chain (first match wins):
     * 1. single-{post_type}-{slug}  (e.g. single-product-camiseta-azul)
     * 2. single-{post_type}         (e.g. single-product)
     * 3. Page's chosen template      (from admin editor)
     * 4. default
     *
     * @param  array  $page Page data array.
     * @return string Template HTML content.
     */
    private function resolveTemplateForPage(array $page): string
    {
        $postType = $page['post_type'] ?? 'page';
        $slug     = $page['slug'] ?? '';
        $resolver = $this->app->getTemplateResolver();

        $candidates = [];

        // Post-type-specific templates (only for non-page types).
        if ($postType !== 'page') {
            if (!empty($slug)) {
                $candidates[] = "single-{$postType}-{$slug}";
            }
            $candidates[] = "single-{$postType}";
        }

        // User-chosen template from the editor.
        $chosen = $page['template'] ?? 'default';
        if ($chosen !== 'default') {
            $candidates[] = $chosen;
        }

        // Ultimate fallback.
        $candidates[] = 'default';

        // Try each candidate through the resolver.
        foreach ($candidates as $name) {
            $html = $resolver->resolve($name);
            if (!empty($html)) {
                return $html;
            }
        }

        return $resolver->resolve('default');
    }

    /**
     * Detect which structural elements the custom template already provides.
     *
     * Scans the raw template (before parts are resolved) for {{klytos_part:*}}
     * references and {{*_html}} variables, and the processed template for
     * hardcoded HTML tags like <header> or <footer>.
     *
     * When a custom template already renders a structural element, the
     * corresponding blocks are excluded from the page template to prevent
     * duplication (e.g. double header/footer).
     *
     * @param  string $rawTemplate       Template HTML before part processing.
     * @param  string $processedTemplate Template HTML after part processing.
     * @return array  Block IDs that should be excluded from page template rendering.
     */
    private function detectProvidedStructure( string $rawTemplate, string $processedTemplate ): array
    {
        // Default mapping: structural element => block IDs to exclude.
        // 'header' covers both the top-bar and header blocks because
        // the header template part serves as the complete top navigation area.
        $mapping = [
            'header' => ['top-bar', 'header'],
            'footer' => ['footer'],
        ];

        $mapping = klytos_apply_filters( 'build.structural_block_mapping', $mapping );

        $exclude = [];

        foreach ( $mapping as $element => $blockIds ) {
            $provided = false;

            // Check raw template for part references (e.g. {{klytos_part:header}}).
            if ( str_contains( $rawTemplate, "{{klytos_part:{$element}}}" ) ) {
                $provided = true;
            }

            // Check raw template for variable references (e.g. {{header_html}}, {{footer_html}}).
            if ( str_contains( $rawTemplate, "{{{$element}_html}}" ) ) {
                $provided = true;
            }

            // Check processed template for hardcoded HTML tags (e.g. <header, <footer).
            if ( str_contains( $processedTemplate, "<{$element}" ) ) {
                $provided = true;
            }

            if ( $provided ) {
                array_push( $exclude, ...$blockIds );
            }
        }

        return klytos_apply_filters( 'build.exclude_structural_blocks', array_unique( $exclude ), $rawTemplate );
    }

    /**
     * Process template parts: replace {{klytos_part:NAME}} with resolved content.
     * Parts are resolved via the TemplateResolver hierarchy (custom > plugin > core).
     * Must be called BEFORE variable replacement so parts can contain {{variables}}.
     *
     * @param  string $templateHtml Raw template HTML.
     * @return string Template with parts inlined.
     */
    public function processTemplateParts(string $templateHtml): string
    {
        $resolver     = $this->app->getTemplateResolver();
        $resolvedParts = [];

        return preg_replace_callback(
            '/\{\{klytos_part:([a-zA-Z0-9_\-]+)\}\}/',
            function (array $matches) use ($resolver, &$resolvedParts): string {
                $partName = $matches[1];

                // Prevent infinite recursion.
                if (isset($resolvedParts[$partName])) {
                    return '';
                }
                $resolvedParts[$partName] = true;

                $partHtml = $resolver->resolvePart($partName);

                return $partHtml ?? '';
            },
            $templateHtml
        );
    }

    /**
     * Generate /assets/js/klytos-hooks.js
     * Concatenates the prelude, plugin hook registrations, and executor.
     * Regenerated during buildAll() and when a plugin is activated/deactivated.
     */
    public function buildHooksJs(): void
    {
        $corePath   = $this->app->getCorePath();
        $pluginsDir = $this->app->getRootPath() . '/plugins';
        $outputDir  = $this->outputPath . '/assets/js';

        // Read prelude and executor from core assets.
        $preludeFile  = $corePath . '/assets/klytos-hooks-prelude.js';
        $executorFile = $corePath . '/assets/klytos-hooks-executor.js';

        if (!file_exists($preludeFile) || !file_exists($executorFile)) {
            return;
        }

        $js = file_get_contents($preludeFile);

        // Append hook registrations from each active plugin.
        $pluginLoader  = $this->app->getPluginLoader();
        $activePlugins = $pluginLoader->getActivePlugins();

        foreach ($activePlugins as $pluginId => $manifest) {
            $hooksFile = $pluginsDir . '/' . $pluginId . '/assets/js/hooks.js';
            if (file_exists($hooksFile)) {
                $version = $manifest['version'] ?? '0.0.0';
                $js .= "\n    // --- Plugin: {$pluginId} (v{$version}) ---\n";
                $js .= file_get_contents($hooksFile);
            }
        }

        // Append executor.
        $js .= file_get_contents($executorFile);

        // Write output.
        Helpers::ensureWritableDir($outputDir);
        file_put_contents($outputDir . '/klytos-hooks.js', $js, LOCK_EX);

        // Store version hash for cache-busting.
        $version = substr(md5($js), 0, 8);
        klytos_set_option('klytos_hooks_js_version', $version);
    }

    /**
     * Generate /assets/css/plugins.css
     * Concatenates CSS from all active plugins.
     * Regenerated during buildAll() and when a plugin is activated/deactivated.
     */
    public function buildPluginsCss(): void
    {
        $pluginsDir = $this->app->getRootPath() . '/plugins';
        $outputDir  = $this->outputPath . '/assets/css';

        $pluginLoader  = $this->app->getPluginLoader();
        $activePlugins = $pluginLoader->getActivePlugins();

        $css = "/* Generated automatically by Klytos. Do not edit. */\n\n";
        $hasContent = false;

        foreach ($activePlugins as $pluginId => $manifest) {
            $cssDir = $pluginsDir . '/' . $pluginId . '/assets/css';
            if (is_dir($cssDir)) {
                foreach (glob($cssDir . '/*.css') as $file) {
                    $css .= "/* --- {$pluginId}: " . basename($file) . " --- */\n";
                    $css .= file_get_contents($file) . "\n\n";
                    $hasContent = true;
                }
            }
        }

        Helpers::ensureWritableDir($outputDir);

        if ($hasContent) {
            file_put_contents($outputDir . '/plugins.css', $css, LOCK_EX);
            $version = substr(md5($css), 0, 8);
            klytos_set_option('klytos_plugins_css_version', $version);
        } else {
            // Remove stale file if no plugins have CSS.
            $pluginsCssFile = $outputDir . '/plugins.css';
            if (file_exists($pluginsCssFile)) {
                unlink($pluginsCssFile);
            }
            klytos_set_option('klytos_plugins_css_version', '');
        }
    }

    /**
     * Build the <link> tag for plugins.css if the file exists.
     *
     * @param  string $basePath Site base path.
     * @return string HTML link tag or empty string.
     */
    private function buildPluginCssLink(string $basePath): string
    {
        $version = klytos_get_option('klytos_plugins_css_version', '');
        if (empty($version)) {
            return '';
        }
        $href = $basePath . 'assets/css/plugins.css?v=' . $version;
        return '<link rel="stylesheet" href="' . Helpers::escUrl($href) . '">';
    }

    /**
     * Build the <script> tag for klytos-hooks.js if the file exists.
     *
     * @param  string $basePath Site base path.
     * @return string HTML script tag or empty string.
     */
    private function buildHooksJsTag(string $basePath): string
    {
        $version = klytos_get_option('klytos_hooks_js_version', '');
        if (empty($version)) {
            return '';
        }
        $src = $basePath . 'assets/js/klytos-hooks.js?v=' . $version;
        return '<script src="' . Helpers::escUrl($src) . '" defer></script>';
    }

    /**
     * Generate /assets/js/consent-manager.js
     * Copies the consent manager library to the output directory.
     * Only outputs the file when the Consent Manager is enabled.
     */
    public function buildConsentManagerJs(): void
    {
        $sourceFile = $this->app->getCorePath() . '/assets/consent-manager.js';
        $outputDir  = $this->outputPath . '/assets/js';

        if ( !file_exists( $sourceFile ) ) {
            klytos_set_option( 'consent_manager_js_version', '' );
            return;
        }

        $consentManager = $this->app->getConsentManager();
        $config         = $consentManager->getConfig();

        if ( empty( $config['enabled'] ) ) {
            // Remove stale file if consent is disabled.
            $outputFile = $outputDir . '/consent-manager.js';
            if ( file_exists( $outputFile ) ) {
                unlink( $outputFile );
            }
            klytos_set_option( 'consent_manager_js_version', '' );
            return;
        }

        $js = file_get_contents( $sourceFile );

        Helpers::ensureWritableDir( $outputDir );
        file_put_contents( $outputDir . '/consent-manager.js', $js, LOCK_EX );

        $version = substr( md5( $js ), 0, 8 );
        klytos_set_option( 'consent_manager_js_version', $version );
    }

    /**
     * Build the <script> tags for consent-manager.js and its init call.
     * Must NOT use defer — the consent manager must execute synchronously
     * before any other scripts to intercept blocked scripts.
     *
     * @param  string $basePath Site base path.
     * @return string HTML script tags or empty string.
     */
    private function buildConsentManagerJsTag( string $basePath ): string
    {
        $version = klytos_get_option( 'consent_manager_js_version', '' );
        if ( empty( $version ) ) {
            return '';
        }

        $consentManager = $this->app->getConsentManager();
        $config         = $consentManager->getConfig();

        if ( empty( $config['enabled'] ) ) {
            return '';
        }

        // Build the JS init config object.
        $jsConfig = [
            'bannerText' => $config['banner_text'],
            'autoShow'   => true,
        ];

        if ( !empty( $config['privacy_url'] ) ) {
            $jsConfig['privacyUrl'] = $config['privacy_url'];
        }

        if ( !empty( $config['categories'] ) ) {
            $jsConfig['categories'] = $config['categories'];
        }

        $jsConfig = klytos_apply_filters( 'consent.init_config', $jsConfig );

        $src        = $basePath . 'assets/js/consent-manager.js?v=' . $version;
        $configJson = json_encode( $jsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

        return '<script src="' . Helpers::escUrl( $src ) . '"></script>' . "\n  "
             . '<script>ConsentManager.init(' . $configJson . ');</script>';
    }

    /**
     * Build hreflang link tags for a page.
     */
    private function buildHreflangTags(array $page, array $siteConfig): string
    {
        $refs = $page['hreflang_refs'] ?? [];
        if (empty($refs)) {
            return '';
        }

        $siteUrl = Helpers::publicUrl();
        $tags    = [];

        foreach ($refs as $lang => $slug) {
            // Clean URL: 'en/about' → '/en/about/'
            $url    = rtrim($siteUrl, '/') . '/' . ltrim($slug, '/') . '/';
            $tags[] = '<link rel="alternate" hreflang="' . Helpers::escAttr($lang) . '" href="' . Helpers::escUrl($url) . '">';
        }

        // x-default (use the default language version).
        $defaultLang = $siteConfig['default_language'] ?? 'es';
        if (isset($refs[$defaultLang])) {
            $defaultUrl = rtrim($siteUrl, '/') . '/' . ltrim($refs[$defaultLang], '/') . '/';
            $tags[]     = '<link rel="alternate" hreflang="x-default" href="' . Helpers::escUrl($defaultUrl) . '">';
        }

        return implode("\n  ", $tags);
    }

    /**
     * Build a basic footer HTML.
     */
    private function buildFooterHtml(array $siteConfig): string
    {
        $name = Helpers::escHtml($siteConfig['site_name'] ?? 'Klytos Site');
        $year = klytos_gmdate( 'Y' );
        return "<footer class=\"klytos-footer\"><p>&copy; {$year} {$name}</p></footer>";
    }

    /**
     * Ensure the public web root has a proper .htaccess with clean URL rules.
     *
     * Creates or updates the .htaccess so that:
     * - /slug/ serves /slug/index.html (DirectoryIndex)
     * - /slug  redirects to /slug/ (trailing slash)
     * - Static files are served directly
     */
    private function ensurePublicHtaccess(): void
    {
        $htaccessPath = $this->outputPath . '/.htaccess';

        $content = "# Klytos — Public Site\n"
            . "DirectoryIndex index.html index.php\n\n"
            . "# Deny access to sensitive files\n"
            . "<FilesMatch \"^\\.(htaccess|htpasswd|install\\.done\\.php)$\">\n"
            . "    Require all denied\n"
            . "</FilesMatch>\n\n"
            . "# Maintenance mode — redirect to 503 page when .maintenance marker exists\n"
            . "RewriteEngine On\n"
            . "RewriteCond %{DOCUMENT_ROOT}/.maintenance -f\n"
            . "RewriteCond %{REQUEST_URI} !^/maintenance\\.html$ [NC]\n"
            . "RewriteCond %{REQUEST_URI} !\\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?)$ [NC]\n"
            . "RewriteRule ^ /maintenance.html [R=503,L]\n\n"
            . "# Clean URLs for static pages\n\n"
            . "# If the exact file exists, serve it directly\n"
            . "RewriteCond %{REQUEST_FILENAME} -f\n"
            . "RewriteRule ^ - [L]\n\n"
            . "# If the directory exists (with index.html inside), serve it\n"
            . "RewriteCond %{REQUEST_FILENAME} -d\n"
            . "RewriteRule ^ - [L]\n\n"
            . "# Add trailing slash if a directory with that name exists\n"
            . "RewriteCond %{REQUEST_FILENAME} !-f\n"
            . "RewriteCond %{REQUEST_FILENAME}/ -d\n"
            . "RewriteRule ^(.+[^/])$ $1/ [R=301,L]\n\n"
            . "# Serve /slug/ as /slug/index.html\n"
            . "RewriteCond %{REQUEST_FILENAME} !-f\n"
            . "RewriteCond %{DOCUMENT_ROOT}/%{REQUEST_URI}/index.html -f [OR]\n"
            . "RewriteCond %{REQUEST_FILENAME}/index.html -f\n"
            . "RewriteRule ^(.+)/$ $1/index.html [L]\n";

        file_put_contents( $htaccessPath, $content, LOCK_EX );
    }

    /**
     * Write or remove maintenance mode files based on site config.
     *
     * When maintenance_mode is enabled, writes:
     *   - .maintenance marker file (checked by .htaccess)
     *   - maintenance.html (503 page served to visitors)
     *
     * When disabled, removes both files.
     *
     * @param array $siteConfig Current site configuration.
     */
    private function handleMaintenanceMode( array $siteConfig ): void
    {
        $markerPath = $this->outputPath . '/.maintenance';
        $pagePath   = $this->outputPath . '/maintenance.html';
        $enabled    = (bool) ( $siteConfig['maintenance_mode'] ?? false );

        if ( $enabled ) {
            // Write .maintenance marker.
            file_put_contents( $markerPath, time(), LOCK_EX );

            // Build maintenance.html from template.
            $templatePath = dirname( __DIR__ ) . '/templates/maintenance.html';
            if ( file_exists( $templatePath ) ) {
                $html = file_get_contents( $templatePath );
                $defaultMsg = __( 'maintenance.default_message' );
                $message    = trim( $siteConfig['maintenance_message'] ?? '' );
                if ( $message === '' ) {
                    $message = $defaultMsg;
                }
                $html = str_replace(
                    ['{{site_name}}', '{{maintenance_message}}', '{{language}}'],
                    [
                        Helpers::escHtml( $siteConfig['site_name'] ?? 'Site' ),
                        Helpers::escHtml( $message ),
                        Helpers::escAttr( $siteConfig['default_language'] ?? 'en' ),
                    ],
                    $html
                );
                file_put_contents( $pagePath, $html, LOCK_EX );
            }

            klytos_do_action( 'maintenance.enabled' );
        } else {
            // Remove maintenance files if they exist.
            if ( file_exists( $markerPath ) ) {
                @unlink( $markerPath );
            }
            if ( file_exists( $pagePath ) ) {
                @unlink( $pagePath );
            }

            klytos_do_action( 'maintenance.disabled' );
        }
    }

    /**
     * Encrypt the page content area and replace it with a password gate.
     *
     * Uses PBKDF2 + AES-256-GCM so the static HTML can be decrypted
     * entirely client-side via SubtleCrypto — no server round-trip.
     *
     * @param  string $html     Full page HTML.
     * @param  string $password The password used to derive the encryption key.
     * @return string Modified HTML with encrypted content and password form.
     */
    private function encryptPageContent( string $html, string $password ): string
    {
        // Extract the <main> content (or fall back to <body> content).
        $contentToEncrypt = '';
        $beforeContent    = '';
        $afterContent     = '';

        if ( preg_match( '#(<main[^>]*>)(.*?)(</main>)#si', $html, $m ) ) {
            $beforeContent    = $m[1];
            $contentToEncrypt = $m[2];
            $afterContent     = $m[3];
        } elseif ( preg_match( '#(<body[^>]*>)(.*?)(</body>)#si', $html, $m ) ) {
            $beforeContent    = $m[1];
            $contentToEncrypt = $m[2];
            $afterContent     = $m[3];
        } else {
            // Cannot find content markers — encrypt whole HTML as fallback.
            $contentToEncrypt = $html;
        }

        if ( empty( $contentToEncrypt ) ) {
            return $html;
        }

        // Generate random salt (16 bytes) and IV (12 bytes for AES-GCM).
        $salt = random_bytes( 16 );
        $iv   = random_bytes( 12 );

        // Derive key via PBKDF2 (must mirror JS: 100k iterations, SHA-256, 256-bit key).
        $key = hash_pbkdf2( 'sha256', $password, $salt, 100000, 32, true );

        // Encrypt with AES-256-GCM.
        $tag        = '';
        $ciphertext = openssl_encrypt( $contentToEncrypt, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
        if ( $ciphertext === false ) {
            return $html; // Encryption failed — return unencrypted as safety fallback.
        }

        // Append GCM auth tag to ciphertext (SubtleCrypto expects them concatenated).
        $encryptedBlob = $ciphertext . $tag;

        // Encode to base64.
        $saltB64 = base64_encode( $salt );
        $ivB64   = base64_encode( $iv );
        $dataB64 = base64_encode( $encryptedBlob );

        // Load password form template.
        $templatePath = dirname( __DIR__ ) . '/templates/password-form.html';
        if ( !file_exists( $templatePath ) ) {
            return $html;
        }
        $formHtml = file_get_contents( $templatePath );

        // Replace template placeholders.
        $formHtml = str_replace(
            [
                '{{password_heading}}',
                '{{password_hint}}',
                '{{password_placeholder}}',
                '{{password_submit}}',
                '{{password_wrong}}',
                '{{enc_salt}}',
                '{{enc_iv}}',
                '{{enc_data}}',
            ],
            [
                Helpers::escHtml( __( 'password_protection.heading' ) ),
                Helpers::escHtml( __( 'password_protection.hint' ) ),
                Helpers::escAttr( __( 'password_protection.placeholder' ) ),
                Helpers::escHtml( __( 'password_protection.submit' ) ),
                Helpers::escHtml( __( 'password_protection.wrong' ) ),
                $saltB64,
                $ivB64,
                $dataB64,
            ],
            $formHtml
        );

        // Allow plugins to modify the password form.
        $formHtml = klytos_apply_filters( 'page.password_form', $formHtml );

        // Replace the content in the full HTML.
        if ( $beforeContent !== '' ) {
            $html = str_replace(
                $beforeContent . $contentToEncrypt . $afterContent,
                $beforeContent . $formHtml . $afterContent,
                $html
            );
        } else {
            $html = $formHtml;
        }

        return $html;
    }

    /**
     * Rewrite root-relative internal links in page content for subdirectory installs.
     *
     * Content authored via MCP uses root-relative links like href="/about/"
     * which must become href="/prueba/about/" when Klytos is installed in /prueba/.
     * Only rewrites links that start with "/" and are not already prefixed with basePath.
     * Skips external links (http://, https://, //, #, mailto:, tel:, javascript:).
     *
     * @param  string $html     The page content HTML.
     * @param  string $basePath The public base path (e.g. "/prueba/" or "/").
     * @return string Content with rewritten internal links.
     */
    private function rewriteInternalLinks( string $html, string $basePath ): string
    {
        // Nothing to rewrite if installed at root.
        if ( $basePath === '/' ) {
            return $html;
        }

        // Rewrite href="/..." and src="/..." that are internal root-relative links.
        // Skip links already prefixed with basePath, and skip absolute URLs.
        return preg_replace_callback(
            '#((?:href|src|action)\s*=\s*["\'])(/(?!/)[^"\']*?)(["\'])#i',
            function ( array $matches ) use ( $basePath ): string {
                $attr = $matches[1]; // e.g. href="
                $path = $matches[2]; // e.g. /about/
                $quote = $matches[3]; // e.g. "

                // Already has the basePath prefix — don't double-prefix.
                if ( str_starts_with( $path, $basePath ) ) {
                    return $matches[0];
                }

                // Rewrite: /about/ → /prueba/about/
                return $attr . rtrim( $basePath, '/' ) . $path . $quote;
            },
            $html
        );
    }

    /**
     * Generate robots.txt
     */
    private function generateRobotsTxt(array $siteConfig): void
    {
        $siteUrl = Helpers::publicUrl();
        $extra   = $siteConfig['seo']['robots_txt_extra'] ?? '';

        $indexingEnabled = $siteConfig['indexing_enabled'] ?? false;

        $content = "User-agent: *\n";

        if ( ! $indexingEnabled ) {
            // Site is not ready for indexing — block all crawlers.
            $content .= "Disallow: /\n";
        } else {
            $content .= "Allow: /\n";
            $content .= "Sitemap: {$siteUrl}sitemap.xml\n";
        }

        if (!empty($extra)) {
            $content .= "\n" . $extra . "\n";
        }

        file_put_contents($this->outputPath . '/robots.txt', $content, LOCK_EX);
    }

    /**
     * Generate sitemap.xml with hreflang support.
     */
    private function generateSitemap(array $pages, array $siteConfig): void
    {
        $siteUrl = rtrim(Helpers::publicUrl(), '/');

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($pages as $page) {
            $slug = $page['slug'] ?? 'index';
            // Clean URLs: 'index' → '/', 'about' → '/about/', 'servicios/marketing' → '/servicios/marketing/'
            $loc  = $slug === 'index'
                ? $siteUrl . '/'
                : $siteUrl . '/' . $slug . '/';

            // Priority: homepage = 1.0, top-level = 0.8, nested = 0.6
            $depth    = substr_count($slug, '/');
            $priority = $slug === 'index' ? '1.0' : ($depth === 0 ? '0.8' : '0.6');
            $changefreq = $slug === 'index' ? 'daily' : 'weekly';

            $xml .= "  <url>\n";
            $xml .= "    <loc>" . Helpers::escUrl($loc) . "</loc>\n";
            $xml .= "    <lastmod>" . ($page['updated_at'] ?? klytos_now_utc()) . "</lastmod>\n";
            $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";

            // Hreflang alternates (clean URLs).
            $refs = $page['hreflang_refs'] ?? [];
            if (!empty($refs)) {
                foreach ($refs as $lang => $refSlug) {
                    $refUrl = $siteUrl . '/' . ltrim($refSlug, '/') . '/';
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . Helpers::escAttr($lang) . '" href="' . Helpers::escUrl($refUrl) . '"/>' . "\n";
                }

                // x-default
                $defaultLang = $siteConfig['default_language'] ?? 'es';
                if (isset($refs[$defaultLang])) {
                    $defaultUrl = $siteUrl . '/' . ltrim($refs[$defaultLang], '/') . '/';
                    $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . Helpers::escUrl($defaultUrl) . '"/>' . "\n";
                }
            }

            $xml .= "  </url>\n";
        }

        // Allow plugins to add custom URLs to the sitemap.
        $pluginUrls = klytos_apply_filters('build.sitemap_urls', []);
        foreach ($pluginUrls as $pluginUrl) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . Helpers::escUrl($pluginUrl['loc'] ?? '') . "</loc>\n";
            if (!empty($pluginUrl['lastmod'])) {
                $xml .= "    <lastmod>" . Helpers::escHtml($pluginUrl['lastmod']) . "</lastmod>\n";
            }
            $xml .= "    <changefreq>" . ($pluginUrl['changefreq'] ?? 'monthly') . "</changefreq>\n";
            $xml .= "    <priority>" . ($pluginUrl['priority'] ?? '0.5') . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        file_put_contents($this->outputPath . '/sitemap.xml', $xml, LOCK_EX);
    }

    // ─── Block System (v2.0) ───────────────────────────────────

    /**
     * Render page content by assembling blocks from the page template.
     *
     * Used for v2.0 pages that have structured block content
     * instead of raw content_html.
     *
     * @param  array  $page          Page data with 'content' and 'template'.
     * @param  array  $excludeBlocks Block IDs to exclude (provided by the custom template).
     * @return string Rendered HTML from assembled blocks.
     */
    private function renderBlockContent( array $page, array $excludeBlocks = [] ): string
    {
        $templateManager = $this->app->getPageTemplateManager();
        return $templateManager->renderPage( $page['template'], $page, $excludeBlocks );
    }

    /**
     * Pre-render all global-scope blocks and cache the HTML.
     *
     * Called once during buildAll() so global blocks (header, footer, etc.)
     * are not re-rendered for every page.
     *
     * @return array Map of block_id => rendered HTML.
     */
    private function cacheGlobalBlocks(): array
    {
        $blockManager = $this->app->getBlockManager();
        $allBlocks    = $blockManager->list('all', 'active');
        $cache        = [];

        foreach ($allBlocks as $block) {
            if (($block['scope'] ?? '') !== 'global') {
                continue;
            }

            $blockId   = $block['id'] ?? '';
            $globalData = $blockManager->getGlobalData($blockId);

            try {
                $cache[$blockId] = $blockManager->render($blockId, $globalData);
            } catch (\RuntimeException $e) {
                $cache[$blockId] = "<!-- Global block '{$blockId}' render error -->";
            }
        }

        return klytos_apply_filters('build.global_blocks', $cache);
    }

    /**
     * Smart rebuild: replace a single global block across all generated HTML files.
     *
     * Uses the HTML comment markers (<!--klytos:block:NAME-->) injected by
     * BlockManager::render() to find and replace the block content without
     * a full site rebuild.
     *
     * @param  string $blockId Block ID to rebuild.
     * @return array  Result with block_id and files_updated count.
     */
    public function smartRebuildBlock(string $blockId): array
    {
        $blockManager = $this->app->getBlockManager();
        $globalData   = $blockManager->getGlobalData($blockId);
        $newHtml      = $blockManager->render($blockId, $globalData);

        $pattern = '/<!--klytos:block:' . preg_quote($blockId, '/') . '-->.*?<!--\/klytos:block:' . preg_quote($blockId, '/') . '-->/s';

        $filesUpdated = 0;
        $iterator     = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->outputPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'html') {
                continue;
            }

            $html = file_get_contents($file->getPathname());
            if (str_contains($html, "<!--klytos:block:{$blockId}-->")) {
                $html = preg_replace($pattern, $newHtml, $html);
                file_put_contents($file->getPathname(), $html, LOCK_EX);
                $filesUpdated++;
            }
        }

        return [
            'block_id'      => $blockId,
            'files_updated' => $filesUpdated,
        ];
    }

    /**
     * Generate /assets/css/blocks.css
     *
     * Aggregates CSS from all active blocks into a single file.
     */
    public function generateBlocksCss(): void
    {
        $blockManager = $this->app->getBlockManager();
        $allBlocks    = $blockManager->list('all', 'active');
        $outputDir    = $this->outputPath . '/assets/css';

        $css        = "/* Generated by Klytos Build Engine — Block CSS */\n\n";
        $hasContent = false;

        foreach ($allBlocks as $block) {
            $blockCss = $block['css'] ?? '';
            if (empty($blockCss)) {
                continue;
            }

            $blockId  = $block['id'] ?? 'unknown';
            $blockCss = klytos_apply_filters('block.css', $blockCss, $blockId);

            $css .= "/* --- Block: {$blockId} --- */\n";
            $css .= $blockCss . "\n\n";
            $hasContent = true;
        }

        Helpers::ensureWritableDir($outputDir);

        if ($hasContent) {
            file_put_contents($outputDir . '/blocks.css', $css, LOCK_EX);
            $version = substr(md5($css), 0, 8);
            klytos_set_option('klytos_blocks_css_version', $version);
        } else {
            $blocksFile = $outputDir . '/blocks.css';
            if (file_exists($blocksFile)) {
                unlink($blocksFile);
            }
            klytos_set_option('klytos_blocks_css_version', '');
        }
    }

    /**
     * Generate /assets/js/blocks.js
     *
     * Aggregates JS from all active blocks that have JS into a single file.
     */
    public function generateBlocksJs(): void
    {
        $blockManager = $this->app->getBlockManager();
        $allBlocks    = $blockManager->list('all', 'active');
        $outputDir    = $this->outputPath . '/assets/js';

        $js         = "/* Generated by Klytos Build Engine — Block JS */\n\n";
        $hasContent = false;

        foreach ($allBlocks as $block) {
            $blockJs = $block['js'] ?? '';
            if (empty($blockJs)) {
                continue;
            }

            $blockId = $block['id'] ?? 'unknown';
            $js .= "// --- Block: {$blockId} ---\n";
            $js .= $blockJs . "\n\n";
            $hasContent = true;
        }

        Helpers::ensureWritableDir($outputDir);

        if ($hasContent) {
            file_put_contents($outputDir . '/blocks.js', $js, LOCK_EX);
            $version = substr(md5($js), 0, 8);
            klytos_set_option('klytos_blocks_js_version', $version);
        } else {
            $blocksFile = $outputDir . '/blocks.js';
            if (file_exists($blocksFile)) {
                unlink($blocksFile);
            }
            klytos_set_option('klytos_blocks_js_version', '');
        }
    }

    /**
     * Build the <link> tag for blocks.css if blocks have CSS.
     *
     * @param  string $basePath Site base path.
     * @return string HTML link tag or empty string.
     */
    private function buildBlocksCssLink(string $basePath): string
    {
        $version = klytos_get_option('klytos_blocks_css_version', '');
        if (empty($version)) {
            return '';
        }
        $href = $basePath . 'assets/css/blocks.css?v=' . $version;
        return '<link rel="stylesheet" href="' . Helpers::escUrl($href) . '">';
    }

    /**
     * Build the <script> tag for blocks.js if blocks have JS.
     *
     * @param  string $basePath Site base path.
     * @return string HTML script tag or empty string.
     */
    private function buildBlocksJsTag(string $basePath): string
    {
        $version = klytos_get_option('klytos_blocks_js_version', '');
        if (empty($version)) {
            return '';
        }
        $src = $basePath . 'assets/js/blocks.js?v=' . $version;
        return '<script src="' . Helpers::escUrl($src) . '" defer></script>';
    }

    // ─── Styles ─────────────────────────────────────────────────

    /**
     * Base CSS reset and responsive styles.
     */
    private function getBaseCss(): string
    {
        return <<<'CSS'
/* Reset */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: var(--klytos-font-body, 16px); scroll-behavior: smooth; }
body {
  font-family: var(--klytos-font-body);
  font-weight: 400;
  line-height: 1.6;
  color: var(--klytos-text);
  background-color: var(--klytos-background);
  -webkit-font-smoothing: antialiased;
}
img, video { max-width: 100%; height: auto; display: block; }
a { color: var(--klytos-primary); text-decoration: none; }
a:hover { text-decoration: underline; }

/* Typography */
h1, h2, h3, h4, h5, h6 {
  font-family: var(--klytos-font-heading);
  font-weight: 700;
  line-height: 1.2;
  color: var(--klytos-text);
  margin-bottom: 0.5em;
}
h1 { font-size: 2.488em; }
h2 { font-size: 2.074em; }
h3 { font-size: 1.728em; }
h4 { font-size: 1.44em; }
h5 { font-size: 1.2em; }
h6 { font-size: 1em; }
p { margin-bottom: 1em; }
code, pre { font-family: var(--klytos-font-code); }
pre { background: var(--klytos-surface); padding: 1rem; border-radius: var(--klytos-radius); overflow-x: auto; }
blockquote {
  border-left: 4px solid var(--klytos-primary);
  padding: 0.5em 1em;
  margin: 1em 0;
  color: var(--klytos-text-muted);
}

/* Layout */
.klytos-container { max-width: var(--klytos-max-width); margin: 0 auto; padding: 0 var(--klytos-spacing); }
.klytos-header {
  background: var(--klytos-surface);
  border-bottom: 1px solid var(--klytos-border);
  padding: var(--klytos-spacing) 0;
}
.klytos-header.sticky { position: sticky; top: 0; z-index: 100; }
.klytos-header .klytos-container { display: flex; align-items: center; justify-content: space-between; }
.klytos-main { padding: calc(var(--klytos-spacing) * 2) 0; min-height: 60vh; }
.klytos-footer {
  background: var(--klytos-surface);
  border-top: 1px solid var(--klytos-border);
  padding: calc(var(--klytos-spacing) * 2) 0;
  text-align: center;
  color: var(--klytos-text-muted);
}

/* Logo */
.klytos-logo { font-size: 1.25rem; font-weight: 700; color: var(--klytos-text); text-decoration: none; }
.klytos-logo:hover { text-decoration: none; color: var(--klytos-primary); }

/* Navigation */
.klytos-nav { display: flex; align-items: center; }
.klytos-menu { list-style: none; display: flex; gap: var(--klytos-spacing); }
.klytos-menu a { color: var(--klytos-text); font-weight: 500; transition: color 0.2s; }
.klytos-menu a:hover { color: var(--klytos-primary); text-decoration: none; }
.klytos-submenu { list-style: none; display: none; position: absolute; background: var(--klytos-surface); border: 1px solid var(--klytos-border); border-radius: var(--klytos-radius); padding: 0.5rem 0; min-width: 200px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.has-children:hover .klytos-submenu { display: block; }
.has-children { position: relative; }
.klytos-nav:empty { display: none; }

/* Buttons */
.klytos-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.5rem; border: none; border-radius: var(--klytos-radius); font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s; text-decoration: none; }
.klytos-btn:hover { text-decoration: none; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.klytos-btn-primary { background: var(--klytos-primary); color: #fff; }
.klytos-btn-primary:hover { background: var(--klytos-accent, var(--klytos-primary)); }
.klytos-btn-secondary { background: var(--klytos-secondary); color: #fff; }
.klytos-btn-outline { background: transparent; border: 2px solid var(--klytos-primary); color: var(--klytos-primary); }
.klytos-btn-outline:hover { background: var(--klytos-primary); color: #fff; }
.klytos-btn-lg { padding: 0.85rem 2rem; font-size: 1.1rem; }

/* Cards */
.klytos-card { background: var(--klytos-surface); border: 1px solid var(--klytos-border); border-radius: var(--klytos-radius); padding: 1.5rem; }
.klytos-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--klytos-spacing); }

/* Hero Section */
.klytos-hero { padding: calc(var(--klytos-spacing) * 4) 0; text-align: center; }
.klytos-hero h1 { font-size: 3rem; margin-bottom: 1rem; }
.klytos-hero p { font-size: 1.2rem; color: var(--klytos-text-muted); max-width: 700px; margin: 0 auto 2rem; }

/* Sections */
.klytos-section { padding: calc(var(--klytos-spacing) * 3) 0; }
.klytos-section-alt { background: var(--klytos-surface); }
.klytos-section h2 { text-align: center; margin-bottom: calc(var(--klytos-spacing) * 2); }

/* Grid utilities */
.klytos-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--klytos-spacing); }
.klytos-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--klytos-spacing); }
.klytos-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--klytos-spacing); }
.klytos-text-center { text-align: center; }
.klytos-text-muted { color: var(--klytos-text-muted); }

/* Responsive */
@media (max-width: 768px) {
  h1 { font-size: 1.8em; }
  h2 { font-size: 1.5em; }
  .klytos-hero h1 { font-size: 2rem; }
  .klytos-menu { flex-direction: column; gap: 0.5rem; }
  .klytos-header .klytos-container { flex-direction: column; gap: 1rem; }
  .klytos-grid-2, .klytos-grid-3, .klytos-grid-4 { grid-template-columns: 1fr; }
  .klytos-btn-lg { width: 100%; justify-content: center; }
}

/* ── Extended Grid System ─────────────────────── */
.klytos-grid-5{display:grid;grid-template-columns:repeat(5,1fr);gap:1.5rem}
.klytos-grid-6{display:grid;grid-template-columns:repeat(6,1fr);gap:1.5rem}
.klytos-grid-auto{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem}

/* ── Section Backgrounds ──────────────────────── */
.klytos-section-dark{background:var(--klytos-text,#1e293b);color:#f1f5f9}
.klytos-section-dark h1,.klytos-section-dark h2,.klytos-section-dark h3,.klytos-section-dark h4{color:#fff}
.klytos-section-dark a{color:var(--klytos-accent,#60a5fa)}
.klytos-section-light{background:var(--klytos-surface,#f8fafc);color:var(--klytos-text,#1e293b)}
.klytos-section-primary{background:var(--klytos-primary,#2563eb);color:#fff}
.klytos-section-primary h1,.klytos-section-primary h2,.klytos-section-primary h3{color:#fff}
.klytos-section-gradient{background:linear-gradient(135deg,var(--klytos-primary,#2563eb) 0%,var(--klytos-secondary,#7c3aed) 100%);color:#fff}
.klytos-section-gradient h1,.klytos-section-gradient h2,.klytos-section-gradient h3{color:#fff}

/* ── Shadows ──────────────────────────────────── */
.klytos-shadow-sm{box-shadow:0 1px 2px rgba(0,0,0,0.05)}
.klytos-shadow{box-shadow:0 1px 3px rgba(0,0,0,0.1),0 1px 2px rgba(0,0,0,0.06)}
.klytos-shadow-md{box-shadow:0 4px 6px -1px rgba(0,0,0,0.1),0 2px 4px -1px rgba(0,0,0,0.06)}
.klytos-shadow-lg{box-shadow:0 10px 15px -3px rgba(0,0,0,0.1),0 4px 6px -2px rgba(0,0,0,0.05)}
.klytos-shadow-xl{box-shadow:0 20px 25px -5px rgba(0,0,0,0.1),0 10px 10px -5px rgba(0,0,0,0.04)}

/* ── Spacing Utilities ────────────────────────── */
.klytos-py-0{padding-top:0;padding-bottom:0}
.klytos-py-2{padding-top:2rem;padding-bottom:2rem}
.klytos-py-3{padding-top:3rem;padding-bottom:3rem}
.klytos-py-4{padding-top:4rem;padding-bottom:4rem}
.klytos-py-5{padding-top:5rem;padding-bottom:5rem}
.klytos-py-6{padding-top:6rem;padding-bottom:6rem}
.klytos-px-1{padding-left:1rem;padding-right:1rem}
.klytos-px-2{padding-left:2rem;padding-right:2rem}
.klytos-mt-0{margin-top:0}.klytos-mt-1{margin-top:1rem}.klytos-mt-2{margin-top:2rem}.klytos-mt-3{margin-top:3rem}
.klytos-mb-0{margin-bottom:0}.klytos-mb-1{margin-bottom:1rem}.klytos-mb-2{margin-bottom:2rem}.klytos-mb-3{margin-bottom:3rem}
.klytos-gap-1{gap:1rem}.klytos-gap-2{gap:2rem}.klytos-gap-3{gap:3rem}

/* ── Typography Utilities ─────────────────────── */
.klytos-text-center{text-align:center}
.klytos-text-left{text-align:left}
.klytos-text-right{text-align:right}
.klytos-text-muted{color:var(--klytos-text-muted,#64748b)}
.klytos-text-sm{font-size:0.875rem}
.klytos-text-lg{font-size:1.125rem}
.klytos-text-xl{font-size:1.25rem}
.klytos-text-2xl{font-size:1.5rem}
.klytos-text-3xl{font-size:2rem}
.klytos-text-4xl{font-size:2.5rem}
.klytos-font-bold{font-weight:700}
.klytos-font-semibold{font-weight:600}
.klytos-font-normal{font-weight:400}
.klytos-leading-tight{line-height:1.25}
.klytos-leading-relaxed{line-height:1.75}

/* ── Flexbox Utilities ────────────────────────── */
.klytos-flex{display:flex}
.klytos-flex-col{display:flex;flex-direction:column}
.klytos-flex-wrap{flex-wrap:wrap}
.klytos-items-center{align-items:center}
.klytos-items-start{align-items:flex-start}
.klytos-justify-center{justify-content:center}
.klytos-justify-between{justify-content:space-between}
.klytos-justify-around{justify-content:space-around}

/* ── Card Variants ────────────────────────────── */
.klytos-card-hover{transition:transform 0.2s ease,box-shadow 0.2s ease}
.klytos-card-hover:hover{transform:translateY(-4px);box-shadow:0 12px 24px rgba(0,0,0,0.12)}
.klytos-card-bordered{border:1px solid var(--klytos-border,#e2e8f0)}
.klytos-card-flat{background:var(--klytos-surface,#f8fafc);border:none;border-radius:var(--klytos-radius,8px);padding:1.5rem}

/* ── Border & Radius Utilities ────────────────── */
.klytos-rounded{border-radius:var(--klytos-radius,8px)}
.klytos-rounded-lg{border-radius:calc(var(--klytos-radius,8px) * 1.5)}
.klytos-rounded-xl{border-radius:calc(var(--klytos-radius,8px) * 2)}
.klytos-rounded-full{border-radius:9999px}
.klytos-border{border:1px solid var(--klytos-border,#e2e8f0)}
.klytos-border-t{border-top:1px solid var(--klytos-border,#e2e8f0)}
.klytos-border-b{border-bottom:1px solid var(--klytos-border,#e2e8f0)}

/* ── Display & Width Utilities ────────────────── */
.klytos-hidden{display:none}
.klytos-block{display:block}
.klytos-inline-block{display:inline-block}
.klytos-w-full{width:100%}
.klytos-max-w-sm{max-width:640px;margin-left:auto;margin-right:auto}
.klytos-max-w-md{max-width:768px;margin-left:auto;margin-right:auto}
.klytos-max-w-lg{max-width:1024px;margin-left:auto;margin-right:auto}
.klytos-max-w-xl{max-width:1280px;margin-left:auto;margin-right:auto}
.klytos-overflow-hidden{overflow:hidden}

/* ── Visual Effects ───────────────────────────── */
.klytos-opacity-50{opacity:0.5}
.klytos-opacity-75{opacity:0.75}
.klytos-opacity-90{opacity:0.9}
.klytos-transition{transition:all 0.2s ease}
.klytos-hover-scale:hover{transform:scale(1.02)}
.klytos-hover-brightness:hover{filter:brightness(1.1)}

/* ── Button Variants ──────────────────────────── */
.klytos-btn-ghost{background:transparent;color:var(--klytos-primary,#2563eb);border:none;padding:0.6rem 1.2rem}
.klytos-btn-ghost:hover{background:rgba(0,0,0,0.05)}
.klytos-btn-rounded{border-radius:9999px}
.klytos-btn-sm{padding:0.4rem 1rem;font-size:0.875rem}
.klytos-btn-xl{padding:1rem 2.5rem;font-size:1.125rem}

/* ── Extended Responsive ──────────────────────── */
@media(max-width:1200px){
  .klytos-grid-5,.klytos-grid-6{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:1024px){
  .klytos-grid-5,.klytos-grid-6{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:768px){
  .klytos-grid-5,.klytos-grid-6,.klytos-grid-auto{grid-template-columns:1fr}
  .klytos-text-4xl{font-size:2rem}
  .klytos-text-3xl{font-size:1.75rem}
  .klytos-py-5,.klytos-py-6{padding-top:3rem;padding-bottom:3rem}
  .klytos-hide-mobile{display:none}
}
@media(max-width:576px){
  .klytos-text-2xl{font-size:1.25rem}
  .klytos-px-2{padding-left:1rem;padding-right:1rem}
  .klytos-hide-sm{display:none}
}
@media(min-width:769px){
  .klytos-show-mobile{display:none}
}
CSS;
    }

    /**
     * Generate llms.txt and llms-full.txt for AI crawler indexing.
     *
     * Follows the llms.txt specification (https://llmstxt.org/).
     * - llms.txt: Summary with page titles, URLs, and descriptions.
     * - llms-full.txt: Full text content of every published page.
     *
     * @param array $pages      Published pages.
     * @param array $siteConfig Site configuration.
     */
    private function generateLlmsTxt(array $pages, array $siteConfig): void
    {
        $siteName = $siteConfig['site_name'] ?? 'Klytos Site';
        $siteDesc = $siteConfig['description'] ?? '';
        $siteUrl  = rtrim(Helpers::publicUrl(), '/');

        // ─── llms.txt (summary) ──────────────────────────────
        $summary  = "# {$siteName}\n\n";

        if (!empty($siteDesc)) {
            $summary .= "> {$siteDesc}\n\n";
        }

        $summary .= "## Pages\n\n";

        foreach ($pages as $page) {
            $slug  = $page['slug'] ?? 'index';
            $title = $page['title'] ?? $slug;
            $desc  = $page['meta_description'] ?? '';
            // Clean URLs: 'index' → '/', 'about' → '/about/'
            $url   = $slug === 'index'
                ? "{$siteUrl}/"
                : "{$siteUrl}/{$slug}/";

            $summary .= "- [{$title}]({$url})";
            if (!empty($desc)) {
                $summary .= ": {$desc}";
            }
            $summary .= "\n";
        }

        file_put_contents($this->outputPath . '/llms.txt', $summary, LOCK_EX);

        // ─── llms-full.txt (detailed content) ────────────────
        $full = "# {$siteName}\n\n";

        if (!empty($siteDesc)) {
            $full .= "> {$siteDesc}\n\n";
        }

        foreach ($pages as $page) {
            $slug    = $page['slug'] ?? 'index';
            $title   = $page['title'] ?? $slug;
            $content = $page['content_html'] ?? '';

            // Strip HTML tags but preserve structure with newlines.
            $textContent = strip_tags(
                str_replace(
                    ['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>', '</h1>', '</h2>', '</h3>', '</h4>'],
                    ["\n", "\n", "\n", "\n\n", "\n", "\n", "\n\n", "\n\n", "\n\n", "\n\n"],
                    $content
                )
            );
            $textContent = trim(preg_replace('/\n{3,}/', "\n\n", $textContent));

            $full .= "---\n\n";
            $full .= "## {$title}\n\n";
            $pageUrl = $slug === 'index'
                ? "{$siteUrl}/"
                : "{$siteUrl}/{$slug}/";
            $full .= "URL: {$pageUrl}\n\n";

            if (!empty($textContent)) {
                $full .= $textContent . "\n\n";
            }
        }

        file_put_contents($this->outputPath . '/llms-full.txt', $full, LOCK_EX);
    }

    /**
     * Build SEO meta tags for a page.
     *
     * Includes: generator, Open Graph, Twitter Cards, JSON-LD, canonical URL.
     * These are injected into the {{seo_meta_tags}} placeholder.
     *
     * @param  array  $page       Page data.
     * @param  array  $siteConfig Site configuration.
     * @return string HTML meta tags.
     */
    private function buildSeoMetaTags(array $page, array $siteConfig): string
    {
        $siteUrl   = rtrim(Helpers::publicUrl(), '/');
        $slug      = $page['slug'] ?? 'index';
        // Clean URLs: 'index' → '/', 'about' → '/about/'
        $pageUrl   = $slug === 'index'
            ? "{$siteUrl}/"
            : "{$siteUrl}/{$slug}/";
        $title     = $page['title'] ?? '';
        $siteName  = $siteConfig['site_name'] ?? '';
        $desc      = $page['meta_description'] ?? $siteConfig['description'] ?? '';
        $ogImage   = $page['og_image'] ?? ($siteConfig['seo']['default_og_image'] ?? '');
        $lang      = $page['lang'] ?? ($siteConfig['default_language'] ?? 'es');
        $version   = $this->app->getVersion();

        // Per-page SEO overrides for social media.
        $ogTitle  = ! empty( $page['og_title'] ) ? $page['og_title'] : $title;
        $ogDesc   = ! empty( $page['og_description'] ) ? $page['og_description'] : $desc;
        $twTitle  = ! empty( $page['twitter_title'] ) ? $page['twitter_title'] : $ogTitle;
        $twDesc   = ! empty( $page['twitter_description'] ) ? $page['twitter_description'] : $ogDesc;
        $canonical = ! empty( $page['canonical_url'] ) ? $page['canonical_url'] : $pageUrl;
        $globalIndexing = $siteConfig['indexing_enabled'] ?? false;
        $noIndex        = ! $globalIndexing || ! empty( $page['noindex'] );

        $tags = [];

        // Generator meta tag — identifies the CMS.
        $tags[] = "<meta name=\"generator\" content=\"Klytos {$version}\">";

        // Robots — noindex if site indexing is disabled globally OR per-page.
        if ( $noIndex ) {
            $tags[] = "<meta name=\"robots\" content=\"noindex, nofollow\">";
        }

        // Canonical URL.
        $tags[] = "<link rel=\"canonical\" href=\"" . Helpers::escUrl( $canonical ) . "\">";

        // Open Graph tags (Facebook, LinkedIn, etc.).
        $tags[] = "<meta property=\"og:type\" content=\"website\">";
        $tags[] = "<meta property=\"og:title\" content=\"" . Helpers::escAttr( $ogTitle ) . "\">";
        $tags[] = "<meta property=\"og:description\" content=\"" . Helpers::escAttr( $ogDesc ) . "\">";
        $tags[] = "<meta property=\"og:url\" content=\"" . Helpers::escUrl( $pageUrl ) . "\">";
        $tags[] = "<meta property=\"og:site_name\" content=\"" . Helpers::escAttr( $siteName ) . "\">";
        $tags[] = "<meta property=\"og:locale\" content=\"" . Helpers::escAttr( $lang ) . "\">";

        if ( ! empty( $ogImage ) ) {
            $tags[] = "<meta property=\"og:image\" content=\"" . Helpers::escUrl( $ogImage ) . "\">";
            $tags[] = "<meta property=\"og:image:width\" content=\"1200\">";
            $tags[] = "<meta property=\"og:image:height\" content=\"630\">";
        }

        // Twitter Card tags.
        $tags[] = "<meta name=\"twitter:card\" content=\"summary_large_image\">";
        $tags[] = "<meta name=\"twitter:title\" content=\"" . Helpers::escAttr( $twTitle ) . "\">";
        $tags[] = "<meta name=\"twitter:description\" content=\"" . Helpers::escAttr( $twDesc ) . "\">";

        if (!empty($ogImage)) {
            $tags[] = "<meta name=\"twitter:image\" content=\"" . Helpers::escUrl( $ogImage ) . "\">";
        }

        // JSON-LD Structured Data (WebPage schema).
        $jsonLd = [
            '@context'    => 'https://schema.org',
            '@type'       => 'WebPage',
            'name'        => $title,
            'description' => $desc,
            'url'         => $pageUrl,
            'inLanguage'  => $lang,
            'publisher'   => [
                '@type' => 'Organization',
                'name'  => $siteName,
            ],
        ];

        if (!empty($ogImage)) {
            $jsonLd['image'] = $ogImage;
        }

        $tags[] = "<script type=\"application/ld+json\">"
                . json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                . "</script>";

        return implode("\n  ", $tags);
    }

    /**
     * Minimal fallback template with full SEO support.
     *
     * This template is used when no custom or built-in template is found.
     * It includes all modern SEO tags, analytics script, and hook placeholders.
     */
    private function getMinimalTemplate(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="{{page_lang}}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{page_title}}{{title_separator}}{{site_name}}</title>
  <meta name="description" content="{{meta_description}}">
  {{seo_meta_tags}}
  {{google_fonts_html}}
  <link rel="stylesheet" href="{{base_path}}assets/css/style.css">
  {{blocks_css_link}}
  {{hreflang_tags}}
  {{head_scripts}}
  {{plugin_head_html}}
</head>
<body>
  <div class="klytos-container">
    {{menu_html}}
    {{breadcrumbs}}
    <main class="klytos-main">
      {{page_content}}
    </main>
    {{footer_html}}
  </div>
  {{custom_css}}
  {{custom_js}}
  {{blocks_js_script}}
  <script src="{{base_path}}assets/js/klytos-analytics.js" defer></script>
  {{body_scripts}}
  {{plugin_body_end_html}}
</body>
</html>
HTML;
    }

    // ─── Search Index ───────────────────────────────────────────

    /**
     * Generate a search index JSON file for client-side search.
     *
     * Outputs /search-index.json at the web root with an array of page entries
     * containing: slug, title, description, content (plaintext, truncated),
     * post_type, and lang. Client-side JavaScript (Fuse.js) uses this for
     * fuzzy full-text search on the static site.
     *
     * Also generates a /search/index.html search results page.
     *
     * @param array $pages      Published pages (already filtered by buildAll).
     * @param array $siteConfig Site configuration.
     */
    private function generateSearchIndex( array $pages, array $siteConfig ): void
    {
        $entries = [];

        foreach ( $pages as $page ) {
            $slug = $page['slug'] ?? 'index';

            // Strip HTML tags to get plaintext for search indexing.
            $html      = $page['content_html'] ?? '';
            $plaintext = strip_tags( preg_replace( '/<!--.*?-->/s', '', $html ) );
            $plaintext = preg_replace( '/\s+/', ' ', trim( $plaintext ) );

            // Truncate to ~500 chars to keep the index lightweight.
            if ( mb_strlen( $plaintext ) > 500 ) {
                $plaintext = mb_substr( $plaintext, 0, 500 );
            }

            $entry = [
                'slug'        => $slug,
                'title'       => $page['title'] ?? '',
                'description' => $page['meta_description'] ?? '',
                'content'     => $plaintext,
                'post_type'   => $page['post_type'] ?? 'page',
                'lang'        => $page['lang'] ?? '',
            ];

            $entries[] = $entry;
        }

        // Allow plugins to modify the search index entries.
        $entries = klytos_apply_filters( 'build.search_index', $entries );

        // Write the search index JSON.
        $json = json_encode( $entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        file_put_contents( $this->outputPath . '/search-index.json', $json, LOCK_EX );

        // Generate the search page at /search/index.html.
        $this->generateSearchPage( $siteConfig );
    }

    /**
     * Generate the search results page.
     *
     * Creates a lightweight HTML page at /search/index.html that loads
     * Fuse.js and the search index to provide client-side fuzzy search.
     *
     * @param array $siteConfig Site configuration.
     */
    private function generateSearchPage( array $siteConfig ): void
    {
        $basePath = Helpers::getPublicBasePath();
        $siteName = Helpers::escHtml( $siteConfig['site_name'] ?? 'Klytos' );
        $lang     = $siteConfig['default_language'] ?? 'es';

        $searchLabels = [
            'title'       => $lang === 'es' ? 'Buscar' : 'Search',
            'placeholder' => $lang === 'es' ? 'Escribe tu búsqueda...' : 'Type your search...',
            'no_results'  => $lang === 'es' ? 'No se encontraron resultados.' : 'No results found.',
            'searching'   => $lang === 'es' ? 'Buscando...' : 'Searching...',
        ];

        $html = <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$searchLabels['title']} — {$siteName}</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="{$basePath}assets/css/style.css">
  <style>
    .search-container { max-width: 720px; margin: 2rem auto; padding: 0 1rem; }
    .search-input { width: 100%; padding: 0.75rem 1rem; font-size: 1.1rem; border: 2px solid var(--klytos-border, #333); border-radius: 8px; background: var(--klytos-surface, #1a1a2e); color: var(--klytos-text, #e0e0e0); outline: none; }
    .search-input:focus { border-color: var(--klytos-primary, #6366f1); }
    .search-results { list-style: none; padding: 0; margin-top: 1.5rem; }
    .search-result { margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--klytos-border, #333); }
    .search-result a { font-size: 1.2rem; font-weight: 600; color: var(--klytos-primary, #6366f1); text-decoration: none; }
    .search-result a:hover { text-decoration: underline; }
    .search-result p { margin: 0.5rem 0 0; color: var(--klytos-text-muted, #999); font-size: 0.95rem; }
    .search-result .search-url { font-size: 0.85rem; color: var(--klytos-text-muted, #777); }
    .search-no-results { color: var(--klytos-text-muted, #999); font-style: italic; }
  </style>
</head>
<body>
  <div class="search-container">
    <h1>{$searchLabels['title']}</h1>
    <input type="search" id="klytos-search" class="search-input" placeholder="{$searchLabels['placeholder']}" autofocus>
    <div id="klytos-search-status" class="search-no-results" style="display:none;"></div>
    <ul id="klytos-search-results" class="search-results"></ul>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/fuse.js@7.0.0/dist/fuse.min.js"></script>
  <script>
  (function() {
    var input = document.getElementById('klytos-search');
    var resultsEl = document.getElementById('klytos-search-results');
    var statusEl = document.getElementById('klytos-search-status');
    var basePath = '{$basePath}';
    var noResultsText = '{$searchLabels['no_results']}';
    var fuse = null;
    var debounceTimer = null;

    fetch(basePath + 'search-index.json')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        fuse = new Fuse(data, {
          keys: [
            { name: 'title', weight: 0.4 },
            { name: 'description', weight: 0.3 },
            { name: 'content', weight: 0.3 }
          ],
          threshold: 0.3,
          includeScore: true,
          minMatchCharLength: 2
        });
        // Check URL params for initial query.
        var params = new URLSearchParams(window.location.search);
        var q = params.get('q');
        if (q) {
          input.value = q;
          doSearch(q);
        }
      });

    input.addEventListener('input', function() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function() { doSearch(input.value); }, 200);
    });

    function doSearch(query) {
      if (!fuse || query.length < 2) {
        resultsEl.innerHTML = '';
        statusEl.style.display = 'none';
        return;
      }
      var results = fuse.search(query, { limit: 20 });
      if (results.length === 0) {
        resultsEl.innerHTML = '';
        statusEl.textContent = noResultsText;
        statusEl.style.display = 'block';
        return;
      }
      statusEl.style.display = 'none';
      resultsEl.innerHTML = results.map(function(r) {
        var item = r.item;
        var url = item.slug === 'index' ? basePath : basePath + item.slug + '/';
        var desc = item.description || item.content.substring(0, 150) + '...';
        return '<li class="search-result">'
          + '<a href="' + url + '">' + escHtml(item.title) + '</a>'
          + '<div class="search-url">' + url + '</div>'
          + '<p>' + escHtml(desc) + '</p>'
          + '</li>';
      }).join('');
    }

    function escHtml(str) {
      var div = document.createElement('div');
      div.appendChild(document.createTextNode(str));
      return div.innerHTML;
    }
  })();
  </script>
</body>
</html>
HTML;

        $searchDir = $this->outputPath . '/search';
        Helpers::ensureWritableDir( $searchDir );
        file_put_contents( $searchDir . '/index.html', $html, LOCK_EX );
    }
}
