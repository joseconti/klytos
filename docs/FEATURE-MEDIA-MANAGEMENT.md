# Feature: Gestion de Imagenes con Categorias y Seguimiento de Uso

## Objetivo

Mejorar radicalmente la gestion de medios respecto a WordPress. Dos mejoras principales:

1. **Categorias de imagenes:** permitir organizar las imagenes en categorias personalizadas, ademas de la organizacion automatica por fecha.
2. **Seguimiento de uso:** registrar donde se utiliza cada imagen (paginas, entradas, cabecera, footer, etc.) para poder saber en todo momento si una imagen esta en uso y donde, y eliminar sin miedo las que no se usan.

---

## 1. Nueva coleccion: `assets` (registro de medios)

### 1.1 Situacion actual

Actualmente el `AssetManager` trabaja directamente con el sistema de archivos. Las imagenes se suben a `public/assets/images/YYYY/mm/` y se listan escaneando directorios con `RecursiveDirectoryIterator`. No hay metadatos persistidos mas alla de lo que el propio archivo ofrece (nombre, tamano, MIME, fecha de modificacion).

### 1.2 Nueva coleccion `assets`

Crear una coleccion `assets` en el StorageInterface para almacenar metadatos de cada archivo subido. Cada registro tendra esta estructura:

```json
{
    "id": "a1b2c3d4",
    "filename": "hero-banner.jpg",
    "path": "assets/images/2026/04/hero-banner.jpg",
    "mime_type": "image/jpeg",
    "size": 245760,
    "size_human": "240 KB",
    "alt_text": "",
    "title": "Hero Banner",
    "description": "",
    "categories": ["banners", "homepage"],
    "uploaded_by": "admin",
    "uploaded_at": "2026-04-01T10:00:00+00:00",
    "updated_at": "2026-04-01T10:00:00+00:00"
}
```

**Generacion del ID:** usar un hash corto unico. Se necesita crear el metodo `Helpers::generateShortId()` si no existe (por ejemplo `substr(md5(uniqid('', true)), 0, 8)`). No usar el path como ID porque puede cambiar si se reorganizan archivos.

**NOTA:** El metodo `Helpers::generateShortId()` no existe actualmente y debe crearse en `core/helpers.php`.

### 1.3 Registro automatico al subir

Modificar `AssetManager::upload()` y `AssetManager::uploadRaw()` para que, despues de escribir el archivo fisico, creen automaticamente un registro en la coleccion `assets`:

```php
// Al final de upload(), despues de escribir el archivo:
$assetId = Helpers::generateShortId(); // hash corto unico

$assetRecord = [
    'id'          => $assetId,
    'filename'    => $filename,
    'path'        => $relativePath,
    'mime_type'   => $this->getMimeType($targetPath),
    'size'        => strlen($data),
    'size_human'  => Helpers::formatBytes(strlen($data)),
    'alt_text'    => '',
    'title'       => pathinfo($filename, PATHINFO_FILENAME),
    'description' => '',
    'categories'  => [],
    'uploaded_by' => (klytos_current_user()['id'] ?? 'system'),
    'uploaded_at' => Helpers::now(),
    'updated_at'  => Helpers::now(),
];

$this->storage->write('assets', $assetId, $assetRecord);

// Anadir el asset_id al resultado devuelto
$result['asset_id'] = $assetId;
```

### 1.4 Eliminacion sincronizada

Modificar `AssetManager::delete()` para que al eliminar un archivo fisico, tambien elimine su registro de la coleccion `assets` y todos sus registros de uso en `asset-usage`:

```php
public function delete(string $relativePath): bool
{
    // ... validacion existente de seguridad ...

    // Buscar el registro del asset por path
    $assetRecord = $this->findAssetByPath($relativePath);

    klytos_do_action('asset.before_delete', $path, $assetRecord);

    $deleted = file_exists($path) && unlink($path);

    if ($deleted) {
        // Eliminar registro de metadatos
        if ($assetRecord) {
            $this->storage->delete('assets', $assetRecord['id']);
            // Eliminar todos los registros de uso
            $this->deleteUsageForAsset($assetRecord['id']);
        }
        klytos_do_action('asset.after_delete', $path, $assetRecord);
    }

    return $deleted;
}
```

### 1.5 Inyeccion de StorageInterface en AssetManager

Actualmente `AssetManager` solo recibe `$publicDir` y `$maxFileSize`. Hay que anadir `StorageInterface` como dependencia:

```php
class AssetManager
{
    private string $publicDir;
    private string $assetsDir;
    private int $maxFileSize;
    private StorageInterface $storage;  // NUEVO

    public function __construct(
        StorageInterface $storage,     // NUEVO
        string $publicDir,
        int $maxFileSize = 10485760
    ) {
        $this->storage     = $storage; // NUEVO
        $this->publicDir   = rtrim($publicDir, '/');
        $this->assetsDir   = $this->publicDir . '/assets';
        $this->maxFileSize = $maxFileSize;
    }
```

**IMPORTANTE:** Actualizar la instanciacion de `AssetManager` en `App` (`app.php`) para pasar el storage. Actualmente se instancia asi: `new AssetManager($this->publicPath)`. Debe cambiar a `new AssetManager($this->storage, $this->publicPath)`. Buscar la linea exacta en `app.php` (actualmente alrededor de la linea 296).

---

## 2. Nueva coleccion: `asset-categories`

### 2.1 Estructura

```json
{
    "id": "banners",
    "name": "Banners",
    "slug": "banners",
    "description": "Imagenes de banner y hero",
    "parent": null,
    "order": 0,
    "created_at": "2026-04-01T10:00:00+00:00"
}
```

Las categorias son planas por defecto, pero el campo `parent` permite jerarquia si se desea en el futuro.

### 2.2 Metodos en AssetManager

```php
/**
 * Crear una categoria de assets.
 */
public function createCategory(string $name, string $description = '', ?string $parent = null): array
{
    $slug = Helpers::sanitizeSlug($name);
    $id   = $slug;

    // Verificar que no existe
    if ($this->storage->exists('asset-categories', $id)) {
        throw new \RuntimeException("Asset category '{$slug}' already exists.");
    }

    $record = [
        'id'          => $id,
        'name'        => $name,
        'slug'        => $slug,
        'description' => $description,
        'parent'      => $parent,
        'order'       => 0,
        'created_at'  => Helpers::now(),
    ];

    $this->storage->write('asset-categories', $id, $record);
    return $record;
}

/**
 * Listar todas las categorias.
 */
public function listCategories(): array
{
    return $this->storage->list('asset-categories');
}

/**
 * Eliminar una categoria (no elimina las imagenes, solo las desvincula).
 */
public function deleteCategory(string $categoryId): bool
{
    if (!$this->storage->exists('asset-categories', $categoryId)) {
        return false;
    }

    // Desvincular la categoria de todos los assets que la tengan
    $assets = $this->storage->list('assets');
    foreach ($assets as $asset) {
        if (isset($asset['categories']) && in_array($categoryId, $asset['categories'], true)) {
            $asset['categories'] = array_values(
                array_filter($asset['categories'], fn($c) => $c !== $categoryId)
            );
            $this->storage->write('assets', $asset['id'], $asset);
        }
    }

    return $this->storage->delete('asset-categories', $categoryId);
}

/**
 * Asignar categorias a un asset.
 */
public function setAssetCategories(string $assetId, array $categoryIds): void
{
    $record = $this->storage->read('assets', $assetId);
    $record['categories'] = $categoryIds;
    $record['updated_at'] = Helpers::now();
    $this->storage->write('assets', $assetId, $record);
}

/**
 * Obtener assets por categoria.
 */
public function getAssetsByCategory(string $categoryId): array
{
    $all    = $this->storage->list('assets');
    $result = [];

    foreach ($all as $asset) {
        if (isset($asset['categories']) && in_array($categoryId, $asset['categories'], true)) {
            $result[] = $asset;
        }
    }

    return $result;
}
```

---

## 3. Nueva coleccion: `asset-usage` (seguimiento de uso)

### 3.1 Estructura

Cada registro representa una relacion "esta imagen se usa en este contenido":

```json
{
    "id": "a1b2c3d4--page--about-us",
    "asset_id": "a1b2c3d4",
    "context_type": "page",
    "context_id": "about-us",
    "context_label": "Sobre nosotros",
    "field": "content_html",
    "added_at": "2026-04-01T10:00:00+00:00"
}
```

**Campos:**
- `asset_id`: ID del asset (referencia a la coleccion `assets`).
- `context_type`: tipo de contenido donde se usa. Valores: `page`, `post`, `header`, `footer`, `sidebar`, `widget`, `theme`, `plugin`, `favicon`, `og_image`.
- `context_id`: identificador del contenido especifico (slug de pagina, ID de widget, etc.).
- `context_label`: nombre legible del contenido (para mostrar en la UI sin tener que cargar el contenido).
- `field`: campo especifico donde se usa (e.g. `content_html`, `featured_image`, `og_image`, `background`).

**ID del registro:** `{asset_id}--{context_type}--{context_id}` (para evitar duplicados).

### 3.2 Metodos en AssetManager para seguimiento de uso

```php
/**
 * Registrar que un asset se usa en un contexto.
 */
public function trackUsage(string $assetId, string $contextType, string $contextId, string $contextLabel = '', string $field = 'content_html'): void
{
    $usageId = "{$assetId}--{$contextType}--{$contextId}";

    // Si ya existe con el mismo field, no duplicar
    if ($this->storage->exists('asset-usage', $usageId)) {
        $existing = $this->storage->read('asset-usage', $usageId);
        // Actualizar label si cambio
        if ($existing['context_label'] !== $contextLabel) {
            $existing['context_label'] = $contextLabel;
            $this->storage->write('asset-usage', $usageId, $existing);
        }
        return;
    }

    $record = [
        'id'            => $usageId,
        'asset_id'      => $assetId,
        'context_type'  => $contextType,
        'context_id'    => $contextId,
        'context_label' => $contextLabel,
        'field'         => $field,
        'added_at'      => Helpers::now(),
    ];

    $this->storage->write('asset-usage', $usageId, $record);
}

/**
 * Eliminar el registro de uso de un asset en un contexto.
 */
public function removeUsage(string $assetId, string $contextType, string $contextId): void
{
    $usageId = "{$assetId}--{$contextType}--{$contextId}";
    $this->storage->delete('asset-usage', $usageId);
}

/**
 * Obtener todos los usos de un asset.
 *
 * @return array Lista de registros de uso.
 */
public function getUsage(string $assetId): array
{
    $all    = $this->storage->list('asset-usage');
    $result = [];

    foreach ($all as $record) {
        if (($record['asset_id'] ?? '') === $assetId) {
            $result[] = $record;
        }
    }

    return $result;
}

/**
 * Obtener todos los assets usados en un contexto especifico.
 */
public function getAssetsForContext(string $contextType, string $contextId): array
{
    $all    = $this->storage->list('asset-usage');
    $result = [];

    foreach ($all as $record) {
        if (($record['context_type'] ?? '') === $contextType
            && ($record['context_id'] ?? '') === $contextId) {
            $result[] = $record;
        }
    }

    return $result;
}

/**
 * Verificar si un asset esta en uso.
 */
public function isAssetInUse(string $assetId): bool
{
    return count($this->getUsage($assetId)) > 0;
}

/**
 * Obtener todos los assets que no estan en uso.
 */
public function getUnusedAssets(): array
{
    $allAssets = $this->storage->list('assets');
    $allUsage  = $this->storage->list('asset-usage');

    // Crear set de asset IDs en uso
    $usedIds = [];
    foreach ($allUsage as $usage) {
        $usedIds[$usage['asset_id'] ?? ''] = true;
    }

    $unused = [];
    foreach ($allAssets as $asset) {
        if (!isset($usedIds[$asset['id']])) {
            $unused[] = $asset;
        }
    }

    return $unused;
}

/**
 * Eliminar todos los registros de uso de un asset.
 */
public function deleteUsageForAsset(string $assetId): int
{
    $usages  = $this->getUsage($assetId);
    $deleted = 0;

    foreach ($usages as $usage) {
        if ($this->storage->delete('asset-usage', $usage['id'])) {
            $deleted++;
        }
    }

    return $deleted;
}

/**
 * Eliminar todos los registros de uso de un contexto (e.g. cuando se elimina una pagina).
 */
public function deleteUsageForContext(string $contextType, string $contextId): int
{
    $usages  = $this->getAssetsForContext($contextType, $contextId);
    $deleted = 0;

    foreach ($usages as $usage) {
        if ($this->storage->delete('asset-usage', $usage['id'])) {
            $deleted++;
        }
    }

    return $deleted;
}

/**
 * Buscar un asset por su path relativo.
 */
public function findAssetByPath(string $relativePath): ?array
{
    $all = $this->storage->list('assets');

    foreach ($all as $asset) {
        if (($asset['path'] ?? '') === $relativePath) {
            return $asset;
        }
    }

    return null;
}
```

---

## 4. Escaneo y actualizacion automatica de uso

### 4.1 Hook en page.after_save

Cuando se guarda una pagina, escanear su contenido HTML para detectar imagenes y actualizar los registros de uso:

```php
klytos_add_action('page.after_save', function(array $page) {
    $app          = App::getInstance();
    $assetManager = $app->getAssetManager();
    $pageSlug     = $page['slug'] ?? '';
    $pageTitle    = $page['title'] ?? $pageSlug;

    if (empty($pageSlug)) return;

    // Obtener usos anteriores de esta pagina
    $previousUsages = $assetManager->getAssetsForContext('page', $pageSlug);
    $previousAssetIds = array_map(fn($u) => $u['asset_id'], $previousUsages);

    // Escanear contenido HTML para encontrar imagenes
    $contentHtml = $page['content_html'] ?? '';
    $foundPaths  = [];

    // Buscar en src de img tags
    if (preg_match_all('/src=["\']([^"\']*\/assets\/[^"\']+)["\']/i', $contentHtml, $matches)) {
        foreach ($matches[1] as $src) {
            // Normalizar: extraer path relativo desde assets/
            $pos = strpos($src, 'assets/');
            if ($pos !== false) {
                $foundPaths[] = substr($src, $pos);
            }
        }
    }

    // Buscar en background-image CSS inline
    if (preg_match_all('/url\(["\']?([^"\')\s]*\/assets\/[^"\')\s]+)["\']?\)/i', $contentHtml, $matches)) {
        foreach ($matches[1] as $url) {
            $pos = strpos($url, 'assets/');
            if ($pos !== false) {
                $foundPaths[] = substr($url, $pos);
            }
        }
    }

    // Tambien escanear featured_image si existe
    if (!empty($page['featured_image'])) {
        $pos = strpos($page['featured_image'], 'assets/');
        if ($pos !== false) {
            $foundPaths[] = substr($page['featured_image'], $pos);
        }
    }

    // Tambien escanear og_image si existe
    if (!empty($page['og_image'])) {
        $pos = strpos($page['og_image'], 'assets/');
        if ($pos !== false) {
            $foundPaths[] = substr($page['og_image'], $pos);
        }
    }

    $foundPaths = array_unique($foundPaths);

    // Resolver paths a asset IDs
    $currentAssetIds = [];
    foreach ($foundPaths as $path) {
        $asset = $assetManager->findAssetByPath($path);
        if ($asset) {
            $currentAssetIds[] = $asset['id'];
            $assetManager->trackUsage($asset['id'], 'page', $pageSlug, $pageTitle, 'content_html');
        }
    }

    // Eliminar usos que ya no existen (imagen fue quitada del contenido)
    foreach ($previousAssetIds as $oldId) {
        if (!in_array($oldId, $currentAssetIds, true)) {
            $assetManager->removeUsage($oldId, 'page', $pageSlug);
        }
    }
}, 20);
```

### 4.2 Hook en page.after_delete

Cuando se elimina una pagina, limpiar todos sus registros de uso:

```php
klytos_add_action('page.after_delete', function(string $slug) {
    App::getInstance()->getAssetManager()->deleteUsageForContext('page', $slug);
}, 20);
```

### 4.3 Hook en theme.after_save

Cuando se actualiza el tema, escanear cabecera, footer y estilos:

```php
klytos_add_action('theme.after_save', function(array $themeConfig) {
    $app          = App::getInstance();
    $assetManager = $app->getAssetManager();

    // Limpiar usos anteriores del tema
    $assetManager->deleteUsageForContext('theme', 'global');
    $assetManager->deleteUsageForContext('header', 'global');
    $assetManager->deleteUsageForContext('footer', 'global');

    // Escanear logo, favicon, og_image por defecto, etc.
    $themeFields = [
        'logo'             => ['type' => 'header',  'field' => 'logo'],
        'favicon'          => ['type' => 'favicon',  'field' => 'favicon'],
        'default_og_image' => ['type' => 'og_image', 'field' => 'default'],
        'background_image' => ['type' => 'theme',    'field' => 'background'],
    ];

    foreach ($themeFields as $configKey => $meta) {
        $value = $themeConfig[$configKey] ?? '';
        if (!empty($value)) {
            $pos = strpos($value, 'assets/');
            if ($pos !== false) {
                $path  = substr($value, $pos);
                $asset = $assetManager->findAssetByPath($path);
                if ($asset) {
                    $assetManager->trackUsage(
                        $asset['id'],
                        $meta['type'],
                        'global',
                        ucfirst($meta['type']) . ' - ' . $meta['field'],
                        $meta['field']
                    );
                }
            }
        }
    }
}, 20);
```

### 4.4 Escaneo completo bajo demanda

Un metodo que recorre todo el contenido del sitio y reconstruye la tabla de uso desde cero. Util despues de una migracion o si se sospecha que los datos estan desincronizados:

```php
/**
 * Reconstruir toda la tabla de uso escaneando todo el contenido.
 * ATENCION: elimina todos los registros de uso y los recrea.
 *
 * @return array Estadisticas: ['scanned_pages' => int, 'usages_found' => int]
 */
public function rebuildUsageIndex(): array
{
    // 1. Eliminar todos los registros de uso existentes
    $allUsage = $this->storage->list('asset-usage');
    foreach ($allUsage as $usage) {
        $this->storage->delete('asset-usage', $usage['id']);
    }

    $stats = ['scanned_pages' => 0, 'usages_found' => 0];

    // 2. Escanear todas las paginas
    $pages = $this->storage->list('pages');
    foreach ($pages as $page) {
        $stats['scanned_pages']++;
        // Disparar page.after_save para que el hook actualice los usos
        klytos_do_action('page.after_save', $page);
    }

    // 3. Escanear configuracion del tema
    // (se dispara via theme.after_save)

    // Contar usos creados
    $stats['usages_found'] = $this->storage->count('asset-usage');

    return $stats;
}
```

---

## 5. Cambios en la base de datos (DatabaseStorage)

### 5.1 Nuevas colecciones en INDEX_FIELDS

```php
private const INDEX_FIELDS = [
    // ... campos existentes ...
    'assets'           => ['mime_type' => 'idx_type', 'path' => 'idx_slug'],
    'asset-categories' => ['slug' => 'idx_slug'],
    'asset-usage'      => ['asset_id' => 'idx_slug', 'context_type' => 'idx_type'],
];
```

### 5.2 Nuevas colecciones en createTables()

Anadir a la lista por defecto:

```php
'assets',
'asset-categories',
'asset-usage',
```

### 5.3 Auto-creacion

Igual que con options: las tablas se crean automaticamente en el primer `write()` si no existen. No se necesita script de migracion explicito.

### 5.4 Compatibilidad con instalaciones existentes

| Situacion | Comportamiento |
|-----------|---------------|
| Tablas `kly_assets`, `kly_asset_categories`, `kly_asset_usage` no existen | Se crean en el primer `write()` |
| Imagenes ya subidas sin registro en `assets` | Funcionan como antes; no aparecen en la biblioteca mejorada hasta que se ejecute un escaneo |
| Escaneo de imagenes existentes | Metodo `syncExistingAssets()` que recorre `public/assets/` y crea registros para archivos sin entrada en la coleccion |

### 5.5 Sincronizacion de assets existentes

```php
/**
 * Escanear el directorio de assets y crear registros para archivos
 * que no tienen entrada en la coleccion 'assets'.
 *
 * @return int Numero de assets sincronizados.
 */
public function syncExistingAssets(): int
{
    $allFiles     = $this->list();  // usa el metodo list() existente que escanea directorios
    $allRegistered = $this->storage->list('assets');

    // Crear mapa de paths registrados
    $registeredPaths = [];
    foreach ($allRegistered as $record) {
        $registeredPaths[$record['path'] ?? ''] = true;
    }

    $synced = 0;

    foreach ($allFiles as $file) {
        if (!isset($registeredPaths[$file['path']])) {
            $assetId = Helpers::generateShortId();

            $record = [
                'id'          => $assetId,
                'filename'    => $file['filename'],
                'path'        => $file['path'],
                'mime_type'   => $file['mime_type'],
                'size'        => $file['size'],
                'size_human'  => $file['size_human'],
                'alt_text'    => '',
                'title'       => pathinfo($file['filename'], PATHINFO_FILENAME),
                'description' => '',
                'categories'  => [],
                'uploaded_by' => 'system',
                'uploaded_at' => $file['modified'],
                'updated_at'  => $file['modified'],
            ];

            $this->storage->write('assets', $assetId, $record);
            $synced++;
        }
    }

    return $synced;
}
```

---

## 6. Panel de administracion: Biblioteca de Medios mejorada

### 6.1 Ubicacion

Modificar el archivo existente `admin/assets.php` para incorporar las nuevas funcionalidades.

### 6.2 Vista principal (grid/lista)

**Filtros superiores:**
- **Todas** - Todas las imagenes
- **En uso** - Imagenes con al menos un registro de uso
- **Sin uso** - Imagenes sin ningun registro de uso
- **Por categoria** - Selector de categoria

**Filtros secundarios:**
- Tipo de archivo (imagenes, videos, documentos, fuentes)
- Fecha (mes/ano)
- Buscador por nombre/titulo

**Vista grid:** Miniaturas con overlay de informacion:
- Nombre del archivo
- Icono indicando si esta en uso (check verde) o no (circulo gris)
- Numero de usos si > 0

**Vista lista:** Tabla con columnas:

| Miniatura | Nombre | Tipo | Tamano | Categorias | Usos | Fecha | Acciones |
|-----------|--------|------|--------|------------|------|-------|----------|
| [img] | hero.jpg | image/jpeg | 240 KB | Banners, Homepage | 3 | 01/04/2026 | [Ver usos] [Editar] [Eliminar] |

### 6.3 Vista detalle de un asset

Al hacer clic en una imagen, se abre un panel lateral (o modal) con:

**Seccion superior:** Preview de la imagen.

**Seccion de metadatos editables:**
- Titulo
- Texto alternativo (alt)
- Descripcion
- Categorias (selector multiple con opcion de crear nueva)

**Seccion "Usado en":** Lista de todos los contextos donde se usa esta imagen:

| Tipo | Contenido | Campo | Fecha |
|------|-----------|-------|-------|
| Pagina | Sobre nosotros | content_html | 01/04/2026 |
| Cabecera | Global | logo | 15/03/2026 |

Cada fila con enlace directo al contenido en el editor.

**Seccion de informacion tecnica:**
- Path completo
- Tamano
- Tipo MIME
- Dimensiones (ancho x alto, si es imagen)
- Fecha de subida
- Subido por

**Acciones:**
- Guardar cambios (metadatos)
- Copiar URL
- Eliminar (con advertencia si esta en uso: "Esta imagen se usa en N contenidos. Si la eliminas, dejara de verse en esos contenidos.")

### 6.4 Gestion de categorias

Accesible desde un boton "Gestionar categorias" en la biblioteca de medios. Panel/modal con:

- Lista de categorias existentes con numero de assets en cada una
- Crear nueva categoria (nombre, descripcion)
- Editar categoria existente
- Eliminar categoria (con confirmacion; no elimina las imagenes, solo desvincula)

### 6.5 Accion masiva: eliminar imagenes sin uso

Boton prominente: "Limpiar imagenes sin uso"
- Muestra preview de las imagenes que se van a eliminar
- Requiere confirmacion explicita
- Elimina archivos fisicos y registros de la coleccion `assets`

### 6.6 Accion de mantenimiento: sincronizar y reconstruir

Disponible en System o en la propia biblioteca:
- **Sincronizar assets:** ejecuta `syncExistingAssets()` para registrar archivos existentes sin entrada en la coleccion.
- **Reconstruir indice de uso:** ejecuta `rebuildUsageIndex()` para recalcular todos los usos desde el contenido actual.

---

## 7. API endpoints

### 7.1 Archivo: `admin/api/assets-management.php`

**Assets:**
- `GET ?action=list&filter=all|in_use|unused&category=slug&type=image|video|document&page=1&per_page=20` - Listar assets con filtros y paginacion
- `GET ?action=get&id=assetId` - Obtener detalle de un asset (incluye usos)
- `POST ?action=update&id=assetId` - Actualizar metadatos (titulo, alt, descripcion, categorias)
- `POST ?action=delete&id=assetId` - Eliminar asset (archivo + registro + usos)
- `POST ?action=bulk_delete` - Eliminar multiples assets (body: `{"ids": ["id1", "id2"]}`)
- `POST ?action=sync` - Sincronizar assets existentes del filesystem
- `POST ?action=rebuild_usage` - Reconstruir indice de uso

**Categorias:**
- `GET ?action=list_categories` - Listar categorias
- `POST ?action=create_category` - Crear categoria (body: `{"name": "...", "description": "..."}`)
- `POST ?action=update_category&id=slug` - Actualizar categoria
- `POST ?action=delete_category&id=slug` - Eliminar categoria

Todas las acciones requieren autenticacion y verificacion CSRF.

---

## 8. Integracion con el editor de paginas

### 8.1 Al insertar imagen desde el editor

Cuando el usuario inserta una imagen desde el editor (TipTap/Gutenberg), el endpoint `admin/api/media-upload.php` ya maneja la subida. Tras la subida, debe devolver el `asset_id` para que el editor lo almacene como atributo del nodo de imagen:

```html
<img src="/assets/images/2026/04/hero.jpg" data-asset-id="a1b2c3d4" alt="Hero">
```

El atributo `data-asset-id` permite que el hook `page.after_save` identifique rapidamente los assets usados sin depender solo del path.

### 8.2 Selector de imagen mejorado

Al hacer clic en "Insertar imagen" en el editor, abrir la biblioteca de medios con:
- Opcion de subir nueva
- Navegar por categorias
- Buscar por nombre
- Ver solo imagenes sin uso (para reutilizar)

---

## 9. Integracion con MCP Tools

Anadir herramientas MCP:

```php
[
    'name'        => 'assets_list',
    'description' => 'List assets with optional filters (category, usage status, mime type)',
    'parameters'  => [
        'filter'   => 'string (optional: all|in_use|unused)',
        'category' => 'string (optional: category slug)',
        'type'     => 'string (optional: image|video|document)',
        'limit'    => 'int (optional, default 20)',
    ],
],
[
    'name'        => 'assets_get_usage',
    'description' => 'Get all locations where an asset is used',
    'parameters'  => ['asset_id' => 'string (required)'],
],
[
    'name'        => 'assets_get_unused',
    'description' => 'Get all assets that are not used anywhere',
    'parameters'  => [],
],
[
    'name'        => 'assets_update_metadata',
    'description' => 'Update asset metadata (title, alt_text, description, categories)',
    'parameters'  => [
        'asset_id'    => 'string (required)',
        'title'       => 'string (optional)',
        'alt_text'    => 'string (optional)',
        'description' => 'string (optional)',
        'categories'  => 'array of strings (optional)',
    ],
],
[
    'name'        => 'asset_categories_list',
    'description' => 'List all asset categories',
    'parameters'  => [],
],
[
    'name'        => 'asset_categories_create',
    'description' => 'Create a new asset category',
    'parameters'  => ['name' => 'string (required)', 'description' => 'string (optional)'],
],
[
    'name'        => 'assets_sync',
    'description' => 'Sync filesystem assets that are not yet registered in the database',
    'parameters'  => [],
],
[
    'name'        => 'assets_rebuild_usage',
    'description' => 'Rebuild the entire usage index by scanning all content',
    'parameters'  => [],
],
[
    'name'        => 'assets_cleanup_unused',
    'description' => 'Delete all assets that are not used anywhere',
    'parameters'  => ['confirm' => 'boolean (required, must be true)'],
],
```

---

## 10. Orden de implementacion

1. Crear colecciones `assets`, `asset-categories` y `asset-usage` (anadir a `INDEX_FIELDS` y `createTables()` en `DatabaseStorage`).
2. Modificar `AssetManager`: anadir `StorageInterface` como dependencia, actualizar constructor.
3. Actualizar instanciacion en `App` (`app.php`).
4. Implementar metodos de categorias en `AssetManager`.
5. Implementar metodos de seguimiento de uso en `AssetManager`.
6. Implementar `syncExistingAssets()` y `rebuildUsageIndex()`.
7. Modificar `upload()` y `delete()` para registrar/eliminar metadatos automaticamente.
8. Implementar hooks: `page.after_save`, `page.after_delete`, `theme.after_save`.
9. Crear `admin/api/assets-management.php`.
10. Modificar `admin/assets.php` (interfaz mejorada).
11. Integrar con el editor de paginas (`data-asset-id`).
12. Anadir MCP tools.
13. Probar: subir imagen, insertarla en pagina, verificar que aparece como "en uso", eliminarla de la pagina, verificar que pasa a "sin uso".

---

## 11. Notas importantes

- **Nunca fallar** si un archivo existe en disco pero no tiene registro en `assets`. El metodo `list()` original del filesystem sigue funcionando como fallback.
- **No bloquear la subida** si el registro en `assets` falla por cualquier razon. El archivo fisico es lo critico; los metadatos se pueden reconstruir.
- **El escaneo de uso es conservador:** si no puede determinar con certeza que una imagen se usa, la marca como "sin uso". Es mejor falso negativo (no detectar un uso) que falso positivo (marcar como usada cuando no lo esta), porque el usuario siempre puede verificar visualmente antes de eliminar.
- **Rendimiento en FileStorage:** para sitios con miles de imagenes, `getUnusedAssets()` puede ser lento. Considerar cache o paginacion. En DatabaseStorage los indices `idx_slug` (asset_id) e `idx_type` (context_type) ayudan.
- **Las categorias son opcionales:** el sistema funciona perfectamente sin crear ninguna categoria. Son una herramienta organizativa, no un requisito.
