<?php
/**
 * Klytos — MCP Page Template Tools
 * Tools for managing page templates: create, update, get, list, delete, add/remove/reorder
 * blocks, preview, approve, and get content schema.
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

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

function registerPageTemplateTools(ToolRegistry $registry, App $app): void
{
    $registry->register(
        'klytos_create_page_template',
        'Create a new page template defining which blocks appear and in what order.',
        [
            'type'         => ['type' => 'string', 'description' => 'Template type ID (e.g. home, landing, contact).'],
            'name'         => ['type' => 'string', 'description' => 'Human-readable template name.'],
            'description'  => ['type' => 'string', 'description' => 'Template description.'],
            'structure'    => ['type' => 'array', 'description' => 'Ordered array of block references: [{block_id, order}].', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'wrapper_html' => ['type' => 'string', 'description' => 'Wrapper HTML with {{blocks_html}} placeholder.'],
        ],
        function (array $params, App $app): array {
            $blockManager    = new \Klytos\Core\BlockManager($app->getStorage());
            $templateManager = new \Klytos\Core\PageTemplateManager($app->getStorage(), $blockManager);
            return $templateManager->save($params);
        },
        ['title' => 'Create Page Template', 'readOnlyHint' => false],
        ['type', 'name', 'structure']
    );

    $registry->register(
        'klytos_get_page_template',
        'Get a page template by type including its block structure.',
        [
            'type' => ['type' => 'string', 'description' => 'Template type (e.g. home, landing).'],
        ],
        function (array $params, App $app): array {
            if (empty($params['type'])) {
                throw new \InvalidArgumentException('type is required.');
            }
            $blockManager    = new \Klytos\Core\BlockManager($app->getStorage());
            $templateManager = new \Klytos\Core\PageTemplateManager($app->getStorage(), $blockManager);
            return $templateManager->get($params['type']);
        },
        ['title' => 'Get Page Template', 'readOnlyHint' => true],
        ['type']
    );

    $registry->register(
        'klytos_list_page_templates',
        'List all page templates.',
        [
            'status' => ['type' => 'string', 'description' => 'Filter: all, active, draft.'],
        ],
        function (array $params, App $app): array {
            $blockManager    = new \Klytos\Core\BlockManager($app->getStorage());
            $templateManager = new \Klytos\Core\PageTemplateManager($app->getStorage(), $blockManager);
            return $templateManager->list($params['status'] ?? 'all');
        },
        ['title' => 'List Page Templates', 'readOnlyHint' => true]
    );

    $registry->register(
        'klytos_add_block_to_template',
        'Add a block to a page template at a specific position.',
        [
            'type'     => ['type' => 'string', 'description' => 'Template type.'],
            'block_id' => ['type' => 'string', 'description' => 'Block ID to add.'],
            'position' => ['type' => 'integer', 'description' => 'Position (0-indexed). -1 = append at end.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['type']) || empty($params['block_id'])) {
                throw new \InvalidArgumentException('type and block_id are required.');
            }
            $blockManager    = new \Klytos\Core\BlockManager($app->getStorage());
            $templateManager = new \Klytos\Core\PageTemplateManager($app->getStorage(), $blockManager);
            return $templateManager->addBlock($params['type'], $params['block_id'], (int) ($params['position'] ?? -1));
        },
        ['title' => 'Add Block to Template', 'readOnlyHint' => false],
        ['type', 'block_id']
    );

    $registry->register(
        'klytos_remove_block_from_template',
        'Remove a block from a page template.',
        [
            'type'     => ['type' => 'string', 'description' => 'Template type.'],
            'block_id' => ['type' => 'string', 'description' => 'Block ID to remove.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['type']) || empty($params['block_id'])) {
                throw new \InvalidArgumentException('type and block_id are required.');
            }
            $blockManager    = new \Klytos\Core\BlockManager($app->getStorage());
            $templateManager = new \Klytos\Core\PageTemplateManager($app->getStorage(), $blockManager);
            return $templateManager->removeBlock($params['type'], $params['block_id']);
        },
        ['title' => 'Remove Block from Template', 'readOnlyHint' => false],
        ['type', 'block_id']
    );

    $registry->register(
        'klytos_reorder_template_blocks',
        'Reorder blocks within a page template by providing the new order of block IDs.',
        [
            'type'      => ['type' => 'string', 'description' => 'Template type.'],
            'block_ids' => ['type' => 'array', 'description' => 'Ordered array of block IDs (new order).', 'items' => ['type' => 'string']],
        ],
        function (array $params, App $app): array {
            if (empty($params['type']) || empty($params['block_ids'])) {
                throw new \InvalidArgumentException('type and block_ids are required.');
            }
            $blockManager    = new \Klytos\Core\BlockManager($app->getStorage());
            $templateManager = new \Klytos\Core\PageTemplateManager($app->getStorage(), $blockManager);
            return $templateManager->reorderBlocks($params['type'], $params['block_ids']);
        },
        ['title' => 'Reorder Template Blocks', 'readOnlyHint' => false],
        ['type', 'block_ids']
    );

    $registry->register(
        'klytos_approve_page_template',
        'Approve a page template (change status from draft to active).',
        [
            'type' => ['type' => 'string', 'description' => 'Template type to approve.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['type'])) {
                throw new \InvalidArgumentException('type is required.');
            }
            $blockManager    = new \Klytos\Core\BlockManager($app->getStorage());
            $templateManager = new \Klytos\Core\PageTemplateManager($app->getStorage(), $blockManager);
            return $templateManager->approve($params['type']);
        },
        ['title' => 'Approve Page Template', 'readOnlyHint' => false],
        ['type']
    );

    $registry->register(
        'klytos_preview_page_template',
        'Preview a page template by rendering it with sample or provided data.',
        [
            'type'      => ['type' => 'string', 'description' => 'Template type to preview.'],
            'page_data' => ['type' => 'object', 'description' => 'Page data with content object (block_id => slot values).', 'additionalProperties' => true],
        ],
        function (array $params, App $app): array {
            if (empty($params['type'])) {
                throw new \InvalidArgumentException('type is required.');
            }
            $blockManager    = new \Klytos\Core\BlockManager($app->getStorage());
            $templateManager = new \Klytos\Core\PageTemplateManager($app->getStorage(), $blockManager);
            $html = $templateManager->renderPage($params['type'], $params['page_data'] ?? []);
            return ['html' => $html];
        },
        ['title' => 'Preview Page Template', 'readOnlyHint' => true],
        ['type']
    );

    $registry->register(
        'klytos_get_template_content_schema',
        'Get the content schema for a template: what blocks it uses and what data each block needs.',
        [
            'type' => ['type' => 'string', 'description' => 'Template type.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['type'])) {
                throw new \InvalidArgumentException('type is required.');
            }
            $blockManager    = new \Klytos\Core\BlockManager($app->getStorage());
            $templateManager = new \Klytos\Core\PageTemplateManager($app->getStorage(), $blockManager);
            return $templateManager->getContentSchema($params['type']);
        },
        ['title' => 'Get Template Content Schema', 'readOnlyHint' => true],
        ['type']
    );
}
