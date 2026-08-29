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
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 Jose Conti -- https://plugins.joseconti.com -- https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
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

    /** @var string Storage collection for terminal data. */
    private const STORAGE_COLLECTION = 'terminal';

    /** @var string Storage record ID for persistent history. */
    private const HISTORY_ID = 'history';

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
            $data = $this->app->getStorage()->read( self::STORAGE_COLLECTION, self::HISTORY_ID );
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

        $this->app->getStorage()->write( self::STORAGE_COLLECTION, self::HISTORY_ID, [
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
            $output     = __( 'terminal.unknown_command', [ 'command' => $commandName ] );
            if ( $suggestion ) {
                $output .= "\n" . __( 'terminal.did_you_mean', [ 'suggestion' => $suggestion ] );
            }
            $output .= "\n" . __( 'terminal.type_help' );

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
            return [
                'success' => false,
                'output'  => __( 'terminal.error', [ 'message' => $e->getMessage() ] ),
            ];
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

        // $clean is what gets PARSED and executed; $recorded is what is allowed to
        // leave this method — persisted to history, written to the audit log, or
        // echoed back to the browser. They differ only in that a secret passed as
        // a flag is masked in the second. Every `return` below uses $recorded.
        $recorded = $this->redactSecrets( $clean );

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
                'output'       => __( 'terminal.rate_limited' ),
                'command'      => $recorded,
                'timestamp'    => $timestamp,
                'requires_2fa' => false,
            ];
        }

        // 3. 2FA revalidation after inactivity.
        if ( $this->checkRevalidation() ) {
            return [
                'success'      => false,
                'output'       => '',
                'command'      => $recorded,
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
                        'output'       => __( 'terminal.no_permission' ),
                        'command'      => $recorded,
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
            'command'   => $recorded,
            'output'    => mb_substr( $result['output'], 0, 2000 ),
            'timestamp' => $timestamp,
        ];
        $this->saveHistory();

        // 9. Audit log.
        klytos_log( $result['success'] ? 'info' : 'error', 'terminal.command', [
            'user_id' => $userId,
            'command' => $recorded,
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'result'  => $result['success'] ? 'ok' : 'error',
        ] );

        return [
            'success'      => $result['success'],
            'output'       => $result['output'],
            'command'      => $recorded,
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
            'description' => __( 'terminal.cmd_build_desc' ),
            'usage'       => 'build',
            'category'    => 'build',
            'permission'  => 'build.run',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $engine = new BuildEngine( $this->app );
                $result = $engine->buildAll();
                $count  = $result['pages_built'] ?? 0;
                return __( 'terminal.cmd_build_done', [ 'count' => (string) $count ] );
            },
        ];

        $this->commands['build:page'] = [
            'description' => __( 'terminal.cmd_build_page_desc' ),
            'usage'       => 'build:page <slug>',
            'category'    => 'build',
            'permission'  => 'build.run',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                if ( empty( $args[0] ) ) {
                    return __( 'terminal.cmd_build_page_usage' );
                }
                $slug   = $args[0];
                $engine = new BuildEngine( $this->app );
                $result = $engine->buildPage( $slug );
                if ( ! empty( $result['success'] ) ) {
                    return __( 'terminal.cmd_build_page_done', [ 'slug' => $slug ] );
                }
                return __( 'terminal.cmd_build_page_missing', [ 'slug' => $slug ] );
            },
        ];

        // --- Category: Content ---

        $this->commands['pages'] = [
            'description' => __( 'terminal.cmd_pages_desc' ),
            'usage'       => 'pages [--status=all|published|draft|archived]',
            'category'    => 'content',
            'permission'  => 'pages.view',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $status = $flags['status'] ?? 'all';
                $pages  = $this->app->getPages()->list( $status );
                if ( empty( $pages ) ) {
                    return __( 'terminal.cmd_pages_empty' );
                }
                $output = __( 'terminal.cmd_pages_heading', [ 'count' => (string) count( $pages ) ] ) . "\n\n";
                foreach ( $pages as $page ) {
                    $st    = $page['status'] ?? 'draft';
                    $slug  = $page['slug'] ?? __( 'terminal.value_no_slug' );
                    $title = $page['title'] ?? __( 'terminal.value_no_title' );
                    $output .= "  [{$st}] /{$slug} -- {$title}\n";
                }
                return $output;
            },
        ];

        $this->commands['pages:count'] = [
            'description' => __( 'terminal.cmd_pages_count_desc' ),
            'usage'       => 'pages:count',
            'category'    => 'content',
            'permission'  => 'pages.view',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $total     = $this->app->getPages()->count( 'all' );
                $published = $this->app->getPages()->count( 'published' );
                $drafts    = $this->app->getPages()->count( 'draft' );
                return __( 'terminal.cmd_pages_count_output', [
                    'total'     => (string) $total,
                    'published' => (string) $published,
                    'drafts'    => (string) $drafts,
                ] );
            },
        ];

        $this->commands['tasks'] = [
            'description' => __( 'terminal.cmd_tasks_desc' ),
            'usage'       => 'tasks [--status=all|pending|completed]',
            'category'    => 'content',
            'permission'  => 'tasks.manage',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $status = $flags['status'] ?? 'all';
                $tasks  = $this->app->getTaskManager()->list( $status );
                if ( empty( $tasks ) ) {
                    return __( 'terminal.cmd_tasks_empty' );
                }
                $output = __( 'terminal.cmd_tasks_heading', [ 'count' => (string) count( $tasks ) ] ) . "\n\n";
                foreach ( $tasks as $task ) {
                    $st    = $task['status'] ?? 'pending';
                    $title = $task['title'] ?? __( 'terminal.value_no_title' );
                    $output .= "  [{$st}] {$title}\n";
                }
                return $output;
            },
        ];

        $this->commands['tasks:count'] = [
            'description' => __( 'terminal.cmd_tasks_count_desc' ),
            'usage'       => 'tasks:count',
            'category'    => 'content',
            'permission'  => 'tasks.manage',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $all       = $this->app->getTaskManager()->list( 'all' );
                $pending   = $this->app->getTaskManager()->list( 'pending' );
                $completed = $this->app->getTaskManager()->list( 'completed' );
                return __( 'terminal.cmd_tasks_count_output', [
                    'total'     => (string) count( $all ),
                    'pending'   => (string) count( $pending ),
                    'completed' => (string) count( $completed ),
                ] );
            },
        ];

        // --- Category: System ---

        $this->commands['status'] = [
            'description' => __( 'terminal.cmd_status_desc' ),
            'usage'       => 'status',
            'category'    => 'system',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $version  = klytos_version();
                $config   = $this->app->getConfig();
                $siteName = $config['site_name'] ?? __( 'terminal.value_unnamed_site' );
                $pages    = $this->app->getPages()->count( 'all' );

                $output  = "Klytos v{$version}\n";
                $output .= __( 'terminal.status_site' ) . ": {$siteName}\n";
                $output .= __( 'terminal.status_pages' ) . ": {$pages}\n";
                $output .= __( 'terminal.status_php' ) . ': ' . PHP_VERSION . "\n";
                $output .= __( 'terminal.status_server' ) . ': ' . PHP_SAPI . "\n";
                return $output;
            },
        ];

        $this->commands['version'] = [
            'description' => __( 'terminal.cmd_version_desc' ),
            'usage'       => 'version',
            'category'    => 'system',
            'permission'  => null,
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                return 'Klytos v' . klytos_version();
            },
        ];

        $this->commands['cache:clear'] = [
            'description' => __( 'terminal.cmd_cache_clear_desc' ),
            'usage'       => 'cache:clear',
            'category'    => 'system',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $storage = klytos_storage();

                // Keyed by the STORAGE id. It used to read `$item['_id']` or
                // `$item['id']`, and the rate limiter writes neither (it stores
                // key/count/window_start), so the guard below it was always
                // false and `cache:clear` never cleared a single record (D-115).
                foreach ( $storage->listWithIds( 'rate-limits' ) as $id => $item ) {
                    $storage->delete( 'rate-limits', (string) $id );
                }

                return __( 'terminal.cmd_cache_clear_done' );
            },
        ];

        $this->commands['cron:run'] = [
            'description' => __( 'terminal.cmd_cron_run_desc' ),
            'usage'       => 'cron:run',
            'category'    => 'system',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $result   = $this->app->getActionScheduler()->processQueue();
                $executed = $result['processed'] ?? 0;
                $failed   = $result['failed'] ?? 0;
                $output   = __( 'terminal.cmd_cron_run_done', [ 'executed' => (string) $executed ] );
                if ( $failed > 0 ) {
                    $output .= ' | ' . __( 'terminal.cmd_cron_run_failed', [ 'failed' => (string) $failed ] );
                }
                return $output;
            },
        ];

        // --- Category: Users ---

        $this->commands['users'] = [
            'description' => __( 'terminal.cmd_users_desc' ),
            'usage'       => 'users',
            'category'    => 'users',
            'permission'  => 'users.manage',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $users = $this->app->getUserManager()->list();
                if ( empty( $users ) ) {
                    return __( 'terminal.cmd_users_empty' );
                }
                $output = __( 'terminal.cmd_users_heading', [ 'count' => (string) count( $users ) ] ) . "\n\n";
                foreach ( $users as $user ) {
                    $name  = $user['display_name'] ?? $user['username'] ?? __( 'terminal.value_no_name' );
                    $role  = $user['role'] ?? 'user';
                    $twofa = ! empty( $user['two_factor']['enabled'] ) ? '[2FA]' : '[---]';
                    $output .= "  {$twofa} {$name} ({$role})\n";
                }
                return $output;
            },
        ];

        // Audit NEW-08: the only supported way to recreate a missing owner.
        //
        // WHAT THE BROKEN STATE ACTUALLY IS, because the fix follows from it.
        // App::boot() Step 10b runs UserManager::migrateFromV1Config(), which
        // builds the owner record from config['admin_user'] and
        // config['admin_pass_hash']. It THROWS when config has no usable
        // admin_email, and D-031 contained that throw so boot survives — leaving
        // an install whose credentials are intact but whose owner RECORD does not
        // exist. Every permission check then denies, and nothing could put the
        // record back.
        //
        // So this command repairs the CAUSE — the missing admin_email — and then
        // runs the product's own migration, rather than creating an owner by a
        // second route. That matters for correctness, not tidiness: Auth::login()
        // validates the username against config['admin_user'] and the password
        // against config['admin_pass_hash'], never against the user record. An
        // owner minted with its own username and password would therefore be a
        // record nobody can log in as — and, because findOwner() would then return
        // non-null, this command would refuse to run again. Found by this slice's
        // own code-reviewer.
        //
        // WHY THE CLI. Recovery must work with no session, which rules out the
        // admin panel by construction. dispatch() runs no permission check and
        // cli.php calls it directly — deliberate, since CLI access already implies
        // filesystem access. The permission below gates the WEB terminal.
        $this->commands['owner:repair'] = [
            'description' => __( 'terminal.owner_repair_description' ),
            'usage'       => 'owner:repair --email=<address>',
            'category'    => 'users',
            'permission'  => 'users.manage',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $email = trim( $flags['email'] ?? '' );

                // Refusals THROW rather than return: dispatch() reports success
                // for any handler that returns, and cli.php derives its exit code
                // from that — a returned refusal would exit 0 and tell a recovery
                // script that a repair which changed nothing had worked.
                if ( $email === '' || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                    throw new \RuntimeException( __( 'terminal.owner_repair_usage' ) );
                }

                $users = $this->app->getUserManager();

                $existing = $users->findOwner();
                if ( $existing !== null ) {
                    $message = __(
                        'terminal.owner_repair_exists',
                        [ 'username' => $existing['username'] ?? '?' ]
                    );

                    throw new \RuntimeException( $message );
                }

                // Without these two, restoring the record restores nothing:
                // Auth::login() has nothing to validate against. Refuse plainly
                // rather than creating an account that cannot be used.
                $config = $this->app->getConfig();
                if ( empty( $config['admin_user'] ) || empty( $config['admin_pass_hash'] ) ) {
                    throw new \RuntimeException( __( 'terminal.owner_repair_no_credentials' ) );
                }

                // Repair the cause, then let the product's own migration build the
                // record from the credentials that already work.
                $config['admin_email'] = $email;

                $storage = $this->app->getStorage();
                $storage->writeTo( $this->app->getConfigPath(), 'config.json.enc', $config );

                $owner = $users->migrateFromV1Config( $config );

                return __( 'terminal.owner_repair_done', [
                    'username' => $owner['username'] ?? (string) $config['admin_user'],
                ] );
            },
        ];

        // --- Category: Plugins ---

        $this->commands['plugins'] = [
            'description' => __( 'terminal.cmd_plugins_desc' ),
            'usage'       => 'plugins',
            'category'    => 'plugins',
            'permission'  => 'plugins.manage',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $loader     = $this->app->getPluginLoader();
                $plugins    = $loader->discoverPlugins();
                if ( empty( $plugins ) ) {
                    return __( 'terminal.cmd_plugins_empty' );
                }
                $activeList = $loader->getActivePlugins();
                $activeIds  = array_keys( $activeList );
                $output     = __( 'terminal.cmd_plugins_heading', [ 'count' => (string) count( $plugins ) ] ) . "\n\n";
                foreach ( $plugins as $id => $manifest ) {
                    $name    = $manifest['name'] ?? $id;
                    $version = $manifest['version'] ?? '?';
                    $active  = in_array( $id, $activeIds, true )
                        ? __( 'terminal.value_active' )
                        : __( 'terminal.value_inactive' );
                    $output .= "  [{$active}] {$name} v{$version} ({$id})\n";
                }
                return $output;
            },
        ];

        $this->commands['plugins:activate'] = [
            'description' => __( 'terminal.cmd_plugins_activate_desc' ),
            'usage'       => 'plugins:activate <plugin-id>',
            'category'    => 'plugins',
            'permission'  => 'plugins.manage',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                if ( empty( $args[0] ) ) {
                    return __( 'terminal.cmd_plugins_activate_usage' );
                }
                $loader = $this->app->getPluginLoader();
                $result = $loader->activate( $args[0] );
                if ( $result['success'] ?? false ) {
                    return __( 'terminal.cmd_plugins_activate_done', [ 'id' => $args[0] ] );
                }
                return __( 'terminal.cmd_plugins_activate_failed', [
                    'reason' => $result['error'] ?? __( 'terminal.value_unknown_reason' ),
                ] );
            },
        ];

        $this->commands['plugins:deactivate'] = [
            'description' => __( 'terminal.cmd_plugins_deactivate_desc' ),
            'usage'       => 'plugins:deactivate <plugin-id>',
            'category'    => 'plugins',
            'permission'  => 'plugins.manage',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                if ( empty( $args[0] ) ) {
                    return __( 'terminal.cmd_plugins_deactivate_usage' );
                }
                $loader = $this->app->getPluginLoader();
                $result = $loader->deactivate( $args[0] );
                if ( $result['success'] ?? false ) {
                    return __( 'terminal.cmd_plugins_deactivate_done', [ 'id' => $args[0] ] );
                }
                return __( 'terminal.cmd_plugins_deactivate_failed', [
                    'reason' => $result['error'] ?? __( 'terminal.value_unknown_reason' ),
                ] );
            },
        ];

        // --- Category: Analytics ---

        $this->commands['analytics'] = [
            'description' => __( 'terminal.cmd_analytics_desc' ),
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

                $output  = __( 'terminal.cmd_analytics_heading', [ 'period' => $period ] ) . "\n\n";
                $output .= '  ' . __( 'terminal.analytics_total_views' ) . ': ' . ( $data['total_views'] ?? 0 ) . "\n";
                $output .= '  ' . __( 'terminal.analytics_unique_visitors' ) . ': ' . ( $data['unique_visitors'] ?? 0 ) . "\n";
                return $output;
            },
        ];

        // --- Special command: help ---

        $this->commands['help'] = [
            'description' => __( 'terminal.cmd_help_desc' ),
            'usage'       => 'help [command]',
            'category'    => 'general',
            'permission'  => null,
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                // Help for a specific command.
                if ( ! empty( $args[0] ) ) {
                    $cmdName = strtolower( $args[0] );
                    if ( isset( $this->commands[ $cmdName ] ) ) {
                        $cmd     = $this->commands[ $cmdName ];
                        $output  = "{$cmdName} -- {$cmd['description']}\n\n";
                        $output .= __( 'terminal.help_usage', [ 'usage' => $cmd['usage'] ] ) . "\n";
                        return $output;
                    }
                    return __( 'terminal.cmd_help_not_found', [ 'command' => $cmdName ] );
                }

                // List all commands grouped by category.
                $categories = [];
                foreach ( $this->commands as $name => $config ) {
                    $cat                      = $config['category'] ?? 'general';
                    $categories[ $cat ][ $name ] = $config;
                }

                /*
                 * The SAME filter and the SAME nine categories the reference
                 * panel draws (`installer/admin/terminal.php`). It carried six
                 * hardcoded Spanish labels, so `backup`, `update` and `config`
                 * fell through to `ucfirst()` and printed their raw slug —
                 * which is why the list below is nine rows and not six.
                 */
                $categoryLabels = klytos_apply_filters( 'terminal.category_labels', [
                    'general' => __( 'terminal.category_general' ),
                    'build'   => __( 'terminal.category_build' ),
                    'content' => __( 'terminal.category_content' ),
                    'system'  => __( 'terminal.category_system' ),
                    'users'   => __( 'terminal.category_users' ),
                    'plugins' => __( 'terminal.category_plugins' ),
                    'backup'  => __( 'terminal.category_backup' ),
                    'update'  => __( 'terminal.category_update' ),
                    'config'  => __( 'terminal.category_config' ),
                ] );

                $output = 'Klytos Terminal v' . klytos_version() . "\n";
                $output .= __( 'terminal.help_heading' ) . "\n\n";

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

                $output .= __( 'terminal.help_footer_detail' ) . "\n";
                $output .= __( 'terminal.help_footer_plugins' ) . "\n";
                return $output;
            },
        ];

        // --- Special command: clear ---

        $this->commands['clear'] = [
            'description' => __( 'terminal.cmd_clear_desc' ),
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
            'description' => __( 'terminal.cmd_backup_create_desc' ),
            'usage'       => 'backup:create [--label=<name>]',
            'category'    => 'backup',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $label  = $flags['label'] ?? '';
                $result = $this->app->getUpdater()->createManualBackup( $label );
                if ( $result['success'] ) {
                    return __( 'terminal.cmd_backup_create_done', [ 'name' => $result['backup'] ] );
                }
                return __( 'terminal.cmd_backup_create_failed' );
            },
        ];

        $this->commands['backup:list'] = [
            'description' => __( 'terminal.cmd_backup_list_desc' ),
            'usage'       => 'backup:list',
            'category'    => 'backup',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $backups = $this->app->getUpdater()->listBackups();
                if ( empty( $backups ) ) {
                    return __( 'terminal.cmd_backup_list_empty' );
                }
                $output = __( 'terminal.cmd_backup_list_heading', [ 'count' => (string) count( $backups ) ] ) . "\n\n";
                foreach ( $backups as $b ) {
                    $date = klytos_gmdate( 'Y-m-d H:i', $b['date'] );
                    $type = $b['type'] === 'manual' ? '[MANUAL]' : '[UPDATE]';
                    $output .= "  {$type} {$b['name']}  ({$date})\n";
                }
                return $output;
            },
        ];

        $this->commands['backup:restore'] = [
            'description' => __( 'terminal.cmd_backup_restore_desc' ),
            'usage'       => 'backup:restore <backup-name>',
            'category'    => 'backup',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                if ( empty( $args[0] ) ) {
                    return __( 'terminal.cmd_backup_restore_usage' );
                }
                $result = $this->app->getUpdater()->restoreFromBackup( $args[0] );
                if ( $result['success'] ?? false ) {
                    return __( 'terminal.cmd_backup_restore_done', [ 'name' => $args[0] ] );
                }
                return __( 'terminal.error', [
                    'message' => $result['error'] ?? __( 'terminal.cmd_backup_restore_failed' ),
                ] );
            },
        ];

        // --- Category: Update ---

        $this->commands['update:check'] = [
            'description' => __( 'terminal.cmd_update_check_desc' ),
            'usage'       => 'update:check',
            'category'    => 'update',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $updater = $this->app->getUpdater();
                $current = $updater->getCurrentVersion();
                $update  = $updater->checkForUpdate( true );
                if ( $update === null ) {
                    return __( 'terminal.cmd_update_check_current', [ 'version' => $current ] );
                }
                $newVer = $update['version'] ?? '?';
                return __( 'terminal.cmd_update_check_available', [
                    'version' => $current,
                    'latest'  => $newVer,
                ] );
            },
        ];

        $this->commands['update:run'] = [
            'description' => __( 'terminal.cmd_update_run_desc' ),
            'usage'       => 'update:run',
            'category'    => 'update',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $updater = $this->app->getUpdater();
                $update  = $updater->checkForUpdate( true );
                if ( $update === null ) {
                    return __( 'terminal.cmd_update_run_none' );
                }
                $downloadUrl = $update['download_url'] ?? '';
                if ( $downloadUrl === '' ) {
                    return __( 'terminal.cmd_update_run_no_url' );
                }
                $result = $updater->install( $downloadUrl );
                if ( $result['success'] ?? false ) {
                    $from = $result['from_version'] ?? '?';
                    $to   = $result['to_version'] ?? '?';
                    return __( 'terminal.cmd_update_run_done', [ 'from' => $from, 'to' => $to ] );
                }
                return __( 'terminal.error', [
                    'message' => $result['error'] ?? __( 'terminal.cmd_update_run_failed' ),
                ] );
            },
        ];

        // --- Category: Config ---

        $this->commands['config:get'] = [
            'description' => __( 'terminal.cmd_config_get_desc' ),
            'usage'       => 'config:get <key>',
            'category'    => 'config',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                if ( empty( $args[0] ) ) {
                    return __( 'terminal.cmd_config_get_usage' );
                }
                $value = $this->app->getOptionsManager()->get( $args[0] );
                if ( $value === null ) {
                    return __( 'terminal.cmd_config_get_missing', [ 'key' => $args[0] ] );
                }
                if ( is_array( $value ) || is_object( $value ) ) {
                    return $args[0] . ' = ' . json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
                }
                return $args[0] . ' = ' . (string) $value;
            },
        ];

        $this->commands['config:set'] = [
            'description' => __( 'terminal.cmd_config_set_desc' ),
            'usage'       => 'config:set <key> <value>',
            'category'    => 'config',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                if ( count( $args ) < 2 ) {
                    return __( 'terminal.cmd_config_set_usage' );
                }
                $key   = $args[0];
                $value = implode( ' ', array_slice( $args, 1 ) );
                $this->app->getOptionsManager()->set( $key, $value );
                return __( 'terminal.cmd_config_set_done', [ 'key' => $key, 'value' => $value ] );
            },
        ];

        // --- Category: Logs ---

        $this->commands['logs'] = [
            'description' => __( 'terminal.cmd_logs_desc' ),
            'usage'       => 'logs [--date=YYYY-MM-DD] [--lines=50]',
            'category'    => 'system',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $logger   = $this->app->getLogger();
                $logFiles = $logger->listLogFiles();

                if ( empty( $logFiles ) ) {
                    return __( 'terminal.cmd_logs_no_files' );
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
                        return __( 'terminal.cmd_logs_no_file_for_date', [ 'date' => $date ] );
                    }
                } else {
                    $filename = $logFiles[0]['name'];
                }

                $limit   = (int) ( $flags['lines'] ?? 50 );
                $entries = $logger->readLogFile( $filename, 0, $limit );

                if ( empty( $entries ) ) {
                    return __( 'terminal.cmd_logs_empty', [ 'file' => $filename ] );
                }

                $output = __( 'terminal.cmd_logs_heading', [
                    'file'  => $filename,
                    'limit' => (string) $limit,
                ] ) . "\n\n";
                foreach ( $entries as $entry ) {
                    $output .= $entry . "\n";
                }
                return $output;
            },
        ];

        // --- Category: Webhooks ---

        $this->commands['webhooks'] = [
            'description' => __( 'terminal.cmd_webhooks_desc' ),
            'usage'       => 'webhooks',
            'category'    => 'system',
            'permission'  => 'site.configure',
            'handler'     => function ( array $args, array $flags, self $terminal ): string {
                $webhooks = $this->app->getWebhookManager()->list();
                if ( empty( $webhooks ) ) {
                    return __( 'terminal.cmd_webhooks_empty' );
                }
                $output = __( 'terminal.cmd_webhooks_heading', [ 'count' => (string) count( $webhooks ) ] ) . "\n\n";
                foreach ( $webhooks as $wh ) {
                    $status = ( $wh['active'] ?? false )
                        ? __( 'terminal.value_active' )
                        : __( 'terminal.value_inactive' );
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
                    __( 'terminal.register_field_required', [ 'field' => $field, 'command' => $name ] )
                );
            }
        }
        $this->commands[ strtolower( $name ) ] = $config;
    }

    // --- Private security methods ---

    /**
     * Mask the VALUE of any secret-bearing flag before a command is persisted.
     *
     * `execute()` writes the typed command into three places that outlive the
     * request: the terminal history (storage collection `terminal`, which is NOT
     * in ENCRYPTED_PATHS at any level, so it is plaintext on disk), the audit log
     * (a plaintext file whenever Developer Mode is on), and the response echoed
     * back to the browser. None of that was secret-aware, because until
     * `owner:repair` no terminal command had ever taken a secret as a flag.
     *
     * The consequence was concrete rather than theoretical: `admin/logs.php` is
     * gated at `site.configure`, which resolves to owner AND admin — so an admin,
     * strictly lower-privileged than the owner, could read the owner's password
     * out of the log. Found by the slice's own `security-auditor` pass.
     *
     * Redaction lives HERE, at the one place every command's text is persisted,
     * rather than in the one command that happens to need it today — the same
     * inversion S-07 and NEW-02 were each closed with. A future command taking a
     * `--token` is safe without its author remembering anything.
     *
     * @param  string $command The sanitized command line as typed.
     * @return string The same line with secret-bearing flag values replaced.
     */
    private function redactSecrets( string $command ): string
    {
        return (string) preg_replace(
            '/(--(?:password|passwd|pass|secret|token|api[_-]?key|key)=)\S+/i',
            '$1***',
            $command
        );
    }

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
