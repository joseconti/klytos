# Klytos -- Arquitectura del Sistema de Templates

**Version:** 0.12.0
**Fecha:** 31 de marzo de 2026
**Estado:** Propuesta de diseno

---

## 1. Diagnostico del sistema actual

### 1.1. Que hay ahora

El sistema actual de templates funciona en dos capas:

- **Archivos HTML** en `installer/templates/` (default.html, landing.html, blog-post.html, blank.html). Son archivos fisicos que el BuildEngine lee del disco.
- **Templates en base de datos** almacenados en `templates.json.enc`, consultados primero por `loadTemplate()`. No hay interfaz para crearlos ni editarlos.

El BuildEngine recorre cada pagina publicada, carga su plantilla (por nombre), reemplaza `{{placeholders}}` con datos de la pagina, y escribe el HTML resultante en `public/`.

### 1.2. Problemas criticos

**El directorio templates/ se sobrescribe en cada actualizacion.** El Updater copia tres directorios: `core/`, `admin/` y `templates/`. Cualquier plantilla personalizada o modificada se destruye.

**No hay hook points en las plantillas.** Los plugins solo pueden inyectar contenido via dos filtros globales (`build.head_html` y `build.body_end_html`). No pueden inyectar contenido en zonas especificas (antes del boton de comprar, despues de la descripcion, dentro de la galeria, etc.).

**Un cambio en un elemento compartido obliga a regenerar todas las paginas.** Si un plugin quiere anadir un icono de carrito en el header, hay que reconstruir las 2.000 paginas porque el header esta horneado dentro de cada HTML.

**No existe un sistema de plantillas por post type.** Todos los post types usan las mismas plantillas genericas. Un producto deberia tener su propia plantilla con zonas especificas (galeria, precio, boton de comprar), pero el sistema actual no lo contempla.

---

## 2. Nuevo sistema de directorios

### 2.1. Estructura de directorios

```
installer/
  templates/                      # CORE -- Se sobrescribe en updates
    default.html                  #   Plantilla generica de pagina
    landing.html                  #   Landing page
    blog-post.html                #   Entrada de blog
    blank.html                    #   Sin chrome (header/footer)
    parts/                        #   Fragmentos compartidos del core
      head.html                   #     Contenido de <head>
      header.html                 #     Cabecera del sitio
      footer.html                 #     Pie de pagina
      scripts.html                #     Scripts antes de </body>

  custom-templates/               # USUARIO -- NUNCA se sobrescribe
    (vacio en instalacion limpia)
    parts/                        #   Override de fragmentos
      (vacio)

  plugins/{plugin-id}/
    templates/                    # PLUGIN -- Vive dentro del plugin
      single-product.html
      archive-product.html
      cart.html
    parts/                        #   Fragmentos del plugin
      cart-icon.html
```

### 2.2. Reglas de propiedad

| Directorio | Propietario | Actualizacion | Se sobrescribe |
|---|---|---|---|
| `templates/` | Core de Klytos | Automatica via Updater | SI |
| `custom-templates/` | Usuario | Manual | NUNCA |
| `plugins/{id}/templates/` | Plugin | Con el plugin | Se elimina al desinstalar |
| `templates.json.enc` | Base de datos | Via admin/API | NUNCA por Updater |

### 2.3. Cambios necesarios en el Updater

En `updater.php`, anadir `custom-templates` a la lista de directorios protegidos:

```php
// updater.php -- Directorios que NUNCA se tocan
$protectedDirs = [
    'config',
    'data',
    'plugins',
    'custom-templates',    // NUEVO
    'public/assets',
    'backups',
];

// Directorios que SI se actualizan
$allowedDirs = ['core', 'admin', 'templates'];
```

En `install.php`, crear el directorio durante la instalacion:

```php
// install.php -- Crear custom-templates/ si no existe
$customTemplatesDir = $rootPath . '/custom-templates';
if (!is_dir($customTemplatesDir)) {
    mkdir($customTemplatesDir, 0755, true);
    mkdir($customTemplatesDir . '/parts', 0755, true);
    // Crear .gitkeep para que el directorio se mantenga en control de versiones
    file_put_contents($customTemplatesDir . '/.gitkeep', '');
    file_put_contents($customTemplatesDir . '/parts/.gitkeep', '');
}
```

---

## 3. Jerarquia de resolucion de templates

### 3.1. Orden de busqueda

Cuando el BuildEngine (o el Router para paginas dinamicas) necesita una plantilla, la busca en este orden. Toma la primera que encuentre:

1. `custom-templates/{nombre}.html` -- Personalizaciones del usuario
2. `plugins/{plugin-activo}/templates/{nombre}.html` -- Proporcionadas por plugins
3. `templates.json.enc` (entrada con clave `{nombre}`) -- Creadas desde el admin
4. `templates/{nombre}.html` -- Core de Klytos (fallback)

### 3.2. Implementacion: TemplateResolver

Se crea una nueva clase `core/template-resolver.php` que centraliza toda la logica de resolucion:

```php
<?php
declare(strict_types=1);

namespace Klytos\Core;

class TemplateResolver
{
    private App $app;

    /** @var array<string, array> Templates registrados por plugins: pluginId => [name => path] */
    private array $pluginTemplates = [];

    /** @var array<string, string> Cache en memoria de templates ya resueltos */
    private array $cache = [];

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Registrar templates de un plugin.
     * Se llama desde klytos_register_templates() en el init.php del plugin.
     *
     * @param string $pluginId  ID del plugin
     * @param array  $templates Array de [nombre => config]
     */
    public function registerPluginTemplates(string $pluginId, array $templates): void
    {
        foreach ($templates as $name => $config) {
            $this->pluginTemplates[$name] = [
                'plugin_id'   => $pluginId,
                'file'        => $config['file'] ?? '',
                'name'        => $config['name'] ?? $name,
                'description' => $config['description'] ?? '',
                'dynamic'     => $config['dynamic'] ?? false,
                'post_type'   => $config['post_type'] ?? null,
            ];
        }
    }

    /**
     * Resolver una plantilla por nombre.
     * Recorre la jerarquia de 4 niveles y devuelve el contenido HTML.
     *
     * @param  string $name Nombre de la plantilla (sin extension)
     * @return string Contenido HTML de la plantilla
     */
    public function resolve(string $name): string
    {
        // Cache hit
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $html = $this->doResolve($name);
        $this->cache[$name] = $html;
        return $html;
    }

    private function doResolve(string $name): string
    {
        $rootPath = $this->app->getRootPath();

        // 1. custom-templates/ del usuario
        $customFile = $rootPath . '/custom-templates/' . $name . '.html';
        if (file_exists($customFile)) {
            return file_get_contents($customFile);
        }

        // 2. Templates de plugins activos
        if (isset($this->pluginTemplates[$name])) {
            $config = $this->pluginTemplates[$name];
            if (!empty($config['file']) && file_exists($config['file'])) {
                return file_get_contents($config['file']);
            }
        }

        // 3. Templates en base de datos (templates.json.enc)
        try {
            $data = $this->app->getStorage()->read('templates.json.enc');
            if (isset($data['templates'][$name])) {
                return $data['templates'][$name]['html'];
            }
        } catch (\RuntimeException $e) {
            // No hay templates en BD
        }

        // 4. templates/ del core
        $coreFile = $this->app->getTemplatesPath() . '/' . $name . '.html';
        if (file_exists($coreFile)) {
            return file_get_contents($coreFile);
        }

        // Fallback absoluto
        return $this->getMinimalTemplate();
    }

    /**
     * Resolver un template part (fragmento compartido).
     * Misma jerarquia pero dentro de subdirectorios parts/.
     *
     * @param  string $partName Nombre del part (ej: 'header', 'footer')
     * @return string|null Contenido HTML, o null si no existe
     */
    public function resolvePart(string $partName): ?string
    {
        $rootPath = $this->app->getRootPath();

        // 1. custom-templates/parts/
        $customPart = $rootPath . '/custom-templates/parts/' . $partName . '.html';
        if (file_exists($customPart)) {
            return file_get_contents($customPart);
        }

        // 2. Parts de plugins (via filtro)
        $pluginPart = klytos_apply_filters('template_part.' . $partName, null);
        if ($pluginPart !== null) {
            return $pluginPart;
        }

        // 3. templates/parts/ del core
        $corePart = $this->app->getTemplatesPath() . '/parts/' . $partName . '.html';
        if (file_exists($corePart)) {
            return file_get_contents($corePart);
        }

        return null;
    }

    /**
     * Obtener lista de todas las plantillas disponibles.
     * Combina las de todos los niveles sin duplicados.
     *
     * @return array Lista de plantillas con origen y metadata
     */
    public function listAll(): array
    {
        $templates = [];
        $rootPath = $this->app->getRootPath();

        // Core templates
        $coreDir = $this->app->getTemplatesPath();
        foreach (glob($coreDir . '/*.html') as $file) {
            $name = basename($file, '.html');
            $templates[$name] = [
                'name'   => $name,
                'source' => 'core',
                'file'   => $file,
            ];
        }

        // DB templates
        try {
            $data = $this->app->getStorage()->read('templates.json.enc');
            foreach (($data['templates'] ?? []) as $name => $tpl) {
                $templates[$name] = [
                    'name'   => $name,
                    'source' => 'database',
                    'file'   => null,
                ];
            }
        } catch (\RuntimeException $e) {}

        // Plugin templates
        foreach ($this->pluginTemplates as $name => $config) {
            $templates[$name] = [
                'name'      => $config['name'],
                'source'    => 'plugin',
                'plugin_id' => $config['plugin_id'],
                'file'      => $config['file'],
                'dynamic'   => $config['dynamic'],
            ];
        }

        // Custom templates (highest priority, overwrite any of the above)
        $customDir = $rootPath . '/custom-templates';
        if (is_dir($customDir)) {
            foreach (glob($customDir . '/*.html') as $file) {
                $name = basename($file, '.html');
                $templates[$name] = [
                    'name'   => $name,
                    'source' => 'custom',
                    'file'   => $file,
                ];
            }
        }

        return $templates;
    }

    /**
     * Limpiar la cache en memoria.
     * Llamar despues de que un plugin se active/desactive.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    private function getMinimalTemplate(): string
    {
        return '<!DOCTYPE html><html lang="{{page_lang}}">'
             . '<head><meta charset="UTF-8"><title>{{page_title}}</title></head>'
             . '<body>{{page_content}}</body></html>';
    }
}
```

### 3.3. Cambios en BuildEngine::loadTemplate()

Reemplazar el metodo actual por una delegacion al TemplateResolver:

```php
// build-engine.php

private function loadTemplate(string $name): string
{
    return $this->app->getTemplateResolver()->resolve($name);
}
```

### 3.4. Integracion en App::boot()

```php
// app.php -- en el metodo boot(), despues de inicializar los managers

$this->templateResolver = new TemplateResolver($this);

// Getter
public function getTemplateResolver(): TemplateResolver
{
    return $this->templateResolver;
}
```

---

## 4. Template Parts (fragmentos compartidos)

### 4.1. El problema que resuelven

Un header que aparece en 2.000 paginas debe poder ser modificado por un plugin sin regenerar las 2.000 paginas. Los template parts son la solucion.

### 4.2. Sintaxis en las plantillas

Las plantillas HTML usan una nueva etiqueta `{{klytos_part:NOMBRE}}` para incluir fragmentos:

```html
<!-- templates/default.html -->
<!DOCTYPE html>
<html lang="{{page_lang}}">
<head>
    <meta charset="UTF-8">
    <title>{{page_title}}{{title_separator}}{{site_name}}</title>
    {{klytos_part:head}}
</head>
<body>
    {{klytos_part:header}}

    <main class="klytos-main">
        {{klytos_part:before_content}}
        {{page_content}}
        {{klytos_part:after_content}}
    </main>

    {{klytos_part:footer}}
    {{klytos_part:scripts}}
</body>
</html>
```

### 4.3. Parts predefinidos del core

El core incluye estos parts en `templates/parts/`:

| Part | Archivo | Contenido |
|---|---|---|
| `head` | `templates/parts/head.html` | Meta tags, CSS, fuentes, favicon |
| `header` | `templates/parts/header.html` | Logo, menu de navegacion |
| `footer` | `templates/parts/footer.html` | Footer con copyright |
| `scripts` | `templates/parts/scripts.html` | JS del core + analytics |
| `before_content` | (vacio por defecto) | Hook para plugins |
| `after_content` | (vacio por defecto) | Hook para plugins |

### 4.4. Como un plugin inyecta contenido en un part

```php
// init.php del plugin de e-commerce
// Anadir icono de carrito al header
klytos_add_filter('template_part.header', function(?string $html): string {
    // $html contiene el header actual (del core o de custom-templates)
    // Lo modificamos para anadir el icono del carrito al final
    $cartIcon = '<div class="cart-icon-wrapper">'
              . '<a href="/carrito" class="cart-icon" id="klytos-cart-icon">'
              . '<span class="cart-count" data-klytos-hook="cart_count">0</span>'
              . '</a></div>';

    // Inyectar antes del cierre de </header>
    if ($html !== null) {
        return str_replace('</header>', $cartIcon . '</header>', $html);
    }
    return $cartIcon;
}, 10);
```

### 4.5. Procesamiento de template parts en BuildEngine

Se anade un paso de procesamiento despues de cargar la plantilla y antes de reemplazar las variables:

```php
// build-engine.php -- nuevo metodo

private function processTemplateParts(string $templateHtml): string
{
    $resolver = $this->app->getTemplateResolver();

    // Buscar todas las etiquetas {{klytos_part:NOMBRE}}
    return preg_replace_callback(
        '/\{\{klytos_part:([a-zA-Z0-9_\-]+)\}\}/',
        function (array $matches) use ($resolver) {
            $partName = $matches[1];

            // Resolver el part (jerarquia: custom > plugin > core)
            $partHtml = $resolver->resolvePart($partName);

            // Si no existe, devolver cadena vacia (el hook point es invisible)
            return $partHtml ?? '';
        },
        $templateHtml
    );
}
```

Y modificar `renderTemplate()` para llamarlo:

```php
private function renderTemplate(array $page, array $siteConfig, string $menuHtml, array $theme): string
{
    $templateName = $page['template'] ?? 'default';
    $templateHtml = $this->loadTemplate($templateName);

    // NUEVO: procesar template parts ANTES de reemplazar variables
    $templateHtml = $this->processTemplateParts($templateHtml);

    // Resto del codigo existente (reemplazo de {{variables}})
    // ...
}
```

---

## 5. Hook Points en paginas estaticas

### 5.1. El problema de las 2.000 paginas

Tenemos 2.000 paginas de producto generadas como HTML estatico. Un plugin nuevo quiere anadir botones de "compartir en redes sociales" debajo del boton "Anadir al carrito" en TODAS esas paginas.

Regenerar las 2.000 paginas cada vez que un plugin se activa es inviable.

### 5.2. La solucion: contenido estatico + hook points + JS compartido

La pagina de producto estatica contiene dos cosas:

1. **El contenido del producto** (titulo, descripcion, precio, imagenes) horneado en el HTML para SEO
2. **Hook points** (divs vacios con un atributo `data-klytos-hook`) donde los plugins inyectan contenido via JavaScript

El JavaScript que rellena los hook points es **un unico archivo compartido** por todas las paginas. Cuando se activa o desactiva un plugin, solo se regenera ESE archivo JS, no las 2.000 paginas.

### 5.3. Anatomia de una pagina de producto estatica

```html
<!-- /productos/camiseta-azul/index.html -->
<!-- Generado por BuildEngine. El contenido del producto esta en el HTML (SEO). -->
<!-- Los hook points son divs vacios que el JS compartido rellena. -->

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Camiseta Azul -- Mi Tienda</title>
    <meta name="description" content="Camiseta de algodon organico...">
    <link rel="stylesheet" href="/assets/css/style.css">
    <!-- CSS de plugins (generado una vez, compartido) -->
    <link rel="stylesheet" href="/assets/css/plugins.css?v=1.2.0">

    <!-- Datos estructurados para SEO (siempre en el HTML) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Product",
        "name": "Camiseta Azul",
        "description": "Camiseta de algodon organico",
        "offers": { "@type": "Offer", "price": "29.99", "priceCurrency": "EUR" }
    }
    </script>
</head>
<body>
    <header><!-- contenido del header --></header>

    <main class="product-page">
        <!-- Datos del producto como JSON (para que el JS tenga acceso) -->
        <script type="application/json" id="klytos-page-data">
        {
            "type": "product",
            "id": "camiseta-azul",
            "title": "Camiseta Azul",
            "price": 29.99,
            "sale_price": null,
            "sku": "CAM-001",
            "stock": 45,
            "url": "/productos/camiseta-azul/"
        }
        </script>

        <div class="product-gallery">
            <img src="/assets/products/cam-azul-1.jpg" alt="Camiseta Azul">
        </div>

        <div class="product-info">
            <h1>Camiseta Azul</h1>

            <!-- HOOK POINT: antes del precio -->
            <div data-klytos-hook="before_price"></div>

            <p class="price">29,99 EUR</p>

            <!-- HOOK POINT: despues del precio, antes del boton -->
            <div data-klytos-hook="before_add_to_cart"></div>

            <button class="add-to-cart" data-product-id="camiseta-azul">
                Anadir al carrito
            </button>

            <!-- HOOK POINT: despues del boton de comprar -->
            <div data-klytos-hook="after_add_to_cart"></div>

            <div class="product-description">
                <p>Camiseta de algodon organico, 100% sostenible...</p>
            </div>

            <!-- HOOK POINT: despues de la descripcion -->
            <div data-klytos-hook="after_product_description"></div>
        </div>

        <!-- HOOK POINT: despues del producto (resenas, relacionados, etc.) -->
        <div data-klytos-hook="after_product"></div>
    </main>

    <footer><!-- contenido del footer --></footer>

    <!-- JS compartido que rellena los hook points (UN archivo para todo el sitio) -->
    <script src="/assets/js/klytos-hooks.js?v=1.2.0"></script>
</body>
</html>
```

### 5.4. El archivo JS compartido: klytos-hooks.js

Este archivo se genera UNA sola vez durante el build (o cuando un plugin se activa/desactiva). Contiene los registros de todos los plugins activos:

```javascript
/**
 * klytos-hooks.js
 * Generado automaticamente por Klytos BuildEngine.
 * Ultima generacion: 2026-03-31T12:00:00Z
 *
 * Este archivo contiene las inyecciones de todos los plugins activos
 * para los hook points del frontend. Se regenera SOLO cuando cambia
 * la configuracion de plugins, NO cuando se edita contenido.
 */
(function() {
    'use strict';

    // ─── Registry de hooks ────────────────────────────────────
    var hooks = {};

    function registerHook(name, callback, priority) {
        if (!hooks[name]) hooks[name] = [];
        hooks[name].push({ callback: callback, priority: priority || 10 });
        hooks[name].sort(function(a, b) { return a.priority - b.priority; });
    }

    // ─── Obtener datos de la pagina (si existen) ──────────────
    var pageDataEl = document.getElementById('klytos-page-data');
    var pageData = pageDataEl ? JSON.parse(pageDataEl.textContent) : {};

    // ─── Utilidades para plugins ──────────────────────────────
    function esc(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ═══════════════════════════════════════════════════════════
    // REGISTROS DE PLUGINS (generados automaticamente)
    // ═══════════════════════════════════════════════════════════

    // --- Plugin: klytos-ecommerce (v1.2.0) ---
    // Hook: cart_count -- muestra el contador del carrito via AJAX
    registerHook('cart_count', function(el, data) {
        fetch('/api/cart/count')
            .then(function(r) { return r.json(); })
            .then(function(d) {
                el.innerHTML = d.count > 0 ? d.count : '';
            })
            .catch(function() {});
    }, 10);

    // --- Plugin: social-share (v1.0.0) ---
    // Hook: after_add_to_cart -- botones de compartir en redes
    registerHook('after_add_to_cart', function(el, data) {
        if (data.type !== 'product') return; // Solo en paginas de producto
        var url = encodeURIComponent(window.location.href);
        var title = encodeURIComponent(data.title || document.title);
        el.innerHTML = '<div class="social-share">'
            + '<span class="social-share__label">Compartir:</span>'
            + '<a href="https://twitter.com/intent/tweet?url=' + url + '&text=' + title + '" '
            + '   target="_blank" rel="noopener" class="social-share__btn social-share__btn--twitter">'
            + '   Twitter</a>'
            + '<a href="https://www.facebook.com/sharer/sharer.php?u=' + url + '" '
            + '   target="_blank" rel="noopener" class="social-share__btn social-share__btn--facebook">'
            + '   Facebook</a>'
            + '<a href="https://api.whatsapp.com/send?text=' + title + '%20' + url + '" '
            + '   target="_blank" rel="noopener" class="social-share__btn social-share__btn--whatsapp">'
            + '   WhatsApp</a>'
            + '</div>';
    }, 10);

    // --- Plugin: product-reviews (v2.1.0) ---
    // Hook: after_product -- seccion de resenas
    registerHook('after_product', function(el, data) {
        if (data.type !== 'product') return;
        el.innerHTML = '<div id="product-reviews" class="reviews-section">'
            + '<h3>Opiniones de clientes</h3>'
            + '<div class="reviews-loading">Cargando resenas...</div>'
            + '</div>';
        // Cargar resenas via AJAX
        fetch('/api/reviews?product=' + encodeURIComponent(data.id))
            .then(function(r) { return r.json(); })
            .then(function(reviews) {
                var container = document.getElementById('product-reviews');
                if (!container) return;
                var html = '<h3>Opiniones de clientes (' + reviews.length + ')</h3>';
                reviews.forEach(function(review) {
                    html += '<div class="review">'
                        + '<strong>' + esc(review.author) + '</strong>'
                        + '<p>' + esc(review.text) + '</p>'
                        + '</div>';
                });
                container.innerHTML = html;
            });
    }, 10);

    // ═══════════════════════════════════════════════════════════
    // EJECUTOR (siempre al final, nunca cambia)
    // ═══════════════════════════════════════════════════════════

    document.addEventListener('DOMContentLoaded', function() {
        var hookPoints = document.querySelectorAll('[data-klytos-hook]');
        hookPoints.forEach(function(el) {
            var name = el.getAttribute('data-klytos-hook');
            if (hooks[name]) {
                hooks[name].forEach(function(h) {
                    try {
                        h.callback(el, pageData);
                    } catch(e) {
                        console.error('Klytos hook error [' + name + ']:', e);
                    }
                });
            }
        });
    });

    // Exponer API global para scripts de plugins que se carguen despues
    window.KlytosHooks = {
        register: registerHook,
        getData: function() { return pageData; }
    };
})();
```

### 5.5. Como genera el BuildEngine este archivo

Se crea un nuevo metodo en el BuildEngine (o en una nueva clase `AssetCompiler`):

```php
/**
 * Generar /assets/js/klytos-hooks.js
 * Concatena los registros JS de todos los plugins activos.
 *
 * Cada plugin proporciona un archivo hooks.js en su directorio assets/js/
 * que contiene llamadas a registerHook().
 *
 * Se llama durante buildAll() y tambien cuando se activa/desactiva un plugin.
 */
public function buildHooksJs(): void
{
    $pluginLoader = $this->app->getPluginLoader();
    $activePlugins = $pluginLoader->getActivePlugins();
    $pluginsDir = $this->app->getRootPath() . '/plugins';

    // Parte 1: preambulo (registry, utilidades, pageData)
    $js = file_get_contents($this->app->getCorePath() . '/assets/klytos-hooks-prelude.js');

    // Parte 2: registros de cada plugin activo
    foreach ($activePlugins as $pluginId => $manifest) {
        $hooksFile = $pluginsDir . '/' . $pluginId . '/assets/js/hooks.js';
        if (file_exists($hooksFile)) {
            $js .= "\n// --- Plugin: {$pluginId} (v{$manifest['version']}) ---\n";
            $js .= file_get_contents($hooksFile);
        }
    }

    // Parte 3: ejecutor (DOMContentLoaded)
    $js .= file_get_contents($this->app->getCorePath() . '/assets/klytos-hooks-executor.js');

    // Escribir con hash de version para cache-busting
    $version = md5($js);
    $outputDir = $this->outputPath . '/assets/js';
    Helpers::ensureWritableDir($outputDir);
    file_put_contents($outputDir . '/klytos-hooks.js', $js, LOCK_EX);

    // Guardar la version para que las plantillas usen ?v=HASH
    klytos_set_option('klytos_hooks_js_version', substr($version, 0, 8));
}
```

### 5.6. Cuando se regenera klytos-hooks.js

| Evento | Se regenera | Se regeneran paginas |
|---|---|---|
| Se activa un plugin | SI | NO |
| Se desactiva un plugin | SI | NO |
| Un plugin actualiza su hooks.js | SI | NO |
| Se edita una pagina individual | NO | Solo esa pagina |
| Se ejecuta buildAll() | SI | SI (pero es raro) |
| Se cambia la plantilla base | NO | SI, todas las que la usen |

El punto clave: **activar un plugin que inyecta contenido en 2.000 paginas solo regenera un archivo JS**.

### 5.7. Hook points predefinidos

Las plantillas del core deben incluir hook points generosos desde el inicio. Si un plugin necesita un hook point que no existe, si necesitaria un rebuild de las paginas que usen esa plantilla, pero eso deberia ser raro.

**Hook points en la plantilla base (default.html):**

| Hook point | Ubicacion |
|---|---|
| `before_header` | Antes del `<header>` |
| `after_header` | Despues del `<header>` |
| `before_content` | Antes de `<main>` |
| `after_content` | Despues de `</main>` |
| `before_footer` | Antes del `<footer>` |
| `after_footer` | Despues del `</footer>` |

**Hook points en la plantilla de producto (single-product.html):**

| Hook point | Ubicacion |
|---|---|
| `before_product` | Antes de toda la ficha |
| `before_product_gallery` | Antes de la galeria de imagenes |
| `after_product_gallery` | Despues de la galeria |
| `before_price` | Antes del precio |
| `after_price` | Despues del precio |
| `before_add_to_cart` | Antes del boton de comprar |
| `after_add_to_cart` | Despues del boton de comprar |
| `before_product_description` | Antes de la descripcion |
| `after_product_description` | Despues de la descripcion |
| `after_product` | Despues de toda la ficha (resenas, relacionados) |
| `product_sidebar` | Barra lateral del producto |

**Hook points en la plantilla de blog (blog-post.html):**

| Hook point | Ubicacion |
|---|---|
| `before_post` | Antes del articulo |
| `after_post_title` | Despues del titulo |
| `before_post_content` | Antes del contenido |
| `after_post_content` | Despues del contenido |
| `after_post` | Despues del articulo (comentarios, relacionados) |

### 5.8. CSS de plugins: misma estrategia

El CSS funciona igual que el JS. Se genera un archivo `plugins.css` que concatena los estilos de todos los plugins activos:

```php
public function buildPluginsCss(): void
{
    $css = "/* Generado automaticamente por Klytos. No editar. */\n\n";

    foreach ($activePlugins as $pluginId => $manifest) {
        $cssDir = $pluginsDir . '/' . $pluginId . '/assets/css';
        if (is_dir($cssDir)) {
            foreach (glob($cssDir . '/*.css') as $file) {
                $css .= "/* --- {$pluginId}: " . basename($file) . " --- */\n";
                $css .= file_get_contents($file) . "\n\n";
            }
        }
    }

    $outputDir = $this->outputPath . '/assets/css';
    Helpers::ensureWritableDir($outputDir);
    file_put_contents($outputDir . '/plugins.css', $css, LOCK_EX);

    $version = md5($css);
    klytos_set_option('klytos_plugins_css_version', substr($version, 0, 8));
}
```

---

## 6. Template parts + Hook points: como trabajan juntos

Para que quede claro como interactuan los dos sistemas:

**Template parts** resuelven el problema del header/footer/sidebar: contenido compartido que se procesa una vez durante el build y se hornea en el HTML. Si un plugin quiere anadir un icono al header, lo hace via el filtro del template part, y se regeneran las paginas (pero es una operacion rara, solo ocurre al activar/desactiva plugins).

**Hook points** resuelven el problema de los 2.000 productos: contenido de plugins que se inyecta via JavaScript en el navegador. Si un plugin quiere anadir botones de compartir en todas las paginas de producto, lo hace via klytos-hooks.js, y solo se regenera ese archivo.

Regla practica:

- Si el contenido es **critico para SEO** (navegacion, footer con links): usa un **template part**.
- Si el contenido es **funcional/interactivo** (botones de compartir, resenas, carrito): usa un **hook point JS**.
- Si el contenido **depende de la sesion del usuario** (contador del carrito, nombre del usuario): obligatoriamente **hook point JS** con AJAX.

---

## 7. Plantillas por post type

### 7.1. Convencion de nombres

Cuando el BuildEngine genera una pagina, busca la plantilla en este orden por nombre:

1. `single-{post_type}-{slug}` -- Plantilla especifica para ESE contenido (ej: `single-product-camiseta-azul`)
2. `single-{post_type}` -- Plantilla para ese tipo (ej: `single-product`)
3. Valor del campo `template` de la pagina -- Lo que el usuario eligio en el editor
4. `default` -- Fallback

Para listados/archivos:

1. `archive-{post_type}-{taxonomy}-{term}` -- Listado de un termino concreto
2. `archive-{post_type}-{taxonomy}` -- Listado de una taxonomia
3. `archive-{post_type}` -- Listado del post type
4. `archive` -- Listado generico
5. `default` -- Fallback

### 7.2. Implementacion en BuildEngine

```php
private function resolveTemplateForPage(array $page): string
{
    $postType = $page['post_type'] ?? 'page';
    $slug     = $page['slug'] ?? '';
    $resolver = $this->app->getTemplateResolver();

    // Orden de resolucion especifico
    $candidates = [];

    if ($postType !== 'page') {
        // Plantilla especifica para este contenido
        $candidates[] = "single-{$postType}-{$slug}";
        // Plantilla del post type
        $candidates[] = "single-{$postType}";
    }

    // Plantilla elegida por el usuario en el editor
    $chosen = $page['template'] ?? 'default';
    if ($chosen !== 'default') {
        $candidates[] = $chosen;
    }

    $candidates[] = 'default';

    foreach ($candidates as $name) {
        try {
            $html = $resolver->resolve($name);
            if (!empty($html)) {
                return $html;
            }
        } catch (\RuntimeException $e) {
            continue;
        }
    }

    return $resolver->resolve('default');
}
```

---

## 8. Helpers globales nuevos

Anadir a `helpers-global.php`:

```php
// ─── Template API ───────────────────────────────────────────

/**
 * Registrar plantillas proporcionadas por un plugin.
 *
 * @param string $pluginId  ID del plugin
 * @param array  $templates Array de templates: nombre => [name, description, file, dynamic, post_type]
 */
function klytos_register_templates(string $pluginId, array $templates): void
{
    App::getInstance()->getTemplateResolver()->registerPluginTemplates($pluginId, $templates);
}

/**
 * Registrar o modificar un template part.
 *
 * @param string   $partName Nombre del part (ej: 'header')
 * @param callable $callback Funcion que recibe el HTML actual y devuelve el modificado
 * @param int      $priority Prioridad de ejecucion (menor = antes)
 */
function klytos_register_template_part(string $partName, callable $callback, int $priority = 10): void
{
    klytos_add_filter('template_part.' . $partName, $callback, $priority);
}

/**
 * Encolar un archivo CSS para el frontend.
 * Se concatenara en /assets/css/plugins.css durante el build.
 *
 * @param string $handle  Identificador unico
 * @param string $src     URL o path al archivo CSS
 * @param array  $deps    Handles de dependencias
 * @param string $version Version para cache-busting
 */
function klytos_enqueue_style(string $handle, string $src, array $deps = [], string $version = ''): void
{
    App::getInstance()->getAssetEnqueue()->enqueueStyle($handle, $src, $deps, $version);
}

/**
 * Encolar un archivo JavaScript para el frontend.
 *
 * @param string $handle   Identificador unico
 * @param string $src      URL o path al archivo JS
 * @param array  $deps     Handles de dependencias
 * @param string $version  Version para cache-busting
 * @param bool   $inFooter Cargar antes de </body> (true) o en <head> (false)
 */
function klytos_enqueue_script(string $handle, string $src, array $deps = [], string $version = '', bool $inFooter = true): void
{
    App::getInstance()->getAssetEnqueue()->enqueueScript($handle, $src, $deps, $version, $inFooter);
}
```

---

## 9. Flujo de build completo (actualizado)

```
BuildEngine::buildAll()
│
├── 1. klytos_do_action('build.before')
│
├── 2. buildHooksJs()         ← NUEVO: genera /assets/js/klytos-hooks.js
├── 3. buildPluginsCss()      ← NUEVO: genera /assets/css/plugins.css
├── 4. generateCss()          (existente: CSS del tema)
│
├── 5. Para cada pagina publicada:
│      ├── resolveTemplateForPage()     ← NUEVO: jerarquia por post type
│      ├── loadTemplate()               ← MODIFICADO: usa TemplateResolver
│      ├── processTemplateParts()       ← NUEVO: resuelve {{klytos_part:X}}
│      ├── Reemplazar {{variables}}     (existente)
│      ├── Aplicar filtros de contenido (existente)
│      └── Escribir HTML en disco       (existente)
│
├── 6. generateRobotsTxt()     (existente)
├── 7. generateSitemap()       (existente)
├── 8. generateLlmsTxt()       (existente)
├── 9. updateBuildTimestamp()   (existente)
│
└── 10. klytos_do_action('build.after')
```

---

## 10. Resumen de archivos a crear y modificar

### Archivos nuevos

| Archivo | Proposito |
|---|---|
| `core/template-resolver.php` | Resolucion de templates con jerarquia de 4 niveles |
| `core/asset-enqueue.php` | Gestor de encolado de CSS/JS para frontend |
| `core/assets/klytos-hooks-prelude.js` | Preambulo del JS de hooks (registry, utilidades) |
| `core/assets/klytos-hooks-executor.js` | Ejecutor del JS de hooks (DOMContentLoaded) |
| `templates/parts/head.html` | Template part: contenido del `<head>` |
| `templates/parts/header.html` | Template part: cabecera del sitio |
| `templates/parts/footer.html` | Template part: pie de pagina |
| `templates/parts/scripts.html` | Template part: scripts antes de `</body>` |
| `custom-templates/.gitkeep` | Directorio protegido (creado en instalacion) |
| `custom-templates/parts/.gitkeep` | Subdirectorio de parts personalizados |

### Archivos a modificar

| Archivo | Cambios |
|---|---|
| `core/build-engine.php` | Usar TemplateResolver, procesar template parts, generar klytos-hooks.js y plugins.css, resolveTemplateForPage() |
| `core/app.php` | Inicializar TemplateResolver y AssetEnqueue, anadir getters |
| `core/helpers-global.php` | Nuevos helpers: klytos_register_templates(), klytos_register_template_part(), klytos_enqueue_style(), klytos_enqueue_script() |
| `core/updater.php` | Anadir custom-templates a directorios protegidos |
| `core/plugin-loader.php` | Llamar a buildHooksJs() y buildPluginsCss() al activar/desactivar un plugin |
| `install.php` | Crear custom-templates/ y templates/parts/ durante instalacion |
| `templates/default.html` | Refactorizar para usar {{klytos_part:X}} y hook points |
| `templates/landing.html` | Idem |
| `templates/blog-post.html` | Idem, con hook points especificos de blog |
| `templates/blank.html` | Minimo: solo hook points basicos |
