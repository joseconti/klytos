# Feature: Sistema de Opciones con Text Domain

## Objetivo

Resolver el problema historico de WordPress donde los plugins dejan basura en la base de datos al desinstalarse. En Klytos, cada opcion almacenada debe llevar asociado el `text_domain` del plugin (o `_core` para opciones del sistema), de forma que siempre se pueda saber a quien pertenece cada opcion. Ademas, se anade un panel de administracion en System para gestionar todas las opciones.

---

## 1. Cambios en la estructura de datos de opciones

### 1.1 Estado actual

Actualmente cada registro en la coleccion `options` tiene esta estructura:

```json
{
    "key": "my-gallery.columns",
    "value": 3,
    "created_at": "2026-04-01T10:00:00+00:00",
    "updated_at": "2026-04-01T10:00:00+00:00"
}
```

### 1.2 Nueva estructura

Anadir el campo `text_domain` como metadato obligatorio:

```json
{
    "key": "my-gallery.columns",
    "value": 3,
    "text_domain": "my-gallery",
    "created_at": "2026-04-01T10:00:00+00:00",
    "updated_at": "2026-04-01T10:00:00+00:00"
}
```

- Las opciones creadas por el core usaran `text_domain: "_core"`.
- Las opciones creadas por plugins usaran el `text_domain` declarado en su cabecera PHP (campo `Text Domain`). Si no tiene Text Domain declarado, se usa el `plugin_id` como fallback.

### 1.3 Migracion / compatibilidad hacia atras

**CRITICO:** El sistema debe funcionar sin fallar si encuentra registros antiguos sin el campo `text_domain`. La estrategia es:

1. **Al leer** (`get`): si el registro no tiene `text_domain`, funciona igual que antes. No falla.
2. **Al escribir** (`set`): si el registro ya existe y no tiene `text_domain`, se le anade en ese momento (migracion perezosa / lazy migration).
3. **Al listar** (panel de administracion): si una opcion no tiene `text_domain`, se clasifica como `_unknown` y se muestra en la categoria "Sin clasificar" o "Desconocido".
4. **Comando de migracion masiva** (opcional pero recomendado): un metodo `migrateTextDomains()` en `OptionsManager` que recorra todas las opciones sin `text_domain` e intente inferirlo por el prefijo de la clave (la parte antes del primer punto).

---

## 2. Cambios en `OptionsManager` (`core/options-manager.php`)

### 2.1 Nueva propiedad: contexto de text domain activo

```php
/** @var string|null Text domain del plugin actualmente en ejecucion. */
private ?string $activeTextDomain = null;

public function setActiveTextDomain(?string $textDomain): void
{
    $this->activeTextDomain = $textDomain;
}

public function getActiveTextDomain(): ?string
{
    return $this->activeTextDomain;
}
```

### 2.2 Modificar el metodo `set()`

El metodo `set()` debe inyectar automaticamente el `text_domain` en el registro:

```php
public function set(string $key, mixed $value, ?string $textDomain = null): void
{
    $key = $this->sanitizeKey($key);

    $oldValue = $this->get($key);

    klytos_do_action('option.before_set', $key, $value, $oldValue);

    $now    = Helpers::now();
    $exists = $this->storage->exists(self::COLLECTION, $key);

    // Determinar el text_domain: parametro explicito > contexto activo > inferir del key > _unknown
    $resolvedDomain = $textDomain
        ?? $this->activeTextDomain
        ?? $this->inferTextDomain($key)
        ?? '_unknown';

    // Si ya existe, preservar created_at y text_domain original (si no se pasa uno nuevo)
    $existingRecord = [];
    if ($exists) {
        try {
            $existingRecord = $this->storage->read(self::COLLECTION, $key);
        } catch (\Throwable) {
            // Ignorar si no se puede leer
        }
    }

    $record = [
        'key'         => $key,
        'value'       => $value,
        'text_domain' => $textDomain ?? $existingRecord['text_domain'] ?? $resolvedDomain,
        'created_at'  => $existingRecord['created_at'] ?? $now,
        'updated_at'  => $now,
    ];

    $this->storage->write(self::COLLECTION, $key, $record);

    // Actualizar cache
    $this->cache[$key]     = $value;
    $this->cacheHits[$key] = true;

    klytos_do_action('option.after_set', $key, $value, $oldValue);
}
```

### 2.3 Metodo auxiliar para inferir text domain

```php
/**
 * Intenta inferir el text_domain a partir del prefijo de la clave.
 * Convencion: 'my-gallery.columns' -> 'my-gallery'
 */
private function inferTextDomain(string $key): ?string
{
    $dotPos = strpos($key, '.');
    if ($dotPos !== false && $dotPos > 0) {
        return substr($key, 0, $dotPos);
    }
    return null;
}
```

### 2.4 Nuevos metodos para gestion por text domain

```php
/**
 * Obtener todas las opciones de un text domain especifico.
 *
 * @param  string $textDomain Text domain a buscar.
 * @return array<string, mixed> Asociativo key => record completo.
 */
public function getByTextDomain(string $textDomain): array
{
    $all    = $this->storage->list(self::COLLECTION);
    $result = [];

    foreach ($all as $record) {
        $domain = $record['text_domain'] ?? $this->inferTextDomain($record['key'] ?? '');
        if ($domain === $textDomain) {
            $result[$record['key']] = $record;
        }
    }

    return $result;
}

/**
 * Eliminar todas las opciones de un text domain especifico.
 *
 * @param  string $textDomain Text domain a eliminar.
 * @return int    Numero de opciones eliminadas.
 */
public function deleteByTextDomain(string $textDomain): int
{
    $options = $this->getByTextDomain($textDomain);
    $deleted = 0;

    foreach ($options as $key => $record) {
        if ($this->delete($key)) {
            $deleted++;
        }
    }

    return $deleted;
}

/**
 * Listar todas las opciones agrupadas por text domain.
 *
 * @return array<string, array> text_domain => [registros]
 */
public function listGroupedByTextDomain(): array
{
    $all    = $this->storage->list(self::COLLECTION);
    $groups = [];

    foreach ($all as $record) {
        $domain = $record['text_domain']
            ?? $this->inferTextDomain($record['key'] ?? '')
            ?? '_unknown';
        $groups[$domain][] = $record;
    }

    ksort($groups);
    return $groups;
}

/**
 * Clasificar opciones segun el estado de sus plugins.
 *
 * @param  array $activePlugins   Lista de text_domains de plugins activos.
 * @param  array $inactivePlugins Lista de text_domains de plugins inactivos.
 * @return array Con claves: 'core', 'active', 'inactive', 'orphan', 'unknown'
 */
public function classifyOptions(array $activePlugins, array $inactivePlugins): array
{
    $grouped = $this->listGroupedByTextDomain();

    $classified = [
        'core'     => [],  // text_domain === '_core'
        'active'   => [],  // text_domain esta en $activePlugins
        'inactive' => [],  // text_domain esta en $inactivePlugins
        'orphan'   => [],  // text_domain no esta ni en activos ni en inactivos ni es core
        'unknown'  => [],  // text_domain === '_unknown'
    ];

    foreach ($grouped as $domain => $records) {
        if ($domain === '_core') {
            $classified['core'][$domain] = $records;
        } elseif ($domain === '_unknown') {
            $classified['unknown'][$domain] = $records;
        } elseif (in_array($domain, $activePlugins, true)) {
            $classified['active'][$domain] = $records;
        } elseif (in_array($domain, $inactivePlugins, true)) {
            $classified['inactive'][$domain] = $records;
        } else {
            $classified['orphan'][$domain] = $records;
        }
    }

    return $classified;
}

/**
 * Migrar opciones antiguas sin text_domain (lazy migration en bloque).
 *
 * @return int Numero de registros migrados.
 */
public function migrateTextDomains(): int
{
    $all      = $this->storage->list(self::COLLECTION);
    $migrated = 0;

    foreach ($all as $record) {
        if (!isset($record['text_domain']) || $record['text_domain'] === '') {
            $key    = $record['key'] ?? '';
            $domain = $this->inferTextDomain($key) ?? '_unknown';

            $record['text_domain'] = $domain;
            $this->storage->write(self::COLLECTION, $key, $record);
            $migrated++;
        }
    }

    return $migrated;
}
```

### 2.5 Actualizar `deleteForPlugin()` existente

El metodo actual ya funciona por prefijo de clave. Mantenerlo pero tambien invocar `deleteByTextDomain()` para cubrir opciones que no sigan la convencion de prefijo:

```php
public function deleteForPlugin(string $pluginId): int
{
    $prefix  = $pluginId . '.';
    $all     = $this->storage->list(self::COLLECTION);
    $deleted = 0;

    foreach ($all as $record) {
        $key    = $record['key'] ?? '';
        $domain = $record['text_domain'] ?? '';

        // Eliminar si coincide por prefijo O por text_domain
        if (($key !== '' && str_starts_with($key, $prefix)) || $domain === $pluginId) {
            $this->delete($key);
            $deleted++;
        }
    }

    return $deleted;
}
```

---

## 3. Integracion con el Plugin Loader

### 3.1 Inyeccion automatica del text domain

En `PluginLoader`, al cargar cada plugin, antes de ejecutar su codigo, establecer el text domain activo en `OptionsManager`. Asi cualquier llamada a `klytos_set_option()` dentro del plugin recibe el text domain correcto automaticamente.

En el metodo que carga y ejecuta cada plugin (dentro del closure de ejecucion aislada):

```php
// Antes de ejecutar el plugin
$app->getOptionsManager()->setActiveTextDomain($manifest['text_domain']);

// Ejecutar el codigo del plugin
(function() use ($entryFile) {
    require $entryFile;
})();

// Despues, restaurar a null (o al text domain anterior si hay plugins anidados)
$app->getOptionsManager()->setActiveTextDomain(null);
```

### 3.2 Obtener listas de plugins para clasificacion

Anadir al `PluginLoader` un metodo que devuelva los text domains de plugins activos e inactivos:

```php
/**
 * Obtener los text domains de todos los plugins, separados por estado.
 *
 * @return array ['active' => [...], 'inactive' => [...]]
 */
public function getTextDomainsByStatus(): array
{
    $active   = [];
    $inactive = [];

    foreach ($this->discoveredPlugins as $id => $manifest) {
        $domain = $manifest['text_domain'] ?? $id;
        if ($this->isActive($id)) {
            $active[] = $domain;
        } else {
            $inactive[] = $domain;
        }
    }

    return ['active' => $active, 'inactive' => $inactive];
}
```

### 3.3 Limpieza automatica al desinstalar

En el proceso de desinstalacion de un plugin (cuando se ejecuta `uninstall.php`), despues de ejecutar el script, ofrecer automaticamente la eliminacion de opciones huerfanas:

```php
// Despues de ejecutar uninstall.php del plugin
$domain  = $manifest['text_domain'] ?? $pluginId;
$deleted = $app->getOptionsManager()->deleteByTextDomain($domain);

if ($deleted > 0) {
    klytos_log("Plugin '{$pluginId}' uninstalled: {$deleted} orphan options removed.");
}
```

---

## 4. Cambios en la base de datos (DatabaseStorage)

### 4.1 Nuevo indice para la coleccion `options`

Anadir `options` al array `INDEX_FIELDS` de `DatabaseStorage`:

```php
private const INDEX_FIELDS = [
    // ... campos existentes ...
    'options' => ['text_domain' => 'idx_type', 'key' => 'idx_slug'],
];
```

Esto permite filtrar opciones por `text_domain` usando el indice `idx_type` sin necesidad de descifrar todos los registros.

### 4.2 Anadir `options` a las tablas por defecto

En el metodo `createTables()`, anadir `'options'` a la lista de colecciones por defecto:

```php
if (empty($collections)) {
    $collections = [
        'config',
        'pages',
        'users',
        'tasks',
        'blocks',
        'page-templates',
        'page-versions',
        'webhooks',
        'webhook-logs',
        'analytics',
        'analytics-salt',
        'audit-log',
        'plugins',
        'cron',
        'post-types',
        'options',        // <-- NUEVO
    ];
}
```

### 4.3 Auto-creacion de tabla si no existe

El metodo `write()` de `DatabaseStorage` ya tiene logica para auto-crear tablas cuando no existen (lineas 254-261 del codigo actual). Esto significa que si se actualiza Klytos y la tabla `kly_options` no existe todavia, se creara automaticamente en el primer `write()`. **No se necesita un script de migracion explicito para la tabla en si.**

Para el campo `text_domain` dentro de los datos JSON, al ser parte del JSON cifrado/almacenado y no una columna SQL independiente, tampoco requiere ALTER TABLE. El indice `idx_type` ya existe en la estructura generica de tablas.

### 4.4 Resumen de compatibilidad BD

| Situacion | Comportamiento |
|-----------|---------------|
| Tabla `kly_options` no existe | Se crea automaticamente en el primer `write()` |
| Registro sin campo `text_domain` en JSON | Se lee sin error, se clasifica como `_unknown` |
| Primer `set()` sobre registro antiguo | Se anade `text_domain` via lazy migration |
| Migracion masiva | Metodo `migrateTextDomains()` disponible en admin |

---

## 5. Funciones helper publicas

Anadir a las funciones helper globales (donde estan `klytos_get_option`, `klytos_set_option`, etc.):

```php
/**
 * Establecer una opcion con text domain explicito.
 */
function klytos_set_option_for(string $textDomain, string $key, mixed $value): void
{
    App::getInstance()->getOptionsManager()->set($key, $value, $textDomain);
}

/**
 * Obtener todas las opciones de un text domain.
 */
function klytos_get_options_by_domain(string $textDomain): array
{
    return App::getInstance()->getOptionsManager()->getByTextDomain($textDomain);
}

/**
 * Eliminar todas las opciones de un text domain.
 */
function klytos_delete_options_by_domain(string $textDomain): int
{
    return App::getInstance()->getOptionsManager()->deleteByTextDomain($textDomain);
}
```

---

## 6. Panel de administracion: System > Gestion de Opciones

### 6.1 Ubicacion

Nuevo archivo: `admin/system-options.php`
Accesible desde: menu lateral System > Gestion de Opciones

### 6.2 Funcionalidad

**Filtros superiores (pestanas):**
- **Todas** - Muestra todas las opciones existentes con su text domain
- **Core** - Opciones del sistema (`_core`)
- **Plugins Activos** - Opciones de plugins actualmente activos
- **Plugins Inactivos** - Opciones de plugins desactivados pero presentes
- **Huerfanos** - Opciones cuyo text domain no corresponde a ningun plugin conocido (el plugin fue eliminado)
- **Sin clasificar** - Opciones sin text domain (`_unknown`)

Cada pestana muestra un badge con el numero de opciones en esa categoria.

**Filtro secundario:**
- Selector/buscador de Text Domain: permite filtrar por un text domain concreto.
- Buscador por clave de opcion (busqueda parcial).

**Tabla de opciones:**

| Text Domain | Clave | Valor (preview) | Creada | Modificada | Acciones |
|-------------|-------|-----------------|--------|------------|----------|
| my-gallery  | my-gallery.columns | 3 | 2026-01-15 | 2026-03-20 | [Eliminar] |

- El valor se muestra con preview truncado (max 100 caracteres). Si es array/objeto, se muestra `{object}` o `[array(N)]`.
- Acciones individuales: Eliminar opcion.
- Accion en bloque: "Eliminar todas las opciones de [text domain]" con confirmacion.

**Boton de migracion:**
- Si existen opciones sin `text_domain` (`_unknown`), mostrar un aviso con boton "Migrar opciones antiguas" que ejecuta `migrateTextDomains()`.

### 6.3 API endpoint

Nuevo endpoint: `admin/api/options-management.php`

Acciones soportadas:
- `GET ?action=list&filter=all|core|active|inactive|orphan|unknown&domain=text-domain` - Listar opciones
- `POST ?action=delete&key=option.key` - Eliminar una opcion
- `POST ?action=delete_domain&domain=text-domain` - Eliminar todas las opciones de un text domain
- `POST ?action=migrate` - Ejecutar migracion masiva de text domains

Todas las acciones requieren autenticacion de administrador y verificacion CSRF.

### 6.4 Entrada en el menu

Anadir la entrada en el menu lateral de admin, dentro de la seccion "System":

```php
// En el sidebar, seccion System
[
    'title'      => 'Gestion de Opciones',
    'slug'       => 'system-options',
    'icon'       => 'settings',  // o el icono apropiado del sistema de iconos
    'capability' => 'manage_options',  // solo admins
]
```

---

## 7. Integracion con MCP Tools

Anadir herramientas MCP para que la IA pueda gestionar opciones:

```php
// En mcp/tools/ o donde se registren las tools
[
    'name'        => 'options_list_by_domain',
    'description' => 'List all options for a specific text domain',
    'parameters'  => ['text_domain' => 'string (required)'],
],
[
    'name'        => 'options_classify',
    'description' => 'Classify all options by plugin status (active, inactive, orphan)',
    'parameters'  => [],
],
[
    'name'        => 'options_delete_domain',
    'description' => 'Delete all options belonging to a text domain',
    'parameters'  => ['text_domain' => 'string (required)', 'confirm' => 'boolean (required, must be true)'],
],
[
    'name'        => 'options_migrate',
    'description' => 'Migrate legacy options without text_domain',
    'parameters'  => [],
],
```

---

## 8. Orden de implementacion

1. Modificar `OptionsManager`: nuevos campos, metodos `getByTextDomain`, `deleteByTextDomain`, `listGroupedByTextDomain`, `classifyOptions`, `migrateTextDomains`, y parametro `textDomain` en `set()`.
2. Anadir `options` a `INDEX_FIELDS` en `DatabaseStorage` y a `createTables()`.
3. Modificar `PluginLoader`: inyectar text domain activo antes de ejecutar plugins, limpieza en desinstalacion.
4. Anadir funciones helper publicas.
5. Crear `admin/api/options-management.php`.
6. Crear `admin/system-options.php` (interfaz).
7. Anadir entrada al menu lateral.
8. Anadir MCP tools.
9. Probar con opciones existentes (compatibilidad hacia atras) y con plugin de ejemplo `hello-ai`.

---

## 9. Notas importantes

- **Nunca fallar** si `text_domain` no existe en un registro. Siempre tratar como `_unknown`.
- **No modificar la firma publica** de `klytos_get_option()` ni `klytos_set_option()`. El parametro `text_domain` en `set()` es opcional y se resuelve automaticamente por contexto.
- **El text_domain se almacena como dato**, no como columna SQL dedicada. Se indexa via `idx_type` en DatabaseStorage para filtrado eficiente.
- **La migracion es no destructiva**: solo anade el campo, no cambia claves ni valores.
