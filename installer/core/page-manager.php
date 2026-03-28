<?php
/**
 * Klytos — Page Manager
 * CRUD operations for site pages.
 *
 * v2.0: Uses StorageInterface (works with both FileStorage and DatabaseStorage).
 *
 * @package Klytos
 * @since   1.0.0
 * @updated 2.0.0
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

class PageManager
{
    /** @var StorageInterface Storage backend (FileStorage or DatabaseStorage). */
    private StorageInterface $storage;

    /** @var string Collection name used in the storage layer. */
    private const COLLECTION = 'pages';

    /**
     * @param StorageInterface $storage Storage backend instance.
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Create a new page.
     *
     * @param  array $data Page data (slug, title, content_html, etc.)
     * @return array The created page data.
     * @throws \RuntimeException If slug already exists or is invalid.
     */
    public function create(array $data): array
    {
        $slug = Helpers::sanitizeSlug($data['slug'] ?? '');
        if (empty($slug)) {
            throw new \RuntimeException('Page slug is required.');
        }

        if ($this->storage->exists(self::COLLECTION, $slug)) {
            throw new \RuntimeException("Page already exists: {$slug}");
        }

        $page = $this->buildPageData($slug, $data);
        $page['created_at'] = Helpers::now();
        $page['updated_at'] = Helpers::now();

        // Hook: allow plugins to modify page data before saving.
        Hooks::doAction('page.before_save', $page, 'create');

        $this->storage->write(self::COLLECTION, $slug, $page);

        // Hook: notify plugins that a page was created.
        Hooks::doAction('page.after_save', $page, 'create');

        return $page;
    }

    /**
     * Update an existing page.
     *
     * @param  string $slug  Page slug to update.
     * @param  array  $data  Fields to update (partial update supported).
     * @return array  The updated page data.
     */
    public function update(string $slug, array $data): array
    {
        $slug = Helpers::sanitizeSlug($slug);

        if (!$this->storage->exists(self::COLLECTION, $slug)) {
            throw new \RuntimeException("Page not found: {$slug}");
        }

        $page = $this->storage->read(self::COLLECTION, $slug);

        // Merge provided fields (partial update).
        $updatable = [
            'title', 'content_html', 'meta_description', 'template',
            'status', 'custom_css', 'custom_js', 'og_image',
            'lang',
            'hreflang_refs',
            'order',
            'post_type',
        ];

        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $page[$field] = $data[$field];
            }
        }

        // Sanitize HTML content to prevent XSS.
        if (isset($data['content_html'])) {
            $page['content_html'] = Helpers::sanitizeHtml($data['content_html']);
        }

        $page['updated_at'] = Helpers::now();

        // Hook: allow plugins to modify page data before saving.
        Hooks::doAction('page.before_save', $page, 'update');

        $this->storage->write(self::COLLECTION, $slug, $page);

        // Hook: notify plugins that a page was updated.
        Hooks::doAction('page.after_save', $page, 'update');

        return $page;
    }

    /**
     * Delete a page.
     *
     * @param  string $slug
     * @return bool
     */
    public function delete(string $slug): bool
    {
        $slug = Helpers::sanitizeSlug($slug);

        // Hook: notify plugins before page deletion.
        Hooks::doAction('page.before_delete', $slug);

        $result = $this->storage->delete(self::COLLECTION, $slug);

        if ($result) {
            // Hook: notify plugins after page deletion.
            Hooks::doAction('page.after_delete', $slug);
        }

        return $result;
    }

    /**
     * Get a single page by slug.
     *
     * @param  string $slug Page slug identifier.
     * @return array  Decrypted page data.
     * @throws \RuntimeException If the page does not exist.
     */
    public function get(string $slug): array
    {
        $slug = Helpers::sanitizeSlug($slug);

        return $this->storage->read(self::COLLECTION, $slug);
    }

    /**
     * Check if a page exists.
     *
     * @param  string $slug Page slug identifier.
     * @return bool
     */
    public function exists(string $slug): bool
    {
        $slug = Helpers::sanitizeSlug($slug);

        return $this->storage->exists(self::COLLECTION, $slug);
    }

    /**
     * List all pages with optional filters.
     *
     * @param  string $status    Filter: 'all', 'published', 'draft'.
     * @param  string $lang      Filter by language code (empty = all).
     * @param  int    $limit
     * @param  int    $offset
     * @param  string $post_type Filter by post type (empty = all).
     * @return array
     */
    public function list(string $status = 'all', string $lang = '', int $limit = 50, int $offset = 0, string $post_type = ''): array
    {
        // Build filters for the storage layer.
        $filters = [];
        if ($status !== 'all') {
            $filters['status'] = $status;
        }
        if ($lang !== '') {
            $filters['lang'] = $lang;
        }
        if ($post_type !== '') {
            $filters['post_type'] = $post_type;
        }

        // Delegate filtering and pagination to the storage backend.
        // DatabaseStorage uses SQL indexes; FileStorage filters in memory.
        $pages = $this->storage->list(self::COLLECTION, $filters);

        // Sort by order, then by title.
        usort($pages, function (array $a, array $b): int {
            $orderA = $a['order'] ?? 0;
            $orderB = $b['order'] ?? 0;
            if ($orderA !== $orderB) {
                return $orderA - $orderB;
            }
            return strcmp($a['title'] ?? '', $b['title'] ?? '');
        });

        // Apply pagination after sorting (storage may not sort the same way).
        return array_slice($pages, $offset, $limit > 0 ? $limit : null);
    }

    /**
     * Count total pages with optional status filter.
     *
     * @param  string $status    Filter: 'all', 'published', 'draft'.
     * @param  string $post_type Filter by post type (empty = all).
     * @return int
     */
    public function count(string $status = 'all', string $post_type = ''): int
    {
        $filters = [];
        if ($status !== 'all') {
            $filters['status'] = $status;
        }
        if ($post_type !== '') {
            $filters['post_type'] = $post_type;
        }

        return $this->storage->count(self::COLLECTION, $filters);
    }

    /**
     * Get all child pages of a given parent slug.
     *
     * For example, if the parent slug is 'servicios', this returns all pages
     * whose parent_slug is 'servicios' (e.g. 'servicios/marketing', 'servicios/diseno').
     *
     * @param  string $parentSlug Parent page slug.
     * @param  string $status     Filter by status ('all', 'published', 'draft').
     * @return array  Array of child pages.
     */
    public function getChildren(string $parentSlug, string $status = 'all'): array
    {
        $allPages = $this->list($status);

        return array_values(array_filter($allPages, function (array $page) use ($parentSlug): bool {
            return ($page['parent_slug'] ?? '') === $parentSlug;
        }));
    }

    /**
     * Get the breadcrumb trail for a page (from root to current page).
     *
     * For slug 'servicios/marketing/seo', returns:
     * [
     *   ['title' => 'Home', 'slug' => 'index', 'url' => '/'],
     *   ['title' => 'Servicios', 'slug' => 'servicios', 'url' => '/servicios/'],
     *   ['title' => 'Marketing', 'slug' => 'servicios/marketing', 'url' => '/servicios/marketing/'],
     *   ['title' => 'SEO', 'slug' => 'servicios/marketing/seo', 'url' => '/servicios/marketing/seo/'],
     * ]
     *
     * @param  string $slug    Page slug.
     * @param  string $baseUrl Base URL for link generation.
     * @return array  Breadcrumb trail (ordered from root to leaf).
     */
    public function getBreadcrumbs(string $slug, string $baseUrl = '/'): array
    {
        $breadcrumbs = [];
        $parts       = explode('/', trim($slug, '/'));

        // Always start with Home.
        $breadcrumbs[] = [
            'title' => 'Home',
            'slug'  => 'index',
            'url'   => $baseUrl,
        ];

        // Build the trail from each segment of the slug.
        $currentPath = '';
        foreach ($parts as $segment) {
            $currentPath .= ($currentPath !== '' ? '/' : '') . $segment;

            // Try to find the page to get its title.
            try {
                $page = $this->get($currentPath);
                $title = $page['title'] ?? ucfirst($segment);
            } catch (\RuntimeException $e) {
                // Page doesn't exist for this segment — use the segment name.
                $title = ucfirst(str_replace('-', ' ', $segment));
            }

            $breadcrumbs[] = [
                'title' => $title,
                'slug'  => $currentPath,
                'url'   => rtrim($baseUrl, '/') . '/' . $currentPath . '/',
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Generate breadcrumb HTML (with Schema.org structured data).
     *
     * @param  string $slug    Page slug.
     * @param  string $baseUrl Base URL.
     * @return string HTML with BreadcrumbList JSON-LD and visible breadcrumb nav.
     */
    public function renderBreadcrumbs(string $slug, string $baseUrl = '/'): string
    {
        // Don't show breadcrumbs on the homepage.
        if ($slug === 'index' || $slug === '') {
            return '';
        }

        $crumbs = $this->getBreadcrumbs($slug, $baseUrl);

        if (count($crumbs) <= 1) {
            return ''; // Only homepage — no breadcrumb needed.
        }

        // Build visible HTML breadcrumb navigation.
        $html = '<nav class="klytos-breadcrumbs" aria-label="Breadcrumb">' . "\n";
        $html .= '  <ol class="breadcrumb-list">' . "\n";

        $last = count($crumbs) - 1;
        foreach ($crumbs as $i => $crumb) {
            $title = htmlspecialchars($crumb['title'], ENT_QUOTES, 'UTF-8');
            $url   = htmlspecialchars($crumb['url'], ENT_QUOTES, 'UTF-8');

            if ($i === $last) {
                // Current page — no link, aria-current.
                $html .= "    <li class=\"breadcrumb-item active\" aria-current=\"page\">{$title}</li>\n";
            } else {
                $html .= "    <li class=\"breadcrumb-item\"><a href=\"{$url}\">{$title}</a></li>\n";
            }
        }

        $html .= "  </ol>\n";
        $html .= "</nav>\n";

        // Build JSON-LD BreadcrumbList for SEO.
        $jsonLdItems = [];
        foreach ($crumbs as $i => $crumb) {
            $jsonLdItems[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $crumb['title'],
                'item'     => rtrim($baseUrl, '/') . $crumb['url'],
            ];
        }

        $jsonLd = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $jsonLdItems,
        ];

        $html .= '<script type="application/ld+json">'
               . json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
               . "</script>\n";

        return $html;
    }

    /**
     * Build the full page data array with defaults.
     *
     * Supports hierarchical pages via parent_slug. When a slug contains '/',
     * the parent_slug is automatically derived.
     * Example: slug 'servicios/marketing' → parent_slug = 'servicios'.
     */
    private function buildPageData(string $slug, array $data): array
    {
        // Auto-detect parent_slug from the slug hierarchy.
        // 'servicios/marketing' → parent = 'servicios'
        // 'servicios/marketing/seo' → parent = 'servicios/marketing'
        // 'about' → parent = '' (top-level)
        $parentSlug = '';
        if (str_contains($slug, '/')) {
            $parentSlug = substr($slug, 0, strrpos($slug, '/'));
        }

        return [
            'slug'             => $slug,
            'parent_slug'      => $data['parent_slug'] ?? $parentSlug,
            'title'            => $data['title'] ?? '',
            'content_html'     => Helpers::sanitizeHtml($data['content_html'] ?? ''),
            'meta_description' => Helpers::smartTruncate( $data['meta_description'] ?? '', 160 ),
            'template'         => $data['template'] ?? 'default',
            'status'           => $data['status'] ?? 'published',
            'custom_css'       => $data['custom_css'] ?? '',
            'custom_js'        => $data['custom_js'] ?? '',
            'og_image'         => $data['og_image'] ?? '',
            'lang'             => $data['lang'] ?? '',
            'hreflang_refs'    => $data['hreflang_refs'] ?? [],
            'order'            => (int) ($data['order'] ?? 0),
            'post_type'        => $data['post_type'] ?? 'page',
        ];
    }
}
