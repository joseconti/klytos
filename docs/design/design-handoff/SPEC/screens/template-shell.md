# Template — Shell

Every authenticated screen. Built once as `admin/templates/header.php` + `sidebar.php` +
`footer.php`; each page fills the content slot. The three auth pages do not use it.

Geometry, colours and type are exactly as the README's *The shell* section — unchanged. This
file specifies the shell's **states**, its **responsive behaviour**, and its
**accessibility**, which the README did not.

---

## 1. States

### Sidebar

**Nav item — default** — 30px tall, radius 6, 9px gap, 18px icon, `--type-nav-item`.
**Hover** — `--fila-hover`. **Focus** — 2px accent ring, inset by the item's own radius.
**Active** — `--fila-seleccion`, `--color-acento` text, `--type-nav-item-active`,
`aria-current="page"`. Colour is not the only cue: the active item is also the only one whose
weight is 500 and the only one carrying `aria-current`.
**With a count** — count right-aligned in `--type-numeric`, `--texto-sutil`; it is part of
the link's accessible name ("Comments, 4 pending").
**Count is zero** — the count is **absent**, not "0".

**Group caption** — mono uppercase, `--texto-sutil`, and it is a real `<h2>` labelling its
`<ul>`.

**What is in the nav** — the eight groups, their order, every item's label, glyph, target and
count, plugin placement and the capability rule are normative in **`SPEC/navigation.md`**. That
file decides contents; this one decides behaviour. Where a prototype's `navGroups` array
disagrees with it, `navigation.md` wins.

**Search field** — 30px, `⌘K` pill absolutely positioned right. Focusing it opens the command
palette; it is a real `<input type="search">` in a `<form role="search">` that also works as
a plain search submit with JS off.

**Account row** — 26px avatar, name + role, log-out link. The avatar is `aria-hidden`; the
name is text.

### Toolbar

**Breadcrumb** — `klytos.io` › optional section › current page. The last crumb is
`600 13px`, `aria-current="page"`, and **not a link**. The breadcrumb is
`<nav aria-label="Breadcrumb">` with an `<ol>`; the `›` separators are CSS, not text.

**Actions** — up to two `sm` buttons, secondary then primary. **Never three.** A third action
belongs in the page.

**Save state** (editor screens) — text between the breadcrumb and the actions: "Saved 14:03"
/ "Saving…" / "Not saved — retrying".

**Sticky** — the toolbar does not scroll away; the content area scrolls under it.

### Status bar

`~33px`, mono facts: left `Klytos 0.28.5 · PHP 8.3.11`, right `Rendered in 21 ms`.

**Degraded** — when a subsystem is unhealthy the left side gains one fact in
`--sobre-tinte-aviso` with a link: "Queue paused". It never becomes a banner and never grows
to two lines.

**Offline** — the browser is offline: the right side becomes "Offline — changes are not being
saved" in `--sobre-tinte-peligro`. The rest of the shell is unchanged; the admin does not
throw up a full-screen offline state.

### Theme toggle

A `<button aria-pressed>` in the account row, **text-only — no glyph** (`navigation.md` §8). It swaps `data-theme` on the wrapper and writes
a cookie so the **server renders the right theme on the next request** — there is no flash of
the wrong theme, ever. With JS off it is a link with a query parameter that sets the same
cookie. Its accessible name states the target: "Switch to dark mode".

### Command palette (`⌘K` / `Ctrl+K`)

**Closed** — nothing in the DOM but the trigger.
**Open** — `role="dialog" aria-modal="true" aria-label="Command palette"`, the shell behind it
`inert`, focus in the input, `Esc` closes and returns focus.
**Typing** — combobox semantics exactly as `SPEC/accessibility.md` §5.11.
**No results** — "No command matches *foo*. Try a page title, a setting name, or `help`."
**Loading** — never: the palette searches an index the server already sent.

### Skip links

First two focusable nodes in `<body>`: "Skip to content" (→ `#main`) and, on screens where
content precedes navigation in the DOM, "Skip to navigation" (→ `#k-nav`). Visually hidden
until focused, then pinned top-left over the toolbar with the standard focus ring. Both
targets carry `tabindex="-1"`.

---

## 2. Responsive

| Width | Sidebar | Toolbar | Status bar |
|---|---|---|---|
| **≥ 1440** | 232px, full | breadcrumb + 2 actions | full |
| **1200–1439** | 232px, full | as above | full |
| **900–1199** | **56px icon rail.** Labels hidden; each link keeps an `aria-label` including its count. Group captions become 1px `--separador` rules with an `aria-label`led `<ul>` (the `<h2>` stays, visually hidden). Active item keeps the accent background. A "Expand navigation" button (`ks-chevron_right`, icon-only) at the foot of the rail restores 232px and remembers the choice. | breadcrumb keeps the last two crumbs; the rest collapses to `…` which is a disclosure listing the full trail | right-hand fact only |
| **< 900** | **Off-canvas drawer.** A 40 × 40 "Navigation" button (`ks-menu`) becomes the first control in the toolbar (`aria-expanded`, `aria-controls`). Open: `role="dialog" aria-modal="true"`, 280px, focus trapped, `Esc` closes, focus returns to the button. The shell behind is `inert`. | breadcrumb collapses to the current page only; the two actions collapse to a "…" menu if both do not fit | hidden below 640px — its facts move to the foot of Health |

The 56px rail and the drawer are the **only** two responsive shell modes. There is no third.

At 200 % zoom on 1280 × 800 (→ 640px effective) the drawer mode applies and the whole admin
is operable. At 400 % (→ 320px) the same, with the toolbar's actions in the "…" menu.

---

## 3. Accessibility

Landmarks, focus order and heading rules for the shell are normative in
`SPEC/accessibility.md` §3.2 and §4.1. Summary of what the shell must emit:

```html
<body>
  <a class="k-skip" href="#main">Skip to content</a>
  <div class="k-shell" data-theme="light">
    <div class="k-sidebar">
      <a class="k-brand" href="index.php">…</a>
      <form role="search" aria-label="Search the admin">…</form>
      <nav id="k-nav" aria-label="Main">
        <h2 class="k-sr">Site</h2><ul>…</ul>
        <h2 class="k-sr">Content</h2><ul>…</ul>
        …
      </nav>
      <div class="k-account">…</div>
    </div>
    <header class="k-toolbar">
      <nav aria-label="Breadcrumb"><ol>…</ol></nav>
      <div class="k-toolbar-actions">…</div>
    </header>
    <main id="main" tabindex="-1">
      <h1>…</h1>
      …
      <p class="k-sr" role="status" aria-live="polite"></p>
      <div role="alert"></div>
    </main>
    <aside role="complementary" aria-label="Klytos AI" hidden>…</aside>
    <footer class="k-statusbar">…</footer>
  </div>
</body>
```

- Exactly one `banner` (`<header>`), one `main`, one `contentinfo` (`<footer>`) per page.
- The sidebar `<div>` is deliberately **not** a landmark: it already contains `navigation`,
  `search` and a group of links.
- The two live regions (`role="status"` polite, `role="alert"`) exist once, in the shell, and
  every screen writes into them. Screens do not create their own.
- No `tabindex` above 0 anywhere.
- `⌘K` is the only global shortcut; there are no single-character shortcuts (2.1.4).
- The copilot dock sits after `main` in the DOM in every mode.
