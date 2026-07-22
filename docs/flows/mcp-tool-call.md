# Flow — MCP tool call

> Created in Sprint 1 slice 9. **Sprint 2's subject.** The authorization step described here as
> missing was built in Sprint 2 slice 2 (audit **NEW-02**, decisions **D-046**/**D-048**). Read
> `docs/reference/mcp-authorization.md` for the full gate.

## Actors
An MCP client (Claude, or any MCP-capable agent) holding a bearer token, OAuth access token, or
application password, talking JSON-RPC to `/<admin-dir>/mcp`.

## Happy path
1. Client authenticates (bearer / OAuth / app password). `TokenAuth::validate()` resolves an actor
   `{user_id, role}` from the credential (slice 1), and `server.php` sets it on the registry
   (`ToolRegistry::setActor()`).
2. Client calls `tools/list` → the registry returns the tool set **filtered by the actor's
   capabilities** (slice 2): the advertised surface equals the usable one.
3. Client calls `tools/call` with a tool name and arguments.
4. `ToolRegistry::call()` runs the **authorization gate first** (`denialReason()`), above the
   `mcp.handle_tool` filter so plugin tools are covered too, then dispatches to the handler.
5. The handler performs the operation and returns a result.

## Failure / recovery branches

| Branch | Behaviour |
|---|---|
| No credentials | **401** — verified in every session's freshness check |
| Bad credentials | 401 |
| Rate limited | `MCP\RateLimiter` refuses, persistent and IP-keyed |
| **Caller lacks the capability the tool needs** | **Denied (slice 2).** The gate throws `PermissionDeniedException`; the MCP server emits a JSON-RPC error object with **HTTP 403** the client can act on, and the AI chat surfaces a model-visible tool error. The capability comes from `klytos_mcp_tool_capabilities()`; the decision from `UserManager::hasPermission()` (the ONE matrix). |
| Caller's role is unrecognized | **Denied.** An unknown role holds nothing in the matrix, so the gate refuses it every capability-gated tool. (`chat-engine`'s advertised-list fail-open is neutralized because `call()` gates regardless; the list filter itself is tightened in slice 3.) |
| Tool not in the capability map | **Denied by default** — a registered core tool without an entry fails `keel-verify` check 10, so in production this only fires for a plugin tool with no declared capability. |

## The recovery branch that matters
The caller adapts to the JSON-RPC 403: a lower-role credential is told, per tool, that it is not
authorized, and can fall back to tools it may call (which `tools/list` already scoped to its role).
MCP authentication proves *who* the caller is; the gate now proves *what they may do*, so an
application-password or bearer holder no longer has owner-equivalent power by default.

## Related
`docs/reference/mcp-authorization.md` · `docs/reference/authorization.md` · D-020 · D-046 · D-048 ·
D-035 (`ai.use` revisited at Sprint 2 close) · NEW-02
