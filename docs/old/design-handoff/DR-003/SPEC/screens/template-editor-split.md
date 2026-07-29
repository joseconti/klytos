# Template — Editor split

**Used by**: Page editor · Block data · Translations (string editor) · AI images ·
Template preview shares its inspector but not its canvas (see `template-preview-matrix.md`).

Three columns: what you are editing, the thing itself, and its properties.

---

## 1. Anatomy

```
main (padding 0 — this template owns its own scroll regions)
├─ [optional] left rail   240px   block list / string list / locale list
├─ canvas                 flex 1  scrolls independently, padding 20–28
└─ inspector              300px   scrolls independently, --fondo-elevado, left border
```

The toolbar keeps the shell's breadcrumb and holds the save state and the publish action.
The status bar is unchanged.

**Canvas** — on the Page editor, a URL line in mono at the top (`klytos.io/pricing` with the
slug in `--color-acento` and an edit affordance), then the blocks as bordered cards with a
mono eyebrow for the block kind. On Block data, the slot editors. On Translations, the
source string and the target field side by side.

**Inspector** — collapsible sections, each with a mono uppercase eyebrow and a chevron.
Rows are label/control pairs at 30px.

---

## 2. States

**Default** — nothing selected: the inspector shows document-level properties (title, slug,
template, locale, visibility), not an empty panel.

**Block hover** — 1px `--borde-control` outline on the block card, plus its action row
appears in the top-right corner of the card. The actions are always in the DOM; hover
changes their opacity, so they remain focusable and reachable by keyboard.

**Block focus** — the block card is a focusable region (`tabindex="0"`,
`role="group"`, `aria-label="Heading block — Pricing that scales"`) and takes the 2px accent
ring. Tab moves between blocks; Tab again moves into a block's controls.

**Block selected** — 2px `--color-acento` border, `aria-selected` on the region, and the
inspector switches to that block's properties with its `<h2>` naming the block. Selection is
announced once through the status region.

**Editing text** — the block's text is a `contenteditable` region with
`role="textbox" aria-multiline` and an accessible name from the block's label. Plain-HTML
fallback: every block also has a "Edit as form" link that opens the same block in a
`record-form` page, so the editor is not the only path. **This is required**, not optional —
the admin must work with JS disabled.

**Autosave — in flight** — the toolbar's save state reads "Saving…" with
`aria-busy="true"` on its wrapper. No modal, no overlay, no blocking.

**Autosave — saved** — "Saved 14:03" in the toolbar, written once to the
`aria-live="polite"` region. It does not re-announce on every idle tick.

**Autosave — failed** — the state becomes "Not saved — retrying" in `--color-aviso`, and
after the second failure it becomes a `role="alert"`:
> "Changes are not being saved — the server returned 500. Your text is still in this window.
> **Retry now** · **Copy the content**"
The editor never discards the buffer on failure and never navigates away by itself.

**Loading** — server-rendered; the editor arrives with content. Media and AI generation are
the only async parts: each shows an indeterminate `role="progressbar"` in its own slot with
the text "Generating — about 8 seconds", and can be cancelled.

**Empty — no blocks** — the canvas shows the block inserter inline, not a modal:
> "This page has no blocks yet. Start with a heading, or let the copilot draft it. —
> **Add a block** · **Ask Klytos AI**"

**Empty — no selection in the inspector** — never blank: document properties, as above.

**Error — a block cannot render** — the block card renders in place with the `warning` glyph,
its kind, and the reason, and it is still selectable and deletable:
> "This `gallery` block references 3 assets that no longer exist. **Repair** · **Delete
> block**"

**Error — publish rejected** — the toolbar's publish opens an inline `role="alert"` panel
under the toolbar listing the blockers, each a link into the offending field:
> "This page was not published. 2 things need attention:
>  · The page has no `<h1>` — add a heading block, or set the title block as the page title
>  · Image in block 4 has no alt text — describe it, or mark it decorative"
The `<h1>` and alt-text blockers are **hard**: see `SPEC/accessibility.md` §10.

**Success** — "Published — klytos.io/pricing" in the status region, with the URL as a link.

**Disabled** — publish is disabled while blockers exist, with the count in its accessible
name ("Publish — 2 blockers"). Never a silently dead button.

---

## 3. Responsive

| Width | Behaviour |
|---|---|
| **≥ 1440** | Reference: rail 240 + canvas + inspector 300. |
| **1200–1439** | Inspector 280. Left rail collapses to 56px icons with labels on focus. |
| **900–1199** | Sidebar → icon rail. **Inspector becomes a right-side sheet**, opened by a toolbar toggle (`aria-expanded`, `aria-controls`), `role="dialog"` non-modal, focus moved in on open, `Esc` closes and returns focus. Canvas takes the full width. |
| **< 900** | Sidebar → drawer. Left rail → a `<select>` of blocks/strings, which is honest at that width. Inspector → full-screen modal sheet. Canvas is single-column, padding 16. Autosave state moves from the toolbar into the page status region so it is not truncated. |

The editor is usable at 320 CSS px. It is not pleasant there, and that is acceptable — but
nothing is unreachable.

---

## 4. Accessibility

- The canvas is `<section aria-label="Page content">`; each block is a
  `role="group"` with a name of the form "*Kind* block — *first words*".
- Reordering: drag exists, and **"Move up" / "Move down" are always present** in each block's
  action menu and post a normal form (2.5.7).
- The inspector is `<aside aria-label="Inspector">` — but note it is **not** the page's
  `complementary` landmark if the copilot dock is open; the dock owns that. Two
  `complementary` landmarks must be distinctly labelled, and they are.
- Inspector sections are `<h3>` + `aria-expanded` disclosure buttons controlling their panel.
- `contenteditable` regions carry a real accessible name and `aria-multiline`, and every one
  of them has the non-JS form fallback described above.
- Focus is never moved by autosave, by a block re-render, or by the copilot.
- The URL/slug line is a real form control with a visible label, not an inline-editable span.
- Headings: `<h1>` is the page's title (the record being edited), not the word "Editor".
