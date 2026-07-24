<?php

/**
 * Klytos CMS — the MCP refusal message is translated (Sprint 2, slice 4).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\I18n;
use Klytos\Tests\UnitTestCase;

/**
 * The one MCP string a PERSON reads.
 *
 * Everything else the gate produces is audit-log material: the denialReason()
 * strings name the role and the capability and stay English on purpose, because
 * their reader is the operator's security log. The 403's message is different —
 * an MCP client surfaces it to whoever is driving the agent — so it comes from
 * the locale catalogues like every other user-facing string in the product
 * (i18n decision D-006: 20 locales, key-based JSON, no gettext).
 *
 * This drives the I18n SERVICE directly rather than the running server, for the
 * reason SecurityHeadersTest records: a property that can be asserted on the
 * real wire is asserted there (McpGateHttpTest pins the 403 body against the
 * English catalogue), and a property the wire cannot show — that the string is
 * actually TRANSLATED and not merely externalized — is asserted here, where a
 * locale is one constructor argument instead of an encrypted config write.
 *
 * Against the pre-slice-4 tree every test in this file fails: the key does not
 * exist in any catalogue, so I18n::get() falls through to returning the key
 * itself.
 */
final class McpRefusalI18nTest extends UnitTestCase
{
    /** The refusal key added to the core catalogues by this slice. */
    private const KEY = 'mcp.permission_denied';

    /** The 20 locales the product ships (D-006). */
    private const LOCALES = [
        'ca', 'da', 'de', 'el', 'en', 'es', 'eu', 'fi', 'fr', 'gl',
        'it', 'ja', 'nb', 'nl', 'pl', 'pt', 'ru', 'sv', 'tr', 'zh',
    ];

    /** Absolute path to the core catalogue directory. */
    private function langDir(): string
    {
        return dirname( __DIR__, 2 ) . '/installer/core/lang';
    }

    /**
     * Every shipped locale carries the key, and none of them falls through to
     * I18n's last-resort behaviour of echoing the key back.
     *
     * The English fallback inside I18n is what makes this assertion worth
     * making per locale: a missing Spanish key would silently serve English and
     * look fine to anyone reading the response.
     */
    public function testEveryShippedLocaleCarriesTheRefusalKey(): void
    {
        foreach ( self::LOCALES as $locale ) {
            // Fallback disabled by construction: loading the locale as its own
            // catalogue directory is not possible, so instead compare against
            // the raw file, which is what actually ships.
            $catalogue = json_decode(
                (string) file_get_contents( $this->langDir() . '/' . $locale . '.json' ),
                true
            );

            self::assertIsArray( $catalogue, "{$locale}.json must be valid JSON" );
            self::assertArrayHasKey(
                'permission_denied',
                $catalogue['mcp'] ?? [],
                "{$locale}.json must carry mcp.permission_denied — a missing key silently serves "
                . 'English through the fallback, which reads as correct and is not.'
            );
        }

        self::assertCount( 20, self::LOCALES, 'the product ships 20 locales (D-006)' );
    }

    /**
     * The tool name is substituted into the message in every locale.
     *
     * The caller supplied the name, so echoing it back discloses nothing — and
     * without it the refusal is useless to an agent calling several tools.
     */
    public function testTheToolNameIsSubstitutedInEveryLocale(): void
    {
        foreach ( self::LOCALES as $locale ) {
            $message = ( new I18n( $locale, $this->langDir() ) )
                ->get( self::KEY, [ 'tool' => 'klytos_delete_page' ] );

            self::assertStringContainsString(
                'klytos_delete_page',
                $message,
                "the {$locale} refusal must name the tool the caller asked for"
            );
            self::assertStringNotContainsString(
                '{tool}',
                $message,
                "the {$locale} refusal left its placeholder unsubstituted"
            );
            self::assertNotSame(
                self::KEY,
                $message,
                "the {$locale} catalogue does not resolve the key — I18n echoed it back"
            );
        }
    }

    /**
     * The message is genuinely translated, not the English string copied into
     * 20 files. Three unrelated language families are enough to prove it: a
     * copy-paste job would make all three identical to English.
     */
    public function testTheMessageIsTranslatedNotCopied(): void
    {
        $english = ( new I18n( 'en', $this->langDir() ) )->get( self::KEY, [ 'tool' => 'x' ] );

        foreach ( [ 'es', 'de', 'ja' ] as $locale ) {
            self::assertNotSame(
                $english,
                ( new I18n( $locale, $this->langDir() ) )->get( self::KEY, [ 'tool' => 'x' ] ),
                "the {$locale} refusal is byte-identical to English — it was not translated"
            );
        }
    }

    /**
     * The refusal names the tool and no internal capability.
     *
     * This is the property D-046 recorded for the client-facing message: the
     * full reason — which role lacked which capability — goes to the audit log
     * through mcp.access_denied, never to the caller. Translating the string is
     * exactly the moment that property could be lost, because a translator
     * working from the internal reason would helpfully include it.
     *
     * The assertion is on capability IDENTIFIERS (`pages.delete`,
     * `site.configure` — the dotted shape the matrix uses), not on a word
     * blocklist. The first version of this test blocked the word "owner" and
     * failed on the English message, which says "ask the site owner" — the
     * PERSON to ask, not the caller's role. That is a legitimate, deliberate
     * part of the message (it names the fix), so a blocklist was measuring
     * something other than the property. What must never appear is the internal
     * identifier the caller is not entitled to learn.
     */
    public function testTheRefusalDisclosesNoCapabilityIdentifier(): void
    {
        foreach ( self::LOCALES as $locale ) {
            $message = ( new I18n( $locale, $this->langDir() ) )
                ->get( self::KEY, [ 'tool' => 'klytos_delete_page' ] );

            self::assertSame(
                0,
                preg_match( '/\b[a-z]+\.[a-z_]+\b/', $message ),
                "the {$locale} refusal contains a capability identifier — the internal reason "
                . 'belongs in the audit log (mcp.access_denied), not in the client-facing message'
            );
            self::assertStringNotContainsStringIgnoringCase(
                'capability',
                $message,
                "the {$locale} refusal uses the internal term 'capability' instead of a "
                . 'user-facing word'
            );
        }
    }
}
