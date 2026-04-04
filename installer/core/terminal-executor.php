<?php

/**
 * Klytos -- Terminal Executor
 * Pseudo-terminal that executes exclusively Klytos CLI commands.
 *
 * Does NOT use exec(), shell_exec(), proc_open(), passthru(), system()
 * or any function that invokes operating system processes. Everything runs
 * internally in PHP by calling the same functions that cli.php uses.
 *
 * @package Klytos
 * @since   0.12.0
 *
 * @license    Elastic License 2.0 (ELv2) -- https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 Jose Conti -- https://plugins.joseconti.com -- https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

namespace Klytos\Core;

class TerminalExecutor
{
    private App $app;

    /**
     * Registered commands.
     * Key: command name (e.g. 'build', 'cache:clear')
     * Value: array with description, usage, handler, permission, category.
     *
     * @var array<string, array>
     */
    private array $commands = [];

    /**
     * Session command history (persisted to storage).
     *
     * @var array<int, array{command: string, output: string, timestamp: int}>
     */
    private array $sessionHistory = [];

    /** @var int Maximum number of history entries to persist. */
    private const MAX_HISTORY = 100;

    /** @var string Storage key for persistent history. */
    private const HISTORY_KEY = 'terminal-history';

    public function __construct( App $app )
    {
        $this->app = $app;
        $this->registerCoreCommands();

        // Allow plugins to register additional commands.
        // Plugins: klytos_add_filter('terminal.commands', fn($cmds) => [...])
        $this->commands = klytos_apply_filters( 'terminal.commands', $this->commands );

        // Load persistent history.
        $this->loadHistory();
    }

    /**
     * Load command history from persistent storage.
     */
    private function loadHistory(): void
    {
        try {
            $data = $this->app->getStorage()->read( self::HISTORY_KEY );
            $this->sessionHistory = $data['entries'] ?? [];
        } catch ( \RuntimeException $e ) {
            $this->sessionHistory = [];
        }
    }

    /**
     * Save current history to persistent storage.
     */
    private function saveHistory(): void
    {
        // Keep only the last MAX_HISTORY entries.
        if ( count( $this->sessionHistory ) > self::MAX_HISTORY ) {
            $this->sessionHistory = array_slice( $this->sessionHistory, -self::MAX_HISTORY );
        }

        $this->app->getStorage()->write( self::HISTORY_KEY, [
            'entries'    => $this->sessionHistory,
            'updated_at' => Helpers::now(),
        ] );
    }

    /**
     * Get the persistent command history.
     *
     * @param  int $limit Maximum entries to return (0 = all).
     * @return array
     */
    public function getHistory( int $limit = 50 ): array
    {
        if ( $limit > 0 ) {
            return array_slice( $this->sessionHistory, -$limit );
        }
        return $this->sessionHistory;
    }

    /**
     * Dispatch a command directly by name.
     *
     * This is the core execution method used by both the web terminal
     * (via execute()) and the CLI (via cli.php). It runs the command
     * handler without web-specific layers (rate limiting, 2FA, session,
     * history persistence, audit logging).
     *
     * @param  string               $commandName Command name (e.g. 'build', 'backup:list').
     * @param  array<int, string>   $args        Positional arguments.
     * @param  array<string, string> $flags       Named flags (e.g. ['period' => '30d']).
     * @return array{success: bool, output: string}
     */
    public function dispatch( string $commandName, array $args = [], array $flags = [] ): array
    {
        $commandName = strtolower( $commandName );

        // 1. Verify command exists.
        if ( ! isset( $this->commands[ $commandName ] ) ) {
            $suggestion = $this->suggestCommand( $commandName );
            $output     = "Comando no reconocido: {$commandName}";
            if ( $suggestion ) {
                $output .= "\nQuizas quisiste decir: {$suggestion}";
            }
            $output .= "\nEscribe 'help' para ver los comandos disponibles.";

            return [ 'success' => false, 'output' => $output ];
        }

        // 2. Execute.
        try {
            $cmdConfig = $this->commands[ $commandName ];

            klytos_do_action( 'terminal.before_execute', $commandName, $args );

            ob_start();
            $handler  = $cmdConfig['handler'];
            $result   = $handler( $args, $flags, $this );
            $buffered = ob_get_clean();

            $output = is_string( $result ) ? $result : $buffered;

            klytos_do_action( 'terminal.after_execute', $commandName, $output );
            $output = klytos_apply_filters( 'terminal.command_output', $output, $commandName );

            return [ 'success' => true, 'output' => $output ];
        } catch ( \Throwable $e ) {
            ob_end_clean();
            return [ 'success' => false, 'output' => 'Error: ' . $e->getMessage() ];
        }
    }

    /**
     * Execute a command from the web terminal.
     *
     * Wraps dispatch() with web-specific security: input sanitization,
     * rate limiting, 2FA revalidation, permission checks, history
     * persistence, and audit logging.
     *
     * @param string $input  Command as typed by the user.
     * @param string $userId ID of the user executing the command.
     * @return array{
     *   success: bool,
     *   output: string,
     *   command: string,
     *   timestamp: int,
     *   requires_2fa: bool
     * }
     */
    public function execute( string $input, string $userId ): array
    {
        $timestamp = time();

        // 1. Sanitize.
        $clean = $this->sanitizeInput( $input );

        if ( $clean === '' ) {
            return [
                'success'      => false,
                'output'       => '',
                'command'      => '',
                'timestamp'    => $timestamp,
                'requires_2fa' => false,
            ];
        }

        // 2. Rate limiting.
        if ( ! $this->checkRateLimit( $userId ) ) {
            return [
                'success'      => false,
                'output'       => 'Demasiadas peticiones. Espera unos segundos.',
                'command'      => $clean,
                'timestamp'    => $timestamp,
                'requires_2fa' => false,
            ];
        }

        // 3. 2FA revalidation after inactivity.
        if ( $this->checkRevalidation() ) {
            return [
                'success'      => false,
                'output'       => '',
                'command'      => $clean,
                'timestamp'    => $timestamp,
                'requires_2fa' => true,
            ];
        }

        // 4. Parse command and arguments.
        $parsed      = $this->parseCommand( $clean );
        $commandName = $parsed['command'];
        $args        = $parsed['args'];
        $flags       = $parsed['flags'];

        // 5. Verify command-specific permissions.
        if ( isset( $this->commands[ $commandName ] ) ) {
            $cmdConfig = $this->commands[ $commandName ];
            if ( ! empty( $cmdConfig['permission'] ) ) {
                if ( ! klytos_has_permission( $cmdConfig['permission'] ) ) {
                    return [
                        'success'      => false,
                        'output'       => 'No tienes permiso para ejecutar este comando.',
                        'command'      => $clean,
                        'timestamp'    => $timestamp,
                        'requires_2fa' => false,
                    ];
                }
            }
        }

        // 6. Dispatch command.
        $result = $this->dispatch( $commandName, $args, $flags );

        // 7. Update last command timestamp.
        if ( isset( $_SESSION ) ) {
            $_SESSION['klytos_terminal_last_command'] = $timestamp;
        }

        // 8. Persist history.
        $this->sessionHistory[] = [
            'command'   => $clean,
            'output'    => mb_substr( $result['output'], 0, 2000 ),
            'timestamp' => $timestamp,
        ];
        $this->saveHistory();

        // 9. Audit log.
        klytos_log( $result['success'] ? 'info' : 'error', 'terminal.command', [
            'user_id' => $userId,
            'command' => $clean,
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'result'  => $result['success'] ? 'ok' : 'error',
        ] );

        return [
            'success'      => $result['success'],
            'output'       => $result['output'],
            'command'      => $clean,
            'timestamp'    => $timestamp,
            'requires_2fa' => false,
        ];
    }

    /**
     * Parse a command string into name, arguments and flags.
     *
     * Examples:
     *   "build"                  => {command: "build", args: [], flags: []}
     *   "build:page mi-pagina"   => {command: "build:page", args: ["mi-pagina"], flags: []}
     *   "analytics --period=30d" => {command: "analytics", args: [], flags: {period: "30d"}}
     *   "klytos build"           => {command: "build", args: [], flags: []}
     *
     * @return array{command: string, args: string[], flags: array<string, string>}
     */
    private function parseCommand( string $input ): array
    {
        $parts = preg_split( '/\s+/', $input );
        $parts = array_values( array_filter( $parts, fn( $p ) => $p !== '' ) );

        if ( empty( $parts ) ) {
            return [ 'command' => '', 'args' => [], 'flags' => [] ];
        }

        // If the user types "klytos build", ignore "klytos".
        if ( strtolower( $parts[0] ) === 'klytos' ) {
            array_shift( $parts );
        }

        if ( empty( $parts ) ) {
            return [ 'command' => 'help', 'args' => [], 'flags' => [] ];
        }

        $command = strtolower( array_shift( $parts ) );
        $args    = [];
        $flags   = [];

        foreach ( $parts as $part ) {
            if ( str_starts_with( $part, '--' ) ) {
                $flag = substr( $part, 2 );
                if ( str_contains( $flag, '=' ) ) {
                    [ $key, $value ] = explode( '=', $flag, 2 );
                    $flags[ $key ]   = $value;
                } else {
                    $flags[ $flag ] = 'true';
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
     * Register all core commands.
     * These are the same commands exposed by cli.php but executed internally.
     */
    private function registerCoreCommands(): void
    {
        // --- Category: Build ---

        $this->commands['build'] = [
            'description' => 'Regenerar todo el sitio estatico',
            'usage'       => 'build',
            'category'    => 'build',
            'permission'  => 'build.run',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $engine = new BuildEngine( $this->app );
                $result = $engine->buildAll();
                $count  = $result['pages_built'] ?? 0;
                return "Sitio regenerado correctamente. {$count} paginas construidas.";
            },
        ];

        $this->commands['build:page'] = [
            'description' => 'Regenerar una pagina especifica por su slug',
            'usage'       => 'build:page <slug>',
            'category'    => 'build',
            'permission'  => 'build.run',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                if ( empty( $args[0] ) ) {
                    return "Uso: build:page <slug>\nEjemplo: build:page mi-pagina";
                }
                $slug   = $args[0];
                $engine = new BuildEngine( $this->app );
                $result = $engine->buildPage( $slug );
                if ( ! empty( $result['success'] ) ) {
                    return "Pagina '{$slug}' regenerada correctamente.";
                }
                return "Error: no se encontro la pagina '{$slug}'.";
            },
        ];

        // --- Category: Content ---

        $this->commands['pages'] = [
            'description' => 'Listar todas las paginas',
            'usage'       => 'pages [--status=all|published|draft|archived]',
            'category'    => 'content',
            'permission'  => 'pages.view',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $status = $flags['status'] ?? 'all';
                $pages  = $this->app->getPages()->list( $status );
                if ( empty( $pages ) ) {
                    return 'No hay paginas.';
                }
                $output = 'Paginas (' . count( $pages ) . "):\n\n";
                foreach ( $pages as $page ) {
                    $st    = $page['status'] ?? 'draft';
                    $slug  = $page['slug'] ?? '(sin slug)';
                    $title = $page['title'] ?? '(sin titulo)';
                    $output .= "  [{$st}] /{$slug} -- {$title}\n";
                }
                return $output;
            },
        ];

        $this->commands['pages:count'] = [
            'description' => 'Mostrar numero total de paginas por estado',
            'usage'       => 'pages:count',
            'category'    => 'content',
            'permission'  => 'pages.view',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $total     = $this->app->getPages()->count( 'all' );
                $published = $this->app->getPages()->count( 'published' );
                $drafts    = $this->app->getPages()->count( 'draft' );
                return "Total: {$total} | Publicadas: {$published} | Borradores: {$drafts}";
            },
        ];

        $this->commands['tasks'] = [
            'description' => 'Listar tareas pendientes',
            'usage'       => 'tasks [--status=all|pending|completed]',
            'category'    => 'content',
            'permission'  => 'tasks.manage',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $status = $flags['status'] ?? 'all';
                $tasks  = $this->app->getTaskManager()->list( $status );
                if ( empty( $tasks ) ) {
                    return 'No hay tareas.';
                }
                $output = 'Tareas (' . count( $tasks ) . "):\n\n";
                foreach ( $tasks as $task ) {
                    $st    = $task['status'] ?? 'pending';
                    $title = $task['title'] ?? '(sin titulo)';
                    $output .= "  [{$st}] {$title}\n";
                }
                return $output;
            },
        ];

        $this->commands['tasks:count'] = [
            'description' => 'Mostrar numero total de tareas',
            'usage'       => 'tasks:count',
            'category'    => 'content',
            'permission'  => 'tasks.manage',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $all       = $this->app->getTaskManager()->list( 'all' );
                $pending   = $this->app->getTaskManager()->list( 'pending' );
                $completed = $this->app->getTaskManager()->list( 'completed' );
                return 'Total: ' . count( $all ) . ' | Pendientes: ' . count( $pending ) . ' | Completadas: ' . count( $completed );
            },
        ];

        // --- Category: System ---

        $this->commands['status'] = [
            'description' => 'Estado general del sistema',
            'usage'       => 'status',
            'category'    => 'system',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $version  = klytos_version();
                $config   = $this->app->getConfig();
                $siteName = $config['site_name'] ?? 'Sin nombre';
                $pages    = $this->app->getPages()->count( 'all' );

                $output  = "Klytos v{$version}\n";
                $output .= "Sitio: {$siteName}\n";
                $output .= "Paginas: {$pages}\n";
                $output .= 'PHP: ' . PHP_VERSION . "\n";
                $output .= 'Servidor: ' . PHP_SAPI . "\n";
                return $output;
            },
        ];

        $this->commands['version'] = [
            'description' => 'Mostrar version de Klytos',
            'usage'       => 'version',
            'category'    => 'system',
            'permission'  => null,
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                return 'Klytos v' . klytos_version();
            },
        ];

        $this->commands['cache:clear'] = [
            'description' => 'Limpiar caches del sistema (rate limits, cron)',
            'usage'       => 'cache:clear',
            'category'    => 'system',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $storage = klytos_storage();
                $items   = $storage->list( 'rate-limits' );
                foreach ( $items as $item ) {
                    $id = $item['_id'] ?? $item['id'] ?? '';
                    if ( $id !== '' ) {
                        $storage->delete( 'rate-limits', (string) $id );
                    }
                }
                return 'Caches limpiadas correctamente.';
            },
        ];

        $this->commands['cron:run'] = [
            'description' => 'Ejecutar tareas programadas pendientes',
            'usage'       => 'cron:run',
            'category'    => 'system',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $result   = $this->app->getActionScheduler()->processQueue();
                $executed = $result['processed'] ?? 0;
                $failed   = $result['failed'] ?? 0;
                $output   = "Tareas ejecutadas: {$executed}";
                if ( $failed > 0 ) {
                    $output .= " | Fallidas: {$failed}";
                }
                return $output;
            },
        ];

        // --- Category: Users ---

        $this->commands['users'] = [
            'description' => 'Listar usuarios del admin',
            'usage'       => 'users',
            'category'    => 'users',
            'permission'  => 'users.manage',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $users = $this->app->getUserManager()->list();
                if ( empty( $users ) ) {
                    return 'No hay usuarios registrados.';
                }
                $output = 'Usuarios (' . count( $users ) . "):\n\n";
                foreach ( $users as $user ) {
                    $name  = $user['display_name'] ?? $user['username'] ?? '(sin nombre)';
                    $role  = $user['role'] ?? 'user';
                    $twofa = ! empty( $user['two_factor']['enabled'] ) ? '[2FA]' : '[---]';
                    $output .= "  {$twofa} {$name} ({$role})\n";
                }
                return $output;
            },
        ];

        // --- Category: Plugins ---

        $this->commands['plugins'] = [
            'description' => 'Listar plugins instalados y su estado',
            'usage'       => 'plugins',
            'category'    => 'plugins',
            'permission'  => 'plugins.manage',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $loader     = $this->app->getPluginLoader();
                $plugins    = $loader->discoverPlugins();
                if ( empty( $plugins ) ) {
                    return 'No hay plugins instalados.';
                }
                $activeList = $loader->getActivePlugins();
                $activeIds  = array_keys( $activeList );
                $output     = 'Plugins instalados (' . count( $plugins ) . "):\n\n";
                foreach ( $plugins as $id => $manifest ) {
                    $name    = $manifest['name'] ?? $id;
                    $version = $manifest['version'] ?? '?';
                    $active  = in_array( $id, $activeIds, true ) ? 'activo' : 'inactivo';
                    $output .= "  [{$active}] {$name} v{$version} ({$id})\n";
                }
                return $output;
            },
        ];

        $this->commands['plugins:activate'] = [
            'description' => 'Activar un plugin por su ID',
            'usage'       => 'plugins:activate <plugin-id>',
            'category'    => 'plugins',
            'permission'  => 'plugins.manage',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                if ( empty( $args[0] ) ) {
                    return "Uso: plugins:activate <plugin-id>\nEjemplo: plugins:activate klytos-ecommerce";
                }
                $loader = $this->app->getPluginLoader();
                $result = $loader->activate( $args[0] );
                if ( $result['success'] ?? false ) {
                    return "Plugin '{$args[0]}' activado correctamente.";
                }
                return 'Error al activar plugin: ' . ( $result['error'] ?? 'desconocido' );
            },
        ];

        $this->commands['plugins:deactivate'] = [
            'description' => 'Desactivar un plugin por su ID',
            'usage'       => 'plugins:deactivate <plugin-id>',
            'category'    => 'plugins',
            'permission'  => 'plugins.manage',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                if ( empty( $args[0] ) ) {
                    return 'Uso: plugins:deactivate <plugin-id>';
                }
                $loader = $this->app->getPluginLoader();
                $result = $loader->deactivate( $args[0] );
                if ( $result['success'] ?? false ) {
                    return "Plugin '{$args[0]}' desactivado correctamente.";
                }
                return 'Error al desactivar plugin: ' . ( $result['error'] ?? 'desconocido' );
            },
        ];

        // --- Category: Analytics ---

        $this->commands['analytics'] = [
            'description' => 'Mostrar resumen de analiticas',
            'usage'       => 'analytics [--period=7d|30d|90d]',
            'category'    => 'system',
            'permission'  => 'analytics.view',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $period = $flags['period'] ?? '7d';

                // Parse period into date range.
                $days = 7;
                if ( preg_match( '/^(\d+)d$/', $period, $m ) ) {
                    $days = (int) $m[1];
                }
                $dateTo   = klytos_gmdate( 'Y-m-d' );
                $dateFrom = klytos_gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

                $analytics = $this->app->getAnalyticsManager();
                $data      = $analytics->getSummary( $dateFrom, $dateTo );

                $output  = "Analiticas (periodo: {$period}):\n\n";
                $output .= '  Visitas totales: ' . ( $data['total_views'] ?? 0 ) . "\n";
                $output .= '  Visitantes unicos: ' . ( $data['unique_visitors'] ?? 0 ) . "\n";
                return $output;
            },
        ];

        // --- Special command: help ---

        $this->commands['help'] = [
            'description' => 'Mostrar esta ayuda',
            'usage'       => 'help [comando]',
            'category'    => 'general',
            'permission'  => null,
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                // Help for a specific command.
                if ( ! empty( $args[0] ) ) {
                    $cmdName = strtolower( $args[0] );
                    if ( isset( $this->commands[ $cmdName ] ) ) {
                        $cmd     = $this->commands[ $cmdName ];
                        $output  = "{$cmdName} -- {$cmd['description']}\n\n";
                        $output .= "Uso: {$cmd['usage']}\n";
                        return $output;
                    }
                    return "Comando no encontrado: {$cmdName}";
                }

                // List all commands grouped by category.
                $categories = [];
                foreach ( $this->commands as $name => $config ) {
                    $cat                      = $config['category'] ?? 'general';
                    $categories[ $cat ][ $name ] = $config;
                }

                $categoryLabels = klytos_apply_filters( 'terminal.category_labels', [
                    'general' => 'General',
                    'build'   => 'Build',
                    'content' => 'Contenido',
                    'system'  => 'Sistema',
                    'users'   => 'Usuarios',
                    'plugins' => 'Plugins',
                ] );

                $output = 'Klytos Terminal v' . klytos_version() . "\n";
                $output .= "Comandos disponibles:\n\n";

                foreach ( $categories as $cat => $cmds ) {
                    $label  = $categoryLabels[ $cat ] ?? ucfirst( $cat );
                    $output .= "  {$label}:\n";
                    foreach ( $cmds as $name => $config ) {
                        $desc    = $config['description'];
                        $padding = max( 1, 24 - strlen( $name ) );
                        $output .= '    ' . $name . str_repeat( ' ', $padding ) . $desc . "\n";
                    }
                    $output .= "\n";
                }

                $output .= "Escribe 'help <comando>' para mas detalles sobre un comando.\n";
                $output .= "Los plugins pueden anadir comandos adicionales.\n";
                return $output;
            },
        ];

        // --- Special command: clear ---

        $this->commands['clear'] = [
            'description' => 'Limpiar la pantalla del terminal',
            'usage'       => 'clear',
            'category'    => 'general',
            'permission'  => null,
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                // Special marker that the frontend interprets.
                return '__CLEAR__';
            },
        ];

        // --- Category: Backup ---

        $this->commands['backup:create'] = [
            'description' => 'Crear un backup manual del sistema',
            'usage'       => 'backup:create [--label=<nombre>]',
            'category'    => 'backup',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $label  = $flags['label'] ?? '';
                $result = $this->app->getUpdater()->createManualBackup( $label );
                if ( $result['success'] ) {
                    return "Backup creado: {$result['backup']}";
                }
                return 'Error al crear el backup.';
            },
        ];

        $this->commands['backup:list'] = [
            'description' => 'Listar backups disponibles',
            'usage'       => 'backup:list',
            'category'    => 'backup',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $backups = $this->app->getUpdater()->listBackups();
                if ( empty( $backups ) ) {
                    return 'No hay backups disponibles.';
                }
                $output = "Backups (" . count( $backups ) . "):\n\n";
                foreach ( $backups as $b ) {
                    $date = klytos_gmdate( 'Y-m-d H:i', $b['date'] );
                    $type = $b['type'] === 'manual' ? '[MANUAL]' : '[UPDATE]';
                    $output .= "  {$type} {$b['name']}  ({$date})\n";
                }
                return $output;
            },
        ];

        $this->commands['backup:restore'] = [
            'description' => 'Restaurar un backup por su nombre',
            'usage'       => 'backup:restore <nombre-del-backup>',
            'category'    => 'backup',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                if ( empty( $args[0] ) ) {
                    return "Uso: backup:restore <nombre-del-backup>\nEjecuta 'backup:list' para ver los disponibles.";
                }
                $result = $this->app->getUpdater()->restoreFromBackup( $args[0] );
                if ( $result['success'] ?? false ) {
                    return "Backup restaurado correctamente desde '{$args[0]}'.";
                }
                return 'Error: ' . ( $result['error'] ?? 'No se pudo restaurar el backup.' );
            },
        ];

        // --- Category: Update ---

        $this->commands['update:check'] = [
            'description' => 'Comprobar si hay una nueva version de Klytos',
            'usage'       => 'update:check',
            'category'    => 'update',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $updater = $this->app->getUpdater();
                $current = $updater->getCurrentVersion();
                $update  = $updater->checkForUpdate( true );
                if ( $update === null ) {
                    return "Klytos v{$current} -- Estas al dia, no hay actualizaciones.";
                }
                $newVer = $update['version'] ?? '?';
                return "Klytos v{$current} -- Actualizacion disponible: v{$newVer}\nEjecuta 'update:run' para actualizar.";
            },
        ];

        $this->commands['update:run'] = [
            'description' => 'Descargar e instalar la ultima actualizacion',
            'usage'       => 'update:run',
            'category'    => 'update',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $updater = $this->app->getUpdater();
                $update  = $updater->checkForUpdate( true );
                if ( $update === null ) {
                    return 'No hay actualizaciones disponibles.';
                }
                $downloadUrl = $update['download_url'] ?? '';
                if ( $downloadUrl === '' ) {
                    return 'Error: no se encontro URL de descarga.';
                }
                $result = $updater->install( $downloadUrl );
                if ( $result['success'] ?? false ) {
                    $from = $result['from_version'] ?? '?';
                    $to   = $result['to_version'] ?? '?';
                    return "Actualizado correctamente: v{$from} -> v{$to}";
                }
                return 'Error: ' . ( $result['error'] ?? 'Fallo la actualizacion.' );
            },
        ];

        // --- Category: Config ---

        $this->commands['config:get'] = [
            'description' => 'Mostrar el valor de una opcion de configuracion',
            'usage'       => 'config:get <clave>',
            'category'    => 'config',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                if ( empty( $args[0] ) ) {
                    return "Uso: config:get <clave>\nEjemplo: config:get site_name";
                }
                $value = $this->app->getOptionsManager()->get( $args[0] );
                if ( $value === null ) {
                    return "Opcion '{$args[0]}' no encontrada.";
                }
                if ( is_array( $value ) || is_object( $value ) ) {
                    return $args[0] . ' = ' . json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
                }
                return $args[0] . ' = ' . (string) $value;
            },
        ];

        $this->commands['config:set'] = [
            'description' => 'Establecer el valor de una opcion de configuracion',
            'usage'       => 'config:set <clave> <valor>',
            'category'    => 'config',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                if ( count( $args ) < 2 ) {
                    return "Uso: config:set <clave> <valor>\nEjemplo: config:set site_name \"Mi Sitio\"";
                }
                $key   = $args[0];
                $value = implode( ' ', array_slice( $args, 1 ) );
                $this->app->getOptionsManager()->set( $key, $value );
                return "Opcion '{$key}' actualizada a: {$value}";
            },
        ];

        // --- Category: Logs ---

        $this->commands['logs'] = [
            'description' => 'Ver las entradas del log del sistema',
            'usage'       => 'logs [--date=YYYY-MM-DD] [--lines=50]',
            'category'    => 'system',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $logger   = $this->app->getLogger();
                $logFiles = $logger->listLogFiles();

                if ( empty( $logFiles ) ) {
                    return 'No hay archivos de log.';
                }

                // Pick log file by date or use the most recent.
                $date     = $flags['date'] ?? '';
                $filename = '';
                if ( $date !== '' ) {
                    foreach ( $logFiles as $f ) {
                        if ( str_contains( $f['name'], $date ) ) {
                            $filename = $f['name'];
                            break;
                        }
                    }
                    if ( $filename === '' ) {
                        return "No se encontro log para la fecha: {$date}";
                    }
                } else {
                    $filename = $logFiles[0]['name'];
                }

                $limit   = (int) ( $flags['lines'] ?? 50 );
                $entries = $logger->readLogFile( $filename, 0, $limit );

                if ( empty( $entries ) ) {
                    return "Log '{$filename}' vacio.";
                }

                $output = "Log: {$filename} (ultimas {$limit} lineas):\n\n";
                foreach ( $entries as $entry ) {
                    $output .= $entry . "\n";
                }
                return $output;
            },
        ];

        // --- Category: Webhooks ---

        $this->commands['webhooks'] = [
            'description' => 'Listar webhooks configurados',
            'usage'       => 'webhooks',
            'category'    => 'system',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $webhooks = $this->app->getWebhookManager()->list();
                if ( empty( $webhooks ) ) {
                    return 'No hay webhooks configurados.';
                }
                $output = "Webhooks (" . count( $webhooks ) . "):\n\n";
                foreach ( $webhooks as $wh ) {
                    $status = ( $wh['active'] ?? false ) ? 'activo' : 'inactivo';
                    $event  = $wh['event'] ?? '?';
                    $url    = $wh['url'] ?? '?';
                    $output .= "  [{$status}] {$event} -> {$url}\n";
                }
                return $output;
            },
        ];
    }

    /**
     * Suggest a similar command when the user types something incorrect.
     * Uses Levenshtein distance to find the closest match.
     */
    private function suggestCommand( string $input ): ?string
    {
        $minDistance = PHP_INT_MAX;
        $suggestion = null;

        foreach ( array_keys( $this->commands ) as $name ) {
            $distance = levenshtein( $input, $name );
            if ( $distance < $minDistance && $distance <= 3 ) {
                $minDistance = $distance;
                $suggestion = $name;
            }
        }

        return $suggestion;
    }

    /**
     * Get the list of registered commands.
     * Used by the autocomplete endpoint.
     *
     * @return array<string, array>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Get command metadata without handlers (safe for serialization/JSON).
     *
     * Returns description, usage, and category for each command.
     * Used by the autocomplete endpoint and command reference panel.
     *
     * @return array<string, array{description: string, usage: string, category: string}>
     */
    public function getCommandsMetadata(): array
    {
        $result = [];
        foreach ( $this->commands as $name => $config ) {
            $result[ $name ] = [
                'description' => $config['description'] ?? '',
                'usage'       => $config['usage'] ?? $name,
                'category'    => $config['category'] ?? 'general',
            ];
        }
        return $result;
    }

    /**
     * Register a command from a plugin.
     * Alternative to the terminal.commands filter.
     */
    public function registerCommand( string $name, array $config ): void
    {
        $required = [ 'description', 'usage', 'handler', 'category' ];
        foreach ( $required as $field ) {
            if ( empty( $config[ $field ] ) ) {
                throw new \InvalidArgumentException(
                    "El campo '{$field}' es requerido al registrar el comando '{$name}'"
                );
            }
        }
        $this->commands[ strtolower( $name ) ] = $config;
    }

    // --- Private security methods ---

    private function sanitizeInput( string $raw ): string
    {
        // 1. Remove control characters (except space).
        $clean = preg_replace( '/[\x00-\x1F\x7F]/', '', $raw );

        // 2. Limit length (256 characters max).
        $clean = mb_substr( $clean, 0, 256 );

        // 3. Remove dangerous shell characters.
        //    Even though we never reach exec(), defense in depth.
        $clean = str_replace(
            [ '|', '>', '<', '&', ';', '`', '$', '(', ')', '{', '}', '[', ']', '\\', "\n", "\r" ],
            '',
            $clean
        );

        return trim( $clean );
    }

    private function checkRateLimit( string $userId ): bool
    {
        $key        = 'terminal_rate_' . $userId;
        $window     = 60; // 1 minute.
        $maxCommands = 30;
        $storage    = klytos_storage();

        $results = $storage->list( 'rate-limits', [ 'key' => $key ], 1 );
        $data    = $results[0] ?? null;

        if ( ! $data || ( time() - ( $data['window_start'] ?? 0 ) ) > $window ) {
            // Start a new rate limit window.
            $recordId = md5( $key );
            if ( $data ) {
                $existingId = $data['_id'] ?? $data['id'] ?? $recordId;
                $storage->delete( 'rate-limits', (string) $existingId );
            }
            $storage->write( 'rate-limits', $recordId, [
                'key'          => $key,
                'count'        => 1,
                'window_start' => time(),
            ] );
            return true;
        }

        if ( ( $data['count'] ?? 0 ) >= $maxCommands ) {
            return false;
        }

        // Increment count.
        $recordId        = $data['_id'] ?? $data['id'] ?? md5( $key );
        $data['count']   = ( $data['count'] ?? 0 ) + 1;
        $storage->delete( 'rate-limits', (string) $recordId );
        $storage->write( 'rate-limits', (string) $recordId, $data );
        return true;
    }

    private function checkRevalidation(): bool
    {
        $lastCommand = $_SESSION['klytos_terminal_last_command'] ?? 0;
        return ( time() - $lastCommand ) > 600; // 10 minutes.
    }
}
