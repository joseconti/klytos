<?php

/**
 * Klytos Admin API — Forms Management Endpoint
 * Handles all form operations: CRUD forms, fields, entries, stats, export.
 *
 * JSON actions (POST/GET with JSON body or query params + X-CSRF-Token header):
 *   Forms:   list, get, create, update, delete, duplicate
 *   Fields:  add_field, update_field, remove_field, reorder_fields
 *   Entries: entries, entry, update_entry_status, add_note, delete_entry, delete_entries, export
 *   Stats:   stats
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

require_once dirname( __DIR__ ) . '/bootstrap.php';

use Klytos\Core\Helpers;

header( 'Content-Type: application/json; charset=utf-8' );

// ─── Authentication ──────────────────────────────────────────
if ( !$app->getAuth()->isAuthenticated() ) {
    Helpers::jsonResponse( ['error' => 'Unauthorized'], 401 );
}

// ─── Permission check ────────────────────────────────────────
if ( !klytos_has_permission( 'site.configure' ) ) {
    Helpers::jsonResponse( ['error' => 'Forbidden'], 403 );
}

// ─── CSRF verification ──────────────────────────────────────
if ( !klytos_verify_csrf() ) {
    Helpers::jsonResponse( ['error' => 'Invalid CSRF token'], 403 );
}

// ─── Rate limiting (60 operations per minute per session) ───
$now   = time();
$key   = 'klytos_forms_api_rate';
$limit = 60;

if ( !isset( $_SESSION[$key] ) ) {
    $_SESSION[$key] = ['count' => 0, 'reset' => $now + 60];
}
if ( $_SESSION[$key]['reset'] < $now ) {
    $_SESSION[$key] = ['count' => 0, 'reset' => $now + 60];
}
$_SESSION[$key]['count']++;

if ( $_SESSION[$key]['count'] > $limit ) {
    Helpers::jsonResponse( ['error' => 'Rate limit exceeded. Try again in a minute.'], 429 );
}

// ─── Parse request ──────────────────────────────────────────
$input  = json_decode( file_get_contents( 'php://input' ), true ) ?? [];
$action = $input['action'] ?? ( $_GET['action'] ?? '' );
$fm     = $app->getFormManager();

try {
    switch ( $action ) {

        // ─── Forms CRUD ─────────────────────────────────────

        case 'list':
            $status = $input['status'] ?? ( $_GET['status'] ?? null );
            $forms  = $fm->listForms( $status );
            Helpers::jsonResponse( ['success' => true, 'forms' => $forms] );
            break;

        case 'get':
            $id   = $input['id'] ?? ( $_GET['id'] ?? '' );
            $form = $fm->getForm( $id );
            if ( !$form ) {
                Helpers::jsonResponse( ['error' => "Form '{$id}' not found."], 404 );
            }
            Helpers::jsonResponse( ['success' => true, 'form' => $form] );
            break;

        case 'create':
            $form = $fm->createForm( $input );
            Helpers::jsonResponse( ['success' => true, 'form' => $form] );
            break;

        case 'update':
            $id   = $input['id'] ?? ( $_GET['id'] ?? '' );
            $form = $fm->updateForm( $id, $input );
            Helpers::jsonResponse( ['success' => true, 'form' => $form] );
            break;

        case 'delete':
            $id             = $input['id'] ?? ( $_GET['id'] ?? '' );
            $deleteEntries  = (bool) ( $input['delete_entries'] ?? ( $_GET['delete_entries'] ?? false ) );
            $deleted        = $fm->deleteForm( $id, $deleteEntries );
            Helpers::jsonResponse( ['success' => $deleted] );
            break;

        case 'duplicate':
            $id       = $input['id'] ?? ( $_GET['id'] ?? '' );
            $newTitle = $input['new_title'] ?? null;
            $form     = $fm->duplicateForm( $id, $newTitle );
            Helpers::jsonResponse( ['success' => true, 'form' => $form] );
            break;

        // ─── Fields ─────────────────────────────────────────

        case 'add_field':
            $formId   = $input['form_id'] ?? ( $_GET['form_id'] ?? '' );
            $field    = $input['field'] ?? [];
            $position = isset( $input['position'] ) ? (int) $input['position'] : null;
            $result   = $fm->addField( $formId, $field, $position );
            Helpers::jsonResponse( ['success' => true, 'field' => $result] );
            break;

        case 'update_field':
            $formId  = $input['form_id'] ?? ( $_GET['form_id'] ?? '' );
            $fieldId = $input['field_id'] ?? ( $_GET['field_id'] ?? '' );
            $updates = $input['updates'] ?? [];
            $form    = $fm->updateField( $formId, $fieldId, $updates );
            Helpers::jsonResponse( ['success' => true, 'form' => $form] );
            break;

        case 'remove_field':
            $formId  = $input['form_id'] ?? ( $_GET['form_id'] ?? '' );
            $fieldId = $input['field_id'] ?? ( $_GET['field_id'] ?? '' );
            $removed = $fm->removeField( $formId, $fieldId );
            Helpers::jsonResponse( ['success' => $removed] );
            break;

        case 'reorder_fields':
            $formId = $input['form_id'] ?? ( $_GET['form_id'] ?? '' );
            $order  = $input['order'] ?? [];
            $form   = $fm->reorderFields( $formId, $order );
            Helpers::jsonResponse( ['success' => true, 'form' => $form] );
            break;

        // ─── Entries ────────────────────────────────────────

        case 'entries':
            $formId  = $input['form_id'] ?? ( $_GET['form_id'] ?? '' );
            $filters = array_intersect_key(
                array_merge( $input, $_GET ),
                array_flip( ['status', 'search', 'date_from', 'date_to', 'page', 'per_page', 'is_spam'] )
            );
            $result  = $fm->listEntries( $formId, $filters );
            Helpers::jsonResponse( ['success' => true] + $result );
            break;

        case 'entry':
            $entryId = $input['id'] ?? ( $_GET['id'] ?? '' );
            $entry   = $fm->getEntry( $entryId );
            if ( !$entry ) {
                Helpers::jsonResponse( ['error' => "Entry '{$entryId}' not found."], 404 );
            }
            Helpers::jsonResponse( ['success' => true, 'entry' => $entry] );
            break;

        case 'update_entry_status':
            $entryId = $input['id'] ?? ( $_GET['id'] ?? '' );
            $status  = $input['status'] ?? ( $_GET['status'] ?? '' );
            $updated = $fm->updateEntryStatus( $entryId, $status );
            Helpers::jsonResponse( ['success' => $updated] );
            break;

        case 'add_note':
            $entryId = $input['id'] ?? ( $_GET['id'] ?? '' );
            $note    = $input['note'] ?? '';
            $updated = $fm->addEntryNote( $entryId, $note );
            Helpers::jsonResponse( ['success' => $updated] );
            break;

        case 'delete_entry':
            $entryId = $input['id'] ?? ( $_GET['id'] ?? '' );
            $deleted = $fm->deleteEntry( $entryId );
            Helpers::jsonResponse( ['success' => $deleted] );
            break;

        case 'delete_entries':
            $formId  = $input['form_id'] ?? ( $_GET['form_id'] ?? '' );
            $deleted = $fm->deleteEntriesByForm( $formId );
            Helpers::jsonResponse( ['success' => true, 'deleted' => $deleted] );
            break;

        case 'export':
            $formId = $input['form_id'] ?? ( $_GET['form_id'] ?? '' );
            $format = $input['format'] ?? ( $_GET['format'] ?? 'csv' );

            if ( $format === 'csv' ) {
                header( 'Content-Type: text/csv; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="entries-' . $formId . '.csv"' );
                echo $fm->exportEntries( $formId, 'csv' );
            } else {
                echo $fm->exportEntries( $formId, 'json' );
            }
            exit;

        // ─── Stats ──────────────────────────────────────────

        case 'stats':
            $formId = $input['form_id'] ?? ( $_GET['form_id'] ?? '' );
            $stats  = $fm->getFormStats( $formId );
            Helpers::jsonResponse( ['success' => true, 'stats' => $stats] );
            break;

        default:
            Helpers::jsonResponse( ['error' => "Invalid action: {$action}"], 400 );
    }
} catch ( \RuntimeException $e ) {
    Helpers::jsonResponse( ['error' => $e->getMessage()], 400 );
} catch ( \Throwable $e ) {
    Helpers::jsonResponse( ['error' => 'Internal error.'], 500 );
}
