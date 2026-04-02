# Feature: API de Integridad en api.klytos.io

## Objetivo

Definir los endpoints, la logica del servidor y el proceso de generacion de manifiestos que debe existir en `api.klytos.io` para dar soporte al sistema de verificacion de integridad de Klytos.

Este documento cubre:

1. **Endpoints publicos** para servir manifiestos de integridad.
2. **Proceso de generacion** de manifiestos en el pipeline de build/publicacion.
3. **Gestion de claves** para firma digital.
4. **Endpoint interno** del Marketplace para generar manifiestos de plugins.

---

## 1. Estructura de la API

### 1.1 Base URL

```
https://api.klytos.io/integrity/
```

### 1.2 Endpoints publicos

Todos los endpoints de integridad son publicos (no requieren autenticacion). Los manifiestos no contienen informacion sensible.

---

## 2. Endpoint: Manifiesto del Core

### 2.1 Ruta

```
GET /integrity/core/{version}.json
```

### 2.2 Ejemplo

```
GET /integrity/core/1.2.0.json
```

### 2.3 Respuesta exitosa (200)

```json
{
    "type": "core",
    "id": "core",
    "version": "1.2.0",
    "generated_at": "2026-04-01T10:00:00Z",
    "algorithm": "sha256",
    "files": {
        "core/app.php": "a3f2b8c1d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1",
        "core/helpers.php": "b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5",
        "core/integrity-checker.php": "c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6",
        "core/options-manager.php": "d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7",
        "admin/index.php": "e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8",
        "...": "..."
    },
    "exclude": [
        "config/local.php",
        "config/database.php",
        ".env",
        "storage/*",
        "public/assets/images/*",
        "public/assets/uploads/*",
        "plugins/*",
        "themes/*",
        "core/keys/*"
    ],
    "signature": "base64-encoded-rsa-signature..."
}
```

### 2.4 Respuesta si la version no existe (404)

```json
{
    "error": "version_not_found",
    "message": "No integrity manifest found for core version 1.2.0"
}
```

### 2.5 Headers de respuesta

```
Content-Type: application/json; charset=utf-8
Cache-Control: public, max-age=86400
ETag: "hash-del-contenido"
```

Los manifiestos del core son inmutables (una vez publicada una version, no cambian), por lo que se pueden cachear agresivamente.

---

## 3. Endpoint: Manifiesto de Plugin del Marketplace

### 3.1 Ruta

```
GET /integrity/plugins/{plugin-id}/{version}.json
```

### 3.2 Ejemplo

```
GET /integrity/plugins/my-gallery/1.0.0.json
```

### 3.3 Respuesta exitosa (200)

```json
{
    "type": "plugin",
    "id": "my-gallery",
    "version": "1.0.0",
    "generated_at": "2026-03-15T14:30:00Z",
    "algorithm": "sha256",
    "files": {
        "my-gallery.php": "1a2b3c4d5e6f...",
        "includes/gallery-renderer.php": "2b3c4d5e6f7a...",
        "includes/shortcode.php": "3c4d5e6f7a8b...",
        "assets/css/gallery.css": "4d5e6f7a8b9c...",
        "assets/js/gallery.js": "5e6f7a8b9c0d..."
    },
    "exclude": [],
    "signature": "base64-encoded-rsa-signature..."
}
```

### 3.4 Respuestas de error

```json
// 404: Plugin o version no encontrado
{
    "error": "plugin_not_found",
    "message": "No integrity manifest found for plugin 'my-gallery' version 1.0.0"
}
```

---

## 4. Endpoint: Clave Publica de Klytos

### 4.1 Ruta

```
GET /integrity/public-key
```

### 4.2 Respuesta (200)

Devuelve la clave publica en formato PEM:

```
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA...
-----END PUBLIC KEY-----
```

### 4.3 Headers

```
Content-Type: application/x-pem-file
Cache-Control: public, max-age=604800
```

### 4.4 Nota

Esta clave tambien se distribuye embebida en el core de Klytos (`core/keys/klytos-integrity.pub`). El endpoint existe como referencia y para que desarrolladores de terceros puedan verificar manifiestos del Marketplace de forma independiente si lo necesitan.

---

## 5. Endpoint: Listar versiones disponibles

### 5.1 Ruta

```
GET /integrity/core/versions.json
GET /integrity/plugins/{plugin-id}/versions.json
```

### 5.2 Respuesta (200)

```json
{
    "id": "core",
    "versions": [
        {"version": "1.2.0", "generated_at": "2026-04-01T10:00:00Z"},
        {"version": "1.1.0", "generated_at": "2026-03-01T10:00:00Z"},
        {"version": "1.0.0", "generated_at": "2026-02-01T10:00:00Z"}
    ]
}
```

Util para diagnostico y para que el `IntegrityChecker` pueda informar si la version instalada es conocida o no.

---

## 6. Generacion de manifiestos: Core

### 6.1 Proceso (pipeline de build/release)

Cada vez que se publica una nueva version del core de Klytos, el pipeline de CI/CD debe:

1. **Compilar/preparar** la release (como se haga actualmente).
2. **Ejecutar el script de generacion de manifiesto** sobre los archivos de la release.
3. **Firmar el manifiesto** con la clave privada de Klytos.
4. **Subir el manifiesto firmado** a la API.

### 6.2 Script de generacion: `generate-manifest.php`

Este script se ejecuta en el servidor de build, NO se distribuye con el core.

```php
#!/usr/bin/env php
<?php
/**
 * Genera el manifiesto de integridad para una release del core de Klytos.
 *
 * Uso: php generate-manifest.php --type=core --version=1.2.0 --path=/build/klytos --key=/keys/private.pem --output=/manifests/core/
 */

$options = getopt('', ['type:', 'version:', 'path:', 'key:', 'output:', 'id:']);

$type    = $options['type']    ?? 'core';
$version = $options['version'] ?? null;
$path    = $options['path']    ?? null;
$keyFile = $options['key']     ?? null;
$output  = $options['output']  ?? null;
$id      = $options['id']      ?? $type;

if (!$version || !$path || !$keyFile || !$output) {
    echo "Uso: php generate-manifest.php --type=core|plugin --version=X.Y.Z --path=/ruta --key=/clave-privada.pem --output=/salida/ [--id=plugin-id]\n";
    exit(1);
}

// Patrones de exclusion por defecto para el core
$coreExcludes = [
    'config/local.php',
    'config/database.php',
    '.env',
    'storage/*',
    'public/assets/images/*',
    'public/assets/uploads/*',
    'plugins/*',
    'themes/*',
    'core/keys/*',
    '.git/*',
    '.gitignore',
    'node_modules/*',
    'vendor/*',
    'tests/*',
];

// Para plugins no hay exclusiones por defecto
$pluginExcludes = [];

$excludes = ($type === 'core') ? $coreExcludes : $pluginExcludes;

// 1. Escanear archivos y generar hashes
$basePath = rtrim($path, '/');
$files    = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;

    $relativePath = str_replace($basePath . '/', '', $file->getPathname());

    // Aplicar exclusiones
    $excluded = false;
    foreach ($excludes as $pattern) {
        if (fnmatch($pattern, $relativePath)) {
            $excluded = true;
            break;
        }
    }
    if ($excluded) continue;

    $files[$relativePath] = hash_file('sha256', $file->getPathname());
}

// Ordenar por path para consistencia
ksort($files);

// 2. Construir el manifiesto (sin firma)
$manifest = [
    'type'         => $type,
    'id'           => $id,
    'version'      => $version,
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'algorithm'    => 'sha256',
    'files'        => $files,
    'exclude'      => $excludes,
];

// 3. Firmar
$payloadJson = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$privateKey  = file_get_contents($keyFile);

if ($privateKey === false) {
    echo "Error: no se pudo leer la clave privada: {$keyFile}\n";
    exit(1);
}

$signature = '';
$success   = openssl_sign($payloadJson, $signature, $privateKey, OPENSSL_ALGO_SHA256);

if (!$success) {
    echo "Error: no se pudo firmar el manifiesto.\n";
    exit(1);
}

$manifest['signature'] = base64_encode($signature);

// 4. Escribir el archivo
$outputDir  = rtrim($output, '/');
$outputFile = "{$outputDir}/{$version}.json";

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

file_put_contents(
    $outputFile,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

echo "Manifiesto generado: {$outputFile}\n";
echo "Archivos incluidos: " . count($files) . "\n";
echo "Firma: OK\n";
```

### 6.3 Ejemplo de uso en el pipeline

```bash
# Generar manifiesto del core
php generate-manifest.php \
    --type=core \
    --version=1.2.0 \
    --path=/build/klytos-release \
    --key=/secrets/klytos-integrity-private.pem \
    --output=/api-data/integrity/core/

# Generar manifiesto de un plugin del Marketplace
php generate-manifest.php \
    --type=plugin \
    --id=my-gallery \
    --version=1.0.0 \
    --path=/build/plugins/my-gallery \
    --key=/secrets/klytos-integrity-private.pem \
    --output=/api-data/integrity/plugins/my-gallery/
```

---

## 7. Generacion de manifiestos: Plugins del Marketplace

### 7.1 Proceso automatico al publicar

Cuando un desarrollador sube una nueva version de un plugin al Marketplace, el sistema debe:

1. **Recibir el archivo ZIP** del plugin.
2. **Extraer el contenido** en un directorio temporal.
3. **Ejecutar las validaciones** habituales del Marketplace (estructura, seguridad, etc.).
4. **Generar el manifiesto de integridad** usando el mismo script `generate-manifest.php` con `--type=plugin`.
5. **Almacenar el manifiesto** en la ruta correspondiente de la API.
6. **Publicar el plugin** en el Marketplace.

### 7.2 Endpoint interno (solo para el sistema del Marketplace)

```
POST /integrity/internal/generate
Authorization: Bearer {internal-api-token}
Content-Type: application/json

{
    "type": "plugin",
    "id": "my-gallery",
    "version": "1.0.0",
    "files_path": "/tmp/marketplace-uploads/my-gallery-1.0.0/"
}
```

Respuesta (201):

```json
{
    "status": "created",
    "manifest_url": "/integrity/plugins/my-gallery/1.0.0.json",
    "files_count": 15,
    "generated_at": "2026-04-01T10:00:00Z"
}
```

Este endpoint es interno y requiere autenticacion con un token de servicio. No es accesible publicamente.

---

## 8. Gestion de claves

### 8.1 Par de claves de Klytos

Se utiliza un par de claves RSA 4096 bits:

- **Clave privada:** solo existe en el servidor de build/API. NUNCA se distribuye. Se almacena en un sistema de secretos (vault, variables de entorno cifradas, etc.).
- **Clave publica:** se distribuye de dos formas:
  - Embebida en el core de Klytos: `core/keys/klytos-integrity.pub`
  - Disponible via API: `GET /integrity/public-key`

### 8.2 Generacion del par de claves

```bash
# Generar clave privada (RSA 4096)
openssl genrsa -out klytos-integrity-private.pem 4096

# Extraer clave publica
openssl rsa -in klytos-integrity-private.pem -pubout -out klytos-integrity.pub
```

### 8.3 Rotacion de claves

Si alguna vez es necesario rotar la clave (compromiso, expiracion, etc.):

1. Generar un nuevo par de claves.
2. Publicar la nueva clave publica en una nueva release del core.
3. La API debe aceptar manifiestos firmados con la clave anterior durante un periodo de transicion (por ejemplo 6 meses).
4. Los manifiestos nuevos se firman con la clave nueva.
5. Pasado el periodo de transicion, retirar la clave anterior.

Para soportar esto, el endpoint `GET /integrity/public-key` podria devolver multiples claves o aceptar un parametro `?version=2`:

```
GET /integrity/public-key?version=current    -> clave actual
GET /integrity/public-key?version=previous   -> clave anterior (periodo de transicion)
```

---

## 9. Almacenamiento en el servidor

### 9.1 Estructura de archivos en api.klytos.io

```
/api-data/integrity/
    core/
        versions.json
        1.0.0.json
        1.1.0.json
        1.2.0.json
    plugins/
        my-gallery/
            versions.json
            1.0.0.json
            1.1.0.json
        contact-form/
            versions.json
            1.0.0.json
    public-key.pem
```

Los manifiestos son archivos JSON estaticos. La API puede servirse directamente con un servidor web (nginx/Apache) sin necesidad de un framework backend, simplemente sirviendo archivos estaticos desde este directorio. Esto es lo mas eficiente y facil de cachear.

### 9.2 Generacion automatica de versions.json

Cada vez que se anade un nuevo manifiesto (core o plugin), el script debe regenerar el `versions.json` correspondiente:

```php
/**
 * Actualizar el archivo versions.json de un directorio de manifiestos.
 */
function updateVersionsIndex(string $dir, string $id): void
{
    $versions = [];

    foreach (glob("{$dir}/*.json") as $file) {
        $basename = basename($file, '.json');
        if ($basename === 'versions') continue;

        $data = json_decode(file_get_contents($file), true);
        if (!$data) continue;

        $versions[] = [
            'version'      => $data['version'] ?? $basename,
            'generated_at' => $data['generated_at'] ?? '',
        ];
    }

    // Ordenar por version descendente
    usort($versions, function ($a, $b) {
        return version_compare($b['version'], $a['version']);
    });

    $index = [
        'id'       => $id,
        'versions' => $versions,
    ];

    file_put_contents(
        "{$dir}/versions.json",
        json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}
```

---

## 10. Configuracion del servidor web

### 10.1 Ejemplo de configuracion nginx

```nginx
server {
    listen 443 ssl;
    server_name api.klytos.io;

    # ... SSL config ...

    # Integridad: servir archivos estaticos JSON
    location /integrity/ {
        alias /api-data/integrity/;
        default_type application/json;
        add_header Cache-Control "public, max-age=86400";
        add_header Access-Control-Allow-Origin "*";
        add_header X-Content-Type-Options "nosniff";

        # La clave publica tiene su propio content-type
        location = /integrity/public-key {
            alias /api-data/integrity/public-key.pem;
            default_type application/x-pem-file;
            add_header Cache-Control "public, max-age=604800";
        }
    }
}
```

### 10.2 CORS

Los manifiestos son publicos y se acceden desde cualquier instalacion de Klytos, por lo que se necesita `Access-Control-Allow-Origin: *`.

---

## 11. Rate limiting

Aunque los manifiestos son archivos estaticos y ligeros, es recomendable aplicar rate limiting para evitar abusos:

```nginx
# En el bloque http de nginx
limit_req_zone $binary_remote_addr zone=integrity:10m rate=10r/m;

# En el location de integrity
location /integrity/ {
    limit_req zone=integrity burst=20 nodelay;
    # ... resto de config ...
}
```

10 peticiones por minuto por IP es mas que suficiente para el uso normal (una instalacion de Klytos hace como mucho 1 peticion al core + N peticiones a plugins, una vez al dia).

---

## 12. Monitorizacion

### 12.1 Metricas recomendadas

- Numero de peticiones a `/integrity/` por dia (uso general del sistema).
- Numero de 404 (versiones solicitadas que no existen; puede indicar instalaciones con versiones muy antiguas o incorrectas).
- Tamano total del directorio de manifiestos (para planificar almacenamiento).

### 12.2 Alertas

- Si el pipeline de build no genera el manifiesto para una nueva release (fallo en la generacion).
- Si el archivo `versions.json` no se actualiza tras una publicacion.

---

## 13. Orden de implementacion

1. Generar el par de claves RSA 4096 y almacenar la privada de forma segura.
2. Crear el script `generate-manifest.php` y probarlo localmente.
3. Integrar el script en el pipeline de build del core.
4. Crear la estructura de directorios en el servidor de api.klytos.io.
5. Configurar nginx para servir los manifiestos como archivos estaticos.
6. Generar el primer manifiesto del core para la version actual.
7. Integrar la generacion automatica en el flujo de publicacion del Marketplace.
8. Implementar el endpoint interno `POST /integrity/internal/generate`.
9. Implementar la generacion automatica de `versions.json`.
10. Configurar rate limiting y CORS.
11. Probar la cadena completa: publicar version -> generar manifiesto -> verificar desde una instalacion de Klytos.

---

## 14. Notas importantes

- **Los manifiestos son inmutables.** Una vez publicado el manifiesto para core 1.2.0, no se modifica. Si se descubre un error, se publica una nueva version (1.2.1) con su propio manifiesto.
- **Los manifiestos de plugins del Marketplace los genera Klytos, no el desarrollador.** Esto evita que un plugin malicioso incluya hashes de archivos maliciosos.
- **La API de integridad es de solo lectura (publica).** La escritura solo ocurre desde el pipeline de build y el sistema del Marketplace (endpoints internos).
- **El script `generate-manifest.php` nunca se distribuye con el core.** Es una herramienta interna del servidor de build.
- **Los manifiestos son archivos estaticos.** No hay base de datos ni logica de servidor para servirlos. Esto maximiza la velocidad y minimiza la superficie de ataque.
