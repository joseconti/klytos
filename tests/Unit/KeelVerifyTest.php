<?php

/**
 * Klytos CMS — keel-verify must keep running every check it claims to run
 * (Sprint 1, slice 9 / Phase 5 §1a).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the guard.
 *
 * keel-verify exists so that promises are checked by a machine rather than by
 * eye. That only holds while keel-verify itself still executes every check —
 * and a check that silently stops running is invisible, because the script goes
 * on printing OK. This project has already been bitten by exactly that decay
 * mode twice: assertConfigNotMutated() was inert for two slices while being
 * counted as evidence (D-039, L-010), and the integration tier's missing hook
 * reset survived five slices because nothing exercised it (D-042, L-012).
 *
 * So the check names are asserted explicitly, one row per check. Deleting a
 * check, renaming it, or letting it throw before it reports now fails the
 * suite — which is the only way "10 checks passed" stays a measurement instead
 * of a habit.
 *
 * Extends TestCase directly rather than UnitTestCase: this shells out to a
 * script that must work on a bare checkout, so the per-test encryption key and
 * throwaway storage the base case builds would be setup cost with no consumer.
 *
 * Requires git. Two checks depend on `git check-attr` and SKIP (uncounted) when
 * git is absent, which would make the script report 8 checks rather than 10 — so
 * in that environment this test fails by design rather than by accident. That is
 * the right trade here: git is a hard prerequisite of the whole Keel workflow,
 * and a suite that cannot see the repository has nothing useful to say. Flagged
 * by this slice's own code review as a contradiction between two files written
 * in the same slice, and recorded here rather than left implicit.
 *
 * Boundary, stated so it is not mistaken for a stronger guarantee: this asserts
 * that each check RAN and reported, not that each check is correct. Correctness
 * was established by injecting a violation per check and observing the failure,
 * then reverting — the evidence is in docs/05-test-points.md. A check whose
 * logic is later gutted while its name survives would still pass here.
 */
final class KeelVerifyTest extends TestCase
{
    /**
     * Every check keel-verify is expected to report, by the stable part of its
     * name. Adding a check to the script means adding its row here.
     *
     * @var array<int, string>
     */
    private const EXPECTED_CHECKS = [
        'authorization gate covers every admin surface',
        'the central gate is invoked from admin/bootstrap.php',
        'docs/api/INDEX.md summary counts match its rows',
        'docs/api/INDEX.md parity',
        'locale catalogues agree on their key set',
        'no placeholder copy in distributable surfaces',
        'changelog order oldest',
        'every registered MCP tool has a capability-map entry',
        'version touchpoints in sync',
        'runtime assets survive the release archive',
        // Added 2026-07-28 with the Keel v3.5.0 → v5.0.0 reconciliation (D-067).
        // This test failing on the count is the guard doing its job, not a
        // nuisance: six checks appeared and something had to notice. It is
        // updated deliberately, which is the only way the count may ever move.
        'code map: every [E] path exists on disk',
        'internal documentation links resolve',
        'README.md link backlog',
        'every cited command exists',
        'conformance sweep has no unexplained gap',
        'first-party lint/analysis suppressions',
        // Added 2026-07-29 with DR-003's resolution (D-074, L-030). A broken
        // cross-document `<use href="…#ks-name">` renders NOTHING — no console
        // error, no failed request — so it is the one icon defect that cannot be
        // noticed by using the admin. Registered here deliberately, per the
        // docblock above: the count may only move on purpose.
        'every #ks-* the admin references resolves to a sprite <symbol>',
    ];

    /**
     * Run keel-verify and capture its output and exit status.
     *
     * @return array{output: string, status: int}
     */
    private function runKeelVerify(): array
    {
        $repoRoot = dirname( __DIR__, 2 );
        $output   = [];
        $status   = 1;

        exec(
            'php ' . escapeshellarg( $repoRoot . '/scripts/keel-verify' ) . ' 2>&1',
            $output,
            $status
        );

        return [ 'output' => implode( "\n", $output ), 'status' => $status ];
    }

    /**
     * The repository must satisfy its own release linter.
     */
    public function testKeelVerifyPassesOnTheCurrentTree(): void
    {
        $result = $this->runKeelVerify();

        $this->assertSame(
            0,
            $result['status'],
            "keel-verify failed on the current tree:\n" . $result['output']
        );
    }

    /**
     * Every expected check must appear in the output.
     *
     * This is the assertion that catches a check quietly disappearing — the
     * failure mode L-010 records, where the absence of a check reads exactly
     * like the check passing.
     */
    public function testEveryExpectedCheckActuallyRuns(): void
    {
        $output = $this->runKeelVerify()['output'];

        foreach ( self::EXPECTED_CHECKS as $check ) {
            $this->assertStringContainsString(
                $check,
                $output,
                "keel-verify no longer reports the check '{$check}'. A check that stops "
                    . 'running is invisible: the script still prints OK. Either restore it or '
                    . 'remove its row from EXPECTED_CHECKS deliberately.'
            );
        }
    }

    /**
     * The reported check count must match the number of checks expected.
     *
     * Asserted separately from the names above because they fail differently: a
     * renamed check trips the names, while a check that returns early without
     * reporting trips the count.
     */
    public function testTheReportedCheckCountMatchesTheExpectedSet(): void
    {
        $output = $this->runKeelVerify()['output'];

        // "run", not "passed": the summary used to report the TOTAL as the number
        // passed, counting the two WARN checks twice — once as passes and again as
        // warnings. It now names how many ran and how many of those passed.
        $this->assertSame(
            1,
            preg_match( '/OK — (\d+) check\(s\) run/u', $output, $match ),
            "keel-verify did not print its summary line:\n" . $output
        );

        $this->assertSame(
            count( self::EXPECTED_CHECKS ),
            (int) $match[1],
            'keel-verify reported ' . $match[1] . ' checks; ' . count( self::EXPECTED_CHECKS )
                . ' are expected.'
        );
    }

    /**
     * A WARN must never be silently upgraded into a pass.
     *
     * The two warning checks (H-01 version drift, NEW-27 stripped guides) report
     * real, currently-broken properties whose fix belongs to Phase 7. If either
     * stops warning, that is either a genuine fix — in which case this test is
     * updated deliberately — or the check went inert, which is the whole reason
     * the WARN mechanism is not just a comment.
     */
    public function testTheKnownWarningsAreStillReported(): void
    {
        $output = $this->runKeelVerify()['output'];

        $this->assertStringContainsString(
            'WARN  version touchpoints in sync',
            $output,
            'The version touchpoints stopped disagreeing. If audit H-01 was genuinely fixed '
                . '(Phase 7 owns it), update this test deliberately.'
        );

        $this->assertStringContainsString(
            'WARN  runtime assets survive the release archive',
            $output,
            'installer/core/guides/ is no longer stripped from the release archive. If NEW-27 '
                . 'was genuinely fixed (Phase 7 / H-02 owns it), update this test deliberately.'
        );

        // Added 2026-07-28 (D-067). Baseline-locked exactly like the lint
        // baseline (D-025): the ten broken README links predate the check and
        // the D-017 editorial pass owns them, so they WARN rather than fail —
        // but a NEW broken link fails, so the number can only go down. This
        // assertion exists so that "the backlog stopped being reported" cannot
        // pass for "the backlog was fixed".
        $this->assertStringContainsString(
            'WARN  README.md link backlog',
            $output,
            'The README link backlog stopped being reported. If D-017\'s editorial pass '
                . 'genuinely fixed the ten dead links, remove them from $knownBroken in '
                . 'scripts/keel-verify and update this test deliberately.'
        );
    }
}
