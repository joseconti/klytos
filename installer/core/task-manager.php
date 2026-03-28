<?php
/**
 * Klytos — Task Manager
 * CRUD for review tasks and front-end annotations.
 *
 * Tasks are page-specific review notes created by users (or AI) to track
 * what needs to be changed, fixed, or improved on each page. They can be
 * created from the front-end review widget (klytos-review.js) or via MCP.
 *
 * Each task includes:
 * - The page it belongs to (page_slug).
 * - A CSS selector pointing to the specific element (for visual highlighting).
 * - A description of what needs to be done.
 * - Priority: low, medium, high, urgent.
 * - Status: open, in_progress, completed, dismissed.
 * - Who created it and who it's assigned to.
 *
 * Storage: Collection 'tasks' in StorageInterface.
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

class TaskManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string Collection name. */
    private const COLLECTION = 'tasks';

    /** @var array Valid task priorities (ascending severity). */
    private const VALID_PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /** @var array Valid task statuses. */
    private const VALID_STATUSES = ['open', 'in_progress', 'completed', 'dismissed'];

    /**
     * @param StorageInterface $storage Storage backend instance.
     */
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Create a new task.
     *
     * @param  array $data Task data: page_slug (required), description (required),
     *                     css_selector, priority, assigned_to, created_by.
     * @return array The created task.
     * @throws \InvalidArgumentException On validation failure.
     */
    public function create(array $data): array
    {
        $pageSlug    = trim($data['page_slug'] ?? '');
        $description = trim($data['description'] ?? '');

        if (empty($pageSlug)) {
            throw new \InvalidArgumentException('page_slug is required.');
        }
        if (empty($description)) {
            throw new \InvalidArgumentException('description is required.');
        }

        $priority = $data['priority'] ?? 'medium';
        if (!in_array($priority, self::VALID_PRIORITIES, true)) {
            $priority = 'medium';
        }

        // Sanitize CSS selector to prevent XSS injection.
        $cssSelector = $this->sanitizeCssSelector($data['css_selector'] ?? '');

        $taskId = Helpers::randomHex(8);

        $task = [
            'id'           => $taskId,
            'page_slug'    => Helpers::sanitizeSlug($pageSlug),
            'css_selector' => $cssSelector,
            'description'  => Helpers::sanitizeHtml($description),
            'priority'     => $priority,
            'status'       => 'open',
            'created_by'   => $data['created_by'] ?? null,
            'assigned_to'  => $data['assigned_to'] ?? null,
            'created_at'   => Helpers::now(),
            'updated_at'   => Helpers::now(),
            'completed_at' => null,
        ];

        $this->storage->write(self::COLLECTION, $taskId, $task);

        Hooks::doAction('task.created', $task);

        return $task;
    }

    /**
     * Get a task by ID.
     *
     * @param  string $taskId Task ID.
     * @return array  Task data.
     * @throws \RuntimeException If not found.
     */
    public function get(string $taskId): array
    {
        return $this->storage->read(self::COLLECTION, $taskId);
    }

    /**
     * Update a task (partial update).
     *
     * @param  string $taskId Task ID.
     * @param  array  $data   Fields to update: description, priority, status, assigned_to.
     * @return array  Updated task.
     */
    public function update(string $taskId, array $data): array
    {
        $task = $this->storage->read(self::COLLECTION, $taskId);

        // Updatable fields.
        if (isset($data['description'])) {
            $task['description'] = Helpers::sanitizeHtml(trim($data['description']));
        }
        if (isset($data['priority']) && in_array($data['priority'], self::VALID_PRIORITIES, true)) {
            $task['priority'] = $data['priority'];
        }
        if (isset($data['status']) && in_array($data['status'], self::VALID_STATUSES, true)) {
            $task['status'] = $data['status'];
            if ($data['status'] === 'completed') {
                $task['completed_at'] = Helpers::now();
            }
        }
        if (array_key_exists('assigned_to', $data)) {
            $task['assigned_to'] = $data['assigned_to'];
        }

        $task['updated_at'] = Helpers::now();
        $this->storage->write(self::COLLECTION, $taskId, $task);

        return $task;
    }

    /**
     * Mark a task as completed.
     *
     * Shortcut for update($taskId, ['status' => 'completed']).
     *
     * @param  string $taskId Task ID.
     * @return array  Updated task.
     */
    public function complete(string $taskId): array
    {
        $task = $this->update($taskId, ['status' => 'completed']);

        Hooks::doAction('task.completed', $task);

        return $task;
    }

    /**
     * Delete a task.
     *
     * @param  string $taskId Task ID.
     * @return bool   True if deleted.
     */
    public function delete(string $taskId): bool
    {
        return $this->storage->delete(self::COLLECTION, $taskId);
    }

    /**
     * List tasks with optional filters.
     *
     * @param  string $status   Filter by status ('all' for no filter).
     * @param  string $pageSlug Filter by page slug (empty for all pages).
     * @param  int    $limit    Maximum results.
     * @param  int    $offset   Skip N results.
     * @return array  Array of tasks.
     */
    public function list(
        string $status = 'all',
        string $pageSlug = '',
        int $limit = 50,
        int $offset = 0,
    ): array {
        $filters = [];
        if ($status !== 'all' && in_array($status, self::VALID_STATUSES, true)) {
            $filters['status'] = $status;
        }

        $tasks = $this->storage->list(self::COLLECTION, $filters);

        // Filter by page slug in memory (not indexed in DB).
        if (!empty($pageSlug)) {
            $tasks = array_filter($tasks, fn(array $t): bool =>
                ($t['page_slug'] ?? '') === $pageSlug
            );
            $tasks = array_values($tasks);
        }

        // Sort: urgent first, then by creation date (newest first).
        usort($tasks, function (array $a, array $b): int {
            $priorityOrder = array_flip(self::VALID_PRIORITIES);
            $pA = $priorityOrder[$a['priority'] ?? 'medium'] ?? 1;
            $pB = $priorityOrder[$b['priority'] ?? 'medium'] ?? 1;
            if ($pA !== $pB) {
                return $pB - $pA; // Higher priority first.
            }
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        return array_slice($tasks, $offset, $limit > 0 ? $limit : null);
    }

    /**
     * Count tasks with optional status filter.
     *
     * @param  string $status Filter by status ('all' for no filter).
     * @return int
     */
    public function count(string $status = 'all'): int
    {
        $filters = [];
        if ($status !== 'all') {
            $filters['status'] = $status;
        }
        return $this->storage->count(self::COLLECTION, $filters);
    }

    // ─── Internal ────────────────────────────────────────────────

    /**
     * Sanitize a CSS selector to prevent XSS attacks.
     *
     * Removes JavaScript protocol, event handlers, and potentially dangerous characters.
     * Only allows standard CSS selector characters.
     *
     * @param  string $selector Raw CSS selector string.
     * @return string Sanitized CSS selector.
     */
    private function sanitizeCssSelector(string $selector): string
    {
        // Remove any content that looks like JavaScript or event handlers.
        $selector = preg_replace('/javascript\s*:/i', '', $selector);
        $selector = preg_replace('/on\w+\s*=/i', '', $selector);

        // Allow only safe CSS selector characters:
        // letters, numbers, spaces, dots, hashes, colons, brackets, hyphens,
        // underscores, commas, parentheses, quotes, >, +, ~, *.
        $selector = preg_replace('/[^a-zA-Z0-9\s\.\#\:\[\]\-\_\,\(\)\"\'\>\+\~\*\=\^]/', '', $selector);

        return trim($selector);
    }
}
