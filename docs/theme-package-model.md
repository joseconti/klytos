# Theme Package model — conceptual redesign of the frontend visual layer

> **Phase 2 spec addendum.** Status: **designed, not implemented.**
> Implementation is sequenced AFTER Phase 5 Sprint 1 (the audit's fix-now bucket,
> D-018). Recorded as decision **D-023**.
>
> This document is self-sufficient: a fresh session can pick the work up from here
> without re-exploring the code.

## 1. Why this exists

The user reported that the theme model was conceptually weak and wanted a way to
accept a design produced by an external design agent (Claude Design or another),
guided by a skill that specifies exactly what that design must be, so that Klytos
can build it — without Klytos dictating header, footer and the rest.

The investigation **contradicted the starting premise** and that correction is the
foundation of this design:

**Klytos does not restrict the visual layer.** `klytos_set_part` already accepts
"COMPLETELY FREE HTML/CSS" for header, footer, top-bar and any author-named part
(`installer/core/mcp/tools/part-tools.php:88-89`), and `{{klytos_part:NAME}}`
accepts arbitrary names through a 4-level resolution hierarchy
(`installer/core/part-manager.php:15-23`).

**The defect is the opposite of restriction: the freedom has no contract.**

## 2. Evidence — what is actually wrong

| # | Finding | Evidence |
|---|---|---|
| 1 | **Design intent is never persisted** | The site-builder Phase 2 produces `=== DESIGN BRIEF ===` and `=== COMPONENT INVENTORY ===` as *prose in the chat transcript* (`installer/core/guides/site-builder.md:85-166`). Phase 4 collapses it into 26 scalars. Nothing downstream can verify build-vs-intent; a later session cannot recover the intent at all. |
| 2 | **The expressive funnel is 14 CSS variables wide** | Frontend tokens: 8 colours, 3 font families, `--max-width`, `--radius`, `--spacing`. One radius, one spacing unit, no elevation scale, no motion tokens, no state colours, no visitor dark mode. (Admin has ~118 tokens — the asymmetry is the point.) |
| 3 | **Base CSS is hardcoded in PHP** | `getBaseCss()` returns a 243-line heredoc, `installer/core/build-engine.php:1543`. Every Klytos site inherits one look. A design can only *append* a `custom_css` blob (`build-engine.php:298-301`). |
| 4 | **Distinctive design deliberately escapes the system** | The site-builder guide steers visually rich sections (hero, product grids, testimonials) into `wp:html` blocks and per-page HTML — outside the theme, outside blocks, outside tokens, invisible to every downstream tool. |
| 5 | **Nothing is portable** | No installable/exportable/versionable theme, no style variations, no template inheritance. Every custom template is a full standalone `<!DOCTYPE html>` document, so changing one region means rewriting the whole document. |
| 6 | **Structural heuristics fight non-standard layouts** | The build engine scans templates for raw `<header` / `<footer` strings to deduplicate blocks, and auto-injects the top-bar before the first `<header>` (`build-engine.php:553-660`). Sidebar-nav-instead-of-header, dual headers, or top-bar-below-header all collide with this. |

Two pre-existing problems this redesign must not inherit:

- **Accessibility, audit A-05 (HIGH).** `build-engine.php` and `installer/templates/`
  contain **zero** `aria-` or `role=` attributes, and there is no skip link. Klytos's
  users inherit this markup and, under the EAA, the legal exposure lands on them for
  markup they did not write (D-007). A free-HTML part system with no validation makes
  this permanently unfixable — which is the strongest argument for a validated contract.
- **Model drift.** Two part models ship side by side: `klytos_set_part` (current, taught
  by the in-product guide) and global blocks + `klytos_set_global_block_data` (superseded,
  still taught by the `klytos-custom-templates` skill). **No shipped skill mentions
  `klytos_set_part`.** Which model an AI follows depends on whether it loaded the skill or
  the guide. Per L-002 this redesign closes the drift instead of adding a third model.

## 3. Decisions taken with the user

| Question | Decision |
|---|---|
| Scope | **Full installable package.** The theme owns its templates, parts, CSS, assets and self-hosted fonts. |
| How design enters | **Authoring skill + validator.** The design becomes a package at authoring time; Klytos never interprets an arbitrary design at runtime. |
| Timing | **Plan only now.** Implementation waits for Sprint 1 (authorization/security) to close. |

Rejected: extending the current record with more knobs (leaves base CSS in PHP and the
design as a `custom_css` blob); a freeform HTML/CSS importer as the canonical path
(no contract → unpredictable results and no accessibility guarantee).

## 3b. Standing invariant — single source of truth for shared chrome

**Non-negotiable, stated by the user and binding on everything below.** Shared chrome —
header, menu, footer, top-bar, any site-wide element — is authored in **one place**. Edit
that one place and the whole site reflects it. Updating a header must NEVER mean editing
the site's pages, one by one or in bulk.

This is already the design of the current parts system and it is **preserved, not
replaced**, by the package model: `installer/core/part-manager.php:8-9` — *"A part is a
named fragment shared across the whole site… Edited once, it propagates everywhere on the
next build via the `{{klytos_part:NAME}}` placeholder."* In the package model the part
simply lives inside the theme package; it remains one file, one source of truth.

**The honest nuance that must not be glossed over.** Klytos generates a *static* site, so
one authored file corresponds to N generated HTML files. The single source of truth is
real at authoring time; propagation to output is a build concern. Both halves must hold:
one edit, and no per-page work.

**Gap found while validating this invariant (2026-07-18) — the propagation half is only
half-built:**

- `BlockManager::render()` wraps output in `<!--klytos:block:{id}-->` markers
  (`block-manager.php:242`) and `BuildEngine::smartRebuildBlock()` uses them to replace a
  global block across every generated HTML file **without a full rebuild**
  (`build-engine.php:1322-1362`). Exposed over MCP at `build-tools.php:100`.
- `PartManager` emits the equivalent `<!--klytos:part:{id}-->` markers
  (`part-manager.php:340`) — but **there is no `smartRebuildPart`, and no MCP entry point
  for one.** The markers exist; the mechanism that would use them does not.

Consequence today: the *superseded* global-blocks model has the fast propagation path,
while the *canonical* parts model does not — so changing the header through the current,
recommended API forces a full site rebuild. This is another face of the model drift noted
in §2, and it is exactly the scaling edge the invariant is meant to protect.

**Requirement for the theme package sprint:** implement smart propagation for parts on the
markers that already exist, expose it over MCP, and make "one edit → whole site updated,
without a full rebuild and without touching pages" a verified test point (see §7).

## 4. The model

### 4.1 A theme is a package, not a record

Mirrors the immutable plugin contract already in force: **theme ID = directory name =
manifest `id`**.

```
installer/themes/{theme-id}/
  klytos-theme.json        # manifest: identity, contract version, declared structure
  templates/               # full or partial layouts (may declare `extends`)
  parts/                   # header, footer, and ANY author-defined part
  css/
    tokens.css             # the theme's values for the token contract
    base.css               # replaces getBaseCss() — no longer PHP
    blocks.css             # the theme's styling of the core block catalogue
  fonts/                   # self-hosted; no Google Fonts request
  assets/
  variations/              # alternative palettes over the same structure
```

The active theme is a **pointer** (an option), not a blob. `custom_css` survives as a
site-level override layer on top of the theme — never as the design carrier.

### 4.2 Klytos guarantees a contract, not a layout

Only three things are fixed:

- **Placeholder vocabulary** — `{{page_content}}`, `{{menu_html}}`, `{{site_name}}`,
  `{{base_path}}`, `{{seo_meta_tags}}`, `{{hreflang_tags}}`, `{{klytos_part:*}}`, block
  slots, JS hook points. This already exists implicitly; the change makes it explicit
  and versioned.
- **Token contract** — the frontend token set grows from 14 into a real system:
  elevation, motion, spacing scale, state colours, a second radius, visitor dark mode.
  Blocks and core CSS may reference **only** tokens, never literal colours.
- **Landmark & a11y contract** — the theme must render the documented landmarks, a skip
  link, and focus-visible styling. This is what makes A-05 fixable at the system level
  instead of once per site.

**Nothing about arrangement is fixed.** Sidebar navigation instead of a header, two
headers, top-bar below the header — all legal, because the structural heuristics of
§2 finding 6 are replaced by the theme *declaring* its structure in the manifest.

### 4.3 Template inheritance

A template may declare `extends`. Changing one region no longer requires rewriting a
whole HTML document — the single largest friction in the current custom-template system.

### 4.4 The design brief becomes a durable artifact

The prose brief is replaced by a machine-readable design record stored **with the theme**:
intent, palette rationale, component inventory, reference notes. This is what makes a
design auditable across sessions, and what a future Keel Phase 3–4 redesign is checked
against. It closes, for the frontend and on the new model, the gap D-009 recorded as
"no design contract" — without retroactively inventing one for the old model.

### 4.5 Authoring skill + validator

- **`klytos-theme-authoring` skill** — the exact specification handed to Claude Design or
  any other agent: directory layout, manifest schema, required placeholders, the token
  contract, required states (hover/focus/disabled/dark), responsive breakpoints, WCAG 2.2
  AA rules, self-hosted fonts only, no external requests (CSP), no literal colours outside
  `tokens.css`.
- **Validator** — refuses a non-compliant package with concrete, actionable errors:
  manifest schema, required placeholders present, no external URLs, tokens-only CSS,
  landmarks and skip link present, contract version compatible. Rides the existing
  integrity-check system (signatures, trust levels).

Per **L-002**, the skill may only assert properties the validator mechanically enforces.

### 4.6 The design agent's MCP authoring loop (D-024)

An external design agent (Claude Design, or any MCP-capable agent) connects to the site's
existing MCP server through a **dedicated design authoring surface**. Its shape is the
decision, not its existence:

**The unit of exchange is the package, never an individual mutation.**

```
Design ──▶ klytos_get_theme_contract()   # placeholders, tokens, a11y rules, version
       ──▶ klytos_describe_site()        # pages, post types, blocks, menus, locales
       ──▶ klytos_validate_theme(pkg)    # concrete, actionable errors
       ──▶ klytos_preview_theme(pkg)     # rendered output against a PREVIEW target
       ──▶ (iterate)
       ──▶ klytos_install_theme(pkg)     # activation on the live site = separate human action
```

Explicitly **not** part of this surface: `klytos_set_part`, `klytos_set_colors`,
`custom_css` and every other per-mutation write tool. They remain available to the site
owner's own tooling; they are not how a design arrives.

**Why this shape** — the obvious alternative (the design agent pushes the design live,
part by part) was rejected on two independent grounds:

1. It reproduces the exact defect §2 diagnoses — imperative, runtime, unvalidated,
   site-bound design that is not portable, versionable or auditable. It is also no new
   capability: the MCP server already exposes those write tools today.
2. **Security.** `klytos_set_part` accepts a `js` parameter emitted as a `<script>` tag on
   **every page**, and stores `html` without `kses`
   (`installer/core/mcp/tools/part-tools.php:88-99`, `installer/core/part-manager.php:84-85`).
   A live-write design surface hands an external agent global stored-XSS on a production
   site — on the very axis the project already records as weakest (D-018).

**Constraints, all non-optional:**

- **Preview target only.** The design connection never writes to the published site.
  Activation is a separate, human action.
- **Dedicated `theme.*` capability**, scoped credential — not a general admin token.
- **No arbitrary JS** from this surface; scripts, if any, come from the validated package
  and are subject to the same contract checks as its CSS.
- Both security profiles apply (D-003); on conflict the stricter wins.

**Open unknown — do not assume it away:** whether Claude Design can act as an MCP client
against an arbitrary server has **not been verified**. The surface is therefore specified
for a generic MCP-capable agent, so it stays useful if Design cannot connect directly.
Verify this before implementation begins.

### 4.7 MCP surface

New: `klytos_validate_theme`, `klytos_install_theme`, `klytos_list_themes`,
`klytos_activate_theme`, `klytos_export_theme`, `klytos_preview_theme`.

Preserved: `klytos_set_theme` / `set_colors` / `set_fonts` / `set_layout` survive as
**overrides on the active theme**, so existing automation keeps working.

## 5. Files this will touch

| File | Change |
|---|---|
| `installer/core/theme-manager.php` | From a single colors/fonts/layout record to package resolution, activation, override layering |
| `installer/core/build-engine.php:1543` | `getBaseCss()` heredoc removed; CSS assembled from the active theme's files |
| `installer/core/build-engine.php:280-306` | CSS pipeline: theme tokens → theme base → theme blocks → site overrides |
| `installer/core/build-engine.php:553-660` | Replace `<header>` / top-bar string heuristics with manifest-declared structure |
| `installer/core/part-manager.php` | Add the theme package as a resolution level |
| `installer/core/template-resolver.php` | Add `extends` inheritance |
| `installer/core/theme-validator.php` | New |
| `installer/core/mcp/tools/theme-tools.php` | New package tools; existing tools become overrides |
| `.claude/skills/klytos-theme-authoring/` | New skill (+ `.agents/` mirror) |
| `.claude/skills/klytos-custom-templates/` | Close the parts-vs-global-blocks drift |
| `.claude/skills/klytos-css-classes/` | Document the expanded frontend token contract |
| `installer/core/guides/site-builder.md` | Phases 2 / 4 / 6 rewritten around the package |

Standing rule: every Klytos change updates the corresponding `.claude/skills/` files in
the same slice.

## 6. Migration — the installed base is real

Non-negotiable: production installs exist with encrypted stored data (project card:
`Installed base: yes`). A **default theme package is generated from today's
`getBaseCss()` + the 26 scalars**, so every existing site renders byte-identically after
upgrade. The package model is additive; the old record becomes the override layer.

## 7. Verification

1. **Playground first** (T-02, already a Sprint 1 dependency) — a site on the old model
   upgrades and renders identically. Byte-compare generated HTML/CSS.
2. **Validator adversarial suite** — a package violating each contract rule is refused
   with the correct error; a compliant package installs.
3. **Round trip** — `klytos_export_theme` → `klytos_install_theme` on a clean install
   reproduces the site.
4. **Accessibility** — automated pass (axe-core / pa11y) on generated output must show
   landmarks, skip link and focus styling present; then the guided assistive-technology
   loop per Keel's `references/accessibility.md`. A-05 closes only with recorded
   evidence, never by assertion (L-002).
5. **Single-source-of-truth propagation (§3b invariant)** — edit ONE part in the active
   theme (e.g. the header menu), trigger propagation, and assert: every generated page
   reflects the change, no page file was authored or edited individually, and no full
   site rebuild was required. Record the files-updated count as the evidence.
6. **End-to-end with a real design agent** — hand the authoring skill to Claude Design,
   build a theme that is deliberately *not* container+section+card-grid (e.g. sidebar
   navigation, no header), and confirm it installs and renders.

## 8. Sequencing

This is a genuine redesign, so it runs through **Keel Phases 3–4** (design handoff +
faithful build) — the first time this project does, per D-009's standing rule. It becomes
its own Phase 5 sprint, after Sprint 1.

Open before implementation starts:
- `docs/estimate.md` must be recomputed to include this scope.
- The `design-fidelity-auditor` subagent (deferred, PROGRESS.md) has its review trigger
  here: this is "the first redesign that goes through Phases 3–4".
