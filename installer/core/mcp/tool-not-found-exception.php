<?php

/**
 * Klytos — MCP tool not found (Sprint 2, slice 3 / NEW-30, D-050).
 *
 * Thrown by ToolRegistry::call() when a tool name reached the dispatcher but no
 * handler produced a result: it is not in the register table AND no
 * mcp.handle_tool listener handled it. With NEW-30, exists() admits a name that
 * is declared in the capability map, so a mapped-but-unhandled entry (a typo, an
 * orphaned declaration) can now reach call() — this is the signal a transport
 * uses to answer "Unknown tool" rather than leaking a 500.
 *
 * It is a TYPED exception so `server.php` catches EXACTLY this case and not every
 * RuntimeException `call()` might raise — a plain RuntimeException from a future
 * handler must surface as a real error, never be reclassified as "Unknown tool"
 * (the code-reviewer's slice-3 note). It extends \RuntimeException so any
 * existing broad `catch` (the AI-chat tool callback) still catches it unchanged.
 * The authorization gate has already run and allowed the call before this can be
 * thrown, so it is never a way past the gate.
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

class ToolNotFoundException extends \RuntimeException
{
}
