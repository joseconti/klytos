# BUILD-SPEC — Klytos Admin redesign

> Created 2026-07-27 at the Phase 4 Step 1 audit of the design handoff *"Klytos CMS Redesign"*
> (Claude Design project `6916aa0a-ee39-4c8a-a531-174650c83281`, bundle
> `design_handoff_klytos_admin/`, materialised in the repo at the contract path
> `docs/design/design-handoff/` — see §1b). Decision: **D-065**.
>
> This file holds the **audit evidence** and, once the handoff passes, the consolidated build
> contract. It is deliberately NOT inside the handoff directory: per the handoff contract's rule 10
> that directory holds Design's bytes and nothing else, so it stays wholesale-replaceable when a
> re-delivery arrives.

## Status: **RE-DELIVERY AUDITED — GATE PASSED 2026-07-29. The build may begin.**

DR-001's re-delivery was downloaded by the user, installed as a wholesale swap, diffed against the
archived delivery, and re-audited with the evidence in **§1c**. Every one of the ten gaps is closed,
nothing changed outside the named gaps, and all 72 declared contrast ratios were **recomputed
independently** rather than accepted. **DR-001 is resolved.**

The previous status is kept immediately below rather than deleted, because the audit trail is the
point of this file: the gate is only meaningful if what it rejected is still legible.

> ### Superseded — status until 2026-07-28: **HANDOFF INCOMPLETE — the build has NOT started**
>
> The handoff contract (`references/handoff-contract.md`, rule 5 and "What complete means") is
> explicit: where gaps exist the build must not begin and the gaps become a **Design Request**,
> never licence to improvise. **DR-001** was registered and sent
> (`docs/design/design-requests/DR-001.md`). Nothing in `installer/admin/` was changed.

**Step 2 is DONE: §5 is consolidated (2026-07-29, D-070).** The build contract is written — resolved
screen list, token table, per-template state matrix, per-screen ledger, accessibility contract,
interaction table, asset map, integration plan and faithfulness checklist.

**DR-002 is RESOLVED (2026-07-29, §1d): the Dashboard is specified as entry 44 and the gate passes
on the re-delivery.** Zero open Design Requests. The delivery is now **44 entries / 42 product
screens**, 41 of them drawn in the prototypes and the Dashboard specified in the manifest and its
template.

**Nothing in `installer/admin/` has been changed yet** — passing the gate authorises the build; it is
not the build, and §5 is the contract it will not deviate from. Three of Step 2's four open items
remain, all of them scope questions for the user (§5.11).

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

### 1b. Materialisation and re-verification against the local bytes (2026-07-27, second session)

The delivery bundle was placed in the repository by the user and, on their instruction, **moved to the
contract path: `docs/design/design-handoff/`** (rule 10 — that directory is exactly Design's bytes and
nothing else, so a re-delivery is installed by replacing the whole directory). Move verified: 49 files
before and after, identical aggregate content hash, no foreign file and no editor dropping inside
(`README.md`, `assets/` ×13 SVG, `tokens/` ×8 CSS, `screens/` ×12 + `_ds/` ×15).

The bundle was then **re-audited against the local files rather than the Design project's API**, and it
is **byte-identical in substance to the delivery audited above** — it is not a re-delivery. Every gap
in §3 was re-confirmed mechanically, not from memory: `SPEC/` does not exist (the only `.md` files in
the bundle are `README.md` and the `_ds` readme); zero PNG files; no `guidelines/` directory; the two
typography conflicts and the six missing roles are unchanged; `README.md` still instructs
`display:grid` instead of `<table>` with no ARIA mentioned; `--type-picking-*` and `--type-mono-pedido`
are still present. **DR-001 therefore remains unresolved and the build still has not started.**

Two findings the first audit could not make, because it did not read the delivered bytes — both
**strengthen G6** and are added to it below:

- `tokens/fonts.css` **does** ship `@font-face` rules, but they resolve to `../fonts/Geist-Variable.woff2`
  and `../fonts/GeistMono-Variable.woff2` — **paths that do not exist in the bundle**. There is no
  `fonts/` directory at the bundle root; the two `.woff2` files exist only under
  `screens/_ds/…/fonts/`. The delivered styles point at nothing.
- The same file states the SIL OFL licence lives at `/fonts/OFL.txt`. **No `OFL*` file exists anywhere
  in the bundle**, so the licence note rule 4 requires is promised and not delivered.

One neutral observation of record: the eight files in `tokens/` are **byte-identical** to those under
`screens/_ds/…/tokens/`. The README is right that one is a copy of the other — which also means there
is no Klytos-scoped token layer anywhere in the delivery (G3).

### 1c. The DR-001 re-delivery: install, diff, re-audit (2026-07-29)

**Provenance.** Downloaded by the user from the Claude Design project and placed at
`docs/klytos-cms-redesign/`; the delivery itself is its `project/design_handoff_klytos_admin/`
subtree, **123 files**, which matches the MCP listing of that directory exactly. The export also
ships a generic `README.md` from the design tool instructing a coding agent to read
`Klytos Admin - Screens 6.dc.html` and reimplement it "in React, Vue, native, whatever fits".
**That file was NOT followed and is not part of the delivery**: Phase 4 governs the order of work,
the gate had not passed when it was read, and its framework advice directly contradicts the
delivery's own constraint that the admin ships no framework and no build step. Tool output is not a
design instruction.

**Install.** Wholesale swap per contract rule 10, never a file-by-file merge: the previous delivery
was **moved** (not deleted) to `docs/old/design-handoff/DR-001/` — 49 files — and the new one placed
whole at `docs/design/design-handoff/`. Foreign-file scan: **0** (`.DS_Store`, `__MACOSX`, editor
droppings — none).

**Byte-stability diff, old vs new.** The re-delivery template requires that nothing changed outside
the named gaps; that is a checked fact here, not a courtesy.

| Area | Result |
|---|---|
| `screens/**` (9 prototypes + `_ds/` + 3 support files, 20 files) | **Does not appear in the diff at all** — byte-identical. The 41 screens, the shell, the component inventory and the interaction model are untouched, exactly as DR-001 required |
| `assets/*.svg` — favicon, 7 icon variants, seal | Byte-identical |
| `assets/klytos-{wordmark,lockup}{,-dark}.svg` | **Changed — in scope.** Replaced by outlined-path versions (gap: fonts / live text). The live-text originals are preserved byte-for-byte as `*-text.svg` and the index declares them "editing source only" |
| `tokens/{colors,effects,glass,motion,platform,spacing}.css` | **Comment headers only.** Every declaration diffed with comments stripped: **identical, all six.** No base hex, no opacity, no spacing value moved |
| `tokens/typography.css` | **Additions only.** The PackDesk scale at the top is untouched; a Klytos `:root` block is appended at the foot |
| `tokens/fonts.css` | `../fonts/…` → `../assets/fonts/…` — the exact gap DR-001 named |
| `README.md` | Changed — the type table and the Table entry, both named gaps |
| New: `SPEC/**`, `assets/fonts/`, `assets/icons/`, `assets/png/`, `tokens/klytos-admin.css` | All named gaps |

**Nothing changed outside the named gaps.**

**Gate evidence, item by item.** Every row is a command's output, not a reading of the delivery's
own claims.

| Contract item | Evidence | Result |
|---|---|---|
| `SPEC/accessibility.md` exists and is complete | 11 sections: contrast, table semantics, focus (indicator + per-screen order + keyboard), landmarks and headings, **12 components** with name/role/state, errors, target size 2.5.8, text scaling / zoom / reflow / forced-colors, motion, the normative floor for generated HTML, and how it is verified | **PASS** |
| Table semantics decided and specified | §2.1 gives the **exact markup**: a real `<table>` **plus** the explicit role set — `role="table"`, `rowgroup` on `thead`/`tbody`, `row` on every `tr`, `columnheader`+`scope="col"`, `cell` on every `td`, and the naming column as `<th role="rowheader" scope="row">`. §2.2 names the three surfaces that are *not* tables. This is option (a)+(b) of the question, not "add ARIA" | **PASS** |
| Contrast pairs, measured | `SPEC/color-contrast-audit.md` ships (the file `colors.css` referenced and the old bundle never contained). **All 72 declared ratios recomputed independently** with the WCAG relative-luminance formula: **72/72 agree within ±0.06**, and **0** rows marked PASS fail their threshold. Where a pair failed the delivery changed the *pattern*, not the palette — the `--sobre-tinte-*` tones in the new `tokens/klytos-admin.css` | **PASS — on my arithmetic, not Design's** |
| Type tokens: SPEC and delivered styles agree | Both conflicts resolved to the delivered screens (`--type-body` 13/17, `--type-caption` 11/16) and all six missing roles now have tokens. README table and `typography.css` carry the same values | **PASS** (see the observation below) |
| Per-screen specs with all states | 11 template files in `SPEC/screens/`, and `SPEC/manifest.md` references exactly those 11 — **no template referenced without its file, no file unreferenced**. Two apparent mismatches were my own regex catching `grid-template-columns` and `template-preview.php`, verified before being reported | **PASS** |
| Assets: every logo and icon in SVG **and** PNG | The index promises 48 PNGs across 12 sources; disk holds **48**, and every single one derived from the index's own size table **exists** — 0 missing. The four `*-text.svg` have no raster and are declared "editing source only", which is a documented position, not a gap | **PASS** |
| Fonts ship at the paths the CSS names | Both `url()` targets in `tokens/fonts.css` resolve to real files, and `assets/fonts/OFL.txt` (SIL OFL 1.1) is present — the two findings that only the bytes could show are both closed | **PASS** |
| UI icons delivered, not named | `assets/icons/klytos-ui-icons.svg` — a 67-symbol SVG sprite, drop-in, no font, no subsetting step, and it survives forced-colors mode | **PASS** |
| `SPEC/open-questions.md` at zero | Present; line 3: `**Status: zero unresolved items.**` Twelve resolved items, each naming who decided it; six standing assumptions listed so a wrong one is caught before code, not after | **PASS** |
| No foreign file (rule 10) | 123 files, 0 junk | **PASS** |

**Three things found in the export that are NOT part of the delivery, recorded before the staging copy
was deleted so they are not rediscovered as surprises.**

1. **`css/klytos-components.css` (30 KB) and `css/klytos-tokens.css` exist at the export's project
   root and deliberately NOT inside the bundle.** The delivery references `klytos-components.css`
   four times — README lines 28, 34, 202, 468 and `SPEC/design-tokens.md`'s load order — always as
   **the file the build writes**, in the project tree, never as a delivered artifact
   (`tokens/klytos-admin.css:75` says outright: *"Reglas normativas. Copiarlas tal cual a
   klytos-components.css"*). Design's root-level copy is its own working version, used to render the
   prototypes. **It was not taken, and building from it would be a Phase 4 principle-8 violation** —
   a prototype is input to Design, never a build source. The build produces that file from the SPEC.
2. **The prototypes load Font Awesome from `cdnjs.cloudflare.com`.** Neither the README nor any SPEC
   file names a CDN or Font Awesome; the declared icon source is the delivered 67-symbol sprite
   `assets/icons/klytos-ui-icons.svg`, on the Material Symbols Outlined 24 grid. The CDN link is
   prototype scaffolding. **Build rule: no CDN, no Font Awesome — the sprite is the icon source**,
   served same-origin because cross-document `<use href>` is same-origin only (the index says so).
3. **The export's project root carried its OWN copies of the nine `*.dc.html` prototypes, and eight
   of them differed from the delivery's — recorded 2026-07-29, immediately before the staging copy
   was removed.** This was found only because the deletion was checked file by file rather than
   trusted: the earlier "byte-identical" verification had compared the export's
   `project/design_handoff_klytos_admin/` subtree (clean, and still clean), which does **not** cover
   the project root. The difference is one line in each file and it is always the same line — the
   ⌘K hint in the search field: `top:calc(50% - 5px)` at the project root versus **`top:50%`** in the
   delivery. **The delivery's value governs**, as it does for everything else: the project root is
   the design tool's working area — the same class of scaffolding as items 1 and 2 — and only the
   bundle is the delivery (contract rule 10). Everything else at that root was accounted for before
   deleting: the `_ds` PackDesk bundle and the three support files are **byte-identical** to the
   delivery's, nine of the thirteen brand SVGs are identical, and the other four are the superseded
   **live-text** marks, each preserved byte-for-byte inside the delivery under its `*-text.svg` name.
   Nothing unique was destroyed except the superseded ⌘K value, which is written down here.

**One observation, recorded rather than bounced.** `--type-body` and `--type-caption` are each
declared **twice** in `typography.css`: the PackDesk value at lines 48/50 and the Klytos value at
91/95. As CSS this is correct and deterministic — the later declaration wins, so the effective values
are 13/17 and 11/16, matching the README — and `SPEC/design-tokens.md` states this shadowing
deliberately, to avoid forking upstream. It is **not** a gap. It is a trap for the build: a tool that
*greps* the file for `--type-body` finds the losing value first. **Build rule: the Klytos section at
the foot of `typography.css` is authoritative; never take the first match.** Bouncing DR-001 a second
time over a correct-but-shadowed declaration would be the audit failing to distinguish a defect from
a design choice it merely finds surprising.

### 1d. The DR-002 re-delivery: install, diff, re-audit (2026-07-29)

**Provenance and the false start.** An export arrived first that was **byte-identical to the
delivery already installed** — 123 files, `diff -rq` clean, same content hash, `manifest.md` still
at 43 entries. Nothing was archived, nothing was swapped and no gate was re-run: there was no
re-delivery to audit. (A first hash comparison of the two trees returned two *different* numbers;
the pipeline broke on the filenames containing spaces and each side skipped a different set of
files. `diff -rq` was authoritative and the re-measurement agreed with it. **L-016's shape: a broken
measurement returns a confident number** — here it would have manufactured a re-delivery that did
not exist.) The real re-delivery arrived the same day at the same staging path.

**Install.** Wholesale swap per contract rule 10: the previous delivery **moved** — not deleted —
to `docs/old/design-handoff/DR-002/` (123 files), the new one placed whole at
`docs/design/design-handoff/` (123 files), verified `diff -rq` clean against the staged bundle.
Foreign-file scan: **0**.

**Byte-stability diff, old vs new — exactly four files differ, and no file was added or removed.**

| Area | Result |
|---|---|
| `tokens/**` (9 CSS files) | **IDENTICAL** — so the 72/72 contrast recomputation of §1c stands unchanged; not one hex, opacity or type value moved |
| `assets/**` (17 SVG + 48 PNG + 2 woff2 + OFL + sprite) | **IDENTICAL** — and the Dashboard adds **no** glyph: all 13 it names were verified present in the existing 67-symbol sprite |
| `screens/**` (9 prototypes + `_ds/` + 3 support files) | **IDENTICAL** — the 41 drawn screens are untouched |
| `SPEC/accessibility.md`, `color-contrast-audit.md`, `design-tokens.md`, `assets-index.md` | **IDENTICAL** |
| `SPEC/manifest.md` | **Changed — in scope.** 43 → **44 entries**; entry 44 *Dashboard* added (110 lines), and entry 9 *Settings* gains one declared delta (below) |
| `SPEC/screens/template-overview-stats.md` | **Changed — in scope.** One line: `Dashboard` added to its *Used by* list. **No twelfth template**, which is what the request asked for |
| `SPEC/open-questions.md` | **Changed — in scope.** Six new resolved items (13–18); still `**Status: zero unresolved items.**` |
| `README.md` | **Changed — in scope.** The four counts: 41 → 42 screens, 43 → 44 entries, and the distinction "42 screens, 41 of them drawn" |

**Nothing changed outside the named gap.**

**Gate evidence, item by item.** Every row is a command's output.

| Contract item | Evidence | Result |
|---|---|---|
| The Dashboard is specified | `SPEC/manifest.md:325` — entry 44, `index.php` · **overview-stats** · H1 **Dashboard**, with data sources, a 5-card stat row, the widget-grid contract, the empty state as an `<ol>` of three setup steps, the degraded case, accessibility and responsive behaviour | **PASS** |
| No twelfth template invented | `SPEC/screens/` holds **11** files; the manifest references **11**; every referenced file exists | **PASS** |
| Every glyph it names exists in the sprite | 13/13 present (`ks-cloud_upload`, `ks-description`, `ks-key`, `ks-rule`, `ks-task_alt`, `ks-system_update_alt`, `ks-block`, `ks-check_circle`, `ks-chevron_right`, `ks-widgets`, `ks-dashboard_customize`, `ks-warning`, `ks-sync_problem`) — **0 additions, 0 second icon set** | **PASS** |
| Every token it names is defined | `--tinte-aviso`, `--tinte-peligro`, `--sobre-tinte-peligro`, `--texto-sutil`, `--color-exito` (2× each — light + dark), `--type-numeric`, `--type-body-mono` (1× each) | **PASS** |
| Every screen it links to is a real entry | `pages.php`, `design.php`, `mcp.php`, `health.php`, `updates.php`, `ai-images.php`, `profile.php`, `settings.php` — all present in the manifest | **PASS** |
| The accessible-chart rule is not weakened | The Dashboard carries **no chart** and says so; §4's `<details>` data-table pattern is stated to apply **in full** to any plugin widget that draws one | **PASS** |
| `open-questions.md` still at zero | Line 3: `**Status: zero unresolved items.**`; items 13–18 record who decided each | **PASS** |
| No foreign file (rule 10) | 123 files, 0 junk | **PASS** |

**One existing entry changed, declared rather than smuggled — and it is a product change, not a
skin.** Entry 9 *Settings* gains: search-engine and AI-crawler **indexing moves to Settings →
Advanced** as a checkbox + Save gated at `site.configure`, and the Dashboard only *warns* while the
site is blocked (a non-dismissible `--tinte-aviso` `role="status"` banner with a link and **no
toggle**). That is a real behavioural change to shipped code — `installer/admin/index.php` carries
an indexing toggle POST handler today — and it is recorded here rather than absorbed. Design
attributes the decision to the client (`open-questions.md` item 15, "Asked of the client"); items
13, 14 and 15 all carry that attribution, so **the user's confirmation that they answered them is
the one thing this audit cannot verify from the bytes** and it is asked rather than assumed.

**Verdict: the gate PASSES on the re-delivery. DR-002 is RESOLVED. Zero open Design Requests.**

### 1e. The DR-003 re-delivery: install, diff, re-audit (2026-07-29)

Raised at **stage 2 of the build** — not by the gate, which is the finding worth keeping (L-030).
DR-003 named two gaps: the sprite was 19 glyphs short of what the sidebar draws, and the sidebar's
*contents* were specified in no file at all. Both are resolved. Decision: **D-074**.

**Install as a wholesale swap** (contract rule 10): the DR-002-era delivery **moved** — never
deleted — to `docs/old/design-handoff/DR-003/` (123 files), the new one placed whole (**124 files**),
`diff -rq` against the staged bundle **clean**, foreign-file scan **0**. Archives intact: DR-001 49,
DR-002 123, DR-003 123.

**Exactly five files differ and one is added; nothing was removed.**

| File | What changed |
|---|---|
| `assets/icons/klytos-ui-icons.svg` | **67 → 87 `<symbol>`s.** The 19 + `menu`. Nothing removed. |
| `SPEC/navigation.md` | **NEW** — the normative sidebar: eight groups, 34 items, glyphs, targets, counts, plugin placement, the capability rule, the three shell controls |
| `SPEC/assets-index.md` §3 | Glyph list regenerated (87), per-shell glyph block added, the 20 additions enumerated |
| `SPEC/screens/template-shell.md` | Four edits: a pointer to `navigation.md`, the theme toggle marked text-only, `ks-menu` and `ks-chevron_right` named in the responsive table |
| `SPEC/open-questions.md` | Items 19–25 added, all resolved; **still zero unresolved** |
| `README.md` | `navigation.md` added to the file tree and to two pointers |

**Byte-stability, checked rather than accepted.** `tokens/**` **0** differing · `screens/**` **0**
differing · `assets/**` 1 differing (the sprite, which is the point) · `SPEC/accessibility.md`,
`SPEC/color-contrast-audit.md`, `SPEC/design-tokens.md` and `SPEC/manifest.md` **byte-identical**.
So **§1c's 72/72 contrast recomputation stands and does not need re-running**, and the manifest is
still 44 entries — Design dropped *Guides* from the nav rather than inventing a 45th entry.

**The glyph check, run properly this time and in both directions:**

| Check | Result |
|---|---|
| The 19 glyphs DR-003 named, present as `ks-*` | **19/19** |
| Nav glyphs still missing (same command as DR-003) | **0** |
| Symbols removed from the sprite | **0** |
| Geometry of all 87 | `viewBox="0 0 24 24"` ×87, `stroke-width="1.5"` ×87, `fill="none"`, `currentColor`, round caps/joins — identical to the delivered 67 |
| Every `ks-*` **`navigation.md` names** (37 distinct) resolves to a symbol | **37/37** |
| `assets-index.md`'s 35-glyph shell block vs `navigation.md` | **exact match**, both directions; the two it omits (`chevron_right`, `menu`) are the shell controls, called out in the following paragraph |
| `navigation.md` item rows vs its own group totals | 34 rows; 3+6+3+4+3+2+11+2 = **34** |

**The export root was audited before anything was destroyed, and holds nothing new** — the same
shape §1c item 3 recorded: its nine prototype copies differ from the delivery's by the **same single
line** (the ⌘K hint's `top:calc(50% - 5px)` versus `top:50%`; `Redesign.dc.html` carries it twice,
`Current.dc.html` not at all), the three support files and the `_ds` bundle are byte-identical, nine
of thirteen brand SVGs are identical and **the other four are exactly the delivery's `*-text.svg`
live-text originals** (verified by `cmp`, all four). `project/css/klytos-components.css` is again
present at the root and is again **not taken** — it is the file the build writes (build rule 3,
Phase 4 principle 8).

**The sprite already placed in the tree was re-placed** and re-verified: `installer/admin/assets/`
`icons/klytos-ui-icons.svg` is byte-identical to the delivery at 87 symbols, and all **73** other
stage-1 files (brand SVGs, 48 PNGs, fonts, 8 token stylesheets) re-checked **0 differing**
(`fonts.css` excluded — adaptation 1).

**Two findings recorded and neither bounced**, because a second bounce goes to the user (Phase 4
Step 3) and neither blocks:

1. **`navigation.md` §6 names a plugin this install does not have.** It writes "This install ships
   two: *Klytos Forms* (`content.forms`) … and *Klytos SEO* (`content.meta`)". The tree ships
   **five** plugins — `hello-ai`, `klytos-forms`, `klytos-importer`, `klytos-x402-coinbase`,
   `klytos-x402-stripe` — and none is an SEO plugin. It is a wrong example inside a correct rule:
   the capability→group mapping, the after-core-items ordering, the five-item bound with **More
   plugins**, and the `ks-extension` fallback all apply unchanged to the five real plugins. Recorded,
   not built from.
2. **The nav names two screens Phase 4 does not build.** *Comments* (entry 14) and *Health* (entry
   22) are nav items in `navigation.md`, and both were deferred to their own Phase 5 slices by
   **D-072**. This is not a design gap — it is the intersection of two correct decisions — but the
   build cannot resolve it silently: rendering a nav item to a screen that does not exist yet is a
   404 on the primary navigation. **It goes to the user** with the stage-2 report.

**Verdict: the gate PASSES on the re-delivery. DR-003 is RESOLVED. Zero open Design Requests, and
stage 2 of the build is unblocked.**

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

*Sharpened by the §1b re-verification:* the first audit read "no `@font-face` written against a final
project path" from the inventory. Reading the bytes makes it worse and more precise — `tokens/fonts.css`
**does** ship both `@font-face` rules, and both are **dangling**: `src: url("../fonts/Geist-Variable.woff2")`
and `url("../fonts/GeistMono-Variable.woff2")`, against a `fonts/` directory that does not exist at the
bundle root. The same header points the licence at `/fonts/OFL.txt`; **no `OFL*` file exists in the
bundle at all**. So the delivery is not merely silent about fonts — it ships style rules that resolve to
nothing and a licence reference to a file it never sent.

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

> **SUPERSEDED 2026-07-29 by §1c: the re-delivery closes all ten gaps and the gate PASSES.** The
> verdict below is kept because it is what the gate rejected, and a gate whose rejections are deleted
> cannot be audited. The re-delivery's own summary, in one line: **`screens/**` byte-identical, six
> token files changed in comments only, 72/72 contrast ratios independently recomputed and agreeing,
> `open-questions.md` at zero.**

### Superseded verdict (2026-07-27)

**Incomplete.** Two blocking gaps (G1 with G1b/G1c, and G2) and eight further gaps. Per the contract
the build does not start; **DR-001** carries every item above back to Design.

The two blocking ones are worth separating from the rest: G2 is a straightforward inconsistency that a
re-delivery fixes mechanically, while **G1 is the one that would have cost the most if it had been
missed**, because a 41-screen rewrite is simultaneously the best and the last cheap opportunity this
project has to fix an admin measured at ~20–25 % against the standard it has committed to. Building
41 screens to a spec that is silent on accessibility would rebuild the gap at full scale.

## 5. Consolidated build contract

**Written 2026-07-29 (Phase 4 Step 2), from the 11 template specs, `SPEC/manifest.md`,
`SPEC/design-tokens.md`, `SPEC/accessibility.md`, `SPEC/assets-index.md`,
`SPEC/color-contrast-audit.md`, `SPEC/open-questions.md` and the delivered `tokens/*.css`.**
Decision: **D-070**.

This is the implementation contract. The build does not deviate from it; code adapts to it, it
never adapts to the code. Where a value is needed and is not here, the answer is a Design Request
(Phase 4 Step 3), never an invention.

### 5.0 The five build rules that override intuition

Recorded first because each one is a place where the obvious action is the wrong one.

1. **`typography.css` shadows two tokens.** `--type-body` and `--type-caption` are declared
   **twice** — the PackDesk values at lines 48/50, the Klytos values at 91/95. CSS resolves the
   later declaration, so the effective values are `400 13px/17px` and `400 11px/16px`. **The Klytos
   section at the foot of the file is authoritative; never take the first grep match** (§1c).
2. **Tables are real `<table>` elements *and* carry the full explicit ARIA role set.** Not a grid
   of divs, not a table with ARIA bolted on. `SPEC/accessibility.md` §2.1 gives the markup element
   by element; §2.2 names the three surfaces that are **not** tables (Assets grid, Blocks/Templates
   galleries, sidebar nav). The build implements it as written and does not choose between halves.
   The reason both are needed: `display:grid` on `<table>`/`<tr>` strips the implicit roles in
   Chromium and WebKit, so the element survives a CSS regression and the role survives the
   `display` change.
3. **`css/klytos-components.css` is a file the BUILD writes, not a delivered artifact.** The
   delivery references it four times as the build's output. Design's own working copy existed at
   the export's root and was **not taken** — building from it would violate Phase 4 principle 8
   (a prototype is input to Design, never a source for the build). In this project that file is
   `installer/admin/assets/css/klytos-components.css`, which already exists and is rewritten
   against this contract.
4. **No CDN and no Font Awesome.** The prototypes load Font Awesome from `cdnjs.cloudflare.com`;
   that is prototype scaffolding, named by no SPEC file. The icon source is the delivered sprite
   `assets/icons/klytos-ui-icons.svg` (67 `<symbol>`s), served **same-origin** — cross-document
   `<use href>` is same-origin only.
5. **The palette is sacred; where a pair failed, the pattern changed.** No base hex moves. Badge
   and chip text is `--sobre-tinte-*`, support text is `--texto-sutil`, control borders are
   `--borde-control`. A hex in a PHP template is a defect; so is `--texto-terciario` on text.

### 5.1 Resolved screen list

**44 manifest entries: 42 product screens + 1 specimen sheet + 1 plugin-page pattern** (43 after
DR-002 added the Dashboard, §1d). Every entry resolves to a template and a SPEC file. Entries 1–43
are drawn in the six prototype files; **entry 44, the Dashboard, is specified in the manifest and in
`template-overview-stats.md` and is not drawn** — which is a complete specification, not a gap: the
states, breakpoints and accessibility come from the template, exactly as they do for the other
screens. "In tree today" is measured against `installer/admin/` on 2026-07-29 — it is the
**current** state, not a claim about the redesign.

Every authenticated screen also uses **shell** (`template-shell.md`); it is not repeated per row.

| # | Screen | H1 | Template(s) | Entry point (manifest) | In tree today | Manifest |
|---|---|---|---|---|---|---|
| 1 | Pages | Pages | list-table | `pages.php` | `[E]` `pages.php` | §1 |
| 2 | Page editor | the page's title | editor-split | `page-editor.php` | `[E]` `page-editor.php` | §2 |
| 3 | Design (theme) | Design | record-form | `design.php` | `[E]` **as `theme.php`** | §3 |
| 4 | Assets | Assets | gallery-grid | `assets.php` | `[E]` `assets.php` | §4 |
| 5 | Users | Users | list-table | `users.php` | `[E]` `users.php` | §5 |
| 6 | Security | Security | record-form | `security.php` | `[E]` `security.php` | §6 |
| 7 | Analytics | Analytics | overview-stats | `analytics.php` | `[E]` `analytics.php` | §7 |
| 8 | MCP | MCP | record-form | `mcp.php` | `[E]` `mcp.php` | §8 |
| 9 | Settings | Settings | record-form | `settings.php` | `[E]` `settings.php` | §9 |
| 10 | Log in | Sign in to Klytos | auth-centered | `login.php` | `[E]` `login.php` | §10 |
| 11 | Verify | Two-factor authentication | auth-centered | `verify.php` | **ABSENT** — the 2FA branch is inside `login.php` today | §11 |
| 12 | AI chat | Klytos AI | conversation | `ai.php` | `[E]` **as `ai-chat.php`** | §12 |
| 13 | Tasks | Tasks | overview-stats | `tasks.php` | `[E]` `tasks.php` | §13 |
| 14 | Comments | Comments | list-table | `comments.php` | **ABSENT** — `core/comment-manager.php` exists, no admin screen | §14 |
| 15 | Plugins | Plugins | list-table | `plugins.php` | `[E]` `plugins.php` | §15 |
| 16 | *States* | — | — (specimen sheet) | — | n/a | §16 |
| 17 | Setup wizard | the step name | wizard | `setup.php` | see **§5.11 item 2** — the 7 steps are the **installer**, `installer/install.php` | §17 |
| 18 | x402 dashboard | Agent payments | overview-stats | `x402.php` | `[E]` **as `x402-dashboard.php`** | §18 |
| 19 | Content model | Content model | record-form | `content-model.php` | `[E]` **as `post-types.php`** | §19 |
| 20 | Translations | Translations | editor-split | `translations.php` | `[E]` `translations.php` | §20 |
| 21 | Blocks | Blocks | gallery-grid | `blocks.php` | `[E]` `blocks.php` | §21 |
| 22 | Health | Health | overview-stats + console-stream | `health.php` | **ABSENT** | §22 |
| 23 | Terminal | Terminal | console-stream | `terminal.php` | `[E]` `terminal.php` | §23 |
| 24 | Webhooks | Webhooks | record-form + list-table | `webhooks.php` | `[E]` `webhooks.php` | §24 |
| 25 | Consent | Consent | record-form + stats | `consent.php` | `[E]` `consent.php` | §25 |
| 26 | Privacy | Privacy | record-form + list-table | `privacy.php` | `[E]` `privacy.php` | §26 |
| 27 | Profile | Your profile | record-form + list-table | `profile.php` | `[E]` `profile.php` | §27 |
| 28 | Licence | Licence | record-form + overview-stats | `licence.php` | `[E]` **as `license.php`** | §28 |
| 29 | AI images | AI images | editor-split | `ai-images.php` | `[E]` `ai-images.php` | §29 |
| 30 | Options | Options | list-table | `options.php` | `[E]` **as `system-options.php`** | §30 |
| 31 | Templates | Templates | gallery-grid | `templates.php` | `[E]` `templates.php` | §31 |
| 32 | Taxonomies | Taxonomies | list-table + record-form | `taxonomies.php` | `[E]` **as `taxonomy.php`** | §32 |
| 33 | Scheduled actions | Scheduled actions | list-table + stats | `scheduled.php` | `[E]` **as `scheduled-actions.php`** | §33 |
| 34 | System integrity | System integrity | overview-stats + list-table + diff | `integrity.php` | `[E]` **as `system-integrity.php`** | §34 |
| 35 | Updates | Updates | overview-stats + list-table | `updates.php` | `[E]` `updates.php` | §35 |
| 36 | Transactions | Transactions | list-table | `transactions.php` | `[E]` **as `x402-transactions.php`** | §36 |
| 37 | x402 settings | Agent payment settings | record-form | `x402-settings.php` | `[E]` `x402-settings.php` | §37 |
| 38 | *Plugin page* (pattern) | the plugin's name | record-form | `plugins/<slug>/admin.php` | `[E]` `plugin-page.php` is the host | §38 |
| 39 | Post type | the post type's name | record-form | `post-type.php` | `[E]` **as `post-type-edit.php`** | §39 |
| 40 | Block data | the global block's name | editor-split | `block-data.php` | `[E]` `block-data.php` | §40 |
| 41 | Logs | Logs | console-stream | `logs.php` | `[E]` `logs.php` | §41 |
| 42 | Template preview | the template's name | preview-matrix | `template-preview.php` | `[E]` `template-preview.php` | §42 |
| 43 | Reset password | Choose a new password | auth-centered | `reset-password.php` | `[E]` `reset-password.php` | §43 |
| 44 | **Dashboard** | Dashboard | overview-stats | `index.php` | `[E]` `index.php` | §44 (DR-002, not drawn) |

**Reuse is preserved: 11 templates serve 42 screens.** Templates stay single sources; no screen is
built by duplicating another — and DR-002 added a screen **without** adding a template, which is the
reuse contract working rather than being asserted. Consumers per template:

| Template | Consumers | SPEC |
|---|---|---|
| shell | every authenticated screen (41) | `SPEC/screens/template-shell.md` |
| list-table | 1, 5, 14, 15, 24, 30, 32, 33, 34, 35, 36, and the embedded tables of 27, 28, 41 | `template-list-table.md` |
| record-form | 3, 6, 8, 9, 19, 24, 25, 26, 27, 28, 32, 37, 38, 39 | `template-record-form.md` |
| overview-stats | **44**, 7, 13, 18, 22, 25, 28, 34, 35 | `template-overview-stats.md` |
| editor-split | 2, 20, 29, 40 | `template-editor-split.md` |
| gallery-grid | 4, 21, 31 | `template-gallery-grid.md` |
| conversation | 12 + the copilot dock (four modes) | `template-conversation.md` |
| auth-centered | 10, 11, 43 | `template-auth-centered.md` |
| wizard | 17 | `template-wizard.md` |
| console-stream | 22 (panel), 23, 24 (payload), 40 (JSON), 41 | `template-console-stream.md` |
| preview-matrix | 42 | `template-preview-matrix.md` |

**Three screens in the design do not exist in the tree** (11 Verify, 14 Comments, 22 Health) —
handled in **§5.11**, not silently absorbed here. The fourth finding, the Dashboard having no
design, was closed by **DR-002** (§1d) and is now entry 44.

**Phase 4 build scope: 40 of these 44 entries (D-072).** Deferred to their own Phase 5 slices:
**11 Verify** (paired with NEW-38), **14 Comments**, **17 Setup wizard** (product scope; NEW-04
makes `install.php` destructive in a checkout) and **22 Health**. They remain manifest entries and
remain in this table — they leave the build ORDER, not the product. Because entry 17 is the
`wizard` template's only consumer, **`template-wizard.md` is not built in Phase 4 either**; every
other template keeps at least one in-scope consumer.

### 5.2 Token table (canonical — must match the delivery exactly)

Origin: **PackDesk Design System, Paleta B "Mediterráneo"**, inherited unmodified, except
`tokens/klytos-admin.css` and the Klytos section of `typography.css`, which are Klytos's own
contract and win locally (`SPEC/design-tokens.md`). Upstream owns everything else; if a value here
disagrees with upstream, upstream is right and the bundle is stale.

**Load order — normative, `klytos-admin.css` LAST:**

```
colors.css → typography.css → spacing.css → effects.css → motion.css →
glass.css (only --glass-fallback-*) → fonts.css → klytos-admin.css
```
then the build's own `klytos-components.css`. The theme flips by swapping `data-theme` on one
wrapper; the cookie is read server-side so the first paint is already correct.

#### 5.2.1 Colour — semantics and product states (`tokens/colors.css`)

| Token | Light | Dark | Role |
|---|---|---|---|
| `--color-acento` | `#0E8074` | `#3CC3B2` | Primary buttons, active nav, links, focus ring |
| `--color-info` | `#0E7490` | `#3FB7D4` | Neutral informational badges |
| `--color-exito` | `#257D36` | `#56C96E` | Verified, published, healthy, settled |
| `--color-aviso` | `#9A6300` | `#E8A93C` | Retrying, modified, held for review |
| `--color-peligro` | `#C03A35` | `#E6685F` | Failed, destructive actions, 5xx |
| `--color-offline` | `#6E6E73` | `#98989D` | Paused, draft, private |
| `--color-sync` | `#3672D9` | `#6FA0EF` | In-flight / background work |
| `--color-conflicto` | `#7C3AED` | `#A78BFA` | Conflicts, third-party origin |
| `--color-reconectar` | `#C2570B` | `#F08C3E` | Retry / reconnect |

Tints (badge and pill backgrounds) are the same base at **11 % light / 19 % dark**:
`--tinte-acento`, `--tinte-info`, `--tinte-exito`, `--tinte-aviso`, `--tinte-peligro`,
`--tinte-offline`, `--tinte-sync`, `--tinte-conflicto`, `--tinte-reconectar`.

#### 5.2.2 Colour — neutrals

| Token | Light | Dark | Role |
|---|---|---|---|
| `--fondo-ventana` | `#F0F0F2` | `#1E1E20` | Window / sunken field background |
| `--fondo-contenido` | `#FFFFFF` | `#232326` | Content area |
| `--fondo-elevado` | `#FFFFFF` | `#2C2C2E` | Cards |
| `--glass-fallback-sidebar` | `#F2F2F4` | `#26262A` | Sidebar (flat — **no blur, no translucency**) |
| `--glass-fallback-toolbar` | `#F7F7F9` | `#2A2A2E` | Toolbar (flat) |
| `--texto-primario` | `#1D1D1F` | `#F5F5F7` | Body text |
| `--texto-secundario` | `#6E6E73` | `#98989D` | Secondary text — **not permitted on `--fondo-ventana` in light** (4.46:1) |
| `--texto-terciario` | `#86868B` | `#6E6E73` | **Never on text.** Decoration duplicating an adjacent label only |
| `--separador` | `rgba(0,0,0,.08)` | `rgba(255,255,255,.10)` | Row/section separator — **never a control border** |
| `--fila-hover` | `rgba(0,0,0,.045)` | `rgba(255,255,255,.06)` | Row hover |
| `--fila-seleccion` | `rgba(14,128,116,.12)` | `rgba(60,195,178,.24)` | Row/nav/step selection |
| `--foco-anillo` | `#0E8074` | `#3CC3B2` | Focus ring colour |
| `--sobre-acento` | `#FFFFFF` | `#0B0B0C` | Text on an accent fill — **never `#fff` fixed** |

#### 5.2.3 Colour — the Klytos accessibility layer (`tokens/klytos-admin.css`, Klytos-owned)

Every value measured; working in `SPEC/color-contrast-audit.md`.

| Token | Light | Dark | Role |
|---|---|---|---|
| `--sobre-tinte-acento` | `#0C7166` | `#3EC4B3` | Badge/chip text on its own tint |
| `--sobre-tinte-info` | `#0D6C86` | `#4EBDD7` | ” |
| `--sobre-tinte-exito` | `#227231` | `#56C96E` | ” |
| `--sobre-tinte-aviso` | `#8B5900` | `#E8A93C` | ” |
| `--sobre-tinte-peligro` | `#B33631` | `#EC8E87` | ” |
| `--sobre-tinte-offline` | `#646469` | `#ACACB0` | ” |
| `--sobre-tinte-sync` | `#2E62BB` | `#86AFF2` | ” |
| `--sobre-tinte-conflicto` | `#7738E4` | `#B69FFB` | ” |
| `--sobre-tinte-reconectar` | `#A34909` | `#F29851` | ” |
| `--texto-sutil` | `#6D6D71` | `#939397` | **All** support text: hints, eyebrows, column headers, timestamps |
| `--borde-control` | `#86868B` | `#757579` | Border of input, checkbox, radio, switch, secondary button (≥ 3:1) |
| `--texto-deshabilitado` | `#86868B` | `#757579` | Disabled text (Klytos holds a 3:1 floor it is exempt from) |
| `--borde-deshabilitado` | `#B9B9BE` | `#545458` | Disabled border |
| `--foco-grosor` / `--foco-offset` | `2px` / `2px` | same | Focus ring geometry |

The same file ships four **normative CSS rules** the build copies as written into
`klytos-components.css`: `:focus-visible`, `.k-hit-24`, the `prefers-reduced-motion` block and the
`forced-colors` block.

#### 5.2.4 Type — the Klytos section is normative (`tokens/typography.css`, foot of file)

Families: `--font-ui` = **Geist**, `--font-mono` = **Geist Mono**. Weights 400/500/600/700, normal
only — no italic face exists anywhere in the admin.

| Token | Value | Role |
|---|---|---|
| `--type-page-title` | `700 22px/28px` + `--type-page-title-tracking: -0.01em` | Page `<h1>` |
| `--type-card-heading` | `600 13px/16px` | Card `<h2>` |
| `--type-eyebrow` | `600 10px/14px` mono, `--eyebrow-tracking: 0.06em`, uppercase | Section eyebrow |
| `--type-column-header` | `600 10px/14px` mono | Table column header |
| `--type-body` | `400 13px/17px` | Body / table cell — **shadowed token, see §5.0.1** |
| `--type-body-mono` | `400 12px/17px` mono | Mono body / raw values |
| `--type-caption` | `400 11px/16px` | Secondary / hint — **shadowed token** |
| `--type-nav-item` | `400 13px/16px` | Sidebar nav item |
| `--type-nav-item-active` | `500 13px/16px` | Sidebar nav item, active |
| `--type-toolbar-title` | `600 13px/16px` | Toolbar title / last breadcrumb crumb |
| `--type-badge` | `500 11px/16px` | Badge and chip |
| `--type-numeric` | `500 12px/17px` mono, right-aligned | Numeric cell, money, counts |
| `--type-code` | `400 12px/19px` mono | Code / payload block |
| `--type-title-1` | `700 22px/28px` | Inherited, used where the README names it |
| `--type-headline` | `600 13px/16px` | Inherited, used where the README names it |

**Nothing in the admin is below 11px** (one exemption: the 9px file-type pill on asset previews,
which duplicates the extension already in the name). Anything a person types or a machine emits —
email, slug, path, key, version, hash, price, timestamp — is mono.

#### 5.2.5 Spacing, radii, elevation, motion

| Token | Value | Role |
|---|---|---|
| `--esp-2/4/6/8/12/16/24/32/48` | 2/4/6/8/12/16/24/32/48px | Base 4/8 spacing scale |
| `--radio-control` | `6px` | Buttons, fields |
| `--radio-card` | `10px` | Cards, table cards |
| `--radio-popover` | `12px` | Popovers |
| `--radio-pildora` | `999px` | Badges, chips, switch, dots |
| `--sombra-card` | `0 1px 3px rgba(0,0,0,.10)` | The card elevation — **the admin uses two of four** |
| `--sombra-popover` | `0 8px 24px rgba(0,0,0,.18)` | Popovers, floating bulk bar, palette |
| `--easing-estandar` | `cubic-bezier(.32,.72,0,1)` | Entry / standard |
| `--easing-salida` | `cubic-bezier(.16,1,.3,1)` | Exit |
| `--dur-hover` | `120ms` | Hover |
| `--dur-popover` / `--dur-toast` / `--dur-navegacion` / `--dur-cambio-tienda` | 180 / 240 / 220 / 280ms | The 120–280ms band |

Fixed geometry from the README, not tokenised and therefore written once in
`klytos-components.css`: content padding 20 · card padding 20 (0 when the card owns a table) · gap
between cards 14 · gap inside a card 12–15 · table row padding `9–11px 16px` · header row
`8px 16px` · table caption row `13px 16px` · grid `gap: 12px` · status square radius 3 · icon tile
radius 7–9 · sidebar 232px · toolbar 50px · status bar ~33px.

Component dimensions: button `sm` 28px / default 34px / auth 38px · badge 20px pill · chip 24px ·
switch 38 × 22 · checkbox 13px (radius 3, 1.5px border) · radio 14px · field 34px · progress track
8px · stat value `600 20px` mono · stat icon tile 32px · avatar 26px · nav item 30px (radius 6, gap
9, icon 18px).

#### 5.2.6 Inherited and **not** used — do not implement

Recorded so nobody re-adds them "for completeness". They stay in the files (diffability against
upstream) and are marked `HERENCIA NO USADA POR KLYTOS`.

| Token / file | Why it is not used |
|---|---|
| `--type-picking-linea`, `--type-picking-pedido` | PackDesk warehouse density. No Klytos screen is read at arm's length |
| `--type-mono-importe`, `--type-mono-pedido` | Klytos's equivalent is `--type-numeric` — money in the x402 ledger uses `--type-numeric` |
| `--font-native-apple/-windows/-android` | The admin is a browser surface and always renders in Geist |
| `platform.css` in full | No native target. WCAG 2.5.8's 24 × 24 governs targets, not 44pt / 48dp |
| `glass.css` except `--glass-fallback-*` | The admin uses **no** translucency and no blur |
| `--sombra-hoja`, `--sombra-ventana` | No sheets, no window chrome of its own |

#### 5.2.7 The seven token rules (`SPEC/design-tokens.md` §Rules)

1. Never a hex in a template — every colour is `var(--…)`. A hex in a PHP file is a defect.
2. Never a font size in a template — every size is a `--type-*` token via the `font:` shorthand.
3. Never `--texto-terciario` on text — use `--texto-sutil`.
4. Never a raw semantic colour as text on its own tint — use `--sobre-tinte-*`.
5. Never `--separador` as a control's border — use `--borde-control`.
6. Never `#fff` on an accent fill — use `--sobre-acento`.
7. The palette is sacred. If a pair fails, change the **pattern** and record it in the audit.

### 5.3 State matrix, per template

Every row is required unless marked. A template is not done until every row is built; a screen is
not done until its template's rows plus its own deltas (§5.4) are built. Boxes are ticked **in this
file at the moment the state is built**, never retroactively.

#### shell — `SPEC/screens/template-shell.md`
| State | Spec | Built |
|---|---|---|
| Nav item: default / hover / focus / active (`aria-current="page"`, weight 500) | §1 Sidebar | ☐ |
| Nav item with count; **count zero = absent, not "0"** | §1 | ☐ |
| Group caption as a real `<h2>` labelling its `<ul>` | §1 | ☐ |
| Search field (`<form role="search">`, ⌘K pill, works with JS off) | §1 | ☐ |
| Account row (26px avatar `aria-hidden`, name, log-out) | §1 | ☐ |
| Breadcrumb `<nav><ol>`, last crumb `aria-current` and not a link, `›` in CSS | §1 Toolbar | ☐ |
| Toolbar actions — max two, secondary then primary. **Never three** | §1 | ☐ |
| Save state: "Saved 14:03" / "Saving…" / "Not saved — retrying" | §1 | ☐ |
| Sticky toolbar (content scrolls under it) | §1 | ☐ |
| Status bar default (`Klytos x.y.z · PHP x.y.z` / `Rendered in n ms`) | §1 | ☐ |
| Status bar degraded (one fact, `--sobre-tinte-aviso`, never a banner) | §1 | ☐ |
| Status bar offline (`--sobre-tinte-peligro`, no full-screen state) | §1 | ☐ |
| Theme toggle `<button aria-pressed>` + cookie + **no flash of wrong theme**; JS-off link path | §1 | ☐ |
| Command palette: closed / open / typing (combobox §5.11) / no results | §1 | ☐ |
| Skip links ("Skip to content", conditional "Skip to navigation") | §1 | ☐ |
| Responsive ≥1440 / 1200–1439 / 900–1199 icon rail / <900 drawer | §2 | ☐ |
| 200 % zoom at 1280×800 → drawer mode, fully operable; 400 % → 320px | §2 | ☐ |
| Landmark/live-region skeleton exactly as §3's markup | §3 | ☐ |

#### list-table — `SPEC/screens/template-list-table.md`
| State | Spec | Built |
|---|---|---|
| Default (no zebra; 1px `--separador` between rows) | §2 | ☐ |
| Row hover (`--fila-hover`, 120ms; decoration only) | §2 | ☐ |
| Row focus (the **link** takes the ring; the row is not focusable) | §2 | ☐ |
| Row selected (`--fila-seleccion`, `aria-selected`, bulk bar + 48px bottom padding) | §2 | ☐ |
| Sort active (`aria-sort`, chevron, sorting is a page load) | §2 | ☐ |
| Filter active (`aria-current="true"`, "Clear filters" link) | §2 | ☐ |
| Loading = **bulk-action post only** (`aria-busy`, progressive label, no overlay) | §2 | ☐ |
| Empty — no records (header kept, 120px row, icon + sentence + primary action) | §2 | ☐ |
| Empty — filtered to nothing (different sentence, never "create") | §2 | ☐ |
| Error — list could not be loaded (error row + retry + Open Health) | §2 | ☐ |
| Error — bulk action partly failed (`role="alert"` summary, per record) | §2 | ☐ |
| Success (`role="status"` line above the table, not a toast, no timer) | §2 | ☐ |
| Disabled row action (`aria-disabled` + reason in the accessible name, never hidden) | §2 | ☐ |
| Responsive: sticky row-header + scroll container 900–1199; **stacked cards** <900 | §3 | ☐ |
| ARIA table markup exactly as `accessibility.md` §2.1 | §4 | ☐ |

#### record-form — `SPEC/screens/template-record-form.md`
| State | Spec | Built |
|---|---|---|
| Default — never validate on load | §2 | ☐ |
| Hover / focus (ring **and** accent border) / active (colour only) | §2 | ☐ |
| Disabled (reason next to the label, never tooltip-only, never hidden) | §2 | ☐ |
| Read-only vs disabled (`readonly`, mono, selectable, copy button) | §2 | ☐ |
| Dirty (toolbar Save primary; `beforeunload` **and** inline nav confirm) | §2 | ☐ |
| Saving (`aria-busy`, controls stay enabled) | §2 | ☐ |
| Success (`role="status"` under the H1, no highlight animation) | §2 | ☐ |
| Error — field level (`aria-invalid`, icon, message via `aria-describedby`) | §2 | ☐ |
| Error — form level (`role="alert"` summary, focus moved, each field a link) | §2 | ☐ |
| Error — server-side save failure (names cause and action, never a bare code) | §2 | ☐ |
| Empty collection inside a form (sentence + add action, card heading kept) | §2 | ☐ |
| Destructive section (last card, inline two-step confirm, never `confirm()`) | §2 | ☐ |
| Responsive: section nav → chip row at 900–1199; single column; Save stays in toolbar | §3 | ☐ |

#### overview-stats — `SPEC/screens/template-overview-stats.md`
| State | Spec | Built |
|---|---|---|
| Default (stat row 3–5, primary panel, detail cards) | §2 | ☐ |
| Hover (stat `--fila-hover`; **chart readout in a fixed position**, not a floating tooltip) | §2 | ☐ |
| Focus (linked stat card = one `<a>`, ring around the whole card) | §2 | ☐ |
| Loading = **on-demand check only** (indeterminate progressbar + text, page stays live) | §2 | ☐ |
| Empty — no data (`—`, never `0`) + sentence + action | §2 | ☐ |
| Empty — nothing is wrong (good-news state, reads like an answer) | §2 | ☐ |
| Error — a source is unavailable (that card degrades, the page does not) | §2 | ☐ |
| Error — the subject is unhealthy (content, not an error state; failures grouped first) | §2 | ☐ |
| Success after a run (`role="status"`, table repopulates) | §2 | ☐ |
| Disabled run trigger (reason in the name) | §2 | ☐ |
| Chart + its `<details>` data table (mandatory); **table replaces chart below 900** | §3, §4 | ☐ |

#### editor-split — `SPEC/screens/template-editor-split.md`
| State | Spec | Built |
|---|---|---|
| Default (inspector shows document properties, never blank) | §2 | ☐ |
| Block hover / focus (`role="group"` + name) / selected (2px accent, inspector switches) | §2 | ☐ |
| Editing text (`contenteditable` + `role="textbox"`) **and the "Edit as form" fallback** | §2 | ☐ |
| Autosave in flight / saved / failed (`role="alert"` after the 2nd failure; buffer never discarded) | §2 | ☐ |
| Loading (media and AI only; cancellable indeterminate progressbar) | §2 | ☐ |
| Empty — no blocks (inline inserter, not a modal) | §2 | ☐ |
| Error — a block cannot render (still selectable and deletable) | §2 | ☐ |
| Error — publish rejected (inline `role="alert"`, each blocker a link) | §2 | ☐ |
| Success — "Published — <url>" in the status region | §2 | ☐ |
| Disabled publish while blockers exist (count in the accessible name) | §2 | ☐ |
| Responsive: inspector → sheet 900–1199; rail → `<select>` and inspector → modal <900 | §3 | ☐ |

#### gallery-grid — `SPEC/screens/template-gallery-grid.md`
| State | Spec | Built |
|---|---|---|
| Default (`auto-fill, minmax(180px,1fr)`, gap 14) | §2 | ☐ |
| Hover (no transform; preview dims 6 %; actions always in the DOM) | §2 | ☐ |
| Focus (single `<a>` tile, or `<a>` + `<button>` — **never nested interactive**) | §2 | ☐ |
| Selected (2px accent + checkbox, same bulk bar as list-table) | §2 | ☐ |
| Drag-over (Assets) + the always-present "Choose files" keyboard path | §2 | ☐ |
| Uploading (determinate `<progress>` + percentage as text; failure keeps the tile) | §2 | ☐ |
| Empty — nothing uploaded / no blocks / no templates (three sentences) | §2 | ☐ |
| Empty — filtered to nothing | §2 | ☐ |
| Error — preview cannot render (record survives) / library cannot be read | §2 | ☐ |
| Success ("4 files uploaded.", new tiles first, **no `aria-current`**) | §2 | ☐ |
| Disabled tile action (asset in use — reason + usage link) | §2 | ☐ |
| **No infinite scroll anywhere**; pagination is a link | §2 | ☐ |

#### conversation — `SPEC/screens/template-conversation.md`
| State | Spec | Built |
|---|---|---|
| Idle (composer focused full-screen, **never** focused in the dock) | §2 | ☐ |
| Hover / focus (each turn a focusable `role="article"` with a name) | §2 | ☐ |
| Sending (`aria-busy`, composer stays enabled; Enter sends, Shift+Enter newlines) | §2 | ☐ |
| Streaming (`role="log"` polite, additions only; **finished turn announced, not tokens**) | §2 | ☐ |
| Tool call: running / done / failed / **needs permission** (inline two-button confirm) | §2 | ☐ |
| Stopped (partial turn kept, marked, retry offered) | §2 | ☐ |
| Loading history ("Load earlier messages" link; no scroll-triggered loading) | §2 | ☐ |
| Empty — new conversation (3 context starters) / no context available | §2 | ☐ |
| Error — model unreachable / no key (composer replaced by a line + action) | §2 | ☐ |
| Success — an action was applied (transcript is never the only record) | §2 | ☐ |
| Dock modes: docked / collapsed / floating / full, remembered per user | §3 | ☐ |
| Responsive: no docked mode below 1200; **full only** below 900 (`aria-modal`) | §4 | ☐ |

#### auth-centered — `SPEC/screens/template-auth-centered.md`
| State | Spec | Built |
|---|---|---|
| Default + autofocus (**acceptable here and only here**) | §2 | ☐ |
| Focus ring on `--fondo-ventana` (4.23:1 light / 7.64:1 dark) | §2 | ☐ |
| **Submit is never disabled** on these screens | §2 | ☐ |
| Loading (`aria-busy`, fields stay enabled) | §2 | ☐ |
| Error — credentials (`role="alert"`, focus moved, **names neither field**) | §2 | ☐ |
| Error — second factor (specific; 5 failures → wait in words, field `readonly`) | §2 | ☐ |
| Error — expired reset token (the card is replaced entirely) | §2 | ☐ |
| Error — password rules (**rules always visible**, met/unmet in words, `<progress>` + label) | §2 | ☐ |
| Success — login redirect (no interstitial) / reset (`role="status"`, states session effect) | §2 | ☐ |
| Responsive: `min(400px, 100% − 32px)`, top alignment below 640px height, `100dvh` | §3 | ☐ |
| Landmarks: `<main>` only — **no banner, nav, contentinfo, no skip link** | §4 | ☐ |

#### wizard — `SPEC/screens/template-wizard.md`
| State | Spec | Built |
|---|---|---|
| Step upcoming (not a link) / current (`aria-current="step"`) / complete (a link) / blocked | §2 | ☐ |
| Default panel — one decision per step | §2 | ☐ |
| Back absent (not disabled) on step 1; **Continue never disabled** | §2 | ☐ |
| Loading (determinate `<progress>` + operation text; Back unavailable **with a reason**) | §2 | ☐ |
| Error — validation (`role="alert"`, focus moved, each field a link) | §2 | ☐ |
| Error — the step's work failed (**the form keeps what was entered**) | §2 | ☐ |
| Success — step (advance) / finish (states what exists and what was **not** done) | §2 | ☐ |
| Resumable server-side, not `localStorage` | §2 | ☐ |
| Works end to end with JavaScript disabled | §4 | ☐ |
| Responsive: rail 220 at 900–1199; horizontal progress strip + "Steps" disclosure <900 | §3 | ☐ |

#### console-stream — `SPEC/screens/template-console-stream.md`
| State | Spec | Built |
|---|---|---|
A row is ticked when it is built **and driven**. Entry 41 (Logs) built this template
on 2026-08-09; the rows that belong to Terminal (23) stay open until stage 6 builds it,
and are marked so rather than left ambiguous.

| State | Spec | Built |
|---|---|---|
| Default (tail, newest last, scrolled to bottom) | §2 | ☑ 41 |
| Hover (line `--fila-hover` + copy affordance, always in the DOM) | §2 | ☑ 41 — affordance is a SIBLING of the line, not a child (adaptation 15) |
| Focus (container `tabindex="0"` + `role="group"` + label; lines focusable only when they act) | §2 | ☑ 41 |
| Selected line (Logs) — `aria-pressed`, detail panel `<h2>` | §2 | ☑ 41 |
| Following (`role="switch"`; scrolling up pauses it and says so once) | §2 | ☑ 41 |
| Polling — **no `aria-live` on the stream**; counts on a 10-second floor | §2 | ☑ 41 |
| Running (Terminal) — prompt disabled, elapsed seconds, `Ctrl+C` + visible Stop | §2 | ☐ 23 (Terminal, stage 6) |
| Empty — no output / log empty / filtered to nothing (good-news state) | §2 | ☑ 41 (log empty · filtered · no file chosen) · ☐ 23 (no output yet) |
| Error — file cannot be read / command failed (**exit code with its meaning**) | §2 | ☑ 41 (cannot be read) · ☐ 23 (command failed) |
| Success — "Done in 1.2 s", no green banner | §2 | ☐ 23 (Terminal, stage 6) |
| Disabled Download when the file is empty (reason in the name) | §2 | ☑ 41 |
| Truncation at 5,000 lines — stated, never silent, with a download link | §2 | ☑ 41 |
| `white-space: pre` + horizontal scroll below 900 (the one permitted case) | §3 | ☑ 41 — asserted by TRYING to scroll, both directions |

**Two rows carry an open Design Request and are ticked as BUILT, not as passing**
(DR-007, drafted 2026-08-09): a stream line is 19px in its constrained dimension where
`accessibility.md` §7 admits no exception below 24px, and the selected line's specified
colours measure 3.61:1 / 3.83:1. Both are excluded from the automated pass by selector
with their measured values pinned; neither is a build-side choice.

#### preview-matrix — `SPEC/screens/template-preview-matrix.md`
| State | Spec | Built |
|---|---|---|
| Default — 4 real `<iframe>`s at 360 / 768 / 1024 / 1440, **never scaled** | §1, §2 | ☐ |
| Hover ("Open at this width") / focus (each iframe has a `title`) | §2 | ☐ |
| Loading — independent per frame; checks card waits for all four | §2 | ☐ |
| Check passed / failed (fix as a link) / not applicable (with the reason) | §2 | ☐ |
| Blocking — a hard-check failure disables "Set as default", count in the name | §2 | ☐ |
| Empty — no sample content (offers placeholder **or** create) | §2 | ☐ |
| Error — the template cannot render (PHP error in a `<pre>`, file and line) | §2 | ☐ |
| Success — "Set as the default template for Pages." | §2 | ☐ |
| Responsive — one frame at a time + width chip row below 900 | §3 | ☐ |
| Works without JS (four frames rendered server-side) | §4 | ☐ |

### 5.4 Per-screen build ledger

Each screen's own deltas are **in `SPEC/manifest.md`** and are not copied here — copying them would
fork the normative source and the two would drift (L-004's shape). Each row below is ticked only
when its template's §5.3 rows **and** every delta in its manifest entry are built and driven.

| # | Screen | Template rows | Manifest deltas | Accessibility (§5.5) | Driven (§5.9) |
|---|---|---|---|---|---|
| 1 | Pages | ☐ | ☐ §1 | ☐ | ☐ |
| 2 | Page editor | ☐ | ☐ §2 | ☐ | ☐ |
| 3 | Design (theme) | ☐ | ☐ §3 | ☐ | ☐ |
| 4 | Assets | ☐ | ☐ §4 | ☐ | ☐ |
| 5 | Users | ☐ | ☐ §5 | ☐ | ☐ |
| 6 | Security | ☑ | ◐ §6 — see note | ☑ | ☑ |
| 7 | Analytics | ☐ | ☐ §7 | ☐ | ☐ |
| 8 | MCP | ☐ | ☐ §8 | ☐ | ☐ |
| 9 | Settings | ☐ | ☐ §9 | ☐ | ☐ |
| 10 | Log in | ☐ | ☐ §10 | ☐ | ☐ |
| 11 | Verify | ☐ | ☐ §11 | ☐ | ☐ |
| 12 | AI chat | ☐ | ☐ §12 | ☐ | ☐ |
| 13 | Tasks | ☐ | ☐ §13 | ☐ | ☐ |
| 14 | Comments | ☐ | ☐ §14 | ☐ | ☐ |
| 15 | Plugins | ☐ | ☐ §15 | ☐ | ☐ |
| 17 | Setup wizard | ☐ | ☐ §17 | ☐ | ☐ |
| 18 | x402 dashboard | ☐ | ☐ §18 | ☐ | ☐ |
| 19 | Content model | ☑ | ◐ §19 — see note | ☑ | ☑ |
| 20 | Translations | ☐ | ☐ §20 | ☐ | ☐ |
| 21 | Blocks | ☐ | ☐ §21 | ☐ | ☐ |
| 22 | Health | ☐ | ☐ §22 | ☐ | ☐ |
| 23 | Terminal | ☐ | ☐ §23 | ☐ | ☐ |
| 24 | Webhooks | ☐ | ☐ §24 | ☐ | ☐ |
| 25 | Consent | ☐ | ☐ §25 | ☐ | ☐ |
| 26 | Privacy | ☐ | ☐ §26 | ☐ | ☐ |
| 27 | Profile | ☐ | ☐ §27 | ☐ | ☐ |
| 28 | Licence | ☐ | ☐ §28 | ☐ | ☐ |
| 29 | AI images | ☐ | ☐ §29 | ☐ | ☐ |
| 30 | Options | ☐ | ☐ §30 | ☐ | ☐ |
| 31 | Templates | ☐ | ☐ §31 | ☐ | ☐ |
| 32 | Taxonomies | ☐ | ☐ §32 | ☐ | ☐ |
| 33 | Scheduled actions | ☐ | ☐ §33 | ☐ | ☐ |
| 34 | System integrity | ☐ | ☐ §34 | ☐ | ☐ |
| 35 | Updates | ☐ | ☐ §35 | ☐ | ☐ |
| 36 | Transactions | ☐ | ☐ §36 | ☐ | ☐ |
| 37 | x402 settings | ☐ | ☐ §37 | ☐ | ☐ |
| 38 | Plugin page (pattern) | ☐ | ☐ §38 | ☐ | ☐ |
| 39 | Post type | ☑ | ◐ §39 — see note | ☑ | ☑ |
| 40 | Block data | ☐ | ☐ §40 | ☐ | ☐ |
| 41 | Logs | ☑ | ☑ §41 | ☑ (DR-007 open, excluded by selector) | ☑ 30 browser tests, both themes |
| 42 | Template preview | ☐ | ☐ §42 | ☐ | ☐ |
| 43 | Reset password | ☐ | ☐ §43 | ☐ | ☐ |
| 44 | **Dashboard** | ☐ | ☐ §44 | ☐ | ☐ |

**Note on entry 19's `◐`.** Its template rows, accessibility and driven evidence are complete
(16 browser tests, both themes, whole-page axe — D-089). Its **manifest deltas are partial by
decision, not by omission**: the *Statuses (editable set)* card and the *"and orders"* half of §19's
delta are DEFERRED as unbacked product under D-088's standing answer 1, and are recorded in
`docs/roadmap.md` §0c. The box is half-ticked rather than ticked so this file cannot be read as
saying entry 19 is finished — the redesign is not reportable as complete while those two stand.

**Note on entry 6's `◐`.** Same shape, same reason as 19 and 39. Its template rows, accessibility
and driven evidence are complete (35 browser tests, both themes, whole-page axe over four states —
D-091), and it is the FIRST screen in this build whose controls are SWITCHES and the first consumer
of `.k-card--secret`. Its **manifest deltas are partial by decision**: the *Content-Security-Policy*
and *Integrity score* cards are DEFERRED as unbacked product — Klytos SENDS a CSP but has no editor
or store for one, and the integrity data lives on entry 34 with nothing that summarises it into a
score. Both recorded in `docs/roadmap.md` §0c under D-088's standing answer 1. Three cards the
manifest's list does NOT name are built anyway, because they are shipped product with no other
surface (Encryption level, Recovery keys, and the destructive Turn-off card) — logged as §5.9 row 29
rather than left as a silent addition.

**Note on entry 39's `◐`.** Same shape, same reason. Its template rows, accessibility and driven
evidence are complete (28 browser tests, both themes, whole-page axe — D-090), and it is the FIRST
screen in this build to render the template's section nav at all. Its **manifest deltas are partial
by decision**: the *Exposure (REST, MCP, sitemap, feeds)* card is DEFERRED as unbacked product —
`buildPostTypeData()` stores no exposure flags, and switches that change what the outside world can
read are a slice with an authorization review, not a card. Recorded in `docs/roadmap.md` §0c under
D-088's standing answer 1. Two further absences are adaptations rather than deferrals and are logged
as §5.9 rows 23–24 (no Taxonomies card — entry 19 owns it; no *Delete this post type* card — entry
19 already carries the delete, and the question is with the user).

**A drift this table itself carries, recorded rather than quietly corrected.** Entries **1 (Pages)**
and **3 (Design)** are built, driven and evidenced — 31 browser tests each, in `docs/05-test-points.md`
under D-079 and D-088 — and their rows above are still entirely unticked, while entry 41's were
ticked at the time. Phase 4 Step 4 makes THIS FILE the record ("mark each state's §4 row at the
moment it is built — the file is the record, not conversation memory"), so an unticked row for
finished work is exactly the drift the rule exists to prevent. They are left unticked here rather
than ticked from someone else's evidence: ticking a row means the deltas were walked one by one
against the built screen, and this session walked entry 19's, not theirs. **Owed: a per-entry delta
walk for 1 and 3, then their ticks.**

Entry 16 (*States*) is a specimen sheet, not a screen: normative only where it agrees with the
per-template `States` sections, and where it differs **the template file wins**.

### 5.5 Accessibility contract

Target: **WCAG 2.2 AA as the floor**, EN 301 549 (clauses 9, 11) and the European Accessibility Act
— which is exactly this project's D-007 commitment. `SPEC/accessibility.md` is normative and wins
over any prototype. The admin's measured baseline is ~20–25 % (`docs/04-adoption-audit.md`); this
redesign is where that is closed, and none of §1–§9 is a "later pass".

**Contrast is settled and was recomputed by this audit, not copied from Design:** all **72**
declared pairs were recomputed independently with the WCAG relative-luminance formula — **72/72
agree within ±0.06 and zero rows marked PASS fall below their threshold** (§1c). The pair-by-pair
table lives in `SPEC/color-contrast-audit.md`; it is not duplicated here, because a second copy of
72 measured rows is a second thing to drift. The three consequences the build must carry are the
tokens in §5.2.3.

**A box is ticked only for what is BUILT AND DRIVEN.** Stage 3 built the component
layer and drove it against the specimen (`tests/E2E/components.spec.js`, 64 tests:
12 component sections × 2 themes under axe at WCAG 2.2 AA, plus the geometry, the
cascade and the four breakpoints). A component's row therefore means "the component
is correct in isolation and in every state the specimen renders" — **not** that
every screen using it is done; those are stages 4–6, and the per-screen rows in §5.4
stay open. Three contrast pairs are **blocked on DR-005** and are marked as such
rather than ticked.

| Area | What the build must do | Spec | Built |
|---|---|---|---|
| Tables | Real `<table>` + the complete explicit role set, element by element | §2.1 | ☑ CSS + markup contract built and driven 2026-07-29 — the grid is on the table elements (`grid`/`contents`/`grid` read from the browser), all 7 `columnheader`s and one `rowheader` per row present, and the accessibility tree Chromium builds is pinned by an aria-snapshot. Both halves proven by a planted defect: removing one `role="rowgroup"` turns the markup test red, and the snapshot correctly stays green because the implicit role survives — which is the redundancy §2.1 asks for. Screens consume it in stage 4 |
| Not tables | Assets grid, Blocks/Templates galleries, sidebar nav — `role="list"` / `<nav>` | §2.2 | ☐ — sidebar nav done in stage 2; the two galleries are stage 4 |
| Focus indicator | `outline: 2px solid var(--color-acento); outline-offset: 2px`, `:focus-visible`, never removed | §3.1 | ☐ |
| Focus never obscured | Dock, bulk bar and status bar sit in flow or reserve space | §3.1 | ☐ |
| Focus order | Skip link → sidebar → toolbar → main → status bar → dock; **no `tabindex` > 0** | §3.2 | ☐ |
| Overlays | Focus in, trapped, `Esc` closes, focus returns, shell `inert` | §3.2 | ☐ |
| Keyboard | Everything operable; ⌘K the only global shortcut; **no single-character shortcuts** | §3.3 | ☐ |
| Drag alternatives | "Move up"/"Move down" always present, posting a normal form | §3.3 | ☐ |
| Landmarks | One `banner`, one `main`, one `contentinfo`; nav/search/complementary labelled | §4.1 | ☐ |
| Headings | Exactly one `<h1>`; `<h2>` cards; `<h3>` groups; **no level skipped**; eyebrows are not headings | §4.2 | ☐ |
| Components ×12 | Name/role/state per §5.1–§5.12 (button, badge, chip, switch, checkbox, radio, field, progress, stat card, dock, palette, the rest) | §5 | ☑ **ten of twelve** built and driven 2026-07-29 in `klytos-components.css` — button, badge, chip, switch, checkbox, radio, field, progress, stat card, and §5.12's card / code block / toast. **Dock (§5.10) and palette (§5.11) are not this stage's**: the palette was built and driven in stage 2, the dock is stage 6. Geometry read back from the browser, not from the file: button 34/28/38px, badge 20px, chip 24px, field 34px + radius 6 + `--borde-control`, checkbox 13px and radio 14px each inside a real 24 × 24 hit area, switch 38 × 22 in a ≥ 24px row, stat tile 32px, progress 8px |
| Switch vs checkbox | Immediate effect = `role="switch"`; needs Save = checkbox — **decided per screen in the manifest** | §5.4 | ☑ both built as distinct components with distinct markup contracts; **which one each screen uses is still decided per screen** in stages 4–6, from the manifest |
| Errors | `aria-invalid` + icon + word + sentence; summary in `role="alert"`, focus moved | §6 | ☑ built and driven — the field error carries all four channels (`aria-invalid`, the icon, the word, the border) with hint FIRST in `aria-describedby`; the summary is `role="alert"` + `tabindex="-1"` listing each failed field as a link to it. **`--color-peligro` on `--fondo-elevado` measures 4.32:1 in dark → blocked on DR-005**, not ticked away |
| Live regions | **Exactly two per page**, in the shell: one `role="status"` polite, one `role="alert"` | §6 | ☐ |
| Session timeout | Warns ≥ 20s before acting and can be extended without data loss | §6 | ☐ |
| Target size | 24 × 24 via `.k-hit-24`; the drawing does not change size | §7 | ☑ for the components built here, measured from the `::before` pseudo-element itself (24 × 24) while the checkbox still draws 13px and the radio 14px. The error summary's stacked field links gained 8px separation after axe found them under the threshold — §7's "inline link" exception is for prose, not for a list of targets |
| Zoom & reflow | 200 % at 1280×800 and 400 % (320 CSS px); no page-level horizontal scroll | §8 | ☑ at 320 CSS px, asserted by TRYING TO SCROLL rather than by comparing `scrollWidth`. This is where the stage's sharpest defect was: `.k-sr` is `position:absolute`, the table's "Actions" header uses it, and with no positioned ancestor it laid out at its static position inside the 670px row — escaping the scroll container AND the card's `overflow:hidden`, and scrolling the whole page 346px. Every containment reading in the chain said 280-inside-320 while the page really scrolled. Fixed by making `.k-table-scroll` a containing block; re-proven by removing it and watching the 346 return |
| Text spacing | 1.4.12 values with no clipping — no fixed-height text containers | §8 | ☐ |
| Forced colors | The `forced-colors` block is normative; tints vanish, borders and `CanvasText` take over | §8 | ☐ |
| Reduced motion | The `prefers-reduced-motion` block is a rule; the sync dot stops pulsing and stays lit | §9 | ☑ for this layer — driven under `prefers-reduced-motion: reduce`, every transition the components declare (button, chip, switch, thumb) collapses to 1ms from `klytos-admin.css`, and nothing here reintroduces a longer one |
| Generated HTML floor | §10's seven points — two of them enforced from inside the admin (publish blockers; theme editor contrast refusal) | §10 | ☐ |

**Documented exceptions — the only ones that exist** (§7): inline links in running text, and the
drag handle (an equivalent alternative exists and it is drawn 24 × 24 anyway). A badge is exempt
because it is not interactive. Any other small target is a defect.

### 5.6 Interaction and logic table

The governing fact: **this is a multi-page PHP app.** Most interaction is a form post and a
redirect. Filters, pagination, sorting and tab switching are **links with query parameters**, which
is what keeps the admin usable with JavaScript disabled.

| Trigger | Behaviour | Condition / gating | Spec |
|---|---|---|---|
| Filter chip click | Page load with the query parameter; active chip = the one whose href matches the current query, `aria-current="true"` | Never a tab, never a button, never a checkbox (except multi-select Logs levels) | README §Interactions; a11y §5.3 |
| Sort a column | Page load; `<th>` carries `aria-sort`; the control is an `<a href>` | — | list-table §2; a11y §2.1 |
| Paginate | `<nav aria-label="Pagination">` of links; current page not a link | — | list-table §4 |
| Select rows | Checkbox column raises the bulk bar (48px, pinned, `<form>` with submit buttons); content gains 48px bottom padding | ≥ 1 row selected | list-table §2 |
| Bulk action submit | Form post; button `aria-busy`, progressive label, other controls disabled, rows stay readable | — | list-table §2 |
| Save a form | Form post; **primary Save lives in the toolbar**, same button on every form screen; `Enter` in a text field submits | — | record-form §1, §4 |
| Destructive action | Inline two-step confirm — the **same button** relabelled, `aria-live="polite"` on its wrapper. **Never a browser `confirm()`** | — | README; a11y §5.1 |
| Theme toggle | Swaps `data-theme` on the wrapper **and writes a cookie**, so the server renders the right theme next request — no flash. JS-off path: a link with a query parameter setting the same cookie | JS enhanced, JS-off equivalent required | shell §1 |
| ⌘K / Ctrl+K | Command palette overlay: `role="dialog" aria-modal`, shell `inert`, combobox semantics, `Esc` closes and returns focus | The only global shortcut | shell §1; a11y §5.11 |
| Copilot dock mode | docked / collapsed / floating / full, remembered per user; open moves focus to the dock heading, close returns it to the launcher | Docked unavailable < 1200; full-only < 900 | conversation §3, §4 |
| Copilot sends a message | `Enter` sends, `Shift+Enter` newlines; streaming announced once on completion, never per token | Composer never auto-focused in the dock | conversation §2 |
| Copilot wants to write | **Two-button inline confirm inside the transcript**, effect spelled out, focus moved to it, `Esc` denies | Every write, always | conversation §2 |
| Terminal command | Real form post; output appended above the prompt; `Ctrl+C` and a visible Stop cancel; autocomplete via `api/terminal-autocomplete.php` with combobox semantics | — | console-stream §2, §4 |
| Log tail | **Poll, never a socket.** Stream is not `aria-live`; counts announced on a 10-second floor. Scrolling up turns Follow off and says so once | Follow switch on | console-stream §2 |
| Autosave (page editor) | Posts to `api/autosave.php` on idle; state shown in the toolbar; **never a modal, never steals focus**; the buffer is never discarded on failure | Editor screens | editor-split §2 |
| Block reorder | Drag **plus** always-present "Move up"/"Move down" posting a normal form | — | editor-split §4 |
| Publish a page | Hard blockers: no `<h1>`, an image with no alt decision. Rejection is an inline `role="alert"` panel, each blocker a link | Blockers present → publish disabled with the count in its name | editor-split §2; a11y §10 |
| Save a theme pair (Design) | The editor **refuses to save a text/background pair below 4.5:1** without a recorded override; the ratio is shown next to every pair | Always | manifest §3; a11y §10.7 |
| Save an AI image | **Cannot be saved to the library until its alt text is written**; the AI draft must be confirmed | Always | manifest §29; a11y §10.2 |
| Upload an asset | Tile appears immediately with a determinate `<progress>` + percentage as text; failure keeps the tile with a retry | Drop zone or "Choose files" | gallery-grid §2 |
| Set a template as default | Disabled while a **hard** check fails, count in the accessible name | preview-matrix hard checks | preview-matrix §2 |
| Rotate the HMAC secret | Two-step inline confirm stating the consequence | Webhooks | manifest §24 |
| Revoke a session | Inline confirm naming the device | Profile | manifest §27 |
| Consent banner config | **"Reject all" is the same component, size and prominence as "Accept all"** — the option to make it less prominent does not exist | Always | manifest §25; a11y §10.4 |
| Personal preferences (Profile) | Switches/selects taking effect immediately — personal and reversible | Profile only | manifest §27 |
| Tool exposure (MCP), exposure (Post type) | **Checkboxes + Save**, never switches — they change what the outside world may do and are reviewed as a set | MCP, Post type | manifest §8, §39 |
| Plugin activation | A form button, **not** a switch, because activation runs a migration | Plugins | manifest §15 |
| Indexing is blocked (Dashboard) | A non-dismissible `--tinte-aviso` **`role="status"`** banner after the `<h1>`, with `ks-block` and a link to `settings.php#advanced` — **and no toggle**. It disappears when the condition does; enabling indexing produces no banner and no confirmation | `indexing_enabled` false | manifest §44 |
| Change indexing (Settings → Advanced) | Checkbox + Save with the consequence stated next to it | `site.configure` | manifest §9 delta (DR-002) |
| Dashboard stat card | The **whole card** is one `<a>`: Last build → `updates.php`, Pages → `pages.php`, MCP → `mcp.php`, Failing checks → `health.php`, Pending updates → `updates.php`. A zero count renders `0` (on a stat card the number is the answer) | — | manifest §44 |
| **Build now** (Dashboard setup panel) | The screen's **only write**: a form post with CSRF, confirmed through the shell's single `aria-live="polite"` region ("Build finished — 3 pages published"). **Never a link** | Step 3 reachable; otherwise `disabled` with the reason in its name | manifest §44 |
| Choose dashboard widgets | A link to `profile.php#preferences`, where visibility is a checkbox set in `<fieldset legend="Dashboard widgets">` + Save — **not** an inline menu on the Dashboard, and no "widget hidden" placeholder is ever rendered | — | manifest §44 |

**Client state, and the complete list of it:** theme, copilot dock mode, sidebar collapse, table
density, last-used filter per list screen. All cookies or `localStorage`, all optional. Everything
else is server-side per request.

**The seven features that legitimately need JavaScript** — and each has a non-JS path: theme
toggle, command palette, copilot dock, terminal, inline edit/autosave, log tail, bulk actions.

### 5.7 Asset map

Source: `SPEC/assets-index.md`. Every asset is delivered in a format the build uses **directly** —
no conversion, resize, recolor, rasterisation or re-export happens on the build side.

| Asset group | Files | Formats delivered | Target path in the project tree | Placed |
|---|---|---|---|---|
| Brand marks | `klytos-icon.svg`, `-circle`, `-gradient`, `-mono`, `-tile`, `-android-background`, `-android-foreground`, `klytos-favicon.svg` (8) | SVG (120×120, 108×108 for the Android layers) | `installer/admin/assets/images/brand/` (adaptation 3) | ☑ placed 2026-07-29, byte-identical |
| Wordmark & lockup | `klytos-wordmark{,-dark}.svg`, `klytos-lockup{,-dark}.svg` — **outlined paths, no font dependency** | SVG (200×72 / 250×72) | `installer/admin/assets/images/brand/` (adaptation 3) | ☑ placed 2026-07-29, byte-identical |
| Editing sources | `klytos-{wordmark,lockup}{,-dark}-text.svg` (4) — live text, **not for production**, require Geist Bold | SVG | not shipped to the admin; kept in the delivery only | ☐ n/a |
| PackDesk seal | `packdesk-seal.svg` | SVG 120×120 | `installer/admin/assets/images/brand/` (adaptation 3) — wizard Finish step, **deferred with entry 17 (D-072)** | ☑ placed 2026-07-29, byte-identical |
| PNG raster set | **48 files** — favicon 16/32/48/180/192/512 · icon 128/256/512/1024 · gradient 512/1024 · tile 71/150/310 · circle 128/256/512 · android bg+fg 108/162/216/324/432 · mono black+white 128/256/512 · seal 128/256 · wordmark & lockup (light+dark) @1x/@2x/@3x | PNG | `installer/admin/assets/images/brand/png/` (adaptation 3) | ☑ 48/48 placed 2026-07-29, byte-identical |
| UI icon sprite | `klytos-ui-icons.svg` — **87 `<symbol>`s** (67 at first delivery, +20 by DR-003), Material Symbols Outlined geometry, 1.5px strokes, `currentColor`, `fill:none` | SVG sprite | `installer/admin/assets/icons/klytos-ui-icons.svg` — **same-origin** | ☑ re-placed 2026-07-29 after DR-003, byte-identical, **87** `<symbol>`s verified |
| Fonts | `Geist-Variable.woff2`, `GeistMono-Variable.woff2` (`wght` 100–900, normal only) + `OFL.txt` | woff2 + licence text | `installer/admin/assets/fonts/` | ☑ 3/3 placed 2026-07-29, byte-identical, all four `url()`s resolve |
| Token stylesheets | 9 CSS files | CSS | `installer/admin/assets/css/tokens/` | ☑ 9/9 placed 2026-07-29 — 8 loaded in SPEC order, `platform.css` deliberately not linked |

**The font path rule is mechanical, and it is the one place a build-side edit is expected.**
`tokens/fonts.css` resolves `../assets/fonts/…` relative to its own directory, which assumes
`admin/tokens/fonts.css` + `admin/assets/fonts/`. This project's stylesheets live in
`installer/admin/assets/css/`, so **either** the tokens go to `installer/admin/assets/css/tokens/`
and the fonts to `installer/admin/assets/fonts/` (the two `url()`s then need `../../fonts/…`),
**or** the layout the handoff assumes is reproduced verbatim. `SPEC/assets-index.md` §2 explicitly
authorises changing **only those two `url()` values** and states that nothing else depends on the
font path. Whichever is chosen is recorded in §5.10 as a code-side adaptation, with the two-line
diff.

> **RESOLVED 2026-07-29 (stage 1 of the build).** The first option was taken: tokens at
> `installer/admin/assets/css/tokens/`, fonts at `installer/admin/assets/fonts/`, paths repointed to
> `../../fonts/`. **Note the count: both `@font-face` rules list each file twice** (a
> `woff2-variations` hint and a `woff2` fallback), so "the two `url()`s" is **four lines** carrying
> two distinct paths. All four were repointed — changing only the two `src:` lines and missing the
> two continuation lines was caught by diffing against the delivery, and would have shipped a 404 on
> the fallback. Verified: all four resolve on disk, and all three font files answer **200** over
> HTTP. Adaptation 1 in §5.9.

Icon usage rules, as delivered: sizes **15px** in a table cell · **16–17px** in a list row or
toolbar · **18px** in the sidebar · **22–26px** in a drop zone or empty state; never below 15, never
above 26. Decorative icons (the overwhelming majority) are `aria-hidden="true"`; for an icon-only
control the **control** carries the `aria-label` and the `<svg>` stays `aria-hidden`. A glyph the
sprite lacks is added as a `<symbol>` in the same geometry — never a second icon set, never an emoji
or a Unicode character. **The admin has no emoji.**

> **RESOLVED 2026-07-29 — DR-003 (D-074), §1e.** The re-delivery took the sprite to **87 symbols**:
> the 19 the sidebar draws plus `ks-menu` for the drawer button. Zero nav glyphs remain missing, zero
> symbols were removed, and the geometry of all 87 is identical. The sidebar's contents are now
> normative in **`SPEC/navigation.md`** (eight groups, 34 items, glyphs, targets, counts, plugin
> placement, capability rule), and the three unnamed shell controls are settled: drawer =
> `ks-menu`, "Expand navigation" = `ks-chevron_right`, theme toggle = **text-only, no glyph**. The
> gap as it was found is kept below, because it is the evidence L-030 rests on.
>
> **GAP FOUND 2026-07-29 at stage 2 of the build — DR-003 (D-073).** The rule above ("a glyph the
> sprite lacks is added as a `<symbol>`") is written for the one-off case, and the shell is not that
> case. The union of every prototype's `navGroups` names **35** glyphs for the sidebar; **16 are in
> the sprite and 19 are not** — `account_circle` `category` `checklist` `cookie` `dynamic_form`
> `forum` `group` `menu_book` `monitor_heart` `monitoring` `palette` `perm_media` `policy` `preview`
> `receipt_long` `shield` `space_dashboard` `translate` `workspace_premium`. Three shell controls
> have no glyph named at all (the <900 drawer button, the rail's "Expand navigation" button, the
> theme toggle). The contradiction is inside the delivery — `SPEC/assets-index.md` §3 calls the
> sprite "one `<symbol>` per glyph the design uses" and names it as the sidebar's source at 18px.
> **The build does not draw them, does not substitute a present glyph, and does not load a second
> icon set:** `docs/design/design-requests/DR-003.md` is drafted and the shell's sidebar is blocked
> until it resolves. A per-screen glyph-presence check joins the Step 1 gate in the same pass —
> `open-questions.md` item 18 ran exactly that check for the Dashboard and it was never run for the
> shell.

Deliberately not in the delivery, and therefore not to be invented: no PNG of the UI icons, no icon
font, no photography or illustration, no favicon `.ico` (build it from the 16/32/48 PNGs if a legacy
one is ever needed).

### 5.8 External manual setup, and externally generated assets

**No external manual setup is required.** The redesign configures no external software: the
delivery ships no `SPEC/external-setup.md` and §3 of this document records that as genuinely not
applicable, confirmed against what the screens actually need. No Step 5 guided loop runs, and
therefore no delegation tag applies.

**No externally generated assets are required.** No `SPEC/external-assets.md`; the admin has no
photography, no illustration and no decorative imagery, and the fonts ship under the SIL OFL rather
than needing an acquisition walkthrough. No Step 6 loop runs.

If either becomes false mid-build, that is a Design Request — not a build-side improvisation.

### 5.9 Target-stack integration plan

This is the **only** place code-side adaptation is allowed, and it may not change design intent.

**Stack.** PHP 8.1+, no framework, server-rendered multi-page app, vanilla CSS and JS, no bundler,
no build step for the admin's own assets. That is what the handoff assumes, and it is what the
project is — the two agree, which is why this section is short on adaptation and long on placement.

| Design concept | How it is represented here |
|---|---|
| Shell | `installer/admin/templates/header.php` + `sidebar.php` + `footer.php`, built once; every page fills the content slot. **Its nav contents are normative in `SPEC/navigation.md`** (DR-003), which wins over any prototype's `navGroups` array |
| Templates (11) | Shared PHP partials under `installer/admin/partials/` plus the component CSS — **a template is one source consumed by N screens, never copied per screen** |
| Screens (41) | The existing page controllers in `installer/admin/`, per §5.1; three new controllers for Verify, Comments, Health (§5.11) |
| Tokens | The nine delivered CSS files, copied unmodified into the admin's stylesheet chain, `klytos-admin.css` **last** |
| Components | Rewritten `installer/admin/assets/css/klytos-components.css` — button, badge, chip, card, table, field, switch, checkbox, radio, stat card, progress, code block, empty/error state |
| Icons | The delivered sprite, served same-origin; `<svg aria-hidden="true"><use href="…#ks-name">` |
| Copilot dock | `<aside role="complementary" aria-label="Klytos AI">` after `main` in the DOM, in every mode |

**Project conventions that bind every file this build touches** (`docs/03-technical-plan.md` §2b
and §3 — the change map's "New admin page or API endpoint" row):

1. `klytos_has_permission( 'domain.action' )` **inside** every page and endpoint, never inferred
   from the includer (NEW-31 is exactly that defect).
2. `klytos_csrf_field()` in every form, `klytos_verify_csrf()` in every mutating handler.
3. `nonce="$cspNonce"` on every inline `<script>`/`<style>`; **no inline `onclick`/`onchange`** —
   `addEventListener` inside a nonced block.
4. Escaping at print time: `klytos_esc_html/attr/url/js/textarea`, `klytos_kses` for rich HTML.
5. **Every user-facing string goes through `__( 'domain.key' )` and the key is added to all 20
   locale catalogues in the same change** — the parity check in `scripts/keel-verify` enforces it.
   The design's copy is the **English source**; the delivery's standing assumption 4 states the
   strings are substitutable with +30 % expansion headroom, so this is integration, not deviation.
   It is also the largest hidden cost in this build and is sized as such in §5.11.
6. **A stable `data-testid` on every interactive element**, `<screen>.<element>[.<entity-id>]`,
   never the visible text and never a faked accessibility label (Keel v5.0.0 addressability;
   `keel-verify` will check it).
7. PSR-12 as adapted by `phpcs.xml` — **spaces inside parentheses**, `foo( $bar )`. This is the
   project's style; it is not "corrected".
8. Time is stored UTC and displayed local (`klytos_gmdate` / `klytos_date` / `klytos_timezone`);
   `<time datetime>` in every timestamp cell, as the accessibility spec requires.
9. Every meaningful decision fires a hook and every user-facing string, query and response should
   be filterable — the project's standing extensibility rule.
10. **Klytos ships no minified pair of its own** (D-038, measured 2026-07-28: all 90 `*.min.*` files
    are third-party vendor distributions). The admin's own CSS/JS ships unminified; if that ever
    changes, the pair rule and a local build script arrive with it.

**Code-side adaptations log** — each is a code change with the design result identical:

| # | Adaptation | Why the stack forces it | Design intent intact? |
|---|---|---|---|
| 1 | `tokens/fonts.css`'s two `url()` values may change to match this project's stylesheet directory | The handoff's assumed layout (`admin/tokens/`) is not this project's (`admin/assets/css/`) | Yes — `SPEC/assets-index.md` §2 authorises changing exactly these two values and nothing else |
| 2 | Entry-point filenames stay as they are in the tree (`theme.php`, `ai-chat.php`, `license.php`, `system-options.php`, `taxonomy.php`, `scheduled-actions.php`, `system-integrity.php`, `x402-transactions.php`, `x402-dashboard.php`, `post-types.php`, `post-type-edit.php`) rather than being renamed to the manifest's names | Klytos is **released, with installs**; a filename is a URL, and renaming breaks bookmarks, sidebar links and any plugin that links to a core admin page | Yes — a filename is not a design value. The manifest's names are recorded here as the mapping; no visual or behavioural value changes |
| 3 | Brand assets are placed at `installer/admin/assets/images/brand/` (+ `png/`), **not** `assets/img/brand/` as §5.7 first wrote it | The tree's existing image directory is `installer/admin/assets/images/` (9 files: the provider logos and the current 120px mark). Creating a second `assets/img/` beside it would leave two image roots and no rule for which is which | Yes — a directory name is not a design value, exactly as adaptation 2 records for filenames. Every byte is unchanged; §5.7's paths were corrected to the as-built ones |
| 4 | `tokens/platform.css` is placed on disk but **not linked** in the stylesheet chain (8 of the 9 files load) | Nothing forces it — this is the delivery's own instruction, not a stack constraint: `SPEC/design-tokens.md` lists "platform.css in full" under *Inherited and not used — do not implement* (native densities, touch targets, window minimums; the admin is a browser surface) | Yes — implementing it would be the deviation. It stays on disk so the token set remains diffable against upstream PackDesk |
| 5 | Font Awesome (`assets/vendor/fontawesome/`) is **still loaded** alongside the new sprite, contrary to build rule 4 (§5.0) | Every shipped screen currently draws its icons from it. Removing the stylesheet before its consumers are ported would strip the icons from all 40 screens at once | **Temporary, and tracked as such.** It is not a permanent adaptation: it is retired when the last screen stops referencing it, and the redesign is not complete while this row stands. Re-checked at Step 7 |
| 6 | The shell's own CSS is a new file, `installer/admin/assets/css/klytos-shell.css`, and `klytos-sidebar.css` leaves the stylesheet chain | §5.9's table assigns a file to *Components* (`klytos-components.css`, stage 3) and to *Tokens*, but names no stylesheet for the **shell** — it names only the three PHP templates. `klytos-sidebar.css` styled the previous `.admin-sidebar` / `.admin-topbar` markup, which stage 2 no longer emits, so leaving it loaded would style nothing and mislead the next reader | Yes — a stylesheet's filename is not a design value, exactly as adaptations 2 and 3 record for entry points and directories. Every declared value traces to the delivered tokens: all **31** custom properties the file consumes were checked against the nine `tokens/*.css`, **0 undefined** |
| 7 | The legacy `.admin-layout` / `.admin-content` / `.admin-main` wrappers are NOT carried onto the new nodes as secondary classes | Considered and rejected: `klytos-base.css` gives those classes a `flex` layout with `margin-left: var(--klytos-sidebar-width)`, which actively fights the grid the shell now uses. Keeping them would have produced a shell that is styled twice and correct neither way | Yes. Only three page files referenced them: `page-editor.php` and `ai-chat.php` (full-screen overrides, ported to the new class names in the same change, with their real answer belonging to stage 6 and their own templates) and `updates.php`, which opened a stray fourth `.admin-main` the old footer never closed — a pre-existing imbalance, removed |
| 8 | The theme toggle POSTs to `admin/api/theme.php` instead of following "a link with a query parameter" | `template-shell.md` §1 describes the JS-off path as a link. A link is a GET; this project does not change state on a GET and requires CSRF on every mutating handler, and a bare link would let any page flip a person's theme. The endpoint is gated at the existing self-service capability `ui.preferences` — the same one `api/notices.php` and `api/sidebar-order.php` use — so no new capability was invented | Yes — the normative part is untouched: a `<button aria-pressed>` whose visible text states the TARGET state, text-only with no glyph (`navigation.md` §8), and a **server-set cookie so the next request renders the right theme with no flash**. Driven and verified: dark → light flips `<html data-theme>` server-side with `aria-pressed` tracking it, no CSRF → 403, GET → 405, an invalid value is ignored, and `redirect_to` refuses absolute and protocol-relative targets |
| 9 | `klytos-components.css` is **rewritten as the redesign's `.k-*` layer**, and the file's previous 991 lines are moved unmodified to a new `installer/admin/assets/css/klytos-legacy-components.css` that loads immediately BEFORE it | Build rule 3 names `klytos-components.css` as the file the build writes, and it is also the live stylesheet of every screen the redesign has not reached: measured 2026-07-29, its classes are drawn by up to **40** admin PHP files (`.btn` 40, `.card` 37, `.alert` 37, `.form-control` 27, `.table-wrap` 14, `.stats-grid` 9 …). Rewriting it in place would strip the styling from all of them in one commit | Yes. The redesign's layer occupies the filename the contract names, with nothing of the design changed; the legacy rules are moved byte-for-byte (verified: the 991-line body `diff`s clean against the original before the original was replaced) and load first, so a `.k-*` class always wins. **Temporary and tracked exactly like adaptation 5** — the file is deleted when its last consumer is ported, and the redesign is not reportable as complete while it exists |
| 10 | The secondary button's border is `--borde-control`, not the `--separador` the README's component inventory names | Not a stack constraint — a conflict INSIDE the delivery, resolved toward the normative half. `tokens/klytos-admin.css` lists "botón secundario" explicitly among the controls whose border must be `--borde-control`, because `--separador` measures 1.19:1 and WCAG 1.4.11 requires 3:1 for a control boundary; §5.0 rule 5 says the same | Yes — and it is the delivery's own instruction. The README's component line predates the accessibility layer, which is loaded last precisely so it wins. Verified in the browser: the field and button borders resolve to `#86868B` (light) / `#757579` (dark) |
| 11 | The under-900 stacked record cards are rendered as a SECOND markup alongside the table, and CSS shows exactly one | `template-list-table.md` §3 requires a real markup change at that width — `<article>` + `<h3>` + `<dl>`, with "the ARIA table roles going away with the table". CSS cannot change a role, and the server has no viewport, so the only alternatives were a JavaScript transform at the breakpoint — which would make the under-900 layout the one part of this shell that depends on JS — or shipping only one of the two. Rendering both and hiding one with `display:none` removes the hidden branch from the accessibility tree as well as from the page, so assistive technology is never offered the record twice | Yes — driven at 800px and 320px: `.k-reclist` visible with its `<dl>`, `.k-table` hidden, and the page does not scroll horizontally. Design intent untouched; only the delivery mechanism adapts |
| 12 | The table's scroll container carries `tabindex="0"` at EVERY width, where §2.1 words it as "when the table scrolls horizontally below 1200px" | The server has no viewport. Adding the attribute with JavaScript on resize would make the keyboard path to the scroll depend on JS; omitting it would remove that path at exactly the width that needs it. An always-focusable labelled group at a width where it happens not to scroll is a superset of the spec, not a departure from it | Yes — `role="group"` + `aria-label` present at all widths, `overflow-x: auto` and the sticky row-header column verified at 1024px |
| 13 | The Logger writes **eight** PSR-3 levels where `template-console-stream.md` §1 names four (`ERROR`, `WARN`, `INFO`, `DEBUG`) | The level WORD shown is always the real one, so nothing is hidden and §4's "a monochrome print of a log screen is fully readable" still holds. Only the TINT is mapped, and it is mapped by the delivery's own ordering rather than by a choice made here: at or above `error` takes the error tint, `warning` takes the warn tint, and §1's "ERROR and WARN only" means nothing below is tinted at all | Yes — driven on entry 41: a `CRITICAL` line prints the word CRITICAL and carries `k-line--error`; `INFO` and `DEBUG` lines carry no tint class at all, which is asserted rather than assumed |
| 14 | The Logs error state does not render §2's **"Open Health"** action | `health.php` does not exist: Health is manifest entry 22 and D-072 deferred it to its own Phase 5 slice, which is the same reason D-075 omitted it from the sidebar. A link that 404s **from an error state** is worse than the state without it. "Choose another file", the other action §2 names, is rendered. The action returns with the screen it points at | Yes — the error state is driven (mode 0000 fixture) and offers exactly one action. Recorded so the omission is a decision, never a forgotten row |
| 15 | The per-line copy affordance is a **sibling** of the line, not a child of it | §2 asks for both a line that is a `<button>` spanning the row and a copy affordance at its right. A button inside a button is invalid HTML — the parser unnests it, so the copy control would land somewhere nobody chose. The row is a positioned wrapper holding the two as siblings: the line still spans the row and still has the line's text as its name, the affordance is still at the right and still always in the DOM (`opacity`, never `display:none`, so a keyboard can reach it) | Yes — driven: the affordance is attached before any hover, reveals on hover AND on `:focus-within`, copies the real line (read back out of the clipboard), and clicking it does not also select the line |
| 16 | The detail panel's body is the line's **context JSON**; there is no separate "stack" field | §1 says the panel carries "context + stack for the selected line". The stored line is `[ts] [LEVEL] [source] message {json}` (`Logger::write()`), so the trailing JSON **is** the context, and a stack appears only where a caller logged one into it. The design is unambiguous about what the panel shows; the data model expresses it as one structure rather than two | Yes — driven: selecting the ERROR line renders its two context keys, and a line with no context says so rather than showing an empty panel |
| 17 | Follow polls `admin/api/logs.php`'s existing `read` action; only **Download** gets a new endpoint | The `read` action is already gated at `site.configure`, CSRF-checked and rate-limited (30/min), and returns `lines` **and** `total` — polling it with `offset = <lines already shown>` returns exactly the new lines. Writing a second endpoint for the same read is the duplication the conventions treat as a defect. Download had no endpoint at all: `admin/api/log-download.php` is a **GET** because it changes no state and a download must work as a plain link, and it resolves the filename through the Logger's own `safeFilePath()` rather than re-deriving a security boundary in a page | Yes — driven: Download streams the real file as `text/plain` with its `Content-Disposition`, a traversal name is refused 404, and a viewer is refused 403 by the gate map entry added in the same slice |
| 18 | The Design screen's four measured pairs are `text`/`text_muted` over `background`/`surface` — and no more | `SPEC/accessibility.md` §10.7 says "every text/background pair it defines" and the theme defines exactly two text colours and two surfaces. `primary` and `accent` are used as link and button colours — real text over a background — but §10.7 does not fix those pairings, so measuring them would invent a rule the delivery does not state, and REFUSING a save on them would block palettes the delivery permits. Stated here rather than guessed; raised in the next Design Request | Yes — four pairs rendered and gated, each ratio recomputed independently in Python from the WCAG formula and agreeing with the screen (14.63:1 / 2.32:1 checked both ways) |
| 19 | `ThemeManager::setColors()` keeps its released behaviour: the §10.7 refusal lives in the SCREEN, not in the setter | §10.7 binds "the theme editor". `setColors()` is a released public method with MCP callers, and adding a refusal to it would change the behaviour of an installed base inside a fidelity stage (the same call D-079 made about `PageManager::list()`). **Consequence, recorded rather than implied: a site configured entirely over MCP can hold a palette this screen would have refused** | Yes — the guard is driven on the screen (a below-AA save refused, nothing written, verified by a fresh GET), and the omission is written into `docs/reference/design-tokens.md` |
| 20 | The admin toolbar renders its actions through a purpose-built allow-list (`admin.toolbar_allowed_tags`), not through `klytos_kses_post()` | Not a design deviation — a seam that could not carry what it was built for. `klytos_kses_post()`'s tag map is written for post CONTENT and has **no `<button>` at all**, so the toolbar Save that `template-record-form.md` §1 requires rendered as the bare word "Save": present, in the right place, and not a control. Stage 2 proved the seam exists; nothing had ever passed a control through it (L-030's shape again) | Yes — the Save is asserted to be a real `<button>`, inside `.k-toolbar`, owned by the form it submits; **proven by restoring `klytos_kses_post()` and watching that test go red** |
| 21 | **Manifest entry 19 renders NO toolbar Save**, and its card stack is not one `<form>` | `template-record-form.md` §1 puts the primary Save in the toolbar "on every form screen". Entry 19's own delta says it "creates and orders; it does not edit", and the one card that would have carried savable fields — Statuses — is deferred as unbacked (D-089, `roadmap.md` §0c). What remains is two collections whose actions are their own, in the card footer §1 provides for; a toolbar Save would submit nothing. The stack is also two independent forms plus a delete form per row, and a form cannot nest inside a form | Yes — and REVERSIBLE by construction: restoring the Save is one block, the day the Statuses card lands. Its absence is asserted by no test, deliberately; what IS asserted is the absence of the Statuses `<h2>`, so the two cannot drift apart |
| 22 | The destructive confirm's armed label states **"the post type only. Records kept: {count}"**, not §2's example "34 records will be deleted" | Not a design deviation — the design's example sentence is FALSE in this product. `PostTypeManager::delete()` removes the type and its term data and **leaves the records**. §2 specifies the SHAPE (the same button relabelled with the consequence, `aria-live="polite"` on its wrapper, never a browser `confirm()`); the consequence itself is a fact about the code, not a value the delivery supplies | Yes — the shape is built exactly, including the server-side two-step that works with JavaScript disabled. A test asserts the true wording so the design's example cannot be copied back in as a claim the code does not honour |
| 23 | **Manifest entry 39 renders no Taxonomies card**, although the screen it replaces had one | The manifest gives taxonomies to entry **19** (Content model) and lists six cards for entry 39, none of them Taxonomies. Entry 19 was built in the previous slice and now creates a taxonomy into a chosen post type, deletes one, and links each to `taxonomy.php` — the same three operations this screen used to offer | Yes, and **nothing is removed from the product**: the capability is one screen away and driven by `content-model.spec.js`. Drawing it twice would be two implementations of one collection, free to drift — the reuse rule, not a fidelity choice |
| 24 | **Manifest entry 39 renders no "Delete this post type" card**, although `template-record-form.md` §2 names a destructive section on this template and uses a post type as its example | §2's destructive section is a TEMPLATE property; entry 39's own card list does not include one, and entry 19's Post types collection already carries the delete — with its server-side two-step confirm and the truthful armed label adaptation 22 records. A second destructive path to the same operation is duplication | **Raised with the user rather than decided silently** (session report, D-090). Reversible in one block if the answer is that entry 39 should carry one too |
| 25 | The custom-field OPTION rows are three static rows in the markup plus a JavaScript "Add another option" button, where the shipped screen built every row in script and showed them only for choice types | Not a design deviation — the delivery specifies no option editor at all. With the script absent the shipped screen let a `select` be created with **no options and no explanation**; three rows in the markup make the capability work with JavaScript off, and the button restores the unbounded case on top | Yes — driven **with JavaScript actually disabled**: a two-option `select` is created and the row reports "Options: 2", while the enhancement button stays hidden because its script never ran |
| 26 | `.k-section-nav-item` paints `--texto-primario`, where the record-form layer first wrote `--texto-secundario` | Not a design deviation — **a defect the build introduced and the build fixed.** The section nav sits on `--fondo-ventana`, outside any card, and that pair measures **4.46:1** in light: under AA by 0.04, and DR-005 gap 2's own pair. No delivered file states this control's colour — §1 names the nav and gives its geometry only — so the token was the build's choice — the same call D-078 made for three pairs `klytos-admin.css` already ruled on | Yes — **14.79:1 light / 15.29:1 dark**, recomputed independently in Python and agreeing with axe, both pinned as floors and **proven to FAIL on the planted original** before being trusted |

| 27 | **Manifest entry 6 renders NO toolbar Save**, where `template-record-form.md` §1 says the primary Save lives in the toolbar and "is the same button on every form screen" | §6's own delta makes every second-factor control immediate-effect, and §4 defines exactly that as the switch idiom — so there is no pending form state for a Save to submit. The one card with staged fields, Encryption level, carries its Save in that card's footer because it submits that card's two fields and nothing else | Yes — D-089's rule applied a second time: a control that lies about what it does is worse than a control that is absent. Pinned by a test asserting the toolbar carries no submit button |
| 28 | **The re-auth step §6's delta requires is a SERVER-SIDE second step**, and it doubles as §2's destructive confirm | §6 says the switches are "each confirmed by a re-auth step" and §2 requires an inline two-step confirm that is never a browser `confirm()`. One mechanism serves both: the switch posts, the card re-renders asking for the current password, the second post applies it. Turning TOTP ON is the one toggle with no password step — its confirmation is the enrolment ceremony, which proves possession of the authenticator and is a strictly stronger claim | Yes — driven in both directions, and **driven with JavaScript actually disabled**, which is what the "never a browser `confirm()`" rule is really about. `UserManager::authenticate()` is reused rather than a third password comparison written (D-056's authority) |
| 29 | **Three cards the manifest's card list does not name are built**: Encryption level, Recovery keys, and the destructive Turn-off card | All three are shipped product and this screen is their only surface; no other manifest entry claims them (entry 34 is System integrity, which is a different thing). Removing shipped behaviour is not a fidelity decision — the standing rule since D-075 | Yes — each keeps its `site.configure` gate exactly as it was, enforced on the POST branch and mirrored in the markup's visibility, and the role split is driven as `editor` |
| 30 | **The Passkeys card is a COLLECTION with a destructive row action, not a switch**, although §6's delta says "2FA and passkey controls are switches" | A switch restores what it turns off. A passkey is created by a WebAuthn ceremony and destroyed by `removePasskey()`; there is no state an "off" position could put back, and an account may hold several | Yes — the row's Remove goes through the same re-auth step, and the ADD control is hidden where `navigator.credentials` is absent rather than shown as a control that cannot act |
| 31 | `.k-card--secret` layers `--tinte-aviso` OVER `--fondo-elevado` instead of replacing the card's background with it | Not a design deviation — §6's delta specifies the tint and the border, and the tint is translucent. Every other consumer of `--sobre-tinte-aviso` sits on a card, so writing `background: var(--tinte-aviso)` alone would composite it over `--fondo-ventana` and quietly measure a different pair — DR-005's addendum in miniature | Yes — axe passes on the card in both themes with the codes rendered, and the token pair is pinned as a floor at its measured value (6.75:1 dark / 5.95:1 light), **proven to fail with the override removed** |
| 32 | **Manifest entry 25 renders no Acceptance stats card**, although §25's card list names one | The product stores no acceptance data of ANY kind. Klytos publishes a static site; the visitor's choice is written to a cookie in their own browser; nothing receives it, stores it or aggregates it. The prototype's "Accepted everything 62%" is visitor telemetry — a whole collection surface, and one carrying its own privacy question, since counting consent choices is itself processing | Deferred under D-088's standing answer 1, carried in `roadmap.md` §0c, and its ABSENCE is asserted by a test so it cannot be invented later from a number nobody measured. The four figures the shipped stat grid drew ARE backed and survive in the audit table's `<caption>`, which §2.1 requires to carry the count anyway |
| 33 | **The cookie audit's four columns and their widths (`170px 120px 90px 1fr`) are taken from the delivery's PROTOTYPE**, not from `SPEC/manifest.md` | §25 names the card a list-table and records neither columns nor widths — a fourteenth list surface DR-006's sent table omits. What separates it from the twelve DR-006 blocks is that nothing CONTRADICTS: DR-006's stated reason for refusing prototypes is that their tables carry different COLUMNS from the manifest's, and here the manifest has none. Three sources agree — the prototype, the shipped screen's own four headings, and silence everywhere else | **The user's decision**, taken before the first line. Recorded as a DR-006 addendum so Design still states the value normatively; the screen is one block from following whatever it states |
| 34 | **The per-plugin "Remove declaration" lives in an `<h3>` group inside the audit card, not as a fifth table column** | The four specified columns are per-COOKIE; a declaration — and therefore its removal — is per-PLUGIN. A fifth column would repeat one plugin's control on each of its cookies, and the manifest gives four columns | Yes — §4 explicitly provides for `<h3>` groups inside a card, and the `.k-collection` layer built in D-089 is REUSED rather than rebuilt. The armed label states what `deletePluginDeclaration()` really does: it removes the audit ENTRY, the plugin stays installed and keeps setting its cookies (§2's own example sentence would be false twice over) |
| 35 | **The banner half of `installer/core/assets/consent-manager.js` is rewritten** — a file outside `installer/admin/` and outside the redesign's usual surface | §25's own two deltas bind "the banner preview **and the shipped banner**" to the same equal-prominence rule, and add `role="dialog" aria-modal="true"`, focus trapped and `Esc` = reject non-essential. The shipped banner met NONE of the five. Building only the preview would have advertised a banner the site does not ship | **The user's decision**, taken before the first line. Every one of the five is driven against the real library served into a real page; the prominence pair is proven to fail on the planted original class. Two shipped fields that did nothing were closed in the same change — `cookie_days` never reached the library, and the banner's words were hardcoded Spanish against D-006 |


**Counts: what is wired, and what is honestly not.** `navigation.md` §2 gives 16
items a count. Seven are wired from a source verified in the tree —
`pages`, `tasks`, `assets`, `content-model`, `blocks`, `templates`, `scheduled`.
The other nine are **not**, and they emit nothing rather than a zero: an absent
count and a zero count are indistinguishable to the reader, so a fabricated zero
would be a lie that cannot be seen. Each needs a surface that does not exist yet —
webhook delivery failures in the last 24 h, invitations not yet accepted, open
export/erasure requests, plugins with an update available, updates pending, files
modified or unsigned, connected MCP clients, strings not yet translated in any
locale, and comments awaiting moderation (entry 14, deferred). They are owned by
the slices that build those surfaces, and the filter `admin.nav_counts` is the
seam. **The redesign is not reportable as complete while this paragraph stands.**

**Build order** (the delivery's own suggested order, adopted as the slice order):

1. Tokens into the stylesheet chain, behind `[data-theme]`, `klytos-admin.css` last.
2. The shell: sidebar, toolbar, status bar, theme toggle, ⌘K.
3. The component layer in `klytos-components.css`.
4. List screens (they share one table).
5. Form screens (they share one field).
6. The specialised ones last: editor, terminal, AI chat, wizard, preview.

**Verification is from rendered output, not from source** (Phase 4 Step 4a/7). The moment the first
screen renders: `./scripts/keel-doctor --check`, the playground up, the drivers installed, one
driven test passing against a real screen. Each screen is then driven at the four declared
breakpoints, every state in §5.3 reached by driving it (open the modal, submit the empty form,
trigger the error), with axe-core at AA in both themes per screen. `tests/E2E/` does not exist yet
(`[A]`, audit T-05) and is created by the slice that lands the first screen. Only the real
assistive-technology pass (§11 of the accessibility spec: NVDA + Firefox, VoiceOver + Safari, once
per template) is delegated, under the `ASSISTIVE-TECH` tag.

### 5.10 Faithfulness checklist (verified at Step 7)

Ticked in **this file** at the moment each item is verified — never retroactively, never from
conversation memory. An unticked box blocks Phase 4's definition of done.

- ☐ Every screen visually matches its artifact + SPEC (tokens, layout, spacing) at 1440 × 976.
- ☐ Every documented state in §5.3 implemented and **reached by driving it**.
- ☐ Every screen's manifest deltas built (§5.4).
- ☐ No invented values: every value traces to §5.2 or a SPEC reference.
- ☐ No interpreted behaviour: every behaviour traces to §5.6.
- ☐ Reuse preserved — 11 templates, 42 screens, no template flattened into duplicated pages.
- ☐ The existing design system (PackDesk) is matched exactly; every divergence went to Design as a
  Design Request, never a build-side choice.
- ☐ Every logo and icon present in both SVG and PNG; every asset used directly, with no build-side
  conversion, resize, recolor or re-export.
- ☐ No placeholder copy shipped.
- ☐ No external-setup steps existed (§5.8) — re-confirmed at Step 7, not assumed.
- ☐ No externally generated assets existed (§5.8) — re-confirmed at Step 7.
- ☐ Every code-side adaptation logged in §5.9 with design intent confirmed intact.
- ☐ Accessibility built to §5.5 / `SPEC/accessibility.md` and verified: axe-core clean at AA both
  themes, keyboard-only pass, zoom 200 %/400 %, forced-colors, reduced-motion — plus the real
  assistive-technology pass per template.
- ☐ Every interactive element carries its `data-testid`; `keel-verify` passes.
- ☐ Every new string exists in all 20 locale catalogues; the parity check passes.
- ☐ Zero unresolved Design Requests.

### 5.11 What this consolidation FOUND — open items, none of them invented answers

Step 2 is where a build contract stops being a reading exercise. Four things surfaced that the
gate could not have seen, because the gate checks the delivery against the contract and these are
questions about the delivery against **this codebase**.

> **ALL FOUR ARE NOW CLOSED — 2026-07-29, `D-072`** (item 1's design half by DR-002 / `D-071`, its
> open attribution half plus items 2–4 by the user's decisions). **Phase 4's build scope is 40 of the
> 44 entries**; entries **11 (Verify), 14 (Comments), 17 (Setup wizard) and 22 (Health)** are
> deferred to their own Phase 5 slices. They stay in the manifest, stay in §5.1 and stay counted as
> 44 — they leave the build ORDER, not the product, and the redesign is not reportable as complete
> while they are outstanding.

1. ~~**The Dashboard has no design, and it is the admin's landing screen.**~~ — **CLOSED
   2026-07-29 by DR-002** (§1d). It was a real gap: `installer/admin/index.php` is shipped and is
   where `template-shell.md`'s own brand link points (`<a class="k-brand" href="index.php">`), yet
   it had **no entry in the then-43-entry manifest and no SPEC file**, the only dashboard in the
   bundle being inside `Klytos Admin - Redesign.dc.html`, which the manifest itself lists as
   *"earlier exploration — kept for reference, not for build"*. Design specified it as **entry 44**,
   deltas from `overview-stats`, **no twelfth template and no new glyph**. The build does not design
   a dashboard and does not ship the old one inside the new shell — it builds entry 44. **One
   consequence rides along and is not cosmetic:** indexing moves off the Dashboard to Settings →
   Advanced (checkbox + Save, gated at `site.configure`), and the Dashboard only warns while the
   site is blocked. `installer/admin/index.php` carries that toggle today, so it is a behavioural
   change to shipped code — see §1d and §5.6.
   **The attribution half is CLOSED 2026-07-29 (D-072): the user CONFIRMED that
   `open-questions.md` items 13–15 are genuinely theirs.** The indexing move is built as specified —
   the toggle moves to Settings → Advanced gated at `site.configure`, the Dashboard warns only. This
   was the one thing §1d said the bytes could not verify and refused to assume; one question settled
   it.
2. **The Setup wizard's seven steps are the installer, not `admin/setup-wizard.php`.**
   **CLOSED 2026-07-29 (D-072): DEFERRED to its own Phase 5 slice — Phase 4 does not build it.**
   It is product scope rather than fidelity, and NEW-04 makes `install.php` destructive in a
   checkout, so the slice needs a deliberate non-destructive driving strategy (a disposable copy)
   instead of an improvisation inside a fidelity build. **Consequence: the `wizard` template has
   exactly one consumer, so `template-wizard.md` is not built in Phase 4 either.** The manifest's
   steps (Welcome · Database · Site identity · Administrator · Content model · Intelligence · Finish)
   and its "no shell — the person has no site to navigate yet" describe
   **`installer/install.php`**, an 85 KB wizard whose current structure is three steps
   (requirements → configuration → complete). `installer/admin/setup-wizard.php` is a different,
   authenticated, post-install screen. So this row is not a re-skin: it changes the installer's step
   structure, which is product scope, not design fidelity. Two further consequences worth stating
   before anyone plans it: `install.php` is **destructive in a checkout** (NEW-04 — never run it
   there), which shapes how it can be driven; and the design has nothing to say about
   `admin/setup-wizard.php`, which therefore keeps its current UI until someone decides otherwise.
   **This needs the user's scope decision before the wizard slice is planned.**
3. **Three screens are new product surfaces, not redesigns.**
   **CLOSED 2026-07-29 (D-072): all three DEFERRED to their own Phase 5 slices, with *Verify*
   paired with NEW-38** so the OAuth consent screen's 2FA gap closes in the same area rather than
   one slice hardening a path another is rebuilding. *Health*'s slice builds a data source before it
   builds a screen; *Comments*' slice carries L-014's recorded history of what that feature actually
   costs here. Each is fully specified — deferring is scheduling, not a gap, and no Design Request
   follows. *Verify* (§11) exists today only as a
   pending-2FA branch inside `login.php`; splitting it out is the design's decision and is
   specified, but it touches the authentication flow Sprint 5 closed (D-056…D-058) and the OAuth
   consent screen's known 2FA gap (**NEW-38**) sits in the same area. *Comments* (§14) has a working
   `core/comment-manager.php` and no admin screen at all. *Health* (§22) has no backing surface in
   the tree today. Each is buildable from the spec; each is **new scope inside a "redesign"**, and
   the estimate must say so rather than absorb it.
4. **The i18n cost is real and it is invisible in the design.**
   **CLOSED 2026-07-29 (D-072): the rule is UNCHANGED — every new key lands in all 20 catalogues in
   the same slice, and no slice passes its test point with a key in English only.** The alternative
   (English first, one bulk translation pass later) was rejected because it leaves `keel-verify`'s
   parity check red across many slices, and a check that is red for weeks stops being read — L-010's
   failure mode applied to a verifier instead of a guard. The estimate grows to tell the truth.
   Every string in 42 screens is new
   English copy, and this project's rule is `__( 'domain.key' )` with the key added to **all 20**
   catalogues in the same change (639 keys each today), enforced mechanically by `keel-verify`.
   Nothing about this contradicts the design — the delivery explicitly assumes substitutable strings
   with +30 % expansion headroom — but a build plan that costs the markup and not the catalogues
   will be wrong by a wide margin.

**All four are decided (2026-07-29, D-072).** Phase 4 Step 4 builds **40 entries**: the 44 in §5.1
minus 11, 14, 17 and 22. The four deferred entries are carried into `docs/roadmap.md` as named
Phase 5 slices, so a deferral that was decided cannot become a deferral that was forgotten.
