<?php
/**
 * Klytos — Version Manager
 * Page version history with save, restore, diff, and prune capabilities.
 *
 * Every time a page is saved (from admin, inline editor, or MCP), a snapshot
 * of the complete page data is stored. This allows users to:
 * - View previous versions of any page.
 * - Compare two versions side by side (diff).
 * - Restore a page to a previous version.
 * - Prune old versions to save storage space.
 *
 * Storage:
 * - Collection 'page-versions' in StorageInterface.
 * - Each version ID format: {page_slug}--v{number} (e.g. 'index--v1', 'about--v5').
 * - Maximum versions per page: configurable, default 50.
 *
 * @package Klytos
 * @since   2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class VersionManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string Collection name for page versions. */
    private const COLLECTION = 'page-versions';

    /** @var int Default maximum versions kept per page. */
    private const DEFAULT_MAX_VERSIONS = 50;

    /**
     * @param StorageInterface $storage Storage backend instance.
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Save a new version snapshot of a page.
     *
     * Called automatically after every page save (admin, inline, MCP).
     * Stores the complete page data at that point in time.
     *
     * @param  string      $pageSlug The page's slug identifier.
     * @param  array       $pageData Complete page data at the time of save.
     * @param  string      $source   Where the save originated: 'admin', 'mcp', 'inline', 'cli'.
     * @param  string|null $userId   User who saved (null for system/MCP actions).
     * @return array       The created version record.
     */
    public function save(string $pageSlug, array $pageData, string $source = 'admin', ?string $userId = null): array
    {
        // Determine the next version number for this page.
        $existingVersions = $this->listForPage($pageSlug);
        $nextNumber       = count($existingVersions) + 1;

        $versionId = $this->buildVersionId($pageSlug, $nextNumber);

        $version = [
            'id'         => $versionId,
            'page_slug'  => $pageSlug,
            'version'    => $nextNumber,
            'data'       => $pageData,
            'source'     => $source,
            'user_id'    => $userId,
            'created_at' => Helpers::now(),
        ];

        $this->storage->write(self::COLLECTION, $versionId, $version);

        // Auto-prune if we exceed the maximum versions for this page.
        $this->pruneIfNeeded($pageSlug);

        return $version;
    }

    /**
     * Get a specific version of a page.
     *
     * @param  string $pageSlug     Page slug.
     * @param  int    $versionNumber Version number (1-based).
     * @return array  Version record including the full page data snapshot.
     * @throws \RuntimeException If the version does not exist.
     */
    public function get(string $pageSlug, int $versionNumber): array
    {
        $versionId = $this->buildVersionId($pageSlug, $versionNumber);
        return $this->storage->read(self::COLLECTION, $versionId);
    }

    /**
     * List all versions of a page, newest first.
     *
     * Returns metadata only (no full page data) for performance.
     *
     * @param  string $pageSlug Page slug.
     * @param  int    $limit    Maximum versions to return.
     * @return array  Array of version summaries (id, version, source, user_id, created_at).
     */
    public function listForPage(string $pageSlug, int $limit = 0): array
    {
        $allVersions = $this->storage->list(self::COLLECTION);

        // Filter by page slug.
        $pageVersions = array_filter($allVersions, fn(array $v): bool =>
            ($v['page_slug'] ?? '') === $pageSlug
        );

        // Sort by version number descending (newest first).
        usort($pageVersions, fn(array $a, array $b): int =>
            ($b['version'] ?? 0) - ($a['version'] ?? 0)
        );

        // Return summaries (without the full page data to save memory).
        $summaries = array_map(function (array $v): array {
            return [
                'id'         => $v['id'] ?? '',
                'page_slug'  => $v['page_slug'] ?? '',
                'version'    => $v['version'] ?? 0,
                'source'     => $v['source'] ?? '',
                'user_id'    => $v['user_id'] ?? null,
                'created_at' => $v['created_at'] ?? '',
            ];
        }, $pageVersions);

        $summaries = array_values($summaries);

        if ($limit > 0) {
            $summaries = array_slice($summaries, 0, $limit);
        }

        return $summaries;
    }

    /**
     * Restore a page to a previous version.
     *
     * Reads the version's data snapshot and writes it as the current page.
     * Also creates a new version entry for the restoration itself.
     *
     * @param  string $pageSlug      Page slug.
     * @param  int    $versionNumber Version number to restore.
     * @param  string $source        Who initiated the restore.
     * @param  string|null $userId   User who restored.
     * @return array  The restored page data.
     * @throws \RuntimeException If the version does not exist.
     */
    public function restore(string $pageSlug, int $versionNumber, string $source = 'admin', ?string $userId = null): array
    {
        $version  = $this->get($pageSlug, $versionNumber);
        $pageData = $version['data'] ?? [];

        // Update the page's timestamp to now.
        $pageData['updated_at'] = Helpers::now();

        // Write the restored data as the current page.
        $this->storage->write('pages', $pageSlug, $pageData);

        // Save a new version entry marking this as a restoration.
        $pageData['_restored_from'] = $versionNumber;
        $this->save($pageSlug, $pageData, $source, $userId);

        return $pageData;
    }

    /**
     * Generate a diff between two versions of a page.
     *
     * Compares field by field and returns what changed.
     *
     * @param  string $pageSlug Page slug.
     * @param  int    $versionA First version number (older).
     * @param  int    $versionB Second version number (newer).
     * @return array  Diff result: ['changed' => [...], 'added' => [...], 'removed' => [...]].
     */
    public function diff(string $pageSlug, int $versionA, int $versionB): array
    {
        $dataA = ($this->get($pageSlug, $versionA))['data'] ?? [];
        $dataB = ($this->get($pageSlug, $versionB))['data'] ?? [];

        $changed = [];
        $added   = [];
        $removed = [];

        // Find changed and removed fields.
        foreach ($dataA as $key => $valueA) {
            if (!array_key_exists($key, $dataB)) {
                $removed[$key] = $valueA;
            } elseif ($dataB[$key] !== $valueA) {
                $changed[$key] = [
                    'from' => $valueA,
                    'to'   => $dataB[$key],
                ];
            }
        }

        // Find added fields.
        foreach ($dataB as $key => $valueB) {
            if (!array_key_exists($key, $dataA)) {
                $added[$key] = $valueB;
            }
        }

        return [
            'page_slug' => $pageSlug,
            'version_a' => $versionA,
            'version_b' => $versionB,
            'changed'   => $changed,
            'added'     => $added,
            'removed'   => $removed,
            'has_changes' => !empty($changed) || !empty($added) || !empty($removed),
        ];
    }

    /**
     * Prune old versions for a specific page, keeping only the most recent N.
     *
     * @param  string $pageSlug   Page slug.
     * @param  int    $keepCount  Number of versions to keep. Default: 50.
     * @return int    Number of versions deleted.
     */
    public function prune(string $pageSlug, int $keepCount = self::DEFAULT_MAX_VERSIONS): int
    {
        $versions = $this->listForPage($pageSlug);

        if (count($versions) <= $keepCount) {
            return 0;
        }

        // Versions are already sorted newest-first. Delete the oldest ones.
        $toDelete = array_slice($versions, $keepCount);
        $deleted  = 0;

        foreach ($toDelete as $version) {
            $versionId = $version['id'] ?? '';
            if (!empty($versionId)) {
                $this->storage->delete(self::COLLECTION, $versionId);
                $deleted++;
            }
        }

        return $deleted;
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Build a version ID from page slug and version number.
     *
     * Format: {slug}--v{number}
     * Example: 'index--v1', 'en--about--v3' (nested slugs use --)
     *
     * @param  string $pageSlug      Page slug.
     * @param  int    $versionNumber Version number.
     * @return string Version ID.
     */
    private function buildVersionId(string $pageSlug, int $versionNumber): string
    {
        // Replace slashes with double-hyphens (same as FileStorage).
        $safeSlug = str_replace('/', '--', $pageSlug);
        return $safeSlug . '--v' . $versionNumber;
    }

    /**
     * Auto-prune if the page exceeds the maximum version count.
     *
     * @param string $pageSlug Page slug.
     */
    private function pruneIfNeeded(string $pageSlug): void
    {
        $maxVersions = self::DEFAULT_MAX_VERSIONS;
        $this->prune($pageSlug, $maxVersions);
    }
}
