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
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
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
        'Create or update a file-based custom page template. Use this when the 4 built-in templates (default, landing, blog-post, blank) do not fit the layout you need. The HTML must be a COMPLETE HTML document with {{variables}} for dynamic content. MUST include {{page_content}} where the page body goes. MUST include <link rel="stylesheet" href="{{base_path}}assets/css/style.css"> for theme CSS. Use {{header_html}} and {{footer_html}} for shared chrome. Use {{seo_meta_tags}}, {{google_fonts_html}}, {{plugin_css_link}}, {{hooks_js_script}} etc. for full functionality. For Custom Post Types, name the template "single-{post_type}" (e.g. "single-product"). Read klytos_get_guide("site-builder") Phase 6 Step 4 for complete variable list and examples. Templates created here have the HIGHEST priority in the resolution hierarchy and survive updates.',
        [
            'name'        => ['type' => 'string', 'description' => 'Template name (without .html). E.g. "blog-home", "single-product", "docs-sidebar". For CPTs use "single-{post_type}".'],
            'html'        => ['type' => 'string', 'description' => 'Full HTML document with {{variables}}. MUST include {{page_content}}. See klytos_get_guide("site-builder") Phase 6 Step 4 for all variables and examples.'],
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
        'DEPRECATED: prefer klytos_list_parts (unified parts system). List template parts from file sources only (core templates/parts/ and custom-templates/parts/); does NOT include parts stored in storage.',
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
        'DEPRECATED: prefer klytos_get_part (unified parts system, includes slots and data). Get the resolved HTML content of a template part. Uses hierarchy: custom-templates/parts/ > plugin filter > storage > templates/parts/.',
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
        'Create or update a custom template part as a FILE in custom-templates/parts/. NOTE: prefer klytos_set_part (unified parts system) for normal editing — file parts created here take precedence over stored parts and shadow them, so reserve this tool for user-requested file overrides that must survive everything. Allows COMPLETELY FREE HTML/CSS design for headers, footers, and any shared site element. The HTML you provide here is used as-is across ALL pages that reference this part via {{klytos_part:NAME}} in their template. Use cases: (1) Custom header with ANY layout — centered logo + menu below, logo left + menu right + CTA button, sticky transparent header, mega-menu, hamburger menu, etc. (2) Custom footer with ANY structure — multi-column with links, minimal single-line, newsletter signup, social icons grid, etc. (3) Any other reusable site element — top bar, announcement banner, sidebar, etc. The HTML can include: inline styles, <style> blocks, SVG icons, CSS Grid, Flexbox, gradients, animations, media queries — anything valid HTML/CSS. Use {{variables}} for dynamic content: {{site_name}}, {{menu_html}}, {{base_path}}, {{site_url}}. This overrides the core part with the same name. Changed once, it updates ALL pages on the next build.',
        [
            'name' => ['type' => 'string', 'description' => 'Part name (e.g. "header", "footer", "top-bar", "announcement"). Without .html extension. Standard parts: "header" and "footer" are referenced by default templates.'],
            'html' => ['type' => 'string', 'description' => 'Complete HTML/CSS content with total design freedom. Can include <style> blocks for scoped CSS, inline styles, SVG icons, and {{variables}} like {{site_name}}, {{menu_html}}, {{base_path}}. Example: a centered header would use flexbox with flex-direction:column and align-items:center.'],
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
