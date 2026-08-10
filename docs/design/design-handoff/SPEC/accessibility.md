# Accessibility — Klytos admin

**Status:** normative. This file is part of the contract. Where it disagrees with a
prototype screen, this file wins and the prototype is the older artefact.

**Target:** WCAG 2.2 **AA as the floor**, EN 301 549 (clauses 9, 11), and the European
Accessibility Act. Not "AA where convenient" — AA everywhere, with every documented
exception listed in §7 and nowhere else.

**Scope:** the Klytos admin panel in full (§1–§9), plus a short normative section for the
HTML Klytos *generates* (§10). The generated-HTML surface is specified in full by a
separate design request; §10 is the floor it may not go below.

**Baseline:** the current admin measures roughly 20–25 % against this target. The redesign
is where that is fixed. Nothing in §1–§9 is a "later pass".

---

## 1. Colour and contrast

Measured, not estimated. Every ratio below was computed from the delivered token values
with the WCAG 2.x relative-luminance formula. The full pair-by-pair table, both themes,
including the pairs that pass comfortably, is **`SPEC/color-contrast-audit.md`**.

`tokens/colors.css` used to point at `guidelines/auditoria-color-y-contraste.md`, which was
never shipped. That reference now points at the audit above, and the audit ships.

### 1.1 What failed, and what changed

The PackDesk palette is sacred (`colors.css`: *"ni un dígito hex"*). So **no base colour
changed. The pattern changed.** Two failures, two pattern fixes, both delivered as tokens
in `tokens/klytos-admin.css`:

| Failure | Measured | Fix |
|---|---|---|
| Badge/chip text painted in the raw semantic colour on that colour's own tint — 7 of 9 tones fail in light, 7 of 9 in dark | worst: `--color-reconectar` on its tint, light, **3.91:1**; `--color-peligro` on its tint over `--fondo-elevado`, dark, **3.37:1** | Text is painted in **`--sobre-tinte-*`**, a measured derivative of the same hue. The tint, the dot and the base colour are unchanged. Every tone now ≥ 4.5:1 on every admin surface. |
| `--texto-terciario` used for hints, eyebrows, column headers and timestamps — all real content at 10–11px | light **3.62:1** on white, **3.18:1** on `--fondo-ventana`; dark **2.75:1** on `--fondo-elevado` | `--texto-terciario` is **never used for text** in the admin. Support text uses **`--texto-sutil`** (light `#6D6D71`, dark `#939397`), ≥ 4.53:1 everywhere. `--texto-terciario` survives only for non-informative decoration that duplicates an adjacent label. |

A third finding, non-text: `--separador` (`rgba(0,0,0,.08)` = **1.19:1** light,
`rgba(255,255,255,.10)` = **1.37:1** dark) is fine as a *separator* between rows and
sections, and fails 1.4.11 as the **boundary of a control**. Inputs, checkboxes, radios,
switches and secondary buttons take **`--borde-control`** (light `#86868B` ≥ 3.18:1, dark
`#757579` ≥ 3.04:1). Row and section separators keep `--separador`.

### 1.2 Pairs that pass unchanged — do not "improve" them

- Primary-button text: `--sobre-acento` on `--color-acento` — **4.82:1** light, **9.03:1**
  dark. Every semantic fill with `--sobre-acento` on it clears 4.5:1 (worst 4.50:1,
  `--color-reconectar`, light).
- Body text `--texto-primario`: 14.79–16.83:1 light, 12.80–15.29:1 dark.
- Secondary text `--texto-secundario`: 4.46–5.07:1 light, 4.85–5.80:1 dark. **Constraint:**
  on `--fondo-ventana` light it is 4.46:1 — below AA. Secondary text is therefore not
  permitted on `--fondo-ventana`; on sunken surfaces use `--texto-sutil`.
- Focus ring `--color-acento` against every admin surface: 4.23–4.82:1 light, 6.40–7.64:1
  dark. Minimum required 3:1 (1.4.11). Passes with headroom in both themes.
- Status dot in the base semantic colour on its own tint: 3.91–4.83:1 light, 3.37–4.60:1
  dark. Required 3:1 (non-text). Passes — the **dot keeps the base colour**; only the text
  next to it moves to `--sobre-tinte-*`.

### 1.3 Colour is never the only channel

1.4.1. Every status carries a **word**, and every badge with a live meaning carries a
**shape** as well as a hue: the 6px leading dot is filled for live states, hollow for
settled ones. Log levels and HTTP codes are labelled (`ERROR 502`, not a red cell).
Diff rows in System integrity are `+`/`−` prefixed, not red/green blocks. See §6.

### 1.4 Disabled

Disabled controls are exempt from 1.4.3. Klytos does not use the exemption: disabled text
holds a **3:1 floor** (`--texto-deshabilitado`), because a disabled field's label is still
information a person needs. Disabled state is announced by `disabled` / `aria-disabled`,
never by colour alone, and every disabled primary action carries a one-line reason next to
it ("Publish — the page has no slug yet").

---

## 2. Table semantics — the decision

**Decision: (a) a real `<table>`, with the grid layout applied to it, *and* the explicit
ARIA table roles written on every element.** Not a bare grid, not a grid with ARIA bolted
on, not "add ARIA".

Why both. A `display:grid` div soup exposes no row/column relationships to assistive
technology. But applying `display:grid` (or `flex`, or `contents`) to `<table>`,
`<thead>`, `<tr>`, `<td>` **strips their implicit roles in Chromium and WebKit** — the
markup is right and the accessibility tree is still wrong. So the semantic element and the
explicit role must both be present: the element survives a CSS regression, the role
survives the `display` change.

Every list screen in the admin is a data table: Pages, Users, Plugins, Comments,
Transactions, Options, Scheduled actions, Logs, Taxonomy terms, Webhook deliveries,
Sessions, Activated domains, Update history, Modified files. All of them use this.

### 2.1 The exact markup

```html
<table role="table" class="k-table" aria-labelledby="pages-h">
  <caption id="pages-h" class="k-table-caption">Pages — 34 results, page 1 of 3</caption>

  <thead role="rowgroup">
    <tr role="row">
      <th role="columnheader" scope="col" class="k-col-check">
        <input type="checkbox" aria-label="Select all pages on this page">
      </th>
      <th role="columnheader" scope="col" aria-sort="ascending">
        <a href="?sort=title&dir=desc">Title</a>
      </th>
      <th role="columnheader" scope="col">Status</th>
      <th role="columnheader" scope="col">Template</th>
      <th role="columnheader" scope="col">Locale</th>
      <th role="columnheader" scope="col" class="k-num">Last edit</th>
      <th role="columnheader" scope="col"><span class="k-sr">Actions</span></th>
    </tr>
  </thead>

  <tbody role="rowgroup">
    <tr role="row" aria-selected="true">
      <td role="cell"><input type="checkbox" aria-labelledby="row-42-title"></td>
      <th role="rowheader" scope="row" id="row-42-title"><a href="page.php?id=42">Pricing</a></th>
      <td role="cell"><span class="k-badge k-badge--exito">Published</span></td>
      <td role="cell">Marketing</td>
      <td role="cell"><span lang="en">English</span></td>
      <td role="cell" class="k-num"><time datetime="2026-07-21T14:03">21 Jul, 14:03</time></td>
      <td role="cell"><a href="…" class="k-hit-24" aria-label="More actions for Pricing">…</a></td>
    </tr>
  </tbody>
</table>
```

Rules, all mandatory:

- `role="table"` on `<table>`; `role="rowgroup"` on `<thead>` and `<tbody>`; `role="row"`
  on every `<tr>`; `role="columnheader"` + `scope="col"` on every `<th>` in `<thead>`;
  `role="cell"` on every `<td>`.
- The column that names the record is a **`<th role="rowheader" scope="row">`**, not a
  `<td>`. One per row. This is what makes "Published, column Status, row Pricing" work.
- **`<caption>`** on every table, and it carries the result count and page position. It is
  visible (it is the table card's heading row); it is not a screen-reader-only crumb.
- Sortable columns: the `<th>` carries `aria-sort` (`ascending` / `descending` / omitted),
  and the control inside it is an `<a href>` with the sort query — sorting is a link, as
  §Interactions requires.
- Select-all checkbox: `aria-label`, and its indeterminate state is set with the DOM
  `indeterminate` property **and** `aria-checked="mixed"`.
- Row checkboxes are labelled by the row header's `id` (`aria-labelledby`), never by an
  invented "Select row 3".
- Icon-only action cells carry `aria-label` naming the **record**, not the icon: "More
  actions for Pricing".
- Numeric and timestamp cells: `class="k-num"` (mono, right-aligned) and a real `<time
  datetime>` where it is a time.
- **The grid layout goes on the table elements**, not on wrappers:
  `.k-table{display:grid}` `.k-table thead,.k-table tbody{display:contents}`
  `.k-table tr{display:grid;grid-template-columns:…;gap:12px}`. Column widths, gap, row
  padding and truncation are exactly as the README's Table entry specifies — the visual
  result is byte-identical to the prototypes.
- Scroll containers: when the table scrolls horizontally below 1200px, the scroll container
  gets `tabindex="0"` and `role="group"` with an `aria-label`, so keyboard users can reach
  the scroll.
- Empty result: **one row spanning all columns** containing the empty-state sentence and
  its action — not a table replaced by a div. `aria-live="polite"` on the caption announces
  the new count after a filter.

### 2.2 What is *not* a table

Three surfaces look like lists and are not data tables, and they must not carry table
roles: the **Assets** media grid (`role="list"` / `listitem`, each a link), the **Blocks**
and **Templates** galleries (same), and the **sidebar nav** (`<nav>` + `<ul>`). Reason:
they have one dimension, no columns, no header row, and nothing to associate.

---

## 3. Focus

### 3.1 The visible indicator

`outline: 2px solid var(--color-acento); outline-offset: 2px`, `border-radius: inherit`.
Applied via `:focus-visible`. Measured against every admin surface in §1.2 — 4.23:1 worst
case, above the 3:1 that 1.4.11 requires, and the 2px ring at 2px offset satisfies 2.4.11
(Focus Not Obscured) and 2.4.13 (Focus Appearance): the ring is at least 2px thick, encloses
the whole control, and has ≥ 3:1 against both the control and what is behind it.

Never `outline: none` without a replacement of equal or better visibility. The focused
*field* additionally gets an accent border — that is decoration on top of the ring, not
instead of it.

Nothing may cover the focused element: the copilot dock, the floating bulk-action bar and
the status bar all sit in the layout flow or reserve space, never on top of focusable
content. When the bulk bar appears, the content area gains bottom padding equal to the
bar's height.

### 3.2 Focus order per screen

One order, every authenticated screen, matching DOM order — there is no `tabindex` above 0
anywhere in the admin:

1. **Skip link** — first focusable node in `<body>`, visually hidden until focused, then
   pinned top-left over the toolbar. `<a class="k-skip" href="#main">Skip to content</a>`.
   A second skip link, "Skip to navigation", follows it on screens where content comes
   first in the DOM. Both are real anchors; the target has `tabindex="-1"`.
2. **Sidebar** (`banner`-adjacent `navigation`): brand link → search field → nav items in
   visual order, group by group → footer account link → log out.
3. **Toolbar**: breadcrumb links (current page is `aria-current="page"` and not a link) →
   secondary button → primary button.
4. **Content** (`main`): H1 → filters → table/form in visual order.
5. **Status bar** (`contentinfo`): normally no focusable content; when it holds a link
   (e.g. "Rendered in 21 ms" → Health), that link is last.
6. **Copilot dock**, when open, is `complementary` and sits **after** `main` in the DOM.
   Opening it moves focus to its heading; closing it returns focus to the launcher.

Overlays (command palette, inline confirm, sheet): focus moves in on open, is trapped while
open, `Esc` closes, and focus returns to the trigger. Nothing else on the page is reachable
while an overlay is open (`inert` on the shell, with `aria-hidden` as the fallback).

### 3.3 Keyboard

Everything is reachable and operable from the keyboard; the admin has no pointer-only path.
Because filters, pagination, sorting and tabs are links, they are keyboard-native for free.

- `⌘K` / `Ctrl+K` opens the command palette; `Esc` closes it. This is the only global
  shortcut, it is single-key-free (no bare-letter shortcuts anywhere — 2.1.4 satisfied by
  construction), and it is listed in the palette itself.
- Tables: `Tab` moves between the interactive things in a row, not cell by cell. No roving
  tabindex, no grid navigation — these are static tables, not editable grids.
- The terminal is a real `<textarea>`-backed prompt with `Esc` releasing focus to the page;
  the log tail is a scrollable region with `tabindex="0"`.
- Drag-to-reorder (blocks, nav items, taxonomy terms) always has a keyboard equivalent:
  "Move up" / "Move down" buttons in the row's action menu, posting a normal form. 2.5.7
  is met by an alternative, not by making drag accessible.

---

## 4. Landmarks and headings

### 4.1 Landmarks, every authenticated screen

| Landmark | Element | Accessible name |
|---|---|---|
| `banner` | `<header class="k-toolbar">` | — (implicit, one per page) |
| `navigation` (primary) | `<nav id="k-nav">` in the sidebar | `aria-label="Main"` |
| `navigation` (breadcrumb) | `<nav>` inside the toolbar | `aria-label="Breadcrumb"` |
| `search` | `<form role="search">` in the sidebar | `aria-label="Search the admin"` |
| `main` | `<main id="main" tabindex="-1">` | — |
| `complementary` | `<aside>` copilot dock, when open | `aria-label="Klytos AI"` |
| `contentinfo` | `<footer class="k-statusbar">` | — |

The sidebar as a whole is **not** a landmark: it contains three (`navigation`, `search`,
and the account row). Wrapping it in a fourth adds noise.

Exactly one `banner`, one `main`, one `contentinfo` per page. Auth pages have `main` only —
no shell, no nav, no status bar.

Secondary in-page navs (Settings sub-nav, Profile sections, Content-model tabs) are
`<nav aria-label="Settings sections">` etc. — every extra nav is labelled, or it is not a
nav.

### 4.2 Headings

- **Exactly one `<h1>` per screen**, in `main`, and it is the page title that also appears
  as the last breadcrumb crumb. The toolbar's copy of the title is `aria-hidden="true"`
  when it duplicates the H1 verbatim.
- `<h2>` = card headings and major sections. `<h3>` = groups inside a card. No level is
  skipped, ever, and heading level is never chosen for size — size comes from
  `--type-card-heading`, not from the tag.
- Section eyebrows (mono uppercase 10px) are **not headings** unless they are the only
  label for a region; when they are, they are a real `<h3>` styled with `--type-eyebrow`.
- The sidebar's group captions ("Site", "Content", "Design"…) are `<h2 class="k-sr-group">`
  inside `<nav>`, each labelling its own `<ul>` — that is what makes the nav navigable by
  heading.
- Table column headers are `<th>`, never headings.
- Every screen's H1 is listed in `SPEC/manifest.md`, per screen.

---

## 5. Components — name, role, state

The rule for all of them: **name from content where content exists**, `aria-label` only for
icon-only controls, `aria-labelledby` when an existing element already says it. Never both.
Never a `title` attribute as the only name.

### 5.1 Button
`<button type="button|submit">` for actions; `<a href>` for navigation. A thing that
changes the URL is a link; a thing that changes state is a button. Icon-only buttons carry
`aria-label` naming the action and its object ("Delete the Pricing page"). Toggle buttons
(density, dock) carry `aria-pressed`. Buttons that open an overlay carry
`aria-expanded` + `aria-controls`. Loading: `aria-busy="true"` and the label changes to the
progressive form ("Publishing…"); the button stays focusable, it does not disappear.
Destructive inline confirm: the second state is the **same button**, its label changed to
"Confirm delete", `aria-live="polite"` on its wrapper so the change is announced.

### 5.2 Badge
Not interactive. `<span class="k-badge">` with **plain text inside** — the text is the
accessible name, so no role and no ARIA is needed or wanted. Where the badge reflects a
value that changes without a page load (queue state, sync), its container is
`aria-live="polite"`. The 6px dot is `aria-hidden="true"`. **Target size does not apply**
(§7).

### 5.3 Chip (filter)
An `<a href>` inside `<nav aria-label="Filter by status">` — filters are links. The
selected chip carries `aria-current="true"`. It is **not** a tab, **not** a
`role="button"`, and **not** a checkbox. Multi-select filter chips (rare: Logs level) are
real `<input type="checkbox">` with a styled `<label>`, inside a `<fieldset>` whose
`<legend>` is the filter name.

### 5.4 Switch
`<button type="button" role="switch" aria-checked="true|false">` with a visible `<label>`
associated by `aria-labelledby`. A switch takes effect **immediately** and says so; if it
does not (it is part of a form that must be saved), it is **not a switch — it is a
checkbox**. That distinction is enforced per screen in the manifest. A switch never has an
`aria-label` that repeats "toggle".

### 5.5 Checkbox
Real `<input type="checkbox">`, visually 13px, hit area 24 × 24 (§7). Always a real
`<label for>`, or `aria-labelledby` pointing at the row header in a table. Groups of
related checkboxes sit in `<fieldset><legend>`. Tri-state select-all uses the
`indeterminate` property **and** `aria-checked="mixed"`.

### 5.6 Radio
Real `<input type="radio">`, 14px, hit area 24 × 24, always inside
`<fieldset><legend>` — a radio group with no legend has no name. Arrow keys move between
options and select; that is native, do not intercept it.

### 5.7 Field
`<label for>` is mandatory and visible — the admin has no placeholder-as-label anywhere.
Hint text is `<p id="x-hint">` linked with `aria-describedby`. Error text is
`<p id="x-err">` also in `aria-describedby`, plus `aria-invalid="true"` on the control.
Required fields carry `required` and the word "Required" in the hint — never an asterisk
alone. `autocomplete` is set on every field that has a standard token (email, current-
password, new-password, organization, url). Mono fields (slug, key, path) carry
`spellcheck="false"` and `autocapitalize="off"`.

### 5.8 Progress
Determinate: `<progress max="100" value="62">` with an `aria-labelledby` pointing at its
label, and the percentage rendered as text next to it — never inside the bar only.
Indeterminate: `role="progressbar"` with no `aria-valuenow`, plus a text status. Long
operations additionally announce start and finish through the page's single
`aria-live="polite"` status region; they do not spam it per percent.

### 5.9 Stat card
A figure, not a heading: `<div class="k-stat">` containing `<p class="k-stat-value">` and
`<p class="k-stat-label">`, associated with `aria-labelledby` so the value is read with its
label ("1,284 — page views, 30 days"). The icon tile is `aria-hidden="true"`. If the stat
links somewhere, the **whole card** is the `<a>`, not a chevron in its corner.

### 5.10 Copilot dock
`<aside role="complementary" aria-label="Klytos AI">`, after `main` in the DOM. The
transcript is `role="log" aria-live="polite" aria-relevant="additions"` — new messages are
announced, the whole transcript is not re-read. Tool-call rows are `<li>` in an
`<ol aria-label="Tool calls">` with their status as text ("Ran search_pages — 3 results"),
not as a spinner alone. The composer is a `<textarea>` with a real label; `Enter` sends,
`Shift+Enter` newlines, and both are stated in the field's hint. Dock mode changes
(docked / collapsed / floating / full) are `aria-pressed` toggles and move focus to the
dock heading on open, back to the launcher on close. The dock **never** takes focus on its
own, and never while the user is typing elsewhere.

### 5.11 Command palette
`role="dialog" aria-modal="true" aria-label="Command palette"`, focus trapped, `Esc`
closes, focus returns to whatever opened it. The input is a combobox:
`role="combobox" aria-expanded="true" aria-controls="k-palette-list"
aria-activedescendant="<id of the highlighted option>"`, `aria-autocomplete="list"`. The
list is `role="listbox"`, items `role="option"` with `aria-selected`. Result count is
announced with `aria-live="polite"` ("7 results"). Arrow keys move the active option;
focus itself never leaves the input.

### 5.12 Everything else
Card = plain container, no role. Code/payload block = `<pre><code>` with
`tabindex="0"` and an `aria-label` naming what it is, because it scrolls. Avatar =
`aria-hidden` when a name is next to it, `alt` with the person's name when it is alone.
Toast = the single `role="status"` region; destructive confirmations never live in a toast.

---

## 6. Errors, status and feedback

2.4.6, 3.3.1, 3.3.3, and 1.4.1 together:

- **Never colour alone.** An error is: `aria-invalid` on the control, an icon with a
  meaning (`error`, not a red dot), the word, and a sentence. A red border is the fourth
  channel, not the first.
- **Never a bare code.** "The order changed in the store — *Refresh*". Every error names
  what happened and what to do. `SPEC/screens/*.md` carries the exact string per screen.
- **Form submission errors** render an error summary at the top of `main`, inside
  `role="alert"`, listing each failed field as a link to that field, and focus moves to the
  summary on load. The per-field messages stay too.
- **Log and status screens** encode level in text first: `ERROR`, `WARN`, `INFO`, `DEBUG`
  as a mono label, tint second. HTTP codes are read as text. The System-integrity diff uses
  `+` / `−` prefixes and `<ins>` / `<del>`, so a monochrome print is still readable.
- **One `aria-live="polite"` status region per page**, in the shell, for autosave, filter
  counts, tail updates and toasts. One `role="alert"` region for failures. Two regions
  total — more than that and they interrupt each other.
- Autosave never uses a modal and never steals focus; it writes "Saved 14:03" into the
  status region.
- Session timeout warns at least 20 seconds before it acts, and can be extended without
  losing data (2.2.1). MCP session revocation is confirmed, not instant-on-click.

---

## 7. Target size (2.5.8, AA — 24 × 24 CSS px)

The visual sizes in the README do not change. The **hit areas** do, via `.k-hit-24`
(`tokens/klytos-admin.css`), which centres a 24 × 24 pseudo-element on the control without
moving a pixel of the drawing.

| Element | Drawn | Hit area | How |
|---|---|---|---|
| Checkbox | 13 × 13 | **24 × 24** | `.k-hit-24` on the `<label>` wrapper; row spacing already ≥ 24px, so nothing reflows |
| Radio | 14 × 14 | **24 × 24** | same |
| Table-cell action icon | 15 × 15 | **24 × 24** | the `<a>` is 24 × 24 with the glyph centred; row height 40–48px absorbs it |
| Sidebar nav item | 232 × 30 | **232 × 30** | passes: 30 ≥ 24 in the constrained dimension |
| `sm` button | ≥ 56 × 28 | passes | 28 ≥ 24 |
| Switch | 38 × 22 | **38 × 24** | label row is 24px tall; the switch's own label is part of the target |
| Chip | ≥ 44 × 24 | passes | exactly 24 |
| Status dot, badge | 6 / 20 tall | **exempt** | not interactive — 2.5.8 applies to pointer targets only |
| Breadcrumb link | text | **24px tall** | the crumb row is 24px; spacing between crumbs ≥ 24px centre-to-centre |
| Inline link inside a sentence | text | **exempt** | 2.5.8 "inline" exception |
| Console-stream line (Logs) | line-width × 19 | **exempt** | the third exception below — `.k-hit-24` must **not** be applied to it |

Two of the three documented exceptions are explicitly permitted by 2.5.8: **inline links in
running text**, and **the drag handle** in reorderable lists — which is exempt as an
equivalent alternative exists (§3.3) and is drawn at 24 × 24 anyway.

### 7.1 The third exception — a console-stream line

Added in answer to **DR-007**. A line in `SPEC/screens/template-console-stream.md` is set in
`--type-code` at 12px/19px and, on Logs, is a `<button>` spanning the line. 19px is under the
rule and `.k-hit-24` cannot resolve it: stacked 19px rows leave no undisturbed 24px to centre
a pseudo-element in, so enlarging one line's hit area only moves the overlap onto its
neighbour. **Do not apply `.k-hit-24` to a stream line.**

The exception rests on 2.5.8's *essential* clause — the target's size is set by the content it
is made of, one emitted record per line, and any line-height that reached 24px would remove
roughly a quarter of the lines visible in the same 60vh. It is the same reasoning by which §3
of the console-stream template already holds the one permitted horizontal-scroll surface in
the admin: this template shows machine output at the density the output has.

It is granted only with all four of these, and a build that drops one has produced a defect:

1. **The line is the only target in its row.** Nothing else in a 19px row is clickable — which
   is why §2's per-line copy affordance is withdrawn (see the template's §2 for where copy
   lives now). Two 19px targets in one row is not what this exception permits.
2. **The unconstrained dimension is the full line.** The target spans the stream's whole
   width, and hover and selected feedback paint that whole width, so the thing being aimed at
   is visibly the whole row.
3. **The stream is fully operable from the keyboard.** `↑`/`↓` move between lines inside the
   focusable stream group, `Enter` or `Space` selects, and the detail panel is reachable with
   no pointer precision at all. Selection is never a pointer-only path.
4. **It applies only to verbatim machine output** — the five consumers named at the head of
   the console-stream template. A list of records that merely looks like a stream is a table
   and follows the table row sizes above.

No other exception exists. If a build produces a smaller target outside these three, that is a
defect.

---

## 8. Text scaling, zoom, reflow, forced colors

- **1.4.4 Resize text:** every size in `tokens/typography.css` is px by design (the admin is
  a fixed-density tool), and the whole admin must remain fully usable at **200 % browser
  zoom** at 1280 × 800. Verified at 1440 × 976 → 200 % → 720 × 488 effective: sidebar
  collapses to the icon rail, tables scroll horizontally inside their labelled scroll
  container, nothing is clipped and nothing overlaps.
- **1.4.10 Reflow:** no horizontal scrolling of the *page* down to 320 CSS px width at
  400 % zoom. Data tables are the permitted exception (they may scroll horizontally within
  their container); the page around them does not.
- **1.4.12 Text spacing:** the layout survives line-height 1.5×, paragraph spacing 2×,
  letter-spacing 0.12em, word-spacing 0.16em with no loss of content. Practical consequence:
  no fixed-height text containers, no `overflow:hidden` on anything that holds a sentence.
  Table cells truncate with ellipsis — allowed, because the full value is in the row's link
  target and in a `title`… **no**: because the full value is reachable on the record's own
  page. Truncation never hides the only copy of a value.
- **Forced colors / high contrast:** the `@media (forced-colors: active)` block in
  `tokens/klytos-admin.css` is normative. Tints vanish in forced-colors mode, so every
  badge, chip and card gains a `1px solid currentColor` border, the status dot becomes
  `CanvasText`, and the focus ring becomes `3px solid CanvasText`. Nothing in the admin
  depends on a background tint to be understood. Icons are `currentColor` line art
  (`assets/icons/klytos-ui-icons.svg`) and survive forced-colors unchanged.
- **Dark mode is not an accessibility feature** — both themes meet AA independently, and
  neither is the "accessible one".

---

## 9. Motion

`prefers-reduced-motion: reduce` is a **rule**, not a mention, and it ships as CSS
(`tokens/klytos-admin.css` §Reducir movimiento). Under it:

- All transitions and animations collapse to ≤ 1ms. Nothing is removed, nothing moves.
- The sync point **stops pulsing and stays lit** — the state is still visible, the motion is
  gone.
- The copilot dock, palette and sheets appear and disappear without slide or fade.
- The terminal and log tail still stream (that is content, not decoration), but
  auto-scroll becomes a jump, not a smooth scroll.
- Nothing in the admin auto-plays, flashes, or moves for longer than 5 seconds
  (2.2.2, 2.3.1). There is no content above 3 flashes per second anywhere.

Standard motion, when it is allowed: `cubic-bezier(.32,.72,0,1)` in, `(.16,1,.3,1)` out,
120–280ms — unchanged from the README.

---

## 10. The HTML Klytos generates — normative floor

Klytos is a CMS: the EAA applies to what it publishes, not only to the panel. The
front-end contract is a separate design request; this is the floor it may not go below, and
the admin must make compliance the **default output**, not an option a user has to find.

1. **Every block emits landmarks and headings correctly.** A block never emits an `<h1>`
   unless it is the page title block; heading level is derived from position in the
   document outline, and the editor shows the resulting level. The editor **warns on a
   skipped level** and refuses to publish a page with no `<h1>`.
2. **Images.** The alt field is a first-class field in the asset and the image block, never
   an "advanced" accordion. Decorative is an explicit choice (`alt=""` + `role=
   "presentation"`), and it must be chosen — an empty alt with no choice recorded blocks
   publication. AI-generated images get an AI-drafted alt that the author must confirm.
3. **Forms** (contact, search, comment): visible `<label>`, `autocomplete` tokens, errors
   identified in text with `aria-describedby`, no placeholder-as-label. Same rules as §5.7.
4. **The cookie banner is the hardest case and gets the strictest rule.** It is a
   `role="dialog" aria-modal="true"` with a real heading, focus moved into it on load and
   trapped, `Esc` = reject non-essential, and **"Reject all" is a control of the same
   prominence, size and level as "Accept all"** — same component, same size, no ghost
   button, no colour trick. It never covers the focused element, and the site is operable
   by keyboard before a choice is made.
5. **Language.** `<html lang>` is emitted per locale, and per-locale slugs and translated
   fragments carry `lang` on the fragment (3.1.1, 3.1.2). The translations screen makes the
   locale of every string explicit.
6. **Templates ship a skip link** and one `<main>`. `template-preview.php`'s check column
   verifies exactly that: one `h1`, no skipped levels, a skip link, a `main`, every image
   with an alt decision, and colour-contrast of the theme's token pairs. A template that
   fails a check cannot be set as the site default.
7. **Contrast of the generated theme** is checked with the same measured method as §1, in
   the Design (theme) screen: the theme editor shows the ratio next to every text/background
   pair it defines and refuses to save a pair below 4.5:1 without an explicit override that
   is recorded.

---

## 11. How this is verified

Not "run axe and ship". Per screen, before it is called done:

1. **Automated:** axe-core clean at AA on every screen, both themes. Catches maybe 30 % —
   it is the floor of the floor.
2. **Keyboard-only pass:** unplug the mouse. Reach everything, operate everything, see
   focus at all times, escape every overlay.
3. **Screen reader pass:** one Windows (NVDA + Firefox) and one macOS (VoiceOver + Safari)
   pass per template, not per screen. Read the table by row and column. Confirm the H1, the
   landmarks, and the live regions.
4. **Zoom pass:** 200 % at 1280 × 800, and 400 % at 1280 (→ 320 CSS px).
5. **Forced-colors pass:** Windows high contrast, both themes.
6. **Reduced-motion pass:** the OS setting on, every animated surface.

The per-template checklist and the per-screen deltas live in `SPEC/screens/` and
`SPEC/manifest.md`.
