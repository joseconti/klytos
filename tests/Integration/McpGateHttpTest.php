<?php

/**
 * Klytos CMS — the MCP authorization gate over real HTTP (Sprint 2, slice 2 /
 * audit NEW-02, D-046). This is the sprint's headline proof: a genuinely
 * reduced credential — a role=viewer BEARER token, the one credential mintable
 * below owner today (L-014) — is refused a destructive tool by a JSON-RPC error
 * object with HTTP 403 on the wire, and an owner credential is allowed the same
 * tool.
 *
 * The refusal SHAPE is the property under test, not observable in process: the
 * transport must set the status explicitly, because the normal MCP dispatch
 * emits with no status arg and defaults to HTTP 200 — a denial merely returned
 * as an error array would ship as 200 (server.php). So the assertions read the
 * status AND the JSON-RPC body: a viewer's tools/call returns 403 with an error
 * object and no result, which is exactly what the UNGATED code did not do (it
 * ran the tool and returned 200 with a result).
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Tests\AdminHttpTestCase;

final class McpGateHttpTest extends AdminHttpTestCase
{
    /** A destructive tool: pages.delete, held only by owner/admin. */
    private const DESTRUCTIVE_TOOL = 'klytos_delete_page';

    /** A read tool: pages.view, held by every role including viewer. */
    private const READ_TOOL = 'klytos_get_page';

    /**
     * Its own port: 8099–8104 are taken by the other HTTP test classes, and a
     * shared port would make the squatter check in the base class fire on
     * whichever class ran second.
     */
    protected static function serverPort(): int
    {
        return 8105;
    }

    /**
     * A role=viewer bearer token is refused a destructive tool with a JSON-RPC
     * error object and HTTP 403 — the end-to-end proof. Against the ungated code
     * this same call ran klytos_delete_page and returned 200 with a result.
     */
    public function testViewerBearerIsDeniedADestructiveToolWith403(): void
    {
        $token    = $this->auth()->createBearerToken( 'gate-test-viewer', 'viewer' )['token'];
        $response = $this->mcpCall( $token, 'tools/call', [
            'name'      => self::DESTRUCTIVE_TOOL,
            'arguments' => [ 'slug' => 'index' ],
        ] );

        self::assertSame( 403, $response['status'], 'the refusal must ship as HTTP 403 on the wire' );
        self::assertIsArray( $response['json'] );
        self::assertArrayHasKey( 'error', $response['json'], 'the body must be a JSON-RPC error object' );
        self::assertArrayNotHasKey( 'result', $response['json'] );
        self::assertSame( -32000, $response['json']['error']['code'] ?? null );
        self::assertSame( 1, $response['json']['id'] ?? null, 'the id correlation must be kept' );
    }

    /**
     * An owner bearer token is allowed the very tool the viewer was refused: the
     * gate distinguishes by role, not by tool. The slug does not exist, so the
     * call is side-effect-free; the point is that it reaches a JSON-RPC success
     * (200 with a result), not the 403 refusal.
     */
    public function testOwnerBearerIsAllowedTheSameTool(): void
    {
        $token    = $this->auth()->createBearerToken( 'gate-test-owner', 'owner' )['token'];
        $response = $this->mcpCall( $token, 'tools/call', [
            'name'      => self::DESTRUCTIVE_TOOL,
            'arguments' => [ 'slug' => 'klytos-gate-test-does-not-exist' ],
        ] );

        self::assertSame( 200, $response['status'], 'the owner must pass the gate' );
        self::assertIsArray( $response['json'] );
        self::assertArrayHasKey( 'result', $response['json'] );
        self::assertArrayNotHasKey( 'error', $response['json'] );
    }

    /**
     * A bearer token stamped with a role the matrix does not know holds nothing,
     * so it is refused even a read tool — the fail-closed direction (D-047).
     */
    public function testUnknownRoleBearerIsDeniedWith403(): void
    {
        $token    = $this->auth()->createBearerToken( 'gate-test-unknown', 'superadmin' )['token'];
        $response = $this->mcpCall( $token, 'tools/call', [
            'name'      => self::READ_TOOL,
            'arguments' => [ 'slug' => 'index' ],
        ] );

        self::assertSame( 403, $response['status'] );
        self::assertArrayHasKey( 'error', $response['json'] );
    }

    /**
     * tools/list for a viewer omits destructive tools and keeps the reads —
     * both directions, so a filter that dropped everything or nothing would
     * fail. tools/call still gates independently (proved above), so this is a
     * courtesy, not the control.
     */
    public function testToolsListForViewerOmitsDestructiveTools(): void
    {
        $token    = $this->auth()->createBearerToken( 'gate-test-viewer-list', 'viewer' )['token'];
        $response = $this->mcpCall( $token, 'tools/list' );

        self::assertSame( 200, $response['status'] );

        $names = array_column( $response['json']['result']['tools'] ?? [], 'name' );

        self::assertContains( self::READ_TOOL, $names, 'a viewer should still see read tools' );
        self::assertNotContains( self::DESTRUCTIVE_TOOL, $names, 'a viewer must not be advertised destructive tools' );
    }

    /**
     * NEW-30: a filter-injected tool — x402 (core, registered through
     * mcp.tools_list / mcp.handle_tool, not the loader) — is now CALLABLE over
     * the HTTP transport. Before this slice, server.php rejected it with a
     * JSON-RPC "Unknown tool" (invalidParams) BEFORE the gate, because exists()
     * consulted only the register-populated table. An owner reaches a JSON-RPC
     * success; the un-fixed code returned an error object for the same call.
     */
    public function testFilterInjectedToolIsCallableOverHttpForOwner(): void
    {
        $token    = $this->auth()->createBearerToken( 'new30-owner', 'owner' )['token'];
        $response = $this->mcpCall( $token, 'tools/call', [
            'name'      => 'klytos_x402_get_config',
            'arguments' => [],
        ] );

        self::assertSame( 200, $response['status'], 'a filter-injected tool must be callable over HTTP (NEW-30)' );
        self::assertArrayHasKey( 'result', $response['json'] );
        self::assertArrayNotHasKey( 'error', $response['json'] );
    }

    /**
     * The same filter-injected tool is still GATED over HTTP: a viewer holds
     * neither x402 capability, so it is refused with 403 — the gate runs on the
     * call path exactly as for a loader-registered tool.
     */
    public function testFilterInjectedToolIsGatedOverHttpForViewer(): void
    {
        $token    = $this->auth()->createBearerToken( 'new30-viewer', 'viewer' )['token'];
        $response = $this->mcpCall( $token, 'tools/call', [
            'name'      => 'klytos_x402_get_config',
            'arguments' => [],
        ] );

        self::assertSame( 403, $response['status'], 'a filter-injected tool must still be gated over HTTP' );
        self::assertArrayHasKey( 'error', $response['json'] );
    }

    /**
     * A shipped plugin's tool (klytos-forms, active in the playground) is
     * likewise callable over HTTP for an owner and gated for a lower role — the
     * plugin's mcp.tool_capabilities declaration reaching the gate end to end,
     * over the wire.
     */
    public function testPluginToolIsCallableAndGatedOverHttp(): void
    {
        $owner = $this->auth()->createBearerToken( 'new30-forms-owner', 'owner' )['token'];
        $ok    = $this->mcpCall( $owner, 'tools/call', [
            'name'      => 'klytos_forms_list',
            'arguments' => [],
        ] );
        self::assertSame( 200, $ok['status'], 'owner must be able to call a plugin tool over HTTP' );
        self::assertArrayHasKey( 'result', $ok['json'] );

        $editor  = $this->auth()->createBearerToken( 'new30-forms-editor', 'editor' )['token'];
        $denied  = $this->mcpCall( $editor, 'tools/call', [
            'name'      => 'klytos_forms_create',
            'arguments' => [ 'title' => 'gate probe' ],
        ] );
        self::assertSame( 403, $denied['status'], 'an editor lacks forms.manage — denied over HTTP' );
        self::assertArrayHasKey( 'error', $denied['json'] );
    }

    /**
     * POST a JSON-RPC request to the MCP endpoint with a Bearer token.
     *
     * The base harness speaks admin sessions (cookie + CSRF); MCP speaks neither
     * — it authenticates by the Authorization header — so this sends the request
     * directly rather than through post()/postJson(). The server lifecycle,
     * squatter check and teardown are still inherited from AdminHttpTestCase; a
     * second copy of those would fork the three defects L-008 records.
     *
     * @param  string|null         $token  Raw bearer token, or null for anonymous.
     * @param  string              $method JSON-RPC method.
     * @param  array<string,mixed> $params JSON-RPC params.
     * @return array{status:int, json:mixed}
     */
    private function mcpCall( ?string $token, string $method, array $params = [] ): array
    {
        $url    = sprintf( 'http://%s:%d/installer/mcp', self::HOST, static::serverPort() );
        $handle = curl_init( $url );

        $headers = [ 'Content-Type: application/json' ];
        if ( $token !== null ) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $body = [ 'jsonrpc' => '2.0', 'id' => 1, 'method' => $method ];
        if ( $params !== [] ) {
            $body['params'] = $params;
        }

        curl_setopt_array( $handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode( $body ),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
        ] );

        $raw = curl_exec( $handle );

        if ( $raw === false ) {
            self::fail( 'MCP request failed: ' . curl_error( $handle ) );
        }

        $status = (int) curl_getinfo( $handle, CURLINFO_HTTP_CODE );
        curl_close( $handle );

        return [ 'status' => $status, 'json' => json_decode( (string) $raw, true ) ];
    }
}
