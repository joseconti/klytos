# Playground — Klytos CMS

> Disposable local verification environment. Keel Phase 5 requires that every runnable project can be
> exercised for real, not only through automated tests. Every command below was executed and its
> result recorded in `docs/05-test-points.md` (slice 0).

## Step 0 — before anything else, in every session (Keel v5.0.0)

```sh
export PATH="/opt/local/bin:$HOME/.composer/vendor/bin:$PATH"   # see below — this is not optional
./scripts/keel-doctor --check
```

**Why the PATH line comes first.** A non-interactive shell here starts with
`PATH=/usr/bin:/bin:/usr/sbin:/sbin` and does **not** source the user's profile, so PHP, Composer and
Node are all invisible to a bare `command -v`. A session that skips this concludes the machine has no
PHP — which is wrong, and which would classify the session as unable to run anything and push every
test back onto the user. `keel-doctor` widens PATH itself for exactly this reason; the export is for
the commands you run by hand afterwards.

**Why the doctor comes before the playground.** A green suite on an environment that could not
actually run it is worse than no result at all. `--check` never installs anything and never needs
sudo; `--plan` prints the literal commands it *would* run without running them; `--fix` runs them
only after you say yes to the printed list.

This applies to **maintenance sessions too**, not only development ones — a hotfix verified on a
half-configured machine is the same defect as a feature verified that way.
>
> **last verified: 2026-07-27 (Sprint 6 slice 3)** — the Start section was run verbatim on
> `KPORT=8115`: seed (`--reset`) clean, `php -S` bound cleanly with the log checked for
> `failed to listen`, owning PID confirmed by `lsof` (php83, 17804), the two documented responses
> reproduced exactly (admin `302`, MCP `401`), and `curl -D -` carried **no `Server:` header** — the
> L-011 tell. Two things were learned here and are now written into the document rather than left to
> the next session: a bare `GET /installer/mcp` answers **200** `{"name":"klytos","status":"ok"}` (a
> health response, not a regression — §3's documented check is a **POST**, and running the wrong
> command was my instrument being wrong, not the product); and the §3 401 probe **writes** to the
> auth-failure bucket, which then failed `LoginCeilingHttpTest` on unmodified code until the
> playground was reseeded (**L-028**).
>
> Previously — **2026-07-26 (Sprint 6 slice 2)** — the same section on
> `KPORT=8113`: seed (`--reset`) clean, `php -S` bound cleanly with the log checked for
> `failed to listen`, owning PID confirmed by `lsof` (php83, 73855), the two documented responses
> reproduced exactly (admin `302`, MCP `401`), `curl -D -` carried **no `Server:` header** — the
> L-011 tell — and the Stop recipe was run and the port re-checked free before the suite, because the
> playground is single-tenant (L-025).
>
> Previously — **2026-07-26 (Sprint 6 slice 1 close)** — the same section on `KPORT=8112`, owning PID
> php83 63899, identical responses. Also verified earlier that day at the sprint kickoff, where
> **8080 was squatted for the ninth consecutive session** (Docker, PID 45413, confirmed by `lsof`).
>
> Previously — **2026-07-25 (Sprint 4 close)** — a fresh-context pass ran this document end to end
> on `KPORT=8110` / `RPORT=8111`, ~45 commands, and every product claim it checked held: the 5×4
> per-role table reproduced exactly, `tools/list` sizes 206/197/56/19 exact, all **eight** router
> protections 403, the full security-header set present, suite `OK (227 tests, 1059 assertions)` with
> no skips, and `keel-verify` 10 checks exit 0. Six DOCUMENT defects were found and fixed here.
> 8080 was squatted for the **eighth** consecutive session (2026-07-25).

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
#    XDEBUG_MODE=off is not optional comfort: with Xdebug loaded, ANY diagnostic
#    prints tens of KB of stack traces here and a seed that succeeded LOOKS like
#    a crash. Use it on every php command in this document.
XDEBUG_MODE=off php scripts/dev/seed-playground.php

# 2. Pick a port and CHECK IT IS FREE (see the warning below).
#    Every command in this document uses $KPORT — export it once, here.
export KPORT=8080
nc -z 127.0.0.1 $KPORT && echo "PORT $KPORT IS TAKEN — export a different KPORT and re-run step 2"

# 3. Serve it — 127.0.0.1 ONLY, never a routable interface.
#    In the FOREGROUND a bind failure is visible: php -S prints it and exits.
php -S 127.0.0.1:$KPORT -t . scripts/dev/router.php

#    If you background it instead, that failure becomes SILENT — so redirect and
#    check, exactly as the $RPORT section does:
#      php -S 127.0.0.1:$KPORT -t . scripts/dev/router.php > /tmp/klytos-kport.log 2>&1 &
#      sleep 2
#      grep -i 'failed to listen' /tmp/klytos-kport.log \
#        && echo "DID NOT BIND — everything below would answer from someone else's server" \
#        || echo "bound cleanly on $KPORT"
```

> **`$KPORT` is not decoration — set it.** 8080 is the default and it has been occupied by an
> unrelated Docker container in **every session since 2026-07-19** (ten consecutive, most recently 2026-07-27) of this project. Every URL below is
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
# Stop: Ctrl-C, or if it was backgrounded — kill by PORT, not by command pattern.
# `pkill -f "php -S ..."` only matches a server started with no options between
# `php` and `-S`; the $RPORT server below has `-d` flags there, so that pattern
# silently matches nothing and the process survives (found 2026-07-25, L-021).
# The guard matters: with nothing listening, `kill $(...)` gets no arguments and
# errors "not enough arguments", which reads like a failure and is not one.
kill $(lsof -nP -tiTCP:$KPORT -sTCP:LISTEN) 2>/dev/null || echo "nothing listening on $KPORT"

# The $RPORT section (below) leaves a private session store behind. It is throwaway
# state and accumulates across sessions — clear it when you are finished:
rm -rf /tmp/klytos-sessions

# Wipe all runtime state and seed again from scratch
XDEBUG_MODE=off php scripts/dev/seed-playground.php --reset
```

> **Run `--reset` after any walkthrough and BEFORE the automated suite.** Stopping the server is not
> enough: refusals provoked by the commands above (401s, 403s, 429s) persist on disk in
> `installer/data/rate_limits.json` and `installer/data/login_lockouts.json` by design, and the
> suite's ceiling tests assert exact counts against those buckets. Sequential is not the same as
> isolated — L-025 covers running the suite and a QA pass *concurrently*; **L-028** covers the residue
> one leaves for the other. Neither `lsof` nor a free port can see this.

`--reset` deletes the contents of `installer/config/` and `installer/data/` and the generated site,
**preserving the tracked `.htaccess` guards** in those directories — those are production access
controls, not clutter. Without `--reset` the seeder refuses to run when an installation already
exists, so it can never silently destroy a real local install.

> **`--reset` prints `user owner already exists, skipping` and that does NOT mean the reset failed.**
> The reset wipes the data tree first, and the line comes from the seeder re-creating users against a
> config it has just rewritten. The reliable confirmation is that the MCP application password in
> `installer/config/.playground-access` is a **new** one. Noted because the message reads like a
> no-op and is not.

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
3. Log out and repeat with `admin`, `editor` and `viewer` — **all four reach the dashboard**, each
   seeing only what its role allows. That is the fastest way to see the whole authorization system
   working end to end.

Without a browser — the same walk, and the one command that proves the sprint's headline claim.
Every other claim in this document ships a runnable command and this one did not, so a terminal-only
reader had to derive the form fields themselves (found by the Sprint 5 fresh-context pass):

**The login form verifies a CSRF token (Sprint 6 slice 4, D-061), so the POST alone is not enough:**
you have to GET the form first, keep the session cookie it sets, and send back the token that page
actually rendered. That is exactly what a browser does. The cookie jar (`-c` then `-b`) carries the
session; `sed` lifts the token out of the returned HTML.

```bash
for u in owner admin editor viewer; do
  jar=/tmp/klytos-$u.jar; rm -f "$jar"
  # 1. GET the form: this issues the session AND mints the token that belongs to it.
  csrf=$( curl -s -c "$jar" "http://127.0.0.1:$KPORT/installer/admin/login.php" \
    | sed -n 's/.*name="csrf" value="\([^"]*\)".*/\1/p' | head -1 )
  # 2. POST the credentials WITH that token, on that same session.
  code=$( curl -s -o /dev/null -w '%{http_code}' -b "$jar" -c "$jar" \
    -d "username=$u" -d "password=playground-$u-2026" -d "csrf=$csrf" \
    "http://127.0.0.1:$KPORT/installer/admin/login.php" )
  dash=$( curl -s -o /dev/null -w '%{http_code}' -b "$jar" \
    "http://127.0.0.1:$KPORT/installer/admin/" )
  echo "$u: login $code, dashboard $dash"
done
rm -f /tmp/klytos-*.jar
```

Expected — **all four identical**:

```
owner: login 302, dashboard 200
admin: login 302, dashboard 200
editor: login 302, dashboard 200
viewer: login 302, dashboard 200
```

> **If you get `login 403, dashboard 302` on all four, the token is the reason** — either the `sed`
> found nothing (check `echo "$csrf" | wc -c`; it should be 65, a 64-hex token plus the newline) or
> the GET and the POST did not share a session. The 403 body re-renders the form carrying *"Your
> session expired before the form was sent."* — that message is about the **token**, not about your
> password, and it is the only refusal on this page that is not a credential problem.
>
> **This block was wrong for one sprint and a fresh-context pass caught it**, which is worth stating
> because the same trap is waiting for the next reader: the command was added in Sprint 5 (a pass
> found that a terminal-only reader had to derive the form fields), and Sprint 6 slice 4 then added
> the CSRF check to the very form it drives — so the document's own headline command answered **403
> four times** and told a newcomer the role system was broken. Both were correct changes; neither
> session ran this block afterwards. Anything here that drives a form is only as current as the last
> time somebody executed it.
>
> Historical note, corrected in the same pass: an older version of this section said a pre-Sprint-5
> refusal answered `login 200`. A **credential** refusal does re-render the form with 200; a **CSRF**
> refusal answers **403**, a status this section did not previously mention at all.

### All four roles log in (since Sprint 5)

`Auth::login()` consults the **user record** through `UserManager::authenticate()` (**D-056**).
Until Sprint 5 it validated only against `config['admin_user']` / `config['admin_pass_hash']`, so
`admin`, `editor` and `viewer` were refused with *"Incorrect username or password"* no matter what
their records said — audit **NEW-11**, closed. Two consequences worth knowing while testing:

- **A password change now takes effect.** Rotating a password through the profile page, the admin
  reset link or `klytos_reset_user_password` changes what the login form accepts. Before Sprint 5 it
  did not (audit **NEW-37**), which is why an old note may tell you the seeded password always works.
- **Five failed attempts lock that ONE account for 15 minutes**, not the whole install. The state
  lives in `installer/data/login_lockouts.json`; deleting that file clears every lockout, which is
  the quickest way out if you lock yourself out of the playground.

Tests still write the session directly rather than logging in (`actingAs()`), because a gate test
should measure the gate rather than the login form:

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
nc -z 127.0.0.1 $RPORT && echo "PORT $RPORT IS TAKEN — see the note below before doing anything else"

# Start a server with a private, inspectable session store.
# Its output is REDIRECTED so the bind check below has something to read — without
# the redirect that check silently finds nothing and reassures you for free (L-016).
SP=/tmp/klytos-sessions; mkdir -p $SP
php -d session.save_path=$SP -d session.serialize_handler=php_serialize \
    -S 127.0.0.1:$RPORT -t . scripts/dev/router.php > /tmp/klytos-rport.log 2>&1 &
sleep 2

# BACKGROUNDED means a failed bind is silent. Check for it explicitly, every time.
grep -i 'failed to listen' /tmp/klytos-rport.log \
  && echo "DID NOT BIND — everything below would answer from someone else's server" \
  || echo "bound cleanly on $RPORT"

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

> **If the port is taken, find out WHOSE it is before switching ports — it is usually ours.**
> L-011 taught the tell `Server: Apache/2.4.54 (Debian)` for a foreign squatter. That does not help
> here: a `php -S` left running by an **earlier session of this project** is byte-identical on every
> signal — same `X-Powered-By`, same security headers, same session store, same router. Every
> identity check passes, honestly, and you end up testing a server you did not start, possibly
> against an older tree. This happened on 2026-07-25 (**L-021**) and the walk was correct only by
> luck. Two commands settle it:
>
> ```bash
> lsof -nP -iTCP:$RPORT -sTCP:LISTEN          # which PID owns the port
> ps -o pid,lstart,command -p <PID>           # when it started, and with which arguments
> ```
>
> If it is an old Klytos router, `kill <PID>` and start a fresh one — do not just move to another
> port, or the stale process is still there for the next session to trip over.

The cookie is named **`klytos_session`**, not `PHPSESSID` (`auth.php:61`):

```bash
curl -s -o /dev/null -w '%{http_code}\n' -b "klytos_session=<the id printed above>" \
  http://127.0.0.1:$RPORT/installer/admin/users.php     # 403 for viewer
```

**When you are done with this section, stop that server** — leaving it up is what breaks the upgrade
test if you ever set `RPORT=8099`, and it costs nothing to be tidy:

```bash
# Kill by PORT — `pkill -f "php -S 127.0.0.1:$RPORT"` does NOT match this server,
# because its command line carries `-d session.save_path=...` between `php` and `-S`.
kill $(lsof -nP -tiTCP:$RPORT -sTCP:LISTEN) 2>/dev/null || echo "nothing listening on $RPORT"
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

> **This probe WRITES.** A 401 is an authentication failure, so it adds an entry to the product's
> persistent, IP-keyed auth-failure bucket (`installer/data/rate_limits.json`) — that is the control
> Sprint 6 built, working as designed. The ceiling is 10 failures per 60 s and
> `LoginCeilingHttpTest` spends all ten, so **one leftover entry is enough to fail that test on
> perfectly good code**. Reseed with `--reset` before running the suite (L-028). The same applies to
> every 403 and 429 below: in this product, provoking a refusal is a write, not a read.

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
filtered by the same decision. Bearer tokens carry a role directly, so mint one per role:

> Since Sprint 5 (**D-056**) application passwords are **no longer pinned to the admin user**: one
> minted for `editor` authenticates and arrives at the gate as an editor, because the role follows
> the user record. Bearer tokens are still used below because they need no account at all, which
> keeps this walk independent of the user set.

> **`klytos_delete_page` really does delete a page.** The call below is safe on a *freshly seeded*
> playground only because the seed creates `home`, `about` and `contact` — there is no `index` page,
> so an allowed call finds nothing to delete. **On any install that does have one, running this as
> owner or admin TRASHES it** — the tool answers *"Page moved to trash. Use klytos_restore_page to
> undo or klytos_permanent_delete_page to remove permanently"*, and the record stays in
> `installer/data/pages/`. So it is recoverable, and the earlier wording ("removes it") overstated it
> — corrected by the Sprint 4 fresh-context pass, which ran the call and read the answer. The flip
> side is the part to remember when tidying up after a probe: **trashing is not cleanup**, and
> leaving the tree clean needs `klytos_permanent_delete_page`.
>
> Use a slug you have just created if you are unsure:
>
> ```bash
> ls installer/data/pages/ 2>/dev/null    # what actually exists before you aim a destructive tool at it
> ```
>
> Found by the Sprint 3 fresh-context pass, which noticed the example is *accidentally* safe and that
> the document never said so.

```bash
# Mint four bearer tokens, one per role. Prints role=token lines.
# NOTE: these persist in the install until revoked — see "Clean up" at the end of this section.
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

**The per-role expectation table.** Every cell below was measured on this playground (re-confirmed
cell-by-cell by the Sprint 3 fresh-context pass). To reproduce it, run this — the arguments differ per
tool, which "swap `$TOK_*` and repeat" did not tell you:

```bash
for tok in OWNER ADMIN EDITOR VIEWER; do
  eval "T=\$TOK_$tok"
  for call in \
    'klytos_get_page|{"slug":"home"}' \
    'klytos_delete_page|{"slug":"index"}' \
    'klytos_x402_get_config|{}' \
    'klytos_forms_list|{}' \
    'klytos_integrity_status|{}' ; do
      name="${call%%|*}"; args="${call#*|}"
      code=$(curl -s -o /dev/null -w '%{http_code}' -X POST http://127.0.0.1:$KPORT/installer/mcp \
        -H "Authorization: Bearer $T" -H 'Content-Type: application/json' \
        -d "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/call\",\"params\":{\"name\":\"$name\",\"arguments\":$args}}")
      printf '%-8s %-28s %s\n' "$tok" "$name" "$code"
  done
done
```

(`klytos_delete_page` above targets `index`, which the seed does not create — see the warning at the
top of this section before pointing it at a slug that exists.)

| Tool | Capability | owner | admin | editor | viewer |
|---|---|---|---|---|---|
| `klytos_get_page` (read) | `pages.view` | 200 | 200 | 200 | 200 |
| `klytos_delete_page` (destructive) | `pages.delete` | 200 | 200 | **403** | **403** |
| `klytos_x402_get_config` (filter-injected core) | `x402.view` | 200 | 200 | 200 | **403** |
| `klytos_forms_list` (shipped plugin) | `forms.manage` | 200 | 200 | **403** | **403** |
| `klytos_integrity_status` (core, admin-tier) | `site.configure` | 200 | 200 | **403** | **403** |
| `tools/list` size | — | 206 | 197 | 56 | 19 |

**Clean up when you are done — these tokens persist.** Every walk mints four more, and three of them
carry a privileged role:

```bash
# revokeBearerToken() takes the token's ID, not its label — so look the IDs up by
# label first. (Passing the label straight in silently revokes nothing and returns
# false, which is why this reads the store rather than guessing.)
XDEBUG_MODE=off php -r 'require "installer/core/app.php";
  $a=\Klytos\Core\App::getInstance(); $a->boot();
  $auth = $a->getAuth();
  $n = 0;
  foreach ( $a->getStorage()->read( "config", "tokens" )["tokens"] ?? [] as $t ) {
      if ( str_starts_with( $t["label"] ?? "", "walk-" ) && $auth->revokeBearerToken( $t["id"] ) ) {
          $n++;
      }
  }
  echo "revoked {$n} walk-* bearer token(s)\n";'

# CONFIRM it, do not trust the count. The message above is the script's own
# report; a revocation that silently wrote nothing would print the same thing.
# A revoked token must be refused:
curl -s -o /dev/null -w 'revoked token now answers %{http_code} (expect 401)\n' \
  -X POST -H "Authorization: Bearer $TOK_OWNER" -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' \
  "http://127.0.0.1:$RPORT/installer/mcp"

# A 2026-07-25 QA pass saw this snippet report success ONCE while all four tokens
# stayed live. It did not reproduce in two further attempts and the mechanism is
# unknown — which is exactly why the confirmation above is not optional.
```

A 200 with `"isError":true` in the body is the tool reporting a domain error — that is the tool
running, i.e. the gate **allowed** it. The gate's refusal is always a 403 with a JSON-RPC `error`
object and no `result`.

> **Do not take `isError` as a reliable "did it work" signal — it is not, and the example this
> paragraph used to give was the counter-example.** It said "a missing slug, say"; the walk above
> deletes the non-existent `index` page, and that answers **`"isError":false`** with
> `"success": false` in the payload. Other tools do set it: `klytos_get_page` on a missing slug
> answers `"isError":true`. Two separate defects sit underneath, both recorded and neither fixed:
> **NEW-43** (`klytos_delete_page` returns `success:false` beside the sentence *"Page moved to
> trash…"*, so one field says nothing happened and the other says something did) and **NEW-55**
> (`klytos_get_page` reports a page that does not exist as *"An internal error occurred"*). **Read
> the payload, not the flag.**

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
`429`s once the window is spent. **The loop below will show `429` on every one of its four
requests, not a mix**: the policy is 2 per 60 seconds and the two submissions above have already
spent the window. To see a `201` from the loop, wait 60 seconds first. The flood ceiling is 10 per minute per address and the
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
XDEBUG_MODE=off php installer/cli.php help        # 27 commands
XDEBUG_MODE=off php installer/cli.php status
XDEBUG_MODE=off php installer/cli.php pages
XDEBUG_MODE=off php installer/cli.php logs
```

#### `owner:repair` — the one command you cannot try safely here

The 27th command exists for an install that has **lost its owner record**, a state D-031 makes
survivable and this command makes recoverable (audit NEW-08, D-055). Full reference:
`docs/reference/owner-recovery.md`.

```bash
XDEBUG_MODE=off php installer/cli.php owner:repair --email=you@example.com
```

On this playground it will **refuse**, and that is the correct outcome — a seeded install already has
an owner:

```
Error: This install already has an owner (owner). Nothing was changed.
```

Exit code **1**. Every refusal path exits non-zero, so a recovery script cannot mistake "nothing was
done" for success. Reproducing the *success* path means deleting the owner record first, which is
what `tests/Integration/OwnerRepairTest.php` does under the playground snapshot — do not do it by
hand here.

Note it is currently the **only** command whose `help` description is English; every other one is
hardcoded Spanish (**NEW-33**, below). New strings must be catalogue keys under D-006, so the
inconsistency is temporary and points the right way.

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

### The database backend's tier — optional, and it SKIPS without a database

Klytos ships two storage backends and, until 2026-08-30, only the flat-file one had ever executed a
test. That is exactly how D-115's `SELECT` defect survived: on the database backend the
per-record-encrypted `config` rows (`tokens`, `app_passwords`, `oauth_clients`, and more at higher
levels) were **silently dropped from every listing**, and no file-tier test could ever have seen it,
because the file tier does not have that code path.

`tests/Unit/DatabaseStorageTest.php` closes that gap. It needs a throwaway database:

```bash
docker run -d --name klytos-test-mysql \
  -e MARIADB_ROOT_PASSWORD=klytos-test -e MARIADB_DATABASE=klytos_test \
  -p 13306:3306 mariadb:11.4
```

**With no database reachable the tier skips**, with a message that repeats that command — so a
machine without a container runtime still gets a green suite, and `scripts/keel-doctor` carries a
`Test database (MySQL/MariaDB)` row so the gap is visible rather than silent. **A skipped backend is
not a tested one.**

MariaDB and not MySQL 8.4 on purpose: 8.4 removed `mysql_native_password`, and this machine's
`mysqlnd` cannot speak `caching_sha2_password` — measured, `SQLSTATE[HY000] [2054]`. MariaDB is a
first-class target for this product either way. Override the defaults with `KLYTOS_TEST_DB_HOST`,
`_PORT`, `_NAME`, `_USER` and `_PASS`.

Stop it when you are done — it holds no state anything else needs:

```bash
docker rm -f klytos-test-mysql
```


> **STOP — two things first, if you have been following this document from the top.**
> Both were found by a fresh-context QA pass at the sprint-1 close, and both make the suite report
> failures that are **artefacts of the walkthrough, not defects in the product**. A newcomer hitting
> them would reasonably conclude Klytos is broken, which is L-008's failure mode reproduced by the
> document that cites it.
>
> ```bash
> # 1. Stop the second server section 1 told you to start and never told you to stop.
> #    Leaving one on port 8099 makes AdminGateHttpTest (12 tests) error out: the harness
> #    refuses to test a server it did not start (correctly — see AdminHttpTestCase).
> #
> #    Kill by PORT. This previously read `pkill -f "127.0.0.1:8099"`, which could never
> #    match the server section 1 tells you to start — that section forbids RPORT=8099 —
> #    AND misses any `php -S` carrying `-d` flags before `-S` (L-021). Found by the
> #    Sprint 3 fresh-context pass, which ran it and watched it match nothing.
> kill $(lsof -nP -tiTCP:$RPORT -sTCP:LISTEN) 2>/dev/null || echo "nothing listening on $RPORT"
> kill $(lsof -nP -tiTCP:8099 -sTCP:LISTEN) 2>/dev/null || echo "nothing on 8099 — the usual case"
>
> # 2. Re-seed. Walking section 4 by hand STORES a real comment, and
> #    PublicCommentTest::testRateLimitHoldsAcrossSessions counts stored comments.
> XDEBUG_MODE=off php scripts/dev/seed-playground.php --reset
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
XDEBUG_MODE=off vendor/bin/phpunit --testsuite unit
XDEBUG_MODE=off vendor/bin/phpunit --testsuite integration

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

`XDEBUG_MODE=off` is the same noise suppression the seeder needs, for the same reason — see the
page-create note below. It also keeps the suite fast; the run is ~17s without it loaded.

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

**There is a third tier, and a clean run still prints it.** A `WARN` reports a property that is
genuinely broken but whose fix another phase owns — it prints full evidence and does **not** change
the exit code (D-045: a check that reddens the build for something you are not allowed to fix is a
check people learn to ignore). The current expected output is:

```
OK — 17 check(s) run: 13 passed, 4 warning(s) carrying 22 note(s) (owned by another phase).
```

**This line was stale until 2026-07-29** — it still read `10 check(s) run: 8 passed, 2 warning(s)`,
the figure from before the Keel v5.0.0 reconciliation added five checks (D-067) and DR-003 added a
sixth (D-074). A fresh-context QA pass follows this document and reads that block as the expected
state, so a stale expected output trains the reader to accept a wrong count — the L-016 defect
living in the instructions instead of the instrument. Whoever changes the check set updates this
line in the same slice.

All four WARNs are owned elsewhere, and seeing them is the expected state, not a regression:
**H-01** — the version string disagrees across five touchpoints (Phase 7) · **NEW-27** — the blanket
`*.md export-ignore` strips all 16 in-product guides from release archives (Phase 7) · the
**README.md link backlog** of 10 known-broken links (D-017's editorial pass) · the **conformance
sweep** still carrying 6 `missing` rows (Phases 6/7/8, which have not run).

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

`installer/vendor-ai/` ships **17** pre-installed packages (the AI chat stack). Since Sprint 1 slice 2
it has a reconstructed manifest at `installer/composer.json`, pinned to exactly what is vendored
(D-028), so it can be audited:

```bash
composer audit -d installer
```

**As of 2026-07-25 the expected output is `No security vulnerability advisories found.` with exit
code 0.** Sprint 3 slice 1 re-vendored the tree to guzzle **7.15.1**, psr7 **2.13.0** and promises
**2.5.1** (adding `symfony/polyfill-php80`), closing audit **NEW-05** — see **D-052**. Before that it
reported 11 advisories across guzzle 7.10.0 and psr7 2.9.0.

**Zero is the expected result, and a non-zero result is a finding, not a known state.** Exit code 1
means advisories were published against the pinned versions since; the table it prints is the full
report. Triage it with the maintainer rather than bumping silently — that is D-022's standing rule,
and it is what produced D-029 and D-052.

> **Distinguish "clean" from "did not run", because they look alike.** `composer audit` needs network
> access to Packagist. If it cannot reach it you may see an error, or an empty-looking result, and
> neither means the tree is clean. Check the **exit code**, not the text:
>
> ```bash
> composer audit -d installer; echo "exit=$?"    # 0 = genuinely clean; 1 = advisories; anything else = it did not run
> ```
>
> CI makes the same three-way distinction in its `vendor-advisories` job, deliberately
> (`.github/workflows/ci.yml`) — reporting an infrastructure failure as a security finding is the same
> dishonesty as the reverse.

The manifest also regenerates the tree reproducibly, but **do not run that in a checkout you care
about** — it rewrites every tracked file under `installer/vendor-ai/` — **509** of them at the time of
writing (`git ls-files installer/vendor-ai | wc -l`, which is how to check it rather than trusting
this number; it was 482 before the Sprint 3 re-vendor added a seventeenth package):

```bash
# Lock only, touches nothing vendored (this is what slice 2 ran):
composer update --no-install -d installer

# Full re-vendor — only as a deliberate, reviewed change:
# composer install -d installer
```

`tests/Unit/VendorAiManifestTest.php` fails the suite if the manifest, the lock,
`vendor-ai/composer/installed.php` and `installer/vendor-ai/LICENSE-THIRD-PARTY.md` ever stop agreeing.

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
plugin.

**Where the snippet goes, because this section used to give the code and not the location** (found
by the Sprint 6 fresh-context pass — a terminal-only reader was left between a code block and a
verification they could not reach). A Klytos plugin is a directory under `installer/plugins/` whose
name, entry file and PHP header all match — that identity is the plugin contract and it is not
negotiable. Three commands create one, and the server picks it up on the next request:

```bash
mkdir -p installer/plugins/qa-log-probe

cat > installer/plugins/qa-log-probe/qa-log-probe.php <<'PHP'
<?php
/**
 * Plugin Name: QA log probe
 * Description: Temporary playground probe — subscribes the refusal actions so they reach the log.
 * Version: 1.0.0
 */
PHP

# Activate it the way the SEEDER does — write the plugin state directly. That is
# deliberate and it is the seeder's own recorded reason: PluginLoader::activate()
# has side effects (it is the admin path), while loadPlugin() at boot only cares
# that the state says active. Note this REPLACES plugins.json.enc, so the two
# shipped MCP plugins are carried through explicitly — drop them and the MCP tool
# count in §3 falls from 206 to 180.
XDEBUG_MODE=off php -r '
define("KLYTOS_INSTALLER_PATH", __DIR__ . "/installer");
require "installer/core/app.php";
$app = \Klytos\Core\App::getInstance(); $app->boot();
$now = \Klytos\Core\Helpers::now();
$app->getStorage()->write( "plugins.json.enc", [
    "active"       => [ "klytos-forms" => true, "klytos-importer" => true, "qa-log-probe" => true ],
    "activated_at" => [ "klytos-forms" => $now, "klytos-importer" => $now, "qa-log-probe" => $now ],
    "logs_enabled" => [],
] );
echo "activated\n";'
```

Then put the listener in that file (append it below the header):

```php
klytos_add_action( 'mcp.access_denied', function ( string $tool, ?string $role, string $reason ): void {
    // The last argument MUST be 'core'. Logger::write() treats any other source as a
    // PLUGIN ID and drops the entry unless that plugin has logging enabled
    // (logger.php, condition 2) — so this snippet previously passed 'security' and
    // wrote absolutely nothing, while the paragraph above explained that an empty log
    // was expected. A remedy that produces silence, next to a note saying silence is
    // normal, is unfalsifiable: found by a fresh-context pass that ran it.
    klytos_log_warning( "MCP refused {$tool} for role " . ( $role ?? 'none' ) . ": {$reason}", [], 'core' );
}, 10 );
```

Now provoke a refusal and confirm it actually wrote something, rather than trusting the absence of an
error. `createBearerToken()` returns an **array** — the token is under `["token"]`, and forgetting
that mints nothing and answers **401**, which looks like a broken gate and is a broken command:

```bash
VTOK=$(XDEBUG_MODE=off php -r 'require "installer/core/app.php";
  $a=\Klytos\Core\App::getInstance(); $a->boot();
  echo $a->getAuth()->createBearerToken( "qa-probe-viewer", "viewer" )["token"];')

# A viewer asking for a destructive tool: must be 403, and must now be logged.
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://127.0.0.1:$KPORT/installer/mcp \
  -H 'Content-Type: application/json' -H "Authorization: Bearer $VTOK" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"klytos_delete_page","arguments":{"slug":"home"}}}'

XDEBUG_MODE=off php installer/cli.php logs | head -5   # must show the entry, not "No hay archivos de log."
```

Verified 2026-07-27, verbatim — `403`, then:

```
Log: debug-2026-07-27.log (ultimas 50 lineas):

[2026-07-27 00:58:56] [WARNING] [core] MCP refused klytos_delete_page for role viewer: role 'viewer' lacks the required capability 'pages.delete'
```

**Clean up when you are done** — the probe plugin and the token both persist:

```bash
rm -rf installer/plugins/qa-log-probe
XDEBUG_MODE=off php scripts/dev/seed-playground.php --reset   # drops the token and the plugin state
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

## Creating a page prints no warning — and if it ever does, that is a regression

Until Sprint 4 (2026-07-25), creating a page printed:

```
Warning: Klytos\Core\App::{closure}(): Argument #1 ($data) must be passed by reference,
value given in installer/core/hooks.php on line 145
```

That was **audit finding NEW-03**, closed by **D-054**: `Hooks::doAction()` passes arguments by
value, so the by-reference listener could never bind and its write was discarded silently. The
listener is now a filter (`page.save_data`), and a by-reference listener is refused at registration.

**A page create is expected to be silent.** The suite pins this from both sides — `phpunit.xml` sets
`failOnWarning="true"`, so ANY PHP warning anywhere reddens the suite, and
`tests/Integration/HookMutationTest.php` asserts specifically that creating a page emits no
diagnostic. If you see a warning here, it is a new defect, not known noise.

**Run it yourself** — this section stated a regression criterion for two sprints without shipping a
command to trigger it, which is the one thing the rest of this document does not do (found by the
Sprint 6 fresh-context pass). Over MCP, using the app password from §3:

```bash
APPPW=$(grep -A1 "application password" installer/config/.playground-access | tail -1 | tr -d ' ')

curl -s -u "owner:$APPPW" -X POST http://127.0.0.1:$KPORT/installer/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"klytos_create_page",
       "arguments":{"slug":"qa-warning-probe","title":"QA warning probe",
       "content":"<!-- wp:paragraph --><p>probe</p><!-- /wp:paragraph -->"}}}'

# Then read the SERVER's output, which is where a PHP warning would land — not this
# terminal. If you started the server backgrounded per the Start section:
grep -i 'warning\|must be passed by reference' /tmp/klytos-kport.log || echo "no warning — correct"

# Clean up (the page is real; leaving it changes what later sections count):
curl -s -u "owner:$APPPW" -X POST http://127.0.0.1:$KPORT/installer/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"klytos_permanent_delete_page",
       "arguments":{"slug":"qa-warning-probe"}}}' > /dev/null
ls installer/data/pages/     # back to: about.json.enc contact.json.enc home.json.enc
```

> **Where the warning would appear is the part that is easy to get wrong.** A PHP warning raised
> while serving a request goes to the server process's own output, so with a foreground `php -S` it
> prints in *that* terminal and with a backgrounded one it lands in the log file the Start section
> redirects to. It never comes back in the `curl` response body, so a clean-looking JSON answer
> proves nothing on its own.

`XDEBUG_MODE=off` is still worth using on every command in this document — with Xdebug loaded, any
diagnostic that does appear prints tens of kilobytes of stack trace, and a seeder that succeeded
looks like a crash.
