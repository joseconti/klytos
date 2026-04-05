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
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
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

    /** @var PostTypeManager|null Post type manager for per-post-type status validation. */
    private ?PostTypeManager $postTypeManager = null;

    /** @var string Collection name used in the storage layer. */
    private const COLLECTION = 'pages';

    /** @var array System page statuses (always available for all post types). */
    public const VALID_STATUSES = ['draft', 'published', 'scheduled', 'trashed'];

    /** @var array Alias for VALID_STATUSES — system statuses are always available. */
    public const SYSTEM_STATUSES = ['draft', 'published', 'scheduled', 'trashed'];

    /** @var int Days to keep trashed pages before auto-purge. */
    public const TRASH_RETENTION_DAYS = 30;

    /**
     * @param StorageInterface $storage Storage backend instance.
     */
    public function __construct( StorageInterface $storage )
    {
        $this->storage = $storage;
    }

    /**
     * Inject PostTypeManager for per-post-type status validation.
     *
     * @param PostTypeManager $postTypeManager
     */
    public function setPostTypeManager( PostTypeManager $postTypeManager ): void
    {
        $this->postTypeManager = $postTypeManager;
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
        klytos_do_action('page.before_save', $page, 'create');

        $this->storage->write(self::COLLECTION, $slug, $page);

        // Hook: notify plugins that a page was created.
        klytos_do_action('page.after_save', $page, 'create');

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

        $page      = $this->storage->read(self::COLLECTION, $slug);
        $oldStatus = $page['status'] ?? 'draft';

        // Merge provided fields (partial update).
        $updatable = [
            'title',
            'content_html',
            'content',
            'meta_description',
            'template',
            'status',
            'custom_css',
            'custom_js',
            'og_image',
            'lang',
            'hreflang_refs',
            'order',
            'post_type',
            'publish_at',
            'is_sticky',
            'password',
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
        klytos_do_action('page.before_save', $page, 'update');

        $this->storage->write(self::COLLECTION, $slug, $page);

        // Hook: notify plugins that a page was updated.
        klytos_do_action( 'page.after_save', $page, 'update' );

        // Hook: notify plugins when status changes (workflow transitions).
        $newStatus = $page['status'] ?? 'draft';
        if ( $oldStatus !== $newStatus ) {
            klytos_do_action( 'page.status_changed', $page, $oldStatus, $newStatus );
        }

        return $page;
    }

    /**
     * Soft-delete a page (move to trash).
     *
     * The page is kept in storage with status 'trashed' and a trashed_at timestamp.
     * Use permanentDelete() to remove it from storage entirely.
     *
     * @param  string $slug
     * @return bool
     */
    public function delete(string $slug): bool
    {
        $slug = Helpers::sanitizeSlug($slug);

        if ( !$this->storage->exists( self::COLLECTION, $slug ) ) {
            return false;
        }

        $page = $this->storage->read( self::COLLECTION, $slug );

        // Already trashed — nothing to do.
        if ( ( $page['status'] ?? '' ) === 'trashed' ) {
            return true;
        }

        // Hook: notify plugins before page is trashed.
        klytos_do_action( 'page.before_trash', $slug, $page );

        // Remember the previous status so restore() can return to it.
        $page['status_before_trash'] = $page['status'] ?? 'draft';
        $page['status']              = 'trashed';
        $page['trashed_at']          = Helpers::now();
        $page['updated_at']          = Helpers::now();

        $this->storage->write( self::COLLECTION, $slug, $page );

        // Hook: notify plugins after page is trashed.
        klytos_do_action( 'page.after_trash', $slug, $page );

        return true;
    }

    /**
     * Restore a page from the trash to its previous status.
     *
     * @param  string $slug
     * @return array  The restored page data.
     * @throws \RuntimeException If the page doesn't exist or isn't trashed.
     */
    public function restore(string $slug): array
    {
        $slug = Helpers::sanitizeSlug( $slug );
        $page = $this->storage->read( self::COLLECTION, $slug );

        if ( ( $page['status'] ?? '' ) !== 'trashed' ) {
            throw new \RuntimeException( "Page is not in trash: {$slug}" );
        }

        klytos_do_action( 'page.before_restore', $slug, $page );

        $page['status']     = $page['status_before_trash'] ?? 'draft';
        $page['updated_at'] = Helpers::now();
        unset( $page['status_before_trash'], $page['trashed_at'] );

        $this->storage->write( self::COLLECTION, $slug, $page );

        klytos_do_action( 'page.after_restore', $slug, $page );

        return $page;
    }

    /**
     * Permanently delete a page from storage.
     *
     * @param  string $slug
     * @return bool
     */
    public function permanentDelete(string $slug): bool
    {
        $slug = Helpers::sanitizeSlug( $slug );

        // Hook: notify plugins before permanent deletion.
        klytos_do_action( 'page.before_delete', $slug );

        $result = $this->storage->delete( self::COLLECTION, $slug );

        if ( $result ) {
            // Hook: notify plugins after permanent deletion.
            klytos_do_action( 'page.after_delete', $slug );
        }

        return $result;
    }

    /**
     * Empty the trash: permanently delete all trashed pages.
     *
     * @return int Number of pages permanently deleted.
     */
    public function emptyTrash(): int
    {
        $trashed = $this->list( 'trashed' );
        $count   = 0;

        foreach ( $trashed as $page ) {
            if ( $this->permanentDelete( $page['slug'] ) ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Auto-purge pages trashed longer than TRASH_RETENTION_DAYS.
     *
     * Called by the scheduled action 'klytos_purge_trash'.
     *
     * @return int Number of pages purged.
     */
    public function purgeExpiredTrash(): int
    {
        $trashed  = $this->list( 'trashed' );
        $cutoff   = date( 'Y-m-d\TH:i:s\Z', time() - ( self::TRASH_RETENTION_DAYS * 86400 ) );
        $count    = 0;

        foreach ( $trashed as $page ) {
            $trashedAt = $page['trashed_at'] ?? '';
            if ( $trashedAt !== '' && $trashedAt < $cutoff ) {
                if ( $this->permanentDelete( $page['slug'] ) ) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Publish all scheduled pages whose publish_at time has arrived.
     *
     * Called by the scheduled action 'klytos_publish_scheduled'.
     *
     * @return array Slugs of pages that were published.
     */
    public function publishScheduled(): array
    {
        $scheduled = $this->list( 'scheduled' );
        $now       = Helpers::now();
        $published = [];

        foreach ( $scheduled as $page ) {
            $publishAt = $page['publish_at'] ?? '';
            if ( $publishAt !== '' && $publishAt <= $now ) {
                $page['status']     = 'published';
                $page['updated_at'] = $now;
                unset( $page['publish_at'] );

                $this->storage->write( self::COLLECTION, $page['slug'], $page );

                klytos_do_action( 'page.after_save', $page, 'publish_scheduled' );
                klytos_do_action( 'page.scheduled_published', $page );

                $published[] = $page['slug'];
            }
        }

        return $published;
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
     * When status is 'all', trashed pages are excluded by default.
     * Use status 'trashed' to list only trashed pages.
     *
     * @param  string $status    Filter: 'all', 'published', 'draft', 'scheduled', 'trashed'.
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
        if ( $status !== 'all' ) {
            $filters['status'] = $status;
        }
        if ( $lang !== '' ) {
            $filters['lang'] = $lang;
        }
        if ( $post_type !== '' ) {
            $filters['post_type'] = $post_type;
        }

        // Delegate filtering and pagination to the storage backend.
        // DatabaseStorage uses SQL indexes; FileStorage filters in memory.
        $pages = $this->storage->list( self::COLLECTION, $filters );

        // When listing 'all', exclude trashed pages (they have their own view).
        if ( $status === 'all' ) {
            $pages = array_values( array_filter( $pages, function ( array $page ): bool {
                return ( $page['status'] ?? '' ) !== 'trashed';
            } ) );
        }

        // Sort: sticky first, then by order, then by title.
        usort( $pages, function ( array $a, array $b ): int {
            $stickyA = ( $a['is_sticky'] ?? false ) ? 0 : 1;
            $stickyB = ( $b['is_sticky'] ?? false ) ? 0 : 1;
            if ( $stickyA !== $stickyB ) {
                return $stickyA - $stickyB;
            }
            $orderA = $a['order'] ?? 0;
            $orderB = $b['order'] ?? 0;
            if ( $orderA !== $orderB ) {
                return $orderA - $orderB;
            }
            return strcmp( $a['title'] ?? '', $b['title'] ?? '' );
        } );

        // Apply pagination after sorting (storage may not sort the same way).
        return array_slice( $pages, $offset, $limit > 0 ? $limit : null );
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
            $title = Helpers::escHtml( $crumb['title'] );
            $url   = Helpers::escUrl( $crumb['url'] );

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

        $status     = $data['status'] ?? 'published';
        $postTypeId = $data['post_type'] ?? 'page';

        if ( $this->postTypeManager !== null ) {
            if ( !$this->postTypeManager->isValidStatusForPostType( $postTypeId, $status ) ) {
                $status = 'draft';
            }
        } else {
            if ( !in_array( $status, self::VALID_STATUSES, true ) ) {
                $status = 'draft';
            }
        }

        $page = [
            'slug'             => $slug,
            'parent_slug'      => $data['parent_slug'] ?? $parentSlug,
            'title'            => $data['title'] ?? '',
            'content_html'     => Helpers::sanitizeHtml( $data['content_html'] ?? '' ),
            'content'          => $data['content'] ?? null,
            'meta_description' => Helpers::smartTruncate( $data['meta_description'] ?? '', 160 ),
            'template'         => $data['template'] ?? 'default',
            'status'           => $status,
            'custom_css'       => $data['custom_css'] ?? '',
            'custom_js'        => $data['custom_js'] ?? '',
            'og_image'         => $data['og_image'] ?? '',
            'lang'             => $data['lang'] ?? '',
            'hreflang_refs'    => $data['hreflang_refs'] ?? [],
            'order'            => (int) ($data['order'] ?? 0),
            'post_type'        => $data['post_type'] ?? 'page',
            'is_sticky'        => (bool) ($data['is_sticky'] ?? false),
            'password'         => $data['password'] ?? '',
            'llm_optional'     => (bool) ($data['llm_optional'] ?? false),
            'llm_exclude'      => (bool) ($data['llm_exclude'] ?? false),
        ];

        // Scheduled pages require a publish_at datetime.
        if ( $status === 'scheduled' ) {
            $publishAt = $data['publish_at'] ?? '';
            if ( empty( $publishAt ) ) {
                throw new \RuntimeException( 'publish_at is required when status is scheduled.' );
            }
            $page['publish_at'] = $publishAt;
        }

        return $page;
    }

    // ─── Post Locking ─────────────────────────────────────────────

    /** Lock expiry time in seconds (5 minutes). */
    private const LOCK_TTL = 300;

    /**
     * Attempt to acquire an editing lock on a page.
     *
     * @param  string $slug   Page slug.
     * @param  string $userId User ID requesting the lock.
     * @return array  ['locked' => bool, 'lock_owner' => ?string, 'lock_owner_name' => ?string, 'lock_token' => ?string]
     */
    public function acquireLock( string $slug, string $userId ): array
    {
        $existing = klytos_get_meta( 'page', $slug, '_editing_lock' );

        // Check if there's an existing unexpired lock by another user.
        if ( $existing && is_array( $existing ) ) {
            $lockedAt = strtotime( $existing['locked_at'] ?? '' );
            $isExpired = $lockedAt === false || ( time() - $lockedAt ) > self::LOCK_TTL;

            if ( !$isExpired && ( $existing['user_id'] ?? '' ) !== $userId ) {
                return [
                    'locked'          => false,
                    'lock_owner'      => $existing['user_id'] ?? '',
                    'lock_owner_name' => $existing['user_name'] ?? '',
                ];
            }
        }

        // Acquire the lock.
        $token = Helpers::randomHex( 8 );
        $userName = '';
        try {
            $userManager = new UserManager( $this->storage );
            $user = $userManager->getById( $userId );
            $userName = $user['display_name'] ?? $user['username'] ?? '';
        } catch ( \Throwable $e ) {
            // User not found — proceed with empty name.
        }

        $lock = [
            'user_id'   => $userId,
            'user_name' => $userName,
            'locked_at' => Helpers::now(),
            'lock_token' => $token,
        ];
        klytos_set_meta( 'page', $slug, '_editing_lock', $lock );
        klytos_do_action( 'page.lock_acquired', $slug, $userId );

        return [
            'locked'     => true,
            'lock_token' => $token,
        ];
    }

    /**
     * Release an editing lock.
     *
     * @param  string $slug   Page slug.
     * @param  string $userId User ID releasing the lock.
     * @return bool   True if released.
     */
    public function releaseLock( string $slug, string $userId ): bool
    {
        $existing = klytos_get_meta( 'page', $slug, '_editing_lock' );
        if ( !$existing || ( $existing['user_id'] ?? '' ) !== $userId ) {
            return false;
        }
        klytos_set_meta( 'page', $slug, '_editing_lock', null );
        klytos_do_action( 'page.lock_released', $slug, $userId );
        return true;
    }

    /**
     * Renew an existing editing lock (heartbeat).
     *
     * @param  string $slug   Page slug.
     * @param  string $userId User ID renewing the lock.
     * @return bool   True if renewed.
     */
    public function renewLock( string $slug, string $userId ): bool
    {
        $existing = klytos_get_meta( 'page', $slug, '_editing_lock' );
        if ( !$existing || ( $existing['user_id'] ?? '' ) !== $userId ) {
            return false;
        }
        $existing['locked_at'] = Helpers::now();
        klytos_set_meta( 'page', $slug, '_editing_lock', $existing );
        return true;
    }

    /**
     * Check the current lock status of a page.
     *
     * @param  string     $slug Page slug.
     * @return array|null Lock info or null if unlocked/expired.
     */
    public function checkLock( string $slug ): ?array
    {
        $existing = klytos_get_meta( 'page', $slug, '_editing_lock' );
        if ( !$existing || !is_array( $existing ) ) {
            return null;
        }

        $lockedAt = strtotime( $existing['locked_at'] ?? '' );
        if ( $lockedAt === false || ( time() - $lockedAt ) > self::LOCK_TTL ) {
            // Expired — clean up.
            klytos_set_meta( 'page', $slug, '_editing_lock', null );
            klytos_do_action( 'page.lock_expired', $slug, $existing['user_id'] ?? '' );
            return null;
        }

        return $existing;
    }

    /**
     * Check if a page is password protected.
     *
     * @param  array $page Page data.
     * @return bool  True if the page has a non-empty password.
     */
    public function isPasswordProtected( array $page ): bool
    {
        return !empty( $page['password'] );
    }

    /**
     * Check if a page uses v2.0 block-based content.
     *
     * @param  array $page Page data.
     * @return bool  True if the page has structured block content.
     */
    public static function hasBlockContent(array $page): bool
    {
        return !empty($page['content']) && is_array($page['content']);
    }
}
