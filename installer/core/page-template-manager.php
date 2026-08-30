<?php

/**
 * Klytos — Page Template Manager
 * Manages page templates: recipes that define which blocks appear and in what order.
 *
 * A page template is a reusable layout that defines:
 * - Which blocks to include (by block ID).
 * - The order in which blocks are rendered.
 * - Default content data for each block.
 *
 * Core templates: home, page, post, contact, landing, gallery, faq, team, services.
 * Plugins can add more via the 'page_template.available_types' filter.
 *
 * Workflow:
 * 1. AI creates a page template (e.g. 'landing') with specific blocks.
 * 2. Admin previews and approves the template.
 * 3. Pages using that template are built by assembling the blocks in order.
 *
 * Storage: Collection 'page-templates' in StorageInterface.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class PageTemplateManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var BlockManager Block manager for rendering. */
    private BlockManager $blockManager;

    /** @var string Collection name. */
    private const COLLECTION = 'page-templates';

    /**
     * @param StorageInterface $storage      Storage backend instance.
     * @param BlockManager     $blockManager Block manager for rendering blocks.
     */
    public function __construct(StorageInterface $storage, BlockManager $blockManager)
    {
        $this->storage      = $storage;
        $this->blockManager = $blockManager;
    }

    /**
     * Save (create or update) a page template.
     *
     * @param  array $data Template data: type (required), name (required),
     *                     structure (array of block references), wrapper_html.
     * @return array The saved template.
     * @throws \InvalidArgumentException On validation failure.
     */
    public function save(array $data): array
    {
        $type = trim($data['type'] ?? '');
        $name = trim($data['name'] ?? '');

        if (empty($type)) {
            throw new \InvalidArgumentException('Template type is required.');
        }
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $type)) {
            throw new \InvalidArgumentException('Template type must be alphanumeric with hyphens/underscores.');
        }
        if (empty($name)) {
            throw new \InvalidArgumentException('Template name is required.');
        }

        $isNew = !$this->storage->exists(self::COLLECTION, $type);

        $template = [
            'type'         => $type,
            'name'         => $name,
            'description'  => $data['description'] ?? '',
            'structure'    => $this->validateStructure($data['structure'] ?? []),
            'wrapper_html' => $data['wrapper_html'] ?? '<div class="klytos-page">{{blocks_html}}</div>',
            'status'       => $data['status'] ?? 'draft',
            'version'      => $data['version'] ?? '1.0.0',
            'approved_by'  => $data['approved_by'] ?? null,
            'approved_at'  => $data['approved_at'] ?? null,
            'created_at'   => $isNew ? Helpers::now() : ($data['created_at'] ?? Helpers::now()),
            'updated_at'   => Helpers::now(),
        ];

        klytos_do_action('page_template.before_save', $template);

        $this->storage->write(self::COLLECTION, $type, $template);

        klytos_do_action('page_template.after_save', $template);

        return $template;
    }

    /**
     * Get a page template by type.
     *
     * @param  string $type Template type (e.g. 'home', 'landing').
     * @return array  Template data.
     * @throws \RuntimeException If not found.
     */
    public function get(string $type): array
    {
        return $this->storage->read(self::COLLECTION, $type);
    }

    /**
     * List all page templates.
     *
     * @param  string $status Filter by status ('all', 'active', 'draft').
     * @return array  Array of templates.
     */
    public function list(string $status = 'all'): array
    {
        $filters = [];
        if ($status !== 'all') {
            $filters['status'] = $status;
        }

        $templates = $this->storage->list(self::COLLECTION, $filters);

        usort($templates, fn(array $a, array $b): int =>
            strcmp($a['name'] ?? '', $b['name'] ?? '')
        );

        return $templates;
    }

    /**
     * Delete a page template.
     *
     * @param  string $type Template type.
     * @return bool   True if deleted.
     */
    public function delete(string $type): bool
    {
        return $this->storage->delete(self::COLLECTION, $type);
    }

    /**
     * Add a block to a template's structure.
     *
     * @param  string $type     Template type.
     * @param  string $blockId  Block ID to add.
     * @param  int    $position Position in the structure (0-indexed). -1 = append.
     * @return array  Updated template.
     */
    public function addBlock(string $type, string $blockId, int $position = -1): array
    {
        $template = $this->get($type);

        $blockRef = [
            'block_id' => $blockId,
            'order'    => $position >= 0 ? $position : count($template['structure']),
        ];

        if ($position >= 0 && $position < count($template['structure'])) {
            array_splice($template['structure'], $position, 0, [$blockRef]);
            // Re-index order values.
            $template['structure'] = $this->reindexStructure($template['structure']);
        } else {
            $template['structure'][] = $blockRef;
        }

        $template['updated_at'] = Helpers::now();
        $this->storage->write(self::COLLECTION, $type, $template);

        return $template;
    }

    /**
     * Remove a block from a template's structure.
     *
     * @param  string $type    Template type.
     * @param  string $blockId Block ID to remove.
     * @return array  Updated template.
     */
    public function removeBlock(string $type, string $blockId): array
    {
        $template = $this->get($type);

        $template['structure'] = array_values(array_filter(
            $template['structure'],
            fn(array $ref): bool => ($ref['block_id'] ?? '') !== $blockId
        ));

        $template['structure']  = $this->reindexStructure($template['structure']);
        $template['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $type, $template);

        return $template;
    }

    /**
     * Reorder blocks within a template.
     *
     * @param  string $type     Template type.
     * @param  array  $blockIds Ordered array of block IDs (new order).
     * @return array  Updated template.
     */
    public function reorderBlocks(string $type, array $blockIds): array
    {
        $template  = $this->get($type);
        $newStructure = [];

        foreach ($blockIds as $index => $blockId) {
            // Find the existing block reference.
            foreach ($template['structure'] as $ref) {
                if (($ref['block_id'] ?? '') === $blockId) {
                    $ref['order'] = $index;
                    $newStructure[] = $ref;
                    break;
                }
            }
        }

        $template['structure']  = $newStructure;
        $template['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $type, $template);

        return $template;
    }

    /**
     * Approve a template (change status from draft to active).
     *
     * @param  string      $type   Template type.
     * @param  string|null $userId User who approved.
     * @return array       Updated template.
     */
    public function approve(string $type, ?string $userId = null): array
    {
        $template = $this->get($type);

        $template['status']      = 'active';
        $template['approved_by'] = $userId;
        $template['approved_at'] = Helpers::now();
        $template['updated_at']  = Helpers::now();

        $this->storage->write(self::COLLECTION, $type, $template);

        klytos_do_action('page_template.approved', $template);

        return $template;
    }

    /**
     * Render a full page using this template and the page's content data.
     *
     * Assembles blocks in order, wraps in the template's wrapper_html,
     * and returns the final HTML.
     *
     * @param  string $type          Template type.
     * @param  array  $pageData      Page data including 'content' (block data keyed by block_id).
     * @param  array  $excludeBlocks Block IDs to exclude (already provided by the custom template).
     * @return string Complete rendered HTML for the page content area.
     */
    public function renderPage( string $type, array $pageData, array $excludeBlocks = [] ): string
    {
        $template  = $this->get( $type );
        $structure = $template['structure'] ?? [];

        // Allow plugins to modify the template structure before rendering.
        $structure = klytos_apply_filters( 'page_template.structure', $structure, $type );

        // Remove structural blocks already provided by the custom template
        // (e.g. header/footer) to prevent duplication in {{page_content}}.
        if ( !empty( $excludeBlocks ) ) {
            $structure = array_filter(
                $structure,
                fn( array $ref ): bool => !in_array( $ref['block_id'] ?? '', $excludeBlocks, true )
            );
            $structure = array_values( $structure );
            $structure = klytos_apply_filters( 'page_template.structure_after_dedup', $structure, $type, $excludeBlocks );
        }

        // Sort blocks by order.
        usort( $structure, fn( array $a, array $b ): int =>
            ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 )
        );

        // Render each block with its data.
        $blocksHtml = '';
        $pageContent = $pageData['content'] ?? [];

        foreach ($structure as $blockRef) {
            $blockId  = $blockRef['block_id'] ?? '';
            $blockData = $pageContent[$blockId] ?? [];

            if (empty($blockId)) {
                continue;
            }

            try {
                $blocksHtml .= $this->blockManager->render($blockId, $blockData) . "\n";
            } catch (\RuntimeException $e) {
                // Block not found — skip silently in production.
                $blocksHtml .= "<!-- Block '{$blockId}' not found -->\n";
            }
        }

        // Insert rendered blocks into the template wrapper.
        $wrapperHtml = $template['wrapper_html'] ?? '{{blocks_html}}';

        // Allow plugins to modify the wrapper before insertion.
        $wrapperHtml = klytos_apply_filters('page_template.wrapper_html', $wrapperHtml, $type);

        $html = str_replace('{{blocks_html}}', $blocksHtml, $wrapperHtml);

        return $html;
    }

    /**
     * Get the content schema required by a template.
     *
     * Returns a list of blocks and their slots, so the AI knows
     * what data to provide when creating a page with this template.
     *
     * @param  string $type Template type.
     * @return array  Schema: [{block_id, block_name, slots: [...]}]
     */
    public function getContentSchema(string $type): array
    {
        $template  = $this->get($type);
        $structure = $template['structure'] ?? [];
        $schema    = [];

        foreach ($structure as $blockRef) {
            $blockId = $blockRef['block_id'] ?? '';
            if (empty($blockId)) {
                continue;
            }

            try {
                $block = $this->blockManager->get($blockId);
                $schema[] = [
                    'block_id'   => $blockId,
                    'block_name' => $block['name'] ?? $blockId,
                    'scope'      => $block['scope'] ?? 'page',
                    'slots'      => $block['slots'] ?? [],
                ];
            } catch (\RuntimeException $e) {
                continue;
            }
        }

        return $schema;
    }

    /**
     * Get all available page template types (core + plugin-registered).
     *
     * @return array Array of available template type definitions.
     */
    /**
     * How many TEMPLATES use each block, counted in one traversal.
     *
     * Manifest entry 21's tile draws a usage count and `BlockManager` tracks no
     * usage of any kind — there is no `block-usage` collection and nothing
     * counts references. The relationship the product does store runs this way
     * round: a template holds block ids (`addBlock()`), and a page gets its
     * blocks from its template. So the count is real, and it is TEMPLATES.
     *
     * Whether §21 means templates or the pages behind them is not something the
     * tile gets to decide quietly — the screen says which it is, and **DR-016**
     * asks. A block used twice in one template counts that template ONCE: the
     * figure answers "how many templates use this", and counting placements
     * instead would change the meaning while looking identical.
     *
     * Here and not on `BlockManager` because the dependency already runs this
     * way: templates know their blocks, blocks know nothing about templates, and
     * inverting that for convenience would be a cycle.
     *
     * @return array<string,int> Block id => number of templates using it.
     *                           A block nothing uses is ABSENT, so the caller
     *                           decides how to draw zero.
     */
    public function blockUsageCounts(): array
    {
        $counts = [];

        foreach ($this->storage->list(self::COLLECTION) as $template) {
            // `structure`, a list of `['block_id' => …, 'order' => …]` — the
            // shape `addBlock()` really writes. This method first read a plain
            // `blocks` list of ids, which is what its unit test had seeded, so
            // the test passed and every count on the real screen was ZERO. The
            // driven screen is what said so (D-120), and the test now goes
            // through `addBlock()` rather than through an invented shape.
            $structure = $template['structure'] ?? [];

            // The collection is writable by MCP and by plugins, so a string
            // where a list belongs is reachable — and a gallery that fatals on
            // one bad record shows nobody their blocks.
            if (!is_array($structure)) {
                continue;
            }

            $seen = [];

            foreach ($structure as $ref) {
                $blockId = is_array($ref) ? (string) ($ref['block_id'] ?? '') : '';

                if ($blockId === '' || isset($seen[$blockId])) {
                    continue;
                }

                $seen[$blockId]   = true;
                $counts[$blockId] = ($counts[$blockId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function getAvailableTypes(): array
    {
        $types = $this->list('all');

        // Allow plugins to register additional template types.
        $types = klytos_apply_filters('page_template.available_types', $types);

        return $types;
    }

    /**
     * Preview a template using sample data from each block.
     *
     * @param  string $type Template type.
     * @return string Rendered HTML with sample data.
     */
    public function preview(string $type): string
    {
        return $this->renderPage($type, []);
    }

    /**
     * Preview a template with custom content data.
     *
     * @param  string $type Template type.
     * @param  array  $data Block data keyed by block_id.
     * @return string Rendered HTML with provided data.
     */
    public function previewWithData(string $type, array $data): string
    {
        return $this->renderPage($type, ['content' => $data]);
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Validate a template structure (array of block references).
     *
     * @param  array $structure Raw structure data.
     * @return array Validated structure.
     */
    private function validateStructure(array $structure): array
    {
        $validated = [];

        foreach ($structure as $index => $ref) {
            if (empty($ref['block_id'])) {
                continue;
            }

            $validated[] = [
                'block_id' => $ref['block_id'],
                'order'    => $ref['order'] ?? $index,
            ];
        }

        return $validated;
    }

    /**
     * Re-index the order values of a structure array.
     *
     * @param  array $structure Structure to re-index.
     * @return array Re-indexed structure.
     */
    private function reindexStructure(array $structure): array
    {
        foreach ($structure as $index => &$ref) {
            $ref['order'] = $index;
        }
        return $structure;
    }
}
