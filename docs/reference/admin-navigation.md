# Admin primary navigation

The admin sidebar's contents — the eight groups, their order, every item's
label, glyph, target, count and capability, plugin placement, and the
capability rule — are **normative in the design handoff**, at
`docs/design/design-handoff/SPEC/navigation.md`. That file declares itself the
source of truth over any prototype's `navGroups` array, and it is right to: the
prototypes each drew a partial nav and contradicted each other.

`installer/core/admin-nav.php` is its implementation.
`installer/admin/templates/sidebar.php` draws what the model returns and decides
nothing about the contents.

Built in Phase 4 Step 4, stage 2 (the shell). Loaded from `App::boot()` beside
`admin-gate.php`, for the same reason: the test suite and `scripts/keel-verify`
reason about the navigation without booting an admin request, and a plugin
registering an item needs the capability → group table.

---

## The rule that is the opposite of the rest of the admin

An item the person lacks the capability for is **not rendered** — not disabled,
not greyed, not shown with a reason (`navigation.md` §7).

Inside a screen the admin does the opposite: a control the person cannot use is
shown **disabled with the reason in its accessible name**. The distinction is
argued rather than asserted — in a screen the person is looking at a specific
object and the absence of its action would be a mystery; in the navigation
nothing is missing, because there was never anything there.

Consequences the code must honour, all of them implemented here:

- Filtering happens **server-side, before render**. The markup for a hidden item
  is never sent.
- A group whose items are all hidden renders **nothing at all** — no caption, no
  empty `<ul>`.
- **Overview is always present** for every authenticated person, so someone with
  nothing else can still land somewhere.
- A count is computed under the same capability filter as its own screen.

Verified by driving all four seeded roles: owner 8 groups / 35 items, admin 8 /
30, editor 6 / 12, viewer 4 / 4, with **zero** `disabled` or `aria-disabled`
markers at any role and Overview present in all four.

---

## `klytos_admin_nav_groups()`

The navigation, ready to render.

Applies, in order: the deferral filter, the capability filter, plugin placement,
the counts, and the empty-group rule.

**Returns** `array<int, array{id: string, caption: string, items: array}>` —
ordered groups, each guaranteed to have a non-empty `items` list. `caption` is a
catalogue key (`nav.group.site`), not a rendered string.

Each item carries `id`, `label` (a catalogue key unless `literal` is true),
`glyph` (a sprite symbol id), `url`, `entry` (its `SPEC/manifest.md` number), and
`count` when it has a non-zero one.

```php
foreach ( klytos_admin_nav_groups() as $group ) {
    echo '<h2>' . klytos_esc_html( __( $group['caption'] ) ) . '</h2>';
    foreach ( $group['items'] as $item ) {
        echo '<a href="' . klytos_esc_url( $item['url'] ) . '">'
           . klytos_esc_html( __( $item['label'] ) ) . '</a>';
    }
}
```

## `klytos_admin_nav_definition()`

The full item definition, **before** capability filtering and before counts.
One row per item in `navigation.md` §2, in the order that file lists them.

Capabilities are taken from the admin gate map (`admin-gate.php`) rather than
restated, so a nav item can never be visible to someone the gate would refuse.

An item marked `'deferred' => true` is specified by `navigation.md` but not
rendered, because its screen does not exist yet. Two are: **Comments** (entry 14)
and **Health** (entry 22), both deferred out of Phase 4 by **D-072**, with the
user choosing on 2026-07-29 to omit the nav items rather than ship a 404 on the
primary navigation. They stay here, described in full, so restoring each one is
deleting a single line.

## `klytos_admin_nav_group_order()`

The eight group ids, top to bottom: `site`, `content`, `design`,
`intelligence`, `monetisation`, `compliance`, `system`, `account`.

The order is fixed and is not personalisable. A group is never reordered, never
collapsed by default and never renamed by a plugin.

## `klytos_admin_nav_capability_group( string $capability ): string`

Maps a plugin's declared primary capability onto the group that owns it
(`navigation.md` §6).

| Primary capability | Group |
|---|---|
| `content.*` | Content |
| `design.*` | Design |
| `ai.*`, `mcp.*` | Intelligence |
| `payments.*` | Monetisation |
| `privacy.*`, `consent.*` | Compliance |
| anything else, or none declared | System |

A plugin **cannot** choose Site or Account: Site is the install's own state and
Account is the person's, and neither is a plugin's to occupy.

> `navigation.md` §6 illustrates this rule with a *Klytos SEO* plugin that this
> install does not have, and describes *Klytos Forms* as declaring
> `content.forms`. The plugin actually shipped here declares `forms.manage`, so
> it lands in **System** by the "anything else" row. The rule is right and the
> example is not — recorded in `docs/BUILD-SPEC.md` §1e and deliberately not
> bounced back to Design.

## `klytos_admin_nav_counts(): array`

Item id → count, **non-zero values only**. A zero count is absent, not `0`.

A count is a call to action, not a magnitude, which is why Transactions, Logs
and Options carry none by design.

**Not every count `navigation.md` specifies is wired.** Wired today, each from a
verified source: `pages`, `tasks`, `assets`, `content-model`, `blocks`,
`templates`, `scheduled`. The rest return nothing rather than a guess — an
absent count and a zero count look identical to the reader, so a fabricated zero
would be a lie that cannot be seen. What is missing, and the surface each one
needs, is listed in `docs/BUILD-SPEC.md` §5.9.

## `klytos_admin_nav_sprite_ids(): array`

Every `<symbol id>` in the delivered icon sprite, keyed for `isset()`.

A `<use>` pointing at an id the sprite does not contain renders **nothing** —
silently, with no console error. That is L-030, and it is why a plugin's chosen
glyph is checked against this set before it is printed rather than trusted.

---

## Hooks

| Hook | Kind | Purpose |
|---|---|---|
| `admin.nav_groups` | filter | The finished navigation, after capability filtering. The last word — an item added here bypasses the placement and capability rules, so a plugin that wants those applied uses `admin.nav_plugin_items` instead. |
| `admin.nav_plugin_items` | filter | Plugin-contributed items, before placement. Each: `id`, `label`, `glyph`, `url`, `capability`. |
| `admin.nav_counts` | filter | The counts, after the core sources have run. A value of 0 or less removes the count. |
| `admin.nav_active_item` | filter | Which item carries `aria-current="page"`, given the current screen. |

### Plugin placement

A plugin contributes **one** item. It sorts after every core item in its group
and alphabetically among plugin items, with no separator and no visual
difference — inside the shell a plugin screen is a screen. Beyond **five** items
in one group, the first five show and a sixth, *More plugins*, links to the
Plugins screen, which is always the complete list. A plugin item never carries a
count.

If a plugin names a glyph that is not in the sprite, or one already used by a
core item in its group, the shell falls back to `ks-extension`.

```php
klytos_add_filter( 'admin.nav_plugin_items', function ( array $items ): array {
    $items[] = [
        'id'         => 'my-plugin',
        'label'      => 'My plugin',
        'glyph'      => 'ks-extension',
        'url'        => klytos_admin_url( 'plugin-page.php?plugin=my-plugin' ),
        'capability' => 'content.my_plugin',   // also chooses the group
    ];
    return $items;
} );
```

### The legacy `admin.sidebar_items` filter

Still fires, and still means what it meant. Klytos is released and plugins use
it, so it is bridged in `klytos_admin_nav_plugin_items()`: an item it contributes
that is not a core id is treated as a plugin item and routed through the same
placement rule. Core ids are dropped from its result, because the core
navigation is now defined by `navigation.md` rather than by that array.

### Two filters that are deprecated, and why

`admin.sidebar_section_order` and `admin.sidebar_section_label` no longer fire.

They cannot be honoured without contradicting `navigation.md` §1, which states
the group order is fixed and not personalisable and that a group is **never
renamed by a plugin**. Firing a filter and then ignoring its return value would
be worse than not firing it: the listener would run, appear to work, and change
nothing.

They are documented here as removed with their replacement rather than deleted
silently, because they were released. Use `admin.nav_groups` for anything either
of them was used for. Recorded in `docs/decisions.md` **D-076**.
