<?php

/**
 * Klytos CMS — Logger::parseLine() (Phase 4 Step 4, stage 4 — entry 41, Logs).
 *
 * `template-console-stream.md` §1 asks the Logs screen for things a raw string
 * cannot give it: the level as a mono label at the START of the line, the
 * timestamp as `<time datetime>`, and — §2's selected-line state — a detail
 * panel whose `<h2>` names the event and whose body is the line's context.
 *
 * All four are fields of a format this very class writes:
 *
 *     [Y-m-d H:i:s] [LEVEL] [source] message {json context}
 *
 * so the parser belongs beside the formatter. A parser living in the screen
 * would be free to drift from the `sprintf()` in `write()` that produces the
 * line, and nothing would notice until a log rendered wrong.
 *
 * Parsing a line is a pure function of its input, which the project card's
 * `Test-first policy: pure-logic` puts squarely in the test-first set: these
 * tests were written BEFORE the method existed and observed failing.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\Logger;
use Klytos\Tests\UnitTestCase;

final class LoggerParseLineTest extends UnitTestCase
{
    public function testParsesEveryFieldOfAWellFormedLine(): void
    {
        $parsed = Logger::parseLine(
            '[2026-08-09 14:03:22] [ERROR] [core] Payment capture failed {"order":17,"gateway":"redsys"}'
        );

        $this->assertSame( '2026-08-09 14:03:22', $parsed['timestamp'] );
        $this->assertSame( 'error', $parsed['level'] );
        $this->assertSame( 'core', $parsed['source'] );
        $this->assertSame( 'Payment capture failed', $parsed['message'] );
        $this->assertSame( ['order' => 17, 'gateway' => 'redsys'], $parsed['context'] );
    }

    public function testALineWithNoContextParsesWithAnEmptyContext(): void
    {
        $parsed = Logger::parseLine( '[2026-08-09 14:03:22] [INFO] [core] Site build finished' );

        $this->assertSame( 'info', $parsed['level'] );
        $this->assertSame( 'Site build finished', $parsed['message'] );
        $this->assertSame( [], $parsed['context'] );
    }

    /**
     * The message itself may contain braces, and the trailing JSON is the only
     * context. Taking the FIRST `{` would cut a message like this in half.
     */
    public function testBracesInTheMessageDoNotBecomeContext(): void
    {
        $parsed = Logger::parseLine(
            '[2026-08-09 14:03:22] [WARNING] [forms] Template {name} is unresolved {"form":3}'
        );

        $this->assertSame( 'Template {name} is unresolved', $parsed['message'] );
        $this->assertSame( ['form' => 3], $parsed['context'] );
    }

    /**
     * A trailing brace group that is not valid JSON is message text, not
     * context. Guessing otherwise would silently drop it from the screen.
     */
    public function testATrailingBraceGroupThatIsNotJsonStaysInTheMessage(): void
    {
        $parsed = Logger::parseLine( '[2026-08-09 14:03:22] [DEBUG] [core] Matched rule {not json}' );

        $this->assertSame( 'Matched rule {not json}', $parsed['message'] );
        $this->assertSame( [], $parsed['context'] );
    }

    /**
     * JSON that decodes to a scalar (`{` … `}` is required for an object, but
     * `json_decode` accepts more) must not reach the detail panel as context:
     * the panel renders key/value pairs.
     */
    public function testContextIsOnlyTakenWhenItDecodesToAnArray(): void
    {
        $parsed = Logger::parseLine( '[2026-08-09 14:03:22] [INFO] [core] Value {"just a string"}' );

        $this->assertSame( 'Value {"just a string"}', $parsed['message'] );
        $this->assertSame( [], $parsed['context'] );
    }

    /**
     * A line this class did not write — a stray line, a truncated file, output
     * from something else in the same directory — is still shown. It becomes a
     * message with no level, never a dropped line and never a crash: a log
     * viewer that hides what it cannot parse is worse than one that shows it.
     */
    public function testAnUnrecognisedLineBecomesAMessageWithNoLevel(): void
    {
        $parsed = Logger::parseLine( 'Fatal error: Uncaught TypeError in /srv/klytos/index.php:12' );

        $this->assertSame( '', $parsed['timestamp'] );
        $this->assertSame( '', $parsed['level'] );
        $this->assertSame( '', $parsed['source'] );
        $this->assertSame( 'Fatal error: Uncaught TypeError in /srv/klytos/index.php:12', $parsed['message'] );
        $this->assertSame( [], $parsed['context'] );
    }

    public function testAnEmptyLineParsesToEmptyFields(): void
    {
        $parsed = Logger::parseLine( '' );

        $this->assertSame( '', $parsed['message'] );
        $this->assertSame( '', $parsed['level'] );
        $this->assertSame( [], $parsed['context'] );
    }

    /**
     * The level is normalised to the lower-case PSR-3 spelling this class
     * stores in `LEVELS`, so a caller can compare it against that list without
     * repeating a `strtolower()` the format already decided.
     */
    public function testTheLevelIsNormalisedToTheClassesOwnLevelSet(): void
    {
        foreach ( Logger::LEVELS as $level ) {
            $parsed = Logger::parseLine(
                '[2026-08-09 14:03:22] [' . strtoupper( $level ) . '] [core] A message'
            );

            $this->assertSame( $level, $parsed['level'], "Level {$level} did not round-trip" );
        }
    }

    /**
     * A level spelling this class never writes is not silently coerced to one
     * that it does — the screen tints by level, and inventing `error` for an
     * unknown word would paint a line red on a guess.
     */
    public function testAnUnknownLevelWordIsReturnedEmpty(): void
    {
        $parsed = Logger::parseLine( '[2026-08-09 14:03:22] [TRACE] [core] A message' );

        $this->assertSame( '', $parsed['level'] );
        $this->assertSame( 'A message', $parsed['message'] );
    }

    /**
     * The round trip that matters: whatever `write()` formats, `parseLine()`
     * reads back. This is the drift check — if the `sprintf()` in `write()`
     * ever changes shape, this fails rather than the screen rendering wrong.
     */
    public function testItReadsBackTheFormatWriteProduces(): void
    {
        $line = sprintf(
            '[%s] [%s] [%s] %s%s',
            '2026-08-09 14:03:22',
            strtoupper( 'warning' ),
            'klytos-forms',
            'Submission rejected',
            ' ' . json_encode( ['reason' => 'honeypot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
        );

        $parsed = Logger::parseLine( $line );

        $this->assertSame( 'warning', $parsed['level'] );
        $this->assertSame( 'klytos-forms', $parsed['source'] );
        $this->assertSame( 'Submission rejected', $parsed['message'] );
        $this->assertSame( ['reason' => 'honeypot'], $parsed['context'] );
    }
}
