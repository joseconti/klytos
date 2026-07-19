# Authorization — Klytos CMS

> Introduced by Sprint 1 slice 4 (audit finding **S-07**). This is the reference for the admin
> authorization gate: the capability matrix, the gate map, the helpers, and the extension points.

## The shape of the system

There is exactly **one** authorization decision point in the product:
`UserManager::hasPermission()` (`installer/core/user-manager.php`). Everything else asks it.

```
klytos_require_permission( 'users.manage' )   ← enforce (refuse + stop)
        └─ klytos_has_permission( 'users.manage' )   ← answer (bool)
                └─ UserManager::hasPermission( $user, 'users.manage' )   ← decide
                        └─ the capability matrix, filtered by auth.capabilities
```

That single-decision-point property was established in slice 3 (**S-04**) and is guarded by a test:
`tests/Integration/PermissionMatrixTest::testTheMatrixIsDefinedExactlyOnce()`. Do not add a second
one — a hand-rolled `in_array( $role, [ 'owner', 'admin' ] )` is a second decision point, and slice 4
removed the last of those from `admin/security.php`.

## The capability matrix

Roles: `owner` (holds everything, by shortcut), `admin`, `editor`, `viewer`.

| Capability | owner | admin | editor | viewer |
|---|:--:|:--:|:--:|:--:|
| `pages.view` | ✓ | ✓ | ✓ | ✓ |
| `pages.create` | ✓ | ✓ | ✓ | |
| `pages.edit` | ✓ | ✓ | ✓ | |
| `pages.delete` | ✓ | ✓ | | |
| `theme.manage` | ✓ | ✓ | | |
| `menu.manage` | ✓ | ✓ | | |
| `blocks.manage` | ✓ | ✓ | | |
| `templates.manage` | ✓ | ✓ | | |
| `templates.approve` | ✓ | | | |
| `build.run` | ✓ | ✓ | | |
| `assets.manage` | ✓ | ✓ | ✓ | |
| `tasks.create` | ✓ | ✓ | ✓ | |
| `tasks.manage` | ✓ | ✓ | | |
| `users.manage` | ✓ | | | |
| `mcp.manage` | ✓ | ✓ | | |
| `site.configure` | ✓ | ✓ | | |
| `plugins.manage` | ✓ | | | |
| `analytics.view` | ✓ | ✓ | ✓ | |
| `forms.manage` | ✓ | ✓ | | |
| `webhooks.manage` | ✓ | ✓ | | |
| `updates.manage` | ✓ | | | |
| `terminal.access` | ✓ | | | |
| `profile.edit` *(slice 4)* | ✓ | ✓ | ✓ | ✓ |
| `security.self` *(slice 4)* | ✓ | ✓ | ✓ | ✓ |
| `ui.preferences` *(slice 4)* | ✓ | ✓ | ✓ | ✓ |
| `setup.run` *(slice 4)* | ✓ | | | |
| `ai.use` *(slice 4)* | ✓ | ✓ | | |

**Unknown keys deny.** A permission the matrix does not define resolves to an empty allow-list, so a
typo denies rather than grants — for every role except `owner`, whose shortcut returns before the
matrix is consulted.

**`ai.use` is deliberately narrow.** The AI chat executes MCP tools, and the MCP tool layer has no
permission checks at all until Sprint 2 (audit **NEW-02**, decision **D-020**). Until then, reaching
that surface is owner-equivalent power regardless of the caller's role, so `editor` is excluded.
Revisit at Sprint 2 close.

## The central gate

Every one of the 66 files under `installer/admin/` requires `admin/bootstrap.php`, which calls
`klytos_enforce_admin_gate()` after establishing that somebody is logged in. The gate looks the
running file up in the **gate map** and requires the mapped capability.

**A surface absent from the map is refused.** That is the whole point: before slice 4, 51 of 66 files
were individually responsible for remembering their own gate and did not, so a new admin file
defaulted to *open*. It now defaults to *closed*, and `scripts/keel-verify` fails the build when a
file under `admin/` has no entry.

`bootstrap.php` itself is deliberately **not** mapped — it hosts the gate rather than being a surface
the gate protects, so a direct request for it hits default-deny.

### Map values

| Value | Meaning |
|---|---|
| a capability string | Require it. |
| `null` | No capability required. The audited exception list; every `null` carries its reason in a comment. It does **not** mean unauthenticated — the auth guard runs first and separately. |
| *absent* | **Denied.** |

### Page-level is a floor, not a ceiling

A page whose tiers differ maps at the level needed to *see* it and re-gates its privileged branches
inline. `admin/pages.php` is mapped `pages.view` so an editor can read the list, and its POST handler
calls `klytos_require_permission( 'pages.delete' )` before trashing anything.

**An API twin must re-gate the same branches its page does.** This is where the floor/ceiling rule
is easiest to get wrong, and it was wrong here until slice 5: `admin/tasks.php` is mapped
`tasks.create` and re-gates completion at `tasks.manage` (`tasks.php:38`), while
`admin/api/tasks.php` — mapped at the same `tasks.create` — re-gated nothing. An editor was
therefore refused task completion through the interface and allowed it through the endpoint the
interface calls (audit **S-06**). When a page and an API expose the same operation, they express the
same capability model or the model is enforced in only one of them; the gate map cannot see the
difference, because both files legitimately sit at the same floor.

## Functions

### `klytos_require_permission( string $permission, ?string $surface = null ): void`

Require a capability, or refuse the request and stop. The enforcing counterpart to
`klytos_has_permission()`, which only answers.

Refuses **401** when nobody is authenticated and **403** when somebody is but lacks the capability —
different facts about the caller, and different fixes for them.

```php
// Top of a privileged branch.
klytos_require_permission( 'users.manage' );

// Everything below this line runs only for callers who hold it.
$users = klytos_app()->getUserManager()->list();
```

`$surface` overrides shape detection; it exists as a testing seam and is not normally passed.

### `klytos_deny( int $status, string $message, string $code = 'forbidden', ?string $surface = null ): never`

Refuse the current request and stop, in the shape the caller can parse. Sets the status, sets the
`Content-Type`, writes the body, and exits.

| Surface | Response |
|---|---|
| `api`, `mcp` | `Helpers::jsonResponse( [ 'error' => …, 'code' => … ], $status )` |
| `cli` | The message on stderr, exit code 1 |
| `page` | A self-contained, escaped HTML document — no admin chrome |

The page shape is deliberately self-contained: the gate runs *before* a page has set up its own
context, and a gate that can fatal while rendering a refusal is not a gate.

```php
klytos_deny( 403, __( 'common.no_permission' ), 'forbidden' );
```

### `klytos_current_surface(): string`

Returns `'api'`, `'mcp'`, `'cli'` or `'page'`. `admin/api/*` is checked before the general admin
test, because those paths are both, and the API shape is the one their callers can parse.

### `klytos_admin_gate_map(): array<string, string|null>`

The map, keyed by path relative to `installer/admin/` (`'users.php'`, `'api/plugins.php'`). Keyed by
path rather than basename because six filenames exist in both directories.

### `klytos_admin_gate_key( ?string $scriptFilename = null ): ?string`

Resolves the running script to its map key, or `null` when it does not resolve inside `admin/`.

Derived from `SCRIPT_FILENAME` — the file PHP actually executed — rather than `SCRIPT_NAME`, which is
URL-derived and therefore caller-influenced.

### `klytos_enforce_admin_gate( ?string $scriptFilename = null ): void`

The central call. Refuses when the script does not resolve inside `admin/`, when it has no map entry,
or when the caller lacks the mapped capability.

## Extension points

### `admin.gate_map` (filter)

Filters the gate map. The intended use is a plugin gating its own admin files, or a deployment
tightening a shipped surface.

```php
klytos_add_filter( 'admin.gate_map', function ( array $map ): array {
    $map['my-plugin-report.php'] = 'analytics.view';
    return $map;
} );
```

Stated honestly: this filter **can** weaken a gate, exactly as the pre-existing `auth.capabilities`
filter can. Both are plugin-trust boundaries, and plugins already run as first-party code here. What
it cannot do is open a hole by omission — removing an entry *denies* the surface.

### `auth.access_denied` (action)

Fires immediately before a request is refused. The audit hook: log refusals, alert on them, count
them.

```php
klytos_add_action( 'auth.access_denied', function ( int $status, string $code, string $surface ): void {
    klytos_log_warning( "Refused: {$code} ({$status}) on {$surface}", [], 'security' );
}, 10 );
```

It **cannot reverse the decision**. A filter that could turn a denial into a grant would put the
product's authorization back in third-party hands, which is the failure S-07 exists to close.

### `auth.capabilities` (filter, pre-existing)

Filters the capability matrix itself. This is the supported way to add a capability or change which
roles hold one.

```php
klytos_add_filter( 'auth.capabilities', function ( array $caps ): array {
    $caps['reports.export'] = [ 'owner', 'admin' ];
    return $caps;
} );
```

## Adding a new admin page — the checklist

1. Create the file; `require_once` `bootstrap.php` at the top as every admin file does.
2. Add its entry to `klytos_admin_gate_map()` in `installer/core/admin-gate.php`. **Until you do,
   it is denied to everyone, including the owner.**
3. If the page mixes tiers, call `klytos_require_permission()` inline on the privileged branch.
4. Run `php scripts/keel-verify` — it fails if the entry is missing.
5. Add the surface to the per-role expectations in `tests/Integration/AdminGateHttpTest.php` if it
   is representative of a tier not already covered.

## What this does NOT cover

- **MCP tools.** All 172 of them still have zero permission checks (**NEW-02**, Sprint 2 per
  **D-020**). When Sprint 1 closes, the admin surface is gated and the product's primary interface is
  not. Sprint 2 reuses `klytos_require_permission()` at the `ToolRegistry` enforcement point.
- **Authentication.** `Auth::login()` validates only against `config['admin_user']`, so `admin`,
  `editor` and `viewer` accounts cannot log in through the form at all (**NEW-11**). The gate is
  correct regardless; the roles simply have no way to reach it interactively yet.
- **Authentication of the second factor.** Passkey second-factor login does not work (**NEW-09**),
  for two independent reasons, and the obvious one-line fix — exempting
  `api/webauthn-challenge.php` from the auth guard — was tried in this slice and **reverted**,
  because it opens an account-takeover path (**D-036**). Do not re-add it without also restricting
  that endpoint's registration actions.
- **CSRF.** Separate concern, separate helpers (`klytos_csrf_field()` / `klytos_verify_csrf()`). The
  gate answers *may this caller do this*, never *did this caller intend it* — so passing the gate is
  not a substitute for a token, and a state-changing surface needs both. `api/download-identity.php`
  is the worked example: it is gated owner-only at `users.manage` and STILL required a POST guard and
  a CSRF check, because the gate cannot tell an owner's deliberate click from an owner's browser
  being made to issue the request by someone else (audit **S-12**, slice 5).
- **Step-up authentication.** Nothing in this system re-checks a password or a second factor before a
  privileged action. Holding the capability is sufficient, so a hijacked session is as good as the
  account. That gap is recorded as **NEW-13** for the identity-key export specifically, and is bound
  to the authentication slice that owns NEW-09 and NEW-11.
