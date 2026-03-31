# Klytos -- Terminal Web Integrado en el Admin

**Version:** 0.12.0
**Fecha:** 31 de marzo de 2026
**Estado:** Propuesta de diseno

**Prerequisito:** El usuario debe tener 2FA activo en su cuenta. Sin 2FA, el terminal no se muestra ni es accesible.

---

## 1. Concepto general

### 1.1. Que es

Un pseudo-terminal integrado en el panel de administracion de Klytos que permite ejecutar **exclusivamente** comandos del CLI de Klytos (`klytos <comando>`) desde el navegador. No es un terminal de sistema operativo. No da acceso a bash, sh, ni a ningun binario del servidor.

### 1.2. Que NO es

- NO es un terminal SSH
- NO es una shell de sistema operativo
- NO permite ejecutar comandos del SO (ls, cat, rm, wget, curl, etc.)
- NO tiene acceso al filesystem mas alla de lo que el CLI de Klytos expone
- NO permite encadenar comandos con pipes (|), redirects (>), o operadores (&&, ||, ;)

### 1.3. Por que un pseudo-terminal y no un terminal real

Un terminal real (como el de WHM en la captura de referencia) da acceso root al servidor. Eso:

- Requiere que el hosting permita exec/shell_exec (muchos shared hosting lo bloquean)
- Abre una superficie de ataque enorme si la sesion se compromete
- No es necesario: el CLI de Klytos cubre todas las operaciones de administracion

Un pseudo-terminal es 100% PHP. Recibe un string, lo parsea, ejecuta la funcion PHP correspondiente, y devuelve la salida. No toca exec() ni shell_exec() ni proc_open(). Funciona en cualquier hosting.

---

## 2. Seguridad

### 2.1. Requisito obligatorio: 2FA activo

El terminal SOLO esta disponible si el usuario tiene 2FA activado en su cuenta. Esto se verifica en dos puntos:

**Punto 1 -- Sidebar (visibilidad del menu):**

En `admin/templates/sidebar.php`, el item "Terminal" solo se anade si el usuario tiene 2FA activo:

```php
// Dentro del array $items, ANTES del filtro admin.sidebar_items
// Solo anadir Terminal si el usuario tiene 2FA activo
$currentUser = klytos_current_user();
$has2fa = !empty($currentUser['two_factor']['enabled']);

if ($has2fa && klytos_has_permission('manage_system')) {
    $items[] = [
        'id'       => 'terminal',
        'label'    => $t('Terminal'),
        'icon'     => 'terminal',
        'url'      => klytos_admin_url('terminal'),
        'section'  => 'system',
        'position' => 95, // Justo antes de la ultima posicion en system
    ];
}
```

**Punto 2 -- Endpoint de ejecucion (doble verificacion):**

Aunque el menu no se muestre, alguien podria intentar llamar al endpoint directamente. El endpoint verifica de nuevo:

```php
// En admin/terminal.php (pagina) y en core/terminal-executor.php (API)
$currentUser = klytos_current_user();
if (empty($currentUser['two_factor']['enabled'])) {
    http_response_code(403);
    echo json_encode([
        'error' => true,
        'output' => 'Terminal requiere autenticacion de dos factores activa.'
    ]);
    exit;
}
```

### 2.2. Permisos requeridos

Solo los usuarios con el permiso `manage_system` pueden acceder al terminal. Este es el permiso mas alto del sistema, equivalente a administrador.

### 2.3. Revalidacion por inactividad

Si el usuario lleva mas de 10 minutos sin ejecutar un comando en el terminal, el siguiente comando requiere re-introducir su codigo 2FA antes de ejecutarse:

```php
// En core/terminal-executor.php
private function checkRevalidation(): bool
{
    $lastCommand = $_SESSION['klytos_terminal_last_command'] ?? 0;
    $elapsed = time() - $lastCommand;

    // 10 minutos sin actividad = revalidar
    return $elapsed > 600;
}
```

Cuando se requiere revalidacion, el frontend muestra un modal pidiendo el codigo 2FA. Solo tras verificarlo se ejecuta el comando pendiente.

### 2.4. Sanitizacion de entrada

Cada comando recibido pasa por un pipeline de validacion estricto:

```php
private function sanitizeInput(string $raw): string
{
    // 1. Eliminar caracteres de control (excepto espacio)
    $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $raw);

    // 2. Limitar longitud (256 caracteres maximo)
    $clean = mb_substr($clean, 0, 256);

    // 3. Eliminar caracteres peligrosos de shell
    //    Aunque nunca llegamos a exec(), defensa en profundidad
    $clean = str_replace(
        ['|', '>', '<', '&', ';', '`', '$', '(', ')', '{', '}', '[', ']', '\\', "\n", "\r"],
        '',
        $clean
    );

    return trim($clean);
}
```

### 2.5. Rate limiting

Maximo 30 comandos por minuto por usuario. Si se supera, el terminal muestra "Demasiadas peticiones. Espera unos segundos." y bloquea la ejecucion hasta que pase la ventana.

```php
private function checkRateLimit(string $userId): bool
{
    $key = "terminal_rate_{$userId}";
    $window = 60; // 1 minuto
    $maxCommands = 30;

    $storage = klytos_storage();
    $data = $storage->findOne('rate-limits', ['key' => $key]);

    if (!$data || (time() - $data['window_start']) > $window) {
        $storage->upsert('rate-limits', ['key' => $key], [
            'key'          => $key,
            'count'        => 1,
            'window_start' => time(),
        ]);
        return true;
    }

    if ($data['count'] >= $maxCommands) {
        return false;
    }

    $storage->upsert('rate-limits', ['key' => $key], [
        'count' => $data['count'] + 1,
    ]);
    return true;
}
```

### 2.6. Audit log

Cada comando ejecutado se registra en el audit log del sistema:

```php
klytos_log('info', 'terminal.command', [
    'user_id' => $userId,
    'command' => $fullCommand,
    'ip'      => $_SERVER['REMOTE_ADDR'],
    'result'  => $success ? 'ok' : 'error',
]);
```

---

## 3. Arquitectura backend

### 3.1. Nueva clase: TerminalExecutor

**Archivo a crear:** `core/terminal-executor.php`

Esta clase es el nucleo del sistema. Recibe un string de comando, lo parsea, lo valida contra la lista de comandos permitidos, ejecuta la funcion PHP correspondiente, y devuelve la salida como texto.

```php
<?php
/**
 * Klytos -- Terminal Executor
 * Pseudo-terminal que ejecuta exclusivamente comandos del CLI de Klytos.
 *
 * No usa exec(), shell_exec(), proc_open(), passthru(), system() ni ninguna
 * funcion que invoque procesos del sistema operativo. Todo se ejecuta
 * internamente en PHP llamando a las mismas funciones que usa cli.php.
 *
 * @package Klytos
 * @since   0.12.0
 */

declare(strict_types=1);

namespace Klytos\Core;

class TerminalExecutor
{
    private App $app;

    /**
     * Registro de comandos disponibles.
     * Clave: nombre del comando (ej: 'build', 'cache:clear')
     * Valor: array con:
     *   - 'description': descripcion corta para help
     *   - 'usage': ejemplo de uso
     *   - 'handler': callable que ejecuta el comando
     *   - 'permission': permiso requerido (ademas de manage_system)
     *   - 'category': categoria para agrupar en help
     *
     * @var array<string, array>
     */
    private array $commands = [];

    /**
     * Historial de comandos de la sesion actual (solo en memoria).
     *
     * @var array<int, array{command: string, output: string, timestamp: int}>
     */
    private array $sessionHistory = [];

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->registerCoreCommands();

        // Permitir que los plugins registren comandos adicionales
        // Los plugins hacen: klytos_add_filter('terminal.commands', fn($cmds) => [...])
        $this->commands = klytos_apply_filters('terminal.commands', $this->commands);
    }

    /**
     * Ejecutar un comando.
     *
     * @param string $input    Comando tal como lo escribio el usuario
     * @param string $userId   ID del usuario que ejecuta
     * @return array{
     *   success: bool,
     *   output: string,
     *   command: string,
     *   timestamp: int,
     *   requires_2fa: bool
     * }
     */
    public function execute(string $input, string $userId): array
    {
        $timestamp = time();

        // 1. Sanitizar
        $clean = $this->sanitizeInput($input);

        if ($clean === '') {
            return [
                'success'      => false,
                'output'       => '',
                'command'      => '',
                'timestamp'    => $timestamp,
                'requires_2fa' => false,
            ];
        }

        // 2. Rate limiting
        if (!$this->checkRateLimit($userId)) {
            return [
                'success'      => false,
                'output'       => 'Demasiadas peticiones. Espera unos segundos.',
                'command'      => $clean,
                'timestamp'    => $timestamp,
                'requires_2fa' => false,
            ];
        }

        // 3. Revalidacion 2FA por inactividad
        if ($this->checkRevalidation()) {
            return [
                'success'      => false,
                'output'       => '',
                'command'      => $clean,
                'timestamp'    => $timestamp,
                'requires_2fa' => true,
            ];
        }

        // 4. Parsear comando y argumentos
        $parsed = $this->parseCommand($clean);
        $commandName = $parsed['command'];
        $args = $parsed['args'];
        $flags = $parsed['flags'];

        // 5. Verificar que el comando existe
        if (!isset($this->commands[$commandName])) {
            $suggestion = $this->suggestCommand($commandName);
            $output = "Comando no reconocido: {$commandName}";
            if ($suggestion) {
                $output .= "\nQuizas quisiste decir: {$suggestion}";
            }
            $output .= "\nEscribe 'help' para ver los comandos disponibles.";

            return [
                'success'      => false,
                'output'       => $output,
                'command'      => $clean,
                'timestamp'    => $timestamp,
                'requires_2fa' => false,
            ];
        }

        // 6. Verificar permisos especificos del comando
        $cmdConfig = $this->commands[$commandName];
        if (!empty($cmdConfig['permission'])) {
            if (!klytos_has_permission($cmdConfig['permission'])) {
                return [
                    'success'      => false,
                    'output'       => "No tienes permiso para ejecutar este comando.",
                    'command'      => $clean,
                    'timestamp'    => $timestamp,
                    'requires_2fa' => false,
                ];
            }
        }

        // 7. Ejecutar
        try {
            ob_start();
            $handler = $cmdConfig['handler'];
            $result = $handler($args, $flags, $this);
            $buffered = ob_get_clean();

            // El handler puede devolver string o usar echo (capturado por ob)
            $output = is_string($result) ? $result : $buffered;

            // 8. Actualizar timestamp de ultimo comando
            $_SESSION['klytos_terminal_last_command'] = $timestamp;

            // 9. Audit log
            klytos_log('info', 'terminal.command', [
                'user_id' => $userId,
                'command' => $clean,
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'result'  => 'ok',
            ]);

            return [
                'success'      => true,
                'output'       => $output,
                'command'      => $clean,
                'timestamp'    => $timestamp,
                'requires_2fa' => false,
            ];

        } catch (\Throwable $e) {
            ob_end_clean();

            klytos_log('error', 'terminal.command', [
                'user_id' => $userId,
                'command' => $clean,
                'ip'      => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'result'  => 'error',
                'error'   => $e->getMessage(),
            ]);

            return [
                'success'      => false,
                'output'       => "Error: " . $e->getMessage(),
                'command'      => $clean,
                'timestamp'    => $timestamp,
                'requires_2fa' => false,
            ];
        }
    }

    /**
     * Parsear un string de comando en nombre, argumentos y flags.
     *
     * Ejemplos:
     *   "build"                  => {command: "build", args: [], flags: []}
     *   "build:page mi-pagina"   => {command: "build:page", args: ["mi-pagina"], flags: []}
     *   "analytics --period=30d" => {command: "analytics", args: [], flags: {period: "30d"}}
     *   "klytos build"           => {command: "build", args: [], flags: []}
     *
     * @return array{command: string, args: string[], flags: array<string, string>}
     */
    private function parseCommand(string $input): array
    {
        $parts = preg_split('/\s+/', $input);
        $parts = array_values(array_filter($parts, fn($p) => $p !== ''));

        if (empty($parts)) {
            return ['command' => '', 'args' => [], 'flags' => []];
        }

        // Si el usuario escribe "klytos build", ignorar "klytos"
        if (strtolower($parts[0]) === 'klytos') {
            array_shift($parts);
        }

        if (empty($parts)) {
            return ['command' => 'help', 'args' => [], 'flags' => []];
        }

        $command = strtolower(array_shift($parts));
        $args = [];
        $flags = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) {
                $flag = substr($part, 2);
                if (str_contains($flag, '=')) {
                    [$key, $value] = explode('=', $flag, 2);
                    $flags[$key] = $value;
                } else {
                    $flags[$flag] = 'true';
                }
            } else {
                $args[] = $part;
            }
        }

        return [
            'command' => $command,
            'args'    => $args,
            'flags'   => $flags,
        ];
    }

    /**
     * Registrar todos los comandos del core.
     * Estos son los mismos que expone cli.php pero ejecutados internamente.
     */
    private function registerCoreCommands(): void
    {
        // --- Categoria: Build ---

        $this->commands['build'] = [
            'description' => 'Regenerar todo el sitio estatico',
            'usage'       => 'build',
            'category'    => 'build',
            'permission'  => 'manage_content',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                $engine = $this->app->getBuildEngine();
                $result = $engine->buildAll();
                $count = $result['pages_built'] ?? 0;
                return "Sitio regenerado correctamente. {$count} paginas construidas.";
            },
        ];

        $this->commands['build:page'] = [
            'description' => 'Regenerar una pagina especifica por su slug',
            'usage'       => 'build:page <slug>',
            'category'    => 'build',
            'permission'  => 'manage_content',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                if (empty($args[0])) {
                    return "Uso: build:page <slug>\nEjemplo: build:page mi-pagina";
                }
                $slug = $args[0];
                $engine = $this->app->getBuildEngine();
                $result = $engine->buildPage($slug);
                if ($result) {
                    return "Pagina '{$slug}' regenerada correctamente.";
                }
                return "Error: no se encontro la pagina '{$slug}'.";
            },
        ];

        // --- Categoria: Contenido ---

        $this->commands['pages'] = [
            'description' => 'Listar todas las paginas publicadas',
            'usage'       => 'pages',
            'category'    => 'content',
            'permission'  => 'manage_content',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                $pages = $this->app->getPageManager()->getAllPages();
                if (empty($pages)) {
                    return "No hay paginas publicadas.";
                }
                $output = "Paginas publicadas (" . count($pages) . "):\n\n";
                foreach ($pages as $page) {
                    $status = $page['status'] ?? 'draft';
                    $slug = $page['slug'] ?? '(sin slug)';
                    $title = $page['title'] ?? '(sin titulo)';
                    $output .= "  [{$status}] /{$slug} -- {$title}\n";
                }
                return $output;
            },
        ];

        $this->commands['pages:count'] = [
            'description' => 'Mostrar numero total de paginas',
            'usage'       => 'pages:count',
            'category'    => 'content',
            'permission'  => 'manage_content',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                $pages = $this->app->getPageManager()->getAllPages();
                return "Total de paginas: " . count($pages);
            },
        ];

        // --- Categoria: Sistema ---

        $this->commands['status'] = [
            'description' => 'Estado general del sistema',
            'usage'       => 'status',
            'category'    => 'system',
            'permission'  => 'manage_system',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                $version = klytos_version();
                $config = $this->app->getConfig();
                $siteName = $config['site_name'] ?? 'Sin nombre';
                $storage = klytos_config('storage.driver') ?? 'file';
                $pages = $this->app->getPageManager()->getAllPages();

                $output  = "Klytos v{$version}\n";
                $output .= "Sitio: {$siteName}\n";
                $output .= "Almacenamiento: {$storage}\n";
                $output .= "Paginas: " . count($pages) . "\n";
                $output .= "PHP: " . PHP_VERSION . "\n";
                $output .= "Servidor: " . (PHP_SAPI) . "\n";
                return $output;
            },
        ];

        $this->commands['version'] = [
            'description' => 'Mostrar version de Klytos',
            'usage'       => 'version',
            'category'    => 'system',
            'permission'  => null,
            'handler'     => function (array $args, array $flags, self $terminal): string {
                return "Klytos v" . klytos_version();
            },
        ];

        $this->commands['cache:clear'] = [
            'description' => 'Limpiar caches del sistema (rate limits, cron)',
            'usage'       => 'cache:clear',
            'category'    => 'system',
            'permission'  => 'manage_system',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                $storage = klytos_storage();
                $storage->deleteCollection('rate-limits');
                $storage->deleteCollection('cron-locks');
                return "Caches limpiadas correctamente.";
            },
        ];

        $this->commands['cron:run'] = [
            'description' => 'Ejecutar tareas programadas pendientes',
            'usage'       => 'cron:run',
            'category'    => 'system',
            'permission'  => 'manage_system',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                $scheduler = $this->app->getActionScheduler();
                $executed = $scheduler->runDueActions();
                return "Tareas ejecutadas: {$executed}";
            },
        ];

        // --- Categoria: Usuarios ---

        $this->commands['users'] = [
            'description' => 'Listar usuarios del admin',
            'usage'       => 'users',
            'category'    => 'users',
            'permission'  => 'manage_users',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                $userManager = $this->app->getUserManager();
                $users = $userManager->getAllUsers();
                if (empty($users)) {
                    return "No hay usuarios registrados.";
                }
                $output = "Usuarios (" . count($users) . "):\n\n";
                foreach ($users as $user) {
                    $name = $user['display_name'] ?? $user['username'] ?? '(sin nombre)';
                    $role = $user['role'] ?? 'user';
                    $twofa = !empty($user['two_factor']['enabled']) ? '[2FA]' : '[---]';
                    $output .= "  {$twofa} {$name} ({$role})\n";
                }
                return $output;
            },
        ];

        // --- Categoria: Plugins ---

        $this->commands['plugins'] = [
            'description' => 'Listar plugins instalados y su estado',
            'usage'       => 'plugins',
            'category'    => 'plugins',
            'permission'  => 'manage_plugins',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                $loader = $this->app->getPluginLoader();
                $plugins = $loader->getDiscoveredPlugins();
                if (empty($plugins)) {
                    return "No hay plugins instalados.";
                }
                $output = "Plugins instalados (" . count($plugins) . "):\n\n";
                foreach ($plugins as $id => $manifest) {
                    $name = $manifest['name'] ?? $id;
                    $version = $manifest['version'] ?? '?';
                    $active = $loader->isActive($id) ? 'activo' : 'inactivo';
                    $output .= "  [{$active}] {$name} v{$version} ({$id})\n";
                }
                return $output;
            },
        ];

        $this->commands['plugins:activate'] = [
            'description' => 'Activar un plugin por su ID',
            'usage'       => 'plugins:activate <plugin-id>',
            'category'    => 'plugins',
            'permission'  => 'manage_plugins',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                if (empty($args[0])) {
                    return "Uso: plugins:activate <plugin-id>\nEjemplo: plugins:activate klytos-ecommerce";
                }
                $loader = $this->app->getPluginLoader();
                $result = $loader->activate($args[0]);
                if ($result['success'] ?? false) {
                    return "Plugin '{$args[0]}' activado correctamente.";
                }
                return "Error al activar plugin: " . ($result['error'] ?? 'desconocido');
            },
        ];

        $this->commands['plugins:deactivate'] = [
            'description' => 'Desactivar un plugin por su ID',
            'usage'       => 'plugins:deactivate <plugin-id>',
            'category'    => 'plugins',
            'permission'  => 'manage_plugins',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                if (empty($args[0])) {
                    return "Uso: plugins:deactivate <plugin-id>";
                }
                $loader = $this->app->getPluginLoader();
                $result = $loader->deactivate($args[0]);
                if ($result['success'] ?? false) {
                    return "Plugin '{$args[0]}' desactivado correctamente.";
                }
                return "Error al desactivar plugin: " . ($result['error'] ?? 'desconocido');
            },
        ];

        // --- Categoria: Tareas ---

        $this->commands['tasks'] = [
            'description' => 'Listar tareas pendientes',
            'usage'       => 'tasks',
            'category'    => 'content',
            'permission'  => 'manage_content',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                $taskManager = $this->app->getTaskManager();
                $tasks = $taskManager->getAllTasks();
                if (empty($tasks)) {
                    return "No hay tareas.";
                }
                $output = "Tareas (" . count($tasks) . "):\n\n";
                foreach ($tasks as $task) {
                    $status = $task['status'] ?? 'pending';
                    $title = $task['title'] ?? '(sin titulo)';
                    $output .= "  [{$status}] {$title}\n";
                }
                return $output;
            },
        ];

        $this->commands['tasks:count'] = [
            'description' => 'Mostrar numero total de tareas',
            'usage'       => 'tasks:count',
            'category'    => 'content',
            'permission'  => 'manage_content',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                $taskManager = $this->app->getTaskManager();
                $tasks = $taskManager->getAllTasks();
                return "Total de tareas: " . count($tasks);
            },
        ];

        // --- Categoria: Analytics ---

        $this->commands['analytics'] = [
            'description' => 'Mostrar resumen de analiticas',
            'usage'       => 'analytics [--period=7d|30d|90d]',
            'category'    => 'system',
            'permission'  => 'view_analytics',
            'handler'     => function (array $args, array $flags, self $terminal): string {
                $period = $flags['period'] ?? '7d';
                $analytics = $this->app->getAnalyticsManager();
                $data = $analytics->getSummary($period);
                $output  = "Analiticas (periodo: {$period}):\n\n";
                $output .= "  Visitas totales: " . ($data['total_visits'] ?? 0) . "\n";
                $output .= "  Visitantes unicos: " . ($data['unique_visitors'] ?? 0) . "\n";
                $output .= "  Paginas vistas: " . ($data['page_views'] ?? 0) . "\n";
                return $output;
            },
        ];

        // --- Comando especial: help ---

        $this->commands['help'] = [
            'description' => 'Mostrar esta ayuda',
            'usage'       => 'help [comando]',
            'category'    => 'general',
            'permission'  => null,
            'handler'     => function (array $args, array $flags, self $terminal): string {
                // Si piden ayuda de un comando especifico
                if (!empty($args[0])) {
                    $cmdName = strtolower($args[0]);
                    if (isset($this->commands[$cmdName])) {
                        $cmd = $this->commands[$cmdName];
                        $output  = "{$cmdName} -- {$cmd['description']}\n\n";
                        $output .= "Uso: {$cmd['usage']}\n";
                        return $output;
                    }
                    return "Comando no encontrado: {$cmdName}";
                }

                // Listar todos los comandos agrupados por categoria
                $categories = [];
                foreach ($this->commands as $name => $config) {
                    $cat = $config['category'] ?? 'general';
                    $categories[$cat][$name] = $config;
                }

                $categoryLabels = [
                    'general' => 'General',
                    'build'   => 'Build',
                    'content' => 'Contenido',
                    'system'  => 'Sistema',
                    'users'   => 'Usuarios',
                    'plugins' => 'Plugins',
                ];

                $output = "Klytos Terminal v" . klytos_version() . "\n";
                $output .= "Comandos disponibles:\n\n";

                foreach ($categories as $cat => $cmds) {
                    $label = $categoryLabels[$cat] ?? ucfirst($cat);
                    $output .= "  {$label}:\n";
                    foreach ($cmds as $name => $config) {
                        $desc = $config['description'];
                        $output .= "    {$name}" . str_repeat(' ', max(1, 24 - strlen($name))) . "{$desc}\n";
                    }
                    $output .= "\n";
                }

                $output .= "Escribe 'help <comando>' para mas detalles sobre un comando.\n";
                $output .= "Los plugins pueden anadir comandos adicionales.\n";
                return $output;
            },
        ];

        // --- Comando especial: clear ---

        $this->commands['clear'] = [
            'description' => 'Limpiar la pantalla del terminal',
            'usage'       => 'clear',
            'category'    => 'general',
            'permission'  => null,
            'handler'     => function (array $args, array $flags, self $terminal): string {
                // Devolvemos un marcador especial que el frontend interpreta
                return '__CLEAR__';
            },
        ];
    }

    /**
     * Sugerir un comando similar cuando el usuario escribe algo incorrecto.
     * Usa distancia de Levenshtein para encontrar el comando mas cercano.
     */
    private function suggestCommand(string $input): ?string
    {
        $minDistance = PHP_INT_MAX;
        $suggestion = null;

        foreach (array_keys($this->commands) as $name) {
            $distance = levenshtein($input, $name);
            if ($distance < $minDistance && $distance <= 3) {
                $minDistance = $distance;
                $suggestion = $name;
            }
        }

        return $suggestion;
    }

    /**
     * Obtener la lista de comandos registrados.
     * Usado por el endpoint de autocompletado.
     *
     * @return array<string, array>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Registrar un comando desde un plugin.
     * Alternativa al filtro terminal.commands.
     */
    public function registerCommand(string $name, array $config): void
    {
        $required = ['description', 'usage', 'handler', 'category'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new \InvalidArgumentException(
                    "El campo '{$field}' es requerido al registrar el comando '{$name}'"
                );
            }
        }
        $this->commands[strtolower($name)] = $config;
    }

    // --- Metodos privados de seguridad (implementados en seccion 2) ---

    private function sanitizeInput(string $raw): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $raw);
        $clean = mb_substr($clean, 0, 256);
        $clean = str_replace(
            ['|', '>', '<', '&', ';', '`', '$', '(', ')', '{', '}', '[', ']', '\\', "\n", "\r"],
            '',
            $clean
        );
        return trim($clean);
    }

    private function checkRateLimit(string $userId): bool
    {
        $key = "terminal_rate_{$userId}";
        $window = 60;
        $maxCommands = 30;
        $storage = klytos_storage();
        $data = $storage->findOne('rate-limits', ['key' => $key]);

        if (!$data || (time() - ($data['window_start'] ?? 0)) > $window) {
            $storage->upsert('rate-limits', ['key' => $key], [
                'key'          => $key,
                'count'        => 1,
                'window_start' => time(),
            ]);
            return true;
        }
        if (($data['count'] ?? 0) >= $maxCommands) {
            return false;
        }
        $storage->upsert('rate-limits', ['key' => $key], [
            'count' => ($data['count'] ?? 0) + 1,
        ]);
        return true;
    }

    private function checkRevalidation(): bool
    {
        $lastCommand = $_SESSION['klytos_terminal_last_command'] ?? 0;
        return (time() - $lastCommand) > 600;
    }
}
```

### 3.2. Endpoint API para ejecutar comandos

**Archivo a crear:** `admin/api/terminal.php`

Este endpoint recibe comandos via AJAX y devuelve la salida en JSON.

```php
<?php
/**
 * Klytos -- Terminal API Endpoint
 *
 * Recibe comandos del terminal web y devuelve la salida.
 * Solo accesible via POST con sesion admin activa y 2FA habilitado.
 *
 * POST /admin/?route=api/terminal
 * Body: { "command": "build" }
 * Response: { "success": true, "output": "...", "timestamp": 1234567890 }
 *
 * @package Klytos
 * @since   0.12.0
 */

declare(strict_types=1);

// Este archivo se carga desde el router del admin, el bootstrap ya esta hecho
// $app ya esta disponible

// 1. Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 2. Verificar CSRF
$csrfHeader = $_SERVER['HTTP_X_KLYTOS_CSRF'] ?? '';
$csrfSession = $_SESSION['klytos_csrf'] ?? '';
if (!$csrfHeader || !hash_equals($csrfSession, $csrfHeader)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token invalido']);
    exit;
}

// 3. Verificar autenticacion admin
$auth = klytos_auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

// 4. Verificar permiso manage_system
if (!klytos_has_permission('manage_system')) {
    http_response_code(403);
    echo json_encode(['error' => 'Permiso insuficiente']);
    exit;
}

// 5. Verificar 2FA activo
$currentUser = klytos_current_user();
if (empty($currentUser['two_factor']['enabled'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Terminal requiere autenticacion de dos factores activa.']);
    exit;
}

// 6. Leer comando del body
$body = json_decode(file_get_contents('php://input'), true);
$command = $body['command'] ?? '';

if (!is_string($command) || trim($command) === '') {
    echo json_encode(['success' => false, 'output' => '', 'timestamp' => time()]);
    exit;
}

// 7. Ejecutar
$executor = new \Klytos\Core\TerminalExecutor($app);
$result = $executor->execute($command, $currentUser['id'] ?? $currentUser['_id'] ?? 'unknown');

// 8. Responder
header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;
```

### 3.3. Endpoint para autocompletado

**Archivo a crear:** `admin/api/terminal-autocomplete.php`

Devuelve la lista de comandos disponibles para que el frontend implemente Tab-completion.

```php
<?php
/**
 * Klytos -- Terminal Autocomplete Endpoint
 *
 * GET /admin/?route=api/terminal-autocomplete&q=bui
 * Response: { "suggestions": ["build", "build:page"] }
 *
 * @package Klytos
 * @since   0.12.0
 */

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Verificaciones de auth, permiso y 2FA (identicas al endpoint principal)
$auth = klytos_auth();
if (!$auth->isAuthenticated() || !klytos_has_permission('manage_system')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$currentUser = klytos_current_user();
if (empty($currentUser['two_factor']['enabled'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Requiere 2FA']);
    exit;
}

$query = strtolower(trim($_GET['q'] ?? ''));

$executor = new \Klytos\Core\TerminalExecutor($app);
$commands = $executor->getCommands();
$commandNames = array_keys($commands);

if ($query === '') {
    $suggestions = $commandNames;
} else {
    $suggestions = array_values(array_filter(
        $commandNames,
        fn($name) => str_starts_with($name, $query)
    ));
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['suggestions' => $suggestions], JSON_UNESCAPED_UNICODE);
exit;
```

### 3.4. Endpoint para revalidacion 2FA

**Archivo a crear:** `admin/api/terminal-revalidate.php`

Cuando el terminal detecta inactividad, el frontend envia el codigo 2FA a este endpoint para revalidar.

```php
<?php
/**
 * Klytos -- Terminal 2FA Revalidation Endpoint
 *
 * POST /admin/?route=api/terminal-revalidate
 * Body: { "code": "123456", "method": "totp" }
 * Response: { "success": true }
 *
 * @package Klytos
 * @since   0.12.0
 */

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// CSRF check
$csrfHeader = $_SERVER['HTTP_X_KLYTOS_CSRF'] ?? '';
$csrfSession = $_SESSION['klytos_csrf'] ?? '';
if (!$csrfHeader || !hash_equals($csrfSession, $csrfHeader)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF invalido']);
    exit;
}

// Auth + 2FA check
$auth = klytos_auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$currentUser = klytos_current_user();
if (empty($currentUser['two_factor']['enabled'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Requiere 2FA']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$code = $body['code'] ?? '';
$method = $body['method'] ?? 'totp';

$twoFactor = klytos_app()->getTwoFactor();
$userId = $currentUser['id'] ?? $currentUser['_id'] ?? '';

$verified = false;

switch ($method) {
    case 'totp':
        $secret = $currentUser['two_factor']['totp_secret'] ?? '';
        $verified = $twoFactor->verifyTotp($secret, $code);
        break;

    case 'recovery':
        $verified = $twoFactor->verifyRecoveryCode($userId, $code);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Metodo 2FA no soportado para revalidacion']);
        exit;
}

if ($verified) {
    $_SESSION['klytos_terminal_last_command'] = time();
    echo json_encode(['success' => true]);
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Codigo 2FA invalido']);
}
exit;
```

---

## 4. Arquitectura frontend

### 4.1. Pagina admin del terminal

**Archivo a crear:** `admin/templates/terminal.php`

Esta pagina se carga cuando el usuario navega a la seccion Terminal del admin.

```php
<?php
/**
 * Klytos -- Terminal Page (Admin)
 *
 * Renderiza la interfaz del terminal usando xterm.js.
 * Solo accesible con 2FA activo y permiso manage_system.
 *
 * @package Klytos
 * @since   0.12.0
 */

// La verificacion de auth/2FA ya se hizo en el router del admin.
// Esta pagina solo se sirve si el usuario paso todas las comprobaciones.

$csrfToken = $_SESSION['klytos_csrf'] ?? '';
$klytosVersion = klytos_version();
?>
<!DOCTYPE html>
<html lang="<?= klytos_config('site.language') ?? 'es' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminal -- Klytos Admin</title>

    <!-- xterm.js desde CDN -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/xterm/5.3.0/xterm.min.css"
          integrity="sha512-..."
          crossorigin="anonymous" />

    <style>
        .klytos-terminal-wrapper {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 60px); /* Restar altura del header del admin */
            background: #1e1e2e;
            border-radius: 8px;
            overflow: hidden;
        }

        .klytos-terminal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 16px;
            background: #181825;
            border-bottom: 1px solid #313244;
            color: #cdd6f4;
            font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', monospace;
            font-size: 13px;
        }

        .klytos-terminal-header .title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .klytos-terminal-header .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #a6e3a1;
        }

        .klytos-terminal-header .dot.inactive {
            background: #f38ba8;
        }

        .klytos-terminal-header .help-btn {
            background: #313244;
            border: none;
            color: #cdd6f4;
            padding: 4px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .klytos-terminal-header .help-btn:hover {
            background: #45475a;
        }

        #klytos-terminal {
            flex: 1;
            padding: 8px;
        }

        /* Modal de revalidacion 2FA */
        .klytos-2fa-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .klytos-2fa-modal.active {
            display: flex;
        }

        .klytos-2fa-modal-content {
            background: #1e1e2e;
            border: 1px solid #313244;
            border-radius: 12px;
            padding: 32px;
            max-width: 400px;
            width: 90%;
            color: #cdd6f4;
        }

        .klytos-2fa-modal-content h3 {
            margin: 0 0 16px;
            font-size: 18px;
        }

        .klytos-2fa-modal-content input {
            width: 100%;
            padding: 10px 14px;
            background: #313244;
            border: 1px solid #45475a;
            border-radius: 6px;
            color: #cdd6f4;
            font-size: 18px;
            letter-spacing: 4px;
            text-align: center;
            margin-bottom: 16px;
        }

        .klytos-2fa-modal-content button {
            width: 100%;
            padding: 10px;
            background: #89b4fa;
            border: none;
            border-radius: 6px;
            color: #1e1e2e;
            font-weight: 600;
            cursor: pointer;
        }

        /* Panel de referencia de comandos */
        .klytos-cmd-panel {
            display: none;
            position: absolute;
            top: 0;
            right: 0;
            width: 320px;
            height: 100%;
            background: #181825;
            border-left: 1px solid #313244;
            color: #cdd6f4;
            overflow-y: auto;
            padding: 16px;
            font-family: 'SF Mono', monospace;
            font-size: 12px;
            z-index: 100;
        }

        .klytos-cmd-panel.active {
            display: block;
        }

        .klytos-cmd-panel h4 {
            color: #89b4fa;
            margin: 16px 0 8px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .klytos-cmd-panel .cmd-item {
            padding: 4px 0;
            display: flex;
            gap: 12px;
        }

        .klytos-cmd-panel .cmd-name {
            color: #a6e3a1;
            min-width: 140px;
        }

        .klytos-cmd-panel .cmd-desc {
            color: #6c7086;
        }
    </style>
</head>
<body>
    <div class="klytos-terminal-wrapper" style="position: relative;">
        <div class="klytos-terminal-header">
            <div class="title">
                <span class="dot" id="terminal-status-dot"></span>
                <span>Klytos Terminal</span>
                <span style="color: #6c7086;">v<?= htmlspecialchars($klytosVersion) ?></span>
            </div>
            <div>
                <button class="help-btn" id="toggle-cmd-panel">Comandos</button>
            </div>
        </div>

        <div id="klytos-terminal"></div>

        <!-- Panel lateral de referencia de comandos -->
        <div class="klytos-cmd-panel" id="cmd-panel">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <strong>Referencia rapida</strong>
                <button class="help-btn" id="close-cmd-panel" style="font-size:14px;">X</button>
            </div>
            <p style="color:#6c7086; margin:8px 0;">
                Escribe <span style="color:#a6e3a1;">help &lt;comando&gt;</span> para mas detalles.
            </p>
            <div id="cmd-panel-list">
                <!-- Se rellena dinamicamente via JS -->
            </div>
        </div>
    </div>

    <!-- Modal de revalidacion 2FA -->
    <div class="klytos-2fa-modal" id="revalidation-modal">
        <div class="klytos-2fa-modal-content">
            <h3>Sesion de terminal expirada</h3>
            <p style="color:#6c7086; margin-bottom:16px;">
                Han pasado mas de 10 minutos de inactividad.
                Introduce tu codigo 2FA para continuar.
            </p>
            <input type="text"
                   id="revalidation-code"
                   maxlength="6"
                   placeholder="000000"
                   autocomplete="one-time-code"
                   inputmode="numeric"
                   pattern="[0-9]*" />
            <button id="revalidation-submit">Verificar</button>
        </div>
    </div>

    <!-- xterm.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xterm/5.3.0/xterm.min.js"
            integrity="sha512-..."
            crossorigin="anonymous"></script>

    <!-- Addon fit (para ajustar al contenedor) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xterm/5.3.0/addon-fit.min.js"
            crossorigin="anonymous"></script>

    <script>
    /**
     * Klytos Terminal -- Frontend Controller
     *
     * Maneja la interfaz del pseudo-terminal:
     * - Renderizado con xterm.js
     * - Envio de comandos al backend via fetch
     * - Autocompletado con Tab
     * - Historial de comandos con flechas arriba/abajo
     * - Revalidacion 2FA por inactividad
     * - Panel de referencia de comandos
     */
    (function() {
        'use strict';

        const CSRF_TOKEN = '<?= htmlspecialchars($csrfToken) ?>';
        const API_BASE = '<?= htmlspecialchars(klytos_admin_url('')) ?>';
        const PROMPT = '\x1b[32mklytos\x1b[0m \x1b[90m>\x1b[0m ';

        // Estado
        let currentInput = '';
        let cursorPos = 0;
        let commandHistory = [];
        let historyIndex = -1;
        let isExecuting = false;
        let suggestions = [];
        let pendingCommand = null;

        // Inicializar xterm
        const term = new Terminal({
            cursorBlink: true,
            cursorStyle: 'bar',
            fontSize: 14,
            fontFamily: "'SF Mono', 'Fira Code', 'Cascadia Code', 'Consolas', monospace",
            theme: {
                background: '#1e1e2e',
                foreground: '#cdd6f4',
                cursor: '#f5e0dc',
                selectionBackground: '#45475a',
                black: '#45475a',
                red: '#f38ba8',
                green: '#a6e3a1',
                yellow: '#f9e2af',
                blue: '#89b4fa',
                magenta: '#cba6f7',
                cyan: '#94e2d5',
                white: '#bac2de',
                brightBlack: '#585b70',
                brightRed: '#f38ba8',
                brightGreen: '#a6e3a1',
                brightYellow: '#f9e2af',
                brightBlue: '#89b4fa',
                brightMagenta: '#cba6f7',
                brightCyan: '#94e2d5',
                brightWhite: '#a6adc8',
            },
            allowProposedApi: true,
        });

        // Addon para ajustar tamano al contenedor
        const fitAddon = new FitAddon.FitAddon();
        term.loadAddon(fitAddon);

        // Montar terminal
        const container = document.getElementById('klytos-terminal');
        term.open(container);
        fitAddon.fit();

        // Redimensionar con la ventana
        window.addEventListener('resize', () => fitAddon.fit());

        // Mensaje de bienvenida
        term.writeln('\x1b[34m' +
            '  _  ___       _            ' + '\r\n' +
            ' | |/ / |_   _| |_ ___  ___ ' + '\r\n' +
            ' | \' /| | | | | __/ _ \\/ __|' + '\r\n' +
            ' | . \\| | |_| | || (_) \\__ \\' + '\r\n' +
            ' |_|\\_\\_|\\__, |\\__\\___/|___/' + '\r\n' +
            '         |___/              ' +
            '\x1b[0m'
        );
        term.writeln('');
        term.writeln('\x1b[90m Terminal integrado. Escribe \x1b[32mhelp\x1b[90m para ver los comandos disponibles.\x1b[0m');
        term.writeln('\x1b[90m Pulsa \x1b[33mTab\x1b[90m para autocompletar. Usa las flechas para navegar el historial.\x1b[0m');
        term.writeln('');
        writePrompt();

        // Cargar lista de comandos para autocompletado
        loadCommandList();

        // --- Input handling ---

        term.onKey(({ key, domEvent }) => {
            if (isExecuting) return;

            const code = domEvent.keyCode;
            const ctrlKey = domEvent.ctrlKey;

            // Ctrl+C: cancelar input actual
            if (ctrlKey && code === 67) {
                term.write('^C\r\n');
                currentInput = '';
                cursorPos = 0;
                historyIndex = -1;
                writePrompt();
                return;
            }

            // Ctrl+L: limpiar pantalla
            if (ctrlKey && code === 76) {
                term.clear();
                writePrompt();
                return;
            }

            // Enter: ejecutar comando
            if (code === 13) {
                term.write('\r\n');
                const cmd = currentInput.trim();
                currentInput = '';
                cursorPos = 0;
                historyIndex = -1;

                if (cmd !== '') {
                    // Anadir al historial (evitar duplicados consecutivos)
                    if (commandHistory.length === 0 || commandHistory[commandHistory.length - 1] !== cmd) {
                        commandHistory.push(cmd);
                    }
                    executeCommand(cmd);
                } else {
                    writePrompt();
                }
                return;
            }

            // Backspace
            if (code === 8) {
                if (cursorPos > 0) {
                    currentInput = currentInput.slice(0, cursorPos - 1) + currentInput.slice(cursorPos);
                    cursorPos--;
                    refreshLine();
                }
                return;
            }

            // Delete
            if (code === 46) {
                if (cursorPos < currentInput.length) {
                    currentInput = currentInput.slice(0, cursorPos) + currentInput.slice(cursorPos + 1);
                    refreshLine();
                }
                return;
            }

            // Tab: autocompletar
            if (code === 9) {
                domEvent.preventDefault();
                autocomplete();
                return;
            }

            // Flecha arriba: historial anterior
            if (code === 38) {
                if (commandHistory.length > 0) {
                    if (historyIndex === -1) {
                        historyIndex = commandHistory.length - 1;
                    } else if (historyIndex > 0) {
                        historyIndex--;
                    }
                    currentInput = commandHistory[historyIndex];
                    cursorPos = currentInput.length;
                    refreshLine();
                }
                return;
            }

            // Flecha abajo: historial siguiente
            if (code === 40) {
                if (historyIndex !== -1) {
                    if (historyIndex < commandHistory.length - 1) {
                        historyIndex++;
                        currentInput = commandHistory[historyIndex];
                    } else {
                        historyIndex = -1;
                        currentInput = '';
                    }
                    cursorPos = currentInput.length;
                    refreshLine();
                }
                return;
            }

            // Flecha izquierda
            if (code === 37) {
                if (cursorPos > 0) {
                    cursorPos--;
                    term.write('\x1b[D');
                }
                return;
            }

            // Flecha derecha
            if (code === 39) {
                if (cursorPos < currentInput.length) {
                    cursorPos++;
                    term.write('\x1b[C');
                }
                return;
            }

            // Home
            if (code === 36) {
                while (cursorPos > 0) {
                    cursorPos--;
                    term.write('\x1b[D');
                }
                return;
            }

            // End
            if (code === 35) {
                while (cursorPos < currentInput.length) {
                    cursorPos++;
                    term.write('\x1b[C');
                }
                return;
            }

            // Caracteres imprimibles
            if (key.length === 1 && !ctrlKey) {
                currentInput = currentInput.slice(0, cursorPos) + key + currentInput.slice(cursorPos);
                cursorPos++;
                refreshLine();
            }
        });

        // --- Funciones ---

        function writePrompt() {
            term.write(PROMPT);
        }

        function refreshLine() {
            // Borrar linea actual y reescribir
            term.write('\r\x1b[K');
            term.write(PROMPT + currentInput);
            // Mover cursor a posicion correcta
            const diff = currentInput.length - cursorPos;
            if (diff > 0) {
                term.write('\x1b[' + diff + 'D');
            }
        }

        async function executeCommand(cmd) {
            // Comando clear se maneja localmente
            if (cmd.toLowerCase() === 'clear') {
                term.clear();
                writePrompt();
                return;
            }

            isExecuting = true;
            document.getElementById('terminal-status-dot').classList.add('inactive');

            try {
                const response = await fetch(API_BASE + '?route=api/terminal', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Klytos-CSRF': CSRF_TOKEN,
                    },
                    body: JSON.stringify({ command: cmd }),
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    term.writeln('\x1b[31mError: ' + (errorData.error || 'Error del servidor') + '\x1b[0m');
                    writePrompt();
                    return;
                }

                const data = await response.json();

                // Requiere revalidacion 2FA
                if (data.requires_2fa) {
                    pendingCommand = cmd;
                    showRevalidationModal();
                    return;
                }

                // Mostrar salida
                if (data.output && data.output !== '__CLEAR__') {
                    // Reemplazar \n por \r\n para xterm
                    const lines = data.output.split('\n');
                    lines.forEach(line => {
                        if (data.success) {
                            term.writeln(line);
                        } else {
                            term.writeln('\x1b[31m' + line + '\x1b[0m');
                        }
                    });
                }

                if (data.output === '__CLEAR__') {
                    term.clear();
                }

            } catch (err) {
                term.writeln('\x1b[31mError de conexion: ' + err.message + '\x1b[0m');
            } finally {
                isExecuting = false;
                document.getElementById('terminal-status-dot').classList.remove('inactive');
                writePrompt();
            }
        }

        async function loadCommandList() {
            try {
                const response = await fetch(API_BASE + '?route=api/terminal-autocomplete&q=', {
                    headers: { 'X-Klytos-CSRF': CSRF_TOKEN },
                });
                const data = await response.json();
                suggestions = data.suggestions || [];

                // Rellenar panel lateral
                populateCommandPanel();
            } catch (e) {
                // Silenciar error, el autocompletado simplemente no funcionara
            }
        }

        function autocomplete() {
            const input = currentInput.toLowerCase();
            if (!input) return;

            const matches = suggestions.filter(s => s.startsWith(input));

            if (matches.length === 0) return;

            if (matches.length === 1) {
                // Autocompletar directamente
                currentInput = matches[0] + ' ';
                cursorPos = currentInput.length;
                refreshLine();
            } else {
                // Mostrar opciones
                term.write('\r\n');
                term.writeln(matches.map(m => '\x1b[32m' + m + '\x1b[0m').join('   '));
                writePrompt();
                term.write(currentInput);
                // Autocompletar hasta el prefijo comun
                const common = commonPrefix(matches);
                if (common.length > input.length) {
                    currentInput = common;
                    cursorPos = currentInput.length;
                    refreshLine();
                }
            }
        }

        function commonPrefix(strings) {
            if (strings.length === 0) return '';
            let prefix = strings[0];
            for (let i = 1; i < strings.length; i++) {
                while (!strings[i].startsWith(prefix)) {
                    prefix = prefix.slice(0, -1);
                }
            }
            return prefix;
        }

        function populateCommandPanel() {
            // Obtener la lista completa desde el endpoint de ayuda
            // (simplificado: usamos las suggestions ya cargadas)
            const panel = document.getElementById('cmd-panel-list');
            if (!panel) return;

            const categories = {};
            suggestions.forEach(cmd => {
                const cat = cmd.includes(':') ? cmd.split(':')[0] : 'general';
                if (!categories[cat]) categories[cat] = [];
                categories[cat].push(cmd);
            });

            let html = '';
            const labels = {
                general: 'General',
                build: 'Build',
                pages: 'Contenido',
                tasks: 'Contenido',
                cache: 'Sistema',
                cron: 'Sistema',
                plugins: 'Plugins',
            };

            Object.keys(categories).forEach(cat => {
                const label = labels[cat] || cat.charAt(0).toUpperCase() + cat.slice(1);
                html += '<h4>' + label + '</h4>';
                categories[cat].forEach(cmd => {
                    html += '<div class="cmd-item"><span class="cmd-name">' + cmd + '</span></div>';
                });
            });

            panel.innerHTML = html;
        }

        // --- Revalidacion 2FA ---

        function showRevalidationModal() {
            const modal = document.getElementById('revalidation-modal');
            modal.classList.add('active');
            document.getElementById('revalidation-code').value = '';
            document.getElementById('revalidation-code').focus();
        }

        document.getElementById('revalidation-submit').addEventListener('click', async () => {
            const code = document.getElementById('revalidation-code').value.trim();
            if (!code) return;

            try {
                const response = await fetch(API_BASE + '?route=api/terminal-revalidate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Klytos-CSRF': CSRF_TOKEN,
                    },
                    body: JSON.stringify({ code, method: 'totp' }),
                });

                const data = await response.json();

                if (data.success) {
                    document.getElementById('revalidation-modal').classList.remove('active');
                    // Reintentar el comando pendiente
                    if (pendingCommand) {
                        const cmd = pendingCommand;
                        pendingCommand = null;
                        executeCommand(cmd);
                    }
                } else {
                    document.getElementById('revalidation-code').value = '';
                    document.getElementById('revalidation-code').style.borderColor = '#f38ba8';
                    document.getElementById('revalidation-code').focus();
                }
            } catch (e) {
                term.writeln('\x1b[31mError de conexion durante revalidacion.\x1b[0m');
                document.getElementById('revalidation-modal').classList.remove('active');
                isExecuting = false;
                writePrompt();
            }
        });

        // Enter en el campo de codigo 2FA
        document.getElementById('revalidation-code').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                document.getElementById('revalidation-submit').click();
            }
        });

        // --- Panel de comandos ---

        document.getElementById('toggle-cmd-panel').addEventListener('click', () => {
            document.getElementById('cmd-panel').classList.toggle('active');
        });

        document.getElementById('close-cmd-panel').addEventListener('click', () => {
            document.getElementById('cmd-panel').classList.remove('active');
        });

    })();
    </script>
</body>
</html>
```

---

## 5. Integracion con el router del admin

### 5.1. Modificacion del router admin

En el router del admin (el archivo que despacha las paginas del panel), anadir la ruta para el terminal:

**Archivo a modificar:** El router del admin que gestiona `?route=X`

```php
// Dentro del switch/match de rutas del admin, anadir:

case 'terminal':
    // Verificar 2FA antes de servir la pagina
    $currentUser = klytos_current_user();
    if (empty($currentUser['two_factor']['enabled'])) {
        // Redirigir a dashboard con mensaje
        header('Location: ' . klytos_admin_url('dashboard') . '&notice=terminal_requires_2fa');
        exit;
    }
    if (!klytos_has_permission('manage_system')) {
        http_response_code(403);
        include __DIR__ . '/templates/403.php';
        exit;
    }
    include __DIR__ . '/templates/terminal.php';
    break;

case 'api/terminal':
    include __DIR__ . '/api/terminal.php';
    break;

case 'api/terminal-autocomplete':
    include __DIR__ . '/api/terminal-autocomplete.php';
    break;

case 'api/terminal-revalidate':
    include __DIR__ . '/api/terminal-revalidate.php';
    break;
```

---

## 6. Extension por plugins

### 6.1. Como un plugin registra comandos propios

Los plugins pueden anadir comandos al terminal de dos formas:

**Forma 1: Via filtro (en init.php del plugin)**

```php
klytos_add_filter('terminal.commands', function (array $commands): array {
    $commands['products'] = [
        'description' => 'Listar todos los productos',
        'usage'       => 'products [--status=published|draft]',
        'category'    => 'ecommerce',
        'permission'  => 'manage_content',
        'handler'     => function (array $args, array $flags) {
            $status = $flags['status'] ?? null;
            $products = klytos_storage()->find('products', $status ? ['status' => $status] : []);
            $output = "Productos (" . count($products) . "):\n\n";
            foreach ($products as $product) {
                $output .= "  [{$product['status']}] {$product['title']} -- {$product['price']}EUR\n";
            }
            return $output;
        },
    ];

    $commands['orders'] = [
        'description' => 'Listar pedidos recientes',
        'usage'       => 'orders [--limit=20]',
        'category'    => 'ecommerce',
        'permission'  => 'manage_orders',
        'handler'     => function (array $args, array $flags) {
            $limit = (int) ($flags['limit'] ?? 20);
            $orders = klytos_storage()->find('orders', [], ['sort' => ['created_at' => -1], 'limit' => $limit]);
            $output = "Ultimos {$limit} pedidos:\n\n";
            foreach ($orders as $order) {
                $total = number_format($order['total'] ?? 0, 2);
                $status = $order['status'] ?? 'pending';
                $output .= "  #{$order['order_number']} [{$status}] {$total}EUR -- {$order['customer_email']}\n";
            }
            return $output;
        },
    ];

    $commands['stock:update'] = [
        'description' => 'Actualizar stock de un producto',
        'usage'       => 'stock:update <product-slug> <quantity>',
        'category'    => 'ecommerce',
        'permission'  => 'manage_content',
        'handler'     => function (array $args, array $flags) {
            if (count($args) < 2) {
                return "Uso: stock:update <product-slug> <cantidad>\nEjemplo: stock:update camiseta-azul 50";
            }
            $slug = $args[0];
            $quantity = (int) $args[1];
            $storage = klytos_storage();
            $product = $storage->findOne('products', ['slug' => $slug]);
            if (!$product) {
                return "Producto no encontrado: {$slug}";
            }
            $storage->update('products', ['slug' => $slug], ['stock' => $quantity]);
            return "Stock de '{$product['title']}' actualizado a {$quantity} unidades.";
        },
    ];

    return $commands;
});
```

**Forma 2: Via metodo directo del TerminalExecutor**

```php
// En el init.php del plugin, tras klytos.init
klytos_add_action('klytos.init', function () {
    $app = klytos_app();
    // Solo si el TerminalExecutor esta inicializado (no en CLI)
    if (method_exists($app, 'getTerminalExecutor')) {
        $executor = $app->getTerminalExecutor();
        if ($executor) {
            $executor->registerCommand('shipping:rates', [
                'description' => 'Mostrar tarifas de envio configuradas',
                'usage'       => 'shipping:rates',
                'category'    => 'ecommerce',
                'permission'  => 'manage_settings',
                'handler'     => function () {
                    // ...
                    return "Tarifas de envio:\n  ...";
                },
            ]);
        }
    }
});
```

### 6.2. Categorias de plugins en el panel de ayuda

Cuando un plugin registra comandos con una categoria personalizada (como 'ecommerce'), el panel de referencia y el comando `help` los agrupan automaticamente bajo esa categoria. El `categoryLabels` del help se extiende via filtro:

```php
// El plugin puede registrar su etiqueta de categoria
klytos_add_filter('terminal.category_labels', function (array $labels): array {
    $labels['ecommerce'] = 'E-Commerce';
    return $labels;
});
```

---

## 7. Resumen de archivos

### 7.1. Archivos a crear

| Archivo | Descripcion |
|---------|-------------|
| `core/terminal-executor.php` | Clase TerminalExecutor: parseo, validacion, ejecucion de comandos |
| `admin/api/terminal.php` | Endpoint POST para ejecutar comandos via AJAX |
| `admin/api/terminal-autocomplete.php` | Endpoint GET para autocompletado |
| `admin/api/terminal-revalidate.php` | Endpoint POST para revalidacion 2FA |
| `admin/templates/terminal.php` | Pagina del terminal con xterm.js |

### 7.2. Archivos a modificar

| Archivo | Cambio |
|---------|--------|
| `admin/templates/sidebar.php` | Anadir item "Terminal" condicional a 2FA + manage_system |
| Router del admin | Anadir rutas: terminal, api/terminal, api/terminal-autocomplete, api/terminal-revalidate |
| `core/app.php` | Anadir propiedad `$terminalExecutor` y getter lazy `getTerminalExecutor()` |

### 7.3. Dependencias externas

| Libreria | Version | CDN | Uso |
|----------|---------|-----|-----|
| xterm.js | 5.3.0 | cdnjs.cloudflare.com | Renderizado del terminal en el navegador |
| xterm-addon-fit | 5.3.0 | cdnjs.cloudflare.com | Ajuste automatico del tamano al contenedor |

---

## 8. Orden de implementacion

1. **Crear `core/terminal-executor.php`** -- La clase central. No depende de nada nuevo, solo del App existente.
2. **Crear `admin/api/terminal.php`** -- El endpoint. Necesita el TerminalExecutor.
3. **Crear `admin/api/terminal-autocomplete.php`** -- Endpoint simple de lectura.
4. **Crear `admin/api/terminal-revalidate.php`** -- Endpoint de revalidacion 2FA.
5. **Modificar `core/app.php`** -- Anadir getter para TerminalExecutor.
6. **Modificar el router del admin** -- Registrar las 4 rutas nuevas.
7. **Modificar `admin/templates/sidebar.php`** -- Anadir item Terminal condicional.
8. **Crear `admin/templates/terminal.php`** -- La pagina frontend con xterm.js.
9. **Probar** -- Verificar flujo completo: menu > terminal > comando > salida > 2FA revalidation.

---

## 9. Consideraciones de hosting

### 9.1. Compatibilidad

El pseudo-terminal **no usa ninguna funcion de ejecucion de sistema**:

- No usa `exec()`
- No usa `shell_exec()`
- No usa `proc_open()`
- No usa `passthru()`
- No usa `system()`
- No usa `popen()`

Todo se ejecuta internamente en PHP. Esto significa que funciona en:

- Shared hosting (incluso con `disable_functions` restrictivo)
- VPS y dedicados
- Docker containers
- Cualquier entorno donde Klytos ya funcione

### 9.2. Rendimiento

Cada comando es una peticion HTTP normal (POST con JSON). No hay WebSocket ni conexion persistente. Esto simplifica enormemente la implementacion y la compatibilidad.

Para comandos que tarden mas (como `build` en un sitio con miles de paginas), el timeout de PHP del servidor aplicara normalmente. El frontend muestra el indicador de "ejecutando" (dot rojo) mientras espera la respuesta.

### 9.3. Limites

- Longitud maxima de comando: 256 caracteres
- Rate limit: 30 comandos/minuto/usuario
- Timeout de revalidacion 2FA: 10 minutos de inactividad
- Sin WebSocket: cada comando es una peticion HTTP independiente
