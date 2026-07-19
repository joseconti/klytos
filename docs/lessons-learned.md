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
