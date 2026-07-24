# Flow — MCP tool call

> Created in Sprint 1 slice 9. **Sprint 2's subject, and Sprint 2 is closed.** The authorization
> step described here as missing was built across slices 1–4 (audit **NEW-02**, now CLOSED;
> decisions **D-046**…**D-051**). Read `docs/reference/mcp-authorization.md` for the full gate,
> the tool counts, and the checklist for adding a tool.

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
| Caller's role is unrecognized | **Denied.** An unknown role holds nothing in the matrix, so the gate refuses it every capability-gated tool. Slice 3 also default-denies an unknown role in `chat-engine`'s advisory tool list (previously it fell through to the full list), so the advertised list is now honest as well as the gate. |
| Tool is filter-injected (x402, a shipped plugin) over HTTP | **Callable and gated (slice 3, NEW-30).** `exists()` treats a tool declared in the capability map as known, so `server.php` no longer answers "Unknown tool" before the gate; `call()` gates and dispatches it via `mcp.handle_tool`. |
| A listed loader file registers nothing | **Boot/CI fails loudly (slice 3, D-049).** `registerToolFile()` throws `ToolRegistrationException` rather than skipping a dead file silently. |
| Tool not in the capability map | **Denied by default** — a registered core tool without an entry fails `keel-verify` check 10, so in production this only fires for a plugin tool with no declared capability. |

## The recovery branch that matters
The caller adapts to the JSON-RPC 403: a lower-role credential is told, per tool, that it is not
authorized — in the site's language (slice 4: `mcp.permission_denied`, 20 locales), naming the tool
and the fix but never the role or capability — and can fall back to tools it may call (which
`tools/list` already scoped to its role). MCP authentication proves *who* the caller is; the gate
now proves *what they may do*, so an application-password or bearer holder no longer has
owner-equivalent power by default.

## The same flow from the AI chat
`chat-engine` is `call()`'s only other caller, with the actor taken from the session. Every step
above is identical from step 4 on, which is what let `ai.use` widen to `editor` at Sprint 2 close
(**D-051**): the chat can no longer amplify a role, because it executes each tool with the caller's
own one. The chat's advertised tool list is advisory; the gate is the control.

## Related
`docs/reference/mcp-authorization.md` · `docs/reference/authorization.md` · `docs/playground.md`
(the runnable per-role table) · D-020 · D-046 · D-048 · D-050 · D-051 (`ai.use` widened, superseding
D-035) · NEW-02 (closed) · NEW-30 (closed)
