# Design tokens — origin, scope, and what Klytos actually uses

## Origin

Every file in `tokens/` except one is **inherited, unmodified, from the PackDesk Design
System** (José Conti) — Paleta B "Mediterráneo". PackDesk is the parent brand; Klytos is a
product inside it and signs *by PackDesk*. Upstream owns those values; this delivery does
not fork them, and the only edits made to them are comment headers stating this and the two
corrections DR-001 required (the dangling audit reference in `colors.css`, the dangling font
paths in `fonts.css`).

The one file Klytos owns is **`tokens/klytos-admin.css`**. It is new, it is product-level,
and it exists because the measured accessibility audit needed derived tones that PackDesk
does not define. It adds; it never rewrites a base hex.

Upstream source of truth: PackDesk Design System › `tokens/`. If a value here disagrees with
upstream, upstream is right and this bundle is stale — except in `klytos-admin.css` and the
Klytos section of `typography.css`, which are Klytos's own contract and win locally.

## Target surfaces this delivery covers

The Klytos admin panel: a PHP, server-rendered, multi-page application at
`/<random>-admin/`. 41 screens, plus three auth pages with no shell. Light and dark, both
shipping. Pointer and keyboard; there is no mobile app and no native shell.

Not covered by this delivery, and therefore not a reason to implement a token: the public
site Klytos generates (its own theme layer, separate DR), transactional email, and any
native platform target.

## Load order

```
tokens/colors.css
tokens/typography.css
tokens/spacing.css
tokens/effects.css
tokens/motion.css
tokens/glass.css        ← only --glass-fallback-* is used
tokens/fonts.css
tokens/klytos-admin.css ← LAST. Klytos-owned. Overrides nothing; adds and enforces.
```

Then `css/klytos-components.css`. Theme flips by swapping `data-theme` on one wrapper
element; the cookie is read server-side so the first paint is already correct.

## Klytos's contract vs. inherited furniture

### Used, and load-bearing

| File | What the admin uses |
|---|---|
| `colors.css` | The whole semantic palette, all nine product states, all tints, all neutrals, `--sobre-acento`, `--fila-hover`, `--fila-seleccion`, `--foco-anillo`, `--glass-fallback-*`. |
| `typography.css` | `--font-ui`, `--font-mono`, the weights, `--eyebrow-tracking`, `--wordmark-*`, and the **Klytos section** at the foot of the file (see below). |
| `spacing.css` | The spacing scale and the radii (control 6 / card 10 / popover 12 / pill 999). |
| `effects.css` | Card and popover shadows only. The admin has four elevations on paper and uses two. |
| `motion.css` | The two curves and the 120–280ms band, plus the reduced-motion variant. |
| `fonts.css` | Both `@font-face` rules. Geist and Geist Mono, variable, self-hosted. |
| `klytos-admin.css` | All of it. |

### Inherited and **not** used — do not implement

| Token | Why it is here | Why Klytos does not use it |
|---|---|---|
| `--type-picking-linea`, `--type-picking-pedido` | PackDesk's warehouse picking density — "legible a distancia en almacén" | Another product's concern. No Klytos screen is read at arm's length. |
| `--type-mono-importe`, `--type-mono-pedido` | PackDesk order number and amount | Klytos's equivalent is `--type-numeric` (mono 500 12px/17px). Money in the x402 ledger uses `--type-numeric`, not `--type-mono-importe`. |
| `--font-native-apple`, `--font-native-windows`, `--font-native-android` | Native-app voice per OS | The admin is a browser surface and always renders in Geist. |
| `platform.css` in full | Native densities, touch targets, window minimums | No native target in this delivery. WCAG 2.5.8's 24 × 24 governs targets here, not the 44pt / 48dp rows. |
| `glass.css` except `--glass-fallback-*` | Liquid Glass recreation for Apple prototypes | The admin uses **no** translucency and no blur. Sidebar and toolbar are the flat fallbacks. |
| The 4-elevation shadow set beyond card and popover | Sheets and window shadows | The admin has no sheets and no window chrome of its own. |

These are left in place rather than deleted so the files stay diffable against upstream. They
are marked in-file with a `HERENCIA NO USADA POR KLYTOS` comment.

## The type layer

`typography.css` now has two sections. The PackDesk scale is untouched at the top; the
**Klytos admin** section at the foot is normative for this product and is character-for-
character the same set as the README's type table. DR-001's two conflicts are resolved in
favour of the delivered screens:

| Role | Was (README) | Was (`typography.css`) | **Canonical** | Token |
|---|---|---|---|---|
| Body / table cell | `400 13px/17px` | `--type-body: 400 13px/18px` | **`400 13px/17px`** | `--type-body` |
| Secondary / hint | `400 11px/16px` | `--type-caption: 400 11px/14px` | **`400 11px/16px`** | `--type-caption` |

Rationale: the 41 delivered screens render 13/17 and 11/16. Changing the screens to match the
CSS would break the byte-stability the re-delivery requires; changing the CSS to match the
screens costs nothing upstream, because `--type-body` and `--type-caption` are redeclared in
the Klytos section rather than edited in the PackDesk section.

The six roles that had no token now have one: `--type-eyebrow`, `--type-column-header`,
`--type-nav-item` / `--type-nav-item-active`, `--type-toolbar-title`, `--type-badge`,
`--type-numeric`. Plus `--type-page-title`, `--type-card-heading`, `--type-body-mono` and
`--type-code`, so no size in the admin is ever hard-coded. Full table: README › Type.

## The accessibility layer

`tokens/klytos-admin.css`, Klytos-owned, adds:

- `--sobre-tinte-*` (9 tones × 2 themes) — the measured text colour for a badge or chip on
  its own tint. **The only correct way to paint badge text.**
- `--texto-sutil` — replaces `--texto-terciario` for all text.
- `--borde-control`, `--borde-deshabilitado`, `--texto-deshabilitado` — 3:1 control
  boundaries, distinct from `--separador`, which stays a separator.
- `--foco-grosor`, `--foco-offset`, plus the normative `:focus-visible`, `.k-hit-24`,
  `prefers-reduced-motion` and `forced-colors` rules.

Every number in it is measured. `SPEC/color-contrast-audit.md` shows the working.

## Rules

1. **Never a hex in a template.** Every colour is `var(--…)`. A hex in a PHP file is a defect.
2. **Never a font size in a template.** Every size is a `--type-*` token via the `font:`
   shorthand.
3. **Never `--texto-terciario` on text.** Use `--texto-sutil`.
4. **Never a raw semantic colour as text on its own tint.** Use `--sobre-tinte-*`.
5. **Never `--separador` as a control's border.** Use `--borde-control`.
6. **Never `#fff` on an accent fill.** Use `--sobre-acento` — in dark, the fill is light and
   white disappears.
7. The palette is sacred. If a pair fails, change the pattern and record it in the audit.
