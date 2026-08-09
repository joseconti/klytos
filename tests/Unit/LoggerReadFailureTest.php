<?php

/**
 * Klytos CMS — Logger::readLogFile() when the file cannot be read
 * (Phase 4 Step 4, stage 4 — manifest entry 41, Logs).
 *
 * `template-console-stream.md` §2 specifies two DIFFERENT states for a log the
 * screen cannot show:
 *
 *   Empty      — "`error.log` is empty. Nothing has been written since it was
 *                rotated on 24 July."
 *   Unreadable — "`error.log` cannot be read — permission denied on
 *                `/var/log/klytos/`."
 *
 * The screen can only distinguish them if the reader distinguishes them, and
 * it did not: `file()` returns `false` when the file exists but cannot be
 * opened, and `count( false )` is a TypeError under PHP 8 — so the unreadable
 * case did not return an empty array, it took the whole admin page down with a
 * fatal. The state the design asks for was unreachable by construction.
 *
 * These tests were written BEFORE the fix and observed failing, per the
 * standing rule that a bug fix starts from a reproduction that goes red.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\Logger;
use Klytos\Core\PluginLoader;
use Klytos\Core\SiteConfig;
use Klytos\Tests\UnitTestCase;

final class LoggerReadFailureTest extends UnitTestCase
{
    /** @var Logger The logger under test, rooted at this test's temp dir. */
    private Logger $logger;

    /** @var string Absolute path to the logger's own (randomly named) logs dir. */
    private string $logsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new Logger(
            $this->tempPath( 'data' ),
            new SiteConfig( $this->storage ),
            new PluginLoader(
                $this->storage,
                $this->tempPath( 'plugins' ),
                '1.0.0',
                $this->tempPath( 'config' )
            ),
            $this->storage
        );

        // getLogsDir() creates the directory and persists its random name.
        $this->logsDir = $this->logger->getLogsDir();
    }

    protected function tearDown(): void
    {
        // Restore readability so the temp-dir cleanup can remove the file.
        $path = $this->logsDir . '/debug-2026-08-09.log';
        if ( is_file( $path ) ) {
            chmod( $path, 0600 );
        }

        parent::tearDown();
    }

    /**
     * The reproduction. An existing log file whose mode denies reading must
     * come back as "no lines", not as a fatal.
     *
     * Before the fix this did not fail an assertion — it raised
     * `TypeError: count(): Argument #1 ($value) must be of type Countable|array,
     * bool given` from logger.php, which is precisely the point: the screen
     * could not render the state because reaching it crashed the request.
     */
    public function testAnUnreadableLogFileReturnsNoLinesInsteadOfFatalling(): void
    {
        $path = $this->logsDir . '/debug-2026-08-09.log';
        file_put_contents( $path, "[2026-08-09 10:00:00] [ERROR] [core] boom\n" );

        if ( ! chmod( $path, 0000 ) || is_readable( $path ) ) {
            self::markTestSkipped(
                'This test needs a file mode the running user cannot bypass; '
                . 'root ignores 0000, so the unreadable case cannot be staged here.'
            );
        }

        self::assertSame(
            [],
            $this->logger->readLogFile( $path === '' ? '' : 'debug-2026-08-09.log', 0, 0 ),
            'An unreadable log file did not come back as an empty line list.'
        );
    }

    /**
     * The other half of the distinction, and the reason the fix must not simply
     * swallow everything: a file that is readable and genuinely EMPTY also
     * returns no lines, so "no lines" alone cannot tell the two states apart.
     * The screen distinguishes them by size, which is why this pins the
     * behaviour the screen relies on.
     */
    public function testAnEmptyButReadableLogFileAlsoReturnsNoLines(): void
    {
        $path = $this->logsDir . '/debug-2026-08-08.log';
        file_put_contents( $path, '' );

        self::assertTrue( is_readable( $path ), 'The fixture file should be readable.' );
        self::assertSame(
            [],
            $this->logger->readLogFile( 'debug-2026-08-08.log', 0, 0 ),
            'An empty log file did not come back as an empty line list.'
        );
        self::assertSame( 0, filesize( $path ), 'The empty fixture is not zero bytes.' );
    }

    /**
     * Reading still works, so the guard has not been bolted on in a way that
     * breaks the ordinary path. Offset and limit are exercised together
     * because the polling the Follow switch does depends on both.
     */
    public function testTheOrdinaryPathStillReadsLinesWithOffsetAndLimit(): void
    {
        $lines = [];
        for ( $i = 1; $i <= 5; $i++ ) {
            $lines[] = sprintf( '[2026-08-09 10:00:0%d] [INFO] [core] line %d', $i, $i );
        }
        file_put_contents(
            $this->logsDir . '/debug-2026-08-07.log',
            implode( "\n", $lines ) . "\n"
        );

        self::assertCount(
            5,
            $this->logger->readLogFile( 'debug-2026-08-07.log', 0, 0 ),
            'Reading the whole file did not return every line.'
        );

        $tail = $this->logger->readLogFile( 'debug-2026-08-07.log', 3, 0 );

        self::assertCount( 2, $tail, 'The offset did not skip the lines before it.' );
        self::assertStringEndsWith( 'line 4', $tail[0], 'The offset landed on the wrong line.' );
    }

    /**
     * A filename that does not resolve inside the logs directory is refused
     * before any file operation. Pinned here because the fix touches the same
     * method and this is the property that must survive it.
     */
    public function testATraversingFilenameStillReturnsNoLines(): void
    {
        self::assertSame(
            [],
            $this->logger->readLogFile( '../../config/.encryption_key', 0, 0 ),
            'A traversing filename was not refused.'
        );
    }

    // ─── isLogFileReadable(), the question that separates the two states ───

    /**
     * The distinction the screen actually renders: an empty file is readable,
     * an unreadable one is not, and both return no lines. Asserting all three
     * facts in one test is deliberate — the value of the new method is exactly
     * that it disagrees with readLogFile() on one of these two files.
     */
    public function testReadabilityTellsEmptyApartFromUnreadable(): void
    {
        $empty = $this->logsDir . '/debug-2026-08-08.log';
        file_put_contents( $empty, '' );

        $denied = $this->logsDir . '/debug-2026-08-09.log';
        file_put_contents( $denied, "[2026-08-09 10:00:00] [ERROR] [core] boom\n" );

        if ( ! chmod( $denied, 0000 ) || is_readable( $denied ) ) {
            self::markTestSkipped(
                'This test needs a file mode the running user cannot bypass; '
                . 'root ignores 0000, so the unreadable case cannot be staged here.'
            );
        }

        self::assertSame(
            [],
            $this->logger->readLogFile( 'debug-2026-08-08.log', 0, 0 ),
            'The empty file should read as no lines.'
        );
        self::assertSame(
            [],
            $this->logger->readLogFile( 'debug-2026-08-09.log', 0, 0 ),
            'The unreadable file should read as no lines.'
        );

        self::assertTrue(
            $this->logger->isLogFileReadable( 'debug-2026-08-08.log' ),
            'An empty file is readable — the screen must call it empty, not broken.'
        );
        self::assertFalse(
            $this->logger->isLogFileReadable( 'debug-2026-08-09.log' ),
            'An unreadable file was reported as readable, so the screen would '
            . 'show "empty" for a log it simply cannot open.'
        );
    }

    /**
     * A name that does not resolve, and a file that is not there at all, are
     * both "not readable" — the screen must never treat either as an empty log.
     */
    public function testAMissingOrTraversingNameIsNotReadable(): void
    {
        self::assertFalse(
            $this->logger->isLogFileReadable( 'debug-2026-01-01.log' ),
            'A file that does not exist was reported as readable.'
        );
        self::assertFalse(
            $this->logger->isLogFileReadable( '../../config/.encryption_key' ),
            'A traversing name was reported as readable.'
        );
        self::assertFalse(
            $this->logger->isLogFileReadable( 'notes.txt' ),
            'A non-log extension was reported as readable.'
        );
    }
}
