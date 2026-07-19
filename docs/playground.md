# Playground — Klytos CMS

> Disposable local verification environment. Keel Phase 5 requires that every runnable project can be
> exercised for real, not only through automated tests. Every command below was executed and its
> result recorded in `docs/05-test-points.md` (slice 0).
>
> **last verified: 2026-07-19**

---

## ⚠️ Never run the web installer inside the repository

`installer/install.php` is **destructive to a checkout**. It renames the tracked `install.php` to
`.install.done.php` (`install.php:750`), renames the whole `installer/` directory to `<hex>-admin`,
copies files into the **repository's parent directory** and deletes `../installer.php`
(`install.php:811-824`). `admin/bootstrap.php:138-179` will even retry the rename on the next admin
request.

Use `scripts/dev/seed-playground.php` instead. It writes the two files `App::isInstalled()` checks
for and never touches anything tracked.

---

## Requirements

Verified present on 2026-07-18: PHP 8.3.12 CLI with `openssl`, `json`, `mbstring`, `curl`, `zip`.
No Docker, no MySQL, no Apache — flat-file storage and PHP's built-in server are enough.

## Start

```bash
# 1. Seed a fresh installation (once)
php scripts/dev/seed-playground.php

# 2. Serve it — 127.0.0.1 ONLY, never a routable interface
php -S 127.0.0.1:8080 -t . scripts/dev/router.php
```

| Surface | URL |
|---|---|
| Admin panel | http://127.0.0.1:8080/installer/admin/ |
| MCP endpoint | http://127.0.0.1:8080/installer/mcp |
| Generated static site | **not available in the playground** — see below |

## Stop and reset

```bash
# Stop: Ctrl-C, or if it was backgrounded
pkill -f "php -S 127.0.0.1:8080"

# Wipe all runtime state and seed again from scratch
php scripts/dev/seed-playground.php --reset
```

`--reset` deletes the contents of `installer/config/` and `installer/data/` and the generated site,
**preserving the tracked `.htaccess` guards** in those directories — those are production access
controls, not clutter. Without `--reset` the seeder refuses to run when an installation already
exists, so it can never silently destroy a real local install.

## Credentials — throwaway, local only

**Never reuse these anywhere real.** One user per role, which is what makes authorization testable:

| Username | Password | Role |
|---|---|---|
| `owner` | `playground-owner-2026` | owner |
| `admin` | `playground-admin-2026` | admin |
| `editor` | `playground-editor-2026` | editor |
| `viewer` | `playground-viewer-2026` | viewer |

The MCP **application password** is generated per seed and cannot be documented statically. The
seeder prints it and writes it to `installer/config/.playground-access` (gitignored, mode 0600):

```bash
cat installer/config/.playground-access
```

Everything the playground writes is gitignored — verified with `git check-ignore` and
`git status --untracked-files=all` (evidence in `docs/05-test-points.md`).

## Try it — step by step

### 1. The admin panel and the role system

1. Open http://127.0.0.1:8080/installer/admin/ — you should be redirected to `login.php`.
2. Log in as `owner`. You should reach the dashboard.
3. Log out, log back in as `viewer`.
4. **Today, a `viewer` can reach privileged pages it should not** — for example
   http://127.0.0.1:8080/installer/admin/users.php. That is audit finding S-01/S-07, and closing it
   is Sprint 1's purpose. When slices 4 and 5 land, that URL must return 403 for `viewer`. Until
   then, seeing it succeed is the bug, not a playground fault.

### 2. The MCP endpoint

```bash
# Unauthenticated — must be 401
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://127.0.0.1:8080/installer/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'

# Authenticated — lists the tools (177 on this seed)
APPPW=$(grep -A1 "application password" installer/config/.playground-access | tail -1 | tr -d ' ')
curl -s -u "owner:$APPPW" -X POST http://127.0.0.1:8080/installer/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

MCP rate limiting is 60 requests/minute per identity, plus per-IP blocking on auth failures
(`core/mcp/server.php:84-126`) — expect it to bite in a tight loop.

### 3. The CLI

```bash
php installer/cli.php help        # 26 commands
php installer/cli.php status
php installer/cli.php pages
php installer/cli.php logs
```

> **Do NOT run `php installer/cli.php build` in the repository.** The build engine writes to
> `dirname( rootPath )` (`build-engine.php:57`) — correct in production, the **repository root** in a
> checkout. It **overwrites the tracked root `.htaccess`** and scatters generated `about/`,
> `contact/` and `.well-known/` directories over the repo; the tracked `index.html` landing page sits
> exactly where a front-page build would land. Verified and reverted on 2026-07-18; recorded as audit
> **NEW-04**. Until the output path is injectable, the playground does not cover the static-site
> build — an honest gap, stated rather than hidden.

**The CLI is not a valid surface for testing authorization.** `TerminalExecutor::dispatch()` — the
CLI path — bypasses the permission checks that the web path `execute()` applies. Verify
authorization over HTTP, always.

## Running the tests

The test harness (Sprint 1 slice 1) is dev-only: `composer.json` declares PHPUnit and PHPCS as
`require-dev` and nothing of it ships (D-022, D-027).

```bash
# Once, to install the dev toolchain (needs PHP 8.2+; the product itself runs on 8.1+)
composer install

# Both tiers
XDEBUG_MODE=off vendor/bin/phpunit

# One tier at a time
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite integration

# Lint (baseline-locked per D-025 — zero violations in the files you touched)
vendor/bin/phpcs --standard=phpcs.xml tests/
```

| Tier | Needs the playground? | What it covers |
|---|---|---|
| `unit` | no — runs on a bare checkout | Storage, managers and hooks against a per-test temp directory. No App |
| `integration` | **yes** | The real `App` booted against this playground, with `$_SESSION` set to a seeded role |

**The integration tier does not leave the playground mutated.** Since D-030 every test in that tier
snapshots `installer/config/` + `installer/data/` before it runs and restores them after, so tests
that create, modify or delete records — which slices 3–5 all do — cannot affect each other or
persist across runs. You do not need to reseed between runs.

Two consequences worth knowing before writing a test here:

- A test that mutates **core config** (`config.json.enc`) fails on purpose. `App::$config` is
  decrypted once at boot and has no refresh path, so restoring the file would leave the booted App
  holding the old value and the test would pass for the wrong reason. Use `#[RunInSeparateProcess]`,
  or assert against storage instead of `App::getConfig()`. The same limit applies to options, the
  encryption level and AI keys — the caches are named with file:line in `tests/PlaygroundState.php`.
- If you ever need to opt out, set `protected bool $isolatePlaygroundState = false;` on the test
  class **with a recorded reason**. That is also how the primitive is proven: flipping it makes
  `PlaygroundIsolationTest` fail, which is how it was verified before anything relied on it.

**If the integration tier reports skips, the playground is not seeded** — that is the harness telling
you it has no fixture, not a pass:

```
OK, but some tests were skipped!
Tests: 9, Assertions: 9, Skipped: 5.
```

Seed it (`php scripts/dev/seed-playground.php`) and run again. A skipped authorization test proves
nothing, which is exactly why it refuses to pass quietly.

`XDEBUG_MODE=off` is the same noise suppression the seeder needs, for the same reason — NEW-03, below.

## Testing an upgrade from the real previous release

Keel makes this mandatory because `Installed base: yes`: the upgrade path is tested from the **real
previous version**, never only from a clean install. Slice 3 needed it, since removing the v1.x owner
fallback could in principle lock out a production install.

```bash
# Defaults to v0.30.1; pass any release tag to test a different one.
XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh v0.29.9
```

What it does, in order: exports the tagged release from git into a **temp directory outside the
repo**, runs that release's *own* installer against it over `php -S`, verifies the pre-upgrade state,
overlays the working tree's `core/` and `VERSION` the way the self-updater does, boots the upgraded
install, and asserts the slice-3 properties on it — the owner still resolves and keeps its
permissions, the boot migration is idempotent, and a session with no `klytos_user_id` is denied
rather than promoted.

It never touches the working tree. That is not incidental: `installer/install.php` is destructive to
a checkout (the warning at the top of this document), so the only safe place to run a real
installation is somewhere the repository is not. The script refuses to delete anything outside its
own `mktemp` directory.

Port 8099 by default (`PORT=8123 bash scripts/dev/upgrade-test.sh` to change it), so it does not
collide with the playground on 8080 — you can leave the playground running.

## Auditing the vendored dependencies

`installer/vendor-ai/` ships 16 pre-installed packages (the AI chat stack). Since Sprint 1 slice 2 it
has a reconstructed manifest at `installer/composer.json`, pinned to exactly what is vendored
(D-028), so it can be audited:

```bash
composer audit -d installer
```

Exit code 1 means advisories were found; the table it prints is the full report. **As of 2026-07-19
this reports 5 medium CVEs in `guzzlehttp/guzzle` 7.10.0 and `guzzlehttp/psr7` 2.9.0** — known,
triaged, and recorded as audit finding NEW-05. Seeing them is the current expected output, not a new
discovery.

The manifest also regenerates the tree reproducibly, but **do not run that in a checkout you care
about** — it rewrites 482 tracked files:

```bash
# Lock only, touches nothing vendored (this is what slice 2 ran):
composer update --no-install -d installer

# Full re-vendor — only as a deliberate, reviewed change:
# composer install -d installer
```

`tests/Unit/VendorAiManifestTest.php` fails the suite if the manifest, the lock,
`vendor-ai/composer/installed.php` and `LICENSE-THIRD-PARTY.md` ever stop agreeing.

## The debug log — how to hand back a diagnosable failure

Klytos's log switch is **Developer Mode**. `Logger::write()` (`core/logger.php:116`) discards every
entry unless it is on. The seeder turns it **on**, as Keel requires through Phase 5; Phase 7 verifies
it ships **off**.

- **Read the log in the admin:** Settings → Developer, then the **Logs** page
  (http://127.0.0.1:8080/installer/admin/logs.php), or `php installer/cli.php logs`.
- **Flip the switch:** admin Settings → Developer → Developer Mode. It is stored as
  `developer.developer_mode` in the site config.
- **When something fails, copy the log entries and paste them back.** That is what turns a failure
  report into a one-round-trip diagnosis instead of twenty questions.

## What the router protects, and why it exists

`php -S` has no `.htaccess`, so without `scripts/dev/router.php` the playground would serve
`installer/config/.encryption_key` over HTTP. The router replicates `installer/.htaccess:11-18` and
is a **security control, not convenience plumbing**. All of these return 403 (verified):

`/installer/config/*` · `/installer/data/*` · `/installer/core/*` · `/installer/backups/*` ·
`/installer/plugins/**/*.php` · any dot-segment (`/.git/config`, `.encryption_key`) ·
`admin-identity.*` · `*.pem`

If you add a protected path to `installer/.htaccess`, add it to the router in the same change, or the
playground stops matching production.

## Known noise: a real bug, not a playground fault

Creating a page prints:

```
Warning: Klytos\Core\App::{closure}(): Argument #1 ($data) must be passed by reference,
value given in installer/core/hooks.php on line 145
```

This is **audit finding NEW-03** — `Hooks::doAction()` collects arguments variadically, so
by-reference listeners can never bind and their mutations are silently discarded. It affects every
production install, was found by the playground's first boot, and is scheduled as its own slice (see
`docs/04-adoption-audit.md` and lesson L-005). Do not "fix" it by changing the seeder.

To keep output readable while it is open, run with Xdebug's stack traces off:

```bash
XDEBUG_MODE=off php scripts/dev/seed-playground.php --reset
```
