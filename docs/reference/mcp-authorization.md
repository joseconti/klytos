# MCP authorization — Klytos CMS

> Introduced by Sprint 2 (audit **NEW-02**, decision **D-020**). This is the reference for how the
> MCP interface decides *what a caller may do*, not merely *who they are*.
>
> **Sprint 2 is landing in slices.** This document grows with them:
> - **Slice 1 (done):** identity — resolving an **actor** `{user_id, role}` from the credential, plus
>   the credential-role model and its boot migration.
> - **Slice 2 (done):** the gate itself — the central capability map, the default-deny enforcement
>   point in `ToolRegistry::call()`, the JSON-RPC refusal, and the `tools/list` filter. *Everything
>   through "tools/list is filtered too" below.*
> - **Slice 3:** coverage — the loader fails loudly, `integrity-tools.php` is wired in, plugins declare
>   their tools' capabilities, and the AI-chat tool list default-denies unknown roles.
> - **Slice 4:** the full "Adding a new MCP tool — the checklist", the count reconciliation, and the
>   forward reference from `docs/reference/authorization.md:217` is closed here.

## Why identity has to be rebuilt on the MCP path

The admin panel and the MCP endpoint authenticate completely differently, and this is the fact the
whole sprint turns on:

- The **admin path** starts a PHP session (`Auth::startSession()`, called from `admin/bootstrap.php`
  and the OAuth consent view). `klytos_current_user()` reads that session.
- The **MCP path** (`index.php` → `Router::handleMcp()` → `mcp/server.php`) **never starts a
  session**. So on every `tools/call`, `klytos_current_user()` returns **null** and
  `klytos_has_permission()` denies *everything*.

Dropping `klytos_require_permission()` into the MCP dispatch as-is would therefore deny 100% of MCP
traffic. Identity must be built **from the credential** first — which is what slice 1 does.

## The actor

An **actor** is the shape the gate reads:

```php
[ 'user_id' => int|string|null, 'role' => string|null ]
```

`role` is load-bearing: the gate (slice 2) passes it to `UserManager::hasPermission()` — the ONE
matrix (S-04). **A null actor, or a null role inside it, means deny.** `user_id` is for audit; it is
null for bearer tokens, which are not tied to a user.

`TokenAuth::validate()` resolves the actor from whichever credential authenticated, and
`TokenAuth::getActor()` surfaces it:

```php
$tokenAuth = new Klytos\Core\MCP\TokenAuth( $app->getAuth(), $app );

if ( $tokenAuth->validate() ) {
    $actor = $tokenAuth->getActor();   // e.g. [ 'user_id' => null, 'role' => 'viewer' ]
    // slice 2: the gate refuses when $actor is null, or when $actor['role'] lacks the tool's capability.
}
```

## Where each credential's role comes from

Klytos has three MCP credential types, and the role is resolved from **whichever place actually holds
the identity** — never duplicated:

| Credential | Carries a username? | Role source |
|---|---|---|
| **Application password** | yes (pinned to the admin user) | the **user record** (`UserManager::getByUsername()`) |
| **OAuth access token** | yes (the token's `user` subject) | the **user record** (`UserManager::getByUsername()`) |
| **Bearer token** | **no** | the **token record itself** (a stamped `role` field) |

This is deliberate (D-047, as amended in slice 1). Application passwords and OAuth tokens already name
a user, so their role follows that user's record — DRY, and forward-compatible with per-user
credentials once **NEW-11** lands (the role will simply follow whatever user the credential names).
Only bearer tokens, which name no user, carry a role on the credential.

**Fail-closed everywhere.** An empty username, a username that no longer resolves to a user, a token
record with no role, or any storage error all resolve to **null** — deny, never a default of `owner`.
That last point matters: a valid credential whose user record has been deleted (a corrupted or
half-migrated install, **NEW-08**) denies rather than escalating.

## Bearer tokens carry a role

`Auth::createBearerToken()` takes an optional role:

```php
// A genuinely reduced credential — the sprint's end-to-end proof of the gate.
$viewer = $app->getAuth()->createBearerToken( 'CI read-only', 'viewer' );
// $viewer['token'] now resolves to [ 'user_id' => null, 'role' => 'viewer' ]

// The default reproduces pre-Sprint-2 behaviour: an owner-equivalent token.
$owner = $app->getAuth()->createBearerToken( 'automation' );
// resolves to [ 'user_id' => null, 'role' => 'owner' ]
```

`Auth::getBearerTokenActor( $rawToken )` reads the stored role back and is the resolver behind the
bearer branch of `TokenAuth::validate()`.

**Honest limit — the residual gap (decision 3, D-047).** A bearer token records no user, so a bearer
token that resolves to `owner` is an **unattributed owner credential**: the audit log can name the
token, not a person. Migration cannot fix this, because the product never attributed bearer tokens to
a user in the first place. Per-user bearer tokens are bound to the **NEW-11** authentication work.

## The installed-base migration

Bearer tokens minted before Sprint 2 carry **no** role. They already operated with owner-equivalent
power, because the MCP tool layer had *no* authorization at all (NEW-02). `Auth::migrateCredentialRoles()`
— run once at boot (`App::boot()` Step 10b-2) and callable directly by tests — stamps every role-less
bearer token with `owner`, **recording what was already true rather than widening anything**.

It is idempotent: it writes only when it actually stamps something, so a migrated store is a no-op,
and it never re-stamps a token that already has a role (a real `viewer` bearer survives the
migration). Application passwords and OAuth tokens are **not** touched — they resolve from their user.

Proven on a **real** upgraded install, not a fixture: `scripts/dev/upgrade-test.sh` mints a bearer
token with the real v0.30.1 code (which stamps no role), upgrades to the working tree, and asserts the
boot migration stamped it `owner`.

## The power-reducing effect is latent today (stated plainly, L-014)

Every credential that exists **today** resolves to `owner`: application passwords are pinned to the
admin user (the owner), and existing bearer/OAuth credentials migrate or resolve to owner. The gate
(slice 2) is real and default-deny, but its power-**reducing** effect only materialises for a
credential minted at a lower role. **Bearer tokens are the one credential mintable below owner today**
without touching NEW-11, so a `role=viewer` bearer token is the sprint's honest end-to-end proof (it
is denied a destructive tool over real HTTP in slice 2). Per-role **application passwords** stay behind
the NEW-11 authentication slice.

## The gate (slice 2)

There is exactly **one** enforcement point for the whole MCP surface:
`ToolRegistry::call()` (`installer/core/mcp/tool-registry.php`), placed **above** the
`mcp.handle_tool` plugin filter so it covers plugin-handled tools as well as the built-in table. A
gate below that filter would leave every plugin tool ungated — the by-omission failure S-07 exists
to close. `call()` is reached by exactly two callers repo-wide (the MCP server and the AI chat
engine), so one gate covers everything.

```
ToolRegistry::call( $name )
        └─ denialReason( $name )              ← the ONE decision, reused by listTools()
                ├─ no usable actor?            → deny
                ├─ tool absent from the map?   → deny  (default-deny, D-048)
                ├─ capability is null?         → allow (audited exception)
                └─ UserManager::hasPermission( [role], capability )   ← the ONE matrix (S-04)
```

The registry has **no session** — the MCP path never starts one — so it cannot reuse
`klytos_require_permission()`, which resolves identity from the session. Instead the actor is set on
the per-request registry (`ToolRegistry::setActor( $userId, $role )`) by whichever transport
authenticated it, and the gate asks `UserManager::hasPermission()` directly with the actor's role.
It **does not** add a second decision point; the matrix decides, the gate only asks.

Default-deny, in three ways, each fail-closed:

- **No actor** — `setActor()` was never called, or the credential resolved to no usable role
  (NEW-08). A registry with no actor refuses every tool.
- **An unmapped tool** — a tool with no entry in the capability map is refused, so a new tool is
  denied until it is mapped deliberately. `scripts/keel-verify` check 10 fails the build when a
  registered core tool has no entry, so this only ever fires for a plugin tool with no declared
  capability.
- **A role that lacks the capability** — including an **unrecognized** role, which holds nothing in
  the matrix and so is denied everything. This is the fail-open that NEW-02 required be closed.

## The capability map (slice 2)

`installer/core/mcp/tool-capabilities.php` holds the tool→capability map behind
`klytos_mcp_tool_capabilities()`, mirroring `admin-gate.php`'s gate map exactly:

| Value | Meaning |
|---|---|
| a capability string | Require it (passed to the matrix). |
| `null` | No capability required — the audited exception list; every `null` carries its reason in a comment. It still requires a usable role. |
| *absent* | **Denied.** |

It covers the **169 live core tools**. Notable choices, recorded so they are not mistaken for
oversights:

- **Reads follow the content flow.** A read a viewer/editor genuinely needs to *create content* —
  `klytos_get_post_type`, `klytos_list_custom_fields`, `klytos_list_post_statuses`, which
  `klytos_create_page`'s own description tells the model to call first — is mapped at `pages.view` so
  an editor can make it. A read that only concerns site administration follows its domain's manage
  capability. Where in doubt, the higher tier: over-restriction fails safe.
- **Destructive-capable tools take the destructive capability.** `klytos_bulk_update_pages` can
  `trash`/`delete` permanently, so it is `pages.delete`, not `pages.edit`. The whole trash lifecycle
  (delete, restore, permanent delete, empty) is `pages.delete`.
- **Tasks match the admin split (S-06).** create/list at `tasks.create`; update/complete at
  `tasks.manage`, because MCP cannot establish task ownership — a bearer token has no user at all —
  so the higher, fail-closed bar is used.
- **Guides are the one `null` exception.** `klytos_list_guides` / `klytos_get_guide` read the
  instructional markdown the AI relies on to operate; no user data, config, or secrets, and no
  mutation, so any authenticated caller with a usable role may read them.

Plugins declare capabilities for their own tools through the **`mcp.tool_capabilities`** filter (the
`admin.gate_map` precedent). Stated honestly: like `admin.gate_map` and `auth.capabilities` this
filter **can weaken** a shipped capability, and plugins already run as first-party code — but it
cannot open a hole by omission, because an absent entry denies.

**Filter-injected core tools declare through the same filter.** Not every core tool goes through the
loader: the **x402** micropayments module (core, loaded unconditionally at boot) registers its 8
`klytos_x402_*` tools through `mcp.tools_list`/`mcp.handle_tool` rather than `register()`, exactly as a
plugin does. It therefore declares their capabilities through `mcp.tool_capabilities` in
`installer/core/x402-mcp-tools.php` — reads at `x402.view` (owner/admin/editor), writes at
`x402.manage` (owner/admin), the capabilities x402 already defines in the matrix. Without that
declaration the gate's default-deny would make every x402 tool unusable by every role, **including
owner** — the regression this slice's code review caught. `scripts/keel-verify` check 10 covers only
the **static** core map against the loader's 33 files, so filter-injected tool sets (x402, and the
shipped plugins in slice 3) are outside its static scope by design; each is covered by its own tests
instead.

## The refusal shape is dictated by the transport (slice 2)

The refusal is not chosen; the transport dictates it. `ToolRegistry::call()` throws a typed
`Klytos\Core\MCP\PermissionDeniedException`, and each caller catches it and shapes it for its own
protocol:

- **The MCP server** (`server.php::handleToolsCall()`) emits a JSON-RPC **error object** with an
  **explicit HTTP 403** — `http_response_code(403)` plus `Helpers::jsonResponse( JsonRpc::error(
  -32000, …, $id ), 403 )`, mirroring the existing 401 auth-failure block and keeping the id
  correlation. This explicit-status step is **mandatory, not cosmetic**: the normal dispatch emits
  with no status arg, which defaults to **HTTP 200**, so a denial merely *returned* as an error
  array would ship as 200. The client-facing message names the tool (which the caller already
  supplied) but not the internal role or capability; the full reason went to the audit log.
- **The AI chat engine** turns it into a model-visible tool error through the tool callback's
  existing `catch`, so the model sees the refusal and can adapt.

`klytos_deny('mcp')` is **not** reused — its `{error, code}` body is not a JSON-RPC error object, so
an MCP client cannot parse it as one.

An audit action fires before the throw:

```php
klytos_add_action( 'mcp.access_denied', function ( string $tool, ?string $role, string $reason ): void {
    klytos_log_warning( "MCP refused {$tool} for role " . ( $role ?? 'none' ) . ": {$reason}", [], 'security' );
}, 10 );
```

Like `auth.access_denied`, it **cannot reverse the decision** — it fires for logging/alerting only.

## tools/list is filtered too (slice 2)

`ToolRegistry::listTools()` filters the advertised list by the actor's capabilities — using the same
`denialReason()` decision — **after** the `mcp.tools_list` plugin filter (so plugin tools are
filtered too) and **before** the return. The advertised surface equals the usable one, so an agent
does not plan around tools it will be refused. This is a **courtesy, not the control**: `tools/call`
gates independently, so a tool the list omitted is still refused if named directly. Hiding is not
access control; the gate is. A registry with no actor advertises an **empty** list — the same
fail-closed default the call gate applies.

## Public surfaces (slices 1–2)

| Surface | What it does |
|---|---|
| `Auth::createBearerToken( string $label = '', string $role = 'owner' ): array` | Mint a bearer token that operates as `$role`. |
| `Auth::getBearerTokenActor( string $token ): ?array` | Resolve a bearer token's `{user_id, role}`; null if unknown; null role if unstamped. |
| `Auth::migrateCredentialRoles(): int` | Idempotently stamp role-less bearer tokens with `owner`; returns the number stamped. |
| `Klytos\Core\MCP\TokenAuth::getActor(): ?array` | The actor resolved for the current authenticated MCP request, or null. |
| `Klytos\Core\MCP\ToolRegistry::setActor( int\|string\|null $userId, ?string $role ): void` | Carry the request's identity onto the per-request registry; the gate reads its role. |
| `klytos_mcp_tool_capabilities(): array` | The MCP tool→capability map (absent = deny; `null` = audited exception); filterable via `mcp.tool_capabilities`. |
| `Klytos\Core\MCP\PermissionDeniedException` | Thrown by `ToolRegistry::call()` on refusal; each transport catches it and shapes its own error. |
| `mcp.tool_capabilities` *(filter)* | Lets a plugin declare capabilities for its own MCP tools; cannot open a hole by omission. |
| `mcp.access_denied` *(action)* | Fires before an MCP refusal; audit hook, cannot reverse the decision. |

## Related

`docs/reference/authorization.md` (the admin gate, whose matrix and default-deny shape slice 2
reuses) · `docs/keel-verify.md` (check 10) · D-020 · D-046 · D-047 · D-048 · D-049 · NEW-02 ·
NEW-08 · NEW-11
