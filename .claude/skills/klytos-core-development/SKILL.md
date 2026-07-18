---
name: klytos-core-development
description: Guide for developing and maintaining Klytos CMS core. Use when modifying core files, adding MCP tools, fixing bugs, extending the core architecture, changing the build engine, modifying the installer, implementing hooks, or working with storage backends. Covers foundational principles (AI-first, privacy by design, security by default), architecture, boot sequence, manager pattern, MCP tool registration, and security checklist.
---

# Klytos Core Development Guide

## Foundational Principles

1. **AI-First**: MCP tools are the PRIMARY interface. Admin UI is secondary.
2. **Privacy by Design**: GDPR/RGPD compliant from line 1. No cookies, no tracking, no PII.
3. **Security by Default**: AES-256-GCM encryption, bcrypt passwords, CSRF, rate limiting.
4. **Plugin Extensibility**: Every feature must fire hooks so plugins can extend it.
5. **Dual Storage**: All code must work with both FileStorage AND DatabaseStorage.

## Architecture

```
installer/
├── index.php              ← Front controller (routes all requests)
├── install.php            ← Multi-step installer
├── t.php                  ← Analytics tracking pixel
├── config/                ← Encrypted config files (.htaccess blocks)
├── core/                  ← PHP source (Klytos\Core namespace)
│   ├── app.php            ← Application bootstrap (singleton)
│   ├── storage-interface.php ← Storage abstraction
│   ├── file-storage.php   ← Flat-file storage implementation
│   ├── database-storage.php ← MySQL/MariaDB implementation
│   ├── hooks.php          ← Action/filter hook engine
│   ├── helpers-global.php ← klytos_*() global functions
│   ├── plugin-loader.php  ← Plugin discovery and loading
│   ├── page-manager.php   ← Page CRUD
│   ├── theme-manager.php  ← Theme configuration
│   ├── menu-manager.php   ← Navigation menus
│   ├── site-config.php    ← Global settings
│   ├── user-manager.php   ← Multi-user with roles
│   ├── task-manager.php   ← Review tasks/annotations
│   ├── version-manager.php ← Page version history
│   ├── block-manager.php  ← Modular HTML blocks
│   ├── page-template-manager.php ← Page template recipes
│   ├── analytics-manager.php ← Privacy-first analytics
│   ├── webhook-manager.php ← Event notifications
│   ├── cron-manager.php   ← Pseudo-cron scheduler
│   ├── audit-log.php      ← Action audit trail
│   ├── auth.php           ← Authentication (session, bearer, OAuth, app passwords)
│   ├── encryption.php     ← AES-256-GCM encryption
│   ├── build-engine.php   ← Static site generator
│   ├── helpers.php        ← Utility functions
│   ├── i18n.php           ← Internationalization
│   ├── license.php        ← Plugin license verification
│   ├── updater.php        ← OTA update system
│   ├── router.php         ← Request routing
│   ├── lang/              ← Translation files (en.json, es.json)
│   └── mcp/               ← MCP server implementation
│       ├── server.php     ← JSON-RPC 2.0 HTTP server
│       ├── tool-registry.php ← Tool registration and dispatch
│       ├── token-auth.php ← Multi-method auth (Bearer → OAuth → Basic)
│       ├── oauth-server.php ← OAuth 2.0/2.1 with PKCE
│       ├── rate-limiter.php ← Sliding window rate limiter
│       ├── json-rpc.php   ← JSON-RPC 2.0 parser/builder
│       └── tools/         ← MCP tool definitions
├── admin/                 ← Admin panel pages
│   ├── setup-wizard.php   ← Post-install setup wizard (2FA, AI keys, MCP)
├── plugins/               ← Plugin directory
├── public/                ← Static site output
├── data/                  ← Encrypted data storage
├── backups/               ← Backup archive storage
└── templates/             ← HTML templates
```

## Boot Sequence (App::boot())

1. Register PSR-4 autoloader (CamelCase → kebab-case files).
2. Check installation status.
3. Initialize AES-256-GCM encryption.
4. Create storage backend (FileStorage or DatabaseStorage based on config).
5. Load main configuration.
6. Initialize i18n.
7. Initialize License manager (for plugin licenses only — core is free).
8. Initialize Auth.
9. Initialize all content managers.
10. Load Hooks engine + global helpers.
11. Load PluginLoader → discover and load active plugins.
12. Fire `klytos.init` action.

## Installation & Setup Flow

1. **Bootstrap** (`klytos-installer/installer.php`): Downloads CMS from GitHub releases.
2. **Installer** (`install.php`): Simplified wizard — site name, username, password, dark/light preference, storage type. Sets `setup_completed => false` in config. Redirects to login after completion.
3. **First Login**: User logs in (always dark theme). `bootstrap.php` detects `setup_completed === false` and redirects to `setup-wizard.php`.
4. **Setup Wizard** (`admin/setup-wizard.php`): 5-screen guided setup:
   - Screen 1: 2FA (TOTP) setup (skippable)
   - Screen 2: Connection type (MCP / API Keys / Both)
   - Screen 3: AI provider API keys (conditional, skippable)
   - Screen 4: Application Password + MCP config (copy-paste ready)
   - Screen 5: Congratulations + AI prompt for site creation
5. **Completion**: Sets `setup_completed => true`. Existing/upgraded installs skip the wizard (key doesn't exist in config).

## Adding a New MCP Tool to Core

1. Create file in `core/mcp/tools/{feature}-tools.php`.
2. Define a `registerXxxTools(ToolRegistry $registry, App $app)` function.
3. Use `$registry->register(name, description, schema, handler, annotations)`.
4. Include the file in the MCP server's tool registration section.
5. Add the GPL-3.0-or-later license header.

## Adding a New Manager

1. Create `core/{feature}-manager.php` in the `Klytos\Core` namespace.
2. Accept `StorageInterface` in the constructor (NEVER `Storage` or `FileStorage`).
3. Define a `const COLLECTION = '{name}'` for the storage collection.
4. Fire hooks at key points: `{feature}.before_save`, `{feature}.after_save`, etc.
5. Add it to `App::boot()` as a property with a getter.
6. Create corresponding MCP tools.
7. Add the GPL-3.0-or-later license header.

## Storage Interface

All managers MUST use `StorageInterface`, never a concrete implementation:

```php
// Collection + ID paradigm:
$this->storage->read('pages', 'about');           // Read
$this->storage->write('pages', 'about', $data);   // Write (upsert)
$this->storage->delete('pages', 'about');          // Delete
$this->storage->exists('pages', 'about');          // Check existence
$this->storage->list('pages', ['status' => 'published']); // List with filters
$this->storage->count('pages', ['status' => 'draft']);     // Count
$this->storage->search('pages', 'keyword', ['title']);     // Search
$this->storage->transaction(function($storage) { ... });   // Transaction
```

## Fatal Error Handling

A `register_shutdown_function` in `admin/bootstrap.php` catches PHP fatal errors after boot:
- Catches `E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR`
- Logs to PHP `error_log()` as fallback
- Logs to Klytos logs via `Logger::writeAlways()` (bypasses Developer Mode)
- Wrapped in try-catch so logger failures don't mask the original error

`Logger::writeAlways()` is the unconditional counterpart to `Logger::write()`. It skips Developer Mode and per-plugin checks. Use it only for fatal/critical errors that must always be recorded.

## Testing

The project has a real test harness. Use it — a core change asserted only by reading the diff is unverified.

```bash
composer install                 # dev-only manifest: PHPUnit + PHPCS (nothing ships)
vendor/bin/phpunit               # both tiers
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite integration
vendor/bin/phpcs --standard=phpcs.xml <paths>
```

**Two tiers, and the difference matters:**

| Tier | Base class | What it gets | When to use it |
|---|---|---|---|
| `tests/Unit` | `Klytos\Tests\UnitTestCase` | No App. A per-test temp dir with its own encryption key and `FileStorage`; `Hooks` reset around every test | Storage, managers, hooks, pure logic. Runs on a bare checkout |
| `tests/Integration` | `Klytos\Tests\IntegrationTestCase` | The real `App` booted against the seeded playground; `actingAs( 'viewer' )` / `actingAsGuest()` set the session | Anything spanning App + Auth + UserManager — above all **authorization** |

- **Authorization is not unit-testable in this codebase.** The permission decision runs through the App singleton, Auth's session state and UserManager's stored roles at once. Assert refusals through the integration tier.
- The integration tier needs the playground (`php scripts/dev/seed-playground.php`). Without it those tests **skip loudly** — they never pass silently.
- `actingAs()` mirrors the session shape of `Auth::login()`. `tests/Integration/HarnessTest.php` guards that mirror; if Auth's session keys change, it fails there first.
- Rules: a behaviour change ships with its named test in the same slice; a fixed bug ships with its regression test; a test is never deleted, skipped or loosened to make it pass.

## Security Checklist for Core Changes

- [ ] All user input sanitized (htmlspecialchars, Helpers::sanitizeHtml)
- [ ] SQL queries use prepared statements (DatabaseStorage handles this)
- [ ] No raw IPs stored (hash with Helpers or AnalyticsManager pattern)
- [ ] Hooks fired at key points (before/after save/delete)
- [ ] Errors logged, not exposed to users
- [ ] Fatal errors caught by shutdown handler (always logged)
- [ ] File permissions 0700 for directories, 0600 for keys
- [ ] CSRF tokens validated on all POST forms
- [ ] Rate limiting on public-facing endpoints
- [ ] GPL-3.0-or-later license header included

## Coding Standards

- PHP 8.1+ with `declare(strict_types=1)`.
- All code in English (comments, variable names, function names).
- Descriptive class/method/variable names.
- PHPDoc on every public method.
- Namespace: `Klytos\Core` (autoloaded via kebab-case filenames).
- License: GPL-3.0-or-later header on every file.
- Copyright: José Conti (https://plugins.joseconti.com — https://klytos.io).

## SEO Requirements for Built Pages

Every page generated by the build engine MUST include:
- `<meta name="generator" content="Klytos {version}">`
- `<meta name="description" content="...">`
- Open Graph tags (og:title, og:description, og:image, og:url, og:type)
- Twitter Card tags
- Canonical URL
- hreflang tags (if multilingual)
- JSON-LD structured data (WebPage schema)
- Link to sitemap.xml in robots.txt
- llms.txt for AI crawler indexing

## MANDATORY RULE: Translations

Every new translation key added to the core MUST exist in `installer/core/lang/en.json`.
The `en.json` file is the master reference. If a key is added to `es.json` without adding
it to `en.json`, the Translation Manager will not discover it.

Correct workflow:
1. Add the key in `en.json` (English).
2. Add the translation in `es.json` (and other languages if desired).
3. Use the key in code with `__('namespace.key')`.
