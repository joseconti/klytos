# Handoff: Klytos Admin — full redesign (42 screens)

## Overview

Klytos is a PHP, server-rendered, AI-first CMS. Its admin lives at an obscured path
(`/<random>-admin/`) and is a **classic multi-page application**: one HTTP request per
action, no SPA, no client router. This bundle is a complete visual redesign of that
admin — 42 screens, 41 of them drawn across six HTML files — plus the Klytos logo set as SVG.

The design brief the screens answer:

- The admin is a **browser** experience, not a native app. The chrome in the mocks is a
  browser window on purpose.
- Everything works in **light and dark**. Every screen in the bundle has a theme toggle;
  both must ship.
- The interface is in **English**, sentence case, impersonal, no emoji.
- Numbers, IDs, slugs, paths, versions and money are always **monospaced**.
- An AI copilot is a first-class citizen, but it never blocks the plain HTML path.

---

## About the design files

The files in this bundle are **design references written in HTML**. They are prototypes
that show the intended look and behaviour — they are **not production code to copy**.

The task is to recreate these designs inside Klytos's own environment: PHP templates
under `admin/`, its existing CSS (`admin/assets/css`, `css/klytos-components.css`) and
its vanilla-JS helpers. Do not introduce React, a bundler, or a component framework —
the prototypes use one only because that is what the design tool renders. Everything in
them maps to plain markup plus CSS custom properties.

Where a prototype shows a component (button, badge, card, switch, chip, progress bar,
avatar), build or reuse the equivalent in `css/klytos-components.css` rather than
inlining styles.

## Fidelity

**High fidelity.** Colours, type, spacing, radii and copy are final. Sizes below are the
values to implement, not approximations. Layout should be recreated pixel-close at a
1440 × 976 viewport, then allowed to reflow — with the four breakpoints in each
`SPEC/screens/template-*.md` as the contract below it.

## Where the detail lives

This README is the overview. The normative detail is in `SPEC/`:

- **`SPEC/accessibility.md`** — WCAG 2.2 AA / EN 301 549 / EAA contract: measured contrast,
  table semantics, focus order, roles and states per component, landmarks and headings,
  target sizes, scaling, forced colors, reduced motion, error identification, and the floor
  for the HTML Klytos generates. **Read it before writing markup.**
- **`SPEC/color-contrast-audit.md`** — every colour pair, both themes, with its ratio.
- **`SPEC/design-tokens.md`** — what is inherited from PackDesk, what Klytos owns, what to
  ignore.
- **`SPEC/screens/`** — 11 templates, each with every applicable state (default, hover,
  focus, active, disabled, loading, empty, error, success) and its responsive behaviour.
- **`SPEC/manifest.md`** — all 44 entries mapped to their template, data and deltas. Entry 44,
  the Dashboard, is specified there and is not drawn in a prototype file.
- **`SPEC/assets-index.md`** — every asset, size, format and use.
- **`SPEC/open-questions.md`** — zero unresolved.

---

## Design tokens

All colours come from the PackDesk design system, Paleta B "Mediterráneo". **Never
hard-code a hex** — every value below already exists as a CSS custom property, scoped by
`[data-theme]` on a wrapper element. The theme flips by swapping that one attribute.

### Colour

| Token | Light | Dark | Used for |
|---|---|---|---|
| `--color-acento` | `#0E8074` | `#3CC3B2` | Primary buttons, active nav, links, focus ring |
| `--color-info` | `#0E7490` | `#3FB7D4` | Neutral informational badges |
| `--color-exito` | `#257D36` | `#56C96E` | Verified, published, healthy, settled |
| `--color-aviso` | `#9A6300` | `#E8A93C` | Retrying, modified, held for review |
| `--color-peligro` | `#C03A35` | `#E6685F` | Failed, destructive actions, 5xx codes |
| `--color-offline` | `#6E6E73` | `#98989D` | Paused, draft, private |
| `--color-sync` | `#3672D9` | `#6FA0EF` | In-flight / background work |
| `--color-conflicto` | `#7C3AED` | `#A78BFA` | Conflicts, third-party origin |
| `--color-reconectar` | `#C2570B` | `#F08C3E` | Retry / reconnect |

Tints (badge and pill backgrounds) are the same base colour at **11 %** in light and
**19 %** in dark: `--tinte-acento`, `--tinte-exito`, `--tinte-aviso`, `--tinte-peligro`,
`--tinte-offline`, `--tinte-sync`, `--tinte-conflicto`, `--tinte-info`.

**Text on a tint is not the base colour.** Most tones fail WCAG AA when the raw semantic
colour is painted on its own tint — measured in `SPEC/color-contrast-audit.md`. Badge and
chip text uses `--sobre-tinte-*` from `tokens/klytos-admin.css` instead; the tint, the dot
and the base colour are unchanged. Two more tokens from the same file are mandatory:
`--texto-sutil` replaces `--texto-terciario` for **all** text (tertiary fails AA in both
themes), and `--borde-control` is the border of inputs, checkboxes, radios and switches
(`--separador` is 1.19:1 and stays a separator, not a control boundary).

Neutrals:

| Token | Light | Dark |
|---|---|---|
| `--fondo-ventana` | `#F0F0F2` | `#1E1E20` |
| `--fondo-contenido` | `#FFFFFF` | `#232326` |
| `--fondo-elevado` | `#FFFFFF` | `#2C2C2E` |
| `--glass-fallback-sidebar` | `#F2F2F4` | `#26262A` |
| `--glass-fallback-toolbar` | `#F7F7F9` | `#2A2A2E` |
| `--texto-primario` | `#1D1D1F` | `#F5F5F7` |
| `--texto-secundario` | `#6E6E73` | `#98989D` |
| `--texto-terciario` | `#86868B` | `#6E6E73` |
| `--separador` | `rgba(0,0,0,.08)` | `rgba(255,255,255,.10)` |
| `--fila-hover` | `rgba(0,0,0,.045)` | `rgba(255,255,255,.06)` |
| `--fila-seleccion` | `rgba(14,128,116,.12)` | `rgba(60,195,178,.24)` |
| `--sobre-acento` | `#FFFFFF` | `#0B0B0C` |

`--sobre-acento` matters: in dark mode the accent fill is light, so text on a primary
button is nearly black, never `#fff`.

### Type

Two families only. `--font-ui` is **Geist**, `--font-mono` is **Geist Mono**.

Every role has a token. **This table and the Klytos section of `tokens/typography.css` are
identical** — if they ever diverge, that is a defect, not a choice. Never hard-code a size.

| Role | Value | Token |
|---|---|---|
| Page H1 (in-content) | `700 22px/28px`, `letter-spacing:-0.01em` | `--type-page-title` (+ `--type-page-title-tracking`) |
| Card heading (h2) | `600 13px/16px` | `--type-card-heading` |
| Section eyebrow | `600 10px/14px` mono, `letter-spacing:0.06em`, uppercase | `--type-eyebrow` (+ `--eyebrow-tracking`) |
| Table column header | same as eyebrow | `--type-column-header` |
| Body / table cell | `400 13px/17px` | `--type-body` |
| Body / table cell, mono | `400 12px/17px` | `--type-body-mono` |
| Secondary / hint | `400 11px/16px` | `--type-caption` |
| Nav item | `400 13px/16px` | `--type-nav-item` |
| Nav item, active | `500 13px/16px` | `--type-nav-item-active` |
| Toolbar title | `600 13px/16px` | `--type-toolbar-title` |
| Badge / chip | `500 11px/16px` | `--type-badge` |
| Numeric cell | mono, `500 12px/17px`, right-aligned | `--type-numeric` |
| Code / payload block | mono, `400 12px/19px` | `--type-code` |

Eyebrows, column headers, hints and timestamps are coloured `--texto-sutil`, **not**
`--texto-terciario` — see the colour note above.

Nothing in the admin is below 11px. Anything a person types or a machine emits — email,
slug, path, key, version, hash, price, timestamp — is mono.

### Spacing, radius, elevation

- Content padding: **20px**. Card padding: **20px**, or `0` when the card owns a table.
- Gap between cards: **14px**. Gap inside a card's stack: **12–15px**.
- Table row padding: `9–11px 16px`. Header row: `8px 16px`.
- Radius: control **6px**, card **10px**, popover **12px**, pill/dot **999px**,
  status square **3px**, icon tile **7–9px**.
- Card shadow: `0 1px 3px rgba(0,0,0,.10)`. No coloured borders, no left accent bars.
- Focus: `outline: 2px solid var(--color-acento); outline-offset: 2px` — always visible.
- Hover: rows `--fila-hover`; controls lighten/darken over **120ms**.
- Motion: `cubic-bezier(.32,.72,0,1)` standard, `(.16,1,.3,1)` exit, 120–280ms, with a
  reduced-motion variant.

---

## The shell

Every authenticated screen except the auth pages uses one shell. Build it once as a PHP
layout (`admin/templates/header.php` + `sidebar.php` + `footer.php`) and let each page
fill the content slot.

```
┌──────────┬────────────────────────────────────────────────┐
│ sidebar  │ toolbar  50px                                  │
│ 232px    ├────────────────────────────────────────────────┤
│          │ content   flex:1, overflow-y:auto, padding:20  │
│          │                                                │
│          ├────────────────────────────────────────────────┤
│          │ status bar  ~33px                              │
└──────────┴────────────────────────────────────────────────┘
```

**Sidebar** — `232px`, `--glass-fallback-sidebar`, right border `--separador`.
Top to bottom: brand row (`Klytos` lockup at 16px + version in mono, tertiary, right),
search field with a `⌘K` mono pill absolutely positioned right, then the nav.
Nav is grouped; each group has a mono uppercase caption and its items are `30px` tall,
`6px` radius, `9px` gap, `18px` Material Symbols icon, optional right-hand count in mono.
The active item gets `background: --fila-seleccion`, `color: --color-acento`, weight 500.
Footer row: 26px avatar, name + role, logout icon.

Nav groups used across the design: **Site · Content · Design · Intelligence ·
Monetisation · Compliance · System · Account**. Plugins may add one entry.

**Toolbar** — `50px`, `--glass-fallback-toolbar`, bottom border. Left: breadcrumb
(`klytos.io` › optional section › **current page**, the last one `600 13px`). Right: up
to two `sm` buttons, secondary then primary.

**Status bar** — mono facts, left `Klytos 0.28.5 · PHP 8.3.11`, right `Rendered in 21 ms`.
It is not decoration; it is the fastest way to see the server is healthy.

**Auth pages** (log in, verify, reset password) drop the shell entirely: centred 400px
column on `--fondo-ventana`, app icon at 48px, heading, one card, one reassurance strip.

---

## Component inventory

Reusable pieces, in the order you will need them. Each already exists visually in the
prototypes; give each a class in `klytos-components.css`.

**Button** — `sm` = 28px tall, default = 34px, auth = 38px. Radius 6. Variants:
`primary` (accent fill, `--sobre-acento` text), `secondary` (transparent, 1px
`--separador`, primary text), `destructive` (danger text/border). Sentence case.

**Badge** — 20px pill, `500 11px`, tint background, matching colour text. Optional 6px
leading dot for live status. Tones map 1:1 to the semantic colours.

**Chip** — 24px filter pill. Selected = `--fila-seleccion` + accent text.

**Card** — `--fondo-elevado`, radius 10, `0 1px 3px rgba(0,0,0,.10)`. Two flavours:
padded (20px) for forms and stats, unpadded for tables — a table card puts its heading
row at `13px 16px`, then a header row, then rows separated by 1px `--separador`.

**Table** — a real `<table>`, **with the grid layout applied to the table elements**, and
with the explicit ARIA table roles written out. Both: `display:grid` on `<table>` strips the
implicit roles in Chromium and WebKit, so the semantic element and the explicit role must
both be present. `.k-table{display:grid}`, `thead,tbody{display:contents}`,
`tr{display:grid;grid-template-columns:…;gap:12px}` — the rendered result is identical to a
grid of divs. `role="table"` / `rowgroup` / `row` / `columnheader` / `cell`, one
`<th role="rowheader" scope="row">` per row, a `<caption>` carrying the result count. **The
exact markup, element by element, is `SPEC/accessibility.md` §2.1 — do not improvise a
partial version, which is worse than none.** Numeric and action columns right-align.
Selected row = `--fila-seleccion` + `aria-selected`. Truncate with `overflow:hidden;
text-overflow:ellipsis; white-space:nowrap` on every cell that can be long.
The Assets, Blocks and Templates galleries and the sidebar nav are **not** tables (§2.2).

**Switch** — 38 × 22. **Checkbox** — 13px square, radius 3, 1.5px border, accent fill
when checked. **Radio** — 14px circle, same treatment.

**Field** — label `600 12px`, control 34px tall, radius 6, 1px `--separador`, background
`--fondo-ventana` (sunken inside a card), hint `400 11px` tertiary underneath. The
focused field gets an accent border **and** the 2px focus ring.

**Stat card** — 32px rounded icon tile in a semantic tint, then `600 20px` mono value
over an `11px` tertiary label.

**Progress** — 8px track, radius 999, accent fill.

**Code / payload block** — `--fondo-ventana` panel, mono `12px/19px`, one line per
element, `white-space:pre`, syntax coloured only by role: keys and structure tertiary,
values secondary, the one line that matters accent.

**Empty and error states** — never a bare code. Always a sentence plus the action, e.g.
"The order changed in the store — *Refresh*".

---

## Screens

41 drawn screens in six files. Each file exposes a chip row (screen switcher) and a
light/dark toggle above the browser frame.

### `Klytos Admin - Screens.dc.html` — core content and configuration
| Screen | Purpose |
|---|---|
| Pages | Page list: status, template, locale, last edit; bulk actions |
| Page editor | Block editor with inspector and publish flow |
| Design (theme) | Theme tokens, palette, type scale |
| Assets | Media library grid with usage counts |
| Users | Roles, capabilities, invitations |
| Security | 2FA, passkeys, CSP, integrity score |
| Analytics | 30-day traffic, top pages, referrers |
| MCP | Application passwords, tool exposure, client config |
| Settings | Site-wide options |

### `Klytos Admin - Screens 2.dc.html` — auth, copilot, moderation
| Screen | Purpose |
|---|---|
| Log in | Email + password, centred auth layout |
| Verify | Second factor: authenticator code, recovery link |
| AI chat | Copilot conversation with tool calls and context chips |
| Tasks | Site to-dos raised by the system |
| Comments | Moderation queue: pending, approved, spam |
| Plugins | Installed plugins, activation, updates |
| States | Empty, loading, error and offline treatments |

### `Klytos Admin - Screens 3.dc.html` — setup, money, content model, machine room
| Screen | Purpose |
|---|---|
| Setup wizard | Seven-step first run; no shell, own sidebar of steps |
| x402 dashboard | Agent-payment revenue, top paid pages |
| Content model | Post types, taxonomies, statuses |
| Translations | Locale progress, string editor with AI suggestions |
| Blocks | Block gallery with wireframe previews and categories |
| Health | Diagnostics, log stream, environment facts |
| Terminal | In-admin CLI with command palette |

### `Klytos Admin - Screens 4.dc.html` — integrations, the law, the owner's account
| Screen | Purpose |
|---|---|
| Webhooks | Endpoints, event subscriptions, delivery log, HMAC panel |
| Consent | Cookie banner config, cookie audit, acceptance stats |
| Privacy | GDPR export and erasure, per-section method and status |
| Profile | Identity, sessions (including MCP clients), security, prefs |
| Licence | Plan, key, activated domains, entitlements |
| AI images | Prompt, model options, result, generation history |
| Options | Raw key/value store, filtered by domain |

### `Klytos Admin - Screens 5.dc.html` — theme files, terms, and the machinery
| Screen | Purpose |
|---|---|
| Templates | Template library with wireframe previews, shared parts, source |
| Taxonomies | Hierarchical terms, add-term form, registered taxonomies |
| Scheduled actions | Queue stats, action table, cron trigger, last run |
| System integrity | Signature check, trust levels, modified-file diffs |
| Updates | Core release + changelog, plugin batch, history with rollback |
| Transactions | x402 ledger: id, page, agent, amount, network, provider |
| x402 settings | Provider choice, wallet, pricing, exempt agents, 402 body |
| Plugin page | A plugin's own screen inside the shell, with capabilities |

### `Klytos Admin - Screens 6.dc.html` — the deep screens
| Screen | Purpose |
|---|---|
| Post type | Full editor: identity, editor choice, custom fields, statuses, per-locale slugs, exposure |
| Block data | Global block values, slot editors, stored JSON, placements |
| Logs | Level filter, file picker, log stream, context + stack panel |
| Template preview | Template × sample content at four real widths, with checks |
| Reset password | Token landing: strength meter, rules, session notice |

Two earlier explorations are kept for reference: `Klytos Admin - Redesign.dc.html`
(dashboard, AI chat and pages in both a native-window and a browser reading) and
`Klytos Admin - Copilot dock.dc.html` (the copilot panel in four states — docked,
collapsed, floating, full). `Klytos Admin - Current.dc.html` is the pre-redesign state,
useful for diffing.

---

## Interactions and behaviour

Because this is a multi-page PHP app, most "interaction" is a form post and a redirect.
Only these need JavaScript:

- **Theme toggle** — swaps `data-theme` on the wrapper, persists to a cookie so the
  server can render the right one on the next request. No flash of the wrong theme.
- **Command palette (`⌘K`)** — overlay, fuzzy match over nav items, pages and commands.
- **Copilot dock** — docked / collapsed / floating / full, remembered per user.
- **Terminal** — line-buffered, streams output, autocomplete via
  `api/terminal-autocomplete.php`.
- **Inline edit and autosave** — the page editor posts to `api/autosave.php` on idle;
  show the saved state in the toolbar, never a modal.
- **Log tail** — poll, do not open a socket.
- **Bulk actions** — a checkbox column enables a floating action bar; the bar posts a
  normal form.

Everything else — filters, pagination, sorting, tab switching — is a link with query
parameters. A filter chip is an `<a>`, and the current filter is the one whose href
matches the current query. This keeps the admin usable with JS disabled, which is the
point.

Destructive actions confirm inline (a second click on a now-red button), never with a
browser `confirm()`.

## State

Server-side, per request. The only client state worth naming: theme, copilot dock mode,
sidebar collapse, table density, and the last-used filter per list screen — all cookies
or `localStorage`, all optional.

---

## Assets

`assets/` in this bundle holds the complete Klytos logo set as SVG, built on the PackDesk
canonical grid (120 units, figure rotated 45° at centre (60,60), squircle radius 27 =
22.5 %, the check retained as the family seal). Klytos's own mark is the small rotated
diamond above the check.

| File | Use |
|---|---|
| `klytos-icon.svg` | Master product icon, squircle, solid accent — macOS, iOS, iPadOS |
| `klytos-icon-gradient.svg` | Hero gradient variant — splash and marketing only |
| `klytos-favicon.svg` | Web favicon, ships at 32px, scales to 512 |
| `klytos-icon-tile.svg` | Windows tile and Store, radius 12 (10 %) |
| `klytos-icon-circle.svg` | Circular mask — avatars, social profiles |
| `klytos-icon-android-background.svg` | Android adaptive icon, background layer, full bleed |
| `klytos-icon-android-foreground.svg` | Android adaptive icon, foreground layer, inside the 66 % safe zone |
| `klytos-icon-mono.svg` | Monochrome figure, `currentColor`, no container — menu bar, watermark, print |
| `klytos-wordmark.svg` | Wordmark, outlined paths, light backgrounds |
| `klytos-wordmark-dark.svg` | Wordmark, outlined paths, dark backgrounds |
| `klytos-wordmark-text.svg` | Wordmark, live Geist Bold text — editing source only |
| `klytos-wordmark-dark-text.svg` | Wordmark, live text, dark — editing source only |
| `klytos-lockup.svg` | Icon + wordmark, outlined, light backgrounds |
| `klytos-lockup-dark.svg` | Icon + wordmark, outlined, dark backgrounds |
| `klytos-lockup-text.svg` | Icon + wordmark, live text, light — editing source only |
| `klytos-lockup-dark-text.svg` | Icon + wordmark, live text, dark — editing source only |
| `packdesk-seal.svg` | The check alone — the "by PackDesk / work done" seal |

Every one of these also ships as **PNG** in `assets/png/` — 48 files, at each platform's
required sizes and densities. `assets/fonts/` holds the two Geist variable `.woff2` files and
`OFL.txt`. `assets/icons/klytos-ui-icons.svg` is the UI icon sprite. The complete index —
every asset, its intrinsic size, every format, its exact use — is **`SPEC/assets-index.md`**.

Notes for implementation:

- The wordmark is **bicolor**: `Kly` in the neutral that suits the background (near-black
  on light, white on dark), `tos` in `--color-acento`. Geist Bold, `letter-spacing:-1.6`
  at 56px.
- The primary wordmark and lockup files carry **outlined paths** — no font dependency, they
  render identically anywhere. The `*-text.svg` versions carry live text and exist only so
  the mark can be re-set; they need Geist Bold installed and are not for production.
- The mono icon inherits `currentColor` — set the colour on a wrapper.
- Do not recolour the icon, do not add effects, do not place the figure on a background
  other than the accent (or the gradient variant).

**UI icons** ship as an SVG sprite: `assets/icons/klytos-ui-icons.svg`, 67 `<symbol>`s, one
per glyph the design uses — Material Symbols Outlined geometry redrawn as 1.5px line icons on
the 24 grid (the weight-300 / `opsz 24` equivalent). Use it with
`<svg aria-hidden="true"><use href="…#ks-search"></use></svg>`; colour comes from
`currentColor`. Sizes: 15 in a table cell, 16–17 in a list row, 18 in the sidebar. There is
no icon font to load and nothing to subset. Per-screen glyph lists and the usage rules are in
`SPEC/assets-index.md` §3.

---

## Files in this bundle

```
design_handoff_klytos_admin/
├─ README.md                              ← this file
├─ SPEC/
│  ├─ accessibility.md                     ← the accessibility contract. Normative.
│  ├─ color-contrast-audit.md              ← every pair, both themes, measured
│  ├─ design-tokens.md                     ← origin, scope, Klytos vs inherited
│  ├─ assets-index.md                      ← every asset, size, format, use
│  ├─ manifest.md                          ← all 44 entries → template + data + deltas
│  ├─ open-questions.md                    ← zero unresolved
│  └─ screens/                             ← 11 template specs: states + responsive + a11y
├─ screens/
│  ├─ Klytos Admin - Screens.dc.html
│  ├─ Klytos Admin - Screens 2.dc.html
│  ├─ Klytos Admin - Screens 3.dc.html
│  ├─ Klytos Admin - Screens 4.dc.html
│  ├─ Klytos Admin - Screens 5.dc.html
│  ├─ Klytos Admin - Screens 6.dc.html
│  ├─ Klytos Admin - Redesign.dc.html     ← earlier exploration, kept for reference
│  ├─ Klytos Admin - Copilot dock.dc.html ← copilot panel, four states
│  ├─ Klytos Admin - Current.dc.html      ← pre-redesign, for diffing
│  ├─ browser-window.jsx                  ← the browser chrome used by the mocks
│  ├─ image-slot.js
│  ├─ support.js
│  └─ _ds/…                               ← design-system bundle the mocks load
├─ assets/
│  ├─ *.svg                               ← the logo set (outlined marks + `-text` sources)
│  ├─ png/                                ← 48 rasters, every platform size
│  ├─ icons/klytos-ui-icons.svg            ← the UI icon sprite, 67 glyphs
│  └─ fonts/                              ← Geist + Geist Mono variable woff2, OFL.txt
└─ tokens/                                ← PackDesk tokens + tokens/klytos-admin.css
```

`screens/_ds/` exists only so the prototypes open offline. The file you actually want
when implementing is `tokens/` at the root of this bundle — nine plain CSS files with
no dependencies, `tokens/klytos-admin.css` last.

Open any `.dc.html` file directly in a browser. Use the chip row above the frame to move
between screens and the last chip to flip light/dark.

## Suggested order of work

1. Tokens: copy `tokens/*.css` into the admin's stylesheet chain, behind `[data-theme]`.
   `tokens/klytos-admin.css` loads **last** — it carries the measured accessibility tones
   (`--sobre-tinte-*`, `--texto-sutil`, `--borde-control`) and the normative `:focus-visible`,
   `.k-hit-24`, reduced-motion and forced-colors rules. Origin and scope of every file:
   `SPEC/design-tokens.md`.
2. The shell: sidebar, toolbar, status bar, theme toggle, `⌘K`.
3. The component layer in `klytos-components.css`: button, badge, chip, card, table,
   field, switch, stat card, progress.
4. List screens (pages, users, plugins, transactions, options) — they share one table.
5. Form screens (settings, x402 settings, post type, profile) — they share one field.
6. The specialised ones last: editor, terminal, AI chat, wizard, preview.
