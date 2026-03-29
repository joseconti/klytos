<?php
/**
 * Klytos — MCP Tool Registry
 * Registers and dispatches MCP tools.
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

namespace Klytos\Core\MCP;

use Klytos\Core\App;

class ToolRegistry
{
    private array $tools = [];
    private App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Register a tool.
     *
     * @param string   $name        Tool name (e.g. 'klytos_create_page').
     * @param array    $schema      JSON Schema for the tool's input.
     * @param callable $handler     Function that executes the tool.
     * @param array    $annotations Tool annotations (readOnlyHint, destructiveHint, etc.)
     */
    /**
     * Register a tool.
     *
     * @param string   $name        Tool name (e.g. 'klytos_create_page').
     * @param string   $description Human-readable description.
     * @param array    $schema      JSON Schema properties (assoc array).
     * @param callable $handler     Function that executes the tool.
     * @param array    $annotations Tool annotations (readOnlyHint, destructiveHint, etc.)
     * @param array    $required    List of required property names.
     */
    public function register(
        string $name,
        string $description,
        array $schema,
        callable $handler,
        array $annotations = [],
        array $required = []
    ): void {
        $inputSchema = [
            'type'       => 'object',
            'properties' => empty( $schema ) ? new \stdClass() : $schema,
        ];

        // Add required fields if specified.
        if ( !empty( $required ) ) {
            $inputSchema['required'] = $required;
        }

        $this->tools[$name] = [
            'name'        => $name,
            'description' => $description,
            'inputSchema' => $inputSchema,
            'handler'     => $handler,
            'annotations' => $annotations,
        ];
    }

    /**
     * List all registered tools (for tools/list response).
     *
     * @return array MCP tools list format.
     */
    public function listTools(): array
    {
        $list = [];

        foreach ( $this->tools as $tool ) {
            $entry = [
                'name'        => $tool['name'],
                'description' => $tool['description'],
                'inputSchema' => self::sanitizeSchema( $tool['inputSchema'] ),
            ];

            if ( !empty( $tool['annotations'] ) ) {
                $entry['annotations'] = (object) $tool['annotations'];
            }

            $list[] = $entry;
        }

        // Hook: allow plugins to add their own MCP tools to the list.
        $list = \Klytos\Core\Hooks::applyFilters( 'mcp.tools_list', $list );

        // Sanitize plugin-added tools too.
        foreach ( $list as &$entry ) {
            if ( isset( $entry['inputSchema'] ) ) {
                $entry['inputSchema'] = self::sanitizeSchema( $entry['inputSchema'] );
            }
        }
        unset( $entry );

        return $list;
    }

    /**
     * Recursively sanitize a JSON Schema for MCP protocol compliance.
     *
     * Ensures all associative arrays (dictionaries) become stdClass so that
     * json_encode produces {} instead of []. This is critical because PHP
     * serializes empty arrays as [] (JSON array), but MCP requires {} (JSON object)
     * for properties, items schemas, etc.
     *
     * @param  mixed $value The schema value to sanitize.
     * @return mixed Sanitized value safe for json_encode.
     */
    private static function sanitizeSchema( mixed $value ): mixed
    {
        if ( !is_array( $value ) ) {
            return $value;
        }

        // Empty array → empty object.
        if ( empty( $value ) ) {
            return new \stdClass();
        }

        // Sequential (indexed) arrays stay as arrays (e.g. "required": ["slug", "title"]).
        if ( array_is_list( $value ) ) {
            return array_map( [self::class, 'sanitizeSchema'], $value );
        }

        // Associative arrays → stdClass (JSON object).
        $obj = new \stdClass();
        foreach ( $value as $key => $val ) {
            $obj->$key = self::sanitizeSchema( $val );
        }
        return $obj;
    }

    /**
     * Call a registered tool.
     *
     * @param  string $name   Tool name.
     * @param  array  $params Tool input parameters.
     * @return array  MCP tool result.
     * @throws \RuntimeException If tool not found.
     */
    public function call(string $name, array $params): array
    {
        // Hook: allow plugins to handle MCP tool calls.
        // If a plugin handles the tool, it returns a non-null result.
        // This allows plugins to register tools dynamically.
        $pluginResult = \Klytos\Core\Hooks::applyFilters('mcp.handle_tool', null, $name, $params);
        if ($pluginResult !== null) {
            // Plugin handled this tool — apply response filter and return.
            $pluginResult = \Klytos\Core\Hooks::applyFilters('mcp.tool_response', $pluginResult, $name);
            return $pluginResult;
        }

        if (!isset($this->tools[$name])) {
            throw new \RuntimeException("Tool not found: {$name}");
        }

        // Fire action: a tool is being called (for logging/auditing).
        \Klytos\Core\Hooks::doAction('mcp.tool_called', $name, $params);

        $handler = $this->tools[$name]['handler'];

        try {
            $result = $handler($params, $this->app);

            $response = [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    ],
                ],
                'isError' => false,
            ];

            // Hook: allow plugins to modify tool responses before sending.
            $response = \Klytos\Core\Hooks::applyFilters('mcp.tool_response', $response, $name);

            return $response;
        } catch (\InvalidArgumentException $e) {
            // User-facing validation errors — safe to expose
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: ' . $e->getMessage(),
                    ],
                ],
                'isError' => true,
            ];
        } catch (\Exception $e) {
            // Internal errors — log but don't expose details
            error_log('Klytos tool error [' . $name . ']: ' . $e->getMessage());

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: An internal error occurred while executing the tool.',
                    ],
                ],
                'isError' => true,
            ];
        }
    }

    /**
     * Check if a tool exists.
     *
     * @param  string $name
     * @return bool
     */
    public function exists(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Register all built-in Klytos tools.
     */
    public function registerAllTools(): void
    {
        $toolsDir = $this->app->getCorePath() . '/mcp/tools';

        $toolFiles = [
            // v1.0 core tools
            'page-tools.php',
            'theme-tools.php',
            'menu-tools.php',
            'site-tools.php',
            'asset-tools.php',
            'template-tools.php',
            'build-tools.php',
            'ai-image-tools.php',
            // v2.0 tools
            'user-tools.php',
            'task-tools.php',
            'version-tools.php',
            'block-tools.php',
            'page-template-tools.php',
            'analytics-tools.php',
            'webhook-tools.php',
            'plugin-tools.php',
            'guide-tools.php',
            'post-type-tools.php',
        ];

        foreach ($toolFiles as $file) {
            $path = $toolsDir . '/' . $file;
            if (file_exists($path)) {
                require_once $path;

                $suffix = $this->fileToFunctionSuffix($file);

                // Try namespaced function first (v1.0 tools).
                $namespacedFunc = 'Klytos\\Core\\MCP\\Tools\\register' . $suffix;
                if (function_exists($namespacedFunc)) {
                    $namespacedFunc($this);
                    continue;
                }

                // Try global function (v2.0 tools that accept $app).
                $globalFunc = 'register' . $suffix;
                if (function_exists($globalFunc)) {
                    $globalFunc($this, $this->app);
                    continue;
                }
            }
        }
    }

    /**
     * Convert filename to function suffix.
     * e.g. 'page-tools.php' → 'PageTools'
     */
    private function fileToFunctionSuffix(string $filename): string
    {
        $name = str_replace(['-tools.php', '.php'], '', $filename);
        $parts = explode('-', $name);
        return implode('', array_map('ucfirst', $parts)) . 'Tools';
    }
}
