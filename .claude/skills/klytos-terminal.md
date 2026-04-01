---
name: klytos-terminal
description: Guide for developing and extending the Klytos web terminal. Use when modifying terminal commands, adding plugin commands, fixing terminal bugs, or working with the TerminalExecutor class.
trigger: When the user asks to modify the terminal, add terminal commands, fix terminal bugs, extend the pseudo-terminal, or work with TerminalExecutor.
---

# Klytos Web Terminal Guide

## Overview

The Klytos terminal is a pseudo-terminal integrated into the admin panel that executes **exclusively** Klytos CLI commands from the browser. It is NOT a system shell -- it runs 100% in PHP without exec/shell_exec/proc_open.

## Security Requirements

- **2FA Required**: Terminal is only visible and accessible if the user has 2FA active.
- **Permission**: Requires `terminal.access` permission (owner role only).
- **Rate Limiting**: Max 30 commands/minute per user via `rate-limits` storage collection.
- **2FA Revalidation**: After 10 minutes of inactivity, the next command requires re-entering a 2FA code.
- **Input Sanitization**: Max 256 chars, no shell operators (`|`, `>`, `<`, `&`, `;`, `` ` ``, `$`, etc.).
- **Audit Logging**: Every command is logged via `klytos_log()` with user_id, command, IP, result.

## Architecture

### Files

| File | Purpose |
|------|---------|
| `core/terminal-executor.php` | TerminalExecutor class: command registry, parsing, execution, security |
| `admin/terminal.php` | Admin page with xterm.js UI |
| `admin/api/terminal.php` | POST endpoint for command execution |
| `admin/api/terminal-autocomplete.php` | GET endpoint for Tab completion |
| `admin/api/terminal-revalidate.php` | POST endpoint for 2FA revalidation |
| `admin/assets/vendor/xterm/` | Bundled xterm.js + fit addon |

### Class: TerminalExecutor

Location: `installer/core/terminal-executor.php`
Namespace: `Klytos\Core`

Two execution methods:

```php
// Web terminal (with 2FA, rate limiting, history, audit log):
$executor = $app->getTerminalExecutor();
$result = $executor->execute('build', $userId);
// Returns: ['success' => bool, 'output' => string, 'command' => string, 'timestamp' => int, 'requires_2fa' => bool]

// CLI / direct dispatch (no web security layers):
$result = $executor->dispatch('build', ['my-page'], ['period' => '30d']);
// Returns: ['success' => bool, 'output' => string]

// Get command metadata (safe for JSON serialization, no handlers):
$meta = $executor->getCommandsMetadata();
// Returns: ['build' => ['description' => '...', 'usage' => '...', 'category' => 'build'], ...]
```

### CLI Entry Point

`installer/cli.php` is a thin adapter over TerminalExecutor. It boots the app, parses `$argv`, and calls `$executor->dispatch()`. Plugin commands registered via `terminal.commands` filter are automatically available in both the web terminal AND the CLI.

### Persistent History

Command history is persisted to storage (collection `terminal-history`). On each successful execution, the command + truncated output is saved. History survives sessions.

```php
$executor->getHistory( 50 );  // Last 50 entries (default)
$executor->getHistory( 0 );   // All entries (max 100 stored)
// Returns: [['command' => '...', 'output' => '...', 'timestamp' => int], ...]
```

### Core Commands

| Command | Category | Permission | Description |
|---------|----------|------------|-------------|
| `help [cmd]` | general | null | Show help or command details |
| `clear` | general | null | Clear terminal screen |
| `build` | build | build.run | Rebuild entire static site |
| `build:page <slug>` | build | build.run | Rebuild a single page |
| `pages [--status=all]` | content | pages.view | List all pages |
| `pages:count` | content | pages.view | Count pages by status |
| `tasks [--status=all]` | content | tasks.manage | List tasks |
| `tasks:count` | content | tasks.manage | Count tasks by status |
| `status` | system | site.configure | System status report |
| `version` | system | null | Show Klytos version |
| `cache:clear` | system | site.configure | Clear rate-limit and cron caches |
| `cron:run` | system | site.configure | Run pending scheduled tasks |
| `logs [--date=Y-m-d] [--lines=50]` | system | site.configure | View system log entries |
| `webhooks` | system | site.configure | List configured webhooks |
| `users` | users | users.manage | List admin users |
| `plugins` | plugins | plugins.manage | List installed plugins |
| `plugins:activate <id>` | plugins | plugins.manage | Activate a plugin |
| `plugins:deactivate <id>` | plugins | plugins.manage | Deactivate a plugin |
| `analytics [--period=7d]` | system | analytics.view | Analytics summary |
| `backup:create [--label=x]` | backup | site.configure | Create a manual backup |
| `backup:list` | backup | site.configure | List available backups |
| `backup:restore <name>` | backup | site.configure | Restore a backup |
| `update:check` | update | site.configure | Check for Klytos updates |
| `update:run` | update | site.configure | Download and install update |
| `config:get <key>` | config | site.configure | Show a config value |
| `config:set <key> <value>` | config | site.configure | Set a config value |

## Adding Commands from a Plugin

### Step 1: Register custom permissions (MANDATORY if using new permissions)

If your plugin terminal commands need permissions that don't already exist in Klytos (e.g., `orders.manage`, `shipping.configure`), you **MUST** register them first via the `auth.capabilities` filter. Without this step, `klytos_has_permission()` will always return false for your custom permission and the command will be blocked.

```php
// In your plugin's init.php -- register BEFORE terminal commands
klytos_add_filter( 'auth.capabilities', function ( array $capabilities ): array {
    // Define which roles can use each permission.
    $capabilities['orders.manage']     = [ 'owner', 'admin' ];
    $capabilities['orders.view']       = [ 'owner', 'admin', 'editor' ];
    $capabilities['shipping.configure'] = [ 'owner' ];
    $capabilities['stock.manage']      = [ 'owner', 'admin' ];
    return $capabilities;
} );
```

**Existing permissions you can reuse** (no need to register again):
`pages.view`, `pages.create`, `pages.edit`, `pages.delete`, `build.run`, `assets.manage`, `tasks.create`, `tasks.manage`, `users.manage`, `mcp.manage`, `site.configure`, `plugins.manage`, `analytics.view`, `forms.manage`, `webhooks.manage`, `updates.manage`, `terminal.access`.

### Step 2: Register terminal commands

#### Method 1: Via filter (recommended)

```php
// In your plugin's init.php
klytos_add_filter( 'terminal.commands', function ( array $commands ): array {
    $commands['orders'] = [
        'description' => 'List recent orders',
        'usage'       => 'orders [--limit=20]',
        'category'    => 'ecommerce',
        'permission'  => 'orders.view',  // Uses custom permission registered above
        'handler'     => function ( array $args, array $flags ) {
            $limit  = (int) ( $flags['limit'] ?? 20 );
            $orders = klytos_storage()->list( 'orders', [], $limit );
            $output = "Last {$limit} orders:\n\n";
            foreach ( $orders as $order ) {
                $total  = number_format( $order['total'] ?? 0, 2 );
                $status = $order['status'] ?? 'pending';
                $output .= "  #{$order['order_number']} [{$status}] {$total}EUR\n";
            }
            return $output;
        },
    ];

    $commands['stock:update'] = [
        'description' => 'Update product stock',
        'usage'       => 'stock:update <product-slug> <quantity>',
        'category'    => 'ecommerce',
        'permission'  => 'stock.manage',  // Uses custom permission registered above
        'handler'     => function ( array $args, array $flags ) {
            if ( count( $args ) < 2 ) {
                return "Usage: stock:update <product-slug> <quantity>\nExample: stock:update blue-shirt 50";
            }
            $slug     = $args[0];
            $quantity = (int) $args[1];
            $storage  = klytos_storage();
            $results  = $storage->list( 'products', [ 'slug' => $slug ], 1 );
            $product  = $results[0] ?? null;
            if ( ! $product ) {
                return "Product not found: {$slug}";
            }
            $id = $product['_id'] ?? $product['id'] ?? '';
            $product['stock'] = $quantity;
            $storage->delete( 'products', (string) $id );
            $storage->write( 'products', (string) $id, $product );
            return "Stock for '{$product['title']}' updated to {$quantity} units.";
        },
    ];

    return $commands;
} );
```

#### Method 2: Via registerCommand()

```php
klytos_add_action( 'klytos.init', function () {
    $app = klytos_app();
    if ( method_exists( $app, 'getTerminalExecutor' ) ) {
        $app->getTerminalExecutor()->registerCommand( 'shipping:rates', [
            'description' => 'Show configured shipping rates',
            'usage'       => 'shipping:rates',
            'category'    => 'ecommerce',
            'permission'  => 'shipping.configure',  // Custom permission
            'handler'     => function ( array $args, array $flags ) {
                return "Shipping rates:\n  ...";
            },
        ] );
    }
} );
```

### Step 3: Custom category labels

```php
klytos_add_filter( 'terminal.category_labels', function ( array $labels ): array {
    $labels['ecommerce'] = 'E-Commerce';
    return $labels;
} );
```

### Complete plugin example (all 3 steps together)

```php
// init.php of a plugin that adds terminal commands

// 1. Register custom permissions FIRST.
klytos_add_filter( 'auth.capabilities', function ( array $caps ): array {
    $caps['orders.view']   = [ 'owner', 'admin', 'editor' ];
    $caps['orders.manage'] = [ 'owner', 'admin' ];
    return $caps;
} );

// 2. Register terminal commands.
klytos_add_filter( 'terminal.commands', function ( array $commands ): array {
    $commands['orders'] = [
        'description' => 'List orders',
        'usage'       => 'orders',
        'category'    => 'ecommerce',
        'permission'  => 'orders.view',
        'handler'     => fn() => 'Orders list here...',
    ];
    return $commands;
} );

// 3. Register category label.
klytos_add_filter( 'terminal.category_labels', function ( array $labels ): array {
    $labels['ecommerce'] = 'E-Commerce';
    return $labels;
} );
```

## Command Handler Signature

```php
function ( array $args, array $flags, TerminalExecutor $terminal ): string
```

- `$args`: Positional arguments (e.g., `build:page mi-pagina` -> `['mi-pagina']`)
- `$flags`: Named flags (e.g., `--period=30d` -> `['period' => '30d']`)
- `$terminal`: The TerminalExecutor instance
- Return: String output to display in the terminal

## Frontend

- **Library**: xterm.js 5.5.0 + addon-fit (bundled locally in `admin/assets/vendor/xterm/`)
- **Theme**: Catppuccin Mocha
- **Features**: Tab autocomplete, command history (arrows), Ctrl+C cancel, Ctrl+L clear, Home/End
- **API calls**: Use `X-CSRF-Token` header with fetch to `admin/api/terminal*.php`
- **Special output**: `__CLEAR__` return value triggers screen clear on frontend

## Sidebar Integration

Terminal appears at position 95 in the `system` section, between Plugins (90) and Updates (98). Only shown when user has 2FA enabled AND `terminal.access` permission. Defined conditionally in `admin/templates/sidebar.php`.

## Hooks

| Hook | Type | Description |
|------|------|-------------|
| `terminal.commands` | filter | Modify the command registry (add/remove commands) |
| `terminal.category_labels` | filter | Add custom category labels for help output |
| `auth.capabilities` | filter | Register custom permissions for plugin commands (MANDATORY before using new permissions) |

## Important: Permission Registration Order

When a plugin adds terminal commands with **custom permissions** (not existing ones), it **MUST** register those permissions via `auth.capabilities` BEFORE registering the commands via `terminal.commands`. Both filters run during init, but `auth.capabilities` is checked at command execution time by `klytos_has_permission()`. If the permission is not registered, the command will always return "No tienes permiso para ejecutar este comando."
