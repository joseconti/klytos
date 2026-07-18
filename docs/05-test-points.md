# Test Points — Klytos CMS

> One row per slice, filled at the moment its test point passes — never retrospectively.
> An empty cell is a missing check, not a formatting gap. The evidence cell carries the **exact
> commands run, a one-line result summary, and the commit hash**; a result without them is empty.
> An acceptance criterion without an automated test carries its one-line justification in the row.
> "n/a" is a valid value where a column genuinely does not apply — but it is written, never left blank.

## Sprint 1 — authorization axis

| Slice | Acceptance criteria checked | Real run in playground | Security checks | Accessibility (automated + AT pass) | i18n (strings externalized) | Reuse checked (no new duplicate) | Docs updated (api/reference, example runs) | Extension points exposed | Evidence (commands + output summary + commit) | Result | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 0 — Playground + gate zero | Playground boots from documented commands; 4 roles seeded; MCP reachable; `.htaccess` denies replicated under `php -S` | **yes** — seeded, booted, admin 302→login, login 200, 4 users + 3 pages created via the product's own managers | Deny checks: `config/.encryption_key`, `config.json.enc`, `admin-identity.priv.enc`, `.playground-access`, `data/**`, `core/*.php`, `backups/`, `/.git/config`, `plugins/**/*.php` → **all 403**. MCP unauthenticated → **401**. `git check-ignore` confirms every generated secret is ignored; `git status -uall` over config/data/public → **empty** | n/a — no UI written in this slice | n/a — no user-facing strings added | **yes** — seeder drives `UserManager::create()`, `PageManager::create()`, `Auth::createAppPassword()`, `Encryption::generateRsaKeyPair()` rather than hand-writing storage; no new duplicate helper | `docs/playground.md` created; no new public PHP surface introduced (both scripts are dev entry points, not API) | n/a — dev tooling, no extension points | See the evidence block below | **PASS with one gate-zero item open** | Found 2 production bugs: NEW-03 (by-ref hooks) and NEW-04 (build writes into the repo root). Neither is fixed here; both recorded with triggers |
| 1 — Test harness + dev manifest | `composer install` clean; both tiers run; unit tier passes on a bare checkout; integration tier boots the App and resolves each seeded role from `$_SESSION`; `phpunit` green; `phpcs` green | **yes** — the integration tier IS a playground run: it boots the real App against the seed and asserts all 4 roles; playground re-seeded and re-booted from the documented commands this session (freshness row below) | Harness only — no product security boundary changed. Two security-relevant properties of the harness itself were verified: the integration tier **skips loudly** instead of passing when the playground is absent (proven by moving the two install files aside), and `actingAs()` sessions are accepted by the product's own `Auth::isAuthenticated()` while a guest session is rejected — so later refusal tests cannot pass against an anonymous session | n/a — no UI written in this slice | n/a — no user-facing strings added | **yes** — class loading uses Composer's `classmap` over `installer/core/` rather than re-implementing `App::registerAutoloader()`'s CamelCase→kebab-case mapping (private, bound to a booted instance), so no second copy of that rule exists. Temp-dir teardown is NOT shared with `seed-playground.php`'s purge: that one deliberately preserves tracked `.htaccess` guards, this one deletes everything — different contracts, not a duplicate | No new product API surface (test harness + dev manifest). Teaching surfaces updated in the same slice per L-004: `docs/03-technical-plan.md` §1 and §4, `docs/playground.md` ("Running the tests"), the `klytos-core-development` skill (new Testing section), and the `docs-discipline` rule in all 7 mirrored containers | n/a — dev tooling, no extension points | See the evidence block below | **PASS** | Composer aliases added (`composer test` / `test:unit` / `test:integration` / `lint`). `composer.lock` untracked→tracked (D-027). `phpcs.xml` now covers `tests/`; `PSR1.Files.SideEffects` excluded for `tests/bootstrap.php` only, with justification. `failOnWarning` deliberately left off until NEW-03 is fixed (D-026) — reason recorded in `phpunit.xml` |
| 2 — `vendor-ai/` manifest + CVE audit | | | | | | | | | | pending | |
| 3 — One matrix + fail-closed current user | | | | | | | | | | pending | |
| 4 — `klytos_require_permission()` + central gate | | | | | | | | | | pending | |
| 5 — Named escalations, one test each | | | | | | | | | | pending | |
| 6 — `SafeHttp` + risky call sites | | | | | | | | | | pending | |
| 7 — Public comments, off the admin path | | | | | | | | | | pending | |
| 8 — HSTS + CSP fail-open + hardening | | | | | | | | | | pending | |
| 9 — `keel-verify` + regenerable INDEX | | | | | | | | | | pending | |

### Slice 0 — evidence (commands and output, 2026-07-18)

Commit: *pending — recorded at commit time.*

```
$ php scripts/dev/seed-playground.php --reset
Resetting playground state… / config written / user admin|editor|viewer created
(owner created by UserManager::migrateFromV1Config at boot) / MCP application
password created / page home|about|contact created / Playground ready.

$ php -r '<boot + list users/pages/config>'
  viewer role=viewer status=active     owner  role=owner  status=active
  admin  role=admin  status=active     editor role=editor status=active
  developer_mode: true        paginas: 3

$ php -S 127.0.0.1:8080 -t . scripts/dev/router.php     (backgrounded)
  admin  302 -> /installer/admin/login.php?redirect_to=%2Finstaller%2Fadmin%2F
  login  200
  mcp (no auth)  401
  config/.encryption_key  403
  tools/list (Basic owner:<app password>)  ->  177 tools

$ phpcs --standard=phpcs.xml scripts/dev/
  (no output — 0 errors, 0 warnings)

$ php installer/cli.php status|pages|logs
  Klytos v0.31.1-beta.1 / Paginas: 3 / 3 pages listed / "No hay archivos de log."
  (note: CLI output is unaccented Spanish — audit I-04 confirmed live)
```

**Gate zero — 2 of 3 met.** `03-technical-plan.md:108` defines it as *phpcs clean + the app boots +
one MCP `tools/list` round trip*. The app boots and MCP answers with 177 tools. **`phpcs` is not
clean and never was:**

```
$ phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
A TOTAL OF 204 ERRORS AND 488 WARNINGS WERE FOUND IN 114 FILES
PHPCBF CAN FIX 203 OF THESE SNIFF VIOLATIONS AUTOMATICALLY
```

The 204 errors are a **pre-existing baseline**, not introduced by this slice — both files added here
lint clean. **Resolved by D-025:** gate zero's lint condition is baseline-locked — zero violations in
the files a slice touches, and the 204/488 baseline may not grow. Re-measured at every sprint close.
With that amendment, **gate zero is met** and slice 0 closes.

### Slice 1 — evidence (commands and output, 2026-07-19)

Commit: *pending — recorded at commit time.*

```
$ composer install --no-interaction
  28 packages installed — phpunit/phpunit 11.5.56, squizlabs/php_codesniffer 3.13.5
  Generating autoload files          (no errors, no warnings)

$ XDEBUG_MODE=off vendor/bin/phpunit
  PHPUnit 11.5.56 / PHP 8.3.12 / Configuration: phpunit.xml
  .........                                                    9 / 9 (100%)
  OK (9 tests, 37 assertions)        [4 unit + 5 integration]

$ vendor/bin/phpcs --standard=phpcs.xml tests/
  ..... 5 / 5 (100%)                 (0 errors, 0 warnings)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
  A TOTAL OF 204 ERRORS AND 488 WARNINGS WERE FOUND IN 114 FILES
  → D-025 baseline UNCHANGED (was 204/488 in 114 files on 2026-07-18)
```

**The tier boundary was verified, not assumed.** The two files `App::isInstalled()` checks for were
temporarily moved aside and the suite re-run, to prove the integration tier cannot pass without its
fixture:

```
$ mv installer/config/.encryption_key{,.bak}; mv installer/config/config.json.enc{,.bak}
$ XDEBUG_MODE=off vendor/bin/phpunit
  ....SSSSS                                                    9 / 9 (100%)
  OK, but some tests were skipped!
  Tests: 9, Assertions: 9, Skipped: 5.
  → the 4 unit tests pass on a bare checkout; all 5 integration tests SKIP with the reseed command.
$ (files restored)  XDEBUG_MODE=off vendor/bin/phpunit
  OK (9 tests, 37 assertions)
```

This matters more than the green run: a harness whose authorization tier passes silently when it
never executed would make every refusal test in slices 3–5 worthless. It refuses.

**Independent review (`code-reviewer` subagent, before the commit).** Verdict: no blocking findings;
the session seam was verified line-by-line against `auth.php:129-136` and `:183-188` and found to
match exactly. Six findings, every one resolved in this slice:

| # | Finding | Resolution |
|---|---|---|
| 1 | `composer lint` runs the unscoped ruleset, which includes `installer/plugins` — a path D-025 never measured. A contributor could not tell pre-existing noise from a regression they introduced | **Measured and recorded**: plugins **131/109 in 25 files**, whole ruleset **335/597 in 139 files** (`03-technical-plan.md` §4). All three paths now locked. D-025's decision is unchanged — its measurement simply covered 2 of the 3 ruleset paths |
| 2 | `requirePlayground()` re-implemented `App::isInstalled()` by hand — a near-duplicate, the exact defect L-004 records | **Fixed**: calls `App::getInstance()->isInstalled()`. Verified safe pre-`boot()` — it reads only `$configPath`, set in the constructor (`app.php:680-684`) |
| 3 | No storage isolation between integration tests. Harmless now (tests are read-only) but slices 3–5 will write state through this seam | **Not fixed — deliberately deferred with a trigger** (before slice 3). Building a rollback primitive nothing yet uses would be speculative; the trap is recorded in `PROGRESS.md` and in the sprint's risks so slice 3 cannot walk into it |
| 4 | Dead `<exclude>` in `phpunit.xml` — `vendor-ai/` is a *sibling* of `core/`, never in the included set | **Fixed**: removed, with a comment recording the layout so it is not re-added |
| 5 | `phpcs.xml` missing from `export-ignore` while the rest of the toolchain was added here | **Fixed**: added |
| 6 | `phpcs.xml` comment said "warn at 120" while `lineLimit` is 150 | **Fixed**: comment corrected (the value was right) |

One lint violation WAS introduced and fixed rather than tolerated: `tests/bootstrap.php` tripped
`PSR1.Files.SideEffects.FoundWithSymbols` (a bootstrap loads the autoloader *and* defines constants,
by nature). Fixed with a path-scoped exclusion in `phpcs.xml` carrying its justification — the same
pattern the ruleset already uses for `chat-engine.php` and the helper files — not by loosening the
rule globally.

## Session-start freshness

At the **first** test point of every working session, the playground is booted from the commands in
`docs/playground.md` exactly as written — this validates the environment and the document in one
move — and `docs/playground.md` is stamped `last verified: <date>`. Instructions that no longer start
the playground are a defect caught here, not by the user.

| Date | Booted from documented commands | Result | Doc stamped |
|---|---|---|---|
| 2026-07-18 | `php scripts/dev/seed-playground.php --reset` then `php -S 127.0.0.1:8080 -t . scripts/dev/router.php` | OK — admin 302→login, login 200, MCP 401 unauthenticated / 177 tools authenticated | yes |
| 2026-07-19 | same two commands, verbatim from `docs/playground.md` | OK — admin 302, login 200, MCP 401 unauthenticated, `config/.encryption_key` 403, 177 tools authenticated. Identical to the slice-0 baseline; the NEW-03 warning appeared on page creation exactly as the document says it would | yes |

## Cross-cutting verification (Phase 5 §4 — before Phase 6)

Filled once, at the end of development — not per sprint.

| Check | Command | Result | Date |
|---|---|---|---|
| Full acceptance-criteria pass | | | |
| All flows incl. failure paths, in the playground | | | |
| Full security profile checklist (web-app + MCP) | | | |
| Accessibility end to end (automated + keyboard + real AT) | | | |
| i18n — no hardcoded/concatenated user-facing strings; catalogues current | | | |
| Performance budgets measured (`03-technical-plan.md`) | | | |
| Upgrade path from the real previous version | | | |
| `scripts/keel-verify` (full output pasted) | | | |
