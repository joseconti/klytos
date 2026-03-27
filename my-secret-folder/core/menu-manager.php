<?php
/**
 * Klytos — Menu Manager
 * Manages site navigation menus.
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

namespace Klytos\Core;

class MenuManager
{
    /** @var StorageInterface Storage backend (FileStorage or DatabaseStorage). */
    private StorageInterface $storage;
    private const COLLECTION = 'config';
    private const ID         = 'menus';

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Get the current menu structure.
     *
     * @return array
     */
    public function get(): array
    {
        if (!$this->storage->exists(self::COLLECTION, self::ID)) {
            return ['items' => []];
        }

        return $this->storage->read(self::COLLECTION, self::ID);
    }

    /**
     * Set the full menu structure (replaces everything).
     *
     * @param  array $items Array of menu items.
     * @return array The saved menu.
     */
    public function set(array $items): array
    {
        $menu = ['items' => $this->normalizeItems($items)];
        $this->storage->write(self::COLLECTION, self::ID, $menu);

        return $menu;
    }

    /**
     * Add a single item to the menu.
     *
     * @param  array $item Menu item data.
     * @return array Updated menu.
     */
    public function addItem(array $item): array
    {
        $menu  = $this->get();
        $item  = $this->normalizeItem($item);

        $menu['items'][] = $item;
        $this->storage->write(self::COLLECTION, self::ID, $menu);

        return $menu;
    }

    /**
     * Remove an item from the menu by ID.
     *
     * @param  string $id Item ID.
     * @return array  Updated menu.
     */
    public function removeItem(string $id): array
    {
        $menu  = $this->get();
        $menu['items'] = $this->filterItemById($menu['items'], $id);
        $this->storage->write(self::COLLECTION, self::ID, $menu);

        return $menu;
    }

    /**
     * Generate HTML for the navigation menu.
     *
     * @param  string $basePath Base URL path.
     * @return string HTML <nav> block.
     */
    public function toHtml(string $basePath = '/'): string
    {
        $menu = $this->get();

        if (empty($menu['items'])) {
            return '';
        }

        $html = "<nav class=\"klytos-nav\" role=\"navigation\">\n";
        $html .= "  <ul class=\"klytos-menu\">\n";
        $html .= $this->renderItems($menu['items'], $basePath, 2);
        $html .= "  </ul>\n";
        $html .= "</nav>";

        return $html;
    }

    /**
     * Normalize an array of menu items (assign IDs, validate).
     */
    private function normalizeItems(array $items): array
    {
        return array_map([$this, 'normalizeItem'], $items);
    }

    /**
     * Normalize a single menu item.
     */
    private function normalizeItem(array $item): array
    {
        $normalized = [
            'id'       => $item['id'] ?? Helpers::randomHex(8),
            'label'    => $item['label'] ?? '',
            'url'      => $item['url'] ?? '#',
            'target'   => $item['target'] ?? '_self',
            'icon'     => $item['icon'] ?? '',
            'order'    => (int) ($item['order'] ?? 0),
            'children' => [],
        ];

        if (!empty($item['children']) && is_array($item['children'])) {
            $normalized['children'] = $this->normalizeItems($item['children']);
        }

        return $normalized;
    }

    /**
     * Recursively filter out an item by ID.
     */
    private function filterItemById(array $items, string $id): array
    {
        $result = [];

        foreach ($items as $item) {
            if (($item['id'] ?? '') === $id) {
                continue;
            }

            if (!empty($item['children'])) {
                $item['children'] = $this->filterItemById($item['children'], $id);
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * Render menu items as HTML list items.
     */
    private function renderItems(array $items, string $basePath, int $indent): string
    {
        $pad  = str_repeat('  ', $indent);
        $html = '';

        // Sort by order
        usort($items, fn($a, $b) => ($a['order'] ?? 0) - ($b['order'] ?? 0));

        foreach ($items as $item) {
            $label  = htmlspecialchars($item['label'] ?? '', ENT_QUOTES, 'UTF-8');
            $url    = $item['url'] ?? '#';
            $target = $item['target'] ?? '_self';

            // Make relative URLs absolute from base path
            if ($url !== '#' && !str_starts_with($url, 'http') && !str_starts_with($url, '//')) {
                $url = rtrim($basePath, '/') . '/' . ltrim($url, '/');
            }

            $targetAttr = ($target === '_blank') ? ' target="_blank" rel="noopener noreferrer"' : '';

            $hasChildren = !empty($item['children']);

            $html .= "{$pad}<li class=\"klytos-menu-item" . ($hasChildren ? ' has-children' : '') . "\">\n";
            $html .= "{$pad}  <a href=\"{$url}\"{$targetAttr}>{$label}</a>\n";

            if ($hasChildren) {
                $html .= "{$pad}  <ul class=\"klytos-submenu\">\n";
                $html .= $this->renderItems($item['children'], $basePath, $indent + 2);
                $html .= "{$pad}  </ul>\n";
            }

            $html .= "{$pad}</li>\n";
        }

        return $html;
    }
}
