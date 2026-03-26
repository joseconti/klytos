<?php
/**
 * Klytos — MCP Template Tools
 * HTML template management via MCP.
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

function registerTemplateTools(ToolRegistry $registry): void
{
    $registry->register(
        'klytos_set_template',
        'Create or update an HTML template. Use {{variables}} for dynamic content.',
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

            // Load existing templates
            try {
                $templates = $storage->read('templates.json.enc');
            } catch (\RuntimeException $e) {
                $templates = ['templates' => []];
            }

            $templates['templates'][$name] = [
                'name'        => $name,
                'html'        => $params['html'] ?? '',
                'description' => $params['description'] ?? '',
                'updated_at'  => \Klytos\Core\Helpers::now(),
            ];

            $storage->write('templates.json.enc', $templates);

            return ['success' => true, 'template' => $name];
        },
        ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true],
        ['name', 'html']
    );

    $registry->register(
        'klytos_list_templates',
        'List all available HTML templates (both built-in and custom).',
        [],
        function (array $params, App $app): array {
            // Built-in templates from templates/ directory
            $builtIn = [];
            $templatesDir = $app->getTemplatesPath();
            if (is_dir($templatesDir)) {
                $files = glob($templatesDir . '/*.html') ?: [];
                foreach ($files as $file) {
                    $name = pathinfo($file, PATHINFO_FILENAME);
                    $builtIn[] = [
                        'name'   => $name,
                        'type'   => 'built-in',
                        'source' => 'templates/' . basename($file),
                    ];
                }
            }

            // Custom templates from data
            $custom = [];
            try {
                $data = $app->getStorage()->read('templates.json.enc');
                foreach ($data['templates'] ?? [] as $name => $tpl) {
                    $custom[] = [
                        'name'        => $name,
                        'type'        => 'custom',
                        'description' => $tpl['description'] ?? '',
                        'updated_at'  => $tpl['updated_at'] ?? '',
                    ];
                }
            } catch (\RuntimeException $e) {
                // No custom templates yet
            }

            return ['built_in' => $builtIn, 'custom' => $custom];
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true]
    );

    $registry->register(
        'klytos_get_template',
        'Get the HTML source of a template by name.',
        [
            'name' => ['type' => 'string', 'description' => 'Template name'],
        ],
        function (array $params, App $app): array {
            $name = $params['name'] ?? '';

            // Check built-in first
            $builtInFile = $app->getTemplatesPath() . '/' . $name . '.html';
            if (file_exists($builtInFile)) {
                return [
                    'name'   => $name,
                    'type'   => 'built-in',
                    'html'   => file_get_contents($builtInFile),
                ];
            }

            // Check custom
            try {
                $data = $app->getStorage()->read('templates.json.enc');
                if (isset($data['templates'][$name])) {
                    return [
                        'name' => $name,
                        'type' => 'custom',
                        'html' => $data['templates'][$name]['html'] ?? '',
                        'description' => $data['templates'][$name]['description'] ?? '',
                    ];
                }
            } catch (\RuntimeException $e) {
                // Fall through to error
            }

            throw new \RuntimeException("Template not found: {$name}");
        },
        ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
        ['name']
    );
}
