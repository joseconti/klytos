# Sprint 3 — `vendor-ai/` CVE remediation, and the AI stack fails safe

- **Planned:** 2026-07-25 (plan mode, approved by the user). Kickoff re-validation ran the same session.
- **Status:** IN PROGRESS — slice 1 next.
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
  Gemini URL's **path** segment. Percent-encoded by psr7, so no CRLF and no authority relocation; worst case
  is an authenticated-admin denial-of-function. Verified independently and recorded as its own audit finding
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
| 2 | NEW-06 fail-safe guard + standing advisory detection | **NEW-06** | pending | — | Typed `UnsupportedRuntimeException` before the vendor `require_once`; pure testable policy split out (D-044 precedent); message via `__()` in all 20 catalogues; an audit **action**, not a filter. Plus the non-blocking `composer audit` CI job. |

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

## Close-out

| Requirement | Status |
|---|---|
| Full suite green (every test, not only this sprint's) | pending |
| `keel-verify` output pasted | pending |
| `composer audit -d installer` → zero, measured both sides | pending |
| Upgrade tested from the REAL previous version | pending |
| Lint baselines held (all five, measured per scope, no default value — L-016) | pending |
| `code-reviewer` + `security-auditor` per slice, on a finished diff (L-015) | pending |
| `docs-verifier` over everything the sprint touched | pending |
| Playground-QA fresh-context pass | pending |
| Numbered try-it script handed to the user, debug log ON | pending |
| **User's recorded verdict** | pending |
| `PROGRESS.md` / `lessons-learned.md` / `token-ledger.md` updated | pending |
| Continuation prompt produced unprompted | pending |
| Finished docs archived to `docs/old/sprint-3/` (or "nothing qualified", stated) | pending |
