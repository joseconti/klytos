# Privacy screen — extension points

`installer/admin/privacy.php` · manifest entry 26 · template `record-form`.

The screen carries the two GDPR flows the product has: **Export requests**
(Art. 15 — find a person, see what would be exported, download it as JSON or
HTML, or send it to them) and **Erasure requests** (Art. 17 — find a person,
choose data sections, confirm, erase). Both are plain form posts and work with
JavaScript disabled.

The business logic lives in `Klytos\Core\PrivacyManager`, whose own hooks
(`privacy.export_data`, `privacy.erasable_data`, `privacy.erase_plugin_data`,
`privacy.before_erasure`, `privacy.erase_section`, `privacy.erasure_complete`)
are how a plugin contributes or erases its data. The three surfaces below belong
to the **screen** and change only what it renders.

## `privacy.status_labels` (filter)

Maps the status `PrivacyManager::eraseUserData()` returns for a section onto the
catalogue key the result table shows as a word.

| | |
|---|---|
| **Signature** | `klytos_apply_filters( 'privacy.status_labels', array $map )` |
| **`$map`** | `array<string,string>` — status → i18n key. Defaults: `anonymized`, `deleted`, `erased`, `skipped`. |
| **Returns** | The map to render from. |

A status with no entry renders its own raw value rather than a missing
translation key, so an unmapped plugin status degrades to something readable
instead of `privacy.whatever`.

Register a status a plugin's own erasure returns:

```php
klytos_add_filter( 'privacy.status_labels', static function ( array $map ): array {
    // The plugin's `privacy.erase_plugin_data` rows return status 'archived'.
    $map['archived'] = 'my-plugin.privacy_archived';

    return $map;
} );
```

The key must exist in all 20 catalogues of the plugin's own translation root, as
every user-facing key does.

## `admin.privacy.before_export` / `admin.privacy.after_export` (actions)

Emitted above the Export requests card and between the two cards. Neither takes
a payload; both are echo points for a plugin that needs to state something at
the top of the screen or between the flows — a retention notice, a link to its
own subject-access tooling.

```php
klytos_add_action( 'admin.privacy.after_export', static function (): void {
    echo '<p class="k-hint">'
        . klytos_esc_html( __( 'my-plugin.privacy_note' ) )
        . '</p>';
} );
```

`admin.privacy.before` and `admin.privacy.after` (the whole screen's outer
bookends) are unchanged and still available.

## What the screen deliberately does not have

The manifest's third card — a site-wide **per-section method and status** table
with a "last run" column — is **not built**, and the reason is recorded in
`docs/roadmap.md` §0c: the product has no site-wide section registry
(`collectErasableData()` requires a user), stores no per-section erasure
timestamp anywhere, and has no vocabulary matching `Automatic` / `Manual` /
`Not covered`. Building it would be a new product surface, not a redesign.
