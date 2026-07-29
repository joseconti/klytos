# Design tokens — the admin's token layer

**Status: partially built.** The token stylesheets are placed and loaded (Phase 4 Step 4, stage 1).
The component layer that consumes them (`klytos-components.css`) is **not yet rewritten**, and no
screen has been ported. Until it is, the admin renders from the legacy `klytos-tokens.css` system and
the delivered tokens are loaded but largely unconsumed. This document describes what exists on disk
and in the chain today — not the finished redesign.

Source of truth for the values: `docs/design/design-handoff/SPEC/design-tokens.md` and the delivered
`tokens/*.css`. The build contract is `docs/BUILD-SPEC.md` §5.2 (token table) and §5.9 (integration).
**A value that is not in one of those is not invented here** — it is a Design Request.

## Where the files live

| What | Path | Count |
|---|---|---|
| Token stylesheets | `installer/admin/assets/css/tokens/` | 9 delivered, **8 loaded** |
| Fonts (Geist, Geist Mono, variable woff2) + licence | `installer/admin/assets/fonts/` | 3 |
| UI icon sprite | `installer/admin/assets/icons/klytos-ui-icons.svg` | 67 `<symbol>`s |
| Brand marks (SVG) | `installer/admin/assets/images/brand/` | 13 |
| Brand raster set (PNG) | `installer/admin/assets/images/brand/png/` | 48 |

Every one of those files is **byte-identical to the delivery**, with exactly one exception:
`tokens/fonts.css`, whose two font paths were repointed (see "The one edited file" below).

## Load order — it is not free

`installer/admin/templates/header.php` emits these eight, in this order, after `klytos-base.css` and
immediately before `klytos-components.css`:

```
tokens/colors.css
tokens/typography.css
tokens/spacing.css
tokens/effects.css
tokens/motion.css
tokens/glass.css        ← only --glass-fallback-* is used
tokens/fonts.css
tokens/klytos-admin.css ← LAST, on purpose
```

`klytos-admin.css` is last because it is the only Klytos-owned file of the set and it carries **real
enforcement rules**, not just declarations: the `:focus-visible` ring, `.k-hit-24` (the WCAG 2.5.8
24 × 24 target expander), the `prefers-reduced-motion` block and the `forced-colors` block. Loading
it earlier would let any later stylesheet quietly win against the accessibility layer.

**`tokens/platform.css` is delivered but deliberately NOT loaded.** `SPEC/design-tokens.md` lists
"platform.css in full" under *Inherited and not used — do not implement*: it carries native
densities, touch targets and window minimums, and the admin is a browser surface. It stays on disk so
the token set remains diffable against upstream PackDesk.

## The trap: `typography.css` declares two tokens twice

`--type-body` and `--type-caption` are each declared **twice** — the inherited PackDesk value first
(lines 48 and 50), the Klytos value in the block at the foot of the file (lines 91 and 95). CSS
resolves the later declaration, so the effective values are:

| Token | First declaration (loses) | Effective value |
|---|---|---|
| `--type-body` | `400 13px/18px` | **`400 13px/17px`** |
| `--type-caption` | `400 11px/14px` | **`400 11px/16px`** |

This is deliberate on Design's part — it avoids forking upstream — and it is correct, deterministic
CSS. It is a **build trap, not a defect**: a tool that greps for the token finds the losing value
first. **Never take the first grep match in this file.** Recorded as build rule 1 in
`docs/BUILD-SPEC.md` §5.0.

## The one edited file

`tokens/fonts.css` is the only delivered stylesheet this project modifies, and the modification is
authorised in writing: `SPEC/assets-index.md` §2 permits changing **exactly** the two font `url()`
values and states that nothing else depends on the font path.

The handoff assumed `admin/tokens/fonts.css` + `admin/assets/fonts/`. This project's stylesheets live
in `installer/admin/assets/css/`, so the paths resolve `../../fonts/` instead of `../assets/fonts/`.
Both `@font-face` rules list each file twice (a `woff2-variations` hint and a `woff2` fallback), so
that is **four** `url()` lines carrying two distinct paths — all four are repointed. A build note in
the file itself records why, so the inherited comment above it is not read as current.

Nothing else in the file changed, and nothing in any other token file changed.

## Icons

The icon source is the delivered sprite, served **same-origin** — cross-document `<use href>` is
same-origin only, so a CDN is not an option even if one were wanted:

```html
<svg class="ks" width="16" height="16" aria-hidden="true">
  <use href="<?php echo klytos_esc_url( $adminPath . 'assets/icons/klytos-ui-icons.svg' ); ?>#ks-description"></use>
</svg>
```

The 67 ids are `ks-` + the Material Symbols name (`ks-description`, `ks-terminal`, `ks-shield_lock`,
`ks-warning`, …). The authoritative list is the sprite file itself:

```sh
grep -o 'id="ks-[^"]*"' installer/admin/assets/icons/klytos-ui-icons.svg | sed 's/id="//;s/"//'
```

Read it before writing a `<use>` — a plausible-sounding id that is not in the sprite renders
**nothing**, silently, with no console error.

Sizes, as delivered: **15px** in a table cell · **16–17px** in a list row or toolbar · **18px** in the
sidebar · **22–26px** in a drop zone or empty state. Never below 15, never above 26.

Decorative icons — the overwhelming majority — are `aria-hidden="true"`. For an icon-only control the
**control** carries the `aria-label` and the `<svg>` stays `aria-hidden`. A glyph the sprite lacks is
added as a `<symbol>` in the same geometry: never a second icon set, never an emoji, never a Unicode
character. **The admin has no emoji.**

> **Font Awesome is still loaded** by `templates/header.php` and is still what the shipped screens
> use. `docs/BUILD-SPEC.md` §5.0 rule 4 retires it in favour of the sprite, but that cannot happen
> until the screens that reference it have been ported — removing the stylesheet now would strip the
> icons from every screen at once. It goes when the last consumer goes, not before.

## Extending the token layer from a plugin

```php
// Load a plugin's own token overrides after the core set — including after
// klytos-admin.css, so use this deliberately: it outranks the accessibility layer.
klytos_add_filter( 'admin.design_tokens', function ( array $files ) {
    $files[] = 'my-plugin-overrides.css';   // relative to assets/css/tokens/
    return $files;
} );
```

**`admin.design_tokens`** — filter. Receives the ordered array of filenames, each resolved against
`installer/admin/assets/css/tokens/`. Returning the array unchanged is a no-op; returning an empty
array unloads the whole token layer, including the accessibility enforcement in `klytos-admin.css`.

For a stylesheet that is *not* a token file — an ordinary plugin stylesheet loaded after the
component layer — use **`admin.stylesheets`** instead, which takes full URLs.

## Related

- `docs/BUILD-SPEC.md` §5.2 (the canonical token table), §5.7 (asset map), §5.9 (integration plan)
- `docs/design/design-handoff/SPEC/design-tokens.md` (origin, and what is inherited-but-unused)
- `docs/design/design-handoff/SPEC/accessibility.md` (what `klytos-admin.css` enforces and why)
