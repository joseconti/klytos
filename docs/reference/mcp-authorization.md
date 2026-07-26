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
> - **Slice 3 (done):** coverage — the tool loader **fails loudly** (D-049), `integrity-tools.php` is
>   wired in and gated, the two shipped MCP plugins (`klytos-forms`, `klytos-importer`) declare their
>   tools' capabilities, the AI-chat advisory tool list default-denies unknown roles, and a
>   filter-injected tool is now **callable over the HTTP transport** (NEW-30). *See "The loader fails
>   loudly", "coverage completeness" and the filter-injected notes below.*
> - **Slice 4 (done):** the count reconciliation ("How many MCP tools there are"), the translated
>   refusal, "Adding a new MCP tool — the checklist", and the `ai.use` widening (**D-051**). The
>   forward reference in `docs/reference/authorization.md` is closed and now points here.
>
> **Sprint 2 is CLOSED.** NEW-02 is closed with it.

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
| **Application password** | yes (any **active** user since D-056; pinned to the admin user before it) | the **user record** (`UserManager::getByUsername()`) |
| **OAuth access token** | yes (the token's `user` subject) | the **user record** (`UserManager::getByUsername()`) |
| **Bearer token** | **no** | the **token record itself** (a stamped `role` field) |

Both username-carrying credentials go through **one** resolver,
`TokenAuth::resolveUserActor()`, which reads the record's `role` **and** its `status` — see
*Suspension* below.

This is deliberate (D-047, as amended in slice 1). Application passwords and OAuth tokens already name
a user, so their role follows that user's record — DRY, and it is what made per-user credentials work
the moment **NEW-11** was closed. That happened in Sprint 5 (**D-056**): `validateAppPassword()` now
resolves the username against an **active user record** instead of comparing it to
`config['admin_user']`, so an application password minted for `editor` authenticates and reaches this
gate carrying the editor's role — with no change to the resolver, exactly as D-047 intended.
Only bearer tokens, which name no user, carry a role on the credential.

**Fail-closed everywhere.** An empty username, a username that no longer resolves to a user, a
**non-active** account, a token record with no role, or any storage error all resolve to **null** —
deny, never a default of `owner`. That last point matters: a valid credential whose user record has
been deleted (a corrupted or half-migrated install, **NEW-08**) denies rather than escalating.

## Suspension — what it does, and what it does NOT do

Suspending a user (`status = 'suspended'`) makes their OAuth access token answer **HTTP 401 on the
next request**. Before Sprint 6 slice 2 it kept working, with its role, until it expired — up to an
hour (audit **NEW-41**). Of the three credential types it was the only one where an operator's
suspension did not take effect, and the inconsistency was the dangerous half: an operator who
suspends an account reasonably believes access is gone.

**It is refused at AUTHENTICATION (401), not at the gate (403)** — deliberately, per **D-060**. That
is the layer **D-056** put application passwords at, so one operator action now produces one answer
from every credential type rather than three. The wire body is the transport's
`Unauthorized: Invalid or missing authentication credentials.` JSON-RPC error, not the catalogue's
`mcp.permission_denied` refusal; that difference is what makes the layer observable, and
`tests/Integration/OAuthSuspensionHttpTest.php` asserts it rather than the status alone.

**What this does NOT do — said plainly rather than implied:** it does **not revoke** the stored
token. The status is read on every request, so:

```php
$users = klytos_app()->getUserManager();

$users->update( $editorId, [ 'status' => 'suspended' ] );  // the token answers 401 from now on
$users->update( $editorId, [ 'status' => 'active' ] );     // the SAME token works again
```

Active revocation — deleting a suspended user's stored tokens, and the adjacent question of whether
a **role change** should invalidate them too — is its own decision with its own test point and is
**not built**. If an operator needs a token gone rather than refused, they revoke it explicitly.

The same resolver change also applies to **application passwords**, where it is defence in depth
rather than a fix: `validateAppPassword()` has required an active record since D-056, so a suspended
user's application password was already refused one layer earlier.

One consequence, named rather than discovered: an OAuth token whose user record has been **deleted**
now also answers 401 instead of the gate's 403. Same direction D-056 chose for the same condition on
the application-password path — both fail closed; only the layer moves.

**Operational note an operator should know before suspending a busy integration (audit NEW-49).** A
refused request is an *authentication failure*, so it feeds `RateLimiter`'s auth-failure bucket —
which is keyed by **IP address**, shared by every credential from that address, and checked at the
top of every MCP request (10 failures per 60 s → **429** for everyone behind it). A suspended client
that keeps retrying will therefore throttle any *other* MCP client sharing its source address, which
in practice means a NAT or a reverse proxy (the same precondition as **NEW-17**). It is not a new
attacker capability — an invalid token has always cost the same one request — but it is a new way for
a legitimate client to cause it, and the honest remedy is to stop the retrying integration rather
than to wait out the window.

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
a user in the first place. Sprint 5 did **not** change this: closing NEW-11 made *application
passwords* per-user (they name a user), while a bearer token still names none. Per-user bearer tokens
remain unbuilt, and an operator who wants an attributable MCP credential should mint an application
password for the account instead.

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

*(As written at Sprint 2's close, and superseded by Sprint 5 — kept because it records what was true
when the gate was built.)* Every credential that existed **then** resolved to `owner`: application
passwords were pinned to the admin user (the owner), and existing bearer/OAuth credentials migrate or
resolve to owner. The gate (slice 2) is real and default-deny, but its power-**reducing** effect only
materialises for a credential minted at a lower role, and bearer tokens were the only credential
mintable below owner without touching NEW-11 — which is why a `role=viewer` bearer token is the
sprint's end-to-end proof (denied a destructive tool over real HTTP in slice 2).

**Since Sprint 5 (D-056), per-role application passwords work.** One minted for `editor` or `viewer`
authenticates and arrives here carrying that role, and a **suspended** user's application password is
refused outright. Both are pinned by `tests/Integration/McpActorResolutionTest.php`.

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

It covers the **172 live core tools** (the 34 loader files, `integrity-tools.php` among them since
slice 3). Notable choices, recorded so they are not mistaken for oversights:

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
- **File integrity is `site.configure` (slice 3).** `klytos_integrity_check`,
  `klytos_integrity_status` and `klytos_integrity_check_plugin` run and read file-hash verification
  against signed manifests. Even reading an integrity report exposes system internals (which files
  differ from the signed release), so they sit at owner/admin, mirroring admin `system-integrity.php`
  / `api/integrity.php` → `site.configure`. They were **dead** before slice 3 (`integrity-tools.php`
  was on disk but absent from the loader list); the loader wires them in and the map gates them.

Plugins declare capabilities for their own tools through the **`mcp.tool_capabilities`** filter (the
`admin.gate_map` precedent). Stated honestly: like `admin.gate_map` and `auth.capabilities` this
filter **can weaken** a shipped capability, and plugins already run as first-party code — but it
cannot open a hole by omission, because an absent entry denies. The two shipped MCP plugins declare
theirs this way (slice 3): **`klytos-forms`** maps its 16 tools at `forms.manage` (owner/admin — forms
carry submitted data, and the matrix has no `forms.view`, so the whole domain sits at the manage bar);
**`klytos-importer`** maps its 10 tools at `site.configure` (a whole-site migration that fetches
arbitrary external URLs and bulk-creates pages is an operations privilege, the mirror of
`klytos_export_site`).

**Filter-injected core tools declare through the same filter.** Not every core tool goes through the
loader: the **x402** micropayments module (core, loaded unconditionally at boot) registers its 8
`klytos_x402_*` tools through `mcp.tools_list`/`mcp.handle_tool` rather than `register()`, exactly as a
plugin does. It therefore declares their capabilities through `mcp.tool_capabilities` in
`installer/core/x402-mcp-tools.php` — reads at `x402.view` (owner/admin/editor), writes at
`x402.manage` (owner/admin), the capabilities x402 already defines in the matrix. Without that
declaration the gate's default-deny would make every x402 tool unusable by every role, **including
owner** — the regression slice 2's code review caught. `scripts/keel-verify` check 10 covers only
the **static** core map against the loader's 34 files (172 tools), so filter-injected tool sets (x402
and the shipped plugins) are outside its static scope by design; each is covered by its own tests
instead (`McpToolGateTest`, `McpGateHttpTest`).

## How many MCP tools there are (slice 4 — the count reconciliation)

Every figure below was **measured** on the live playground for this document (an owner `tools/list`
grouped by prefix), not copied from an earlier document — the L-015 rule, because this project's own
records are exactly the source that gets trusted hardest and checked least. There is **no single
number**, and pretending there is one is what produced the stale 172/177/206 spread that this slice
reconciled: the served count depends on which plugins are active.

| Set | Count | Where it comes from | Gated by |
|---|---|---|---|
| Core, loader-registered | **172** | the 34 files in `installer/core/mcp/tools/`, listed in `ToolRegistry::registerAllTools()` | the static map in `tool-capabilities.php`; **keel-verify check 10** |
| Core, filter-injected (x402) | **8** | `installer/core/x402-mcp-tools.php`, loaded unconditionally at boot | `mcp.tool_capabilities` declaration + `McpToolGateTest` |
| **Default install serves** | **180** | 172 + 8 — neither shipped MCP plugin is active on a fresh install (`$state['active'][$id] ?? false`) | — |
| `klytos-forms` (when active) | **16** | the plugin's `mcp.tools_list` | `forms.manage`, declared via `mcp.tool_capabilities` |
| `klytos-importer` (when active) | **10** | the plugin's `mcp.tools_list` | `site.configure`, declared via `mcp.tool_capabilities` |
| **All tools on disk / the playground serves** | **206** | 172 + 8 + 16 + 10; the seed activates both plugins | — |
| Dead tools | **0** | the 3 integrity tools were the only dead set; slice 3 wired them in | — |

`docs/api/INDEX.md` records **206** — every tool that exists in the repository, which is the right
figure for an API index and stays correct. Historical documents saying "177 served" or "169 live"
described a real state at a real moment (177 = the pre-gate served count with x402 but with the
integrity tools still dead; 169 = the live core count before slice 3 wired them in); they were not
wrong when written, they were superseded. Anywhere those numbers describe *today*, they are corrected.

**The one number a new tool must not change silently is 172.** keel-verify check 10 parses the
loader's `$toolFiles` literal and fails the build when a registered core tool has no capability-map
entry, so the static core set cannot grow past its map. Filter-injected sets (x402, the plugins) are
outside that static scope **by design** — a filter's contents are not knowable without booting the
app — and each is covered by its own test instead.

## Adding a new MCP tool — the checklist

The mirror of "Adding a new admin page" in `docs/reference/authorization.md`. Step 2 is the one that
matters: **until you do it, the tool is denied to everyone, including the owner.**

1. Write the tool in the right domain file under `installer/core/mcp/tools/`, registering it with
   `$registry->register( 'klytos_your_tool', … )`. A brand-new file must also be added to the
   `$toolFiles` list in `ToolRegistry::registerAllTools()` — a file that is on disk but off the list
   registers nothing, which is exactly how `integrity-tools.php` stayed dead for its whole life
   (D-049). A file on the list that registers nothing now **throws** at boot rather than being
   skipped.
2. Map it in `installer/core/mcp/tool-capabilities.php`: a capability string from the ONE matrix, or
   `null` **with a comment stating why** if it genuinely needs no capability (the audited exception
   list — currently only the two guide readers). **An absent entry denies.** Pick from the matrix in
   `UserManager::hasPermission()`; do not invent a capability here, and do not add a second matrix.
   Where in doubt, take the higher tier — over-restriction fails safe and is a one-line change to
   loosen later; the opposite is a security incident.
3. For a **filter-injected** tool — a plugin's, or a core module that registers through
   `mcp.tools_list`/`mcp.handle_tool` rather than `register()` — declare its capability through the
   **`mcp.tool_capabilities`** filter instead of the static map:

   ```php
   klytos_add_filter( 'mcp.tool_capabilities', function ( array $map ): array {
       $map['klytos_myplugin_list']   = 'analytics.view';
       $map['klytos_myplugin_delete'] = 'site.configure';
       return $map;
   } );
   ```

   Without this the tool is advertised to nobody and refused to everybody — including the owner.
4. Run `php scripts/keel-verify`. Check 10 fails the build when a registered core tool has no map
   entry. It does **not** cover filter-injected tools, so step 5 is not optional for those.
5. Add a test. A capability-gated tool gets at least one role that may call it and one that may not
   (`tests/Integration/McpToolGateTest.php` for the in-process gate,
   `tests/Integration/McpGateHttpTest.php` when the tool's reachability over the wire is the point).
   Asserting both directions is the rule: a filter that dropped everything and a filter that dropped
   nothing both pass a one-directional test (L-008).
6. Document it — `docs/api/` entry plus its row in `docs/api/INDEX.md`, in the same slice.

## The loader fails loudly (slice 3)

`ToolRegistry::registerAllTools()` walks a hardcoded `$toolFiles` list and, per file, calls
`registerToolFile()`. That method now **throws `Klytos\Core\MCP\ToolRegistrationException`** when a
listed file is missing, or is present but defines neither its namespaced nor its global register
function. Before slice 3 it skipped such a file by silent fall-through — which is exactly how
`integrity-tools.php` (present on disk, off the list) stayed dead and unnoticed for its whole life: the
loader could not tell "this file registers nothing" from "this file is fine". Failing loudly surfaces
an unfinished or misnamed registration at boot/CI, the S-07 default-deny lesson (D-049, L-007) applied
to the loader. `registerToolFile()` is extracted from the loop so the contract is exercised per file by
a test (`McpToolLoaderTest`) without mutating the `$toolFiles` literal that keel-verify check 10 parses.

## Filter-injected tools are callable over HTTP (slice 3 — NEW-30)

`ToolRegistry::exists()` treats a tool name as known when it is either registered through the loader
**or** declared in the capability map. A filter-injected tool (x402, and the shipped plugins) never
enters the register table — it is served entirely through `mcp.tools_list`/`mcp.handle_tool` — so a
register-only `exists()` left `server.php::handleToolsCall()` rejecting it with a JSON-RPC "Unknown
tool" **before** the gate, even though `tools/list` advertised it. Widening `exists()` to the declared
set lets those calls reach `call()`, which gates them (`denialReason`) and dispatches them via
`mcp.handle_tool` exactly as the AI-chat path already did. A name that is neither registered nor
declared is still unknown — an undeclared plugin tool fails closed, as default-deny intends; and
`handleToolsCall()` catches a **`ToolNotFoundException`** from `call()` (a mapped-but-unhandled tool —
a typo or orphaned declaration) and answers "Unknown tool" rather than leaking a 500. That catch is
deliberately narrow — a typed exception, not every `RuntimeException` — so a plain `RuntimeException`
from a handler surfaces as a real error instead of being masked, and a `PermissionDeniedException`
(also a `RuntimeException`) is caught earlier and shipped as the 403. Before this, filter-injected
tools worked only on the AI-chat path; now they are first-class over both transports.

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
  supplied) but not the internal role or capability; the full reason goes to the
  `mcp.access_denied` action instead.

  **It is translated (slice 4).** This is the one MCP string a *person* reads — an MCP client
  surfaces the refusal to whoever is driving the agent — so it comes from the locale catalogues
  like every other user-facing string in the product: `mcp.permission_denied`, present in all
  **20** locales, resolved through the I18n **service** (`$app->getI18n()->get( 'mcp.permission_denied', [ 'tool' => $name ] )`),
  not the global `__()`, which is declared only in `admin/bootstrap.php` and does not exist on the
  MCP path (**NEW-18** — the same reason `installer/public/comment-submit.php` calls the service).
  The message states the refusal and names the fix ("ask the site owner to grant this connection
  the permission it requires") while disclosing neither the caller's role nor the required
  capability. The internal `denialReason()` strings stay **English on purpose**: their reader is
  the operator's security log, not a client. Pinned by `tests/Unit/McpRefusalI18nTest.php` (per
  locale, including that the translations are not English copies) and by an assertion in
  `McpGateHttpTest` that the wire message equals the catalogue entry.
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

**It is a seam, not a sink — say this plainly rather than implying a log exists.** No core listener
subscribes to `mcp.access_denied` (or to `auth.access_denied`), so out of the box a refusal writes
**nothing** to `installer/data/logs-*/`: the reason is offered to whoever wants it, and by default
nobody does. Subscribing the snippet above is what turns refusals into log entries. Recorded as
audit **NEW-32**, found by the Sprint-2 playground-QA pass, which followed this documentation to a
log that was legitimately empty. Wording elsewhere that said the reason "went to the audit log" is
corrected to this.

## tools/list is filtered too (slice 2)

`ToolRegistry::listTools()` filters the advertised list by the actor's capabilities — using the same
`denialReason()` decision — **after** the `mcp.tools_list` plugin filter (so plugin tools are
filtered too) and **before** the return. The advertised surface equals the usable one, so an agent
does not plan around tools it will be refused. This is a **courtesy, not the control**: `tools/call`
gates independently, so a tool the list omitted is still refused if named directly. Hiding is not
access control; the gate is. A registry with no actor advertises an **empty** list — the same
fail-closed default the call gate applies.

## The AI chat is the same gate (slice 4 — `ai.use` widened)

The admin AI chat calls the same `ToolRegistry::call()`, with the actor taken from the session
instead of a credential — so a tool call the chat makes is gated exactly like one arriving over
HTTP, with the **caller's own** role. That is what made the `ai.use` widening safe: `editor` was
excluded from the AI chat (D-035) purely because, while NEW-02 was open, the tool layer would have
executed anything the chat asked for regardless of who asked. Now it will not, so `editor` holds
`ai.use` (**D-051**, superseding D-035 at its own recorded trigger). `viewer` stays out.

Two honest limits, so this is not read as more than it is:

- The chat's advertised tool list (`chat-engine::getAvailableTools()`) is **advisory**. It
  default-denies an unknown role since slice 3, but it is not the control — `call()` is. A model
  that names a tool the list omitted is refused by the gate, not by the list.
- An editor in the chat can still ask for anything; what changed is that the answer is now "no" for
  everything an editor may not do, per tool, with the refusal visible to the model so it can adapt.

## Public surfaces (slices 1–4)

| Surface | What it does |
|---|---|
| `Auth::createBearerToken( string $label = '', string $role = 'owner' ): array` | Mint a bearer token that operates as `$role`. |
| `Auth::getBearerTokenActor( string $token ): ?array` | Resolve a bearer token's `{user_id, role}`; null if unknown; null role if unstamped. |
| `Auth::migrateCredentialRoles(): int` | Idempotently stamp role-less bearer tokens with `owner`; returns the number stamped. |
| `Klytos\Core\MCP\TokenAuth::getActor(): ?array` | The actor resolved for the current authenticated MCP request, or null. |
| `Klytos\Core\MCP\ToolRegistry::setActor( int\|string\|null $userId, ?string $role ): void` | Carry the request's identity onto the per-request registry; the gate reads its role. |
| `Klytos\Core\MCP\ToolRegistry::registerToolFile( string $toolsDir, string $file ): void` | Register one loader file's tools, throwing `ToolRegistrationException` if it is missing or registers none (slice 3, D-049). |
| `Klytos\Core\MCP\ToolRegistry::exists( string $name ): bool` | True when a tool is registered OR declared in the capability map — so filter-injected tools are callable over HTTP (slice 3, NEW-30). |
| `klytos_mcp_tool_capabilities(): array` | The MCP tool→capability map (absent = deny; `null` = audited exception); filterable via `mcp.tool_capabilities`. |
| `Klytos\Core\MCP\PermissionDeniedException` | Thrown by `ToolRegistry::call()` on refusal; each transport catches it and shapes its own error. |
| `Klytos\Core\MCP\ToolRegistrationException` | Thrown by the loader (`registerToolFile`) when a listed file is missing or registers no tools (slice 3, D-049). |
| `Klytos\Core\MCP\ToolNotFoundException` | Thrown by `ToolRegistry::call()` for a mapped-but-unhandled tool (post-gate); the transport answers "Unknown tool" without masking other errors (slice 3, NEW-30). |
| `mcp.tool_capabilities` *(filter)* | Lets a plugin (or a filter-injected core module) declare capabilities for its own MCP tools; cannot open a hole by omission. |
| `mcp.access_denied` *(action)* | Fires before an MCP refusal; audit hook, cannot reverse the decision. |
| `mcp.permission_denied` *(locale key, slice 4)* | The client-facing 403 message, in all 20 catalogues; `{tool}` is substituted with the requested tool name. |

## Related

`docs/reference/authorization.md` (the admin gate, whose matrix and default-deny shape slice 2
reuses) · `docs/keel-verify.md` (check 10) · `docs/flows/mcp-tool-call.md` (the journey, including
every failure branch) · `docs/playground.md` (the per-role `tools/call` table you can run) ·
D-020 · D-046 · D-047 · D-048 · D-049 · D-050 · D-051 · **D-060** (suspension, above) ·
NEW-02 (closed) · NEW-08 · NEW-11 · NEW-18 · NEW-41 (closed)
