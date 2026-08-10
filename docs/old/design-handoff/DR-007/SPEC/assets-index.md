# Assets index — Klytos admin

Every asset in this bundle: what it is, its intrinsic size, every delivered format, and the
exact place and size it is used. If an asset is not in this list, it is not part of the
delivery.

**Formats.** Every logo and icon ships as **SVG and PNG**. SVG is the source and the thing
the admin actually loads; PNG is for the platforms and contexts that cannot take SVG
(favicon fallbacks, store listings, OG images, email). PNGs are rendered from the SVGs at
the exact sizes below — do not re-export them by hand, and do not upscale a smaller one.

**Colour.** Accent `#0E8074` in light contexts. The icon container is **never** recoloured,
never given effects, and the figure never sits on a background other than the accent (or the
gradient variant). The mono icon inherits `currentColor`.

---

## 1. Brand — logos and product icons

| File | Intrinsic | What it is | Where and at what size |
|---|---|---|---|
| `assets/klytos-favicon.svg` | 120 × 120 | Web favicon | Browser tab, bookmark, PWA. Ships at 32; scales to 512. |
| `assets/klytos-icon-android-background.svg` | 108 × 108 | Android adaptive icon — background layer, full bleed | Android only |
| `assets/klytos-icon-android-foreground.svg` | 108 × 108 | Android adaptive icon — foreground layer, inside the 66 % safe zone | Android only |
| `assets/klytos-icon-circle.svg` | 120 × 120 | Circular mask | Avatars, social profiles |
| `assets/klytos-icon-gradient.svg` | 120 × 120 | Hero gradient variant | Splash and marketing only. Never in the admin UI. |
| `assets/klytos-icon-mono.svg` | 120 × 120 | Monochrome figure, `currentColor`, no container | Menu bar, watermark, print. Set the colour on a wrapper. |
| `assets/klytos-icon-tile.svg` | 120 × 120 | Windows tile / Store, radius 12 (10 %) | Windows only |
| `assets/klytos-icon.svg` | 120 × 120 | Master product icon — squircle, solid accent | macOS / iOS / iPadOS app icon, and the 48px icon on the auth pages |
| `assets/klytos-lockup-dark-text.svg` | 250 × 72 | Icon + wordmark, **live text**, dark | Editing source only. |
| `assets/klytos-lockup-dark.svg` | 250 × 72 | Icon + wordmark, **outlined**, dark backgrounds | Primary lockup, dark. |
| `assets/klytos-lockup-text.svg` | 250 × 72 | Icon + wordmark, **live text**, light | Editing source only. |
| `assets/klytos-lockup.svg` | 250 × 72 | Icon + wordmark, **outlined**, light backgrounds | Primary lockup — sidebar brand row at 16px, wizard rail at 18px. |
| `assets/klytos-wordmark-dark-text.svg` | 200 × 72 | Wordmark, **live text**, dark backgrounds | Editing source only. |
| `assets/klytos-wordmark-dark.svg` | 200 × 72 | Wordmark, **outlined paths**, dark backgrounds | Primary wordmark, dark. |
| `assets/klytos-wordmark-text.svg` | 200 × 72 | Wordmark, **live Geist Bold text**, light backgrounds | Editing source only. Requires Geist Bold. |
| `assets/klytos-wordmark.svg` | 200 × 72 | Wordmark, **outlined paths**, light backgrounds | Primary wordmark. No font dependency. |
| `assets/packdesk-seal.svg` | 120 × 120 | The check alone — the "by PackDesk / work done" seal | Footer of the wizard’s Finish step; print marks. |

### PNG raster set

Full platform set, as chosen for this delivery. All PNGs live in `assets/png/`.

| Source | PNG sizes delivered | Why these |
|---|---|---|
| `klytos-favicon.svg` | 16, 32, 48, 180, 192, 512 | browser tab (16/32), Windows shortcut (48), `apple-touch-icon` (180), Android home screen / web manifest (192), maskable + store (512) |
| `klytos-icon.svg` | 128, 256, 512, **1024** | 1024 is the App Store / Icon Composer master; 128–512 for macOS iconset members |
| `klytos-icon-gradient.svg` | 512, 1024 | splash and marketing only |
| `klytos-icon-tile.svg` | 71, 150, 310 | Windows small, medium and wide tile |
| `klytos-icon-circle.svg` | 128, 256, 512 | avatars and social profiles |
| `klytos-icon-android-background.svg` | 108, 162, 216, 324, **432** | Android adaptive background at mdpi → xxxhdpi; 432 is the 108dp × 4 master |
| `klytos-icon-android-foreground.svg` | 108, 162, 216, 324, **432** | as above, foreground layer |
| `klytos-icon-mono.svg` | 128, 256, 512 in **black (`#1D1D1F`) and white** | `currentColor` cannot survive rasterisation, so both tones ship |
| `packdesk-seal.svg` | 128, 256 | print and email |
| `klytos-wordmark.svg` / `-dark` | @1x (200 × 72), @2x, @3x | email headers, OG images, anywhere SVG is not accepted |
| `klytos-lockup.svg` / `-dark` | @1x (250 × 72), @2x, @3x | as above |

Filenames: `<name>-<px>.png` for square assets, `<name>-<n>x.png` for the marks. **48 PNG
files.**

### The wordmark and the font

The wordmark and lockup are **bicolor**: `Kly` in the neutral that suits the background
(`#1D1D1F` on light, `#FFFFFF` on dark), `tos` in `#0E8074`. Geist Bold, tracking
`-1.6` at 56px (wordmark) and `-1.3` at 46px (lockup).

**The primary files carry outlined paths, not live text.** They render identically anywhere,
with no font dependency, which is what a brand asset has to do. The live-text versions ship
alongside as `*-text.svg` and exist so the wordmark can be re-set if the tracking or the
split point ever changes — they require Geist Bold to be installed and are not for
production use.

---

## 2. Fonts

| File | Family | Axis | Licence |
|---|---|---|---|
| `assets/fonts/Geist-Variable.woff2` | Geist | `wght` 100–900, normal | SIL OFL 1.1 |
| `assets/fonts/GeistMono-Variable.woff2` | Geist Mono | `wght` 100–900, normal | SIL OFL 1.1 |
| `assets/fonts/OFL.txt` | — | — | the licence text, shipped as the OFL requires |

**Weights the admin actually uses:** 400, 500, 600, 700. **Styles:** normal only — there is
no italic anywhere in the admin, and no italic face is delivered.

Two variable files cover all four weights, so there is nothing else to acquire. The
`@font-face` rules are in `tokens/fonts.css` and point at `../assets/fonts/…`.

**Target path in the project tree.** The `url()`s are relative to the folder `fonts.css`
itself sits in. Expected layout:

```
admin/tokens/fonts.css        ← the stylesheet
admin/assets/fonts/Geist-Variable.woff2
admin/assets/fonts/GeistMono-Variable.woff2
admin/assets/fonts/OFL.txt
```

With that layout `../assets/fonts/…` resolves unchanged. If `fonts.css` is served from a
different folder, change only those two `url()` values — nothing else in the bundle depends
on the font path.

**Source, for the record:** Geist and Geist Mono, by Vercel, released under the SIL Open Font
License 1.1 — `https://github.com/vercel/geist-font`. The OFL permits redistribution, so the
binaries ship here and nothing has to be fetched at build time.

---

## 3. UI icons

Delivered as **`assets/icons/klytos-ui-icons.svg`** — one SVG sprite, **87 `<symbol>` elements**, one per glyph the design uses. Line icons on the Material Symbols Outlined 24
grid: `stroke-width: 1.5`, round caps and joins, `fill: none`, colour from
`currentColor`. That is the weight-300 / `opsz 24` equivalent the design was drawn against.

This replaces the icon **font**. A drop-in subsetted `.woff2` cannot be produced without a
build step, and a handoff that requires the build to run `pyftsubset` is not a finished
handoff — so the design ships the geometry instead. No font to load, no FOIT, no
`font-variation-settings`, and the icons survive forced-colors mode because they are
`currentColor` strokes.

### How to use it

```html
<svg class="k-icon" width="16" height="16" aria-hidden="true">
  <use href="/admin/assets/icons/klytos-ui-icons.svg#ks-search"></use>
</svg>
```

```css
.k-icon { flex-shrink: 0; vertical-align: -0.15em; }
```

- **Sizes:** 15px in a table cell · 16–17px in a list row or toolbar · 18px in the sidebar ·
  22–26px in a drop zone or empty state. Never below 15px, never above 26px — the 1.5 stroke
  stops reading outside that band.
- **Decorative** (the overwhelming majority, because a label is next to them):
  `aria-hidden="true"`.
- **Meaningful** (icon-only controls): the *control* carries the `aria-label`, not the
  `<svg>`. The svg stays `aria-hidden`.
- Cross-document `<use href>` is same-origin only — serve the sprite from the admin's own
  origin. For the handful of icons in the shell, inlining the sprite once in
  `footer.php` is also fine and avoids a request.

### Glyph list, by screen file

The design uses **87 unique glyphs**. Shell glyphs (`chevron_right`, `logout`,
`search`, `close`, `expand_more`, `auto_awesome`, `arrow_upward`, `more_horiz`)
appear on every screen and are listed with the file where they first occur.


**The shell — sidebar and shell controls** — 36 glyphs

Normative in `SPEC/navigation.md`; that file, not a prototype, decides which glyph an item
carries. The 35 nav items:

```
account_circle        auto_awesome          category              checklist             cookie
dashboard_customize   data_object           description           dynamic_form          extension
format_align_left     forum                 group                 imagesmode            monitor_heart
monitoring            more_horiz            palette               perm_media            policy
receipt_long          schedule              sell                  shield                smart_toy
space_dashboard       system_update_alt     terminal              toll                  translate
tune                  verified_user         webhook               widgets               workspace_premium
```

Plus one glyph for a shell control: **`menu`**, the "Navigation" drawer button below 900px
(`navigation.md` §8). The rail's "Expand navigation" button reuses `chevron_right`, and the
theme toggle is text-only and carries no glyph.

**`Klytos Admin - Screens.dc.html`** — 19 glyphs

```
arrow_upward  auto_awesome  check  chevron_right  close  cloud_upload  edit  expand_less  expand_more  fingerprint  format_align_left  key  logout  mail  more_horiz  phonelink_lock  smart_toy  title  widgets
```

**`Klytos Admin - Screens 2.dc.html`** — 16 glyphs

```
auto_awesome  block  check  chevron_right  cloud_off  construction  delete  key_off  lock  logout  priority_high  progress_activity  search_off  shield_lock  stop  sync_problem
```

**`Klytos Admin - Screens 3.dc.html`** — 17 glyphs

```
check  check_circle  chevron_right  drag_indicator  edit_note  help  lock  logout  shield_lock  smart_toy  system_update_alt  terminal  toll  tune  unfold_more  verified  verified_user
```

**`Klytos Admin - Screens 4.dc.html`** — 10 glyphs

```
chevron_right  gavel  imagesmode  logout  search  unfold_more  visibility  vpn_key  warning  webhook
```

**`Klytos Admin - Screens 5.dc.html`** — 18 glyphs

```
arrow_forward  backup  chevron_right  close  content_copy  dashboard_customize  data_object  error  extension  lock  logout  pending  play_circle  schedule  shield_question  smart_toy  task_alt  unfold_more
```

**`Klytos Admin - Screens 6.dc.html`** — 13 glyphs

```
check_circle  chevron_right  dashboard_customize  data_object  description  devices  folder  logout  public  rule  sell  unfold_more  visibility
```

### Full sprite contents

```
account_circle        arrow_forward         arrow_upward          auto_awesome          backup
block                 category              check                 check_circle          checklist
chevron_right         close                 cloud_off             cloud_upload          construction
content_copy          cookie                dashboard_customize   data_object           delete
description           devices               drag_indicator        dynamic_form          edit
edit_note             error                 expand_less           expand_more           extension
fingerprint           folder                format_align_left     forum                 gavel
group                 help                  imagesmode            key                   key_off
lock                  logout                mail                  menu                  menu_book
monitor_heart         monitoring            more_horiz            palette               pending
perm_media            phonelink_lock        play_circle           policy                preview
priority_high         progress_activity     public                receipt_long          rule
schedule              search                search_off            sell                  shield
shield_lock           shield_question       smart_toy             space_dashboard       stop
sync_problem          system_update_alt     task_alt              terminal              title
toll                  translate             tune                  unfold_more           verified
verified_user         visibility            vpn_key               warning               webhook
widgets               workspace_premium
```

**Twenty symbols were added in this re-delivery**, taking the sprite from 67 to 87: the
nineteen the sidebar draws and had no symbol for — `account_circle`, `category`, `checklist`,
`cookie`, `dynamic_form`, `forum`, `group`, `menu_book`, `monitor_heart`, `monitoring`,
`palette`, `perm_media`, `policy`, `preview`, `receipt_long`, `shield`, `space_dashboard`,
`translate`, `workspace_premium` — plus `menu` for the drawer button. Same geometry as the
rest: Material Symbols Outlined 24 grid, 1.5 stroke, round caps and joins, `fill: none`,
`currentColor`. `menu_book` and `preview` ship even though `navigation.md` retires the *Guides*
item and makes *Template preview* a child screen — the sprite is the design's glyph set, and
both are drawn by prototypes.

If a screen ever needs a glyph that is not here, add a `<symbol>` to the sprite in the same
geometry — do not mix in a second icon set, and do not fall back to an emoji or a unicode
character. The admin has no emoji.

---

## 4. What is deliberately not in `assets/`

- **No PNG of the UI icons.** They are strokes that inherit `currentColor` and change colour
  per state; a raster would defeat the point.
- **No icon font.** See §3.
- **No imagery.** The admin has no photographs, no illustrations and no decorative graphics.
  Wireframe previews in the Blocks and Templates galleries are inline SVG generated from the
  block's own proportions, not assets.
- **No favicon `.ico`.** Modern browsers take PNG and SVG; if a legacy `.ico` is needed,
  build it from `klytos-favicon-16.png`, `-32.png` and `-48.png`.
