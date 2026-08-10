<?php

/**
 * Klytos CMS — ConsentManager reads back the declarations it stores.
 *
 * FOUND BY DRIVING, while building manifest entry 25 (Phase 4 Step 4, stage 5).
 * The screen's fixture created two declarations, the files landed on disk, and
 * the audit reported "declared cookies: 0". Nothing anywhere said why.
 *
 * `StorageInterface::list()` is documented as returning "Array of decrypted
 * RECORDS", and every other manager in core uses it that way. `ConsentManager`
 * alone treated the return as a list of IDs:
 *
 *     $ids = $this->storage->list( self::COLLECTION );
 *     foreach ( $ids as $id ) {
 *         try {
 *             $declarations[] = $this->storage->read( self::COLLECTION, $id );
 *         } catch ( \Throwable ) {
 *             continue;
 *         }
 *     }
 *
 * so `read()` was called with an ARRAY where it wants a string id, threw on
 * every single record, and the bare `catch` dropped it. The method therefore
 * returned an empty array on every install, always — and the `catch` is what
 * made it silent, which is why it survived to release.
 *
 * WHAT IT COST, stated plainly because this is a compliance feature: the cookie
 * audit was empty on every site, the JSON and CSV exports contained nothing, and
 * `klytos_list_consent_declarations` and `klytos_get_consent_audit` returned
 * nothing over MCP. A GDPR audit trail that reports "no cookies declared"
 * whatever is declared is worse than an absent one: it answers the question
 * confidently and wrongly.
 *
 * These tests were written BEFORE the fix and observed FAILING against it — the
 * unconditional rule that a bug fix starts from a reproduction test, which holds
 * at every value of `Test-first policy:`. The failure line is recorded in
 * `docs/05-test-points.md`.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Core\ConsentManager;
use Klytos\Tests\IntegrationTestCase;

final class ConsentDeclarationsTest extends IntegrationTestCase
{
    private ConsentManager $consent;

    protected function setUp(): void
    {
        parent::setUp();

        // The real manager over the booted App's own storage. This tier and
        // not the unit tier, and the reason is the same one recorded for
        // authorization: `getConfig()` reads through `klytos_get_option()`,
        // which resolves `App::getOptionsManager()`, so a ConsentManager
        // constructed against a bare temp-dir storage cannot validate a
        // category or read its own config. Attempted in tests/Unit first; the
        // red it produced named the absent App rather than the defect, which is
        // not a red worth trusting.
        $this->consent = new ConsentManager( $this->storage );

        // Start from an empty collection whatever the playground happens to
        // hold. The tier snapshots and restores state around each test, so this
        // is undone afterwards — but a declaration seeded BEFORE the snapshot
        // is inside it, and counting rows is exactly what these tests do.
        foreach ( $this->consent->getPluginDeclarations() as $existing ) {
            $this->consent->deletePluginDeclaration( (string) $existing['plugin_id'] );
        }
    }

    /**
     * The reproduction: what is written comes back.
     *
     * @return void
     */
    public function testADeclarationThatWasStoredIsReadBack(): void
    {
        $this->consent->savePluginDeclaration( [
            'plugin_id'   => 'analytics-plugin',
            'name'        => 'Analytics Plugin',
            'category'    => 'analytics',
            'description' => 'Measures traffic.',
            'cookies'     => [
                [ 'name' => '_pk_id', 'duration' => '13 months', 'description' => 'Visitor id.' ],
            ],
            'scripts'     => [ 'https://example.invalid/matomo.js' ],
        ] );

        $declarations = $this->consent->getPluginDeclarations();

        $this->assertCount(
            1,
            $declarations,
            'A declaration was stored and getPluginDeclarations() returned nothing. '
            . 'StorageInterface::list() yields RECORDS, not ids.'
        );
        $this->assertSame( 'analytics-plugin', $declarations[0]['plugin_id'] );
        $this->assertSame( 'Analytics Plugin', $declarations[0]['name'] );
        $this->assertSame( '_pk_id', $declarations[0]['cookies'][0]['name'] );
    }

    /**
     * The audit's totals count what is really there.
     *
     * The counts are the half a person actually reads, and they were the half
     * that read zero. Asserted separately from the list above because the audit
     * has its own aggregation on top of it.
     *
     * @return void
     */
    public function testTheAuditCountsEveryDeclaredCookieAndScript(): void
    {
        $this->consent->savePluginDeclaration( [
            'plugin_id' => 'essential-plugin',
            'name'      => 'Essential Plugin',
            'category'  => 'necessary',
            'cookies'   => [ [ 'name' => 'session', 'duration' => 'Session' ] ],
            'scripts'   => [],
        ] );

        $this->consent->savePluginDeclaration( [
            'plugin_id' => 'analytics-plugin',
            'name'      => 'Analytics Plugin',
            'category'  => 'analytics',
            'cookies'   => [
                [ 'name' => '_pk_id', 'duration' => '13 months' ],
                [ 'name' => '_pk_ses', 'duration' => '30 minutes' ],
            ],
            'scripts'   => [ 'https://example.invalid/matomo.js' ],
        ] );

        $audit = $this->consent->getAuditReport();

        $this->assertSame( 2, $audit['total_plugins'], 'total_plugins is wrong.' );
        $this->assertSame( 3, $audit['total_cookies'], 'total_cookies is wrong.' );
        $this->assertSame( 1, $audit['total_scripts'], 'total_scripts is wrong.' );
    }

    /**
     * Each declaration lands under the category it declared.
     *
     * The grouping is what the audit table draws its Type column from, so a
     * declaration silently landing in the wrong group would mislabel a cookie's
     * legal basis — which is the one thing this screen exists to get right.
     *
     * @return void
     */
    public function testDeclarationsAreGroupedUnderTheirOwnCategory(): void
    {
        $this->consent->savePluginDeclaration( [
            'plugin_id' => 'marketing-plugin',
            'name'      => 'Marketing Plugin',
            'category'  => 'marketing',
            'cookies'   => [ [ 'name' => 'vuid', 'duration' => '2 years' ] ],
        ] );

        $audit = $this->consent->getAuditReport();

        $this->assertCount( 1, $audit['categories']['marketing']['plugins'] );
        $this->assertSame(
            'marketing-plugin',
            $audit['categories']['marketing']['plugins'][0]['plugin_id']
        );
        $this->assertSame( [], $audit['categories']['analytics']['plugins'] );
    }

    /**
     * A deleted declaration stops being read back.
     *
     * The other direction of the same parity: a reader that returned nothing
     * would have passed a delete test trivially, so this is only meaningful
     * beside the first test in this class.
     *
     * @return void
     */
    public function testADeletedDeclarationIsGone(): void
    {
        $this->consent->savePluginDeclaration( [
            'plugin_id' => 'temporary-plugin',
            'name'      => 'Temporary Plugin',
            'category'  => 'functional',
        ] );

        $this->assertCount( 1, $this->consent->getPluginDeclarations() );

        $this->consent->deletePluginDeclaration( 'temporary-plugin' );

        $this->assertSame( [], $this->consent->getPluginDeclarations() );
    }

    /**
     * A record the storage cannot return does not take the whole audit down.
     *
     * The old `catch ( \Throwable ) { continue; }` was doing two jobs: hiding
     * the defect above, and skipping a genuinely unreadable record. Removing it
     * wholesale would trade a silent empty list for a fatal on one bad file, so
     * the skip stays — narrowed to records that are not arrays, which is the
     * only shape `list()` can yield that the rest of the method cannot use.
     *
     * @return void
     */
    public function testANonRecordEntryIsSkippedRatherThanFatal(): void
    {
        $this->consent->savePluginDeclaration( [
            'plugin_id' => 'good-plugin',
            'name'      => 'Good Plugin',
            'category'  => 'necessary',
        ] );

        // Written straight into the collection, bypassing the manager, because
        // the manager is what refuses to store this shape — which is the point:
        // the reader must survive a file the writer would never have produced
        // (a partial write, a hand edit, a failed migration).
        $this->storage->write( ConsentManager::class, 'ignored', [ 'x' => 1 ] );
        $this->storage->write( 'consent_declarations', 'malformed', [ 'no_plugin_id' => true ] );

        $declarations = $this->consent->getPluginDeclarations();

        $this->assertCount(
            1,
            $declarations,
            'The malformed record should be skipped and the good one still returned.'
        );
        $this->assertSame( 'good-plugin', $declarations[0]['plugin_id'] );
    }
}
