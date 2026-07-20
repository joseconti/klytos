# Playground — Klytos CMS

> Disposable local verification environment. Keel Phase 5 requires that every runnable project can be
> exercised for real, not only through automated tests. Every command below was executed and its
> result recorded in `docs/05-test-points.md` (slice 0).
>
> **last verified: 2026-07-20**

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

# 2. Check nobody else already owns the port (see the warning below)
nc -z 127.0.0.1 8080 && echo "PORT 8080 IS TAKEN — pick another or stop the squatter"

# 3. Serve it — 127.0.0.1 ONLY, never a routable interface
php -S 127.0.0.1:8080 -t . scripts/dev/router.php
```

> **Always run step 2.** `php -S` fails to bind a busy port, prints its error and exits — and if you
> backgrounded it, you will not see that. Every subsequent `curl` then reaches **whatever else is on
> 8080**, and reads as a Klytos response. This is not hypothetical: on 2026-07-19 a Docker container
> from an unrelated project held 8080, and the session-start verification recorded an MCP endpoint
> answering `302` where it documents `401` — a result that looked exactly like a regression in the
> slice-4 gate and was an unrelated Apache. The tell was `Server: Apache/2.4.54 (Debian)` in the
> response headers; PHP's built-in server never sends that. `curl -D -` when a result surprises you.
>
> The automated harness already refuses to run in this situation — `tests/AdminHttpTestCase.php`
> fails loudly rather than testing a server it did not start. This document had no equivalent.
> Same defect, same fix (L-008: suspect the harness before the product).

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

### ⚠️ Only `owner` can log in — and that is a product bug, not a playground fault

The seeded `admin`, `editor` and `viewer` accounts exist, are `active`, and carry valid password
hashes, but the login form will reject them with *"Incorrect username or password"*.
`Auth::login()` (`core/auth.php:99-102`) validates **only** against `config['admin_user']` and never
calls `UserManager::authenticate()`, which is fully implemented one layer below. Recorded as audit
**NEW-11**; not fixed in Sprint 1 because it is authentication, not authorization.

To exercise the role system, drive it the way the tests do — write the session directly:

```bash
# Start a server with a private, inspectable session store.
SP=/tmp/klytos-sessions; mkdir -p $SP
php -d session.save_path=$SP -d session.serialize_handler=php_serialize \
    -S 127.0.0.1:8099 -t . scripts/dev/router.php &

# Mint a session for any seeded role and print its cookie value.
XDEBUG_MODE=off php -r '
require "installer/core/app.php"; $a=\Klytos\Core\App::getInstance(); $a->boot();
$u=(new \Klytos\Core\UserManager($a->getStorage()))->getByUsername($argv[1]);
$sid=substr(hash("sha256","try-".$argv[1]),0,32);
file_put_contents("/tmp/klytos-sessions/sess_".$sid, serialize([
  "klytos_auth"=>true,"klytos_user"=>$u["username"],"klytos_user_id"=>$u["id"],
  "klytos_login_time"=>time(),"klytos_last_active"=>time(),"klytos_csrf"=>str_repeat("a",64)]));
echo $sid."\n";' viewer
```

The cookie is named **`klytos_session`**, not `PHPSESSID` (`auth.php:61`):

```bash
curl -s -o /dev/null -w '%{http_code}\n' -b "klytos_session=<the id>" \
  http://127.0.0.1:8099/installer/admin/users.php     # 403 for viewer
```

### 2. Authorization — what each role may reach (Sprint 1 slice 4)

Since slice 4 every admin surface is gated centrally and **denies by default**. A `viewer` gets:

| URL | Expected |
|---|---|
| `/installer/admin/users.php` | **403** — HTML refusal page, not a redirect |
| `/installer/admin/api/plugins.php` | **403** — `{"error":"…","code":"forbidden"}` |
| `/installer/admin/profile.php` | **200** — self-service, every role holds `profile.edit` |
| `/installer/admin/index.php` | **200** — the dashboard is reachable by all roles |

Anonymous (no cookie at all):

| URL | Expected |
|---|---|
| `/installer/admin/users.php` | **302** to `login.php` — a browser should land on the form |
| `/installer/admin/api/plugins.php` | **401** `{"…","code":"authentication_required"}` — **not** a 302 |

If you add a new file under `installer/admin/`, it is **denied to everyone, including the owner,**
until you add it to `klytos_admin_gate_map()` in `installer/core/admin-gate.php`. That is deliberate.
`php scripts/keel-verify` tells you which files are missing an entry.

### 3. The MCP endpoint

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

### 4. Public comments — the one anonymous write surface (Sprint 1 slice 7)

`/comment-submit.php` is the only endpoint that accepts a write from a caller with no
identity. It is served from the **web root**, not from the admin directory, so nothing
a visitor sees names the randomized `<hex>-admin` folder. Full reference:
`docs/reference/public-comments.md`.

Comments ship **disabled**, so switch them on first:

```bash
XDEBUG_MODE=off php -r 'require "installer/core/app.php";
  $a=\Klytos\Core\App::getInstance(); $a->boot();
  $a->getSiteConfig()->setValue( "comments_enabled", true );'
```

```bash
# Anonymous submission — 201, and the comment is stored as "pending".
curl -s -w '\n%{http_code}\n' -X POST http://127.0.0.1:8080/comment-submit.php \
  -d "page_slug=about&author_name=Visitor&content=Hello from nobody in particular."

# The honeypot — answers EXACTLY like a success (201, same shape, a decoy id) and
# stores nothing. A distinguishable response would teach a bot to skip the field.
curl -s -w '\n%{http_code}\n' -X POST http://127.0.0.1:8080/comment-submit.php \
  -d "page_slug=about&author_name=Bot&content=spam&_honeypot=http://spam.test/"

# The rate limit is IP-keyed and PERSISTENT — 2 per 60s. Send a different session
# cookie every time: it makes no difference, which is the whole point (S-09).
for i in 1 2 3 4; do
  curl -s -o /dev/null -w "req $i -> %{http_code}\n" -X POST \
    http://127.0.0.1:8080/comment-submit.php -b "klytos_session=sess$i" \
    -d "page_slug=about&author_name=N&content=msg$i"
done
```

Expect `201`, then `201` again (the honeypot, indistinguishable on purpose — check
`klytos_list_comments` or the admin Comments page to see that it stored nothing), then
`429`s once the window is spent. The flood ceiling is 10 per minute per address and the
comment policy is 2; reset by waiting 60 seconds or deleting
`installer/data/rate_limits.json`.

Switch comments back off when you are done, so the playground matches its documented
default:

```bash
XDEBUG_MODE=off php -r 'require "installer/core/app.php";
  $a=\Klytos\Core\App::getInstance(); $a->boot();
  $a->getSiteConfig()->setValue( "comments_enabled", false );'
```

> **There is no comment FORM on the generated site yet**, by decision — nothing in the
> build emits one, so the endpoint is exercised with `curl` and not through a page.
> Form emission belongs to the theme-package sprint (D-023), which is replacing the
> template layer that would carry it.

### 5. Security headers — every admin surface, including the refusals (Sprint 1 slice 8)

Since slice 8 the headers are decided in one place and sent from one place, so
**every** admin page and API endpoint carries them — including the 401 and 403
refusals. Full contract: `docs/reference/security-headers.md`.

```bash
# An admin API endpoint. Before slice 8, 0 of the 23 files in admin/api/ sent anything.
curl -s -D - -o /dev/null -b "klytos_session=<the id>" \
  http://127.0.0.1:8080/installer/admin/api/notices.php |
  grep -iE '^X-|^Referrer|^Content-Security|^Permissions|^Strict'

# The login page — it sent NOTHING before slice 8, despite being the most
# security-sensitive page in the product.
curl -s -D - -o /dev/null http://127.0.0.1:8080/installer/admin/login.php | grep -i '^content-security'

# The refusals carry them too. This is the ordering proof: klytos_deny() and the
# auth guard both write a body and exit, so a header set below them would never
# reach the client.
curl -s -D - -o /dev/null http://127.0.0.1:8080/installer/admin/api/plugins.php   # 401 + headers
```

**HSTS is absent here and that is correct** — it is sent only over HTTPS, and the
playground speaks plain HTTP. A browser ignores HSTS on a cleartext response, so
sending it would be a claim the transport cannot back:

```bash
curl -s -D - -o /dev/null http://127.0.0.1:8080/installer/admin/index.php |
  grep -ci strict-transport      # expect 0
```

**Checking the CSP in a browser** (the part only a person can do): open any admin
page, then the DevTools console. It should be **empty** — no
`Refused to execute inline script` messages. Every inline `<script>` in the admin
carries the request's nonce. Two known exceptions that are *not* violations:
`page-editor.php` sets its own policy allowing inline script (**NEW-21**), and the
public generated site keeps `unsafe-inline` because a build-time file cannot hold
a per-request nonce (**NEW-23**).

If you add an inline `<script>` to an admin page, it needs
`nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"` — the CSP now **fails
closed**, so an un-nonced block is silently refused by the browser rather than
quietly allowed.

### 6. The CLI

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

## Running keel-verify

The project's own release linter (Keel Phase 5 §1a). Introduced in Sprint 1 slice 4 carrying the
authorization-gate check; slice 9 extends it with the remaining checks.

```bash
php scripts/keel-verify
```

Exit 0 means every mechanical promise the docs make is currently true. Exit 1 lists what broke. The
gate check is the one that matters today: it fails the build when any file under `installer/admin/`
has no entry in `klytos_admin_gate_map()`, and when `admin/bootstrap.php` stops calling
`klytos_enforce_admin_gate()` — a complete map enforces nothing if nobody invokes the enforcer.

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
