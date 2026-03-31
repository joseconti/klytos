<?php

/**
 * Klytos — Post Type Manager
 * CRUD operations for custom post types, taxonomies, and custom fields.
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
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
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
            'id'            => self::BUILTIN_PAGE,
            'name'          => 'Pages',
            'slug'          => '/',
            'slug_i18n'     => [],
            'taxonomies'    => [],
            'custom_fields' => [],
            'builtin'       => true,
            'created_at'    => Helpers::now(),
            'updated_at'    => Helpers::now(),
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

        klytos_do_action('post_type.before_save', $postType, 'create');

        $this->storage->write(self::COLLECTION, $id, $postType);

        klytos_do_action('post_type.after_save', $postType, 'create');

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
        $updatable = [ 'name', 'slug', 'slug_i18n', 'taxonomies', 'custom_fields' ];
        foreach ($updatable as $field) {
            if (array_key_exists($field, $data)) {
                $postType[$field] = $data[$field];
            }
        }

        $postType['updated_at'] = Helpers::now();

        klytos_do_action('post_type.before_save', $postType, 'update');

        $this->storage->write(self::COLLECTION, $id, $postType);

        klytos_do_action('post_type.after_save', $postType, 'update');

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

        klytos_do_action('post_type.before_delete', $id);

        $result = $this->storage->delete(self::COLLECTION, $id);

        if ($result) {
            // Also delete all taxonomy term data for this post type.
            $this->deleteAllTerms($id);
            klytos_do_action('post_type.after_delete', $id);
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

        klytos_do_action('taxonomy.before_save', $postTypeId, $taxData, 'create');

        $this->storage->write(self::COLLECTION, $postTypeId, $postType);

        klytos_do_action('taxonomy.after_save', $postTypeId, $taxData, 'create');

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

        klytos_do_action('taxonomy.before_save', $postTypeId, $taxonomyId, 'update');

        $this->storage->write(self::COLLECTION, $postTypeId, $postType);

        klytos_do_action('taxonomy.after_save', $postTypeId, $taxonomyId, 'update');

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

        klytos_do_action('taxonomy.after_delete', $postTypeId, $taxonomyId);

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

        klytos_do_action('term.before_save', $postTypeId, $taxonomyId, $termData, 'create');

        $this->storage->write($collection, $slug, $termData);

        klytos_do_action('term.after_save', $postTypeId, $taxonomyId, $termData, 'create');

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

        klytos_do_action('term.before_save', $postTypeId, $taxonomyId, $term, 'update');

        $this->storage->write($collection, $termSlug, $term);

        klytos_do_action('term.after_save', $postTypeId, $taxonomyId, $term, 'update');

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

        klytos_do_action('term.before_delete', $postTypeId, $taxonomyId, $termSlug);

        $result = $this->storage->delete($collection, $termSlug);

        if ($result) {
            klytos_do_action('term.after_delete', $postTypeId, $taxonomyId, $termSlug);
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

    // ─── Custom Fields Management ────────────────────────────────

    /** @var array Allowed custom field types. */
    private const FIELD_TYPES = [
        // Text
        'text', 'textarea', 'richtext', 'code', 'password',
        // Number
        'number', 'range',
        // Date/Time
        'date', 'datetime', 'time',
        // Choice
        'select', 'multiselect', 'checkbox', 'checkbox_group', 'radio', 'toggle',
        // Media
        'image', 'file', 'gallery',
        // Data
        'email', 'url', 'phone', 'color', 'json',
        // Advanced
        'repeater', 'relationship',
    ];

    /**
     * Get all supported field types with metadata.
     *
     * @return array Map of type => description, validation keys, features.
     */
    public static function getFieldTypes(): array
    {
        return [
            'text'           => [
                'label'       => 'Text',
                'category'    => 'text',
                'description' => 'Single-line text input.',
                'validation'  => ['min_length', 'max_length', 'pattern'],
            ],
            'textarea'       => [
                'label'       => 'Textarea',
                'category'    => 'text',
                'description' => 'Multi-line plain text input.',
                'validation'  => ['min_length', 'max_length'],
            ],
            'richtext'       => [
                'label'       => 'Rich Text',
                'category'    => 'text',
                'description' => 'Rich text editor with HTML formatting.',
                'validation'  => ['max_length'],
            ],
            'code'           => [
                'label'       => 'Code',
                'category'    => 'text',
                'description' => 'Code editor with monospace font.',
                'validation'  => ['max_length', 'language'],
            ],
            'password'       => [
                'label'       => 'Password',
                'category'    => 'text',
                'description' => 'Masked password input.',
                'validation'  => ['min_length', 'max_length'],
            ],
            'number'         => [
                'label'       => 'Number',
                'category'    => 'number',
                'description' => 'Numeric input (integer or decimal).',
                'validation'  => ['min', 'max', 'step', 'integer'],
            ],
            'range'          => [
                'label'       => 'Range',
                'category'    => 'number',
                'description' => 'Slider input with min/max range.',
                'validation'  => ['min', 'max', 'step'],
            ],
            'date'           => [
                'label'       => 'Date',
                'category'    => 'datetime',
                'description' => 'Date picker (YYYY-MM-DD).',
                'validation'  => ['min', 'max'],
            ],
            'datetime'       => [
                'label'       => 'Date & Time',
                'category'    => 'datetime',
                'description' => 'Date and time picker (ISO 8601).',
                'validation'  => ['min', 'max'],
            ],
            'time'           => [
                'label'       => 'Time',
                'category'    => 'datetime',
                'description' => 'Time picker (HH:MM or HH:MM:SS).',
                'validation'  => ['min', 'max'],
            ],
            'select'         => [
                'label'       => 'Select',
                'category'    => 'choice',
                'description' => 'Dropdown selector (single value).',
                'has_options' => true,
                'validation'  => [],
            ],
            'multiselect'    => [
                'label'       => 'Multi Select',
                'category'    => 'choice',
                'description' => 'Dropdown selector (multiple values).',
                'has_options' => true,
                'validation'  => ['min_selections', 'max_selections'],
            ],
            'checkbox'       => [
                'label'       => 'Checkbox',
                'category'    => 'choice',
                'description' => 'Single boolean checkbox (on/off).',
                'validation'  => [],
            ],
            'checkbox_group' => [
                'label'       => 'Checkbox Group',
                'category'    => 'choice',
                'description' => 'Multiple checkboxes (multiple values).',
                'has_options' => true,
                'validation'  => ['min_selections', 'max_selections'],
            ],
            'radio'          => [
                'label'       => 'Radio',
                'category'    => 'choice',
                'description' => 'Radio buttons (single value).',
                'has_options' => true,
                'validation'  => [],
            ],
            'toggle'         => [
                'label'       => 'Toggle',
                'category'    => 'choice',
                'description' => 'Toggle switch (on/off).',
                'validation'  => [],
            ],
            'image'          => [
                'label'       => 'Image',
                'category'    => 'media',
                'description' => 'Image selector with preview.',
                'validation'  => ['allowed_types', 'max_size'],
            ],
            'file'           => [
                'label'       => 'File',
                'category'    => 'media',
                'description' => 'File upload/selector.',
                'validation'  => ['allowed_types', 'max_size'],
            ],
            'gallery'        => [
                'label'       => 'Gallery',
                'category'    => 'media',
                'description' => 'Multiple image selector.',
                'validation'  => ['allowed_types', 'max_size', 'max_items'],
            ],
            'email'          => [
                'label'       => 'Email',
                'category'    => 'data',
                'description' => 'Email address input with validation.',
                'validation'  => [],
            ],
            'url'            => [
                'label'       => 'URL',
                'category'    => 'data',
                'description' => 'URL input with validation.',
                'validation'  => [],
            ],
            'phone'          => [
                'label'       => 'Phone',
                'category'    => 'data',
                'description' => 'Phone number input.',
                'validation'  => ['pattern'],
            ],
            'color'          => [
                'label'       => 'Color',
                'category'    => 'data',
                'description' => 'Color picker (hex format).',
                'validation'  => [],
            ],
            'json'           => [
                'label'       => 'JSON',
                'category'    => 'data',
                'description' => 'JSON data editor with validation.',
                'validation'  => ['max_length'],
            ],
            'repeater'       => [
                'label'          => 'Repeater',
                'category'       => 'advanced',
                'description'    => 'Repeatable group of sub-fields.',
                'has_sub_fields' => true,
                'validation'     => ['min_rows', 'max_rows'],
            ],
            'relationship'   => [
                'label'            => 'Relationship',
                'category'         => 'advanced',
                'description'      => 'Reference to other post entries.',
                'has_relationship' => true,
                'validation'       => ['max'],
            ],
        ];
    }

    /**
     * Add a custom field to a post type.
     *
     * @param  string $postTypeId Post type ID.
     * @param  array  $field      Field data: id, type, label, description, etc.
     * @return array  Updated post type.
     */
    public function addCustomField(string $postTypeId, array $field): array
    {
        $postType = $this->get($postTypeId);

        $fieldId = Helpers::sanitizeSlug($field['id'] ?? '');
        if (empty($fieldId)) {
            throw new \InvalidArgumentException('Custom field ID is required.');
        }

        // Check for duplicate field ID.
        $existingFields = $postType['custom_fields'] ?? [];
        foreach ($existingFields as $existing) {
            if (($existing['id'] ?? '') === $fieldId) {
                throw new \InvalidArgumentException("Custom field '{$fieldId}' already exists in post type '{$postTypeId}'.");
            }
        }

        $fieldData = $this->buildCustomFieldData($field);

        // Auto-assign position if not provided.
        if (!isset($field['position'])) {
            $maxPosition = -1;
            foreach ($existingFields as $existing) {
                $pos = $existing['position'] ?? 0;
                if ($pos > $maxPosition) {
                    $maxPosition = $pos;
                }
            }
            $fieldData['position'] = $maxPosition + 1;
        }

        $postType['custom_fields']   = $existingFields;
        $postType['custom_fields'][] = $fieldData;
        $postType['updated_at']      = Helpers::now();

        klytos_do_action('custom_field.before_save', $postTypeId, $fieldData, 'create');

        $this->storage->write(self::COLLECTION, $postTypeId, $postType);

        klytos_do_action('custom_field.after_save', $postTypeId, $fieldData, 'create');

        return $postType;
    }

    /**
     * Update a custom field within a post type.
     *
     * @param  string $postTypeId Post type ID.
     * @param  string $fieldId    Custom field ID.
     * @param  array  $data       Fields to update.
     * @return array  Updated post type.
     */
    public function updateCustomField(string $postTypeId, string $fieldId, array $data): array
    {
        $postType = $this->get($postTypeId);
        $found    = false;

        $fields = $postType['custom_fields'] ?? [];
        foreach ($fields as &$cf) {
            if (($cf['id'] ?? '') === $fieldId) {
                $updatable = [
                    'type', 'label', 'description', 'placeholder',
                    'default_value', 'required', 'position', 'options',
                    'validation', 'conditions', 'sub_fields', 'relationship',
                ];
                foreach ($updatable as $key) {
                    if (array_key_exists($key, $data)) {
                        $cf[$key] = $data[$key];
                    }
                }
                // Re-validate type if changed.
                if (isset($data['type']) && !in_array($data['type'], self::FIELD_TYPES, true)) {
                    throw new \InvalidArgumentException("Invalid field type: {$data['type']}");
                }
                $found = true;
                break;
            }
        }
        unset($cf);

        if (!$found) {
            throw new \InvalidArgumentException("Custom field '{$fieldId}' not found in post type '{$postTypeId}'.");
        }

        $postType['custom_fields'] = $fields;
        $postType['updated_at']    = Helpers::now();

        klytos_do_action('custom_field.before_save', $postTypeId, $fieldId, 'update');

        $this->storage->write(self::COLLECTION, $postTypeId, $postType);

        klytos_do_action('custom_field.after_save', $postTypeId, $fieldId, 'update');

        return $postType;
    }

    /**
     * Remove a custom field from a post type.
     *
     * @param  string $postTypeId Post type ID.
     * @param  string $fieldId    Custom field ID to remove.
     * @return array  Updated post type.
     */
    public function removeCustomField(string $postTypeId, string $fieldId): array
    {
        $postType = $this->get($postTypeId);

        klytos_do_action('custom_field.before_delete', $postTypeId, $fieldId);

        $postType['custom_fields'] = array_values(array_filter(
            $postType['custom_fields'] ?? [],
            fn(array $cf) => ($cf['id'] ?? '') !== $fieldId
        ));

        $postType['updated_at'] = Helpers::now();
        $this->storage->write(self::COLLECTION, $postTypeId, $postType);

        klytos_do_action('custom_field.after_delete', $postTypeId, $fieldId);

        return $postType;
    }

    /**
     * List all custom fields for a post type, sorted by position.
     *
     * @param  string $postTypeId Post type ID.
     * @return array
     */
    public function listCustomFields(string $postTypeId): array
    {
        $postType = $this->get($postTypeId);
        $fields   = $postType['custom_fields'] ?? [];

        usort($fields, fn(array $a, array $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        return $fields;
    }

    /**
     * Get a single custom field definition by ID.
     *
     * @param  string $postTypeId Post type ID.
     * @param  string $fieldId    Custom field ID.
     * @return array
     */
    public function getCustomField(string $postTypeId, string $fieldId): array
    {
        $postType = $this->get($postTypeId);

        foreach ($postType['custom_fields'] ?? [] as $cf) {
            if (($cf['id'] ?? '') === $fieldId) {
                return $cf;
            }
        }

        throw new \InvalidArgumentException("Custom field '{$fieldId}' not found in post type '{$postTypeId}'.");
    }

    /**
     * Reorder custom fields within a post type.
     *
     * @param  string $postTypeId Post type ID.
     * @param  array  $fieldIds   Ordered array of field IDs.
     * @return array  Updated post type.
     */
    public function reorderCustomFields(string $postTypeId, array $fieldIds): array
    {
        $postType = $this->get($postTypeId);
        $fields   = $postType['custom_fields'] ?? [];

        // Index fields by ID.
        $indexed = [];
        foreach ($fields as $cf) {
            $indexed[$cf['id'] ?? ''] = $cf;
        }

        // Validate all IDs are present.
        foreach ($fieldIds as $fid) {
            if (!isset($indexed[$fid])) {
                throw new \InvalidArgumentException("Custom field '{$fid}' not found in post type '{$postTypeId}'.");
            }
        }

        // Rebuild with new positions.
        $reordered = [];
        $position  = 0;
        foreach ($fieldIds as $fid) {
            $cf             = $indexed[$fid];
            $cf['position'] = $position++;
            $reordered[]    = $cf;
        }

        // Append any fields not in the provided list (keep their relative order).
        foreach ($fields as $cf) {
            if (!in_array($cf['id'] ?? '', $fieldIds, true)) {
                $cf['position'] = $position++;
                $reordered[]    = $cf;
            }
        }

        $postType['custom_fields'] = $reordered;
        $postType['updated_at']    = Helpers::now();
        $this->storage->write(self::COLLECTION, $postTypeId, $postType);

        klytos_do_action('custom_field.after_reorder', $postTypeId, $fieldIds);

        return $postType;
    }

    /**
     * Build a custom field definition with defaults.
     *
     * @param  array $field Raw field data.
     * @return array Complete field definition.
     */
    private function buildCustomFieldData(array $field): array
    {
        $type = $field['type'] ?? 'text';
        if (!in_array($type, self::FIELD_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid field type: {$type}");
        }

        return [
            'id'            => Helpers::sanitizeSlug($field['id'] ?? ''),
            'type'          => $type,
            'label'         => $field['label'] ?? ucfirst($field['id'] ?? ''),
            'description'   => $field['description'] ?? '',
            'placeholder'   => $field['placeholder'] ?? '',
            'default_value' => $field['default_value'] ?? null,
            'required'      => (bool) ($field['required'] ?? false),
            'position'      => (int) ($field['position'] ?? 0),
            'options'       => $field['options'] ?? [],
            'validation'    => $field['validation'] ?? [],
            'conditions'    => $field['conditions'] ?? [],
            'sub_fields'    => $field['sub_fields'] ?? [],
            'relationship'  => $field['relationship'] ?? [],
        ];
    }

    /**
     * Validate a value against a custom field definition.
     *
     * @param  array $fieldDef The field definition.
     * @param  mixed $value    The value to validate.
     * @return mixed The validated/coerced value.
     * @throws \InvalidArgumentException If the value is invalid.
     */
    public function validateFieldValue(array $fieldDef, mixed $value): mixed
    {
        $type     = $fieldDef['type'] ?? 'text';
        $required = $fieldDef['required'] ?? false;
        $rules    = $fieldDef['validation'] ?? [];
        $fieldId  = $fieldDef['id'] ?? 'unknown';

        // Check required.
        if ($required && ($value === null || $value === '' || $value === [])) {
            throw new \InvalidArgumentException("Field '{$fieldId}' is required.");
        }

        // Allow null/empty for non-required fields.
        if ($value === null || $value === '') {
            return $value;
        }

        switch ($type) {
            case 'text':
            case 'textarea':
            case 'richtext':
            case 'code':
            case 'password':
                $value = (string) $value;
                if (isset($rules['min_length']) && mb_strlen($value) < (int) $rules['min_length']) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be at least {$rules['min_length']} characters.");
                }
                if (isset($rules['max_length']) && mb_strlen($value) > (int) $rules['max_length']) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be at most {$rules['max_length']} characters.");
                }
                if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' does not match the required pattern.");
                }
                break;

            case 'number':
            case 'range':
                if (!is_numeric($value)) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be a number.");
                }
                if (!empty($rules['integer'])) {
                    $value = (int) $value;
                } else {
                    $value = (float) $value;
                }
                if (isset($rules['min']) && $value < $rules['min']) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be at least {$rules['min']}.");
                }
                if (isset($rules['max']) && $value > $rules['max']) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be at most {$rules['max']}.");
                }
                break;

            case 'email':
                $value = (string) $value;
                if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be a valid email address.");
                }
                break;

            case 'url':
                $value = (string) $value;
                if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be a valid URL.");
                }
                break;

            case 'phone':
                $value   = (string) $value;
                $pattern = $rules['pattern'] ?? '/^[+]?[\d\s\-().]+$/';
                if (!preg_match($pattern, $value)) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be a valid phone number.");
                }
                break;

            case 'date':
                $value = (string) $value;
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be a valid date (YYYY-MM-DD).");
                }
                break;

            case 'datetime':
                $value = (string) $value;
                $dt    = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value)
                      ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value)
                      ?: \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);
                if (!$dt) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be a valid datetime.");
                }
                break;

            case 'time':
                $value = (string) $value;
                if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be a valid time (HH:MM or HH:MM:SS).");
                }
                break;

            case 'color':
                $value = (string) $value;
                if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be a valid hex color (#RGB, #RRGGBB, or #RRGGBBAA).");
                }
                break;

            case 'select':
            case 'radio':
                $value   = (string) $value;
                $options = array_column($fieldDef['options'] ?? [], 'value');
                if (!empty($options) && !in_array($value, $options, true)) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' has an invalid option: {$value}");
                }
                break;

            case 'multiselect':
            case 'checkbox_group':
                if (!is_array($value)) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be an array of values.");
                }
                $options = array_column($fieldDef['options'] ?? [], 'value');
                if (!empty($options)) {
                    foreach ($value as $v) {
                        if (!in_array((string) $v, $options, true)) {
                            throw new \InvalidArgumentException("Field '{$fieldId}' has an invalid option: {$v}");
                        }
                    }
                }
                if (isset($rules['min_selections']) && count($value) < (int) $rules['min_selections']) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' requires at least {$rules['min_selections']} selections.");
                }
                if (isset($rules['max_selections']) && count($value) > (int) $rules['max_selections']) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' allows at most {$rules['max_selections']} selections.");
                }
                break;

            case 'checkbox':
            case 'toggle':
                $value = (bool) $value;
                break;

            case 'image':
            case 'file':
                $value = (string) $value;
                break;

            case 'gallery':
                if (!is_array($value)) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be an array of image URLs.");
                }
                if (isset($rules['max_items']) && count($value) > (int) $rules['max_items']) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' allows at most {$rules['max_items']} items.");
                }
                break;

            case 'json':
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \InvalidArgumentException("Field '{$fieldId}' must contain valid JSON.");
                    }
                    $value = $decoded;
                }
                break;

            case 'repeater':
                if (!is_array($value)) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' must be an array of rows.");
                }
                $subFields = $fieldDef['sub_fields'] ?? [];
                if (isset($rules['min_rows']) && count($value) < (int) $rules['min_rows']) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' requires at least {$rules['min_rows']} rows.");
                }
                if (isset($rules['max_rows']) && count($value) > (int) $rules['max_rows']) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' allows at most {$rules['max_rows']} rows.");
                }
                // Validate each row against sub-field definitions.
                foreach ($value as $rowIndex => &$row) {
                    if (!is_array($row)) {
                        throw new \InvalidArgumentException("Field '{$fieldId}' row {$rowIndex} must be an object.");
                    }
                    foreach ($subFields as $subDef) {
                        $subId = $subDef['id'] ?? '';
                        if (array_key_exists($subId, $row)) {
                            $row[$subId] = $this->validateFieldValue($subDef, $row[$subId]);
                        }
                    }
                }
                unset($row);
                break;

            case 'relationship':
                if (!is_array($value)) {
                    $value = [$value];
                }
                $relConfig = $fieldDef['relationship'] ?? [];
                $multiple  = $relConfig['multiple'] ?? true;
                if (!$multiple && count($value) > 1) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' only allows a single relationship.");
                }
                if (isset($relConfig['max']) && count($value) > (int) $relConfig['max']) {
                    throw new \InvalidArgumentException("Field '{$fieldId}' allows at most {$relConfig['max']} relationships.");
                }
                break;
        }

        return $value;
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

        $customFields = [];
        if (!empty($data['custom_fields']) && is_array($data['custom_fields'])) {
            foreach ($data['custom_fields'] as $cf) {
                $cfId = Helpers::sanitizeSlug($cf['id'] ?? '');
                if (empty($cfId) || empty($cf['type'])) {
                    continue;
                }
                $customFields[] = $this->buildCustomFieldData($cf);
            }
        }

        return [
            'id'            => $id,
            'name'          => $data['name'] ?? ucfirst($id),
            'slug'          => $data['slug'] ?? $id,
            'slug_i18n'     => $data['slug_i18n'] ?? [],
            'taxonomies'    => $taxonomies,
            'custom_fields' => $customFields,
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
