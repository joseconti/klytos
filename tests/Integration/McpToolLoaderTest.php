<?php

/**
 * Klytos CMS — the MCP tool loader fails LOUDLY (Sprint 2, slice 3 / decision
 * D-049, L-007).
 *
 * The loader used to skip a listed file that was missing or registered nothing
 * by silent fall-through — which is exactly how integrity-tools.php stayed dead
 * and unnoticed for its whole life. It now throws a typed
 * ToolRegistrationException, so an unfinished or misnamed registration breaks
 * boot/CI with a named message rather than shipping as a quietly-missing tool.
 *
 * The contract is exercised through the extracted per-file method
 * registerToolFile(), so the fail-loud branches are driven with fixture files
 * without mutating the hardcoded $toolFiles list keel-verify check 10 parses.
 * The real 34-file loader is also run once, to prove every listed file —
 * integrity-tools.php now among them — registers without throwing.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Core\MCP\ToolRegistrationException;
use Klytos\Core\MCP\ToolRegistry;
use Klytos\Tests\IntegrationTestCase;

final class McpToolLoaderTest extends IntegrationTestCase
{
    /** A scratch directory for fixture tool files, per process. */
    private function fixtureDir(): string
    {
        $dir = sys_get_temp_dir() . '/klytos-loader-' . getmypid();
        if ( ! is_dir( $dir ) ) {
            mkdir( $dir, 0700, true );
        }

        return $dir;
    }

    /**
     * A file on the loader list but not on disk is a misnamed or removed
     * registration — it fails loudly rather than being skipped. Against the old
     * silent fall-through this returned quietly and the tool simply vanished.
     */
    public function testAMissingFileFailsLoudly(): void
    {
        $registry = new ToolRegistry( $this->app );

        $this->expectException( ToolRegistrationException::class );
        $registry->registerToolFile( $this->fixtureDir(), 'this-file-does-not-exist-tools.php' );
    }

    /**
     * A file that exists but defines neither its namespaced nor its global
     * register function registers nothing — the integrity-tools.php failure mode
     * — and now fails loudly with a named message.
     */
    public function testAFileThatRegistersNothingFailsLoudly(): void
    {
        $dir  = $this->fixtureDir();
        $file = 'empty-registration-tools.php';
        file_put_contents( $dir . '/' . $file, "<?php\n// A listed file that registers nothing.\n" );

        try {
            $registry = new ToolRegistry( $this->app );
            $registry->registerToolFile( $dir, $file );
            self::fail( 'A file that registers no tools did not fail loudly.' );
        } catch ( ToolRegistrationException $e ) {
            self::assertStringContainsString( 'registers no tools', $e->getMessage() );
        } finally {
            @unlink( $dir . '/' . $file );
        }
    }

    /**
     * The positive control: a file that DOES define a matching global register
     * function registers its tool and does not throw — so the fail-loud branches
     * above are refusing a real defect, not everything (L-008).
     */
    public function testAValidFileRegistersWithoutThrowing(): void
    {
        $dir  = $this->fixtureDir();
        $file = 'probe-tools.php';
        file_put_contents(
            $dir . '/' . $file,
            "<?php\nfunction registerProbeTools( \$registry, \$app ): void {\n"
            . "    \$registry->register( 'klytos_probe_ok', 'probe', [], static fn(): array => [] );\n}\n"
        );

        try {
            $registry = new ToolRegistry( $this->app );
            $registry->registerToolFile( $dir, $file );
            self::assertTrue( $registry->exists( 'klytos_probe_ok' ), 'a valid file must register its tool' );
        } finally {
            @unlink( $dir . '/' . $file );
        }
    }

    /**
     * The REAL loader registers every one of its listed files without throwing —
     * proof that integrity-tools.php, wired into the list this slice, has a
     * working registration (against the unfixed loader it was absent from the
     * list and its 3 tools never registered at all).
     */
    public function testTheRealLoaderWiresInIntegrityToolsWithoutThrowing(): void
    {
        $registry = new ToolRegistry( $this->app );
        $registry->registerAllTools();

        self::assertTrue( $registry->exists( 'klytos_integrity_check' ) );
        self::assertTrue( $registry->exists( 'klytos_integrity_status' ) );
        self::assertTrue( $registry->exists( 'klytos_integrity_check_plugin' ) );
    }
}
