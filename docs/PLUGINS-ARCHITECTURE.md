# Klytos -- Arquitectura del Sistema de Plugins

**Version:** 0.15.0
**Fecha:** 31 de marzo de 2026
**Estado:** Implementado (contrato inmutable) + Propuesta de diseno (features extendidas)

**Prerequisito:** Este documento asume que el sistema de Templates descrito en `TEMPLATES-ARCHITECTURE.md` ya esta implementado. Ambos documentos se complementan.

---

## 0. Contrato Inmutable de Identificacion de Plugins

Este contrato define como Klytos identifica un plugin. Una vez establecido, NUNCA puede cambiar de forma que rompa plugins existentes. Solo se permiten cambios aditivos (anadir campos nuevos opcionales).

### 0.1. Regla de Identificacion

Un plugin de Klytos es un directorio dentro de `plugins/` que contiene un archivo PHP con el mismo nombre que el directorio, y ese archivo tiene una cabecera `Plugin Name:` en su docblock.

```
plugins/mi-plugin/mi-plugin.php    <-- ESTO es un plugin
```

- El nombre del directorio = el nombre del archivo (sin `.php`) = el `plugin-id`
- El `plugin-id` solo permite: `[a-z0-9][a-z0-9_-]*` (minusculas, numeros, guiones, underscores)
- El archivo PHP DEBE tener al menos `Plugin Name:` en su cabecera docblock

### 0.2. Plugin Minimo Viable

```php
<?php
/**
 * Plugin Name: Hello World
 */
```

Esto en `plugins/hello-world/hello-world.php` es un plugin valido. Klytos lo descubre, lo lista en admin, y permite activarlo. No hace nada, pero ES un plugin.

### 0.3. Formato de Cabecera PHP (inmutable)

```php
<?php
/**
 * Plugin Name: Mi Plugin Genial
 * Plugin URI: https://ejemplo.com/mi-plugin
 * Description: Descripcion corta del plugin.
 * Version: 1.0.0
 * Author: Jose Conti
 * Author URI: https://joseconti.com
 * Requires Klytos: 0.15.0
 * Requires PHP: 8.1
 * License: ELv2
 * License URI: https://www.elastic.co/licensing/elastic-license
 * Text Domain: mi-plugin
 * Domain Path: /lang
 * Premium: false
 * Item Name: mi-plugin-pro
 * Update URI: https://klytos.io/plugins/mi-plugin
 */
```

| Campo | Requerido | Default | Proposito |
|---|---|---|---|
| `Plugin Name` | **SI** | -- | Nombre humano. UNICO campo obligatorio |
| `Plugin URI` | no | `''` | Web del plugin |
| `Description` | no | `''` | Descripcion corta |
| `Version` | no | `'0.0.1'` | Version semver |
| `Author` | no | `''` | Nombre del autor |
| `Author URI` | no | `''` | Web del autor |
| `Requires Klytos` | no | `'0.0.0'` | Version minima de Klytos |
| `Requires PHP` | no | `'8.1'` | Version minima de PHP |
| `License` | no | `''` | Identificador de licencia (SPDX o nombre corto) |
| `License URI` | no | `''` | URL de la licencia |
| `Text Domain` | no | plugin-id | Dominio i18n |
| `Domain Path` | no | `'/lang'` | Ruta a archivos de traduccion |
| `Premium` | no | `false` | Si requiere clave de licencia |
| `Item Name` | no | plugin-id | Slug/identificador tecnico del producto en el servidor de licencias (ej: `klytos-e-commerce-pro`) |
| `Update URI` | no | `''` | URL para actualizaciones de terceros |

**Regla de oro**: en el futuro se pueden ANADIR campos nuevos, pero NUNCA quitar ni cambiar la semantica de los existentes.

### 0.4. El archivo principal ES el punto de entrada

El archivo `{plugin-id}.php` es TANTO la identificacion como el punto de entrada. Cuando el plugin esta activo, Klytos ejecuta este archivo en cada peticion (en scope aislado via closure).

### 0.5. klytos-plugin.json es extension OPCIONAL

El archivo JSON se mantiene para datos estructurados complejos que no caben en una cabecera:

- `admin_pages` -- configuracion declarativa del sidebar (estructura anidada)
- `mcp_tools` -- lista de herramientas MCP
- `permissions` -- capabilities requeridos
- `capabilities` -- funcionalidades que usa el plugin (informativo)
- `post_types` -- registro declarativo de post types
- `routes` -- registro declarativo de rutas
- `assets` -- configuracion de encolado de assets

Si ambos archivos contienen el mismo campo (ej: `version`), la **cabecera PHP gana siempre**.

### 0.6. Estructura del Plugin

```
plugins/mi-plugin/
|-- mi-plugin.php                <-- REQUERIDO: identificacion + entry point
|-- klytos-plugin.json           <-- OPCIONAL: metadata extendida
|-- install.php                  <-- OPCIONAL: primera activacion
|-- deactivate.php               <-- OPCIONAL: al desactivar
|-- uninstall.php                <-- OPCIONAL: al desinstalar (borrar datos)
|-- admin/                       <-- OPCIONAL: paginas de admin
|   +-- settings.php
|-- assets/                      <-- OPCIONAL: CSS, JS, imagenes
|-- lang/                        <-- OPCIONAL: traducciones
|-- src/                         <-- OPCIONAL: clases PHP
|-- templates/                   <-- OPCIONAL: plantillas HTML
+-- migrations/                  <-- OPCIONAL: migraciones de datos
```

### 0.7. NO se soportan plugins de archivo suelto

Un archivo PHP suelto en `plugins/` (sin directorio) NO es un plugin. Requiere siempre directorio + archivo con el mismo nombre.

### 0.8. Compatibilidad con formato legacy

Los plugins que aun usan `klytos-plugin.json` + `init.php` (sin cabecera PHP) siguen funcionando via fallback, pero se registra un aviso de deprecacion. Este fallback se eliminara en v2.0.0 (con un minimo de 12 meses de aviso).

---

## 1. Estado actual del sistema de plugins

### 1.1. Que ya funciona

El PluginLoader (`core/plugin-loader.php`) implementa:

- Descubrimiento de plugins por cabecera PHP (`plugins/{id}/{id}.php` con `Plugin Name:`)
- Fallback legacy por `klytos-plugin.json` (deprecated, se eliminara en v2.0.0)
- Merge de extension fields desde `klytos-plugin.json` (admin_pages, mcp_tools, etc.)
- Validacion de cabecera y manifiesto (campos requeridos, versiones, ID)
- Ciclo de vida completo: activate (con install.php), deactivate, uninstall
- Estado persistente en `plugins.json.enc` (activos, fechas)
- Verificacion de licencias premium contra plugins.joseconti.com
- Ejecucion aislada del entry point (closure scope)
- Hooks: plugin.loaded, plugin.activated, plugin.deactivated, plugin.uninstalled

El sistema de hooks (`core/hooks.php`) ya implementa:

- Actions y Filters con prioridad numerica
- Helpers globales: klytos_add_action(), klytos_add_filter(), etc.
- Usado extensivamente por todos los managers del core

El sidebar del admin (`admin/templates/sidebar.php`) ya implementa:

- Filtro `admin.sidebar_items` para que plugins anadan menus
- Soporte para secciones (content, system, custom)
- Soporte para children (submenus)
- Capability checks por item

### 1.2. Que falta

1. **Routing dinamico:** los plugins no pueden registrar rutas publicas (paginas, API, webhooks)
2. **Paginas de admin de plugins:** no hay un router estandarizado para servir las paginas de admin de un plugin
3. **Autenticacion frontend:** no existen usuarios de sitio (clientes, suscriptores)
4. **Migraciones:** no hay sistema para que los plugins creen sus colecciones de datos
5. **Registro declarativo de post types:** los plugins no tienen API para crear post types que se gestionen automaticamente
6. **Encolado de assets:** no hay forma estandarizada de que un plugin cargue CSS/JS en el frontend

---

## 2. Routing dinamico

### 2.1. Nueva clase: RouteManager

**Archivo a crear:** `core/route-manager.php`

Esta clase gestiona las rutas dinamicas registradas por plugins. El Router la consulta antes de intentar servir archivos estaticos.

```php
<?php
/**
 * Klytos -- Route Manager
 * Gestiona rutas dinamicas registradas por plugins.
 *
 * Los plugins registran rutas en su init.php usando klytos_register_route().
 * El Router consulta este manager antes de servir archivos estaticos.
 *
 * Tipos de ruta:
 * - 'page': renderiza HTML dentro de una plantilla (como una pagina normal)
 * - 'api': devuelve JSON (para peticiones AJAX)
 * - 'webhook': recibe callbacks de servicios externos (Stripe, PayPal, etc.)
 *
 * @package Klytos
 * @since   0.12.0
 */

declare(strict_types=1);

namespace Klytos\Core;

class RouteManager
{
    /**
     * Rutas registradas.
     * Estructura: [
     *   ['pattern' => '/carrito', 'regex' => '...', 'params' => [...], 'config' => [...]]
     * ]
     *
     * @var array<int, array>
     */
    private array $routes = [];

    /**
     * Registrar una ruta dinamica.
     *
     * @param string $pattern Patron de URL. Puede contener parametros: /producto/{slug}
     *                        Los parametros se extraen automaticamente.
     *                        Ejemplos validos:
     *                          '/carrito'
     *                          '/mi-cuenta/{section}'
     *                          '/api/orders/{id}/status'
     *                          '/webhook/stripe'
     *
     * @param array $config Configuracion de la ruta:
     *   - 'callback' (required): callable. Array de [clase, metodo] o closure.
     *       Para tipo 'page': debe devolver string (HTML del contenido).
     *       Para tipo 'api': debe devolver array (se serializa a JSON).
     *       Para tipo 'webhook': debe devolver array (se serializa a JSON).
     *   - 'method' (optional): string. 'GET', 'POST', 'GET|POST'. Default: 'GET'.
     *   - 'type' (required): string. 'page', 'api' o 'webhook'.
     *   - 'template' (optional): string. Nombre de plantilla para tipo 'page'. Default: 'default'.
     *   - 'title' (optional): string. Titulo de la pagina para tipo 'page'. Default: ''.
     *   - 'auth' (optional): string|false.
     *       false = sin autenticacion (default).
     *       'frontend' = requiere sesion de usuario frontend (FrontendAuth).
     *       'admin' = requiere sesion de admin.
     *   - 'capability' (optional): string|null. Permiso requerido. Default: null.
     *   - 'plugin_id' (optional): string. ID del plugin que registra la ruta (se rellena automaticamente).
     *
     * @return void
     */
    public function register(string $pattern, array $config): void
    {
        // Validar campos requeridos
        if (empty($config['callback']) || !is_callable($config['callback'])) {
            throw new \InvalidArgumentException(
                "Route '{$pattern}': 'callback' es requerido y debe ser callable."
            );
        }
        if (empty($config['type']) || !in_array($config['type'], ['page', 'api', 'webhook'], true)) {
            throw new \InvalidArgumentException(
                "Route '{$pattern}': 'type' debe ser 'page', 'api' o 'webhook'."
            );
        }

        // Normalizar el patron
        $pattern = '/' . trim($pattern, '/');

        // Convertir patron a regex
        // '/mi-cuenta/{section}' => '#^/mi-cuenta/(?P<section>[^/]+)$#'
        $paramNames = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function (array $matches) use (&$paramNames): string {
                $paramNames[] = $matches[1];
                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $pattern
        );
        $regex = '#^' . $regex . '/?$#'; // Aceptar con o sin trailing slash

        // Normalizar config con defaults
        $config = array_merge([
            'method'     => 'GET',
            'type'       => 'page',
            'template'   => 'default',
            'title'      => '',
            'auth'       => false,
            'capability' => null,
            'plugin_id'  => null,
        ], $config);

        $this->routes[] = [
            'pattern' => $pattern,
            'regex'   => $regex,
            'params'  => $paramNames,
            'config'  => $config,
        ];
    }

    /**
     * Intentar hacer match de una URL con las rutas registradas.
     *
     * @param  string $url    URL limpia (sin query string). Ej: 'mi-cuenta/pedidos'
     * @param  string $method Metodo HTTP: 'GET', 'POST', etc.
     * @return array|null     Ruta matcheada con sus parametros, o null si no hay match.
     *   Estructura del resultado:
     *   [
     *     'config'   => [...],        // Configuracion de la ruta
     *     'params'   => ['section' => 'pedidos'],  // Parametros extraidos de la URL
     *     'pattern'  => '/mi-cuenta/{section}',     // Patron original
     *   ]
     */
    public function match(string $url, string $method = 'GET'): ?array
    {
        // Normalizar URL
        $url = '/' . trim($url, '/');

        foreach ($this->routes as $route) {
            // Verificar metodo HTTP
            $allowedMethods = array_map('trim', explode('|', strtoupper($route['config']['method'])));
            if (!in_array(strtoupper($method), $allowedMethods, true)) {
                continue;
            }

            // Intentar match con regex
            if (preg_match($route['regex'], $url, $matches)) {
                // Extraer parametros nombrados
                $params = [];
                foreach ($route['params'] as $paramName) {
                    $params[$paramName] = $matches[$paramName] ?? '';
                    // Sanitizar parametros (solo alfanumerico, guiones, puntos)
                    $params[$paramName] = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $params[$paramName]);
                }

                return [
                    'config'  => $route['config'],
                    'params'  => $params,
                    'pattern' => $route['pattern'],
                ];
            }
        }

        return null;
    }

    /**
     * Obtener todas las rutas registradas.
     * Util para debugging y para la pagina de admin de rutas.
     *
     * @return array Lista de rutas con su configuracion.
     */
    public function listRoutes(): array
    {
        return array_map(function (array $route): array {
            return [
                'pattern'   => $route['pattern'],
                'method'    => $route['config']['method'],
                'type'      => $route['config']['type'],
                'auth'      => $route['config']['auth'],
                'plugin_id' => $route['config']['plugin_id'],
            ];
        }, $this->routes);
    }

    /**
     * Verificar si hay alguna ruta registrada para un patron.
     *
     * @param  string $pattern Patron exacto (ej: '/carrito')
     * @return bool
     */
    public function hasRoute(string $pattern): bool
    {
        $pattern = '/' . trim($pattern, '/');
        foreach ($this->routes as $route) {
            if ($route['pattern'] === $pattern) {
                return true;
            }
        }
        return false;
    }
}
```

### 2.2. Modificaciones en Router::dispatch()

**Archivo a modificar:** `core/router.php`

Modificar el metodo `dispatch()` para consultar el RouteManager antes de servir estaticos. Los cambios son:

1. Despues del switch/case de rutas del core, consultar `RouteManager::match()`
2. Si hay match, ejecutar la ruta dinamica
3. Si no hay match, seguir con el flujo actual (servir estaticos)

```php
/**
 * Dispatch the current request to the appropriate handler.
 * MODIFICADO: consulta RouteManager antes de servir estaticos.
 */
public function dispatch(): void
{
    $route  = $_GET['route'] ?? $this->parseRoute();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // 1. Rutas internas del core (switch/case existente)
    switch ($route) {
        case 'mcp':
            $this->handleMcp();
            return;
        case 'oauth/authorize':
            $this->handleOAuthAuthorize();
            return;
        case 'oauth/token':
            $this->handleOAuthToken();
            return;
        case '.well-known/oauth-authorization-server':
            $this->handleOAuthMetadata();
            return;
        case 'cron':
            $this->handleCron();
            return;
        case 'install':
            $this->handleInstall();
            return;
        case 't':
        case 't.php':
            $this->handleAnalyticsPixel();
            return;
    }

    // 2. NUEVO: Rutas dinamicas registradas por plugins
    $routeManager = $this->app->getRouteManager();
    $matched = $routeManager->match($route, $method);

    if ($matched !== null) {
        $this->handleDynamicRoute($matched);
        return;
    }

    // 3. Fallback: servir archivo estatico (comportamiento existente)
    $this->handlePublic($route);
}

/**
 * NUEVO: Ejecutar una ruta dinamica registrada por un plugin.
 *
 * @param array $matched Resultado de RouteManager::match()
 *   - 'config': configuracion de la ruta
 *   - 'params': parametros extraidos de la URL
 *   - 'pattern': patron original
 */
private function handleDynamicRoute(array $matched): void
{
    $config = $matched['config'];
    $params = $matched['params'];

    // ─── Autenticacion ───────────────────────────────────────
    if ($config['auth'] === 'frontend') {
        // Iniciar sesion frontend si no esta activa
        $frontendAuth = $this->app->getFrontendAuth();
        if (!$frontendAuth->isAuthenticated()) {
            // Redirigir a login con URL de retorno
            $returnUrl = $_SERVER['REQUEST_URI'] ?? '/';
            $loginUrl = Helpers::publicUrl() . 'login?redirect=' . urlencode($returnUrl);
            Helpers::redirect($loginUrl);
            return;
        }
    } elseif ($config['auth'] === 'admin') {
        if (!$this->app->getAuth()->isAuthenticated()) {
            http_response_code(401);
            if ($config['type'] === 'api') {
                Helpers::jsonResponse(['error' => 'Authentication required'], 401);
            }
            Helpers::redirect(Helpers::url('admin/login.php'));
            return;
        }
    }

    // ─── Capability check ────────────────────────────────────
    if (!empty($config['capability'])) {
        if (!klytos_has_permission($config['capability'])) {
            http_response_code(403);
            if ($config['type'] === 'api' || $config['type'] === 'webhook') {
                Helpers::jsonResponse(['error' => 'Forbidden'], 403);
            } else {
                echo '<!DOCTYPE html><html><body><h1>403 - Acceso denegado</h1></body></html>';
            }
            return;
        }
    }

    // ─── Rate limiting para API y webhooks ───────────────────
    if ($config['type'] === 'api' || $config['type'] === 'webhook') {
        $rateLimiter = new MCP\RateLimiter($this->app->getDataPath());
        $clientIp = MCP\RateLimiter::getClientIp();
        $rateKey = 'route:' . $clientIp . ':' . $matched['pattern'];

        if (!$rateLimiter->check($rateKey, 60)) { // 60 peticiones por minuto
            http_response_code(429);
            header('Retry-After: 60');
            Helpers::jsonResponse(['error' => 'Rate limit exceeded'], 429);
            return;
        }
    }

    // ─── Ejecutar callback del plugin ────────────────────────
    try {
        $result = call_user_func($config['callback'], $params);
    } catch (\Throwable $e) {
        error_log("Klytos Router: error in dynamic route {$matched['pattern']}: " . $e->getMessage());

        if ($config['type'] === 'api' || $config['type'] === 'webhook') {
            Helpers::jsonResponse(['error' => 'Internal server error'], 500);
        } else {
            http_response_code(500);
            echo '<!DOCTYPE html><html><body><h1>500 - Error interno</h1></body></html>';
        }
        return;
    }

    // ─── Enviar respuesta segun el tipo ──────────────────────
    switch ($config['type']) {
        case 'api':
            // CORS para API endpoints
            $this->sendCorsHeaders();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'webhook':
            // Los webhooks devuelven JSON sin CORS
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'page':
            // Renderizar HTML dentro de una plantilla
            $this->renderDynamicPage($result, $config);
            break;
    }
}

/**
 * NUEVO: Renderizar una pagina dinamica dentro de una plantilla.
 * Usa el mismo sistema de templates que las paginas estaticas.
 *
 * @param string $contentHtml HTML del contenido (devuelto por el callback del plugin)
 * @param array  $config      Configuracion de la ruta
 */
private function renderDynamicPage(string $contentHtml, array $config): void
{
    $buildEngine = $this->app->getBuildEngine();

    // Construir un array de pagina "virtual" compatible con renderTemplate()
    $virtualPage = [
        'slug'             => trim($_SERVER['REQUEST_URI'] ?? '', '/'),
        'title'            => $config['title'] ?? '',
        'content_html'     => $contentHtml,
        'meta_description' => '',
        'og_image'         => '',
        'template'         => $config['template'] ?? 'default',
        'status'           => 'published',
        'lang'             => klytos_config('default_language', 'es'),
        'post_type'        => 'dynamic',
        'custom_css'       => '',
        'custom_js'        => '',
    ];

    // Renderizar usando el BuildEngine (misma logica que paginas estaticas)
    $html = $buildEngine->renderPage($virtualPage['slug']);

    // Si renderPage no funciona con slugs dinamicos, usar renderDynamicPage:
    $siteConfig = $this->app->getSiteConfig()->get();
    $menuHtml   = $this->app->getMenu()->toHtml(Helpers::getBasePath());
    $theme      = $this->app->getTheme()->get();

    // Cargar plantilla
    $templateResolver = $this->app->getTemplateResolver();
    $templateHtml = $templateResolver->resolve($config['template'] ?? 'default');

    // Procesar template parts
    $templateHtml = $buildEngine->processTemplateParts($templateHtml);

    // Reemplazar variables (misma logica que renderTemplate() existente)
    $replacements = $buildEngine->buildReplacements($virtualPage, $siteConfig, $menuHtml, $theme);
    foreach ($replacements as $key => $value) {
        $templateHtml = str_replace($key, $value, $templateHtml);
    }

    header('Content-Type: text/html; charset=utf-8');
    echo $templateHtml;
}
```

**NOTA IMPORTANTE:** El metodo `renderTemplate()` actual de BuildEngine es `private`. Para que el Router pueda usarlo, hay que:

1. Extraer la logica de reemplazo de variables a un metodo publico `buildReplacements()` en BuildEngine
2. Hacer publico `processTemplateParts()`

Cambios concretos en `build-engine.php`:

```php
// ANTES (private):
private function renderTemplate(array $page, ...): string

// DESPUES: extraer la parte de reemplazos a un metodo publico
public function buildReplacements(array $page, array $siteConfig, string $menuHtml, array $theme): array
{
    // Mover aqui todo el array $replacements que actualmente esta en renderTemplate()
    // Devolver el array en vez de aplicarlo
    $basePath = Helpers::getBasePath();
    $siteUrl  = Helpers::publicUrl();
    // ... (todo el codigo de construccion de variables)

    return [
        '{{site_name}}'         => Helpers::escHtml($siteConfig['site_name'] ?? ''),
        '{{page_title}}'        => Helpers::escHtml($page['title'] ?? ''),
        '{{page_content}}'      => klytos_apply_filters('page.content', $page['content_html'] ?? '', $page),
        // ... todas las demas variables existentes
    ];
}

// processTemplateParts() debe ser public:
public function processTemplateParts(string $templateHtml): string
{
    // ... (implementacion del documento TEMPLATES-ARCHITECTURE.md)
}
```

### 2.3. Integracion en App::boot()

**Archivo a modificar:** `core/app.php`

Anadir la propiedad y la inicializacion:

```php
// En la seccion de propiedades de la clase App:
/** @var RouteManager|null Gestor de rutas dinamicas. */
private ?RouteManager $routeManager = null;

// En el metodo boot(), ANTES de cargar plugins:
$this->routeManager = new RouteManager();

// Getter publico:
public function getRouteManager(): RouteManager
{
    return $this->routeManager;
}
```

El RouteManager se inicializa ANTES de cargar plugins porque los plugins registran sus rutas en init.php, que se ejecuta durante `PluginLoader::loadAll()`.

### 2.4. Helper global

**Archivo a modificar:** `core/helpers-global.php`

```php
/**
 * Registrar una ruta dinamica desde un plugin.
 *
 * @param string $pattern Patron de URL (ej: '/carrito', '/mi-cuenta/{section}')
 * @param array  $config  Configuracion de la ruta. Campos:
 *   - 'callback' (required): callable que recibe array $params y devuelve string|array
 *   - 'type' (required): 'page' | 'api' | 'webhook'
 *   - 'method' (optional): 'GET' | 'POST' | 'GET|POST'. Default: 'GET'
 *   - 'template' (optional): nombre de plantilla para tipo 'page'. Default: 'default'
 *   - 'title' (optional): titulo para tipo 'page'. Default: ''
 *   - 'auth' (optional): false | 'frontend' | 'admin'. Default: false
 *   - 'capability' (optional): string de permiso. Default: null
 *
 * @see RouteManager::register()
 */
function klytos_register_route(string $pattern, array $config): void
{
    App::getInstance()->getRouteManager()->register($pattern, $config);
}
```

---

## 3. Paginas de administracion de plugins

### 3.1. Nueva pagina: admin/plugin-page.php

**Archivo a crear:** `admin/plugin-page.php`

Esta pagina sirve como router para las paginas de admin que los plugins declaran. Recibe dos parametros GET: `plugin` (ID del plugin) y `page` (nombre de la pagina dentro del plugin).

```php
<?php
/**
 * Klytos Admin -- Plugin Page Router
 * Carga las paginas de administracion declaradas por los plugins.
 *
 * URL: admin/plugin-page.php?plugin={id}&page={page}
 *
 * El archivo PHP del plugin se busca en: plugins/{id}/admin/{page}.php
 * El plugin tiene acceso a $app, $currentUser, y todas las helpers de Klytos.
 *
 * @package Klytos
 * @since   0.12.0
 */

require_once __DIR__ . '/bootstrap.php';

// ─── Validar parametros ──────────────────────────────────────

$pluginId = $_GET['plugin'] ?? '';
$pageName = $_GET['page'] ?? '';

// Sanitizar: solo alfanumerico, guiones, underscores
$pluginId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $pluginId);
$pageName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $pageName);

if (empty($pluginId) || empty($pageName)) {
    http_response_code(400);
    $pageTitle = 'Error';
    require_once __DIR__ . '/templates/header.php';
    require_once __DIR__ . '/templates/sidebar.php';
    echo '<div class="alert alert-danger">Parametros invalidos.</div>';
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

// ─── Verificar que el plugin esta activo ─────────────────────

$pluginLoader = $app->getPluginLoader();
$activePlugins = $pluginLoader->getActivePlugins();

if (!isset($activePlugins[$pluginId])) {
    http_response_code(404);
    $pageTitle = 'Plugin no encontrado';
    require_once __DIR__ . '/templates/header.php';
    require_once __DIR__ . '/templates/sidebar.php';
    echo '<div class="alert alert-danger">El plugin "' . klytos_esc_html($pluginId)
       . '" no esta activo o no existe.</div>';
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

// ─── Verificar que la pagina existe ──────────────────────────

$pluginPagePath = klytos_plugin_path($pluginId, 'admin/' . $pageName . '.php');

if (!file_exists($pluginPagePath)) {
    http_response_code(404);
    $pageTitle = 'Pagina no encontrada';
    require_once __DIR__ . '/templates/header.php';
    require_once __DIR__ . '/templates/sidebar.php';
    echo '<div class="alert alert-danger">La pagina "' . klytos_esc_html($pageName)
       . '" no existe en el plugin "' . klytos_esc_html($pluginId) . '".</div>';
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

// ─── Verificar capability (si esta definida en el manifiesto) ─

$manifest = $pluginLoader->getManifest($pluginId);
$adminPages = $manifest['admin_pages'] ?? [];

// Buscar la capability requerida para esta pagina en el manifiesto
$requiredCapability = null;
foreach ($adminPages as $adminPage) {
    if (($adminPage['id'] ?? '') === $pageName) {
        $requiredCapability = $adminPage['capability'] ?? null;
        break;
    }
    // Buscar tambien en children
    foreach (($adminPage['children'] ?? []) as $child) {
        if (($child['id'] ?? '') === $pageName) {
            $requiredCapability = $child['capability'] ?? ($adminPage['capability'] ?? null);
            break 2;
        }
    }
}

if ($requiredCapability !== null && !klytos_has_permission($requiredCapability)) {
    http_response_code(403);
    $pageTitle = 'Acceso denegado';
    require_once __DIR__ . '/templates/header.php';
    require_once __DIR__ . '/templates/sidebar.php';
    echo '<div class="alert alert-danger">No tienes permisos para acceder a esta pagina.</div>';
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

// ─── Configurar contexto para el plugin ──────────────────────

// Estas variables estan disponibles dentro del archivo PHP del plugin:
// $app           - instancia de App (ya disponible desde bootstrap.php)
// $pluginId      - ID del plugin
// $pluginPath    - Path raiz del plugin
// $currentUser   - Usuario actual (ya disponible desde bootstrap.php)
// $cspNonce      - Nonce para scripts inline (ya disponible desde bootstrap.php)

$pluginPath = klytos_plugin_path($pluginId);

// Establecer $currentPage para que el sidebar marque el item correcto
$currentPage = 'plugin-page';
$currentItemId = $pageName;

// ─── Renderizar ──────────────────────────────────────────────

// El archivo del plugin es responsable de:
// 1. Definir $pageTitle antes de incluir sidebar
// 2. Generar el HTML del contenido
// El header, sidebar y footer los proporciona el core

require_once $pluginPagePath;
```

### 3.2. Lectura automatica de admin_pages desde el manifiesto

**Archivo a modificar:** `admin/templates/sidebar.php`

Actualmente los plugins usan el filtro `admin.sidebar_items` manualmente. Se anade lectura automatica del campo `admin_pages` del manifiesto, ANTES de aplicar el filtro (para que el filtro pueda modificar lo que el manifiesto declaro).

Anadir este bloque despues de la generacion de custom post types y ANTES de la linea `$sidebarItems = klytos_apply_filters('admin.sidebar_items', $sidebarItems);`:

```php
// Dinamico: leer admin_pages de los manifiestos de plugins activos.
// Los plugins pueden declarar sus paginas de admin en klytos-plugin.json
// sin necesidad de usar el filtro admin.sidebar_items manualmente.
try {
    $pluginLoader = $app->getPluginLoader();
    $activePlugins = $pluginLoader->getActivePlugins();

    foreach ($activePlugins as $activePluginId => $activeManifest) {
        $pluginAdminPages = $activeManifest['admin_pages'] ?? [];

        foreach ($pluginAdminPages as $pap) {
            $papId = $pap['id'] ?? '';
            if (empty($papId)) {
                continue;
            }

            // Construir children
            $papChildren = [];
            foreach (($pap['children'] ?? []) as $papChild) {
                $papChildren[] = [
                    'id'    => $papChild['id'] ?? '',
                    'title' => $papChild['title'] ?? '',
                    'url'   => $adminPath . 'plugin-page.php?plugin='
                             . urlencode($activePluginId) . '&page='
                             . urlencode($papChild['id'] ?? $papChild['file'] ?? ''),
                ];
            }

            $sidebarItems[] = [
                'id'         => $papId,
                'title'      => $pap['title'] ?? $activeManifest['name'] ?? $activePluginId,
                'url'        => $adminPath . 'plugin-page.php?plugin='
                             . urlencode($activePluginId) . '&page='
                             . urlencode($pap['id'] ?? ''),
                'icon'       => $pap['icon'] ?? 'fa-solid fa-puzzle-piece',
                'position'   => $pap['position'] ?? 86,
                'section'    => $pap['section'] ?? 'content',
                'capability' => $pap['capability'] ?? 'site.configure',
                'children'   => $papChildren,
            ];
        }
    }
} catch (\Throwable $e) {
    // Silently skip if plugin data is not available.
}

// EXISTENTE (no mover): filtro para que los plugins modifiquen el sidebar
$sidebarItems = klytos_apply_filters('admin.sidebar_items', $sidebarItems);
```

### 3.3. Formato del campo admin_pages en klytos-plugin.json

```json
{
  "admin_pages": [
    {
      "id": "ecommerce-settings",
      "title": "E-Commerce",
      "icon": "fa-solid fa-cart-shopping",
      "position": 25,
      "section": "content",
      "capability": "site.configure",
      "children": [
        {
          "id": "ecom-orders",
          "title": "Pedidos"
        },
        {
          "id": "ecom-shipping",
          "title": "Envios"
        },
        {
          "id": "ecom-payments",
          "title": "Pasarelas de pago"
        },
        {
          "id": "ecom-coupons",
          "title": "Cupones"
        }
      ]
    }
  ]
}
```

Cada `id` se mapea a un archivo en `plugins/{plugin-id}/admin/{id}.php`. El `id` del item principal se usa como pagina por defecto; los children se cargan por su propio `id`.

---

## 4. Autenticacion frontend

### 4.1. Nueva clase: FrontendUserManager

**Archivo a crear:** `core/frontend-user-manager.php`

Gestiona CRUD de usuarios del frontend. Usa una coleccion separada de los usuarios admin.

```php
<?php
/**
 * Klytos -- Frontend User Manager
 * CRUD para usuarios del frontend (clientes, suscriptores).
 *
 * Coleccion de almacenamiento: 'frontend_users'
 * Los usuarios frontend son COMPLETAMENTE separados de los usuarios admin.
 *
 * Datos de un usuario frontend:
 * [
 *     'id'              => string (16 hex chars, UUID),
 *     'email'           => string (unico, usado como login),
 *     'pass_hash'       => string (bcrypt),
 *     'first_name'      => string,
 *     'last_name'       => string,
 *     'display_name'    => string,
 *     'role'            => string ('customer' por defecto, extensible por plugins),
 *     'status'          => string ('active', 'pending', 'suspended'),
 *     'email_verified'  => bool,
 *     'created_at'      => string (ISO 8601),
 *     'updated_at'      => string (ISO 8601),
 *     'last_login'      => string|null (ISO 8601),
 *     '_meta'           => array (metadatos extensibles por plugins),
 * ]
 *
 * @package Klytos
 * @since   0.12.0
 */

declare(strict_types=1);

namespace Klytos\Core;

class FrontendUserManager
{
    private StorageInterface $storage;
    private const COLLECTION = 'frontend_users';

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Crear un usuario frontend.
     *
     * @param  array $data Datos requeridos: email, password, first_name, last_name
     * @return array Usuario creado (sin pass_hash)
     * @throws \InvalidArgumentException Si email duplicado o datos invalidos
     */
    public function create(array $data): array
    {
        $email = strtolower(trim($data['email'] ?? ''));

        if (empty($email) || !Helpers::isEmail($email)) {
            throw new \InvalidArgumentException('Email valido es requerido.');
        }

        $password = $data['password'] ?? '';
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('La contrasena debe tener al menos 8 caracteres.');
        }

        // Verificar unicidad de email
        $existing = $this->getByEmail($email);
        if ($existing !== null) {
            throw new \InvalidArgumentException('Ya existe un usuario con ese email.');
        }

        $id = Helpers::randomHex(8); // 16 chars

        $user = [
            'id'             => $id,
            'email'          => $email,
            'pass_hash'      => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'first_name'     => trim($data['first_name'] ?? ''),
            'last_name'      => trim($data['last_name'] ?? ''),
            'display_name'   => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
            'role'           => $data['role'] ?? 'customer',
            'status'         => $data['status'] ?? 'pending', // Requiere verificacion de email
            'email_verified' => false,
            'created_at'     => Helpers::now(),
            'updated_at'     => Helpers::now(),
            'last_login'     => null,
            '_meta'          => [],
        ];

        // Permitir que plugins modifiquen los datos antes de guardar
        $user = klytos_apply_filters('frontend_user.before_create', $user);

        $this->storage->write(self::COLLECTION, $id, $user);

        klytos_do_action('frontend_user.created', $user);

        // Devolver sin pass_hash
        unset($user['pass_hash']);
        return $user;
    }

    /**
     * Obtener un usuario por ID.
     *
     * @param  string $id User ID
     * @return array  Datos del usuario (sin pass_hash)
     * @throws \RuntimeException Si no existe
     */
    public function get(string $id): array
    {
        $user = $this->storage->read(self::COLLECTION, $id);
        unset($user['pass_hash']);
        return $user;
    }

    /**
     * Obtener un usuario por email (para login).
     * Devuelve CON pass_hash (solo para uso interno de FrontendAuth).
     *
     * @param  string $email Email del usuario
     * @return array|null Datos del usuario o null si no existe
     */
    public function getByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        $users = $this->storage->list(self::COLLECTION, ['email' => $email], 1);
        return !empty($users) ? $users[0] : null;
    }

    /**
     * Actualizar datos del perfil.
     *
     * @param  string $id   User ID
     * @param  array  $data Campos a actualizar (first_name, last_name, display_name)
     * @return array  Usuario actualizado (sin pass_hash)
     */
    public function update(string $id, array $data): array
    {
        $user = $this->storage->read(self::COLLECTION, $id);

        $allowedFields = ['first_name', 'last_name', 'display_name', 'role', 'status', 'email_verified'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $user[$field] = $data[$field];
            }
        }

        $user['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $id, $user);

        klytos_do_action('frontend_user.updated', $user);

        unset($user['pass_hash']);
        return $user;
    }

    /**
     * Verificar password (para login).
     *
     * @param  string $email    Email del usuario
     * @param  string $password Password en texto plano
     * @return array|null Datos del usuario si la password es correcta, null si no
     */
    public function verifyPassword(string $email, string $password): ?array
    {
        $user = $this->getByEmail($email);
        if ($user === null) {
            return null;
        }

        if (!password_verify($password, $user['pass_hash'])) {
            return null;
        }

        // Actualizar last_login
        $user['last_login'] = Helpers::now();
        $this->storage->write(self::COLLECTION, $user['id'], $user);

        unset($user['pass_hash']);
        return $user;
    }

    /**
     * Cambiar password.
     *
     * @param  string $id          User ID
     * @param  string $newPassword Nueva password en texto plano (minimo 8 chars)
     * @return bool
     */
    public function changePassword(string $id, string $newPassword): bool
    {
        if (strlen($newPassword) < 8) {
            throw new \InvalidArgumentException('La contrasena debe tener al menos 8 caracteres.');
        }

        $user = $this->storage->read(self::COLLECTION, $id);
        $user['pass_hash']  = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $user['updated_at'] = Helpers::now();

        $this->storage->write(self::COLLECTION, $id, $user);

        return true;
    }

    /**
     * Eliminar un usuario frontend.
     *
     * @param  string $id User ID
     * @return bool
     */
    public function delete(string $id): bool
    {
        $user = $this->storage->read(self::COLLECTION, $id);

        klytos_do_action('frontend_user.before_delete', $user);

        $result = $this->storage->delete(self::COLLECTION, $id);

        klytos_do_action('frontend_user.deleted', $id);

        return $result;
    }

    /**
     * Listar usuarios frontend.
     *
     * @param  array $filters Filtros: role, status
     * @param  int   $limit   Maximo de resultados
     * @param  int   $offset  Offset para paginacion
     * @return array Lista de usuarios (sin pass_hash)
     */
    public function list(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $users = $this->storage->list(self::COLLECTION, $filters, $limit, $offset);
        return array_map(function (array $user): array {
            unset($user['pass_hash']);
            return $user;
        }, $users);
    }
}
```

### 4.2. Nueva clase: FrontendAuth

**Archivo a crear:** `core/frontend-auth.php`

Gestiona la sesion del usuario frontend (login, logout, sesion activa).

```php
<?php
/**
 * Klytos -- Frontend Authentication
 * Gestiona sesiones de usuarios del frontend (clientes).
 *
 * Usa una sesion PHP separada de la del admin:
 * - Nombre de sesion: 'klytos_frontend'
 * - Path de cookie: '/' (todo el sitio, no solo /admin/)
 * - Lifetime: 30 dias
 *
 * COMPLETAMENTE separado de Auth (que gestiona el admin).
 *
 * @package Klytos
 * @since   0.12.0
 */

declare(strict_types=1);

namespace Klytos\Core;

class FrontendAuth
{
    private FrontendUserManager $userManager;
    private bool $sessionStarted = false;

    public function __construct(FrontendUserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    /**
     * Iniciar la sesion frontend (si no esta ya activa).
     * Se llama automaticamente cuando se necesita.
     */
    private function ensureSession(): void
    {
        if ($this->sessionStarted) {
            return;
        }

        // No interferir con la sesion del admin
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Si ya hay una sesion activa (del admin), guardarla y cerrarla
            session_write_close();
        }

        session_name('klytos_frontend');
        session_set_cookie_params([
            'lifetime' => 86400 * 30,   // 30 dias
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly'  => true,
            'samesite'  => 'Lax',       // Lax permite redireccion desde pasarelas de pago
        ]);
        session_start();
        $this->sessionStarted = true;
    }

    /**
     * Intentar login con email y password.
     *
     * @param  string $email    Email del usuario
     * @param  string $password Password en texto plano
     * @return array  ['success' => bool, 'user' => array|null, 'error' => string|null]
     */
    public function login(string $email, string $password): array
    {
        $this->ensureSession();

        // Rate limiting por IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $attemptKey = 'frontend_login_attempts_' . md5($ip);
        $attempts = (int) klytos_get_option($attemptKey, 0);

        if ($attempts >= 5) {
            return [
                'success' => false,
                'user'    => null,
                'error'   => 'Demasiados intentos. Espera 15 minutos.',
            ];
        }

        $user = $this->userManager->verifyPassword($email, $password);

        if ($user === null) {
            // Incrementar intentos fallidos
            klytos_set_option($attemptKey, $attempts + 1);

            return [
                'success' => false,
                'user'    => null,
                'error'   => 'Email o contrasena incorrectos.',
            ];
        }

        // Verificar que el usuario esta activo
        if (($user['status'] ?? '') !== 'active') {
            return [
                'success' => false,
                'user'    => null,
                'error'   => 'Tu cuenta no esta activa. Verifica tu email.',
            ];
        }

        // Login exitoso: crear sesion
        session_regenerate_id(true);
        $_SESSION['klytos_frontend_user_id'] = $user['id'];
        $_SESSION['klytos_frontend_login_at'] = time();

        // Reset intentos fallidos
        klytos_delete_option($attemptKey);

        klytos_do_action('frontend_user.logged_in', $user);

        return [
            'success' => true,
            'user'    => $user,
            'error'   => null,
        ];
    }

    /**
     * Cerrar sesion.
     */
    public function logout(): void
    {
        $this->ensureSession();

        $userId = $_SESSION['klytos_frontend_user_id'] ?? null;

        $_SESSION = [];
        session_destroy();
        $this->sessionStarted = false;

        // Eliminar cookie
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', time() - 42000, '/');
        }

        if ($userId) {
            klytos_do_action('frontend_user.logged_out', $userId);
        }
    }

    /**
     * Verificar si hay una sesion frontend activa.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        $this->ensureSession();
        return !empty($_SESSION['klytos_frontend_user_id']);
    }

    /**
     * Obtener el usuario frontend actual.
     *
     * @return array|null Datos del usuario o null si no esta autenticado
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $userId = $_SESSION['klytos_frontend_user_id'];

        try {
            return $this->userManager->get($userId);
        } catch (\RuntimeException $e) {
            // El usuario ya no existe en la BD
            $this->logout();
            return null;
        }
    }

    /**
     * Obtener el CSRF token para formularios frontend.
     *
     * @return string Token CSRF
     */
    public function getCsrfToken(): string
    {
        $this->ensureSession();

        if (empty($_SESSION['klytos_frontend_csrf'])) {
            $_SESSION['klytos_frontend_csrf'] = Helpers::randomHex(32);
        }

        return $_SESSION['klytos_frontend_csrf'];
    }

    /**
     * Verificar un CSRF token.
     *
     * @param  string $token Token recibido del formulario
     * @return bool
     */
    public function verifyCsrfToken(string $token): bool
    {
        $this->ensureSession();
        $expected = $_SESSION['klytos_frontend_csrf'] ?? '';
        return !empty($expected) && hash_equals($expected, $token);
    }
}
```

### 4.3. Integracion en App::boot()

**Archivo a modificar:** `core/app.php`

```php
// Propiedades:
/** @var FrontendUserManager|null Gestor de usuarios frontend. */
private ?FrontendUserManager $frontendUserManager = null;

/** @var FrontendAuth|null Autenticacion frontend. */
private ?FrontendAuth $frontendAuth = null;

// En boot(), despues de inicializar el storage:
$this->frontendUserManager = new FrontendUserManager($this->storage);
$this->frontendAuth = new FrontendAuth($this->frontendUserManager);

// Getters:
public function getFrontendUserManager(): FrontendUserManager
{
    return $this->frontendUserManager;
}

public function getFrontendAuth(): FrontendAuth
{
    return $this->frontendAuth;
}
```

### 4.4. Helpers globales para autenticacion frontend

**Archivo a modificar:** `core/helpers-global.php`

```php
// ─── Frontend Auth API ──────────────────────────────────────

/**
 * Obtener el usuario frontend actualmente autenticado.
 *
 * @return array|null Datos del usuario o null si no hay sesion
 */
function klytos_frontend_user(): ?array
{
    return App::getInstance()->getFrontendAuth()->getCurrentUser();
}

/**
 * Verificar si hay una sesion frontend activa.
 *
 * @return bool
 */
function klytos_is_frontend_authenticated(): bool
{
    return App::getInstance()->getFrontendAuth()->isAuthenticated();
}

/**
 * Obtener el CSRF token para formularios frontend.
 *
 * @return string
 */
function klytos_frontend_csrf_token(): string
{
    return App::getInstance()->getFrontendAuth()->getCsrfToken();
}

/**
 * Generar un campo hidden con el CSRF token para formularios frontend.
 *
 * @return string HTML del campo hidden
 */
function klytos_frontend_csrf_field(): string
{
    $token = klytos_frontend_csrf_token();
    return '<input type="hidden" name="_klytos_frontend_csrf" value="' . Helpers::escAttr($token) . '">';
}

/**
 * Verificar el CSRF token de un formulario frontend.
 * Llama a esta funcion al inicio de cada handler POST de formularios frontend.
 *
 * @return bool True si el token es valido
 */
function klytos_verify_frontend_csrf(): bool
{
    $token = $_POST['_klytos_frontend_csrf'] ?? '';
    return App::getInstance()->getFrontendAuth()->verifyCsrfToken($token);
}
```

---

## 5. Migraciones de plugins

### 5.1. Nueva clase: PluginMigration

**Archivo a crear:** `core/plugin-migration.php`

```php
<?php
/**
 * Klytos -- Plugin Migration System
 * Ejecuta migraciones de base de datos/colecciones para plugins.
 *
 * Las migraciones se almacenan en plugins/{id}/migrations/ como archivos PHP
 * numerados: 001-create-orders.php, 002-add-fields.php, etc.
 *
 * Cada archivo devuelve un array con:
 * - 'id': string identificador unico de la migracion
 * - 'description': string descripcion
 * - 'up': callable que ejecuta la migracion
 * - 'down': callable que revierte la migracion (usado en uninstall)
 *
 * El estado de migraciones ejecutadas se guarda en Options:
 * {plugin_id}.migrations_run = ['001-create-orders', '002-add-fields']
 *
 * @package Klytos
 * @since   0.12.0
 */

declare(strict_types=1);

namespace Klytos\Core;

class PluginMigration
{
    private string $pluginsDir;

    public function __construct(string $pluginsDir)
    {
        $this->pluginsDir = rtrim($pluginsDir, '/');
    }

    /**
     * Ejecutar migraciones pendientes de un plugin.
     * Se llama automaticamente durante activate() y al actualizar el plugin.
     *
     * @param  string $pluginId ID del plugin
     * @return array  ['success' => bool, 'run' => string[], 'errors' => string[]]
     */
    public function runPending(string $pluginId): array
    {
        $migrationsDir = $this->pluginsDir . '/' . $pluginId . '/migrations';
        if (!is_dir($migrationsDir)) {
            return ['success' => true, 'run' => [], 'errors' => []];
        }

        // Obtener migraciones ya ejecutadas
        $optionKey = $pluginId . '.migrations_run';
        $alreadyRun = klytos_get_option($optionKey, []);
        if (!is_array($alreadyRun)) {
            $alreadyRun = [];
        }

        // Descubrir archivos de migracion (ordenados numericamente)
        $files = glob($migrationsDir . '/*.php');
        sort($files); // Orden alfabetico = orden numerico con prefijo 001, 002...

        $run = [];
        $errors = [];

        foreach ($files as $file) {
            $migration = require $file;

            if (!is_array($migration) || empty($migration['id'])) {
                $errors[] = "Archivo invalido: " . basename($file);
                continue;
            }

            $migrationId = $migration['id'];

            // Saltar si ya se ejecuto
            if (in_array($migrationId, $alreadyRun, true)) {
                continue;
            }

            // Ejecutar la migracion
            try {
                if (!empty($migration['up']) && is_callable($migration['up'])) {
                    call_user_func($migration['up']);
                }

                $alreadyRun[] = $migrationId;
                $run[] = $migrationId;

                klytos_log('info', "Migration {$migrationId} executed for plugin {$pluginId}");

            } catch (\Throwable $e) {
                $errors[] = "Error en migracion {$migrationId}: " . $e->getMessage();
                klytos_log('error', "Migration {$migrationId} failed for {$pluginId}: " . $e->getMessage());
                // Parar al primer error para no dejar estado inconsistente
                break;
            }
        }

        // Guardar estado actualizado
        klytos_set_option($optionKey, $alreadyRun);

        return [
            'success' => empty($errors),
            'run'     => $run,
            'errors'  => $errors,
        ];
    }

    /**
     * Revertir todas las migraciones de un plugin (para uninstall).
     * Ejecuta los callbacks 'down' en orden inverso.
     *
     * @param  string $pluginId ID del plugin
     * @return array  ['success' => bool, 'reverted' => string[], 'errors' => string[]]
     */
    public function rollbackAll(string $pluginId): array
    {
        $migrationsDir = $this->pluginsDir . '/' . $pluginId . '/migrations';
        if (!is_dir($migrationsDir)) {
            return ['success' => true, 'reverted' => [], 'errors' => []];
        }

        $optionKey = $pluginId . '.migrations_run';
        $alreadyRun = klytos_get_option($optionKey, []);
        if (!is_array($alreadyRun) || empty($alreadyRun)) {
            return ['success' => true, 'reverted' => [], 'errors' => []];
        }

        // Cargar todas las migraciones en orden inverso
        $files = glob($migrationsDir . '/*.php');
        rsort($files); // Orden inverso

        $reverted = [];
        $errors = [];

        foreach ($files as $file) {
            $migration = require $file;

            if (!is_array($migration) || empty($migration['id'])) {
                continue;
            }

            $migrationId = $migration['id'];

            // Solo revertir las que se ejecutaron
            if (!in_array($migrationId, $alreadyRun, true)) {
                continue;
            }

            try {
                if (!empty($migration['down']) && is_callable($migration['down'])) {
                    call_user_func($migration['down']);
                }
                $reverted[] = $migrationId;
            } catch (\Throwable $e) {
                $errors[] = "Error revirtiendo {$migrationId}: " . $e->getMessage();
            }
        }

        // Limpiar estado
        klytos_delete_option($optionKey);

        return [
            'success'  => empty($errors),
            'reverted' => $reverted,
            'errors'   => $errors,
        ];
    }
}
```

### 5.2. Integracion con PluginLoader

**Archivo a modificar:** `core/plugin-loader.php`

En el metodo `activate()`, despues de ejecutar install.php, ejecutar migraciones:

```php
public function activate(string $pluginId): array
{
    // ... (codigo existente: leer manifiesto, verificar si ya activo) ...

    // Run install.php if it exists (first-time activation setup).
    $installFile = $this->pluginsDir . '/' . $pluginId . '/install.php';
    if (file_exists($installFile)) {
        try {
            require_once $installFile;
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Install script failed: ' . $e->getMessage()];
        }
    }

    // NUEVO: ejecutar migraciones pendientes
    $migration = new PluginMigration($this->pluginsDir);
    $migrationResult = $migration->runPending($pluginId);
    if (!$migrationResult['success']) {
        return [
            'success' => false,
            'error'   => 'Migration failed: ' . implode('; ', $migrationResult['errors']),
        ];
    }

    // ... (codigo existente: marcar como activo, guardar estado) ...

    // NUEVO: regenerar klytos-hooks.js y plugins.css
    try {
        $buildEngine = \Klytos\Core\App::getInstance()->getBuildEngine();
        $buildEngine->buildHooksJs();
        $buildEngine->buildPluginsCss();
    } catch (\Throwable $e) {
        error_log("Klytos: error rebuilding assets after activating {$pluginId}: " . $e->getMessage());
    }

    // ... (codigo existente: fire plugin.activated action) ...
}
```

En el metodo `uninstall()`, antes de ejecutar uninstall.php, revertir migraciones:

```php
public function uninstall(string $pluginId): array
{
    // Deactivate first.
    $this->deactivate($pluginId);

    // NUEVO: revertir migraciones
    $migration = new PluginMigration($this->pluginsDir);
    $migration->rollbackAll($pluginId);

    // Run uninstall.php if present (removes plugin data).
    // ... (codigo existente) ...

    // NUEVO: limpiar options del plugin
    App::getInstance()->getOptionsManager()->deleteForPlugin($pluginId);

    // NUEVO: regenerar assets
    try {
        $buildEngine = App::getInstance()->getBuildEngine();
        $buildEngine->buildHooksJs();
        $buildEngine->buildPluginsCss();
    } catch (\Throwable $e) {
        error_log("Klytos: error rebuilding assets after uninstalling {$pluginId}: " . $e->getMessage());
    }

    // ... (codigo existente: eliminar de estado, fire hook) ...
}
```

Y en `deactivate()`, regenerar tambien:

```php
public function deactivate(string $pluginId): array
{
    // ... (codigo existente) ...

    // NUEVO: regenerar assets (el plugin ya no esta activo, sus hooks desaparecen)
    try {
        $buildEngine = App::getInstance()->getBuildEngine();
        $buildEngine->buildHooksJs();
        $buildEngine->buildPluginsCss();
    } catch (\Throwable $e) {
        error_log("Klytos: error rebuilding assets after deactivating {$pluginId}: " . $e->getMessage());
    }

    // ... (codigo existente) ...
}
```

### 5.3. Formato de archivo de migracion

Los plugins colocan sus migraciones en `plugins/{id}/migrations/`:

```php
<?php
// plugins/klytos-ecommerce/migrations/001-create-orders.php

return [
    'id'          => '001-create-orders',
    'description' => 'Crear colecciones de pedidos y items de pedido',
    'up'          => function () {
        // En FileStorage, las colecciones se crean automaticamente al escribir.
        // En DatabaseStorage, se crea la tabla automaticamente.
        // Pero podemos crear datos iniciales o configuracion:
        klytos_set_option('klytos-ecommerce.order_statuses', [
            'pending'    => 'Pendiente de pago',
            'processing' => 'En proceso',
            'shipped'    => 'Enviado',
            'completed'  => 'Completado',
            'cancelled'  => 'Cancelado',
            'refunded'   => 'Reembolsado',
        ]);
    },
    'down'        => function () {
        // Limpiar al desinstalar
        klytos_delete_option('klytos-ecommerce.order_statuses');
        // Nota: eliminar colecciones completas es operacion destructiva
        // Se hace en uninstall.php, no en migrations down
    },
];
```

---

## 6. Registro declarativo de Post Types desde plugins

### 6.1. Helper global

**Archivo a modificar:** `core/helpers-global.php`

```php
/**
 * Registrar un Custom Post Type desde un plugin.
 *
 * El post type se crea usando PostTypeManager, pero se marca con el plugin_id
 * como propietario. Al desinstalar el plugin, los post types marcados como suyos
 * pueden limpiarse opcionalmente.
 *
 * @param string $postTypeId ID del post type (ej: 'product')
 * @param array  $config     Configuracion:
 *   - 'name' (required): string. Nombre legible (ej: 'Productos')
 *   - 'slug' (required): string. Slug para URLs (ej: '/productos')
 *   - 'slug_i18n' (optional): array. Slugs por idioma. Ej: ['en' => '/products']
 *   - 'icon' (optional): string. Clase CSS del icono. Default: 'fa-solid fa-newspaper'
 *   - 'plugin' (required): string. ID del plugin propietario
 *   - 'taxonomies' (optional): array. Taxonomias a crear. Cada una:
 *       - 'id': string
 *       - 'name': string
 *       - 'slug': string
 *       - 'hierarchical': bool
 *   - 'custom_fields' (optional): array. Campos personalizados. Cada uno:
 *       - 'name': string
 *       - 'type': string (text, number, boolean, image, url, select, richtext, date, email, phone, array, html, color, icon)
 *       - 'label': string
 *       - 'required': bool (optional, default false)
 *       - 'default': mixed (optional)
 */
function klytos_register_post_type(string $postTypeId, array $config): void
{
    $app = App::getInstance();
    $ptm = $app->getPostTypeManager();

    // Verificar si ya existe
    if ($ptm->exists($postTypeId)) {
        // Si ya existe y es del mismo plugin, actualizar
        $existing = $ptm->get($postTypeId);
        $existingPlugin = klytos_get_meta('post-types', $postTypeId, '_plugin_owner');

        if ($existingPlugin === ($config['plugin'] ?? '')) {
            // Actualizar post type existente
            $ptm->update($postTypeId, [
                'name'          => $config['name'] ?? $existing['name'],
                'slug'          => $config['slug'] ?? $existing['slug'],
                'slug_i18n'     => $config['slug_i18n'] ?? ($existing['slug_i18n'] ?? []),
                'custom_fields' => $config['custom_fields'] ?? ($existing['custom_fields'] ?? []),
            ]);
        }
        // Si existe pero es de otro plugin o del usuario, no tocar
        return;
    }

    // Crear post type nuevo
    $ptm->create([
        'id'            => $postTypeId,
        'name'          => $config['name'] ?? $postTypeId,
        'slug'          => $config['slug'] ?? '/' . $postTypeId,
        'slug_i18n'     => $config['slug_i18n'] ?? [],
        'custom_fields' => $config['custom_fields'] ?? [],
        'icon'          => $config['icon'] ?? 'fa-solid fa-newspaper',
    ]);

    // Marcar como propiedad del plugin
    klytos_set_meta('post-types', $postTypeId, '_plugin_owner', $config['plugin'] ?? '');

    // Crear taxonomias
    $taxonomies = $config['taxonomies'] ?? [];
    foreach ($taxonomies as $tax) {
        $ptm->addTaxonomy($postTypeId, [
            'id'           => $tax['id'],
            'name'         => $tax['name'],
            'slug'         => $tax['slug'] ?? '/' . $tax['id'],
            'hierarchical' => $tax['hierarchical'] ?? false,
        ]);
    }
}

/**
 * Registrar una taxonomia individual para un post type existente.
 *
 * @param string $postTypeId ID del post type
 * @param array  $taxonomy   Datos de la taxonomia (id, name, slug, hierarchical)
 */
function klytos_register_taxonomy(string $postTypeId, array $taxonomy): void
{
    $ptm = App::getInstance()->getPostTypeManager();

    if (!$ptm->exists($postTypeId)) {
        throw new \InvalidArgumentException("Post type '{$postTypeId}' no existe.");
    }

    $ptm->addTaxonomy($postTypeId, [
        'id'           => $taxonomy['id'],
        'name'         => $taxonomy['name'],
        'slug'         => $taxonomy['slug'] ?? '/' . $taxonomy['id'],
        'hierarchical' => $taxonomy['hierarchical'] ?? false,
    ]);
}

/**
 * Registrar campos personalizados para un post type.
 *
 * @param string $postTypeId ID del post type
 * @param array  $fields     Array de campos. Cada campo: [name, type, label, required, default]
 */
function klytos_register_custom_fields(string $postTypeId, array $fields): void
{
    $ptm = App::getInstance()->getPostTypeManager();

    if (!$ptm->exists($postTypeId)) {
        throw new \InvalidArgumentException("Post type '{$postTypeId}' no existe.");
    }

    $existing = $ptm->get($postTypeId);
    $currentFields = $existing['custom_fields'] ?? [];

    // Merge: anadir campos nuevos sin duplicar
    $currentNames = array_column($currentFields, 'name');
    foreach ($fields as $field) {
        if (!in_array($field['name'], $currentNames, true)) {
            $currentFields[] = $field;
        }
    }

    $ptm->update($postTypeId, ['custom_fields' => $currentFields]);
}
```

---

## 7. Nuevo campo de manifiesto: capabilities

El manifiesto `klytos-plugin.json` acepta un nuevo campo opcional `capabilities` que declara que funcionalidades usa el plugin. Esto es INFORMATIVO (para la pagina de plugins del admin), no restrictivo:

```json
{
  "id": "klytos-ecommerce",
  "name": "Klytos E-Commerce",
  "version": "1.0.0",
  "description": "Tienda online completa para Klytos",
  "author": "Jose Conti",
  "author_url": "https://joseconti.com",
  "requires_klytos": "0.12.0",
  "requires_php": "8.1",

  "capabilities": {
    "post_types": true,
    "taxonomies": true,
    "custom_fields": true,
    "templates": true,
    "dynamic_routes": true,
    "admin_pages": true,
    "frontend_auth": true,
    "migrations": true,
    "cron_jobs": true,
    "webhooks": true
  },

  "admin_pages": [
    {
      "id": "ecommerce-settings",
      "title": "E-Commerce",
      "icon": "fa-solid fa-cart-shopping",
      "position": 25,
      "section": "content",
      "capability": "site.configure",
      "children": [
        { "id": "ecom-orders", "title": "Pedidos" },
        { "id": "ecom-shipping", "title": "Envios" },
        { "id": "ecom-payments", "title": "Pasarelas de pago" },
        { "id": "ecom-coupons", "title": "Cupones" }
      ]
    }
  ]
}
```

---

## 8. Ejemplo completo: estructura del plugin de e-commerce

```
plugins/klytos-ecommerce/
│
├── klytos-plugin.json              # Manifiesto (ver seccion 7)
│
├── init.php                         # Punto de entrada: registra rutas, templates,
│                                    # hooks, filters, menus, assets.
│                                    # Se ejecuta en cada peticion si el plugin esta activo.
│
├── install.php                      # Primera activacion: llama a klytos_register_post_type(),
│                                    # crea opciones iniciales.
│
├── deactivate.php                   # Limpieza al desactivar (opcional).
│                                    # NO borra datos. Solo deshabilita funcionalidad.
│
├── uninstall.php                    # Limpieza total: borra opciones, meta, colecciones.
│                                    # PREGUNTA al usuario si quiere borrar datos.
│
├── src/                             # Clases PHP del plugin
│   ├── Cart.php                     #   Logica del carrito (sesion, add/remove/update)
│   ├── CartApi.php                  #   API endpoints del carrito (/api/cart/*)
│   ├── Checkout.php                 #   Proceso de checkout
│   ├── Account.php                  #   Paginas de Mi Cuenta
│   ├── Product.php                  #   Logica de productos
│   ├── Order.php                    #   Gestion de pedidos
│   ├── OrderManager.php             #   CRUD de pedidos
│   ├── PaymentGateway.php           #   Interfaz base para pasarelas
│   ├── Gateways/
│   │   ├── Stripe.php               #   Pasarela Stripe
│   │   └── PayPal.php               #   Pasarela PayPal
│   └── Emails/
│       ├── OrderConfirmation.php    #   Email de confirmacion
│       └── OrderShipped.php         #   Email de envio
│
├── admin/                           # Paginas del admin
│   ├── ecommerce-settings.php       #   Configuracion general (moneda, impuestos, etc.)
│   ├── ecom-orders.php              #   Lista y detalle de pedidos
│   ├── ecom-shipping.php            #   Metodos de envio
│   ├── ecom-payments.php            #   Pasarelas de pago
│   └── ecom-coupons.php             #   Gestion de cupones
│
├── templates/                       # Plantillas HTML del plugin
│   ├── single-product.html          #   Ficha de producto (estatica, con hook points)
│   ├── archive-product.html         #   Listado de productos
│   ├── cart.html                    #   Pagina del carrito (dinamica)
│   ├── checkout.html                #   Checkout (dinamica)
│   ├── my-account.html              #   Mi Cuenta (dinamica)
│   └── order-confirmation.html      #   Confirmacion de pedido
│
├── assets/                          # Recursos estaticos
│   ├── css/
│   │   ├── ecommerce.css            #   Estilos generales
│   │   └── cart.css                 #   Estilos del carrito
│   └── js/
│       ├── hooks.js                 #   Registros de hook points (se concatena en klytos-hooks.js)
│       ├── cart.js                  #   Logica del carrito (AJAX)
│       └── checkout.js              #   Logica del checkout
│
├── lang/                            # Traducciones
│   ├── es.json
│   └── en.json
│
└── migrations/                      # Migraciones
    ├── 001-initial-setup.php        #   Opciones iniciales, estados de pedido
    ├── 002-create-shipping.php      #   Configuracion de envios
    └── 003-create-coupons.php       #   Estructura de cupones
```

---

## 9. Ejemplo de init.php del plugin de e-commerce

```php
<?php
/**
 * Klytos E-Commerce -- Plugin Entry Point
 * Se ejecuta en cada peticion cuando el plugin esta activo.
 *
 * Registra: rutas, templates, hooks, assets, menus.
 * NO contiene logica de negocio (esa va en src/).
 */

declare(strict_types=1);

// ─── Autoloader del plugin ──────────────────────────────────
spl_autoload_register(function (string $class): void {
    $prefix = 'KlytosEcommerce\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// ─── Registrar traducciones ─────────────────────────────────
klytos_register_translations('klytos-ecommerce', __DIR__ . '/lang');

// ─── Registrar plantillas ───────────────────────────────────
klytos_register_templates('klytos-ecommerce', [
    'single-product' => [
        'name'        => 'Ficha de Producto',
        'description' => 'Pagina individual de producto con galeria, precio y boton de compra',
        'file'        => __DIR__ . '/templates/single-product.html',
        'post_type'   => 'product',
    ],
    'archive-product' => [
        'name'        => 'Listado de Productos',
        'description' => 'Listado de productos con filtros por categoria',
        'file'        => __DIR__ . '/templates/archive-product.html',
        'post_type'   => 'product',
    ],
    'cart' => [
        'name'        => 'Carrito de Compras',
        'file'        => __DIR__ . '/templates/cart.html',
        'dynamic'     => true,
    ],
    'checkout' => [
        'name'        => 'Checkout',
        'file'        => __DIR__ . '/templates/checkout.html',
        'dynamic'     => true,
    ],
    'my-account' => [
        'name'        => 'Mi Cuenta',
        'file'        => __DIR__ . '/templates/my-account.html',
        'dynamic'     => true,
    ],
]);

// ─── Registrar rutas dinamicas ──────────────────────────────

// Paginas publicas
klytos_register_route('/carrito', [
    'callback' => [KlytosEcommerce\Cart::class, 'renderPage'],
    'method'   => 'GET',
    'type'     => 'page',
    'template' => 'cart',
    'title'    => 'Carrito de compras',
    'auth'     => false,
]);

klytos_register_route('/checkout', [
    'callback' => [KlytosEcommerce\Checkout::class, 'renderPage'],
    'method'   => 'GET|POST',
    'type'     => 'page',
    'template' => 'checkout',
    'title'    => 'Finalizar compra',
    'auth'     => 'frontend',
]);

klytos_register_route('/mi-cuenta', [
    'callback' => [KlytosEcommerce\Account::class, 'renderDashboard'],
    'method'   => 'GET',
    'type'     => 'page',
    'template' => 'my-account',
    'title'    => 'Mi cuenta',
    'auth'     => 'frontend',
]);

klytos_register_route('/mi-cuenta/{section}', [
    'callback' => [KlytosEcommerce\Account::class, 'renderSection'],
    'method'   => 'GET|POST',
    'type'     => 'page',
    'template' => 'my-account',
    'title'    => 'Mi cuenta',
    'auth'     => 'frontend',
]);

klytos_register_route('/login', [
    'callback' => [KlytosEcommerce\Account::class, 'renderLogin'],
    'method'   => 'GET|POST',
    'type'     => 'page',
    'template' => 'default',
    'title'    => 'Iniciar sesion',
    'auth'     => false,
]);

klytos_register_route('/registro', [
    'callback' => [KlytosEcommerce\Account::class, 'renderRegister'],
    'method'   => 'GET|POST',
    'type'     => 'page',
    'template' => 'default',
    'title'    => 'Crear cuenta',
    'auth'     => false,
]);

// API endpoints (AJAX)
klytos_register_route('/api/cart/add', [
    'callback' => [KlytosEcommerce\CartApi::class, 'addItem'],
    'method'   => 'POST',
    'type'     => 'api',
    'auth'     => false,
]);

klytos_register_route('/api/cart/update', [
    'callback' => [KlytosEcommerce\CartApi::class, 'updateItem'],
    'method'   => 'POST',
    'type'     => 'api',
    'auth'     => false,
]);

klytos_register_route('/api/cart/remove', [
    'callback' => [KlytosEcommerce\CartApi::class, 'removeItem'],
    'method'   => 'POST',
    'type'     => 'api',
    'auth'     => false,
]);

klytos_register_route('/api/cart/count', [
    'callback' => [KlytosEcommerce\CartApi::class, 'getCount'],
    'method'   => 'GET',
    'type'     => 'api',
    'auth'     => false,
]);

// Webhooks (IPN)
klytos_register_route('/webhook/stripe', [
    'callback' => [KlytosEcommerce\Gateways\Stripe::class, 'handleWebhook'],
    'method'   => 'POST',
    'type'     => 'webhook',
    'auth'     => false,
]);

klytos_register_route('/webhook/paypal', [
    'callback' => [KlytosEcommerce\Gateways\PayPal::class, 'handleWebhook'],
    'method'   => 'POST',
    'type'     => 'webhook',
    'auth'     => false,
]);

// ─── Inyectar icono del carrito en el header ────────────────
klytos_register_template_part('header', function (?string $html): string {
    $cartHtml = '<div class="ecom-cart-icon">'
              . '<a href="/carrito" id="ecom-cart-link">'
              . '<i class="fa-solid fa-cart-shopping"></i>'
              . '<span data-klytos-hook="cart_count" class="ecom-cart-badge"></span>'
              . '</a></div>';

    if ($html !== null) {
        return str_replace('</header>', $cartHtml . '</header>', $html);
    }
    return $cartHtml;
}, 10);

// ─── Hooks del build: anadir datos de producto al JSON de pagina ─
klytos_add_filter('page.content', function (string $content, array $page): string {
    if (($page['post_type'] ?? '') !== 'product') {
        return $content;
    }

    // Inyectar datos del producto como JSON para que klytos-hooks.js los use
    $productData = [
        'type'  => 'product',
        'id'    => $page['slug'] ?? '',
        'title' => $page['title'] ?? '',
        'price' => (float) klytos_get_meta('pages', $page['slug'], 'klytos-ecommerce.price'),
        'sku'   => klytos_get_meta('pages', $page['slug'], 'klytos-ecommerce.sku') ?? '',
        'stock' => (int) klytos_get_meta('pages', $page['slug'], 'klytos-ecommerce.stock'),
        'url'   => '/' . ($page['slug'] ?? '') . '/',
    ];

    $json = json_encode($productData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $scriptTag = '<script type="application/json" id="klytos-page-data">' . $json . '</script>';

    return $scriptTag . "\n" . $content;
}, 10);

// ─── Exponer hooks para otros plugins ───────────────────────
// Otros plugins pueden filtrar pasarelas de pago, metodos de envio, etc.
// Ver documentacion del plugin para la lista completa de hooks.
```

---

## 10. Resumen de todos los archivos

### Archivos nuevos a crear

| Archivo | Lineas aprox. | Proposito |
|---|---|---|
| `core/route-manager.php` | ~150 | Registro y matching de rutas dinamicas |
| `core/frontend-user-manager.php` | ~200 | CRUD de usuarios frontend |
| `core/frontend-auth.php` | ~180 | Sesion y autenticacion frontend |
| `core/plugin-migration.php` | ~130 | Migraciones de base de datos para plugins |
| `admin/plugin-page.php` | ~80 | Router de paginas de admin de plugins |

### Archivos a modificar

| Archivo | Cambios concretos |
|---|---|
| `core/router.php` | Anadir consulta a RouteManager en dispatch(), nuevo metodo handleDynamicRoute(), nuevo metodo renderDynamicPage() |
| `core/build-engine.php` | Hacer publicos buildReplacements() y processTemplateParts(), nuevos metodos buildHooksJs() y buildPluginsCss() |
| `core/app.php` | Inicializar RouteManager, FrontendUserManager, FrontendAuth. Anadir getters para los tres |
| `core/helpers-global.php` | Nuevos helpers: klytos_register_route(), klytos_register_post_type(), klytos_register_taxonomy(), klytos_register_custom_fields(), klytos_frontend_user(), klytos_is_frontend_authenticated(), klytos_frontend_csrf_token(), klytos_frontend_csrf_field(), klytos_verify_frontend_csrf() |
| `core/plugin-loader.php` | En activate(): ejecutar migraciones y regenerar assets. En deactivate()/uninstall(): regenerar assets y revertir migraciones |
| `admin/templates/sidebar.php` | Leer admin_pages del manifiesto de plugins activos antes de aplicar el filtro |

### Orden de implementacion

1. **RouteManager** + cambios en Router (prerequisito para todo lo dinamico)
2. **TemplateResolver** + cambios en BuildEngine (prerequisito para templates de plugins) -- ver TEMPLATES-ARCHITECTURE.md
3. **admin/plugin-page.php** + lectura de admin_pages del manifiesto
4. **FrontendUserManager** + **FrontendAuth** + helpers
5. **PluginMigration** + integracion en PluginLoader
6. **Helpers de post types** (klytos_register_post_type, etc.)
7. **buildHooksJs()** y **buildPluginsCss()** en BuildEngine
8. Hook points en las plantillas HTML del core
