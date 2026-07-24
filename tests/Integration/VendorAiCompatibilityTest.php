<?php

/**
 * Klytos CMS — the vendored AI HTTP stack still satisfies its consumers after a
 * re-vendor (Sprint 3, slice 1 / audit NEW-05, D-029, D-052).
 *
 * WHY THIS EXISTS. `tests/Unit/VendorAiManifestTest.php` proves the four
 * bookkeeping records agree — manifest, lock, installed.php, licence notice. It
 * deliberately compares metadata only and never loads a single vendored class,
 * so it would stay green against a tree that cannot autoload at all. Nothing in
 * this project exercised the vendored code itself: the three
 * ChatEngineToolListTest tests do reach App::getChatEngine() and therefore run
 * the autoloader, but they assert nothing about what it produced.
 *
 * So a version bump could satisfy every record, satisfy `composer audit`, and
 * still have removed a class `soukicz/llm` imports — and the whole suite would
 * pass while AI chat was dead. This test closes that gap.
 *
 * WHAT IT DOES NOT PROVE, stated rather than implied (L-014). It makes no
 * network request and it does not prove "AI chat works". It proves the stack
 * LOADS and that the API surface its consumers name still RESOLVES. A live
 * provider round-trip needs an API key the playground does not have, and that
 * check is handed to the operator in docs/playground.md instead of being
 * simulated here.
 *
 * The symbol list below is MEASURED, not guessed — it is every GuzzleHttp\* and
 * Psr\Http\* symbol imported by `installer/vendor-ai/soukicz/llm/src/`, plus the
 * two exception types first-party code catches at
 * `installer/core/ai/chat-engine.php:197` and `:216`. Regenerate it with:
 *
 *   grep -rhoE '^use (GuzzleHttp|Psr)\\[A-Za-z0-9_\\]+' \
 *       installer/vendor-ai/soukicz/llm/src/ | sed 's/^use //' | sort -u
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Klytos\Tests\IntegrationTestCase;

/**
 * Grouped so CI's PHP 8.2 leg can EXCLUDE these explicitly rather than let them
 * skip. Klytos declares PHP 8.1+ and CI verifies 8.2, but the vendored AI stack
 * needs 8.3 (NEW-06 / D-053), so these tests cannot run there. Excluding a named
 * group keeps D-045's 'a skip is a hard failure' rule intact and meaningful — a
 * silently skipped integration tier is exactly what that rule exists to catch.
 */
#[Group( 'ai-runtime' )]
final class VendorAiCompatibilityTest extends IntegrationTestCase
{
    /**
     * Every vendored symbol the AI stack names.
     *
     * A missing entry here is not a style problem: it is a fatal the moment the
     * corresponding code path runs in production.
     *
     * @var array<int, string>
     */
    private const REQUIRED_SYMBOLS = [
        // Imported by installer/vendor-ai/soukicz/llm/src/.
        'GuzzleHttp\\Client',
        'GuzzleHttp\\ClientInterface',
        'GuzzleHttp\\Exception\\ClientException',
        'GuzzleHttp\\HandlerStack',
        'GuzzleHttp\\Middleware',
        'GuzzleHttp\\Promise\\Create',
        'GuzzleHttp\\Promise\\PromiseInterface',
        'GuzzleHttp\\Promise\\Utils',
        'GuzzleHttp\\Psr7\\Request',
        'GuzzleHttp\\Psr7\\Response',
        'GuzzleHttp\\RetryMiddleware',
        'Psr\\Http\\Message\\RequestInterface',
        'Psr\\Http\\Message\\ResponseInterface',

        // Caught by first-party code — chat-engine.php:197 and :216. These two
        // are the ONLY vendored symbols Klytos itself names, so a rename here
        // would silently turn a handled provider error into an unhandled one.
        'GuzzleHttp\\Exception\\ConnectException',
    ];

    /**
     * The five provider endpoints the AI module can reach, as hardcoded literals
     * in installer/core/ai/chat-engine.php and in the soukicz/llm client
     * classes. They are the only URLs this stack ever parses.
     *
     * @var array<int, string>
     */
    private const PROVIDER_ENDPOINTS = [
        'https://api.anthropic.com/v1/messages',
        'https://api.openai.com/v1/chat/completions',
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent',
        'https://openrouter.ai/api/v1/chat/completions',
        'https://ollama.com/v1/chat/completions',
    ];

    /**
     * Load the vendored autoloader the same way production does, rather than by
     * requiring the file directly — App::getChatEngine() (app.php) is the single
     * load point, so driving it is what proves the real path still works.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Below the AI stack's PHP floor the guard refuses (NEW-06 / D-053) and
        // the autoloader never runs — correct behaviour there, and nothing this
        // class can assert. CI runs the full suite on PHP 8.2 as well as 8.3.
        $this->requireAiRuntime();

        $this->app->getChatEngine();
    }

    public function testTheVendoredAutoloaderResolvesEverySymbolTheAiStackNames(): void
    {
        $missing = [];

        foreach ( self::REQUIRED_SYMBOLS as $symbol ) {
            // class_exists() alone returns false for interfaces, and FOUR of the
            // fourteen are interfaces — ClientInterface, PromiseInterface,
            // RequestInterface and ResponseInterface. Asserting with
            // class_exists() only would have reported all four missing and sent
            // the next reader hunting for a breakage that is not there.
            if ( class_exists( $symbol ) || interface_exists( $symbol ) ) {
                continue;
            }

            $missing[] = $symbol;
        }

        $this->assertSame(
            [],
            $missing,
            "The vendored AI stack no longer provides symbols its own consumers import.\n"
            . "This is what a version bump breaks silently: the manifest records agree, "
            . "`composer audit` is clean, and AI chat fatals on first use.\n"
            . 'Missing: ' . implode( ', ', $missing )
        );
    }

    public function testGuzzleStillBuildsAClientTheWayTheAiStackBuildsOne(): void
    {
        // The exact construction soukicz/llm performs in
        // Http/HttpClientFactory::createClient(): a handler stack from
        // HandlerStack::create(), passed as the 'handler' option. No network.
        $stack = \GuzzleHttp\HandlerStack::create();

        $this->assertInstanceOf( \GuzzleHttp\HandlerStack::class, $stack );

        $client = new \GuzzleHttp\Client( [
            'handler' => $stack,
            'headers' => [ 'Accept-encoding' => 'gzip' ],
        ] );

        $this->assertInstanceOf( \GuzzleHttp\ClientInterface::class, $client );

        // Cookies must stay OFF. Four of the eleven advisories this re-vendor
        // closed were cookie-jar issues, and the reachability assessment in
        // D-052 turns on this default — so it is asserted rather than trusted to
        // stay put across a future bump.
        $this->assertFalse(
            $client->getConfig( 'cookies' ),
            'Guzzle enabled a cookie jar by default. D-052 records the cookie advisories as '
            . 'unreachable BECAUSE no jar exists; that reasoning expires the moment this is true.'
        );
    }

    /**
     * psr7's URI parsing is the half of this re-vendor that changed most —
     * CVE-2026-59882 (weak URI host validation) and CVE-2026-48998 (authority
     * reinterpretation) both landed in Uri, and 2.13.0 introduces a new Rfc3986
     * parser. The five provider endpoints are the only URLs this stack parses,
     * so they are the ones that must still round-trip exactly.
     */
    public function testProviderEndpointsStillRoundTripThroughPsr7(): void
    {
        foreach ( self::PROVIDER_ENDPOINTS as $endpoint ) {
            $request = new \GuzzleHttp\Psr7\Request( 'POST', $endpoint );

            $this->assertSame(
                $endpoint,
                (string) $request->getUri(),
                "psr7 no longer round-trips a provider endpoint: {$endpoint}"
            );

            $this->assertSame( 'https', $request->getUri()->getScheme() );
            $this->assertNotSame( '', $request->getUri()->getHost() );
            $this->assertSame( 'POST', $request->getMethod() );
        }
    }
}
