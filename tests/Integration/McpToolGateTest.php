<?php

/**
 * Klytos CMS — the MCP authorization gate, in process (Sprint 2, slice 2 /
 * audit NEW-02, D-046 / D-048).
 *
 * The end-to-end proof over real HTTP is McpGateHttpTest; this asserts the gate
 * LOGIC directly on a ToolRegistry, which is where the branches live and where a
 * removed map entry or an unknown role can be exercised without a running
 * server. Authorization is not unit-testable here (it spans App, UserManager and
 * the registry at once — the reason IntegrationTestCase exists), so it is
 * asserted against the real booted application.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Integration;

use Klytos\Core\MCP\PermissionDeniedException;
use Klytos\Core\MCP\ToolNotFoundException;
use Klytos\Core\MCP\ToolRegistry;
use Klytos\Tests\IntegrationTestCase;

final class McpToolGateTest extends IntegrationTestCase
{
    /** A destructive tool: pages.delete, held only by owner/admin. */
    private const DESTRUCTIVE_TOOL = 'klytos_delete_page';

    /** A read tool: pages.view, held by every role including viewer. */
    private const READ_TOOL = 'klytos_get_page';

    /**
     * A registry with every core tool registered, ready for setActor().
     *
     * Built fresh per test rather than reusing the App's shared one so a
     * setActor() from one test cannot bleed into another — the registry carries
     * the actor, and these tests deliberately drive it through many actors.
     */
    private function registry(): ToolRegistry
    {
        $registry = new ToolRegistry( $this->app );
        $registry->registerAllTools();

        return $registry;
    }

    /**
     * No actor at all denies every tool — the fail-closed default. A registry
     * whose setActor() was never called must refuse, not wave the call through.
     */
    public function testAnUnsetActorDeniesEveryTool(): void
    {
        $registry = $this->registry();

        $this->expectException( PermissionDeniedException::class );
        $registry->call( self::READ_TOOL, [ 'slug' => 'index' ] );
    }

    /**
     * A viewer is refused a destructive tool, and the exception carries the tool
     * and role for the audit log.
     */
    public function testViewerIsDeniedADestructiveTool(): void
    {
        $registry = $this->registry();
        $registry->setActor( 1, 'viewer' );

        try {
            $registry->call( self::DESTRUCTIVE_TOOL, [ 'slug' => 'index' ] );
            self::fail( 'A viewer was allowed ' . self::DESTRUCTIVE_TOOL . ' — the gate did not fire.' );
        } catch ( PermissionDeniedException $e ) {
            self::assertSame( self::DESTRUCTIVE_TOOL, $e->getToolName() );
            self::assertSame( 'viewer', $e->getRole() );
        }
    }

    /**
     * A viewer IS allowed a read tool — the positive control, so the gate is not
     * merely refusing everything (L-008). The call reaches the tool and returns
     * a result envelope rather than throwing the gate.
     */
    public function testViewerIsAllowedAReadTool(): void
    {
        $registry = $this->registry();
        $registry->setActor( 1, 'viewer' );

        $result = $registry->call( self::READ_TOOL, [ 'slug' => 'index' ] );

        self::assertArrayHasKey( 'content', $result );
    }

    /**
     * An owner is allowed the very tool a viewer was refused — the gate
     * distinguishes by role, not by tool. A slug that does not exist keeps the
     * call side-effect-free; the point is only that the gate let it through.
     */
    public function testOwnerIsAllowedADestructiveTool(): void
    {
        $registry = $this->registry();
        $registry->setActor( 1, 'owner' );

        $result = $registry->call( self::DESTRUCTIVE_TOOL, [ 'slug' => 'klytos-gate-test-does-not-exist' ] );

        self::assertIsArray( $result );
        self::assertArrayHasKey( 'content', $result );
    }

    /**
     * An unrecognized role holds nothing in the matrix, so it is denied even a
     * read tool — the fail-open case NEW-02 required be closed (D-047).
     */
    public function testAnUnknownRoleIsDeniedEvenAReadTool(): void
    {
        $registry = $this->registry();
        $registry->setActor( 1, 'superadmin' );

        $this->expectException( PermissionDeniedException::class );
        $registry->call( self::READ_TOOL, [ 'slug' => 'index' ] );
    }

    /**
     * A registered tool with no map entry is denied — even for the owner. This
     * is the default-deny inversion (D-048): omission refuses. The entry is
     * removed through the mcp.tool_capabilities filter, which is exactly how the
     * gate reads the map, so it exercises the real branch.
     */
    public function testAnUnmappedToolIsDeniedEvenForOwner(): void
    {
        $this->addTemporaryFilter( 'mcp.tool_capabilities', static function ( array $map ): array {
            unset( $map[ self::READ_TOOL ] );
            return $map;
        } );

        $registry = $this->registry();
        $registry->setActor( 1, 'owner' );

        $this->expectException( PermissionDeniedException::class );
        $registry->call( self::READ_TOOL, [ 'slug' => 'index' ] );
    }

    /**
     * tools/list is filtered by the actor: a viewer sees the read tool and not
     * the destructive one. Both directions asserted, so a filter that dropped
     * everything (or nothing) would fail.
     */
    public function testToolsListIsFilteredForViewer(): void
    {
        $registry = $this->registry();
        $registry->setActor( 1, 'viewer' );

        $names = array_column( $registry->listTools(), 'name' );

        self::assertContains( self::READ_TOOL, $names, 'viewer should see the read tool' );
        self::assertNotContains( self::DESTRUCTIVE_TOOL, $names, 'viewer must not see the destructive tool' );
    }

    /**
     * The owner sees the destructive tool the viewer did not — the list filter's
     * positive control.
     */
    public function testToolsListIncludesDestructiveToolsForOwner(): void
    {
        $registry = $this->registry();
        $registry->setActor( 1, 'owner' );

        $names = array_column( $registry->listTools(), 'name' );

        self::assertContains( self::DESTRUCTIVE_TOOL, $names );
    }

    /**
     * With no actor, tools/list is empty — hiding everything is the same
     * fail-closed default the call gate applies.
     */
    public function testToolsListIsEmptyWithNoActor(): void
    {
        $registry = $this->registry();

        self::assertSame( [], $registry->listTools() );
    }

    /**
     * x402 registers its 8 tools through the mcp.tools_list / mcp.handle_tool
     * filters (it is core, loaded at boot), NOT the tool loader, and declares
     * their capabilities through mcp.tool_capabilities. Without that declaration
     * the gate's default-deny would make every x402 tool unusable by every role,
     * including owner. This proves the declaration reaches the gate: an editor
     * holds x402.view (a read) but not x402.manage (a write).
     */
    public function testX402ToolsAreGatedByTheirDeclaredCapability(): void
    {
        $registry = $this->registry();
        $registry->setActor( 1, 'editor' );

        // x402.view — editor is allowed; the x402 handler runs and returns a result.
        $read = $registry->call( 'klytos_x402_get_config', [] );
        self::assertIsArray( $read );

        // x402.manage — editor is denied before the handler runs.
        try {
            $registry->call( 'klytos_x402_set_config', [ 'default_price_usd' => '0.01' ] );
            self::fail( 'An editor was allowed klytos_x402_set_config (x402.manage).' );
        } catch ( PermissionDeniedException $e ) {
            self::assertSame( 'klytos_x402_set_config', $e->getToolName() );
        }
    }

    /**
     * A viewer holds neither x402 capability, so it is denied even an x402 read —
     * and the owner's list advertises the x402 tools that would otherwise have
     * vanished under default-deny (the regression this guards against).
     */
    public function testX402IsDeniedToViewerAndAdvertisedToOwner(): void
    {
        $registry = $this->registry();

        $registry->setActor( 1, 'viewer' );
        try {
            $registry->call( 'klytos_x402_get_config', [] );
            self::fail( 'A viewer was allowed an x402 tool.' );
        } catch ( PermissionDeniedException $e ) {
            self::assertSame( 'klytos_x402_get_config', $e->getToolName() );
        }

        $registry->setActor( 1, 'owner' );
        $names = array_column( $registry->listTools(), 'name' );
        self::assertContains( 'klytos_x402_get_config', $names, 'owner must still see x402 tools' );
        self::assertContains( 'klytos_x402_set_config', $names );
    }

    /**
     * integrity-tools.php is wired into the loader this slice (D-049) and mapped
     * at site.configure (owner/admin). Its 3 tools were DEAD before — never
     * registered, never mapped — so all three assertions below fail against the
     * unfixed code: the map has no entry, and an owner's list does not contain
     * them.
     */
    public function testIntegrityToolsAreGatedAtSiteConfigure(): void
    {
        $map = klytos_mcp_tool_capabilities();
        self::assertSame( 'site.configure', $map['klytos_integrity_check'] ?? null );
        self::assertSame( 'site.configure', $map['klytos_integrity_status'] ?? null );
        self::assertSame( 'site.configure', $map['klytos_integrity_check_plugin'] ?? null );

        $registry = $this->registry();

        // An editor lacks site.configure — denied before the checker runs.
        $registry->setActor( 1, 'editor' );
        try {
            $registry->call( 'klytos_integrity_check', [] );
            self::fail( 'An editor was allowed klytos_integrity_check (site.configure).' );
        } catch ( PermissionDeniedException $e ) {
            self::assertSame( 'klytos_integrity_check', $e->getToolName() );
        }

        // The owner holds it: the tools are advertised. Membership is the
        // positive control — calling verify() would hit the network.
        $registry->setActor( 1, 'owner' );
        $names = array_column( $registry->listTools(), 'name' );
        self::assertContains( 'klytos_integrity_check', $names );
        self::assertContains( 'klytos_integrity_status', $names );
        self::assertContains( 'klytos_integrity_check_plugin', $names );
    }

    /**
     * klytos-forms declares its 16 MCP tools at forms.manage (owner/admin)
     * through mcp.tool_capabilities (it is filter-injected, not loader-registered,
     * so keel-verify check 10 does not cover it — this test does). An editor,
     * lacking forms.manage, is denied; the owner sees the tool advertised. Both
     * the map value and the owner membership fail against the un-declared code.
     */
    public function testFormsPluginToolsAreGatedByFormsManage(): void
    {
        $map = klytos_mcp_tool_capabilities();
        self::assertSame( 'forms.manage', $map['klytos_forms_create'] ?? null, 'the forms plugin must declare forms.manage' );
        self::assertSame( 'forms.manage', $map['klytos_forms_stats'] ?? null );

        $registry = $this->registry();

        $registry->setActor( 1, 'editor' );
        try {
            $registry->call( 'klytos_forms_create', [ 'title' => 'gate probe' ] );
            self::fail( 'An editor was allowed klytos_forms_create (forms.manage).' );
        } catch ( PermissionDeniedException $e ) {
            self::assertSame( 'klytos_forms_create', $e->getToolName() );
        }

        $registry->setActor( 1, 'owner' );
        $names = array_column( $registry->listTools(), 'name' );
        self::assertContains( 'klytos_forms_create', $names, 'owner must see the forms plugin tools' );
    }

    /**
     * klytos-importer declares its 10 MCP tools at site.configure (owner/admin) —
     * a whole-site migration is an operations privilege, the mirror of
     * klytos_export_site. An editor is denied; the owner sees them advertised.
     */
    public function testImporterPluginToolsAreGatedBySiteConfigure(): void
    {
        $map = klytos_mcp_tool_capabilities();
        self::assertSame( 'site.configure', $map['klytos_import_execute_batch'] ?? null, 'the importer plugin must declare site.configure' );
        self::assertSame( 'site.configure', $map['klytos_import_analyze_style'] ?? null );

        $registry = $this->registry();

        $registry->setActor( 1, 'editor' );
        try {
            $registry->call( 'klytos_import_execute_batch', [ 'session_id' => 'x', 'pages' => [] ] );
            self::fail( 'An editor was allowed klytos_import_execute_batch (site.configure).' );
        } catch ( PermissionDeniedException $e ) {
            self::assertSame( 'klytos_import_execute_batch', $e->getToolName() );
        }

        $registry->setActor( 1, 'owner' );
        $names = array_column( $registry->listTools(), 'name' );
        self::assertContains( 'klytos_import_execute_batch', $names );
    }

    /**
     * A tool that is mapped (so the gate passes for a holder) but that nothing
     * registers or handles raises the typed `ToolNotFoundException` — NOT a plain
     * error and NOT a permission denial. This is the mapped-but-unhandled case
     * NEW-30 introduced (a typo or orphaned declaration reaching `call()` now
     * that `exists()` admits mapped names); the transport answers "Unknown tool"
     * on exactly this, without masking an unrelated `RuntimeException` (D-050).
     * The gate runs first, so this is reached only AFTER the owner is authorised.
     */
    public function testAMappedButUnhandledToolThrowsToolNotFound(): void
    {
        $this->addTemporaryFilter( 'mcp.tool_capabilities', static function ( array $map ): array {
            $map['klytos_ghost_unhandled'] = 'pages.view';
            return $map;
        } );

        $registry = $this->registry();
        $registry->setActor( 1, 'owner' );

        $this->expectException( ToolNotFoundException::class );
        $registry->call( 'klytos_ghost_unhandled', [] );
    }
}
