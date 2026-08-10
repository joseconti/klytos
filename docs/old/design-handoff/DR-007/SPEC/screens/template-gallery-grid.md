# Template — Gallery grid

**Used by**: Assets (media library) · Blocks (block gallery) · Templates (template library).

A one-dimensional collection of things you recognise by sight. **Not a data table** — see
`SPEC/accessibility.md` §2.2.

---

## 1. Anatomy

```
main (padding 20)
├─ h1
├─ filter row        chips (kind / category / usage) + search + sort
├─ [Assets only] drop zone   1.5px dashed --borde-control, radius 10, padding 18
└─ grid              repeat(auto-fill, minmax(180px, 1fr)), gap 14
   └─ tile           radius 10, overflow hidden, --fondo-elevado, card shadow
      ├─ preview     96px (assets) / 120px wireframe (blocks, templates)
      ├─ name        --type-body, truncated to one line
      └─ meta        --type-caption, --texto-sutil — size, usage count, category
```

Previews are wireframes for blocks and templates (grey rectangles at the real proportions),
real thumbnails for assets. A file-type pill sits top-right on asset previews at
`--type-numeric` size on a 42 %-black plate — which is the one place a fixed rgba is
correct, because it sits over arbitrary imagery.

---

## 2. States

**Default** — as drawn.

**Hover** — tile lifts by nothing (no transform); the preview area dims 6 % and the tile's
actions fade in. Actions are in the DOM at all times.

**Focus** — the tile is a single `<a>`; the ring goes around the whole tile at 2px offset.
Where a tile has more than one action, the tile is a `<div>` containing a primary `<a>` plus
an actions `<button>`, and each takes its own ring — never a nested-interactive tile.

**Selected** (Assets, multi-select) — 2px `--color-acento` border and a checked checkbox in
the top-left, 24 × 24 hit area. Selection raises the same bulk bar as `list-table`.

**Drag-over** (Assets) — the drop zone's border goes `--color-acento` and its text becomes
"Drop to upload". A keyboard user reaches the same thing through "Choose files", which is
always present.

**Uploading** — the tile appears immediately with a determinate `<progress>` over its
preview and the filename below; the percentage is text next to the bar. Failure replaces the
bar with the reason and a retry, and the tile stays until dismissed.

**Loading** — server-rendered. Pagination is a link. There is no infinite scroll anywhere in
the admin: infinite scroll steals the status bar and breaks the back button.

**Empty — nothing uploaded / no blocks installed**
> Assets: "No files yet. Drop images, video or documents here — or let the copilot generate
> an image. — **Choose files** · **Generate an image**"
> Blocks: "No blocks are registered. Blocks come from the core set and from plugins. —
> **Open Plugins**"
> Templates: "No templates in this theme. A template decides how a kind of page is laid
> out. — **Create a template**"

**Empty — filtered to nothing** — "No assets match *video* in *Unused*. — **Clear filters**"

**Error — the preview cannot be rendered** — the tile keeps its name and meta and shows the
`imagesmode` glyph plus "Preview unavailable" in `--texto-sutil`. The asset is still
openable, still deletable. A broken thumbnail never removes the record.

**Error — the library cannot be read** — the grid is replaced by the same error card as
`list-table`, with the same sentence shape.

**Success** — "4 files uploaded." in the page status region; the new tiles are first in the
grid and each is marked `aria-current="true"`… **no** — `aria-current` is for navigation.
The new tiles are simply first, and the sentence says how many.

**Disabled tile action** — an asset in use cannot be deleted: the action is `disabled`, its
name reads "Delete — used on 3 pages", and the usage count in the meta line is a link to the
list of those pages.

---

## 3. Responsive

The grid is intrinsically responsive (`auto-fill, minmax(180px, 1fr)`), so the breakpoints
mostly concern the shell:

| Width | Columns (content area) | Other |
|---|---|---|
| **≥ 1440** | 6 | reference |
| **1200–1439** | 5 | — |
| **900–1199** | 4 | sidebar → icon rail; filter row wraps |
| **< 900** | 2 → 1 below 420px | sidebar → drawer; drop zone stacks its button under its text; the file-type pill stays legible (it never scales below 9px, which is the one sub-11px value in the admin and is exempt as it duplicates the extension in the name) |

At 400 % zoom (320 CSS px) the grid is one column and the tile preview is 100 % width.

---

## 4. Accessibility

- The grid is `<ul role="list">` with `<li>` tiles — an explicit `role="list"` because
  `list-style:none` removes list semantics in Safari.
- The `<ul>` has `aria-label` naming the collection and the count ("Assets, 128 files").
- Each tile's accessible name is the **filename or block name**, not "image". Meta is inside
  the same link, so it is read after the name.
- Asset previews are `<img alt="">` — decorative, because the filename is the name and the
  alt of the *asset itself* is a property being managed, not a label for the tile. The alt
  the author wrote is shown as text in the tile's meta line, and its absence is shown as a
  warning chip "No alt text" — which is how §10.2 gets enforced from the library side.
- Wireframe previews (blocks, templates) are inline SVG with `aria-hidden="true"`.
- Filter chips: links, `aria-current="true"` on the active one, in a labelled `<nav>`.
- Bulk selection mirrors `list-table` exactly, including `aria-checked="mixed"`.
- Upload progress is a real `<progress>` with the percentage as text; the file's status is
  announced once at start and once at finish.
- Headings: `<h1>` the collection; category group headings, where used, are `<h2>` and each
  `<ul>` is `aria-labelledby` its heading.
