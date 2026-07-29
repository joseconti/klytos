# Template — List table

**Used by** (data in `SPEC/manifest.md`): Pages · Users · Plugins · Comments · Transactions ·
Options · Scheduled actions · Taxonomies (terms) · Webhooks (delivery log) · Updates
(history) · System integrity (modified files) · Profile (sessions) · Licence (domains) ·
Logs (rows, inside `console-stream`).

Specify once here. A consumer screen only records its columns, its filters, its empty
sentence and its deltas.

---

## 1. Anatomy

```
main (padding 20)
├─ h1                              --type-page-title, margin-bottom 14
├─ filter row            height 42, gap 8      ← chips + search + bulk affordance
└─ table card            --fondo-elevado, radius 10, shadow 0 1px 3px rgba(0,0,0,.10)
   ├─ caption row        13px 16px             ← title + result count + right-side action
   ├─ header row         8px 16px, --type-column-header, uppercase, --texto-sutil
   ├─ rows               9–11px 16px, 1px --separador between
   └─ pagination row     10px 16px             ← links, never buttons
```

Grid on the table elements, not on wrappers — exact rule in `SPEC/accessibility.md` §2.1.
`grid-template-columns` is per screen; `gap: 12px`; numeric and action columns right-align;
every cell that can be long truncates (`overflow:hidden; text-overflow:ellipsis;
white-space:nowrap`).

A checkbox column, when the screen supports bulk actions, is the first column at 28px.

---

## 2. States

**Default** — as drawn. Zebra striping is not used; rows are separated by 1px `--separador`.

**Row hover** — `background: --fila-hover`, 120ms. Hover is decoration: it never conveys
anything that is not also in the row.

**Row focus** — the focused *link* inside the row takes the 2px accent ring at 2px offset.
The row itself is not focusable and never gets a ring.

**Row selected** — `background: --fila-seleccion`, checkbox checked, `aria-selected="true"`
on the `<tr>`. Selecting ≥ 1 row raises the bulk bar: 48px, pinned to the bottom of the
content area, `--fondo-elevado` + card shadow, holding "*n* selected", the actions, and
"Clear". The content area gains 48px bottom padding so the bar never covers a focused row.
The bar is a `<form>`; its actions are submit buttons.

**Sort active** — the `<th>`'s link shows the direction chevron, `aria-sort` set. Sorting is
a page load.

**Filter active** — the selected chip carries `aria-current="true"` and
`--fila-seleccion` + accent text; a "Clear filters" link appears at the end of the chip row
whenever any filter is not the default.

**Loading** — this is a multi-page app: a list *page* never loads asynchronously, so there is
no skeleton on first paint. The only loading state is the bulk-action post: the bar's button
goes `aria-busy="true"`, label becomes the progressive form ("Deleting 3 pages…"), the bar's
other controls become `disabled`, and the rows stay readable. No overlay, no spinner over the
table.

**Empty — no records at all** — the table keeps its header row and renders a single row
spanning all columns, 120px tall, centred: a 20px `--texto-sutil` icon, one sentence, one
primary action. Never a bare "No results".
> "No pages yet. A page is a URL with blocks on it — **Create the first page**."

**Empty — filtered to nothing** — different sentence, different action, and it never
suggests creating a record:
> "No pages match *Draft* in *French*. — **Clear filters**"
The result count in the caption updates and is announced through the page status region.

**Error — the list could not be loaded** — the card renders an error row in place of the
rows: `error` icon in `--color-peligro`, the sentence, and a retry link that is a plain
reload of the current URL.
> "The page index could not be read — the database returned an error. **Try again** ·
> **Open Health**"

**Error — a bulk action partly failed** — the list reloads with a `role="alert"` summary
above the table listing what failed and why, per record, each linking to that record. The
records that succeeded are not mentioned individually; the summary opens with "7 of 9 pages
were deleted."

**Success** — after any write, the list reloads with a `role="status"` line above the table:
"Pricing was published." It is text in the flow, not a floating toast, so it survives a
screenshot and a screen reader. It disappears on the next navigation, not on a timer.

**Disabled row action** — an action a record cannot take is rendered `disabled` with
`aria-disabled="true"` and a reason in its accessible name ("Delete — this page is the site
home"), never hidden. Hiding an action teaches nothing.

---

## 3. Responsive

Design viewport 1440 × 976. Behaviour at each required breakpoint:

| Width | Sidebar | Table | Other |
|---|---|---|---|
| **≥ 1440** | 232px, full | all columns, as drawn | reference rendering |
| **1200–1439** | 232px, full | as drawn; the widest text column absorbs the loss | filter row may wrap to two lines |
| **900–1199** | **56px icon rail** — labels hidden, `title` + `aria-label` retained, active item keeps the accent background; counts move into the item's `aria-label` | table scrolls horizontally inside a `tabindex="0"` `role="group"` container labelled "Pages table, scrollable"; the row-header column is **sticky left** with a 1px `--separador` right edge | toolbar keeps breadcrumb's last two crumbs only |
| **< 900** | **off-canvas drawer**, opened by a 40 × 40 button first in the toolbar; `role="dialog" aria-modal="true"` when open, focus trapped, `Esc` closes | table becomes **stacked cards**: one `<article>` per record, the row header as its `<h3>`, remaining columns as a `<dl>` of label/value pairs. This is a real markup change, so the ARIA table roles go away with the table — a definition list is honest about what it is | bulk bar becomes full-width, actions stack |

At 200 % zoom on 1280 × 800 the effective width is 640px → the < 900 rules apply and
everything remains operable. At 400 % (320 CSS px) the stacked cards reflow with no
horizontal page scroll.

---

## 4. Accessibility

Landmarks and focus order: `SPEC/accessibility.md` §3.2, §4.1 — unchanged by this template.

Deltas this template owns:

- The table is a real `<table>` with explicit ARIA roles. Full markup: §2.1 of the
  accessibility spec. Do not ship the grid-of-divs version.
- `<caption>` carries the result count and page position and is the visible heading row.
- One `<th role="rowheader" scope="row">` per row — the column that names the record.
- The caption is `aria-live="polite"` so a filter change announces the new count once.
- Checkbox column: select-all is `aria-label`led and uses `indeterminate` +
  `aria-checked="mixed"`; row checkboxes are `aria-labelledby` the row header.
- Icon-only actions name the record: "More actions for Pricing".
- Pagination is a `<nav aria-label="Pagination">` of links; the current page is
  `aria-current="page"` and is not a link.
- Filter chips are links in `<nav aria-label="Filter by status">`, selected one
  `aria-current="true"`. They are not tabs and not buttons.
- Every action icon is 24 × 24 (`.k-hit-24`); the 13px checkbox likewise.

**H1** is the plural noun of the record, sentence case, no count: "Pages", not "Pages (34)".
The count lives in the caption where it can update.
