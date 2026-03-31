<?php
/**
 * Klytos — MCP Template Tools
 * HTML template management via MCP.
 *
 * Covers the full template system:
 * - DB templates (templates.json.enc)
 * - Custom templates (custom-templates/)
 * - Template parts (custom-templates/parts/)
 * - TemplateResolver hierarchy & debugging
 * - Frontend hook assets (klytos-hooks.js, plugins.css)
 *
 * @package Klytos
 * @since   1.0.0
 * @updated 0.12.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\MCP\Tools;

use Klytos\Core\App;
use Klytos\Core\Helpers;
use Klytos\Core\MCP\ToolRegistry;

function registerTemplateTools(ToolRegistry $registry): void
{
    // ─── DB Templates (templates.json.enc) ─────────────────────

    $registry->register(
        'klytos_set_template',
        'Create or update an HTML template stored in the database. Use {{variables}} for dynamic content. For file-based custom templates, use klytos_set_custom_template instead.',
        [
            'name'        => ['type' => 'string', 'description' => 'Template identifier (e.g. "portfolio", "blog")'],
            'html'        => ['type' => 'string', 'description' => 'Full HTML template with {{variables}}'],
            'description' => ['type' => 'string', 'description' => 'What this template is for'],
        ],
        function (array $params, App $app): array {
            $name = $params['name'] ?? '';
            if (empty($name)) {
                throw new \RuntimeException('Template name is required.');
            }

            $storage = $app->getStorage();

            try {
                $templates = $storage->read('config', 'templates');
            } catch (\RuntimeException $e) {
                $templates = ['templates' => []];
            }

            $templates['templates'][$name] = [
                'name'        => $name,
                'html'        => $params['html'] ?? '',
                'description' => $params['description'] ?? '',
                'updated_at'  => Helpers::now(),
            ];

            $storage->write('config', 'templates', $templates);

            // Clear TemplateResolver cache so the new template is picked up.
            $app->getTemplateResolver()->clearCache();

            return ['success' => true, 'template' => $name];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['name', 'html']
    );

    $registry->register(
        'klytos_delete_template',
        'Delete a custom template from the database (templates.json.enc). Cannot delete built-in or file-based templates.',
        [
            'name' => ['type' => 'string', 'description' => 'Template name to delete'],
        ],
        function (array $params, App $app): array {
            $name = $params['name'] ?? '';
            if (empty($name)) {
                throw new \RuntimeException('Template name is required.');
            }

            $storage = $app->getStorage();

            try {
                $templates = $storage->read('config', 'templates');
            } catch (\RuntimeException $e) {
                throw new \RuntimeException("No custom templates exist.");
            }

            if (!isset($templates['templates'][$name])) {
                throw new \RuntimeException("Template not found in database: {$name}");
            }

            unset($templates['templates'][$name]);
            $storage->write('config', 'templates', $templates);
            $app->getTemplateResolver()->clearCache();

            return ['success' => true, 'deleted' => $name];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
        ['name']
    );

    // ─── TemplateResolver: Full Hierarchy ──────────────────────

    $registry->register(
        'klytos_list_templates',
        'List ALL available templates from every source in the 4-level hierarchy: custom-templates/ (user), plugin-registered, database, and core templates/. Shows which source each template comes from.',
        [],
        function (array $params, App $app): array {
            return ['templates' => $app->getTemplateResolver()->listAll()];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_get_template',
        'Get the resolved HTML source of a template by name. Uses the 4-level hierarchy: custom-templates > plugin > database > core.',
        [
            'name' => ['type' => 'string', 'description' => 'Template name (without .html extension)'],
        ],
        function (array $params, App $app): array {
            $name = $params['name'] ?? '';
            if (empty($name)) {
                throw new \RuntimeException('Template name is required.');
            }

            $resolver = $app->getTemplateResolver();
            $html     = $resolver->resolve($name);
            $all      = $resolver->listAll();
            $source   = $all[$name]['source'] ?? 'fallback';

            return [
                'name'   => $name,
                'source' => $source,
                'html'   => $html,
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['name']
    );

    $registry->register(
        'klytos_resolve_template',
        'Debug template resolution: show which template would be used for a page, including the full candidate chain and which level matched.',
        [
            'slug'      => ['type' => 'string', 'description' => 'Page slug (e.g. "camiseta-azul")'],
            'post_type' => ['type' => 'string', 'description' => 'Post type (e.g. "product", "blog"). Default: "page"'],
            'template'  => ['type' => 'string', 'description' => 'Template chosen by user in editor. Default: "default"'],
        ],
        function (array $params, App $app): array {
            $slug     = $params['slug'] ?? '';
            $postType = $params['post_type'] ?? 'page';
            $chosen   = $params['template'] ?? 'default';
            $resolver = $app->getTemplateResolver();
            $all      = $resolver->listAll();

            // Build the candidate chain (same logic as resolveTemplateForPage)
            $candidates = [];
            if ($postType !== 'page') {
                if (!empty($slug)) {
                    $candidates[] = "single-{$postType}-{$slug}";
                }
                $candidates[] = "single-{$postType}";
            }
            if ($chosen !== 'default') {
                $candidates[] = $chosen;
            }
            $candidates[] = 'default';

            // Check which candidate matches
            $resolved = null;
            $chain    = [];
            foreach ($candidates as $name) {
                $exists = isset($all[$name]);
                $chain[] = [
                    'candidate' => $name,
                    'exists'    => $exists,
                    'source'    => $exists ? ($all[$name]['source'] ?? 'unknown') : null,
                ];
                if ($exists && $resolved === null) {
                    $resolved = $name;
                }
            }

            return [
                'resolved_template' => $resolved ?? 'default',
                'resolved_source'   => $all[$resolved ?? 'default']['source'] ?? 'core',
                'candidate_chain'   => $chain,
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['slug']
    );

    // ─── Custom Templates (custom-templates/) ──────────────────

    $registry->register(
        'klytos_set_custom_template',
        'Create or update a file-based custom template in custom-templates/. These survive updates and have the highest priority in the resolution hierarchy.',
        [
            'name'        => ['type' => 'string', 'description' => 'Template name (without .html). E.g. "default", "single-product"'],
            'html'        => ['type' => 'string', 'description' => 'Full HTML template content'],
        ],
        function (array $params, App $app): array {
            $name = $params['name'] ?? '';
            $html = $params['html'] ?? '';
            if (empty($name) || empty($html)) {
                throw new \RuntimeException('Both name and html are required.');
            }

            // Sanitize name (only alphanumeric, hyphens, underscores)
            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
                throw new \RuntimeException('Template name can only contain letters, numbers, hyphens, and underscores.');
            }

            $dir  = $app->getRootPath() . '/custom-templates';
            $file = $dir . '/' . $name . '.html';

            Helpers::ensureWritableDir($dir);
            file_put_contents($file, $html, LOCK_EX);

            $app->getTemplateResolver()->clearCache();

            return ['success' => true, 'template' => $name, 'file' => 'custom-templates/' . $name . '.html'];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['name', 'html']
    );

    $registry->register(
        'klytos_get_custom_template',
        'Get the HTML content of a specific custom template file from custom-templates/.',
        [
            'name' => ['type' => 'string', 'description' => 'Template name (without .html)'],
        ],
        function (array $params, App $app): array {
            $name = $params['name'] ?? '';
            $file = $app->getRootPath() . '/custom-templates/' . $name . '.html';

            if (!file_exists($file)) {
                throw new \RuntimeException("Custom template not found: {$name}");
            }

            return [
                'name' => $name,
                'html' => file_get_contents($file),
                'file' => 'custom-templates/' . $name . '.html',
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['name']
    );

    $registry->register(
        'klytos_delete_custom_template',
        'Delete a custom template file from custom-templates/. The system will fall back to the next level in the hierarchy.',
        [
            'name' => ['type' => 'string', 'description' => 'Template name to delete (without .html)'],
        ],
        function (array $params, App $app): array {
            $name = $params['name'] ?? '';
            $file = $app->getRootPath() . '/custom-templates/' . $name . '.html';

            if (!file_exists($file)) {
                throw new \RuntimeException("Custom template not found: {$name}");
            }

            unlink($file);
            $app->getTemplateResolver()->clearCache();

            return ['success' => true, 'deleted' => $name];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
        ['name']
    );

    $registry->register(
        'klytos_list_custom_templates',
        'List all user custom templates in custom-templates/ and custom-templates/parts/.',
        [],
        function (array $params, App $app): array {
            $rootPath  = $app->getRootPath();
            $templates = [];
            $parts     = [];

            // Custom templates
            $dir = $rootPath . '/custom-templates';
            if (is_dir($dir)) {
                foreach (glob($dir . '/*.html') as $file) {
                    $templates[] = basename($file, '.html');
                }
            }

            // Custom parts
            $partsDir = $dir . '/parts';
            if (is_dir($partsDir)) {
                foreach (glob($partsDir . '/*.html') as $file) {
                    $parts[] = basename($file, '.html');
                }
            }

            return ['custom_templates' => $templates, 'custom_parts' => $parts];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    // ─── Template Parts ────────────────────────────────────────

    $registry->register(
        'klytos_list_template_parts',
        'List all available template parts from all sources (core templates/parts/ and custom-templates/parts/).',
        [],
        function (array $params, App $app): array {
            $rootPath  = $app->getRootPath();
            $result    = [];

            // Core parts
            $coreDir = $app->getTemplatesPath() . '/parts';
            if (is_dir($coreDir)) {
                foreach (glob($coreDir . '/*.html') as $file) {
                    $name = basename($file, '.html');
                    $result[$name] = ['name' => $name, 'source' => 'core'];
                }
            }

            // Custom parts (override core)
            $customDir = $rootPath . '/custom-templates/parts';
            if (is_dir($customDir)) {
                foreach (glob($customDir . '/*.html') as $file) {
                    $name = basename($file, '.html');
                    $result[$name] = ['name' => $name, 'source' => 'custom'];
                }
            }

            return ['parts' => array_values($result)];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_get_template_part',
        'Get the resolved HTML content of a template part. Uses hierarchy: custom-templates/parts/ > plugin filter > templates/parts/.',
        [
            'name' => ['type' => 'string', 'description' => 'Part name (e.g. "header", "footer", "head", "scripts")'],
        ],
        function (array $params, App $app): array {
            $name = $params['name'] ?? '';
            if (empty($name)) {
                throw new \RuntimeException('Part name is required.');
            }

            $html = $app->getTemplateResolver()->resolvePart($name);

            if ($html === null) {
                throw new \RuntimeException("Template part not found: {$name}");
            }

            // Determine source
            $customFile = $app->getRootPath() . '/custom-templates/parts/' . $name . '.html';
            $coreFile   = $app->getTemplatesPath() . '/parts/' . $name . '.html';

            if (file_exists($customFile)) {
                $source = 'custom';
            } elseif (file_exists($coreFile)) {
                $source = 'core';
            } else {
                $source = 'plugin';
            }

            return ['name' => $name, 'source' => $source, 'html' => $html];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['name']
    );

    $registry->register(
        'klytos_set_custom_template_part',
        'Create or update a custom template part in custom-templates/parts/. Overrides the core part with the same name.',
        [
            'name' => ['type' => 'string', 'description' => 'Part name (e.g. "header", "footer"). Without .html extension.'],
            'html' => ['type' => 'string', 'description' => 'HTML content of the part. Can include {{variables}}.'],
        ],
        function (array $params, App $app): array {
            $name = $params['name'] ?? '';
            $html = $params['html'] ?? '';
            if (empty($name) || empty($html)) {
                throw new \RuntimeException('Both name and html are required.');
            }

            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
                throw new \RuntimeException('Part name can only contain letters, numbers, hyphens, and underscores.');
            }

            $dir  = $app->getRootPath() . '/custom-templates/parts';
            $file = $dir . '/' . $name . '.html';

            Helpers::ensureWritableDir($dir);
            file_put_contents($file, $html, LOCK_EX);

            $app->getTemplateResolver()->clearCache();

            return ['success' => true, 'part' => $name, 'file' => 'custom-templates/parts/' . $name . '.html'];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['name', 'html']
    );

    $registry->register(
        'klytos_delete_custom_template_part',
        'Delete a custom template part. The system will fall back to the core part or plugin-provided part.',
        [
            'name' => ['type' => 'string', 'description' => 'Part name to delete (without .html)'],
        ],
        function (array $params, App $app): array {
            $name = $params['name'] ?? '';
            $file = $app->getRootPath() . '/custom-templates/parts/' . $name . '.html';

            if (!file_exists($file)) {
                throw new \RuntimeException("Custom part not found: {$name}");
            }

            unlink($file);
            $app->getTemplateResolver()->clearCache();

            return ['success' => true, 'deleted' => $name];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
        ['name']
    );

    // ─── Frontend Assets (klytos-hooks.js, plugins.css) ────────

    $registry->register(
        'klytos_rebuild_plugin_assets',
        'Regenerate klytos-hooks.js and plugins.css without rebuilding the entire site. Use after manual plugin asset changes.',
        [],
        function (array $params, App $app): array {
            $buildEngine = new \Klytos\Core\BuildEngine($app);
            $buildEngine->buildHooksJs();
            $buildEngine->buildPluginsCss();

            $jsVersion  = klytos_get_option('klytos_hooks_js_version', '');
            $cssVersion = klytos_get_option('klytos_plugins_css_version', '');

            return [
                'success'            => true,
                'hooks_js_version'   => $jsVersion,
                'plugins_css_version' => $cssVersion,
            ];
        },
        ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_get_plugin_assets_status',
        'Get the current status of generated plugin assets (klytos-hooks.js and plugins.css): version hashes, file existence, and sizes.',
        [],
        function (array $params, App $app): array {
            $publicPath = $app->getPublicPath();
            $jsFile     = $publicPath . '/assets/js/klytos-hooks.js';
            $cssFile    = $publicPath . '/assets/css/plugins.css';

            return [
                'hooks_js' => [
                    'exists'  => file_exists($jsFile),
                    'version' => klytos_get_option('klytos_hooks_js_version', ''),
                    'size'    => file_exists($jsFile) ? filesize($jsFile) : 0,
                ],
                'plugins_css' => [
                    'exists'  => file_exists($cssFile),
                    'version' => klytos_get_option('klytos_plugins_css_version', ''),
                    'size'    => file_exists($cssFile) ? filesize($cssFile) : 0,
                ],
            ];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );
}
