<?php
/**
 * Klytos — Block Manager
 * Manages reusable HTML blocks for the modular template system.
 *
 * Blocks are the building pieces of Klytos pages. Each block is a reusable
 * HTML component with configurable "slots" (editable fields like text, images, URLs).
 *
 * Block categories:
 * - structure:    top-bar, header, menu, footer, breadcrumb, sidebar, cookie-banner.
 * - content:      hero, text-block, image-text, gallery, video-embed, blog-list.
 * - interaction:  contact-form, faq-accordion, cta, stats-counter.
 * - social-proof: testimonials, team-grid, logo-bar, map-embed.
 * - custom:       AI-generated blocks or plugin-provided blocks.
 *
 * Block scopes:
 * - global:   Same data across entire site (e.g. header, footer). Edited once.
 * - template: Configured at page template level.
 * - page:     Each page has its own data for this block.
 *
 * Slot types: text, richtext, image, url, icon, color, number, select,
 *             boolean, array, html, date, email, phone.
 *
 * Storage: Collection 'blocks' in StorageInterface.
 *
 * @package Klytos
 * @since   2.0.0
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

class BlockManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string Collection name for block definitions. */
    private const COLLECTION = 'blocks';

    /** @var array Valid block categories. */
    private const VALID_CATEGORIES = ['structure', 'content', 'interaction', 'social-proof', 'custom'];

    /** @var array Valid block scopes. */
    private const VALID_SCOPES = ['global', 'template', 'page'];

    /** @var array Valid slot types (core). Plugins can add more via 'block.slot_types' filter. */
    private const CORE_SLOT_TYPES = [
        'text', 'richtext', 'image', 'url', 'icon', 'color', 'number',
        'select', 'boolean', 'array', 'html', 'date', 'email', 'phone',
    ];

    /**
     * @param StorageInterface $storage Storage backend instance.
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Save (create or update) a block definition.
     *
     * @param  array $data Block data including: id (required), name (required),
     *                     category, scope, slots, html, css, js, sample_data.
     * @return array The saved block.
     * @throws \InvalidArgumentException On validation failure.
     */
    public function save(array $data): array
    {
        $blockId = trim($data['id'] ?? '');
        $name    = trim($data['name'] ?? '');

        if (empty($blockId)) {
            throw new \InvalidArgumentException('Block ID is required.');
        }
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $blockId)) {
            throw new \InvalidArgumentException('Block ID must be alphanumeric with hyphens/underscores.');
        }
        if (empty($name)) {
            throw new \InvalidArgumentException('Block name is required.');
        }

        $category = $data['category'] ?? 'custom';
        if (!in_array($category, self::VALID_CATEGORIES, true)) {
            $category = 'custom';
        }

        $scope = $data['scope'] ?? 'page';
        if (!in_array($scope, self::VALID_SCOPES, true)) {
            $scope = 'page';
        }

        // Build the block record.
        $isNew = !$this->storage->exists(self::COLLECTION, $blockId);

        $block = [
            'id'          => $blockId,
            'name'        => $name,
            'category'    => $category,
            'version'     => $data['version'] ?? '1.0.0',
            'status'      => $data['status'] ?? 'active',
            'scope'       => $scope,
            'slots'       => $this->validateSlots($data['slots'] ?? []),
            'html'        => $data['html'] ?? '',
            'css'         => $data['css'] ?? '',
            'js'          => $data['js'] ?? '',
            'sample_data' => $data['sample_data'] ?? [],
            'global_data' => $data['global_data'] ?? null,
            'created_at'  => $isNew ? Helpers::now() : ($data['created_at'] ?? Helpers::now()),
            'updated_at'  => Helpers::now(),
        ];

        Hooks::doAction('block.before_save', $block);

        $this->storage->write(self::COLLECTION, $blockId, $block);

        Hooks::doAction('block.after_save', $block);

        return $block;
    }

    /**
     * Get a block definition by ID.
     *
     * @param  string $blockId Block ID.
     * @return array  Block data.
     * @throws \RuntimeException If not found.
     */
    public function get(string $blockId): array
    {
        return $this->storage->read(self::COLLECTION, $blockId);
    }

    /**
     * List all blocks with optional category filter.
     *
     * @param  string $category Filter by category ('all' for no filter).
     * @param  string $status   Filter by status ('all', 'active', 'draft').
     * @return array  Array of block definitions.
     */
    public function list(string $category = 'all', string $status = 'all'): array
    {
        $filters = [];
        if ($category !== 'all' && in_array($category, self::VALID_CATEGORIES, true)) {
            $filters['category'] = $category;
        }
        if ($status !== 'all') {
            $filters['status'] = $status;
        }

        $blocks = $this->storage->list(self::COLLECTION, $filters);

        // Sort by category, then by name.
        usort($blocks, function (array $a, array $b): int {
            $catCmp = strcmp($a['category'] ?? '', $b['category'] ?? '');
            return $catCmp !== 0 ? $catCmp : strcmp($a['name'] ?? '', $b['name'] ?? '');
        });

        return $blocks;
    }

    /**
     * Delete a block definition.
     *
     * @param  string $blockId Block ID.
     * @return bool   True if deleted.
     */
    public function delete(string $blockId): bool
    {
        return $this->storage->delete(self::COLLECTION, $blockId);
    }

    /**
     * Render a block with provided data.
     *
     * Replaces {{slot_name}} placeholders in the block's HTML template
     * with the actual data values. Sanitizes all output.
     *
     * @param  string $blockId Block ID.
     * @param  array  $data    Slot values to inject (key => value).
     * @return string Rendered HTML.
     */
    public function render(string $blockId, array $data = []): string
    {
        $block = $this->get($blockId);
        $html  = $block['html'] ?? '';

        // If this is a global block and no data provided, use global_data.
        if ($block['scope'] === 'global' && empty($data)) {
            $data = $block['global_data'] ?? $block['sample_data'] ?? [];
        }

        // Replace slot placeholders with data values.
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Sanitize output to prevent XSS.
                $safeValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                $html = str_replace('{{' . $key . '}}', $safeValue, $html);
            } elseif (is_bool($value)) {
                $html = str_replace('{{' . $key . '}}', $value ? 'true' : 'false', $html);
            } elseif (is_numeric($value)) {
                $html = str_replace('{{' . $key . '}}', (string) $value, $html);
            }
            // Arrays and complex types are handled by specific slot renderers.
        }

        // Remove any remaining unreplaced placeholders.
        $html = preg_replace('/\{\{[a-zA-Z0-9_]+\}\}/', '', $html);

        // Wrap with comment markers for smart rebuild.
        $wrappedHtml = "<!--klytos:block:{$blockId}-->\n{$html}\n<!--/klytos:block:{$blockId}-->";

        // Allow plugins to modify the rendered block HTML.
        $wrappedHtml = Hooks::applyFilters('block.rendered_html', $wrappedHtml, $blockId, $data);

        return $wrappedHtml;
    }

    /**
     * Set the global data for a global-scope block.
     *
     * Global blocks (header, footer, etc.) share the same data across all pages.
     *
     * @param  string $blockId Block ID (must be a global-scope block).
     * @param  array  $data    Global slot values.
     * @return array  Updated block.
     * @throws \RuntimeException If the block is not global-scope.
     */
    public function setGlobalData(string $blockId, array $data): array
    {
        $block = $this->get($blockId);

        if ($block['scope'] !== 'global') {
            throw new \RuntimeException("Block '{$blockId}' is not global-scope. Cannot set global data.");
        }

        $block['global_data'] = $data;
        $block['updated_at']  = Helpers::now();

        $this->storage->write(self::COLLECTION, $blockId, $block);

        Hooks::doAction('block.global_data_changed', $blockId, $data);

        return $block;
    }

    /**
     * Get the global data for a global-scope block.
     *
     * @param  string $blockId Block ID.
     * @return array  Global data, or empty array.
     */
    public function getGlobalData(string $blockId): array
    {
        $block = $this->get($blockId);
        return $block['global_data'] ?? [];
    }

    /**
     * Get all available block types (core + plugin-registered).
     *
     * Plugins can add custom block types via the 'block.available_types' filter.
     *
     * @return array Array of available block type definitions.
     */
    public function getAvailableTypes(): array
    {
        $types = $this->list('all', 'active');

        // Allow plugins to register additional block types.
        $types = Hooks::applyFilters('block.available_types', $types);

        return $types;
    }

    /**
     * Get the slot definitions for a block.
     *
     * @param  string $blockId Block ID.
     * @return array  Array of slot definitions.
     */
    public function getSlots(string $blockId): array
    {
        $block = $this->get($blockId);
        return $block['slots'] ?? [];
    }

    /**
     * Get all valid slot types (core + plugin-registered).
     *
     * @return array List of valid slot type names.
     */
    public function getSlotTypes(): array
    {
        $types = self::CORE_SLOT_TYPES;

        // Allow plugins to add custom slot types (e.g. 'price', 'variant-selector').
        $types = Hooks::applyFilters('block.slot_types', $types);

        return $types;
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Validate slot definitions.
     *
     * Each slot must have at minimum: name and type.
     *
     * @param  array $slots Raw slot definitions.
     * @return array Validated slots.
     */
    private function validateSlots(array $slots): array
    {
        $validTypes = $this->getSlotTypes();
        $validated  = [];

        foreach ($slots as $slot) {
            if (empty($slot['name']) || empty($slot['type'])) {
                continue; // Skip invalid slots.
            }

            if (!in_array($slot['type'], $validTypes, true)) {
                continue; // Skip unknown slot types.
            }

            $validated[] = [
                'name'        => $slot['name'],
                'type'        => $slot['type'],
                'label'       => $slot['label'] ?? $slot['name'],
                'required'    => $slot['required'] ?? false,
                'default'     => $slot['default'] ?? null,
                'placeholder' => $slot['placeholder'] ?? '',
                'options'     => $slot['options'] ?? [],
            ];
        }

        return $validated;
    }
}
