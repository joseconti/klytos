# 03 — Technical Plan — Klytos CMS

> **Adopted project — reconstructed as-built.** This records the REAL stack, code map, conventions
> and commands as they exist on 2026-07-18, not a plan for how things should be. Conventions are
> observed, not imposed (adoption principle 2). Items marked `as-built, unverified` were inferred
> from reading code and have not been confirmed by a test or by the user.

## 1. Stack and versions

| Concern | Reality |
|---------|---------|
| Language | PHP only. 667 tracked `.php` files; **228 first-party** (rest is vendored) |
| Minimum PHP | **8.1** — enforced at `installer/install.php:911`; documented in `INSTALL.md:22`, `README.md:180` |
| Required extensions | `openssl`, `json`, `mbstring`, `session`, `curl`, `zip` |
| Framework | **None** — custom microframework, WordPress-inspired (hooks, plugins, options, capabilities) |
| Host | Apache + `mod_rewrite`; 6 tracked `.htaccess` files; shared-hosting oriented |
| Storage | Pluggable: flat-file JSON (`file-storage.php`) or MySQL/MariaDB (`database-storage.php`) behind `StorageInterface` |
| Front-end tooling | **None** — no bundler, no transpiler, no npm scripts. Vanilla CSS/JS shipped as-is |
| Dependency manifest | Root `composer.json` since 2026-07-19 (Sprint 1 slice 1) — **`require-dev` only**: PHPUnit + PHPCS, plus `autoload-dev` for the test tiers. The runtime stays dependency-free and nothing ships (`export-ignore`). `composer.lock` is tracked so versions are reproducible (D-022, D-027). No root `package.json` |
| Vendored deps | `installer/vendor-ai/` committed — **17 packages, 509 tracked files** (guzzlehttp, psr/*, ramsey/uuid, ramsey/collection, brick/math, swaggest, symfony polyfills, phplang/scope-exit, ralouphie/getallheaders, soukicz/llm) + TinyMCE under `installer/admin/assets/vendor/`. Since 2026-07-19 (slice 2) reconstructed and pinned by `installer/composer.json` + `installer/composer.lock` (D-028), so `composer audit -d installer` runs. Counts re-measured 2026-07-25 after the Sprint 3 security re-vendor (D-052), which raised guzzle→7.15.1, psr7→2.13.0, promises→2.5.1 and added `symfony/polyfill-php80`. Loaded lazily, only by `App::getChatEngine()` |
| Autoloading | Custom `spl_autoload_register` for `Klytos\Core` at `installer/core/app.php:698`, plus explicit `require_once` chains; Composer's `vendor-ai/autoload.php` loaded lazily from `App::getChatEngine()`, behind the NEW-06 runtime guard (D-053) |
| Output | Static HTML generated into `installer/public/` |

**Risk closed 2026-07-19 (slice 2), with findings.** `vendor-ai/` now has a manifest (D-028), so the
tree is auditable and reproducible; audit H-04 is closed. The first audit reported **5 CVEs across
guzzle 7.10.0 and psr7 2.9.0** (NEW-05, all medium, reachability assessed) and surfaced a
**PHP 8.3 floor in code shipped by a product declaring 8.1+** (NEW-06). Neither was patched there —
D-022's standing rule sends CVE findings to user triage.

**NEW-05 closed 2026-07-25 (Sprint 3 slice 1, D-029 → D-052).** By then the advisory count had grown
5 → **11** on the same pinned versions. The tree was re-vendored to guzzle **7.15.1**, psr7
**2.13.0**, promises **2.5.1** plus a new `symfony/polyfill-php80`, and `composer audit -d installer`
now reports **zero**. D-029's recorded floors (7.12.1 / 2.12.1) would have left 6 open and were
raised to satisfy D-029's own "audit to zero" criterion (D-052). The change is **95 files**, not the
482 this document previously implied — that figure was the size of the whole tree, never the size of
the change. NEW-06 remains open here and is closed in slice 2. Audit command:

```bash
composer audit -d installer
```

## 2. Code map

One line per path. `installer/` is the entire application; everything above it is repo scaffolding.

| Path | Purpose |
|------|---------|
| `installer/install.php` | 85 KB install wizard: requirements check → configuration → completion |
| `installer/index.php` | Front controller — boots the app and hands off to `Router` |
| `installer/cli.php` | Shell adapter over `TerminalExecutor` (`php cli.php <command>`) |
| `installer/t.php` | Analytics tracking beacon |
| `installer/VERSION` | Canonical version string (read at boot into `KLYTOS_VERSION`) |
| `installer/core/app.php` | Service container + bootstrap; owns every manager |
| `installer/core/*.php` | ~60 manager classes — see `docs/api/INDEX.md` for the full list |
| `installer/core/mcp/` | MCP server: `server.php`, `json-rpc.php`, `tool-registry.php`, `token-auth.php`, `oauth-server.php`, `rate-limiter.php` |
| `installer/core/mcp/tools/` | 34 files registering **172** core MCP tools, grouped by domain; 8 more are injected by core x402 through `mcp.tools_list`. Counts and what "served" means: `docs/reference/mcp-authorization.md` |
| `installer/core/ai/` | `ai-key-manager.php`, `chat-manager.php`, `chat-engine.php` — provider keys and the tool-calling loop |
| `installer/core/x402/` | Micropayment gating: config, gate, bot detector, htaccess writer, stats, transaction log, provider registry |
| `installer/core/cache/` | Cache drivers: apcu, file, memcached, null, redis |
| `installer/core/guides/` | 17 Markdown guides served to AI via `klytos_get_guide` |
| `installer/core/lang/` | 20 locale JSON catalogues, 639 keys each |
| `installer/core/keys/` | `klytos-integrity.pub` — RSA **public** key for verifying signed manifests |
| `installer/admin/` | 42 admin page controllers + `bootstrap.php` (auth guard, CSP nonce) |
| `installer/admin/api/` | 24 session+CSRF JSON endpoints (not REST) |
| `installer/admin/templates/` | Admin chrome: `header.php`, `sidebar.php`, `footer.php` |
| `installer/admin/partials/` | 4 AI panels + shared fragments |
| `installer/admin/assets/css/` | `klytos-tokens.css` (118 tokens) + base, components, sidebar, utilities, editor, plugins, logs (~4,900 lines) |
| `installer/plugins/` | 5 bundled plugins: `hello-ai`, `klytos-forms`, `klytos-importer`, `klytos-x402-coinbase`, `klytos-x402-stripe` |
| `installer/templates/`, `installer/custom-templates/` | Frontend HTML templates + `parts/` |
| `installer/public/` | Generated static output (gitignored) + `x402-gate.php` |
| `installer/config/`, `installer/data/`, `installer/backups/` | Runtime state — contents gitignored, only `.htaccess` guards tracked |
| `installer/docs/KLYTOS-ARCHITECTURE.md` | The substantial pre-Keel technical reference |
| `docs/` | Keel artifacts (this directory) + `FEATURE-INTEGRITY-DEVELOPERS.md`, `media/` |
| `.claude/skills/`, `.agents/skills/` | 31 `klytos-*` domain skills + the embedded Keel v3.3.0 (both trees) |

## 3. Conventions (observed — this is the contract for new code)

- **Naming.** Global procedural API is `klytos_*` **snake_case** (`klytos_get_option`,
  `klytos_esc_html`). Classes are `PascalCase` under `Klytos\Core` (and `\MCP`, `\Ai`, `\X402`,
  `\Cache` sub-namespaces). Methods are `camelCase` — except the helper files, explicitly exempted
  in `phpcs.xml`.
- **Style.** PSR-12 as adapted by `phpcs.xml` (ruleset name `Klytos`): line limit 150 (warning),
  9 spacing sniffs disabled so WordPress-style spaces inside parentheses are permitted —
  `foo( $bar )`, not `foo($bar)`. **This is the project's style; do not "correct" it.**
- **Hooks.** `klytos_do_action('domain.event', ...)` / `klytos_apply_filters('domain.thing', $v, ...)`.
  Names are dot-namespaced and lowercase (`page.after_save`, `admin.sidebar_items`,
  `mcp.tool_response`). Every meaningful decision fires a hook before/after; every user-facing
  string, query and response should be filterable (Keel's extensibility rule — see the gap in the
  audit for where the read path falls short).
- **Escaping and sanitization.** Output goes through `klytos_esc_html/attr/url/js/textarea`; input
  through `klytos_sanitize_*`; rich HTML through `klytos_kses` / `klytos_kses_post`. Never echo raw.
- **CSRF.** Every admin form carries `klytos_csrf_field()`; every mutating endpoint calls
  `klytos_verify_csrf()`.
- **CSP.** Every inline `<script>`/`<style>` in the admin carries `nonce="$cspNonce"` (from
  `core/auth.php:787`) or the browser blocks it. No inline `onclick`/`onchange` handlers —
  `addEventListener` inside a nonced script block.
- **Permissions.** `klytos_has_permission('domain.action')` — the live implementation is in
  `helpers-global.php:430`. **Every** admin page and API endpoint must gate on it (this is the
  single largest as-built gap; see audit S-01…S-07).
- **i18n.** `__('domain.key')` with the key added to all 20 catalogues in the same change.
- **Time.** Store UTC, display local: `klytos_gmdate` / `klytos_date` / `klytos_timezone`.
- **Comments/docblocks.** Every file carries a GPL header with `@copyright`; public surfaces carry
  a docblock. Comments in English.
- **Plugin contract (immutable).** Plugin ID = directory name = `{id}.php` = the PHP header.
  `klytos-plugin.json` is an optional extension, never the identity.

## 4. Testing

Adopted as-built with **nothing**. The harness below was built in Sprint 1 slice 1 (audit T-01, T-04).

| Layer | Reality |
|-------|---------|
| Unit | **`tests/Unit`, base `Klytos\Tests\UnitTestCase`.** No App and no installation: each test gets its own temp directory, its own encryption key and its own `FileStorage`, with `Hooks` reset around every test. Runs on a bare checkout |
| Integration | **`tests/Integration`, base `Klytos\Tests\IntegrationTestCase`.** Boots the real `App` against the seeded playground; `actingAs( $role )` / `actingAsGuest()` assign `$_SESSION`. This is the seam the authorization slices assert refusals through — authorization is not unit-testable here, since the decision spans App + Auth + UserManager at once. Skips loudly when the playground is not seeded |
| E2E | **None.** No browser-driven tier; the playground is walked by hand and by the fresh-context playground-QA pass |
| Static analysis | `phpstan.neon` is export-ignored in `.gitattributes` but **does not exist** (audit T-03) |
| Lint | `phpcs.xml`, now covering `tests/` as well as `installer/core`, `installer/admin`, `installer/plugins`. Command: `vendor/bin/phpcs --standard=phpcs.xml`. Baseline-locked per D-025. **Measured 2026-07-19, per ruleset path:** `core`+`admin` **204 errors / 488 warnings in 114 files (unchanged from 2026-07-18)** · `plugins` **131 / 109 in 25 files** · `tests` **0 / 0** · **whole ruleset 335 / 597 in 139 files**. The plugins figure had never been measured — D-025 recorded the baseline over only two of the ruleset's three paths, so the unscoped command returned a number no document explained. All three are now locked: none may grow |
| Test commands | `composer install`, then `vendor/bin/phpunit` (both tiers), `--testsuite unit`, `--testsuite integration`. Composer script aliases: `composer test`, `composer test:unit`, `composer test:integration`, `composer lint` |
| Runtime constraint | The suite needs **PHP 8.2+** (PHPUnit 11), while the product supports 8.1+ — so PHP 8.1 cannot be verified through the suite. Deliberate, with a revisit trigger: D-027 |
| CI | **None** — no workflow runs anything. `.github/` exists but carries only the assistant-config instruction files (D-010), no Actions |

**Playground: EXISTS since 2026-07-18** (Sprint 1 slice 0) — `scripts/dev/seed-playground.php` +
`scripts/dev/router.php`, documented in `docs/playground.md`. No Docker and no web installer: the
seeder writes the two files `App::isInstalled()` checks for, and `php -S` serves the app because
`Router::parseRoute()` falls back to `REQUEST_URI`. The router replicates the `.htaccess` denies,
which `php -S` would otherwise drop — without it the playground would serve
`installer/config/.encryption_key` over HTTP. One user per role is seeded, which is what makes the
authorization work testable. A MySQL/docker tier was **not** built: file storage covers Sprint 1, and
`database-storage.php` verification remains an open gap.

**Gate zero (amended by D-025).** Originally "`phpcs` clean + the app boots + one MCP `tools/list`
round trip". The lint condition is now **baseline-locked**: zero violations in the files a slice
touches, and the 204/488 baseline does not grow. The other two conditions are unchanged and were met
on 2026-07-18 (app boots; `tools/list` returned 177 tools). Evidence in `docs/05-test-points.md`.

**Never run `installer/install.php` or `installer/cli.php build` in a checkout** — both are
destructive to the repository (`install.php:750`, `:811-824`; `build-engine.php:57`). See audit
NEW-04 and `docs/playground.md`.

## 5. Version touchpoints (currently in four-way disagreement)

| Location | Value on 2026-07-18 |
|----------|---------------------|
| `installer/VERSION` (canonical — read at `core/app.php:34`) | `0.31.1-beta.1` |
| `README.md:7` and `README.md:519` | `0.28.5` |
| `changelog.txt:3` (only entry in the file) | `0.4.0` |
| Newest git tag | `v0.30.1` |

There is no hardcoded PHP version constant — `KLYTOS_VERSION` is derived from the `VERSION` file.
Bundled plugin versions are all frozen at `1.0.0` and are decoupled from core. Nothing reconciles
these; a `scripts/keel-verify` release linter is the Keel-standard fix (audit H-01).

## 6. Distribution and release

- **Install:** standalone `installer.php` from `https://github.com/joseconti/klytos-installer`
  uploaded to the domain root, or manual ZIP extraction + `/installer/install.php`.
- **Update:** self-updater against GitHub Releases (`core/updater.php`), with backup and rollback;
  CLI equivalent `php cli.php update:run`.
- **Packaging:** `git archive` semantics driven by `.gitattributes` alone. No build script, no
  release workflow, no version automation.
- **Known packaging defect:** the blanket `*.md export-ignore` strips `README.md` and `INSTALL.md`
  from release archives, although `INSTALL.md` instructs users to upload them (audit H-02).

## 7. Security profiles in force

`references/security/web-app.md` **and** `references/security/mcp-server.md` (D-003). Applied
findings live in `docs/04-adoption-audit.md`.

## 8. Debug logging (Keel requirement — partially present)

`Klytos\Core\Logger` exists with PSR-style levels and the `klytos_log_*` helpers, plus an admin
`logs.php` viewer and `plugin.logs_enabled` / `plugin.logs_disabled` actions. What is not confirmed
`as-built, unverified`: whether there is a single documented user-facing switch, OFF by default at
release, that a user can flip to hand back a debuggable failure in one round trip. To verify and
close in the first Phase 5 sprint.
