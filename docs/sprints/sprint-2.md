# Sprint 2 — MCP tool authorization, and make it provable

- **Planned:** 2026-07-22 (plan mode, approved by the user). Kickoff re-validation ran 2026-07-21
  (session 13) + a freshness re-confirmation 2026-07-22 (this session).
- **Status:** **IN PROGRESS** — planning artifacts first, then slices 1–4.
- **Scope basis:** audit **NEW-02** (172 registered / 169 live MCP tools, zero permission checks),
  triaged to a dedicated sprint by **D-020**. It reuses `klytos_require_permission()`'s intent and the
  ONE matrix (`UserManager::hasPermission()`, S-04) that Sprint 1 built.

## Why this sprint exists

Sprint 1 gated the admin panel; this gates the product's **primary interface**. MCP authentication
proves *who* the caller is and never *what* they may do, so today any application-password holder has
owner-equivalent power over the CMS, including destructive tools. An authorization fix cannot be
demonstrated by reading a diff — Keel's test-point rule requires a command and an output — so the
end-to-end proof is a real JSON-RPC `tools/call` over HTTP, denied for a lower-role credential.

## Re-validation of assumptions (Phase 5 §0 kickoff, step 2)

Verified against source, not against the recorded plan. The recorded one-line plan (D-020,
`authorization.md:217` — "reuse `klytos_require_permission()` at the ToolRegistry enforcement point")
would **deny 100% of MCP traffic** dropped in as-is. Five corrections, each re-confirmed this session
by two Explore agents (evidence folded into **D-046**…**D-049**):

1. **No identity on the MCP path.** `Auth::startSession()` runs only in the admin path (`auth.php:62`,
   from `admin/bootstrap.php:216` + the OAuth consent view). `Router::handleMcp()` (`router.php:123-147`)
   → `mcp/server.php` never starts a session, so `klytos_current_user()` returns null
   (`helpers-global.php:370-376`) and `klytos_has_permission()` denies. **Identity must be built from
   the credential first — a prerequisite slice, not a line in the gate slice.**
2. **Credentials carry no role, and the material is discarded.** OAuth `user` surfaced at
   `oauth-server.php:557`, dropped at `token-auth.php:228`; app-password username never returned and
   structurally owner-pinned (`auth.php:588-592`); **bearer tokens have no user field at all**
   (`auth.php:469-475`).
3. **The choke point funnels both callers, but a filter runs first.** `ToolRegistry::call()`
   (`tool-registry.php:159`) is reached by exactly two callers — MCP HTTP (`server.php:225`) and AI
   chat (`chat-engine.php:309`). `mcp.handle_tool` runs at `:164` and returns before the built-in table
   at `:171`. **Gate ABOVE `:164`** or miss all plugin tools.
4. **`integrity-tools.php` is DEAD** — 34 files on disk, 33 in the loader (`:242-282`); its 3 tools
   never register (silent fall-through at `:284-305`). → 172 registered / **169 live**.
5. **`getAvailableTools()` fail-open confirmed** (`chat-engine.php:409-419`): `if viewer {} elseif
   editor {}` with no `else`; also wrapped in `if (function_exists('klytos_current_user'))` (`:405`).
   The null case IS fail-closed (`$user['role'] ?? 'viewer'`, `:407`) and load-bearing — preserved.

**Refusal shape (verified):** normal dispatch emits with no status arg → **HTTP 200**
(`server.php:177`), so a denial returned as a `JsonRpc::error` array would ship as 200. Each transport
therefore **catches** the exception and emits explicitly (`http_response_code(403) + jsonResponse(…, 403)`,
mirroring the `:103-108` 401 block). `klytos_deny()` is **not** on the MCP path and is not reused.

## Acceptance — this sprint is done when

1. Every registered MCP tool resolves through **one** enforcement point in `ToolRegistry::call()`,
   default-deny (no actor / unmapped tool / role lacks capability), with a **named test asserting the
   refusal** — a JSON-RPC error object + **HTTP 403**, proven to FAIL against the unfixed code.
2. `scripts/keel-verify` **fails the build** when a registered tool name has neither a map entry nor a
   plugin-declared capability (check 10), verified by injecting an unmapped tool and reverting.
3. A `role=viewer` **bearer token** is denied a destructive tool over real HTTP (:8105), and an owner
   credential is allowed — the end-to-end proof.
4. `tools/list` is filtered by the actor's capabilities, and `tools/call` gates independently of it.
5. The full suite is green (not only this sprint's tests), `keel-verify` output pasted, and the
   upgrade path tested **from the real previous version** (installed base is `yes`).
6. The user's own test verdict is recorded.

## Slices

| # | Slice | Closes | Status | Test point result | Notes |
|---|-------|--------|--------|-------------------|-------|
| 1 | MCP actor resolution (credential → `{user_id, role}`; role on records; idempotent boot migration) | prerequisite for NEW-02 | **planned** | — | The novel work. `token-auth.php` surfaces the actor; `auth.php` gives records a role (`createBearerToken()` optional role); migration existing app-pw + bearer → owner, OAuth → stored username's role (D-021 shape). Proven to FAIL against unfixed code; upgrade from real v0.30.1 passes |
| 2 | The gate + capability map + `tools/list` filter + keel-verify check 10 | **NEW-02** (core) | **planned** | — | `installer/core/mcp/tool-capabilities.php` (absent = deny, `mcp.tool_capabilities` filter); `PermissionDeniedException`; gate in `call()` above `:164`; `setActor()`; `listTools()` filter; `server.php` catch→403; `chat-engine` catch→tool error. Check 10 → keel-verify 9→**10** |
| 3 | Coverage completeness | NEW-02 tail; L-007 | **planned** | — | Loader silent fall-through → **hard failure**; wire `integrity-tools.php` in gated; `klytos-forms` (16) + `klytos-importer` (10) declare capabilities via `mcp.tool_capabilities`; `chat-engine` `getAvailableTools()` default-denies unknown roles + closes the `function_exists`/null fail-opens |
| 4 | Reconciliation + D-035 + docs/skills/i18n + count truth | NEW-02 closure; D-035 revisit | **planned** | — | NEW `docs/reference/mcp-authorization.md`; close the `authorization.md:217-221` forward reference; count truth (177 served / 169 live / 3 dead); refusal i18n keys × 20 catalogues; `playground.md` `tools/call` curl + per-role table; 4 skill updates; **D-035 widening of `ai.use` to editor — CONFIRM with the user** |

Full per-slice files, reuse targets and test points are in the approved plan
(`~/.claude/plans/floofy-nibbling-ullman.md`); authoritative per-slice evidence lands in
`docs/05-test-points.md` as each slice closes.

### Slice-by-slice test points (the definition of done per slice)

- **1** — app-pw resolves to owner; `role=viewer` bearer resolves to viewer; unresolvable/absent role
  → null (deny); each **proven to FAIL** against unfixed code; **upgrade tested from real v0.30.1**.
- **2** — viewer denied `klytos_delete_page` (JSON-RPC error + **403** on the wire); owner allowed;
  unmapped tool denied; unknown role denied; `tools/list` for viewer omits destructive tools; keel-verify
  check 10 demonstrably fails on an injected unmapped tool, then restored.
- **3** — a listed file whose register function is absent makes the loader **fail loudly**; the 3
  integrity tools now register and are gated; the two plugins' tools carry capabilities; an unknown
  role gets an empty AI tool list (default-deny). Each proven.
- **4** — INDEX + audit + skills + all 20 catalogues consistent; `keel-verify` + `docs-verifier` clean;
  the `tools/call` curl in `playground.md` runs; `ai.use` widening confirmed and its test updated.

## Explicitly out of scope (named, so it is not mistaken for oversight)

- **Per-role application passwords.** App passwords are structurally owner-pinned (`auth.php:588-592`);
  minting one at a lower role is authentication work bound to the **NEW-11** slice. The sprint's
  end-to-end proof uses a **bearer token**, the one credential mintable at a lower role today.
- **NEW-11 authentication** (only `config['admin_user']` can log in). Unchanged this sprint.
- **NEW-03** (`Hooks::doAction()` by-reference), **NEW-05** (vendor-ai CVEs, D-029), **NEW-15** (DNS
  rebinding), **NEW-17…NEW-28**. Each carries its own trigger.
- **NEW-09's one-line fix is FORBIDDEN** (D-036).

## Risks carried into this sprint

1. **Default-deny denies real MCP traffic if identity is not built first.** This is why slice 1 is a
   prerequisite and not a line in slice 2. Mitigated by proving actor resolution end to end (app-pw →
   owner, bearer → its role) before the gate lands.
2. **The gate is real but its power-reducing effect is latent today** (L-014): every credential that
   exists resolves to owner, so only a `role=viewer` bearer token exercises a genuine denial. Stated
   plainly in the reference doc rather than shipping a green test point over a latent capability.
3. **The MCP rate limit is 60/min per identity.** A 4-role × N-tool HTTP matrix will trip it; batched
   or filtered via `addTemporaryFilter` in the tests.
4. **The `mcp.handle_tool` / `mcp.tools_list` filters are plugin-trust boundaries.** A plugin can
   already handle or list its own tools; the map's `mcp.tool_capabilities` filter can weaken a shipped
   capability exactly as `admin.gate_map` can (D-032) — but it cannot open a hole by omission, since an
   absent map entry denies. Stated in the reference doc.
5. **Never run the web installer or `cli.php build` in-tree** (NEW-04). Held; documented in
   `docs/playground.md`.

## Close-out — (filled at sprint close)

| Requirement | Status |
|---|---|
| Full suite green (every test, not only this sprint's) | — |
| `keel-verify` output pasted (now 10 checks) | — |
| Upgrade tested from the REAL previous version | — |
| Lint baselines held | — |
| `code-reviewer` + `security-auditor` per slice, on a finished diff (L-015) | — |
| `docs-verifier` over everything the sprint touched | — |
| Playground-QA fresh-context pass | — |
| Numbered try-it script handed to the user, debug log ON | — |
| **User's recorded verdict** | — |
| `PROGRESS.md` / `lessons-learned.md` / `token-ledger.md` updated | — |
| Continuation prompt produced unprompted | — |
| Finished docs archived to `docs/old/sprint-2/` (or "nothing qualified", stated) | — |
