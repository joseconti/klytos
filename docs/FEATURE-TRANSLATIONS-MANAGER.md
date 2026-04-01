# Feature: Translations Manager

> Especificacion para implementar el gestor de traducciones del admin de Klytos.
> Documento destinado a Claude Code para su implementacion.

---

## 1. Resumen

Crear una nueva seccion **Translations** en el apartado **System** del panel de administracion. Esta pagina permite al usuario:

1. Ver todas las claves de traduccion del core, plugins y templates.
2. Comparar el texto en ingles (referencia) con la traduccion en cada idioma configurado.
3. Traducir manualmente campos vacios o corregir traducciones existentes.
4. Traducir automaticamente usando la IA configurada (boton "Traducir con...").
5. Filtrar por origen (core, nombre de plugin, nombre de template).

Los idiomas disponibles en la pagina de traducciones se obtienen de la configuracion `languages` definida en Settings (array de objetos `{code, name}`). El ingles (`en`) siempre se muestra como columna de referencia y no es editable desde esta pantalla.

---

## 2. Arquitectura General

### 2.1 Archivos Nuevos

| Archivo | Tipo | Descripcion |
|---------|------|-------------|
| `installer/admin/translations.php` | Admin page | Pagina principal del gestor de traducciones |
| `installer/admin/api/translations.php` | API endpoint | Guardado AJAX de traducciones individuales |
| `installer/admin/api/translations-ai.php` | API endpoint | Traduccion via IA (recibe texto en ingles, devuelve traduccion) |
| `installer/core/mcp/tools/translation-tools.php` | MCP tools | Tools MCP para traduccion via Asistente |
| `installer/core/translation-manager.php` | Core class | Clase `TranslationManager` que abstrae lectura/escritura de archivos de traduccion |

### 2.2 Archivos a Modificar

| Archivo | Cambio |
|---------|--------|
| `installer/admin/templates/sidebar.php` | Anadir item "Translations" en la seccion system |
| `installer/core/lang/en.json` | Anadir claves `translations.*` para la UI de la pagina |
| `installer/core/lang/es.json` | Anadir traducciones `translations.*` al espanol |
| `installer/core/mcp/tools/` (algun archivo de registro) | Registrar los nuevos translation tools |
| `installer/core/i18n.php` | Anadir metodo para obtener todas las claves aplanadas (flat keys) |
| `.claude/skills/klytos-plugin-development.md` | Documentar la OBLIGATORIEDAD del archivo `en.json` |
| `.claude/skills/klytos-core-development.md` | Documentar la regla de `en.json` obligatorio |

---

## 3. Modelo de Datos

### 3.1 Estructura de Archivos de Traduccion

Los archivos de traduccion son JSON con estructura anidada y metadatos:

```json
{
  "_meta": {
    "locale": "en",
    "name": "English",
    "flag": "GB",
    "author": "Jose Conti",
    "version": "1.0.0"
  },
  "namespace": {
    "key": "Value"
  }
}
```

Las claves se acceden con dot-notation: `namespace.key` resuelve a `"Value"`.

### 3.2 Ubicacion de Archivos

| Origen | Ruta | Ejemplo |
|--------|------|---------|
| Core | `installer/core/lang/{locale}.json` | `installer/core/lang/en.json` |
| Plugin | `installer/plugins/{plugin-id}/lang/{locale}.json` | `installer/plugins/hello-ai/lang/en.json` |
| Template | `installer/templates/{template-id}/lang/{locale}.json` | (futuro, mismo patron) |

### 3.3 Idiomas Disponibles

Se leen de la configuracion del sitio:

```php
$siteConfig = $app->getSiteConfig()->getAll();
$languages = $siteConfig['languages'] ?? [];
// Resultado: [["code" => "es", "name" => "Espanol"], ["code" => "en", "name" => "English"], ...]
```

El ingles (`en`) SIEMPRE se muestra como columna de referencia aunque no este en la lista de idiomas. Los demas idiomas de la lista son los que se pueden traducir.

---

## 4. Clase TranslationManager

**Archivo**: `installer/core/translation-manager.php`
**Namespace**: `Klytos\Core`

### 4.1 Responsabilidades

- Descubrir todos los origenes de traduccion (core + plugins activos + templates).
- Cargar y aplanar las claves de un archivo JSON a dot-notation.
- Comparar claves entre el archivo `en.json` (referencia) y otros idiomas.
- Guardar traducciones individuales o en bloque.
- Listar claves sin traducir por idioma.

### 4.2 Interfaz Publica

```php
class TranslationManager
{
    public function __construct(App $app) {}

    /**
     * Devuelve todos los origenes de traduccion descubiertos.
     * @return array [['id' => 'core', 'type' => 'core', 'name' => 'Klytos Core', 'path' => '...'], ...]
     */
    public function getSources(): array {}

    /**
     * Obtiene todas las claves aplanadas (flat) del archivo en.json de un origen.
     * @param string $sourceId  'core', 'hello-ai', 'theme-starter', etc.
     * @return array ['common.save' => 'Save', 'common.cancel' => 'Cancel', ...]
     */
    public function getReferenceKeys(string $sourceId): array {}

    /**
     * Obtiene las traducciones existentes para un idioma y origen.
     * @param string $sourceId
     * @param string $locale
     * @return array ['common.save' => 'Guardar', ...] (solo claves con valor)
     */
    public function getTranslations(string $sourceId, string $locale): array {}

    /**
     * Devuelve las claves que faltan por traducir en un idioma.
     * @param string $sourceId
     * @param string $locale
     * @return array ['common.new_key' => 'New key value in English', ...]
     */
    public function getMissingKeys(string $sourceId, string $locale): array {}

    /**
     * Guarda una traduccion individual.
     * @param string $sourceId
     * @param string $locale
     * @param string $key     Dot-notation key (e.g. 'common.save')
     * @param string $value   Translated string
     */
    public function saveTranslation(string $sourceId, string $locale, string $key, string $value): void {}

    /**
     * Guarda multiples traducciones a la vez.
     * @param string $sourceId
     * @param string $locale
     * @param array  $translations ['key' => 'value', ...]
     */
    public function saveBulkTranslations(string $sourceId, string $locale, array $translations): void {}

    /**
     * Estadisticas de traduccion por origen e idioma.
     * @return array ['core' => ['es' => ['total' => 557, 'translated' => 540, 'missing' => 17], ...], ...]
     */
    public function getStats(): array {}
}
```

### 4.3 Metodo Auxiliar en I18n

Anadir a `installer/core/i18n.php` un metodo estatico para aplanar JSON anidado:

```php
/**
 * Flatten a nested array to dot-notation keys.
 * Ignora la clave '_meta'.
 *
 * @param  array  $data
 * @param  string $prefix
 * @return array  ['common.save' => 'Save', ...]
 */
public static function flattenKeys(array $data, string $prefix = ''): array
{
    $result = [];
    foreach ($data as $key => $value) {
        if ($key === '_meta') continue;
        $fullKey = $prefix ? $prefix . '.' . $key : $key;
        if (is_array($value)) {
            $result = array_merge($result, self::flattenKeys($value, $fullKey));
        } else {
            $result[$fullKey] = $value;
        }
    }
    return $result;
}
```

### 4.4 Escritura de Archivos JSON

Al guardar traducciones, el `TranslationManager` debe:

1. Cargar el JSON completo del archivo destino (o crear uno nuevo con `_meta`).
2. Expandir la clave dot-notation al arbol anidado.
3. Escribir el JSON con `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.
4. Mantener el bloque `_meta` intacto.

Si el archivo de idioma no existe, crearlo con la estructura `_meta` correcta:

```json
{
  "_meta": {
    "locale": "fr",
    "name": "Francais",
    "flag": "FR",
    "version": "1.0.0"
  }
}
```

El nombre y flag se pueden obtener del array `languages` de settings o de un mapa interno de locales comunes.

---

## 5. Pagina Admin: translations.php

### 5.1 Sidebar Entry

Anadir en `installer/admin/templates/sidebar.php`, seccion system, posicion **82** (despues de system-options en 81, antes de post-types en 85):

```php
[
    'id'         => 'translations',
    'title'      => __( 'translations.title' ),
    'url'        => $adminPath . 'translations.php',
    'icon'       => 'fa-solid fa-language',
    'position'   => 82,
    'section'    => 'system',
    'capability' => 'site.configure',
],
```

### 5.2 Layout de la Pagina

La pagina tiene dos zonas:

**A) Barra superior de filtros:**

- **Dropdown "Source"**: filtrar por `Core`, o nombre de cada plugin/template activo. Valor por defecto: `Core`.
- **Dropdown "Language"**: lista de idiomas configurados en Settings (excepto `en`). Si solo hay un idioma, se selecciona automaticamente.
- **Campo de busqueda**: filtra claves en tiempo real (busca en la clave, texto ingles y traduccion).
- **Indicador de progreso**: `340 / 557 translated (61%)` con barra de progreso visual.
- **Toggle "Show only missing"**: filtra para mostrar solo las claves sin traducir.

**B) Tabla de traducciones:**

Cada fila contiene:

| Columna | Contenido |
|---------|-----------|
| Key | La clave en dot-notation, en fuente monoespaciada, tamano pequeno. Ejemplo: `hello_ai.response_5` |
| English | El texto de referencia del `en.json`. Solo lectura, fondo ligeramente gris. |
| Traduccion {Idioma} | Campo de texto editable (`<textarea>` auto-expandible). Si esta vacio, placeholder con el texto en ingles en gris. |
| Acciones | Boton "Traducir con..." (dropdown con las IAs configuradas) + boton "Guardar" (icono check) |

### 5.3 Diseno Visual

Seguir el estilo del admin de Klytos (variables CSS existentes):

- Usar las variables `--admin-bg`, `--admin-card-bg`, `--admin-text`, `--admin-text-muted`, `--admin-border`, `--admin-accent`.
- Cards con `border-radius: var(--admin-radius)` y sombras sutiles.
- La tabla debe ser responsive: en movil, las columnas English y Traduccion se apilan verticalmente.
- El campo de traduccion debe tener borde `var(--admin-border)` normal y `var(--admin-accent)` al hacer focus.
- Cuando se guarda correctamente, flash verde breve en la fila.
- Textarea con auto-resize segun contenido.

### 5.4 Guardado AJAX

Al hacer clic en "Guardar" (o al hacer blur del campo si ha cambiado), enviar via `fetch()`:

```
POST installer/admin/api/translations.php
Content-Type: application/json
X-CSRF-Token: {token}

{
    "source": "core",
    "locale": "es",
    "key": "common.save",
    "value": "Guardar"
}
```

Respuesta exitosa:
```json
{"success": true}
```

### 5.5 Traduccion con IA

El boton "Traducir con..." abre un dropdown con los proveedores de IA que tengan API key configurada (usar `AiKeyManager::listProviders()` y filtrar por `configured === true`).

Al seleccionar un proveedor:

```
POST installer/admin/api/translations-ai.php
Content-Type: application/json
X-CSRF-Token: {token}

{
    "provider": "anthropic",
    "source_text": "Hello, human! I live in plugins/hello-ai/hello-ai.php and that's all Klytos needs to find me.",
    "source_locale": "en",
    "target_locale": "es",
    "context": "CMS admin panel translation - key: hello_ai.response_5"
}
```

Respuesta:
```json
{
    "success": true,
    "translation": "Hola, humano! Vivo en plugins/hello-ai/hello-ai.php y eso es todo lo que Klytos necesita para encontrarme."
}
```

El endpoint `translations-ai.php` debe:

1. Obtener la API key del proveedor via `AiKeyManager`.
2. Construir un prompt del tipo:

```
Translate the following text from {source_locale} to {target_locale}.
Context: This is a UI string for a CMS admin panel.
Keep HTML tags intact if present. Do not add explanations.
Only return the translated text.

Text: {source_text}
```

3. Llamar a la API del proveedor usando el `ChatEngine` existente o directamente con cURL.
4. Devolver solo el texto traducido.

Tambien debe haber un boton "Traducir todo lo que falta" que traduzca en bloque todas las claves sin traducir del origen/idioma seleccionado. Este proceso se ejecuta secuencialmente (o en batches de 10) con indicador de progreso visual.

---

## 6. MCP Tools para Traduccion

**Archivo**: `installer/core/mcp/tools/translation-tools.php`

Registrar las siguientes tools para que el Asistente (IA via MCP) pueda gestionar traducciones:

### 6.1 klytos_list_translation_sources

```
Nombre: klytos_list_translation_sources
Descripcion: List all translation sources (core, active plugins, templates) with translation statistics per language.
Parametros: ninguno
Respuesta: { sources: [...], languages: [...] }
Annotations: readOnlyHint: true
```

### 6.2 klytos_get_translations

```
Nombre: klytos_get_translations
Descripcion: Get all translation keys for a source, comparing English reference with a target locale. Shows which keys are missing translations.
Parametros:
  - source (string, required): Source ID ('core', plugin-id, template-id)
  - locale (string, required): Target locale code (e.g. 'es', 'fr')
  - only_missing (boolean, optional): If true, only return keys without translation
Respuesta: { source, locale, total, translated, missing, keys: { "key": { "en": "...", "translation": "..." | null }, ... } }
Annotations: readOnlyHint: true
```

### 6.3 klytos_translate

```
Nombre: klytos_translate
Descripcion: Save one or more translations for a source and locale. The AI assistant should translate from the English reference text. IMPORTANT: Always maintain HTML tags intact. Do not translate placeholder variables like {variable}.
Parametros:
  - source (string, required): Source ID
  - locale (string, required): Target locale code
  - translations (object, required): Map of dot-notation key to translated string. Example: {"common.save": "Guardar", "common.cancel": "Cancelar"}
Respuesta: { success: true, saved: 15 }
Annotations: readOnlyHint: false, destructiveHint: false, idempotentHint: true
```

### 6.4 klytos_translate_with_ai

```
Nombre: klytos_translate_with_ai
Descripcion: Use a configured AI provider to automatically translate missing keys for a source and locale. Requires an AI provider with API key configured in Settings > AI Keys.
Parametros:
  - source (string, required): Source ID
  - locale (string, required): Target locale code
  - provider (string, optional): AI provider ID. If omitted, uses the active provider.
  - keys (array of strings, optional): Specific keys to translate. If omitted, translates all missing keys.
Respuesta: { success: true, translated: 17, provider: "anthropic", translations: { "key": "translated value", ... } }
Annotations: readOnlyHint: false, destructiveHint: false
```

**Nota sobre klytos_translate**: Esta tool es la que se invoca cuando el usuario le dice al Asistente "traduce las cadenas que faltan al espanol". El Asistente debe:

1. Llamar a `klytos_get_translations` con `only_missing: true` para obtener las claves sin traducir.
2. Traducir el texto en ingles al idioma destino (el propio Asistente es capaz de traducir).
3. Llamar a `klytos_translate` con el mapa de traducciones.

La tool `klytos_translate_with_ai` es para traduccion desde el admin usando una API de IA externa, NO es para el Asistente MCP (que ya puede traducir por si mismo).

---

## 7. Claves de Traduccion para la UI

Anadir a `installer/core/lang/en.json`:

```json
"translations": {
    "title": "Translations",
    "source": "Source",
    "language": "Language",
    "key": "Key",
    "english": "English",
    "translation": "Translation",
    "search_placeholder": "Search keys...",
    "show_missing_only": "Show only missing",
    "progress": "{translated} / {total} translated ({percent}%)",
    "save_success": "Translation saved",
    "save_error": "Error saving translation",
    "translate_with": "Translate with...",
    "translate_all_missing": "Translate all missing",
    "translating": "Translating...",
    "no_providers": "No AI providers configured. Go to Settings to add API keys.",
    "no_languages": "No languages configured. Go to Settings > Languages to add languages.",
    "all_translated": "All keys are translated for this source and language.",
    "confirm_translate_all": "Translate all {count} missing keys with {provider}?",
    "translated_count": "{count} keys translated successfully",
    "core": "Klytos Core",
    "plugin": "Plugin",
    "template": "Template",
    "filter_all": "All sources"
}
```

Y las correspondientes traducciones en `installer/core/lang/es.json`.

---

## 8. Seguridad

- **CSRF**: Todos los endpoints POST (`api/translations.php`, `api/translations-ai.php`) deben verificar el token CSRF.
- **Capability**: Requiere `site.configure` para acceder a la pagina y a los endpoints.
- **Sanitizacion**: Las claves de traduccion solo pueden contener `[a-z0-9_.]`. Los valores se sanitizan con `htmlspecialchars()` al guardar para prevenir XSS almacenado, EXCEPTO cuando el valor original en ingles contiene HTML (tags como `<strong>`, `<em>`, `<a>`) — en ese caso se permiten esos mismos tags.
- **Validacion de locale**: El locale debe coincidir con uno de los idiomas configurados en Settings.
- **Validacion de source**: El source debe ser `core` o el ID de un plugin/template activo.
- **Escritura de archivos**: Solo se escriben archivos `.json` dentro de los directorios `lang/` de core/plugins/templates. Nunca se permite escritura fuera de esas rutas. Validar que no haya `..` ni caracteres peligrosos en la ruta.
- **CSP**: Todos los `<script>` usan nonce. Cero event handlers inline.

---

## 9. Registro de MCP Tools

En el archivo donde se registran los tools MCP (probablemente en el flujo de `registerAllTools` o similar), anadir:

```php
require_once __DIR__ . '/tools/translation-tools.php';
\Klytos\Core\MCP\Tools\registerTranslationTools($registry);
```

---

## 10. Regla Obligatoria: en.json en Plugins y Templates

### 10.1 Regla

**Todo plugin y template que use traducciones DEBE incluir un archivo `lang/en.json`** con todas las claves que utiliza. Este archivo es la referencia maestra para las traducciones. Sin el, el Translation Manager no puede descubrir que claves necesitan traduccion.

### 10.2 Actualizacion de Skills

Anadir la siguiente regla en `.claude/skills/klytos-plugin-development.md`, en la seccion de traducciones:

```markdown
## REGLA OBLIGATORIA: Archivo en.json

Cuando un plugin usa traducciones (funcion `__()`), es **OBLIGATORIO** crear el archivo
`lang/en.json` con TODAS las claves de traduccion en ingles. Este archivo sirve como
referencia maestra para el Translation Manager del admin.

Sin `en.json`, las claves del plugin no apareceran en System > Translations y no se
podran traducir desde el admin ni via MCP.

Estructura minima del archivo `lang/en.json`:

```json
{
    "plugin_id.key_name": "English text",
    "plugin_id.another_key": "Another English text"
}
```

Opcionalmente, se pueden incluir otros idiomas (`es.json`, `fr.json`, etc.) para
distribuir traducciones de fabrica con el plugin.
```

Anadir regla similar en `.claude/skills/klytos-core-development.md`:

```markdown
## REGLA OBLIGATORIA: Traducciones

Toda nueva clave de traduccion anadida al core DEBE existir en `installer/core/lang/en.json`.
El archivo en.json es la referencia maestra. Si se anade una clave en `es.json` sin anadirla
en `en.json`, el Translation Manager no la descubrira.

Flujo correcto:
1. Anadir la clave en `en.json` (ingles).
2. Anadir la traduccion en `es.json` (y otros idiomas si se desea).
3. Usar la clave en el codigo con `__('namespace.key')`.
```

---

## 11. Modificacion de I18n para Descubrimiento

Anadir al metodo `getAvailableLocales()` de `I18n` (o al nuevo `TranslationManager`) la capacidad de escanear tambien los directorios `lang/` de plugins activos:

```php
// En TranslationManager::getSources()
$sources = [
    ['id' => 'core', 'type' => 'core', 'name' => 'Klytos Core', 'path' => $coreLangDir]
];

// Plugins activos
$plugins = $app->getPluginLoader()->getActivePlugins();
foreach ($plugins as $plugin) {
    $langDir = $app->getPluginLoader()->getPluginPath($plugin['id']) . '/lang';
    if (is_dir($langDir) && file_exists($langDir . '/en.json')) {
        $sources[] = [
            'id'   => $plugin['id'],
            'type' => 'plugin',
            'name' => $plugin['name'] ?? $plugin['id'],
            'path' => $langDir,
        ];
    }
}

// Templates (futuro, mismo patron)
// ...
```

---

## 12. Orden de Implementacion

1. **TranslationManager** (`translation-manager.php`) — La clase core primero.
2. **Metodo `flattenKeys` en I18n** — Necesario para TranslationManager.
3. **Admin page** (`translations.php`) — UI completa con filtros, tabla, guardado AJAX.
4. **API endpoints** (`api/translations.php`, `api/translations-ai.php`).
5. **Sidebar entry** — Anadir el item al menu.
6. **Claves de traduccion** — Anadir `translations.*` a `en.json` y `es.json`.
7. **MCP tools** (`translation-tools.php`) — Las 4 tools descritas.
8. **Actualizacion de Skills** — Documentar la obligatoriedad de `en.json`.
9. **Testing** — Verificar que la pagina carga, el guardado funciona, la traduccion con IA funciona, y las MCP tools responden correctamente.

---

## 13. Notas Adicionales

- **Rendimiento**: Para sitios con muchas claves, paginar la tabla (50 claves por pagina) o usar scroll infinito.
- **Cache**: El `TranslationManager` puede cachear las claves aplanadas en memoria durante la request, pero NO en disco (los archivos JSON son la fuente de verdad).
- **Plugins de formato flat vs anidado**: Los plugins usan formato flat (`"plugin_id.key": "value"`) mientras que el core usa formato anidado. El `flattenKeys()` maneja ambos casos.
- **Placeholder preservation**: Al traducir con IA, el prompt debe instruir a la IA a mantener intactos los placeholders `{variable}` y las etiquetas HTML.
- **No sobreescribir _meta**: Al guardar traducciones, nunca modificar ni eliminar el bloque `_meta` del JSON.
- **Creacion de archivo nuevo**: Si un idioma configurado no tiene archivo JSON en un origen, al guardar la primera traduccion se crea el archivo automaticamente con la estructura `_meta` correcta.
- **Formato JSON**: Siempre escribir con `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` para legibilidad y soporte de caracteres especiales (acentos, enes, etc.).
- **Cabecera de licencia**: Todos los archivos PHP nuevos deben llevar la cabecera ELv2 estandar del proyecto.
- **Coding style**: Espacios tipo WordPress (`__( 'key' )`, `count( $array )`), no usar `<?=`, PHP strict types.
