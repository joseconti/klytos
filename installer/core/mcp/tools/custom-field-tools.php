<?php

/**
 * Klytos — MCP Custom Field Tools
 * CRUD operations for custom field definitions and field values via MCP.
 *
 * Custom fields are stored inline within post type definitions (like taxonomies).
 * Field values are stored in the page/entry '_meta' field via MetaManager,
 * namespaced under 'cf.' prefix (e.g. 'cf.price', 'cf.color').
 *
 * @package Klytos
 * @since   0.7.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;
use Klytos\Core\PostTypeManager;

function registerCustomFieldTools(ToolRegistry $registry, App $app): void
{
    // ─── klytos_get_field_types ─────────────────────────────────
    $registry->register(
        'klytos_get_field_types',
        'List all 27 supported custom field types with their descriptions, categories, and available validation rules. Use this tool when the administrator asks what types of fields are available, or before creating custom fields. Each type includes: label, category (text/number/datetime/choice/media/data/advanced), description explaining when to use it, and available validation rules. You SHOULD present the types grouped by category and explain each one in plain language so the administrator can choose the right type for their data.',
        [],
        function (array $params, App $app): array {
            return [
                'success'     => true,
                'field_types' => PostTypeManager::getFieldTypes(),
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    // ─── klytos_add_custom_field ────────────────────────────────
    $registry->register(
        'klytos_add_custom_field',
        'Add a custom field definition to a specific post type. Custom fields define the data schema for entries of that post type. The field ID must be unique within the post type. IMPORTANT WORKFLOW: When adding a custom field, you MUST: (1) Ask the administrator for the field ID, label, and type. If they are unsure about the type, call klytos_get_field_types first and explain the options. (2) Ask whether the field should be REQUIRED (required=true) or optional (required=false). Explain that required fields must be filled when creating entries. (3) For choice types (select, multiselect, radio, checkbox_group), ask for the list of options (value/label pairs). (4) For number/range, ask about min/max/step validation rules. Supported types: text, textarea, richtext, code, password, number, range, date, datetime, time, select, multiselect, checkbox, checkbox_group, radio, toggle, image, file, gallery, email, url, phone, color, json, repeater, relationship. Read the "post-types-and-fields" guide for complete documentation.',
        [
            'post_type_id'  => ['type' => 'string', 'description' => 'The post type ID to add the custom field to (e.g. "products", "portfolio").'],
            'id'            => ['type' => 'string', 'description' => 'Unique machine name for the field (lowercase, underscores/hyphens). E.g.: "price", "featured_image", "release_date".'],
            'type'          => ['type' => 'string', 'description' => 'Field type. Use klytos_get_field_types to see all available types.'],
            'label'         => ['type' => 'string', 'description' => 'Human-readable label shown in the editor. E.g.: "Price", "Featured Image".'],
            'description'   => ['type' => 'string', 'description' => 'Help text shown below the field in the editor.'],
            'placeholder'   => ['type' => 'string', 'description' => 'Placeholder text for text-based inputs.'],
            'default_value' => ['type' => 'string', 'description' => 'Default value for new entries. JSON-encoded for complex types.'],
            'required'      => ['type' => 'boolean', 'description' => 'Whether this field is required. Default: false.'],
            'position'      => ['type' => 'integer', 'description' => 'Sort order (lower = first). Auto-assigned if omitted.'],
            'options'       => [
                'type'        => 'array',
                'description' => 'Options for select, multiselect, radio, checkbox_group fields. Each option needs value and label.',
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'value' => ['type' => 'string', 'description' => 'Option value stored in the database.'],
                        'label' => ['type' => 'string', 'description' => 'Option label shown to the user.'],
                    ],
                ],
            ],
            'validation'    => [
                'type'                 => 'object',
                'description'          => 'Validation rules specific to the field type. E.g.: {"min": 0, "max": 999, "step": 0.01} for number, {"min_length": 3, "max_length": 100} for text.',
                'additionalProperties' => true,
            ],
            'sub_fields'    => [
                'type'        => 'array',
                'description' => 'Sub-field definitions for repeater type. Each sub-field has: id, type, label, and optional description, placeholder, options, validation.',
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'id'          => ['type' => 'string'],
                        'type'        => ['type' => 'string'],
                        'label'       => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'placeholder' => ['type' => 'string'],
                        'required'    => ['type' => 'boolean'],
                        'options'     => ['type' => 'array'],
                        'validation'  => ['type' => 'object', 'additionalProperties' => true],
                    ],
                ],
            ],
            'relationship'  => [
                'type'                 => 'object',
                'description'          => 'Configuration for relationship type. Specifies which post types can be referenced.',
                'additionalProperties' => true,
                'properties'           => [
                    'post_types' => ['type' => 'array', 'description' => 'Array of post type IDs that can be referenced.', 'items' => ['type' => 'string']],
                    'multiple'   => ['type' => 'boolean', 'description' => 'Allow multiple references. Default: true.'],
                    'max'        => ['type' => 'integer', 'description' => 'Maximum number of references allowed.'],
                ],
            ],
        ],
        function (array $params, App $app): array {
            $postTypeId = $params['post_type_id'] ?? '';
            unset($params['post_type_id']);
            $postType = $app->getPostTypeManager()->addCustomField($postTypeId, $params);
            return [
                'success'   => true,
                'post_type' => $postType,
                'message'   => "Custom field '{$params['id']}' added to post type '{$postTypeId}'.",
            ];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false],
        ['post_type_id', 'id', 'type', 'label']
    );

    // ─── klytos_update_custom_field ─────────────────────────────
    $registry->register(
        'klytos_update_custom_field',
        'Update an existing custom field definition within a post type. Only provided fields will be changed. You can change the type, label, description, placeholder, default_value, required, position, options, validation, sub_fields, or relationship.',
        [
            'post_type_id'  => ['type' => 'string', 'description' => 'The post type ID that contains the field.'],
            'field_id'      => ['type' => 'string', 'description' => 'The custom field ID to update.'],
            'type'          => ['type' => 'string', 'description' => 'New field type.'],
            'label'         => ['type' => 'string', 'description' => 'New human-readable label.'],
            'description'   => ['type' => 'string', 'description' => 'New help text.'],
            'placeholder'   => ['type' => 'string', 'description' => 'New placeholder text.'],
            'default_value' => ['type' => 'string', 'description' => 'New default value.'],
            'required'      => ['type' => 'boolean', 'description' => 'Whether this field is required.'],
            'position'      => ['type' => 'integer', 'description' => 'New sort order.'],
            'options'       => [
                'type'        => 'array',
                'description' => 'New options for select/radio/checkbox_group fields.',
                'items'       => ['type' => 'object'],
            ],
            'validation'    => [
                'type'                 => 'object',
                'description'          => 'New validation rules.',
                'additionalProperties' => true,
            ],
            'sub_fields'    => [
                'type'        => 'array',
                'description' => 'New sub-field definitions for repeater type.',
                'items'       => ['type' => 'object'],
            ],
            'relationship'  => [
                'type'                 => 'object',
                'description'          => 'New relationship configuration.',
                'additionalProperties' => true,
            ],
        ],
        function (array $params, App $app): array {
            $postTypeId = $params['post_type_id'] ?? '';
            $fieldId    = $params['field_id'] ?? '';
            unset($params['post_type_id'], $params['field_id']);
            $postType = $app->getPostTypeManager()->updateCustomField($postTypeId, $fieldId, $params);
            return [
                'success'   => true,
                'post_type' => $postType,
                'message'   => "Custom field '{$fieldId}' updated in post type '{$postTypeId}'.",
            ];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['post_type_id', 'field_id']
    );

    // ─── klytos_remove_custom_field ─────────────────────────────
    $registry->register(
        'klytos_remove_custom_field',
        'Remove a custom field definition from a post type. Existing field values on entries are preserved in _meta but no longer displayed or validated.',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type ID that contains the field.'],
            'field_id'     => ['type' => 'string', 'description' => 'The custom field ID to remove.'],
        ],
        function (array $params, App $app): array {
            $postTypeId = $params['post_type_id'] ?? '';
            $fieldId    = $params['field_id'] ?? '';
            $postType   = $app->getPostTypeManager()->removeCustomField($postTypeId, $fieldId);
            return [
                'success'   => true,
                'post_type' => $postType,
                'message'   => "Custom field '{$fieldId}' removed from post type '{$postTypeId}'.",
            ];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['post_type_id', 'field_id']
    );

    // ─── klytos_get_custom_field ────────────────────────────────
    $registry->register(
        'klytos_get_custom_field',
        'Get a single custom field definition by ID from a post type. Returns the field type, label, description, options, validation rules, and all other configuration.',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type ID that contains the field.'],
            'field_id'     => ['type' => 'string', 'description' => 'The custom field ID to retrieve.'],
        ],
        function (array $params, App $app): array {
            $field = $app->getPostTypeManager()->getCustomField(
                $params['post_type_id'] ?? '',
                $params['field_id'] ?? ''
            );
            return ['success' => true, 'field' => $field];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['post_type_id', 'field_id']
    );

    // ─── klytos_list_custom_fields ──────────────────────────────
    $registry->register(
        'klytos_list_custom_fields',
        'List all custom field definitions for a specific post type, ordered by position. Returns field IDs, types, labels, and all configuration for each field.',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type ID to list custom fields for.'],
        ],
        function (array $params, App $app): array {
            $fields = $app->getPostTypeManager()->listCustomFields($params['post_type_id'] ?? '');
            return [
                'success' => true,
                'fields'  => $fields,
                'total'   => count($fields),
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['post_type_id']
    );

    // ─── klytos_reorder_custom_fields ───────────────────────────
    $registry->register(
        'klytos_reorder_custom_fields',
        'Reorder custom fields within a post type. Pass an array of field IDs in the desired display order. All field IDs must be present.',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type ID that contains the fields.'],
            'field_ids'    => [
                'type'        => 'array',
                'description' => 'Ordered array of field IDs representing the desired display order.',
                'items'       => ['type' => 'string'],
            ],
        ],
        function (array $params, App $app): array {
            $postType = $app->getPostTypeManager()->reorderCustomFields(
                $params['post_type_id'] ?? '',
                $params['field_ids'] ?? []
            );
            return [
                'success'   => true,
                'post_type' => $postType,
                'message'   => 'Custom fields reordered successfully.',
            ];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
        ['post_type_id', 'field_ids']
    );

    // ─── klytos_set_field_value ─────────────────────────────────
    $registry->register(
        'klytos_set_field_value',
        'Set the value of a custom field on a page/entry. The value is validated against the field definition (type, required, options, min/max, etc.) before saving. The post_type is inferred from the entry unless post_type_id is provided.',
        [
            'page_slug'    => ['type' => 'string', 'description' => 'The page/entry slug to set the field value on (e.g. "blue-shirt" or "products/blue-shirt").'],
            'field_id'     => ['type' => 'string', 'description' => 'The custom field ID to set (e.g. "price", "color").'],
            'value'        => ['description' => 'The value to set. Type depends on the field definition (string, number, boolean, array, object).'],
            'post_type_id' => ['type' => 'string', 'description' => 'Optional: explicit post type ID. If omitted, the post type is inferred from the entry.'],
        ],
        function (array $params, App $app): array {
            $pageSlug    = $params['page_slug'] ?? '';
            $fieldId     = $params['field_id'] ?? '';
            $value       = $params['value'] ?? null;
            $postTypeId  = $params['post_type_id'] ?? '';

            if (empty($pageSlug)) {
                throw new \InvalidArgumentException('page_slug is required.');
            }
            if (empty($fieldId)) {
                throw new \InvalidArgumentException('field_id is required.');
            }

            // Determine post type from entry if not provided.
            if (empty($postTypeId)) {
                $page       = $app->getPages()->get($pageSlug);
                $postTypeId = $page['post_type'] ?? 'page';
            }

            // Get field definition and validate.
            $fieldDef = $app->getPostTypeManager()->getCustomField($postTypeId, $fieldId);
            $value    = $app->getPostTypeManager()->validateFieldValue($fieldDef, $value);

            // Save via MetaManager.
            $app->getMetaManager()->set('pages', $pageSlug, 'cf.' . $fieldId, $value);

            return [
                'success'    => true,
                'page_slug'  => $pageSlug,
                'field_id'   => $fieldId,
                'value'      => $value,
                'message'    => "Field '{$fieldId}' set on entry '{$pageSlug}'.",
            ];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
        ['page_slug', 'field_id', 'value']
    );

    // ─── klytos_get_field_value ─────────────────────────────────
    $registry->register(
        'klytos_get_field_value',
        'Get the current value of a custom field from a page/entry. Returns null if the field has no value set.',
        [
            'page_slug'    => ['type' => 'string', 'description' => 'The page/entry slug.'],
            'field_id'     => ['type' => 'string', 'description' => 'The custom field ID to retrieve.'],
            'post_type_id' => ['type' => 'string', 'description' => 'Optional: explicit post type ID for returning field definition alongside value.'],
        ],
        function (array $params, App $app): array {
            $pageSlug = $params['page_slug'] ?? '';
            $fieldId  = $params['field_id'] ?? '';

            $value = $app->getMetaManager()->get('pages', $pageSlug, 'cf.' . $fieldId);

            $result = [
                'success'   => true,
                'page_slug' => $pageSlug,
                'field_id'  => $fieldId,
                'value'     => $value,
            ];

            // Include field definition if post_type_id provided.
            $postTypeId = $params['post_type_id'] ?? '';
            if (!empty($postTypeId)) {
                try {
                    $result['field_definition'] = $app->getPostTypeManager()->getCustomField($postTypeId, $fieldId);
                } catch (\Throwable $e) {
                    // Field definition not found, skip.
                }
            }

            return $result;
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['page_slug', 'field_id']
    );

    // ─── klytos_get_all_field_values ────────────────────────────
    $registry->register(
        'klytos_get_all_field_values',
        'Get all custom field values for a page/entry, along with the field definitions from its post type. Useful for getting a complete picture of an entry\'s custom data.',
        [
            'page_slug'    => ['type' => 'string', 'description' => 'The page/entry slug.'],
            'post_type_id' => ['type' => 'string', 'description' => 'Optional: explicit post type ID. If omitted, inferred from the entry.'],
        ],
        function (array $params, App $app): array {
            $pageSlug   = $params['page_slug'] ?? '';
            $postTypeId = $params['post_type_id'] ?? '';

            if (empty($pageSlug)) {
                throw new \InvalidArgumentException('page_slug is required.');
            }

            // Determine post type.
            if (empty($postTypeId)) {
                $page       = $app->getPages()->get($pageSlug);
                $postTypeId = $page['post_type'] ?? 'page';
            }

            // Get field definitions.
            $fieldDefs = $app->getPostTypeManager()->listCustomFields($postTypeId);

            // Get all meta and filter to cf.* keys.
            $allMeta = $app->getMetaManager()->getAll('pages', $pageSlug);
            $values  = [];
            foreach ($allMeta as $key => $val) {
                if (str_starts_with($key, 'cf.')) {
                    $values[substr($key, 3)] = $val;
                }
            }

            // Build merged result with definitions and current values.
            $fields = [];
            foreach ($fieldDefs as $def) {
                $fid       = $def['id'] ?? '';
                $fields[] = [
                    'definition'    => $def,
                    'current_value' => $values[$fid] ?? $def['default_value'] ?? null,
                    'has_value'     => isset($values[$fid]),
                ];
            }

            return [
                'success'      => true,
                'page_slug'    => $pageSlug,
                'post_type_id' => $postTypeId,
                'fields'       => $fields,
                'total'        => count($fields),
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['page_slug']
    );

    // ─── klytos_set_bulk_field_values ───────────────────────────
    $registry->register(
        'klytos_set_bulk_field_values',
        'Set multiple custom field values on a page/entry at once. Each field is validated against its definition. Useful for populating all fields in a single call. Pass fields as an object with field_id keys and their values.',
        [
            'page_slug'    => ['type' => 'string', 'description' => 'The page/entry slug.'],
            'fields'       => [
                'type'                 => 'object',
                'description'          => 'Object with field IDs as keys and values. E.g.: {"price": 29.99, "color": "#ff0000", "featured": true}.',
                'additionalProperties' => true,
            ],
            'post_type_id' => ['type' => 'string', 'description' => 'Optional: explicit post type ID. If omitted, inferred from the entry.'],
        ],
        function (array $params, App $app): array {
            $pageSlug   = $params['page_slug'] ?? '';
            $fields     = $params['fields'] ?? [];
            $postTypeId = $params['post_type_id'] ?? '';

            if (empty($pageSlug)) {
                throw new \InvalidArgumentException('page_slug is required.');
            }
            if (empty($fields) || !is_array($fields)) {
                throw new \InvalidArgumentException('fields must be a non-empty object.');
            }

            // Determine post type.
            if (empty($postTypeId)) {
                $page       = $app->getPages()->get($pageSlug);
                $postTypeId = $page['post_type'] ?? 'page';
            }

            $ptManager = $app->getPostTypeManager();
            $meta      = $app->getMetaManager();
            $saved     = [];
            $errors    = [];

            foreach ($fields as $fieldId => $value) {
                try {
                    $fieldDef = $ptManager->getCustomField($postTypeId, $fieldId);
                    $value    = $ptManager->validateFieldValue($fieldDef, $value);
                    $meta->set('pages', $pageSlug, 'cf.' . $fieldId, $value);
                    $saved[$fieldId] = $value;
                } catch (\Throwable $e) {
                    $errors[$fieldId] = $e->getMessage();
                }
            }

            $result = [
                'success'   => empty($errors),
                'page_slug' => $pageSlug,
                'saved'     => $saved,
                'total'     => count($saved),
            ];

            if (!empty($errors)) {
                $result['errors']  = $errors;
                $result['message'] = count($errors) . ' field(s) had validation errors.';
            } else {
                $result['message'] = count($saved) . ' field(s) saved successfully.';
            }

            return $result;
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
        ['page_slug', 'fields']
    );
}
