<?php

/**
 * Klytos — MCP authorization refusal (Sprint 2, slice 2 / audit NEW-02, D-046).
 *
 * Thrown by ToolRegistry::call() when the MCP authorization gate refuses a tool
 * call: no actor resolved for the request, the tool is not in the capability
 * map, or the caller's role lacks the tool's capability. It is a TYPED exception
 * rather than a bare RuntimeException so each transport can catch exactly the
 * authorization refusal and shape it for its own protocol — a JSON-RPC error
 * object with HTTP 403 on the MCP server, a model-visible tool error in AI chat —
 * without also catching the unrelated RuntimeExceptions call() may raise.
 *
 * The message carries the internal reason (role, capability) for the audit log;
 * transports build their own client-facing message rather than echoing it, so
 * the capability map is not disclosed to the caller.
 *
 * @package Klytos
 * @since   0.32.0
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

class PermissionDeniedException extends \RuntimeException
{
    /**
     * @param string      $toolName The tool whose call was refused.
     * @param string|null $role     The actor's role, or null when none resolved.
     * @param string      $reason   The internal reason, for the audit log only.
     */
    public function __construct(
        private string $toolName,
        private ?string $role,
        string $reason
    ) {
        parent::__construct( "MCP tool '{$toolName}' denied: {$reason}" );
    }

    /**
     * The tool whose call was refused.
     *
     * @return string
     */
    public function getToolName(): string
    {
        return $this->toolName;
    }

    /**
     * The actor's role at the time of refusal, or null when no usable role
     * resolved (no actor, or a credential whose user record is gone — NEW-08).
     *
     * @return string|null
     */
    public function getRole(): ?string
    {
        return $this->role;
    }
}
