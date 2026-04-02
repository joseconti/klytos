# Propuesta: SKILL klytos-site-builder

## Que es

Un skill integrado en Klytos CMS que guia al asistente IA (via MCP o Chat AI del admin) a traves de todo el proceso de creacion de un sitio web completo desde cero, justo despues de la instalacion. El skill actua como un "arquitecto web conversacional" que va haciendo preguntas, tomando decisiones y ejecutando acciones hasta dejar el sitio 100% funcional y publicado.

## Filosofia

El skill no es un wizard rigido de pasos fijos. Es un flujo conversacional inteligente que se adapta al tipo de sitio que el usuario necesita. Si alguien dice "quiero un blog personal", el flujo sera muy distinto a "necesito un sitio corporativo con catalogo de productos". El skill sabe cuando preguntar, cuando recomendar, y cuando simplemente ejecutar.

## Donde vive dentro de Klytos

El skill se sirve como una guia interna del CMS a traves del sistema de guias MCP existente (`klytos_get_guide` / `klytos_list_guides`), almacenada en `/core/guides/`. Cualquier asistente IA conectado via MCP o usando el Chat AI del admin puede pedirla y seguirla.

## Estructura de archivos propuesta

```
core/guides/
  site-builder.md              -- Guia principal con el flujo de las 9 fases
  site-builder-types.md        -- Tipos de sitio y sus estructuras recomendadas
  site-builder-palettes.md     -- Paletas de colores por sector/tipo
  site-builder-page-trees.md   -- Arboles de paginas tipicos por tipo de sitio
  site-builder-content.md      -- Plantillas de preguntas para generar contenido
  site-builder-checklist.md    -- Checklist final de verificacion
```

---

## FASES DEL FLUJO

### FASE 1 -- Descubrimiento y planificacion

Objetivo: entender que se va a construir antes de tocar nada.

Preguntas que debe hacer el asistente:

- Tipo de sitio: blog personal, corporativo, portfolio, tienda/catalogo, landing page, documentacion, otro
- Sector/nicho: tecnologia, salud, educacion, comercio, gastronomia, etc. (influye en tono, colores, estructura)
- Idioma principal y si habra multiidioma (configurar lista de idiomas)
- Publico objetivo (condiciona tono, complejidad, nivel de accesibilidad)
- Si tienen contenido existente para importar (activa el plugin klytos-importer)
- Si tienen marca existente (logo, colores corporativos, tipografias)
- Cuantas paginas estiman necesitar aproximadamente
- Si necesitan formularios de contacto u otros
- Si necesitan blog/noticias ademas de paginas estaticas
- Si necesitan custom post types (servicios, productos, equipo, testimonios, proyectos, etc.)

Al final de esta fase, el asistente genera un **"plan del sitio"** en texto (resumen de lo que se va a crear) y pide confirmacion antes de continuar. Nada se ejecuta sin aprobacion.


### FASE 2 -- Referencia de diseno

Objetivo: obtener una referencia visual concreta para guiar todas las decisiones de diseno.

El asistente pregunta:

1. "Tienes algun sitio web cuyo diseno te guste o que quieras usar como referencia?"
   - **Si proporciona una URL**: el asistente visita el sitio (via herramientas de navegacion si las tiene), analiza la estructura visual, paleta de colores, tipografia, layout, estilo general
   - **Si no puede visitar URLs**: pide capturas de pantalla al usuario para analizarlas visualmente
   - **Si proporciona varias referencias**: el asistente identifica patrones comunes (ej: "veo que todos usan mucho espacio en blanco, tipografia sans-serif grande y colores neutros con acento azul")

2. "Hay algo especifico de ese diseno que te gusta? Colores, disposicion, tipografia, estilo de imagenes..."

3. El asistente debe determinar si tiene capacidad de visitar URLs (herramientas de navegacion). Si la tiene, visita el sitio y analiza su diseno. Si no, pide capturas. En ambos casos, extrae:
   - Paleta de colores dominante
   - Tipo de tipografia (serif, sans-serif, monospace)
   - Densidad del layout (aireado vs compacto)
   - Estilo de imagenes (fotografias, ilustraciones, iconos)
   - Tono general (minimalista, corporativo, creativo, elegante, juvenil, etc.)

Resultado: un **"brief de diseno"** interno que guia todas las decisiones visuales posteriores.


### FASE 3 -- Configuracion global del sitio

Objetivo: configurar todo lo que toca `klytos_set_site_config`.

**Identidad del sitio:**
- `site_name` -- nombre del sitio
- `tagline` -- subtitulo o lema
- `description` -- meta descripcion global para SEO
- `default_language` -- idioma por defecto (es, en, ca, fr, etc.)
- `languages` -- lista de idiomas adicionales si es multiidioma

**Imagenes de marca:**
- `favicon_url` -- si tienen favicon, subirlo; si no, anotar para generar o anadir despues
- `logo_url` -- misma logica que favicon
- `seo.default_og_image` -- imagen por defecto para compartir en redes sociales

**Redes sociales:**
- `social.twitter` -- URL de perfil
- `social.github` -- URL de perfil
- `social.linkedin` -- URL de perfil
- `social.instagram` -- URL de perfil
- `social.youtube` -- URL de canal
- `social.mastodon` -- URL de perfil
- Preguntar solo cuales usan, no forzar todas

**SEO global:**
- `seo.robots_txt_extra` -- reglas adicionales de robots.txt (normalmente vacio al inicio)
- `indexing_enabled` -- DEJAR DESACTIVADO durante la construccion, activar en Fase 9

**Analytics:**
- Explicar que Klytos tiene analytics propios privacy-first que no requieren configuracion
- `analytics.google_analytics_id` -- preguntar si usan Google Analytics
- `analytics.custom_head_scripts` -- scripts de terceros en head (ej: tag managers)
- `analytics.custom_body_scripts` -- scripts de terceros antes de cierre body

**Preferencias del admin:**
- `editor` -- preguntar si prefieren Gutenberg (bloques) o TinyMCE (clasico). Recomendar Gutenberg
- `admin_theme` -- light o dark para el panel de administracion

**MCP tools utilizadas:** `klytos_set_site_config`


### FASE 4 -- Tema y diseno visual

Objetivo: configurar toda la apariencia visual basandose en el brief de diseno de Fase 2.

**Colores (11 parametros via `klytos_set_colors`):**
- `primary` -- color principal de marca
- `secondary` -- color secundario
- `accent` -- color de acento/destacado
- `background` -- fondo de pagina
- `surface` -- fondo de componentes/tarjetas
- `text` -- color de texto principal
- `text_muted` -- color de texto secundario
- `border` -- color de bordes
- `success` -- color de estado exitoso (normalmente verde)
- `warning` -- color de advertencia (normalmente amarillo/naranja)
- `error` -- color de error (normalmente rojo)

El asistente propone 2-3 paletas de colores basadas en:
- Las referencias de diseno de Fase 2
- Los colores corporativos si los proporcionaron
- El sector/tipo de sitio (consultar `site-builder-palettes.md`)

Los colores de background, surface, text, text_muted y border se pueden calcular automaticamente a partir de los principales.

**Tipografia (8 parametros via `klytos_set_fonts`):**
- `fonts.heading` -- fuente para titulos (ej: Inter, Playfair Display, Montserrat)
- `fonts.body` -- fuente para texto de cuerpo
- `fonts.code` -- fuente monospace para codigo
- `fonts.heading_weight` -- peso de titulos (400-900)
- `fonts.body_weight` -- peso de cuerpo (300-500 normalmente)
- `fonts.base_size` -- tamano base (16px por defecto)
- `fonts.scale_ratio` -- ratio de escala tipografica (1.25 por defecto)
- `fonts.google_fonts_url` -- URL de Google Fonts si se usan fuentes externas

El asistente recomienda combinaciones segun tipo de sitio:
- Corporativo: Inter + Inter, Roboto + Roboto Slab
- Creativo: Playfair Display + Source Sans Pro
- Tech: JetBrains Mono + Inter
- Elegante: Cormorant Garamond + Montserrat

**Layout (7 parametros via `klytos_set_layout`):**
- `layout.max_width` -- ancho maximo del contenido (1200px por defecto)
- `layout.header_style` -- sticky (fijo al hacer scroll), static, o absolute
- `layout.footer_enabled` -- mostrar footer (true/false)
- `layout.sidebar_enabled` -- mostrar sidebar (true/false)
- `layout.sidebar_position` -- izquierda o derecha
- `layout.border_radius` -- estilo de bordes redondeados (0px a 16px)
- `layout.spacing_unit` -- unidad base de espaciado (1rem por defecto)

**CSS personalizado:**
- `custom_css` -- si el usuario tiene CSS propio que quiera aplicar

**MCP tools utilizadas:** `klytos_set_theme`, `klytos_set_colors`, `klytos_set_fonts`, `klytos_set_layout`


### FASE 5 -- Estructura de contenido

Objetivo: definir la arquitectura de informacion completa del sitio.

**Paginas principales:**
- El asistente propone un arbol de paginas basado en el tipo de sitio (consultar `site-builder-page-trees.md`):
  - Blog personal: Inicio, Sobre mi, Blog, Contacto
  - Corporativo: Inicio, Sobre nosotros, Servicios, Blog/Noticias, Contacto
  - Portfolio: Inicio, Proyectos, Sobre mi, Contacto
  - Catalogo: Inicio, Productos, Categorias, Sobre nosotros, Contacto
  - Landing: pagina unica con secciones
  - Documentacion: Inicio, Guias (por categorias), FAQ, Contacto
- El usuario confirma, modifica, anade o quita paginas
- Crear cada pagina via `klytos_create_page` con slug, titulo, plantilla asignada, idioma, estado draft

**Custom Post Types (si aplica):**
- El asistente propone los que encajen segun el tipo de sitio:
  - Servicios (corporativo)
  - Proyectos/Trabajos (portfolio)
  - Productos (catalogo)
  - Testimonios (cualquiera)
  - Equipo/Miembros (corporativo)
  - FAQ/Preguntas frecuentes (cualquiera)
- Crear via `klytos_create_post_type` con id, nombre, slug, slug_i18n
- Para cada post type, definir campos personalizados via `klytos_add_custom_field`
  - 27 tipos de campo disponibles: text, textarea, richtext, number, date, select, image, file, gallery, repeater, relationship, etc.
- Crear taxonomias via `klytos_add_taxonomy` (categorias jerarquicas o etiquetas planas)
- Crear terminos iniciales via `klytos_add_term`

**Homepage:**
- Definir cual sera la pagina de inicio

**MCP tools utilizadas:** `klytos_create_page`, `klytos_create_post_type`, `klytos_add_custom_field`, `klytos_add_taxonomy`, `klytos_add_term`, `klytos_set_site_config`


### FASE 6 -- Plantillas y bloques

Objetivo: crear las plantillas HTML y bloques reutilizables necesarios.

**Guias a consultar previamente:**
- `klytos_get_guide('page-structure')`
- `klytos_get_guide('gutenberg-blocks')`

**Plantillas:**
- Evaluar si las 4 built-in (default, landing, blog-post, blank) cubren las necesidades
- Si no, crear custom templates via `klytos_set_custom_template`
- Configurar template parts (header, footer) via `klytos_set_custom_template_part` con la navegacion y branding correctos
- Crear plantillas especificas para custom post types si es necesario

**Bloques reutilizables:**
- Identificar elementos que se repiten en varias paginas:
  - CTAs (llamadas a la accion)
  - Banners
  - Secciones de testimonios
  - Tarjetas de servicios/productos
  - Formularios embebidos
  - Secciones de equipo
- Crear via `klytos_create_block` con HTML, CSS, JS y slots configurables
- Definir scope: global (todas las paginas), template (tipo especifico), o pagina concreta
- Configurar datos globales via `klytos_set_global_block_data`

**Page templates (combinaciones de bloques):**
- Crear combinaciones predefinidas via `klytos_create_page_template`
- Definir orden de bloques via `klytos_reorder_template_blocks`
- Aprobar templates via `klytos_approve_page_template`

**MCP tools utilizadas:** `klytos_set_custom_template`, `klytos_set_custom_template_part`, `klytos_create_block`, `klytos_set_global_block_data`, `klytos_create_page_template`, `klytos_reorder_template_blocks`, `klytos_approve_page_template`, `klytos_rebuild_plugin_assets`


### FASE 7 -- Contenido

Objetivo: generar el contenido real de cada pagina y configurar la navegacion.

**Guias a consultar previamente:**
- `klytos_get_guide('gutenberg-blocks')` -- para crear contenido con bloques correctos
- `klytos_get_guide('seo-content')` -- para SEO de cada pagina
- `klytos_get_guide('accessibility')` -- para accesibilidad

**Fuentes de contenido (preguntar al usuario para cada pagina):**

1. **Textos propios del usuario** -- puede subir archivos en cualquier formato soportado:
   - txt, docx, pdf, md, html, csv, u otros
   - El asistente los lee, extrae el contenido relevante y lo adapta a bloques Gutenberg

2. **URLs de documentos externos** -- enlaces directos desde Dropbox, Google Drive, u otros servicios:
   - El enlace debe apuntar al archivo individual, NO al directorio
   - El asistente descarga el archivo e importa el contenido
   - Sirve tanto para textos como para imagenes

3. **Dictado al asistente** -- el usuario describe lo que quiere y el asistente redacta el contenido

4. **Generacion completa con IA** -- el asistente genera contenido basandose en el brief del sitio

**Para cada pagina, el asistente:**
- Pregunta cual fuente de contenido prefiere el usuario
- Genera/adapta el contenido con bloques Gutenberg correctos
- Configura SEO: title, `meta_description`, `og_image`
- Asigna la plantilla correcta
- Configura hreflang si es multiidioma

**Imagenes (5 vias disponibles):**

1. **Subida directa** -- el usuario sube archivos de imagen al asistente o al CMS
2. **Generacion con IA** -- via Gemini/Nano Banana si hay API key configurada. Ofrecer generar: hero images, cabeceras de seccion, iconos ilustrativos, fondos
3. **URL externa** -- enlaces directos desde Dropbox, Google Drive, etc. (archivo individual, no directorio). El asistente descarga e importa via asset tools
4. **Capturas de pantalla** -- como referencia visual, no necesariamente para usar en el sitio
5. **Placeholder** -- marcar posiciones de imagen para completar mas adelante

Si hay API key de Gemini configurada, el asistente debe ofrecer activamente la generacion de imagenes. Si no la hay, mencionar que pueden configurarla despues.

**Custom Post Types:**
- Crear items de ejemplo con `klytos_create_page` (parametro post_type)
- Rellenar campos personalizados via `klytos_set_field_value` o `klytos_set_bulk_field_values`
- Asignar terminos de taxonomia

**Navegacion:**
- Crear menu principal via `klytos_set_menu` con todas las paginas
- Anadir items individuales via `klytos_add_menu_item` con jerarquia si procede
- Configurar enlaces externos si son necesarios

**MCP tools utilizadas:** `klytos_update_page`, `klytos_create_page`, `klytos_set_field_value`, `klytos_set_bulk_field_values`, `klytos_set_menu`, `klytos_add_menu_item`, `klytos_generate_ai_image`


### FASE 8 -- Funcionalidades adicionales

Objetivo: configurar todos los subsistemas que no son contenido puro.

**Formularios:**
- Si necesitan formulario de contacto u otros, activar klytos-forms (`klytos_activate_plugin`)
- Crear formularios con los campos necesarios (via tools del plugin)
- Configurar notificaciones por email del formulario

**Consentimiento / GDPR (via `klytos_set_consent_config`):**
- `enabled` -- activar el banner de cookies
- `banner_text` -- texto del banner (adaptar al idioma del sitio)
- `privacy_url` -- enlace a la pagina de politica de privacidad (crearla si no existe)
- `cookie_days` -- duracion del consentimiento (365 dias por defecto)
- `categories` -- categorias de cookies: necessary, functional, analytics, marketing
- Crear declaraciones de consentimiento via `klytos_add_consent_declaration` para cada servicio externo (Google Analytics, redes sociales embebidas, etc.)

**Email / SMTP (via site config, bloque `email`):**
- `email.transport` -- preguntar si quieren configurar SMTP o usar mail() de PHP
- `email.from_name` -- nombre del remitente
- `email.from_email` -- email del remitente
- `email.reply_to` -- email de respuesta
- Si SMTP:
  - `email.smtp_host` -- servidor SMTP
  - `email.smtp_port` -- puerto (587 STARTTLS, 465 SSL, 25 plain)
  - `email.smtp_user` -- usuario
  - `email.smtp_pass` -- contrasena
  - `email.smtp_security` -- tls, ssl, o ninguno

**IA / Providers (via AI tools):**
- Preguntar si quieren configurar proveedores de IA:
  - Anthropic (Claude)
  - OpenAI (GPT)
  - Google Gemini
  - OpenRouter
- Configurar API keys para los proveedores elegidos
- Seleccionar modelo por defecto
- Si configuran Gemini, activar la generacion de imagenes con IA

**Usuarios:**
- Si necesitan mas usuarios ademas del admin, crearlos via `klytos_create_user`
- Asignar roles: admin, editor, viewer
- Recomendar configurar 2FA para cada usuario

**Plugins:**
- Revisar plugins bundled y recomendar cuales activar:
  - klytos-forms -- si hay formularios
  - klytos-importer -- si hay contenido que migrar
  - hello-ai -- solo como demo, no necesario en produccion

**Webhooks (si aplica):**
- Preguntar si necesitan notificaciones a servicios externos
- Crear via `klytos_create_webhook` con URL y eventos
- Testear via `klytos_test_webhook`
- Eventos disponibles: page.created, page.updated, page.deleted, build.completed, build.failed, task.created, task.completed, user.created, user.login, plugin.activated, plugin.deactivated

**Acciones programadas (si aplica):**
- Preguntar si necesitan tareas recurrentes (limpieza, backups, sincronizaciones)
- Crear via `klytos_schedule_recurring_action` o `klytos_schedule_single_action`

**Cache:**
- Explicar opciones disponibles: auto (recomendado), apcu, redis, memcached, file, none
- Recomendar "auto" para la mayoria de los casos (auto-detecta el mejor driver disponible)
- Si el hosting soporta Redis o Memcached, configurar los parametros de conexion

**Modo desarrollador:**
- Preguntar si quieren activar DevBar (`developer.developer_mode`)
- Si activan: configurar que paneles mostrar (performance, queries, hooks, assets, request, environment)
- Si no activan: dejarlo desactivado (recomendado para produccion)

**MCP tools utilizadas:** `klytos_activate_plugin`, `klytos_set_consent_config`, `klytos_add_consent_declaration`, `klytos_create_user`, `klytos_create_webhook`, `klytos_test_webhook`, `klytos_schedule_recurring_action`, `klytos_schedule_single_action`


### FASE 9 -- Build, verificacion y lanzamiento

Objetivo: publicar el sitio y verificar que todo funciona.

**Build:**
- Ejecutar `klytos_build_site` para generar el HTML estatico completo
- Ejecutar `klytos_rebuild_css` para asegurar estilos actualizados
- Reconstruir assets de plugins via `klytos_rebuild_plugin_assets`

**Verificacion:**
- Ejecutar integrity check via `klytos_run_integrity_check`
- Verificar que todas las paginas se generaron correctamente
- Verificar que el menu de navegacion funciona
- Verificar que las plantillas se renderizan bien
- Verificar que los custom post types muestran sus campos
- Verificar que los formularios funcionan (si hay)
- Verificar que el banner de cookies aparece (si esta activado)

**Activar indexacion:**
- Activar `indexing_enabled: true` via `klytos_set_site_config`
- Rebuild final para generar robots.txt y sitemap.xml correctos
- Ejecutar `klytos_build_site` una ultima vez

**Resumen final para el usuario:**

Todo lo creado:
- Numero de paginas y sus titulos
- Custom post types y sus campos
- Taxonomias y terminos
- Plantillas creadas
- Bloques reutilizables
- Menus de navegacion

Todo lo configurado:
- Tema (colores, fuentes, layout)
- SEO global
- Redes sociales
- GDPR / consent
- Email / SMTP
- Analytics
- Plugins activos
- Cache
- IA providers

**Proximos pasos recomendados:**
- Anadir mas contenido a las paginas
- Crear mas items en los custom post types
- Configurar 2FA si no lo hicieron en el setup wizard
- Planificar backups regulares
- Revisar rendimiento con DevBar si procede
- Considerar webhooks para integraciones
- Actualizar Klytos cuando haya nuevas versiones

**MCP tools utilizadas:** `klytos_build_site`, `klytos_rebuild_css`, `klytos_rebuild_plugin_assets`, `klytos_run_integrity_check`, `klytos_set_site_config`


---

## COBERTURA DE MCP TOOLS

El skill utiliza un total de **63 MCP tools de configuracion** repartidos asi:

| Categoria | Tools |
|-----------|-------|
| Site config | klytos_set_site_config, klytos_get_site_config |
| Theme | klytos_set_theme, klytos_set_colors, klytos_set_fonts, klytos_set_layout |
| Menus | klytos_set_menu, klytos_add_menu_item, klytos_remove_menu_item |
| Paginas | klytos_create_page, klytos_update_page, klytos_delete_page |
| Post types | klytos_create_post_type, klytos_update_post_type, klytos_delete_post_type |
| Taxonomias | klytos_add_taxonomy, klytos_update_taxonomy, klytos_remove_taxonomy, klytos_add_term, klytos_update_term, klytos_delete_term |
| Custom fields | klytos_add_custom_field, klytos_update_custom_field, klytos_remove_custom_field, klytos_reorder_custom_fields, klytos_set_field_value, klytos_set_bulk_field_values |
| Bloques | klytos_create_block, klytos_update_block, klytos_delete_block, klytos_set_global_block_data |
| Templates | klytos_set_template, klytos_delete_template, klytos_set_custom_template, klytos_delete_custom_template, klytos_set_custom_template_part, klytos_delete_custom_template_part, klytos_rebuild_plugin_assets |
| Page templates | klytos_create_page_template, klytos_add_block_to_template, klytos_remove_block_from_template, klytos_reorder_template_blocks, klytos_approve_page_template |
| Usuarios | klytos_create_user, klytos_update_user, klytos_reset_user_password, klytos_force_logout_user |
| Plugins | klytos_activate_plugin, klytos_deactivate_plugin |
| Webhooks | klytos_create_webhook, klytos_delete_webhook, klytos_test_webhook |
| Consent/GDPR | klytos_set_consent_config, klytos_add_consent_declaration, klytos_delete_consent_declaration |
| Scheduler | klytos_schedule_single_action, klytos_schedule_recurring_action, klytos_cancel_scheduled_action |
| Build | klytos_build_site, klytos_build_page, klytos_rebuild_block, klytos_rebuild_css |
| Guias | klytos_list_guides, klytos_get_guide |
| IA | klytos_generate_ai_image |
| Options | klytos_options_delete_domain, klytos_options_migrate |
| Integrity | klytos_run_integrity_check |


## PARAMETROS CONFIGURABLES CUBIERTOS

El skill cubre los **24 subsistemas** y **80+ parametros configurables** de Klytos:

1. Site config general (12 parametros)
2. Social media (6 parametros)
3. Analytics y scripts (3 parametros)
4. Email/SMTP (8 parametros)
5. Idiomas (code + name por idioma)
6. Theme colores (11 parametros)
7. Theme tipografia (8 parametros)
8. Theme layout (7 parametros)
9. CSS personalizado
10. Cache (driver, prefix, TTL, Redis, Memcached)
11. Developer mode (7 parametros)
12. SEO global (2 parametros)
13. Consent/GDPR (enabled, banner, privacy_url, cookie_days, categories)
14. Webhooks (url, events, secret, description, status)
15. AI providers (provider, model, API keys)
16. AI image generation (Gemini API key)
17. Analytics privacy-first (automatico, sin config necesaria)
18. Plugins (activacion/desactivacion)
19. Usuarios y roles
20. Menus de navegacion
21. Paginas y su SEO individual
22. Custom post types, campos y taxonomias
23. Templates y template parts
24. Bloques reutilizables


## NOTAS DE DISENO

**Adaptabilidad:** el skill no fuerza un flujo lineal rigido. Si alguien dice "solo quiero una landing page con formulario", el asistente salta a las fases relevantes y omite lo que no aplica.

**Uso de MCP tools existentes:** el skill no inventa herramientas nuevas. Orquesta las 122+ herramientas MCP que ya existen. Simplemente le dice al asistente en que orden usarlas y que preguntar antes.

**Consulta de guias:** antes de crear contenido, el asistente siempre debe leer las guias relevantes (gutenberg-blocks, seo-content, accessibility) via `klytos_get_guide`.

**Plan visible:** al final de la Fase 1, el asistente muestra un plan completo al usuario. Nada se ejecuta sin confirmacion.

**Progresivo:** cada fase termina con un mini-resumen de lo hecho y una pregunta de confirmacion antes de avanzar a la siguiente.

**Fuentes de contenido multiples:** el usuario puede proporcionar contenido de muchas formas (archivos txt/docx/pdf/md/html/csv, URLs directas de Dropbox/Drive, dictado, generacion con IA).

**Imagenes flexibles:** 5 vias para obtener imagenes (subida directa, generacion con IA via Gemini, URL externa tipo Dropbox, capturas de referencia, placeholder).

**Referencia de diseno:** el asistente analiza sitios web de referencia (visitandolos o via capturas) para tomar decisiones visuales informadas en vez de trabajar a ciegas.
