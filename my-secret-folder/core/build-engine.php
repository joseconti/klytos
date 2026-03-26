<?php
/**
 * Klytos — Build Engine
 * Generates the static HTML site from data, templates, and theme.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
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
        Hooks::doAction('build.before');

        // 1. Generate CSS
        $this->generateCss();

        // 2. Get global data
        $siteConfig = $this->app->getSiteConfig()->get();
        $menuHtml   = $this->app->getMenu()->toHtml(Helpers::getBasePath());
        $theme      = $this->app->getTheme()->get();

        // 3. Build each published page
        $pages = $this->app->getPages()->list('published');

        foreach ($pages as $page) {
            try {
                $this->writePageHtml($page, $siteConfig, $menuHtml, $theme);
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
        Hooks::doAction('build.after', $pagesBuilt, $errors);

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
    private function generateCss(): void
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
        $templateName = $page['template'] ?? 'default';
        $templateHtml = $this->loadTemplate($templateName);

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
                             . '<link href="' . htmlspecialchars( $fontsUrl ) . '" rel="stylesheet">';
        }

        // Build SEO meta tags (generator, OG, Twitter, JSON-LD, canonical).
        $seoMetaTags = $this->buildSeoMetaTags($page, $siteConfig);

        // Build breadcrumbs (HTML + JSON-LD structured data).
        $breadcrumbHtml = $this->app->getPages()->renderBreadcrumbs(
            $page['slug'] ?? 'index',
            Helpers::getBasePath()
        );

        // Allow plugins to inject content into <head> and before </body>.
        $pluginHeadHtml    = Hooks::applyFilters('build.head_html', '');
        $pluginBodyEndHtml = Hooks::applyFilters('build.body_end_html', '');

        // Allow plugins to modify the page content before rendering.
        $pageContent = Hooks::applyFilters('page.content', $page['content_html'] ?? '', $page);

        // Build smart title separator: skip " — Site Name" if page title already contains site name.
        $rawSiteName  = $siteConfig['site_name'] ?? '';
        $rawPageTitle = $page['title'] ?? '';
        $titleSeparator = '';
        if ( !empty( $rawSiteName ) && !str_contains( strtolower( $rawPageTitle ), strtolower( $rawSiteName ) ) ) {
            $titleSeparator = ' — ';
        }

        $replacements = [
            '{{site_name}}'         => htmlspecialchars($rawSiteName, ENT_QUOTES, 'UTF-8'),
            '{{title_separator}}'   => $titleSeparator,
            '{{tagline}}'           => htmlspecialchars($siteConfig['tagline'] ?? '', ENT_QUOTES, 'UTF-8'),
            '{{default_language}}'  => $siteConfig['default_language'] ?? 'es',
            '{{page_title}}'        => htmlspecialchars($page['title'] ?? '', ENT_QUOTES, 'UTF-8'),
            '{{page_content}}'      => $pageContent,
            '{{meta_description}}'  => htmlspecialchars($page['meta_description'] ?? '', ENT_QUOTES, 'UTF-8'),
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
        // Check custom templates first
        try {
            $data = $this->app->getStorage()->read('templates.json.enc');
            if (isset($data['templates'][$name])) {
                return $data['templates'][$name]['html'];
            }
        } catch (\RuntimeException $e) {
            // No custom templates
        }

        // Check built-in templates
        $file = $this->templatesPath . '/' . $name . '.html';
        if (file_exists($file)) {
            return file_get_contents($file);
        }

        // Fallback to default
        $defaultFile = $this->templatesPath . '/default.html';
        if (file_exists($defaultFile)) {
            return file_get_contents($defaultFile);
        }

        // Ultimate fallback
        return $this->getMinimalTemplate();
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
            $tags[] = '<link rel="alternate" hreflang="' . htmlspecialchars($lang) . '" href="' . htmlspecialchars($url) . '">';
        }

        // x-default (use the default language version).
        $defaultLang = $siteConfig['default_language'] ?? 'es';
        if (isset($refs[$defaultLang])) {
            $defaultUrl = rtrim($siteUrl, '/') . '/' . ltrim($refs[$defaultLang], '/') . '/';
            $tags[]     = '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($defaultUrl) . '">';
        }

        return implode("\n  ", $tags);
    }

    /**
     * Build a basic footer HTML.
     */
    private function buildFooterHtml(array $siteConfig): string
    {
        $name = htmlspecialchars($siteConfig['site_name'] ?? 'Klytos Site', ENT_QUOTES, 'UTF-8');
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

        $content  = "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Sitemap: {$siteUrl}sitemap.xml\n";

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
            $xml .= "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
            $xml .= "    <lastmod>" . ($page['updated_at'] ?? date('c')) . "</lastmod>\n";
            $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
            $xml .= "    <priority>{$priority}</priority>\n";

            // Hreflang alternates (clean URLs).
            $refs = $page['hreflang_refs'] ?? [];
            if (!empty($refs)) {
                foreach ($refs as $lang => $refSlug) {
                    $refUrl = $siteUrl . '/' . ltrim($refSlug, '/') . '/';
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($lang) . '" href="' . htmlspecialchars($refUrl) . '"/>' . "\n";
                }

                // x-default
                $defaultLang = $siteConfig['default_language'] ?? 'es';
                if (isset($refs[$defaultLang])) {
                    $defaultUrl = $siteUrl . '/' . ltrim($refs[$defaultLang], '/') . '/';
                    $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($defaultUrl) . '"/>' . "\n";
                }
            }

            $xml .= "  </url>\n";
        }

        // Allow plugins to add custom URLs to the sitemap.
        $pluginUrls = Hooks::applyFilters('build.sitemap_urls', []);
        foreach ($pluginUrls as $pluginUrl) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . htmlspecialchars($pluginUrl['loc'] ?? '') . "</loc>\n";
            if (!empty($pluginUrl['lastmod'])) {
                $xml .= "    <lastmod>" . htmlspecialchars($pluginUrl['lastmod']) . "</lastmod>\n";
            }
            $xml .= "    <changefreq>" . ($pluginUrl['changefreq'] ?? 'monthly') . "</changefreq>\n";
            $xml .= "    <priority>" . ($pluginUrl['priority'] ?? '0.5') . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        file_put_contents($this->outputPath . '/sitemap.xml', $xml, LOCK_EX);
    }

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
                str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>', '</h1>', '</h2>', '</h3>', '</h4>'],
                            ["\n", "\n", "\n", "\n\n", "\n", "\n", "\n\n", "\n\n", "\n\n", "\n\n"],
                            $content)
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
        $noIndex  = ! empty( $page['noindex'] );

        $tags = [];

        // Generator meta tag — identifies the CMS.
        $tags[] = "<meta name=\"generator\" content=\"Klytos {$version}\">";

        // Robots.
        if ( $noIndex ) {
            $tags[] = "<meta name=\"robots\" content=\"noindex, nofollow\">";
        }

        // Canonical URL.
        $tags[] = "<link rel=\"canonical\" href=\"" . htmlspecialchars( $canonical ) . "\">";

        // Open Graph tags (Facebook, LinkedIn, etc.).
        $tags[] = "<meta property=\"og:type\" content=\"website\">";
        $tags[] = "<meta property=\"og:title\" content=\"" . htmlspecialchars( $ogTitle ) . "\">";
        $tags[] = "<meta property=\"og:description\" content=\"" . htmlspecialchars( $ogDesc ) . "\">";
        $tags[] = "<meta property=\"og:url\" content=\"" . htmlspecialchars( $pageUrl ) . "\">";
        $tags[] = "<meta property=\"og:site_name\" content=\"" . htmlspecialchars( $siteName ) . "\">";
        $tags[] = "<meta property=\"og:locale\" content=\"" . htmlspecialchars( $lang ) . "\">";

        if ( ! empty( $ogImage ) ) {
            $tags[] = "<meta property=\"og:image\" content=\"" . htmlspecialchars( $ogImage ) . "\">";
            $tags[] = "<meta property=\"og:image:width\" content=\"1200\">";
            $tags[] = "<meta property=\"og:image:height\" content=\"630\">";
        }

        // Twitter Card tags.
        $tags[] = "<meta name=\"twitter:card\" content=\"summary_large_image\">";
        $tags[] = "<meta name=\"twitter:title\" content=\"" . htmlspecialchars( $twTitle ) . "\">";
        $tags[] = "<meta name=\"twitter:description\" content=\"" . htmlspecialchars( $twDesc ) . "\">";

        if (!empty($ogImage)) {
            $tags[] = "<meta name=\"twitter:image\" content=\"" . htmlspecialchars($ogImage) . "\">";
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
  <script src="{{base_path}}assets/js/klytos-analytics.js" defer></script>
  {{body_scripts}}
  {{plugin_body_end_html}}
</body>
</html>
HTML;
    }
}
