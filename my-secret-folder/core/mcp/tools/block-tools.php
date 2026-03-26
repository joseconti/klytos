<?php
/**
 * Klytos — MCP Block Management Tools
 * Tools for the modular block system: create, update, get, list, delete, preview,
 * set global data, and get slot definitions.
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

function registerBlockTools(ToolRegistry $registry, App $app): void
{
    $registry->register(
        'klytos_create_block',
        'Create a new reusable HTML block with configurable slots. Blocks are the building pieces of pages.',
        [
            'id'          => ['type' => 'string', 'description' => 'Block ID (alphanumeric, hyphens, underscores).'],
            'name'        => ['type' => 'string', 'description' => 'Human-readable block name.'],
            'category'    => ['type' => 'string', 'description' => 'Category: structure, content, interaction, social-proof, custom.'],
            'scope'       => ['type' => 'string', 'description' => 'Scope: global (same across site), template, page.'],
            'html'        => ['type' => 'string', 'description' => 'HTML template with {{slot_name}} placeholders.'],
            'css'         => ['type' => 'string', 'description' => 'CSS styles for this block (optional).'],
            'js'          => ['type' => 'string', 'description' => 'JavaScript for this block (optional).'],
            'slots'       => ['type' => 'array', 'description' => 'Array of slot definitions: [{name, type, label, required, default}].', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'sample_data' => ['type' => 'object', 'description' => 'Sample data for preview (slot_name => value).', 'additionalProperties' => true],
        ],
        function (array $params, App $app): array {
            $blockManager = new \Klytos\Core\BlockManager($app->getStorage());
            return $blockManager->save($params);
        },
        ['title' => 'Create Block', 'readOnlyHint' => false],
        ['id', 'name', 'html']
    );

    $registry->register(
        'klytos_update_block',
        'Update an existing block. Only provide the fields you want to change.',
        [
            'id'          => ['type' => 'string', 'description' => 'Block ID to update.'],
            'name'        => ['type' => 'string', 'description' => 'New name.'],
            'html'        => ['type' => 'string', 'description' => 'New HTML template.'],
            'css'         => ['type' => 'string', 'description' => 'New CSS.'],
            'js'          => ['type' => 'string', 'description' => 'New JS.'],
            'slots'       => ['type' => 'array', 'description' => 'Updated slot definitions.', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            'status'      => ['type' => 'string', 'description' => 'Status: active or draft.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['id'])) {
                throw new \InvalidArgumentException('id is required.');
            }
            $blockManager = new \Klytos\Core\BlockManager($app->getStorage());
            $existing = $blockManager->get($params['id']);
            $merged = array_merge($existing, array_filter($params, fn($v) => $v !== null));
            return $blockManager->save($merged);
        },
        ['title' => 'Update Block', 'readOnlyHint' => false],
        ['id']
    );

    $registry->register(
        'klytos_get_block',
        'Get a block definition by ID, including its HTML template, slots, and data.',
        [
            'block_id' => ['type' => 'string', 'description' => 'Block ID.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['block_id'])) {
                throw new \InvalidArgumentException('block_id is required.');
            }
            $blockManager = new \Klytos\Core\BlockManager($app->getStorage());
            return $blockManager->get($params['block_id']);
        },
        ['title' => 'Get Block', 'readOnlyHint' => true],
        ['block_id']
    );

    $registry->register(
        'klytos_list_blocks',
        'List all blocks with optional category filter.',
        [
            'category' => ['type' => 'string', 'description' => 'Filter: all, structure, content, interaction, social-proof, custom.'],
            'status'   => ['type' => 'string', 'description' => 'Filter: all, active, draft.'],
        ],
        function (array $params, App $app): array {
            $blockManager = new \Klytos\Core\BlockManager($app->getStorage());
            return $blockManager->list($params['category'] ?? 'all', $params['status'] ?? 'all');
        },
        ['title' => 'List Blocks', 'readOnlyHint' => true]
    );

    $registry->register(
        'klytos_delete_block',
        'Delete a block definition permanently.',
        [
            'block_id' => ['type' => 'string', 'description' => 'Block ID to delete.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['block_id'])) {
                throw new \InvalidArgumentException('block_id is required.');
            }
            $blockManager = new \Klytos\Core\BlockManager($app->getStorage());
            return ['deleted' => $blockManager->delete($params['block_id'])];
        },
        ['title' => 'Delete Block', 'readOnlyHint' => false, 'destructiveHint' => true],
        ['block_id']
    );

    $registry->register(
        'klytos_preview_block',
        'Render a block with provided data and return the HTML output.',
        [
            'block_id' => ['type' => 'string', 'description' => 'Block ID to render.'],
            'data'     => ['type' => 'object', 'description' => 'Slot values to inject (key => value).', 'additionalProperties' => true],
        ],
        function (array $params, App $app): array {
            if (empty($params['block_id'])) {
                throw new \InvalidArgumentException('block_id is required.');
            }
            $blockManager = new \Klytos\Core\BlockManager($app->getStorage());
            $html = $blockManager->render($params['block_id'], $params['data'] ?? []);
            return ['html' => $html];
        },
        ['title' => 'Preview Block', 'readOnlyHint' => true],
        ['block_id']
    );

    $registry->register(
        'klytos_set_global_block_data',
        'Set the global data for a global-scope block (e.g. header, footer). Changes apply to all pages.',
        [
            'block_id' => ['type' => 'string', 'description' => 'Block ID (must be global scope).'],
            'data'     => ['type' => 'object', 'description' => 'Slot values for the global block.', 'additionalProperties' => true],
        ],
        function (array $params, App $app): array {
            if ( empty( $params['block_id'] ) ) {
                throw new \InvalidArgumentException( 'block_id is required.' );
            }
            $blockManager = new \Klytos\Core\BlockManager( $app->getStorage() );
            try {
                return $blockManager->setGlobalData( $params['block_id'], $params['data'] ?? [] );
            } catch ( \RuntimeException $e ) {
                throw new \InvalidArgumentException(
                    "Block '{$params['block_id']}' not found or not global-scope. "
                    . "Use klytos_create_block to create it first, or klytos_list_blocks to see available blocks. "
                    . "Error: " . $e->getMessage()
                );
            }
        },
        ['title' => 'Set Global Block Data', 'readOnlyHint' => false],
        ['block_id', 'data']
    );

    $registry->register(
        'klytos_get_block_slots',
        'Get the editable slot definitions for a block (what data it needs).',
        [
            'block_id' => ['type' => 'string', 'description' => 'Block ID.'],
        ],
        function (array $params, App $app): array {
            if (empty($params['block_id'])) {
                throw new \InvalidArgumentException('block_id is required.');
            }
            $blockManager = new \Klytos\Core\BlockManager($app->getStorage());
            return $blockManager->getSlots($params['block_id']);
        },
        ['title' => 'Get Block Slots', 'readOnlyHint' => true],
        ['block_id']
    );
}
