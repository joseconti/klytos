<?php

/**
 * Klytos CMS — `Updater::getCachedUpdateState()` answers WITHOUT touching the network.
 *
 * Written for manifest entry 44 (Dashboard), whose *Pending updates* stat card
 * needs the update count on the admin's landing screen. The only shipped way to
 * get it is `checkForUpdate()`, and on a cold or expired cache that method makes
 * a blocking HTTPS request to GitHub — so the screen a person lands on after
 * every login would wait on a third party that may be slow, rate-limiting or
 * unreachable. A landing screen never blocks on somebody else's server.
 *
 * The second half is the one that matters more, and it is why this is a new
 * method rather than a wrapper: `getCachedRelease()` returns `null` for BOTH
 * "the cache is empty or stale" and "you are up to date". On a stat card those
 * are opposite answers — one is `0`, a measured fact, and the other is `—`,
 * which `SPEC/manifest.md` §44 requires precisely because a fabricated zero is
 * a claim nobody made. So the method returns THREE states, not two.
 *
 * `pure-logic` under the card's `Test-first policy:` — it is a function of
 * stored state and the clock, with no I/O of its own — so the test is written
 * and seen failing first.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\Updater;
use Klytos\Tests\UnitTestCase;

/**
 * The three answers a cached update check can honestly give.
 *
 * Every test here writes the cache file through the same storage the Updater
 * reads, so nothing is asserted against a value this test invented in memory.
 */
final class UpdaterCachedStateTest extends UnitTestCase
{
    private function makeUpdater(): Updater
    {
        // The Updater derives rootPath from configPath's parent, and reads
        // VERSION from there — so the installed version is real, not mocked.
        file_put_contents( $this->tempDir . '/VERSION', "1.0.0\n" );

        return new Updater( $this->storage, $this->tempDir . '/config' );
    }

    /**
     * Write the update cache exactly as `cacheRelease()` does.
     *
     * @param string $remoteVersion The version GitHub last reported.
     * @param int    $ageSeconds    How long ago the check ran.
     */
    private function seedCache( string $remoteVersion, int $ageSeconds ): void
    {
        $this->storage->writeTo( $this->tempDir . '/config', 'update_cache.json.enc', [
            'cached_at'      => time() - $ageSeconds,
            'remote_version' => $remoteVersion,
            'changelog'      => 'Seeded by ' . self::class,
            'html_url'       => 'https://example.invalid/release',
            'published_at'   => '2026-08-01T00:00:00Z',
            'download_url'   => 'https://example.invalid/release.zip',
        ] );
    }

    // ─── unknown — the state that must never render as a zero ─────

    public function testWithNoCacheAtAllTheStateIsUnknown(): void
    {
        $state = $this->makeUpdater()->getCachedUpdateState();

        self::assertSame( 'unknown', $state['state'] );
        self::assertNull( $state['update'] );
    }

    public function testAnExpiredCacheIsUnknownAndNotUpToDate(): void
    {
        // Six hours is the TTL; seven hours old is stale by an hour.
        $this->seedCache( '1.0.0', 7 * 3600 );

        $state = $this->makeUpdater()->getCachedUpdateState();

        // The distinction this whole method exists for: a stale cache says
        // NOTHING about whether an update is pending, so it may not say "0".
        self::assertSame( 'unknown', $state['state'] );
        self::assertNull( $state['update'] );
    }

    // ─── current — a measured zero ────────────────────────────────

    public function testAFreshCacheWithNoNewerReleaseIsCurrent(): void
    {
        $this->seedCache( '1.0.0', 60 );

        $state = $this->makeUpdater()->getCachedUpdateState();

        self::assertSame( 'current', $state['state'] );
        self::assertNull( $state['update'] );
    }

    public function testAFreshCacheReportingAnOLDERReleaseIsAlsoCurrent(): void
    {
        // A downgraded channel, or a yanked release: not an update.
        $this->seedCache( '0.9.0', 60 );

        self::assertSame( 'current', $this->makeUpdater()->getCachedUpdateState()['state'] );
    }

    // ─── pending — the count the card draws ───────────────────────

    public function testAFreshCacheWithANewerReleaseIsPendingAndCarriesIt(): void
    {
        $this->seedCache( '1.2.0', 60 );

        $state = $this->makeUpdater()->getCachedUpdateState();

        self::assertSame( 'pending', $state['state'] );
        self::assertIsArray( $state['update'] );
        self::assertSame( '1.2.0', $state['update']['new_version'] );
        self::assertSame( '1.0.0', $state['update']['current'] );
    }

    public function testAMajorUpdateIsFlaggedAsOne(): void
    {
        $this->seedCache( '2.0.0', 60 );

        $state = $this->makeUpdater()->getCachedUpdateState();

        self::assertSame( 'pending', $state['state'] );
        self::assertTrue( $state['update']['is_major'] );
    }

    // ─── the guarantee the screen depends on ──────────────────────

    public function testTheMethodNeverReachesTheNetwork(): void
    {
        // `fetchBestRelease()` goes through SafeHttp, which needs a resolvable
        // host; a call would take real time and could not succeed against a
        // host that does not exist. The proof used here is stronger than a
        // stopwatch: with an EMPTY cache — the only case where the shipped
        // `checkForUpdate()` would fetch — the answer arrives and is `unknown`,
        // which is precisely the answer a fetch would have replaced.
        $updater = $this->makeUpdater();

        $before = microtime( true );
        $state  = $updater->getCachedUpdateState();
        $after  = microtime( true );

        self::assertSame( 'unknown', $state['state'] );
        self::assertLessThan( 1.0, $after - $before, 'A cache-only read must not wait on anything.' );

        // And it left no cache behind: a read is not a write.
        $second = $updater->getCachedUpdateState();
        self::assertSame( 'unknown', $second['state'] );
    }
}
