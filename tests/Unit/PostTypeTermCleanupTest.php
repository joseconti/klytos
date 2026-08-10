<?php

/**
 * Klytos CMS — deleting a post type deletes its terms.
 *
 * Found by the fixture of manifest entry 32 (Taxonomies), which re-creates its
 * post type on every run and started failing with the manager's OWN duplicate
 * guard: "Term 'e2e-parent-term' already exists in taxonomy 'e2e-tax-cat'" —
 * for a taxonomy of a post type that had just been deleted.
 *
 * `PostTypeManager::delete()` removes the post-type record FIRST and only then
 * calls `deleteAllTerms()`, whose first act is `$this->get( $postTypeId )` —
 * a read of the record that was deleted one line earlier. The read fails, and
 * the `catch ( \RuntimeException )` around it swallows the failure with the
 * comment "Post type already deleted, nothing to clean up." So the cleanup has
 * never once run: every term of every post type ever deleted is still in
 * storage on every install, and a post type re-created under the same id
 * silently inherits the dead one's terms.
 *
 * That is L-041's shape for the third time in this project — a `catch` turning
 * a broken feature into a confident silence — and, unlike the two before it,
 * this one also leaves data behind. The comment inside `delete()` states the
 * behaviour in the present tense ("Also delete all taxonomy term data for this
 * post type"), which is the "declared is not delivered" defect exactly.
 *
 * The red was observed before the fix and it names the ABSENT BEHAVIOUR — a
 * term surviving its post type — not a missing method or a typo.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\PostTypeManager;
use Klytos\Tests\UnitTestCase;

/**
 * The delete contract of the post-type store, both directions.
 *
 * One direction alone is half a test (L-010): the terms of the deleted type go,
 * and the terms of every OTHER type stay — a cleanup that over-reaches is a
 * worse defect than one that under-reaches, because it destroys records nobody
 * asked it to touch.
 */
final class PostTypeTermCleanupTest extends UnitTestCase
{
    private function makeManager(): PostTypeManager
    {
        return new PostTypeManager( $this->storage );
    }

    /** Create a post type with one taxonomy holding one term. */
    private function seed( PostTypeManager $manager, string $postType, string $taxonomy, string $term ): void
    {
        $manager->create( [ 'id' => $postType, 'name' => 'Type ' . $postType ] );
        $manager->addTaxonomy( $postType, [
            'id'           => $taxonomy,
            'name'         => 'Taxonomy ' . $taxonomy,
            'hierarchical' => true,
        ] );
        $manager->addTerm( $postType, $taxonomy, [ 'name' => 'Term', 'slug' => $term ] );
    }

    // ─── The reproduction (the defect) ───────────────────────────

    public function testDeletingAPostTypeDeletesItsTerms(): void
    {
        $manager = $this->makeManager();
        $this->seed( $manager, 'probe', 'probe-tax', 'ghost' );

        $manager->delete( 'probe' );

        // Re-create the SAME id, which is how a person meets this defect: the
        // new post type comes up already holding the dead one's records.
        $manager->create( [ 'id' => 'probe', 'name' => 'Probe again' ] );
        $manager->addTaxonomy( 'probe', [
            'id'           => 'probe-tax',
            'name'         => 'Taxonomy',
            'hierarchical' => true,
        ] );

        $this->assertSame(
            [],
            $manager->listTerms( 'probe', 'probe-tax' ),
            'A term outlived the post type that owned it: deleteAllTerms() reads a record delete() has already removed.'
        );
    }

    // ─── The other direction (the over-reach) ────────────────────

    public function testDeletingAPostTypeLeavesAnotherTypesTermsAlone(): void
    {
        $manager = $this->makeManager();
        $this->seed( $manager, 'gone', 'shared-name', 'kept-a' );
        $this->seed( $manager, 'stays', 'shared-name', 'kept-b' );

        $manager->delete( 'gone' );

        $this->assertSame(
            [ 'kept-b' ],
            array_column( $manager->listTerms( 'stays', 'shared-name' ), 'slug' ),
            'The cleanup reached into a post type it was not asked to touch.'
        );
    }
}
