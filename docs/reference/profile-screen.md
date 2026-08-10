# Profile screen — extension points

`installer/admin/profile.php` · manifest entry 27 · template `record-form`.

The screen a person edits their own account on: **Identity** (names, email, bio,
avatar URL, website, language, social links), **Security** (the password, behind
a mandatory confirmation with the current one) and **Preferences** (the admin
colour scheme). Every control is a plain form post and works with JavaScript
disabled.

The business logic lives in `Klytos\Core\UserManager`, whose own hooks
(`user.before_update`, `user.updated`, `user.profile_fields`) are how a plugin
changes what is stored. The three surfaces below belong to the **screen** and
change only what it renders — with one deliberate exception, the filter below,
which the POST handler reads as well.

Two facts worth knowing before extending it:

- **Every save is confirmed with the current password**, and everything is
  validated before anything is written. A plugin that adds a field through
  `user.profile_fields` inherits both.
- **The screen never fetches an image from another origin.** The admin sends
  `img-src 'self' data:`, so an avatar preview cannot load there; the stored URL
  is used by the published site, where that policy does not apply.

## `admin.profile.social_networks` (filter)

The social networks the Identity card renders **and** the POST handler reads.
One list, both jobs — so a network added here is a field that actually saves.

| | |
|---|---|
| **Signature** | `klytos_apply_filters( 'admin.profile.social_networks', array $networks )` |
| **`$networks`** | `array<string,array{label:string,example:string}>` — keyed by the key inside the user's `social_links`. `label` is the visible `<label>` (a brand name, not a translation key); `example` is the placeholder. |
| **Returns** | The networks to render and collect. |

Each network becomes a field named `social_<key>`, sanitized with
`klytos_sanitize_url()` and reported as a field-level error when the value is
not a plain `http(s)` address.

```php
klytos_add_filter( 'admin.profile.social_networks', static function ( array $networks ): array {
    $networks['bluesky'] = [
        'label'   => 'Bluesky',
        'example' => 'https://bsky.app/profile/…',
    ];

    return $networks;
} );
```

The value lands in `$user['social_links']['bluesky']` and is read back into the
field on the next load. Removing a network from this list stops rendering it and
stops collecting it; the stored value is left untouched rather than erased.

## `admin.profile.before_security` / `admin.profile.before_preferences` (actions)

Emitted before the Security card and before the Preferences card. Both receive
the user being edited.

```php
klytos_add_action( 'admin.profile.before_security', static function ( array $user ): void {
    echo '<p class="k-hint">'
        . klytos_esc_html( __( 'my-plugin.profile_notice' ) )
        . '</p>';
} );
```

`before_security` is inside the saved form, so a control echoed there is
submitted with the Save. `before_preferences` is **outside** it — the
Preferences card takes effect immediately and posts to its own endpoint — so a
control echoed there needs its own form and its own CSRF field.

## The screen's other hooks

`admin.profile.before`, `admin.profile.before_fields`,
`admin.profile.custom_fields`, `admin.profile.after_fields` and
`admin.profile.after` are unchanged from the screen this replaced, and fire in
the same order and the same places relative to the form. `custom_fields` remains
the point where a plugin renders inputs for values it added through
`user.profile_fields`.
