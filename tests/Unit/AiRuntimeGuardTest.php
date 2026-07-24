<?php

/**
 * Klytos CMS — the AI stack refuses an unsupported runtime instead of fataling
 * inside vendored code (Sprint 3, slice 2 / audit NEW-06).
 *
 * WHAT IS BEING PINNED. `installer/vendor-ai/composer/autoload_real.php`
 * requires Composer's generated `platform_check.php` unconditionally, and below
 * PHP 8.3 that file sends HTTP 500, echoes "Composer detected issues in your
 * platform" into the response body and throws a bare \RuntimeException — all
 * before Klytos can say which feature failed. `App::getChatEngine()` now refuses
 * ABOVE that require.
 *
 * WHY THE POLICY IS A PURE STATIC, AND WHY THIS TEST CAN EXIST AT ALL. PHP
 * cannot be downgraded inside the suite, so a guard reading PHP_VERSION_ID
 * directly could never be observed refusing anything — it would be a branch no
 * test could reach, which is the L-010 shape (a guard indistinguishable from one
 * that cannot fire). Splitting the decision into
 * `App::aiRuntimeUnsupportedReason( int )` makes every branch reachable, exactly
 * as D-044 split `Auth::buildSecurityHeaders()` out of `sendSecurityHeaders()`
 * for the same reason.
 *
 * THE LIMIT, STATED RATHER THAN IMPLIED (L-014). On a supported runtime this
 * tier cannot drive `getChatEngine()` down the refusing branch. What it proves
 * is the decision and the ordering; that the refusal is REACHED is proven
 * structurally by `testTheGuardRunsBeforeTheVendoredAutoloaderIsRequired()`
 * below, and the supported path is proven for real by
 * `AiRuntimeGuardIntegrationTest`.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\Ai\UnsupportedRuntimeException;
use Klytos\Core\App;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AiRuntimeGuardTest extends TestCase
{
    /**
     * Versions below the floor must be refused, including the version one patch
     * below it — an off-by-one in the comparison is the whole risk here.
     *
     * @return array<string, array{int}>
     */
    public static function unsupportedVersions(): array
    {
        return [
            'PHP 8.1.0 — the product floor, below the AI stack floor' => [ 80100 ],
            'PHP 8.2.0 — the suite floor (D-027), still below'        => [ 80200 ],
            'PHP 8.2.99 — one patch below the boundary'               => [ 80299 ],
        ];
    }

    /**
     * @return array<string, array{int}>
     */
    public static function supportedVersions(): array
    {
        return [
            'PHP 8.3.0 — exactly the floor, must be ALLOWED' => [ 80300 ],
            'PHP 8.4.0 — above the floor'                    => [ 80400 ],
        ];
    }

    #[DataProvider( 'unsupportedVersions' )]
    public function testRuntimesBelowTheFloorAreRefused( int $phpVersionId ): void
    {
        $this->assertSame(
            'php_version_too_low',
            App::aiRuntimeUnsupportedReason( $phpVersionId ),
            'A runtime below the vendored AI stack floor must be refused, so the '
            . 'feature degrades instead of fataling inside vendor-ai/.'
        );
    }

    /**
     * The positive case, asserted on purpose: a guard that only ever says "no"
     * is indistinguishable from a broken feature (L-008).
     */
    #[DataProvider( 'supportedVersions' )]
    public function testRuntimesAtOrAboveTheFloorAreAllowed( int $phpVersionId ): void
    {
        $this->assertNull(
            App::aiRuntimeUnsupportedReason( $phpVersionId ),
            'A supported runtime must NOT be refused — an over-eager guard would '
            . 'disable AI chat on hosts that can run it perfectly well.'
        );
    }

    /**
     * The floor is a single constant and must agree with what Composer actually
     * generated. If a future re-vendor raises `soukicz/llm`'s requirement, this
     * is what fails — rather than a user discovering it as a 500.
     */
    public function testTheConstantMatchesTheGeneratedPlatformCheck(): void
    {
        $platformCheck = (string) file_get_contents(
            KLYTOS_INSTALLER_PATH . '/vendor-ai/composer/platform_check.php'
        );

        $this->assertSame(
            1,
            preg_match( '/PHP_VERSION_ID\s*>=\s*(\d+)/', $platformCheck, $m ),
            'Could not read the floor out of the generated platform_check.php — '
            . 'the check is not verified, it did not run (L-016).'
        );

        $this->assertSame(
            (int) $m[1],
            App::AI_MIN_PHP_VERSION_ID,
            'App::AI_MIN_PHP_VERSION_ID has drifted from the floor Composer '
            . 'generated into vendor-ai/composer/platform_check.php.'
        );
    }

    /**
     * The ordering is the load-bearing property and NO runtime test on a
     * supported host can reach it: once the vendored autoloader has been
     * required, platform_check.php has already thrown, so a guard placed below it
     * would be dead code that still looks correct in review.
     *
     * Asserted on the source, the same way D-046 established that the MCP gate
     * sits above the `mcp.handle_tool` filter by line order.
     */
    public function testTheGuardRunsBeforeTheVendoredAutoloaderIsRequired(): void
    {
        $source = (string) file_get_contents( KLYTOS_INSTALLER_PATH . '/core/app.php' );

        $guardAt   = strpos( $source, 'aiRuntimeUnsupportedReason( PHP_VERSION_ID )' );
        $guardAt   = $guardAt === false ? strpos( $source, 'aiRuntimeUnsupportedReason(PHP_VERSION_ID)' ) : $guardAt;
        $requireAt = strpos( $source, 'require_once $vendorAutoload' );

        $this->assertNotFalse( $guardAt, 'The NEW-06 guard call is not in App::getChatEngine() at all.' );
        $this->assertNotFalse( $requireAt, 'The vendor-ai autoload require is not where this test expects it.' );

        $this->assertLessThan(
            $requireAt,
            $guardAt,
            'The NEW-06 guard must run BEFORE require_once $vendorAutoload. Below it '
            . 'the guard is unreachable: platform_check.php has already sent HTTP 500 '
            . 'and thrown from inside vendored code.'
        );
    }

    /**
     * The exception carries both versions so a caller (or a log line) can say
     * what is needed and what is running, rather than only that something failed.
     */
    public function testTheExceptionCarriesBothVersions(): void
    {
        $e = new UnsupportedRuntimeException( 'message', 80300, 80200 );

        $this->assertSame( 80300, $e->getRequiredVersionId() );
        $this->assertSame( 80200, $e->getRunningVersionId() );
        $this->assertSame( 'message', $e->getMessage() );
        $this->assertInstanceOf( \RuntimeException::class, $e );
    }
}
