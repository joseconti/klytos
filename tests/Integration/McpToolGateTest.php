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
}
