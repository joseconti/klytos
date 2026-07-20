# Flow — MCP tool call

> Created in Sprint 1 slice 9. **Sprint 2's subject.** Read `docs/decisions.md` **D-020** and audit
> **NEW-02** before working this flow: today it has no authorization step at all.

## Actors
An MCP client (Claude, or any MCP-capable agent) holding an application password, talking JSON-RPC
to `/<admin-dir>/mcp`.

## Happy path
1. Client authenticates over HTTP Basic with an application password (`Auth::createAppPassword()`).
2. Client calls `tools/list` → the registry returns the tool set (**206** registered tools).
3. Client calls `tools/call` with a tool name and arguments.
4. `ToolRegistry` dispatches to the tool's handler.
5. The handler performs the operation and returns a result.

## Failure / recovery branches

| Branch | Current behaviour | Correct behaviour |
|---|---|---|
| No credentials | **401** — verified in every session's freshness check | unchanged |
| Bad credentials | 401 | unchanged |
| Rate limited | `MCP\RateLimiter` refuses, persistent and IP-keyed | unchanged |
| **Caller lacks the capability the tool needs** | **THERE IS NO SUCH CHECK.** `klytos_has_permission` appears **zero** times across all 34 files and 172 tools in `installer/core/mcp/tools/` (NEW-02) | deny via `klytos_require_permission()`, the helper slice 4 built for exactly this, returning a JSON-RPC error the client can act on |
| Caller's role is unrecognized | `ai/chat-engine.php:401-421` filters the tool list only for exactly `viewer`/`editor`, so an unknown role falls through **unfiltered** — fail-open | default-deny an unknown role |

## The recovery branch that matters
**There is none, and that is the finding.** MCP authentication proves *who* the caller is and never
*what they may do*, so any application-password holder currently has owner-equivalent power over the
CMS. Sprint 1 gated the admin panel; this flow is why `docs/PROGRESS.md` says in plain words that
when Sprint 1 closes, **the admin is gated and the product's primary interface is not**.

## Related
`docs/reference/authorization.md` · D-020 · D-035 (`ai.use` excludes `editor` while this is open) ·
NEW-02
