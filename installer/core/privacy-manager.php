<?php

/**
 * Klytos — Privacy Manager
 * GDPR compliance tools: data export (Right of Access, Art. 15) and
 * data erasure (Right to Erasure, Art. 17).
 *
 * This class orchestrates the collection and erasure of personal data
 * from both core collections and plugins. Plugins participate via
 * the hook system:
 *
 * - 'privacy.export_data'       — Append plugin data sections to export.
 * - 'privacy.erasable_data'     — Declare plugin data sections + erasability.
 * - 'privacy.erase_plugin_data' — Perform plugin-specific erasure.
 *
 * Core collections containing personal data:
 * - users              — User profiles.
 * - audit-log          — Activity logs (anonymized on erasure, never deleted).
 * - tasks              — Task assignments (user references cleared on erasure).
 *
 * Analytics data is excluded: it uses daily-salted SHA-256 hashes which are
 * not considered personal data under GDPR Recital 26.
 *
 * @package Klytos
 * @since   0.18.0
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

class PrivacyManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var UserManager User manager instance. */
    private UserManager $userManager;

    /** @var AuditLog Audit log instance. */
    private AuditLog $auditLog;

    /** @var int Maximum audit log entries to include in an export. */
    private const MAX_AUDIT_ENTRIES = 1000;

    /**
     * @param StorageInterface $storage     Storage backend instance.
     * @param UserManager      $userManager User manager instance.
     * @param AuditLog         $auditLog    Audit log instance.
     */
    public function __construct(
        StorageInterface $storage,
        UserManager $userManager,
        AuditLog $auditLog,
    ) {
        $this->storage     = $storage;
        $this->userManager = $userManager;
        $this->auditLog    = $auditLog;
    }

    // ─── User Lookup ─────────────────────────────────────────────

    /**
     * Find a user by username or email address.
     *
     * Searches by username first, then by email.
     *
     * @param  string $query Username or email to search for.
     * @return array|null User data (sanitized), or null if not found.
     */
    public function findUser( string $query ): ?array
    {
        $query = trim( $query );
        if ( $query === '' ) {
            return null;
        }

        // Try username first.
        $user = $this->userManager->getByUsername( $query );
        if ( $user !== null ) {
            return $user;
        }

        // Try email.
        return $this->userManager->getByEmail( $query );
    }

    // ─── Data Export (GDPR Art. 15 — Right of Access) ────────────

    /**
     * Collect all personal data stored for a user.
     *
     * Gathers data from core collections and allows plugins to append
     * their own sections via the 'privacy.export_data' filter.
     *
     * @param  string $userId User ID.
     * @return array  Structured export data with sections.
     * @throws \RuntimeException If user not found.
     */
    public function collectUserData( string $userId ): array
    {
        $user = $this->userManager->getById( $userId );

        $data = [
            'export_version' => '1.0',
            'generated_at'   => Helpers::now(),
            'generated_by'   => 'Klytos CMS Privacy Tools',
            'subject'        => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
            ],
            'sections' => [],
        ];

        // Section 1: User profile.
        $data['sections'][] = [
            'source' => 'core',
            'label'  => __( 'privacy.section_profile' ),
            'count'  => 1,
            'data'   => $user,
        ];

        // Section 2: Audit log entries.
        $auditEntries = $this->auditLog->query(
            ['user_id' => $userId],
            self::MAX_AUDIT_ENTRIES,
        );
        if ( !empty( $auditEntries ) ) {
            $data['sections'][] = [
                'source' => 'core',
                'label'  => __( 'privacy.section_audit_log' ),
                'count'  => count( $auditEntries ),
                'data'   => $auditEntries,
            ];
        }

        // Section 3 used to be form submissions and is gone from core for the
        // reason given in `collectErasableData()`: the collection it read has no
        // writer. The plugin that owns the data adds its own section through
        // `privacy.export_data`, which is applied below (D-116).

        // Section 4: Task assignments.
        $tasks = $this->getTasksForUser( $userId );
        if ( !empty( $tasks ) ) {
            $data['sections'][] = [
                'source' => 'core',
                'label'  => __( 'privacy.section_tasks' ),
                'count'  => count( $tasks ),
                'data'   => $tasks,
            ];
        }

        // Allow plugins to append their data sections.
        $data = klytos_apply_filters( 'privacy.export_data', $data, $userId, $user );

        klytos_do_action( 'privacy.export_generated', $userId, 'collect' );

        return $data;
    }

    /**
     * Export all personal data as a JSON string.
     *
     * @param  string $userId User ID.
     * @return string JSON-encoded export.
     */
    public function exportAsJson( string $userId ): string
    {
        $data = $this->collectUserData( $userId );

        klytos_do_action( 'privacy.export_generated', $userId, 'json' );

        return json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    /**
     * Export all personal data as a self-contained HTML document.
     *
     * @param  string $userId User ID.
     * @return string HTML document.
     */
    public function exportAsHtml( string $userId ): string
    {
        $data    = $this->collectUserData( $userId );
        $subject = $data['subject'];

        $html = '<!DOCTYPE html><html lang="en"><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html .= '<title>' . htmlspecialchars( __( 'privacy.export_title' ) ) . ' — ' . htmlspecialchars( $subject['username'] ) . '</title>';
        $html .= '<style>';
        $html .= 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:900px;margin:2rem auto;padding:0 1rem;color:#1a1a2e;line-height:1.6;}';
        $html .= 'h1{font-size:1.5rem;border-bottom:2px solid #3a86ff;padding-bottom:0.5rem;}';
        $html .= 'h2{font-size:1.1rem;color:#3a86ff;margin-top:2rem;}';
        $html .= '.meta{color:#666;font-size:0.85rem;margin-bottom:2rem;}';
        $html .= 'table{width:100%;border-collapse:collapse;margin:0.5rem 0 1.5rem;}';
        $html .= 'th,td{text-align:left;padding:0.5rem 0.75rem;border:1px solid #ddd;font-size:0.85rem;}';
        $html .= 'th{background:#f0f4ff;font-weight:600;}';
        $html .= 'td{word-break:break-word;}';
        $html .= '.badge{display:inline-block;padding:0.15rem 0.5rem;border-radius:3px;font-size:0.75rem;background:#e8f0fe;color:#3a86ff;}';
        $html .= '</style></head><body>';

        $html .= '<h1>' . htmlspecialchars( __( 'privacy.export_title' ) ) . '</h1>';
        $html .= '<div class="meta">';
        $html .= '<strong>' . htmlspecialchars( __( 'privacy.generated_at' ) ) . ':</strong> ' . htmlspecialchars( $data['generated_at'] );
        $html .= ' &middot; <strong>User:</strong> ' . htmlspecialchars( $subject['username'] );
        $html .= ' (' . htmlspecialchars( $subject['email'] ) . ')';
        $html .= '</div>';

        foreach ( $data['sections'] as $section ) {
            $html .= '<h2>' . htmlspecialchars( $section['label'] );
            if ( isset( $section['count'] ) ) {
                $html .= ' <span class="badge">' . (int) $section['count'] . '</span>';
            }
            $html .= '</h2>';

            $sectionData = $section['data'] ?? [];

            if ( empty( $sectionData ) ) {
                $html .= '<p><em>' . htmlspecialchars( __( 'privacy.no_data' ) ) . '</em></p>';
                continue;
            }

            // If data is a single record (associative array), wrap it for uniform rendering.
            if ( !isset( $sectionData[0] ) ) {
                $sectionData = [$sectionData];
            }

            $html .= '<table><thead><tr>';
            $keys = array_keys( $sectionData[0] );
            foreach ( $keys as $key ) {
                $html .= '<th>' . htmlspecialchars( (string) $key ) . '</th>';
            }
            $html .= '</tr></thead><tbody>';

            foreach ( $sectionData as $row ) {
                $html .= '<tr>';
                foreach ( $keys as $key ) {
                    $value = $row[$key] ?? '';
                    if ( is_array( $value ) ) {
                        $value = json_encode( $value, JSON_UNESCAPED_UNICODE );
                    }
                    $html .= '<td>' . htmlspecialchars( (string) $value ) . '</td>';
                }
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        // Allow plugins to append custom HTML.
        $html = klytos_apply_filters( 'privacy.export_html_sections', $html, $data );

        $html .= '</body></html>';

        klytos_do_action( 'privacy.export_generated', $userId, 'html' );

        return $html;
    }

    // ─── Data Erasure (GDPR Art. 17 — Right to Erasure) ──────────

    /**
     * Collect erasable data sections for a user.
     *
     * Each section declares whether it can be erased, the erasure method,
     * and an optional retention reason for legally retained data.
     *
     * @param  string $userId User ID.
     * @return array  Array of erasable data sections.
     * @throws \RuntimeException If user not found.
     */
    public function collectErasableData( string $userId ): array
    {
        $user     = $this->userManager->getById( $userId );
        $isOwner  = ( $user['role'] ?? '' ) === 'owner';

        $sections = [];

        // User account.
        $sections[] = [
            'id'               => 'core:user_account',
            'source'           => 'core',
            'label'            => __( 'privacy.section_profile' ),
            'erasable'         => !$isOwner,
            'erasure_method'   => 'anonymize',
            'retention_reason' => $isOwner ? __( 'privacy.owner_cannot_erase' ) : null,
            'item_count'       => 1,
        ];

        // Audit log.
        $auditCount = count( $this->auditLog->query( ['user_id' => $userId], self::MAX_AUDIT_ENTRIES ) );
        if ( $auditCount > 0 ) {
            $sections[] = [
                'id'             => 'core:audit_log',
                'source'         => 'core',
                'label'          => __( 'privacy.section_audit_log' ),
                'erasable'       => true,
                'erasure_method' => 'anonymize',
                'item_count'     => $auditCount,
            ];
        }

        /*
         * FORM DATA IS NOT CORE'S. It used to be declared here over a collection
         * called `form-submissions` that nothing in the product has ever written
         * to — Klytos Forms stores entries in `form-entries` — and because the
         * section was gated on finding matches it never even appeared. A person's
         * form data was silently absent from both export and erasure, and nothing
         * said so (D-116).
         *
         * The plugin that holds the data now declares, exports and erases it
         * through `privacy.erasable_data`, `privacy.export_data` and
         * `privacy.erase_plugin_data`. Core does not learn a plugin's collection
         * names, and any other plugin holding personal data is covered by the
         * same route rather than needing a case added here.
         */

        // Tasks.
        $tasks = $this->getTasksForUser( $userId );
        if ( !empty( $tasks ) ) {
            $sections[] = [
                'id'             => 'core:tasks',
                'source'         => 'core',
                'label'          => __( 'privacy.section_tasks' ),
                'erasable'       => true,
                'erasure_method' => 'anonymize',
                'item_count'     => count( $tasks ),
            ];
        }

        // Allow plugins to declare their data sections.
        $sections = klytos_apply_filters( 'privacy.erasable_data', $sections, $userId, $user );

        return $sections;
    }

    /**
     * Erase personal data for a user.
     *
     * Processes only the selected sections. Returns a result array indicating
     * what was erased, anonymized, or skipped.
     *
     * @param  string $userId           User ID.
     * @param  array  $selectedSections Array of section IDs to erase (e.g. ['core:user_account', 'core:audit_log']).
     * @param  string $currentUserId    The ID of the user performing the erasure (to prevent self-erasure).
     * @return array  Results array with status per section.
     * @throws \RuntimeException If trying to erase the owner or self.
     */
    public function eraseUserData( string $userId, array $selectedSections, string $currentUserId ): array
    {
        $user = $this->userManager->getById( $userId );

        // Protection: owner cannot be erased.
        if ( ( $user['role'] ?? '' ) === 'owner' && in_array( 'core:user_account', $selectedSections, true ) ) {
            throw new \RuntimeException( __( 'privacy.owner_cannot_erase' ) );
        }

        // Protection: cannot erase yourself.
        if ( $userId === $currentUserId && in_array( 'core:user_account', $selectedSections, true ) ) {
            throw new \RuntimeException( __( 'privacy.self_cannot_erase' ) );
        }

        klytos_do_action( 'privacy.before_erasure', $userId, $selectedSections );

        $results = [];

        // Collect erasable sections to check `erasable` flags.
        $erasableSections = $this->collectErasableData( $userId );
        $erasableMap      = [];
        foreach ( $erasableSections as $section ) {
            $erasableMap[$section['id']] = $section;
        }

        foreach ( $selectedSections as $sectionId ) {
            // Skip sections not declared as erasable.
            if ( isset( $erasableMap[$sectionId] ) && !$erasableMap[$sectionId]['erasable'] ) {
                $results[] = [
                    'section' => $sectionId,
                    'status'  => 'skipped',
                    'reason'  => 'legally_retained',
                    'detail'  => $erasableMap[$sectionId]['retention_reason'] ?? '',
                ];
                continue;
            }

            klytos_do_action( 'privacy.erase_section', $sectionId, $userId );

            switch ( $sectionId ) {
                case 'core:user_account':
                    $this->anonymizeUserAccount( $userId );
                    $results[] = ['section' => $sectionId, 'status' => 'anonymized'];
                    break;

                case 'core:audit_log':
                    $count = $this->anonymizeAuditLog( $userId );
                    $results[] = ['section' => $sectionId, 'status' => 'anonymized', 'count' => $count];
                    break;

                case 'core:tasks':
                    $count = $this->anonymizeTaskAssignments( $userId );
                    $results[] = ['section' => $sectionId, 'status' => 'anonymized', 'count' => $count];
                    break;

                default:
                    // Plugin section — handled via filter below.
                    break;
            }
        }

        // Allow plugins to perform their own erasure.
        $results = klytos_apply_filters( 'privacy.erase_plugin_data', $results, $userId, $selectedSections );

        // Log the erasure action in the audit log.
        $this->auditLog->record(
            'privacy_erasure',
            'user',
            $userId,
            [
                'sections_requested' => $selectedSections,
                'results'            => $results,
            ],
            'admin',
        );

        klytos_do_action( 'privacy.erasure_complete', $userId, $results );

        return $results;
    }

    // ─── Internal: Core Data Collection ──────────────────────────


    /**
     * Get tasks assigned to or created by a user.
     *
     * @param  string $userId User ID.
     * @return array  Matching tasks.
     */
    private function getTasksForUser( string $userId ): array
    {
        $tasks   = $this->storage->list( 'tasks' );
        $matches = [];

        foreach ( $tasks as $task ) {
            if (
                ( $task['assigned_to'] ?? '' ) === $userId
                || ( $task['created_by'] ?? '' ) === $userId
            ) {
                $matches[] = $task;
            }
        }

        return $matches;
    }

    // ─── Internal: Erasure Operations ────────────────────────────

    /**
     * Anonymize a user account (replace PII with generic values).
     *
     * The record is preserved with status 'deleted' to maintain referential
     * integrity. The username and email are randomized to free them for reuse.
     *
     * @param string $userId User ID.
     */
    private function anonymizeUserAccount( string $userId ): void
    {
        $user = $this->storage->read( 'users', $userId );

        $randomSuffix = Helpers::randomHex( 4 );

        $user['username']     = 'deleted_' . $randomSuffix;
        $user['email']        = 'deleted_' . $randomSuffix . '@anonymized.invalid';
        $user['first_name']   = '';
        $user['last_name']    = '';
        $user['display_name'] = __( 'privacy.deleted_user' );
        $user['status']       = 'deleted';
        $user['pass_hash']    = '';
        $user['updated_at']   = Helpers::now();

        // Clear any reset tokens.
        $user['password_reset_token']   = null;
        $user['password_reset_expires'] = null;
        $user['force_logout_at']        = Helpers::now();

        $this->storage->write( 'users', $userId, $user );
    }

    /**
     * Anonymize audit log entries for a user.
     *
     * Replaces PII (username, IP) with anonymized values. The entries themselves
     * are preserved for compliance — audit logs should never be fully deleted.
     *
     * @param  string $userId User ID.
     * @return int    Number of entries anonymized.
     */
    private function anonymizeAuditLog( string $userId ): int
    {
        // Keyed by the STORAGE id, which is the only identity these records
        // have. The previous code rebuilt an id from the timestamp as
        // `Ymd-His-0000` when the record carried no `id` field — which it never
        // does — while real audit ids end in `randomHex(4)`. So the write landed
        // on an id nothing was stored under: it CREATED AN ORPHAN holding the
        // anonymised copy and left the original, with the username and IP of a
        // person who had exercised their right to erasure, untouched. The count
        // it returned was true about records written and false about anything
        // being erased (D-115, `GdprAuditAnonymisationTest`).
        $entries = $this->storage->listWithIds( 'audit-log' );
        $count   = 0;

        foreach ( $entries as $id => $entry ) {
            if ( ( $entry['user_id'] ?? '' ) !== $userId ) {
                continue;
            }

            $entry['username']   = '[anonymized]';
            $entry['ip_address'] = '0.0.0.0';
            $entry['user_id']    = null;

            $this->storage->write( 'audit-log', (string) $id, $entry );
            $count++;
        }

        return $count;
    }


    /**
     * Anonymize task assignments for a user.
     *
     * Clears assigned_to and created_by references. Task content is preserved.
     *
     * @param  string $userId User ID.
     * @return int    Number of tasks anonymized.
     */
    private function anonymizeTaskAssignments( string $userId ): int
    {
        $tasks = $this->storage->list( 'tasks' );
        $count = 0;

        foreach ( $tasks as $task ) {
            $modified = false;

            if ( ( $task['assigned_to'] ?? '' ) === $userId ) {
                $task['assigned_to'] = null;
                $modified = true;
            }

            if ( ( $task['created_by'] ?? '' ) === $userId ) {
                $task['created_by'] = null;
                $modified = true;
            }

            if ( $modified ) {
                $taskId = $task['id'] ?? '';
                if ( $taskId !== '' ) {
                    $this->storage->write( 'tasks', $taskId, $task );
                    $count++;
                }
            }
        }

        return $count;
    }
}
