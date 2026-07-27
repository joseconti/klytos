# Lessons Learned — Klytos CMS

> Append-only; never trim. A session never repeats a mistake recorded here.

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
  the fresh-context QA pass at the same time.** Parallelising the review subagents is free because they
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
