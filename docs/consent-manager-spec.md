# Consent Manager API - Especificacion Tecnica

## Contexto del problema

En un sitio con frontend estatico, los plugins pueden necesitar:

- Establecer cookies (sesion, analiticas, marketing)
- Cargar scripts externos (Google Analytics, pixels de conversion, etc.)
- Ejecutar codigo JavaScript que depende de esas cookies

El problema es que, por normativa (GDPR, ePrivacy, CCPA), no se puede hacer nada de esto sin el consentimiento previo del visitante. Y como el frontend es estatico, no hay servidor que filtre que enviar; todo debe resolverse en el lado del cliente.

Adicionalmente, el **administrador del sitio** necesita poder ver exactamente que declara cada plugin instalado: que cookies pone, que scripts carga y en que categoria se clasifica. Esto es necesario tanto para cumplimiento legal como para auditoria.

---

## Arquitectura general

```
+------------------------------------------------------------------+
|                        FRONTEND ESTATICO                          |
|                                                                   |
|  +-----------------------+    +------------------------------+    |
|  |   consent-manager.js  |    |     Plugins (N plugins)      |    |
|  |                       |    |                              |    |
|  |  - Lee/guarda cookie  |<---|  register({                  |    |
|  |    de consentimiento  |    |    pluginId, name,           |    |
|  |  - Muestra banner     |    |    category,                 |    |
|  |  - Carga/bloquea JS   |    |    cookies: [...],           |    |
|  |  - Limpia cookies     |    |    scripts: [...],           |    |
|  |  - Notifica cambios   |    |    onAccept, onReject        |    |
|  |                       |    |  })                          |    |
|  +-----------+-----------+    +------------------------------+    |
|              |                                                    |
|              v                                                    |
|  +-----------------------------------------------------------+   |
|  |                  Panel del Visitante                       |   |
|  |  Banner -> Aceptar todo / Solo obligatorias / Configurar  |   |
|  |  Panel lateral con categorias, toggles, detalle plugins   |   |
|  +-----------------------------------------------------------+   |
|                                                                   |
|  +-----------------------------------------------------------+   |
|  |              Panel de Administracion                       |   |
|  |  Vista completa de todos los plugins registrados           |   |
|  |  Cookies declaradas, scripts, categorias, estado           |   |
|  |  Exportacion para auditoria legal                          |   |
|  +-----------------------------------------------------------+   |
+------------------------------------------------------------------+
```

### Componentes principales

1. **consent-manager.js** - Nucleo del sistema. API JavaScript que los plugins consumen.
2. **Banner de cookies** - UI para el visitante (se muestra si no hay consentimiento previo).
3. **Panel de configuracion del visitante** - Panel lateral donde el visitante elige por categorias y ve que hace cada plugin.
4. **Panel de administracion** - Pagina/vista independiente donde el admin ve la auditoria completa de todo lo que los plugins han declarado.

---

## Flujo de consentimiento

```
Visitante llega al sitio
        |
        v
  Hay cookie __consent_prefs?
        |
   SI --+-- NO
   |         |
   v         v
 Leer    Mostrar banner
 prefs   (3 opciones)
   |         |
   v         v
 Aplicar   Visitante elige:
 reglas    - "Aceptar todas" -> todas las categorias = true
           - "Solo obligatorias" -> solo necessary = true
           - "Configurar" -> Panel lateral con toggles por categoria
                |
                v
           Guardar eleccion en cookie __consent_prefs (tecnica/obligatoria)
                |
                v
           Aplicar reglas:
           - Categorias aceptadas: cargar scripts, ejecutar onAccept
           - Categorias rechazadas: no cargar nada, ejecutar onReject
           - Categorias revocadas: limpiar cookies declaradas
```

### La cookie de consentimiento

La propia cookie que guarda las preferencias es **tecnica/obligatoria** (no requiere consentimiento bajo GDPR, porque es necesaria para recordar la decision del visitante).

Formato:

```json
{
  "version": 1,
  "timestamp": "2026-03-31T10:00:00.000Z",
  "categories": {
    "necessary": true,
    "functional": false,
    "analytics": true,
    "marketing": false
  }
}
```

---

## Categorias de cookies

| Categoria    | Siempre activa | Descripcion |
|-------------|:-:|---|
| `necessary`  | Si | Cookies esenciales: sesion, consentimiento, seguridad (CSRF), preferencias basicas |
| `functional` | No | Mejoran la experiencia: chat en vivo, personalizacion, idioma |
| `analytics`  | No | Medicion de trafico y comportamiento: Google Analytics, Matomo, Plausible |
| `marketing`  | No | Publicidad y remarketing: Meta Pixel, Google Ads, cookies de conversion |

Las categorias son extensibles. El administrador puede definir categorias adicionales al inicializar el Consent Manager.

---

## API para desarrolladores de plugins

### Registro de un plugin

Todo plugin que use cookies o cargue scripts externos **debe** registrarse. Es la unica forma de que sus recursos se carguen (el Consent Manager bloquea todo lo no registrado).

```javascript
ConsentManager.register({
  // --- OBLIGATORIO ---
  pluginId: 'google-analytics',       // ID unico del plugin
  name: 'Google Analytics',           // Nombre legible
  category: 'analytics',              // Categoria de consentimiento

  // --- RECOMENDADO ---
  description: 'Recopila estadisticas de visitas y comportamiento de forma anonimizada.',
  vendor: 'Google LLC',               // Empresa proveedora
  privacyUrl: 'https://policies.google.com/privacy',

  // --- DECLARACION DE COOKIES ---
  cookies: [
    {
      name: '_ga',                     // Nombre exacto de la cookie
      duration: '2 anos',             // Duracion legible
      description: 'Distingue usuarios unicos asignando un ID aleatorio',
      type: 'cookie',                 // 'cookie' | 'localStorage' | 'sessionStorage'
      paths: ['/']                    // Paths donde se establece (para limpieza)
    },
    {
      name: '_ga_XXXXX',
      duration: '2 anos',
      description: 'Mantiene el estado de la sesion'
    },
    {
      name: '_gid',
      duration: '24 horas',
      description: 'Distingue usuarios (renovable cada 24h)'
    }
  ],

  // --- DECLARACION DE SCRIPTS ---
  scripts: [
    'https://www.googletagmanager.com/gtag/js?id=G-XXXXX'
  ],

  // --- CALLBACKS ---
  onAccept: function(errors) {
    // Se ejecuta cuando el visitante acepta la categoria 'analytics'.
    // Los scripts de 'scripts' ya se han inyectado antes de llamar aqui.
    // 'errors' es null si todo fue bien, o un array de errores si algun script fallo.
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-XXXXX');
  },

  onReject: function() {
    // Se ejecuta cuando el visitante revoca la categoria.
    // Las cookies declaradas en 'cookies' ya se han eliminado automaticamente.
    // Aqui puedes hacer limpieza adicional si es necesario.
    window.dataLayer = undefined;
  }
});
```

### Consultar el estado del consentimiento

Un plugin puede preguntar antes de hacer algo:

```javascript
if (ConsentManager.hasConsent('analytics')) {
  // El visitante ha aceptado analiticas, puedo proceder
  trackEvent('page_view');
} else {
  // No hay consentimiento, no hacer nada
}
```

### Escuchar cambios de consentimiento

Si el visitante cambia sus preferencias en tiempo real:

```javascript
var unsubscribe = ConsentManager.onChange(function(categories) {
  if (categories.analytics) {
    initMyTracking();
  } else {
    stopMyTracking();
  }
});

// Para dejar de escuchar:
unsubscribe();
```

### Obtener el estado completo

```javascript
var state = ConsentManager.getConsentState();
// { necessary: true, functional: false, analytics: true, marketing: false }
```

### Obtener informacion de un plugin especifico

```javascript
var info = ConsentManager.getPluginInfo('google-analytics');
// {
//   pluginId: 'google-analytics',
//   name: 'Google Analytics',
//   category: 'analytics',
//   cookies: [...],
//   scripts: [...],
//   activated: true
// }
```

---

## Bloqueo de scripts inline con atributos HTML

Ademas de los scripts declarados en `register()`, los desarrolladores pueden bloquear scripts directamente en el HTML usando atributos `data-`:

```html
<!-- Este script NO se ejecuta hasta que el visitante acepte 'analytics' -->
<script type="text/plain" data-consent-category="analytics">
  // Codigo de tracking que solo se ejecuta con consentimiento
  fbq('init', '1234567890');
  fbq('track', 'PageView');
</script>

<!-- Script externo bloqueado -->
<script type="text/plain" data-consent-category="marketing"
        src="https://connect.facebook.net/en_US/fbevents.js">
</script>
```

El truco esta en `type="text/plain"`: el navegador no ejecuta scripts con un type que no reconoce. Cuando el visitante acepta la categoria correspondiente, el Consent Manager clona el elemento con `type="text/javascript"` y lo inyecta en el DOM.

---

## Panel de administracion

### Proposito

El panel de administracion es para el **administrador del sitio**, no para el visitante. Permite:

- Ver todos los plugins registrados y su categoria
- Ver exactamente que cookies declara cada plugin (nombre, duracion, descripcion)
- Ver que scripts externos carga cada plugin
- Ver el estado actual (activo/inactivo segun consentimiento)
- Exportar un informe para auditoria legal

### API del panel de administracion

```javascript
// Obtener el registro completo agrupado por categorias
var registry = ConsentManager.getPluginRegistry();

// Estructura devuelta:
// {
//   necessary: {
//     category: { id, name, description, required },
//     plugins: [
//       {
//         pluginId: 'session-handler',
//         name: 'Gestor de Sesion',
//         description: '...',
//         vendor: '',
//         privacyUrl: '',
//         cookies: [{ name, duration, description, type }],
//         scripts: ['...'],
//         activated: true
//       }
//     ]
//   },
//   functional: { ... },
//   analytics: { ... },
//   marketing: { ... }
// }
```

### Implementacion del panel de administracion

El panel de administracion se implementa como una pagina HTML independiente que el admin incluye en su area privada. Aqui va el codigo completo:

```html
<!-- admin-cookies.html -->
<!-- Incluir en la zona de administracion del sitio -->
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Administracion - Cookies y Plugins</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: #f5f5f5; color: #333; padding: 32px;
    }
    .admin-header {
      margin-bottom: 32px;
    }
    .admin-header h1 { font-size: 24px; margin-bottom: 8px; }
    .admin-header p { color: #666; font-size: 14px; }

    .admin-summary {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px; margin-bottom: 32px;
    }
    .summary-card {
      background: #fff; border-radius: 8px; padding: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .summary-card .number { font-size: 32px; font-weight: 700; color: #0066cc; }
    .summary-card .label { font-size: 13px; color: #888; margin-top: 4px; }

    .admin-section {
      background: #fff; border-radius: 8px; padding: 24px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px;
    }
    .admin-section h2 {
      font-size: 18px; margin-bottom: 16px;
      padding-bottom: 12px; border-bottom: 1px solid #e5e5e5;
    }

    .plugin-card {
      border: 1px solid #e5e5e5; border-radius: 6px; padding: 16px;
      margin-bottom: 12px;
    }
    .plugin-card h3 { font-size: 15px; margin-bottom: 4px; }
    .plugin-meta { font-size: 12px; color: #888; margin-bottom: 8px; }
    .plugin-desc { font-size: 13px; color: #666; margin-bottom: 12px; }

    table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 8px; }
    th { text-align: left; padding: 8px; background: #f9f9f9;
         border-bottom: 2px solid #e5e5e5; font-weight: 600; color: #555; }
    td { padding: 8px; border-bottom: 1px solid #f0f0f0; }
    code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: 12px; }

    .badge {
      display: inline-block; padding: 2px 10px; border-radius: 12px;
      font-size: 11px; font-weight: 600;
    }
    .badge-active { background: #e6f4ea; color: #1e7e34; }
    .badge-inactive { background: #fce8e6; color: #c5221f; }
    .badge-necessary { background: #e8f0fe; color: #1967d2; }
    .badge-functional { background: #fef7e0; color: #b06000; }
    .badge-analytics { background: #f3e8fd; color: #7627bb; }
    .badge-marketing { background: #fce8e6; color: #c5221f; }

    .script-url { font-size: 12px; color: #888; word-break: break-all; font-family: monospace; }

    .export-btn {
      background: #0066cc; color: #fff; border: none; padding: 10px 20px;
      border-radius: 6px; cursor: pointer; font-size: 14px;
    }
    .export-btn:hover { background: #0052a3; }

    .no-plugins { color: #888; font-style: italic; padding: 12px 0; }
  </style>
</head>
<body>
  <div class="admin-header">
    <h1>Auditoria de Cookies y Plugins</h1>
    <p>Vista completa de todos los plugins registrados, sus cookies y scripts.</p>
  </div>

  <div class="admin-summary" id="admin-summary"></div>
  <div id="admin-content"></div>

  <div style="margin-top: 24px;">
    <button class="export-btn" onclick="exportAudit()">Exportar informe (JSON)</button>
    <button class="export-btn" onclick="exportAuditCSV()" style="margin-left: 8px; background: #555;">
      Exportar cookies (CSV)
    </button>
  </div>

  <!-- Incluir consent-manager.js antes de este script -->
  <script src="consent-manager.js"></script>
  <script>
    function renderAdminPanel() {
      var registry = ConsentManager.getPluginRegistry();

      // Resumen
      var totalPlugins = 0;
      var totalCookies = 0;
      var totalScripts = 0;
      var activePlugins = 0;

      for (var cat in registry) {
        var plugins = registry[cat].plugins;
        totalPlugins += plugins.length;
        for (var i = 0; i < plugins.length; i++) {
          totalCookies += plugins[i].cookies.length;
          totalScripts += plugins[i].scripts.length;
          if (plugins[i].activated) activePlugins++;
        }
      }

      document.getElementById('admin-summary').innerHTML =
        '<div class="summary-card"><div class="number">' + totalPlugins + '</div><div class="label">Plugins registrados</div></div>' +
        '<div class="summary-card"><div class="number">' + totalCookies + '</div><div class="label">Cookies declaradas</div></div>' +
        '<div class="summary-card"><div class="number">' + totalScripts + '</div><div class="label">Scripts externos</div></div>' +
        '<div class="summary-card"><div class="number">' + activePlugins + '</div><div class="label">Plugins activos</div></div>';

      // Contenido por categorias
      var html = '';
      for (var catId in registry) {
        var catData = registry[catId];
        var cat = catData.category;
        var plugins = catData.plugins;

        html += '<div class="admin-section">';
        html += '<h2><span class="badge badge-' + catId + '">' + cat.name + '</span>';
        html += ' (' + plugins.length + ' plugin' + (plugins.length !== 1 ? 's' : '') + ')';
        if (cat.required) html += ' &mdash; Siempre activa';
        html += '</h2>';

        if (plugins.length === 0) {
          html += '<p class="no-plugins">No hay plugins registrados en esta categoria.</p>';
        }

        for (var p = 0; p < plugins.length; p++) {
          var plugin = plugins[p];
          html += '<div class="plugin-card">';
          html += '<h3>' + plugin.name + ' ';
          html += '<span class="badge ' + (plugin.activated ? 'badge-active' : 'badge-inactive') + '">';
          html += plugin.activated ? 'Activo' : 'Inactivo';
          html += '</span></h3>';
          html += '<div class="plugin-meta">';
          html += 'ID: <code>' + plugin.pluginId + '</code>';
          if (plugin.vendor) html += ' | Proveedor: ' + plugin.vendor;
          if (plugin.privacyUrl) html += ' | <a href="' + plugin.privacyUrl + '" target="_blank">Politica de privacidad</a>';
          html += '</div>';
          if (plugin.description) {
            html += '<div class="plugin-desc">' + plugin.description + '</div>';
          }

          // Tabla de cookies
          if (plugin.cookies.length > 0) {
            html += '<table>';
            html += '<thead><tr><th>Cookie</th><th>Tipo</th><th>Duracion</th><th>Descripcion</th></tr></thead>';
            html += '<tbody>';
            for (var c = 0; c < plugin.cookies.length; c++) {
              var ck = plugin.cookies[c];
              html += '<tr>';
              html += '<td><code>' + ck.name + '</code></td>';
              html += '<td>' + (ck.type || 'cookie') + '</td>';
              html += '<td>' + ck.duration + '</td>';
              html += '<td>' + ck.description + '</td>';
              html += '</tr>';
            }
            html += '</tbody></table>';
          }

          // Scripts
          if (plugin.scripts.length > 0) {
            html += '<div style="margin-top: 8px;"><strong>Scripts externos:</strong></div>';
            for (var s = 0; s < plugin.scripts.length; s++) {
              html += '<div class="script-url">' + plugin.scripts[s] + '</div>';
            }
          }

          html += '</div>'; // plugin-card
        }

        html += '</div>'; // admin-section
      }

      document.getElementById('admin-content').innerHTML = html;
    }

    function exportAudit() {
      var registry = ConsentManager.getPluginRegistry();
      var data = {
        exportDate: new Date().toISOString(),
        consentManagerVersion: ConsentManager.VERSION,
        registry: registry
      };
      var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url;
      a.download = 'cookie-audit-' + new Date().toISOString().split('T')[0] + '.json';
      a.click();
      URL.revokeObjectURL(url);
    }

    function exportAuditCSV() {
      var registry = ConsentManager.getPluginRegistry();
      var rows = [['Plugin ID', 'Plugin', 'Categoria', 'Cookie', 'Tipo', 'Duracion', 'Descripcion']];
      for (var catId in registry) {
        var plugins = registry[catId].plugins;
        for (var p = 0; p < plugins.length; p++) {
          var plugin = plugins[p];
          if (plugin.cookies.length === 0) {
            rows.push([plugin.pluginId, plugin.name, catId, '-', '-', '-', '-']);
          }
          for (var c = 0; c < plugin.cookies.length; c++) {
            var ck = plugin.cookies[c];
            rows.push([
              plugin.pluginId, plugin.name, catId,
              ck.name, ck.type || 'cookie', ck.duration, ck.description
            ]);
          }
        }
      }
      var csv = rows.map(function(r) {
        return r.map(function(cell) {
          return '"' + String(cell).replace(/"/g, '""') + '"';
        }).join(',');
      }).join('\n');
      var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url;
      a.download = 'cookie-audit-' + new Date().toISOString().split('T')[0] + '.csv';
      a.click();
      URL.revokeObjectURL(url);
    }

    // Inicializar sin mostrar banner (estamos en admin)
    ConsentManager.init({ autoShow: false });

    // Renderizar cuando el DOM este listo
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', renderAdminPanel);
    } else {
      renderAdminPanel();
    }
  </script>
</body>
</html>
```

---

## Inicializacion del sistema

### En el sitio publico (frontend)

```html
<!DOCTYPE html>
<html>
<head>
  <!-- 1. Cargar el Consent Manager ANTES de cualquier plugin -->
  <script src="consent-manager.js"></script>

  <!-- 2. Scripts bloqueados nativamente con data-consent-category -->
  <script type="text/plain" data-consent-category="analytics"
          src="https://www.googletagmanager.com/gtag/js?id=G-XXXXX">
  </script>
  <script type="text/plain" data-consent-category="analytics">
    window.dataLayer = window.dataLayer || [];
    function gtag(){ dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-XXXXX');
  </script>
</head>
<body>

  <!-- 3. Inicializar el Consent Manager -->
  <script>
    ConsentManager.init({
      bannerText: 'Utilizamos cookies para mejorar tu experiencia. Puedes aceptar todas, solo las necesarias, o configurar tus preferencias.',
      privacyUrl: '/politica-de-privacidad',
      autoShow: true
    });
  </script>

  <!-- 4. Los plugins se registran en sus propios archivos JS -->
  <script src="plugins/mi-plugin-analytics.js"></script>
  <script src="plugins/mi-plugin-chat.js"></script>

</body>
</html>
```

### Opciones de configuracion

| Parametro | Tipo | Default | Descripcion |
|-----------|------|---------|-------------|
| `bannerText` | string | Texto por defecto en espanol | Texto del banner |
| `privacyUrl` | string | null | URL de la politica de privacidad (se muestra como enlace) |
| `autoShow` | boolean | true | Mostrar el banner automaticamente si no hay consentimiento |
| `categories` | object | null | Categorias personalizadas (se fusionan con las 4 por defecto) |

### Categorias personalizadas

```javascript
ConsentManager.init({
  categories: {
    // Modificar una existente
    analytics: {
      name: 'Estadisticas',
      description: 'Cookies de medicion de trafico'
    },
    // Agregar una nueva
    social: {
      name: 'Redes sociales',
      description: 'Permiten compartir contenido e integrar widgets de redes sociales.',
      required: false
    }
  }
});
```

---

## Ejemplo completo: Plugin de chat en vivo

```javascript
// plugins/livechat-plugin.js

ConsentManager.register({
  pluginId: 'livechat-crisp',
  name: 'Crisp Live Chat',
  category: 'functional',
  description: 'Widget de chat en vivo para soporte al cliente.',
  vendor: 'Crisp IM SAS',
  privacyUrl: 'https://crisp.chat/en/privacy/',

  cookies: [
    {
      name: 'crisp-client%2Fsession%2F*',
      duration: '6 meses',
      description: 'Identificador de sesion del chat',
      type: 'cookie'
    },
    {
      name: 'crisp-client%2Fsocket%2F*',
      duration: 'Sesion',
      description: 'Conexion de socket del chat en tiempo real',
      type: 'cookie'
    }
  ],

  scripts: [
    'https://client.crisp.chat/l.js'
  ],

  onAccept: function(errors) {
    if (errors) {
      console.error('Error cargando Crisp:', errors);
      return;
    }
    window.$crisp = [];
    window.CRISP_WEBSITE_ID = "tu-crisp-id-aqui";
  },

  onReject: function() {
    // Limpiar el widget del DOM si existe
    var crispElements = document.querySelectorAll('[class^="crisp"]');
    crispElements.forEach(function(el) { el.remove(); });
    delete window.$crisp;
    delete window.CRISP_WEBSITE_ID;
  }
});
```

---

## Ejemplo completo: Plugin con cookies de sesion (obligatorias)

```javascript
// plugins/session-plugin.js

ConsentManager.register({
  pluginId: 'session-manager',
  name: 'Gestor de Sesion',
  category: 'necessary',     // <-- Obligatoria, siempre activa
  description: 'Mantiene la sesion del usuario autenticado.',

  cookies: [
    {
      name: 'PHPSESSID',
      duration: 'Sesion',
      description: 'Identificador de sesion del servidor',
      type: 'cookie'
    },
    {
      name: 'csrf_token',
      duration: 'Sesion',
      description: 'Token de proteccion contra ataques CSRF',
      type: 'cookie'
    }
  ],

  scripts: [],

  onAccept: function() {
    // Las cookies de sesion se gestionan por el servidor,
    // aqui solo declaramos su existencia para transparencia.
    console.log('[Session] Plugin de sesion activo');
  }
});
```

---

## Metodos de la API publica

### Gestion del consentimiento

| Metodo | Descripcion |
|--------|-------------|
| `ConsentManager.init(config)` | Inicializa el sistema. Llamar una vez al cargar. |
| `ConsentManager.acceptAll()` | Acepta todas las categorias. |
| `ConsentManager.acceptNecessaryOnly()` | Acepta solo las obligatorias. |
| `ConsentManager.updateConsent(categories)` | Actualiza categorias especificas: `{ analytics: true, marketing: false }` |
| `ConsentManager.hasConsent(category)` | Devuelve `true`/`false` para una categoria. |
| `ConsentManager.getConsentState()` | Devuelve el estado completo o `null` si no hay consentimiento. |
| `ConsentManager.revokeAll()` | Revoca todo y limpia cookies de categorias no obligatorias. |
| `ConsentManager.reset()` | Resetea completamente y vuelve a mostrar el banner. |

### Registro de plugins

| Metodo | Descripcion |
|--------|-------------|
| `ConsentManager.register(opts)` | Registra un plugin (ver estructura arriba). |
| `ConsentManager.unregister(pluginId)` | Elimina un plugin del registro. |
| `ConsentManager.getPluginInfo(pluginId)` | Info de un plugin especifico. |
| `ConsentManager.getPluginRegistry()` | Registro completo agrupado por categorias (para admin). |

### UI

| Metodo | Descripcion |
|--------|-------------|
| `ConsentManager.showSettings()` | Abre el panel de configuracion del visitante. |
| `ConsentManager.hideSettings()` | Cierra el panel. |

### Eventos

| Metodo | Descripcion |
|--------|-------------|
| `ConsentManager.onChange(callback)` | Escucha cambios. Devuelve funcion para desuscribirse. |

### Constantes

| Propiedad | Valor |
|-----------|-------|
| `ConsentManager.CATEGORIES` | Objeto con las categorias definidas |
| `ConsentManager.VERSION` | Version del Consent Manager (string) |

---

## Consideraciones tecnicas para frontend estatico

### Por que funciona sin servidor

Todo el sistema opera en el cliente:

1. **La cookie de consentimiento** se lee/escribe con `document.cookie` desde JavaScript.
2. **Los scripts bloqueados** usan `type="text/plain"` que el navegador ignora nativamente; el Consent Manager los activa reemplazando el nodo.
3. **Los plugins se registran** via JavaScript; la logica de carga condicional es puramente client-side.
4. **El panel de administracion** es una pagina HTML estatica que simplemente llama a `getPluginRegistry()` para mostrar los datos.

### Limitaciones conocidas

- **Scripts de terceros**: Una vez cargado un script externo, no se puede "descargar" de la pagina. La revocacion impide que se cargue en la siguiente visita, pero no detiene uno ya en ejecucion (requeriria recargar la pagina).
- **Cookies de terceros (third-party)**: Si un script externo pone cookies desde su propio dominio, el Consent Manager no puede eliminarlas (restriccion del navegador). Solo puede evitar que el script se cargue en primer lugar.
- **localStorage/sessionStorage**: El Consent Manager puede declarar su uso para transparencia, pero la limpieza automatica requiere que el plugin coopere via `onReject`.

### Orden de carga recomendado

```
1. consent-manager.js          (lo primero, antes de todo)
2. ConsentManager.init({...})  (configurar)
3. Scripts con type="text/plain" data-consent-category="..."
4. Archivos JS de plugins que llaman a ConsentManager.register()
```

---

## Seguridad y cumplimiento

### GDPR

- El consentimiento es opt-in (nada se carga hasta que el visitante acepta).
- Las categorias obligatorias (`necessary`) son las unicas activas por defecto.
- El visitante puede revocar el consentimiento en cualquier momento.
- Se guarda la fecha del consentimiento para demostrar cuando se dio.

### Cookie de consentimiento

- Nombre: `__consent_prefs`
- Duracion: 365 dias (configurable)
- SameSite: Lax
- Secure: si en HTTPS
- Es una cookie tecnica/obligatoria (necesaria para recordar la decision).

### Version del consentimiento

Si cambias los plugins o las categorias, puedes incrementar `CONSENT_VERSION` en el codigo. Cuando la version guardada no coincide con la actual, el banner se muestra de nuevo para que el visitante reconfirme.
