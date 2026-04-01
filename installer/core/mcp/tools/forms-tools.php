<?php

/**
 * Klytos — MCP Form Tools
 * AI-first form management: create, modify, query forms, fields, and entries via MCP.
 *
 * 16 tools: 6 for forms, 4 for fields, 6 for entries/stats.
 *
 * @package Klytos
 * @since   0.20.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

namespace Klytos\Core\MCP\Tools;

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

function registerFormsTools( ToolRegistry $registry ): void
{
    // ─── Form CRUD ──────────────────────────────────────────

    $registry->register(
        'klytos_forms_create',
        'Create a new form with fields, notifications, and settings. Provide the complete form definition.',
        [
            'title'         => ['type' => 'string', 'description' => 'Form title (required)'],
            'description'   => ['type' => 'string', 'description' => 'Form description'],
            'id'            => ['type' => 'string', 'description' => 'Form ID (auto-generated from title if omitted)'],
            'fields'        => ['type' => 'array', 'description' => 'Array of field objects: {type, label, id?, required?, placeholder?, options?, validation?, conditional?, step?, css_class?}'],
            'settings'      => ['type' => 'object', 'description' => 'Form settings: {submit_label, success_message, success_action, success_redirect, enable_ajax, css_class, layout, steps[]}'],
            'notifications' => ['type' => 'array', 'description' => 'Array of notification objects: {id, name, enabled, to, reply_to, subject, body, format, conditional?}'],
            'anti_spam'     => ['type' => 'object', 'description' => 'Anti-spam settings: {honeypot, rate_limit, rate_limit_window}'],
        ],
        function ( array $params, App $app ): array {
            $form = $app->getFormManager()->createForm( $params );
            return ['success' => true, 'form' => $form, 'shortcode' => '{{form:' . $form['id'] . '}}'];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false],
        ['title']
    );

    $registry->register(
        'klytos_forms_get',
        'Get a form definition by ID, including all fields, settings, and notifications.',
        [
            'id' => ['type' => 'string', 'description' => 'Form ID'],
        ],
        function ( array $params, App $app ): array {
            $form = $app->getFormManager()->getForm( $params['id'] ?? '' );
            if ( !$form ) return ['error' => 'Form not found.'];
            return ['success' => true, 'form' => $form];
        },
        ['readOnlyHint' => true],
        ['id']
    );

    $registry->register(
        'klytos_forms_list',
        'List all forms, optionally filtered by status (active/inactive).',
        [
            'status' => ['type' => 'string', 'description' => 'Filter by status: active, inactive. Omit for all.'],
        ],
        function ( array $params, App $app ): array {
            $forms = $app->getFormManager()->listForms( $params['status'] ?? null );
            return ['success' => true, 'count' => count( $forms ), 'forms' => $forms];
        },
        ['readOnlyHint' => true]
    );

    $registry->register(
        'klytos_forms_update',
        'Update a form. Only provided fields are updated (partial update). Use "fields" to replace all fields.',
        [
            'id'            => ['type' => 'string', 'description' => 'Form ID (required)'],
            'title'         => ['type' => 'string', 'description' => 'New title'],
            'description'   => ['type' => 'string', 'description' => 'New description'],
            'status'        => ['type' => 'string', 'description' => 'New status: active or inactive'],
            'fields'        => ['type' => 'array', 'description' => 'Replace all fields with this array'],
            'settings'      => ['type' => 'object', 'description' => 'Merge with existing settings'],
            'notifications' => ['type' => 'array', 'description' => 'Replace all notifications'],
            'anti_spam'     => ['type' => 'object', 'description' => 'Merge with existing anti-spam settings'],
        ],
        function ( array $params, App $app ): array {
            $id = $params['id'] ?? '';
            unset( $params['id'] );
            $form = $app->getFormManager()->updateForm( $id, $params );
            return ['success' => true, 'form' => $form];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
        ['id']
    );

    $registry->register(
        'klytos_forms_delete',
        'Delete a form and optionally all its entries. Requires confirm=true.',
        [
            'id'             => ['type' => 'string', 'description' => 'Form ID'],
            'delete_entries' => ['type' => 'boolean', 'description' => 'Also delete all entries (default false)'],
            'confirm'        => ['type' => 'boolean', 'description' => 'Must be true to confirm deletion'],
        ],
        function ( array $params, App $app ): array {
            if ( !( $params['confirm'] ?? false ) ) return ['error' => 'Set confirm=true to delete.'];
            $deleted = $app->getFormManager()->deleteForm(
                $params['id'] ?? '',
                (bool) ( $params['delete_entries'] ?? false )
            );
            return ['success' => $deleted];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
        ['id', 'confirm']
    );

    $registry->register(
        'klytos_forms_duplicate',
        'Duplicate an existing form with a new title.',
        [
            'id'        => ['type' => 'string', 'description' => 'Form ID to duplicate'],
            'new_title' => ['type' => 'string', 'description' => 'Title for the copy (optional)'],
        ],
        function ( array $params, App $app ): array {
            $form = $app->getFormManager()->duplicateForm( $params['id'] ?? '', $params['new_title'] ?? null );
            return ['success' => true, 'form' => $form];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false],
        ['id']
    );

    // ─── Field Management ───────────────────────────────────

    $registry->register(
        'klytos_forms_add_field',
        'Add a new field to an existing form. Provide the field definition as an object.',
        [
            'form_id'  => ['type' => 'string', 'description' => 'Form ID'],
            'field'    => ['type' => 'object', 'description' => 'Field object: {type, label, id?, required?, placeholder?, options?, validation?, conditional?, step?, css_class?}'],
            'position' => ['type' => 'integer', 'description' => 'Insert at this position (optional, appends by default)'],
        ],
        function ( array $params, App $app ): array {
            $field = $app->getFormManager()->addField(
                $params['form_id'] ?? '',
                $params['field'] ?? [],
                isset( $params['position'] ) ? (int) $params['position'] : null
            );
            return ['success' => true, 'field' => $field];
        },
        ['readOnlyHint' => false],
        ['form_id', 'field']
    );

    $registry->register(
        'klytos_forms_update_field',
        'Update an existing field in a form. Only provided properties are updated.',
        [
            'form_id'  => ['type' => 'string', 'description' => 'Form ID'],
            'field_id' => ['type' => 'string', 'description' => 'Field ID to update'],
            'updates'  => ['type' => 'object', 'description' => 'Properties to update (label, required, placeholder, validation, conditional, etc.)'],
        ],
        function ( array $params, App $app ): array {
            $form = $app->getFormManager()->updateField(
                $params['form_id'] ?? '',
                $params['field_id'] ?? '',
                $params['updates'] ?? []
            );
            return ['success' => true, 'form' => $form];
        },
        ['readOnlyHint' => false],
        ['form_id', 'field_id', 'updates']
    );

    $registry->register(
        'klytos_forms_remove_field',
        'Remove a field from a form. Also cleans up conditional rules referencing this field.',
        [
            'form_id'  => ['type' => 'string', 'description' => 'Form ID'],
            'field_id' => ['type' => 'string', 'description' => 'Field ID to remove'],
        ],
        function ( array $params, App $app ): array {
            $removed = $app->getFormManager()->removeField( $params['form_id'] ?? '', $params['field_id'] ?? '' );
            return ['success' => $removed];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true],
        ['form_id', 'field_id']
    );

    $registry->register(
        'klytos_forms_reorder_fields',
        'Reorder fields in a form by providing field IDs in desired order.',
        [
            'form_id' => ['type' => 'string', 'description' => 'Form ID'],
            'order'   => ['type' => 'array', 'description' => 'Array of field ID strings in desired order'],
        ],
        function ( array $params, App $app ): array {
            $form = $app->getFormManager()->reorderFields( $params['form_id'] ?? '', $params['order'] ?? [] );
            return ['success' => true, 'form' => $form];
        },
        ['readOnlyHint' => false],
        ['form_id', 'order']
    );

    // ─── Entries ────────────────────────────────────────────

    $registry->register(
        'klytos_forms_list_entries',
        'List form entries with filters, pagination, and search.',
        [
            'form_id'   => ['type' => 'string', 'description' => 'Form ID'],
            'status'    => ['type' => 'string', 'description' => 'Filter: unread, read, starred, trash'],
            'search'    => ['type' => 'string', 'description' => 'Search in entry data'],
            'date_from' => ['type' => 'string', 'description' => 'ISO date start'],
            'date_to'   => ['type' => 'string', 'description' => 'ISO date end'],
            'page'      => ['type' => 'integer', 'description' => 'Page number (default 1)'],
            'per_page'  => ['type' => 'integer', 'description' => 'Entries per page (default 20)'],
        ],
        function ( array $params, App $app ): array {
            $formId = $params['form_id'] ?? '';
            unset( $params['form_id'] );
            $result = $app->getFormManager()->listEntries( $formId, $params );
            return ['success' => true] + $result;
        },
        ['readOnlyHint' => true],
        ['form_id']
    );

    $registry->register(
        'klytos_forms_get_entry',
        'Get a specific form entry by ID with all data and metadata.',
        [
            'entry_id' => ['type' => 'string', 'description' => 'Entry ID'],
        ],
        function ( array $params, App $app ): array {
            $entry = $app->getFormManager()->getEntry( $params['entry_id'] ?? '' );
            if ( !$entry ) return ['error' => 'Entry not found.'];
            return ['success' => true, 'entry' => $entry];
        },
        ['readOnlyHint' => true],
        ['entry_id']
    );

    $registry->register(
        'klytos_forms_update_entry_status',
        'Update the status of a form entry (unread, read, starred, trash).',
        [
            'entry_id' => ['type' => 'string', 'description' => 'Entry ID'],
            'status'   => ['type' => 'string', 'description' => 'New status: unread, read, starred, trash'],
        ],
        function ( array $params, App $app ): array {
            $updated = $app->getFormManager()->updateEntryStatus( $params['entry_id'] ?? '', $params['status'] ?? '' );
            return ['success' => $updated];
        },
        ['readOnlyHint' => false],
        ['entry_id', 'status']
    );

    $registry->register(
        'klytos_forms_delete_entry',
        'Permanently delete a form entry. Requires confirm=true.',
        [
            'entry_id' => ['type' => 'string', 'description' => 'Entry ID'],
            'confirm'  => ['type' => 'boolean', 'description' => 'Must be true to confirm deletion'],
        ],
        function ( array $params, App $app ): array {
            if ( !( $params['confirm'] ?? false ) ) return ['error' => 'Set confirm=true to delete.'];
            $deleted = $app->getFormManager()->deleteEntry( $params['entry_id'] ?? '' );
            return ['success' => $deleted];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true],
        ['entry_id', 'confirm']
    );

    $registry->register(
        'klytos_forms_export_entries',
        'Export all entries of a form as CSV or JSON text.',
        [
            'form_id' => ['type' => 'string', 'description' => 'Form ID'],
            'format'  => ['type' => 'string', 'description' => 'Export format: csv or json (default csv)'],
        ],
        function ( array $params, App $app ): array {
            $data = $app->getFormManager()->exportEntries( $params['form_id'] ?? '', $params['format'] ?? 'csv' );
            return ['success' => true, 'format' => $params['format'] ?? 'csv', 'data' => $data];
        },
        ['readOnlyHint' => true],
        ['form_id']
    );

    $registry->register(
        'klytos_forms_stats',
        'Get statistics for a form: total entries, unread, by period, etc.',
        [
            'form_id' => ['type' => 'string', 'description' => 'Form ID'],
        ],
        function ( array $params, App $app ): array {
            $stats = $app->getFormManager()->getFormStats( $params['form_id'] ?? '' );
            return ['success' => true, 'stats' => $stats];
        },
        ['readOnlyHint' => true],
        ['form_id']
    );
}
