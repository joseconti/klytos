# Lessons Learned — Klytos CMS

> Append-only; never trim. A session never repeats a mistake recorded here.

> **HOW TO READ THIS FILE (D-064).** Read the INDEX below in full — each line is the rule the lesson
> bought, which is the part that stops the mistake recurring — then open the body of a lesson when the
> current work is near its subject, or when it was recorded since your last session. Unlike the
> decisions file this one is short (~20 k tokens), so reading it whole is still cheap and still
> allowed; the index exists so that a session that is short of room reads the RULES rather than
> skipping the file.

> **NEW ENTRIES USE A NEW SHAPE (Keel v4.0.0, adopted 2026-07-28 — D-067).** From L-029 on, every
> entry carries, in this order: **Symptom** (what was actually observed, before anyone knew the
> cause) · **Cause** (the mechanism, not the blame) · **Fix** (what was changed) · **Check added**
> (the mechanical check that makes this class of defect fail loudly next time — a `keel-verify`
> check, a test, a doctor row — or the explicit words *"none possible, and why"*). The last field is
> the load-bearing one: **a lesson whose only defence is that someone read it is not yet defended.**
> Prose asks for discipline once per reader; a check enforces itself forever.
>
> **L-001…L-028 are NOT rewritten.** They predate the shape, they are already correct, and rewriting
> them would edit the record to look tidier than the history was. Their rules stand as written.

## Index

| # | Rule it bought |
|---|----------------|
| **L-001** | The embedded Keel copy silently rotted 20+ releases behind |
| **L-002** | Documentation described intent the code did not implement |
| **L-004** | An API was superseded in the code but in none of the skills, so two mental models shipped at once |
| **L-005** | The first boot of the playground found a production bug that reading never did |
| **L-006** | The fix for a crash nearly introduced a crash, on the same path, invisible to every test |
| **L-007** | "Dead code, delete it" would have deleted the evidence of a live bug |
| **L-008** | A test harness that lies about its own result costs more than the bug it was hiding |
| **L-009** | A fatal hid a second fatal, and a status-only assertion would have hidden both |
| **L-010** | A guard written to "fail loudly" was inert for two slices, and its own docblock argued for it |
| **L-011** | The verification tested a stranger's server for three checks, and reported it as findings |
| **L-012** | A test tier reset its hooks and its sibling did not, and it took a year of slices to notice |
| **L-013** | The reviewer said "very likely already handled"; testing found a live bypass in the control I had just shipped |
| **L-014** | The audit's recorded fix was refuted by the audit's own recorded test point, and the feature was broken at three independent layers |
| **L-015** | A review is a snapshot of the moment it READ, and the "12 blocks" I wrote were never counted |
| **L-016** | Three measurements lied in one session, and every one of them read as green |
| **L-017** | A migration returned a count of what it stamped and persisted none of it |
| **L-018** | A "does not disclose" assertion written as a word blocklist measured the wrong property |
| **L-019** | "It goes to the audit log" was true of the hook and false of the system: a seam is not a sink |
| **L-020** | A drift guard was built against an artifact it had never produced, so its one hardcoded assumption rode along untested for three sprints |
| **L-021** | The squatter was our own leftover server, and every L-011 tell agreed with it |
| **L-022** | The CI workflow has never run, so its second matrix leg had been broken for two sprints |
| **L-023** | Two of my own measurements of the same thing disagreed, and the disagreement was the finding |
| **L-024** | I quoted L-014 in the docblock and then tested the manager instead of the gate |
| **L-025** | The close-out suite and the fresh-context QA pass shared one playground, and each corrupted the other |
| **L-026** | The test harness sent a header the product never sends, so a feature that could not work in any browser had a green suite |
| **L-027** | I was hardening a page that had never served a request |
| **L-028** | The session-start freshness check fed the counter that a later test measures, and the suite failed on code that was fine |
| **L-029** | The probe measured its own missing tool and reported it as a missing world — twice in one session, and the throttle would have preserved the wrong answer for a day |
| **L-030** | The gate proved the asset file was intact and never asked whether it contained what the screens draw |
| **L-031** | The half I could not drive was the half that was broken, and "verified" had been said about the other half |
| **L-032** | Three times the stylesheet said one thing and the browser painted another, and the mechanism was different each time |
| **L-033** | The specimen proved the component layer correct in a cascade the product never has, so the fix for the last lesson shipped a new defect |
| **L-034** | The design named a state the data layer could not express, and the method that should have reported it crashed instead |
| **L-035** | The harness said "both themes" and had only ever measured one, and nothing could have told you |
| **L-036** | Build rule 1's sixth mechanism, inside the section whose comment said it could not happen there |
| **L-037** | Every accessibility pass was scoped to `#main`, so the one component on every screen was the one component nothing scanned |
| **L-038** | A blank line above a table row orphans it from the table, and the check that validates the row passes because it never sees it |
| **L-039** | A whole component was written two slices before anything rendered it, so it shipped below AA and every pass in between was honest and blind |

## L-001 — The embedded Keel copy silently rotted 20+ releases behind
- Problem: The repo carried `.claude/skills/keel/` at v1.11.0 while the installed skill was at v3.3.0, and the `CLAUDE.md` lock block was stamped v1.11.0. Any session reading the embedded copy was running an obsolete protocol, and `AGENTS.md` did not exist at all — so a fork opened in Codex/Copilot/Cursor/Gemini was bound by nothing.
- Where: Adoption step 2 / repo root
- What failed: Embedding the skill once (at v1.8.0, then v1.11.0) and treating it as done. No session before this one ran the maintenance update check against the embedded tree.
- Working solution: Full verified re-copy of the installed v3.3.0 into BOTH `.claude/skills/keel/` and `.agents/skills/keel/` (`diff -rq` clean), lock refreshed from the canonical block and re-stamped v3.3.0 in `CLAUDE.md` + newly created `AGENTS.md`, `.keel-update-check` stamp written and gitignored.
- Rule for next time: Every session runs Keel's maintenance block BEFORE any work, and compares the installed version against **every** embedded tree — not just the running copy. The two embed trees are updated together, never one alone.

## L-002 — Documentation described intent the code did not implement
- Problem: The repo ships 31 `klytos-*` skill documents, including a `klytos-accessibility` skill claiming WCAG 2.1 AA / EAA compliance and a `klytos-security-architecture` skill describing a full role/capability matrix. The shipped code matches neither: no skip links, no `prefers-reduced-motion`, 13 focus rules in ~4,900 CSS lines; and `UserManager::hasPermission()` — the documented matrix — is never called anywhere, with permission gates present on only ~30% of admin surfaces.
- Where: Adoption step 1 (inventory) / `installer/admin/`, `installer/core/user-manager.php`, `.claude/skills/klytos-*`
- What failed: Writing the guidance document as the deliverable, with no mechanical check that the code satisfies it. A skill that asserts compliance makes every future session (and every AI operating the CMS) assume the guarantee holds.
- Working solution: Every gap is recorded, with file:line evidence, in `docs/04-adoption-audit.md` and prioritized with the user — documentation claims are not treated as verification.
- Rule for next time: A skill or doc may only assert a property the code demonstrably has. When a doc states a standard is met, the same change records how it was verified (command + result); otherwise the doc states the target, explicitly labelled as not yet met.

## L-004 — An API was superseded in the code but in none of the skills, so two mental models shipped at once
- Problem: `klytos_set_part` is the canonical API for shared site chrome, and `installer/core/mcp/tools/part-tools.php:174` says so outright ("edit shared elements with `klytos_set_part` … instead of `klytos_set_global_block_data`"). The in-product guide teaches it correctly. **None of the 31 shipped `.claude/skills/klytos-*` skills mentions it**, and `klytos-custom-templates` still teaches the superseded global-blocks model. Which model an AI follows depends on whether it loaded the skill or the in-product guide — a coin flip deciding whether a site's chrome is authored through the current or the abandoned API.
- Where: Adoption follow-up / theme-model design work, 2026-07-18 — `installer/core/mcp/tools/part-tools.php`, `installer/core/guides/site-builder.md`, `.claude/skills/klytos-custom-templates/`
- What failed: Superseding an API in the code and in the in-product guide while treating the skills as a separate, later chore. The migration tool (`klytos_migrate_global_blocks_to_parts`) was even written, so the replacement was deliberate and complete on the code side — the documentation surface simply was not part of the same change.
- Second-order damage, which is the real cost: the superseded model kept its incremental propagation path (`smartRebuildBlock`) while the canonical one never got one, even though `PartManager` already emits the markers for it (audit F-01). The API the skills do not teach is also the faster one. Drift did not stay cosmetic — it produced a functional gap.
- Working solution: recorded as audit **D-06** (skills) and **F-01** (propagation), both bound to the theme-package sprint (D-023) with F-01 as a required deliverable, not an optional one.
- Rule for next time: superseding an API is not done until every surface that teaches it is updated **in the same slice** — code, in-product guides, and `.claude/skills/` (plus the `.agents/` mirror). This is the inverse failure of L-002: there the docs claimed more than the code delivered; here the code delivered more than the docs admitted. Both are the same defect — a documentation surface that does not match the code — and both are caught by the same question at every test point: *does every place that teaches this still describe what the code now does?*

## L-005 — The first boot of the playground found a production bug that reading never did
- Problem: `Hooks::doAction()` takes its arguments variadically, which copies them, so the
  by-reference listener at `core/x402-bootstrap.php:194` can never bind. Every page create in every
  production install emits `Argument #1 ($data) must be passed by reference` and silently discards
  the listener's mutation — the x402 post-type default is never applied. Recorded as audit NEW-03.
- Where: `installer/core/hooks.php:124` and `:145`; surfaced from `installer/core/page-manager.php:86`
- What failed: Not the code review — the *absence of execution*. The adoption inventory read this
  codebase thoroughly enough to produce a 930-surface API index and a 30-finding audit, and it did
  not find this, because the defect is invisible in a diff and only exists at runtime. The project
  had no way to run itself: no tests, no playground (T-01, T-02).
- Working solution: The playground (slice 0) was stood up and seeded through the application's own
  managers rather than by hand-writing storage records. Using the real API is what fired the real
  hook and exposed the defect; a seeder that wrote JSON directly would have stayed silent.
- Rule for next time: **Seed and verify through the product's own API, never around it.** A fixture
  that bypasses the application proves only that the fixture works. And treat "the playground boots"
  as a test point with real findings, not as setup to get past — its first run is the cheapest bug
  discovery the project will ever get.

## L-006 — The fix for a crash nearly introduced a crash, on the same path, invisible to every test
- Problem: Slice 3 wrapped `App::boot()` Step 10b in `try`/`catch` so a failed v1.x owner migration
  could not fatal the whole application (D-031). The first implementation logged the failure with
  `$this->logger->write(...)`. `$this->logger` is lazily constructed by `getLogger()`, whose `Logger`
  constructor requires a **non-nullable** `PluginLoader`, and `$this->pluginLoader` is not assigned
  until Step 12 — after Step 10b. So the handler would have raised a `TypeError` and crashed boot at
  exactly the point it was written to keep boot alive.
- Where: `installer/core/app.php` Step 10b; `getLogger()` and `Logger::__construct()`
- What failed: writing an error handler against the object graph as it exists at the END of boot,
  while the handler runs in the MIDDLE of it. The mental model was "the App has a logger" — true of a
  booted App, false of the one being booted. Nothing in the code signals the boundary; the property
  is simply null until later.
- Why no test would have caught it: the branch only executes on an install whose v1 config has no
  usable `admin_email`. The playground has a valid one, the upgrade test builds a valid one, and the
  unit tier does not boot an App at all. A defect reachable only from a damaged production config is
  invisible to a green suite — the failure mode would have been a user reporting a white screen.
- Working solution: `error_log()`, the only sink with no dependencies at that point in boot, with a
  comment stating why the obvious choice is wrong so it is not "improved" back later. Caught by
  reading the dependency chain of the call before running anything — `grep` for the property's
  assignment line and comparing it to the call site's line number.
- Rule for next time: **code that runs during initialization may only use services already
  initialized at that exact point — verify the assignment line is above the call line, do not infer
  it from the class having the property.** And more generally: when adding a handler for a rare
  failure path, ask what would execute it in a test. If the honest answer is "nothing", the handler
  has to be read line by line against its dependencies, because the suite cannot vouch for it.

## L-007 — "Dead code, delete it" would have deleted the evidence of a live bug
- Problem: The adoption audit recorded, next to S-07, that the `isAuthenticated()` re-checks inside
  20 of the 24 admin API endpoints were unreachable — bootstrap 302-redirects an unauthenticated
  request before any endpoint body runs. The obvious tidy-up is to delete them. One of those 20,
  `api/webauthn-challenge.php:20`, was **not** redundant: it reads
  `!$auth->isAuthenticated() && !$auth->is2faPending()`, and the second half exists to serve a user
  who has passed the password stage but not the second factor. That user was being redirected away
  by bootstrap, so `login.php:311`'s passkey fetch received HTML instead of challenge JSON.
  Passkey-as-second-factor login was simply broken (NEW-09).
- Where: `installer/admin/bootstrap.php:244-251`, `installer/admin/api/webauthn-challenge.php:20`,
  `installer/admin/login.php:311`
- What failed: classifying code by whether it *can currently execute* rather than by what it was
  written to do. "Unreachable" and "unnecessary" are different claims. Every one of the 20 checks
  looked identical under grep; only reading why each existed separated the nineteen that were
  belt-and-braces from the one that was a bug report nobody had filed.
- Working solution: instead of deleting the check, ask what would have to be true for it to run —
  and then check whether that state can actually arrive. It could not, and that was the defect. The
  endpoint was added to bootstrap's exemption list so its own check became the real gate; the other
  nineteen were left in place as defence in depth.
- Rule for next time: **before removing code as dead, state the condition that would execute it and
  verify that condition is genuinely impossible — not merely currently prevented.** Code that is
  unreachable *because something upstream is wrong* is evidence, not clutter. This is the same shape
  as L-002 and L-004: a surface that disagrees with the code around it is a finding, and deleting
  the quieter side of the disagreement destroys the finding instead of resolving it.

## L-008 — A test harness that lies about its own result costs more than the bug it was hiding
- Problem: Three separate harness defects in one slice, each of which made a PASSING gate look like a
  failure or a hang. (1) The per-role HTTP tests sent `PHPSESSID`, but `Auth::startSession()`
  renames the session to `klytos_session` (`auth.php:61`), so every request arrived anonymous — the
  401/302 assertions would have passed for entirely the wrong reason. (2) `proc_open()` was given a
  command STRING, so the handle referred to `sh -c` rather than to `php -S`; `proc_terminate()`
  killed the shell, the server kept the port, and `proc_close()` blocked forever waiting for a
  grandchild nothing would reap — the suite appeared to hang for 7 minutes with every assertion
  already green. (3) The manual 65-surface role walk iterated alphabetically with one long-lived
  session and reached `logout.php` a third of the way down, which destroyed the session and made
  every later surface report an anonymous 302 — a walk that read as a catastrophic lockout and was
  an artefact of the walk itself.
- Where: `tests/Integration/AdminGateHttpTest.php`; the scratch walk script
- What failed: in all three cases, trusting a *default* that the product had deliberately changed —
  the session cookie name, the shape of a `proc_open` handle, the assumption that requesting a page
  has no side effects. Each default was reasonable in general and wrong here.
- Working solution: (1) the real cookie name, with a comment saying why the default is wrong so it
  is not "simplified" back; (2) the ARRAY form of `proc_open` plus explicit descriptors for all
  three standard streams, so the handle IS the server and it inherits none of the runner's pipes —
  and a teardown assertion that FAILS if the server outlives the suite, because an orphan holding
  the port makes the next run look like a gate defect; (3) rewrite the session before every request.
- Rule for next time: **when a test's result is surprising, suspect the harness before the product —
  and prove which one it is rather than adjusting until it goes green.** Specifically: a security
  test that can pass while its request arrives unauthenticated is worthless, so assert the positive
  case too (a role that SHOULD reach a surface gets 200) — that is what would have caught defect (1)
  immediately, and it is why the per-role matrix asserts 200s and not only 403s.

## L-009 — A fatal hid a second fatal, and a status-only assertion would have hidden both
- Problem: `api/download-identity.php` carried **three** defects stacked on top of each other. Line
  35 called `Auth::isLoggedIn()`, which does not exist, so every request died there. Fixing only
  that revealed line 102 calling `Logger::log()`, which also does not exist (the API is
  `write()`/`writeAlways()`) — reached for the first time in the endpoint's life. Underneath both sat
  a hand-rolled owner check outside the capability matrix. The first fatal had been masking the
  second for as long as the file existed.
- Where: `installer/admin/api/download-identity.php:35`, `:102`; `installer/core/logger.php:113,181`
- What failed, twice over:
  1. **The guard that should have caught defect 2 interrogated the wrong object.**
     `if ( method_exists( $app, 'getLogger' ) )` protects a call made against
     `$app->getLogger()` — a `Logger`. `App` does have `getLogger()`, so the guard passed and the
     next line fataled. A defensive check aimed one object to the left of the risk.
  2. **The first version of the regression test could not fail.** It asserted the HTTP status
     (`assertNotSame( 500, ... )`). Verified live: this fatal returns **HTTP 200** with the error
     rendered into the response body, because output has already begun by the time it throws. The
     test passed against completely broken code, and only failed once it asserted on the BODY.
- Working solution: assert on the response body for `Uncaught Error` / `Fatal error` /
  `Call to undefined method`, not on the status. That is what surfaced defect 2 — the test found it,
  not a human reading the file.
- Rule for next time: **when a fix removes a crash, assume it has uncovered whatever the crash was
  standing in front of, and drive the whole path again before calling it fixed.** A file that has
  never successfully executed past line 35 has no evidence at all about lines 36 onward — its later
  code has literally never run in production. And when testing that "it no longer crashes", assert
  on the OUTPUT, because PHP does not reliably signal a fatal in the status code once output has
  started. Related to L-006 (a crash fix that nearly introduced a crash) and L-007 (unreachable code
  is evidence): all three come from the same root — reasoning about code paths that have never
  actually executed.

## L-010 — A guard written to "fail loudly" was inert for two slices, and its own docblock argued for it
- Problem: `PlaygroundState::assertConfigNotMutated()` (D-030) existed to fail a test that mutated
  core config under a booted App — the acknowledged hole in the isolation primitive, the part that
  refuses to pretend a file restore can refresh `App::$config`. It could not fail. It runs after
  `restorePlayground()`, and it re-read the config file *live*, so it compared the restored file
  against the snapshot it had just been restored from. Every run was snapshot-against-itself.
  Slices 3 and 4 were both signed off with this guard as part of the evidence.
- Where: `tests/PlaygroundState.php` — `assertConfigNotMutated()` vs `restorePlayground()`, and
  `tests/IntegrationTestCase.php:104-105`, which calls them in that order.
- What failed: the ordering was reasoned about carefully and the reasoning was *recorded* — D-030
  states "Restore runs before the config assertion, so the playground is left correct even when the
  assertion fails." That sentence is true and it is a good property. What nobody asked is what the
  assertion would be *reading* by then. The docblock, the decision entry and the failure message
  were all written from the intent, and the intent was sound; only the data flow was wrong, and
  nothing in the code looks wrong locally. A well-argued rationale next to a broken mechanism is
  harder to catch than a bare mistake, because it answers the question you were about to ask.
- How it was found: not by reading. By needing it — slice 5's S-12 tests hit an endpoint that writes
  config, and the question "will the guard catch that?" was settled with a probe instead of an
  opinion. The probe wrote a marker key into core config and passed green.
- Second defect the repair uncovered, in the L-009 shape: once the guard could fail, it failed **ten
  healthy tests**. The comparison was a hash of the *encrypted* file, and (a) re-encrypting identical
  content yields different ciphertext, so it could not tell "changed" from "written again"; (b)
  `ActionScheduler::setConfigValue()` writes `scheduler_last_run` on every `App::boot()`, and the
  HTTP tests boot a server per request. The first repair was correct and unusable — a permanently
  red suite trains people to ignore it, which is how a guard goes inert the second time.
- Working solution: capture the bytes BEFORE the restore overwrites them, and compare **decrypted**
  content with a documented volatile-key allowlist. Undecryptable input falls back to comparing raw
  bytes, so "I cannot read this" counts as a difference rather than as "nothing changed". Pinned
  permanently by `tests/Integration/PlaygroundGuardTest.php`, which asserts both halves — a real
  mutation trips it, a heartbeat-only change does not.
- Rule for next time: **a guard is not delivered until it has been observed FAILING on the thing it
  exists to catch — and observed passing on the benign case that most resembles it.** One direction
  is half a test. This project already applies that discipline to product fixes (slice 2's drift
  guard, slice 3's migration test, slice 4's removed gate); the lesson is that **test infrastructure
  needs it more, not less**, because a broken product fix fails visibly while a broken check just
  goes quiet and lends its credibility to everything downstream. Concretely: when writing an
  assertion that runs in teardown, state which observation it reads and at what moment that
  observation was taken — and if any cleanup runs in between, assume the evidence is gone until
  proven otherwise.

## L-011 — The verification tested a stranger's server for three checks, and reported it as findings
- Problem: the session-start freshness check ran `docs/playground.md`'s documented commands verbatim.
  Port 8080 was already held by a Docker container from an unrelated project, so `php -S` could not
  bind — and because the command had been backgrounded, that failure was invisible. Every subsequent
  `curl` reached the squatter. The check duly recorded that the admin panel answered `302` (right,
  by luck), that the MCP endpoint answered `302` where `docs/playground.md` documents `401`, and that
  it exposed 200 tools rather than 177. Two of those three read as a **regression in the slice-4
  authorization gate** — the most alarming thing the project could find at that moment — and all
  three were an unrelated Apache.
- Where: `docs/playground.md` "Start"; the session-start freshness step of Phase 5 §4.
- What failed: the documented start command has no way to fail loudly. `php -S` prints its bind error
  and exits, which is fine in the foreground and silent when backgrounded — and the document
  backgrounds it in every example that then runs `curl`. Nothing distinguishes "the playground is
  serving this" from "something else is".
- How it was caught, and the tell is worth memorising: `curl -D -` showed `Server: Apache/2.4.54
  (Debian)`. **PHP's built-in server never sends a `Server` header of that shape.** One header
  settled in seconds what would otherwise have become an investigation into a gate that was fine.
- The uncomfortable part: the test harness had already solved this. `AdminHttpTestCase` refuses to
  run when its port is taken, and its comment says why — "the whole class would silently test a
  process with a different session save path… it happened once." That guard was written in slice 4,
  for exactly this failure, and it was never carried across to the human-facing document. The
  automated path was hardened and the manual path was left as it was.
- Working solution: `docs/playground.md` gained a bind check as step 2 (`nc -z 127.0.0.1 8080 && echo
  "PORT IS TAKEN"`), plus the diagnostic note above, so the next session cannot lose the same time.
- Rule for next time: **when a guard is added to the automated harness, ask what the human-facing
  document does in the same situation** — a defect that can waste a session through the docs is a
  defect, and "the tests catch it" is not an answer when the docs are what a person follows. And
  concretely: when a result surprises you, `curl -D -` before believing it. This is L-008's rule
  ("suspect the harness before the product") applied one layer out — the harness is not only the test
  suite, it is also the environment the commands actually reached.

## L-012 — A test tier reset its hooks and its sibling did not, and it took a year of slices to notice
- Problem: `UnitTestCase` calls `Hooks::reset()` before and after every test. `IntegrationTestCase`
  called it **never**. `Hooks` is static and `App::boot()` runs once per process (D-030), so any
  listener an integration test registered stayed registered for every test that ran after it.
- Where: `tests/IntegrationTestCase.php` vs `tests/UnitTestCase.php:62,67`.
- Why it survived five slices: **no integration test had ever registered a hook.** The tier was built
  in slice 1 and used hard by slices 3, 4 and 5, and none of those tests needed a filter, so the
  missing reset had nothing to leak and looked exactly like a tier that did not need one. Slice 6 is
  the first — a test that filters `http.safe.allowed_schemes` to permit `ftp://` — and it was
  immediately leaking that permission into every test that followed it in the same process.
- How it was caught: not by reading the base class. By reading the tier's own **log output** and
  noticing that a later test refused a URL for the reason `too_many_redirects` where
  `private_or_reserved_address` was expected. The refusal was still correct; the *reason* was not,
  and the reason is exactly what these tests assert on. A test that had only asserted "was it
  refused?" would have stayed green and told nobody. **Asserting the reason, not just the outcome, is
  what surfaced this** — the same instinct that made SafeHttp return a REASON_* constant rather than
  a bool.
- Nothing was passing for the wrong reason yet, and that is the point rather than a reassurance: the
  affected assertions still held by luck. The next weakening filter registered in this tier would
  have leaked into everything downstream and looked like a green suite. That is L-010's failure mode
  arriving again, one slice after L-010 was written — which says the lesson generalises further than
  the one method it was written about.
- Working solution: the tier records the post-boot hook registry as a baseline and fails any test
  that hands it back larger, with a message naming the hook and pointing at the
  `addTemporaryFilter()` / `addTemporaryAction()` helpers that clean up by callback identity (D-042).
  A blanket `Hooks::reset()` was rejected: it would strip the App's own hooks from every test after
  the first. Proven in BOTH directions with a throwaway probe before being trusted — a leaking test
  failed with the intended message, a helper-using test passed in the same run — then the probe was
  deleted.
- Rule for next time: **when two test tiers share a static subsystem, the isolation each one performs
  is part of its contract — compare them explicitly rather than assuming the newer one inherited the
  older one's care.** More sharply: an isolation primitive covers what its author thought of, and
  D-030 thought about storage. Hooks are also global, also static, also mutable, and were simply not
  on the list. When adding isolation, enumerate every *global* the tier touches, not just the one
  that prompted the work.

## L-013 — The reviewer said "very likely already handled"; testing found a live bypass in the control I had just shipped
- Problem: `SafeHttp::isReservedAddress()` classified addresses with
  `filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE )` —
  promoted verbatim from `ImportValidator::isPrivateIp()`, the product's only working SSRF control,
  and verified against a table of a dozen addresses that all classified correctly. It does **not**
  understand IPv4-mapped IPv6. `::ffff:127.0.0.1`, `::ffff:169.254.169.254`, `::ffff:10.0.0.1` and
  `::ffff:192.168.1.1` all passed as **public**. `http://[::ffff:127.0.0.1]/` was allowed by
  `blockReason()`, and a live request confirmed it: **HTTP 200, body returned, `remote_ip
  ::ffff:127.0.0.1`.** Every private address has such a spelling, so the entire class was one
  notation away from being bypassable — in the slice written to close SSRF.
- Where: `installer/core/safe-http.php` — `isReservedAddress()`, now preceded by `normalizeAddress()`.
- How it was nearly missed, which is the whole lesson: the `security-auditor` pass raised this exact
  case and then reasoned its way past it — *"`FILTER_FLAG_NO_RES_RANGE` is documented to have
  explicit handling for IPv4-mapped IPv6, so `::ffff:127.0.0.1` is very likely already handled
  correctly."* That sentence is plausible, cites the documentation, and is wrong. It was also the
  only finding in either review marked as unverifiable, because that subagent had no shell. Had the
  claim been accepted at face value — and it was the *reassuring* half of a finding whose other half
  was being acted on — the bypass would have shipped with a note in the audit saying it had been
  considered.
- What actually found it: running the encodings. Seven candidate spellings through `blockReason()`,
  one line each. Six were refused; one printed `*** ALLOWED ***`. Then a second command confirmed it
  was not merely a classification quirk but a real fetch, against a real local server, returning a
  real body. Total cost: about two minutes.
- The adjacent finding, same root: the code reviewer noted `gethostbynamel()` reads **A records
  only** while the transport is dual-stack, so a host publishing a public A and a private AAAA
  passes. That one needs no exotic notation at all — just two DNS records. Both are the same defect:
  the classifier was reasoned about in terms of the *flags it passes* rather than the *inputs it
  will actually receive*.
- Working solution: normalize IPv4-mapped and IPv4-compatible IPv6 to dotted-quad **before**
  classifying, working on the packed bytes from `inet_pton()` so every spelling of one address
  (`::ffff:127.0.0.1` and `::ffff:7f00:1`) normalizes identically; and resolve AAAA alongside A so
  both families are checked. Both pinned by named rows in `tests/Unit/SafeHttpTest.php`, together
  with a public-IPv6 row asserting the fix did not over-correct into "refuses IPv6".
- Rule for next time: **a security predicate is not verified by reading its flags or its
  documentation — only by feeding it the encodings an attacker would actually use.** Concretely,
  before trusting any address or URL check, run it against the alternative notations as a table:
  IPv4-mapped and IPv4-compatible IPv6, octal/decimal/hex integer forms, short forms, userinfo
  disguises, trailing dots. It is minutes of work and it is the difference between a control and the
  appearance of one. And on review findings specifically: **a reviewer's reassurance is a hypothesis,
  exactly like a reviewer's accusation.** This project already knows to verify accusations before
  acting on them (slice 5 refuted one); L-013 is the mirror — the *comforting* half of a review needs
  the same treatment, and it is the half nobody thinks to check.

## L-014 — The audit's recorded fix was refuted by the audit's own recorded test point, and the feature was broken at three independent layers
- Problem: audit **S-09** said public comment submission was broken because the handler sat behind
  the admin auth guard, and recorded the remediation as "add it to bootstrap's `$preAuthScripts`".
  Applying that would have produced a green slice and a still-useless feature. Three separate
  layers were broken, only the first of which the finding named:
  1. the endpoint was unreachable anonymously (the recorded defect);
  2. `SiteConfig::setValue()` **did not exist**, although `comment-tools.php:136-148` calls it four
     times — so `klytos_set_comment_settings`, the only supported way to switch comments on, had
     fataled for its entire life, and its sibling `set()` silently drops `comments_enabled` through
     a hardcoded allow-list (NEW-16);
  3. **no comment form exists anywhere in the generated output**, and none ever did — no template,
     part or build step emits one, and `renderCommentsHtml()` returns early on zero approved
     comments, so a first commenter would have had no entry point even if one did.
- Where: `installer/admin/api/comment-submit.php` (deleted), `installer/core/site-config.php`,
  `installer/core/build-engine.php`, `installer/core/comment-manager.php:257-259`.
- **What caught the wrong remediation, and this is the transferable part: the finding's own
  acceptance criteria contradicted the finding's own suggested fix.** S-09's recorded remediation
  was "make it reachable where it is". Criterion 4 of the same slice's test point, written months
  later at sprint planning, was "**no admin-directory name appears in any frontend-reachable
  URL**". Both were in the repository, three files apart, and they cannot both be satisfied: the
  handler lived inside the randomized admin directory, so any form posting to it publishes that
  directory's name. Nobody reconciled them until the slice was planned against source.
  **When a recorded remediation and a recorded acceptance criterion disagree, the criterion is
  the requirement and the remediation is a guess** — the criterion describes the property the
  system must have, the remediation describes one person's idea of how. The disagreement is itself
  evidence that the original diagnosis stopped early.
- The D-036 question is what changed the design, not the URL: asking *what would this endpoint
  PERMIT once its only authentication check is gone* found that `admin/bootstrap.php` runs the cron
  manager and the action scheduler on every request (`bootstrap.php:184-196`). Exempting it would
  have given every anonymous passer-by a scheduler trigger — a capability nobody was proposing to
  grant, attached to a comment box. `installer/index.php` does neither, which is why relocating the
  handler out of the admin tree beat exempting it on security grounds and not merely on URL
  hygiene. D-036 exists because the webauthn diagnosis "identified the redirect and stopped"; this
  is the first slice to ask its question *before* acting, and the answer moved the file.
- A control can be inert because its INPUT can never arrive, not because its logic is wrong. The
  "per-session rate limit" read `$_SESSION['last_comment_at']`, and the session cookie is scoped
  `path=<base>/admin/` with `SameSite=Strict` (`auth.php:52-62`), so a form on the generated site
  can never send it. The comparison was correct; the value was structurally guaranteed absent.
  This is L-010's family — a guard that checks nothing — but reached by a different route: L-010's
  guard read the wrong data at the wrong moment, this one read data that could not exist. Both are
  invisible to a reader who checks the logic and not the data flow into it.
- How layer 3 was found: not by reading the endpoint, but by asking a separate agent to trace how
  the feature reaches a generated page at all. The answer — "it does not, there is no form" — is
  not visible from any file the slice touches, which is exactly why it survived an adoption audit
  that produced a 930-surface API index.
- Rule for next time: **before closing a finding, drive the whole FEATURE end to end, not the
  defect the finding names.** Ask three questions in order — can it be switched on? can it be
  reached? is there anything that calls it? — and answer each against the source, not the finding
  text. A finding is a report about one symptom, written by someone who was looking at one file;
  closing it is not the same as making the feature work, and a slice that conflates the two ships
  a green test point over a dead feature. Where the whole feature is deliberately not finished in
  this slice (here: the form), say so in the reference doc in plain words — claiming otherwise is
  the L-002 defect.

## L-015 — A review is a snapshot of the moment it READ, and the "12 blocks" I wrote were never counted
- Problem: two things went wrong in slice 8's review cycle, and they point the same way.
  1. The `code-reviewer` returned exactly one **BLOCKING** finding — that `docs/PROGRESS.md` still
     said "slices 0–7 closed, next is slice 8" while `decisions.md`, `api/INDEX.md`,
     `reference/security-headers.md` and `05-test-points.md` all recorded the slice as done. The
     finding was **false at the time it was reported and true at the time it was read**: the
     reviewers were launched as soon as the *code* was stable, and the docs were written while they
     ran. `PROGRESS.md` had been updated minutes before the agent finished. The second reviewer,
     running in the same window, read the file after the edit and said nothing about it.
  2. In the same slice I wrote, in two separate new documents, that "the 12 `<style>` blocks now
     carry nonce attributes". There are **10** in `installer/admin/`. The audit's S-10 entry says
     12, and I carried that number across without counting, into a decision entry whose neighbouring
     paragraphs argue for verifying claims against source. Two of the audit's twelve are `<style>`
     occurrences embedded inside `srcdoc` attributes (`blocks.php:82`, `block-data.php:172`) that
     build an iframe document as an escaped string and **cannot carry a nonce at all**.
- Where: the slice-8 review cycle; `docs/decisions.md` D-044 and `docs/reference/security-headers.md`
  as first written; `docs/04-adoption-audit.md` S-10 as the inherited source of the wrong number.
- What failed, and it is one root not two: **treating a number or a report as evidence because of
  where it came from rather than because it was checked.** The reviewer's blocking finding carried
  the authority of an independent pass; the "12" carried the authority of the project's own audit.
  Neither had been re-derived. L-013 established that a reviewer's *reassurance* is a hypothesis
  like its accusation; this adds that **an accusation can also be stale rather than wrong**, and
  that the project's own earlier documents are a source to verify, not a source to quote.
- Why the stale finding was harmless and would not always be: verifying it cost one `grep`, and the
  answer was "already done". Had I acted on it without looking, I would have rewritten a correct
  file — noise, not damage. But the same mechanism applied to a *code* finding ("this check is
  missing") would have meant re-adding something already present, or worse, "fixing" a file the
  reviewer saw in a half-written state. The mechanism scales badly even though this instance did not.
- Working solution: every finding from both reviewers was re-derived against source before anything
  was changed — which is how the blocking one was refuted, and how the real ones (unescaped nonce
  echoes at `updates.php:379,554`; a surviving `isHttps()` duplicate at `bootstrap.php:195`; the two
  public entry points sending no headers at all) were confirmed and acted on. The count was settled
  with one command that excluded `srcdoc` matches, and corrected in all three documents.
- Rule for next time: **launch the review subagents on a diff that is FINISHED, docs included — or
  state in the prompt which surfaces are still in flight.** A reviewer cannot tell "not yet written"
  from "forgotten", and it will always report the latter. And separately: **a number copied from
  another document is not a measurement.** When writing a count into a decision or a reference doc,
  run the command that produces it — especially when the number came from this project's own audit,
  because that is exactly the source trusted hardest and checked least. This is L-002's shape
  turned inward: a document asserting a property the code does not have, written by the person who
  had just finished warning about it.

## L-016 — Three measurements lied in one session, and every one of them read as green
- Problem: slice 9's whole subject is replacing "checked by eye" with "checked by a machine". In the
  session that built it, **three separate measurements produced a false PASS**, and none would have
  been caught by reading the code that produced them.
  1. **A shell fallback invented a clean baseline.** The lint loop used
     `${t:-0 ERRORS AND 0 WARNINGS}` so that a scope with no matching `grep` line printed
     "0 ERRORS AND 0 WARNINGS". `installer/core` + `installer/admin` printed exactly that — a
     *false clean* on the project's largest baseline, which actually stands at 193/488. The default
     was written to make the table tidy and it made the table wrong.
  2. **A guard test passed against the guard not firing.** The first SAPI guard for
     `scripts/dev/router.php` checked `PHP_SAPI !== 'cli-server'`. Under `php -S` a file served
     *as an ordinary file* also reports `cli-server`, so the guard did not fire — but the probe
     reported `HTTP 404` and looked green, because the **unguarded** file answers 404 for that path
     too and its first line is byte-identical to the guard's. Only the body SIZE separated them:
     468 bytes is the disclosure page, 19 bytes is the guard.
  3. **An archive test measured HEAD instead of the working tree.** The probe built its fixture with
     `git archive $(git write-tree)`, which reads the index — and the edits were unstaged. So the
     "fixed" files under test were the unmodified ones, and `upgrade-assert.php` correctly reported
     200 while `router.php` reported a 404 that belonged to the old code.
- Where: the slice-9 session — the lint measurement loop, `scripts/dev/router.php`, and the NEW-28
  archive probe.
- What failed, and it is one root: **each measurement had a path that produced "fine" without ever
  observing the thing it claimed to observe.** A defaulting fallback, a status code two different
  code paths both emit, and a fixture built from the wrong tree. In all three the *instrument* was
  broken, not the subject — L-008's rule ("suspect the harness before the product") arriving for the
  fourth time, now inside the very slice built to stop exactly this.
- How each was caught: by re-deriving the number a second way rather than by inspection. The lint
  figure was re-run per scope with no fallback; the guard was re-measured by body size and then the
  discriminator was **probed** (`SCRIPT_NAME` is the requested path when acting as router, and the
  script's own path when served as a file); the archive fixture was replaced with an explicit `cp`
  of the working-tree files, after confirming with `grep -c PHP_SAPI` that the copies were the
  edited ones.
- The uncomfortable part: probe (2) is the same shape as the bug it was testing for. NEW-28 exists
  because a file answered a request it should have refused; the probe passed because a file answered
  404 for a reason unrelated to the fix. Both are "the right answer for the wrong reason", and only
  measuring a *second property* — the body size — could tell them apart.
- Rule for next time: **a measurement needs the same prove-it-fails discipline as a check.** Before
  trusting a number, ask what it would print if the thing being measured did not exist — and if the
  honest answer is "the same thing", measure something else as well. Concretely: never give a
  measurement a default value (an absent result must be visibly absent, never a plausible zero);
  when asserting that a guard fires, assert on a property only the guard produces, not on a status
  code the unguarded path also emits; and when building a fixture from git, verify the fixture
  contains the change under test before drawing any conclusion from it. This generalises L-010 from
  guards to the instruments that measure them: **a broken check goes quiet, and a broken measurement
  goes green.**

## L-017 — A migration returned a count of what it stamped and persisted none of it
- Problem: `Auth::migrateCredentialRoles()` (Sprint 2, slice 1) iterated `$tokensData['tokens'] ?? [] as
  &$stored`, set `$stored['role'] = 'owner'`, counted the stamp, and wrote `$tokensData`. It returned 1
  and wrote a file in which the role was still absent — every bearer token it "migrated" stayed
  role-less on disk. A role-less bearer resolves to a null role, i.e. DENY, so the unfixed migration
  would have locked out every pre-Sprint-2 bearer token rather than preserving it.
- Where: `installer/core/auth.php::migrateCredentialRoles()`.
- What failed: iterating the result of the null-coalescing operator BY REFERENCE. `$x['k'] ?? []` is an
  expression, not a variable, so it evaluates to a temporary; `foreach ( … as &$ref )` binds `$ref` to
  elements of that temporary and the writes never reach `$x['k']`. The `?? []` was there to be safe
  against a missing key — and it silently disabled the write-back it was wrapped around. The identical
  pattern sits in the pre-existing `validateAppPassword()` (recorded as **NEW-29**), where it drops the
  `last_used` update — proof the footgun is real in this codebase, not hypothetical.
- How it was caught, which is the whole point: the test asserted the **persisted role after migration**
  (`getBearerTokenActor()` returns `'owner'`), not the method's **return value**. Against the buggy code
  the return value was a truthful-looking `1`, and only the persisted state was wrong. A test that had
  asserted `assertSame( 1, migrateCredentialRoles() )` alone would have passed against a migration that
  did nothing — L-016's shape (a measurement that goes green) arriving one sprint later, in a migration.
- Working solution: guard the key and iterate the real array — `if ( ! isset( $t['tokens'] ) || ! is_array( $t['tokens'] ) ) { return 0; }`
  then `foreach ( $t['tokens'] as &$stored )`. A comment states why `?? []` cannot be used here so it is
  not "simplified" back.
- Rule for next time: **never iterate `$x['k'] ?? [] as &$ref` — the reference binds to a temporary and
  the writes vanish.** Assign to a variable first (`$list = $x['k'] ?? []`) or guard the key. And the
  L-016 rule applied to migrations: assert the PERSISTED effect, never the return value — a migration
  that reports how much it changed is not evidence that anything changed.

## L-018 — A "does not disclose" assertion written as a word blocklist measured the wrong property
- Problem: slice 4 translated the client-facing MCP refusal into 20 locales, and the property worth
  pinning is the one D-046 recorded: the message names the tool, never the caller's role or the
  required capability (the full reason goes to the audit log through `mcp.access_denied`). The first
  version of that test asserted the message contains none of `owner`, `admin`, `editor`, `viewer`,
  `pages.delete`, `capability`. It failed immediately — on **English**, on the word *owner*, because
  the message deliberately says "ask the site **owner** to grant this connection the permission it
  requires". That clause is the half of the message that names the FIX, which is exactly what the
  shape was designed to do.
- Where: `tests/Unit/McpRefusalI18nTest.php`, first version, this session.
- What failed: the test asserted on **words that could appear for a legitimate reason** instead of on
  the thing that constitutes the disclosure. "owner" in "the site owner" is a person to contact;
  "owner" in "role 'owner' lacks…" is the internal reason. A blocklist cannot tell them apart, and
  the failure it produced was a **false accusation against correct content** — the noisy twin of
  L-016's false pass. Worse, it would have gone on being wrong in the other direction later: a
  translator writing "administrador del sitio" would have tripped it, and someone would have
  "fixed" the translation to satisfy the test.
- Working solution: assert on the **identifier shape** that is the disclosure — no
  `[a-z]+\.[a-z_]+` capability token anywhere in the message, plus no use of the internal word
  *capability* — and state in the docblock why "site owner" is not a leak, so the narrowing does not
  read as a weakened assertion.
- Rule for next time: **when pinning a negative security property, assert on what the leak would
  literally look like, not on words associated with it.** Ask "could this string appear in a correct
  message?" before adding it to a blocklist; if the answer is yes, the blocklist is measuring
  something other than the property. This is L-016's family reached from the opposite side — that
  lesson was about measurements that go green without observing anything, this is about one that
  goes red without observing anything — and both have the same root: the instrument was never
  checked against the thing it claims to detect.

## L-019 — "It goes to the audit log" was true of the hook and false of the system: a seam is not a sink
- Problem: two of this project's documents — `installer/core/mcp/server.php`'s refusal comment and
  `docs/reference/mcp-authorization.md` — said the full reason for an MCP permission denial "went to
  the audit log via `mcp.access_denied`". The action does fire, with the role, the capability and the
  tool. **Nothing subscribes to it.** Neither `mcp.access_denied` nor its admin twin
  `auth.access_denied` has a single listener anywhere in core, so on a default install every 403 and
  every MCP refusal writes exactly nothing to `installer/data/logs-*/`, Developer Mode on or off.
- Where: `installer/core/mcp/tool-registry.php:275`, `installer/core/helpers-global.php:507`; the
  claim, in `server.php` and `reference/mcp-authorization.md`. Recorded as audit **NEW-32**.
- What failed: the sentence described the **mechanism** (a hook exists, it carries the reason) and
  was read — including by me, writing it — as describing the **outcome** (an operator can go and read
  why a call was refused). Both halves of the sprint's own story leaned on it: the client-facing
  message is deliberately vague *because* "the full reason went to the audit log", which is the
  justification for not telling the caller. Take the log away and that trade is one-sided — nobody
  learns the reason, not the caller and not the operator.
- How it was caught, and this is the part worth keeping: **not by a reviewer reading the code, but by
  a fresh-context checker following the documentation and finding nothing at the end of it.** Two
  review subagents had read this exact code path across three slices without noticing, because the
  hook is right there and reads like plumbing that works. The playground-QA pass was handed "the
  debug log is ON, paste it when something fails", walked a session full of refusals, and came back
  with an empty log — reporting the document as defective. The document was defective, and so was
  the belief behind it.
- Working solution: the wording is corrected everywhere to say the reason goes to the
  `mcp.access_denied` **action** — the audit *seam* — with the plain statement that no core listener
  subscribes and a one-line snippet for operators who want it logged. The gap itself is recorded as
  NEW-32 with a trigger rather than fixed at sprint close, because "log every refusal by default"
  carries real volume and content decisions that belong to a slice, not to a close-out.
- Rule for next time: **when a document claims something is logged, recorded, audited, notified or
  persisted, follow it to the code that WRITES it — a hook, an event, a callback or an interface is a
  seam, and a seam with no subscriber produces nothing.** Grep for the listener, not for the emitter.
  This is L-002 ("do not assert what the code does not do") narrowed to the specific shape that keeps
  slipping past code review: an extension point mistaken for an implementation. And a second, cheaper
  rule from the same finding: **the fresh-context pass is not a formality.** It is the only check in
  this project's process that reads the docs as promises rather than as description, which is exactly
  what a user does.

## L-020 — A drift guard was built against an artifact it had never produced, so its one hardcoded assumption rode along untested for three sprints
- Problem: `tests/Unit/VendorAiManifestTest.php` (D-028) cross-checks four records — the manifest, the
  lock, Composer's generated `vendor-ai/composer/installed.php`, and the licence notice — using
  `installed.php` as the anchor. `vendoredVersions()` skipped the root package by the **literal string**
  `'__root__'`. That is only what Composer writes for a root package it cannot name. This manifest is
  named (`klytos/vendor-ai-manifest`), so the moment Sprint 3 ran the first real `composer update` this
  repository has ever performed, Composer wrote the root under its **real name**, the entry no longer
  matched the hardcoded skip, and the root leaked into all three comparisons.
- Where: `tests/Unit/VendorAiManifestTest.php::vendoredVersions()`;
  `installer/vendor-ai/composer/installed.php`.
- Why it survived three sprints undetected, which is the whole lesson: **the guard had never once seen a
  tree that Composer itself generated.** D-028 *reconstructed* a manifest to describe a tree that had
  been vendored somewhere else, and slice 2's own test point asserted the opposite of exercising it —
  "`vendor-ai/` unmutated". So every green run since was a run against the single input the assumption
  happened to fit. The guard was not weak; it was **unexercised on the only event it exists for**. A
  drift guard that has never watched the thing drift is in the same position as a check that has never
  fired (L-010) — indistinguishable from one that cannot.
- A second defect surfaced in the same moment, and it is the same root: left to itself Composer wrote
  `'pretty_version' => 'dev-develop'` and `'reference' => '<commit sha>'` into that **tracked** generated
  file — making a committed artifact a function of the branch name and the commit it was regenerated at,
  in direct contradiction of the manifest's own recorded purpose ("reproduced byte-for-byte", D-028).
  Nobody had seen it because nobody had ever regenerated the file. Fixed by pinning `"version": "1.0.0"`
  in `installer/composer.json`, which restores `reference => null`.
- Working solution: read the root's identity from the data instead of assuming its spelling —
  `$installed['root']['name'] ?? '__root__'` — which is what the method's own docblock always said it
  did. Explicitly **not** a loosening: both set comparisons remain `assertSame` in both directions, and
  a vendored package can never collide with the root name because Composer refuses to install a package
  named after its own root.
- The consolation, and it is worth stating because it is why this cost minutes rather than a session:
  the failure was **loud and immediate**. All three methods failed with a readable diff naming the
  intruding key. A guard that breaks noisily when its assumption expires is behaving correctly; the
  defect is that the assumption was never put under load, not that it broke.
- Rule for next time: **when a guard checks a generated artifact, generate the artifact at least once
  before trusting the guard** — and if the project has never run the generator, say so in the guard's
  docblock rather than letting the reader assume it has. More sharply, and generalising L-010 one step:
  a check must be exercised against the *event* it guards, not merely against the *state* it happens to
  find. "It has been green for three sprints" is evidence about the inputs it saw, not about the check.

## L-021 — The squatter was our own leftover server, and every L-011 tell agreed with it
- Problem: the slice-2 playground walk started a `php -S` on `$RPORT=8093`, ran a four-role check
  against `admin/ai-chat.php`, and got a perfect result — 200/200/200/403, exactly what D-051
  predicts. The documented bind check (`nc -z`, added by L-011) had printed **`PORT 8093 IS TAKEN`**
  one line earlier. The server that answered was **not the one this session started**: ours failed to
  bind and, because it was backgrounded, said so only in its log file. The responses came from a
  `php -S` left running by the *previous* session of this same project.
- Where: `docs/playground.md` "Try it" (`$RPORT`); the slice-2 test point.
- Why L-011's diagnostic does not cover this case, which is the whole point: L-011 taught the tell
  `Server: Apache/2.4.54 (Debian)` — PHP's built-in server never sends a `Server:` header of that
  shape, so a foreign squatter is obvious in one `curl -D -`. A **leftover Klytos server** is
  byte-identical on every such signal: same `X-Powered-By: PHP/8.3.12`, same `X-Content-Type-Options:
  nosniff`, same session save path, same router, same working tree. Every check L-011 prescribes
  **passes**, and passes honestly. The tell was written for the wrong half of the problem space.
- Why the result was nevertheless correct, stated so the record is accurate rather than dramatic:
  PHP's built-in server re-reads its PHP files on every request and the leftover was started with
  `-t .` on this same checkout, so it was serving the current code. The walk was right. It was right
  **by luck**, and it was indistinguishable in advance from a walk against a week-old tree — which is
  what a leftover server usually is, and which would have produced a confidently wrong test point.
- How it was caught: by reading the `nc` output that had already fired and not reading past it, then
  `lsof -nP -iTCP:8093 -sTCP:LISTEN` for the owning PID and `ps -o lstart,command` for its start time
  and argv. That pair — not the response headers — is what separates "my server" from "a server".
- Working solution: kill the leftover, restart, and **verify the bind succeeded before trusting a
  single response** — the backgrounded `php -S` writes `Failed to listen on 127.0.0.1:8093 (reason:
  Address already in use)` to its redirect target and nothing else ever mentions it. Then re-run the
  walk and confirm the result reproduces. It did, identically.
- Rule for next time: **a busy port is not automatically a stranger — check whether it is your own
  process from a previous session, because if it is, every identity check will agree with you.**
  Concretely: when the bind check says TAKEN, run `lsof -nP -iTCP:<port> -sTCP:LISTEN` and
  `ps -o lstart,command -p <pid>` before doing anything else, and when a `php -S` is backgrounded,
  grep its log for `Failed to listen` rather than assuming silence means success. And the general
  form, which is L-008's rule one turn further out: **a correct-looking result from an unverified
  instrument is not evidence, even when it is correct.**

## L-022 — The CI workflow has never run, so its second matrix leg had been broken for two sprints
- Problem: `.github/workflows/ci.yml` runs the full suite on **PHP 8.2 and 8.3**, because Klytos
  declares 8.1+ and the 8.2 leg is how the declared floor gets any real coverage (D-045/D-027). But
  `installer/vendor-ai/` needs **8.3**, and every test that reaches `App::getChatEngine()` therefore
  cannot pass on the 8.2 leg. Sprint 3 slice 2 added two such tests. It also emerged that **three more
  had existed since Sprint 2 slice 3** (`ChatEngineToolListTest`) and three since Sprint 3 slice 1
  (`VendorAiCompatibilityTest`) — **8 tests in 3 classes**, measured by temporarily raising the floor
  so this host counted as unsupported.
- Where: `.github/workflows/ci.yml` (the `test` job's matrix); `tests/Integration/` — three classes.
- **Why nobody noticed, and this is the whole lesson: CI has never executed. Not once.** The workflow
  was written in Sprint 1 slice 9 (2026-07-20) and every commit since — 29 at the time of writing — is
  **unpushed**, by a standing instruction. So the project has carried a green-looking CI configuration
  for two sprints while it has never run a single job. Its 8.2 leg would have gone red on the first
  push, on code that had already been signed off through three test points.
- The shape is **L-019 one level out**: there, a hook that fires with no subscriber writes nothing, so
  "it goes to the audit log" was true of the mechanism and false of the system. Here a workflow that
  is never triggered checks nothing, so "CI runs the same commands the test points run" is true of the
  file and false of the project. **A seam with no subscriber, and a workflow with no run, fail the
  same way: the mechanism is real and the outcome does not exist.**
- The near-miss inside the near-miss: slice 2's own subject is *"PHP below 8.3 must degrade
  gracefully"*, and the tests it wrote to prove that were the ones that would have broken the build on
  PHP 8.2. Found by the slice's `code-reviewer`, not by the suite — because the suite runs on one
  runtime, on one machine, and that machine is 8.3.
- Working solution, in two layers because they answer different questions: the 8 tests carry a
  `#[Group('ai-runtime')]` and CI's 8.2 leg **excludes** that group — chosen over letting them skip,
  because CI promotes any skip to a hard failure (D-045) and that rule must keep meaning "the
  playground did not seed". And `IntegrationTestCase::requireAiRuntime()` skips them anyway for a
  developer running the whole suite locally on 8.1/8.2. Proven by re-running with the floor raised:
  8 errors/failures became **8 skips**, and the group selects exactly those 8. The group is applied
  **per method** in one class, so the one test that asserts only the refusal *message* keeps running on
  8.2 — the runtime where that message is what a real operator sees.
- Rule for next time: **a CI configuration that has never run is a promise, not a check — treat its
  first execution as an unverified test point, not a formality.** Concretely: when adding a matrix leg,
  ask what in the suite is runtime-dependent and prove the answer by *simulating* that leg (here:
  moving the floor rather than the runtime), because the machine you develop on will only ever tell
  you about itself. And when a workflow cannot be exercised at all — an unpushed repo, a disabled
  action — say so where its status is reported, rather than letting a committed YAML file read as
  coverage.

## L-023 — Two of my own measurements of the same thing disagreed, and the disagreement was the finding
- Problem: Sprint 4 slice 1 needed the size of the hook surface, because a recorded decision (D-026)
  had deferred the work twice on the strength of a number. I measured it twice and got two answers:
  **35 vs 27** listener registrations, **115 vs 118** distinct filter names. Neither pass was
  obviously wrong. I had already written the second set into `docs/decisions.md`, `docs/PROGRESS.md`,
  `docs/sprints/sprint-4.md` and the audit before noticing they disagreed with the first.
- Where: the Sprint 4 slice 1 re-validation; `docs/decisions.md` D-054; `docs/api/INDEX.md`.
- What failed: not the counting — the **scoping and the method**, differently wrong in each pass.
  One included `tests/`, where this slice's own new file had just added nine registrations. The other
  counted the helper's internal delegation line as a registration. And **both were structurally blind
  to multi-line calls**: a line-based `grep` cannot see
  `klytos_apply_filters(\n  'name',\n  $value\n)`, and this codebase has four of them.
- What the reconciliation found, which neither pass reported: a third measurement — strip comments,
  strip the helper definitions, match across newlines — settled the counts AND surfaced
  **`x402.should_protect` (`x402/gate.php:70`) fired in code with no `docs/api/INDEX.md` row**. A
  documentation gap dating to adoption, whose extraction had the same line-based blindness. Nobody
  was looking for it; it fell out of resolving a disagreement about something else.
- The uncomfortable part: `keel-verify` was **green** throughout. Its INDEX check verifies that the
  summary counts match the rows — an internal consistency check, which a file can pass while being
  incomplete in exactly the same way its generator was. A verifier inherits its generator's blind
  spots when it was built from the same extraction technique.
- Working solution: three passes, the last one method-aware rather than line-aware, with the scope
  of each stated (shipped code vs tests; call sites vs distinct names). Every figure in D-054 now
  names what it counts, because "27 registrations" and "23 shipped registrations" were both true and
  answered different questions.
- Rule for next time: **when two measurements of the same property disagree, do not pick the more
  plausible one — the disagreement is itself evidence that at least one method is wrong, and finding
  out which routinely surfaces a third defect.** Concretely: a line-based `grep` over a language with
  multi-line call syntax is not a measurement of call sites, it is a measurement of call sites that
  happen to fit on one line; state the scope of every count you write down (does it include tests?
  definitions? delegations?); and never write a number into a document before you have derived it a
  second way. This is L-015 one turn further out — that lesson was "a number copied from another
  document is not a measurement", this one is "a number measured **once** is not a measurement
  either."

## L-024 — I quoted L-014 in the docblock and then tested the manager instead of the gate
- Problem: Sprint 4 slice 2 built `owner:repair` to close NEW-08 — an install can lose its owner
  record and there was no way to put one back. The command took `--username`, `--email` and
  `--password` and called `UserManager::create()`. It was wrong end to end: **`Auth::login()`**, the
  admin panel's actual gate, validates the username against `config['admin_user']` and the password
  against **`config['admin_pass_hash']`** — never against the user record (that is NEW-11). So the
  supplied password could not log anyone in, and once `findOwner()` returned non-null the command
  **refused to run again**, leaving the install permanently unrecoverable through the product.
- Where: `installer/core/terminal-executor.php` (`owner:repair`, first version);
  `tests/Integration/OwnerRepairTest.php::testTheRecreatedOwnerCanActuallyAuthenticate`;
  `installer/core/auth.php:99-102`.
- What failed, and it is uncomfortable: **the test written specifically to prevent this tested the
  wrong layer.** Its docblock says, verbatim, *"Recreating a RECORD is not the same as restoring
  ACCESS — L-014's rule applied to this slice's own subject"* — and then it asserted
  `UserManager::authenticate()`, the manager, rather than `Auth::login()`, the gate. It passed
  against a command that restored nothing. Quoting the lesson is not applying it.
- The second surface that should have caught it: **the reference doc contained its own disproof.**
  The headline said "Then log in to the admin panel with the username and password you passed", and
  three paragraphs later the "What it does not do" section said `Auth::login()` validates only against
  `config['admin_user']`. Both sentences were written in the same session, by me, and neither was read
  against the other.
- How it was caught: the slice's own `code-reviewer`, tracing the call chain from the command to the
  login path rather than reading either document. Not by the suite — the suite agreed with the defect.
- The fix that followed from asking what actually breaks: `upgrade-assert.php:131` removes **only**
  `admin_email`; `admin_user` and `admin_pass_hash` survive. So the missing piece was never the
  identity — it was the email the migration needed. The command now supplies that and runs the
  product's existing `migrateFromV1Config()`, which restores the record from credentials that already
  work. One code path creates an owner from config, not two.
- Rule for next time: **when a fix claims to restore ACCESS, the test must go through the exact
  function the product uses to grant it — name that function before writing the test, and check that
  the assertion calls it.** A manager method with a similar name is not the gate. Concretely: for
  anything touching authentication, grep for what the login form actually calls and assert against
  that; and when a document has a "what it does not do" section, read it against its own headline
  before shipping — a doc that contains its own refutation is a defect that has already been written
  down, just not noticed. This is L-014 one turn inward: that lesson said drive the FEATURE, not the
  defect; this one says the feature is defined by the code path the USER traverses, not the one the
  fix happens to touch.

## L-025 — The close-out suite and the fresh-context QA pass shared one playground, and each corrupted the other
- Problem: at the Sprint 4 close, `vendor/bin/phpunit` reported **6 failures** on a tree whose suite
  had been green minutes earlier, on identical code. The failures included
  `PublicCommentTest::testRateLimitHoldsAcrossSessions` and
  `OembedSsrfTest::testAKnownProviderUrlIsStillAccepted` — neither related to anything the sprint
  changed.
- Where: the Sprint 4 close-out, run while the fresh-context playground-QA pass was still executing
  against the same checkout, the same `installer/data/`, and its own `php -S` on another port.
- What failed: **two verification passes were run concurrently over one shared environment.** The QA
  agent was following `docs/playground.md` for real — starting a server, submitting comments, walking
  flows — which consumed the product's own persistent, IP-keyed `MCP\RateLimiter` and occupied ports.
  The suite then measured an application whose shared state a second process was mutating underneath
  it. Neither pass was wrong; the environment was not exclusive to either.
- Why L-021 does not cover it: that lesson is about a **leftover** server from a previous session, and
  its remedy is `lsof` + `ps` to establish ownership before trusting a response. Here the other process
  was **ours, live, and deliberately started** — ownership was never in doubt. The interference was not
  through the port at all for the rate-limit failure; it was through **shared application state on
  disk**, which no port check can see.
- How it was caught, and the part worth keeping: by refusing to act on the failures. Two tests
  unrelated to the sprint's subject, failing on code that passed twenty minutes earlier, with exactly
  one new variable in the environment — that is a hypothesis about the instrument, not about the
  product. Re-running after the other process exited returned **227 tests / 1059 assertions green**,
  with no test touched. Had the failures been "fixed", the fix would have been permanent and the
  defect imaginary.
- Working solution: wait for the concurrent pass to exit (watch for its server to disappear), then
  re-run. Sequential, not parallel, for anything that shares the playground.
- Rule for next time: **the playground is a single-tenant resource — never run the close-out suite and
  the fresh-context QA pass at the same time.**
- **SECOND OCCURRENCE, 2026-07-27, Sprint 6 close — and I caused it myself, in the same session that
  recorded L-028 about this very resource.** I launched the fresh-context playground-QA agent in the
  background, then ran the close-out suite while it was still walking `docs/playground.md`. Five
  `PublicCommentTest` failures: three `429 != 201`, one empty string, one `0 > 0`. Identical shape to
  the first occurrence — the QA pass was submitting comments, which consumes the persistent IP-keyed
  bucket the test measures. `lsof` showed two live `php83` servers on 8117 and 8118 that were not
  mine to be running alongside a suite. **No assertion was touched**; the tell was the same one this
  lesson names: failures confined to one class, unrelated to the diff (which was documentation only),
  on code that was green minutes earlier. What this occurrence adds: the danger is no longer only a
  human running two things — **a background agent counts as the second tenant**, and launching one is
  as much a commitment of the playground as starting a server by hand. The close-out sequence is
  therefore ordered, not parallel: QA pass → wait for it to exit → reseed (L-028) → suite. Parallelising the review subagents is free because they
  only READ; parallelising anything that EXERCISES the product is not. And the general form, which is
  L-008's rule with a new cause: when results change without the code changing, enumerate what else
  changed in the environment before touching a single assertion — a green suite that was green an hour
  ago does not spontaneously develop six unrelated defects.

## L-026 — The test harness sent a header the product never sends, so a feature that could not work in any browser had a green suite
- Problem: Sprint 5 slice 2 closed NEW-09 — passkey second-factor login — with five tests, a real
  ES256 signature, a real CBOR/COSE fixture, and the credential enrolled through the product's own
  `completePasskeyRegistration()`. All of it passed. **None of it could happen in a browser.**
  `login.php` and `security.php` call the endpoint with `Content-Type: application/json` and put the
  CSRF token inside the JSON body; `Helpers::verifyCsrf()` reads `$_POST['csrf']`, the
  `X-CSRF-Token` header and `$_GET['csrf']`; and PHP does not populate `$_POST` for a JSON body. So
  the real request was answered **403 `Invalid CSRF token`** — passkey enrolment and passkey login
  both unreachable, on top of the two defects NEW-09 already named.
- Where: `tests/AdminHttpTestCase.php::postJson()` versus `installer/admin/login.php`'s and
  `installer/admin/security.php`'s `fetch()` calls; `installer/core/helpers.php::verifyCsrf()`;
  `installer/admin/api/webauthn-challenge.php`.
- What failed, and it is worse than a missed case: **`postJson()` adds an `X-CSRF-Token` header that
  no shipped page sends** — and its own docblock explains exactly why, in a sentence that is a
  correct diagnosis of the product's bug: *"klytos_verify_csrf() reads the request superglobals and
  the header, not the JSON body."* The harness had understood the problem and worked around it,
  silently, for every JSON test in the project. So the suite was not merely failing to observe the
  defect — **it was repairing it on the way in.**
- The tell had been sitting in the endpoint the whole time: `$csrf = $input['csrf'] ?? '';` was read
  into a variable and never used. Dead code marking the exact spot.
- How it was caught, which is the transferable part: **not by the suite, and not by reading — by a
  reviewer asking whether the test client resembles the real one.** The `security-auditor` traced the
  shipped page's `fetch()` options against the CSRF helper's sources and reported that the two could
  never meet. It was then proven in the main session by building the request by hand, with exactly
  the page's headers, and watching it return 403.
- This is **L-016 one turn further out**. That lesson was about measurements that go green without
  observing anything — a defaulting fallback, a status code two paths both emit, a fixture built from
  the wrong tree. This is a harness that observes correctly and *fixes the subject first*. Both are
  instruments lying; only this one improves the product as it measures it, which is why it survives
  code review, a green suite, and a proven-to-fail-first cycle. Every one of those was satisfied here.
- Working solution: the endpoint now accepts the token from **either** channel (using the variable it
  already read), and all three `fetch()` calls send the header too — so neither side can silently
  break the other again. Pinned by `testTheEndpointAcceptsTheTokenTheShippedPageActuallySends`, which
  builds its request by hand *without* the convenience header, is the only test in that class that
  reproduces the shipped page byte for byte, and was observed failing first.
- Rule for next time: **when a test drives an HTTP surface, the request it sends must be the request
  the product's own client sends — compare them field by field, and treat any convenience the harness
  adds (a header, a default, a normalisation) as a potential repair of the thing under test.** Any
  helper that makes requests "just work" is a suspect. Concretely: for every endpoint called from
  JavaScript, read the `fetch()`/XHR options in the shipped page and assert that the test builds the
  same thing — or, better, have one test per endpoint that constructs the request by hand. And when a
  test helper's docblock explains *why* something has to be done a particular way, ask whether that
  explanation is describing a workaround for a defect nobody filed.

## L-027 — I was hardening a page that had never served a request
- Problem: Sprint 6 slice 4 closed audit NEW-47 (the password-login POST verifies no CSRF token).
  Both review passes then found the same gap on the **other** `Auth::login()` call site — the OAuth
  consent screen (`core/mcp/oauth-authorize-view.php`), recorded as **NEW-51**. Fixing it was three
  lines. Proving it required requesting the URL, and the URL answered a **fatal**:
  `Call to undefined function Klytos\Core\handleOAuthAuthorizeView()`. `Router::handleOAuthAuthorize()`
  calls the view's function unqualified from namespace `Klytos\Core`, while the function is declared
  in `Klytos\Core\MCP`; PHP falls back to the **global** namespace and never to a sibling. **The OAuth
  consent screen has never rendered for anybody**, so the authorization-code flow — the only
  interactive way an MCP client can be authorized — could not be completed by any client, ever
  (**NEW-52**, HIGH, and byte-identical at HEAD, so pre-existing).
- Where: `installer/core/router.php::handleOAuthAuthorize()`;
  `installer/core/mcp/oauth-authorize-view.php:30`.
- What failed, and it is not "nobody tested it": **five sprints of readers walked past it, including
  two review subagents in this very slice and a fresh-context QA pass, because reading a call site
  does not tell you which namespace resolves it.** `handleOAuthAuthorizeView($this->app)` looks
  correct in isolation and is correct in every file that declares the function in the SAME namespace.
  Worse, the audit already had an entry about this page — **NEW-38**, "the consent screen cannot
  complete a 2FA login" — derived carefully from source, describing the behaviour of a page that
  could not render. A finding written from reading can be perfectly reasoned and still describe
  something unreachable.
- The near-miss that makes it a lesson rather than a bug report: had the CSRF fix been shipped without
  requesting the URL, this slice would have "closed" a vulnerability on a page that fatals, the fatal
  would have survived, and the audit would have carried NEW-51 as CLOSED and NEW-38 as the only
  problem with a screen nobody could open.
- Working solution: fix the call (`MCP\handleOAuthAuthorizeView(...)`, fully qualified, with the
  reason at the call site so it is not "simplified" back), and pin the page with an HTTP test that
  drives the real authorize URL — built from a real client through the product's own
  `OAuthServer::createClient()` and PKCE, because an invented URL is refused before the form renders
  and the test would then pass on the error page. Proven by reverting the qualification: the harness's
  own `formSession()` fails loudly with *"issued no klytos_session cookie"* — the fatal page.
- Rule for next time: **before adding a control to a surface, REQUEST that surface once.** Not the
  function, not the file — the URL, over HTTP, as a client reaches it. If it does not answer, the
  control being added is decoration and the real defect is underneath it. This is L-005 ("verify
  through the product's own API") and L-014 ("drive the FEATURE, not the finding") applied to the
  cheapest possible check, and it costs one `curl`. Corollary, from the same session: an audit entry
  about a surface is not evidence that the surface works — check reachability before inheriting its
  description.

## L-028 — The session-start freshness check fed the counter that a later test measures, and the suite failed on code that was fine
- Problem: the first full-suite run of this session reported **1 failure** —
  `LoginCeilingHttpTest::testTheIpCeilingRefusesABurstOfInventedUsernamesThroughTheShippedForm`,
  *"Attempt 10 of 10 was refused before the ceiling was reached"*. The tree was at HEAD, unmodified,
  and the same suite had been recorded green at 276 tests / 1331 assertions at the previous session's
  close.
- Where: `docs/playground.md` §3's documented MCP probe, versus
  `installer/data/rate_limits.json` and `tests/Integration/LoginCeilingHttpTest.php`.
- What actually happened: Phase 5 §4 requires booting the playground from its documented commands at
  the first test point of every session. One of those documented commands is *"Unauthenticated — must
  be 401"*. **A 401 IS an authentication failure**, so the probe did exactly what it is written to do
  and, in doing so, wrote one entry into the product's persistent, IP-keyed auth-failure bucket —
  `{"auth_failures":{"ip:127.0.0.1":[…]}}`. The ceiling is 10 per 60 s and the test spends all ten, so
  a single leftover entry moves the refusal one attempt earlier and the test's own guard fires.
  Verified by reading the file, not by reasoning: exactly **one** entry, for exactly the address the
  test uses.
- Why the existing rules did not cover it: **L-025** is about two passes running at the SAME TIME over
  one playground, and its remedy is "wait for the other process to exit". Here nothing was concurrent
  — the server had been stopped and the port re-checked free before the suite started. The
  interference was *sequential residue* in on-disk application state, which no port check, no `lsof`,
  and no waiting can see. `PlaygroundState` did not help either, and could not: it snapshots at the
  start of each test, so it faithfully preserved the pollution it found.
- How it was caught: by refusing to act on the failure. One test, failing on unmodified code that was
  green at the last close, with exactly one new variable in the environment — that is a hypothesis
  about the instrument, not about the product (L-008). Reseeding with
  `seed-playground.php --reset` and re-running returned **276 tests / 1331 assertions green, with no
  test and no product file touched**. Had it been "fixed", the fix would have been permanent and the
  defect imaginary — the same near-miss L-025 records, arriving by a different route.
- Working solution: **reseed between the freshness check and the suite.** The freshness boot proves the
  document and the environment; `--reset` then returns the product to a known state before anything
  measures it. `docs/playground.md` now says so at both places a reader meets it, so the next session
  does not rediscover this.
- Rule for next time: **any verification that provokes a refusal is a WRITE, not a read.** A 401, a
  403 and a 429 all leave persistent state behind in this product by design — that is what the
  hardening sprints built — so the session-start ritual is not free, and the suite must start from a
  reseeded playground rather than from "whatever the freshness check left". Generally: before treating
  a single unexplained failure as a defect, ask what the SESSION did to the environment, not only what
  the code did — the answer here was a command the project's own documentation told me to run.

## L-029 — The probe measured its own missing tool and reported it as a missing world

- **Symptom.** Three confident negatives in one session, each of which read as a fact about the
  machine or the network:
  1. Keel's update check reported *"the remote lookup did not answer"*, and the project was
     reconciled to **v3.5.0** — the copy that happened to be on disk — while **v4.0.0 and v5.0.0
     already existed**.
  2. The environment probe reported **PHP, Composer and Node all absent**. On that answer the
     session is `NO-EXECUTION`, and every test in the project goes back to the user's hands.
  3. A grep reported **Codex has no rule container**, and that was written into a decision entry.
- **Cause.** In all three the instrument was broken and the subject was fine, but the *shape* is
  sharper than that: **each probe measured its own environment and reported the result as a property
  of the world.**
  - `timeout` does not exist on macOS. `timeout 25 git ls-remote …` produced no output and exited 0,
    so "the tool I invoked is absent" arrived looking exactly like "the network is absent".
  - This shell starts with `PATH=/usr/bin:/bin:/usr/sbin:/sbin` and does not source the user's
    profile. `command -v php` therefore measured **my PATH**, not the machine — which has PHP 8.3.12,
    8.2.24 and 8.1.30 installed, and runs the whole suite.
  - The grep searched `.codex/` and the root `AGENTS.md` — the two places Codex's rules were never
    going to be. Its rules are three nested `AGENTS.md` files, exactly where `assistant-config.md`
    says they go.
- **Why this is worse than a false positive, which is the part worth keeping.** A false positive is
  investigated, because a claim that something is broken invites a check. **A false negative is
  filed.** "Not found", "no answer", "absent" all read as thorough — nobody re-derives an absence.
  And the update check compounds it: its 24-hour throttle stamp would have suppressed the retry, so
  the project would have sat two major versions behind *believing it was reconciled* until the user
  happened to say "actualiza keel". L-016 said a broken measurement goes green; this adds that **a
  broken measurement can also go red and still be believed, as long as the red is about the world
  rather than about the work.**
- **Fix.** `scripts/keel-doctor` widens `PATH` itself instead of trusting what it inherited, and
  probes Composer through its phar because the `composer` on this machine is a shell alias that does
  not exist in a non-interactive shell. The PATH trap is written into `docs/playground.md` Step 0,
  `docs/01-discovery.md` §9 and `docs/03-technical-plan.md` §4b — three places a session actually
  reads — rather than left as something to rediscover. D-066's Codex claim is corrected in place and
  the three nested files were updated.
- **Check added.** `scripts/keel-doctor --check` is now the first command of every session,
  development or maintenance, and it fails loudly on a blocking row instead of letting a bare
  `command -v` speak for the machine. Plus `keel-verify` check 13 ("every cited command exists"), so
  a command named in a document cannot quietly stop existing. **What is NOT checkable mechanically,
  said plainly:** nothing can verify that a probe searched the right place — that stays a habit, and
  the habit is *state what the probe would print if the thing existed but you were looking in the
  wrong place, and if the answer is "the same thing", look somewhere else too.*
- **Rule for next time.** **A negative result is a claim about your instrument until you prove it is
  a claim about the world.** Before recording an absence — of a tool, a file, a network, a rule —
  run one command that would succeed if the thing were present somewhere you did not look:
  `command -v` the tool itself, print `$PATH`, `ls` the parent directory, `git ls-remote` without the
  wrapper. It costs seconds. Reporting an absence that turns out to be your own blindfold costs a
  version, a decision entry, or a project's whole test discipline.

## L-030 — The gate proved the asset file was intact and never asked whether it contained what the screens draw
- **Symptom.** Stage 1 of the Phase 4 build placed the delivered icon sprite and verified it
  byte-for-byte, 67 `<symbol>`s counted. Stage 2 opened the shell, went to write the sidebar's first
  `<use href="…#ks-palette">`, and the id was not in the file. Nor were 18 others: 19 of the 35
  glyphs the design's own prototypes draw in the sidebar are absent from the sprite that
  `SPEC/assets-index.md` §3 calls "one `<symbol>` per glyph the design uses" and names as the
  sidebar's icon source at 18px. The build had been one line away from shipping a navigation whose
  icons render **nothing, silently, with no console error** — a broken `<use>` is not an error, it
  is an empty box.
- **Cause.** The Step 1 completeness gate asked *integrity* questions and never asked a *coverage*
  question. Its evidence rows are all of the form "the asset in `assets-index.md` exists", "the SVG
  is byte-identical", "the sprite has 67 symbols" — every one of them true, and none of them the
  question that mattered, which is **"is the glyph this screen draws one of the 67?"** The same
  blindness produced gap 2 of the same Design Request: `template-shell.md` is present and complete
  *for what it claims to cover* (states, responsive, accessibility), so a presence-based gate passes
  it without noticing that the sidebar's contents — which of the 44 entries sits in which of the
  eight groups — are specified in no file at all. Both gaps are absence-shaped, and a checklist that
  verifies things exist is structurally worst at absence. The delivery itself had already
  demonstrated the right method and it was not generalised: `open-questions.md` item 18 asked "Does
  the Dashboard need a new glyph?" and answered it by checking all eleven against the sprite — once,
  for one screen, never for the shell.
- **Fix.** No file was written. Stage 2 stopped before its first line (**D-073**), the two gaps went
  to Design as **DR-003**, and the finding is recorded in `docs/BUILD-SPEC.md` §5.7 so the contract
  carries it. The three tempting build-side outs were each refused on the record: substituting a
  present glyph (`folder` for `perm_media`) changes what Design chose, drawing 19 Material Symbols
  paths is authoring visual assets, and a CDN icon font is refused by build rule 4 and by the CSP.
- **Check added.** **(a) BUILT 2026-07-29 with DR-003's resolution (D-074) — `keel-verify` check 16,
  `every #ks-* the admin references resolves to a sprite <symbol>`.** It is a FAIL, not a WARN, and
  it was **proven to fail before being trusted**: a deliberately bad `#ks-not_a_real_glyph` planted
  in `installer/admin/index.php` turned it red with the file named and the exit code 1; the file was
  restored from a backup and re-verified byte-identical (`git diff --stat` empty) and the exit code
  returned to 0. It was added **before the first `<use>` was written**, which is the whole point —
  today it passes on **zero** references, and that is the check waiting correctly, not a weakness.
  Registered in `KeelVerifyTest::EXPECTED_CHECKS` so the count can only move deliberately.
  **(b) STILL OWED:** the per-screen glyph-presence row in the Step 1 gate's evidence table, so a
  future handoff cannot pass by *declaring* its sprite complete. Owner: the next Phase 4 Step 1 run.
  The original wording follows, unedited, because a lesson that quietly rewrites its own promise is
  the defect it was recording.

  > **NOT YET BUILT, and it is owed by the DR-003 resolution pass — stated here so it
  cannot become a promise nobody kept.** Two, both mechanical: (a) a `keel-verify` check that every
  `#ks-*` referenced anywhere under `installer/admin/` resolves to a `<symbol id>` in
  `assets/icons/klytos-ui-icons.svg` — today it would pass on zero references, which is the point:
  it fails the moment the first bad `<use>` is written, instead of a year later when someone
  screenshots an empty box; and (b) a **glyph-presence row per screen** in the Step 1 gate's evidence
  table, so a future handoff cannot pass by declaring its sprite complete. What is **not** checkable
  mechanically, said plainly: nothing can verify that a SPEC file covers everything it needed to
  cover — `template-shell.md`'s silence about the nav's contents would satisfy any presence check.
  That stays a habit, and the habit is: **for every artifact the gate proves EXISTS, ask separately
  what CONSUMES it, and check the consumer's demands against the artifact's contents.**

## L-031 — The half I could not drive was the half that was broken, and "verified" had been said about the other half
- **Symptom.** Stage 2 of the Phase 4 build (the shell) closed on 2026-07-29 with an unusually strong
  evidence record: 39 screens driven as owner with zero errors, the capability rule driven as all
  four roles, landmark uniqueness, child parentage, and the theme toggle's 403/405/invalid/
  open-redirect branches all walked. One paragraph of that record read `⚠ unverified` — the
  JavaScript — because Playwright was not installed. Playwright was installed the next work block,
  and **the first driven run found two defects, both in that paragraph's territory, and one of them
  was a keyboard trap.** Pressing Escape in the command palette did nothing: closing it returned
  focus to the search field, whose focus listener re-opened it, with no way out using the keyboard
  alone (WCAG 2.1.2). The second: the rail's "Expand navigation" button was `display: none` at every
  viewport, because the base rule sat AFTER the media query that turns it on and won on source order.
  The button existed, had its accessible name, had its `data-testid`, and no user could ever click it.
- **Cause.** Two mechanisms, one habit.
  **(a) The defects lived between two things that were each correct.** The search field opening the
  palette is `template-shell.md` §1. Returning focus to the opener is `accessibility.md` §3.2. Both
  are right; their composition is a trap, and no amount of reading either listener produces the
  sentence "these two call each other". The CSS is the same shape one layer down and is *literally*
  build rule 1 — the rule this project already wrote down after `typography.css` shadowed
  `--type-body`, arriving in a layout property instead of a token and therefore not recognised.
  **(b) The strength of the verified half made the unverified half feel small.** The stage-2 record is
  honest — it names what was not verified, in bold, with the reason. But a record that is 95 %
  green and 5 % `⚠ unverified` is read as "verified", and the 5 % was not a random sample: it was
  precisely everything a keyboard user touches, which is where an accessibility defect lives by
  definition. **The unverified fraction is never a random fraction. It is the fraction the available
  tools could not reach, and defects concentrate there for the same reason.**
- **Fix.** The user was asked, at the start of the block, whether to install Playwright rather than
  being handed another stage of `⚠ unverified` — which is the Keel v5.0.0 contract working as
  designed, since installing a ~400 MB driver is not the assistant's call. Playwright 1.62.0 +
  chromium went in, `tests/E2E/` was created with a real-form login fixture and the read-back duty,
  and 16 tests now cover the palette, the drawer, the rail and the offline state. Both defects were
  fixed and each fix was **proven by watching its test go from red to green**, not by re-reading the
  code. Recorded as D-077.
- **Check added.** **(a) `tests/E2E/shell.spec.js` — 16 driven tests**, and the two that matter here
  (`Escape closes it and focus returns to whatever opened it`, `Expand navigation restores the full
  sidebar and the choice survives a reload`) were **both observed FAILING against the unfixed code
  before the fixes landed**, which is the only thing that makes them evidence rather than decoration.
  **(b) The read-back fixture in `tests/E2E/fixtures.js`** fails any test whose flow produces a
  console error, a page error, a failed request, a 5xx or a new ERROR line in
  `installer/data/logs-*/` — **proven by planting a `console.error` and watching it turn red**, then
  removing the probe. **(c) `trace`, `video` and `screenshot` are on for every test, passing or
  failing** (Keel v5.1.0), so a future run leaves something a human can open instead of a word.
  **What is NOT mechanically checkable, said plainly:** nothing can detect that two individually
  correct specified behaviours compose into a trap. That stays a habit, and the habit is: **when a
  slice closes with any `⚠ unverified` fraction, treat that fraction as the most likely location of
  the next defect, not the least — and say so in the record, so the next session inherits a suspicion
  instead of a footnote.**

## L-032 — Three times now the stylesheet said one thing and the browser painted another, and each time the mechanism was different
- **Symptom.** Stage 3 of the Phase 4 build wrote the component layer. Driving it
  found, in one session, a record's name rendering in the accent colour at 4.11:1
  when a rule plainly said `--texto-primario`; a whole page scrolling horizontally
  346px at 320 CSS px while every containment measurement in the chain read
  correct (280 inside 320, `overflow-x: auto` present, `overflow: hidden` on the
  card); and a destructive button reported at 2.59:1 in dark when it is 4.86:1.
  Three confident, wrong readings of the same file.
- **Cause.** Three different mechanisms, which is the whole point.
  **(a) Specificity, not source order.** The generic in-component link rule's
  widest branch was `.k-card a:not(.k-btn):not(.k-chip):not(.k-stat)` — `(0,4,1)`,
  because `:not()` contributes its argument's specificity — and every table sits
  inside a card, so it beat `.k-table tbody th a` at `(0,1,3)`. **The previous
  lesson had taught "check source order" (D-077's rail button), and that framing
  is what made this one invisible**: source order never entered into it. Two
  attempts to out-specify the rule failed before the structure was questioned.
  **(b) An absolutely positioned element escaping two clipping ancestors.**
  `.k-sr` (visually hidden, `position: absolute`) is used by the table's "Actions"
  column header — that is `SPEC/accessibility.md` §2.1's own markup. With no
  positioned ancestor, its containing block was the initial one, so it laid out at
  its static position inside the 670px-wide row and dragged the page's scrollable
  area with it, straight through `overflow-x: auto` and `overflow: hidden`. A 1 × 1
  invisible span producing a WCAG 1.4.10 failure.
  **(c) The measurement itself was inside a transition.** `.k-btn` transitions
  `color` over 120ms (the design specifies it). The test toggled `data-theme` after
  paint and read `getComputedStyle` immediately, so it got the INTERPOLATED value
  and reported a contrast failure for a button that is fine. A specified transition
  and a theme-switching test, each correct alone, composing into a false accusation
  — L-031's shape, arriving inside the tooling instead of the product.
- **Fix.** (a) The default link colour moved inside `:where()`, giving it **zero
  specificity**, so every named component rule beats it by construction rather than
  by arithmetic — a structural answer after two numeric ones failed. (b)
  `.k-table-scroll` became a containing block (`position: relative`), with the
  reason written into the stylesheet at length so nobody removes it as decoration.
  (c) The fixture now bakes `data-theme` into the markup **before load**, which is
  also how the product really works (D-075: server-rendered from the cookie, no
  flash) — so the test and the product agree about what a theme is.
- **Check added.** **(a) Every cascade and geometry assertion in
  `tests/E2E/components.spec.js` reads the COMPUTED value out of the browser** —
  never the file, never a locator's class list: button heights, badge and chip
  pills, the field's border colour, the checkbox's 24 × 24 hit pseudo-element, the
  table's `grid`/`contents`/`grid`, and the fact that `--type-body` resolves to
  13px/17px rather than the shadowed PackDesk value. **(b) The 1.4.10 test asserts
  by TRYING TO SCROLL** (`window.scrollTo(5000,0)` then reading `scrollX`) instead
  of comparing `scrollWidth`, because the two disagreed and the disagreement was
  the finding; **proven by removing the containing block and watching the 346px
  return.** (c) `openSpecimen()` asserts the theme substitution actually took, so
  a fixture edit cannot silently turn every dark-theme test into a light-theme one.
  **What is NOT mechanically checkable, said plainly:** nothing can tell you in
  advance which of the cascade's several tie-breakers is about to decide a
  declaration. So the rule this buys is not about any one of them — it is: **NEVER
  ASSUME WHICH RULE WINS. READ THE COMPUTED VALUE OUT OF THE BROWSER.** A lesson
  phrased as "check source order" is already too narrow, and this project has now
  paid for that narrowness once.

## L-033 — The specimen proved the component layer correct in a cascade the product never has, so the fix for the last lesson shipped a new defect
- **Symptom.** Stage 3 built the component layer and closed with 64 driven tests, axe per state in
  both themes, and a recorded measurement for the in-component link colour: "applied, it measures
  4.82:1 light, 6.40:1 dark". Stage 4 built the first real screen and the same links measured
  **4.31:1** in dark and rendered `#5B8DEF` — a blue that is not in the delivery's palette at all.
  It is `--klytos-accent`, the PRE-REDESIGN token. Every plain link inside a card, on every real
  admin screen, had been painted the old blue since the moment the new layer shipped.
- **Cause.** Two things, and the second is the one worth keeping.
  **(a) The specificity floor was zero, and zero loses to a bare element selector.** D-078 wrote
  the default link colour inside `:where()` at (0,0,0) deliberately, so that every named component
  rule would beat it by construction rather than by arithmetic — a good fix for the defect it was
  fixing. But `klytos-base.css`, the pre-redesign stylesheet every admin screen still loads, has
  `a { color: var(--klytos-accent) }` at **(0,0,1)**. (0,0,0) does not beat "nothing"; it beats
  nothing *at all*, including the sheet the redesign is supposed to supersede. **This is build rule
  1's fourth distinct mechanism, and the fix for the third one is what introduced it.**
  **(b) THE SPECIMEN'S CASCADE IS NOT THE PRODUCT'S CASCADE.** The component specimen
  (`tests/E2E/fixtures/components-specimen.html`) is served by route interception at an
  `/installer/admin/` path specifically so it gets the playground's real origin and "the REAL
  stylesheet chain" — which it does, for the sheets it links. It does not link `klytos-base.css`.
  So the measurement was taken in a document that no user ever loads, and it was correct there and
  wrong everywhere else. The specimen answered "is this rule right?" when the question that
  mattered was "does this rule win where it runs?"
- **Fix.** `:is(.k-card, …) :where(a:not(…))` — the ancestor half carries (0,1,0), the anchor half
  stays at zero. It beats a bare element selector and still loses to `.k-table tbody th a` at
  (0,1,3), which is the defect D-078 was solving, so both properties hold at once. Recorded as
  **D-079**. Two sibling defects found in the same run had the same root shape: the bulk bar's
  `hidden` attribute losing to `.k-bulkbar{display:flex}` on source order, and `.k-has-bulkbar`
  losing to `.k-main`'s `padding` shorthand in a later sheet. Both were fixed by raising specificity
  rather than by reordering, because order-independence is the property that survives the next edit.
- **Check added.** **(a) `tests/E2E/pages.spec.js` reads its cascade and geometry assertions off a
  REAL SCREEN**, not off the specimen — including the per-screen `grid-template-columns`, the
  full-width empty row, the sticky row-header column and the stacked-card breakpoint. **(b) Each of
  the five defects this stage found was proven by planting it back and watching its own test go
  red**, then restoring the file and verifying it byte-identical — including the one that first
  appeared to stay green, which turned out to be a bad grep over my own output and not a weak test.
  Saying so is the point: a false green reported about your own tooling is worse than the defect.
  **What is NOT mechanically checkable, said plainly:** nothing can tell you that a fixture's
  document differs from the product's document in the one way that matters. So the rule this buys
  is: **a component is not verified until it has been measured on a page a user can actually
  navigate to.** A specimen proves the rule is written correctly. Only the product proves it wins.
  Corollary for the eleven list screens still to come: the legacy sheets are still loaded, so every
  ported screen re-opens this question, and every one of them measures its own colours.

## L-034 — The design named a state the data layer could not express, and the method that should have reported it crashed instead
- **Symptom.** Stage 4's remaining surface, manifest entry 41 (Logs), specifies two different
  screens for a log with nothing on it, each with its own sentence:
  *"`error.log` is empty. Nothing has been written since it was rotated on 24 July."* and
  *"`error.log` cannot be read — permission denied on `/var/log/klytos/`."*
  (`template-console-stream.md` §2). Neither could be built. `Logger::readLogFile()` answers **both**
  with an empty array, so the screen had no way to tell them apart — and in the unreadable case it
  did not return an empty array at all: `file()` answers `false` on a failed open, `count( false )`
  is a **TypeError** under PHP 8, and the request died. **The state the design asks for was
  unreachable by construction, and reaching for it was a fatal.**
- **Cause.** Two defects sitting on top of each other, and the order matters.
  **(a) A dead variable hid a live crash.** The line was `$total = count( $lines );` and `$total`
  was never used again — it had no purpose except to be the thing that threw. A reader skims past an
  unused assignment; PHP does not.
  **(b) The reader collapsed three different answers into one.** "Missing", "empty" and "cannot be
  opened" are three facts a log viewer must distinguish, and the method returned the same value for
  all of them — so no caller could ever have rendered the specified states, however carefully it was
  written. The screen was not missing a branch; the branch was not expressible.
- **Why nothing had caught it.** Logging is written under Developer Mode and read by an admin whose
  own process wrote the file, so in every path anyone had exercised the file was readable. The
  unreadable case is the one that happens on a real host — a log rotated by root, a directory whose
  mode changed, a file owned by another user — and it is exactly the case with no test.
- **The general shape, which is the part worth keeping.** Stages 2, 3 and 4A each found the design
  and the code disagreeing about **presentation**. This is the first time they disagreed about
  **what the data layer can say at all**, and it did not surface at the gate, in the SPEC audit, or
  in any amount of reading — it surfaced on the first attempt to render a state. A per-screen check
  that every state in a template's §2 has a data source capable of distinguishing it would have
  found this before the build, the same way the per-screen glyph-presence check (D-074) now finds
  the missing-icon shape.
- **Fix.** `readLogFile()` guards with `is_readable()` and re-checks `file() === false`, returning
  no lines rather than fatalling; the dead `$total` is gone. The distinction the screen needs became
  its own small public surface, **`Logger::isLogFileReadable()`**, placed on the Logger rather than
  in the page because answering it means resolving a filename to a path inside the logs directory,
  and that resolution is a security boundary (traversal refusal, prefix and extension validation) —
  a second copy of it in a screen would be a second implementation of the same rule, free to drift.
- **Check added.** `tests/Unit/LoggerReadFailureTest.php`, **written before the fix and observed
  failing for the right reason** — `TypeError: count(): Argument #1 ($value) must be of type
  Countable|array, false given` at `logger.php:264`, not a broken import. Six tests pin all three
  answers apart, plus the ordinary offset/limit path and the traversal refusal that had to survive
  the change. The unreadable fixture skips rather than lies where the running user can bypass mode
  0000 (root), because a test that silently passes as root would be worse than none.

## L-035 — The harness said "both themes" and had only ever measured one, and nothing could have told you
- **Symptom.** `tests/E2E/logs.spec.js` audited every state of the Logs screen with axe in
  "both themes", and reported it. The light-theme runs were rendering DARK. The suite was
  green, the count was right, the test names said `— light`, and the claim in the record
  would have been false.
- **Cause.** The helper set a cookie named `klytos_theme`. The shell reads
  `klytos_admin_theme` (`templates/header.php`). An unknown cookie is not an error — the
  server simply fell back to the default theme — so the page loaded, every assertion in the
  test passed on the dark rendering, and the only thing that was wrong was the word in the
  test's own name.
- **Why nothing caught it.** Every check in the file was a check about the SCREEN. Nothing
  was a check about the HARNESS. The theme was an input the test set and then never read
  back, which is the one shape a passing test cannot detect: a test proves what it asserts,
  and this one never asserted that the thing it varied had varied.
- **What it cost, immediately.** Fixing the cookie surfaced three light-theme axe failures
  that had been invisible — and one of them, the chip pair on `--fondo-ventana`, is a gap
  DR-005's addendum had already predicted would reach "thirteen filter rows". The suite had
  been carrying a false green for exactly as long as the file had existed.
- **The general shape, which is the part worth keeping.** This is L-030's rule turned on the
  tooling rather than on the product: *a check that has never been shown to fail is not
  evidence.* Stage 2 learned it about a `keel-verify` check passing on zero references;
  stage 3 learned it about a specimen that did not load the product's stylesheets; this is
  the same lesson about a test's own parameter. **Whenever a test varies an input, it must
  read that input back from the system under test before asserting anything else** —
  theme, locale, role, viewport, feature flag, seeded data. The read-back costs one line
  and is the only thing standing between "we verified both" and "we said we verified both".
- **Fix.** `open()` now reads `document.documentElement.getAttribute( 'data-theme' )` and
  fails with a message naming the problem — *"the theme cookie did not take — this run
  measured the wrong theme"* — before the test body proceeds. The corrected cookie name is
  the smaller half of the fix; the assertion is the half that generalises, because the next
  spec to set the wrong thing will be stopped rather than believed.
- **Check added.** The assertion itself, and it was **proven by planting the wrong cookie
  name back and watching the light-theme tests fail with that message**, then restoring the
  file byte-identically. `KNOWN_DELIVERY_GAPS` also moved out of `pages.spec.js` into
  `tests/E2E/fixtures.js` in the same slice: the second screen to hit the chip pair proved
  that list is not one screen's property, and a per-spec copy would let screen twelve
  exclude a pair screen one had already fixed.

## L-036 — Build rule 1's sixth mechanism, inside the section whose comment said it could not happen there
- **Symptom.** Logs rendered every ERROR and WARN line with **no tint and no colour**: the
  classes were on the element, the stylesheet plainly declares them, and
  `getComputedStyle` returned `rgba(0, 0, 0, 0)`.
- **Cause.** `.k-stream-line` resets `background` and `color` because a `<button>` carries a
  UA background of its own. That reset is (0,1,0) and lives further down the file than
  `.k-line--error` / `.k-line--warn`, which are also (0,1,0). Equal specificity, later
  wins — so the reset ate the tint. The section's own header comment warns about source
  order in so many words, and the trap still landed, because the comment was about MEDIA
  QUERIES and the collision was between a component reset and a modifier class.
- **And the fix broke two more states before it worked.** Excluding the tints from the reset
  raised it to (0,3,0), which then outranked `.k-stream-line[aria-pressed="true"]` at
  (0,2,0) — a selected line lost its selection colour. Excluding the selected state from
  both declarations left it on the UA's `buttontext`, measured 2.03:1 in dark and 15.87:1
  in light: a pair that comes from no stylesheet in this project. The third version splits
  the two declarations, because they have DIFFERENT exception sets — a selected line paints
  its own background but not its own colour — and that asymmetry is the whole content of
  the bug.
- **The general shape.** Six mechanisms now: token shadowing, source order between a base
  rule and a media query, specificity between two component rules, zero specificity losing
  to a superseded sheet, a shorthand in a later sheet, and now **a component reset eating a
  modifier class defined earlier in the same file**. The rule does not generalise to any
  list of mechanisms — the list keeps growing. It generalises to exactly one instruction,
  L-032's, and this is the sixth time it has paid: **never assume which rule wins; read the
  computed value out of the browser, on a real screen.** Corollary earned here: after
  changing a cascade rule, re-measure **every state of the component**, not the one that was
  broken — two of the three wrong versions looked correct in the state being fixed.
- **Check added.** `tests/E2E/logs.spec.js`'s *a SELECTED tinted line keeps its tint* and
  *measures the pairs DR-007 asks about* read the composited ratio out of the browser for
  four states in both themes. The measurer itself had to be fixed first, and its bug is the
  same family: it read the first non-transparent `backgroundColor` instead of COMPOSITING
  the translucent tints over the panel, and reported 1.12:1 for a pair that measures
  4.53:1 — a false FAILURE, which is as much a tooling defect as a false pass. Both fixes
  proven by planting the defect back and watching the test go red, then restoring
  byte-identically.

## L-037 — Every accessibility pass was scoped to `#main`, so the component on every screen was the one nothing scanned

- **Symptom.** Building manifest entry 19 — the sixth screen of this redesign — the axe
  pass reported a contrast failure on the SIDEBAR's current nav item: **4.31:1 dark,
  3.70:1 light**, both under AA. The screen was new; the defect was not. Re-run against
  `theme.php`, `logs.php` and `pages.php`, it reproduced on all three.
- **Cause.** Nothing about the shell had changed. What changed was the SCOPE of the
  scan. `design.spec.js`, `logs.spec.js` and `pages.spec.js` all narrow axe with
  `.include( '#main' )` or `.include( <section> )` — a reasonable-looking choice each
  time, made so that a screen's spec measures that screen. But the shell is the sidebar,
  the toolbar and the status bar, and **`#main` is by definition where the shell is
  not**. So the most-shown component in the entire admin — on all 39 screens, in every
  session, at every role — had been scanned exactly zero times, across four stages that
  each reported an accessibility pass.
- **Why it read as covered.** Every one of those specs was honest: each said "axe at
  WCAG 2.2 AA, both themes", each ran, each passed. None claimed to cover the shell and
  none said it did not. The gap lived in the space BETWEEN four specs, which is exactly
  where no single spec's author looks.
- **The general shape, and it is L-031's with the subject changed.** L-031 said the
  unverified fraction is never a random fraction — it is the fraction the tools could
  not reach, and defects concentrate there for the same reason. This is that rule
  arriving on the TOOLING rather than on the product: the fraction a scan EXCLUDES is
  not a random fraction either, because exclusions are chosen for convenience.
  **A per-screen check cannot cover a cross-screen component, and adding a sixth
  per-screen check does not fix it — one of them has to scan the whole page.**
- **Check added.** `tests/E2E/content-model.spec.js` scans the WHOLE page, with the
  reason written into its `scan()` helper so a later author does not tidy it back to
  `#main`. The finding is DR-005 addendum 2 (the palette is Design's — Phase 4 rule 2),
  excluded by selector with **both ratios pinned as floors** in `shell.spec.js`, each
  proven to FAIL on a planted colour and then restored byte-identically.
- **A second tooling defect in the same hour.** `AxeBuilder.exclude()` reads an ARRAY as
  a FRAME PATH — "inside frame A, the element B" — not as a list of selectors, so
  `exclude( KNOWN_DELIVERY_GAPS )` matched nothing and excluded nothing. Here it failed
  loudly, which is why it was caught; the same mistake on a list of REAL exclusions
  produces noise nobody can clear, and the natural response to unclearable noise is to
  stop reading the output. **An exclusion list must be proven to exclude, exactly as a
  check must be proven to fail** — and the axe pass was then planted against twice,
  because the first plant landed on a rule with no rendered text and proved nothing.

## L-038 — A blank line above a table row orphans it from the table, and the check that validates it passes because it never sees it

- **Symptom.** A new Phase 4 stage row was appended to `docs/05-test-points.md` carrying
  a `Red first` value that is **not one of the five** the legend permits.
  `scripts/keel-verify` — which has a check whose entire job is to FAIL exactly that —
  reported `PASS`.
- **Cause.** The insertion left a blank line between the last existing row and the new
  one. The checker walks the file linearly and resets its notion of the current table on
  any line that is not a pipe row, so the new row had **no header above it** and was
  skipped entirely: not validated, not counted, not reported. Its own counter gave it
  away — "15 row(s) carry a recognised value" before the insertion and 15 after. Markdown
  renders it the same way: two tables, not one.
- **The first reading was wrong, and that is the part worth keeping.** The obvious
  conclusion was that the check was decoration — the same defect D-076 found in check 16,
  which was "passing on ZERO references". It was not: with the blank line removed the
  check counted 16 rows and **failed the planted bogus value immediately**. The tool was
  right and the input was malformed, which is the less flattering of the two readings and
  the reason to test before concluding.
- **The general shape.** A record that is silently not read is worse than one that is
  missing, because the missing one is visible. Filing something in the wrong place looks
  identical to filing it correctly, from the side the author is standing on — and every
  mechanical check has an input shape it silently ignores.
- **Check added.** None in code: the checker already does the right thing. The rule is
  procedural and cheap — **after appending a row to any checked table, re-run the check
  and confirm its ROW COUNT went up.** A passing check whose count did not move is not a
  passing check.

## L-039 — A component was written two slices before anything rendered it, so it shipped below AA and every pass in between was honest and blind

- **Symptom.** Building manifest entry 39 — the seventh screen of this redesign, and the
  first with more than three cards — the axe pass reported a contrast failure on the
  SECTION NAV: `--texto-secundario` on `--fondo-ventana`, **4.46:1 in light**, under AA
  by 0.04. Recomputed independently in Python from the WCAG formula; axe read 4.45 and
  the arithmetic agreed. The screen was new. The rule was not: it had been in
  `klytos-components.css` since **D-088**, two slices earlier.
- **Cause.** Nothing about the rule had changed. What changed is that something finally
  DREW it. D-088 built the record-form layer as a layer — `.k-record-form`,
  `.k-field-grid`, the swatch row, the pair display **and `.k-section-nav`** — and then
  built entry 3 as its first consumer. Entry 3 has no section nav, so it renders
  `.k-record-form--no-nav`. Entry 19, the next consumer, has no section nav either. The
  `.k-section-nav` block therefore sat in the shipped stylesheet, on every admin screen,
  for two whole slices, **painting nothing at all** — and a rule that paints nothing
  cannot fail a pass that measures what is painted.
- **Why it read as covered.** Every pass in between was honest. D-088 and D-089 each ran
  axe at WCAG 2.2 AA in both themes, each passed, and neither claimed to cover a
  component neither screen contained. The record even said the layer was "built". It was
  built; it was never *rendered*, and those are different claims that the word "built"
  does not distinguish.
- **The general shape, and it is L-030's with the author changed.** L-030 was about the
  DELIVERY handing over a seam nothing consumed — a sprite without the glyphs its screens
  draw. This is the same defect with the build as its author: **we wrote the component
  ahead of its consumer, on the reasonable-sounding ground that a layer should be built
  once.** D-079 had already established the counterpart ("a stylesheet with no consumer is
  where a defect hides") and D-088 quotes it — while introducing exactly that in the same
  file, in the same commit, in the part of the layer its own screen did not use. The rule
  was known, cited, and still not applied to the fraction of the work it covered. That is
  the fifth occurrence of this shape in this build and the first the build owns outright.
- **What it cost, and what it did not.** It cost a below-AA pair riding in the shipped
  stylesheet for two slices. It cost nothing to *users* only because no screen rendered
  it — which is precisely why it survived, and precisely why the next such block might
  not be so harmless.
- **Fix.** `--texto-primario`: **14.79:1 light / 15.29:1 dark**. Fixed in the build rather
  than registered as a Design Request, and the distinction matters — no delivered file
  states this control's colour, so the token was ours and the defect was ours. Recorded as
  **D-090** and as `BUILD-SPEC.md` §5.9 adaptation 26.
- **Check added.** **(a)** Two floor tests in `tests/E2E/post-type.spec.js` pin the ratio
  at the FIXED value (14.79 / 15.29), not at AA's 4.5, so a quiet slide back toward the
  threshold fails here rather than at some later screen's axe run. **(b)** Both, and the
  three light-theme axe runs, were **proven to FAIL on the planted original token** —
  they went red together — after which the stylesheet was restored byte-identically
  (`diff` clean). **What is NOT mechanically checkable, said plainly:** nothing can tell
  you that a CSS rule you just wrote has no element to match, because "no element matches"
  is indistinguishable from "no element matches YET". So the rule is procedural:
  **build a component with the screen that consumes it, and where a layer genuinely must
  come first, write down in the record that the unconsumed part is UNVERIFIED — not
  "built" — so the next slice inherits a suspicion instead of a green line.**

## L-040 — The stylesheet said `hidden` five times and the browser painted it five times, and the comment explaining why was already there

**What happened.** The Security screen's passkey enrolment form is revealed only where
`navigator.credentials` exists, because adding a passkey is a WebAuthn ceremony and a button that
cannot run one is a control that does nothing. It is written `hidden` in the markup and unhidden by
the script. Driven with JavaScript disabled, the form was **on the screen and operable**.

**The mechanism, which is always the same one.** `[hidden] { display: none }` lives in the user
agent stylesheet at specificity (0,1,0). `.k-field { display: flex }` is also (0,1,0) — and an
author stylesheet beats the user agent on ORIGIN, whatever the source order. The element keeps its
attribute, every reading of the DOM reports it as hidden, and it is painted, clickable and in the
tab order. Assistive technology and the browser disagree about whether it exists.

**Why this is a lesson and not a bug report.** It is the FIFTH occurrence: the bulk bar visible on
load (D-079), the Logs payload copy button (D-085), and this. And the stylesheet already carried the
rule, in a comment written at `.k-btn[hidden]` after the second occurrence, saying in as many words
that "any component here that sets `display` and is toggled with `hidden` needs its own `[hidden]`
rule". It was read, it was correct, and it was forgotten three more times.

**A rule that is only prose is a rule that is still open.** That is Keel's own standing principle
and this is the clearest instance of it in the project: the prose was not vague, was not buried, and
was not wrong. It simply required someone to remember it at the exact moment they wrote a new
`display` declaration, which is not a thing people do reliably. So the fix is not a better comment.
`scripts/keel-verify` check 21 now walks the admin's markup for every element carrying a bare
`hidden` attribute, resolves its `k-*` classes against the component stylesheets, and FAILS when one
declares a non-`none` `display` with no `[hidden]` rule of its own.

**And the check had to be proven twice.** Once by planting the missing rule back and watching it
name the real file and the real class — the project's standing duty. And once against itself: the
first run reported the sidebar's avatar, because `\bhidden\b` matches inside `aria-hidden="true"`
(`-` is a word boundary). A check whose first output is a false positive teaches people to ignore
it, which is worse than not having written it.

**The rule earned.** Every one of these five was found by DRIVING, never by reading, because the
source says `hidden` and looks right. When a defect class recurs and its prevention is a sentence
somebody has to remember, stop rewriting the sentence: the recurrence is the evidence that prose is
the wrong instrument, and the count is how you know.
