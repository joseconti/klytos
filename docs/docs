# Klytos — Arquitectura v2.0

> **Klytos** (Κλυτός) — Epíteto del dios Hefesto: "El Glorioso Creador".
> CMS controlado por IA a través de MCP.
> Software propietario con licencia comercial.

---

## 0. Contexto

Este documento describe las funcionalidades nuevas de Klytos v2.0, que se construyen sobre la arquitectura v1.0 ya implementada. Todo lo descrito en `KLYTOS-ARCHITECTURE.md` (v1.0) sigue vigente: encriptación AES-256-GCM, sistema de licencias, MCP Streamable HTTP, panel admin multilingüe, build estático, etc.

La v2.0 introduce cinco pilares nuevos:

1. **Base de datos MySQL/MariaDB** (opcional, con fallback a flat-file)
2. **Sistema de plugins con hooks** (extensibilidad nativa)
3. **Sistema de tareas y anotaciones** desde el front
4. **Multi-usuario con roles** y edición de contenido en el front/back
5. **Modelo de negocio: Core + Plugins premium** (backups en la nube como primer plugin de pago)

Además se añaden funcionalidades al core: analytics integrado, formularios con almacenamiento, historial de versiones de páginas, staging/preview, webhooks y CLI.

### 0.1 Modelo de Negocio

    KLYTOS CORE (licencia base):
      ✅ CMS flat-file / MySQL
      ✅ Control total por IA vía MCP
      ✅ Multi-usuario con roles
      ✅ Sistema de tareas y anotaciones
      ✅ Editor visual (TipTap) + editor inline
      ✅ Historial de versiones
      ✅ Staging / Preview
      ✅ Analytics privacy-first
      ✅ Formularios con almacenamiento
      ✅ Webhooks
      ✅ CLI
      ✅ Backups locales
      ✅ Sistema de plugins (loader + hooks)

    PLUGINS PREMIUM (licencia adicional):
      💰 Klytos Cloud Backup — Dropbox, Google Drive, OneDrive
      💰 (futuros plugins premium)

- **Web del proyecto**: https://klytos.io
- **Repositorio privado**: https://github.com/joseconti/klytos
- **Licencia**: Propietaria (comercial, no open source)
- **Servidor de licencias**: https://plugins.joseconti.com
- **item_name core**: `Klytos`
- **item_name backup plugin**: `Klytos Cloud Backup`

---

## 1. Base de Datos MySQL/MariaDB (Opcional)

### 1.1 Filosofía: Dual Storage

Klytos v2.0 mantiene la capacidad de funcionar sin base de datos (flat-file puro), pero ofrece MySQL/MariaDB como opción para instalaciones que necesiten escalar. La decisión se toma durante la instalación y se puede migrar después.

### 1.2 Capa de Abstracción — StorageInterface

```php
interface StorageInterface {
    public function read(string $collection, string $id): ?array;
    public function write(string $collection, string $id, array $data): void;
    public function delete(string $collection, string $id): void;
    public function list(string $collection, array $filters = [], int $limit = 50, int $offset = 0): array;
    public function count(string $collection, array $filters = []): int;
    public function exists(string $collection, string $id): bool;
    public function search(string $collection, string $query, array $fields = []): array;
    public function transaction(callable $callback): mixed;
}
```

Dos implementaciones: `FileStorage` (JSON encriptado en data/) y `DatabaseStorage` (PDO con datos encriptados en columna `data`).

### 1.3 Esquema de Base de Datos

Tablas con prefijo configurable (kly_ por defecto):

- `kly_pages` — Páginas (slug como PK, data encriptado, status, lang)
- `kly_config` — Configuración (site, theme, menus, etc.)
- `kly_users` — Usuarios con roles (owner, admin, editor, viewer)
- `kly_tasks` — Tareas y anotaciones del front
- `kly_page_versions` — Historial de versiones de cada página
- `kly_analytics` — Datos de analytics (pageviews anonimizados)
- `kly_form_submissions` — Envíos de formularios
- `kly_audit_log` — Log de actividad de usuarios
- `kly_webhooks` — Configuración de webhooks
- `kly_plugins` — Estado y licencias de plugins instalados

Todos los campos `data` son LONGTEXT con JSON encriptado AES-256-GCM. Los campos de índice (status, lang, etc.) se duplican en columnas separadas para poder filtrar sin desencriptar.

### 1.4 Migración Flat-File → MySQL

Desde admin/settings.php: solicitar credenciales → probar conexión → crear tablas → migrar datos → verificar integridad. Reversible en cualquier momento cambiando `storage_driver` en config.

---

## 2. Sistema de Plugins con Hooks

### 2.1 Filosofía

Klytos incluye un sistema de plugins propio, ligero, inspirado en WordPress pero sin su complejidad. Permite plugins propios premium y futuros plugins de terceros.

El sistema tiene dos mecanismos: **actions** (ejecutar código en puntos clave) y **filters** (modificar datos antes de usarlos).

### 2.2 Clase Hooks.php — Motor de Hooks

```php
class Hooks {
    private static array $actions = [];
    private static array $filters = [];

    // --- ACTIONS ---
    public static function addAction(string $hook, callable $callback, int $priority = 10): void;
    public static function doAction(string $hook, mixed ...$args): void;

    // --- FILTERS ---
    public static function addFilter(string $hook, callable $callback, int $priority = 10): void;
    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed;

    // --- UTILIDADES ---
    public static function hasAction(string $hook): bool;
    public static function hasFilter(string $hook): bool;
    public static function removeAction(string $hook, callable $callback): void;
    public static function removeFilter(string $hook, callable $callback): void;
    public static function removeAllActions(string $hook): void;
    public static function removeAllFilters(string $hook): void;
}
```

### 2.3 Funciones Helper Globales

```php
function klytos_add_action(string $hook, callable $callback, int $priority = 10): void;
function klytos_do_action(string $hook, mixed ...$args): void;
function klytos_add_filter(string $hook, callable $callback, int $priority = 10): void;
function klytos_apply_filters(string $hook, mixed $value, mixed ...$args): mixed;
```

### 2.4 Hooks Disponibles en el Core

#### Actions (puntos de ejecución)

| Hook | Cuándo se ejecuta | Args |
|------|-------------------|------|
| `klytos.init` | Al arrancar la aplicación | `$app` |
| `klytos.shutdown` | Al finalizar el request | — |
| `page.before_save` | Antes de guardar una página | `$slug, $data` |
| `page.after_save` | Después de guardar una página | `$slug, $data` |
| `page.before_delete` | Antes de eliminar una página | `$slug` |
| `page.after_delete` | Después de eliminar una página | `$slug` |
| `theme.after_update` | Después de actualizar el tema | `$themeData` |
| `menu.after_update` | Después de actualizar el menú | `$menuData` |
| `build.before` | Antes de iniciar un build | `$target` |
| `build.after` | Después de un build exitoso | `$target, $stats` |
| `build.page` | Después de generar una página | `$slug, $html` |
| `user.login` | Después de un login exitoso | `$userId, $username` |
| `user.logout` | Después de un logout | `$userId` |
| `user.created` | Después de crear un usuario | `$userId, $userData` |
| `task.created` | Después de crear una tarea | `$taskId, $taskData` |
| `task.completed` | Después de completar una tarea | `$taskId` |
| `form.submitted` | Después de recibir un formulario | `$formId, $data` |
| `backup.before` | Antes de iniciar un backup | `$provider` |
| `backup.after` | Después de un backup exitoso | `$provider, $data` |
| `backup.failed` | Después de un backup fallido | `$provider, $error` |
| `mcp.request` | Al recibir un request MCP | `$method, $params` |
| `mcp.tool_called` | Después de ejecutar un tool MCP | `$toolName, $result` |
| `update.available` | Al detectar una actualización | `$newVersion` |
| `cron.run` | Al ejecutar una tarea programada | `$taskName` |
| `plugin.activated` | Después de activar un plugin | `$pluginId` |
| `plugin.deactivated` | Después de desactivar un plugin | `$pluginId` |

#### Filters (modificación de datos)

| Hook | Qué filtra | Tipo |
|------|-----------|------|
| `page.content` | HTML de la página antes de guardar | `string` |
| `page.meta` | Metadatos de la página | `array` |
| `page.rendered_html` | HTML final renderizado (build) | `string` |
| `theme.css_variables` | Variables CSS generadas | `string` |
| `theme.custom_css` | CSS custom del tema | `string` |
| `menu.items` | Items del menú antes de renderizar | `array` |
| `menu.html` | HTML del menú generado | `string` |
| `build.robots_txt` | Contenido del robots.txt | `string` |
| `build.sitemap_urls` | URLs del sitemap.xml | `array` |
| `build.head_html` | HTML extra en `<head>` | `string` |
| `build.body_end_html` | HTML extra antes de `</body>` | `string` |
| `template.variables` | Variables disponibles en templates | `array` |
| `analytics.event` | Datos del evento de analytics | `array` |
| `form.submission_data` | Datos del formulario antes de guardar | `array` |
| `admin.sidebar_items` | Items del menú lateral del admin | `array` |
| `admin.dashboard_widgets` | Widgets del dashboard | `array` |
| `mcp.tools_list` | Lista de tools MCP registrados | `array` |
| `mcp.tool_response` | Respuesta de un tool MCP | `array` |

### 2.5 Clase PluginLoader.php

```php
class PluginLoader {
    private StorageInterface $storage;
    private string $pluginsDir;
    private array $loaded = [];

    public function __construct(StorageInterface $storage, string $pluginsDir);
    public function loadAll(): void;          // Escanear y cargar plugins activos
    public function load(string $pluginId): bool;  // Cargar plugin individual
    public function activate(string $pluginId): array;
    public function deactivate(string $pluginId): array;
    public function listAll(): array;
    public function getActivePlugins(): array;
    public function getManifest(string $pluginId): ?array;
    private function verifyPluginLicense(string $pluginId, array $manifest): bool;
}
```

Flujo de carga: escanea `plugins/`, lee `klytos-plugin.json`, verifica compatibilidad y licencia (si premium), ejecuta `init.php`.

### 2.6 Estructura de un Plugin

```
plugins/
└── cloud-backup/
    ├── klytos-plugin.json           ← Manifiesto (OBLIGATORIO)
    ├── init.php                     ← Entry point (OBLIGATORIO)
    ├── install.php                  ← Se ejecuta al activar (opcional)
    ├── deactivate.php               ← Se ejecuta al desactivar (opcional)
    ├── uninstall.php                ← Se ejecuta al eliminar (opcional)
    ├── admin/                       ← Vistas del admin (opcional)
    │   └── settings.php
    ├── assets/                      ← CSS/JS del plugin (opcional)
    └── src/                         ← Código PHP del plugin
        ├── CloudBackupManager.php
        ├── BackupProviderInterface.php
        ├── DropboxProvider.php
        ├── GoogleDriveProvider.php
        └── OneDriveProvider.php
```

### 2.7 Manifiesto — klytos-plugin.json

```json
{
  "id": "cloud-backup",
  "name": "Klytos Cloud Backup",
  "description": "Copias de seguridad automáticas en Dropbox, Google Drive y OneDrive",
  "version": "1.0.0",
  "author": "José Conti",
  "author_url": "https://joseconti.com",
  "premium": true,
  "item_name": "Klytos Cloud Backup",
  "requires_klytos": "2.0.0",
  "requires_php": "8.0",
  "license_server": "https://plugins.joseconti.com",
  "update_server": "https://plugins.joseconti.com",
  "permissions": ["backup.manage", "settings.read"],
  "admin_page": {
    "title": "Cloud Backup",
    "slug": "cloud-backup",
    "icon": "☁️",
    "parent": "settings"
  },
  "mcp_tools": [
    "klytos_cloud_backup_create",
    "klytos_cloud_backup_list",
    "klytos_cloud_backup_restore",
    "klytos_cloud_backup_status"
  ]
}
```

### 2.8 Cómo un Plugin Registra Tools MCP

El plugin usa el filter `mcp.tools_list` para inyectar sus tools en el catálogo MCP:

```php
// En init.php del plugin
klytos_add_filter('mcp.tools_list', function (array $tools): array {
    $tools[] = [
        'name' => 'klytos_cloud_backup_create',
        'description' => 'Create a backup to a cloud provider',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'provider' => [
                    'type' => 'string',
                    'enum' => ['dropbox', 'google_drive', 'onedrive'],
                ],
            ],
            'required' => ['provider'],
        ],
    ];
    // ... más tools
    return $tools;
});

// Y usa mcp.tool_called para manejar la ejecución
klytos_add_action('mcp.tool_called', function (string $toolName, &$result) {
    if (!str_starts_with($toolName, 'klytos_cloud_backup_')) return;
    $manager = new CloudBackupManager(klytos_storage());
    // ... ejecutar según toolName
});
```

### 2.9 Licencias de Plugins Premium

Los plugins premium usan el mismo servidor (`plugins.joseconti.com`) con `item_name` diferente al core:

```
POST https://plugins.joseconti.com/?wc-api=lm-license-api
  action    = activate_license
  license   = KLYTOS-CB-XXXX-XXXX
  item_name = Klytos Cloud Backup      ← Diferente del core
```

Las actualizaciones también van por separado con su propio `item_name` y `slug`.

### 2.10 Panel Admin → Sección "Plugins"

```
/admin/plugins.php

┌──────────────────────────────────────────────────────────────────┐
│  Plugins                                                          │
├──────────────────────────────────────────────────────────────────┤
│  ☁️ Klytos Cloud Backup               v1.0.0        ✅ Activo    │
│     Backups en Dropbox, Drive, OneDrive                           │
│     Licencia: KLYTOS-CB-XXXX-XXXX       ✅ Válida                │
│     [Configurar]  [Desactivar]                                    │
│                                                                   │
│  [+ Subir plugin (.zip)]                                          │
│  [🔍 Ver plugins disponibles → klytos.io/plugins]                │
└──────────────────────────────────────────────────────────────────┘
```

### 2.11 Integración en App.php

```php
class App {
    public function boot(): void {
        // 1-3. Config, encryption, storage, license, i18n (existente)
        
        // 4. NUEVO: Inicializar hooks
        require_once __DIR__ . '/core/Hooks.php';
        require_once __DIR__ . '/core/PluginLoader.php';

        // 5. NUEVO: Cargar plugins activos
        $this->plugins = new PluginLoader($this->storage, KLYTOS_ROOT . '/plugins');
        $this->plugins->loadAll();

        // 6. NUEVO: Disparar hook de inicio
        klytos_do_action('klytos.init', $this);

        // 7. Routing (existente, ahora con hooks)
    }
}
```

---

## 3. Sistema Multi-Usuario

### 3.1 Roles y Permisos

| Permiso | Owner | Admin | Editor | Viewer |
|---------|-------|-------|--------|--------|
| Ver dashboard | ✅ | ✅ | ✅ | ✅ |
| Ver páginas | ✅ | ✅ | ✅ | ✅ |
| Crear/editar páginas | ✅ | ✅ | ✅ | ❌ |
| Eliminar páginas | ✅ | ✅ | ❌ | ❌ |
| Gestionar tema | ✅ | ✅ | ❌ | ❌ |
| Gestionar menú | ✅ | ✅ | ✅ | ❌ |
| Build sitio | ✅ | ✅ | ✅ | ❌ |
| Gestionar assets | ✅ | ✅ | ✅ | ❌ |
| Crear/gestionar tareas | ✅ | ✅ | ✅ | ✅ |
| Gestionar usuarios | ✅ | ✅ | ❌ | ❌ |
| Gestionar MCP tokens | ✅ | ✅ | ❌ | ❌ |
| Configuración del sitio | ✅ | ✅ | ❌ | ❌ |
| Licencia y actualizaciones | ✅ | ❌ | ❌ | ❌ |
| Gestionar plugins | ✅ | ✅ | ❌ | ❌ |
| Ver analytics | ✅ | ✅ | ✅ | ✅ |
| Gestionar formularios | ✅ | ✅ | ✅ | ❌ |
| Gestionar webhooks | ✅ | ✅ | ❌ | ❌ |
| Eliminar cuenta de otros | ✅ | ❌ | ❌ | ❌ |
| Transferir ownership | ✅ | ❌ | ❌ | ❌ |

### 3.2 Clase UserManager.php

```php
class UserManager {
    public function create(array $userData): array;
    public function update(int $userId, array $fields): array;
    public function delete(int $userId): bool;
    public function getById(int $userId): ?array;
    public function getByUsername(string $username): ?array;
    public function list(array $filters = []): array;
    public function authenticate(string $username, string $password): ?array;
    public function changePassword(int $userId, string $newPassword): void;
    public function hasPermission(int $userId, string $permission): bool;
    public function transferOwnership(int $fromUserId, int $toUserId): void;
}
```

### 3.3 Audit Log

Cada acción queda registrada con: user_id, action (ej: `page.update`), entity_type, entity_id, details (campos cambiados), source (`admin`, `mcp`, `front_editor`, `plugin`, `cli`), ip_address, timestamp.

---

## 4. Sistema de Tareas y Anotaciones

### 4.1 Concepto

El usuario navega por el sitio generado y activa un "modo revisión" que permite hacer clic en cualquier elemento, dejar una nota, y guardarla como tarea. El agente IA consulta las tareas pendientes vía MCP y las ejecuta.

### 4.2 Widget de Anotación

Se activa con `?klytos_review=1` o cookie de sesión. Inyecta `klytos-review.js` que permite seleccionar elementos, describir el cambio deseado y asignar prioridad. Las tareas almacenan: page_slug, CSS selector, descripción, prioridad, created_by.

### 4.3 Barra de Revisión (fixed bottom)

Solo visible para usuarios con rol editor o superior. Muestra contador de tareas pendientes y botones para anotar, ver tareas y activar editor inline.

### 4.4 Tools MCP para Tareas

| Tool | Descripción |
|------|-------------|
| `klytos_list_tasks` | Lista tareas con filtros (status, priority, page) |
| `klytos_get_task` | Detalle completo de una tarea |
| `klytos_update_task` | Actualizar estado o asignación |
| `klytos_complete_task` | Marcar tarea como completada |

---

## 5. Editor de Contenido

### 5.1 Editor en el Admin (Back) — TipTap

WYSIWYG con toolbar limpio, toggle HTML/visual, inserción de imágenes desde assets, links internos con autocompletado, auto-save cada 60s, historial de versiones integrado.

### 5.2 Editor Inline en el Front

Se activa con `?klytos_edit=1`. Los elementos de texto se vuelven editables (contenteditable). Mini toolbar flotante (B, I, Link). Los cambios se envían al servidor via `/admin/api/inline-edit.php` y se regenera el HTML estático.

### 5.3 API Interna del Admin

- `POST /admin/api/inline-edit.php` — Aplicar cambios inline
- `POST /admin/api/tasks.php` — Crear/listar tareas
- `POST /admin/api/autosave.php` — Auto-guardado del editor

---

## 6. Historial de Versiones de Páginas

Cada guardado (admin, editor inline, MCP) crea una versión automáticamente. Se conservan las últimas N versiones (configurable, default 50).

### 6.1 Clase VersionManager.php

```php
class VersionManager {
    public function save(string $pageSlug, array $pageData, ?int $userId, string $changeType, ?string $summary): int;
    public function get(string $pageSlug, int $version): ?array;
    public function list(string $pageSlug, int $limit = 20): array;
    public function restore(string $pageSlug, int $version): array;
    public function diff(string $pageSlug, int $versionA, int $versionB): array;
    public function prune(string $pageSlug): int;
}
```

### 6.2 Tools MCP

| Tool | Descripción |
|------|-------------|
| `klytos_list_versions` | Lista versiones de una página |
| `klytos_get_version` | Obtiene una versión específica |
| `klytos_restore_version` | Restaura una versión anterior |
| `klytos_diff_versions` | Compara dos versiones |

---

## 7. Staging / Preview

Build a `public/_preview/` con token temporal de 24h. Compartible con clientes para revisión. Promover a producción o descartar con un click / tool MCP.

### 7.1 Tools MCP

| Tool | Descripción |
|------|-------------|
| `klytos_build_preview` | Build a staging |
| `klytos_get_preview_url` | URL temporal del preview |
| `klytos_promote_preview` | Promover a producción |
| `klytos_discard_preview` | Descartar preview |

---

## 8. Analytics Integrado (Privacy-First)

Analytics propio, ~2KB JS, sin cookies, IP anonimizada (hash), GDPR compatible sin banner. Pixel tracker via `t.php`.

### 8.1 Tools MCP

| Tool | Descripción |
|------|-------------|
| `klytos_get_analytics` | Resumen con periodo configurable |
| `klytos_get_top_pages` | Páginas más visitadas |

---

## 9. Sistema de Formularios

La IA genera formularios HTML como parte del contenido. Los envíos van a `form.php` con honeypot anti-spam, rate limit por IP y CSRF.

### 9.1 Tools MCP

| Tool | Descripción |
|------|-------------|
| `klytos_list_forms` | Lista formularios y conteo de envíos |
| `klytos_get_submissions` | Envíos de un formulario |
| `klytos_mark_submission` | Cambiar estado de envío |
| `klytos_export_submissions` | Exportar como CSV |

---

## 10. Webhooks

Eventos del core (build, page, task, form, backup, user, plugin) con payload JSON firmado (HMAC-SHA256). 5 reintentos con backoff exponencial. Los plugins pueden disparar eventos adicionales via `klytos_do_action`.

---

## 11. Backups: Core (Local) vs Plugin (Cloud)

### 11.1 Core — Backups Locales (Gratuito)

```php
class BackupManager {
    public function createLocal(?string $label = null): array;
    public function restoreLocal(string $backupPath): array;
    public function listLocal(): array;
    public function deleteLocal(string $backupPath): bool;
}
```

El core incluye: backup manual a `backups/`, backup automático antes de actualizaciones, restauración local, retención configurable.

El core también expone hooks (`backup.before`, `backup.after`, `backup.failed`, `cron.run`) para que plugins se enganchen.

### 11.2 Tools MCP de Backups (Core)

| Tool | Descripción |
|------|-------------|
| `klytos_create_backup` | Crea backup local |
| `klytos_list_backups` | Lista backups locales |
| `klytos_restore_backup` | Restaura desde backup local |
| `klytos_get_backup_status` | Estado del último backup |

### 11.3 Plugin "Klytos Cloud Backup" (Premium)

Añade: Dropbox, Google Drive, OneDrive via OAuth 2.0 (PKCE), programación automática (diaria/semanal) via pseudo-cron, panel admin dedicado, y 4 tools MCP adicionales registrados via el filter `mcp.tools_list`.

El plugin se vende con su propia licencia (`item_name: "Klytos Cloud Backup"`) en plugins.joseconti.com y se actualiza de forma independiente al core.

### 11.4 Pseudo-Cron (Core)

El core ejecuta en cada request al admin un pseudo-cron que: verifica licencia (cada 7 días), limpia analytics antiguos (cada 24h), y dispara `klytos_do_action('cron.run', 'scheduled')` para que plugins registren sus propias tareas programadas.

---

## 12. CLI

```bash
php klytos/cli.php <comando> [opciones]
```

Comandos: build, pages, tasks, backup, users, analytics, plugins, status, update, cache.

---

## 13. Estructura de Directorios (v2.0 — Nuevos)

```
klytos/
├── ... (todo lo de v1.0)
├── cli.php                         ← CLI entry point
├── form.php                        ← Receptor de formularios
├── t.php                           ← Pixel de analytics
│
├── config/
│   └── database.json.enc           ← Credenciales MySQL (opcional)
│
├── core/
│   ├── Hooks.php                   ← Motor de hooks
│   ├── PluginLoader.php            ← Cargador de plugins
│   ├── StorageInterface.php        ← Interfaz abstracta
│   ├── FileStorage.php             ← Refactor de Storage.php
│   ├── DatabaseStorage.php         ← MySQL/MariaDB
│   ├── UserManager.php
│   ├── TaskManager.php
│   ├── VersionManager.php
│   ├── BackupManager.php           ← Solo backups LOCALES
│   ├── AnalyticsManager.php
│   ├── FormManager.php
│   ├── WebhookManager.php
│   └── MCP/Tools/
│       ├── TaskTools.php
│       ├── UserTools.php
│       ├── VersionTools.php
│       ├── BackupTools.php         ← Solo backups LOCALES
│       ├── AnalyticsTools.php
│       ├── FormTools.php
│       ├── WebhookTools.php
│       ├── PreviewTools.php
│       └── PluginTools.php
│
├── plugins/                        ← Directorio de plugins
│   ├── .htaccess                   ← Deny from all
│   └── cloud-backup/              ← Plugin premium
│       ├── klytos-plugin.json
│       ├── init.php
│       ├── install.php
│       ├── admin/settings.php
│       └── src/
│           ├── CloudBackupManager.php
│           ├── BackupProviderInterface.php
│           ├── DropboxProvider.php
│           ├── GoogleDriveProvider.php
│           └── OneDriveProvider.php
│
├── admin/
│   ├── users.php
│   ├── tasks.php
│   ├── analytics.php
│   ├── forms.php
│   ├── webhooks.php
│   ├── plugins.php
│   ├── edit-page.php
│   └── api/
│       ├── inline-edit.php
│       ├── tasks.php
│       └── autosave.php
│
├── public/js/
│   ├── klytos-review.js
│   ├── klytos-inline-editor.js
│   └── klytos-analytics.js
│
└── data/
    ├── users.json.enc
    ├── tasks.json.enc
    ├── plugins.json.enc
    ├── page_versions/
    ├── analytics/
    ├── forms/
    ├── webhooks.json.enc
    └── audit_log/
```

---

## 14. Resumen de Tools MCP

### Core (53 tools)

**v1.0 (26):** create/update/delete/get/list pages, set/get theme + colors + fonts + layout, set/get/add/remove menu, set/get site_config, upload/list/delete assets, set/list/get templates, build_site/page/preview/status.

**v2.0 nuevos (27):** list/get/update/complete tasks, list/get/restore/diff versions, create/list/restore/status backup (local), build/get_url/promote/discard preview, get_analytics/top_pages, list_forms/get/mark/export submissions, list/create/update users, list/activate/deactivate plugins.

### Plugin "Cloud Backup" (4 tools)

Registrados dinámicamente via filter `mcp.tools_list`: cloud_backup_create, cloud_backup_list, cloud_backup_restore, cloud_backup_status.

---

## 15. Checklist de Implementación v2.0

### Fase 4 — Hooks, Plugins + Almacenamiento Dual

- [ ] `core/Hooks.php` — Motor de actions + filters
- [ ] `core/PluginLoader.php` — Cargador de plugins
- [ ] `core/StorageInterface.php` — Interfaz abstracta
- [ ] `core/FileStorage.php` — Refactorizar Storage.php
- [ ] `core/DatabaseStorage.php` — MySQL/MariaDB
- [ ] Opción MySQL en install.php
- [ ] `admin/plugins.php` — Gestión de plugins
- [ ] `core/MCP/Tools/PluginTools.php`
- [ ] Funciones helper globales
- [ ] Inyectar hooks en core existente
- [ ] `plugins/` directorio con .htaccess

### Fase 5 — Multi-Usuario + Tareas + Editor

- [ ] `core/UserManager.php`
- [ ] `core/Auth.php` actualizado (multi-usuario + permisos)
- [ ] `admin/users.php`
- [ ] `core/AuditLog.php`
- [ ] `core/TaskManager.php`
- [ ] `core/VersionManager.php`
- [ ] `public/js/klytos-review.js`
- [ ] `public/js/klytos-inline-editor.js`
- [ ] `admin/tasks.php`, `admin/edit-page.php`
- [ ] `admin/api/` (inline-edit, tasks, autosave)
- [ ] MCP Tools: Task, Version, Preview, User

### Fase 6 — Analytics, Formularios, Webhooks, Backups locales

- [ ] `core/BackupManager.php` (local)
- [ ] `core/AnalyticsManager.php` + `t.php` + `klytos-analytics.js`
- [ ] `admin/analytics.php`
- [ ] `core/FormManager.php` + `form.php`
- [ ] `admin/forms.php`
- [ ] `core/WebhookManager.php` + `admin/webhooks.php`
- [ ] MCP Tools: Backup (local), Analytics, Form, Webhook

### Fase 7 — Plugin Cloud Backup (Premium)

- [ ] `plugins/cloud-backup/klytos-plugin.json`
- [ ] `plugins/cloud-backup/init.php` con hooks
- [ ] `plugins/cloud-backup/src/` (Manager + Providers)
- [ ] `plugins/cloud-backup/admin/settings.php`
- [ ] Producto en plugins.joseconti.com
- [ ] Pruebas OAuth con Dropbox/Drive/OneDrive

### Fase 8 — CLI + Polish

- [ ] `cli.php` con todos los comandos
- [ ] Pseudo-cron con soporte para plugins
- [ ] Traducciones v2.0 (es, en, ca, fr, de, pt, it)
- [ ] Pruebas migración flat-file ↔ MySQL
- [ ] Pruebas sistema de plugins
- [ ] Documentación desarrollo de plugins
- [ ] Actualización klytos.io

---

## 16. Seguridad v2.0

| Capa | Medida |
|------|--------|
| **Multi-usuario** | Roles granulares, audit log completo |
| **Base de datos** | PDO prepared statements, credenciales encriptadas |
| **Editor inline** | Sesión + CSRF, sanitización HTML (allowlist) |
| **Formularios** | Honeypot, rate limit por IP, CSRF |
| **Analytics** | Sin cookies, IP hash, GDPR compatible |
| **Webhooks** | HMAC-SHA256, retry con backoff |
| **Backups** | Encriptados en reposo (AES-256-GCM) |
| **Preview** | Tokens 24h, noindex/nofollow |
| **Plugins** | Manifiesto validado, licencia verificada, sandbox |
| **API admin** | Rate limiting, roles por endpoint |

---

*Documento extensión de KLYTOS-ARCHITECTURE.md (v1.0).*
*Versión: 2.0.0 — Fecha: 2026-03-26*
