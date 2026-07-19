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
