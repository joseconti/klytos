# PackDesk Design System

Identidad y sistema de diseño **multiplataforma** de **PackDesk** — la marca matriz de **José Conti** para software de comercio. Un mismo ADN (color, tipografía, nombre, icono y tono) aplicado a **webs, plugins, extensiones y apps nativas de macOS, Windows, iOS y Android**. Paleta **B "Mediterráneo"** (verde petróleo `#0E8074`). Claro y oscuro en todo.

---

## Cómo reutilizarlo
- **Empezar una app o web nueva:** copiar la plantilla de **`templates/`** de tu plataforma — *App macOS · App iOS · App Android · App Windows · Web Landing* — y cambiar cadenas. Traen tokens, fuentes locales, marca en su sitio canónico y, en Apple, **Liquid Glass + fallback sólido** (tweak Material) ya cableados.
- **Crear un producto o pantalla nueva:** seguir **`guidelines/crear-producto-nuevo.md`** — receta por plataforma (tipo, navegación, material, fallback, kit base) + checklist de entrega.
- **Como sistema en tu organización:** menú *Share → File type → **Design System***. A partir de ahí, cualquier proyecto puede adjuntarlo y heredar tokens, componentes y marca.
- **Web / plugins / extensiones:** enlaza `styles.css` (un único archivo: trae todos los tokens y las fuentes) y usa los componentes de `components/`.
- **Apps nativas:** usa los tokens como tema (acento + neutros + escala de tipo), el font del sistema de cada SO y los assets de icono de `assets/`.
- **Claude Code:** descarga el proyecto y usa `SKILL.md` como Agent Skill.

---

## Qué es PackDesk
Marca paraguas de herramientas de comercio. El producto insignia —la app **PackDesk**— es nativa de macOS para gestionar tiendas WooCommerce / PrestaShop por perfiles de empleado, con pagos **Redsys** (incl. preautorizaciones), trabajo offline y sincronización. Cada nuevo producto (apps, paneles, plugins, extensiones, en cualquier plataforma) nace con su propio nombre e icono en este lenguaje y se firma **by PackDesk**. Autoría legal: **José Conti**.

**Fuentes** (no se asume acceso; se guardan por si lo tienes): bundle de handoff `app-macos-redsys/` (prototipos HTML + SPEC); tokens en `tokens/` y `brand-assets/tokens.css`; icono maestro y wordmark en `assets/`; brand book en `Imagen Corporativa PackDesk.dc.html`.

---

## Plataformas — capa de marca vs capa de plataforma
El sistema separa lo que **nunca cambia** de lo que **se adapta** a cada sistema operativo.

**Capa de marca — idéntica en todas partes**
- Color Mediterráneo (acento + semánticos + estados de producto), claro/oscuro.
- Nombre/wordmark: **Geist Bold bicolor** — 1ª parte en neutro, 2ª en acento; el neutro se adapta al fondo (negro sobre claro, blanco sobre oscuro).
- Icono de producto: squircle en acento + figura blanca; el **check** es la firma común de la familia.
- Tono impersonal en español, **sin emoji**. Iconografía de línea.

**Capa de plataforma — adopta las convenciones nativas, tintadas con la marca**

| Plataforma | Tipografía | Controles | Objetivo táctil | Icono | Materiales |
|---|---|---|---|---|---|
| Web · plugins · extensiones | Geist / Geist Mono | Componentes `components/` | 28 px (puntero) | favicon SVG/PNG del squircle | sombras del sistema |
| macOS | SF Pro / SF Mono | SwiftUI (HIG), sidebar | 28 px | squircle (Icon Composer) | Liquid Glass + fallback sólido |
| Windows | Segoe UI Variable | WinUI / Fluent | 32 px | tile/Store cuadrado redondeado | Mica / Acrylic |
| iOS · iPadOS | SF Pro / SF Mono | SwiftUI / UIKit, tab bar | 44 pt | squircle | Liquid Glass + fallback sólido |
| Android | Roboto (o Geist embebido) | Material 3, bottom nav | 48 dp | icono adaptativo (fondo + figura) | elevación Material |
| Email (transaccional) | Helvetica/Arial (pila segura) | tablas + estilos inline, 600 px | botón ≥ 44 px | wordmark en texto | sólido, sin translucidez |

El acento de marca **sustituye al "accent/tint" del sistema** en cada plataforma. La navegación sigue lo nativo: sidebar en escritorio, tab bar en iOS, bottom nav / drawer en Android. Los componentes de `components/` son la implementación web/React (universal) y la **referencia visual** para reconstruir lo mismo en SwiftUI / WinUI / Compose.

---

## Content fundamentals
- **Voz:** copy de interfaz **en inglés** (regla prioritaria del brief), impersonal, *sentence case* ("Mark as shipped"). i18n en/es/ca con +30 % de expansión; todo string es sustituible. Los emails transaccionales del mercado España se mantienen en español.
- **Tono:** profesional, sobrio, técnico, de confianza. Claridad por encima de simpatía.
- **Sin emojis** en ningún contexto de marca.
- **Errores:** nunca un código a secas; siempre mensaje + acción ("El pedido cambió en la tienda — *Refrescar*").
- **Cifras:** SKU, nº de pedido, importes, versiones y referencias en monoespaciada (alinean en columna y no bailan al cambiar de dígito).
- **Casing:** *Sentence case* en botones y títulos ("Completar pedido"); MAYÚSCULAS solo en eyebrows/labels mono con letter-spacing.

Ejemplos: botón primario "Completar pedido"; badge "Pendiente de pago"; toast "Pedido #1842 marcado como enviado".

---

## Visual foundations
- **Universal (todas las plataformas):** color Mediterráneo; tipografía Geist/SF; radios control 6 / card 10 / popover 12 / pill 999; sombras en 4 elevaciones (card/popover/hoja/ventana); iconografía de línea; **sin gradientes** decorativos salvo el del icono.
- **Tarjetas:** fondo elevado, radius 10, sombra `0 1px 3px rgba(0,0,0,.10)`, **sin bordes de color** ni acento a la izquierda.
- **Materiales (Apple 26+):** **Liquid Glass en toda la capa de navegación** —barras, tab bar, sidebar, hojas, popovers, FAB y controles flotantes—, nunca sobre el contenido y nunca cristal sobre cristal; tinte solo en la acción primaria. **En nativo se adopta la API del sistema** —SwiftUI `.glassEffect`, `.buttonStyle(.glass/.glassProminent)`, `GlassEffectContainer`; UIKit `UIGlassEffect`; AppKit `NSGlassEffectView`—, **nunca un cristal imitado** con blur propio. Comportamientos a diseñar: lozenge deslizable en tab bar/segmented, tab bar minimizable al scroll, scroll edge effect, morphing. El blur clásico (`--glass-*`) queda **solo como fallback** ("Reducir transparencia" u OS < 26) y se entregan siempre ambas versiones. Los tokens `--cristal-*` (`tokens/glass.css`) son únicamente la recreación web para prototipos.
- **Escritorio (macOS/Windows):** densidades de fila 36 / 52 / 68; en Windows, **Mica/Acrylic** solo en sidebar y toolbar con fallback sólido; ventana ≥ 980×640; sidebar.
- **Móvil (iOS/Android):** objetivos táctiles 44 pt / 48 dp; filas 56 px; safe-areas; en iOS tab bar flotante de cristal + acción primaria separada; en Android bottom nav Material con indicador pill, elevación sin translucidez.
- **Hover** (puntero): filas `rgba(0,0,0,.045)` / blanco `.06`; controles aclaran/oscurecen 120 ms. **Press:** cambio de color, sin "bounce". **Foco:** anillo 2 px en acento, offset 2 px — **siempre visible**.
- **Animación:** curvas `cubic-bezier(.32,.72,0,1)` (estándar) y `(.16,1,.3,1)` (salida); 120–280 ms; todo con variante **"Reducir movimiento"**. El punto de sync pulsa 1200 ms.

---

## Iconography
**Icono de producto por plataforma** — mismo dibujo, distinto contenedor:
- **iOS / iPadOS / macOS:** *squircle* (esquina continua, radio ≈ 0.2237 × lado), sin transparencia.
- **Android:** *icono adaptativo* — capa de fondo en acento + capa de figura (blanca) dentro de la zona segura (66 %); el sistema aplica la máscara (círculo, squircle, etc.).
- **Windows:** cuadrado de esquinas suaves para tile y Store.
- **Web:** favicon SVG/PNG (16–512) y avatar para redes desde el master.
- **Monocromo:** figura en un solo color para barra de menús, marcas de agua e impresión.

**Iconos de UI:** línea de ~1.5–2 px, esquinas redondeadas; en nativo, **SF Symbols** (Apple) / **Material Symbols** (Android) / **Fluent/Segoe icons** (Windows) — mismo peso visual. **Sin emoji**; sin unicode como icono. El **check** del icono se reutiliza como sello de "trabajo hecho / by PackDesk".

**Construir el logo de un producto** (plugin, skill, app). Toda la familia comparte un **grid canónico de 120 u** y la **misma proporción**: figura blanca ≈38 % del lado, centrada en (60,60) y girada 45°, sobre el contenedor en acento. Para un producto nuevo se cambia **solo la figura interior** —conservando grid, máscara (squircle 22.5 % · tile 10 % · círculo 50 %), tono y proporción— y se mantiene el **check** como sello común. Fuente única: el componente `AppIcon` (props `mask` · `tone` · `gradient` · `glyph`) y los assets `assets/favicon.svg` / `assets/icono-packdesk.svg`. Detalle visual en la tarjeta *Construcción del icono*; **procedimiento completo paso a paso en `guidelines/construir-logo-producto.md`**.

Assets en `assets/` (icono maestro SVG en capas, apto Icon Composer; wordmark; símbolos).

---

## Index (manifiesto)
- **`styles.css`** — entrada global (link único). Importa todo `tokens/` (fuentes Geist auto-hospedadas en `fonts/`).
- **`templates/`** — plantillas de arranque cableadas por plataforma (macOS, iOS, Android, Windows, web). Marca fija vía componentes; Apple con Liquid Glass + fallback.
- **`tokens/`** — `colors.css`, `typography.css`, `spacing.css`, `effects.css`, `motion.css`, `platform.css`, `glass.css` (Liquid Glass + fallback), `fonts.css`.
- **`guidelines/`** — tarjetas de especímenes (Colores, Tipografía, Espaciado, Marca, Plataformas) y guías paso a paso: `crear-producto-nuevo.md` (empezar aquí), `construir-logo-producto.md`.
- **`components/`** — primitivas React: AppIcon, Logo, Badge, Button, Card, Input, Select, Switch, Toast, Spinner, Checkbox, Radio, SearchField, Textarea, Slider, Stepper, DatePicker, FileUpload, Tabs, SegmentedControl, Breadcrumb, Pagination, Menu, Dialog, Sheet, Popover, Tooltip, Banner, Table, ListRow, StatCard, Avatar, Chip, EmptyState, Skeleton, Progress. Cada una con `.d.ts` (props cerradas → reglas de adherencia automáticas) y `.prompt.md` con su uso exacto.
- **`ui_kits/`** — recreaciones de pantalla por producto/plataforma: macOS, **iOS 26 Liquid Glass + fallback**, Android (Material 3), Windows (Fluent/Mica), móvil web y **email (estándar + transaccional)**.
- **`assets/`** — logos, icono, símbolos, favicon.
- **`Imagen Corporativa PackDesk.dc.html`** — brand book navegable.
- **`SKILL.md`** — para llevar el sistema a Claude Code.

> **Caveat de fuentes:** Geist se carga desde Google Fonts en `tokens/fonts.css`. Para auto-hospedar (apps offline, plugins empaquetados), sustituir por `@font-face` locales `.woff2`.
