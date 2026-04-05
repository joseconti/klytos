# KLYTOS — Sistema de Niveles de Encriptación y Recuperación

> **Documento:** KLYTOS-ENCRYPTION-RECOVERY.md
> **Versión:** 1.1.0
> **Fecha:** 2026-04-05
> **Autor:** José Conti
> **Requisito:** KLYTOS-ARCHITECTURE.md §5 (Encryption), §4 (Instalación)
> **Repositorio installer:** github.com/joseconti/klytos-installer
> **Estado:** Arquitectura definida — pendiente de implementación

---

## 1. Resumen Ejecutivo

Este documento define tres capas del sistema de seguridad de Klytos:

1. **Niveles de encriptación** — el usuario elige durante la instalación cuántos datos se encriptan (Básica, Media, Profesional). El nivel se puede cambiar en ambas direcciones después.
2. **Archivos de recuperación** — dos archivos criptográficos que el usuario descarga y guarda fuera del servidor para poder recuperar el acceso en caso de emergencia.
3. **Installer unificado** — `installer.php` evoluciona de un simple bootstrap de descarga a un punto de entrada multi-modo: instalación, recuperación de acceso, restauración de backup, migración de hosting y actualización de conexión a base de datos.

### 1.1 Principio Fundamental

> **La seguridad de una instalación de Klytos es responsabilidad del propietario del sitio.**
> Klytos proporciona las herramientas para proteger los datos y recuperar el acceso, pero el usuario es el único responsable de custodiar los archivos de recuperación. Sin ellos, los datos encriptados son **irrecuperables**.

---

## 2. Niveles de Encriptación

### 2.1 Definición de Niveles

El nivel de encriptación se elige durante la instalación y determina qué archivos se almacenan con extensión `.json.enc` (encriptados con AES-256-GCM) y cuáles como `.json` (texto plano).

#### Básica

Encripta únicamente la configuración crítica del sistema.

**Recomendado para:** sitios personales, portfolios, webs de pruebas.

**Datos encriptados:**

| Archivo | Contenido |
|---------|-----------|
| `config/config.json.enc` | Credenciales admin, MCP secret, ajustes del sistema |
| `config/license.json.enc` | Clave de licencia, dominio, estado |
| `config/ai-keys.json.enc` | API keys de proveedores de IA |
| `data/tokens.json.enc` | Bearer tokens MCP (hasheados) |

**Datos NO encriptados:** páginas, bloques, templates, tema, menús, usuarios adicionales, logs, chats, formularios.

#### Media

Encripta todo lo anterior más cualquier dato que contenga información personal o sensible de usuarios.

**Recomendado para:** sitios corporativos, webs con múltiples editores.

**Datos adicionales encriptados (sobre Básica):**

| Archivo/Directorio | Contenido | Razón |
|---------------------|-----------|-------|
| `data/users/` | Usuarios, contraseñas, emails, roles | Datos personales (GDPR) |
| `data/audit-log/` | Registro de acciones: quién, qué, cuándo | Contiene IPs y actividad de usuarios |
| `data/sessions/` | Sesiones activas | Tokens de sesión reutilizables |
| `data/chats/` | Conversaciones del chat IA | Pueden contener datos sensibles del negocio |
| `data/2fa/` | Configuración 2FA por usuario | Secretos TOTP |

#### Profesional

Encripta absolutamente todos los datos del sitio.

**Recomendado para:** sitios que manejan datos regulados, información confidencial, o que requieren máxima protección.

> ⚠️ **ADVERTENCIA CRÍTICA:** Con el nivel Profesional, la pérdida de la clave de encriptación significa perder **TODO** el contenido del sitio de forma irreversible. No hay forma de recuperar los datos sin la clave.

**Datos adicionales encriptados (sobre Media):**

| Archivo/Directorio | Contenido |
|---------------------|-----------|
| `data/pages/` | Contenido de todas las páginas |
| `data/blocks/` | Definiciones de bloques reutilizables |
| `data/templates.json` | Plantillas HTML personalizadas |
| `data/site.json` | Metadatos del sitio (nombre, SEO, social) |
| `data/theme.json` | Configuración del tema (colores, fuentes, layout) |
| `data/menus.json` | Estructura de navegación |
| `data/forms/` | Envíos de formularios |
| `data/logs/` | Logs del sistema |
| `data/webhooks.json` | Configuración de webhooks |
| `data/ai-models-cache.json` | Cache de modelos IA |
| `data/update_cache.json` | Cache de actualizaciones |
| `data/update_log.json` | Historial de actualizaciones |

### 2.2 Tabla Comparativa Completa

| Dato | Básica | Media | Profesional |
|------|:------:|:-----:|:-----------:|
| Config del sistema | ✅ | ✅ | ✅ |
| Licencia | ✅ | ✅ | ✅ |
| API keys IA | ✅ | ✅ | ✅ |
| Tokens MCP | ✅ | ✅ | ✅ |
| Usuarios y credenciales | ❌ | ✅ | ✅ |
| Audit log | ❌ | ✅ | ✅ |
| Sesiones | ❌ | ✅ | ✅ |
| Chats IA | ❌ | ✅ | ✅ |
| 2FA | ❌ | ✅ | ✅ |
| Páginas | ❌ | ❌ | ✅ |
| Bloques | ❌ | ❌ | ✅ |
| Templates | ❌ | ❌ | ✅ |
| Metadatos del sitio | ❌ | ❌ | ✅ |
| Tema | ❌ | ❌ | ✅ |
| Menús | ❌ | ❌ | ✅ |
| Formularios | ❌ | ❌ | ✅ |
| Logs del sistema | ❌ | ❌ | ✅ |
| Webhooks | ❌ | ❌ | ✅ |
| Caches | ❌ | ❌ | ✅ |

### 2.3 Cambio de Nivel Post-Instalación

El nivel de encriptación se puede cambiar **en ambas direcciones**, bajo responsabilidad del usuario.

#### Subir de nivel (Básica → Media → Profesional)

Operación segura. Los archivos que ahora deben encriptarse se cifran con la clave existente. Se hace desde Configuración > Seguridad. Requiere re-autenticación (contraseña + 2FA).

#### Bajar de nivel (Profesional → Media → Básica)

Operación permitida pero con advertencia explícita. Los archivos que dejan de requerir encriptación se descifran y se almacenan como `.json` plano.

**¿Cuál es el riesgo?** No se pierden datos. Bajar de nivel no elimina información, simplemente retira la protección de encriptación. Los archivos siguen existiendo con el mismo contenido, pero en texto plano. El riesgo es que si un atacante obtiene acceso al filesystem del servidor (hosting comprometido, backup sin encriptar robado, acceso FTP comprometido), esos datos serán legibles directamente.

**Protecciones para bajar de nivel:**

1. Requiere re-introducir la contraseña del admin.
2. Requiere confirmar el 2FA si está activo.
3. Se muestra advertencia con lista explícita de los datos que dejarán de estar protegidos.
4. Checkbox de aceptación de responsabilidad obligatorio.
5. Se registra en audit log como evento crítico.
6. Se envía notificación por email al admin.

```
┌──────────────────────────────────────────────────────────────┐
│  ⚠️ ATENCIÓN — Reducción del nivel de encriptación           │
│                                                               │
│  Va a cambiar el nivel de encriptación de Profesional        │
│  a Media. Esto significa que los siguientes datos dejarán    │
│  de estar encriptados y serán accesibles en texto plano      │
│  en el servidor:                                              │
│                                                               │
│  • Contenido de todas las páginas                            │
│  • Definiciones de bloques                                    │
│  • Plantillas, menús, tema                                   │
│  • Metadatos del sitio                                        │
│  • Envíos de formularios                                      │
│  • Logs del sistema                                           │
│                                                               │
│  Si alguien obtiene acceso al servidor (hosting               │
│  comprometido, backup robado), estos datos serán legibles.   │
│                                                               │
│  Esta operación se realizará bajo su responsabilidad.        │
│                                                               │
│  Contraseña actual: [________________________]               │
│  Código 2FA:        [______]                                  │
│                                                               │
│  □ Entiendo los riesgos y acepto la responsabilidad          │
│                                                               │
│  [Cancelar]                      [Confirmar y bajar nivel]   │
└──────────────────────────────────────────────────────────────┘
```

### 2.4 Implementación en Storage

```php
class Storage {
    private const ENCRYPTION_LEVELS = [
        'basic'        => 0,
        'medium'       => 1,
        'professional' => 2,
    ];

    private const ENCRYPTED_PATHS = [
        'basic' => [
            'config/config.json',
            'config/license.json',
            'config/ai-keys.json',
            'data/tokens.json',
        ],
        'medium' => [
            'data/users/',
            'data/audit-log/',
            'data/sessions/',
            'data/chats/',
            'data/2fa/',
        ],
        'professional' => [
            'data/pages/',
            'data/blocks/',
            'data/templates.json',
            'data/site.json',
            'data/theme.json',
            'data/menus.json',
            'data/forms/',
            'data/logs/',
            'data/webhooks.json',
            'data/ai-models-cache.json',
            'data/update_cache.json',
            'data/update_log.json',
        ],
    ];

    public function shouldEncrypt(string $filePath): bool
    {
        $level = $this->getEncryptionLevel();
        foreach (self::ENCRYPTION_LEVELS as $levelName => $levelNum) {
            if ($levelNum > self::ENCRYPTION_LEVELS[$level]) break;
            foreach (self::ENCRYPTED_PATHS[$levelName] as $pattern) {
                if (str_ends_with($pattern, '/') && str_starts_with($filePath, $pattern)) return true;
                if ($filePath === $pattern) return true;
            }
        }
        return false;
    }

    public function read(string $file): array
    {
        $encPath = $this->dataDir . '/' . $file . '.enc';
        $rawPath = $this->dataDir . '/' . str_replace('.enc', '', $file);
        if (file_exists($encPath)) return $this->encryption->decrypt(file_get_contents($encPath));
        if (file_exists($rawPath)) return json_decode(file_get_contents($rawPath), true);
        throw new \RuntimeException("File not found: {$file}");
    }

    public function write(string $file, array $data): void
    {
        if ($this->shouldEncrypt($file)) {
            $encoded = $this->encryption->encrypt($data);
            file_put_contents($this->dataDir . '/' . $file . '.enc', $encoded);
            $rawPath = $this->dataDir . '/' . $file;
            if (file_exists($rawPath)) unlink($rawPath);
        } else {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($this->dataDir . '/' . $file, $json);
            $encPath = $this->dataDir . '/' . $file . '.enc';
            if (file_exists($encPath)) unlink($encPath);
        }
    }

    public function changeEncryptionLevel(string $newLevel): void
    {
        $currentLevel = $this->getEncryptionLevel();
        $currentNum = self::ENCRYPTION_LEVELS[$currentLevel];
        $newNum = self::ENCRYPTION_LEVELS[$newLevel];
        if ($newNum === $currentNum) return;

        if ($newNum > $currentNum) {
            foreach (self::ENCRYPTION_LEVELS as $levelName => $levelNum) {
                if ($levelNum <= $currentNum) continue;
                if ($levelNum > $newNum) break;
                foreach (self::ENCRYPTED_PATHS[$levelName] as $p) $this->encryptPath($p);
            }
        } else {
            foreach (self::ENCRYPTION_LEVELS as $levelName => $levelNum) {
                if ($levelNum <= $newNum) continue;
                if ($levelNum > $currentNum) break;
                foreach (self::ENCRYPTED_PATHS[$levelName] as $p) $this->decryptPath($p);
            }
        }
        $this->setEncryptionLevel($newLevel);
    }

    private function encryptPath(string $pattern): void
    {
        if (str_ends_with($pattern, '/')) {
            $dir = $this->dataDir . '/' . rtrim($pattern, '/');
            if (!is_dir($dir)) return;
            foreach (glob($dir . '/*.json') as $file) {
                $data = json_decode(file_get_contents($file), true);
                file_put_contents($file . '.enc', $this->encryption->encrypt($data));
                unlink($file);
            }
        } else {
            $rawPath = $this->dataDir . '/' . $pattern;
            if (!file_exists($rawPath)) return;
            $data = json_decode(file_get_contents($rawPath), true);
            file_put_contents($rawPath . '.enc', $this->encryption->encrypt($data));
            unlink($rawPath);
        }
    }

    private function decryptPath(string $pattern): void
    {
        if (str_ends_with($pattern, '/')) {
            $dir = $this->dataDir . '/' . rtrim($pattern, '/');
            if (!is_dir($dir)) return;
            foreach (glob($dir . '/*.json.enc') as $file) {
                $data = $this->encryption->decrypt(file_get_contents($file));
                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                file_put_contents(str_replace('.enc', '', $file), $json);
                unlink($file);
            }
        } else {
            $encPath = $this->dataDir . '/' . $pattern . '.enc';
            if (!file_exists($encPath)) return;
            $data = $this->encryption->decrypt(file_get_contents($encPath));
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($this->dataDir . '/' . $pattern, $json);
            unlink($encPath);
        }
    }
}
```

### 2.5 Almacenamiento del Nivel

En `config/config.json.enc`:

```json
{
  "site_name": "...",
  "encryption_level": "medium",
  "identity_fingerprint": "sha256:a1b2c3d4...",
  "recovery_keys_confirmed": false,
  "recovery_keys_confirmed_at": null,
  "identity_last_downloaded_at": null,
  "identity_download_count": 0
}
```

---

## 3. Archivos de Recuperación

Se generan durante la instalación. Son la **única forma** de recuperar el acceso a un sitio encriptado.

### 3.1 Archivo de Encriptación — `klytos-encryption.key`

**¿Qué es?** La clave AES-256-GCM (32 bytes, codificada en base64) que se usa para encriptar/desencriptar todos los archivos `.json.enc`.

**¿Dónde se almacena en el servidor?** `config/.encryption_key`

**¿Se puede descargar desde el admin?** **NO, NUNCA.** Solo accesible vía FTP/SFTP o gestor de archivos del hosting.

**Formato del archivo:**

```
-----BEGIN KLYTOS ENCRYPTION KEY-----
Generado: 2026-04-05T12:00:00Z
Sitio: https://ejemplo.com/klytos
Nivel: medium
---
dGhpcyBpcyBhIGJhc2U2NCBlbmNvZGVkIGtleQ==
-----END KLYTOS ENCRYPTION KEY-----
```

### 3.2 Archivo de Identidad — `klytos-identity.pem`

**¿Qué es?** La clave privada RSA-2048 vinculada al administrador original. Prueba criptográfica de propiedad del sitio.

**¿Se puede descargar desde el admin?** **SÍ**, con protecciones: re-autenticación (contraseña + 2FA), rate limit 24h, audit log, notificación email.

**Justificación:** La clave de identidad sin la clave de encriptación no sirve para nada — el installer requiere ambos archivos.

**Almacenamiento en el servidor:**

```
config/
├── .encryption_key               ← Clave AES-256-GCM (32 bytes raw)
├── admin-identity.pub.enc        ← Clave pública RSA (encriptada con AES)
└── admin-identity.priv.enc       ← Clave privada RSA (encriptada con AES)
```

**Formato del archivo:**

```
-----BEGIN KLYTOS IDENTITY KEY-----
Generado: 2026-04-05T12:00:00Z
Sitio: https://ejemplo.com/klytos
Usuario: admin
Fingerprint: sha256:a1b2c3d4...
---
-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEA...
-----END RSA PRIVATE KEY-----
-----END KLYTOS IDENTITY KEY-----
```

### 3.3 Generación durante la Instalación

```php
// 1. Generar par RSA-2048
$keyPair = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);

// 2. Extraer claves
openssl_pkey_export($keyPair, $privateKeyPem);
$publicKeyPem = openssl_pkey_get_details($keyPair)['key'];
$fingerprint = 'sha256:' . hash('sha256', $publicKeyPem);

// 3. Almacenar en servidor (encriptadas)
$encryption->writeRaw('config/admin-identity.pub.enc', $encryption->encrypt([
    'public_key' => $publicKeyPem,
    'fingerprint' => $fingerprint,
    'created_at' => date('c'),
    'admin_user' => $adminUsername,
]));

$encryption->writeRaw('config/admin-identity.priv.enc', $encryption->encrypt([
    'private_key' => $privateKeyPem,
    'fingerprint' => $fingerprint,
    'created_at' => date('c'),
    'admin_user' => $adminUsername,
]));

// 4. Preparar archivos de descarga para el usuario
$identityFile = "-----BEGIN KLYTOS IDENTITY KEY-----\n"
    . "Generado: " . date('c') . "\n"
    . "Sitio: {$siteUrl}\n"
    . "Usuario: {$adminUsername}\n"
    . "Fingerprint: {$fingerprint}\n"
    . "---\n" . $privateKeyPem
    . "-----END KLYTOS IDENTITY KEY-----\n";

$encryptionKeyFile = "-----BEGIN KLYTOS ENCRYPTION KEY-----\n"
    . "Generado: " . date('c') . "\n"
    . "Sitio: {$siteUrl}\n"
    . "Nivel: {$encryptionLevel}\n"
    . "---\n" . base64_encode(file_get_contents('config/.encryption_key')) . "\n"
    . "-----END KLYTOS ENCRYPTION KEY-----\n";
```

---

## 4. Installer Unificado (`installer.php`)

### 4.1 Evolución del Concepto

El `installer.php` actual (repositorio `klytos-installer`) es un bootstrap que descarga el zip de Klytos desde GitHub y redirige al `install.php` del paquete. Este concepto se amplía: `installer.php` se convierte en el **punto de entrada único** para todas las operaciones que requieren acceso directo al filesystem fuera del contexto del admin panel.

### 4.2 Modos de Operación

```
┌──────────────────────────────────────────────────────────────────┐
│                                                                   │
│  [Logo Klytos]                                                    │
│                                                                   │
│  Klytos — Utilidad de Instalación y Recuperación                 │
│                                                                   │
│  Seleccione la acción a realizar:                                │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  🆕 Instalar Klytos                                        │  │
│  │  Descarga e instala la última versión estable.             │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  🔑 Recuperar acceso al admin                              │  │
│  │  Ha perdido contraseña, 2FA o acceso al panel.             │  │
│  │  Necesita: klytos-encryption.key + klytos-identity.pem     │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  💾 Restaurar un backup                                    │  │
│  │  Restaurar datos en servidor nuevo o instalación limpia.   │  │
│  │  Necesita: klytos-encryption.key + klytos-identity.pem     │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  🔄 Migrar a otro hosting                                  │  │
│  │  Actualizar dominio, rutas y licencia tras mover archivos. │  │
│  │  Necesita: klytos-encryption.key + klytos-identity.pem     │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │  🗄️ Actualizar conexión a base de datos                    │  │
│  │  Cambiar host, usuario, contraseña o nombre de la BD.      │  │
│  │  Necesita: klytos-encryption.key + klytos-identity.pem     │  │
│  └────────────────────────────────────────────────────────────┘  │
│                                                                   │
│  [EN | ES | CA | FR | DE | PT | IT]                              │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

### 4.3 Detección Automática de Contexto

```php
$hasInstallation = file_exists(__DIR__ . '/install/core/App.php')
                || file_exists(__DIR__ . '/core/App.php');
$hasEncKey       = file_exists(__DIR__ . '/install/config/.encryption_key')
                || file_exists(__DIR__ . '/config/.encryption_key');
$hasEncFiles     = !empty(glob(__DIR__ . '/install/data/*.enc'))
                || !empty(glob(__DIR__ . '/data/*.enc'));
$hasDatabase     = file_exists(__DIR__ . '/install/config/database.json.enc')
                || file_exists(__DIR__ . '/config/database.json.enc');

$modes = ['install' => true];  // Siempre disponible
if ($hasInstallation && $hasEncKey)  $modes['recover']  = true;
if ($hasEncFiles && !$hasEncKey)     $modes['restore']  = true;
if ($hasInstallation && $hasEncKey)  $modes['migrate']  = true;
if ($hasInstallation && $hasDatabase) $modes['database'] = true;
```

Los modos no disponibles se muestran deshabilitados con explicación.

### 4.4 Modo: Instalar Klytos

Hereda el comportamiento actual del `installer.php`: verificar requisitos, descargar zip de GitHub, extraer, redirigir a `install/install.php`.

El `install.php` real (dentro del paquete) ahora incluye los pasos adicionales de nivel de encriptación y descarga de archivos de recuperación (ver §5).

### 4.5 Modo: Recuperar Acceso al Admin

**Requiere:** `klytos-encryption.key` + `klytos-identity.pem`

**Flujo:**

1. Subir ambos archivos.
2. Verificación criptográfica:
   - Parsear `.key` → extraer clave AES-256-GCM.
   - Descifrar `config/admin-identity.pub.enc` con esa clave.
   - Parsear `.pem` → extraer clave privada RSA.
   - Challenge-response: firmar 32 bytes aleatorios con la privada, verificar con la pública.
   - Doble check con fingerprint almacenado.
3. Acciones disponibles:
   - Desactivar 2FA.
   - Resetear contraseña (min 12 chars).
4. Recomendar eliminar `installer.php` tras uso.

```php
// Verificación criptográfica
$encryptionKey = base64_decode($keyContent['key']);
$encryption = new Encryption($encryptionKey);
$identityData = $encryption->decrypt(file_get_contents($root . '/config/admin-identity.pub.enc'));
$publicKey = $identityData['public_key'];

$privateKey = openssl_pkey_get_private($pemContent['private_key']);
$challenge = random_bytes(32);
openssl_sign($challenge, $signature, $privateKey, OPENSSL_ALGO_SHA256);
$verified = openssl_verify($challenge, $signature, openssl_pkey_get_public($publicKey), OPENSSL_ALGO_SHA256);

if ($verified !== 1) die('Identity key does not match.');
```

### 4.6 Modo: Restaurar un Backup

**Requiere:** `klytos-encryption.key` + `klytos-identity.pem`

Detecta que hay archivos `.enc` pero no hay `config/.encryption_key`. Ofrece:

1. Restaurar la clave de encriptación (escribir `.encryption_key` desde el archivo proporcionado).
2. Verificar que puede descifrar `config.json.enc`.
3. Desactivar 2FA y resetear contraseña.

### 4.7 Modo: Migrar a Otro Hosting

**Requiere:** `klytos-encryption.key` + `klytos-identity.pem`

Tras verificar identidad, permite:

1. Cambiar dominio y ruta de instalación.
2. Contactar con `plugins.joseconti.com` para migrar la licencia al nuevo dominio.
3. Regenerar tokens MCP (los anteriores se invalidan).
4. Actualizar `.htaccess` si la ruta cambió.
5. Opcionalmente resetear contraseña.

### 4.8 Modo: Actualizar Conexión a Base de Datos

**Requiere:** `klytos-encryption.key` + `klytos-identity.pem`

Para instalaciones con MySQL/MariaDB (v2.0). Permite cambiar host, usuario, contraseña, nombre de BD y puerto. Incluye botón "Probar conexión" antes de guardar.

### 4.9 Seguridad del Installer

| Medida | Detalle |
|--------|---------|
| **Rate limiting** | 5 intentos/hora por IP (todos los modos excepto instalación) |
| **CSRF** | Token por formulario |
| **No-cache** | `Cache-Control: no-store`, `Pragma: no-cache` |
| **Sin dependencias** | No carga App.php ni core — opera independientemente |
| **HTTPS check** | Advierte si no se accede por HTTPS |
| **Timeout** | Sesión expira en 10 minutos |
| **Logging** | Escribe intentos en archivo temporal del sistema |

### 4.10 Lo que `installer.php` NO Hace

- No carga el framework de Klytos.
- No lee datos del sitio más allá de lo necesario para verificación.
- No expone contenido de archivos encriptados.
- No permite cambiar el nivel de encriptación.
- No permite crear nuevos usuarios.
- No permite acceder al sitio — solo resetea configuración.

### 4.11 Estructura del Código

```php
<?php
/**
 * Klytos — Unified Installer & Recovery Tool
 *
 * Modes: install, recover, restore, migrate, database
 *
 * @package Klytos
 * @license Elastic License 2.0 (ELv2)
 * @copyright 2026 José Conti
 */
declare(strict_types=1);

define('KLYTOS_INSTALLER_VERSION', '2.0.0');
// ... GitHub URLs heredadas del installer actual ...

// Translations (7 idiomas, claves para todos los modos)
$translations = [ 'en' => [...], 'es' => [...], ... ];

// Detect state & available modes
$klytosRoot = detectKlytosRoot(__DIR__);
$state = detectInstallationState($klytosRoot);
$availableModes = determineAvailableModes($state);

// Route
$mode = $_GET['mode'] ?? $_POST['mode'] ?? null;
switch ($mode) {
    case 'install':   handleInstallMode($lang, $t);               break;
    case 'recover':   requireBothKeys(); handleRecoverMode(...);   break;
    case 'restore':   requireBothKeys(); handleRestoreMode(...);   break;
    case 'migrate':   requireBothKeys(); handleMigrateMode(...);   break;
    case 'database':  requireBothKeys(); handleDatabaseMode(...);  break;
    default:          showModeSelector($availableModes, $lang, $t);
}

// Shared verification
function verifyRecoveryKeys(string $root, array $encKey, array $identity): bool { ... }

// Helper functions (inherited)
function klytos_fetch_url(...) { ... }
function klytos_download_file(...) { ... }
function klytos_move_contents(...) { ... }
function klytos_rmdir_recursive(...) { ... }
```

---

## 5. Flujo de Instalación Actualizado

El `install.php` dentro del paquete de Klytos se actualiza con pasos adicionales.

### 5.1 Nuevo Paso — Nivel de Encriptación

Entre el Paso 2 (Configuración) y el Paso Final. Selector con tres opciones, "Media" preseleccionado. Advertencia especial para "Profesional".

### 5.2 Paso Final — Descarga de Archivos de Recuperación

**Bloqueante:** la instalación no finaliza sin descargar ambos archivos y marcar ambos checkboxes.

```
┌─────────────────────────────────────────────────────────────────┐
│  Paso Final — Archivos de Recuperación                           │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ ⚠️ IMPORTANTE — LEA CON ATENCIÓN                           ││
│  │                                                              ││
│  │ Los dos archivos siguientes son la ÚNICA forma de           ││
│  │ recuperar el acceso a su sitio en caso de emergencia.       ││
│  │                                                              ││
│  │ Sin estos archivos, los datos encriptados son              ││
│  │ IRRECUPERABLES. No existe puerta trasera ni proceso        ││
│  │ de soporte que pueda restaurar el acceso.                   ││
│  │                                                              ││
│  │ ESTE ES EL MOMENTO MÁS FÁCIL PARA DESCARGARLOS.           ││
│  │ Después de la instalación, aunque será posible obtener     ││
│  │ ambos archivos, el proceso será más complicado. Y si       ││
│  │ pierde el acceso al panel de administración sin tener      ││
│  │ estos archivos, NO PODRÁ RECUPERAR SU INSTALACIÓN.         ││
│  └─────────────────────────────────────────────────────────────┘│
│                                                                  │
│  1. Clave de encriptación                                        │
│     📥 [Descargar klytos-encryption.key]                         │
│     ⚠️ Después de la instalación, este archivo NO podrá        │
│     descargarse desde el panel. Solo accesible por FTP/SFTP.   │
│     □ He descargado y guardado este archivo.                    │
│                                                                  │
│  2. Clave de identidad                                           │
│     📥 [Descargar klytos-identity.pem]                           │
│     Podrá descargarse desde el panel (con verificación),        │
│     pero si pierde el acceso al panel, no podrá obtenerla.     │
│     □ He descargado y guardado este archivo.                    │
│                                                                  │
│  💡 Guárdelos en gestor de contraseñas, USB o disco externo.   │
│     NUNCA en el propio servidor.                                │
│                                                                  │
│  [Finalizar instalación]  ← Solo con ambos ☑️                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 6. Advertencia Persistente en el Admin

### 6.1 Banner de Seguridad

Mientras `recovery_keys_confirmed === false`:

- Banner fijo rojo/naranja en **todas** las páginas del admin.
- No se puede cerrar definitivamente; solo posponer 24 horas.

```
┌─────────────────────────────────────────────────────────────────┐
│ 🔴 ADVERTENCIA: No ha confirmado la descarga de los archivos   │
│ de recuperación. Podría perder TODOS los datos de forma         │
│ irreversible.  [Ir a Seguridad]              [Recordar en 24h] │
└─────────────────────────────────────────────────────────────────┘
```

### 6.2 Sección Configuración > Seguridad

Nueva sección en sidebar (posición 91). Muestra estado de ambas claves, ubicación FTP para la de encriptación, botón de descarga protegido para la de identidad, nivel actual de encriptación con opción de cambio, y estadísticas de descargas.

Badge rojo en sidebar mientras `recovery_keys_confirmed === false`.

---

## 7. Descarga de Clave de Identidad desde el Admin

### 7.1 Protecciones

1. Solo el owner puede descargar.
2. Re-autenticación con contraseña actual.
3. Verificación 2FA si activo.
4. Rate limit: 1 descarga / 24 horas.
5. Registro en audit log.
6. Notificación por email.

### 7.2 Endpoint

```php
// admin/api/download-identity.php
// 1. requireAuth() + isOwner()
// 2. Verificar contraseña actual
// 3. Verificar 2FA
// 4. Rate limit 24h
// 5. Descifrar admin-identity.priv.enc
// 6. Construir archivo .pem con metadatos
// 7. Audit log + email notification
// 8. Enviar archivo con headers no-cache
```

---

## 8. Backups y Encriptación

### 8.1 Qué Incluye un Backup

| Incluido | No incluido |
|----------|-------------|
| `data/` (todos los archivos) | `config/.encryption_key` |
| `config/config.json.enc` | `config/admin-identity.*.enc` |
| `config/license.json.enc` | `core/`, `admin/` |
| `config/ai-keys.json.enc` | `public/` (se regenera) |
| `templates/` (custom) | `installer.php` |
| `backup-meta.json` | |

### 8.2 Metadatos del Backup

```json
{
    "klytos_version": "1.0.0",
    "backup_date": "2026-04-05T14:30:00Z",
    "encryption_level": "medium",
    "identity_fingerprint": "sha256:a1b2c3d4...",
    "files_encrypted": true,
    "backup_type": "full",
    "php_version": "8.2.0",
    "site_url": "https://ejemplo.com/klytos"
}
```

### 8.3 Flujo de Restauración

1. Nuevo servidor → instalar Klytos (o subir archivos).
2. Subir archivos del backup sobreescribiendo `data/` y `config/*.enc`.
3. Subir `installer.php` → modo "Restaurar backup".
4. Proporcionar `klytos-encryption.key` + `klytos-identity.pem` originales.
5. Installer restaura clave, permite resetear acceso.
6. Post-restauración: verificar, re-activar licencia si cambió dominio, build, eliminar installer.

---

## 9. Cambios en Archivos Existentes

### 9.1 Nuevos Archivos en `config/`

```
config/
├── .encryption_key               ← (existente)
├── admin-identity.pub.enc        ← NUEVO
├── admin-identity.priv.enc       ← NUEVO
├── config.json.enc               ← (existente) + nuevos campos
├── license.json.enc              ← (sin cambios)
└── ai-keys.json.enc              ← (sin cambios)
```

### 9.2 `.htaccess` — Nuevas Reglas

```apache
RewriteRule admin-identity\. - [F,L]
RewriteRule \.pem$ - [F,L]
```

### 9.3 Nuevas Vistas Admin

- `admin/security.php`
- `admin/api/download-identity.php`
- Banner en `admin/templates/header.php`

---

## 10. Decisiones de Diseño

| Decisión | Elección | Razón |
|----------|----------|-------|
| Algoritmo asimétrico | RSA-2048 | Máxima compatibilidad PHP 8.0+ en cualquier hosting |
| Cambio de nivel | Bidireccional | Bajar no pierde datos, solo retira protección. Usuario responsable con re-auth + checkbox. |
| Installer | Unificado multi-modo | Un solo archivo para instalar, recuperar, restaurar, migrar, DB. El usuario ya sube un archivo por FTP, que sirva para todo. |
| Encryption key por HTTP | Nunca post-instalación | Riesgo MITM/XSS/sesión. Solo durante instalación (sin datos reales aún). |
| Identity key por HTTP | Sí, con protecciones | Sin encryption key no sirve. Admin ya tiene acceso total. Re-auth + 2FA + rate limit + email + audit. |
| Private key en servidor | Sí, encriptada con AES | Para re-descarga desde admin. Sin encryption key no se descifra. |
| Backups sin encryption key | Sí | Backup robado no compromete datos encriptados. |
| Backup con metadatos | Sí, `backup-meta.json` plano | Nivel de encriptación y fingerprint sin necesitar la clave. |

---

## 11. Checklist de Implementación

### Instalación (install.php del paquete)

- [ ] Paso de selección de nivel de encriptación
- [ ] Generación par RSA-2048 + almacenamiento encriptado
- [ ] Paso final de descarga con checkboxes bloqueantes
- [ ] Archivos `.key` y `.pem` con metadatos legibles
- [ ] Campos en config: `encryption_level`, `recovery_keys_confirmed`, `identity_fingerprint`

### Storage

- [ ] `shouldEncrypt()`, `changeEncryptionLevel()`, `encryptPath()`, `decryptPath()`
- [ ] Lectura dual `.json` / `.json.enc`
- [ ] Limpieza de versión opuesta al escribir

### Admin

- [ ] Sección Seguridad (posición 91, badge rojo)
- [ ] `admin/security.php` — vista completa
- [ ] Banner persistente (posponer 24h)
- [ ] `admin/api/download-identity.php` con re-auth + rate limit + email + audit
- [ ] UI cambio de nivel bidireccional con advertencias

### Installer unificado (klytos-installer v2.0.0)

- [ ] Selector de modo con detección automática
- [ ] Modo: Instalar (heredado, sin cambios funcionales)
- [ ] Modo: Recuperar acceso (RSA challenge-response + reset)
- [ ] Modo: Restaurar backup (restaurar encryption key + reset)
- [ ] Modo: Migrar hosting (dominio/rutas/licencia/tokens MCP)
- [ ] Modo: Actualizar DB (conexión MySQL/MariaDB)
- [ ] Verificación criptográfica compartida
- [ ] Rate limiting, CSRF, HTTPS check, timeout
- [ ] 7 idiomas

### Backups

- [ ] `backup-meta.json` con `encryption_level` + `identity_fingerprint`
- [ ] NO incluir `.encryption_key` ni `admin-identity.*.enc`

### Seguridad

- [ ] `.htaccess` para `admin-identity.*` y `.pem`
- [ ] Config Nginx equivalente

### Traducciones

- [ ] Claves `security.*`, `security_banner.*`, `install.*` (nuevas) en 7 idiomas
- [ ] Textos del installer unificado en 7 idiomas

---

*Documento extensión de KLYTOS-ARCHITECTURE.md (v1.0).*
*Versión: 1.1.0 — Fecha: 2026-04-05*
