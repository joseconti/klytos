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

        // 1f. Pre-render global blocks (scope=global) and cache in memory.
        $this->globalBlocksCache = $this->cacheGlobalBlocks();

        // 2. Get global data
        $siteConfig = $this->app->getSiteConfig()->get();
        $menuHtml   = $this->app->getMenu()->toHtml(Helpers::getBasePath());
        $theme      = $this->app->getTheme()->get();

        // 3. Build each published page
        $pages = $this->app->getPages()->list('published');

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

        // 7. Update build timestamp
        $this->app->getSiteConfig()->updateBuildTimestamp();

        // 8. Fire build.after hook for plugins.
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
     * Build a single page.
     *
     * @param  string $slug
     * @return array
     */
    public function buildPage(string $slug): array
    {
        $page       = $this->app->getPages()->get($slug);
        $siteConfig = $this->app->getSiteConfig()->get();
        $menuHtml   = $this->app->getMenu()->toHtml(Helpers::getBasePath());
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
        $menuHtml   = $this->app->getMenu()->toHtml(Helpers::getBasePath());
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
    private function renderTemplate(array $page, array $siteConfig, string $menuHtml, array $theme): string
    {
        $templateHtml = $this->resolveTemplateForPage($page);

        // Process template parts ({{klytos_part:X}}) BEFORE variable replacement.
        $templateHtml = $this->processTemplateParts($templateHtml);

        // Build hreflang tags
        $hreflangHtml = $this->buildHreflangTags($page, $siteConfig);

        // Build replacement map
        $basePath   = Helpers::getBasePath();
        $siteUrl    = Helpers::publicUrl();
        $fontsUrl   = $theme['fonts']['google_fonts_url'] ?? '';

        // Build Google Fonts <link> tags for preconnect + stylesheet.
        $googleFontsHtml = '';
        if ( !empty( $fontsUrl ) ) {
            $googleFontsHtml = '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n  "
                             . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n  "
                             . '<link href="' . Helpers::escUrl( $fontsUrl ) . '" rel="stylesheet">';
        }

        // Build SEO meta tags (generator, OG, Twitter, JSON-LD, canonical).
        $seoMetaTags = $this->buildSeoMetaTags($page, $siteConfig);

        // Build breadcrumbs (HTML + JSON-LD structured data).
        $breadcrumbHtml = $this->app->getPages()->renderBreadcrumbs(
            $page['slug'] ?? 'index',
            Helpers::getBasePath()
        );

        // Allow plugins to inject content into <head> and before </body>.
        $pluginHeadHtml    = klytos_apply_filters('build.head_html', '');
        $pluginBodyEndHtml = klytos_apply_filters('build.body_end_html', '');

        // Determine page content: v2.0 block assembly or v1.0 raw HTML.
        if (PageManager::hasBlockContent($page) && !empty($page['template'])) {
            $pageContent = $this->renderBlockContent($page);
        } else {
            $pageContent = $page['content_html'] ?? '';
        }

        // Allow plugins to modify the page content before rendering.
        $pageContent = klytos_apply_filters('page.content', $pageContent, $page);

        // Build smart title separator: skip " — Site Name" if page title already contains site name.
        $rawSiteName  = $siteConfig['site_name'] ?? '';
        $rawPageTitle = $page['title'] ?? '';
        $titleSeparator = '';
        if ( !empty( $rawSiteName ) && !str_contains( strtolower( $rawPageTitle ), strtolower( $rawSiteName ) ) ) {
            $titleSeparator = ' — ';
        }

        $replacements = [
            '{{site_name}}'         => Helpers::escHtml($rawSiteName),
            '{{title_separator}}'   => $titleSeparator,
            '{{tagline}}'           => Helpers::escHtml($siteConfig['tagline'] ?? ''),
            '{{default_language}}'  => $siteConfig['default_language'] ?? 'es',
            '{{page_title}}'        => Helpers::escHtml($page['title'] ?? ''),
            '{{page_content}}'      => $pageContent,
            '{{meta_description}}'  => Helpers::escHtml($page['meta_description'] ?? ''),
            '{{page_lang}}'         => $page['lang'] ?? ($siteConfig['default_language'] ?? 'es'),
            '{{hreflang_tags}}'     => $hreflangHtml,
            '{{seo_meta_tags}}'     => $seoMetaTags,
            '{{page_slug}}'         => $page['slug'] ?? '',
            '{{menu_html}}'         => $menuHtml,
            '{{current_year}}'      => date('Y'),
            '{{og_image}}'          => $page['og_image'] ?? ($siteConfig['seo']['default_og_image'] ?? ''),
            '{{custom_css}}'        => !empty($page['custom_css']) ? '<style>' . $page['custom_css'] . '</style>' : '',
            '{{custom_js}}'         => !empty($page['custom_js']) ? '<script>' . $page['custom_js'] . '</script>' : '',
            '{{google_fonts_url}}'  => $fontsUrl,
            '{{google_fonts_html}}' => $googleFontsHtml,
            '{{favicon_url}}'       => $siteConfig['favicon_url'] ?? '',
            '{{logo_url}}'          => $siteConfig['logo_url'] ?? '',
            '{{head_scripts}}'      => $siteConfig['analytics']['custom_head_scripts'] ?? '',
            '{{body_scripts}}'      => $siteConfig['analytics']['custom_body_scripts'] ?? '',
            '{{css_variables}}'     => $this->app->getTheme()->generateCssVariables(),
            '{{sitemap_url}}'       => $siteUrl . 'sitemap.xml',
            '{{base_path}}'         => $basePath,
            '{{site_url}}'          => $siteUrl,
            '{{header_html}}'       => '',
            '{{footer_html}}'       => $this->buildFooterHtml($siteConfig),
            '{{sidebar_html}}'      => '',
            '{{breadcrumbs}}'          => $breadcrumbHtml,
            '{{plugin_head_html}}'     => $pluginHeadHtml,
            '{{plugin_body_end_html}}' => $pluginBodyEndHtml,
            '{{plugin_css_link}}'      => $this->buildPluginCssLink($basePath),
            '{{blocks_css_link}}'      => $this->buildBlocksCssLink($basePath),
            '{{blocks_js_script}}'     => $this->buildBlocksJsTag($basePath),
            '{{hooks_js_script}}'      => $this->buildHooksJsTag($basePath),
        ];

        $html = $templateHtml;
        foreach ($replacements as $key => $value) {
            $html = str_replace($key, $value, $html);
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
        $year = date('Y');
        return "<footer class=\"klytos-footer\"><p>&copy; {$year} {$name}</p></footer>";
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
            $xml .= "    <lastmod>" . ($page['updated_at'] ?? date('c')) . "</lastmod>\n";
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
     * @param  array  $page Page data with 'content' and 'template'.
     * @return string Rendered HTML from assembled blocks.
     */
    private function renderBlockContent(array $page): string
    {
        $templateManager = $this->app->getPageTemplateManager();
        return $templateManager->renderPage($page['template'], $page);
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
}
