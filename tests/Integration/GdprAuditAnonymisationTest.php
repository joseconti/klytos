<?php

/**
 * Klytos CMS — a GDPR erasure of the audit log really anonymises the entries.
 *
 * THE BUG THIS REPRODUCES, and it is the worst of the seven the storage sweep
 * found, because it reported success while doing the opposite of its job.
 *
 * `PrivacyManager::anonymizeAuditLog()` read the entries with `list()`, stripped
 * the PII from its in-memory copy, and wrote it back under an id produced by
 * `reconstructAuditEntryId()`. That helper's comment claimed "the storage layer
 * typically includes 'id' in the read data" — it does not — so it fell through
 * to rebuilding the id as `Ymd-His-0000`. Real audit ids are
 * `Ymd-His-<randomHex(4)>` (`audit-log.php:99`).
 *
 * So the write did not update the entry. It **created a new orphan record**
 * holding the anonymised copy, and left the original — with the username and the
 * IP address of a person who had exercised their right to erasure — untouched in
 * the collection. `eraseUserData()` then reported a count, and the report was
 * true about records written and false about anything being erased.
 *
 * Written BEFORE the fix and seen failing (D-115).
 *
 * INTEGRATION and not unit, deliberately. `eraseUserData()` builds user-facing
 * strings through `__()`, which `App::registerI18nGlobal()` declares INSIDE the
 * `Klytos\Core` namespace and only once an App has booted. A stub in the test
 * bootstrap looked like the cheap way in and is not: `bootI18n()` guards its own
 * declaration with `function_exists( '__' )`, so a global stub wins the race and
 * silently strips the integration tier of its translations — measured, and it
 * also broke the very test that pins the namespaced declaration. A test that
 * needs a booted App belongs in the tier that boots one.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Core\AuditLog;
use Klytos\Core\PrivacyManager;
use Klytos\Core\UserManager;
use Klytos\Tests\IntegrationTestCase;

/**
 * An erasure that reports success must have erased something.
 */
final class GdprAuditAnonymisationTest extends IntegrationTestCase
{
    private const COLLECTION = 'audit-log';

    private function makeManager(): PrivacyManager
    {
        return new PrivacyManager(
            $this->storage,
            new UserManager( $this->storage ),
            new AuditLog( $this->storage )
        );
    }

    /** `eraseUserData()` looks the person up first, so they have to exist. */
    private function seedUser( string $id, string $username ): void
    {
        $this->storage->write( 'users', $id, [
            'id'           => $id,
            'username'     => $username,
            'email'        => $username . '@example.test',
            'display_name' => $username,
            'role'         => 'editor',
            'status'       => 'active',
            'created_at'   => gmdate( 'c' ),
        ] );
    }

    /** Write an audit entry in the shape `AuditLog::record()` produces. */
    private function seedEntry( string $id, ?string $userId, string $username ): void
    {
        $this->storage->write( self::COLLECTION, $id, [
            'timestamp'   => gmdate( 'c' ),
            'user_id'     => $userId,
            'username'    => $username,
            'action'      => 'login',
            'entity_type' => 'user',
            'entity_id'   => $userId,
            'details'     => [],
            'source'      => 'admin',
            'ip_address'  => '203.0.113.9',
        ] );
    }

    /**
     * THE REPRODUCTION.
     *
     * Before the fix this left the PII entry in place and added a second,
     * anonymised record beside it — so the collection GREW and the personal data
     * survived.
     */
    public function testErasureAnonymisesTheEntryInPlaceAndCreatesNoOrphan(): void
    {
        $this->seedUser( 'user-1', 'alice' );
        $this->seedUser( 'user-2', 'bob' );

        // Ids in the real format: Ymd-His plus four random hex characters.
        $this->seedEntry( gmdate( 'Ymd-His' ) . '-a1b2', 'user-1', 'alice' );
        $this->seedEntry( gmdate( 'Ymd-His' ) . '-c3d4', 'user-2', 'bob' );

        $before = $this->storage->listWithIds( self::COLLECTION );
        $this->assertCount( 2, $before );

        $result = $this->makeManager()->eraseUserData( 'user-1', ['core:audit_log'], 'admin-1' );

        $after = $this->storage->listWithIds( self::COLLECTION );

        /*
         * NOT "the collection has exactly two records": the erasure legitimately
         * records ITSELF in the audit log, so one new entry is correct and
         * forbidding it would be a test that fails on right behaviour.
         *
         * What must hold is narrower and is exactly the defect: both seeded
         * records are still under their own ids, and nothing was written under
         * the fabricated `Ymd-His-0000` that `reconstructAuditEntryId()`'s
         * fallback produced.
         */
        foreach ( array_keys( $before ) as $id ) {
            $this->assertArrayHasKey(
                $id,
                $after,
                'the original entry was updated in place, not replaced'
            );
        }

        foreach ( array_keys( $after ) as $id ) {
            $this->assertStringEndsNotWith(
                '-0000',
                (string) $id,
                'an orphan under a fabricated id — the write did not reach the real record'
            );
        }

        // And the PII is genuinely gone from the erased person's entry.
        $mine = null;
        foreach ( $after as $entry ) {
            if ( ( $entry['username'] ?? '' ) === 'alice' ) {
                $this->fail( 'the username of the erased user is still stored' );
            }
            if ( ( $entry['ip_address'] ?? '' ) === '203.0.113.9'
                && ( $entry['username'] ?? '' ) !== 'bob'
                && ( $entry['user_id'] ?? '' ) !== 'admin-1' ) {
                $this->fail( 'the IP address of the erased user is still stored' );
            }
            if ( ( $entry['username'] ?? '' ) === '[anonymized]' ) {
                $mine = $entry;
            }
        }

        $this->assertNotNull( $mine, 'the erased user\'s entry is present and anonymised' );
        $this->assertNull( $mine['user_id'], 'the user id is cleared' );
        $this->assertSame( '0.0.0.0', $mine['ip_address'] );

        // The reported count is about work actually done.
        $auditResult = null;
        foreach ( $result['results'] ?? $result as $row ) {
            if ( ( $row['section'] ?? '' ) === 'core:audit_log' ) {
                $auditResult = $row;
            }
        }
        $this->assertNotNull( $auditResult, 'the erasure reports on the audit-log section' );
        $this->assertSame( 1, $auditResult['count'] ?? null, 'one entry anonymised, and it says so' );
    }

    /** Another person's audit entry is never touched by someone else's erasure. */
    public function testAnotherUsersEntryIsUntouched(): void
    {
        $this->seedUser( 'user-1', 'alice' );
        $this->seedUser( 'user-2', 'bob' );
        $this->seedEntry( gmdate( 'Ymd-His' ) . '-a1b2', 'user-1', 'alice' );
        $this->seedEntry( gmdate( 'Ymd-His' ) . '-c3d4', 'user-2', 'bob' );

        $this->makeManager()->eraseUserData( 'user-1', ['core:audit_log'], 'admin-1' );

        $survivors = array_values( array_filter(
            $this->storage->list( self::COLLECTION ),
            static fn( array $e ): bool => ( $e['username'] ?? '' ) === 'bob'
        ) );

        $this->assertCount( 1, $survivors, 'bob\'s entry is still there' );
        $this->assertSame( 'user-2', $survivors[0]['user_id'] );
        $this->assertSame( '203.0.113.9', $survivors[0]['ip_address'], 'and it is not anonymised' );
    }
}
