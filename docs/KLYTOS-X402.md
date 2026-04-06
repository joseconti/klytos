# Klytos — Plugin x402 Micropayments v1.0

> Plugin premium para Klytos.
> Monetización de contenido para agentes IA mediante el protocolo x402.
> Los humanos navegan gratis. Los bots IA pagan por acceder al contenido protegido.
> Se integra mediante el sistema de hooks de Klytos — no modifica el core.

---

## 0. Contexto

### 0.1 ¿Qué es x402?

x402 es un protocolo de pagos abierto creado por Coinbase (mayo 2025) que revive el código HTTP 402 "Payment Required". Permite que agentes IA y software autónomo paguen por acceder a recursos web usando stablecoins (USDC), sin cuentas, sin suscripciones, sin intervención humana.

La x402 Foundation (transferida a la Linux Foundation en abril 2026) cuenta con el respaldo de Coinbase, Cloudflare, AWS, Google, Microsoft, Stripe, Visa, Mastercard, American Express, Shopify, Adyen, Fiserv, Circle y Solana Foundation.

### 0.2 ¿Por qué en Klytos?

Klytos genera dos versiones de cada página durante el build:

- `public/{slug}.html` — para navegadores humanos
- `public/{slug}.html.md` — para agentes IA (versión markdown, eficiente en tokens)

Los bots IA buscan ambas versiones. Consumen contenido a escala sin compensar a los creadores. El plugin x402 permite al propietario del sitio cobrar a los agentes IA por acceder a cualquiera de los dos formatos, mientras los humanos siguen navegando gratis.

### 0.3 Principio fundamental

**La protección x402 se configura por página y aplica a ambos formatos (.html y .md) de esa página.** Si una página es de pago, ambos archivos son de pago. Si es gratis, ambos son gratis. No hay configuración separada por formato.

### 0.4 Alcance

- **Plugin premium**: `klytos-x402`, distribuido y licenciado desde `plugins.joseconti.com`
- **No modifica el core**: se integra exclusivamente mediante el sistema de hooks (`KLYTOS-HOOKS-API.md`)
- **Requiere**: Klytos >= 2.0.0, PHP >= 8.0, extensión cURL

---

## 1. Arquitectura General

### 1.1 Cómo sirve Klytos las páginas estáticas

Klytos genera archivos estáticos en `public/`. El servidor web (Apache/Nginx) los sirve directamente — PHP no interviene en cada petición de visitante. Este es el flujo normal:

```
Visitante → Apache/Nginx → public/{slug}.html → respuesta directa
```

### 1.2 El problema: archivos estáticos no pueden decidir

Un archivo `.html` o `.md` estático no puede decidir por sí solo si cobra o no. El control debe estar en una capa anterior al archivo, interceptando la petición antes de servirla.

### 1.3 Solución: interceptación por user-agent en .htaccess

El plugin genera reglas de rewrite que detectan user-agents de bots IA conocidos y redirigen esas peticiones a un script PHP (`x402-gate.php`). Los navegadores humanos nunca pasan por PHP — reciben el archivo estático directamente.

```
Petición HTTP entrante
    │
    ├─ .htaccess detecta User-Agent de bot IA
    │   (GPTBot, ClaudeBot, Google-Extended, PerplexityBot, etc.)
    │   o detecta headers x402 (X-Payment, X-Payment-Response)
    │
    ├─ SI es bot IA conocido o trae headers x402:
    │   │
    │   │  RewriteRule → x402-gate.php?slug={slug}&format={html|md}
    │   │
    │   │  x402-gate.php:
    │   │   1. ¿Existe esta página? → 404 si no
    │   │   2. ¿Esta página tiene x402 activado?
    │   │      ├─ NO → file_get_contents(public/{slug}.{ext}) → respuesta directa
    │   │      └─ SÍ → ¿Trae recibo/prueba de pago en headers?
    │   │          ├─ NO → HTTP 402 + JSON payload (precio, wallet, licencia)
    │   │          └─ SÍ → verificar con facilitator
    │   │              ├─ Válido → servir contenido + log de transacción
    │   │              └─ Inválido → HTTP 402 + error
    │   │
    └─ SI es navegador humano:
       │
       └─ Apache/Nginx sirve public/{slug}.html directamente
          (PHP no se entera, rendimiento máximo para humanos)
```

### 1.4 Rendimiento

- **Humanos**: cero overhead. Apache sirve archivos estáticos sin invocar PHP.
- **Bots en páginas gratis**: overhead mínimo — PHP lee config, detecta que no hay x402, sirve el archivo.
- **Bots en páginas de pago**: una petición PHP + una verificación al facilitator (async, < 500ms).

---

## 2. Configuración

### 2.1 Niveles de configuración

La protección x402 se controla a tres niveles, con herencia en cascada:

```
NIVEL 1: Global (plugin settings)
    │
    │  x402_default_enabled: true | false
    │  (default para todo contenido nuevo)
    │
    ├─── NIVEL 2: Por página/entry (override individual)
    │
    │    Cada página de cualquier Post Type puede sobrescribir
    │    el default global con su propio valor:
    │
    │    page.x402_enabled: true | false | null (hereda global)
    │
    └─── RESULTADO: 
         Si page.x402_enabled !== null → usa el valor de la página
         Si page.x402_enabled === null → usa x402_default_enabled
```

### 2.2 Configuración global del plugin

Almacenada en `data/plugin-settings/klytos-x402.json.enc`:

```json
{
  "x402_default_enabled": false,
  "wallet_address": "0x1234...abcd",
  "default_price_usd": "0.01",
  "network": "base",
  "facilitator_url": "https://x402.org/facilitator",
  "license": {
    "default_type": "inference",
    "default_text": "Content licensed for AI inference only. Not for training."
  },
  "known_bot_user_agents": [
    "GPTBot",
    "ClaudeBot",
    "Claude-Web",
    "Google-Extended",
    "GoogleOther",
    "PerplexityBot",
    "Amazonbot",
    "FacebookBot",
    "Bytespider",
    "CCBot",
    "Applebot-Extended",
    "cohere-ai",
    "Diffbot",
    "anthropic-ai",
    "YouBot",
    "Omgilibot",
    "Timpibot"
  ],
  "custom_bot_user_agents": [],
  "logging_enabled": true,
  "stats_enabled": true
}
```

### 2.3 Configuración por página

Al crear o editar una página, se añade un campo `x402_enabled` al JSON de la página (vía hook en `page.before_save`):

```json
{
  "slug": "servicios",
  "title": "Nuestros Servicios",
  "template": "services",
  "status": "published",
  "x402_enabled": true,
  "x402_price_usd": "0.05",
  "x402_license_type": "inference-only"
}
```

Si `x402_enabled` es `null` o no existe, hereda el valor global `x402_default_enabled`.

### 2.4 Precios

- **Precio global por defecto** (`default_price_usd`): aplica a todas las páginas que no definen precio propio.
- **Precio por página** (`x402_price_usd`): override del precio global para páginas de alto valor.
- Moneda: USD (liquidado en USDC stablecoin).

---

## 3. Integración MCP

### 3.1 Comportamiento al crear páginas

Cuando se crea una página vía MCP (`klytos_create_page`) y el plugin x402 está activo:

- Si el agente IA especifica `x402_enabled` en los parámetros → se usa ese valor.
- Si el agente IA **no** especifica `x402_enabled` → se aplica el valor global `x402_default_enabled`.
- El agente IA puede especificar también `x402_price_usd` para override de precio.

### 3.2 Tools MCP del plugin

El plugin registra los siguientes tools vía `mcp.tools_list`:

| Tool | Descripción | readOnly | destructive |
|------|-------------|----------|-------------|
| `klytos_x402_get_config` | Obtiene configuración global x402 | ✅ | ❌ |
| `klytos_x402_set_config` | Actualiza configuración global x402 | ❌ | ✅ |
| `klytos_x402_get_page_status` | Obtiene estado x402 de una página | ✅ | ❌ |
| `klytos_x402_set_page_status` | Cambia estado x402 de una página | ❌ | ✅ |
| `klytos_x402_bulk_set_status` | Cambia estado x402 de múltiples páginas | ❌ | ✅ |
| `klytos_x402_get_stats` | Estadísticas de pagos recibidos | ✅ | ❌ |
| `klytos_x402_list_transactions` | Lista transacciones recientes | ✅ | ❌ |

### 3.3 Tool Schemas

```json
// klytos_x402_set_config
{
  "name": "klytos_x402_set_config",
  "description": "Update the global x402 micropayments configuration. Controls whether new pages require AI bot payment by default, the wallet address for receiving payments, and default pricing.",
  "inputSchema": {
    "type": "object",
    "properties": {
      "x402_default_enabled": {
        "type": "boolean",
        "description": "Whether new pages are payment-protected by default"
      },
      "wallet_address": {
        "type": "string",
        "description": "Wallet address to receive payments (EVM-compatible)"
      },
      "default_price_usd": {
        "type": "string",
        "description": "Default price in USD for protected pages (e.g. '0.01')"
      },
      "network": {
        "type": "string",
        "enum": ["base", "base-sepolia", "polygon", "solana"],
        "description": "Blockchain network for settlements"
      },
      "facilitator_url": {
        "type": "string",
        "description": "URL of the x402 facilitator service"
      }
    }
  },
  "annotations": {
    "title": "Update x402 Configuration",
    "readOnlyHint": false,
    "destructiveHint": true,
    "idempotentHint": true
  }
}

// klytos_x402_set_page_status
{
  "name": "klytos_x402_set_page_status",
  "description": "Enable or disable x402 payment protection for a specific page. When enabled, AI bots must pay to access the page (both .html and .md versions). Human visitors are not affected.",
  "inputSchema": {
    "type": "object",
    "properties": {
      "slug": {
        "type": "string",
        "description": "Page slug to update"
      },
      "x402_enabled": {
        "type": "boolean",
        "description": "true = AI bots must pay, false = free for all, null = inherit global default"
      },
      "x402_price_usd": {
        "type": "string",
        "description": "Custom price in USD (null = use global default)"
      },
      "x402_license_type": {
        "type": "string",
        "enum": ["inference", "inference-only", "training", "full"],
        "description": "License type granted after payment"
      }
    },
    "required": ["slug"]
  },
  "annotations": {
    "title": "Set Page x402 Status",
    "readOnlyHint": false,
    "destructiveHint": false,
    "idempotentHint": true
  }
}

// klytos_x402_bulk_set_status
{
  "name": "klytos_x402_bulk_set_status",
  "description": "Enable or disable x402 payment protection for multiple pages at once.",
  "inputSchema": {
    "type": "object",
    "properties": {
      "slugs": {
        "type": "array",
        "items": { "type": "string" },
        "description": "Array of page slugs to update"
      },
      "x402_enabled": {
        "type": "boolean",
        "description": "true = AI bots must pay, false = free for all"
      },
      "x402_price_usd": {
        "type": "string",
        "description": "Custom price in USD (null = use global default)"
      }
    },
    "required": ["slugs", "x402_enabled"]
  },
  "annotations": {
    "title": "Bulk Set x402 Status",
    "readOnlyHint": false,
    "destructiveHint": false,
    "idempotentHint": true
  }
}
```

### 3.4 Integración con `klytos_create_page`

El plugin NO crea un tool alternativo para crear páginas. En su lugar, usa el filter `page.before_save` para inyectar la lógica x402:

```php
klytos_add_filter('page.before_save', function (array $data, string $slug): array {
    // If x402_enabled is not explicitly set, apply global default
    if (!array_key_exists('x402_enabled', $data) || $data['x402_enabled'] === null) {
        $config = klytos_config('klytos-x402.x402_default_enabled', false);
        $data['x402_enabled'] = $config;
    }
    return $data;
}, 10);
```

Esto significa que cuando un agente IA ejecuta `klytos_create_page`:

```json
// Agent specifies x402 → uses that value
{
  "slug": "premium-report",
  "title": "Premium AI Report",
  "template": "page",
  "x402_enabled": true,
  "x402_price_usd": "0.10"
}

// Agent doesn't specify x402 → inherits global default
{
  "slug": "about",
  "title": "About Us",
  "template": "page"
}
// → x402_enabled will be set to x402_default_enabled (true or false)
```

---

## 4. Respuesta HTTP 402

### 4.1 Formato de la respuesta

Cuando un bot IA solicita una página protegida sin prueba de pago, el servidor responde con HTTP 402 y un payload JSON:

```http
HTTP/1.1 402 Payment Required
Content-Type: application/json
X-Payment-Required: true
X-Payment-Network: base
X-Payment-Asset: USDC

{
  "x402": {
    "version": "2",
    "accepts": [
      {
        "scheme": "exact",
        "network": "base",
        "maxAmountRequired": "10000",
        "resource": "/servicios.html",
        "description": "Access to page: Nuestros Servicios",
        "mimeType": "text/html",
        "payTo": "0x1234...abcd",
        "maxTimeoutSeconds": 300,
        "asset": "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913",
        "extra": {
          "license": {
            "type": "inference-only",
            "text": "Content licensed for AI inference only. Not for training.",
            "spdx": "LicenseRef-x402-inference"
          },
          "name": "Nuestros Servicios",
          "site": "Mi Empresa",
          "generator": "Klytos CMS"
        }
      }
    ],
    "facilitator": "https://x402.org/facilitator"
  }
}
```

### 4.2 Notas sobre el precio

El campo `maxAmountRequired` usa la denominación mínima del asset (USDC tiene 6 decimales). Ejemplo: `$0.01 = "10000"`, `$0.05 = "50000"`, `$1.00 = "1000000"`.

### 4.3 Verificación del pago

Cuando el bot reenvía la petición con el recibo de pago en el header `X-Payment`:

1. `x402-gate.php` extrae el payload del header
2. Envía el payload al facilitator para verificación
3. El facilitator verifica la transacción on-chain
4. Si es válido → sirve el contenido + log de transacción
5. Si es inválido → HTTP 402 con error

```php
private function verifyPayment(string $paymentHeader, array $pageConfig): bool
{
    $facilitatorUrl = klytos_config('klytos-x402.facilitator_url');
    
    $ch = curl_init($facilitatorUrl . '/verify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'payment'  => $paymentHeader,
            'payTo'    => klytos_config('klytos-x402.wallet_address'),
            'amount'   => $this->calculateAmount($pageConfig),
            'network'  => klytos_config('klytos-x402.network'),
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200 && json_decode($response, true)['valid'] === true;
}
```

---

## 5. Detección de Bots IA

### 5.1 User-Agents conocidos

El plugin mantiene una lista actualizable de user-agents de bots IA. Esta lista se puede ampliar desde la configuración del plugin y vía MCP.

**User-agents incluidos por defecto:**

| Bot | Operador | Propósito |
|-----|----------|-----------|
| `GPTBot` | OpenAI | Crawling para ChatGPT |
| `OAI-SearchBot` | OpenAI | SearchGPT |
| `ChatGPT-User` | OpenAI | Browsing en tiempo real |
| `ClaudeBot` | Anthropic | Crawling para Claude |
| `Claude-Web` | Anthropic | Web access |
| `anthropic-ai` | Anthropic | Crawling general |
| `Google-Extended` | Google | Crawling para Gemini |
| `GoogleOther` | Google | Otros productos Google |
| `PerplexityBot` | Perplexity | Búsqueda IA |
| `Amazonbot` | Amazon | Alexa/búsqueda |
| `Bytespider` | ByteDance | TikTok/búsqueda |
| `CCBot` | Common Crawl | Crawling para datasets |
| `Applebot-Extended` | Apple | Apple Intelligence |
| `cohere-ai` | Cohere | Crawling para modelos |
| `Diffbot` | Diffbot | Extracción de datos |
| `YouBot` | You.com | Búsqueda IA |
| `Timpibot` | Timpi | Búsqueda descentralizada |
| `Meta-ExternalAgent` | Meta | Crawling para Meta AI |
| `Omgilibot` | Omgili | Crawling de contenido |
| `ImagesiftBot` | Imagesift | Crawling de imágenes |
| `Kangaroo Bot` | Kangaroo | Extracción de datos |

### 5.2 Detección dual

El plugin detecta bots IA de dos formas complementarias:

1. **User-Agent matching**: regex contra la lista de user-agents conocidos.
2. **Headers x402**: si la petición incluye `X-Payment` o `X-Payment-Response`, es un cliente x402 (independientemente del user-agent).

Un cliente desconocido que traiga headers x402 se trata como bot y pasa por el gate. Esto cubre agentes IA futuros que no estén en la lista pero soporten x402 nativamente.

---

## 6. Integración con el Build

### 6.1 Generación del .htaccess

Cuando el plugin se activa o cuando cambia la configuración, regenera las reglas de `.htaccess` usando el hook `build.after`:

```php
klytos_add_action('build.after', function () {
    $x402Plugin = new X402HtaccessWriter();
    $x402Plugin->writeRules();
});
```

### 6.2 Reglas .htaccess generadas

```apache
# --- Klytos x402 Plugin - START ---
# Auto-generated. Do not edit manually.

# Detect x402 headers (any client with payment headers)
RewriteCond %{HTTP:X-Payment} !^$
RewriteRule ^(.+)\.(html|html\.md)$ x402-gate.php?slug=$1&format=$2 [L,QSA]

# Detect known AI bot User-Agents
RewriteCond %{HTTP_USER_AGENT} (GPTBot|OAI-SearchBot|ChatGPT-User|ClaudeBot|Claude-Web|anthropic-ai|Google-Extended|GoogleOther|PerplexityBot|Amazonbot|Bytespider|CCBot|Applebot-Extended|cohere-ai|Diffbot|YouBot|Timpibot|Meta-ExternalAgent|Omgilibot) [NC]
RewriteRule ^(.+)\.(html|html\.md)$ x402-gate.php?slug=$1&format=$2 [L,QSA]

# --- Klytos x402 Plugin - END ---
```

Estas reglas se insertan **antes** de las reglas estándar de Klytos que sirven archivos estáticos. Si el request no matchea (es un humano), pasa directamente a las reglas normales.

### 6.3 Reglas Nginx (alternativa)

Para usuarios con Nginx, el plugin genera un archivo `x402-nginx.conf` que se puede incluir:

```nginx
# Klytos x402 Plugin - Include in your server block
# include /path/to/klytos/plugins/klytos-x402/x402-nginx.conf;

# Detect x402 headers
set $x402_gate 0;
if ($http_x_payment) {
    set $x402_gate 1;
}

# Detect known AI bot User-Agents
if ($http_user_agent ~* "(GPTBot|OAI-SearchBot|ChatGPT-User|ClaudeBot|Claude-Web|anthropic-ai|Google-Extended|GoogleOther|PerplexityBot|Amazonbot|Bytespider|CCBot|Applebot-Extended|cohere-ai|Diffbot|YouBot|Timpibot|Meta-ExternalAgent|Omgilibot)") {
    set $x402_gate 1;
}

# Route to gate if bot detected
if ($x402_gate = 1) {
    rewrite ^/(.+)\.(html|html\.md)$ /x402-gate.php?slug=$1&format=$2 last;
}
```

### 6.4 Integración con llms.txt

El plugin modifica la generación del archivo `llms.txt` (y `llms-full.txt`) para indicar qué páginas son de pago:

```php
klytos_add_filter('build.llms_txt', function (string $content): string {
    // Add x402 pricing info for protected pages
    $content .= "\n## Paid Content (x402)\n";
    $content .= "The following pages require x402 payment for AI access:\n";
    
    foreach ($this->getProtectedPages() as $page) {
        $price = $page['x402_price_usd'] ?? klytos_config('klytos-x402.default_price_usd');
        $content .= "- [{$page['title']}](/{$page['slug']}.html.md): \${$price} USD (USDC)\n";
    }
    
    return $content;
});
```

### 6.5 Integración con robots.txt

El plugin añade información para bots IA en el `robots.txt`:

```php
klytos_add_filter('build.robots_txt', function (string $robots): string {
    $robots .= "\n# x402 Micropayments - AI bots can access paid content via x402 protocol\n";
    $robots .= "# Payment info: See /.well-known/x402.json\n";
    return $robots;
});
```

### 6.6 Well-Known endpoint

El plugin crea `public/.well-known/x402.json` durante el build:

```json
{
  "x402": {
    "version": "2",
    "facilitator": "https://x402.org/facilitator",
    "network": "base",
    "asset": "USDC",
    "wallet": "0x1234...abcd",
    "protected_pages": 12,
    "total_pages": 45,
    "default_price_usd": "0.01",
    "license_types": ["inference", "inference-only", "training", "full"],
    "info_url": "/x402-info.html"
  }
}
```

---

## 7. Hooks utilizados

### 7.1 Hooks del core que usa el plugin

| Hook | Tipo | Uso |
|------|------|-----|
| `page.before_save` | Filter | Inyectar `x402_enabled` default en páginas nuevas |
| `page.after_save` | Action | Regenerar config de x402-gate si cambia estado x402 |
| `build.after` | Action | Regenerar `.htaccess`, `llms.txt`, `.well-known/x402.json` |
| `build.robots_txt` | Filter | Añadir info x402 al robots.txt |
| `build.sitemap_urls` | Filter | Marcar URLs protegidas en sitemap |
| `mcp.tools_list` | Filter | Registrar tools x402 |
| `mcp.handle_tool` | Filter | Manejar ejecución de tools x402 |
| `admin.sidebar_items` | Filter | Añadir sección "x402 Payments" al menú |
| `admin.dashboard_widgets` | Filter | Widget de estadísticas x402 |
| `admin.page_editor.tabs` | Filter | Pestaña "x402" en editor de páginas |
| `admin.routes` | Filter | Páginas de admin del plugin |
| `admin.api_routes` | Filter | Endpoints API del plugin |
| `admin.styles` | Filter | CSS del plugin en admin |
| `admin.scripts` | Filter | JS del plugin en admin |
| `auth.capabilities` | Filter | Permisos x402.manage, x402.view |
| `settings.sections` | Filter | Sección x402 en configuración |
| `webhooks.events` | Filter | Eventos x402.payment.received, x402.payment.failed |
| `plugin.activated` | Action | Crear tablas DB, generar .htaccess |
| `plugin.deactivated` | Action | Limpiar reglas .htaccess |

### 7.2 Hooks propios del plugin

El plugin expone sus propios hooks para que otros plugins puedan extenderlo:

```php
// Filter: x402.should_protect — override dinámico de protección
// Permite a otros plugins decidir si una página debe protegerse
klytos_add_filter('x402.should_protect', function (bool $protect, string $slug, array $request): bool {
    // Example: free access for verified agents with World ID
    if (isset($request['headers']['X-World-ID'])) {
        return false; // skip payment for verified agents
    }
    return $protect;
});

// Filter: x402.price — override dinámico de precio
klytos_add_filter('x402.price', function (string $price, string $slug, array $request): string {
    // Example: dynamic pricing based on time of day
    return $price;
});

// Filter: x402.response_payload — modificar la respuesta 402
klytos_add_filter('x402.response_payload', function (array $payload, string $slug): array {
    return $payload;
});

// Action: x402.payment_received — después de pago verificado
klytos_do_action('x402.payment_received', $slug, $amount, $transactionId, $botUserAgent);

// Action: x402.payment_failed — pago rechazado
klytos_do_action('x402.payment_failed', $slug, $reason, $botUserAgent);

// Filter: x402.bot_user_agents — modificar lista de user-agents
klytos_add_filter('x402.bot_user_agents', function (array $agents): array {
    $agents[] = 'MyCustomBot';
    return $agents;
});

// Filter: x402.license — modificar licencia por página
klytos_add_filter('x402.license', function (array $license, string $slug): array {
    return $license;
});
```

---

## 8. Admin UI

### 8.1 Sección en el menú lateral

```php
klytos_add_filter('admin.sidebar_items', function (array $items): array {
    $items[] = [
        'id'         => 'klytos-x402',
        'title'      => __('klytos-x402.title'), // "x402 Payments"
        'url'        => klytos_admin_url('plugin/klytos-x402/dashboard.php'),
        'icon'       => '💰',
        'position'   => 86,
        'capability' => 'x402.manage',
        'children'   => [
            [
                'id'    => 'x402-dashboard',
                'title' => __('klytos-x402.dashboard'),
                'url'   => klytos_admin_url('plugin/klytos-x402/dashboard.php'),
            ],
            [
                'id'    => 'x402-settings',
                'title' => __('klytos-x402.settings'),
                'url'   => klytos_admin_url('plugin/klytos-x402/settings.php'),
            ],
            [
                'id'    => 'x402-transactions',
                'title' => __('klytos-x402.transactions'),
                'url'   => klytos_admin_url('plugin/klytos-x402/transactions.php'),
            ],
        ],
    ];
    return $items;
});
```

### 8.2 Dashboard del plugin

Muestra:

- Total de ingresos (hoy, esta semana, este mes, total)
- Número de transacciones
- Top 10 páginas más solicitadas por bots
- Top 10 bots que más pagan
- Gráfico de ingresos por día (últimos 30 días)
- Estado del facilitator (online/offline)
- Wallet balance (si es posible consultarlo)

### 8.3 Pestaña x402 en el editor de páginas

```php
klytos_add_filter('admin.page_editor.tabs', function (array $tabs): array {
    $tabs[] = [
        'id'    => 'x402',
        'title' => '💰 x402',
        'icon'  => '💰',
        'render' => function (array $page): string {
            $globalDefault = klytos_config('klytos-x402.x402_default_enabled', false);
            $pageEnabled = $page['x402_enabled'] ?? null;
            $pagePrice = $page['x402_price_usd'] ?? null;
            $defaultPrice = klytos_config('klytos-x402.default_price_usd', '0.01');
            
            // Render toggle + price field + license selector
            return '...'; // HTML del formulario
        },
    ];
    return $tabs;
});
```

### 8.4 Configuración general

Página de settings con:

- Wallet address (campo de texto con validación de formato)
- Red blockchain (selector: Base, Polygon, Solana, Base Sepolia para testing)
- URL del facilitator (default: Coinbase CDP)
- Precio por defecto en USD
- Default para páginas nuevas (toggle on/off)
- Tipo de licencia por defecto
- Lista de User-Agents (editable, con opción de restaurar defaults)
- Toggle de logging
- Toggle de estadísticas

---

## 9. Almacenamiento de datos

### 9.1 Configuración del plugin

```
data/plugin-settings/klytos-x402.json.enc
```

### 9.2 Transacciones (flat-file)

```
data/x402-transactions/
├── 2026-04/
│   ├── 2026-04-06.json.enc    ← transacciones del día
│   └── 2026-04-07.json.enc
└── stats.json.enc              ← estadísticas agregadas
```

Cada transacción:

```json
{
  "id": "tx_abc123",
  "slug": "servicios",
  "format": "html.md",
  "bot_user_agent": "GPTBot/1.0",
  "bot_ip_hash": "sha256:...",
  "amount_usd": "0.05",
  "amount_raw": "50000",
  "network": "base",
  "tx_hash": "0xabc...",
  "facilitator_response": "verified",
  "license_type": "inference-only",
  "timestamp": "2026-04-06T14:30:00Z"
}
```

### 9.3 Transacciones (MySQL, si disponible)

```php
klytos_add_action('plugin.activated', function (string $pluginId) {
    if ($pluginId !== 'klytos-x402') return;
    if (klytos_config('storage_driver') !== 'database') return;

    $pdo = klytos_storage()->getPdo();
    $prefix = klytos_config('database.prefix', 'kly_');

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}x402_transactions (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug            VARCHAR(255) NOT NULL,
        format          VARCHAR(10) NOT NULL DEFAULT 'html',
        bot_user_agent  VARCHAR(512),
        bot_ip_hash     VARCHAR(64),
        amount_usd      DECIMAL(10,4) NOT NULL,
        amount_raw      VARCHAR(32) NOT NULL,
        network         VARCHAR(32) NOT NULL DEFAULT 'base',
        tx_hash         VARCHAR(128),
        facilitator_ok  TINYINT(1) DEFAULT 0,
        license_type    VARCHAR(32) DEFAULT 'inference',
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_slug (slug),
        INDEX idx_date (created_at DESC),
        INDEX idx_bot (bot_user_agent(100))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
});
```

---

## 10. Estructura de directorios del plugin

```
plugins/klytos-x402/
├── klytos-plugin.json          ← Metadata del plugin
├── init.php                    ← Entry point (hooks registration)
├── LICENSE.md                  ← Licencia propietaria
│
├── core/
│   ├── X402Gate.php            ← Lógica principal del gate (HTTP 402 / verificación)
│   ├── X402Config.php          ← Gestión de configuración
│   ├── X402BotDetector.php     ← Detección de bots por user-agent / headers
│   ├── X402PaymentVerifier.php ← Verificación de pagos con facilitator
│   ├── X402HtaccessWriter.php  ← Generación de reglas .htaccess / nginx
│   ├── X402TransactionLog.php  ← Log de transacciones (flat-file / MySQL)
│   ├── X402Stats.php           ← Estadísticas agregadas
│   └── X402McpTools.php        ← Registro y ejecución de tools MCP
│
├── admin/
│   ├── dashboard.php           ← Dashboard de estadísticas
│   ├── settings.php            ← Configuración del plugin
│   ├── transactions.php        ← Listado de transacciones
│   ├── assets/
│   │   ├── x402-admin.css      ← Estilos del admin
│   │   └── x402-admin.js       ← JS del admin (charts, toggles)
│   └── api/
│       └── x402-api.php        ← Endpoints API (stats, transactions)
│
├── public/
│   └── x402-gate.php           ← Script frontal (se copia a la raíz durante activación)
│
├── lang/
│   ├── en.json                 ← English
│   ├── es.json                 ← Español
│   ├── ca.json                 ← Català
│   ├── fr.json                 ← Français
│   ├── de.json                 ← Deutsch
│   ├── pt.json                 ← Português
│   └── it.json                 ← Italiano
│
└── templates/
    └── x402-nginx.conf.tpl     ← Template para reglas Nginx
```

---

## 11. Licenciamiento del plugin

### 11.1 Modelo de distribución

- Plugin premium vendido en `plugins.joseconti.com`
- Licencia propia (separada de la licencia core de Klytos)
- `item_name`: `Klytos x402`
- Actualizaciones vía el mismo mecanismo que el core (`wc-api=upgrade-api`)

### 11.2 Verificación

```php
// In init.php — verificar licencia del plugin al arrancar
$license = new PluginLicense('klytos-x402', 'Klytos x402');
if (!$license->isValid()) {
    // Show admin notice, disable functionality
    klytos_add_action('admin.dashboard.top', function () {
        echo '<div class="klytos-notice klytos-notice--warning">'
           . __('klytos-x402.license_required')
           . '</div>';
    });
    return; // Don't register hooks
}
```

---

## 12. Seguridad

| Capa | Medida |
|------|--------|
| **Datos** | Transacciones encriptadas con AES-256-GCM (mismo sistema que el core) |
| **Wallet** | Solo recibe pagos, nunca envía — no se almacenan private keys |
| **Facilitator** | Comunicación HTTPS con el facilitator de Coinbase CDP |
| **Bot detection** | User-agent + headers, no fingerprinting invasivo |
| **IP** | Solo se almacena hash SHA-256 de la IP, nunca la IP real |
| **Rate limiting** | Máximo 60 peticiones/minuto por IP al gate (previene DoS) |
| **Admin** | Permiso `x402.manage` requerido para configuración |
| **MCP** | Tools x402 requieren autenticación MCP estándar |
| **Replay protection** | Nonces y timestamps en pagos (verificados por facilitator) |

---

## 13. Permisos

```php
klytos_add_filter('auth.capabilities', function (array $capabilities): array {
    $capabilities['x402.manage'] = ['owner', 'admin'];
    $capabilities['x402.view']   = ['owner', 'admin', 'editor'];
    return $capabilities;
});
```

| Capability | Owner | Admin | Editor | Viewer |
|-----------|-------|-------|--------|--------|
| `x402.manage` | ✅ | ✅ | ❌ | ❌ |
| `x402.view` | ✅ | ✅ | ✅ | ❌ |

---

## 14. Webhook Events

```php
klytos_add_filter('webhooks.events', function (array $events): array {
    $events['x402.payment.received'] = 'x402: Payment received from AI bot';
    $events['x402.payment.failed']   = 'x402: Payment verification failed';
    $events['x402.config.updated']   = 'x402: Configuration updated';
    return $events;
});
```

---

## 15. Checklist de implementación

### Fase 1 — Core del plugin

- [ ] `klytos-plugin.json` — metadata
- [ ] `init.php` — registro de todos los hooks
- [ ] `core/X402Gate.php` — lógica principal del gate
- [ ] `core/X402Config.php` — configuración
- [ ] `core/X402BotDetector.php` — detección de bots
- [ ] `core/X402PaymentVerifier.php` — verificación con facilitator
- [ ] `core/X402HtaccessWriter.php` — generación de reglas
- [ ] `public/x402-gate.php` — script frontal
- [ ] Verificación de licencia del plugin

### Fase 2 — MCP Tools

- [ ] `core/X402McpTools.php` — registro y ejecución
- [ ] `klytos_x402_get_config` / `klytos_x402_set_config`
- [ ] `klytos_x402_get_page_status` / `klytos_x402_set_page_status`
- [ ] `klytos_x402_bulk_set_status`
- [ ] `klytos_x402_get_stats` / `klytos_x402_list_transactions`
- [ ] Integración con `page.before_save` (default x402_enabled)

### Fase 3 — Admin UI

- [ ] `admin/dashboard.php` — estadísticas y gráficos
- [ ] `admin/settings.php` — configuración del plugin
- [ ] `admin/transactions.php` — listado de transacciones
- [ ] Pestaña x402 en editor de páginas
- [ ] Widget en dashboard del admin
- [ ] `admin/assets/x402-admin.css` + `x402-admin.js`

### Fase 4 — Storage y Build

- [ ] `core/X402TransactionLog.php` — flat-file + MySQL
- [ ] `core/X402Stats.php` — agregación de estadísticas
- [ ] Tabla SQL `kly_x402_transactions` (auto-creación)
- [ ] Integración con `build.after` (`.htaccess`, `.well-known/x402.json`)
- [ ] Integración con `build.robots_txt`
- [ ] Integración con `build.llms_txt`

### Fase 5 — Polish

- [ ] Traducciones (es, en, ca, fr, de, pt, it)
- [ ] Reglas Nginx (template)
- [ ] Webhook events
- [ ] Documentación para usuarios
- [ ] Tests con Base Sepolia (testnet)
- [ ] Tests con bots reales (GPTBot, ClaudeBot)

---

## 16. Ejemplo de flujo completo

### Setup inicial

```
ADMIN → Activa plugin "Klytos x402" en /admin/plugins.php
      → Configura wallet address, red (Base), precio default ($0.01)
      → Activa "x402 por defecto en páginas nuevas" = ON
      → Build site → se generan reglas .htaccess + .well-known/x402.json
```

### Creación de páginas vía MCP

```
AGENTE IA → MCP:

  // Página premium (override explícito del precio)
  klytos_create_page({
    slug: "report-q1-2026",
    title: "AI Market Report Q1 2026",
    template: "page",
    x402_enabled: true,
    x402_price_usd: "0.25"
  })

  // Página gratis (override explícito)
  klytos_create_page({
    slug: "about",
    title: "About Us",
    template: "page",
    x402_enabled: false
  })

  // Página sin especificar → hereda default global (ON = de pago)
  klytos_create_page({
    slug: "blog-post-1",
    title: "Latest News",
    template: "post"
  })
  // → x402_enabled = true (hereda global)

  klytos_build_site()
```

### Bot IA accede a página protegida

```
GPTBot → GET /report-q1-2026.html.md
       ← HTTP 402 + JSON {price: $0.25, wallet: 0x..., network: base}

GPTBot → firma transacción USDC $0.25 → on-chain

GPTBot → GET /report-q1-2026.html.md
         Header: X-Payment: {signed payment payload}
       
       → x402-gate.php verifica con facilitator
       ← HTTP 200 + contenido markdown de la página
       
       → Transacción logueada en data/x402-transactions/
```

### Cambio vía MCP

```
AGENTE IA → "Cambia el blog post 1 a gratis"

  klytos_x402_set_page_status({
    slug: "blog-post-1",
    x402_enabled: false
  })
  
  → Actualizado. La próxima vez que un bot pida esa página, pasa sin pagar.
```

---

*Plugin premium para Klytos.*
*Versión del documento: 1.0.0 — Fecha: 2026-04-06*
