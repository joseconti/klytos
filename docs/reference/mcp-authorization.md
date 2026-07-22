# MCP authorization — Klytos CMS

> Introduced by Sprint 2 (audit **NEW-02**, decision **D-020**). This is the reference for how the
> MCP interface decides *what a caller may do*, not merely *who they are*.
>
> **Sprint 2 is landing in slices.** This document grows with them:
> - **Slice 1 (done):** identity — resolving an **actor** `{user_id, role}` from the credential, plus
>   the credential-role model and its boot migration. *That is what this file currently covers.*
> - **Slice 2:** the gate itself — the central capability map, the default-deny enforcement point in
>   `ToolRegistry::call()`, the JSON-RPC refusal, and the `tools/list` filter.
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

## Public surfaces (this slice)

| Surface | What it does |
|---|---|
| `Auth::createBearerToken( string $label = '', string $role = 'owner' ): array` | Mint a bearer token that operates as `$role`. |
| `Auth::getBearerTokenActor( string $token ): ?array` | Resolve a bearer token's `{user_id, role}`; null if unknown; null role if unstamped. |
| `Auth::migrateCredentialRoles(): int` | Idempotently stamp role-less bearer tokens with `owner`; returns the number stamped. |
| `Klytos\Core\MCP\TokenAuth::getActor(): ?array` | The actor resolved for the current authenticated MCP request, or null. |

## Related

`docs/reference/authorization.md` (the admin gate, whose helpers slice 2 reuses) · D-020 · D-046 ·
D-047 · D-049 · NEW-02 · NEW-08 · NEW-11
