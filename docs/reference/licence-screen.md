# Licence screen — extension points

`installer/admin/license.php` · manifest entry 28 · template `record-form`.

The screen the install's Klytos licence is read and activated on: **Plan** (the
status, plan, registered domain, activation date, last check, and the on-demand
check) and **Licence key** (the stored key, readonly and copyable, plus the field
that activates or replaces one). Every control is a plain form post and works
with JavaScript disabled; the Copy button is the only enhancement, and the value
beside it is selectable and complete precisely so that nothing is lost without
it.

The business logic lives in `Klytos\Core\License`, reached through
`App::getLicense()`. Three facts are worth knowing before extending this screen,
because each one is a property of the product rather than of the page:

- **The licence is the install's, not a plugin's.** `License::$itemName` is
  hardcoded to `Klytos` and the record is `config/license.json.enc` — one licence
  per install. Premium plugins are licensed separately, per plugin, out of
  `config/plugin_licenses/{id}.json.enc` by `PluginLoader::verifyPluginLicense()`.
- **Nothing calls `License::isActive()`**, so the licence currently gates no
  feature anywhere. An expired licence degrades this screen and the status bar
  and changes nothing else.
- **Nothing calls `License::checkIfDue()`** either, so the seven-day automatic
  re-verification the manager implements never runs: `Last check` moves only when
  someone uses **Check now**. The screen's own wording says so, and a test pins
  that wording — a hint promising an automatic check would describe a control
  that does not exist.

The screen also registers the first listener of the shell's
`admin.statusbar_degraded` filter (documented in
[`admin-navigation.md`](admin-navigation.md)), in `installer/admin/bootstrap.php`
rather than in the screen, because the fact belongs on every admin page.

## The six screen actions

All six take the licence record as their single argument — the array
`License::getStatus()` returns, read once per response. They change what the
screen renders and nothing about what is stored; to change the stored record, use
`License` itself.

| Action | Fires |
|---|---|
| `admin.licence.before` | Immediately after the shell, before the status line and the error summary |
| `admin.licence.before_cards` | Inside the card stack, above the Plan card |
| `admin.licence.before_key_field` | Inside the activation form, above the key field |
| `admin.licence.after_key_field` | Inside the activation form, below the key field and its error |
| `admin.licence.after_cards` | Inside the card stack, below the Licence key card |
| `admin.licence.after` | After the card stack, before the footer |

| | |
|---|---|
| **Signature** | `klytos_do_action( 'admin.licence.<point>', array $status )` |
| **`$status`** | The licence record: `license_key`, `license_status` (`valid` / `revoked` / `expired` / `missing`), `license_salt`, `domain`, `site_url`, `activated_at`, `last_verified`, `plan`, `grace_period_until`. On an install with no licence it is the empty-shaped record `getStatus()` returns from its `catch`. |
| **Returns** | Nothing — a listener echoes markup. |

The two form points are inside `<form id="k-licence-activate">`, so anything a
listener echoes there is posted with the key. The handler reads `license_key` and
`action` only; a listener that needs its own values reads `$_POST` from a hook of
its own on the same request.

```php
// A support plugin adding its own account link beside the key field.
klytos_add_action( 'admin.licence.after_key_field', static function ( array $status ): void {
    if ( ( $status['license_status'] ?? '' ) === 'valid' ) {
        return;
    }

    printf(
        '<p><a href="%s">%s</a></p>',
        klytos_esc_url( 'https://plugins.joseconti.com/account/' ),
        klytos_esc_html( __( 'my_plugin.find_your_key' ) )
    );
} );
```

```php
// A hosting plugin that suppresses the screen's cards entirely on a managed
// install, where the licence is provisioned outside the admin.
klytos_add_action( 'admin.licence.before_cards', static function ( array $status ): void {
    printf(
        '<p class="k-status-line k-status-line--info">%s</p>',
        klytos_esc_html( __( 'my_host.licence_managed_for_you' ) )
    );
} );
```

## What this screen deliberately does not render

`SPEC/manifest.md` §28 names four cards and two are not built, each because the
product has nothing behind it (D-101, `docs/roadmap.md` §0c). A plugin that adds
either is adding a product surface, not filling a gap:

- **Activated domains** — the record holds exactly one `domain` and one
  `site_url`; there is no collection of activated domains anywhere, and the
  licence API is never asked for one.
- **Entitlements** — `plan` is a bare string copied out of the activation
  response. No entitlement, quota or feature record exists, and nothing reads
  `plan` to grant or refuse anything.

`tests/E2E/licence.spec.js` asserts the absence of both, so building either later
means deleting an assertion on purpose rather than discovering the decision by
accident.
