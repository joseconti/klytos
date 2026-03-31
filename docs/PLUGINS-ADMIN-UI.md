# Klytos -- Pagina de Administracion de Plugins

**Version:** 0.15.0
**Fecha:** 31 de marzo de 2026
**Estado:** Propuesta de diseno

---

## 1. Estado actual

La pagina `admin/plugins.php` muestra una tabla basica con los plugins instalados:
- Columnas: Plugin (nombre + descripcion), Version, Author, Type (Free/Premium), Estado (Active/Inactive), Acciones (Activate/Deactivate)
- Las acciones recargan la pagina completa (POST con redirect)
- No hay acciones en lote
- No hay boton de eliminar ni actualizar
- No hay feedback visual (solo alertas tras recarga)

---

## 2. Objetivos

Convertir la pagina de plugins en una experiencia moderna tipo WordPress:

1. **Todo por AJAX** — ninguna accion recarga la pagina
2. **Acciones en lote** — seleccionar multiples plugins y aplicar una accion
3. **Acciones individuales** — cada plugin tiene sus propios botones
4. **Feedback visual en tiempo real** — spinners, notificaciones, cambios de estado sin recarga
5. **Confirmaciones** — modal antes de acciones destructivas (eliminar)
6. **Accesibilidad** — teclado, ARIA, focus management

---

## 3. Diseno de la interfaz

### 3.1. Barra de acciones en lote (encima de la tabla)

```
[checkbox] Seleccionar todos   |  Accion en lote: [▼ Seleccionar accion]  [Aplicar]
                                   - Activar
                                   - Desactivar
                                   - Eliminar
                                   - Actualizar
```

- El checkbox "Seleccionar todos" marca/desmarca todos los checkboxes de la tabla
- El select de accion solo se habilita cuando hay al menos un plugin seleccionado
- El boton "Aplicar" ejecuta la accion sobre todos los seleccionados via AJAX

### 3.2. Tabla de plugins (rediseno)

```
[x] | Plugin                              | Version | Author      | Type    | Estado   | Acciones
----|--------------------------------------|---------|-------------|---------|----------|------------------
[x] | Hello AI                             | 1.0.0   | Jose Conti  | Free    | Active   | Desactivar | Eliminar
    | A demo plugin that adds...           |         |             |         |          |
----|--------------------------------------|---------|-------------|---------|----------|------------------
[ ] | Klytos E-Commerce                    | 2.1.0   | Jose Conti  | Premium | Inactive | Activar | Eliminar
    | Tienda online completa...            |         |             |         | Update   |
    |                                      |         |             |         | available|
```

Cada fila tiene:
- **Checkbox** a la izquierda para seleccion en lote
- **Nombre + descripcion** (nombre en bold, descripcion debajo en gris)
- **Version** con badge de "Update available" si hay actualizacion
- **Author** con link a author_url
- **Type** badge: Free (verde) o Premium (dorado)
- **Estado** badge: Active (verde) o Inactive (gris)
- **Acciones** como botones inline:
  - Plugin activo: `Desactivar` | `Eliminar`
  - Plugin inactivo: `Activar` | `Eliminar`
  - Si hay update: `Actualizar` (boton azul)

### 3.3. Indicador de plugin legacy

Si `discovery_method === 'json_legacy'`, mostrar un badge amarillo:
```
⚠ Legacy format — migrate to PHP header
```

### 3.4. Feedback visual

- **Al ejecutar una accion**: el boton muestra un spinner y se deshabilita
- **Al completar**: notificacion toast en la esquina superior derecha (verde=exito, rojo=error)
- **El estado cambia en la tabla sin recargar**: el badge Active/Inactive se actualiza, los botones de accion cambian
- **Al eliminar**: la fila se desvanece con animacion y se elimina del DOM
- **Al activar/desactivar en lote**: cada fila se actualiza secuencialmente con una pequena animacion

### 3.5. Modal de confirmacion para eliminar

```
┌─────────────────────────────────────────┐
│  Eliminar plugin                        │
│                                         │
│  ¿Estas seguro de que quieres eliminar  │
│  "Hello AI"?                            │
│                                         │
│  Esta accion eliminara todos los        │
│  archivos del plugin. Los datos del     │
│  plugin se mantendran.                  │
│                                         │
│  [ ] Eliminar tambien los datos del     │
│      plugin (no se puede deshacer)      │
│                                         │
│           [Cancelar]  [Eliminar]        │
└─────────────────────────────────────────┘
```

- Checkbox opcional para eliminar datos (ejecuta uninstall.php)
- Boton "Eliminar" en rojo
- Se puede cerrar con Escape o click fuera

---

## 4. Endpoint AJAX

### 4.1. Archivo a crear: `admin/api/plugins.php`

Endpoint unico para todas las operaciones de plugins via AJAX.

**Request**: POST con JSON body + CSRF token.

```
POST /admin/api/plugins.php
Content-Type: application/json
X-CSRF-Token: {token}

{
    "action": "activate|deactivate|delete|uninstall|check_updates",
    "plugins": ["hello-ai", "klytos-ecommerce"]
}
```

**Acciones disponibles:**

| Accion | Descripcion | Respuesta |
|---|---|---|
| `activate` | Activar uno o mas plugins | `{ success: true, results: { "hello-ai": { success: true } } }` |
| `deactivate` | Desactivar uno o mas plugins | Igual que activate |
| `delete` | Eliminar archivos del plugin (sin datos) | Igual, pero borra el directorio |
| `uninstall` | Desinstalar + eliminar archivos y datos | Ejecuta uninstall.php + borra directorio |
| `check_updates` | Comprobar actualizaciones disponibles | `{ updates: { "plugin-id": { current: "1.0", latest: "1.1", url: "..." } } }` |
| `update` | Descargar e instalar actualizacion | `{ success: true, new_version: "1.1.0" }` |

**Respuesta comun:**

```json
{
    "success": true,
    "results": {
        "hello-ai": {
            "success": true,
            "message": "Plugin activated successfully"
        },
        "broken-plugin": {
            "success": false,
            "error": "Requires PHP 8.3+"
        }
    }
}
```

### 4.2. Seguridad

- Verificar CSRF token en cada request
- Verificar que el usuario tiene el permiso `plugins.manage`
- Rate limiting: maximo 30 operaciones por minuto por IP
- Sanitizar plugin IDs (solo alfanumerico, guiones, underscores)
- La accion `delete`/`uninstall` requiere confirmacion previa (el modal la proporciona en el frontend)

---

## 5. JavaScript

### 5.1. Archivo a crear: `admin/assets/js/klytos-plugins.js`

Vanilla JavaScript (sin jQuery, igual que el resto del admin).

**Estructura:**

```javascript
(function () {
    'use strict';

    const Plugins = {
        el: {},           // DOM element cache
        csrf: '',         // CSRF token
        apiUrl: '',       // API endpoint URL
        selected: [],     // Selected plugin IDs

        init() { ... },

        // ── API ──
        api(action, plugins) { ... },          // Fetch wrapper

        // ── Individual actions ──
        activate(pluginId) { ... },
        deactivate(pluginId) { ... },
        confirmDelete(pluginId) { ... },       // Show modal
        deletePlugin(pluginId, withData) { ... },
        updatePlugin(pluginId) { ... },

        // ── Bulk actions ──
        selectAll(checked) { ... },
        updateBulkUI() { ... },               // Enable/disable bulk controls
        applyBulk() { ... },

        // ── UI updates ──
        setRowState(pluginId, state) { ... },  // Update badge + buttons
        removeRow(pluginId) { ... },           // Fade out + remove
        showSpinner(btn) { ... },
        hideSpinner(btn) { ... },
        toast(message, type) { ... },          // Success/error notification

        // ── Modal ──
        showDeleteModal(pluginId, pluginName) { ... },
        hideDeleteModal() { ... },
    };

    document.addEventListener('DOMContentLoaded', function () {
        Plugins.init();
    });
})();
```

### 5.2. Patron de boton con spinner

```javascript
async activate(pluginId) {
    const btn = document.querySelector(`[data-action="activate"][data-plugin="${pluginId}"]`);
    this.showSpinner(btn);

    const result = await this.api('activate', [pluginId]);

    this.hideSpinner(btn);

    if (result.results[pluginId]?.success) {
        this.setRowState(pluginId, 'active');
        this.toast('Plugin activated', 'success');
    } else {
        this.toast(result.results[pluginId]?.error || 'Error', 'error');
    }
}
```

### 5.3. Toast notifications

Notificaciones temporales (3 segundos) en la esquina superior derecha:

```html
<div class="plugin-toast plugin-toast-success">
    <i class="fa-solid fa-check-circle"></i>
    Plugin activated successfully
</div>
```

Se animan con CSS (slide in from right, fade out).

---

## 6. CSS

### 6.1. Estilos nuevos a anadir

Ya sea inline en plugins.php o en un archivo CSS dedicado:

- `.plugin-checkbox` — checkbox de seleccion
- `.plugin-row` — fila de la tabla con transicion para animaciones
- `.plugin-row.removing` — animacion de fade out al eliminar
- `.plugin-row.updating` — background pulsante durante actualizacion
- `.plugin-actions` — contenedor de botones de accion
- `.plugin-actions .btn` — botones inline con gap
- `.plugin-spinner` — spinner circular (CSS puro)
- `.plugin-toast` — notificacion toast
- `.plugin-toast-success` / `.plugin-toast-error` — variantes de color
- `.plugin-bulk-bar` — barra de acciones en lote
- `.plugin-badge-update` — badge de "Update available"
- `.plugin-badge-legacy` — badge amarillo para plugins legacy
- `.plugin-delete-modal` — modal de confirmacion de eliminacion

---

## 7. Modificaciones en plugins.php

### 7.1. Cambios en el HTML

- Anadir data attributes al contenedor: `data-csrf`, `data-api-url`
- Anadir checkboxes en cada fila
- Anadir barra de acciones en lote encima de la tabla
- Cambiar los botones de accion de links/forms a botones con `data-action` y `data-plugin`
- Anadir el modal de confirmacion de eliminacion al final del HTML
- Cargar `klytos-plugins.js` con nonce

### 7.2. Eliminar

- Los formularios POST actuales para activar/desactivar
- Los redirects tras acciones
- Las alertas basadas en query params

---

## 8. Hooks para plugins

La pagina de plugins debe tener hooks para que otros plugins puedan extenderla:

| Hook | Tipo | Descripcion |
|---|---|---|
| `admin.plugins_page_actions` | filter | Anadir acciones personalizadas al dropdown de acciones en lote |
| `admin.plugins_row_actions` | filter | Anadir botones de accion personalizados a cada fila (recibe $pluginId, $manifest) |
| `admin.plugins_columns` | filter | Anadir/modificar columnas de la tabla |
| `admin.plugins_row_data` | filter | Modificar los datos de cada fila antes de renderizar |
| `admin.plugins_before_table` | action | Inyectar HTML antes de la tabla |
| `admin.plugins_after_table` | action | Inyectar HTML despues de la tabla |
| `admin.plugins_page_scripts` | action | Inyectar JavaScript adicional |

---

## 9. Archivos implicados

### Archivos a crear

| Archivo | Proposito |
|---|---|
| `admin/api/plugins.php` | Endpoint AJAX para todas las operaciones |
| `admin/assets/js/klytos-plugins.js` | Logica JavaScript de la pagina |

### Archivos a modificar

| Archivo | Cambios |
|---|---|
| `admin/plugins.php` | Redisenar HTML: checkboxes, bulk bar, botones AJAX, modal, cargar JS |

---

## 10. Orden de implementacion

1. **Endpoint AJAX** (`admin/api/plugins.php`) — activate, deactivate, delete, uninstall
2. **HTML de plugins.php** — checkboxes, bulk bar, data attributes, botones con data-action
3. **JavaScript** (`klytos-plugins.js`) — acciones individuales con AJAX + feedback
4. **CSS** — spinner, toast, animaciones
5. **Acciones en lote** — select all, bulk apply
6. **Modal de eliminacion** — confirmacion con opcion de borrar datos
7. **Check updates** — comprobar actualizaciones desde el marketplace
8. **Badge legacy** — mostrar aviso para plugins con discovery_method json_legacy
9. **Hooks** — anadir los filtros y acciones para extensibilidad

---

## 11. Referencia visual

La pagina debe seguir el mismo patron visual que el resto del admin de Klytos:
- Variables CSS: `--admin-primary`, `--admin-surface`, `--admin-border`, etc.
- Botones: `.btn`, `.btn-primary`, `.btn-outline`, `.btn-danger`, `.btn-sm`
- Badges: `.badge-status`, `.badge-active`, `.badge-inactive`
- Dark mode: soporte completo via `[data-theme="dark"]`
- Responsive: funcional en movil (tabla con scroll horizontal)

El modelo a seguir es la pagina de plugins de WordPress (`wp-admin/plugins.php`) pero adaptada al diseno de Klytos.
