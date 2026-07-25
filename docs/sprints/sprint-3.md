# Sprint 3 — `vendor-ai/` CVE remediation, and the AI stack fails safe

- **Planned:** 2026-07-25 (plan mode, approved by the user). Kickoff re-validation ran the same session.
- **Status:** **CLOSED 2026-07-25** — both slices. Audit **NEW-05** and **NEW-06** CLOSED. Close-out table at the foot of this file; the user's own test verdict is the one row still open.
- **Scope basis:** audit **NEW-05** (CVEs in the vendored HTTP stack), triaged to a dedicated post-Sprint-1
  slice by **D-029**, plus audit **NEW-06** (the vendored AI stack needs PHP 8.3 while the product declares
  8.1+, with no guard), whose recorded natural home is this slice.

## Why this sprint exists

`installer/vendor-ai/` is the lazily-loaded vendored Composer tree behind AI chat. Sprint 1 slice 2 made it
auditable (D-028) and the project's first `composer audit` found CVEs that were reported, not patched —
D-022's standing rule. D-029 set the closing date: a dedicated slice after Sprint 1, with its own test point
and its own estimate version. Sprint 1 closed 2026-07-20 and Sprint 2 closed 2026-07-24, so the trigger has
fired twice over.

Stated plainly rather than inflated: **none of the 11 advisories has a demonstrated exploitation path in
Klytos** (see the re-validation below). The bump happens because a self-updating CMS with a real installed
base should not ship known-vulnerable dependencies and D-029 set a closing date — not because an exploit was
found.

## Re-validation of assumptions (Phase 5 §0 kickoff, step 2)

Verified against source and against live commands this session, not against the recorded plan. **Two things
D-029 recorded are now stale, and one of them is load-bearing.**

1. **The advisory count grew 5 → 11.** `composer audit -d installer` on 2026-07-25 reports 11 advisories
   across the same 2 packages at the same pinned versions: 7 in `guzzlehttp/guzzle` 7.10.0, 4 in
   `guzzlehttp/psr7` 2.9.0, all medium.

2. **D-029's two halves contradict each other, and the acceptance criterion wins.** It fixed the target as
   *"bump guzzle ≥ 7.12.1 and psr7 ≥ 2.12.1"* **and** *"re-audit to zero"*. The floors have moved: three
   guzzle advisories are fixed only in `7.15.1`, one in `7.14.2`, one in `7.12.3`; psr7's newest is fixed in
   `2.12.3`. So 7.12.1/2.12.1 would leave **6 of 11 open** and the slice would fail its own acceptance.
   Per **L-014** — *when a recorded remediation and a recorded acceptance criterion disagree, the criterion
   is the requirement and the remediation is a guess* — the criterion governs. **User decision 2026-07-25:
   audit to zero.** D-029's remediation *shape* is untouched; its version numbers are corrected as a
   recorded amendment (**D-052**).

3. **The bump is three packages plus a new one, not two.** `guzzle 7.15.1` requires `psr7 ^2.13`,
   `promises ^2.5.1` and `symfony/polyfill-php80 ^1.25`; the last is **not currently vendored**. The tree
   goes **16 → 17 packages**.

   | package | now | target | why |
   |---|---|---|---|
   | `guzzlehttp/guzzle` | 7.10.0 | **7.15.1** | highest advisory floor is `<7.15.1` |
   | `guzzlehttp/psr7` | 2.9.0 | **2.13.0** | own floor is `<2.12.3`; guzzle 7.15.1 requires `^2.13` |
   | `guzzlehttp/promises` | 2.3.0 | **2.5.1** | guzzle 7.15.1 requires `^2.5.1` |
   | `symfony/polyfill-php80` | — | **new** | required by both guzzle 7.15.1 and psr7 2.13.0 |

4. **It is still a re-vendor, not a dependency-tree rewrite** — D-029's central cost claim holds.
   `soukicz/llm` 0.5.0, the only genuine root requirement, asks for `guzzlehttp/guzzle: ^7.9`,
   `guzzlehttp/psr7: ^2.7`, `guzzlehttp/promises: ^2.0` (`installer/vendor-ai/soukicz/llm/composer.json:23-31`,
   read directly). All three targets satisfy it.

5. **The PHP floor does not move.** It comes from `soukicz/llm` (`php: >=8.3`), untouched, so
   `config.platform.php = 8.3.0` and the generated `vendor-ai/composer/platform_check.php` regenerate
   identically. NEW-06 is therefore neither created nor closed by the bump — it is closed on its own merits
   in slice 2.

6. **D-029's stated command must not be followed literally.** It says `composer install -d installer`, which
   installs *what the lock already says* — i.e. reinstalls 7.10.0. The exact pins are hand-edited first and
   then `composer update`. They must stay **exact versions with no operator**: `VendorAiManifestTest`
   normalises only a leading `v`, so a constraint written `^7.15` fails `assertSame` against `7.15.1`.

### Reachability — redone from scratch, not inherited

D-029's assessment turned on *"no cookie jar, no user-controllable URL in the AI module"*, which does not
cover the new URI/host-parsing advisories. Re-derived, and every decisive claim re-verified by hand after
the subagent reported it (**L-013** — a subagent report is a hypothesis):

- **The NEW-15 / `SafeHttp` adjacency does not exist.** `installer/core/safe-http.php` and
  `installer/core/http-client.php` contain **zero** references to `GuzzleHttp` or `Psr\Http` — they are pure
  first-party parsing (`parse_url`, `filter_var`, `inet_pton`, `gethostbynamel`, `dns_get_record`) over cURL
  and stream transports. "Host confusion via authority reinterpretation" needs one URL parsed by two
  different parsers, and the two stacks never share a URL. **CVE-2026-59882 and CVE-2026-48998 have no
  bearing on the SSRF control.** That concern is resolved, not carried forward.
- **The only first-party Guzzle references repo-wide are two catch clauses** —
  `installer/core/ai/chat-engine.php:197` and `:216` — exception *types*, not URLs. No first-party file
  constructs a Guzzle client, request or URI.
- **Cookies (4 advisories — dot-only domains, IP-address domains, host-only scope, unbounded response
  cookies): no path.** The string `cookie` does not occur anywhere in `installer/core/ai/` or in
  `installer/vendor-ai/soukicz/llm/`. Guzzle's default is `'cookies' => false`
  (`vendor-ai/guzzlehttp/guzzle/src/Client.php:236`) and the jar is built only on `=== true` (`:260-261`),
  so `CookieJar`/`SetCookie` are shipped but never loaded.
- **Proxy (2 advisories): no attacker-reachable path.** Nothing in the stack sets `proxy`. Guzzle's own env
  read is SAPI-guarded for `HTTP_PROXY`, and no request header can produce a `$_SERVER` key named exactly
  `HTTPS_PROXY` under CGI. It is an operator-misconfiguration surface — precisely what D-029 already said
  about CVE-2026-55568, and what this bump fixes.
- **Referer/fragment leakage: no path.** `allow_redirects` defaults `'referer' => false` and the header is
  actively stripped; no caller overrides it.
- **URI/host parsing: no exploitable path.** All five provider authorities are hardcoded literals and
  `chat-engine.php`'s `default => throw` pins the provider enum.
- **One residue found by the trace, and it is not a CVE hit:** `$input['model']` at
  `installer/admin/api/ai-chat.php:168` is never validated against `AiKeyManager::PROVIDERS` and reaches the
  Gemini URL's **path** segment. Percent-encoded by psr7, so no CRLF and no authority relocation; the host
  never moves in any spelling. Measured rather than reasoned about: a `#` pushes `key={apiKey}` into the URI
  **fragment**, so the request leaves with **no API key** (401), and a `?` truncates the path. It is a
  denial-of-function, and the population is **editor and above** (`ai.use`, widened by D-051) — not
  "authenticated admin", a framing the slice-1 `security-auditor` corrected. Recorded as audit **NEW-34**
  with a trigger — **not fixed here** (adjacent subsystem, D-031's narrowing).

**Standing detection was missing and that is why the growth went unseen.** `composer audit -d installer`
exists as a root script but neither CI nor `keel-verify` runs it, so 5 → 11 was noticed by hand at a sprint
close, five days late. Closed in slice 2 (user decision 2026-07-25).

## Acceptance — this sprint is done when

1. `composer audit -d installer` reports **zero** advisories, measured before (11) and after (0).
2. `tests/Unit/VendorAiManifestTest.php` is green with the tree at 17 packages, and was **proven to FAIL**
   with one of its four records deliberately left stale.
3. A named test proves the vendored API surface the AI stack depends on still resolves after the bump, and
   was proven to fail against a wrong symbol.
4. `App::getChatEngine()` refuses with a typed, translated error below PHP 8.3 instead of fataling inside a
   vendored file, with the policy proven to refuse 8.1/8.2 and allow 8.3/8.4, plus a positive control on the
   running runtime.
5. CI reports the current advisory list on every run without failing an unrelated PR.
6. The full suite is green (not only this sprint's tests), `keel-verify` output pasted, the upgrade path
   tested **from the real previous version**, and all five D-025 lint baselines held exactly.
7. The user's own test verdict is recorded.

## Slices

| # | Slice | Closes | Status | Test point result | Notes |
|---|-------|--------|--------|-------------------|-------|
| 1 | The re-vendor to zero advisories | **NEW-05** | **closed 2026-07-25** | **PASS** — `composer audit -d installer` **11 → 0**, measured both sides; full suite **195 tests / 986 assertions** (+3/+25); keel-verify 10 checks, exit 0, same 2 Phase-7 WARNs; upgrade from real v0.30.1 PASS; all five D-025 baselines held **exactly** (193/488, 113/109, 0/0, 0/0, 0/2). Evidence in `docs/05-test-points.md` | guzzle 7.10.0→**7.15.1**, psr7 2.9.0→**2.13.0**, promises 2.3.0→**2.5.1**, + **`symfony/polyfill-php80` v1.37.0** (16→**17** packages, 482→**509** tracked files). **The diff is 95 files, not the 482 D-029 implied** — that was the tree size, not the change size. `VendorAiManifestTest` was observed **RED on all three methods** against the half-updated records before it went green — no injected fault needed. It also exposed **L-020**: its root-package skip was hardcoded to `'__root__'` and had never been exercised, because the guard was built against a tree vendored elsewhere and never regenerated here. `installer/composer.json` gains `"version": "1.0.0"` so the tracked generated `installed.php` stops embedding the branch name and commit sha. New `VendorAiCompatibilityTest` (3 tests), proven to fail in three directions |
| 2 | NEW-06 fail-safe guard + standing advisory detection | **NEW-06** | **closed 2026-07-25** | **PASS** — full suite **206 tests / 1007 assertions** (+10/+17); three probes proven (constant drift, off-by-one, guard relocated below the require) and reverted; keel-verify 10 checks exit 0, INDEX parity green after +2 rows; upgrade from real v0.30.1 PASS; `composer audit` still zero; all five D-025 baselines held exactly; live per-role walk 200/200/200/403. Evidence in `docs/05-test-points.md` | Typed `Klytos\Core\Ai\UnsupportedRuntimeException` thrown **above** the vendor `require_once` — pinned by a source-order test, the only way to reach that property on a supported host. Pure `App::aiRuntimeUnsupportedReason( int )` (D-044 split) because PHP cannot be downgraded in the suite. `ai.unsupported_runtime` in all 20 catalogues; `ai.runtime_unsupported` is an **action** with no core listener, said in those words (L-019). Non-blocking `vendor-advisories` CI job, both branches proven. **L-021** — the first per-role walk was answered by a leftover server from the previous session that every L-011 tell agreed with; two `docs/playground.md` defects fixed as a result, including a bind check that could not fire |

## Explicitly out of scope (named, so it is not mistaken for oversight)

- **The unvalidated `model` parameter** (`admin/api/ai-chat.php:168`). Recorded with a trigger; it is an
  adjacent subsystem and an authenticated-admin denial-of-function, not a CVE path.
- **Raising the product's PHP floor to 8.3.** Four different floors are asserted in this codebase (8.0 in
  `installer/index.php`, 8.1 in README/`install.php`/`updater.php`, 8.2 for the suite per D-027, 8.3 for
  vendor-ai). Reconciling them is a support-matrix decision with installed-base consequences and belongs to
  **D-027's trigger**, not to a CVE slice. Slice 2 makes the 8.3 requirement *fail safe*; it does not
  declare it.
- **`config.audit.block-insecure`** stays `false`. D-028 owns that flag and its reasoning (the manifest's job
  is to record what ships; `composer audit` is the reporting mechanism). Left alone deliberately.
- **NEW-03** (`Hooks::doAction()` by-reference), **NEW-15** (DNS rebinding), **NEW-32** (audit hooks have no
  listener), **NEW-33** (Spanish terminal strings), the **NEW-11** authentication slice, the
  `.gitattributes` review (**NEW-27/28/H-02**, Phase 7). Each keeps its own trigger.
- **NEW-09's one-line fix remains FORBIDDEN** (D-036).

## Risks carried into this sprint

1. **No automated test can prove "AI chat still works" end to end** — that needs a live provider key, which
   the playground does not have. D-029 named this risk at triage. The suite proves the vendored code *loads*
   and that its *API surface is intact*; a real provider round-trip is handed to the user in the try-it
   script. Stated rather than implied (**L-014**).
2. **A ~500-file third-party diff must still pass the confidential-data scan.** Real work here, not a
   formality — and `.gitattributes` means the manifest, lock and notice are export-ignored while the
   vendored PHP ships.
3. **Never run `installer/install.php` or `cli.php build` in the checkout** (NEW-04). `composer update -d installer`
   cannot reach them: the root manifest has no install hooks and `installer/composer.json` sets
   `allow-plugins: false`.
4. **The autoloader init hash** (`ComposerAutoloaderInita67a…`, `vendor-ai/autoload.php:22`) is derived from
   the vendor-dir path and should be stable — but if it moves, three more generated files change with it.
   Checked in the diff rather than assumed.
5. **A re-vendor cannot move the lint baselines** — `phpcs.xml:27` excludes `installer/vendor-ai/*`. If a
   baseline moves, that is itself the finding.

## Close-out — filled 2026-07-25

| Requirement | Status |
|---|---|
| `composer audit -d installer` → zero, measured both sides | **PASS** — 11 advisories before, **0** after. D-029's recorded floors would have left 6 open; the criterion beat the derivation (D-052, user decision) |
| Full suite green (every test, not only this sprint's) | **PASS** — `XDEBUG_MODE=off vendor/bin/phpunit` → **206 tests / 1007 assertions**, 0 failures, 0 skips on PHP 8.3 (sprint start: 192/961) |
| `keel-verify` output pasted | **PASS** — 10 checks, exit 0, the same 2 WARNs owned by Phase 7. INDEX parity green after +2 rows (classes 100→101, actions 307→308, total 955→**957**) |
| Upgrade tested from the REAL previous version | **PASS** — `UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)`, run after both slices |
| Lint baselines held (all five, per scope, no default value — L-016) | **PASS**, exactly: core+admin **193/488**, plugins **113/109**, tests **0/0** (35 files), installer/public **0/0**, scripts **0/2**. `phpcs.xml:27` excludes `vendor-ai/`, so a re-vendor structurally cannot move them |
| `code-reviewer` + `security-auditor` per slice, on a finished diff (L-015) | **DONE, 2/2 slices.** Slice 1: both **no blocking**; two non-blocking doc-precision fixes taken. Slice 2: `security-auditor` **no blocking** (corrected a premise, found **NEW-35**); `code-reviewer` **ONE BLOCKING, correct** — see below |
| `docs-verifier` over everything the sprint touched | **PASS — no blockers.** INDEX parity exact in both directions and **957 = 957** re-counted per section (146/101/308/117/206/34/26/19); every file in `docs/reference/` has a row and every row's doc exists. All four new surfaces documented **and** their examples checked against source — `aiRuntimeUnsupportedReason(80200)` → `'php_version_too_low'`, `(80300)` → `null`, the exception signature, and the action's parameter count and order. The `ai.unsupported_runtime` key confirmed present in **all 20** catalogues with both placeholders. No stale NEW-05/NEW-06 references, and no other skill left asserting a superseded package or advisory count |
| Playground-QA fresh-context pass | **DONE — verdict "largely accurate but does not work end to end", and it earned its keep again.** ~60 commands run. **Both areas this sprint edited came back clean** — the `$RPORT` bind check fires in both directions and both kill-by-port Stop recipes work, with the L-021 `pkill` claim confirmed empirically (`pgrep` matched 0 for the `-d`-flagged server, 1 for the plain one). It also re-confirmed §3a's entire 5×4 table byte-for-byte, the 206/197/56/19 counts, NEW-11, NEW-32, NEW-33 and the include-time gate caveat. **11 document defects found, all fixed before this close** — the two most serious created by this very sprint. See the note below |
| Numbered try-it script handed to the user, debug log ON | **DONE** — handed with this close |
| **User's recorded verdict** | **PENDING** — awaiting the user's own walk. A reported failure reopens the sprint |
| `PROGRESS.md` / `lessons-learned.md` / `token-ledger.md` updated | **DONE** — **L-020** (a drift guard built against an artifact it had never produced), **L-021** (the squatter was our own leftover, and every L-011 tell agreed with it), **L-022** (the CI workflow has never run); token-ledger row **18**, and the layout slip its own note deferred to "the sprint close" was fixed here |
| Continuation prompt produced unprompted | **DONE** — handed with this close |
| Finished docs archived to `docs/old/sprint-3/` (or "nothing qualified", stated) | **Nothing qualified — stated, not skipped.** Phase 5 §5.7 moves docs that are finished AND no longer consulted. `sprint-1.md`/`sprint-2.md` are still read as precedent (this sprint read both); `theme-package-model.md` specifies an upcoming sprint; `estimate.md` gained v3 today; `04-adoption-audit.md` gained NEW-34/NEW-35. The state files, specs, technical plan, flows and api/reference docs never move while the project is alive |

### The blocking finding was this sprint's own subject turned against it

Slice 2's `code-reviewer` found that the two new integration tests would have turned **CI's PHP 8.2
leg red** — for behaviour that is *correct* on 8.2. A slice whose entire premise is "below PHP 8.3 the
AI stack must degrade gracefully" had written tests that break the build below 8.3.

Three things about it are worth carrying forward:

1. **It was measured by simulating the environment, not by reasoning about it.** PHP could not be
   downgraded, so the *floor* was raised instead. That single trick did three jobs this sprint: it
   drove the refusal branch end to end (proving `__()` resolves in the throwing path — the sharpest
   risk in the slice), it reproduced CI's 8.2 leg, and it proved the fix. **8 tests in 3 classes**
   broke, of which **3 have been latent since Sprint 2 slice 3** and 3 came from slice 1.
2. **The obvious fix was rejected on the record.** Letting them skip would trip D-045's
   "a skip is a hard failure" rule — which exists to catch an un-seeded playground and must keep
   meaning exactly that. So the 8 carry `#[Group('ai-runtime')]` and the 8.2 leg excludes them
   explicitly, with `requireAiRuntime()` (shaped like the existing `requirePlayground()`) still
   skipping them for a developer running locally on 8.1/8.2. The group is applied **per method** in one
   class so the test asserting the refusal *message* keeps running on 8.2 — the runtime where that
   message is what an operator actually sees.
3. **Why nobody had seen it: CI has never run.** Not once. The workflow was written in Sprint 1 slice 9
   and all 29 commits since are unpushed, so its second matrix leg had been broken for two sprints
   behind a green-looking config. **L-022** — a workflow with no run checks nothing, which is **L-019
   one level out**.

One reviewer suggestion was **refuted**: dropping inner-paren spaces contradicts
`docs/03-technical-plan.md` §3 (*"do not 'correct' it"*). It was applied before checking, then
reverted — L-013 and L-015 in one move.

### The fresh-context pass found 11 defects, and the two worst were this sprint's own

**Both of the serious ones were mine, and neither review subagent could have caught them** — they are
properties of the *document* against the *world*, not of the diff:

1. **The "Auditing the vendored dependencies" section documented the opposite of reality.** It said
   `composer audit` reports **11 advisories** across guzzle 7.10.0 and psr7 2.9.0 and that "seeing them
   is the current expected output". Slice 1 made that false four hours earlier and I did not update the
   document. It now states zero as the expected result, corrects 16 packages → **17**, and adds the
   distinction the old text could not make: **a clean run and a run that never happened look alike**, so
   check the exit code, not the text.
2. **A STOP-box command that could never match anything.** `pkill -f "127.0.0.1:8099"` was supposed to
   stop the role-system server — but §1 *forbids* `RPORT=8099`, and the pattern also misses any
   `php -S` carrying `-d` flags, which is exactly what L-021 records. Two independent reasons it was a
   no-op. Now kills by port, and the stale "16 tests never run" is corrected to the real **12**
   (`AdminGateHttpTest`, re-measured).

Nine more, all fixed and each re-run verbatim: the `klytos_delete_page` example is **only accidentally
safe** (the seed has no `index` page — on an install that has one, an owner token following the table
deletes the front page), so the hazard is now stated with a "look before you aim" command; §3a's
per-role table was not reproducible from its own text ("swap `$TOK_*` and repeat" hid four different
argument shapes) and now ships a runnable loop; §3a minted four privileged bearer tokens per walk and
never revoked them — a cleanup step was added, and **the first version of it was wrong**
(`revokeBearerToken()` takes an ID, not a label, so it would have silently revoked nothing — the same
free-reassurance defect this sprint fixed twice elsewhere), corrected and then run for real, revoking
the 4 tokens the QA pass had left; the `$KPORT` server had no bind check while `$RPORT` did; the
keel-verify section documented only exit 0/1 and never mentioned the WARN tier a clean run actually
prints; `--testsuite` lines dropped `XDEBUG_MODE=off`; `kill $(...)` with nothing listening errored
confusingly; `/tmp/klytos-sessions` was never cleaned; and the seeder hardcoded `8080` into the access
file while the whole document is `$KPORT`-parameterised.

### Findings opened, not fixed

- **NEW-34** — `$input['model']` is unvalidated and reaches the Gemini URL's path segment. Measured:
  a `#` pushes `key={apiKey}` into the fragment so the request leaves with **no API key**; the host
  never moves; CRLF is percent-encoded. Denial-of-function, **editor and above** (`ai.use`, D-051) —
  the slice-1 auditor corrected an "admin-only" framing.
- **NEW-35** — `ToolRegistry::call()` **never validates `$params` against `inputSchema`**, so every
  tool contract the MCP server publishes is advisory, and a second URL interpolation
  (`ai-image-generator.php:62`, reachable via `klytos_generate_image`) rides on that. The systemic half
  matters more than the instance. Not fixed: enforcing schemas is a behaviour change for every MCP
  client and needs the D-034 treatment.
