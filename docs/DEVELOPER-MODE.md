# Klytos Developer Mode — Especificacion Tecnica

## Resumen

El Developer Mode es una funcionalidad integrada en el panel de administracion de Klytos que, al activarse desde Settings, despliega una barra de depuracion persistente en la parte inferior del admin. Esta barra muestra metricas de rendimiento en tiempo real y se puede expandir para mostrar informacion detallada del request actual, similar a lo que ofrece Query Monitor en WordPress.

Solo los usuarios con rol `owner` o `admin` pueden activar y ver el Developer Mode.

---

## 1. Activacion desde Settings

### 1.1 Nueva seccion en Settings

Anadir una nueva pestana **"Developer"** en `admin/settings.php`, junto a las existentes (general, social, analytics, email, languages).

```
Tab: Developer
Seccion POST: section=developer
```

### 1.2 Opciones de la pestana Developer

| Opcion | Tipo | Default | Descripcion |
|---|---|---|---|
| `developer_mode` | toggle (bool) | `false` | Activa/desactiva el Developer Mode globalmente |
| `devbar_show_performance` | toggle (bool) | `true` | Mostrar metricas de CPU/memoria en la barra compacta |
| `devbar_show_queries` | toggle (bool) | `true` | Mostrar panel de queries (si usa DatabaseStorage) |
| `devbar_show_hooks` | toggle (bool) | `true` | Mostrar panel de hooks |
| `devbar_show_assets` | toggle (bool) | `true` | Mostrar panel de assets cargados |
| `devbar_show_request` | toggle (bool) | `true` | Mostrar panel de informacion del request |
| `devbar_show_environment` | toggle (bool) | `true` | Mostrar panel de entorno del servidor |
| `devbar_log_slow_threshold` | number (ms) | `200` | Umbral para marcar operaciones como lentas (resaltado en rojo) |

### 1.3 Almacenamiento

Guardar en SiteConfig bajo la clave `developer`:

```php
$app->getSiteConfig()->set([
    'developer' => [
        'developer_mode'          => (bool) ($_POST['developer_mode'] ?? false),
        'devbar_show_performance'  => (bool) ($_POST['devbar_show_performance'] ?? true),
        'devbar_show_queries'      => (bool) ($_POST['devbar_show_queries'] ?? true),
        'devbar_show_hooks'        => (bool) ($_POST['devbar_show_hooks'] ?? true),
        'devbar_show_assets'       => (bool) ($_POST['devbar_show_assets'] ?? true),
        'devbar_show_request'      => (bool) ($_POST['devbar_show_request'] ?? true),
        'devbar_show_environment'  => (bool) ($_POST['devbar_show_environment'] ?? true),
        'devbar_log_slow_threshold' => (int) ($_POST['devbar_log_slow_threshold'] ?? 200),
    ],
]);
```

### 1.4 Restriccion de acceso

Solo roles `owner` y `admin` pueden ver y modificar la pestana Developer en settings. Si el usuario no tiene permiso, la pestana no se renderiza.

---

## 2. Nucleo: clase DevBar (Collector)

### 2.1 Archivo: `core/dev-bar.php`

Clase que recopila datos durante todo el ciclo de vida del request. Se instancia al principio del request si el Developer Mode esta activo.

```php
<?php
declare(strict_types=1);

namespace Klytos\Core;

class DevBar
{
    private static ?self $instance = null;
    private float $requestStart;
    private array $queries       = [];
    private array $hooksFired    = [];
    private array $timers        = [];
    private array $assets        = [];
    private array $logs          = [];
    private array $storageOps    = [];
    private int   $peakMemory    = 0;
    private array $includedFiles = [];
    private array $cacheHits     = [];
    private array $cacheMisses   = [];
    private array $deprecations  = [];

    private function __construct()
    {
        $this->requestStart = $_SERVER['REQUEST_FLOAT_TIME'] ?? microtime(true);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // --- Metodos de registro ---

    public function logQuery(string $type, string $detail, float $duration, ?string $caller = null): void;
    public function logHook(string $name, string $type, int $callbackCount, float $duration): void;
    public function logTimer(string $label, float $start, float $end): void;
    public function logAsset(string $type, string $path, int $sizeBytes, float $loadTime): void;
    public function logStorageOp(string $operation, string $collection, float $duration): void;
    public function logCacheHit(string $key): void;
    public function logCacheMiss(string $key): void;
    public function logDeprecation(string $message, string $caller): void;
    public function log(string $level, string $message, array $context = []): void;

    // --- Getters para renderizar ---

    public function getExecutionTime(): float;      // ms desde inicio del request
    public function getMemoryUsage(): int;           // bytes actuales
    public function getPeakMemory(): int;            // bytes pico
    public function getMemoryUsageFormatted(): string; // "4.2 MB"
    public function getQueryCount(): int;
    public function getQueryTime(): float;           // ms total en queries
    public function getQueries(): array;
    public function getHooksFired(): array;
    public function getHookCount(): int;
    public function getTimers(): array;
    public function getAssets(): array;
    public function getStorageOps(): array;
    public function getLogs(): array;
    public function getIncludedFiles(): array;       // get_included_files()
    public function getCacheStats(): array;          // ['hits' => n, 'misses' => n]
    public function getDeprecations(): array;

    public function toArray(): array;                // Todo en un array para JSON
}
```

### 2.2 Inicializacion

En `core/app.php`, durante el boot, si Developer Mode esta activo:

```php
// Al inicio del boot de App, despues de cargar config
$devConfig = $this->getSiteConfig()->get('developer');
if (!empty($devConfig['developer_mode'])) {
    $this->devBar = DevBar::getInstance();

    // Instrumentar Hooks para capturar metricas
    klytos_set_profiler(function (string $hookName, string $type, int $count, float $duration) {
        DevBar::getInstance()->logHook($hookName, $type, $count, $duration);
    });

    // Instrumentar Storage para capturar operaciones
    // (ver seccion 2.3)
}
```

### 2.3 Instrumentacion del Storage

Crear un wrapper `ProfilingStorage` que implemente `StorageInterface` y delegue al storage real, midiendo tiempos:

```php
<?php
declare(strict_types=1);

namespace Klytos\Core;

class ProfilingStorage implements StorageInterface
{
    public function __construct(
        private StorageInterface $inner,
        private DevBar $devBar
    ) {}

    public function read(string $collection, string $id): ?array
    {
        $start = microtime(true);
        $result = $this->inner->read($collection, $id);
        $this->devBar->logStorageOp('read', $collection, microtime(true) - $start);
        return $result;
    }

    // ... lo mismo para write, delete, exists, list, count, search
}
```

En `App::boot()`, si devMode esta activo, envolver el storage:

```php
$this->storage = new ProfilingStorage($this->storage, DevBar::getInstance());
```

### 2.4 Instrumentacion de Hooks

Modificar `klytos_do_action()` y `klytos_apply_filters()` para que, si hay un profiler registrado, midan el tiempo de ejecucion de cada hook y lo reporten.

Anadir a `Hooks`:

```php
private static ?\Closure $profiler = null;

public static function setProfiler(\Closure $fn): void
{
    self::$profiler = $fn;
}
```

Y en `doAction` / `applyFilters`, envolver la ejecucion de callbacks con medicion de tiempo si `$profiler !== null`.

---

## 3. Barra compacta (estado colapsado)

### 3.1 Aspecto visual

La barra se muestra como una franja fija en la parte inferior del admin (`position: fixed; bottom: 0; left: 0; right: 0;`). Altura: ~36px. Fondo oscuro semitransparente (`#1a1a2e` con `rgba`). Texto monoespacio pequeno.

```
+-------------------------------------------------------------------+
| PHP 8.3.x | 127ms | Mem 4.2MB | 12 queries (3.1ms) | 47 hooks | /admin/pages.php | [^]  |
+-------------------------------------------------------------------+
```

### 3.2 Contenido de la barra compacta

De izquierda a derecha:

1. **Version de PHP**: `PHP X.X.X`
2. **Tiempo total del request**: en ms, con color segun umbral:
   - Verde: < 100ms
   - Amarillo: 100-200ms (o el umbral configurado)
   - Rojo: > umbral configurado
3. **Memoria**: uso actual formateado (ej: "4.2 MB")
4. **Queries/Storage ops**: cantidad y tiempo total (ej: "12 queries (3.1ms)")
   - Solo si usa DatabaseStorage; si usa FileStorage, mostrar "12 file ops (3.1ms)"
5. **Hooks disparados**: cantidad total
6. **Pagina actual**: la ruta de la pagina admin que se esta viendo (ej: `/admin/pages.php`, `/admin/settings.php`)
   - Esto se extrae de `$_SERVER['SCRIPT_NAME']` o similar
7. **Boton expandir**: una flecha hacia arriba `[^]` que al hacer clic abre el panel detallado

### 3.3 Indicadores de alerta

Si alguna metrica supera umbrales, su seccion en la barra cambia a color rojo:

- Tiempo de request > umbral configurado
- Memoria > 64MB
- Alguna query individual > 50ms
- Alguna deprecation detectada (mostrar icono de warning)

---

## 4. Panel expandido (estado desplegado)

### 4.1 Estructura general

Al pulsar la flecha `[^]`, la barra se expande hacia arriba ocupando entre el 40% y el 60% de la pantalla (configurable con drag del borde superior). El panel tiene pestanas laterales o superiores.

### 4.2 Pestanas del panel

#### 4.2.1 Performance

Informacion general de rendimiento de la pagina:

- **Tiempo total del request** (ms)
- **Time to first byte (TTFB)** si se puede calcular
- **Memoria actual / pico** (formateado en MB)
- **CPU time** (si disponible via `getrusage()`)
  - `ru_utime` (user time)
  - `ru_stime` (system time)
- **Archivos PHP cargados**: cantidad y lista desplegable con `get_included_files()`
- **Timeline visual**: barra horizontal mostrando las fases del request:
  - Bootstrap / Init
  - Routing
  - Auth
  - Controller / Page Logic
  - Render
  - Hooks overhead
  - Storage/DB overhead

#### 4.2.2 Queries / Storage

Lista detallada de todas las operaciones de storage (queries SQL si DatabaseStorage, operaciones de archivo si FileStorage):

| # | Tipo | Coleccion/Tabla | Detalle | Duracion | Caller |
|---|------|-----------------|---------|----------|--------|
| 1 | read | pages | id=home | 0.8ms | PageManager::get() |
| 2 | search | pages | status=published | 2.1ms | PageManager::list() |
| 3 | write | config | site_config | 1.2ms | SiteConfig::set() |

Funcionalidades:

- Ordenar por duracion (mas lento primero)
- Filtrar por coleccion/tabla
- Resaltar en rojo las operaciones que superen el umbral
- Mostrar el total de tiempo en storage
- Si es DatabaseStorage: mostrar la query SQL real
- Mostrar el stack trace simplificado del caller (archivo:linea)

#### 4.2.3 Hooks

Lista de todos los hooks que se han disparado durante el request:

| # | Hook | Tipo | Callbacks | Duracion | Resultado |
|---|------|------|-----------|----------|-----------|
| 1 | klytos.init | action | 3 | 0.2ms | -- |
| 2 | admin.page.before_render | action | 1 | 0.1ms | -- |
| 3 | page.content | filter | 2 | 1.5ms | modified |

Funcionalidades:

- Mostrar TODOS los hooks disparados, en orden cronologico
- Para cada hook: nombre, tipo (action/filter), numero de callbacks registrados, tiempo total
- Expandir un hook para ver cada callback individual: nombre/closure, prioridad, archivo de origen, tiempo
- Mostrar tambien hooks registrados pero NO disparados (en gris), util para saber que hooks existen
- Filtro de busqueda por nombre de hook
- Separar visualmente actions de filters si se desea

#### 4.2.4 Request

Informacion completa del request HTTP actual:

**Request:**
- Metodo: GET/POST
- URL completa
- Pagina admin actual (util para condicionar carga de CSS/JS)
- Query string parseado
- Headers del request
- Cookies (nombres, NO valores por seguridad)
- Body del POST (si aplica, truncado a 2KB)
- IP del cliente
- User-Agent

**Response:**
- Status code
- Headers de response
- Content-Type
- Content-Length (si disponible)

**Session:**
- ID de sesion (truncado)
- Usuario autenticado (nombre, rol)
- Datos de sesion (claves, no valores sensibles)

**Servidor:**
- `$_SERVER` filtrado (sin datos sensibles como passwords)

#### 4.2.5 Assets

Lista de todos los assets (CSS, JS, fuentes, imagenes) cargados en la pagina actual:

| # | Tipo | Archivo | Tamano | Fuente |
|---|------|---------|--------|--------|
| 1 | CSS | /admin/css/admin.css | 24.3 KB | core |
| 2 | JS | /admin/js/editor.js | 18.7 KB | core |
| 3 | JS | /plugins/hello-ai/assets/js/hello-ai.js | 2.1 KB | plugin:hello-ai |
| 4 | CSS | /admin/css/theme-vars.css | 1.2 KB | core |

Funcionalidades:

- Total de assets por tipo
- Tamano total combinado
- Indicar si viene de core, tema, o plugin
- Marcar assets duplicados o potencialmente innecesarios para la pagina actual
- Esto es fundamental para que el desarrollador sepa que se carga en cada pagina y optimice

#### 4.2.6 Environment

Informacion del entorno de ejecucion:

**PHP:**
- Version
- Extensions cargadas
- Configuracion relevante: `memory_limit`, `max_execution_time`, `upload_max_filesize`, `post_max_size`, `display_errors`, `error_reporting`, `opcache` status

**Klytos:**
- Version (del archivo `/VERSION`)
- Storage backend activo (File / Database)
- Ruta de instalacion
- Ruta del directorio data/
- Plugins activos (lista con version)
- Idioma activo
- Modo de build (static/dynamic)

**Servidor:**
- Software (`$_SERVER['SERVER_SOFTWARE']`)
- OS (`php_uname()`)
- Document root
- SAPI (`php_sapi_name()`)

**Base de datos (si DatabaseStorage):**
- Version MySQL/MariaDB
- Nombre de la base de datos
- Prefijo de tablas
- Charset/collation

#### 4.2.7 Logs

Panel de logs en tiempo real capturados durante el request:

| Nivel | Mensaje | Contexto | Origen |
|-------|---------|----------|--------|
| INFO | Page loaded: home | ['slug' => 'home'] | PageManager:45 |
| WARNING | Deprecated filter used | ['hook' => 'old.filter'] | plugin:hello-ai |
| ERROR | Failed to read file | ['path' => '...'] | FileStorage:112 |

Funcionalidades:

- Filtrar por nivel (DEBUG, INFO, WARNING, ERROR)
- Buscar en mensajes
- Mostrar deprecation warnings de forma destacada
- Capturar PHP notices/warnings si `display_errors` esta activo

#### 4.2.8 Page Context

Informacion especifica de la pagina actual que es util para desarrollo condicional:

- **Pagina admin actual**: ruta completa y nombre del archivo
- **Identificador de pagina**: slug o ID util para condicionar logica
- **Variables disponibles**: variables globales o de scope relevantes
- **Permisos requeridos**: que rol necesita esta pagina
- **Breadcrumb de navegacion**: donde estamos en la jerarquia del admin
- **Hooks especificos de esta pagina**: hooks que solo se disparan en esta pagina concreta
- **CSS/JS cargados solo en esta pagina**: assets condicionales

Esto permite al desarrollador de plugins saber exactamente en que pagina esta, para poder condicionar la carga de sus assets, registrar hooks solo donde se necesitan, etc.

---

## 5. Implementacion del frontend

### 5.1 Archivo CSS: `admin/css/dev-bar.css`

Solo se carga si Developer Mode esta activo. Estilos para:

- Barra compacta fija en el bottom
- Panel expandible con transicion suave
- Pestanas
- Tablas de datos con filas hover
- Colores semanticos para niveles de alerta (verde, amarillo, rojo)
- Fuente monoespacio para datos tecnicos
- Responsive: en pantallas estrechas, la barra compacta puede hacer scroll horizontal
- Z-index alto para que siempre este encima del contenido admin
- Ajustar el `padding-bottom` del body del admin para que el contenido no quede tapado

### 5.2 Archivo JS: `admin/js/dev-bar.js`

Logica del frontend:

```javascript
// Pseudocodigo de la estructura

class KlytosDevBar {
    constructor(data) {
        this.data = data;  // JSON inyectado por PHP
        this.expanded = false;
        this.activeTab = 'performance';
        this.init();
    }

    init() {
        this.renderBar();
        this.bindEvents();
    }

    renderBar() {
        // Renderizar barra compacta con metricas resumen
    }

    toggle() {
        this.expanded = !this.expanded;
        // Expandir/colapsar panel con animacion
    }

    renderPanel() {
        // Renderizar panel expandido con pestanas
    }

    renderTab(tabName) {
        // Renderizar contenido de la pestana activa
    }

    // Utilidades
    formatBytes(bytes) { /* ... */ }
    formatMs(ms) { /* ... */ }
    colorForMs(ms, threshold) { /* ... */ }
    sortTable(column, direction) { /* ... */ }
    filterTable(query) { /* ... */ }
}

// Inicializar con datos del servidor
document.addEventListener('DOMContentLoaded', () => {
    if (window.__KLYTOS_DEVBAR_DATA__) {
        new KlytosDevBar(window.__KLYTOS_DEVBAR_DATA__);
    }
});
```

### 5.3 Inyeccion de datos

En el layout del admin (`admin/layout.php` o equivalente), justo antes de `</body>`, si Developer Mode esta activo:

```php
<?php if ($app->isDevMode() && $currentUser->hasRole(['owner', 'admin'])): ?>
    <link rel="stylesheet" href="<?= $baseUrl ?>/admin/css/dev-bar.css">
    <script>
        window.__KLYTOS_DEVBAR_DATA__ = <?= json_encode(
            DevBar::getInstance()->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?>;
    </script>
    <script src="<?= $baseUrl ?>/admin/js/dev-bar.js"></script>
<?php endif; ?>
```

---

## 6. Medicion de CPU y memoria

### 6.1 Memoria

PHP proporciona esto de forma nativa:

```php
memory_get_usage(true);       // Memoria actual asignada por el sistema
memory_get_peak_usage(true);  // Pico de memoria durante el request
```

### 6.2 CPU (aproximado)

Usando `getrusage()` si esta disponible:

```php
$usage = getrusage();
$userTime   = $usage['ru_utime.tv_sec'] + ($usage['ru_utime.tv_usec'] / 1e6);
$systemTime = $usage['ru_stime.tv_sec'] + ($usage['ru_stime.tv_usec'] / 1e6);
```

Nota: `getrusage()` no esta disponible en todos los sistemas (no funciona en Windows). Detectar disponibilidad y mostrar "N/A" si no esta.

### 6.3 Tiempo real de ejecucion

```php
$executionTime = (microtime(true) - $_SERVER['REQUEST_FLOAT_TIME']) * 1000; // ms
```

---

## 7. Hooks y API para plugins

### 7.1 Hooks del DevBar

Los plugins pueden interactuar con el Developer Mode:

```php
// Anadir una pestana custom al panel
klytos_add_filter('devbar.tabs', function (array $tabs): array {
    $tabs['my_plugin_debug'] = [
        'label' => 'My Plugin',
        'renderer' => function () {
            return '<div>Custom debug info here</div>';
        },
    ];
    return $tabs;
});

// Anadir metricas a la barra compacta
klytos_add_filter('devbar.bar_items', function (array $items): array {
    $items[] = ['label' => 'Cache', 'value' => '95% hit'];
    return $items;
});

// Registrar datos custom en el DevBar
klytos_do_action('devbar.collect', DevBar::getInstance());
```

### 7.2 Metodo publico para que plugins registren datos

```php
// Desde un plugin:
if (class_exists(\Klytos\Core\DevBar::class)) {
    $devBar = \Klytos\Core\DevBar::getInstance();
    $devBar->log('info', 'My plugin initialized', ['version' => '1.0']);
    $devBar->logTimer('my_plugin.api_call', $start, $end);
}
```

---

## 8. Metodo helper en App

### 8.1 `App::isDevMode(): bool`

Metodo convenience en la clase App:

```php
public function isDevMode(): bool
{
    $config = $this->getSiteConfig()->get('developer');
    return !empty($config['developer_mode']);
}
```

Usar este metodo en toda la aplicacion para verificar si el DevMode esta activo antes de ejecutar logica de profiling.

---

## 9. Consideraciones de rendimiento

### 9.1 Overhead minimo cuando esta desactivado

- Si `developer_mode` es `false`, NO se instancia DevBar, NO se carga ProfilingStorage, NO se inyecta CSS/JS.
- La unica operacion es leer el flag de config, que ya esta en memoria despues del boot.

### 9.2 Overhead controlado cuando esta activo

- La recopilacion de datos anada entre 1-5ms al request total, dependiendo de la cantidad de hooks y operaciones.
- Los datos se recopilan en memoria y se serializan a JSON una sola vez al final del request.
- No se escribe nada a disco ni a base de datos por el DevBar (todo es in-memory, per-request).

### 9.3 No activar en produccion

Mostrar un aviso en Settings cuando Developer Mode esta activo:

> "Developer Mode esta activo. Esto anade overhead al procesamiento de cada pagina. Desactivalo en entornos de produccion."

---

## 10. Seguridad

- Solo roles `owner` y `admin` pueden activar y ver el Developer Mode.
- No exponer datos sensibles: passwords, tokens, API keys, valores de cookies de sesion.
- Los valores de `$_POST` que contengan "password", "token", "secret", "key", "api_key" se enmascaran como `***`.
- El DevBar NO se renderiza si el usuario no tiene permisos, aunque este activado en config.
- El JSON inyectado se sanitiza para evitar XSS (json_encode ya escapa por defecto, pero verificar contextos).

---

## 11. Estructura de archivos a crear/modificar

### Archivos nuevos:

```
core/dev-bar.php                  -- Clase DevBar (collector)
core/profiling-storage.php        -- ProfilingStorage wrapper
admin/css/dev-bar.css             -- Estilos del Developer Bar
admin/js/dev-bar.js               -- Logica frontend del Developer Bar
```

### Archivos a modificar:

```
core/app.php                      -- Inicializar DevBar si activo, metodo isDevMode()
core/hooks.php                    -- Anadir setProfiler() y medicion condicional
admin/settings.php                -- Anadir pestana Developer con las opciones
admin/layout.php (o equivalente)  -- Inyectar CSS/JS/JSON del DevBar antes de </body>
lang/en.json                      -- Traducciones para la pestana Developer
lang/es.json                      -- Traducciones para la pestana Developer
```

---

## 12. Plan de implementacion

### Fase 1: Nucleo
1. Crear `core/dev-bar.php` con la clase DevBar completa
2. Crear `core/profiling-storage.php` con ProfilingStorage
3. Modificar `core/hooks.php` para anadir profiling condicional
4. Modificar `core/app.php` para inicializar DevBar y metodo `isDevMode()`

### Fase 2: Settings
5. Anadir pestana Developer en `admin/settings.php`
6. Anadir traducciones en archivos de idioma

### Fase 3: Frontend
7. Crear `admin/css/dev-bar.css` con todos los estilos
8. Crear `admin/js/dev-bar.js` con toda la logica de renderizado
9. Modificar el layout del admin para inyectar el DevBar

### Fase 4: Integracion
10. Verificar que el DevBar no tiene overhead cuando esta desactivado
11. Verificar seguridad (roles, datos sensibles enmascarados)
12. Probar con FileStorage y DatabaseStorage
13. Probar que plugins pueden anadir pestanas custom y datos

---

## 13. Ejemplo de output JSON (toArray)

```json
{
    "meta": {
        "php_version": "8.3.4",
        "klytos_version": "1.0.0",
        "storage_backend": "file",
        "page": "/admin/pages.php",
        "page_identifier": "admin.pages",
        "timestamp": 1711900800
    },
    "performance": {
        "execution_time_ms": 127.4,
        "memory_usage": 4398046,
        "memory_peak": 5242880,
        "memory_formatted": "4.2 MB",
        "memory_peak_formatted": "5.0 MB",
        "cpu_user_time": 0.045,
        "cpu_system_time": 0.012,
        "included_files_count": 34
    },
    "storage": {
        "total_ops": 12,
        "total_time_ms": 3.1,
        "operations": [
            {
                "type": "read",
                "collection": "pages",
                "detail": "id=home",
                "duration_ms": 0.8,
                "caller": "PageManager::get() at core/page-manager.php:45"
            }
        ]
    },
    "hooks": {
        "total_fired": 47,
        "total_registered": 62,
        "fired": [
            {
                "name": "klytos.init",
                "type": "action",
                "callbacks": 3,
                "duration_ms": 0.2
            }
        ],
        "registered_not_fired": [
            "build.before",
            "build.after"
        ]
    },
    "assets": {
        "css": [
            {"path": "/admin/css/admin.css", "size": 24883, "source": "core"}
        ],
        "js": [
            {"path": "/admin/js/editor.js", "size": 19148, "source": "core"}
        ],
        "total_size": 44031
    },
    "request": {
        "method": "GET",
        "url": "/admin/pages.php",
        "query_string": {},
        "headers": {},
        "ip": "127.0.0.1",
        "user_agent": "Mozilla/5.0..."
    },
    "session": {
        "user": "jose",
        "role": "owner"
    },
    "environment": {
        "php": {},
        "server": {},
        "klytos": {},
        "database": null
    },
    "logs": [],
    "deprecations": [],
    "cache": {
        "hits": 15,
        "misses": 3
    }
}
```
