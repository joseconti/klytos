<?php

/**
 * Klytos CMS — proof that the config-mutation guard can actually fail
 * (Sprint 1, slice 5; repairs the guard introduced by D-030).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\IntegrationTestCase;
use PHPUnit\Framework\AssertionFailedError;

/**
 * Regression cover for a guard that was INERT for two slices.
 *
 * D-030 introduced assertConfigNotMutated() as "the honest half" of the
 * isolation primitive: the part that refuses to pretend a file restore can
 * refresh App::$config, and fails loudly instead. It could not fail. It runs
 * after restorePlayground() — correct, so the playground is left clean even on
 * a failing assertion — but it re-hashed the file the restore had just put
 * back, so every comparison was the snapshot against itself. Slice 5 proved it
 * with a probe that wrote a marker key into core config and passed green.
 *
 * That is the L-009 shape exactly: a check that cannot fail is not a check, and
 * it had been carrying the credibility of the whole tier since slice 3.
 *
 * So the repair gets the same treatment slice 2 gave the manifest drift guard
 * and slice 3 gave the migration test — it must DEMONSTRATE the property under
 * a real mutation, permanently, rather than be believed. These two tests are
 * the reason the guard cannot quietly go inert again.
 *
 * Isolation is off here because these tests drive the primitive by hand:
 * snapshot, mutate, restore, then inspect the verdict. Letting tearDown run it
 * a second time would compare against state this class has already consumed.
 */
final class PlaygroundGuardTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        // Recorded reason, per the opt-out rule in IntegrationTestCase: this
        // class IS the test of the isolation primitive, so it cannot also be a
        // client of it. It snapshots and restores explicitly in each test.
        $this->isolatePlaygroundState = false;

        parent::setUp();
    }

    /**
     * A real config mutation trips the guard.
     *
     * The test that would have caught the inert version on the day it shipped.
     *
     * @return void
     */
    public function testARealConfigMutationTripsTheGuard(): void
    {
        $this->snapshotPlayground();

        $configPath = KLYTOS_INSTALLER_PATH . '/config';
        $config     = $this->storage->readFrom( $configPath, 'config.json.enc' );

        $config['zz_guard_regression_marker'] = 'slice-5';
        $this->storage->writeTo( $configPath, 'config.json.enc', $config );

        $this->restorePlayground();

        // self::fail() also throws AssertionFailedError, so the verdict is
        // carried in a flag rather than by reaching or not reaching a line —
        // otherwise this test could "pass" by catching its own failure.
        $fired = false;

        try {
            $this->assertConfigNotMutated();
        } catch ( AssertionFailedError $e ) {
            $fired = true;

            self::assertStringContainsString(
                'mutated installer/config/config.json.enc',
                $e->getMessage(),
                'The guard fired, but not with the message that tells a developer what to do.'
            );
        }

        self::assertTrue(
            $fired,
            'The config-mutation guard did NOT fire on a real mutation — it is inert again. '
            . 'Check that assertConfigNotMutated() compares the state captured BEFORE '
            . 'restorePlayground() overwrote it, not the file as it stands now.'
        );

        // The restore above already put the playground back; assert it rather
        // than trust it, since this class opts out of automatic rollback.
        self::assertArrayNotHasKey(
            'zz_guard_regression_marker',
            $this->storage->readFrom( $configPath, 'config.json.enc' ),
            'This test must not leave its marker key in the playground.'
        );
    }

    /**
     * The scheduler's own heartbeat does NOT trip the guard.
     *
     * ActionScheduler::setConfigValue() writes `scheduler_last_run` whenever
     * due actions are processed, and App::boot() triggers that on EVERY
     * request. The HTTP tests boot a real server per request, so core config is
     * rewritten from another process as a matter of course — which made ten
     * healthy tests fail the moment the guard was repaired. A guard that cannot
     * tell a background heartbeat from a test's own write would be turned off
     * within a week, and then it would be inert for a second time.
     *
     * This also pins the subtler half: the file is ENCRYPTED, so rewriting
     * byte-identical content still yields different ciphertext. The comparison
     * has to be on decrypted content or it cannot distinguish "changed" from
     * "written again".
     *
     * @return void
     */
    public function testTheSchedulerHeartbeatAloneDoesNotTripTheGuard(): void
    {
        $this->snapshotPlayground();

        $configPath = KLYTOS_INSTALLER_PATH . '/config';
        $config     = $this->storage->readFrom( $configPath, 'config.json.enc' );

        $config['scheduler_last_run'] = time() + 9999;
        $this->storage->writeTo( $configPath, 'config.json.enc', $config );

        $this->restorePlayground();

        // Must not throw. If it does, PHPUnit reports it as this test failing,
        // which is the correct outcome.
        $this->assertConfigNotMutated();

        self::assertTrue(
            true,
            'The guard tolerated a volatile-key-only change, as it must.'
        );
    }
}
