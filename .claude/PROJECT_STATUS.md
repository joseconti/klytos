# Klytos CMS — Estado del Proyecto

> Última actualización: 2026-03-26
> Repositorio: /Users/joseconti/Documents/GitHub/klytos

## Qué es Klytos

CMS AI-First controlado por MCP (Model Context Protocol). El core es gratuito bajo licencia **Elastic License 2.0 (ELv2)**. El modelo de negocio se basa en **plugins premium** (marketplace), no en licencias del CMS.

## Estructura del Proyecto

```
klytos/
├── .htaccess                          ← Root: sirve web estática pública
├── LICENSE                            ← Elastic License 2.0
├── installer/                  ← Directorio admin (nombre configurable)
│   ├── .htaccess                      ← Enruta /mcp, /oauth/*, protege /data, /config, /core
│   ├── index.php                      ← Front controller (router)
│   ├── install.php                    ← Instalador web
│   ├── cli.php                        ← CLI tool
│   ├── t.php                          ← Pixel tracker analytics
│   ├── admin/                         ← Panel de administración
│   │   ├── bootstrap.php              ← Init común para admin pages
│   │   ├── login.php, logout.php
│   │   ├── index.php                  ← Dashboard
│   │   ├── pages.php                  ← Gestión de páginas
│   │   ├── theme.php                  ← Tema visual (ahora bajo "Diseño")
│   │   ├── templates.php              ← Plantillas de página (nuevo, bajo "Diseño")
│   │   ├── assets.php                 ← Gestión de archivos
│   │   ├── ai-images.php              ← Generación IA (Gemini)
│   │   ├── tasks.php                  ← Tareas/anotaciones
│   │   ├── analytics.php              ← Analytics privacy-first
│   │   ├── webhooks.php               ← Gestión de webhooks
│   │   ├── users.php                  ← Multi-usuario
│   │   ├── mcp.php                    ← Conexión MCP (App Passwords + OAuth)
│   │   ├── settings.php               ← Configuración del sitio
│   │   ├── plugins.php                ← Gestión de plugins
│   │   ├── updates.php                ← Actualizaciones
│   │   ├── license.php                ← Licencias de plugins premium
│   │   ├── templates/                 ← header.php, sidebar.php, footer.php
│   │   └── api/                       ← autosave.php, inline-edit.php, tasks.php
│   ├── core/
│   │   ├── app.php                    ← Singleton principal, boot(), managers
│   │   ├── auth.php                   ← Autenticación, CSRF, sessions, App Passwords
│   │   ├── encryption.php             ← AES-256-GCM
│   │   ├── storage-interface.php      ← Interface StorageInterface
│   │   ├── file-storage.php           ← FileStorage (JSON encriptado en disco)
│   │   ├── database-storage.php       ← DatabaseStorage (MySQL/MariaDB)
│   │   ├── page-manager.php           ← CRUD páginas con URLs jerárquicas
│   │   ├── build-engine.php           ← SSG: genera HTML estático + sitemap + llms.txt
│   │   ├── theme-manager.php          ← Colores, fonts, layout
│   │   ├── menu-manager.php           ← Menús de navegación
│   │   ├── asset-manager.php          ← Upload/gestión de archivos
│   │   ├── site-config.php            ← Configuración del sitio
│   │   ├── ai-image-generator.php     ← Integración Gemini
│   │   ├── hooks.php                  ← Motor de hooks (acciones + filtros)
│   │   ├── helpers.php                ← Funciones helper globales
│   │   ├── plugin-loader.php          ← Cargador de plugins
│   │   ├── user-manager.php           ← Multi-usuario (roles: owner, admin, editor, viewer)
│   │   ├── audit-log.php              ← Registro de auditoría
│   │   ├── task-manager.php           ← Tareas/anotaciones
│   │   ├── version-manager.php        ← Historial de versiones de páginas
│   │   ├── block-manager.php          ← Bloques modulares (~20 tipos core)
│   │   ├── page-template-manager.php  ← Plantillas de página (9 tipos core)
│   │   ├── analytics-manager.php      ← Analytics privacy-first (sin cookies)
│   │   ├── webhook-manager.php        ← Webhooks con firma HMAC
│   │   ├── cron-manager.php           ← Pseudo-cron
│   │   ├── updater.php                ← Sistema de actualizaciones
│   │   ├── router.php                 ← Router HTTP
│   │   ├── seed-data.php              ← Datos iniciales (bloques, templates)
│   │   ├── i18n.php                   ← Internacionalización
│   │   ├── lang/
│   │   │   ├── en.json                ← Inglés
│   │   │   └── es.json                ← Español (con acentos/eñes corregidos)
│   │   └── mcp/
│   │       ├── server.php             ← MCP Server (JSON-RPC 2.0)
│   │       ├── token-auth.php         ← Auth: Bearer + OAuth + Basic Auth (App Passwords)
│   │       ├── tool-registry.php      ← Registro de tools MCP
│   │       ├── json-rpc.php           ← Helpers JSON-RPC
│   │       ├── oauth-server.php       ← OAuth 2.0/2.1 con PKCE (S256)
│   │       ├── oauth-authorize-view.php
│   │       ├── rate-limiter.php       ← Rate limiting por IP y por token
│   │       └── tools/                 ← 15+ archivos de tools MCP
│   ├── config/                        ← .encryption_key, config.json.enc (protegido)
│   ├── data/                          ← Datos encriptados (protegido)
│   ├── plugins/                       ← Directorio de plugins
│   ├── public/                        ← Sitio estático generado
│   │   └── js/                        ← klytos-analytics.js, klytos-review.js, etc.
│   └── backups/                       ← Backups automáticos
└── .claude/
    └── skills/                        ← Skills para desarrollo de plugins
        ├── klytos-plugin-development.md
        ├── klytos-core-development.md
        ├── klytos-security-architecture.md
        └── klytos-seo-and-indexing.md
```

## Autenticación MCP

Dos métodos soportados:

### 1. Application Password (Recomendado)
- Se crea desde admin → MCP Connection
- Genera URL con credenciales embebidas: `https://user:pass@domain.com/path/mcp`
- También muestra JSON config para Claude Desktop / settings files
- HTTP Basic Auth

### 2. OAuth 2.0 / 2.1 (Avanzado)
- PKCE (S256) obligatorio para todos los clientes
- Soporta clientes confidenciales y públicos
- Endpoints: `/oauth/authorize`, `/oauth/token`, `/.well-known/oauth-authorization-server`
- Sección colapsada por defecto en el admin

### Bearer tokens v1.0 → ELIMINADOS
- El instalador ya NO genera Bearer tokens
- Solo App Password + OAuth

## Endpoint MCP de producción

```
URL: https://klytos.io/28974823476283542/mcp
GET (sin auth): {"name":"klytos","status":"ok"}  ← FUNCIONA ✓
POST (con auth): JSON-RPC 2.0
```

## Modelo de Negocio

- **Core CMS**: Gratuito (Elastic License 2.0)
- **Plugins**: Marketplace con plugins free y premium
- **NO hay licencia del CMS** — eliminada toda referencia a licencia del core
- La sección "Licencia" del admin es para **licencias de plugins premium**
- Los plugins premium tienen copyright propietario

## Licencia (ELv2)

Todos los archivos PHP llevan cabecera:
```php
/**
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */
```

## Decisiones de Arquitectura

### Core vs Plugin
| Feature | Core/Plugin | Razón |
|---------|------------|-------|
| Analytics | Core | Funcionalidad básica privacy-first |
| Webhooks | Core | Infraestructura que plugins necesitan |
| Pseudo-Cron | Core | Infraestructura que plugins necesitan |
| Formularios | **Plugin** | Territorio clásico de plugins |
| Backups | **Plugin** | Free básico + premium con cloud |

### Menú Admin
- "Tema" renombrado a **"Diseño"** con submenús:
  - Diseño > Tema visual (`theme.php`)
  - Diseño > Plantillas (`templates.php`)
- Sidebar soporta `children` (sub-items)

### URLs Jerárquicas
- Soporte nativo: `dominio.com/servicios/marketing/`
- `parent_slug` en PageManager
- BuildEngine genera `public/servicios/marketing/index.html`
- Sitemap.xml incluye jerarquía
- Breadcrumbs automáticos

### Seguridad
- **CSRF**: Todos los formularios POST tienen token CSRF (excepto login por diseño)
- **CSP**: Todos los `<script>` usan nonce. **Cero event handlers inline** (onclick, onsubmit, etc.)
- **Encryption**: AES-256-GCM para todos los datos en disco
- **Rate Limiting**: Por IP y por token en endpoints MCP/OAuth
- **Admin secreto**: El directorio admin tiene nombre configurable (no predecible)
- **Directories protegidos**: .htaccess bloquea /data/, /config/, /core/, /backups/
- **HTTP Auth passthrough**: .htaccess pasa Authorization header para CGI/FastCGI

### Coding Style
- **NO usar `<?=`** → siempre `<?php echo`
- **Espacios WordPress**: `__( 'key' )`, `htmlspecialchars( $var )`, `count( $array )`
- **PHP claro y con espacios**: `<?php echo __( 'auth.login' ); ?>`
- **Español correcto**: acentos, eñes, signos de interrogación de apertura

### SEO
- Sitemap.xml automático en el build
- llms.txt y llms-full.txt para indexación IA
- Meta generator: `<meta name="generator" content="Klytos CMS">`
- Open Graph, Twitter Cards, hreflang, canonical
- Cabeceras SEO modernas en todas las páginas

### Storage
- **File Storage**: JSON encriptado en disco (por defecto)
- **Database Storage**: MySQL/MariaDB (opcional, configurado en instalador)
- Ambos implementan `StorageInterface`
- `config/` siempre en disco (encryption key, config principal)

## Bugs Corregidos en Esta Sesión

1. ~~`<?=` short tags~~ → Reemplazados por `<?php echo` en 20 archivos
2. ~~Acentos faltantes en es.json~~ → Corregidos ~80 strings
3. ~~`generateCsrf()` no existe~~ → Cambiado a `getCsrfToken()`
4. ~~`setSecurityHeaders()` no existe~~ → Cambiado a `Auth::sendSecurityHeaders()`
5. ~~`__()` undefined~~ → Definida en bootstrap ANTES de boot()
6. ~~onclick inline bloqueados por CSP~~ → Movidos a `<script nonce>`
7. ~~bootstrap.php buscaba en `data/` en vez de `config/`~~ → Corregido a `config/`
8. ~~Sin .htaccess en admin folder~~ → Creado con rutas MCP/OAuth
9. ~~MCP page demasiado compleja~~ → Rediseñada: URL con credenciales primero
10. ~~Instalador mostraba Bearer Token~~ → Ahora muestra App Password + config

## Auditoría MCP Completada (2026-03-26)

Todos los issues encontrados han sido corregidos:

| Issue | Severidad | Estado |
|-------|-----------|--------|
| Notifications: server respondía a notifications (MUST NOT per JSON-RPC 2.0) | CRÍTICO | ✅ Corregido |
| inputSchema sin `required` arrays | CRÍTICO | ✅ Corregido (37 tools) |
| `properties: []` se serializaba como JSON array | CRÍTICO | ✅ Corregido (sanitizeSchema recursivo) |
| `type: "object"` sin inner properties | MEDIO | ✅ additionalProperties:true |
| `type: "array"` sin items | MEDIO | ✅ items schema añadido |
| Plugin tools sin sanitizar via hook | MEDIO | ✅ Sanitizado post-hook |
| ping devolvía `{status:"pong"}` en vez de `{}` | MENOR | ✅ Corregido |

## Pendiente

- [ ] Verificar que la conexión MCP funciona end-to-end con autenticación
- [ ] Probar creación de páginas vía MCP
- [ ] Integrar seedDefaultData() en install.php
- [ ] Probar build engine (generación de sitio estático)
- [ ] Añadir cabeceras ELv2 a archivos nuevos que falten
- [ ] Revisión completa de seguridad del proyecto
- [ ] Implementar marketplace de plugins (futuro)
- [ ] Diseño moderno del admin (CSS mejorable)
