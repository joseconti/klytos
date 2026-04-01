<?php

/**
 * Klytos — Form Manager
 * Complete form lifecycle: CRUD for forms, fields, entries, notifications, and submissions.
 *
 * AI-first: every operation is available via MCP tools.
 * The admin panel is a complementary UI, not the primary interface.
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

namespace KlytosForms;

use Klytos\Core\StorageInterface;
use Klytos\Core\Helpers;
use Klytos\Core\AssetManager;

class FormManager
{
    private StorageInterface $storage;
    private FormConditionalEngine $conditionalEngine;
    private ?AssetManager $assetManager;

    private const FORMS_COLLECTION   = 'forms';
    private const ENTRIES_COLLECTION  = 'form-entries';

    public function __construct(
        StorageInterface $storage,
        FormConditionalEngine $conditionalEngine,
        ?AssetManager $assetManager = null
    ) {
        $this->storage            = $storage;
        $this->conditionalEngine  = $conditionalEngine;
        $this->assetManager       = $assetManager;
    }

    // ─── CRUD: Forms ────────────────────────────────────────────

    /**
     * Create a new form.
     *
     * @param  array $data Form definition.
     * @return array The created form.
     */
    public function createForm( array $data ): array
    {
        $id = $data['id'] ?? Helpers::sanitizeSlug( $data['title'] ?? 'form-' . Helpers::generateShortId() );

        if ( $this->storage->exists( self::FORMS_COLLECTION, $id ) ) {
            throw new \RuntimeException( "Form '{$id}' already exists." );
        }

        $data['fields'] = $this->normalizeFieldIds( $data['fields'] ?? [] );

        $form = [
            'id'            => $id,
            'title'         => $data['title'] ?? 'Sin titulo',
            'description'   => $data['description'] ?? '',
            'status'        => $data['status'] ?? 'active',
            'fields'        => $data['fields'],
            'settings'      => array_merge( $this->defaultSettings(), $data['settings'] ?? [] ),
            'notifications' => $data['notifications'] ?? [],
            'anti_spam'     => array_merge( $this->defaultAntiSpam(), $data['anti_spam'] ?? [] ),
            'created_by'    => klytos_current_user()['id'] ?? 'system',
            'created_at'    => Helpers::now(),
            'updated_at'    => Helpers::now(),
        ];

        $this->storage->write( self::FORMS_COLLECTION, $id, $form );

        klytos_do_action( 'form.after_create', $form );

        return $form;
    }

    /**
     * Get a form by ID.
     */
    public function getForm( string $id ): ?array
    {
        if ( !$this->storage->exists( self::FORMS_COLLECTION, $id ) ) {
            return null;
        }
        return $this->storage->read( self::FORMS_COLLECTION, $id );
    }

    /**
     * List all forms, optionally filtered by status.
     */
    public function listForms( ?string $status = null ): array
    {
        $all = $this->storage->list( self::FORMS_COLLECTION );

        if ( $status !== null ) {
            $all = array_filter( $all, fn( $f ) => ( $f['status'] ?? '' ) === $status );
        }

        return array_values( $all );
    }

    /**
     * Update a form. Only provided fields are changed.
     */
    public function updateForm( string $id, array $data ): array
    {
        $form = $this->getForm( $id );
        if ( !$form ) {
            throw new \RuntimeException( "Form '{$id}' not found." );
        }

        if ( isset( $data['title'] ) )         $form['title']         = $data['title'];
        if ( isset( $data['description'] ) )   $form['description']   = $data['description'];
        if ( isset( $data['status'] ) )        $form['status']        = $data['status'];
        if ( isset( $data['fields'] ) )        $form['fields']        = $this->normalizeFieldIds( $data['fields'] );
        if ( isset( $data['settings'] ) )      $form['settings']      = array_merge( $form['settings'], $data['settings'] );
        if ( isset( $data['notifications'] ) ) $form['notifications'] = $data['notifications'];
        if ( isset( $data['anti_spam'] ) )     $form['anti_spam']     = array_merge( $form['anti_spam'], $data['anti_spam'] );

        $form['updated_at'] = Helpers::now();

        $this->storage->write( self::FORMS_COLLECTION, $id, $form );

        klytos_do_action( 'form.after_update', $form );

        return $form;
    }

    /**
     * Delete a form (and optionally its entries).
     */
    public function deleteForm( string $id, bool $deleteEntries = false ): bool
    {
        if ( !$this->storage->exists( self::FORMS_COLLECTION, $id ) ) {
            return false;
        }

        klytos_do_action( 'form.before_delete', $id );

        if ( $deleteEntries ) {
            $this->deleteEntriesByForm( $id );
        }

        $deleted = $this->storage->delete( self::FORMS_COLLECTION, $id );

        if ( $deleted ) {
            klytos_do_action( 'form.after_delete', $id );
        }

        return $deleted;
    }

    /**
     * Duplicate a form.
     */
    public function duplicateForm( string $id, ?string $newTitle = null ): array
    {
        $form = $this->getForm( $id );
        if ( !$form ) {
            throw new \RuntimeException( "Form '{$id}' not found." );
        }

        $newId    = $id . '-copy-' . Helpers::generateShortId();
        $newTitle = $newTitle ?? $form['title'] . ' (copia)';

        $form['id']         = $newId;
        $form['title']      = $newTitle;
        $form['created_at'] = Helpers::now();
        $form['updated_at'] = Helpers::now();

        $this->storage->write( self::FORMS_COLLECTION, $newId, $form );

        return $form;
    }

    // ─── Field Management ───────────────────────────────────────

    /**
     * Add a field to a form.
     */
    public function addField( string $formId, array $fieldData, ?int $position = null ): array
    {
        $form = $this->getForm( $formId );
        if ( !$form ) {
            throw new \RuntimeException( "Form '{$formId}' not found." );
        }

        if ( empty( $fieldData['id'] ) ) {
            $fieldData['id'] = 'field_' . Helpers::generateShortId();
        }

        foreach ( $form['fields'] as $existing ) {
            if ( $existing['id'] === $fieldData['id'] ) {
                throw new \RuntimeException( "Field ID '{$fieldData['id']}' already exists in form." );
            }
        }

        if ( $position !== null ) {
            $fieldData['order'] = $position;
            foreach ( $form['fields'] as &$f ) {
                if ( $f['order'] >= $position ) {
                    $f['order']++;
                }
            }
            unset( $f );
        } else {
            $maxOrder = max( array_column( $form['fields'], 'order' ) ?: [0] );
            $fieldData['order'] = $maxOrder + 1;
        }

        $fieldData = array_merge( [
            'type'          => 'text',
            'label'         => '',
            'placeholder'   => '',
            'required'      => false,
            'validation'    => [],
            'css_class'     => '',
            'default_value' => '',
            'step'          => 1,
            'conditional'   => null,
        ], $fieldData );

        $form['fields'][] = $fieldData;

        usort( $form['fields'], fn( $a, $b ) => ( $a['order'] ?? 0 ) - ( $b['order'] ?? 0 ) );

        $form['updated_at'] = Helpers::now();
        $this->storage->write( self::FORMS_COLLECTION, $formId, $form );

        return $fieldData;
    }

    /**
     * Update an existing field.
     */
    public function updateField( string $formId, string $fieldId, array $fieldData ): array
    {
        $form = $this->getForm( $formId );
        if ( !$form ) {
            throw new \RuntimeException( "Form '{$formId}' not found." );
        }

        $found = false;
        foreach ( $form['fields'] as &$field ) {
            if ( $field['id'] === $fieldId ) {
                $field = array_merge( $field, $fieldData );
                $field['id'] = $fieldId; // Never allow ID change via merge
                $found = true;
                break;
            }
        }
        unset( $field );

        if ( !$found ) {
            throw new \RuntimeException( "Field '{$fieldId}' not found in form '{$formId}'." );
        }

        $form['updated_at'] = Helpers::now();
        $this->storage->write( self::FORMS_COLLECTION, $formId, $form );

        return $form;
    }

    /**
     * Remove a field from a form (also cleans conditional references).
     */
    public function removeField( string $formId, string $fieldId ): bool
    {
        $form = $this->getForm( $formId );
        if ( !$form ) {
            throw new \RuntimeException( "Form '{$formId}' not found." );
        }

        $originalCount = count( $form['fields'] );
        $form['fields'] = array_values(
            array_filter( $form['fields'], fn( $f ) => $f['id'] !== $fieldId )
        );

        if ( count( $form['fields'] ) === $originalCount ) {
            return false;
        }

        // Clean conditional rules referencing this field
        foreach ( $form['fields'] as &$field ) {
            if ( isset( $field['conditional']['rules'] ) ) {
                $field['conditional']['rules'] = array_values(
                    array_filter( $field['conditional']['rules'], fn( $r ) => $r['field_id'] !== $fieldId )
                );
                if ( empty( $field['conditional']['rules'] ) ) {
                    $field['conditional'] = null;
                }
            }
        }
        unset( $field );

        $form['updated_at'] = Helpers::now();
        $this->storage->write( self::FORMS_COLLECTION, $formId, $form );

        return true;
    }

    /**
     * Reorder fields by providing field IDs in desired order.
     */
    public function reorderFields( string $formId, array $fieldIdsInOrder ): array
    {
        $form = $this->getForm( $formId );
        if ( !$form ) {
            throw new \RuntimeException( "Form '{$formId}' not found." );
        }

        $indexed = [];
        foreach ( $form['fields'] as $field ) {
            $indexed[$field['id']] = $field;
        }

        $reordered = [];
        $order = 1;
        foreach ( $fieldIdsInOrder as $fieldId ) {
            if ( isset( $indexed[$fieldId] ) ) {
                $indexed[$fieldId]['order'] = $order++;
                $reordered[] = $indexed[$fieldId];
                unset( $indexed[$fieldId] );
            }
        }
        // Append unmentioned fields at the end
        foreach ( $indexed as $field ) {
            $field['order'] = $order++;
            $reordered[] = $field;
        }

        $form['fields'] = $reordered;
        $form['updated_at'] = Helpers::now();
        $this->storage->write( self::FORMS_COLLECTION, $formId, $form );

        return $form;
    }

    // ─── Form Submission ────────────────────────────────────────

    /**
     * Process a form submission.
     *
     * @param  string $formId  Form ID.
     * @param  array  $rawData Submitted data ($_POST).
     * @param  array  $files   Uploaded files ($_FILES).
     * @param  array  $meta    Metadata (ip, user_agent, referrer, etc.).
     * @return array  Entry on success or errors array.
     */
    public function submitForm( string $formId, array $rawData, array $files = [], array $meta = [] ): array
    {
        $form = $this->getForm( $formId );
        if ( !$form || $form['status'] !== 'active' ) {
            return ['success' => false, 'errors' => ['form' => 'Formulario no disponible.']];
        }

        // 1. Anti-spam: honeypot
        if ( ( $form['anti_spam']['honeypot'] ?? true ) && !empty( $rawData['_klytos_hp'] ) ) {
            // Bot detected: simulate success but don't save
            return ['success' => true, 'message' => $form['settings']['success_message'] ?? 'Gracias.'];
        }

        // 2. Anti-spam: rate limiting
        if ( $this->isRateLimited( $formId, $meta['ip'] ?? '' ) ) {
            return ['success' => false, 'errors' => ['form' => 'Demasiados envios. Intentalo de nuevo mas tarde.']];
        }

        // 3. Determine visible fields (evaluate conditionals)
        $visibleFieldIds = [];
        foreach ( $form['fields'] as $field ) {
            if ( $field['type'] === 'html' || $field['type'] === 'section' ) continue;
            if ( $this->conditionalEngine->evaluate( $field['conditional'] ?? null, $rawData ) ) {
                $visibleFieldIds[] = $field['id'];
            }
        }

        // 4. Hook: before validate
        klytos_do_action( 'form.before_validate', $form, $rawData );

        // 5. Validate only visible + required fields
        $errors = $this->validateSubmission( $form, $rawData, $visibleFieldIds );

        // 6. Hook: after validate (plugins can add errors)
        $errors = klytos_apply_filters( 'form.after_validate', $errors, $form, $rawData );

        if ( !empty( $errors ) ) {
            return ['success' => false, 'errors' => $errors];
        }

        // 7. Sanitize data (only visible fields)
        $cleanData = [];
        foreach ( $form['fields'] as $field ) {
            if ( !in_array( $field['id'], $visibleFieldIds ) ) continue;
            $cleanData[$field['id']] = $this->sanitizeFieldValue( $field, $rawData[$field['id']] ?? null );
        }

        // 8. Process file uploads
        $fileRecords = [];
        if ( $this->assetManager ) {
            foreach ( $form['fields'] as $field ) {
                if ( $field['type'] !== 'file' ) continue;
                if ( !in_array( $field['id'], $visibleFieldIds ) ) continue;
                if ( !isset( $files[$field['id']] ) ) continue;

                $uploaded = $this->processFileUpload( $field, $files[$field['id']] );
                if ( $uploaded ) {
                    $fileRecords = array_merge( $fileRecords, $uploaded );
                }
            }
        }

        // 9. Create entry
        $entryId = 'entry_' . Helpers::generateShortId();
        $entry = [
            'id'                  => $entryId,
            'form_id'             => $formId,
            'data'                => $cleanData,
            'files'               => $fileRecords,
            'metadata'            => [
                'ip'           => $meta['ip'] ?? '',
                'user_agent'   => $meta['user_agent'] ?? '',
                'referrer'     => $meta['referrer'] ?? '',
                'page_url'     => $meta['page_url'] ?? '',
                'submitted_at' => Helpers::now(),
                'locale'       => $meta['locale'] ?? '',
            ],
            'status'              => 'unread',
            'notes'               => '',
            'is_spam'             => false,
            'notifications_sent'  => [],
            'created_at'          => Helpers::now(),
        ];

        $this->storage->write( self::ENTRIES_COLLECTION, $entryId, $entry );

        klytos_do_action( 'form.entry_created', $entry, $form );

        // 10. Send notifications
        $sentNotifications = $this->sendNotifications( $form, $entry );
        $entry['notifications_sent'] = $sentNotifications;
        $this->storage->write( self::ENTRIES_COLLECTION, $entryId, $entry );

        // 11. Response
        $response = [
            'success'  => true,
            'entry_id' => $entryId,
            'message'  => $form['settings']['success_message'] ?? 'Formulario enviado correctamente.',
        ];

        if ( ( $form['settings']['success_action'] ?? 'message' ) === 'redirect' ) {
            $response['redirect'] = $form['settings']['success_redirect'] ?? '';
        }

        return $response;
    }

    // ─── Entry Management ───────────────────────────────────────

    /**
     * Get an entry by ID.
     */
    public function getEntry( string $entryId ): ?array
    {
        if ( !$this->storage->exists( self::ENTRIES_COLLECTION, $entryId ) ) {
            return null;
        }
        return $this->storage->read( self::ENTRIES_COLLECTION, $entryId );
    }

    /**
     * List entries for a form with filters and pagination.
     */
    public function listEntries( string $formId, array $filters = [] ): array
    {
        $all     = $this->storage->list( self::ENTRIES_COLLECTION );
        $entries = [];

        foreach ( $all as $entry ) {
            if ( ( $entry['form_id'] ?? '' ) !== $formId ) continue;

            if ( isset( $filters['status'] ) && ( $entry['status'] ?? '' ) !== $filters['status'] ) continue;
            if ( isset( $filters['is_spam'] ) && ( $entry['is_spam'] ?? false ) !== $filters['is_spam'] ) continue;
            if ( isset( $filters['date_from'] ) && ( $entry['created_at'] ?? '' ) < $filters['date_from'] ) continue;
            if ( isset( $filters['date_to'] ) && ( $entry['created_at'] ?? '' ) > $filters['date_to'] ) continue;

            if ( isset( $filters['search'] ) ) {
                $found = false;
                foreach ( ( $entry['data'] ?? [] ) as $value ) {
                    if ( is_string( $value ) && str_contains( mb_strtolower( $value ), mb_strtolower( $filters['search'] ) ) ) {
                        $found = true;
                        break;
                    }
                }
                if ( !$found ) continue;
            }

            $entries[] = $entry;
        }

        // Sort by date descending
        usort( $entries, fn( $a, $b ) => ( $b['created_at'] ?? '' ) <=> ( $a['created_at'] ?? '' ) );

        // Pagination
        $page    = max( 1, (int) ( $filters['page'] ?? 1 ) );
        $perPage = max( 1, (int) ( $filters['per_page'] ?? 20 ) );
        $total   = count( $entries );
        $entries = array_slice( $entries, ( $page - 1 ) * $perPage, $perPage );

        return [
            'entries'  => $entries,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil( $total / $perPage ),
        ];
    }

    /**
     * Update entry status.
     */
    public function updateEntryStatus( string $entryId, string $status ): bool
    {
        $entry = $this->getEntry( $entryId );
        if ( !$entry ) return false;

        $entry['status'] = $status;
        $this->storage->write( self::ENTRIES_COLLECTION, $entryId, $entry );

        return true;
    }

    /**
     * Add a note to an entry.
     */
    public function addEntryNote( string $entryId, string $note ): bool
    {
        $entry = $this->getEntry( $entryId );
        if ( !$entry ) return false;

        $entry['notes'] = $note;
        $this->storage->write( self::ENTRIES_COLLECTION, $entryId, $entry );

        return true;
    }

    /**
     * Delete a single entry.
     */
    public function deleteEntry( string $entryId ): bool
    {
        return $this->storage->delete( self::ENTRIES_COLLECTION, $entryId );
    }

    /**
     * Delete all entries for a form.
     */
    public function deleteEntriesByForm( string $formId ): int
    {
        $entries = $this->storage->list( self::ENTRIES_COLLECTION );
        $deleted = 0;

        foreach ( $entries as $entry ) {
            if ( ( $entry['form_id'] ?? '' ) === $formId ) {
                $this->storage->delete( self::ENTRIES_COLLECTION, $entry['id'] );
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Export entries as CSV or JSON.
     */
    public function exportEntries( string $formId, string $format = 'csv' ): string
    {
        $form    = $this->getForm( $formId );
        $result  = $this->listEntries( $formId, ['per_page' => 99999] );
        $entries = $result['entries'];

        if ( $format === 'csv' ) {
            $output = fopen( 'php://temp', 'r+' );

            // Header row
            $headers = ['ID', 'Fecha'];
            foreach ( $form['fields'] as $field ) {
                if ( $field['type'] === 'html' || $field['type'] === 'section' ) continue;
                $headers[] = $field['label'] ?: $field['id'];
            }
            $headers[] = 'Estado';
            fputcsv( $output, $headers );

            // Data rows
            foreach ( $entries as $entry ) {
                $row = [$entry['id'], $entry['metadata']['submitted_at'] ?? $entry['created_at']];
                foreach ( $form['fields'] as $field ) {
                    if ( $field['type'] === 'html' || $field['type'] === 'section' ) continue;
                    $val = $entry['data'][$field['id']] ?? '';
                    if ( is_array( $val ) ) $val = implode( ', ', $val );
                    $row[] = $val;
                }
                $row[] = $entry['status'];
                fputcsv( $output, $row );
            }

            rewind( $output );
            $csv = stream_get_contents( $output );
            fclose( $output );

            return $csv;
        }

        // JSON
        return json_encode( $entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Get statistics for a form.
     */
    public function getFormStats( string $formId ): array
    {
        $result  = $this->listEntries( $formId, ['per_page' => 99999] );
        $entries = $result['entries'];

        $stats = [
            'total'   => count( $entries ),
            'unread'  => 0,
            'read'    => 0,
            'starred' => 0,
            'trash'   => 0,
            'spam'    => 0,
            'today'   => 0,
            'week'    => 0,
            'month'   => 0,
        ];

        $now       = time();
        $todayStr  = date( 'Y-m-d' );
        $weekAgo   = date( 'c', $now - 7 * 86400 );
        $monthAgo  = date( 'c', $now - 30 * 86400 );

        foreach ( $entries as $entry ) {
            $status = $entry['status'] ?? 'unread';
            if ( isset( $stats[$status] ) ) $stats[$status]++;
            if ( $entry['is_spam'] ?? false ) $stats['spam']++;

            $created = $entry['created_at'] ?? '';
            if ( str_starts_with( $created, $todayStr ) ) $stats['today']++;
            if ( $created > $weekAgo ) $stats['week']++;
            if ( $created > $monthAgo ) $stats['month']++;
        }

        return $stats;
    }

    // ─── Notifications ──────────────────────────────────────────

    /**
     * Send configured notifications for a form submission.
     */
    private function sendNotifications( array $form, array $entry ): array
    {
        $sent = [];

        foreach ( $form['notifications'] ?? [] as $notification ) {
            if ( !( $notification['enabled'] ?? true ) ) continue;

            // Evaluate notification conditional
            if ( isset( $notification['conditional'] ) ) {
                if ( !$this->conditionalEngine->evaluate( $notification['conditional'], $entry['data'] ) ) {
                    continue;
                }
            }

            // Replace merge tags
            $to      = $this->replaceMergeTags( $notification['to'] ?? '', $entry, $form );
            $replyTo = $this->replaceMergeTags( $notification['reply_to'] ?? '', $entry, $form );
            $subject = $this->replaceMergeTags( $notification['subject'] ?? '', $entry, $form );
            $body    = $this->replaceMergeTags( $notification['body'] ?? '', $entry, $form );

            if ( empty( $to ) ) continue;

            // Build headers
            $headers  = "From: " . klytos_get_option( 'site_name', 'Klytos' ) . " <" . klytos_get_option( 'site_email', 'noreply@localhost' ) . ">\r\n";
            if ( !empty( $replyTo ) ) {
                $headers .= "Reply-To: {$replyTo}\r\n";
            }

            if ( ( $notification['format'] ?? 'text' ) === 'html' ) {
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            } else {
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            }

            $success = mail( $to, $subject, $body, $headers );

            if ( $success ) {
                $sent[] = $notification['id'] ?? $notification['name'] ?? 'unknown';
            }

            klytos_do_action( 'form.notification_sent', $notification, $entry, $success );
        }

        return $sent;
    }

    /**
     * Replace merge tags {{field_id}} and system variables.
     */
    private function replaceMergeTags( string $template, array $entry, array $form ): string
    {
        $systemVars = [
            'site_name'     => klytos_get_option( 'site_name', 'Klytos' ),
            'site_email'    => klytos_get_option( 'site_email', '' ),
            'site_url'      => klytos_get_option( 'site_url', '' ),
            'form_title'    => $form['title'] ?? '',
            'entry_id'      => $entry['id'] ?? '',
            'entry_date'    => $entry['metadata']['submitted_at'] ?? $entry['created_at'] ?? '',
            'entry_ip'      => $entry['metadata']['ip'] ?? '',
            'all_fields'    => $this->formatAllFields( $entry['data'], $form ),
        ];

        foreach ( $systemVars as $key => $value ) {
            $template = str_replace( '{{' . $key . '}}', (string) $value, $template );
        }

        foreach ( ( $entry['data'] ?? [] ) as $fieldId => $value ) {
            if ( is_array( $value ) ) $value = implode( ', ', $value );
            $template = str_replace( '{{' . $fieldId . '}}', (string) $value, $template );
        }

        return $template;
    }

    /**
     * Format all fields as plain text for {{all_fields}}.
     */
    private function formatAllFields( array $data, array $form ): string
    {
        $lines = [];
        foreach ( $form['fields'] as $field ) {
            if ( $field['type'] === 'html' || $field['type'] === 'section' ) continue;
            $value = $data[$field['id']] ?? '';
            if ( is_array( $value ) ) $value = implode( ', ', $value );
            $lines[] = ( $field['label'] ?: $field['id'] ) . ': ' . $value;
        }
        return implode( "\n", $lines );
    }

    // ─── Validation ─────────────────────────────────────────────

    /**
     * Validate submitted data against form definition.
     */
    private function validateSubmission( array $form, array $data, array $visibleFieldIds ): array
    {
        $errors = [];

        foreach ( $form['fields'] as $field ) {
            if ( !in_array( $field['id'], $visibleFieldIds ) ) continue;
            if ( $field['type'] === 'html' || $field['type'] === 'section' ) continue;

            $value = $data[$field['id']] ?? null;

            // Required
            if ( ( $field['required'] ?? false ) && ( $value === null || $value === '' || $value === [] ) ) {
                $errors[$field['id']] = "El campo \"{$field['label']}\" es obligatorio.";
                continue;
            }

            // Skip additional validations if empty and not required
            if ( $value === null || $value === '' ) continue;

            $validation = $field['validation'] ?? [];

            // Min length
            if ( isset( $validation['min_length'] ) && mb_strlen( $value ) < $validation['min_length'] ) {
                $errors[$field['id']] = "Minimo {$validation['min_length']} caracteres.";
            }

            // Max length
            if ( isset( $validation['max_length'] ) && mb_strlen( $value ) > $validation['max_length'] ) {
                $errors[$field['id']] = "Maximo {$validation['max_length']} caracteres.";
            }

            // Pattern
            if ( isset( $validation['pattern'] ) && !preg_match( '/' . $validation['pattern'] . '/', $value ) ) {
                $errors[$field['id']] = $validation['pattern_message'] ?? 'Formato no valido.';
            }

            // Email
            if ( $field['type'] === 'email' && !filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
                $errors[$field['id']] = 'Email no valido.';
            }

            // URL
            if ( $field['type'] === 'url' && !filter_var( $value, FILTER_VALIDATE_URL ) ) {
                $errors[$field['id']] = 'URL no valida.';
            }

            // Number range
            if ( $field['type'] === 'number' || $field['type'] === 'range' ) {
                if ( isset( $validation['min'] ) && (float) $value < (float) $validation['min'] ) {
                    $errors[$field['id']] = "El valor minimo es {$validation['min']}.";
                }
                if ( isset( $validation['max'] ) && (float) $value > (float) $validation['max'] ) {
                    $errors[$field['id']] = "El valor maximo es {$validation['max']}.";
                }
            }

            // Checkbox group selection limits
            if ( $field['type'] === 'checkbox_group' && is_array( $value ) ) {
                if ( isset( $validation['min_selected'] ) && count( $value ) < $validation['min_selected'] ) {
                    $errors[$field['id']] = "Selecciona al menos {$validation['min_selected']} opciones.";
                }
                if ( isset( $validation['max_selected'] ) && count( $value ) > $validation['max_selected'] ) {
                    $errors[$field['id']] = "Selecciona como maximo {$validation['max_selected']} opciones.";
                }
            }
        }

        return $errors;
    }

    // ─── Anti-Spam ──────────────────────────────────────────────

    /**
     * Check if an IP is rate-limited for a form.
     */
    private function isRateLimited( string $formId, string $ip ): bool
    {
        if ( empty( $ip ) ) return false;

        $form   = $this->getForm( $formId );
        $limit  = $form['anti_spam']['rate_limit'] ?? 3;
        $window = $form['anti_spam']['rate_limit_window'] ?? 60;

        if ( $limit <= 0 ) return false;

        $entries = $this->storage->list( self::ENTRIES_COLLECTION );
        $cutoff  = date( 'c', time() - $window );
        $count   = 0;

        foreach ( $entries as $entry ) {
            if ( ( $entry['form_id'] ?? '' ) === $formId
                && ( $entry['metadata']['ip'] ?? '' ) === $ip
                && ( $entry['created_at'] ?? '' ) > $cutoff ) {
                $count++;
            }
        }

        return $count >= $limit;
    }

    // ─── Sanitization ───────────────────────────────────────────

    /**
     * Sanitize a field value based on its type.
     */
    private function sanitizeFieldValue( array $field, mixed $value ): mixed
    {
        if ( $value === null ) return '';

        return match ( $field['type'] ?? 'text' ) {
            'email'          => filter_var( trim( (string) $value ), FILTER_SANITIZE_EMAIL ),
            'url'            => filter_var( trim( (string) $value ), FILTER_SANITIZE_URL ),
            'number', 'range' => is_numeric( $value ) ? (float) $value : 0,
            'checkbox', 'consent' => (bool) $value,
            'checkbox_group' => is_array( $value ) ? array_map( 'strval', $value ) : [],
            'hidden'         => (string) $value,
            default          => htmlspecialchars( trim( (string) $value ), ENT_QUOTES, 'UTF-8' ),
        };
    }

    /**
     * Process a file upload for a form field.
     *
     * @return array|null Array of file records or null on failure.
     */
    private function processFileUpload( array $field, array $fileData ): ?array
    {
        $records   = [];
        $validation = $field['validation'] ?? [];
        $maxSize   = ( $validation['max_size'] ?? 5 ) * 1024 * 1024; // MB to bytes
        $allowed   = $validation['allowed_types'] ?? [];

        // Normalize single/multiple file arrays
        $files = [];
        if ( isset( $fileData['name'] ) && is_array( $fileData['name'] ) ) {
            for ( $i = 0; $i < count( $fileData['name'] ); $i++ ) {
                if ( $fileData['error'][$i] !== UPLOAD_ERR_OK ) continue;
                $files[] = [
                    'name'     => $fileData['name'][$i],
                    'tmp_name' => $fileData['tmp_name'][$i],
                    'size'     => $fileData['size'][$i],
                    'type'     => $fileData['type'][$i],
                ];
            }
        } elseif ( isset( $fileData['name'] ) && $fileData['error'] === UPLOAD_ERR_OK ) {
            $files[] = $fileData;
        }

        foreach ( $files as $file ) {
            // Validate size
            if ( $file['size'] > $maxSize ) continue;

            // Validate type
            if ( !empty( $allowed ) ) {
                $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
                if ( !in_array( $ext, $allowed ) ) continue;
            }

            // Upload via AssetManager
            $destDir = 'assets/form-uploads/' . date( 'Y/m' );
            $safeName = pathinfo( $file['name'], PATHINFO_FILENAME )
                . '_' . Helpers::generateShortId()
                . '.' . pathinfo( $file['name'], PATHINFO_EXTENSION );

            $result = $this->assetManager->upload(
                $file['tmp_name'],
                $destDir . '/' . $safeName,
                $file['type']
            );

            if ( $result ) {
                $records[] = [
                    'field_id'      => $field['id'],
                    'original_name' => $file['name'],
                    'stored_path'   => $destDir . '/' . $safeName,
                    'mime_type'     => $file['type'],
                    'size'          => $file['size'],
                    'asset_id'      => $result['id'] ?? Helpers::generateShortId(),
                ];
            }
        }

        return !empty( $records ) ? $records : null;
    }

    // ─── Defaults ───────────────────────────────────────────────

    private function defaultSettings(): array
    {
        return [
            'submit_label'     => 'Enviar',
            'success_message'  => 'Formulario enviado correctamente.',
            'success_action'   => 'message',
            'success_redirect' => '',
            'enable_ajax'      => true,
            'css_class'        => '',
            'layout'           => 'stacked',
            'steps'            => [
                ['step' => 1, 'title' => ''],
            ],
        ];
    }

    private function defaultAntiSpam(): array
    {
        return [
            'honeypot'          => true,
            'rate_limit'        => 3,
            'rate_limit_window' => 60,
        ];
    }

    private function normalizeFieldIds( array $fields ): array
    {
        $usedIds = [];
        foreach ( $fields as &$field ) {
            if ( empty( $field['id'] ) ) {
                $base = 'field_' . Helpers::sanitizeSlug( $field['label'] ?? '' );
                if ( empty( $base ) || $base === 'field_' ) {
                    $base = 'field_' . Helpers::generateShortId();
                }
                $field['id'] = $base;
            }
            // Ensure uniqueness
            while ( in_array( $field['id'], $usedIds ) ) {
                $field['id'] .= '_' . Helpers::generateShortId();
            }
            $usedIds[] = $field['id'];
        }
        unset( $field );
        return $fields;
    }
}
