<?php
/**
 * Klytos — MCP Post Type Tools
 * CRUD operations for custom post types, taxonomies, and taxonomy terms via MCP.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\MCP\Tools;

use Klytos\Core\App;
use Klytos\Core\MCP\ToolRegistry;

function registerPostTypeTools(ToolRegistry $registry): void
{
    // ─── klytos_create_post_type ───────────────────────────────
    $registry->register(
        'klytos_create_post_type',
        'Create a new custom post type. Provide an id (machine name, lowercase, no spaces, max 20 chars), a human-readable name, and optionally a slug for URL rewriting. You can also attach taxonomies at creation time by passing an array of taxonomy objects.',
        [
            'id'         => ['type' => 'string', 'description' => 'Unique machine name for the post type (lowercase, underscores allowed, max 20 chars). E.g.: "portfolio", "testimonial".'],
            'name'       => ['type' => 'string', 'description' => 'Human-readable plural label. E.g.: "Portfolios", "Testimonials".'],
            'slug'       => ['type' => 'string', 'description' => 'URL rewrite slug. E.g.: "portfolios" → /portfolios/. Defaults to id if omitted.'],
            'slug_i18n'  => ['type' => 'object', 'description' => 'Localized slugs keyed by language code. E.g.: {"es": "portafolios", "en": "portfolios"}.', 'additionalProperties' => true],
            'taxonomies' => [
                'type'        => 'array',
                'description' => 'Taxonomies to attach at creation time. Each object needs at least id and name.',
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'id'           => ['type' => 'string', 'description' => 'Machine name for the taxonomy.'],
                        'name'         => ['type' => 'string', 'description' => 'Human-readable label.'],
                        'slug'         => ['type' => 'string', 'description' => 'URL rewrite slug.'],
                        'slug_i18n'    => ['type' => 'object', 'description' => 'Localized slugs keyed by lang code.', 'additionalProperties' => true],
                        'hierarchical' => ['type' => 'boolean', 'description' => 'true = category-like (parent/child), false = tag-like (flat). Default: false.'],
                    ],
                ],
            ],
        ],
        function (array $params, App $app): array {
            $postType = $app->getPostTypeManager()->create($params);
            return ['success' => true, 'post_type' => $postType];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
        ['id', 'name']
    );

    // ─── klytos_update_post_type ──────────────────────────────
    $registry->register(
        'klytos_update_post_type',
        'Update an existing custom post type. Only provided fields will be changed. Use this to rename, change the slug, update i18n slugs, or replace the taxonomies list.',
        [
            'id'         => ['type' => 'string', 'description' => 'The post type id to update (required).'],
            'name'       => ['type' => 'string', 'description' => 'New human-readable label.'],
            'slug'       => ['type' => 'string', 'description' => 'New URL rewrite slug.'],
            'slug_i18n'  => ['type' => 'object', 'description' => 'New localized slugs keyed by language code.', 'additionalProperties' => true],
            'taxonomies' => [
                'type'        => 'array',
                'description' => 'Replace the full taxonomies list. Each object needs at least id and name.',
                'items'       => [
                    'type'       => 'object',
                    'properties' => [
                        'id'           => ['type' => 'string'],
                        'name'         => ['type' => 'string'],
                        'slug'         => ['type' => 'string'],
                        'slug_i18n'    => ['type' => 'object', 'additionalProperties' => true],
                        'hierarchical' => ['type' => 'boolean'],
                    ],
                ],
            ],
        ],
        function (array $params, App $app): array {
            $id = $params['id'] ?? '';
            unset($params['id']);
            $postType = $app->getPostTypeManager()->update($id, $params);
            return ['success' => true, 'post_type' => $postType];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['id']
    );

    // ─── klytos_delete_post_type ──────────────────────────────
    $registry->register(
        'klytos_delete_post_type',
        'Delete a custom post type by id. Built-in post types (post, page) cannot be deleted. This permanently removes the post type registration and all its taxonomy/term definitions.',
        [
            'id' => ['type' => 'string', 'description' => 'The post type id to delete.'],
        ],
        function (array $params, App $app): array {
            $deleted = $app->getPostTypeManager()->delete($params['id'] ?? '');
            return ['success' => $deleted, 'id' => $params['id'] ?? ''];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['id']
    );

    // ─── klytos_get_post_type ─────────────────────────────────
    $registry->register(
        'klytos_get_post_type',
        'Get a post type by id. Returns the full post type definition including name, slug, i18n slugs, and all attached taxonomies with their terms.',
        [
            'id' => ['type' => 'string', 'description' => 'The post type id to retrieve.'],
        ],
        function (array $params, App $app): array {
            return $app->getPostTypeManager()->get($params['id'] ?? '');
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['id']
    );

    // ─── klytos_list_post_types ───────────────────────────────
    $registry->register(
        'klytos_list_post_types',
        'List all registered post types. Returns an array of post type objects with their taxonomies. No parameters required.',
        [],
        function (array $params, App $app): array {
            $postTypes = $app->getPostTypeManager()->list();
            return ['post_types' => $postTypes, 'total' => count($postTypes)];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    // ─── klytos_add_taxonomy ──────────────────────────────────
    $registry->register(
        'klytos_add_taxonomy',
        'Add a new taxonomy to an existing post type. A taxonomy groups content (e.g., categories, tags, genres). Set hierarchical=true for category-like behavior (parent/child) or false for tag-like (flat).',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type id to add the taxonomy to.'],
            'id'           => ['type' => 'string', 'description' => 'Machine name for the new taxonomy (lowercase, underscores). E.g.: "genre", "project_type".'],
            'name'         => ['type' => 'string', 'description' => 'Human-readable label. E.g.: "Genres", "Project Types".'],
            'slug'         => ['type' => 'string', 'description' => 'URL rewrite slug. Defaults to id if omitted.'],
            'slug_i18n'    => ['type' => 'object', 'description' => 'Localized slugs keyed by language code.', 'additionalProperties' => true],
            'hierarchical' => ['type' => 'boolean', 'description' => 'true = category-like (parent/child), false = tag-like (flat). Default: false.'],
        ],
        function (array $params, App $app): array {
            $postTypeId = $params['post_type_id'] ?? '';
            unset($params['post_type_id']);
            $taxonomy = $app->getPostTypeManager()->addTaxonomy($postTypeId, $params);
            return ['success' => true, 'taxonomy' => $taxonomy];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false],
        ['post_type_id', 'id']
    );

    // ─── klytos_update_taxonomy ───────────────────────────────
    $registry->register(
        'klytos_update_taxonomy',
        'Update an existing taxonomy within a post type. Only provided fields will be changed. Use this to rename, change slug, toggle hierarchical mode, or update i18n slugs.',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type id that owns the taxonomy.'],
            'taxonomy_id'  => ['type' => 'string', 'description' => 'The taxonomy id to update.'],
            'name'         => ['type' => 'string', 'description' => 'New human-readable label.'],
            'slug'         => ['type' => 'string', 'description' => 'New URL rewrite slug.'],
            'slug_i18n'    => ['type' => 'object', 'description' => 'New localized slugs keyed by language code.', 'additionalProperties' => true],
            'hierarchical' => ['type' => 'boolean', 'description' => 'Change hierarchy mode: true = category-like, false = tag-like.'],
        ],
        function (array $params, App $app): array {
            $postTypeId = $params['post_type_id'] ?? '';
            $taxonomyId = $params['taxonomy_id'] ?? '';
            unset($params['post_type_id'], $params['taxonomy_id']);
            $taxonomy = $app->getPostTypeManager()->updateTaxonomy($postTypeId, $taxonomyId, $params);
            return ['success' => true, 'taxonomy' => $taxonomy];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['post_type_id', 'taxonomy_id']
    );

    // ─── klytos_remove_taxonomy ───────────────────────────────
    $registry->register(
        'klytos_remove_taxonomy',
        'Remove a taxonomy from a post type. This deletes the taxonomy definition and all its terms from the post type.',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type id that owns the taxonomy.'],
            'taxonomy_id'  => ['type' => 'string', 'description' => 'The taxonomy id to remove.'],
        ],
        function (array $params, App $app): array {
            $deleted = $app->getPostTypeManager()->removeTaxonomy(
                $params['post_type_id'] ?? '',
                $params['taxonomy_id'] ?? ''
            );
            return ['success' => $deleted, 'post_type_id' => $params['post_type_id'] ?? '', 'taxonomy_id' => $params['taxonomy_id'] ?? ''];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['post_type_id', 'taxonomy_id']
    );

    // ─── klytos_add_term ──────────────────────────────────────
    $registry->register(
        'klytos_add_term',
        'Add a new term to a taxonomy. Terms are the individual items within a taxonomy (e.g., "Rock" in a "Genre" taxonomy). For hierarchical taxonomies, use parent to create nested terms.',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type id that owns the taxonomy.'],
            'taxonomy_id'  => ['type' => 'string', 'description' => 'The taxonomy id to add the term to.'],
            'name'         => ['type' => 'string', 'description' => 'Human-readable term name. E.g.: "Rock", "Web Development".'],
            'slug'         => ['type' => 'string', 'description' => 'URL slug for the term. Auto-generated from name if omitted.'],
            'slug_i18n'    => ['type' => 'object', 'description' => 'Localized slugs keyed by language code.', 'additionalProperties' => true],
            'parent'       => ['type' => 'string', 'description' => 'Parent term slug for hierarchical taxonomies. Leave empty for top-level terms.'],
            'description'  => ['type' => 'string', 'description' => 'Optional description of the term.'],
        ],
        function (array $params, App $app): array {
            $postTypeId = $params['post_type_id'] ?? '';
            $taxonomyId = $params['taxonomy_id'] ?? '';
            unset($params['post_type_id'], $params['taxonomy_id']);
            $term = $app->getPostTypeManager()->addTerm($postTypeId, $taxonomyId, $params);
            return ['success' => true, 'term' => $term];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false],
        ['post_type_id', 'taxonomy_id', 'name']
    );

    // ─── klytos_update_term ───────────────────────────────────
    $registry->register(
        'klytos_update_term',
        'Update an existing taxonomy term. Identify the term by its current slug (term_slug). Only provided fields will be changed.',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type id that owns the taxonomy.'],
            'taxonomy_id'  => ['type' => 'string', 'description' => 'The taxonomy id that contains the term.'],
            'term_slug'    => ['type' => 'string', 'description' => 'Current slug of the term to update.'],
            'name'         => ['type' => 'string', 'description' => 'New human-readable name.'],
            'slug_i18n'    => ['type' => 'object', 'description' => 'New localized slugs keyed by language code.', 'additionalProperties' => true],
            'parent'       => ['type' => 'string', 'description' => 'New parent term slug (for hierarchical taxonomies). Empty string to make top-level.'],
            'description'  => ['type' => 'string', 'description' => 'New description.'],
        ],
        function (array $params, App $app): array {
            $postTypeId = $params['post_type_id'] ?? '';
            $taxonomyId = $params['taxonomy_id'] ?? '';
            $termSlug   = $params['term_slug'] ?? '';
            unset($params['post_type_id'], $params['taxonomy_id'], $params['term_slug']);
            $term = $app->getPostTypeManager()->updateTerm($postTypeId, $taxonomyId, $termSlug, $params);
            return ['success' => true, 'term' => $term];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['post_type_id', 'taxonomy_id', 'term_slug']
    );

    // ─── klytos_delete_term ───────────────────────────────────
    $registry->register(
        'klytos_delete_term',
        'Delete a taxonomy term by slug. This permanently removes the term from the taxonomy.',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type id that owns the taxonomy.'],
            'taxonomy_id'  => ['type' => 'string', 'description' => 'The taxonomy id that contains the term.'],
            'term_slug'    => ['type' => 'string', 'description' => 'Slug of the term to delete.'],
        ],
        function (array $params, App $app): array {
            $deleted = $app->getPostTypeManager()->deleteTerm(
                $params['post_type_id'] ?? '',
                $params['taxonomy_id'] ?? '',
                $params['term_slug'] ?? ''
            );
            return ['success' => $deleted, 'post_type_id' => $params['post_type_id'] ?? '', 'taxonomy_id' => $params['taxonomy_id'] ?? '', 'term_slug' => $params['term_slug'] ?? ''];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['post_type_id', 'taxonomy_id', 'term_slug']
    );

    // ─── klytos_list_terms ────────────────────────────────────
    $registry->register(
        'klytos_list_terms',
        'List all terms for a specific taxonomy within a post type. Returns an array of term objects with name, slug, i18n slugs, parent, and description.',
        [
            'post_type_id' => ['type' => 'string', 'description' => 'The post type id that owns the taxonomy.'],
            'taxonomy_id'  => ['type' => 'string', 'description' => 'The taxonomy id to list terms for.'],
        ],
        function (array $params, App $app): array {
            $terms = $app->getPostTypeManager()->listTerms(
                $params['post_type_id'] ?? '',
                $params['taxonomy_id'] ?? ''
            );
            return ['terms' => $terms, 'total' => count($terms)];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['post_type_id', 'taxonomy_id']
    );
}
