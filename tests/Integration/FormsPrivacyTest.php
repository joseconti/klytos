<?php

/**
 * Klytos CMS — a person's form entries are exported and erased with the rest of their data.
 *
 * THE GAP THIS REPRODUCES. Core's privacy manager owned a `core:form_submissions`
 * section that read a collection called `form-submissions`, and **nothing in the
 * product has ever written to that name** — Klytos Forms stores entries in
 * `form-entries` (`FormManager.php:36`). The section was gated on finding
 * matches, so it never even appeared: a person's form data was silently absent
 * from both the export and the erasure, and nothing anywhere said so. An
 * uncovered category that announces itself is a gap; one that is invisible is a
 * compliance claim nobody can check.
 *
 * The fix is not to point core at a plugin's private collection. The privacy
 * system already carries three extension points for exactly this —
 * `privacy.erasable_data` to declare, `privacy.erase_plugin_data` to erase and
 * `privacy.export_data` to export — and the erasure switch's `default:` branch
 * already says "Plugin section — handled via filter below". So Forms owns its own
 * data, core stops knowing the collection exists, and any other plugin holding
 * personal data gets covered by the same route (D-116).
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
 * Personal data held by a plugin is still personal data.
 */
final class FormsPrivacyTest extends IntegrationTestCase
{
    private const PERSON = 'erasure-subject@example.test';
    private const OTHER  = 'someone-else@example.test';

    private string $formId = '';

    protected function setUp(): void
    {
        parent::setUp();

        if ( ! function_exists( 'klytos_forms' ) ) {
            $this->markTestSkipped( 'the Klytos Forms plugin is not active in this install' );
        }

        $form = klytos_forms()->createForm( [
            'title'  => 'Privacy fixture form',
            'fields' => [
                ['id' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                ['id' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => false],
            ],
        ] );

        $this->formId = (string) ( $form['id'] ?? '' );
        $this->assertNotSame( '', $this->formId, 'the fixture form was created' );

        $this->submit( self::PERSON, 'first message' );
        $this->submit( self::PERSON, 'second message' );
        $this->submit( self::OTHER, 'not mine' );
    }

    private function submit( string $email, string $message ): void
    {
        $result = klytos_forms()->submitForm(
            $this->formId,
            ['email' => $email, 'message' => $message],
            [],
            ['ip' => '203.0.113.9']
        );

        $this->assertTrue(
            (bool) ( $result['success'] ?? false ),
            'the fixture submission was accepted: ' . json_encode( $result['errors'] ?? [] )
        );
    }

    private function makeManager(): PrivacyManager
    {
        return new PrivacyManager(
            $this->storage,
            new UserManager( $this->storage ),
            new AuditLog( $this->storage )
        );
    }

    private function seedUser( string $id, string $email ): void
    {
        $this->storage->write( 'users', $id, [
            'id'           => $id,
            'username'     => 'subject',
            'email'        => $email,
            'display_name' => 'Subject',
            'role'         => 'editor',
            'status'       => 'active',
            'created_at'   => gmdate( 'c' ),
        ] );
    }

    /** The person's form data is OFFERED as an erasable section, by name. */
    public function testTheFormsSectionIsDeclaredForSomeoneWhoHasEntries(): void
    {
        $this->seedUser( 'subject-1', self::PERSON );

        $sections = $this->makeManager()->collectErasableData( 'subject-1' );
        $ids      = array_column( $sections, 'id' );

        $this->assertContains(
            'klytos-forms:entries',
            $ids,
            'the plugin declares the section it owns — an uncovered category must not be invisible'
        );

        $this->assertNotContains(
            'core:form_submissions',
            $ids,
            'core no longer offers a section over a collection nothing writes'
        );
    }

    /** Somebody with no entries is offered nothing, rather than an empty section. */
    public function testNoSectionIsDeclaredForSomeoneWithNoEntries(): void
    {
        $this->seedUser( 'subject-2', 'nobody@example.test' );

        $ids = array_column( $this->makeManager()->collectErasableData( 'subject-2' ), 'id' );

        $this->assertNotContains( 'klytos-forms:entries', $ids );
    }

    /**
     * THE REPRODUCTION: erasing really removes the person's entries, and only theirs.
     */
    public function testErasureRemovesTheirEntriesAndLeavesEverybodyElsesAlone(): void
    {
        $this->seedUser( 'subject-1', self::PERSON );

        $before = $this->storage->list( 'form-entries' );
        $this->assertCount( 3, $before, 'two entries for the subject, one for somebody else' );

        $results = $this->makeManager()->eraseUserData(
            'subject-1',
            ['klytos-forms:entries'],
            'admin-1'
        );

        $after = $this->storage->list( 'form-entries' );

        $this->assertCount( 1, $after, 'both of the subject\'s entries are gone' );
        $this->assertSame(
            self::OTHER,
            $after[0]['data']['email'] ?? '',
            'the entry left standing belongs to the other person'
        );

        $row = null;
        foreach ( $results as $candidate ) {
            if ( ( $candidate['section'] ?? '' ) === 'klytos-forms:entries' ) {
                $row = $candidate;
            }
        }

        $this->assertNotNull( $row, 'the erasure reports on the section it was asked to erase' );
        $this->assertSame( 2, $row['count'] ?? null, 'and the count is what it actually deleted' );
    }

    /** A section the caller did not select is never erased. */
    public function testAnUnselectedSectionIsNotErased(): void
    {
        $this->seedUser( 'subject-1', self::PERSON );

        $this->makeManager()->eraseUserData( 'subject-1', ['core:audit_log'], 'admin-1' );

        $this->assertCount(
            3,
            $this->storage->list( 'form-entries' ),
            'erasing the audit log does not touch form entries'
        );
    }

    /** The person's entries appear in their data export. */
    public function testTheExportCarriesTheirEntries(): void
    {
        $this->seedUser( 'subject-1', self::PERSON );

        $json = $this->makeManager()->exportAsJson( 'subject-1' );

        $this->assertStringContainsString( 'first message', $json );
        $this->assertStringContainsString( 'second message', $json );
        $this->assertStringNotContainsString(
            'not mine',
            $json,
            'and never somebody else\'s'
        );
    }
}
