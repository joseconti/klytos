# Klytos — Sistema Modular de Templates v2.0

> Extensión de KLYTOS-ARCHITECTURE-V2.md
> Templates modulares por bloques, creados por IA, output 100% HTML estático.

---

## 1. Problema que Resuelve

En v1.0, un template es un HTML monolítico con `{{variables}}`. Cambiar el footer implica regenerar todo. No hay reutilización entre templates ni preview antes de aplicar.

El nuevo sistema descompone el diseño en **bloques independientes** que se ensamblan durante el build. Cambiar un bloque (ej: el menú) solo requiere re-inyectar ese fragmento HTML — sin rehacer el contenido.

**Principio fundamental:** El build ensambla bloques en HTML estático. Lo que se sirve al visitante es `.html` plano sin PHP, sin JS de renderizado, sin server-side.

**Alcance del core:** Templates para webs institucionales/corporativas. Los bloques y templates de e-commerce (productos, carrito, variantes) se añadirán en un futuro plugin premium "Klytos eCommerce".

---

## 2. Arquitectura: Bloques + Layouts + Page Templates

```
THEME (colores, tipografías, spacing — ya existe en v1.0)
  │
  ├── BLOQUES (piezas HTML reutilizables e independientes)
  │     │
  │     │── ESTRUCTURA (presentes en múltiples páginas)
  │     │     ├── top-bar          ← Barra superior (teléfono, idiomas, RRSS)
  │     │     ├── header           ← Logo + navegación principal
  │     │     ├── menu             ← Menú de navegación
  │     │     ├── footer           ← Pie de página
  │     │     ├── breadcrumb       ← Migas de pan
  │     │     ├── sidebar          ← Barra lateral
  │     │     └── cookie-banner    ← Aviso de cookies
  │     │
  │     │── CONTENIDO (secciones dentro de la página)
  │     │     ├── hero             ← Banner con imagen/video de fondo + CTA
  │     │     ├── text-block       ← Bloque de texto libre con formato
  │     │     ├── image-text       ← Imagen + texto lado a lado
  │     │     ├── gallery          ← Galería de imágenes / portfolio
  │     │     ├── video-embed      ← Video embebido (YouTube, Vimeo)
  │     │     └── blog-list        ← Lista de entradas recientes
  │     │
  │     │── INTERACCIÓN (elementos de conversión)
  │     │     ├── contact-form     ← Formulario de contacto
  │     │     ├── faq-accordion    ← Preguntas frecuentes
  │     │     ├── cta              ← Llamada a la acción
  │     │     └── stats-counter    ← Números/estadísticas animados
  │     │
  │     │── SOCIAL PROOF (confianza y autoridad)
  │     │     ├── testimonials     ← Testimonios de clientes
  │     │     ├── team-grid        ← Grid de equipo
  │     │     ├── logo-bar         ← Logos de clientes/partners
  │     │     └── map-embed        ← Mapa integrado (iframe)
  │     │
  │     └── CUSTOM (creados por la IA según necesidad)
  │
  ├── LAYOUTS (estructura: dónde van los bloques)
  │     ├── full-width             ← Sin sidebar, contenido al 100%
  │     ├── sidebar-left           ← Sidebar izquierda + contenido
  │     ├── sidebar-right          ← Contenido + sidebar derecha
  │     └── (custom)
  │
  └── PAGE TEMPLATES (recetas: qué bloques, en qué orden)
        ├── home                   ← top-bar + header + hero + features + testimonials + cta + footer
        ├── page                   ← top-bar + header + breadcrumb + text-block + footer
        ├── post                   ← top-bar + header + breadcrumb + text-block + sidebar + footer
        ├── contact                ← top-bar + header + breadcrumb + text-block + contact-form + map + footer
        ├── landing                ← header(transparent) + hero + features + testimonials + faq + cta + footer
        ├── gallery                ← top-bar + header + breadcrumb + gallery + footer
        ├── faq                    ← top-bar + header + breadcrumb + faq-accordion + cta + footer
        ├── team                   ← top-bar + header + breadcrumb + team-grid + cta + footer
        ├── services               ← top-bar + header + hero + feature-grid + cta + testimonials + footer
        └── (custom)               ← Combinaciones personalizadas creadas por IA

  (FUTURO — Plugin "Klytos eCommerce"):
        ├── product                ← Producto simple
        ├── product-variable       ← Producto con variantes
        ├── catalog                ← Catálogo / listado de productos
        └── pricing                ← Tabla de precios / planes
```

### 2.1 ¿Qué cambia cuando modificas cada nivel?

| Cambias... | Qué se regenera | Impacto |
|------------|-----------------|---------|
| **Theme** (color, font) | Solo `style.css` | Las páginas HTML no cambian, solo el CSS |
| **Bloque global** (ej: footer) | Solo el fragmento del footer en cada página | Smart rebuild: find-and-replace |
| **Layout** | Las páginas que usan ese layout | Re-ensamblaje de bloques |
| **Page Template** | Las páginas de ese tipo | Se reconstruyen con nuevos bloques/orden |
| **Contenido** (una página) | Solo esa página | Un solo archivo HTML |

---

## 3. Estructura de un Bloque

### 3.1 Datos almacenados en `data/blocks/{block_id}.json.enc`

```json
{
  "id": "header",
  "name": "Header Principal",
  "category": "structure",
  "version": 2,
  "status": "active",
  "scope": "global",
  
  "slots": {
    "logo_url": { "type": "image", "description": "Logo del sitio" },
    "logo_alt": { "type": "text", "default": "{{site_name}}" },
    "show_tagline": { "type": "boolean", "default": false },
    "sticky": { "type": "boolean", "default": true },
    "style": { "type": "select", "options": ["transparent", "solid", "gradient"], "default": "solid" }
  },

  "html": "<header class=\"klytos-header klytos-header--{{style}}\" ...>...</header>",
  "css": ".klytos-header { ... }",
  "js": "// Opcional: toggle mobile menu (vanilla JS mínimo)",

  "sample_data": {
    "logo_url": "/assets/images/logo.svg",
    "logo_alt": "Mi Sitio",
    "sticky": true,
    "style": "solid"
  },

  "created_at": "2026-03-26T10:00:00Z",
  "updated_at": "2026-03-26T14:30:00Z"
}
```

### 3.2 Categorías de Bloques (Core)

| Categoría | Bloques incluidos | Uso |
|-----------|-------------------|-----|
| `structure` | top-bar, header, menu, footer, breadcrumb, sidebar, cookie-banner | Presentes en múltiples páginas, raramente cambian |
| `content` | hero, text-block, image-text, gallery, video-embed, blog-list | Secciones de contenido dentro de la página |
| `interaction` | contact-form, faq-accordion, cta, stats-counter | Elementos interactivos o de conversión |
| `social-proof` | testimonials, team-grid, logo-bar, map-embed | Confianza, autoridad, ubicación |
| `custom` | Cualquier bloque creado por la IA | Específicos del sitio |

> **Nota:** La categoría `commerce` (product-info, product-variants, product-card, pricing-table, catalog-grid) será aportada por el plugin "Klytos eCommerce". El core no incluye bloques de comercio.

### 3.3 Scope de un Bloque

| Scope | Significado | Ejemplo |
|-------|------------|---------|
| `global` | Mismos datos en todo el sitio | header, footer, top-bar, cookie-banner |
| `template` | Se configura a nivel de page template | sidebar, hero style |
| `page` | Cada página tiene sus propios datos | text-block, gallery items, hero content |

### 3.4 Tipos de Slot

| Tipo | Validación | Ejemplo |
|------|-----------|---------|
| `text` | String, max_length, tag (h1, h2, p, span) | Títulos, párrafos |
| `richtext` | HTML sanitizado (allowlist) | Contenido con formato |
| `image` | URL, recommended_size | Fotos, banners, logos |
| `url` | URL válida (absoluta o relativa) | Links, CTAs |
| `icon` | Emoji o clase de icono | Feature icons |
| `color` | Hex o CSS variable | Backgrounds |
| `number` | Min, max, step, default | Opacidades, cantidades |
| `select` | Enum de opciones | Estilos, layouts |
| `boolean` | true/false | Mostrar/ocultar |
| `array` | Lista repetible con item_slots | Features, testimonials |
| `html` | HTML raw (escape valve) | Embeds, custom code |
| `date` | ISO 8601 | Fechas de publicación |
| `email` | Email válido | Contacto |
| `phone` | Teléfono | Contacto |

> **Nota:** El plugin eCommerce añadirá los tipos `price` (número + moneda) y `variant-selector` (selector de variantes).

---

## 4. Estructura de un Page Template

Un Page Template es una receta: qué bloques, en qué orden, con qué layout.

### 4.1 Datos almacenados en `data/page-templates/{type}.json.enc`

```json
{
  "type": "home",
  "name": "Página de Inicio",
  "description": "Homepage con hero, features, testimonials y CTA",
  "version": 3,
  "status": "active",
  "approved_by": 1,
  "approved_at": "2026-03-26T14:00:00Z",

  "layout": "full-width",

  "structure": [
    { "block": "top-bar",       "scope": "global", "required": false },
    { "block": "header",        "scope": "global", "required": true },
    { "block": "hero",          "scope": "page",   "required": true },
    { "block": "feature-grid",  "scope": "page",   "required": false, "config": { "min_items": 3, "max_items": 6 } },
    { "block": "testimonials",  "scope": "page",   "required": false, "config": { "min_items": 1, "max_items": 8 } },
    { "block": "stats-counter", "scope": "page",   "required": false },
    { "block": "cta",           "scope": "page",   "required": false },
    { "block": "footer",        "scope": "global", "required": true }
  ],

  "wrapper_html": "<!DOCTYPE html>\n<html lang=\"{{page_lang}}\">\n<head>...</head>\n<body class=\"klytos-page klytos-page--{{template_type}}\">\n  {{blocks_html}}\n  {{body_scripts}}\n</body>\n</html>"
}
```

### 4.2 Page Templates Built-in (Core)

| Tipo | Estructura de bloques | Uso |
|------|----------------------|-----|
| `home` | top-bar → header → hero → feature-grid → testimonials → stats → cta → footer | Homepage institucional |
| `page` | top-bar → header → breadcrumb → text-block → footer | Página genérica (Sobre nosotros, Legal, etc.) |
| `post` | top-bar → header → breadcrumb → text-block → sidebar(blog-list) → footer | Entrada de blog |
| `contact` | top-bar → header → breadcrumb → text-block → contact-form → map-embed → footer | Página de contacto |
| `landing` | header(transparent) → hero → feature-grid → testimonials → faq-accordion → cta → footer | Landing page de campaña |
| `gallery` | top-bar → header → breadcrumb → gallery → footer | Portfolio / galería de imágenes |
| `faq` | top-bar → header → breadcrumb → faq-accordion → cta → footer | Preguntas frecuentes |
| `team` | top-bar → header → breadcrumb → team-grid → cta → footer | Página de equipo |
| `services` | top-bar → header → hero → feature-grid → cta → testimonials → footer | Servicios de la empresa |

> **Nota:** Los page templates de comercio (product, product-variable, catalog, pricing) serán aportados por el plugin "Klytos eCommerce" vía el filter `page_template.available_types`.

---

## 5. Formato de una Página (v2.0)

```json
{
  "slug": "index",
  "title": "Mi Empresa",
  "template": "home",
  "status": "published",
  "lang": "es",
  "meta_description": "...",

  "content": {
    "hero": {
      "heading": "Innovación al servicio de tu negocio",
      "subheading": "Más de 15 años ayudando a empresas a crecer",
      "cta_text": "Contactar",
      "cta_url": "/contacto",
      "background_image": "/assets/images/hero.jpg"
    },
    "feature-grid": {
      "section_title": "Nuestros Servicios",
      "items": [
        { "icon": "📊", "title": "Consultoría", "description": "..." },
        { "icon": "💻", "title": "Desarrollo", "description": "..." },
        { "icon": "📱", "title": "Marketing Digital", "description": "..." }
      ]
    },
    "testimonials": { ... },
    "cta": { ... }
  },

  "content_html": null
}
```

**Retrocompatibilidad:** Si `content_html` tiene valor y `content` es null → se usa directamente (modo v1.0).

---

## 6. Motor de Build Modular

### 6.1 Proceso de Build

```
BUILD SITE:

  1. FASE CSS (una vez):
     Leer theme + todos los bloques activos → generar style.css + blocks.css
     
  2. FASE JS (una vez):
     Concatenar JS de bloques que lo necesitan → blocks.js
     
  3. FASE BLOQUES GLOBALES (una vez):
     Renderizar bloques scope=global → cachear en memoria
     
  4. FASE PÁGINAS (por cada página publicada):
     a. Leer page → determinar template
     b. Leer page template → obtener structure
     c. Para cada bloque en structure:
        - scope=global → usar HTML cacheado
        - scope=page → renderizar con datos de page.content[block_id]
        - Sin datos + required=false → omitir
        - Sin datos + required=true → usar sample_data
     d. Concatenar → {{blocks_html}}
     e. Insertar en wrapper_html + variables globales
     f. Escribir public/{slug}.html
     
  5. FASE SEO: robots.txt + sitemap.xml
```

### 6.2 Smart Rebuild (clave de la eficiencia)

El build inyecta comentarios HTML invisibles que delimitan cada bloque:

```html
<body>
  <!--klytos:block:top-bar-->
  <div class="klytos-top-bar">...</div>
  <!--/klytos:block:top-bar-->

  <!--klytos:block:header-->
  <header class="klytos-header">...</header>
  <!--/klytos:block:header-->

  <!--klytos:block:hero-->
  <section class="klytos-hero">...</section>
  <!--/klytos:block:hero-->

  <!--klytos:block:footer-->
  <footer class="klytos-footer">...</footer>
  <!--/klytos:block:footer-->
</body>
```

Para un smart rebuild del footer:

```php
// Solo find-and-replace en archivos HTML existentes
// No parsea templates, no renderiza contenido
foreach (glob('public/**/*.html') as $file) {
    $html = file_get_contents($file);
    $pattern = '/<!--klytos:block:footer-->.*?<!--\/klytos:block:footer-->/s';
    $replacement = '<!--klytos:block:footer-->' . $newFooterHtml . '<!--/klytos:block:footer-->';
    $html = preg_replace($pattern, $replacement, $html);
    file_put_contents($file, $html);
}
// ~50ms para 100 páginas
```

### 6.3 Cuándo se usa cada estrategia

| Cambio | Estrategia | Velocidad |
|--------|-----------|-----------|
| Color/font del theme | Regenerar solo CSS | Instantáneo |
| Contenido de bloque global (footer, header) | Smart rebuild | ~50ms/100 páginas |
| Menú | Smart rebuild del bloque header/menu | ~50ms/100 páginas |
| Page template modificado | Rebuild solo páginas de ese tipo | Depende del nº |
| Contenido de una página | Rebuild solo esa página | Instantáneo |
| Nuevo bloque añadido a un template | Rebuild páginas de ese tipo | Depende del nº |
| Build completo | Todo | Depende del sitio |

---

## 7. Clases PHP

### 7.1 BlockManager.php

```php
class BlockManager {
    public function save(string $blockId, array $blockData): array;
    public function get(string $blockId): ?array;
    public function list(string $category = 'all'): array;
    public function delete(string $blockId): bool;
    public function render(string $blockId, array $data = []): string;
    public function renderPreview(string $blockId): string;
    public function setGlobalData(string $blockId, array $data): void;
    public function getGlobalData(string $blockId): ?array;
    public function getAvailableTypes(): array;
    public function getSlots(string $blockId): array;
}
```

### 7.2 PageTemplateManager.php

```php
class PageTemplateManager {
    public function save(string $type, array $templateData): array;
    public function get(string $type): ?array;
    public function list(): array;
    public function delete(string $type): bool;
    public function addBlock(string $templateType, array $blockConfig): array;
    public function removeBlock(string $templateType, string $blockId): bool;
    public function reorderBlocks(string $templateType, array $blockIds): void;
    public function preview(string $type): string;
    public function previewWithData(string $type, array $pageData): string;
    public function approve(string $type, int $userId): array;
    public function setDraft(string $type): void;
    public function renderPage(string $templateType, array $pageData, array $globalBlocks, array $siteData): string;
    public function getRequiredContent(string $type): array;
    public function getVersionHistory(string $type): array;
    public function restoreVersion(string $type, int $version): array;
}
```

---

## 8. Tools MCP

### 8.1 Bloques (8 tools)

| Tool | Descripción | readOnly |
|------|-------------|----------|
| `klytos_create_block` | Crea un bloque reutilizable | ❌ |
| `klytos_update_block` | Actualiza HTML/CSS/slots de un bloque | ❌ |
| `klytos_get_block` | Obtiene un bloque completo | ✅ |
| `klytos_list_blocks` | Lista bloques por categoría | ✅ |
| `klytos_delete_block` | Elimina un bloque custom | ❌ |
| `klytos_preview_block` | Renderiza con sample_data | ✅ |
| `klytos_set_global_block_data` | Define contenido de bloque global | ❌ |
| `klytos_get_block_slots` | Schema de slots | ✅ |

### 8.2 Page Templates (11 tools)

| Tool | Descripción | readOnly |
|------|-------------|----------|
| `klytos_create_page_template` | Crea un page template | ❌ |
| `klytos_update_page_template` | Actualiza estructura | ❌ |
| `klytos_get_page_template` | Obtiene un page template | ✅ |
| `klytos_list_page_templates` | Lista todos | ✅ |
| `klytos_delete_page_template` | Elimina un custom | ❌ |
| `klytos_add_block_to_template` | Añade bloque | ❌ |
| `klytos_remove_block_from_template` | Quita bloque | ❌ |
| `klytos_reorder_template_blocks` | Cambia orden | ❌ |
| `klytos_preview_page_template` | Preview con sample_data | ✅ |
| `klytos_approve_page_template` | Aprueba para builds | ❌ |
| `klytos_get_template_content_schema` | Qué datos necesita la IA | ✅ |

### 8.3 Build (actualizado, 2 tools nuevos)

| Tool | Descripción |
|------|-------------|
| `klytos_rebuild_block` | Smart rebuild de un bloque global en todas las páginas |
| `klytos_rebuild_css` | Regenerar solo CSS (theme + bloques) |

---

## 9. Extensibilidad via Plugins

El sistema de templates está diseñado para que los plugins añadan bloques y page templates propios a través de hooks.

### 9.1 Cómo un Plugin Añade Bloques

```php
// En el init.php del plugin "Klytos eCommerce"

// Registrar nuevos tipos de bloque
klytos_add_filter('block.available_types', function (array $types): array {
    $types[] = [
        'id' => 'product-info',
        'name' => 'Producto Simple',
        'category' => 'commerce',
        'description' => 'Ficha de producto con imagen, precio y CTA',
        'slots' => [
            'name' => ['type' => 'text', 'tag' => 'h1'],
            'price' => ['type' => 'price'],
            'currency' => ['type' => 'select', 'options' => ['EUR', 'USD', 'GBP']],
            'description' => ['type' => 'richtext'],
            'image' => ['type' => 'image'],
            'cta_text' => ['type' => 'text', 'default' => 'Comprar'],
            'cta_url' => ['type' => 'url'],
        ],
        'html' => '...',
        'css' => '...',
    ];
    // ... product-variants, product-card, pricing-table, catalog-grid
    return $types;
});

// Registrar nuevos page templates
klytos_add_filter('page_template.available_types', function (array $templates): array {
    $templates[] = [
        'type' => 'product',
        'name' => 'Producto Simple',
        'structure' => [
            ['block' => 'top-bar', 'scope' => 'global'],
            ['block' => 'header', 'scope' => 'global'],
            ['block' => 'breadcrumb', 'scope' => 'template'],
            ['block' => 'product-info', 'scope' => 'page', 'required' => true],
            ['block' => 'feature-grid', 'scope' => 'page'],
            ['block' => 'footer', 'scope' => 'global'],
        ],
    ];
    // ... product-variable, catalog, pricing
    return $templates;
});

// Registrar tipo de slot "price"
klytos_add_filter('block.slot_types', function (array $types): array {
    $types['price'] = [
        'validation' => 'numeric',
        'render' => function($value, $currency) { return number_format($value, 2) . ' ' . $currency; }
    ];
    return $types;
});
```

### 9.2 Esto Significa

El core de Klytos viene con ~20 bloques y ~9 page templates para webs institucionales. El plugin "Klytos eCommerce" (premium) añadiría:

- **Bloques:** product-info, product-variants, product-card, pricing-table, catalog-grid, cart-summary
- **Page Templates:** product, product-variable, catalog, pricing
- **Slot types:** price, variant-selector
- **JS:** Selector de variantes, actualización de precio dinámico

Todo esto sin tocar el core — solo via hooks.

---

## 10. Hooks del Sistema

### Actions

| Hook | Cuándo | Args |
|------|--------|------|
| `block.before_save` | Antes de guardar un bloque | `$blockId, $data` |
| `block.after_save` | Después de guardar | `$blockId, $data` |
| `block.global_data_changed` | Al cambiar datos de bloque global | `$blockId, $data` |
| `page_template.approved` | Al aprobar un page template | `$type, $userId` |
| `page_template.after_save` | Después de guardar un page template | `$type, $data` |

### Filters

| Hook | Qué filtra | Tipo |
|------|-----------|------|
| `block.rendered_html` | HTML de un bloque renderizado | `string` |
| `block.css` | CSS de un bloque | `string` |
| `block.available_types` | Tipos de bloque disponibles | `array` |
| `block.slot_types` | Tipos de slot disponibles | `array` |
| `page_template.wrapper_html` | Wrapper del page template | `string` |
| `page_template.structure` | Lista de bloques de un template | `array` |
| `page_template.available_types` | Page templates disponibles | `array` |
| `build.global_blocks` | Bloques globales cacheados | `array` |

---

## 11. Flujo Completo: La IA Crea un Sitio Institucional

```
USUARIO → Claude: "Crea un sitio web para mi despacho de abogados
                   García & Asociados en Madrid"

CLAUDE → MCP:

  === FASE 1: Tema + Bloques globales ===

  1. klytos_set_theme({ colors: { primary: "#1B365D", ... }, fonts: { heading: "Merriweather" } })
  2. klytos_create_block({ id: "top-bar", ... })
  3. klytos_create_block({ id: "header", ... })
  4. klytos_create_block({ id: "footer", ... })
  5. klytos_set_global_block_data("top-bar", { phone: "+34 915 555 123", ... })
  6. klytos_set_global_block_data("header", { logo_url: "/assets/images/logo.svg", sticky: true })
  7. klytos_set_global_block_data("footer", { columns: [...], copyright: "© 2026 García & Asociados" })

  === FASE 2: Bloques de contenido ===

  8-12. klytos_create_block({ id: "hero", ... }), feature-grid, testimonials, cta, etc.

  === FASE 3: Page templates ===

  13. klytos_create_page_template({ type: "home", structure: [...] })
  14. klytos_create_page_template({ type: "services", structure: [...] })
  15. klytos_preview_page_template({ type: "home" }) → Preview

  16. CLAUDE → Usuario: "He diseñado la home y la página de servicios.
      Preview: [link]. ¿Te gustan?"
  17. USUARIO → "Sí, apruébalos"
  18. klytos_approve_page_template({ type: "home" })
  19. klytos_approve_page_template({ type: "services" })

  === FASE 4: Crear páginas con contenido ===

  20-28. klytos_create_page({ slug: "index", template: "home", content: {...} })
         klytos_create_page({ slug: "servicios", template: "services", content: {...} })
         klytos_create_page({ slug: "equipo", template: "team", content: {...} })
         klytos_create_page({ slug: "contacto", template: "contact", content: {...} })
         ...

  29. klytos_set_menu({ items: [...] })
  30. klytos_build_site()

RESULTADO → Sitio institucional completo con bloques consistentes.
```

### Después: Cambiar solo el footer

```
USUARIO → "Añade la dirección de la nueva oficina en Barcelona al footer"

CLAUDE → MCP:
  1. klytos_set_global_block_data("footer", { ..., column_4: { title: "Barcelona", content: "..." } })
  2. klytos_rebuild_block({ block_id: "footer" })
     → Smart rebuild: ~50ms, solo reemplaza footer en todos los HTML

RESULTADO → Nueva dirección en el footer de TODAS las páginas. Cero regeneración de contenido.
```

---

## 12. Panel Admin

### 12.1 Listado de Bloques

```
/admin/blocks.php

┌──────────────────────────────────────────────────────────────────┐
│  Bloques de Diseño                                                │
├──────────────────────────────────────────────────────────────────┤
│  ESTRUCTURA:                                                      │
│  📐 top-bar      v1  global  ✅   "Barra superior con tel..."   │
│  📐 header       v2  global  ✅   "Logo + navegación sticky"    │
│  📐 footer       v3  global  ✅   "3 columnas + copyright"      │
│  📐 breadcrumb   v1  template ✅  "Migas de pan"                │
│                                                                   │
│  CONTENIDO:                                                       │
│  🎨 hero         v2  page    ✅   "Banner con imagen de fondo"  │
│  🎨 feature-grid v1  page    ✅   "Grid 3-6 cards"              │
│  🎨 text-block   v1  page    ✅   "Bloque de texto libre"       │
│                                                                   │
│  INTERACCIÓN:                                                     │
│  ⚡ cta           v1  page    ✅   "Llamada a la acción"         │
│  ⚡ contact-form  v1  page    ✅   "Formulario de contacto"      │
│  ⚡ faq-accordion v1  page    ✅   "Preguntas frecuentes"        │
│                                                                   │
│  SOCIAL PROOF:                                                    │
│  💬 testimonials v1  page    ✅   "Testimonios de clientes"     │
│  💬 team-grid    v1  page    ✅   "Grid de equipo"              │
│                                                                   │
│  ℹ️ Los bloques son creados por la IA. Puedes previsualizarlos   │
│     y editar los datos globales (header, footer, etc.) aquí.      │
└──────────────────────────────────────────────────────────────────┘
```

### 12.2 Listado de Page Templates

```
/admin/page-templates.php

┌──────────────────────────────────────────────────────────────────┐
│  Page Templates                                                   │
├──────────────────────────────────────────────────────────────────┤
│  🏠 home          v3  ✅ Aprobado   7 bloques   1 página        │
│  📄 page          v2  ✅ Aprobado   4 bloques   6 páginas       │
│  📝 post          v1  ✅ Aprobado   5 bloques   12 páginas      │
│  📞 contact       v1  ✅ Aprobado   6 bloques   1 página        │
│  🎯 landing       v1  ✅ Aprobado   7 bloques   2 páginas       │
│  🖼️ gallery       v1  ✅ Aprobado   4 bloques   1 página        │
│  ❓ faq           v1  ✅ Aprobado   4 bloques   1 página        │
│  👥 team          v1  ✅ Aprobado   4 bloques   1 página        │
│  💼 services      v2  ✅ Aprobado   6 bloques   1 página        │
│                                                                   │
│  [Preview]  [Ver bloques]  [Aprobar]                              │
└──────────────────────────────────────────────────────────────────┘
```

### 12.3 Editor de Datos Globales de Bloque

```
/admin/block-data.php?id=footer

Formulario con los slots del bloque, preview en vivo, y botón
[Guardar y rebuild footer] que ejecuta smart rebuild.
```

---

## 13. Esquema SQL

```sql
CREATE TABLE kly_blocks (
    id          VARCHAR(64) PRIMARY KEY,
    name        VARCHAR(128) NOT NULL,
    category    VARCHAR(32) NOT NULL,
    data        LONGTEXT NOT NULL,            -- JSON enc (html, css, js, slots, sample_data)
    scope       ENUM('global', 'template', 'page') DEFAULT 'page',
    global_data LONGTEXT,                     -- JSON enc (datos si scope=global)
    version     INT UNSIGNED NOT NULL DEFAULT 1,
    status      ENUM('active', 'draft') DEFAULT 'active',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kly_page_templates (
    type        VARCHAR(64) PRIMARY KEY,
    name        VARCHAR(128) NOT NULL,
    data        LONGTEXT NOT NULL,            -- JSON enc (structure, layout, wrapper_html)
    status      ENUM('active', 'draft') DEFAULT 'draft',
    version     INT UNSIGNED NOT NULL DEFAULT 1,
    approved_by INT UNSIGNED,
    approved_at DATETIME,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (approved_by) REFERENCES kly_users(id) ON DELETE SET NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 14. Checklist de Implementación

### Core

- [ ] `core/BlockManager.php` — CRUD + renderizado de bloques
- [ ] `core/PageTemplateManager.php` — CRUD + ensamblaje
- [ ] `data/blocks/` y `data/page-templates/` (flat-file mode)
- [ ] Tablas SQL kly_blocks y kly_page_templates
- [ ] Build modular con marcadores HTML (smart rebuild)
- [ ] `klytos_rebuild_block` y `klytos_rebuild_css` tools
- [ ] `core/MCP/Tools/BlockTools.php` — 8 tools
- [ ] `core/MCP/Tools/PageTemplateTools.php` — 11 tools
- [ ] `admin/blocks.php` — Listado
- [ ] `admin/page-templates.php` — Listado
- [ ] `admin/block-data.php` — Editor de datos globales
- [ ] `admin/template-preview.php` — Preview visual
- [ ] Retrocompatibilidad content_html (v1.0) + content (v2.0)
- [ ] Hooks de bloques y templates

### Bloques built-in (core — web institucional)

- [ ] top-bar, header, menu, footer, breadcrumb, sidebar, cookie-banner
- [ ] hero, text-block, image-text, gallery, video-embed, blog-list
- [ ] contact-form, faq-accordion, cta, stats-counter
- [ ] testimonials, team-grid, logo-bar, map-embed

### Page templates built-in (core)

- [ ] home, page, post, contact, landing, gallery, faq, team, services

### Futuro plugin "Klytos eCommerce"

- [ ] Bloques: product-info, product-variants, product-card, pricing-table, catalog-grid, cart-summary
- [ ] Page templates: product, product-variable, catalog, pricing
- [ ] Slot types: price, variant-selector
- [ ] JS: Selector de variantes, precio dinámico

---

*Documento extensión de KLYTOS-ARCHITECTURE-V2.md.*
*Versión: 2.0.0 — Fecha: 2026-03-26*
