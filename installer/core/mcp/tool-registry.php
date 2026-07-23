<?php

/**
 * Klytos — MCP Tool Registry
 * Registers and dispatches MCP tools.
 *
 * @package Klytos
 * @since   1.0.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core\MCP;

use Klytos\Core\App;

class ToolRegistry
{
    private array $tools = [];
    private App $app;

    /**
     * The role of the credential/session that owns THIS request, or null when
     * none has been resolved. Load-bearing: the authorization gate (D-046) reads
     * it. Null — no actor set, or an actor with no usable role — means deny.
     *
     * @var string|null
     */
    private ?string $actorRole = null;

    /**
     * The acting user id, for audit only. Null for bearer tokens, which name no
     * user (D-047). The gate never decides on this — only on $actorRole.
     *
     * @var int|string|null
     */
    private int|string|null $actorUserId = null;

    public function __construct(App $app)
    {
        $this->app = $app;

        // The tool→capability map (D-048). A plain function file, not a class,
        // so the autoloader cannot reach it — require it here, once per registry.
        require_once __DIR__ . '/tool-capabilities.php';
    }

    /**
     * Set the actor that owns this request, from the resolved MCP credential
     * (server.php, after TokenAuth) or the admin session (chat-engine).
     *
     * The registry is built fresh per request (server.php:40) and the chat
     * engine's is refreshed on every processMessage(), so this carries identity
     * WITHOUT a global. call() and listTools() read the actor set here; a
     * registry whose actor was never set denies every tool by default.
     *
     * @param  int|string|null $userId Acting user id (null for bearer tokens).
     * @param  string|null      $role   Acting role; an empty or null role is
     *                                   normalized to null and denies.
     * @return void
     */
    public function setActor( int|string|null $userId, ?string $role ): void
    {
        $this->actorUserId = $userId;
        $this->actorRole   = ( $role !== null && $role !== '' ) ? $role : null;
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
        $list = klytos_apply_filters( 'mcp.tools_list', $list );

        // Sanitize plugin-added tools too.
        foreach ( $list as &$entry ) {
            if ( isset( $entry['inputSchema'] ) ) {
                $entry['inputSchema'] = self::sanitizeSchema( $entry['inputSchema'] );
            }
        }
        unset( $entry );

        // Filter by the actor's capabilities (D-048), AFTER the mcp.tools_list
        // plugin filter above so plugin-added tools are filtered too, and BEFORE
        // the return. The advertised surface equals the usable one, so an agent
        // does not plan around tools it will be refused. This is a courtesy, not
        // the control: call() gates independently (denialReason), so a tool the
        // list omitted is still refused if named directly. Hiding is not access
        // control; the gate is.
        $list = array_values( array_filter( $list, function ( array $entry ): bool {
            $name = $entry['name'] ?? '';
            return is_string( $name ) && $name !== '' && $this->denialReason( $name ) === null;
        } ) );

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
     * Decide whether the current actor may call a tool, and why not.
     *
     * The single source of truth for the authorization decision, used by both
     * call() (which throws on a non-null reason) and listTools() (which drops
     * tools whose reason is non-null). It never decides here: the capability
     * question goes to UserManager::hasPermission() — the ONE matrix (S-04) —
     * because the MCP path has no session, so klytos_require_permission(), which
     * resolves identity from the session, cannot be reused (D-046).
     *
     * @param  string $name Tool name.
     * @return string|null  The refusal reason (for the log), or null if allowed.
     */
    private function denialReason(string $name): ?string
    {
        // 1. A usable actor is required. No actor, or an actor whose role could
        //    not be resolved (a credential whose user record is gone — NEW-08 —
        //    or an unstamped token), denies: the fail-closed direction D-021 and
        //    D-047 set for identity on this codebase.
        if ($this->actorRole === null) {
            return 'no actor resolved for this MCP request';
        }

        // 2. The tool must be mapped. An absent entry denies by default — the
        //    S-07 inversion applied to the MCP surface (D-048). keel-verify
        //    check 10 fails the build if a registered tool has no entry, so this
        //    branch is reached only by a plugin tool with no declared capability.
        $map = klytos_mcp_tool_capabilities();
        if (!array_key_exists($name, $map)) {
            return "tool '{$name}' is not in the capability map";
        }

        // 3. A null capability is the audited "no capability required" exception
        //    (mirrors admin-gate.php): the actor is authenticated with a usable
        //    role and the tool needs nothing more.
        $capability = $map[$name];
        if ($capability === null) {
            return null;
        }

        // 4. The matrix decides. An unknown role holds nothing, so it is denied
        //    every capability — the fail-open case NEW-02 required be closed.
        if (!$this->app->getUserManager()->hasPermission(['role' => $this->actorRole], $capability)) {
            return "role '{$this->actorRole}' lacks the required capability '{$capability}'";
        }

        return null;
    }

    /**
     * Call a registered tool.
     *
     * @param  string $name   Tool name.
     * @param  array  $params Tool input parameters.
     * @return array  MCP tool result.
     * @throws PermissionDeniedException If the actor may not call the tool.
     * @throws \RuntimeException         If tool not found.
     */
    public function call(string $name, array $params): array
    {
        // Authorization gate (D-046). ABOVE the mcp.handle_tool filter below, so
        // it covers plugin-handled tools too — a gate placed after that filter
        // would leave every plugin tool ungated, the by-omission failure S-07
        // exists to close. Default-deny: no actor, an unmapped tool, or a role
        // that lacks the tool's capability all throw. tools/list filtering
        // (listTools) is a courtesy on top of this, never a substitute — a tool
        // named directly is gated here regardless of what tools/list returned.
        $denial = $this->denialReason($name);
        if ($denial !== null) {
            // Audit hook: log/alert/count refusals. Fires before the throw and
            // cannot reverse it (there is no return value the gate reads).
            klytos_do_action('mcp.access_denied', $name, $this->actorRole, $denial);

            throw new PermissionDeniedException($name, $this->actorRole, $denial);
        }

        // Hook: allow plugins to handle MCP tool calls.
        // If a plugin handles the tool, it returns a non-null result.
        // This allows plugins to register tools dynamically.
        $pluginResult = klytos_apply_filters('mcp.handle_tool', null, $name, $params);
        if ($pluginResult !== null) {
            // Plugin handled this tool — apply response filter and return.
            $pluginResult = klytos_apply_filters('mcp.tool_response', $pluginResult, $name);
            return $pluginResult;
        }

        if (!isset($this->tools[$name])) {
            // A name that passed the gate and exists() (it is registered OR in
            // the capability map — NEW-30) but no mcp.handle_tool listener
            // produced a result: a mapped-but-unhandled entry. Typed so the
            // transport can answer "Unknown tool" for THIS case only, never
            // masking an unrelated RuntimeException from a handler (D-050).
            throw new ToolNotFoundException("Tool not found: {$name}");
        }

        // Fire action: a tool is being called (for logging/auditing).
        klytos_do_action('mcp.tool_called', $name, $params);

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
            $response = klytos_apply_filters('mcp.tool_response', $response, $name);

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
     * Check if a tool name is known to the registry.
     *
     * A tool is "known" when it is registered through the loader OR when it has a
     * declared capability in the map — because a filter-injected tool (x402, and
     * the shipped MCP plugins) never enters $this->tools; it is served entirely
     * through mcp.tools_list / mcp.handle_tool, and its declared capability in
     * klytos_mcp_tool_capabilities() is what marks it a first-class tool.
     *
     * This is what makes filter-injected tools callable over the HTTP transport
     * (NEW-30): server.php's handleToolsCall() rejects a name that does not exist
     * BEFORE reaching call(), so a register-only exists() left every x402/plugin
     * tool advertised by tools/list yet answered "Unknown tool" on tools/call.
     * Widening exists() to the declared set lets those calls reach call(), which
     * gates them (denialReason) and dispatches them via mcp.handle_tool exactly as
     * the AI-chat path already did. A name that is neither registered nor declared
     * is still unknown — an undeclared plugin tool fails closed, as default-deny
     * intends. This is the only caller-visible effect: server.php:exists() is the
     * method's sole caller repo-wide.
     *
     * @param  string $name Tool name.
     * @return bool
     */
    public function exists(string $name): bool
    {
        if (isset($this->tools[$name])) {
            return true;
        }

        return array_key_exists($name, klytos_mcp_tool_capabilities());
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
            'part-tools.php',
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
            'consent-tools.php',
            'scheduler-tools.php',
            'plugin-tools.php',
            'guide-tools.php',
            'post-type-tools.php',
            'post-status-tools.php',
            'custom-field-tools.php',
            'option-tools.php',
            // v0.9.0 AI chat tools.
            'ai-tools.php',
            'translation-tools.php',
            // Site builder.
            'site-builder-tools.php',
            // v0.18.0 — WordPress parity features.
            'export-tools.php',
            'comment-tools.php',
            'site-health-tools.php',
            // v0.26.0 — Phase 2+3 features.
            'maintenance-tools.php',
            'bulk-tools.php',
            'shortcode-tools.php',
            // File integrity verification (was on disk but never listed, hence
            // dead — D-049 wires it in, gated in tool-capabilities.php).
            'integrity-tools.php',
        ];

        foreach ($toolFiles as $file) {
            $this->registerToolFile($toolsDir, $file);
        }
    }

    /**
     * Register the tools declared by ONE loader file, failing loudly (D-049,
     * L-007) if it declares none.
     *
     * A listed file that is missing, or present but defining neither its
     * namespaced nor its global register function, is an unfinished or misnamed
     * registration — evidence, not something to skip. The old silent
     * fall-through here is exactly what kept integrity-tools.php dead and
     * unnoticed for its whole life: the loader could not tell "this file
     * registers nothing" from "this file is fine". Throwing surfaces it at
     * boot/CI, the S-07 default-deny lesson applied to the loader — omission is
     * never silently tolerated.
     *
     * Extracted from registerAllTools() so the fail-loud contract is exercised
     * per file by a test, without mutating the hardcoded $toolFiles list that
     * keel-verify check 10 parses.
     *
     * @param  string $toolsDir Directory holding the tool files.
     * @param  string $file     File name within $toolsDir (e.g. 'page-tools.php').
     * @return void
     * @throws ToolRegistrationException When the file is missing or registers no tools.
     */
    public function registerToolFile(string $toolsDir, string $file): void
    {
        $path = $toolsDir . '/' . $file;

        if (!file_exists($path)) {
            throw new ToolRegistrationException(
                "MCP tool file '{$file}' is listed in the loader but does not exist at {$path}."
            );
        }

        require_once $path;

        $suffix = $this->fileToFunctionSuffix($file);

        // Try namespaced function first (v1.0 tools).
        $namespacedFunc = 'Klytos\\Core\\MCP\\Tools\\register' . $suffix;
        if (function_exists($namespacedFunc)) {
            $namespacedFunc($this);
            return;
        }

        // Try global function (v2.0 tools that accept $app).
        $globalFunc = 'register' . $suffix;
        if (function_exists($globalFunc)) {
            $globalFunc($this, $this->app);
            return;
        }

        // The file was required but defined neither register function.
        throw new ToolRegistrationException(
            "MCP tool file '{$file}' registers no tools: neither {$namespacedFunc}() nor "
            . "{$globalFunc}() is defined after requiring it. A listed file must register its "
            . 'tools, or be removed from the loader list.'
        );
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
