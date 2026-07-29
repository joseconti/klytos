<?php

/**
 * Klytos CMS — the admin's primary navigation follows SPEC/navigation.md
 * (Phase 4 Step 4, stage 2 — the shell).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the navigation MODEL, not the markup that draws it.
 *
 * `SPEC/navigation.md` is normative and it settles things that took a Design
 * Request to settle (D-073 → D-074): which group `Blocks` belongs to, that
 * `Guides` is dropped, that every glyph is unique bar the deliberate `ks-tune`
 * pair, and that a capability-gated item is HIDDEN rather than disabled. Those
 * answers are cheap to un-answer by accident later — someone "tidies" the array
 * and Blocks drifts back to Content, which is precisely the contradiction the
 * prototypes shipped and the DR existed to remove.
 *
 * These assertions read the definition directly rather than a rendered page, so
 * they run in the unit tier on a bare checkout with no App and no session. The
 * rendered half — capability filtering per role, empty groups vanishing,
 * aria-current parentage — is driven against the real playground at the test
 * point, because those need a booted App and a signed-in person.
 */
final class AdminNavTest extends TestCase
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private static array $definition;

    public static function setUpBeforeClass(): void
    {
        $root = dirname( __DIR__, 2 ) . '/installer';

        // The nav model is a pure data + function file: it neither boots the
        // App nor renders. Stubbing the two helpers it would otherwise reach
        // for keeps this in the unit tier, which is the point of splitting the
        // model out of sidebar.php in the first place.
        if ( ! function_exists( 'klytos_apply_filters' ) ) {
            eval( 'function klytos_apply_filters( string $h, $v, ...$a ) { return $v; }' );
        }
        if ( ! class_exists( \Klytos\Core\Helpers::class ) ) {
            eval( 'namespace Klytos\Core; class Helpers { public static function getBasePath(): string { return "/"; } }' );
        }

        require_once $root . '/core/admin-nav.php';

        self::$definition = klytos_admin_nav_definition();
    }

    public function testTheEightGroupsAreInTheOrderNavigationMdFixes(): void
    {
        $this->assertSame(
            [ 'site', 'content', 'design', 'intelligence', 'monetisation', 'compliance', 'system', 'account' ],
            klytos_admin_nav_group_order(),
            'navigation.md §1 fixes the order and states it is not personalisable'
        );

        $this->assertSame(
            klytos_admin_nav_group_order(),
            array_keys( self::$definition ),
            'the definition must be written in the same order it renders'
        );
    }

    public function testTheManifestCarriesThirtyFourItemsAndGuidesIsNotOneOfThem(): void
    {
        $ids = [];
        foreach ( self::$definition as $items ) {
            foreach ( $items as $item ) {
                $ids[] = $item['id'];
            }
        }

        $this->assertCount( 34, $ids, 'navigation.md §2 lists 34 items' );
        $this->assertSame( $ids, array_unique( $ids ), 'no item id may appear twice' );
        $this->assertNotContains( 'guides', $ids, 'navigation.md §4 drops Guides rather than promoting it' );
    }

    /**
     * The single answer DR-003 was raised to get, asserted so it cannot drift
     * back. Four prototypes put Blocks under Design and two under Content;
     * navigation.md §3 settles it as Design, because entry 21 is an inventory
     * of registered block TYPES, like Templates and Theme.
     */
    public function testBlocksBelongsToDesignAndNotToContent(): void
    {
        $design  = array_column( self::$definition['design'], 'id' );
        $content = array_column( self::$definition['content'], 'id' );

        $this->assertContains( 'blocks', $design );
        $this->assertNotContains( 'blocks', $content );
    }

    public function testSettingsIsAlwaysLastInItsGroup(): void
    {
        $system = array_column( self::$definition['system'], 'id' );

        $this->assertSame( 'settings', end( $system ), 'navigation.md §2: Settings sits at the bottom of System' );
    }

    /**
     * Every glyph appears exactly once, with one deliberate exception:
     * `ks-tune` is the settings mark and serves both Settings and Payment
     * settings. navigation.md §2 says inventing a second settings glyph would
     * weaken the first, so the duplicate is asserted as intended rather than
     * tolerated.
     */
    public function testGlyphsAreUniqueApartFromTheDeliberateSettingsPair(): void
    {
        $glyphs = [];
        foreach ( self::$definition as $items ) {
            foreach ( $items as $item ) {
                $glyphs[] = $item['glyph'];
            }
        }

        $repeated = array_keys( array_filter( array_count_values( $glyphs ), static fn ( int $n ): bool => $n > 1 ) );

        $this->assertSame( [ 'ks-tune' ], $repeated );
    }

    /**
     * A `<use>` pointing at an id the sprite does not contain renders nothing,
     * silently, with no console error — L-030. `keel-verify` check 16 is the
     * standing guard; this asserts the same property from the suite so a bare
     * `composer test` catches it too.
     */
    public function testEveryGlyphResolvesToARealSpriteSymbol(): void
    {
        $sprite = dirname( __DIR__, 2 ) . '/installer/admin/assets/icons/klytos-ui-icons.svg';
        $this->assertFileExists( $sprite );

        preg_match_all( '/<symbol[^>]+id="(ks-[A-Za-z0-9_-]+)"/', (string) file_get_contents( $sprite ), $m );
        $known = array_flip( $m[1] );

        foreach ( self::$definition as $groupId => $items ) {
            foreach ( $items as $item ) {
                $this->assertArrayHasKey(
                    $item['glyph'],
                    $known,
                    sprintf( '%s (%s) draws %s, which is not in the sprite', $item['id'], $groupId, $item['glyph'] )
                );
            }
        }
    }

    /**
     * navigation.md §7: Overview is the one item always present for every
     * authenticated person, so that someone with nothing else can still land
     * somewhere. A capability on it would break that promise silently.
     */
    public function testOverviewCarriesNoCapability(): void
    {
        $overview = self::$definition['site'][0];

        $this->assertSame( 'overview', $overview['id'] );
        $this->assertNull( $overview['capability'] );
    }

    /**
     * D-072 deferred Comments (14) and Health (22) out of Phase 4, and the user
     * chose on 2026-07-29 to omit their nav items rather than ship a 404 on the
     * primary navigation. They stay in the definition, described in full and
     * marked deferred, so restoring each is deleting one line.
     */
    public function testTheTwoDeferredEntriesAreMarkedAndNotRendered(): void
    {
        $deferred = [];
        foreach ( self::$definition as $items ) {
            foreach ( $items as $item ) {
                if ( ! empty( $item['deferred'] ) ) {
                    $deferred[] = $item['id'];
                }
            }
        }

        $this->assertSame( [ 'comments', 'health' ], $deferred );
    }

    /**
     * navigation.md §6's capability → group table. A plugin may not choose Site
     * or Account: Site is the install's own state and Account is the person's.
     *
     */
    #[DataProvider( 'capabilityGroupProvider' )]
    public function testAPluginsCapabilityChoosesItsGroup( string $capability, string $expected ): void
    {
        $this->assertSame( $expected, klytos_admin_nav_capability_group( $capability ) );
    }

    /** @return array<int, array{0: string, 1: string}> */
    public static function capabilityGroupProvider(): array
    {
        return [
            [ 'content.forms', 'content' ],
            [ 'design.widgets', 'design' ],
            [ 'ai.use', 'intelligence' ],
            [ 'mcp.manage', 'intelligence' ],
            [ 'payments.collect', 'monetisation' ],
            [ 'privacy.export', 'compliance' ],
            [ 'consent.manage', 'compliance' ],
            // "anything else, or none declared" lands in System — and never in
            // Site or Account, which is the half worth asserting.
            [ 'forms.manage', 'system' ],
            [ 'something.unknown', 'system' ],
            [ '', 'system' ],
        ];
    }

    public function testEveryItemNamesATranslationKeyRatherThanEnglishCopy(): void
    {
        foreach ( self::$definition as $groupId => $items ) {
            foreach ( $items as $item ) {
                $this->assertMatchesRegularExpression(
                    '/^nav\.item\.[a-z_]+$/',
                    $item['label'],
                    sprintf( '%s must carry a catalogue key, not a literal string', $item['id'] )
                );
            }
        }
    }

    /**
     * The English catalogue must actually contain every key the definition
     * names. A missing key renders the key itself in the sidebar — visible,
     * ugly, and exactly the kind of thing that ships on a Friday.
     */
    public function testTheEnglishCatalogueDefinesEveryLabelAndCaption(): void
    {
        $catalogue = json_decode(
            (string) file_get_contents( dirname( __DIR__, 2 ) . '/installer/core/lang/en.json' ),
            true
        );

        foreach ( klytos_admin_nav_group_order() as $groupId ) {
            $this->assertArrayHasKey( $groupId, $catalogue['nav']['group'] );
        }

        foreach ( self::$definition as $items ) {
            foreach ( $items as $item ) {
                $key = substr( $item['label'], strlen( 'nav.item.' ) );
                $this->assertArrayHasKey( $key, $catalogue['nav']['item'], $item['label'] . ' is not in en.json' );
            }
        }
    }
}
