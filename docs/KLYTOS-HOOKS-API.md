# Klytos — Plugin API & Hooks Reference v2.0

> Guía completa para desarrolladores de plugins.
> Define todos los hooks, funciones helper y puntos de extensión.
> Objetivo: flexibilidad tipo WordPress sin la complejidad de WordPress.

---

## 1. Principio

Un plugin de Klytos debe poder, sin tocar el core:

- Registrar páginas completas en el admin
- Añadir sub-secciones a páginas existentes del admin
- Añadir items al menú lateral y al dashboard
- Inyectar CSS y JS propios en el admin y/o en el front
- Registrar nuevos tipos de bloques y page templates
- Registrar nuevos tipos de slot
- Registrar tools MCP adicionales
- Crear endpoints API propios
- Registrar permisos/capabilities personalizados
- Añadir campos a formularios de configuración existentes
- Registrar tareas de cron
- Registrar tablas de base de datos propias
- Modificar cualquier output antes de renderizarlo

Todo esto se hace mediante **actions**, **filters** y **funciones helper** del core.

---

## 2. Funciones Helper Globales

Estas funciones están disponibles globalmente desde el momento en que un plugin se carga (init.php).

### 2.1 Hooks

```php
klytos_add_action(string $hook, callable $callback, int $priority = 10): void;
klytos_do_action(string $hook, mixed ...$args): void;
klytos_add_filter(string $hook, callable $callback, int $priority = 10): void;
klytos_apply_filters(string $hook, mixed $value, mixed ...$args): mixed;
klytos_remove_action(string $hook, callable $callback): void;
klytos_remove_filter(string $hook, callable $callback): void;
klytos_has_action(string $hook): bool;
klytos_has_filter(string $hook): bool;
```

### 2.2 Acceso al Core

```php
// Obtener instancias del core (singleton)
klytos_storage(): StorageInterface;           // Acceso a lectura/escritura de datos
klytos_app(): App;                             // Instancia de la aplicación
klytos_auth(): Auth;                           // Autenticación y permisos
klytos_config(string $key, mixed $default = null): mixed;  // Leer configuración
klytos_url(string $path = ''): string;         // URL base del sitio + path
klytos_admin_url(string $path = ''): string;   // URL del admin + path
klytos_plugin_url(string $pluginId, string $path = ''): string;  // URL assets del plugin
klytos_plugin_path(string $pluginId, string $path = ''): string; // Path físico del plugin
klytos_version(): string;                      // Versión de Klytos
klytos_is_admin(): bool;                       // ¿Estamos en el admin?
klytos_is_mcp(): bool;                         // ¿Estamos en un request MCP?
klytos_is_cli(): bool;                         // ¿Estamos en CLI?
klytos_current_user(): ?array;                 // Usuario actual (null si no logueado)
klytos_has_permission(string $permission): bool; // ¿El usuario actual tiene permiso?
```

### 2.3 I18n

```php
__(string $key, array $replacements = []): string;  // Ya existe en v1.0
klytos_register_translations(string $pluginId, string $langDir): void;  // NUEVO
// El plugin registra su propio directorio de traducciones
// Las claves se prefijan automáticamente: 'cloud-backup.settings.title'
```

### 2.4 Logging

```php
klytos_log(string $level, string $message, array $context = []): void;
// Levels: 'debug', 'info', 'warning', 'error'
// Se escribe en data/logs/{date}.log.enc
```

---

## 3. Hooks de Admin — Completos

### 3.1 Menú Lateral del Admin

```php
// Filter: admin.sidebar_items
// Permite añadir items al menú lateral, con posición, icono, subitems, y badge

klytos_add_filter('admin.sidebar_items', function (array $items): array {

    // Añadir sección principal
    $items[] = [
        'id'         => 'cloud-backup',           // Identificador único
        'title'      => __('cloud-backup.title'),  // Texto visible
        'url'        => klytos_admin_url('plugin/cloud-backup/settings.php'),
        'icon'       => '☁️',                      // Emoji o SVG inline
        'position'   => 85,                        // Orden (dashboard=10, pages=20, theme=30, ...)
        'capability' => 'backup.manage',           // Permiso requerido (null = todos)
        'badge'      => null,                      // Texto de badge (ej: '3' para notificaciones)
        'badge_type' => 'info',                    // 'info', 'warning', 'error'
        'children'   => [                          // Sub-items opcionales
            [
                'id'    => 'cloud-backup-settings',
                'title' => __('cloud-backup.settings'),
                'url'   => klytos_admin_url('plugin/cloud-backup/settings.php'),
            ],
            [
                'id'    => 'cloud-backup-history',
                'title' => __('cloud-backup.history'),
                'url'   => klytos_admin_url('plugin/cloud-backup/history.php'),
            ],
        ],
    ];

    return $items;
});
```

**Posiciones estándar del menú:**

| Posición | Sección |
|----------|---------|
| 10 | Dashboard |
| 20 | Páginas |
| 30 | Tema |
| 40 | Bloques |
| 50 | Templates |
| 55 | Tareas |
| 60 | Formularios |
| 65 | Analytics |
| 70 | Assets |
| 75 | MCP |
| 80 | Usuarios |
| 85-89 | *Zona de plugins* |
| 90 | Configuración |
| 95 | Licencia |
| 98 | Actualizaciones |
| 99 | Plugins |

### 3.2 Dashboard Widgets

```php
// Filter: admin.dashboard_widgets
klytos_add_filter('admin.dashboard_widgets', function (array $widgets): array {
    $widgets[] = [
        'id'         => 'cloud-backup-status',
        'title'      => '☁️ Cloud Backup',
        'position'   => 50,                        // Orden en el dashboard
        'size'       => 'half',                    // 'full', 'half', 'third'
        'capability' => 'backup.manage',
        'render'     => function (): string {      // Callback que retorna HTML
            $lastBackup = getLastBackupInfo();
            return '<div class="klytos-widget">
                <p>Último backup: ' . $lastBackup['date'] . '</p>
                <a href="' . klytos_admin_url('plugin/cloud-backup/settings.php') . '">
                    Gestionar backups
                </a>
            </div>';
        },
    ];
    return $widgets;
});
```

### 3.3 Páginas de Admin Propias del Plugin

Los plugins pueden tener sus propias páginas en `plugins/{id}/admin/`:

```php
// El plugin registra sus rutas de admin
klytos_add_filter('admin.routes', function (array $routes): array {
    $routes[] = [
        'pattern'    => 'plugin/cloud-backup/settings',
        'file'       => klytos_plugin_path('cloud-backup', 'admin/settings.php'),
        'capability' => 'backup.manage',
        'title'      => 'Cloud Backup — Configuración',
    ];
    $routes[] = [
        'pattern'    => 'plugin/cloud-backup/history',
        'file'       => klytos_plugin_path('cloud-backup', 'admin/history.php'),
        'capability' => 'backup.manage',
        'title'      => 'Cloud Backup — Historial',
    ];
    $routes[] = [
        'pattern'    => 'plugin/cloud-backup/oauth-callback',
        'file'       => klytos_plugin_path('cloud-backup', 'admin/oauth-callback.php'),
        'capability' => 'backup.manage',
        'title'      => 'OAuth Callback',
    ];
    return $routes;
});
```

La página del plugin recibe el layout del admin (header, sidebar, footer) automáticamente:

```php
// plugins/cloud-backup/admin/settings.php
<?php
// Este archivo se incluye dentro del layout del admin
// Tiene acceso a todas las funciones helper de Klytos
// El header, sidebar y footer del admin ya están renderizados

$auth = klytos_auth();
$auth->requirePermission('backup.manage');
?>

<div class="klytos-admin-content">
    <h1><?= __('cloud-backup.settings.title') ?></h1>
    
    <!-- Formulario de configuración del plugin -->
    <form method="POST" action="">
        <input type="hidden" name="_csrf" value="<?= $auth->csrfToken() ?>">
        <!-- ... campos del plugin ... -->
        <button type="submit"><?= __('common.save') ?></button>
    </form>
</div>
```

### 3.4 Sub-Secciones en Páginas Existentes del Admin

Un plugin puede inyectar HTML en puntos específicos de páginas del admin que ya existen:

```php
// Action: admin.settings.before_form — antes del formulario de configuración
// Action: admin.settings.after_form — después del formulario
// Action: admin.settings.section_{name} — dentro de una sección específica
// Action: admin.page_editor.sidebar — sidebar del editor de páginas
// Action: admin.page_editor.below_content — debajo del editor
// Action: admin.dashboard.top — arriba del dashboard
// Action: admin.dashboard.bottom — abajo del dashboard
// Action: admin.users.after_list — después de la lista de usuarios
// Action: admin.head — dentro de <head> del admin (para CSS/meta)
// Action: admin.footer — antes de </body> del admin (para JS)

// Ejemplo: añadir campo al formulario de configuración del sitio
klytos_add_action('admin.settings.after_form', function () {
    if (!klytos_has_permission('backup.manage')) return;
    ?>
    <fieldset class="klytos-fieldset">
        <legend><?= __('cloud-backup.auto_backup_setting') ?></legend>
        <label>
            <input type="checkbox" name="auto_backup_on_update" value="1"
                <?= klytos_config('cloud-backup.auto_backup_on_update') ? 'checked' : '' ?>>
            <?= __('cloud-backup.backup_before_updates') ?>
        </label>
    </fieldset>
    <?php
});

// Ejemplo: añadir pestaña al editor de páginas
klytos_add_filter('admin.page_editor.tabs', function (array $tabs): array {
    $tabs[] = [
        'id'    => 'seo-analysis',
        'title' => 'SEO',
        'icon'  => '🔍',
        'render' => function (array $page): string {
            // Retorna HTML del contenido de la pestaña
            return '<div class="seo-panel">...</div>';
        },
    ];
    return $tabs;
});
```

### 3.5 Enqueue de CSS y JS en el Admin

```php
// Filter: admin.styles — CSS del admin
klytos_add_filter('admin.styles', function (array $styles): array {
    $styles[] = [
        'id'   => 'cloud-backup-admin',
        'url'  => klytos_plugin_url('cloud-backup', 'assets/admin.css'),
        'page' => 'plugin/cloud-backup/*',   // Solo en páginas del plugin (null = todas)
    ];
    return $styles;
});

// Filter: admin.scripts — JS del admin
klytos_add_filter('admin.scripts', function (array $scripts): array {
    $scripts[] = [
        'id'     => 'cloud-backup-admin',
        'url'    => klytos_plugin_url('cloud-backup', 'assets/admin.js'),
        'page'   => 'plugin/cloud-backup/*',
        'defer'  => true,
        'data'   => [                          // Datos inyectados como JSON en window.klytosPluginData
            'apiUrl' => klytos_admin_url('api/plugin/cloud-backup'),
            'nonce'  => klytos_auth()->csrfToken(),
        ],
    ];
    return $scripts;
});
```

### 3.6 Enqueue de CSS y JS en el Front (Sitio Público)

```php
// Filter: build.head_html — inyectar en <head> del sitio generado
klytos_add_filter('build.head_html', function (string $html): string {
    $html .= '<link rel="stylesheet" href="/plugins/my-plugin/assets/front.css">';
    return $html;
});

// Filter: build.body_end_html — inyectar antes de </body>
klytos_add_filter('build.body_end_html', function (string $html): string {
    $html .= '<script src="/plugins/my-plugin/assets/front.js" defer></script>';
    return $html;
});
```

---

## 4. Hooks de Permisos y Capabilities

### 4.1 Registrar Capabilities Personalizadas

```php
// Filter: auth.capabilities — añadir capabilities propias
klytos_add_filter('auth.capabilities', function (array $capabilities): array {
    // Añadir capabilities del plugin
    $capabilities['backup.manage']  = ['owner', 'admin'];           // Solo owner y admin
    $capabilities['backup.view']    = ['owner', 'admin', 'editor']; // También editores
    $capabilities['backup.restore'] = ['owner'];                     // Solo owner

    return $capabilities;
});

// Uso en el plugin:
if (klytos_has_permission('backup.manage')) {
    // Mostrar botón de backup
}
```

### 4.2 Capabilities Estándar del Core

| Capability | Owner | Admin | Editor | Viewer |
|-----------|-------|-------|--------|--------|
| `pages.view` | ✅ | ✅ | ✅ | ✅ |
| `pages.create` | ✅ | ✅ | ✅ | ❌ |
| `pages.edit` | ✅ | ✅ | ✅ | ❌ |
| `pages.delete` | ✅ | ✅ | ❌ | ❌ |
| `theme.manage` | ✅ | ✅ | ❌ | ❌ |
| `menu.manage` | ✅ | ✅ | ✅ | ❌ |
| `blocks.manage` | ✅ | ✅ | ❌ | ❌ |
| `templates.manage` | ✅ | ✅ | ❌ | ❌ |
| `templates.approve` | ✅ | ✅ | ❌ | ❌ |
| `build.run` | ✅ | ✅ | ✅ | ❌ |
| `assets.manage` | ✅ | ✅ | ✅ | ❌ |
| `tasks.create` | ✅ | ✅ | ✅ | ✅ |
| `tasks.manage` | ✅ | ✅ | ✅ | ❌ |
| `users.manage` | ✅ | ✅ | ❌ | ❌ |
| `mcp.manage` | ✅ | ✅ | ❌ | ❌ |
| `site.configure` | ✅ | ✅ | ❌ | ❌ |
| `license.manage` | ✅ | ❌ | ❌ | ❌ |
| `plugins.manage` | ✅ | ✅ | ❌ | ❌ |
| `analytics.view` | ✅ | ✅ | ✅ | ✅ |
| `forms.manage` | ✅ | ✅ | ✅ | ❌ |
| `webhooks.manage` | ✅ | ✅ | ❌ | ❌ |
| `updates.manage` | ✅ | ❌ | ❌ | ❌ |

Los plugins añaden las suyas via `auth.capabilities`.

---

## 5. Hooks de API — Endpoints Propios

### 5.1 Endpoints API del Admin

```php
// Filter: admin.api_routes — registrar endpoints API propios
klytos_add_filter('admin.api_routes', function (array $routes): array {
    
    // POST /admin/api/plugin/cloud-backup/trigger
    $routes[] = [
        'method'     => 'POST',
        'pattern'    => 'plugin/cloud-backup/trigger',
        'callback'   => function (array $request): array {
            klytos_auth()->requirePermission('backup.manage');
            $provider = $request['provider'] ?? 'local';
            $manager = new CloudBackupManager(klytos_storage());
            return $manager->create($provider);
        },
        'capability' => 'backup.manage',
    ];

    // GET /admin/api/plugin/cloud-backup/status
    $routes[] = [
        'method'     => 'GET',
        'pattern'    => 'plugin/cloud-backup/status',
        'callback'   => function (array $request): array {
            return ['last_backup' => getLastBackupInfo()];
        },
        'capability' => 'backup.view',
    ];

    return $routes;
});
```

Los endpoints se acceden via AJAX desde JS del plugin:

```javascript
// En el JS del plugin
const response = await fetch('/klytos/admin/api/plugin/cloud-backup/trigger', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.klytosPluginData.nonce,
    },
    body: JSON.stringify({ provider: 'dropbox' }),
});
```

---

## 6. Hooks de MCP — Tools Dinámicos

### 6.1 Registrar Tools MCP

```php
// Filter: mcp.tools_list — añadir tools al catálogo MCP
klytos_add_filter('mcp.tools_list', function (array $tools): array {
    $tools[] = [
        'name'        => 'klytos_cloud_backup_create',
        'description' => 'Create a cloud backup to Dropbox, Google Drive or OneDrive',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'provider' => [
                    'type' => 'string',
                    'enum' => ['dropbox', 'google_drive', 'onedrive'],
                    'description' => 'Cloud storage provider',
                ],
            ],
            'required' => ['provider'],
        ],
        'annotations' => [
            'title' => 'Create Cloud Backup',
            'readOnlyHint' => false,
            'destructiveHint' => true,
            'idempotentHint' => true,
            'openWorldHint' => true,
        ],
    ];
    return $tools;
});
```

### 6.2 Manejar Ejecución de Tools

```php
// Filter: mcp.handle_tool — interceptar la ejecución de tools propios
klytos_add_filter('mcp.handle_tool', function (?array $result, string $toolName, array $params): ?array {
    // Si no es nuestro tool, devolver null para que el core lo maneje
    if (!str_starts_with($toolName, 'klytos_cloud_backup_')) return $result;

    $manager = new CloudBackupManager(klytos_storage());

    return match($toolName) {
        'klytos_cloud_backup_create'  => $manager->create($params['provider']),
        'klytos_cloud_backup_list'    => $manager->list($params['provider'] ?? 'all'),
        'klytos_cloud_backup_restore' => $manager->restore($params['backup_id']),
        'klytos_cloud_backup_status'  => $manager->getLastBackup(),
        default => null,
    };
}, 10);
```

### 6.3 Modificar Respuestas de Tools Existentes

```php
// Filter: mcp.tool_response — modificar respuesta antes de enviar
klytos_add_filter('mcp.tool_response', function (array $response, string $toolName): array {
    // Añadir info de backup al build status
    if ($toolName === 'klytos_get_build_status') {
        $response['last_cloud_backup'] = getLastBackupInfo();
    }
    return $response;
}, 10);
```

---

## 7. Hooks de Contenido y Build

### 7.1 Ciclo de Vida de una Página

```
page.before_validate  →  Antes de validar datos de la página
page.after_validate   →  Después de validar (se puede rechazar)
page.before_save      →  Antes de escribir al storage
page.after_save       →  Después de guardar
page.before_delete    →  Antes de eliminar
page.after_delete     →  Después de eliminar
```

### 7.2 Ciclo de Vida del Build

```
build.before          →  Antes de iniciar el build
build.css_generated   →  Después de generar CSS (se puede modificar)
build.js_generated    →  Después de generar JS
build.global_blocks   →  Después de renderizar bloques globales (filter)
build.before_page     →  Antes de renderizar cada página
build.after_page      →  Después de renderizar cada página
build.after           →  Después de completar el build
```

### 7.3 Filtros de Renderizado

```
page.content                →  Contenido antes de guardar (sanitización)
page.meta                   →  Metadatos de la página
block.rendered_html         →  HTML de un bloque después de renderizar
block.css                   →  CSS de un bloque
page.rendered_html          →  HTML final completo de la página
template.variables          →  Variables disponibles en templates
theme.css_variables         →  CSS custom properties (:root)
theme.custom_css            →  CSS custom del tema
menu.items                  →  Items del menú antes de renderizar
menu.html                   →  HTML del menú generado
build.head_html             →  HTML inyectado en <head>
build.body_end_html         →  HTML inyectado antes de </body>
build.robots_txt            →  Contenido del robots.txt
build.sitemap_urls          →  URLs del sitemap.xml
```

---

## 8. Hooks de Bloques y Templates

### 8.1 Extensión de Tipos

```php
// Filter: block.available_types — registrar tipos de bloque nuevos
klytos_add_filter('block.available_types', function (array $types): array {
    $types[] = [
        'id'          => 'product-info',
        'name'        => 'Producto Simple',
        'category'    => 'commerce',
        'description' => 'Ficha de producto con imagen, precio y CTA',
        'slots'       => [...],
        'html'        => '...',
        'css'         => '...',
        'js'          => '...',
        'sample_data' => [...],
    ];
    return $types;
});

// Filter: block.slot_types — registrar tipos de slot nuevos
klytos_add_filter('block.slot_types', function (array $types): array {
    $types['price'] = [
        'label'      => 'Precio',
        'validation' => function ($value) { return is_numeric($value); },
        'render'     => function ($value, $context) {
            $currency = $context['currency'] ?? 'EUR';
            return '<span class="klytos-price">' . number_format($value, 2) . ' ' . $currency . '</span>';
        },
    ];
    $types['variant-selector'] = [...];
    return $types;
});

// Filter: page_template.available_types — registrar page templates nuevos
klytos_add_filter('page_template.available_types', function (array $templates): array {
    $templates[] = [
        'type'      => 'product',
        'name'      => 'Producto Simple',
        'structure' => [
            ['block' => 'top-bar',      'scope' => 'global'],
            ['block' => 'header',       'scope' => 'global'],
            ['block' => 'breadcrumb',   'scope' => 'template'],
            ['block' => 'product-info', 'scope' => 'page', 'required' => true],
            ['block' => 'footer',       'scope' => 'global'],
        ],
    ];
    return $templates;
});
```

---

## 9. Hooks de Configuración

### 9.1 Añadir Campos a Configuración del Sitio

```php
// Filter: settings.sections — añadir sección entera a configuración
klytos_add_filter('settings.sections', function (array $sections): array {
    $sections[] = [
        'id'         => 'cloud-backup',
        'title'      => __('cloud-backup.settings.title'),
        'capability' => 'backup.manage',
        'fields'     => [
            [
                'id'      => 'backup_frequency',
                'type'    => 'select',
                'label'   => __('cloud-backup.frequency'),
                'options' => ['daily' => 'Diaria', 'weekly' => 'Semanal', 'monthly' => 'Mensual'],
                'default' => 'daily',
            ],
            [
                'id'      => 'backup_retention_days',
                'type'    => 'number',
                'label'   => __('cloud-backup.retention'),
                'min'     => 7,
                'max'     => 365,
                'default' => 30,
            ],
            [
                'id'      => 'backup_notify_email',
                'type'    => 'email',
                'label'   => __('cloud-backup.notify_email'),
            ],
        ],
    ];
    return $sections;
});

// Action: settings.save — cuando se guardan los settings
klytos_add_action('settings.saved', function (array $settings) {
    // El plugin puede reaccionar a cambios en la configuración
    if (isset($settings['cloud-backup'])) {
        updateBackupSchedule($settings['cloud-backup']);
    }
});
```

### 9.2 Configuración Propia del Plugin

```php
// Leer/escribir config propia del plugin (prefijada automáticamente)
$value = klytos_config('cloud-backup.frequency', 'daily');
klytos_set_config('cloud-backup.frequency', 'weekly');

// Internamente se guarda en data/plugin-settings/cloud-backup.json.enc
// o en kly_config con id = 'plugin:cloud-backup'
```

---

## 10. Hooks de Cron y Tareas Programadas

```php
// Filter: cron.tasks — registrar tarea programada
klytos_add_filter('cron.tasks', function (array $tasks): array {
    $tasks[] = [
        'id'         => 'cloud_backup_scheduled',
        'callback'   => function () {
            $manager = new CloudBackupManager(klytos_storage());
            $manager->runScheduled();
        },
        'interval'   => klytos_config('cloud-backup.frequency', 'daily'),
        'capability' => 'backup.manage',
    ];
    return $tasks;
});

// Los intervalos válidos: 'hourly', 'daily', 'weekly', 'monthly'
// El pseudo-cron del core ejecuta las tareas según su intervalo
```

---

## 11. Hooks de Base de Datos

### 11.1 Registrar Tablas Propias

```php
// Action: database.install — crear tablas al activar el plugin (solo MySQL mode)
klytos_add_action('plugin.activated', function (string $pluginId) {
    if ($pluginId !== 'cloud-backup') return;
    if (klytos_config('storage_driver') !== 'database') return;

    $pdo = klytos_storage()->getPdo(); // Solo disponible en DatabaseStorage
    $prefix = klytos_config('database.prefix', 'kly_');

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}cloud_backups (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        provider    VARCHAR(32) NOT NULL,
        remote_path VARCHAR(512),
        file_size   BIGINT UNSIGNED,
        status      ENUM('pending','uploading','completed','failed') DEFAULT 'pending',
        error       TEXT,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_provider_date (provider, created_at DESC)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
});

// Action: database.uninstall — limpiar al desinstalar
klytos_add_action('plugin.uninstalled', function (string $pluginId) {
    if ($pluginId !== 'cloud-backup') return;
    if (klytos_config('storage_driver') !== 'database') return;

    $pdo = klytos_storage()->getPdo();
    $prefix = klytos_config('database.prefix', 'kly_');
    $pdo->exec("DROP TABLE IF EXISTS {$prefix}cloud_backups");
});
```

---

## 12. Hooks de Webhooks y Eventos

```php
// Filter: webhooks.events — registrar eventos propios
klytos_add_filter('webhooks.events', function (array $events): array {
    $events['backup.cloud.completed'] = 'Cloud backup completado';
    $events['backup.cloud.failed']    = 'Cloud backup fallido';
    return $events;
});

// Disparar evento propio
klytos_do_action('webhook.trigger', 'backup.cloud.completed', [
    'provider'  => 'dropbox',
    'file_size' => 124000000,
    'path'      => '/Apps/Klytos/backup-2026-03-26.zip',
]);
```

---

## 13. Hooks del Front (Sitio Público)

```php
// Filter: front.authenticated_tools — herramientas visibles para usuarios logueados
klytos_add_filter('front.review_bar_buttons', function (array $buttons): array {
    $buttons[] = [
        'id'    => 'seo-check',
        'label' => '🔍 SEO',
        'icon'  => '🔍',
        'onclick' => 'KlytosSeoPlugin.analyze()',  // JS del plugin
    ];
    return $buttons;
});
```

---

## 14. Resumen: Todos los Hooks

### Actions (32)

| Hook | Cuándo |
|------|--------|
| `klytos.init` | App arrancada |
| `klytos.shutdown` | Request finalizado |
| `page.before_validate` | Antes de validar página |
| `page.after_validate` | Después de validar |
| `page.before_save` | Antes de guardar |
| `page.after_save` | Después de guardar |
| `page.before_delete` | Antes de eliminar |
| `page.after_delete` | Después de eliminar |
| `theme.after_update` | Tema actualizado |
| `menu.after_update` | Menú actualizado |
| `block.before_save` | Antes de guardar bloque |
| `block.after_save` | Después de guardar bloque |
| `block.global_data_changed` | Datos de bloque global cambiados |
| `page_template.after_save` | Page template guardado |
| `page_template.approved` | Page template aprobado |
| `build.before` | Inicio de build |
| `build.css_generated` | CSS generado |
| `build.js_generated` | JS generado |
| `build.before_page` | Antes de renderizar cada página |
| `build.after_page` | Después de renderizar cada página |
| `build.after` | Build completado |
| `user.login` | Login exitoso |
| `user.logout` | Logout |
| `user.created` | Usuario creado |
| `task.created` | Tarea creada |
| `task.completed` | Tarea completada |
| `form.submitted` | Formulario recibido |
| `backup.before` | Inicio de backup |
| `backup.after` | Backup exitoso |
| `backup.failed` | Backup fallido |
| `mcp.request` | Request MCP recibido |
| `plugin.activated` | Plugin activado |
| `plugin.deactivated` | Plugin desactivado |
| `plugin.uninstalled` | Plugin eliminado |
| `settings.saved` | Configuración guardada |
| `cron.run` | Tarea cron ejecutada |
| `webhook.trigger` | Evento webhook disparado |
| `admin.head` | Dentro de <head> del admin |
| `admin.footer` | Antes de </body> del admin |
| `admin.settings.before_form` | Antes del form de settings |
| `admin.settings.after_form` | Después del form de settings |
| `admin.page_editor.sidebar` | Sidebar del editor |
| `admin.page_editor.below_content` | Debajo del editor |
| `admin.dashboard.top` | Arriba del dashboard |
| `admin.dashboard.bottom` | Abajo del dashboard |

### Filters (32)

| Hook | Qué filtra |
|------|-----------|
| `page.content` | HTML de contenido |
| `page.meta` | Metadatos de página |
| `page.rendered_html` | HTML final de la página |
| `block.rendered_html` | HTML de bloque renderizado |
| `block.css` | CSS de bloque |
| `block.available_types` | Tipos de bloque disponibles |
| `block.slot_types` | Tipos de slot disponibles |
| `page_template.wrapper_html` | Wrapper HTML |
| `page_template.structure` | Estructura de bloques |
| `page_template.available_types` | Page templates disponibles |
| `build.global_blocks` | Bloques globales cacheados |
| `build.head_html` | HTML en <head> |
| `build.body_end_html` | HTML antes de </body> |
| `build.robots_txt` | robots.txt |
| `build.sitemap_urls` | URLs del sitemap |
| `theme.css_variables` | CSS custom properties |
| `theme.custom_css` | CSS custom |
| `menu.items` | Items del menú |
| `menu.html` | HTML del menú |
| `template.variables` | Variables de template |
| `analytics.event` | Datos de analytics |
| `form.submission_data` | Datos de formulario |
| `admin.sidebar_items` | Menú lateral del admin |
| `admin.dashboard_widgets` | Widgets del dashboard |
| `admin.routes` | Rutas del admin |
| `admin.styles` | CSS del admin |
| `admin.scripts` | JS del admin |
| `admin.page_editor.tabs` | Pestañas del editor |
| `auth.capabilities` | Capabilities/permisos |
| `settings.sections` | Secciones de configuración |
| `mcp.tools_list` | Tools MCP |
| `mcp.handle_tool` | Ejecución de tools |
| `mcp.tool_response` | Respuesta de tools |
| `webhooks.events` | Eventos de webhook |
| `cron.tasks` | Tareas programadas |
| `front.review_bar_buttons` | Botones barra revisión |
| `admin.api_routes` | Endpoints API admin |

---

## 15. Ejemplo Completo: Plugin Mínimo

```php
// plugins/hello-world/klytos-plugin.json
{
  "id": "hello-world",
  "name": "Hello World",
  "version": "1.0.0",
  "author": "Developer",
  "premium": false,
  "requires_klytos": "2.0.0",
  "requires_php": "8.0"
}

// plugins/hello-world/init.php
<?php
if (!defined('KLYTOS_VERSION')) die();

// 1. Añadir item al menú del admin
klytos_add_filter('admin.sidebar_items', function (array $items): array {
    $items[] = [
        'id' => 'hello-world',
        'title' => 'Hello World',
        'url' => klytos_admin_url('plugin/hello-world/index.php'),
        'icon' => '👋',
        'position' => 85,
    ];
    return $items;
});

// 2. Registrar ruta del admin
klytos_add_filter('admin.routes', function (array $routes): array {
    $routes[] = [
        'pattern' => 'plugin/hello-world/index',
        'file'    => klytos_plugin_path('hello-world', 'admin/index.php'),
    ];
    return $routes;
});

// 3. Añadir widget al dashboard
klytos_add_filter('admin.dashboard_widgets', function (array $widgets): array {
    $widgets[] = [
        'id'     => 'hello-widget',
        'title'  => '👋 Hello',
        'size'   => 'third',
        'render' => fn() => '<p>Hello from my first plugin!</p>',
    ];
    return $widgets;
});

// 4. Registrar tool MCP
klytos_add_filter('mcp.tools_list', function (array $tools): array {
    $tools[] = [
        'name' => 'klytos_hello_say',
        'description' => 'Say hello to someone',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Person to greet'],
            ],
        ],
    ];
    return $tools;
});

klytos_add_filter('mcp.handle_tool', function (?array $result, string $tool, array $params): ?array {
    if ($tool !== 'klytos_hello_say') return $result;
    return ['message' => 'Hello, ' . ($params['name'] ?? 'World') . '!'];
});
```

**Con solo ese init.php el plugin ya tiene:** página de admin, item en el menú, widget en el dashboard, y un tool MCP. Sin tocar el core.

---

*Referencia para desarrolladores de plugins de Klytos.*
*Versión: 2.0.0 — Fecha: 2026-03-26*
