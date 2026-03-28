<?php
/**
 * Klytos — Post Type Manager
 * CRUD operations for custom post types and their taxonomies.
 *
 * Post types are stored in the 'post-types' collection. Each post type defines
 * its name, slug, language slugs, and associated taxonomies.
 *
 * The built-in 'page' post type is always present and cannot be deleted.
 * Plugins can register post types via hooks or MCP tools.
 *
 * @package Klytos
 * @since   0.6.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 Jose Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class PostTypeManager
{
    private StorageInterface $storage;
    private const COLLECTION = 'post-types';

    /** @var string The built-in page post type ID (cannot be deleted). */
    private const BUILTIN_PAGE = 'page';

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
        $this->ensureBuiltinPage();
    }

    /**
     * Ensure the built-in 'page' post type exists.
     */
    private function ensureBuiltinPage(): void
    {
        if ($this->storage->exists(self::COLLECTION, self::BUILTIN_PAGE)) {
            return;
        }

        $page = [
            'id'          => self::BUILTIN_PAGE,
            'name'        => 'Pages',
            'slug'        => '/',
            'slug_i18n'   => [],
            'taxonomies'  => [],
            'builtin'     => true,
            'created_at'  => Helpers::now(),
            'updated_at'  => Helpers::now(),
        ];

        $this->storage->write(self::COLLECTION, self::BUILTIN_PAGE, $page);
    }

    // ─── Post Type CRUD ─────────────────────────────────────────

    /**
     * Create a new custom post type.
     *
     * @param  array $data Post type data (id, name, slug, slug_i18n, taxonomies).
     * @return array The created post type.
     * @throws \RuntimeException If ID already exists or is invalid.
     */
    public function create(array $data): array
    {
        $id = Helpers::sanitizeSlug($data['id'] ?? '');
        if (empty($id)) {
            throw new \InvalidArgumentException('Post type ID is required.');
        }

        if ($this->storage->exists(self::COLLECTION, $id)) {
            throw new \InvalidArgumentException("Post type already exists: {$id}");
        }

        // Reserved IDs.
        $reserved = ['page', 'post', 'attachment', 'revision', 'nav_menu_item'];
        if (in_array($id, $reserved, true) && $id !== self::BUILTIN_PAGE) {
            throw new \InvalidArgumentException("Post type ID '{$id}' is reserved.");
        }

        $postType = $this->buildPostTypeData($id, $data);
        $postType['builtin']    = false;
        $postType['created_at'] = Helpers::now();
        $postType['updated_at'] = Helpers::now();

        Hooks::doAction('post_type.before_save', $postType, 'create');

        $this->storage->write(self::COLLECTION, $id, $postType);

        Hooks::doAction('post_type.after_save', $postType, 'create');

        return $postType;
    }

    /**
     * Update an existing post type.
     *
     * @param  string $id   Post type ID.
     * @param  array  $data Fields to update (partial update).
     * @return array  The updated post type.
     */
    public function update(string $id, array $data): array
    {
        $id = Helpers::sanitizeSlug($id);

        if (!$this->storage->exists(self::COLLECTION, $id)) {
            throw new \InvalidArgumentException("Post type not found: {$id}");
        }

        $postType = $this->storage->read(self::COLLECTION, $id);

        // Updatable fields.
        $updatable = ['name', 'slug', 'slug_i18n', 'taxonomies'];
        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $postType[$field] = $data[$field];
            }
        }

        $postType['updated_at'] = Helpers::now();

        Hooks::doAction('post_type.before_save', $postType, 'update');

        $this->storage->write(self::COLLECTION, $id, $postType);

        Hooks::doAction('post_type.after_save', $postType, 'update');

        return $postType;
    }

    /**
     * Delete a custom post type.
     *
     * @param  string $id Post type ID.
     * @return bool
     * @throws \InvalidArgumentException If trying to delete a built-in post type.
     */
    public function delete(string $id): bool
    {
        $id = Helpers::sanitizeSlug($id);

        if ($id === self::BUILTIN_PAGE) {
            throw new \InvalidArgumentException('Cannot delete the built-in page post type.');
        }

        if (!$this->storage->exists(self::COLLECTION, $id)) {
            throw new \InvalidArgumentException("Post type not found: {$id}");
        }

        Hooks::doAction('post_type.before_delete', $id);

        $result = $this->storage->delete(self::COLLECTION, $id);

        if ($result) {
            // Also delete all taxonomy term data for this post type.
            $this->deleteAllTerms($id);
            Hooks::doAction('post_type.after_delete', $id);
        }

        return $result;
    }

    /**
     * Get a single post type by ID.
     *
     * @param  string $id Post type ID.
     * @return array
     */
    public function get(string $id): array
    {
        $id = Helpers::sanitizeSlug($id);
        return $this->storage->read(self::COLLECTION, $id);
    }

    /**
     * Check if a post type exists.
     */
    public function exists(string $id): bool
    {
        return $this->storage->exists(self::COLLECTION, Helpers::sanitizeSlug($id));
    }

    /**
     * List all registered post types.
     *
     * @return array
     */
    public function list(): array
    {
        $postTypes = $this->storage->list(self::COLLECTION);

        // Sort: built-in first, then alphabetically.
        usort($postTypes, function (array $a, array $b): int {
            if (($a['builtin'] ?? false) !== ($b['builtin'] ?? false)) {
                return ($a['builtin'] ?? false) ? -1 : 1;
            }
            return strcmp($a['name'] ?? '', $b['name'] ?? '');
        });

        return $postTypes;
    }

    // ─── Taxonomy Management ────────────────────────────────────

    /**
     * Add a taxonomy to a post type.
     *
     * @param  string $postTypeId  Post type ID.
     * @param  array  $taxonomy    Taxonomy data: id, name, slug, slug_i18n, hierarchical.
     * @return array  Updated post type.
     */
    public function addTaxonomy(string $postTypeId, array $taxonomy): array
    {
        $postType = $this->get($postTypeId);

        $taxId = Helpers::sanitizeSlug($taxonomy['id'] ?? '');
        if (empty($taxId)) {
            throw new \InvalidArgumentException('Taxonomy ID is required.');
        }

        // Check for duplicate taxonomy ID.
        foreach ($postType['taxonomies'] as $existing) {
            if (($existing['id'] ?? '') === $taxId) {
                throw new \InvalidArgumentException("Taxonomy '{$taxId}' already exists in post type '{$postTypeId}'.");
            }
        }

        $taxData = [
            'id'           => $taxId,
            'name'         => $taxonomy['name'] ?? ucfirst($taxId),
            'slug'         => $taxonomy['slug'] ?? $taxId,
            'slug_i18n'    => $taxonomy['slug_i18n'] ?? [],
            'hierarchical' => $taxonomy['hierarchical'] ?? false,
        ];

        $postType['taxonomies'][] = $taxData;
        $postType['updated_at']   = Helpers::now();

        $this->storage->write(self::COLLECTION, $postTypeId, $postType);

        Hooks::doAction('taxonomy.after_save', $postTypeId, $taxData, 'create');

        return $postType;
    }

    /**
     * Update a taxonomy within a post type.
     *
     * @param  string $postTypeId  Post type ID.
     * @param  string $taxonomyId  Taxonomy ID.
     * @param  array  $data        Fields to update.
     * @return array  Updated post type.
     */
    public function updateTaxonomy(string $postTypeId, string $taxonomyId, array $data): array
    {
        $postType = $this->get($postTypeId);
        $found = false;

        foreach ($postType['taxonomies'] as &$tax) {
            if (($tax['id'] ?? '') === $taxonomyId) {
                $updatable = ['name', 'slug', 'slug_i18n', 'hierarchical'];
                foreach ($updatable as $field) {
                    if (array_key_exists($field, $data)) {
                        $tax[$field] = $data[$field];
                    }
                }
                $found = true;
                break;
            }
        }
        unset($tax);

        if (!$found) {
            throw new \InvalidArgumentException("Taxonomy '{$taxonomyId}' not found in post type '{$postTypeId}'.");
        }

        $postType['updated_at'] = Helpers::now();
        $this->storage->write(self::COLLECTION, $postTypeId, $postType);

        Hooks::doAction('taxonomy.after_save', $postTypeId, $taxonomyId, 'update');

        return $postType;
    }

    /**
     * Remove a taxonomy from a post type.
     *
     * @param  string $postTypeId  Post type ID.
     * @param  string $taxonomyId  Taxonomy ID to remove.
     * @return array  Updated post type.
     */
    public function removeTaxonomy(string $postTypeId, string $taxonomyId): array
    {
        $postType = $this->get($postTypeId);

        $postType['taxonomies'] = array_values(array_filter(
            $postType['taxonomies'],
            fn(array $tax) => ($tax['id'] ?? '') !== $taxonomyId
        ));

        $postType['updated_at'] = Helpers::now();
        $this->storage->write(self::COLLECTION, $postTypeId, $postType);

        // Delete all terms for this taxonomy.
        $this->deleteTermsForTaxonomy($postTypeId, $taxonomyId);

        Hooks::doAction('taxonomy.after_delete', $postTypeId, $taxonomyId);

        return $postType;
    }

    // ─── Taxonomy Terms ─────────────────────────────────────────

    /**
     * Get the storage collection key for taxonomy terms.
     */
    private function termsCollection(string $postTypeId, string $taxonomyId): string
    {
        return "terms-{$postTypeId}-{$taxonomyId}";
    }

    /**
     * Add a term to a taxonomy.
     *
     * @param  string $postTypeId  Post type ID.
     * @param  string $taxonomyId  Taxonomy ID.
     * @param  array  $term        Term data: name, slug, slug_i18n, parent, description.
     * @return array  The created term.
     */
    public function addTerm(string $postTypeId, string $taxonomyId, array $term): array
    {
        // Verify post type and taxonomy exist.
        $this->verifyTaxonomyExists($postTypeId, $taxonomyId);

        $collection = $this->termsCollection($postTypeId, $taxonomyId);

        $slug = Helpers::sanitizeSlug($term['slug'] ?? $term['name'] ?? '');
        if (empty($slug)) {
            throw new \InvalidArgumentException('Term slug or name is required.');
        }

        if ($this->storage->exists($collection, $slug)) {
            throw new \InvalidArgumentException("Term '{$slug}' already exists in taxonomy '{$taxonomyId}'.");
        }

        $termData = [
            'slug'        => $slug,
            'name'        => $term['name'] ?? ucfirst($slug),
            'slug_i18n'   => $term['slug_i18n'] ?? [],
            'parent'      => $term['parent'] ?? '',
            'description' => $term['description'] ?? '',
            'created_at'  => Helpers::now(),
            'updated_at'  => Helpers::now(),
        ];

        $this->storage->write($collection, $slug, $termData);

        Hooks::doAction('term.after_save', $postTypeId, $taxonomyId, $termData, 'create');

        return $termData;
    }

    /**
     * Update a term.
     */
    public function updateTerm(string $postTypeId, string $taxonomyId, string $termSlug, array $data): array
    {
        $collection = $this->termsCollection($postTypeId, $taxonomyId);

        if (!$this->storage->exists($collection, $termSlug)) {
            throw new \InvalidArgumentException("Term '{$termSlug}' not found.");
        }

        $term = $this->storage->read($collection, $termSlug);

        $updatable = ['name', 'slug_i18n', 'parent', 'description'];
        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $term[$field] = $data[$field];
            }
        }

        $term['updated_at'] = Helpers::now();
        $this->storage->write($collection, $termSlug, $term);

        Hooks::doAction('term.after_save', $postTypeId, $taxonomyId, $term, 'update');

        return $term;
    }

    /**
     * Delete a term.
     */
    public function deleteTerm(string $postTypeId, string $taxonomyId, string $termSlug): bool
    {
        $collection = $this->termsCollection($postTypeId, $taxonomyId);

        if (!$this->storage->exists($collection, $termSlug)) {
            throw new \InvalidArgumentException("Term '{$termSlug}' not found.");
        }

        Hooks::doAction('term.before_delete', $postTypeId, $taxonomyId, $termSlug);

        $result = $this->storage->delete($collection, $termSlug);

        if ($result) {
            Hooks::doAction('term.after_delete', $postTypeId, $taxonomyId, $termSlug);
        }

        return $result;
    }

    /**
     * List all terms for a taxonomy.
     */
    public function listTerms(string $postTypeId, string $taxonomyId): array
    {
        $this->verifyTaxonomyExists($postTypeId, $taxonomyId);

        $collection = $this->termsCollection($postTypeId, $taxonomyId);
        $terms = $this->storage->list($collection);

        usort($terms, fn(array $a, array $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));

        return $terms;
    }

    /**
     * Get a single term.
     */
    public function getTerm(string $postTypeId, string $taxonomyId, string $termSlug): array
    {
        $collection = $this->termsCollection($postTypeId, $taxonomyId);
        return $this->storage->read($collection, $termSlug);
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Verify a taxonomy exists within a post type.
     */
    private function verifyTaxonomyExists(string $postTypeId, string $taxonomyId): void
    {
        $postType = $this->get($postTypeId);
        $found = false;

        foreach ($postType['taxonomies'] as $tax) {
            if (($tax['id'] ?? '') === $taxonomyId) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new \InvalidArgumentException("Taxonomy '{$taxonomyId}' not found in post type '{$postTypeId}'.");
        }
    }

    /**
     * Delete all terms for all taxonomies in a post type.
     */
    private function deleteAllTerms(string $postTypeId): void
    {
        try {
            $postType = $this->get($postTypeId);
            foreach ($postType['taxonomies'] as $tax) {
                $this->deleteTermsForTaxonomy($postTypeId, $tax['id'] ?? '');
            }
        } catch (\RuntimeException $e) {
            // Post type already deleted, nothing to clean up.
        }
    }

    /**
     * Delete all terms for a specific taxonomy.
     */
    private function deleteTermsForTaxonomy(string $postTypeId, string $taxonomyId): void
    {
        $collection = $this->termsCollection($postTypeId, $taxonomyId);
        try {
            $terms = $this->storage->list($collection);
            foreach ($terms as $term) {
                $this->storage->delete($collection, $term['slug'] ?? '');
            }
        } catch (\RuntimeException $e) {
            // No terms to delete.
        }
    }

    /**
     * Build post type data array with defaults.
     */
    private function buildPostTypeData(string $id, array $data): array
    {
        $taxonomies = [];
        if (!empty($data['taxonomies']) && is_array($data['taxonomies'])) {
            foreach ($data['taxonomies'] as $tax) {
                $taxId = Helpers::sanitizeSlug($tax['id'] ?? $tax['name'] ?? '');
                if (empty($taxId)) {
                    continue;
                }
                $taxonomies[] = [
                    'id'           => $taxId,
                    'name'         => $tax['name'] ?? ucfirst($taxId),
                    'slug'         => $tax['slug'] ?? $taxId,
                    'slug_i18n'    => $tax['slug_i18n'] ?? [],
                    'hierarchical' => $tax['hierarchical'] ?? false,
                ];
            }
        }

        return [
            'id'         => $id,
            'name'       => $data['name'] ?? ucfirst($id),
            'slug'       => $data['slug'] ?? $id,
            'slug_i18n'  => $data['slug_i18n'] ?? [],
            'taxonomies' => $taxonomies,
        ];
    }

    /**
     * Get all post types with their taxonomies for sidebar menu generation.
     *
     * @return array Post types with menu-ready structure.
     */
    public function getMenuItems(): array
    {
        $postTypes = $this->list();
        $items = [];

        foreach ($postTypes as $pt) {
            // Skip the built-in page type (it has its own menu).
            if ($pt['builtin'] ?? false) {
                continue;
            }

            $items[] = [
                'id'         => $pt['id'],
                'name'       => $pt['name'],
                'taxonomies' => $pt['taxonomies'] ?? [],
            ];
        }

        return $items;
    }
}
