<?php
/**
 * Klytos — Command Line Interface
 * Manage Klytos from the terminal: build, pages, tasks, users, analytics, plugins.
 *
 * Usage:
 *   php cli.php <command> [options]
 *
 * Commands:
 *   build              Build the entire static site
 *   build:page <slug>  Build a single page
 *   pages              List all pages
 *   pages:count        Count pages by status
 *   tasks              List open tasks
 *   tasks:count        Count tasks by status
 *   users              List all users
 *   analytics          Show analytics summary (last 7 days)
 *   plugins            List installed plugins
 *   status             Show system status
 *   version            Show Klytos version
 *   cache:clear        Clear rate limit and cron caches
 *   help               Show this help message
 *
 * Examples:
 *   php cli.php build
 *   php cli.php pages
 *   php cli.php analytics --period=30d
 *   php cli.php status
 *
 * @package Klytos
 * @since   2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

// ─── Ensure CLI context ──────────────────────────────────────
if (php_sapi_name() !== 'cli') {
    echo "This script can only be run from the command line.\n";
    exit(1);
}

// ─── Bootstrap ───────────────────────────────────────────────
$rootPath = __DIR__;
require_once $rootPath . '/core/app.php';

use Klytos\Core\App;
use Klytos\Core\BuildEngine;
use Klytos\Core\Helpers;

$app = App::getInstance();

if (!$app->isInstalled()) {
    cliError('Klytos is not installed. Run the web installer first.');
}

$app->boot();

// ─── Parse Arguments ─────────────────────────────────────────
$command = $argv[1] ?? 'help';
$args    = array_slice($argv, 2);

// Parse --key=value options.
$options = [];
foreach ($args as $arg) {
    if (str_starts_with($arg, '--')) {
        $parts = explode('=', substr($arg, 2), 2);
        $options[$parts[0]] = $parts[1] ?? true;
    }
}

// ─── Command Dispatch ────────────────────────────────────────
match ($command) {
    'build'        => cmdBuild($app),
    'build:page'   => cmdBuildPage($app, $args[0] ?? ''),
    'pages'        => cmdPages($app),
    'pages:count'  => cmdPagesCount($app),
    'tasks'        => cmdTasks($app),
    'tasks:count'  => cmdTasksCount($app),
    'users'        => cmdUsers($app),
    'analytics'    => cmdAnalytics($app, $options),
    'plugins'      => cmdPlugins($app),
    'status'       => cmdStatus($app),
    'version'      => cmdVersion($app),
    'cache:clear'  => cmdCacheClear($app),
    'help', '--help', '-h' => cmdHelp(),
    default        => cliError("Unknown command: {$command}. Run 'php cli.php help' for usage."),
};

// ─── Command Implementations ─────────────────────────────────

function cmdBuild(App $app): void
{
    cliInfo('Building static site...');
    $engine = new BuildEngine($app);
    $result = $engine->buildAll();

    if ($result['success']) {
        cliSuccess("Build complete: {$result['pages_built']} pages in {$result['duration_ms']}ms");
    } else {
        cliWarning("Build finished with errors:");
        foreach ($result['errors'] as $err) {
            cliError("  - {$err['slug']}: {$err['error']}");
        }
    }
}

function cmdBuildPage(App $app, string $slug): void
{
    if (empty($slug)) {
        cliError('Usage: php cli.php build:page <slug>');
    }
    cliInfo("Building page: {$slug}");
    $engine = new BuildEngine($app);
    $result = $engine->buildPage($slug);
    cliSuccess("Page '{$slug}' built successfully.");
}

function cmdPages(App $app): void
{
    $pages = $app->getPages()->list('all');
    if (empty($pages)) {
        cliInfo('No pages found.');
        return;
    }

    cliInfo(str_pad('SLUG', 30) . str_pad('TITLE', 30) . str_pad('STATUS', 12) . 'LANG');
    cliInfo(str_repeat('─', 85));

    foreach ($pages as $page) {
        $status = ($page['status'] ?? 'draft') === 'published' ? "\033[32mpublished\033[0m" : "\033[33mdraft\033[0m";
        echo str_pad($page['slug'] ?? '', 30)
           . str_pad(mb_substr($page['title'] ?? '', 0, 28), 30)
           . str_pad($status, 21) // extra for ANSI codes
           . ($page['lang'] ?? '') . "\n";
    }

    cliInfo("\nTotal: " . count($pages) . " pages");
}

function cmdPagesCount(App $app): void
{
    $all       = $app->getPages()->count('all');
    $published = $app->getPages()->count('published');
    $draft     = $app->getPages()->count('draft');
    echo "Total: {$all}  |  Published: {$published}  |  Draft: {$draft}\n";
}

function cmdTasks(App $app): void
{
    $tasks = $app->getTaskManager()->list('open');
    if (empty($tasks)) {
        cliInfo('No open tasks.');
        return;
    }

    foreach ($tasks as $task) {
        $priority = match ($task['priority'] ?? 'medium') {
            'urgent' => "\033[31m[URGENT]\033[0m",
            'high'   => "\033[33m[HIGH]\033[0m",
            'medium' => "\033[34m[MEDIUM]\033[0m",
            'low'    => "\033[32m[LOW]\033[0m",
            default  => '[?]',
        };
        echo "{$priority} {$task['description']}  (page: {$task['page_slug']})\n";
    }

    cliInfo("\nOpen tasks: " . count($tasks));
}

function cmdTasksCount(App $app): void
{
    $tm = $app->getTaskManager();
    echo "Open: {$tm->count('open')}  |  In Progress: {$tm->count('in_progress')}  |  Completed: {$tm->count('completed')}\n";
}

function cmdUsers(App $app): void
{
    $users = $app->getUserManager()->list();
    if (empty($users)) {
        cliInfo('No users found.');
        return;
    }

    cliInfo(str_pad('USERNAME', 20) . str_pad('ROLE', 10) . str_pad('EMAIL', 30) . 'STATUS');
    cliInfo(str_repeat('─', 75));

    foreach ($users as $user) {
        echo str_pad($user['username'] ?? '', 20)
           . str_pad($user['role'] ?? '', 10)
           . str_pad($user['email'] ?? '', 30)
           . ($user['status'] ?? 'active') . "\n";
    }
}

function cmdAnalytics(App $app, array $options): void
{
    $period = $options['period'] ?? '7d';
    $days   = match ($period) {
        '24h' => 1, '7d' => 7, '30d' => 30, '90d' => 90, default => 7,
    };

    $dateFrom = date('Y-m-d', strtotime("-{$days} days"));
    $dateTo   = date('Y-m-d');

    $summary = $app->getAnalyticsManager()->getSummary($dateFrom, $dateTo);

    cliInfo("Analytics: {$dateFrom} to {$dateTo}");
    cliInfo(str_repeat('─', 40));
    echo "Page Views:      {$summary['total_views']}\n";
    echo "Unique Visitors: {$summary['unique_visitors']}\n";

    if (!empty($summary['top_pages'])) {
        echo "\nTop Pages:\n";
        $i = 1;
        foreach (array_slice($summary['top_pages'], 0, 10, true) as $path => $views) {
            echo "  {$i}. {$path} ({$views} views)\n";
            $i++;
        }
    }
}

function cmdPlugins(App $app): void
{
    $plugins = $app->getPluginLoader()->listAll();
    if (empty($plugins)) {
        cliInfo('No plugins installed.');
        return;
    }

    foreach ($plugins as $p) {
        $status = $p['active'] ? "\033[32mactive\033[0m" : "\033[33minactive\033[0m";
        $type   = $p['premium'] ? '[PREMIUM]' : '[FREE]';
        echo "{$type} {$p['name']} v{$p['version']} — {$status}\n";
    }
}

function cmdStatus(App $app): void
{
    echo "\n";
    echo "  \033[1mKlytos CMS v{$app->getVersion()}\033[0m\n";
    echo "  ─────────────────────────────\n";
    echo "  PHP:          " . PHP_VERSION . "\n";
    echo "  Storage:      " . (($app->getConfig()['storage_driver'] ?? 'file') === 'database' ? 'MySQL' : 'Flat-file') . "\n";
    echo "  Pages:        " . $app->getPages()->count('all') . "\n";
    echo "  Users:        " . $app->getUserManager()->count() . "\n";
    echo "  Open Tasks:   " . $app->getTaskManager()->count('open') . "\n";
    echo "  Plugins:      " . count($app->getPluginLoader()->listAll()) . "\n";
    echo "  Last Build:   " . ($app->getSiteConfig()->get()['last_build'] ?? 'never') . "\n";
    echo "\n";
}

function cmdVersion(App $app): void
{
    echo "Klytos {$app->getVersion()}\n";
}

function cmdCacheClear(App $app): void
{
    // Clear rate limit data.
    $rateLimitFile = $app->getDataPath() . '/rate_limits.json';
    if (file_exists($rateLimitFile)) {
        unlink($rateLimitFile);
    }

    // Clear cron lock.
    $cronLock = $app->getDataPath() . '/.cron.lock';
    if (file_exists($cronLock)) {
        unlink($cronLock);
    }

    cliSuccess('Cache cleared.');
}

function cmdHelp(): void
{
    echo <<<HELP

  \033[1mKlytos CLI\033[0m — AI-Powered CMS

  \033[33mUsage:\033[0m
    php cli.php <command> [options]

  \033[33mCommands:\033[0m
    build              Build the entire static site
    build:page <slug>  Build a single page
    pages              List all pages
    pages:count        Count pages by status
    tasks              List open tasks
    tasks:count        Count tasks by status
    users              List all users
    analytics          Analytics summary (--period=7d|30d|90d)
    plugins            List installed plugins
    status             System status overview
    version            Show version
    cache:clear        Clear caches
    help               This help message

  \033[33mExamples:\033[0m
    php cli.php build
    php cli.php analytics --period=30d
    php cli.php status


HELP;
}

// ─── Output Helpers ──────────────────────────────────────────

function cliInfo(string $msg): void
{
    echo $msg . "\n";
}

function cliSuccess(string $msg): void
{
    echo "\033[32m✓ {$msg}\033[0m\n";
}

function cliWarning(string $msg): void
{
    echo "\033[33m⚠ {$msg}\033[0m\n";
}

function cliError(string $msg): void
{
    fwrite(STDERR, "\033[31m✗ {$msg}\033[0m\n");
    if (str_starts_with($msg, 'Unknown command') || str_starts_with($msg, 'Klytos is not')) {
        exit(1);
    }
}
