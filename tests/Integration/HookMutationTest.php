<?php

/**
 * Klytos CMS — the page-save mutation path, end to end (Sprint 4, slice 1 / audit NEW-03).
 *
 * NEW-03 names one symptom: a by-reference action listener cannot bind, so the
 * x402 post-type default is never applied to a new page. L-014's rule is to drive
 * the whole FEATURE rather than the defect the finding names — "can it be switched
 * on? can it be reached? is there anything that calls it?" — so this file asserts
 * the mechanism AND the feature, separately, because they can fail independently.
 *
 * Every assertion reads the PERSISTED record rather than the value create()
 * returned (L-017): a mutation that reaches the return value but not storage is
 * exactly the class of defect that reports success while changing nothing.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\IntegrationTestCase;

/**
 * Page data can be modified before it is written, and the write lands.
 */
final class HookMutationTest extends IntegrationTestCase
{
    /** @var string Slug used by the mechanism test; removed in tearDown. */
    private const MECHANISM_SLUG = 'sprint4-hook-mechanism';

    /** @var string Slug used by the x402 feature test; removed in tearDown. */
    private const FEATURE_SLUG = 'sprint4-x402-default';

    /**
     * The MECHANISM: a listener's modification reaches storage.
     *
     * Deliberately independent of x402. If this passes and the feature test
     * below fails, the hook contract is sound and the failure is x402's own
     * plumbing — a distinction the single "does x402 work?" test cannot make,
     * and the one that matters when a finding has more than one layer.
     */
    public function testAListenerCanModifyPageDataAndTheChangeIsPersisted(): void
    {
        $this->addTemporaryFilter(
            'page.save_data',
            static function ( array $page, string $action ): array {
                $page['meta_description'] = 'set-by-listener-on-' . $action;
                return $page;
            }
        );

        $this->app->getPages()->create( [
            'slug'  => self::MECHANISM_SLUG,
            'title' => 'Hook mechanism',
        ] );

        $persisted = $this->storage->read( 'pages', self::MECHANISM_SLUG );

        self::assertSame(
            'set-by-listener-on-create',
            $persisted['meta_description'] ?? null,
            'A listener modified the page and the modification did not reach storage.'
        );
    }

    /**
     * The FEATURE: a new page inherits its post type's x402 default.
     *
     * This is what NEW-03 says is broken in production. It is asserted through
     * the product's own managers rather than by writing storage records by hand,
     * because seeding around the API proves only that the fixture works (L-005).
     */
    public function testANewPageInheritsItsPostTypeX402Default(): void
    {
        $postTypes = $this->app->getPostTypeManager();

        $postTypes->create( [
            'id'   => 'sprint4pt',
            'name' => 'Sprint 4 Post Type',
        ] );
        $postTypes->update( 'sprint4pt', [ 'x402_default_enabled' => true ] );

        // Guard the precondition explicitly rather than letting the real
        // assertion fail for the wrong reason. A page cannot inherit a default
        // the post type does not carry, and the two failures need different
        // fixes (L-009: a fault standing in front of a fault).
        $storedType = $this->storage->read( 'post-types', 'sprint4pt' );
        self::assertTrue(
            $storedType['x402_default_enabled'] ?? false,
            'PRECONDITION FAILED: the post type does not carry x402_default_enabled, so no page '
            . 'could ever inherit it. This is a defect in the post-type save path, not in the hook.'
        );

        $this->app->getPages()->create( [
            'slug'      => self::FEATURE_SLUG,
            'title'     => 'x402 default',
            'post_type' => 'sprint4pt',
        ] );

        $persisted = $this->storage->read( 'pages', self::FEATURE_SLUG );

        self::assertTrue(
            $persisted['x402_enabled'] ?? false,
            'A new page did not inherit its post type\'s x402_default_enabled.'
        );
    }

    /**
     * `post_type.updatable_fields` cannot be used to open a reserved key.
     *
     * The filter exists so a plugin can persist its OWN keys. Naming `id`,
     * `builtin` or `created_at` would turn it into a mass-assignment primitive
     * for exactly the three fields that must never come from request data — `id`
     * is the storage key, `builtin` is trusted as authoritative elsewhere, and
     * `created_at` is history. Asserted in both directions in one test: the
     * reserved key is refused AND a normal declared key still goes through, so
     * this cannot pass by the filter simply not running (L-010).
     */
    public function testTheUpdatableFieldsFilterCannotOpenAReservedKey(): void
    {
        $postTypes = $this->app->getPostTypeManager();
        $postTypes->create( [ 'id' => 'sprint4pt', 'name' => 'Reserved key check' ] );

        $this->addTemporaryFilter(
            'post_type.updatable_fields',
            static function ( array $fields ): array {
                $fields[] = 'builtin';          // reserved — must be refused
                $fields[] = 'x402_price_usd';   // ordinary — must go through
                return $fields;
            }
        );

        $postTypes->update( 'sprint4pt', [
            'builtin'         => true,
            'x402_price_usd'  => '1.23',
        ] );

        $stored = $this->storage->read( 'post-types', 'sprint4pt' );

        self::assertFalse(
            $stored['builtin'] ?? null,
            'A filter opened the reserved `builtin` key — that is mass assignment.'
        );
        self::assertSame(
            '1.23',
            $stored['x402_price_usd'] ?? null,
            'The positive control failed: an ordinary declared key did not persist, so the '
            . 'reserved-key assertion above proves nothing.'
        );
    }

    /**
     * The visible symptom: creating a page emits no PHP diagnostic.
     *
     * Before this slice every page create in every install emitted
     * "Argument #1 ($data) must be passed by reference, value given". Asserting
     * the absence of the warning is what makes the user-facing outcome — a clean
     * log — a tested property rather than an incidental one.
     *
     * A local error handler is used rather than phpunit's warning conversion so
     * the assertion states which diagnostic it caught.
     */
    public function testCreatingAPageEmitsNoPhpDiagnostic(): void
    {
        $diagnostics = [];

        set_error_handler(
            static function ( int $errno, string $errstr ) use ( &$diagnostics ): bool {
                $diagnostics[] = $errstr;
                return true;
            }
        );

        try {
            $this->app->getPages()->create( [
                'slug'  => self::MECHANISM_SLUG,
                'title' => 'Diagnostic check',
            ] );
        } finally {
            restore_error_handler();
        }

        self::assertSame( [], $diagnostics, 'Creating a page emitted a PHP diagnostic.' );
    }

    protected function tearDown(): void
    {
        // The playground snapshot restores storage, but these run against the
        // real tree — remove what the test made before the base class asserts
        // the playground came back clean.
        foreach ( [ self::MECHANISM_SLUG, self::FEATURE_SLUG ] as $slug ) {
            if ( $this->storage->exists( 'pages', $slug ) ) {
                $this->storage->delete( 'pages', $slug );
            }
        }

        if ( $this->storage->exists( 'post-types', 'sprint4pt' ) ) {
            $this->storage->delete( 'post-types', 'sprint4pt' );
        }

        parent::tearDown();
    }
}
