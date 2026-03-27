# Klytos — Arquitectura Completa v1.0

> **Klytos** (Κλυτός) — Epíteto del dios Hefesto: "El Glorioso Creador".
> CMS flat-file controlado 100% por IA a través de MCP.
> Software propietario con licencia comercial.

---

## 1. Visión del Proyecto

**Klytos** es un CMS flat-file ultraligero, controlado 100% por IA a través del protocolo MCP (Model Context Protocol). Sin base de datos. Sin frameworks pesados. Un solo directorio PHP desplegable en cualquier hosting.

La IA conecta vía MCP y construye el sitio completo: páginas, estilos, navegación, contenido, imágenes — todo desde prompts.

- **Web del proyecto**: https://klytos.io
- **Repositorio privado**: https://github.com/joseconti/klytos
- **Licencia**: Propietaria (comercial, no open source)
- **Servidor de licencias**: https://plugins.joseconti.com
- **item_name**: `Klytos`

---

## 2. Requisitos Técnicos

### 2.1 Stack

- **Lenguaje**: PHP 8.0+ (puro, sin frameworks)
- **Almacenamiento**: Archivos JSON encriptados (AES-256-GCM)
- **Transporte MCP**: Streamable HTTP (POST/GET en un solo endpoint)
- **Frontend generado**: HTML estático + CSS + JS
- **Panel admin**: PHP nativo con autenticación por sesión, multilingüe (JSON)
- **Compatibilidad**: Apache (mod_rewrite), LiteSpeed, Nginx
- **Protección**: Ofuscación de código + sistema de licencias
- **Actualizaciones**: Sistema automático OTA desde plugins.joseconti.com

### 2.2 Requisitos del Servidor

- PHP 8.0+ con extensiones: `openssl`, `json`, `mbstring`, `session`, `curl`, `zip`
- Soporte URL rewriting
- Sin base de datos requerida
- Sin Composer requerido (zero dependencies)
- Conexión saliente HTTPS (para licencia y actualizaciones)

---

## 3. Estructura de Directorios

```
klytos/                             ← Directorio raíz (renombrable)
│
├── index.php                       ← Router principal (front-controller)
├── install.php                     ← Instalador (se elimina tras setup)
├── .htaccess                       ← Rewrite rules (Apache/LiteSpeed)
├── web.config                      ← Rewrite rules (IIS, opcional)
├── VERSION                         ← Versión actual en texto plano (ej: 1.0.0)
│
├── config/                         ← Configuración (se crea en instalación)
│   ├── config.json.enc             ← Config general encriptada
│   ├── license.json.enc            ← Datos de licencia encriptados
│   ├── .encryption_key             ← Clave maestra (256 bits)
│   └── .htaccess                   ← Deny from all
│
├── core/                           ← Motor PHP (ofuscado en distribución)
│   ├── App.php                     ← Bootstrap y clase principal
│   ├── Router.php                  ← Enrutador de peticiones
│   ├── Auth.php                    ← Autenticación (admin + MCP bearer)
│   ├── Encryption.php              ← Wrapper AES-256-GCM
│   ├── Storage.php                 ← CRUD sobre archivos JSON encriptados
│   ├── PageManager.php             ← Gestión de páginas HTML
│   ├── ThemeManager.php            ← Gestión de estilos/tema
│   ├── AssetManager.php            ← Gestión de imágenes/CSS/JS
│   ├── MenuManager.php             ← Gestión de navegación
│   ├── SiteConfig.php              ← Lectura/escritura de config del sitio
│   ├── I18n.php                    ← Sistema de internacionalización del admin
│   ├── License.php                 ← Verificación y gestión de licencia
│   ├── Updater.php                 ← Sistema de actualizaciones OTA
│   ├── Helpers.php                 ← Funciones auxiliares
│   │
│   ├── lang/                       ← Traducciones del admin (JSON)
│   │   ├── en.json                 ← English (fallback por defecto)
│   │   ├── es.json                 ← Español
│   │   ├── ca.json                 ← Català
│   │   ├── fr.json                 ← Français
│   │   ├── de.json                 ← Deutsch
│   │   ├── pt.json                 ← Português
│   │   └── it.json                 ← Italiano
│   │
│   └── MCP/                        ← Implementación MCP
│       ├── Server.php              ← MCP Server (Streamable HTTP)
│       ├── ToolRegistry.php        ← Registro de tools MCP
│       ├── JsonRpc.php             ← Parser/Builder JSON-RPC 2.0
│       ├── TokenAuth.php           ← Validación Bearer token MCP
│       └── Tools/                  ← Tools MCP individuales
│           ├── PageTools.php       ← CRUD de páginas
│           ├── ThemeTools.php      ← Gestión de tema visual
│           ├── MenuTools.php       ← Gestión de navegación
│           ├── SiteTools.php       ← Configuración global del sitio
│           ├── AssetTools.php      ← Gestión de archivos/imágenes
│           ├── TemplateTools.php   ← Gestión de plantillas HTML
│           └── BuildTools.php      ← Generación del sitio estático
│
├── admin/                          ← Panel de administración
│   ├── index.php                   ← Dashboard admin
│   ├── login.php                   ← Login form
│   ├── logout.php                  ← Cerrar sesión
│   ├── settings.php                ← Configuración general
│   ├── pages.php                   ← Listado de páginas
│   ├── theme.php                   ← Editor de tema/colores
│   ├── mcp.php                     ← Estado MCP + gestión de tokens
│   ├── assets.php                  ← Gestor de archivos
│   ├── license.php                 ← Estado y activación de licencia
│   ├── updates.php                 ← Gestión de actualizaciones
│   └── templates/                  ← Vistas parciales del admin
│       ├── header.php
│       ├── footer.php
│       └── sidebar.php
│
├── data/                           ← Datos del sitio (JSON encriptado)
│   ├── site.json.enc               ← Metadatos del sitio
│   ├── pages/                      ← Páginas individuales
│   │   ├── index.json.enc
│   │   └── about.json.enc
│   ├── menus.json.enc              ← Estructura de navegación
│   ├── theme.json.enc              ← Configuración del tema
│   ├── templates.json.enc          ← Plantillas HTML personalizadas
│   ├── tokens.json.enc             ← MCP bearer tokens (hasheados)
│   ├── update_log.json.enc         ← Historial de actualizaciones
│   └── .htaccess                   ← Deny from all
│
├── public/                         ← Sitio generado (output final)
│   ├── index.html                  ← Homepage generada
│   ├── about.html                  ← Páginas generadas
│   ├── en/                         ← Subdirectorio idioma (creado por IA)
│   │   ├── index.html
│   │   └── about.html
│   ├── css/
│   │   └── style.css               ← CSS generado desde tema
│   ├── js/
│   │   └── main.js                 ← JS opcional
│   ├── assets/
│   │   └── images/                 ← Imágenes subidas
│   ├── robots.txt                  ← Generado por build
│   └── sitemap.xml                 ← Generado por build
│
├── templates/                      ← Plantillas base HTML
│   ├── default.html                ← Plantilla por defecto
│   ├── landing.html                ← Landing page
│   ├── blog-post.html              ← Entrada de blog
│   └── blank.html                  ← Lienzo en blanco
│
└── backups/                        ← Backups pre-actualización
    ├── .htaccess                   ← Deny from all
    └── pre-1.0.0/                  ← Snapshot antes de actualizar
```

---

## 4. Flujo de Instalación

### 4.1 Primera Visita → `install.php`

```
1. VERIFICAR REQUISITOS DEL SERVIDOR:
   - PHP >= 8.0
   - Extensiones: openssl, json, mbstring, session, curl, zip
   - Directorio escribible (config/, data/, public/, backups/)
   - mod_rewrite activo (test con archivo temporal)
   ├── Todo OK → Continuar
   └── Falta algo → Mostrar errores con instrucciones claras

2. PASO 1 — LICENCIA:
   - Campo: Clave de licencia (KLYTOS-XXXX-XXXX-XXXX)
   - POST a plugins.joseconti.com/?wc-api=lm-license-api
     Body: {
       action: "activate_license",
       license: "KLYTOS-XXXX-XXXX-XXXX",
       item_name: "Klytos",
       url: "https://dominio-instalacion.tld",
       domain: "dominio-instalacion.tld",
       site_url: "https://dominio-instalacion.tld"
     }
   - Respuesta esperada: { activated: true, license: "valid", salt: "xxx" }
   ├── Válida → Guardar y continuar a Paso 2
   └── Inválida → Mostrar error del servidor, reintentar

3. PASO 2 — CONFIGURACIÓN:
   - Nombre del sitio
   - Idioma del admin (selector: es, en, ca, fr, de, pt, it)
   - Usuario administrador
   - Contraseña (min 12 chars, indicador de fuerza visual)
   - Confirmación de contraseña
   - Email del administrador
   - Descripción breve del sitio
   - Paleta de colores inicial (presets visuales + custom)

4. AL ENVIAR:
   a. Generar encryption_key → random_bytes(32) → config/.encryption_key
   b. Hash password → password_hash(PASSWORD_BCRYPT, cost=12)
   c. Crear config/license.json.enc:
      {
        "license_key": "KLYTOS-XXXX-XXXX-XXXX",
        "license_status": "valid",
        "license_salt": "salt-del-servidor",
        "domain": "dominio-instalacion.tld",
        "site_url": "https://dominio-instalacion.tld",
        "activated_at": "2026-03-26T12:00:00Z",
        "last_verified": "2026-03-26T12:00:00Z",
        "plan": "pro"
      }
   d. Crear config/config.json.enc:
      {
        "site_name": "...",
        "admin_language": "es",
        "admin_user": "...",
        "admin_pass_hash": "$2y$12$...",
        "admin_email": "...",
        "mcp_secret": "random_hex(64)",
        "installed_at": "2026-03-26T12:00:00Z",
        "version": "1.0.0",
        "update_channel": "stable",
        "timezone": "Europe/Madrid"
      }
   e. Crear data/site.json.enc (metadatos públicos del sitio)
   f. Crear data/theme.json.enc (paleta de colores inicial)
   g. Crear data/menus.json.enc (vacío)
   h. Crear data/templates.json.enc (vacío)
   i. Generar primer MCP bearer token → data/tokens.json.enc
   j. Crear public/index.html (placeholder "Sitio en construcción")
   k. Crear public/css/style.css (CSS base desde paleta elegida)
   l. Proteger directorios con .htaccess
   m. Mostrar pantalla de resumen:
      ┌─────────────────────────────────────────────┐
      │  ✅ Klytos instalado correctamente           │
      │                                               │
      │  Panel admin: /klytos/admin/                  │
      │  MCP Endpoint: /klytos/mcp                    │
      │  Bearer Token: abc123...def789                │
      │  (⚠️ Copia este token, no se mostrará otra vez)│
      │                                               │
      │  Para conectar Claude Desktop:                │
      │  {                                            │
      │    "mcpServers": {                            │
      │      "klytos": {                              │
      │        "url": "https://tu-dominio/klytos/mcp",│
      │        "headers": {                           │
      │          "Authorization": "Bearer abc123..."  │
      │        }                                      │
      │      }                                        │
      │    }                                          │
      │  }                                            │
      └─────────────────────────────────────────────┘
   n. Renombrar install.php → .install.done.php
```

### 4.2 Protecciones Post-Instalación

- `config/`, `data/`, `core/`, `backups/` → `.htaccess` con `Deny from all`
- Verificación de `config.json.enc` en cada request al admin/MCP
- Rate limiting en endpoint MCP: 60 req/min por token (archivo contador)
- CSRF tokens en el panel admin (por sesión)
- Bloqueo de login tras 5 intentos fallidos (15 min)

---

## 5. Sistema de Encriptación

### 5.1 Algoritmo

```
Cifrado:    AES-256-GCM
Clave:      256 bits (32 bytes, almacenada en config/.encryption_key)
IV:         12 bytes random generados por cada operación de escritura
Tag:        16 bytes (autenticación GCM)
Formato:    base64( IV[12] + TAG[16] + CIPHERTEXT[n] )
Extensión:  .json.enc
```

### 5.2 Clase Encryption.php — Interfaz

```php
class Encryption {
    public function __construct(string $keyPath);
    public function encrypt(array $data): string;     // array → base64 string
    public function decrypt(string $encoded): array;   // base64 string → array
    public function rotateKey(string $newKeyPath): void; // re-encripta todo
}
```

### 5.3 Clase Storage.php — Interfaz

```php
class Storage {
    public function __construct(Encryption $enc, string $dataDir);
    public function read(string $file): array;          // lee y desencripta
    public function write(string $file, array $data): void; // encripta y escribe
    public function exists(string $file): bool;
    public function delete(string $file): void;
    public function listFiles(string $dir): array;      // lista archivos en subdir
}
```

### 5.4 Flujo de Lectura/Escritura

```
WRITE:
  PHP array → json_encode() → openssl_encrypt(AES-256-GCM) → base64_encode() → .json.enc

READ:
  .json.enc → base64_decode() → openssl_decrypt(AES-256-GCM) → json_decode() → PHP array
```

---

## 6. Sistema de Licencias (License.php)

### 6.1 Resumen

Adapta la lógica de `WC_Gateway_Redsys_License` a PHP puro sin WordPress.
Llama a los mismos endpoints en `plugins.joseconti.com`.

### 6.2 Clase License.php — Interfaz

```php
class License {
    private Storage $storage;
    private string $apiUrl = 'https://plugins.joseconti.com/';
    private string $itemName = 'Klytos';
    private string $slug = 'klytos';

    public function __construct(Storage $storage);

    // Activar licencia contra el servidor
    public function activate(string $licenseKey, string $siteUrl): array;
    // Retorna: ['success' => bool, 'license' => 'valid'|'invalid', 'salt' => '...', 'error' => '...']

    // Verificar estado actual (lectura local)
    public function getStatus(): array;
    // Retorna: ['license_key', 'status', 'domain', 'activated_at', 'last_verified', 'plan']

    // Re-verificar contra servidor (periódico, cada 7 días)
    public function verify(): array;

    // ¿Licencia activa?
    public function isActive(): bool;
}
```

### 6.3 Endpoint de Activación

```
POST https://plugins.joseconti.com/?wc-api=lm-license-api

Body (form-urlencoded):
  action    = activate_license
  license   = KLYTOS-XXXX-XXXX-XXXX
  item_name = Klytos
  url       = https://dominio.tld
  site_url  = https://dominio.tld
  domain    = dominio.tld

Respuesta exitosa:
  { "activated": true, "license": "valid", "salt": "random_salt_string" }

Respuesta error:
  { "activated": false, "error": "License key not found" }
```

### 6.4 Implementación HTTP (sin WordPress)

```php
private function apiPost(string $endpoint, array $params): ?object {
    $url = $this->apiUrl . '?wc-api=' . $endpoint;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        // Reintentar sin verificar SSL (mismo comportamiento que WC_Gateway_Redsys_License)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }

    curl_close($ch);

    if ($httpCode !== 200 || empty($response)) {
        return null;
    }

    return json_decode($response);
}
```

### 6.5 Verificación Periódica

- Cada vez que se accede al admin, comprobar `last_verified`
- Si han pasado más de 7 días, hacer `verify()` en segundo plano
- Si falla la verificación (servidor no accesible), seguir funcionando con gracia
- Si el servidor responde que la licencia fue revocada, mostrar aviso pero NO bloquear inmediatamente (dar 14 días de gracia)

### 6.6 Datos almacenados en `config/license.json.enc`

```json
{
  "license_key": "KLYTOS-XXXX-XXXX-XXXX",
  "license_status": "valid",
  "license_salt": "salt_from_server",
  "domain": "dominio.tld",
  "site_url": "https://dominio.tld",
  "activated_at": "2026-03-26T12:00:00Z",
  "last_verified": "2026-03-26T12:00:00Z",
  "plan": "pro",
  "grace_period_until": null
}
```

---

## 7. Sistema de Actualizaciones (Updater.php)

### 7.1 Resumen

Mismo servidor y mismos endpoints que el plugin de Redsys, adaptados a PHP puro.
Las actualizaciones se descargan, verifican e instalan desde el panel admin.

### 7.2 Clase Updater.php — Interfaz

```php
class Updater {
    private Storage $storage;
    private License $license;
    private string $apiUrl = 'https://plugins.joseconti.com/';
    private string $itemName = 'Klytos';
    private string $currentVersion;

    public function __construct(Storage $storage, License $license);

    // Comprobar si hay nueva versión
    public function checkForUpdate(): ?array;
    // Retorna: null | ['new_version', 'package_url', 'is_major', 'changelog', 'requires_php']

    // Descargar e instalar actualización
    public function install(string $packageUrl): array;
    // Retorna: ['success' => bool, 'from_version', 'to_version', 'error' => '...']

    // Obtener historial de actualizaciones
    public function getLog(): array;

    // Obtener versión actual
    public function getCurrentVersion(): string;
}
```

### 7.3 Endpoint de Check de Actualizaciones

```
POST https://plugins.joseconti.com/?wc-api=upgrade-api

Body (form-urlencoded):
  action        = plugin_latest_version
  license       = KLYTOS-XXXX-XXXX-XXXX
  salt          = salt_from_activation
  item_name     = Klytos
  item_version  = 1.0.0
  slug          = klytos
  site_url      = https://dominio.tld
  domain        = dominio.tld

Respuesta (hay actualización):
  {
    "new_version": "1.1.0",
    "package": "https://plugins.joseconti.com/downloads/klytos-1.1.0.zip",
    "is_major": false,
    "changelog": "...",
    "requires_php": "8.0"
  }

Respuesta (sin actualización):
  { "new_version": "1.0.0" }   // misma versión = nada que hacer
```

### 7.4 Endpoint de Descarga de Paquete

La URL de descarga se extiende con parámetros de verificación, igual que en Redsys:

```
GET https://plugins.joseconti.com/downloads/klytos-1.1.0.zip
    ?action=get_last_version
    &license=KLYTOS-XXXX-XXXX-XXXX
    &salt=salt_from_activation
    &item_name=Klytos
    &slug=klytos
    &site_url=https://dominio.tld
    &domain=dominio.tld
```

### 7.5 Proceso de Actualización

```
1. CHECK:
   - POST a upgrade-api con versión actual
   - Si new_version > current_version → hay update
   - Mostrar en admin: "Nueva versión X.X.X disponible" + changelog

2. PRE-UPDATE BACKUP:
   - Crear backups/pre-{current_version}/
   - Copiar: core/, admin/, templates/, VERSION, index.php, .htaccess
   - NO copiar: config/, data/, public/, backups/
   - Registrar en data/update_log.json.enc

3. DOWNLOAD:
   - Descargar zip desde package URL extendida
   - Guardar en /tmp/ o backups/ temporalmente
   - Verificar integridad: hash SHA-256 (si el servidor lo proporciona)

4. EXTRACT & APPLY:
   - Descomprimir zip en directorio temporal
   - Copiar archivos nuevos sobre los existentes:
     ✅ core/*         (sobreescribir)
     ✅ admin/*        (sobreescribir)
     ✅ templates/*    (sobreescribir, pero NO borrar custom del usuario)
     ✅ index.php      (sobreescribir)
     ✅ .htaccess      (sobreescribir)
     ✅ VERSION        (sobreescribir)
     ❌ config/*       (NUNCA tocar)
     ❌ data/*         (NUNCA tocar)
     ❌ public/*       (NUNCA tocar)
     ❌ backups/*      (NUNCA tocar)

5. POST-UPDATE:
   - Ejecutar migraciones si existen (core/migrations/migrate_X_to_Y.php)
   - Actualizar VERSION
   - Limpiar temporales
   - Registrar éxito en data/update_log.json.enc:
     {
       "from": "1.0.0",
       "to": "1.1.0",
       "date": "2026-04-15T10:30:00Z",
       "status": "success",
       "backup_path": "backups/pre-1.0.0/"
     }
   - Mostrar confirmación en admin

6. ROLLBACK (si falla):
   - Restaurar desde backups/pre-{version}/
   - Registrar error en update_log
   - Mostrar instrucciones al usuario
```

### 7.6 Check Automático

- Al cargar el dashboard del admin, verificar si hay update (con cache de 12h)
- Cache almacenada en data/ como `update_cache.json.enc`
- Si hay update disponible, mostrar badge en sidebar del admin
- Actualizaciones mayores (is_major=true) muestran aviso destacado en rojo

---

## 8. Sistema de Internacionalización del Admin (I18n.php)

### 8.1 Clase I18n.php — Interfaz

```php
class I18n {
    private array $strings = [];
    private string $locale;
    private string $fallback = 'en';

    public function __construct(string $locale, string $langDir);

    // Obtener traducción
    public function get(string $key, array $replacements = []): string;

    // Shorthand global: __('key') o __('key', ['name' => 'José'])
    // Se registra como función global en App.php

    // Obtener idioma actual
    public function getLocale(): string;

    // Listar idiomas disponibles
    public function getAvailableLocales(): array;
}
```

### 8.2 Formato de Archivos de Traducción

```json
// core/lang/es.json
{
  "_meta": {
    "locale": "es",
    "name": "Español",
    "flag": "🇪🇸",
    "author": "José Conti",
    "version": "1.0.0"
  },
  "common": {
    "save": "Guardar",
    "cancel": "Cancelar",
    "delete": "Eliminar",
    "edit": "Editar",
    "create": "Crear",
    "back": "Volver",
    "search": "Buscar",
    "loading": "Cargando...",
    "success": "Operación exitosa",
    "error": "Ha ocurrido un error",
    "confirm_delete": "¿Estás seguro de que quieres eliminar esto?",
    "yes": "Sí",
    "no": "No"
  },
  "auth": {
    "login": "Iniciar sesión",
    "logout": "Cerrar sesión",
    "username": "Usuario",
    "password": "Contraseña",
    "remember_me": "Recordarme",
    "login_failed": "Usuario o contraseña incorrectos",
    "account_locked": "Cuenta bloqueada. Inténtalo en {minutes} minutos."
  },
  "dashboard": {
    "title": "Panel de control",
    "welcome": "Bienvenido a Klytos",
    "total_pages": "Total de páginas",
    "last_build": "Último build",
    "mcp_status": "Estado MCP",
    "license_status": "Estado de licencia",
    "update_available": "Actualización disponible: v{version}"
  },
  "pages": {
    "title": "Páginas",
    "create_page": "Crear página",
    "edit_page": "Editar página",
    "slug": "Slug (URL)",
    "page_title": "Título",
    "content": "Contenido",
    "template": "Plantilla",
    "status": "Estado",
    "published": "Publicada",
    "draft": "Borrador",
    "no_pages": "No hay páginas todavía. ¡Conecta una IA para empezar!"
  },
  "theme": {
    "title": "Tema",
    "colors": "Colores",
    "fonts": "Tipografías",
    "layout": "Disposición",
    "primary_color": "Color primario",
    "secondary_color": "Color secundario",
    "accent_color": "Color de acento",
    "background_color": "Color de fondo",
    "text_color": "Color de texto",
    "preview": "Vista previa"
  },
  "mcp": {
    "title": "Conexión MCP",
    "endpoint": "Endpoint MCP",
    "tokens": "Tokens de acceso",
    "create_token": "Crear nuevo token",
    "revoke_token": "Revocar token",
    "token_created": "Token creado. Cópialo ahora, no se mostrará de nuevo:",
    "no_tokens": "No hay tokens activos",
    "last_activity": "Última actividad",
    "requests_today": "Peticiones hoy",
    "connection_guide": "Guía de conexión"
  },
  "license": {
    "title": "Licencia",
    "key": "Clave de licencia",
    "status": "Estado",
    "active": "Activa",
    "inactive": "Inactiva",
    "expired": "Expirada",
    "activate": "Activar licencia",
    "domain": "Dominio registrado",
    "activated_on": "Activada el",
    "last_check": "Última verificación"
  },
  "updates": {
    "title": "Actualizaciones",
    "current_version": "Versión actual",
    "latest_version": "Última versión",
    "up_to_date": "Klytos está actualizado",
    "available": "Nueva versión disponible: v{version}",
    "update_now": "Actualizar ahora",
    "updating": "Actualizando...",
    "backup_created": "Backup creado en {path}",
    "update_success": "Actualización completada: v{from} → v{to}",
    "update_failed": "Error en la actualización: {error}",
    "major_warning": "⚠️ Esta es una actualización mayor. Se recomienda hacer un backup completo.",
    "changelog": "Notas de la versión",
    "history": "Historial de actualizaciones"
  },
  "settings": {
    "title": "Configuración",
    "site_name": "Nombre del sitio",
    "site_description": "Descripción",
    "admin_language": "Idioma del panel",
    "timezone": "Zona horaria",
    "admin_email": "Email del administrador"
  },
  "assets": {
    "title": "Archivos",
    "upload": "Subir archivo",
    "delete": "Eliminar archivo",
    "no_assets": "No hay archivos subidos",
    "max_size": "Tamaño máximo: {size}MB"
  },
  "install": {
    "title": "Instalación de Klytos",
    "step_license": "Licencia",
    "step_config": "Configuración",
    "step_complete": "Completado",
    "enter_license": "Introduce tu clave de licencia",
    "license_valid": "Licencia válida ✅",
    "license_invalid": "Licencia no válida. Comprueba la clave e inténtalo de nuevo.",
    "site_name": "Nombre de tu sitio",
    "choose_language": "Idioma del panel de administración",
    "choose_password": "Elige una contraseña segura (mínimo 12 caracteres)",
    "choose_colors": "Elige una paleta de colores inicial",
    "installing": "Instalando Klytos...",
    "complete": "¡Klytos instalado correctamente!",
    "copy_token": "⚠️ Copia este token MCP. No se mostrará de nuevo."
  }
}
```

### 8.3 Función Helper Global

```php
// Registrada en App.php como función global
function __(string $key, array $replacements = []): string {
    global $klytos_i18n;
    return $klytos_i18n->get($key, $replacements);
}

// Uso en templates del admin:
<h1><?= __('dashboard.title') ?></h1>
<p><?= __('updates.available', ['version' => '1.1.0']) ?></p>
<p><?= __('auth.account_locked', ['minutes' => 15]) ?></p>
```

### 8.4 Fallback Chain

```
1. Buscar clave en el idioma activo (ej: es.json)
2. Si no existe → buscar en en.json (fallback)
3. Si no existe → devolver la propia clave como texto
```

---

## 9. Protocolo MCP — Implementación

### 9.1 Endpoint

```
POST  https://dominio.tld/klytos/mcp    → JSON-RPC 2.0 requests
GET   https://dominio.tld/klytos/mcp    → Server info (futuro: SSE)
```

Transporte: **Streamable HTTP** (stateless JSON-RPC 2.0 sobre POST)

### 9.2 Autenticación

```
Authorization: Bearer <token>
```

- Token: 64 chars hex, generado con `bin2hex(random_bytes(32))`
- Almacenado hasheado (SHA-256) en `data/tokens.json.enc`
- Crear/revocar desde admin → sección MCP
- Rate limiting: 60 req/min por token (contador en archivo temporal)

### 9.3 JSON-RPC 2.0 — Métodos MCP Soportados

| Método | Descripción |
|--------|-------------|
| `initialize` | Handshake inicial, devuelve server info y capabilities |
| `tools/list` | Lista todos los tools disponibles con schemas |
| `tools/call` | Ejecuta un tool específico |
| `ping` | Health check |

### 9.4 Server Capabilities

```json
{
  "name": "klytos",
  "version": "1.0.0",
  "capabilities": {
    "tools": {
      "listChanged": false
    }
  }
}
```

### 9.5 Tools MCP — Catálogo Completo

#### 9.5.1 Páginas (PageTools)

| Tool | Descripción | readOnly | destructive | idempotent |
|------|-------------|----------|-------------|------------|
| `klytos_create_page` | Crea una nueva página HTML | ❌ | ✅ | ❌ |
| `klytos_update_page` | Actualiza contenido/meta de una página | ❌ | ✅ | ✅ |
| `klytos_delete_page` | Elimina una página | ❌ | ✅ | ✅ |
| `klytos_get_page` | Obtiene una página completa | ✅ | ❌ | ✅ |
| `klytos_list_pages` | Lista todas las páginas | ✅ | ❌ | ✅ |

**`klytos_create_page` — Input Schema:**

```json
{
  "slug": {
    "type": "string",
    "description": "URL slug. Lowercase, alphanumeric, hyphens. E.g.: 'about', 'en/about', 'contact'"
  },
  "title": {
    "type": "string",
    "description": "Page title for <title> and <h1>"
  },
  "content_html": {
    "type": "string",
    "description": "Full HTML body content. Any valid HTML allowed."
  },
  "meta_description": {
    "type": "string",
    "description": "SEO meta description, max 160 chars"
  },
  "template": {
    "type": "string",
    "enum": ["default", "landing", "blog-post", "blank"],
    "default": "default"
  },
  "status": {
    "type": "string",
    "enum": ["draft", "published"],
    "default": "published"
  },
  "custom_css": { "type": "string", "default": "" },
  "custom_js": { "type": "string", "default": "" },
  "og_image": { "type": "string", "default": "" },
  "lang": {
    "type": "string",
    "description": "Language code (es, en, ca...) for hreflang. Empty = default language.",
    "default": ""
  },
  "hreflang_refs": {
    "type": "object",
    "description": "Map of lang→slug for alternate versions. E.g.: {\"en\": \"en/about\", \"es\": \"about\"}",
    "default": {}
  },
  "order": { "type": "integer", "default": 0 }
}
```

**`klytos_update_page` — Input Schema:**

```json
{
  "slug": { "type": "string", "description": "Slug of page to update (required)" },
  "title": { "type": "string" },
  "content_html": { "type": "string" },
  "meta_description": { "type": "string" },
  "template": { "type": "string", "enum": ["default", "landing", "blog-post", "blank"] },
  "status": { "type": "string", "enum": ["draft", "published"] },
  "custom_css": { "type": "string" },
  "custom_js": { "type": "string" },
  "og_image": { "type": "string" },
  "lang": { "type": "string" },
  "hreflang_refs": { "type": "object" },
  "order": { "type": "integer" }
}
```

**`klytos_delete_page`:** `{ "slug": "string" }`

**`klytos_get_page`:** `{ "slug": "string" }`

**`klytos_list_pages`:** `{ "status": "all|published|draft", "lang": "string|empty", "limit": 50, "offset": 0 }`

#### 9.5.2 Tema (ThemeTools)

| Tool | Descripción | readOnly | destructive | idempotent |
|------|-------------|----------|-------------|------------|
| `klytos_set_theme` | Define configuración completa del tema | ❌ | ✅ | ✅ |
| `klytos_get_theme` | Obtiene config actual del tema | ✅ | ❌ | ✅ |
| `klytos_set_colors` | Actualiza solo la paleta de colores | ❌ | ✅ | ✅ |
| `klytos_set_fonts` | Actualiza tipografías | ❌ | ✅ | ✅ |
| `klytos_set_layout` | Configura layout | ❌ | ✅ | ✅ |

**`klytos_set_theme` — Input Schema:**

```json
{
  "colors": {
    "type": "object",
    "properties": {
      "primary": { "type": "string", "description": "Primary brand color (#hex)" },
      "secondary": { "type": "string", "description": "Secondary color (#hex)" },
      "accent": { "type": "string", "description": "Accent/CTA color (#hex)" },
      "background": { "type": "string", "description": "Page background (#hex)" },
      "surface": { "type": "string", "description": "Card/surface background (#hex)" },
      "text": { "type": "string", "description": "Main text color (#hex)" },
      "text_muted": { "type": "string", "description": "Secondary text color (#hex)" },
      "border": { "type": "string", "description": "Border color (#hex)" },
      "success": { "type": "string" },
      "warning": { "type": "string" },
      "error": { "type": "string" }
    }
  },
  "fonts": {
    "type": "object",
    "properties": {
      "heading": { "type": "string", "description": "Font family for headings" },
      "body": { "type": "string", "description": "Font family for body text" },
      "code": { "type": "string", "description": "Font family for code blocks" },
      "heading_weight": { "type": "string", "default": "700" },
      "body_weight": { "type": "string", "default": "400" },
      "base_size": { "type": "string", "default": "16px" },
      "scale_ratio": { "type": "string", "default": "1.25", "description": "Type scale ratio for heading sizes" },
      "google_fonts_url": { "type": "string", "description": "Full Google Fonts CSS2 URL" }
    }
  },
  "layout": {
    "type": "object",
    "properties": {
      "max_width": { "type": "string", "default": "1200px" },
      "header_style": { "type": "string", "enum": ["fixed", "static", "sticky"], "default": "sticky" },
      "footer_enabled": { "type": "boolean", "default": true },
      "sidebar_enabled": { "type": "boolean", "default": false },
      "sidebar_position": { "type": "string", "enum": ["left", "right"], "default": "left" },
      "border_radius": { "type": "string", "default": "8px" },
      "spacing_unit": { "type": "string", "default": "1rem" }
    }
  },
  "custom_css": { "type": "string", "default": "" }
}
```

#### 9.5.3 Menú (MenuTools)

| Tool | Descripción | readOnly | destructive | idempotent |
|------|-------------|----------|-------------|------------|
| `klytos_set_menu` | Define estructura completa del menú | ❌ | ✅ | ✅ |
| `klytos_get_menu` | Obtiene el menú actual | ✅ | ❌ | ✅ |
| `klytos_add_menu_item` | Añade un ítem al menú | ❌ | ✅ | ❌ |
| `klytos_remove_menu_item` | Elimina un ítem del menú | ❌ | ✅ | ✅ |

**`klytos_set_menu` — Input Schema:**

```json
{
  "items": {
    "type": "array",
    "items": {
      "type": "object",
      "properties": {
        "id": { "type": "string", "description": "Unique ID (auto-generated if empty)" },
        "label": { "type": "string", "description": "Display text" },
        "url": { "type": "string", "description": "Link URL (relative or absolute)" },
        "target": { "type": "string", "enum": ["_self", "_blank"], "default": "_self" },
        "icon": { "type": "string", "default": "" },
        "children": { "type": "array", "description": "Nested sub-items (same structure)" },
        "order": { "type": "integer", "default": 0 }
      }
    }
  }
}
```

#### 9.5.4 Configuración del Sitio (SiteTools)

| Tool | Descripción | readOnly | destructive | idempotent |
|------|-------------|----------|-------------|------------|
| `klytos_set_site_config` | Actualiza configuración global | ❌ | ✅ | ✅ |
| `klytos_get_site_config` | Obtiene configuración actual | ✅ | ❌ | ✅ |

**`klytos_set_site_config` — Input Schema:**

```json
{
  "site_name": { "type": "string" },
  "tagline": { "type": "string" },
  "default_language": { "type": "string", "default": "es" },
  "description": { "type": "string", "description": "SEO description" },
  "favicon_url": { "type": "string" },
  "logo_url": { "type": "string" },
  "social": {
    "type": "object",
    "properties": {
      "twitter": { "type": "string" },
      "github": { "type": "string" },
      "linkedin": { "type": "string" },
      "instagram": { "type": "string" },
      "youtube": { "type": "string" },
      "mastodon": { "type": "string" }
    }
  },
  "analytics": {
    "type": "object",
    "properties": {
      "google_analytics_id": { "type": "string" },
      "custom_head_scripts": { "type": "string" },
      "custom_body_scripts": { "type": "string" }
    }
  },
  "seo": {
    "type": "object",
    "properties": {
      "default_og_image": { "type": "string" },
      "robots_txt_extra": { "type": "string" }
    }
  }
}
```

#### 9.5.5 Assets (AssetTools)

| Tool | Descripción | readOnly | destructive | idempotent |
|------|-------------|----------|-------------|------------|
| `klytos_upload_asset` | Sube un archivo (base64) | ❌ | ✅ | ✅ |
| `klytos_list_assets` | Lista archivos subidos | ✅ | ❌ | ✅ |
| `klytos_delete_asset` | Elimina un archivo | ❌ | ✅ | ✅ |

**`klytos_upload_asset` — Input Schema:**

```json
{
  "filename": { "type": "string", "description": "Filename with extension (e.g. 'logo.png')" },
  "data_base64": { "type": "string", "description": "File content encoded in base64" },
  "directory": { "type": "string", "default": "images", "description": "Subdirectory in assets/" }
}
```

#### 9.5.6 Templates (TemplateTools)

| Tool | Descripción | readOnly | destructive | idempotent |
|------|-------------|----------|-------------|------------|
| `klytos_set_template` | Crea/actualiza una plantilla HTML | ❌ | ✅ | ✅ |
| `klytos_list_templates` | Lista plantillas disponibles | ✅ | ❌ | ✅ |
| `klytos_get_template` | Obtiene HTML de una plantilla | ✅ | ❌ | ✅ |

**`klytos_set_template` — Input Schema:**

```json
{
  "name": { "type": "string", "description": "Template identifier (e.g. 'portfolio', 'blog')" },
  "html": { "type": "string", "description": "Full HTML template with {{variables}}" },
  "description": { "type": "string", "description": "What this template is for" }
}
```

**Variables disponibles en templates:**

```
{{site_name}}           {{tagline}}             {{default_language}}
{{page_title}}          {{page_content}}        {{meta_description}}
{{page_lang}}           {{hreflang_tags}}       {{page_slug}}
{{menu_html}}           {{current_year}}        {{og_image}}
{{custom_css}}          {{custom_js}}           {{google_fonts_url}}
{{header_html}}         {{footer_html}}         {{sidebar_html}}
{{favicon_url}}         {{logo_url}}            {{head_scripts}}
{{body_scripts}}        {{css_variables}}       {{sitemap_url}}
```

#### 9.5.7 Build (BuildTools)

| Tool | Descripción | readOnly | destructive | idempotent |
|------|-------------|----------|-------------|------------|
| `klytos_build_site` | Regenera todo el sitio estático | ❌ | ✅ | ✅ |
| `klytos_build_page` | Regenera una sola página | ❌ | ✅ | ✅ |
| `klytos_preview_page` | Devuelve HTML renderizado sin guardar | ✅ | ❌ | ✅ |
| `klytos_get_build_status` | Estado del último build | ✅ | ❌ | ✅ |

---

## 10. Motor de Build (Generación Estática)

### 10.1 Proceso de Build Completo (`klytos_build_site`)

```
1. Leer theme.json.enc → generar CSS variables
2. Generar public/css/style.css:
   - CSS reset mínimo
   - Variables CSS desde tema
   - Estilos base responsivos
   - Google Fonts @import
   - Custom CSS del tema
3. Leer menus.json.enc → generar HTML del menú (<nav>)
4. Leer site.json.enc → obtener metadata global
5. Para cada página en data/pages/:
   a. Leer page.json.enc
   b. Si status != "published" → saltar
   c. Seleccionar template (default si no especificado)
   d. Reemplazar {{variables}} en template
   e. Inyectar hreflang tags si lang/hreflang_refs existen
   f. Escribir public/{slug}.html
   g. Si slug contiene "/" (ej: "en/about") → crear subdirectorio
6. Generar public/robots.txt
7. Generar public/sitemap.xml (con hreflang si aplica)
8. Actualizar timestamp de build en data/site.json.enc
9. Devolver resumen: { pages_built, errors, duration_ms }
```

### 10.2 CSS Variables Generado

```css
:root {
  --klytos-primary: #...;
  --klytos-secondary: #...;
  --klytos-accent: #...;
  --klytos-bg: #...;
  --klytos-surface: #...;
  --klytos-text: #...;
  --klytos-text-muted: #...;
  --klytos-border: #...;
  --klytos-success: #...;
  --klytos-warning: #...;
  --klytos-error: #...;
  --klytos-font-heading: '...', sans-serif;
  --klytos-font-body: '...', sans-serif;
  --klytos-font-code: '...', monospace;
  --klytos-max-width: 1200px;
  --klytos-radius: 8px;
  --klytos-spacing: 1rem;
}
```

### 10.3 Sitemap.xml con hreflang

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <url>
    <loc>https://dominio.tld/about.html</loc>
    <xhtml:link rel="alternate" hreflang="es" href="https://dominio.tld/about.html"/>
    <xhtml:link rel="alternate" hreflang="en" href="https://dominio.tld/en/about.html"/>
    <xhtml:link rel="alternate" hreflang="x-default" href="https://dominio.tld/about.html"/>
  </url>
</urlset>
```

---

## 11. Panel de Administración

### 11.1 Secciones

| Sección | Ruta | Descripción |
|---------|------|-------------|
| Dashboard | `/admin/` | Resumen: nº páginas, último build, estado MCP, licencia, versión |
| Páginas | `/admin/pages.php` | Tabla con slug, título, estado, template. Acciones: editar, borrar |
| Tema | `/admin/theme.php` | Color pickers, selectores de fuente, preview en vivo |
| MCP | `/admin/mcp.php` | Endpoint URL, tokens activos, crear/revocar, log de actividad |
| Assets | `/admin/assets.php` | Upload/gestión de imágenes y archivos |
| Configuración | `/admin/settings.php` | Nombre, idioma admin, SEO, analytics, social |
| Licencia | `/admin/license.php` | Estado, clave, dominio, activar/cambiar |
| Actualizaciones | `/admin/updates.php` | Versión actual, check update, changelog, botón actualizar, historial |

### 11.2 Autenticación Admin

- Login con usuario/contraseña (bcrypt, cost=12)
- Sesiones PHP con `session_regenerate_id(true)` tras login
- CSRF token por formulario: `bin2hex(random_bytes(32))`
- Cookie config: `httpOnly=true`, `secure=true`, `samesite=Strict`
- Auto-logout tras 30 min de inactividad
- Bloqueo tras 5 intentos fallidos → 15 min lockout (almacenado en archivo temporal)

### 11.3 Headers de Seguridad (Admin)

```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; style-src \'self\' \'unsafe-inline\' fonts.googleapis.com; font-src fonts.gstatic.com');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
```

---

## 12. Routing

### 12.1 Rutas

| Patrón | Handler | Descripción |
|--------|---------|-------------|
| `POST /mcp` | `core/MCP/Server.php` | Endpoint MCP (JSON-RPC) |
| `GET /mcp` | `core/MCP/Server.php` | Server info |
| `/admin/*` | `admin/*.php` | Panel de administración |
| `/install` | `install.php` | Instalador (solo pre-config) |
| `/*` | `public/*.html` | Sitio estático generado |

### 12.2 `.htaccess` (Apache / LiteSpeed)

```apache
RewriteEngine On
RewriteBase /klytos/

# Bloquear acceso a directorios protegidos
RewriteRule ^config/ - [F,L]
RewriteRule ^core/ - [F,L]
RewriteRule ^data/ - [F,L]
RewriteRule ^backups/ - [F,L]
RewriteRule ^templates/ - [F,L]

# Bloquear archivos sensibles
RewriteRule \.enc$ - [F,L]
RewriteRule \.encryption_key$ - [F,L]
RewriteRule ^VERSION$ - [F,L]

# MCP Endpoint
RewriteRule ^mcp$ index.php?route=mcp [L,QSA]

# Admin
RewriteRule ^admin/?$ admin/index.php [L]

# Install
RewriteRule ^install/?$ install.php [L]

# Archivos estáticos de public/ (CSS, JS, imágenes)
RewriteCond %{REQUEST_URI} \.(css|js|png|jpg|jpeg|gif|svg|ico|webp|woff|woff2)$
RewriteRule ^(.+)$ public/$1 [L]

# Páginas HTML
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{DOCUMENT_ROOT}/klytos/public/$1.html -f
RewriteRule ^(.+)$ public/$1.html [L]

# Default → homepage
RewriteRule ^$ public/index.html [L]
```

### 12.3 Nginx (referencia)

```nginx
location /klytos/ {
    # Proteger directorios
    location ~ ^/klytos/(config|core|data|backups|templates)/ { deny all; }
    location ~ \.(enc|encryption_key)$ { deny all; }

    # MCP
    location = /klytos/mcp {
        try_files $uri /klytos/index.php?route=mcp;
    }

    # Admin
    location /klytos/admin/ {
        try_files $uri $uri/ =404;
    }

    # Sitio estático
    location /klytos/ {
        try_files $uri $uri/ /klytos/public/$uri /klytos/public/$uri.html =404;
    }
}
```

---

## 13. Protección del Código

### 13.1 Ofuscación

- Todo el código en `core/` se ofusca antes de la distribución
- Herramienta: PHP-Obfuscator o yakpro-po
- Variables renombradas, comentarios eliminados, strings codificados
- Los archivos en `admin/` y `templates/` se ofuscan parcialmente (mantener HTML legible)

### 13.2 Checks Anti-Manipulación

```php
// En App.php — verificación al arrancar
private function integrityCheck(): void {
    // 1. Verificar que license.json.enc existe
    if (!$this->storage->exists('config/license.json.enc')) {
        die('Klytos: License file missing. Please reinstall.');
    }

    // 2. Verificar que la licencia es válida (lectura local)
    $license = new License($this->storage);
    if (!$license->isActive()) {
        // Redirigir a admin/license.php
        header('Location: admin/license.php');
        exit;
    }
}
```

### 13.3 Lo que NO se ofusca

- `install.php` (necesita ser legible para debug de instalación)
- `core/lang/*.json` (traducciones editables por el usuario)
- `templates/*.html` (plantillas personalizables)
- `.htaccess` / `web.config`

---

## 14. Seguridad — Resumen

| Capa | Medida |
|------|--------|
| **Datos en reposo** | AES-256-GCM para todos los JSON |
| **Passwords** | bcrypt cost=12 |
| **MCP tokens** | 64 chars hex, almacenados como SHA-256 hash |
| **CSRF** | Token por sesión en cada formulario admin |
| **Rate limiting** | 60 req/min MCP, 5 login intentos/15min |
| **Directorios** | .htaccess deny en config/, data/, core/, backups/ |
| **Sesiones** | httpOnly, secure, samesite=Strict, regeneración de ID |
| **Headers** | CSP, X-Frame-Options, X-Content-Type-Options, etc. |
| **Input** | Sanitización HTML (allowlist), validación de tipos |
| **Licencia** | Verificación contra servidor externo + gracia de 14 días |
| **Código** | Ofuscado en distribución |

---

## 15. Multilingüe del Front (Delegado al Agente IA)

El front multilingüe no requiere infraestructura especial en el core. El agente IA lo gestiona creando:

### 15.1 Estructura de subdirectorios

```
public/
├── index.html              ← Versión en idioma por defecto (es)
├── about.html
├── en/                     ← Versión inglesa
│   ├── index.html
│   └── about.html
├── ca/                     ← Versión catalana
│   ├── index.html
│   └── about.html
```

### 15.2 Hreflang Tags (inyectados por el build engine)

Si una página tiene `lang` y `hreflang_refs`, el build engine inyecta:

```html
<html lang="es">
<head>
  <link rel="alternate" hreflang="es" href="https://dominio.tld/about.html" />
  <link rel="alternate" hreflang="en" href="https://dominio.tld/en/about.html" />
  <link rel="alternate" hreflang="ca" href="https://dominio.tld/ca/about.html" />
  <link rel="alternate" hreflang="x-default" href="https://dominio.tld/about.html" />
</head>
```

### 15.3 Tool de Conveniencia (futuro)

```
klytos_translate_page:
  Input: { "source_slug": "about", "target_lang": "en" }
  Behavior: El agente lee la página original, la traduce, y llama a
            klytos_create_page con slug "en/about" y los hreflang_refs correctos.
```

---

## 16. Ejemplo de Flujo Completo con IA

```
USUARIO → Claude: "Crea un sitio web para mi cafetería 'El Rincón del Café'
                   con versión en español y en inglés"

CLAUDE → MCP (secuencia de llamadas):

  1. klytos_get_site_config()
     → Lee configuración actual para entender el contexto

  2. klytos_set_site_config({
       site_name: "El Rincón del Café",
       default_language: "es",
       tagline: "El mejor café de Barcelona",
       description: "Cafetería artesanal en el corazón de Barcelona...",
       social: { instagram: "@rincondelcafe" }
     })

  3. klytos_set_theme({
       colors: { primary: "#6B4226", secondary: "#F5E6D3", accent: "#D4A574", ... },
       fonts: { heading: "Playfair Display", body: "Lora", google_fonts_url: "..." },
       layout: { max_width: "1100px", header_style: "sticky" }
     })

  4. klytos_set_template({ name: "cafeteria", html: "<!DOCTYPE html>..." })

  5. klytos_create_page({
       slug: "index",
       title: "El Rincón del Café",
       content_html: "<section class='hero'>...",
       lang: "es",
       hreflang_refs: { "en": "en/index", "es": "index" }
     })

  6. klytos_create_page({
       slug: "en/index",
       title: "El Rincón del Café — The Coffee Corner",
       content_html: "<section class='hero'>...",
       lang: "en",
       hreflang_refs: { "en": "en/index", "es": "index" }
     })

  7. klytos_create_page({ slug: "carta", title: "Nuestra Carta", ... })
  8. klytos_create_page({ slug: "en/menu", title: "Our Menu", ... })
  9. klytos_create_page({ slug: "contacto", title: "Contacto", ... })
  10. klytos_create_page({ slug: "en/contact", title: "Contact", ... })

  11. klytos_set_menu({ items: [
        { label: "Inicio", url: "/", children: [] },
        { label: "Carta", url: "/carta" },
        { label: "Contacto", url: "/contacto" },
        { label: "EN", url: "/en/", target: "_self" }
      ]})

  12. klytos_build_site()

RESULTADO → Sitio estático bilingüe completo en public/
            Con hreflang, sitemap.xml multilingüe, robots.txt
            Listo para servir.
```

---

## 17. Conexión desde Clientes IA

### 17.1 Claude Desktop / Claude Code

```json
// ~/.claude/claude_desktop_config.json
{
  "mcpServers": {
    "klytos": {
      "url": "https://tu-dominio.tld/klytos/mcp",
      "headers": {
        "Authorization": "Bearer tu-token-aquí"
      }
    }
  }
}
```

### 17.2 Claude.ai (MCP Remoto)

El endpoint `https://tu-dominio.tld/klytos/mcp` es directamente compatible con la configuración de MCP remoto en Claude.ai (Settings → MCP Servers).

### 17.3 Otros Clientes MCP

Cualquier cliente compatible con MCP Streamable HTTP puede conectarse:
- Cursor
- Windsurf
- Continue.dev
- Cualquier cliente que implemente la spec MCP

---

## 18. Futuras Extensiones (v2+)

- **Blog engine**: Posts con fecha, categorías, tags, paginación, feed RSS
- **Formularios**: Generación de formularios de contacto con almacenamiento flat-file
- **Media AI**: Tool `klytos_generate_image` integrando APIs de generación de imágenes
- **Backup/Restore**: Export/import completo del sitio como .zip
- **Plugin system**: Hooks para extensiones de terceros
- **Git integration**: Versionado de cambios del sitio
- **MCP Resources**: Exponer páginas y tema como MCP Resources (read-only)
- **Webhooks**: Notificar builds completados a servicios externos
- **CDN purge**: Invalidar cache tras build (Cloudflare, etc.)
- **Multi-site**: Una instalación de Klytos gestionando múltiples dominios
- **klytos_translate_page**: Tool de conveniencia para traducción asistida por IA
- **A/B testing**: Variantes de página para testing

---

## 19. Checklist de Implementación

### Fase 1 — Core (MVP)

- [ ] `core/Encryption.php` — AES-256-GCM encrypt/decrypt
- [ ] `core/Storage.php` — CRUD JSON encriptado
- [ ] `core/I18n.php` — Sistema i18n con JSON + fallback
- [ ] `core/lang/en.json` — Traducciones base inglés
- [ ] `core/lang/es.json` — Traducciones base español
- [ ] `core/License.php` — Activación y verificación contra plugins.joseconti.com
- [ ] `core/Auth.php` — Login admin + Bearer MCP
- [ ] `core/Router.php` — Enrutador de peticiones
- [ ] `core/App.php` — Bootstrap
- [ ] `core/MCP/Server.php` — Streamable HTTP MCP server
- [ ] `core/MCP/JsonRpc.php` — Parser JSON-RPC 2.0
- [ ] `core/MCP/ToolRegistry.php` — Registro de tools
- [ ] `core/MCP/TokenAuth.php` — Validación de tokens
- [ ] `core/MCP/Tools/PageTools.php` — CRUD de páginas
- [ ] `core/MCP/Tools/ThemeTools.php` — Gestión de tema
- [ ] `core/MCP/Tools/SiteTools.php` — Config del sitio
- [ ] `core/MCP/Tools/BuildTools.php` — Generador estático
- [ ] `install.php` — Instalador completo (licencia + config)
- [ ] `.htaccess` — Routing + protección
- [ ] `templates/default.html` — Plantilla base
- [ ] `admin/login.php` — Login
- [ ] `admin/index.php` — Dashboard mínimo
- [ ] `admin/mcp.php` — Gestión de tokens MCP
- [ ] `admin/license.php` — Estado de licencia
- [ ] `VERSION` — Archivo de versión

### Fase 2 — Admin Completo + Updates

- [ ] `core/Updater.php` — Sistema de actualizaciones OTA
- [ ] `admin/updates.php` — UI de actualizaciones
- [ ] `admin/settings.php` — Configuración general con selector de idioma
- [ ] `admin/pages.php` — Tabla de páginas con acciones
- [ ] `admin/theme.php` — Editor visual de tema (color pickers, font selector)
- [ ] `admin/assets.php` — Gestor de archivos con upload
- [ ] `core/MCP/Tools/MenuTools.php` — Gestión de menú
- [ ] `core/MCP/Tools/AssetTools.php` — Upload de assets
- [ ] `core/MCP/Tools/TemplateTools.php` — Gestión de plantillas
- [ ] `core/lang/ca.json` — Traducciones català
- [ ] `core/lang/fr.json` — Traducciones français
- [ ] `templates/landing.html` — Plantilla landing
- [ ] `templates/blog-post.html` — Plantilla blog
- [ ] `templates/blank.html` — Plantilla en blanco
- [ ] Generación de sitemap.xml con hreflang
- [ ] Log de actividad MCP

### Fase 3 — Polish + Distribución

- [ ] Ofuscación del código para distribución
- [ ] web.config para IIS
- [ ] Configuración Nginx de referencia
- [ ] Documentación de usuario
- [ ] Pruebas en hostings compartidos (cPanel, Plesk, etc.)
- [ ] Pruebas con Claude Desktop, Claude.ai, Cursor
- [ ] Landing page en klytos.io
- [ ] Producto configurado en plugins.joseconti.com (licencias + updates)
- [ ] core/lang/de.json, pt.json, it.json
