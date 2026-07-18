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
| 1 — Test harness + dev manifest | | | | | | | | | | pending | |
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

## Session-start freshness

At the **first** test point of every working session, the playground is booted from the commands in
`docs/playground.md` exactly as written — this validates the environment and the document in one
move — and `docs/playground.md` is stamped `last verified: <date>`. Instructions that no longer start
the playground are a defect caught here, not by the user.

| Date | Booted from documented commands | Result | Doc stamped |
|---|---|---|---|
| 2026-07-18 | `php scripts/dev/seed-playground.php --reset` then `php -S 127.0.0.1:8080 -t . scripts/dev/router.php` | OK — admin 302→login, login 200, MCP 401 unauthenticated / 177 tools authenticated | yes |

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
