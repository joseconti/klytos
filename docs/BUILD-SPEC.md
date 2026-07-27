# BUILD-SPEC — Klytos Admin redesign

> Created 2026-07-27 at the Phase 4 Step 1 audit of the design handoff *"Klytos CMS Redesign"*
> (Claude Design project `6916aa0a-ee39-4c8a-a531-174650c83281`, bundle
> `design_handoff_klytos_admin/`). Decision: **D-065**.
>
> This file holds the **audit evidence** and, once the handoff passes, the consolidated build
> contract. It is deliberately NOT inside the handoff directory: per the handoff contract's rule 10
> that directory holds Design's bytes and nothing else, so it stays wholesale-replaceable when a
> re-delivery arrives.

## Status: **HANDOFF INCOMPLETE — the build has NOT started**

The handoff contract (`references/handoff-contract.md`, rule 5 and "What complete means") is explicit:
where gaps exist the build must not begin and the gaps become a **Design Request**, never licence to
improvise. **DR-001** is registered and sent (`docs/design/design-requests/DR-001.md`).

Nothing in `installer/admin/` has been changed.

---

## 1. What was audited, and how

Read directly from the Design project through the `DesignSync` read methods on 2026-07-27:

| Evidence | Method | What it established |
|---|---|---|
| Project identity | `get_project` | `Klytos CMS Redesign`, `type: PROJECT_TYPE_PROJECT`, `canEdit: true` |
| Full file inventory | `list_files` | 100 paths; the delivery bundle is `design_handoff_klytos_admin/` with `README.md`, `screens/` (9 `.dc.html` + 3 support files + `_ds/`), `assets/` (13 SVG), `tokens/` (8 CSS) |
| The handoff's own README | `get_file` | Brief, fidelity statement, token tables, shell spec, component inventory, 41-screen catalogue, interaction spec, asset notes, suggested order of work |
| `tokens/colors.css` | `get_file` | Compared value-by-value against the README's colour tables |
| `tokens/typography.css` | `get_file` | Compared against the README's type table |
| `tokens/spacing.css` | `get_file` | Compared against the README's spacing/radius section |

**The screens themselves were deliberately NOT pulled into this audit.** They are large prototype
HTML files and the gate does not need their bytes: what the contract checks at this step is the
presence and internal agreement of the SPEC layer, which is decidable from the inventory and the
README. They are materialised when the build starts.

**The handoff is not yet materialised into `docs/design/design-handoff/`.** That is deliberate rather
than pending sloppiness: rule 10 requires that directory to be exactly Design's delivery so a
re-delivery can be installed by replacing the whole directory. A partial copy would be worse than no
copy. It is materialised in full when DR-001 comes back.

## 2. What the handoff gets RIGHT

Recorded first, because the gaps below are specific and this delivery is well above average:

- **It respects the actual architecture.** It states plainly that the files are *"design references
  written in HTML… not production code to copy"*, that the target is PHP templates under `admin/`
  with the existing CSS and vanilla JS, and that no React, bundler or component framework is to be
  introduced. That is the single most common way a handoff goes wrong, and this one closes it in the
  first paragraph.
- **The colour token layer is exact and it agrees with the spec.** Every value in the README's colour
  tables was compared against `tokens/colors.css` and matches: accent `#0E8074`/`#3CC3B2`, the five
  semantics, the four product states, tints at 11 % light / 19 % dark, the neutrals, and
  `--sobre-acento` `#FFFFFF`/`#0B0B0C`. Contract rule 3 is met **for colour**.
- **The dark/light contract is real**, expressed as `[data-theme]` on a wrapper with a single
  attribute swap, and the file itself warns against the `#fff`-on-accent trap that dark mode creates.
- **A component inventory with dimensions** (button heights 28/34/38, badge 20px, chip 24px, switch
  38×22, checkbox 13px, field 34px, radii, shadow, focus ring, motion curves).
- **An interaction spec that matches a multi-page app**: form-post-and-redirect by default, JS named
  only where it is genuinely required, filters as `<a>` links with query parameters so the admin
  keeps working with JS disabled.
- **A complete logo set** as 13 SVGs with per-file usage notes.

## 3. Gate result — gaps against `references/handoff-contract.md`

Each gap names the contract rule, what was expected, and the evidence.

### BLOCKING

**G1 — `SPEC/accessibility.md` is absent entirely (rule 9).**
There is no accessibility spec of any kind: no contrast-verified pairs with measured ratios, no focus
order, no accessible name/role/state per component and state, no heading/landmark structure, no target
sizes, no text-scaling or forced-colors behaviour, no error-identification pattern. The README mentions
a visible focus ring and a reduced-motion variant, which is two items out of that list.
Rule 9 is unambiguous: *"A screen delivered without its accessibility spec is an incomplete handoff —
the build must not invent it."* This project has committed to **WCAG 2.2 AA + EN 301 549 + the
European Accessibility Act (D-007)** and its admin baseline is measured at **~20–25 %**, so this is
not a formality here.

**G1b — the handoff admits its contrast is not fully verified, and points at a document it does not
ship.** `tokens/colors.css`'s own header states that text/icon meet WCAG AA against the mode
background, but that *on badge tints* "algunos tonos quedan al límite en un modo u otro", referring
the reader to `guidelines/auditoria-color-y-contraste.md` — **a file that is not in this bundle**. So
the one contrast statement the delivery does make is qualified, and its evidence is unshipped.

**G1c — the README instructs a pattern that removes table semantics.** *"Table — CSS grid, not
`<table>`, when columns are fixed-width."* A data table built from `display:grid` is **not exposed as
a table to assistive technology** — no row/column relationships, no header association — unless
explicit ARIA table roles are applied, which the handoff never mentions. The admin's list screens
(pages, users, plugins, transactions, options, logs) are all data tables. This needs an answer from
Design, not a build-side improvisation in either direction.

**G2 — the type scale in the SPEC and in the delivered code DISAGREE (rule 3: "They must agree").**
Measured, README table vs `tokens/typography.css`:

| Role | README says | `typography.css` says | |
|---|---|---|---|
| Body / table cell | `400 13px/17px` | `--type-body: 400 13px/18px` | **conflict** |
| Secondary / hint | `400 11px/16px` | `--type-caption: 400 11px/14px` | **conflict** |
| Page H1 | `700 22px/28px` | `--type-title-1: 700 22px/28px` | agrees |
| Card heading | `600 13px/16px` | `--type-headline: 600 13px/16px` | agrees |
| Section eyebrow | `600 10px/14px` mono, `0.06em` | *no token* | missing |
| Table column header | same as eyebrow | *no token* | missing |
| Nav item | `400 13px/16px`, active `500` | *no token* | missing |
| Toolbar title | `600 13px/16px` | *no token* | missing |
| Badge / chip | `500 11px/16px` | *no token* | missing |
| Numeric cell | mono `500 12px/17px` | *no token* | missing |

The build cannot know which is canonical, and six of the ten roles the README specifies have no token
at all — so a faithful build would have to hard-code them, which the README itself forbids
(*"Never hard-code a hex"* is stated for colour; the same principle is what the token layer is for).

### NON-BLOCKING, but must be resolved before the affected work

**G3 — the token files are PackDesk-generic, not scoped to this project (rule 3).**
`tokens/typography.css` ships `--type-picking-linea` and `--type-picking-pedido`, commented
*"Densidad XL de picking (legible a distancia en almacén)"*, plus `--type-mono-pedido` and
`--type-mono-importe`. Those are a warehouse/packing product's concerns. Rule 3 requires the token
file to state its **origin** and the **target surfaces** it covers; it states neither for Klytos. Not
wrong to inherit a design system — the README says the colours come from PackDesk — but the delivery
must say which tokens are Klytos's contract and which are inherited furniture.

**G4 — no per-screen SPEC files (rule 2).** 41 screens, zero `SPEC/screens/<screen>.md`. States are
gathered into a single *"States"* screen rather than specified per screen, and the only responsive
instruction is *"recreate pixel-close at 1440 × 976, then allow to reflow"* — no breakpoints, no
per-screen behaviour at narrow widths.

**G5 — logos and icons ship SVG only (rule 4, a hard delivery rule).** 13 SVG files, **zero PNG**. The
contract requires both, PNG at intrinsic size plus the platform's densities/sizes, and the bundle
includes a `klytos-favicon.svg` with no raster fallback sizes.

**G6 — fonts are not delivered at their final paths (rule 4).** `Geist-Variable.woff2` and
`GeistMono-Variable.woff2` exist only under `screens/_ds/…/fonts/`, which the README says *"exists
only so the prototypes open offline"*. There is no `@font-face` written against a final project path,
no licence note, and no acquisition block. The wordmark SVGs additionally *"carry live text, so Geist
Bold must be loaded"* — so a font gap is also a brand-asset gap.

**G7 — Material Symbols are named, not delivered (rule 4).** *"If you prefer to self-host, subset only
the glyphs used."* Subsetting is precisely a build-side transformation, which rule 4 defines as an
incomplete handoff.

**G8 — no `SPEC/manifest.md`** (rule 1). The README's screen tables list purpose per screen but do not
map screen → template + data/variant, which is the reuse map the contract exists to enforce.

**G9 — no `SPEC/assets-index.md`** (rule 4). The README's asset table gives a "Use" column but no
intrinsic dimensions and no per-asset format list.

**G10 — no `SPEC/open-questions.md`** (rule 5). Its absence is not the same as zero open questions; the
contract requires the file to exist and be empty.

### Met in substance, recorded as satisfied

- **`SPEC/interactions.md`** — not present as a file, but the README's *"Interactions and behaviour"*
  and *"State"* sections cover behaviour, what needs JS, destructive-action confirmation and client
  state. Accepted; no Design Request item raised.
- **`SPEC/external-setup.md`** and **`SPEC/external-assets.md`** — genuinely n/a. The redesign
  configures no external software and needs no photographic or generated imagery.

## 4. Verdict

**Incomplete.** Two blocking gaps (G1 with G1b/G1c, and G2) and eight further gaps. Per the contract
the build does not start; **DR-001** carries every item above back to Design.

The two blocking ones are worth separating from the rest: G2 is a straightforward inconsistency that a
re-delivery fixes mechanically, while **G1 is the one that would have cost the most if it had been
missed**, because a 41-screen rewrite is simultaneously the best and the last cheap opportunity this
project has to fix an admin measured at ~20–25 % against the standard it has committed to. Building
41 screens to a spec that is silent on accessibility would rebuild the gap at full scale.

## 5. Consolidated build contract

To be written here once DR-001 is resolved and the re-audit passes. It does not exist yet, and no
section of it may be inferred from the current delivery.
