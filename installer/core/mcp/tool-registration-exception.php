<?php

/**
 * Klytos — MCP tool loader failure (Sprint 2, slice 3 / decision D-049, L-007).
 *
 * Thrown by ToolRegistry::registerAllTools() when a file listed in the loader's
 * $toolFiles is missing, or is present but registers no tools (neither its
 * namespaced nor its global register function is defined after the file is
 * required). Before this, the loader skipped such a file by SILENT fall-through —
 * which is exactly how integrity-tools.php stayed dead and unnoticed: the loader
 * could not tell "this file registers nothing" from "this file is fine". An
 * unfinished or misnamed registration is EVIDENCE (L-007), not clutter, so it
 * fails the boot/CI loudly with a named message rather than shipping a
 * quietly-missing tool.
 *
 * It is a TYPED exception, like PermissionDeniedException, so a caller can catch
 * exactly a loader failure — a test asserting the fail-loud behaviour, for
 * instance — without also catching the unrelated RuntimeExceptions boot may raise.
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

class ToolRegistrationException extends \RuntimeException
{
}
