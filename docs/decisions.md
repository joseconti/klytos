# Decisions — Klytos CMS

> Append-only. A session NEVER re-opens a decision recorded here on its own initiative;
> only the user reverses a decision (append the reversal as a new entry).

## D-001 — Keel adopted in an existing project (brownfield)
- Date / phase: 2026-07-18 / Adoption
- Decision: Klytos CMS is brought under the Keel workflow via the adoption entry mode (`references/adoption.md`). Adoption documents reality and changes no code.
- Why: The project is mature and already released (self-updater, public GitHub releases, production installs), but had no Keel state (`docs/PROGRESS.md` did not exist) despite carrying a Keel lock and an outdated embedded skill copy.
- Alternatives rejected (and why): Treating it as a new project (would discard the as-built reality); resume mode (no state existed to resume from).
- Supersedes: none

## D-002 — Embedded Keel updated v1.11.0 → v3.3.0, in both trees
- Date / phase: 2026-07-18 / Adoption step 2
- Decision: The project's embedded Keel copy was replaced wholesale with the installed v3.3.0, verified file-for-file, in `.claude/skills/keel/` AND `.agents/skills/keel/` (previously only `.claude/`). The portability lock block was refreshed from the canonical copy and re-stamped v3.3.0 in `CLAUDE.md`, and `AGENTS.md` was created carrying the same block.
- Why: The Keel maintenance check found the embedded copy 20+ releases behind. The project is **open source**: forks and contributors may use any assistant (Codex, Copilot, Cursor, Gemini CLI, Windsurf, Zed, opencode, Cline…), so the workflow must travel via the open standards (`AGENTS.md` + `.agents/skills/`), not only the Claude-native paths.
- Alternatives rejected (and why): Claude-only lock and single embed tree — used on the author's private projects, but wrong here: a fork opened in a non-Claude tool would be unbound by the workflow.
- Supersedes: none

## D-003 — Project type: web app + MCP server (both security profiles)
- Date / phase: 2026-07-18 / Adoption (as-built)
- Decision: Klytos is classified as a self-hosted web application that also exposes an MCP server. Both `references/security/web-app.md` and `references/security/mcp-server.md` apply; on any conflict the stricter rule wins.
- Why: The product's primary interface is MCP (`/mcp` JSON-RPC endpoint, OAuth 2.0, 180+ core tools) sitting on top of a full PHP admin application with authentication, 2FA, payments (x402) and encrypted data at rest.
- Alternatives rejected (and why): "MCP server" alone (ignores the admin panel, sessions, CSRF, uploads); "web app" alone (ignores the model-facing threat class: tool poisoning, injection via tool results, confused deputy).
- Supersedes: none

## D-004 — Adopted as-is: stack and conventions
- Date / phase: 2026-07-18 / Adoption (as-built)
- Decision: The existing stack and conventions are adopted as-is and are the contract for new code: PHP 8.1+ with no framework; custom `spl_autoload_register` for `Klytos\Core`; `klytos_*` snake_case global helper API; PSR-12 as adapted by `phpcs.xml` (spaces inside parentheses permitted, snake_case exempted for the helper files); manager-class architecture behind `App`; pluggable `StorageInterface` (file/database); WordPress-style hooks (`klytos_do_action` / `klytos_apply_filters`).
- Why: Adoption principle 2 — conventions are observed, not imposed. Consistency with 228 existing PHP files beats Keel's defaults.
- Alternatives rejected (and why): Migrating to Composer/PSR-4/a framework — a real option, but a user decision for a future sprint, not an adoption-time change.
- Supersedes: none

## D-005 — Adopted as-is: license GPL-3.0-or-later
- Date / phase: 2026-07-18 / Adoption (as-built)
- Decision: The project is and remains GPL-3.0-or-later, with the declared clause that plugins and templates are not derivative works. Third-party vendored code under `installer/vendor-ai/` is MIT/BSD-family, compatible with GPL-3.0 distribution.
- Why: Declared in `LICENSE`, `README.md` and 195 of 228 first-party PHP file headers.
- Alternatives rejected (and why): none — this is recorded reality, not a new choice.
- Supersedes: none

## D-006 — Adopted as-is: i18n is multi-language, base English, custom JSON mechanism
- Date / phase: 2026-07-18 / Adoption (as-built)
- Decision: The product is multi-language with English as the base language, 20 shipped locales, and a custom key-based JSON mechanism (`installer/core/i18n.php`, `__('domain.key')`), not gettext. New user-facing strings go through `__()` with a catalogue key added to all locales.
- Why: The mechanism exists, the 20 catalogues are perfectly in sync (639 keys each), and switching to gettext now would be a rewrite with no user-visible benefit.
- Alternatives rejected (and why): Migrating to gettext/`.pot` (Keel's WordPress rule does not apply — this is not a WordPress project); leaving strings hardcoded (rejected — the observed hardcoding is recorded as a gap in `docs/04-adoption-audit.md`, not as an accepted practice).
- Supersedes: none

## D-007 — Accessibility target: WCAG 2.2 AA + European Accessibility Act
- Date / phase: 2026-07-18 / Adoption step 3
- Decision: The project targets WCAG 2.2 AA as the floor (AAA where feasible) plus EN 301 549 / the European Accessibility Act, applied to **both** the admin panel and the HTML Klytos generates for its users' sites.
- Why: Klytos operates in the EU market and, critically, its users inherit the accessibility of the generated output — under the EAA the legal exposure lands on them for markup they did not write. Measured reality at adoption is ~20–25% (admin) and ~15% (generated output), so this is a target with real work behind it, not a claim.
- Alternatives rejected (and why): WCAG 2.1 AA (what the shipped `klytos-accessibility` skill already asserts — but 2.2 adds focus appearance, target size and dragging movements, which are exactly the gaps measured); generated output only (the admin is also used by people with disabilities).
- Supersedes: none

## D-008 — Docs language: English
- Date / phase: 2026-07-18 / Adoption step 3
- Decision: All `docs/` artifacts and everything else Keel creates for this project are written in English.
- Why: Token economy (Keel's default), and every pre-existing document in the repo is already English — no translation needed and no inconsistency introduced. Conversation with the user continues in Spanish.
- Alternatives rejected (and why): Spanish docs (would cost 15–30% more tokens on every session's state re-read, and would fork the documentation language of an open-source project whose contributors are international).
- Supersedes: none

## D-009 — Design system: existing, token-based; current look is the baseline
- Date / phase: 2026-07-18 / Adoption step 3
- Decision: The existing token system is the design system: `installer/admin/assets/css/klytos-tokens.css` (118 `--klytos-*` tokens) for the admin, and build-time generated tokens from theme config (`installer/core/theme-manager.php`) for the frontend. There are no design source files. The UI predates Keel and has **no design contract**: the current look is the baseline. Keel Phases 3–4 are marked n/a for the existing UI.
- Why: Adoption rule — do not invent a retroactive BUILD-SPEC for a pre-existing UI.
- Alternatives rejected (and why): Reconstructing a design handoff retroactively (fabricates a contract nobody agreed to and would be audited against invented evidence).
- Applies from now on: a redesign goes through Phases 3–4 normally; small changes respect the baseline and the token system (never hardcode a colour).
- Supersedes: none

## D-010 — Assistant config package: full, for six tools
- Date / phase: 2026-07-18 / Adoption step 3
- Decision: The native assistant config package is generated for **Claude Code, OpenAI Codex, Cursor, GitHub Copilot, Gemini CLI and Windsurf** — path-scoped rules, verifier subagents, permission allow-lists where the tool supports them, the confidential-data pre-commit gate (one per project), and MCP registration. Gemini CLI is covered by `.gemini/settings.json` with `context.fileName` including `AGENTS.md` — **no `GEMINI.md` mirror**.
- Why: The project is open source. Forks and contributors will use whatever assistant they already have; binding only Claude would leave the workflow unenforced for most of them. The `context.fileName` route avoids a third copy of the lock block that could drift in a repo with many forks.
- Alternatives rejected (and why): Claude-only package (used on the author's private projects — wrong for a public repo); `GEMINI.md` mirror (more visible on clone, but one more file to keep in sync).
- Standing rule: a "—" cell in the container matrix removes the mechanism, never the duty; container parity is verified at the Phase 7 gate.
- Supersedes: none

## D-011 — Model binding: default role→model map
- Date / phase: 2026-07-18 / Adoption step 3
- Decision: Abstract roles bind to models as follows, materialized in each tool's native model field using that vendor's own identifiers: `orchestrator` → the strongest available model (session driver, judgment-heavy phases); `reviewer` → mid tier (`code-reviewer`, `security-auditor`, `design-fidelity-auditor`); `mechanical` → cheapest tier (`docs-verifier`, `playground-qa`, `launch-verifier`, `a11y-auditor`, `guide-qa`, exploration agents).
- Why: The read-only mechanical verifiers were otherwise inheriting whatever expensive model drove the session. On a flat subscription this buys speed and rate-limit headroom rather than money; on metered billing it is direct spend. Either way there is no quality argument for running a docs-coverage check on the strongest model.
- Alternatives rejected (and why): All roles on the strongest model (maximum cost and rate-limit pressure for no measurable gain on mechanical checks); no agents at all (loses the independent fresh-context reviewer, which is the point of the verifier set).
- Note: concrete model names live only in each tool's config file, never in the skill or the lock — so a model name that ages never reaches a file that travels.
- Materialized: `.claude/agents/` (reviewers `claude-sonnet-5`, mechanical `claude-haiku-4-5`; Cursor reads this tree natively), `.gemini/agents/` (reviewers `gemini-2.5-pro`, mechanical `gemini-2.5-flash`). Codex and Windsurf have no subagent mechanism — the inline fallback runs on the session model, as designed.
- **Open gap — GitHub Copilot:** the `model:` frontmatter key exists in Copilot's custom-agent reference, but no source enumerates its accepted values, and the format is inconsistent across surfaces (VS Code documents display names such as `Claude Opus 4.5` / `GPT-5.2 (copilot)`; community examples use bare slugs; there is a known CLI/VS Code string-vs-array incompatibility, github/copilot-cli#2133). The key was therefore **omitted rather than guessed**: the four `.github/agents/*.agent.md` files each state that the binding falls back to the session model. Re-attempt when Copilot documents the accepted values.
- Supersedes: none

## D-012 — Website intent: yes, Phase 8 deferred
- Date / phase: 2026-07-18 / Adoption step 3
- Decision: The project has website intent (klytos.io, already referenced in code headers, plus the root `index.html` landing page). Phase 8 is **not** run now; it runs on the user's request, normally after a release.
- Why: The immediate priority is the authorization remediation, not the site.
- Alternatives rejected (and why): Auditing the current site now (real value, but it competes with critical security work); no website intent (contradicts reality).
- Supersedes: none

## D-013 — No client budget
- Date / phase: 2026-07-18 / Adoption step 3
- Decision: There is no client to bill and no quote to produce. `docs/budget.md` is not created and the rate/currency/budget-language questions are not asked. `docs/estimate.md` and `docs/token-ledger.md` remain in force.
- Why: Klytos is the author's own open-source project.
- Alternatives rejected (and why): none.
- Supersedes: none

## D-014 — Competitive scan run at adoption
- Date / phase: 2026-07-18 / Adoption step 3
- Decision: The Phase 1 step 0 competitive scan was run (optional in adoption) and recorded in `docs/00-competitive-landscape.md`. It feeds the roadmap; it does not gate anything.
- Why: The user asked for it. The AI-first-CMS space moved fast in 2025–2026 (WordPress's own Abilities API and MCP plugins, headless CMSs shipping official MCP servers), so the positioning needs contrasting against reality rather than assumption.
- Alternatives rejected (and why): Skipping it (would have been recorded as skipped with the standard warning).
- Supersedes: none

## D-015 — Confidential-data pre-commit gate installed and verified
- Date / phase: 2026-07-18 / Adoption step 2 (installed immediately, per the adoption specifics)
- Decision: `.githooks/pre-commit` is committed and `core.hooksPath` is set to `.githooks`. One gate per project, tool-agnostic — a classic git hook fires in every environment (any assistant, any editor, a bare terminal), which is the point in an open-source repo with forks.
- Verification (evidence, not memory): a synthetic secret file was staged and the commit was **blocked** — `BLOCKED (content): keel-gate-test.txt matches a secret pattern` — then the test file was unstaged and deleted. An unverified gate is not a gate.
- Collaborator note: `core.hooksPath` is per-clone. `git config core.hooksPath .githooks` must be documented in the repo's development notes so contributors run it too — **pending**, tracked as part of the audit's H bucket.
- Exemptions: `.githooks/*`, `.claude/skills/*`, `.agents/skills/*` — the canonical trees that legitimately contain the gate's own patterns. The assistant-side check still scans them.
- Why: The repository is public and self-updating; a leaked credential here reaches forks and installed sites. The gate is the net under the assistant-side check, and it also covers commits made outside any Keel session.
- Supersedes: none

## D-016 — Export-ignore extended to every assistant config container
- Date / phase: 2026-07-18 / Adoption step 4
- Decision: `.gitattributes` now export-ignores `AGENTS.md`, `.agents/`, `.codex/`, `.cursor/`, `.gemini/`, `.windsurf/`, `.githooks/`, `.mcp.json`, `.keel-update-check` and the three nested `installer/*/AGENTS.md` context files, alongside the pre-existing `CLAUDE.md` and `.claude/` rules.
- Why: Nothing from the Keel package ships in the distributable. This was previously true only for the Claude containers, because they were the only ones that existed.
- Known adjacent defect NOT fixed here: the blanket `*.md export-ignore` also strips `README.md` and `INSTALL.md` from release archives, although `INSTALL.md` instructs users to upload them. Recorded as audit H-02 for triage, not silently changed during adoption.
- Supersedes: none

## D-017 — Repositioning: lead with the PHP + MCP + static + multilingual intersection; x402 leaves the marketing
- Date / phase: 2026-07-18 / Adoption step 6
- Decision: The product's headline message becomes **"the PHP CMS with MCP and static generation in the core — multilingual out of the box, on the hosting you already have"**. x402 is removed from the marketing message; **the x402 code stays in the product, untouched**. "AI-first CMS" is retired as the differentiating claim.
- Why (evidence, `docs/00-competitive-landscape.md`): (a) "AI-first CMS" is commoditised — WordPress core shipped the Abilities API in 6.9, Lovable is at ~$500M ARR, and Cloudflare's EmDash (MIT, self-hostable, MCP-native, Astro-based, shipped 2026-04-01) matches most of the differentiation list with 11.3k stars to Klytos's 10. (b) x402 shows zero demand across seven CMS repos, ~77% volume collapse and ~50% wash trading, and Cloudflare abandoned pay-per-crawl in July 2026 — EmDash shipping it drew 3 comments out of 504. (c) No PHP CMS has both MCP and static generation in core: Statamic needs Laravel and $349/site, Grav's MCP is a Node sidecar, WordPress's static story is plugin-only with WP2Static dead. (d) Multilingual is the highest-volume unmet defect cluster in the category (Tina has no i18n; Decap's issue open since 2020) and Klytos's 20 in-sync locales appear nowhere in its positioning.
- Time limit on this: the official PHP MCP SDK is now Symfony / PHP-Foundation maintained, so the empty intersection should be expected to fill within roughly twelve months.
- Alternatives rejected (and why): keeping x402 in the marketing (the evidence is not merely absent, it is negative — and it costs credibility on the pillars that are real); keeping "AI-first CMS" as the headline (out-marketed and out-resourced on that exact claim); dropping x402 from the code too (it costs nothing to keep, and the thesis may return).
- Consequences: `README.md`, the root `index.html` landing page and any future Phase 8 site follow this message. Audit D-05 (README's stale "160+ tools / 75+ hooks" vs the real 206 and 411) is fixed in the same work — on the extensibility axis the project undersells itself more than fivefold.
- Note recorded honestly: the biggest risk identified by the scan is **not** competition but distribution — 10 stars and a bus factor of 1, with three architecturally similar projects (LightCMS 23 stars, Seite 13, VoxelSite 4) documented as having been right and gone nowhere. Repositioning addresses visibility; it does not address the bus factor.
- Supersedes: none

## D-018 — Adoption audit triaged
- Date / phase: 2026-07-18 / Adoption step 5
- Decision: **Fix now (Sprint 1):** S-01…S-09 + T-01 — the whole authorization axis, the SSRF, the broken public comment handler, and a minimal test harness. **Fix when touched:** the remaining 23 findings, each bound to a named trigger in `docs/04-adoption-audit.md`. **Accepted: none** — nothing was written off as permanent.
- Why: Authorization is the weak axis of a product whose premise is handing control to an autonomous agent — that ordering is not negotiable. T-01 joins it because an authorization fix cannot be demonstrated by reading a diff; Keel's test-point rule needs a command and an output.
- Alternatives rejected (and why): critical-only (S-01/S-02/S-03) — leaves S-07's systemic ~70% ungated surface open, so the same class of bug returns with the next endpoint; adding H-01…H-04 to Sprint 1 — real defects, but the next release runs the full Phase 7 where they close by construction, and widening Sprint 1 delays the security work.
- Supersedes: none

## D-019 — Competitive scan recorded as partial; no user-supplied competitors
- Date / phase: 2026-07-18 / Adoption step 3 (asked late — the scan had already run)
- Decision: `docs/00-competitive-landscape.md` stands as written, with its **partial** status on the record. The user reported no additional competitors beyond those in the report.
- Why: Phase 1 step 0's opening question ("which competitors do you already know about?") was skipped when the scan was launched; it was asked afterwards and answered "none relevant". Recording the process gap rather than pretending the sequence was correct.
- Uncovered surfaces, named so a later pass knows where to look: Reddit required a mirror, the 200-call search budget was exhausted, and Lobsters, IndieHackers, ProductHunt and WPTavern went unexamined. These are *uncovered*, not *negative evidence*.
- Supersedes: none

## D-020 — MCP tool authorization deferred to Sprint 2, not folded into Sprint 1
- Date / phase: 2026-07-18 / Phase 5, Sprint 1 planning
- Decision: The newly found gap NEW-02 — `klytos_has_permission` appears **zero** times across all 34 files and 172 tools in `installer/core/mcp/tools/` — is fixed in a **dedicated Sprint 2**, not added to Sprint 1. Sprint 1 builds the `klytos_require_permission()` helper that Sprint 2 reuses.
- Why: Gating 172 tools needs its own tool→capability map, an enforcement point in `ToolRegistry`, and its own test set. Folding it into Sprint 1 would roughly double the sprint and delay the admin-side authorization fix that is already triaged and planned. A sprint that cannot close is worse than two that can.
- Stated plainly, not buried: **when Sprint 1 closes, the admin surface is gated and the product's primary interface is not.** MCP auth proves *who* the caller is, never *what* they may do, so any app-password holder currently has owner-level power over the CMS.
- Alternatives rejected (and why): adding it to Sprint 1 (no window where admin is gated and MCP is not — but Sprint 1 stops being closeable); a coarse blanket owner-gate in `ToolRegistry` now (closes the escalation fast, but over-restricts legitimate editor/viewer MCP use until the fine-grained map lands, and an over-restrictive gate tends to get reverted under pressure rather than refined).
- Supersedes: none. Extends the D-018 triage with a finding that did not exist when it was made.

## D-021 — `klytos_current_user()` fails closed, with a migration for the installed base
- Date / phase: 2026-07-18 / Phase 5, Sprint 1 planning
- Decision: The v1.x compatibility fallback in `klytos_current_user()` (`installer/core/helpers-global.php:390-397`), which returns a hardcoded `'role' => 'owner'` whenever `$auth->getUserId()` is empty or `UserManager` throws, is changed to return **null (deny)**. The existing `UserManager::migrateFromV1Config()` (`user-manager.php:646`) is wired into boot as an idempotent migration so live v1.x installs get a real owner record instead of being locked out.
- Why: Any authenticated session lacking `klytos_user_id` is currently promoted to owner silently. This defeats **every** gate Sprint 1 adds, which makes it a prerequisite rather than an addition. It was not in the adoption audit — it was found during the Sprint 1 kickoff re-validation, which is precisely what that step exists for.
- Alternatives rejected (and why): remove the fallback with no migration (safest against escalation, fastest to ship — but a v1.x install whose migration has not run locks out its owner, and this is a self-updating CMS with production installs, so that is a real support incident); keep it, narrow it and log it to measure real-world frequency (lowest breakage risk, but leaves the escalation open for the whole sprint while the sprint's entire purpose is closing it).
- Verification required (not optional): the upgrade path is tested **from the real previous version**, not only from a clean install — mandatory whenever `Installed base: yes`.
- Supersedes: none.

## D-022 — Dev-only `composer.json`, plus a manifest for the vendored dependencies
- Date / phase: 2026-07-18 / Phase 5, Sprint 1 planning
- Decision: A root `composer.json` is added with **`require-dev` only** (`phpunit/phpunit`, `squizlabs/php_codesniffer`). The runtime stays dependency-free. `composer.json`, `composer.lock` and `vendor/` are export-ignored so nothing ships. Additionally, a manifest is reconstructed for `installer/vendor-ai/` (482 files, 9 packages) so the vendored code can be audited with `composer audit`.
- Why: PHPUnit and PHPCS exist only as global installs on the author's machine, so "lint and tests pass" is not reproducible for a contributor or in CI — which makes every test-point claim unverifiable by anyone else. The vendored half (audit H-04, HIGH) cannot be checked against CVEs at all today; for a self-updating CMS with production installs, an unauditable dependency tree is a standing risk, not a hygiene nit.
- Alternatives rejected (and why): dev-only manifest without the `vendor-ai/` work (cheaper and lower-risk, but leaves H-04 HIGH open with no trigger to close it); no manifest at all (preserves the adopted state exactly, but keeps tests unreproducible and blocks CI, which is the same failure mode T-01 already records as CRITICAL).
- Standing rule for the CVE audit: findings are **reported and triaged with the user, never silently patched**. An upgrade across 482 vendored files is a scope change (→ Estimate v2), not a slice detail.
- Supersedes: the `docs/03-technical-plan.md` §1 dependency-manifest line ("no dependency manifest at project level") — an as-built record, not a prior decision. That line is updated in the same slice.

## D-023 — Theme becomes an installable, validated package; design enters via an authoring skill
- Date / phase: 2026-07-18 / Phase 5 (scope change, designed only — not implemented)
- Decision: The frontend visual layer is redesigned around a **theme package**: a directory with a manifest (`klytos-theme.json`, ID = directory name, mirroring the immutable plugin contract) owning its templates, parts, CSS, self-hosted fonts and assets, plus style variations. Klytos stops shipping a look and instead guarantees a **contract** in three parts — placeholder vocabulary, an expanded frontend token system, and a landmark/accessibility contract — while fixing nothing about arrangement. A design produced by Claude Design (or any agent) enters through a new `klytos-theme-authoring` skill and is **refused at install time by a validator** if it violates the contract. Templates gain `extends` inheritance. The design brief becomes a machine-readable artifact stored with the theme. Full spec: `docs/theme-package-model.md`.
- Why: The starting premise (that Klytos over-restricts header/footer) is false and the code disproves it — `klytos_set_part` already accepts completely free HTML/CSS for any named part (`installer/core/mcp/tools/part-tools.php:88-89`). The real defect is that this freedom has **no contract**: the design brief is never persisted (prose in a chat transcript, `installer/core/guides/site-builder.md:85-166`), the frontend exposes only 14 CSS variables against the admin's ~118, base CSS is a 243-line heredoc inside PHP (`build-engine.php:1543`), distinctive design is deliberately steered into per-page `wp:html` blobs outside every system, and nothing is portable or versionable. Decisively, audit A-05 (HIGH) records **zero** `aria-`/`role=` attributes in the generated output: an unvalidated free-HTML part system makes that permanently unfixable, and under the EAA the exposure lands on Klytos's users (D-007). A validated contract is the only mechanism that fixes accessibility at the system level rather than once per site.
- Alternatives rejected (and why): extending the current record with more knobs (leaves base CSS in PHP and the design as a `custom_css` blob — treats the symptom); a freeform HTML/CSS importer as the canonical path (no contract, so unpredictable results, unmaintainable output and no accessibility guarantee — it may exist later as an assisted converter that still emits a package through the same validator).
- Consequences: closes the shipped model drift — `klytos_set_part` vs the superseded global-blocks model still taught by the `klytos-custom-templates` skill, which no shipped skill reconciles today. The structural heuristics in `build-engine.php:553-660` (raw `<header` string matching + top-bar auto-injection) are replaced by manifest-declared structure. A default package generated from today's `getBaseCss()` + the 26 scalars keeps every production install rendering byte-identically (installed base is real).
- Relation to D-009: does **not** supersede it. D-009 records that the pre-Keel UI has no design contract and that the current look is the baseline; that remains true for the admin. D-020 establishes, for the frontend and on the new model, the contract D-009 recorded as absent.
- Sequencing (explicit user decision): **planned now, implemented after Phase 5 Sprint 1** (S-01…S-09 + T-01, D-018). The authorization axis does not wait for a theme redesign. As a genuine redesign it then runs through Keel Phases 3–4 — the first time this project does — which is also the review trigger for the deferred `design-fidelity-auditor`.
- Supersedes: none

## D-024 — The design agent connects over MCP as an authoring loop, package-scoped and preview-only
- Date / phase: 2026-07-18 / Phase 5 (scope change, designed only — not implemented; extends D-023)
- Decision: Klytos exposes a dedicated **design authoring surface** over its existing MCP server so an external design agent (Claude Design or any other MCP-capable agent) can connect to a site and work against it. The surface is deliberately shaped as a **read → validate → preview → iterate loop whose unit of exchange is the theme PACKAGE, never an individual mutation**: the agent reads the contract and a description of the site, submits a complete package, receives concrete validation errors or a rendered preview, and iterates. It works **only against a preview target**; activating a theme on the published site is a separate human action. Individual write tools (`klytos_set_part`, `set_colors`, `custom_css`) are NOT part of this surface.
- Why: The obvious shape — letting the design agent push the design live, mutation by mutation — was rejected because it reproduces the exact defect D-023 diagnoses: imperative, runtime, unvalidated, site-bound design that is not portable, not versionable and not auditable. It would also be no new capability, since the MCP server already exposes those write tools today. What the design agent genuinely lacks is (a) the ability to READ the site and the contract it must satisfy — today it designs blind, which is why the brief degenerates into prose — and (b) a feedback loop with real rendered output. MCP is the right transport for both, and package-scoping keeps D-023's invariant intact: the package is the artifact, the validator is the gate.
- Security rationale (this is the decisive constraint, not a footnote): `klytos_set_part` accepts a `js` parameter that is emitted as a `<script>` tag on **every page**, and stores `html` without passing it through `kses` (`installer/core/mcp/tools/part-tools.php:88-99`, `installer/core/part-manager.php:84-85`). A live-write design surface therefore hands an external agent global stored-XSS on a production site, on the very axis (authorization) the project already records as its weakest — D-018, S-01…S-09. Preview-only + package-scoped + a dedicated `theme.*` capability + separate human activation bounds the blast radius. Applies both security profiles per D-003; the stricter wins.
- Alternatives rejected (and why): **live direct write** (immediate and superficially simpler, but recreates the diagnosed defect and opens the JS-injection path above); **read-only** (safest and simplest, package delivered out of band as a file — but it drops the render-feedback loop, which is half the value and the reason the current prose-brief model fails).
- Open unknown, deliberately NOT assumed: whether Claude Design can act as an MCP client against an arbitrary server has **not** been verified. The surface is therefore specified for a generic MCP-capable agent, so it remains useful if Design cannot connect directly. Verify before implementation.
- Supersedes: none — extends D-023.


## D-025 — Gate zero is baseline-locked, not "phpcs clean"
- Date / phase: 2026-07-18 / Phase 5, Sprint 1 slice 0
- Decision: The project's gate zero, defined in `docs/03-technical-plan.md` §4 as *phpcs clean + the app boots + one MCP `tools/list` round trip*, is amended. The lint condition becomes: **zero violations in the files a slice touches, and the measured baseline does not grow.** The baseline is recorded as **204 errors / 488 warnings across 114 files** in `installer/core` + `installer/admin`, measured with `phpcs --standard=phpcs.xml --report=summary` on 2026-07-18.
- Why: The literal condition was never achievable — the baseline predates Keel and has never been clean, so gate zero as written would block every slice from starting, which is the opposite of its purpose. Baseline-locking is the standard treatment for a brownfield codebase: new code enters clean, old code is cleaned when it is touched anyway.
- Verified before deciding, not assumed: both files added in slice 0 (`scripts/dev/seed-playground.php`, `scripts/dev/router.php`) lint **clean** under the project ruleset. The problem is inherited, not introduced.
- Alternatives rejected (and why): running `phpcbf` now over the 114 files (203 of 204 are auto-fixable, and it would satisfy the literal gate — but it produces an enormous diff across almost all of `installer/` immediately before a security sprint, with **no test harness yet** to catch a regression, and it would be reviewed tangled up with the authorization fixes); deferring the sweep to a dedicated slice after Sprint 1 (tidier, but adds pending work that tends to stay pending — the trigger below covers the same ground without a standing debt).
- Trigger for reducing the baseline: opportunistic. Any slice that touches a file leaves it clean. The baseline number is re-measured at each sprint close and recorded — it may not increase.
- Note: the IDE in use reports **WordPress Coding Standards** diagnostics for this repository, which contradict D-004 (PSR-12 with spaces inside parentheses, `camelCase` locals). Those diagnostics are noise and are ignored; `phpcs --standard=phpcs.xml` is the only authority.
- Supersedes: the gate-zero definition in `docs/03-technical-plan.md` §4 (as-built record, amended in the same change).

## D-026 — NEW-03 and NEW-04 are fixed after Sprint 1, not inside it
- Date / phase: 2026-07-18 / Phase 5, Sprint 1 slice 0
- Decision: The two production bugs found by the playground's first boot are recorded and **not** fixed in Sprint 1. **NEW-03** (by-reference action listeners never bind; every page create warns and the x402 default injection silently does nothing) gets its own slice with tests. **NEW-04** (`build` writes into the repository root, overwriting the tracked `.htaccess`) is bound to the theme-package sprint (D-023), which needs a safe build target regardless.
- Why: D-018 prioritized the authorization axis above everything, and that ordering is not re-opened on the assistant's initiative. NEW-03's correct fix changes the signature of `Hooks::doAction()`, which backs 301 registered actions — that is a deliberate, separately-tested change, and doing it before the test harness exists (slice 1) would be exactly the kind of unverified core surgery this sprint exists to make impossible.
- Accepted cost, stated plainly: the NEW-03 warning will pollute the output of every test point in this sprint (mitigated by `XDEBUG_MODE=off`, documented in `docs/playground.md`), and x402's post-type default stays broken in production meanwhile.
- Alternatives rejected (and why): fixing NEW-03 inside Sprint 1 after slice 1 (defensible — the harness would exist — but it still widens a sprint whose scope was deliberately bounded); fixing both before continuing (cleanest ground to build on, but delays precisely the work triaged as most urgent).
- Supersedes: none.

## D-027 — PHPUnit is pinned to ^11.5, and `composer.lock` is tracked
- Date / phase: 2026-07-19 / Phase 5, Sprint 1 slice 1
- Decision: The dev manifest (D-022) pins `phpunit/phpunit: ^11.5` and `squizlabs/php_codesniffer: ^3.13`, and **`composer.lock` is committed** — the pre-Keel `.gitignore` line that excluded it is removed. Both files stay out of the distributable through `export-ignore`, never through `.gitignore`.
- Why: D-022 named the two packages but not their versions, and the point of the whole manifest is that a contributor and CI resolve **the same** versions the author ran — an ignored lock file defeats exactly that, so tracking it is implementing D-022, not amending it. `^11.5` matches the PHPUnit already installed globally on the author's machine (11.5.53), so the existing workflow and its results are reproduced rather than migrated in a security sprint.
- Consequence, stated rather than discovered later: **PHPUnit 11 requires PHP 8.2+, while Klytos itself supports PHP 8.1+ (D-004).** The test suite therefore cannot run on the lowest supported runtime, so CI cannot verify PHP 8.1 through the suite. Nothing in the product changes; the gap is in verification coverage only.
- Alternatives rejected (and why): **PHPUnit ^10** — the only line still supporting PHP 8.1, which would close the coverage gap above, but it reached end of life in Feb 2025, so the harness would start on an unmaintained runner in a sprint whose whole purpose is security. **PHPUnit ^12** — current and supported, but requires PHP 8.3+, widening the same gap by two minor versions for no gain here.
- Trigger for revisiting: when the support matrix is next verified, or when CI is introduced (whichever comes first) — decide then whether PHP 8.1 support is real enough to warrant a runner the suite can actually execute on, or whether the floor should rise to 8.2.
- Supersedes: none — implements D-022.

## D-028 — The `vendor-ai/` manifest pins every package exactly, transitive ones included, and does not block on advisories
- Date / phase: 2026-07-19 / Phase 5, Sprint 1 slice 2
- Decision: The manifest reconstructed for the vendored AI tree lives at **`installer/composer.json`** with `config.vendor-dir = "vendor-ai"`, and `installer/composer.lock` is tracked beside it. It pins **all 16 packages at exact versions**, transitive dependencies included — not just the single genuine root requirement (`soukicz/llm` 0.5.0). `config.platform.php` is set to `8.3.0`, and `config.audit.block-insecure` is set to `false`.
- Why (each part, because each was a real choice):
  - **Placement.** `vendor-ai/composer/installed.php` records the root package's `install_path` as `installer/`, so `installer/` with `vendor-dir: vendor-ai` is the layout that actually produced this tree. Any other placement would need a relative-path hack and would describe a tree nobody built.
  - **Exact pins on transitive packages too.** Requiring only `soukicz/llm` would let Composer resolve its dependencies to *newer* versions than the ones vendored — so `composer audit` would report on a tree that does not ship, which is worse than no audit. Verified: the lock resolves to the 16 vendored versions with zero deltas.
  - **`platform.php = 8.3.0`.** Makes resolution independent of whichever PHP a contributor runs. It also states NEW-06 out loud: `soukicz/llm` requires PHP >= 8.3 while Klytos declares 8.1+.
  - **`block-insecure: false`.** Composer 2.9 refuses to resolve packages with known advisories, which made the lock ungeneratable — the manifest could not describe reality precisely *because* reality is vulnerable. The manifest's job is to record what ships; `composer audit` is the reporting mechanism, and it reports fully with this flag off.
- Alternatives rejected (and why): a manifest under `scripts/dev/` (keeps the product tree untouched, but misrepresents the layout and needs `../../` vendor-dir plumbing); requiring only the root package (faithful to the original authored manifest, but audits a resolved set that is not the shipped one); leaving `block-insecure` on (the resolution failure is itself a signal — but it yields no lock, hence no `composer audit`, hence no per-CVE detail to triage, which is the whole deliverable).
- Distribution: both files are already export-ignored by the existing unanchored `composer.json` / `composer.lock` rules in `.gitattributes` — verified with `git check-attr export-ignore`, not assumed. Nothing new ships.
- Drift guard, not a promise: `tests/Unit/VendorAiManifestTest.php` asserts manifest ≡ lock ≡ `composer/installed.php` ≡ `LICENSE-THIRD-PARTY.md`, in both directions. Proven to fail on injected drift before it was trusted (evidence in `docs/05-test-points.md`).
- Standing rule reaffirmed (D-022): the CVEs this manifest exposed (NEW-05) are **reported and triaged with the user, never silently patched**.
- Supersedes: the `docs/03-technical-plan.md` §1 line "Vendored deps … no manifest" and its "Risk, recorded not fixed" note — as-built records, updated in the same slice. Closes audit H-04.

## D-029 — NEW-05's five CVEs are remediated in a dedicated slice AFTER Sprint 1, not inside it
- Date / phase: 2026-07-19 / Phase 5, Sprint 1 slice 2 (user triage, per D-022's standing rule)
- Decision: The 5 medium CVEs found by the project's first `composer audit` — `guzzlehttp/guzzle` 7.10.0 (CVE-2026-55767, CVE-2026-55568) and `guzzlehttp/psr7` 2.9.0 (CVE-2026-55766, CVE-2026-49214, CVE-2026-48998) — are **recorded and not fixed in Sprint 1**. Remediation becomes its own slice immediately after Sprint 1 closes, with its own test point and its own estimate version.
- Why: (a) D-018 put the authorization axis above everything and that ordering is not re-opened on the assistant's initiative; (b) reachability was assessed rather than assumed and is low — `vendor-ai/` loads lazily from a single call site, there is no cookie jar and no user-controllable URL in the AI module, so four of the five have no path in this codebase; (c) re-vendoring touches 482 tracked files in a **released** product with an installed base, which needs a verification step of its own — "does AI chat still work after the bump?" — that the playground cannot fully exercise without a real provider API key.
- Scope of the future slice, fixed now so it is not re-litigated: bump `guzzlehttp/guzzle` to ≥ 7.12.1 and `guzzlehttp/psr7` to ≥ 2.12.1 via `composer install -d installer`, review the resulting diff, re-run `composer audit -d installer` to zero, update `LICENSE-THIRD-PARTY.md` versions, and let `tests/Unit/VendorAiManifestTest.php` prove the four records still agree. **Verified at triage time, so the cost is known:** `soukicz/llm` 0.5.0 requires `guzzlehttp/guzzle: ^7.9`, so both fixes are constraint-compatible — a re-vendor, not a dependency-tree rewrite. It carries an Estimate v2.
- Stated plainly rather than buried: until that slice runs, every Klytos install that uses AI chat carries five known-vulnerable dependencies. The one with a real path is **CVE-2026-55568** — Guzzle honours `HTTP_PROXY`/`HTTPS_PROXY` from the environment whether the application asks or not, so on a host that sets them an LLM API key can leave over cleartext. Klytos cannot prevent this from application code.
- Alternatives rejected (and why): **re-vendor inside Sprint 1** (cheap while the manifest is fresh, and the sprint is security work — but the sprint's subject is *authorization* specifically, and a 482-file diff reviewed alongside the gate work is exactly the tangling D-025 refused for `phpcbf`); **guzzle-only minimal fix** (closes the single reachable CVE with the smallest diff — genuinely defensible, but it splits one re-vendor into two, doubling the "does AI chat still work" verification for no reduction in total work); **accept permanently** (rejected — nothing in this project's triage has been written off as permanent, and a self-updating CMS with production installs carrying known-vulnerable code needs a closing date, not an acceptance).
- Supersedes: none — applies D-022's standing rule to its first real finding.

## D-030 — The integration tier isolates by snapshot/restore of the whole playground, and fails loudly where it cannot
- Date / phase: 2026-07-19 / Phase 5, Sprint 1 — deferred item resolved before slice 3
- Decision: The integration tier snapshots `installer/config/` and `installer/data/` before every test and restores them afterwards (`tests/PlaygroundState.php`, used by `IntegrationTestCase`). Isolation is **ON by default for every test in the tier**, opt-out only with a recorded reason. Where a file-level restore provably cannot reach — in-memory caches held by the booted App singleton — the primitive does **not** pretend to cover it: `assertConfigNotMutated()` fails the test with instructions instead.
- Why: The slice-1 `code-reviewer` pass raised that the tier shares the App singleton *and* the real on-disk playground with no rollback. Slice 1's tests were read-only, so nothing broke; slices 3-5 assert refusals on state-changing surfaces, and they would have become order-dependent within a run while permanently mutating the playground across runs.
- Why snapshot/restore and not per-test fixtures (the sprint file left both open): **the requirement decides it.** Slice 3's own test point requires proving the v1.x migration is idempotent, which means DELETING the seeded owner and re-running boot. That record belongs to the seed, not to the test, so a create-and-destroy fixture cannot express it. The cost objection does not apply either — a seeded playground is **31 files / ~124K**, so a full copy per test is microseconds; measured suite time went from 0.049s to 0.187s for 14 tests.
- Verified before deciding, not assumed (subagent scan of `installer/core/`): the storage layer memoizes **nothing** — `FileStorage::read/list/count/exists`, `UserManager` and `SiteConfig` touch the filesystem on every call — which is exactly why a file-level restore is sound for the users, pages and site-config records slices 3-5 assert against. Four caches survive a restore and are named in the trait's docblock with file:line: `App::$config` (app.php:217, read once at boot, **no invalidation path at all**), `EncryptionLevelTrait::$cachedEncryptionLevel` (held by the live storage object — the sharpest, because a stale value corrupts writes rather than reading stale data), `OptionsManager::$cache` plus its static `$sensitivityRegistry`, and `AiKeyManager::$cache`.
- Proven, not asserted: `tests/Integration/PlaygroundIsolationTest.php` deletes a seeded user, confirms the mutation took effect and the on-disk fingerprint changed, then asserts the next test sees the untouched playground — record restored **and** byte-for-byte fingerprint match, including permission bits. Demonstrated to FAIL with isolation disabled (`testBPlaygroundIsRestoredForTheNextTest` → "the playground was NOT rolled back") before being trusted; the proof run really did delete the user, and the playground was reseeded afterwards.
- Alternatives rejected (and why): **per-test fixtures** (cheapest and needs no primitive, but cannot express slice 3's migration test, as above); **adding `App::reset()` to the product** (would close the `App::$config` gap properly — but it is core surgery on a singleton in a sprint whose subject is authorization, exactly the tangling D-025 refused for `phpcbf` and D-026 refused for `Hooks::doAction()`); **reflection on the private `$instance`/`$config`** (no product change, but silently couples the harness to private internals that may be renamed with no compiler to catch it); **opt-in isolation per test** (cheaper, but which tests mutate is precisely what cannot be known in advance — a helper three calls deep writing a rate-limit counter is still a mutation).
- Ordering detail that is load-bearing: the snapshot is taken **before** `App::boot()`, because boot itself writes (Step 10b's migration, the action scheduler). Restore runs before the config assertion, so the playground is left correct even when the assertion fails.
- Trigger for revisiting: the first test that genuinely needs to mutate core config, options, the encryption level or AI keys. At that point either run it with `#[RunInSeparateProcess]` or reopen the `App::reset()` question as its own change — not in passing.
- Supersedes: none. Resolves the "Storage isolation for the integration tier" deferred item (PROGRESS.md) and sprint-1 risk 4.
