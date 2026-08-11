<?php

/**
 * Klytos CMS — manifest entry 13 (Tasks) renders what §13 specifies.
 *
 * The SERVER-RENDERED half of entry 13's evidence: the stat row, the filter
 * chips, the grouped list, the good-news empty state, and the two corrections
 * this slice made to shipped behaviour — a refused CSRF post that now reports
 * itself, and row actions that name their row instead of being a `title` on a
 * tick character. Geometry, contrast and axe belong to the browser tier and are
 * NOT claimed here.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

/**
 * The Tasks screen's server-rendered contract.
 */
final class TasksHttpTest extends AdminHttpTestCase
{
    /** Its own slot in the reserved band; `KLYTOS_TEST_PORT_OFFSET` shifts the whole band. */
    protected static function serverPort(): int
    {
        return 8116;
    }

    /**
     * Seed a known population before the class runs.
     *
     * A check over a zero population is not evidence (D-079). Without this the
     * grouping, the badges and the row actions all skip on a thin seed, and a
     * skipped test looks exactly like a passing one in a summary line.
     */
    /**
     * Run the task fixture through the real TaskManager.
     *
     * @param array $args Extra arguments, e.g. `['--off']`.
     */
    private static function runFixture( array $args = [] ): int
    {
        $cmd = escapeshellarg( PHP_BINARY ) . ' '
            . escapeshellarg( self::$repoRoot . '/tests/E2E/fixtures/reset-tasks.php' );

        foreach ( $args as $arg ) {
            $cmd .= ' ' . escapeshellarg( (string) $arg );
        }

        exec( $cmd . ' 2>&1', $out, $code );

        return $code;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if ( self::runFixture() !== 0 ) {
            self::markTestSkipped( 'the task fixture could not seed' );
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::runFixture( ['--off'] );

        parent::tearDownAfterClass();
    }

    private function tasks( string $query = '' ): array
    {
        return $this->request( 'installer/admin/tasks.php' . $query, 'owner' );
    }

    public function testTheScreenRendersWithoutAFatal(): void
    {
        $body = $this->tasks()['body'];

        self::assertStringNotContainsString( 'Fatal error', $body );
        self::assertStringNotContainsString( 'TypeError', $body );
        self::assertStringContainsString( '</body>', $body );
    }

    // ─── the stat row ─────────────────────────────────────────────

    public function testTheStatRowCarriesTheThreeBackedFactsAndNoOthers(): void
    {
        $body = $this->tasks()['body'];

        foreach ( ['open', 'progress', 'done'] as $id ) {
            self::assertStringContainsString( 'data-testid="tasks.stat.' . $id . '"', $body );
        }

        // THREE, not §13's four: *Due this week* and *Overdue* need a due date
        // that exists nowhere in the tree, so they are deferred rather than
        // filled with a number nobody measured (DR-013, roadmap §0c). Asserting
        // the count is what stops one being added back from the manifest alone.
        self::assertSame( 3, substr_count( $body, 'data-testid="tasks.stat.' ) );

        // `template-overview-stats.md` §1's floor is three columns. The row
        // meets it BECAUSE *In progress* is built; that is adaptation 87 and it
        // is load-bearing, not decoration.
        self::assertGreaterThanOrEqual( 3, substr_count( $body, 'data-testid="tasks.stat.' ) );
    }

    public function testEveryStatValueIsBoundToItsLabel(): void
    {
        $body = $this->tasks()['body'];

        foreach ( ['open', 'progress', 'done'] as $id ) {
            self::assertMatchesRegularExpression(
                '/aria-labelledby="tasks-stat-' . $id . '-value tasks-stat-' . $id . '-label"/',
                $body
            );
        }
    }

    // ─── the filters ──────────────────────────────────────────────

    public function testTheFiltersAreLinksCarryingAriaCurrentAndNeverTabs(): void
    {
        $body = $this->tasks( '?status=completed' )['body'];

        // accessibility.md §5.4: a filter is a LINK with aria-current, never a
        // tab and never a button. The shipped screen drew `.tab` divs.
        self::assertMatchesRegularExpression(
            '/<a class="k-chip"[^>]*aria-current="true"[^>]*data-testid="tasks\.chip\.completed"/',
            $body
        );
        self::assertStringNotContainsString( 'role="tab"', $body );
    }

    public function testAnUnknownFilterResolvesToOpenRatherThanFailing(): void
    {
        $body = $this->tasks( '?status=not-a-status' )['body'];

        self::assertStringNotContainsString( 'Fatal error', $body );
        self::assertMatchesRegularExpression(
            '/aria-current="true"[^>]*data-testid="tasks\.chip\.open"/',
            $body
        );
    }

    // ─── the body: grouped list, or the good-news empty state ─────

    public function testTheScreenRendersEitherAGroupedListOrTheGoodNewsEmptyState(): void
    {
        $body = $this->tasks()['body'];

        $hasGroup = strpos( $body, 'data-testid="tasks.group.' ) !== false;
        $hasEmpty = strpos( $body, 'data-testid="tasks.empty"' ) !== false;

        self::assertTrue( $hasGroup xor $hasEmpty, 'exactly one of the two bodies renders' );

        if ( $hasGroup ) {
            // §13: each task is a <li>. `.k-collection` is the <ul> that
            // entries 19, 32, 37 and 24 already use — reused, not re-invented.
            self::assertMatchesRegularExpression( '/<ul class="k-collection"/', $body );
        }
    }

    public function testTheFilteredEmptyStateIsNotTheGoodNewsOne(): void
    {
        // Two different sentences for two different facts: `completed` is empty
        // because of the filter, which is not good news about anything.
        $body = $this->tasks( '?status=completed' )['body'];

        self::assertStringContainsString( 'data-testid="tasks.empty"', $body );
        self::assertStringContainsString( 'No tasks in this view', $body );
        self::assertStringNotContainsString( 'Nothing needs your attention', $body );
    }

    public function testTheGoodNewsEmptyStateIsReachedByEmptyingTheQueue(): void
    {
        // REACHED, not skipped past. The fixture's rows are removed, the open
        // view is read, and the rows are put back — so the state under test is
        // the real one the product renders and not a hypothesis.
        $this->runFixture( ['--off'] );

        try {
            $body = $this->tasks( '?status=open' )['body'];

            if ( strpos( $body, 'data-testid="tasks.empty"' ) === false ) {
                self::markTestSkipped( 'the base seed carries open tasks of its own' );
            }

            // template-overview-stats.md §2's good-news state. The shipped
            // screen said "No tasks found" and told the reader to call an MCP
            // tool — a gap dressed as an instruction.
            self::assertStringNotContainsString( 'No tasks found', $body );
            self::assertStringNotContainsString( 'klytos_create_task', $body );
            self::assertStringContainsString( 'Nothing needs your attention', $body );
        } finally {
            $this->runFixture( [] );
        }
    }

    // ─── the two corrections to shipped behaviour ─────────────────

    public function testARefusedCsrfPostIsReportedRatherThanSwallowed(): void
    {
        // The shipped screen wrote `if ( ... && klytos_verify_csrf() )` with no
        // `else`, so a refusal re-rendered as if nothing had been sent — the
        // FIFTH screen of this build with the identical defect.
        $response = $this->post(
            'installer/admin/tasks.php',
            ['action' => 'complete', 'task_id' => 'whatever', 'csrf_token' => 'wrong'],
            'owner'
        );

        self::assertStringContainsString( 'data-testid="tasks.error"', $response['body'] );
        self::assertMatchesRegularExpression(
            '/data-testid="tasks\.error"[^>]*role="alert"|role="alert"[^>]*data-testid="tasks\.error"/',
            $response['body']
        );
    }

    public function testNoRowActionIsAnUnnamedGlyph(): void
    {
        $body = $this->tasks()['body'];

        self::assertStringContainsString(
            'data-testid="tasks.group.',
            $body,
            'the fixture seeds open tasks, so a group must render'
        );

        // The shipped screen drew "✓" and "✕" with only a `title` attribute.
        // `title` is announced by no screen reader reliably and is unreachable
        // by touch, so each control now names its own row (D-098's correction).
        self::assertStringNotContainsString( 'title="Complete"', $body );
        self::assertStringNotContainsString( 'title="Dismiss"', $body );

        preg_match_all( '/data-testid="tasks\.(complete|dismiss)\.[^"]*"/', $body, $ids );
        foreach ( $ids[0] as $_ ) {
            // Every one of them carries an aria-label, and the label is not the
            // bare verb: it names the task.
            self::assertMatchesRegularExpression(
                '/aria-label="[^"]+ — [^"]+"[^>]*data-testid="tasks\.(complete|dismiss)\./',
                $body
            );
            break;
        }
    }

    public function testNoCatalogueKeyReachesTheScreen(): void
    {
        $body = strip_tags( $this->tasks()['body'] );

        // The shipped screen carried NOT ONE `__()` call — every string was
        // hardcoded English in twenty locales. A missing key renders as the key
        // itself, so this asserts none of them reaches the page.
        //
        // The check is built from the CATALOGUE's own key list rather than from
        // a `tasks\.[a-z_]+` pattern, because two strings of that shape are on
        // the page legitimately and neither is copy: the dev bar prints
        // `tasks.php`, and `tasks.manage` is a CAPABILITY name. A pattern would
        // have to be taught about both, and would go stale the moment a third
        // appeared; the key list cannot.
        $catalogue = json_decode(
            (string) file_get_contents( self::$repoRoot . '/installer/core/lang/en.json' ),
            true
        );

        $keys = array_keys( $catalogue['tasks'] ?? [] );
        self::assertNotEmpty( $keys, 'the `tasks` catalogue root must exist' );

        foreach ( $keys as $key ) {
            self::assertStringNotContainsString(
                'tasks.' . $key,
                $body,
                "the key `tasks.{$key}` reached the rendered page"
            );
        }
    }
}
