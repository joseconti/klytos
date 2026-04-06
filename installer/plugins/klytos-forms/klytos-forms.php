<?php

/**
 * Plugin Name: Klytos Forms
 * Plugin URI: https://klytos.io/plugins/klytos-forms
 * Description: Advanced form builder with conditional logic, multi-step, anti-spam, notifications, and full MCP integration. Gravity Forms-style visual editor.
 * Version: 1.0.0
 * Author: Jose Conti
 * Author URI: https://joseconti.com
 * Requires Klytos: 0.20.0
 * Requires PHP: 8.1
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: klytos-forms
 * Domain Path: /lang
 * Premium: false
 */

declare( strict_types=1 );

// ─── Load classes ───────────────────────────────────────────
require_once __DIR__ . '/src/FormConditionalEngine.php';
require_once __DIR__ . '/src/FormManager.php';
require_once __DIR__ . '/src/FormRenderer.php';

// ─── Boot ───────────────────────────────────────────────────

$klytosFormsEngine  = new \KlytosForms\FormConditionalEngine();
$klytosFormsManager = new \KlytosForms\FormManager(
    klytos_storage(),
    $klytosFormsEngine,
    klytos_app()->getAssetManager()
);

$GLOBALS['klytos_forms_manager'] = $klytosFormsManager;

/**
 * Get the FormManager instance.
 */
function klytos_forms(): \KlytosForms\FormManager
{
    return $GLOBALS['klytos_forms_manager'];
}

/**
 * Render a form as HTML.
 */
function klytos_render_form( string $formId ): string
{
    $renderer = new \KlytosForms\FormRenderer( klytos_forms() );
    return $renderer->render( $formId );
}

// ─── Translations ───────────────────────────────────────────
klytos_register_translations( 'klytos-forms', __DIR__ . '/lang' );

// ─── Admin sidebar ──────────────────────────────────────────
klytos_add_filter( 'admin.sidebar_items', function ( array $items ): array {
    $adminPath = \Klytos\Core\Helpers::getBasePath() . 'admin/';

    $items[] = [
        'id'         => 'klytos-forms',
        'title'      => 'Formularios',
        'url'        => $adminPath . 'plugin-page.php?plugin=klytos-forms&page=forms',
        'icon'       => 'fa-solid fa-rectangle-list',
        'position'   => 61,
        'section'    => 'content',
        'capability' => 'site.configure',
        'children'   => [
            [
                'id'    => 'klytos-forms-list',
                'title' => 'Todos los formularios',
                'url'   => $adminPath . 'plugin-page.php?plugin=klytos-forms&page=forms',
            ],
            [
                'id'    => 'klytos-forms-entries',
                'title' => 'Entradas',
                'url'   => $adminPath . 'plugin-page.php?plugin=klytos-forms&page=form-entries',
            ],
        ],
    ];

    return $items;
} );

// ─── Form shortcode in page content ─────────────────────────
klytos_add_filter( 'page.content', function ( string $content ): string {
    return preg_replace_callback(
        '/\{\{form:([a-z0-9\-_]+)\}\}/',
        function ( $matches ) {
            return klytos_render_form( $matches[1] );
        },
        $content
    );
} );

// ─── Public route: form submission ──────────────────────────
klytos_register_route( 'api/forms/submit', [
    'type'     => 'api',
    'method'   => 'POST',
    'auth'     => false,
    'callback' => function ( array $params ): array {
        $formId = $_POST['_form_id'] ?? '';
        if ( empty( $formId ) ) {
            return ['success' => false, 'errors' => ['form' => 'Missing form ID.']];
        }

        $meta = [
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referrer'   => $_SERVER['HTTP_REFERER'] ?? '',
            'page_url'   => $_POST['_page_url'] ?? '',
            'locale'     => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        ];

        return klytos_forms()->submitForm( $formId, $_POST, $_FILES, $meta );
    },
] );

// ─── MCP Tools ──────────────────────────────────────────────
klytos_add_filter( 'mcp.tools_list', function ( array $tools ): array {
    $formTools = [
        // Forms CRUD
        ['name' => 'klytos_forms_create', 'description' => 'Create a new form with fields, notifications, and settings.', 'inputSchema' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string', 'description' => 'Form title'], 'fields' => ['type' => 'array', 'description' => 'Array of field objects'], 'settings' => ['type' => 'object', 'description' => 'Form settings'], 'notifications' => ['type' => 'array', 'description' => 'Notification configs'], 'anti_spam' => ['type' => 'object', 'description' => 'Anti-spam settings']], 'required' => ['title']], 'annotations' => ['title' => 'Create Form', 'readOnlyHint' => false, 'destructiveHint' => false]],
        ['name' => 'klytos_forms_get', 'description' => 'Get a form definition by ID.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string', 'description' => 'Form ID']], 'required' => ['id']], 'annotations' => ['title' => 'Get Form', 'readOnlyHint' => true]],
        ['name' => 'klytos_forms_list', 'description' => 'List all forms, optionally filtered by status.', 'inputSchema' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string', 'description' => 'Filter: active, inactive']]], 'annotations' => ['title' => 'List Forms', 'readOnlyHint' => true]],
        ['name' => 'klytos_forms_update', 'description' => 'Update a form (partial update).', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string', 'description' => 'Form ID'], 'title' => ['type' => 'string'], 'fields' => ['type' => 'array'], 'settings' => ['type' => 'object'], 'notifications' => ['type' => 'array'], 'anti_spam' => ['type' => 'object']], 'required' => ['id']], 'annotations' => ['title' => 'Update Form', 'readOnlyHint' => false]],
        ['name' => 'klytos_forms_delete', 'description' => 'Delete a form. Requires confirm=true.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'delete_entries' => ['type' => 'boolean'], 'confirm' => ['type' => 'boolean']], 'required' => ['id', 'confirm']], 'annotations' => ['title' => 'Delete Form', 'readOnlyHint' => false, 'destructiveHint' => true]],
        ['name' => 'klytos_forms_duplicate', 'description' => 'Duplicate an existing form.', 'inputSchema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string'], 'new_title' => ['type' => 'string']], 'required' => ['id']], 'annotations' => ['title' => 'Duplicate Form', 'readOnlyHint' => false]],
        // Fields
        ['name' => 'klytos_forms_add_field', 'description' => 'Add a field to a form.', 'inputSchema' => ['type' => 'object', 'properties' => ['form_id' => ['type' => 'string'], 'field' => ['type' => 'object', 'description' => 'Field definition'], 'position' => ['type' => 'integer']], 'required' => ['form_id', 'field']], 'annotations' => ['title' => 'Add Field', 'readOnlyHint' => false]],
        ['name' => 'klytos_forms_update_field', 'description' => 'Update a field in a form.', 'inputSchema' => ['type' => 'object', 'properties' => ['form_id' => ['type' => 'string'], 'field_id' => ['type' => 'string'], 'updates' => ['type' => 'object']], 'required' => ['form_id', 'field_id', 'updates']], 'annotations' => ['title' => 'Update Field', 'readOnlyHint' => false]],
        ['name' => 'klytos_forms_remove_field', 'description' => 'Remove a field from a form.', 'inputSchema' => ['type' => 'object', 'properties' => ['form_id' => ['type' => 'string'], 'field_id' => ['type' => 'string']], 'required' => ['form_id', 'field_id']], 'annotations' => ['title' => 'Remove Field', 'readOnlyHint' => false, 'destructiveHint' => true]],
        ['name' => 'klytos_forms_reorder_fields', 'description' => 'Reorder fields by providing IDs in order.', 'inputSchema' => ['type' => 'object', 'properties' => ['form_id' => ['type' => 'string'], 'order' => ['type' => 'array']], 'required' => ['form_id', 'order']], 'annotations' => ['title' => 'Reorder Fields', 'readOnlyHint' => false]],
        // Entries
        ['name' => 'klytos_forms_list_entries', 'description' => 'List form entries with filters.', 'inputSchema' => ['type' => 'object', 'properties' => ['form_id' => ['type' => 'string'], 'status' => ['type' => 'string'], 'search' => ['type' => 'string'], 'page' => ['type' => 'integer'], 'per_page' => ['type' => 'integer']], 'required' => ['form_id']], 'annotations' => ['title' => 'List Entries', 'readOnlyHint' => true]],
        ['name' => 'klytos_forms_get_entry', 'description' => 'Get a specific entry.', 'inputSchema' => ['type' => 'object', 'properties' => ['entry_id' => ['type' => 'string']], 'required' => ['entry_id']], 'annotations' => ['title' => 'Get Entry', 'readOnlyHint' => true]],
        ['name' => 'klytos_forms_update_entry_status', 'description' => 'Update entry status.', 'inputSchema' => ['type' => 'object', 'properties' => ['entry_id' => ['type' => 'string'], 'status' => ['type' => 'string']], 'required' => ['entry_id', 'status']], 'annotations' => ['title' => 'Update Entry Status', 'readOnlyHint' => false]],
        ['name' => 'klytos_forms_delete_entry', 'description' => 'Delete an entry. Requires confirm=true.', 'inputSchema' => ['type' => 'object', 'properties' => ['entry_id' => ['type' => 'string'], 'confirm' => ['type' => 'boolean']], 'required' => ['entry_id', 'confirm']], 'annotations' => ['title' => 'Delete Entry', 'readOnlyHint' => false, 'destructiveHint' => true]],
        ['name' => 'klytos_forms_export_entries', 'description' => 'Export entries as CSV or JSON.', 'inputSchema' => ['type' => 'object', 'properties' => ['form_id' => ['type' => 'string'], 'format' => ['type' => 'string']], 'required' => ['form_id']], 'annotations' => ['title' => 'Export Entries', 'readOnlyHint' => true]],
        ['name' => 'klytos_forms_stats', 'description' => 'Get form statistics.', 'inputSchema' => ['type' => 'object', 'properties' => ['form_id' => ['type' => 'string']], 'required' => ['form_id']], 'annotations' => ['title' => 'Form Stats', 'readOnlyHint' => true]],
    ];

    return array_merge( $tools, $formTools );
} );

// ─── MCP Tool Handler ───────────────────────────────────────
klytos_add_filter( 'mcp.handle_tool', function ( mixed $result, string $toolName, array $params ): mixed {
    $fm = $GLOBALS['klytos_forms_manager'] ?? null;
    if ( !$fm ) return $result;

    $handlers = [
        'klytos_forms_create'  => function ( $p ) use ( $fm ) { $f = $fm->createForm( $p ); return ['form' => $f, 'shortcode' => '{{form:' . $f['id'] . '}}']; },
        'klytos_forms_get'     => fn( $p ) => ($f = $fm->getForm( $p['id'] ?? '' )) ? ['form' => $f] : ['error' => 'Not found'],
        'klytos_forms_list'    => fn( $p ) => ['forms' => $fm->listForms( $p['status'] ?? null )],
        'klytos_forms_update'  => function ( $p ) use ( $fm ) { $id = $p['id']; unset( $p['id'] ); return ['form' => $fm->updateForm( $id, $p )]; },
        'klytos_forms_delete'  => fn( $p ) => ( $p['confirm'] ?? false ) ? ['success' => $fm->deleteForm( $p['id'] ?? '', (bool) ( $p['delete_entries'] ?? false ) )] : ['error' => 'Confirm required'],
        'klytos_forms_duplicate' => fn( $p ) => ['form' => $fm->duplicateForm( $p['id'] ?? '', $p['new_title'] ?? null )],
        'klytos_forms_add_field' => fn( $p ) => ['field' => $fm->addField( $p['form_id'] ?? '', $p['field'] ?? [], isset( $p['position'] ) ? (int) $p['position'] : null )],
        'klytos_forms_update_field' => fn( $p ) => ['form' => $fm->updateField( $p['form_id'] ?? '', $p['field_id'] ?? '', $p['updates'] ?? [] )],
        'klytos_forms_remove_field' => fn( $p ) => ['success' => $fm->removeField( $p['form_id'] ?? '', $p['field_id'] ?? '' )],
        'klytos_forms_reorder_fields' => fn( $p ) => ['form' => $fm->reorderFields( $p['form_id'] ?? '', $p['order'] ?? [] )],
        'klytos_forms_list_entries' => function ( $p ) use ( $fm ) { $fid = $p['form_id'] ?? ''; unset( $p['form_id'] ); return $fm->listEntries( $fid, $p ); },
        'klytos_forms_get_entry' => fn( $p ) => ($e = $fm->getEntry( $p['entry_id'] ?? '' )) ? ['entry' => $e] : ['error' => 'Not found'],
        'klytos_forms_update_entry_status' => fn( $p ) => ['success' => $fm->updateEntryStatus( $p['entry_id'] ?? '', $p['status'] ?? '' )],
        'klytos_forms_delete_entry' => fn( $p ) => ( $p['confirm'] ?? false ) ? ['success' => $fm->deleteEntry( $p['entry_id'] ?? '' )] : ['error' => 'Confirm required'],
        'klytos_forms_export_entries' => fn( $p ) => ['data' => $fm->exportEntries( $p['form_id'] ?? '', $p['format'] ?? 'csv' )],
        'klytos_forms_stats' => fn( $p ) => ['stats' => $fm->getFormStats( $p['form_id'] ?? '' )],
    ];

    if ( !isset( $handlers[$toolName] ) ) return $result;

    try {
        $data = $handlers[$toolName]( $params );
        return [
            'content' => [['type' => 'text', 'text' => json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )]],
            'isError' => false,
        ];
    } catch ( \Throwable $e ) {
        return [
            'content' => [['type' => 'text', 'text' => json_encode( ['error' => $e->getMessage()] )]],
            'isError' => true,
        ];
    }
}, 10 );
