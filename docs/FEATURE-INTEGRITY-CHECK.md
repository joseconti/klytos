# Feature: Sistema de Verificacion de Integridad de Archivos

## Objetivo

Implementar un sistema que permita verificar que los archivos del core de Klytos y de los plugins instalados no han sido modificados, eliminados ni se han anadido archivos no autorizados. El sistema compara los hashes SHA-256 de los archivos locales contra manifiestos de referencia proporcionados por fuentes de confianza.

El sistema cubre tres escenarios:

1. **Core de Klytos:** manifiesto oficial alojado en `api.klytos.io`.
2. **Plugins del Marketplace:** manifiesto generado automaticamente por el Marketplace al publicar cada version.
3. **Plugins premium de terceros:** manifiesto alojado en el servidor del desarrollador, declarado en la cabecera del plugin.

---

## 1. Nueva clase: `IntegrityChecker` (`core/integrity-checker.php`)

### 1.1 Responsabilidades

- Descargar y cachear manifiestos de integridad.
- Calcular hashes SHA-256 de archivos locales.
- Comparar hashes locales contra el manifiesto de referencia.
- Generar un informe de estado detallado.
- Soportar verificacion por lotes (batched) para no sobrecargar el servidor en hosting compartido.

### 1.2 Propiedades

```php
class IntegrityChecker
{
    private StorageInterface $storage;
    private string $basePath;
    private string $apiBaseUrl;
    private int $batchSize;
    private int $cacheLifetime;

    /** Coleccion donde se almacenan los resultados y caches. */
    private const COLLECTION = 'integrity';

    /** Algoritmo de hash utilizado. */
    private const ALGORITHM = 'sha256';

    public function __construct(
        StorageInterface $storage,
        string $basePath,
        string $apiBaseUrl = 'https://api.klytos.io',
        int $batchSize = 100,
        int $cacheLifetime = 86400 // 24 horas
    ) {
        $this->storage       = $storage;
        $this->basePath      = rtrim($basePath, '/');
        $this->apiBaseUrl    = rtrim($apiBaseUrl, '/');
        $this->batchSize     = $batchSize;
        $this->cacheLifetime = $cacheLifetime;
    }
}
```

### 1.3 Estructura del manifiesto de referencia

Todos los manifiestos (core, marketplace, terceros) comparten la misma estructura:

```json
{
    "type": "core|plugin",
    "id": "core|plugin-id",
    "version": "1.2.0",
    "generated_at": "2026-04-01T10:00:00Z",
    "algorithm": "sha256",
    "files": {
        "core/app.php": "a1b2c3d4e5f6...",
        "core/helpers.php": "f7e8d9c0b1a2...",
        "admin/index.php": "1a2b3c4d5e6f..."
    },
    "exclude": [
        "config/local.php",
        "storage/*",
        ".env",
        "public/assets/images/*"
    ],
    "signature": "base64-encoded-signature..."
}
```

**Notas:**
- Los paths en `files` son relativos a la raiz de Klytos (para core) o a la raiz del plugin (para plugins).
- La seccion `exclude` lista paths y patrones glob que no deben verificarse (archivos que cambian legitimamente).
- El campo `signature` contiene la firma digital del manifiesto (ver seccion 4).

### 1.4 Metodo principal: `verify()`

```php
/**
 * Ejecutar verificacion completa de integridad.
 *
 * @param  bool $forceRefresh  Forzar descarga de manifiestos aunque esten en cache.
 * @return array Informe completo de verificacion.
 */
public function verify(bool $forceRefresh = false): array
{
    $report = [
        'status'      => 'ok', // ok | warning | error | unknown
        'checked_at'  => Helpers::now(),
        'core'        => $this->verifyCore($forceRefresh),
        'plugins'     => $this->verifyAllPlugins($forceRefresh),
        'summary'     => [],
    ];

    // Calcular estado global
    if ($report['core']['status'] === 'error' ||
        $this->hasPluginWithStatus($report['plugins'], 'error')) {
        $report['status'] = 'error';
    } elseif ($report['core']['status'] === 'warning' ||
              $this->hasPluginWithStatus($report['plugins'], 'warning') ||
              $this->hasPluginWithStatus($report['plugins'], 'unverified')) {
        $report['status'] = 'warning';
    }

    // Generar resumen
    $report['summary'] = $this->buildSummary($report);

    // Almacenar resultado
    $this->storage->write(self::COLLECTION, 'last-report', $report);

    return $report;
}
```

### 1.5 Verificacion del core: `verifyCore()`

```php
/**
 * Verificar integridad de los archivos del core.
 *
 * @param  bool $forceRefresh
 * @return array Resultado de la verificacion del core.
 */
private function verifyCore(bool $forceRefresh = false): array
{
    $version  = KLYTOS_VERSION; // Constante definida en el core
    $manifest = $this->fetchManifest(
        "core",
        "{$this->apiBaseUrl}/integrity/core/{$version}.json",
        $forceRefresh
    );

    if ($manifest === null) {
        return [
            'status'  => 'error',
            'message' => 'No se pudo obtener el manifiesto de integridad del core.',
        ];
    }

    if (!$this->verifySignature($manifest, 'core')) {
        return [
            'status'  => 'error',
            'message' => 'La firma del manifiesto del core no es valida.',
        ];
    }

    return $this->compareFiles($manifest, $this->basePath);
}
```

### 1.6 Verificacion de plugins: `verifyAllPlugins()`

```php
/**
 * Verificar integridad de todos los plugins instalados.
 *
 * @param  bool $forceRefresh
 * @return array Asociativo plugin_id => resultado.
 */
private function verifyAllPlugins(bool $forceRefresh = false): array
{
    $app       = App::getInstance();
    $loader    = $app->getPluginLoader();
    $plugins   = $loader->getDiscoveredPlugins();
    $results   = [];

    foreach ($plugins as $pluginId => $manifest) {
        $results[$pluginId] = $this->verifyPlugin($pluginId, $manifest, $forceRefresh);
    }

    return $results;
}

/**
 * Verificar un plugin individual.
 */
private function verifyPlugin(string $pluginId, array $pluginManifest, bool $forceRefresh): array
{
    $version   = $pluginManifest['version'] ?? '0.0.0';
    $source    = $pluginManifest['source'] ?? 'unknown'; // 'marketplace' | 'external' | 'unknown'

    // Determinar URL del manifiesto segun el origen
    $manifestUrl = $this->resolvePluginManifestUrl($pluginId, $version, $pluginManifest);

    if ($manifestUrl === null) {
        return [
            'status'  => 'unverified',
            'message' => 'Este plugin no proporciona verificacion de integridad. '
                       . 'No es posible confirmar que sus archivos no han sido modificados. '
                       . 'Contacta con el desarrollador del plugin y solicitale que implemente '
                       . 'el endpoint de verificacion de integridad de Klytos.',
            'docs_url' => 'https://developers.klytos.io/integrity',
        ];
    }

    $manifest = $this->fetchManifest("plugin:{$pluginId}", $manifestUrl, $forceRefresh);

    if ($manifest === null) {
        return [
            'status'  => 'warning',
            'message' => "No se pudo descargar el manifiesto de integridad desde: {$manifestUrl}",
        ];
    }

    // Verificar firma: los del marketplace usan la clave de Klytos,
    // los externos usan la clave publica del desarrollador.
    $signatureSource = ($source === 'marketplace') ? 'klytos' : "developer:{$pluginId}";
    if (!$this->verifySignature($manifest, $signatureSource)) {
        return [
            'status'  => 'error',
            'message' => 'La firma del manifiesto de integridad no es valida.',
        ];
    }

    $pluginPath = $this->basePath . '/plugins/' . $pluginId;
    return $this->compareFiles($manifest, $pluginPath);
}

/**
 * Determinar la URL del manifiesto de integridad de un plugin.
 */
private function resolvePluginManifestUrl(string $pluginId, string $version, array $pluginManifest): ?string
{
    $source = $pluginManifest['source'] ?? 'unknown';

    if ($source === 'marketplace') {
        // Plugins del Marketplace: manifiesto en api.klytos.io
        return "{$this->apiBaseUrl}/integrity/plugins/{$pluginId}/{$version}.json";
    }

    // Plugins externos: buscar integrity_url en el manifiesto del plugin
    $integrityUrl = $pluginManifest['integrity_url'] ?? null;

    if ($integrityUrl === null) {
        return null;
    }

    // Sustituir {version} en la URL
    return str_replace('{version}', $version, $integrityUrl);
}
```

### 1.7 Comparacion de archivos: `compareFiles()`

```php
/**
 * Comparar archivos locales contra un manifiesto de referencia.
 *
 * @param  array  $manifest  Manifiesto descargado.
 * @param  string $basePath  Ruta base donde estan los archivos.
 * @return array  Resultado con listas de modified, added, missing.
 */
private function compareFiles(array $manifest, string $basePath): array
{
    $expectedFiles = $manifest['files'] ?? [];
    $excludes      = $manifest['exclude'] ?? [];

    $modified = [];
    $missing  = [];
    $added    = [];

    // 1. Verificar archivos esperados
    foreach ($expectedFiles as $relativePath => $expectedHash) {
        $fullPath = $basePath . '/' . $relativePath;

        if (!file_exists($fullPath)) {
            $missing[] = $relativePath;
            continue;
        }

        $localHash = hash_file(self::ALGORITHM, $fullPath);
        if ($localHash !== $expectedHash) {
            $modified[] = $relativePath;
        }
    }

    // 2. Detectar archivos anadidos (no estan en el manifiesto)
    $localFiles = $this->scanDirectory($basePath, $excludes);

    foreach ($localFiles as $relativePath) {
        if (!isset($expectedFiles[$relativePath])) {
            $added[] = $relativePath;
        }
    }

    // 3. Determinar estado
    $status = 'ok';
    if (!empty($modified) || !empty($added)) {
        $status = 'error';
    } elseif (!empty($missing)) {
        $status = 'warning';
    }

    return [
        'status'       => $status,
        'checked'      => count($expectedFiles),
        'modified'     => $modified,
        'added'        => $added,
        'missing'      => $missing,
        'version'      => $manifest['version'] ?? 'unknown',
        'manifest_date' => $manifest['generated_at'] ?? 'unknown',
    ];
}
```

### 1.8 Escaneo de directorio: `scanDirectory()`

```php
/**
 * Escanear un directorio recursivamente, excluyendo patrones.
 *
 * @param  string $basePath  Directorio base.
 * @param  array  $excludes  Patrones glob a excluir.
 * @return array  Lista de paths relativos.
 */
private function scanDirectory(string $basePath, array $excludes = []): array
{
    $files    = [];
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $relativePath = str_replace($basePath . '/', '', $file->getPathname());

        // Comprobar si coincide con algun patron de exclusion
        if ($this->matchesExclude($relativePath, $excludes)) {
            continue;
        }

        $files[] = $relativePath;
    }

    return $files;
}

/**
 * Comprobar si un path coincide con algun patron de exclusion.
 */
private function matchesExclude(string $path, array $excludes): bool
{
    foreach ($excludes as $pattern) {
        if (fnmatch($pattern, $path)) {
            return true;
        }
    }
    return false;
}
```

### 1.9 Descarga y cache de manifiestos: `fetchManifest()`

```php
/**
 * Descargar un manifiesto de integridad, usando cache local.
 *
 * @param  string $cacheKey     Clave unica para la cache (ej: "core", "plugin:my-gallery").
 * @param  string $url          URL del manifiesto.
 * @param  bool   $forceRefresh Ignorar cache.
 * @return array|null           Manifiesto decodificado o null si falla.
 */
private function fetchManifest(string $cacheKey, string $url, bool $forceRefresh = false): ?array
{
    $cacheId = 'manifest-cache-' . md5($cacheKey);

    // Comprobar cache
    if (!$forceRefresh) {
        try {
            $cached = $this->storage->read(self::COLLECTION, $cacheId);
            if (isset($cached['fetched_at']) &&
                (time() - strtotime($cached['fetched_at'])) < $this->cacheLifetime) {
                return $cached['data'];
            }
        } catch (\Throwable) {
            // No hay cache, continuar
        }
    }

    // Descargar
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'header'  => "Accept: application/json\r\n"
                           . "User-Agent: Klytos/" . KLYTOS_VERSION . "\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data['files'])) {
            return null;
        }

        // Guardar en cache
        $this->storage->write(self::COLLECTION, $cacheId, [
            'url'        => $url,
            'fetched_at' => Helpers::now(),
            'data'       => $data,
        ]);

        return $data;

    } catch (\Throwable) {
        return null;
    }
}
```

### 1.10 Verificacion de firma: `verifySignature()`

```php
/**
 * Verificar la firma digital de un manifiesto.
 *
 * @param  array  $manifest       Manifiesto completo (incluye campo 'signature').
 * @param  string $signatureSource Origen de la clave publica: 'core', 'klytos', 'developer:plugin-id'.
 * @return bool
 */
private function verifySignature(array $manifest, string $signatureSource): bool
{
    $signature = $manifest['signature'] ?? null;
    if ($signature === null) {
        return false;
    }

    // Obtener clave publica
    $publicKey = $this->getPublicKey($signatureSource);
    if ($publicKey === null) {
        return false;
    }

    // Reconstruir el payload firmado (manifiesto sin el campo 'signature')
    $payload = $manifest;
    unset($payload['signature']);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Verificar con openssl
    $signatureDecoded = base64_decode($signature, true);
    if ($signatureDecoded === false) {
        return false;
    }

    $result = openssl_verify($payloadJson, $signatureDecoded, $publicKey, OPENSSL_ALGO_SHA256);

    return $result === 1;
}

/**
 * Obtener la clave publica para verificar firmas.
 *
 * @param  string $source 'core' | 'klytos' | 'developer:plugin-id'
 * @return string|null     Clave publica PEM.
 */
private function getPublicKey(string $source): ?string
{
    if ($source === 'core' || $source === 'klytos') {
        // Clave publica de Klytos, embebida en el core
        $keyPath = $this->basePath . '/core/keys/klytos-integrity.pub';
        if (file_exists($keyPath)) {
            return file_get_contents($keyPath);
        }
        return null;
    }

    // Clave publica de un desarrollador externo
    if (str_starts_with($source, 'developer:')) {
        $pluginId = substr($source, strlen('developer:'));
        // La clave se almacena localmente al registrar el plugin
        try {
            $keyRecord = $this->storage->read('integrity-keys', $pluginId);
            return $keyRecord['public_key'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    return null;
}
```

### 1.11 Verificacion por lotes: `verifyBatch()`

Para hosting compartido, la verificacion puede ejecutarse en lotes a traves del cron:

```php
/**
 * Ejecutar verificacion en lotes (para cron).
 * Procesa un lote de archivos y almacena progreso.
 *
 * @return array ['completed' => bool, 'progress' => int, 'total' => int]
 */
public function verifyBatch(): array
{
    // Leer estado del lote actual
    $batchState = null;
    try {
        $batchState = $this->storage->read(self::COLLECTION, 'batch-state');
    } catch (\Throwable) {
        // No existe, iniciar nuevo lote
    }

    if ($batchState === null || ($batchState['completed'] ?? false)) {
        // Iniciar nuevo lote: recopilar todos los archivos a verificar
        $allFiles  = $this->collectAllFilesToVerify();
        $batchState = [
            'files'      => $allFiles,
            'offset'     => 0,
            'total'      => count($allFiles),
            'results'    => ['modified' => [], 'added' => [], 'missing' => []],
            'completed'  => false,
            'started_at' => Helpers::now(),
        ];
    }

    // Procesar siguiente lote
    $offset   = $batchState['offset'];
    $batch    = array_slice($batchState['files'], $offset, $this->batchSize, true);

    foreach ($batch as $index => $fileInfo) {
        $fullPath     = $fileInfo['base_path'] . '/' . $fileInfo['relative_path'];
        $expectedHash = $fileInfo['expected_hash'];

        if (!file_exists($fullPath)) {
            $batchState['results']['missing'][] = $fileInfo['label'];
        } else {
            $localHash = hash_file(self::ALGORITHM, $fullPath);
            if ($localHash !== $expectedHash) {
                $batchState['results']['modified'][] = $fileInfo['label'];
            }
        }
    }

    $batchState['offset'] += $this->batchSize;

    if ($batchState['offset'] >= $batchState['total']) {
        $batchState['completed']    = true;
        $batchState['completed_at'] = Helpers::now();

        // Generar informe final y almacenarlo
        $this->storage->write(self::COLLECTION, 'last-report', [
            'status'      => empty($batchState['results']['modified']) && empty($batchState['results']['added']) ? 'ok' : 'error',
            'checked_at'  => Helpers::now(),
            'results'     => $batchState['results'],
            'mode'        => 'batch',
        ]);
    }

    $this->storage->write(self::COLLECTION, 'batch-state', $batchState);

    return [
        'completed' => $batchState['completed'],
        'progress'  => min($batchState['offset'], $batchState['total']),
        'total'     => $batchState['total'],
    ];
}
```

### 1.12 Metodo auxiliar: `getLastReport()`

```php
/**
 * Obtener el ultimo informe de verificacion almacenado.
 *
 * @return array|null
 */
public function getLastReport(): ?array
{
    try {
        return $this->storage->read(self::COLLECTION, 'last-report');
    } catch (\Throwable) {
        return null;
    }
}
```

---

## 2. Endpoints de api.klytos.io

La API de integridad se documenta en detalle en `FEATURE-INTEGRITY-API.md`. Aqui se indica como el `IntegrityChecker` los consume:

### 2.1 Endpoints consumidos

| Recurso | URL | Descripcion |
|---------|-----|-------------|
| Core | `GET /integrity/core/{version}.json` | Manifiesto del core para una version concreta |
| Plugin Marketplace | `GET /integrity/plugins/{plugin-id}/{version}.json` | Manifiesto de un plugin del Marketplace |
| Clave publica Klytos | `GET /integrity/public-key` | Clave publica para verificar firmas de core y Marketplace |

### 2.2 Headers enviados

Todas las peticiones incluyen:
- `Accept: application/json`
- `User-Agent: Klytos/{version}`

No se requiere autenticacion para descargar manifiestos (son publicos).

---

## 3. Cambios en el manifiesto de plugins

### 3.1 Nuevos campos en la cabecera del plugin

Anadir los siguientes campos opcionales a la cabecera PHP del plugin:

```php
/**
 * Plugin Name: Premium SEO Tool
 * Plugin URI: https://premiumdev.com/seo-tool
 * Description: Herramientas SEO avanzadas.
 * Version: 2.1.0
 * Author: Premium Dev
 * Text Domain: premium-seo-tool
 * Source: external
 * Integrity URL: https://api.premiumdev.com/klytos/integrity/{version}.json
 * Integrity Key URL: https://api.premiumdev.com/klytos/integrity/public-key.pem
 */
```

**Campos nuevos:**
- `Source`: `marketplace` o `external`. Si no se indica, se infiere: si el plugin fue instalado desde el Marketplace se marca como `marketplace`, en cualquier otro caso como `external`.
- `Integrity URL`: URL del manifiesto de integridad. Se sustituye `{version}` por la version instalada. Solo necesario para plugins `external`.
- `Integrity Key URL`: URL de la clave publica del desarrollador para verificar la firma del manifiesto. Solo se descarga una vez (al instalar el plugin). Solo para plugins `external`.

### 3.2 Registro de clave publica de desarrollador

Al instalar un plugin externo que incluye `Integrity Key URL`:

```php
/**
 * Registrar la clave publica de un desarrollador externo.
 * Se ejecuta una sola vez al instalar el plugin.
 */
private function registerDeveloperKey(string $pluginId, string $keyUrl): bool
{
    try {
        $publicKey = @file_get_contents($keyUrl);

        if ($publicKey === false) {
            return false;
        }

        // Validar que es una clave publica PEM valida
        $key = openssl_pkey_get_public($publicKey);
        if ($key === false) {
            return false;
        }

        $this->storage->write('integrity-keys', $pluginId, [
            'plugin_id'     => $pluginId,
            'public_key'    => $publicKey,
            'key_url'       => $keyUrl,
            'registered_at' => Helpers::now(),
        ]);

        return true;

    } catch (\Throwable) {
        return false;
    }
}
```

### 3.3 Deteccion de cambio de manifiesto remoto sin cambio de version

Al verificar un plugin externo, si el manifiesto descargado es distinto al cacheado y la version no ha cambiado, generar una alerta:

```php
// Dentro de fetchManifest(), despues de descargar con exito:
if (!$forceRefresh && isset($cached['data'])) {
    $cachedVersion  = $cached['data']['version'] ?? '';
    $newVersion     = $data['version'] ?? '';
    $cachedHash     = md5(json_encode($cached['data']['files'] ?? []));
    $newHash        = md5(json_encode($data['files'] ?? []));

    if ($cachedVersion === $newVersion && $cachedHash !== $newHash) {
        // Alerta: el manifiesto cambio sin que cambiara la version
        klytos_log(
            "INTEGRITY WARNING: Manifest for '{$cacheKey}' changed without version bump. "
            . "Previous hash: {$cachedHash}, New hash: {$newHash}",
            'warning'
        );
        // Almacenar alerta para mostrar en el panel
        $this->storeAlert($cacheKey, 'manifest_changed_without_version_bump', [
            'previous_hash' => $cachedHash,
            'new_hash'      => $newHash,
            'version'       => $newVersion,
        ]);
    }
}
```

---

## 4. Niveles de confianza

El sistema define tres niveles de confianza, visibles en el panel de plugins con un indicador visual:

| Nivel | Icono | Origen | Verificacion |
|-------|-------|--------|-------------|
| Verificado (Klytos) | Escudo verde | Core + Plugins Marketplace | Manifiesto firmado por Klytos. Verificacion completa. |
| Verificado (Desarrollador) | Escudo amarillo | Plugins externos con `integrity_url` | Manifiesto firmado por el desarrollador. Verificacion delegada. |
| Sin verificacion | Escudo gris | Plugins externos sin `integrity_url` | No es posible verificar la integridad. |

### 4.1 Avisos al instalar plugins externos

**Plugin externo CON integrity_url:**

Antes de confirmar la instalacion, mostrar:

> "Este plugin no proviene del Marketplace de Klytos. Antes de instalarlo, asegurate de que confias en el desarrollador y en el sitio de donde lo has obtenido. Klytos no puede garantizar la seguridad de plugins que no provengan del Marketplace."

Incluir boton "Entiendo, instalar plugin" y boton "Cancelar".

**Plugin externo SIN integrity_url:**

Antes de confirmar la instalacion, mostrar:

> "Este plugin no proviene del Marketplace de Klytos. Antes de instalarlo, asegurate de que confias en el desarrollador y en el sitio de donde lo has obtenido. Klytos no puede garantizar la seguridad de plugins que no provengan del Marketplace."
>
> "Ademas, este plugin no incluye verificacion de integridad, lo que significa que no sera posible comprobar automaticamente si sus archivos son modificados en el futuro."

Incluir boton "Entiendo los riesgos, instalar plugin" y boton "Cancelar".

### 4.2 Aviso en el panel para plugins sin verificacion

En la pagina de plugins, junto al plugin sin `integrity_url`, mostrar un aviso permanente (amarillo/naranja):

> "Este plugin no proporciona verificacion de integridad. No es posible confirmar que sus archivos no han sido modificados. Contacta con el desarrollador del plugin y solicitale que implemente el endpoint de verificacion de integridad de Klytos."
>
> [Documentacion para desarrolladores](https://developers.klytos.io/integrity)

El enlace a la documentacion permite que el administrador lo comparta directamente con el desarrollador.

---

## 5. Integracion con el Cron

### 5.1 Registro de tarea cron

Al inicializar Klytos, registrar la tarea de verificacion de integridad:

```php
// En el proceso de inicializacion (App::boot() o similar)
$cronManager->register([
    'id'          => 'integrity-check',
    'description' => 'Verificacion automatica de integridad de archivos',
    'interval'    => 'daily', // Cada 24 horas
    'callback'    => function () {
        $checker = App::getInstance()->getIntegrityChecker();
        $result  = $checker->verifyBatch();

        // Si el lote no se completo, reprogramar para pronto
        if (!$result['completed']) {
            return ['reschedule' => 300]; // Reintentar en 5 minutos
        }

        return $result;
    },
]);
```

### 5.2 Logica del cron con lotes

La primera ejecucion del cron inicia un lote nuevo. Si el sitio tiene muchos archivos y el lote no se completa en una ejecucion, la tarea se reprograma a 5 minutos y continua donde se quedo. Cuando el lote se completa, el resultado queda almacenado y la siguiente ejecucion sera al dia siguiente.

---

## 6. Panel de administracion: System > Integridad

### 6.1 Ubicacion

Nuevo archivo: `admin/system-integrity.php`
Accesible desde: menu lateral System > Verificacion de Integridad

### 6.2 Funcionalidad

**Seccion superior: Estado global**
- Indicador grande con el estado general: "Todo correcto" (verde), "Hay avisos" (amarillo), "Se han detectado problemas" (rojo).
- Fecha y hora de la ultima verificacion.
- Boton "Verificar ahora" que ejecuta `verify(forceRefresh: true)`.

**Seccion Core:**
- Estado del core (ok / archivos modificados / archivos anadidos / archivos eliminados).
- Version del core verificada.
- Si hay problemas: lista expandible con los archivos afectados.

**Seccion Plugins:**
- Tabla con todos los plugins y su estado de integridad:

| Plugin | Version | Nivel de confianza | Estado | Detalles |
|--------|---------|-------------------|--------|----------|
| My Gallery | 1.0.0 | Marketplace (verde) | OK | 23 archivos verificados |
| Premium SEO | 2.1.0 | Desarrollador (amarillo) | 1 modificado | [Ver detalles] |
| Custom Plugin | 1.0.0 | Sin verificacion (gris) | -- | Contactar desarrollador |

- Al hacer clic en "Ver detalles" se muestra la lista de archivos afectados.

**Seccion Configuracion:**
- Frecuencia de verificacion automatica (cada 12h / 24h / 48h / semanal / desactivada).
- Notificacion por email al detectar problemas (activar/desactivar + email destino).
- Tamano del lote para verificacion (por defecto 100 archivos por ejecucion del cron).

### 6.3 API endpoint

Nuevo endpoint: `admin/api/integrity.php`

Acciones soportadas:
- `GET ?action=status` - Estado del ultimo informe de verificacion.
- `POST ?action=verify` - Ejecutar verificacion completa (sincrona si hay pocos archivos, o iniciar lote).
- `POST ?action=verify_force` - Verificacion forzada (descarga manifiestos nuevos ignorando cache).
- `GET ?action=report` - Informe completo detallado.

Todas las acciones requieren autenticacion de administrador y verificacion CSRF.

### 6.4 Entrada en el menu

```php
// En el sidebar, seccion System
[
    'title'      => 'Verificacion de Integridad',
    'slug'       => 'system-integrity',
    'icon'       => 'shield-check', // o el icono apropiado del sistema de iconos
    'capability' => 'manage_system', // solo admins
]
```

---

## 7. Integracion con MCP Tools

```php
[
    'name'        => 'integrity_check',
    'description' => 'Run a full integrity check on core and all plugins',
    'parameters'  => ['force_refresh' => 'boolean (optional, default false)'],
],
[
    'name'        => 'integrity_status',
    'description' => 'Get the last integrity check report',
    'parameters'  => [],
],
[
    'name'        => 'integrity_check_plugin',
    'description' => 'Run integrity check on a specific plugin',
    'parameters'  => ['plugin_id' => 'string (required)', 'force_refresh' => 'boolean (optional)'],
],
```

---

## 8. Instanciacion en App

### 8.1 Nueva propiedad y getter

```php
// En App (app.php)
private ?IntegrityChecker $integrityChecker = null;

public function getIntegrityChecker(): IntegrityChecker
{
    if ($this->integrityChecker === null) {
        $this->integrityChecker = new IntegrityChecker(
            $this->storage,
            $this->basePath
        );
    }
    return $this->integrityChecker;
}
```

### 8.2 Clave publica de Klytos

Crear el directorio `core/keys/` y colocar ahi la clave publica:
- `core/keys/klytos-integrity.pub` - Clave publica RSA 4096 bits para verificar firmas de manifiestos del core y del Marketplace.

La clave privada correspondiente NUNCA se incluye en el core. Solo existe en el servidor de api.klytos.io.

---

## 9. Colecciones nuevas en la base de datos

Anadir al array de colecciones por defecto en `DatabaseStorage::createTables()`:

```php
$collections = [
    // ... existentes ...
    'integrity',       // Informes, cache de manifiestos, estado de lotes
    'integrity-keys',  // Claves publicas de desarrolladores externos
];
```

---

## 10. Funciones helper publicas

```php
/**
 * Ejecutar verificacion de integridad completa.
 */
function klytos_integrity_check(bool $forceRefresh = false): array
{
    return App::getInstance()->getIntegrityChecker()->verify($forceRefresh);
}

/**
 * Obtener el ultimo informe de integridad.
 */
function klytos_integrity_status(): ?array
{
    return App::getInstance()->getIntegrityChecker()->getLastReport();
}
```

---

## 11. Orden de implementacion

1. Crear `core/integrity-checker.php` con la clase `IntegrityChecker` completa.
2. Crear `core/keys/` y generar el par de claves RSA 4096 (publica en el repo, privada solo en api.klytos.io).
3. Anadir colecciones `integrity` e `integrity-keys` a `DatabaseStorage`.
4. Modificar el parser de cabeceras de plugins en `PluginLoader` para reconocer `Source`, `Integrity URL` e `Integrity Key URL`.
5. Implementar el registro de clave publica del desarrollador al instalar plugins externos.
6. Anadir avisos de confianza en el flujo de instalacion de plugins.
7. Anadir aviso permanente en la pagina de plugins para plugins sin verificacion.
8. Registrar tarea cron `integrity-check`.
9. Crear `admin/api/integrity.php`.
10. Crear `admin/system-integrity.php` (panel de administracion).
11. Anadir entrada al menu lateral.
12. Anadir funciones helper publicas.
13. Anadir MCP tools.
14. Instanciar `IntegrityChecker` en `App`.
15. Probar con archivos del core modificados manualmente para confirmar la deteccion.

---

## 12. Notas importantes

- **`hash_file('sha256', $path)` es nativo de PHP.** No requiere dependencias externas.
- **La clave privada de Klytos NUNCA se distribuye.** Solo la clave publica va en el core.
- **Los manifiestos son publicos.** No contienen informacion sensible, solo paths relativos y hashes.
- **La verificacion no bloquea el funcionamiento del CMS.** Si falla la descarga de un manifiesto o la verificacion, se informa pero el sitio sigue funcionando.
- **Plugins sin `integrity_url` no se bloquean.** Se muestran con aviso y se insta al administrador a contactar con el desarrollador.
- **La primera vez que se instala un plugin externo con `Integrity Key URL`, se descarga y almacena su clave publica.** Si la clave cambia en el servidor del desarrollador, no se actualiza automaticamente (para evitar ataques de sustitucion de clave). El administrador deberia poder actualizarla manualmente desde el panel si es necesario.
- **Proteccion contra cambio de manifiesto sin cambio de version:** si un manifiesto remoto cambia su contenido pero mantiene la misma version, se genera una alerta visible en el panel.
