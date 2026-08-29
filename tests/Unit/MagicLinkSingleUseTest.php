<?php

/**
 * Klytos CMS — a magic login link is SINGLE USE.
 *
 * THE BUG THIS REPRODUCES, and it is an authentication defect in shipped code.
 * `createMagicLink()` writes the record with no `'id'` field (`two-factor.php`
 * :207-217 — the id is a local `$tokenId`), and `markMagicLinkUsed()` recovers
 * it with `$id = $link['id'] ?? null; if ( $id ) { … }` (`:1032-1036`). `$id` is
 * therefore always `null`, the guard is always false, and **the write that marks
 * the link used never executes**.
 *
 * `verifyMagicLink()` skips a link only when `$link['used']` is true (`:239`),
 * which it never becomes — so the SAME link verifies over and over for its whole
 * ten-minute lifetime. A single-use login link is replayable.
 *
 * The root is one layer below and is shared with six other sites: `list()`
 * returned records without their storage ids, so a caller could read a record and
 * have no way back to the identity it needed in order to write or delete it. The
 * `?? null` guard is what turned a crash into silence here (D-115).
 *
 * Written BEFORE the fix and seen failing — the unconditional rule at every value
 * of `Test-first policy:`.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\TwoFactor;
use Klytos\Tests\UnitTestCase;

/**
 * Single use means the second attempt fails.
 */
final class MagicLinkSingleUseTest extends UnitTestCase
{
    private const COLLECTION = 'magic-links';

    /**
     * THE REPRODUCTION. Before the fix the second verification also returned
     * true — indefinitely, until the link expired.
     */
    public function testAMagicLinkCannotBeUsedTwice(): void
    {
        $twoFactor = new TwoFactor( $this->storage );

        $link = $twoFactor->createMagicLink( 'user-1', 'someone@example.test' );

        $this->assertTrue(
            $twoFactor->verifyMagicLink( $link['token'], 'user-1' ),
            'the first use of a fresh link succeeds'
        );

        $this->assertFalse(
            $twoFactor->verifyMagicLink( $link['token'], 'user-1' ),
            'THE REPLAY: the same link must not verify a second time'
        );
    }

    /** And the record itself records that it was used, not just the answer. */
    public function testUsingALinkPersistsTheUsedFlag(): void
    {
        $twoFactor = new TwoFactor( $this->storage );

        $link = $twoFactor->createMagicLink( 'user-1', 'someone@example.test' );
        $twoFactor->verifyMagicLink( $link['token'], 'user-1' );

        $stored = $this->storage->list( self::COLLECTION );

        $this->assertCount( 1, $stored );
        $this->assertTrue(
            $stored[0]['used'] ?? false,
            'the stored record carries used = true, so any other process sees it too'
        );
    }

    /**
     * Burning one link does not burn another person's.
     *
     * `markMagicLinkUsed()` matches on the token hash, and once it can really
     * write, a mistake there would silently invalidate the wrong record — which
     * is a worse failure than the one being fixed.
     */
    public function testUsingOneLinkDoesNotInvalidateAnother(): void
    {
        $twoFactor = new TwoFactor( $this->storage );

        $mine   = $twoFactor->createMagicLink( 'user-1', 'one@example.test' );
        $theirs = $twoFactor->createMagicLink( 'user-2', 'two@example.test' );

        $this->assertTrue( $twoFactor->verifyMagicLink( $mine['token'], 'user-1' ) );

        $this->assertTrue(
            $twoFactor->verifyMagicLink( $theirs['token'], 'user-2' ),
            'the other person\'s link is untouched'
        );
    }

    /** Expired links are purged, and the purge really removes records. */
    public function testCleanupRemovesExpiredLinksAndReportsWhatItRemoved(): void
    {
        $twoFactor = new TwoFactor( $this->storage );

        $twoFactor->createMagicLink( 'user-1', 'fresh@example.test' );

        // An expired one, written in the shape `createMagicLink()` produces.
        $this->storage->write( self::COLLECTION, 'ml_expired', [
            'user_id'    => 'user-2',
            'email'      => 'old@example.test',
            'hash'       => hash( 'sha256', 'whatever' ),
            'created_at' => gmdate( 'c', time() - 7200 ),
            'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 3600 ),
            'used'       => false,
        ] );

        $this->assertCount( 2, $this->storage->list( self::COLLECTION ) );

        $twoFactor->cleanupMagicLinks();

        $survivors = $this->storage->list( self::COLLECTION );

        $this->assertCount( 1, $survivors, 'the expired link is gone' );
        $this->assertSame( 'fresh@example.test', $survivors[0]['email'] );
    }
}
