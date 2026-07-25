# Sprint 4 — the hook mutation contract, and owner recovery

- **Planned:** 2026-07-25 (plan mode, approved by the user). Kickoff re-validation ran the same session.
- **Status:** **CLOSED 2026-07-25** — both slices. Audit **NEW-03**, **NEW-36** and **NEW-08** CLOSED.
- **Scope basis:** audit **NEW-03** (by-reference action listeners are silently broken), deferred by
  **D-026** in Sprint 1 slice 0; and audit **NEW-08** (there is no supported way to recreate a missing
  owner), whose recorded trigger is literally *"with the NEW-03 slice, after Sprint 1"*.

## Why this sprint exists

`Hooks::doAction()` collects its arguments variadically (`mixed ...$args`, `hooks.php:124`) and
dispatches with `call_user_func_array()` (`hooks.php:145`). Variadics copy. A listener declaring
`&$param` therefore cannot bind: PHP emits a warning, **runs the callback body against a copy**, and
silently discards the write.

D-026 deferred it for two stated reasons, both now expired: no test harness existed (it has since
Sprint 1 slice 1), and the trigger was *"its own slice, before or alongside Sprint 2"* (Sprint 2 and
Sprint 3 have both closed).

Stated plainly rather than inflated: the functional damage is **narrow** — one listener, so a new page
never inherits its post type's `x402_default_enabled` and falls through to the global default. What
makes it worth a slice is the **systemic** half: the mutation contract is broken for every
by-reference listener, core or third-party, on all 308 action names, and nothing signals it.

## Re-validation of assumptions (Phase 5 §0 kickoff, step 2)

Verified against source and by running PHP this session, not against the recorded plan. **Three things
the record says are wrong, and one of them decides the design.**

### 1. The obvious fix is dead, and it is dead by measurement

`mixed &...$args` does bind by reference (probed, works). But PHP refuses a **non-variable** argument
to a by-reference variadic. Measured, one case per real call-site shape:

| argument shape | result under `mixed &...$args` | real example |
|---|---|---|
| plain `$var`, `$obj->prop`, `$arr['k']` | OK | `PageManager::create()` arg 2 |
| string / int / `null` literal | **fatal `Error`** | `PageManager::create()` arg 3 (`'create'`) |
| array literal | **fatal `Error`** | `comment-submit.php:224` |
| `??` expression | **fatal `Error`** | `page-editor.php:419` (×9 in that file) |
| ternary, concatenation, class constant | **fatal `Error`** | `updater.php:978` (`'local'`) |
| function-call result | `Notice`, then OK | — |
| **undefined** array key / variable | OK — and **silently creates it** | latent everywhere |

**36+ call sites** pass such an argument. The last row is the worst: `doAction( 'x', $data['maybe'] )`
would materialise `$data['maybe'] = null` in the caller's array. Refuted, not merely disfavoured.

### 2. "301 registered actions" does not reproduce — and is wrong in kind, not just in number

D-026, `04-adoption-audit.md` NEW-03 and D-04 all repeat it. It is a stale copy of
`docs/api/INDEX.md`'s `| Actions |` row **at commit `622d54c`**, traced through the file's history
(301 → 302 → 304 → 306 → 307 → **308** at HEAD). Nothing *registers* 301 anything.

Re-measured this session, three ways — the third settling a disagreement between the first two:

| measure | value |
|---|---|
| distinct action **names** | **308** (307 same-line literals + 1 multi-line, `ai.runtime_unsupported` at `app.php:1138`) |
| action **fire** sites | **363** |
| shipped action **registrations** | **23** (core 15, admin 3, plugins 4) |
| distinct filter names | **120** (118 before this slice added 2) |
| filter fire sites | **128** |
| shipped filter **registrations** | **32** |
| max payload args at any fire site | **4** |

**Two earlier passes at this table disagreed, and the reconciliation found a real gap.** A line-based
grep and a subagent's count differed on registrations (35 vs 27) and filter names (115 vs 118) —
differently scoped (one counted `tests/`, where this slice's own new file adds 9 registrations; the
other counted the helper's delegation line), and **both blind to the 4 multi-line `applyFilters()`
calls** a line-based pattern cannot see. The figures above come from a third pass that strips
comments and helper definitions and matches across newlines. It also found **`x402.should_protect`
(`x402/gate.php:70`) fired in code with no `docs/api/INDEX.md` row** — a pre-existing gap from
adoption's line-based extraction, fixed here (Filters 119 → **120**, total **961**).

The blast radius of the by-reference defect is **one listener**, not 301 sites. The original framing
is what made this look bigger than it is — and, ironically, is part of why it was deferred twice.
**L-015 again: a number copied from another document is not a measurement.**

### 3. Exactly ONE by-reference listener exists repo-wide, and zero by-reference filter callbacks

`installer/core/x402-bootstrap.php:194`. Verified two ways. The only other by-reference parameters in
first-party code are four private internals never registered as hooks (`hooks.php::removeCallback`,
two `two-factor.php` CBOR parsers, `chat-engine.php::convertTools`). Every hook registration in the
repo is an inline closure — no array or string callable hides an indirection. The four `use ( &$x )`
captures in tests are by-reference *capture*, a different mechanism, unaffected.

### Other corrections found and applied in this sprint

- `04-adoption-audit.md:544` cites `core/app.php:486` for the unconditional x402 load. The real line is
  `:546`. Fixed by naming the method rather than a line number (the D-053 precedent, so the next
  insertion cannot rot it again).
- `docs/api/INDEX.md`'s filter count is already off by one at HEAD, independently of this sprint.
- `docs/docs:149` documents the **wrong arguments** for `page.before_save` (`$slug, $data`; the real
  ones are `$page, $action`). Pre-existing, in a stale Spanish table. `docs/docs` is audit **H-06** and
  needs the user's classification — flagged, not fixed.

### The environment

Playground booted from `docs/playground.md` exactly as written (kickoff step 3): `KPORT=8083`, bound
cleanly, admin → **302**, anonymous MCP → **401**, no `Server:` header, owning PID confirmed as this
session's own (L-011 + L-021). 8080 squatted again.

## Slices

| # | Slice | Closes | Status | Test point result | Notes |
|---|-------|--------|--------|-------------------|-------|
| 1 | Actions are fire-and-forget, enforced; page data gets a real filter | **NEW-03**, **NEW-36** | **closed 2026-07-25** | **PASS** — suite **206 → 221 tests / 1007 → 1029 assertions**; keel-verify 10 checks exit 0, INDEX parity green after +4 rows (102/308/**120**, total **961**); upgrade from real v0.30.1 PASS; five D-025 baselines held with core+admin **improved** 193 → **192**. Evidence in `docs/05-test-points.md` | Refusal at registration (typed `HookContractException`, both registries); `page.save_data` filter above the `page.before_save` action; x402 converted. **NEW-36 found by driving the feature** — the post-type allow-list dropped what its own extension filter added, so x402's checkbox had never persisted. `failOnWarning="true"` enabled per `phpunit.xml`'s own trigger. Both reviews **no blocking**; they **disagreed** on the reserved-key gap and the auditor was right (**L-023**) |
| 2 | Owner recovery from the CLI | **NEW-08** | **closed 2026-07-25** | **PASS** — suite **221 → 227 tests / 1029 → 1059 assertions**; keel-verify 10 checks exit 0 incl. locale parity ×20 and INDEX parity (CLI commands 26 → **27**, total **962**); lint held 192/488 and tests 0/0. Real CLI: every refusal exits **1**. Evidence in `docs/05-test-points.md` | `owner:repair --email=<address>` writes the missing `admin_email` and runs the product's **own** `migrateFromV1Config()`; the existing password still applies. **The first design was refuted in review** — it took `--username`/`--password` and created a record `Auth::login()` could never accept, then refused to run again (**L-024**). Refusals THROW so they exit non-zero. Two tests first passed for the WRONG reason (L-012). Recovery proven through **`Auth::login()`**, the real gate. `execute()` gained `redactSecrets()` from the security pass. NEW-33 **not** closed — user decision, trigger re-bound |

## Acceptance — this sprint is done when

1. Creating a page emits **no PHP warning**, and the created page carries its post type's
   `x402_default_enabled` — both proven to FAIL against the unfixed code first (L-016).
2. A by-reference listener on any action **or filter** is refused at registration with a typed, named
   exception; a by-value listener is unaffected — proven in **both** directions (L-010).
3. A missing owner can be recreated from the CLI on an install with no session.
4. Full suite green (sprint start: **206 tests / 1007 assertions**), `keel-verify` 10 checks exit 0 with
   its output pasted, upgrade tested from the **real** v0.30.1, and all five D-025 lint baselines held
   exactly (core+admin 193/488, plugins 113/109, tests 0/0, installer/public 0/0, scripts 0/2).
5. Both review subagents ran on the **finished** diff, docs included (L-015); `docs-verifier` and a
   fresh-context playground-QA pass at the close.
6. The user's own test verdict recorded.

## Explicitly out of scope (named, so it is not mistaken for oversight)

- **`doActionRefArray()`** (the WordPress `do_action_ref_array` shape). It works — probed — and was
  rejected on the record: it creates a *second* dispatch contract, so "may this action mutate?" would
  depend on which method the emitter happened to call, invisible to a listener author. That is the
  by-omission shape S-07, NEW-02 and the tool loader were each closed to eliminate. It would also force
  enforcement from cheap registration-time to per-dispatch Reflection across 363 fire sites.
- **A `keel-verify` check for by-reference listeners.** The runtime enforcement is universal and cannot
  be bypassed; a static source check would only duplicate it and could disagree with it. Its absence is
  stated in the script header so it reads as a decision, the D-045 precedent.
- **The nine other emit-then-consume sites** (`post-type-manager`, `part-manager`, `block-manager`,
  `comment-manager`, `consent-manager`, `page-template-manager`, `user-manager`, `webhook-manager`,
  `theme-manager`) have the identical shape and **no** by-reference listener. Recorded as a pattern;
  each gets a filter when something needs one, not speculatively.
- **Raising the product's PHP floor**, **NEW-04** (build writes into the repo root), **NEW-11**
  authentication, **NEW-15**, **NEW-32**, **NEW-34**, **NEW-35**, the `.gitattributes` review
  (**NEW-27/28/H-02**, Phase 7). Each keeps its own trigger.
- **NEW-09's one-line fix remains FORBIDDEN** (D-036).

## Risks carried into this sprint

1. **A throw from CORE registration is uncontained.** `PluginLoader::loadPlugin()` wraps every plugin
   entry point in `try/catch (\Throwable)` and records a named load error (`plugin-loader.php:245-252`),
   so a third-party by-reference listener fails safe. But `x402-bootstrap.php` loads at `app.php:546`,
   **outside** that catch — so the core listener must migrate *before* the enforcement lands, or boot
   breaks. Pinned by a test asserting core registers no by-reference listener.
2. **The enforcement is a breaking change for third-party plugins** carrying a by-reference listener —
   code that has never worked, which now fails loudly instead of silently. Release note required, the
   D-034 precedent.
3. **`IntegrationTestCase::addTemporaryAction()` wraps `Hooks::addAction`**, so the throw reaches test
   listeners too. None register by reference today.
4. **Reflection cost is measured, not assumed** (L-016), even though 27 registrations makes it certain
   to be negligible.
5. **NEW-04 is live**: activating a plugin or running a build writes into the repository root. The
   page-create test must trigger neither, and must assert the root is left clean (the slice-4 precedent).
6. **CI has never run** (L-022). No test in this sprint reaches `App::getChatEngine()`, so
   `#[Group('ai-runtime')]` does not apply — stated because the rule is live, not because it bites.

## Close-out — filled 2026-07-25

| Requirement | Status |
|---|---|
| Full suite green (every test, not only this sprint's) | **PASS** — `OK (227 tests, 1059 assertions)`, 0 skips, on PHP 8.3. Sprint start: 206/1007. **Re-run clean after a contended run** — see the note below |
| `keel-verify` output pasted | **PASS** — 10 checks, exit 0, the same 2 WARNs owned by Phase 7 (H-01 five version touchpoints; NEW-27 sixteen export-ignored guides). INDEX parity green after +5 rows: classes 101→**102**, filters 117→**120**, CLI commands 26→**27**, total 957→**962** |
| Upgrade tested from the REAL previous version | **PASS** — `UPGRADE TEST PASSED (v0.30.1 -> 0.31.1-beta.1)`, run after both slices. Slice 2 changes the very migration path this test exercises |
| Lint baselines held (all five, per scope, no default value — L-016) | **PASS** — core+admin **192/488**, *improved* from 193; plugins 113/109; tests 0/0 (38 files); installer/public 0/0; scripts 0/2. The `tests` scope briefly grew to 1 during slice 2 (a stray blank line) and was fixed and re-measured |
| `code-reviewer` + `security-auditor` per slice, on a finished diff (L-015) | **DONE, 2/2 slices — and each slice got one BLOCKING finding that was correct and mine.** Slice 1: both no-blocking, but they **disagreed with each other** on the reserved-key gap; re-derived independently, the auditor was right (**L-023** territory). Slice 2: `code-reviewer` **BLOCKING** — the command minted an owner `Auth::login()` could never accept (**L-024**), which redesigned it; `security-auditor` **BLOCKING** — `execute()` persisted typed commands in plaintext where an `admin` could read them |
| `docs-verifier` over everything the sprint touched | **PASS — no blocking defects.** Re-derived every INDEX count independently (146/102/308/120/206/34/27/19 = **962**), checked all four new surfaces' examples against source, and found **no** stale claims anywhere — no surviving "301 registered actions", no 957/960/961 totals, no "117 filters", and no trace of `owner:repair`'s refuted `--username`/`--password` design. All five `terminal.owner_repair_*` keys structurally identical across 20 catalogues |
| Playground-QA fresh-context pass | **DONE — and it confirmed this sprint's central claim independently.** ~45 commands on `KPORT=8110`/`RPORT=8111`. **A page create emits zero warnings** (`grep -iE 'warning\|deprecat\|notice\|fatal'` over the server log → no match), `failOnWarning="true"` verified in `phpunit.xml`, suite green with no skips. Every product claim it checked held to the digit: the 5×4 per-role table exact, `tools/list` 206/197/56/19 exact, all nine router protections 403, the full header set present, `--reset` preserving both `.htaccess` guards. **6 document defects found, all fixed** — see below |
| Numbered try-it script handed to the user, debug log ON | **DONE** — handed with this close |
| **User's recorded verdict** | **PENDING** — awaiting the user's own walk. A reported failure reopens the sprint |
| `PROGRESS.md` / `lessons-learned.md` / `token-ledger.md` updated | **DONE** — **L-023** (two of my own measurements disagreed and the reconciliation found a third defect), **L-024** (I quoted L-014 and then tested the manager instead of the gate), **L-025** (the close-out suite and the QA pass shared one playground); token-ledger row **19**; Estimate **v4** |
| Continuation prompt produced unprompted | **DONE** — handed with this close |
| Finished docs archived to `docs/old/sprint-4/` (or "nothing qualified", stated) | **Nothing qualified — stated, not skipped.** Same reasoning as Sprint 3: `sprint-1/2/3.md` are still read as precedent (this sprint read sprint-3), `theme-package-model.md` specifies an upcoming sprint, `estimate.md` gained v4, `04-adoption-audit.md` gained NEW-36. State files, specs, plan, flows and api/reference docs never move while the project is alive |

### The suite went red without the code changing, and that was the instrument

The first close-out run reported **6 failures** on a tree that had been green minutes earlier —
`PublicCommentTest::testRateLimitHoldsAcrossSessions` and
`OembedSsrfTest::testAKnownProviderUrlIsStillAccepted` among them, neither related to anything this
sprint changed. The fresh-context playground-QA pass was still running, driving its own `php -S` and
consuming the product's persistent IP-keyed rate limiter against the same `installer/data/`.

Nothing was touched. Waiting for that process to exit and re-running returned **227 tests / 1059
assertions green**. Recorded as **L-025**: the playground is a single-tenant resource, and
parallelising the review subagents is free only because they *read* — a pass that *exercises* the
product cannot share it with the suite.

### The fresh-context pass found 6 document defects, two of them created by this sprint

1. **The CLI command count was stale** — the doc said 26, `cli.php help` prints **27**. Mine: slice 2
   added the 27th and updated `docs/api/INDEX.md` without updating the playground.
2. **`owner:repair` appeared nowhere in `docs/playground.md`** — a new recovery command a contributor
   could not discover from the document they are told to follow. Section 6 now carries it, including
   that on a seeded playground it correctly **refuses** (exit 1) and that reproducing the success path
   means deleting the owner, which the integration test does under snapshot and a human should not do
   by hand.
3. **The bearer-token cleanup reported success once while all four tokens stayed live** — observed
   once, not reproducible in two further attempts, mechanism unknown. The *documentation* defect is
   independent of the mechanism: the snippet's own `revoked N` message was the only evidence offered.
   A `curl … → 401` confirmation step is now mandatory, with the observation recorded beside it.
4. **The comment rate-limit loop cannot show the mix the text implied** — the 2-per-60s window is
   already spent by the two submissions above it, so all four requests return `429`. Now stated.
5. **`klytos_delete_page` trashes rather than removes** — the hazard note overstated it. Corrected,
   with the flip side that matters for cleanup: trashing is not cleanup, and leaving the tree clean
   needs `klytos_permanent_delete_page`.
6. **Two `--reset` snippets omitted `XDEBUG_MODE=off`** against the document's own rule, and `--reset`
   prints `user owner already exists, skipping`, which reads like a no-op and is not. Both fixed.
