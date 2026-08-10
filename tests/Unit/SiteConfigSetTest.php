<?php

/**
 * Klytos CMS — `SiteConfig::set()` persists the keys its callers hand it.
 *
 * Written for the defect found while surveying manifest entry 9 (Settings):
 * `set()` copies a value into the stored config only if its name appears in a
 * hard-coded `$topLevel` list, or matches one of the named nested branches.
 * Anything else is accepted, merged into nothing and dropped, and the method
 * returns the merged array either way — so the CALLER sees its own value come
 * back and has no way to learn the write never happened.
 *
 * `encryption_key_backed_up` is one of those names. Two shipped surfaces write
 * it (`admin/settings.php`, `admin/setup-wizard.php`) and a third reads it as
 * the condition of an UNDISMISSABLE system error notice
 * (`admin/bootstrap.php`, `notice.condition.encryption_key_not_backed_up`).
 * Because the write is dropped, the condition is permanently true: every admin
 * page of every install carries a red "your encryption key has not been backed
 * up" banner that no control in the product can clear.
 *
 * This is L-041's shape a second time — a feature that answers confidently and
 * wrongly rather than failing — so the red is observed before the fix, and it
 * names the absent behaviour (the key is missing after a round trip) rather
 * than a missing class or a typo.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\SiteConfig;
use Klytos\Tests\UnitTestCase;

/**
 * The write contract of the site configuration store.
 *
 * Both directions are pinned, because one is half a test (L-010): a key the
 * method is meant to carry survives a round trip through storage, and a key it
 * is not meant to carry is still not invented out of nothing.
 */
final class SiteConfigSetTest extends UnitTestCase
{
    private function makeConfig(): SiteConfig
    {
        return new SiteConfig( $this->storage );
    }

    // ─── The reproduction (the defect) ───────────────────────────

    public function testEncryptionKeyBackedUpSurvivesAWriteAndAReRead(): void
    {
        $config = $this->makeConfig();
        $config->set( ['encryption_key_backed_up' => true] );

        // A FRESH instance, so the assertion reads STORAGE and not an in-memory
        // array the setter happened to return (the shipped surfaces read it on
        // the next request, which is a fresh instance by definition).
        $reread = $this->makeConfig()->get();

        $this->assertArrayHasKey(
            'encryption_key_backed_up',
            $reread,
            'set() dropped the key: the undismissable backup notice can never be cleared.'
        );
        $this->assertTrue( $reread['encryption_key_backed_up'] );
    }

    public function testTheBackupFlagCanBeClearedAgain(): void
    {
        $config = $this->makeConfig();
        $config->set( ['encryption_key_backed_up' => true] );
        $config->set( ['encryption_key_backed_up' => false] );

        $this->assertFalse( $this->makeConfig()->get()['encryption_key_backed_up'] );
    }

    // ─── The other direction ─────────────────────────────────────

    public function testAnUnknownKeyIsStillNotStored(): void
    {
        // The fix widens the allow-list by one NAMED field; it does not turn
        // set() into a pass-through that would let any POST key reach the
        // stored configuration.
        $this->makeConfig()->set( ['klytos_tests_not_a_setting' => 'x'] );

        $this->assertArrayNotHasKey(
            'klytos_tests_not_a_setting',
            $this->makeConfig()->get()
        );
    }

    // ─── The partial-merge guarantee entry 9 depends on ──────────

    public function testASecondPartialWriteDoesNotWipeTheFirst(): void
    {
        /*
         * Entry 9 re-partitions eleven POST groups onto five sections, so a
         * value written by one section must survive a save from another. That
         * is `set()`'s documented "partial update" contract, and the whole
         * re-partition rests on it, so it is pinned rather than assumed.
         */
        $config = $this->makeConfig();
        $config->set( ['site_name' => 'Entry 9', 'default_language' => 'en'] );
        $config->set( ['tagline' => 'Only the tagline'] );

        $stored = $this->makeConfig()->get();

        $this->assertSame( 'Entry 9', $stored['site_name'] );
        $this->assertSame( 'en', $stored['default_language'] );
        $this->assertSame( 'Only the tagline', $stored['tagline'] );
    }
}
