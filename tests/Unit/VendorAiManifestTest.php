<?php

/**
 * Klytos CMS — the vendored AI tree and its manifest must not drift apart
 * (Sprint 1, slice 2 / H-04).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the reconstructed manifest for installer/vendor-ai/ (D-028).
 *
 * The manifest only has value while it describes what is actually vendored: an
 * audit run against a manifest that has drifted reports on a tree nobody ships.
 * That is the L-002 defect — a document asserting a property the code no longer
 * has — so it gets a mechanical check rather than a promise.
 *
 * Extends TestCase directly rather than UnitTestCase: this asserts facts about
 * tracked files, so the per-test encryption key and throwaway storage that base
 * case builds would be set-up cost with no consumer.
 *
 * Boundary, stated so it is not mistaken for a stronger guarantee: all four
 * readers compare *metadata* — the manifest, the lock, Composer's generated
 * installed.php and the licence notice. None hashes the vendored source. Code
 * edited in place inside a package directory, with installed.php left alone,
 * would pass here. Detecting that is integrity-manifest work (core/keys/), not
 * this test's job.
 */
final class VendorAiManifestTest extends TestCase
{
    /** @var string Path to the reconstructed manifest. */
    private const MANIFEST = KLYTOS_INSTALLER_PATH . '/composer.json';

    /** @var string Path to the lock resolved from that manifest. */
    private const LOCK = KLYTOS_INSTALLER_PATH . '/composer.lock';

    /** @var string Composer's own record of what is installed in vendor-ai/. */
    private const INSTALLED = KLYTOS_INSTALLER_PATH . '/vendor-ai/composer/installed.php';

    /** @var string The human-readable third-party licence notice. */
    private const NOTICE = KLYTOS_INSTALLER_PATH . '/vendor-ai/LICENSE-THIRD-PARTY.md';

    public function testManifestPinsExactlyWhatIsVendored(): void
    {
        $vendored = $this->vendoredVersions();
        $required = $this->manifestRequirements();

        // Both directions: a package vendored but unpinned would be audited
        // against nothing, and a package pinned but not vendored would make the
        // audit report on code that does not ship.
        self::assertSame(
            array_keys( $vendored ),
            array_keys( $required ),
            'installer/composer.json must require exactly the packages present in vendor-ai/.'
        );

        foreach ( $vendored as $name => $version ) {
            self::assertSame(
                $version,
                $required[ $name ],
                "installer/composer.json pins {$name} at a version other than the one vendored."
            );
        }
    }

    public function testLockResolvesToTheVendoredVersions(): void
    {
        $vendored = $this->vendoredVersions();
        $locked   = $this->lockedVersions();

        self::assertSame(
            $vendored,
            $locked,
            'composer.lock has drifted from vendor-ai/: `composer update --no-install -d installer` and re-verify.'
        );
    }

    public function testEveryVendoredPackageAppearsInTheLicenceNotice(): void
    {
        $notice = file_get_contents( self::NOTICE );
        self::assertIsString( $notice );

        $listed = [];

        // Headings look like: "## soukicz/llm (v0.5.0)".
        if ( preg_match_all( '/^##\s+(\S+)\s+\(v?([^)]+)\)/m', $notice, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $listed[ $match[1] ] = $this->normalise( $match[2] );
            }
        }

        ksort( $listed );

        // Redistribution under MIT/BSD carries the notice obligation, so a
        // package missing here is a licence defect, not a documentation nit.
        self::assertSame(
            $this->vendoredVersions(),
            $listed,
            'LICENSE-THIRD-PARTY.md must list every vendored package at its vendored version.'
        );
    }

    /**
     * The versions Composer records as actually installed in vendor-ai/.
     *
     * @return array<string, string> Package name => normalised version, sorted by name.
     */
    private function vendoredVersions(): array
    {
        $installed = require self::INSTALLED;
        $versions  = [];

        // The root package is whatever installed.php says it is — read it, never
        // assume a spelling. Until Sprint 3 this was hardcoded to '__root__',
        // which is only what Composer writes for a root package it cannot name.
        // The tree this guard was built against (D-028) was vendored elsewhere
        // and never regenerated here, so that spelling survived untested; the
        // first real `composer update` in this repository renamed the entry to
        // 'klytos/vendor-ai-manifest' and the root leaked into the comparison.
        // A vendored package can never collide with this name — Composer refuses
        // to install a package named after its own root.
        $rootName = $installed['root']['name'] ?? '__root__';

        foreach ( $installed['versions'] as $name => $data ) {
            // Skip the root package and virtual entries (provide/replace), which
            // carry no pretty_version and are not vendored directories.
            if ( $name === $rootName || ! isset( $data['pretty_version'] ) ) {
                continue;
            }

            $versions[ $name ] = $this->normalise( $data['pretty_version'] );
        }

        ksort( $versions );

        return $versions;
    }

    /**
     * The versions pinned in the reconstructed manifest.
     *
     * @return array<string, string> Package name => normalised version, sorted by name.
     */
    private function manifestRequirements(): array
    {
        $manifest = json_decode( (string) file_get_contents( self::MANIFEST ), true, 512, JSON_THROW_ON_ERROR );
        $required = [];

        foreach ( $manifest['require'] as $name => $constraint ) {
            // Platform requirements (php, ext-*) are not vendored packages.
            if ( $name === 'php' || str_starts_with( $name, 'ext-' ) || str_starts_with( $name, 'lib-' ) ) {
                continue;
            }

            $required[ $name ] = $this->normalise( $constraint );
        }

        ksort( $required );

        return $required;
    }

    /**
     * The versions Composer resolved into the lock.
     *
     * @return array<string, string> Package name => normalised version, sorted by name.
     */
    private function lockedVersions(): array
    {
        $lock   = json_decode( (string) file_get_contents( self::LOCK ), true, 512, JSON_THROW_ON_ERROR );
        $locked = [];

        foreach ( $lock['packages'] as $package ) {
            $locked[ $package['name'] ] = $this->normalise( $package['version'] );
        }

        ksort( $locked );

        return $locked;
    }

    /**
     * Strip the tag prefix so "v3.12.1", "3.12.1" and the lock's "v3.12.1"
     * compare equal — the same release written three ways by three tools.
     *
     * @param  string $version Raw version or exact constraint.
     * @return string
     */
    private function normalise( string $version ): string
    {
        return ltrim( trim( $version ), 'v' );
    }
}
