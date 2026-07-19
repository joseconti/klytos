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
| 2 — `vendor-ai/` manifest + CVE audit | Manifest resolves to **exactly** the 16 vendored versions (0 deltas); `composer audit` runs and its full output is pasted below; CVE findings reported and triaged, **not** patched (D-022) | **yes** — session-start freshness boot from `docs/playground.md` verbatim: admin 302, login 200, MCP 401 unauthenticated, `config/.encryption_key` 403, 177 tools authenticated. The slice itself changes no runtime code, so there is no new flow to walk | **This slice IS the security check.** First `composer audit` the project has ever been able to run: **5 advisories / 2 packages** (guzzle 7.10.0, psr7 2.9.0), all medium — recorded as NEW-05. Reachability assessed rather than assumed: no `CookieJar` anywhere, no user-controllable URLs (5 hardcoded provider endpoints, `chat-engine.php:242-247`), `vendor-ai/` loaded lazily from one call site. Also found NEW-06 (PHP 8.3 floor vs declared 8.1+) and NEW-07 (2 BSD packages with no licence text). **Verified `vendor-ai/` was not mutated** by the resolution: `git status --porcelain installer/vendor-ai` empty | n/a — no UI written in this slice | n/a — no user-facing strings added | **yes** — no new PHP surface. The drift guard extends `PHPUnit\Framework\TestCase` directly rather than `UnitTestCase`, whose per-test encryption key and throwaway storage would be set-up cost with no consumer; version normalisation is one private helper used by all four readers, not repeated per call site | `docs/03-technical-plan.md` §1 (both the dependency rows and the risk block), `docs/04-adoption-audit.md` (H-04 closed + NEW-05/06/07), `installer/vendor-ai/LICENSE-THIRD-PARTY.md` (all 16 packages, attribution corrected, BSD text added), `docs/playground.md` (new "Auditing the vendored dependencies" section — commands run as written). No new public API surface, so no `docs/api/` row | n/a — dev tooling and packaging metadata, no extension points | See the evidence block below | **PASS — with 5 CVEs open for user triage** | The sprint's "widest unknown" is now bounded: the upgrade is constraint-compatible (`soukicz/llm` allows guzzle `^7.9`, fixes are 7.12.1 / 2.12.1), so it is a re-vendor, not a dependency rewrite. Still a scope change → Estimate v2, per D-022 |
| 3 — One matrix + fail-closed current user | Viewer denied an owner-only permission; unknown permission denied for every non-owner role; owner shortcut intact (unknown keys included); **a session with no `klytos_user_id` is DENIED, not promoted**; v1.x migration idempotent; **upgrade tested from the REAL previous version (v0.30.1)** | **yes** — session-start freshness boot from `docs/playground.md` verbatim (admin 302, login 200, `config/.encryption_key` 403, MCP 401). Beyond the playground, a **real v0.30.1 install** was built by that release's own installer in a temp dir and upgraded to the working tree: 12/12 assertions pass, including the NEW-01 denial on a genuinely upgraded install | **This slice IS the security fix.** NEW-01 closed: the hardcoded `['role' => 'owner']` fallback is gone; both failure shapes (no `klytos_user_id`; id that does not resolve) deny and log. S-04 closed: one matrix, in `UserManager`. Independent `security-auditor` pass over the diff: **no blocking findings** — it traced every caller of `klytos_current_user()`, `Auth::getUserId()` and direct `$_SESSION['klytos_user_id']` reads and found no residual path to privilege, confirmed display-only callers fail closed on null, and confirmed the new log calls carry no secret, hash or personal data. It also confirmed the temp-dir scripts cannot delete outside their `mktemp` directory and embed only throwaway credentials | n/a — no UI written in this slice | n/a — no user-facing strings added. The two new log messages are operator-facing diagnostics, not UI copy | **yes** — the matrix was not re-implemented anywhere: `klytos_has_permission()` now delegates to the existing `UserManager::hasPermission()`. No new helper was created; the fix is a deletion plus a delegation. Test-side, `PlaygroundState` is a new trait rather than a copy of `UnitTestCase`'s temp-dir teardown, whose contract is different (that one preserves nothing; this one must preserve tracked `.htaccess` guards) | `docs/04-adoption-audit.md` (S-04 and NEW-01 marked CLOSED with the evidence; NEW-02 sharpened; NEW-08 added), `docs/playground.md` (new "Testing an upgrade from the real previous release" section, plus the isolation contract under "Running the tests"), `docs/decisions.md` (D-030, D-031), `docs/lessons-learned.md` (L-006). No new public PHP surface introduced — the change removes a surface and redirects a caller — so no `docs/api/` row | n/a for this slice — the `auth.capabilities` filter already existed and still applies, now at the single remaining call site (`user-manager.php:628`) rather than at two | See the evidence block below | **PASS** | S-04 resolved the **opposite way** to the audit's remediation note, deliberately and recorded: the "dead" copy was kept and the live one deleted, because `UserManager` is the lower layer and slice 4 / Sprint 2 both hold a user object rather than a session. One defect caught before commit and recorded as **L-006**: the first version of the crash fix logged through `$this->logger`, which is null at that point in boot and would have raised a `TypeError` — a crash introduced by a crash fix, on a path no test can reach. **The `code-reviewer` pass returned a BLOCKING finding and it was fixed, not argued with:** the Step 10b `try`/`catch` was never executed by any test, because every install the tests boot already has an owner, so `migrateFromV1Config()` was never reached *through* `App::boot()`. The upgrade script gained two phases (`break-migration`, `boot-must-survive`) that put an install into the exact state that used to fatal and boot it in a fresh process. Two further reviewer findings applied: `UserManager::hasPermission()` now denies a record with no usable role instead of defaulting it to `viewer` (with its own test), and a comment citing the wrong boot step was corrected |
| 4 — `klytos_require_permission()` + central gate | | | | | | | | | | pending | |
| 5 — Named escalations, one test each | | | | | | | | | | pending | |
| 6 — `SafeHttp` + risky call sites | | | | | | | | | | pending | |
| 7 — Public comments, off the admin path | | | | | | | | | | pending | |
| 8 — HSTS + CSP fail-open + hardening | | | | | | | | | | pending | |
| 9 — `keel-verify` + regenerable INDEX | | | | | | | | | | pending | |

### Slice 0 — evidence (commands and output, 2026-07-18)

Commit: `622d54c` — *Sprint 1 slice 0: verification playground, plus the Keel docs baseline*.

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

Commit: `585ec68` — *Sprint 1 slice 1: two-tier test harness (T-01) + dev-only composer manifest (D-022)*.

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

### Slice 2 — evidence (commands and output, 2026-07-19)

Commit: `d406e1f` — *Sprint 1 slice 2: vendor-ai manifest + first CVE audit (H-04, D-028)*.

**The tree, established from Composer's own record — not from counting directories.**
`installer/vendor-ai/composer/installed.json` and `installed.php` were already present, so the
manifest was *derived*, never guessed. The dependency graph has exactly **one** genuine root
requirement; the other 15 packages are transitive:

```
$ php -r '<read installed.json; invert the require graph>'
  packages: 16
  soukicz/llm            0.5.0      php:>=8.3     *** ROOT CANDIDATE ***
  guzzlehttp/guzzle      7.10.0     php:^7.2.5||^8.0   <- soukicz/llm
  guzzlehttp/psr7        2.9.0                          <- guzzlehttp/guzzle, soukicz/llm
  brick/math             0.14.8     php:^8.2            <- ramsey/uuid
  ramsey/collection      2.1.1      php:^8.1            <- ramsey/uuid
  … 11 more, every one reachable from soukicz/llm
```

Two counts in the original H-04 finding were wrong and are corrected on the record: **16 packages,
not 9**, and 482 is the **tracked** count (`git ls-files | wc -l`) while 484 exist on disk — the two
extras are gitignored package-internal files (`guzzle/package-lock.json`,
`swaggest/json-schema/composer.lock`).

**The manifest resolves, and resolves to exactly what ships.**

```
$ composer validate --no-check-publish -d installer
  (warnings only: "exact version constraints should be avoided" — intentional, see D-028)

$ composer update --no-install --no-interaction -d installer
  Lock file operations: 16 installs, 0 updates, 0 removals
  - Locking brick/math (0.14.8)          - Locking ramsey/collection (2.1.1)
  - Locking guzzlehttp/guzzle (7.10.0)   - Locking ramsey/uuid (4.9.2)
  - Locking guzzlehttp/promises (2.3.0)  - Locking soukicz/llm (0.5.0)
  - Locking guzzlehttp/psr7 (2.9.0)      - Locking swaggest/json-diff (v3.12.1)
  - Locking phplang/scope-exit (1.0.0)   - Locking swaggest/json-schema (v0.12.43)
  - Locking psr/http-client (1.0.3)      - Locking symfony/deprecation-contracts (v3.6.0)
  - Locking psr/http-factory (1.1.0)     - Locking symfony/polyfill-mbstring (v1.33.0)
  - Locking psr/http-message (2.0)       - Locking ralouphie/getallheaders (3.0.3)
  Writing lock file
  Found 5 security vulnerability advisories affecting 2 packages.

$ git status --porcelain installer/vendor-ai
  (empty — the resolution wrote a lock and touched nothing vendored)
```

**A first attempt failed, and the failure is part of the record.** With Composer 2.9's default
`audit.block-insecure`, the resolver refused outright — *"found guzzlehttp/guzzle[7.10.0] but these
were not loaded, because they are affected by security advisories"*. The manifest could not describe
reality precisely **because** reality is vulnerable. Resolved by setting `block-insecure: false` in
the manifest (D-028): recording what ships is the manifest's job; reporting on it is
`composer audit`'s.

**`composer audit` — full output, 5 advisories / 2 packages, all severity *medium*.**

```
$ composer audit --no-interaction -d installer          (exit code 1)
Found 5 security vulnerability advisories affecting 2 packages:

| Package | guzzlehttp/guzzle | Severity medium | PKSA-93qv-9n9h-6k6p | CVE-2026-55767
| Title   | Dot-only cookie domains match all hosts
| URL     | https://github.com/guzzle/guzzle/security/advisories/GHSA-cwxw-98qj-8qjx
| Affected versions <7.12.1                      | Reported 2026-06-18T14:12:49+00:00

| Package | guzzlehttp/guzzle | Severity medium | PKSA-k22t-f949-t9g6 | CVE-2026-55568
| Title   | Silent HTTPS proxy downgrade to cleartext
| URL     | https://github.com/guzzle/guzzle/security/advisories/GHSA-wpwq-4j6v-78m3
| Affected versions <7.12.1                      | Reported 2026-06-18T14:12:49+00:00

| Package | guzzlehttp/psr7   | Severity medium | PKSA-7qs6-zvnz-h66r | CVE-2026-55766
| Title   | CRLF injection in HTTP start-line serialization
| URL     | https://github.com/guzzle/psr7/security/advisories/GHSA-vm85-hxw5-5432
| Affected versions <2.12.1                      | Reported 2026-06-18T09:49:37+00:00

| Package | guzzlehttp/psr7   | Severity medium | PKSA-gm5x-j3mz-71n9 | CVE-2026-49214
| Title   | CRLF injection via URI host component
| URL     | https://github.com/guzzle/psr7/security/advisories/GHSA-hq7v-mx3g-29hw
| Affected versions <2.10.2                      | Reported 2026-05-25T22:58:15+00:00

| Package | guzzlehttp/psr7   | Severity medium | PKSA-jj5t-2zs1-dcfm | CVE-2026-48998
| Title   | Host confusion via authority reinterpretation
| URL     | https://github.com/guzzle/psr7/security/advisories/GHSA-34xg-wgjx-8xph
| Affected versions <2.10.2                      | Reported 2026-05-25T22:58:15+00:00
```

**Reachability was checked, not assumed** — this is what turns five CVE rows into a triage the user
can actually decide on (full analysis in `docs/04-adoption-audit.md` NEW-05):

```
$ grep -rn "CookieJar|'cookies'" installer/core/ai installer/vendor-ai/soukicz   → no matches
$ grep -rni "proxy"              installer/core/ai installer/vendor-ai/soukicz   → no matches
$ grep -rn "custom_url|custom_endpoint|base_url|api_url" installer/core/ai installer/core/mcp/tools
                                                                                → no matches
$ sed -n '240,248p' installer/core/ai/chat-engine.php
    'anthropic' => new AnthropicClient(...)   'openrouter' => new OpenAICompatibleClient($apiKey, 'https://openrouter.ai/api/v1')
    'openai'    => new OpenAIClient(...)      'ollama'     => new OpenAICompatibleClient($apiKey, 'https://ollama.com/v1')
    'gemini'    => new GeminiClient(...)      default => throw new \InvalidArgumentException(...)
```

Four of the five CVEs need either a cookie jar or an attacker-influenced URI; this code path offers
neither. The fifth (CVE-2026-55568) is the one with a plausible path, because Guzzle honours
`HTTP_PROXY`/`HTTPS_PROXY` from the environment whether the application asks or not.

**The drift guard was proven to fail before it was trusted** — the same discipline slice 1 applied to
the tier boundary. A guard that cannot fail is decoration:

```
$ XDEBUG_MODE=off vendor/bin/phpunit
  ............                                                  12 / 12 (100%)
  OK (12 tests, 57 assertions)          [3 new: manifest ≡ vendored ≡ lock ≡ licence notice]

# inject drift: pin brick/math at 0.14.7, delete phplang/scope-exit from the licence notice
$ XDEBUG_MODE=off vendor/bin/phpunit --filter VendorAiManifest
  1) …::testManifestPinsExactlyWhatIsVendored
     installer/composer.json pins brick/math at a version other than the one vendored.
  2) …::testEveryVendoredPackageAppearsInTheLicenceNotice
     LICENSE-THIRD-PARTY.md must list every vendored package at its vendored version.
  FAILURES! Tests: 3, Assertions: 5, Failures: 2.

# both files restored
$ XDEBUG_MODE=off vendor/bin/phpunit --filter VendorAiManifest
  OK (3 tests, 20 assertions)
```

**Lint — D-025 baseline unchanged.**

```
$ vendor/bin/phpcs --standard=phpcs.xml tests/
  ...... 6 / 6 (100%)                (0 errors, 0 warnings)

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
  A TOTAL OF 204 ERRORS AND 488 WARNINGS WERE FOUND IN 114 FILES
  → unchanged (204/488/114, as on 2026-07-18 and 2026-07-19 slice 1)
```

**Distribution impact verified, not assumed.** The new files must not ship:

```
$ git check-attr export-ignore -- installer/composer.json installer/composer.lock
  installer/composer.json: export-ignore: set
  installer/composer.lock: export-ignore: set     (via the existing unanchored rules, .gitattributes:18-19)
```

The same check found NEW-07's packaging half: `installer/vendor-ai/LICENSE-THIRD-PARTY.md` is
**stripped** from release archives by the blanket `*.md export-ignore`, while every per-package
`LICENSE` file survives. Not changed here — that is H-02's `.gitattributes` policy and a Phase 7 call.

### Slice 3 — evidence (commands and output, 2026-07-19)

Commit: `bbeeb09` — *Sprint 1 slice 3: one matrix + fail-closed current user (S-04, NEW-01)*.

```
$ XDEBUG_MODE=off vendor/bin/phpunit
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.3.12
................................                                  32 / 32 (100%)
OK (32 tests, 219 assertions)
```

**The tests were proven able to fail before they were trusted.** Reverting only the two product
files and re-running the suite:

```
$ git stash push installer/core/helpers-global.php installer/core/app.php
$ XDEBUG_MODE=off vendor/bin/phpunit
1) CurrentUserFailClosedTest::testSessionWithoutUserIdIsDeniedNotPromoted
2) CurrentUserFailClosedTest::testSessionWithoutUserIdHoldsNoPermission
3) CurrentUserFailClosedTest::testSessionNamingAMissingUserIsDenied
4) PermissionMatrixTest::testTheMatrixIsDefinedExactlyOnce
FAILURES!  Tests: 31, Assertions: 208, Failures: 4.
$ git stash pop
```

Recorded honestly: `V1MigrationTest` passes both with and without the revert, because
`migrateFromV1Config()` itself was already correct and already wired into boot. Those tests verify
existing behaviour the fail-closed change depends on — they do not verify a new fix.

**Upgrade from the REAL previous version** (`Installed base: yes`, so clean-install-only does not
satisfy this). v0.30.1 is exported from git into a temp directory outside the repo, installed by
**its own installer** over `php -S`, then upgraded to the working tree:

```
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
== Klytos upgrade test: v0.30.1 -> working tree
-- 1. Exporting v0.30.1 from git ............ VERSION on disk: 0.30.1
-- 2. Running the v0.30.1 installer ......... installer responded 200
-- 3. Verifying the v0.30.1 install ......... 5/5 PASS (pre-upgrade, VERSION 0.30.1)
-- 4. Upgrading in place .................... upgraded to VERSION 0.31.1-beta.1
-- 5. Booting the upgraded install
   PASS  the migration is idempotent on an upgraded install (found 1)
   PASS  the upgraded owner still resolves after the fallback removal
   PASS  the upgraded owner still holds owner-only permissions
   PASS  a session without klytos_user_id is DENIED, not promoted (NEW-01)
   PASS  that session holds no owner permission
   PASS  that session holds no permission at all
-- 6. Breaking the install so the boot migration must fail ... 4/4 PASS
-- 7. Booting the broken install (this used to fatal on every request)
Klytos: v1.x owner migration failed — this install has no owner record, so every
permission check will deny until one exists. Underlying error: Cannot migrate:
admin_email is missing or invalid in config.
   PASS  App::boot() completed without a fatal
   PASS  boot left the install with no owner, rather than half-creating one
   PASS  no session is promoted into the missing-owner gap
   PASS  the surviving app denies owner-only permissions
   PASS  the surviving app denies every permission
== UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)
```

Steps 6 and 7 exist **because the `code-reviewer` pass found the Step 10b `try`/`catch` had no test
that drove execution through it** — a structural fix with no test is an unverified claim by this
project's own rule. They were then proven to detect its absence, the same way the isolation
primitive was:

```
$ git stash push installer/core/app.php     # remove the try/catch
$ XDEBUG_MODE=off bash scripts/dev/upgrade-test.sh
PHP Fatal error:  Uncaught RuntimeException: Cannot migrate: admin_email is missing
or invalid in config.  in .../core/user-manager.php:658
Stack trace:
#0 .../core/app.php(360): Klytos\Core\UserManager->migrateFromV1Config(Array)
#1 .../scripts/dev/upgrade-assert.php(76): Klytos\Core\App->boot()
$ git stash pop
```

**Storage isolation (D-030), proven before the slice relied on it.** The rollback primitive is what
makes `V1MigrationTest` safe, since it deletes the seeded owner:

```
$ XDEBUG_MODE=off vendor/bin/phpunit --testsuite integration   # with isolation DISABLED
1) PlaygroundIsolationTest::testBPlaygroundIsRestoredForTheNextTest
The deleted user is still missing — the playground was NOT rolled back, and every later
authorization test is running against a mutated fixture.
FAILURES!  Tests: 7, Assertions: 32, Failures: 1.
```

The proof run really did delete the seeded `editor`; the playground was reseeded immediately after
(`php scripts/dev/seed-playground.php --reset`), and the suite then ran green twice in a row with
`git status --porcelain installer/` empty.

**Lint (D-025).** Zero violations introduced. The three pre-existing auto-fixable errors in
`app.php` were taken under D-025's opportunistic rule, so the baseline **decreased**:

```
$ vendor/bin/phpcs --standard=phpcs.xml --report=summary \
    installer/core/helpers-global.php installer/core/app.php installer/core/user-manager.php tests/ scripts/dev/
A TOTAL OF 0 ERRORS AND 8 WARNINGS WERE FOUND IN 3 FILES

$ vendor/bin/phpcs --standard=phpcs.xml --report=summary installer/core installer/admin
A TOTAL OF 201 ERRORS AND 488 WARNINGS WERE FOUND IN 114 FILES     (was 204 / 488)
```

Remaining warnings are line-length in `app.php` (pre-existing) and `PSR1.Files.SideEffects` on
`upgrade-assert.php` — the same class already exempted for `tests/bootstrap.php`: it is a dev entry
point that must both define a helper and execute.

**Independent review (Phase 5 §2 test point, item f).** Two subagents on the slice diff.
`security-auditor`: **no blocking findings** — traced every caller of `klytos_current_user()`,
`Auth::getUserId()` and direct `$_SESSION['klytos_user_id']` reads and found no residual path to
privilege; confirmed display-only callers degrade safely on `null`; confirmed the new log calls carry
no secret, hash or personal data; confirmed the temp-dir scripts cannot delete outside their `mktemp`
directory. It also sharpened **NEW-02** with a finding on adjacent code
(`ai/chat-engine.php:401-421` filters the AI tool list only for exactly `viewer`/`editor`, so any
unrecognized role falls through unfiltered — fail-open, and Sprint 2's enforcement point must
default-deny instead). `code-reviewer`: **one blocking finding** (the untested `try`/`catch`, fixed
above) plus three non-blocking, of which two were applied (the role fail-closed tightening and its
test; the wrong boot-step number in a comment) and one was noted as a nit (`klytos_has_permission()`
constructs `UserManager` twice per call via `klytos_current_user()`).

## Session-start freshness

At the **first** test point of every working session, the playground is booted from the commands in
`docs/playground.md` exactly as written — this validates the environment and the document in one
move — and `docs/playground.md` is stamped `last verified: <date>`. Instructions that no longer start
the playground are a defect caught here, not by the user.

| Date | Booted from documented commands | Result | Doc stamped |
|---|---|---|---|
| 2026-07-18 | `php scripts/dev/seed-playground.php --reset` then `php -S 127.0.0.1:8080 -t . scripts/dev/router.php` | OK — admin 302→login, login 200, MCP 401 unauthenticated / 177 tools authenticated | yes |
| 2026-07-19 | same two commands, verbatim from `docs/playground.md` | OK — admin 302, login 200, MCP 401 unauthenticated, `config/.encryption_key` 403, 177 tools authenticated. Identical to the slice-0 baseline; the NEW-03 warning appeared on page creation exactly as the document says it would | yes |
| 2026-07-19 (slice 2 session) | same two commands, verbatim | OK — admin 302, login 200, MCP 401 unauthenticated, `config/.encryption_key` 403, 177 tools authenticated. Unchanged; NEW-03 warning present as documented | yes |
| 2026-07-19 (slice 3 session) | same two commands, verbatim | OK — admin 302, login 200, `config/.encryption_key` 403, MCP 401 unauthenticated. The playground was additionally re-seeded mid-session after the isolation proof run deliberately deleted a seeded user (see the slice-3 evidence block) | yes |

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
