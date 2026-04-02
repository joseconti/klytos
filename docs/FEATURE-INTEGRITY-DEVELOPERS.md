# Guia para Desarrolladores: Verificacion de Integridad de Plugins

## Introduccion

Klytos incluye un sistema de verificacion de integridad que permite a los administradores confirmar que los archivos de los plugins instalados no han sido modificados, eliminados ni se han anadido archivos no autorizados.

Si tu plugin se distribuye a traves del **Marketplace de Klytos**, no necesitas hacer nada. El Marketplace genera automaticamente el manifiesto de integridad por ti.

Si tu plugin se distribuye **fuera del Marketplace** (plugin premium, distribucion privada, etc.), esta guia explica como implementar la verificacion de integridad para que los administradores puedan confiar en la autenticidad de tu plugin.

**Implementar la verificacion de integridad es fundamental para que los administradores de Klytos tengan la certeza de que tu plugin es correcto y no ha sido manipulado.** Los plugins sin verificacion de integridad se marcan visualmente en el panel de administracion y se recomienda a los administradores que contacten con el desarrollador para solicitarla.

---

## 1. Resumen del proceso

El sistema funciona en tres pasos:

1. **Tu generas un manifiesto JSON** con los hashes SHA-256 de todos los archivos de tu plugin.
2. **Firmas el manifiesto** con tu clave privada RSA.
3. **Alojas el manifiesto** en un endpoint publico accesible por HTTPS.
4. **Declaras la URL** del manifiesto en la cabecera de tu plugin.

Cuando un administrador ejecuta la verificacion de integridad, Klytos descarga tu manifiesto, verifica la firma con tu clave publica, y compara los hashes contra los archivos instalados.

---

## 2. Generar tu par de claves

Necesitas un par de claves RSA 4096 bits. Solo se genera una vez:

```bash
# Generar clave privada
openssl genrsa -out mi-plugin-private.pem 4096

# Extraer clave publica
openssl rsa -in mi-plugin-private.pem -pubout -out mi-plugin-public.pem
```

**La clave privada es secreta.** No la incluyas en tu plugin, no la subas a repositorios publicos, no la compartas. Guardala de forma segura.

**La clave publica se comparte.** Debes alojarla en una URL publica (ver seccion 5).

---

## 3. Estructura del manifiesto

El manifiesto es un archivo JSON con la siguiente estructura:

```json
{
    "type": "plugin",
    "id": "mi-plugin-id",
    "version": "2.1.0",
    "generated_at": "2026-04-01T10:00:00Z",
    "algorithm": "sha256",
    "files": {
        "mi-plugin.php": "a3f2b8c1d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1",
        "includes/main-class.php": "b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5",
        "includes/admin.php": "c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6",
        "assets/css/style.css": "d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7",
        "assets/js/main.js": "e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8"
    },
    "exclude": [],
    "signature": "base64-encoded-rsa-signature..."
}
```

### Campos obligatorios

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `type` | string | Siempre `"plugin"` |
| `id` | string | El `plugin_id` de tu plugin (debe coincidir con el nombre del directorio del plugin) |
| `version` | string | La version exacta del plugin (debe coincidir con la version declarada en la cabecera PHP) |
| `generated_at` | string | Fecha/hora de generacion en formato ISO 8601 UTC |
| `algorithm` | string | Siempre `"sha256"` |
| `files` | object | Mapa de `path_relativo => hash_sha256` de cada archivo del plugin |
| `exclude` | array | Lista de patrones glob excluidos de la verificacion (puede estar vacio) |
| `signature` | string | Firma digital del manifiesto codificada en base64 |

### Reglas de los paths

- Los paths son **relativos a la raiz del directorio del plugin**, no a la raiz de Klytos.
- Usa `/` como separador (nunca `\`).
- No incluyas el directorio del plugin en el path. Correcto: `includes/main.php`. Incorrecto: `mi-plugin/includes/main.php`.
- Incluye **todos** los archivos que se distribuyen con el plugin. Si un archivo no esta en el manifiesto, Klytos lo marcara como "archivo anadido no autorizado".

### Exclusiones

El campo `exclude` permite indicar archivos o patrones que no deben verificarse. Usalo solo para archivos que legitimamente cambian en cada instalacion (por ejemplo, archivos de cache generados por el plugin). En la mayoria de los casos, este campo estara vacio.

---

## 4. Generar y firmar el manifiesto

### 4.1 Script de ejemplo en PHP

Puedes usar este script en tu proceso de build/release:

```php
#!/usr/bin/env php
<?php
/**
 * Genera el manifiesto de integridad para un plugin de Klytos.
 *
 * Uso: php generate-integrity.php --id=mi-plugin --version=2.1.0 --path=./dist --key=./mi-plugin-private.pem --output=./manifests/
 */

$options = getopt('', ['id:', 'version:', 'path:', 'key:', 'output:']);

$id      = $options['id']      ?? null;
$version = $options['version'] ?? null;
$path    = $options['path']    ?? null;
$keyFile = $options['key']     ?? null;
$output  = $options['output']  ?? null;

if (!$id || !$version || !$path || !$keyFile || !$output) {
    echo "Uso: php generate-integrity.php --id=plugin-id --version=X.Y.Z --path=./dist --key=./private.pem --output=./manifests/\n";
    exit(1);
}

$basePath = rtrim(realpath($path), '/');

if (!$basePath || !is_dir($basePath)) {
    echo "Error: el directorio '{$path}' no existe.\n";
    exit(1);
}

// 1. Escanear archivos y generar hashes
$files    = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;

    $relativePath = str_replace($basePath . '/', '', $file->getPathname());
    // Normalizar separadores a /
    $relativePath = str_replace('\\', '/', $relativePath);

    $files[$relativePath] = hash_file('sha256', $file->getPathname());
}

ksort($files);

echo "Archivos encontrados: " . count($files) . "\n";

// 2. Construir manifiesto sin firma
$manifest = [
    'type'         => 'plugin',
    'id'           => $id,
    'version'      => $version,
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'algorithm'    => 'sha256',
    'files'        => $files,
    'exclude'      => [],
];

// 3. Firmar
$payloadJson = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$privateKey  = file_get_contents($keyFile);

if ($privateKey === false) {
    echo "Error: no se pudo leer la clave privada.\n";
    exit(1);
}

$signature = '';
$success   = openssl_sign($payloadJson, $signature, $privateKey, OPENSSL_ALGO_SHA256);

if (!$success) {
    echo "Error: no se pudo firmar el manifiesto.\n";
    exit(1);
}

$manifest['signature'] = base64_encode($signature);

// 4. Escribir
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
echo "Firma: OK\n";
```

### 4.2 Ejemplo de uso

```bash
php generate-integrity.php \
    --id=premium-seo-tool \
    --version=2.1.0 \
    --path=./dist/premium-seo-tool \
    --key=./keys/mi-plugin-private.pem \
    --output=./public/klytos/integrity/
```

### 4.3 Importante: cuando generar el manifiesto

Genera el manifiesto **despues** de que los archivos esten en su estado final de distribucion. Si minificas CSS/JS, compilas assets o haces cualquier transformacion, el manifiesto debe generarse sobre los archivos finales, no sobre los fuentes.

---

## 5. Alojar el manifiesto y la clave publica

### 5.1 Estructura de URLs

Necesitas alojar dos cosas en tu servidor:

1. **Los manifiestos** de cada version del plugin.
2. **Tu clave publica** (un unico archivo).

Ejemplo de estructura:

```
https://api.tudominio.com/klytos/integrity/
    2.0.0.json
    2.1.0.json
    public-key.pem
```

### 5.2 Requisitos

- Las URLs deben ser accesibles por **HTTPS** (obligatorio).
- Los manifiestos deben servirse con `Content-Type: application/json`.
- La clave publica debe servirse con `Content-Type: application/x-pem-file` (o `text/plain`).
- Se recomienda configurar CORS con `Access-Control-Allow-Origin: *` para evitar problemas.
- Se recomienda cachear los manifiestos (`Cache-Control: public, max-age=86400`).

### 5.3 Hosting sencillo

Si no tienes una API, puedes alojar los manifiestos como archivos estaticos en cualquier servidor web, en un bucket de S3/R2, o incluso en GitHub Pages. Lo unico que necesitas es que las URLs sean estables y accesibles.

---

## 6. Declarar la URL en tu plugin

Anade dos campos a la cabecera PHP de tu plugin:

```php
<?php
/**
 * Plugin Name: Premium SEO Tool
 * Plugin URI: https://premiumdev.com/seo-tool
 * Description: Herramientas SEO avanzadas para Klytos.
 * Version: 2.1.0
 * Author: Premium Dev
 * Author URI: https://premiumdev.com
 * Text Domain: premium-seo-tool
 * Source: external
 * Integrity URL: https://api.premiumdev.com/klytos/integrity/{version}.json
 * Integrity Key URL: https://api.premiumdev.com/klytos/integrity/public-key.pem
 */
```

### Campos nuevos

| Campo | Obligatorio | Descripcion |
|-------|-------------|-------------|
| `Source` | No | Indica `external` para plugins fuera del Marketplace. Si no se indica, Klytos lo infiere automaticamente. |
| `Integrity URL` | Si (para verificacion) | URL del manifiesto. Usa `{version}` como placeholder: Klytos lo sustituye por la version instalada del plugin. |
| `Integrity Key URL` | Si (para verificacion) | URL de tu clave publica RSA. Solo se descarga una vez, al instalar el plugin. |

### Sobre el placeholder {version}

Klytos sustituye `{version}` por la version actualmente instalada. Asi con una sola URL cubres todas las versiones:

```
Integrity URL: https://api.premiumdev.com/klytos/integrity/{version}.json

Version 2.0.0 -> https://api.premiumdev.com/klytos/integrity/2.0.0.json
Version 2.1.0 -> https://api.premiumdev.com/klytos/integrity/2.1.0.json
```

---

## 7. Flujo completo paso a paso

Aqui esta el flujo completo desde que publicas una version hasta que un administrador la verifica:

**Tu (desarrollador):**

1. Finalizas el codigo de la version 2.1.0 de tu plugin.
2. Ejecutas el script `generate-integrity.php` sobre los archivos finales.
3. Subes el archivo `2.1.0.json` a tu servidor.
4. Distribuyes el ZIP del plugin con la cabecera que incluye `Integrity URL` e `Integrity Key URL`.

**El administrador:**

5. Instala tu plugin en su sitio Klytos.
6. Klytos detecta `Integrity Key URL`, descarga tu clave publica y la almacena localmente.
7. Klytos muestra el plugin con nivel de confianza "Verificado (Desarrollador)" (escudo amarillo).
8. Periodicamente (o manualmente), Klytos ejecuta la verificacion de integridad.
9. Klytos descarga `https://api.premiumdev.com/klytos/integrity/2.1.0.json`.
10. Klytos verifica la firma del manifiesto con tu clave publica almacenada.
11. Klytos compara los hashes del manifiesto con los archivos locales.
12. Si todo coincide: "OK". Si hay diferencias: alerta al administrador.

---

## 8. Buenas practicas

### 8.1 Automatiza la generacion

Integra la generacion del manifiesto en tu pipeline de CI/CD o en tu script de release. No lo hagas manualmente, porque un olvido dejaria a tus usuarios sin verificacion para esa version.

### 8.2 No modifiques un manifiesto publicado

Si descubres que un manifiesto tiene un error, no lo corrijas in situ. Publica una nueva version del plugin (aunque sea un patch, por ejemplo 2.1.1) con su propio manifiesto. Klytos detecta si un manifiesto cambia sin que cambie la version y lo marca como sospechoso.

### 8.3 Guarda la clave privada de forma segura

Usa un gestor de secretos, variables de entorno cifradas o similar. Si la clave privada se compromete, un atacante podria firmar manifiestos maliciosos.

### 8.4 Incluye todos los archivos

No omitas archivos del manifiesto. Si un archivo existe en el ZIP de distribucion pero no esta en el manifiesto, Klytos lo reportara como "archivo anadido no autorizado". Incluye todo: PHP, CSS, JS, imagenes, fuentes, archivos de configuracion por defecto, etc.

### 8.5 Manten la URL estable

La URL de tus manifiestos debe mantenerse accesible a largo plazo. Los administradores pueden tener versiones antiguas de tu plugin instaladas y Klytos intentara verificarlas. No elimines manifiestos de versiones anteriores.

---

## 9. Verificacion local (para testing)

Puedes verificar que tu manifiesto es correcto antes de publicarlo:

```php
#!/usr/bin/env php
<?php
/**
 * Verifica un manifiesto contra los archivos locales.
 *
 * Uso: php verify-integrity.php --manifest=./manifests/2.1.0.json --path=./dist/mi-plugin --key=./mi-plugin-public.pem
 */

$options = getopt('', ['manifest:', 'path:', 'key:']);

$manifestFile = $options['manifest'] ?? null;
$path         = $options['path']     ?? null;
$keyFile      = $options['key']      ?? null;

if (!$manifestFile || !$path || !$keyFile) {
    echo "Uso: php verify-integrity.php --manifest=./manifest.json --path=./dist --key=./public.pem\n";
    exit(1);
}

$manifest  = json_decode(file_get_contents($manifestFile), true);
$publicKey = file_get_contents($keyFile);
$basePath  = rtrim(realpath($path), '/');

// 1. Verificar firma
$signature = base64_decode($manifest['signature']);
$payload   = $manifest;
unset($payload['signature']);
$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$valid = openssl_verify($payloadJson, $signature, $publicKey, OPENSSL_ALGO_SHA256);

if ($valid !== 1) {
    echo "ERROR: La firma del manifiesto NO es valida.\n";
    exit(1);
}
echo "Firma: VALIDA\n";

// 2. Verificar archivos
$errors = 0;
foreach ($manifest['files'] as $relativePath => $expectedHash) {
    $fullPath = $basePath . '/' . $relativePath;

    if (!file_exists($fullPath)) {
        echo "FALTA:      {$relativePath}\n";
        $errors++;
        continue;
    }

    $localHash = hash_file('sha256', $fullPath);
    if ($localHash !== $expectedHash) {
        echo "MODIFICADO: {$relativePath}\n";
        $errors++;
    }
}

// 3. Detectar archivos extra
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $relativePath = str_replace($basePath . '/', '', $file->getPathname());
    $relativePath = str_replace('\\', '/', $relativePath);
    if (!isset($manifest['files'][$relativePath])) {
        echo "EXTRA:      {$relativePath}\n";
        $errors++;
    }
}

echo "\n";
if ($errors === 0) {
    echo "Verificacion completada: todos los archivos son correctos.\n";
} else {
    echo "Verificacion completada: {$errors} problemas encontrados.\n";
    exit(1);
}
```

---

## 10. Preguntas frecuentes

**Mi plugin genera archivos en tiempo de ejecucion (cache, logs, etc.). Como lo manejo?**
Anade esos paths al campo `exclude` del manifiesto. Por ejemplo: `"exclude": ["cache/*", "logs/*"]`. Klytos no verificara los archivos que coincidan con esos patrones.

**Tengo varias variantes del plugin (lite, pro, etc.). Necesito manifiestos separados?**
Si. Cada variante que tenga archivos diferentes necesita su propio manifiesto con su propia version.

**Puedo usar la misma clave para varios plugins?**
Si, puedes usar el mismo par de claves para todos tus plugins. La clave identifica al desarrollador, no al plugin individual.

**Que pasa si mi servidor esta caido cuando Klytos intenta verificar?**
Klytos cachea los manifiestos localmente (por defecto 24 horas). Si tu servidor no responde y hay una version cacheada, usa esa. Si no hay cache, el plugin se marca con un aviso de "No se pudo verificar" pero sigue funcionando.

**Es obligatorio implementar la verificacion de integridad?**
No es obligatorio tecnicamente: tu plugin funcionara sin ella. Pero los administradores veran un aviso permanente indicando que tu plugin no proporciona verificacion, y se les recomienda contactarte para que la implementes. Para la confianza de tus usuarios, es muy recomendable.

---

## 11. Soporte

Si tienes dudas sobre la implementacion, consulta la documentacion completa en:

```
https://developers.klytos.io/integrity
```

O contacta con el equipo de Klytos en:

```
developers@klytos.io
```
