# Playground — Klytos CMS

> Disposable local verification environment. Keel Phase 5 requires that every runnable project can be
> exercised for real, not only through automated tests. Every command below was executed and its
> result recorded in `docs/05-test-points.md` (slice 0).
>
> **last verified: 2026-07-25** — booted on `KPORT=8083` (8080/8081/8082/8090 squatted for the
> **sixth** consecutive session), admin → 302, anonymous MCP → 401, and the `Server:` header checked
> to confirm the responses came from our own `php -S` and not from the squatter (L-011).

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
# 1. Seed a fresh installation (once; add --reset to re-seed an existing one).
#    XDEBUG_MODE=off is not optional comfort: with Xdebug loaded, the known NEW-03
#    warning prints ~35 KB of stack traces here and the seed LOOKS like a crash
#    when it succeeded. Use it on every php command in this document.
XDEBUG_MODE=off php scripts/dev/seed-playground.php

# 2. Pick a port and CHECK IT IS FREE (see the warning below).
#    Every command in this document uses $KPORT — export it once, here.
export KPORT=8080
nc -z 127.0.0.1 $KPORT && echo "PORT $KPORT IS TAKEN — export a different KPORT and re-run step 2"

# 3. Serve it — 127.0.0.1 ONLY, never a routable interface
php -S 127.0.0.1:$KPORT -t . scripts/dev/router.php
```

> **`$KPORT` is not decoration — set it.** 8080 is the default and it has been occupied by an
> unrelated Docker container in **four consecutive sessions** of this project. Every URL below is
> written `http://127.0.0.1:$KPORT/...` so that changing the port in step 2 is the only edit you
> ever make. A fresh reader following this document with 8080 busy previously had no path forward,
> because the rest of the page hardcoded 8080 — found by the sprint-1 playground-QA pass and fixed
> here.

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
| Admin panel | http://127.0.0.1:$KPORT/installer/admin/ |
| MCP endpoint | http://127.0.0.1:$KPORT/installer/mcp |
| Generated static site | **not available in the playground** — see below |

## Stop and reset

```bash
# Stop: Ctrl-C, or if it was backgrounded
pkill -f "php -S 127.0.0.1:$KPORT"

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

1. Open http://127.0.0.1:$KPORT/installer/admin/ — you should be redirected to `login.php`.
2. Log in as `owner`. You should reach the dashboard.

### ⚠️ Only `owner` can log in — and that is a product bug, not a playground fault

The seeded `admin`, `editor` and `viewer` accounts exist, are `active`, and carry valid password
hashes, but the login form will reject them with *"Incorrect username or password"*.
`Auth::login()` (`core/auth.php:99-102`) validates **only** against `config['admin_user']` and never
calls `UserManager::authenticate()`, which is fully implemented one layer below. Recorded as audit
**NEW-11**; not fixed in Sprint 1 because it is authentication, not authorization.

To exercise the role system, drive it the way the tests do — write the session directly:

> **This section runs a SECOND server, on its own port `$RPORT`.** It needs a private session
> store, which the `$KPORT` server from "Start" does not have — a session minted here is unknown to
> that server, so every cookie-bearing command below must target **`$RPORT`**, not `$KPORT`. And
> `$RPORT` must not be **8099**: that is `upgrade-test.sh`'s default port, and a server left running
> on it makes the upgrade test fail with `the installer did not produce an admin directory` — it
> silently talks to this router instead of the release installer. Found by the sprint-2 playground-QA
> pass, which hit exactly that.

```bash
# Pick a free port for the role-system server — NOT 8099 (upgrade-test.sh owns that).
export RPORT=8093
nc -z 127.0.0.1 $RPORT && echo "PORT $RPORT IS TAKEN — export a different RPORT"

# Start a server with a private, inspectable session store.
SP=/tmp/klytos-sessions; mkdir -p $SP
php -d session.save_path=$SP -d session.serialize_handler=php_serialize \
    -S 127.0.0.1:$RPORT -t . scripts/dev/router.php &

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
curl -s -o /dev/null -w '%{http_code}\n' -b "klytos_session=<the id printed above>" \
  http://127.0.0.1:$RPORT/installer/admin/users.php     # 403 for viewer
```

**When you are done with this section, stop that server** — leaving it up is what breaks the upgrade
test if you ever set `RPORT=8099`, and it costs nothing to be tidy:

```bash
pkill -f "php -S 127.0.0.1:$RPORT"
```

### 2. Authorization — what each role may reach (Sprint 1 slice 4)

> **Send these to `$RPORT`, the server from section 1.** The session cookie is stored in that
> server's private save path and is *anonymous everywhere else* — on `$KPORT` the same requests
> answer 302/401 (correctly, for a caller with no session), which reads like a broken gate and is
> not one.

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

**Read that precisely: the guarantee is include-time, not directory-level.** The gate runs from
`admin/bootstrap.php`, so it protects a file that `require_once`s bootstrap — which every real admin
file does. A file that does *not* include bootstrap executes with no gate at all (a
sprint-2 playground-QA probe confirmed it: a two-line file under `installer/admin/` answered **200
anonymously**). Nothing about the directory refuses it; what catches it is `keel-verify`, which
failed on that same probe for having no map entry, and CI runs keel-verify. So: add the file, add
its map entry, include bootstrap — and let the build tell you if you forgot.

### 3. The MCP endpoint

```bash
# Unauthenticated — must be 401
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://127.0.0.1:$KPORT/installer/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'

# Authenticated — lists the tools (206 on this seed; see the count table below)
APPPW=$(grep -A1 "application password" installer/config/.playground-access | tail -1 | tr -d ' ')
curl -s -u "owner:$APPPW" -X POST http://127.0.0.1:$KPORT/installer/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

MCP rate limiting is 60 requests/minute per identity, plus per-IP blocking on auth failures
(`core/mcp/server.php:84-126`) — expect it to bite in a tight loop. Each bearer token is its own
identity, so the four tokens minted below get 60/minute each.

**How many tools you should see, and why it is not one number.** The seed activates both shipped MCP
plugins, so this playground serves **206**: 172 core (the 34 loader files) + 8 x402 (core, injected
through `mcp.tools_list`) + 16 `klytos-forms` + 10 `klytos-importer`. A **default install** activates
neither plugin and therefore serves **180**. `docs/api/INDEX.md` records 206 — every tool that exists
in the repository. Full breakdown: `docs/reference/mcp-authorization.md` ("How many MCP tools there
are").

### 3a. MCP authorization — `tools/call` per role (Sprint 2)

Every tool call passes a default-deny gate carrying the **credential's** role, and `tools/list` is
filtered by the same decision. Bearer tokens are the one credential mintable below owner today
(application passwords are pinned to the admin user until **NEW-11**), so mint one per role:

```bash
# Mint four bearer tokens, one per role. Prints role=token lines.
XDEBUG_MODE=off php -r 'require "installer/core/app.php";
  $a=\Klytos\Core\App::getInstance(); $a->boot();
  foreach ( ["owner","admin","editor","viewer"] as $r ) {
      echo $r, "=", $a->getAuth()->createBearerToken( "walk-".$r, $r )["token"], "\n";
  }'

# Export them (paste the values the command above printed)
export TOK_OWNER=…  TOK_ADMIN=…  TOK_EDITOR=…  TOK_VIEWER=…

# A refusal, on the wire: a viewer asking for a destructive tool.
curl -s -w '\n%{http_code}\n' -X POST http://127.0.0.1:$KPORT/installer/mcp \
  -H "Authorization: Bearer $TOK_VIEWER" -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call",
       "params":{"name":"klytos_delete_page","arguments":{"slug":"index"}}}'
```

Expected — a JSON-RPC **error object** and **HTTP 403**, never a 200 carrying an error (the body is
one line on the wire; it is wrapped here to fit the page):

```json
{"jsonrpc":"2.0","error":{"code":-32000,"message":"Permission denied: not authorized to call the
tool 'klytos_delete_page'. Ask the site owner to grant this connection the permission it requires."},"id":1}
```

The message comes from the locale catalogues (`mcp.permission_denied`, 20 locales), so a site whose
admin language is Spanish gets it in Spanish. It names the tool and the fix, never the role or the
capability — that detail goes to the audit log (`mcp.access_denied`).

**The per-role expectation table.** Every cell below was measured on this playground on 2026-07-24
(`tools/call` with the four tokens; HTTP status shown). Swap `$TOK_*` and repeat:

| Tool | Capability | owner | admin | editor | viewer |
|---|---|---|---|---|---|
| `klytos_get_page` (read) | `pages.view` | 200 | 200 | 200 | 200 |
| `klytos_delete_page` (destructive) | `pages.delete` | 200 | 200 | **403** | **403** |
| `klytos_x402_get_config` (filter-injected core) | `x402.view` | 200 | 200 | 200 | **403** |
| `klytos_forms_list` (shipped plugin) | `forms.manage` | 200 | 200 | **403** | **403** |
| `klytos_integrity_status` (core, admin-tier) | `site.configure` | 200 | 200 | **403** | **403** |
| `tools/list` size | — | 206 | 197 | 56 | 19 |

A 200 with `"isError":true` in the body is the tool reporting a domain error (a missing slug, say) —
that is the tool running, i.e. the gate **allowed** it. The gate's refusal is always a 403 with a
JSON-RPC `error` object and no `result`.

Two things worth trying, because they are the properties that were actually hard to get right:

```bash
# A name that is neither registered nor declared in the capability map is unknown
# even to the owner. Note the status: this is HTTP 200 with a JSON-RPC error
# (-32602, invalid params) — a PROTOCOL error, not a refusal. The gate's refusal
# is the 403 above. Two different failures, deliberately distinguishable.
curl -s -w '\n%{http_code}\n' -X POST http://127.0.0.1:$KPORT/installer/mcp \
  -H "Authorization: Bearer $TOK_OWNER" -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"klytos_not_a_tool","arguments":{}}}'
# → {"jsonrpc":"2.0","error":{"code":-32602,"message":"Invalid params: Unknown tool: klytos_not_a_tool"},"id":1}  200

# tools/list for a viewer: 19 tools, and none of them destructive.
# (Count the array, not the string "name" — tool SCHEMAS contain that key too.)
curl -s -X POST http://127.0.0.1:$KPORT/installer/mcp \
  -H "Authorization: Bearer $TOK_VIEWER" -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' \
  | python3 -c "import sys,json;print(len(json.load(sys.stdin)['result']['tools']))"
```

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
curl -s -w '\n%{http_code}\n' -X POST http://127.0.0.1:$KPORT/comment-submit.php \
  -d "page_slug=about&author_name=Visitor&content=Hello from nobody in particular."

# The honeypot — answers EXACTLY like a success (201, same shape, a decoy id) and
# stores nothing. A distinguishable response would teach a bot to skip the field.
curl -s -w '\n%{http_code}\n' -X POST http://127.0.0.1:$KPORT/comment-submit.php \
  -d "page_slug=about&author_name=Bot&content=spam&_honeypot=http://spam.test/"

# The rate limit is IP-keyed and PERSISTENT — 2 per 60s. Send a different session
# cookie every time: it makes no difference, which is the whole point (S-09).
for i in 1 2 3 4; do
  curl -s -o /dev/null -w "req $i -> %{http_code}\n" -X POST \
    http://127.0.0.1:$KPORT/comment-submit.php -b "klytos_session=sess$i" \
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
# NOTE: the cookie only works on $RPORT (section 1's server, private session store).
# Sent to $KPORT it arrives anonymous and you measure the 401's headers instead —
# which is a real case, but not this one.
curl -s -D - -o /dev/null -b "klytos_session=<the id from the $RPORT server>" \
  http://127.0.0.1:$RPORT/installer/admin/api/notices.php |
  grep -iE '^X-|^Referrer|^Content-Security|^Permissions|^Strict'

# The login page — it sent NOTHING before slice 8, despite being the most
# security-sensitive page in the product.
curl -s -D - -o /dev/null http://127.0.0.1:$KPORT/installer/admin/login.php | grep -i '^content-security'

# The refusals carry them too. This is the ordering proof: klytos_deny() and the
# auth guard both write a body and exit, so a header set below them would never
# reach the client.
curl -s -D - -o /dev/null http://127.0.0.1:$KPORT/installer/admin/api/plugins.php   # 401 + headers
```

**HSTS is absent here and that is correct** — it is sent only over HTTPS, and the
playground speaks plain HTTP. A browser ignores HSTS on a cleartext response, so
sending it would be a claim the transport cannot back:

```bash
curl -s -D - -o /dev/null http://127.0.0.1:$KPORT/installer/admin/index.php |
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
XDEBUG_MODE=off php installer/cli.php help        # 26 commands
XDEBUG_MODE=off php installer/cli.php status
XDEBUG_MODE=off php installer/cli.php pages
XDEBUG_MODE=off php installer/cli.php logs
```

> **The CLI answers in Spanish, and that is a defect, not a locale setting.** `help` prints
> *"Comandos disponibles:"* whatever the site's language is, because the strings are hardcoded
> Spanish literals in `installer/core/terminal-executor.php` — the product's base language is
> English and every user-facing string is supposed to come from the catalogues (D-006). Recorded as
> audit **NEW-33**; do not read it as your install being misconfigured. Everything the CLI *does* is
> unaffected.

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

> **STOP — two things first, if you have been following this document from the top.**
> Both were found by a fresh-context QA pass at the sprint-1 close, and both make the suite report
> failures that are **artefacts of the walkthrough, not defects in the product**. A newcomer hitting
> them would reasonably conclude Klytos is broken, which is L-008's failure mode reproduced by the
> document that cites it.
>
> ```bash
> # 1. Stop the second server section 1 told you to start and never told you to stop.
> #    Leaving it up makes AdminGateHttpTest error out: the harness refuses to test a
> #    server it did not start (correctly — see AdminHttpTestCase), and 16 tests never run.
> pkill -f "127.0.0.1:8099"
>
> # 2. Re-seed. Walking section 4 by hand STORES a real comment, and
> #    PublicCommentTest::testRateLimitHoldsAcrossSessions counts stored comments.
> php scripts/dev/seed-playground.php --reset
> ```
>
> The paragraph below is true and is **not** a contradiction of this: the integration tier does not
> leave the playground mutated, because it snapshots and restores around every test. What it cannot
> undo is what *you* did by hand before running it.


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

Port **8099** by default (`PORT=8123 bash scripts/dev/upgrade-test.sh` to change it). It does not
collide with the playground on `$KPORT`, so you can leave *that* running — but **nothing else may be
listening on 8099**. If something is, the script's own `curl` reaches the squatter, the install it
believes it made never happened, and it fails with `the installer did not produce an admin directory`
without ever mentioning the port. That is the L-008 trap this document warns about, arriving through
the back door; section 1's server is the likely culprit, which is why it now runs on `$RPORT`.

## Auditing the vendored dependencies

`installer/vendor-ai/` ships 16 pre-installed packages (the AI chat stack). Since Sprint 1 slice 2 it
has a reconstructed manifest at `installer/composer.json`, pinned to exactly what is vendored
(D-028), so it can be audited:

```bash
composer audit -d installer
```

Exit code 1 means advisories were found; the table it prints is the full report. **As of 2026-07-24
this reports 11 advisories affecting 2 packages** — 7 in `guzzlehttp/guzzle` 7.10.0 and 4 in
`guzzlehttp/psr7` 2.9.0, all medium. Seeing them is the current expected output, not a new discovery:
they are recorded as audit finding **NEW-05** and triaged to their own slice (D-029).

**The number grows over time and that is expected** — it was **5** when NEW-05 was triaged on
2026-07-19 and is 11 today, because advisories keep being published against versions that are not
being updated. Do not read a higher number as a regression; read it as the reason the remediation
slice is queued. Check it against the count recorded in `docs/04-adoption-audit.md` NEW-05 rather
than against this sentence, which is a snapshot.

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
  (http://127.0.0.1:$KPORT/installer/admin/logs.php), or `php installer/cli.php logs`.
- **Flip the switch:** admin Settings → Developer → Developer Mode. It is stored as
  `developer.developer_mode` in the site config.
- **When something fails, copy the log entries and paste them back.** That is what turns a failure
  report into a one-round-trip diagnosis instead of twenty questions.

**What the log does NOT contain, so an empty log is not a surprise (found by the sprint-2
playground-QA pass).** Klytos writes a log entry when some code *asks* it to. Authorization refusals
do not: they fire the audit **actions** `auth.access_denied` and `mcp.access_denied`, and **no core
listener subscribes to either** — the hooks are the seam, not the sink (recorded as audit
**NEW-32**). So a walkthrough full of 401s, 403s and MCP permission denials can legitimately leave
`installer/data/logs-*/` empty. To make refusals self-log while you test, subscribe one line from a
plugin:

```php
klytos_add_action( 'mcp.access_denied', function ( string $tool, ?string $role, string $reason ): void {
    klytos_log_warning( "MCP refused {$tool} for role " . ( $role ?? 'none' ) . ": {$reason}", [], 'security' );
}, 10 );
```

Also note the log directory name is **randomized per install** (`data/logs-<12 hex>/`), and a reset
creates a new one while leaving the old ones behind — so `ls data/logs-*/` showing several empty
directories is normal. `php installer/cli.php logs` reads the current one; trust it over the
directory listing.

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
