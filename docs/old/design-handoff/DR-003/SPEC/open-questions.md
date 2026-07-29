# Open questions — Klytos admin

**Status: zero unresolved items.**

This file exists because the absence of an open-questions file is not the same as zero open
questions. Everything that was genuinely undecided is below, with who decided it and what
was decided. Nothing here is waiting on anyone.

---

## Resolved in this re-delivery

### 1. What gives when a badge fails contrast on its own tint?
**Asked of the client. Answer: decide for me → the palette stays sacred, the pattern
changes.** Base hexes and tint opacities are untouched; badge text is painted in the measured
`--sobre-tinte-*` tones added in `tokens/klytos-admin.css`. Nine tones × two themes, all
≥ 4.5:1 on every admin surface. Working: `SPEC/color-contrast-audit.md`.
**Consequence:** badge text is a hair darker in light and a hair lighter in dark than the
prototypes render. That is the only visual delta in the whole re-delivery, and it is the one
DR-001 explicitly authorised.

### 2. Grid table or real table?
**Decided in design: a real `<table>` with the grid layout applied to it, *and* the explicit
ARIA table roles on every element.** Both, because `display:grid` strips the implicit roles
that the elements would otherwise provide. Exact markup, element by element, in
`SPEC/accessibility.md` §2.1; the three surfaces that are *not* tables are named in §2.2.
The README's Table entry now says the same thing.

### 3. Which value is canonical for the two type conflicts?
**Decided in design: the delivered screens win.** Body `400 13px/17px`, secondary
`400 11px/16px`. `typography.css` and the README's type table are now identical, and the six
roles that had no token have one. Rationale in `SPEC/design-tokens.md`.

### 4. Should the wordmark ship with outlined text?
**Asked of the client. Answer: outlined paths are the primary files; the live-text versions
ship alongside as `*-text.svg` for editing.** Done — four outlined SVGs, four `-text` SVGs.
The outlined files have no font dependency and render identically anywhere.

### 5. How do the UI icons ship, given that a subsetted font cannot be produced here?
**Asked of the client. Answer: an SVG sprite.** `assets/icons/klytos-ui-icons.svg`, 67
`<symbol>`s, Material Symbols Outlined geometry redrawn as 1.5px line icons on the 24 grid.
Drop-in, no font, no build step, and it survives forced-colors mode. Usage and the per-file
glyph list: `SPEC/assets-index.md` §3.

### 6. How far does the PNG raster set go?
**Asked of the client. Answer: the full platform set.** 48 PNGs — favicon 16/32/48/180/192/512,
iOS 1024, Android adaptive 432 plus every mipmap density, Windows tile 71/150/310, mono in
black and white, wordmark and lockup @1x/@2x/@3x. Enumerated in `SPEC/assets-index.md` §1.

### 7. Does the accessibility spec cover the HTML Klytos generates?
**Asked of the client. Answer: the admin in full, plus a normative floor for generated
HTML.** `SPEC/accessibility.md` §1–§9 is the admin; §10 is the seven-point floor for blocks,
templates, forms, the cookie banner, language and theme contrast, which the front-end design
request expands. Two of those points are enforced from inside the admin: the editor refuses
to publish a page with no `<h1>` or an image with no alt decision, and the theme editor
refuses a contrast pair below 4.5:1.

### 8. Can the Geist fonts be shipped, or does the build have to fetch them?
**Resolved by reading the licence: they ship.** Geist and Geist Mono are SIL OFL 1.1, which
permits redistribution. Both variable `.woff2` files and `OFL.txt` are in `assets/fonts/`,
and `tokens/fonts.css` points at them — the previous `../fonts/…` paths resolved to nothing
and the licence file it named did not exist. Both are fixed.

### 9. Where does `--texto-terciario` survive?
**Decided in design: nowhere in text.** It fails AA in both themes at every size the admin
uses. All support text moves to `--texto-sutil`. `--texto-terciario` remains defined, and is
permitted only on decoration that duplicates an adjacent label (a chevron next to the word
"Expand"). Stated as a rule in `SPEC/design-tokens.md` and enforced in the audit.

### 10. Switch or checkbox, per control?
**Decided in design, and recorded per screen** in `SPEC/manifest.md` rather than left to the
build: a control that takes effect immediately is `role="switch"`; a control that needs Save
is a checkbox. Twelve screens carry an explicit note. The two are different roles and the
handoff no longer leaves it to interpretation.

### 11. What happens to a 20px badge under the 24 × 24 target rule?
**Decided in design: documented exception.** A badge is not interactive, and 2.5.8 governs
pointer targets. The controls that *are* interactive and drawn smaller — the 13px checkbox,
the 14px radio, the 15px table-cell icons — get a 24 × 24 hit area from `.k-hit-24`, with no
change to the drawing. Full table, including the two permitted exceptions (inline links, the
drag handle): `SPEC/accessibility.md` §7.

### 12. Is the "States" file a screen?
**Decided: no.** It is a specimen sheet. The states it shows are normative only where they
match the per-template `States` sections; where they differ, the template file wins. Recorded
as entry 16 in `SPEC/manifest.md`.

---

## Resolved in DR-002 (the Dashboard)

### 13. Is the Dashboard a stats overview, and does it exist at all?
**Asked of the client. Answer: it exists, and it is "state of this install".** It stays the
target of the shell's brand link, so `template-shell.md` §3 is unchanged. It is specified as
deltas from `template-overview-stats.md` — no twelfth template. Entry 44 in
`SPEC/manifest.md`.

### 14. Which figures does it lead with?
**Asked of the client. Answer: Last build · Pages · MCP · Failing checks · Pending updates** —
five, which is the template's maximum stat-row width. Klytos and PHP versions were dropped
from the stat row (they are facts, not figures, and the status bar already carries them). No
traffic, task, health-detail or revenue numbers: those screens own them, and each card links
to its owner.

### 15. The indexing toggle on the Dashboard
**Asked of the client. Answer: move the setting to Settings; the Dashboard only warns.** The
Dashboard shows a non-dismissible `--tinte-aviso` `role="status"` banner while the site is
blocked, with a link and **no toggle**. The setting itself is a checkbox + Save in
Settings → Advanced. This is the only change DR-002 makes to an existing entry (entry 9).

### 16. How do plugin dashboard widgets get specified?
**Decided in design: as a frame with a contract, plus the two core widgets, plus the
visibility control.** The frame states what a widget may and may not render; *Quick actions*
and *System info* are specified in full; per-user show/hide is a checkbox set in
Profile → Preferences, not an inline menu on the Dashboard, because a hidden widget must be
recoverable from somewhere findable.

### 17. How loud is a degraded subsystem on the Dashboard?
**Decided in design: per-card only, no page-level banner.** `template-shell.md` §1 already
gives degradation one status-bar fact and says it never escalates to a banner; on a screen
whose job is showing subsystem state, a banner would restate the card beside it. The good
case is equally explicit: zero failing checks reads "All 24 checks passed", not a blank.

### 18. Does the Dashboard need a new glyph?
**Resolved by checking: no.** All eleven glyphs it uses are already in the 67-symbol sprite.
"Indexing blocked" uses `ks-block` rather than a new `visibility_off`.

---

## Standing assumptions — not questions, but say so if any is wrong

These were not asked because the brief already answers them. They are listed so a wrong
assumption is caught before code is written, not after.

1. **The admin is a browser surface.** No native shell, no Liquid Glass, no blur, no
   translucency. `platform.css` and most of `glass.css` are inherited furniture.
2. **Multi-page PHP, no SPA.** Filters, sorting, pagination and tabs are links; writes are
   form posts. Everything works with JavaScript disabled except the five features the README
   names, and each of those has a non-JS path.
3. **Both themes ship.** Neither is the accessible one; both meet AA independently.
4. **Copy is English, sentence case, impersonal, no emoji**, and every string is
   substitutable for i18n with +30 % expansion headroom.
5. **1440 × 976 is the design viewport**, and the four breakpoints in each template file are
   the contract below it.
6. **The design system is upstream.** If PackDesk changes a token, this bundle is stale in
   that one value; `tokens/klytos-admin.css` and the Klytos section of `typography.css` are
   the only places Klytos overrides it.
