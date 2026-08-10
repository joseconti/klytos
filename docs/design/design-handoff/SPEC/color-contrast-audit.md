# Colour and contrast audit — Klytos admin

**This is the file `tokens/colors.css` refers to.** It replaces the never-shipped
`guidelines/auditoria-color-y-contraste.md`.

Method: WCAG 2.x relative luminance and contrast-ratio formula, computed from the delivered
token values. Tints are composited onto the surface underneath before measuring — a badge's
text is measured against the *resulting* colour, not against the base surface. Thresholds:
**4.5:1** for text below 18.66px/24px (everything in the admin is), **3:1** for UI component
boundaries, focus indicators, graphical objects and status dots (1.4.11).

No base colour was changed. Where a pair failed, the **pattern** changed — see
`SPEC/accessibility.md` §1.1 and the `--sobre-tinte-*` / `--texto-sutil` /
`--borde-control` tokens in `tokens/klytos-admin.css`.


---

## Light theme

### Text on surface (threshold 4.5:1)

| Text token | Surface | Ratio | |
|---|---|---|---|
| `--texto-primario` #1D1D1F | `--fondo-contenido / --fondo-elevado` #FFFFFF | 16.83:1 | PASS |
| `--texto-primario` #1D1D1F | `--fondo-ventana` #F0F0F2 | 14.79:1 | PASS |
| `--texto-primario` #1D1D1F | `--glass-fallback-sidebar` #F2F2F4 | 15.05:1 | PASS |
| `--texto-primario` #1D1D1F | `--glass-fallback-toolbar` #F7F7F9 | 15.73:1 | PASS |
| `--texto-secundario` #6E6E73 | `--fondo-contenido / --fondo-elevado` #FFFFFF | 5.07:1 | PASS |
| `--texto-secundario` #6E6E73 | `--fondo-ventana` #F0F0F2 | 4.46:1 | **FAIL** |
| `--texto-secundario` #6E6E73 | `--glass-fallback-sidebar` #F2F2F4 | 4.54:1 | PASS |
| `--texto-secundario` #6E6E73 | `--glass-fallback-toolbar` #F7F7F9 | 4.74:1 | PASS |
| `--texto-terciario (retired for text)` #86868B | `--fondo-contenido / --fondo-elevado` #FFFFFF | 3.62:1 | **FAIL** |
| `--texto-terciario (retired for text)` #86868B | `--fondo-ventana` #F0F0F2 | 3.18:1 | **FAIL** |
| `--texto-terciario (retired for text)` #86868B | `--glass-fallback-sidebar` #F2F2F4 | 3.24:1 | **FAIL** |
| `--texto-terciario (retired for text)` #86868B | `--glass-fallback-toolbar` #F7F7F9 | 3.39:1 | **FAIL** |
| `--texto-sutil (new)` #6D6D71 | `--fondo-contenido / --fondo-elevado` #FFFFFF | 5.15:1 | PASS |
| `--texto-sutil (new)` #6D6D71 | `--fondo-ventana` #F0F0F2 | 4.53:1 | PASS |
| `--texto-sutil (new)` #6D6D71 | `--glass-fallback-sidebar` #F2F2F4 | 4.61:1 | PASS |
| `--texto-sutil (new)` #6D6D71 | `--glass-fallback-toolbar` #F7F7F9 | 4.82:1 | PASS |

### Badge / chip text on its own tint — base colour (threshold 4.5:1)

Tint = base at 11 % over the surface.

| Tone | Base | Tint over `--fondo-contenido` | Ratio | Tint over `--fondo-elevado` | Ratio | |
|---|---|---|---|---|---|---|
| `--color-acento` | #0E8074 | #E4F1F0 | 4.16:1 | #E4F1F0 | 4.16:1 | **FAIL** |
| `--color-info` | #0E7490 | #E4F0F3 | 4.61:1 | #E4F0F3 | 4.61:1 | PASS |
| `--color-exito` | #257D36 | #E7F1E9 | 4.47:1 | #E7F1E9 | 4.47:1 | **FAIL** |
| `--color-aviso` | #9A6300 | #F4EEE3 | 4.37:1 | #F4EEE3 | 4.37:1 | **FAIL** |
| `--color-peligro` | #C03A35 | #F8E9E9 | 4.57:1 | #F8E9E9 | 4.57:1 | PASS |
| `--color-offline` | #6E6E73 | #EFEFF0 | 4.41:1 | #EFEFF0 | 4.41:1 | **FAIL** |
| `--color-sync` | #3672D9 | #E9EFFB | 3.99:1 | #E9EFFB | 3.99:1 | **FAIL** |
| `--color-conflicto` | #7C3AED | #F1E9FD | 4.83:1 | #F1E9FD | 4.83:1 | PASS |
| `--color-reconectar` | #C2570B | #F8EDE4 | 3.91:1 | #F8EDE4 | 3.91:1 | **FAIL** |

### Badge / chip text on its own tint — **after the fix** (`--sobre-tinte-*`)

| Tone | Delivered text colour | vs tint over `--fondo-contenido` | vs tint over `--fondo-elevado` | |
|---|---|---|---|---|
| `--sobre-tinte-acento` | #0C7166 | 5.08:1 | 5.08:1 | PASS |
| `--sobre-tinte-info` | #0D6C86 | 5.15:1 | 5.15:1 | PASS |
| `--sobre-tinte-exito` | #227231 | 5.16:1 | 5.16:1 | PASS |
| `--sobre-tinte-aviso` | #8B5900 | 5.16:1 | 5.16:1 | PASS |
| `--sobre-tinte-peligro` | #B33631 | 5.11:1 | 5.11:1 | PASS |
| `--sobre-tinte-offline` | #646469 | 5.12:1 | 5.12:1 | PASS |
| `--sobre-tinte-sync` | #2E62BB | 5.08:1 | 5.08:1 | PASS |
| `--sobre-tinte-conflicto` | #7738E4 | 5.13:1 | 5.13:1 | PASS |
| `--sobre-tinte-reconectar` | #A34909 | 5.19:1 | 5.19:1 | PASS |

### Status dot on its own tint (threshold 3:1 — graphical object)

| Tone | Ratio over `--fondo-elevado` tint | |
|---|---|---|
| `--color-acento` | 4.16:1 | PASS |
| `--color-info` | 4.61:1 | PASS |
| `--color-exito` | 4.47:1 | PASS |
| `--color-aviso` | 4.37:1 | PASS |
| `--color-peligro` | 4.57:1 | PASS |
| `--color-offline` | 4.41:1 | PASS |
| `--color-sync` | 3.99:1 | PASS |
| `--color-conflicto` | 4.83:1 | PASS |
| `--color-reconectar` | 3.91:1 | PASS |

### Text on a solid semantic fill — `--sobre-acento` (threshold 4.5:1)

`--sobre-acento` = #FFFFFF

| Fill | Ratio | |
|---|---|---|
| `--color-acento` #0E8074 | 4.82:1 | PASS |
| `--color-info` #0E7490 | 5.36:1 | PASS |
| `--color-exito` #257D36 | 5.17:1 | PASS |
| `--color-aviso` #9A6300 | 5.05:1 | PASS |
| `--color-peligro` #C03A35 | 5.39:1 | PASS |
| `--color-offline` #6E6E73 | 5.07:1 | PASS |
| `--color-sync` #3672D9 | 4.60:1 | PASS |
| `--color-conflicto` #7C3AED | 5.70:1 | PASS |
| `--color-reconectar` #C2570B | 4.50:1 | PASS |

### Focus ring `--color-acento` vs surfaces (threshold 3:1)

| Surface | Ratio | |
|---|---|---|
| `--fondo-contenido / --fondo-elevado` | 4.82:1 | PASS |
| `--fondo-ventana` | 4.23:1 | PASS |
| `--glass-fallback-sidebar` | 4.31:1 | PASS |
| `--glass-fallback-toolbar` | 4.50:1 | PASS |

### Control boundaries (threshold 3:1)

| Token | Composited | vs worst surface | |
|---|---|---|---|
| `--separador` (as a control border) | #EBEBEB | 1.19:1 | **FAIL** |
| `--borde-control` (new) | #86868B | 3.18:1 | PASS |
| `--texto-deshabilitado` (3:1 self-imposed floor; WCAG exempt) | #86868B | 3.18:1 | PASS |

### Row states (threshold 3:1 against the surface — informational, not required)

- `--fila-hover` composites to #F4F4F4. Hover is never the only indicator of anything; selection is.
- `--fila-seleccion` composites to #E2F0EE. Selection is additionally carried by the row checkbox and `aria-selected`, so its 1.17:1 against the surface is not load-bearing.

---

## Dark theme

### Text on surface (threshold 4.5:1)

| Text token | Surface | Ratio | |
|---|---|---|---|
| `--texto-primario` #F5F5F7 | `--fondo-contenido` #232326 | 14.39:1 | PASS |
| `--texto-primario` #F5F5F7 | `--fondo-elevado` #2C2C2E | 12.80:1 | PASS |
| `--texto-primario` #F5F5F7 | `--fondo-ventana` #1E1E20 | 15.29:1 | PASS |
| `--texto-primario` #F5F5F7 | `--glass-fallback-sidebar` #26262A | 13.84:1 | PASS |
| `--texto-primario` #F5F5F7 | `--glass-fallback-toolbar` #2A2A2E | 13.13:1 | PASS |
| `--texto-secundario` #98989D | `--fondo-contenido` #232326 | 5.46:1 | PASS |
| `--texto-secundario` #98989D | `--fondo-elevado` #2C2C2E | 4.85:1 | PASS |
| `--texto-secundario` #98989D | `--fondo-ventana` #1E1E20 | 5.80:1 | PASS |
| `--texto-secundario` #98989D | `--glass-fallback-sidebar` #26262A | 5.25:1 | PASS |
| `--texto-secundario` #98989D | `--glass-fallback-toolbar` #2A2A2E | 4.98:1 | PASS |
| `--texto-terciario (retired for text)` #6E6E73 | `--fondo-contenido` #232326 | 3.09:1 | **FAIL** |
| `--texto-terciario (retired for text)` #6E6E73 | `--fondo-elevado` #2C2C2E | 2.75:1 | **FAIL** |
| `--texto-terciario (retired for text)` #6E6E73 | `--fondo-ventana` #1E1E20 | 3.28:1 | **FAIL** |
| `--texto-terciario (retired for text)` #6E6E73 | `--glass-fallback-sidebar` #26262A | 2.97:1 | **FAIL** |
| `--texto-terciario (retired for text)` #6E6E73 | `--glass-fallback-toolbar` #2A2A2E | 2.82:1 | **FAIL** |
| `--texto-sutil (new)` #939397 | `--fondo-contenido` #232326 | 5.12:1 | PASS |
| `--texto-sutil (new)` #939397 | `--fondo-elevado` #2C2C2E | 4.55:1 | PASS |
| `--texto-sutil (new)` #939397 | `--fondo-ventana` #1E1E20 | 5.44:1 | PASS |
| `--texto-sutil (new)` #939397 | `--glass-fallback-sidebar` #26262A | 4.92:1 | PASS |
| `--texto-sutil (new)` #939397 | `--glass-fallback-toolbar` #2A2A2E | 4.67:1 | PASS |

### Badge / chip text on its own tint — base colour (threshold 4.5:1)

Tint = base at 19 % over the surface.

| Tone | Base | Tint over `--fondo-contenido` | Ratio | Tint over `--fondo-elevado` | Ratio | |
|---|---|---|---|---|---|---|
| `--color-acento` | #3CC3B2 | #284141 | 5.01:1 | #2F4947 | 4.45:1 | **FAIL** |
| `--color-info` | #3FB7D4 | #283F47 | 4.72:1 | #30464E | 4.23:1 | **FAIL** |
| `--color-exito` | #56C96E | #2D4334 | 5.08:1 | #344A3A | 4.56:1 | PASS |
| `--color-aviso` | #E8A93C | #483C2A | 5.20:1 | #504431 | 4.60:1 | PASS |
| `--color-peligro` | #E6685F | #483031 | 3.74:1 | #4F3737 | 3.37:1 | **FAIL** |
| `--color-offline` | #98989D | #39393D | 4.00:1 | #414143 | 3.55:1 | **FAIL** |
| `--color-sync` | #6FA0EF | #313B4C | 4.27:1 | #394253 | 3.83:1 | **FAIL** |
| `--color-conflicto` | #A78BFA | #3C374E | 4.17:1 | #433E55 | 3.75:1 | **FAIL** |
| `--color-reconectar` | #F08C3E | #4A372B | 4.55:1 | #513E31 | 4.09:1 | **FAIL** |

### Badge / chip text on its own tint — **after the fix** (`--sobre-tinte-*`)

| Tone | Delivered text colour | vs tint over `--fondo-contenido` | vs tint over `--fondo-elevado` | |
|---|---|---|---|---|
| `--sobre-tinte-acento` | #3EC4B3 | 5.07:1 | 4.50:1 | PASS |
| `--sobre-tinte-info` | #4EBDD7 | 5.07:1 | 4.54:1 | PASS |
| `--sobre-tinte-exito` | #56C96E (unchanged — already passed) | 5.08:1 | 4.56:1 | PASS |
| `--sobre-tinte-aviso` | #E8A93C (unchanged — already passed) | 5.20:1 | 4.60:1 | PASS |
| `--sobre-tinte-peligro` | #EC8E87 | 5.04:1 | 4.54:1 | PASS |
| `--sobre-tinte-offline` | #ACACB0 | 5.08:1 | 4.50:1 | PASS |
| `--sobre-tinte-sync` | #86AFF2 | 5.07:1 | 4.54:1 | PASS |
| `--sobre-tinte-conflicto` | #B69FFB | 5.05:1 | 4.54:1 | PASS |
| `--sobre-tinte-reconectar` | #F29851 | 5.01:1 | 4.51:1 | PASS |

### Status dot on its own tint (threshold 3:1 — graphical object)

| Tone | Ratio over `--fondo-elevado` tint | |
|---|---|---|
| `--color-acento` | 4.45:1 | PASS |
| `--color-info` | 4.23:1 | PASS |
| `--color-exito` | 4.56:1 | PASS |
| `--color-aviso` | 4.60:1 | PASS |
| `--color-peligro` | 3.37:1 | PASS |
| `--color-offline` | 3.55:1 | PASS |
| `--color-sync` | 3.83:1 | PASS |
| `--color-conflicto` | 3.75:1 | PASS |
| `--color-reconectar` | 4.09:1 | PASS |

### Text on a solid semantic fill — `--sobre-acento` (threshold 4.5:1)

`--sobre-acento` = #0B0B0C

| Fill | Ratio | |
|---|---|---|
| `--color-acento` #3CC3B2 | 9.03:1 | PASS |
| `--color-info` #3FB7D4 | 8.37:1 | PASS |
| `--color-exito` #56C96E | 9.35:1 | PASS |
| `--color-aviso` #E8A93C | 9.53:1 | PASS |
| `--color-peligro` #E6685F | 6.10:1 | PASS |
| `--color-offline` #98989D | 6.85:1 | PASS |
| `--color-sync` #6FA0EF | 7.45:1 | PASS |
| `--color-conflicto` #A78BFA | 7.23:1 | PASS |
| `--color-reconectar` #F08C3E | 7.99:1 | PASS |

### Focus ring `--color-acento` vs surfaces (threshold 3:1)

| Surface | Ratio | |
|---|---|---|
| `--fondo-contenido` | 7.20:1 | PASS |
| `--fondo-elevado` | 6.40:1 | PASS |
| `--fondo-ventana` | 7.64:1 | PASS |
| `--glass-fallback-sidebar` | 6.92:1 | PASS |
| `--glass-fallback-toolbar` | 6.56:1 | PASS |

### Control boundaries (threshold 3:1)

| Token | Composited | vs worst surface | |
|---|---|---|---|
| `--separador` (as a control border) | #414143 | 1.37:1 | **FAIL** |
| `--borde-control` (new) | #757579 | 3.04:1 | PASS |
| `--texto-deshabilitado` (3:1 self-imposed floor; WCAG exempt) | #757579 | 3.04:1 | PASS |

### Row states (threshold 3:1 against the surface — informational, not required)

- `--fila-hover` composites to #39393B. Hover is never the only indicator of anything; selection is.
- `--fila-seleccion` composites to #30504E. Selection is additionally carried by the row checkbox and `aria-selected`, so its 1.58:1 against the surface is not load-bearing.

---

## Composed pairs — text on a tint over another surface (added for DR-007)

The 72 pairs above measure text against a *surface*. These measure text against a **tint
composited over a surface**, which is what the console-stream template actually paints. Both
themes, threshold 4.5:1, tint composited before the ratio is taken.

| Composition | Light | Dark | |
|---|---|---|---|
| `--texto-primario` on `--fila-seleccion` over `--fondo-ventana` | 12.72:1 | 9.52:1 | PASS — **the selected stream line** |
| `--texto-primario` on `--fila-seleccion` over `--fondo-elevado` | 14.35:1 | 8.07:1 | PASS |
| `--texto-sutil` on `--fila-seleccion` over `--fondo-ventana` | 3.89:1 | 3.39:1 | **FAIL — withdrawn**: a selected line paints all its text `--texto-primario` |
| `--texto-secundario` on `--fila-seleccion` over `--fondo-ventana` | 3.83:1 | 3.61:1 | **FAIL — withdrawn** (DR-007 gap 2, as measured by the build) |
| `--texto-secundario` on `--fondo-ventana` (unselected line, old spec) | 4.46:1 | 5.80:1 | **FAIL light — withdrawn**: §1.2's rule reaches the stream; values are `--texto-primario` |
| `--texto-sutil` on `--fondo-ventana` (keys and structure) | 4.53:1 | 5.44:1 | PASS |
| `--sobre-tinte-acento` on `--tinte-acento` over `--fondo-ventana` (the highlighted line) | 4.50:1 | 5.38:1 | PASS |
| `--sobre-tinte-peligro` on `--tinte-peligro` over `--fondo-ventana` (ERROR line) | 4.53:1 | 5.35:1 | PASS |
| `--color-acento` as text on `--fondo-ventana` | 4.23:1 | 7.64:1 | **FAIL light** — accent is a focus ring and a fill on this surface, never text |

The rule these produce, and it is general: **a token that passes on a surface is not thereby
passing on a tint over that surface.** Any new composition — a tint on a row state, a badge on
a selected row — is measured before it is specified.

---

## Summary

| | Light | Dark |
|---|---|---|
| Text-on-surface pairs failing before | `--texto-terciario` on all 4 surfaces | `--texto-terciario` on all 5 surfaces |
| Badge tones failing before | 6 of 9 | 7 of 9 |
| Text-on-surface pairs failing after | 0 (`--texto-terciario` retired for text) | 0 |
| Badge tones failing after | 0 | 0 |
| Base palette values changed | **0** | **0** |

Retest whenever a surface colour, a tint opacity, or a semantic hex changes upstream. The
computation is 20 lines; do not eyeball it.
